<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>
            <a href="{{ route('articles.index') }}">
                <x-secondary-button type="button">Alle Artikel</x-secondary-button>
            </a>
        </div>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <div class="px-4 sm:px-0"><x-undo-flash /></div>

            @php
                $count = fn (int $n, string $one, string $many) => $n === 1 ? "1 $one" : "$n $many";
                // Hinweise auf nicht mitgezählte Verkäufe, je mit Link auf die Liste zum Nachtragen.
                $gaps = fn (array $parts) => array_values(array_filter(array_map(
                    fn ($text, $type) => $missing[$type] ? [$text($missing[$type]), route('pending.index', $type)] : null,
                    $parts,
                    array_keys($parts),
                )));
                $tiles = [
                    ['Verfügbar', $stats['available'], 'von '.$stats['total'].' Artikeln', route('articles.index', ['hide_sold' => 1]), false, []],
                    ['Verkauft', $stats['sold'], $stats['soldThisMonth'].' in diesem Monat', route('articles.index', ['sold' => 1]), false, []],
                    [
                        'Umsatz',
                        \App\Models\Article::euro($stats['revenue']),
                        'aller verkauften Artikel',
                        route('articles.index', ['sold' => 1]),
                        false,
                        $gaps(['ohne-preis' => fn ($n) => $count($n, 'Verkauf ohne Preis', 'Verkäufe ohne Preis')]),
                    ],
                    [
                        'Ø Rabatt',
                        $stats['discount'] ? number_format($stats['discount']['percent'], 0, ',', '.').' %' : '–',
                        $stats['discount'] ? 'Ø '.\App\Models\Article::euro($stats['discount']['amount']).' bei '.$count($stats['discount']['count'], 'Verkauf', 'Verkäufen') : 'noch keine Verkäufe mit Preis',
                        null,
                        false,
                        $gaps(['ohne-preise' => fn ($n) => $count($n, 'Verkauf ohne beide Preise', 'Verkäufe ohne beide Preise')]),
                    ],
                    [
                        'Noch offen',
                        \App\Models\Article::euro($stats['openAmount']),
                        $count($paymentPendingCount, 'Zahlung ausstehend', 'Zahlungen ausstehend'),
                        route('pending.index', 'zahlung'),
                        $paymentPendingCount > 0,
                        $gaps([
                            'zahlung-ohne-preis' => fn ($n) => "$n ohne Preis",
                            'ohne-versandkosten' => fn ($n) => "$n ohne Versandkosten",
                        ]),
                    ],
                ];
            @endphp
            <div class="grid grid-cols-2 gap-3 px-4 sm:grid-cols-3 sm:px-0 lg:grid-cols-5 lg:gap-4">
                @foreach ($tiles as [$label, $value, $hint, $href, $warn, $gaps])
                    @php($tag = $href ? 'a' : 'div')
                    <div @class(['flex min-w-0 flex-col rounded-lg bg-white shadow-sm transition', 'hover:shadow-md' => $href, 'col-span-2 sm:col-span-1' => $loop->last])>
                        <{{ $tag }} @if ($href) href="{{ $href }}" @endif @class(['block p-4', 'pb-2' => $gaps])>
                            <div class="text-sm text-gray-500">{{ $label }}</div>
                            <div @class(['mt-1 text-2xl font-semibold tabular-nums', 'text-amber-700' => $warn, 'text-gray-900' => ! $warn])>{{ $value }}</div>
                            <div class="mt-0.5 truncate text-xs text-gray-500">{{ $hint }}</div>
                        </{{ $tag }}>
                        @if ($gaps)
                            <div class="flex items-start gap-1 px-4 pb-4 text-xs font-medium text-amber-700" title="Diese Artikel fehlen in der Summe.">
                                <svg class="mt-px h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" /></svg>
                                <span>
                                    @foreach ($gaps as [$text, $url])
                                        <a href="{{ $url }}" class="underline decoration-amber-300 underline-offset-2 hover:text-amber-900 hover:decoration-amber-700">{{ $text }}</a>@if (! $loop->last) · @endif
                                    @endforeach
                                    – nicht mitgezählt
                                </span>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="grid grid-cols-1 gap-4 px-4 sm:gap-6 sm:px-0 lg:grid-cols-2">
                <x-dashboard-list title="Zahlung ausstehend" accent="amber" mark="paid" :articles="$paymentPending" :count="$paymentPendingCount"
                                  :href="route('pending.index', 'zahlung')" empty="Alle Verkäufe sind bezahlt." />
                <x-dashboard-list title="Versand/Abholung ausstehend" accent="amber" mark="shipped" :articles="$shippingPending" :count="$shippingPendingCount"
                                  :href="route('pending.index', 'versand')" empty="Nichts zu verschicken oder abzuholen." />
                <x-dashboard-list title="Zuletzt verkauft" :articles="$recentlySold" empty="Noch nichts verkauft." />

                <section class="flex min-w-0 flex-col rounded-lg bg-white shadow-sm">
                    <header class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
                        <h3 class="font-semibold text-gray-900">Kategorien</h3>
                        <a href="{{ route('categories.create') }}" class="text-sm text-gray-500 hover:text-gray-800">+ Neue</a>
                    </header>
                    @if ($categories->isEmpty())
                        <p class="px-4 py-6 text-sm text-gray-500">Noch keine Kategorien vorhanden.</p>
                    @else
                        <ul class="divide-y divide-gray-100">
                            @foreach ($categories as $category)
                                @php($share = $category->articles_count ? round(100 * ($category->articles_count - $category->available_count) / $category->articles_count) : 0)
                                <li>
                                    <a href="{{ route('categories.show', $category) }}" class="block px-4 py-2.5 transition hover:bg-gray-50">
                                        <div class="flex items-baseline justify-between gap-3 text-sm">
                                            <span class="truncate font-medium text-gray-900">{{ $category->name }}</span>
                                            <span class="shrink-0 tabular-nums text-gray-500">{{ $category->available_count }} von {{ $category->articles_count }} verfügbar</span>
                                        </div>
                                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-gray-100" title="{{ $share }} % verkauft">
                                            <div class="h-full rounded-full bg-gray-800" style="width: {{ $share }}%"></div>
                                        </div>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>
        </div>
    </div>
</x-app-layout>

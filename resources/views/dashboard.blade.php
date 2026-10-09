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
            <x-flash />

            @php
                $tiles = [
                    ['Verfügbar', $stats['available'], 'von '.$stats['total'].' Artikeln', route('articles.index', ['hide_sold' => 1]), false],
                    ['Verkauft', $stats['sold'], $stats['soldThisMonth'].' in diesem Monat', route('articles.index'), false],
                    ['Umsatz', \App\Models\Article::euro($stats['revenue']), 'aller verkauften Artikel', null, false],
                    [
                        'Ø Rabatt',
                        $stats['discount'] ? number_format($stats['discount']['percent'], 0, ',', '.').' %' : '–',
                        $stats['discount'] ? 'Ø '.\App\Models\Article::euro($stats['discount']['amount']).' bei '.$stats['discount']['count'].' Verkäufen' : 'noch keine Verkäufe mit Preis',
                        null,
                        false,
                    ],
                    ['Noch offen', \App\Models\Article::euro($stats['openAmount']), $paymentPendingCount === 1 ? '1 Zahlung ausstehend' : $paymentPendingCount.' Zahlungen ausstehend', route('articles.index', ['pending' => ['payment']]), $paymentPendingCount > 0],
                ];
            @endphp
            <div class="grid grid-cols-2 gap-3 px-4 sm:grid-cols-3 sm:px-0 lg:grid-cols-5 lg:gap-4">
                @foreach ($tiles as [$label, $value, $hint, $href, $warn])
                    @php($tag = $href ? 'a' : 'div')
                    <{{ $tag }} @if ($href) href="{{ $href }}" @endif @class(['block rounded-lg bg-white p-4 shadow-sm transition', 'hover:shadow-md' => $href])>
                        <div class="text-sm text-gray-500">{{ $label }}</div>
                        <div @class(['mt-1 text-2xl font-semibold tabular-nums', 'text-amber-700' => $warn, 'text-gray-900' => ! $warn])>{{ $value }}</div>
                        <div class="mt-0.5 truncate text-xs text-gray-500">{{ $hint }}</div>
                    </{{ $tag }}>
                @endforeach
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <x-dashboard-list title="Zahlung ausstehend" accent="amber" :articles="$paymentPending" :count="$paymentPendingCount"
                                  :href="route('articles.index', ['pending' => ['payment']])" empty="Alle Verkäufe sind bezahlt." />
                <x-dashboard-list title="Versand ausstehend" accent="amber" :articles="$shippingPending" :count="$shippingPendingCount"
                                  :href="route('articles.index', ['pending' => ['shipping']])" empty="Nichts zu verschicken." />
                <x-dashboard-list title="Zuletzt verkauft" :articles="$recentlySold" empty="Noch nichts verkauft." />

                <section class="flex flex-col bg-white shadow-sm sm:rounded-lg">
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

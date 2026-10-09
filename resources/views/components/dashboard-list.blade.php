@props(['title', 'articles', 'count' => null, 'href' => null, 'empty', 'accent' => 'gray', 'mark' => null, 'edit' => false])

@php($labels = ['paid' => 'Bezahlt', 'shipped' => 'Versendet', 'picked_up' => 'Abgeholt'])

<section class="flex min-w-0 flex-col rounded-lg bg-white shadow-sm">
    <header class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
        <h3 class="flex items-center gap-2 font-semibold text-gray-900">
            {{ $title }}
            @if ($count)
                <span @class([
                    'inline-block min-w-[1.5rem] rounded-full px-2 text-center text-xs leading-5',
                    'bg-amber-100 text-amber-800' => $accent === 'amber',
                    'bg-gray-100 text-gray-600' => $accent !== 'amber',
                ])>{{ $count }}</span>
            @endif
        </h3>
        @if ($href && $count)
            <a href="{{ $href }}" class="text-sm text-gray-500 hover:text-gray-800">Alle &rarr;</a>
        @endif
    </header>

    @if ($articles->isEmpty())
        <p class="px-4 py-6 text-sm text-gray-500">{{ $empty }}</p>
    @else
        <ul class="divide-y divide-gray-100">
            @php($shownBundles = [])
            @foreach ($articles as $article)
                {{-- Bestellung als eine Zeile; Schnell-Buttons wirken über den ersten Artikel auf alle. --}}
                @continue($article->bundle_id && in_array($article->bundle_id, $shownBundles, true))
                @php($group = $article->bundle_id ? $article->bundle->articles : null)
                @if ($group)
                    @php($shownBundles[] = $article->bundle_id)
                @endif
                @php($flag = $mark === 'shipped' && $article->pickup ? 'picked_up' : $mark)
                @php($markLabel = $flag ? $labels[$flag] : null)
                <li class="flex items-center transition hover:bg-gray-50">
                    <a href="{{ $group && ! $edit ? route('orders.show', $article->bundle_id) : route($edit ? 'articles.edit' : 'articles.show', $article) }}" class="flex min-w-0 flex-1 items-center gap-3 py-2.5 ps-3 pe-2 sm:px-4">
                        <img src="{{ $article->imageUrl() }}" alt="" loading="lazy" class="h-12 w-12 shrink-0 rounded-md bg-gray-100 object-cover">
                        @php($price = $group ? $group->sum(fn ($a) => $a->effectiveSalePrice() ?? 0) : $article->effectiveSalePrice())
                        @php($shipping = $group ? $group->sum(fn ($a) => (float) $a->shipping_cost) : (float) $article->shipping_cost)
                        @php($due = $group ? $group->sum(fn ($a) => $a->amountDue()) : $article->amountDue())
                        @php($breakdown = ($group || (float) $article->sale_price > 0 ? 'VK ' : 'Preis ').($price ? \App\Models\Article::euro($price) : '–').($article->pickup ? ', Abholung' : ($shipping > 0 ? ' + '.\App\Models\Article::euro($shipping).' Versand' : ', ohne Versand')))
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium text-gray-900">
                                @if ($group)
                                    <span class="me-1 rounded bg-violet-100 px-1.5 py-0.5 text-xs font-semibold text-violet-800">Bestellung</span>{{ $group->count() }} Artikel
                                @else
                                    {{ $article->displayTitle() }}
                                @endif
                            </div>
                            <div class="truncate text-xs text-gray-500">
                                {{ $article->buyer_name ? 'an '.$article->buyer_name : $article->category->name }}
                                · {{ $group ? $group->map->displayTitle()->join(', ') : $article->brand.' · Gr. '.$article->size }}
                                @if ($mark === 'shipped' && $article->pickup)
                                    · <span class="font-medium text-gray-700">Selbstabholung</span>
                                @endif
                                @if ($mark === 'shipped' && ! $article->paid)
                                    · <span class="font-medium text-amber-700">unbezahlt</span>
                                @endif
                                @if ($label = $article->soldSinceLabel())
                                    @php($overdue = $accent === 'amber' && $article->sold_at->copy()->diffInDays(now()) >= 7)
                                    · <span @class(['font-medium text-amber-700' => $overdue])>{{ $label }}</span>
                                @endif
                            </div>
                            <div class="truncate text-xs tabular-nums text-gray-500 sm:hidden">{{ $breakdown }}</div>
                        </div>
                        <div class="shrink-0 text-right">
                            <div class="text-sm font-semibold tabular-nums text-gray-900">{{ $due > 0 ? \App\Models\Article::euro($due) : '–' }}</div>
                            <div class="hidden text-xs tabular-nums text-gray-500 sm:block">{{ $breakdown }}</div>
                            @if (! $group)
                                <x-price-difference :article="$article" />
                            @elseif ($discount = $article->bundle->discount())
                                <span @class(['whitespace-nowrap text-xs font-medium', 'text-rose-600' => $discount > 0, 'text-green-600' => $discount < 0])>{{ $discount > 0 ? '−' : '+' }}{{ \App\Models\Article::euro(abs($discount)) }}</span>
                            @endif
                        </div>
                    </a>
                    @if ($markLabel)
                        <form method="POST" action="{{ route('articles.mark', [$article, $flag]) }}" class="shrink-0 pe-3 sm:ps-2 sm:pe-4">
                            @csrf
                            @method('PATCH')
                            <button class="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white p-2 text-xs sm:px-2.5 sm:py-1.5 font-semibold text-gray-700 shadow-sm transition hover:border-green-600 hover:bg-green-50 hover:text-green-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-green-500"
                                    title="Als {{ strtolower($markLabel) }} markieren">
                                <svg class="h-4 w-4 sm:h-3.5 sm:w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                                <span class="sr-only sm:not-sr-only">{{ $markLabel }}</span>
                            </button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</section>

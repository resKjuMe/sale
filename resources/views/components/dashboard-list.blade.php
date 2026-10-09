@props(['title', 'articles', 'count' => null, 'href' => null, 'empty', 'accent' => 'gray'])

<section class="flex flex-col bg-white shadow-sm sm:rounded-lg">
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
            @foreach ($articles as $article)
                <li>
                    <a href="{{ route('articles.show', $article) }}" class="flex items-center gap-3 px-4 py-2.5 transition hover:bg-gray-50">
                        <img src="{{ $article->imageUrl() }}" alt="" loading="lazy" class="h-12 w-12 shrink-0 rounded-md bg-gray-100 object-cover">
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium text-gray-900">{{ $article->displayTitle() }}</div>
                            <div class="truncate text-xs text-gray-500">
                                {{ $article->buyer_name ? 'an '.$article->buyer_name : $article->category->name }}
                                · {{ $article->brand }} · Gr. {{ $article->size }}
                                @if ($label = $article->soldSinceLabel())
                                    @php($overdue = $accent === 'amber' && $article->sold_at->copy()->diffInDays(now()) >= 7)
                                    · <span @class(['font-medium text-amber-700' => $overdue])>{{ $label }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="shrink-0 text-right">
                            @php($price = $article->effectiveSalePrice())
                            <div class="text-sm font-semibold tabular-nums text-gray-900">{{ $article->amountDue() > 0 ? \App\Models\Article::euro($article->amountDue()) : '–' }}</div>
                            <div class="text-xs tabular-nums text-gray-500">{{ ((float) $article->sale_price > 0 ? 'VK ' : 'Preis ').($price ? \App\Models\Article::euro($price) : '–').((float) $article->shipping_cost > 0 ? ' + '.$article->formattedShippingCost().' Versand' : ', ohne Versand') }}</div>
                            <x-price-difference :article="$article" />
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</section>

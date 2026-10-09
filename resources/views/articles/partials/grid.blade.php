<div data-live-target="results">
@if ($articles->isEmpty())
    <div class="bg-white shadow-sm sm:rounded-lg p-6 text-gray-600">
        {{ $emptyText }}
    </div>
@else
    <div x-data="sellMode({
            sold: @js($articles->mapWithKeys(fn ($a) => [$a->id => $a->sold])),
            details: @js($articles->mapWithKeys(fn ($a) => [$a->id => $a->hasSaleDetails()])),
         })"
         class="pb-24">
        <div class="grid gap-4 grid-cols-2 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($articles as $article)
                <a href="{{ route('articles.show', $article) }}"
                   x-on:click="tap($event, {{ $article->id }}, @js(route('articles.sold', $article)))"
                   class="relative block select-none overflow-hidden bg-white shadow-sm transition hover:shadow-md sm:rounded-lg"
                   :class="{
                       'ring-2 ring-gray-800': sold[{{ $article->id }}],
                       'ring-2 ring-green-500 ring-offset-2': active && ! sold[{{ $article->id }}],
                       'opacity-60': busy[{{ $article->id }}],
                   }">
                    <div class="relative">
                        <img src="{{ $article->imageUrl() }}" alt="{{ $article->displayTitle() }}"
                             class="aspect-square w-full bg-gray-100 object-cover transition"
                             :class="sold[{{ $article->id }}] && 'opacity-50 grayscale'"
                             loading="lazy">
                        @if ($article->sold)
                            <div x-show="sold[{{ $article->id }}] && ! changed[{{ $article->id }}]" class="absolute left-2 top-2">
                                <x-sale-badge :article="$article" />
                            </div>
                        @endif
                        <span x-show="sold[{{ $article->id }}] && changed[{{ $article->id }}]" x-cloak
                              class="absolute left-2 top-2 rounded-full bg-gray-800 px-2 py-0.5 text-xs font-semibold text-white">Verkauft</span>
                        @if ($article->vinted_url)
                            <span class="absolute bottom-2 right-2 rounded-full bg-teal-600 px-2 py-0.5 text-xs font-semibold text-white" title="Auf Vinted eingestellt">Vinted</span>
                        @endif
                        <div x-show="active" x-cloak class="absolute inset-0 flex items-center justify-center">
                            <span class="flex h-14 w-14 items-center justify-center rounded-full text-3xl font-bold shadow-lg"
                                  :class="sold[{{ $article->id }}] ? 'bg-gray-800 text-white' : 'bg-white/90 text-green-600'"
                                  x-text="sold[{{ $article->id }}] ? '✓' : '+'"></span>
                        </div>
                    </div>
                    <div class="p-3">
                        @if ($showCategory ?? false)
                            <div class="truncate text-xs font-medium uppercase tracking-wide text-gray-400">{{ $article->category->name }}</div>
                        @endif
                        <div class="font-medium text-gray-900 truncate">{{ $article->displayTitle() }}</div>
                        <div class="text-sm text-gray-600 truncate">{{ $article->brand }} · Gr. {{ $article->size }}</div>
                        <div class="mt-1 flex items-center justify-between gap-2 text-sm">
                            @if ($article->sold)
                                <span x-show="! changed[{{ $article->id }}]" class="truncate text-gray-500">{{ $article->buyer_name ? 'an '.$article->buyer_name : 'verkauft' }}@if ($article->sold_at) · {{ $article->statusDate('sold', 'd.m.') }}@endif</span>
                                <span x-show="! changed[{{ $article->id }}]" class="shrink-0 font-semibold text-gray-900">{{ $article->formattedSalePrice() }}</span>
                            @endif
                            <span x-show="{{ $article->sold ? 'changed['.$article->id.'] && ' : '' }}! sold[{{ $article->id }}]" @if ($article->sold) x-cloak @endif
                                  class="truncate text-gray-500">{{ $article->condition?->label() }}</span>
                            <span x-show="{{ $article->sold ? 'changed['.$article->id.'] && ' : '' }}! sold[{{ $article->id }}]" @if ($article->sold) x-cloak @endif
                                  class="shrink-0 font-semibold text-gray-900">{{ $article->formattedPrice() }}</span>
                            <span x-show="changed[{{ $article->id }}] && sold[{{ $article->id }}]" x-cloak class="truncate text-gray-500">verkauft</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-6">{{ $articles->links() }}</div>

        <div class="fixed inset-x-0 bottom-0 z-20 border-t border-gray-200 bg-white/95 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center gap-3">
                <template x-if="! active">
                    <button type="button" x-on:click="setActive(true)"
                            class="w-full rounded-md bg-gray-800 py-3 text-base font-semibold text-white active:bg-gray-700 sm:ms-auto sm:w-auto sm:px-6">
                        Verkauft markieren
                    </button>
                </template>
                <template x-if="active">
                    <div class="flex w-full items-center gap-3">
                        <span class="flex-1 text-sm text-gray-700" x-text="message || 'Tippe auf die verkauften Artikel.'"></span>
                        <button type="button" x-on:click="setActive(false)"
                                class="shrink-0 rounded-md bg-green-600 px-6 py-3 text-base font-semibold text-white active:bg-green-700">
                            Fertig
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>
@endif
</div>

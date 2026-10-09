<div data-live-target="results">
@if ($articles->isEmpty())
    <div class="bg-white shadow-sm sm:rounded-lg p-6 text-gray-600">
        {{ $emptyText }}
    </div>
@else
    <div x-data="sellMode({
            sold: @js($articles->mapWithKeys(fn ($a) => [$a->id => $a->sold])),
            details: @js($articles->mapWithKeys(fn ($a) => [$a->id => $a->hasSaleDetails()])),
            ids: @js($articles->pluck('id')),
            batchUrl: @js(route('articles.batch.edit')),
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
                       'ring-4 ring-violet-500': selecting && selected[{{ $article->id }}],
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
                        @if (($days = $article->availableDays()) >= \App\Models\Article::STALE_DAYS)
                            <span class="absolute right-2 top-2 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800" title="Seit {{ $days }} Tagen verfügbar">{{ $days }} Tage</span>
                        @endif
                        @if ($article->bundle_id)
                            <span class="absolute right-2 top-2 rounded-full bg-violet-600 px-2 py-0.5 text-xs font-semibold text-white" title="Teil einer Bestellung">Bestellung</span>
                        @endif
                        <div x-show="selecting" x-cloak class="absolute inset-0 flex items-start justify-end p-2" :class="selected[{{ $article->id }}] ? 'bg-violet-500/15' : ''">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full border-2 text-sm font-bold shadow"
                                  :class="selected[{{ $article->id }}] ? 'border-violet-600 bg-violet-600 text-white' : 'border-white bg-white/80 text-transparent'">✓</span>
                        </div>
                        @if ($article->images_count)
                            <span class="absolute bottom-2 left-2 rounded-full bg-black/60 px-2 py-0.5 text-xs font-semibold text-white" title="{{ $article->images_count + 1 }} Fotos">+{{ $article->images_count }}</span>
                        @endif
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
                                <span x-show="! changed[{{ $article->id }}]" class="shrink-0 text-right font-semibold text-gray-900">{{ $article->formattedSalePrice() }}<x-price-difference :article="$article" class="ml-1" /></span>
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
                <template x-if="! active && ! selecting">
                    <div class="flex w-full gap-3 sm:justify-end">
                        <button type="button" x-on:click="setSelecting(true)"
                                class="flex-1 rounded-md border border-gray-300 bg-white py-3 text-base font-semibold text-gray-700 active:bg-gray-50 sm:flex-none sm:px-6">
                            Auswählen
                        </button>
                        <button type="button" x-on:click="setActive(true)"
                                class="flex-1 rounded-md bg-gray-800 py-3 text-base font-semibold text-white active:bg-gray-700 sm:flex-none sm:px-6">
                            Verkauft markieren
                        </button>
                    </div>
                </template>
                <template x-if="selecting">
                    <div class="flex w-full flex-wrap items-center gap-2 sm:gap-3">
                        <span class="text-sm font-medium text-gray-700" x-text="selectedIds.length === 1 ? '1 ausgewählt' : `${selectedIds.length} ausgewählt`"></span>
                        <button type="button" x-on:click="toggleAll()" class="text-sm text-gray-500 underline hover:text-gray-800"
                                x-text="allSelected ? 'Keine auf dieser Seite' : 'Alle auf dieser Seite'"></button>
                        <div class="ms-auto flex gap-2">
                            <button type="button" x-on:click="setSelecting(false)"
                                    class="rounded-md border border-gray-300 bg-white px-4 py-3 text-base font-semibold text-gray-700 active:bg-gray-50">
                                Abbrechen
                            </button>
                            <button type="button" x-on:click="openBatch()" :disabled="selectedIds.length === 0"
                                    class="rounded-md bg-violet-600 px-5 py-3 text-base font-semibold text-white active:bg-violet-700 disabled:opacity-40">
                                Bearbeiten
                            </button>
                        </div>
                    </div>
                </template>
                <template x-if="active">
                    <div class="flex w-full flex-wrap items-center justify-end gap-2 sm:gap-3">
                        <span class="min-w-[10rem] flex-1 text-sm text-gray-700" x-text="message || 'Tippe auf die verkauften Artikel.'"></span>
                        <button type="button" x-show="soldNow.length >= 2" x-on:click="bundleSoldNow()"
                                class="shrink-0 rounded-md border border-violet-300 bg-violet-50 px-4 py-3 text-base font-semibold text-violet-800 active:bg-violet-100"
                                x-text="`Als Bestellung (${soldNow.length})`"></button>
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

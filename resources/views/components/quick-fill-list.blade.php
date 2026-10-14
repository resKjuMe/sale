@props(['articles', 'type', 'fields', 'pickup' => false, 'empty'])

@php($labels = ['price' => 'Angebot', 'sale_price' => 'Gezahlt', 'shipping_cost' => 'Versand'])

<section class="rounded-lg bg-white shadow-sm">
    <header class="border-b border-gray-100 px-4 py-3">
        <h3 class="font-semibold text-gray-900">Beträge direkt eintragen</h3>
        <p class="text-xs text-gray-500">Leere Felder bleiben unverändert. Nach dem Speichern geht es mit dem nächsten Artikel weiter.</p>
    </header>

    @if ($articles->isEmpty())
        <p class="px-4 py-6 text-sm text-gray-500">{{ $empty }}</p>
    @else
        <ul class="divide-y divide-gray-100">
            @foreach ($articles as $article)
                <li x-data="quickFill" data-quick-fill class="px-3 py-3 sm:px-4" :class="done && 'bg-green-50'">
                    <a href="{{ route('articles.edit', $article) }}" class="flex min-w-0 items-center gap-3">
                        <img src="{{ $article->imageUrl() }}" alt="" loading="lazy" class="h-12 w-12 shrink-0 rounded-md bg-gray-100 object-cover">
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium text-gray-900">
                                @if ($article->bundle_id)
                                    <span class="me-1 rounded bg-violet-100 px-1.5 py-0.5 text-xs font-semibold text-violet-800">Bestellung</span>
                                @endif
                                {{ $article->displayTitle() }}
                            </div>
                            <div class="truncate text-xs text-gray-500">
                                {{ $article->buyer_name ? 'an '.$article->buyer_name : $article->category->name }}
                                · {{ $article->brand }} · Gr. {{ $article->size }}
                                @if ($soldOn = $article->statusDate('sold', 'd.m.Y'))
                                    · verkauft {{ $soldOn }}
                                @endif
                            </div>
                        </div>
                    </a>

                    <p x-show="done" x-cloak class="mt-2 flex items-center gap-1.5 text-sm font-medium text-green-700">
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                        <span x-text="summary"></span>
                    </p>

                    <form x-show="! done" method="POST" action="{{ route('pending.fill', [$type, $article]) }}" @submit.prevent="submit" class="mt-2">
                        @csrf
                        @method('PATCH')
                        <div class="flex items-end gap-2">
                            @foreach ($fields as $field)
                                <label class="min-w-0 flex-1">
                                    <span class="mb-0.5 block text-xs font-medium text-gray-600">{{ $labels[$field] }}</span>
                                    <span class="relative block">
                                        <input type="text" name="{{ $field }}" inputmode="decimal" autocomplete="off" placeholder="0,00"
                                               value="{{ (float) $article->{$field} > 0 ? number_format((float) $article->{$field}, 2, ',', '') : '' }}"
                                               enterkeyhint="{{ $loop->last ? 'done' : 'next' }}"
                                               @if (! $loop->last) @keydown.enter.prevent="focusNext($event.target)" @endif
                                               :readonly="busy"
                                               class="block h-11 w-full rounded-md border-gray-300 pe-7 text-base tabular-nums shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <span class="pointer-events-none absolute inset-y-0 end-0 flex items-center pe-2.5 text-sm text-gray-400">€</span>
                                    </span>
                                </label>
                            @endforeach
                            <button type="submit" :disabled="busy" title="Speichern"
                                    class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-md bg-gray-800 text-white shadow-sm transition hover:bg-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-50">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                                <span class="sr-only">Speichern</span>
                            </button>
                            @if ($pickup)
                                <button type="submit" name="pickup" value="1" :disabled="busy"
                                        class="h-11 shrink-0 rounded-md border border-gray-300 bg-white px-3 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-50">
                                    Abholung
                                </button>
                            @endif
                        </div>
                        @if (count($fields) > 1)
                            <button type="button" @click="copyFirst()" class="mt-1.5 text-xs font-medium text-indigo-600 hover:text-indigo-800">
                                Beide gleich
                            </button>
                        @endif
                        <p x-show="error" x-cloak x-text="error" class="mt-1.5 text-sm text-rose-600"></p>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif
</section>

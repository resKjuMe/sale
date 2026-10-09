<x-app-layout>
    <x-slot name="header">
        <a href="{{ $back }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Zurück</a>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $articles->count() === 1 ? '1 Artikel' : $articles->count().' Artikel' }} bearbeiten</h2>
    </x-slot>

    @php
        $card = 'rounded-lg bg-white p-4 shadow-sm sm:p-6';
    @endphp

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="rounded-md bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            <div class="flex gap-2 overflow-x-auto pb-1">
                @foreach ($articles as $article)
                    <img src="{{ $article->imageUrl() }}" alt="{{ $article->displayTitle() }}" title="{{ $article->displayTitle() }}"
                         @class(['h-16 w-16 shrink-0 rounded-md bg-gray-100 object-cover', 'opacity-40 grayscale' => $article->sold])>
                @endforeach
            </div>

            <form method="POST" action="{{ route('articles.batch.update') }}" class="{{ $card }}">
                @include('articles.partials.batch-hidden')
                <input type="hidden" name="action" value="category">
                <h3 class="font-semibold text-gray-900">In andere Kategorie verschieben</h3>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <select name="category_id" required class="min-w-0 flex-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        <option value="">Kategorie wählen …</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <x-primary-button>Verschieben</x-primary-button>
                </div>
            </form>

            <form method="POST" action="{{ route('articles.batch.update') }}" class="{{ $card }}" x-data="{ mode: 'percent' }">
                @include('articles.partials.batch-hidden')
                <input type="hidden" name="action" value="price">
                <h3 class="font-semibold text-gray-900">Preis ändern</h3>
                <p class="mt-1 text-sm text-gray-500">Gilt nur für verfügbare Artikel; Senkungen nur, wo schon ein Preis eingetragen ist.</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach (['percent' => 'um % senken', 'amount' => 'um € senken', 'set' => 'auf Betrag setzen'] as $value => $label)
                        <label class="cursor-pointer">
                            <input type="radio" name="mode" value="{{ $value }}" x-model="mode" class="peer sr-only">
                            <span class="inline-block rounded-full border border-gray-300 px-3 py-1 text-sm text-gray-700 peer-checked:border-gray-800 peer-checked:bg-gray-800 peer-checked:text-white">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <div class="relative w-32">
                        <x-text-input name="value" type="text" inputmode="decimal" required class="block w-full pe-8" x-bind:placeholder="mode === 'percent' ? '20' : '5,00'" />
                        <span class="pointer-events-none absolute inset-y-0 end-3 flex items-center text-sm text-gray-500" x-text="mode === 'percent' ? '%' : '€'"></span>
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="round" value="1" checked class="rounded border-gray-300 text-gray-800 shadow-sm focus:ring-gray-500">
                        auf 50 Cent runden
                    </label>
                    <x-primary-button class="ms-auto">Preise ändern</x-primary-button>
                </div>
            </form>

            <section class="{{ $card }}">
                <h3 class="font-semibold text-gray-900">Als Sammelverkauf verkaufen</h3>
                @if (! $allAvailable)
                    <p class="mt-1 text-sm text-gray-500">Geht nur, wenn alle ausgewählten Artikel noch verfügbar sind.</p>
                @else
                    <p class="mt-1 text-sm text-gray-500">Ein Käufer, Versand einmal; „Bezahlt" und „Versendet" gelten danach für alle Artikel gemeinsam.</p>
                    <form method="POST" action="{{ route('articles.batch.update') }}" class="mt-4 space-y-4" x-data="{ pickup: false }">
                        @include('articles.partials.batch-hidden')
                        <input type="hidden" name="action" value="bundle">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="buyer_name" value="An wen" />
                                <x-text-input id="buyer_name" name="buyer_name" type="text" required class="mt-1 block w-full" :value="old('buyer_name')" />
                            </div>
                            <div x-show="! pickup">
                                <x-input-label for="shipping_cost" value="Versandkosten gesamt in €" />
                                <x-text-input id="shipping_cost" name="shipping_cost" type="text" inputmode="decimal" placeholder="0,00" class="mt-1 block w-full" :value="old('shipping_cost')" />
                            </div>
                            <div class="sm:col-span-2" x-show="! pickup">
                                <x-input-label for="buyer_address" value="Adresse" />
                                <textarea id="buyer_address" name="buyer_address" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('buyer_address') }}</textarea>
                            </div>
                        </div>
                        <div>
                            <x-input-label value="Verkaufspreis je Artikel in €" />
                            <ul class="mt-1 divide-y divide-gray-100 rounded-md border border-gray-200">
                                @foreach ($articles as $article)
                                    <li class="flex items-center gap-3 px-3 py-2">
                                        <img src="{{ $article->imageUrl() }}" alt="" class="h-10 w-10 shrink-0 rounded bg-gray-100 object-cover">
                                        <span class="min-w-0 flex-1 truncate text-sm text-gray-700">{{ $article->displayTitle() }}</span>
                                        <input type="text" name="sale_price[{{ $article->id }}]" inputmode="decimal" placeholder="0,00"
                                               value="{{ old('sale_price.'.$article->id, $article->price !== null ? str_replace('.', ',', $article->price) : '') }}"
                                               class="w-24 rounded-md border-gray-300 text-right text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="flex flex-wrap items-center gap-x-8 gap-y-3">
                            <x-toggle name="pickup" label="Selbstabholung" model="pickup" />
                            <div x-data="{ paid: false }"><x-toggle name="paid" label="Bezahlt" model="paid" /></div>
                            <x-primary-button class="ms-auto">Sammelverkauf anlegen</x-primary-button>
                        </div>
                    </form>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <a href="{{ route('categories.show', $category) }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ $category->name }}</a>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Mehrere Bilder hochladen</h2>
    </x-slot>

    <div class="py-6 sm:py-12 pb-32"
         x-data="bulkUpload({ storeUrl: @js(route('categories.articles.store', $category)), doneUrl: @js(route('categories.show', $category)) })">
        <div class="mx-auto max-w-4xl space-y-4 px-4 sm:px-6 lg:px-8">

            <label for="images"
                   class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-gray-300 bg-white px-4 py-8 text-gray-600 hover:bg-gray-50"
                   :class="items.length ? 'py-4' : 'py-12'">
                <svg class="h-10 w-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                </svg>
                <span class="font-medium" x-text="items.length ? 'Weitere Bilder hinzufügen' : 'Bilder auswählen'"></span>
                <span class="text-xs text-gray-500" x-show="!items.length">Es wird erst gespeichert, wenn du auf „Alle speichern" tippst.</span>
            </label>
            <input id="images" type="file" accept="image/*" multiple class="sr-only" x-on:change="addFiles($event)" :disabled="saving">

            <div x-show="items.length > 1" x-cloak class="rounded-lg bg-white p-4 shadow-sm">
                <div class="mb-2 text-sm font-medium text-gray-700">Für alle leeren Felder übernehmen</div>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <x-text-input type="text" list="brands" placeholder="Marke" x-model="defaults.brand" autocapitalize="words" class="block w-full" />
                    <x-text-input type="text" list="sizes" placeholder="Größe" x-model="defaults.size" class="block w-full" />
                    <select x-model="defaults.condition" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Zustand –</option>
                        @foreach (\App\Enums\ArticleCondition::cases() as $condition)
                            <option value="{{ $condition->value }}">{{ $condition->label() }}</option>
                        @endforeach
                    </select>
                    <x-secondary-button type="button" class="justify-center" x-on:click="applyDefaults()">Übernehmen</x-secondary-button>
                </div>
            </div>

            <template x-for="(item, index) in items" :key="item.id">
                <div class="flex gap-3 rounded-lg bg-white p-3 shadow-sm sm:gap-4 sm:p-4"
                     :class="{ 'has-error ring-2 ring-red-400': Object.keys(item.errors).length, 'opacity-60': item.saving }">
                    <div class="relative w-24 shrink-0 sm:w-32">
                        <img :src="item.preview" alt="" class="aspect-[3/4] w-full rounded-md bg-gray-100 object-cover">
                        <span x-show="item.preparing || item.saving"
                              class="absolute inset-0 flex items-center justify-center rounded-md bg-white/70 text-xs text-gray-700"
                              x-text="item.saving ? 'Speichert …' : 'Bereite vor …'"></span>
                        <span class="absolute left-1 top-1 rounded bg-black/60 px-1.5 text-xs text-white" x-text="index + 1"></span>
                    </div>

                    <div class="grid min-w-0 flex-1 grid-cols-2 content-start gap-2 sm:grid-cols-4 sm:gap-3">
                        <div>
                            <x-text-input type="text" list="brands" placeholder="Marke *" x-model="item.brand" autocapitalize="words" autocomplete="off" class="block w-full" ::class="item.errors.brand && 'border-red-500'" />
                            <p class="mt-1 text-xs text-red-600" x-show="item.errors.brand" x-text="item.errors.brand"></p>
                        </div>
                        <div>
                            <x-text-input type="text" list="sizes" placeholder="Größe *" x-model="item.size" autocomplete="off" class="block w-full" ::class="item.errors.size && 'border-red-500'" />
                            <p class="mt-1 text-xs text-red-600" x-show="item.errors.size" x-text="item.errors.size"></p>
                        </div>
                        <div>
                            <select x-model="item.condition" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Zustand –</option>
                                @foreach (\App\Enums\ArticleCondition::cases() as $condition)
                                    <option value="{{ $condition->value }}">{{ $condition->label() }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-red-600" x-show="item.errors.condition" x-text="item.errors.condition"></p>
                        </div>
                        <div>
                            <x-text-input type="text" inputmode="decimal" placeholder="Preis €" x-model="item.price" autocomplete="off" class="block w-full" />
                            <p class="mt-1 text-xs text-red-600" x-show="item.errors.price" x-text="item.errors.price"></p>
                        </div>
                        <div class="col-span-2 sm:col-span-3">
                            <x-text-input type="text" placeholder="Titel (optional)" x-model="item.title" autocomplete="off" class="block w-full" />
                            <p class="mt-1 text-xs text-red-600" x-show="item.errors.title" x-text="item.errors.title"></p>
                        </div>
                        <div class="col-span-2 flex items-center justify-end sm:col-span-1">
                            <button type="button" x-on:click="remove(item)" :disabled="saving"
                                    class="text-sm text-red-600 hover:text-red-800 disabled:opacity-40">Entfernen</button>
                        </div>
                        <p class="col-span-2 text-xs text-red-600 sm:col-span-4" x-show="item.errors.image" x-text="item.errors.image"></p>
                    </div>
                </div>
            </template>
        </div>

        <datalist id="brands">
            @foreach ($brands as $brand)
                <option value="{{ $brand }}">
            @endforeach
        </datalist>
        <datalist id="sizes">
            @foreach ($sizes as $size)
                <option value="{{ $size }}">
            @endforeach
        </datalist>

        <div x-show="items.length" x-cloak
             class="fixed inset-x-0 bottom-0 border-t border-gray-200 bg-white/95 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur">
            <div class="mx-auto flex max-w-4xl items-center gap-3">
                <span class="text-sm text-gray-600" x-text="saving ? `${savedCount} gespeichert, ${pendingCount} offen` : `${pendingCount} Artikel`"></span>
                <button type="button" x-on:click="saveAll()" :disabled="saving || preparing"
                        class="ms-auto rounded-md bg-gray-800 px-6 py-3 text-base font-semibold text-white hover:bg-gray-700 disabled:opacity-40">
                    <span x-text="saving ? 'Speichert …' : (preparing ? 'Bilder werden vorbereitet …' : 'Alle speichern')"></span>
                </button>
            </div>
        </div>
    </div>
</x-app-layout>

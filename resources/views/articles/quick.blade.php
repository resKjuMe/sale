<x-app-layout>
    <div class="mx-auto max-w-lg px-4 pt-4 pb-32">
        <div class="mb-4 flex items-center justify-between gap-4">
            <div class="min-w-0">
                <div class="text-xs uppercase tracking-wide text-gray-500">Schnellerfassung</div>
                <h1 class="truncate text-lg font-semibold text-gray-900">{{ $category->name }}</h1>
            </div>
            <span class="shrink-0 rounded-full bg-gray-200 px-3 py-1 text-sm text-gray-700">{{ $articleCount }} Artikel</span>
        </div>

        <x-flash />

        @if ($errors->any())
            <div class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-800">
                Nicht gespeichert – bitte Foto erneut aufnehmen und Angaben prüfen.
            </div>
        @endif

        <form method="POST" enctype="multipart/form-data"
              action="{{ route('categories.articles.store', $category) }}"
              x-data="articlePhoto()"
              x-on:submit="$refs.save.disabled = true">
            @csrf
            <input type="hidden" name="quick" value="1">

            <input id="image" name="image" type="file" accept="image/*" capture="environment" required
                   class="sr-only" x-ref="camera"
                   x-on:change="pick($event).then(() => preview && $nextTick(() => $refs.brand.focus()))">

            <label for="image" x-show="!preview"
                   class="flex h-72 w-full cursor-pointer flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-gray-300 bg-white text-gray-600 active:bg-gray-50">
                <svg class="h-16 w-16" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z" />
                </svg>
                <span class="text-lg font-medium">Foto aufnehmen</span>
            </label>
            <x-input-error :messages="$errors->get('image')" class="mt-2" />
            <x-ai-suggest-buttons class="mt-3" />

            <div x-show="preview" x-cloak class="space-y-4">
                <div class="relative">
                    <img :src="preview" alt="Vorschau" class="max-h-[45vh] w-full rounded-xl bg-gray-200 object-contain">
                    <label for="image"
                           class="absolute bottom-2 right-2 cursor-pointer rounded-full bg-black/60 px-3 py-1.5 text-sm font-medium text-white">
                        Neu aufnehmen
                    </label>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <x-input-label for="brand" value="Marke" />
                        <x-text-input id="brand" name="brand" type="text" list="brands" x-ref="brand"
                                      autocapitalize="words" autocomplete="off" enterkeyhint="next"
                                      class="mt-1 block w-full" :value="old('brand')" required />
                        <x-input-error :messages="$errors->get('brand')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="size" value="Größe" />
                        <x-text-input id="size" name="size" type="text" list="sizes"
                                      autocapitalize="characters" autocomplete="off" enterkeyhint="next"
                                      class="mt-1 block w-full" :value="old('size')" required />
                        <x-input-error :messages="$errors->get('size')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="condition" value="Zustand" />
                        <select id="condition" name="condition"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">–</option>
                            @foreach (\App\Enums\ArticleCondition::cases() as $condition)
                                <option value="{{ $condition->value }}" @selected(old('condition') === $condition->value)>{{ $condition->label() }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('condition')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="price" value="Preis €" />
                        <x-text-input id="price" name="price" type="text" inputmode="decimal" placeholder="0,00"
                                      autocomplete="off" enterkeyhint="next"
                                      class="mt-1 block w-full" :value="old('price')" />
                        <x-input-error :messages="$errors->get('price')" class="mt-1" />
                    </div>
                    <div class="col-span-2">
                        <x-input-label for="title" value="Titel (optional)" />
                        <x-text-input id="title" name="title" type="text" autocomplete="off" enterkeyhint="done"
                                      class="mt-1 block w-full" :value="old('title')" />
                        <x-input-error :messages="$errors->get('title')" class="mt-1" />
                    </div>
                </div>
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

            <div class="fixed inset-x-0 bottom-0 border-t border-gray-200 bg-white/95 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur">
                <div class="mx-auto flex max-w-lg items-center gap-3">
                    <a href="{{ route('categories.show', $category) }}"
                       class="rounded-md px-4 py-3 text-sm font-medium text-gray-700 active:bg-gray-100">Fertig</a>
                    <button type="submit" x-ref="save" x-bind:disabled="!preview || processing"
                            class="flex-1 rounded-md bg-gray-800 py-3 text-base font-semibold text-white active:bg-gray-700 disabled:opacity-40">
                        <span x-show="!processing">Speichern &amp; Weiter</span>
                        <span x-show="processing" x-cloak>Bild wird vorbereitet …</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>

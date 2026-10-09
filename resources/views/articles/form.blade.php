<x-app-layout>
    <x-slot name="header">
        <a href="{{ route('categories.show', $category) }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ $category->name }}</a>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $article->exists ? 'Artikel bearbeiten' : 'Neuer Artikel' }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="POST" enctype="multipart/form-data"
                      action="{{ $article->exists ? route('articles.update', $article) : route('categories.articles.store', $category) }}"
                      class="space-y-6"
                      x-data="articlePhoto(@js($article->exists ? $article->imageUrl() : null))">
                    @csrf
                    @if ($article->exists)
                        @method('PUT')
                    @endif

                    <div>
                        <x-input-label for="image" :value="$article->exists ? 'Bild (leer lassen, um es zu behalten)' : 'Bild'" />
                        <template x-if="preview">
                            <img :src="preview" alt="" class="mt-2 h-48 w-48 rounded-md object-cover bg-gray-100">
                        </template>
                        <input id="image" name="image" type="file" accept="image/*" @required(! $article->exists)
                               x-on:change="pick($event)"
                               class="mt-2 block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-gray-800 file:px-4 file:py-2 file:text-xs file:font-semibold file:uppercase file:tracking-widest file:text-white hover:file:bg-gray-700">
                        <x-input-error :messages="$errors->get('image')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="title" value="Titel (optional)" />
                        <x-text-input id="title" name="title" type="text" class="mt-1 block w-full"
                                      :value="old('title', $article->title)" />
                        <x-input-error :messages="$errors->get('title')" class="mt-2" />
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <x-input-label for="brand" value="Marke" />
                            <x-text-input id="brand" name="brand" type="text" class="mt-1 block w-full"
                                          :value="old('brand', $article->brand)" required />
                            <x-input-error :messages="$errors->get('brand')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="size" value="Größe" />
                            <x-text-input id="size" name="size" type="text" class="mt-1 block w-full"
                                          :value="old('size', $article->size)" required />
                            <x-input-error :messages="$errors->get('size')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="condition" value="Zustand (optional)" />
                            <select id="condition" name="condition"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="">–</option>
                                @foreach (\App\Enums\ArticleCondition::cases() as $condition)
                                    <option value="{{ $condition->value }}" @selected(old('condition', $article->condition?->value) === $condition->value)>
                                        {{ $condition->label() }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('condition')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="price" value="Preis in € (optional)" />
                            <x-text-input id="price" name="price" type="text" inputmode="decimal" class="mt-1 block w-full"
                                          :value="old('price', $article->price !== null ? str_replace('.', ',', $article->price) : null)"
                                          placeholder="0,00" />
                            <x-input-error :messages="$errors->get('price')" class="mt-2" />
                        </div>
                    </div>

                    @if ($article->exists)
                        <div class="space-y-4 border-t border-gray-200 pt-6"
                             x-data="{ sold: @js((bool) old('sold', $article->sold)), paid: @js((bool) old('paid', $article->paid)) }">
                            <x-toggle name="sold" label="Verkauft" model="sold" />

                            <div x-show="sold" x-cloak class="grid gap-4 rounded-md bg-gray-50 p-4 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <x-input-label for="buyer_name" value="An wen" />
                                    <x-text-input id="buyer_name" name="buyer_name" type="text" class="mt-1 block w-full"
                                                  :value="old('buyer_name', $article->buyer_name)" />
                                    <x-input-error :messages="$errors->get('buyer_name')" class="mt-2" />
                                </div>
                                <div class="sm:col-span-2">
                                    <x-input-label for="buyer_address" value="Adresse" />
                                    <textarea id="buyer_address" name="buyer_address" rows="3"
                                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('buyer_address', $article->buyer_address) }}</textarea>
                                    <x-input-error :messages="$errors->get('buyer_address')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="sale_price" value="Verkaufspreis in €" />
                                    <x-text-input id="sale_price" name="sale_price" type="text" inputmode="decimal" class="mt-1 block w-full"
                                                  :value="old('sale_price', $article->sale_price !== null ? str_replace('.', ',', $article->sale_price) : null)"
                                                  placeholder="0,00" />
                                    <x-input-error :messages="$errors->get('sale_price')" class="mt-2" />
                                </div>
                                <div class="flex items-end pb-2">
                                    <x-toggle name="paid" label="Bezahlt" model="paid" />
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="flex items-center gap-4">
                        <x-primary-button x-bind:disabled="processing">Speichern</x-primary-button>
                        <a href="{{ $article->exists ? route('articles.show', $article) : route('categories.show', $category) }}"
                           class="text-sm text-gray-600 hover:text-gray-900">Abbrechen</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

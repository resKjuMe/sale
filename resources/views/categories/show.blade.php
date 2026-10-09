<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <a href="{{ route('categories.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Kategorien</a>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $category->name }}</h2>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('categories.articles.quick', $category) }}">
                    <x-primary-button type="button">Schnellerfassung</x-primary-button>
                </a>
                <a href="{{ route('categories.articles.bulk', $category) }}">
                    <x-secondary-button type="button">Mehrere Bilder</x-secondary-button>
                </a>
                <a href="{{ route('categories.articles.create', $category) }}">
                    <x-secondary-button type="button">Neuer Artikel</x-secondary-button>
                </a>
                <a href="{{ route('categories.print', $category) }}" target="_blank">
                    <x-secondary-button type="button">Druckansicht</x-secondary-button>
                </a>
                <a href="{{ route('categories.edit', $category) }}">
                    <x-secondary-button type="button">Bearbeiten</x-secondary-button>
                </a>
                <form method="POST" action="{{ route('categories.destroy', $category) }}"
                      onsubmit="return confirm('Kategorie inklusive aller {{ $articles->total() }} Artikel löschen?')">
                    @csrf
                    @method('DELETE')
                    <x-danger-button>Löschen</x-danger-button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-flash />
            @if (request()->integer('saved') > 0)
                <div class="mb-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ request()->integer('saved') }} Artikel gespeichert.
                </div>
            @endif

            @if ($category->description)
                <p class="mb-6 text-gray-600">{{ $category->description }}</p>
            @endif

            <div class="mb-6 flex flex-wrap items-center gap-2 bg-white p-4 shadow-sm sm:rounded-lg"
                 x-data="{ copied: false, copy() { navigator.clipboard.writeText(this.$refs.url.value).then(() => { this.copied = true; setTimeout(() => this.copied = false, 2000) }) } }">
                <span class="text-sm font-medium text-gray-700">Öffentlicher Link:</span>
                <input x-ref="url" type="text" readonly value="{{ $category->publicUrl() }}" x-on:focus="$el.select()"
                       class="min-w-0 flex-1 rounded-md border-gray-300 bg-gray-50 text-sm text-gray-700 shadow-sm">
                <x-secondary-button type="button" x-on:click="copy()">
                    <span x-text="copied ? 'Kopiert ✓' : 'Kopieren'">Kopieren</span>
                </x-secondary-button>
                <a href="{{ $category->publicUrl() }}" target="_blank">
                    <x-secondary-button type="button">Öffnen</x-secondary-button>
                </a>
                <form method="POST" action="{{ route('categories.public-link', $category) }}"
                      onsubmit="return confirm('Neuen Link erzeugen? Der bisherige Link funktioniert danach nicht mehr.')">
                    @csrf
                    <button class="px-2 text-sm text-gray-500 underline hover:text-gray-700">Neu erzeugen</button>
                </form>
            </div>

            @if ($articles->isEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-6 text-gray-600">
                    In dieser Kategorie gibt es noch keine Artikel.
                </div>
            @else
                <div class="grid gap-4 grid-cols-2 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($articles as $article)
                        <a href="{{ route('articles.show', $article) }}"
                           @class([
                               'block bg-white shadow-sm sm:rounded-lg overflow-hidden hover:shadow-md transition',
                               'ring-2 ring-gray-800' => $article->sold,
                           ])>
                            <div class="relative">
                                <img src="{{ $article->imageUrl() }}" alt="{{ $article->displayTitle() }}"
                                     @class(['aspect-square w-full object-cover bg-gray-100', 'opacity-50 grayscale' => $article->sold])
                                     loading="lazy">
                                @if ($article->sold)
                                    <x-sale-badge :article="$article" class="absolute left-2 top-2" />
                                @endif
                                @if ($article->vinted_url)
                                    <span class="absolute bottom-2 right-2 rounded-full bg-teal-600 px-2 py-0.5 text-xs font-semibold text-white" title="Auf Vinted eingestellt">Vinted</span>
                                @endif
                            </div>
                            <div class="p-3">
                                <div class="font-medium text-gray-900 truncate">{{ $article->displayTitle() }}</div>
                                <div class="text-sm text-gray-600 truncate">{{ $article->brand }} · Gr. {{ $article->size }}</div>
                                <div class="mt-1 flex items-center justify-between gap-2 text-sm">
                                    @if ($article->sold)
                                        <span class="truncate text-gray-500">{{ $article->buyer_name ? 'an '.$article->buyer_name : 'verkauft' }}</span>
                                        <span class="shrink-0 font-semibold text-gray-900">{{ $article->formattedSalePrice() }}</span>
                                    @else
                                        <span class="truncate text-gray-500">{{ $article->condition?->label() }}</span>
                                        <span class="shrink-0 font-semibold text-gray-900">{{ $article->formattedPrice() }}</span>
                                    @endif
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="mt-6">{{ $articles->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>

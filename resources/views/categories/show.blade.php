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

            @if ($articles->isEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-6 text-gray-600">
                    In dieser Kategorie gibt es noch keine Artikel.
                </div>
            @else
                <div class="grid gap-4 grid-cols-2 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($articles as $article)
                        <a href="{{ route('articles.show', $article) }}"
                           class="block bg-white shadow-sm sm:rounded-lg overflow-hidden hover:shadow-md transition">
                            <img src="{{ $article->imageUrl() }}" alt="{{ $article->displayTitle() }}"
                                 class="aspect-square w-full object-cover bg-gray-100" loading="lazy">
                            <div class="p-3">
                                <div class="font-medium text-gray-900 truncate">{{ $article->displayTitle() }}</div>
                                <div class="text-sm text-gray-600 truncate">{{ $article->brand }} · Gr. {{ $article->size }}</div>
                                <div class="mt-1 flex items-center justify-between text-sm">
                                    <span class="text-gray-500">{{ $article->condition?->label() }}</span>
                                    <span class="font-semibold text-gray-900">{{ $article->formattedPrice() }}</span>
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

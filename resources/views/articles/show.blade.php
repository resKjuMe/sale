<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <a href="{{ route('categories.show', $article->category) }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ $article->category->name }}</a>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $article->displayTitle() }}</h2>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('articles.edit', $article) }}">
                    <x-secondary-button type="button">Bearbeiten</x-secondary-button>
                </a>
                <form method="POST" action="{{ route('articles.destroy', $article) }}"
                      onsubmit="return confirm('Artikel löschen?')">
                    @csrf
                    @method('DELETE')
                    <x-danger-button>Löschen</x-danger-button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <x-flash />

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden md:flex">
                <a href="{{ $article->imageUrl() }}" target="_blank" class="md:w-1/2 shrink-0">
                    <img src="{{ $article->imageUrl() }}" alt="{{ $article->displayTitle() }}" class="w-full object-contain bg-gray-100 max-h-[70vh]">
                </a>
                <dl class="p-6 grid grid-cols-[auto,1fr] gap-x-6 gap-y-3 content-start text-sm">
                    <dt class="text-gray-500">Titel</dt>
                    <dd class="text-gray-900">{{ $article->title ?? '–' }}</dd>
                    <dt class="text-gray-500">Marke</dt>
                    <dd class="text-gray-900">{{ $article->brand }}</dd>
                    <dt class="text-gray-500">Größe</dt>
                    <dd class="text-gray-900">{{ $article->size }}</dd>
                    <dt class="text-gray-500">Zustand</dt>
                    <dd class="text-gray-900">{{ $article->condition?->label() ?? '–' }}</dd>
                    <dt class="text-gray-500">Preis</dt>
                    <dd class="text-gray-900 font-semibold">{{ $article->formattedPrice() ?? '–' }}</dd>
                    <dt class="text-gray-500">Angelegt</dt>
                    <dd class="text-gray-900">{{ $article->created_at->format('d.m.Y H:i') }}</dd>
                </dl>
            </div>
        </div>
    </div>
</x-app-layout>

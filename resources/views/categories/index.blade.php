<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Kategorien</h2>
            <a href="{{ route('categories.create') }}">
                <x-primary-button type="button">Neue Kategorie</x-primary-button>
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-flash />

            @if ($categories->isEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-6 text-gray-600">
                    Noch keine Kategorien vorhanden.
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($categories as $category)
                        <a href="{{ route('categories.show', $category) }}"
                           class="block bg-white shadow-sm sm:rounded-lg p-6 hover:shadow-md transition">
                            <div class="flex items-start justify-between gap-4">
                                <h3 class="text-lg font-medium text-gray-900">{{ $category->name }}</h3>
                                <span class="shrink-0 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs text-gray-700">
                                    {{ $category->articles_count }} Artikel
                                    @if ($category->sold_count)
                                        · {{ $category->sold_count }} verkauft
                                    @endif
                                </span>
                            </div>
                            @if ($category->description)
                                <p class="mt-2 text-sm text-gray-600 line-clamp-3">{{ $category->description }}</p>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

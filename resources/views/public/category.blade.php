<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $category->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/live-filter.js'])
</head>
<body class="bg-gray-100 font-sans text-gray-900 antialiased">
    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
        <header class="mb-6">
            <h1 class="text-2xl font-semibold">{{ $category->name }}</h1>
            @if ($category->description)
                <p class="mt-2 whitespace-pre-line text-gray-600">{{ $category->description }}</p>
            @endif
            <p class="mt-2 text-sm text-gray-500" data-live-target="counts">
                {{ $articles->where('sold', false)->count() }} verfügbar
                @if ($articles->where('sold', true)->isNotEmpty())
                    · {{ $articles->where('sold', true)->count() }} verkauft
                @endif
            </p>
        </header>

        <x-article-filter :filter="$filter" :category="$category" class="mb-6" />

        <div data-live-target="results">
        @if ($articles->isEmpty())
            <p class="rounded-lg bg-white p-6 text-gray-600 shadow-sm">{{ $filter->isActive() ? 'Keine Artikel passen zum Filter.' : 'Hier gibt es noch keine Artikel.' }}</p>
        @else
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($articles as $article)
                    <figure class="overflow-hidden rounded-lg bg-white shadow-sm">
                        <a href="{{ $article->imageUrl() }}" target="_blank" class="relative block">
                            <img src="{{ $article->imageUrl() }}" alt="{{ $article->displayTitle() }}" loading="lazy"
                                 @class(['aspect-square w-full bg-gray-100 object-cover', 'opacity-50 grayscale' => $article->sold])>
                            @if ($article->sold)
                                <span class="absolute left-2 top-2 rounded-full bg-gray-800 px-2 py-0.5 text-xs font-semibold text-white">Verkauft</span>
                            @endif
                        </a>
                        <figcaption @class(['p-3 text-sm', 'text-gray-400' => $article->sold])>
                            @if ($article->title)
                                <div @class(['truncate font-medium', 'text-gray-900' => ! $article->sold, 'line-through' => $article->sold])>{{ $article->title }}</div>
                            @endif
                            <div @class(['truncate', 'text-gray-600' => ! $article->sold])>{{ $article->brand }} · Gr. {{ $article->size }}</div>
                            <div class="mt-1 flex items-center justify-between gap-2">
                                <span @class(['truncate', 'text-gray-500' => ! $article->sold])>{{ $article->condition?->label() }}</span>
                                @if ($article->price !== null)
                                    <span @class(['shrink-0 font-semibold', 'text-gray-900' => ! $article->sold, 'line-through' => $article->sold])>{{ $article->formattedPrice() }}</span>
                                @endif
                            </div>
                            @if ($article->vinted_url && ! $article->sold)
                                <a href="{{ $article->vinted_url }}" target="_blank" rel="noopener noreferrer"
                                   class="mt-2 block rounded-md bg-teal-600 py-1.5 text-center text-xs font-semibold text-white hover:bg-teal-700">Auf Vinted ansehen</a>
                            @endif
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        @endif
        </div>
    </main>
</body>
</html>

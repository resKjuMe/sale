<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $category->name }} – Druckansicht · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @page {
            size: A4;
            margin: 12mm;
        }

        .print-grid {
            display: grid;
            grid-template-columns: repeat({{ $columns }}, minmax(0, 1fr));
            gap: 6mm;
        }

        .print-card {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        @media print {
            body {
                background: #fff;
            }
        }
    </style>
</head>
<body class="bg-gray-100 font-sans text-gray-900 antialiased">
    <div class="print:hidden sticky top-0 z-10 border-b border-gray-200 bg-white">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-3">
            <a href="{{ route('categories.show', $category) }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; Zurück</a>
            <div class="flex items-center gap-3">
                <span class="text-sm text-gray-500">Spalten:</span>
                @foreach ([2, 3, 4] as $cols)
                    <a href="{{ route('categories.print', [$category, 'cols' => $cols]) }}"
                       @class([
                           'rounded-md px-3 py-1 text-sm',
                           'bg-gray-800 text-white' => $cols === $columns,
                           'bg-gray-100 text-gray-700 hover:bg-gray-200' => $cols !== $columns,
                       ])>{{ $cols }}</a>
                @endforeach
                <button type="button" onclick="window.print()"
                        class="ml-2 rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">
                    Drucken
                </button>
            </div>
        </div>
    </div>

    <main class="mx-auto max-w-5xl bg-white p-8 shadow print:max-w-none print:p-0 print:shadow-none sm:my-6">
        <header class="mb-6 flex items-baseline justify-between border-b border-gray-300 pb-2">
            <h1 class="text-2xl font-semibold">{{ $category->name }}</h1>
            <span class="text-sm text-gray-500">{{ $articles->count() }} Artikel · {{ now()->format('d.m.Y') }}</span>
        </header>

        @if ($articles->isEmpty())
            <p class="text-gray-600">In dieser Kategorie gibt es keine Artikel.</p>
        @else
            <div class="print-grid">
                @foreach ($articles as $article)
                    <figure class="print-card">
                        <div class="relative">
                            <img src="{{ $article->imageUrl() }}" alt="{{ $article->displayTitle() }}"
                                 @class(['aspect-[3/4] w-full border border-gray-200 bg-gray-50 object-cover', 'opacity-60' => $article->sold])>
                            @if ($article->sold)
                                <svg class="absolute inset-0 h-full w-full" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                                    <line x1="0" y1="0" x2="100" y2="100" stroke="#111" stroke-width="2.5" vector-effect="non-scaling-stroke" />
                                    <line x1="100" y1="0" x2="0" y2="100" stroke="#111" stroke-width="2.5" vector-effect="non-scaling-stroke" />
                                </svg>
                            @endif
                        </div>
                        <figcaption @class(['mt-1.5 text-sm leading-snug', 'line-through decoration-2 text-gray-500' => $article->sold])>
                            @if ($article->title)
                                <div class="font-semibold">{{ $article->title }}</div>
                            @endif
                            <div class="flex justify-between gap-2 text-gray-700">
                                <span class="truncate">{{ $article->brand }}</span>
                                <span class="shrink-0">Gr. {{ $article->size }}</span>
                            </div>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        @endif
    </main>
</body>
</html>

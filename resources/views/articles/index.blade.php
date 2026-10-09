<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Alle Artikel</h2>
            <span class="text-sm text-gray-500" data-live-target="overview-count">
                @if ($filter->isActive())
                    {{ $articles->total() }} von {{ $total }} Artikeln
                @else
                    {{ $total }} Artikel
                @endif
            </span>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-flash />

            <x-public-link :url="auth()->user()->overviewUrl()" :regenerate="route('articles.public-link')" />

            <x-article-filter :filter="$filter" :articles="\App\Models\Article::query()" with-categories with-pending with-stale class="mb-6 sm:rounded-lg" />

            @include('articles.partials.grid', [
                'emptyText' => $filter->isActive() ? 'Keine Artikel passen zum Filter.' : 'Es gibt noch keine Artikel.',
                'showCategory' => true,
            ])
        </div>
    </div>
</x-app-layout>

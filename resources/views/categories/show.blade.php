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
                @if (app(\App\Services\ArticleAssistant::class)->configured() && $category->articles()->exists())
                    <div x-data="aiTitles({ urls: @js($category->articles()->pluck('id')->map(fn ($id) => route('articles.ai-title', $id))) })" class="flex items-center gap-2">
                        <button type="button" x-show="!running" x-on:click="start()"
                                class="inline-flex items-center gap-1.5 rounded-md border border-violet-200 bg-violet-50 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-violet-800 transition hover:bg-violet-100">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 1l1.9 5.1L17 8l-5.1 1.9L10 15l-1.9-5.1L3 8l5.1-1.9L10 1zm6 10l.9 2.1L19 14l-2.1.9L16 17l-.9-2.1L13 14l2.1-.9L16 11z" /></svg>
                            Titel per KI
                        </button>
                        <template x-if="running">
                            <span class="inline-flex items-center gap-2 text-sm text-violet-800">
                                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" class="opacity-25" /><path d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" /></svg>
                                <span x-text="`${done} / ${total}`"></span>
                                <button type="button" x-on:click="stopping = true" :disabled="stopping" class="text-xs text-gray-500 underline hover:text-gray-700 disabled:opacity-40" x-text="stopping ? 'Stoppt …' : 'Abbrechen'"></button>
                            </span>
                        </template>
                        <span x-show="message" x-cloak x-text="message" class="text-xs text-violet-700"></span>
                    </div>
                @endif
                <a href="{{ route('categories.print', $category) }}" target="_blank">
                    <x-secondary-button type="button">Druckansicht</x-secondary-button>
                </a>
                <a href="{{ route('categories.collage', [$category, ...$filter->query()]) }}" data-live-target="collage-link">
                    <x-secondary-button type="button">Collagen</x-secondary-button>
                </a>
                <a href="{{ route('categories.edit', $category) }}">
                    <x-secondary-button type="button">Bearbeiten</x-secondary-button>
                </a>
                <form method="POST" action="{{ route('categories.destroy', $category) }}"
                      onsubmit="return confirm('Kategorie inklusive aller {{ $category->articles()->count() }} Artikel löschen?')">
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

            <x-public-link :url="$category->publicUrl()" :regenerate="route('categories.public-link', $category)" />

            <x-article-filter :filter="$filter" :articles="$category->articles()" with-pending with-stale class="mb-6 sm:rounded-lg" />

            @include('articles.partials.grid', [
                'emptyText' => $filter->isActive() ? 'Keine Artikel passen zum Filter.' : 'In dieser Kategorie gibt es noch keine Artikel.',
            ])
        </div>
    </div>
</x-app-layout>

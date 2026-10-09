<x-app-layout>
    <x-slot name="header">
        <a href="{{ route('categories.show', $category) }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ $category->name }}</a>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Collagen</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8"
             x-data="collage({ articles: @js($articles), title: @js($category->name), slug: @js(Str::slug($category->name) ?: 'kategorie') })">
            @if ($articles->isEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-6 text-gray-600">
                    In dieser Kategorie gibt es noch keine Artikel.
                </div>
            @else
                <div class="mb-6 space-y-4 bg-white p-4 shadow-sm sm:rounded-lg">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="w-20 text-sm font-medium text-gray-700">Raster</span>
                        <template x-for="grid in grids" :key="grid.join('x')">
                            <button type="button" x-on:click="setGrid(grid)"
                                    class="rounded-md px-3 py-1.5 text-sm tabular-nums"
                                    :class="cols === grid[0] && rows === grid[1] ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                                    x-text="`${grid[0]} × ${grid[1]}`"></button>
                        </template>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                        <span class="w-20 text-sm font-medium text-gray-700">Optionen</span>
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" x-model="showHeader" class="rounded border-gray-300 text-gray-800 shadow-sm focus:ring-gray-500">
                            Kategoriename als Überschrift
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" x-model="includeSold" class="rounded border-gray-300 text-gray-800 shadow-sm focus:ring-gray-500">
                            Verkaufte einbeziehen
                        </label>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-4">
                        <span class="text-sm text-gray-600">
                            <span x-text="selected.length"></span> Artikel
                            · <span x-text="cols * rows"></span> pro Bild
                            · <strong class="text-gray-900" x-text="pageCount === 1 ? '1 Collage' : `${pageCount} Collagen`"></strong>
                            · 1080 × 1920 px
                        </span>
                        <div class="flex flex-wrap gap-2">
                            <x-secondary-button type="button" x-show="canShare" x-cloak x-on:click="share()" ::disabled="busy">Teilen</x-secondary-button>
                            <x-primary-button type="button" x-on:click="downloadAll()" ::disabled="busy || pages.length === 0">
                                <span x-text="pages.length > 1 ? 'Alle herunterladen' : 'Herunterladen'">Herunterladen</span>
                            </x-primary-button>
                        </div>
                    </div>
                    <p x-show="error" x-cloak x-text="error" class="text-sm text-red-600"></p>
                </div>

                <div x-show="selected.length === 0" x-cloak class="bg-white shadow-sm sm:rounded-lg p-6 text-gray-600">
                    Alle Artikel sind verkauft. Aktiviere „Verkaufte einbeziehen", um sie trotzdem abzubilden.
                </div>

                <div class="grid grid-cols-2 gap-4 px-4 sm:grid-cols-3 sm:px-0 lg:grid-cols-4" :class="busy && 'opacity-60'">
                    <template x-for="(page, index) in pages" :key="page.url">
                        <figure>
                            <a :href="page.url" :download="page.name" class="block overflow-hidden rounded-lg shadow-sm transition hover:shadow-md">
                                <img :src="page.url" :alt="`Collage ${index + 1}`" class="aspect-[9/16] w-full bg-gray-200 object-cover">
                            </a>
                            <figcaption class="mt-1.5 flex items-center justify-between text-sm text-gray-600">
                                <span x-text="`${index + 1} / ${pages.length}`"></span>
                                <a :href="page.url" :download="page.name" class="font-medium text-gray-800 underline hover:text-gray-600">Herunterladen</a>
                            </figcaption>
                        </figure>
                    </template>
                </div>
                <p x-show="busy && pages.length === 0" class="text-sm text-gray-500">Collagen werden erstellt …</p>
            @endif
        </div>
    </div>
</x-app-layout>

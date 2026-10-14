<x-app-layout>
    <x-slot name="header">
        <a href="{{ route('dashboard') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Dashboard</a>
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $title }}</h2>
            @if ($articles->isNotEmpty())
                <span class="text-sm text-gray-500" x-data="{ left: {{ $articles->count() }} }" @quick-fill-done.window="left--">
                    <span x-text="left === 1 ? '1 Artikel' : `${left} Artikel`">{{ $articles->count() === 1 ? '1 Artikel' : $articles->count().' Artikel' }}</span>
                    @if ($type === 'zahlung')
                        · offen <span class="font-semibold text-amber-700">{{ \App\Models\Article::euro($sum) }}</span>
                    @endif
                </span>
            @endif
        </div>
        @if ($hint)
            <p class="mt-1 text-sm text-gray-500">{{ $hint }}</p>
        @endif
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="px-4 sm:px-0"><x-undo-flash /></div>

            <div class="px-4 sm:px-0">
            @if ($fill)
                <x-quick-fill-list :articles="$articles" :type="$type" :fields="$fill" :pickup="$pickup" :empty="$empty" />
            @else
            <x-dashboard-list :title="$mark ? 'Älteste zuerst' : 'Zum Bearbeiten antippen'" accent="amber"
                              :mark="$mark" :edit="! $mark" :articles="$articles" :empty="$empty" />
            @endif
            </div>
        </div>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <a href="{{ route('dashboard') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Dashboard</a>
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $title }}</h2>
            @if ($articles->isNotEmpty())
                <span class="text-sm text-gray-500">
                    {{ $articles->count() === 1 ? '1 Artikel' : $articles->count().' Artikel' }}
                    @if ($type === 'zahlung')
                        · offen <span class="font-semibold text-amber-700">{{ \App\Models\Article::euro($sum) }}</span>
                    @endif
                </span>
            @endif
        </div>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="px-4 sm:px-0"><x-undo-flash /></div>

            <div class="px-4 sm:px-0">
            <x-dashboard-list title="Älteste zuerst" accent="amber"
                              :mark="$type === 'zahlung' ? 'paid' : 'shipped'" :articles="$articles" :empty="$empty" />
            </div>
        </div>
    </div>
</x-app-layout>

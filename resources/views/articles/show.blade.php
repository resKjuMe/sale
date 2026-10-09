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
                    <dt class="text-gray-500">Vinted</dt>
                    <dd class="text-gray-900">
                        @if ($article->vinted_url)
                            <a href="{{ $article->vinted_url }}" target="_blank" rel="noopener noreferrer"
                               class="text-teal-700 underline hover:text-teal-900">Auf Vinted ansehen</a>
                        @else
                            –
                        @endif
                    </dd>
                    <dt class="text-gray-500">Angelegt</dt>
                    <dd class="text-gray-900">{{ $article->created_at->format('d.m.Y H:i') }}</dd>

                    <dt class="col-span-2 mt-3 border-t border-gray-200 pt-3 font-medium text-gray-900">Verkauf</dt>
                    <dt class="text-gray-500">Status</dt>
                    <dd><x-sale-badge :article="$article" /></dd>
                    @if ($article->sold)
                        <dt class="text-gray-500">An wen</dt>
                        <dd class="text-gray-900">{{ $article->buyer_name ?? '–' }}</dd>
                        <dt class="text-gray-500">Adresse</dt>
                        <dd class="whitespace-pre-line text-gray-900">{{ $article->buyer_address ?? '–' }}</dd>
                        <dt class="text-gray-500">Verkaufspreis</dt>
                        <dd class="font-semibold text-gray-900">{{ $article->formattedSalePrice() ?? '–' }}</dd>
                        <dt class="text-gray-500">Versand</dt>
                        <dd class="text-gray-900">
                            @if ($article->shipped)
                                Versendet
                                @if ($article->tracking_code)
                                    · <a href="{{ $article->trackingUrl() }}" target="_blank" rel="noopener"
                                         class="font-mono text-indigo-600 underline hover:text-indigo-800">{{ $article->tracking_code }}</a>
                                @endif
                            @else
                                Noch nicht versendet
                            @endif
                        </dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>
</x-app-layout>

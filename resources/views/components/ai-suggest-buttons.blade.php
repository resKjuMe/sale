@props(['article' => null])

{{-- Ohne API-Key gibt es nichts anzubieten. --}}
@if (app(\App\Services\ArticleAssistant::class)->configured())

<div x-data="aiSuggest({ url: @js(route('articles.ai-suggest')), articleId: @js($article?->id), priceUrl: @js($article ? route('articles.ai-price', $article) : null) })" {{ $attributes->class('space-y-1.5') }}>
    <div class="flex flex-wrap gap-2">
        {{-- Der Preisvorschlag braucht gespeicherte Fotos, also nur beim Bearbeiten. --}}
        @foreach (['brand_size' => 'Marke & Größe erkennen', 'title' => 'Titel vorschlagen', ...($article ? ['price' => 'Preis vorschlagen'] : [])] as $mode => $label)
            <button type="button" x-on:click="{{ $mode === 'price' ? 'suggestPrice()' : 'suggest('.\Illuminate\Support\Js::from($mode).')' }}" :disabled="busy !== null"
                    class="inline-flex items-center gap-1.5 rounded-md border border-violet-200 bg-violet-50 px-3 py-1.5 text-xs font-semibold text-violet-800 transition hover:bg-violet-100 disabled:opacity-50">
                <svg x-show="busy !== @js($mode)" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 1l1.9 5.1L17 8l-5.1 1.9L10 15l-1.9-5.1L3 8l5.1-1.9L10 1zm6 10l.9 2.1L19 14l-2.1.9L16 17l-.9-2.1L13 14l2.1-.9L16 11z" /></svg>
                <svg x-show="busy === @js($mode)" x-cloak class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" class="opacity-25" /><path d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" /></svg>
                {{ $label }}
            </button>
        @endforeach
    </div>
    <p x-show="message" x-cloak x-text="message" class="text-xs" :class="failed ? 'text-red-600' : 'text-violet-700'"></p>
</div>
@endif

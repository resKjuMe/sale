@props(['article'])

@if ($article->sold)
    <span {{ $attributes->class('inline-flex flex-wrap gap-1') }}>
        <span class="rounded-full bg-gray-800 px-2 py-0.5 text-xs font-semibold text-white">Verkauft</span>
        @if ($article->paid)
            <span class="rounded-full bg-green-600 px-2 py-0.5 text-xs font-semibold text-white">Bezahlt</span>
        @else
            <span class="rounded-full bg-amber-500 px-2 py-0.5 text-xs font-semibold text-white">Offen</span>
        @endif
        @if ($article->pickup && $article->picked_up)
            <span class="rounded-full bg-blue-600 px-2 py-0.5 text-xs font-semibold text-white">Abgeholt</span>
        @elseif ($article->pickup)
            <span class="rounded-full bg-gray-200 px-2 py-0.5 text-xs font-semibold text-gray-700">Abholung</span>
        @elseif ($article->shipped)
            <span class="rounded-full bg-blue-600 px-2 py-0.5 text-xs font-semibold text-white">Versendet</span>
        @endif
    </span>
@else
    <span {{ $attributes->class('rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700') }}>Verfügbar</span>
@endif

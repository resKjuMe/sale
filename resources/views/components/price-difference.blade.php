@props(['article'])

@if ($difference = $article->formattedPriceDifference())
    <span {{ $attributes->class([
        'whitespace-nowrap text-xs font-medium',
        'text-rose-600' => $article->priceDifference() < 0,
        'text-green-600' => $article->priceDifference() > 0,
    ]) }} title="gegenüber Angebotspreis {{ $article->formattedPrice() }}">{{ $difference }}</span>
@endif

<?php

namespace App\Support;

use App\Models\Category;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ArticleFilter
{
    /**
     * @param  list<string>  $sizes
     * @param  list<string>  $brands
     * @param  list<int>  $categories
     * @param  list<string>  $pending  'payment' und/oder 'shipping', nur intern
     */
    public function __construct(
        public readonly array $sizes = [],
        public readonly array $brands = [],
        public readonly bool $hideSold = false,
        public readonly array $categories = [],
        public readonly array $pending = [],
        public readonly bool $stale = false,
        public readonly bool $soldOnly = false,
        public readonly bool $paidOnly = false,
    ) {}

    // Interne Status-Filter: Parameter => Beschriftung
    public const STATUS = ['sold' => 'Verkauft', 'paid' => 'Bezahlt'];

    public const PENDING = ['payment' => 'Zahlung ausstehend', 'shipping' => 'Versand/Abholung ausstehend'];

    public static function fromRequest(Request $request, bool $internal = true): self
    {
        return new self(
            self::list($request, 'size'),
            self::list($request, 'brand'),
            $request->boolean('hide_sold'),
            array_values(array_filter(array_map('intval', self::list($request, 'category')))),
            $internal ? array_values(array_intersect(array_keys(self::PENDING), self::list($request, 'pending'))) : [],
            $internal && $request->boolean('stale'),
            $internal && $request->boolean('sold'),
            $internal && $request->boolean('paid'),
        );
    }

    public function apply(Builder $query): Builder
    {
        return $query
            ->when($this->sizes, fn (Builder $query) => $query->whereIn('size', $this->sizes))
            ->when($this->brands, fn (Builder $query) => $query->whereIn('brand', $this->brands))
            ->when($this->hideSold, fn (Builder $query) => $query->where('sold', false))
            ->when($this->categories, fn (Builder $query) => $query->whereIn('category_id', $this->categories))
            ->when($this->pending, fn (Builder $query) => $query->where(function (Builder $query) {
                foreach ($this->pending as $pending) {
                    $query->orWhere(fn (Builder $query) => $pending === 'payment' ? $query->paymentPending() : $query->shippingPending());
                }
            }))
            ->when($this->stale, fn (Builder $query) => $query->stale())
            ->when($this->soldOnly, fn (Builder $query) => $query->where('sold', true))
            ->when($this->paidOnly, fn (Builder $query) => $query->where('sold', true)->where('paid', true));
    }

    public function isActive(): bool
    {
        return $this->sizes !== [] || $this->brands !== [] || $this->hideSold || $this->categories !== [] || $this->pending !== [] || $this->stale || $this->soldOnly || $this->paidOnly;
    }

    public function query(): array
    {
        return array_filter([
            'category' => $this->categories,
            'size' => $this->sizes,
            'brand' => $this->brands,
            'hide_sold' => $this->hideSold ? 1 : null,
            'pending' => $this->pending,
            'stale' => $this->stale ? 1 : null,
            'sold' => $this->soldOnly ? 1 : null,
            'paid' => $this->paidOnly ? 1 : null,
        ]);
    }

    public function without(string $key): self
    {
        return new self(
            $key === 'size' ? [] : $this->sizes,
            $key === 'brand' ? [] : $this->brands,
            $this->hideSold,
            $key === 'category' ? [] : $this->categories,
            $key === 'pending' ? [] : $this->pending,
            $key === 'stale' ? false : $this->stale,
            $key === 'sold' ? false : $this->soldOnly,
            $key === 'paid' ? false : $this->paidOnly,
        );
    }

    /**
     * Anzahl je Wert unter allen übrigen Filtern; Werte ohne Treffer fehlen.
     *
     * @param  'size'|'brand'|'category'  $key
     * @return array<string, int> Wert => Anzahl, natürlich sortiert
     */
    public function facet(Builder $articles, string $key): array
    {
        $column = $key === 'category' ? 'category_id' : $key;

        return $this->without($key)->apply(clone $articles)->toBase()
            ->selectRaw("$column as value, count(*) as total")
            ->groupBy($column)
            ->get()
            ->mapWithKeys(fn ($row) => [(string) $row->value => (int) $row->total])
            ->sortKeysUsing(fn ($a, $b) => strnatcasecmp((string) $a, (string) $b))
            ->all();
    }

    /**
     * @return array<string, int> 'payment'/'shipping' => Anzahl unter allen übrigen Filtern
     */
    /**
     * @return array<string, int> 'sold'/'paid' => Anzahl unter allen übrigen Filtern
     */
    public function statusCounts(Builder $articles): array
    {
        return [
            'sold' => $this->without('sold')->apply(clone $articles)->where('sold', true)->count(),
            'paid' => $this->without('paid')->apply(clone $articles)->where('sold', true)->where('paid', true)->count(),
        ];
    }

    public function isStatusSelected(string $status): bool
    {
        return $status === 'sold' ? $this->soldOnly : $this->paidOnly;
    }

    public function staleCount(Builder $articles): int
    {
        return $this->without('stale')->apply(clone $articles)->stale()->count();
    }

    public function pendingCounts(Builder $articles): array
    {
        $base = $this->without('pending');

        return [
            'payment' => $base->apply(clone $articles)->paymentPending()->count(),
            'shipping' => $base->apply(clone $articles)->shippingPending()->count(),
        ];
    }

    /**
     * @return list<string>
     */
    private static function list(Request $request, string $key): array
    {
        return Collection::wrap($request->query($key, []))
            ->filter(fn ($value) => is_string($value))
            ->map(fn (string $value) => trim($value))
            ->filter(fn (string $value) => $value !== '')
            ->unique()
            ->values()
            ->all();
    }
}

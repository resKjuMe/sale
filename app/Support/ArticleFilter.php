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
     */
    public function __construct(
        public readonly array $sizes = [],
        public readonly array $brands = [],
        public readonly bool $hideSold = false,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(self::list($request, 'size'), self::list($request, 'brand'), $request->boolean('hide_sold'));
    }

    public function apply(Builder $query): Builder
    {
        return $query
            ->when($this->sizes, fn (Builder $query) => $query->whereIn('size', $this->sizes))
            ->when($this->brands, fn (Builder $query) => $query->whereIn('brand', $this->brands))
            ->when($this->hideSold, fn (Builder $query) => $query->where('sold', false));
    }

    public function isActive(): bool
    {
        return $this->sizes !== [] || $this->brands !== [] || $this->hideSold;
    }

    public function query(): array
    {
        return array_filter(['size' => $this->sizes, 'brand' => $this->brands, 'hide_sold' => $this->hideSold ? 1 : null]);
    }

    /**
     * @return list<string> natürlich sortiert
     */
    public static function options(Category $category, string $column): array
    {
        return $category->articles()->distinct()->pluck($column)
            ->map(fn ($value) => (string) $value)
            ->sort(fn (string $a, string $b) => strnatcasecmp($a, $b))
            ->values()
            ->all();
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

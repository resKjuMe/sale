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
     */
    public function __construct(
        public readonly array $sizes = [],
        public readonly array $brands = [],
        public readonly bool $hideSold = false,
        public readonly array $categories = [],
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            self::list($request, 'size'),
            self::list($request, 'brand'),
            $request->boolean('hide_sold'),
            array_values(array_filter(array_map('intval', self::list($request, 'category')))),
        );
    }

    public function apply(Builder $query): Builder
    {
        return $query
            ->when($this->sizes, fn (Builder $query) => $query->whereIn('size', $this->sizes))
            ->when($this->brands, fn (Builder $query) => $query->whereIn('brand', $this->brands))
            ->when($this->hideSold, fn (Builder $query) => $query->where('sold', false))
            ->when($this->categories, fn (Builder $query) => $query->whereIn('category_id', $this->categories));
    }

    public function isActive(): bool
    {
        return $this->sizes !== [] || $this->brands !== [] || $this->hideSold || $this->categories !== [];
    }

    public function query(): array
    {
        return array_filter([
            'category' => $this->categories,
            'size' => $this->sizes,
            'brand' => $this->brands,
            'hide_sold' => $this->hideSold ? 1 : null,
        ]);
    }

    /**
     * @return array<string, int> Wert => Anzahl, natürlich sortiert
     */
    public static function options(Builder $articles, string $column): array
    {
        return (clone $articles)->toBase()
            ->selectRaw("$column as value, count(*) as total")
            ->groupBy($column)
            ->get()
            ->mapWithKeys(fn ($row) => [(string) $row->value => (int) $row->total])
            ->sortKeysUsing(fn ($a, $b) => strnatcasecmp((string) $a, (string) $b))
            ->all();
    }

    /**
     * @return array<int, array{0: string, 1: int}> Kategorie-ID => [Name, Anzahl], nach Name sortiert
     */
    public static function categoryOptions(): array
    {
        return Category::withCount('articles')->orderBy('name')->get()
            ->filter(fn (Category $category) => $category->articles_count > 0)
            ->mapWithKeys(fn (Category $category) => [$category->id => [$category->name, $category->articles_count]])
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

<?php

namespace App\Support;

use App\Models\Category;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ArticleFilter
{
    public const STATUSES = ['available' => 'Verfügbar', 'sold' => 'Verkauft'];

    /**
     * @param  list<string>  $sizes
     */
    public function __construct(
        public readonly array $sizes = [],
        public readonly ?string $brand = null,
        public readonly ?string $status = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $sizes = array_values(array_unique(array_filter(
            array_map(fn ($size) => is_string($size) ? trim($size) : '', (array) $request->query('size', [])),
            fn (string $size) => $size !== '',
        )));
        $brand = is_string($request->query('brand')) ? trim($request->query('brand')) : '';
        $status = $request->query('status');

        return new self($sizes, $brand === '' ? null : $brand, array_key_exists((string) $status, self::STATUSES) ? $status : null);
    }

    public function apply(Builder $query): Builder
    {
        return $query
            ->when($this->sizes, fn (Builder $query) => $query->whereIn('size', $this->sizes))
            ->when($this->brand !== null, fn (Builder $query) => $query->where('brand', $this->brand))
            ->when($this->status !== null, fn (Builder $query) => $query->where('sold', $this->status === 'sold'));
    }

    public function isActive(): bool
    {
        return $this->sizes !== [] || $this->brand !== null || $this->status !== null;
    }

    public function query(): array
    {
        return array_filter(['size' => $this->sizes, 'brand' => $this->brand, 'status' => $this->status]);
    }

    /**
     * @return Collection<string, int> Größe => Anzahl, natürlich sortiert
     */
    public static function sizeOptions(Category $category): Collection
    {
        return self::counted($category, 'size');
    }

    /**
     * @return Collection<string, int> Marke => Anzahl
     */
    public static function brandOptions(Category $category): Collection
    {
        return self::counted($category, 'brand');
    }

    private static function counted(Category $category, string $column): Collection
    {
        return $category->articles()
            ->selectRaw("$column as value, count(*) as total")
            ->groupBy($column)
            ->pluck('total', 'value')
            ->sortKeysUsing(fn ($a, $b) => strnatcasecmp((string) $a, (string) $b));
    }
}

<?php

namespace App\Models;

use App\Enums\ArticleCondition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Article extends Model
{
    protected $fillable = [
        'image_path', 'title', 'brand', 'size', 'condition', 'price',
        'sold', 'buyer_name', 'buyer_address', 'paid', 'sale_price',
    ];

    protected $attributes = [
        'sold' => false,
        'paid' => false,
    ];

    protected function casts(): array
    {
        return [
            'condition' => ArticleCondition::class,
            'price' => 'decimal:2',
            'sold' => 'boolean',
            'paid' => 'boolean',
            'sale_price' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(fn (Article $article) => Storage::disk('public')->delete($article->image_path));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function imageUrl(): string
    {
        return Storage::disk('public')->url($this->image_path);
    }

    public function displayTitle(): string
    {
        return $this->title ?: $this->brand.' · '.$this->size;
    }

    public function formattedPrice(): ?string
    {
        return self::euro($this->price);
    }

    public function formattedSalePrice(): ?string
    {
        return self::euro($this->sale_price);
    }

    private static function euro(?string $amount): ?string
    {
        return $amount === null ? null : number_format((float) $amount, 2, ',', '.').' €';
    }
}

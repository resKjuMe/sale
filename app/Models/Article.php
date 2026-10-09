<?php

namespace App\Models;

use App\Enums\ArticleCondition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Article extends Model
{
    protected $fillable = [
        'image_path', 'title', 'brand', 'size', 'condition', 'price', 'vinted_url',
        'sold', 'buyer_name', 'buyer_address', 'paid', 'sale_price', 'shipped', 'tracking_code',
    ];

    public const UNSOLD_RESET = [
        'buyer_name' => null,
        'buyer_address' => null,
        'paid' => false,
        'sale_price' => null,
        'shipped' => false,
        'tracking_code' => null,
    ];

    protected $attributes = [
        'sold' => false,
        'paid' => false,
        'shipped' => false,
    ];

    protected function casts(): array
    {
        return [
            'condition' => ArticleCondition::class,
            'price' => 'decimal:2',
            'sold' => 'boolean',
            'paid' => 'boolean',
            'sale_price' => 'decimal:2',
            'shipped' => 'boolean',
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

    public function markSold(bool $sold): void
    {
        $this->fill(['sold' => $sold] + ($sold ? [] : self::UNSOLD_RESET))->save();
    }

    public function hasSaleDetails(): bool
    {
        return $this->buyer_name !== null || $this->buyer_address !== null || $this->sale_price !== null
            || $this->paid || $this->shipped || $this->tracking_code !== null;
    }

    public function trackingUrl(): ?string
    {
        return $this->tracking_code === null
            ? null
            : 'https://www.dhl.de/de/privatkunden/pakete-empfangen/verfolgen.html?piececode='.rawurlencode($this->tracking_code);
    }

    private static function euro(?string $amount): ?string
    {
        return $amount === null ? null : number_format((float) $amount, 2, ',', '.').' €';
    }
}

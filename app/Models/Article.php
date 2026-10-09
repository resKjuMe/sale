<?php

namespace App\Models;

use App\Enums\ArticleCondition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Article extends Model
{
    protected $fillable = [
        'image_path', 'title', 'brand', 'size', 'condition', 'price', 'vinted_url',
        'sold', 'buyer_name', 'buyer_address', 'paid', 'sale_price', 'shipping_cost', 'shipped', 'tracking_code',
    ];

    public const STATUS_FLAGS = ['sold', 'paid', 'shipped'];

    public const UNSOLD_RESET = [
        'buyer_name' => null,
        'buyer_address' => null,
        'paid' => false,
        'sale_price' => null,
        'shipping_cost' => null,
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
            'sold_at' => 'datetime',
            'paid' => 'boolean',
            'paid_at' => 'datetime',
            'sale_price' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'shipped' => 'boolean',
            'shipped_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Zeitpunkt beim Setzen merken, beim Zurücknehmen löschen.
        static::saving(function (Article $article) {
            foreach (self::STATUS_FLAGS as $flag) {
                if (! $article->exists || $article->isDirty($flag)) {
                    $article->{"{$flag}_at"} = $article->{$flag} ? ($article->{"{$flag}_at"} ?? now()) : null;
                }
            }
        });
        static::deleted(fn (Article $article) => Storage::disk('public')->delete($article->image_path));
    }

    public function scopePaymentPending(Builder $query): void
    {
        $query->where('sold', true)->where('paid', false);
    }

    public function scopeShippingPending(Builder $query): void
    {
        $query->where('sold', true)->where('shipped', false);
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

    public function formattedShippingCost(): ?string
    {
        return self::euro($this->shipping_cost);
    }

    // Verkaufspreis, sonst Angebotspreis; 0 € gilt als nicht erfasst.
    public function effectiveSalePrice(): ?float
    {
        foreach ([$this->sale_price, $this->price] as $amount) {
            if ((float) $amount > 0) {
                return (float) $amount;
            }
        }

        return null;
    }

    // Verkaufs- minus Angebotspreis, nur wenn beide erfasst sind und sich unterscheiden.
    public function priceDifference(): ?float
    {
        if ((float) $this->sale_price <= 0 || (float) $this->price <= 0) {
            return null;
        }
        $difference = round((float) $this->sale_price - (float) $this->price, 2);

        return $difference == 0 ? null : $difference;
    }

    public function formattedPriceDifference(): ?string
    {
        $difference = $this->priceDifference();

        return $difference === null ? null : ($difference < 0 ? '−' : '+').self::euro(abs($difference));
    }

    // Was der Käufer insgesamt zahlt.
    public function amountDue(): float
    {
        return ($this->effectiveSalePrice() ?? 0) + (float) ($this->shipping_cost ?? 0);
    }

    public function markSold(bool $sold): void
    {
        $this->fill(['sold' => $sold] + ($sold ? [] : self::UNSOLD_RESET))->save();
    }

    // Gespeichert wird in UTC, angezeigt in deutscher Zeit.
    public function statusDate(string $flag, string $format = 'd.m.Y, H:i'): ?string
    {
        return $this->{"{$flag}_at"}?->timezone('Europe/Berlin')->format($format);
    }

    public function soldSinceLabel(): ?string
    {
        if ($this->sold_at === null) {
            return null;
        }
        $days = (int) $this->sold_at->copy()->timezone('Europe/Berlin')->startOfDay()->diffInDays(now('Europe/Berlin')->startOfDay());

        return match ($days) {
            0 => 'heute',
            1 => 'seit gestern',
            default => "seit $days Tagen",
        };
    }

    public function hasSaleDetails(): bool
    {
        return $this->buyer_name !== null || $this->buyer_address !== null || $this->sale_price !== null || $this->shipping_cost !== null
            || $this->paid || $this->shipped || $this->tracking_code !== null;
    }

    public function trackingUrl(): ?string
    {
        return $this->tracking_code === null
            ? null
            : 'https://www.dhl.de/de/privatkunden/pakete-empfangen/verfolgen.html?piececode='.rawurlencode($this->tracking_code);
    }

    public static function euro(string|float|null $amount): ?string
    {
        return $amount === null ? null : number_format((float) $amount, 2, ',', '.').' €';
    }
}

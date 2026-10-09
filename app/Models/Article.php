<?php

namespace App\Models;

use App\Enums\ArticleCondition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Article extends Model
{
    protected $fillable = [
        'image_path', 'title', 'brand', 'size', 'condition', 'price', 'vinted_url',
        'sold', 'buyer_name', 'buyer_address', 'paid', 'sale_price', 'shipping_cost', 'pickup', 'picked_up', 'shipped', 'tracking_code',
    ];

    public const MAX_EXTRA_IMAGES = 8;

    public const STATUS_FLAGS = ['sold', 'paid', 'shipped', 'picked_up'];

    public const UNSOLD_RESET = [
        'buyer_name' => null,
        'buyer_address' => null,
        'paid' => false,
        'sale_price' => null,
        'shipping_cost' => null,
        'pickup' => false,
        'picked_up' => false,
        'shipped' => false,
        'tracking_code' => null,
    ];

    protected $attributes = [
        'sold' => false,
        'paid' => false,
        'shipped' => false,
        'pickup' => false,
        'picked_up' => false,
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
            'pickup' => 'boolean',
            'picked_up' => 'boolean',
            'picked_up_at' => 'datetime',
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
        // Zusatzfotos einzeln löschen, damit ihre Dateien mit verschwinden (die FK-Kaskade kennt keine Dateien).
        static::deleting(fn (Article $article) => $article->images->each->delete());
        static::deleted(fn (Article $article) => Storage::disk('public')->delete($article->image_path));
    }

    public function scopeSold(Builder $query): void
    {
        $query->where('sold', true);
    }

    // Verkaufs- oder Angebotspreis fehlt (0 € zählt als fehlend).
    public function scopeWithoutBothPrices(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereNull('sale_price')->orWhere('sale_price', '<=', 0)->orWhereNull('price')->orWhere('price', '<=', 0));
    }

    // Versand ohne erfasste Versandkosten; Selbstabholung braucht keine.
    public function scopeWithoutShippingCost(Builder $query): void
    {
        $query->where('pickup', false)->whereNull('shipping_cost');
    }

    // Weder Verkaufs- noch Angebotspreis über 0 €.
    public function scopeWithoutPrice(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query->whereNull('sale_price')->orWhere('sale_price', '<=', 0))
            ->where(fn (Builder $query) => $query->whereNull('price')->orWhere('price', '<=', 0));
    }

    public function scopePaymentPending(Builder $query): void
    {
        $query->where('sold', true)->where('paid', false);
    }

    // Noch zu übergeben: Versand offen oder Selbstabholung noch nicht abgeholt.
    public function scopeShippingPending(Builder $query): void
    {
        $query->where('sold', true)->where(fn (Builder $query) => $query
            ->where(fn (Builder $query) => $query->where('pickup', false)->where('shipped', false))
            ->orWhere(fn (Builder $query) => $query->where('pickup', true)->where('picked_up', false)));
    }

    public function handedOver(): bool
    {
        return $this->pickup ? $this->picked_up : $this->shipped;
    }

    public function images(): HasMany
    {
        return $this->hasMany(ArticleImage::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return list<string> Hauptfoto zuerst, dann die Zusatzfotos
     */
    public function imageUrls(): array
    {
        return [$this->imageUrl(), ...$this->images->map->url()];
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
            || $this->paid || $this->shipped || $this->tracking_code !== null || $this->pickup || $this->picked_up;
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

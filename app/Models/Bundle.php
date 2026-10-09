<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Bestellung: mehrere Artikel an denselben Käufer mit gemeinsamer Zahlung und Übergabe, ggf. als Konvolut zum Gesamtpreis.
class Bundle extends Model
{
    use BelongsToTenant;

    protected $fillable = ['buyer_name'];

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class)->orderBy('id');
    }

    public function amountDue(): float
    {
        return round($this->articles->sum(fn (Article $article) => $article->amountDue()), 2);
    }

    public function listTotal(): float
    {
        return round($this->articles->sum(fn (Article $article) => (float) $article->price), 2);
    }

    public function saleTotal(): float
    {
        return round($this->articles->sum(fn (Article $article) => (float) $article->sale_price), 2);
    }

    public function shippingTotal(): float
    {
        return round($this->articles->sum(fn (Article $article) => (float) $article->shipping_cost), 2);
    }

    // Rabatt gegenüber den Angebotspreisen; nur sinnvoll, wenn alle Positionen beide Preise haben.
    public function discount(): ?float
    {
        $complete = $this->articles->every(fn (Article $article) => (float) $article->price > 0 && (float) $article->sale_price > 0);

        return $complete ? round($this->listTotal() - $this->saleTotal(), 2) : null;
    }

    /**
     * Verteilt einen Gesamtpreis anteilig nach Angebotspreis (ohne Angebotspreis zu gleichen Teilen).
     * Rundungscent landen bei der teuersten Position, damit die Summe genau stimmt.
     *
     * @return array<int, float> Artikel-ID => Anteil
     */
    public static function distribute(Collection $articles, float $total): array
    {
        $weights = $articles->mapWithKeys(fn (Article $article) => [$article->id => max(0.0, (float) $article->price)]);
        if ($weights->sum() <= 0) {
            $weights = $weights->map(fn () => 1.0);
        }

        $shares = $weights->map(fn (float $weight) => round($total * $weight / $weights->sum(), 2));
        $largest = $weights->sortDesc()->keys()->first();
        $shares[$largest] = round($shares[$largest] + $total - $shares->sum(), 2);

        return $shares->all();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Sammelverkauf: mehrere Artikel an denselben Käufer mit gemeinsamer Zahlung und Übergabe.
class Bundle extends Model
{
    protected $fillable = ['buyer_name'];

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class)->orderBy('id');
    }

    public function amountDue(): float
    {
        return round($this->articles->sum(fn (Article $article) => $article->amountDue()), 2);
    }
}

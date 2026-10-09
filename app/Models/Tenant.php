<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Mandant: alle Kategorien, Artikel und Bestellungen gehören genau einem.
class Tenant extends Model
{
    protected $fillable = ['name'];

    public static function currentId(): ?int
    {
        return auth()->user()?->tenant_id;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}

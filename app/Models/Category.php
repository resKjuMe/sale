<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    protected $fillable = ['name', 'description'];

    protected static function booted(): void
    {
        static::creating(fn (Category $category) => $category->public_token ??= Str::random(32));
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function publicUrl(): string
    {
        return route('public.category', $this->public_token);
    }

    public function regeneratePublicToken(): void
    {
        $this->forceFill(['public_token' => Str::random(32)])->save();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ArticleImage extends Model
{
    protected $fillable = ['path', 'position'];

    protected static function booted(): void
    {
        static::deleted(fn (ArticleImage $image) => Storage::disk('public')->delete($image->path));
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}

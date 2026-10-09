<?php

namespace App\Models;

use App\Enums\ArticleCondition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Article extends Model
{
    protected $fillable = ['image_path', 'title', 'brand', 'size', 'condition', 'price'];

    protected function casts(): array
    {
        return [
            'condition' => ArticleCondition::class,
            'price' => 'decimal:2',
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
        return $this->price === null ? null : number_format((float) $this->price, 2, ',', '.').' €';
    }
}

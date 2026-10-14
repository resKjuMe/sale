<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Ein Anthropic-Konto für alle Mandanten, daher ohne Mandanten-Scope.
class AiCredit extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'amount_micros'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

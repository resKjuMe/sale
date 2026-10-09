<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;

/**
 * Angemeldete sehen nur Datensätze ihres Mandanten; neue Datensätze bekommen ihn automatisch.
 * Ohne Anmeldung (öffentliche Links, Konsole) greift kein Scope – dort muss explizit gefiltert werden.
 */
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            if (($tenantId = Tenant::currentId()) !== null) {
                $query->where($query->qualifyColumn('tenant_id'), $tenantId);
            }
        });

        static::creating(function ($model) {
            $model->tenant_id ??= Tenant::currentId();
        });
    }

    public function scopeForTenant(Builder $query, ?int $tenantId): void
    {
        $query->withoutGlobalScope('tenant')->where($query->qualifyColumn('tenant_id'), $tenantId);
    }
}

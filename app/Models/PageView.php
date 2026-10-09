<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

// Aufruf eines öffentlichen Links – ohne Cookies und ohne gespeicherte IP.
class PageView extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    // Link-Vorschauen (Messenger), Bots und Skripte zählen nicht als Besuch.
    private const IGNORED_AGENTS = '/bot|crawl|spider|slurp|preview|whatsapp|telegram|facebookexternalhit|slack|discord|signal|skype|curl|wget|python|headless|lighthouse/i';

    protected $fillable = ['tenant_id', 'category_id', 'visitor', 'viewed_on'];

    protected function casts(): array
    {
        return ['viewed_on' => 'date'];
    }

    public static function record(Request $request, int $tenantId, ?int $categoryId): void
    {
        $agent = (string) $request->userAgent();
        // Filter-Klicks laden die Seite im Hintergrund nach – kein neuer Aufruf.
        if ($request->hasHeader('X-Live-Filter') || $agent === '' || preg_match(self::IGNORED_AGENTS, $agent)) {
            return;
        }

        $today = now()->toDateString();
        static::create([
            'tenant_id' => $tenantId,
            'category_id' => $categoryId,
            // Täglich wechselnder Hash: zählt Besucher je Tag, lässt sich aber nicht auf IP/Gerät zurückführen.
            'visitor' => substr(hash_hmac('sha256', $today.'|'.$request->ip().'|'.$agent, (string) config('app.key')), 0, 16),
            'viewed_on' => $today,
        ]);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}

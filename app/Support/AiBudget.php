<?php

namespace App\Support;

use App\Models\AiCredit;
use App\Models\AiUsage;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Restguthaben des Anthropic-Kontos: per API nicht abrufbar, daher eingetragenes Guthaben minus seitdem gezählter Verbrauch.
 */
class AiBudget
{
    /**
     * @param  array{cost: int, input: int, output: int, requests: int}  $month
     * @param  array<string, int>  $perRequest  Modus => durchschnittliche Mikro-Dollar je Anfrage
     */
    public function __construct(
        public readonly ?AiCredit $credit,
        public readonly int $spentSinceCredit,
        public readonly array $month,
        public readonly ?float $microsPerToken,
        public readonly array $perRequest,
        public readonly int $unpriced,
    ) {}

    public static function current(): self
    {
        $credit = AiCredit::query()->latest('id')->first();
        $totals = fn (Builder $query) => $query->toBase()->selectRaw(
            'coalesce(sum(cost_micros), 0) as cost, coalesce(sum(input_tokens + cache_read_tokens + cache_write_tokens), 0) as input, coalesce(sum(output_tokens), 0) as output, count(*) as requests'
        )->first();

        $month = $totals(AiUsage::query()->where('created_at', '>=', now()->startOfMonth()));
        $priced = $totals(AiUsage::query()->whereNotNull('cost_micros'));
        $tokens = (int) $priced->input + (int) $priced->output;

        return new self(
            $credit,
            $credit ? (int) AiUsage::query()->where('created_at', '>=', $credit->created_at)->sum('cost_micros') : 0,
            ['cost' => (int) $month->cost, 'input' => (int) $month->input, 'output' => (int) $month->output, 'requests' => (int) $month->requests],
            $tokens > 0 ? (int) $priced->cost / $tokens : null,
            AiUsage::query()->whereNotNull('cost_micros')->groupBy('mode')->selectRaw('mode, avg(cost_micros) as average')
                ->pluck('average', 'mode')->map(fn ($average) => (int) round($average))->filter()->all(),
            AiUsage::query()->whereNull('cost_micros')->count(),
        );
    }

    public function remaining(): ?int
    {
        return $this->credit ? $this->credit->amount_micros - $this->spentSinceCredit : null;
    }

    // Grob, nach dem bisherigen Mix aus Eingabe- und Ausgabe-Tokens.
    public function remainingTokens(): ?int
    {
        $remaining = $this->remaining();

        return $remaining !== null && $this->microsPerToken ? max(0, (int) ($remaining / $this->microsPerToken)) : null;
    }

    public function remainingRequests(string $mode): ?int
    {
        $remaining = $this->remaining();
        $average = $this->perRequest[$mode] ?? null;

        return $remaining !== null && $average ? max(0, intdiv($remaining, $average)) : null;
    }

    public static function dollars(int $micros, int $decimals = 2): string
    {
        return number_format($micros / 1_000_000, $decimals, ',', '.').' $';
    }
}

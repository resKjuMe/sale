<?php

namespace App\Models;

use Anthropic\Beta\Messages\BetaFallbackMessageIterationUsage;
use Anthropic\Beta\Messages\BetaMessage;
use Anthropic\Beta\Messages\BetaMessageIterationUsage;
use Illuminate\Database\Eloquent\Model;

// Verbrauch des gemeinsamen Anthropic-Kontos; tenant_id nur zur Aufschlüsselung, kein Scope.
class AiUsage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['tenant_id', 'mode', 'model', 'input_tokens', 'output_tokens', 'cache_read_tokens', 'cache_write_tokens', 'cost_micros'];

    /**
     * Bei Fallbacks deckt die Top-Level-Usage nur den letzten Durchlauf ab; abgerechnet wird jeder in usage.iterations.
     */
    public static function record(BetaMessage $message, string $mode, string $requestedModel): void
    {
        $runs = array_map(
            fn ($run) => [$run, $run->model ?? $requestedModel],
            array_filter($message->usage->iterations ?? [], fn ($run) => $run instanceof BetaMessageIterationUsage || $run instanceof BetaFallbackMessageIterationUsage),
        ) ?: [[$message->usage, $message->model]];

        foreach ($runs as [$run, $model]) {
            $tokens = [
                'input_tokens' => $run->inputTokens,
                'output_tokens' => $run->outputTokens,
                'cache_read_tokens' => $run->cacheReadInputTokens ?? 0,
                'cache_write_tokens' => $run->cacheCreationInputTokens ?? 0,
            ];

            self::create([
                'tenant_id' => Tenant::currentId(),
                'mode' => $mode,
                'model' => $model,
                ...$tokens,
                'cost_micros' => self::cost($model, $tokens),
            ]);
        }
    }

    /**
     * @param  array{input_tokens: int, output_tokens: int, cache_read_tokens: int, cache_write_tokens: int}  $tokens
     */
    public static function cost(string $model, array $tokens): ?int
    {
        // $/MTok × Tokens = Mikro-Dollar
        [$input, $output] = config("services.anthropic.prices.$model") ?? [null, null];
        if ($input === null) {
            return null;
        }

        return (int) round($tokens['input_tokens'] * $input + $tokens['output_tokens'] * $output
            + $tokens['cache_read_tokens'] * $input * 0.1 + $tokens['cache_write_tokens'] * $input * 1.25);
    }
}

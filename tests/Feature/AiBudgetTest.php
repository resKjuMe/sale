<?php

namespace Tests\Feature;

use Anthropic\Beta\Messages\BetaMessage;
use App\Models\AiCredit;
use App\Models\AiUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiBudgetTest extends TestCase
{
    use RefreshDatabase;

    private function message(string $model, int $input, int $output, ?array $iterations = null): BetaMessage
    {
        return BetaMessage::fromArray([
            'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'content' => [], 'model' => $model, 'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => $input, 'output_tokens' => $output, 'cache_read_input_tokens' => 0, 'cache_creation_input_tokens' => 0, 'iterations' => $iterations],
        ]);
    }

    public function test_usage_is_recorded_with_cost_per_billed_run(): void
    {
        $this->actingAs($user = User::factory()->create());

        AiUsage::record($this->message('claude-sonnet-5-5', 1000, 200), 'brand_size', 'claude-sonnet-5-5');
        $this->assertSame(1000 * 2 + 200 * 10, AiUsage::sole()->cost_micros);
        $this->assertSame($user->tenant_id, AiUsage::sole()->tenant_id);

        // Fallback: jeder Durchlauf wird zum Preis seines Modells abgerechnet.
        AiUsage::query()->delete();
        AiUsage::record($this->message('claude-opus-5', 300, 50, [
            ['type' => 'message', 'model' => null, 'input_tokens' => 300, 'output_tokens' => 0, 'cache_read_input_tokens' => 0, 'cache_creation_input_tokens' => 0],
            ['type' => 'fallback_message', 'model' => 'claude-opus-5', 'input_tokens' => 300, 'output_tokens' => 50, 'cache_read_input_tokens' => 0, 'cache_creation_input_tokens' => 0],
        ]), 'brand_size', 'claude-sonnet-5-5');
        $this->assertSame(
            [['claude-sonnet-5-5', 600], ['claude-opus-5', 300 * 5 + 50 * 25]],
            AiUsage::query()->orderBy('id')->get()->map(fn ($usage) => [$usage->model, $usage->cost_micros])->all(),
        );

        AiUsage::record($this->message('claude-unknown-9', 10, 10), 'title', 'claude-unknown-9');
        $this->assertNull(AiUsage::query()->latest('id')->first()->cost_micros);
    }

    public function test_profile_shows_remaining_credit_after_usage_since_entry(): void
    {
        $this->actingAs(User::factory()->create());
        $usage = fn (int $micros, string $mode) => AiUsage::create([
            'mode' => $mode, 'model' => 'claude-sonnet-5-5', 'input_tokens' => 1000, 'output_tokens' => 100, 'cost_micros' => $micros,
        ]);

        $this->get(route('profile.edit'))->assertOk()->assertSee('Noch kein Guthaben eingetragen.');

        $usage(5_000_000, 'brand_size');
        $this->travel(1)->minute();
        $this->put(route('profile.ai-credit'), ['amount' => ' 12,50 '])->assertRedirect(route('profile.edit'));
        $this->assertSame(12_500_000, AiCredit::sole()->amount_micros);

        $this->travel(1)->minute();
        $usage(10_000, 'brand_size');
        $usage(2_000, 'title');

        $this->get(route('profile.edit'))
            ->assertSee(['Verbleibend', '12,49 $', '0,01 $', 'Marke/Größe erkennen', 'Titel vorschlagen'])
            ->assertDontSee('Noch kein Guthaben eingetragen.');
    }

    public function test_credit_must_be_a_number(): void
    {
        $this->actingAs(User::factory()->create());

        $this->from(route('profile.edit'))->put(route('profile.ai-credit'), ['amount' => 'viel'])
            ->assertRedirect(route('profile.edit'))->assertSessionHasErrorsIn('aiCredit', 'amount');
        $this->assertSame(0, AiCredit::count());
    }
}

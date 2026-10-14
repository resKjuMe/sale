<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendingFillTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->category = Category::create(['name' => 'Bodys']);
    }

    private function sold(array $extra = []): Article
    {
        return $this->category->articles()->create(['image_path' => 'articles/x.jpg', 'title' => 'Body', 'brand' => 'Zara', 'size' => '74', 'sold' => true] + $extra);
    }

    public function test_list_shows_inline_price_fields_with_existing_amounts(): void
    {
        $article = $this->sold(['price' => 12]);

        $this->get(route('pending.index', 'ohne-preise'))->assertOk()
            ->assertSee([route('pending.fill', ['ohne-preise', $article]), 'name="price"', 'value="12,00"', 'name="sale_price"', 'Beide gleich'], false)
            ->assertDontSee('Abholung');
        $this->get(route('pending.index', 'ohne-versandkosten'))->assertOk()
            ->assertSee(['name="shipping_cost"', 'Abholung'], false)
            ->assertDontSee('name="sale_price"', false);
    }

    public function test_prices_are_saved_with_comma_and_blank_fields_stay_unchanged(): void
    {
        $article = $this->sold(['price' => 12]);

        $this->patchJson(route('pending.fill', ['ohne-preise', $article]), ['price' => '', 'sale_price' => '9,50'])
            ->assertOk()->assertExactJson(['missing' => false, 'summary' => 'Angebot 12,00 € · Gezahlt 9,50 €']);

        $this->assertSame(['12.00', '9.50'], [$article->fresh()->price, $article->fresh()->sale_price]);
        $this->get(route('pending.index', 'ohne-preise'))->assertDontSee(route('pending.fill', ['ohne-preise', $article]));
    }

    public function test_response_reports_when_the_article_still_misses_something(): void
    {
        $article = $this->sold();

        $this->patchJson(route('pending.fill', ['ohne-preise', $article]), ['price' => '10'])
            ->assertOk()->assertJson(['missing' => true]);
        $this->patchJson(route('pending.fill', ['ohne-preis', $article]), ['sale_price' => '0'])
            ->assertOk()->assertJson(['missing' => false]);
    }

    public function test_empty_or_invalid_input_is_rejected(): void
    {
        $article = $this->sold();

        $this->patchJson(route('pending.fill', ['ohne-preis', $article]), ['price' => '', 'sale_price' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors(['price' => 'Bitte einen Betrag eintragen.']);
        $this->patchJson(route('pending.fill', ['ohne-preis', $article]), ['sale_price' => 'abc'])
            ->assertJsonValidationErrors('sale_price');
        // Felder, die die Liste nicht anbietet, werden ignoriert.
        $this->patchJson(route('pending.fill', ['ohne-preis', $article]), ['price' => '5', 'shipping_cost' => '4'])->assertOk();
        $this->assertNull($article->fresh()->shipping_cost);
    }

    public function test_shipping_cost_or_pickup_can_be_set(): void
    {
        $shipped = $this->sold();
        $pickup = $this->sold(['tracking_code' => 'AB12']);

        $this->patchJson(route('pending.fill', ['ohne-versandkosten', $shipped]), ['shipping_cost' => '4,99'])
            ->assertOk()->assertExactJson(['missing' => false, 'summary' => 'Versand 4,99 €']);
        $this->patchJson(route('pending.fill', ['ohne-versandkosten', $this->sold()]), ['shipping_cost' => '0'])
            ->assertOk()->assertExactJson(['missing' => false, 'summary' => 'Versand 0,00 €']);
        $this->patchJson(route('pending.fill', ['ohne-versandkosten', $pickup]), ['shipping_cost' => '', 'pickup' => '1'])
            ->assertOk()->assertExactJson(['missing' => false, 'summary' => 'Selbstabholung']);

        $this->assertSame('4.99', $shipped->fresh()->shipping_cost);
        $this->assertTrue($pickup->fresh()->pickup);
        $this->assertNull($pickup->fresh()->tracking_code);
    }

    public function test_without_javascript_the_form_redirects_back_with_status(): void
    {
        $article = $this->sold();

        $this->from(route('pending.index', 'ohne-preis'))
            ->patch(route('pending.fill', ['ohne-preis', $article]), ['price' => '10', 'sale_price' => '8'])
            ->assertRedirect(route('pending.index', 'ohne-preis'))
            ->assertSessionHas('status', '„Body“: Angebot 10,00 € · Gezahlt 8,00 €.');
    }

    public function test_only_sold_articles_on_fill_lists_can_be_filled(): void
    {
        $available = $this->category->articles()->create(['image_path' => 'articles/x.jpg', 'brand' => 'Zara', 'size' => '74']);
        $sold = $this->sold();

        $this->patchJson(route('pending.fill', ['ohne-preis', $available]), ['price' => '5'])->assertStatus(422);
        $this->patchJson(route('pending.fill', ['zahlung', $sold]), ['price' => '5'])->assertStatus(422);
        $this->patchJson(route('pending.fill', ['unbekannt', $sold]), ['price' => '5'])->assertNotFound();
        $this->assertNull($sold->fresh()->price);
    }

    public function test_fill_lists_show_latest_sales_first(): void
    {
        $this->travelTo(now()->setDateTime(2026, 10, 1, 12, 0));
        $old = $this->sold(['title' => 'Alter Body']);
        $this->travelTo(now()->setDateTime(2026, 10, 12, 12, 0));
        $new = $this->sold(['title' => 'Neuer Body']);

        $this->get(route('pending.index', 'ohne-preis'))->assertOk()
            ->assertSeeTextInOrder(['Neuer Body', 'verkauft 12.10.2026', 'Alter Body', 'verkauft 01.10.2026']);
        $this->get(route('pending.index', 'zahlung'))->assertOk()->assertSeeTextInOrder(['Alter Body', 'Neuer Body']);
    }
}

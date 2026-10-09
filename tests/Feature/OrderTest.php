<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Bundle;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
        $this->category = Category::create(['name' => 'Bodys']);
    }

    private function article(string $title, ?float $price, array $extra = []): Article
    {
        return $this->category->articles()->create(['image_path' => 'articles/x.jpg', 'title' => $title, 'brand' => 'Zara', 'size' => '74', 'price' => $price] + $extra);
    }

    public function test_total_price_is_distributed_proportionally_with_exact_cents(): void
    {
        $articles = collect([$this->article('A', 10), $this->article('B', 20), $this->article('C', 0)]);

        $shares = Bundle::distribute(new \Illuminate\Database\Eloquent\Collection($articles), 25);
        $this->assertSame(25.0, round(array_sum($shares), 2));
        $this->assertSame([8.33, 16.67, 0.0], array_values($shares));

        $equal = Bundle::distribute(new \Illuminate\Database\Eloquent\Collection([$this->article('D', null), $this->article('E', null), $this->article('F', null)]), 10);
        $this->assertSame(10.0, round(array_sum($equal), 2));
    }

    public function test_bundle_with_total_price_spreads_discount_and_mixes_sold_articles(): void
    {
        $a = $this->article('Body A', 10);
        $b = $this->article('Body B', 30, ['sold' => true, 'buyer_name' => 'Erika', 'sale_price' => 30]);

        $response = $this->post(route('articles.batch.update'), [
            'ids' => [$a->id, $b->id], 'action' => 'bundle', 'buyer_name' => 'Erika',
            'price_mode' => 'total', 'total' => '30,00', 'shipping_cost' => '4,99',
        ]);

        $bundle = Bundle::sole();
        $response->assertRedirect(route('orders.show', $bundle))->assertSessionHas('status', 'Bestellung von Erika mit 2 Artikel angelegt.');
        $this->assertSame(['7.50', '22.50'], [$a->fresh()->sale_price, $b->fresh()->sale_price]);
        $this->assertSame(10.0, $bundle->fresh()->discount());

        $this->get(route('orders.show', $bundle))->assertOk()
            ->assertSeeTextInOrder(['Bestellung von Erika', 'Body A', '7,50 €', 'statt 10,00 €', 'Body B', '22,50 €',
                'Angebotspreise zusammen', '40,00 €', 'Gezahlter Preis', '30,00 €', 'Rabatt', '−10,00 € (25 %)', 'Versand', '4,99 €', 'Gesamt', '34,99 €']);
        $this->get(route('dashboard'))
            ->assertSee(route('orders.show', $bundle))
            ->assertSeeTextInOrder(['Ø Rabatt', '25 %', 'Zahlung ausstehend', 'Bestellung', '2 Artikel', '34,99 €', '−10,00 €']);
    }

    public function test_order_page_updates_total_shipping_and_status_for_all_articles(): void
    {
        $a = $this->article('Body A', 10);
        $b = $this->article('Body B', 10);
        $this->post(route('articles.batch.update'), ['ids' => [$a->id, $b->id], 'action' => 'bundle', 'buyer_name' => 'Erika', 'total' => '20']);
        $bundle = Bundle::sole();

        $this->put(route('orders.update', $bundle), [
            'buyer_name' => 'Erika M.', 'buyer_address' => 'Weg 1', 'total' => '15', 'shipping_cost' => '5',
            'paid' => '1', 'shipped' => '1', 'tracking_code' => 'AB 12',
        ])->assertRedirect(route('orders.show', $bundle));

        [$a, $b] = [$a->fresh(), $b->fresh()];
        $this->assertSame(['7.50', '7.50'], [$a->sale_price, $b->sale_price]);
        $this->assertSame(['5.00', '0.00'], [$a->shipping_cost, $b->shipping_cost]);
        $this->assertTrue($a->paid && $b->paid && $a->shipped && $b->shipped);
        $this->assertSame(['AB12', 'AB12'], [$a->tracking_code, $b->tracking_code]);
        $this->assertSame(['Erika M.', 'Erika M.'], [$a->buyer_name, $bundle->fresh()->buyer_name]);
        $this->assertNotNull($b->paid_at);

        $this->put(route('orders.update', $bundle), ['buyer_name' => 'Erika M.', 'pickup' => '1', 'picked_up' => '1', 'shipped' => '1']);
        [$a, $b] = [$a->fresh(), $b->fresh()];
        $this->assertSame(['7.50', '7.50'], [$a->sale_price, $b->sale_price], 'Ohne neuen Gesamtpreis bleiben die Preise.');
        $this->assertTrue($a->pickup && $b->picked_up);
        $this->assertFalse($a->shipped || $b->shipped);
        $this->assertNull($a->shipping_cost);
    }

    public function test_article_can_be_removed_and_order_dissolved(): void
    {
        $a = $this->article('Body A', 10);
        $b = $this->article('Body B', 10);
        $c = $this->article('Body C', 10);
        $this->post(route('articles.batch.update'), ['ids' => [$a->id, $b->id, $c->id], 'action' => 'bundle', 'buyer_name' => 'Erika', 'shipping_cost' => '6']);
        $bundle = Bundle::sole();

        $this->delete(route('orders.articles.remove', [$bundle, $a]))->assertRedirect(route('orders.show', $bundle));
        $this->assertNull($a->fresh()->bundle_id);
        $this->assertTrue($a->fresh()->sold);
        $this->assertSame('6.00', $b->fresh()->shipping_cost, 'Versandkosten wandern zum nächsten Artikel.');

        $this->delete(route('orders.destroy', $bundle))->assertRedirect(route('articles.show', $b));
        $this->assertSame(0, Bundle::count());
        $this->assertTrue($b->fresh()->sold && $c->fresh()->sold);
    }

    public function test_sell_mode_offers_turning_freshly_sold_articles_into_an_order(): void
    {
        $this->article('Body A', 10);

        $this->get(route('categories.show', $this->category))->assertSee(['bundleSoldNow()', 'Als Bestellung'], false);
        $this->get(route('articles.batch.edit', ['ids' => [Article::sole()->id]]))->assertSee('id="bestellung"', false);
    }

    public function test_orders_are_tenant_scoped(): void
    {
        $a = $this->article('Body A', 10);
        $this->post(route('articles.batch.update'), ['ids' => [$a->id], 'action' => 'bundle', 'buyer_name' => 'Erika']);
        $bundle = Bundle::sole();

        $this->actingAs(User::factory()->create());
        $this->get(route('orders.show', $bundle))->assertNotFound();
        $this->put(route('orders.update', $bundle), ['buyer_name' => 'X'])->assertNotFound();
        $this->delete(route('orders.destroy', $bundle))->assertNotFound();
    }
}

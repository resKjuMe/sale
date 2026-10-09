<?php

namespace Tests\Feature;

use App\Enums\ArticleCondition;
use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CategoryArticleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->actingAs(User::factory()->create());
    }

    public function test_guests_are_redirected_to_login(): void
    {
        auth()->logout();

        $this->get(route('categories.index'))->assertRedirect(route('login'));
    }

    public function test_category_can_be_created_updated_and_listed(): void
    {
        $this->post(route('categories.store'), ['name' => 'Jacken'])->assertRedirect();

        $category = Category::sole();
        $this->put(route('categories.update', $category), ['name' => 'Winterjacken', 'description' => 'Warm'])
            ->assertRedirect(route('categories.show', $category));

        $this->get(route('categories.index'))->assertOk()->assertSee('Winterjacken');
        $this->assertSame('Warm', $category->fresh()->description);
    }

    public function test_category_name_must_be_unique(): void
    {
        Category::create(['name' => 'Jacken']);

        $this->post(route('categories.store'), ['name' => 'Jacken'])->assertSessionHasErrors('name');
    }

    public function test_article_with_only_required_fields_can_be_created(): void
    {
        $category = Category::create(['name' => 'Schuhe']);

        $this->post(route('categories.articles.store', $category), [
            'image' => UploadedFile::fake()->image('schuh.jpg'),
            'brand' => 'Nike',
            'size' => '42',
        ])->assertRedirect(route('categories.show', $category));

        $article = $category->articles()->sole();
        $this->assertNull($article->title);
        $this->assertNull($article->condition);
        $this->assertNull($article->price);
        Storage::disk('public')->assertExists($article->image_path);

        $this->get(route('categories.show', $category))->assertOk()->assertSee('Nike');
        $this->get(route('articles.show', $article))->assertOk()->assertSee('42');
    }

    public function test_article_with_all_fields_and_comma_price(): void
    {
        $category = Category::create(['name' => 'Schuhe']);

        $this->post(route('categories.articles.store', $category), [
            'image' => UploadedFile::fake()->image('schuh.jpg'),
            'title' => 'Air Max',
            'brand' => 'Nike',
            'size' => '42',
            'condition' => ArticleCondition::VeryGood->value,
            'price' => '49,90',
        ])->assertSessionHasNoErrors();

        $article = Article::sole();
        $this->assertSame('Air Max', $article->title);
        $this->assertSame(ArticleCondition::VeryGood, $article->condition);
        $this->assertSame('49.90', $article->price);
        $this->get(route('articles.show', $article))->assertSee('49,90 €')->assertSee('Sehr gut');
    }

    public function test_quick_capture_page_suggests_existing_brands_and_sizes(): void
    {
        $category = Category::create(['name' => 'Schuhe']);
        $category->articles()->create(['image_path' => 'articles/x.jpg', 'brand' => 'Nike', 'size' => '42']);

        $this->get(route('categories.show', $category))->assertSee(route('categories.articles.quick', $category));
        $this->get(route('categories.articles.quick', $category))
            ->assertOk()
            ->assertSee('capture="environment"', false)
            ->assertSee('<option value="Nike">', false)
            ->assertSee('<option value="42">', false);
    }

    public function test_quick_capture_save_redirects_back_to_quick_capture(): void
    {
        $category = Category::create(['name' => 'Schuhe']);

        $this->post(route('categories.articles.store', $category), [
            'quick' => '1',
            'image' => UploadedFile::fake()->image('schuh.jpg'),
            'brand' => 'Nike',
            'size' => '42',
        ])->assertRedirect(route('categories.articles.quick', $category))
            ->assertSessionHas('status');

        $this->assertSame(1, $category->articles()->count());
    }

    public function test_print_view_lists_all_articles_with_title_brand_and_size(): void
    {
        $category = Category::create(['name' => 'Schuhe']);
        foreach (range(1, 30) as $i) {
            $category->articles()->create(['image_path' => "articles/$i.jpg", 'title' => "Titel $i", 'brand' => "Marke $i", 'size' => "Größe $i"]);
        }

        $this->get(route('categories.show', $category))->assertSee(route('categories.print', $category));
        $this->get(route('categories.print', [$category, 'cols' => 9]))
            ->assertOk()
            ->assertSee(['Titel 30', 'Marke 30', 'Gr. Größe 30'])
            ->assertSee('repeat(4,', false);
    }

    public function test_bulk_page_is_linked_and_renders(): void
    {
        $category = Category::create(['name' => 'Schuhe']);

        $this->get(route('categories.show', $category))->assertSee(route('categories.articles.bulk', $category));
        $this->get(route('categories.articles.bulk', $category))
            ->assertOk()
            ->assertSee('multiple', false)
            ->assertSee('bulkUpload(', false);
    }

    public function test_json_store_returns_created_and_json_validation_errors(): void
    {
        $category = Category::create(['name' => 'Schuhe']);

        $this->postJson(route('categories.articles.store', $category), [
            'image' => UploadedFile::fake()->image('a.jpg'),
            'brand' => 'Nike',
            'size' => '42',
            'title' => '',
            'condition' => '',
            'price' => '',
        ])->assertCreated()->assertJsonStructure(['id']);

        $this->postJson(route('categories.articles.store', $category), [
            'image' => UploadedFile::fake()->image('b.jpg'),
            'brand' => '',
            'size' => '42',
        ])->assertUnprocessable()->assertJsonValidationErrors('brand');

        $this->assertSame(1, $category->articles()->count());
    }

    public function test_article_can_be_marked_as_sold_and_is_marked_in_lists(): void
    {
        $category = Category::create(['name' => 'Schuhe']);
        $article = $category->articles()->create(['image_path' => 'articles/x.jpg', 'brand' => 'Nike', 'size' => '42']);
        $this->assertFalse($article->fresh()->sold);

        $this->put(route('articles.update', $article), [
            'brand' => 'Nike',
            'size' => '42',
            'sold' => '1',
            'buyer_name' => 'Erika Muster',
            'buyer_address' => "Hauptstr. 1\n12345 Musterstadt",
            'paid' => '0',
            'sale_price' => '35,50',
        ])->assertSessionHasNoErrors();

        $article->refresh();
        $this->assertTrue($article->sold);
        $this->assertFalse($article->paid);
        $this->assertSame('35.50', $article->sale_price);
        $this->assertSame('Erika Muster', $article->buyer_name);

        $this->get(route('categories.show', $category))->assertSee(['Verkauft', 'Offen', 'an Erika Muster', '35,50 €']);
        $this->get(route('categories.index'))->assertSee('1 verkauft');
        $this->get(route('articles.show', $article))->assertSee(['Erika Muster', 'Musterstadt', '35,50 €']);
    }

    public function test_unmarking_sold_clears_sale_data(): void
    {
        $category = Category::create(['name' => 'Schuhe']);
        $article = $category->articles()->create([
            'image_path' => 'articles/x.jpg', 'brand' => 'Nike', 'size' => '42',
            'sold' => true, 'buyer_name' => 'Erika', 'paid' => true, 'sale_price' => 10,
        ]);

        $this->put(route('articles.update', $article), ['brand' => 'Nike', 'size' => '42', 'sold' => '0', 'paid' => '1', 'buyer_name' => 'Erika'])
            ->assertSessionHasNoErrors();

        $article->refresh();
        $this->assertFalse($article->sold);
        $this->assertFalse($article->paid);
        $this->assertNull($article->buyer_name);
        $this->assertNull($article->sale_price);
        $this->get(route('categories.show', $category))->assertDontSee('&& ! changed[', false);
    }

    public function test_print_view_strikes_through_sold_articles(): void
    {
        $category = Category::create(['name' => 'Schuhe']);
        $category->articles()->create(['image_path' => 'articles/a.jpg', 'brand' => 'Verfuegbar', 'size' => 'M']);
        $category->articles()->create(['image_path' => 'articles/b.jpg', 'brand' => 'Weg', 'size' => 'L', 'sold' => true]);

        $html = $this->get(route('categories.print', $category))->assertOk()->assertSee(['Verfuegbar', 'Weg'])->getContent();

        $this->assertSame(1, substr_count($html, 'line-through'));
        $this->assertSame(2, substr_count($html, '<line '));
    }

    public function test_public_page_is_reachable_without_login_and_hides_buyer_data(): void
    {
        $category = Category::create(['name' => 'Schuhe']);
        $category->articles()->create(['image_path' => 'articles/a.jpg', 'brand' => 'Nike', 'size' => '42', 'price' => 20]);
        $category->articles()->create([
            'image_path' => 'articles/b.jpg', 'brand' => 'Adidas', 'size' => '43',
            'sold' => true, 'buyer_name' => 'Geheim Käufer', 'buyer_address' => 'Geheimweg 1', 'paid' => true, 'sale_price' => 99,
        ]);
        $this->assertSame(32, strlen($category->public_token));

        $this->get(route('categories.show', $category))->assertSee($category->publicUrl());

        auth()->logout();
        $this->get($category->publicUrl())
            ->assertOk()
            ->assertSee(['Schuhe', 'Nike', 'Adidas', 'Verkauft', '20,00 €', 'noindex'])
            ->assertDontSee(['Geheim Käufer', 'Geheimweg', '99,00', 'Bezahlt', 'Abmelden', 'Kategorien']);

        $this->get('/p/'.str_repeat('x', 32))->assertNotFound();
    }

    public function test_regenerating_public_link_invalidates_old_one(): void
    {
        $category = Category::create(['name' => 'Schuhe']);
        $oldUrl = $category->publicUrl();

        $this->post(route('categories.public-link', $category))->assertRedirect(route('categories.show', $category));

        $this->assertNotSame($oldUrl, $category->fresh()->publicUrl());
        $this->get($oldUrl)->assertNotFound();
        $this->get($category->fresh()->publicUrl())->assertOk();
    }

    public function test_sold_article_can_be_marked_as_shipped_with_tracking_code(): void
    {
        $category = Category::create(['name' => 'Schuhe']);
        $article = $category->articles()->create(['image_path' => 'articles/x.jpg', 'brand' => 'Nike', 'size' => '42', 'sold' => true]);

        $this->put(route('articles.update', $article), [
            'brand' => 'Nike', 'size' => '42', 'sold' => '1', 'shipped' => '1', 'tracking_code' => ' 0034 0434 1234 ',
        ])->assertSessionHasNoErrors();

        $article->refresh();
        $this->assertTrue($article->shipped);
        $this->assertSame('003404341234', $article->tracking_code);
        $this->get(route('articles.show', $article))->assertSee('piececode=003404341234', false);
        $this->get(route('categories.show', $category))->assertSee('Versendet');

        $this->put(route('articles.update', $article), ['brand' => 'Nike', 'size' => '42', 'sold' => '1', 'shipped' => '1', 'tracking_code' => ''])
            ->assertSessionHasNoErrors();
        $this->assertNull($article->fresh()->tracking_code);

        $this->put(route('articles.update', $article), ['brand' => 'Nike', 'size' => '42', 'sold' => '1', 'shipped' => '1', 'tracking_code' => 'abc-<x>'])
            ->assertSessionHasErrors('tracking_code');
    }

    public function test_unshipping_or_unselling_clears_tracking_code(): void
    {
        $category = Category::create(['name' => 'Schuhe']);
        $article = $category->articles()->create([
            'image_path' => 'articles/x.jpg', 'brand' => 'Nike', 'size' => '42', 'sold' => true, 'shipped' => true, 'tracking_code' => 'ABC123',
        ]);

        $this->put(route('articles.update', $article), ['brand' => 'Nike', 'size' => '42', 'sold' => '1', 'shipped' => '0', 'tracking_code' => 'ABC123']);
        $this->assertNull($article->fresh()->tracking_code);

        $article->update(['shipped' => true, 'tracking_code' => 'ABC123']);
        $this->put(route('articles.update', $article), ['brand' => 'Nike', 'size' => '42', 'sold' => '0', 'shipped' => '1', 'tracking_code' => 'ABC123']);
        $this->assertFalse($article->fresh()->shipped);
        $this->assertNull($article->fresh()->tracking_code);
    }

    public function test_vinted_url_is_saved_and_marked_in_lists(): void
    {
        $category = Category::create(['name' => 'Schuhe']);
        $article = $category->articles()->create(['image_path' => 'articles/x.jpg', 'brand' => 'Nike', 'size' => '42']);
        $url = 'https://www.vinted.de/items/1234567-nike-schuhe';

        $this->get(route('categories.show', $category))->assertDontSee('>Vinted<', false);
        $this->get(route('categories.articles.create', $category))->assertDontSee('vinted_url');
        $this->get(route('articles.edit', $article))->assertSee('vinted_url');

        $this->put(route('articles.update', $article), ['brand' => 'Nike', 'size' => '42', 'vinted_url' => $url])
            ->assertSessionHasNoErrors();

        $this->assertSame($url, $article->fresh()->vinted_url);
        $this->get(route('categories.show', $category))->assertSee('>Vinted<', false);
        $this->get(route('articles.show', $article))->assertSee($url, false);
        $this->get($category->publicUrl())->assertSee($url, false)->assertSee('Auf Vinted ansehen');
    }

    public function test_vinted_url_must_point_to_vinted(): void
    {
        $category = Category::create(['name' => 'Schuhe']);
        $article = $category->articles()->create(['image_path' => 'articles/x.jpg', 'brand' => 'Nike', 'size' => '42']);

        foreach (['https://example.com/items/1', 'https://vinted.de.evil.com/items/1', 'http://www.vinted.de/items/1', 'javascript:alert(1)'] as $url) {
            $this->put(route('articles.update', $article), ['brand' => 'Nike', 'size' => '42', 'vinted_url' => $url])
                ->assertSessionHasErrors('vinted_url');
        }

        foreach (['https://vinted.de/items/1', 'https://www.vinted.co.uk/items/1', 'https://www.vinted.com/items/1'] as $url) {
            $this->put(route('articles.update', $article), ['brand' => 'Nike', 'size' => '42', 'vinted_url' => $url])
                ->assertSessionHasNoErrors();
        }
    }

    public function test_quick_sold_toggle_via_json_and_form(): void
    {
        $category = Category::create(['name' => 'Schuhe']);
        $article = $category->articles()->create(['image_path' => 'articles/x.jpg', 'brand' => 'Nike', 'size' => '42']);

        $this->get(route('categories.show', $category))->assertSee(['sellMode(', 'Verkauft markieren']);

        $this->patchJson(route('articles.sold', $article), ['sold' => true])->assertOk()->assertJson(['sold' => true]);
        $this->assertTrue($article->fresh()->sold);

        $article->update(['buyer_name' => 'Erika', 'paid' => true, 'shipped' => true, 'tracking_code' => 'ABC1']);
        $this->get(route('articles.show', $article))->assertSee('Wieder als verfügbar markieren');

        $this->from(route('articles.show', $article))
            ->patch(route('articles.sold', $article), ['sold' => '0'])
            ->assertRedirect(route('articles.show', $article));

        $article->refresh();
        $this->assertFalse($article->sold);
        $this->assertFalse($article->paid);
        $this->assertFalse($article->shipped);
        $this->assertNull($article->buyer_name);
        $this->assertNull($article->tracking_code);
        $this->get(route('articles.show', $article))->assertSee('Als verkauft markieren');

        $this->patchJson(route('articles.sold', $article), [])->assertUnprocessable();
    }

    public function test_image_brand_and_size_are_required(): void
    {
        $category = Category::create(['name' => 'Schuhe']);

        $this->post(route('categories.articles.store', $category), [])
            ->assertSessionHasErrors(['image', 'brand', 'size']);
    }

    public function test_updating_without_image_keeps_it_and_new_image_replaces_old_file(): void
    {
        $category = Category::create(['name' => 'Schuhe']);
        $this->post(route('categories.articles.store', $category), [
            'image' => UploadedFile::fake()->image('a.jpg'),
            'brand' => 'Nike',
            'size' => '42',
        ]);
        $article = Article::sole();
        $oldPath = $article->image_path;

        $this->put(route('articles.update', $article), ['brand' => 'Adidas', 'size' => '43'])
            ->assertSessionHasNoErrors();
        $this->assertSame($oldPath, $article->fresh()->image_path);

        $this->put(route('articles.update', $article), [
            'image' => UploadedFile::fake()->image('b.jpg'),
            'brand' => 'Adidas',
            'size' => '43',
        ])->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($article->fresh()->image_path);
    }

    public function test_deleting_category_removes_articles_and_images(): void
    {
        $category = Category::create(['name' => 'Schuhe']);
        $this->post(route('categories.articles.store', $category), [
            'image' => UploadedFile::fake()->image('a.jpg'),
            'brand' => 'Nike',
            'size' => '42',
        ]);
        $path = Article::sole()->image_path;

        $this->delete(route('categories.destroy', $category))->assertRedirect(route('categories.index'));

        $this->assertSame(0, Article::count());
        Storage::disk('public')->assertMissing($path);
    }

    public function test_collage_page_provides_brand_size_and_relative_image_of_every_article(): void
    {
        $category = Category::create(['name' => 'Bodys']);
        $this->post(route('categories.articles.store', $category), [
            'image' => UploadedFile::fake()->image('a.jpg'),
            'brand' => 'Petit Bateau',
            'size' => '6-12 Monate',
        ]);
        $article = Article::sole();
        $article->markSold(true);

        $this->get(route('categories.show', $category))->assertSee(route('categories.collage', $category));
        $response = $this->get(route('categories.collage', $category))->assertOk();

        $articles = $response->viewData('articles');
        $this->assertSame([[
            'id' => $article->id,
            'brand' => 'Petit Bateau',
            'size' => '6-12 Monate',
            'sold' => true,
            'image' => '/storage/'.$article->image_path,
        ]], $articles->all());
    }

    public function test_category_and_public_page_filter_by_size_and_brand_and_hide_sold(): void
    {
        $category = Category::create(['name' => 'Bodys']);
        $category->articles()->createMany([
            ['image_path' => 'articles/a.jpg', 'title' => 'Body Alpha', 'brand' => 'Zara', 'size' => '68 / 72'],
            ['image_path' => 'articles/b.jpg', 'title' => 'Body Beta', 'brand' => 'H&M', 'size' => '6-12 Monate'],
            ['image_path' => 'articles/c.jpg', 'title' => 'Body Gamma', 'brand' => 'Zara', 'size' => '74', 'sold' => true],
        ]);

        foreach ([route('categories.show', $category), $category->publicUrl()] as $url) {
            $this->get($url)->assertOk()->assertSee(['Body Alpha', 'Body Beta', 'Body Gamma', 'Größe', 'Marke', 'Verkaufte ausblenden']);
            $this->get($url.'?'.http_build_query(['size' => ['68 / 72', '6-12 Monate']]))
                ->assertSee(['Body Alpha', 'Body Beta'])->assertDontSee('Body Gamma');
            $this->get($url.'?brand[]=Zara')->assertSee(['Body Alpha', 'Body Gamma'])->assertDontSee('Body Beta');
            $this->get($url.'?'.http_build_query(['brand' => ['Zara', 'H&M']]))->assertSee(['Body Alpha', 'Body Beta', 'Body Gamma']);
            $this->get($url.'?hide_sold=1')->assertSee(['Body Alpha', 'Body Beta'])->assertDontSee('Body Gamma');
            $this->get($url.'?hide_sold=1&brand[]=Zara')->assertSee('Body Alpha')->assertDontSee(['Body Beta', 'Body Gamma']);
            $this->get($url.'?brand=Zara')->assertSee('Body Alpha')->assertDontSee('Body Beta');
            $this->get($url.'?brand[]=Nobody')->assertSee('Keine Artikel passen zum Filter.');
        }
    }

    public function test_collage_page_applies_size_and_brand_filter_but_keeps_sold_articles(): void
    {
        $category = Category::create(['name' => 'Bodys']);
        $category->articles()->createMany([
            ['image_path' => 'articles/a.jpg', 'brand' => 'Zara', 'size' => '68 / 72'],
            ['image_path' => 'articles/b.jpg', 'brand' => 'H&M', 'size' => '68 / 72', 'sold' => true],
            ['image_path' => 'articles/c.jpg', 'brand' => 'Zara', 'size' => '74'],
        ]);

        $this->get(route('categories.show', [$category, 'size' => ['68 / 72']]))
            ->assertSee(route('categories.collage', [$category, 'size' => ['68 / 72']]), false);

        $articles = $this->get(route('categories.collage', [$category, 'size' => ['68 / 72'], 'hide_sold' => 1]))
            ->assertOk()->viewData('articles');

        $this->assertSame(['Zara', 'H&M'], $articles->pluck('brand')->all());
    }

    public function test_overview_lists_articles_of_all_categories_with_category_filter(): void
    {
        $bodys = Category::create(['name' => 'Bodys']);
        $hosen = Category::create(['name' => 'Hosen']);
        $bodys->articles()->create(['image_path' => 'articles/a.jpg', 'title' => 'Body Alpha', 'brand' => 'Zara', 'size' => '68 / 72']);
        $hosen->articles()->createMany([
            ['image_path' => 'articles/b.jpg', 'title' => 'Hose Beta', 'brand' => 'H&M', 'size' => '74'],
            ['image_path' => 'articles/c.jpg', 'title' => 'Hose Gamma', 'brand' => 'Zara', 'size' => '74', 'sold' => true],
        ]);

        $this->get(route('categories.index'))->assertSee(route('articles.index'));
        $this->get(route('articles.index'))->assertOk()
            ->assertSee(['Body Alpha', 'Hose Beta', 'Hose Gamma', 'Kategorie', 'Bodys', 'Hosen', '3 Artikel']);

        $this->get(route('articles.index', ['category' => [$hosen->id]]))
            ->assertSee(['Hose Beta', 'Hose Gamma', '2 von 3 Artikeln'])->assertDontSee('Body Alpha');
        $this->get(route('articles.index', ['category' => [$hosen->id], 'brand' => ['Zara'], 'hide_sold' => 1]))
            ->assertSee('Keine Artikel passen zum Filter.');
        $this->get(route('articles.index', ['size' => ['74'], 'hide_sold' => 1]))
            ->assertSee('Hose Beta')->assertDontSee(['Body Alpha', 'Hose Gamma']);
    }

    public function test_status_timestamps_are_set_and_cleared_with_their_flags(): void
    {
        $this->travelTo(now()->setDateTime(2026, 10, 9, 12, 0));
        $article = Category::create(['name' => 'Bodys'])->articles()->create(['image_path' => 'articles/a.jpg', 'brand' => 'Zara', 'size' => '74']);
        $this->assertNull($article->sold_at);

        $article->markSold(true);
        $this->travel(2)->hours();
        $article->update(['paid' => true, 'shipped' => true]);
        $this->travel(1)->hours();
        $article->update(['buyer_name' => 'Erika']);

        $article->refresh();
        $this->assertSame('2026-10-09 12:00', $article->sold_at->format('Y-m-d H:i'));
        $this->assertSame('2026-10-09 14:00', $article->paid_at->format('Y-m-d H:i'));
        $this->assertSame('09.10.2026, 16:00', $article->statusDate('shipped'));

        $article->markSold(false);
        $article->refresh();
        $this->assertNull($article->sold_at);
        $this->assertNull($article->paid_at);
        $this->assertNull($article->shipped_at);
    }

    public function test_pending_payment_and_shipping_filter_is_internal_and_sorted_by_sale_date(): void
    {
        $category = Category::create(['name' => 'Bodys']);
        $this->travelTo(now()->setDateTime(2026, 10, 1, 12, 0));
        $newer = $category->articles()->create(['image_path' => 'articles/a.jpg', 'title' => 'Neu offen', 'brand' => 'Zara', 'size' => '74']);
        $older = $category->articles()->create(['image_path' => 'articles/b.jpg', 'title' => 'Alt offen', 'brand' => 'Zara', 'size' => '74']);
        $older->markSold(true);
        $this->travel(1)->days();
        $newer->markSold(true);
        $category->articles()->create(['image_path' => 'articles/c.jpg', 'title' => 'Bezahlt nicht versendet', 'brand' => 'Zara', 'size' => '74', 'sold' => true, 'paid' => true]);
        $category->articles()->create(['image_path' => 'articles/d.jpg', 'title' => 'Erledigt', 'brand' => 'Zara', 'size' => '74', 'sold' => true, 'paid' => true, 'shipped' => true]);

        $this->get(route('articles.index'))->assertSeeInOrder(['Zahlung ausstehend', '2', 'Versand ausstehend', '3']);
        $this->get(route('articles.index', ['pending' => ['payment']]))
            ->assertSeeInOrder(['Alt offen', 'Neu offen'])->assertDontSee(['Bezahlt nicht versendet', 'Erledigt']);
        $this->get(route('categories.show', [$category, 'pending' => ['shipping']]))
            ->assertSee(['Alt offen', 'Neu offen', 'Bezahlt nicht versendet'])->assertDontSee('Erledigt');
        $this->get($category->publicUrl().'?pending[]=payment')
            ->assertSee(['Erledigt', 'Bezahlt nicht versendet'])->assertDontSee('Zahlung ausstehend');
    }

    public function test_filter_counts_follow_other_selected_filters_and_hide_options_without_hits(): void
    {
        $category = Category::create(['name' => 'Bodys']);
        $category->articles()->createMany([
            ['image_path' => 'articles/a.jpg', 'brand' => 'Zara', 'size' => '68 / 72'],
            ['image_path' => 'articles/b.jpg', 'brand' => 'Zara', 'size' => '74'],
            ['image_path' => 'articles/c.jpg', 'brand' => 'H&M', 'size' => '74'],
            ['image_path' => 'articles/d.jpg', 'brand' => 'Steiff', 'size' => '80'],
        ]);
        $pills = fn (string $html, string $name) => collect(preg_match_all('#name="'.preg_quote($name).'\[\]" value="([^"]+)".*?>(\d+)</span></span>#s', $html, $m) ? array_combine($m[1], $m[2]) : [])->all();

        $html = $this->get($category->publicUrl())->getContent();
        $this->assertSame(['68 / 72' => '1', '74' => '2', '80' => '1'], $pills($html, 'size'));
        $this->assertSame(['H&amp;M' => '1', 'Steiff' => '1', 'Zara' => '2'], $pills($html, 'brand'));

        $html = $this->get($category->publicUrl().'?brand[]=Zara')->getContent();
        $this->assertSame(['68 / 72' => '1', '74' => '1'], $pills($html, 'size'));
        $this->assertSame(['H&amp;M' => '1', 'Steiff' => '1', 'Zara' => '2'], $pills($html, 'brand'));

        $html = $this->get($category->publicUrl().'?size[]=74&brand[]=Steiff')->getContent();
        $this->assertSame(['74' => '0', '80' => '1'], $pills($html, 'size'));
    }

    public function test_overview_has_public_link_showing_all_articles_with_category_filter(): void
    {
        $bodys = Category::create(['name' => 'Bodys']);
        $hosen = Category::create(['name' => 'Hosen']);
        $bodys->articles()->create(['image_path' => 'articles/a.jpg', 'title' => 'Body Alpha', 'brand' => 'Zara', 'size' => '74']);
        $hosen->articles()->create(['image_path' => 'articles/b.jpg', 'title' => 'Hose Beta', 'brand' => 'H&M', 'size' => '74', 'sold' => true]);

        $user = auth()->user();
        $this->get(route('articles.index'))->assertOk();
        $url = $user->fresh()->overviewUrl();
        $this->get(route('articles.index'))->assertSee($url);

        auth()->logout();
        $this->get($url)->assertOk()->assertSee(['Body Alpha', 'Hose Beta', 'Bodys', 'Hosen', 'Kategorie'])->assertDontSee('Zahlung ausstehend');
        $this->get($url.'?category[]='.$bodys->id)->assertSee('Body Alpha')->assertDontSee('Hose Beta');
        $this->get(route('public.overview', 'falsch'))->assertNotFound();

        $this->actingAs($user)->post(route('articles.public-link'))->assertRedirect(route('articles.index'));
        $this->get($url)->assertNotFound();
    }

    public function test_dashboard_shows_stats_pending_lists_and_categories(): void
    {
        $this->travelTo(now()->setDateTime(2026, 10, 5, 12, 0));
        $bodys = Category::create(['name' => 'Bodys']);
        $hosen = Category::create(['name' => 'Hosen']);
        $bodys->articles()->create(['image_path' => 'articles/a.jpg', 'title' => 'Body frei', 'brand' => 'Zara', 'size' => '74', 'price' => 5]);
        $this->travel(-10)->days();
        $unpaid = $bodys->articles()->create(['image_path' => 'articles/b.jpg', 'title' => 'Body unbezahlt', 'brand' => 'Zara', 'size' => '74', 'price' => 8, 'sold' => true, 'buyer_name' => 'Erika']);
        $this->travel(8)->days();
        $hosen->articles()->create(['image_path' => 'articles/c.jpg', 'title' => 'Hose bezahlt', 'brand' => 'H&M', 'size' => '80', 'sold' => true, 'paid' => true, 'sale_price' => 12.5]);
        $this->travel(2)->days();

        $this->get(route('dashboard'))->assertOk()
            ->assertSeeInOrder(['Verfügbar', '1', 'von 3 Artikeln'])
            ->assertSeeInOrder(['Verkauft', '2', '1 in diesem Monat'])
            ->assertSeeInOrder(['Umsatz', '20,50 €'])
            ->assertSeeInOrder(['Noch offen', '8,00 €', '1 Zahlung ausstehend'])
            ->assertSeeInOrder(['Zahlung ausstehend', 'Body unbezahlt', 'an Erika', 'seit 10 Tagen'])
            ->assertSeeInOrder(['Versand ausstehend', 'Body unbezahlt', 'Hose bezahlt', 'seit 2 Tagen'])
            ->assertSeeInOrder(['Zuletzt verkauft', 'Hose bezahlt', 'Body unbezahlt'])
            ->assertSeeInOrder(['Kategorien', 'Bodys', '1 von 2 verfügbar', 'Hosen', '0 von 1 verfügbar'])
            ->assertDontSee('Body frei');
        $this->assertSame('2026-09-25', $unpaid->fresh()->sold_at->format('Y-m-d'));
    }

    public function test_shipping_cost_is_saved_shown_and_reset_when_unsold(): void
    {
        $article = Category::create(['name' => 'Bodys'])->articles()->create(['image_path' => 'articles/a.jpg', 'brand' => 'Zara', 'size' => '74']);

        $this->put(route('articles.update', $article), ['brand' => 'Zara', 'size' => '74', 'sold' => '1', 'sale_price' => '8', 'shipping_cost' => '4,99'])
            ->assertSessionHasNoErrors();
        $this->assertSame('4.99', $article->fresh()->shipping_cost);
        $this->assertSame(12.99, $article->fresh()->amountDue());
        $this->get(route('articles.show', $article))->assertSeeInOrder(['Versandkosten', '4,99 €']);
        $this->get(route('articles.edit', $article))->assertSee('value="4,99"', false);

        $this->put(route('articles.update', $article), ['brand' => 'Zara', 'size' => '74', 'shipping_cost' => '-1'])->assertSessionHasErrors('shipping_cost');

        $this->put(route('articles.update', $article), ['brand' => 'Zara', 'size' => '74', 'sold' => '0']);
        $this->assertNull($article->fresh()->shipping_cost);
    }

    public function test_dashboard_shows_average_discount_and_open_amount_with_shipping(): void
    {
        $category = Category::create(['name' => 'Bodys']);
        $category->articles()->createMany([
            ['image_path' => 'articles/a.jpg', 'brand' => 'Zara', 'size' => '74', 'price' => 10, 'sale_price' => 8, 'shipping_cost' => 4.5, 'sold' => true],
            ['image_path' => 'articles/b.jpg', 'brand' => 'Zara', 'size' => '74', 'price' => 20, 'sale_price' => 12, 'sold' => true, 'paid' => true],
            ['image_path' => 'articles/c.jpg', 'brand' => 'Zara', 'size' => '74', 'sale_price' => 5, 'sold' => true, 'paid' => true],
        ]);

        $this->get(route('dashboard'))->assertOk()
            ->assertSeeInOrder(['Ø Rabatt', '30 %', 'Ø 5,00 € bei 2 Verkäufen'])
            ->assertSeeInOrder(['Noch offen', '12,50 €'])
            ->assertSeeInOrder(['Zahlung ausstehend', '12,50 €']);
    }

    public function test_dashboard_ignores_zero_euro_amounts(): void
    {
        $category = Category::create(['name' => 'Bodys']);
        $category->articles()->createMany([
            ['image_path' => 'articles/a.jpg', 'title' => 'Null verkauft', 'brand' => 'Zara', 'size' => '74', 'price' => 10, 'sale_price' => 0, 'sold' => true],
            ['image_path' => 'articles/b.jpg', 'title' => 'Ohne Preise', 'brand' => 'Zara', 'size' => '74', 'price' => 0, 'sale_price' => 0, 'shipping_cost' => 0, 'sold' => true],
            ['image_path' => 'articles/c.jpg', 'title' => 'Normal', 'brand' => 'Zara', 'size' => '74', 'price' => 20, 'sale_price' => 15, 'sold' => true, 'paid' => true],
        ]);

        $html = $this->get(route('dashboard'))->assertOk()
            ->assertSeeInOrder(['Umsatz', '25,00 €'])
            ->assertSeeInOrder(['Ø Rabatt', '25 %', 'Ø 5,00 € bei 1 Verkäufen'])
            ->assertSeeInOrder(['Noch offen', '10,00 €'])
            ->assertSeeInOrder(['Zahlung ausstehend', 'Null verkauft', '10,00 €', 'Ohne Preise', '–'])
            ->getContent();
        $this->assertDoesNotMatchRegularExpression('/(?<![\d.])0,00 €/u', $html);
    }

    public function test_price_difference_and_dashboard_breakdown_are_internal_only(): void
    {
        $category = Category::create(['name' => 'Bodys']);
        $category->articles()->createMany([
            ['image_path' => 'articles/a.jpg', 'title' => 'Mit Rabatt', 'brand' => 'Zara', 'size' => '74', 'price' => 10, 'sale_price' => 8, 'shipping_cost' => 4.5, 'sold' => true],
            ['image_path' => 'articles/b.jpg', 'title' => 'Teurer', 'brand' => 'Zara', 'size' => '74', 'price' => 10, 'sale_price' => 11, 'sold' => true, 'paid' => true],
            ['image_path' => 'articles/c.jpg', 'title' => 'Gleich', 'brand' => 'Zara', 'size' => '74', 'price' => 6, 'sale_price' => 6, 'sold' => true, 'paid' => true, 'shipped' => true],
        ]);

        $this->get(route('categories.show', $category))->assertSee(['−2,00 €', '+1,00 €'])->assertDontSee('±');
        $this->get(route('articles.index'))->assertSee(['−2,00 €', '+1,00 €']);
        $this->get($category->publicUrl())->assertDontSee(['−2,00 €', '+1,00 €']);

        $this->get(route('dashboard'))
            ->assertSeeInOrder(['Zahlung ausstehend', 'Mit Rabatt', '12,50 €', 'VK 8,00 € + 4,50 € Versand', '−2,00 €'])
            ->assertSeeInOrder(['Versand ausstehend', 'Mit Rabatt', '12,50 €', 'Teurer', '11,00 €', 'VK 11,00 €, ohne Versand', '+1,00 €'])
            ->assertSeeInOrder(['Zuletzt verkauft', 'Gleich', '6,00 €', 'VK 6,00 €, ohne Versand']);
    }
}

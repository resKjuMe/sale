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
}

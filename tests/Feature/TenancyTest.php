<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ArticleAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TenancyTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    private Category $aliceCategory;

    private Article $aliceArticle;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->alice = User::factory()->create();
        $this->bob = User::factory()->create();

        $this->actingAs($this->alice);
        $this->aliceCategory = Category::create(['name' => 'Bodys']);
        $this->aliceArticle = $this->aliceCategory->articles()->create(['image_path' => 'articles/a.jpg', 'title' => 'Alices Body', 'brand' => 'Zara', 'size' => '74', 'sold' => true]);
    }

    public function test_new_records_belong_to_the_tenant_of_the_logged_in_user(): void
    {
        $this->assertNotSame($this->alice->tenant_id, $this->bob->tenant_id);
        $this->assertSame($this->alice->tenant_id, $this->aliceCategory->tenant_id);
        $this->assertSame($this->alice->tenant_id, $this->aliceArticle->tenant_id);
    }

    public function test_other_tenants_see_none_of_the_data(): void
    {
        $this->actingAs($this->bob);

        $this->get(route('categories.index'))->assertOk()->assertDontSee('Bodys');
        $this->get(route('articles.index'))->assertOk()->assertDontSee('Alices Body');
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Alices Body');
        $this->get(route('pending.index', 'zahlung'))->assertOk()->assertDontSee('Alices Body');

        $this->get(route('categories.show', $this->aliceCategory))->assertNotFound();
        $this->get(route('categories.collage', $this->aliceCategory))->assertNotFound();
        $this->get(route('articles.show', $this->aliceArticle))->assertNotFound();
        $this->get(route('articles.edit', $this->aliceArticle))->assertNotFound();
        $this->put(route('articles.update', $this->aliceArticle), ['brand' => 'X', 'size' => '1'])->assertNotFound();
        $this->delete(route('articles.destroy', $this->aliceArticle))->assertNotFound();
        $this->patch(route('articles.mark', [$this->aliceArticle, 'paid']))->assertNotFound();
        $this->app->instance(ArticleAssistant::class, new ArticleAssistant('test-key', 'claude-opus-5-5'));
        $this->postJson(route('articles.ai-suggest'), ['mode' => 'title', 'article_id' => $this->aliceArticle->id])->assertNotFound();

        $this->post(route('articles.batch.update'), ['ids' => [$this->aliceArticle->id], 'action' => 'price', 'mode' => 'set', 'value' => '1'])
            ->assertSessionHas('status', 'Preis bei 0 Artikel geändert.');
        $this->assertNull($this->aliceArticle->fresh()->price);
        $this->assertFalse($this->aliceArticle->fresh()->paid);
    }

    public function test_category_names_are_unique_per_tenant_only(): void
    {
        $this->actingAs($this->bob);

        $this->post(route('categories.store'), ['name' => 'Bodys'])->assertSessionHasNoErrors();
        $this->post(route('categories.store'), ['name' => 'Bodys'])->assertSessionHasErrors('name');
        $this->assertSame(2, Category::withoutGlobalScope('tenant')->where('name', 'Bodys')->count());
    }

    public function test_public_links_show_the_link_owners_data_regardless_of_who_is_logged_in(): void
    {
        $overview = $this->alice->overviewUrl();

        foreach ([null, $this->bob, $this->alice] as $viewer) {
            $viewer ? $this->actingAs($viewer) : auth()->logout();

            $this->get($this->aliceCategory->publicUrl())->assertOk()->assertSee(['Bodys', 'Alices Body']);
            $this->get($overview)->assertOk()->assertSee(['Alices Body', 'Bodys']);
        }

        $this->actingAs($this->bob);
        Category::create(['name' => 'Bobs Hosen'])->articles()->create(['image_path' => 'articles/b.jpg', 'title' => 'Bobs Hose', 'brand' => 'H&M', 'size' => '80']);
        $this->get($overview)->assertDontSee(['Bobs Hose', 'Bobs Hosen']);
        $this->get($this->bob->overviewUrl())->assertSee('Bobs Hose')->assertDontSee('Alices Body');
    }

    public function test_registration_creates_an_own_tenant(): void
    {
        auth()->logout();

        $this->post('/register', [
            'name' => 'Carla',
            'email' => 'carla@example.org',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $carla = User::firstWhere('email', 'carla@example.org');
        $this->assertSame('Carla', $carla->tenant->name);
        $this->assertSame(3, Tenant::count());
        $this->get(route('articles.index'))->assertOk()->assertDontSee('Alices Body');
    }
}

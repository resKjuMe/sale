<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PageView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PageViewTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';

    public function test_public_views_are_counted_anonymously_and_shown_on_the_dashboard(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user);
        $bodys = Category::create(['name' => 'Bodys']);
        $overview = $user->overviewUrl();
        auth()->logout();

        $this->withHeader('User-Agent', self::BROWSER)->get($bodys->publicUrl())->assertOk();
        $this->withHeader('User-Agent', self::BROWSER)->get($bodys->publicUrl())->assertOk();
        $this->withHeader('User-Agent', self::BROWSER)->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->get($bodys->publicUrl());
        $this->withHeader('User-Agent', self::BROWSER)->get($overview)->assertOk();
        // nicht gezählt: Filter-Klick, Messenger-Vorschau, Bot
        $this->withHeaders(['User-Agent' => self::BROWSER, 'X-Live-Filter' => '1'])->get($bodys->publicUrl().'?size[]=74');
        $this->withHeader('User-Agent', 'WhatsApp/2.24.1 A')->get($bodys->publicUrl());
        $this->withHeader('User-Agent', 'Googlebot/2.1')->get($bodys->publicUrl());

        $this->assertSame(4, PageView::withoutGlobalScope('tenant')->count());
        $this->assertSame(16, strlen(PageView::withoutGlobalScope('tenant')->value('visitor')));
        $this->assertSame(2, PageView::withoutGlobalScope('tenant')->where('category_id', $bodys->id)->distinct()->count('visitor'));

        $this->actingAs($user)->get(route('dashboard'))
            ->assertSeeTextInOrder(['Aufrufe öffentlicher Links', 'heute 4', '7 Tage 4 Aufrufe, 2 Besucher', 'Bodys', '3 Aufrufe', '2 Besucher', 'Gesamtübersicht', '1 Aufruf']);

        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertSee('In den letzten 7 Tagen hat niemand einen öffentlichen Link geöffnet.');
    }
}

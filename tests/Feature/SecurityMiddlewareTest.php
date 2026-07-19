<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SecurityMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_engine_bot_user_agent_is_blocked(): void
    {
        Config::set('security.block_search_engine_bots', true);

        $response = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        ])->get('/login');

        $response->assertStatus(403);
    }

    public function test_normal_browser_gets_noindex_header(): void
    {
        Config::set('security.noindex', true);

        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet');
    }

    public function test_absolute_session_lifetime_logs_user_out(): void
    {
        Config::set('security.session_absolute_lifetime', 30);

        $user = User::factory()->create();

        $this->actingAs($user);
        session(['logged_in_at' => now()->subMinutes(31)->timestamp]);

        $response = $this->get(route('products.index'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_web_rate_limit_returns_429(): void
    {
        Config::set('security.rate_limit_enabled', true);
        Config::set('security.web_per_minute', 3);

        $this->get('/login')->assertStatus(200);
        $this->get('/login')->assertStatus(200);
        $this->get('/login')->assertStatus(200);
        $this->get('/login')->assertStatus(429);
    }
}

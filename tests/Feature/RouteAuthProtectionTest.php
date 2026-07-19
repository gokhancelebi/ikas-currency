<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteAuthProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_update_requires_authentication(): void
    {
        $response = $this->put(route('products.bulk_update'), [
            'product_ids' => [1],
            'sync_enabled' => 1,
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_products_index_requires_authentication(): void
    {
        $this->get(route('products.index'))->assertRedirect(route('login'));
    }

    public function test_cron_refresh_without_token_returns_404(): void
    {
        config(['app.cron_secret' => 'test-secret']);

        $this->get('/cron-refresh')->assertStatus(404);
    }

    public function test_cron_refresh_with_valid_token_is_allowed(): void
    {
        config(['app.cron_secret' => 'test-secret']);

        $this->get('/cron-refresh?token=test-secret')->assertStatus(200);
    }

    public function test_authenticated_user_can_access_products(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('products.index'))
            ->assertStatus(200);
    }
}

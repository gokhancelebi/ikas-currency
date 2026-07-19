<?php

namespace Tests\Feature;

use Tests\TestCase;

class CustomErrorPageTest extends TestCase
{
    public function test_not_found_returns_branded_page(): void
    {
        $response = $this->get('/sayfa-yok-test-404');

        $response->assertStatus(404);
        $response->assertSee('404', false);
        $response->assertSee(config('app.name'), false);
        $response->assertDontSee('Laravel', false);
    }
}

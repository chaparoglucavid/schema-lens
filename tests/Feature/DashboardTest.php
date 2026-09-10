<?php

declare(strict_types=1);

namespace SchemaLens\Tests\Feature;

use Illuminate\Support\Facades\Route;
use SchemaLens\Tests\TestCase;

final class DashboardTest extends TestCase
{
    public function test_dashboard_route_is_registered(): void
    {
        $this->assertTrue(Route::has('schemalens.dashboard'));
        $this->assertTrue(Route::has('schemalens.compare'));
        $this->assertTrue(Route::has('schemalens.assets.css'));
    }

    public function test_dashboard_renders(): void
    {
        $response = $this->get('/schema-lens');

        $response->assertOk();
        $response->assertSee('SchemaLens');
        $response->assertSee('See exactly what changed in your database.');
        $response->assertSee('Compare two connections');
        $response->assertSee('Compare');
        $response->assertDontSee('super-secret-password-do-not-leak');
    }

    public function test_assets_are_served(): void
    {
        $this->get('/schema-lens/assets/css/schemalens.css')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/css; charset=UTF-8');

        $this->get('/schema-lens/assets/js/schemalens.js')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/javascript; charset=UTF-8');
    }

    public function test_compare_rejects_same_connection(): void
    {
        $response = $this->postJson('/schema-lens/compare', [
            'from' => 'production',
            'to' => 'production',
        ]);

        $response->assertStatus(422);
    }

    public function test_connections_listed_without_credentials(): void
    {
        $response = $this->get('/schema-lens');
        $response->assertOk();
        $content = $response->getContent();

        $this->assertIsString($content);
        $this->assertStringNotContainsString('super-secret-password-do-not-leak', $content);
        $this->assertStringNotContainsString('"password"', $content);
    }
}

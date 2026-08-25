<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_vite_assets_use_forwarded_https_tunnel_url(): void
    {
        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders([
                'Host' => 'demo-tunnel.example',
                'X-Forwarded-Host' => 'demo-tunnel.example',
                'X-Forwarded-Proto' => 'https',
            ])
            ->get('/');

        $response
            ->assertOk()
            ->assertSee('https://demo-tunnel.example/build/assets/app-', false)
            ->assertDontSee('http://demo-tunnel.example/build/', false);
    }
}

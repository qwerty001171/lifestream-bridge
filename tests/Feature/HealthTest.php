<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_ok_status(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'timestamp',
                'checks' => ['database'],
            ])
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database', 'ok');
    }

    public function test_health_response_contains_valid_timestamp(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200);

        $timestamp = $response->json('timestamp');
        $this->assertNotEmpty($timestamp);

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?Z$/',
            $timestamp,
            "Timestamp '{$timestamp}' is not a valid ISO 8601 UTC date"
        );
    }

    public function test_health_endpoint_is_accessible_without_authentication(): void
    {
        $response = $this->get('/api/health');

        $response->assertStatus(200);
    }

    public function test_health_endpoint_returns_json_content_type(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertHeader('Content-Type', 'application/json');
    }
}

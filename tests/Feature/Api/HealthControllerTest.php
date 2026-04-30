<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class HealthControllerTest extends TestCase
{
    public function test_returns_ok_status(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk()
                 ->assertExactJson(['status' => 'ok']);
    }

    public function test_returns_json_content_type(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertHeader('Content-Type', 'application/json');
    }
}

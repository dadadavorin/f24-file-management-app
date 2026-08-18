<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_health_endpoint_returns_ok(): void
    {
        $response = $this->get('/api/v1/health');

        $response->assertStatus(200)->assertJson(['status' => 'ok']);
    }
}

<?php

namespace Tests\Feature;

use Tests\TestCase;

class StepsControllerTest extends TestCase
{
    public function test_steps_requires_country(): void
    {
        $this->getJson('/api/steps')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['country']);
    }

    public function test_usa_returns_two_steps(): void
    {
        $response = $this->getJson('/api/steps?country=USA');

        $response->assertOk();
        $steps = $response->json('steps');

        $this->assertCount(2, $steps);
        $this->assertEquals('dashboard', $steps[0]['id']);
        $this->assertEquals('employees', $steps[1]['id']);
    }

    public function test_germany_returns_three_steps_including_documentation(): void
    {
        $response = $this->getJson('/api/steps?country=Germany');

        $response->assertOk();
        $steps = $response->json('steps');

        $this->assertCount(3, $steps);
        $ids = array_column($steps, 'id');
        $this->assertContains('documentation', $ids);
    }

    public function test_steps_include_required_metadata(): void
    {
        $response = $this->getJson('/api/steps?country=USA');
        $step     = $response->json('steps.0');

        $this->assertArrayHasKey('id', $step);
        $this->assertArrayHasKey('label', $step);
        $this->assertArrayHasKey('icon', $step);
        $this->assertArrayHasKey('path', $step);
        $this->assertArrayHasKey('order', $step);
    }
}

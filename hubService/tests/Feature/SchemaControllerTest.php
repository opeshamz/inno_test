<?php

namespace Tests\Feature;

use Tests\TestCase;

class SchemaControllerTest extends TestCase
{
    public function test_requires_country_param(): void
    {
        $this->getJson('/api/schema/dashboard')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['country']);
    }

    public function test_non_existent_step_returns_404(): void
    {
        $this->getJson('/api/schema/nonexistent?country=USA')
            ->assertNotFound();
    }

    public function test_usa_dashboard_schema_has_three_widgets(): void
    {
        $response = $this->getJson('/api/schema/dashboard?country=USA');
        $response->assertOk();

        $widgets = $response->json('schema.widgets');
        $this->assertCount(3, $widgets);
    }

    public function test_germany_dashboard_schema_has_goal_tracking_widget(): void
    {
        $response = $this->getJson('/api/schema/dashboard?country=Germany');
        $response->assertOk();

        $widgetIds = array_column($response->json('schema.widgets'), 'id');
        $this->assertContains('goal_tracking', $widgetIds);
    }

    public function test_germany_documentation_step_is_available(): void
    {
        $response = $this->getJson('/api/schema/documentation?country=Germany');
        $response->assertOk()
            ->assertJsonPath('schema.step_id', 'documentation');
    }

    public function test_usa_documentation_step_returns_404(): void
    {
        // Documentation step only exists for Germany
        $this->getJson('/api/schema/documentation?country=USA')
            ->assertNotFound();
    }

    public function test_each_widget_has_channel_for_real_time_updates(): void
    {
        $response = $this->getJson('/api/schema/dashboard?country=USA');
        $widgets  = $response->json('schema.widgets');

        foreach ($widgets as $widget) {
            $this->assertArrayHasKey('channel', $widget, "Widget '{$widget['id']}' is missing 'channel'.");
        }
    }
}

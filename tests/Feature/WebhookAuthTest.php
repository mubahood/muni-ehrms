<?php

namespace Tests\Feature;

use Tests\TestCase;

class WebhookAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.hikvision.webhook_token' => 'primary-token-0123456789',
            'services.hikvision.accepted_tokens' => ' old-bridge-token , ',
        ]);
    }

    private function post_events(array $headers)
    {
        return $this->postJson('/api/webhook/events', ['events' => []], $headers);
    }

    public function test_primary_token_is_accepted()
    {
        $this->assertNotEquals(401, $this->post_events(['X-API-Key' => 'primary-token-0123456789'])->status());
    }

    public function test_bridge_token_listed_as_accepted_is_accepted()
    {
        $this->assertNotEquals(401, $this->post_events(['X-Webhook-Token' => 'old-bridge-token'])->status());
        $this->assertNotEquals(401, $this->postJson('/api/hikvision/events', [], ['Authorization' => 'Bearer old-bridge-token'])->status());
    }

    public function test_wrong_or_missing_token_is_refused()
    {
        $this->post_events(['X-API-Key' => 'nope'])->assertStatus(401);
        $this->post_events([])->assertStatus(401);
        $this->postJson('/api/hikvision/events', [], ['X-Webhook-Token' => 'nope'])->assertStatus(401);
    }

    public function test_nothing_is_accepted_when_no_token_is_configured()
    {
        config(['services.hikvision.webhook_token' => '', 'services.hikvision.accepted_tokens' => '']);
        $this->post_events(['X-API-Key' => ''])->assertStatus(401);
    }
}

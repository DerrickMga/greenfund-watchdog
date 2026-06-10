<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileAccountApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receive_a_mobile_token(): void
    {
        SubscriptionPlan::query()->create([
            'code' => 'starter',
            'name' => 'Starter',
            'price_cents' => 499,
            'currency' => 'USD',
            'interval' => 'month',
            'device_limit' => 1,
            'features' => ['Core VPN protection'],
        ]);

        $response = $this->postJson('/api/mobile/register', [
            'name' => 'Mobile User',
            'email' => 'mobile@example.com',
            'password' => 'password123',
            'device_name' => 'Pixel',
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email', 'subscription'],
            ])
            ->assertJsonPath('user.subscription.plan.code', 'starter');

        $token = $response->json('token');

        $this->withToken($token)
            ->getJson('/api/mobile/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'mobile@example.com');
    }

    public function test_user_can_select_a_subscription_plan(): void
    {
        SubscriptionPlan::query()->create([
            'code' => 'pro',
            'name' => 'Pro',
            'price_cents' => 999,
            'currency' => 'USD',
            'interval' => 'month',
            'device_limit' => 5,
            'features' => ['Split tunnel'],
        ]);

        $register = $this->postJson('/api/mobile/register', [
            'name' => 'Subscriber',
            'email' => 'subscriber@example.com',
            'password' => 'password123',
        ]);

        $this->withToken($register->json('token'))
            ->postJson('/api/mobile/subscription/select-plan', [
                'plan_code' => 'pro',
            ])
            ->assertOk()
            ->assertJsonPath('subscription.status', 'active')
            ->assertJsonPath('subscription.plan.code', 'pro');
    }
}

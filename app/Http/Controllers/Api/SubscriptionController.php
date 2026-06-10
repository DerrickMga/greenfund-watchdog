<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function plans(): JsonResponse
    {
        return response()->json([
            'plans' => SubscriptionPlan::query()
                ->where('is_active', true)
                ->orderBy('price_cents')
                ->get()
                ->map(fn (SubscriptionPlan $plan) => [
                    'code' => $plan->code,
                    'name' => $plan->name,
                    'price_cents' => $plan->price_cents,
                    'currency' => $plan->currency,
                    'interval' => $plan->interval,
                    'device_limit' => $plan->device_limit,
                    'features' => $plan->features ?? [],
                ]),
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->attributes->get('mobile_user');
        $user->loadMissing('subscription.plan');

        return response()->json([
            'subscription' => $user->subscription,
        ]);
    }

    public function selectPlan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_code' => ['required', 'string', 'exists:subscription_plans,code'],
        ]);

        $user = $request->attributes->get('mobile_user');
        $plan = SubscriptionPlan::query()
            ->where('code', $validated['plan_code'])
            ->where('is_active', true)
            ->firstOrFail();

        $subscription = Subscription::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'subscription_plan_id' => $plan->id,
                'provider' => 'manual',
                'status' => 'active',
                'trial_ends_at' => null,
                'renews_at' => now()->addMonth(),
                'ends_at' => null,
            ],
        );

        return response()->json([
            'subscription' => $subscription->load('plan'),
        ]);
    }
}

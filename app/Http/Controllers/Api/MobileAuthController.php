<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileAccessToken;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MobileAuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => Str::lower($validated['email']),
            'password' => $validated['password'],
        ]);

        $plan = SubscriptionPlan::query()
            ->where('code', 'starter')
            ->where('is_active', true)
            ->first();

        if ($plan) {
            Subscription::query()->create([
                'user_id' => $user->id,
                'subscription_plan_id' => $plan->id,
                'provider' => 'manual',
                'status' => 'trialing',
                'trial_ends_at' => now()->addDays(14),
            ]);
        }

        return response()->json($this->sessionPayload(
            $user,
            $validated['device_name'] ?? 'mobile'
        ), 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $user = User::query()
            ->where('email', Str::lower($validated['email']))
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return response()->json($this->sessionPayload(
            $user,
            $validated['device_name'] ?? 'mobile'
        ));
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userPayload($request->attributes->get('mobile_user')),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->attributes->get('mobile_token')?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * @return array{token: string, user: array<string, mixed>}
     */
    private function sessionPayload(User $user, string $deviceName): array
    {
        $plainToken = Str::random(80);

        MobileAccessToken::query()->create([
            'user_id' => $user->id,
            'name' => $deviceName,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(90),
        ]);

        return [
            'token' => $plainToken,
            'user' => $this->userPayload($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        $user->loadMissing('subscription.plan');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'subscription' => $user->subscription ? [
                'status' => $user->subscription->status,
                'provider' => $user->subscription->provider,
                'trial_ends_at' => $user->subscription->trial_ends_at?->toIso8601String(),
                'renews_at' => $user->subscription->renews_at?->toIso8601String(),
                'ends_at' => $user->subscription->ends_at?->toIso8601String(),
                'plan' => $user->subscription->plan ? [
                    'code' => $user->subscription->plan->code,
                    'name' => $user->subscription->plan->name,
                    'device_limit' => $user->subscription->plan->device_limit,
                ] : null,
            ] : null,
        ];
    }
}

<?php

namespace App\Http\Controllers\Api\Waiter;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureWaiterApiToken;
use App\Models\RolePermission;
use App\Models\StaffProfile;
use App\Models\User;
use App\Models\WaiterApiToken;
use App\Support\WaiterApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function profiles(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'restaurant_id' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);
        $user = $this->restaurantUser($validated['restaurant_id'], $validated['password']);
        $profiles = StaffProfile::withoutGlobalScopes()
            ->where('branch_id', $user->branch_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (StaffProfile $staff): bool => $this->canUseWaiterApi($staff))
            ->values()
            ->map(fn (StaffProfile $staff): array => WaiterApiPresenter::staff($staff));

        return response()->json([
            'success' => true,
            'data' => [
                'branch' => [
                    'id' => $user->branch->id,
                    'name' => $user->branch->name,
                    'code' => $user->branch->code,
                ],
                'profiles' => $profiles,
            ],
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'restaurant_id' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'profile_id' => ['required', 'integer'],
            'pin' => ['required', 'digits_between:4,6'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);
        $user = $this->restaurantUser($validated['restaurant_id'], $validated['password']);
        $staff = StaffProfile::withoutGlobalScopes()
            ->whereKey($validated['profile_id'])
            ->where('branch_id', $user->branch_id)
            ->where('is_active', true)
            ->first();

        if (! $staff || ! $this->canUseWaiterApi($staff) || ! $staff->verifyPin($validated['pin'])) {
            throw ValidationException::withMessages([
                'credentials' => ['Personel profili veya PIN hatalı.'],
            ]);
        }

        [$token, $plainTextToken] = DB::transaction(function () use ($user, $staff, $validated): array {
            WaiterApiToken::where('expires_at', '<=', now())->delete();

            $plainTextToken = 'wtr_'.Str::random(80);
            $ttlMinutes = max(60, min(525600, (int) config('adisyon.waiter_api_token_ttl_minutes', 43200)));
            $token = WaiterApiToken::create([
                'branch_id' => $user->branch_id,
                'user_id' => $user->id,
                'staff_profile_id' => $staff->id,
                'name' => trim((string) ($validated['device_name'] ?? 'Flutter Garson')) ?: 'Flutter Garson',
                'token_hash' => hash('sha256', $plainTextToken),
                'last_used_at' => now(),
                'expires_at' => now()->addMinutes($ttlMinutes),
            ]);

            return [$token, $plainTextToken];
        });

        return response()->json([
            'success' => true,
            'message' => "Hoş geldiniz, {$staff->name}.",
            'data' => [
                'token_type' => 'Bearer',
                'access_token' => $plainTextToken,
                'expires_at' => $token->expires_at->toIso8601String(),
                'branch' => [
                    'id' => $user->branch->id,
                    'name' => $user->branch->name,
                    'code' => $user->branch->code,
                ],
                'staff' => WaiterApiPresenter::staff($staff),
                'permissions' => RolePermission::getPermissionsForRole($staff->role),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $token = $request->attributes->get(EnsureWaiterApiToken::TOKEN_ATTRIBUTE);
        $staff = $request->attributes->get(EnsureWaiterApiToken::STAFF_ATTRIBUTE);

        return response()->json([
            'success' => true,
            'data' => [
                'branch' => [
                    'id' => $token->branch->id,
                    'name' => $token->branch->name,
                    'code' => $token->branch->code,
                ],
                'staff' => WaiterApiPresenter::staff($staff),
                'permissions' => RolePermission::getPermissionsForRole($staff->role),
                'expires_at' => $token->expires_at->toIso8601String(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->attributes->get(EnsureWaiterApiToken::TOKEN_ATTRIBUTE)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Oturum kapatıldı.',
        ]);
    }

    private function restaurantUser(string $login, string $password): User
    {
        $normalizedLogin = trim($login);
        $cleanLogin = str_replace('-', '', strtoupper($normalizedLogin));
        $user = User::with('branch')
            ->where(function ($query) use ($normalizedLogin, $cleanLogin): void {
                $query->where('restaurant_id', $normalizedLogin)
                    ->orWhereRaw("REPLACE(UPPER(restaurant_id), '-', '') = ?", [$cleanLogin])
                    ->orWhere('email', strtolower($normalizedLogin));
            })
            ->first();

        if (! $user
            || $user->isAdminUser()
            || ! $user->branch?->is_active
            || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'credentials' => ['Restoran bilgileri hatalı.'],
            ]);
        }

        return $user;
    }

    private function canUseWaiterApi(StaffProfile $staff): bool
    {
        return in_array($staff->role, ['Yönetici', 'Müdür'], true)
            || in_array('garson', RolePermission::getPermissionsForRole($staff->role), true);
    }
}

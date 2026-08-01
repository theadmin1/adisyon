<?php

namespace App\Http\Middleware;

use App\Models\RolePermission;
use App\Models\WaiterApiToken;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureWaiterApiToken
{
    public const TOKEN_ATTRIBUTE = 'waiter_api_token';

    public const STAFF_ATTRIBUTE = 'waiter_staff_profile';

    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = $request->bearerToken();

        if (! is_string($plainTextToken) || ! str_starts_with($plainTextToken, 'wtr_')) {
            return $this->deny('Geçerli bir Bearer token gerekli.', 401);
        }

        $token = WaiterApiToken::with(['branch', 'user', 'staffProfile'])
            ->where('token_hash', hash('sha256', $plainTextToken))
            ->first();

        if (! $token || $token->expires_at->isPast()) {
            $token?->delete();

            return $this->deny('Oturum geçersiz veya süresi dolmuş.', 401);
        }

        $staff = $token->staffProfile;
        $user = $token->user;
        $branch = $token->branch;

        if (! $branch?->is_active
            || ! $staff?->is_active
            || (int) $staff->branch_id !== (int) $token->branch_id
            || ! $user
            || $user->isAdminUser()
            || (int) $user->branch_id !== (int) $token->branch_id) {
            $token->delete();

            return $this->deny('Personel veya şube artık aktif değil.', 403);
        }

        $permissions = RolePermission::getPermissionsForRole($staff->role);
        if (! in_array($staff->role, ['Yönetici', 'Müdür'], true)
            && ! in_array('garson', $permissions, true)) {
            return $this->deny('Bu personelin garson uygulamasını kullanma yetkisi yok.', 403);
        }

        Auth::setUser($user);
        $request->setUserResolver(static fn () => $user);
        $request->attributes->set(self::TOKEN_ATTRIBUTE, $token);
        $request->attributes->set(self::STAFF_ATTRIBUTE, $staff);

        if ($token->last_used_at === null || $token->last_used_at->lt(now()->subMinutes(5))) {
            $token->forceFill(['last_used_at' => now()])->save();
        }

        return $next($request);
    }

    private function deny(string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}

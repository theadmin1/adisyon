<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRestaurantUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isAdminUser()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Restoran şubesi oturumu gereklidir.'], 403);
            }

            return redirect()->route('admin.dashboard');
        }

        if (! $user?->branch_id
            || ! Branch::whereKey($user->branch_id)->where('is_active', true)->exists()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Aktif restoran şubesi bulunamadı.'], 403);
            }

            return redirect()->route('login')->withErrors([
                'restaurant_id' => 'Hesabınıza bağlı aktif restoran şubesi bulunamadı.',
            ]);
        }

        return $next($request);
    }
}

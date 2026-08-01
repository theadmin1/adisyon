<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureChainUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || ! $user->isChainUser() || ! $user->organization?->is_active) {
            Auth::logout();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            return redirect()->route('chain.login')->with('error', 'Zincir yönetim paneline erişim yetkiniz yok.');
        }

        return $next($request);
    }
}

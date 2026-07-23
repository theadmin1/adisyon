<?php

namespace App\Http\Middleware;

use App\Models\RolePermission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffModulePermission
{
    /**
     * Handle an incoming request for staff module authorization.
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        // Yönetici veya Admin Kullanıcılar Her Şeye Erişebilir
        if ($request->user() && $request->user()->isAdminUser()) {
            return $next($request);
        }

        $role = session('active_staff_role', 'Yönetici');

        // Yönetici ve Müdür Rolü Tüm Modüllere Erişebilir
        if (in_array($role, ['Yönetici', 'Müdür'])) {
            return $next($request);
        }

        $allowedModules = RolePermission::getPermissionsForRole($role);

        if (!in_array($module, $allowedModules)) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => "'{$role}' rolünün bu modüle ({$module}) erişim yetkisi bulunmamaktadır.",
                ], 403);
            }

            return redirect()->route('dashboard')->with('error', "'{$role}' rolünüz için '{$module}' modülüne erişim yetkisi bulunmamaktadır.");
        }

        return $next($request);
    }
}

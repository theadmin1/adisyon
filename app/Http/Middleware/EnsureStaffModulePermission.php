<?php

namespace App\Http\Middleware;

use App\Models\RolePermission;
use App\Models\StaffProfile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffModulePermission
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        if ($request->user()?->isAdminUser()) {
            return $next($request);
        }

        $staffId = session('active_staff_id');
        $role = session('active_staff_role');

        if (! $staffId || ! $role) {
            return $this->deny($request, 'İşlem için aktif personel profili seçilmelidir.');
        }

        $profile = StaffProfile::query()
            ->whereKey($staffId)
            ->where('is_active', true)
            ->first();

        if (! $profile || $profile->role !== $role) {
            session()->forget(['active_staff_id', 'active_staff_name', 'active_staff_role', 'active_staff_color']);

            return $this->deny($request, 'Personel oturumu geçersiz veya pasif.');
        }

        if (in_array($role, ['Yönetici', 'Müdür'], true)) {
            return $next($request);
        }

        if (! in_array($module, RolePermission::getPermissionsForRole($role), true)) {
            return $this->deny($request, "'{$role}' rolünün {$module} modülüne erişim yetkisi yok.");
        }

        return $next($request);
    }

    private function deny(Request $request, string $message): Response
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => false, 'message' => $message], 403);
        }

        return redirect()->route('staff.profiles')->with('error', $message);
    }
}

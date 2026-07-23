<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RolePermission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminRolePermissionController extends Controller
{
    public function index(): View
    {
        $allModules = RolePermission::availableModules();
        $defaultRoles = array_keys(RolePermission::defaultPermissions());

        // Veritabanındaki tüm rolleri getir
        $dbRoles = RolePermission::all()->keyBy('role_name');

        // Bütün rolleri birleştir (Varsayılan + Özel eklenenler)
        $roleList = array_unique(array_merge($defaultRoles, $dbRoles->keys()->toArray()));

        $matrix = [];
        foreach ($roleList as $roleName) {
            if (isset($dbRoles[$roleName])) {
                $matrix[$roleName] = $dbRoles[$roleName]->permissions ?? [];
            } else {
                $matrix[$roleName] = RolePermission::defaultPermissions()[$roleName] ?? [];
            }
        }

        return view('admin.roles.index', compact('allModules', 'matrix', 'roleList'));
    }

    public function update(Request $request): RedirectResponse
    {
        $postedPermissions = $request->input('permissions', []);
        $allRoles = $request->input('roles', []);

        foreach ($allRoles as $roleName) {
            $allowedModules = $postedPermissions[$roleName] ?? [];
            
            RolePermission::updateOrCreate(
                ['role_name' => $roleName],
                ['permissions' => array_values($allowedModules)]
            );
        }

        return redirect()->back()->with('success', 'Rol ve modül yetki tanımları başarıyla güncellendi ve kaydedildi!');
    }

    public function storeRole(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'role_name' => 'required|string|max:100|unique:role_permissions,role_name',
        ]);

        $roleName = trim($validated['role_name']);

        RolePermission::create([
            'role_name' => $roleName,
            'permissions' => ['masalar', 'hizli-satis', 'online-siparis'],
        ]);

        return redirect()->back()->with('success', "'{$roleName}' isimli yeni rol başarıyla oluşturuldu.");
    }
}

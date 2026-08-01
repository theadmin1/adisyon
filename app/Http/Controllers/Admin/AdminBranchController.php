<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\License;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminBranchController extends Controller
{
    public function index(): View
    {
        try {
            $branches = Branch::with(['organizations:id,name,logo_path'])
                ->withCount(['licenses', 'devices', 'staffProfiles'])
                ->latest()
                ->paginate(15);
        } catch (\Throwable $e) {
            $branches = new LengthAwarePaginator([], 0, 15);
        }

        return view('admin.branches.index', compact('branches'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'code' => Str::upper(trim((string) $request->input('code'))),
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('branches', 'code'),
                Rule::unique('users', 'restaurant_id'),
            ],
            'contact_email' => 'nullable|email',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'create_license' => 'nullable|boolean',
            'license_expires_at' => 'nullable|required_if:create_license,1|date|after_or_equal:today',
            'license_max_devices' => 'nullable|required_if:create_license,1|integer|min:1|max:1000',
            'license_notes' => 'nullable|string|max:2000',
        ]);

        $password = Str::password(12, letters: true, numbers: true, symbols: false);
        $licenseKey = $request->boolean('create_license')
            ? 'ALTF4-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4))
            : null;

        DB::transaction(function () use ($validated, $password, $licenseKey): void {
            $branch = Branch::create([
                'name' => $validated['name'],
                'code' => $validated['code'],
                'contact_email' => $validated['contact_email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
            ]);

            User::create([
                'name' => $branch->name.' Restoran Hesabı',
                'email' => 'branch.'.Str::lower($branch->code).'.'.Str::lower(Str::random(8)).'@adisyon.local',
                'restaurant_id' => $branch->code,
                'branch_id' => $branch->id,
                'password' => $password,
                'is_admin' => false,
            ]);

            if ($licenseKey !== null) {
                License::create([
                    'branch_id' => $branch->id,
                    'license_key' => $licenseKey,
                    'device_token' => (string) Str::uuid(),
                    'status' => 'Active',
                    'expires_at' => $validated['license_expires_at'],
                    'max_devices' => $validated['license_max_devices'],
                    'notes' => $validated['license_notes'] ?? null,
                ]);
            }
        });

        return redirect()->back()
            ->with('success', 'Yeni şube ve restoran giriş hesabı başarıyla oluşturuldu.')
            ->with('restaurant_credentials', [
                'restaurant_id' => $validated['code'],
                'password' => $password,
                'license_key' => $licenseKey,
            ]);
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $request->merge([
            'code' => Str::upper(trim((string) $request->input('code'))),
        ]);

        $restaurantUser = User::where('branch_id', $branch->id)
            ->where('restaurant_id', $branch->code)
            ->where('is_admin', false)
            ->first();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('branches', 'code')->ignore($branch->id),
                Rule::unique('users', 'restaurant_id')->ignore($restaurantUser?->id),
            ],
            'contact_email' => 'nullable|email',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:2000',
        ]);

        DB::transaction(function () use ($branch, $restaurantUser, $validated): void {
            $branch->update($validated);

            if ($restaurantUser) {
                $restaurantUser->update([
                    'name' => $branch->name.' Restoran Hesabı',
                    'restaurant_id' => $branch->code,
                ]);
            }
        });

        return back()->with('success', 'Şube bilgileri güncellendi.');
    }

    public function toggleStatus(Branch $branch): RedirectResponse
    {
        $branch->update(['is_active' => ! $branch->is_active]);

        return back()->with('success', 'Şube durumu '.($branch->is_active ? 'aktif' : 'pasif').' olarak güncellendi.');
    }

    public function resetPassword(Branch $branch): RedirectResponse
    {
        $restaurantUser = User::where('branch_id', $branch->id)
            ->where('restaurant_id', $branch->code)
            ->where('is_admin', false)
            ->first();

        $password = Str::password(12, letters: true, numbers: true, symbols: false);

        if ($restaurantUser) {
            $restaurantUser->update(['password' => $password]);
        } else {
            $restaurantUser = User::create([
                'name' => $branch->name.' Restoran Hesabı',
                'email' => 'branch.'.Str::lower($branch->code).'.'.Str::lower(Str::random(8)).'@adisyon.local',
                'restaurant_id' => $branch->code,
                'branch_id' => $branch->id,
                'password' => $password,
                'is_admin' => false,
            ]);
        }

        return back()
            ->with('success', 'Restoran giriş şifresi yenilendi.')
            ->with('restaurant_credentials', [
                'restaurant_id' => $branch->code,
                'password' => $password,
                'license_key' => null,
            ]);
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        try {
            DB::transaction(function () use ($branch): void {
                User::where('branch_id', $branch->id)
                    ->where('is_admin', false)
                    ->delete();

                $branch->delete();
            });
        } catch (\Throwable) {
            return back()->withErrors([
                'branch' => 'Bu şube operasyonel kayıtlarda kullanıldığı için silinemedi. Önce şubeyi pasif duruma alabilirsiniz.',
            ]);
        }

        return back()->with('success', 'Şube, restoran hesabı ve bağlı kayıtları silindi.');
    }
}

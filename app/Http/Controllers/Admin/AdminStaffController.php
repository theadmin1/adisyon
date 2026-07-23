<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\StaffProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminStaffController extends Controller
{
    public function index(Request $request): View
    {
        $branches = Branch::orderBy('name')->get();
        $selectedBranchId = $request->input('branch_id');
        $search = trim($request->input('search', ''));

        $query = StaffProfile::with('branch')->latest();

        if ($selectedBranchId) {
            $query->where('branch_id', $selectedBranchId);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('role', 'like', "%{$search}%")
                  ->orWhere('pin_code', 'like', "%{$search}%");
            });
        }

        $profiles = $query->paginate(20)->withQueryString();

        return view('admin.staff.index', compact('profiles', 'branches', 'selectedBranchId', 'search'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'name' => 'required|string|max:255',
            'role' => 'required|string|max:100',
            'pin_code' => 'required|string|min:4|max:6',
            'avatar_color' => 'required|string|in:indigo,emerald,amber,rose,cyan,purple',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        StaffProfile::create($validated);

        return redirect()->back()->with('success', 'Personel alt hesabı / profili başarıyla eklendi!');
    }

    public function update(Request $request, StaffProfile $staff): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'name' => 'required|string|max:255',
            'role' => 'required|string|max:100',
            'pin_code' => 'required|string|min:4|max:6',
            'avatar_color' => 'required|string|in:indigo,emerald,amber,rose,cyan,purple',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $staff->update($validated);

        return redirect()->back()->with('success', 'Personel profili başarıyla güncellendi!');
    }

    public function toggleStatus(StaffProfile $staff): RedirectResponse
    {
        $staff->update([
            'is_active' => !$staff->is_active,
        ]);

        $statusText = $staff->is_active ? 'aktif' : 'pasif';
        return redirect()->back()->with('success', "Personel profili durumu {$statusText} yapıldı.");
    }

    public function destroy(StaffProfile $staff): RedirectResponse
    {
        $staffName = $staff->name;
        $staff->delete();

        return redirect()->back()->with('success', "'{$staffName}' isimli personel profili silindi.");
    }
}

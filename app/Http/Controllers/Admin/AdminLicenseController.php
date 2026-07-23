<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\License;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminLicenseController extends Controller
{
    public function index(): View
    {
        try {
            $licenses = License::with(['branch', 'devices'])->latest()->paginate(15);
            $branches = Branch::where('is_active', true)->get();
        } catch (\Throwable $e) {
            $licenses = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15);
            $branches = collect([]);
        }

        return view('admin.licenses.index', compact('licenses', 'branches'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'expires_at' => 'nullable|date',
            'max_devices' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $licenseKey = 'ALTF4-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));

        License::create([
            'branch_id' => $validated['branch_id'],
            'license_key' => $licenseKey,
            'device_token' => (string) Str::uuid(),
            'status' => 'Active',
            'expires_at' => $validated['expires_at'] ?? now()->addYear(),
            'max_devices' => $validated['max_devices'],
            'notes' => $validated['notes'],
        ]);

        return redirect()->back()->with('success', "Yeni Lisans Başarıyla Oluşturuldu: {$licenseKey}");
    }

    public function toggleStatus(License $license): RedirectResponse
    {
        $newStatus = $license->status === 'Active' ? 'Suspended' : 'Active';
        $license->update(['status' => $newStatus]);

        return redirect()->back()->with('success', "Lisans durumu güncellendi: {$newStatus}");
    }

    public function destroy(License $license): RedirectResponse
    {
        $license->delete();
        return redirect()->back()->with('success', 'Lisans silindi.');
    }
}

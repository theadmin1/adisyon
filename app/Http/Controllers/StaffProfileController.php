<?php

namespace App\Http\Controllers;

use App\Models\StaffProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StaffProfileController extends Controller
{
    /**
     * Netflix tarzı "Kim Çalışıyor?" Personel Profil Seçim Ekranı.
     */
    public function index()
    {
        $user = Auth::user();
        $branchId = $user->branch_id ?? 1;

        $profiles = StaffProfile::where('branch_id', $branchId)
            ->where('is_active', true)
            ->get();

        $activeStaff = null;
        if (session()->has('active_staff_id')) {
            $activeStaff = StaffProfile::find(session('active_staff_id'));
        }

        return view('staff.profiles', compact('profiles', 'activeStaff'));
    }

    /**
     * Seçilen personel için 4-6 haneli PIN Kodunu doğrular ve oturumu aktifleştirir.
     */
    public function selectProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'profile_id' => 'required|exists:staff_profiles,id',
            'pin' => 'required|string|min:4|max:6',
        ]);

        $profile = StaffProfile::findOrFail($validated['profile_id']);

        if (!$profile->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Bu personel profili pasife alınmıştır!',
            ], 403);
        }

        if (!$profile->verifyPin($validated['pin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Girdiğiniz PIN Kodu hatalı!',
            ], 422);
        }

        // Aktif personel oturumunu ayarla
        session([
            'active_staff_id' => $profile->id,
            'active_staff_name' => $profile->name,
            'active_staff_role' => $profile->role,
            'active_staff_color' => $profile->avatar_color,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Hoş geldiniz, {$profile->name} ({$profile->role})!",
            'redirect' => route('dashboard'),
        ]);
    }

    /**
     * Aktif personeli kapatır ve Profil Seçim Ekranına yönlendirir.
     */
    public function switchProfile()
    {
        session()->forget(['active_staff_id', 'active_staff_name', 'active_staff_role', 'active_staff_color']);
        return redirect()->route('staff.profiles')->with('info', 'Personel oturumu kapatıldı. Lütfen profilinizi seçiniz.');
    }
}

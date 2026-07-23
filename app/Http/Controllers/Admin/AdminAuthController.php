<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAuthController extends Controller
{
    public function showLogin()
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('branches') || !\Illuminate\Support\Facades\Schema::hasTable('licenses') || !\Illuminate\Support\Facades\Schema::hasTable('staff_profiles')) {
            try {
                \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
                \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
            } catch (\Throwable $e) {}
        }

        if (Auth::check() && Auth::user()->is_admin) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $user = Auth::user();

            if (!$user->is_admin) {
                Auth::logout();
                return back()->withErrors([
                    'email' => '❌ Yetkisiz Giriş: Bu panele sadece Lisans ve Sistem Yöneticileri erişebilir.',
                ])->onlyInput('email');
            }

            $request->session()->regenerate();

            return redirect()->route('admin.dashboard')
                ->with('success', 'Central Admin paneline hoş geldiniz!');
        }

        return back()->withErrors([
            'email' => 'Girdiğiniz yönetici e-postası veya şifre hatalı.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('info', 'Admin oturumu kapatıldı.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check() && Auth::user()->isAdminUser()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $loginValue = trim($request->input('email') ?? $request->input('login') ?? '');
        $password = $request->input('password');

        if (empty($loginValue) || empty($password)) {
            return back()->withErrors([
                'email' => 'Lütfen e-posta ve şifrenizi giriniz.',
            ]);
        }

        $remember = $request->boolean('remember');

        $user = \App\Models\User::where('email', $loginValue)
            ->orWhere('restaurant_id', $loginValue)
            ->first();

        if ($user && \Illuminate\Support\Facades\Hash::check($password, $user->password)) {
            if (!$user->isAdminUser()) {
                return back()->withErrors([
                    'email' => '❌ Yetkisiz Giriş: Bu panele sadece Lisans ve Sistem Yöneticileri erişebilir.',
                ])->onlyInput('email');
            }

            Auth::login($user, $remember);
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

<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $loginValue = trim($request->input('restaurant_id') ?? $request->input('login') ?? $request->input('email') ?? $request->input('username') ?? '');
        $password = $request->input('password');

        if (empty($loginValue) || empty($password)) {
            return back()->withErrors([
                'restaurant_id' => 'Lütfen Restoran ID ve şifrenizi giriniz.',
            ]);
        }

        $remember = $request->boolean('remember', true);

        // Kullanıcıyı restaurant_id veya email üzerinden arayalım
        $user = null;
        try {
            $cleanLogin = str_replace('-', '', strtoupper($loginValue));
            $user = User::where(function ($query) use ($loginValue, $cleanLogin) {
                if (Schema::hasColumn('users', 'restaurant_id')) {
                    $query->where('restaurant_id', $loginValue)
                        ->orWhere('restaurant_id', strtoupper($loginValue))
                        ->orWhere('restaurant_id', strtolower($loginValue))
                        ->orWhereRaw("REPLACE(UPPER(restaurant_id), '-', '') = ?", [$cleanLogin]);
                }
                $query->orWhere('email', $loginValue)
                    ->orWhere('email', strtolower($loginValue));
            })->first();
        } catch (\Throwable $e) {
            Log::error('Login kullanıcı arama hatası: '.$e->getMessage());
            $user = null;
        }

        $hasActiveBranch = $user
            && ! $user->isAdminUser()
            && $user->branch_id
            && Branch::whereKey($user->branch_id)->where('is_active', true)->exists();

        if ($user && $hasActiveBranch && Hash::check($password, $user->password)) {
            Auth::login($user, $remember);
            try {
                $request->session()->regenerate();
            } catch (\Throwable $e) {
            }

            return redirect()->intended(route('dashboard'))
                ->with('success', 'Hoş geldiniz! Oturum açma başarılı.');
        }

        return back()->withErrors([
            'restaurant_id' => 'Girdiğiniz Restoran ID veya şifre hatalı.',
        ])->onlyInput('restaurant_id');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('info', 'Güvenli çıkış yapıldı.');
    }
}

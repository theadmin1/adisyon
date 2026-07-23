<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        // Kullanıcıyı restaurant_id veya email üzerinden bulalım
        $user = \App\Models\User::where('restaurant_id', $loginValue)
            ->orWhere('email', $loginValue)
            ->first();

        if ($user && \Illuminate\Support\Facades\Hash::check($password, $user->password)) {
            Auth::login($user, $remember);
            $request->session()->regenerate();

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

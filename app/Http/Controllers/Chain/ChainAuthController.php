<?php

namespace App\Http\Controllers\Chain;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ChainAuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::user()?->isChainUser()) {
            return redirect()->route('chain.dashboard');
        }

        return view('chain.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::with('organization')->where('email', strtolower($credentials['email']))->first();

        if (! $user || ! $user->isChainUser() || ! $user->organization?->is_active || ! Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors(['email' => 'E-posta, şifre veya zincir yetkisi geçersiz.'])->onlyInput('email');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('chain.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('chain.login');
    }
}

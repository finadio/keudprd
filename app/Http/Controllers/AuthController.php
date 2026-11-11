<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    /**
     * Show the login form.
     */
    public function showLogin(): Response
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * Handle incoming authentication request.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember', true);

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();

            AuditLog::log('login', 'User', Auth::id(), null, [
                'email' => Auth::user()->email,
                'name' => Auth::user()->name,
            ]);

            return redirect()->intended('/dashboard')->with('success', 'Selamat datang kembali, '.Auth::user()->name.'!');
        }

        return back()->withErrors([
            'email' => 'Email atau kata sandi yang Anda masukkan tidak sesuai.',
        ])->onlyInput('email');
    }

    /**
     * Handle user logout.
     */
    public function logout(Request $request)
    {
        $userId = Auth::id();
        if ($userId) {
            AuditLog::log('logout', 'User', $userId);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Anda telah berhasil keluar dari sistem.');
    }
}

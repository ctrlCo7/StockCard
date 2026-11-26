<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt([...$credentials, 'is_active' => true], $request->boolean('remember'))) {
            AuditLogger::log('auth.failed', 'Failed login attempt for '.$credentials['email']);

            throw ValidationException::withMessages(['email' => 'These credentials do not match an active account.']);
        }

        $request->session()->regenerate();
        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->save();
        AuditLogger::log('auth.login', "{$user->name} logged in", $user);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        AuditLogger::log('auth.logout', "{$request->user()->name} logged out", $request->user());

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

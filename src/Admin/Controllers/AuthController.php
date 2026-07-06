<?php
declare(strict_types=1);

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <me@wekser.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Wekser\Laragram\Admin\Controllers;

use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Login / logout for the admin panel, backed by the "laragram_admin" session
 * guard (the laragram_admins table). Kept outside the Authorize middleware so an
 * unauthenticated visitor can actually reach the form.
 */
class AuthController
{
    public function show(): View|RedirectResponse
    {
        if ($this->guard()->check()) {
            return redirect()->route('laragram.admin.dashboard');
        }

        return view('laragram::admin.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (! $this->guard()->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'username' => 'These credentials do not match our records.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('laragram.admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->guard()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('laragram.admin.login');
    }

    private function guard(): StatefulGuard
    {
        return Auth::guard(config('laragram.admin.guard', 'laragram_admin'));
    }
}

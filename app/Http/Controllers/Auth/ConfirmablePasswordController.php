<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ConfirmablePasswordController extends Controller
{
    /**
     * Show the confirm password page.
     */
    public function show(): Response
    {
        return Inertia::render('auth/confirm-password');
    }

    /**
     * Confirm the user's password.
     */
    public function store(Request $request): RedirectResponse
    {
        if (! Auth::guard('web')->validate([
            'email' => $request->user()->email,
            'password' => $request->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        // BUG FIX (permintaan Boss 2026-08-07): konsisten dgn AuthenticatedSessionController
        // -- 'dashboard' (lama) -> 'dashboard.overview' (Command Center). Fallback ini
        // JARANG kepakai (intended() biasanya sudah punya tujuan asli), tapi tetap
        // dibetulkan supaya tidak ada jalur tersisa yang balik ke dashboard lama.
        return redirect()->intended(route('dashboard.overview', absolute: false));
    }
}

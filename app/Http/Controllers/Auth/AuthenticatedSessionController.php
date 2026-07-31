<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * BUSINESS RULE: 03-BUSINESS-FLOW §7 — diagram alur auth sejak Hari-1 sudah
     * menetapkan tujuan landing BEDA per role: admin -> Dashboard, member -> My
     * Tasks (halaman kerja utama mereka, F-29 member tidak punya dashboard tim).
     * Hari-5 §D5 baru mengimplementasikannya. intended() tetap diprioritaskan —
     * kalau user coba akses URL spesifik sebelum diarahkan ke login, redirect_to
     * situ dulu, bukan ke landing default.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // F-90/v0.8 H3: dashboard.view (bukan project.viewAll lagi, F-99 rekonsiliasi
        // H2->H3) — sekarang permission itu ADA dan persis menjawab "boleh landing di
        // Dashboard", jadi dipakai langsung, bukan proxy permission lain.
        $landing = $request->user()->can('dashboard.view') ? 'dashboard' : 'tasks.my';

        return redirect()->intended(route($landing, absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

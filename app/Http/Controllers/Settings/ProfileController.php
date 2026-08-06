<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    /**
     * Update the user's profile settings.
     *
     * SUMBER: back() (bukan to_route('profile.edit')) -- halaman penuh
     * dipensiunkan, form sekarang selalu dipicu dari dalam SettingsModal
     * (client-side, bisa dibuka dari halaman APA PUN), jadi tidak ada satu
     * "halaman profil" tetap untuk dituju balik.
     *
     * BUSINESS RULE (permintaan Boss): user biasa (tanpa permission
     * `user.manage`, F-90) TIDAK BOLEH ganti email sendiri -- cuma admin/role
     * custom yang punya `user.manage` yang boleh. Field 'email' DIBUANG dari
     * data yang di-fill kalau user tidak punya izin itu -- ENFORCEMENT DI SINI
     * (server), bukan cuma disable input di UI (F-90-style: UI cuma hint,
     * penegakan asli selalu server-side, sama pola task-status-cell.tsx).
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if (! $user->can('user.manage')) {
            unset($validated['email']);
        }

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return back();
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

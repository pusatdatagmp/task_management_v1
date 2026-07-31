<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        // F-90/RBAC §D3: eager-load SEKALI per request (F-85 — preventLazyLoading
        // akan melempar exception kalau ->role->permissions diakses tanpa ini,
        // dan share() ini jalan di SETIAP request Inertia).
        $request->user()?->loadMissing('role.permissions');

        return array_merge(parent::share($request), [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            // DIPAKAI: sidebar/tombol gating di frontend (F-90) — daftar NAMA
            // permission (string[]), BUKAN boolean isAdmin/role string. Frontend
            // cek `auth.permissions.includes('task.manage')`, TIDAK PERNAH
            // hardcode nama role (F-44-style). Ini HANYA gating tampilan —
            // penegakan sebenarnya tetap di middleware `can:xxx` server-side
            // (routes/admin.php) + Gate::before (AppServiceProvider).
            'auth' => [
                'user' => $request->user(),
                'permissions' => $request->user()?->role?->permissions->pluck('permission_name')->values() ?? [],
            ],
            // DIPAKAI: badge jumlah belum dibaca di bell header (F-35 §C4) — dishare
            // di SEMUA halaman (bukan cuma halaman notifikasi) supaya badge selalu
            // tampil terkini tanpa page tiap halaman query manual.
            'unreadNotificationsCount' => $request->user()?->unreadNotifications()->count() ?? 0,
        ]);
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\DeadlineExtension;
use App\Models\Organization;
use App\Models\Scopes\PendingProposalScope;
use App\Models\Task;
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
        $request->user()?->loadMissing('role.permissions', 'organization');

        // F-169 (v1.4, keputusan Boss 2026-08-10): guest (belum login) TIDAK
        // punya organization lewat sesi -- SEBELUMNYA branding/tema jatuh ke
        // null total untuk semua halaman guest (welcome/login/error), padahal
        // proyek ini single-tenant sampai v3.0 (F-5 -- org_id disiapkan sejak
        // awal untuk marketplace nanti, tapi HARI INI cuma ada 1 organization).
        // Organization::first() dipakai HANYA sebagai fallback guest -- user
        // login TETAP pakai relasi asli ($request->user()->organization) di
        // atas, tak pernah "nebak". RISIKO WAJIB DIREVISIT saat v3.0 multi-
        // tenant hidup (guest tak lagi bisa diasumsikan 1 organization).
        $organization = $request->user()?->organization ?? Organization::query()->oldest('id')->first();

        return array_merge(parent::share($request), [
            ...parent::share($request),
            'name' => config('app.name'),
            // Permintaan Boss (2026-08-10, F-169): label versi sistem di footer
            // sidebar -- dishare GLOBAL (bukan gated permission apa pun, sekadar
            // info build, bukan data sensitif).
            'version' => config('app.version'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            // F-142 (v1.2 DS-2): dishare GLOBAL (bukan cuma halaman Setelan) --
            // sidebar (AppLogo, NavFooter sosmed/wa) butuh ini di SETIAP halaman,
            // pola sama unreadNotificationsCount di bawah. $organization sekarang
            // (F-169) SUDAH fallback ke Organization::first() untuk guest --
            // null di sini cuma terjadi kalau BENAR-BENAR nol organization di DB
            // (instalasi belum diseed), FRONTEND baru render default TEMPO saat itu.
            'branding' => $organization ? [
                'company_name' => $organization->company_name,
                'address' => $organization->address,
                'wa_number' => $organization->wa_number,
                'facebook_url' => $organization->facebook_url,
                'instagram_url' => $organization->instagram_url,
                'linkedin_url' => $organization->linkedin_url,
                'logo_url' => $organization->logoUrl(),
            ] : null,
            // F-143 (v1.2 DS-3): dishare GLOBAL -- app.tsx apply token override
            // SEKALI saat boot (pola sama initializeTheme() appearance, F-144
            // "editor ubah token, komponen mewarisi"), bukan cuma halaman Setelan.
            // Guest sekarang (F-169) ikut $organization fallback di atas --
            // null di sini murni berarti org itu belum pernah kustom tema (bukan
            // "belum login"), FRONTEND diam (CSS default TEMPO dari app.css, F-145).
            'theme' => $organization?->theme_config,
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
            // Permintaan Boss (2026-08-22): badge jumlah tugas di menu sidebar
            // "Tugas Saya", gaya SAMA icon notifikasi (pill merah). Filter IDENTIK
            // TaskController::myTasks() (assignee = user login, status belum
            // selesai, F-44 flag) -- SATU SUMBER supaya angka badge selalu sama
            // dengan jumlah baris yang benar-benar tampil begitu diklik. Dishare
            // GLOBAL (pola sama unreadNotificationsCount) karena sidebar dirender
            // di SETIAP halaman, bukan cuma halaman /my-tasks.
            'myTasksCount' => $request->user()
                ? Task::whereHas('assignees', fn ($q) => $q->whereKey($request->user()->id))
                    ->whereHas('taskStatus', fn ($q) => $q->where('is_completed', false))
                    ->count()
                : 0,
            // Permintaan Boss (2026-08-22): indikator "Review" di header, sebelah
            // bell notifikasi -- LIVE COUNT tugas berstatus Review SEKARANG (F-44
            // flag is_review, BUKAN riwayat notifikasi tersimpan/dihitung), pola
            // SAMA myTasksCount di atas. Digerbangi DUA permission sekaligus:
            // `task.approve` (SATU-SATUNYA permission yang bisa approve/reject
            // task keluar dari Review, lihat TaskController::approve()/reject())
            // DAN `project.viewAll` (link klik mengarah ke route('tasks.all')
            // yang JUGA digerbangi permission itu, F-90 -- kalau cuma task.approve
            // tanpa project.viewAll, badge akan tampil tapi link-nya 403 begitu
            // diklik; DUA syarat sekaligus mencegah link mati). Org-wide (BUKAN
            // scoped ke proyek tertentu) -- role dgn task.approve lazimnya
            // reviewer lintas proyek, sama seperti halaman "Perpanjangan".
            // REVISI 2026-08-22 (permintaan Boss): NULL (bukan 0) untuk yang
            // TIDAK berwenang -- frontend (review-notice.tsx) SEKARANG tetap
            // menampilkan "Review · 0" untuk yang BERWENANG tapi nol tugas
            // Review, TAPI harus tetap sembunyi total untuk member biasa (yang
            // dulunya juga dapat 0, alasan beda: bukan "nol review", tapi
            // "tidak berhak lihat"). null vs 0 membedakan dua alasan itu.
            'reviewTasksCount' => ($request->user()?->can('task.approve') && $request->user()?->can('project.viewAll'))
                ? Task::whereHas('taskStatus', fn ($q) => $q->where('is_completed', false)->where('is_review', true))->count()
                : null,
            // Permintaan Boss (2026-08-22): badge jumlah di menu sidebar
            // "Perpanjangan" (item admin, gated extension.approve F-170 — dulu
            // task.approve, app-sidebar.tsx), pola SAMA myTasksCount. Filter
            // IDENTIK DeadlineExtensionController::index() (status='pending') --
            // SATU SUMBER supaya angka badge selalu sama dengan jumlah baris yang
            // tampil begitu diklik. 0 untuk yang tidak berwenang (menu item-nya
            // sendiri sudah tidak dirender di sidebar buat mereka, jadi 0-vs-null
            // tidak relevan di sini).
            'pendingExtensionsCount' => $request->user()?->can('extension.approve')
                ? DeadlineExtension::where('status', 'pending')->count()
                : 0,
            // F-186 (keputusan Boss 2026-09-04): badge sidebar "Pengajuan Tugas"
            // (admin) -- SATU SUMBER dengan TaskProposalController::index(),
            // pola SAMA pendingExtensionsCount. withoutGlobalScope WAJIB --
            // PendingProposalScope (Task::booted()) menyembunyikan baris
            // 'pending' dari query default, badge ini JUSTRU perlu menghitungnya.
            'pendingTaskProposalsCount' => $request->user()?->can('task.approve')
                ? Task::withoutGlobalScope(PendingProposalScope::class)->where('proposal_status', 'pending')->count()
                : 0,
        ]);
    }
}

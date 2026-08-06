# SPEK — TASK AUTOMATION ENGINE (Dynamic Event & Condition-Driven)
### v1.3 · dikunci bersama Boss 2026-07-28 · findings F-151..F-154

> Evolusi dari recurring engine lama (`GenerateRecurringTasksCommand`, F-100/101/102/104/61).
> Dokumen KONSEP + SKEMA untuk direview Boss SEBELUM implementasi. Stack: Laravel 12 + MySQL 8,
> Asia/Jakarta (F-69).

---

## 1. KONSEP INTI

Engine lama: kalender kaku (harian/mingguan/bulanan pada tanggal tetap).
Engine baru: **interval + kondisi**. Dua sumbu baru:
- **Interval + `last_generated_date`** → tahan miss-run (self-heal).
- **Anchor Strategy** → A (time-based, jalan sesuai jadwal) atau B (completion-based, tunggu yang sebelumnya selesai).

---

## 2. SKEMA DATABASE (aditif — F-121)

### `task_templates` (tambah kolom)
| kolom | tipe | fungsi |
|---|---|---|
| `anchor_strategy` | enum('time_based','completion_based') default 'time_based' | Opsi A / B |
| `interval_value` | int unsigned | besar interval (mis. 1, 2, 7) |
| `interval_unit` | enum('day','week','month') | satuan interval |
| `last_generated_date` | date nullable | untuk Time-Delta check |
| `is_active` | bool default true | template Active (kalau belum ada) |
| `blocked_since` | date nullable | kapan mulai ter-block (Opsi B deadlock) |
| `last_block_notified_at` | datetime nullable | anti-spam notif block |

*(Checklist template items sudah ada dari H2 — disalin ke instance saat generate, F-123.)*

### `tasks` (generated) — linkage untuk Opsi B
| kolom | fungsi |
|---|---|
| `template_id` | FK ke task_templates (kalau belum ada) — untuk cari task periode sebelumnya |
| `period_key` | string/int penanda periode (mis. '2026-07-28' atau nomor urut) — identifikasi "periode sebelumnya" & idempotency |

🔴 **Idempotency (F-61):** unik `(template_id, period_key)` → cron dobel tak generate ganda.

### `holidays` — REUSE (F-43). Tidak ada kolom baru.

### Notifikasi block (Opsi B) — REUSE sistem notif, kategori KOLABORASI (F-114), bukan lifecycle trigger.

---

## 3. ALUR ENGINE (per run, 00:01 WIB)

```
CRON 00:01 Asia/Jakarta (Laravel scheduler ->dailyAt('00:01')->timezone('Asia/Jakarta'))
│
├─ now_WIB = Carbon::now('Asia/Jakarta')  🔴 WIB, BUKAN UTC (F-69)
│
├─ FETCH task_templates WHERE is_active = true   [chunkById(100), F-85]
├─ PRELOAD holidays window SEKALI (F-43)          [hindari query per-template]
├─ PRELOAD status task periode-sebelumnya semua template [1 query, Opsi B]
│
└─ untuk tiap template:
   │
   ├─ [TIME-DELTA] delta = DateDiff(now_WIB, last_generated_date)
   │     delta < interval_value → SKIP "belum waktunya"   ┐ log
   │
   ├─ [ANCHOR STRATEGY]
   │   ├─ A (time_based)       → lanjut ke Holiday Check
   │   └─ B (completion_based) → cek status task periode sebelumnya:
   │         TODO/IN-PROGRESS/REVIEW → SKIP "sebelumnya belum selesai"
   │                                   → set blocked_since (kalau belum), NOTIF admin bila ambang (F-154)
   │         SELESAI            → clear blocked_since → lanjut Holiday Check
   │
   ├─ [HOLIDAY & WEEKEND SHIFT]  (F-153: SEMUA tipe termasuk harian digeser)
   │     target_date = now_WIB
   │     while target_date libur/weekend: target_date = hari berikutnya   (Forward-Shift, F-43)
   │     → GENERATE task due target_date
   │
   ├─ [GENERATE]
   │     insert tasks (template_id, period_key, due=target_date, salin checklist F-123)
   │     🔴 cek idempotency (template_id, period_key) dulu — kalau ada, skip (F-61)
   │
   └─ [STATE MUTATION]
         template.last_generated_date = now_WIB   (catch-up SATU, F-152)
         template.blocked_since = null (kalau tadi Opsi B lolos)
```

---

## 4. EXCEPTION HANDLING (jawaban 3 pertanyaan Boss)

**A. Cron miss-run (F-152):** delta-based → self-heal. Server mati 3 hari → run berikutnya delta≥interval → generate **SATU** (bukan backfill semua), set last_generated=today. Command juga bisa dijalankan **manual** kapan saja sebagai sweep. Idempotency (F-61) jaga dari dobel.

**B. Timezone (F-69):** scheduler `->timezone('Asia/Jakarta')`. `now_WIB` eksplisit `Carbon::now('Asia/Jakarta')` untuk delta & tanggal — JANGAN tanggal UTC (00:01 WIB = 17:01 UTC hari sebelumnya → salah hari). Indonesia tanpa DST → nol edge-case.

**C. Bulk (F-85):** `chunkById`, preload holidays + status-sebelumnya SEKALI, bulk-update last_generated, transaksi per chunk. **Log tiap keputusan** (GENERATE / SKIP:alasan / SHIFT) → observability.

---

## 5. INTERAKSI FINDING (yang berubah)

| Finding | Status | Perubahan |
|---|---|---|
| **F-100** no-backfill | 🔄 DIGANTI F-152 | today-only → delta-based catch-up-satu (self-heal) |
| **F-102** harian=skip di libur | 🔄 DIUBAH F-153 | harian sekarang IKUT geser (Boss) |
| **F-101** clamp bulanan | ✅ tetap | interval_unit=month tetap clamp akhir bulan |
| **F-104** lookback | ✅ diserap | last_generated menggantikan lookback 2-periode |
| **F-61** idempotency | ✅ tetap wajib | kunci (template_id, period_key) |
| **F-69** timezone | ✅ tetap | WIB eksplisit |
| **F-43** business-day/holiday | ✅ reuse | Forward-Shift pakai logika ini |
| **F-123** checklist template | ✅ tetap | disalin ke instance |
| **F-114** notif kolaborasi | ✅ reuse | notif block Opsi B |

---

## 6. OBSERVABILITY (wajib untuk engine kondisional)

Tiap run + tiap template → log terstruktur: `template_id, keputusan (GENERATE/SKIP-belum-waktunya/
SKIP-sebelumnya-belum-selesai/SHIFT-libur), target_date, delta`. Tanpa ini, debug "kenapa task X tak
muncul" jadi menebak. Pertimbangkan tabel `automation_run_log` atau Laravel log channel khusus.

---

## 7. YANG PERLU DIPUTUSKAN SEBELUM IMPLEMENTASI (untuk Boss review)

1. **`period_key` format** — tanggal (`2026-07-28`) atau nomor urut periode? (Jarvis usul: tanggal periode terjadwal, konsisten & mudah baca.)
2. **Ambang notif block (F-154)** — notif setelah ter-block berapa lama? (Usul: notif pertama saat block terdeteksi, lalu tak spam — cukup sekali sampai selesai.)
3. **`automation_run_log`** — tabel DB (queryable, bisa ditampilkan di UI) atau cukup Laravel log file? (Usul: tabel, supaya kelak ada UI "riwayat automation".)
4. **Migrasi template lama** — template existing dapat `anchor_strategy='time_based'`, `interval` diturunkan dari recurrence type lama. Perlu data migration.

---

*Setelah Boss review spek ini + putuskan §7, Jarvis susun prompt implementasi (skema → engine → test) untuk Claude Code, dengan commit wajib (F-149).*

---

## 8. ARSITEKTUR EXTENSIBLE (F-158) — inti "mudah dikembangkan kedepannya"

> Kunci agar engine ini tumbuh tanpa ditulis ulang: **pisahkan APA yang memicu, APA syaratnya,
> dan APA aksinya.** Empat lapisan, tiap lapisan bisa ditambah tanpa menyentuh yang lain.

```
[ TRIGGER ] ──► [ CONDITION / GUARD CHAIN ] ──► [ RESOLVER ] ──► [ ACTION ]
  apa yang        boleh generate?                kapan/di mana?    lakukan
  memicu          (rantai syarat berurutan)      (tanggal target)
```

### 8.1 TRIGGER (apa yang memicu evaluasi) — trigger-agnostic
- `CronTrigger` — harian 00:01 WIB (utama).
- `ManualTrigger` — sweep manual kapan saja (recovery miss-run).
- 🔮 **future** `EventTrigger` — mis. "saat task X selesai, evaluasi template dependen". Inilah
  bagian "Event-Driven" di nama engine — pipeline di bawahnya SAMA, cuma pemicunya beda.
- Trigger cuma menghasilkan "evaluasi template-template ini sekarang". Tak tahu soal syarat/aksi.

### 8.2 CONDITION = GUARD CHAIN (boleh generate?) — komposabel
Rantai guard berurutan; tiap guard kembalikan **Pass** atau **Skip(reason)**. Skip pertama menghentikan.
- `TimeDeltaGuard` — delta >= interval?
- `AnchorStrategyGuard` — delegasi ke Strategy (A/B, lihat 8.3).
- 🔮 **future** slot: `QuotaGuard` (maks N task/periode), `DateWindowGuard` (hanya tanggal 1-25),
  `DependencyGuard` (template lain harus selesai dulu). **Tambah syarat baru = tambah 1 Guard, sisip ke rantai. NOL rewrite.**

### 8.3 ANCHOR = STRATEGY PATTERN (registered by key)
```
interface AnchorStrategy { evaluate(template, ctx): Decision }
  TimeBasedStrategy        (Opsi A) — selalu lanjut
  CompletionBasedStrategy  (Opsi B) — cek task periode sebelumnya SELESAI?
  🔮 future: EventBasedStrategy, DependencyGraphStrategy
```
`template.anchor_strategy` (kolom) = key → resolver strategi. **Tambah anchor baru = tambah 1 class + daftar. NOL if-else membengkak.**

### 8.4 RESOLVER (kapan/di mana)
- `HolidayShiftResolver` — Forward-Shift semua tipe (F-153) ke hari kerja (reuse F-43).
- 🔮 future: `TimeWindowResolver` (jam kerja spesifik), `LoadBalanceResolver` (geser ke user paling longgar).

### 8.5 ACTION + DECISION object (observability)
Tiap evaluasi template menghasilkan **Decision**: `action` (GENERATE | SKIP | SHIFT), `reason`,
`target_date`, `meta`. ACTION mengeksekusi (GenerateTask + MutateState) HANYA bila GENERATE.
🔴 **Decision di-LOG ke `automation_run_log`** — ini backbone debug + audit + future UI.

### 8.6 KENAPA INI EXTENSIBLE
| Mau tambah | Cukup | Tak perlu |
|---|---|---|
| Syarat baru (mis. kuota) | 1 Guard class + sisip rantai | sentuh engine |
| Tipe anchor baru | 1 Strategy class + daftar key | if-else baru |
| Pemicu baru (event) | 1 Trigger + reuse pipeline | tulis ulang kondisi/aksi |
| Cara geser baru | 1 Resolver | ubah generate |
Filosofi sama dgn RBAC data-driven (F-90): perilaku = DATA + komponen kecil yang bisa dicolok,
bukan satu fungsi raksasa.

---

## 9. §7 DIRESOLUSI (F-159)
1. **`period_key` = TANGGAL periode terjadwal** (mis. `2026-07-28`) — readable + kunci idempotency `(template_id, period_key)`.
2. **Notif block = SEKALI** saat `blocked_since` di-set; clear saat task sebelumnya selesai. Anti-spam.
3. **`automation_run_log` = TABEL DB** (`id, template_id, run_at, action, reason, target_date, delta, meta`) → queryable, jadi UI "riwayat automation" kelak.
4. **Migrasi template lama** → `anchor_strategy='time_based'`, `interval` diturunkan dari `recurrence_type` lama (daily=1day, weekly=1week, monthly=1month), `last_generated_date` dari task terakhir atau null.

---

## 10. RENCANA IMPLEMENTASI (urutan, tiap = 1 sesi + commit F-149)
- **AE-1** Skema: kolom template + tasks period_key + `automation_run_log` + migrasi data template lama.
- **AE-2** Pipeline inti: Trigger/Guard/Strategy/Resolver/Action + Decision + log. Command `automation:run` + scheduler 00:01 WIB.
- **AE-2b** 🖥️ **FORM KONFIGURASI TEMPLATE** — halaman/modal tempat Boss atur SENDIRI: interval bebas ("tiap N hari/minggu/bulan"), anchor strategy (A/B/C), date-window, quota. INILAH yang membuat perulangan "dinamis, Boss atur sesuai kebutuhan". Validasi + preview jadwal berikutnya.
- **AE-3** Opsi B completion + notif block (F-154) + idempotency (F-61) + test intens (miss-run, holiday-shift, deadlock) + **CUTOVER** ganti engine lama (F-160).
- **AE-4** (opsional) UI "riwayat automation" baca run_log.

# Verifikasi RT — Tahap 1 (Akun RT + Verifikasi Domisili + Antrean Reviu) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Akun peran `rt` bisa dibuat Dinkes, login ke satu halaman `/rt/verifikasi`, menandai keberadaan/domisili anak di RT-nya (dan mengklaim anak sekelurahan yang belum ber-RT); usulan itu masuk antrean reviu puskesmas/Dinkes dan, bila disetujui, memperbarui tag verifikasi (serta `anak.id_rt` untuk klaim).

**Architecture:** Satu service (`VerifikasiRtService`) memegang seluruh aturan cakupan, usulan, reviu, dan denormalisasi ke kolom `anak.verif_rt_*`; dua controller tipis (RT: JSON untuk halaman; admin: halaman server-rendered) hanya memanggilnya. Halaman RT adalah blade mandiri (tanpa sidebar admin) yang memuat data lewat JSON; antrean reviu memakai layout admin yang ada.

**Tech Stack:** Laravel 12, PHP 8.4, MySQL 8, Blade + jQuery/vanilla JS, PHPUnit 11 (`RefreshDatabase`, DB `sirindu_testing`).

**Spec:** `docs/superpowers/specs/2026-09-14-verifikasi-rt-design.md` (§2, §3.1 `verifikasi_anak`, §3.2, §4 tab 1–2, §6.1–6.2, §7 tidak termasuk — itu T4, §10 a–d & g).

## Global Constraints

- **Dasbor Operasi Timbang (`/admin/timbang-dashboard`) tidak boleh berubah**: tak ada kolom/badge/filter/angka baru di sana. Semua kueri di `TimbangDashboardController` tidak disentuh.
- Akun RT: `users.role='rt'`, `users.type=2`, `users.id_rt` wajib. Akun RT harus mendapat **403** di semua rute `/admin/*`.
- RT tidak pernah menulis langsung ke `anak`; hanya `VerifikasiRtService` yang menulis `verif_rt_*`/`id_rt`.
- Menulis kolom `anak.verif_rt_*` **tidak boleh menyentuh `anak.updated_at`** (heuristik `updated_at == created_at` dipakai `CapilDedupService::sigiziUntouched()`): pakai `DB::table('anak')->where('id', …)->update([...])`, bukan Eloquent.
- Status verifikasi: `berdomisili | pindah | meninggal | tidak_dikenal | bukan_rt_ini`. Reviu: `diusulkan | disetujui | ditolak`.
- `bukan_rt_ini` tidak pernah mengubah `anak.verif_rt_*`.
- Tes: jangan jalankan dua proses PHPUnit bersamaan (DB `sirindu_testing` dipakai bersama). Jalankan PHP lewat path penuh: `D:\apps\laragon\bin\php\php-8.4.7-Win32-vs17-x64\php.exe` (di bash: `/d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe`). Tiap proses PHPUnit memigrasi ulang DB (~90 dtk) — jalankan beberapa file tes dalam satu perintah.
- Bahasa UI & komentar kode: Indonesia, mengikuti gaya berkas sekitarnya.
- Commit lokal saja; **jangan push**.

---

## File Structure

| Berkas | Tanggung jawab |
|---|---|
| `database/migrations/2026_09_15_000001_add_id_rt_to_users_table.php` | kolom `users.id_rt` |
| `database/migrations/2026_09_15_000002_create_verifikasi_anak_table.php` | tabel riwayat usulan RT |
| `database/migrations/2026_09_15_000003_add_verif_rt_to_anak_table.php` | kolom denormalisasi `anak.verif_rt_status/reviu/at` |
| `app/Models/VerifikasiAnak.php` | model + relasi + konstanta status/reviu |
| `app/Models/User.php` (modify) | `id_rt` fillable, `rt()`, `isRt()`, `berandaRoute()` |
| `app/Services/VerifikasiRtService.php` | cakupan, usulan, reviu, denormalisasi, progres |
| `app/Http/Controllers/Rt/VerifikasiRtController.php` | halaman RT + JSON |
| `app/Http/Controllers/VerifikasiRtReviuController.php` | antrean reviu admin |
| `resources/views/rt/verifikasi.blade.php` | halaman RT mandiri |
| `resources/views/admin/verifikasi-rt/index.blade.php` | antrean reviu (layout admin) |
| `app/Http/Requests/Admin/User/storeUserRequest.php` (modify) | role `rt` + `id_rt` |
| `app/Repositories/Admin/User/UserRepository.php` (modify) | simpan `id_rt`, turunkan kel/kec, `type=2` |
| `resources/views/admin/user/create.blade.php`, `edit.blade.php` (modify) | pilihan role RT + cascade RT |
| `app/Http/Controllers/Auth/LoginController.php`, `bootstrap/app.php`, `resources/views/public/timbang.blade.php` (modify) | arah beranda per peran |
| `routes/web.php` (modify) | grup `/rt`, rute reviu admin |
| `resources/views/vendor/admin/layouts/partials/leftsidebar.blade.php` (modify) | menu "Verifikasi RT" |
| `tests/Feature/VerifikasiRt/*.php` | tes fitur per tugas |
| `tests/Feature/TimbangDashboardTerkunciTest.php` (modify) | penjaga regresi OT |

---

### Task 1: Skema & model dasar

**Files:**
- Create: `database/migrations/2026_09_15_000001_add_id_rt_to_users_table.php`
- Create: `database/migrations/2026_09_15_000002_create_verifikasi_anak_table.php`
- Create: `database/migrations/2026_09_15_000003_add_verif_rt_to_anak_table.php`
- Create: `app/Models/VerifikasiAnak.php`
- Modify: `app/Models/User.php:24-40` (fillable) dan setelah `puskesmas()` (~baris 61)
- Test: `tests/Feature/VerifikasiRt/SkemaVerifikasiRtTest.php`

**Interfaces:**
- Produces: `VerifikasiAnak` (Eloquent, tabel `verifikasi_anak`, konstanta `STATUS`, `REVIU`; relasi `anak()`, `rt()`, `pengusul()`, `peninjau()`); `User::rt(): BelongsTo`, `User::isRt(): bool`, `User::berandaRoute(): string` (`'rt.verifikasi'` untuk RT, selain itu `'admin.home'`); kolom `anak.verif_rt_status`, `verif_rt_reviu`, `verif_rt_at`.

- [ ] **Step 1: Tulis tes skema yang gagal**

```php
<?php
// tests/Feature/VerifikasiRt/SkemaVerifikasiRtTest.php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Anak;
use App\Models\Rt;
use App\Models\User;
use App\Models\VerifikasiAnak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SkemaVerifikasiRtTest extends TestCase
{
    use RefreshDatabase;

    public function test_kolom_dan_tabel_baru_ada(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'id_rt'));
        $this->assertTrue(Schema::hasColumns('anak', ['verif_rt_status', 'verif_rt_reviu', 'verif_rt_at']));
        $this->assertTrue(Schema::hasColumns('verifikasi_anak', [
            'id_anak', 'id_rt', 'status', 'klaim_id_rt', 'catatan',
            'diusulkan_oleh', 'diusulkan_at', 'reviu', 'ditinjau_oleh', 'ditinjau_at', 'catatan_reviu',
        ]));
    }

    public function test_user_rt_mengenal_rt_dan_berandanya(): void
    {
        $rt   = Rt::factory()->create();
        $user = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => $rt->id, 'id_kel' => $rt->id_kelurahan]);

        $this->assertTrue($user->isRt());
        $this->assertSame($rt->id, $user->rt->id);
        $this->assertSame('rt.verifikasi', $user->berandaRoute());

        $super = User::factory()->create(['type' => 0]);
        $this->assertFalse($super->isRt());
        $this->assertSame('admin.home', $super->berandaRoute());
    }

    public function test_verifikasi_anak_menyimpan_relasi(): void
    {
        $rt   = Rt::factory()->create();
        $user = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => $rt->id]);
        $anak = Anak::create([
            'nama' => 'Anak A', 'nik' => '3201000000008001', 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'operasi_timbang', 'id_rt' => $rt->id,
        ]);

        $v = VerifikasiAnak::create([
            'id_anak' => $anak->id, 'id_rt' => $rt->id, 'status' => 'berdomisili',
            'diusulkan_oleh' => $user->id, 'diusulkan_at' => now(),
        ]);

        $this->assertSame('diusulkan', $v->fresh()->reviu);
        $this->assertSame($anak->id, $v->anak->id);
        $this->assertSame($rt->id, $v->rt->id);
        $this->assertSame($user->id, $v->pengusul->id);
        $this->assertContains('bukan_rt_ini', VerifikasiAnak::STATUS);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `/d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe vendor/bin/phpunit tests/Feature/VerifikasiRt/SkemaVerifikasiRtTest.php`
Expected: FAIL — `Failed asserting that false is true` (kolom `users.id_rt` belum ada) / `Class "App\Models\VerifikasiAnak" not found`.

- [ ] **Step 3: Tulis migrasi**

```php
<?php
// database/migrations/2026_09_15_000001_add_id_rt_to_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Akun peran RT: RT yang diverifikasi user ini (spec verifikasi RT §2). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('id_rt')->nullable()->after('id_posyandu');
            $table->foreign('id_rt')->references('id')->on('rt')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['id_rt']);
            $table->dropColumn('id_rt');
        });
    }
};
```

```php
<?php
// database/migrations/2026_09_15_000002_create_verifikasi_anak_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat usulan verifikasi RT per anak (spec §3.1). Satu baris per usulan;
 * baris terbaru yang bukan `bukan_rt_ini` = yang berlaku (didenormalisasi ke anak.verif_rt_*).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verifikasi_anak', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_anak');
            $table->unsignedBigInteger('id_rt');
            $table->enum('status', ['berdomisili', 'pindah', 'meninggal', 'tidak_dikenal', 'bukan_rt_ini']);
            $table->unsignedBigInteger('klaim_id_rt')->nullable();
            $table->text('catatan')->nullable();
            $table->unsignedBigInteger('diusulkan_oleh');
            $table->timestamp('diusulkan_at');
            $table->enum('reviu', ['diusulkan', 'disetujui', 'ditolak'])->default('diusulkan');
            $table->unsignedBigInteger('ditinjau_oleh')->nullable();
            $table->timestamp('ditinjau_at')->nullable();
            $table->text('catatan_reviu')->nullable();
            $table->timestamps();

            $table->foreign('id_anak')->references('id')->on('anak')->cascadeOnDelete();
            $table->foreign('id_rt')->references('id')->on('rt')->cascadeOnDelete();
            $table->index(['id_anak', 'id']);
            $table->index(['reviu', 'id_rt']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verifikasi_anak');
    }
};
```

```php
<?php
// database/migrations/2026_09_15_000003_add_verif_rt_to_anak_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Denormalisasi usulan verifikasi RT terakhir (spec §3.2) — untuk badge/filter tanpa join. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anak', function (Blueprint $table) {
            $table->enum('verif_rt_status', ['berdomisili', 'pindah', 'meninggal', 'tidak_dikenal'])->nullable()->after('pj_updated_at');
            $table->enum('verif_rt_reviu', ['diusulkan', 'disetujui', 'ditolak'])->nullable()->after('verif_rt_status');
            $table->timestamp('verif_rt_at')->nullable()->after('verif_rt_reviu');
            $table->index('verif_rt_status');
        });
    }

    public function down(): void
    {
        Schema::table('anak', function (Blueprint $table) {
            $table->dropIndex(['verif_rt_status']);
            $table->dropColumn(['verif_rt_status', 'verif_rt_reviu', 'verif_rt_at']);
        });
    }
};
```

- [ ] **Step 4: Tulis model `VerifikasiAnak`**

```php
<?php
// app/Models/VerifikasiAnak.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Usulan verifikasi RT untuk satu anak. Riwayat: satu baris per usulan.
 * Ditulis hanya lewat VerifikasiRtService.
 */
class VerifikasiAnak extends Model
{
    protected $table = 'verifikasi_anak';

    public const STATUS = ['berdomisili', 'pindah', 'meninggal', 'tidak_dikenal', 'bukan_rt_ini'];
    public const REVIU  = ['diusulkan', 'disetujui', 'ditolak'];

    public const LABEL_STATUS = [
        'berdomisili'   => 'Berdomisili',
        'pindah'        => 'Pindah',
        'meninggal'     => 'Meninggal',
        'tidak_dikenal' => 'Tidak dikenal',
        'bukan_rt_ini'  => 'Bukan warga RT ini',
    ];

    protected $guarded = [];

    protected $casts = [
        'diusulkan_at' => 'datetime',
        'ditinjau_at'  => 'datetime',
    ];

    public function anak(): BelongsTo
    {
        return $this->belongsTo(Anak::class, 'id_anak');
    }

    public function rt(): BelongsTo
    {
        return $this->belongsTo(Rt::class, 'id_rt');
    }

    public function pengusul(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diusulkan_oleh');
    }

    public function peninjau(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditinjau_oleh');
    }
}
```

- [ ] **Step 5: Tambah ke `User`**

Di `$fillable` (setelah `'id_posyandu',`) tambahkan `'id_rt',       // peran rt: RT yang diverifikasi`.
Setelah method `puskesmas()` tambahkan:

```php
    public function rt()
    {
        return $this->belongsTo(Rt::class, 'id_rt');
    }

    /** Peran RT: memverifikasi domisili anak di RT-nya (spec verifikasi RT §2). */
    public function isRt(): bool
    {
        return $this->role === 'rt';
    }

    /** Nama route beranda sesuai peran — dipakai login, guest-redirect, dan tombol landing. */
    public function berandaRoute(): string
    {
        return $this->isRt() ? 'rt.verifikasi' : 'admin.home';
    }
```

Tambahkan `use App\Models\Rt;` bila `User.php` belum mengimpor namespace yang sama (berada di `App\Models`, jadi cukup `Rt::class`).

- [ ] **Step 6: Jalankan tes, pastikan lulus**

Run: `/d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe vendor/bin/phpunit tests/Feature/VerifikasiRt/SkemaVerifikasiRtTest.php`
Expected: `OK (3 tests)`.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_09_15_000001_add_id_rt_to_users_table.php database/migrations/2026_09_15_000002_create_verifikasi_anak_table.php database/migrations/2026_09_15_000003_add_verif_rt_to_anak_table.php app/Models/VerifikasiAnak.php app/Models/User.php tests/Feature/VerifikasiRt/SkemaVerifikasiRtTest.php
git commit -m "feat(verifikasi-rt): skema users.id_rt, tabel verifikasi_anak, kolom verif_rt di anak"
```

---

### Task 2: Peran RT — arah beranda & penolakan rute admin

**Files:**
- Modify: `app/Http/Controllers/Auth/LoginController.php:52-66` (blok `match`)
- Modify: `bootstrap/app.php` (baris `redirectUsersTo`)
- Modify: `resources/views/public/timbang.blade.php` (tiga blok `@auth`)
- Modify: `routes/web.php` (grup `/rt` — placeholder rute `rt.verifikasi` diisi controller di Task 5; di task ini cukup closure sementara **yang diganti di Task 5**)
- Test: `tests/Feature/VerifikasiRt/AkunRtTest.php`

**Interfaces:**
- Consumes: `User::isRt()`, `User::berandaRoute()` (Task 1).
- Produces: route name `rt.verifikasi` (GET `/rt/verifikasi`), grup middleware `['auth', 'module.role:rt']`.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php
// tests/Feature/VerifikasiRt/AkunRtTest.php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Rt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AkunRtTest extends TestCase
{
    use RefreshDatabase;

    private function userRt(): User
    {
        $rt = Rt::factory()->create();
        return User::factory()->create([
            'type' => 2, 'role' => 'rt', 'id_rt' => $rt->id,
            'id_kel' => $rt->id_kelurahan, 'password' => bcrypt('rahasia123'),
        ]);
    }

    public function test_login_rt_diarahkan_ke_halaman_verifikasi(): void
    {
        config(['services.recaptcha.enabled' => false]);
        $user = $this->userRt();

        $this->post('/login', ['email' => $user->email, 'password' => 'rahasia123'])
            ->assertRedirect(route('rt.verifikasi'));
    }

    public function test_rt_yang_sudah_login_membuka_login_diarahkan_ke_verifikasi(): void
    {
        $this->actingAs($this->userRt())->get('/login')->assertRedirect(route('rt.verifikasi'));
    }

    public function test_rt_ditolak_dari_rute_admin(): void
    {
        $user = $this->userRt();
        $this->actingAs($user)->get('/admin/home')->assertForbidden();
        $this->actingAs($user)->get('/admin/timbang-dashboard')->assertForbidden();
        $this->actingAs($user)->getJson(route('admin.timbang.daftar'))->assertForbidden();
    }

    public function test_non_rt_ditolak_dari_halaman_rt(): void
    {
        $faskes = User::factory()->create(['type' => 1, 'role' => 'imunisasi_faskes']);
        $this->actingAs($faskes)->get(route('rt.verifikasi'))->assertForbidden();
        $this->get(route('rt.verifikasi'))->assertRedirect(route('login'));
    }

    public function test_landing_menawarkan_halaman_verifikasi_untuk_rt(): void
    {
        $this->actingAs($this->userRt())->get('/')
            ->assertOk()
            ->assertSee(route('rt.verifikasi'))
            ->assertDontSee(route('admin.home'));
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `/d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe vendor/bin/phpunit tests/Feature/VerifikasiRt/AkunRtTest.php`
Expected: FAIL — `Route [rt.verifikasi] not defined.`

- [ ] **Step 3: Rute sementara + middleware**

Di `routes/web.php`, setelah blok `Auth::routes();` dan grup `timbang-publik`, tambahkan:

```php
/*------------------------------------------
--------------------------------------------
Peran RT — verifikasi domisili warga (spec verifikasi RT §4)
--------------------------------------------
--------------------------------------------*/
Route::middleware(['auth', 'module.role:rt'])->prefix('rt')->name('rt.')->group(function () {
    Route::get('verifikasi', fn () => 'verifikasi rt')->name('verifikasi'); // diganti controller di Task 5
});
```

- [ ] **Step 4: Arah beranda per peran**

`app/Http/Controllers/Auth/LoginController.php` — di dalam `match(true)` sisipkan **paling atas**:

```php
                // RT: satu halaman verifikasi warga, tanpa akses admin
                $user->isRt()                  => redirect()->route('rt.verifikasi'),
```

`bootstrap/app.php` — ganti baris `redirectUsersTo(...)` menjadi:

```php
        $middleware->redirectUsersTo(fn () => route(auth()->user()->berandaRoute()));
```

`resources/views/public/timbang.blade.php` — di ketiga blok `@auth` ganti `route('admin.home')` menjadi `route(auth()->user()->berandaRoute())` (topbar, hero, footer).

- [ ] **Step 5: Jalankan tes, pastikan lulus (termasuk tes login yang sudah ada)**

Run: `/d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe vendor/bin/phpunit tests/Feature/VerifikasiRt/AkunRtTest.php tests/Feature/LoginSudahMasukTest.php tests/Feature/LandingPublikTest.php`
Expected: semua lulus.

- [ ] **Step 6: Commit**

```bash
git add routes/web.php app/Http/Controllers/Auth/LoginController.php bootstrap/app.php resources/views/public/timbang.blade.php tests/Feature/VerifikasiRt/AkunRtTest.php
git commit -m "feat(verifikasi-rt): peran rt diarahkan ke /rt/verifikasi dan ditolak dari admin"
```

---

### Task 3: Pembuatan akun RT di manajemen user

**Files:**
- Modify: `app/Http/Requests/Admin/User/storeUserRequest.php` (rules)
- Modify: `app/Repositories/Admin/User/UserRepository.php` (`typeFromRole`, `storeUser`, `updateUser`)
- Modify: `resources/views/admin/user/create.blade.php` (opsi role, grup RT, JS)
- Modify: `resources/views/admin/user/edit.blade.php` (opsi role, grup RT)
- Test: `tests/Feature/VerifikasiRt/BuatAkunRtTest.php`

**Interfaces:**
- Consumes: `Rt` (`id_kelurahan`, `id_posyandu`), `Kelurahan.id_kecamatan`, endpoint JSON `admin.getRtByKelAnak` (`/admin/get-rt-by-kel-anak/{id_kel}` → `{id: name}`), `admin.getKelAnak` (`/admin/get-kel-dasar-anak/{id_kec}`).
- Produces: POST `super.admin.storeUser` dengan `role=rt&id_rt=…` membuat user `type=2`, `id_kel`/`id_kec` diturunkan dari RT.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php
// tests/Feature/VerifikasiRt/BuatAkunRtTest.php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuatAkunRtTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_membuat_akun_rt_dengan_wilayah_turunan(): void
    {
        $super = User::factory()->create(['type' => 0, 'role' => 'superadmin']);
        $rt    = Rt::factory()->create();
        $kel   = Kelurahan::find($rt->id_kelurahan);

        $this->actingAs($super)->post(route('super.admin.storeUser'), [
            'name' => 'Ketua RT 05', 'email' => 'rt05@sirindu.go.id', 'role' => 'rt', 'id_rt' => $rt->id,
        ])->assertRedirect(route('super.admin.user'));

        $user = User::where('email', 'rt05@sirindu.go.id')->firstOrFail();
        $this->assertSame('rt', $user->role);
        $this->assertSame(2, (int) $user->type);
        $this->assertSame($rt->id, (int) $user->id_rt);
        $this->assertSame($kel->id, (int) $user->id_kel);
        $this->assertSame($kel->id_kecamatan, (int) $user->id_kec);
        $this->assertNull($user->faskes_type);
    }

    public function test_role_rt_tanpa_id_rt_ditolak(): void
    {
        $super = User::factory()->create(['type' => 0, 'role' => 'superadmin']);

        $this->actingAs($super)->from(route('super.admin.user'))->post(route('super.admin.storeUser'), [
            'name' => 'Ketua RT', 'email' => 'rt@sirindu.go.id', 'role' => 'rt',
        ])->assertSessionHasErrors('id_rt');
    }

    public function test_update_akun_rt_memindahkan_rt_dan_wilayah(): void
    {
        $super = User::factory()->create(['type' => 0, 'role' => 'superadmin']);
        $rtA   = Rt::factory()->create();
        $rtB   = Rt::factory()->create();
        $user  = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => $rtA->id, 'id_kel' => $rtA->id_kelurahan]);

        $this->actingAs($super)->put(route('super.admin.updateUser', $user->id), [
            'name' => $user->name, 'email' => $user->email, 'role' => 'rt', 'id_rt' => $rtB->id,
        ])->assertRedirect(route('super.admin.user'));

        $user->refresh();
        $this->assertSame($rtB->id, (int) $user->id_rt);
        $this->assertSame($rtB->id_kelurahan, (int) $user->id_kel);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `/d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe vendor/bin/phpunit tests/Feature/VerifikasiRt/BuatAkunRtTest.php`
Expected: FAIL — tes pertama: `role.in` menolak (`assertRedirect` gagal karena kembali dengan error validasi) / user tidak ditemukan.

- [ ] **Step 3: Validasi**

`storeUserRequest::rules()` — ubah baris `role` dan tambahkan `id_rt`:

```php
            'role'         => 'required|in:superadmin,imunisasi_faskes,surveilans_puskesmas,surveilans_rs,rt',
            // Peran RT: wajib memilih RT; kelurahan/kecamatan diturunkan dari RT (spec verifikasi RT §2)
            'id_rt'        => [Rule::requiredIf($role === 'rt'), 'nullable', 'integer', 'exists:rt,id'],
```

Di `messages()` tambahkan `'id_rt.required' => 'RT wajib dipilih untuk peran RT.', 'id_rt.exists' => 'RT tidak ditemukan.',`.

- [ ] **Step 4: Repository**

`UserRepository.php`:

```php
    private function typeFromRole(string $role): int
    {
        // 0 = superadmin, 1 = admin/faskes (akses /admin), 2 = user biasa (peran rt: TIDAK boleh /admin)
        return match ($role) {
            'superadmin' => 0,
            'rt'         => 2,
            default      => 1,
        };
    }

    /** Peran RT: wilayah diturunkan dari RT yang dipilih, bukan dari isian form. */
    private function wilayahDariRt($request): array
    {
        if ($request->role !== 'rt' || !$request->id_rt) {
            return [];
        }
        $rt  = Rt::findOrFail($request->id_rt);
        $kel = Kelurahan::find($rt->id_kelurahan);

        return [
            'id_rt'       => $rt->id,
            'id_kel'      => $rt->id_kelurahan,
            'id_kec'      => $kel?->id_kecamatan,
            'id_posyandu' => $rt->id_posyandu,
        ];
    }
```

Tambahkan `use App\Models\Rt; use App\Models\Kelurahan;` di atas berkas. Di `storeUser()`, ubah pemanggilan `User::create([...])` menjadi `User::create(array_merge([...isi lama...], $this->wilayahDariRt($request)));`. Di `updateUser()`, sebelum `$user->update($data);` tambahkan `$data = array_merge($data, $this->wilayahDariRt($request));` dan untuk peran bukan RT pastikan `id_rt` dikosongkan: tambahkan ke `$data` awal baris `'id_rt' => null,` (di atas `'id_puskesmas'`) — `wilayahDariRt` akan menimpanya untuk peran rt.

- [ ] **Step 5: Form create**

`resources/views/admin/user/create.blade.php` — di `<select name="role">` tambahkan `<option value="rt">RT (verifikasi warga)</option>`. Tepat sebelum `<div class="row mt-2">` (cascade kec/kel) sisipkan grup RT:

```blade
                    {{-- Peran RT: pilih RT lewat cascade Kec → Kel → RT; wilayah user diturunkan dari RT --}}
                    <div class="form-group d-none" id="create_rt_group">
                        <label for="create_rt">RT</label>
                        <select id="create_rt" name="id_rt" class="form-control">
                            <option value="">== Pilih Kelurahan dulu ==</option>
                        </select>
                        @error('id_rt') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
```

Di JS `updateCreateFaskesVisibility()` tambahkan baris pertama `document.getElementById('create_rt_group').classList.add('d-none');` dan cabang `else if (role === 'rt') { document.getElementById('create_rt_group').classList.remove('d-none'); }`. Di handler `$('#create_kec').on('change')` tak ada perubahan; tambahkan handler kelurahan:

```javascript
    $('#create_kel').on('change', function () {
        var id = $(this).val();
        $('#create_rt').empty().append('<option value="">== Pilih RT ==</option>');
        if (!id) { return; }
        $.getJSON('{{ url("admin/get-rt-by-kel-anak") }}' + '/' + id, function (response) {
            $.each(response, function (rtId, name) { $('#create_rt').append(new Option(name, rtId)); });
        });
    });
```

- [ ] **Step 6: Form edit**

`resources/views/admin/user/edit.blade.php` — tambahkan `<option value="rt" {{ $user->role === 'rt' ? 'selected' : '' }}>RT (verifikasi warga)</option>` ke select role. Di dalam blok "Ganti Lokasi", setelah kolom Posyandu, tambahkan:

```blade
        <div class="col-md-3 col-sm-12 mt-2" id="edit_rt_group" style="{{ $user->role === 'rt' ? '' : 'display:none' }}">
            <div class="form-group">
                <label>RT (peran RT)</label>
                <select id="rtx" name="id_rt" class="form-control">
                    <option value="">== Pilih Kelurahan dulu ==</option>
                    @foreach (\App\Models\Rt::where('id_kelurahan', $user->id_kel)->orderBy('name')->get() as $rt)
                    <option value="{{ $rt->id }}" {{ (int) $user->id_rt === $rt->id ? 'selected' : '' }}>{{ $rt->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
```

Di `@section('custom_scripts')` tambahkan:

```javascript
    document.getElementById('edit_role').addEventListener('change', function () {
        document.getElementById('edit_rt_group').style.display = this.value === 'rt' ? '' : 'none';
    });
    $('#kelx').on('change', function () {
        var id = $(this).val();
        $('#rtx').empty().append('<option value="">== Pilih RT ==</option>');
        if (!id) { return; }
        $.getJSON('{{ url("admin/get-rt-by-kel-anak") }}' + '/' + id, function (response) {
            $.each(response, function (rtId, name) { $('#rtx').append(new Option(name, rtId)); });
        });
    });
```

Catatan: `updateUser` memakai `id_kecx/id_kelx` hanya bila "Ganti Lokasi" dicentang; untuk peran rt, `wilayahDariRt()` menimpa wilayah dari RT terpilih sehingga select `rtx` cukup.

- [ ] **Step 7: Jalankan tes, pastikan lulus**

Run: `/d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe vendor/bin/phpunit tests/Feature/VerifikasiRt/BuatAkunRtTest.php`
Expected: `OK (3 tests)`.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Requests/Admin/User/storeUserRequest.php app/Repositories/Admin/User/UserRepository.php resources/views/admin/user/create.blade.php resources/views/admin/user/edit.blade.php tests/Feature/VerifikasiRt/BuatAkunRtTest.php
git commit -m "feat(verifikasi-rt): Dinkes membuat akun peran rt dengan cascade Kec-Kel-RT"
```

---

### Task 4: `VerifikasiRtService` — cakupan, usulan, reviu, denormalisasi

**Files:**
- Create: `app/Services/VerifikasiRtService.php`
- Test: `tests/Feature/VerifikasiRt/VerifikasiRtServiceTest.php`

**Interfaces:**
- Consumes: `VerifikasiAnak`, `Anak`, `Rt`, `User` (Task 1).
- Produces:
  - `wargaQuery(Rt $rt): \Illuminate\Database\Eloquent\Builder` — `Anak` dengan `id_rt = $rt->id`.
  - `tanpaRtQuery(Rt $rt): Builder` — `Anak` sekelurahan (`id_kel = $rt->id_kelurahan`), `id_rt IS NULL`, minus yang punya `verifikasi_anak(status='bukan_rt_ini', id_rt=$rt->id, reviu != 'ditolak')`.
  - `dalamCakupan(Anak $anak, Rt $rt): bool`.
  - `usulkan(Anak $anak, Rt $rt, User $oleh, string $status, ?string $catatan = null): VerifikasiAnak` — melempar `AuthorizationException` di luar cakupan, `InvalidArgumentException` untuk status tak valid / `bukan_rt_ini` pada anak yang sudah ber-`id_rt` RT ini.
  - `tinjau(VerifikasiAnak $v, User $peninjau, bool $setuju, ?string $catatan = null): VerifikasiAnak` — `AuthorizationException` di luar kelurahan peninjau (non-super), `InvalidArgumentException` bila `reviu != 'diusulkan'`.
  - `segarkanDenormalisasi(Anak $anak): void`.
  - `progres(Rt $rt): array{total:int, diverifikasi:int}`.
  - `antreanQuery(User $peninjau): Builder` — `VerifikasiAnak` `reviu='diusulkan'`, non-super dibatasi kelurahan (`anak.id_kel` = `user.id_kel`, atau bila `anak.id_kel` NULL: `rt.id_kelurahan` = `user.id_kel`).

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php
// tests/Feature/VerifikasiRt/VerifikasiRtServiceTest.php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Anak;
use App\Models\Kelurahan;
use App\Models\Posyandu;
use App\Models\Rt;
use App\Models\User;
use App\Models\VerifikasiAnak;
use App\Services\VerifikasiRtService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifikasiRtServiceTest extends TestCase
{
    use RefreshDatabase;

    private VerifikasiRtService $svc;
    private Rt $rt;
    private Rt $rtLain;
    private User $userRt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc    = app(VerifikasiRtService::class);
        $this->rt     = Rt::factory()->create();
        $this->rtLain = Rt::factory()->create(['id_kelurahan' => $this->rt->id_kelurahan]);
        $this->userRt = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => $this->rt->id, 'id_kel' => $this->rt->id_kelurahan]);
    }

    private function anak(string $nik, array $o = []): Anak
    {
        return Anak::create(array_merge([
            'nama' => 'Anak '.$nik, 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'operasi_timbang',
            'id_kel' => $this->rt->id_kelurahan, 'id_rt' => $this->rt->id,
        ], $o));
    }

    public function test_cakupan_warga_dan_tanpa_rt(): void
    {
        $warga   = $this->anak('3201000000009001');
        $tanpaRt = $this->anak('3201000000009002', ['id_rt' => null]);
        $rtLain  = $this->anak('3201000000009003', ['id_rt' => $this->rtLain->id]);
        $kelLain = $this->anak('3201000000009004', ['id_rt' => null, 'id_kel' => Kelurahan::factory()->create()->id]);

        $this->assertSame([$warga->id], $this->svc->wargaQuery($this->rt)->pluck('id')->all());
        $this->assertSame([$tanpaRt->id], $this->svc->tanpaRtQuery($this->rt)->pluck('id')->all());
        $this->assertTrue($this->svc->dalamCakupan($warga, $this->rt));
        $this->assertTrue($this->svc->dalamCakupan($tanpaRt, $this->rt));
        $this->assertFalse($this->svc->dalamCakupan($rtLain, $this->rt));
        $this->assertFalse($this->svc->dalamCakupan($kelLain, $this->rt));
    }

    public function test_usulan_berdomisili_menandai_anak_dan_tidak_menyentuh_updated_at(): void
    {
        $anak = $this->anak('3201000000009005');
        $updatedAtAwal = $anak->fresh()->updated_at;
        $this->travel(1)->hours();

        $v = $this->svc->usulkan($anak, $this->rt, $this->userRt, 'berdomisili', 'sudah ditemui');

        $anak->refresh();
        $this->assertSame('berdomisili', $v->status);
        $this->assertSame('diusulkan', $v->reviu);
        $this->assertNull($v->klaim_id_rt);
        $this->assertSame('berdomisili', $anak->verif_rt_status);
        $this->assertSame('diusulkan', $anak->verif_rt_reviu);
        $this->assertNotNull($anak->verif_rt_at);
        $this->assertEquals($updatedAtAwal, $anak->updated_at, 'verif_rt_* tidak boleh menyentuh updated_at');
    }

    public function test_usulan_di_luar_cakupan_ditolak(): void
    {
        $anak = $this->anak('3201000000009006', ['id_rt' => $this->rtLain->id]);

        $this->expectException(AuthorizationException::class);
        $this->svc->usulkan($anak, $this->rt, $this->userRt, 'berdomisili');
    }

    public function test_klaim_anak_tanpa_rt_mengisi_klaim_id_rt(): void
    {
        $anak = $this->anak('3201000000009007', ['id_rt' => null]);

        $v = $this->svc->usulkan($anak, $this->rt, $this->userRt, 'berdomisili');

        $this->assertSame($this->rt->id, (int) $v->klaim_id_rt);
        $this->assertNull($anak->fresh()->id_rt, 'id_rt baru diisi setelah disetujui');
    }

    public function test_bukan_rt_ini_menyembunyikan_dari_tanpa_rt_tanpa_menyentuh_tag(): void
    {
        $anak = $this->anak('3201000000009008', ['id_rt' => null]);

        $this->svc->usulkan($anak, $this->rt, $this->userRt, 'bukan_rt_ini');

        $this->assertSame([], $this->svc->tanpaRtQuery($this->rt)->pluck('id')->all());
        $this->assertSame([$anak->id], $this->svc->tanpaRtQuery($this->rtLain)->pluck('id')->all(), 'RT lain masih melihatnya');
        $this->assertNull($anak->fresh()->verif_rt_status);
    }

    public function test_bukan_rt_ini_untuk_warga_sendiri_ditolak(): void
    {
        $anak = $this->anak('3201000000009009');

        $this->expectException(\InvalidArgumentException::class);
        $this->svc->usulkan($anak, $this->rt, $this->userRt, 'bukan_rt_ini');
    }

    public function test_usulan_baru_menggantikan_usulan_lama_yang_belum_ditinjau(): void
    {
        $anak = $this->anak('3201000000009010');
        $lama = $this->svc->usulkan($anak, $this->rt, $this->userRt, 'pindah');
        $baru = $this->svc->usulkan($anak, $this->rt, $this->userRt, 'berdomisili');

        $this->assertSame('ditolak', $lama->fresh()->reviu);
        $this->assertSame('Digantikan usulan baru', $lama->fresh()->catatan_reviu);
        $this->assertSame('berdomisili', $anak->fresh()->verif_rt_status);
        $this->assertSame(1, VerifikasiAnak::where('id_anak', $anak->id)->where('reviu', 'diusulkan')->count());
        $this->assertSame($baru->id, VerifikasiAnak::where('id_anak', $anak->id)->where('reviu', 'diusulkan')->value('id'));
    }

    public function test_setuju_klaim_mengisi_id_rt_dan_posyandu_hanya_bila_kosong(): void
    {
        $super  = User::factory()->create(['type' => 0]);
        $posLama = Posyandu::factory()->create();
        $a = $this->anak('3201000000009011', ['id_rt' => null, 'id_posyandu' => null]);
        $b = $this->anak('3201000000009012', ['id_rt' => null, 'id_posyandu' => $posLama->id]);
        $va = $this->svc->usulkan($a, $this->rt, $this->userRt, 'berdomisili');
        $vb = $this->svc->usulkan($b, $this->rt, $this->userRt, 'berdomisili');

        $this->svc->tinjau($va, $super, true, 'ok');
        $this->svc->tinjau($vb, $super, true);

        $this->assertSame($this->rt->id, (int) $a->fresh()->id_rt);
        $this->assertSame($this->rt->id_posyandu, (int) $a->fresh()->id_posyandu);
        $this->assertSame($this->rt->id, (int) $b->fresh()->id_rt);
        $this->assertSame($posLama->id, (int) $b->fresh()->id_posyandu, 'posyandu terisi tidak ditimpa');
        $this->assertSame('disetujui', $a->fresh()->verif_rt_reviu);
        $this->assertSame('disetujui', $va->fresh()->reviu);
        $this->assertSame($super->id, (int) $va->fresh()->ditinjau_oleh);
    }

    public function test_tolak_menandai_ditolak_tanpa_mengubah_id_rt(): void
    {
        $super = User::factory()->create(['type' => 0]);
        $anak  = $this->anak('3201000000009013', ['id_rt' => null]);
        $v     = $this->svc->usulkan($anak, $this->rt, $this->userRt, 'berdomisili');

        $this->svc->tinjau($v, $super, false, 'bukan warga');

        $this->assertNull($anak->fresh()->id_rt);
        $this->assertSame('ditolak', $anak->fresh()->verif_rt_reviu);
        $this->assertSame('bukan warga', $v->fresh()->catatan_reviu);
    }

    public function test_faskes_hanya_meninjau_kelurahannya(): void
    {
        $kelLain = Kelurahan::factory()->create();
        $faskesLain = User::factory()->create(['type' => 1, 'id_kel' => $kelLain->id]);
        $faskesSini = User::factory()->create(['type' => 1, 'id_kel' => $this->rt->id_kelurahan]);
        $anak = $this->anak('3201000000009014');
        $v = $this->svc->usulkan($anak, $this->rt, $this->userRt, 'meninggal');

        $this->assertSame([], $this->svc->antreanQuery($faskesLain)->pluck('id')->all());
        $this->assertSame([$v->id], $this->svc->antreanQuery($faskesSini)->pluck('id')->all());

        try {
            $this->svc->tinjau($v, $faskesLain, true);
            $this->fail('harus ditolak');
        } catch (AuthorizationException) {
        }

        $this->svc->tinjau($v, $faskesSini, true);
        $this->assertSame('disetujui', $v->fresh()->reviu);
    }

    public function test_usulan_yang_sudah_ditinjau_tidak_bisa_ditinjau_lagi(): void
    {
        $super = User::factory()->create(['type' => 0]);
        $anak  = $this->anak('3201000000009015');
        $v     = $this->svc->usulkan($anak, $this->rt, $this->userRt, 'pindah');
        $this->svc->tinjau($v, $super, true);

        $this->expectException(\InvalidArgumentException::class);
        $this->svc->tinjau($v->fresh(), $super, false);
    }

    public function test_progres_menghitung_warga_yang_sudah_diverifikasi(): void
    {
        $a = $this->anak('3201000000009016');
        $this->anak('3201000000009017');
        $this->anak('3201000000009018', ['id_rt' => null]); // bukan warga → tidak dihitung
        $this->svc->usulkan($a, $this->rt, $this->userRt, 'berdomisili');

        $this->assertSame(['total' => 2, 'diverifikasi' => 1], $this->svc->progres($this->rt));
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `/d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe vendor/bin/phpunit tests/Feature/VerifikasiRt/VerifikasiRtServiceTest.php`
Expected: FAIL — `Class "App\Services\VerifikasiRtService" not found`.

- [ ] **Step 3: Tulis service**

```php
<?php
// app/Services/VerifikasiRtService.php

namespace App\Services;

use App\Models\Anak;
use App\Models\Rt;
use App\Models\User;
use App\Models\VerifikasiAnak;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Seluruh aturan verifikasi RT (spec docs/superpowers/specs/2026-09-14-verifikasi-rt-design.md
 * §3–§6): cakupan anak per RT, usulan RT, reviu puskesmas/Dinkes, dan denormalisasi ke
 * anak.verif_rt_*. Controller tidak boleh menulis tabel ini sendiri.
 */
class VerifikasiRtService
{
    /** Anak yang sudah dipetakan ke RT ini (tab "Warga RT"). */
    public function wargaQuery(Rt $rt): Builder
    {
        return Anak::query()->where('id_rt', $rt->id);
    }

    /**
     * Anak sekelurahan yang belum ber-RT (tab "Belum ber-RT"), minus yang sudah dinyatakan
     * "bukan warga RT ini" oleh RT ini (selama pernyataan itu belum ditolak peninjau).
     */
    public function tanpaRtQuery(Rt $rt): Builder
    {
        return Anak::query()
            ->where('id_kel', $rt->id_kelurahan)
            ->whereNull('id_rt')
            ->whereNotExists(function ($q) use ($rt) {
                $q->select(DB::raw(1))->from('verifikasi_anak as va')
                  ->whereColumn('va.id_anak', 'anak.id')
                  ->where('va.id_rt', $rt->id)
                  ->where('va.status', 'bukan_rt_ini')
                  ->where('va.reviu', '!=', 'ditolak');
            });
    }

    public function dalamCakupan(Anak $anak, Rt $rt): bool
    {
        if ((int) $anak->id_rt === (int) $rt->id) {
            return true;
        }

        return $anak->id_rt === null && (int) $anak->id_kel === (int) $rt->id_kelurahan;
    }

    /**
     * Usulan RT. Usulan lama yang masih `diusulkan` dari RT yang sama untuk anak yang sama
     * ditandai `ditolak` ("Digantikan usulan baru") supaya antrean hanya memuat satu usulan per anak.
     */
    public function usulkan(Anak $anak, Rt $rt, User $oleh, string $status, ?string $catatan = null): VerifikasiAnak
    {
        if (!in_array($status, VerifikasiAnak::STATUS, true)) {
            throw new InvalidArgumentException("Status verifikasi tidak dikenal: {$status}");
        }
        if (!$this->dalamCakupan($anak, $rt)) {
            throw new AuthorizationException('Anak di luar cakupan RT ini.');
        }
        if ($status === 'bukan_rt_ini' && $anak->id_rt !== null) {
            throw new InvalidArgumentException('"Bukan warga RT ini" hanya untuk anak yang belum ber-RT.');
        }

        return DB::transaction(function () use ($anak, $rt, $oleh, $status, $catatan) {
            VerifikasiAnak::where('id_anak', $anak->id)
                ->where('id_rt', $rt->id)
                ->where('reviu', 'diusulkan')
                ->update([
                    'reviu'         => 'ditolak',
                    'catatan_reviu' => 'Digantikan usulan baru',
                    'ditinjau_at'   => now(),
                ]);

            $v = VerifikasiAnak::create([
                'id_anak'        => $anak->id,
                'id_rt'          => $rt->id,
                'status'         => $status,
                // Klaim: anak belum ber-RT dinyatakan berdomisili → setelah disetujui, id_rt diisi
                'klaim_id_rt'    => ($status === 'berdomisili' && $anak->id_rt === null) ? $rt->id : null,
                'catatan'        => $catatan ?: null,
                'diusulkan_oleh' => $oleh->id,
                'diusulkan_at'   => now(),
                'reviu'          => 'diusulkan',
            ]);

            $this->segarkanDenormalisasi($anak);

            return $v;
        });
    }

    /** Reviu puskesmas (kelurahannya) / Dinkes (semua). */
    public function tinjau(VerifikasiAnak $v, User $peninjau, bool $setuju, ?string $catatan = null): VerifikasiAnak
    {
        if ($v->reviu !== 'diusulkan') {
            throw new InvalidArgumentException('Usulan ini sudah ditinjau.');
        }
        $this->pastikanBolehMeninjau($v, $peninjau);

        return DB::transaction(function () use ($v, $peninjau, $setuju, $catatan) {
            $v->update([
                'reviu'         => $setuju ? 'disetujui' : 'ditolak',
                'ditinjau_oleh' => $peninjau->id,
                'ditinjau_at'   => now(),
                'catatan_reviu' => $catatan ?: null,
            ]);

            $anak = $v->anak;

            if ($setuju && $v->klaim_id_rt) {
                $rt = Rt::findOrFail($v->klaim_id_rt);
                $update = ['id_rt' => $rt->id];
                if (!$anak->id_kel) {
                    $update['id_kel'] = $rt->id_kelurahan;
                }
                if (!$anak->id_posyandu) { // posyandu yang sudah terisi tidak ditimpa (spec §6.2)
                    $update['id_posyandu'] = $rt->id_posyandu;
                }
                // Perubahan domisili sungguhan → updated_at BOLEH ikut berubah (pakai Eloquent)
                $anak->update($update);
            }

            $this->segarkanDenormalisasi($anak);

            return $v->fresh();
        });
    }

    /** Antrean reviu: `diusulkan`, dibatasi kelurahan untuk non-superadmin. */
    public function antreanQuery(User $peninjau): Builder
    {
        $q = VerifikasiAnak::query()->with(['anak', 'rt', 'pengusul'])->where('reviu', 'diusulkan');

        if (!$peninjau->isSuperAdmin()) {
            $kel = (int) $peninjau->id_kel;
            $q->where(function ($w) use ($kel) {
                $w->whereHas('anak', fn ($a) => $a->where('id_kel', $kel))
                  ->orWhere(fn ($x) => $x->whereHas('anak', fn ($a) => $a->whereNull('id_kel'))
                                          ->whereHas('rt', fn ($r) => $r->where('id_kelurahan', $kel)));
            });
        }

        return $q->orderBy('id');
    }

    /**
     * anak.verif_rt_* = usulan terbaru yang bukan `bukan_rt_ini`. Ditulis lewat query builder
     * agar anak.updated_at tidak tersentuh (heuristik CapilDedupService::sigiziUntouched()).
     */
    public function segarkanDenormalisasi(Anak $anak): void
    {
        $terbaru = VerifikasiAnak::where('id_anak', $anak->id)
            ->where('status', '!=', 'bukan_rt_ini')
            ->orderByDesc('id')
            ->first();

        DB::table('anak')->where('id', $anak->id)->update([
            'verif_rt_status' => $terbaru?->status,
            'verif_rt_reviu'  => $terbaru?->reviu,
            'verif_rt_at'     => $terbaru?->diusulkan_at,
        ]);
    }

    /** @return array{total:int, diverifikasi:int} */
    public function progres(Rt $rt): array
    {
        $total        = (clone $this->wargaQuery($rt))->count();
        $diverifikasi = (clone $this->wargaQuery($rt))->whereNotNull('verif_rt_status')->count();

        return ['total' => $total, 'diverifikasi' => $diverifikasi];
    }

    private function pastikanBolehMeninjau(VerifikasiAnak $v, User $peninjau): void
    {
        if ($peninjau->isSuperAdmin()) {
            return;
        }
        if (!$peninjau->id_kel) {
            throw new AuthorizationException('Akun peninjau tidak punya kelurahan.');
        }
        $kelAnak = $v->anak->id_kel ?: $v->rt->id_kelurahan;
        if ((int) $kelAnak !== (int) $peninjau->id_kel) {
            throw new AuthorizationException('Usulan di luar kelurahan peninjau.');
        }
    }
}
```

- [ ] **Step 4: Jalankan tes, pastikan lulus**

Run: `/d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe vendor/bin/phpunit tests/Feature/VerifikasiRt/VerifikasiRtServiceTest.php`
Expected: `OK (11 tests)`.

- [ ] **Step 5: Commit**

```bash
git add app/Services/VerifikasiRtService.php tests/Feature/VerifikasiRt/VerifikasiRtServiceTest.php
git commit -m "feat(verifikasi-rt): VerifikasiRtService — cakupan, usulan, reviu, denormalisasi"
```

---

### Task 5: Controller & endpoint JSON halaman RT

**Files:**
- Create: `app/Http/Controllers/Rt/VerifikasiRtController.php`
- Modify: `routes/web.php` (ganti closure sementara Task 2)
- Test: `tests/Feature/VerifikasiRt/EndpointRtTest.php`

**Interfaces:**
- Consumes: `VerifikasiRtService` (Task 4), `Anak` route binding by `hashid`, `HashIdService::encode($id, 'anak')`.
- Produces routes: `rt.verifikasi` GET `/rt/verifikasi` (view `rt.verifikasi`), `rt.api.warga` GET `/rt/api/warga`, `rt.api.tanpaRt` GET `/rt/api/tanpa-rt`, `rt.api.usulkan` POST `/rt/api/anak/{anak}/verifikasi` body `{status, catatan?}`.
- Bentuk baris JSON (dipakai blade Task 6): `{id, sumber, sumber_label, nik, nama, jk, tgl_lahir, nama_ibu, nama_ayah, no_kk, alamat, alamat_ktp, posyandu, verif_status, verif_label, verif_reviu, verif_at}`; respons `warga`/`tanpaRt`: `{rows: [...], progres: {total, diverifikasi}}`; respons `usulkan`: `{id, verif_status, verif_label, verif_reviu, verif_at, hilang: bool}` (`hilang=true` bila anak tidak lagi tampil di tab asal, mis. `bukan_rt_ini`).
- Superadmin boleh membuka halaman RT mana pun lewat `?rt={id}` (pratinjau/dukungan); tanpa parameter → 403.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php
// tests/Feature/VerifikasiRt/EndpointRtTest.php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Anak;
use App\Models\Rt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndpointRtTest extends TestCase
{
    use RefreshDatabase;

    private Rt $rt;
    private User $userRt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rt     = Rt::factory()->create();
        $this->userRt = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => $this->rt->id, 'id_kel' => $this->rt->id_kelurahan]);
    }

    private function anak(string $nik, array $o = []): Anak
    {
        return Anak::create(array_merge([
            'nama' => 'Anak '.$nik, 'nik' => $nik, 'jk' => 2, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'capil',
            'id_kel' => $this->rt->id_kelurahan, 'id_rt' => $this->rt->id, 'nama_ibu' => 'Ibu', 'nama_ayah' => 'Ayah',
        ], $o));
    }

    public function test_warga_mengembalikan_baris_lengkap_dengan_badge_sumber(): void
    {
        $anak = $this->anak('3201000000009101');
        $this->anak('3201000000009102', ['id_rt' => Rt::factory()->create()->id]);

        $res = $this->actingAs($this->userRt)->getJson(route('rt.api.warga'))->assertOk()->json();

        $this->assertCount(1, $res['rows']);
        $row = $res['rows'][0];
        $this->assertSame($anak->hashid, $row['id']);
        $this->assertSame('3201000000009101', $row['nik'], 'RT melihat NIK lengkap (spec §8)');
        $this->assertSame('Capil', $row['sumber_label']);
        $this->assertSame('P', $row['jk']);
        $this->assertNull($row['verif_status']);
        $this->assertSame(['total' => 1, 'diverifikasi' => 0], $res['progres']);
        $this->assertArrayNotHasKey('bb', $row);
    }

    public function test_tanpa_rt_hanya_sekelurahan_tanpa_id_rt(): void
    {
        $a = $this->anak('3201000000009103', ['id_rt' => null]);
        $this->anak('3201000000009104');

        $ids = collect($this->actingAs($this->userRt)->getJson(route('rt.api.tanpaRt'))->assertOk()->json('rows'))->pluck('id');
        $this->assertSame([$a->hashid], $ids->all());
    }

    public function test_usulkan_menyimpan_dan_mengembalikan_tag(): void
    {
        $anak = $this->anak('3201000000009105');

        $this->actingAs($this->userRt)
            ->postJson(route('rt.api.usulkan', $anak), ['status' => 'pindah', 'catatan' => 'ke Samarinda'])
            ->assertOk()
            ->assertJsonPath('verif_status', 'pindah')
            ->assertJsonPath('verif_label', 'Pindah')
            ->assertJsonPath('verif_reviu', 'diusulkan')
            ->assertJsonPath('hilang', false);

        $this->assertSame('pindah', $anak->fresh()->verif_rt_status);
    }

    public function test_usulkan_bukan_rt_ini_menandai_hilang(): void
    {
        $anak = $this->anak('3201000000009106', ['id_rt' => null]);

        $this->actingAs($this->userRt)
            ->postJson(route('rt.api.usulkan', $anak), ['status' => 'bukan_rt_ini'])
            ->assertOk()
            ->assertJsonPath('hilang', true);
    }

    public function test_usulkan_anak_rt_lain_ditolak_403(): void
    {
        $anak = $this->anak('3201000000009107', ['id_rt' => Rt::factory()->create()->id]);

        $this->actingAs($this->userRt)
            ->postJson(route('rt.api.usulkan', $anak), ['status' => 'berdomisili'])
            ->assertForbidden();
    }

    public function test_status_tidak_valid_422(): void
    {
        $anak = $this->anak('3201000000009108');

        $this->actingAs($this->userRt)
            ->postJson(route('rt.api.usulkan', $anak), ['status' => 'hilang'])
            ->assertStatus(422);
    }

    public function test_halaman_rt_merender_identitas_rt(): void
    {
        $this->actingAs($this->userRt)->get(route('rt.verifikasi'))
            ->assertOk()
            ->assertSee($this->rt->name)
            ->assertSee(route('rt.api.warga'));
    }

    public function test_superadmin_pratinjau_dengan_parameter_rt(): void
    {
        $super = User::factory()->create(['type' => 0]);
        $this->actingAs($super)->get(route('rt.verifikasi'))->assertForbidden();
        $this->actingAs($super)->get(route('rt.verifikasi', ['rt' => $this->rt->id]))->assertOk()->assertSee($this->rt->name);
        $this->actingAs($super)->getJson(route('rt.api.warga', ['rt' => $this->rt->id]))->assertOk();
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `/d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe vendor/bin/phpunit tests/Feature/VerifikasiRt/EndpointRtTest.php`
Expected: FAIL — `Route [rt.api.warga] not defined.`

- [ ] **Step 3: Controller**

```php
<?php
// app/Http/Controllers/Rt/VerifikasiRtController.php

namespace App\Http\Controllers\Rt;

use App\Http\Controllers\Controller;
use App\Models\Anak;
use App\Models\Rt;
use App\Models\VerifikasiAnak;
use App\Services\HashIdService;
use App\Services\VerifikasiRtService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Halaman tunggal peran RT (spec §4): tab Warga RT & Belum ber-RT. Seluruh aturan di
 * VerifikasiRtService; controller hanya memilih RT, membentuk baris JSON, dan memetakan error.
 */
class VerifikasiRtController extends Controller
{
    private const LABEL_SUMBER = [
        'operasi_timbang' => 'OT',
        'capil'           => 'Capil',
        'manual'          => 'Manual',
        'dummy'           => 'Dummy',
    ];

    public function __construct(private readonly VerifikasiRtService $svc)
    {
    }

    public function index(Request $request): View
    {
        $rt = $this->rt($request);
        $rt->load('kelurahan');

        return view('rt.verifikasi', [
            'rt'      => $rt,
            'progres' => $this->svc->progres($rt),
        ]);
    }

    public function warga(Request $request): JsonResponse
    {
        $rt = $this->rt($request);

        return response()->json([
            'rows'    => $this->rows($this->svc->wargaQuery($rt)),
            'progres' => $this->svc->progres($rt),
        ]);
    }

    public function tanpaRt(Request $request): JsonResponse
    {
        $rt = $this->rt($request);

        return response()->json([
            'rows'    => $this->rows($this->svc->tanpaRtQuery($rt)),
            'progres' => $this->svc->progres($rt),
        ]);
    }

    public function usulkan(Request $request, Anak $anak): JsonResponse
    {
        $rt   = $this->rt($request);
        $data = $request->validate([
            'status'  => ['required', Rule::in(VerifikasiAnak::STATUS)],
            'catatan' => 'nullable|string|max:1000',
        ]);

        try {
            $v = $this->svc->usulkan($anak, $rt, $request->user(), $data['status'], $data['catatan'] ?? null);
        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $anak->refresh();

        return response()->json([
            'id'           => $anak->hashid,
            'verif_status' => $anak->verif_rt_status,
            'verif_label'  => $anak->verif_rt_status ? VerifikasiAnak::LABEL_STATUS[$anak->verif_rt_status] : null,
            'verif_reviu'  => $anak->verif_rt_reviu,
            'verif_at'     => $anak->verif_rt_at?->format('Y-m-d H:i'),
            'hilang'       => $v->status === 'bukan_rt_ini',
            'progres'      => $this->svc->progres($rt),
        ]);
    }

    /** RT milik user; superadmin boleh memilih lewat ?rt= untuk pratinjau/dukungan. */
    private function rt(Request $request): Rt
    {
        $user = $request->user();
        if ($user->isSuperAdmin()) {
            abort_if(!$request->query('rt'), 403, 'Pilih RT lewat parameter ?rt=');
            return Rt::findOrFail((int) $request->query('rt'));
        }
        abort_if(!$user->isRt() || !$user->id_rt, 403);

        return Rt::findOrFail($user->id_rt);
    }

    private function rows(Builder $q): array
    {
        return $q->with('posyandu:id,name')
            ->orderBy('nama')
            ->get()
            ->map(fn (Anak $a) => [
                'id'           => HashIdService::encode($a->id, 'anak'),
                'sumber'       => $a->sumber,
                'sumber_label' => self::LABEL_SUMBER[$a->sumber] ?? ucfirst((string) $a->sumber),
                'nik'          => $a->nik,
                'nama'         => $a->nama,
                'jk'           => (int) $a->jk === 1 ? 'L' : 'P',
                'tgl_lahir'    => $a->tgl_lahir,
                'nama_ibu'     => $a->nama_ibu,
                'nama_ayah'    => $a->nama_ayah,
                'no_kk'        => $a->no_kk,
                'alamat'       => $a->alamat,
                'alamat_ktp'   => $a->alamat_ktp,
                'posyandu'     => $a->posyandu?->name,
                'verif_status' => $a->verif_rt_status,
                'verif_label'  => $a->verif_rt_status ? VerifikasiAnak::LABEL_STATUS[$a->verif_rt_status] : null,
                'verif_reviu'  => $a->verif_rt_reviu,
                'verif_at'     => $a->verif_rt_at ? \Carbon\Carbon::parse($a->verif_rt_at)->format('Y-m-d H:i') : null,
            ])
            ->values()
            ->all();
    }
}
```

`app/Models/Anak.php` sudah punya `posyandu()` (`belongsTo(Posyandu::class, 'id_posyandu')`). `app/Models/Rt.php` **belum** punya `kelurahan()` — tambahkan di bawah `$fillable`:

```php
    // app/Models/Rt.php
    public function kelurahan()
    {
        return $this->belongsTo(Kelurahan::class, 'id_kelurahan');
    }
```

- [ ] **Step 4: Rute**

Ganti grup sementara di `routes/web.php` dengan:

```php
Route::middleware(['auth', 'module.role:rt'])->prefix('rt')->name('rt.')->group(function () {
    Route::get('verifikasi',   [App\Http\Controllers\Rt\VerifikasiRtController::class, 'index'])->name('verifikasi');
    Route::get('api/warga',    [App\Http\Controllers\Rt\VerifikasiRtController::class, 'warga'])->name('api.warga');
    Route::get('api/tanpa-rt', [App\Http\Controllers\Rt\VerifikasiRtController::class, 'tanpaRt'])->name('api.tanpaRt');
    Route::post('api/anak/{anak}/verifikasi', [App\Http\Controllers\Rt\VerifikasiRtController::class, 'usulkan'])->name('api.usulkan');
});
```

Tes `test_halaman_rt_merender_identitas_rt` memerlukan view `rt.verifikasi`; buat dulu **kerangka minimal** yang akan dilengkapi di Task 6:

```blade
{{-- resources/views/rt/verifikasi.blade.php — dilengkapi di Task 6 --}}
<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Verifikasi Warga — {{ $rt->name }}</title></head>
<body>
<h1>{{ $rt->name }}</h1>
<script>var API_WARGA = '{{ route("rt.api.warga", request()->only("rt")) }}';</script>
</body></html>
```

- [ ] **Step 5: Jalankan tes, pastikan lulus**

Run: `/d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe vendor/bin/phpunit tests/Feature/VerifikasiRt/EndpointRtTest.php tests/Feature/VerifikasiRt/AkunRtTest.php`
Expected: semua lulus.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Rt/VerifikasiRtController.php app/Models/Rt.php routes/web.php resources/views/rt/verifikasi.blade.php tests/Feature/VerifikasiRt/EndpointRtTest.php
git commit -m "feat(verifikasi-rt): endpoint JSON halaman RT (warga, tanpa-rt, usulkan)"
```

---

### Task 6: Halaman RT (blade mandiri)

**Files:**
- Modify (lengkapi): `resources/views/rt/verifikasi.blade.php`
- Test: `tests/Feature/VerifikasiRt/HalamanRtTest.php`

**Interfaces:**
- Consumes: routes & bentuk JSON Task 5; `$rt` (dengan `kelurahan`), `$progres`.
- Produces: halaman mandiri (tanpa layout admin) dengan dua tab, pencarian, filter status, tombol aksi per baris, progres, logout.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php
// tests/Feature/VerifikasiRt/HalamanRtTest.php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Rt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HalamanRtTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_memuat_dua_tab_aksi_dan_logout_tanpa_menu_admin(): void
    {
        $rt   = Rt::factory()->create();
        $user = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => $rt->id, 'id_kel' => $rt->id_kelurahan]);

        $res = $this->actingAs($user)->get(route('rt.verifikasi'))->assertOk();

        $res->assertSee('Warga RT')
            ->assertSee('Belum ber-RT')
            ->assertSee(route('rt.api.warga'))
            ->assertSee(route('rt.api.tanpaRt'))
            ->assertSee(route('logout'))
            ->assertSee('data-status="berdomisili"', false)
            ->assertSee('data-status="pindah"', false)
            ->assertSee('data-status="meninggal"', false)
            ->assertSee('data-status="tidak_dikenal"', false)
            ->assertSee('data-status="bukan_rt_ini"', false)
            ->assertDontSee(route('admin.home'))
            ->assertDontSee('Berat Badan')
            ->assertDontSee('Tinggi Badan');
    }

    public function test_url_usulkan_memakai_placeholder_hashid(): void
    {
        $src = file_get_contents(resource_path('views/rt/verifikasi.blade.php'));

        $this->assertStringContainsString("route('rt.api.usulkan', ['anak' => '__ID__']", $src);
        $this->assertStringContainsString("'__ID__'", $src);
        $this->assertStringContainsString('X-CSRF-TOKEN', $src);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `/d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe vendor/bin/phpunit tests/Feature/VerifikasiRt/HalamanRtTest.php`
Expected: FAIL — `Failed asserting that ... contains "Warga RT"`.

- [ ] **Step 3: Tulis halaman**

Ganti seluruh isi `resources/views/rt/verifikasi.blade.php` dengan:

```blade
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Verifikasi Warga — {{ $rt->name }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
<style>
:root{ --ink:oklch(0.24 0.02 145); --muted:oklch(0.50 0.015 145); --faint:oklch(0.62 0.012 145);
  --line:oklch(0.90 0.012 145); --bg:oklch(0.98 0.012 145); --card:#fff; --green:oklch(0.48 0.14 145);
  --green-soft:oklch(0.95 0.04 145); --amber:#b45309; --amber-soft:#fef3c7; --red:#b91c1c; --red-soft:#fee2e2; --blue:#1d4ed8; --blue-soft:#dbeafe; }
*{ box-sizing:border-box; }
body{ margin:0; font-family:Barlow,system-ui,sans-serif; color:var(--ink); background:var(--bg); }
.rt-shell{ max-width:1200px; margin:0 auto; padding:16px; }
.rt-top{ display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; padding:12px 0 18px; }
.rt-brand{ display:flex; align-items:center; gap:10px; font-weight:800; letter-spacing:.06em; }
.rt-brand img{ width:34px; height:34px; }
.rt-who{ font-size:.9rem; color:var(--muted); }
.rt-who b{ color:var(--ink); }
.rt-logout{ background:transparent; border:1px solid var(--line); border-radius:8px; padding:6px 12px; font:inherit; cursor:pointer; color:var(--muted); }
.rt-prog{ background:var(--card); border:1px solid var(--line); border-radius:12px; padding:14px 16px; margin-bottom:14px; }
.rt-prog__bar{ height:8px; background:var(--line); border-radius:99px; overflow:hidden; margin-top:8px; }
.rt-prog__fill{ height:100%; background:var(--green); width:0; transition:width .3s; }
.rt-tabs{ display:flex; gap:6px; border-bottom:1px solid var(--line); margin-bottom:12px; }
.rt-tab{ background:transparent; border:0; border-bottom:2px solid transparent; padding:10px 14px; font:inherit; font-weight:700; color:var(--muted); cursor:pointer; }
.rt-tab.active{ color:var(--green); border-bottom-color:var(--green); }
.rt-tab .n{ display:inline-block; min-width:22px; padding:0 6px; border-radius:99px; background:var(--line); font-size:.75rem; margin-left:6px; }
.rt-tools{ display:flex; gap:8px; flex-wrap:wrap; margin-bottom:10px; }
.rt-tools input,.rt-tools select{ font:inherit; padding:8px 10px; border:1px solid var(--line); border-radius:8px; background:var(--card); }
.rt-tools input{ flex:1; min-width:220px; }
.rt-hint{ font-size:.85rem; color:var(--muted); margin:0 0 10px; }
.rt-wrap{ background:var(--card); border:1px solid var(--line); border-radius:12px; overflow:auto; }
table.rt-dt{ width:100%; border-collapse:collapse; font-size:.85rem; }
.rt-dt th{ position:sticky; top:0; background:oklch(0.96 0.016 145); text-align:left; padding:.55rem .7rem; font-size:.68rem; text-transform:uppercase; letter-spacing:.05em; color:var(--muted); white-space:nowrap; }
.rt-dt td{ padding:.55rem .7rem; border-top:1px solid var(--line); vertical-align:top; }
.badge{ display:inline-block; padding:.1rem .45rem; border-radius:6px; font-size:.72rem; font-weight:700; }
.badge--ot{ background:var(--green-soft); color:var(--green); } .badge--capil{ background:var(--blue-soft); color:var(--blue); }
.badge--manual{ background:var(--amber-soft); color:var(--amber); } .badge--lain{ background:var(--line); color:var(--muted); }
.st{ display:inline-block; padding:.15rem .5rem; border-radius:6px; font-size:.75rem; font-weight:700; }
.st--berdomisili{ background:var(--green-soft); color:var(--green); } .st--pindah,.st--tidak_dikenal{ background:var(--amber-soft); color:var(--amber); }
.st--meninggal{ background:var(--red-soft); color:var(--red); } .st--kosong{ color:var(--faint); font-style:italic; }
.reviu{ display:block; font-size:.68rem; color:var(--faint); margin-top:2px; }
.aksi{ display:flex; gap:4px; flex-wrap:wrap; }
.aksi button{ font:inherit; font-size:.75rem; font-weight:700; padding:4px 8px; border-radius:6px; border:1px solid var(--line); background:var(--card); cursor:pointer; }
.aksi button:hover{ border-color:var(--green); color:var(--green); }
.aksi button.aktif{ background:var(--green); border-color:var(--green); color:#fff; }
.rt-empty{ text-align:center; padding:28px; color:var(--faint); }
.rt-toast{ position:fixed; left:50%; bottom:20px; transform:translateX(-50%); background:var(--ink); color:#fff; padding:10px 16px; border-radius:10px; font-size:.85rem; display:none; }
</style>
</head>
<body>
<div class="rt-shell">
  <header class="rt-top">
    <div class="rt-brand"><img src="{{ asset('logo/icon-sirindu.png') }}" alt="">SIRINDU · Verifikasi Warga</div>
    <div class="rt-who"><b>{{ $rt->name }}</b> · Kel. {{ $rt->kelurahan?->name ?? '-' }} · {{ auth()->user()->name }}</div>
    <form method="POST" action="{{ route('logout') }}">@csrf<button class="rt-logout" type="submit">Keluar</button></form>
  </header>

  <section class="rt-prog">
    <div><b id="prog-teks">{{ $progres['diverifikasi'] }} dari {{ $progres['total'] }}</b> warga sudah diverifikasi</div>
    <div class="rt-prog__bar"><div class="rt-prog__fill" id="prog-fill" style="width:{{ $progres['total'] ? round($progres['diverifikasi'] / $progres['total'] * 100) : 0 }}%"></div></div>
  </section>

  <nav class="rt-tabs">
    <button class="rt-tab active" data-tab="warga">Warga RT <span class="n" id="n-warga">–</span></button>
    <button class="rt-tab" data-tab="tanpa">Belum ber-RT (sekelurahan) <span class="n" id="n-tanpa">–</span></button>
  </nav>

  <p class="rt-hint" id="hint-warga">Tandai setiap anak: masih berdomisili di RT ini, sudah pindah, meninggal, atau tidak dikenal. Keputusan Anda ditinjau puskesmas.</p>
  <p class="rt-hint" id="hint-tanpa" style="display:none">Anak di kelurahan ini yang belum diketahui RT-nya. Klik <b>Warga RT saya</b> bila ia tinggal di RT Anda, atau <b>Bukan</b> bila tidak.</p>

  <div class="rt-tools">
    <input type="search" id="cari" placeholder="Cari nama / NIK / orang tua / alamat…">
    <select id="f-status">
      <option value="">Semua status</option>
      <option value="kosong">Belum diverifikasi</option>
      <option value="berdomisili">Berdomisili</option>
      <option value="pindah">Pindah</option>
      <option value="meninggal">Meninggal</option>
      <option value="tidak_dikenal">Tidak dikenal</option>
    </select>
  </div>

  <div class="rt-wrap" id="tabel"><div class="rt-empty">Memuat…</div></div>
</div>
<div class="rt-toast" id="toast"></div>

<script>
var API_WARGA   = '{{ route("rt.api.warga", request()->only("rt")) }}';
var API_TANPA   = '{{ route("rt.api.tanpaRt", request()->only("rt")) }}';
var API_USULKAN = '{{ route('rt.api.usulkan', ['anak' => '__ID__'] + request()->only('rt')) }}';
var CSRF        = '{{ csrf_token() }}';
var LABEL = { berdomisili:'Berdomisili', pindah:'Pindah', meninggal:'Meninggal', tidak_dikenal:'Tidak dikenal', bukan_rt_ini:'Bukan warga RT ini' };
var data = { warga:[], tanpa:[] };
var tab = 'warga';

function esc(s){ return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function toast(msg){ var t = document.getElementById('toast'); t.textContent = msg; t.style.display = 'block'; clearTimeout(t._h); t._h = setTimeout(function(){ t.style.display = 'none'; }, 2600); }
function badgeSumber(r){
  var cls = r.sumber === 'operasi_timbang' ? 'ot' : r.sumber === 'capil' ? 'capil' : r.sumber === 'manual' ? 'manual' : 'lain';
  return '<span class="badge badge--'+cls+'">'+esc(r.sumber_label)+'</span>';
}
function statusCell(r){
  if(!r.verif_status) return '<span class="st st--kosong">belum diverifikasi</span>';
  var reviu = r.verif_reviu === 'disetujui' ? 'disetujui' : r.verif_reviu === 'ditolak' ? 'ditolak peninjau' : 'menunggu reviu';
  return '<span class="st st--'+esc(r.verif_status)+'">'+esc(r.verif_label)+'</span><span class="reviu">'+reviu+(r.verif_at ? ' · '+esc(r.verif_at) : '')+'</span>';
}
function aksiCell(r){
  if(tab === 'tanpa'){
    return '<div class="aksi">'
      +'<button data-status="berdomisili" data-id="'+esc(r.id)+'">Warga RT saya</button>'
      +'<button data-status="bukan_rt_ini" data-id="'+esc(r.id)+'">Bukan</button></div>';
  }
  return '<div class="aksi">'+['berdomisili','pindah','meninggal','tidak_dikenal'].map(function(s){
    return '<button data-status="'+s+'" data-id="'+esc(r.id)+'" class="'+(r.verif_status === s ? 'aktif' : '')+'">'+LABEL[s]+'</button>';
  }).join('')+'</div>';
}
function render(){
  var rows = data[tab];
  var q = document.getElementById('cari').value.trim().toLowerCase();
  var fs = document.getElementById('f-status').value;
  if(q){ rows = rows.filter(function(r){ return [r.nama,r.nik,r.nama_ibu,r.nama_ayah,r.alamat,r.alamat_ktp,r.no_kk].join(' ').toLowerCase().indexOf(q) >= 0; }); }
  if(fs === 'kosong'){ rows = rows.filter(function(r){ return !r.verif_status; }); }
  else if(fs){ rows = rows.filter(function(r){ return r.verif_status === fs; }); }
  document.getElementById('n-warga').textContent = data.warga.length;
  document.getElementById('n-tanpa').textContent = data.tanpa.length;
  if(!rows.length){ document.getElementById('tabel').innerHTML = '<div class="rt-empty">Tidak ada data</div>'; return; }
  var h = '<table class="rt-dt"><thead><tr><th>No</th><th>Sumber</th><th>NIK</th><th>Nama</th><th>JK</th><th>Tgl Lahir</th><th>Ibu</th><th>Ayah</th><th>No KK</th><th>Alamat Domisili</th><th>Alamat KTP</th><th>Posyandu</th><th>Status</th><th>Aksi</th></tr></thead><tbody>';
  rows.forEach(function(r, i){
    h += '<tr data-row="'+esc(r.id)+'"><td>'+(i+1)+'</td><td>'+badgeSumber(r)+'</td><td>'+esc(r.nik)+'</td><td><b>'+esc(r.nama)+'</b></td><td>'+esc(r.jk)+'</td><td>'+esc(r.tgl_lahir)+'</td>'
      +'<td>'+esc(r.nama_ibu)+'</td><td>'+esc(r.nama_ayah)+'</td><td>'+esc(r.no_kk)+'</td><td>'+esc(r.alamat || '-')+'</td><td>'+esc(r.alamat_ktp || '-')+'</td><td>'+esc(r.posyandu || '-')+'</td>'
      +'<td class="c-status">'+statusCell(r)+'</td><td>'+aksiCell(r)+'</td></tr>';
  });
  document.getElementById('tabel').innerHTML = h + '</tbody></table>';
}
function setProgres(p){
  if(!p) return;
  document.getElementById('prog-teks').textContent = p.diverifikasi+' dari '+p.total;
  document.getElementById('prog-fill').style.width = (p.total ? Math.round(p.diverifikasi / p.total * 100) : 0)+'%';
}
function muat(){
  document.getElementById('tabel').innerHTML = '<div class="rt-empty">Memuat…</div>';
  Promise.all([fetch(API_WARGA, {headers:{Accept:'application/json'}}).then(function(r){ return r.json(); }),
               fetch(API_TANPA, {headers:{Accept:'application/json'}}).then(function(r){ return r.json(); })])
    .then(function(res){ data.warga = res[0].rows || []; data.tanpa = res[1].rows || []; setProgres(res[0].progres); render(); })
    .catch(function(){ document.getElementById('tabel').innerHTML = '<div class="rt-empty" style="color:#b91c1c">Gagal memuat data</div>'; });
}
function usulkan(id, status, btn){
  var catatan = null;
  if(status === 'pindah' || status === 'tidak_dikenal'){ catatan = window.prompt('Catatan (opsional), mis. pindah ke mana:', '') ; if(catatan === null) return; }
  btn.disabled = true;
  fetch(API_USULKAN.replace('__ID__', encodeURIComponent(id)), {
    method:'POST', headers:{ 'X-CSRF-TOKEN':CSRF, 'Content-Type':'application/json', Accept:'application/json' },
    body: JSON.stringify({ status:status, catatan:catatan })
  })
  .then(function(r){ return r.json().then(function(j){ if(!r.ok) throw new Error(j.message || 'HTTP '+r.status); return j; }); })
  .then(function(j){
    var list = data[tab];
    var idx = list.findIndex(function(r){ return r.id === id; });
    if(j.hilang){ if(idx >= 0) list.splice(idx, 1); }
    else if(idx >= 0){ list[idx].verif_status = j.verif_status; list[idx].verif_label = j.verif_label; list[idx].verif_reviu = j.verif_reviu; list[idx].verif_at = j.verif_at; }
    setProgres(j.progres); render(); toast('Tersimpan — menunggu reviu puskesmas');
  })
  .catch(function(e){ btn.disabled = false; toast('Gagal: '+e.message); });
}
document.querySelectorAll('.rt-tab').forEach(function(b){
  b.addEventListener('click', function(){
    tab = b.getAttribute('data-tab');
    document.querySelectorAll('.rt-tab').forEach(function(x){ x.classList.toggle('active', x === b); });
    document.getElementById('hint-warga').style.display = tab === 'warga' ? '' : 'none';
    document.getElementById('hint-tanpa').style.display = tab === 'tanpa' ? '' : 'none';
    render();
  });
});
document.getElementById('cari').addEventListener('input', render);
document.getElementById('f-status').addEventListener('change', render);
document.getElementById('tabel').addEventListener('click', function(e){
  var btn = e.target.closest('button[data-status]');
  if(btn) usulkan(btn.getAttribute('data-id'), btn.getAttribute('data-status'), btn);
});
muat();
</script>
</body>
</html>
```

Catatan JS: `window.prompt` dipakai untuk catatan singkat — ini dialog blokir; jangan otomatisasi klik "Pindah"/"Tidak dikenal" dari alat browser tanpa menangani dialognya.

- [ ] **Step 4: Jalankan tes, pastikan lulus**

Run: `/d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe artisan view:clear && /d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe vendor/bin/phpunit tests/Feature/VerifikasiRt/HalamanRtTest.php tests/Feature/VerifikasiRt/EndpointRtTest.php`
Expected: semua lulus.

- [ ] **Step 5: Cek visual (opsional tapi dianjurkan)**

Nyalakan MySQL bila mati, `php artisan migrate`, buat RT user lewat tinker
(`User::factory()->create(['type'=>2,'role'=>'rt','id_rt'=>Rt::first()->id,'id_kel'=>Rt::first()->id_kelurahan,'email'=>'rt.demo@sirindu.go.id','password'=>bcrypt('rahasia123')])`),
`php artisan serve --port=8123`, login sebagai RT, pastikan dua tab, badge sumber, tombol status berfungsi (jaringan POST 200), lalu hapus user demo.

- [ ] **Step 6: Commit**

```bash
git add resources/views/rt/verifikasi.blade.php tests/Feature/VerifikasiRt/HalamanRtTest.php
git commit -m "feat(verifikasi-rt): halaman RT mandiri — tab warga & belum ber-RT"
```

---

### Task 7: Antrean reviu puskesmas/Dinkes

**Files:**
- Create: `app/Http/Controllers/VerifikasiRtReviuController.php`
- Create: `resources/views/admin/verifikasi-rt/index.blade.php`
- Modify: `routes/web.php` (di dalam grup `['auth','is_admin']->prefix('admin/')`)
- Modify: `resources/views/vendor/admin/layouts/partials/leftsidebar.blade.php:21,35-42,140-154`
- Test: `tests/Feature/VerifikasiRt/ReviuVerifikasiRtTest.php`

**Interfaces:**
- Consumes: `VerifikasiRtService::antreanQuery()`, `::tinjau()`; `VerifikasiAnak::LABEL_STATUS`.
- Produces routes: `admin.verifikasiRt.index` GET `/admin/verifikasi-rt` (query `?rt=&status=`), `admin.verifikasiRt.tinjau` POST `/admin/verifikasi-rt/{verifikasi}/tinjau` body `{setuju: 1|0, catatan?}` → redirect kembali dengan flash `success`/`error`.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php
// tests/Feature/VerifikasiRt/ReviuVerifikasiRtTest.php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Anak;
use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\User;
use App\Services\VerifikasiRtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviuVerifikasiRtTest extends TestCase
{
    use RefreshDatabase;

    private Rt $rt;
    private User $userRt;
    private VerifikasiRtService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc    = app(VerifikasiRtService::class);
        $this->rt     = Rt::factory()->create();
        $this->userRt = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => $this->rt->id, 'id_kel' => $this->rt->id_kelurahan]);
    }

    private function anak(string $nik, array $o = []): Anak
    {
        return Anak::create(array_merge([
            'nama' => 'Anak '.$nik, 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'operasi_timbang',
            'id_kel' => $this->rt->id_kelurahan, 'id_rt' => $this->rt->id,
        ], $o));
    }

    public function test_faskes_melihat_antrean_kelurahannya_saja(): void
    {
        $sini = $this->anak('3201000000009201');
        $kelLain = Kelurahan::factory()->create();
        $rtLain  = Rt::factory()->create(['id_kelurahan' => $kelLain->id]);
        $userLain = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => $rtLain->id, 'id_kel' => $kelLain->id]);
        $sana = $this->anak('3201000000009202', ['id_kel' => $kelLain->id, 'id_rt' => $rtLain->id]);
        $this->svc->usulkan($sini, $this->rt, $this->userRt, 'pindah');
        $this->svc->usulkan($sana, $rtLain, $userLain, 'meninggal');

        $faskes = User::factory()->create(['type' => 1, 'id_kel' => $this->rt->id_kelurahan]);
        $this->actingAs($faskes)->get(route('admin.verifikasiRt.index'))
            ->assertOk()
            ->assertSee('Anak 3201000000009201')
            ->assertDontSee('Anak 3201000000009202');

        $super = User::factory()->create(['type' => 0]);
        $this->actingAs($super)->get(route('admin.verifikasiRt.index'))
            ->assertOk()
            ->assertSee('Anak 3201000000009201')
            ->assertSee('Anak 3201000000009202');
    }

    public function test_setujui_klaim_mengisi_id_rt(): void
    {
        $anak = $this->anak('3201000000009203', ['id_rt' => null]);
        $v = $this->svc->usulkan($anak, $this->rt, $this->userRt, 'berdomisili');
        $faskes = User::factory()->create(['type' => 1, 'id_kel' => $this->rt->id_kelurahan]);

        $this->actingAs($faskes)->from(route('admin.verifikasiRt.index'))
            ->post(route('admin.verifikasiRt.tinjau', $v), ['setuju' => 1, 'catatan' => 'ok'])
            ->assertRedirect(route('admin.verifikasiRt.index'))
            ->assertSessionHas('success');

        $this->assertSame($this->rt->id, (int) $anak->fresh()->id_rt);
        $this->assertSame('disetujui', $v->fresh()->reviu);
    }

    public function test_tolak_menyimpan_catatan(): void
    {
        $anak = $this->anak('3201000000009204');
        $v = $this->svc->usulkan($anak, $this->rt, $this->userRt, 'meninggal');
        $super = User::factory()->create(['type' => 0]);

        $this->actingAs($super)->from(route('admin.verifikasiRt.index'))
            ->post(route('admin.verifikasiRt.tinjau', $v), ['setuju' => 0, 'catatan' => 'masih hidup, cek ulang'])
            ->assertRedirect(route('admin.verifikasiRt.index'));

        $this->assertSame('ditolak', $v->fresh()->reviu);
        $this->assertSame('masih hidup, cek ulang', $v->fresh()->catatan_reviu);
        $this->assertSame('ditolak', $anak->fresh()->verif_rt_reviu);
    }

    public function test_faskes_kelurahan_lain_ditolak_403(): void
    {
        $anak = $this->anak('3201000000009205');
        $v = $this->svc->usulkan($anak, $this->rt, $this->userRt, 'pindah');
        $faskesLain = User::factory()->create(['type' => 1, 'id_kel' => Kelurahan::factory()->create()->id]);

        $this->actingAs($faskesLain)->post(route('admin.verifikasiRt.tinjau', $v), ['setuju' => 1])->assertForbidden();
        $this->assertSame('diusulkan', $v->fresh()->reviu);
    }

    public function test_akun_rt_tidak_bisa_membuka_antrean(): void
    {
        $this->actingAs($this->userRt)->get(route('admin.verifikasiRt.index'))->assertForbidden();
    }

    public function test_menu_sidebar_menampilkan_verifikasi_rt_untuk_superadmin(): void
    {
        $super = User::factory()->create(['type' => 0]);
        $this->actingAs($super)->get(route('admin.home'))->assertOk()->assertSee(route('admin.verifikasiRt.index'));
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `/d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe vendor/bin/phpunit tests/Feature/VerifikasiRt/ReviuVerifikasiRtTest.php`
Expected: FAIL — `Route [admin.verifikasiRt.index] not defined.`

- [ ] **Step 3: Controller**

```php
<?php
// app/Http/Controllers/VerifikasiRtReviuController.php

namespace App\Http\Controllers;

use App\Models\Rt;
use App\Models\VerifikasiAnak;
use App\Services\VerifikasiRtService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Antrean reviu usulan RT (spec §6.1–6.2). Faskes melihat kelurahannya, superadmin semua.
 */
class VerifikasiRtReviuController extends Controller
{
    public function __construct(private readonly VerifikasiRtService $svc)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $q = $this->svc->antreanQuery($user);

        if ($request->query('rt')) {
            $q->where('id_rt', (int) $request->query('rt'));
        }
        if ($request->query('status') && in_array($request->query('status'), VerifikasiAnak::STATUS, true)) {
            $q->where('status', $request->query('status'));
        }

        $rtList = Rt::query()
            ->when(!$user->isSuperAdmin(), fn ($r) => $r->where('id_kelurahan', (int) $user->id_kel))
            ->orderBy('name')->get();

        return view('admin.verifikasi-rt.index', [
            'antrean' => $q->paginate(50)->withQueryString(),
            'rtList'  => $rtList,
            'label'   => VerifikasiAnak::LABEL_STATUS,
            'filter'  => ['rt' => $request->query('rt'), 'status' => $request->query('status')],
        ]);
    }

    public function tinjau(Request $request, VerifikasiAnak $verifikasi): RedirectResponse
    {
        $data = $request->validate([
            'setuju'  => 'required|boolean',
            'catatan' => 'nullable|string|max:1000',
        ]);

        try {
            $this->svc->tinjau($verifikasi, $request->user(), (bool) $data['setuju'], $data['catatan'] ?? null);
        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.verifikasiRt.index', $request->only('rt', 'status', 'page'))
            ->with('success', $data['setuju'] ? 'Usulan disetujui.' : 'Usulan ditolak.');
    }
}
```

- [ ] **Step 4: Rute & menu**

Di `routes/web.php`, di dalam grup `Route::middleware(['auth', 'is_admin'])->prefix('admin/')`, setelah rute `intervensi-gizi`:

```php
    // Antrean reviu usulan verifikasi RT (spec verifikasi RT §6) — faskes: kelurahannya; superadmin: semua
    Route::get('verifikasi-rt', [App\Http\Controllers\VerifikasiRtReviuController::class, 'index'])->name('admin.verifikasiRt.index');
    Route::post('verifikasi-rt/{verifikasi}/tinjau', [App\Http\Controllers\VerifikasiRtReviuController::class, 'tinjau'])->name('admin.verifikasiRt.tinjau');
```

Sidebar `leftsidebar.blade.php`: pada baris 21 dan 140 (definisi `$dashboard = request()->routeIs(...)`) tambahkan `'admin.verifikasiRt.*'` ke daftar. Di kedua `<ul class="submenu">` blok Dashboard (setelah `<li>` Intervensi Gizi, baris ~42 dan ~154) tambahkan:

```blade
						<li><a href="{{route('admin.verifikasiRt.index')}}" class="{{ request()->routeIs('admin.verifikasiRt.*') ? 'active' : '' }}">Verifikasi RT</a></li>
```

- [ ] **Step 5: View antrean**

```blade
{{-- resources/views/admin/verifikasi-rt/index.blade.php --}}
@extends('admin::layouts.app')

@section('title') Verifikasi RT @endsection

@section('content')
<div class="page-header">
    <div class="row">
        <div class="col-md-12">
            <div class="title">
                <h4>Antrean Verifikasi RT</h4>
                <p class="text-muted mb-0">Usulan status domisili dari RT. Menyetujui klaim "warga RT saya" akan mengisi RT anak; status lain hanya menjadi penanda.</p>
            </div>
        </div>
    </div>
</div>

@if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
@if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif

<div class="card-box mb-3">
    <form method="GET" class="form-inline">
        <select name="rt" class="form-control mr-2">
            <option value="">Semua RT</option>
            @foreach($rtList as $rt)
            <option value="{{ $rt->id }}" {{ (string) $filter['rt'] === (string) $rt->id ? 'selected' : '' }}>{{ $rt->name }}</option>
            @endforeach
        </select>
        <select name="status" class="form-control mr-2">
            <option value="">Semua status</option>
            @foreach($label as $k => $v)
            <option value="{{ $k }}" {{ $filter['status'] === $k ? 'selected' : '' }}>{{ $v }}</option>
            @endforeach
        </select>
        <button class="btn btn-primary">Terapkan</button>
        <a href="{{ route('admin.verifikasiRt.index') }}" class="btn btn-link">Reset</a>
        <span class="ml-auto text-muted">{{ $antrean->total() }} usulan menunggu</span>
    </form>
</div>

<div class="card-box">
    <div class="table-responsive">
        <table class="table table-striped table-sm">
            <thead>
                <tr><th>Anak</th><th>Wilayah</th><th>Usulan RT</th><th>Catatan</th><th>Diusulkan</th><th style="width:260px">Tinjauan</th></tr>
            </thead>
            <tbody>
            @forelse($antrean as $v)
                <tr>
                    <td>
                        <b>{{ $v->anak->nama }}</b><br>
                        <small class="text-muted">NIK {{ $v->anak->nik }} · lahir {{ $v->anak->tgl_lahir }} · sumber {{ $v->anak->sumber }}</small>
                    </td>
                    <td>{{ $v->rt->name }}<br><small class="text-muted">{{ $v->anak->alamat ?: '-' }}</small></td>
                    <td>
                        <span class="badge badge-{{ $v->status === 'berdomisili' ? 'success' : ($v->status === 'meninggal' ? 'danger' : 'warning') }}">{{ $label[$v->status] }}</span>
                        @if($v->klaim_id_rt) <br><small class="text-muted">klaim: masukkan ke {{ $v->rt->name }}</small> @endif
                    </td>
                    <td>{{ $v->catatan ?: '-' }}</td>
                    <td><small>{{ $v->pengusul?->name }}<br>{{ $v->diusulkan_at?->format('d/m/Y H:i') }}</small></td>
                    <td>
                        <form method="POST" action="{{ route('admin.verifikasiRt.tinjau', $v) }}" class="form-inline">
                            @csrf
                            <input type="hidden" name="rt" value="{{ $filter['rt'] }}">
                            <input type="hidden" name="status" value="{{ $filter['status'] }}">
                            <input type="text" name="catatan" class="form-control form-control-sm mr-1 mb-1" placeholder="Catatan reviu" maxlength="1000" style="width:100%">
                            <button class="btn btn-sm btn-success mr-1" name="setuju" value="1">Setujui</button>
                            <button class="btn btn-sm btn-outline-danger" name="setuju" value="0">Tolak</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada usulan yang menunggu.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $antrean->onEachSide(1)->links('pagination::bootstrap-4') }}
</div>
@endsection
```

Perhatikan pola `@section('title') Verifikasi RT @endsection` **dengan spasi** (CLAUDE.md § Blade `@endsection`).

- [ ] **Step 6: Jalankan tes, pastikan lulus**

Run: `/d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe artisan view:clear && /d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe vendor/bin/phpunit tests/Feature/VerifikasiRt/ReviuVerifikasiRtTest.php`
Expected: `OK (6 tests)`.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/VerifikasiRtReviuController.php resources/views/admin/verifikasi-rt/index.blade.php routes/web.php resources/views/vendor/admin/layouts/partials/leftsidebar.blade.php tests/Feature/VerifikasiRt/ReviuVerifikasiRtTest.php
git commit -m "feat(verifikasi-rt): antrean reviu usulan RT untuk puskesmas/Dinkes"
```

---

### Task 8: Penjaga regresi dasbor OT & suite penuh

**Files:**
- Modify: `tests/Feature/TimbangDashboardTerkunciTest.php` (tambah satu tes)

**Interfaces:**
- Consumes: `VerifikasiRtService::usulkan()/tinjau()`; endpoint `admin.timbang.ringkasan`, `admin.timbang.gizi`, `admin.timbang.daftar`.

- [ ] **Step 1: Tulis tes penjaga**

Tambahkan di akhir kelas `TimbangDashboardTerkunciTest` (impor `App\Models\Rt`, `App\Services\VerifikasiRtService`):

```php
    /** Spec verifikasi RT §1.1: tag pindah/meninggal yang disetujui TIDAK mengubah angka dasbor OT. */
    public function test_verifikasi_rt_tidak_mengubah_angka_dasbor_ot(): void
    {
        $super = User::factory()->create(['type' => 0]);
        $rt    = Rt::factory()->create();
        $rtUser = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => $rt->id, 'id_kel' => $rt->id_kelurahan]);
        $a = $this->anakOt('3201000000005101', -2.5, ['id_rt' => $rt->id, 'id_kel' => $rt->id_kelurahan]);
        $b = $this->anakOt('3201000000005102', -2.5, ['id_rt' => $rt->id, 'id_kel' => $rt->id_kelurahan]);

        $sebelum = [
            $this->actingAs($super)->getJson(route('admin.timbang.ringkasan'))->json(),
            $this->actingAs($super)->getJson(route('admin.timbang.gizi'))->json(),
            count($this->actingAs($super)->getJson(route('admin.timbang.daftar', ['kategori' => 'stunting']))->json('rows')),
        ];

        $svc = app(VerifikasiRtService::class);
        $svc->tinjau($svc->usulkan($a, $rt, $rtUser, 'pindah'), $super, true);
        $svc->tinjau($svc->usulkan($b, $rt, $rtUser, 'meninggal'), $super, true);

        $sesudah = [
            $this->actingAs($super)->getJson(route('admin.timbang.ringkasan'))->json(),
            $this->actingAs($super)->getJson(route('admin.timbang.gizi'))->json(),
            count($this->actingAs($super)->getJson(route('admin.timbang.daftar', ['kategori' => 'stunting']))->json('rows')),
        ];

        $this->assertSame($sebelum, $sesudah);
        $this->assertSame(2, $sesudah[2]);
    }
```

- [ ] **Step 2: Jalankan tes itu — harus langsung lulus (ini penjaga, bukan fitur baru; bila gagal berarti ada kueri OT yang tersentuh — perbaiki kuerinya, bukan tesnya)**

Run: `/d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe vendor/bin/phpunit tests/Feature/TimbangDashboardTerkunciTest.php`
Expected: semua lulus.

- [ ] **Step 3: Jalankan suite penuh (±5 menit)**

Run: `/d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe vendor/bin/phpunit`
Expected: `OK` (deprecation `@test` yang sudah ada boleh diabaikan). Bila ada yang merah, perbaiki sebelum commit.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/TimbangDashboardTerkunciTest.php
git commit -m "test(verifikasi-rt): penjaga — tag RT tidak mengubah angka dasbor OT"
```

---

## Self-Review (sudah dijalankan penulis plan)

- **Spec coverage T1:** §2 (Task 1–3), §3.1 `verifikasi_anak` & §3.2 `verif_rt_*` (Task 1, 4), §4 tab 1–2 + progres + pencarian/filter (Task 5–6), §6.1 antrean & §6.2 efek `berdomisili`+klaim / tag / `bukan_rt_ini` per RT (Task 4, 7), §8 scope 403 & jejak oleh/at (Task 4, 5, 7), §10 a–d & g (Task 4, 5, 7, 8). `sumber_gabungan`, kandidat, tautan, merge, dan Dasbor Gizi sengaja di luar T1 (T2–T4).
- **Placeholder:** tidak ada TBD/TODO; kerangka blade Task 5 diganti utuh di Task 6.
- **Konsistensi nama:** `rt.verifikasi`, `rt.api.warga`, `rt.api.tanpaRt`, `rt.api.usulkan`, `admin.verifikasiRt.index`, `admin.verifikasiRt.tinjau`; service `wargaQuery/tanpaRtQuery/dalamCakupan/usulkan/tinjau/antreanQuery/segarkanDenormalisasi/progres`; JSON `verif_status/verif_label/verif_reviu/verif_at/hilang/progres` sama di controller, blade, dan tes.

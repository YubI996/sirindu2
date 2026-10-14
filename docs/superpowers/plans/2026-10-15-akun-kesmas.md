# Akun Peran Modul Kesmas (Sub-proyek 1) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tujuh peran akun baru (Pimpinan, Kelurahan, Kader, Puskesmas Kesmas, RS Kesmas, Sarkes) yang bisa dibuat super-admin dan hanya membuka dasbor sesuai lingkupnya — tanpa pernah lolos ke `/admin` lain.

**Architecture:** Satu kelas `App\Support\AksesKesmas` menjawab "boleh apa, sebatas mana" (menggantikan `BatasDataPribadiKesmas`). Rute baca dasbor dipindah dari grup `is_admin` ke grup `auth` + middleware baru `dasbor:{nama}`; nama/URL rute tetap. Controller dasbor Kesmas & Export Kesmas menimpa filter wilayah dengan kunci akun (`kunci + filter`, kunci menang).

**Tech Stack:** Laravel 12, Blade, jQuery, PHPUnit 11 (`RefreshDatabase`, DB `sirindu_testing`), OpenSpout (baca hasil export di tes).

**Spec:** `docs/superpowers/specs/2026-10-14-akun-kesmas-design.md`

## Global Constraints

- Nilai `role` baru persis: `pimpinan`, `kelurahan`, `kader`, `kesmas_puskesmas`, `kesmas_rs`, `sarkes`.
- Peran baru selalu `type = 2`. `IsAdmin` menolak peran baru **apa pun nilai `type`-nya**.
- URL dan nama rute dasbor **tidak berubah** (bookmark lama tetap jalan).
- Akses peran lama (`superadmin`, legacy `type` 0/1, `imunisasi_faskes`, `surveilans_*`, `rt`) tidak berubah; tes yang ada untuk mereka wajib tetap lulus tanpa disunting kecuali disebut di task.
- Kunci lingkup diterapkan di server; query string yang keluar lingkup ditimpa diam-diam (bukan 403).
- Gagal tertutup: peran terbatas dengan lingkup kosong = tanpa akses data.
- Satu akun `kelurahan` per `id_kel` (akun soft-deleted tidak dihitung).
- Pesan galat akun kedua: *"Kelurahan {nama} sudah memiliki akun ({email}). Satu kelurahan hanya boleh memiliki satu akun."*
- Teks beranda Sarkes: *"Akses akun Anda belum diatur. Hubungi Dinas Kesehatan."*
- Setiap tes yang memakai `ImunisasiStatusService` / `WilkerPuskesmas` memanggil `flushCache()` keduanya di `setUp()`; fixture dasbor Kesmas wajib `sasaran_balita_kesmas = 1` (CLAUDE.md).
- Jangan jalankan dua proses PHPUnit bersamaan (DB tes dipakai bersama).
- Setelah mengubah blade: `php artisan view:clear`.

## Penyimpangan kecil dari spec (disengaja)

- Spec §4 menyebut penjaga `export:kesmas`. Rencana ini memakai `dasbor:kesmas` pada rute Export Kesmas + `abort_unless($akses->bolehExport(), 403)` di controller (pola `batas()` yang sudah ada). Perilakunya sama, satu middleware lebih sedikit.
- Di dasbor Imunisasi & Operasi Timbang, tombol export/PJ/peta disembunyikan berdasarkan `terbatas()` (bukan `bolehExport()`), supaya akun `imunisasi_faskes` RS — yang `bolehExport()`-nya false untuk Kesmas — tidak kehilangan tombol yang hari ini ia pakai.

## Review Focus

1. **Akun peran baru ber-`type` 0/1 (data lama/salah isi manual di DB)** — tetap 403 di seluruh `/admin`; dikunci di Task 2 (`IsAdminMenolakPeranBaruTest`).
2. **Akun Kelurahan membuka tautan dasbor berfilter kelurahan lain / kecamatan lain** — angka & registri tetap kelurahannya (atau kosong bila irisan kosong), bukan se-kota; dikunci di Task 4.
3. **Akun Kelurahan mengunduh Export Kesmas dengan `id_kel` lain di URL** — berkas hanya berisi anak kelurahannya; dikunci di Task 4.
4. **Akun Kader / Kelurahan yang lingkupnya kosong (posyandu/kelurahan dihapus atau tak diisi)** — 403 di dasbor, bukan data se-kota; dikunci di Task 1 (unit) dan Task 3 (HTTP).
5. **Super-admin mengganti peran akun `kelurahan` menjadi peran lain lalu membuat akun kelurahan baru** — lingkup lama terhapus dan akun baru tidak ditolak; dikunci di Task 7.

---

## File Structure

| File | Tanggung jawab |
|---|---|
| `app/Support/AksesKesmas.php` (baru) | Sumber tunggal izin & lingkup per akun |
| `app/Support/BatasDataPribadiKesmas.php` (hapus) | Dilebur ke `AksesKesmas` |
| `app/Http/Middleware/AksesDasbor.php` (baru) | Penjaga `dasbor:{nama,...}` |
| `app/Http/Middleware/IsAdmin.php` | Pakai `AksesKesmas::adminSetara()` |
| `bootstrap/app.php` | Alias `dasbor` |
| `routes/web.php` | Grup baru rute baca dasbor + rute beranda Sarkes |
| `app/Models/User.php` | `berandaRoute()`, `nama_sarana` fillable |
| `app/Http/Controllers/Auth/LoginController.php` | Peran terbatas → `berandaRoute()` |
| `app/Http/Controllers/AksesBelumDiaturController.php` + `resources/views/akses-belum-diatur.blade.php` (baru) | Beranda Sarkes |
| `database/migrations/2026_10_15_000001_add_nama_sarana_to_users.php` (baru) | Kolom `nama_sarana` |
| `app/Http/Controllers/KesmasDashboardController.php`, `ExportKesmasController.php` | Kunci lingkup |
| `resources/views/admin/kesmas/dashboard.blade.php`, `partials/_filter.blade.php`, `partials/_sdidtk-ckg-idl.blade.php`, `admin/export/kesmas.blade.php` | UI terkunci, tombol sesuai izin |
| `resources/views/admin/imunisasi/dashboard.blade.php`, `admin/dashboard/timbang.blade.php` | Mode baca Pimpinan |
| `app/Http/Controllers/SasaranImunisasiController.php`, `TimbangDashboardController.php` | Cek `bolehNama()` |
| `resources/views/vendor/admin/layouts/partials/leftsidebar.blade.php` | Menu peran terbatas |
| `app/Http/Requests/Admin/User/storeUserRequest.php`, `app/Repositories/Admin/User/UserRepository.php`, `app/Http/Controllers/AdminController.php` (user/editUser), `resources/views/admin/user/{create,edit,index}.blade.php` | Manajemen akun |
| `tests/Feature/AkunKesmas/*` (baru) | Semua tes sub-proyek ini |

---

### Task 1: `AksesKesmas` menggantikan `BatasDataPribadiKesmas`

**Files:**
- Create: `app/Support/AksesKesmas.php`
- Delete: `app/Support/BatasDataPribadiKesmas.php`
- Modify: `app/Http/Controllers/KesmasDashboardController.php` (use + `index()` baris `registriBoleh` + `registri()`)
- Modify: `app/Http/Controllers/ExportKesmasController.php` (use + `batas()` + `download()`)
- Modify: `resources/views/vendor/admin/layouts/partials/leftsidebar.blade.php:183`
- Modify: `app/Exports/KesmasAnakSheet.php:27` (komentar saja)
- Test: `tests/Feature/AkunKesmas/AksesKesmasTest.php`

**Interfaces:**
- Produces:
  - `AksesKesmas::PERAN_TERBATAS: list<string>` = `['pimpinan','kelurahan','kader','kesmas_puskesmas','kesmas_rs','sarkes']`
  - `AksesKesmas::DASBOR: list<string>` = `['kesmas','imunisasi','timbang','spm']`
  - `AksesKesmas::untuk(?User $user): AksesKesmas`
  - `AksesKesmas::adminSetara(User $user): bool`
  - `AksesKesmas::kelurahanCatchment(int $idPuskesmas): list<int>`
  - `->terbatas(): bool`, `->bolehDasbor(string $nama): bool`, `->bolehNama(): bool`, `->bolehExport(): bool`
  - `->kunciFilter(): array<string,int>` (kunci dasbor: `id_kelurahan` | `id_posyandu` | `id_puskesmas`)
  - `->kunciDataPribadi(): array<string,int>` (kunci registri/export; superset `kunciFilter()`)
  - `->batasKelurahanExport(): ?list<int>` (nilai untuk `$filter['batas_kelurahan']` Export Kesmas)

- [ ] **Step 1: Tulis tes yang gagal**

`tests/Feature/AkunKesmas/AksesKesmasTest.php`:

```php
<?php

namespace Tests\Feature\AkunKesmas;

use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Posyandu;
use App\Models\Puskesmas;
use App\Models\User;
use App\Support\AksesKesmas;
use App\Support\WilkerPuskesmas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Matriks izin per peran (spec akun Kesmas §2–§3). Peran lama wajib identik dengan
 * BatasDataPribadiKesmas yang digantikan (SEC-001).
 */
class AksesKesmasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        WilkerPuskesmas::flushCache();
    }

    private function user(array $attr): User
    {
        return User::factory()->create($attr);
    }

    public function test_admin_setara_bebas_tanpa_kunci(): void
    {
        foreach ([['type' => 0, 'role' => 'superadmin'], ['type' => 1, 'role' => null], ['type' => 0, 'role' => null]] as $attr) {
            $a = AksesKesmas::untuk($this->user($attr));
            $this->assertFalse($a->terbatas());
            foreach (AksesKesmas::DASBOR as $d) {
                $this->assertTrue($a->bolehDasbor($d), $d);
            }
            $this->assertTrue($a->bolehNama());
            $this->assertTrue($a->bolehExport());
            $this->assertSame([], $a->kunciFilter());
            $this->assertNull($a->batasKelurahanExport());
        }
    }

    public function test_imunisasi_faskes_mengikuti_aturan_lama(): void
    {
        $pkm = Puskesmas::factory()->create(['name' => 'Bontang Utara 1']);
        $pusk = AksesKesmas::untuk($this->user(['type' => 1, 'role' => 'imunisasi_faskes', 'faskes_type' => 'puskesmas', 'id_puskesmas' => $pkm->id]));
        $this->assertTrue($pusk->bolehNama());
        $this->assertTrue($pusk->bolehExport());
        $this->assertSame([], $pusk->kunciFilter(), 'angka dasbor tetap se-kota');
        $this->assertSame(['id_puskesmas' => $pkm->id], $pusk->kunciDataPribadi());

        $rs = AksesKesmas::untuk($this->user(['type' => 1, 'role' => 'imunisasi_faskes', 'faskes_type' => 'rs']));
        $this->assertFalse($rs->bolehNama());
        $this->assertFalse($rs->bolehExport());
        $this->assertTrue($rs->bolehDasbor('kesmas'));
    }

    public function test_rt_dan_user_biasa_tanpa_akses(): void
    {
        foreach ([['type' => 2, 'role' => 'rt'], ['type' => 2, 'role' => null]] as $attr) {
            $a = AksesKesmas::untuk($this->user($attr));
            $this->assertFalse($a->bolehDasbor('kesmas'));
            $this->assertFalse($a->bolehNama());
        }
        $this->assertFalse(AksesKesmas::untuk(null)->bolehDasbor('kesmas'));
    }

    public function test_pimpinan_semua_dasbor_nama_tanpa_export(): void
    {
        $a = AksesKesmas::untuk($this->user(['type' => 2, 'role' => 'pimpinan']));
        $this->assertTrue($a->terbatas());
        foreach (AksesKesmas::DASBOR as $d) {
            $this->assertTrue($a->bolehDasbor($d), $d);
        }
        $this->assertTrue($a->bolehNama());
        $this->assertFalse($a->bolehExport());
        $this->assertSame([], $a->kunciFilter());
    }

    public function test_kelurahan_dikunci_dan_boleh_export(): void
    {
        $kel = Kelurahan::factory()->create();
        $a = AksesKesmas::untuk($this->user(['type' => 2, 'role' => 'kelurahan', 'id_kel' => $kel->id]));
        $this->assertTrue($a->bolehDasbor('kesmas'));
        $this->assertFalse($a->bolehDasbor('imunisasi'));
        $this->assertTrue($a->bolehNama());
        $this->assertTrue($a->bolehExport());
        $this->assertSame(['id_kelurahan' => $kel->id], $a->kunciFilter());
        $this->assertSame([$kel->id], $a->batasKelurahanExport());
    }

    public function test_kader_dan_puskesmas_dikunci_tanpa_export(): void
    {
        $pos = Posyandu::factory()->create();
        $kader = AksesKesmas::untuk($this->user(['type' => 2, 'role' => 'kader', 'id_posyandu' => $pos->id]));
        $this->assertSame(['id_posyandu' => $pos->id], $kader->kunciFilter());
        $this->assertTrue($kader->bolehNama());
        $this->assertFalse($kader->bolehExport());

        $pkm = Puskesmas::factory()->create();
        $p = AksesKesmas::untuk($this->user(['type' => 2, 'role' => 'kesmas_puskesmas', 'id_puskesmas' => $pkm->id]));
        $this->assertSame(['id_puskesmas' => $pkm->id], $p->kunciFilter());
        $this->assertFalse($p->bolehExport());
    }

    public function test_rs_kesmas_angka_saja(): void
    {
        $a = AksesKesmas::untuk($this->user(['type' => 2, 'role' => 'kesmas_rs', 'id_rs' => 1]));
        $this->assertTrue($a->bolehDasbor('kesmas'));
        $this->assertFalse($a->bolehDasbor('timbang'));
        $this->assertFalse($a->bolehNama());
        $this->assertFalse($a->bolehExport());
    }

    public function test_lingkup_kosong_dan_sarkes_tanpa_akses(): void
    {
        foreach (['kelurahan', 'kader', 'kesmas_puskesmas', 'kesmas_rs', 'sarkes'] as $role) {
            $a = AksesKesmas::untuk($this->user(['type' => 2, 'role' => $role]));
            $this->assertTrue($a->terbatas(), $role);
            $this->assertFalse($a->bolehDasbor('kesmas'), $role);
            $this->assertFalse($a->bolehNama(), $role);
        }
    }

    public function test_admin_setara_tidak_pernah_untuk_peran_terbatas_walau_type_admin(): void
    {
        foreach (AksesKesmas::PERAN_TERBATAS as $role) {
            $this->assertFalse(AksesKesmas::adminSetara($this->user(['type' => 1, 'role' => $role])), $role);
            $this->assertFalse(AksesKesmas::adminSetara($this->user(['type' => 0, 'role' => $role])), $role);
        }
    }

    public function test_catchment_puskesmas_tak_dikenal_tidak_berarti_tanpa_batas(): void
    {
        $kec = Kecamatan::create(['name' => 'Bontang Utara']);
        Kelurahan::create(['name' => 'Bontang Baru', 'id_kecamatan' => $kec->id]);
        $dikenal = Puskesmas::factory()->create(['name' => 'Bontang Utara 1']);
        $asing = Puskesmas::factory()->create(['name' => 'Puskesmas Antah Berantah']);

        $this->assertCount(1, AksesKesmas::kelurahanCatchment($dikenal->id));
        $this->assertSame([0], AksesKesmas::kelurahanCatchment($asing->id));
    }
}
```

Catatan: periksa `database/factories/KelurahanFactory.php` dan `PuskesmasFactory.php` sebelum menjalankan; bila factory butuh kolom lain, ikuti definisinya — jangan ubah factory.

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php vendor/bin/phpunit tests/Feature/AkunKesmas/AksesKesmasTest.php`
Expected: FAIL — `Class "App\Support\AksesKesmas" not found`.

- [ ] **Step 3: Tulis `app/Support/AksesKesmas.php`**

```php
<?php

namespace App\Support;

use App\Models\Puskesmas;
use App\Models\User;

/**
 * Siapa boleh membuka dasbor mana, melihat daftar nama (NIK, nama orang tua, riwayat), dan
 * mengunduh Export Kesmas — dan sebatas wilayah mana. Spec docs/superpowers/specs/2026-10-14-akun-kesmas-design.md.
 *
 * Menggantikan BatasDataPribadiKesmas; aturan lamanya dipertahankan persis (audit Kesmas 2026-10-06, SEC-001):
 * akun `imunisasi_faskes` melihat ANGKA se-kota, data pribadi hanya catchment puskesmas-nya; akun RS atau
 * puskesmas tanpa id_puskesmas ditolak (gagal tertutup).
 *
 * Peran terbatas (PERAN_TERBATAS) TIDAK PERNAH admin-setara, apa pun `type`-nya — kalau lolos IsAdmin,
 * mereka membuka seluruh /admin (Data Anak, Import, Master Data).
 */
final class AksesKesmas
{
    public const PERAN_TERBATAS = ['pimpinan', 'kelurahan', 'kader', 'kesmas_puskesmas', 'kesmas_rs', 'sarkes'];

    public const DASBOR = ['kesmas', 'imunisasi', 'timbang', 'spm'];

    /**
     * @param list<string>       $dasbor
     * @param array<string,int>  $kunci        kunci seluruh dasbor
     * @param array<string,int>  $kunciPribadi kunci registri & export
     */
    private function __construct(
        private readonly bool $terbatas,
        private readonly array $dasbor,
        private readonly bool $nama,
        private readonly bool $export,
        private readonly array $kunci,
        private readonly array $kunciPribadi,
    ) {
    }

    public static function untuk(?User $user): self
    {
        if (!$user) {
            return self::tanpaAkses(false);
        }
        if (in_array($user->role, self::PERAN_TERBATAS, true)) {
            return self::peranTerbatas($user);
        }
        if (!self::adminSetara($user)) {
            return self::tanpaAkses(false);
        }
        if ($user->isFaskesImunisasi()) {
            if ($user->faskes_type === 'puskesmas' && $user->id_puskesmas) {
                return new self(false, self::DASBOR, true, true, [], ['id_puskesmas' => (int) $user->id_puskesmas]);
            }

            return new self(false, self::DASBOR, false, false, [], []);
        }

        return new self(false, self::DASBOR, true, true, [], []);
    }

    /** Aturan IsAdmin sebelum peran Kesmas ada — kini tanpa peran terbatas. */
    public static function adminSetara(User $user): bool
    {
        if (in_array($user->role, self::PERAN_TERBATAS, true)) {
            return false;
        }

        // Sengaja sama persis dengan IsAdmin lama, termasuk perbandingan longgar `type`.
        return $user->isSuperAdmin()
            || $user->isFaskesSurveilans()
            || $user->isFaskesImunisasi()
            || in_array($user->type, [0, 1]);
    }

    private static function peranTerbatas(User $u): self
    {
        return match ($u->role) {
            'pimpinan'         => new self(true, self::DASBOR, true, false, [], []),
            'kelurahan'        => $u->id_kel ? self::lokal(['id_kelurahan' => (int) $u->id_kel], true) : self::tanpaAkses(true),
            'kader'            => $u->id_posyandu ? self::lokal(['id_posyandu' => (int) $u->id_posyandu], false) : self::tanpaAkses(true),
            'kesmas_puskesmas' => $u->id_puskesmas ? self::lokal(['id_puskesmas' => (int) $u->id_puskesmas], false) : self::tanpaAkses(true),
            // RS tak punya wilayah anak: angka se-kota, tanpa nama (SEC-001).
            'kesmas_rs'        => $u->id_rs ? new self(true, ['kesmas'], false, false, [], []) : self::tanpaAkses(true),
            default            => self::tanpaAkses(true), // sarkes: akses belum diputuskan
        };
    }

    /** @param array<string,int> $kunci */
    private static function lokal(array $kunci, bool $export): self
    {
        return new self(true, ['kesmas'], true, $export, $kunci, $kunci);
    }

    private static function tanpaAkses(bool $terbatas): self
    {
        return new self($terbatas, [], false, false, [], []);
    }

    public function terbatas(): bool
    {
        return $this->terbatas;
    }

    public function bolehDasbor(string $nama): bool
    {
        return in_array($nama, $this->dasbor, true);
    }

    public function bolehNama(): bool
    {
        return $this->nama;
    }

    public function bolehExport(): bool
    {
        return $this->export;
    }

    /** @return array<string,int> */
    public function kunciFilter(): array
    {
        return $this->kunci;
    }

    /** @return array<string,int> */
    public function kunciDataPribadi(): array
    {
        return $this->kunciPribadi;
    }

    /** @return list<int>|null null = tidak dibatasi */
    public function batasKelurahanExport(): ?array
    {
        if (isset($this->kunciPribadi['id_kelurahan'])) {
            return [$this->kunciPribadi['id_kelurahan']];
        }
        if (isset($this->kunciPribadi['id_puskesmas'])) {
            return self::kelurahanCatchment($this->kunciPribadi['id_puskesmas']);
        }

        return null;
    }

    /**
     * ID kelurahan catchment sebuah puskesmas. Kosong → [0] supaya `whereIn` tak menghasilkan apa pun
     * (puskesmas yang namanya tak dikenal tabel wilker tidak boleh berarti "tanpa batas").
     *
     * @return list<int>
     */
    public static function kelurahanCatchment(int $idPuskesmas): array
    {
        $nama = Puskesmas::whereKey($idPuskesmas)->value('name');
        $ids = $nama ? WilkerPuskesmas::catchmentKelurahanIds($nama) : [];

        return $ids ?: [0];
    }
}
```

- [ ] **Step 4: Pindahkan pemanggil lama**

`KesmasDashboardController.php`: ganti `use App\Support\BatasDataPribadiKesmas;` → `use App\Support\AksesKesmas;`. Di `index()`, ganti baris
`'registriBoleh' => BatasDataPribadiKesmas::untuk(auth()->user())['boleh'],` → `'registriBoleh' => AksesKesmas::untuk(auth()->user())->bolehNama(),`.
Di `registri()` ganti blok

```php
        $batas = BatasDataPribadiKesmas::untuk(auth()->user());
        abort_unless($batas['boleh'], 403, 'Akses tidak diizinkan.');

        [$periode, $filters, $usia] = $this->parse($request);
        // Batas wilayah akun menang atas pilihan klien; filter lain tetap AND, jadi hanya bisa mempersempit.
        if ($batas['puskesmas']) {
            $filters['id_puskesmas'] = $batas['puskesmas'];
        }
```

dengan

```php
        $akses = AksesKesmas::untuk(auth()->user());
        abort_unless($akses->bolehNama(), 403, 'Akses tidak diizinkan.');

        [$periode, $filters, $usia] = $this->parse($request);
        // Batas wilayah akun menang atas pilihan klien; filter lain tetap AND, jadi hanya bisa mempersempit.
        $filters = $akses->kunciDataPribadi() + $filters;
```

`ExportKesmasController.php`: ganti use; ubah `batas()` menjadi

```php
    private function akses(): AksesKesmas
    {
        $akses = AksesKesmas::untuk(auth()->user());
        abort_unless($akses->bolehExport(), 403, 'Akses tidak diizinkan.');

        return $akses;
    }
```

panggil `$this->akses();` di `index()`, dan di `download()` ganti

```php
        $batas = $this->batas();
        $filter = $this->filter($request);
        // Bukan input klien: ditambahkan SETELAH validasi supaya tak bisa dilewati lewat query string.
        if ($batas['puskesmas']) {
            $filter['batas_kelurahan'] = BatasDataPribadiKesmas::kelurahanCatchment($batas['puskesmas']);
        }
```

dengan

```php
        $akses = $this->akses();
        $filter = $this->filter($request);
        // Bukan input klien: ditambahkan SETELAH validasi supaya tak bisa dilewati lewat query string.
        if (($batas = $akses->batasKelurahanExport()) !== null) {
            $filter['batas_kelurahan'] = $batas;
        }
```

Perbarui docblock kelas kedua controller: sebut `AksesKesmas`.

`leftsidebar.blade.php:183`: `@if (\App\Support\BatasDataPribadiKesmas::untuk(Auth::user())['boleh'])` → `@if (\App\Support\AksesKesmas::untuk(Auth::user())->bolehExport())`.

`KesmasAnakSheet.php:27` komentar: `lihat BatasDataPribadiKesmas` → `lihat AksesKesmas::batasKelurahanExport`.

Hapus `app/Support/BatasDataPribadiKesmas.php`, lalu pastikan tak ada sisa:

Run: `grep -rn "BatasDataPribadiKesmas" app resources routes tests`
Expected: hanya nama kelas tes `BatasDataPribadiFaskesKesmasTest` (nama berkas tes, bukan pemakaian kelas).

- [ ] **Step 5: Jalankan tes baru + regresi**

Run: `php artisan view:clear; php vendor/bin/phpunit tests/Feature/AkunKesmas/AksesKesmasTest.php tests/Feature/Kesmas/BatasDataPribadiFaskesKesmasTest.php tests/Feature/Kesmas/KesmasSwarmAksesTest.php tests/Feature/Kesmas/ExportKesmasTest.php tests/Feature/Kesmas/KesmasDashboardControllerTest.php`
Expected: semua PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Support/AksesKesmas.php app/Http/Controllers/KesmasDashboardController.php app/Http/Controllers/ExportKesmasController.php app/Exports/KesmasAnakSheet.php resources/views/vendor/admin/layouts/partials/leftsidebar.blade.php tests/Feature/AkunKesmas/AksesKesmasTest.php
git rm app/Support/BatasDataPribadiKesmas.php
git commit -m "feat(akun-kesmas): AksesKesmas menggantikan BatasDataPribadiKesmas"
```

---

### Task 2: Pengerasan `IsAdmin`, `type`, beranda, login, dan halaman Sarkes

**Files:**
- Modify: `app/Http/Middleware/IsAdmin.php` (`handle`)
- Modify: `app/Repositories/Admin/User/UserRepository.php:32-40` (`typeFromRole`)
- Modify: `app/Models/User.php` (`$fillable` + `berandaRoute()`)
- Modify: `app/Http/Controllers/Auth/LoginController.php` (match login)
- Create: `database/migrations/2026_10_15_000001_add_nama_sarana_to_users.php`
- Create: `app/Http/Controllers/AksesBelumDiaturController.php`
- Create: `resources/views/akses-belum-diatur.blade.php`
- Modify: `routes/web.php` (rute `akses.belumDiatur`)
- Test: `tests/Feature/AkunKesmas/IsAdminMenolakPeranBaruTest.php`, `tests/Feature/AkunKesmas/BerandaPeranKesmasTest.php`

**Interfaces:**
- Consumes: `AksesKesmas::PERAN_TERBATAS`, `AksesKesmas::adminSetara()`, `->terbatas()` (Task 1)
- Produces: rute `akses.belumDiatur` (GET `/akses-belum-diatur`); `User::berandaRoute()` mengembalikan `'admin.kesmas.dashboard'` untuk peran terbatas selain sarkes, `'akses.belumDiatur'` untuk sarkes; kolom `users.nama_sarana`.

- [ ] **Step 1: Tulis tes yang gagal**

`tests/Feature/AkunKesmas/IsAdminMenolakPeranBaruTest.php`:

```php
<?php

namespace Tests\Feature\AkunKesmas;

use App\Models\User;
use App\Support\AksesKesmas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Peran Kesmas tak boleh lolos ke /admin umum, termasuk bila `type` di DB terisi 0/1. */
class IsAdminMenolakPeranBaruTest extends TestCase
{
    use RefreshDatabase;

    public static function peranDanType(): array
    {
        $kasus = [];
        foreach (AksesKesmas::PERAN_TERBATAS as $role) {
            foreach ([0, 1, 2] as $type) {
                $kasus["$role type $type"] = [$role, $type];
            }
        }

        return $kasus;
    }

    /** @dataProvider peranDanType */
    public function test_rute_admin_umum_403(string $role, int $type): void
    {
        $user = User::factory()->create(['type' => $type, 'role' => $role, 'id_kel' => 1, 'id_posyandu' => 1, 'id_puskesmas' => 1, 'id_rs' => 1]);

        foreach (['admin.home', 'admin.anak', 'admin.importCsv.index', 'admin.masterdata.spm.index'] as $rute) {
            $this->actingAs($user)->get(route($rute))->assertForbidden();
        }
    }

    public function test_admin_lama_tetap_lolos(): void
    {
        foreach ([['type' => 1, 'role' => null], ['type' => 0, 'role' => 'superadmin'], ['type' => 1, 'role' => 'imunisasi_faskes']] as $attr) {
            $this->actingAs(User::factory()->create($attr))->get(route('admin.home'))->assertOk();
        }
    }
}
```

`tests/Feature/AkunKesmas/BerandaPeranKesmasTest.php`:

```php
<?php

namespace Tests\Feature\AkunKesmas;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BerandaPeranKesmasTest extends TestCase
{
    use RefreshDatabase;

    public function test_kolom_nama_sarana_ada(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'nama_sarana'));
    }

    public function test_beranda_per_peran(): void
    {
        foreach (['pimpinan', 'kelurahan', 'kader', 'kesmas_puskesmas', 'kesmas_rs'] as $role) {
            $this->assertSame('admin.kesmas.dashboard', (new User(['role' => $role]))->berandaRoute(), $role);
        }
        $this->assertSame('akses.belumDiatur', (new User(['role' => 'sarkes']))->berandaRoute());
        $this->assertSame('rt.verifikasi', (new User(['role' => 'rt']))->berandaRoute());
        $this->assertSame('admin.home', (new User(['role' => 'superadmin']))->berandaRoute());
    }

    public function test_login_peran_terbatas_ke_berandanya(): void
    {
        config(['services.recaptcha.enabled' => false]);
        User::factory()->create(['email' => 'pim@sirindu.test', 'password' => bcrypt('rahasia123'), 'type' => 2, 'role' => 'pimpinan']);

        $this->post(route('login'), ['email' => 'pim@sirindu.test', 'password' => 'rahasia123'])
            ->assertRedirect(route('admin.kesmas.dashboard'));
    }

    public function test_sarkes_melihat_halaman_akses_belum_diatur(): void
    {
        $user = User::factory()->create(['type' => 2, 'role' => 'sarkes', 'nama_sarana' => 'Klinik Pratama Sehat']);

        $this->actingAs($user)->get(route('akses.belumDiatur'))
            ->assertOk()
            ->assertSee('Akses akun Anda belum diatur. Hubungi Dinas Kesehatan.')
            ->assertSee('Klinik Pratama Sehat');
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('akses.belumDiatur'))->assertRedirect(route('login'));
    }
}
```

Catatan: periksa nama rute login (`php artisan route:list --name=login`); bila POST login bernama lain, sesuaikan `route('login')`. `test_login_peran_terbatas_ke_berandanya` hanya memeriksa redirect — rute dasbor Kesmas belum dibuka untuk pimpinan sampai Task 3.

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php vendor/bin/phpunit tests/Feature/AkunKesmas/IsAdminMenolakPeranBaruTest.php tests/Feature/AkunKesmas/BerandaPeranKesmasTest.php`
Expected: FAIL — rute admin 200 untuk peran `type` 0/1, kolom `nama_sarana` tidak ada, rute `akses.belumDiatur` tidak ada.

- [ ] **Step 3: Implementasi**

`IsAdmin::handle()` — ganti seluruh isi badan dengan:

```php
        // Satu sumber aturan: AksesKesmas::adminSetara() — menolak peran Kesmas apa pun `type`-nya.
        if (\App\Support\AksesKesmas::adminSetara(auth()->user())) {
            return $next($request);
        }

        abort(403, 'Unauthorized action.');
```

`UserRepository::typeFromRole()`:

```php
    private function typeFromRole(string $role): int
    {
        // 0 = superadmin, 1 = admin/faskes (akses /admin), 2 = user biasa (tidak boleh /admin).
        // Daftar 1 ditulis eksplisit: peran yang ditambahkan nanti TIDAK diam-diam menjadi admin.
        return match ($role) {
            'superadmin' => 0,
            'imunisasi_faskes', 'surveilans_puskesmas', 'surveilans_rs' => 1,
            default => 2,
        };
    }
```

`User.php`: tambah `'nama_sarana', // peran sarkes: nama klinik/praktik (belum ada master)` ke `$fillable`; ganti `berandaRoute()`:

```php
    /** Nama route beranda sesuai peran — dipakai login, guest-redirect, tombol landing, dan halaman 403. */
    public function berandaRoute(): string
    {
        return match (true) {
            $this->isRt() => 'rt.verifikasi',
            $this->role === 'sarkes' => 'akses.belumDiatur',
            in_array($this->role, \App\Support\AksesKesmas::PERAN_TERBATAS, true) => 'admin.kesmas.dashboard',
            default => 'admin.home',
        };
    }
```

`LoginController::login()` — tambahkan sebagai lengan pertama `match(true)`:

```php
                // Peran Kesmas (pimpinan, kelurahan, kader, puskesmas/RS Kesmas, sarkes): beranda sesuai peran
                \App\Support\AksesKesmas::untuk($user)->terbatas() => redirect()->route($user->berandaRoute()),
```

Migrasi `database/migrations/2026_10_15_000001_add_nama_sarana_to_users.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Peran sarkes (sarana kesehatan swasta): belum ada master klinik, jadi namanya teks bebas.
            $table->string('nama_sarana', 150)->nullable()->after('id_rs');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('nama_sarana');
        });
    }
};
```

`app/Http/Controllers/AksesBelumDiaturController.php`:

```php
<?php

namespace App\Http\Controllers;

/** Beranda akun yang aksesnya belum diputuskan (peran sarkes) — spec akun Kesmas §5. */
class AksesBelumDiaturController extends Controller
{
    public function __invoke()
    {
        return view('akses-belum-diatur', ['user' => auth()->user()]);
    }
}
```

`resources/views/akses-belum-diatur.blade.php` (berdiri sendiri — tanpa layout admin yang menaut ke halaman terlarang):

```blade
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Akses belum diatur · SIRINDU</title>
    <style>
        body { margin:0; font-family:'Barlow', system-ui, sans-serif; background:#f6f8f6; color:#1f2a1f; }
        main { max-width:32rem; margin:12vh auto; padding:2rem 1.5rem; background:#fff; border:1px solid #d9e2d9; border-radius:8px; }
        h1 { font-size:1.35rem; margin:0 0 1rem; }
        p { line-height:1.55; margin:.4rem 0; }
        .muted { color:#535a53; }
        button { margin-top:1.5rem; padding:.6rem 1.1rem; border:0; border-radius:6px; background:#1d6b3a; color:#fff; font:inherit; cursor:pointer; }
    </style>
</head>
<body>
<main>
    <img src="{{ asset('logo/Sirindu-allblack.png') }}" alt="SIRINDU" height="36">
    <h1>Akses akun Anda belum diatur. Hubungi Dinas Kesehatan.</h1>
    <p><strong>{{ $user->name }}</strong></p>
    @if ($user->nama_sarana)
        <p class="muted">{{ $user->nama_sarana }}</p>
    @endif
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Keluar</button>
    </form>
</main>
</body>
</html>
```

`routes/web.php` — tambahkan tepat sebelum blok komentar "All Super Admin Routes List":

```php
// Beranda akun yang aksesnya belum diputuskan (peran sarkes) — spec akun Kesmas §5.
Route::get('akses-belum-diatur', App\Http\Controllers\AksesBelumDiaturController::class)
     ->middleware('auth')->name('akses.belumDiatur');
```

- [ ] **Step 4: Migrasi + jalankan tes**

Run: `php artisan migrate; php artisan view:clear; php vendor/bin/phpunit tests/Feature/AkunKesmas tests/Feature/LoginSudahMasukTest.php tests/Feature/VerifikasiRt/BuatAkunRtTest.php`
Expected: semua PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Middleware/IsAdmin.php app/Repositories/Admin/User/UserRepository.php app/Models/User.php app/Http/Controllers/Auth/LoginController.php database/migrations/2026_10_15_000001_add_nama_sarana_to_users.php app/Http/Controllers/AksesBelumDiaturController.php resources/views/akses-belum-diatur.blade.php routes/web.php tests/Feature/AkunKesmas
git commit -m "feat(akun-kesmas): IsAdmin menolak peran Kesmas, beranda per peran, halaman Sarkes"
```

---

### Task 3: Middleware `dasbor` dan pemindahan rute baca dasbor

**Files:**
- Create: `app/Http/Middleware/AksesDasbor.php`
- Modify: `bootstrap/app.php` (alias)
- Modify: `routes/web.php` (pindahkan rute; lihat daftar)
- Test: `tests/Feature/AkunKesmas/MatriksAksesDasborTest.php`

**Interfaces:**
- Consumes: `AksesKesmas::untuk()->bolehDasbor()` (Task 1); `berandaRoute()` (Task 2)
- Produces: alias middleware `dasbor` dengan parameter variadik (`dasbor:kesmas,imunisasi,timbang` = lolos bila salah satu boleh).

- [ ] **Step 1: Tulis tes matriks yang gagal**

`tests/Feature/AkunKesmas/MatriksAksesDasborTest.php`:

```php
<?php

namespace Tests\Feature\AkunKesmas;

use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Posyandu;
use App\Models\Puskesmas;
use App\Models\User;
use App\Services\ImunisasiStatusService;
use App\Support\WilkerPuskesmas;
use Database\Seeders\JenisVaksinSeeder;
use Database\Seeders\KelompokVaksinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Spec akun Kesmas §2 & §4: setiap peran baru × setiap pintu dasbor. */
class MatriksAksesDasborTest extends TestCase
{
    use RefreshDatabase;

    private array $lingkup;

    protected function setUp(): void
    {
        parent::setUp();
        ImunisasiStatusService::flushCache();
        WilkerPuskesmas::flushCache();
        $this->seed(JenisVaksinSeeder::class);
        $this->seed(KelompokVaksinSeeder::class);

        $kec = Kecamatan::create(['name' => 'Bontang Utara']);
        $kel = Kelurahan::create(['name' => 'Bontang Baru', 'id_kecamatan' => $kec->id]);
        $pkm = Puskesmas::factory()->create(['name' => 'Bontang Utara 1']);
        $pos = Posyandu::factory()->create(['id_puskesmas' => $pkm->id]);
        $this->lingkup = ['id_kel' => $kel->id, 'id_posyandu' => $pos->id, 'id_puskesmas' => $pkm->id, 'id_rs' => 1, 'kel' => $kel->id];
    }

    private function akun(string $role): User
    {
        $l = $this->lingkup;
        $attr = match ($role) {
            'kelurahan' => ['id_kel' => $l['id_kel']],
            'kader' => ['id_posyandu' => $l['id_posyandu']],
            'kesmas_puskesmas' => ['id_puskesmas' => $l['id_puskesmas']],
            'kesmas_rs' => ['id_rs' => $l['id_rs']],
            default => [],
        };

        return User::factory()->create(['type' => 2, 'role' => $role] + $attr);
    }

    /** @return array<string, array{0:string,1:array}> nama kunci => [nama rute, parameter] */
    private function pintu(): array
    {
        return [
            'kesmas'          => ['admin.kesmas.dashboard', []],
            'kesmas_registri' => ['admin.kesmas.registri', []],
            'export_kesmas'   => ['admin.export.kesmas.index', []],
            'imunisasi'       => ['admin.imunisasiDashboard', []],
            'imun_sasaran'    => ['admin.imunisasiDashboard.sasaran', ['kelompok' => 'bbl']],
            'imun_export'     => ['admin.imunisasiDashboard.sasaranExport', ['kelompok' => 'bbl']],
            'timbang'         => ['admin.timbang.dashboard', []],
            'timbang_ring'    => ['admin.timbang.ringkasan', []],
            'timbang_daftar'  => ['admin.timbang.daftar', []],
            'timbang_export'  => ['admin.timbang.daftar.export', []],
            'spm'             => ['admin.spm.dashboard', []],
            'rt_by_kel'       => ['admin.getRtByKelAnak', ['id' => $this->lingkup['kel']]],
            'anak'            => ['admin.anak', []],
        ];
    }

    public static function harapan(): array
    {
        $tolakSemua = array_fill_keys(['kesmas', 'kesmas_registri', 'export_kesmas', 'imunisasi', 'imun_sasaran', 'imun_export', 'timbang', 'timbang_ring', 'timbang_daftar', 'timbang_export', 'spm', 'rt_by_kel', 'anak'], 403);
        $hanyaKesmas = array_merge($tolakSemua, ['kesmas' => 200, 'kesmas_registri' => 200, 'rt_by_kel' => 200]);

        return [
            'pimpinan' => ['pimpinan', array_merge($tolakSemua, [
                'kesmas' => 200, 'kesmas_registri' => 200, 'imunisasi' => 200, 'imun_sasaran' => 200,
                'timbang' => 200, 'timbang_ring' => 200, 'timbang_daftar' => 200, 'spm' => 200, 'rt_by_kel' => 200,
            ])],
            'kelurahan' => ['kelurahan', array_merge($hanyaKesmas, ['export_kesmas' => 200])],
            'kader' => ['kader', $hanyaKesmas],
            'kesmas_puskesmas' => ['kesmas_puskesmas', $hanyaKesmas],
            'kesmas_rs' => ['kesmas_rs', array_merge($tolakSemua, ['kesmas' => 200, 'rt_by_kel' => 200])],
            'sarkes' => ['sarkes', $tolakSemua],
        ];
    }

    /** @dataProvider harapan */
    public function test_matriks(string $role, array $harapan): void
    {
        $user = $this->akun($role);
        foreach ($this->pintu() as $kunci => [$rute, $param]) {
            $status = $this->actingAs($user)->getJson(route($rute, $param))->status();
            $this->assertSame($harapan[$kunci], $status, "$role → $kunci ($rute)");
        }
    }

    public function test_tulis_ot_ditolak_untuk_pimpinan(): void
    {
        $user = $this->akun('pimpinan');
        $this->actingAs($user)->postJson(route('admin.timbang.publikasi'), ['aktif' => 1])->assertForbidden();
    }

    public function test_admin_lama_tetap_membuka_semua_pintu(): void
    {
        $admin = User::factory()->create(['type' => 1, 'role' => null]);
        foreach (['admin.kesmas.dashboard', 'admin.imunisasiDashboard', 'admin.timbang.dashboard', 'admin.spm.dashboard', 'admin.export.kesmas.index'] as $rute) {
            $this->actingAs($admin)->get(route($rute))->assertOk();
        }
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('admin.timbang.dashboard'))->assertRedirect(route('login'));
        $this->getJson(route('admin.kesmas.registri'))->assertUnauthorized();
    }
}
```

Catatan: `getJson` dipakai agar 403 berbentuk JSON dan `streamDownload` export tetap mengembalikan status. Bila `admin.timbang.ringkasan` atau `daftar` butuh parameter wajib dan mengembalikan 422 untuk admin juga, tambahkan parameter minimum yang sama dipakai tes OT yang sudah ada (cari `route('admin.timbang.ringkasan'` di `tests/`).

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php vendor/bin/phpunit tests/Feature/AkunKesmas/MatriksAksesDasborTest.php`
Expected: FAIL — peran baru 403 di `kesmas` (masih di grup `is_admin`).

- [ ] **Step 3: Middleware + alias**

`app/Http/Middleware/AksesDasbor.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Support\AksesKesmas;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penjaga rute baca dasbor: `dasbor:kesmas` atau `dasbor:kesmas,imunisasi` (lolos bila salah satu boleh).
 * Admin-setara selalu lolos (perilaku lama grup is_admin); peran Kesmas sesuai AksesKesmas.
 * Spec akun Kesmas §4. Dipasang SETELAH `auth`.
 */
class AksesDasbor
{
    public function handle(Request $request, Closure $next, string ...$dasbor): Response
    {
        $akses = AksesKesmas::untuk($request->user());
        foreach ($dasbor as $nama) {
            if ($akses->bolehDasbor($nama)) {
                return $next($request);
            }
        }

        abort(403, 'Akses tidak diizinkan.');
    }
}
```

`bootstrap/app.php` — tambahkan ke array alias: `'dasbor' => \App\Http\Middleware\AksesDasbor::class,`

- [ ] **Step 4: Pindahkan rute**

Di dalam grup `Route::middleware(['auth', 'is_admin'])->prefix('admin/')`, **hapus** baris-baris ini (hanya baris ini):
- `get-kel-dasar-anak/{id}` (`admin.getKelAnak`), `get-rt-by-kel-anak/{id}` (`admin.getRtByKelAnak`), `get-posyandu-by-kel-anak/{id}` (`admin.getPosyanduByKelAnak`)
- di prefix `timbang-dashboard`: `/` (`admin.timbang.dashboard`), `api/ringkasan`, `api/gizi`, `api/tren`, `api/coverage`, `api/program`, `api/peringkat`, `api/daftar` — **biarkan** `api/daftar/export`, `api/anak/{anak}/pj`, `publikasi`
- `imunisasi-dashboard` (`admin.imunisasiDashboard`) dan `imunisasi-dashboard/sasaran` (`admin.imunisasiDashboard.sasaran`) — **biarkan** `sasaran/export`
- `spm-dashboard` (`admin.spm.dashboard`)
- seluruh `Route::prefix('export-kesmas')` dan `Route::prefix('kesmas-dashboard')`

Tambahkan grup baru tepat **setelah** penutup grup `is_admin`:

```php
/*------------------------------------------
Rute BACA dasbor — admin-setara + peran Kesmas (spec docs/superpowers/specs/2026-10-14-akun-kesmas-design.md §4).
Nama & URL sama dengan saat masih di grup is_admin. Rute ekspor/tulis dasbor tetap di grup is_admin.
--------------------------------------------*/
Route::middleware(['auth'])->prefix('admin/')->group(function () {
    // Dropdown wilayah yang dipanggil JS dasbor (dan form admin).
    Route::middleware('dasbor:kesmas,imunisasi,timbang')->group(function () {
        Route::get('get-kel-dasar-anak/{id}', [App\Http\Controllers\AdminController::class, 'getKelAnak'])->name('admin.getKelAnak');
        Route::get('get-rt-by-kel-anak/{id}', [App\Http\Controllers\AdminController::class, 'getRtByKelAnak'])->name('admin.getRtByKelAnak');
        Route::get('get-posyandu-by-kel-anak/{id}', [App\Http\Controllers\AdminController::class, 'getPosyanduByKelAnak'])->name('admin.getPosyanduByKelAnak');
    });

    Route::middleware('dasbor:kesmas')->group(function () {
        // Dasbor Kesmas — spec docs/superpowers/specs/2026-09-21-dasbor-kesmas-design.md
        Route::prefix('kesmas-dashboard')->group(function () {
            Route::get('/', [App\Http\Controllers\KesmasDashboardController::class, 'index'])
                 ->name('admin.kesmas.dashboard');
            Route::get('api/registri', [App\Http\Controllers\KesmasDashboardController::class, 'registri'])
                 ->name('admin.kesmas.registri');
        });
        // Export Kesmas — izin & batas wilayah dicek di controller (AksesKesmas::bolehExport).
        Route::prefix('export-kesmas')->group(function () {
            Route::get('/', [App\Http\Controllers\ExportKesmasController::class, 'index'])
                 ->name('admin.export.kesmas.index');
            Route::get('download', [App\Http\Controllers\ExportKesmasController::class, 'download'])
                 ->name('admin.export.kesmas.download');
        });
    });

    Route::middleware('dasbor:imunisasi')->group(function () {
        Route::get('imunisasi-dashboard', [App\Http\Controllers\AdminController::class, 'imunisasiDashboard'])->name('admin.imunisasiDashboard');
        Route::get('imunisasi-dashboard/sasaran', [App\Http\Controllers\SasaranImunisasiController::class, 'daftar'])->name('admin.imunisasiDashboard.sasaran');
    });

    Route::middleware('dasbor:timbang')->prefix('timbang-dashboard')->group(function () {
        Route::get('/',            [App\Http\Controllers\TimbangDashboardController::class, 'index'])    ->name('admin.timbang.dashboard');
        Route::get('api/ringkasan',[App\Http\Controllers\TimbangDashboardController::class, 'ringkasan'])->name('admin.timbang.ringkasan');
        Route::get('api/gizi',     [App\Http\Controllers\TimbangDashboardController::class, 'gizi'])     ->name('admin.timbang.gizi');
        Route::get('api/tren',     [App\Http\Controllers\TimbangDashboardController::class, 'tren'])     ->name('admin.timbang.tren');
        Route::get('api/coverage', [App\Http\Controllers\TimbangDashboardController::class, 'coverage']) ->name('admin.timbang.coverage');
        Route::get('api/program',  [App\Http\Controllers\TimbangDashboardController::class, 'program'])  ->name('admin.timbang.program');
        Route::get('api/peringkat',[App\Http\Controllers\TimbangDashboardController::class, 'peringkat'])->name('admin.timbang.peringkat');
        Route::get('api/daftar',   [App\Http\Controllers\TimbangDashboardController::class, 'daftar'])   ->name('admin.timbang.daftar');
    });

    // Dasbor SPM — spec docs/superpowers/specs/2026-09-29-dasbor-spm-design.md §5
    Route::middleware('dasbor:spm')->get('spm-dashboard', [App\Http\Controllers\SpmDashboardController::class, 'index'])
         ->name('admin.spm.dashboard');
});
```

Salin baris rute persis dari berkas (nama controller/metode) — kode di atas mengikuti `routes/web.php` per 15 Okt 2026; bila berbeda, yang di berkas yang benar.

Lalu periksa tidak ada nama rute ganda:

Run: `php artisan route:list --json | php -r '$r=json_decode(stream_get_contents(STDIN),true);$n=array_filter(array_column($r,"name"));$d=array_diff_assoc($n,array_unique($n));echo $d?implode("\n",$d):"tidak ada duplikat";'`
Expected: `tidak ada duplikat`

- [ ] **Step 5: Jalankan tes + regresi dasbor**

Run: `php artisan route:clear; php vendor/bin/phpunit tests/Feature/AkunKesmas tests/Feature/Kesmas/KesmasSwarmAksesTest.php tests/Feature/Imunisasi tests/Feature/Spm`
Expected: semua PASS (kecuali 2 kegagalan lama `KelompokVaksinKosongTest` yang sudah merah sejak 66b5e3c — catat, jangan perbaiki di sini).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Middleware/AksesDasbor.php bootstrap/app.php routes/web.php tests/Feature/AkunKesmas/MatriksAksesDasborTest.php
git commit -m "feat(akun-kesmas): rute baca dasbor dibuka untuk peran Kesmas lewat middleware dasbor"
```

---

### Task 4: Kunci lingkup di Dasbor Kesmas dan Export Kesmas

**Files:**
- Modify: `app/Http/Controllers/KesmasDashboardController.php` (`index()`)
- Modify: `resources/views/admin/kesmas/partials/_filter.blade.php`
- Modify: `resources/views/admin/kesmas/dashboard.blade.php` (tombol Export Data)
- Modify: `resources/views/admin/kesmas/partials/_sdidtk-ckg-idl.blade.php:66` (tautan dasbor imunisasi)
- Modify: `app/Http/Controllers/ExportKesmasController.php` (`index()`)
- Modify: `resources/views/admin/export/kesmas.blade.php` (blok pilihan wilayah)
- Test: `tests/Feature/AkunKesmas/KunciLingkupKesmasTest.php`

**Interfaces:**
- Consumes: `AksesKesmas::untuk()`, `->kunciFilter()`, `->kunciDataPribadi()`, `->bolehExport()`, `->bolehDasbor()`, `->batasKelurahanExport()` (Task 1)
- Produces: view Kesmas menerima `$akses` (`AksesKesmas`) dan `$kunci` (`array<string,int>`); view export menerima `$kunciWilayah` (`?string`, label wilayah terkunci).

- [ ] **Step 1: Tulis tes yang gagal**

`tests/Feature/AkunKesmas/KunciLingkupKesmasTest.php`:

```php
<?php

namespace Tests\Feature\AkunKesmas;

use App\Models\Anak;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Posyandu;
use App\Models\Puskesmas;
use App\Models\User;
use App\Services\ImunisasiStatusService;
use App\Support\WilkerPuskesmas;
use Database\Seeders\JenisVaksinSeeder;
use Database\Seeders\KelompokVaksinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

/** Spec akun Kesmas §3 "Penerapan kunci" & §7: query string keluar lingkup ditimpa kunci akun. */
class KunciLingkupKesmasTest extends TestCase
{
    use RefreshDatabase;

    private Kelurahan $kelA;
    private Kelurahan $kelB;
    private Posyandu $posA;

    protected function setUp(): void
    {
        parent::setUp();
        ImunisasiStatusService::flushCache();
        WilkerPuskesmas::flushCache();
        $this->seed(JenisVaksinSeeder::class);
        $this->seed(KelompokVaksinSeeder::class);

        $kec = Kecamatan::create(['name' => 'Bontang Utara']);
        $this->kelA = Kelurahan::create(['name' => 'Bontang Baru', 'id_kecamatan' => $kec->id]);
        $this->kelB = Kelurahan::create(['name' => 'Lok Tuan', 'id_kecamatan' => $kec->id]);
        $pkm = Puskesmas::factory()->create(['name' => 'Bontang Utara 1']);
        $this->posA = Posyandu::factory()->create(['id_puskesmas' => $pkm->id]);

        $this->anak('Anak A1', '6474010101230001', $this->kelA, $this->posA->id);
        $this->anak('Anak A2', '6474010101230002', $this->kelA, null);
        $this->anak('Anak B1', '6474010101230003', $this->kelB, null);
    }

    private function anak(string $nama, string $nik, Kelurahan $kel, ?int $pos): void
    {
        Anak::create([
            'nama' => $nama, 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => now()->subMonths(10)->toDateString(), 'status' => 1, 'no' => '1', 'sumber' => 'manual',
            'id_kec' => $kel->id_kecamatan, 'id_kel' => $kel->id, 'id_posyandu' => $pos, 'sasaran_balita_kesmas' => 1,
        ]);
    }

    private function namaRegistri(User $user, array $query = []): array
    {
        $json = $this->actingAs($user)->getJson(route('admin.kesmas.registri', $query))->assertOk()->json('data');

        return collect($json)->pluck('nama')->sort()->values()->all();
    }

    public function test_kelurahan_tidak_bisa_melihat_kelurahan_lain_lewat_query_string(): void
    {
        $user = User::factory()->create(['type' => 2, 'role' => 'kelurahan', 'id_kel' => $this->kelA->id]);

        $this->assertSame(['Anak A1', 'Anak A2'], $this->namaRegistri($user));
        $this->assertSame(['Anak A1', 'Anak A2'], $this->namaRegistri($user, ['id_kelurahan' => $this->kelB->id]));

        $this->actingAs($user)->get(route('admin.kesmas.dashboard', ['id_kelurahan' => $this->kelB->id]))
            ->assertOk()
            ->assertViewHas('filters', fn ($f) => $f['id_kelurahan'] === $this->kelA->id);
    }

    public function test_kader_hanya_posyandunya(): void
    {
        $user = User::factory()->create(['type' => 2, 'role' => 'kader', 'id_posyandu' => $this->posA->id]);

        $this->assertSame(['Anak A1'], $this->namaRegistri($user, ['id_posyandu' => 999]));
    }

    public function test_dropdown_wilayah_terkunci_dan_tombol_sesuai_izin(): void
    {
        $kader = User::factory()->create(['type' => 2, 'role' => 'kader', 'id_posyandu' => $this->posA->id]);
        $html = $this->actingAs($kader)->get(route('admin.kesmas.dashboard'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<select name="id_posyandu" id="filterPos"[^>]*disabled/', $html);
        $this->assertStringNotContainsString(route('admin.export.kesmas.index'), $html, 'kader tak boleh export');
        $this->assertStringNotContainsString(route('admin.imunisasiDashboard'), $html, 'kader tak boleh dasbor imunisasi');

        $kel = User::factory()->create(['type' => 2, 'role' => 'kelurahan', 'id_kel' => $this->kelA->id]);
        $this->actingAs($kel)->get(route('admin.kesmas.dashboard'))->assertOk()
            ->assertSee(route('admin.export.kesmas.index'), false);
    }

    public function test_export_kelurahan_hanya_kelurahannya_walau_url_meminta_lain(): void
    {
        $user = User::factory()->create(['type' => 2, 'role' => 'kelurahan', 'id_kel' => $this->kelA->id]);

        $response = $this->actingAs($user)->get(route('admin.export.kesmas.download', ['id_kel' => $this->kelB->id]));
        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'kesmas');
        file_put_contents($path, $response->streamedContent());
        $reader = new Reader();
        $reader->open($path);
        $nama = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $i => $row) {
                $nama[] = implode('|', array_map('strval', $row->toArray()));
            }
            break; // sheet Per Anak
        }
        $reader->close();
        @unlink($path);

        $isi = implode("\n", $nama);
        $this->assertStringContainsString('Anak A1', $isi);
        $this->assertStringContainsString('Anak A2', $isi);
        $this->assertStringNotContainsString('Anak B1', $isi);
    }

    public function test_halaman_export_kelurahan_menampilkan_wilayah_terkunci(): void
    {
        $user = User::factory()->create(['type' => 2, 'role' => 'kelurahan', 'id_kel' => $this->kelA->id]);

        $this->actingAs($user)->get(route('admin.export.kesmas.index'))->assertOk()
            ->assertSee('Kel. Bontang Baru')
            ->assertDontSee('name="id_kel"', false);
    }

    public function test_admin_tidak_terkunci(): void
    {
        $admin = User::factory()->create(['type' => 1, 'role' => null]);
        $this->assertSame(['Anak B1'], $this->namaRegistri($admin, ['id_kelurahan' => $this->kelB->id]));
    }
}
```

Catatan: kolom `nama` di `registri()['data']` — periksa `KesmasDashboardService::registri()` (±baris 557–612); bila kuncinya lain, sesuaikan `pluck()`.

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php vendor/bin/phpunit tests/Feature/AkunKesmas/KunciLingkupKesmasTest.php`
Expected: FAIL — dasbor kelurahan masih mengikuti `?id_kelurahan`, dropdown tidak `disabled`, halaman export masih ada `name="id_kel"`.

- [ ] **Step 3: Controller dasbor**

`KesmasDashboardController::index()` — ganti dua baris awal:

```php
        $akses = AksesKesmas::untuk(auth()->user());
        [$periode, $filters, $usia] = $this->parse($request);
        // Kunci wilayah akun menang atas query string (spec akun Kesmas §3).
        $filters = $akses->kunciFilter() + $filters;
```

dan tambahkan ke array view: `'akses' => $akses, 'kunci' => $akses->kunciFilter(),`; ganti `'registriBoleh' => AksesKesmas::untuk(auth()->user())->bolehNama(),` → `'registriBoleh' => $akses->bolehNama(),`.

- [ ] **Step 4: View dasbor**

`_filter.blade.php`: di baris paling atas (sebelum `<form`) tambahkan

```blade
@php($terkunci = !empty($kunci ?? []))
{{-- Akun berlingkup (kelurahan/kader/puskesmas): pilihan wilayah ditampilkan tetapi tak bisa diubah; server menimpanya juga. --}}
```

lalu pada kelima `<select>` wilayah (`id_kecamatan`, `id_kelurahan`, `id_rt`, `id_posyandu`, `id_puskesmas`) tambahkan `@disabled($terkunci)` tepat setelah atribut `id`. Kecualikan RT untuk akun kelurahan supaya bisa memilih RT di kelurahannya: untuk `id_rt` gunakan `@disabled($terkunci && !isset($kunci['id_kelurahan']))`, dan `id_kelurahan` untuk akun kelurahan tetap `disabled` tetapi tambahkan input tersembunyi agar JS RT tahu kelurahannya:

```blade
    @if(isset($kunci['id_kelurahan']))
        <input type="hidden" name="id_kelurahan" value="{{ $kunci['id_kelurahan'] }}">
    @endif
```

(letakkan tepat setelah `<select name="id_kelurahan" ...>...</select>`). Tombol "Reset" tetap ada (mengembalikan periode; kunci tetap berlaku).

`dashboard.blade.php` — bungkus tautan header `<a href="{{ route('admin.export.kesmas.index', $exportQs) }}" ...>...</a>` dengan `@if($akses->bolehExport()) ... @endif`.

`_sdidtk-ckg-idl.blade.php:66` — bungkus `<div class="km-foot">...</div>` yang menaut ke `admin.imunisasiDashboard` dengan `@if($akses->bolehDasbor('imunisasi')) ... @endif`. Periksa view lain yang meng-include partial ini (`grep -rn "_sdidtk-ckg-idl" resources/views`) — bila ada view selain dasbor Kesmas, gunakan `@if(!isset($akses) || $akses->bolehDasbor('imunisasi'))`.

- [ ] **Step 5: Halaman Export Kesmas**

`ExportKesmasController::index()`:

```php
    public function index(Request $request)
    {
        $akses = $this->akses();
        $filter = $this->filter($request);
        $kec = Kecamatan::orderBy('name')->get();
        $kel = Kelurahan::orderBy('name')->get();
        $puskesmas = Puskesmas::orderBy('name')->get();
        $posyandu = Posyandu::orderBy('name')->get();
        // Peran Kesmas berlingkup kelurahan: pilihan wilayah diganti label (server tetap membatasi lewat batas_kelurahan).
        $kunciWilayah = $akses->terbatas() && isset($akses->kunciDataPribadi()['id_kelurahan'])
            ? 'Kel. ' . optional($kel->firstWhere('id', $akses->kunciDataPribadi()['id_kelurahan']))->name
            : null;

        // Muat opsi di server agar tautan dengan filter anak saja juga tetap terpilih.
        return view('admin.export.kesmas', compact('kec', 'kel', 'puskesmas', 'posyandu', 'filter', 'kunciWilayah'));
    }
```

`admin/export/kesmas.blade.php` — bungkus empat kolom wilayah (`kec`, `kel`, `puskesmas`, `posyandu`; dari `<div class="col-md-3 col-sm-12">` pertama sampai penutup kolom posyandu) dengan:

```blade
                @if ($kunciWilayah)
                <div class="col-12">
                    <p class="mb-3">Wilayah: <strong>{{ $kunciWilayah }}</strong> (sesuai akun)</p>
                </div>
                @else
                {{-- ...empat kolom wilayah yang sudah ada, tidak diubah... --}}
                @endif
```

Pastikan JS halaman itu tidak galat saat `#kec` tidak ada: bungkus isi `$(function(){...})` yang menyentuh `#kec`/`#kel` dengan `if (!document.getElementById('kec')) return;` di baris pertamanya.

- [ ] **Step 6: Jalankan tes**

Run: `php artisan view:clear; php vendor/bin/phpunit tests/Feature/AkunKesmas tests/Feature/Kesmas`
Expected: semua PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/KesmasDashboardController.php app/Http/Controllers/ExportKesmasController.php resources/views/admin/kesmas resources/views/admin/export/kesmas.blade.php tests/Feature/AkunKesmas/KunciLingkupKesmasTest.php
git commit -m "feat(akun-kesmas): kunci wilayah akun di dasbor & Export Kesmas"
```

---

### Task 5: Mode baca Pimpinan di dasbor Imunisasi dan Operasi Timbang

**Files:**
- Modify: `app/Http/Controllers/SasaranImunisasiController.php` (`daftar()`)
- Modify: `app/Http/Controllers/TimbangDashboardController.php` (`daftar()`)
- Modify: `resources/views/admin/imunisasi/dashboard.blade.php` (link export modal, link `admin.earlyWarning`)
- Modify: `resources/views/admin/dashboard/timbang.blade.php` (tombol peta, tombol export modal, sel PJ)
- Test: `tests/Feature/AkunKesmas/PimpinanBacaSajaTest.php`

**Interfaces:**
- Consumes: `AksesKesmas::untuk()->terbatas()`, `->bolehNama()` (Task 1); rute sudah terbuka untuk pimpinan (Task 3)
- Produces: variabel JS `BOLEH_UBAH` di dasbor OT.

- [ ] **Step 1: Tulis tes yang gagal**

`tests/Feature/AkunKesmas/PimpinanBacaSajaTest.php`:

```php
<?php

namespace Tests\Feature\AkunKesmas;

use App\Models\User;
use App\Services\ImunisasiStatusService;
use App\Support\WilkerPuskesmas;
use Database\Seeders\JenisVaksinSeeder;
use Database\Seeders\KelompokVaksinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Spec akun Kesmas §2: Pimpinan melihat daftar nama, tetapi tanpa export dan tanpa menulis. */
class PimpinanBacaSajaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ImunisasiStatusService::flushCache();
        WilkerPuskesmas::flushCache();
        $this->seed(JenisVaksinSeeder::class);
        $this->seed(KelompokVaksinSeeder::class);
    }

    private function pimpinan(): User
    {
        return User::factory()->create(['type' => 2, 'role' => 'pimpinan']);
    }

    public function test_dasbor_imunisasi_tanpa_export_dan_tautan_admin(): void
    {
        $html = $this->actingAs($this->pimpinan())->get(route('admin.imunisasiDashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('data-kelompok="bbl"', $html, 'kartu tetap membuka daftar nama');
        $this->assertStringNotContainsString('id="sasaran-export"', $html);
        $this->assertStringNotContainsString(route('admin.imunisasiDashboard.sasaranExport'), $html);
        $this->assertStringNotContainsString(route('admin.earlyWarning'), $html);
    }

    public function test_dasbor_ot_tanpa_export_peta_dan_pj_bisa_diubah(): void
    {
        $html = $this->actingAs($this->pimpinan())->get(route('admin.timbang.dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('id="daftar-export"', $html);
        $this->assertStringNotContainsString('id="btn-peta"', $html);
        $this->assertStringContainsString('var BOLEH_UBAH = false;', $html);
        $this->assertStringNotContainsString('id="tb-publikasi"', $html);
    }

    public function test_admin_tetap_punya_tombolnya(): void
    {
        $admin = User::factory()->create(['type' => 1, 'role' => null]);
        $this->actingAs($admin)->get(route('admin.imunisasiDashboard'))->assertOk()->assertSee('id="sasaran-export"', false);
        $this->actingAs($admin)->get(route('admin.timbang.dashboard'))->assertOk()
            ->assertSee('id="daftar-export"', false)->assertSee('var BOLEH_UBAH = true;', false);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php vendor/bin/phpunit tests/Feature/AkunKesmas/PimpinanBacaSajaTest.php`
Expected: FAIL — `id="sasaran-export"` masih ada, `BOLEH_UBAH` belum ada.

- [ ] **Step 3: Cek `bolehNama()` di endpoint daftar**

Di awal `SasaranImunisasiController::daftar()` dan `TimbangDashboardController::daftar()` tambahkan:

```php
        // Peran Kesmas tanpa izin daftar nama tak boleh membuka NIK (spec akun Kesmas §4). Admin-setara tidak berubah.
        $akses = \App\Support\AksesKesmas::untuk(auth()->user());
        abort_if($akses->terbatas() && !$akses->bolehNama(), 403, 'Akses tidak diizinkan.');
```

- [ ] **Step 4: View dasbor Imunisasi**

Di `resources/views/admin/imunisasi/dashboard.blade.php`, tepat setelah `@section('content')` (cari dengan `grep -n "@section('content')"`), tambahkan:

```blade
@php($bacaSaja = \App\Support\AksesKesmas::untuk(Auth::user())->terbatas())
```

Bungkus `<a class="im-btn im-btn--primary" id="sasaran-export" href="#">...</a>` dengan `@unless($bacaSaja) ... @endunless`. Di JS modal ganti `$id('sasaran-export').href = API_EXPORT + params(kel);` menjadi `if ($id('sasaran-export')) $id('sasaran-export').href = API_EXPORT + params(kel);`. Bungkus `<div class="im-card__foot">` berisi tautan `admin.earlyWarning` dengan `@unless($bacaSaja) ... @endunless`.

- [ ] **Step 5: View dasbor Operasi Timbang**

Di `resources/views/admin/dashboard/timbang.blade.php`, tepat setelah `@php($isSuper = Auth::user()->isSuperAdmin())` tambahkan:

```blade
@php($bacaSaja = \App\Support\AksesKesmas::untuk(Auth::user())->terbatas())
```

- Bungkus `<a class="tb-filter-btn tb-filter-btn--ghost" id="btn-peta" ...>...</a>` dengan `@unless($bacaSaja) ... @endunless`.
- Bungkus `<a class="tb-filter-btn" id="daftar-export" ...>...</a>` dengan `@unless($bacaSaja) ... @endunless`.
- Kedua baris JS `document.getElementById('daftar-export').href = daftarExportHref();` diganti `var ex = document.getElementById('daftar-export'); if(ex) ex.href = daftarExportHref();`.
- Setelah `var IS_SUPER = ...;` tambahkan `var BOLEH_UBAH = {{ $bacaSaja ? 'false' : 'true' }};`.
- Di awal `function pjCell(r){` tambahkan:

```js
    if(!BOLEH_UBAH){
        return '<td class="tb-pj"><span class="tb-pj__val'+(r.pj_nama ? '' : ' tb-pj__val--kosong')+'">'
            +(r.pj_nama ? escHtml(r.pj_nama) : '—')+'</span></td>';
    }
```

- Di `function bukaEditorPj(td){` tambahkan baris pertama `if(!BOLEH_UBAH) return;`.

- [ ] **Step 6: Jalankan tes**

Run: `php artisan view:clear; php vendor/bin/phpunit tests/Feature/AkunKesmas tests/Feature/Imunisasi tests/Feature/Pj`
Expected: semua PASS (kecuali 2 kegagalan lama `KelompokVaksinKosongTest`).

Periksa sintaks JS kedua dasbor:

Run (bash): `for f in resources/views/admin/imunisasi/dashboard.blade.php resources/views/admin/dashboard/timbang.blade.php; do awk '/^<script>$/{f=1;next} /^<\/script>/{if(f)exit} f' $f | sed "s/{{[^}]*}}/x/g" > /tmp/cek.js; node --check /tmp/cek.js && echo "$f OK"; done`
Expected: dua baris `OK`. (Bila blok `<script>` pertama bukan yang diubah, sesuaikan pola awk ke blok yang memuat `pjCell`/`sasaran-export`.)

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/SasaranImunisasiController.php app/Http/Controllers/TimbangDashboardController.php resources/views/admin/imunisasi/dashboard.blade.php resources/views/admin/dashboard/timbang.blade.php tests/Feature/AkunKesmas/PimpinanBacaSajaTest.php
git commit -m "feat(akun-kesmas): Pimpinan membaca dasbor Imunisasi & OT tanpa export dan tanpa ubah PJ"
```

---

### Task 6: Menu samping dan logo untuk peran terbatas

**Files:**
- Modify: `resources/views/vendor/admin/layouts/partials/leftsidebar.blade.php` (logo baris 3; cabang baru setelah blok superadmin)
- Test: `tests/Feature/AkunKesmas/MenuPeranKesmasTest.php`

**Interfaces:**
- Consumes: `AksesKesmas::untuk()->terbatas()`, `->bolehDasbor()`, `->bolehExport()` (Task 1); `berandaRoute()` (Task 2)

- [ ] **Step 1: Tulis tes yang gagal**

`tests/Feature/AkunKesmas/MenuPeranKesmasTest.php`:

```php
<?php

namespace Tests\Feature\AkunKesmas;

use App\Models\Kelurahan;
use App\Models\User;
use App\Services\ImunisasiStatusService;
use App\Support\WilkerPuskesmas;
use Database\Seeders\JenisVaksinSeeder;
use Database\Seeders\KelompokVaksinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuPeranKesmasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ImunisasiStatusService::flushCache();
        WilkerPuskesmas::flushCache();
        $this->seed(JenisVaksinSeeder::class);
        $this->seed(KelompokVaksinSeeder::class);
    }

    private function menu(User $user): string
    {
        $html = $this->actingAs($user)->get(route('admin.kesmas.dashboard'))->assertOk()->getContent();
        preg_match('/<div class="left-side-bar">(.*?)<div class="mobile-menu-overlay|<div class="main-container/s', $html, $m);

        return $m[1] ?? $html;
    }

    public function test_pimpinan_hanya_empat_dasbor(): void
    {
        $menu = $this->menu(User::factory()->create(['type' => 2, 'role' => 'pimpinan']));

        foreach (['admin.kesmas.dashboard', 'admin.imunisasiDashboard', 'admin.timbang.dashboard', 'admin.spm.dashboard'] as $r) {
            $this->assertStringContainsString(route($r), $menu, $r);
        }
        foreach (['admin.anak', 'admin.importCsv.index', 'admin.export.kesmas.index', 'super.admin.user', 'admin.home'] as $r) {
            $this->assertStringNotContainsString(route($r), $menu, $r);
        }
    }

    public function test_kelurahan_kesmas_dan_export(): void
    {
        $kel = Kelurahan::factory()->create();
        $menu = $this->menu(User::factory()->create(['type' => 2, 'role' => 'kelurahan', 'id_kel' => $kel->id]));

        $this->assertStringContainsString(route('admin.kesmas.dashboard'), $menu);
        $this->assertStringContainsString(route('admin.export.kesmas.index'), $menu);
        $this->assertStringNotContainsString(route('admin.imunisasiDashboard'), $menu);
    }

    public function test_menu_admin_tidak_berubah(): void
    {
        $menu = $this->menu(User::factory()->create(['type' => 0, 'role' => 'superadmin']));
        $this->assertStringContainsString(route('admin.anak'), $menu);
        $this->assertStringContainsString(route('super.admin.user'), $menu);
    }
}
```

Catatan: periksa penanda pembuka/penutup sidebar di `resources/views/vendor/admin/layouts/app.blade.php` (setelah `@include('admin::layouts.partials.leftsidebar')`) dan sesuaikan regex `menu()` agar hanya menangkap isi sidebar. Bila `admin.anak` juga muncul di header/quicklink, tes `test_pimpinan_hanya_empat_dasbor` akan salah menyalahkan sidebar — sebab itulah isi sidebar dipotong lebih dulu.

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php vendor/bin/phpunit tests/Feature/AkunKesmas/MenuPeranKesmasTest.php`
Expected: FAIL — pimpinan mendapat menu cabang `@else` (Data Anak, Import, dst.) dan logo menaut `admin.home`.

- [ ] **Step 3: Implementasi**

Logo (baris 3): `<a href="{{ route('admin.home') }}" aria-label="Beranda SIRINDU">` → `<a href="{{ route(Auth::user()->berandaRoute()) }}" aria-label="Beranda SIRINDU">`.

Di antara blok superadmin dan `@elseif (Auth::user()->isFaskesSurveilans())`, sisipkan:

```blade
				{{-- ============================================
				     PERAN KESMAS (pimpinan, kelurahan, kader, puskesmas/RS Kesmas)
				     Hanya dasbor yang diizinkan AksesKesmas — spec akun Kesmas §5.
				     ============================================ --}}
				@elseif (($aksesKesmas = \App\Support\AksesKesmas::untuk(Auth::user()))->terbatas())
				<li class="dropdown section-group show">
					<a href="javascript:;" class="dropdown-toggle section-toggle active">
						<span class="micon fa fa-tachometer"></span><span class="mtext">Dashboard</span>
					</a>
					<ul class="submenu" style="display:block;">
						@if ($aksesKesmas->bolehDasbor('kesmas'))
						<li><a href="{{route('admin.kesmas.dashboard')}}" class="{{ request()->routeIs('admin.kesmas.*') ? 'active' : '' }}">Kesmas</a></li>
						@endif
						@if ($aksesKesmas->bolehDasbor('imunisasi'))
						<li><a href="{{route('admin.imunisasiDashboard')}}" class="{{ request()->routeIs('admin.imunisasiDashboard') ? 'active' : '' }}">Imunisasi</a></li>
						@endif
						@if ($aksesKesmas->bolehDasbor('timbang'))
						<li><a href="{{route('admin.timbang.dashboard')}}" class="{{ request()->routeIs('admin.timbang.*') ? 'active' : '' }}">Operasi Timbang</a></li>
						@endif
						@if ($aksesKesmas->bolehDasbor('spm'))
						<li><a href="{{route('admin.spm.dashboard')}}" class="{{ request()->routeIs('admin.spm.dashboard') ? 'active' : '' }}">SPM</a></li>
						@endif
					</ul>
				</li>
				@if ($aksesKesmas->bolehExport())
				<li>
					<a href="{{route('admin.export.kesmas.index')}}" class="dropdown-toggle no-arrow {{ request()->routeIs('admin.export.kesmas.*') ? 'active' : '' }}">
						<span class="micon fa fa-download"></span><span class="mtext">Export Kesmas</span>
					</a>
				</li>
				@endif
```

Ikuti markup `<li>` tunggal yang sudah dipakai sidebar ini untuk item tanpa submenu (cari `no-arrow` di berkas); bila kelasnya berbeda, samakan dengan yang ada.

Lalu buka layout admin sekali di browser untuk tiap peran (Task 8) — header/quicklink beranda tak boleh menaut ke halaman terlarang. Bila `mainheader.blade.php` atau `app.blade.php` menampilkan quicklink beranda (`beranda_quicklinks`) di luar halaman `admin.home`, bungkus dengan `@unless(\App\Support\AksesKesmas::untuk(Auth::user())->terbatas())`.

- [ ] **Step 4: Jalankan tes**

Run: `php artisan view:clear; php vendor/bin/phpunit tests/Feature/AkunKesmas`
Expected: semua PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/vendor/admin/layouts/partials/leftsidebar.blade.php tests/Feature/AkunKesmas/MenuPeranKesmasTest.php
git commit -m "feat(akun-kesmas): menu samping ringkas untuk peran Kesmas"
```

---

### Task 7: Manajemen akun — form, validasi, satu akun per kelurahan

**Files:**
- Modify: `app/Http/Requests/Admin/User/storeUserRequest.php` (`rules()`, `messages()`)
- Modify: `app/Repositories/Admin/User/UserRepository.php` (`storeUser`, `updateUser`, metode baru `lingkupKesmas`, `pastikanKelurahanBelumPunyaAkun`)
- Modify: `app/Http/Controllers/AdminController.php` (`user()`, `editUser()` — kirim `kelurahanList`, `posyanduList`)
- Modify: `resources/views/admin/user/create.blade.php`, `edit.blade.php`, `index.blade.php`
- Test: `tests/Feature/AkunKesmas/KelolaAkunKesmasTest.php`

**Interfaces:**
- Consumes: kolom `nama_sarana` (Task 2), `typeFromRole()` baru (Task 2)
- Produces: field form `kesmas_id_kel`, `kesmas_id_posyandu`, `nama_sarana` (+ `id_puskesmas`, `id_rs` yang sudah ada).

- [ ] **Step 1: Tulis tes yang gagal**

`tests/Feature/AkunKesmas/KelolaAkunKesmasTest.php`:

```php
<?php

namespace Tests\Feature\AkunKesmas;

use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Posyandu;
use App\Models\Puskesmas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Spec akun Kesmas §6. */
class KelolaAkunKesmasTest extends TestCase
{
    use RefreshDatabase;

    private User $super;
    private Kelurahan $kel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->super = User::factory()->create(['type' => 0, 'role' => 'superadmin']);
        $kec = Kecamatan::create(['name' => 'Bontang Selatan']);
        $this->kel = Kelurahan::create(['name' => 'Satimpo', 'id_kecamatan' => $kec->id]);
    }

    private function simpan(array $data)
    {
        return $this->actingAs($this->super)->from(route('super.admin.user'))
            ->post(route('super.admin.storeUser'), ['name' => 'Akun', 'email' => uniqid() . '@sirindu.test'] + $data);
    }

    public function test_akun_kelurahan_tersimpan_dengan_wilayah_dan_type_2(): void
    {
        $this->simpan(['role' => 'kelurahan', 'kesmas_id_kel' => $this->kel->id, 'email' => 'satimpo@sirindu.test'])
            ->assertRedirect(route('super.admin.user'));

        $u = User::where('email', 'satimpo@sirindu.test')->firstOrFail();
        $this->assertSame(2, (int) $u->type);
        $this->assertSame($this->kel->id, (int) $u->id_kel);
        $this->assertSame($this->kel->id_kecamatan, (int) $u->id_kec);
    }

    public function test_akun_kelurahan_kedua_ditolak_dengan_pesan_jelas(): void
    {
        User::factory()->create(['type' => 2, 'role' => 'kelurahan', 'id_kel' => $this->kel->id, 'email' => 'lama@sirindu.test']);

        $this->simpan(['role' => 'kelurahan', 'kesmas_id_kel' => $this->kel->id])
            ->assertSessionHasErrors(['kesmas_id_kel' => 'Kelurahan Satimpo sudah memiliki akun (lama@sirindu.test). Satu kelurahan hanya boleh memiliki satu akun.']);
    }

    public function test_akun_kelurahan_terhapus_tidak_menghalangi(): void
    {
        User::factory()->create(['type' => 2, 'role' => 'kelurahan', 'id_kel' => $this->kel->id])->delete();

        $this->simpan(['role' => 'kelurahan', 'kesmas_id_kel' => $this->kel->id])->assertSessionHasNoErrors();
    }

    public function test_edit_akun_kelurahan_itu_sendiri_lolos(): void
    {
        $u = User::factory()->create(['type' => 2, 'role' => 'kelurahan', 'id_kel' => $this->kel->id]);

        $this->actingAs($this->super)->put(route('super.admin.updateUser', $u->id), [
            'name' => 'Nama Baru', 'email' => $u->email, 'role' => 'kelurahan', 'kesmas_id_kel' => $this->kel->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame('Nama Baru', $u->fresh()->name);
    }

    public function test_kader_menurunkan_puskesmas_dari_posyandu(): void
    {
        $pkm = Puskesmas::factory()->create();
        $pos = Posyandu::factory()->create(['id_puskesmas' => $pkm->id]);

        $this->simpan(['role' => 'kader', 'kesmas_id_posyandu' => $pos->id, 'email' => 'kader@sirindu.test'])->assertSessionHasNoErrors();
        $u = User::where('email', 'kader@sirindu.test')->firstOrFail();
        $this->assertSame($pos->id, (int) $u->id_posyandu);
        $this->assertSame($pkm->id, (int) $u->id_puskesmas);
    }

    public function test_isian_wajib_per_peran(): void
    {
        $this->simpan(['role' => 'kelurahan'])->assertSessionHasErrors('kesmas_id_kel');
        $this->simpan(['role' => 'kader'])->assertSessionHasErrors('kesmas_id_posyandu');
        $this->simpan(['role' => 'kesmas_puskesmas'])->assertSessionHasErrors('id_puskesmas');
        $this->simpan(['role' => 'kesmas_rs'])->assertSessionHasErrors('id_rs');
        $this->simpan(['role' => 'sarkes'])->assertSessionHasErrors('nama_sarana');
        $this->simpan(['role' => 'pimpinan'])->assertSessionHasNoErrors();
    }

    public function test_ganti_peran_mengosongkan_lingkup_lama(): void
    {
        $u = User::factory()->create(['type' => 2, 'role' => 'kelurahan', 'id_kel' => $this->kel->id, 'id_kec' => $this->kel->id_kecamatan]);

        $this->actingAs($this->super)->put(route('super.admin.updateUser', $u->id), [
            'name' => $u->name, 'email' => $u->email, 'role' => 'pimpinan',
        ])->assertSessionHasNoErrors();

        $u->refresh();
        $this->assertSame('pimpinan', $u->role);
        $this->assertNull($u->id_kel);
        $this->assertNull($u->id_kec);

        // Kelurahan itu kini boleh punya akun baru.
        $this->simpan(['role' => 'kelurahan', 'kesmas_id_kel' => $this->kel->id])->assertSessionHasNoErrors();
    }

    public function test_sarkes_menyimpan_nama_sarana(): void
    {
        $this->simpan(['role' => 'sarkes', 'nama_sarana' => '  Klinik Pratama Sehat ', 'email' => 'klinik@sirindu.test'])->assertSessionHasNoErrors();
        $this->assertSame('Klinik Pratama Sehat', User::where('email', 'klinik@sirindu.test')->value('nama_sarana'));
    }

    public function test_form_menampilkan_peran_baru(): void
    {
        $html = $this->actingAs($this->super)->get(route('super.admin.user'))->assertOk()->getContent();
        foreach (['pimpinan', 'kelurahan', 'kader', 'kesmas_puskesmas', 'kesmas_rs', 'sarkes'] as $role) {
            $this->assertStringContainsString('value="' . $role . '"', $html, $role);
        }
        $this->assertStringContainsString('name="kesmas_id_kel"', $html);
        $this->assertStringContainsString('name="kesmas_id_posyandu"', $html);
        $this->assertStringContainsString('name="nama_sarana"', $html);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php vendor/bin/phpunit tests/Feature/AkunKesmas/KelolaAkunKesmasTest.php`
Expected: FAIL — `role` baru ditolak `in:` validasi.

- [ ] **Step 3: Validasi**

Di `storeUserRequest::rules()`:
- `'role' => 'required|in:superadmin,imunisasi_faskes,surveilans_puskesmas,surveilans_rs,rt,pimpinan,kelurahan,kader,kesmas_puskesmas,kesmas_rs,sarkes',`
- `id_puskesmas` → tambahkan `$role === 'kesmas_puskesmas' ||` di dalam `Rule::requiredIf(...)`.
- `id_rs` → tambahkan `$role === 'kesmas_rs' ||` di dalam `Rule::requiredIf(...)`.
- tambahkan:

```php
            'kesmas_id_kel' => [
                Rule::requiredIf($role === 'kelurahan'), 'nullable', 'integer', 'exists:kelurahan,id',
                // Satu akun per kelurahan (spec akun Kesmas §6); akun soft-deleted tak dihitung, akun ini sendiri dikecualikan.
                function ($attribute, $value, $fail) use ($role, $userId) {
                    if ($role !== 'kelurahan' || !$value) {
                        return;
                    }
                    $ada = \App\Models\User::where('role', 'kelurahan')->where('id_kel', $value)
                        ->when($userId, fn ($q) => $q->whereKeyNot($userId))->first();
                    if ($ada) {
                        $nama = \App\Models\Kelurahan::whereKey($value)->value('name');
                        $fail("Kelurahan {$nama} sudah memiliki akun ({$ada->email}). Satu kelurahan hanya boleh memiliki satu akun.");
                    }
                },
            ],
            'kesmas_id_posyandu' => [Rule::requiredIf($role === 'kader'), 'nullable', 'integer', 'exists:posyandu,id'],
            'nama_sarana'        => [Rule::requiredIf($role === 'sarkes'), 'nullable', 'string', 'max:150'],
```

Di `messages()` tambahkan:

```php
            'kesmas_id_kel.required'      => 'Kelurahan wajib dipilih untuk akun kelurahan.',
            'kesmas_id_posyandu.required' => 'Posyandu wajib dipilih untuk akun kader.',
            'nama_sarana.required'        => 'Nama sarana wajib diisi untuk akun sarkes.',
```

- [ ] **Step 4: Repository**

Tambahkan `use App\Models\Posyandu;`, `use Illuminate\Support\Facades\DB;`, `use Illuminate\Validation\ValidationException;` dan metode:

```php
    /**
     * Peran Kesmas: seluruh kolom lingkup ditulis ulang — yang tak relevan dikosongkan, supaya akun yang
     * berganti peran tidak membawa lingkup lama (spec akun Kesmas §6). [] untuk peran lain.
     */
    private function lingkupKesmas($request): array
    {
        $kosong = [
            'id_kec' => null, 'id_kel' => null, 'id_puskesmas' => null, 'id_rs' => null, 'id_posyandu' => null,
            'id_rt' => null, 'rt_sekelurahan' => false, 'nama_sarana' => null, 'faskes_type' => null,
        ];

        switch ($request->role) {
            case 'pimpinan':
                return $kosong;
            case 'kelurahan':
                $kel = Kelurahan::findOrFail($request->kesmas_id_kel);
                return ['id_kel' => $kel->id, 'id_kec' => $kel->id_kecamatan] + $kosong;
            case 'kader':
                // Tabel posyandu tak punya kolom kelurahan — yang bisa diturunkan hanya puskesmas.
                $pos = Posyandu::findOrFail($request->kesmas_id_posyandu);
                return ['id_posyandu' => $pos->id, 'id_puskesmas' => $pos->id_puskesmas] + $kosong;
            case 'kesmas_puskesmas':
                return ['id_puskesmas' => (int) $request->id_puskesmas, 'faskes_type' => 'puskesmas'] + $kosong;
            case 'kesmas_rs':
                return ['id_rs' => (int) $request->id_rs, 'faskes_type' => 'rs'] + $kosong;
            case 'sarkes':
                return ['nama_sarana' => trim((string) $request->nama_sarana)] + $kosong;
            default:
                return [];
        }
    }

    /** Cek ulang di dalam transaksi: dua super-admin yang menyimpan bersamaan tak boleh lolos validasi berdua. */
    private function pastikanKelurahanBelumPunyaAkun(array $data, ?int $kecuali): void
    {
        if (($data['role'] ?? null) !== 'kelurahan') {
            return;
        }
        $ada = User::where('role', 'kelurahan')->where('id_kel', $data['id_kel'])
            ->when($kecuali, fn ($q) => $q->whereKeyNot($kecuali))->lockForUpdate()->first();
        if ($ada) {
            $nama = Kelurahan::whereKey($data['id_kel'])->value('name');
            throw ValidationException::withMessages([
                'kesmas_id_kel' => "Kelurahan {$nama} sudah memiliki akun ({$ada->email}). Satu kelurahan hanya boleh memiliki satu akun.",
            ]);
        }
    }
```

Di `storeUser()`: ganti `User::create(array_merge([...], $this->wilayahDariRt($request)));` menjadi

```php
        $data = array_merge([ /* array yang sudah ada, tidak diubah */ ], $this->wilayahDariRt($request), $this->lingkupKesmas($request));

        DB::transaction(function () use ($data) {
            $this->pastikanKelurahanBelumPunyaAkun($data, null);
            User::create($data);
        });
```

Di `updateUser()`: tambahkan `'nama_sarana' => null,` ke `$data` awal; ganti

```php
        $data = array_merge($data, $this->wilayahDariRt($request));

        $user->update($data);
```

dengan

```php
        $data = array_merge($data, $this->wilayahDariRt($request), $this->lingkupKesmas($request));

        DB::transaction(function () use ($user, $data) {
            $this->pastikanKelurahanBelumPunyaAkun($data, $user->id);
            $user->update($data);
        });
```

Perhatikan `AdminController::storeUser/updateUser` menangkap `Throwable` dan menampilkan Alert — `ValidationException` dari jalur balapan ini akan muncul sebagai Alert "Gagal" berisi pesannya; itu cukup (jalur normal sudah ditolak FormRequest).

- [ ] **Step 5: Controller + view**

`AdminController::user()` dan `editUser()`: tambahkan

```php
        $kelurahanList = Kelurahan::orderBy('name')->get();
        $posyanduList = Posyandu::orderBy('name')->get();
```

dan sertakan `'kelurahanList', 'posyanduList'` di `compact(...)`. (Pastikan `use App\Models\Posyandu;` dan `Kelurahan` sudah di-import di `AdminController`; tambahkan bila belum.)

`create.blade.php`:
- tambahkan option setelah `rt`:

```blade
                            <optgroup label="Modul Kesmas">
                                <option value="pimpinan">Pimpinan (semua dasbor, baca saja)</option>
                                <option value="kelurahan">Kelurahan (dasbor & export Kesmas kelurahannya)</option>
                                <option value="kader">Kader (dasbor Kesmas posyandunya)</option>
                                <option value="kesmas_puskesmas">Puskesmas – Kesmas</option>
                                <option value="kesmas_rs">Rumah Sakit – Kesmas</option>
                                <option value="sarkes">Sarkes (klinik/praktik swasta)</option>
                            </optgroup>
```

- tambahkan setelah grup `create_rs_group`:

```blade
                    <div class="form-group d-none" id="create_kesmas_kel_group">
                        <label for="create_kesmas_kel">Kelurahan</label>
                        <select class="form-control" name="kesmas_id_kel" id="create_kesmas_kel">
                            <option value="">== Pilih Kelurahan ==</option>
                            @foreach($kelurahanList as $k)
                            <option value="{{ $k->id }}" @selected(old('kesmas_id_kel') == $k->id)>{{ $k->name }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Satu kelurahan hanya boleh memiliki satu akun.</small>
                        @error('kesmas_id_kel') <span class="text-danger d-block">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-group d-none" id="create_kesmas_pos_group">
                        <label for="create_kesmas_pos">Posyandu</label>
                        <select class="form-control" name="kesmas_id_posyandu" id="create_kesmas_pos">
                            <option value="">== Pilih Posyandu ==</option>
                            @foreach($posyanduList as $p)
                            <option value="{{ $p->id }}" @selected(old('kesmas_id_posyandu') == $p->id)>{{ $p->name }}</option>
                            @endforeach
                        </select>
                        @error('kesmas_id_posyandu') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-group d-none" id="create_sarana_group">
                        <label for="create_sarana">Nama sarana</label>
                        <input class="form-control" name="nama_sarana" id="create_sarana" type="text" maxlength="150" value="{{ old('nama_sarana') }}" placeholder="mis. Klinik Pratama Sehat">
                        @error('nama_sarana') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
```

- di `updateCreateFaskesVisibility()`: tambahkan ketiga id grup baru ke daftar yang di-`add('d-none')`, lalu cabang:

```js
        } else if (role === 'kelurahan') {
            document.getElementById('create_kesmas_kel_group').classList.remove('d-none');
        } else if (role === 'kader') {
            document.getElementById('create_kesmas_pos_group').classList.remove('d-none');
        } else if (role === 'kesmas_puskesmas') {
            document.getElementById('create_puskesmas_group').classList.remove('d-none');
        } else if (role === 'kesmas_rs') {
            document.getElementById('create_rs_group').classList.remove('d-none');
        } else if (role === 'sarkes') {
            document.getElementById('create_sarana_group').classList.remove('d-none');
```

(sisipkan sebelum `} else if (role === 'imunisasi_faskes') {`).

`edit.blade.php`: tambahkan optgroup yang sama dengan `{{ $user->role === 'x' ? 'selected' : '' }}`; tiga grup yang sama dengan id `edit_kesmas_kel_group`, `edit_kesmas_pos_group`, `edit_sarana_group`, kelas kolom `col-md-4 col-sm-12` mengikuti grup puskesmas di berkas itu, nilai terpilih dari `$user->id_kel` / `$user->id_posyandu` / `$user->nama_sarana`, dan `d-none` kecuali perannya cocok. Perluas `$showPuskesmas` dengan `|| $user->role === 'kesmas_puskesmas'` dan `$showRS` dengan `|| $user->role === 'kesmas_rs'`. Di `updateEditFaskesVisibility()` tambahkan cabang yang sama seperti create dengan id `edit_*`.

`index.blade.php` — sebelum `@else` di kolom peran tambahkan:

```blade
                    @elseif (in_array($data->role, \App\Support\AksesKesmas::PERAN_TERBATAS, true))
                    <span class="badge badge-dark">{{ ['pimpinan' => 'Pimpinan', 'kelurahan' => 'Kelurahan', 'kader' => 'Kader', 'kesmas_puskesmas' => 'Puskesmas – Kesmas', 'kesmas_rs' => 'RS – Kesmas', 'sarkes' => 'Sarkes'][$data->role] }}</span>
```

- [ ] **Step 6: Jalankan tes + regresi form user**

Run: `php artisan view:clear; php vendor/bin/phpunit tests/Feature/AkunKesmas tests/Feature/VerifikasiRt/BuatAkunRtTest.php tests/Feature/ScopingRt/KelolaTautanTest.php`
Expected: semua PASS.

Periksa sintaks JS kedua form (blok `@push('js')`):

Run (bash): `for f in resources/views/admin/user/create.blade.php resources/views/admin/user/edit.blade.php; do awk '/<script>/{f=1;next} /<\/script>/{if(f)exit} f' $f | sed "s/{{[^}]*}}/x/g" > /tmp/cek.js; node --check /tmp/cek.js && echo "$f OK"; done`
Expected: dua baris `OK`.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Requests/Admin/User/storeUserRequest.php app/Repositories/Admin/User/UserRepository.php app/Http/Controllers/AdminController.php resources/views/admin/user tests/Feature/AkunKesmas/KelolaAkunKesmasTest.php
git commit -m "feat(akun-kesmas): super-admin membuat akun tujuh peran Kesmas, satu akun per kelurahan"
```

---

### Task 8: Dokumentasi, suite penuh, dan uji browser per peran

**Files:**
- Modify: `CLAUDE.md` (bagian baru)

- [ ] **Step 1: Tambah catatan di `CLAUDE.md`** (sebelum bagian "Export Data Anak")

```markdown
### Akun peran Kesmas: `AksesKesmas` satu-satunya sumber izin, dan `IsAdmin` tak boleh meloloskannya

Peran `pimpinan`, `kelurahan`, `kader`, `kesmas_puskesmas`, `kesmas_rs`, `sarkes`
(spec `docs/superpowers/specs/2026-10-14-akun-kesmas-design.md`) hanya membuka rute baca dasbor yang
dijaga middleware `dasbor:{nama}`. Grup `is_admin` membuka SELURUH `/admin` (Data Anak, Import, Master Data).

- **Peran baru wajib masuk `AksesKesmas::PERAN_TERBATAS`** — `adminSetara()` menolaknya apa pun `type`-nya.
  `typeFromRole()` mendaftar peran ber-`type` 1 secara eksplisit; default 2. Jangan kembali ke `default => 1`:
  setiap peran yang ditambahkan nanti diam-diam jadi admin lewat cabang legacy `IsAdmin`.
- **Rute baca dasbor baru** masuk grup `['auth']` + `dasbor:…`; rute ekspor/tulis tetap di `is_admin`.
- **Kunci wilayah menang atas query string**: `$filters = $akses->kunciFilter() + $filters` (urutan operan
  `+` penting — operan kiri menang). Registri/Export memakai `kunciDataPribadi()`, superset untuk `imunisasi_faskes`.
- `BatasDataPribadiKesmas` sudah dilebur ke `AksesKesmas` — aturan SEC-001 (`imunisasi_faskes` RS ditolak data
  pribadi) ada di `AksesKesmas::untuk()`.
- Tabel `posyandu` tak punya kolom kelurahan — akun kader hanya bisa menurunkan `id_puskesmas`.
- Dikunci `tests/Feature/AkunKesmas/*` (matriks peran × rute, kunci lingkup, form akun).
```

- [ ] **Step 2: Jalankan seluruh suite** (±10+ menit; jangan bersamaan dengan proses PHPUnit lain)

Run: `php artisan view:clear; php vendor/bin/phpunit`
Expected: semua PASS kecuali 2 kegagalan lama `KelompokVaksinKosongTest` (sudah merah sejak 66b5e3c). Kegagalan lain = regresi, perbaiki sebelum lanjut.

- [ ] **Step 3: Uji browser per peran** (preferensi proyek: superadmin, admin, faskes, lingkup wilayah, dan satu yang harus ditolak)

Nyalakan MySQL & server lokal (lihat memori "Menjalankan & Memotret SIRINDU Lokal"). Buat lewat form super-admin satu akun per peran baru di DB dev, lalu untuk tiap akun:
- login → mendarat di beranda yang benar (Sarkes → halaman "akses belum diatur");
- menu samping hanya berisi dasbor yang diizinkan; logo kembali ke beranda;
- Kelurahan/Kader/Puskesmas: dropdown wilayah terkunci, angka berubah sesuai lingkup, RT bisa dipilih untuk akun kelurahan;
- Pimpinan: modal daftar nama terbuka di Imunisasi & OT, tanpa tombol Export, kolom PJ tidak bisa diklik;
- buka `/admin/data-dasar-anak` langsung → halaman 403 dengan tombol kembali ke beranda;
- superadmin & admin lama: semua menu dan tombol seperti sebelumnya.

Catat hasilnya di pesan commit/PR; yang tak bisa dicek (mis. browser tak tersambung) disebut eksplisit.

- [ ] **Step 4: Commit**

```bash
git add CLAUDE.md
git commit -m "docs: catatan akun peran Kesmas di CLAUDE.md"
```

---

## Catatan deploy (untuk pesan PR, bukan task)

- Prod wajib `php artisan migrate` (kolom `users.nama_sarana`) + `php artisan route:clear view:clear`.
- Tidak ada perubahan data; akun lama tidak tersentuh.

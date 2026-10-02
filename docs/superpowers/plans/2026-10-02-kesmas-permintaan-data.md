# Tindak Lanjut Permintaan Data Klien (Kesmas) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menindaklanjuti 17 item lembar "PERMINTAAN DATA" klien — field HBIG, tanda Sasaran Balita Kesmas (opt-in) beserta perintah penandaan massal, tahun sasaran rumus Kesmas, input BB dalam gram untuk umur < 2 bulan di form pengukuran, dan penyelarasan kartu dasbor Kesmas — tanpa mengubah perilaku modul imunisasi, Operasi Timbang (OT), maupun PD3I.

**Architecture:** Perluas jalur Kesmas yang sudah ada (`KesmasRules`/`kolomKesmas()`, `KesmasPresenter`, `KesmasAnakSheet`, `KesmasDashboardService`) dengan dua value object murni (`TahunSasaranKesmas`, `SatuanBeratBadan`), satu helper validasi (`AturanBeratBadan`), satu perintah artisan (`kesmas:tandai-sasaran`), dan tabel audit `sasaran_kesmas_log`. `data_anak.bb` tetap kg; penulisan massal ke `anak` lewat query builder tanpa `updated_at` dan tanpa event model.

**Tech Stack:** Laravel 12 · PHP 8.4 · MySQL 8 · Carbon 3 · Bootstrap 4 + jQuery · Maatwebsite Excel / PhpSpreadsheet · PHPUnit · Playwright

**Spec:** `docs/superpowers/specs/2026-10-02-kesmas-permintaan-data-design.md`

## Global Constraints

- Kerja di branch `feat/kesmas-permintaan-data` di checkout utama `D:\apps\laragon\www\sirindu` — **bukan worktree** (Apache hanya melayani path ini; junction `vendor/` di worktree membuat autoload mengambil kode branch lain).
- MySQL lokal mati secara default. Nyalakan sebelum tes (PowerShell): `Start-Process -WindowStyle Hidden -FilePath "D:\apps\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqld.exe" -ArgumentList '--defaults-file=D:\apps\laragon\bin\mysql\mysql-8.0.30-winx64\my.ini'` (user `root`, password kosong).
- DB tes `sirindu_testing` (dari `phpunit.xml`). **Jangan menjalankan dua proses `php artisan test` bersamaan** — keduanya memakai DB yang sama dan saling menghapus. Suite penuh > 10 menit; selama task selalu pakai `--filter`.
- Kolom baru `anak.tgl_hbig` dan `anak.sasaran_balita_kesmas` **nullable tanpa DEFAULT**. NULL = belum diisi / belum pernah ditandai. Jangan menambah `->default()` atau backfill.
- Dasbor Kesmas **opt-in**: hanya `sasaran_balita_kesmas = 1` yang dihitung — NULL dan 0 tidak.
- Form **Edit Anak**: anak NULL + checkbox tak dicentang (`'0'`) **tetap NULL**. Form **Tambah Anak** (default tercentang): `'0'` disimpan 0.
- Penulisan massal ke `anak` (perintah `kesmas:tandai-sasaran`) **wajib `DB::table()`**, tanpa `updated_at`, tanpa Eloquent — `AnakObserver::saved` memicu refresh prioritas gizi (OT) dan `CapilDedupService::sigiziUntouched()` membaca `updated_at = created_at`.
- `data_anak.bb` **selalu kg**. Satuan input BB = gram bila `tgl_kunjungan < tgl_lahir + 2 bulan` (`addMonthsNoOverflow(2)`; tepat di batas = kg), selain itu kg. Rentang: gram **300–8.000**, kg **1–150**. Berlaku **hanya** di Tambah Data Pengukuran (`data-anak.blade.php`) dan form per kunjungan di Edit Anak. Form identitas Tambah/Edit Anak dan BBL tetap kg.
- Di `updateDataAnak`, rentang BB dicek **hanya bila nilainya berubah** (toleransi 0,5 g); nilai yang tak berubah ditulis ulang apa adanya.
- Tahun sasaran (rumus Kesmas) = tahun lahir + `idl` 1, `24_bln` 2, `ibl` 3, `48_bln` 4, `60_bln` 5, `72_bln` 6. Hanya tampilan (detail anak + Export Kesmas); tidak disimpan.
- K4 = **0–59 bulan**; kode usia registri `balita_0_59` = `[0, 59]`, dipakai bersama K4 dan chip registri.
- Teks UI persis: "Sasaran Balita Kesmas", "Tanggal pemberian HBIG", "Balita Dilayani Tumbuh Kembang", "Cakupan Balita &amp; Anak Prasekolah Dilayani SDIDTK", "Semua balita (0–59)".
- Value object `TahunSasaranKesmas` dan `SatuanBeratBadan` **tidak boleh** memanggil `config()`, `now()`, DB, atau container — unit test-nya memakai `PHPUnit\Framework\TestCase` polos.
- Bootstrap 4 (`data-toggle`), bukan `data-bs-*`. `@section('x') isi @endsection` selalu berspasi. Tidak ada `required`/`min`/`max` di dalam kartu collapse Kesmas. Setelah mengubah Blade: `php artisan view:clear`.
- Payload tes meniru form asli: checkbox tak dicentang mengirim `'0'`, select/teks kosong mengirim `''` — bukan menghilangkan field. Tes yang menyentuh `ImunisasiStatusService` memanggil `ImunisasiStatusService::flushCache()` di `setUp()`; yang memakai puskesmas memanggil `WilkerPuskesmas::flushCache()`.
- Jangan mengubah berkas di luar daftar izin spec §10 (diperiksa mekanis di Task 11). Khususnya **jangan sentuh**: `ImunisasiStatusService`, `KohortImunisasi`, `FilterWilayahAnak`, `WilkerPuskesmas`, `app/Imports/**`, `app/Observers/**`, `PrioritasGiziService`, `OtGiziService`, `CapilDedupService`, `VerifikasiRtService`, `app/Models/Anak.php`, `database/factories/**`, `routes/web.php`, sidebar.
- Tulis berkas PHP/tes lewat editor (Write/Edit), **bukan heredoc bash** — heredoc merusak `\b`, `\x`, `\H` di regex.
- Pesan commit bahasa Indonesia (`feat(kesmas): …`, `test(kesmas): …`, `docs(kesmas): …`), diakhiri baris atribusi yang berlaku di sesi yang menjalankan.

## Review Focus

Lima kondisi yang tersirat di spec tetapi mudah lolos tanpa tes. Masing-masing sudah diberi tes di task pemilik kodenya:

1. **Tanggal kunjungan di form edit digeser melewati batas 2 bulan saat JS tidak berjalan** — nilai gram (3250) terkirim apa adanya dan dibaca server sebagai kg. Harus ditolak dengan pesan satuan kg, bukan tersimpan 3.250 kg. → Task 7 `test_tanggal_digeser_melewati_batas_tanpa_js_ditolak_bukan_tersimpan_ribuan_kg`.
2. **Anak lahir di akhir bulan (31 Des, 31 Jan)** — batas gram tidak boleh meluber ke Maret, dan label di browser harus sama persis dengan keputusan server. → Task 2 `test_batas_gram_dua_bulan_tanpa_luapan_akhir_bulan`, Task 7 `test_batas_gram_akhir_bulan_tidak_meluber`.
3. **Tanggal lahir tak sah di data lama** (kosong, `0000-00-00`, format lain) — tahun sasaran tidak boleh tampil "1"/"1901", dan satuan BB jatuh ke kg (perilaku lama) tanpa error. → Task 1 `test_tanggal_tak_sah_menghasilkan_null`, Task 2 `test_tanggal_tak_sah_jatuh_ke_kg_perilaku_lama`.
4. **Petugas membuka Edit Anak untuk anak lama (NULL) hanya untuk membetulkan nama** — tanda tetap NULL, tak ada baris log, dan perintah massal masih menjangkaunya. → Task 5 `test_edit_null_tak_dicentang_tetap_null_tanpa_log`, Task 6 `test_menandai_hanya_null_di_wilayah_dan_melewati_dilepas_pindah_meninggal_tidak_aktif`.
5. **Hari pertama setelah rilis: wilayah tanpa satu pun anak bertanda** — semua kartu "—" (bukan 0,0 %) dan peringatan menjelaskan sebabnya. → Task 9 `test_baris_penandaan_dan_peringatan_saat_belum_ada_anak_bertanda`.

## Peta berkas

| Berkas | Tanggung jawab | Task |
|---|---|---|
| `app/Support/TahunSasaranKesmas.php` (baru) | Enam tahun sasaran dari tanggal lahir | 1 |
| `app/Support/SatuanBeratBadan.php` (baru) | Batas gram, satuan, konversi, rentang | 2 |
| `database/migrations/2026_10_02_000001_add_hbig_dan_sasaran_kesmas_to_anak_table.php` (baru) | `anak.tgl_hbig`, `anak.sasaran_balita_kesmas` | 3 |
| `database/migrations/2026_10_02_000002_create_sasaran_kesmas_log_table.php` (baru) | Tabel `sasaran_kesmas_log` | 3 |
| `app/Models/SasaranKesmasLog.php` (baru) | Model log audit | 3 |
| `app/Http/Requests/Admin/Anak/KesmasRules.php` | Rule `tgl_hbig`; method `sasaran()` | 4, 5 |
| `resources/views/admin/anak/partials/form-riwayat-lahir.blade.php` | Input HBIG | 4 |
| `app/Http/Requests/Admin/Anak/storeAnakRequest.php` | Validasi tanda di Tambah Anak | 5 |
| `app/Repositories/Admin/Anak/AnakRepository.php` | Simpan tanda + log dalam transaksi | 5 |
| `app/Console/Commands/TandaiSasaranKesmas.php` (baru) | Penandaan massal & pembatalan | 6 |
| `app/Http/Requests/Admin/Anak/AturanBeratBadan.php` (baru) | Rule & konversi BB form pengukuran | 7 |
| `app/Http/Controllers/AdminController.php` | `updateAnak` (validasi tanda), `storeDataAnak`/`updateDataAnak` (BB) | 5, 7 |
| `public/js/satuan-bb.js` (baru) | Label/nilai BB mengikuti tanggal kunjungan | 7 |
| `resources/views/admin/anak/{create,edit,data-anak}.blade.php` | Checkbox tanda, input BB, label "(kg)" | 5, 7 |
| `app/Services/KesmasPresenter.php` | Label status tanda | 8 |
| `resources/views/admin/anak/show.blade.php` | Baris HBIG, kartu Sasaran Kesmas | 4, 8 |
| `app/Exports/KesmasAnakSheet.php` | Kolom AG–AN | 8 |
| `app/Services/KesmasDashboardService.php` | Populasi opt-in, penandaan, K4 0–59, HBIG | 9, 10 |
| `app/Http/Controllers/KesmasDashboardController.php` | Kirim `penandaan` ke view | 9 |
| `resources/views/admin/kesmas/**`, `public/css/kesmas-dashboard.css`, `public/js/kesmas-registri.js` | Baris penandaan, label kartu, chip 0–59, HBIG | 9, 10 |
| `CLAUDE.md`, `docs/kesmas-pemetaan-permintaan-data.md` | Catatan jebakan & lampiran klien | 11 |

---

### Task 1: Value object `TahunSasaranKesmas`

**Files:**
- Create: `app/Support/TahunSasaranKesmas.php`
- Test: `tests/Unit/Support/TahunSasaranKesmasTest.php`

**Interfaces:**
- Consumes: —
- Produces: `App\Support\TahunSasaranKesmas::TAHAP` (`array<string, array{0: int, 1: string}>`, kunci urut `idl`, `24_bln`, `ibl`, `48_bln`, `60_bln`, `72_bln`); `TahunSasaranKesmas::coba(?string $tglLahir): ?self`; `->tahun(string $tahap): int` (`InvalidArgumentException` untuk kode asing); `->semua(): array<string, array{label: string, tahun: int}>`.

- [ ] **Step 0: Siapkan lingkungan dan catat baseline**

PowerShell:
```powershell
git -C D:\apps\laragon\www\sirindu branch --show-current
git -C D:\apps\laragon\www\sirindu status --short
Start-Process -WindowStyle Hidden -FilePath "D:\apps\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqld.exe" -ArgumentList '--defaults-file=D:\apps\laragon\bin\mysql\mysql-8.0.30-winx64\my.ini'
& D:\apps\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe -u root -e "SHOW DATABASES LIKE 'sirindu%';"
```
Expected: branch `feat/kesmas-permintaan-data`, status kosong, database `sirindu` dan `sirindu_testing` ada.

Baseline (Bash, satu proses, ±4–5 menit):
```bash
cd /d/apps/laragon/www/sirindu && php artisan test --filter=Kesmas --compact > storage/logs/kesmas-permintaan-baseline.log 2>&1; tail -5 storage/logs/kesmas-permintaan-baseline.log
```
Expected: semua lulus. Kalau ada yang gagal di baseline, **berhenti dan laporkan** — jangan mulai sebelum baseline hijau.

- [ ] **Step 1: Tulis tes yang gagal**

Create `tests/Unit/Support/TahunSasaranKesmasTest.php`:
```php
<?php

namespace Tests\Unit\Support;

use App\Support\TahunSasaranKesmas;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Rumus Kesmas dari lembar klien (spec 2026-10-02 §5.4): tahun lahir + n, kalender murni. */
class TahunSasaranKesmasTest extends TestCase
{
    public function test_enam_tahap_mengikuti_rumus_tahun_lahir_plus_n(): void
    {
        $this->assertSame([
            'idl'    => ['label' => 'IDL', 'tahun' => 2026],
            '24_bln' => ['label' => 'Sasaran 24 bulan', 'tahun' => 2027],
            'ibl'    => ['label' => 'IBL', 'tahun' => 2028],
            '48_bln' => ['label' => 'Sasaran 48 bulan', 'tahun' => 2029],
            '60_bln' => ['label' => 'Sasaran 60 bulan', 'tahun' => 2030],
            '72_bln' => ['label' => 'Sasaran 72 bulan', 'tahun' => 2031],
        ], TahunSasaranKesmas::coba('2025-05-10')->semua());
    }

    public function test_hanya_tahun_kalender_yang_menentukan_bukan_bulan_lahir(): void
    {
        // Beda sengaja dari KohortImunisasi (cut-off 1 April): anak lahir Januari dan Desember
        // di tahun yang sama mendapat tahun sasaran yang sama.
        $this->assertSame(2026, TahunSasaranKesmas::coba('2025-01-01')->tahun('idl'));
        $this->assertSame(2026, TahunSasaranKesmas::coba('2025-12-31')->tahun('idl'));
        $this->assertSame(2028, TahunSasaranKesmas::coba('2025-01-01')->tahun('ibl'));
    }

    public function test_menerima_datetime_dari_database(): void
    {
        $this->assertSame(2026, TahunSasaranKesmas::coba('2025-03-01 00:00:00')->tahun('idl'));
    }

    public function test_tanggal_tak_sah_menghasilkan_null(): void
    {
        foreach ([null, '', '   ', '0000-00-00', '2025-02-30', '10/05/2025', 'bukan tanggal', '1899-12-31'] as $tgl) {
            $this->assertNull(TahunSasaranKesmas::coba($tgl), var_export($tgl, true));
        }
    }

    public function test_tahap_asing_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TahunSasaranKesmas::coba('2025-05-10')->tahun('ibl2');
    }

    public function test_urutan_tahap_tetap(): void
    {
        $this->assertSame(['idl', '24_bln', 'ibl', '48_bln', '60_bln', '72_bln'], array_keys(TahunSasaranKesmas::TAHAP));
    }
}
```

- [ ] **Step 2: Jalankan dan pastikan gagal**

Run: `php artisan test --filter=TahunSasaranKesmasTest`
Expected: FAIL — `Class "App\Support\TahunSasaranKesmas" not found`.

- [ ] **Step 3: Implementasi**

Create `app/Support/TahunSasaranKesmas.php`:
```php
<?php
// app/Support/TahunSasaranKesmas.php

namespace App\Support;

use InvalidArgumentException;

/**
 * Tahun sasaran per tahap — rumus Kesmas dari lembar "PERMINTAAN DATA" klien
 * (spec docs/superpowers/specs/2026-10-02-kesmas-permintaan-data-design.md §5.4):
 * tahun lahir + 1 (IDL), +2 (24 bln), +3 (IBL), +4 (48 bln), +5 (60 bln), +6 (72 bln).
 *
 * SENGAJA beda dari KohortImunisasi (cut-off 1 Apr–31 Mar, IBL dinilai di tahun Baduta ≈ +2):
 * modul imunisasi tidak disentuh, dan halaman yang menampilkan angka ini menyebut perbedaannya.
 * Kalau klien mengoreksi posisi IBL, cukup ubah TAHAP.
 *
 * Tidak disimpan — dihitung dari tgl_lahir saat tampil, jadi koreksi tanggal lahir langsung ikut.
 * Murni PHP: tanpa DB, config(), atau now().
 */
final class TahunSasaranKesmas
{
    /** kode => [selisih tahun dari tahun lahir, label]. Urutan = urutan tampil. */
    public const TAHAP = [
        'idl'    => [1, 'IDL'],
        '24_bln' => [2, 'Sasaran 24 bulan'],
        'ibl'    => [3, 'IBL'],
        '48_bln' => [4, 'Sasaran 48 bulan'],
        '60_bln' => [5, 'Sasaran 60 bulan'],
        '72_bln' => [6, 'Sasaran 72 bulan'],
    ];

    private function __construct(private readonly int $tahunLahir)
    {
    }

    /** null bila kosong, bukan Y-m-d, tanggal mustahil, atau tahun < 1900 (mis. '0000-00-00'). */
    public static function coba(?string $tglLahir): ?self
    {
        $tgl = substr(trim((string) $tglLahir), 0, 10);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $tgl, $m)) {
            return null;
        }
        [$tahun, $bulan, $hari] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        if ($tahun < 1900 || !checkdate($bulan, $hari, $tahun)) {
            return null;
        }

        return new self($tahun);
    }

    public function tahun(string $tahap): int
    {
        if (!isset(self::TAHAP[$tahap])) {
            throw new InvalidArgumentException("Tahap tahun sasaran tidak dikenal: {$tahap}");
        }

        return $this->tahunLahir + self::TAHAP[$tahap][0];
    }

    /** @return array<string, array{label: string, tahun: int}> */
    public function semua(): array
    {
        $hasil = [];
        foreach (self::TAHAP as $kode => [$selisih, $label]) {
            $hasil[$kode] = ['label' => $label, 'tahun' => $this->tahunLahir + $selisih];
        }

        return $hasil;
    }
}
```

- [ ] **Step 4: Jalankan dan pastikan lulus**

Run: `php artisan test --filter=TahunSasaranKesmasTest`
Expected: PASS — 6 tes.

- [ ] **Step 5: Commit**

```bash
git add app/Support/TahunSasaranKesmas.php tests/Unit/Support/TahunSasaranKesmasTest.php
git commit -m "feat(kesmas): tahun sasaran rumus Kesmas (tahun lahir + 1..6) sebagai value object murni"
```

---

### Task 2: Value object `SatuanBeratBadan`

**Files:**
- Create: `app/Support/SatuanBeratBadan.php`
- Test: `tests/Unit/Support/SatuanBeratBadanTest.php`

**Interfaces:**
- Consumes: —
- Produces: `App\Support\SatuanBeratBadan` dengan konstanta `GRAM = 'g'`, `KG = 'kg'`, `RENTANG = ['g' => [300, 8000], 'kg' => [1, 150]]` dan method statis `batasGram(?string $tglLahir): string` (Y-m-d, `''` bila tak sah), `untuk(?string $tglLahir, ?string $tglKunjungan): string` (`'g'`|`'kg'`), `keKg(float $nilai, string $satuan): float`, `untukTampil(float $kg, string $satuan): int|float`, `dalamRentang(float $nilai, string $satuan): bool`, `sama(float $kgA, float $kgB): bool`, `label(string $satuan): string` (`'gram'`|`'kg'`).

- [ ] **Step 1: Tulis tes yang gagal**

Create `tests/Unit/Support/SatuanBeratBadanTest.php`:
```php
<?php

namespace Tests\Unit\Support;

use App\Support\SatuanBeratBadan as S;
use PHPUnit\Framework\TestCase;

/** Satuan input BB form pengukuran (spec 2026-10-02 §5.5): < 2 bulan gram, selain itu kg. */
class SatuanBeratBadanTest extends TestCase
{
    public function test_batas_gram_dua_bulan_tanpa_luapan_akhir_bulan(): void
    {
        $this->assertSame('2026-03-15', S::batasGram('2026-01-15'));
        $this->assertSame('2027-02-28', S::batasGram('2026-12-31'), '31 Des + 2 bln = akhir Februari, bukan 3 Maret');
        $this->assertSame('2028-02-29', S::batasGram('2027-12-31'), 'Februari kabisat');
        $this->assertSame('2024-04-29', S::batasGram('2024-02-29'));
        $this->assertSame('2026-02-28', S::batasGram('2025-12-30'));
        $this->assertSame('2025-03-10', S::batasGram('2025-01-10 00:00:00'), 'datetime dari DB diterima');
    }

    public function test_sehari_sebelum_batas_gram_tepat_batas_kg(): void
    {
        $this->assertSame(S::GRAM, S::untuk('2026-12-31', '2027-02-27'));
        $this->assertSame(S::KG, S::untuk('2026-12-31', '2027-02-28'), '"< 2 bulan" = belum genap 2 bulan');
        $this->assertSame(S::GRAM, S::untuk('2025-01-10', '2025-01-10'), 'hari lahir');
        $this->assertSame(S::KG, S::untuk('2025-01-10', '2025-06-10'));
    }

    public function test_tanggal_tak_sah_jatuh_ke_kg_perilaku_lama(): void
    {
        $this->assertSame('', S::batasGram('0000-00-00'));
        $this->assertSame('', S::batasGram(null));
        $this->assertSame(S::KG, S::untuk('0000-00-00', '2025-02-10'));
        $this->assertSame(S::KG, S::untuk('2025-01-10', ''));
        $this->assertSame(S::KG, S::untuk('2025-01-10', '10/02/2025'));
    }

    public function test_konversi_dan_tampilan(): void
    {
        $this->assertSame(3.25, S::keKg(3250, S::GRAM));
        $this->assertSame(7.5, S::keKg(7.5, S::KG));
        $this->assertSame(3200, S::untukTampil(3.2000000476837, S::GRAM), 'sisa FLOAT MySQL dibuang');
        $this->assertSame(3255, S::untukTampil(3.255, S::GRAM));
        $this->assertSame(7.5, S::untukTampil(7.5, S::KG));
        $this->assertSame('gram', S::label(S::GRAM));
        $this->assertSame('kg', S::label(S::KG));
    }

    public function test_rentang_tidak_beririsan_sehingga_salah_satuan_pasti_ditolak(): void
    {
        $this->assertTrue(S::dalamRentang(300, S::GRAM));
        $this->assertTrue(S::dalamRentang(8000, S::GRAM));
        $this->assertFalse(S::dalamRentang(3.25, S::GRAM), 'kg diketik di kolom gram');
        $this->assertFalse(S::dalamRentang(8001, S::GRAM));
        $this->assertTrue(S::dalamRentang(1, S::KG));
        $this->assertTrue(S::dalamRentang(150, S::KG));
        $this->assertFalse(S::dalamRentang(3250, S::KG), 'gram diketik di kolom kg');
        $this->assertFalse(S::dalamRentang(0, S::KG));
        $this->assertGreaterThan(S::RENTANG[S::KG][1], S::RENTANG[S::GRAM][0]);
    }

    public function test_sama_dalam_toleransi_setengah_gram(): void
    {
        $this->assertTrue(S::sama(3.25, 3.2500004));
        $this->assertTrue(S::sama(0.0, 0.0));
        $this->assertFalse(S::sama(3.25, 3.251));
    }
}
```

- [ ] **Step 2: Jalankan dan pastikan gagal**

Run: `php artisan test --filter=SatuanBeratBadanTest`
Expected: FAIL — `Class "App\Support\SatuanBeratBadan" not found`.

- [ ] **Step 3: Implementasi**

Create `app/Support/SatuanBeratBadan.php`:
```php
<?php
// app/Support/SatuanBeratBadan.php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Satuan input berat badan di form pengukuran (spec docs/superpowers/specs/2026-10-02-kesmas-permintaan-data-design.md §5.5):
 * umur di bawah 2 bulan pada tanggal kunjungan → gram, selain itu kg. Yang DISIMPAN selalu kg —
 * data_anak.bb dibaca z-score, PrioritasGiziService, OtGiziService, dasbor, dan importer.
 *
 * Batas = tgl_lahir + 2 bulan TANPA luapan akhir bulan (31 Des → 28/29 Feb), setara MySQL
 * DATE_ADD(tgl_lahir, INTERVAL 2 MONTH). Kunjungan tepat di batas = kg. JS form hanya
 * membandingkan tanggal dengan batas ini (string ISO), jadi tidak ada aritmetika bulan yang bisa
 * berbeda antara PHP dan browser.
 *
 * Rentang gram (≥ 300) dan kg (≤ 150) sengaja tidak beririsan: salah satuan pasti ditolak, tidak
 * pernah tersimpan diam-diam. Tanggal tak sah → kg (perilaku sebelum aturan ini ada).
 * Murni: tanpa DB, config(), atau now().
 */
final class SatuanBeratBadan
{
    public const GRAM = 'g';
    public const KG = 'kg';

    /** [min, maks] inklusif per satuan. */
    public const RENTANG = [self::GRAM => [300, 8000], self::KG => [1, 150]];

    /** Y-m-d tanggal mulai kg; '' bila tanggal lahir kosong/tak sah. */
    public static function batasGram(?string $tglLahir): string
    {
        $lahir = self::tanggal($tglLahir);

        return $lahir ? $lahir->addMonthsNoOverflow(2)->toDateString() : '';
    }

    public static function untuk(?string $tglLahir, ?string $tglKunjungan): string
    {
        $batas = self::batasGram($tglLahir);
        $kunjungan = self::tanggal($tglKunjungan);
        if ($batas === '' || $kunjungan === null) {
            return self::KG;
        }

        return $kunjungan->toDateString() < $batas ? self::GRAM : self::KG;
    }

    public static function keKg(float $nilai, string $satuan): float
    {
        return $satuan === self::GRAM ? $nilai / 1000 : $nilai;
    }

    /** Nilai awal di form: gram dibulatkan ke gram bulat (sisa presisi FLOAT MySQL dibuang). */
    public static function untukTampil(float $kg, string $satuan): int|float
    {
        return $satuan === self::GRAM ? (int) round($kg * 1000) : $kg;
    }

    public static function dalamRentang(float $nilai, string $satuan): bool
    {
        [$min, $maks] = self::RENTANG[$satuan];

        return $nilai >= $min && $nilai <= $maks;
    }

    /** Sama dalam toleransi 0,5 gram — membandingkan input ulang dengan nilai tersimpan. */
    public static function sama(float $kgA, float $kgB): bool
    {
        return abs($kgA - $kgB) < 0.0005;
    }

    public static function label(string $satuan): string
    {
        return $satuan === self::GRAM ? 'gram' : 'kg';
    }

    private static function tanggal(?string $tgl): ?CarbonImmutable
    {
        $tgl = substr(trim((string) $tgl), 0, 10);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $tgl, $m)) {
            return null;
        }
        [$tahun, $bulan, $hari] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        if ($tahun < 1900 || !checkdate($bulan, $hari, $tahun)) {
            return null;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $tgl);
    }
}
```

- [ ] **Step 4: Jalankan dan pastikan lulus**

Run: `php artisan test --filter=SatuanBeratBadanTest`
Expected: PASS — 6 tes.

- [ ] **Step 5: Commit**

```bash
git add app/Support/SatuanBeratBadan.php tests/Unit/Support/SatuanBeratBadanTest.php
git commit -m "feat(kesmas): aturan satuan BB gram < 2 bulan sebagai value object murni"
```

---

### Task 3: Migrasi kolom HBIG & tanda sasaran + tabel log

**Files:**
- Create: `database/migrations/2026_10_02_000001_add_hbig_dan_sasaran_kesmas_to_anak_table.php`
- Create: `database/migrations/2026_10_02_000002_create_sasaran_kesmas_log_table.php`
- Create: `app/Models/SasaranKesmasLog.php`
- Test: `tests/Feature/Kesmas/MigrasiSasaranHbigTest.php`

**Interfaces:**
- Consumes: —
- Produces: kolom `anak.tgl_hbig` (`date` null), `anak.sasaran_balita_kesmas` (`tinyint(1)` null); tabel `sasaran_kesmas_log` (`id`, `id_anak`, `nilai_lama`, `nilai_baru`, `sumber` enum `form_tambah|form_edit|perintah|batal`, `batch` char(36), `alasan` varchar(255), `id_user`, `created_at`); model `App\Models\SasaranKesmasLog` (`UPDATED_AT = null`, `$guarded = []`).

- [ ] **Step 1: Tulis tes yang gagal**

Create `tests/Feature/Kesmas/MigrasiSasaranHbigTest.php`:
```php
<?php

namespace Tests\Feature\Kesmas;

use App\Models\SasaranKesmasLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Spec 2026-10-02 §4: dua kolom anak nullable TANPA DEFAULT + tabel audit penandaan. */
class MigrasiSasaranHbigTest extends TestCase
{
    use RefreshDatabase;

    private function kolom(string $tabel): Collection
    {
        return collect(DB::select(
            'SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$tabel]
        ))->keyBy('COLUMN_NAME');
    }

    public function test_kolom_anak_baru_nullable_tanpa_default(): void
    {
        $k = $this->kolom('anak');
        foreach (['tgl_hbig' => 'date', 'sasaran_balita_kesmas' => 'tinyint(1)'] as $nama => $tipe) {
            $this->assertTrue($k->has($nama), "anak.$nama tidak ada");
            $this->assertSame('YES', $k[$nama]->IS_NULLABLE, "anak.$nama harus nullable");
            $this->assertNull($k[$nama]->COLUMN_DEFAULT, "anak.$nama tidak boleh punya DEFAULT — NULL = belum diisi/ditandai");
            $this->assertSame($tipe, $k[$nama]->COLUMN_TYPE);
        }
    }

    public function test_tabel_log_penandaan(): void
    {
        $this->assertTrue(Schema::hasTable('sasaran_kesmas_log'));
        $k = $this->kolom('sasaran_kesmas_log');
        foreach (['id', 'id_anak', 'nilai_lama', 'nilai_baru', 'sumber', 'batch', 'alasan', 'id_user', 'created_at'] as $nama) {
            $this->assertTrue($k->has($nama), "sasaran_kesmas_log.$nama tidak ada");
        }
        $this->assertFalse($k->has('updated_at'), 'Log audit hanya ditambah, tidak pernah diubah');
        $this->assertSame("enum('form_tambah','form_edit','perintah','batal')", $k['sumber']->COLUMN_TYPE);
    }

    public function test_model_log_mengisi_created_at_tanpa_updated_at(): void
    {
        $log = SasaranKesmasLog::create(['id_anak' => 1, 'nilai_lama' => null, 'nilai_baru' => 1, 'sumber' => 'form_tambah']);

        $this->assertNotNull($log->fresh()->created_at);
        $this->assertNull($log->fresh()->nilai_lama);
    }
}
```

- [ ] **Step 2: Jalankan dan pastikan gagal**

Run: `php artisan test --filter=MigrasiSasaranHbigTest`
Expected: FAIL — `anak.tgl_hbig tidak ada` (dan `Class "App\Models\SasaranKesmasLog" not found`).

- [ ] **Step 3: Implementasi**

Create `database/migrations/2026_10_02_000001_add_hbig_dan_sasaran_kesmas_to_anak_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec docs/superpowers/specs/2026-10-02-kesmas-permintaan-data-design.md §4.
 * Keduanya nullable TANPA DEFAULT:
 * - tgl_hbig: NULL = belum diisi.
 * - sasaran_balita_kesmas: NULL = belum pernah ditandai, 0 = dilepas, 1 = sasaran. Dasbor Kesmas
 *   hanya menghitung 1 (opt-in). DEFAULT 1 memasukkan ±15 rb anak lama ke sasaran tanpa keputusan
 *   siapa pun; DEFAULT 0 menghapus beda "belum ditandai" vs "dilepas" yang dipakai perintah
 *   kesmas:tandai-sasaran.
 * Tanpa after(): kolom ditambahkan di ujung supaya ALTER di prod cepat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anak', function (Blueprint $table) {
            $table->date('tgl_hbig')->nullable();
            $table->boolean('sasaran_balita_kesmas')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('anak', function (Blueprint $table) {
            $table->dropColumn(['tgl_hbig', 'sasaran_balita_kesmas']);
        });
    }
};
```

Create `database/migrations/2026_10_02_000002_create_sasaran_kesmas_log_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak setiap perubahan anak.sasaran_balita_kesmas (spec 2026-10-02 §4): dari form Tambah/Edit
 * Anak, perintah kesmas:tandai-sasaran, atau pembatalannya. Menjawab "kenapa anak ini (tidak)
 * dihitung di dasbor Kesmas?" dan menjadi dasar --batalkan. Tanpa FK: baris audit tetap ada walau
 * anaknya dihapus (sejalan dengan epid_renumber_log).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sasaran_kesmas_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_anak')->index();
            $table->tinyInteger('nilai_lama')->nullable();
            $table->tinyInteger('nilai_baru')->nullable();
            $table->enum('sumber', ['form_tambah', 'form_edit', 'perintah', 'batal']);
            $table->char('batch', 36)->nullable()->index();
            $table->string('alasan', 255)->nullable();
            $table->unsignedBigInteger('id_user')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sasaran_kesmas_log');
    }
};
```

Create `app/Models/SasaranKesmasLog.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Jejak perubahan anak.sasaran_balita_kesmas (spec 2026-10-02 §4). Hanya ditambah, tidak pernah
 * diubah — karena itu tanpa updated_at.
 */
class SasaranKesmasLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'sasaran_kesmas_log';
    protected $guarded = [];
}
```

- [ ] **Step 4: Jalankan dan pastikan lulus, lalu migrasi DB dev**

Run: `php artisan test --filter=MigrasiSasaranHbigTest`
Expected: PASS — 3 tes.

Lalu terapkan ke DB dev `sirindu` (dipakai pengecekan browser di Task 11):
```bash
php artisan migrate
php artisan migrate:status | grep 2026_10_02
```
Expected: kedua migrasi `Ran`.

Regresi migrasi lama: `php artisan test --filter=MigrasiKesmasTest` → PASS.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_10_02_000001_add_hbig_dan_sasaran_kesmas_to_anak_table.php database/migrations/2026_10_02_000002_create_sasaran_kesmas_log_table.php app/Models/SasaranKesmasLog.php tests/Feature/Kesmas/MigrasiSasaranHbigTest.php
git commit -m "feat(kesmas): kolom tgl_hbig & sasaran_balita_kesmas (nullable tanpa default) + tabel sasaran_kesmas_log"
```

---

### Task 4: Field HBIG (form, validasi, detail)

**Files:**
- Modify: `app/Http/Requests/Admin/Anak/KesmasRules.php` (method `anak()`, setelah rule `pemeriksaan_hepatitis_b`)
- Modify: `resources/views/admin/anak/partials/form-riwayat-lahir.blade.php` (setelah blok select Pemeriksaan Hepatitis B, ±baris 108–118)
- Modify: `resources/views/admin/anak/show.blade.php` (`$isiLahir` ±baris 557; baris setelah `@endforeach` skrining ±baris 645)
- Test: `tests/Feature/Kesmas/FormHbigTest.php`

**Interfaces:**
- Consumes: kolom `anak.tgl_hbig` (Task 3).
- Produces: field form `tgl_hbig` (`<input type="date" name="tgl_hbig" id="tgl_hbig">`), rule `'nullable|date|after_or_equal:tgl_lahir|before_or_equal:today'` di `KesmasRules::anak()` (otomatis ikut `kolomKesmas()`), baris "HBIG" di kartu Riwayat Kelahiran.

- [ ] **Step 1: Tulis tes yang gagal**

Create `tests/Feature/Kesmas/FormHbigTest.php`:
```php
<?php

namespace Tests\Feature\Kesmas;

use App\Models\Anak;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Posyandu;
use App\Models\Puskesmas;
use App\Models\Rt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** HBIG = field tanggal anak.tgl_hbig, bukan jenis vaksin (spec 2026-10-02 §5.1). */
class FormHbigTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private array $wilayah;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['type' => 1]);
        $kec = Kecamatan::create(['name' => 'Bontang Utara']);
        $kel = Kelurahan::create(['name' => 'Bontang Baru', 'id_kecamatan' => $kec->id]);
        $pkm = Puskesmas::create(['name' => 'Bontang Utara 1', 'id_kecamatan' => $kec->id]);
        $pos = Posyandu::create(['name' => 'Melati', 'id_puskesmas' => $pkm->id]);
        $rt  = Rt::create(['name' => '01', 'id_kelurahan' => $kel->id, 'id_posyandu' => $pos->id]);
        $this->wilayah = ['id_kec' => $kec->id, 'id_kel' => $kel->id, 'id_puskesmas' => $pkm->id, 'id_posyandu' => $pos->id, 'id_rt' => $rt->id];
    }

    /** Payload form Tambah Anak; tgl_hbig dikirim '' seperti form asli bila tidak diisi. */
    private function payloadTambah(array $extra = []): array
    {
        return array_merge([
            'no_kk' => '6474010101010001', 'nik' => '6474010101230001', 'nama' => 'Bayi HBIG',
            'nik_ortu' => '6474010101900001', 'nama_ibu' => 'Ibu', 'nama_ayah' => 'Ayah', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2025-01-10', 'golda' => 'O', 'anak' => 1, 'no' => '1',
            'tb' => 49, 'bb' => 3.2, 'lla' => 10, 'lk' => 34, 'asi' => 1, 'obat_cacing' => 0,
            'tgl_kunjungan' => '2025-01-11', 'tgl_hbig' => '',
        ], $this->wilayah, $extra);
    }

    private function anakTersimpan(string $nik, ?string $tglHbig): Anak
    {
        return Anak::create(array_merge([
            'nama' => 'Bayi', 'nik' => $nik, 'jk' => 2, 'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2025-01-10',
            'status' => 1, 'no' => '1', 'sumber' => 'manual', 'tgl_hbig' => $tglHbig,
        ], $this->wilayah));
    }

    public function test_tanggal_hbig_tersimpan_dan_dikosongkan_jadi_null(): void
    {
        $this->actingAs($this->admin)->post(route('admin.storeAnak'), $this->payloadTambah(['tgl_hbig' => '2025-01-10']))
            ->assertRedirect(route('admin.anak'))->assertSessionDoesntHaveErrors();
        $anak = Anak::where('nik', '6474010101230001')->firstOrFail();
        $this->assertSame('2025-01-10', $anak->tgl_hbig);

        $this->put(route('admin.updateAnak', $anak->hashid), array_merge($this->payloadTambah(), [
            'status' => 1, 'posisi' => 'L', 'vit_a' => 0, 'pitting_edema' => 0, 'kelas_ibu_balita' => 0, 'mbg' => 0, 'ddtka' => '',
        ]))->assertRedirect(route('admin.anak'))->assertSessionDoesntHaveErrors();

        $this->assertNull($anak->fresh()->tgl_hbig, "'' dari form = dikosongkan petugas");
    }

    public function test_hbig_sebelum_lahir_atau_di_masa_depan_ditolak(): void
    {
        $this->actingAs($this->admin)->from(route('admin.createAnak'))
            ->post(route('admin.storeAnak'), $this->payloadTambah(['tgl_hbig' => '2025-01-09']))
            ->assertRedirect(route('admin.createAnak'))->assertSessionHasErrors('tgl_hbig');

        $this->from(route('admin.createAnak'))
            ->post(route('admin.storeAnak'), $this->payloadTambah(['tgl_hbig' => now()->addDay()->toDateString()]))
            ->assertSessionHasErrors('tgl_hbig');

        $this->assertDatabaseMissing('anak', ['nik' => '6474010101230001']);
    }

    public function test_form_edit_memuat_input_hbig_di_kartu_riwayat_lahir(): void
    {
        $anak = $this->anakTersimpan('6474010101230002', '2025-01-11');

        $html = $this->actingAs($this->admin)->get(route('admin.editAnak', $anak->hashid))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/id="kartuRiwayatLahir" class="collapse">.*<input type="date" name="tgl_hbig" id="tgl_hbig" class="form-control" value="2025-01-11">/s',
            $html
        );
        $this->assertStringContainsString('<label for="tgl_hbig">Tanggal pemberian HBIG</label>', $html);
        $this->assertStringContainsString('Bukan bagian Imunisasi Dasar Lengkap', $html);
    }

    public function test_detail_menampilkan_hbig_di_riwayat_kelahiran(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('admin.showAnak', $this->anakTersimpan('6474010101230003', '2025-01-11')->hashid))
            ->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/HBIG<\/dt>\s*<dd[^>]*>\s*11\/01\/2025\s*</', $html);
    }

    public function test_detail_hbig_kosong_tampil_strip(): void
    {
        $anak = $this->anakTersimpan('6474010101230004', null);
        $anak->update(['penolong_lahir' => 'Bidan']); // agar kartu tidak "Belum diisi"

        $html = $this->actingAs($this->admin)->get(route('admin.showAnak', $anak->hashid))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/HBIG<\/dt>\s*<dd[^>]*>\s*—\s*</', $html);
    }
}
```

- [ ] **Step 2: Jalankan dan pastikan gagal**

Run: `php artisan test --filter=FormHbigTest`
Expected: FAIL — `tgl_hbig` tidak tersimpan (NULL) dan regex input/detail tidak cocok.

- [ ] **Step 3: Implementasi**

`app/Http/Requests/Admin/Anak/KesmasRules.php` — di `anak()`, ganti:
```php
            'pemeriksaan_hepatitis_b' => ['nullable', Rule::in(array_keys($c['hepatitis_b']))],
            'komplikasi_neonatal'     => 'nullable|string',
```
dengan:
```php
            'pemeriksaan_hepatitis_b' => ['nullable', Rule::in(array_keys($c['hepatitis_b']))],
            // HBIG: field tanggal, BUKAN jenis vaksin — tak ikut IDL/jadwal/kejar (spec 2026-10-02 §5.1).
            'tgl_hbig'                => 'nullable|date|after_or_equal:tgl_lahir|before_or_equal:today',
            'komplikasi_neonatal'     => 'nullable|string',
```

`resources/views/admin/anak/partials/form-riwayat-lahir.blade.php` — ganti:
```blade
                                @foreach ($k['hepatitis_b'] as $kode => $teks)
                                <option value="{{ $kode }}" @selected($nilai('pemeriksaan_hepatitis_b') === $kode)>{{ $teks }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
```
dengan:
```blade
                                @foreach ($k['hepatitis_b'] as $kode => $teks)
                                <option value="{{ $kode }}" @selected($nilai('pemeriksaan_hepatitis_b') === $kode)>{{ $teks }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-12">
                        <div class="form-group">
                            <label for="tgl_hbig">Tanggal pemberian HBIG</label>
                            <input type="date" name="tgl_hbig" id="tgl_hbig" class="form-control" value="{{ $nilai('tgl_hbig') }}">
                            <small class="form-text text-muted">Untuk bayi dari ibu HBsAg reaktif. Bukan bagian Imunisasi Dasar Lengkap.</small>
                        </div>
                    </div>
```

`resources/views/admin/anak/show.blade.php` — di `$isiLahir`, ganti:
```php
'pemeriksaan_hepatitis_b', 'komplikasi_neonatal'])
```
dengan:
```php
'pemeriksaan_hepatitis_b', 'komplikasi_neonatal', 'tgl_hbig'])
```
lalu ganti:
```blade
                        @endforeach

                        <dt class="col-sm-5 text-accessible-muted">Komplikasi persalinan</dt>
```
dengan:
```blade
                        @endforeach

                        <dt class="col-sm-5 text-accessible-muted">HBIG</dt>
                        <dd class="col-sm-7">{{ $anak->tgl_hbig ? \Carbon\Carbon::parse($anak->tgl_hbig)->format('d/m/Y') : '—' }}</dd>

                        <dt class="col-sm-5 text-accessible-muted">Komplikasi persalinan</dt>
```

- [ ] **Step 4: Jalankan dan pastikan lulus**

```bash
php artisan view:clear
php artisan test --filter='FormHbigTest|FormAnakKesmasTest|FormKesmasBladeTest|DetailAnakKesmasTest'
```
Expected: PASS semua (FormKesmasBladeTest membuktikan partial tetap tanpa `required`/`min`/`max`).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Requests/Admin/Anak/KesmasRules.php resources/views/admin/anak/partials/form-riwayat-lahir.blade.php resources/views/admin/anak/show.blade.php tests/Feature/Kesmas/FormHbigTest.php
git commit -m "feat(kesmas): field tanggal HBIG di riwayat kelahiran (bukan jenis vaksin)"
```

---

### Task 5: Tanda "Sasaran Balita Kesmas" di Tambah/Edit Anak

**Files:**
- Modify: `app/Http/Requests/Admin/Anak/KesmasRules.php` (method baru `sasaran()`)
- Modify: `app/Http/Requests/Admin/Anak/storeAnakRequest.php:44`
- Modify: `app/Http/Controllers/AdminController.php:250` (`updateAnak`)
- Modify: `app/Repositories/Admin/Anak/AnakRepository.php` (`storeAnak`, `updateAnak`, helper baru)
- Modify: `resources/views/admin/anak/create.blade.php` (setelah kolom No HP, ±baris 101–106)
- Modify: `resources/views/admin/anak/edit.blade.php` (setelah kolom No HP, ±baris 101–106)
- Test: `tests/Feature/Kesmas/FormSasaranKesmasTest.php`

**Interfaces:**
- Consumes: kolom `anak.sasaran_balita_kesmas`, model `SasaranKesmasLog` (Task 3).
- Produces: `KesmasRules::sasaran(): array` (`['sasaran_balita_kesmas' => 'nullable|boolean']`); field form `sasaran_balita_kesmas` (hidden `0` + checkbox `1`, `id="sasaran_balita_kesmas"`); baris log `form_tambah`/`form_edit` dengan `id_user` pengguna login. `AnakRepository::storeAnak()` kini mengembalikan model `Anak`.

- [ ] **Step 1: Tulis tes yang gagal**

Create `tests/Feature/Kesmas/FormSasaranKesmasTest.php`:
```php
<?php

namespace Tests\Feature\Kesmas;

use App\Models\Anak;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Posyandu;
use App\Models\Puskesmas;
use App\Models\Rt;
use App\Models\SasaranKesmasLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tanda "Sasaran Balita Kesmas" (spec 2026-10-02 §5.2). Payload meniru form asli: checkbox tak
 * dicentang mengirim '0' lewat hidden input.
 */
class FormSasaranKesmasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private array $wilayah;
    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['type' => 1]);
        $kec = Kecamatan::create(['name' => 'Bontang Utara']);
        $kel = Kelurahan::create(['name' => 'Bontang Baru', 'id_kecamatan' => $kec->id]);
        $pkm = Puskesmas::create(['name' => 'Bontang Utara 1', 'id_kecamatan' => $kec->id]);
        $pos = Posyandu::create(['name' => 'Melati', 'id_puskesmas' => $pkm->id]);
        $rt  = Rt::create(['name' => '01', 'id_kelurahan' => $kel->id, 'id_posyandu' => $pos->id]);
        $this->wilayah = ['id_kec' => $kec->id, 'id_kel' => $kel->id, 'id_puskesmas' => $pkm->id, 'id_posyandu' => $pos->id, 'id_rt' => $rt->id];
    }

    private function payloadTambah(array $extra = []): array
    {
        return array_merge([
            'no_kk' => '6474010101010001', 'nik' => '6474010101230001', 'nama' => 'Anak Baru',
            'nik_ortu' => '6474010101900001', 'nama_ibu' => 'Ibu', 'nama_ayah' => 'Ayah', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2025-01-10', 'golda' => 'O', 'anak' => 1, 'no' => '1',
            'tb' => 60, 'bb' => 5.5, 'lla' => 12, 'lk' => 40, 'asi' => 1, 'obat_cacing' => 0,
            'tgl_kunjungan' => '2025-06-10',
        ], $this->wilayah, $extra);
    }

    private function anakTersimpan(?int $sasaran): Anak
    {
        $this->n++;

        return Anak::create(array_merge([
            'no_kk' => '6474010101010002', 'nik' => '64740101012399' . str_pad((string) $this->n, 2, '0', STR_PAD_LEFT),
            'nama' => 'Anak Lama ' . $this->n, 'nik_ortu' => '6474010101900002', 'nama_ibu' => 'Ibu', 'nama_ayah' => 'Ayah',
            'jk' => 2, 'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2024-05-05', 'golda' => 'A', 'anak' => 2, 'no' => '2',
            'status' => 1, 'sumber' => 'manual', 'sasaran_balita_kesmas' => $sasaran,
        ], $this->wilayah));
    }

    /** Payload form Edit Anak tanpa ganti lokasi (id_kec tidak dikirim → cabang pertama updateAnak). */
    private function payloadEdit(Anak $anak, array $extra = []): array
    {
        return array_merge([
            'no_kk' => $anak->no_kk, 'nik' => $anak->nik, 'nama' => $anak->nama, 'nik_ortu' => $anak->nik_ortu,
            'nama_ibu' => 'Ibu', 'nama_ayah' => 'Ayah', 'jk' => 2, 'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2024-05-05',
            'golda' => 'A', 'anak' => 2, 'no' => '2', 'status' => 1,
            'posisi' => 'L', 'tb' => 70, 'bb' => 8, 'lla' => 13, 'lk' => 44, 'asi' => 1, 'vit_a' => 1,
            'pitting_edema' => 0, 'kelas_ibu_balita' => 0, 'mbg' => 0, 'tgl_kunjungan' => '2025-05-05',
            'obat_cacing' => 0, 'ddtka' => '',
        ], $extra);
    }

    public function test_form_tambah_checkbox_tercentang_default_di_luar_kartu_collapse(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.createAnak'))->assertOk()->getContent();

        $this->assertStringContainsString('<input type="hidden" name="sasaran_balita_kesmas" value="0">', $html);
        $this->assertMatchesRegularExpression('/id="sasaran_balita_kesmas" name="sasaran_balita_kesmas" value="1" aria-describedby="sasaran_balita_kesmas_bantuan"\s*checked>/', $html);
        $this->assertStringContainsString('<label class="form-check-label" for="sasaran_balita_kesmas">Sasaran Balita Kesmas</label>', $html);
        $this->assertLessThan(strpos($html, 'id="kartuKesmas"'), strpos($html, 'name="sasaran_balita_kesmas"'),
            'Checkbox harus di bagian identitas, bukan di dalam kartu collapse');
    }

    public function test_tambah_dicentang_tersimpan_1_dan_tercatat(): void
    {
        $this->actingAs($this->admin)->post(route('admin.storeAnak'), $this->payloadTambah(['sasaran_balita_kesmas' => '1']))
            ->assertRedirect(route('admin.anak'))->assertSessionDoesntHaveErrors();

        $anak = Anak::where('nik', '6474010101230001')->firstOrFail();
        $this->assertSame(1, (int) $anak->sasaran_balita_kesmas);
        $log = SasaranKesmasLog::where('id_anak', $anak->id)->sole();
        $this->assertNull($log->nilai_lama);
        $this->assertSame(1, (int) $log->nilai_baru);
        $this->assertSame('form_tambah', $log->sumber);
        $this->assertSame($this->admin->id, (int) $log->id_user);
    }

    public function test_tambah_centang_dilepas_tersimpan_0_bukan_null(): void
    {
        $this->actingAs($this->admin)->post(route('admin.storeAnak'), $this->payloadTambah(['sasaran_balita_kesmas' => '0']))
            ->assertRedirect(route('admin.anak'));

        $nilai = Anak::where('nik', '6474010101230001')->value('sasaran_balita_kesmas');
        $this->assertNotNull($nilai, 'Melepas centang default di Tambah Anak adalah keputusan');
        $this->assertSame(0, (int) $nilai);
    }

    public function test_tambah_tanpa_field_tetap_null_tanpa_log(): void
    {
        $this->actingAs($this->admin)->post(route('admin.storeAnak'), $this->payloadTambah())->assertRedirect(route('admin.anak'));

        $this->assertNull(Anak::where('nik', '6474010101230001')->firstOrFail()->sasaran_balita_kesmas);
        $this->assertSame(0, SasaranKesmasLog::count());
    }

    public function test_edit_null_tak_dicentang_tetap_null_tanpa_log(): void
    {
        // Review Focus #4: petugas hanya membetulkan nama anak lama.
        $anak = $this->anakTersimpan(null);

        $this->actingAs($this->admin)->put(route('admin.updateAnak', $anak->hashid),
            $this->payloadEdit($anak, ['nama' => 'Nama Dibetulkan', 'sasaran_balita_kesmas' => '0']))
            ->assertRedirect(route('admin.anak'))->assertSessionDoesntHaveErrors();

        $anak->refresh();
        $this->assertSame('Nama Dibetulkan', $anak->nama);
        $this->assertNull($anak->sasaran_balita_kesmas, "'0' dari anak NULL bukan keputusan — tetap belum ditandai");
        $this->assertSame(0, SasaranKesmasLog::count());
    }

    public function test_edit_mengikuti_matriks_di_kedua_cabang_update(): void
    {
        // [tersimpan, dikirim, hasil, jumlah log]
        $kasus = [[null, '1', 1, 1], [1, '0', 0, 1], [0, '1', 1, 1], [1, '1', 1, 0], [0, '0', 0, 0]];

        foreach ([false, true] as $gantiLokasi) {
            foreach ($kasus as [$lama, $kirim, $hasil, $jumlahLog]) {
                $anak = $this->anakTersimpan($lama);
                $payload = $this->payloadEdit($anak, ['sasaran_balita_kesmas' => $kirim]);
                if ($gantiLokasi) {
                    $payload = array_merge($payload, $this->wilayah); // id_kec terisi → cabang kedua updateAnak
                }

                $this->actingAs($this->admin)->put(route('admin.updateAnak', $anak->hashid), $payload)
                    ->assertRedirect(route('admin.anak'))->assertSessionDoesntHaveErrors();

                $label = sprintf('tersimpan=%s dikirim=%s cabang=%s', var_export($lama, true), $kirim, $gantiLokasi ? 'ganti-lokasi' : 'tetap');
                $this->assertSame($hasil, (int) $anak->fresh()->sasaran_balita_kesmas, $label);
                $this->assertSame($jumlahLog, SasaranKesmasLog::where('id_anak', $anak->id)->count(), $label);
                if ($jumlahLog === 1) {
                    $log = SasaranKesmasLog::where('id_anak', $anak->id)->first();
                    $this->assertSame('form_edit', $log->sumber, $label);
                    $this->assertSame($lama, $log->nilai_lama === null ? null : (int) $log->nilai_lama, $label);
                    $this->assertSame($this->admin->id, (int) $log->id_user, $label);
                }
            }
        }
    }

    public function test_edit_tanpa_field_tidak_menyentuh_tanda(): void
    {
        $anak = $this->anakTersimpan(1);

        $this->actingAs($this->admin)->put(route('admin.updateAnak', $anak->hashid), $this->payloadEdit($anak))
            ->assertRedirect(route('admin.anak'));

        $this->assertSame(1, (int) $anak->fresh()->sasaran_balita_kesmas);
        $this->assertSame(0, SasaranKesmasLog::count());
    }

    public function test_nilai_bukan_boolean_ditolak(): void
    {
        $this->actingAs($this->admin)->from(route('admin.createAnak'))
            ->post(route('admin.storeAnak'), $this->payloadTambah(['sasaran_balita_kesmas' => 'ya']))
            ->assertRedirect(route('admin.createAnak'))->assertSessionHasErrors('sasaran_balita_kesmas');

        $anak = $this->anakTersimpan(null);
        $this->from(route('admin.editAnak', $anak->hashid))
            ->put(route('admin.updateAnak', $anak->hashid), $this->payloadEdit($anak, ['sasaran_balita_kesmas' => '2']))
            ->assertSessionHasErrors('sasaran_balita_kesmas');
    }

    public function test_form_edit_null_tak_tercentang_dengan_keterangan_dan_1_tercentang(): void
    {
        $belum = $this->anakTersimpan(null);
        $html = $this->actingAs($this->admin)->get(route('admin.editAnak', $belum->hashid))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/value="1" aria-describedby="sasaran_balita_kesmas_bantuan"\s*>/', $html);
        $this->assertStringContainsString('Status: belum pernah ditandai (tidak dihitung).', $html);

        $sudah = $this->anakTersimpan(1);
        $html = $this->get(route('admin.editAnak', $sudah->hashid))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/value="1" aria-describedby="sasaran_balita_kesmas_bantuan"\s*checked>/', $html);
        $this->assertStringNotContainsString('belum pernah ditandai', $html);
    }
}
```

- [ ] **Step 2: Jalankan dan pastikan gagal**

Run: `php artisan test --filter=FormSasaranKesmasTest`
Expected: FAIL — checkbox tidak ada di HTML, nilai tetap NULL, dan `sasaran_balita_kesmas` tidak divalidasi.

- [ ] **Step 3a: Rule & validasi**

`app/Http/Requests/Admin/Anak/KesmasRules.php` — tambahkan method setelah `anak()` (sebelum docblock `kunjungan()`):
```php
    /**
     * Tanda Sasaran Balita Kesmas (spec 2026-10-02 §5.2). SENGAJA di luar anak(): kunci anak()
     * dibaca kolomKesmas() sebagai daftar kolom, sedangkan tanda ini punya aturan simpan sendiri
     * (anak NULL + tak dicentang di form Edit tetap NULL) — lihat AnakRepository::sasaranEdit().
     */
    public static function sasaran(): array
    {
        return ['sasaran_balita_kesmas' => 'nullable|boolean'];
    }
```

`app/Http/Requests/Admin/Anak/storeAnakRequest.php` — ganti:
```php
        ], KesmasRules::anak()); // field Kesmas & riwayat lahir (spec 2026-09-15 §3.3), semua opsional
```
dengan:
```php
        ], KesmasRules::anak(), KesmasRules::sasaran()); // field Kesmas & riwayat lahir (spec 2026-09-15 §3.3) + tanda sasaran (spec 2026-10-02 §5.2), semua opsional
```

`app/Http/Controllers/AdminController.php` (`updateAnak`) — ganti:
```php
        $request->validate(KesmasRules::anak($anak->penolong_lahir));
```
dengan:
```php
        $request->validate(array_merge(KesmasRules::anak($anak->penolong_lahir), KesmasRules::sasaran()));
```

- [ ] **Step 3b: Repository — simpan tanda + log dalam transaksi**

`app/Repositories/Admin/Anak/AnakRepository.php`:

1. Tambah import setelah `use App\Models\DataAnak;`:
```php
use App\Models\SasaranKesmasLog;
```

2. Ganti:
```php
    public function storeAnak($request)
    {
        $lahir = strtotime($request->tgl_lahir);
```
dengan:
```php
    public function storeAnak($request)
    {
        // Anak, kunjungan pertama, dan log tanda sasaran ditulis sebagai satu kesatuan
        // (spec 2026-10-02 §5.2): log yang gagal tidak boleh meninggalkan anak tanpa jejak.
        return DB::transaction(fn () => $this->simpanAnakBaru($request));
    }

    private function simpanAnakBaru($request)
    {
        $lahir = strtotime($request->tgl_lahir);
```

3. Ganti:
```php
        $anak_baru = Anak::create(array_merge([
```
dengan:
```php
        $sasaran = $this->sasaranTambah($request);

        $anak_baru = Anak::create(array_merge([
```

4. Ganti (blok penutup `Anak::create` di method yang sama — satu-satunya yang diawali `'sumber' => 'manual',`):
```php
            'sumber' => 'manual',
        ], $this->kesmasAnakAttributes($request)));
```
dengan:
```php
            'sumber' => 'manual',
        ], $this->kesmasAnakAttributes($request), ['sasaran_balita_kesmas' => $sasaran]));
```

5. Ganti:
```php
            'sumber' => 'manual',
        ]);
    }

    public function updateAnak($request, $id)
    {
        $lahir = strtotime($request->tgl_lahir);
```
dengan:
```php
            'sumber' => 'manual',
        ]);

        $this->catatSasaran($anak_baru->id, null, $sasaran, 'form_tambah');

        return $anak_baru;
    }

    public function updateAnak($request, $id)
    {
        DB::transaction(fn () => $this->ubahAnak($request, $id));
    }

    private function ubahAnak($request, $id)
    {
        $lahir = strtotime($request->tgl_lahir);
```

6. Ganti:
```php
        $anak = Anak::find($id);
        // Anak hasil import bisa belum punya baris DataAnak; firstOrNew agar
```
dengan:
```php
        $anak = Anak::find($id);
        $sasaranLama = $anak->sasaran_balita_kesmas === null ? null : (int) $anak->sasaran_balita_kesmas;
        $sasaran = $this->sasaranEdit($request, $sasaranLama);
        // Anak hasil import bisa belum punya baris DataAnak; firstOrNew agar
```

7. Ganti **kedua** kemunculan (cabang `id_kec == null` dan cabang ganti lokasi — teksnya identik; pakai replace-all):
```php
                'catatan' => $request->catatan ?? '',
            ], $this->kesmasAnakAttributes($request)));
```
dengan:
```php
                'catatan' => $request->catatan ?? '',
            ], $this->kesmasAnakAttributes($request), $sasaran));
```

8. Ganti (akhir `ubahAnak`, tepat sebelum `destroyAnak`):
```php
                'id_user' => Auth::user()->id,
            ])->save();
        }
    }

    public function destroyAnak($id)
```
dengan:
```php
                'id_user' => Auth::user()->id,
            ])->save();
        }

        $this->catatSasaran(
            $anak->id,
            $sasaranLama,
            array_key_exists('sasaran_balita_kesmas', $sasaran) ? $sasaran['sasaran_balita_kesmas'] : $sasaranLama,
            'form_edit'
        );
    }

    public function destroyAnak($id)
```

9. Tambahkan blok helper di akhir kelas (sebelum `}` penutup kelas, setelah `kolomKesmas()`):
```php

    // ==================== SASARAN BALITA KESMAS (spec 2026-10-02 §5.2) ====================

    /** Tambah Anak: dikirim → 0/1 apa adanya (melepas centang default = keputusan); tak dikirim → NULL. */
    private function sasaranTambah($request): ?int
    {
        return $request->has('sasaran_balita_kesmas') ? (int) $request->boolean('sasaran_balita_kesmas') : null;
    }

    /**
     * Edit Anak: kolom yang perlu ditulis, atau [] bila tidak disentuh.
     * Anak NULL + '0' TETAP NULL — form Edit merender NULL sebagai tak tercentang, jadi '0' di situ
     * bukan keputusan petugas. Menulis 0 membuat anak lama diam-diam "dilepas" setiap kali namanya
     * dibetulkan, dan perintah kesmas:tandai-sasaran (hanya NULL → 1) tak lagi menjangkaunya.
     */
    private function sasaranEdit($request, ?int $lama): array
    {
        if (!$request->has('sasaran_balita_kesmas')) {
            return [];
        }
        $baru = (int) $request->boolean('sasaran_balita_kesmas');
        if ($lama === null && $baru === 0) {
            return [];
        }

        return ['sasaran_balita_kesmas' => $baru];
    }

    private function catatSasaran(int $idAnak, ?int $lama, ?int $baru, string $sumber): void
    {
        if ($lama === $baru) {
            return;
        }
        SasaranKesmasLog::create([
            'id_anak' => $idAnak, 'nilai_lama' => $lama, 'nilai_baru' => $baru,
            'sumber' => $sumber, 'id_user' => Auth::id(),
        ]);
    }
```

- [ ] **Step 3c: View — checkbox di bagian identitas**

`resources/views/admin/anak/create.blade.php` — ganti:
```blade
                <label for="no_hp">No HP</label>
                <input type="number" name="no" id="no_hp" class="form-control">
            </div>
        </div>
```
dengan:
```blade
                <label for="no_hp">No HP</label>
                <input type="number" name="no" id="no_hp" class="form-control">
            </div>
        </div>
        {{-- Tanda Sasaran Balita Kesmas (spec 2026-10-02 §5.2): opt-in, default tercentang di Tambah Anak --}}
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <span class="d-block mb-1">Sasaran Kesmas</span>
                <div class="form-check">
                    <input type="hidden" name="sasaran_balita_kesmas" value="0">
                    <input class="form-check-input" type="checkbox" id="sasaran_balita_kesmas" name="sasaran_balita_kesmas" value="1" aria-describedby="sasaran_balita_kesmas_bantuan" @checked(old('sasaran_balita_kesmas', '1') === '1')>
                    <label class="form-check-label" for="sasaran_balita_kesmas">Sasaran Balita Kesmas</label>
                </div>
                <small id="sasaran_balita_kesmas_bantuan" class="form-text text-muted">Centang bila anak dihitung sebagai sasaran Dasbor Kesmas. Tidak memengaruhi dasbor imunisasi dan operasi timbang.</small>
            </div>
        </div>
```

`resources/views/admin/anak/edit.blade.php` — ganti:
```blade
                <label for="no_hp">No HP</label>
                <input type="number" name="no" id="no_hp" value="{{$anak->no}}" class="form-control">
            </div>
        </div>
```
dengan:
```blade
                <label for="no_hp">No HP</label>
                <input type="number" name="no" id="no_hp" value="{{$anak->no}}" class="form-control">
            </div>
        </div>
        {{-- Tanda Sasaran Balita Kesmas (spec 2026-10-02 §5.2). NULL dirender tak tercentang; menyimpan
             tanpa mencentang membiarkannya NULL (AnakRepository::sasaranEdit). --}}
        @php $sasaranTersimpan = $anak->sasaran_balita_kesmas === null ? null : (int) $anak->sasaran_balita_kesmas; @endphp
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <span class="d-block mb-1">Sasaran Kesmas</span>
                <div class="form-check">
                    <input type="hidden" name="sasaran_balita_kesmas" value="0">
                    <input class="form-check-input" type="checkbox" id="sasaran_balita_kesmas" name="sasaran_balita_kesmas" value="1" aria-describedby="sasaran_balita_kesmas_bantuan" @checked((string) old('sasaran_balita_kesmas', $sasaranTersimpan === 1 ? '1' : '0') === '1')>
                    <label class="form-check-label" for="sasaran_balita_kesmas">Sasaran Balita Kesmas</label>
                </div>
                <small id="sasaran_balita_kesmas_bantuan" class="form-text text-muted">Centang bila anak dihitung sebagai sasaran Dasbor Kesmas. Tidak memengaruhi dasbor imunisasi dan operasi timbang.
                    @if ($sasaranTersimpan === null)
                    <strong>Status: belum pernah ditandai (tidak dihitung).</strong>
                    @endif
                </small>
            </div>
        </div>
```

- [ ] **Step 4: Jalankan dan pastikan lulus (termasuk regresi form lama)**

```bash
php artisan view:clear
php artisan test --filter='FormSasaranKesmasTest|FormAnakKesmasTest|KesmasSwarmDataExportTest|FormKesmasBladeTest'
```
Expected: PASS semua. (`FormAnakKesmasTest` & `KesmasSwarmDataExportTest` tidak mengirim field tanda → membuktikan form/klien lama tak terpengaruh.)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Requests/Admin/Anak/KesmasRules.php app/Http/Requests/Admin/Anak/storeAnakRequest.php app/Http/Controllers/AdminController.php app/Repositories/Admin/Anak/AnakRepository.php resources/views/admin/anak/create.blade.php resources/views/admin/anak/edit.blade.php tests/Feature/Kesmas/FormSasaranKesmasTest.php
git commit -m "feat(kesmas): tanda Sasaran Balita Kesmas di Tambah/Edit Anak dengan log audit"
```

---

### Task 6: Perintah `kesmas:tandai-sasaran`

**Files:**
- Create: `app/Console/Commands/TandaiSasaranKesmas.php`
- Test: `tests/Feature/Kesmas/TandaiSasaranKesmasCommandTest.php`

**Interfaces:**
- Consumes: kolom `anak.sasaran_balita_kesmas`, tabel `sasaran_kesmas_log`, model `SasaranKesmasLog` (Task 3); trait `App\Support\FilterWilayahAnak::applyWilayahFilters($query, array $filters, string $alias)` (sudah ada, **jangan diubah**).
- Produces: perintah `php artisan kesmas:tandai-sasaran` dengan opsi `--kecamatan= --kelurahan= --rt= --posyandu= --puskesmas= --lahir-sejak= --lahir-sampai= --semua --termasuk-pindah --termasuk-tidak-aktif --jalankan --alasan= --batalkan=`. Dipakai Task 11 (DB dev) dan rilis prod.

- [ ] **Step 1: Tulis tes yang gagal**

Create `tests/Feature/Kesmas/TandaiSasaranKesmasCommandTest.php`:
```php
<?php

namespace Tests\Feature\Kesmas;

use App\Models\Anak;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Puskesmas;
use App\Models\SasaranKesmasLog;
use App\Support\WilkerPuskesmas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Penandaan massal Sasaran Balita Kesmas (spec 2026-10-02 §5.3): default dry-run, hanya NULL → 1,
 * nilai 0 tak pernah ditimpa, tanpa updated_at & tanpa event model.
 */
class TandaiSasaranKesmasCommandTest extends TestCase
{
    use RefreshDatabase;

    private Kelurahan $tanjungLaut; // catchment Puskesmas Bontang Selatan 1 (WilkerPuskesmas)
    private Kelurahan $berbas;      // catchment Bontang Selatan 2
    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();
        WilkerPuskesmas::flushCache();
        $kec = Kecamatan::create(['name' => 'Bontang Selatan']);
        $this->tanjungLaut = Kelurahan::create(['name' => 'Tanjung Laut', 'id_kecamatan' => $kec->id]);
        $this->berbas = Kelurahan::create(['name' => 'Berbas Tengah', 'id_kecamatan' => $kec->id]);
    }

    private function anak(Kelurahan $kel, array $extra = []): Anak
    {
        $this->n++;

        return Anak::create(array_merge([
            'nama' => 'Anak Tanda ' . $this->n, 'nik' => str_pad((string) $this->n, 16, '8', STR_PAD_LEFT), 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2024-03-15', 'status' => 1, 'no' => '1', 'sumber' => 'manual',
            'id_kec' => $kel->id_kecamatan, 'id_kel' => $kel->id,
        ], $extra));
    }

    private function nilai(Anak $a): ?int
    {
        $v = DB::table('anak')->where('id', $a->id)->value('sasaran_balita_kesmas');

        return $v === null ? null : (int) $v;
    }

    public function test_tanpa_filter_wilayah_dan_tanpa_semua_ditolak(): void
    {
        $a = $this->anak($this->tanjungLaut);

        $this->artisan('kesmas:tandai-sasaran', ['--jalankan' => true, '--alasan' => 'uji'])
            ->expectsOutputToContain('--semua')
            ->assertFailed();

        $this->assertNull($this->nilai($a));
        $this->assertSame(0, SasaranKesmasLog::count());
    }

    public function test_dry_run_hanya_merekap_tanpa_menulis(): void
    {
        $a = $this->anak($this->tanjungLaut);
        $this->anak($this->tanjungLaut, ['sasaran_balita_kesmas' => 0]);

        $this->artisan('kesmas:tandai-sasaran', ['--kelurahan' => $this->tanjungLaut->id])
            ->expectsOutputToContain('DRY-RUN')
            ->assertSuccessful();

        $this->assertNull($this->nilai($a));
        $this->assertSame(0, SasaranKesmasLog::count());
    }

    public function test_jalankan_tanpa_alasan_ditolak(): void
    {
        $a = $this->anak($this->tanjungLaut);

        $this->artisan('kesmas:tandai-sasaran', ['--kelurahan' => $this->tanjungLaut->id, '--jalankan' => true])
            ->expectsOutputToContain('--alasan')
            ->assertFailed();

        $this->assertNull($this->nilai($a));
    }

    public function test_menandai_hanya_null_di_wilayah_dan_melewati_dilepas_pindah_meninggal_tidak_aktif(): void
    {
        $baru1 = $this->anak($this->tanjungLaut);
        $baru2 = $this->anak($this->tanjungLaut);
        $berdomisili = $this->anak($this->tanjungLaut, ['verif_rt_status' => 'berdomisili']);
        $dilepas = $this->anak($this->tanjungLaut, ['sasaran_balita_kesmas' => 0]);
        $sudah = $this->anak($this->tanjungLaut, ['sasaran_balita_kesmas' => 1]);
        $pindah = $this->anak($this->tanjungLaut, ['verif_rt_status' => 'pindah']);
        $meninggal = $this->anak($this->tanjungLaut, ['verif_rt_status' => 'meninggal']);
        $tidakAktif = $this->anak($this->tanjungLaut, ['status' => 0]);
        $lain = $this->anak($this->berbas);

        $this->artisan('kesmas:tandai-sasaran', ['--kelurahan' => $this->tanjungLaut->id, '--jalankan' => true, '--alasan' => 'Penandaan uji'])
            ->expectsOutputToContain('Kode batch')
            ->assertSuccessful();

        foreach ([$baru1, $baru2, $berdomisili] as $a) {
            $this->assertSame(1, $this->nilai($a), $a->nama);
        }
        $this->assertSame(0, $this->nilai($dilepas), 'Centang yang sengaja dilepas tidak boleh ditimpa');
        $this->assertSame(1, $this->nilai($sudah));
        foreach ([$pindah, $meninggal, $tidakAktif, $lain] as $a) {
            $this->assertNull($this->nilai($a), $a->nama);
        }

        $log = SasaranKesmasLog::orderBy('id')->get();
        $this->assertCount(3, $log);
        $this->assertCount(1, $log->pluck('batch')->unique());
        foreach ($log as $l) {
            $this->assertSame('perintah', $l->sumber);
            $this->assertNull($l->nilai_lama);
            $this->assertSame(1, (int) $l->nilai_baru);
            $this->assertSame('Penandaan uji', $l->alasan);
            $this->assertNull($l->id_user);
        }
    }

    public function test_opsi_termasuk_pindah_dan_tidak_aktif(): void
    {
        $pindah = $this->anak($this->tanjungLaut, ['verif_rt_status' => 'pindah']);
        $tidakAktif = $this->anak($this->tanjungLaut, ['status' => 0]);

        $this->artisan('kesmas:tandai-sasaran', [
            '--kelurahan' => $this->tanjungLaut->id, '--termasuk-pindah' => true, '--termasuk-tidak-aktif' => true,
            '--jalankan' => true, '--alasan' => 'uji',
        ])->assertSuccessful();

        $this->assertSame(1, $this->nilai($pindah));
        $this->assertSame(1, $this->nilai($tidakAktif));
    }

    public function test_tidak_menyentuh_updated_at_dan_tidak_memicu_refresh_prioritas_gizi(): void
    {
        $a = $this->anak($this->tanjungLaut);
        // Anak::create memicu AnakObserver → baris prioritas_gizi. Bekukan stempel waktu keduanya.
        $this->assertDatabaseHas('prioritas_gizi', ['id_anak' => $a->id]);
        DB::table('anak')->where('id', $a->id)->update(['created_at' => '2026-01-01 08:00:00', 'updated_at' => '2026-01-01 08:00:00']);
        DB::table('prioritas_gizi')->where('id_anak', $a->id)->update(['refreshed_at' => '2026-01-01 08:00:00']);

        $this->artisan('kesmas:tandai-sasaran', ['--kelurahan' => $this->tanjungLaut->id, '--jalankan' => true, '--alasan' => 'uji'])
            ->assertSuccessful();

        $this->assertSame(1, $this->nilai($a));
        $this->assertSame('2026-01-01 08:00:00', DB::table('anak')->where('id', $a->id)->value('updated_at'),
            'updated_at berubah → CapilDedupService::sigiziUntouched() kehilangan anak ini');
        $this->assertSame('2026-01-01 08:00:00', DB::table('prioritas_gizi')->where('id_anak', $a->id)->value('refreshed_at'),
            'Refresh prioritas gizi (OT) terpicu → penulisan lewat Eloquent, bukan query builder');
    }

    public function test_filter_puskesmas_memakai_catchment_kelurahan(): void
    {
        $pkm = Puskesmas::create(['name' => 'Puskesmas Bontang Selatan I', 'id_kecamatan' => $this->tanjungLaut->id_kecamatan]);
        $dalam = $this->anak($this->tanjungLaut);
        $luar = $this->anak($this->berbas);

        $this->artisan('kesmas:tandai-sasaran', ['--puskesmas' => $pkm->id, '--jalankan' => true, '--alasan' => 'uji'])
            ->assertSuccessful();

        $this->assertSame(1, $this->nilai($dalam));
        $this->assertNull($this->nilai($luar));
    }

    public function test_rentang_tanggal_lahir_inklusif(): void
    {
        $tepatAwal = $this->anak($this->tanjungLaut, ['tgl_lahir' => '2020-01-01']);
        $tepatAkhir = $this->anak($this->tanjungLaut, ['tgl_lahir' => '2020-12-31']);
        $sebelum = $this->anak($this->tanjungLaut, ['tgl_lahir' => '2019-12-31']);

        $this->artisan('kesmas:tandai-sasaran', [
            '--semua' => true, '--lahir-sejak' => '2020-01-01', '--lahir-sampai' => '2020-12-31',
            '--jalankan' => true, '--alasan' => 'uji',
        ])->assertSuccessful();

        $this->assertSame(1, $this->nilai($tepatAwal));
        $this->assertSame(1, $this->nilai($tepatAkhir));
        $this->assertNull($this->nilai($sebelum));
    }

    public function test_input_tak_sah_ditolak_tanpa_menulis(): void
    {
        $a = $this->anak($this->tanjungLaut);

        $this->artisan('kesmas:tandai-sasaran', ['--kelurahan' => 999999, '--jalankan' => true, '--alasan' => 'uji'])->assertFailed();
        $this->artisan('kesmas:tandai-sasaran', ['--semua' => true, '--lahir-sejak' => '01/01/2020', '--jalankan' => true, '--alasan' => 'uji'])->assertFailed();
        $this->artisan('kesmas:tandai-sasaran', ['--semua' => true, '--jalankan' => true, '--alasan' => str_repeat('x', 256)])->assertFailed();

        $this->assertNull($this->nilai($a));
        $this->assertSame(0, SasaranKesmasLog::count());
    }

    public function test_idempoten(): void
    {
        $a = $this->anak($this->tanjungLaut);
        $argumen = ['--kelurahan' => $this->tanjungLaut->id, '--jalankan' => true, '--alasan' => 'uji'];

        $this->artisan('kesmas:tandai-sasaran', $argumen)->assertSuccessful();
        $this->artisan('kesmas:tandai-sasaran', $argumen)->expectsOutputToContain('Selesai: 0 anak ditandai')->assertSuccessful();

        $this->assertSame(1, $this->nilai($a));
        $this->assertSame(1, SasaranKesmasLog::count());
    }

    public function test_batalkan_hanya_mengembalikan_yang_belum_diubah_lewat_form(): void
    {
        $tetap = $this->anak($this->tanjungLaut);
        $diubahForm = $this->anak($this->tanjungLaut);
        $this->artisan('kesmas:tandai-sasaran', ['--kelurahan' => $this->tanjungLaut->id, '--jalankan' => true, '--alasan' => 'awal'])
            ->assertSuccessful();
        $batch = SasaranKesmasLog::value('batch');

        // Petugas melepas centang lewat Edit Anak sesudah penandaan massal.
        DB::table('anak')->where('id', $diubahForm->id)->update(['sasaran_balita_kesmas' => 0]);
        SasaranKesmasLog::create(['id_anak' => $diubahForm->id, 'nilai_lama' => 1, 'nilai_baru' => 0, 'sumber' => 'form_edit']);

        $this->artisan('kesmas:tandai-sasaran', ['--batalkan' => $batch])->expectsOutputToContain('DRY-RUN')->assertSuccessful();
        $this->assertSame(1, $this->nilai($tetap), 'dry-run tidak menulis');

        $this->artisan('kesmas:tandai-sasaran', ['--batalkan' => $batch, '--jalankan' => true, '--alasan' => 'salah kriteria'])
            ->expectsOutputToContain('dilewati')
            ->assertSuccessful();

        $this->assertNull($this->nilai($tetap));
        $this->assertSame(0, $this->nilai($diubahForm), 'Keputusan petugas sesudah batch tidak boleh dibatalkan');
        $this->assertSame(1, SasaranKesmasLog::where('sumber', 'batal')->where('batch', $batch)->count());

        // Dibatalkan lagi: tak ada yang tersisa untuk dikembalikan.
        $this->artisan('kesmas:tandai-sasaran', ['--batalkan' => $batch, '--jalankan' => true, '--alasan' => 'ulang'])->assertSuccessful();
        $this->assertSame(1, SasaranKesmasLog::where('sumber', 'batal')->count());
    }

    public function test_batch_tak_dikenal_ditolak(): void
    {
        $this->artisan('kesmas:tandai-sasaran', ['--batalkan' => '00000000-0000-0000-0000-000000000000'])->assertFailed();
        $this->artisan('kesmas:tandai-sasaran', ['--batalkan' => 'bukan-uuid'])->assertFailed();
    }
}
```

- [ ] **Step 2: Jalankan dan pastikan gagal**

Run: `php artisan test --filter=TandaiSasaranKesmasCommandTest`
Expected: FAIL — `The command "kesmas:tandai-sasaran" does not exist.`

- [ ] **Step 3: Implementasi**

Create `app/Console/Commands/TandaiSasaranKesmas.php`:
```php
<?php

namespace App\Console\Commands;

use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Posyandu;
use App\Models\Puskesmas;
use App\Models\Rt;
use App\Support\FilterWilayahAnak;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Penandaan massal "Sasaran Balita Kesmas"
 * (spec docs/superpowers/specs/2026-10-02-kesmas-permintaan-data-design.md §5.3).
 *
 * Dasbor Kesmas opt-in: hanya anak.sasaran_balita_kesmas = 1 yang dihitung. Perintah ini hanya
 * mengubah NULL → 1 (atau, lewat --batalkan, 1 → NULL untuk satu batch). Nilai 0 — centang yang
 * sengaja dilepas petugas — tidak pernah ditimpa.
 *
 * WAJIB query builder, tanpa updated_at, tanpa event model:
 *  - AnakObserver::saved memicu PrioritasGiziService::refreshAnak (modul OT) per anak;
 *  - CapilDedupService::sigiziUntouched() membaca updated_at = created_at sebagai
 *    "belum tersentuh Capil".
 */
class TandaiSasaranKesmas extends Command
{
    use FilterWilayahAnak;

    protected $signature = 'kesmas:tandai-sasaran
        {--kecamatan= : ID kecamatan}
        {--kelurahan= : ID kelurahan}
        {--rt= : ID RT}
        {--posyandu= : ID posyandu}
        {--puskesmas= : ID puskesmas (catchment kelurahan wilker)}
        {--lahir-sejak= : Tanggal lahir paling awal, Y-m-d (inklusif)}
        {--lahir-sampai= : Tanggal lahir paling akhir, Y-m-d (inklusif)}
        {--semua : Izinkan tanpa filter wilayah (seluruh kota)}
        {--termasuk-pindah : Ikut tandai anak dengan verifikasi RT pindah/meninggal}
        {--termasuk-tidak-aktif : Ikut tandai anak berstatus Tidak Aktif}
        {--jalankan : Tulis ke database (tanpa ini = dry-run)}
        {--alasan= : Wajib bersama --jalankan; disimpan di sasaran_kesmas_log}
        {--batalkan= : Kode batch yang dikembalikan ke belum ditandai (NULL)}';

    protected $description = 'Tandai anak sebagai Sasaran Balita Kesmas (dry-run default; hanya NULL → 1; tercatat di sasaran_kesmas_log).';

    /** opsi => [kunci filter FilterWilayahAnak, model untuk cek keberadaan ID]. */
    private const WILAYAH = [
        'kecamatan' => ['id_kecamatan', Kecamatan::class],
        'kelurahan' => ['id_kelurahan', Kelurahan::class],
        'rt'        => ['id_rt', Rt::class],
        'posyandu'  => ['id_posyandu', Posyandu::class],
        'puskesmas' => ['id_puskesmas', Puskesmas::class],
    ];

    private const POTONGAN = 500;

    public function handle(): int
    {
        $alasan = $this->alasanTervalidasi();
        if ($alasan === false) {
            return self::FAILURE;
        }

        if ($this->option('batalkan') !== null) {
            return $this->batalkan((string) $this->option('batalkan'), $alasan);
        }

        $filters = $this->filterTervalidasi();
        if ($filters === null) {
            return self::FAILURE;
        }

        $sejak = $this->option('lahir-sejak');
        $sampai = $this->option('lahir-sampai');
        foreach (['lahir-sejak' => $sejak, 'lahir-sampai' => $sampai] as $opsi => $tgl) {
            if ($tgl !== null && !$this->tanggalSah((string) $tgl)) {
                $this->error("--{$opsi} harus berformat Y-m-d (mis. 2020-01-31).");

                return self::FAILURE;
            }
        }

        // Pengecualian bawaan; '0' = tidak ada pengecualian (opsi --termasuk-*).
        $pindah = $this->option('termasuk-pindah') ? '0' : "COALESCE(a.verif_rt_status, '') IN ('pindah', 'meninggal')";
        $tidakAktif = $this->option('termasuk-tidak-aktif') ? '0' : 'a.status = 0';

        $rekap = $this->dasar($filters, $sejak, $sampai)
            ->selectRaw("a.id_kel, COUNT(*) as total,
                SUM(a.sasaran_balita_kesmas = 1) as bertanda,
                SUM(a.sasaran_balita_kesmas = 0) as dilepas,
                SUM(a.sasaran_balita_kesmas IS NULL AND {$pindah}) as pindah_meninggal,
                SUM(a.sasaran_balita_kesmas IS NULL AND NOT ({$pindah}) AND {$tidakAktif}) as tidak_aktif,
                SUM(a.sasaran_balita_kesmas IS NULL AND NOT ({$pindah}) AND NOT ({$tidakAktif})) as akan")
            ->groupBy('a.id_kel')->orderBy('a.id_kel')->get();
        $this->cetakRekap($rekap);
        $akan = (int) $rekap->sum('akan');

        if (!$this->option('jalankan')) {
            $this->info("DRY-RUN — tidak ada yang diubah. {$akan} anak akan ditandai. Jalankan ulang dengan --jalankan --alasan=\"…\".");

            return self::SUCCESS;
        }

        $ids = $this->dasar($filters, $sejak, $sampai)
            ->whereNull('a.sasaran_balita_kesmas')
            ->whereRaw("NOT ({$pindah})")
            ->whereRaw("NOT ({$tidakAktif})")
            ->orderBy('a.id')->pluck('a.id')->all();

        $batch = (string) Str::uuid();
        $ditandai = 0;
        foreach (array_chunk($ids, self::POTONGAN) as $potongan) {
            $ditandai += $this->tulis($potongan, null, 1, 'perintah', $batch, $alasan);
        }

        $this->info("Selesai: {$ditandai} anak ditandai." . ($ditandai > 0 ? " Kode batch: {$batch} (simpan untuk --batalkan)." : ''));

        return self::SUCCESS;
    }

    private function batalkan(string $batch, ?string $alasan): int
    {
        $diBatch = DB::table('sasaran_kesmas_log')->where('batch', $batch)->where('sumber', 'perintah');
        if (!Str::isUuid($batch) || !(clone $diBatch)->exists()) {
            $this->error("Batch {$batch} tidak ditemukan.");

            return self::FAILURE;
        }

        $semua = (clone $diBatch)->count();
        // Hanya anak yang log TERAKHIR-nya milik batch ini dan nilainya masih 1: perubahan
        // sesudah batch (form atau batch lain) adalah keputusan yang tidak boleh dibatalkan.
        $terakhir = DB::table('sasaran_kesmas_log')
            ->whereIn('id_anak', (clone $diBatch)->select('id_anak'))
            ->selectRaw('id_anak, MAX(id) as id_terakhir')
            ->groupBy('id_anak');
        $ids = DB::table('sasaran_kesmas_log as l')
            ->joinSub($terakhir, 't', 't.id_terakhir', '=', 'l.id')
            ->join('anak as a', 'a.id', '=', 'l.id_anak')
            ->where('l.batch', $batch)->where('l.sumber', 'perintah')
            ->where('a.sasaran_balita_kesmas', 1)
            ->orderBy('l.id_anak')->pluck('l.id_anak')->all();
        $dilewati = $semua - count($ids);

        $this->line("Batch {$batch}: {$semua} anak ditandai; " . count($ids) . " dapat dikembalikan ke belum ditandai, {$dilewati} sudah berubah sejak itu (dilewati).");
        if (!$this->option('jalankan')) {
            $this->info('DRY-RUN — tidak ada yang diubah. Jalankan ulang dengan --jalankan --alasan="…".');

            return self::SUCCESS;
        }

        $dikembalikan = 0;
        foreach (array_chunk($ids, self::POTONGAN) as $potongan) {
            $dikembalikan += $this->tulis($potongan, 1, null, 'batal', $batch, $alasan);
        }
        $this->info("Selesai: {$dikembalikan} anak dikembalikan ke belum ditandai; {$dilewati} dilewati.");

        return self::SUCCESS;
    }

    /**
     * Ubah satu potongan id dari $dari ke $ke dalam satu transaksi. Baris dipilih ULANG dengan kunci
     * (lockForUpdate) supaya perubahan lewat form di sela proses tidak tertimpa.
     *
     * @param  list<int>  $potongan
     */
    private function tulis(array $potongan, ?int $dari, ?int $ke, string $sumber, string $batch, string $alasan): int
    {
        return DB::transaction(function () use ($potongan, $dari, $ke, $sumber, $batch, $alasan) {
            $q = DB::table('anak')->whereIn('id', $potongan);
            $dari === null ? $q->whereNull('sasaran_balita_kesmas') : $q->where('sasaran_balita_kesmas', $dari);
            $kunci = $q->lockForUpdate()->pluck('id')->all();
            if ($kunci === []) {
                return 0;
            }

            // Query builder: TANPA updated_at & TANPA event model (lihat docblock kelas).
            DB::table('anak')->whereIn('id', $kunci)->update(['sasaran_balita_kesmas' => $ke]);
            $waktu = now();
            DB::table('sasaran_kesmas_log')->insert(array_map(fn ($id) => [
                'id_anak' => (int) $id, 'nilai_lama' => $dari, 'nilai_baru' => $ke, 'sumber' => $sumber,
                'batch' => $batch, 'alasan' => $alasan, 'id_user' => null, 'created_at' => $waktu,
            ], $kunci));

            return count($kunci);
        });
    }

    private function dasar(array $filters, ?string $sejak, ?string $sampai): Builder
    {
        $q = DB::table('anak as a');
        $this->applyWilayahFilters($q, $filters, 'a');
        if ($sejak !== null) {
            $q->where('a.tgl_lahir', '>=', $sejak);
        }
        if ($sampai !== null) {
            $q->where('a.tgl_lahir', '<=', $sampai);
        }

        return $q;
    }

    /** @return array<string, int>|null  null = tidak sah (pesan sudah dicetak). */
    private function filterTervalidasi(): ?array
    {
        $filters = [];
        foreach (self::WILAYAH as $opsi => [$kunci, $model]) {
            $nilai = $this->option($opsi);
            if ($nilai === null) {
                continue;
            }
            $id = filter_var($nilai, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false || !$model::whereKey($id)->exists()) {
                $this->error("--{$opsi}={$nilai} tidak ditemukan.");

                return null;
            }
            $filters[$kunci] = $id;
        }
        if ($filters === [] && !$this->option('semua')) {
            $this->error('Sebutkan minimal satu filter wilayah (--kecamatan/--kelurahan/--rt/--posyandu/--puskesmas), atau --semua untuk seluruh kota.');

            return null;
        }

        return $filters;
    }

    /** @return string|null|false  false = tidak sah (pesan sudah dicetak). */
    private function alasanTervalidasi(): string|null|false
    {
        $alasan = $this->option('alasan');
        $alasan = $alasan === null ? null : trim((string) $alasan);
        if ($this->option('jalankan') && ($alasan === null || $alasan === '')) {
            $this->error('--jalankan wajib disertai --alasan="…" (dicatat di sasaran_kesmas_log).');

            return false;
        }
        if ($alasan !== null && mb_strlen($alasan) > 255) {
            $this->error('--alasan maksimal 255 karakter.');

            return false;
        }

        return $alasan;
    }

    private function tanggalSah(string $tgl): bool
    {
        return (bool) preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $tgl, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    private function cetakRekap(Collection $rekap): void
    {
        $nama = Kelurahan::whereIn('id', $rekap->pluck('id_kel')->filter())->pluck('name', 'id');
        $this->table(
            ['Kelurahan', 'Total', 'Akan ditandai', 'Sudah bertanda', 'Dilepas (0)', 'Pindah/meninggal', 'Tidak Aktif'],
            $rekap->map(fn ($r) => [
                $r->id_kel ? ($nama[$r->id_kel] ?? "#{$r->id_kel}") : '(tanpa kelurahan)',
                (int) $r->total, (int) $r->akan, (int) $r->bertanda, (int) $r->dilepas,
                (int) $r->pindah_meninggal, (int) $r->tidak_aktif,
            ])->all()
        );
        $this->line(sprintf(
            'Jumlah: %d anak · akan ditandai %d · sudah bertanda %d · dilepas %d (selalu dilewati) · pindah/meninggal %d · Tidak Aktif %d',
            $rekap->sum('total'), $rekap->sum('akan'), $rekap->sum('bertanda'), $rekap->sum('dilepas'),
            $rekap->sum('pindah_meninggal'), $rekap->sum('tidak_aktif')
        ));
    }
}
```

- [ ] **Step 4: Jalankan dan pastikan lulus**

Run: `php artisan test --filter=TandaiSasaranKesmasCommandTest`
Expected: PASS — 12 tes.

Cek bantuan perintah terbaca: `php artisan kesmas:tandai-sasaran --help` → menampilkan semua opsi.

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/TandaiSasaranKesmas.php tests/Feature/Kesmas/TandaiSasaranKesmasCommandTest.php
git commit -m "feat(kesmas): perintah kesmas:tandai-sasaran (dry-run, hanya NULL→1, log & batalkan, tanpa updated_at)"
```

---

### Task 7: Satuan BB gram < 2 bulan di form pengukuran

**Files:**
- Create: `app/Http/Requests/Admin/Anak/AturanBeratBadan.php`
- Create: `public/js/satuan-bb.js`
- Modify: `app/Http/Controllers/AdminController.php` (import ±baris 14; `storeDataAnak` ±491–534; `updateDataAnak` ±571–585)
- Modify: `resources/views/admin/anak/data-anak.blade.php` (input `tgl_kunjungan` ±29–32; blok BB ±57–63; `custom_scripts` ±241–243)
- Modify: `resources/views/admin/anak/edit.blade.php` (label BB identitas ±184; awal daftar kunjungan ±308–309; BB per kunjungan ±326–328; akhir `custom_scripts`)
- Modify: `resources/views/admin/anak/create.blade.php` (label BB identitas ±182)
- Modify (fixture): `tests/Feature/Kesmas/FormPengukuranKesmasTest.php`, `tests/Feature/Kesmas/KesmasSwarmDataExportTest.php`
- Test: `tests/Feature/Kesmas/SatuanBeratBadanFormTest.php`

**Interfaces:**
- Consumes: `App\Support\SatuanBeratBadan` (Task 2).
- Produces: `App\Http\Requests\Admin\Anak\AturanBeratBadan::rentang(?string $tglLahir, mixed $tglKunjungan, ?float $kgTersimpan = null): Closure`, `::pesan(string $satuan): string`, `::untukDisimpan(?string $tglLahir, string $tglKunjungan, mixed $input, mixed $bbTersimpan = null): mixed`; atribut HTML `data-batas-gram`, `data-satuan`, `data-satuan-label="<id input>"`, `data-satuan-info="<id input>"`; id input BB per kunjungan `k{id}_bb`.

- [ ] **Step 1: Sesuaikan fixture lama yang kini melanggar aturan gram**

Dua berkas tes lama mengirim `bb` 4,5 (kg) untuk kunjungan **10 Feb 2025 pada anak lahir 10 Jan 2025 = umur 1 bulan**. Setelah aturan ini, nilai itu wajib gram. Ini penyesuaian data uji terhadap aturan baru, bukan pelonggaran.

`tests/Feature/Kesmas/FormPengukuranKesmasTest.php` — di `payloadKunjungan()`, ganti:
```php
            'tb' => 55, 'bb' => 4.5, 'lla' => 11, 'lk' => 38, 'asi' => 1, 'vit_a' => 0, 'obat_cacing' => 0, 'ddtka' => '',
```
dengan:
```php
            // Kunjungan 10 Feb 2025, lahir 10 Jan 2025 → umur < 2 bulan → BB dalam gram (spec 2026-10-02 §5.5).
            'tb' => 55, 'bb' => '4500', 'lla' => 11, 'lk' => 38, 'asi' => 1, 'vit_a' => 0, 'obat_cacing' => 0, 'ddtka' => '',
```
dan di `test_update_pengukuran_tanpa_field_kesmas_tidak_menimpa`, ganti:
```php
            'tgl_kunjungan' => '2025-02-10', 'posisi' => 'L', 'tb' => 56, 'bb' => 4.6, 'lla' => 11, 'lk' => 38,
```
dengan:
```php
            'tgl_kunjungan' => '2025-02-10', 'posisi' => 'L', 'tb' => 56, 'bb' => '4600', 'lla' => 11, 'lk' => 38,
```

`tests/Feature/Kesmas/KesmasSwarmDataExportTest.php` — di `test_seluruh_checkbox_dan_keterangan_kunjungan_round_trip_tanpa_menimpa_kunjungan_lain`, ganti:
```php
        $payload = array_merge($this->dasar(), ['id_anak_hash' => $anak->hashid], $this->layanan());
```
dengan:
```php
        // Kunjungan 10 Feb 2025 pada anak lahir 10 Jan 2025 → BB dalam gram (spec 2026-10-02 §5.5).
        $payload = array_merge($this->dasar(), ['id_anak_hash' => $anak->hashid, 'bb' => '4500'], $this->layanan());
```
dan di `test_invalid_kesmas_ditolak_sebelum_identitas_atau_kunjungan_diubah`, ganti:
```php
        $this->putJson(route('admin.updateDataAnak', $data->id), $this->dasar(array_merge($invalidLayanan, ['bb' => 9])))
```
dengan:
```php
        // 5000 g = perubahan BB yang SAH; tetap tak boleh tersimpan karena Kesmas tidak sah.
        $this->putJson(route('admin.updateDataAnak', $data->id), $this->dasar(array_merge($invalidLayanan, ['bb' => '5000'])))
```

- [ ] **Step 2: Tulis tes yang gagal**

Create `tests/Feature/Kesmas/SatuanBeratBadanFormTest.php`:
```php
<?php

namespace Tests\Feature\Kesmas;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BB < 2 bulan diinput dalam gram di form pengukuran (spec 2026-10-02 §5.5).
 * Anak lahir 10 Jan 2025 → batas gram 10 Mar 2025 (kunjungan sebelum itu = gram).
 * data_anak.bb SELALU kg.
 */
class SatuanBeratBadanFormTest extends TestCase
{
    use RefreshDatabase;

    private const PESAN_GRAM = 'Untuk umur di bawah 2 bulan, berat badan diisi dalam gram (300–8.000), mis. 3250.';
    private const PESAN_KG = 'Berat badan diisi dalam kg (1–150), mis. 7.5.';

    private User $admin;
    private Anak $anak;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['type' => 1]);
        $this->anak = Anak::create([
            'nama' => 'Bayi Gram', 'nik' => '6474010101250101', 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2025-01-10', 'status' => 1, 'sumber' => 'manual', 'no' => '1',
        ]);
    }

    private function payload(string $tgl, string $bb, array $extra = []): array
    {
        $checkbox = array_fill_keys(array_keys(config('kesmas.layanan')), '0');

        return array_merge([
            'id_anak_hash' => $this->anak->hashid, 'tgl_kunjungan' => $tgl, 'posisi' => 'L',
            'tb' => 50, 'bb' => $bb, 'lla' => 10, 'lk' => 35, 'asi' => 1, 'vit_a' => 0, 'obat_cacing' => 0, 'ddtka' => '',
            'tgl_penanda_ckg' => '', 'pemeriksaan_gigi' => '', 'rujukan' => '', 'mt_pangan_lokal' => '',
            'catatan_pengukuran' => '', 'pemeriksaan_lainnya' => '', 'pola_makan' => '', 'pola_asuh' => '', 'intervensi' => '',
        ], $checkbox, $extra);
    }

    private function simpan(string $tgl, string $bb)
    {
        return $this->actingAs($this->admin)->from(route('admin.dataAnak', $this->anak->hashid))
            ->post(route('admin.storeDataAnak'), $this->payload($tgl, $bb));
    }

    private function kunjungan(string $tgl, float $bb, array $extra = []): DataAnak
    {
        return DataAnak::create(array_merge([
            'id_anak' => $this->anak->id, 'tgl_kunjungan' => $tgl, 'bln' => 1, 'posisi' => 'L',
            'tb' => 50, 'bb' => $bb, 'lla' => 10, 'lk' => 35, 'id_user' => $this->admin->id,
        ], $extra));
    }

    private function ubah(DataAnak $d, string $tgl, string $bb, array $extra = [])
    {
        $payload = $this->payload($tgl, $bb, $extra);
        unset($payload['id_anak_hash']);

        return $this->actingAs($this->admin)->from(route('admin.editAnak', $this->anak->hashid))
            ->put(route('admin.updateDataAnak', $d->id), $payload);
    }

    private function bbTersimpan(?DataAnak $d = null): float
    {
        return round((float) ($d ? $d->fresh()->bb : DataAnak::where('id_anak', $this->anak->id)->value('bb')), 3);
    }

    public function test_kunjungan_di_bawah_dua_bulan_disimpan_dari_gram_ke_kg(): void
    {
        $this->simpan('2025-02-10', '3250')->assertRedirect(route('admin.anak'))->assertSessionDoesntHaveErrors();

        $this->assertSame(3.25, $this->bbTersimpan());
    }

    public function test_kg_diketik_di_kolom_gram_ditolak_dengan_pesan_gram(): void
    {
        $this->simpan('2025-02-10', '3.25')
            ->assertRedirect(route('admin.dataAnak', $this->anak->hashid))
            ->assertSessionHasErrors(['bb' => self::PESAN_GRAM]);

        $this->assertSame(0, DataAnak::where('id_anak', $this->anak->id)->count());
    }

    public function test_dua_bulan_ke_atas_tetap_kg_dan_gram_ditolak(): void
    {
        $this->simpan('2025-06-10', '5.4')->assertSessionDoesntHaveErrors();
        $this->assertSame(5.4, $this->bbTersimpan());

        $this->simpan('2025-07-10', '5400')->assertSessionHasErrors(['bb' => self::PESAN_KG]);
        $this->assertSame(1, DataAnak::where('id_anak', $this->anak->id)->count());
    }

    public function test_sehari_sebelum_batas_gram_tepat_batas_kg(): void
    {
        $this->simpan('2025-03-09', '4100')->assertSessionDoesntHaveErrors();
        $this->simpan('2025-03-10', '4.1')->assertSessionDoesntHaveErrors();

        $this->assertSame([4.1, 4.1], DataAnak::where('id_anak', $this->anak->id)->orderBy('tgl_kunjungan')
            ->pluck('bb')->map(fn ($v) => round((float) $v, 3))->all());
    }

    public function test_hash_anak_salah_404(): void
    {
        $payload = $this->payload('2025-02-10', '3250');
        $payload['id_anak_hash'] = 'tidak-ada';

        $this->actingAs($this->admin)->post(route('admin.storeDataAnak'), $payload)->assertNotFound();
    }

    public function test_edit_placeholder_bb_nol_bisa_disimpan_tanpa_diubah(): void
    {
        // Baris placeholder ImunisasiImport: bb = tb = lla = lk = 0, sumber imunisasi.
        $d = $this->kunjungan('2025-02-10', 0, ['tb' => 0, 'lla' => 0, 'lk' => 0, 'sumber' => 'imunisasi', 'alasan_tidak_imunisasi' => 'Sakit']);

        $this->ubah($d, '2025-02-10', '0', ['tb' => 0, 'lla' => 0, 'lk' => 0])
            ->assertRedirect(route('admin.anak'))->assertSessionDoesntHaveErrors();

        $this->assertSame(0.0, $this->bbTersimpan($d));
    }

    public function test_edit_data_lama_ganjil_tanpa_diubah_tetap_tersimpan_apa_adanya(): void
    {
        // Data lama: dulu gram diketik di kolom kg → tersimpan 3250 "kg".
        $d = $this->kunjungan('2025-02-10', 3250);

        // Form edit menampilkannya sebagai 3.250.000 gram; dikirim ulang tanpa diubah.
        $this->ubah($d, '2025-02-10', '3250000')->assertSessionDoesntHaveErrors();

        $this->assertSame(3250.0, $this->bbTersimpan($d));
    }

    public function test_edit_nilai_baru_salah_satuan_ditolak_dan_yang_benar_disimpan(): void
    {
        $d = $this->kunjungan('2025-02-10', 3.25);

        $this->ubah($d, '2025-02-10', '3.3')->assertSessionHasErrors(['bb' => self::PESAN_GRAM]);
        $this->assertSame(3.25, $this->bbTersimpan($d));

        $this->ubah($d, '2025-02-10', '3300')->assertSessionDoesntHaveErrors();
        $this->assertSame(3.3, $this->bbTersimpan($d));
    }

    public function test_tanggal_digeser_melewati_batas_tanpa_js_ditolak_bukan_tersimpan_ribuan_kg(): void
    {
        // Review Focus #1: kunjungan 1 bln (3,25 kg tampil 3250 g) digeser ke 3 bln; JS tidak jalan,
        // jadi 3250 terkirim apa adanya dan server membacanya sebagai kg.
        $d = $this->kunjungan('2025-02-10', 3.25);

        $this->ubah($d, '2025-04-10', '3250')->assertSessionHasErrors(['bb' => self::PESAN_KG]);

        $this->assertSame('2025-02-10', $d->fresh()->tgl_kunjungan);
        $this->assertSame(3.25, $this->bbTersimpan($d));
    }

    public function test_edit_bb_dan_tanggal_wajib_diisi(): void
    {
        $d = $this->kunjungan('2025-06-10', 6.0);

        $this->ubah($d, '2025-06-10', '')->assertSessionHasErrors('bb');
        $this->ubah($d, '', '6')->assertSessionHasErrors('tgl_kunjungan');
    }

    public function test_form_tambah_pengukuran_membawa_batas_gram_dari_server(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.dataAnak', $this->anak->hashid))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<input type="number"[^>]*name="bb" id="bb"[^>]*data-batas-gram="2025-03-10"/', $html);
        $this->assertStringContainsString('<span data-satuan-label="bb">', $html);
        $this->assertStringContainsString('aria-live="polite" data-satuan-info="bb"', $html);
        $this->assertStringContainsString('js/satuan-bb.js', $html);
    }

    public function test_batas_gram_akhir_bulan_tidak_meluber(): void
    {
        // Review Focus #2.
        $anak = Anak::create([
            'nama' => 'Lahir 31 Des', 'nik' => '6474010101250102', 'jk' => 2, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2025-12-31', 'status' => 1, 'sumber' => 'manual', 'no' => '1',
        ]);

        $html = $this->actingAs($this->admin)->get(route('admin.dataAnak', $anak->hashid))->assertOk()->getContent();

        $this->assertStringContainsString('data-batas-gram="2026-02-28"', $html);
    }

    public function test_form_edit_per_kunjungan_menampilkan_gram_bulat_untuk_bayi_dan_kg_untuk_lainnya(): void
    {
        $bayi = $this->kunjungan('2025-02-10', 3.2);
        $besar = $this->kunjungan('2025-06-10', 6.5, ['bln' => 5]);

        $html = $this->actingAs($this->admin)->get(route('admin.editAnak', $this->anak->hashid))->assertOk()->getContent();

        $idBayi = 'k' . $bayi->id . '_bb';
        $idBesar = 'k' . $besar->id . '_bb';
        $this->assertMatchesRegularExpression('/<label for="' . $idBayi . '">Berat Badan <span data-satuan-label="' . $idBayi . '">\(gram\)<\/span>/', $html);
        $this->assertMatchesRegularExpression('/id="' . $idBayi . '"[^>]*value="3200"/', $html);
        $this->assertMatchesRegularExpression('/<label for="' . $idBesar . '">Berat Badan <span data-satuan-label="' . $idBesar . '">\(kg\)<\/span>/', $html);
        $this->assertMatchesRegularExpression('/id="' . $idBesar . '"[^>]*value="6.5"/', $html);
        // Form identitas tetap kg dan labelnya kini menyebut satuan.
        $this->assertStringContainsString('<label for="bb">Berat Badan Lahir (kg)', $html);
        $this->assertStringContainsString('js/satuan-bb.js', $html);
    }

    public function test_form_identitas_tambah_anak_tetap_kg_walau_kunjungan_di_bawah_dua_bulan(): void
    {
        $kec = Kecamatan::create(['name' => 'Bontang Utara']);
        $kel = Kelurahan::create(['name' => 'Bontang Baru', 'id_kecamatan' => $kec->id]);

        $this->actingAs($this->admin)->post(route('admin.storeAnak'), [
            'no_kk' => '6474010101010009', 'nik' => '6474010101250109', 'nama' => 'Bayi Identitas',
            'nik_ortu' => '6474010101900009', 'nama_ibu' => 'Ibu', 'nama_ayah' => 'Ayah', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2025-01-10', 'golda' => 'O', 'anak' => 1, 'no' => '1',
            'id_kec' => $kec->id, 'id_kel' => $kel->id, 'id_puskesmas' => 1, 'id_posyandu' => 1, 'id_rt' => 1,
            'tb' => 49, 'bb' => '3.2', 'lla' => 10, 'lk' => 34, 'asi' => 1, 'obat_cacing' => 0, 'tgl_kunjungan' => '2025-01-11',
        ])->assertRedirect(route('admin.anak'));

        $id = Anak::where('nik', '6474010101250109')->value('id');
        $this->assertSame(3.2, round((float) DataAnak::where('id_anak', $id)->value('bb'), 3));
    }
}
```

- [ ] **Step 3: Jalankan dan pastikan gagal**

Run: `php artisan test --filter=SatuanBeratBadanFormTest`
Expected: FAIL — `3250` tersimpan sebagai 3250 (bukan 3,25), pesan gram/kg tidak ada, atribut `data-batas-gram` tidak ada.

- [ ] **Step 4a: Helper validasi**

Create `app/Http/Requests/Admin/Anak/AturanBeratBadan.php`:
```php
<?php

namespace App\Http\Requests\Admin\Anak;

use App\Support\SatuanBeratBadan as S;
use Closure;

/**
 * Validasi & konversi berat badan di form pengukuran (spec 2026-10-02 §5.5) — Tambah Data
 * Pengukuran (AdminController::storeDataAnak) dan form per kunjungan (updateDataAnak). Form
 * identitas Tambah/Edit Anak TIDAK memakai ini: di sana BB tetap kg (keputusan pemilik produk).
 *
 * Server yang memutuskan satuan dari tanggal lahir + tanggal kunjungan yang dikirim; label di form
 * hanya cermin. Yang disimpan selalu kg (data_anak.bb dibaca z-score dan prioritas gizi OT).
 */
final class AturanBeratBadan
{
    /**
     * Rule closure rentang wajar sesuai satuan. $kgTersimpan diisi di form edit: bila nilai tidak
     * berubah, rentang TIDAK dicek — baris placeholder import imunisasi (bb = 0) dan data lama ganjil
     * tetap bisa disimpan tanpa memaksa petugas memperbaikinya lebih dulu.
     */
    public static function rentang(?string $tglLahir, mixed $tglKunjungan, ?float $kgTersimpan = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($tglLahir, $tglKunjungan, $kgTersimpan): void {
            if (!is_numeric($value)) {
                return; // rule 'numeric' yang melaporkan
            }
            $satuan = S::untuk($tglLahir, is_string($tglKunjungan) ? $tglKunjungan : null);
            $nilai = (float) $value;
            if ($kgTersimpan !== null && S::sama(S::keKg($nilai, $satuan), $kgTersimpan)) {
                return;
            }
            if (!S::dalamRentang($nilai, $satuan)) {
                $fail(self::pesan($satuan));
            }
        };
    }

    public static function pesan(string $satuan): string
    {
        [$min, $maks] = S::RENTANG[$satuan];
        $angka = fn (int|float $n): string => number_format($n, 0, ',', '.');

        return $satuan === S::GRAM
            ? sprintf('Untuk umur di bawah 2 bulan, berat badan diisi dalam gram (%s–%s), mis. 3250.', $angka($min), $angka($maks))
            : sprintf('Berat badan diisi dalam kg (%s–%s), mis. 7.5.', $angka($min), $angka($maks));
    }

    /**
     * Nilai kg yang ditulis ke data_anak.bb. Tidak berubah dari yang tersimpan → nilai tersimpan
     * dikembalikan apa adanya (tanpa pembulatan float ulang).
     */
    public static function untukDisimpan(?string $tglLahir, string $tglKunjungan, mixed $input, mixed $bbTersimpan = null): mixed
    {
        $kg = S::keKg((float) $input, S::untuk($tglLahir, $tglKunjungan));
        if ($bbTersimpan !== null && S::sama($kg, (float) $bbTersimpan)) {
            return $bbTersimpan;
        }

        return $kg;
    }
}
```

- [ ] **Step 4b: Controller**

`app/Http/Controllers/AdminController.php` — tambah import, ganti:
```php
use App\Http\Requests\Admin\Anak\KesmasRules;
```
dengan:
```php
use App\Http\Requests\Admin\Anak\AturanBeratBadan;
use App\Http\Requests\Admin\Anak\KesmasRules;
```

Di `storeDataAnak`, ganti:
```php
    public function storeDataAnak(Request $request)
    {
        $request->validate(array_merge([
            'id_anak_hash' => 'required',
            'tgl_kunjungan' => 'required|date',
            'posisi' => 'required|in:H,L',
            'tb' => 'required|numeric',
            'bb' => 'required|numeric',
```
dengan:
```php
    public function storeDataAnak(Request $request)
    {
        // Anak dicari SEBELUM validasi: satuan BB (gram < 2 bln / kg) bergantung tanggal lahirnya
        // (spec 2026-10-02 §5.5). Hash salah → 404.
        $request->validate(['id_anak_hash' => 'required']);
        $anak = Anak::findByHashIdOrFail($request->id_anak_hash);

        $request->validate(array_merge([
            'id_anak_hash' => 'required',
            'tgl_kunjungan' => 'required|date',
            'posisi' => 'required|in:H,L',
            'tb' => 'required|numeric',
            'bb' => ['required', 'numeric', AturanBeratBadan::rentang($anak->tgl_lahir, $request->input('tgl_kunjungan'))],
```
lalu ganti:
```php
        try {
            $anak = Anak::findByHashIdOrFail($request->id_anak_hash);

            // Umur (bln) dihitung otomatis dari tgl lahir → tgl kunjungan,
```
dengan:
```php
        // data_anak.bb selalu kg: gram dari form dikonversi di server, bukan di JS (spec 2026-10-02 §5.5).
        $request->merge(['bb' => AturanBeratBadan::untukDisimpan($anak->tgl_lahir, $request->tgl_kunjungan, $request->bb)]);

        try {
            // Umur (bln) dihitung otomatis dari tgl lahir → tgl kunjungan,
```

Ganti seluruh method `updateDataAnak`:
```php
    public function updateDataAnak(Request $request, $id)
    {
        // Hanya field Kesmas yang divalidasi (semua opsional); field lama tetap seperti
        // sebelumnya. Harus SEBELUM try agar ValidationException tidak tertelan catch.
        $request->validate(KesmasRules::kunjungan());

        try {
```
dengan:
```php
    public function updateDataAnak(Request $request, $id)
    {
        $dataAnak = DataAnak::findOrFail($id);
        $tglLahir = Anak::whereKey($dataAnak->id_anak)->value('tgl_lahir');
        $bbTersimpan = $dataAnak->getRawOriginal('bb');

        // Field Kesmas (opsional) + tanggal & BB (wajib, satuan mengikuti umur — spec 2026-10-02 §5.5);
        // field lama lain tetap seperti sebelumnya. Harus SEBELUM try agar ValidationException tidak
        // tertelan catch. Rentang BB hanya dicek bila nilainya berubah (placeholder bb = 0 tetap bisa disimpan).
        $request->validate(array_merge([
            'tgl_kunjungan' => 'required|date',
            'bb' => ['required', 'numeric', AturanBeratBadan::rentang(
                $tglLahir, $request->input('tgl_kunjungan'), $bbTersimpan === null ? null : (float) $bbTersimpan
            )],
        ], KesmasRules::kunjungan()));

        $request->merge(['bb' => AturanBeratBadan::untukDisimpan($tglLahir, $request->tgl_kunjungan, $request->bb, $bbTersimpan)]);

        try {
```
(Sisa method — `try { $this->anakRepository->updateDataAnak(...) ... }` — tidak berubah.)

- [ ] **Step 4c: JS satuan**

Create `public/js/satuan-bb.js`:
```js
// public/js/satuan-bb.js
// Satuan input berat badan di form pengukuran (spec 2026-10-02 §5.5).
// Server yang memutuskan satuan (AturanBeratBadan); berkas ini hanya menyelaraskan label, step,
// dan nilai dengan tanggal kunjungan. Batas datang dari server (data-batas-gram), jadi tidak ada
// aritmetika bulan di JS yang bisa berbeda dari PHP — cukup perbandingan string ISO Y-m-d.
(function () {
    'use strict';

    function hariIni() {
        var d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function satuanUntuk(tgl, batas) {
        return (tgl || hariIni()) < batas ? 'g' : 'kg';
    }

    function konversi(nilai, ke) {
        var n = Number(nilai);
        if (nilai === '' || !isFinite(n)) return nilai;
        return ke === 'g' ? String(Math.round(n * 1000)) : String(Math.round(n) / 1000);
    }

    function pasang(input) {
        var form = input.form;
        var batas = input.getAttribute('data-batas-gram');
        var tgl = form ? form.querySelector('[name="tgl_kunjungan"]') : null;
        if (!tgl || !batas) return;
        var label = form.querySelector('[data-satuan-label="' + input.id + '"]');
        var info = form.querySelector('[data-satuan-info="' + input.id + '"]');

        function terapkan(umumkan) {
            var baru = satuanUntuk(tgl.value, batas);
            var lama = input.getAttribute('data-satuan') || baru;
            if (baru !== lama && input.value !== '') {
                input.value = konversi(input.value, baru);
                if (umumkan && info) {
                    info.textContent = baru === 'g'
                        ? 'Umur di bawah 2 bulan pada tanggal ini — berat badan diubah ke gram.'
                        : 'Umur 2 bulan ke atas pada tanggal ini — berat badan diubah ke kg.';
                }
            }
            input.setAttribute('data-satuan', baru);
            input.step = baru === 'g' ? '1' : 'any';
            input.placeholder = baru === 'g' ? 'mis. 3250' : 'mis. 7.5';
            if (label) label.textContent = baru === 'g' ? '(gram)' : '(kg)';
        }

        terapkan(false);
        tgl.addEventListener('change', function () { terapkan(true); });
    }

    document.querySelectorAll('input[data-batas-gram]').forEach(pasang);
})();
```

Run: `node --check public/js/satuan-bb.js` → tanpa keluaran (sintaks sah).

- [ ] **Step 4d: View**

`resources/views/admin/anak/data-anak.blade.php` — ganti:
```blade
                <input type="date" name="tgl_kunjungan" id="tgl_kunjungan" class="form-control" required>
```
dengan:
```blade
                <input type="date" name="tgl_kunjungan" id="tgl_kunjungan" class="form-control" required value="{{ old('tgl_kunjungan') }}">
```
lalu ganti:
```blade
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="bb">Berat Badan <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="number" step="any" name="bb" id="bb" class="form-control" required>
                <small class="form-text text-muted">Gunakan titik (.) untuk angka desimal.</small>
            </div>
        </div>
```
dengan:
```blade
        @php
            // Satuan BB (spec 2026-10-02 §5.5): gram bila kunjungan sebelum batas, selain itu kg.
            // Server tetap yang memutuskan; satuan-bb.js hanya menyelaraskan label & nilai.
            $batasGram = \App\Support\SatuanBeratBadan::batasGram($anak->tgl_lahir);
            $satuanBb  = \App\Support\SatuanBeratBadan::untuk($anak->tgl_lahir, old('tgl_kunjungan') ?: now()->toDateString());
            $batasTeks = $batasGram !== '' ? \Carbon\Carbon::parse($batasGram)->format('d/m/Y') : null;
        @endphp
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="bb">Berat Badan <span data-satuan-label="bb">({{ \App\Support\SatuanBeratBadan::label($satuanBb) }})</span> <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="number" step="{{ $satuanBb === 'g' ? '1' : 'any' }}" name="bb" id="bb" class="form-control" required value="{{ old('bb') }}" data-batas-gram="{{ $batasGram }}" data-satuan="{{ $satuanBb }}" placeholder="{{ $satuanBb === 'g' ? 'mis. 3250' : 'mis. 7.5' }}" aria-describedby="bb_bantuan">
                <small id="bb_bantuan" class="form-text text-muted">
                    @if ($batasTeks)
                    Kunjungan sebelum {{ $batasTeks }} (umur di bawah 2 bulan): isi dalam <strong>gram</strong>, mis. 3250.
                    Sesudahnya dalam <strong>kg</strong>, titik untuk desimal, mis. 7.5.
                    @else
                    Isi dalam kg, titik untuk desimal, mis. 7.5.
                    @endif
                </small>
                <small class="form-text text-muted" aria-live="polite" data-satuan-info="bb"></small>
            </div>
        </div>
```
lalu ganti:
```blade
@section('custom_scripts')
<script>
(function() {
```
dengan:
```blade
@section('custom_scripts')
<script src="{{ asset('js/satuan-bb.js') }}" defer></script>
<script>
(function() {
```

`resources/views/admin/anak/edit.blade.php` — label BB form identitas, ganti:
```blade
                <label for="bb">Berat Badan Lahir <span class="text-danger" aria-hidden="true">*</span></label>
```
dengan:
```blade
                <label for="bb">Berat Badan Lahir (kg) <span class="text-danger" aria-hidden="true">*</span></label>
```
lalu ganti:
```blade
<div class="row">
    @foreach ($dataAnak as $data)
```
dengan:
```blade
<div class="row">
    @php
        // Satu batas gram per anak untuk semua form kunjungan (spec 2026-10-02 §5.5).
        $batasGramAnak = \App\Support\SatuanBeratBadan::batasGram($anak->tgl_lahir);
        $batasGramTeks = $batasGramAnak !== '' ? \Carbon\Carbon::parse($batasGramAnak)->format('d/m/Y') : '—';
    @endphp
    @foreach ($dataAnak as $data)
```
lalu ganti:
```blade
                <label>Berat Badan <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" name="bb" value="{{$data->bb}}" class="form-control" required>
                <small class="form-text text-muted">Gunakan titik (.) untuk desimal.</small>
```
dengan:
```blade
                @php
                    $satuanBb = \App\Support\SatuanBeratBadan::untuk($anak->tgl_lahir, $data->tgl_kunjungan);
                    $idBb = 'k' . $data->id . '_bb';
                @endphp
                <label for="{{ $idBb }}">Berat Badan <span data-satuan-label="{{ $idBb }}">({{ \App\Support\SatuanBeratBadan::label($satuanBb) }})</span> <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="number" step="{{ $satuanBb === 'g' ? '1' : 'any' }}" name="bb" id="{{ $idBb }}" class="form-control" required value="{{ \App\Support\SatuanBeratBadan::untukTampil((float) $data->bb, $satuanBb) }}" data-batas-gram="{{ $batasGramAnak }}" data-satuan="{{ $satuanBb }}">
                <small class="form-text text-muted">Di bawah 2 bulan (sebelum {{ $batasGramTeks }}): gram, mis. 3250. Sesudahnya kg, titik untuk desimal.</small>
                <small class="form-text text-muted" aria-live="polite" data-satuan-info="{{ $idBb }}"></small>
```
lalu ganti (akhir `custom_scripts`):
```blade
        $('#samakanAlamat').on('click', function() {
            $('#alamat_ktp').val($('#alamat').val());
        });
    });
</script>
```
dengan:
```blade
        $('#samakanAlamat').on('click', function() {
            $('#alamat_ktp').val($('#alamat').val());
        });
    });
</script>
<script src="{{ asset('js/satuan-bb.js') }}" defer></script>
```

`resources/views/admin/anak/create.blade.php` — ganti:
```blade
                <label for="bb">Berat Badan Lahir <span class="text-danger" aria-hidden="true">*</span></label>
```
dengan:
```blade
                <label for="bb">Berat Badan Lahir (kg) <span class="text-danger" aria-hidden="true">*</span></label>
```

- [ ] **Step 5: Jalankan dan pastikan lulus (termasuk tes lama yang fixture-nya disesuaikan)**

```bash
php artisan view:clear
php artisan test --filter='SatuanBeratBadanFormTest|FormPengukuranKesmasTest|KesmasSwarmDataExportTest|FormAnakKesmasTest|FormSasaranKesmasTest|DetailAnakKesmasTest'
```
Expected: PASS semua.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Requests/Admin/Anak/AturanBeratBadan.php public/js/satuan-bb.js app/Http/Controllers/AdminController.php resources/views/admin/anak/data-anak.blade.php resources/views/admin/anak/edit.blade.php resources/views/admin/anak/create.blade.php tests/Feature/Kesmas/SatuanBeratBadanFormTest.php tests/Feature/Kesmas/FormPengukuranKesmasTest.php tests/Feature/Kesmas/KesmasSwarmDataExportTest.php
git commit -m "feat(kesmas): BB < 2 bulan diinput dalam gram di form pengukuran, disimpan tetap kg"
```

---

### Task 8: Kartu "Sasaran Kesmas" di detail + kolom Export Kesmas

**Files:**
- Modify: `app/Services/KesmasPresenter.php` (method baru `sasaran()`)
- Modify: `resources/views/admin/anak/show.blade.php` (kartu baru setelah kartu Riwayat Kelahiran, sebelum `</section>` ±baris 657)
- Modify: `app/Exports/KesmasAnakSheet.php` (`headings()`, `map()`, `bindValue()`)
- Test: `tests/Unit/KesmasPresenterTest.php`, `tests/Feature/Kesmas/DetailAnakKesmasTest.php`, `tests/Feature/Kesmas/ExportKesmasTest.php`

**Interfaces:**
- Consumes: `TahunSasaranKesmas` (Task 1), kolom `tgl_hbig` & `sasaran_balita_kesmas` (Task 3).
- Produces: `KesmasPresenter::sasaran($nilai): string` (`'Ya'` / `'Tidak (dilepas)'` / `'Belum ditandai'`); kartu ber-`id="sasaran-info-title"` dengan baris `<tr data-tahap="{kode}">`; kolom export AG `Tgl HBIG`, AH `Sasaran Balita Kesmas`, AI–AN `Thn Sasaran …` (numerik).

- [ ] **Step 1: Tulis tes yang gagal**

`tests/Unit/KesmasPresenterTest.php` — tambahkan method:
```php
    public function test_sasaran_membedakan_belum_ditandai_dari_dilepas(): void
    {
        $this->assertSame('Belum ditandai', KesmasPresenter::sasaran(null));
        $this->assertSame('Belum ditandai', KesmasPresenter::sasaran(''));
        $this->assertSame('Ya', KesmasPresenter::sasaran(1));
        $this->assertSame('Ya', KesmasPresenter::sasaran('1'));
        $this->assertSame('Tidak (dilepas)', KesmasPresenter::sasaran(0));
        $this->assertSame('Tidak (dilepas)', KesmasPresenter::sasaran('0'));
    }
```

`tests/Feature/Kesmas/DetailAnakKesmasTest.php` — tambahkan method:
```php
    public function test_kartu_sasaran_kesmas_status_dan_enam_tahun(): void
    {
        $kartu = $this->kartu($this->render($this->anak(['sasaran_balita_kesmas' => 1])), 'sasaran-info-title');

        $this->assertMatchesRegularExpression('/Sasaran Balita Kesmas<\/dt>\s*<dd[^>]*>\s*Ya\s*</', $kartu);
        $this->assertStringNotContainsString('tidak dihitung di Dasbor Kesmas', $kartu);
        $harapan = [
            'idl' => ['IDL', 2026], '24_bln' => ['Sasaran 24 bulan', 2027], 'ibl' => ['IBL', 2028],
            '48_bln' => ['Sasaran 48 bulan', 2029], '60_bln' => ['Sasaran 60 bulan', 2030], '72_bln' => ['Sasaran 72 bulan', 2031],
        ];
        foreach ($harapan as $kode => [$label, $tahun]) {
            $this->assertMatchesRegularExpression(
                '/<tr data-tahap="' . $kode . '"><td>' . preg_quote($label, '/') . '<\/td><td class="text-right">' . $tahun . '<\/td><\/tr>/',
                $kartu, $kode
            );
        }
        $this->assertStringContainsString('Dasbor imunisasi memakai kohort 1 April–31 Maret', $kartu);
    }

    public function test_kartu_sasaran_belum_ditandai_dan_dilepas_tidak_dihitung(): void
    {
        $belum = $this->kartu($this->render($this->anak()), 'sasaran-info-title');
        $this->assertMatchesRegularExpression('/Sasaran Balita Kesmas<\/dt>\s*<dd[^>]*>\s*Belum ditandai/', $belum);
        $this->assertStringContainsString('tidak dihitung di Dasbor Kesmas', $belum);

        $dilepas = $this->kartu($this->render($this->anak(['nik' => '6474010101250019', 'sasaran_balita_kesmas' => 0])), 'sasaran-info-title');
        $this->assertMatchesRegularExpression('/Sasaran Balita Kesmas<\/dt>\s*<dd[^>]*>\s*Tidak \(dilepas\)/', $dilepas);
        $this->assertStringContainsString('tidak dihitung di Dasbor Kesmas', $dilepas);
    }
```

`tests/Feature/Kesmas/ExportKesmasTest.php` — tambahkan method:
```php
    public function test_sheet_per_anak_memuat_hbig_sasaran_dan_tahun_sasaran_di_ujung_kanan(): void
    {
        $this->anak('6474010101250006', $this->kelA, ['tgl_lahir' => '2025-05-10', 'tgl_hbig' => '2025-05-10', 'sasaran_balita_kesmas' => 1]);
        $this->anak('6474010101250007', $this->kelA, ['tgl_lahir' => '2024-02-01']); // belum ditandai — tetap terekspor

        [$anak] = $this->sheets(['id_kel' => $this->kelA->id]);

        $judul = [
            'AF' => 'Komplikasi Neonatal', 'AG' => 'Tgl HBIG', 'AH' => 'Sasaran Balita Kesmas',
            'AI' => 'Thn Sasaran IDL', 'AJ' => 'Thn Sasaran 24 bln', 'AK' => 'Thn Sasaran IBL',
            'AL' => 'Thn Sasaran 48 bln', 'AM' => 'Thn Sasaran 60 bln', 'AN' => 'Thn Sasaran 72 bln',
        ];
        foreach ($judul as $kolom => $teks) {
            $this->assertSame($teks, $anak->getCell($kolom . '1')->getValue(), $kolom);
        }

        // Urut nama: ...0006 lalu ...0007.
        $this->assertSame('2025-05-10', $anak->getCell('AG2')->getValue());
        $this->assertSame('s', $anak->getCell('AG2')->getDataType());
        $this->assertSame('Ya', $anak->getCell('AH2')->getValue());
        foreach (['AI' => 2026, 'AJ' => 2027, 'AK' => 2028, 'AL' => 2029, 'AM' => 2030, 'AN' => 2031] as $kolom => $tahun) {
            $this->assertSame($tahun, (int) $anak->getCell($kolom . '2')->getValue(), $kolom);
            $this->assertSame('n', $anak->getCell($kolom . '2')->getDataType(), "$kolom harus angka");
        }
        $this->assertSame('', (string) $anak->getCell('AG3')->getValue());
        $this->assertSame('', (string) $anak->getCell('AH3')->getValue(), 'NULL = belum ditandai → kosong');
        $this->assertSame(2025, (int) $anak->getCell('AI3')->getValue());
    }
```

- [ ] **Step 2: Jalankan dan pastikan gagal**

Run: `php artisan test --filter='KesmasPresenterTest|DetailAnakKesmasTest|ExportKesmasTest'`
Expected: FAIL — `KesmasPresenter::sasaran()` tidak ada, kartu `sasaran-info-title` tidak ditemukan, sel AG1 kosong.

- [ ] **Step 3: Implementasi**

`app/Services/KesmasPresenter.php` — tambahkan setelah method `teks()`:
```php
    /** Status tanda Sasaran Balita Kesmas di detail anak (spec 2026-10-02 §6.1). NULL ≠ "Tidak". */
    public static function sasaran($nilai): string
    {
        if ($nilai === null || $nilai === '') {
            return 'Belum ditandai';
        }

        return ((int) $nilai) === 1 ? 'Ya' : 'Tidak (dilepas)';
    }
```

`resources/views/admin/anak/show.blade.php` — ganti:
```blade
                        <dd class="col-sm-7 mb-0">{{ $K::teks($anak->komplikasi_neonatal) }}</dd>
                    </dl>
                    @endif
                </div>
            </article>
        </div>
    </section>
```
dengan:
```blade
                        <dd class="col-sm-7 mb-0">{{ $K::teks($anak->komplikasi_neonatal) }}</dd>
                    </dl>
                    @endif
                </div>
            </article>
        </div>

        {{-- Sasaran Kesmas (spec 2026-10-02 §6.1): tanda dasbor Kesmas + tahun sasaran rumus Kesmas. Selalu tampil. --}}
        @php
            $nilaiSasaran = $anak->sasaran_balita_kesmas === null ? null : (int) $anak->sasaran_balita_kesmas;
            $tahunSasaran = \App\Support\TahunSasaranKesmas::coba($anak->tgl_lahir);
        @endphp
        <div class="col-lg-4 mb-4">
            <article class="card info-card h-100">
                <div class="card-header">
                    <h2 id="sasaran-info-title">
                        <span aria-hidden="true" class="icon-copy dw dw-checked mr-2"></span>
                        Sasaran Kesmas
                    </h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-2">
                        <dt class="col-sm-5 text-accessible-muted">Sasaran Balita Kesmas</dt>
                        <dd class="col-sm-7">{{ $K::sasaran($nilaiSasaran) }}
                            @if ($nilaiSasaran !== 1)
                            <br><small class="text-accessible-muted">tidak dihitung di Dasbor Kesmas</small>
                            @endif
                        </dd>
                    </dl>
                    @if ($tahunSasaran)
                    <table class="table table-sm mb-2">
                        <caption class="sr-only">Tahun sasaran per tahap (rumus Kesmas)</caption>
                        <thead><tr><th scope="col">Tahap</th><th scope="col" class="text-right">Tahun</th></tr></thead>
                        <tbody>
                            @foreach ($tahunSasaran->semua() as $kode => $t)
                            <tr data-tahap="{{ $kode }}"><td>{{ $t['label'] }}</td><td class="text-right">{{ $t['tahun'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                    @else
                    <p class="text-accessible-muted mb-2">Tahun sasaran tidak dapat dihitung — tanggal lahir tidak sah.</p>
                    @endif
                    <p class="small text-accessible-muted mb-0">Rumus Kesmas: tahun lahir + n. Dasbor imunisasi memakai kohort 1 April–31 Maret, jadi tahunnya bisa berbeda.</p>
                </div>
            </article>
        </div>
    </section>
```

`app/Exports/KesmasAnakSheet.php`:

1. Tambah import setelah `use App\Services\KesmasPresenter as K;`:
```php
use App\Support\TahunSasaranKesmas;
```

2. Tambah konstanta di awal kelas (setelah `{` pembuka kelas, sebelum konstruktor):
```php
    /** Kolom yang tetap numerik: BBL, PBL, LK lahir, usia kehamilan (R–U) + enam tahun sasaran (AI–AN). */
    private const KOLOM_ANGKA = ['R', 'S', 'T', 'U', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN'];
```

3. Di `headings()`, ganti:
```php
            'IMD', 'KEK Ibu', 'SHK', 'SHAK', 'G6PD', 'Hepatitis B', 'Komplikasi Persalinan', 'Komplikasi Neonatal',
        ];
```
dengan:
```php
            'IMD', 'KEK Ibu', 'SHK', 'SHAK', 'G6PD', 'Hepatitis B', 'Komplikasi Persalinan', 'Komplikasi Neonatal',
            // Spec 2026-10-02 §6.2 — di ujung kanan agar kolom numerik R–U tidak bergeser.
            'Tgl HBIG', 'Sasaran Balita Kesmas', 'Thn Sasaran IDL', 'Thn Sasaran 24 bln', 'Thn Sasaran IBL',
            'Thn Sasaran 48 bln', 'Thn Sasaran 60 bln', 'Thn Sasaran 72 bln',
        ];
```

4. Di `map()`, ganti:
```php
            $a->komplikasi_persalinan, $a->komplikasi_neonatal,
        ];
    }
```
dengan:
```php
            $a->komplikasi_persalinan, $a->komplikasi_neonatal,
            $a->tgl_hbig, K::yaTidak($a->sasaran_balita_kesmas),
            ...self::tahunSasaran($a->tgl_lahir),
        ];
    }

    /** @return list<int|null> enam tahun sasaran (rumus Kesmas), kosong bila tanggal lahir tak sah. */
    private static function tahunSasaran(?string $tglLahir): array
    {
        $t = TahunSasaranKesmas::coba($tglLahir);

        return $t ? array_column($t->semua(), 'tahun') : array_fill(0, count(TahunSasaranKesmas::TAHAP), null);
    }
```

5. Di `bindValue()`, ganti:
```php
        if ($value !== null && !in_array($cell->getColumn(), ['R', 'S', 'T', 'U'], true)) {
```
dengan:
```php
        if ($value !== null && !in_array($cell->getColumn(), self::KOLOM_ANGKA, true)) {
```

- [ ] **Step 4: Jalankan dan pastikan lulus**

```bash
php artisan view:clear
php artisan test --filter='KesmasPresenterTest|DetailAnakKesmasTest|ExportKesmasTest|KesmasSwarmDataExportTest|FormHbigTest'
```
Expected: PASS semua (`KesmasSwarmDataExportTest` membuktikan R–U tetap numerik).

- [ ] **Step 5: Commit**

```bash
git add app/Services/KesmasPresenter.php resources/views/admin/anak/show.blade.php app/Exports/KesmasAnakSheet.php tests/Unit/KesmasPresenterTest.php tests/Feature/Kesmas/DetailAnakKesmasTest.php tests/Feature/Kesmas/ExportKesmasTest.php
git commit -m "feat(kesmas): kartu Sasaran Kesmas di detail anak & kolom HBIG/tanda/tahun sasaran di Export Kesmas"
```

---

### Task 9: Dasbor — populasi opt-in & baris penandaan

**Files:**
- Modify: `app/Services/KesmasDashboardService.php` (`KOLOM_KESMAS_ANAK`, `sasaranSub()`, `registri()`, method baru `penandaanSasaran()`)
- Modify: `app/Http/Controllers/KesmasDashboardController.php` (`index()`)
- Modify: `resources/views/admin/kesmas/dashboard.blade.php` (setelah `</header>`)
- Modify: `public/css/kesmas-dashboard.css` (gaya baris penandaan)
- Modify (fixture & tes): `tests/Feature/Kesmas/KesmasDashboardServiceTest.php`, `KesmasDashboardControllerTest.php`, `KesmasSwarmAgregatTest.php`, `KesmasDashboardMemoriTest.php`, `KesmasDashboardBladeTest.php`

**Interfaces:**
- Consumes: kolom `anak.sasaran_balita_kesmas` (Task 3).
- Produces: `KesmasDashboardService::penandaanSasaran(PeriodeKesmas $p, array $filters): array{total: int, bertanda: int, dilepas: int, belum: int}`; `sasaranSub(PeriodeKesmas $p, array $filters, bool $hanyaBertanda = true)`; variabel view `$penandaan`; blok `data-blok="penandaan"` ditutup `<!-- /penandaan -->`.

- [ ] **Step 1: Beri tanda 1 pada fixture tes dasbor yang ada (masih hijau sebelum implementasi)**

`tests/Feature/Kesmas/KesmasDashboardServiceTest.php` — di helper `anak()`, ganti:
```php
            'status' => 1, 'no' => '1', 'sumber' => 'manual',
            'id_kec' => $this->kec->id, 'id_kel' => $this->kel->id,
        ], $extra));
```
dengan:
```php
            'status' => 1, 'no' => '1', 'sumber' => 'manual',
            'id_kec' => $this->kec->id, 'id_kel' => $this->kel->id,
            // Dasbor Kesmas opt-in (spec 2026-10-02 §6.3): fixture harus bertanda, kalau tidak populasinya kosong.
            'sasaran_balita_kesmas' => 1,
        ], $extra));
```
dan ganti **kedua** kemunculan (anak "Lahir tahun depan" & "Anak 2024"; replace-all):
```php
'status' => 1, 'no' => '1', 'sumber' => 'manual', 'id_kec' => $this->kec->id, 'id_kel' => $this->kel->id]);
```
dengan:
```php
'status' => 1, 'no' => '1', 'sumber' => 'manual', 'id_kec' => $this->kec->id, 'id_kel' => $this->kel->id, 'sasaran_balita_kesmas' => 1]);
```

`tests/Feature/Kesmas/KesmasDashboardControllerTest.php` — di helper `anak()`, ganti:
```php
            'tgl_lahir' => $tglLahir, 'status' => 1, 'no' => '1', 'sumber' => 'manual',
            'id_kec' => $this->kec->id, 'id_kel' => $this->kel->id,
        ], $extra));
```
dengan:
```php
            'tgl_lahir' => $tglLahir, 'status' => 1, 'no' => '1', 'sumber' => 'manual',
            'id_kec' => $this->kec->id, 'id_kel' => $this->kel->id, 'sasaran_balita_kesmas' => 1,
        ], $extra));
```

`tests/Feature/Kesmas/KesmasSwarmAgregatTest.php` — di helper `anak()`, ganti:
```php
            'status' => 1, 'no' => '1', 'sumber' => 'manual',
            'id_kec' => $this->kec->id, 'id_kel' => $this->kel->id,
        ], $extra));
```
dengan:
```php
            'status' => 1, 'no' => '1', 'sumber' => 'manual',
            'id_kec' => $this->kec->id, 'id_kel' => $this->kel->id, 'sasaran_balita_kesmas' => 1,
        ], $extra));
```

`tests/Feature/Kesmas/KesmasDashboardMemoriTest.php`:
- di `seedMassal()`, ganti:
```php
                    'id_kec' => $idKec, 'id_kel' => $idKel, 'created_at' => $now, 'updated_at' => $now,
```
dengan:
```php
                    'id_kec' => $idKec, 'id_kel' => $idKel, 'created_at' => $now, 'updated_at' => $now,
                    'sasaran_balita_kesmas' => 1,
```
- di akhir `seedMassal()`, ganti:
```php
        $this->assertDatabaseCount('data_anak', self::JUMLAH_ANAK * self::KUNJUNGAN_PER_ANAK);
    }
```
dengan:
```php
        $this->assertDatabaseCount('data_anak', self::JUMLAH_ANAK * self::KUNJUNGAN_PER_ANAK);
        // Dasbor opt-in: tanpa tanda, populasi kosong dan tes memori/kueri LOLOS PALSU.
        $this->assertSame($jumlah, DB::table('anak')->where('sasaran_balita_kesmas', 1)->count(),
            'Fixture memori harus bertanda Sasaran Balita Kesmas');
    }
```

Run: `php artisan test --filter='KesmasDashboardServiceTest|KesmasDashboardControllerTest|KesmasSwarmAgregatTest'`
Expected: PASS (kolom sudah ada sejak Task 3; perilaku belum berubah).

- [ ] **Step 2: Tulis tes baru yang gagal**

`tests/Feature/Kesmas/KesmasDashboardServiceTest.php` — tambahkan sebelum bagian `// ── Registri per anak`:
```php
    // ── Tanda Sasaran Balita Kesmas (spec 2026-10-02 §6.3) ─────────────────

    public function test_anak_belum_ditandai_dan_dilepas_tidak_masuk_agregat_mana_pun(): void
    {
        $this->seedVaksin();
        foreach ([1, null, 0] as $tanda) {
            $a = $this->anak(30, ['sasaran_balita_kesmas' => $tanda, 'air_bersih' => 1]);
            $this->kunjunganBulanan($a, 8, 1, ['ddtka' => 'Sesuai', 'vit_a' => 1, 'kn1' => 1, 'tgl_penanda_ckg' => '2025-01-15']);
        }
        $p = $this->tahun2025();

        $this->assertSame(1, $this->svc->sasaran($p, [])['semua']);
        $this->assertSame(1, $this->svc->spmKohort($p, [])['anak_balita']['sasaran']);
        $this->assertSame(1, $this->svc->pemantauanTk($p, [])['sasaran']);
        $this->assertSame(1, $this->svc->sdidtk($p, [])['total']['sasaran']);
        $this->assertSame(1, $this->svc->ckg($p, [])['kelompok']['t2']['sasaran']);
        $l = $this->svc->layananLingkungan($p, []);
        $this->assertSame(1, $l['sasaran']);
        $this->assertSame(1, $l['layanan']['baris']['kn1']['terisi']);
        $this->assertSame(1, $l['sanitasi']['baris']['air_bersih']['terisi']);
        $this->assertSame(1, $this->svc->registri($p, [])['total']);
    }

    public function test_penandaan_sasaran_dihitung_tanpa_filter_tanda(): void
    {
        $this->anak(5);                                                             // bertanda (bawaan helper)
        $this->anak(30, ['sasaran_balita_kesmas' => null]);
        $this->anak(40, ['sasaran_balita_kesmas' => null]);
        $this->anak(60, ['sasaran_balita_kesmas' => 0]);
        $this->anak(80, ['sasaran_balita_kesmas' => null]);                         // > 72 bln — di luar dasbor
        $this->anak(20, ['sasaran_balita_kesmas' => null, 'id_kel' => $this->kelLain->id]);

        $this->assertSame(['total' => 5, 'bertanda' => 1, 'dilepas' => 1, 'belum' => 3],
            $this->svc->penandaanSasaran($this->tahun2025(), []));
        $this->assertSame(['total' => 4, 'bertanda' => 1, 'dilepas' => 1, 'belum' => 2],
            $this->svc->penandaanSasaran($this->tahun2025(), ['id_kelurahan' => $this->kel->id]));
    }

    public function test_penandaan_tanpa_anak_semua_nol(): void
    {
        $this->assertSame(['total' => 0, 'bertanda' => 0, 'dilepas' => 0, 'belum' => 0],
            $this->svc->penandaanSasaran($this->tahun2025(), []));
    }
```

`tests/Feature/Kesmas/KesmasDashboardControllerTest.php` — tambahkan method:
```php
    public function test_baris_penandaan_dan_peringatan_saat_belum_ada_anak_bertanda(): void
    {
        // Review Focus #5: hari pertama setelah rilis.
        $this->anak('Belum Ditandai', '2023-06-30', ['sasaran_balita_kesmas' => null]);

        $html = $this->actingAs($this->admin)->get(route('admin.kesmas.dashboard', ['tahun' => 2025]))->assertOk()->getContent();
        $blok = $this->blok($html, 'penandaan');

        $this->assertStringContainsString('km-penandaan--kosong', $blok);
        $this->assertStringContainsString('Belum ada anak bertanda Sasaran Balita Kesmas', $blok);
        $this->assertStringContainsString('1 belum ditandai', $blok);
        foreach (['spm-balita', 'spm-bayi', 'spm-anak-balita', 'spm-tk'] as $nama) {
            $this->assertStringNotContainsString('0,0 %', $this->blok($html, $nama), "{$nama}: pembagi 0 harus '—'");
        }
    }

    public function test_baris_penandaan_menyebut_jumlah_bertanda(): void
    {
        $this->anak('Bertanda', '2023-06-30');
        $this->anak('Belum', '2023-06-30', ['sasaran_balita_kesmas' => null]);

        $html = $this->actingAs($this->admin)->get(route('admin.kesmas.dashboard', ['tahun' => 2025]))->assertOk()->getContent();
        $blok = $this->blok($html, 'penandaan');

        $this->assertStringNotContainsString('km-penandaan--kosong', $blok);
        $this->assertMatchesRegularExpression('/<b class="im-num">1<\/b> dari 2 anak 0–72 bln sudah ditandai/', $blok);
    }
```

`tests/Feature/Kesmas/KesmasDashboardMemoriTest.php` — di `test_agregat_tidak_memuat_seluruh_populasi_dan_jumlah_query_tetap`, ganti:
```php
        $layanan = $svc->layananLingkungan($p, []);

        $jumlahQuery = count(DB::getQueryLog());
```
dengan:
```php
        $layanan = $svc->layananLingkungan($p, []);
        $penandaan = $svc->penandaanSasaran($p, []);

        $jumlahQuery = count(DB::getQueryLog());
```
lalu ganti:
```php
        $this->assertSame(self::JUMLAH_ANAK, $layanan['layanan']['baris']['kn1']['terisi']);
```
dengan:
```php
        $this->assertSame(self::JUMLAH_ANAK, $layanan['layanan']['baris']['kn1']['terisi']);
        $this->assertSame(self::JUMLAH_ANAK, $penandaan['bertanda']);
```
dan ganti teks pesan `'%d query untuk enam agregat` menjadi `'%d query untuk tujuh agregat`.

`tests/Feature/Kesmas/KesmasDashboardBladeTest.php` — di `test_blok_ditutup_komentar_penanda`, ganti:
```php
        foreach (['spm-balita', 'spm-bayi', 'spm-anak-balita', 'spm-tk', 'sdidtk', 'ckg', 'idl', 'layanan', 'registri'] as $nama) {
```
dengan:
```php
        foreach (['penandaan', 'spm-balita', 'spm-bayi', 'spm-anak-balita', 'spm-tk', 'sdidtk', 'ckg', 'idl', 'layanan', 'registri'] as $nama) {
```

- [ ] **Step 3: Jalankan dan pastikan gagal**

Run: `php artisan test --filter='KesmasDashboardServiceTest|KesmasDashboardControllerTest|KesmasDashboardBladeTest'`
Expected: FAIL — `penandaanSasaran()` tidak ada; anak NULL/0 masih dihitung (`semua` = 3); blok `penandaan` tidak ditemukan.

- [ ] **Step 4: Implementasi**

`app/Services/KesmasDashboardService.php`:

1. Ganti:
```php
    private const KOLOM_KESMAS_ANAK = [
        'skrining_shk', 'skrining_shak', 'skrining_g6pd', 'pemeriksaan_hepatitis_b',
        'air_bersih', 'jamban_sehat', 'merokok_keluarga',
    ];
```
dengan:
```php
    private const KOLOM_KESMAS_ANAK = [
        'skrining_shk', 'skrining_shak', 'skrining_g6pd', 'pemeriksaan_hepatitis_b',
        'air_bersih', 'jamban_sehat', 'merokok_keluarga', 'sasaran_balita_kesmas',
    ];
```

2. Ganti method `sasaranSub()` seluruhnya:
```php
    /** Subquery `s`: anak terfilter wilayah dengan umur (bulan) pada akhir periode. */
    private function sasaranSub(PeriodeKesmas $p, array $filters): Builder
    {
        $akhir = $p->akhir()->toDateString();
        $q = DB::table('anak as a')
            ->selectRaw(
                'a.id, TIMESTAMPDIFF(MONTH, a.tgl_lahir, ?) as umur, a.' . implode(', a.', self::KOLOM_KESMAS_ANAK),
                [$akhir]
            )
            ->whereNotNull('a.tgl_lahir')
            ->where('a.tgl_lahir', '<=', $akhir);

        return $this->applyWilayahFilters($q, $filters, 'a');
    }
```
dengan:
```php
    /**
     * Subquery `s`: anak terfilter wilayah dengan umur (bulan) pada akhir periode.
     * Bawaan hanya anak bertanda Sasaran Balita Kesmas = 1 (opt-in, spec 2026-10-02 §6.3) —
     * NULL (belum ditandai) dan 0 (dilepas) tidak dihitung. $hanyaBertanda = false khusus
     * penandaanSasaran(), yang justru menghitung ketiganya.
     */
    private function sasaranSub(PeriodeKesmas $p, array $filters, bool $hanyaBertanda = true): Builder
    {
        $akhir = $p->akhir()->toDateString();
        $q = DB::table('anak as a')
            ->selectRaw(
                'a.id, TIMESTAMPDIFF(MONTH, a.tgl_lahir, ?) as umur, a.' . implode(', a.', self::KOLOM_KESMAS_ANAK),
                [$akhir]
            )
            ->whereNotNull('a.tgl_lahir')
            ->where('a.tgl_lahir', '<=', $akhir);
        if ($hanyaBertanda) {
            $q->where('a.sasaran_balita_kesmas', 1);
        }

        return $this->applyWilayahFilters($q, $filters, 'a');
    }

    /**
     * Cakupan penandaan Sasaran Balita Kesmas (spec 2026-10-02 §6.3): anak 0–72 bln (umur akhir
     * periode) di wilayah terfilter, TANPA filter tanda — menjelaskan kenapa kartu kecil atau kosong.
     *
     * @return array{total: int, bertanda: int, dilepas: int, belum: int}
     */
    public function penandaanSasaran(PeriodeKesmas $p, array $filters): array
    {
        $r = DB::query()->fromSub($this->sasaranSub($p, $filters, false), 's')
            ->where('s.umur', '<=', 72)
            ->selectRaw('COUNT(*) as total, SUM(sasaran_balita_kesmas = 1) as bertanda, '
                . 'SUM(sasaran_balita_kesmas = 0) as dilepas, SUM(sasaran_balita_kesmas IS NULL) as belum')
            ->first();

        return array_map('intval', (array) $r);
    }
```

3. Di `registri()`, ganti:
```php
            ->whereRaw('TIMESTAMPDIFF(MONTH, a.tgl_lahir, ?) BETWEEN ? AND ?', [$akhir, $min, $max]);
```
dengan:
```php
            ->whereRaw('TIMESTAMPDIFF(MONTH, a.tgl_lahir, ?) BETWEEN ? AND ?', [$akhir, $min, $max])
            ->where('a.sasaran_balita_kesmas', 1); // populasi sama dengan kartu (opt-in, spec 2026-10-02 §6.3)
```

`app/Http/Controllers/KesmasDashboardController.php` — di `index()`, ganti:
```php
            'sasaran' => $svc->sasaran($periode, $filters),
```
dengan:
```php
            'sasaran' => $svc->sasaran($periode, $filters),
            'penandaan' => $svc->penandaanSasaran($periode, $filters),
```

`resources/views/admin/kesmas/dashboard.blade.php` — ganti:
```blade
    </header>

    @include('admin.kesmas.partials._filter')
```
dengan:
```blade
    </header>

    {{-- Penandaan Sasaran Balita Kesmas (spec 2026-10-02 §6.3): populasi dasbor = anak bertanda 1 --}}
    <section class="km-penandaan {{ $penandaan['bertanda'] === 0 ? 'km-penandaan--kosong' : '' }}" data-blok="penandaan" role="status" aria-label="Penandaan sasaran">
        @if($penandaan['bertanda'] === 0)
            <b>Belum ada anak bertanda Sasaran Balita Kesmas di wilayah ini</b>, jadi kartu menampilkan "—".
            Centang "Sasaran Balita Kesmas" di Edit Anak, atau minta admin menjalankan penandaan massal.
            <span class="km-penandaan__angka">({{ $fmt($penandaan['total']) }} anak 0–72 bln: {{ $fmt($penandaan['belum']) }} belum ditandai · {{ $fmt($penandaan['dilepas']) }} dilepas)</span>
        @else
            Sasaran Balita Kesmas: <b class="im-num">{{ $fmt($penandaan['bertanda']) }}</b> dari {{ $fmt($penandaan['total']) }} anak 0–72 bln sudah ditandai
            · {{ $fmt($penandaan['belum']) }} belum ditandai · {{ $fmt($penandaan['dilepas']) }} dilepas
        @endif
    </section><!-- /penandaan -->

    @include('admin.kesmas.partials._filter')
```

`public/css/kesmas-dashboard.css` — tambahkan di akhir berkas:
```css
/* Baris penandaan Sasaran Balita Kesmas (spec 2026-10-02 §6.3) */
.km-penandaan{ margin:-.4rem 0 1rem; padding:.55rem .85rem; border:1px solid var(--line); border-radius:10px; background:var(--card); color:var(--muted); font-size:.82rem; line-height:1.45; }
.km-penandaan b{ color:var(--ink); }
.km-penandaan--kosong{ background:var(--amber-bg); border-color:oklch(0.85 0.09 70); color:oklch(0.35 0.10 70); }
.km-penandaan--kosong b{ color:oklch(0.30 0.11 70); }
.km-penandaan__angka{ display:block; font-size:.75rem; }
```

- [ ] **Step 5: Jalankan dan pastikan lulus**

```bash
php artisan view:clear
php artisan test --filter='KesmasDashboardServiceTest|KesmasDashboardControllerTest|KesmasDashboardBladeTest|KesmasSwarmAgregatTest|KesmasSwarmAksesTest|KesmasDashboardMemoriTest'
```
Expected: PASS semua (±3–4 menit; `KesmasDashboardMemoriTest` tetap < 16 MB dan ≤ 20 query).

- [ ] **Step 6: Commit**

```bash
git add app/Services/KesmasDashboardService.php app/Http/Controllers/KesmasDashboardController.php resources/views/admin/kesmas/dashboard.blade.php public/css/kesmas-dashboard.css tests/Feature/Kesmas/KesmasDashboardServiceTest.php tests/Feature/Kesmas/KesmasDashboardControllerTest.php tests/Feature/Kesmas/KesmasSwarmAgregatTest.php tests/Feature/Kesmas/KesmasDashboardMemoriTest.php tests/Feature/Kesmas/KesmasDashboardBladeTest.php
git commit -m "feat(kesmas): dasbor Kesmas hanya menghitung anak bertanda Sasaran Balita Kesmas + baris penandaan"
```

---

### Task 10: Dasbor — K4 0–59, chip registri, label kartu, HBIG

**Files:**
- Modify: `app/Services/KesmasDashboardService.php` (`KOLOM_KESMAS_ANAK`, `pemantauanTk()`, `USIA`, `layananLingkungan()`)
- Modify: `resources/views/admin/kesmas/partials/_spm.blade.php`, `_sdidtk-ckg-idl.blade.php`, `_layanan.blade.php`
- Modify: `resources/views/admin/kesmas/dashboard.blade.php` (daftar chip usia)
- Modify: `public/js/kesmas-registri.js` (tautan "perlu perhatian")
- Modify: `public/css/kesmas-dashboard.css`
- Modify (tes): `tests/Feature/Kesmas/KesmasDashboardServiceTest.php`, `KesmasSwarmAgregatTest.php`, `KesmasDashboardControllerTest.php`, `e2e/kesmas-dashboard.spec.ts`, `e2e/kesmas-swarm.spec.ts`

**Interfaces:**
- Consumes: `penandaanSasaran()`, populasi opt-in (Task 9); kolom `anak.tgl_hbig` (Task 3).
- Produces: `KesmasDashboardService::USIA['balita_0_59'] = [0, 59]` (juga rentang K4); `layananLingkungan()['hbig']: int`; chip `data-usia="balita_0_59"` berlabel "Semua balita (0–59)".

- [ ] **Step 1: Tulis/ubah tes yang gagal**

`tests/Feature/Kesmas/KesmasDashboardServiceTest.php` — ganti seluruh method:
```php
    public function test_pemantauan_tk_mencakup_prasekolah_dan_hanya_timbang_plus_ddtka(): void
    {
        $pra = $this->anak(65);
        $this->kunjunganBulanan($pra, 6, 1);
        $this->kunjungan($pra, '2025-07-15', ['ddtka' => 'Sesuai']);
        $this->kunjungan($pra, '2025-08-15', ['ddtka' => 'Sesuai']); // 8 timbang, 2 ddtka, tanpa vit A → lengkap

        $bayi = $this->anak(8);
        $this->kunjunganBulanan($bayi, 8, 5); // 8 timbang tanpa ddtka → tidak

        $tk = $this->svc->pemantauanTk($this->tahun2025(), []);

        $this->assertSame(2, $tk['sasaran']);
        $this->assertSame(1, $tk['lengkap']);
        $this->assertSame(50.0, $tk['persen']);
    }
```
dengan:
```php
    public function test_pemantauan_tk_hanya_0_59_bulan_dan_hanya_timbang_plus_ddtka(): void
    {
        // K4 = "Balita Dilayani Tumbuh Kembang (0–60 bln)" di lembar klien, dibaca < 60 bulan (spec 2026-10-02 §6.3).
        $batas = $this->anak(59);
        $this->kunjunganBulanan($batas, 6, 1);
        $this->kunjungan($batas, '2025-07-15', ['ddtka' => 'Sesuai']);
        $this->kunjungan($batas, '2025-08-15', ['ddtka' => 'Sesuai']); // 8 timbang, 2 ddtka, tanpa vit A → lengkap

        $pra = $this->anak(60); // syarat lengkap, tetapi di luar 0–59
        $this->kunjunganBulanan($pra, 6, 1);
        $this->kunjungan($pra, '2025-07-15', ['ddtka' => 'Sesuai']);
        $this->kunjungan($pra, '2025-08-15', ['ddtka' => 'Sesuai']);

        $bayi = $this->anak(8);
        $this->kunjunganBulanan($bayi, 8, 5); // 8 timbang tanpa ddtka → tidak

        $tk = $this->svc->pemantauanTk($this->tahun2025(), []);

        $this->assertSame(2, $tk['sasaran'], 'anak 59 & 8 bln; anak 60 bln tidak');
        $this->assertSame(1, $tk['lengkap']);
        $this->assertSame(50.0, $tk['persen']);
    }

    public function test_perlu_perhatian_k4_hanya_0_59_bulan_dan_sama_dengan_registri(): void
    {
        $this->seedVaksin();
        $this->kunjungan($this->anak(59), '2025-06-15', ['ntob' => 'T']);
        $this->kunjungan($this->anak(65), '2025-06-15', ['ntob' => 'T']);

        $this->assertSame(1, $this->svc->pemantauanTk($this->tahun2025(), [])['perhatian']);
        $this->assertSame(1, $this->svc->registri($this->tahun2025(), [], 'balita_0_59', '', 'perhatian')['total']);
    }

    public function test_hbig_dihitung_per_periode_hanya_anak_bertanda(): void
    {
        $this->anak(3, ['tgl_hbig' => '2025-09-30']);                                   // lahir 30 Sep 2025 → dalam periode
        $this->anak(13, ['tgl_hbig' => '2024-11-30']);                                  // tahun lalu
        $this->anak(4, ['tgl_hbig' => '2025-08-31', 'sasaran_balita_kesmas' => null]);  // belum ditandai
        $this->anak(2);                                                                 // tanpa HBIG

        $this->assertSame(1, $this->svc->layananLingkungan($this->tahun2025(), [])['hbig']);
        $this->assertSame(0, $this->svc->layananLingkungan(PeriodeKesmas::dari(2025, 'tw1'), [])['hbig']);
    }
```

`tests/Feature/Kesmas/KesmasSwarmAgregatTest.php` — di `test_jumlah_perhatian_k4_sama_dengan_drilldown_setelah_koreksi_pada_hari_yang_sama`, ganti:
```php
        $drilldown = $this->svc->registri($periode, [], 'semua', '', 'perhatian');
```
dengan:
```php
        // K4 = 0–59 bln; tautan "perlu perhatian" memilih chip balita_0_59 (spec 2026-10-02 §6.3).
        $drilldown = $this->svc->registri($periode, [], 'balita_0_59', '', 'perhatian');
```
dan ganti:
```php
        $this->getJson(route('admin.kesmas.registri', ['tahun' => 2025, 'periode' => 'tw3', 'status_gizi' => 'perhatian']))
```
dengan:
```php
        $this->getJson(route('admin.kesmas.registri', ['tahun' => 2025, 'periode' => 'tw3', 'usia' => 'balita_0_59', 'status_gizi' => 'perhatian']))
```

`tests/Feature/Kesmas/KesmasDashboardControllerTest.php` — di `test_kartu_spm_menampilkan_angka_dari_service`, ganti:
```php
        $balita = $this->blok($html, 'spm-balita');
        $this->assertAngkaBesar($balita, '1', '3');
        $this->assertStringContainsString('33,3 %', $balita);
```
dengan:
```php
        $balita = $this->blok($html, 'spm-balita');
        $this->assertAngkaBesar($balita, '1', '3');
        $this->assertStringContainsString('33,3 %', $balita);
        $this->assertStringContainsString('1 bayi (0–11 bln) + 0 anak balita (12–59 bln)', $balita);
```
dan ganti:
```php
        $this->assertAngkaBesar($kTk, '1', '4');
```
dengan:
```php
        $this->assertAngkaBesar($kTk, '1', '3'); // K4 0–59 bln: anak prasekolah 65 bln tidak ikut
```
lalu tambahkan method:
```php
    public function test_label_kartu_mengikuti_nama_indikator_klien_dan_chip_k4(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.kesmas.dashboard', ['tahun' => 2025]))->assertOk()->getContent();

        $this->assertStringContainsString('Usia 0–5 tahun (0–59 bulan): gabungan Pelayanan Kesehatan Bayi + Anak Balita', $this->blok($html, 'spm-balita'));
        $kTk = $this->blok($html, 'spm-tk');
        $this->assertStringContainsString('SPM Tumbuh Kembang Balita', $kTk);
        $this->assertStringContainsString('Balita Dilayani Tumbuh Kembang', $kTk);
        $this->assertStringContainsString('0–59 bulan dengan min.', $kTk);
        $this->assertStringContainsString('Cakupan Balita &amp; Anak Prasekolah Dilayani SDIDTK', $this->blok($html, 'sdidtk'));
        $this->assertStringContainsString('tidak memakai tanda Sasaran Balita Kesmas', $this->blok($html, 'idl'));
        $this->assertStringContainsString('HBIG diberikan dalam periode', $this->blok($html, 'layanan'));
        $this->assertMatchesRegularExpression('/<button[^>]*data-usia="balita_0_59"[^>]*>Semua balita \(0–59\)<\/button>/', $html);

        $this->getJson(route('admin.kesmas.registri', ['usia' => 'balita_0_59']))->assertOk();
        $this->getJson(route('admin.kesmas.registri', ['usia' => 'balita_0_60']))->assertUnprocessable();
    }
```

- [ ] **Step 2: Jalankan dan pastikan gagal**

Run: `php artisan test --filter='KesmasDashboardServiceTest|KesmasSwarmAgregatTest|KesmasDashboardControllerTest'`
Expected: FAIL — K4 masih menghitung anak 60/65 bln, `usia=balita_0_59` ditolak 422, kunci `hbig` tidak ada, label lama.

- [ ] **Step 3: Implementasi service**

`app/Services/KesmasDashboardService.php`:

1. Di `KOLOM_KESMAS_ANAK`, ganti `'air_bersih', 'jamban_sehat', 'merokok_keluarga', 'sasaran_balita_kesmas',` dengan `'air_bersih', 'jamban_sehat', 'merokok_keluarga', 'sasaran_balita_kesmas', 'tgl_hbig',`.

2. Di `pemantauanTk()`, ganti potongan dari docblock sampai baris `->where('s.umur', '<=', 72)` — yaitu:
```php
    /** Kartu K4: 0–72 bln dengan ≥T8 timbang & ≥T2 DDTKA; "perlu perhatian" dari kunjungan terakhir. */
    public function pemantauanTk(PeriodeKesmas $p, array $filters): array
    {
        $r = $this->dariSasaran($p, $filters)
            ->leftJoinSub($this->kunjunganSub($p), 'k', 'k.id_anak', '=', 's.id')
            ->selectRaw(
                'SUM(umur BETWEEN 0 AND 72) as sasaran, '
                . 'SUM(umur BETWEEN 0 AND 72 AND COALESCE(n_timbang,0) >= ? AND COALESCE(n_ddtka,0) >= ?) as lengkap',
                [$p->syarat(8), $p->syarat(2)]
            )->first();
        $sasaran = (int) $r->sasaran;
        $lengkap = (int) $r->lengkap;

        // KMS kuning/merah: BB tidak naik (ntob T) atau BB/U ≤ -2 SD pada kunjungan terakhir dalam periode.
        $perhatian = $this->dariSasaran($p, $filters)
            ->joinSub($this->kunjunganTerakhirSub($p), 't', 't.id_anak', '=', 's.id')
            ->join('data_anak as da', 'da.id', '=', 't.id_kunjungan')
            ->where('s.umur', '<=', 72)
```
dengan:
```php
    /**
     * Kartu K4 "Balita Dilayani Tumbuh Kembang": 0–59 bln (lembar klien "0–60 bln", dibaca < 60 —
     * spec 2026-10-02 §6.3) dengan ≥T8 timbang & ≥T2 DDTKA; "perlu perhatian" dari kunjungan
     * terakhir. Rentang diambil dari USIA['balita_0_59'] supaya tautan ke registri selalu
     * menghitung populasi yang sama dengan kartu.
     */
    public function pemantauanTk(PeriodeKesmas $p, array $filters): array
    {
        [$min, $max] = self::USIA['balita_0_59'];
        $r = $this->dariSasaran($p, $filters)
            ->leftJoinSub($this->kunjunganSub($p), 'k', 'k.id_anak', '=', 's.id')
            ->selectRaw(
                "SUM(umur BETWEEN {$min} AND {$max}) as sasaran, "
                . "SUM(umur BETWEEN {$min} AND {$max} AND COALESCE(n_timbang,0) >= ? AND COALESCE(n_ddtka,0) >= ?) as lengkap",
                [$p->syarat(8), $p->syarat(2)]
            )->first();
        $sasaran = (int) $r->sasaran;
        $lengkap = (int) $r->lengkap;

        // KMS kuning/merah: BB tidak naik (ntob T) atau BB/U ≤ -2 SD pada kunjungan terakhir dalam periode.
        $perhatian = $this->dariSasaran($p, $filters)
            ->joinSub($this->kunjunganTerakhirSub($p), 't', 't.id_anak', '=', 's.id')
            ->join('data_anak as da', 'da.id', '=', 't.id_kunjungan')
            ->whereBetween('s.umur', [$min, $max])
```
(Baris setelahnya — `->where(function ($w) { ... ntob ... zscore_bb_u ... })->distinct()->count('s.id');` dan `return [...]` — tidak berubah.)

3. Ganti:
```php
    /** Rentang umur (bulan) untuk chip usia — registri & penyorotan SDIDTK. */
    public const USIA = [
        'semua' => [0, 72], 'bayi' => [0, 11], 'baduta' => [12, 23], 'balita' => [24, 59], 'prasekolah' => [60, 72],
    ];
```
dengan:
```php
    /**
     * Rentang umur (bulan) untuk chip usia — registri & penyorotan SDIDTK. `balita_0_59` juga
     * rentang kartu K4 (pemantauanTk), supaya jumlah registri = angka kartu.
     */
    public const USIA = [
        'semua' => [0, 72], 'balita_0_59' => [0, 59], 'bayi' => [0, 11], 'baduta' => [12, 23],
        'balita' => [24, 59], 'prasekolah' => [60, 72],
    ];
```

4. Di `layananLingkungan()`, ganti:
```php
        $sel = ['COUNT(*) as sasaran'];
        foreach (array_keys($sanitasiDef) as $k) {
            $sel[] = "SUM({$k} IS NOT NULL) as {$k}_isi";
            $sel[] = "SUM({$k} = 1) as {$k}_ya";
        }
        $rn = (array) $this->dariSasaran($p, $filters)->where('s.umur', '<=', 72)->selectRaw(implode(', ', $sel))->first();
```
dengan:
```php
        $sel = ['COUNT(*) as sasaran'];
        foreach (array_keys($sanitasiDef) as $k) {
            $sel[] = "SUM({$k} IS NOT NULL) as {$k}_isi";
            $sel[] = "SUM({$k} = 1) as {$k}_ya";
        }
        // HBIG diberikan dalam periode (spec 2026-10-02 §6.3): jumlah, bukan cakupan — status HBsAg
        // ibu tidak tercatat, jadi pembaginya tidak ada. Menumpang query ini agar tak ada query baru.
        $sel[] = 'SUM(tgl_hbig BETWEEN ? AND ?) as hbig';
        $rn = (array) $this->dariSasaran($p, $filters)->where('s.umur', '<=', 72)->selectRaw(implode(', ', $sel), [$a, $z])->first();
```
lalu ganti:
```php
        return [
            'sasaran'  => $sasaran,
            'bayi'     => $bayi,
```
dengan:
```php
        return [
            'sasaran'  => $sasaran,
            'bayi'     => $bayi,
            'hbig'     => (int) $rn['hbig'],
```

- [ ] **Step 4: Implementasi view, JS, CSS**

`resources/views/admin/kesmas/partials/_spm.blade.php`:
- ganti `        <div class="im-card__sub">Usia 0–59 bulan (gabungan kohort bayi + anak balita) yang mendapat pelayanan sesuai standar</div>` dengan:
```blade
        <div class="im-card__sub">Usia 0–5 tahun (0–59 bulan): gabungan Pelayanan Kesehatan Bayi + Anak Balita yang mendapat pelayanan sesuai standar</div>
```
- ganti:
```blade
        <div class="km-prog" role="img" aria-label="{{ $pct($spm['balita']['persen']) }}"><span class="{{ $tone($spm['balita']['persen']) }}" style="width:{{ (int) ($spm['balita']['persen'] ?? 0) }}%"></span></div>
```
dengan:
```blade
        <div class="km-prog" role="img" aria-label="{{ $pct($spm['balita']['persen']) }}"><span class="{{ $tone($spm['balita']['persen']) }}" style="width:{{ (int) ($spm['balita']['persen'] ?? 0) }}%"></span></div>
        <div class="km-k1-rincian im-num">{{ $fmt($spm['bayi']['lengkap']) }} bayi (0–11 bln) + {{ $fmt($spm['anak_balita']['lengkap']) }} anak balita (12–59 bln)</div>
```
- ganti `title="Kohort SI {{ $periode->tahun() }}, tidak mengikuti semester/triwulan"` dengan `title="Kohort SI {{ $periode->tahun() }}, tidak mengikuti semester/triwulan; populasi kohort imunisasi, tidak memakai tanda Sasaran Balita Kesmas"`.
- ganti `title="Kohort Baduta {{ $periode->tahun() }}, tidak mengikuti semester/triwulan"` dengan `title="Kohort Baduta {{ $periode->tahun() }}, tidak mengikuti semester/triwulan; populasi kohort imunisasi, tidak memakai tanda Sasaran Balita Kesmas"`.
- ganti:
```blade
        <div class="km-kicker"><span class="im-card__lbl">Pemantauan tumbuh kembang</span>
```
dengan:
```blade
        <div class="km-kicker"><span class="im-card__lbl">SPM Tumbuh Kembang Balita</span>
```
- ganti:
```blade
        <div class="km-title">Pemantauan Lengkap T&amp;K</div>
        <div class="im-card__sub">0–72 bulan dengan min. {{ $sy['timbang'] }}× timbang + {{ $sy['ddtka'] }}× DDTKA dalam periode</div>
```
dengan:
```blade
        <div class="km-title">Balita Dilayani Tumbuh Kembang</div>
        <div class="im-card__sub">0–59 bulan dengan min. {{ $sy['timbang'] }}× timbang + {{ $sy['ddtka'] }}× DDTKA dalam periode</div>
```

`resources/views/admin/kesmas/partials/_sdidtk-ckg-idl.blade.php`:
- ganti:
```blade
        <div class="im-h" style="margin-bottom:.2rem;"><h2>Cakupan Layanan SDIDTK</h2></div>
        <div class="im-card__sub">Stimulasi, Deteksi &amp; Intervensi Dini Tumbuh Kembang (0–72 bulan)</div>
```
dengan:
```blade
        <div class="im-h" style="margin-bottom:.2rem;"><h2>Cakupan Balita &amp; Anak Prasekolah Dilayani SDIDTK</h2></div>
        <div class="im-card__sub">0–72 bulan · penjumlahan kelompok 0–11, 12–23, 24–59, 60–72 · Stimulasi, Deteksi &amp; Intervensi Dini Tumbuh Kembang</div>
```
- ganti:
```blade
        <div class="im-card__sub">Cakupan IDL (kohort SI) &amp; IBL (kohort Baduta) {{ $periode->tahun() }} — mengikuti tahun, bukan semester/triwulan</div>
```
dengan:
```blade
        <div class="im-card__sub">Cakupan IDL (kohort SI) &amp; IBL (kohort Baduta) {{ $periode->tahun() }} — mengikuti tahun, bukan semester/triwulan · populasi kohort imunisasi; tidak memakai tanda Sasaran Balita Kesmas</div>
```

`resources/views/admin/kesmas/partials/_layanan.blade.php` — ganti:
```blade
                    <div class="km-empty">Belum ada data skrining — lengkapi lewat Edit Anak (kartu Riwayat lahir &amp; skrining)</div>
                    <p class="km-belum">{{ $fmt($layanan['bayi']) }} bayi belum diisi untuk setiap skrining</p>
                @endif
            </div>
```
dengan:
```blade
                    <div class="km-empty">Belum ada data skrining — lengkapi lewat Edit Anak (kartu Riwayat lahir &amp; skrining)</div>
                    <p class="km-belum">{{ $fmt($layanan['bayi']) }} bayi belum diisi untuk setiap skrining</p>
                @endif
                <p class="km-hbig" data-baris="hbig">HBIG diberikan dalam periode: <b class="im-num">{{ $fmt($layanan['hbig']) }}</b> bayi
                    <small>(bayi dari ibu HBsAg reaktif — jumlah, bukan cakupan)</small></p>
            </div>
```

`resources/views/admin/kesmas/dashboard.blade.php` — ganti:
```blade
        @foreach(['semua' => 'Semua (0–72 bln)', 'bayi' => 'Bayi (0–11)', 'baduta' => 'Baduta (12–23)', 'balita' => 'Balita (24–59)', 'prasekolah' => 'Prasekolah (60–72)'] as $kode => $label)
```
dengan:
```blade
        @foreach(['semua' => 'Semua (0–72 bln)', 'balita_0_59' => 'Semua balita (0–59)', 'bayi' => 'Bayi (0–11)', 'baduta' => 'Baduta (12–23)', 'balita' => 'Balita (24–59)', 'prasekolah' => 'Prasekolah (60–72)'] as $kode => $label)
```

`public/js/kesmas-registri.js` — ganti:
```js
    document.querySelectorAll('[data-registri-status]').forEach((link) => {
        link.addEventListener('click', () => {
            // K4 menghitung seluruh usia: pulihkan lingkup yang sama sebelum melihat rinciannya.
            document.querySelector('.km-chip[data-usia="semua"]').setAttribute('aria-pressed', 'true');
            document.querySelectorAll('.km-chip[data-usia]:not([data-usia="semua"])').forEach((b) => b.setAttribute('aria-pressed', 'false'));
            state.usia = 'semua';
            usiaHidden.value = 'semua';
            const url = new URL(window.location.href);
            url.searchParams.set('usia', 'semua');
```
dengan:
```js
    document.querySelectorAll('[data-registri-status]').forEach((link) => {
        link.addEventListener('click', () => {
            // K4 menghitung balita 0–59 bln: pilih lingkup yang sama sebelum melihat rinciannya
            // (jumlah registri = angka kartu; dikunci KesmasSwarmAgregatTest).
            const k4 = 'balita_0_59';
            document.querySelectorAll('.km-chip[data-usia]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.usia === k4)));
            state.usia = k4;
            usiaHidden.value = k4;
            const url = new URL(window.location.href);
            url.searchParams.set('usia', k4);
```
Run: `node --check public/js/kesmas-registri.js` → tanpa keluaran.

`public/css/kesmas-dashboard.css` — tambahkan di akhir berkas:
```css
.km-k1-rincian{ margin-top:.35rem; font-size:.75rem; color:var(--muted); }
.km-hbig{ margin:.6rem 0 0; font-size:.78rem; color:var(--muted); }
.km-hbig b{ color:var(--ink); }
```

`e2e/kesmas-dashboard.spec.ts` — ganti:
```ts
  expect(requests.at(-1)?.get('usia')).toBe('semua');
```
dengan:
```ts
  expect(requests.at(-1)?.get('usia')).toBe('balita_0_59');
```

`e2e/kesmas-swarm.spec.ts`:
- ganti `test('registri nyata: paginasi, lima usia, pencarian` dengan `test('registri nyata: paginasi, enam usia, pencarian`.
- ganti:
```ts
  for (const [age, min, max] of [['bayi', 0, 11], ['baduta', 12, 23], ['balita', 24, 59], ['prasekolah', 60, 72], ['semua', 0, 72]] as const) {
```
dengan:
```ts
  for (const [age, min, max] of [['bayi', 0, 11], ['baduta', 12, 23], ['balita', 24, 59], ['prasekolah', 60, 72], ['balita_0_59', 0, 59], ['semua', 0, 72]] as const) {
```
- ganti:
```ts
    if (age !== 'semua') await expect(page.locator(`#sdidtkRows [data-usia="${age}"]`)).toHaveClass(/hl/);
```
dengan:
```ts
    if (age !== 'semua' && age !== 'balita_0_59') await expect(page.locator(`#sdidtkRows [data-usia="${age}"]`)).toHaveClass(/hl/);
```
- ganti:
```ts
  expect(attention.params.get('usia')).toBe('semua');
```
dengan:
```ts
  expect(attention.params.get('usia')).toBe('balita_0_59');
```

- [ ] **Step 5: Jalankan dan pastikan lulus**

```bash
php artisan view:clear
php artisan test --filter='KesmasDashboardServiceTest|KesmasSwarmAgregatTest|KesmasDashboardControllerTest|KesmasDashboardBladeTest|KesmasDashboardMemoriTest'
```
Expected: PASS semua. (E2E dijalankan di Task 11 setelah DB dev ditandai.)

- [ ] **Step 6: Commit**

```bash
git add app/Services/KesmasDashboardService.php resources/views/admin/kesmas/partials/_spm.blade.php resources/views/admin/kesmas/partials/_sdidtk-ckg-idl.blade.php resources/views/admin/kesmas/partials/_layanan.blade.php resources/views/admin/kesmas/dashboard.blade.php public/js/kesmas-registri.js public/css/kesmas-dashboard.css tests/Feature/Kesmas/KesmasDashboardServiceTest.php tests/Feature/Kesmas/KesmasSwarmAgregatTest.php tests/Feature/Kesmas/KesmasDashboardControllerTest.php e2e/kesmas-dashboard.spec.ts e2e/kesmas-swarm.spec.ts
git commit -m "feat(kesmas): K4 0–59 bln dengan chip registri, label kartu sesuai indikator klien, dan jumlah HBIG"
```

---

### Task 11: Verifikasi menyeluruh, pagar modul lain, dokumentasi

**Files:**
- Create: `e2e/satuan-bb.spec.ts`
- Create: `docs/kesmas-pemetaan-permintaan-data.md`
- Modify: `CLAUDE.md` (tambah tiga bagian di akhir)

**Interfaces:**
- Consumes: seluruh hasil Task 1–10.
- Produces: bukti verifikasi (log di `storage/logs/`), lampiran klien, catatan jebakan.

- [ ] **Step 1: Pagar diff — tidak ada berkas di luar daftar izin**

```bash
cd /d/apps/laragon/www/sirindu
git diff --name-only main...HEAD | while IFS= read -r f; do
  case "$f" in
    database/migrations/2026_10_02_000001_*|database/migrations/2026_10_02_000002_*) ;;
    app/Models/SasaranKesmasLog.php|app/Support/TahunSasaranKesmas.php|app/Support/SatuanBeratBadan.php) ;;
    app/Console/Commands/TandaiSasaranKesmas.php) ;;
    app/Http/Requests/Admin/Anak/KesmasRules.php|app/Http/Requests/Admin/Anak/storeAnakRequest.php|app/Http/Requests/Admin/Anak/AturanBeratBadan.php) ;;
    app/Http/Controllers/AdminController.php|app/Repositories/Admin/Anak/AnakRepository.php) ;;
    app/Services/KesmasDashboardService.php|app/Services/KesmasPresenter.php|app/Http/Controllers/KesmasDashboardController.php|app/Exports/KesmasAnakSheet.php) ;;
    resources/views/admin/anak/create.blade.php|resources/views/admin/anak/edit.blade.php|resources/views/admin/anak/data-anak.blade.php|resources/views/admin/anak/show.blade.php|resources/views/admin/anak/partials/form-riwayat-lahir.blade.php) ;;
    resources/views/admin/kesmas/dashboard.blade.php|resources/views/admin/kesmas/partials/_spm.blade.php|resources/views/admin/kesmas/partials/_sdidtk-ckg-idl.blade.php|resources/views/admin/kesmas/partials/_layanan.blade.php) ;;
    public/js/kesmas-registri.js|public/js/satuan-bb.js|public/css/kesmas-dashboard.css) ;;
    tests/Unit/Support/*|tests/Unit/KesmasPresenterTest.php|tests/Feature/Kesmas/*) ;;
    e2e/kesmas-dashboard.spec.ts|e2e/kesmas-swarm.spec.ts|e2e/satuan-bb.spec.ts) ;;
    CLAUDE.md|docs/*) ;;
    *) echo "DI LUAR DAFTAR IZIN: $f" ;;
  esac
done
```
Expected: **tanpa keluaran**. Ada baris "DI LUAR DAFTAR IZIN" → berhenti, tinjau, kembalikan perubahan itu.

Tinjau manual dua berkas bersama-modul:
```bash
git diff main...HEAD -- app/Http/Controllers/AdminController.php
git diff main...HEAD -- app/Repositories/Admin/Anak/AnakRepository.php
```
Expected: perubahan `AdminController` hanya di baris `use`, `updateAnak`, `storeDataAnak`, `updateDataAnak`; `AnakRepository` hanya di `use`, `storeAnak`/`simpanAnakBaru`, `updateAnak`/`ubahAnak`, dan blok helper Sasaran.

- [ ] **Step 2: Pemeriksaan statis**

```bash
for f in $(git diff --name-only main...HEAD -- '*.php'); do php -l "$f" | grep -v "No syntax errors" ; done
node --check public/js/satuan-bb.js && node --check public/js/kesmas-registri.js
git diff --check main...HEAD
php artisan view:clear && php artisan view:cache && php artisan view:clear
```
Expected: tanpa galat sintaks, tanpa whitespace error, `view:cache` sukses.

- [ ] **Step 3: Regresi — satu proses berurutan (jangan paralel)**

```bash
php artisan test --filter='Kesmas|TahunSasaranKesmas|SatuanBeratBadan|FormSasaranKesmas|FormHbig|TandaiSasaranKesmas' --compact > storage/logs/kesmas-permintaan-kesmas.log 2>&1; tail -4 storage/logs/kesmas-permintaan-kesmas.log
php artisan test tests/Feature/Imunisasi --compact > storage/logs/kesmas-permintaan-imunisasi.log 2>&1; tail -4 storage/logs/kesmas-permintaan-imunisasi.log
php artisan test tests/Feature/PrioritasGizi --compact > storage/logs/kesmas-permintaan-prioritas.log 2>&1; tail -4 storage/logs/kesmas-permintaan-prioritas.log
php artisan test --filter='Timbang|OperasiTimbang|ExportAllDataMemori|CapilDedup|KohortImunisasi|BackfillPosyandu' --compact > storage/logs/kesmas-permintaan-ot.log 2>&1; tail -4 storage/logs/kesmas-permintaan-ot.log
```
Expected: semua PASS. Gagal di suite non-Kesmas → modul lain tersenggol: berhenti dan laporkan dengan keluarannya.

Lalu suite penuh di latar belakang (> 10 menit, tunggu notifikasi selesai; jangan jalankan tes lain selama itu):
```bash
php artisan test --compact > storage/logs/kesmas-permintaan-suite.log 2>&1; tail -6 storage/logs/kesmas-permintaan-suite.log
```
Expected: semua PASS.

- [ ] **Step 4: E2E satuan BB**

Create `e2e/satuan-bb.spec.ts`:
```ts
import { test, expect } from '@playwright/test';

test.use({ storageState: 'e2e/.auth/superadmin.json' });

const iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

test('label & nilai BB mengikuti tanggal kunjungan terhadap batas dari server', async ({ page }) => {
  const res = await page.request.get('/admin/kesmas-dashboard/api/registri?usia=semua');
  expect(res.ok()).toBe(true);
  const anak = (await res.json()).data[0];
  expect(anak, 'DB dev butuh minimal satu anak bertanda Sasaran Balita Kesmas').toBeTruthy();
  const hash = anak.url_detail.split('/').pop();

  await page.goto(`/admin/data-anak/${hash}`);
  const bb = page.locator('#bb');
  const label = page.locator('[data-satuan-label="bb"]');
  const batas = await bb.getAttribute('data-batas-gram');
  expect(batas).toMatch(/^\d{4}-\d{2}-\d{2}$/);
  const sebelum = new Date(`${batas}T00:00:00`);
  sebelum.setDate(sebelum.getDate() - 1);

  await page.locator('#tgl_kunjungan').fill(iso(sebelum));
  await expect(label).toHaveText('(gram)');
  await bb.fill('3250');

  await page.locator('#tgl_kunjungan').fill(batas!);
  await expect(label).toHaveText('(kg)');
  await expect(bb).toHaveValue('3.25');
  await expect(page.locator('[data-satuan-info="bb"]')).toContainText('kg');

  await page.locator('#tgl_kunjungan').fill(iso(sebelum));
  await expect(label).toHaveText('(gram)');
  await expect(bb).toHaveValue('3250');
});
```

Siapkan DB dev dan server, lalu jalankan:
```bash
php artisan kesmas:tandai-sasaran --semua
php artisan kesmas:tandai-sasaran --semua --jalankan --alasan="Dev lokal: data uji e2e dasbor Kesmas"
php artisan view:clear
curl -s -o /dev/null -w "%{http_code}\n" http://sirindu.test/login
```
Expected: dry-run menampilkan rekap; `--jalankan` mencetak "Kode batch"; curl `200`. Bila curl gagal (Apache mati), jalankan `php artisan serve --port=8123` di latar belakang dan pakai `PLAYWRIGHT_BASE_URL=http://127.0.0.1:8123` pada perintah berikut.

```bash
npx playwright test e2e/kesmas-dashboard.spec.ts e2e/kesmas-swarm.spec.ts e2e/satuan-bb.spec.ts --project=chromium --no-deps --workers=1 > storage/logs/kesmas-permintaan-browser.log 2>&1; tail -15 storage/logs/kesmas-permintaan-browser.log
```
Expected: semua lulus. Bila tes dialihkan ke halaman login (sesi `e2e/.auth/superadmin.json` kedaluwarsa), ulangi **tanpa** `--no-deps` agar project `setup` login ulang.

Cek visual singkat (Playwright atau Chrome) di lebar 375 px: `/admin/kesmas-dashboard` (baris penandaan tidak meluber, chip "Semua balita (0–59)" terlihat), `/admin/data-anak/{hash}` (label satuan BB), detail anak (kartu Sasaran Kesmas).

- [ ] **Step 5: Dokumentasi**

`CLAUDE.md` — tambahkan di akhir berkas:
```markdown

### Sasaran Balita Kesmas: opt-in, NULL tetap NULL saat edit, penandaan massal lewat query builder

Dasbor Kesmas (spec `docs/superpowers/specs/2026-10-02-kesmas-permintaan-data-design.md`) hanya menghitung
`anak.sasaran_balita_kesmas = 1`. NULL = belum pernah ditandai, 0 = dilepas; keduanya **tidak** dihitung.

- Form **Edit Anak** merender NULL sebagai checkbox tak tercentang, jadi `'0'` dari anak NULL **bukan
  keputusan** — `AnakRepository::sasaranEdit()` membiarkannya NULL. Kalau ditulis 0, membetulkan nama anak
  lama diam-diam membuatnya "dilepas" dan perintah massal tak lagi menjangkaunya. Tambah Anak (default
  tercentang) menyimpan `'0'` apa adanya.
- **Penandaan massal hanya lewat `php artisan kesmas:tandai-sasaran`** (dry-run bawaan, `--jalankan
  --alasan="…"`, `--batalkan=<batch>`; jejak di `sasaran_kesmas_log`). Perintah itu wajib `DB::table()`
  tanpa `updated_at`: `AnakObserver::saved` memicu refresh prioritas gizi (OT) per anak, dan
  `CapilDedupService::sigiziUntouched()` membaca `updated_at = created_at` sebagai "belum tersentuh Capil".
  Jangan "menyederhanakannya" jadi `Anak::query()->update()` atau loop `save()`.
- Anak dari import (OT, Kohort, Capil) masuk NULL → tidak terhitung sampai ditandai.
- **Fixture tes dasbor Kesmas wajib bertanda 1.** Tanpa itu populasinya kosong dan tes — terutama
  `KesmasDashboardMemoriTest` — lolos palsu (memori kecil karena tak ada yang dihitung).
- Kartu/chip IDL & IBL di dasbor Kesmas tetap dari `ImunisasiStatusService` dan **tidak** memakai tanda ini.

### BB < 2 bulan diinput dalam gram — hanya di form pengukuran, yang disimpan tetap kg

`data_anak.bb` selalu kg (dibaca z-score, `PrioritasGiziService`, `OtGiziService`, dasbor, importer). Di
Tambah Data Pengukuran dan form per kunjungan Edit Anak, satuan input = gram bila
`tgl_kunjungan < tgl_lahir + 2 bulan` (`addMonthsNoOverflow` — 31 Des → 28/29 Feb; tepat di batas = kg),
diputuskan **server** (`App\Support\SatuanBeratBadan`, `App\Http\Requests\Admin\Anak\AturanBeratBadan`).
`public/js/satuan-bb.js` hanya membandingkan tanggal dengan `data-batas-gram` dari server — jangan menambah
aritmetika bulan di JS.

- Rentang gram 300–8.000 dan kg 1–150 sengaja **tidak beririsan** supaya salah satuan pasti ditolak.
- Di `updateDataAnak`, rentang hanya dicek bila nilai **berubah** — baris placeholder import imunisasi
  (`bb = 0`) dan data lama ganjil harus tetap bisa disimpan.
- Form identitas Tambah/Edit Anak dan BBL tetap kg (keputusan pemilik produk). Importer tidak disentuh.

### Tahun sasaran Kesmas ≠ kohort imunisasi

`App\Support\TahunSasaranKesmas` = rumus klien: tahun lahir +1 (IDL), +2 (24 bln), +3 (IBL), +4, +5, +6 —
kalender murni, hanya tampilan (detail anak, Export Kesmas). `KohortImunisasi` memakai cut-off
1 Apr–31 Mar dan menilai IBL di tahun Baduta (≈ +2). Perbedaan ini disengaja dan tertulis di halaman;
jangan "menyamakan" salah satunya tanpa keputusan pemilik produk.
```

Create `docs/kesmas-pemetaan-permintaan-data.md`:
```markdown
# Pemetaan Lembar "PERMINTAAN DATA" ke SIRINDU

Tanggal: 2 Oktober 2026 · Untuk: tim Kesmas Dinas Kesehatan · Tujuan: membantu mengisi kolom E
("sudah di tindak lanjuti").

| Permintaan | Status | Letak di aplikasi | Catatan |
|---|---|---|---|
| Imunisasi HBIG (tanggal) | Ditindaklanjuti | Edit Anak → kartu "Riwayat Kelahiran & Skrining Neonatal"; detail anak; Export Kesmas; Dasbor Kesmas panel Skrining neonatal | Bukan bagian IDL. Dasbor menampilkan jumlah, bukan persen, karena status HBsAg ibu tidak tercatat |
| Penandaan Sasaran Balita | Ditindaklanjuti | Centang "Sasaran Balita Kesmas" di Tambah/Edit Anak | Hanya anak bercentang yang dihitung di Dasbor Kesmas. Anak lama ditandai massal sesuai kriteria Dinkes |
| Rumus IDL, 24 bln, IBL, 48, 60, 72 bln | Ditindaklanjuti | Detail anak (kartu Sasaran Kesmas); Export Kesmas | Tahun lahir +1 … +6 |
| Satuan BB (< 2 bln gram, ≥ 2 bln kg, wajib) | Ditindaklanjuti | Tambah Data Pengukuran; edit per kunjungan | Form identitas Tambah/Edit Anak tetap kg; data disimpan dan ditampilkan dalam kg |
| Pelayanan Kesehatan Balita (0–5 th) | Sudah ada | Dasbor Kesmas, kartu 1 | Gabungan bayi 0–11 bln + anak balita 12–59 bln, kini dengan rinciannya |
| Balita Dilayani Tumbuh Kembang (0–60 bln) | Disesuaikan | Dasbor Kesmas, kartu 4 | Dihitung 0–59 bln (di bawah 60 bulan), min. 8× timbang + 2× DDTKA setahun (prorata per periode) |
| Cakupan SDIDTK 0–72 dan kelompok 0–11, 12–23, 24–59, 60–72 | Sudah ada | Dasbor Kesmas, blok SDIDTK | Total = penjumlahan keempat kelompok |

## Cara memakai

- **Menandai sasaran:** buka Edit Anak → centang "Sasaran Balita Kesmas" → Simpan. Anak baru otomatis
  tercentang di Tambah Anak; lepaskan centang bila anak bukan sasaran (mis. bukan warga wilayah).
- **Penandaan massal anak lama:** sampaikan kriteria ke admin (wilayah, rentang tanggal lahir, perlakuan
  anak pindah/meninggal). Admin menjalankan penandaan dan bisa membatalkannya bila kriteria keliru.
- **Berat badan bayi < 2 bulan:** isi dalam gram (mis. 3250). Label di samping kolom berubah sendiri
  mengikuti tanggal kunjungan.

## Mohon konfirmasi

1. **Posisi IBL dalam rumus.** Lembar menulis IBL = tahun lahir + 3, sedangkan Dasbor Imunisasi menilai IBL
   pada tahun Baduta (± tahun lahir + 2). Aplikasi mengikuti lembar; mohon konfirmasi bila yang dimaksud +2.
2. **"0–60 bulan" pada kartu Tumbuh Kembang** dibaca "di bawah 60 bulan" (0–59), sama dengan definisi balita.
```

```bash
git add -f docs/kesmas-pemetaan-permintaan-data.md
git add CLAUDE.md e2e/satuan-bb.spec.ts
git commit -m "docs(kesmas): catatan jebakan tanda sasaran & satuan BB, lampiran pemetaan untuk klien, e2e satuan BB"
```

- [ ] **Step 6: Ringkas hasil untuk pemilik produk**

Laporkan: daftar commit (`git log --oneline main..HEAD`), hasil tiap log tes (`tail` dari Step 3–4), keluaran pagar diff (kosong), dan langkah rilis prod yang **belum** dijalankan (spec §9: ukur prod read-only, kriteria Dinkes, `mysqldump`, `migrate --force`, penandaan massal dalam sesi yang sama, bandingkan angka dasbor imunisasi & OT sebelum/sesudah).

---

## Setelah rencana ini: rilis

Rilis ke prod **tidak** termasuk rencana ini — ikuti spec §9 (pengukuran read-only, kriteria Dinkes, `mysqldump`, `git pull`, `php artisan migrate --force`, penandaan massal langsung dalam sesi yang sama, perbandingan angka dasbor imunisasi & OT sebelum/sesudah, rollback lewat `--batalkan` atau revert + `migrate:rollback --step=2`).

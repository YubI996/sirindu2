# Import Data Kesmas Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tipe import baru "Data Kesmas" di halaman Import CSV yang mengisi field Kesmas (per anak & per kunjungan) untuk anak yang sudah ada dari berkas Excel.

**Architecture:** Tiga unit kecil yang bisa dites terpisah — `NilaiSelKesmas` (sel → nilai, murni), `KolomKesmasImport` (konversi + validasi per kolom memakai `KesmasRules`), `PenautanAnakKesmas` (cari anak, tak pernah membuat) — dirangkai `KesmasImport` (Maatwebsite, sheet pertama saja). Kerangka upload/riwayat/reimport/format tanggal memakai `ImportCsvController` + `import_logs` yang ada; template `.xlsx` dibangun saat diunduh dari `config('kesmas')`.

**Tech Stack:** Laravel 12, Maatwebsite Excel 3 (PhpSpreadsheet), PHPUnit, MySQL (`sirindu_testing`).

**Spec:** `docs/superpowers/specs/2026-10-15-import-kesmas-design.md`

## Global Constraints

- Import **hanya mengisi anak yang sudah ada**: tidak membuat anak, tidak pernah mengubah `anak.nik`.
- Field Kesmas: "isi yang diberikan" — sel berisi menimpa, sel kosong tidak menulis apa pun (tidak pernah menulis NULL).
- Ukuran (`bb`,`tb`,`posisi`,`lk`,`lla`,`zscore_*`) kunjungan yang **sudah ada** tidak disentuh; bentrok → `[PERINGATAN]`.
- `anak.sasaran_balita_kesmas` tidak ditulis import ini.
- BB: `SatuanBeratBadan::untuk(anak.tgl_lahir, tgl_kunjungan)` — gram 300–8.000 bila < 2 bulan, selain itu kg 1–150; disimpan kg.
- Pesan: `[ERROR]` / `[PERINGATAN]` / `[INFO] Baris N (nama): …`, N = nomor baris Excel (header = baris 1). Tanpa baris ringkasan tambahan (sama dengan `AnakImport::getResults()`).
- Format tanggal: `auto|dmy|mdy` lewat trait `MembacaTanggalBerkas`; JANGAN `Carbon::parse` untuk sel berkas.
- Berkas: hanya `.xlsx`/`.xls`, maks 20 MB; hanya sheet pertama yang dibaca.
- Akses: hanya superadmin (`isSuperAdmin()`), sama dengan seluruh `ImportCsvController`.
- Daftar kolom Kesmas diturunkan dari `array_keys(KesmasRules::anak())` / `array_keys(KesmasRules::kunjungan())` — jangan ditulis ulang.
- Tes: `PHP=/d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe; "$PHP" artisan test --filter=<Nama>`; jangan jalankan dua proses tes bersamaan (DB `sirindu_testing` dipakai bersama). MySQL Laragon harus menyala.

## Review Focus

- BB bayi ditulis dengan titik ribuan (`3.250` gram) → terbaca 3,25 → harus DITOLAK (di luar 300–8.000 g), bukan tersimpan 3,25 kg. Tes di Task 5.
- NIK diketik sebagai sel angka Excel (float `6474010101230001`) → harus tetap cocok lewat NIK, bukan jadi `6.47E+15`. Tes di Task 1 dan Task 4.
- Anak yang sama di dua baris, satu baris gagal → baris lainnya tetap tersimpan (transaksi per baris). Tes di Task 5.
- Baris yang hanya berisi identitas tanpa isian apa pun → `[PERINGATAN]` dan tidak dihitung berhasil. Tes di Task 4.
- Header beda huruf/berspasi (` Nama_Anak `) → tetap dikenali. Tes di Task 4.

---

### Task 1: `NilaiSelKesmas` — pengubah sel murni

**Files:**
- Create: `app/Support/NilaiSelKesmas.php`
- Test: `tests/Unit/Support/NilaiSelKesmasTest.php`

**Interfaces:**
- Produces (semua `public static`, tanpa DB/`config()`/`now()`):
  - `kosong(mixed $sel): bool`
  - `teks(mixed $sel): string`
  - `yaTidak(mixed $sel): ?int` — 1/0, null = tak sah
  - `pilihan(mixed $sel, array $daftar): ?string` — ejaan dari `$daftar`
  - `pilihanBerkunci(mixed $sel, array $kunciLabel): ?string` — mengembalikan kunci
  - `angka(mixed $sel): ?float`
  - `nik(mixed $sel): string` — '' bila kosong
  - `jk(mixed $sel): ?string` — 'L'|'P'
  - `posisi(mixed $sel): ?string` — 'H'|'L'

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php

namespace Tests\Unit\Support;

use App\Support\NilaiSelKesmas as N;
use PHPUnit\Framework\TestCase;

class NilaiSelKesmasTest extends TestCase
{
    public function test_kosong_mengenali_null_string_kosong_dan_spasi(): void
    {
        $this->assertTrue(N::kosong(null));
        $this->assertTrue(N::kosong(''));
        $this->assertTrue(N::kosong('   '));
        $this->assertFalse(N::kosong(0));
        $this->assertFalse(N::kosong('0'));
    }

    public function test_ya_tidak_tanpa_beda_huruf(): void
    {
        foreach (['Ya', 'YA', 'y', '1', 1, 'true'] as $v) {
            $this->assertSame(1, N::yaTidak($v), var_export($v, true));
        }
        foreach (['Tidak', 'tidak', 'T', '0', 0, 'false'] as $v) {
            $this->assertSame(0, N::yaTidak($v), var_export($v, true));
        }
        $this->assertNull(N::yaTidak('mungkin'));
    }

    public function test_pilihan_dicocokkan_tanpa_beda_huruf_dan_disimpan_dengan_ejaan_daftar(): void
    {
        $this->assertSame('Bidan', N::pilihan(' bidan ', ['Dokter Umum', 'Bidan']));
        $this->assertNull(N::pilihan('Dukun', ['Dokter Umum', 'Bidan']));
    }

    public function test_pilihan_berkunci_menerima_kunci_atau_label(): void
    {
        $skrining = ['belum' => 'Belum', 'normal' => 'Normal', 'tidak_normal' => 'Tidak normal'];
        $this->assertSame('tidak_normal', N::pilihanBerkunci('Tidak Normal', $skrining));
        $this->assertSame('tidak_normal', N::pilihanBerkunci('tidak_normal', $skrining));
        $this->assertNull(N::pilihanBerkunci('abnormal', $skrining));
    }

    public function test_angka_menerima_koma_desimal_dan_sel_angka(): void
    {
        $this->assertSame(3.25, N::angka('3,25'));
        $this->assertSame(10.5, N::angka(10.5));
        $this->assertSame(3250.0, N::angka(3250));
        $this->assertSame(3.25, N::angka('3.250'));   // titik dibaca desimal — rentang gram yang menolaknya (Task 5)
        $this->assertNull(N::angka('sepuluh'));
    }

    public function test_nik_dari_sel_angka_excel_tidak_jadi_notasi_ilmiah(): void
    {
        $this->assertSame('6474010101230001', N::nik(6474010101230001.0));
        $this->assertSame('6474010101230001', N::nik(6474010101230001));
        $this->assertSame('6474010101230001', N::nik(" '6474010101230001 "));
        $this->assertSame('', N::nik(null));
        $this->assertSame('', N::nik('-'));
    }

    public function test_jk_dan_posisi(): void
    {
        $this->assertSame('L', N::jk('l'));
        $this->assertSame('P', N::jk('Perempuan'));
        $this->assertNull(N::jk('X'));
        $this->assertSame('H', N::posisi('Berdiri'));
        $this->assertSame('L', N::posisi('terlentang'));
        $this->assertNull(N::posisi('duduk'));
        $this->assertNull(N::posisi(''));
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `"$PHP" artisan test --filter=NilaiSelKesmasTest`
Expected: FAIL — `Class "App\Support\NilaiSelKesmas" not found`

- [ ] **Step 3: Implementasi**

```php
<?php

namespace App\Support;

/**
 * Pengubah sel berkas Import Data Kesmas jadi nilai kolom (spec 2026-10-15-import-kesmas-design.md §3).
 * Murni: tanpa DB, config(), atau now() — daftar pilihan diteruskan pemanggil.
 * Pengubah mengembalikan null untuk sel yang TAK SAH; pemanggil memeriksa kosong() lebih dulu.
 */
final class NilaiSelKesmas
{
    private const YA = ['ya', 'y', '1', 'true'];
    private const TIDAK = ['tidak', 't', '0', 'false'];
    private const BERDIRI = ['h', 'berdiri', 'tinggi', 'tinggi badan', 'tb'];
    private const TERLENTANG = ['l', 'terlentang', 'telentang', 'berbaring', 'panjang', 'panjang badan', 'pb'];

    public static function kosong(mixed $sel): bool
    {
        return $sel === null || (is_string($sel) && trim($sel) === '');
    }

    public static function teks(mixed $sel): string
    {
        return trim((string) $sel);
    }

    public static function yaTidak(mixed $sel): ?int
    {
        if (is_bool($sel)) {
            return (int) $sel;
        }
        $v = mb_strtolower(self::teks($sel));

        return in_array($v, self::YA, true) ? 1 : (in_array($v, self::TIDAK, true) ? 0 : null);
    }

    /** @param string[] $daftar */
    public static function pilihan(mixed $sel, array $daftar): ?string
    {
        $v = mb_strtolower(self::teks($sel));
        foreach ($daftar as $opsi) {
            if (mb_strtolower($opsi) === $v) {
                return $opsi;
            }
        }

        return null;
    }

    /** @param array<string,string> $kunciLabel kunci => label, mis. config('kesmas.skrining') */
    public static function pilihanBerkunci(mixed $sel, array $kunciLabel): ?string
    {
        $v = mb_strtolower(self::teks($sel));
        foreach ($kunciLabel as $kunci => $label) {
            if ($v === mb_strtolower($kunci) || $v === mb_strtolower($label)) {
                return $kunci;
            }
        }

        return null;
    }

    /** Koma desimal diterima ('3,25'). Titik selalu desimal — '3.250' = 3,25. */
    public static function angka(mixed $sel): ?float
    {
        if (is_int($sel) || is_float($sel)) {
            return (float) $sel;
        }
        $v = str_replace(' ', '', self::teks($sel));
        if (preg_match('/^-?\d+,\d+$/', $v)) {
            $v = str_replace(',', '.', $v);
        }

        return is_numeric($v) ? (float) $v : null;
    }

    /** NIK dari sel: sel angka Excel ditulis ulang tanpa notasi ilmiah, selain digit dibuang. */
    public static function nik(mixed $sel): string
    {
        if (is_int($sel) || is_float($sel)) {
            return sprintf('%.0f', $sel);
        }

        return preg_replace('/\D/', '', self::teks($sel)) ?? '';
    }

    public static function jk(mixed $sel): ?string
    {
        $v = mb_strtolower(self::teks($sel));

        return match (true) {
            in_array($v, ['l', 'laki-laki', 'laki laki', '1'], true) => 'L',
            in_array($v, ['p', 'perempuan', '2'], true) => 'P',
            default => null,
        };
    }

    /** 'H' = berdiri, 'L' = terlentang (kanonik z_score()). Kosong/tak dikenal → null, BUKAN default 'L'. */
    public static function posisi(mixed $sel): ?string
    {
        $v = mb_strtolower(self::teks($sel));

        return in_array($v, self::BERDIRI, true) ? 'H' : (in_array($v, self::TERLENTANG, true) ? 'L' : null);
    }
}
```

- [ ] **Step 4: Jalankan, pastikan lolos**

Run: `"$PHP" artisan test --filter=NilaiSelKesmasTest`
Expected: PASS (7 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Support/NilaiSelKesmas.php tests/Unit/Support/NilaiSelKesmasTest.php
git commit -m "feat(import-kesmas): pengubah sel berkas Kesmas"
```

---

### Task 2: `KolomKesmasImport` — konversi + validasi per kolom

**Files:**
- Create: `app/Services/KolomKesmasImport.php`
- Test: `tests/Feature/ImportKesmas/KolomKesmasImportTest.php`

**Interfaces:**
- Consumes: `NilaiSelKesmas` (Task 1); `KesmasRules::anak(?string $penolongLama)`, `KesmasRules::kunjungan()`, `KesmasRules::pesan(array)`, `KesmasRules::atribut()`, `KesmasRules::BOOL_ANAK`; `config('kesmas')`.
- Produces:
  - `KolomKesmasImport::kolom(string $tingkat): string[]` — `$tingkat` = `'anak'|'kunjungan'`
  - `KolomKesmasImport::pilihan(string $kolom): ?string[]` — pilihan tampil (untuk template), null = bebas
  - `KolomKesmasImport::ubah(string $tingkat, array $sel, Anak $anak, callable $bacaTanggal): array{nilai: array<string,mixed>, salah: array<string,string>}` — `$sel` hanya berisi sel TIDAK kosong; `salah[kolom]` = kalimat lengkap tanpa titik akhir.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php

namespace Tests\Feature\ImportKesmas;

use App\Models\Anak;
use App\Services\KolomKesmasImport;
use App\Support\TanggalBerkas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KolomKesmasImportTest extends TestCase
{
    use RefreshDatabase;

    private function anak(array $isi = []): Anak
    {
        return Anak::create(array_merge([
            'nama' => 'Budi Santoso', 'nik' => '6474010101230001', 'jk' => 1,
            'tgl_lahir' => '2023-01-01', 'status' => 1, 'nama_ibu' => 'Siti Aminah',
        ], $isi));
    }

    private function ubah(string $tingkat, array $sel, ?Anak $anak = null): array
    {
        return KolomKesmasImport::ubah($tingkat, $sel, $anak ?? $this->anak(), fn ($v) => TanggalBerkas::parse($v, 'dmy'));
    }

    public function test_daftar_kolom_diturunkan_dari_kesmas_rules(): void
    {
        $this->assertContains('air_bersih', KolomKesmasImport::kolom('anak'));
        $this->assertContains('tgl_hbig', KolomKesmasImport::kolom('anak'));
        $this->assertNotContains('sasaran_balita_kesmas', KolomKesmasImport::kolom('anak'));
        $this->assertContains('mbg', KolomKesmasImport::kolom('kunjungan'));
        $this->assertContains('rujukan', KolomKesmasImport::kolom('kunjungan'));
    }

    public function test_pilihan_untuk_template(): void
    {
        $this->assertSame(['Ya', 'Tidak'], KolomKesmasImport::pilihan('air_bersih'));
        $this->assertSame(['Ya', 'Tidak'], KolomKesmasImport::pilihan('kn1'));
        $this->assertSame(['Belum', 'Normal', 'Tidak normal'], KolomKesmasImport::pilihan('skrining_shk'));
        $this->assertContains('Bidan', KolomKesmasImport::pilihan('penolong_lahir'));
        $this->assertNull(KolomKesmasImport::pilihan('fktp_bpjs'));
    }

    public function test_mengubah_tiap_jenis_kolom_anak(): void
    {
        $hasil = $this->ubah('anak', [
            'air_bersih' => 'ya', 'penolong_lahir' => 'BIDAN', 'skrining_shk' => 'Tidak normal',
            'bbl' => '3,1', 'tgl_hbig' => '05/01/2023', 'fktp_bpjs' => ' Puskesmas Bontang Utara 1 ',
        ]);

        $this->assertSame([], $hasil['salah']);
        $this->assertSame([
            'air_bersih' => 1, 'penolong_lahir' => 'Bidan', 'skrining_shk' => 'tidak_normal',
            'bbl' => 3.1, 'tgl_hbig' => '2023-01-05', 'fktp_bpjs' => 'Puskesmas Bontang Utara 1',
        ], $hasil['nilai']);
    }

    public function test_sel_tak_dikenal_masuk_salah_dengan_label_dan_pilihan(): void
    {
        $hasil = $this->ubah('anak', ['penolong_lahir' => 'Dukun', 'air_bersih' => 'Ya']);

        $this->assertSame(['air_bersih' => 1], $hasil['nilai']);
        $this->assertStringContainsString('Penolong persalinan', $hasil['salah']['penolong_lahir']);
        $this->assertStringContainsString("'Dukun'", $hasil['salah']['penolong_lahir']);
        $this->assertStringContainsString('Bidan', $hasil['salah']['penolong_lahir']);
    }

    public function test_aturan_kesmas_rules_tetap_berlaku(): void
    {
        // tgl_hbig sebelum tanggal lahir & usia kehamilan di luar 20–45 ditolak oleh KesmasRules.
        $hasil = $this->ubah('anak', ['tgl_hbig' => '01/12/2022', 'usia_kehamilan_lahir' => '50', 'imd' => 'Tidak']);

        $this->assertSame(['imd' => 0], $hasil['nilai']);
        $this->assertArrayHasKey('tgl_hbig', $hasil['salah']);
        $this->assertStringContainsString('Usia kehamilan saat lahir', $hasil['salah']['usia_kehamilan_lahir']);
    }

    public function test_penolong_lama_di_luar_daftar_tetap_sah(): void
    {
        $anak = $this->anak(['penolong_lahir' => 'Dukun Terlatih']);

        $hasil = $this->ubah('anak', ['penolong_lahir' => 'dukun terlatih'], $anak);

        $this->assertSame(['penolong_lahir' => 'Dukun Terlatih'], $hasil['nilai']);
    }

    public function test_kolom_kunjungan(): void
    {
        $hasil = $this->ubah('kunjungan', ['mbg' => 'Ya', 'rujukan' => 'Puskesmas X', 'tgl_penanda_ckg' => '10/03/2025']);

        $this->assertSame(['mbg' => 1, 'tgl_penanda_ckg' => '2025-03-10'], $hasil['nilai']);
        $this->assertStringContainsString('Rujukan', $hasil['salah']['rujukan']);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `"$PHP" artisan test --filter=KolomKesmasImportTest`
Expected: FAIL — `Class "App\Services\KolomKesmasImport" not found`

- [ ] **Step 3: Implementasi**

```php
<?php

namespace App\Services;

use App\Http\Requests\Admin\Anak\KesmasRules;
use App\Models\Anak;
use App\Support\NilaiSelKesmas;
use Illuminate\Support\Facades\Validator;

/**
 * Kolom Kesmas di Import Data Kesmas (spec 2026-10-15-import-kesmas-design.md §3): sel → nilai, lalu
 * divalidasi dengan KesmasRules — sumber yang sama dengan form. Sel tak sah hanya membatalkan kolom itu.
 */
final class KolomKesmasImport
{
    /** Pilihan berlabel tanpa kunci: kolom => kunci config('kesmas'). */
    private const PILIHAN = [
        'status_tk_paud' => 'status_tk_paud', 'jenis_persalinan' => 'jenis_persalinan',
        'penolong_lahir' => 'penolong_lahir', 'pemeriksaan_gigi' => 'pemeriksaan_gigi', 'rujukan' => 'rujukan',
    ];

    /** Pilihan berkunci (enum di DB): kolom => kunci config('kesmas'). */
    private const PILIHAN_KUNCI = [
        'skrining_shk' => 'skrining', 'skrining_shak' => 'skrining', 'skrining_g6pd' => 'skrining',
        'pemeriksaan_hepatitis_b' => 'hepatitis_b',
    ];

    private const TANGGAL = ['tgl_hbig', 'tgl_penanda_ckg'];
    private const ANGKA = ['bbl', 'pbl', 'lk_lahir', 'usia_kehamilan_lahir'];

    /** @return string[] */
    public static function kolom(string $tingkat): array
    {
        return array_keys($tingkat === 'anak' ? KesmasRules::anak() : KesmasRules::kunjungan());
    }

    /** @return string[]|null pilihan tampil untuk dropdown template; null = isian bebas */
    public static function pilihan(string $kolom): ?array
    {
        if (self::yaTidak($kolom)) {
            return ['Ya', 'Tidak'];
        }
        if (isset(self::PILIHAN[$kolom])) {
            return config('kesmas.'.self::PILIHAN[$kolom]);
        }
        if (isset(self::PILIHAN_KUNCI[$kolom])) {
            return array_values(config('kesmas.'.self::PILIHAN_KUNCI[$kolom]));
        }

        return null;
    }

    /**
     * @param 'anak'|'kunjungan'    $tingkat
     * @param array<string,mixed>   $sel          kolom => sel mentah, hanya yang tidak kosong
     * @param callable(mixed):?string $bacaTanggal format tanggal berkas yang sudah dikunci importer
     * @return array{nilai: array<string,mixed>, salah: array<string,string>}
     */
    public static function ubah(string $tingkat, array $sel, Anak $anak, callable $bacaTanggal): array
    {
        $label = KesmasRules::atribut();
        $nilai = [];
        $salah = [];

        foreach ($sel as $kolom => $mentah) {
            $v = self::ubahSatu($kolom, $mentah, $anak, $bacaTanggal);
            if ($v === null) {
                $pilihan = self::pilihan($kolom);
                $salah[$kolom] = ($label[$kolom] ?? $kolom)." — nilai '".NilaiSelKesmas::teks($mentah)."' tidak dikenal"
                    .($pilihan !== null ? ' (pilihan: '.implode(', ', $pilihan).')' : '');
            } else {
                $nilai[$kolom] = $v;
            }
        }

        if ($nilai !== []) {
            $aturan = array_intersect_key(
                $tingkat === 'anak' ? KesmasRules::anak($anak->penolong_lahir) : KesmasRules::kunjungan(),
                $nilai
            );
            $validator = Validator::make(
                $nilai + ['tgl_lahir' => substr((string) $anak->tgl_lahir, 0, 10)],
                $aturan,
                KesmasRules::pesan($aturan),
                $label
            );
            foreach ($validator->errors()->messages() as $kolom => $pesan) {
                $salah[$kolom] = rtrim($pesan[0], '.')." (nilai '".NilaiSelKesmas::teks($sel[$kolom])."')";
                unset($nilai[$kolom]);
            }
        }

        return ['nilai' => $nilai, 'salah' => $salah];
    }

    private static function yaTidak(string $kolom): bool
    {
        return in_array($kolom, KesmasRules::BOOL_ANAK, true) || array_key_exists($kolom, config('kesmas.layanan'));
    }

    private static function ubahSatu(string $kolom, mixed $mentah, Anak $anak, callable $bacaTanggal): mixed
    {
        if (self::yaTidak($kolom)) {
            return NilaiSelKesmas::yaTidak($mentah);
        }
        if ($kolom === 'penolong_lahir') {
            $daftar = array_values(array_unique(array_merge(config('kesmas.penolong_lahir'), array_filter([$anak->penolong_lahir]))));

            return NilaiSelKesmas::pilihan($mentah, $daftar);
        }
        if (isset(self::PILIHAN[$kolom])) {
            return NilaiSelKesmas::pilihan($mentah, config('kesmas.'.self::PILIHAN[$kolom]));
        }
        if (isset(self::PILIHAN_KUNCI[$kolom])) {
            return NilaiSelKesmas::pilihanBerkunci($mentah, config('kesmas.'.self::PILIHAN_KUNCI[$kolom]));
        }
        if (in_array($kolom, self::TANGGAL, true)) {
            return $bacaTanggal($mentah);
        }
        if (in_array($kolom, self::ANGKA, true)) {
            return NilaiSelKesmas::angka($mentah);
        }

        return NilaiSelKesmas::teks($mentah);
    }
}
```

Catatan: `KesmasRules::atribut()` memanggil `config('kesmas.layanan')` — aman karena kelas ini selalu jalan dengan aplikasi ter-boot (bukan unit test murni).

- [ ] **Step 4: Jalankan, pastikan lolos**

Run: `"$PHP" artisan test --filter=KolomKesmasImportTest`
Expected: PASS (7 tests). Bila `test_aturan_kesmas_rules_tetap_berlaku` gagal pada pesan `tgl_hbig`, periksa kunci validator: `after_or_equal:tgl_lahir` butuh `tgl_lahir` di data — sudah diteruskan.

- [ ] **Step 5: Commit**

```bash
git add app/Services/KolomKesmasImport.php tests/Feature/ImportKesmas/KolomKesmasImportTest.php
git commit -m "feat(import-kesmas): konversi dan validasi kolom Kesmas per sel"
```

---

### Task 3: `PenautanAnakKesmas` — cari anak, tak pernah membuat

**Files:**
- Create: `app/Services/PenautanAnakKesmas.php`
- Test: `tests/Feature/ImportKesmas/PenautanAnakKesmasTest.php`

**Interfaces:**
- Consumes: `PenautanAnakImport::tautkan(string $nama, ?string $tglLahir, string $jkChar, ?string $noKk, ?string $nikBerkas, ?string $namaIbu): array{hasil, anak, nik, jumlah, tingkat, pesan}` dengan konstanta `PenautanAnakImport::SAMA|BARU|AMBIGU`; `NikDummyService`.
- Produces: `(new PenautanAnakKesmas())->cari(string $nama, string $jk, string $tglLahir, string $namaOrtu, string $nik): array{anak: ?Anak, pesan: list<array{0:string,1:string}>}` — `pesan` = `[tingkat, teks]`, tingkat `ERROR|PERINGATAN|INFO`; `anak` null ⇔ ada pesan ERROR.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php

namespace Tests\Feature\ImportKesmas;

use App\Models\Anak;
use App\Services\PenautanAnakKesmas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PenautanAnakKesmasTest extends TestCase
{
    use RefreshDatabase;

    private function anak(array $isi): Anak
    {
        return Anak::create(array_merge(['jk' => 1, 'status' => 1], $isi));
    }

    private function tingkat(array $hasil): array
    {
        return array_column($hasil['pesan'], 0);
    }

    public function test_cocok_lewat_nik_tanpa_pesan(): void
    {
        $budi = $this->anak(['nama' => 'Budi Santoso', 'nik' => '6474010101230001', 'tgl_lahir' => '2023-01-01', 'nama_ibu' => 'Siti']);

        $hasil = (new PenautanAnakKesmas())->cari('BUDI SANTOSO', 'L', '2023-01-01', 'Siti', '6474010101230001');

        $this->assertSame($budi->id, $hasil['anak']->id);
        $this->assertSame([], $hasil['pesan']);
    }

    public function test_nik_milik_anak_lain_jatuh_ke_nama(): void
    {
        $this->anak(['nama' => 'Budi Santoso', 'nik' => '6474010101230001', 'tgl_lahir' => '2023-01-01', 'nama_ibu' => 'Siti']);
        $citra = $this->anak(['nama' => 'Citra Lestari', 'nik' => '6474014505220002', 'jk' => 2, 'tgl_lahir' => '2022-05-05', 'nama_ibu' => 'Rina']);

        $hasil = (new PenautanAnakKesmas())->cari('Citra Lestari', 'P', '2022-05-05', 'Rina', '6474010101230001');

        $this->assertSame($citra->id, $hasil['anak']->id);
        $this->assertContains('PERINGATAN', $this->tingkat($hasil));
        $this->assertStringContainsString('tercatat untuk anak lain', $hasil['pesan'][0][1]);
    }

    public function test_cocok_lewat_nama_tidak_mengubah_nik(): void
    {
        $budi = $this->anak(['nama' => 'Budi Santoso', 'nik' => '6474010101239001', 'tgl_lahir' => '2023-01-01', 'nama_ibu' => 'Siti']);

        $hasil = (new PenautanAnakKesmas())->cari('Budi Santoso', 'L', '2023-01-01', 'Siti', '6474010101230005');

        $this->assertSame($budi->id, $hasil['anak']->id);
        $this->assertSame('6474010101239001', $budi->fresh()->nik);
        $this->assertSame(['INFO'], $this->tingkat($hasil));
    }

    public function test_tidak_ditemukan_error_dan_tidak_membuat_anak(): void
    {
        $hasil = (new PenautanAnakKesmas())->cari('Anak Baru', 'L', '2023-01-01', 'Ibu', '');

        $this->assertNull($hasil['anak']);
        $this->assertSame(['ERROR'], $this->tingkat($hasil));
        $this->assertStringContainsString('tidak ditemukan', $hasil['pesan'][0][1]);
        $this->assertSame(0, Anak::count());
    }

    public function test_nama_ortu_berbeda_menyingkirkan_kandidat(): void
    {
        $this->anak(['nama' => 'MUHAMMAD ALFIAN RAMADHAN', 'nik' => '6474011903240001', 'tgl_lahir' => '2024-03-19', 'nama_ibu' => 'ARISDA']);

        $hasil = (new PenautanAnakKesmas())->cari('Muhammad Aydan Ramadhan', 'L', '2024-03-19', 'BUNGA MELATI', '');

        $this->assertNull($hasil['anak']);
        $this->assertSame(['ERROR'], $this->tingkat($hasil));
    }

    public function test_kembar_tanpa_nik_ambigu_bukan_ditebak(): void
    {
        $this->anak(['nama' => 'Muhammad Zayyan Al Fatih', 'nik' => '6474011204260002', 'tgl_lahir' => '2026-04-12', 'nama_ibu' => 'ALFIANA']);
        $this->anak(['nama' => 'Muhammad Rayyan Al Fatih', 'nik' => '6474011204260001', 'tgl_lahir' => '2026-04-12', 'nama_ibu' => 'ALFIANA']);

        $hasil = (new PenautanAnakKesmas())->cari('Muhammad Rayyan Al Fatih', 'L', '2026-04-12', 'ALFIANA', '');

        $this->assertNull($hasil['anak']);
        $this->assertStringContainsString('isi nik_anak', $hasil['pesan'][0][1]);
    }

    public function test_kembar_dengan_nik_tepat_sasaran(): void
    {
        $this->anak(['nama' => 'Muhammad Zayyan Al Fatih', 'nik' => '6474011204260002', 'tgl_lahir' => '2026-04-12', 'nama_ibu' => 'ALFIANA']);
        $rayyan = $this->anak(['nama' => 'Muhammad Rayyan Al Fatih', 'nik' => '6474011204260001', 'tgl_lahir' => '2026-04-12', 'nama_ibu' => 'ALFIANA']);

        $hasil = (new PenautanAnakKesmas())->cari('Muhammad Rayyan Al Fatih', 'L', '2026-04-12', 'ALFIANA', '6474011204260001');

        $this->assertSame($rayyan->id, $hasil['anak']->id);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `"$PHP" artisan test --filter=PenautanAnakKesmasTest`
Expected: FAIL — `Class "App\Services\PenautanAnakKesmas" not found`

- [ ] **Step 3: Implementasi**

```php
<?php

namespace App\Services;

use App\Models\Anak;

/**
 * Penautan baris Import Data Kesmas ke anak yang SUDAH ADA (spec 2026-10-15-import-kesmas-design.md §4).
 * Hanya mencari: tidak membuat anak, tidak mengubah NIK.
 *
 * NIK dipercaya hanya bila tgl lahir sama ATAU nama mirip — 72% anak ganda di prod ber-NIK ibu atau
 * salah ketik, dan salah tautan di sini berarti data Kesmas menempel ke anak orang lain tanpa jejak.
 */
class PenautanAnakKesmas
{
    private const MIRIP = 87;

    private PenautanAnakImport $penautan;

    public function __construct(?PenautanAnakImport $penautan = null)
    {
        $this->penautan = $penautan ?? new PenautanAnakImport(new NikDummyService());
    }

    /** @return array{anak: ?Anak, pesan: list<array{0:string,1:string}>} */
    public function cari(string $nama, string $jk, string $tglLahir, string $namaOrtu, string $nik): array
    {
        $pesan = [];
        $nik = substr($nik, 0, 16);
        $nikSah = strlen($nik) >= 15 && ctype_digit($nik);

        if ($nikSah && ($lewatNik = Anak::where('nik', $nik)->first())) {
            if (substr((string) $lewatNik->tgl_lahir, 0, 10) === $tglLahir || self::mirip($nama, (string) $lewatNik->nama)) {
                return ['anak' => $lewatNik, 'pesan' => []];
            }
            $pesan[] = ['PERINGATAN', "NIK {$nik} tercatat untuk anak lain ({$lewatNik->nama}, lahir "
                .substr((string) $lewatNik->tgl_lahir, 0, 10).') — dicocokkan lewat nama.'];
        }

        $t = $this->penautan->tautkan($nama, $tglLahir, $jk, null, $nikSah ? $nik : null, $namaOrtu);

        if ($t['hasil'] === PenautanAnakImport::SAMA) {
            $pesan[] = ['INFO', "dicocokkan lewat nama + tgl lahir + jk dengan anak ber-NIK {$t['anak']->nik}"
                .($nikSah ? "; NIK berkas {$nik} tidak dipakai dan NIK di data tidak diubah." : '.')];

            return ['anak' => $t['anak'], 'pesan' => $pesan];
        }

        $pesan[] = ['ERROR', $t['hasil'] === PenautanAnakImport::AMBIGU
            ? "ditemukan {$t['jumlah']} anak yang cocok (nama + tgl lahir + jk) — isi nik_anak supaya pasti."
            : 'anak tidak ditemukan (nama + tgl lahir + jk + nama ortu) — tambahkan dulu lewat Tambah Anak atau Import Anak.'];

        return ['anak' => null, 'pesan' => $pesan];
    }

    private static function mirip(string $a, string $b): bool
    {
        similar_text(mb_strtolower(trim($a)), mb_strtolower(trim($b)), $pct);

        return $pct >= self::MIRIP;
    }
}
```

- [ ] **Step 4: Jalankan, pastikan lolos**

Run: `"$PHP" artisan test --filter=PenautanAnakKesmasTest`
Expected: PASS (7 tests). Bila `test_kembar_tanpa_nik_ambigu_bukan_ditebak` mendapat SAMA, cek bahwa `nama_ibu` kedua anak sama (aturan kembar butuh NIK berkas; tanpa NIK keduanya kandidat → AMBIGU).

- [ ] **Step 5: Commit**

```bash
git add app/Services/PenautanAnakKesmas.php tests/Feature/ImportKesmas/PenautanAnakKesmasTest.php
git commit -m "feat(import-kesmas): penautan anak yang sudah ada, NIK berpengaman"
```

---

### Task 4: `KesmasImport` — header, identitas, field per anak

**Files:**
- Create: `app/Exceptions/BarisImportBatal.php`
- Create: `app/Imports/KesmasImport.php`
- Test: `tests/Feature/ImportKesmas/KesmasImportAnakTest.php`

**Interfaces:**
- Consumes: Task 1–3; trait `App\Traits\MembacaTanggalBerkas` (`setFormatTanggal`, `kunciFormatTanggal`, `bacaTanggal`); `App\Support\NamaAnak::sah/kosong`; `App\Support\ImportError::message`.
- Produces:
  - `new KesmasImport(int $userId)`; `->setFormatTanggal(?string): static`; `->collection(Collection $rows): void`; `->getResults(): array{success:int, error_count:int, failures:string[]}`
  - `KesmasImport::KOLOM_IDENTITAS`, `KesmasImport::KOLOM_UKUR` (const string[]), `KesmasImport::kolomDikenal(): string[]`
  - `App\Exceptions\BarisImportBatal` (RuntimeException; pesannya = alasan ERROR)
  - private `tulis()` yang di Task 5 diberi cabang kunjungan.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php

namespace Tests\Feature\ImportKesmas;

use App\Exceptions\FormatTanggalAmbigu;
use App\Imports\KesmasImport;
use App\Models\Anak;
use App\Models\DataAnak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KesmasImportAnakTest extends TestCase
{
    use RefreshDatabase;

    private const ID = ['nik_anak', 'nama_anak', 'jk', 'tgl_lahir', 'nama_ortu'];

    private function anak(array $isi = []): Anak
    {
        return Anak::create(array_merge([
            'nama' => 'Budi Santoso', 'nik' => '6474010101230001', 'jk' => 1,
            'tgl_lahir' => '2023-01-01', 'status' => 1, 'nama_ibu' => 'Siti Aminah',
        ], $isi));
    }

    private function budi(array $tambahan = []): array
    {
        return array_merge(['6474010101230001', 'Budi Santoso', 'L', '2023-01-01', 'Siti Aminah'], $tambahan);
    }

    private function impor(array $header, array $baris, string $format = 'auto'): array
    {
        $i = (new KesmasImport(1))->setFormatTanggal($format);
        $i->collection(collect(array_merge([$header], $baris)));

        return $i->getResults();
    }

    private function teks(array $hasil): string
    {
        return implode("\n", $hasil['failures']);
    }

    public function test_field_anak_terisi_lewat_nik(): void
    {
        $anak = $this->anak();

        $hasil = $this->impor([...self::ID, 'air_bersih', 'penolong_lahir'], [$this->budi(['Ya', 'bidan'])]);

        $this->assertSame(1, $hasil['success'], $this->teks($hasil));
        $this->assertSame(0, $hasil['error_count']);
        $this->assertSame(1, (int) $anak->fresh()->air_bersih);
        $this->assertSame('Bidan', $anak->fresh()->penolong_lahir);
    }

    public function test_sel_kosong_tidak_menimpa_nilai_lama(): void
    {
        $anak = $this->anak(['air_bersih' => 0, 'fktp_bpjs' => 'Klinik A']);

        $this->impor([...self::ID, 'air_bersih', 'fktp_bpjs', 'jamban_sehat'], [$this->budi(['', null, 'Ya'])]);

        $this->assertSame(0, (int) $anak->fresh()->air_bersih);
        $this->assertSame('Klinik A', $anak->fresh()->fktp_bpjs);
        $this->assertSame(1, (int) $anak->fresh()->jamban_sehat);
    }

    public function test_sel_berisi_menimpa_nilai_lama(): void
    {
        $anak = $this->anak(['air_bersih' => 1]);

        $this->impor([...self::ID, 'air_bersih'], [$this->budi(['Tidak'])]);

        $this->assertSame(0, (int) $anak->fresh()->air_bersih);
    }

    public function test_sel_tak_sah_hanya_kolom_itu_yang_dilewati(): void
    {
        $anak = $this->anak();

        $hasil = $this->impor([...self::ID, 'penolong_lahir', 'air_bersih'], [$this->budi(['Dukun', 'Ya'])]);

        $this->assertSame(1, $hasil['success']);
        $this->assertNull($anak->fresh()->penolong_lahir);
        $this->assertSame(1, (int) $anak->fresh()->air_bersih);
        $this->assertStringContainsString('[PERINGATAN] Baris 2 (Budi Santoso): Penolong persalinan', $this->teks($hasil));
        $this->assertStringContainsString('kolom dilewati', $this->teks($hasil));
    }

    public function test_identitas_wajib_kosong_error(): void
    {
        $this->anak();

        $hasil = $this->impor([...self::ID, 'air_bersih'], [['6474010101230001', 'Budi Santoso', 'L', '2023-01-01', '', 'Ya']]);

        $this->assertSame(1, $hasil['error_count']);
        $this->assertStringContainsString('[ERROR] Baris 2 (Budi Santoso): kolom wajib kosong atau tidak terbaca: nama_ortu', $this->teks($hasil));
        $this->assertNull(Anak::first()->air_bersih);
    }

    public function test_anak_tidak_ditemukan_error_tanpa_membuat_anak(): void
    {
        $hasil = $this->impor([...self::ID, 'air_bersih'], [['', 'Anak Baru', 'L', '2023-01-01', 'Ibu Baru', 'Ya']]);

        $this->assertSame(1, $hasil['error_count']);
        $this->assertSame(0, Anak::count());
        $this->assertStringContainsString('tidak ditemukan', $this->teks($hasil));
    }

    public function test_nik_sel_angka_excel_tetap_cocok(): void
    {
        $anak = $this->anak();

        $this->impor([...self::ID, 'air_bersih'], [[6474010101230001.0, 'Budi Santoso', 'L', '2023-01-01', 'Siti Aminah', 'Ya']]);

        $this->assertSame(1, (int) $anak->fresh()->air_bersih);
    }

    public function test_header_beda_huruf_dan_berspasi_tetap_dikenali(): void
    {
        $anak = $this->anak();

        $hasil = $this->impor([' NIK_ANAK', 'Nama_Anak ', 'JK', 'Tgl_Lahir', 'NAMA_ORTU', ' Air_Bersih '], [$this->budi(['Ya'])]);

        $this->assertSame(1, $hasil['success'], $this->teks($hasil));
        $this->assertSame(1, (int) $anak->fresh()->air_bersih);
    }

    public function test_header_wajib_hilang_menghentikan_import(): void
    {
        $this->anak();

        $hasil = $this->impor(['nik_anak', 'nama_anak', 'jk', 'tgl_lahir', 'air_bersih'], [['6474010101230001', 'Budi Santoso', 'L', '2023-01-01', 'Ya']]);

        $this->assertSame(0, $hasil['success']);
        $this->assertStringContainsString('[ERROR] Header: kolom wajib tidak ada: nama_ortu', $this->teks($hasil));
        $this->assertNull(Anak::first()->air_bersih);
    }

    public function test_kolom_tak_dikenal_dan_sasaran_tidak_ditulis(): void
    {
        $anak = $this->anak();

        $hasil = $this->impor([...self::ID, 'sasaran_balita_kesmas', 'air_bersih'], [$this->budi(['1', 'Ya'])]);

        $this->assertNull($anak->fresh()->sasaran_balita_kesmas);
        $this->assertStringContainsString("[PERINGATAN] Header: kolom 'sasaran_balita_kesmas' tidak dikenal", $this->teks($hasil));
    }

    public function test_baris_tanpa_tanggal_kunjungan_hanya_mengisi_field_anak(): void
    {
        $anak = $this->anak();

        $hasil = $this->impor([...self::ID, 'tgl_kunjungan', 'bb', 'mbg', 'air_bersih'], [$this->budi(['', '10', 'Ya', 'Ya'])]);

        $this->assertSame(0, DataAnak::count());
        $this->assertSame(1, (int) $anak->fresh()->air_bersih);
        $this->assertStringContainsString('diabaikan karena tgl_kunjungan kosong', $this->teks($hasil));
    }

    public function test_baris_hanya_identitas_peringatan_dan_tidak_dihitung(): void
    {
        $this->anak();

        $hasil = $this->impor([...self::ID, 'air_bersih'], [$this->budi([''])]);

        $this->assertSame(0, $hasil['success']);
        $this->assertSame(0, $hasil['error_count']);
        $this->assertStringContainsString('tidak ada isian Kesmas atau kunjungan', $this->teks($hasil));
    }

    public function test_anak_di_dua_baris_baris_bawah_menang_dengan_peringatan(): void
    {
        $anak = $this->anak();

        $hasil = $this->impor([...self::ID, 'air_bersih'], [$this->budi(['Ya']), $this->budi(['Tidak'])]);

        $this->assertSame(0, (int) $anak->fresh()->air_bersih);
        $this->assertStringContainsString('[PERINGATAN] Baris 3 (Budi Santoso): Akses air bersih di rumah berbeda dengan baris 2', $this->teks($hasil));
    }

    public function test_baris_kosong_dilewati_diam_diam(): void
    {
        $this->anak();

        $hasil = $this->impor([...self::ID, 'air_bersih'], [[null, null, null, null, null, null], $this->budi(['Ya'])]);

        $this->assertSame(1, $hasil['success']);
        $this->assertSame([], $hasil['failures']);
    }

    public function test_format_tanggal_ambigu_menghentikan_import(): void
    {
        $this->expectException(FormatTanggalAmbigu::class);

        $this->impor([...self::ID, 'air_bersih'], [['', 'Budi Santoso', 'L', '05/02/2023', 'Siti Aminah', 'Ya']]);
    }

    public function test_format_tanggal_pilihan_dipakai(): void
    {
        $anak = $this->anak(['tgl_lahir' => '2023-02-05']);

        $this->impor([...self::ID, 'air_bersih'], [['', 'Budi Santoso', 'L', '05/02/2023', 'Siti Aminah', 'Ya']], 'dmy');

        $this->assertSame(1, (int) $anak->fresh()->air_bersih);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `"$PHP" artisan test --filter=KesmasImportAnakTest`
Expected: FAIL — `Class "App\Imports\KesmasImport" not found`

- [ ] **Step 3: Implementasi**

`app/Exceptions/BarisImportBatal.php`:

```php
<?php

namespace App\Exceptions;

/** Satu baris import dibatalkan seluruhnya; pesannya dicatat sebagai [ERROR] baris itu. */
class BarisImportBatal extends \RuntimeException
{
}
```

`app/Imports/KesmasImport.php`:

```php
<?php

namespace App\Imports;

use App\Exceptions\BarisImportBatal;
use App\Http\Requests\Admin\Anak\KesmasRules;
use App\Models\Anak;
use App\Services\KolomKesmasImport;
use App\Services\PenautanAnakKesmas;
use App\Support\ImportError;
use App\Support\NamaAnak;
use App\Support\NilaiSelKesmas;
use App\Traits\MembacaTanggalBerkas;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Import Data Kesmas (spec docs/superpowers/specs/2026-10-15-import-kesmas-design.md).
 *
 * Mengisi field Kesmas anak yang SUDAH ADA; satu baris = satu anak pada satu kunjungan (tanpa
 * tgl_kunjungan = hanya field per anak). Hanya sheet pertama yang dibaca: tanpa WithMultipleSheets
 * Maatwebsite memproses SEMUA sheet, dan sheet Petunjuk template akan terbaca sebagai data.
 */
class KesmasImport implements ToCollection, WithChunkReading, WithMultipleSheets
{
    use MembacaTanggalBerkas;

    public const KOLOM_IDENTITAS = ['nama_anak', 'jk', 'tgl_lahir', 'nama_ortu'];
    public const KOLOM_UKUR = ['bb', 'tb_cm', 'posisi', 'lk_cm', 'lila_cm', 'zscore_bb_u', 'zscore_pb_u', 'zscore_bb_pb'];
    private const KOLOM_TANGGAL = ['tgl_lahir', 'tgl_kunjungan', 'tgl_hbig', 'tgl_penanda_ckg'];

    private int $sukses = 0;
    private int $gagal = 0;
    /** @var string[] */
    private array $pesan = [];
    /** @var array<string,int>|null kolom => indeks sel; null = header belum dibaca */
    private ?array $peta = null;
    private int $barisDibaca = 0;
    private bool $berhenti = false;
    /** @var array<string, array{anak: ?Anak, pesan: array}> hasil penautan per identitas baris */
    private array $tautan = [];
    /** @var array<int, array<string, array{0:mixed, 1:int}>> id anak => kolom => [nilai, baris] */
    private array $nilaiAnakBerkas = [];
    private PenautanAnakKesmas $penautan;

    public function __construct(private readonly int $userId)
    {
        $this->penautan = new PenautanAnakKesmas();
    }

    public function sheets(): array
    {
        return [0 => $this];
    }

    public function chunkSize(): int
    {
        return 200;
    }

    /** @return string[] */
    public static function kolomDikenal(): array
    {
        return array_merge(
            ['nik_anak'], self::KOLOM_IDENTITAS, ['tgl_kunjungan'], self::KOLOM_UKUR,
            KolomKesmasImport::kolom('kunjungan'), KolomKesmasImport::kolom('anak')
        );
    }

    public function collection(Collection $rows): void
    {
        $awal = $this->barisDibaca;
        $this->barisDibaca += $rows->count();
        if ($this->berhenti) {
            return;
        }

        $rows = $rows->map(fn ($r) => $r instanceof Collection ? $r->all() : (array) $r);

        if ($this->peta === null) {
            if (! $this->bacaHeader($rows->first() ?? [])) {
                $this->berhenti = true;

                return;
            }
            $rows = $rows->slice(1);
            $this->kunciFormatTanggal($this->nilaiTanggal($rows));
        }

        foreach ($rows as $i => $row) {
            $this->prosesBaris($awal + $i + 1, $row);
        }
    }

    public function getResults(): array
    {
        return ['success' => $this->sukses, 'error_count' => $this->gagal, 'failures' => $this->pesan];
    }

    private function bacaHeader(array $header): bool
    {
        $peta = [];
        foreach ($header as $idx => $nama) {
            $kunci = mb_strtolower(trim((string) $nama));
            if ($kunci !== '') {
                $peta[$kunci] = $idx;
            }
        }

        $hilang = array_diff(self::KOLOM_IDENTITAS, array_keys($peta));
        if ($hilang !== []) {
            $this->pesan[] = '[ERROR] Header: kolom wajib tidak ada: '.implode(', ', $hilang)
                .' — gunakan template Import Data Kesmas.';

            return false;
        }

        $dikenal = self::kolomDikenal();
        foreach (array_diff(array_keys($peta), $dikenal) as $asing) {
            $this->pesan[] = "[PERINGATAN] Header: kolom '{$asing}' tidak dikenal — diabaikan.";
        }
        $this->peta = array_intersect_key($peta, array_flip($dikenal));

        return true;
    }

    private function nilaiTanggal(Collection $rows): \Generator
    {
        foreach ($rows as $row) {
            foreach (self::KOLOM_TANGGAL as $kolom) {
                $v = $this->sel($row, $kolom);
                if (! NilaiSelKesmas::kosong($v)) {
                    yield $v;
                }
            }
        }
    }

    private function sel(array $row, string $kolom): mixed
    {
        return isset($this->peta[$kolom]) ? ($row[$this->peta[$kolom]] ?? null) : null;
    }

    /** @param string[] $kolom @return array<string,mixed> hanya sel yang tidak kosong */
    private function selKolom(array $row, array $kolom): array
    {
        $sel = [];
        foreach ($kolom as $k) {
            $v = $this->sel($row, $k);
            if (! NilaiSelKesmas::kosong($v)) {
                $sel[$k] = $v;
            }
        }

        return $sel;
    }

    private function prosesBaris(int $n, array $row): void
    {
        if ($this->selKolom($row, array_keys($this->peta)) === []) {
            return;   // baris kosong (sisa format Excel)
        }

        $nama = NilaiSelKesmas::teks($this->sel($row, 'nama_anak'));
        $label = $nama !== '' ? $nama : '-';
        $catat = function (string $tingkat, string $teks) use ($n, $label): void {
            $this->pesan[] = "[{$tingkat}] Baris {$n} ({$label}): {$teks}";
        };

        $jk = NilaiSelKesmas::jk($this->sel($row, 'jk'));
        $tglLahir = $this->bacaTanggal($this->sel($row, 'tgl_lahir'));
        $namaOrtu = NilaiSelKesmas::teks($this->sel($row, 'nama_ortu'));

        $kurang = [];
        if (NamaAnak::kosong($nama)) $kurang[] = 'nama_anak';
        if ($jk === null) $kurang[] = 'jk (L/P)';
        if ($tglLahir === null) $kurang[] = 'tgl_lahir';
        if (NamaAnak::kosong($namaOrtu)) $kurang[] = 'nama_ortu';
        if ($kurang !== []) {
            $catat('ERROR', 'kolom wajib kosong atau tidak terbaca: '.implode(', ', $kurang).' — baris dilewati.');
            $this->gagal++;

            return;
        }

        $nik = NilaiSelKesmas::nik($this->sel($row, 'nik_anak'));
        $kunci = implode('|', [mb_strtolower($nama), $jk, $tglLahir, mb_strtolower($namaOrtu), $nik]);
        $tautan = $this->tautan[$kunci] ??= $this->penautan->cari($nama, $jk, $tglLahir, $namaOrtu, $nik);
        foreach ($tautan['pesan'] as [$tingkat, $teks]) {
            $catat($tingkat, $teks);
        }
        if ($tautan['anak'] === null) {
            $this->gagal++;

            return;
        }

        try {
            $ditulis = DB::transaction(fn () => $this->tulis($n, $row, $tautan['anak'], $catat));
        } catch (BarisImportBatal $e) {
            $catat('ERROR', $e->getMessage());
            $this->gagal++;

            return;
        } catch (\Throwable $e) {
            $catat('ERROR', ImportError::message($e->getMessage()));
            $this->gagal++;
            Log::warning("KesmasImport baris {$n}: ".$e->getMessage());

            return;
        }

        if ($ditulis) {
            $this->sukses++;
        } else {
            $catat('PERINGATAN', 'tidak ada isian Kesmas atau kunjungan — tidak ada yang disimpan.');
        }
    }

    /** @return bool apakah ada yang ditulis */
    private function tulis(int $n, array $row, Anak $anak, callable $catat): bool
    {
        $kunjunganDitulis = false;
        if (NilaiSelKesmas::kosong($this->sel($row, 'tgl_kunjungan'))) {
            if ($this->selKolom($row, array_merge(self::KOLOM_UKUR, KolomKesmasImport::kolom('kunjungan'))) !== []) {
                $catat('PERINGATAN', 'kolom kunjungan (ukur/layanan) diabaikan karena tgl_kunjungan kosong.');
            }
        }
        $anakDitulis = $this->tulisAnak($n, $row, $anak, $catat);

        return $kunjunganDitulis || $anakDitulis;
    }

    private function tulisAnak(int $n, array $row, Anak $anak, callable $catat): bool
    {
        $sel = $this->selKolom($row, KolomKesmasImport::kolom('anak'));
        if ($sel === []) {
            return false;
        }

        ['nilai' => $nilai, 'salah' => $salah] = KolomKesmasImport::ubah('anak', $sel, $anak, fn ($v) => $this->bacaTanggal($v));
        foreach ($salah as $teks) {
            $catat('PERINGATAN', $teks.'; kolom dilewati.');
        }
        if ($nilai === []) {
            return false;
        }

        $label = KesmasRules::atribut();
        foreach ($nilai as $kolom => $v) {
            $lama = $this->nilaiAnakBerkas[$anak->id][$kolom] ?? null;
            if ($lama !== null && (string) $lama[0] !== (string) $v) {
                $catat('PERINGATAN', ($label[$kolom] ?? $kolom)." berbeda dengan baris {$lama[1]} ('{$lama[0]}' → '{$v}') — nilai baris ini yang dipakai.");
            }
            $this->nilaiAnakBerkas[$anak->id][$kolom] = [$v, $n];
        }

        $anak->fill($nilai)->save();

        return true;
    }
}
```

- [ ] **Step 4: Jalankan, pastikan lolos**

Run: `"$PHP" artisan test --filter=KesmasImportAnakTest`
Expected: PASS (16 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Exceptions/BarisImportBatal.php app/Imports/KesmasImport.php tests/Feature/ImportKesmas/KesmasImportAnakTest.php
git commit -m "feat(import-kesmas): importer — header, penautan, field Kesmas per anak"
```

---

### Task 5: `KesmasImport` — kunjungan, ukuran, satuan gram

**Files:**
- Modify: `app/Imports/KesmasImport.php` (method `tulis()` + method baru)
- Test: `tests/Feature/ImportKesmas/KesmasImportKunjunganTest.php`

**Interfaces:**
- Consumes: `SatuanBeratBadan::untuk/dalamRentang/keKg/sama/RENTANG/GRAM`; `usia_bulan()`; `normalisasi_posisi()`; `App\Models\DataAnak`.
- Produces: perilaku §5 spec; tidak ada API publik baru.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php

namespace Tests\Feature\ImportKesmas;

use App\Imports\KesmasImport;
use App\Models\Anak;
use App\Models\DataAnak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KesmasImportKunjunganTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = ['nik_anak', 'nama_anak', 'jk', 'tgl_lahir', 'nama_ortu', 'tgl_kunjungan', 'bb', 'tb_cm', 'posisi', 'mbg', 'rujukan', 'air_bersih'];

    private Anak $anak;

    protected function setUp(): void
    {
        parent::setUp();
        $this->anak = Anak::create([
            'nama' => 'Budi Santoso', 'nik' => '6474010101230001', 'jk' => 1,
            'tgl_lahir' => '2023-01-01', 'status' => 1, 'nama_ibu' => 'Siti Aminah',
        ]);
    }

    private function baris(string $tglLahir, string $tgl, mixed $bb, mixed $tb = 85, string $posisi = 'Berdiri', string $mbg = '', string $rujukan = '', string $air = ''): array
    {
        return ['6474010101230001', 'Budi Santoso', 'L', $tglLahir, 'Siti Aminah', $tgl, $bb, $tb, $posisi, $mbg, $rujukan, $air];
    }

    private function impor(array $baris): array
    {
        $i = (new KesmasImport(7))->setFormatTanggal('auto');
        $i->collection(collect(array_merge([self::HEADER], $baris)));

        return $i->getResults();
    }

    private function teks(array $hasil): string
    {
        return implode("\n", $hasil['failures']);
    }

    public function test_kunjungan_baru_dibuat_dengan_ukuran_kg_dan_layanan(): void
    {
        $hasil = $this->impor([$this->baris('2023-01-01', '2025-03-10', '10,5', 85, 'Berdiri', 'Ya')]);

        $this->assertSame(1, $hasil['success'], $this->teks($hasil));
        $k = DataAnak::sole();
        $this->assertSame('2025-03-10', substr((string) $k->tgl_kunjungan, 0, 10));
        $this->assertEqualsWithDelta(10.5, (float) $k->bb, 0.0001);
        $this->assertEqualsWithDelta(85.0, (float) $k->tb, 0.0001);
        $this->assertSame('H', $k->posisi);
        $this->assertSame(26, (int) $k->bln);
        $this->assertSame(1, (int) $k->mbg);
        $this->assertSame(0.0, (float) $k->lk);
        $this->assertSame(7, (int) $k->id_user);
    }

    public function test_bayi_di_bawah_dua_bulan_bb_dalam_gram(): void
    {
        $this->anak->update(['tgl_lahir' => '2025-01-10']);

        $hasil = $this->impor([$this->baris('2025-01-10', '2025-02-01', 3250, 50, 'Terlentang')]);

        $this->assertSame(1, $hasil['success'], $this->teks($hasil));
        $this->assertEqualsWithDelta(3.25, (float) DataAnak::sole()->bb, 0.0001);
    }

    public function test_bayi_di_bawah_dua_bulan_bb_ditulis_kg_ditolak_seluruh_baris(): void
    {
        $this->anak->update(['tgl_lahir' => '2025-01-10']);

        $hasil = $this->impor([$this->baris('2025-01-10', '2025-02-01', '3,25', 50, 'Terlentang', '', '', 'Ya')]);

        $this->assertSame(1, $hasil['error_count']);
        $this->assertSame(0, DataAnak::count());
        $this->assertNull($this->anak->fresh()->air_bersih);   // field anak ikut batal
        $this->assertStringContainsString('gram', $this->teks($hasil));
    }

    public function test_bb_gram_dengan_titik_ribuan_ditolak_bukan_tersimpan_kg(): void
    {
        $this->anak->update(['tgl_lahir' => '2025-01-10']);

        $hasil = $this->impor([$this->baris('2025-01-10', '2025-02-01', '3.250', 50, 'Terlentang')]);

        $this->assertSame(1, $hasil['error_count']);
        $this->assertSame(0, DataAnak::count());
    }

    public function test_tepat_dua_bulan_sudah_kg(): void
    {
        $this->anak->update(['tgl_lahir' => '2025-01-10']);

        $this->impor([$this->baris('2025-01-10', '2025-03-10', 5, 57, 'Terlentang')]);

        $this->assertEqualsWithDelta(5.0, (float) DataAnak::sole()->bb, 0.0001);
    }

    public function test_kunjungan_baru_tanpa_posisi_error(): void
    {
        $hasil = $this->impor([$this->baris('2023-01-01', '2025-03-10', 10, 85, '')]);

        $this->assertSame(1, $hasil['error_count']);
        $this->assertSame(0, DataAnak::count());
        $this->assertStringContainsString('posisi', $this->teks($hasil));
    }

    public function test_kunjungan_yang_ada_ukurannya_tidak_diubah_tetapi_layanannya_diisi(): void
    {
        DataAnak::create([
            'id_anak' => $this->anak->id, 'tgl_kunjungan' => '2025-03-10', 'bln' => 26, 'posisi' => 'H',
            'bb' => 10.2, 'tb' => 85, 'lk' => 0, 'lla' => 0, 'id_user' => 1,
        ]);

        $hasil = $this->impor([$this->baris('2023-01-01', '2025-03-10', '10,5', 85, 'Berdiri', 'Ya')]);

        $k = DataAnak::sole();
        $this->assertEqualsWithDelta(10.2, (float) $k->bb, 0.0001);
        $this->assertSame(1, (int) $k->mbg);
        $this->assertSame(1, $hasil['success']);
        $this->assertStringContainsString('ukuran tidak diubah: bb di berkas 10,5, tersimpan 10,2', $this->teks($hasil));
    }

    public function test_kunjungan_yang_ada_dengan_ukuran_sama_tanpa_peringatan(): void
    {
        DataAnak::create([
            'id_anak' => $this->anak->id, 'tgl_kunjungan' => '2025-03-10', 'bln' => 26, 'posisi' => 'H',
            'bb' => 10.5, 'tb' => 85, 'lk' => 0, 'lla' => 0, 'id_user' => 1,
        ]);

        $hasil = $this->impor([$this->baris('2023-01-01', '2025-03-10', '10,5', 85, 'Berdiri', 'Ya')]);

        $this->assertStringNotContainsString('ukuran tidak diubah', $this->teks($hasil));
    }

    public function test_tgl_kunjungan_tak_terbaca_seluruh_baris_dilewati(): void
    {
        $hasil = $this->impor([$this->baris('2023-01-01', 'kemarin', 10, 85, 'Berdiri', '', '', 'Ya')]);

        $this->assertSame(1, $hasil['error_count']);
        $this->assertNull($this->anak->fresh()->air_bersih);
        $this->assertStringContainsString("tgl_kunjungan 'kemarin' tidak terbaca", $this->teks($hasil));
    }

    public function test_layanan_tak_sah_hanya_sel_itu_kunjungan_tetap_dibuat(): void
    {
        $hasil = $this->impor([$this->baris('2023-01-01', '2025-03-10', 10, 85, 'Berdiri', 'Ya', 'Puskesmas X')]);

        $k = DataAnak::sole();
        $this->assertSame(1, (int) $k->mbg);
        $this->assertNull($k->rujukan);
        $this->assertStringContainsString('Rujukan', $this->teks($hasil));
    }

    public function test_dua_kunjungan_anak_sama_satu_gagal_yang_lain_tersimpan(): void
    {
        $hasil = $this->impor([
            $this->baris('2023-01-01', '2025-03-10', 10, 85),
            $this->baris('2023-01-01', '2025-04-10', 'x', 86),
        ]);

        $this->assertSame(1, $hasil['success']);
        $this->assertSame(1, $hasil['error_count']);
        $this->assertSame(['2025-03-10'], DataAnak::pluck('tgl_kunjungan')->map(fn ($t) => substr((string) $t, 0, 10))->all());
        $this->assertStringContainsString('[ERROR] Baris 3', $this->teks($hasil));
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `"$PHP" artisan test --filter=KesmasImportKunjunganTest`
Expected: FAIL — kunjungan tidak dibuat (`DataAnak::sole()` → `ModelNotFoundException`), karena `tulis()` belum menangani `tgl_kunjungan`.

- [ ] **Step 3: Implementasi**

Tambahkan `use` di `app/Imports/KesmasImport.php`:

```php
use App\Models\DataAnak;
use App\Support\SatuanBeratBadan;
```

Tambahkan konstanta di kelas (di bawah `KOLOM_TANGGAL`):

```php
    /** kolom berkas => kolom data_anak */
    private const UKUR = [
        'bb' => 'bb', 'tb_cm' => 'tb', 'posisi' => 'posisi', 'lk_cm' => 'lk', 'lila_cm' => 'lla',
        'zscore_bb_u' => 'zscore_bb_u', 'zscore_pb_u' => 'zscore_pb_u', 'zscore_bb_pb' => 'zscore_bb_pb',
    ];

    /** Ukuran wajib untuk kunjungan baru: kolom data_anak => kolom berkas. */
    private const UKUR_WAJIB = ['bb' => 'bb', 'tb' => 'tb_cm', 'posisi' => 'posisi'];
```

Ganti method `tulis()` seluruhnya:

```php
    /** @return bool apakah ada yang ditulis */
    private function tulis(int $n, array $row, Anak $anak, callable $catat): bool
    {
        $tglSel = $this->sel($row, 'tgl_kunjungan');
        $kunjunganDitulis = false;
        if (! NilaiSelKesmas::kosong($tglSel)) {
            $kunjunganDitulis = $this->tulisKunjungan($row, $anak, $tglSel, $catat);
        } elseif ($this->selKolom($row, array_merge(self::KOLOM_UKUR, KolomKesmasImport::kolom('kunjungan'))) !== []) {
            $catat('PERINGATAN', 'kolom kunjungan (ukur/layanan) diabaikan karena tgl_kunjungan kosong.');
        }
        // Kunjungan lebih dulu: bila baris batal, field anak tidak sempat dicatat sebagai nilai berkas.
        $anakDitulis = $this->tulisAnak($n, $row, $anak, $catat);

        return $kunjunganDitulis || $anakDitulis;
    }

    private function tulisKunjungan(array $row, Anak $anak, mixed $tglSel, callable $catat): bool
    {
        $tgl = $this->bacaTanggal($tglSel);
        if ($tgl === null) {
            throw new BarisImportBatal("tgl_kunjungan '".NilaiSelKesmas::teks($tglSel)."' tidak terbaca — seluruh baris dilewati.");
        }

        $ukur = $this->ukurBerkas($row, $anak, $tgl);
        $sel = $this->selKolom($row, KolomKesmasImport::kolom('kunjungan'));
        ['nilai' => $kesmas, 'salah' => $salah] = $sel === []
            ? ['nilai' => [], 'salah' => []]
            : KolomKesmasImport::ubah('kunjungan', $sel, $anak, fn ($v) => $this->bacaTanggal($v));

        $ada = DataAnak::where('id_anak', $anak->id)->whereDate('tgl_kunjungan', $tgl)->first();

        if ($ada === null) {
            $kurang = [];
            foreach (self::UKUR_WAJIB as $kolom => $kolomBerkas) {
                if (! array_key_exists($kolom, $ukur['nilai'])) {
                    $kurang[] = $ukur['salah'][$kolom] ?? "{$kolomBerkas} wajib diisi";
                }
            }
            if ($kurang !== []) {
                throw new BarisImportBatal("kunjungan {$tgl} belum ada dan ukurannya tidak lengkap: ".implode('; ', $kurang).' — seluruh baris dilewati.');
            }
            foreach ($ukur['salah'] as $teks) {
                $catat('PERINGATAN', $teks.'; kolom dilewati.');
            }
            foreach ($salah as $teks) {
                $catat('PERINGATAN', $teks.'; kolom dilewati.');
            }

            DataAnak::create(array_merge(
                ['lk' => 0, 'lla' => 0],
                $ukur['nilai'],
                $kesmas,
                [
                    'id_anak' => $anak->id, 'tgl_kunjungan' => $tgl,
                    'bln' => usia_bulan($anak->tgl_lahir, $tgl) ?? 0, 'id_user' => $this->userId,
                ]
            ));

            return true;
        }

        foreach ($salah as $teks) {
            $catat('PERINGATAN', $teks.'; kolom dilewati.');
        }
        $beda = $this->bedaUkur($ada, $ukur['nilai']);
        if ($beda !== []) {
            $catat('PERINGATAN', "kunjungan {$tgl} sudah ada — ukuran tidak diubah: ".implode('; ', $beda).'.');
        }
        if ($kesmas === []) {
            return false;
        }
        $ada->fill($kesmas)->save();

        return true;
    }

    /** @return array{nilai: array<string,mixed>, salah: array<string,string>} kunci = kolom data_anak */
    private function ukurBerkas(array $row, Anak $anak, string $tgl): array
    {
        $nilai = [];
        $salah = [];
        foreach (self::UKUR as $kolomBerkas => $kolom) {
            $sel = $this->sel($row, $kolomBerkas);
            if (NilaiSelKesmas::kosong($sel)) {
                continue;
            }
            $teks = NilaiSelKesmas::teks($sel);

            if ($kolom === 'posisi') {
                $v = NilaiSelKesmas::posisi($sel);
                if ($v === null) {
                    $salah[$kolom] = "posisi '{$teks}' tidak dikenal (Berdiri/Terlentang)";
                } else {
                    $nilai[$kolom] = $v;
                }
                continue;
            }

            $v = NilaiSelKesmas::angka($sel);
            if ($v === null) {
                $salah[$kolom] = "{$kolomBerkas} '{$teks}' bukan angka";
                continue;
            }
            if ($kolom === 'bb') {
                $satuan = SatuanBeratBadan::untuk($anak->tgl_lahir, $tgl);
                if (! SatuanBeratBadan::dalamRentang($v, $satuan)) {
                    [$min, $maks] = SatuanBeratBadan::RENTANG[$satuan];
                    $salah[$kolom] = $satuan === SatuanBeratBadan::GRAM
                        ? "bb '{$teks}' di luar {$min}–{$maks} gram — anak berumur di bawah 2 bulan pada {$tgl}, isi berat dalam gram (mis. 3250)"
                        : "bb '{$teks}' di luar {$min}–{$maks} kg — anak berumur 2 bulan atau lebih pada {$tgl}, isi berat dalam kg";
                    continue;
                }
                $v = SatuanBeratBadan::keKg($v, $satuan);
            } elseif (in_array($kolom, ['tb', 'lk', 'lla'], true) && $v <= 0) {
                $salah[$kolom] = "{$kolomBerkas} '{$teks}' harus lebih dari 0";
                continue;
            }
            $nilai[$kolom] = $v;
        }

        return ['nilai' => $nilai, 'salah' => $salah];
    }

    /** @return string[] kalimat "bb di berkas 10,5, tersimpan 10,2" per ukuran yang berbeda */
    private function bedaUkur(DataAnak $ada, array $ukur): array
    {
        $beda = [];
        foreach ($ukur as $kolom => $v) {
            $lama = $ada->{$kolom};
            $sama = match (true) {
                $kolom === 'posisi' => normalisasi_posisi($lama) === $v,
                $lama === null => false,
                $kolom === 'bb' => SatuanBeratBadan::sama((float) $lama, (float) $v),
                default => abs((float) $lama - (float) $v) < 0.005,
            };
            if (! $sama) {
                $beda[] = "{$kolom} di berkas ".self::tampil($v).', tersimpan '.($lama === null ? 'kosong' : self::tampil($lama));
            }
        }

        return $beda;
    }

    private static function tampil(mixed $v): string
    {
        return is_numeric($v) ? str_replace('.', ',', (string) (0 + $v)) : (string) $v;
    }
```

- [ ] **Step 4: Jalankan tes Task 4 & 5, pastikan lolos**

Run: `"$PHP" artisan test --filter="KesmasImportKunjunganTest|KesmasImportAnakTest"`
Expected: PASS (11 + 16 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Imports/KesmasImport.php tests/Feature/ImportKesmas/KesmasImportKunjunganTest.php
git commit -m "feat(import-kesmas): kunjungan baru wajib ukur, ukuran lama tak ditimpa, BB gram < 2 bulan"
```

---

### Task 6: Template, Job, controller, rute, halaman Import

**Files:**
- Create: `app/Exports/TemplateKesmas.php`
- Create: `app/Jobs/ImportKesmasJob.php`
- Modify: `app/Http/Controllers/ImportCsvController.php` (`VALID_TYPES`, `uploadKesmas`, `handleUpload`, `reimport`, `downloadTemplate`)
- Modify: `routes/web.php:143` (setelah rute `pj`)
- Modify: `resources/views/admin/import/index.blade.php` (tab, panel, chip, filter, label tipe Blade + JS)
- Test: `tests/Feature/ImportKesmas/ImportKesmasBerkasTest.php`

**Interfaces:**
- Consumes: `KesmasImport` (Task 4–5), `KolomKesmasImport::pilihan/kolom`, `KesmasRules::atribut()`, `PrioritasGiziService::duringMutedImport/refreshAll`.
- Produces: rute `admin.importCsv.kesmas` (input `file_kesmas`, `format_tanggal`); `GET admin.importCsv.template` dengan `type=kesmas`; `TemplateKesmas::kolom(): list<array{kolom,label,wajib,keterangan,pilihan}>`, `TemplateKesmas::buat(): string` (path berkas sementara).

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php

namespace Tests\Feature\ImportKesmas;

use App\Exports\TemplateKesmas;
use App\Imports\KesmasImport;
use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\ImportLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportKesmasBerkasTest extends TestCase
{
    use RefreshDatabase;

    private const MIME_XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /** @var string[] */
    private array $sementara = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['queue.default' => 'sync']);
    }

    protected function tearDown(): void
    {
        foreach ($this->sementara as $p) {
            @unlink($p);
        }
        parent::tearDown();
    }

    private function superadmin(): User
    {
        return User::factory()->create(['type' => 0]);
    }

    /** Unduh template lewat rute, isi baris 2 dari peta kolom => nilai, simpan ke berkas sementara. */
    private function templateTerisi(array $isi): string
    {
        $res = $this->get(route('admin.importCsv.template', 'kesmas'))->assertOk();
        $ss = IOFactory::load($res->baseResponse->getFile()->getPathname());
        $ws = $ss->getSheetByName('Data');
        $header = $ws->rangeToArray('A1:'.$ws->getHighestColumn().'1')[0];
        foreach ($isi as $kolom => $nilai) {
            $c = array_search($kolom, $header, true) + 1;
            $sel = $ws->getCell([$c, 2]);
            if (is_array($nilai) && isset($nilai['tgl'])) {
                $sel->setValue(Date::PHPToExcel(new \DateTime($nilai['tgl'])));
                $sel->getStyle()->getNumberFormat()->setFormatCode('dd/mm/yyyy');
            } else {
                $sel->setValueExplicit((string) $nilai, DataType::TYPE_STRING);
            }
        }
        $path = tempnam(sys_get_temp_dir(), 'kesmas').'.xlsx';
        (new Xlsx($ss))->save($path);
        $this->sementara[] = $path;

        return $path;
    }

    public function test_template_dua_sheet_dengan_header_kolom_dikenal_dan_dropdown(): void
    {
        $this->actingAs($this->superadmin());

        $res = $this->get(route('admin.importCsv.template', 'kesmas'))->assertOk();
        $ss = IOFactory::load($res->baseResponse->getFile()->getPathname());

        $this->assertSame(['Data', 'Petunjuk'], $ss->getSheetNames());
        $ws = $ss->getSheetByName('Data');
        $header = array_values(array_filter($ws->rangeToArray('A1:'.$ws->getHighestColumn().'1')[0]));
        $this->assertEqualsCanonicalizing(KesmasImport::kolomDikenal(), $header);
        $this->assertSame(1, $ws->getHighestDataRow(), 'template tanpa baris contoh');

        $kolomAir = array_search('air_bersih', $header, true) + 1;
        $validasi = $ws->getCell([$kolomAir, 2])->getDataValidation();
        $this->assertSame('list', $validasi->getType());
        $this->assertSame('"Ya,Tidak"', $validasi->getFormula1());
    }

    public function test_template_diisi_lalu_diimpor_ulang_petunjuk_tidak_terbaca(): void
    {
        $this->actingAs($this->superadmin());
        $anak = Anak::create(['nama' => 'Budi Santoso', 'nik' => '6474010101230001', 'jk' => 1,
            'tgl_lahir' => '2023-01-01', 'status' => 1, 'nama_ibu' => 'Siti Aminah']);

        $path = $this->templateTerisi([
            'nik_anak' => '6474010101230001', 'nama_anak' => 'Budi Santoso', 'jk' => 'L',
            'tgl_lahir' => ['tgl' => '2023-01-01'], 'nama_ortu' => 'Siti Aminah',
            'tgl_kunjungan' => ['tgl' => '2025-03-10'], 'bb' => '10,5', 'tb_cm' => '85', 'posisi' => 'Berdiri',
            'mbg' => 'Ya', 'air_bersih' => 'Ya',
        ]);

        $id = $this->postJson(route('admin.importCsv.kesmas'), [
            'file_kesmas' => new UploadedFile($path, 'kesmas.xlsx', self::MIME_XLSX, null, true),
            'format_tanggal' => 'auto',
        ])->assertOk()->assertJsonPath('ok', true)->json('log_id');

        $log = ImportLog::findOrFail($id);
        $this->assertSame('done', $log->status, implode('; ', $log->failures ?? []));
        $this->assertSame('kesmas', $log->type);
        $this->assertSame(1, (int) $log->success_count);
        $this->assertSame(0, (int) $log->failure_count);
        $this->assertEmpty($log->failures, 'sheet Petunjuk tidak boleh terbaca sebagai data');
        $this->assertSame(1, (int) $anak->fresh()->air_bersih);
        $this->assertSame(1, (int) DataAnak::sole()->mbg);
    }

    public function test_reimport_memakai_format_tanggal_yang_sama(): void
    {
        $this->actingAs($this->superadmin());
        Anak::create(['nama' => 'Budi Santoso', 'nik' => '6474010101230001', 'jk' => 1,
            'tgl_lahir' => '2023-01-01', 'status' => 1, 'nama_ibu' => 'Siti Aminah']);
        $path = $this->templateTerisi(['nama_anak' => 'Budi Santoso', 'jk' => 'L', 'tgl_lahir' => '01/01/2023',
            'nama_ortu' => 'Siti Aminah', 'air_bersih' => 'Ya']);
        $id = $this->postJson(route('admin.importCsv.kesmas'), [
            'file_kesmas' => new UploadedFile($path, 'kesmas.xlsx', self::MIME_XLSX, null, true),
            'format_tanggal' => 'dmy',
        ])->json('log_id');

        $baru = $this->postJson(route('admin.importCsv.reimport', $id))->assertOk()->json('log_id');

        $log = ImportLog::findOrFail($baru);
        $this->assertSame('dmy', $log->format_tanggal);
        $this->assertSame('done', $log->status, implode('; ', $log->failures ?? []));
    }

    public function test_csv_ditolak(): void
    {
        $this->actingAs($this->superadmin());

        $this->postJson(route('admin.importCsv.kesmas'), [
            'file_kesmas' => UploadedFile::fake()->createWithContent('kesmas.csv', "nama_anak,jk,tgl_lahir,nama_ortu\n"),
        ])->assertStatus(422)->assertJsonValidationErrors('file_kesmas');
    }

    public function test_bukan_superadmin_ditolak(): void
    {
        $this->actingAs(User::factory()->create(['type' => 1]));

        $this->get(route('admin.importCsv.template', 'kesmas'))->assertForbidden();
        $this->postJson(route('admin.importCsv.kesmas'), [
            'file_kesmas' => UploadedFile::fake()->create('kesmas.xlsx', 10, self::MIME_XLSX),
        ])->assertForbidden();
    }

    public function test_halaman_import_menampilkan_tab_kesmas(): void
    {
        $this->actingAs($this->superadmin());

        $this->get(route('admin.importCsv.index'))->assertOk()
            ->assertSee('id="panel-kesmas"', false)
            ->assertSee(route('admin.importCsv.kesmas'), false)
            ->assertSee(route('admin.importCsv.template', 'kesmas'), false);
    }
}
```

Catatan: admin `type => 1` lolos middleware grup `['auth', 'is_admin']` (`routes/web.php:91`) dan ditolak 403 oleh `abort_if(!isSuperAdmin())` di controller — pola yang sama dipakai `ImportXlsxTest`.

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `"$PHP" artisan test --filter=ImportKesmasBerkasTest`
Expected: FAIL — `Route [admin.importCsv.kesmas] not defined`

- [ ] **Step 3a: `app/Exports/TemplateKesmas.php`**

```php
<?php

namespace App\Exports;

use App\Http\Requests\Admin\Anak\KesmasRules;
use App\Services\KolomKesmasImport;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Template Import Data Kesmas, dibangun saat diunduh dari config('kesmas') supaya dropdown selalu sama
 * dengan form (spec 2026-10-15-import-kesmas-design.md §2). Sheet "Data" HARUS sheet pertama — hanya
 * sheet itu yang dibaca KesmasImport. Tanpa baris contoh: contoh yang lupa dihapus ikut terimpor.
 */
final class TemplateKesmas
{
    private const BARIS_DROPDOWN = 2000;

    /** @return list<array{kolom:string,label:string,wajib:string,keterangan:string,pilihan:?array}> */
    public static function kolom(): array
    {
        $baris = [
            ['nik_anak', 'NIK anak', 'Dianjurkan', '16 digit, ketik sebagai teks. Dipakai lebih dulu untuk mencari anak.', null],
            ['nama_anak', 'Nama anak', 'Wajib', 'Sesuai data anak di SIRINDU.', null],
            ['jk', 'Jenis kelamin', 'Wajib', 'L atau P.', ['L', 'P']],
            ['tgl_lahir', 'Tanggal lahir', 'Wajib', 'Sel tanggal Excel, 2023-01-31, atau 31/01/2023 (pilih format tanggal saat mengunggah).', null],
            ['nama_ortu', 'Nama ibu/orang tua', 'Wajib', 'Membedakan anak bernama mirip yang lahir di hari yang sama.', null],
            ['tgl_kunjungan', 'Tanggal kunjungan', 'Opsional', 'Kosongkan bila baris hanya mengisi data Kesmas per anak; kolom kunjungan di baris itu diabaikan.', null],
            ['bb', 'Berat badan', 'Wajib untuk kunjungan baru', 'GRAM bila anak berumur di bawah 2 bulan pada tanggal kunjungan (300–8000, mis. 3250); selain itu KG (1–150). Tanpa titik ribuan.', null],
            ['tb_cm', 'Tinggi/panjang badan (cm)', 'Wajib untuk kunjungan baru', 'Angka, koma desimal boleh.', null],
            ['posisi', 'Posisi ukur', 'Wajib untuk kunjungan baru', 'Berdiri atau Terlentang.', ['Berdiri', 'Terlentang']],
            ['lk_cm', 'Lingkar kepala (cm)', 'Opsional', 'Angka.', null],
            ['lila_cm', 'LiLA (cm)', 'Opsional', 'Angka.', null],
            ['zscore_bb_u', 'Z-score BB/U', 'Opsional', 'Angka.', null],
            ['zscore_pb_u', 'Z-score PB/U', 'Opsional', 'Angka.', null],
            ['zscore_bb_pb', 'Z-score BB/PB', 'Opsional', 'Angka.', null],
        ];
        $hasil = array_map(fn ($b) => array_combine(['kolom', 'label', 'wajib', 'keterangan', 'pilihan'], $b), $baris);

        $label = KesmasRules::atribut();
        foreach (['kunjungan' => 'Layanan per kunjungan — butuh tgl_kunjungan.', 'anak' => 'Data per anak.'] as $tingkat => $ket) {
            foreach (KolomKesmasImport::kolom($tingkat) as $kolom) {
                $pilihan = KolomKesmasImport::pilihan($kolom);
                $hasil[] = [
                    'kolom' => $kolom, 'label' => $label[$kolom] ?? $kolom, 'wajib' => 'Opsional',
                    'keterangan' => $ket.' '.match (true) {
                        $pilihan !== null => 'Pilih dari daftar.',
                        in_array($kolom, ['tgl_hbig', 'tgl_penanda_ckg'], true) => 'Tanggal.',
                        in_array($kolom, ['bbl', 'pbl', 'lk_lahir', 'usia_kehamilan_lahir'], true) => 'Angka.',
                        default => 'Teks bebas.',
                    },
                    'pilihan' => $pilihan,
                ];
            }
        }

        return $hasil;
    }

    /** @return string path berkas .xlsx sementara; pemanggil yang menghapusnya */
    public static function buat(): string
    {
        $kolom = self::kolom();
        $ss = new Spreadsheet();

        $data = $ss->getActiveSheet()->setTitle('Data');
        foreach ($kolom as $i => $k) {
            $c = $i + 1;
            $data->getCell([$c, 1])->setValue($k['kolom']);
            $data->getColumnDimensionByColumn($c)->setWidth(max(14, strlen($k['kolom']) + 4));
            $huruf = $data->getCell([$c, 1])->getColumn();
            if ($k['kolom'] === 'nik_anak') {
                $data->getStyle("{$huruf}2:{$huruf}".(self::BARIS_DROPDOWN + 1))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
            }
            if ($k['pilihan'] !== null) {
                $v = $data->getCell([$c, 2])->getDataValidation();
                $v->setType(DataValidation::TYPE_LIST)->setAllowBlank(true)->setShowDropDown(true)
                    ->setShowErrorMessage(true)->setErrorTitle('Pilihan tidak dikenal')
                    ->setError('Pilih salah satu dari daftar.')
                    ->setFormula1('"'.implode(',', $k['pilihan']).'"');
                $data->setDataValidation("{$huruf}2:{$huruf}".(self::BARIS_DROPDOWN + 1), $v);
            }
        }
        $data->getStyle('1:1')->getFont()->setBold(true);
        $data->freezePane('A2');

        $petunjuk = $ss->createSheet()->setTitle('Petunjuk');
        $petunjuk->fromArray([
            ['Import Data Kesmas — isi sheet "Data" mulai baris 2. Satu baris = satu anak pada satu kunjungan.'],
            ['Anak harus SUDAH ADA di SIRINDU; import ini tidak membuat anak baru dan tidak mengubah NIK.'],
            ['Sel kosong tidak mengubah data yang sudah ada. Ukuran kunjungan yang sudah tercatat tidak diubah.'],
            [],
            ['Kolom', 'Label', 'Wajib', 'Keterangan', 'Pilihan'],
        ]);
        foreach ($kolom as $i => $k) {
            $petunjuk->fromArray([[$k['kolom'], $k['label'], $k['wajib'], $k['keterangan'], $k['pilihan'] ? implode(', ', $k['pilihan']) : '']], null, 'A'.($i + 6));
        }
        $petunjuk->getStyle('A5:E5')->getFont()->setBold(true);
        foreach (['A' => 24, 'B' => 40, 'C' => 26, 'D' => 70, 'E' => 50] as $kol => $lebar) {
            $petunjuk->getColumnDimension($kol)->setWidth($lebar);
        }

        $ss->setActiveSheetIndex(0);
        $path = tempnam(sys_get_temp_dir(), 'tpl_kesmas').'.xlsx';
        (new Xlsx($ss))->save($path);

        return $path;
    }
}
```

- [ ] **Step 3b: `app/Jobs/ImportKesmasJob.php`**

```php
<?php

namespace App\Jobs;

use App\Imports\KesmasImport;
use App\Models\ImportLog;
use App\Services\PrioritasGiziService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ImportKesmasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 600;

    public function __construct(protected ImportLog $importLog) {}

    public function handle(): void
    {
        $this->importLog->update(['status' => 'processing', 'started_at' => now()]);

        try {
            $path = Storage::path($this->importLog->file_path);

            if (!file_exists($path)) {
                throw new \RuntimeException("File tidak ditemukan: {$this->importLog->file_path}");
            }

            $import = (new KesmasImport($this->importLog->user_id))->setFormatTanggal($this->importLog->format_tanggal);
            app(PrioritasGiziService::class)->duringMutedImport(fn () => Excel::import($import, $path));
            app(PrioritasGiziService::class)->refreshAll();
            $results = $import->getResults();

            $this->importLog->update([
                'status'        => 'done',
                'success_count' => $results['success'],
                'failure_count' => $results['error_count'],
                'failures'      => $results['failures'] ?: null,
                'completed_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error("ImportKesmasJob gagal [{$this->importLog->id}]: ".$e->getMessage());
            $this->importLog->update([
                'status'       => 'failed',
                'failures'     => ['Import gagal: '.$e->getMessage()],
                'completed_at' => now(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->importLog->update([
            'status'       => 'failed',
            'failures'     => ['Job gagal dieksekusi: '.$exception->getMessage()],
            'completed_at' => now(),
        ]);
    }
}
```

- [ ] **Step 3c: `ImportCsvController`**

1. `use App\Exports\TemplateKesmas;` dan `use App\Jobs\ImportKesmasJob;` di blok `use`.
2. `VALID_TYPES`: tambah `'kesmas' => 'Data Kesmas',` setelah `'pj'`.
3. Method baru setelah `uploadPj()`:

```php
    /** Data Kesmas untuk anak yang sudah ada — Excel saja (spec 2026-10-15-import-kesmas-design.md). */
    public function uploadKesmas(Request $request)
    {
        return $this->handleUpload($request, 'kesmas', 'file_kesmas');
    }
```

4. Di `handleUpload()`, ganti blok validasi `$isCapil` menjadi:

```php
        // Capil (multi-sheet) dan Kesmas (template dua sheet) khusus Excel; tipe lain menerima Excel
        // maupun CSV — pembacanya (Maatwebsite) mengenali format dari ekstensi.
        $hanyaExcel = in_array($type, ['capil', 'kesmas'], true);
        $request->validate([
            $inputName => $hanyaExcel
                ? 'required|file|mimes:xlsx,xls|max:20480'
                : 'required|file|mimes:xlsx,xls,csv,txt|max:20480',
            'format_tanggal' => TanggalBerkas::ATURAN,
        ], [
            'format_tanggal.in'  => 'Format penanggalan tidak dikenal.',
            "{$inputName}.mimes" => $hanyaExcel
                ? 'File harus berformat Excel (.xlsx/.xls).'
                : 'File harus berformat Excel (.xlsx/.xls) atau CSV (.csv).',
            "{$inputName}.max"   => 'Ukuran file maksimal 20 MB.',
        ]);
```

5. Tambah `'kesmas' => ImportKesmasJob::class,` ke kedua `match` (`handleUpload` dan `reimport`).
6. Di `downloadTemplate()`, setelah `abort_if(!auth()->user()->isSuperAdmin(), 403);`:

```php
        if ($type === 'kesmas') {
            return response()->download(TemplateKesmas::buat(), 'template_import_kesmas.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend();
        }
```

- [ ] **Step 3d: Rute** — di `routes/web.php`, setelah baris `Route::post('pj', ...)`:

```php
        Route::post('kesmas',     [App\Http\Controllers\ImportCsvController::class, 'uploadKesmas'])      ->name('admin.importCsv.kesmas');
```

- [ ] **Step 3e: View `resources/views/admin/import/index.blade.php`**

1. CSS chip, setelah `.imp-chip-ukur …`:

```css
.imp-chip-kesmas     { background: oklch(0.92 0.07 175 / 0.35); color: oklch(0.38 0.11 175); }
```

2. Tombol tab, setelah tombol `data-tab="pj"` (`</button>` penutupnya):

```blade
    <button class="imp-tab-btn" data-tab="kesmas"
        role="tab" aria-selected="false" aria-controls="panel-kesmas" id="tab-kesmas">
        <span class="material-symbols-outlined">health_and_safety</span>
        Data Kesmas
    </button>
```

3. Panel baru, tepat sebelum komentar blok `Riwayat Import`:

```blade
{{-- =====================================================================
     PANEL: Data Kesmas
     ===================================================================== --}}
<div class="imp-panel" id="panel-kesmas" role="tabpanel" aria-labelledby="tab-kesmas">
    <div class="imp-panel-grid">
        <div class="imp-guide">
            <p class="imp-guide__label">Data yang diimpor</p>
            <p>Field Kesmas anak (lingkungan rumah, riwayat lahir, skrining neonatal) dan layanan per kunjungan (KN, MTBM/MTBS, CKG, MBG, rujukan, dll.). Satu baris = satu anak pada satu kunjungan; tanpa tanggal kunjungan, baris hanya mengisi data per anak.</p>
            <div class="imp-guide__rule">
                <strong>Hanya anak yang sudah ada</strong>
                Anak dicari lewat <strong>NIK</strong>, lalu <strong>nama + tanggal lahir + jenis kelamin + nama ortu</strong>. Anak yang tidak ketemu atau lebih dari satu kandidat dilaporkan &mdash; tidak dibuat baru, dan NIK tidak diubah.
            </div>
            <div class="imp-guide__rule">
                <strong>Yang ditimpa</strong>
                Sel berisi menimpa data Kesmas lama; sel kosong tidak mengubah apa pun. Ukuran kunjungan yang sudah tercatat <strong>tidak diubah</strong> &mdash; perbedaannya dilaporkan.
            </div>
            <div class="imp-guide__rule">
                <strong>Berat badan</strong>
                Kunjungan baru wajib berisi BB, TB, dan posisi. BB dalam <strong>gram</strong> bila anak berumur di bawah 2 bulan pada tanggal kunjungan (mis. 3250), selain itu <strong>kg</strong>.
            </div>
            <a href="{{ route('admin.importCsv.template', 'kesmas') }}" class="imp-template-link" download>
                <span class="material-symbols-outlined">download</span>
                Unduh Template Excel
            </a>
        </div>
        <div class="imp-upload-col">
            <form method="POST" action="{{ route('admin.importCsv.kesmas') }}"
                  enctype="multipart/form-data" class="imp-form" data-type="kesmas">
                @csrf
                <label class="imp-upload-zone" for="file-kesmas" id="zone-kesmas">
                    <span class="material-symbols-outlined imp-upload-zone__icon">cloud_upload</span>
                    <span class="imp-upload-zone__label">Klik atau seret file Excel ke sini</span>
                    <span class="imp-upload-zone__hint">Format .xlsx/.xls &mdash; maksimal 20 MB</span>
                    <input type="file" id="file-kesmas" name="file_kesmas" accept=".xlsx,.xls" required>
                </label>
                <div class="imp-fname" id="fname-kesmas">
                    <span class="material-symbols-outlined">description</span>
                    <span>Belum ada file dipilih</span>
                </div>
                @include('admin.partials.pilihan-format-tanggal', ['id' => 'format-tanggal-kesmas'])
                <button type="submit" class="imp-btn-upload">
                    <span class="material-symbols-outlined">upload</span>
                    Upload &amp; Import
                </button>
            </form>
        </div>
    </div>
</div>

```

4. Filter riwayat, setelah tombol `data-filter="ukur"`:

```blade
            <button class="imp-filter-btn" data-filter="kesmas">Kesmas</button>
```

5. Label tipe di tabel Blade: tambah `'kesmas'=>'Kesmas'` ke array `['anak'=>'Anak', …,'hasil_lab'=>'Hasil Lab']`.
6. JS: tambah `kesmas: 'Kesmas'` ke `typeLabel` dan `kesmas: 'imp-chip-kesmas'` ke `typeChip`.

- [ ] **Step 4: Jalankan, pastikan lolos**

Run: `"$PHP" artisan test --filter=ImportKesmasBerkasTest`
Expected: PASS (6 tests). Bila tes round-trip gagal dengan pesan kolom tak dikenal dari Petunjuk, `sheets()` di `KesmasImport` tidak terpakai — pastikan kelas mengimplementasikan `WithMultipleSheets`.

- [ ] **Step 5: Jalankan tes import yang ada (regresi controller)**

Run: `"$PHP" artisan test --filter="ImportXlsxTest|FormatTanggalImportTest|ImportCapilTest"`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Exports/TemplateKesmas.php app/Jobs/ImportKesmasJob.php app/Http/Controllers/ImportCsvController.php routes/web.php resources/views/admin/import/index.blade.php tests/Feature/ImportKesmas/ImportKesmasBerkasTest.php
git commit -m "feat(import-kesmas): tipe import Data Kesmas — template Excel, job, tab halaman import"
```

---

### Task 7: Dokumentasi & regresi penuh

**Files:**
- Modify: `CLAUDE.md` (bagian baru di akhir)

- [ ] **Step 1: Tambah catatan di `CLAUDE.md`**

```markdown
### Import Data Kesmas: hanya anak yang ada, ukuran lama tidak ditimpa

Tipe import `kesmas` (spec `docs/superpowers/specs/2026-10-15-import-kesmas-design.md`) mengisi field Kesmas dari
Excel. Ini jembatan sementara — pengisian otomatis dari modul imunisasi/OT/MBG menyusul, dan import ini **bukan**
aturan "sumber mana yang menang".

- **Tidak membuat anak, tidak mengubah NIK.** Penautan di `PenautanAnakKesmas`: NIK hanya dipercaya bila tgl lahir
  sama atau nama mirip (72% anak ganda di prod ber-NIK ibu/salah ketik), lalu `PenautanAnakImport` dengan nama ortu.
- **Field Kesmas "isi yang diberikan", ukuran kunjungan yang ada tidak disentuh** (bentrok → PERINGATAN). Kunjungan
  baru wajib BB/TB/posisi; BB gram bila < 2 bulan lewat `SatuanBeratBadan` — sama dengan form.
- Hanya sheet pertama dibaca (`WithMultipleSheets`) — sheet Petunjuk template ikut terbaca bila dilepas.
- Kolom diturunkan dari `KesmasRules`; field Kesmas baru otomatis ikut template & import. Template dibangun saat
  diunduh (`TemplateKesmas`), bukan file statis.
- `sasaran_balita_kesmas` sengaja tidak ada di daftar kolom.

Dikunci `tests/Feature/ImportKesmas/*`, `tests/Unit/Support/NilaiSelKesmasTest.php`.
```

- [ ] **Step 2: Jalankan seluruh suite (±10+ menit, jangan paralel dengan proses tes lain)**

Run: `"$PHP" artisan test` (di background)
Expected: semua PASS. Bila ada kegagalan di luar `ImportKesmas`, bandingkan dengan `git stash` di `main` sebelum menyimpulkan regresi.

- [ ] **Step 3: Commit**

```bash
git add CLAUDE.md
git commit -m "docs: catatan Import Data Kesmas di CLAUDE.md"
```

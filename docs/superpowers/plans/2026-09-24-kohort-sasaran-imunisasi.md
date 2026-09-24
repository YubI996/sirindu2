# Kohort Sasaran Imunisasi — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dasbor imunisasi rutin menghitung sasaran & capaian atas kohort tahunan (lahir 1 April X-1 s.d. 31 Maret X, dikelompokkan BBL/SI/Baduta), sementara angka operasional tetap memakai tanggal berjalan.

**Architecture:** Value object `App\Support\KohortImunisasi` (immutable, murni PHP, tanpa DB) memegang seluruh aritmetika tanggal dan dibawa sebagai parameter eksplisit ke tiap method statistik di `ImunisasiStatusService`. Helper privat `scopeKohort()` mengganti tiap `whereRaw('TIMESTAMPDIFF(MONTH, tgl_lahir, CURDATE()) ...')` dengan `whereBetween('tgl_lahir', ...)`. Angka operasional (`butuh_kejar`, sasaran harian) dipisah ke method tanpa parameter kohort.

**Tech Stack:** Laravel 12, PHP 8.4.7, MySQL 8.0.30, PHPUnit (`php artisan test`), Blade.

**Spec:** `docs/superpowers/specs/2026-09-24-kohort-sasaran-imunisasi-design.md`

## Global Constraints

- Sasaran tahun X = anak lahir **1 April X-1 s.d. 31 Maret X**, inklusif. Umur dipotret **31 Maret X**.
- Rentang lahir per kelompok: `BBL` = `[1 Feb X, 31 Mar X]` · `SI` = `[1 Apr X-1, 31 Jan X]` · `SELURUH` = `[1 Apr X-1, 31 Mar X]` · `BADUTA` = `[1 Apr X-2, 31 Mar X-1]`.
- Batas atas SI berarti **"belum genap 12 bulan"**, bukan "≤ 11 bulan 29 hari". SI = seluruh kohort dikurangi BBL, tanpa syarat tambahan.
- **Setiap agregat per anak WAJIB lewat `eachAnak()`** (`chunkById(500)` + `select(KOLOM_ANAK_AGREGAT)`). Jangan pernah `->get()` populasi — itu penyebab OOM prod 16 Sep 2026.
- `ImunisasiStatusService::flushCache()` di `setUp()` **tiap** test yang menyentuh service ini.
- Dropdown tahun: tahun berjalan mundur 4 tahun (5 pilihan). Nilai di luar daftar → kembali ke default, **bukan** error 500.
- Kategori vaksin `Tambahan` (DT, Td, HPV, MR-Sekolah) tetap dikecualikan dari cakupan antigen.
- Satuan berbeda: `jenis_vaksin.usia_pemberian_*` dalam **hari**; `kelompok_vaksin.usia_pemberian_*` dalam **bulan**.
- Test DB = `sirindu_testing`. **Jangan jalankan dua proses tes bersamaan** — DB-nya dipakai bersama.
- Setelah mengubah blade mana pun: `php artisan view:clear` sebelum menyimpulkan perubahan tak berefek.
- Blade: `@section('x') isi @endsection` **wajib** ada spasi sebelum `@endsection`, kalau tidak directive-nya tidak ter-compile.
- **`CURDATE()` dievaluasi MySQL, bukan PHP.** `$this->travelTo()` Laravel TIDAK mempengaruhinya. Test kohort memakai tanggal absolut + `KohortImunisasi::dari(2026)`; test operasional (`getButuhKejar`) memakai `now()->subMonths(...)` relatif.

## Review Focus

1. **`?tahun=abc`, `?tahun=0`, `?tahun=-5`, `?tahun=2099`** → halaman tetap terbuka dengan tahun default, bukan 500 atau kohort tahun 0. → Task 9.
2. **Wilayah terfilter yang tidak punya anak di kohort** (penyebut = 0) → persen `0.0`, tanpa division-by-zero dan tanpa `NAN` di JSON grafik. → Task 3.
3. **Antigen dengan `usia_pemberian_max` tepat 59, 60, 364, atau 365 hari** → jatuh ke kelompok penyebut yang benar; aturan `<=` harus diuji tepat di batasnya, bukan di tengah rentang. → Task 6.
4. **Anak lahir tepat di keempat batas (1 Apr, 31 Jan, 1 Feb, 31 Mar) dan pada 29 Februari kabisat** → masuk kelompok yang benar, tidak ada tanggal yang jatuh di luar semua kelompok. → Task 1.
5. **`tgl_lahir` di masa depan** (salah ketik petugas, mis. 2030-01-01) → tidak masuk kohort mana pun dan tidak membuat perhitungan error. → Task 2.

---

### Task 1: Value object `KohortImunisasi`

**Files:**
- Create: `app/Support/KohortImunisasi.php`
- Test: `tests/Unit/Support/KohortImunisasiTest.php`

**Interfaces:**
- Consumes: tidak ada (task pertama)
- Produces:
  - `KohortImunisasi::dari(int $tahun): self`
  - `KohortImunisasi::pilihanTahun(?int $tahunIni = null): list<int>`
  - `KohortImunisasi::tahunTervalidasi(mixed $input, ?int $tahunIni = null): int`
  - `->tahun(): int`
  - `->rentang(string $kelompok): array{0: string, 1: string}` — `Y-m-d`, inklusif; kelompok `BBL|SI|SELURUH|BADUTA`
  - `->kelompokDari(string $tglLahir): list<string>`
  - `->label(): string`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Support;

use App\Support\KohortImunisasi;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class KohortImunisasiTest extends TestCase
{
    public function test_rentang_tiap_kelompok_untuk_tahun_2026(): void
    {
        $k = KohortImunisasi::dari(2026);

        $this->assertSame(['2026-02-01', '2026-03-31'], $k->rentang('BBL'));
        $this->assertSame(['2025-04-01', '2026-01-31'], $k->rentang('SI'));
        $this->assertSame(['2025-04-01', '2026-03-31'], $k->rentang('SELURUH'));
        $this->assertSame(['2024-04-01', '2025-03-31'], $k->rentang('BADUTA'));
    }

    public function test_anak_lahir_hari_pertama_kohort_masuk_si_bukan_terbuang(): void
    {
        // Lahir 1 Apr 2025 berumur 11 bln 30 hr pada 31 Mar 2026. Membaca
        // "11 bln 29 hr" harfiah akan membuang hari pertama kohortnya sendiri.
        $this->assertSame(['SI', 'SELURUH'], KohortImunisasi::dari(2026)->kelompokDari('2025-04-01'));
    }

    public function test_keempat_batas_tanggal_jatuh_di_kelompok_yang_benar(): void
    {
        $k = KohortImunisasi::dari(2026);

        $this->assertSame(['SI', 'SELURUH'],  $k->kelompokDari('2026-01-31'), 'Hari terakhir SI.');
        $this->assertSame(['BBL', 'SELURUH'], $k->kelompokDari('2026-02-01'), 'Hari pertama BBL.');
        $this->assertSame(['BBL', 'SELURUH'], $k->kelompokDari('2026-03-31'), 'Hari terakhir kohort.');
        $this->assertSame(['BADUTA'],         $k->kelompokDari('2025-03-31'), 'Hari terakhir kohort 2025.');
    }

    public function test_bbl_dan_si_partisi_bersih_tanpa_tumpang_tindih(): void
    {
        $k = KohortImunisasi::dari(2026);

        foreach (['2025-04-01', '2025-09-15', '2026-01-31', '2026-02-01', '2026-03-31'] as $tgl) {
            $anggota = array_intersect($k->kelompokDari($tgl), ['BBL', 'SI']);
            $this->assertCount(1, $anggota, "Tanggal {$tgl} harus masuk tepat satu dari BBL/SI.");
        }
    }

    public function test_tanggal_lahir_kabisat_29_februari(): void
    {
        $this->assertSame(['BBL', 'SELURUH'], KohortImunisasi::dari(2024)->kelompokDari('2024-02-29'));
        $this->assertSame(['BADUTA'],         KohortImunisasi::dari(2025)->kelompokDari('2024-02-29'));
    }

    public function test_tanggal_di_luar_semua_kelompok(): void
    {
        $k = KohortImunisasi::dari(2026);

        $this->assertSame([], $k->kelompokDari('2024-03-31'), 'Sebelum kohort Baduta.');
        $this->assertSame([], $k->kelompokDari('2026-04-01'), 'Sesudah kohort berjalan.');
        $this->assertSame([], $k->kelompokDari('2030-01-01'), 'Tanggal masa depan salah ketik.');
    }

    public function test_pilihan_tahun_lima_tahun_menurun(): void
    {
        $this->assertSame([2026, 2025, 2024, 2023, 2022], KohortImunisasi::pilihanTahun(2026));
    }

    public function test_tahun_tervalidasi_mengembalikan_default_untuk_input_ngawur(): void
    {
        foreach ([null, '', 'abc', '0', '-5', '2099', '2026.5', '1999'] as $input) {
            $this->assertSame(
                2026,
                KohortImunisasi::tahunTervalidasi($input, 2026),
                'Input ngawur harus jatuh ke default, bukan melempar atau membuat kohort aneh.'
            );
        }

        $this->assertSame(2024, KohortImunisasi::tahunTervalidasi('2024', 2026));
        $this->assertSame(2024, KohortImunisasi::tahunTervalidasi(2024, 2026));
    }

    public function test_kelompok_tak_dikenal_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        KohortImunisasi::dari(2026)->rentang('BALITA');
    }

    public function test_tahun_tak_masuk_akal_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        KohortImunisasi::dari(1900);
    }

    public function test_label_menyebut_periode_dan_potret(): void
    {
        $this->assertSame(
            'Kohort 2026 · lahir 1 Apr 2025 – 31 Mar 2026 · potret umur 31 Mar 2026',
            KohortImunisasi::dari(2026)->label()
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Support/KohortImunisasiTest.php`
Expected: FAIL — `Class "App\Support\KohortImunisasi" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php
// app/Support/KohortImunisasi.php

namespace App\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Kohort sasaran imunisasi — spec docs/superpowers/specs/2026-09-24-kohort-sasaran-imunisasi-design.md.
 *
 * Sasaran tahun X = anak lahir 1 April X-1 s.d. 31 Maret X, umur dipotret pada
 * 31 Maret X. Batas atas SI dibaca "belum genap 12 bulan": anak yang lahir
 * 1 April X-1 berumur 11 bulan 30 hari di hari potret, jadi membaca "11 bln
 * 29 hr" harfiah justru membuang hari pertama kohortnya sendiri.
 *
 * Keempat batas jatuh di tanggal kalender tetap, jadi tidak ada operasi
 * "tambah N bulan" yang bisa meleset di akhir bulan atau tahun kabisat.
 * Murni PHP, tanpa DB — sebangun dengan PeriodeKesmas.
 */
final class KohortImunisasi
{
    /** Urutannya menentukan urutan keluaran kelompokDari(). */
    public const KELOMPOK = ['BBL', 'SI', 'SELURUH', 'BADUTA'];

    private function __construct(
        private readonly int $tahun,
        private readonly CarbonImmutable $lahirAwal,
        private readonly CarbonImmutable $lahirAkhir,
    ) {
    }

    public static function dari(int $tahun): self
    {
        if ($tahun < 2000 || $tahun > 2100) {
            throw new InvalidArgumentException("Tahun kohort di luar akal: {$tahun}");
        }

        return new self(
            $tahun,
            CarbonImmutable::create($tahun - 1, 4, 1)->startOfDay(),
            CarbonImmutable::create($tahun, 3, 31)->startOfDay(),
        );
    }

    public function tahun(): int
    {
        return $this->tahun;
    }

    /**
     * Rentang tanggal lahir satu kelompok, inklusif di kedua ujung.
     *
     * @return array{0: string, 1: string} [awal, akhir] format Y-m-d
     */
    public function rentang(string $kelompok): array
    {
        $t = $this->tahun;
        $tgl = fn (int $th, int $b, int $h) => CarbonImmutable::create($th, $b, $h)->toDateString();

        return match ($kelompok) {
            'BBL'     => [$tgl($t, 2, 1),     $tgl($t, 3, 31)],
            'SI'      => [$tgl($t - 1, 4, 1), $tgl($t, 1, 31)],
            'SELURUH' => [$tgl($t - 1, 4, 1), $tgl($t, 3, 31)],
            'BADUTA'  => [$tgl($t - 2, 4, 1), $tgl($t - 1, 3, 31)],
            default   => throw new InvalidArgumentException("Kelompok kohort tidak dikenal: {$kelompok}"),
        };
    }

    /**
     * Kelompok mana saja yang memuat tanggal lahir ini. BBL & SI saling
     * meniadakan; SELURUH memuat keduanya; BADUTA terpisah dari ketiganya.
     *
     * @return list<string>
     */
    public function kelompokDari(string $tglLahir): array
    {
        $tgl = substr($tglLahir, 0, 10);
        $hasil = [];

        foreach (self::KELOMPOK as $kelompok) {
            [$awal, $akhir] = $this->rentang($kelompok);
            if ($tgl >= $awal && $tgl <= $akhir) {
                $hasil[] = $kelompok;
            }
        }

        return $hasil;
    }

    public function label(): string
    {
        $f = fn (CarbonImmutable $d) => $d->format('j M Y');

        return sprintf(
            'Kohort %d · lahir %s – %s · potret umur %s',
            $this->tahun,
            $f($this->lahirAwal),
            $f($this->lahirAkhir),
            $f($this->lahirAkhir),
        );
    }

    /**
     * Pilihan dropdown: tahun berjalan mundur 4 tahun. Kohort yang lebih tua
     * sudah lewat usia imunisasi rutin.
     *
     * @return list<int>
     */
    public static function pilihanTahun(?int $tahunIni = null): array
    {
        $tahunIni ??= (int) date('Y');

        return range($tahunIni, $tahunIni - 4);
    }

    /** Tahun dari input mentah query string; yang di luar daftar → default. */
    public static function tahunTervalidasi(mixed $input, ?int $tahunIni = null): int
    {
        $tahunIni ??= (int) date('Y');
        $tahun = filter_var($input, FILTER_VALIDATE_INT);

        return ($tahun !== false && in_array($tahun, self::pilihanTahun($tahunIni), true))
            ? $tahun
            : $tahunIni;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Support/KohortImunisasiTest.php`
Expected: PASS — 11 tests

- [ ] **Step 5: Commit**

```bash
git add app/Support/KohortImunisasi.php tests/Unit/Support/KohortImunisasiTest.php
git commit -m "feat(imunisasi): value object KohortImunisasi — periode 1 Apr–31 Mar & kelompok BBL/SI/Baduta"
```

---

### Task 2: `scopeKohort()` + `getRingkasanSasaran()` ke BBL/SI/Baduta

**Files:**
- Modify: `app/Services/ImunisasiStatusService.php` (tambah `scopeKohort()`; ganti `getRingkasanSasaran()` di baris ~430)
- Test: `tests/Feature/Imunisasi/ImunisasiRutinDashboardServiceTest.php`

**Interfaces:**
- Consumes: `KohortImunisasi::dari()`, `->rentang()`, `->tahun()`, `->label()`
- Produces:
  - `private scopeKohort($query, KohortImunisasi $kohort, string $kelompok)` — dipakai Task 3, 4, 5, 8
  - `getRingkasanSasaran(KohortImunisasi $kohort, array $filters = []): array{tahun: int, label: string, bbl: array{jumlah: int, rentang: array}, si: ..., baduta: ...}`

- [ ] **Step 1: Write the failing test**

Ganti method `test_sasaran_menghitung_bayi_0_11_bulan_dan_baduta_terpisah()` yang lama dengan:

```php
    public function test_sasaran_memilah_bbl_si_dan_baduta_menurut_kohort(): void
    {
        $this->anak(['tgl_lahir' => '2025-04-01']); // SI  — hari pertama kohort 2026
        $this->anak(['tgl_lahir' => '2025-09-15']); // SI
        $this->anak(['tgl_lahir' => '2026-01-31']); // SI  — hari terakhir SI
        $this->anak(['tgl_lahir' => '2026-02-01']); // BBL — hari pertama BBL
        $this->anak(['tgl_lahir' => '2026-03-31']); // BBL — hari terakhir kohort
        $this->anak(['tgl_lahir' => '2024-06-10']); // Baduta (kohort 2025)
        $this->anak(['tgl_lahir' => '2025-03-31']); // Baduta — hari terakhir kohort 2025
        $this->anak(['tgl_lahir' => '2024-03-31']); // di luar semua kelompok
        $this->anak(['tgl_lahir' => '2026-04-01']); // di luar — sudah kohort 2027

        $sasaran = $this->service->getRingkasanSasaran(KohortImunisasi::dari(2026));

        $this->assertSame(2, $sasaran['bbl']['jumlah']);
        $this->assertSame(3, $sasaran['si']['jumlah']);
        $this->assertSame(2, $sasaran['baduta']['jumlah']);
        $this->assertSame(2026, $sasaran['tahun']);
        $this->assertSame(['2026-02-01', '2026-03-31'], $sasaran['bbl']['rentang']);
    }

    public function test_tanggal_lahir_masa_depan_tidak_masuk_kohort_mana_pun(): void
    {
        // Salah ketik petugas. Tidak boleh masuk hitungan, tidak boleh error.
        $this->anak(['tgl_lahir' => '2030-01-01']);
        $this->anak(['tgl_lahir' => '2025-09-15']); // SI, pembanding

        $sasaran = $this->service->getRingkasanSasaran(KohortImunisasi::dari(2026));

        $this->assertSame(0, $sasaran['bbl']['jumlah']);
        $this->assertSame(1, $sasaran['si']['jumlah']);
        $this->assertSame(0, $sasaran['baduta']['jumlah']);
    }

    public function test_sasaran_menghormati_filter_wilayah(): void
    {
        $this->anak(['tgl_lahir' => '2025-09-15', 'id_kel' => 1]);
        $this->anak(['tgl_lahir' => '2025-09-15', 'id_kel' => 2]);

        $sasaran = $this->service->getRingkasanSasaran(KohortImunisasi::dari(2026), ['id_kelurahan' => 1]);

        $this->assertSame(1, $sasaran['si']['jumlah']);
    }
```

Tambahkan `use App\Support\KohortImunisasi;` di bagian atas berkas tes.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Imunisasi/ImunisasiRutinDashboardServiceTest.php --filter=test_sasaran_memilah_bbl_si_dan_baduta_menurut_kohort`
Expected: FAIL — `ArgumentCountError` / `getRingkasanSasaran()` belum menerima `KohortImunisasi`

- [ ] **Step 3: Write minimal implementation**

Tambah `use App\Support\KohortImunisasi;` di `ImunisasiStatusService`, lalu tambahkan helper dan ganti method:

```php
    /**
     * Batasi query anak ke rentang tanggal lahir satu kelompok kohort.
     * Ini pengganti seluruh `whereRaw('TIMESTAMPDIFF(MONTH, tgl_lahir,
     * CURDATE()) ...')` di jalur statistik: penyebutnya jadi tetap, tidak
     * bergeser tiap hari, sehingga angkanya bisa dikunci sebagai capaian
     * tahun tertentu.
     *
     * @template TQuery of \Illuminate\Database\Eloquent\Builder
     * @param  TQuery  $query
     * @return TQuery
     */
    private function scopeKohort($query, KohortImunisasi $kohort, string $kelompok)
    {
        return $query->whereBetween('tgl_lahir', $kohort->rentang($kelompok));
    }

    /**
     * Populasi sasaran satu tahun kohort, dipilah BBL / SI / Baduta.
     * BBL & SI adalah partisi bersih (tiap anak tepat satu kelompok);
     * Baduta diambil dari kohort tahun sebelumnya secara utuh.
     *
     * @param  array{id_kecamatan?: int, id_kelurahan?: int, id_rt?: int, id_posyandu?: int, id_puskesmas?: int}  $filters
     * @return array{tahun: int, label: string,
     *               bbl: array{jumlah: int, rentang: array{0: string, 1: string}},
     *               si: array{jumlah: int, rentang: array{0: string, 1: string}},
     *               baduta: array{jumlah: int, rentang: array{0: string, 1: string}}}
     */
    public function getRingkasanSasaran(KohortImunisasi $kohort, array $filters = []): array
    {
        $kelompok = function (string $nama) use ($kohort, $filters): array {
            $jumlah = $this->scopeKohort(
                $this->applyWilayahFilters(Anak::query(), $filters),
                $kohort,
                $nama
            )->count();

            return ['jumlah' => $jumlah, 'rentang' => $kohort->rentang($nama)];
        };

        return [
            'tahun'  => $kohort->tahun(),
            'label'  => $kohort->label(),
            'bbl'    => $kelompok('BBL'),
            'si'     => $kelompok('SI'),
            'baduta' => $kelompok('BADUTA'),
        ];
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Imunisasi/ImunisasiRutinDashboardServiceTest.php --filter="sasaran|masa_depan"`
Expected: PASS — 3 tests

- [ ] **Step 5: Commit**

```bash
git add app/Services/ImunisasiStatusService.php tests/Feature/Imunisasi/ImunisasiRutinDashboardServiceTest.php
git commit -m "feat(imunisasi): getRingkasanSasaran pakai kohort BBL/SI/Baduta"
```

---

### Task 3: Cakupan IDL ke kohort SI, `butuh_kejar` dipisah jadi operasional

**Files:**
- Modify: `app/Services/ImunisasiStatusService.php` (`getIdlCoverage()` baris ~336; tambah `getButuhKejar()`)
- Test: `tests/Feature/Imunisasi/ImunisasiRutinDashboardServiceTest.php`

**Interfaces:**
- Consumes: `scopeKohort()` (Task 2), `kejarFlags()` (sudah ada, baris 220)
- Produces:
  - `getIdlCoverage(KohortImunisasi $kohort, array $filters = []): array{total: int, idl_lengkap: int, persen: float, per_kelurahan: array}` — parameter `withKejar` **dihapus**, kunci `butuh_kejar` **dihapus** dari keluaran
  - `getButuhKejar(array $filters = []): int`

**Catatan:** `getButuhKejar()` mempertahankan populasi lama (anak ≥ 12 bulan menurut `CURDATE()`) supaya nilainya tidak berubah oleh pekerjaan ini. Memperluasnya ke bayi < 12 bulan adalah keputusan terpisah, di luar lingkup spec ini.

- [ ] **Step 1: Write the failing test**

```php
    public function test_cakupan_idl_penyebutnya_kohort_si(): void
    {
        $siLengkap = $this->anak(['tgl_lahir' => '2025-05-10']);
        $this->lengkapiIdl($siLengkap);
        $this->anak(['tgl_lahir' => '2025-06-10']);  // SI, belum lengkap
        $this->anak(['tgl_lahir' => '2026-03-01']);  // BBL — bukan penyebut IDL
        $this->anak(['tgl_lahir' => '2024-06-10']);  // Baduta — bukan penyebut IDL

        $coverage = $this->service->getIdlCoverage(KohortImunisasi::dari(2026));

        $this->assertSame(2, $coverage['total'], 'Penyebut IDL = SI saja.');
        $this->assertSame(1, $coverage['idl_lengkap']);
        $this->assertSame(50.0, $coverage['persen']);
        $this->assertArrayNotHasKey('butuh_kejar', $coverage, 'butuh_kejar pindah ke method operasional sendiri.');
    }

    public function test_cakupan_idl_kohort_kosong_menghasilkan_nol_bukan_error(): void
    {
        $this->anak(['tgl_lahir' => '2025-06-10', 'id_kel' => 1]);

        $coverage = $this->service->getIdlCoverage(KohortImunisasi::dari(2026), ['id_kelurahan' => 99]);

        $this->assertSame(0, $coverage['total']);
        $this->assertSame(0.0, $coverage['persen']);
        $this->assertFalse(is_nan($coverage['persen']), 'Penyebut nol tidak boleh menghasilkan NAN di JSON grafik.');
    }

    public function test_butuh_kejar_tidak_berubah_saat_tahun_kohort_diganti(): void
    {
        // Angka operasional: dihitung atas tanggal berjalan, sengaja TIDAK
        // mengikuti dropdown tahun. Kartunya menaut ke halaman Proyeksi yang
        // juga memakai tanggal berjalan — kalau ikut kohort, keduanya tak
        // akan pernah cocok. CURDATE() dievaluasi MySQL, jadi tanggalnya
        // relatif (now()), bukan absolut seperti test kohort di atas.
        $this->anak(['tgl_lahir' => now()->subMonths(18)->toDateString()]);
        $this->anak(['tgl_lahir' => now()->subMonths(30)->toDateString()]);

        $acuan = $this->service->getButuhKejar();

        foreach ([2026, 2025, 2024] as $tahun) {
            $this->service->getIdlCoverage(KohortImunisasi::dari($tahun));
            $this->assertSame($acuan, $this->service->getButuhKejar(), "butuh_kejar bergeser saat kohort {$tahun} dihitung.");
        }
    }
```

Tambahkan helper di kelas tes (kalau `lengkapiIdl` belum ada — cek dulu, berkas ini sudah punya `beriVaksin()`):

```php
    private function lengkapiIdl(Anak $anak): void
    {
        foreach (['HB0', 'BCG', 'POLIO1', 'POLIO2', 'DPT-HB-HIB1', 'PCV1', 'POLIO3', 'DPT-HB-HIB2',
                  'PCV2', 'POLIO4', 'IPV1', 'DPT-HB-HIB3', 'IPV2', 'MR1', 'RV1', 'RV2'] as $kode) {
            $this->beriVaksin($anak, $kode);
        }
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Imunisasi/ImunisasiRutinDashboardServiceTest.php --filter="idl_penyebutnya|kohort_kosong|butuh_kejar"`
Expected: FAIL — `Call to undefined method getButuhKejar()`

- [ ] **Step 3: Write minimal implementation**

Pada `getIdlCoverage()`: ganti tanda tangan, buang seluruh jalur `$withKejar`, ganti query awal.

```php
    /**
     * Cakupan IDL atas kohort SI tahun terpilih. Penyebutnya SI karena itulah
     * denominator doktrin program untuk imunisasi dasar lengkap — bukan
     * "semua anak ≥ 12 bulan", yang ikut menyeret anak 4–5 tahun.
     *
     * @param  array{id_kecamatan?: int, id_kelurahan?: int, id_rt?: int, id_posyandu?: int, id_puskesmas?: int}  $filters
     * @return array{total: int, idl_lengkap: int, persen: float,
     *               per_kelurahan: array<string, array{nama: string, total: int, lengkap: int, persen: float}>}
     */
    public function getIdlCoverage(KohortImunisasi $kohort, array $filters = []): array
    {
        $query = $this->scopeKohort(
            $this->applyWilayahFilters(Anak::query(), $filters),
            $kohort,
            'SI'
        );

        $perKelurahan = [];
        $totalLengkap = 0;

        $total = $this->eachAnak($query, function (Anak $anak) use (&$perKelurahan, &$totalLengkap) {
            $namaKel = $anak->kel?->name ?? 'Tidak Diketahui';
            $kelId   = $anak->id_kel ?? 0;

            if (!isset($perKelurahan[$kelId])) {
                $perKelurahan[$kelId] = ['nama' => $namaKel, 'total' => 0, 'lengkap' => 0, 'persen' => 0.0];
            }

            $perKelurahan[$kelId]['total']++;

            if ($this->isIdlLengkap($anak)) {
                $perKelurahan[$kelId]['lengkap']++;
                $totalLengkap++;
            }
        }, ['imunisasi.jenisVaksin', 'kel']);

        foreach ($perKelurahan as &$row) {
            $row['persen'] = $row['total'] > 0 ? round(($row['lengkap'] / $row['total']) * 100, 1) : 0.0;
        }

        return [
            'total'         => $total,
            'idl_lengkap'   => $totalLengkap,
            'persen'        => $total > 0 ? round(($totalLengkap / $total) * 100, 1) : 0.0,
            'per_kelurahan' => $perKelurahan,
        ];
    }

    /**
     * Jumlah anak yang perlu dikejar IDL/IBL menurut TANGGAL BERJALAN.
     *
     * Angka operasional — sengaja TIDAK menerima KohortImunisasi. Kartunya di
     * dasbor menaut ke halaman Proyeksi (admin.earlyWarning) yang menghitung
     * dengan tanggal berjalan; kalau angka ini dikohortkan, kartu dan daftar
     * yang ditautnya tidak akan pernah cocok, tanpa error apa pun.
     *
     * Populasinya sengaja sama dengan sebelum pemisahan (anak ≥ 12 bulan) agar
     * nilainya tidak bergeser. Memperluasnya ke bayi < 12 bulan adalah
     * keputusan terpisah.
     *
     * @param  array{id_kecamatan?: int, id_kelurahan?: int, id_rt?: int, id_posyandu?: int, id_puskesmas?: int}  $filters
     */
    public function getButuhKejar(array $filters = []): int
    {
        $query = $this->applyWilayahFilters(Anak::query(), $filters)
            ->whereRaw('TIMESTAMPDIFF(MONTH, tgl_lahir, CURDATE()) >= 12');

        $butuh = 0;
        $this->eachAnak($query, function (Anak $anak) use (&$butuh) {
            $kejar = $this->kejarFlags($anak);
            if ($kejar['kejar_idl'] || $kejar['kejar_ibl']) {
                $butuh++;
            }
        });

        return $butuh;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Imunisasi/ImunisasiRutinDashboardServiceTest.php --filter="idl_penyebutnya|kohort_kosong|butuh_kejar"`
Expected: PASS — 3 tests

- [ ] **Step 5: Commit**

```bash
git add app/Services/ImunisasiStatusService.php tests/Feature/Imunisasi/ImunisasiRutinDashboardServiceTest.php
git commit -m "feat(imunisasi): cakupan IDL atas kohort SI; butuh_kejar dipisah jadi angka operasional"
```

---

### Task 4: Cakupan IBL ke kohort Baduta

**Files:**
- Modify: `app/Services/ImunisasiStatusService.php` (`getIblCoverage()` baris ~402)
- Test: `tests/Feature/Imunisasi/ImunisasiRutinDashboardServiceTest.php`

**Interfaces:**
- Consumes: `scopeKohort()` (Task 2)
- Produces: `getIblCoverage(KohortImunisasi $kohort, array $filters = []): array{total: int, ibl_lengkap: int, persen: float}`

- [ ] **Step 1: Write the failing test**

```php
    public function test_cakupan_ibl_penyebutnya_kohort_baduta(): void
    {
        $lengkap = $this->anak(['tgl_lahir' => '2024-06-10']); // Baduta 2026
        foreach (['PCV3', 'MR2', 'DPT-HB-HIB4'] as $kode) {
            $this->beriVaksin($lengkap, $kode);
        }
        $this->anak(['tgl_lahir' => '2025-03-31']); // Baduta 2026, belum lengkap
        $this->anak(['tgl_lahir' => '2025-09-15']); // SI 2026 — bukan penyebut IBL
        $this->anak(['tgl_lahir' => '2023-01-01']); // di luar Baduta 2026

        $coverage = $this->service->getIblCoverage(KohortImunisasi::dari(2026));

        $this->assertSame(2, $coverage['total'], 'Penyebut IBL = Baduta tahun terpilih, bukan "anak >= 24 bulan".');
        $this->assertSame(1, $coverage['ibl_lengkap']);
        $this->assertSame(50.0, $coverage['persen']);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Imunisasi/ImunisasiRutinDashboardServiceTest.php --filter=test_cakupan_ibl_penyebutnya_kohort_baduta`
Expected: FAIL — jumlah `total` masih memakai `TIMESTAMPDIFF >= 24`

- [ ] **Step 3: Write minimal implementation**

```php
    /**
     * Cakupan IBL (booster baduta) atas kohort Baduta tahun terpilih.
     * Menggantikan penyebut lama "anak ≥ 24 bulan", yang populasinya
     * mengambang ikut hari ini sehingga angkanya tak bisa dikunci sebagai
     * capaian tahun tertentu.
     *
     * @param  array{id_kecamatan?: int, id_kelurahan?: int, id_rt?: int, id_posyandu?: int, id_puskesmas?: int}  $filters
     * @return array{total: int, ibl_lengkap: int, persen: float}
     */
    public function getIblCoverage(KohortImunisasi $kohort, array $filters = []): array
    {
        $query = $this->scopeKohort(
            $this->applyWilayahFilters(Anak::query(), $filters),
            $kohort,
            'BADUTA'
        );

        $lengkap = 0;
        $total = $this->eachAnak($query, function (Anak $anak) use (&$lengkap) {
            if ($this->isIblLengkap($anak)) {
                $lengkap++;
            }
        });

        return [
            'total'       => $total,
            'ibl_lengkap' => $lengkap,
            'persen'      => $total > 0 ? round($lengkap / $total * 100, 1) : 0.0,
        ];
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Imunisasi/ImunisasiRutinDashboardServiceTest.php --filter=test_cakupan_ibl_penyebutnya_kohort_baduta`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/ImunisasiStatusService.php tests/Feature/Imunisasi/ImunisasiRutinDashboardServiceTest.php
git commit -m "feat(imunisasi): cakupan IBL atas kohort Baduta"
```

---

### Task 5: Funnel dosis ke kohort SI

**Files:**
- Modify: `app/Services/ImunisasiStatusService.php` (`getFunnelDosis()` baris ~466)
- Test: `tests/Feature/Imunisasi/ImunisasiRutinDashboardServiceTest.php`

**Interfaces:**
- Consumes: `scopeKohort()` (Task 2)
- Produces: `getFunnelDosis(KohortImunisasi $kohort, array $filters = []): list<array{kode: string, label: string, jumlah: int}>`

- [ ] **Step 1: Write the failing test**

Ganti method tes funnel yang lama (cari berdasarkan NAMA METHOD, bukan nomor baris — berkas ini sudah disunting Task 2-4) dengan:

```php
    public function test_funnel_dosis_dihitung_atas_kohort_si(): void
    {
        $si = $this->anak(['tgl_lahir' => '2025-05-10']);
        $this->beriVaksin($si, 'HB0');
        $this->beriVaksin($si, 'DPT-HB-HIB1');

        $badutaDiabaikan = $this->anak(['tgl_lahir' => '2024-06-10']);
        $this->beriVaksin($badutaDiabaikan, 'HB0');

        $bblDiabaikan = $this->anak(['tgl_lahir' => '2026-03-01']);
        $this->beriVaksin($bblDiabaikan, 'HB0');

        $funnel = collect($this->service->getFunnelDosis(KohortImunisasi::dari(2026)))->keyBy('kode');

        $this->assertSame(1, $funnel['HB0']['jumlah'], 'Hanya anak SI yang masuk funnel.');
        $this->assertSame(1, $funnel['DPT-HB-HIB1']['jumlah']);
        $this->assertSame(0, $funnel['DPT-HB-HIB3']['jumlah']);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Imunisasi/ImunisasiRutinDashboardServiceTest.php --filter=test_funnel_dosis_dihitung_atas_kohort_si`
Expected: FAIL — `HB0` terhitung 3 (populasi masih `>= 12 bulan`)

- [ ] **Step 3: Write minimal implementation**

Ganti tanda tangan dan query kohort di `getFunnelDosis()`:

```php
    public function getFunnelDosis(KohortImunisasi $kohort, array $filters = []): array
    {
        $tahapan = [
            'HB0'          => 'HB0',
            'DPT-HB-HIB1'  => 'DPT-HB-Hib 1',
            'DPT-HB-HIB2'  => 'DPT-HB-Hib 2',
            'DPT-HB-HIB3'  => 'DPT-HB-Hib 3',
            'MR1'          => 'Campak-Rubela',
        ];

        $cohort = $this->scopeKohort(
            $this->applyWilayahFilters(Anak::query(), $filters),
            $kohort,
            'SI'
        );
```

Sisa badan method tidak berubah.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Imunisasi/ImunisasiRutinDashboardServiceTest.php --filter=test_funnel_dosis_dihitung_atas_kohort_si`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/ImunisasiStatusService.php tests/Feature/Imunisasi/ImunisasiRutinDashboardServiceTest.php
git commit -m "feat(imunisasi): funnel dosis dihitung atas kohort SI"
```

---

### Task 6: Cakupan antigen — penyebut per jendela usia

**Files:**
- Modify: `app/Services/ImunisasiStatusService.php` (`getCakupanAntigen()` baris ~511; tambah `kelompokPenyebutAntigen()`)
- Test: `tests/Feature/Imunisasi/ImunisasiRutinDashboardServiceTest.php`

**Interfaces:**
- Consumes: `KohortImunisasi::rentang()`, `->kelompokDari()`
- Produces:
  - `private kelompokPenyebutAntigen(int $usiaPemberianMax): string` — mengembalikan `SELURUH|SI|BADUTA`
  - `public kelompokPenyebutAntigenUntukUji(int $usiaPemberianMax): string` — pembungkus tipis untuk pengujian batas
  - `getCakupanAntigen(KohortImunisasi $kohort, array $filters = []): list<array{kode: string, nama: string, kelompok: string, jumlah_sudah: int, jumlah_penyebut: int, persen: float}>` — kunci `jumlah_eligible` **diganti** `jumlah_penyebut`, ditambah `kelompok`

- [ ] **Step 1: Write the failing test**

```php
    public function test_penyebut_antigen_mengikuti_jendela_usia_pemberian(): void
    {
        $si     = $this->anak(['tgl_lahir' => '2025-05-10']); // SI + SELURUH
        $bbl    = $this->anak(['tgl_lahir' => '2026-03-01']); // BBL + SELURUH
        $baduta = $this->anak(['tgl_lahir' => '2024-06-10']); // BADUTA

        $this->beriVaksin($si, 'HB0');
        $this->beriVaksin($bbl, 'HB0');
        $this->beriVaksin($si, 'DPT-HB-HIB1');
        $this->beriVaksin($baduta, 'PCV3');

        $cakupan = collect($this->service->getCakupanAntigen(KohortImunisasi::dari(2026)))->keyBy('kode');

        // HB0 (max 7 hr) -> seluruh kelahiran periode = BBL + SI = 2 anak.
        $this->assertSame('SELURUH', $cakupan['HB0']['kelompok']);
        $this->assertSame(2, $cakupan['HB0']['jumlah_penyebut']);
        $this->assertSame(2, $cakupan['HB0']['jumlah_sudah']);

        // DPT1 (max 90 hr) -> SI saja = 1 anak.
        $this->assertSame('SI', $cakupan['DPT-HB-HIB1']['kelompok']);
        $this->assertSame(1, $cakupan['DPT-HB-HIB1']['jumlah_penyebut']);

        // PCV3 (max 395 hr) -> Baduta = 1 anak.
        $this->assertSame('BADUTA', $cakupan['PCV3']['kelompok']);
        $this->assertSame(1, $cakupan['PCV3']['jumlah_penyebut']);
        $this->assertSame(100.0, $cakupan['PCV3']['persen']);
    }

    public function test_rv1_yang_jendelanya_melintasi_batas_masuk_si(): void
    {
        // RV1 jendelanya 42-70 hari, melintasi batas 59. Dengan aturan batas
        // ATAS ia masuk SI — benar, karena baru bisa dinilai setelah 70 hari.
        $cakupan = collect($this->service->getCakupanAntigen(KohortImunisasi::dari(2026)))->keyBy('kode');

        $this->assertSame('SI', $cakupan['RV1']['kelompok']);
    }

    public function test_pemetaan_kelompok_tepat_di_batas_59_60_364_365(): void
    {
        $peta = fn (int $maxHari) => $this->service->kelompokPenyebutAntigenUntukUji($maxHari);

        $this->assertSame('SELURUH', $peta(59));
        $this->assertSame('SI',      $peta(60));
        $this->assertSame('SI',      $peta(364));
        $this->assertSame('BADUTA',  $peta(365));
    }

    public function test_antigen_kategori_tambahan_tetap_dikecualikan(): void
    {
        $kode = collect($this->service->getCakupanAntigen(KohortImunisasi::dari(2026)))->pluck('kode');

        $this->assertNotContains('HPV1', $kode);
        $this->assertNotContains('DT', $kode);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Imunisasi/ImunisasiRutinDashboardServiceTest.php --filter="penyebut_antigen|rv1_yang|tepat_di_batas|tambahan_tetap"`
Expected: FAIL — kunci `kelompok` belum ada; `kelompokPenyebutAntigenUntukUji()` undefined

- [ ] **Step 3: Write minimal implementation**

```php
    /**
     * Kelompok kohort yang jadi penyebut satu antigen, ditentukan dari
     * `usia_pemberian_max` (satuan HARI) — bukan daftar kode yang ditulis
     * tangan, supaya antigen baru otomatis kebagian.
     *
     *   ≤  59 hari  → SELURUH kohort  (HB0, BCG, Polio 1)
     *   ≤ 364 hari  → SI              (RV1 … MR1)
     *     lainnya   → BADUTA          (PCV3, MR2, DPT-HB-Hib 4)
     *
     * Penyebut antigen bayi baru lahir sengaja SELURUH kohort, bukan kelompok
     * BBL: HB0 diberikan 0–7 hari setelah lahir, jadi setiap anak dalam
     * periode menerimanya. Kalau penyebutnya kelompok BBL (yang hanya berisi
     * kelahiran Februari–Maret), HB0 milik ±10 bulan kelahiran lain tidak
     * masuk pembilang maupun penyebut mana pun.
     */
    private function kelompokPenyebutAntigen(int $usiaPemberianMax): string
    {
        return match (true) {
            $usiaPemberianMax <= 59  => 'SELURUH',
            $usiaPemberianMax <= 364 => 'SI',
            default                  => 'BADUTA',
        };
    }

    /** Titik masuk pengujian untuk kelompokPenyebutAntigen(). */
    public function kelompokPenyebutAntigenUntukUji(int $usiaPemberianMax): string
    {
        return $this->kelompokPenyebutAntigen($usiaPemberianMax);
    }

    /**
     * Cakupan tiap antigen rutin atas kohort tahun terpilih. Penyebutnya
     * kelompok kohort yang sesuai jendela antigen (lihat
     * kelompokPenyebutAntigen), MENGGANTIKAN metodologi lama "anak yang
     * jendela usianya sudah lewat". Konsekuensinya semua persen turun untuk
     * kohort berjalan — itu memang perilaku cakupan tahunan.
     *
     * Satu pass populasi atas gabungan BADUTA ∪ SELURUH, yang kebetulan
     * bersambung: [1 Apr X-2 .. 31 Mar X].
     *
     * @param  array{id_kecamatan?: int, id_kelurahan?: int, id_rt?: int, id_posyandu?: int, id_puskesmas?: int}  $filters
     * @return list<array{kode: string, nama: string, kelompok: string, jumlah_sudah: int, jumlah_penyebut: int, persen: float}>
     */
    public function getCakupanAntigen(KohortImunisasi $kohort, array $filters = []): array
    {
        $vaksinList = JenisVaksin::aktif()
            ->where('kategori', '!=', 'Tambahan')
            ->orderBy('usia_pemberian_min')
            ->get();

        $kelompokVaksin = [];
        foreach ($vaksinList as $vaksin) {
            $kelompokVaksin[$vaksin->id] = $this->kelompokPenyebutAntigen((int) $vaksin->usia_pemberian_max);
        }

        [$awal] = $kohort->rentang('BADUTA');
        [, $akhir] = $kohort->rentang('SELURUH');

        $sudah = $penyebut = array_fill_keys($vaksinList->pluck('id')->all(), 0);

        $this->eachAnak(
            $this->applyWilayahFilters(Anak::query(), $filters)
                ->whereBetween('tgl_lahir', [$awal, $akhir]),
            function (Anak $anak) use ($vaksinList, $kelompokVaksin, $kohort, &$sudah, &$penyebut) {
                $anggota = $kohort->kelompokDari((string) $anak->tgl_lahir);

                foreach ($vaksinList as $vaksin) {
                    if (!in_array($kelompokVaksin[$vaksin->id], $anggota, true)) {
                        continue;
                    }

                    $record = $anak->imunisasi->firstWhere('id_jenis_vaksin', $vaksin->id);

                    if ($this->getVaccineStatus($anak, $vaksin, $record) === 'tidak_relevan') {
                        continue;
                    }

                    $penyebut[$vaksin->id]++;

                    if ($record && $record->status === 'sudah') {
                        $sudah[$vaksin->id]++;
                    }
                }
            }
        );

        $result = [];
        foreach ($vaksinList as $vaksin) {
            $n = $penyebut[$vaksin->id];
            $result[] = [
                'kode'            => $vaksin->kode,
                'nama'            => $vaksin->nama,
                'kelompok'        => $kelompokVaksin[$vaksin->id],
                'jumlah_sudah'    => $sudah[$vaksin->id],
                'jumlah_penyebut' => $n,
                'persen'          => $n > 0 ? round($sudah[$vaksin->id] / $n * 100, 1) : 0.0,
            ];
        }

        return $result;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Imunisasi/ImunisasiRutinDashboardServiceTest.php --filter="penyebut_antigen|rv1_yang|tepat_di_batas|tambahan_tetap"`
Expected: PASS — 4 tests

- [ ] **Step 5: Commit**

```bash
git add app/Services/ImunisasiStatusService.php tests/Feature/Imunisasi/ImunisasiRutinDashboardServiceTest.php
git commit -m "feat(imunisasi): penyebut cakupan antigen mengikuti jendela usia & kelompok kohort"
```

---

### Task 7: Kohort per wilayah ke BBL/SI/Baduta

**Files:**
- Modify: `app/Services/ImunisasiStatusService.php` (`getKohortWilayah()` baris ~563)
- Test: `tests/Feature/Imunisasi/ImunisasiRutinDashboardServiceTest.php`

**Interfaces:**
- Consumes: `KohortImunisasi::rentang()`, `->kelompokDari()`
- Produces: `getKohortWilayah(KohortImunisasi $kohort, array $filters = []): list<array{nama: string, jumlah_rt: int, bbl: int, si: int, baduta: int, total: int, persen_kota: float, kelurahan: list<...>}>` — kunci `bayi`/`baduta` **diganti** `bbl`/`si`/`baduta`

- [ ] **Step 1: Write the failing test**

```php
    public function test_kohort_wilayah_memilah_bbl_si_baduta_per_kelurahan(): void
    {
        $this->anak(['tgl_lahir' => '2025-09-15', 'id_kec' => 1, 'id_kel' => 1]); // SI
        $this->anak(['tgl_lahir' => '2026-03-01', 'id_kec' => 1, 'id_kel' => 1]); // BBL
        $this->anak(['tgl_lahir' => '2024-06-10', 'id_kec' => 1, 'id_kel' => 1]); // Baduta
        $this->anak(['tgl_lahir' => '2024-03-31', 'id_kec' => 1, 'id_kel' => 1]); // di luar kohort

        $kohort = collect($this->service->getKohortWilayah(KohortImunisasi::dari(2026)));
        $baris  = $kohort->firstWhere('nama', \App\Models\Kecamatan::find(1)->name);

        $this->assertSame(1, $baris['bbl']);
        $this->assertSame(1, $baris['si']);
        $this->assertSame(1, $baris['baduta']);
        $this->assertSame(3, $baris['total'], 'Anak di luar kohort tidak ikut terhitung.');
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Imunisasi/ImunisasiRutinDashboardServiceTest.php --filter=test_kohort_wilayah_memilah_bbl_si_baduta_per_kelurahan`
Expected: FAIL — `Undefined array key "bbl"`

- [ ] **Step 3: Write minimal implementation**

Ganti kepala method dan akumulatornya; bagian penyusunan `$result` di bawahnya mengikuti nama kunci baru.

```php
    public function getKohortWilayah(KohortImunisasi $kohort, array $filters = []): array
    {
        $rtCountByKel = \App\Models\Rt::query()
            ->selectRaw('id_kelurahan, COUNT(*) as jumlah')
            ->groupBy('id_kelurahan')
            ->pluck('jumlah', 'id_kelurahan');

        [$awal] = $kohort->rentang('BADUTA');
        [, $akhir] = $kohort->rentang('SELURUH');

        $perKel = [];
        $grandTotal = $this->eachAnak(
            $this->applyWilayahFilters(Anak::query(), $filters)
                ->whereBetween('tgl_lahir', [$awal, $akhir]),
            function (Anak $anak) use (&$perKel, $kohort) {
                $kelId = $anak->id_kel ?? 0;
                $anggota = $kohort->kelompokDari((string) $anak->tgl_lahir);

                if (!isset($perKel[$kelId])) {
                    $perKel[$kelId] = ['id_kec' => $anak->id_kec, 'bbl' => 0, 'si' => 0, 'baduta' => 0, 'total' => 0];
                }

                $perKel[$kelId]['total']++;

                if (in_array('BBL', $anggota, true)) {
                    $perKel[$kelId]['bbl']++;
                } elseif (in_array('SI', $anggota, true)) {
                    $perKel[$kelId]['si']++;
                } elseif (in_array('BADUTA', $anggota, true)) {
                    $perKel[$kelId]['baduta']++;
                }
            },
            with: [] // distribusi populasi murni, tak perlu relasi imunisasi
        );
```

Lalu di bagian penyusunan hasil, ganti tiap `'bayi' => ...` / `$kecBayi` menjadi tiga akumulator `bbl`/`si`/`baduta`:

```php
        $kelurahanNames = \App\Models\Kelurahan::whereIn('id', array_keys($perKel))->pluck('name', 'id');

        $result = [];
        foreach (\App\Models\Kecamatan::orderBy('name')->get() as $kec) {
            $kelurahanRows = [];
            $kecTotal = $kecBbl = $kecSi = $kecBaduta = $kecRt = 0;

            foreach ($perKel as $kelId => $row) {
                if ((int) $row['id_kec'] !== $kec->id) {
                    continue;
                }
                $jumlahRt = (int) ($rtCountByKel[$kelId] ?? 0);
                $kelurahanRows[] = [
                    'nama'        => $kelurahanNames[$kelId] ?? 'Tidak diketahui',
                    'jumlah_rt'   => $jumlahRt,
                    'bbl'         => $row['bbl'],
                    'si'          => $row['si'],
                    'baduta'      => $row['baduta'],
                    'total'       => $row['total'],
                    'persen_kota' => $grandTotal > 0 ? round($row['total'] / $grandTotal * 100, 1) : 0.0,
                ];
                $kecTotal  += $row['total'];
                $kecBbl    += $row['bbl'];
                $kecSi     += $row['si'];
                $kecBaduta += $row['baduta'];
                $kecRt     += $jumlahRt;
            }

            if (empty($kelurahanRows)) {
                continue;
            }

            $result[] = [
                'nama'        => $kec->name,
                'jumlah_rt'   => $kecRt,
                'bbl'         => $kecBbl,
                'si'          => $kecSi,
                'baduta'      => $kecBaduta,
                'total'       => $kecTotal,
                'persen_kota' => $grandTotal > 0 ? round($kecTotal / $grandTotal * 100, 1) : 0.0,
                'kelurahan'   => $kelurahanRows,
            ];
        }

        return $result;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Imunisasi/ImunisasiRutinDashboardServiceTest.php --filter=test_kohort_wilayah_memilah_bbl_si_baduta_per_kelurahan`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/ImunisasiStatusService.php tests/Feature/Imunisasi/ImunisasiRutinDashboardServiceTest.php
git commit -m "feat(imunisasi): kohort per wilayah dipilah BBL/SI/Baduta"
```

---

### Task 8: Rincian puskesmas ke kohort SI

**Files:**
- Modify: `app/Services/ImunisasiStatusService.php` (`getRincianPuskesmas()` baris ~660)
- Test: `tests/Feature/Imunisasi/ImunisasiRutinDashboardServiceTest.php`

**Interfaces:**
- Consumes: `scopeKohort()` (Task 2); helper tes `lengkapiIdl(Anak $anak): void` yang dibuat di Task 3
- Produces: `getRincianPuskesmas(KohortImunisasi $kohort, array $filters = []): list<array{nama: string, sasaran: int, capaian_idl: int, persen: float, do_rate: float, status: string}>` (bentuk keluaran tidak berubah)

- [ ] **Step 1: Write the failing test**

Sesuaikan method tes rincian puskesmas yang lama (cari berdasarkan NAMA METHOD, bukan nomor baris — berkas ini sudah disunting Task 2-6) menjadi:

```php
    public function test_rincian_puskesmas_sasarannya_kohort_si(): void
    {
        $kelIds = \App\Support\WilkerPuskesmas::catchmentKelurahanIds(\App\Models\Puskesmas::orderBy('name')->first()->name);
        $idKel  = $kelIds[0];

        $lengkap = $this->anak(['tgl_lahir' => '2025-05-10', 'id_kel' => $idKel]);
        $this->lengkapiIdl($lengkap);
        $this->anak(['tgl_lahir' => '2025-06-10', 'id_kel' => $idKel]); // SI, belum lengkap
        $this->anak(['tgl_lahir' => '2026-03-01', 'id_kel' => $idKel]); // BBL — bukan sasaran
        $this->anak(['tgl_lahir' => '2024-06-10', 'id_kel' => $idKel]); // Baduta — bukan sasaran

        $rincian = collect($this->service->getRincianPuskesmas(KohortImunisasi::dari(2026)))
            ->firstWhere('nama', \App\Models\Puskesmas::orderBy('name')->first()->name);

        $this->assertSame(2, $rincian['sasaran'], 'Sasaran puskesmas = kohort SI, bukan "anak >= 12 bulan".');
        $this->assertSame(1, $rincian['capaian_idl']);
        $this->assertSame(50.0, $rincian['persen']);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Imunisasi/ImunisasiRutinDashboardServiceTest.php --filter=test_rincian_puskesmas_sasarannya_kohort_si`
Expected: FAIL — `ArgumentCountError` / sasaran masih menghitung populasi `>= 12 bulan`

- [ ] **Step 3: Write minimal implementation**

```php
    public function getRincianPuskesmas(KohortImunisasi $kohort, array $filters = []): array
    {
        $result = [];
        foreach (\App\Models\Puskesmas::orderBy('name')->get() as $pkm) {
            $kelIds = \App\Support\WilkerPuskesmas::catchmentKelurahanIds($pkm->name);

            $query = $this->scopeKohort(
                $this->applyWilayahFilters(Anak::query(), $filters)->whereIn('id_kel', $kelIds ?: [0]),
                $kohort,
                'SI'
            );
```

Sisa badan method tidak berubah.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Imunisasi/ImunisasiRutinDashboardServiceTest.php --filter=test_rincian_puskesmas_sasarannya_kohort_si`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/ImunisasiStatusService.php tests/Feature/Imunisasi/ImunisasiRutinDashboardServiceTest.php
git commit -m "feat(imunisasi): sasaran rincian puskesmas pakai kohort SI"
```

---

### Task 9: Controller — dropdown tahun & validasi

**Files:**
- Modify: `app/Http/Controllers/AdminController.php:606-645` (`imunisasiDashboard()`)
- Test: `tests/Feature/Imunisasi/ImunisasiDashboardTahunTest.php` (create)

**Interfaces:**
- Consumes: seluruh method dari Task 2–8, `KohortImunisasi::tahunTervalidasi()`, `::pilihanTahun()`, `::dari()`
- Produces: view `admin.imunisasi.dashboard` menerima variabel tambahan `$kohort`, `$tahun`, `$pilihanTahun`; `$butuhKejar` kini dari `getButuhKejar()`

**Catatan:** `korelasiStuntingVaksin($filters, $coverage)` **tidak** diubah tanda tangannya. Ia memakai `$coverage['per_kelurahan']` yang kini berbasis SI — itu memang yang dikehendaki spec (korelasi termasuk statistik).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Imunisasi;

use App\Models\User;
use App\Services\ImunisasiStatusService;
use Database\Seeders\JenisVaksinSeeder;
use Database\Seeders\KelompokVaksinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImunisasiDashboardTahunTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ImunisasiStatusService::flushCache();
        $this->seed(JenisVaksinSeeder::class);
        $this->seed(KelompokVaksinSeeder::class);
    }

    private function admin(): User
    {
        // `type` (0=super-admin, 1=admin) adalah yang dibaca middleware IsAdmin.
        // Kolom `role` dipakai untuk sub-peran faskes, bukan untuk ini.
        return User::factory()->create(['type' => 1]);
    }

    public function test_tahun_default_adalah_tahun_berjalan(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.imunisasiDashboard'))
            ->assertOk()
            ->assertViewHas('tahun', (int) date('Y'));
    }

    public function test_tahun_valid_dari_query_string_dipakai(): void
    {
        $tahun = (int) date('Y') - 2;

        $this->actingAs($this->admin())
            ->get(route('admin.imunisasiDashboard', ['tahun' => $tahun]))
            ->assertOk()
            ->assertViewHas('tahun', $tahun);
    }

    /**
     * @dataProvider tahunNgawur
     */
    public function test_tahun_ngawur_kembali_ke_default_bukan_500(string $input): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.imunisasiDashboard', ['tahun' => $input]))
            ->assertOk()
            ->assertViewHas('tahun', (int) date('Y'));
    }

    public static function tahunNgawur(): array
    {
        return [
            'huruf'        => ['abc'],
            'nol'          => ['0'],
            'negatif'      => ['-5'],
            'terlalu jauh' => ['2099'],
            'pecahan'      => ['2026.5'],
            'kosong'       => [''],
            'sebelum daftar' => ['1999'],
        ];
    }

    public function test_pilihan_tahun_berisi_lima_tahun(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.imunisasiDashboard'))
            ->assertOk()
            ->assertViewHas('pilihanTahun', fn ($p) => count($p) === 5 && $p[0] === (int) date('Y'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Imunisasi/ImunisasiDashboardTahunTest.php`
Expected: FAIL — `Failed asserting that the view has key 'tahun'`

- [ ] **Step 3: Write minimal implementation**

Ganti badan `imunisasiDashboard()`:

```php
    public function imunisasiDashboard(Request $request)
    {
        $filters = array_filter([
            'id_kecamatan' => $request->integer('id_kecamatan') ?: null,
            'id_kelurahan' => $request->integer('id_kelurahan') ?: null,
            'id_rt'        => $request->integer('id_rt') ?: null,
            'id_puskesmas' => $request->integer('id_puskesmas') ?: null,
            'id_posyandu'  => $request->integer('id_posyandu') ?: null,
        ]);

        // Tahun sasaran: kohort 1 Apr X-1 s.d. 31 Mar X. Input ngawur jatuh ke
        // default, bukan 500 — lihat KohortImunisasi::tahunTervalidasi().
        $tahun        = \App\Support\KohortImunisasi::tahunTervalidasi($request->query('tahun'));
        $pilihanTahun = \App\Support\KohortImunisasi::pilihanTahun();
        $kohort       = \App\Support\KohortImunisasi::dari($tahun);

        $service = app(\App\Services\ImunisasiStatusService::class);

        // Statistik & SPM — mengikuti kohort tahun terpilih.
        $coverage         = $service->getIdlCoverage($kohort, $filters);
        $sasaran          = $service->getRingkasanSasaran($kohort, $filters);
        $iblCoverage      = $service->getIblCoverage($kohort, $filters);
        $funnel           = $service->getFunnelDosis($kohort, $filters);
        $cakupanAntigen   = $service->getCakupanAntigen($kohort, $filters);
        $kohortWilayah    = $service->getKohortWilayah($kohort, $filters);
        $rincianPuskesmas = $service->getRincianPuskesmas($kohort, $filters);

        // Operasional — tanggal berjalan, sengaja TIDAK mengikuti dropdown tahun.
        $butuhKejar    = $service->getButuhKejar($filters);
        $sasaranHarian = $service->getSasaranHarianBesok($filters);

        $kecamatanList = \App\Models\Kecamatan::orderBy('name')->get();
        $kelurahanList = \App\Models\Kelurahan::orderBy('name')->get();
        $posyanduList  = \App\Models\Posyandu::orderBy('name')->get();
        $puskesmasList = \App\Models\Puskesmas::orderBy('name')->get();

        // Korelasi memakai per_kelurahan dari cakupan IDL, jadi sisi imunisasinya
        // ikut kohort SI. Itu dikehendaki: korelasi termasuk statistik.
        $korelasiData = $this->korelasiStuntingVaksin($filters, $coverage);
        $alasanTidakImunisasi = $service->getAlasanTidakImunisasi($filters);

        return view('admin.imunisasi.dashboard', compact(
            'coverage', 'butuhKejar', 'filters', 'kohort', 'tahun', 'pilihanTahun',
            'kecamatanList', 'kelurahanList', 'posyanduList', 'puskesmasList', 'korelasiData',
            'alasanTidakImunisasi',
            'sasaran', 'iblCoverage', 'funnel', 'cakupanAntigen', 'kohortWilayah', 'rincianPuskesmas', 'sasaranHarian'
        ));
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Imunisasi/ImunisasiDashboardTahunTest.php`
Expected: PASS — 11 tests (7 dari data provider)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/AdminController.php tests/Feature/Imunisasi/ImunisasiDashboardTahunTest.php
git commit -m "feat(imunisasi): dropdown tahun sasaran di dasbor, input ngawur jatuh ke default"
```

---

### Task 10: Blade — kartu BBL/SI/Baduta, label periode, penanda operasional

**Files:**
- Modify: `resources/views/admin/imunisasi/dashboard.blade.php` (filter ~163; Data sasaran 212–240; Data capaian 242–277; Kohort wilayah 278–335; Rincian puskesmas 336–371; Cakupan antigen 372–398; Funnel 399–417)
- Test: `tests/Feature/Imunisasi/ImunisasiDashboardTahunTest.php`

**Interfaces:**
- Consumes: `$kohort`, `$tahun`, `$pilihanTahun`, `$sasaran['bbl'|'si'|'baduta']`, `$cakupanAntigen[*]['jumlah_penyebut'|'kelompok']`, `$kohortWilayah[*]['bbl'|'si'|'baduta']`
- Produces: tidak ada API baru

- [ ] **Step 1: Write the failing test**

Tambahkan ke `ImunisasiDashboardTahunTest`:

```php
    public function test_halaman_menampilkan_dropdown_tahun_dan_label_periode(): void
    {
        $tahun = (int) date('Y');

        $this->actingAs($this->admin())
            ->get(route('admin.imunisasiDashboard'))
            ->assertOk()
            ->assertSee('Tahun sasaran')
            ->assertSee('name="tahun"', false)
            ->assertSee("Kohort {$tahun}")
            ->assertSee('potret umur');
    }

    public function test_kartu_sasaran_memakai_istilah_bbl_si_baduta_dan_balita_dihapus(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('admin.imunisasiDashboard'))
            ->assertOk();

        $response->assertSee('BBL');
        $response->assertSee('Surviving Infant');
        $response->assertSee('Baduta');
        $response->assertDontSee('Balita 0&ndash;59 bulan', false);
    }

    public function test_blok_operasional_diberi_penanda_tidak_mengikuti_tahun(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.imunisasiDashboard'))
            ->assertOk()
            ->assertSee('tidak mengikuti tahun sasaran');
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan view:clear` lalu `php artisan test tests/Feature/Imunisasi/ImunisasiDashboardTahunTest.php --filter="dropdown_tahun|kartu_sasaran|penanda"`
Expected: FAIL — teks belum ada

- [ ] **Step 3: Write minimal implementation**

**3a. Dropdown tahun** — sisipkan sebagai `<div>` PERTAMA di dalam `<form class="im-filter">` (sebelum blok Kecamatan, baris ~165):

```blade
    <div>
        <label for="filterTahun">Tahun sasaran</label>
        <select name="tahun" id="filterTahun">
            @foreach($pilihanTahun as $t)
                <option value="{{ $t }}" {{ $tahun == $t ? 'selected' : '' }}>{{ $t }}</option>
            @endforeach
        </select>
    </div>
```

**3b. Judul & kartu Data sasaran** — ganti blok baris 212–240:

```blade
{{-- Data sasaran: populasi kohort tahun terpilih, bukan capaian --}}
<div class="im-h">
    <h2>Data sasaran</h2>
    <small>{{ $sasaran['label'] }} &middot; bukan ukuran capaian</small>
</div>
<div class="im-cards">
    <div class="im-card">
        <div class="im-card__lbl">BBL &middot; bayi baru lahir</div>
        <div class="im-card__val im-num">{{ number_format($sasaran['bbl']['jumlah']) }}</div>
        <div class="im-card__sub">umur 0 &ndash; 1 bln 29 hr &middot; lahir {{ $sasaran['bbl']['rentang'][0] }} s.d. {{ $sasaran['bbl']['rentang'][1] }}</div>
    </div>
    <div class="im-card">
        <div class="im-card__lbl">SI &middot; Surviving Infant</div>
        <div class="im-card__val im-num">{{ number_format($sasaran['si']['jumlah']) }}</div>
        <div class="im-card__sub">umur 2 bln &ndash; blm genap 12 bln &middot; lahir {{ $sasaran['si']['rentang'][0] }} s.d. {{ $sasaran['si']['rentang'][1] }}</div>
    </div>
    <div class="im-card">
        <div class="im-card__lbl">Baduta &middot; bawah dua tahun</div>
        <div class="im-card__val im-num">{{ number_format($sasaran['baduta']['jumlah']) }}</div>
        <div class="im-card__sub">kohort {{ $sasaran['tahun'] - 1 }} &middot; lahir {{ $sasaran['baduta']['rentang'][0] }} s.d. {{ $sasaran['baduta']['rentang'][1] }}</div>
    </div>
</div>
<p class="im-note">
    BBL dan SI tidak tumpang tindih &mdash; tiap anak masuk tepat satu kelompok, jadi BBL + SI =
    seluruh kelahiran periode. Anak di luar ketiga kohort ini tidak muncul di bagian statistik mana
    pun, sehingga jumlah di halaman ini tidak sama dengan jumlah anak terdaftar.
</p>
```

**3c. Penanda operasional** — pada kartu "Butuh kejar" (baris ~266-274) tambahkan di bawah nilainya:

```blade
        <div class="im-card__sub">menurut tanggal hari ini &middot; <strong>tidak mengikuti tahun sasaran</strong></div>
```

dan pada judul bagian "Sasaran hari ini &amp; besok" (baris ~421) tambahkan `<small>`:

```blade
        <small>jadwal menurut tanggal hari ini &middot; <strong>tidak mengikuti tahun sasaran</strong></small>
```

**3d. Teks metodologi yang sudah tidak berlaku** — ganti dua `<small>`:

Baris 243:
```blade
<div class="im-h"><h2>Data capaian</h2><small>% kelengkapan terhadap target 95% &middot; penyebut = kohort {{ $tahun }} (IDL atas SI, IBL atas Baduta)</small></div>
```

Baris 373:
```blade
<div class="im-h"><h2>Cakupan per antigen</h2><small>% terhadap kelompok kohort sesuai jendela antigen &middot; diurutkan dari yang paling tertinggal &middot; garis putus-putus = target 95%</small></div>
```

Baris 337 dan 400 (keduanya masih menyebut "Kohort &ge;12 bulan"):
```blade
<div class="im-h"><h2>Rincian per puskesmas</h2><small>Sasaran = kohort SI {{ $tahun }} &middot; wilayah kerja via catchment kelurahan</small></div>
```
```blade
<div class="im-h"><h2>Funnel dosis</h2><small>Kohort SI {{ $tahun }} &middot; jumlah anak yang sudah menerima tiap dosis, berurutan sesuai jadwal</small></div>
```

**3e. Kunci array lama yang tersisa di luar blok yang diganti utuh.** Ada lima baris. Semuanya wajib diganti — kunci lamanya sudah tidak dikembalikan service, dan Blade akan melempar `Undefined array key`.

Baris 257 (label kartu IBL di Data capaian) — `baduta_min`/`baduta_max` sudah tidak ada:

```blade
            <span>IBL &middot; Baduta kohort {{ $tahun - 1 }}</span>
```

Baris 292 (ringkasan kecamatan):

```blade
                <span>{{ number_format($kec['bbl']) }} BBL</span>
                <span>{{ number_format($kec['si']) }} SI</span>
                <span>{{ number_format($kec['baduta']) }} baduta</span>
```

Baris 303 (baris kelurahan) — ganti satu `<td>` jadi dua:

```blade
                <td class="r" style="width:80px;">{{ number_format($kel['bbl']) }} BBL</td>
                <td class="r" style="width:80px;">{{ number_format($kel['si']) }} SI</td>
```

Tambahkan satu `<th>` yang bersesuaian di kepala tabel kelurahan (blok `<thead>` tepat di atasnya) supaya jumlah kolomnya tetap cocok.

Baris 379 (atribut `title` baris antigen) — teksnya juga menyebut metodologi lama:

```blade
        <div class="im-antigen__row" title="{{ $ag['jumlah_sudah'] }} dari {{ $ag['jumlah_penyebut'] }} anak kelompok {{ $ag['kelompok'] }} kohort {{ $tahun }}">
```

Baris 385 (angka di ujung kanan):

```blade
            <span class="im-antigen__val im-num">{{ $ag['persen'] }}% <small>&middot; {{ number_format($ag['jumlah_sudah']) }}/{{ number_format($ag['jumlah_penyebut']) }}</small></span>
```

Setelah kelimanya diganti, pastikan tidak ada sisa dengan:

```bash
grep -n "jumlah_eligible\|\['bayi'\]\|baduta_min\|baduta_max\|sasaran\['balita'\]" resources/views/admin/imunisasi/dashboard.blade.php
```

Keluaran harus kosong.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan view:clear` lalu `php artisan test tests/Feature/Imunisasi/ImunisasiDashboardTahunTest.php`
Expected: PASS — 14 tests

- [ ] **Step 5: Commit**

```bash
git add resources/views/admin/imunisasi/dashboard.blade.php tests/Feature/Imunisasi/ImunisasiDashboardTahunTest.php
git commit -m "feat(imunisasi): dasbor tampilkan kohort BBL/SI/Baduta & tandai blok operasional"
```

---

### Task 11: Test memori + verifikasi angka di data nyata

**Files:**
- Modify: `tests/Feature/Imunisasi/ImunisasiDashboardMemoriTest.php:54-59`
- Create: `docs/superpowers/plans/2026-09-24-verifikasi-angka-kohort.md` (catatan hasil)

**Interfaces:**
- Consumes: seluruh method dari Task 2–8
- Produces: tidak ada API baru

- [ ] **Step 1: Perbarui test memori**

```php
        $kohort = \App\Support\KohortImunisasi::dari((int) date('Y'));

        $coverage   = $service->getIdlCoverage($kohort);
        $butuhKejar = $service->getButuhKejar();
        $ibl        = $service->getIblCoverage($kohort);
        $funnel     = $service->getFunnelDosis($kohort);
        $antigen    = $service->getCakupanAntigen($kohort);
        $kohortWil  = $service->getKohortWilayah($kohort);
        $rincian    = $service->getRincianPuskesmas($kohort);
```

Ambang kenaikan memori puncak tetap `< 16 MB` walau kini ada satu pass tambahan (`getButuhKejar`). Jangan naikkan ambangnya — kalau terlampaui, itu sinyal ada query yang lupa lewat `eachAnak()`.

- [ ] **Step 2: Jalankan test memori**

Run: `php artisan test tests/Feature/Imunisasi/ImunisasiDashboardMemoriTest.php`
Expected: PASS — kenaikan memori puncak < 16 MB dengan 2.000 anak

- [ ] **Step 3: Jalankan seluruh suite imunisasi**

Run: `php artisan test tests/Feature/Imunisasi tests/Unit/Support/KohortImunisasiTest.php`
Expected: PASS semua. Ingat: jangan jalankan proses tes lain bersamaan — `sirindu_testing` dipakai bersama.

- [ ] **Step 4: Ukur pergeseran angka di data nyata**

Spec sengaja tidak menebak besaran pergeseran; dev cuma punya 39 anak. Jalankan pembanding ini di salinan data prod (bukan di prod langsung), catat hasilnya, dan laporkan ke pemilik produk SEBELUM rilis:

```bash
php artisan tinker --execute="
\$s = app(App\Services\ImunisasiStatusService::class);
\$k = App\Support\KohortImunisasi::dari((int) date('Y'));
\$sasaran = \$s->getRingkasanSasaran(\$k);
\$idl = \$s->getIdlCoverage(\$k);
\$ibl = \$s->getIblCoverage(\$k);
printf(\"BBL %d | SI %d | Baduta %d\n\", \$sasaran['bbl']['jumlah'], \$sasaran['si']['jumlah'], \$sasaran['baduta']['jumlah']);
printf(\"IDL %d/%d = %.1f%%\n\", \$idl['idl_lengkap'], \$idl['total'], \$idl['persen']);
printf(\"IBL %d/%d = %.1f%%\n\", \$ibl['ibl_lengkap'], \$ibl['total'], \$ibl['persen']);
printf(\"Butuh kejar (operasional) %d\n\", \$s->getButuhKejar());
"
```

Bandingkan dengan angka dasbor sebelum perubahan. Yang perlu dijawab: berapa persen IDL bergeser, dan apakah pergeseran itu bisa dijelaskan sepenuhnya oleh penyempitan penyebut. Kalau ada selisih yang tidak bisa dijelaskan, **hentikan rilis** dan telusuri — jangan anggap angka baru otomatis benar.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/Imunisasi/ImunisasiDashboardMemoriTest.php docs/superpowers/plans/2026-09-24-verifikasi-angka-kohort.md
git commit -m "test(imunisasi): test memori ikut tanda tangan kohort + catatan verifikasi angka prod"
```

# Dasbor Kesmas Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Halaman `admin/kesmas-dashboard` — dasbor tumbuh kembang balita (4 kartu SPM prorata, SDIDTK, CKG, IDL/IBL, Layanan & Lingkungan, registri per anak) di atas data `anak`/`data_anak`/imunisasi yang sudah ada.

**Architecture:** `KesmasDashboardService` menghitung semua agregat murni SQL (`DB::table` + `SUM(CASE)`/`GROUP BY`/`joinSub`) dari satu subquery sasaran (umur pada akhir periode) dan satu subquery kunjungan-per-anak; `PeriodeKesmas` (value object) menerjemahkan filter tahun/periode menjadi rentang tanggal + syarat prorata. Halaman di-render server-side (pola dasbor imunisasi); hanya registri yang lewat endpoint JSON + JS vanilla. IDL/IBL dan kategori gizi dipakai ulang dari `ImunisasiStatusService`/`StatusGiziService`.

**Tech Stack:** Laravel 12, PHP 8.4, MySQL 8 (`sirindu_testing` untuk PHPUnit), Blade + layout `admin::layouts.app` (Bootstrap 4, jQuery di `core.js`), Chart.js CDN, PHPUnit via `php artisan test`.

**Spec:** `docs/superpowers/specs/2026-09-21-dasbor-kesmas-design.md`

## Global Constraints

- Semua agregat halaman utama **tanpa** memuat model `Anak` massal; model hanya di `registri()` (≤ 20/halaman). Batas tes memori: kenaikan puncak < 16 MB untuk 2.000 anak; query enam method service ≤ 20.
- Sasaran = umur `TIMESTAMPDIFF(MONTH, tgl_lahir, akhir_periode)`; `tgl_lahir` NULL atau > akhir periode tidak dihitung.
- Syarat prorata: `syarat(8)` = 8/4/2, `syarat(2)` = 2/1/1; DDTKA & Vit A dievaluasi pada semester induk; `periode='tahun'` → semester = tahun penuh.
- `ddtka` dianggap terisi bila `ddtka IS NOT NULL AND TRIM(ddtka) <> ''`.
- Pembagi 0 → persen `null` → tampil "—" (bukan 0 %). Format angka Indonesia: `number_format($n, 0, ',', '.')`, persen 1 desimal koma.
- Seksi Layanan & Lingkungan: pembagi = anak yang kolomnya `IS NOT NULL`; "belum diisi" selalu ditampilkan; seluruh panel tanpa data → empty-state.
- Akses: `auth` + tolak `isFaskesSurveilans()` (403). Tanpa scoping per kelurahan.
- Blade: `@section('x') isi @endsection` **berspasi**; BS4 `data-toggle` (bukan `data-bs-toggle`); jangan jQuery `:hidden` pada `<option>`; setiap kontrol filter `label for` = `id`; kontras ≥ 4,5:1 (jangan pakai teal `#0891b2` + putih).
- Setelah mengubah Blade: `php artisan view:clear` sebelum menyimpulkan.
- Jangan menjalankan dua proses `php artisan test` bersamaan (DB `sirindu_testing` dipakai bersama). Suite penuh > 10 menit — pakai `--filter`.
- PHP: `D:\apps\laragon\bin\php\php-8.4.7-Win32-vs17-x64\php.exe` bila `php` tidak di PATH. MySQL lokal harus sudah hidup.
- Commit pesan bahasa Indonesia, diakhiri dua baris atribusi:
  `Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>` dan
  `Claude-Session: https://claude.ai/code/session_011UJEVC5xw87p91CXS1BnFv`.

## Struktur berkas

| Berkas | Tanggung jawab |
|---|---|
| `app/Support/PeriodeKesmas.php` (baru) | tahun+kode periode → awal/akhir/semester/p/syarat/label; murni PHP |
| `app/Support/FilterWilayahAnak.php` (baru, trait) | `applyWilayahFilters($query, $filters, $alias)` dipakai dua service |
| `app/Services/ImunisasiStatusService.php` (ubah) | pakai trait; tambah `getAlasanTidakImunisasi()` (dipindah dari controller) |
| `app/Http/Controllers/AdminController.php` (ubah) | `imunisasiDashboard()` memanggil service; hapus method privat |
| `app/Services/KesmasDashboardService.php` (baru) | `sasaran`, `spmKohort`, `pemantauanTk`, `sdidtk`, `ckg`, `layananLingkungan`, `registri`, `kategoriGizi` |
| `app/Http/Controllers/KesmasDashboardController.php` (baru) | `index`, `registri`; validasi & 403 |
| `routes/web.php` (ubah) | `admin.kesmas.dashboard`, `admin.kesmas.registri` |
| `resources/views/vendor/admin/layouts/partials/leftsidebar.blade.php` (ubah) | menu "Kesmas" dua cabang |
| `public/css/dasbor-base.css` (baru) | token & komponen `.im-*` dasar, dipindah dari dasbor imunisasi |
| `resources/views/admin/imunisasi/dashboard.blade.php` (ubah) | link CSS dasar, blok CSS dasar dihapus |
| `resources/views/admin/kesmas/dashboard.blade.php` + `partials/_filter.blade.php`, `partials/_registri.blade.php` (baru) | halaman |
| `tests/Unit/PeriodeKesmasTest.php`, `tests/Feature/Kesmas/KesmasDashboard{Service,Controller,Blade,Memori}Test.php`, `tests/Feature/Kesmas/KesmasRegistriTest.php` (baru) | pengunci |

---

### Task 1: `PeriodeKesmas` value object

**Files:**
- Create: `app/Support/PeriodeKesmas.php`
- Test: `tests/Unit/PeriodeKesmasTest.php`

**Interfaces:**
- Produces: `PeriodeKesmas::dari(int $tahun, string $periode = 'tahun'): self`; `tahun(): int`; `kode(): string`; `awal()/akhir()/semesterAwal()/semesterAkhir(): CarbonImmutable`; `p(): float`; `syarat(int $nPerTahun): int`; `label(): string`; konstanta `PeriodeKesmas::KODE`.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php
// tests/Unit/PeriodeKesmasTest.php

namespace Tests\Unit;

use App\Support\PeriodeKesmas;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PeriodeKesmasTest extends TestCase
{
    public function test_tahun_penuh(): void
    {
        $p = PeriodeKesmas::dari(2025, 'tahun');
        $this->assertSame('2025-01-01', $p->awal()->toDateString());
        $this->assertSame('2025-12-31', $p->akhir()->toDateString());
        $this->assertSame('2025-01-01', $p->semesterAwal()->toDateString(), 'Untuk tahun, semester = tahun penuh.');
        $this->assertSame('2025-12-31', $p->semesterAkhir()->toDateString());
        $this->assertSame(1.0, $p->p());
        $this->assertSame(8, $p->syarat(8));
        $this->assertSame(2, $p->syarat(2));
        $this->assertSame('Tahun 2025', $p->label());
        $this->assertSame(2025, $p->tahun());
        $this->assertSame('tahun', $p->kode());
    }

    public function test_semester(): void
    {
        $s1 = PeriodeKesmas::dari(2025, 's1');
        $this->assertSame(['2025-01-01', '2025-06-30'], [$s1->awal()->toDateString(), $s1->akhir()->toDateString()]);
        $this->assertSame(['2025-01-01', '2025-06-30'], [$s1->semesterAwal()->toDateString(), $s1->semesterAkhir()->toDateString()]);
        $this->assertSame(4, $s1->syarat(8));
        $this->assertSame(1, $s1->syarat(2));
        $this->assertSame('Semester I 2025 (1 Jan–30 Jun)', $s1->label());

        $s2 = PeriodeKesmas::dari(2025, 's2');
        $this->assertSame(['2025-07-01', '2025-12-31'], [$s2->awal()->toDateString(), $s2->akhir()->toDateString()]);
        $this->assertSame('Semester II 2025 (1 Jul–31 Des)', $s2->label());
    }

    public function test_triwulan_memakai_semester_induk(): void
    {
        $tw3 = PeriodeKesmas::dari(2025, 'tw3');
        $this->assertSame(['2025-07-01', '2025-09-30'], [$tw3->awal()->toDateString(), $tw3->akhir()->toDateString()]);
        $this->assertSame(['2025-07-01', '2025-12-31'], [$tw3->semesterAwal()->toDateString(), $tw3->semesterAkhir()->toDateString()]);
        $this->assertSame(0.25, $tw3->p());
        $this->assertSame(2, $tw3->syarat(8));
        $this->assertSame(1, $tw3->syarat(2));
        $this->assertSame('Triwulan III 2025 (1 Jul–30 Sep)', $tw3->label());

        $tw2 = PeriodeKesmas::dari(2025, 'tw2');
        $this->assertSame(['2025-04-01', '2025-06-30'], [$tw2->awal()->toDateString(), $tw2->akhir()->toDateString()]);
        $this->assertSame(['2025-01-01', '2025-06-30'], [$tw2->semesterAwal()->toDateString(), $tw2->semesterAkhir()->toDateString()]);

        $tw4 = PeriodeKesmas::dari(2024, 'tw4');
        $this->assertSame(['2024-10-01', '2024-12-31'], [$tw4->awal()->toDateString(), $tw4->akhir()->toDateString()]);
        $this->assertSame('Triwulan IV 2024 (1 Okt–31 Des)', $tw4->label());
    }

    public function test_periode_tidak_dikenal_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PeriodeKesmas::dari(2025, 'bulan');
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --filter=PeriodeKesmasTest`
Expected: FAIL — `Class "App\Support\PeriodeKesmas" not found`.

- [ ] **Step 3: Implementasi**

```php
<?php
// app/Support/PeriodeKesmas.php

namespace App\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Periode dasbor Kesmas — spec docs/superpowers/specs/2026-09-21-dasbor-kesmas-design.md §2.2.
 * Syarat "N× setahun" diprorata: tahun ×1, semester ×½, triwulan ×¼.
 * DDTKA & Vit A dievaluasi pada semester induk (jadwalnya semesteran); untuk
 * periode 'tahun' semester = tahun penuh. Murni PHP, tanpa DB.
 */
final class PeriodeKesmas
{
    public const KODE = ['tahun', 's1', 's2', 'tw1', 'tw2', 'tw3', 'tw4'];

    private const BULAN = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    private function __construct(
        private readonly int $tahun,
        private readonly string $kode,
        private readonly CarbonImmutable $awal,
        private readonly CarbonImmutable $akhir,
        private readonly CarbonImmutable $semesterAwal,
        private readonly CarbonImmutable $semesterAkhir,
        private readonly float $p,
    ) {
    }

    public static function dari(int $tahun, string $periode = 'tahun'): self
    {
        if (!in_array($periode, self::KODE, true)) {
            throw new InvalidArgumentException("Periode tidak dikenal: {$periode}");
        }

        $tgl = fn (int $bulan, int $hari) => CarbonImmutable::create($tahun, $bulan, $hari)->startOfDay();

        [$awal, $akhir, $p] = match ($periode) {
            'tahun' => [$tgl(1, 1), $tgl(12, 31), 1.0],
            's1'    => [$tgl(1, 1), $tgl(6, 30), 0.5],
            's2'    => [$tgl(7, 1), $tgl(12, 31), 0.5],
            'tw1'   => [$tgl(1, 1), $tgl(3, 31), 0.25],
            'tw2'   => [$tgl(4, 1), $tgl(6, 30), 0.25],
            'tw3'   => [$tgl(7, 1), $tgl(9, 30), 0.25],
            'tw4'   => [$tgl(10, 1), $tgl(12, 31), 0.25],
        };

        [$sAwal, $sAkhir] = match (true) {
            $periode === 'tahun' => [$awal, $akhir],
            $awal->month <= 6    => [$tgl(1, 1), $tgl(6, 30)],
            default              => [$tgl(7, 1), $tgl(12, 31)],
        };

        return new self($tahun, $periode, $awal, $akhir, $sAwal, $sAkhir, $p);
    }

    public function tahun(): int { return $this->tahun; }
    public function kode(): string { return $this->kode; }
    public function awal(): CarbonImmutable { return $this->awal; }
    public function akhir(): CarbonImmutable { return $this->akhir; }
    public function semesterAwal(): CarbonImmutable { return $this->semesterAwal; }
    public function semesterAkhir(): CarbonImmutable { return $this->semesterAkhir; }
    public function p(): float { return $this->p; }

    /** Syarat minimal dalam periode ini untuk indikator "N× setahun". */
    public function syarat(int $nPerTahun): int
    {
        return (int) ceil($nPerTahun * $this->p);
    }

    public function label(): string
    {
        $rentang = sprintf(
            '(%d %s–%d %s)',
            $this->awal->day, self::BULAN[$this->awal->month],
            $this->akhir->day, self::BULAN[$this->akhir->month]
        );

        return match ($this->kode) {
            'tahun' => "Tahun {$this->tahun}",
            's1'    => "Semester I {$this->tahun} {$rentang}",
            's2'    => "Semester II {$this->tahun} {$rentang}",
            'tw1'   => "Triwulan I {$this->tahun} {$rentang}",
            'tw2'   => "Triwulan II {$this->tahun} {$rentang}",
            'tw3'   => "Triwulan III {$this->tahun} {$rentang}",
            'tw4'   => "Triwulan IV {$this->tahun} {$rentang}",
        };
    }
}
```

- [ ] **Step 4: Jalankan, pastikan lulus**

Run: `php artisan test --filter=PeriodeKesmasTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Support/PeriodeKesmas.php tests/Unit/PeriodeKesmasTest.php
git commit -m "feat(kesmas): PeriodeKesmas — rentang tanggal & syarat prorata dasbor"
```

---

### Task 2: Trait `FilterWilayahAnak` + `getAlasanTidakImunisasi` ke service

Refactor tanpa mengubah perilaku, agar `KesmasDashboardService` bisa memakai filter wilayah yang sama dan kartu IDL memakai daftar alasan yang sama dengan dasbor imunisasi.

**Files:**
- Create: `app/Support/FilterWilayahAnak.php`
- Modify: `app/Services/ImunisasiStatusService.php` (hapus `applyWilayahFilters` privat ±baris 290–322; tambah `use` trait; tambah method `getAlasanTidakImunisasi`)
- Modify: `app/Http/Controllers/AdminController.php` (`imunisasiDashboard()` baris 629; hapus `alasanTidakImunisasiData()` baris 701–732)
- Test: `tests/Feature/Imunisasi/ImunisasiRutinDashboardServiceTest.php` (tambah 1 test)

**Interfaces:**
- Produces: `trait App\Support\FilterWilayahAnak { protected function applyWilayahFilters($query, array $filters, string $alias = '') }` — `$query` Eloquent atau Query Builder, mengembalikan query yang sama; `$alias` untuk `DB::table('anak as a')`.
- Produces: `ImunisasiStatusService::getAlasanTidakImunisasi(array $filters): array<string,int>` (alasan → jumlah, urut menurun).

- [ ] **Step 1: Tulis tes yang gagal** — tambahkan ke `ImunisasiRutinDashboardServiceTest`:

```php
    public function test_alasan_tidak_imunisasi_dihitung_dari_kunjungan_terakhir_per_anak(): void
    {
        $known = config('imunisasi.alasan_tidak_imunisasi', []);
        $this->assertNotEmpty($known, 'Config alasan harus ada agar bucket "Lainnya" bisa diuji.');
        $alasanDikenal = $known[0];

        $a = $this->anak();
        \App\Models\DataAnak::create(['id_anak' => $a->id, 'tgl_kunjungan' => '2026-01-10', 'bln' => 1, 'posisi' => 'L', 'tb' => 50, 'bb' => 4, 'lla' => 10, 'lk' => 35, 'id_user' => 1, 'alasan_tidak_imunisasi' => 'Sembarang teks lama']);
        \App\Models\DataAnak::create(['id_anak' => $a->id, 'tgl_kunjungan' => '2026-03-10', 'bln' => 3, 'posisi' => 'L', 'tb' => 55, 'bb' => 5, 'lla' => 11, 'lk' => 37, 'id_user' => 1, 'alasan_tidak_imunisasi' => $alasanDikenal]);
        $b = $this->anak();
        \App\Models\DataAnak::create(['id_anak' => $b->id, 'tgl_kunjungan' => '2026-02-01', 'bln' => 2, 'posisi' => 'L', 'tb' => 52, 'bb' => 4.5, 'lla' => 10, 'lk' => 36, 'id_user' => 1, 'alasan_tidak_imunisasi' => 'Teks bebas tak dikenal']);

        $hasil = $this->service->getAlasanTidakImunisasi([]);

        $this->assertSame([$alasanDikenal => 1, 'Lainnya' => 1], $hasil, 'Hanya kunjungan TERAKHIR per anak; teks tak dikenal masuk bucket Lainnya.');
    }
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --filter=test_alasan_tidak_imunisasi_dihitung_dari_kunjungan_terakhir_per_anak`
Expected: FAIL — `Call to undefined method ... getAlasanTidakImunisasi()`.

- [ ] **Step 3: Buat trait**

```php
<?php
// app/Support/FilterWilayahAnak.php

namespace App\Support;

use App\Models\Puskesmas;

/**
 * Filter wilayah untuk query anak — dipakai ImunisasiStatusService dan
 * KesmasDashboardService supaya "wilayah terpilih" berarti sama di semua dasbor.
 *
 * Semua dimensi yang diisi digabung dengan AND (bukan saling meniadakan) —
 * cascading filter UI hanya pernah mengisi satu jalur konsisten (mis. kelurahan
 * yang benar-benar ada di kecamatan terpilih), sekaligus bisa mengombinasikan
 * puskesmas (wilker, lintas kelurahan) dengan RT tanpa saling menimpa.
 */
trait FilterWilayahAnak
{
    /**
     * @template TQuery of \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     * @param  TQuery  $query
     * @param  array{id_kecamatan?: int, id_kelurahan?: int, id_rt?: int, id_posyandu?: int, id_puskesmas?: int}  $filters
     * @param  string  $alias  alias tabel anak bila query memakai `anak as a` (kosong = tanpa prefiks)
     * @return TQuery
     */
    protected function applyWilayahFilters($query, array $filters, string $alias = '')
    {
        $kolom = fn (string $nama) => $alias === '' ? $nama : "{$alias}.{$nama}";

        if (!empty($filters['id_kecamatan'])) {
            $query->where($kolom('id_kec'), $filters['id_kecamatan']);
        }
        if (!empty($filters['id_kelurahan'])) {
            $query->where($kolom('id_kel'), $filters['id_kelurahan']);
        }
        if (!empty($filters['id_rt'])) {
            $query->where($kolom('id_rt'), $filters['id_rt']);
        }
        if (!empty($filters['id_posyandu'])) {
            $query->where($kolom('id_posyandu'), $filters['id_posyandu']);
        }
        if (!empty($filters['id_puskesmas'])) {
            $namaPuskesmas = Puskesmas::whereKey($filters['id_puskesmas'])->value('name');
            $kelIds = $namaPuskesmas ? WilkerPuskesmas::catchmentKelurahanIds($namaPuskesmas) : [];
            $query->whereIn($kolom('id_kel'), $kelIds ?: [0]);
        }

        return $query;
    }
}
```

- [ ] **Step 4: Ubah `ImunisasiStatusService`**

1. Tambah import di atas: `use App\Support\FilterWilayahAnak;` dan `use Illuminate\Support\Facades\DB;`.
2. Di dalam class, tepat setelah baris `class ImunisasiStatusService` `{`, tambah `use FilterWilayahAnak;`.
3. Hapus method `private function applyWilayahFilters(...)` beserta docblock-nya (blok yang diawali komentar "Terapkan filter wilayah ke query Anak" sampai `return $query; }`). Pemanggil di dalam class (`$this->applyWilayahFilters(Anak::query(), $filters)`) tidak perlu diubah.
4. Tambahkan method publik berikut (isi dipindah apa adanya dari `AdminController::alasanTidakImunisasiData`):

```php
    /**
     * Sebaran alasan tidak imunisasi dari KUNJUNGAN TERAKHIR tiap anak (wilayah
     * terfilter); nilai di luar config('imunisasi.alasan_tidak_imunisasi') digabung
     * ke "Lainnya". Dipakai dasbor imunisasi & dasbor Kesmas.
     *
     * @return array<string, int>  alasan => jumlah, urut menurun
     */
    public function getAlasanTidakImunisasi(array $filters): array
    {
        $maxTgl = DB::table('data_anak as dm')
            ->join('anak as am', 'dm.id_anak', '=', 'am.id')
            ->selectRaw('dm.id_anak, MAX(dm.tgl_kunjungan) as max_tgl')
            ->whereNotNull('dm.tgl_kunjungan');
        if (!empty($filters['id_posyandu']))      $maxTgl->where('am.id_posyandu', $filters['id_posyandu']);
        elseif (!empty($filters['id_kelurahan'])) $maxTgl->where('am.id_kel', $filters['id_kelurahan']);
        elseif (!empty($filters['id_kecamatan'])) $maxTgl->where('am.id_kec', $filters['id_kecamatan']);
        $maxTgl->groupBy('dm.id_anak');

        $values = DB::table('data_anak as da')
            ->joinSub($maxTgl, 'm', function ($j) {
                $j->on('m.id_anak', '=', 'da.id_anak')->on('m.max_tgl', '=', 'da.tgl_kunjungan');
            })
            ->whereNotNull('da.alasan_tidak_imunisasi')
            ->where('da.alasan_tidak_imunisasi', '!=', '')
            ->pluck('da.alasan_tidak_imunisasi');

        $known  = config('imunisasi.alasan_tidak_imunisasi', []);
        $counts = [];
        foreach ($values as $val) {
            $val = trim((string) $val);
            if ($val === '') continue;
            $bucket = in_array($val, $known, true) ? $val : 'Lainnya';
            $counts[$bucket] = ($counts[$bucket] ?? 0) + 1;
        }
        arsort($counts);

        return $counts;
    }
```

- [ ] **Step 5: Ubah `AdminController`**

Baris `$alasanTidakImunisasi = $this->alasanTidakImunisasiData($filters);` di `imunisasiDashboard()` → `$alasanTidakImunisasi = $service->getAlasanTidakImunisasi($filters);` (`$service` sudah ada di method itu). Hapus seluruh `private function alasanTidakImunisasiData(array $filters): array { … }`.

- [ ] **Step 6: Jalankan tes terkait**

Run: `php artisan test --filter="ImunisasiRutinDashboardServiceTest|ImunisasiDashboardControllerTest|ImunisasiDashboardMemoriTest"`
Expected: PASS semua (termasuk test baru).

- [ ] **Step 7: Commit**

```bash
git add app/Support/FilterWilayahAnak.php app/Services/ImunisasiStatusService.php app/Http/Controllers/AdminController.php tests/Feature/Imunisasi/ImunisasiRutinDashboardServiceTest.php
git commit -m "refactor(imunisasi): filter wilayah jadi trait & alasan tidak imunisasi pindah ke service"
```

---
### Task 3: `KesmasDashboardService` — sasaran & kartu SPM (K1–K3)

**Files:**
- Create: `app/Services/KesmasDashboardService.php`
- Test: `tests/Feature/Kesmas/KesmasDashboardServiceTest.php`

**Interfaces:**
- Consumes: `PeriodeKesmas` (Task 1), trait `FilterWilayahAnak` (Task 2).
- Produces:
  - `KesmasDashboardService::persen(int $n, int $sasaran): ?float` (statis).
  - `sasaran(PeriodeKesmas $p, array $filters): array{bayi,bayi_6_11,baduta,anak_balita,balita,balita_24_59,prasekolah,semua,tahun_0,tahun_1,tahun_2,tahun_3_6: int}`.
  - `spmKohort(PeriodeKesmas $p, array $filters): array{syarat: array{timbang,ddtka,vita: int}, balita: array{sasaran,lengkap: int, persen: ?float}, bayi: array{sasaran,lengkap,sisa: int, persen: ?float, sub: array<string, array{n,sasaran: int, persen: ?float}>}, anak_balita: array{sasaran,lengkap,gap: int, persen: ?float, sub: array<string, array{n,sasaran: int, persen: ?float}>}}` — kunci `sub`: bayi `timbang|ddtka|vita|lk`, anak_balita `timbang|ddtka|vita`.
  - Konstanta `KELOMPOK` (bayi/baduta/balita/prasekolah → `[min, max, label, domain]`), privat `sasaranSub()`, `dariSasaran()`, `kunjunganSub()` dipakai task berikutnya.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php
// tests/Feature/Kesmas/KesmasDashboardServiceTest.php

namespace Tests\Feature\Kesmas;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Services\ImunisasiStatusService;
use App\Services\KesmasDashboardService;
use App\Support\PeriodeKesmas;
use App\Support\WilkerPuskesmas;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Agregat dasbor Kesmas (spec 2026-09-21 §2–3). Semua tes memakai periode TETAP
 * (tahun 2025 / triwulan 2025) supaya tidak bergantung tanggal hari ini; umur anak
 * dihitung pada AKHIR periode (31 Des 2025 untuk 'tahun').
 */
class KesmasDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    private KesmasDashboardService $svc;
    private Kecamatan $kec;
    private Kelurahan $kel;
    private Kelurahan $kelLain;

    protected function setUp(): void
    {
        parent::setUp();
        ImunisasiStatusService::flushCache();
        WilkerPuskesmas::flushCache();
        $this->svc = app(KesmasDashboardService::class);
        $this->kec = Kecamatan::create(['name' => 'Bontang Selatan']);
        $this->kel = Kelurahan::create(['name' => 'Berbas Tengah', 'id_kecamatan' => $this->kec->id]);
        $this->kelLain = Kelurahan::create(['name' => 'Tanjung Laut', 'id_kecamatan' => $this->kec->id]);
    }

    private function tahun2025(): PeriodeKesmas
    {
        return PeriodeKesmas::dari(2025, 'tahun');
    }

    /** Anak yang berumur $umurBln bulan tepat pada 31 Des 2025. */
    private function anak(int $umurBln, array $extra = []): Anak
    {
        static $n = 0;
        $n++;

        return Anak::create(array_merge([
            'nama' => 'Anak Uji ' . $n, 'nik' => str_pad((string) $n, 16, '0', STR_PAD_LEFT), 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => Carbon::create(2025, 12, 31)->subMonths($umurBln)->toDateString(),
            'status' => 1, 'no' => '1', 'sumber' => 'manual',
            'id_kec' => $this->kec->id, 'id_kel' => $this->kel->id,
        ], $extra));
    }

    private function kunjungan(Anak $anak, string $tgl, array $extra = []): DataAnak
    {
        return DataAnak::create(array_merge([
            'id_anak' => $anak->id, 'tgl_kunjungan' => $tgl, 'bln' => 1, 'posisi' => 'L',
            'tb' => 55, 'bb' => 4.5, 'lla' => 11, 'lk' => 38, 'id_user' => 1, 'sumber' => 'manual',
        ], $extra));
    }

    /** $n kunjungan tanggal 15 tiap bulan, mulai bulan $mulai tahun 2025. */
    private function kunjunganBulanan(Anak $anak, int $n, int $mulai = 1, array $extra = []): void
    {
        for ($i = 0; $i < $n; $i++) {
            $this->kunjungan($anak, sprintf('2025-%02d-15', $mulai + $i), $extra);
        }
    }

    // ── Sasaran ────────────────────────────────────────────────────────────

    public function test_sasaran_memakai_umur_pada_akhir_periode(): void
    {
        $this->anak(5);    // bayi
        $this->anak(11);   // bayi (batas atas)
        $this->anak(12);   // baduta / anak balita
        $this->anak(30);   // balita 24–59
        $this->anak(65);   // prasekolah
        $this->anak(80);   // umur tahun 3–6 saja
        Anak::create(['nama' => 'Lahir tahun depan', 'nik' => '9999999999999999', 'jk' => 2, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2026-01-15', 'status' => 1, 'no' => '1', 'sumber' => 'manual', 'id_kec' => $this->kec->id, 'id_kel' => $this->kel->id]);
        Anak::create(['nama' => 'Tanpa tgl lahir', 'nik' => '9999999999999998', 'jk' => 2, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => null, 'status' => 1, 'no' => '1', 'sumber' => 'manual', 'id_kec' => $this->kec->id, 'id_kel' => $this->kel->id]);

        $s = $this->svc->sasaran($this->tahun2025(), []);

        $this->assertSame(2, $s['bayi']);
        $this->assertSame(1, $s['baduta']);
        $this->assertSame(2, $s['anak_balita'], '12–59 bulan');
        $this->assertSame(4, $s['balita'], '0–59 bulan');
        $this->assertSame(1, $s['prasekolah']);
        $this->assertSame(5, $s['semua'], '0–72 bulan; anak 80 bln & yang lahir setelah akhir periode tidak ikut');
        $this->assertSame(2, $s['tahun_0']);
        $this->assertSame(1, $s['tahun_1']);
        $this->assertSame(1, $s['tahun_2']);
        $this->assertSame(2, $s['tahun_3_6'], '36–83 bulan: anak 65 & 80');
    }

    public function test_sasaran_tahun_lalu_menghitung_anak_yang_kini_sudah_lebih_tua(): void
    {
        // Lahir 1 Jan 2024: pada 31 Des 2024 berumur 11 bln (bayi), pada 31 Des 2025 berumur 23 bln.
        Anak::create(['nama' => 'Anak 2024', 'nik' => '1111111111111111', 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'no' => '1', 'sumber' => 'manual', 'id_kec' => $this->kec->id, 'id_kel' => $this->kel->id]);

        $this->assertSame(1, $this->svc->sasaran(PeriodeKesmas::dari(2024, 'tahun'), [])['bayi']);
        $this->assertSame(0, $this->svc->sasaran($this->tahun2025(), [])['bayi']);
        $this->assertSame(1, $this->svc->sasaran($this->tahun2025(), [])['baduta']);
    }

    public function test_sasaran_mengikuti_filter_wilayah(): void
    {
        $this->anak(5);
        $this->anak(5, ['id_kel' => $this->kelLain->id]);

        $this->assertSame(2, $this->svc->sasaran($this->tahun2025(), [])['bayi']);
        $this->assertSame(1, $this->svc->sasaran($this->tahun2025(), ['id_kelurahan' => $this->kel->id])['bayi']);
        $this->assertSame(0, $this->svc->sasaran($this->tahun2025(), ['id_kecamatan' => $this->kec->id + 1000])['bayi']);
    }

    // ── SPM kohort bayi (K2) ───────────────────────────────────────────────

    public function test_bayi_lengkap_bila_8_timbang_2_ddtka_vit_a_dan_lk(): void
    {
        $lengkap = $this->anak(8);
        $this->kunjunganBulanan($lengkap, 6, 5);                                   // Mei–Okt, ddtka kosong
        $this->kunjungan($lengkap, '2025-11-15', ['ddtka' => 'Sesuai']);           // 7
        $this->kunjungan($lengkap, '2025-12-15', ['ddtka' => 'Sesuai', 'vit_a' => 1]); // 8

        $timbang7 = $this->anak(8);
        $this->kunjunganBulanan($timbang7, 5, 5);
        $this->kunjungan($timbang7, '2025-10-15', ['ddtka' => 'Sesuai']);
        $this->kunjungan($timbang7, '2025-11-15', ['ddtka' => 'Sesuai', 'vit_a' => 1]); // hanya 7 timbang

        $ddtka1 = $this->anak(8);
        $this->kunjunganBulanan($ddtka1, 7, 5);
        $this->kunjungan($ddtka1, '2025-12-15', ['ddtka' => 'Sesuai', 'vit_a' => 1]); // 8 timbang, 1 ddtka

        $tanpaVitA = $this->anak(8);
        $this->kunjunganBulanan($tanpaVitA, 6, 5);
        $this->kunjungan($tanpaVitA, '2025-11-15', ['ddtka' => 'Sesuai']);
        $this->kunjungan($tanpaVitA, '2025-12-15', ['ddtka' => 'Sesuai']); // umur 8 ≥ 6 → Vit A wajib

        $lkNol = $this->anak(8);
        $this->kunjunganBulanan($lkNol, 6, 5, ['lk' => 0]);
        $this->kunjungan($lkNol, '2025-11-15', ['ddtka' => 'Sesuai', 'lk' => 0]);
        $this->kunjungan($lkNol, '2025-12-15', ['ddtka' => 'Sesuai', 'vit_a' => 1, 'lk' => 0]);

        $spm = $this->svc->spmKohort($this->tahun2025(), []);

        $this->assertSame(['timbang' => 8, 'ddtka' => 2, 'vita' => 2], $spm['syarat']);
        $this->assertSame(5, $spm['bayi']['sasaran']);
        $this->assertSame(1, $spm['bayi']['lengkap']);
        $this->assertSame(20.0, $spm['bayi']['persen']);
        $this->assertSame(4, $spm['bayi']['sisa']);
        $this->assertSame(4, $spm['bayi']['sub']['timbang']['n'], '≥8 timbang: lengkap, ddtka1, tanpaVitA, lkNol');
        $this->assertSame(4, $spm['bayi']['sub']['ddtka']['n'], '≥2 ddtka: lengkap, timbang7, tanpaVitA, lkNol');
        $this->assertSame(4, $spm['bayi']['sub']['vita']['n'], 'Vit A: lengkap, timbang7, ddtka1, lkNol');
        $this->assertSame(5, $spm['bayi']['sub']['vita']['sasaran'], 'Pembagi Vit A bayi = 6–11 bln');
        $this->assertSame(4, $spm['bayi']['sub']['lk']['n']);
    }

    public function test_bayi_di_bawah_6_bulan_dibebaskan_dari_vit_a(): void
    {
        $bayi4 = $this->anak(4);
        $this->kunjunganBulanan($bayi4, 6, 5);
        $this->kunjungan($bayi4, '2025-11-15', ['ddtka' => 'Sesuai']);
        $this->kunjungan($bayi4, '2025-12-15', ['ddtka' => 'Sesuai']); // tanpa vit_a

        $spm = $this->svc->spmKohort($this->tahun2025(), []);

        $this->assertSame(1, $spm['bayi']['lengkap']);
        $this->assertSame(0, $spm['bayi']['sub']['vita']['sasaran'], 'Bayi <6 bln tidak masuk pembagi Vit A');
        $this->assertNull($spm['bayi']['sub']['vita']['persen'], 'Pembagi 0 → persen null, bukan 0.0');
    }

    public function test_ddtka_kosong_atau_spasi_tidak_dihitung(): void
    {
        $a = $this->anak(8);
        $this->kunjunganBulanan($a, 6, 5);
        $this->kunjungan($a, '2025-11-15', ['ddtka' => '   ']);
        $this->kunjungan($a, '2025-12-15', ['ddtka' => '', 'vit_a' => 1]);

        $spm = $this->svc->spmKohort($this->tahun2025(), []);

        $this->assertSame(0, $spm['bayi']['sub']['ddtka']['n']);
        $this->assertSame(0, $spm['bayi']['lengkap']);
    }

    // ── SPM kohort anak balita (K3) & gabungan (K1) ────────────────────────

    public function test_anak_balita_lengkap_bila_8_timbang_2_ddtka_2_vit_a(): void
    {
        $lengkap = $this->anak(30);
        $this->kunjunganBulanan($lengkap, 6, 1);
        $this->kunjungan($lengkap, '2025-02-20', ['ddtka' => 'Sesuai', 'vit_a' => 1]);
        $this->kunjungan($lengkap, '2025-08-20', ['ddtka' => 'Meragukan', 'vit_a' => 1]);

        $vitA1 = $this->anak(40);
        $this->kunjunganBulanan($vitA1, 7, 1);
        $this->kunjungan($vitA1, '2025-08-20', ['ddtka' => 'Sesuai', 'vit_a' => 1]);
        $this->kunjungan($vitA1, '2025-09-20', ['ddtka' => 'Sesuai']); // 9 timbang, 2 ddtka, 1 vit A

        $spm = $this->svc->spmKohort($this->tahun2025(), []);

        $this->assertSame(2, $spm['anak_balita']['sasaran']);
        $this->assertSame(1, $spm['anak_balita']['lengkap']);
        $this->assertSame(50.0, $spm['anak_balita']['persen']);
        $this->assertSame(1, $spm['anak_balita']['gap']);
        $this->assertSame(2, $spm['anak_balita']['sub']['timbang']['n']);
        $this->assertSame(2, $spm['anak_balita']['sub']['ddtka']['n']);
        $this->assertSame(1, $spm['anak_balita']['sub']['vita']['n']);
        $this->assertSame(2, $spm['anak_balita']['sub']['vita']['sasaran']);

        // K1 = gabungan bayi + anak balita (di sini tidak ada bayi).
        $this->assertSame(2, $spm['balita']['sasaran']);
        $this->assertSame(1, $spm['balita']['lengkap']);
        $this->assertSame(50.0, $spm['balita']['persen']);
    }

    public function test_k1_menggabungkan_bayi_dan_anak_balita(): void
    {
        $bayi = $this->anak(8);
        $this->kunjunganBulanan($bayi, 6, 5);
        $this->kunjungan($bayi, '2025-11-15', ['ddtka' => 'Sesuai']);
        $this->kunjungan($bayi, '2025-12-15', ['ddtka' => 'Sesuai', 'vit_a' => 1]);
        $this->anak(30); // anak balita tanpa kunjungan
        $this->anak(65); // prasekolah — bukan sasaran K1

        $spm = $this->svc->spmKohort($this->tahun2025(), []);

        $this->assertSame(2, $spm['balita']['sasaran'], '0–59 bln saja');
        $this->assertSame(1, $spm['balita']['lengkap']);
    }

    public function test_triwulan_memprorata_syarat_dan_menilai_ddtka_pada_semester_induk(): void
    {
        $tw3 = PeriodeKesmas::dari(2025, 'tw3'); // T8=2, T2=1; semester induk Jul–Des

        $lengkap = $this->anak(30);
        $this->kunjungan($lengkap, '2025-07-10');
        $this->kunjungan($lengkap, '2025-08-10');
        $this->kunjungan($lengkap, '2025-11-10', ['ddtka' => 'Sesuai', 'vit_a' => 1]); // di luar TW3 tapi masih semester II

        $ddtkaSemesterLain = $this->anak(30);
        $this->kunjungan($ddtkaSemesterLain, '2025-02-10', ['ddtka' => 'Sesuai', 'vit_a' => 1]); // semester I
        $this->kunjungan($ddtkaSemesterLain, '2025-07-10');
        $this->kunjungan($ddtkaSemesterLain, '2025-08-10');

        $timbang1 = $this->anak(30);
        $this->kunjungan($timbang1, '2025-07-10', ['ddtka' => 'Sesuai', 'vit_a' => 1]);

        $spm = $this->svc->spmKohort($tw3, []);

        $this->assertSame(['timbang' => 2, 'ddtka' => 1, 'vita' => 1], $spm['syarat']);
        $this->assertSame(3, $spm['anak_balita']['sasaran']);
        $this->assertSame(1, $spm['anak_balita']['lengkap']);
        $this->assertSame(2, $spm['anak_balita']['sub']['timbang']['n']);
        $this->assertSame(2, $spm['anak_balita']['sub']['ddtka']['n'], 'DDTKA Nov (semester II) dihitung; Feb (semester I) tidak');
    }

    public function test_kunjungan_di_luar_periode_tidak_dihitung(): void
    {
        $a = $this->anak(30);
        $this->kunjunganBulanan($a, 8, 1);
        $this->kunjungan($a, '2024-12-20', ['ddtka' => 'Sesuai', 'vit_a' => 1]);
        $this->kunjungan($a, '2026-01-05', ['ddtka' => 'Sesuai', 'vit_a' => 1]);

        $spm = $this->svc->spmKohort($this->tahun2025(), []);

        $this->assertSame(1, $spm['anak_balita']['sub']['timbang']['n']);
        $this->assertSame(0, $spm['anak_balita']['sub']['ddtka']['n']);
        $this->assertSame(0, $spm['anak_balita']['sub']['vita']['n']);
    }

    public function test_tanpa_anak_semua_nol_dan_persen_null(): void
    {
        $spm = $this->svc->spmKohort($this->tahun2025(), []);

        $this->assertSame(0, $spm['balita']['sasaran']);
        $this->assertNull($spm['balita']['persen']);
        $this->assertNull($spm['bayi']['sub']['timbang']['persen']);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --filter=KesmasDashboardServiceTest`
Expected: FAIL — `Class "App\Services\KesmasDashboardService" not found`.

- [ ] **Step 3: Implementasi service (bagian 1)**

```php
<?php
// app/Services/KesmasDashboardService.php

namespace App\Services;

use App\Support\FilterWilayahAnak;
use App\Support\PeriodeKesmas;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Agregat dasbor Kesmas — spec docs/superpowers/specs/2026-09-21-dasbor-kesmas-design.md §2–3.
 *
 * Semua agregat murni SQL (SUM/COUNT/GROUP BY/joinSub); model Anak hanya dimuat
 * di registri() (≤ 20 per halaman). JANGAN memuat populasi anak ke PHP di sini —
 * insiden OOM dasbor imunisasi 16 Sep 2026 (memory_limit prod 128 MB, ±10 rb anak).
 *
 * Dua subquery inti dipakai berulang:
 *  - sasaranSub(): anak terfilter wilayah + umur (bulan) pada AKHIR periode;
 *  - kunjunganSub(): jumlah timbang/DDTKA/Vit A/LK per anak dalam periode
 *    (DDTKA & Vit A pada semester induk — lihat PeriodeKesmas).
 */
class KesmasDashboardService
{
    use FilterWilayahAnak;

    /** Kelompok umur SDIDTK & registri: kode => [min, max, label, domain SDIDTK (teks statis pedoman)]. */
    public const KELOMPOK = [
        'bayi'       => [0, 11, 'Bayi', 'Motorik kasar, motorik halus & bahasa'],
        'baduta'     => [12, 23, 'Baduta', 'Kemandirian & KPSP'],
        'balita'     => [24, 59, 'Balita', 'Daya dengar & daya lihat'],
        'prasekolah' => [60, 72, 'Prasekolah', 'Kesiapan sekolah (PAUD)'],
    ];

    /** Kolom anak yang dibawa subquery sasaran (dipakai skrining neonatal & sanitasi). */
    private const KOLOM_KESMAS_ANAK = [
        'skrining_shk', 'skrining_shak', 'skrining_g6pd', 'pemeriksaan_hepatitis_b',
        'air_bersih', 'jamban_sehat', 'merokok_keluarga',
    ];

    /** Persen 1 desimal; pembagi 0 → null (tampil "—", bukan 0 %). */
    public static function persen(int $n, int $sasaran): ?float
    {
        return $sasaran > 0 ? round($n / $sasaran * 100, 1) : null;
    }

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

    private function dariSasaran(PeriodeKesmas $p, array $filters): Builder
    {
        return DB::query()->fromSub($this->sasaranSub($p, $filters), 's');
    }

    /**
     * Subquery `k`: per anak, jumlah kunjungan (timbang) & LK dalam periode, serta
     * DDTKA & Vit A dalam semester induk. Hanya anak yang punya kunjungan di
     * rentang gabungan yang muncul — pemanggil memakai LEFT JOIN + COALESCE(…,0).
     */
    private function kunjunganSub(PeriodeKesmas $p): Builder
    {
        [$a, $z]   = [$p->awal()->toDateString(), $p->akhir()->toDateString()];
        [$sa, $sz] = [$p->semesterAwal()->toDateString(), $p->semesterAkhir()->toDateString()];

        return DB::table('data_anak')
            ->selectRaw(
                'id_anak, '
                . 'SUM(tgl_kunjungan BETWEEN ? AND ?) as n_timbang, '
                . "SUM(tgl_kunjungan BETWEEN ? AND ? AND ddtka IS NOT NULL AND TRIM(ddtka) <> '') as n_ddtka, "
                . 'SUM(tgl_kunjungan BETWEEN ? AND ? AND vit_a = 1) as n_vita, '
                . 'SUM(tgl_kunjungan BETWEEN ? AND ? AND lk > 0) as n_lk',
                [$a, $z, $sa, $sz, $sa, $sz, $a, $z]
            )
            ->whereBetween('tgl_kunjungan', [min($a, $sa), max($z, $sz)])
            ->groupBy('id_anak');
    }

    /** Sasaran per kelompok umur dalam SATU query (§2.3). */
    public function sasaran(PeriodeKesmas $p, array $filters): array
    {
        $r = $this->dariSasaran($p, $filters)->selectRaw(
            'SUM(umur BETWEEN 0 AND 11) as bayi, SUM(umur BETWEEN 6 AND 11) as bayi_6_11, '
            . 'SUM(umur BETWEEN 12 AND 23) as baduta, SUM(umur BETWEEN 12 AND 59) as anak_balita, '
            . 'SUM(umur BETWEEN 0 AND 59) as balita, SUM(umur BETWEEN 24 AND 59) as balita_24_59, '
            . 'SUM(umur BETWEEN 60 AND 72) as prasekolah, SUM(umur BETWEEN 0 AND 72) as semua, '
            . 'SUM(umur BETWEEN 0 AND 11) as tahun_0, SUM(umur BETWEEN 12 AND 23) as tahun_1, '
            . 'SUM(umur BETWEEN 24 AND 35) as tahun_2, SUM(umur BETWEEN 36 AND 83) as tahun_3_6'
        )->first();

        return array_map('intval', (array) $r);
    }

    /** Kartu K1 (balita 0–59), K2 (bayi 0–11), K3 (anak balita 12–59) — §3. */
    public function spmKohort(PeriodeKesmas $p, array $filters): array
    {
        $t8 = $p->syarat(8);
        $t2 = $p->syarat(2);

        $r = $this->dariSasaran($p, $filters)
            ->leftJoinSub($this->kunjunganSub($p), 'k', 'k.id_anak', '=', 's.id')
            ->selectRaw(
                'SUM(umur BETWEEN 0 AND 11) as bayi, '
                . 'SUM(umur BETWEEN 0 AND 11 AND COALESCE(n_timbang,0) >= ?) as bayi_timbang, '
                . 'SUM(umur BETWEEN 0 AND 11 AND COALESCE(n_ddtka,0) >= ?) as bayi_ddtka, '
                . 'SUM(umur BETWEEN 6 AND 11) as bayi_6_11, '
                . 'SUM(umur BETWEEN 6 AND 11 AND COALESCE(n_vita,0) >= 1) as bayi_vita, '
                . 'SUM(umur BETWEEN 0 AND 11 AND COALESCE(n_lk,0) >= 1) as bayi_lk, '
                . 'SUM(umur BETWEEN 0 AND 11 AND COALESCE(n_timbang,0) >= ? AND COALESCE(n_ddtka,0) >= ? '
                . '    AND (umur < 6 OR COALESCE(n_vita,0) >= 1) AND COALESCE(n_lk,0) >= 1) as bayi_lengkap, '
                . 'SUM(umur BETWEEN 12 AND 59) as anak_balita, '
                . 'SUM(umur BETWEEN 12 AND 59 AND COALESCE(n_timbang,0) >= ?) as ab_timbang, '
                . 'SUM(umur BETWEEN 12 AND 59 AND COALESCE(n_ddtka,0) >= ?) as ab_ddtka, '
                . 'SUM(umur BETWEEN 12 AND 59 AND COALESCE(n_vita,0) >= ?) as ab_vita, '
                . 'SUM(umur BETWEEN 12 AND 59 AND COALESCE(n_timbang,0) >= ? AND COALESCE(n_ddtka,0) >= ? '
                . '    AND COALESCE(n_vita,0) >= ?) as ab_lengkap',
                [$t8, $t2, $t8, $t2, $t8, $t2, $t2, $t8, $t2, $t2]
            )->first();
        $r = array_map('intval', (array) $r);

        $sub = fn (int $n, int $sasaran) => ['n' => $n, 'sasaran' => $sasaran, 'persen' => self::persen($n, $sasaran)];
        $balitaSasaran = $r['bayi'] + $r['anak_balita'];
        $balitaLengkap = $r['bayi_lengkap'] + $r['ab_lengkap'];

        return [
            'syarat' => ['timbang' => $t8, 'ddtka' => $t2, 'vita' => $t2],
            'balita' => [
                'sasaran' => $balitaSasaran,
                'lengkap' => $balitaLengkap,
                'persen'  => self::persen($balitaLengkap, $balitaSasaran),
            ],
            'bayi' => [
                'sasaran' => $r['bayi'],
                'lengkap' => $r['bayi_lengkap'],
                'persen'  => self::persen($r['bayi_lengkap'], $r['bayi']),
                'sisa'    => $r['bayi'] - $r['bayi_lengkap'],
                'sub'     => [
                    'timbang' => $sub($r['bayi_timbang'], $r['bayi']),
                    'ddtka'   => $sub($r['bayi_ddtka'], $r['bayi']),
                    'vita'    => $sub($r['bayi_vita'], $r['bayi_6_11']),
                    'lk'      => $sub($r['bayi_lk'], $r['bayi']),
                ],
            ],
            'anak_balita' => [
                'sasaran' => $r['anak_balita'],
                'lengkap' => $r['ab_lengkap'],
                'persen'  => self::persen($r['ab_lengkap'], $r['anak_balita']),
                'gap'     => $r['anak_balita'] - $r['ab_lengkap'],
                'sub'     => [
                    'timbang' => $sub($r['ab_timbang'], $r['anak_balita']),
                    'ddtka'   => $sub($r['ab_ddtka'], $r['anak_balita']),
                    'vita'    => $sub($r['ab_vita'], $r['anak_balita']),
                ],
            ],
        ];
    }
}
```

- [ ] **Step 4: Jalankan, pastikan lulus**

Run: `php artisan test --filter=KesmasDashboardServiceTest`
Expected: PASS (11 tests). Bila MySQL mengeluh `Unknown column 'umur'` di outer query: pastikan `SUM(umur …)` dipakai di query yang `fromSub(..., 's')`, bukan di subquery-nya.

- [ ] **Step 5: Commit**

```bash
git add app/Services/KesmasDashboardService.php tests/Feature/Kesmas/KesmasDashboardServiceTest.php
git commit -m "feat(kesmas): service dasbor — sasaran per umur & kartu SPM prorata"
```

---
### Task 4: Service — Pemantauan T&K (K4), SDIDTK, CKG

**Files:**
- Modify: `app/Services/KesmasDashboardService.php` (tambah 4 method)
- Test: `tests/Feature/Kesmas/KesmasDashboardServiceTest.php` (tambah test)

**Interfaces:**
- Consumes: `sasaranSub()`, `dariSasaran()`, `kunjunganSub()`, `persen()`, `KELOMPOK` (Task 3).
- Produces:
  - `pemantauanTk(PeriodeKesmas, array): array{sasaran,lengkap,perhatian: int, persen: ?float}`.
  - `sdidtk(PeriodeKesmas, array): array{kelompok: array<string, array{label,domain: string, min,max,sasaran,realisasi: int, persen: ?float}>, total: array{sasaran,realisasi: int, persen: ?float}, fokus: ?array{kelompok,label: string, min,max: int, gap: float}}`.
  - `ckg(PeriodeKesmas, array): array{kelompok: array<'t0'|'t1'|'t2'|'t3', array{label: string, sasaran,realisasi: int, persen: ?float}>, gigi: array{terisi,sehat: int, persen_sehat: ?float}, rujuk_gigi: int}`.
  - privat `kunjunganTerakhirSub(PeriodeKesmas): Builder` (`id_anak, max_tgl`) dipakai Task 6.

- [ ] **Step 1: Tulis tes yang gagal** — tambahkan ke `KesmasDashboardServiceTest`:

```php
    // ── K4 Pemantauan lengkap T&K ──────────────────────────────────────────

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

    public function test_perlu_perhatian_dari_kunjungan_terakhir_dalam_periode(): void
    {
        $ntobT = $this->anak(20);
        $this->kunjungan($ntobT, '2025-03-15', ['ntob' => 'N']);
        $this->kunjungan($ntobT, '2025-06-15', ['ntob' => ' t ']); // terakhir: T (huruf kecil + spasi)

        $sembuh = $this->anak(20);
        $this->kunjungan($sembuh, '2025-03-15', ['ntob' => 'T']);
        $this->kunjungan($sembuh, '2025-06-15', ['ntob' => 'N']); // terakhir N → tidak

        $underweight = $this->anak(20);
        $this->kunjungan($underweight, '2025-06-15', ['zscore_bb_u' => -2.5]);

        $batas = $this->anak(20);
        $this->kunjungan($batas, '2025-06-15', ['zscore_bb_u' => -2.0]); // > -2.01 → normal

        $lama = $this->anak(20);
        $this->kunjungan($lama, '2024-06-15', ['ntob' => 'T']); // di luar periode

        $tk = $this->svc->pemantauanTk($this->tahun2025(), []);

        $this->assertSame(2, $tk['perhatian'], 'ntobT & underweight');
    }

    // ── SDIDTK ─────────────────────────────────────────────────────────────

    public function test_sdidtk_per_kelompok_umur_dengan_fokus_gap_terbesar(): void
    {
        $b1 = $this->anak(5);  $this->kunjungan($b1, '2025-03-01', ['ddtka' => 'Sesuai']);
        $b2 = $this->anak(9);  $this->kunjungan($b2, '2025-03-01'); // tanpa ddtka
        $d1 = $this->anak(15); $this->kunjungan($d1, '2025-04-01', ['ddtka' => 'Meragukan']);
        $this->kunjungan($d1, '2025-05-01', ['ddtka' => 'Sesuai']); // 2 kunjungan = tetap 1 anak
        $l1 = $this->anak(30);
        $l2 = $this->anak(40);
        $l3 = $this->anak(50); $this->kunjungan($l3, '2024-12-01', ['ddtka' => 'Sesuai']); // di luar periode
        $p1 = $this->anak(65); $this->kunjungan($p1, '2025-09-01', ['ddtka' => 'Sesuai']);

        $s = $this->svc->sdidtk($this->tahun2025(), []);

        $this->assertSame([2, 1, 50.0], [$s['kelompok']['bayi']['sasaran'], $s['kelompok']['bayi']['realisasi'], $s['kelompok']['bayi']['persen']]);
        $this->assertSame([1, 1, 100.0], [$s['kelompok']['baduta']['sasaran'], $s['kelompok']['baduta']['realisasi'], $s['kelompok']['baduta']['persen']]);
        $this->assertSame([3, 0, 0.0], [$s['kelompok']['balita']['sasaran'], $s['kelompok']['balita']['realisasi'], $s['kelompok']['balita']['persen']]);
        $this->assertSame([1, 1, 100.0], [$s['kelompok']['prasekolah']['sasaran'], $s['kelompok']['prasekolah']['realisasi'], $s['kelompok']['prasekolah']['persen']]);
        $this->assertSame('Kemandirian & KPSP', $s['kelompok']['baduta']['domain']);
        $this->assertSame(['sasaran' => 7, 'realisasi' => 3, 'persen' => 42.9], $s['total']);
        $this->assertSame('balita', $s['fokus']['kelompok']);
        $this->assertSame(100.0, $s['fokus']['gap']);
        $this->assertSame([24, 59], [$s['fokus']['min'], $s['fokus']['max']]);
    }

    public function test_sdidtk_tanpa_sasaran_fokus_null(): void
    {
        $s = $this->svc->sdidtk($this->tahun2025(), []);

        $this->assertNull($s['fokus']);
        $this->assertNull($s['total']['persen']);
    }

    // ── CKG ────────────────────────────────────────────────────────────────

    public function test_ckg_per_umur_tahun_dan_footer_gigi(): void
    {
        $t0 = $this->anak(3);  $this->kunjungan($t0, '2025-10-01', ['tgl_penanda_ckg' => '2025-10-01', 'pemeriksaan_gigi' => 'Sehat']);
        $t0b = $this->anak(10); // tanpa CKG
        $t1 = $this->anak(15); $this->kunjungan($t1, '2025-02-01', ['tgl_penanda_ckg' => '2024-12-20']); // CKG tahun lalu
        $t2 = $this->anak(30); $this->kunjungan($t2, '2025-05-01', ['tgl_penanda_ckg' => '2025-05-01', 'pemeriksaan_gigi' => 'Karies', 'rujukan' => 'Dokter gigi']);
        $this->kunjungan($t2, '2025-11-01', ['pemeriksaan_gigi' => 'Karies', 'rujukan' => 'Dokter gigi']); // anak yang sama → rujuk tetap 1
        $t3 = $this->anak(70); $this->kunjungan($t3, '2025-06-01', ['tgl_penanda_ckg' => '2025-06-01', 'rujukan' => 'Rumah sakit']);
        $t3b = $this->anak(80); $this->kunjungan($t3b, '2025-06-01');

        $c = $this->svc->ckg($this->tahun2025(), []);

        $this->assertSame([2, 1], [$c['kelompok']['t0']['sasaran'], $c['kelompok']['t0']['realisasi']]);
        $this->assertSame([1, 0], [$c['kelompok']['t1']['sasaran'], $c['kelompok']['t1']['realisasi']]);
        $this->assertSame([1, 1], [$c['kelompok']['t2']['sasaran'], $c['kelompok']['t2']['realisasi']]);
        $this->assertSame([2, 1], [$c['kelompok']['t3']['sasaran'], $c['kelompok']['t3']['realisasi']], '36–83 bln: anak 70 & 80');
        $this->assertSame('Usia 3–6 tahun', $c['kelompok']['t3']['label']);
        $this->assertSame(['terisi' => 3, 'sehat' => 1, 'persen_sehat' => 33.3], $c['gigi']);
        $this->assertSame(1, $c['rujuk_gigi']);
    }
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --filter="KesmasDashboardServiceTest::test_(pemantauan|perlu|sdidtk|ckg)"`
Expected: FAIL — `Call to undefined method … pemantauanTk()`.

- [ ] **Step 3: Tambahkan ke `KesmasDashboardService`** (setelah `spmKohort()`):

```php
    /** Subquery `m`: tanggal kunjungan terakhir per anak DI DALAM periode. */
    private function kunjunganTerakhirSub(PeriodeKesmas $p): Builder
    {
        return DB::table('data_anak')
            ->selectRaw('id_anak, MAX(tgl_kunjungan) as max_tgl')
            ->whereBetween('tgl_kunjungan', [$p->awal()->toDateString(), $p->akhir()->toDateString()])
            ->groupBy('id_anak');
    }

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
            ->joinSub($this->kunjunganTerakhirSub($p), 'm', 'm.id_anak', '=', 's.id')
            ->join('data_anak as da', function ($j) {
                $j->on('da.id_anak', '=', 'm.id_anak')->on('da.tgl_kunjungan', '=', 'm.max_tgl');
            })
            ->where('s.umur', '<=', 72)
            ->where(function ($w) {
                $w->whereRaw("UPPER(TRIM(da.ntob)) = 'T'")->orWhere('da.zscore_bb_u', '<=', -2.01);
            })
            ->distinct()->count('s.id');

        return ['sasaran' => $sasaran, 'lengkap' => $lengkap, 'persen' => self::persen($lengkap, $sasaran), 'perhatian' => $perhatian];
    }

    /** Cakupan SDIDTK per kelompok umur: anak dengan ≥1 kunjungan ber-ddtka dalam periode. */
    public function sdidtk(PeriodeKesmas $p, array $filters): array
    {
        $ddtka = DB::table('data_anak')->select('id_anak')->distinct()
            ->whereBetween('tgl_kunjungan', [$p->awal()->toDateString(), $p->akhir()->toDateString()])
            ->whereNotNull('ddtka')->whereRaw("TRIM(ddtka) <> ''");

        $sel = [];
        foreach (self::KELOMPOK as $kode => [$min, $max]) {
            $sel[] = "SUM(umur BETWEEN {$min} AND {$max}) as {$kode}_sasaran";
            $sel[] = "SUM(umur BETWEEN {$min} AND {$max} AND d.id_anak IS NOT NULL) as {$kode}_ya";
        }
        $r = (array) $this->dariSasaran($p, $filters)
            ->leftJoinSub($ddtka, 'd', 'd.id_anak', '=', 's.id')
            ->selectRaw(implode(', ', $sel))->first();

        $kelompok = [];
        $totalSasaran = 0;
        $totalYa = 0;
        $fokus = null;
        foreach (self::KELOMPOK as $kode => [$min, $max, $label, $domain]) {
            $sasaran = (int) $r["{$kode}_sasaran"];
            $ya      = (int) $r["{$kode}_ya"];
            $persen  = self::persen($ya, $sasaran);
            $kelompok[$kode] = [
                'label' => $label, 'domain' => $domain, 'min' => $min, 'max' => $max,
                'sasaran' => $sasaran, 'realisasi' => $ya, 'persen' => $persen,
            ];
            $totalSasaran += $sasaran;
            $totalYa      += $ya;
            if ($persen !== null && ($fokus === null || (100 - $persen) > $fokus['gap'])) {
                $fokus = ['kelompok' => $kode, 'label' => $label, 'min' => $min, 'max' => $max, 'gap' => round(100 - $persen, 1)];
            }
        }

        return [
            'kelompok' => $kelompok,
            'total'    => ['sasaran' => $totalSasaran, 'realisasi' => $totalYa, 'persen' => self::persen($totalYa, $totalSasaran)],
            'fokus'    => $fokus,
        ];
    }

    /** CKG (Cek Kesehatan Gratis) per umur tahun + footer gigi/rujukan. */
    public function ckg(PeriodeKesmas $p, array $filters): array
    {
        [$a, $z] = [$p->awal()->toDateString(), $p->akhir()->toDateString()];

        $ckg = DB::table('data_anak')->select('id_anak')->distinct()->whereBetween('tgl_penanda_ckg', [$a, $z]);
        $r = (array) $this->dariSasaran($p, $filters)
            ->leftJoinSub($ckg, 'c', 'c.id_anak', '=', 's.id')
            ->selectRaw(
                'SUM(umur BETWEEN 0 AND 11) as t0, SUM(umur BETWEEN 0 AND 11 AND c.id_anak IS NOT NULL) as t0_ya, '
                . 'SUM(umur BETWEEN 12 AND 23) as t1, SUM(umur BETWEEN 12 AND 23 AND c.id_anak IS NOT NULL) as t1_ya, '
                . 'SUM(umur BETWEEN 24 AND 35) as t2, SUM(umur BETWEEN 24 AND 35 AND c.id_anak IS NOT NULL) as t2_ya, '
                . 'SUM(umur BETWEEN 36 AND 83) as t3, SUM(umur BETWEEN 36 AND 83 AND c.id_anak IS NOT NULL) as t3_ya'
            )->first();

        $gigi = DB::table('data_anak as da')
            ->joinSub($this->sasaranSub($p, $filters), 's', 's.id', '=', 'da.id_anak')
            ->where('s.umur', '<=', 83)
            ->whereBetween('da.tgl_kunjungan', [$a, $z])
            ->whereNotNull('da.pemeriksaan_gigi')
            ->selectRaw("COUNT(*) as terisi, SUM(da.pemeriksaan_gigi = 'Sehat') as sehat")
            ->first();

        $rujuk = DB::table('data_anak as da')
            ->joinSub($this->sasaranSub($p, $filters), 's', 's.id', '=', 'da.id_anak')
            ->where('s.umur', '<=', 83)
            ->whereBetween('da.tgl_kunjungan', [$a, $z])
            ->where('da.rujukan', 'Dokter gigi')
            ->distinct()->count('da.id_anak');

        $baris = fn (string $label, string $k) => [
            'label' => $label, 'sasaran' => (int) $r[$k], 'realisasi' => (int) $r["{$k}_ya"],
            'persen' => self::persen((int) $r["{$k}_ya"], (int) $r[$k]),
        ];

        return [
            'kelompok' => [
                't0' => $baris('Bayi baru lahir (< 1 tahun)', 't0'),
                't1' => $baris('Usia 1 tahun', 't1'),
                't2' => $baris('Usia 2 tahun', 't2'),
                't3' => $baris('Usia 3–6 tahun', 't3'),
            ],
            'gigi' => [
                'terisi' => (int) $gigi->terisi, 'sehat' => (int) $gigi->sehat,
                'persen_sehat' => self::persen((int) $gigi->sehat, (int) $gigi->terisi),
            ],
            'rujuk_gigi' => $rujuk,
        ];
    }
```

- [ ] **Step 4: Jalankan, pastikan lulus**

Run: `php artisan test --filter=KesmasDashboardServiceTest`
Expected: PASS (16 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/KesmasDashboardService.php tests/Feature/Kesmas/KesmasDashboardServiceTest.php
git commit -m "feat(kesmas): agregat pemantauan T&K, SDIDTK per usia, dan CKG"
```

---

### Task 5: Service — Layanan & Lingkungan

**Files:**
- Modify: `app/Services/KesmasDashboardService.php`
- Test: `tests/Feature/Kesmas/KesmasDashboardServiceTest.php`

**Interfaces:**
- Consumes: `config('kesmas.layanan')` (9 kunci: kn1, kn3, mtbm, mtbs, pkat, skrining_atresia_bilier, oralit_zinc, mbg, kelas_ibu_balita), `config('kesmas.skrining')`, `config('kesmas.hepatitis_b')`.
- Produces: `layananLingkungan(PeriodeKesmas, array): array{sasaran,bayi: int, layanan: array{baris: array<string, array{label,badge: string, ya,terisi,belum_diisi: int, persen: ?float}>, ada_data: bool}, skrining: array{baris: array<string, array{label: string, terisi,belum_diisi: int, sebaran: array<string, array{label: string, n: int, persen: ?float}>}>, ada_data: bool}, sanitasi: array{baris: array<string, array{label: string, ya,terisi,belum_diisi: int, persen: ?float, terbalik: bool}>, ada_data: bool}}`.

- [ ] **Step 1: Tulis tes yang gagal** — tambahkan ke `KesmasDashboardServiceTest`:

```php
    // ── Layanan & Lingkungan ───────────────────────────────────────────────

    public function test_layanan_per_kunjungan_pembagi_hanya_anak_yang_terisi(): void
    {
        $ya = $this->anak(20);
        $this->kunjungan($ya, '2025-02-01', ['kn1' => 0, 'mbg' => 1]);
        $this->kunjungan($ya, '2025-05-01', ['kn1' => 1]);            // pernah 1 → ya
        $tidak = $this->anak(20);
        $this->kunjungan($tidak, '2025-02-01', ['kn1' => 0]);          // terisi, tidak
        $kosong = $this->anak(20);
        $this->kunjungan($kosong, '2025-02-01');                       // kn1 NULL → belum diisi
        $tanpaKunjungan = $this->anak(20);
        $lama = $this->anak(20);
        $this->kunjungan($lama, '2024-02-01', ['kn1' => 1]);           // di luar periode → belum diisi

        $l = $this->svc->layananLingkungan($this->tahun2025(), []);

        $this->assertSame(5, $l['sasaran']);
        $kn1 = $l['layanan']['baris']['kn1'];
        $this->assertSame(['ya' => 1, 'terisi' => 2, 'persen' => 50.0, 'belum_diisi' => 3], array_intersect_key($kn1, array_flip(['ya', 'terisi', 'persen', 'belum_diisi'])));
        $this->assertSame('KN1', $kn1['badge']);
        $this->assertSame(['ya' => 1, 'terisi' => 1, 'belum_diisi' => 4], array_intersect_key($l['layanan']['baris']['mbg'], array_flip(['ya', 'terisi', 'belum_diisi'])));
        $this->assertNull($l['layanan']['baris']['pkat']['persen']);
        $this->assertTrue($l['layanan']['ada_data']);
        $this->assertCount(9, $l['layanan']['baris']);
    }

    public function test_skrining_neonatal_pada_bayi_dan_sanitasi_pada_0_72(): void
    {
        $this->anak(3, ['skrining_shk' => 'normal', 'pemeriksaan_hepatitis_b' => 'reaktif', 'air_bersih' => 1, 'jamban_sehat' => 0, 'merokok_keluarga' => 1]);
        $this->anak(9, ['skrining_shk' => 'tidak_normal', 'air_bersih' => 1]);
        $this->anak(11, ['skrining_shk' => 'belum']);
        $this->anak(30, ['skrining_shk' => 'normal', 'merokok_keluarga' => 0]); // bukan bayi → skrining tak dihitung, sanitasi ya
        $this->anak(5);                                                          // semua NULL
        $this->anak(80, ['air_bersih' => 1]);                                    // > 72 bln → tidak ikut sanitasi

        $l = $this->svc->layananLingkungan($this->tahun2025(), []);

        $this->assertSame(4, $l['bayi']);
        $shk = $l['skrining']['baris']['skrining_shk'];
        $this->assertSame(3, $shk['terisi']);
        $this->assertSame(1, $shk['belum_diisi']);
        $this->assertSame(1, $shk['sebaran']['normal']['n']);
        $this->assertSame(1, $shk['sebaran']['tidak_normal']['n']);
        $this->assertSame(1, $shk['sebaran']['belum']['n']);
        $this->assertSame(33.3, $shk['sebaran']['normal']['persen']);
        $this->assertSame('Tidak normal', $shk['sebaran']['tidak_normal']['label']);
        $this->assertSame(1, $l['skrining']['baris']['pemeriksaan_hepatitis_b']['sebaran']['reaktif']['n']);
        $this->assertSame(0, $l['skrining']['baris']['skrining_g6pd']['terisi']);
        $this->assertTrue($l['skrining']['ada_data']);

        $this->assertSame(5, $l['sasaran'], '0–72 bln');
        $air = $l['sanitasi']['baris']['air_bersih'];
        $this->assertSame(['ya' => 2, 'terisi' => 2, 'persen' => 100.0, 'belum_diisi' => 3], array_intersect_key($air, array_flip(['ya', 'terisi', 'persen', 'belum_diisi'])));
        $rokok = $l['sanitasi']['baris']['merokok_keluarga'];
        $this->assertSame([1, 2, 50.0, true], [$rokok['ya'], $rokok['terisi'], $rokok['persen'], $rokok['terbalik']]);
        $this->assertSame(0, $l['sanitasi']['baris']['jamban_sehat']['ya']);
        $this->assertSame(1, $l['sanitasi']['baris']['jamban_sehat']['terisi']);
    }

    public function test_layanan_lingkungan_tanpa_data_ada_data_false(): void
    {
        $this->anak(20);
        $this->kunjungan($this->anak(5), '2025-03-01');

        $l = $this->svc->layananLingkungan($this->tahun2025(), []);

        $this->assertFalse($l['layanan']['ada_data']);
        $this->assertFalse($l['skrining']['ada_data']);
        $this->assertFalse($l['sanitasi']['ada_data']);
        $this->assertSame(2, $l['sanitasi']['baris']['air_bersih']['belum_diisi']);
    }
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --filter="KesmasDashboardServiceTest::test_(layanan|skrining)"`
Expected: FAIL — `Call to undefined method … layananLingkungan()`.

- [ ] **Step 3: Tambahkan ke `KesmasDashboardService`**:

```php
    /**
     * Seksi "Layanan & Lingkungan" (§3): pembagi = anak yang datanya TERISI (IS NOT NULL);
     * NULL berarti belum ditanya, bukan "tidak" — jumlah belum_diisi selalu ikut dikembalikan.
     */
    public function layananLingkungan(PeriodeKesmas $p, array $filters): array
    {
        [$a, $z] = [$p->awal()->toDateString(), $p->akhir()->toDateString()];

        // 1) Layanan per kunjungan: per anak MAX(kolom = 1) → pernah ya; MAX(kolom IS NOT NULL) → terisi.
        $layananDef = config('kesmas.layanan');
        $kolom = array_keys($layananDef);
        $perAnak = DB::table('data_anak')
            ->selectRaw('id_anak, ' . implode(', ', array_map(
                fn ($k) => "MAX({$k} = 1) as {$k}_ya, MAX({$k} IS NOT NULL) as {$k}_isi", $kolom
            )))
            ->whereBetween('tgl_kunjungan', [$a, $z])
            ->groupBy('id_anak');
        $r = (array) $this->dariSasaran($p, $filters)
            ->leftJoinSub($perAnak, 'l', 'l.id_anak', '=', 's.id')
            ->where('s.umur', '<=', 72)
            ->selectRaw('COUNT(*) as sasaran, ' . implode(', ', array_map(
                fn ($k) => "SUM(COALESCE({$k}_ya,0)) as {$k}_ya, SUM(COALESCE({$k}_isi,0)) as {$k}_isi", $kolom
            )))
            ->first();
        $sasaran = (int) $r['sasaran'];
        $layanan = [];
        $adaLayanan = false;
        foreach ($layananDef as $k => $def) {
            $ya  = (int) $r["{$k}_ya"];
            $isi = (int) $r["{$k}_isi"];
            $adaLayanan = $adaLayanan || $isi > 0;
            $layanan[$k] = [
                'label' => $def['label'], 'badge' => $def['badge'],
                'ya' => $ya, 'terisi' => $isi, 'persen' => self::persen($ya, $isi), 'belum_diisi' => $sasaran - $isi,
            ];
        }

        // 2) Skrining neonatal — bayi 0–11 bulan (kolom enum di tabel anak).
        $skriningDef = [
            'skrining_shk'            => ['SHK (hipotiroid kongenital)', 'skrining'],
            'skrining_shak'           => ['SHAK (hiperplasia adrenal)', 'skrining'],
            'skrining_g6pd'           => ['G6PD', 'skrining'],
            'pemeriksaan_hepatitis_b' => ['Hepatitis B', 'hepatitis_b'],
        ];
        $sel = ['COUNT(*) as bayi'];
        foreach ($skriningDef as $k => [, $opsi]) {
            $sel[] = "SUM({$k} IS NOT NULL) as {$k}_isi";
            foreach (array_keys(config("kesmas.{$opsi}")) as $nilai) {
                $sel[] = "SUM({$k} = '{$nilai}') as {$k}_{$nilai}";
            }
        }
        $rs = (array) $this->dariSasaran($p, $filters)->whereBetween('s.umur', [0, 11])->selectRaw(implode(', ', $sel))->first();
        $bayi = (int) $rs['bayi'];
        $skrining = [];
        $adaSkrining = false;
        foreach ($skriningDef as $k => [$label, $opsi]) {
            $isi = (int) $rs["{$k}_isi"];
            $adaSkrining = $adaSkrining || $isi > 0;
            $sebaran = [];
            foreach (config("kesmas.{$opsi}") as $nilai => $labelNilai) {
                $n = (int) $rs["{$k}_{$nilai}"];
                $sebaran[$nilai] = ['label' => $labelNilai, 'n' => $n, 'persen' => self::persen($n, $isi)];
            }
            $skrining[$k] = ['label' => $label, 'terisi' => $isi, 'sebaran' => $sebaran, 'belum_diisi' => $bayi - $isi];
        }

        // 3) Sanitasi rumah — 0–72 bulan. 'terbalik' = nilai tinggi berarti buruk (warna dibalik di view).
        $sanitasiDef = [
            'air_bersih'       => ['Akses air bersih', false],
            'jamban_sehat'     => ['Jamban sehat', false],
            'merokok_keluarga' => ['Ada anggota keluarga merokok', true],
        ];
        $sel = ['COUNT(*) as sasaran'];
        foreach (array_keys($sanitasiDef) as $k) {
            $sel[] = "SUM({$k} IS NOT NULL) as {$k}_isi";
            $sel[] = "SUM({$k} = 1) as {$k}_ya";
        }
        $rn = (array) $this->dariSasaran($p, $filters)->where('s.umur', '<=', 72)->selectRaw(implode(', ', $sel))->first();
        $sanitasi = [];
        $adaSanitasi = false;
        foreach ($sanitasiDef as $k => [$label, $terbalik]) {
            $isi = (int) $rn["{$k}_isi"];
            $ya  = (int) $rn["{$k}_ya"];
            $adaSanitasi = $adaSanitasi || $isi > 0;
            $sanitasi[$k] = [
                'label' => $label, 'ya' => $ya, 'terisi' => $isi, 'persen' => self::persen($ya, $isi),
                'belum_diisi' => (int) $rn['sasaran'] - $isi, 'terbalik' => $terbalik,
            ];
        }

        return [
            'sasaran'  => $sasaran,
            'bayi'     => $bayi,
            'layanan'  => ['baris' => $layanan, 'ada_data' => $adaLayanan],
            'skrining' => ['baris' => $skrining, 'ada_data' => $adaSkrining],
            'sanitasi' => ['baris' => $sanitasi, 'ada_data' => $adaSanitasi],
        ];
    }
```

- [ ] **Step 4: Jalankan, pastikan lulus**

Run: `php artisan test --filter=KesmasDashboardServiceTest`
Expected: PASS (19 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/KesmasDashboardService.php tests/Feature/Kesmas/KesmasDashboardServiceTest.php
git commit -m "feat(kesmas): agregat layanan per kunjungan, skrining neonatal, dan sanitasi"
```

---
### Task 6: Service — registri per anak

**Files:**
- Modify: `app/Services/KesmasDashboardService.php`
- Test: `tests/Feature/Kesmas/KesmasRegistriTest.php` (bagian service; endpoint HTTP ditambah di Task 11)

**Interfaces:**
- Consumes: `kunjunganTerakhirSub()` (Task 4); `ImunisasiStatusService::isIdlLengkap/isIblLengkap(Anak)`; `StatusGiziService::enumEppgbm(?float, ?float, ?float): array{bb_u,tb_u,bb_tb: ?string}`; `Anak->hashid`; route `admin.showAnak`.
- Produces:
  - Konstanta `USIA = ['semua'=>[0,72],'bayi'=>[0,11],'baduta'=>[12,23],'balita'=>[24,59],'prasekolah'=>[60,72]]`, `STATUS_GIZI = ['semua','normal','stunted','underweight','wasted','perhatian']`.
  - `registri(PeriodeKesmas $p, array $filters, string $usia = 'semua', string $q = '', string $statusGizi = 'semua', int $page = 1, int $perPage = 20): array{data: list<array>, total, page, last_page, per_page: int}`; tiap baris: `no, id, nama, nik, jk ('L'|'P'), umur_bln, tgl_lahir, nama_ibu, nama_ayah, kelurahan, rt, posyandu, kunjungan (null | {tgl, bb, tb, lk, bb_tidak_naik: bool, gizi: null|{kode,label,tone}}), idl, ibl ('lengkap'|'belum'|'belum_usia'), catatan, url_detail`.
  - `KesmasDashboardService::kategoriGizi(array $z): ?array{kode,label,tone: string}` (statis; tone `bad|warn|ok`).

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php
// tests/Feature/Kesmas/KesmasRegistriTest.php

namespace Tests\Feature\Kesmas;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Posyandu;
use App\Models\Rt;
use App\Models\User;
use App\Services\ImunisasiStatusService;
use App\Services\KesmasDashboardService;
use App\Support\PeriodeKesmas;
use App\Support\WilkerPuskesmas;
use Carbon\Carbon;
use Database\Seeders\JenisVaksinSeeder;
use Database\Seeders\KelompokVaksinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Registri longitudinal dasbor Kesmas (spec §3.1): service + endpoint JSON. */
class KesmasRegistriTest extends TestCase
{
    use RefreshDatabase;

    private KesmasDashboardService $svc;
    private User $admin;
    private Kecamatan $kec;
    private Kelurahan $kel;
    private Rt $rt;
    private Posyandu $posyandu;

    protected function setUp(): void
    {
        parent::setUp();
        ImunisasiStatusService::flushCache();
        WilkerPuskesmas::flushCache();
        $this->seed(JenisVaksinSeeder::class);
        $this->seed(KelompokVaksinSeeder::class);
        $this->svc = app(KesmasDashboardService::class);
        $this->admin = User::factory()->create(['type' => 1]);
        $this->kec = Kecamatan::create(['name' => 'Bontang Selatan']);
        $this->kel = Kelurahan::create(['name' => 'Berbas Tengah', 'id_kecamatan' => $this->kec->id]);
        $this->posyandu = Posyandu::create(['name' => 'Melati I']);
        $this->rt = Rt::create(['name' => '05', 'id_kelurahan' => $this->kel->id, 'id_posyandu' => $this->posyandu->id]);
    }

    private function periode(): PeriodeKesmas
    {
        return PeriodeKesmas::dari(2025, 'tahun');
    }

    private function anak(string $nama, int $umurBln, array $extra = []): Anak
    {
        static $n = 0;
        $n++;

        return Anak::create(array_merge([
            'nama' => $nama, 'nik' => str_pad((string) $n, 16, '7', STR_PAD_LEFT), 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => Carbon::create(2025, 12, 31)->subMonths($umurBln)->toDateString(),
            'status' => 1, 'no' => '1', 'sumber' => 'manual', 'nama_ibu' => 'Ibu ' . $nama, 'nama_ayah' => 'Ayah ' . $nama,
            'id_kec' => $this->kec->id, 'id_kel' => $this->kel->id, 'id_rt' => $this->rt->id, 'id_posyandu' => $this->posyandu->id,
        ], $extra));
    }

    private function kunjungan(Anak $anak, string $tgl, array $extra = []): DataAnak
    {
        return DataAnak::create(array_merge([
            'id_anak' => $anak->id, 'tgl_kunjungan' => $tgl, 'bln' => 1, 'posisi' => 'L',
            'tb' => 80, 'bb' => 10, 'lla' => 14, 'lk' => 46, 'id_user' => $this->admin->id, 'sumber' => 'manual',
        ], $extra));
    }

    public function test_baris_memuat_identitas_wilayah_kunjungan_terakhir_dan_status_imunisasi(): void
    {
        $a = $this->anak('Budi', 30);
        $this->kunjungan($a, '2025-03-10', ['bb' => 9, 'zscore_pb_u' => -2.5]);
        $this->kunjungan($a, '2025-09-10', ['bb' => 10.2, 'tb' => 81, 'lk' => 46.5, 'ntob' => 'N',
            'zscore_bb_u' => -1.0, 'zscore_pb_u' => -2.2, 'zscore_bb_pb' => 0.3, 'catatan_pengukuran' => 'Nafsu makan baik']);
        $this->kunjungan($a, '2026-01-05', ['bb' => 99]); // di luar periode → bukan "terakhir"

        $r = $this->svc->registri($this->periode(), []);

        $this->assertSame(1, $r['total']);
        $b = $r['data'][0];
        $this->assertSame(1, $b['no']);
        $this->assertSame('Budi', $b['nama']);
        $this->assertSame('L', $b['jk']);
        $this->assertSame(30, $b['umur_bln']);
        $this->assertSame('Ibu Budi', $b['nama_ibu']);
        $this->assertSame(['Berbas Tengah', '05', 'Melati I'], [$b['kelurahan'], $b['rt'], $b['posyandu']]);
        $this->assertSame('2025-09-10', $b['kunjungan']['tgl']);
        $this->assertEquals(10.2, $b['kunjungan']['bb']);
        $this->assertSame('stunted', $b['kunjungan']['gizi']['kode']);
        $this->assertSame('Pendek', $b['kunjungan']['gizi']['label']);
        $this->assertFalse($b['kunjungan']['bb_tidak_naik']);
        $this->assertSame('Nafsu makan baik', $b['catatan']);
        $this->assertSame('belum', $b['idl'], '30 bln tanpa vaksin → IDL belum');
        $this->assertSame('belum', $b['ibl']);
        $this->assertSame(route('admin.showAnak', $a->hashid), $b['url_detail']);
    }

    public function test_belum_masuk_usia_dan_anak_tanpa_kunjungan(): void
    {
        $this->anak('Bayi', 5);   // <12 → IDL belum_usia, <24 → IBL belum_usia
        $this->anak('Baduta', 15);

        $r = $this->svc->registri($this->periode(), []);

        $bayi = collect($r['data'])->firstWhere('nama', 'Bayi');
        $this->assertSame(['belum_usia', 'belum_usia'], [$bayi['idl'], $bayi['ibl']]);
        $this->assertNull($bayi['kunjungan']);
        $baduta = collect($r['data'])->firstWhere('nama', 'Baduta');
        $this->assertSame(['belum', 'belum_usia'], [$baduta['idl'], $baduta['ibl']]);
    }

    public function test_paginasi_20_per_halaman_urut_nama(): void
    {
        for ($i = 1; $i <= 23; $i++) {
            $this->anak(sprintf('Anak %02d', $i), 20);
        }

        $h1 = $this->svc->registri($this->periode(), [], 'semua', '', 'semua', 1);
        $h2 = $this->svc->registri($this->periode(), [], 'semua', '', 'semua', 2);

        $this->assertSame([23, 20, 2, 1], [$h1['total'], $h1['per_page'], $h1['last_page'], $h1['page']]);
        $this->assertCount(20, $h1['data']);
        $this->assertSame('Anak 01', $h1['data'][0]['nama']);
        $this->assertCount(3, $h2['data']);
        $this->assertSame(21, $h2['data'][0]['no']);
        $this->assertSame('Anak 21', $h2['data'][0]['nama']);
    }

    public function test_cari_nama_nik_dan_orang_tua(): void
    {
        $this->anak('Citra Dewi', 20, ['nik' => '6474012345678901', 'nama_ibu' => 'Ratna']);
        $this->anak('Dani', 20, ['nik' => '6474099999999999', 'nama_ayah' => 'Bambang Ratnadi']);
        $this->anak('Eka', 20, ['nik' => '6474088888888888']);

        $this->assertSame(1, $this->svc->registri($this->periode(), [], 'semua', 'citra')['total']);
        $this->assertSame(1, $this->svc->registri($this->periode(), [], 'semua', '2345678')['total']);
        $this->assertSame(2, $this->svc->registri($this->periode(), [], 'semua', 'Ratna')['total'], 'nama ibu & nama ayah');
        $this->assertSame(0, $this->svc->registri($this->periode(), [], 'semua', 'zzz')['total']);
    }

    public function test_filter_usia_dan_wilayah(): void
    {
        $this->anak('Bayi', 5);
        $this->anak('Balita', 30);
        $kelLain = Kelurahan::create(['name' => 'Tanjung Laut', 'id_kecamatan' => $this->kec->id]);
        $this->anak('Lain', 30, ['id_kel' => $kelLain->id, 'id_rt' => null, 'id_posyandu' => null]);
        $this->anak('Tua', 80);

        $this->assertSame(3, $this->svc->registri($this->periode(), [])['total'], '0–72 saja');
        $this->assertSame(1, $this->svc->registri($this->periode(), [], 'bayi')['total']);
        $this->assertSame(2, $this->svc->registri($this->periode(), [], 'balita')['total']);
        $this->assertSame(1, $this->svc->registri($this->periode(), ['id_kelurahan' => $kelLain->id], 'balita')['total']);
        $lain = $this->svc->registri($this->periode(), ['id_kelurahan' => $kelLain->id])['data'][0];
        $this->assertNull($lain['rt']);
        $this->assertNull($lain['posyandu']);
    }

    public function test_filter_status_gizi(): void
    {
        $stunted = $this->anak('Stunted', 30);
        $this->kunjungan($stunted, '2025-05-01', ['zscore_pb_u' => -2.3, 'zscore_bb_u' => -1, 'zscore_bb_pb' => 0]);
        $under = $this->anak('Underweight', 30);
        $this->kunjungan($under, '2025-05-01', ['zscore_pb_u' => -1, 'zscore_bb_u' => -2.2, 'zscore_bb_pb' => -1]);
        $wasted = $this->anak('Wasted', 30);
        $this->kunjungan($wasted, '2025-05-01', ['zscore_pb_u' => -1, 'zscore_bb_u' => -1, 'zscore_bb_pb' => -3.5]);
        $ntob = $this->anak('Tidak naik', 30);
        $this->kunjungan($ntob, '2025-05-01', ['zscore_pb_u' => 0, 'zscore_bb_u' => 0, 'zscore_bb_pb' => 0, 'ntob' => 'T']);
        $normal = $this->anak('Normal', 30);
        $this->kunjungan($normal, '2025-05-01', ['zscore_pb_u' => 0, 'zscore_bb_u' => 0, 'zscore_bb_pb' => 0, 'ntob' => 'N']);
        $this->anak('Tanpa kunjungan', 30);

        $nama = fn (string $status) => collect($this->svc->registri($this->periode(), [], 'semua', '', $status)['data'])->pluck('nama')->sort()->values()->all();

        $this->assertSame(['Stunted'], $nama('stunted'));
        $this->assertSame(['Underweight'], $nama('underweight'));
        $this->assertSame(['Wasted'], $nama('wasted'));
        $this->assertSame(['Tidak naik', 'Underweight'], $nama('perhatian'));
        $this->assertSame(['Normal'], $nama('normal'));
        $this->assertCount(6, $this->svc->registri($this->periode(), [])['data']);

        $w = collect($this->svc->registri($this->periode(), [])['data'])->firstWhere('nama', 'Wasted');
        $this->assertSame(['severely_wasted', 'Gizi buruk', 'bad'], array_values($w['kunjungan']['gizi']));
        $t = collect($this->svc->registri($this->periode(), [])['data'])->firstWhere('nama', 'Tidak naik');
        $this->assertTrue($t['kunjungan']['bb_tidak_naik']);
        $this->assertSame('normal', $t['kunjungan']['gizi']['kode']);
    }

    public function test_kategori_gizi_memilih_yang_terburuk(): void
    {
        $this->assertSame('wasted', KesmasDashboardService::kategoriGizi(['bb_u' => 'underweight', 'tb_u' => 'stunted', 'bb_tb' => 'wasted'])['kode']);
        $this->assertSame('underweight', KesmasDashboardService::kategoriGizi(['bb_u' => 'underweight', 'tb_u' => 'stunted', 'bb_tb' => 'normal'])['kode']);
        $this->assertSame('overweight', KesmasDashboardService::kategoriGizi(['bb_u' => 'normal', 'tb_u' => 'normal', 'bb_tb' => 'overweight'])['kode']);
        $this->assertSame(['normal', 'Gizi baik', 'ok'], array_values(KesmasDashboardService::kategoriGizi(['bb_u' => 'normal', 'tb_u' => 'tinggi', 'bb_tb' => 'normal'])));
        $this->assertNull(KesmasDashboardService::kategoriGizi(['bb_u' => null, 'tb_u' => null, 'bb_tb' => null]));
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --filter=KesmasRegistriTest`
Expected: FAIL — `Call to undefined method … registri()`.

- [ ] **Step 3: Tambahkan ke `KesmasDashboardService`**

Tambah import di atas file: `use App\Models\Anak;`, `use App\Models\Kelurahan;`, `use App\Models\Posyandu;`, `use App\Models\Rt;`, `use Illuminate\Support\Str;`. Lalu tambahkan konstanta & method:

```php
    /** Rentang umur (bulan) untuk chip usia — registri & penyorotan SDIDTK. */
    public const USIA = [
        'semua' => [0, 72], 'bayi' => [0, 11], 'baduta' => [12, 23], 'balita' => [24, 59], 'prasekolah' => [60, 72],
    ];

    public const STATUS_GIZI = ['semua', 'normal', 'stunted', 'underweight', 'wasted', 'perhatian'];

    /**
     * Badge gizi satu kata dari hasil StatusGiziService::enumEppgbm(): kategori
     * TERBURUK dengan urutan wasted > underweight > stunted > lebih > normal.
     *
     * @param  array{bb_u: ?string, tb_u: ?string, bb_tb: ?string}  $z
     * @return ?array{kode: string, label: string, tone: string}
     */
    public static function kategoriGizi(array $z): ?array
    {
        $urutan = [
            'severely_wasted'      => ['bb_tb', 'Gizi buruk', 'bad'],
            'wasted'               => ['bb_tb', 'Gizi kurang', 'bad'],
            'severely_underweight' => ['bb_u', 'BB sangat kurang', 'bad'],
            'underweight'          => ['bb_u', 'BB kurang', 'warn'],
            'severely_stunted'     => ['tb_u', 'Sangat pendek', 'bad'],
            'stunted'              => ['tb_u', 'Pendek', 'warn'],
            'obese'                => ['bb_tb', 'Obesitas', 'warn'],
            'overweight'           => ['bb_tb', 'Gizi lebih', 'warn'],
            'risiko_lebih'         => ['bb_tb', 'Risiko gizi lebih', 'warn'],
        ];
        foreach ($urutan as $kode => [$dimensi, $label, $tone]) {
            if (($z[$dimensi] ?? null) === $kode) {
                return ['kode' => $kode, 'label' => $label, 'tone' => $tone];
            }
        }
        foreach (['bb_tb', 'bb_u', 'tb_u'] as $dimensi) {
            if (($z[$dimensi] ?? null) !== null) {
                return ['kode' => 'normal', 'label' => 'Gizi baik', 'tone' => 'ok'];
            }
        }

        return null;
    }

    /**
     * Registri longitudinal (§3.1): 20 baris/halaman, urut nama. Sasaran = anak di
     * kelompok $usia (umur pada akhir periode). Model Anak dimuat hanya untuk ≤20 id.
     */
    public function registri(PeriodeKesmas $p, array $filters, string $usia = 'semua', string $q = '', string $statusGizi = 'semua', int $page = 1, int $perPage = 20): array
    {
        [$min, $max] = self::USIA[$usia] ?? self::USIA['semua'];
        $akhir = $p->akhir()->toDateString();

        // id kunjungan terakhir per anak dalam periode (dua kunjungan setanggal → id terbesar).
        $terakhir = DB::table('data_anak as d2')
            ->joinSub($this->kunjunganTerakhirSub($p), 'm', function ($j) {
                $j->on('m.id_anak', '=', 'd2.id_anak')->on('m.max_tgl', '=', 'd2.tgl_kunjungan');
            })
            ->selectRaw('d2.id_anak, MAX(d2.id) as id_kunjungan')
            ->groupBy('d2.id_anak');

        $base = DB::table('anak as a')
            ->leftJoinSub($terakhir, 't', 't.id_anak', '=', 'a.id')
            ->leftJoin('data_anak as da', 'da.id', '=', 't.id_kunjungan')
            ->whereNotNull('a.tgl_lahir')
            ->where('a.tgl_lahir', '<=', $akhir)
            ->whereRaw('TIMESTAMPDIFF(MONTH, a.tgl_lahir, ?) BETWEEN ? AND ?', [$akhir, $min, $max]);
        $this->applyWilayahFilters($base, $filters, 'a');
        if ($q !== '') {
            $like = '%' . $q . '%';
            $base->where(function ($w) use ($like) {
                $w->where('a.nama', 'like', $like)->orWhere('a.nik', 'like', $like)
                  ->orWhere('a.nama_ibu', 'like', $like)->orWhere('a.nama_ayah', 'like', $like);
            });
        }
        $this->applyStatusGizi($base, $statusGizi);

        $total = (clone $base)->count('a.id');
        $rows  = $base
            ->selectRaw(
                'a.id, a.nama, a.nik, a.jk, a.tgl_lahir, a.nama_ibu, a.nama_ayah, a.id_kel, a.id_rt, a.id_posyandu, '
                . 'TIMESTAMPDIFF(MONTH, a.tgl_lahir, ?) as umur, da.tgl_kunjungan, da.bb, da.tb, da.lk, da.ntob, '
                . 'da.zscore_bb_u, da.zscore_pb_u, da.zscore_bb_pb, da.catatan_pengukuran',
                [$akhir]
            )
            ->orderBy('a.nama')->orderBy('a.id')
            ->forPage($page, $perPage)
            ->get();

        $anakModels = Anak::whereIn('id', $rows->pluck('id'))->with('imunisasi')->get()->keyBy('id');
        $namaKel = Kelurahan::whereIn('id', $rows->pluck('id_kel')->filter()->unique())->pluck('name', 'id');
        $namaRt  = Rt::whereIn('id', $rows->pluck('id_rt')->filter()->unique())->pluck('name', 'id');
        $namaPos = Posyandu::whereIn('id', $rows->pluck('id_posyandu')->filter()->unique())->pluck('name', 'id');
        $imun = app(ImunisasiStatusService::class);
        $gizi = app(StatusGiziService::class);

        $data = [];
        foreach ($rows as $i => $r) {
            $anak = $anakModels[$r->id];
            $umur = (int) $r->umur;
            $kunjungan = null;
            if ($r->tgl_kunjungan !== null) {
                $z = $gizi->enumEppgbm(
                    $r->zscore_bb_u !== null ? (float) $r->zscore_bb_u : null,
                    $r->zscore_pb_u !== null ? (float) $r->zscore_pb_u : null,
                    $r->zscore_bb_pb !== null ? (float) $r->zscore_bb_pb : null,
                );
                $kunjungan = [
                    'tgl' => $r->tgl_kunjungan, 'bb' => $r->bb, 'tb' => $r->tb, 'lk' => $r->lk,
                    'bb_tidak_naik' => strtoupper(trim((string) $r->ntob)) === 'T',
                    'gizi' => self::kategoriGizi($z),
                ];
            }
            $data[] = [
                'no'         => ($page - 1) * $perPage + $i + 1,
                'id'         => $r->id,
                'nama'       => $r->nama,
                'nik'        => $r->nik,
                'jk'         => (int) $r->jk === 1 ? 'L' : 'P',
                'umur_bln'   => $umur,
                'tgl_lahir'  => $r->tgl_lahir,
                'nama_ibu'   => $r->nama_ibu,
                'nama_ayah'  => $r->nama_ayah,
                'kelurahan'  => $namaKel[$r->id_kel] ?? null,
                'rt'         => $namaRt[$r->id_rt] ?? null,
                'posyandu'   => $namaPos[$r->id_posyandu] ?? null,
                'kunjungan'  => $kunjungan,
                'idl'        => $umur < 12 ? 'belum_usia' : ($imun->isIdlLengkap($anak) ? 'lengkap' : 'belum'),
                'ibl'        => $umur < 24 ? 'belum_usia' : ($imun->isIblLengkap($anak) ? 'lengkap' : 'belum'),
                'catatan'    => $r->catatan_pengukuran !== null && trim($r->catatan_pengukuran) !== '' ? Str::limit(trim($r->catatan_pengukuran), 80) : null,
                'url_detail' => route('admin.showAnak', $anak->hashid),
            ];
        }

        return [
            'data'      => $data,
            'total'     => $total,
            'page'      => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
            'per_page'  => $perPage,
        ];
    }

    /**
     * Filter status gizi pada kunjungan terakhir (alias `da`). Ambang sama dengan
     * StatusGiziService::enumEppgbm: ≤ -2.01 = kurang; TB/U ≤ -6.01 = outlier (bukan stunted).
     */
    private function applyStatusGizi(Builder $q, string $status): void
    {
        $stunted     = fn ($w) => $w->where('da.zscore_pb_u', '>', -6.01)->where('da.zscore_pb_u', '<=', -2.01);
        $underweight = fn ($w) => $w->where('da.zscore_bb_u', '<=', -2.01);
        $wasted      = fn ($w) => $w->where('da.zscore_bb_pb', '<=', -2.01);
        $perhatian   = fn ($w) => $w->whereRaw("UPPER(TRIM(da.ntob)) = 'T'")->orWhere('da.zscore_bb_u', '<=', -2.01);

        match ($status) {
            'stunted'     => $q->where($stunted),
            'underweight' => $q->where($underweight),
            'wasted'      => $q->where($wasted),
            'perhatian'   => $q->where($perhatian),
            // normal = punya kunjungan dalam periode dan tidak masuk kategori mana pun; z-score NULL dianggap tidak bermasalah.
            'normal'      => $q->whereNotNull('da.id')
                ->where(fn ($w) => $w->whereNull('da.zscore_pb_u')->orWhere('da.zscore_pb_u', '<=', -6.01)->orWhere('da.zscore_pb_u', '>', -2.01))
                ->where(fn ($w) => $w->whereNull('da.zscore_bb_u')->orWhere('da.zscore_bb_u', '>', -2.01))
                ->where(fn ($w) => $w->whereNull('da.zscore_bb_pb')->orWhere('da.zscore_bb_pb', '>', -2.01))
                ->where(fn ($w) => $w->whereNull('da.ntob')->orWhereRaw("UPPER(TRIM(da.ntob)) <> 'T'")),
            default       => null,
        };
    }
```

Tambah juga `use App\Services\StatusGiziService;` tidak perlu (satu namespace) — cukup `ImunisasiStatusService` & `StatusGiziService` dipanggil langsung karena berada di `App\Services`.

- [ ] **Step 4: Jalankan, pastikan lulus**

Run: `php artisan test --filter=KesmasRegistriTest`
Expected: PASS (7 tests). Jika `test_baris_…` gagal di `gizi.kode`: `enumEppgbm` menerima urutan `(zBbU, zTbU, zBbTb)` — pastikan `zscore_pb_u` dipetakan ke argumen kedua.

- [ ] **Step 5: Commit**

```bash
git add app/Services/KesmasDashboardService.php tests/Feature/Kesmas/KesmasRegistriTest.php
git commit -m "feat(kesmas): registri longitudinal per anak — paginasi, cari, filter usia & status gizi"
```

---

### Task 7: CSS dasar dasbor ke `public/css/dasbor-base.css`

Memindahkan token & komponen `.im-*` dasar dari dasbor imunisasi ke berkas bersama, tanpa mengubah nama kelas; dasbor Kesmas (Task 8) memakainya.

**Files:**
- Create: `public/css/dasbor-base.css`
- Modify: `resources/views/admin/imunisasi/dashboard.blade.php` (baris 9–68 blok `<style>`)

**Interfaces:**
- Produces: kelas `.im-page` (token `--green`, `--green-d`, `--green-dk`, `--amber`, `--amber-bg`, `--red`, `--red-d`, `--red-bg`, `--ink`, `--muted`, `--faint`, `--line`, `--bg`, `--card`; font Barlow), `.im-num`, `.im-h`, `.im-filter`, `.im-btn`(`--primary|--ghost|--sm`), `.im-tabs`/`.im-tab`, `.im-cards`(`--2|--3|--5`), `.im-card`, `.im-card__lbl|__val|__sub|__foot|__link`, `.im-badge`(`--ok|--warn`).

- [ ] **Step 1: Salin blok CSS**

Buka `resources/views/admin/imunisasi/dashboard.blade.php`. Baris 10 adalah `@import url('https://fonts.googleapis.com/css2?family=Barlow…')`, baris 68 adalah `.im-badge--warn{ background:var(--red-bg); color:var(--red-d); }`. Buat `public/css/dasbor-base.css` berisi **persis** baris 10–68 (verifikasi: baris pertama berkas baru diawali `@import url(`, baris terakhir diawali `.im-badge--warn`). Tambahkan komentar di baris paling atas **setelah** `@import` (CSS mewajibkan `@import` paling awal):

```css
/* Token & komponen dasar dasbor SIRINDU (.im-*) — dipakai dasbor imunisasi & Kesmas.
   Aturan khusus halaman tetap inline di blade masing-masing. */
```

- [ ] **Step 2: Ganti blok di dasbor imunisasi**

Hapus baris 10–68 dari `dashboard.blade.php` (blok `<style>` tetap ada berisi aturan khusus halaman mulai `/* Day tabs …`). Tambahkan sebelum `@section('content')`:

```blade
@push('styles')
<link rel="stylesheet" href="{{ asset('css/dasbor-base.css') }}">
@endpush
```

- [ ] **Step 3: Verifikasi**

Run: `php artisan view:clear; php artisan test --filter=ImunisasiDashboardControllerTest`
Expected: PASS.

Lalu buka `http://sirindu.test/admin/imunisasi-dashboard` (login `dinkes@sirindu.go.id` / `Sirindu@2026`) dan cek: font Barlow, kartu bertepi 14 px, filter bar & tombol hijau tampil sama seperti sebelum perubahan; DevTools → Network: `dasbor-base.css` status 200.

- [ ] **Step 4: Commit**

```bash
git add public/css/dasbor-base.css resources/views/admin/imunisasi/dashboard.blade.php
git commit -m "refactor(dasbor): token & komponen .im-* dasar dipindah ke public/css/dasbor-base.css"
```

---
### Task 8: Controller, route, menu, dan kerangka halaman (kepala + filter)

**Files:**
- Create: `app/Http/Controllers/KesmasDashboardController.php`
- Create: `resources/views/admin/kesmas/dashboard.blade.php`, `resources/views/admin/kesmas/partials/_filter.blade.php`
- Modify: `routes/web.php` (setelah blok `export-kesmas`, ±baris 270, masih di grup `is_admin`)
- Modify: `resources/views/vendor/admin/layouts/partials/leftsidebar.blade.php` (baris 21, 36, 142, 153)
- Test: `tests/Feature/Kesmas/KesmasDashboardControllerTest.php`

**Interfaces:**
- Consumes: `KesmasDashboardService` (Task 3–6), `ImunisasiStatusService::getIdlCoverage/getIblCoverage/getAlasanTidakImunisasi`, `PeriodeKesmas`.
- Produces: route `admin.kesmas.dashboard` (`GET admin/kesmas-dashboard`), `admin.kesmas.registri` (`GET admin/kesmas-dashboard/api/registri`); view menerima `periode, filters, usia, sasaran, spm, tk, sdidtk, ckg, layanan, idl, ibl, alasan, kecamatanList, kelurahanList, posyanduList, puskesmasList, tahunList`. Halaman: `<div class="im-page km-page">`, `<header class="km-head">`, form `#kmFilter` dengan `#filterTahun #filterPeriode #filterKec #filterKel #filterRt #filterPos #filterPkm`, chip usia `button.km-chip[data-usia]`.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php
// tests/Feature/Kesmas/KesmasDashboardControllerTest.php

namespace Tests\Feature\Kesmas;

use App\Models\Anak;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\User;
use App\Services\ImunisasiStatusService;
use App\Support\WilkerPuskesmas;
use Database\Seeders\JenisVaksinSeeder;
use Database\Seeders\KelompokVaksinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Halaman dasbor Kesmas (spec 2026-09-21 §4–6): akses, validasi, filter, menu, isi kartu. */
class KesmasDashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $admin;
    private Kecamatan $kec;
    private Kelurahan $kel;

    protected function setUp(): void
    {
        parent::setUp();
        ImunisasiStatusService::flushCache();
        WilkerPuskesmas::flushCache();
        $this->seed(JenisVaksinSeeder::class);
        $this->seed(KelompokVaksinSeeder::class);
        $this->superAdmin = User::factory()->create(['type' => 0]);
        $this->admin = User::factory()->create(['type' => 1]);
        $this->kec = Kecamatan::create(['name' => 'Bontang Selatan']);
        $this->kel = Kelurahan::create(['name' => 'Berbas Tengah', 'id_kecamatan' => $this->kec->id]);
    }

    private function anak(string $nama, string $tglLahir, array $extra = []): Anak
    {
        static $n = 0;
        $n++;

        return Anak::create(array_merge([
            'nama' => $nama, 'nik' => str_pad((string) $n, 16, '5', STR_PAD_LEFT), 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => $tglLahir, 'status' => 1, 'no' => '1', 'sumber' => 'manual',
            'id_kec' => $this->kec->id, 'id_kel' => $this->kel->id,
        ], $extra));
    }

    /**
     * Potongan HTML satu blok: dari tag pembuka ber-`data-blok="$nama"` sampai komentar
     * penutup `<!-- /$nama -->` (setiap blok di view ditutup komentar itu). Assertion angka
     * dikurung di sini supaya tidak lolos palsu karena mencocoki blok lain.
     */
    protected function blok(string $html, string $nama): string
    {
        $re = '/<(?:section|article|div)\b[^>]*data-blok="' . preg_quote($nama, '/') . '"[^>]*>.*?<!-- \/' . preg_quote($nama, '/') . ' -->/s';
        $this->assertMatchesRegularExpression($re, $html, "Blok '{$nama}' tidak ditemukan");
        preg_match($re, $html, $m);

        return $m[0];
    }

    public function test_super_admin_dan_admin_bisa_membuka_faskes_surveilans_ditolak_tamu_dialihkan(): void
    {
        $this->actingAs($this->superAdmin)->get(route('admin.kesmas.dashboard'))->assertOk()->assertSee('Dashboard Kesmas');
        $this->actingAs($this->admin)->get(route('admin.kesmas.dashboard'))->assertOk();

        $faskes = User::factory()->create(['type' => 1, 'role' => 'surveilans_puskesmas']);
        $this->actingAs($faskes)->get(route('admin.kesmas.dashboard'))->assertForbidden();
        $this->actingAs($faskes)->getJson(route('admin.kesmas.registri'))->assertForbidden();

        $this->get(route('admin.kesmas.dashboard'))->assertRedirect(route('login'));
    }

    public function test_periode_tidak_valid_ditolak(): void
    {
        $this->actingAs($this->admin)->get(route('admin.kesmas.dashboard', ['periode' => 'bulan']))
            ->assertSessionHasErrors('periode');
        $this->actingAs($this->admin)->getJson(route('admin.kesmas.registri', ['periode' => 'bulan']))
            ->assertStatus(422)->assertJsonValidationErrors('periode');
        $this->actingAs($this->admin)->getJson(route('admin.kesmas.registri', ['tahun' => 1999]))
            ->assertStatus(422)->assertJsonValidationErrors('tahun');
        $this->actingAs($this->admin)->getJson(route('admin.kesmas.registri', ['id_kelurahan' => 99999]))
            ->assertStatus(422)->assertJsonValidationErrors('id_kelurahan');
    }

    public function test_kepala_menampilkan_label_periode_dan_wilayah_terpilih(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('admin.kesmas.dashboard', ['tahun' => 2025, 'periode' => 'tw3', 'id_kelurahan' => $this->kel->id]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('Triwulan III 2025 (1 Jul–30 Sep)', $html);
        $this->assertStringContainsString('Kel. Berbas Tengah', $html);
        $this->assertMatchesRegularExpression('/<option value="tw3"[^>]*selected/', $html);
        $this->assertMatchesRegularExpression('/<option value="' . $this->kel->id . '"[^>]*selected/', $html);
    }

    public function test_default_tahun_ini_dan_wilayah_kota(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.kesmas.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Tahun ' . now()->year, $html);
        $this->assertStringContainsString('Kota Bontang', $html);
    }

    public function test_tautan_export_membawa_filter_wilayah(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('admin.kesmas.dashboard', ['id_kecamatan' => $this->kec->id, 'id_kelurahan' => $this->kel->id]))
            ->getContent();

        $this->assertStringContainsString(
            e(route('admin.export.kesmas.index', ['id_kec' => $this->kec->id, 'id_kel' => $this->kel->id])),
            $html
        );
    }

    public function test_menu_sidebar_memuat_kesmas_untuk_kedua_peran(): void
    {
        foreach ([$this->superAdmin, $this->admin] as $user) {
            $html = $this->actingAs($user)->get(route('admin.kesmas.dashboard'))->getContent();
            $this->assertMatchesRegularExpression(
                '/<a href="' . preg_quote(route('admin.kesmas.dashboard'), '/') . '" class="active">Kesmas<\/a>/',
                $html,
                'Menu Dashboard → Kesmas harus ada dan aktif untuk user type ' . $user->type
            );
        }
    }

    public function test_chip_usia_dari_query_string_terpilih(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.kesmas.dashboard', ['usia' => 'baduta']))->getContent();

        $this->assertMatchesRegularExpression('/<button[^>]*data-usia="baduta"[^>]*aria-pressed="true"/', $html);
        $this->assertMatchesRegularExpression('/<button[^>]*data-usia="semua"[^>]*aria-pressed="false"/', $html);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --filter=KesmasDashboardControllerTest`
Expected: FAIL — `Route [admin.kesmas.dashboard] not defined`.

- [ ] **Step 3: Route**

Di `routes/web.php`, setelah grup `Route::prefix('export-kesmas')->group(...)` (masih di dalam `Route::middleware(['auth', 'is_admin'])->prefix('admin/')`):

```php
    // Dasbor Kesmas — spec docs/superpowers/specs/2026-09-21-dasbor-kesmas-design.md
    Route::prefix('kesmas-dashboard')->group(function () {
        Route::get('/', [App\Http\Controllers\KesmasDashboardController::class, 'index'])
             ->name('admin.kesmas.dashboard');
        Route::get('api/registri', [App\Http\Controllers\KesmasDashboardController::class, 'registri'])
             ->name('admin.kesmas.registri');
    });
```

- [ ] **Step 4: Controller**

```php
<?php
// app/Http/Controllers/KesmasDashboardController.php

namespace App\Http\Controllers;

use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Posyandu;
use App\Models\Puskesmas;
use App\Services\ImunisasiStatusService;
use App\Services\KesmasDashboardService;
use App\Support\PeriodeKesmas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Dasbor Kesmas — tumbuh kembang balita & posyandu.
 * Spec: docs/superpowers/specs/2026-09-21-dasbor-kesmas-design.md
 * Akses seperti Export Kesmas: super-admin & admin; pengguna modul surveilans ditolak.
 */
class KesmasDashboardController extends Controller
{
    private const KUNCI_WILAYAH = ['id_kecamatan', 'id_kelurahan', 'id_rt', 'id_posyandu', 'id_puskesmas'];

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (auth()->user()->isFaskesSurveilans()) {
                abort(403, 'Akses tidak diizinkan.');
            }

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        [$periode, $filters, $usia] = $this->parse($request);
        $svc  = app(KesmasDashboardService::class);
        $imun = app(ImunisasiStatusService::class);

        return view('admin.kesmas.dashboard', [
            'periode' => $periode,
            'filters' => $filters,
            'usia'    => $usia,
            'sasaran' => $svc->sasaran($periode, $filters),
            'spm'     => $svc->spmKohort($periode, $filters),
            'tk'      => $svc->pemantauanTk($periode, $filters),
            'sdidtk'  => $svc->sdidtk($periode, $filters),
            'ckg'     => $svc->ckg($periode, $filters),
            'layanan' => $svc->layananLingkungan($periode, $filters),
            'idl'     => $imun->getIdlCoverage($filters),
            'ibl'     => $imun->getIblCoverage($filters),
            'alasan'  => array_slice($imun->getAlasanTidakImunisasi($filters), 0, 4, true),
            'kecamatanList' => Kecamatan::orderBy('name')->get(),
            'kelurahanList' => Kelurahan::orderBy('name')->get(),
            'posyanduList'  => Posyandu::orderBy('name')->get(),
            'puskesmasList' => Puskesmas::orderBy('name')->get(),
            'tahunList'     => range(now()->year + 1, 2020),
        ]);
    }

    public function registri(Request $request): JsonResponse
    {
        [$periode, $filters, $usia] = $this->parse($request);
        $v = $request->validate([
            'q'           => 'nullable|string|max:100',
            'status_gizi' => ['nullable', Rule::in(KesmasDashboardService::STATUS_GIZI)],
            'page'        => 'nullable|integer|min:1',
        ]);

        return response()->json(app(KesmasDashboardService::class)->registri(
            $periode, $filters, $usia,
            trim((string) ($v['q'] ?? '')),
            $v['status_gizi'] ?? 'semua',
            (int) ($v['page'] ?? 1),
        ));
    }

    /** @return array{0: PeriodeKesmas, 1: array<string,int>, 2: string} */
    private function parse(Request $request): array
    {
        $v = $request->validate([
            'tahun'        => ['nullable', 'integer', 'min:2020', 'max:' . (now()->year + 1)],
            'periode'      => ['nullable', Rule::in(PeriodeKesmas::KODE)],
            'id_kecamatan' => 'nullable|integer|exists:kecamatan,id',
            'id_kelurahan' => 'nullable|integer|exists:kelurahan,id',
            'id_rt'        => 'nullable|integer|exists:rt,id',
            'id_posyandu'  => 'nullable|integer|exists:posyandu,id',
            'id_puskesmas' => 'nullable|integer|exists:puskesmas,id',
            'usia'         => ['nullable', Rule::in(array_keys(KesmasDashboardService::USIA))],
        ]);

        $periode = PeriodeKesmas::dari((int) ($v['tahun'] ?? now()->year), $v['periode'] ?? 'tahun');
        $filters = [];
        foreach (self::KUNCI_WILAYAH as $k) {
            if (!empty($v[$k])) {
                $filters[$k] = (int) $v[$k];
            }
        }

        return [$periode, $filters, $v['usia'] ?? 'semua'];
    }
}
```

- [ ] **Step 5: View — kerangka halaman**

`resources/views/admin/kesmas/dashboard.blade.php`:

```blade
@extends('admin::layouts.app')
@section('title') Dashboard Kesmas @endsection
@section('title-content') Dashboard Kesmas @endsection
@section('item') Kesmas @endsection
@section('item-active') Tumbuh Kembang Balita @endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/dasbor-base.css') }}">
<style>
/* ── Khusus dasbor Kesmas (token & komponen dasar .im-* ada di dasbor-base.css) ── */
.km-head{ display:flex; flex-wrap:wrap; align-items:flex-end; justify-content:space-between; gap:.75rem 1rem; margin-bottom:1.1rem; }
.km-head h1{ font-family:'Barlow Condensed','Barlow',sans-serif; font-weight:700; font-size:1.7rem; line-height:1.1; margin:0; color:var(--ink); }
.km-head .km-sub{ color:var(--muted); font-size:.85rem; margin-top:.3rem; }
.km-usia{ display:flex; flex-wrap:wrap; gap:.4rem; align-items:center; margin:-.6rem 0 1.4rem; }
.km-usia .km-lbl{ font-size:.68rem; font-weight:700; letter-spacing:.05em; text-transform:uppercase; color:var(--muted); margin-right:.3rem; }
.km-chip{ height:30px; padding:0 .8rem; border-radius:99px; border:1px solid var(--line); background:var(--card); font-family:inherit; font-weight:600; font-size:.78rem; color:var(--ink); cursor:pointer; }
.km-chip[aria-pressed="true"]{ background:var(--green-dk); border-color:var(--green-dk); color:#fff; }
.km-chip:focus-visible{ outline:2px solid oklch(0.60 0.15 145 / .5); outline-offset:2px; }
@media(max-width:700px){ .km-head h1{ font-size:1.35rem; } }
</style>
@endpush

@section('content')
@php
    $fmt  = fn ($n) => number_format((int) $n, 0, ',', '.');
    $pct  = fn ($p) => $p === null ? '—' : number_format($p, 1, ',', '.') . ' %';
    $tone = fn ($p) => $p === null ? 'na' : ($p >= 80 ? 'ok' : ($p >= 60 ? 'mid' : 'low'));

    $namaWilayah = 'Kota Bontang';
    if (!empty($filters['id_posyandu']))      $namaWilayah = 'Posyandu ' . optional($posyanduList->firstWhere('id', $filters['id_posyandu']))->name;
    elseif (!empty($filters['id_kelurahan'])) $namaWilayah = 'Kel. ' . optional($kelurahanList->firstWhere('id', $filters['id_kelurahan']))->name;
    elseif (!empty($filters['id_puskesmas'])) $namaWilayah = 'Puskesmas ' . optional($puskesmasList->firstWhere('id', $filters['id_puskesmas']))->name;
    elseif (!empty($filters['id_kecamatan'])) $namaWilayah = 'Kec. ' . optional($kecamatanList->firstWhere('id', $filters['id_kecamatan']))->name;

    $exportQs = array_filter([
        'id_kec' => $filters['id_kecamatan'] ?? null, 'id_kel' => $filters['id_kelurahan'] ?? null,
        'id_puskesmas' => $filters['id_puskesmas'] ?? null, 'id_posyandu' => $filters['id_posyandu'] ?? null,
    ]);
@endphp
<div class="im-page km-page">

    <header class="km-head">
        <div>
            <h1>Dashboard Kesmas — Tumbuh Kembang Balita</h1>
            <div class="km-sub">{{ $namaWilayah }} &middot; {{ $periode->label() }}</div>
        </div>
        <a href="{{ route('admin.export.kesmas.index', $exportQs) }}" class="im-btn im-btn--ghost">
            <span class="material-symbols-outlined" style="font-size:18px;">download</span>Export Data
        </a>
    </header>

    @include('admin.kesmas.partials._filter')

    <div class="km-usia" role="group" aria-label="Kelompok usia untuk registri">
        <span class="km-lbl">Kelompok usia</span>
        @foreach(['semua' => 'Semua (0–72 bln)', 'bayi' => 'Bayi (0–11)', 'baduta' => 'Baduta (12–23)', 'balita' => 'Balita (24–59)', 'prasekolah' => 'Prasekolah (60–72)'] as $kode => $label)
            <button type="button" class="km-chip" data-usia="{{ $kode }}" aria-pressed="{{ $usia === $kode ? 'true' : 'false' }}">{{ $label }}</button>
        @endforeach
    </div>

</div>
@endsection

@push('js')
<script>
(function () {
    var URL_KEL_BY_KEC = '{{ url("admin/get-kel-dasar-anak") }}';
    var URL_RT_BY_KEL  = '{{ url("admin/get-rt-by-kel-anak") }}';
    var SELECTED_RT    = '{{ $filters['id_rt'] ?? '' }}';
    var $kec = $('#filterKec'), $kel = $('#filterKel'), $rt = $('#filterRt');

    function fillSelect($sel, data, placeholder, selected) {
        $sel.empty().append($('<option>', { value: '', text: placeholder }));
        $.each(data, function (id, name) {
            $sel.append($('<option>', { value: id, text: name, selected: String(id) === String(selected) }));
        });
    }

    function loadRt(kelId, selected) {
        if (!kelId) { fillSelect($rt, {}, 'Semua RT'); return; }
        $.getJSON(URL_RT_BY_KEL + '/' + kelId, function (d) { fillSelect($rt, d, 'Semua RT', selected); });
    }

    // Validitas pilihan kelurahan dicek dari data-kec, BUKAN jQuery :hidden — <option> tak punya
    // box model saat select tertutup, jadi :hidden selalu true dan pilihan awal ke-reset.
    function filterKelOptionsByKec(kecId) {
        var currentVal = $kel.val();
        var stillValid = false;
        $kel.find('option[data-kec]').each(function () {
            var match = !kecId || String($(this).data('kec')) === String(kecId);
            $(this).toggle(match);
            if (match && this.value === currentVal) { stillValid = true; }
        });
        if (currentVal && !stillValid) { $kel.val(''); }
    }

    $kec.on('change', function () { filterKelOptionsByKec(this.value); $kel.val(''); fillSelect($rt, {}, 'Semua RT'); });
    $kel.on('change', function () { loadRt(this.value, ''); });

    if ($kec.val()) { filterKelOptionsByKec($kec.val()); }
    if ($kel.val()) { loadRt($kel.val(), SELECTED_RT); }
})();
</script>
@endpush
```

`resources/views/admin/kesmas/partials/_filter.blade.php`:

```blade
{{-- Filter periode & wilayah — submit = reload halaman (agregat server-side). --}}
<form method="GET" action="{{ route('admin.kesmas.dashboard') }}" class="im-filter" id="kmFilter">
    <input type="hidden" name="usia" value="{{ $usia }}" id="filterUsia">
    <div>
        <label for="filterTahun">Tahun</label>
        <select name="tahun" id="filterTahun">
            @foreach($tahunList as $th)
                <option value="{{ $th }}" {{ $periode->tahun() === $th ? 'selected' : '' }}>{{ $th }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="filterPeriode">Periode</label>
        <select name="periode" id="filterPeriode">
            @foreach(['tahun' => 'Setahun penuh', 's1' => 'Semester I (Jan–Jun)', 's2' => 'Semester II (Jul–Des)', 'tw1' => 'Triwulan I (Jan–Mar)', 'tw2' => 'Triwulan II (Apr–Jun)', 'tw3' => 'Triwulan III (Jul–Sep)', 'tw4' => 'Triwulan IV (Okt–Des)'] as $kode => $label)
                <option value="{{ $kode }}" {{ $periode->kode() === $kode ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="filterKec">Kecamatan</label>
        <select name="id_kecamatan" id="filterKec">
            <option value="">Semua kecamatan</option>
            @foreach($kecamatanList as $kec)
                <option value="{{ $kec->id }}" {{ ($filters['id_kecamatan'] ?? null) == $kec->id ? 'selected' : '' }}>{{ $kec->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="filterKel">Kelurahan</label>
        <select name="id_kelurahan" id="filterKel">
            <option value="">Semua kelurahan</option>
            @foreach($kelurahanList as $kel)
                <option value="{{ $kel->id }}" data-kec="{{ $kel->id_kecamatan }}" {{ ($filters['id_kelurahan'] ?? null) == $kel->id ? 'selected' : '' }}>{{ $kel->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="filterRt">RT</label>
        <select name="id_rt" id="filterRt">
            <option value="">Semua RT</option>
        </select>
    </div>
    <div>
        <label for="filterPos">Posyandu</label>
        <select name="id_posyandu" id="filterPos">
            <option value="">Semua posyandu</option>
            @foreach($posyanduList as $pos)
                <option value="{{ $pos->id }}" {{ ($filters['id_posyandu'] ?? null) == $pos->id ? 'selected' : '' }}>{{ $pos->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="filterPkm">Puskesmas</label>
        <select name="id_puskesmas" id="filterPkm">
            <option value="">Semua puskesmas</option>
            @foreach($puskesmasList as $pkm)
                <option value="{{ $pkm->id }}" {{ ($filters['id_puskesmas'] ?? null) == $pkm->id ? 'selected' : '' }}>{{ $pkm->name }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="im-btn im-btn--primary">
        <span class="material-symbols-outlined" style="font-size:18px;">filter_alt</span>Terapkan
    </button>
    @if(!empty($filters) || $periode->kode() !== 'tahun' || $periode->tahun() !== (int) now()->year)
        <a href="{{ route('admin.kesmas.dashboard') }}" class="im-btn im-btn--ghost">Reset</a>
    @endif
</form>
```

- [ ] **Step 6: Menu sidebar**

Di `leftsidebar.blade.php`:
1. Baris 21 (cabang super-admin) dan baris 142 (cabang admin): tambahkan `'admin.kesmas.*', ` tepat setelah `'admin.imunisasiDashboard', ` di dalam `request()->routeIs(...)`.
2. Setelah baris 36 dan setelah baris 153 (keduanya `<li><a href="{{route('admin.imunisasiDashboard')}}" …>Imunisasi</a></li>`) tambahkan:

```blade
						<li><a href="{{route('admin.kesmas.dashboard')}}" class="{{ request()->routeIs('admin.kesmas.*') ? 'active' : '' }}">Kesmas</a></li>
```

(Lakukan penyisipan baris 153 lebih dulu supaya nomor baris 36 tidak bergeser.)

- [ ] **Step 7: Jalankan, pastikan lulus**

Run: `php artisan view:clear; php artisan test --filter=KesmasDashboardControllerTest`
Expected: PASS (7 tests).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/KesmasDashboardController.php routes/web.php resources/views/admin/kesmas resources/views/vendor/admin/layouts/partials/leftsidebar.blade.php tests/Feature/Kesmas/KesmasDashboardControllerTest.php
git commit -m "feat(kesmas): halaman dasbor Kesmas — route, controller, filter periode/wilayah, menu"
```

---
### Task 9: View — 4 kartu SPM + SDIDTK, CKG, IDL & IBL

**Files:**
- Modify: `resources/views/admin/kesmas/dashboard.blade.php` (tambah CSS, markup baris 1–2, Chart.js)
- Test: `tests/Feature/Kesmas/KesmasDashboardControllerTest.php` (tambah test)

**Interfaces:**
- Consumes: variabel view Task 8 (`$spm`, `$tk`, `$sdidtk`, `$ckg`, `$idl`, `$ibl`, `$alasan`, `$usia`) dan helper `$fmt/$pct/$tone`.
- Produces: blok ber-`data-blok`: `spm-balita`, `spm-bayi`, `spm-anak-balita`, `spm-tk`, `sdidtk`, `ckg`, `idl`; masing-masing ditutup `<!-- /nama -->`. Kelas `.km-prog`, `.km-sub`, `.km-row`, `.km-callout`.

- [ ] **Step 1: Tulis tes yang gagal** — tambahkan ke `KesmasDashboardControllerTest`:

```php
    /** Angka besar kartu: `<span class="n">N</span><span class="d">/ SASARAN …` di dalam satu blok. */
    private function assertAngkaBesar(string $blok, string $n, string $sasaran): void
    {
        $this->assertMatchesRegularExpression(
            '/class="n">' . preg_quote($n, '/') . '<\/span><span class="d">\/ ' . preg_quote($sasaran, '/') . ' /',
            $blok,
            "Angka besar {$n} / {$sasaran} tidak ditemukan"
        );
    }

    private function kunjungan(Anak $anak, string $tgl, array $extra = []): void
    {
        \App\Models\DataAnak::create(array_merge([
            'id_anak' => $anak->id, 'tgl_kunjungan' => $tgl, 'bln' => 1, 'posisi' => 'L',
            'tb' => 60, 'bb' => 6, 'lla' => 12, 'lk' => 40, 'id_user' => $this->admin->id, 'sumber' => 'manual',
        ], $extra));
    }

    public function test_kartu_spm_menampilkan_angka_dari_service(): void
    {
        // Bayi 8 bln pada 31 Des 2025: 8 timbang, 2 DDTKA, Vit A, LK → lengkap.
        $bayi = $this->anak('Bayi Lengkap', '2025-04-30');
        foreach (range(5, 10) as $b) $this->kunjungan($bayi, sprintf('2025-%02d-15', $b));
        $this->kunjungan($bayi, '2025-11-15', ['ddtka' => 'Sesuai']);
        $this->kunjungan($bayi, '2025-12-15', ['ddtka' => 'Sesuai', 'vit_a' => 1]);
        $this->anak('Bayi Kosong', '2025-06-30');          // 6 bln, tanpa kunjungan
        $this->anak('Anak Balita', '2023-06-30');          // 30 bln, tanpa kunjungan
        $this->anak('Prasekolah', '2020-07-31');           // 65 bln

        $html = $this->actingAs($this->admin)
            ->get(route('admin.kesmas.dashboard', ['tahun' => 2025, 'periode' => 'tahun']))
            ->assertOk()->getContent();

        $balita = $this->blok($html, 'spm-balita');
        $this->assertAngkaBesar($balita, '1', '3');
        $this->assertStringContainsString('33,3 %', $balita);

        $kBayi = $this->blok($html, 'spm-bayi');
        $this->assertAngkaBesar($kBayi, '1', '2');
        $this->assertStringContainsString('50,0 %', $kBayi);
        $this->assertStringContainsString('8× Tbg', $kBayi);
        $this->assertStringContainsString('Sisa belum lengkap', $kBayi);

        $kAb = $this->blok($html, 'spm-anak-balita');
        $this->assertAngkaBesar($kAb, '0', '1');
        $this->assertStringContainsString('0,0 %', $kAb);

        $kTk = $this->blok($html, 'spm-tk');
        $this->assertAngkaBesar($kTk, '1', '4');
        $this->assertStringContainsString('perlu perhatian', $kTk);
    }

    public function test_kartu_spm_tanpa_sasaran_menampilkan_strip_bukan_nol_persen(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.kesmas.dashboard', ['tahun' => 2025]))->getContent();

        $balita = $this->blok($html, 'spm-balita');
        $this->assertStringContainsString('—', $balita);
        $this->assertStringNotContainsString('0,0 %', $balita);
    }

    public function test_sdidtk_ckg_dan_idl_terisi(): void
    {
        $b = $this->anak('Bayi', '2025-07-31'); // 5 bln
        $this->kunjungan($b, '2025-10-01', ['ddtka' => 'Sesuai', 'tgl_penanda_ckg' => '2025-10-01', 'pemeriksaan_gigi' => 'Sehat']);
        $this->anak('Balita', '2023-06-30');    // 30 bln, tanpa DDTKA → gap terbesar

        $html = $this->actingAs($this->admin)->get(route('admin.kesmas.dashboard', ['tahun' => 2025]))->getContent();

        $sdidtk = $this->blok($html, 'sdidtk');
        $this->assertStringContainsString('Kemandirian &amp; KPSP', $sdidtk);
        $this->assertStringContainsString('Fokus intervensi', $sdidtk);
        $this->assertStringContainsString('Balita (24–59 bln)', $sdidtk);

        $ckg = $this->blok($html, 'ckg');
        $this->assertStringContainsString('Bayi baru lahir', $ckg);
        $this->assertStringContainsString('100,0 %', $ckg, 'Bebas karies 1/1');

        $idl = $this->blok($html, 'idl');
        $this->assertStringContainsString('idlDonut', $idl);
        $this->assertStringContainsString(route('admin.imunisasiDashboard'), $idl);
    }
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --filter="KesmasDashboardControllerTest::test_(kartu|sdidtk)"`
Expected: FAIL — "Blok 'spm-balita' tidak ditemukan".

- [ ] **Step 3: Tambah CSS** — di dalam `<style>` pada `@push('styles')` (setelah aturan `.km-chip`):

```css
/* Kartu SPM */
.km-kicker{ display:flex; justify-content:space-between; align-items:center; gap:.5rem; }
.km-pill{ font-size:.66rem; font-weight:700; padding:.15rem .5rem; border-radius:99px; background:oklch(0.95 0.012 145); color:var(--muted); white-space:nowrap; }
.km-pill--ok{ background:oklch(0.94 0.06 145); color:var(--green-dk); }
.km-pill--mid{ background:var(--amber-bg); color:var(--amber); }
.km-pill--low{ background:var(--red-bg); color:var(--red-d); }
.km-title{ font-weight:700; font-size:1.02rem; line-height:1.25; color:var(--ink); }
.km-big{ display:flex; align-items:baseline; gap:.4rem; flex-wrap:wrap; }
.km-big .n{ font-family:'Barlow Condensed','Barlow',sans-serif; font-weight:700; font-size:2rem; line-height:1; color:var(--ink); }
.km-big .d{ color:var(--faint); font-size:.9rem; }
.km-big .p{ font-weight:700; font-size:1rem; margin-left:auto; }
.km-big .p.ok{ color:var(--green-dk); } .km-big .p.mid{ color:var(--amber); } .km-big .p.low{ color:var(--red-d); } .km-big .p.na{ color:var(--faint); }
.km-prog{ height:7px; border-radius:99px; background:oklch(0.93 0.012 145); overflow:hidden; }
.km-prog > span{ display:block; height:100%; border-radius:99px; background:var(--green-d); }
.km-prog > span.mid{ background:var(--amber); } .km-prog > span.low{ background:var(--red-d); }
.km-subs{ display:flex; flex-wrap:wrap; gap:.35rem; }
.km-sub{ font-size:.7rem; font-weight:600; padding:.16rem .5rem; border-radius:6px; border:1px solid var(--line); background:oklch(0.985 0.008 145); color:var(--ink); }
.km-sub.ok{ border-color:oklch(0.85 0.08 145); color:var(--green-dk); } .km-sub.mid{ border-color:oklch(0.85 0.09 70); color:var(--amber); } .km-sub.low{ border-color:oklch(0.85 0.09 25); color:var(--red-d); }
.km-foot{ margin-top:auto; font-size:.78rem; color:var(--muted); display:flex; gap:.5rem; align-items:center; flex-wrap:wrap; }
.km-foot b{ color:var(--ink); }
.km-foot .warn{ color:var(--red-d); font-weight:700; }
.km-dot{ display:inline-block; width:8px; height:8px; border-radius:50%; background:var(--red); }
/* Baris SDIDTK / CKG */
.km-row{ display:grid; grid-template-columns:1fr auto; gap:.15rem .6rem; align-items:baseline; padding:.45rem 0; border-top:1px solid var(--line); }
.km-row:first-of-type{ border-top:0; }
.km-row .lbl{ font-size:.85rem; font-weight:600; color:var(--ink); }
.km-row .dom{ font-size:.72rem; color:var(--faint); }
.km-row .val{ font-size:.82rem; color:var(--muted); white-space:nowrap; }
.km-row .val b{ color:var(--ink); }
.km-row .km-prog{ grid-column:1 / -1; height:6px; }
.km-row.hl .lbl{ color:var(--green-dk); }
.km-callout{ margin-top:.8rem; padding:.7rem .85rem; border-radius:10px; background:var(--amber-bg); color:oklch(0.35 0.10 70); font-size:.8rem; line-height:1.45; }
.km-callout b{ color:oklch(0.30 0.11 70); }
.km-empty{ padding:1rem; border:1px dashed var(--line); border-radius:10px; color:var(--muted); font-size:.85rem; text-align:center; }
/* IDL */
.km-donut{ display:flex; gap:1rem; align-items:center; }
.km-donut canvas{ width:120px !important; height:120px !important; }
.km-legend{ font-size:.8rem; color:var(--muted); display:flex; flex-direction:column; gap:.3rem; }
.km-legend b{ color:var(--ink); }
.km-alasan{ margin:.6rem 0 0; padding:0; list-style:none; font-size:.8rem; }
.km-alasan li{ display:flex; justify-content:space-between; gap:.5rem; padding:.28rem 0; border-top:1px solid var(--line); }
.km-alasan li b{ color:var(--ink); }
```

- [ ] **Step 4: Markup baris 1 & 2** — sisipkan di `dashboard.blade.php` **setelah** `</div>` penutup `.km-usia` dan **sebelum** `</div>` penutup `.im-page`:

```blade
    {{-- Baris 1 — kartu SPM (definisi angka: KesmasDashboardService::spmKohort / pemantauanTk) --}}
    @php $sy = $spm['syarat']; @endphp
    <section class="im-cards" aria-label="Kartu SPM tumbuh kembang">

        <article class="im-card" data-blok="spm-balita">
            <div class="km-kicker"><span class="im-card__lbl">SPM Kemenkes No. 4/2019</span>
                <span class="km-pill km-pill--{{ $tone($spm['balita']['persen']) }}">{{ $pct($spm['balita']['persen']) }}</span></div>
            <div class="km-title">Pelayanan Kesehatan Balita</div>
            <div class="im-card__sub">Usia 0–59 bulan (gabungan kohort bayi + anak balita) yang mendapat pelayanan sesuai standar</div>
            <div class="km-big im-num"><span class="n">{{ $fmt($spm['balita']['lengkap']) }}</span><span class="d">/ {{ $fmt($spm['balita']['sasaran']) }} balita</span>
                <span class="p {{ $tone($spm['balita']['persen']) }}">{{ $pct($spm['balita']['persen']) }}</span></div>
            <div class="km-prog" role="img" aria-label="{{ $pct($spm['balita']['persen']) }}"><span class="{{ $tone($spm['balita']['persen']) }}" style="width:{{ (int) ($spm['balita']['persen'] ?? 0) }}%"></span></div>
            <div class="km-foot">Syarat periode ini: {{ $sy['timbang'] }}× timbang · {{ $sy['ddtka'] }}× DDTKA · Vit A</div>
        </article><!-- /spm-balita -->

        <article class="im-card" data-blok="spm-bayi">
            <div class="km-kicker"><span class="im-card__lbl">Kohort bayi 0–11 bulan</span>
                <span class="km-pill km-pill--{{ $tone($spm['bayi']['persen']) }}">{{ $pct($spm['bayi']['persen']) }}</span></div>
            <div class="km-title">Pelayanan Kesehatan Bayi</div>
            <div class="im-card__sub">{{ $sy['timbang'] }}× timbang, {{ $sy['ddtka'] }}× DDTKA, Vit A (≥6 bln), lingkar kepala terukur</div>
            <div class="km-big im-num"><span class="n">{{ $fmt($spm['bayi']['lengkap']) }}</span><span class="d">/ {{ $fmt($spm['bayi']['sasaran']) }} bayi</span>
                <span class="p {{ $tone($spm['bayi']['persen']) }}">{{ $pct($spm['bayi']['persen']) }}</span></div>
            <div class="km-subs">
                @foreach(['timbang' => $sy['timbang'] . '× Tbg', 'ddtka' => $sy['ddtka'] . '× DDTKA', 'vita' => 'Vit A', 'lk' => 'LK'] as $k => $lbl)
                    @php $s = $spm['bayi']['sub'][$k]; @endphp
                    <span class="km-sub {{ $tone($s['persen']) }}" title="{{ $fmt($s['n']) }} dari {{ $fmt($s['sasaran']) }}">{{ $lbl }}: {{ $pct($s['persen']) }}</span>
                @endforeach
                <span class="km-sub {{ $tone($idl['persen'] ?: null) }}" title="Status saat ini, tidak mengikuti periode">IDL: {{ $idl['total'] > 0 ? $pct($idl['persen']) : '—' }}</span>
            </div>
            <div class="km-foot">Sisa belum lengkap: <b>{{ $fmt($spm['bayi']['sisa']) }} bayi</b>
                @if($spm['bayi']['sasaran'] > 0)<span>({{ $pct(round($spm['bayi']['sisa'] / $spm['bayi']['sasaran'] * 100, 1)) }})</span>@endif</div>
        </article><!-- /spm-bayi -->

        <article class="im-card" data-blok="spm-anak-balita">
            <div class="km-kicker"><span class="im-card__lbl">Kohort anak balita 12–59 bulan</span>
                <span class="km-pill km-pill--{{ $tone($spm['anak_balita']['persen']) }}">{{ $pct($spm['anak_balita']['persen']) }}</span></div>
            <div class="km-title">Pelayanan Kesehatan Anak Balita</div>
            <div class="im-card__sub">{{ $sy['timbang'] }}× timbang, {{ $sy['ddtka'] }}× DDTKA, {{ $sy['vita'] }}× Vit A; imunisasi lanjutan dipantau lewat IBL</div>
            <div class="km-big im-num"><span class="n">{{ $fmt($spm['anak_balita']['lengkap']) }}</span><span class="d">/ {{ $fmt($spm['anak_balita']['sasaran']) }} anak</span>
                <span class="p {{ $tone($spm['anak_balita']['persen']) }}">{{ $pct($spm['anak_balita']['persen']) }}</span></div>
            <div class="km-subs">
                @foreach(['timbang' => $sy['timbang'] . '× Tbg', 'ddtka' => $sy['ddtka'] . '× DDTKA', 'vita' => 'Vit A (' . $sy['vita'] . '×)'] as $k => $lbl)
                    @php $s = $spm['anak_balita']['sub'][$k]; @endphp
                    <span class="km-sub {{ $tone($s['persen']) }}" title="{{ $fmt($s['n']) }} dari {{ $fmt($s['sasaran']) }}">{{ $lbl }}: {{ $pct($s['persen']) }}</span>
                @endforeach
                <span class="km-sub {{ $tone($ibl['persen'] ?: null) }}" title="Status saat ini, tidak mengikuti periode">IBL: {{ $ibl['total'] > 0 ? $pct($ibl['persen']) : '—' }}</span>
            </div>
            <div class="km-foot">Kesenjangan target: <b>{{ $fmt($spm['anak_balita']['gap']) }} anak</b>
                @if($spm['anak_balita']['sasaran'] > 0)<span>({{ $pct(round($spm['anak_balita']['gap'] / $spm['anak_balita']['sasaran'] * 100, 1)) }})</span>@endif</div>
        </article><!-- /spm-anak-balita -->

        <article class="im-card" data-blok="spm-tk">
            <div class="km-kicker"><span class="im-card__lbl">Pemantauan tumbuh kembang</span>
                <span class="km-pill km-pill--{{ $tone($tk['persen']) }}">{{ $pct($tk['persen']) }}</span></div>
            <div class="km-title">Pemantauan Lengkap T&amp;K</div>
            <div class="im-card__sub">0–72 bulan dengan min. {{ $sy['timbang'] }}× timbang + {{ $sy['ddtka'] }}× DDTKA dalam periode</div>
            <div class="km-big im-num"><span class="n">{{ $fmt($tk['lengkap']) }}</span><span class="d">/ {{ $fmt($tk['sasaran']) }} balita</span>
                <span class="p {{ $tone($tk['persen']) }}">{{ $pct($tk['persen']) }}</span></div>
            <div class="km-prog" role="img" aria-label="{{ $pct($tk['persen']) }}"><span class="{{ $tone($tk['persen']) }}" style="width:{{ (int) ($tk['persen'] ?? 0) }}%"></span></div>
            <div class="km-foot"><span class="km-dot" aria-hidden="true"></span>
                <a href="#registri" class="warn" data-registri-status="perhatian">{{ $fmt($tk['perhatian']) }} balita perlu perhatian</a>
                <span>(BB tidak naik / BB/U &lt; −2 SD pada kunjungan terakhir)</span></div>
        </article><!-- /spm-tk -->

    </section>

    {{-- Baris 2 — SDIDTK, CKG, IDL & IBL --}}
    <section class="im-cards im-cards--3" aria-label="SDIDTK, CKG, dan imunisasi">

        <article class="im-card" data-blok="sdidtk">
            <div class="im-h" style="margin-bottom:.2rem;"><h2>Cakupan Layanan SDIDTK</h2></div>
            <div class="im-card__sub">Stimulasi, Deteksi &amp; Intervensi Dini Tumbuh Kembang (0–72 bulan)</div>
            <div class="km-big im-num"><span class="n">{{ $fmt($sdidtk['total']['realisasi']) }}</span><span class="d">dari {{ $fmt($sdidtk['total']['sasaran']) }} anak</span>
                <span class="p {{ $tone($sdidtk['total']['persen']) }}">{{ $pct($sdidtk['total']['persen']) }}</span></div>
            <div id="sdidtkRows">
                @foreach($sdidtk['kelompok'] as $kode => $k)
                    <div class="km-row {{ $usia === $kode ? 'hl' : '' }}" data-usia="{{ $kode }}">
                        <div><span class="lbl">{{ $k['label'] }} ({{ $k['min'] }}–{{ $k['max'] }} bln)</span> <span class="dom">{{ $k['domain'] }}</span></div>
                        <div class="val im-num"><b>{{ $fmt($k['realisasi']) }}</b> / {{ $fmt($k['sasaran']) }} ({{ $pct($k['persen']) }})</div>
                        <div class="km-prog"><span class="{{ $tone($k['persen']) }}" style="width:{{ (int) ($k['persen'] ?? 0) }}%"></span></div>
                    </div>
                @endforeach
            </div>
            @if($sdidtk['fokus'])
                <div class="km-callout"><b>Fokus intervensi:</b> kelompok {{ $sdidtk['fokus']['label'] }} ({{ $sdidtk['fokus']['min'] }}–{{ $sdidtk['fokus']['max'] }} bln) memiliki gap terbesar ({{ number_format($sdidtk['fokus']['gap'], 1, ',', '.') }} %). Prioritaskan skrining di posyandu/PAUD wilayah ini.</div>
            @endif
        </article><!-- /sdidtk -->

        <article class="im-card" data-blok="ckg">
            <div class="im-h" style="margin-bottom:.2rem;"><h2>Cek Kesehatan Gigi &amp; CKG</h2></div>
            <div class="im-card__sub">Anak dengan penanda CKG (Cek Kesehatan Gratis) dalam periode, per usia</div>
            <div>
                @foreach($ckg['kelompok'] as $k)
                    <div class="km-row">
                        <div><span class="lbl">{{ $k['label'] }}</span></div>
                        <div class="val im-num"><b>{{ $fmt($k['realisasi']) }}</b> / {{ $fmt($k['sasaran']) }} ({{ $pct($k['persen']) }})</div>
                        <div class="km-prog"><span class="{{ $tone($k['persen']) }}" style="width:{{ (int) ($k['persen'] ?? 0) }}%"></span></div>
                    </div>
                @endforeach
            </div>
            <div class="km-foot" style="display:grid; grid-template-columns:1fr 1fr; gap:.5rem;">
                <div>Bebas karies (gigi sehat)<br><b class="im-num" style="font-size:1.1rem;">{{ $pct($ckg['gigi']['persen_sehat']) }}</b>
                    <small>dari {{ $fmt($ckg['gigi']['terisi']) }} pemeriksaan</small></div>
                <div>Rujuk dokter gigi<br><b class="im-num" style="font-size:1.1rem;">{{ $fmt($ckg['rujuk_gigi']) }} anak</b></div>
            </div>
        </article><!-- /ckg -->

        <article class="im-card" data-blok="idl">
            <div class="im-h" style="margin-bottom:.2rem;"><h2>Imunisasi Dasar &amp; Lanjut</h2></div>
            <div class="im-card__sub">Cakupan IDL (≥12 bln) &amp; IBL (≥24 bln) — status saat ini, tidak mengikuti periode</div>
            <div class="km-donut">
                <canvas id="idlDonut" width="120" height="120" role="img" aria-label="IDL lengkap {{ $idl['total'] > 0 ? $pct($idl['persen']) : '—' }}"></canvas>
                <div class="km-legend im-num">
                    <span><b>{{ $idl['total'] > 0 ? $pct($idl['persen']) : '—' }}</b> IDL lengkap ({{ $fmt($idl['idl_lengkap']) }}/{{ $fmt($idl['total']) }})</span>
                    <span><b>{{ $idl['total'] > 0 ? $pct(round(100 - $idl['persen'], 1)) : '—' }}</b> belum lengkap</span>
                    <span><b>{{ $ibl['total'] > 0 ? $pct($ibl['persen']) : '—' }}</b> IBL booster ({{ $fmt($ibl['ibl_lengkap']) }}/{{ $fmt($ibl['total']) }})</span>
                </div>
            </div>
            @if(count($alasan) > 0)
                <div class="im-card__lbl" style="margin-top:.5rem;">Penyebab belum lengkap</div>
                <ul class="km-alasan">
                    @php $totalAlasan = array_sum($alasan); @endphp
                    @foreach($alasan as $nama => $jumlah)
                        <li><span>{{ $nama }}</span><b class="im-num">{{ $pct(round($jumlah / max(1, $totalAlasan) * 100, 1)) }}</b></li>
                    @endforeach
                </ul>
            @endif
            <div class="km-foot"><a href="{{ route('admin.imunisasiDashboard', $filters) }}" class="im-card__link">Lihat dasbor imunisasi &rarr;</a></div>
        </article><!-- /idl -->

    </section>
```

- [ ] **Step 5: Chart.js** — di `@push('js')`, **sebelum** `<script>` cascade yang sudah ada:

```blade
@if($idl['total'] > 0)
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function () {
    var el = document.getElementById('idlDonut');
    if (!el || typeof Chart === 'undefined') return;
    new Chart(el.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['IDL lengkap', 'Belum lengkap'],
            datasets: [{ data: [{{ (int) $idl['idl_lengkap'] }}, {{ (int) ($idl['total'] - $idl['idl_lengkap']) }}],
                backgroundColor: ['#2f7d4f', '#e4e8e4'], borderWidth: 0 }]
        },
        options: { cutout: '72%', plugins: { legend: { display: false } }, responsive: false }
    });
})();
</script>
@endif
```

- [ ] **Step 6: Jalankan, pastikan lulus**

Run: `php artisan view:clear; php artisan test --filter=KesmasDashboardControllerTest`
Expected: PASS (10 tests). Jika `assertAngkaBesar` gagal: pastikan markup `<span class="n">…</span><span class="d">/ …` ditulis tanpa spasi/baris baru di antara kedua span (persis seperti Step 4).

- [ ] **Step 7: Commit**

```bash
git add resources/views/admin/kesmas/dashboard.blade.php tests/Feature/Kesmas/KesmasDashboardControllerTest.php
git commit -m "feat(kesmas): kartu SPM, SDIDTK, CKG, dan IDL/IBL di dasbor Kesmas"
```

---
### Task 10: View — seksi Layanan & Lingkungan

**Files:**
- Modify: `resources/views/admin/kesmas/dashboard.blade.php`
- Test: `tests/Feature/Kesmas/KesmasDashboardControllerTest.php`

**Interfaces:**
- Consumes: `$layanan` (Task 5: `sasaran`, `bayi`, `layanan.baris/ada_data`, `skrining.baris/ada_data`, `sanitasi.baris/ada_data`).
- Produces: blok `data-blok="layanan"` berisi tiga panel `data-panel="layanan|skrining|sanitasi"`; kelas `.km-bar`, `.km-stack`, `.km-belum`.

- [ ] **Step 1: Tulis tes yang gagal** — tambahkan ke `KesmasDashboardControllerTest`:

```php
    public function test_layanan_lingkungan_menampilkan_pembagi_terisi_dan_belum_diisi(): void
    {
        $a = $this->anak('Terisi', '2024-06-30', ['air_bersih' => 1, 'merokok_keluarga' => 1]); // 18 bln
        $this->kunjungan($a, '2025-03-01', ['kn1' => 1, 'mtbs' => 0]);
        $this->anak('Kosong', '2024-06-30');
        $b = $this->anak('Bayi', '2025-09-30', ['skrining_shk' => 'tidak_normal']); // 3 bln

        $html = $this->actingAs($this->admin)->get(route('admin.kesmas.dashboard', ['tahun' => 2025]))->getContent();
        $blok = $this->blok($html, 'layanan');

        $this->assertStringContainsString('KN1', $blok);
        $this->assertMatchesRegularExpression('/data-baris="kn1".*?<b>1<\/b>\s*\/\s*1.*?100,0 %/s', $blok);
        $this->assertMatchesRegularExpression('/data-baris="kn1".*?2 anak belum diisi/s', $blok, 'sasaran 3 − terisi 1');
        $this->assertMatchesRegularExpression('/data-baris="mtbs".*?<b>0<\/b>\s*\/\s*1/s', $blok);
        $this->assertMatchesRegularExpression('/data-baris="pkat".*?—/s', $blok, 'pembagi 0 → strip');

        $this->assertMatchesRegularExpression('/data-baris="skrining_shk".*?Tidak normal.*?1/s', $blok);
        $this->assertMatchesRegularExpression('/data-baris="skrining_shk".*?0 bayi belum diisi/s', $blok);

        $this->assertMatchesRegularExpression('/data-baris="air_bersih".*?100,0 %/s', $blok);
        $this->assertMatchesRegularExpression('/data-baris="air_bersih".*?2 anak belum diisi/s', $blok);
        $this->assertStringContainsString('class="km-bar terbalik" data-baris="merokok_keluarga"', $blok);
    }

    public function test_layanan_lingkungan_tanpa_data_menampilkan_empty_state(): void
    {
        $this->anak('Polos', '2024-06-30');

        $html = $this->actingAs($this->admin)->get(route('admin.kesmas.dashboard', ['tahun' => 2025]))->getContent();
        $blok = $this->blok($html, 'layanan');

        $this->assertSame(3, substr_count($blok, 'Belum ada data'), 'tiga panel empty-state');
        $this->assertStringContainsString('lengkapi lewat Edit Anak', $blok);
        $this->assertStringNotContainsString('0,0 %', $blok);
    }
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --filter="KesmasDashboardControllerTest::test_layanan"`
Expected: FAIL — "Blok 'layanan' tidak ditemukan".

- [ ] **Step 3: CSS** — tambahkan ke `<style>` halaman:

```css
/* Layanan & Lingkungan */
.km-wide{ background:var(--card); border:1px solid var(--line); border-radius:14px; padding:1.1rem 1.25rem; margin-bottom:1.6rem; box-shadow:0 1px 3px oklch(0 0 0 / .04); }
.km-panels{ display:grid; grid-template-columns:repeat(3,1fr); gap:1.25rem; margin-top:.8rem; }
@media(max-width:980px){ .km-panels{ grid-template-columns:1fr; } }
.km-panel h3{ font-family:'Barlow Condensed','Barlow',sans-serif; font-weight:700; font-size:1rem; margin:0 0 .1rem; color:var(--ink); }
.km-panel .im-card__sub{ margin-bottom:.5rem; }
.km-bar{ display:grid; grid-template-columns:1fr auto; gap:.1rem .6rem; align-items:baseline; padding:.4rem 0; border-top:1px solid var(--line); }
.km-bar .lbl{ font-size:.82rem; font-weight:600; color:var(--ink); }
.km-bar .val{ font-size:.8rem; color:var(--muted); white-space:nowrap; }
.km-bar .val b{ color:var(--ink); }
.km-bar .km-prog{ grid-column:1 / -1; height:6px; }
.km-bar.terbalik .km-prog > span{ background:var(--red-d); }
.km-belum{ grid-column:1 / -1; font-size:.7rem; color:var(--faint); }
.km-stack{ grid-column:1 / -1; display:flex; height:8px; border-radius:99px; overflow:hidden; background:oklch(0.93 0.012 145); }
.km-stack > span{ display:block; height:100%; }
.km-stack .s-normal, .km-stack .s-non_reaktif{ background:var(--green-d); }
.km-stack .s-tidak_normal, .km-stack .s-reaktif{ background:var(--red-d); }
.km-stack .s-belum{ background:oklch(0.80 0.02 145); }
.km-legend-inline{ grid-column:1 / -1; display:flex; flex-wrap:wrap; gap:.2rem .8rem; font-size:.72rem; color:var(--muted); }
.km-legend-inline b{ color:var(--ink); }
```

- [ ] **Step 4: Markup** — sisipkan setelah `</section>` baris 2 (blok `idl`), sebelum `</div>` penutup `.im-page`:

```blade
    {{-- Baris 3 — Layanan & Lingkungan: pembagi = anak yang datanya TERISI; NULL = belum ditanya --}}
    <section class="km-wide" data-blok="layanan" aria-label="Layanan dan lingkungan">
        <div class="im-h"><h2>Layanan &amp; Lingkungan</h2><small>persentase dihitung dari anak yang datanya sudah diisi</small></div>
        <div class="km-panels">

            <div class="km-panel" data-panel="layanan">
                <h3>Layanan per kunjungan</h3>
                <div class="im-card__sub">Anak 0–72 bln dengan ≥1 kunjungan dalam periode; dari {{ $fmt($layanan['sasaran']) }} sasaran</div>
                @if($layanan['layanan']['ada_data'])
                    @foreach($layanan['layanan']['baris'] as $kode => $b)
                        <div class="km-bar" data-baris="{{ $kode }}">
                            <span class="lbl" title="{{ $b['label'] }}">{{ $b['badge'] }} <small style="font-weight:400;color:var(--faint);">{{ \Illuminate\Support\Str::after($b['label'], '— ') }}</small></span>
                            <span class="val im-num"><b>{{ $fmt($b['ya']) }}</b> / {{ $fmt($b['terisi']) }} ({{ $pct($b['persen']) }})</span>
                            <div class="km-prog"><span class="{{ $tone($b['persen']) }}" style="width:{{ (int) ($b['persen'] ?? 0) }}%"></span></div>
                            <span class="km-belum">{{ $fmt($b['belum_diisi']) }} anak belum diisi</span>
                        </div>
                    @endforeach
                @else
                    <div class="km-empty">Belum ada data layanan — lengkapi lewat Edit Anak (kartu Kesmas per kunjungan)</div>
                @endif
            </div>

            <div class="km-panel" data-panel="skrining">
                <h3>Skrining neonatal</h3>
                <div class="im-card__sub">Bayi 0–11 bln; dari {{ $fmt($layanan['bayi']) }} bayi</div>
                @if($layanan['skrining']['ada_data'])
                    @foreach($layanan['skrining']['baris'] as $kode => $b)
                        <div class="km-bar" data-baris="{{ $kode }}">
                            <span class="lbl">{{ $b['label'] }}</span>
                            <span class="val im-num">{{ $fmt($b['terisi']) }} terisi</span>
                            <div class="km-stack" role="img" aria-label="{{ collect($b['sebaran'])->map(fn ($s) => $s['label'] . ' ' . $fmt($s['n']))->implode(', ') }}">
                                @foreach($b['sebaran'] as $nilai => $s)
                                    <span class="s-{{ $nilai }}" style="width:{{ (int) ($s['persen'] ?? 0) }}%"></span>
                                @endforeach
                            </div>
                            <span class="km-legend-inline">
                                @foreach($b['sebaran'] as $s)<span>{{ $s['label'] }} <b>{{ $fmt($s['n']) }}</b></span>@endforeach
                            </span>
                            <span class="km-belum">{{ $fmt($b['belum_diisi']) }} bayi belum diisi</span>
                        </div>
                    @endforeach
                @else
                    <div class="km-empty">Belum ada data skrining — lengkapi lewat Edit Anak (kartu Riwayat lahir &amp; skrining)</div>
                @endif
            </div>

            <div class="km-panel" data-panel="sanitasi">
                <h3>Sanitasi rumah</h3>
                <div class="im-card__sub">Anak 0–72 bln; dari {{ $fmt($layanan['sasaran']) }} sasaran</div>
                @if($layanan['sanitasi']['ada_data'])
                    @foreach($layanan['sanitasi']['baris'] as $kode => $b)
                        <div class="km-bar {{ $b['terbalik'] ? 'terbalik' : '' }}" data-baris="{{ $kode }}">
                            <span class="lbl">{{ $b['label'] }}</span>
                            <span class="val im-num"><b>{{ $fmt($b['ya']) }}</b> / {{ $fmt($b['terisi']) }} ({{ $pct($b['persen']) }})</span>
                            <div class="km-prog"><span class="{{ $b['terbalik'] ? '' : $tone($b['persen']) }}" style="width:{{ (int) ($b['persen'] ?? 0) }}%"></span></div>
                            <span class="km-belum">{{ $fmt($b['belum_diisi']) }} anak belum diisi</span>
                        </div>
                    @endforeach
                @else
                    <div class="km-empty">Belum ada data sanitasi — lengkapi lewat Edit Anak (kartu Kesmas &amp; lingkungan)</div>
                @endif
            </div>

        </div>
    </section><!-- /layanan -->
```

- [ ] **Step 5: Jalankan, pastikan lulus**

Run: `php artisan view:clear; php artisan test --filter=KesmasDashboardControllerTest`
Expected: PASS (12 tests).

- [ ] **Step 6: Commit**

```bash
git add resources/views/admin/kesmas/dashboard.blade.php tests/Feature/Kesmas/KesmasDashboardControllerTest.php
git commit -m "feat(kesmas): seksi Layanan & Lingkungan dengan pembagi anak terisi dan jumlah belum diisi"
```

---

### Task 11: Registri — partial, JS fetch, endpoint JSON

**Files:**
- Create: `resources/views/admin/kesmas/partials/_registri.blade.php`
- Modify: `resources/views/admin/kesmas/dashboard.blade.php` (include + CSS + JS)
- Test: `tests/Feature/Kesmas/KesmasRegistriTest.php` (tambah test HTTP), `tests/Feature/Kesmas/KesmasDashboardControllerTest.php` (tambah 1 test)

**Interfaces:**
- Consumes: route `admin.kesmas.registri` (Task 8) → JSON `{data, total, page, last_page, per_page}` (Task 6).
- Produces: `<section id="registri" data-blok="registri">` dengan `#registriCari`, `#registriGizi`, `<tbody id="registriBody" aria-live="polite">`, `#registriInfo`, `#registriPag`; JS global state chip usia → `history.replaceState`.

- [ ] **Step 1: Tulis tes yang gagal** — tambahkan ke `KesmasRegistriTest`:

```php
    public function test_endpoint_json_mengembalikan_bentuk_registri(): void
    {
        $a = $this->anak('Budi', 30);
        $this->kunjungan($a, '2025-09-10', ['zscore_bb_u' => -2.5]);
        $this->anak('Citra', 5);

        $r = $this->actingAs($this->admin)
            ->getJson(route('admin.kesmas.registri', ['tahun' => 2025, 'periode' => 'tahun', 'usia' => 'semua', 'q' => '', 'status_gizi' => 'perhatian']))
            ->assertOk()
            ->assertJsonStructure(['data' => ['*' => ['no', 'nama', 'nik', 'jk', 'umur_bln', 'kelurahan', 'rt', 'posyandu', 'kunjungan', 'idl', 'ibl', 'catatan', 'url_detail']], 'total', 'page', 'last_page', 'per_page'])
            ->json();

        $this->assertSame(1, $r['total']);
        $this->assertSame('Budi', $r['data'][0]['nama']);
        $this->assertSame('underweight', $r['data'][0]['kunjungan']['gizi']['kode']);
    }

    public function test_endpoint_json_menolak_status_gizi_dan_halaman_tidak_valid(): void
    {
        $this->actingAs($this->admin)->getJson(route('admin.kesmas.registri', ['status_gizi' => 'gemuk']))
            ->assertStatus(422)->assertJsonValidationErrors('status_gizi');
        $this->actingAs($this->admin)->getJson(route('admin.kesmas.registri', ['page' => 0]))
            ->assertStatus(422)->assertJsonValidationErrors('page');
        $this->actingAs($this->admin)->getJson(route('admin.kesmas.registri', ['usia' => 'remaja']))
            ->assertStatus(422)->assertJsonValidationErrors('usia');
    }
```

Dan ke `KesmasDashboardControllerTest`:

```php
    public function test_halaman_memuat_kerangka_registri(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.kesmas.dashboard'))->getContent();
        $blok = $this->blok($html, 'registri');

        $this->assertStringContainsString('id="registriBody"', $blok);
        $this->assertStringContainsString('aria-live="polite"', $blok);
        $this->assertStringContainsString('<label for="registriCari"', $blok);
        $this->assertStringContainsString('<label for="registriGizi"', $blok);
        $this->assertStringContainsString(route('admin.kesmas.registri'), $html);
    }
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --filter="KesmasRegistriTest::test_endpoint|KesmasDashboardControllerTest::test_halaman_memuat_kerangka_registri"`
Expected: `test_endpoint_json_mengembalikan_bentuk_registri` PASS (endpoint sudah ada sejak Task 8), `test_halaman_memuat_kerangka_registri` FAIL "Blok 'registri' tidak ditemukan".

- [ ] **Step 3: Partial `_registri.blade.php`**

```blade
{{-- Registri longitudinal — data dimuat JS dari admin.kesmas.registri (20/halaman) supaya balik halaman tak menghitung ulang agregat. --}}
<section class="km-wide" id="registri" data-blok="registri" aria-label="Registri longitudinal balita">
    <div class="km-reg-head">
        <div>
            <div class="im-h" style="margin-bottom:.1rem;"><h2>Registri Longitudinal &amp; Pelayanan Balita</h2></div>
            <div class="im-card__sub">Antropometri terakhir dalam periode, status IDL/IBL saat ini, dan catatan petugas</div>
        </div>
        <span class="km-pill" id="registriTotal">Memuat…</span>
        <div class="km-reg-tools">
            <label for="registriCari" class="sr-only">Cari nama, NIK, atau nama orang tua</label>
            <input type="search" id="registriCari" placeholder="Cari nama, NIK, atau orang tua" autocomplete="off">
            <label for="registriGizi" class="sr-only">Status gizi</label>
            <select id="registriGizi">
                <option value="semua">Semua status gizi</option>
                <option value="normal">Gizi baik</option>
                <option value="stunted">Pendek (stunting)</option>
                <option value="underweight">BB kurang (underweight)</option>
                <option value="wasted">Gizi kurang (wasting)</option>
                <option value="perhatian">Perlu perhatian (BB tidak naik / BB/U &lt; −2 SD)</option>
            </select>
        </div>
    </div>
    <div class="km-table-wrap">
        <table class="km-table">
            <thead>
                <tr>
                    <th scope="col">No</th>
                    <th scope="col">Nama balita &amp; NIK</th>
                    <th scope="col">Orang tua</th>
                    <th scope="col">Wilayah &amp; posyandu</th>
                    <th scope="col">Antropometri terakhir</th>
                    <th scope="col">IDL</th>
                    <th scope="col">IBL</th>
                    <th scope="col">Catatan</th>
                    <th scope="col"><span class="sr-only">Aksi</span></th>
                </tr>
            </thead>
            <tbody id="registriBody" aria-live="polite" aria-busy="true">
                @for($i = 0; $i < 5; $i++)
                    <tr class="km-skel"><td colspan="9"><span></span></td></tr>
                @endfor
            </tbody>
        </table>
    </div>
    <div class="km-reg-foot">
        <span id="registriInfo" class="im-card__sub"></span>
        <nav id="registriPag" class="km-pag" aria-label="Halaman registri"></nav>
    </div>
</section><!-- /registri -->
```

- [ ] **Step 4: Include, CSS, dan JS di `dashboard.blade.php`**

Sisipkan `@include('admin.kesmas.partials._registri')` setelah `</section><!-- /layanan -->`.

CSS (tambahkan ke `<style>`):

```css
/* Registri */
.km-reg-head{ display:flex; flex-wrap:wrap; align-items:center; gap:.75rem 1rem; }
.km-reg-tools{ display:flex; flex-wrap:wrap; gap:.5rem; margin-left:auto; }
.km-reg-tools input, .km-reg-tools select{ height:36px; padding:0 .7rem; border:1px solid oklch(0.84 0.012 145); border-radius:8px; background:var(--card); font-family:inherit; font-size:.85rem; color:var(--ink); min-width:200px; }
.km-reg-tools input:focus, .km-reg-tools select:focus{ outline:2px solid oklch(0.60 0.15 145 / .35); outline-offset:1px; border-color:var(--green); }
.km-table-wrap{ overflow-x:auto; margin-top:.9rem; -webkit-overflow-scrolling:touch; }
.km-table{ width:100%; min-width:960px; border-collapse:collapse; font-size:.82rem; }
.km-table th{ text-align:left; font-size:.66rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--muted); padding:.5rem .6rem; border-bottom:1px solid var(--line); white-space:nowrap; }
.km-table td{ padding:.6rem .6rem; border-bottom:1px solid var(--line); vertical-align:top; color:var(--ink); }
.km-table td small{ display:block; color:var(--faint); font-size:.72rem; }
.km-table .mono{ font-family:'Barlow',monospace; font-variant-numeric:tabular-nums; }
.km-badge{ display:inline-block; padding:.14rem .5rem; border-radius:99px; font-size:.68rem; font-weight:700; white-space:nowrap; }
.km-badge--ok{ background:oklch(0.94 0.06 145); color:var(--green-dk); }
.km-badge--warn{ background:var(--amber-bg); color:var(--amber); }
.km-badge--bad{ background:var(--red-bg); color:var(--red-d); }
.km-badge--na{ background:oklch(0.95 0.012 145); color:var(--muted); font-style:italic; font-weight:600; }
.km-skel td{ padding:.7rem .6rem; } .km-skel span{ display:block; height:14px; border-radius:6px; background:linear-gradient(90deg, oklch(0.94 0.01 145), oklch(0.97 0.01 145), oklch(0.94 0.01 145)); background-size:200% 100%; animation:km-shine 1.2s infinite; }
@keyframes km-shine{ from{ background-position:200% 0; } to{ background-position:-200% 0; } }
.km-reg-foot{ display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:.5rem; margin-top:.8rem; }
.km-pag{ display:flex; gap:.25rem; }
.km-pag button{ min-width:32px; height:32px; padding:0 .5rem; border:1px solid var(--line); border-radius:8px; background:var(--card); font-family:inherit; font-weight:600; font-size:.8rem; color:var(--ink); cursor:pointer; }
.km-pag button[aria-current="page"]{ background:var(--green-dk); border-color:var(--green-dk); color:#fff; }
.km-pag button:disabled{ color:var(--faint); cursor:not-allowed; }
.km-err{ color:var(--red-d); font-weight:600; }
.km-err button{ margin-left:.5rem; }
.sr-only{ position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
```

JS (tambahkan `<script>` baru di `@push('js')`, setelah script cascade):

```blade
<script>
(function () {
    var URL  = '{{ route('admin.kesmas.registri') }}';
    var BASE = @json(array_merge(['tahun' => $periode->tahun(), 'periode' => $periode->kode()], $filters));
    var state = { usia: '{{ $usia }}', q: '', status_gizi: 'semua', page: 1 };
    var body = document.getElementById('registriBody');
    var info = document.getElementById('registriInfo');
    var pag  = document.getElementById('registriPag');
    var total = document.getElementById('registriTotal');
    var cari = document.getElementById('registriCari');
    var gizi = document.getElementById('registriGizi');
    var usiaHidden = document.getElementById('filterUsia');
    var timer = null;

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function fmt(n) { return Number(n).toLocaleString('id-ID'); }
    function num(v, d) { return v == null ? '—' : Number(v).toLocaleString('id-ID', { minimumFractionDigits: d, maximumFractionDigits: d }); }
    function badgeImun(s) {
        if (s === 'lengkap') return '<span class="km-badge km-badge--ok">Lengkap</span>';
        if (s === 'belum')   return '<span class="km-badge km-badge--bad">Belum</span>';
        return '<span class="km-badge km-badge--na">Belum masuk usia</span>';
    }
    function tglId(s) { if (!s) return ''; var p = s.split('-'); return p[2] + '/' + p[1] + '/' + p[0]; }

    function baris(r) {
        var k = r.kunjungan;
        var antro = k
            ? '<span class="mono">BB ' + num(k.bb, 1) + ' kg · TB ' + num(k.tb, 1) + ' cm · LK ' + num(k.lk, 1) + ' cm</span>'
              + (k.gizi ? ' <span class="km-badge km-badge--' + esc(k.gizi.tone) + '">' + esc(k.gizi.label) + '</span>' : '')
              + (k.bb_tidak_naik ? ' <span class="km-badge km-badge--bad">BB tidak naik</span>' : '')
              + '<small>' + tglId(k.tgl) + '</small>'
            : '<span style="color:var(--faint)">— belum ada kunjungan dalam periode</span>';
        return '<tr>'
            + '<td class="mono">' + r.no + '</td>'
            + '<td><b>' + esc(r.nama) + '</b><small>NIK ' + esc(r.nik || '—') + ' · ' + esc(r.jk) + ' · ' + r.umur_bln + ' bln (' + tglId(r.tgl_lahir) + ')</small></td>'
            + '<td>' + (r.nama_ibu ? 'Ibu: ' + esc(r.nama_ibu) : '') + (r.nama_ayah ? '<small>Ayah: ' + esc(r.nama_ayah) + '</small>' : '') + '</td>'
            + '<td>' + esc(r.posyandu || '—') + '<small>' + (r.rt ? 'RT ' + esc(r.rt) + ' · ' : '') + esc(r.kelurahan || '—') + '</small></td>'
            + '<td>' + antro + '</td>'
            + '<td>' + badgeImun(r.idl) + '</td>'
            + '<td>' + badgeImun(r.ibl) + '</td>'
            + '<td>' + (r.catatan ? esc(r.catatan) : '<span style="color:var(--faint)">—</span>') + '</td>'
            + '<td><a class="im-btn im-btn--ghost im-btn--sm" href="' + esc(r.url_detail) + '">Detail</a></td>'
            + '</tr>';
    }

    function render(j) {
        body.setAttribute('aria-busy', 'false');
        total.textContent = 'Total: ' + fmt(j.total) + ' balita';
        if (!j.data.length) {
            body.innerHTML = '<tr><td colspan="9"><div class="km-empty">Tidak ada balita yang cocok dengan filter ini</div></td></tr>';
            info.textContent = ''; pag.innerHTML = ''; return;
        }
        body.innerHTML = j.data.map(baris).join('');
        var a = (j.page - 1) * j.per_page + 1, b = a + j.data.length - 1;
        info.textContent = 'Menampilkan ' + fmt(a) + '–' + fmt(b) + ' dari ' + fmt(j.total) + ' balita';
        var html = '<button type="button" data-page="' + (j.page - 1) + '"' + (j.page <= 1 ? ' disabled' : '') + ' aria-label="Sebelumnya">‹</button>';
        var pages = [];
        for (var p = 1; p <= j.last_page; p++) { if (p === 1 || p === j.last_page || Math.abs(p - j.page) <= 1) pages.push(p); }
        pages.forEach(function (p, i) {
            if (i > 0 && p - pages[i - 1] > 1) html += '<button type="button" disabled>…</button>';
            html += '<button type="button" data-page="' + p + '"' + (p === j.page ? ' aria-current="page"' : '') + '>' + p + '</button>';
        });
        html += '<button type="button" data-page="' + (j.page + 1) + '"' + (j.page >= j.last_page ? ' disabled' : '') + ' aria-label="Berikutnya">›</button>';
        pag.innerHTML = html;
    }

    function gagal() {
        body.setAttribute('aria-busy', 'false');
        body.innerHTML = '<tr><td colspan="9" class="km-err">Gagal memuat registri — coba lagi <button type="button" class="im-btn im-btn--ghost im-btn--sm" id="registriUlang">Coba lagi</button></td></tr>';
        document.getElementById('registriUlang').addEventListener('click', load);
    }

    function load() {
        body.setAttribute('aria-busy', 'true');
        var params = new URLSearchParams(Object.assign({}, BASE, state));
        fetch(URL + '?' + params.toString(), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
            .then(render)
            .catch(gagal);
    }

    // Chip usia: tanpa reload — ditulis ke URL (history) & hidden input filter supaya ikut saat "Terapkan".
    document.querySelectorAll('.km-chip[data-usia]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.km-chip[data-usia]').forEach(function (b) { b.setAttribute('aria-pressed', 'false'); });
            btn.setAttribute('aria-pressed', 'true');
            state.usia = btn.dataset.usia; state.page = 1;
            if (usiaHidden) usiaHidden.value = state.usia;
            var u = new URL(window.location.href); u.searchParams.set('usia', state.usia); history.replaceState(null, '', u.toString());
            document.querySelectorAll('#sdidtkRows .km-row').forEach(function (row) { row.classList.toggle('hl', row.dataset.usia === state.usia); });
            load();
        });
    });
    cari.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(function () { state.q = cari.value.trim(); state.page = 1; load(); }, 300); });
    gizi.addEventListener('change', function () { state.status_gizi = gizi.value; state.page = 1; load(); });
    pag.addEventListener('click', function (e) {
        var b = e.target.closest('button[data-page]'); if (!b || b.disabled) return;
        state.page = parseInt(b.dataset.page, 10); load();
        document.getElementById('registri').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    // Tautan "perlu perhatian" di kartu K4 → registri terfilter.
    document.querySelectorAll('[data-registri-status]').forEach(function (a) {
        a.addEventListener('click', function () { gizi.value = a.dataset.registriStatus; gizi.dispatchEvent(new Event('change')); });
    });

    load();
})();
</script>
```

- [ ] **Step 5: Jalankan, pastikan lulus**

Run: `php artisan view:clear; php artisan test --filter="KesmasRegistriTest|KesmasDashboardControllerTest"`
Expected: PASS (9 + 13 tests).

- [ ] **Step 6: Cek di browser**

Buka `http://sirindu.test/admin/kesmas-dashboard` (login `dinkes@sirindu.go.id` / `Sirindu@2026`; DB dev hanya 39 anak, sebagian angka "—" wajar): registri terisi tanpa reload, ketik nama → tabel menyaring, ganti status gizi, klik chip usia → URL berubah `?usia=…` & baris SDIDTK tersorot, klik halaman (bila > 20 anak), klik "perlu perhatian" di kartu K4 → select status berubah & tabel memuat. Console tanpa error. Lebar 375 px: tabel scroll horizontal, kartu 1 kolom.

- [ ] **Step 7: Commit**

```bash
git add resources/views/admin/kesmas tests/Feature/Kesmas/KesmasRegistriTest.php tests/Feature/Kesmas/KesmasDashboardControllerTest.php
git commit -m "feat(kesmas): registri longitudinal — tabel JSON dengan cari, filter gizi, chip usia, paginasi"
```

---
### Task 12: Pengunci memori/query, pengunci gotcha Blade, verifikasi akhir

**Files:**
- Test: `tests/Feature/Kesmas/KesmasDashboardMemoriTest.php`, `tests/Feature/Kesmas/KesmasDashboardBladeTest.php`
- (Tidak ada kode produksi baru kecuali perbaikan bila tes ini menemukan pelanggaran.)

**Interfaces:**
- Consumes: semua method `KesmasDashboardService`; view `admin/kesmas/*.blade.php`.

- [ ] **Step 1: Tes memori & jumlah query**

```php
<?php
// tests/Feature/Kesmas/KesmasDashboardMemoriTest.php

namespace Tests\Feature\Kesmas;

use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Services\ImunisasiStatusService;
use App\Services\KesmasDashboardService;
use App\Support\PeriodeKesmas;
use App\Support\WilkerPuskesmas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Prod ±10 rb anak, memory_limit 128 MB (insiden dasbor imunisasi 16 Sep 2026).
 * Mengunci bahwa agregat dasbor Kesmas tidak memuat populasi anak ke PHP:
 * memori puncak tidak tumbuh sebanding jumlah anak, dan jumlah query tetap (bukan per anak).
 */
class KesmasDashboardMemoriTest extends TestCase
{
    use RefreshDatabase;

    private const JUMLAH_ANAK = 2000;
    private const KUNJUNGAN_PER_ANAK = 6;
    private const BATAS_MB = 16;
    private const BATAS_QUERY = 20;

    protected function setUp(): void
    {
        parent::setUp();
        ImunisasiStatusService::flushCache();
        WilkerPuskesmas::flushCache();
    }

    public function test_agregat_tidak_memuat_seluruh_populasi_dan_jumlah_query_tetap(): void
    {
        $kec = Kecamatan::create(['name' => 'Bontang Utara']);
        $kel = Kelurahan::create(['name' => 'API-API', 'id_kecamatan' => $kec->id]);
        $this->seedMassal(self::JUMLAH_ANAK, $kec->id, $kel->id);

        $svc = app(KesmasDashboardService::class);
        $p   = PeriodeKesmas::dari(2025, 'tahun');

        gc_collect_cycles();
        $memoriAwal = memory_get_usage();
        DB::enableQueryLog();

        $sasaran = $svc->sasaran($p, []);
        $spm     = $svc->spmKohort($p, []);
        $tk      = $svc->pemantauanTk($p, []);
        $sdidtk  = $svc->sdidtk($p, []);
        $ckg     = $svc->ckg($p, []);
        $layanan = $svc->layananLingkungan($p, []);

        $jumlahQuery = count(DB::getQueryLog());
        DB::disableQueryLog();
        $kenaikanMb = (memory_get_peak_usage() - $memoriAwal) / 1048576;

        // Populasi benar-benar diproses, bukan dilewati.
        $this->assertSame(self::JUMLAH_ANAK, $sasaran['semua']);
        $this->assertSame(self::JUMLAH_ANAK, $spm['anak_balita']['sasaran']);
        $this->assertSame(self::JUMLAH_ANAK, $spm['anak_balita']['sub']['timbang']['sasaran']);
        $this->assertSame(self::JUMLAH_ANAK, $tk['sasaran']);
        $this->assertSame(self::JUMLAH_ANAK, $sdidtk['total']['sasaran']);
        $this->assertSame(self::JUMLAH_ANAK, $ckg['kelompok']['t2']['sasaran']);
        $this->assertSame(self::JUMLAH_ANAK, $layanan['sasaran']);
        $this->assertSame(self::JUMLAH_ANAK, $layanan['layanan']['baris']['kn1']['terisi']);

        $this->assertLessThan(self::BATAS_MB, $kenaikanMb,
            sprintf('Memori puncak naik %.1f MB untuk %d anak — agregat memuat populasi ke PHP.', $kenaikanMb, self::JUMLAH_ANAK));
        $this->assertLessThanOrEqual(self::BATAS_QUERY, $jumlahQuery,
            sprintf('%d query untuk enam agregat — ada query per anak/per kelompok yang seharusnya digabung.', $jumlahQuery));
    }

    public function test_registri_hanya_memuat_satu_halaman(): void
    {
        $kec = Kecamatan::create(['name' => 'Bontang Utara']);
        $kel = Kelurahan::create(['name' => 'API-API', 'id_kecamatan' => $kec->id]);
        $this->seed(\Database\Seeders\JenisVaksinSeeder::class);
        $this->seed(\Database\Seeders\KelompokVaksinSeeder::class);
        $this->seedMassal(self::JUMLAH_ANAK, $kec->id, $kel->id);

        gc_collect_cycles();
        $memoriAwal = memory_get_usage();

        $r = app(KesmasDashboardService::class)->registri(PeriodeKesmas::dari(2025, 'tahun'), [], 'semua', '', 'semua', 3);

        $kenaikanMb = (memory_get_peak_usage() - $memoriAwal) / 1048576;
        $this->assertSame(self::JUMLAH_ANAK, $r['total']);
        $this->assertCount(20, $r['data']);
        $this->assertSame(41, $r['data'][0]['no']);
        $this->assertLessThan(self::BATAS_MB, $kenaikanMb, sprintf('Registri naik %.1f MB — memuat lebih dari satu halaman.', $kenaikanMb));
    }

    /** Anak 30 bln (lahir 30 Jun 2023) dengan 6 kunjungan bulanan 2025 (kn1 terisi, ddtka di 2 kunjungan), insert massal. */
    private function seedMassal(int $jumlah, int $idKec, int $idKel): void
    {
        $now = now()->toDateTimeString();
        foreach (array_chunk(range(1, $jumlah), 500) as $potongan) {
            $anak = [];
            foreach ($potongan as $i) {
                $anak[] = [
                    'nama' => 'Anak Massal ' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                    'nik' => '3' . str_pad((string) $i, 15, '0', STR_PAD_LEFT), 'jk' => 1, 'tempat_lahir' => 'Bontang',
                    'tgl_lahir' => '2023-06-30', 'status' => 1, 'no' => '1', 'sumber' => 'manual',
                    'id_kec' => $idKec, 'id_kel' => $idKel, 'created_at' => $now, 'updated_at' => $now,
                ];
            }
            DB::table('anak')->insert($anak);
        }

        $ids = DB::table('anak')->where('nama', 'like', 'Anak Massal %')->pluck('id');
        foreach ($ids->chunk(250) as $potongan) {
            $rows = [];
            foreach ($potongan as $idAnak) {
                for ($b = 1; $b <= self::KUNJUNGAN_PER_ANAK; $b++) {
                    $rows[] = [
                        'id_anak' => $idAnak, 'tgl_kunjungan' => sprintf('2025-%02d-10', $b), 'bln' => 24 + $b, 'posisi' => 'B',
                        'tb' => 85, 'bb' => 11, 'lla' => 14, 'lk' => 47, 'id_user' => 1, 'sumber' => 'manual',
                        'ddtka' => $b <= 2 ? 'Sesuai' : null, 'kn1' => 1, 'vit_a' => $b === 2 ? 1 : 0,
                        'created_at' => $now, 'updated_at' => $now,
                    ];
                }
            }
            DB::table('data_anak')->insert($rows);
        }
    }
}
```

- [ ] **Step 2: Tes gotcha Blade**

```php
<?php
// tests/Feature/Kesmas/KesmasDashboardBladeTest.php

namespace Tests\Feature\Kesmas;

use Tests\TestCase;

/**
 * Mengunci jebakan yang pernah memakan waktu di proyek ini (lihat CLAUDE.md):
 * @section satu baris tanpa spasi, atribut Bootstrap 5, jQuery :hidden pada <option>,
 * label tanpa pasangan id, dan wilayah aria-live untuk tabel yang dimuat JS.
 */
class KesmasDashboardBladeTest extends TestCase
{
    private const VIEWS = [
        'resources/views/admin/kesmas/dashboard.blade.php',
        'resources/views/admin/kesmas/partials/_filter.blade.php',
        'resources/views/admin/kesmas/partials/_registri.blade.php',
    ];

    private function semua(): string
    {
        return implode("\n", array_map(fn ($v) => file_get_contents(base_path($v)), self::VIEWS));
    }

    public function test_section_satu_baris_selalu_berspasi(): void
    {
        $this->assertDoesNotMatchRegularExpression("/@section\\('[a-z-]+'\\)\\S.*@endsection/", $this->semua(),
            "@section('x')isi@endsection tanpa spasi membuat @endsection tidak diparse (lihat CLAUDE.md).");
    }

    public function test_tanpa_atribut_bootstrap_5_dan_jquery_hidden_pada_option(): void
    {
        $html = $this->semua();
        $this->assertStringNotContainsString('data-bs-', $html, 'Proyek memakai Bootstrap 4 (data-toggle).');
        $this->assertDoesNotMatchRegularExpression("/\.is\('(:hidden|:visible)'\)|option:(hidden|visible)/", $html, '<option> tidak punya box model — cek validitas dari data-kec.');
    }

    public function test_setiap_label_for_punya_id_pasangan(): void
    {
        $html = $this->semua();
        preg_match_all('/<label for="([^"]+)"/', $html, $labels);
        $this->assertNotEmpty($labels[1]);
        foreach ($labels[1] as $id) {
            $this->assertMatchesRegularExpression('/\bid="' . preg_quote($id, '/') . '"/', $html, "label for=\"{$id}\" tanpa kontrol ber-id yang sama");
        }
        foreach (['filterTahun', 'filterPeriode', 'filterKec', 'filterKel', 'filterRt', 'filterPos', 'filterPkm', 'registriCari', 'registriGizi'] as $id) {
            $this->assertContains($id, $labels[1], "Kontrol #{$id} harus punya <label for>");
        }
    }

    public function test_tabel_registri_aria_live_dan_tanpa_required(): void
    {
        $html = $this->semua();
        $this->assertStringContainsString('id="registriBody" aria-live="polite"', $html);
        $this->assertStringNotContainsString(' required', $html, 'Filter GET tidak boleh punya required (tak ada partial validasi accordion di sini).');
    }

    public function test_blok_ditutup_komentar_penanda(): void
    {
        $html = $this->semua();
        foreach (['spm-balita', 'spm-bayi', 'spm-anak-balita', 'spm-tk', 'sdidtk', 'ckg', 'idl', 'layanan', 'registri'] as $nama) {
            $this->assertStringContainsString('data-blok="' . $nama . '"', $html);
            $this->assertStringContainsString('<!-- /' . $nama . ' -->', $html, "Blok {$nama} harus ditutup komentar penanda (dipakai tes blok())");
        }
    }
}
```

- [ ] **Step 3: Jalankan kedua tes**

Run: `php artisan test --filter="KesmasDashboardMemoriTest|KesmasDashboardBladeTest"`
Expected: PASS. Bila `BATAS_QUERY` terlampaui: hitung per method — `sasaran` 1, `spmKohort` 1, `pemantauanTk` 2, `sdidtk` 1, `ckg` 3, `layananLingkungan` 3 = 11 (+ query `Puskesmas` bila filter puskesmas) — cari loop yang memanggil DB per kelompok.

- [ ] **Step 4: Seluruh tes Kesmas + tes yang tersentuh refactor**

Run: `php artisan test --filter="Kesmas|PeriodeKesmas|ImunisasiRutinDashboardServiceTest|ImunisasiDashboardControllerTest|ImunisasiDashboardMemoriTest"`
Expected: PASS semua.

- [ ] **Step 5: Verifikasi browser (wajib sebelum commit akhir)**

`php artisan view:clear`, lalu di Chrome (login `dinkes@sirindu.go.id` / `Sirindu@2026`):
1. `/admin/imunisasi-dashboard` — tampilan tak berubah (CSS dasar dari berkas terpisah).
2. `/admin/kesmas-dashboard` — tiap periode (`tahun`, `s1`, `tw3`) mengubah label kepala & syarat di kartu; Reset muncul saat filter aktif.
3. Cascade Kecamatan → Kelurahan → RT; pilih kelurahan lalu Terapkan → kelurahan tetap terpilih setelah reload.
4. Registri: cari, status gizi, chip usia (URL `?usia=`), paginasi, tautan "perlu perhatian".
5. Lebar 375 px: kartu 1 kolom, tabel scroll horizontal, tidak ada scroll horizontal halaman.
6. DevTools Console: tanpa error; Network: `api/registri` 200.
7. Menu sidebar Dashboard → Kesmas aktif (hijau) di halaman ini.

- [ ] **Step 6: Commit**

```bash
git add tests/Feature/Kesmas/KesmasDashboardMemoriTest.php tests/Feature/Kesmas/KesmasDashboardBladeTest.php
git commit -m "test(kesmas): kunci memori/jumlah query agregat dasbor dan gotcha Blade"
```

- [ ] **Step 7: Catatan deploy** — tambahkan ke pesan akhir untuk pemilik produk: prod perlu `git pull`, `php artisan view:clear`, dan migrasi Spec 1 (`2026_09_22_000001`) sudah harus `Ran` (`php artisan migrate:status | grep 2026_09_22`) sebelum halaman ini dibuka; tidak ada migrasi baru di Spec 2.

---

## Self-review (dilakukan saat menulis plan)

- **Cakupan spec**: §2.1 filter → Task 8; §2.2 → Task 1; §2.3–2.4 → Task 3; §3 K1–K4 → Task 3–4; SDIDTK/CKG → Task 4; IDL/IBL → Task 2 + 9; Layanan & Lingkungan → Task 5 + 10; §3.1 registri → Task 6 + 11; §4.1–4.3 → Task 2, 7, 8; §4.4 performa → Task 12; §5 tampilan → Task 8–11; §6 galat → Task 8 (422/403), Task 11 (fetch gagal), Task 9 ("—"); §7 tes → Task 1–12; §8 di luar lingkup → tidak ada task (tombol mockup, PDF, WhatsApp).
- **Placeholder**: tidak ada TBD/TODO; setiap step berisi kode.
- **Konsistensi nama**: `sasaran/spmKohort/pemantauanTk/sdidtk/ckg/layananLingkungan/registri/kategoriGizi/persen`; konstanta `KELOMPOK/USIA/STATUS_GIZI`; blok `spm-balita/spm-bayi/spm-anak-balita/spm-tk/sdidtk/ckg/idl/layanan/registri`; id `filterTahun/filterPeriode/filterKec/filterKel/filterRt/filterPos/filterPkm/filterUsia/registriBody/registriCari/registriGizi/registriInfo/registriPag/registriTotal/idlDonut/sdidtkRows` — sama di view, JS, dan tes.

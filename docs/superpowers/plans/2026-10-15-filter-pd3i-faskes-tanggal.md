# Filter Dasbor PD3I: Faskes Pelapor & Rentang Tanggal — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tambah filter Faskes Pelapor (multiselect `instansi_pelapor`) dan rentang Tanggal Laporan (di dalam Tahun) ke dasbor PD3I beserta ekspor Excel/PDF.

**Architecture:** Value object `App\Support\FilterPd3i` membaca, menormalkan, dan menerapkan seluruh filter. Repository, controller, dan `SurveillanceExport` menerimanya sebagai ganti 5 argumen berurutan. Tren 5 tahunan/epiweek memakai `terapkanTanpaWaktu()`.

**Tech Stack:** Laravel 12, PHPUnit, Maatwebsite Excel, barryvdh/dompdf, Blade + vanilla JS.

**Spec:** `docs/superpowers/specs/2026-10-15-filter-pd3i-faskes-tanggal-design.md`

## Global Constraints

- Rentang tanggal selalu di dalam `tahun`. Tanggal di luar tahun atau tak valid berarti `null` (tidak digeser). "dari" > "s/d" berarti ditukar.
- Tren tahunan & epiweek: faskes berlaku, rentang tidak.
- Penyebut per 100.000 tetap penduduk `tahun` terpilih.
- `FilterPd3i` tanpa `config()`; `now()` hanya di `dariRequest()`. Unit test memakai `PHPUnit\Framework\TestCase` polos.
- `SurveillanceExport` tetap memasang `visibleTo(auth()->user())` lebih dulu; konstruktor bernama lama tidak berubah.
- Catatan "Grafik ini tidak mengikuti rentang tanggal laporan." selalu terlihat (bukan tooltip) saat rentang aktif.
- Jangan jalankan dua proses PHPUnit bersamaan (DB `sirindu_testing` dipakai bersama).
- Edit file PHP dengan tool Edit/Write, bukan heredoc bash (backslash namespace rusak).

## Review Focus

- URL diketik tangan dengan `tanggal_dari=2026-02-30` / `15/03/2026` / tanggal tahun lain berarti diabaikan, bukan 500 atau query kosong. Dikunci unit test + satu tes HTTP (Task 1, Task 2).
- `per_faskes`/`per_kecamatan` me-`leftJoin` tabel lain: kolom filter wajib berprefix `surveillance_cases.`, jika tidak "ambiguous column". Dikunci tes tren dengan rentang + faskes (Task 2).
- Ganti Tahun setelah memilih tanggal: tanggal harus dikosongkan SEBELUM fetch, jika tidak request membawa tanggal tahun lama (server mengabaikannya, tapi input tetap tampil menyesatkan). Urutan listener diatur di Task 4; hanya bisa diverifikasi di browser.
- Akun faskes (RS) mengekspor Excel dengan filter faskes lain: tetap hanya kasusnya sendiri. Dikunci Task 3.
- `instansi_pelapor` kosong/NULL tidak muncul sebagai opsi kosong. Dikunci Task 4.

---

### Task 1: Value object `FilterPd3i`

**Files:**
- Create: `app/Support/FilterPd3i.php`
- Test: `tests/Unit/Support/FilterPd3iTest.php`

**Interfaces:**
- Produces: `new FilterPd3i(int $tahun, ?int $jenisKasusId = null, ?array $wilker = null, ?array $kelurahanId = null, ?array $kabKota = null, ?array $faskesPelapor = null, ?string $tanggalDari = null, ?string $tanggalSampai = null)`; readonly props of the same names; `static dariRequest(Request): self`; `terapkan(Eloquent\Builder): void`; `terapkanTanpaWaktu(Eloquent\Builder): void`; `adaRentang(): bool`; `labelPeriode(): string`.

- [ ] **Step 1: Write the failing test** — `tests/Unit/Support/FilterPd3iTest.php`

```php
<?php

namespace Tests\Unit\Support;

use App\Support\FilterPd3i;
use PHPUnit\Framework\TestCase;

class FilterPd3iTest extends TestCase
{
    public function test_tanpa_tanggal_tidak_ada_rentang(): void
    {
        $f = new FilterPd3i(tahun: 2026);
        $this->assertFalse($f->adaRentang());
        $this->assertNull($f->tanggalDari);
        $this->assertNull($f->tanggalSampai);
        $this->assertSame('Tahun 2026', $f->labelPeriode());
    }

    public function test_hanya_dari_terisi(): void
    {
        $f = new FilterPd3i(tahun: 2026, tanggalDari: '2026-03-01');
        $this->assertTrue($f->adaRentang());
        $this->assertSame('2026-03-01', $f->tanggalDari);
        $this->assertNull($f->tanggalSampai);
        $this->assertSame('1 Mar 2026 – 31 Des 2026', $f->labelPeriode());
    }

    public function test_hanya_sampai_terisi(): void
    {
        $f = new FilterPd3i(tahun: 2026, tanggalSampai: '2026-06-30');
        $this->assertSame('1 Jan 2026 – 30 Jun 2026', $f->labelPeriode());
    }

    public function test_dari_lebih_besar_ditukar(): void
    {
        $f = new FilterPd3i(tahun: 2026, tanggalDari: '2026-06-30', tanggalSampai: '2026-05-01');
        $this->assertSame('2026-05-01', $f->tanggalDari);
        $this->assertSame('2026-06-30', $f->tanggalSampai);
        $this->assertSame('1 Mei 2026 – 30 Jun 2026', $f->labelPeriode());
    }

    public function test_tanggal_di_luar_tahun_diabaikan(): void
    {
        $f = new FilterPd3i(tahun: 2026, tanggalDari: '2025-12-31', tanggalSampai: '2027-01-01');
        $this->assertNull($f->tanggalDari);
        $this->assertNull($f->tanggalSampai);
        $this->assertFalse($f->adaRentang());
    }

    public function test_format_salah_diabaikan(): void
    {
        foreach (['15/03/2026', '2026-02-30', '2026-3-1', 'x', ''] as $nilai) {
            $f = new FilterPd3i(tahun: 2026, tanggalDari: $nilai);
            $this->assertNull($f->tanggalDari, "nilai '{$nilai}' harus diabaikan");
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Support/FilterPd3iTest.php`
Expected: FAIL, `Class "App\Support\FilterPd3i" not found`.

- [ ] **Step 3: Write implementation** — `app/Support/FilterPd3i.php`

```php
<?php

namespace App\Support;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filter Dasbor PD3I — satu tempat untuk membaca, menormalkan, dan menerapkan
 * filter (spec docs/superpowers/specs/2026-10-15-filter-pd3i-faskes-tanggal-design.md).
 *
 * Rentang tanggal lapor selalu DI DALAM $tahun: tanggal di luar tahun atau tak
 * valid dianggap kosong (tidak digeser ke batas tahun), "dari" > "s/d" ditukar.
 * Tanpa config(); now() hanya di dariRequest() — supaya bisa diuji dengan
 * TestCase polos.
 */
final class FilterPd3i
{
    private const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    public readonly ?string $tanggalDari;
    public readonly ?string $tanggalSampai;

    public function __construct(
        public readonly int $tahun,
        public readonly ?int $jenisKasusId = null,
        public readonly ?array $wilker = null,
        public readonly ?array $kelurahanId = null,
        public readonly ?array $kabKota = null,
        public readonly ?array $faskesPelapor = null,
        ?string $tanggalDari = null,
        ?string $tanggalSampai = null,
    ) {
        $dari   = $this->tanggalDalamTahun($tanggalDari);
        $sampai = $this->tanggalDalamTahun($tanggalSampai);

        if ($dari !== null && $sampai !== null && $dari > $sampai) {
            [$dari, $sampai] = [$sampai, $dari];
        }

        $this->tanggalDari   = $dari;
        $this->tanggalSampai = $sampai;
    }

    public static function dariRequest(Request $request): self
    {
        $teks = fn (string $k) => is_string($request->input($k)) ? $request->input($k) : null;

        return new self(
            tahun:         (int) $request->get('tahun', now()->year),
            jenisKasusId:  $request->filled('jenis_kasus_id') ? (int) $request->input('jenis_kasus_id') : null,
            wilker:        self::multi($request->input('wilker')),
            kelurahanId:   self::multi($request->input('kelurahan_id'), true),
            kabKota:       self::multi($request->input('kab_kota')),
            faskesPelapor: self::multi($request->input('faskes_pelapor')),
            tanggalDari:   $teks('tanggal_dari'),
            tanggalSampai: $teks('tanggal_sampai'),
        );
    }

    /** Tahun + rentang tanggal lapor + seluruh filter non-waktu. */
    public function terapkan(Builder $query): void
    {
        $query->whereYear('surveillance_cases.tanggal_lapor', $this->tahun);

        // tanggal_lapor bertipe DATE → pembanding langsung (tetap bisa memakai indeks).
        if ($this->tanggalDari !== null) {
            $query->where('surveillance_cases.tanggal_lapor', '>=', $this->tanggalDari);
        }
        if ($this->tanggalSampai !== null) {
            $query->where('surveillance_cases.tanggal_lapor', '<=', $this->tanggalSampai);
        }

        $this->terapkanTanpaWaktu($query);
    }

    /**
     * Filter non-waktu saja — untuk tren 5 tahunan & epiweek yang memasang batas
     * waktunya sendiri (lintas tahun, jadi rentang di dalam tahun tak bermakna).
     * Kolom berprefix tabel: beberapa query me-leftJoin rumah_sakits/kecamatan/kelurahan.
     */
    public function terapkanTanpaWaktu(Builder $query): void
    {
        if ($this->jenisKasusId) {
            $query->where('surveillance_cases.id_jenis_kasus', $this->jenisKasusId);
        }
        if (!empty($this->kabKota)) {
            $query->whereIn('surveillance_cases.kab_kota', $this->kabKota);
        }
        if (!empty($this->wilker)) {
            $query->whereIn('surveillance_cases.wilker_puskesmas', $this->wilker);
        }
        if (!empty($this->kelurahanId)) {
            $query->whereIn('surveillance_cases.id_kel', $this->kelurahanId);
        }
        if (!empty($this->faskesPelapor)) {
            $query->whereIn('surveillance_cases.instansi_pelapor', $this->faskesPelapor);
        }
    }

    public function adaRentang(): bool
    {
        return $this->tanggalDari !== null || $this->tanggalSampai !== null;
    }

    /** "Tahun 2026" atau "1 Mar 2026 – 30 Jun 2026" (ujung kosong = batas tahun). */
    public function labelPeriode(): string
    {
        if (!$this->adaRentang()) {
            return 'Tahun ' . $this->tahun;
        }

        return $this->formatTanggal($this->tanggalDari ?? "{$this->tahun}-01-01")
            . ' – '
            . $this->formatTanggal($this->tanggalSampai ?? "{$this->tahun}-12-31");
    }

    private function formatTanggal(string $ymd): string
    {
        [$y, $m, $d] = explode('-', $ymd);

        return (int) $d . ' ' . self::BULAN[(int) $m - 1] . ' ' . $y;
    }

    private function tanggalDalamTahun(?string $nilai): ?string
    {
        $nilai = $nilai === null ? '' : trim($nilai);
        if ($nilai === '') {
            return null;
        }

        // '!' mereset field lain; format ulang harus identik → menolak 2026-02-30 & 2026-3-1.
        $tanggal = DateTimeImmutable::createFromFormat('!Y-m-d', $nilai);
        if ($tanggal === false || $tanggal->format('Y-m-d') !== $nilai) {
            return null;
        }

        return (int) $tanggal->format('Y') === $this->tahun ? $nilai : null;
    }

    /** Normalisasi multiselect (name[]=… atau skalar) → array bersih, atau null bila kosong. */
    private static function multi($value, bool $asInt = false): ?array
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        $items = is_array($value) ? $value : [$value];
        $items = array_filter($items, fn ($v) => $v !== null && $v !== '');

        if (empty($items)) {
            return null;
        }

        $items = $asInt
            ? array_map('intval', $items)
            : array_map(fn ($v) => trim((string) $v), $items);

        return array_values(array_unique($items));
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Support/FilterPd3iTest.php`
Expected: PASS, 6 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Support/FilterPd3i.php tests/Unit/Support/FilterPd3iTest.php
git commit -m "feat(pd3i): value object FilterPd3i — faskes pelapor & rentang tanggal di dalam tahun"
```

---

### Task 2: Repository + controller memakai `FilterPd3i`

**Files:**
- Modify: `app/Repositories/Admin/Epidemiologi/SurveillanceRepository.php:575-1100` (pd3iBaseQuery, applyPd3iGeoFilters, 4 getPd3i*)
- Modify: `app/Http/Controllers/Pd3iDashboardController.php` (kinerja/demografi/tren/wilayah/exportPdf, hapus parsePd3iFilters/normalizeMulti; exportExcel tetap dipakai sementara dengan properti filter)
- Modify: `tests/Feature/Epidemiologi/Pd3iKinerjaAfpRateTest.php:56`, `tests/Feature/Epidemiologi/Pd3iLabPercentagesTest.php:59`
- Test: `tests/Feature/Epidemiologi/Pd3iFilterFaskesTanggalTest.php`

**Interfaces:**
- Consumes: `FilterPd3i` (Task 1).
- Produces: `getPd3iKinerja(FilterPd3i $f): array`, `getPd3iDemografi(FilterPd3i $f)`, `getPd3iTren(FilterPd3i $f)`, `getPd3iWilayah(FilterPd3i $f)`; controller helper `private function filter(Request $r): FilterPd3i`.

- [ ] **Step 1: Write the failing test** — `tests/Feature/Epidemiologi/Pd3iFilterFaskesTanggalTest.php`

```php
<?php

namespace Tests\Feature\Epidemiologi;

use App\Models\JenisKasusEpidemiologi;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\SurveillanceCase;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Filter Faskes Pelapor & rentang Tanggal Laporan di Dasbor PD3I
 * (spec 2026-10-15-filter-pd3i-faskes-tanggal-design.md). Nama faskes unik per
 * tes supaya hitungan tak tercampur data lain di DB uji bersama.
 */
class Pd3iFilterFaskesTanggalTest extends TestCase
{
    use DatabaseTransactions;

    private const FASKES_A = 'PKM Uji Filter A';
    private const FASKES_B = 'RS Uji Filter B';

    private User $admin;
    private Kelurahan $kel;
    private Rt $rt;

    protected function setUp(): void
    {
        parent::setUp();

        $kec         = Kecamatan::factory()->create();
        $this->kel   = Kelurahan::factory()->create(['id_kecamatan' => $kec->id]);
        $this->rt    = Rt::factory()->create(['id_kelurahan' => $this->kel->id]);
        $this->admin = User::factory()->create(['type' => 0, 'role' => 'superadmin']);

        // id 1 = Campak-Rubella (di-hardcode di getPd3iKinerja).
        if (!JenisKasusEpidemiologi::find(1)) {
            JenisKasusEpidemiologi::factory()->create([
                'id' => 1, 'kode_penyakit' => 'CAMPAK_RUBELLA', 'nama_penyakit' => 'Campak-Rubella', 'is_active' => true,
            ]);
        }

        // A: Februari & Juni; B: Juni.
        $this->kasus(self::FASKES_A, '2026-02-10');
        $this->kasus(self::FASKES_A, '2026-06-15');
        $this->kasus(self::FASKES_B, '2026-06-20');
    }

    private function kasus(string $instansi, string $tanggal): SurveillanceCase
    {
        return SurveillanceCase::factory()->create([
            'id_jenis_kasus'   => 1,
            'id_kec'           => $this->kel->id_kecamatan,
            'id_kel'           => $this->kel->id,
            'id_rt'            => $this->rt->id,
            'instansi_pelapor' => $instansi,
            'tanggal_lapor'    => $tanggal,
            'tanggal_onset'    => $tanggal,
            'id_petugas_input' => $this->admin->id,
            'created_by'       => $this->admin->id,
            'updated_by'       => $this->admin->id,
        ]);
    }

    private function api(string $rute, array $query): array
    {
        return $this->actingAs($this->admin)
            ->getJson(route($rute, ['tahun' => 2026] + $query))
            ->assertOk()
            ->json();
    }

    public function test_kinerja_hanya_menghitung_faskes_terpilih(): void
    {
        $data = $this->api('admin.pd3i.apiKinerja', ['faskes_pelapor' => [self::FASKES_A]]);
        $this->assertSame(2, $data['campak_rubella']['suspek']);

        $data = $this->api('admin.pd3i.apiKinerja', ['faskes_pelapor' => [self::FASKES_A, self::FASKES_B]]);
        $this->assertSame(3, $data['campak_rubella']['suspek']);
    }

    public function test_kinerja_menghormati_rentang_tanggal(): void
    {
        $data = $this->api('admin.pd3i.apiKinerja', [
            'faskes_pelapor' => [self::FASKES_A],
            'tanggal_dari'   => '2026-05-01',
            'tanggal_sampai' => '2026-06-30',
        ]);
        $this->assertSame(1, $data['campak_rubella']['suspek']);
    }

    public function test_tanggal_dari_url_yang_tak_sah_diabaikan(): void
    {
        foreach (['2025-05-01', '2026-02-30', '01/05/2026'] as $nilai) {
            $data = $this->api('admin.pd3i.apiKinerja', [
                'faskes_pelapor' => [self::FASKES_A],
                'tanggal_dari'   => $nilai,
            ]);
            $this->assertSame(2, $data['campak_rubella']['suspek'], "tanggal_dari={$nilai}");
        }
    }

    public function test_tren_tahunan_dan_epiweek_abaikan_rentang_tapi_ikut_faskes(): void
    {
        $data = $this->api('admin.pd3i.apiTren', [
            'faskes_pelapor' => [self::FASKES_A],
            'tanggal_dari'   => '2026-05-01',
            'tanggal_sampai' => '2026-06-30',
        ]);

        $tahun2026 = collect($data['tahunan'])->firstWhere('tahun', 2026);
        $this->assertSame(2, $tahun2026['total'], 'tahunan tidak dipersempit rentang, tapi hanya faskes A');
        $this->assertSame(2, array_sum(array_column($data['epiweek'], 'suspek')));

        $bulanan = collect($data['bulanan'])->keyBy('bulan');
        $this->assertSame(0, $bulanan[2]['total'], 'Februari di luar rentang');
        $this->assertSame(1, $bulanan[6]['total']);

        // per_faskes me-leftJoin rumah_sakits — kolom filter harus berprefix tabel.
        $perFaskes = collect($data['per_faskes']);
        $this->assertSame(1, $perFaskes->where('faskes', self::FASKES_A)->sum('jumlah'));
        $this->assertSame(0, $perFaskes->where('faskes', self::FASKES_B)->sum('jumlah'));
        $this->assertSame(1, collect($data['per_kecamatan'])->sum('jumlah'));
    }

    public function test_demografi_dan_wilayah_ikut_filter(): void
    {
        $query = ['faskes_pelapor' => [self::FASKES_A], 'tanggal_dari' => '2026-05-01'];

        $demografi = $this->api('admin.pd3i.apiDemografi', $query);
        $this->assertSame(1, array_sum($demografi['jenis_kelamin']));

        $wilayah = $this->api('admin.pd3i.apiWilayah', $query);
        $this->assertSame([self::FASKES_A], array_column($wilayah['per_faskes_pelapor'], 'faskes'));
        $this->assertSame(1, $wilayah['per_faskes_pelapor'][0]['total']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Epidemiologi/Pd3iFilterFaskesTanggalTest.php`
Expected: FAIL. Hitungan faskes 3 ≠ 2 (filter belum diterapkan), dsb.

- [ ] **Step 3: Implement — repository**

Di `SurveillanceRepository.php`:
- Tambah `use App\Support\FilterPd3i;`.
- Ganti `pd3iBaseQuery` + docblock-nya, dan hapus `applyPd3iGeoFilters`:

```php
    /**
     * Base query dasbor PD3I — seluruh filter (tahun, rentang tanggal lapor, jenis,
     * wilayah, faskes pelapor) diterapkan oleh FilterPd3i.
     */
    private function pd3iBaseQuery(FilterPd3i $f): \Illuminate\Database\Eloquent\Builder
    {
        $query = SurveillanceCase::query();
        $f->terapkan($query);

        return $query;
    }
```

- `getPd3iKinerja(FilterPd3i $f): array`: baris pertama jadi `$tahun = $f->tahun;` lalu `$base = $this->pd3iBaseQuery($f);`. Sisanya tidak berubah (JumlahPenduduk tetap `$tahun`).
- `getPd3iTren(FilterPd3i $f): array`: tambahkan `$tahun = $f->tahun;` di awal. Di blok tahunan, ganti dua baris `if ($jenisKasusId) …` + `$this->applyPd3iGeoFilters($tahunBase, …)` dengan `$f->terapkanTanpaWaktu($tahunBase);`. Lakukan hal yang sama di blok epiweek (`$f->terapkanTanpaWaktu($epiBase);`). `$base = $this->pd3iBaseQuery($f);` di blok bulanan.
- `getPd3iDemografi(FilterPd3i $f)` dan `getPd3iWilayah(FilterPd3i $f)`: `$base = $this->pd3iBaseQuery($f);`. Periksa body masing-masing: bila `$tahun`/`$jenisKasusId` dipakai di bawah, definisikan dari `$f`.
- Verifikasi tak ada sisa: `grep -n "applyPd3iGeoFilters\|\$jenisKasusId\|\$kabKota" app/Repositories/Admin/Epidemiologi/SurveillanceRepository.php` hanya boleh memunculkan baris di luar method PD3I.

- [ ] **Step 4: Implement — controller**

Di `Pd3iDashboardController.php`:
- Tambah `use App\Support\FilterPd3i;`.
- Empat endpoint API menjadi (contoh kinerja, ulangi untuk demografi/tren/wilayah):

```php
    public function kinerja(Request $request): JsonResponse
    {
        return response()->json($this->repo->getPd3iKinerja(FilterPd3i::dariRequest($request)));
    }
```

- `exportPdf`: `$f = FilterPd3i::dariRequest($request);` lalu keempat panggilan repo memakai `$f`. Ganti `$filters['…']` dengan `$f->jenisKasusId`, `$f->kabKota`, `$f->wilker`, `$f->kelurahanId`, `$f->tahun`. Tambah ke data view:

```php
            'periode'        => $f->labelPeriode(),
            'namaFaskes'     => $f->faskesPelapor ? implode(', ', $f->faskesPelapor) : null,
```

- `exportExcel` sementara: `$f = FilterPd3i::dariRequest($request);` dan `new SurveillanceExport($f->tahun, $f->jenisKasusId, $f->wilker, $f->kelurahanId, $f->kabKota)` dengan nama file dari `$f->tahun`/`$f->wilker` (Task 3 menggantinya).
- Hapus `parsePd3iFilters()` dan `normalizeMulti()`.

- [ ] **Step 5: Update tes lama**

`Pd3iKinerjaAfpRateTest.php:56` → `->getPd3iKinerja(new FilterPd3i(tahun: 2026));` + `use App\Support\FilterPd3i;`.
`Pd3iLabPercentagesTest.php:59` → sama.

- [ ] **Step 6: Run tests**

Run: `php artisan test tests/Feature/Epidemiologi/Pd3iFilterFaskesTanggalTest.php tests/Feature/Epidemiologi/Pd3iKinerjaAfpRateTest.php tests/Feature/Epidemiologi/Pd3iLabPercentagesTest.php tests/Unit/Support/FilterPd3iTest.php`
Expected: PASS semua.

- [ ] **Step 7: Commit**

```bash
git add app/Repositories/Admin/Epidemiologi/SurveillanceRepository.php app/Http/Controllers/Pd3iDashboardController.php tests/Feature/Epidemiologi/Pd3iFilterFaskesTanggalTest.php tests/Feature/Epidemiologi/Pd3iKinerjaAfpRateTest.php tests/Feature/Epidemiologi/Pd3iLabPercentagesTest.php
git commit -m "feat(pd3i): dasbor PD3I memakai FilterPd3i — filter faskes pelapor & rentang tanggal"
```

---

### Task 3: Ekspor Excel dari dasbor menghormati filter baru

**Files:**
- Modify: `app/Exports/SurveillanceExport.php:24-74`
- Modify: `app/Http/Controllers/Pd3iDashboardController.php` (exportExcel)
- Test: `tests/Feature/Epidemiologi/Pd3iFilterExportTest.php`

**Interfaces:**
- Consumes: `FilterPd3i::terapkan()` (Task 1), `FilterPd3i::dariRequest()`.
- Produces: `SurveillanceExport::untukDasborPd3i(FilterPd3i $f): self`.

- [ ] **Step 1: Write the failing test** — `tests/Feature/Epidemiologi/Pd3iFilterExportTest.php`

```php
<?php

namespace Tests\Feature\Epidemiologi;

use App\Exports\SurveillanceExport;
use App\Models\JenisKasusEpidemiologi;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\RumahSakit;
use App\Models\SurveillanceCase;
use App\Models\User;
use App\Support\FilterPd3i;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class Pd3iFilterExportTest extends TestCase
{
    use DatabaseTransactions;

    private const FASKES = 'PKM Uji Ekspor Filter';

    private User $superadmin;
    private User $userRs;

    protected function setUp(): void
    {
        parent::setUp();

        $kec  = Kecamatan::factory()->create();
        $kel  = Kelurahan::factory()->create(['id_kecamatan' => $kec->id]);
        $rt   = Rt::factory()->create(['id_kelurahan' => $kel->id]);
        $rs   = RumahSakit::create(['name' => 'RS Uji Ekspor', 'kode_rs' => 'RS-EKS', 'is_active' => true]);
        $jenis = JenisKasusEpidemiologi::factory()->create(['kode_penyakit' => 'UJI_EKS', 'nama_penyakit' => 'Uji Ekspor', 'is_active' => true]);

        $this->superadmin = User::factory()->create(['type' => 0, 'role' => 'superadmin']);
        $this->userRs     = User::factory()->create([
            'type' => 1, 'role' => 'surveilans_rs', 'faskes_type' => 'rs', 'id_rs' => $rs->id,
        ]);

        $kasus = fn (string $tgl, string $instansi, array $faskes) => SurveillanceCase::factory()->create([
            'id_jenis_kasus' => $jenis->id, 'id_kec' => $kec->id, 'id_kel' => $kel->id, 'id_rt' => $rt->id,
            'instansi_pelapor' => $instansi, 'tanggal_lapor' => $tgl, 'tanggal_onset' => $tgl,
            'created_by' => $this->superadmin->id, 'updated_by' => $this->superadmin->id,
        ] + $faskes);

        // Faskes uji: Feb (bukan RS), Jun (milik RS), Jun (bukan RS). Faskes lain: Jun.
        $kasus('2026-02-10', self::FASKES, ['faskes_type' => 'puskesmas', 'id_faskes' => 999999]);
        $kasus('2026-06-15', self::FASKES, ['faskes_type' => 'rs', 'id_faskes' => $rs->id]);
        $kasus('2026-06-16', self::FASKES, ['faskes_type' => 'puskesmas', 'id_faskes' => 999999]);
        $kasus('2026-06-17', 'Faskes Lain Uji', ['faskes_type' => 'puskesmas', 'id_faskes' => 999999]);
    }

    private function filterJuni(): FilterPd3i
    {
        return new FilterPd3i(tahun: 2026, faskesPelapor: [self::FASKES], tanggalDari: '2026-05-01', tanggalSampai: '2026-06-30');
    }

    public function test_ekspor_menghormati_faskes_dan_rentang(): void
    {
        $this->actingAs($this->superadmin);
        $this->assertSame(2, SurveillanceExport::untukDasborPd3i($this->filterJuni())->query()->count());
    }

    public function test_akun_rs_tetap_hanya_melihat_kasusnya_sendiri(): void
    {
        $this->actingAs($this->userRs);
        $this->assertSame(1, SurveillanceExport::untukDasborPd3i($this->filterJuni())->query()->count());
    }

    public function test_tombol_excel_dasbor_meneruskan_filter_baru(): void
    {
        Excel::fake();

        $this->actingAs($this->superadmin)->get(route('admin.pd3i.exportExcel', [
            'tahun' => 2026, 'faskes_pelapor' => [self::FASKES],
            'tanggal_dari' => '2026-05-01', 'tanggal_sampai' => '2026-06-30',
        ]))->assertOk();

        Excel::assertDownloaded('surveilans-pd3i-2026.xlsx', fn (SurveillanceExport $e) => $e->query()->count() === 2);
    }
}
```

Catatan untuk implementer: periksa `SurveillanceCase::scopeVisibleTo()` untuk akun RS. Bila cakupannya diturunkan dari kolom selain `faskes_type`/`id_faskes` (mis. `id_petugas_input`), sesuaikan fixture kasus "milik RS" agar mengikuti aturan scope itu, lalu catat sebagai Ruling. Pola acuannya `ExportScopingFaskesTest::kasus()`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Epidemiologi/Pd3iFilterExportTest.php`
Expected: FAIL, `Call to undefined method …SurveillanceExport::untukDasborPd3i()`.

- [ ] **Step 3: Implement**

Di `SurveillanceExport.php`: tambah `use App\Support\FilterPd3i;`, properti, dan factory setelah konstruktor:

```php
    /** Filter Dasbor PD3I — bila ada, menggantikan filter tahun/jenis/wilayah konstruktor. */
    private ?FilterPd3i $filterPd3i = null;

    public static function untukDasborPd3i(FilterPd3i $filter): self
    {
        $export = new self(tahun: $filter->tahun);
        $export->filterPd3i = $filter;

        return $export;
    }
```

Di `baseQuery()`, setelah `$q = SurveillanceCase::query()->visibleTo(auth()->user());`:

```php
        if ($this->filterPd3i !== null) {
            $this->filterPd3i->terapkan($q);

            return $q;
        }
```

Di controller `exportExcel`:

```php
    public function exportExcel(Request $request)
    {
        $f = FilterPd3i::dariRequest($request);

        $namaFile = 'surveilans-pd3i-' . $f->tahun;
        if ($f->wilker) {
            $namaFile .= '-' . str_replace(' ', '_', implode('-', $f->wilker));
        }
        $namaFile .= '.xlsx';

        return Excel::download(SurveillanceExport::untukDasborPd3i($f), $namaFile);
    }
```

- [ ] **Step 4: Run tests**

Run: `php artisan test tests/Feature/Epidemiologi/Pd3iFilterExportTest.php tests/Feature/Epidemiologi/ExportScopingFaskesTest.php`
Expected: PASS semua.

- [ ] **Step 5: Commit**

```bash
git add app/Exports/SurveillanceExport.php app/Http/Controllers/Pd3iDashboardController.php tests/Feature/Epidemiologi/Pd3iFilterExportTest.php
git commit -m "feat(pd3i): ekspor Excel dasbor ikut filter faskes pelapor & rentang tanggal"
```

---

### Task 4: Tampilan filter, opsi faskes, header PDF, catatan

**Files:**
- Modify: `app/Http/Controllers/Pd3iDashboardController.php` (index)
- Modify: `resources/views/admin/epidemiologi/pd3i-dashboard.blade.php` (filter bar ±333-401, kartu tren ±679-697, buildParams ±902-939, listener ±1652-1657, DOMContentLoaded)
- Modify: `resources/views/admin/epidemiologi/pdf/pd3i-dashboard.blade.php:204-208, 525`
- Modify: `CLAUDE.md` (catatan singkat)
- Test: `tests/Feature/Epidemiologi/Pd3iFilterTampilanTest.php`

**Interfaces:**
- Consumes: `FilterPd3i::labelPeriode()`, view var `periode`, `namaFaskes` (Task 2), repo methods (Task 2).
- Produces: view var `faskesPelapors` (Collection of string); DOM ids `filter-tgl-dari`, `filter-tgl-sampai`; `.ms-dropdown[data-ms="faskes_pelapor"]`; `.catatan-rentang`.

- [ ] **Step 1: Write the failing test** — `tests/Feature/Epidemiologi/Pd3iFilterTampilanTest.php`

```php
<?php

namespace Tests\Feature\Epidemiologi;

use App\Models\JenisKasusEpidemiologi;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\SurveillanceCase;
use App\Models\User;
use App\Repositories\Admin\Epidemiologi\SurveillanceRepository;
use App\Support\FilterPd3i;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class Pd3iFilterTampilanTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $kec   = Kecamatan::factory()->create();
        $kel   = Kelurahan::factory()->create(['id_kecamatan' => $kec->id]);
        $rt    = Rt::factory()->create(['id_kelurahan' => $kel->id]);
        $jenis = JenisKasusEpidemiologi::factory()->create(['kode_penyakit' => 'UJI_TAMPIL', 'nama_penyakit' => 'Uji Tampil', 'is_active' => true]);
        $this->admin = User::factory()->create(['type' => 0, 'role' => 'superadmin']);

        foreach (['PKM Uji Tampil', '', null] as $instansi) {
            SurveillanceCase::factory()->create([
                'id_jenis_kasus' => $jenis->id, 'id_kec' => $kec->id, 'id_kel' => $kel->id, 'id_rt' => $rt->id,
                'instansi_pelapor' => $instansi, 'tanggal_lapor' => '2026-06-15',
                'created_by' => $this->admin->id, 'updated_by' => $this->admin->id,
            ]);
        }
    }

    public function test_dasbor_menampilkan_filter_faskes_dan_tanggal(): void
    {
        $this->actingAs($this->admin)->get(route('admin.pd3i.dashboard'))
            ->assertOk()
            ->assertViewHas('faskesPelapors', fn ($c) => $c->contains('PKM Uji Tampil') && !$c->contains('') && !$c->contains(null))
            ->assertSee('data-ms="faskes_pelapor"', false)
            ->assertSee('id="filter-tgl-dari"', false)
            ->assertSee('id="filter-tgl-sampai"', false)
            ->assertSee("params.append('faskes_pelapor[]'", false)
            ->assertSee("params.set('tanggal_dari'", false)
            ->assertSee('Grafik ini tidak mengikuti rentang tanggal laporan.');
    }

    public function test_header_pdf_mencantumkan_periode_dan_faskes(): void
    {
        $repo = app(SurveillanceRepository::class);
        $f    = new FilterPd3i(tahun: 2026, faskesPelapor: ['PKM Uji Tampil'], tanggalDari: '2026-05-01', tanggalSampai: '2026-06-30');

        $html = view('admin.epidemiologi.pdf.pd3i-dashboard', [
            'tahun' => 2026, 'wilker' => null, 'namaKabKota' => null, 'namaKelurahan' => null, 'namaJenisKasus' => null,
            'periode' => $f->labelPeriode(), 'namaFaskes' => 'PKM Uji Tampil',
            'kinerja' => $repo->getPd3iKinerja($f), 'demografi' => $repo->getPd3iDemografi($f),
            'tren' => $repo->getPd3iTren($f), 'wilayah' => $repo->getPd3iWilayah($f),
        ])->render();

        $this->assertStringContainsString('1 Mei 2026 – 30 Jun 2026', $html);
        $this->assertStringContainsString('PKM Uji Tampil', $html);
    }

    public function test_ekspor_pdf_dengan_filter_baru_berhasil(): void
    {
        $this->actingAs($this->admin)->post(route('admin.pd3i.exportPdf'), [
            'tahun' => 2026, 'faskes_pelapor' => ['PKM Uji Tampil'],
            'tanggal_dari' => '2026-05-01', 'tanggal_sampai' => '2026-06-30',
        ])->assertOk();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Epidemiologi/Pd3iFilterTampilanTest.php`
Expected: FAIL. `faskesPelapors` tak ada di view; teks periode tak ada di PDF.

- [ ] **Step 3: Implement — controller index**

Setelah `$kabKotas`:

```php
        // Faskes pelapor yang ada di data (instansi_pelapor — id_faskes_pelapor tak pernah terisi)
        $faskesPelapors = SurveillanceCase::whereNotNull('instansi_pelapor')
            ->where('instansi_pelapor', '!=', '')
            ->distinct()
            ->orderBy('instansi_pelapor')
            ->pluck('instansi_pelapor');
```

Tambahkan `'faskesPelapors'` ke `compact(...)`.

- [ ] **Step 4: Implement — dashboard blade**

(a) Di filter bar, setelah `</div>` kolom Kelurahan (sebelum `</div>` penutup `.row`):

```blade
            <div class="col-6 col-md-3">
                <label id="lbl-faskes-pelapor"><i class="fa fa-clinic-medical mr-1"></i>Faskes Pelapor</label>
                <div class="ms-dropdown" data-ms="faskes_pelapor" data-all-label="Semua Faskes">
                    <button type="button" class="form-control ms-toggle" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="lbl-faskes-pelapor">
                        <span class="ms-label">Semua Faskes</span>
                        <i class="fa fa-chevron-down ms-caret" aria-hidden="true"></i>
                    </button>
                    <div class="ms-menu" role="listbox" aria-multiselectable="true">
                        @forelse($faskesPelapors as $fp)
                            <label class="ms-option"><input type="checkbox" value="{{ $fp }}"><span>{{ $fp }}</span></label>
                        @empty
                            <div class="ms-empty">Tidak ada data</div>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <label for="filter-tgl-dari"><i class="fa fa-calendar-day mr-1"></i>Tgl lapor dari</label>
                <input type="date" id="filter-tgl-dari" class="form-control"
                       min="{{ now()->year }}-01-01" max="{{ now()->year }}-12-31">
            </div>
            <div class="col-6 col-md-2">
                <label for="filter-tgl-sampai"><i class="fa fa-calendar-day mr-1"></i>Tgl lapor s/d</label>
                <input type="date" id="filter-tgl-sampai" class="form-control"
                       min="{{ now()->year }}-01-01" max="{{ now()->year }}-12-31">
            </div>
```

(b) Pada kartu tren tahunan dan epiweek, tepat setelah `</div>` wadah `height:220px` dan `height:260px` (masih di dalam `.card-body`):

```blade
                    <p class="catatan-rentang small text-muted mb-0 mt-2" hidden>Grafik ini tidak mengikuti rentang tanggal laporan.</p>
```

(c) `buildParams()`: tambah pembacaan setelah `const kelurahan = …`:

```js
        const faskes    = getMsValues('faskes_pelapor');
        const tglDari   = document.getElementById('filter-tgl-dari').value;
        const tglSampai = document.getElementById('filter-tgl-sampai').value;
```

setelah `kelurahan.forEach(v => params.append('kelurahan_id[]', v));`:

```js
        faskes.forEach(v => params.append('faskes_pelapor[]', v));
        if (tglDari)   params.set('tanggal_dari', tglDari);
        if (tglSampai) params.set('tanggal_sampai', tglSampai);
```

setelah `kelurahan.forEach(v => addHidden('kelurahan_id[]', v));`:

```js
            faskes.forEach(v => addHidden('faskes_pelapor[]', v));
            if (tglDari)   addHidden('tanggal_dari', tglDari);
            if (tglSampai) addHidden('tanggal_sampai', tglSampai);
```

sebelum `// Excel href`:

```js
        // Tren 5 tahunan & epiweek lintas tahun — tak dipersempit rentang (catatan selalu terlihat saat aktif).
        document.querySelectorAll('.catatan-rentang').forEach(el => { el.hidden = !(tglDari || tglSampai); });
```

(d) Ganti blok listener `['filter-tahun','filter-penyakit'].forEach(...)`:

```js
    // Multiselect dropdowns (kab_kota / wilker / kelurahan / faskes_pelapor) trigger fetch via their own change handlers.
    const TGL_IDS = ['filter-tgl-dari', 'filter-tgl-sampai'];

    // Rentang tanggal selalu di dalam Tahun terpilih: batasi pemilih tanggal ke tahun itu.
    function aturBatasTanggal() {
        const t = document.getElementById('filter-tahun').value;
        TGL_IDS.forEach(id => {
            const el = document.getElementById(id);
            el.min = t + '-01-01';
            el.max = t + '-12-31';
        });
    }

    document.getElementById('filter-penyakit')?.addEventListener('change', fetchAllTabs);
    // Ganti Tahun → kosongkan tanggal DULU, baru fetch (buildParams membaca nilai input).
    document.getElementById('filter-tahun')?.addEventListener('change', function () {
        TGL_IDS.forEach(id => { document.getElementById(id).value = ''; });
        aturBatasTanggal();
        fetchAllTabs();
    });
    TGL_IDS.forEach(id => document.getElementById(id)?.addEventListener('change', fetchAllTabs));
```

(e) Di `DOMContentLoaded`, sebelum `buildParams();` tambah `aturBatasTanggal();`.

- [ ] **Step 5: Implement — PDF blade**

Ganti baris 204 `<strong>Tahun:</strong> {{ $tahun }}<br>` dengan:

```blade
            <strong>Periode:</strong> {{ $periode ?? ('Tahun ' . $tahun) }}<br>
```

Setelah baris Kelurahan (208), ubah agar baris Kelurahan diakhiri `<br>` lalu tambah:

```blade
            <strong>Faskes pelapor:</strong> {{ $namaFaskes ?? 'Semua Faskes' }}
```

Footer (525): ganti `Tahun {{ $tahun }}` dengan `{{ $periode ?? ('Tahun ' . $tahun) }}`.

- [ ] **Step 6: CLAUDE.md** — tambah di akhir:

```markdown
### Filter Dasbor PD3I: `FilterPd3i`, rentang di dalam Tahun

Seluruh filter dasbor PD3I (tahun, jenis, kab/kota, wilker, kelurahan, faskes pelapor, rentang tanggal lapor) dibaca dan
diterapkan oleh `App\Support\FilterPd3i` (spec `docs/superpowers/specs/2026-10-15-filter-pd3i-faskes-tanggal-design.md`).
Filter baru ditambah di sana, bukan sebagai argumen berurutan ke repository/export.

- Rentang tanggal **selalu di dalam Tahun**; tanggal di luar tahun/tak valid diabaikan (bukan digeser). Tren 5 tahunan &
  epiweek memakai `terapkanTanpaWaktu()` — lintas tahun, jadi rentang tak berlaku; catatan di bawah grafiknya wajib terlihat.
- "Faskes pelapor" = `instansi_pelapor` (teks bebas). `id_faskes_pelapor` tak pernah terisi.
- Kolom di `terapkan*()` berprefix `surveillance_cases.` karena query tren/wilayah me-`leftJoin` tabel lain.
- `SurveillanceExport::untukDasborPd3i()` untuk tombol Excel dasbor; konstruktor bernama lama tetap untuk halaman
  Surveilans (`tahun: null` = semua tahun).

Dikunci `tests/Unit/Support/FilterPd3iTest.php`, `tests/Feature/Epidemiologi/Pd3iFilter*Test.php`.
```

- [ ] **Step 7: Run tests**

Run: `php artisan view:clear` lalu `php artisan test tests/Feature/Epidemiologi tests/Unit/Support/FilterPd3iTest.php`
Expected: PASS semua.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Pd3iDashboardController.php resources/views/admin/epidemiologi/pd3i-dashboard.blade.php resources/views/admin/epidemiologi/pdf/pd3i-dashboard.blade.php CLAUDE.md tests/Feature/Epidemiologi/Pd3iFilterTampilanTest.php
git commit -m "feat(pd3i): filter Faskes Pelapor & Tgl lapor di dasbor, periode di header PDF"
```

---

## Final verification

- Suite penuh: `php artisan test > <workspace>/suite.txt 2>&1`; kegagalan yang sudah ada di main (KelompokVaksinKosongTest ×2, ExportAnakVerifikasiRtTest, tes memori flaky) dilaporkan apa adanya.
- Browser (bila tersedia): buka `/admin/pd3i-dashboard/`, pilih faskes + tanggal, ganti Tahun (tanggal kosong), cek catatan di tab Tren, unduh PDF.

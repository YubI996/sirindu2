# Verifikasi RT — Tahap 4 (Dasbor Gizi + Export Anak) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Hasil verifikasi RT tampil dan berpengaruh di Dasbor Gizi (`/admin/analytics`): kartu "Verifikasi RT", filter "Status verifikasi RT" (default *Semua* — angka lama tak bergeser), dan kolom Sumber / Verifikasi RT / Reviu di export daftar anak modul Anak. Dasbor & export OT tidak disentuh.

**Architecture:** Satu helper di `VerifikasiRtService` (`ringkasanVerifikasi(?int $idKel)` + `terapkanFilterVerif()`) dipakai oleh `AdminController::analyticsDashboard()` (render awal) dan `analyticsFilterImunisasi()` (AJAX) lewat tiga closure filter yang sudah ada; view menambah satu `<select>` + satu baris kartu + beberapa baris JS. Export Anak: kolom baru di view SQL `alldata` (migrasi recreate) + `AnakExport`.

**Tech Stack:** Laravel 12, MySQL 8 (VIEW), Blade + jQuery, Chart.js (tak diubah), PHPUnit 11.

**Spec:** `docs/superpowers/specs/2026-09-14-verifikasi-rt-design.md` §7, §10 h.

## Global Constraints

- **Dasbor OT (`/admin/timbang-dashboard`) dan export OT (`TimbangDaftarExport`) tidak disentuh.**
- Filter "Status verifikasi RT" **default `''` (Semua)** → semua kueri analytics menghasilkan angka yang sama persis dengan sebelum fitur (dikunci tes).
- Makna nilai filter `verif`: `berdomisili|pindah|meninggal|tidak_dikenal` = `anak.verif_rt_status = X AND anak.verif_rt_reviu = 'disetujui'`; `belum` = `verif_rt_reviu IS NULL OR verif_rt_reviu != 'disetujui'` (belum pernah / menunggu reviu / ditolak).
- Kartu "Verifikasi RT": `berdomisili, pindah, meninggal, tidak_dikenal` (masing-masing `reviu='disetujui'`), `menunggu` (`reviu='diusulkan'`), `belum` (sisanya), `total`. Mengikuti filter kelurahan yang aktif.
- Jalankan PHP lewat path penuh; satu proses PHPUnit; commit lokal di `feat/verifikasi-rt`; patch via Edit tool / skrip scratchpad.

---

## File Structure

| Berkas | Tanggung jawab |
|---|---|
| `app/Services/VerifikasiRtService.php` (modify) | `terapkanFilterVerif()`, `ringkasanVerifikasi()` |
| `app/Http/Controllers/AdminController.php` (modify) | `analyticsDashboard()` + `analyticsFilterImunisasi()` |
| `resources/views/admin/dashboard/analytics.blade.php` (modify) | select filter, kartu, JS |
| `database/migrations/2026_09_18_000001_add_verifikasi_rt_to_alldata_view.php` | recreate VIEW dengan 4 kolom baru |
| `app/Exports/AnakExport.php` (modify) | 3 kolom baru |
| `tests/Feature/VerifikasiRt/AnalyticsVerifikasiRtTest.php`, `ExportAnakVerifikasiRtTest.php` | tes |

---

### Task 1: Helper filter & ringkasan di `VerifikasiRtService`

**Files:**
- Modify: `app/Services/VerifikasiRtService.php`
- Test: `tests/Feature/VerifikasiRt/AnalyticsVerifikasiRtTest.php` (bagian service)

**Interfaces:**
- `public const FILTER_VERIF = ['berdomisili', 'pindah', 'meninggal', 'tidak_dikenal', 'belum']`.
- `terapkanFilterVerif($query, ?string $verif, string $tabel = 'anak'): void` — no-op bila `$verif` kosong/tak dikenal; menambah `where` sesuai makna di atas dengan prefiks tabel (`$tabel.verif_rt_status` …). `$query` boleh Query Builder atau Eloquent Builder.
- `ringkasanVerifikasi(?int $idKel = null): array{total:int, berdomisili:int, pindah:int, meninggal:int, tidak_dikenal:int, menunggu:int, belum:int}` — satu kueri agregat `SUM(CASE …)` atas `anak` (filter `id_kel` bila diisi).

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php
// tests/Feature/VerifikasiRt/AnalyticsVerifikasiRtTest.php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Anak;
use App\Models\Kelurahan;
use App\Models\User;
use App\Services\VerifikasiRtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnalyticsVerifikasiRtTest extends TestCase
{
    use RefreshDatabase;

    private function anak(string $nik, array $o = []): Anak
    {
        return Anak::create(array_merge(['nama' => 'Anak '.$nik, 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'capil'], $o));
    }

    /** Set kolom denormalisasi langsung (tanpa lewat usulan) — cukup untuk menguji filter/ringkasan. */
    private function tandai(Anak $a, ?string $status, ?string $reviu): void
    {
        DB::table('anak')->where('id', $a->id)->update(['verif_rt_status' => $status, 'verif_rt_reviu' => $reviu, 'verif_rt_at' => now()]);
    }

    private function seedCampuran(): array
    {
        $kel = Kelurahan::factory()->create();
        $a = $this->anak('3201000000021001', ['id_kel' => $kel->id]); $this->tandai($a, 'berdomisili', 'disetujui');
        $b = $this->anak('3201000000021002', ['id_kel' => $kel->id]); $this->tandai($b, 'pindah', 'disetujui');
        $c = $this->anak('3201000000021003', ['id_kel' => $kel->id]); $this->tandai($c, 'berdomisili', 'diusulkan');
        $d = $this->anak('3201000000021004', ['id_kel' => $kel->id]); $this->tandai($d, 'meninggal', 'ditolak');
        $e = $this->anak('3201000000021005', ['id_kel' => $kel->id]); // belum pernah
        $f = $this->anak('3201000000021006', ['id_kel' => Kelurahan::factory()->create()->id]); $this->tandai($f, 'berdomisili', 'disetujui');
        return compact('kel', 'a', 'b', 'c', 'd', 'e', 'f');
    }

    public function test_filter_verif_berdomisili_hanya_yang_disetujui(): void
    {
        $s = $this->seedCampuran();
        $svc = app(VerifikasiRtService::class);

        $q = DB::table('anak'); $svc->terapkanFilterVerif($q, 'berdomisili');
        $this->assertSame([$s['a']->id, $s['f']->id], $q->orderBy('id')->pluck('id')->all());

        $q = DB::table('anak'); $svc->terapkanFilterVerif($q, 'belum');
        $this->assertSame([$s['c']->id, $s['d']->id, $s['e']->id], $q->orderBy('id')->pluck('id')->all());

        $q = DB::table('anak'); $svc->terapkanFilterVerif($q, '');
        $this->assertSame(6, $q->count(), 'kosong = Semua');

        $q = DB::table('anak'); $svc->terapkanFilterVerif($q, 'ngawur');
        $this->assertSame(6, $q->count(), 'nilai tak dikenal diabaikan');
    }

    public function test_ringkasan_verifikasi_per_kelurahan(): void
    {
        $s = $this->seedCampuran();
        $svc = app(VerifikasiRtService::class);

        $this->assertSame(
            ['total' => 6, 'berdomisili' => 2, 'pindah' => 1, 'meninggal' => 0, 'tidak_dikenal' => 0, 'menunggu' => 1, 'belum' => 2],
            $svc->ringkasanVerifikasi()
        );
        $this->assertSame(
            ['total' => 5, 'berdomisili' => 1, 'pindah' => 1, 'meninggal' => 0, 'tidak_dikenal' => 0, 'menunggu' => 1, 'belum' => 2],
            $svc->ringkasanVerifikasi($s['kel']->id)
        );
    }

    // ---- bagian controller (Task 2) ----

    public function test_analytics_json_default_semua_tidak_mengubah_angka_dan_memuat_ringkasan(): void
    {
        $s = $this->seedCampuran();
        $super = User::factory()->create(['type' => 0]);

        $r = $this->actingAs($super)->getJson(route('admin.analytics.filterImunisasi'))->assertOk()->json();
        $this->assertSame(6, $r['totalAnak']);
        $this->assertSame(2, $r['verifikasiRt']['berdomisili']);
        $this->assertSame(6, $r['verifikasiRt']['total']);

        $r = $this->actingAs($super)->getJson(route('admin.analytics.filterImunisasi', ['verif' => 'berdomisili']))->assertOk()->json();
        $this->assertSame(2, $r['totalAnak']);
        $this->assertSame(2, array_sum($r['genderDistribution']['data']));

        $r = $this->actingAs($super)->getJson(route('admin.analytics.filterImunisasi', ['kelurahan' => $s['kel']->id, 'verif' => 'belum']))->assertOk()->json();
        $this->assertSame(2, $r['totalAnak']);
        $this->assertSame(5, $r['verifikasiRt']['total'], 'ringkasan mengikuti kelurahan, bukan filter verif');
    }

    public function test_halaman_analytics_memuat_filter_dan_kartu_verifikasi(): void
    {
        $this->seedCampuran();
        $super = User::factory()->create(['type' => 0]);

        $this->actingAs($super)->get(route('admin.analytics'))
            ->assertOk()
            ->assertSee('id="filterVerifRt"', false)
            ->assertSee('Status Verifikasi RT')
            ->assertSee('id="verifBerdomisili"', false)
            ->assertSee('id="verifMenunggu"', false)
            ->assertSee('id="verifBelum"', false);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal** — `…/phpunit tests/Feature/VerifikasiRt/AnalyticsVerifikasiRtTest.php --filter "filter_verif|ringkasan"` → FAIL `Call to undefined method …::terapkanFilterVerif()`.

- [ ] **Step 3: Tambahkan ke `VerifikasiRtService`**

```php
    /** Nilai filter "Status verifikasi RT" di Dasbor Gizi (spec §7). */
    public const FILTER_VERIF = ['berdomisili', 'pindah', 'meninggal', 'tidak_dikenal', 'belum'];

    /**
     * Terapkan filter status verifikasi ke kueri anak. Kosong / tak dikenal = tanpa filter (Semua).
     * `belum` = belum pernah diusulkan, masih menunggu reviu, atau ditolak — pokoknya belum final.
     */
    public function terapkanFilterVerif($query, ?string $verif, string $tabel = 'anak'): void
    {
        if (!$verif || !in_array($verif, self::FILTER_VERIF, true)) {
            return;
        }
        if ($verif === 'belum') {
            $query->where(fn ($w) => $w->whereNull("$tabel.verif_rt_reviu")->orWhere("$tabel.verif_rt_reviu", '!=', 'disetujui'));
            return;
        }
        $query->where("$tabel.verif_rt_status", $verif)->where("$tabel.verif_rt_reviu", 'disetujui');
    }

    /** @return array{total:int, berdomisili:int, pindah:int, meninggal:int, tidak_dikenal:int, menunggu:int, belum:int} */
    public function ringkasanVerifikasi(?int $idKel = null): array
    {
        $q = DB::table('anak');
        if ($idKel) {
            $q->where('id_kel', $idKel);
        }
        $r = $q->selectRaw("
            COUNT(*) AS total,
            SUM(verif_rt_status = 'berdomisili'   AND verif_rt_reviu = 'disetujui') AS berdomisili,
            SUM(verif_rt_status = 'pindah'        AND verif_rt_reviu = 'disetujui') AS pindah,
            SUM(verif_rt_status = 'meninggal'     AND verif_rt_reviu = 'disetujui') AS meninggal,
            SUM(verif_rt_status = 'tidak_dikenal' AND verif_rt_reviu = 'disetujui') AS tidak_dikenal,
            SUM(verif_rt_reviu = 'diusulkan') AS menunggu
        ")->first();

        $out = [
            'total'         => (int) $r->total,
            'berdomisili'   => (int) $r->berdomisili,
            'pindah'        => (int) $r->pindah,
            'meninggal'     => (int) $r->meninggal,
            'tidak_dikenal' => (int) $r->tidak_dikenal,
            'menunggu'      => (int) $r->menunggu,
        ];
        $out['belum'] = $out['total'] - $out['berdomisili'] - $out['pindah'] - $out['meninggal'] - $out['tidak_dikenal'] - $out['menunggu'];

        return $out;
    }
```

- [ ] **Step 4: Jalankan tes service** → 2 tes lulus (tes controller masih gagal — itu Task 2).
- [ ] **Step 5: Commit** — `git add app/Services/VerifikasiRtService.php tests/Feature/VerifikasiRt/AnalyticsVerifikasiRtTest.php && git commit -m "feat(verifikasi-rt): helper filter & ringkasan verifikasi untuk Dasbor Gizi"`

---

### Task 2: Controller analytics — parameter `verif` + `verifikasiRt`

**Files:**
- Modify: `app/Http/Controllers/AdminController.php` (`analyticsDashboard`, `analyticsFilterImunisasi`)

**Interfaces:**
- `analyticsDashboard()` mengirim `verifikasiRt` (array ringkasan, tanpa filter kelurahan) ke view.
- `analyticsFilterImunisasi()` membaca `?verif=`; ketiga closure (`$applyAnakFilter`, `$applyImunisasiFilter`, `$applyDataAnakFilter`) dan kueri `$completeQuery` ikut menerapkan filter; JSON menambah `verifikasiRt` = `ringkasanVerifikasi($filterKel ?: null)`.

- [ ] **Step 1: Jalankan tes controller, pastikan gagal** — `--filter analytics_json` → FAIL (`verifikasiRt` tidak ada / totalAnak 6 saat verif=berdomisili).

- [ ] **Step 2: Patch controller**

Di `analyticsDashboard()`, sebelum `return view(...)` tambahkan `$verifikasiRt = app(\App\Services\VerifikasiRtService::class)->ringkasanVerifikasi();` dan masukkan `'verifikasiRt'` ke `compact(...)`.

Di `analyticsFilterImunisasi()`:

```php
        $verif = (string) $request->input('verif', '');
        $verifSvc = app(\App\Services\VerifikasiRtService::class);
```

`$applyAnakFilter`:
```php
        $applyAnakFilter = function($query, $table = 'anak') use ($filterKel, $verif, $verifSvc) {
            if ($filterKel) $query->where("$table.id_kel", $filterKel);
            $verifSvc->terapkanFilterVerif($query, $verif, $table);
            return $query;
        };
```

`$applyImunisasiFilter` dan `$applyDataAnakFilter`: ganti blok `if ($filterKel) { whereExists(...) }` menjadi satu `whereExists` yang jalan bila `$filterKel || $verif`:
```php
            if ($filterKel || $verif) {
                $query->whereExists(function($sub) use ($filterKel, $verif, $verifSvc) {
                    $sub->select(DB::raw(1))->from('anak')->whereColumn('anak.id', 'imunisasi.id_anak');
                    if ($filterKel) $sub->where('anak.id_kel', $filterKel);
                    $verifSvc->terapkanFilterVerif($sub, $verif, 'anak');
                });
            }
```
(untuk `data_anak` ganti `imunisasi.id_anak` → `data_anak.id_anak`). Perlakukan `$completeQuery` (Incomplete Imunisasi) dengan pola yang sama. Periksa juga kueri lain di fungsi itu yang memakai `if ($filterKel)` langsung (mis. `recentActivities`, `zScore`) — samakan.

Di `return response()->json([...])` tambahkan `'verifikasiRt' => $verifSvc->ringkasanVerifikasi($filterKel ?: null),`.

- [ ] **Step 3: Jalankan** — `…/phpunit tests/Feature/VerifikasiRt/AnalyticsVerifikasiRtTest.php --filter "analytics_json"` → lulus.
- [ ] **Step 4: Commit** — `git add app/Http/Controllers/AdminController.php && git commit -m "feat(verifikasi-rt): filter verif & ringkasan verifikasi di endpoint analytics"`

---

### Task 3: View analytics — select, kartu, JS

**Files:**
- Modify: `resources/views/admin/dashboard/analytics.blade.php`

- [ ] **Step 1: Jalankan tes halaman, pastikan gagal** — `--filter halaman_analytics` → FAIL (`filterVerifRt` tidak ada).

- [ ] **Step 2: Patch view**

1. Filter: ubah keempat kolom filter dari `col-md-3` menjadi `col-md-2` **kecuali** biarkan; lebih sederhana: tambahkan kolom kelima setelah "Status Imunisasi" dengan `col-md-3` (baris akan wrap — dapat diterima) atau ubah semua menjadi `col-md`. Pakai `col-md` (auto) untuk kelima kolom:

```blade
            <div class="col-md">
                <div class="form-group">
                    <label class="font-weight-bold" style="font-size: 0.8125rem;">Status Verifikasi RT</label>
                    <select id="filterVerifRt" class="form-control filter-input">
                        <option value="">-- Semua --</option>
                        <option value="berdomisili">Berdomisili (disetujui)</option>
                        <option value="belum">Belum diverifikasi</option>
                        <option value="pindah">Pindah</option>
                        <option value="meninggal">Meninggal</option>
                        <option value="tidak_dikenal">Tidak dikenal</option>
                    </select>
                </div>
            </div>
```

2. Kartu — sisipkan setelah blok `{{-- Summary Statistics --}} … </div>` (sebelum `<h4 class="section-title"><i class="fa fa-heartbeat …`):

```blade
{{-- Verifikasi RT (spec verifikasi RT §7) — status domisili hasil verifikasi RT yang sudah disetujui --}}
<h4 class="section-title"><i class="fa fa-check-square-o mr-2"></i> Verifikasi RT</h4>
<div class="row mb-4" id="verifRtCards">
    @foreach ([
        ['verifBerdomisili', 'berdomisili', 'Berdomisili (disetujui)', 'success'],
        ['verifPindah', 'pindah', 'Pindah', 'warning'],
        ['verifMeninggal', 'meninggal', 'Meninggal', 'danger'],
        ['verifTidakDikenal', 'tidak_dikenal', 'Tidak dikenal', 'warning'],
        ['verifMenunggu', 'menunggu', 'Menunggu reviu', 'primary'],
        ['verifBelum', 'belum', 'Belum diverifikasi', 'primary'],
    ] as [$id, $key, $label, $warna])
    <div class="col-xl-2 col-lg-4 col-md-4 col-6 mb-3">
        <div class="stat-card {{ $warna }} p-3">
            <div class="stat-value" id="{{ $id }}">{{ number_format($verifikasiRt[$key]) }}</div>
            <div class="stat-label">{{ $label }}</div>
        </div>
    </div>
    @endforeach
</div>
```

3. JS di `applyDashboardFilter()`: baca `var verif = $('#filterVerifRt').val();`, ikutkan di `toggle(!!(… || verif))`, kirim `verif: verif` di `data`, dan di `success` tambahkan:

```javascript
                if (r.verifikasiRt) {
                    $('#verifBerdomisili').text(fmt(r.verifikasiRt.berdomisili));
                    $('#verifPindah').text(fmt(r.verifikasiRt.pindah));
                    $('#verifMeninggal').text(fmt(r.verifikasiRt.meninggal));
                    $('#verifTidakDikenal').text(fmt(r.verifikasiRt.tidak_dikenal));
                    $('#verifMenunggu').text(fmt(r.verifikasiRt.menunggu));
                    $('#verifBelum').text(fmt(r.verifikasiRt.belum));
                }
```

Reset: tambahkan `#filterVerifRt` ke selector `val('')` di handler `#btnResetFilter`.

- [ ] **Step 3: Jalankan** — `view:clear` lalu seluruh `AnalyticsVerifikasiRtTest` → 4 tes lulus.
- [ ] **Step 4: Cek visual singkat** (opsional): buka `/admin/analytics`, pilih "Belum diverifikasi", pastikan kartu & Total Anak berubah, reset mengembalikan.
- [ ] **Step 5: Commit** — `git add resources/views/admin/dashboard/analytics.blade.php && git commit -m "feat(verifikasi-rt): kartu & filter status verifikasi RT di Dasbor Gizi"`

---

### Task 4: Export Anak — kolom Sumber / Verifikasi RT / Reviu

**Files:**
- Create: `database/migrations/2026_09_18_000001_add_verifikasi_rt_to_alldata_view.php`
- Modify: `app/Exports/AnakExport.php`
- Test: `tests/Feature/VerifikasiRt/ExportAnakVerifikasiRtTest.php`

**Interfaces:**
- VIEW `alldata` bertambah `a.sumber, a.sumber_gabungan, a.verif_rt_status AS verifRtStatus, a.verif_rt_reviu AS verifRtReviu` (SELECT list lainnya persis salinan migrasi `2026_04_28_155001`).
- `AnakExport::headings()` bertambah di akhir: `'Sumber Data', 'Verifikasi RT', 'Reviu Verifikasi'`; `map()`: `$data->sumber . ($data->sumber_gabungan ? ' (+'.implode(', ', array_diff(json_decode($data->sumber_gabungan, true) ?: [], [$data->sumber])).')' : '')`, label status (`VerifikasiAnak::LABEL_STATUS[$data->verifRtStatus] ?? '-'`), `$data->verifRtReviu ?? '-'`.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php
// tests/Feature/VerifikasiRt/ExportAnakVerifikasiRtTest.php

namespace Tests\Feature\VerifikasiRt;

use App\Exports\AnakExport;
use App\Models\Anak;
use App\Models\DataAnak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExportAnakVerifikasiRtTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_alldata_punya_kolom_verifikasi(): void
    {
        $this->assertTrue(Schema::hasColumns('alldata', ['sumber', 'sumber_gabungan', 'verifRtStatus', 'verifRtReviu']));
    }

    public function test_export_anak_memuat_kolom_sumber_dan_verifikasi(): void
    {
        $a = Anak::create(['nama' => 'Anak Export', 'nik' => '3201000000022001', 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'operasi_timbang', 'sumber_gabungan' => ['operasi_timbang', 'capil']]);
        DataAnak::create(['id_anak' => $a->id, 'tgl_kunjungan' => '2026-01-10', 'bln' => 24, 'posisi' => 'berdiri', 'tb' => 80, 'bb' => 10,
            'lla' => 0, 'lk' => 0, 'id_user' => 1, 'zscore_bb_u' => 0, 'zscore_pb_u' => 0, 'zscore_bb_pb' => 0, 'sumber' => 'operasi_timbang']);
        DB::table('anak')->where('id', $a->id)->update(['verif_rt_status' => 'pindah', 'verif_rt_reviu' => 'disetujui']);

        $export = new AnakExport(new Request());
        $headings = $export->headings();
        $row = $export->map($export->query()->first());

        $this->assertSame(['Sumber Data', 'Verifikasi RT', 'Reviu Verifikasi'], array_slice($headings, -3));
        $this->assertSame(['operasi_timbang (+capil)', 'Pindah', 'disetujui'], array_slice($row, -3));
        $this->assertCount(count($headings), $row);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal** → FAIL kolom `sumber` tidak ada di `alldata`.

- [ ] **Step 3: Migrasi recreate VIEW** — salin `up()` dari `2026_04_28_155001_create_alldata_view.php`, sisipkan setelah `a.catatan,`:

```sql
                a.sumber,
                a.sumber_gabungan,
                a.verif_rt_status AS verifRtStatus,
                a.verif_rt_reviu  AS verifRtReviu,
```

`down()` = recreate VIEW versi lama (salin utuh SELECT lama). Kedua arah diawali `DROP VIEW IF EXISTS`.

- [ ] **Step 4: `AnakExport`** — tambahkan `use App\Models\VerifikasiAnak;`; tiga heading di akhir; tiga nilai di akhir `map()`:

```php
            $this->labelSumber($data),
            $data->verifRtStatus ? (VerifikasiAnak::LABEL_STATUS[$data->verifRtStatus] ?? $data->verifRtStatus) : '-',
            $data->verifRtReviu ?: '-',
```

```php
    /** "operasi_timbang (+capil)" — sumber utama + sumber lain yang pernah dilebur (verifikasi RT §3.2). */
    private function labelSumber($data): string
    {
        $lain = array_values(array_diff((array) (json_decode((string) $data->sumber_gabungan, true) ?: []), [$data->sumber]));
        return (string) $data->sumber . ($lain ? ' (+'.implode(', ', $lain).')' : '');
    }
```

- [ ] **Step 5: Jalankan tes** → 2 lulus. Jalankan juga tes lain yang menyentuh export anak bila ada (`grep -rl AnakExport tests`).
- [ ] **Step 6: Commit** — `git add database/migrations/2026_09_18_000001_add_verifikasi_rt_to_alldata_view.php app/Exports/AnakExport.php tests/Feature/VerifikasiRt/ExportAnakVerifikasiRtTest.php && git commit -m "feat(verifikasi-rt): kolom sumber & verifikasi RT di export anak"`

---

### Task 5: Suite penuh, memori, laporan

- [ ] `php artisan migrate` di DB dev.
- [ ] Suite penuh → `OK` (pastikan `TimbangDashboardTerkunciTest` ikut hijau — dasbor OT tak tersentuh).
- [ ] Perbarui memori `project_verifikasi_rt.md` (T4 selesai; seluruh fitur selesai; yang tersisa: keputusan integrasi branch + migrasi ke prod + pertanyaan Kesmas).

## Self-Review

- **Spec §7:** kartu (Task 1, 3), filter default Semua (Task 1–3, dikunci tes `default_semua`), distribusi wilayah memakai `id_rt/id_kel` terkini (otomatis — kueri lama), kolom export Anak (Task 4). §10 h (tes `default_semua`).
- **Placeholder:** tidak ada.
- **Nama:** `terapkanFilterVerif`, `ringkasanVerifikasi`, param `verif`, JSON `verifikasiRt`, id DOM `filterVerifRt`, `verifBerdomisili/verifPindah/verifMeninggal/verifTidakDikenal/verifMenunggu/verifBelum` konsisten di controller, view, tes.

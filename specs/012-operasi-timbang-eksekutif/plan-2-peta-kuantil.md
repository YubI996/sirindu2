# Plan 2 — Peta RT Prioritas Kuantil Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Warnai peta sebaran per wilayah (Kecamatan/Kelurahan/RT) berdasarkan **kuantil** (Q1 hijau → M kuning → Q3 merah) untuk tiap indikator prioritas (stunting %, gizi buruk/kurang %, BB tidak naik %, jumlah anak prioritas), dengan sumber agregasi cepat dari snapshot `prioritas_gizi` dan popup yang menampilkan prevalensi tiap indikator.

**Architecture:** Agregasi per-wilayah dibaca dari `prioritas_gizi` (di-key nama wilayah agar cocok dengan pencocokan GeoJSON existing), bukan recompute live. Ambang kuantil (tertil) dihitung **server-side** oleh helper murni `App\Support\Kuantil` untuk tiap kombinasi (level × indikator), lalu dikirim ke view. JS peta hanya membandingkan nilai wilayah terhadap ambang untuk memilih warna. Ini juga menyatukan angka gizi peta ke indikator **BB/TB** (konsisten dengan dashboard timbang & tab prioritas), menggantikan basis IMT/U lama.

**Tech Stack:** PHP 8 / Laravel 12, Eloquent/Query Builder, Blade + Leaflet 1.9 (client JS), PHPUnit (RefreshDatabase).

## Global Constraints

- Sumber agregasi wilayah = tabel `prioritas_gizi` (Plan 1). JANGAN recompute z-score di peta.
- Indikator gizi peta memakai **BB/TB** (`gizi_buruk` = severely_wasted, `gizi_kurang` = wasted) — ini MENGGANTIKAN basis IMT/U lama di `akumulasiStatistikGizi`. Stunting = TB/U (kolom `stunting` snapshot). BB tidak naik = kolom `bb_tidak_naik`.
- Data agregasi di-key **nama wilayah** (kecamatan.name / kelurahan.name / rt.name) agar tetap cocok dengan resolver GeoJSON di `map.blade.php` (`resolveKelurahanName`, `getRtLookup`).
- Kuantil = **tertil**: bagi nilai wilayah (yang `total>0`) jadi 3 kelas. `kelas 0` (≤ ambang bawah) → **Hijau**; `kelas 1` → **Kuning**; `kelas 2` (> ambang atas) → **Merah**. Nilai tinggi = buruk = merah. Wilayah `total==0` → abu-abu, DIKELUARKAN dari perhitungan ambang.
- Denominator prevalensi = anak **terukur** di wilayah = baris snapshot dengan `usia_bln IS NOT NULL`. Flag gizi/stunting hanya true untuk anak terukur.
- Indikator & nilai kuantilnya: `stunting`→stunting_pct, `gizi`→gizi_kurang_buruk_pct, `bb_tidak_naik`→bb_tidak_naik_pct, `prioritas`→anak_prioritas (count).
- Mode peta existing yang TIDAK diubah: `count` (jumlah anak, warna fixed lama) dan `imunisasi`. Yang diubah/ditambah: `stunting`, `gizi` (jadi kuantil), + mode baru `bbtn` (BB tidak naik) & `prioritas`.
- Test suite berbagi satu DB test → jalankan `php artisan test` SERIAL (jangan paralel).
- Test menyuntik referensi via `StatusGiziService::useRefs([...])` + `flushCache()`; snapshot diisi melalui observer saat membuat DataAnak (Plan 1 sudah aktif), atau panggil `app(PrioritasGiziService::class)->refreshAll()`.

---

### Task 1: Helper kuantil murni `App\Support\Kuantil`

**Files:**
- Create: `app/Support/Kuantil.php`
- Test: `tests/Unit/Support/KuantilTest.php`

**Interfaces:**
- Produces:
  - `Kuantil::ambangTertil(array $nilai): array` — dari daftar nilai numerik (boleh kosong), kembalikan `[batasBawah, batasAtas]` (dua titik potong tertil dari nilai TERURUT). Array kosong → `[]`.
  - `Kuantil::kelas(float $nilai, array $ambang): int` — `0` bila `$ambang` kosong atau `$nilai <= $ambang[0]`; `1` bila `<= $ambang[1]`; selain itu `2`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Support;

use App\Support\Kuantil;
use PHPUnit\Framework\TestCase;

class KuantilTest extends TestCase
{
    public function test_ambang_tertil_membagi_sembilan_nilai_jadi_tiga(): void
    {
        // 1..9: tertil di indeks 3 dan 6 (nilai 4 dan 7).
        $this->assertSame([4.0, 7.0], Kuantil::ambangTertil([9, 1, 8, 2, 7, 3, 6, 4, 5]));
    }

    public function test_ambang_tertil_daftar_kosong_kembalikan_kosong(): void
    {
        $this->assertSame([], Kuantil::ambangTertil([]));
    }

    public function test_kelas_membagi_hijau_kuning_merah(): void
    {
        $ambang = [4.0, 7.0];
        $this->assertSame(0, Kuantil::kelas(2, $ambang));   // <= batas bawah → hijau
        $this->assertSame(0, Kuantil::kelas(4, $ambang));   // tepat batas bawah → hijau
        $this->assertSame(1, Kuantil::kelas(5, $ambang));   // tengah → kuning
        $this->assertSame(1, Kuantil::kelas(7, $ambang));   // tepat batas atas → kuning
        $this->assertSame(2, Kuantil::kelas(8, $ambang));   // > batas atas → merah
    }

    public function test_kelas_ambang_kosong_selalu_nol(): void
    {
        $this->assertSame(0, Kuantil::kelas(99, []));
    }

    public function test_ambang_tertil_nilai_seri_tidak_error(): void
    {
        // Semua sama → kedua batas sama; kelas apa pun jadi 0/1 tapi tidak error.
        $this->assertSame([5.0, 5.0], Kuantil::ambangTertil([5, 5, 5, 5]));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=KuantilTest`
Expected: FAIL — class `App\Support\Kuantil` not found.

- [ ] **Step 3: Write the helper**

Create `app/Support/Kuantil.php`:

```php
<?php

namespace App\Support;

/**
 * Pembagian nilai wilayah menjadi tiga kelas (tertil) untuk pewarnaan peta:
 * kelas 0 = terendah (hijau), 1 = tengah (kuning), 2 = tertinggi (merah).
 */
class Kuantil
{
    /**
     * Dua titik potong tertil dari daftar nilai. Daftar kosong → [].
     *
     * @param array<int|float> $nilai
     * @return array{0:float,1:float}|array{}
     */
    public static function ambangTertil(array $nilai): array
    {
        if (empty($nilai)) {
            return [];
        }

        $urut = array_values($nilai);
        sort($urut, SORT_NUMERIC);
        $n = count($urut);

        $iBawah = (int) floor($n / 3);
        $iAtas  = (int) floor(2 * $n / 3);
        // Jaga indeks dalam rentang untuk n kecil.
        $iBawah = min($iBawah, $n - 1);
        $iAtas  = min($iAtas, $n - 1);

        return [(float) $urut[$iBawah], (float) $urut[$iAtas]];
    }

    /**
     * Kelas tertil sebuah nilai terhadap ambang [bawah, atas].
     * Ambang kosong → 0.
     */
    public static function kelas(float $nilai, array $ambang): int
    {
        if (count($ambang) < 2) {
            return 0;
        }
        if ($nilai <= $ambang[0]) {
            return 0;
        }
        if ($nilai <= $ambang[1]) {
            return 1;
        }
        return 2;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=KuantilTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Support/Kuantil.php tests/Unit/Support/KuantilTest.php
git commit -m "feat(peta): helper Kuantil tertil untuk pewarnaan wilayah

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: `PetaPrioritasService` — agregasi snapshot per wilayah

**Files:**
- Create: `app/Services/PetaPrioritasService.php`
- Test: `tests/Feature/PrioritasGizi/PetaPrioritasServiceTest.php`

**Interfaces:**
- Consumes: tabel `prioritas_gizi` (kolom `id_kec,id_kel,id_rt,gizi_buruk,gizi_kurang,stunting,bb_tidak_naik,prioritas,usia_bln`).
- Produces: `PetaPrioritasService::agregat(string $level): array` — `$level` ∈ `'kecamatan'|'kelurahan'|'rt'`. Kembalikan array di-key **nama wilayah** →
  `['total'=>int,'stunting'=>int,'gizi_buruk'=>int,'gizi_kurang'=>int,'bb_tidak_naik'=>int,'anak_prioritas'=>int,'stunting_pct'=>float,'gizi_kurang_buruk_pct'=>float,'bb_tidak_naik_pct'=>float]`.
  `total` = jumlah anak TERUKUR (`usia_bln` non-null) di wilayah. Wilayah tanpa anak terukur tidak muncul.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\PrioritasGizi;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\Kelurahan;
use App\Services\PetaPrioritasService;
use App\Services\StatusGiziService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PetaPrioritasServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        StatusGiziService::useRefs([
            '1_1_24_2' => (object) ['m3sd' => 12, 'm2sd' => 13, '1sd' => 17, '2sd' => 18, '3sd' => 19],
            '2_1_24_1' => (object) ['m3sd' => 9, 'm2sd' => 10, '1sd' => 15],
            '3_1_24_2' => (object) ['m3sd' => 80, 'm2sd' => 83, '3sd' => 97],
            '4_1_90_2' => (object) ['m3sd' => 15, 'm2sd' => 16, '1sd' => 20, '2sd' => 22, '3sd' => 24],
        ]);
    }

    protected function tearDown(): void
    {
        StatusGiziService::flushCache();
        parent::tearDown();
    }

    public function test_agregat_kelurahan_menghitung_gizi_buruk_dan_prevalensi(): void
    {
        $kel = Kelurahan::create(['name' => 'Api-Api', 'id_kec' => 1]);

        // Anak gizi buruk (bb=12/tb=90/bln=24 → BB/TB severely_wasted).
        $a1 = Anak::create([
            'nama' => 'Buruk', 'nik' => '3201000000009101', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1, 'id_kel' => $kel->id,
        ]);
        DataAnak::create(['id_anak' => $a1->id, 'tgl_kunjungan' => '2024-06-01', 'bln' => 24,
            'posisi' => 'berdiri', 'tb' => 90, 'bb' => 12, 'lla' => 0, 'lk' => 0, 'id_user' => 1]);

        // Anak normal (bb=18/tb=90 → BB/TB normal, TB/U normal).
        $a2 = Anak::create([
            'nama' => 'Normal', 'nik' => '3201000000009102', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1, 'id_kel' => $kel->id,
        ]);
        DataAnak::create(['id_anak' => $a2->id, 'tgl_kunjungan' => '2024-06-01', 'bln' => 24,
            'posisi' => 'berdiri', 'tb' => 90, 'bb' => 18, 'lla' => 0, 'lk' => 0, 'id_user' => 1]);

        $agg = app(PetaPrioritasService::class)->agregat('kelurahan');

        $this->assertArrayHasKey('Api-Api', $agg);
        $this->assertSame(2, $agg['Api-Api']['total']);
        $this->assertSame(1, $agg['Api-Api']['gizi_buruk']);
        $this->assertSame(1, $agg['Api-Api']['anak_prioritas']);
        $this->assertSame(50.0, $agg['Api-Api']['gizi_kurang_buruk_pct']);
    }

    public function test_agregat_mengabaikan_anak_tanpa_pengukuran(): void
    {
        $kel = Kelurahan::create(['name' => 'Kanaan', 'id_kec' => 1]);
        // Anak tanpa DataAnak → snapshot usia_bln null → tidak dihitung sebagai terukur.
        Anak::create([
            'nama' => 'Tanpa Ukur', 'nik' => '3201000000009103', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1, 'id_kel' => $kel->id,
        ]);

        $agg = app(PetaPrioritasService::class)->agregat('kelurahan');

        $this->assertArrayNotHasKey('Kanaan', $agg);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PetaPrioritasServiceTest`
Expected: FAIL — class `PetaPrioritasService` not found.

- [ ] **Step 3: Write the service**

Create `app/Services/PetaPrioritasService.php`:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Agregasi snapshot prioritas_gizi per wilayah (kecamatan/kelurahan/rt),
 * di-key nama wilayah agar cocok dengan pencocokan GeoJSON di peta.
 * Denominator prevalensi = anak terukur (usia_bln non-null).
 */
class PetaPrioritasService
{
    /** @return array<string,array<string,int|float>> */
    public function agregat(string $level): array
    {
        [$kolomId, $tabel] = match ($level) {
            'kecamatan' => ['id_kec', 'kecamatan'],
            'rt'        => ['id_rt', 'rt'],
            default     => ['id_kel', 'kelurahan'],
        };

        $rows = DB::table('prioritas_gizi as p')
            ->join("{$tabel} as w", "p.{$kolomId}", '=', 'w.id')
            ->whereNotNull('p.usia_bln')
            ->groupBy('w.name')
            ->selectRaw('w.name as nama')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(p.stunting) as stunting')
            ->selectRaw('SUM(p.gizi_buruk) as gizi_buruk')
            ->selectRaw('SUM(p.gizi_kurang) as gizi_kurang')
            ->selectRaw('SUM(p.bb_tidak_naik) as bb_tidak_naik')
            ->selectRaw('SUM(CASE WHEN p.prioritas IS NOT NULL THEN 1 ELSE 0 END) as anak_prioritas')
            ->get();

        $hasil = [];
        foreach ($rows as $r) {
            $total = (int) $r->total;
            if ($total === 0) {
                continue;
            }
            $giziKB = (int) $r->gizi_buruk + (int) $r->gizi_kurang;
            $hasil[$r->nama] = [
                'total'                 => $total,
                'stunting'              => (int) $r->stunting,
                'gizi_buruk'            => (int) $r->gizi_buruk,
                'gizi_kurang'           => (int) $r->gizi_kurang,
                'bb_tidak_naik'         => (int) $r->bb_tidak_naik,
                'anak_prioritas'        => (int) $r->anak_prioritas,
                'stunting_pct'          => round((int) $r->stunting / $total * 100, 1),
                'gizi_kurang_buruk_pct' => round($giziKB / $total * 100, 1),
                'bb_tidak_naik_pct'     => round((int) $r->bb_tidak_naik / $total * 100, 1),
            ];
        }

        return $hasil;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=PetaPrioritasServiceTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Run the full suite (serial)**

Run: `php artisan test`
Expected: all passing (previous baseline + new tests).

- [ ] **Step 6: Commit**

```bash
git add app/Services/PetaPrioritasService.php tests/Feature/PrioritasGizi/PetaPrioritasServiceTest.php
git commit -m "feat(peta): PetaPrioritasService agregasi snapshot per wilayah

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: Controller `mapDashboard` — kirim agregat + ambang kuantil ke view

**Files:**
- Modify: `app/Http/Controllers/AdminController.php` (method `mapDashboard`, sekitar baris 1680)
- Test: `tests/Feature/PrioritasGizi/PetaKuantilViewTest.php`

**Interfaces:**
- Consumes: `PetaPrioritasService::agregat`, `App\Support\Kuantil::ambangTertil`.
- Produces: view `admin.dashboard.map` menerima variabel baru:
  - `$petaAgregat` — `['kecamatan'=>[...], 'kelurahan'=>[...], 'rt'=>[...]]` (hasil `agregat()` per level).
  - `$petaKuantil` — ambang per level per indikator: `$petaKuantil[$level][$indikator] = [bawah, atas]` untuk `$indikator` ∈ `stunting|gizi|bbtn|prioritas`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\PrioritasGizi;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\Kelurahan;
use App\Models\User;
use App\Services\StatusGiziService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PetaKuantilViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        StatusGiziService::useRefs([
            '1_1_24_2' => (object) ['m3sd' => 12, 'm2sd' => 13, '1sd' => 17, '2sd' => 18, '3sd' => 19],
            '2_1_24_1' => (object) ['m3sd' => 9, 'm2sd' => 10, '1sd' => 15],
            '3_1_24_2' => (object) ['m3sd' => 80, 'm2sd' => 83, '3sd' => 97],
            '4_1_90_2' => (object) ['m3sd' => 15, 'm2sd' => 16, '1sd' => 20, '2sd' => 22, '3sd' => 24],
        ]);
    }

    protected function tearDown(): void
    {
        StatusGiziService::flushCache();
        parent::tearDown();
    }

    public function test_view_peta_menerima_agregat_dan_ambang_kuantil(): void
    {
        $superAdmin = User::factory()->create(['type' => 0]);
        $kel = Kelurahan::create(['name' => 'Api-Api', 'id_kec' => 1]);
        $anak = Anak::create([
            'nama' => 'Buruk', 'nik' => '3201000000009111', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1, 'id_kel' => $kel->id,
        ]);
        DataAnak::create(['id_anak' => $anak->id, 'tgl_kunjungan' => '2024-06-01', 'bln' => 24,
            'posisi' => 'berdiri', 'tb' => 90, 'bb' => 12, 'lla' => 0, 'lk' => 0, 'id_user' => 1]);

        $response = $this->actingAs($superAdmin)->get(route('admin.map'));

        $response->assertStatus(200);
        $agg = $response->viewData('petaAgregat');
        $kuantil = $response->viewData('petaKuantil');

        $this->assertArrayHasKey('kelurahan', $agg);
        $this->assertArrayHasKey('Api-Api', $agg['kelurahan']);
        $this->assertArrayHasKey('kelurahan', $kuantil);
        $this->assertArrayHasKey('stunting', $kuantil['kelurahan']);
        $this->assertArrayHasKey('gizi', $kuantil['kelurahan']);
        $this->assertArrayHasKey('bbtn', $kuantil['kelurahan']);
        $this->assertArrayHasKey('prioritas', $kuantil['kelurahan']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PetaKuantilViewTest`
Expected: FAIL — `viewData('petaAgregat')` null.

- [ ] **Step 3: Build aggregates + thresholds in the controller**

In `app/Http/Controllers/AdminController.php`, ensure imports exist at top (add if missing):

```php
use App\Services\PetaPrioritasService;
use App\Support\Kuantil;
```

Inside `mapDashboard()`, just before the `return view('admin.dashboard.map', compact(...))`, add:

```php
        // Agregasi prioritas per wilayah (dari snapshot) + ambang kuantil per indikator.
        $peta = app(PetaPrioritasService::class);
        $petaAgregat = [
            'kecamatan' => $peta->agregat('kecamatan'),
            'kelurahan' => $peta->agregat('kelurahan'),
            'rt'        => $peta->agregat('rt'),
        ];

        $indikatorNilai = [
            'stunting'  => 'stunting_pct',
            'gizi'      => 'gizi_kurang_buruk_pct',
            'bbtn'      => 'bb_tidak_naik_pct',
            'prioritas' => 'anak_prioritas',
        ];
        $petaKuantil = [];
        foreach ($petaAgregat as $level => $wilayah) {
            foreach ($indikatorNilai as $indikator => $kolom) {
                $nilai = array_map(fn ($w) => (float) $w[$kolom], array_values($wilayah));
                $petaKuantil[$level][$indikator] = Kuantil::ambangTertil($nilai);
            }
        }
```

Then add `$petaAgregat` and `$petaKuantil` to the `compact(...)` list in the `return view('admin.dashboard.map', compact(...))` call (append `'petaAgregat', 'petaKuantil'` to the existing argument list).

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=PetaKuantilViewTest`
Expected: PASS.

- [ ] **Step 5: Run the full suite (serial)**

Run: `php artisan test`
Expected: all passing.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/AdminController.php tests/Feature/PrioritasGizi/PetaKuantilViewTest.php
git commit -m "feat(peta): mapDashboard kirim agregat snapshot + ambang kuantil

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 4: View peta — pewarnaan kuantil + mode BB tidak naik & anak prioritas + popup prevalensi

**Files:**
- Modify: `resources/views/admin/dashboard/map.blade.php`

**Interfaces:**
- Consumes: `$petaAgregat` (per level, di-key nama), `$petaKuantil` (ambang per level×indikator) dari Task 3.

Catatan orientasi (struktur file existing):
- Data dikirim ke JS via `@json(...)`. Mode aktif disimpan di `currentMode` (`count|stunting|gizi|imunisasi`), layer di `currentLayer` (`kecamatan|kelurahan|rt`).
- Warna dipilih di `getStyle()` yang memanggil `getColorByStunting/Gizi/Imunisasi/Count`. Tombol mode: `showMode('count'|'stunting'|'gizi'|'imunisasi')`. Legenda di `updateLegend()`. Popup di `getPopupContent()`, panel hover di `onEachFeature`.

- [ ] **Step 1: Inject the new PHP data into JS**

In `resources/views/admin/dashboard/map.blade.php`, in the `@section('scripts')` block where existing `const kelurahanZScore = @json($kelurahanZScore);` etc. are declared, add:

```javascript
    const petaAgregat = @json($petaAgregat);
    const petaKuantil = @json($petaKuantil);
```

- [ ] **Step 2: Add quantile color + lookup helpers**

In the same script, after the existing `getColorByImunisasi` function, add:

```javascript
    // Warna kuantil: kelas 0 hijau, 1 kuning, 2 merah; abu-abu bila tak ada data.
    const KUANTIL_WARNA = ['#047857', '#f59e0b', '#be123c'];

    function kelasKuantil(nilai, ambang) {
        if (!ambang || ambang.length < 2) return 0;
        if (nilai <= ambang[0]) return 0;
        if (nilai <= ambang[1]) return 1;
        return 2;
    }

    // Ambil baris agregat wilayah utk layer aktif berdasarkan nama tampilan.
    function agregatWilayah(type, name, feature) {
        let key = name;
        if (type === 'kelurahan') key = resolveKelurahanName(name);
        else if (type === 'kecamatan') key = name.replace('Kecamatan ', '');
        else if (type === 'rt') {
            const info = getRtLookup(feature);
            key = info && info.dbRTName ? info.dbRTName : name;
        }
        const level = (type === 'kecamatan') ? 'kecamatan' : (type === 'rt' ? 'rt' : 'kelurahan');
        return (petaAgregat[level] || {})[key] || null;
    }

    // Nilai indikator + warna kuantil utk mode prioritas.
    function warnaKuantilMode(type, agg) {
        if (!agg) return '#e5e7eb';
        const level = (type === 'kecamatan') ? 'kecamatan' : (type === 'rt' ? 'rt' : 'kelurahan');
        const ambangSet = petaKuantil[level] || {};
        let nilai, ambang;
        if (currentMode === 'stunting')      { nilai = agg.stunting_pct;          ambang = ambangSet.stunting; }
        else if (currentMode === 'gizi')     { nilai = agg.gizi_kurang_buruk_pct; ambang = ambangSet.gizi; }
        else if (currentMode === 'bbtn')     { nilai = agg.bb_tidak_naik_pct;     ambang = ambangSet.bbtn; }
        else if (currentMode === 'prioritas'){ nilai = agg.anak_prioritas;        ambang = ambangSet.prioritas; }
        else return '#e5e7eb';
        return KUANTIL_WARNA[kelasKuantil(nilai, ambang)];
    }
```

- [ ] **Step 3: Route the prioritas modes through the quantile color in `getStyle`**

In `getStyle(name, type, feature = null)`, replace the color-selection block (the `if (currentMode === 'stunting' && zScore) {...} else if ... else { color = getColorByCount(count); }`) with:

```javascript
        let color;
        if (['stunting', 'gizi', 'bbtn', 'prioritas'].includes(currentMode)) {
            color = warnaKuantilMode(type, agregatWilayah(type, name, feature));
        } else if (currentMode === 'imunisasi') {
            color = getColorByImunisasi(imunisasiStats);
        } else {
            color = getColorByCount(count);
        }
```

(Leave the earlier lines that compute `stats`, `zScore`, `imunisasiStats`, `count` intact — they are still used by other modes and popups.)

- [ ] **Step 4: Add the two new mode buttons**

In the `layer-toggle` control (after the existing `btnGizi` button), add:

```blade
            <button class="btn btn-outline-danger btn-sm" id="btnBbtn" onclick="showMode('bbtn')">
                <i class="fa fa-arrow-down mr-1"></i> BB Tidak Naik
            </button>
            <button class="btn btn-outline-dark btn-sm" id="btnPrioritas" onclick="showMode('prioritas')">
                <i class="fa fa-triangle-exclamation mr-1"></i> Anak Prioritas
            </button>
```

And in the `showMode` function, extend the button-deactivation list so the new buttons toggle active state:

```javascript
        ['btnCount', 'btnStunting', 'btnGizi', 'btnImunisasi', 'btnBbtn', 'btnPrioritas'].forEach(id => {
            const btn = document.getElementById(id);
            if (btn) btn.classList.remove('active');
        });
```

(The existing `document.getElementById('btn' + mode.charAt(0).toUpperCase() + mode.slice(1))` resolves `btnBbtn`/`btnPrioritas` correctly.)

- [ ] **Step 5: Update the legend for quantile modes**

In `updateLegend()`, add branches before the final `else` (count) branch:

```javascript
        } else if (['stunting', 'gizi', 'bbtn', 'prioritas'].includes(currentMode)) {
            const judul = {
                stunting: 'Prevalensi stunting',
                gizi: 'Prevalensi gizi buruk/kurang',
                bbtn: 'Prevalensi BB tidak naik',
                prioritas: 'Jumlah anak prioritas'
            }[currentMode];
            legendContent.innerHTML = `
                <div class="legend-item"><div class="legend-color" style="background: #be123c;"></div><span>${judul}: tinggi (Q3)</span></div>
                <div class="legend-item"><div class="legend-color" style="background: #f59e0b;"></div><span>Sedang (M)</span></div>
                <div class="legend-item"><div class="legend-color" style="background: #047857;"></div><span>Rendah (Q1)</span></div>
                <div class="legend-item"><div class="legend-color" style="background: #e5e7eb;"></div><span>Tidak ada data</span></div>
            `;
```

(Keep the existing `if (currentMode === 'stunting')`/`gizi`/`imunisasi` branches only if they don't conflict — replace the old `stunting` and `gizi` legend branches with this unified quantile branch so they don't shadow it. Ensure the `imunisasi` branch and the final `else` count branch remain.)

- [ ] **Step 6: Show every prevalence in the popup**

In `getPopupContent(...)`, after the existing `Jumlah Anak` line, add a prioritas block sourced from the aggregate (so all indicators show regardless of mode):

```javascript
        const agg = agregatWilayah(type, name, feature);
        if (agg) {
            content += '<hr style="margin:0.3rem 0">';
            content += '<div class="popup-stat"><span>Stunting:</span><strong style="color:#be123c">' + agg.stunting + ' (' + agg.stunting_pct + '%)</strong></div>';
            content += '<div class="popup-stat"><span>Gizi Buruk:</span><strong style="color:#be123c">' + agg.gizi_buruk + '</strong></div>';
            content += '<div class="popup-stat"><span>Gizi Kurang:</span><strong style="color:#d97706">' + agg.gizi_kurang + '</strong></div>';
            content += '<div class="popup-stat"><span>Gizi Buruk/Kurang:</span><strong>' + agg.gizi_kurang_buruk_pct + '%</strong></div>';
            content += '<div class="popup-stat"><span>BB Tidak Naik:</span><strong style="color:#ea580c">' + agg.bb_tidak_naik + ' (' + agg.bb_tidak_naik_pct + '%)</strong></div>';
            content += '<div class="popup-stat"><span>Anak Prioritas:</span><strong>' + agg.anak_prioritas + '</strong></div>';
        }
```

(Insert this in place of / alongside the existing `zScore` popup block. Keep the `imunisasi`-mode popup branch as-is. If both the old `zScore` block and this new block would render duplicate stunting lines, remove the old `zScore` stunting/wasting lines and rely on this aggregate block.)

- [ ] **Step 7: Verify rendering (manual + test)**

Run: `php artisan test --filter=PetaKuantilViewTest`
Expected: PASS (the route renders the Blade with the new `@json` without error).

Then manually: seed snapshot (`php artisan prioritas:refresh`), `php artisan serve`, open `/admin/map`, and check: mode buttons **BB Tidak Naik** and **Anak Prioritas** appear; switching to Stunting/Gizi/BB Tidak Naik/Anak Prioritas recolors areas green→yellow→red by quantile; areas with no measured children stay grey; popups list Stunting/Gizi Buruk/Gizi Kurang/BB Tidak Naik/Anak Prioritas with counts and %.

- [ ] **Step 8: Commit**

```bash
git add resources/views/admin/dashboard/map.blade.php
git commit -m "feat(peta): pewarnaan kuantil per indikator + mode BB tidak naik & anak prioritas + popup prevalensi

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Catatan & risiko

- **Perubahan perilaku disengaja:** angka gizi peta beralih dari IMT/U ke BB/TB (via snapshot), sehingga konsisten dengan dashboard timbang & tab prioritas. `akumulasiStatistikGizi`/`getZScoreBy*Optimized` yang lama TIDAK dihapus (masih menyuplai mode `count`/legacy & summary `totalStunting`/`totalWasting`); hanya mode pewarnaan stunting/gizi yang kini memakai agregat snapshot. Bila di kemudian hari summary atas peta ingin ikut BB/TB, itu pekerjaan terpisah.
- **Nama wilayah sebagai kunci:** agregat di-key `w.name`; pencocokan ke GeoJSON tetap lewat resolver JS existing (`resolveKelurahanName`, `getRtLookup`). RT memakai `dbRTName` hasil `getRtLookup`.
- **Snapshot harus terisi:** peta kuantil kosong (semua abu-abu) bila `prioritas_gizi` belum di-`refresh`. `php artisan prioritas:refresh` wajib dijalankan sekali di dev/prod.
- **Kuantil untuk n kecil:** dengan sedikit wilayah, tertil bisa menaruh banyak wilayah dalam satu kelas; ini benar secara definisi (relatif antar wilayah), bukan bug.

## Self-review notes

- **Spec coverage (design.md Komponen 4):** kuantil Q1/M/Q3 → Task 1 (helper) + Task 3 (ambang) + Task 4 (warna). Per indikator (stunting/gizi/bbtn/prioritas) → Task 2 agregat + Task 3 indikatorNilai + Task 4 mode. Prevalensi di popup → Task 4 Step 6. Sumber snapshot → Task 2. Toggle Kec/Kel/RT → sudah ada, dipakai `agregatWilayah`.
- **Placeholder scan:** tidak ada TODO/TBD; tiap step berisi kode nyata. Titik "sisipkan/ganti blok existing" memberi instruksi eksplisit apa yang diganti.
- **Type consistency:** `ambangTertil`→`[bawah,atas]` dipakai konsisten Task 1/3/4; kunci agregat (`total,stunting,gizi_buruk,gizi_kurang,bb_tidak_naik,anak_prioritas,stunting_pct,gizi_kurang_buruk_pct,bb_tidak_naik_pct`) konsisten Task 2 (produce) → Task 3 (indikatorNilai) → Task 4 (popup/warna). Indikator key set `stunting|gizi|bbtn|prioritas` konsisten Task 3/4.
```

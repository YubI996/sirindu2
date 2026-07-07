# Plan 1 — Fondasi Snapshot + Subsection Prioritas P1–P3 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bangun tabel snapshot `prioritas_gizi` (satu baris per anak, dihitung via `StatusGiziService`) beserta mekanisme refresh, lalu tampilkan pengelompokan prioritas P1–P3 sebagai subsection collapsible di dalam halaman Early Warning + tambahkan baris wilker Puskesmas pada kartu anak.

**Architecture:** Snapshot pra-hitung menjadi sumber cepat & konsisten. `PrioritasGiziService` mengklasifikasi satu anak memakai `StatusGiziService` yang sudah ada (satu sumber kebenaran) + logika BB-tidak-naik existing. Refresh dipicu observer model untuk edit interaktif dan `refreshAll()` sesudah import massal (observer dibisukan saat import). Halaman Early Warning membaca subsection prioritas langsung dari snapshot (query ringan), skor gabungan lama tidak diubah.

**Tech Stack:** PHP 8 / Laravel 12, Eloquent, Maatwebsite Excel, PHPUnit (RefreshDatabase), Blade + Bootstrap 5.

## Global Constraints

- Satu sumber kebenaran klasifikasi gizi: `App\Services\StatusGiziService`. JANGAN menduplikasi rumus z-score.
- Wasting (gizi buruk/kurang) memakai indikator **BB/TB** (`enum['bb_tb']`), bukan IMT/U — konsisten dengan dashboard timbang.
- Stunting memakai **TB/U** (`enum['tb_u']`): `stunted` atau `severely_stunted`.
- `bb_tidak_naik`: pertahankan logika existing (BB kunjungan terakhir ≤ sebelumnya bila ada ≥2 kunjungan; selain itu `ntob='T'`). JANGAN mengubah definisi.
- Skor gabungan / risk score di `earlyWarningSystem` **tidak diubah**.
- Test menyuntik referensi via `StatusGiziService::useRefs([...])` lalu `flushCache()` di tearDown; pakai `RefreshDatabase`.
- Super-admin di test = `User::factory()->create(['type' => 0])`.
- Prioritas ditentukan paling-berat-menang: gizi buruk → 1; else stunting → 2; else bb tidak naik → 3; else null. Flag individual tetap disimpan.

---

### Task 1: Tabel snapshot `prioritas_gizi` + model

**Files:**
- Create: `database/migrations/2026_07_07_100000_create_prioritas_gizi_table.php`
- Create: `app/Models/PrioritasGizi.php`
- Test: `tests/Feature/PrioritasGizi/PrioritasGiziModelTest.php`

**Interfaces:**
- Produces: model `App\Models\PrioritasGizi` (table `prioritas_gizi`, `$guarded = []`), kolom: `id_anak`, `id_kec`, `id_kel`, `id_rt`, `id_posyandu`, `gizi_buruk`, `gizi_kurang`, `stunting`, `bb_tidak_naik`, `prioritas`, `usia_bln`, `refreshed_at`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\PrioritasGizi;

use App\Models\PrioritasGizi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrioritasGiziModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_dapat_menyimpan_dan_membaca_baris_snapshot(): void
    {
        $row = PrioritasGizi::create([
            'id_anak' => 1,
            'id_kel' => 5,
            'id_rt' => 10,
            'gizi_buruk' => true,
            'gizi_kurang' => false,
            'stunting' => true,
            'bb_tidak_naik' => false,
            'prioritas' => 1,
            'usia_bln' => 24,
            'refreshed_at' => now(),
        ]);

        $this->assertDatabaseHas('prioritas_gizi', ['id_anak' => 1, 'prioritas' => 1]);
        $this->assertTrue($row->fresh()->gizi_buruk);
        $this->assertSame(1, $row->fresh()->prioritas);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PrioritasGiziModelTest`
Expected: FAIL — table `prioritas_gizi` tidak ada / class `PrioritasGizi` not found.

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_07_07_100000_create_prioritas_gizi_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prioritas_gizi', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_anak')->unique();
            $table->unsignedBigInteger('id_kec')->nullable();
            $table->unsignedBigInteger('id_kel')->nullable();
            $table->unsignedBigInteger('id_rt')->nullable();
            $table->unsignedBigInteger('id_posyandu')->nullable();
            $table->boolean('gizi_buruk')->default(false);
            $table->boolean('gizi_kurang')->default(false);
            $table->boolean('stunting')->default(false);
            $table->boolean('bb_tidak_naik')->default(false);
            $table->unsignedTinyInteger('prioritas')->nullable();
            $table->integer('usia_bln')->nullable();
            $table->timestamp('refreshed_at')->nullable();
            $table->timestamps();

            $table->index('id_rt');
            $table->index('id_kel');
            $table->index('prioritas');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prioritas_gizi');
    }
};
```

- [ ] **Step 4: Write the model**

Create `app/Models/PrioritasGizi.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrioritasGizi extends Model
{
    protected $table = 'prioritas_gizi';
    protected $guarded = [];

    protected $casts = [
        'gizi_buruk' => 'boolean',
        'gizi_kurang' => 'boolean',
        'stunting' => 'boolean',
        'bb_tidak_naik' => 'boolean',
        'prioritas' => 'integer',
        'usia_bln' => 'integer',
        'refreshed_at' => 'datetime',
    ];

    public function anak()
    {
        return $this->belongsTo(Anak::class, 'id_anak', 'id');
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=PrioritasGiziModelTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_07_100000_create_prioritas_gizi_table.php app/Models/PrioritasGizi.php tests/Feature/PrioritasGizi/PrioritasGiziModelTest.php
git commit -m "feat(prioritas): tabel snapshot prioritas_gizi + model

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: `PrioritasGiziService::hitungUntukAnak()` — klasifikasi satu anak

**Files:**
- Create: `app/Services/PrioritasGiziService.php`
- Test: `tests/Feature/PrioritasGizi/PrioritasGiziServiceTest.php`

**Interfaces:**
- Consumes: `App\Services\StatusGiziService::klasifikasi($bb, $tb, $bln, $posisi, $jk)` → array dengan `enum['tb_u']`, `enum['bb_tb']`.
- Produces: `PrioritasGiziService::hitungUntukAnak(App\Models\Anak $anak): array` mengembalikan
  `['gizi_buruk'=>bool, 'gizi_kurang'=>bool, 'stunting'=>bool, 'bb_tidak_naik'=>bool, 'prioritas'=>?int, 'usia_bln'=>?int]`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\PrioritasGizi;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Services\PrioritasGiziService;
use App\Services\StatusGiziService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrioritasGiziServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // jk=1, umur 24 (var=2), tinggi 90. bb=12 → BB/TB severely_wasted (gizi buruk),
        // tinggi 90 < m2sd 83? tidak → TB/U normal. Lihat TimbangGiziBbTbTest utk pola.
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

    public function test_anak_gizi_buruk_diberi_prioritas_1(): void
    {
        $anak = Anak::create([
            'nama' => 'Balita Buruk', 'nik' => '3201000000009002', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1,
        ]);
        DataAnak::create([
            'id_anak' => $anak->id, 'tgl_kunjungan' => '2024-06-01', 'bln' => 24,
            'posisi' => 'berdiri', 'tb' => 90, 'bb' => 12, 'lla' => 0, 'lk' => 0, 'id_user' => 1,
        ]);

        $hasil = app(PrioritasGiziService::class)->hitungUntukAnak($anak->fresh());

        $this->assertTrue($hasil['gizi_buruk']);
        $this->assertSame(1, $hasil['prioritas']);
        $this->assertSame(24, $hasil['usia_bln']);
    }

    public function test_anak_tanpa_kunjungan_valid_prioritas_null(): void
    {
        $anak = Anak::create([
            'nama' => 'Tanpa Ukur', 'nik' => '3201000000009003', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1,
        ]);

        $hasil = app(PrioritasGiziService::class)->hitungUntukAnak($anak->fresh());

        $this->assertFalse($hasil['gizi_buruk']);
        $this->assertFalse($hasil['stunting']);
        $this->assertNull($hasil['prioritas']);
    }

    public function test_bb_tidak_naik_dari_dua_kunjungan(): void
    {
        $anak = Anak::create([
            'nama' => 'BB Turun', 'nik' => '3201000000009004', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2023-06-01', 'status' => 1,
        ]);
        // Dua kunjungan, BB terakhir <= sebelumnya, tanpa tb valid (fokus bb_tidak_naik).
        DataAnak::create(['id_anak' => $anak->id, 'tgl_kunjungan' => '2024-05-01', 'bln' => 11,
            'posisi' => 'telentang', 'tb' => 0, 'bb' => 9.0, 'lla' => 0, 'lk' => 0, 'id_user' => 1]);
        DataAnak::create(['id_anak' => $anak->id, 'tgl_kunjungan' => '2024-06-01', 'bln' => 12,
            'posisi' => 'telentang', 'tb' => 0, 'bb' => 8.9, 'lla' => 0, 'lk' => 0, 'id_user' => 1]);

        $hasil = app(PrioritasGiziService::class)->hitungUntukAnak($anak->fresh());

        $this->assertTrue($hasil['bb_tidak_naik']);
        $this->assertSame(3, $hasil['prioritas']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PrioritasGiziServiceTest`
Expected: FAIL — class `PrioritasGiziService` not found.

- [ ] **Step 3: Write the service**

Create `app/Services/PrioritasGiziService.php`:

```php
<?php

namespace App\Services;

use App\Models\Anak;
use App\Models\DataAnak;

/**
 * Menghitung & menyimpan snapshot prioritas gizi per anak.
 *
 * Klasifikasi status memakai StatusGiziService (satu sumber kebenaran).
 * bb_tidak_naik mengikuti logika TimbangDashboardController::bbTidakNaikIds
 * (BB kunjungan terakhir <= sebelumnya bila ada >=2 kunjungan; selain itu ntob='T').
 */
class PrioritasGiziService
{
    /** Saat true, observer melewati refresh (dipakai selama import massal). */
    public static bool $muted = false;

    public function __construct(private StatusGiziService $statusGizi) {}

    /**
     * @return array{gizi_buruk:bool,gizi_kurang:bool,stunting:bool,bb_tidak_naik:bool,prioritas:?int,usia_bln:?int}
     */
    public function hitungUntukAnak(Anak $anak): array
    {
        $giziBuruk = false;
        $giziKurang = false;
        $stunting = false;
        $usiaBln = null;

        // Kunjungan terakhir yang valid untuk klasifikasi status (<=60 bln, bb/tb > 0).
        $latest = DataAnak::where('id_anak', $anak->id)
            ->whereNotNull('tgl_kunjungan')
            ->where('bln', '<=', 60)
            ->where('bb', '>', 0)
            ->where('tb', '>', 0)
            ->orderByDesc('tgl_kunjungan')
            ->orderByDesc('id')
            ->first();

        if ($latest) {
            $usiaBln = (int) $latest->bln;
            $g = $this->statusGizi->klasifikasi($latest->bb, $latest->tb, $latest->bln, $latest->posisi, $anak->jk);
            $giziBuruk = $g['enum']['bb_tb'] === 'severely_wasted';
            $giziKurang = $g['enum']['bb_tb'] === 'wasted';
            $stunting = in_array($g['enum']['tb_u'], ['stunted', 'severely_stunted'], true);
        }

        $bbTidakNaik = $this->bbTidakNaik($anak->id);

        $prioritas = $giziBuruk ? 1 : ($stunting ? 2 : ($bbTidakNaik ? 3 : null));

        return [
            'gizi_buruk' => $giziBuruk,
            'gizi_kurang' => $giziKurang,
            'stunting' => $stunting,
            'bb_tidak_naik' => $bbTidakNaik,
            'prioritas' => $prioritas,
            'usia_bln' => $usiaBln,
        ];
    }

    /** BB tidak naik: 2 kunjungan terakhir turun/tetap, atau ntob='T' bila hanya 1 kunjungan. */
    private function bbTidakNaik(int $idAnak): bool
    {
        $visits = DataAnak::where('id_anak', $idAnak)
            ->whereNotNull('tgl_kunjungan')
            ->where('bb', '>', 0)
            ->orderByDesc('tgl_kunjungan')
            ->orderByDesc('id')
            ->get(['bb', 'ntob']);

        if ($visits->count() >= 2) {
            return (float) $visits[0]->bb <= (float) $visits[1]->bb;
        }
        if ($visits->count() === 1) {
            return strtoupper(trim((string) $visits[0]->ntob)) === 'T';
        }
        return false;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=PrioritasGiziServiceTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/PrioritasGiziService.php tests/Feature/PrioritasGizi/PrioritasGiziServiceTest.php
git commit -m "feat(prioritas): PrioritasGiziService.hitungUntukAnak klasifikasi P1-P3

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: `refreshAnak` / `refreshBatch` / `refreshAll` (tulis snapshot)

**Files:**
- Modify: `app/Services/PrioritasGiziService.php`
- Test: `tests/Feature/PrioritasGizi/PrioritasGiziRefreshTest.php`

**Interfaces:**
- Produces: `refreshAnak(int $idAnak): void`, `refreshBatch(array $idAnak): void`, `refreshAll(): int` (mengembalikan jumlah baris ditulis).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\PrioritasGizi;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\PrioritasGizi;
use App\Services\PrioritasGiziService;
use App\Services\StatusGiziService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrioritasGiziRefreshTest extends TestCase
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

    public function test_refresh_anak_menulis_baris_snapshot_dengan_wilayah(): void
    {
        $anak = Anak::create([
            'nama' => 'Balita Buruk', 'nik' => '3201000000009005', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1,
            'id_kel' => 7, 'id_rt' => 3,
        ]);
        DataAnak::create(['id_anak' => $anak->id, 'tgl_kunjungan' => '2024-06-01', 'bln' => 24,
            'posisi' => 'berdiri', 'tb' => 90, 'bb' => 12, 'lla' => 0, 'lk' => 0, 'id_user' => 1]);

        app(PrioritasGiziService::class)->refreshAnak($anak->id);

        $this->assertDatabaseHas('prioritas_gizi', [
            'id_anak' => $anak->id, 'prioritas' => 1, 'gizi_buruk' => 1, 'id_kel' => 7, 'id_rt' => 3,
        ]);
    }

    public function test_refresh_anak_idempoten_memakai_upsert(): void
    {
        $anak = Anak::create([
            'nama' => 'Balita', 'nik' => '3201000000009006', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1,
        ]);
        DataAnak::create(['id_anak' => $anak->id, 'tgl_kunjungan' => '2024-06-01', 'bln' => 24,
            'posisi' => 'berdiri', 'tb' => 90, 'bb' => 12, 'lla' => 0, 'lk' => 0, 'id_user' => 1]);

        $svc = app(PrioritasGiziService::class);
        $svc->refreshAnak($anak->id);
        $svc->refreshAnak($anak->id);

        $this->assertSame(1, PrioritasGizi::where('id_anak', $anak->id)->count());
    }

    public function test_refresh_all_menulis_semua_anak(): void
    {
        foreach (['3201000000009007', '3201000000009008'] as $i => $nik) {
            $anak = Anak::create([
                'nama' => "Anak {$i}", 'nik' => $nik, 'jk' => 1,
                'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1,
            ]);
            DataAnak::create(['id_anak' => $anak->id, 'tgl_kunjungan' => '2024-06-01', 'bln' => 24,
                'posisi' => 'berdiri', 'tb' => 90, 'bb' => 12, 'lla' => 0, 'lk' => 0, 'id_user' => 1]);
        }

        $ditulis = app(PrioritasGiziService::class)->refreshAll();

        $this->assertSame(2, $ditulis);
        $this->assertSame(2, PrioritasGizi::count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PrioritasGiziRefreshTest`
Expected: FAIL — method `refreshAnak` not defined.

- [ ] **Step 3: Add refresh methods to the service**

Add `use` imports and methods to `app/Services/PrioritasGiziService.php`. At the top add:

```php
use App\Models\PrioritasGizi;
```

Add these methods to the class:

```php
    public function refreshAnak(int $idAnak): void
    {
        $anak = Anak::find($idAnak);
        if (!$anak) {
            PrioritasGizi::where('id_anak', $idAnak)->delete();
            return;
        }

        $hasil = $this->hitungUntukAnak($anak);

        PrioritasGizi::updateOrCreate(
            ['id_anak' => $anak->id],
            $hasil + [
                'id_kec' => $anak->id_kec,
                'id_kel' => $anak->id_kel,
                'id_rt' => $anak->id_rt,
                'id_posyandu' => $anak->id_posyandu,
                'refreshed_at' => now(),
            ]
        );
    }

    /** @param array<int> $idAnak */
    public function refreshBatch(array $idAnak): void
    {
        foreach (array_unique($idAnak) as $id) {
            $this->refreshAnak((int) $id);
        }
    }

    public function refreshAll(): int
    {
        $ditulis = 0;
        Anak::query()->select('id')->chunkById(500, function ($anaks) use (&$ditulis) {
            foreach ($anaks as $a) {
                $this->refreshAnak($a->id);
                $ditulis++;
            }
        });
        return $ditulis;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=PrioritasGiziRefreshTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/PrioritasGiziService.php tests/Feature/PrioritasGizi/PrioritasGiziRefreshTest.php
git commit -m "feat(prioritas): refreshAnak/refreshBatch/refreshAll tulis snapshot

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 4: Observer model + registrasi (refresh interaktif)

**Files:**
- Create: `app/Observers/DataAnakObserver.php`
- Create: `app/Observers/AnakObserver.php`
- Modify: `app/Providers/AppServiceProvider.php` (method `boot`)
- Test: `tests/Feature/PrioritasGizi/PrioritasGiziObserverTest.php`

**Interfaces:**
- Consumes: `PrioritasGiziService::refreshAnak`, flag `PrioritasGiziService::$muted`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\PrioritasGizi;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\PrioritasGizi;
use App\Services\StatusGiziService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrioritasGiziObserverTest extends TestCase
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

    public function test_membuat_data_anak_mengisi_snapshot(): void
    {
        $anak = Anak::create([
            'nama' => 'Balita Buruk', 'nik' => '3201000000009009', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1,
        ]);
        DataAnak::create(['id_anak' => $anak->id, 'tgl_kunjungan' => '2024-06-01', 'bln' => 24,
            'posisi' => 'berdiri', 'tb' => 90, 'bb' => 12, 'lla' => 0, 'lk' => 0, 'id_user' => 1]);

        $this->assertDatabaseHas('prioritas_gizi', ['id_anak' => $anak->id, 'prioritas' => 1]);
    }

    public function test_menghapus_anak_menghapus_snapshot(): void
    {
        $anak = Anak::create([
            'nama' => 'Balita', 'nik' => '3201000000009010', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1,
        ]);
        DataAnak::create(['id_anak' => $anak->id, 'tgl_kunjungan' => '2024-06-01', 'bln' => 24,
            'posisi' => 'berdiri', 'tb' => 90, 'bb' => 12, 'lla' => 0, 'lk' => 0, 'id_user' => 1]);
        $this->assertDatabaseHas('prioritas_gizi', ['id_anak' => $anak->id]);

        $anak->delete();

        $this->assertDatabaseMissing('prioritas_gizi', ['id_anak' => $anak->id]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PrioritasGiziObserverTest`
Expected: FAIL — snapshot tidak terisi (observer belum ada).

- [ ] **Step 3: Write the observers**

Create `app/Observers/DataAnakObserver.php`:

```php
<?php

namespace App\Observers;

use App\Models\DataAnak;
use App\Services\PrioritasGiziService;

class DataAnakObserver
{
    public function __construct(private PrioritasGiziService $service) {}

    public function saved(DataAnak $dataAnak): void
    {
        if (PrioritasGiziService::$muted) return;
        $this->service->refreshAnak((int) $dataAnak->id_anak);
    }

    public function deleted(DataAnak $dataAnak): void
    {
        if (PrioritasGiziService::$muted) return;
        $this->service->refreshAnak((int) $dataAnak->id_anak);
    }
}
```

Create `app/Observers/AnakObserver.php`:

```php
<?php

namespace App\Observers;

use App\Models\Anak;
use App\Models\PrioritasGizi;
use App\Services\PrioritasGiziService;

class AnakObserver
{
    public function __construct(private PrioritasGiziService $service) {}

    public function saved(Anak $anak): void
    {
        if (PrioritasGiziService::$muted) return;
        $this->service->refreshAnak((int) $anak->id);
    }

    public function deleted(Anak $anak): void
    {
        if (PrioritasGiziService::$muted) return;
        PrioritasGizi::where('id_anak', $anak->id)->delete();
    }
}
```

- [ ] **Step 4: Register observers in AppServiceProvider**

In `app/Providers/AppServiceProvider.php`, add imports after the existing `use` lines:

```php
use App\Models\Anak;
use App\Models\DataAnak;
use App\Observers\AnakObserver;
use App\Observers\DataAnakObserver;
```

At the end of the `boot()` method body (after the RateLimiter block), add:

```php
        DataAnak::observe(DataAnakObserver::class);
        Anak::observe(AnakObserver::class);
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=PrioritasGiziObserverTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Run the full prioritas suite to check no regressions**

Run: `php artisan test --filter=PrioritasGizi`
Expected: PASS (all tasks 1–4 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Observers/DataAnakObserver.php app/Observers/AnakObserver.php app/Providers/AppServiceProvider.php tests/Feature/PrioritasGizi/PrioritasGiziObserverTest.php
git commit -m "feat(prioritas): observer DataAnak/Anak auto-refresh snapshot

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 5: Command `prioritas:refresh` + hook refresh sesudah import

**Files:**
- Create: `app/Console/Commands/RefreshPrioritasGizi.php`
- Modify: `app/Jobs/ImportUkurJob.php`
- Modify: `app/Console/Commands/ImportOperasiTimbang.php`
- Test: `tests/Feature/PrioritasGizi/RefreshPrioritasGiziCommandTest.php`

**Interfaces:**
- Consumes: `PrioritasGiziService::refreshAll()`, `PrioritasGiziService::$muted`.
- Produces: artisan command signature `prioritas:refresh`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\PrioritasGizi;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\PrioritasGizi;
use App\Services\StatusGiziService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefreshPrioritasGiziCommandTest extends TestCase
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

    public function test_command_membangun_ulang_snapshot(): void
    {
        $anak = Anak::create([
            'nama' => 'Balita Buruk', 'nik' => '3201000000009011', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1,
        ]);
        DataAnak::create(['id_anak' => $anak->id, 'tgl_kunjungan' => '2024-06-01', 'bln' => 24,
            'posisi' => 'berdiri', 'tb' => 90, 'bb' => 12, 'lla' => 0, 'lk' => 0, 'id_user' => 1]);
        // Kosongkan snapshot untuk membuktikan command mengisinya kembali.
        PrioritasGizi::query()->delete();

        $this->artisan('prioritas:refresh')->assertExitCode(0);

        $this->assertDatabaseHas('prioritas_gizi', ['id_anak' => $anak->id, 'prioritas' => 1]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RefreshPrioritasGiziCommandTest`
Expected: FAIL — command `prioritas:refresh` tidak dikenal.

- [ ] **Step 3: Write the command**

Create `app/Console/Commands/RefreshPrioritasGizi.php`:

```php
<?php

namespace App\Console\Commands;

use App\Services\PrioritasGiziService;
use Illuminate\Console\Command;

class RefreshPrioritasGizi extends Command
{
    protected $signature = 'prioritas:refresh';
    protected $description = 'Bangun ulang snapshot prioritas_gizi untuk seluruh anak';

    public function handle(PrioritasGiziService $service): int
    {
        $this->info('Membangun ulang snapshot prioritas gizi...');
        $jumlah = $service->refreshAll();
        $this->info("Selesai. {$jumlah} anak diproses.");
        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Hook refresh into import Ukur job**

In `app/Jobs/ImportUkurJob.php`, wrap the existing import execution so observers are muted during bulk insert and snapshot is rebuilt once afterward. Add import at top:

```php
use App\Services\PrioritasGiziService;
```

Locate where `$import = new UkurImport(...)` runs the import (around line 36) and the import is executed. Set the mute flag before it runs and rebuild after it completes:

```php
        PrioritasGiziService::$muted = true;
        try {
            // ... existing import execution (Excel::import / $import run) ...
        } finally {
            PrioritasGiziService::$muted = false;
        }

        app(PrioritasGiziService::class)->refreshAll();
```

(Keep the existing import body inside the `try`. Only add the mute wrapper and the `refreshAll()` call.)

- [ ] **Step 5: Hook refresh into Operasi Timbang command**

In `app/Console/Commands/ImportOperasiTimbang.php`, add import at top:

```php
use App\Services\PrioritasGiziService;
```

Wrap the commit run (around line 63 where `$import = new OperasiTimbangImport(...)` executes with `$commit`) with the same mute pattern, and after the import completes AND only when `$commit` is true, rebuild:

```php
        PrioritasGiziService::$muted = true;
        try {
            // ... existing import execution ...
        } finally {
            PrioritasGiziService::$muted = false;
        }

        if ($commit) {
            app(PrioritasGiziService::class)->refreshAll();
        }
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=RefreshPrioritasGiziCommandTest`
Expected: PASS.

- [ ] **Step 7: Run full prioritas suite**

Run: `php artisan test --filter=PrioritasGizi`
Expected: PASS.

- [ ] **Step 8: Seed snapshot once for existing data (manual, non-test)**

Run: `php artisan prioritas:refresh`
Expected: "Selesai. N anak diproses." (N = jumlah anak di DB dev).

- [ ] **Step 9: Commit**

```bash
git add app/Console/Commands/RefreshPrioritasGizi.php app/Jobs/ImportUkurJob.php app/Console/Commands/ImportOperasiTimbang.php tests/Feature/PrioritasGizi/RefreshPrioritasGiziCommandTest.php
git commit -m "feat(prioritas): command prioritas:refresh + rebuild sesudah import

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 6: Controller — kirim data subsection P1–P3 + wilker ke view

**Files:**
- Modify: `app/Http/Controllers/AdminController.php` (method `earlyWarningSystem`, sekitar baris 1794)
- Test: `tests/Feature/PrioritasGizi/EarlyWarningPrioritasTest.php`

**Interfaces:**
- Consumes: model `PrioritasGizi`, `App\Support\WilkerPuskesmas::wilkerForKelurahanId(?int): string`.
- Produces: view `admin.dashboard.early-warning` menerima variabel `$prioritasTiers` — array dengan kunci `1`,`2`,`3`, tiap nilai array baris `['nama','nik','usia_bln','posyandu','puskesmas','kelurahan','rt','hashid']`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\PrioritasGizi;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\User;
use App\Services\StatusGiziService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EarlyWarningPrioritasTest extends TestCase
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

    public function test_view_menerima_tier_prioritas_dari_snapshot(): void
    {
        $superAdmin = User::factory()->create(['type' => 0]);
        $anak = Anak::create([
            'nama' => 'Balita Buruk', 'nik' => '3201000000009012', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1,
        ]);
        DataAnak::create(['id_anak' => $anak->id, 'tgl_kunjungan' => '2024-06-01', 'bln' => 24,
            'posisi' => 'berdiri', 'tb' => 90, 'bb' => 12, 'lla' => 0, 'lk' => 0, 'id_user' => 1]);

        $response = $this->actingAs($superAdmin)->get(route('admin.earlyWarning'));

        $response->assertStatus(200);
        $tiers = $response->viewData('prioritasTiers');
        $this->assertArrayHasKey(1, $tiers);
        $this->assertCount(1, $tiers[1]);
        $this->assertSame('Balita Buruk', $tiers[1][0]['nama']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=EarlyWarningPrioritasTest`
Expected: FAIL — `viewData('prioritasTiers')` null.

- [ ] **Step 3: Build the tiers in the controller**

In `app/Http/Controllers/AdminController.php`, at the top of the file ensure imports exist (add if missing):

```php
use App\Models\PrioritasGizi;
use App\Support\WilkerPuskesmas;
```

Inside `earlyWarningSystem()`, just before the final `return view(...)`, build the tiers from the snapshot (ringan; tidak menghitung ulang):

```php
        $prioritasRows = PrioritasGizi::query()
            ->whereNotNull('prioritas')
            ->join('anak', 'anak.id', '=', 'prioritas_gizi.id_anak')
            ->leftJoin('kelurahan', 'anak.id_kel', '=', 'kelurahan.id')
            ->leftJoin('rt', 'anak.id_rt', '=', 'rt.id')
            ->leftJoin('posyandu', 'anak.id_posyandu', '=', 'posyandu.id')
            ->select(
                'prioritas_gizi.prioritas',
                'prioritas_gizi.usia_bln',
                'anak.id as anak_id',
                'anak.nama',
                'anak.nik',
                'anak.id_kel',
                'kelurahan.name as kelurahan',
                'rt.name as rt',
                'posyandu.name as posyandu'
            )
            ->orderBy('anak.nama')
            ->get();

        $prioritasTiers = [1 => [], 2 => [], 3 => []];
        foreach ($prioritasRows as $r) {
            $prioritasTiers[(int) $r->prioritas][] = [
                'nama' => $r->nama,
                'nik' => $r->nik,
                'usia_bln' => $r->usia_bln,
                'posyandu' => $r->posyandu ?: '-',
                'puskesmas' => WilkerPuskesmas::wilkerForKelurahanId($r->id_kel ? (int) $r->id_kel : null) ?: '-',
                'kelurahan' => $r->kelurahan ?: '-',
                'rt' => $r->rt ?: '-',
                'hashid' => \App\Models\Anak::find($r->anak_id)?->hashid,
            ];
        }
```

Then add `'prioritasTiers' => $prioritasTiers` to the `compact(...)`/array passed into `return view('admin.dashboard.early-warning', [...])`. (Find the existing `return view(...)` in this method and add the key to the data array.)

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=EarlyWarningPrioritasTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/AdminController.php tests/Feature/PrioritasGizi/EarlyWarningPrioritasTest.php
git commit -m "feat(prioritas): earlyWarning kirim tier P1-P3 + wilker ke view

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 7: View — subsection collapsible P1–P3 + baris wilker di kartu anak

**Files:**
- Modify: `resources/views/admin/dashboard/early-warning.blade.php`

**Interfaces:**
- Consumes: `$prioritasTiers` (dari Task 6); kartu anak existing `$paginatedList` (tambah baris Puskesmas).

- [ ] **Step 1: Add the collapsible priority subsections**

In `resources/views/admin/dashboard/early-warning.blade.php`, immediately after the `<div class="section-title"> ... Daftar Prioritas Intervensi ...</div>` block (around line 813–822) and BEFORE `<div id="childrenList">`, insert:

```blade
{{-- Subsection Prioritas Berjenjang (P1–P3) dari snapshot prioritas_gizi --}}
@php
    $tierMeta = [
        1 => ['label' => 'Prioritas 1 — Gizi Buruk', 'cls' => 'high'],
        2 => ['label' => 'Prioritas 2 — Stunting', 'cls' => 'medium'],
        3 => ['label' => 'Prioritas 3 — BB Tidak Naik 2 Bulan', 'cls' => 'low'],
    ];
@endphp
<div class="mb-4">
    @foreach($tierMeta as $tier => $meta)
    @php $rows = $prioritasTiers[$tier] ?? []; @endphp
    <div class="alert-card">
        <button type="button"
                class="alert-card-header {{ $meta['cls'] }} w-100 text-left border-0"
                style="display:flex;justify-content:space-between;align-items:center;cursor:pointer;"
                aria-expanded="false" aria-controls="tierBody{{ $tier }}"
                onclick="toggleTier({{ $tier }}, this)">
            <span><i class="fa fa-layer-group mr-2"></i>{{ $meta['label'] }}</span>
            <span class="badge badge-light">{{ count($rows) }} anak</span>
        </button>
        <div class="alert-card-body" id="tierBody{{ $tier }}" style="display:none;">
            @if(count($rows))
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>No</th><th>Nama</th><th>NIK</th><th>Usia (bln)</th>
                            <th>Posyandu</th><th>Puskesmas</th><th>Kelurahan</th><th>RT</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $i => $r)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td><strong>{{ $r['nama'] }}</strong></td>
                            <td>{{ $r['nik'] }}</td>
                            <td>{{ $r['usia_bln'] ?? '-' }}</td>
                            <td>{{ $r['posyandu'] }}</td>
                            <td>{{ $r['puskesmas'] }}</td>
                            <td>{{ $r['kelurahan'] }}</td>
                            <td>{{ $r['rt'] }}</td>
                            <td>
                                @if($r['hashid'])
                                <a href="{{ route('admin.showAnak', $r['hashid']) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fa fa-eye"></i>
                                </a>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <p class="text-muted mb-0">Tidak ada anak pada tingkat ini.</p>
            @endif
        </div>
    </div>
    @endforeach

    {{-- P4 nonaktif (butuh data keluarga miskin) --}}
    <div class="alert-card">
        <div class="alert-card-header" style="background:#9ca3af;display:flex;justify-content:space-between;align-items:center;">
            <span><i class="fa fa-lock mr-2"></i>Prioritas 4 — Keluarga Miskin Berisiko</span>
            <span class="badge badge-light" title="Butuh data keluarga miskin (DTKS/P3KE) — belum tersedia">Belum tersedia</span>
        </div>
    </div>
</div>
```

- [ ] **Step 2: Add the toggle script**

In the same file, inside the `@section('scripts')` block (after the opening `<script>` near line 1068), add:

```javascript
function toggleTier(tier, btn) {
    var body = document.getElementById('tierBody' + tier);
    if (!body) return;
    var open = body.style.display !== 'none';
    body.style.display = open ? 'none' : 'block';
    btn.setAttribute('aria-expanded', open ? 'false' : 'true');
}
```

- [ ] **Step 3: Add the Puskesmas row to each child card**

In the existing child card `child-info` grid (around line 847–880), after the Kecamatan `child-info-item` block, add a Puskesmas item. Add this block right after the Kecamatan `<div class="child-info-item">...</div>`:

```blade
                <div class="child-info-item">
                    <strong>Puskesmas</strong>
                    <span>{{ \App\Support\WilkerPuskesmas::wilkerForKelurahanId(optional($child)['id_kel'] ?? null) ?: '-' }}</span>
                </div>
```

Note: `$child` is an array in this loop. If `id_kel` is not present in `$child`, extend Task 6's per-child data assembly (the `$childData` array in `earlyWarningSystem`) to include `'id_kel' => $kelurahan?->id`, then use `{{ \App\Support\WilkerPuskesmas::wilkerForKelurahanId($child['id_kel'] ?? null) ?: '-' }}`.

- [ ] **Step 4: Verify in browser (manual)**

Run: `php artisan serve` then open `/admin/early-warning` as an admin.
Expected: Tiga panel collapsible "Prioritas 1/2/3" dengan jumlah anak; klik header membuka tabel berisi kolom Puskesmas; panel "Prioritas 4" tampil nonaktif; kartu anak menampilkan baris Puskesmas.

- [ ] **Step 5: Run the early-warning test to ensure no view errors**

Run: `php artisan test --filter=EarlyWarningPrioritasTest`
Expected: PASS (view renders without exception).

- [ ] **Step 6: Commit**

```bash
git add resources/views/admin/dashboard/early-warning.blade.php app/Http/Controllers/AdminController.php
git commit -m "feat(prioritas): subsection collapsible P1-P3 + baris wilker di kartu anak

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 8: Export Excel per tier prioritas

**Files:**
- Create: `app/Exports/PrioritasTierExport.php`
- Modify: `routes/web.php` (setelah baris 65)
- Modify: `app/Http/Controllers/AdminController.php` (method baru `exportPrioritasTier`)
- Modify: `resources/views/admin/dashboard/early-warning.blade.php` (tombol export di tiap panel)
- Test: `tests/Feature/PrioritasGizi/ExportPrioritasTierTest.php`

**Interfaces:**
- Consumes: `$prioritasTiers` shape dari Task 6.
- Produces: route name `admin.prioritas.export`, method `AdminController::exportPrioritasTier(Request $request)`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\PrioritasGizi;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\User;
use App\Services\StatusGiziService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportPrioritasTierTest extends TestCase
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

    public function test_export_tier_1_mengembalikan_file_xlsx(): void
    {
        $superAdmin = User::factory()->create(['type' => 0]);
        $anak = Anak::create([
            'nama' => 'Balita Buruk', 'nik' => '3201000000009013', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1,
        ]);
        DataAnak::create(['id_anak' => $anak->id, 'tgl_kunjungan' => '2024-06-01', 'bln' => 24,
            'posisi' => 'berdiri', 'tb' => 90, 'bb' => 12, 'lla' => 0, 'lk' => 0, 'id_user' => 1]);

        $response = $this->actingAs($superAdmin)->get(route('admin.prioritas.export', ['tier' => 1]));

        $response->assertStatus(200);
        $this->assertStringContainsString(
            'spreadsheetml',
            $response->headers->get('content-type')
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ExportPrioritasTierTest`
Expected: FAIL — route `admin.prioritas.export` tidak ada.

- [ ] **Step 3: Write the export class**

Create `app/Exports/PrioritasTierExport.php`:

```php
<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PrioritasTierExport implements FromArray, WithHeadings
{
    /** @param array<int,array<string,mixed>> $rows */
    public function __construct(private array $rows, private string $judul) {}

    public function array(): array
    {
        return array_map(fn ($r) => [
            $r['nama'], $r['nik'], $r['usia_bln'] ?? '-',
            $r['posyandu'], $r['puskesmas'], $r['kelurahan'], $r['rt'],
        ], $this->rows);
    }

    public function headings(): array
    {
        return ['Nama', 'NIK', 'Usia (bln)', 'Posyandu', 'Puskesmas', 'Kelurahan', 'RT'];
    }
}
```

- [ ] **Step 4: Add the controller method**

In `app/Http/Controllers/AdminController.php`, add at top if missing:

```php
use App\Exports\PrioritasTierExport;
use Maatwebsite\Excel\Facades\Excel;
```

Add a method. To avoid duplicating the tier-assembly SQL from Task 6, extract that assembly into a private helper `buildPrioritasTiers(): array` (move the Task 6 code into it, and have `earlyWarningSystem` call `$prioritasTiers = $this->buildPrioritasTiers();`). Then:

```php
    public function exportPrioritasTier(Request $request)
    {
        $tier = (int) $request->query('tier', 1);
        $tiers = $this->buildPrioritasTiers();
        $rows = $tiers[$tier] ?? [];

        $judul = ['Prioritas 1 - Gizi Buruk', 'Prioritas 2 - Stunting', 'Prioritas 3 - BB Tidak Naik'][$tier - 1] ?? 'Prioritas';
        $file = 'prioritas-' . $tier . '-' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new PrioritasTierExport($rows, $judul), $file);
    }
```

- [ ] **Step 5: Add the route**

In `routes/web.php`, after line 65 (`admin.exportVaccineNeeds`), add:

```php
    Route::get('early-warning/prioritas/export', [App\Http\Controllers\AdminController::class, 'exportPrioritasTier'])->name('admin.prioritas.export');
```

- [ ] **Step 6: Add export buttons to each panel**

In `resources/views/admin/dashboard/early-warning.blade.php`, inside the tier panel header button block from Task 7, add an export link next to the count badge. Replace the closing `</button>` area of each tier header by adding, right after the button, an anchor (place it inside the `.alert-card`, after the header button):

```blade
        <div style="padding:0.5rem 1rem;background:#f9fafb;">
            <a href="{{ route('admin.prioritas.export', ['tier' => $tier]) }}" class="btn btn-sm btn-outline-success">
                <i class="fa fa-file-excel mr-1"></i> Export Prioritas {{ $tier }}
            </a>
        </div>
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --filter=ExportPrioritasTierTest`
Expected: PASS.

- [ ] **Step 8: Run the full prioritas suite**

Run: `php artisan test --filter=PrioritasGizi`
Expected: PASS (all tasks).

- [ ] **Step 9: Commit**

```bash
git add app/Exports/PrioritasTierExport.php routes/web.php app/Http/Controllers/AdminController.php resources/views/admin/dashboard/early-warning.blade.php tests/Feature/PrioritasGizi/ExportPrioritasTierTest.php
git commit -m "feat(prioritas): export Excel per tier P1-P3

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Rencana lanjutan (plan terpisah)

Fondasi `prioritas_gizi` yang dibangun plan ini memblokir & menjadi sumber data untuk:

- **Plan 2 — Peta RT kuantil:** agregasi `prioritas_gizi` per RT/Kelurahan/Kecamatan; pewarnaan kuantil Q1 hijau → M kuning → Q3 merah per mode indikator (stunting %, gizi buruk/kurang %, BB tidak naik %, jumlah anak prioritas); popup menampilkan prevalensi tiap indikator. Ubah `resources/views/admin/dashboard/map.blade.php` + endpoint agregasi.
- **Plan 3 — Modul Intervensi Gizi:** tabel `intervensi_gizi` (log per anak), CRUD, rekap cakupan "X dari Y anak prioritas sudah diintervensi", tombol "+ Intervensi" dari daftar prioritas.

Kedua plan ditulis setelah Plan 1 dieksekusi/di-review.

## Self-review notes

- **Spec coverage:** Komponen 1 (snapshot) → Task 1–5. Komponen 2 (subsection P1–P3) → Task 6–8. Komponen 3 (wilker) → Task 6 (data) + Task 7 (tampilan). Komponen 4 (peta) & 5 (intervensi) → plan terpisah (dinyatakan). Poin 7 (miskin) → panel P4 nonaktif (Task 7).
- **Placeholder scan:** tidak ada TODO/TBD; setiap step berisi kode nyata. Dua titik "temukan blok existing" (import job body, return view) memberi instruksi eksplisit apa yang ditambahkan.
- **Type consistency:** shape baris tier (`nama,nik,usia_bln,posyandu,puskesmas,kelurahan,rt,hashid`) konsisten antara Task 6 (produce), Task 7 (view), Task 8 (export memakai subset). `PrioritasGiziService::$muted` konsisten Task 3/4/5. `refreshAll(): int` konsisten Task 3/5.

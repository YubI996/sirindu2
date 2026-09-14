# Verifikasi RT — Tahap 3 (Penggabungan dengan Pemilih Kolom + Log + Batalkan) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tautan `sama` yang sudah disetujui dieksekusi Dinkes menjadi satu baris `anak` lewat halaman pemilih kolom; seluruh data anak (pengukuran, imunisasi, intervensi, verifikasi) pindah ke baris yang dipertahankan; penggabungan tercatat di `anak_merge_log` dan bisa dibatalkan.

**Architecture:** `IdentitasMergeService` memegang tiga operasi — `pratinjau` (baris mana dipertahankan + nilai default per kolom), `gabung` (satu transaksi, urutan pindah-dulu-hapus-kemudian), `batalkan` (pulihkan dari snapshot). Controller superadmin tipis + dua view (antrean/log, pemilih kolom). Tidak ada kueri dasbor yang disentuh.

**Tech Stack:** Laravel 12, PHP 8.4, MySQL 8 (JSON column), Blade (layout admin/Bootstrap 4), PHPUnit 11.

**Spec:** `docs/superpowers/specs/2026-09-14-verifikasi-rt-design.md` — §3.1 `anak_merge_log`, §3.2 `sumber_gabungan`, §6.3, §10 f–g.

## Global Constraints

- **Baris `sumber='operasi_timbang'` tidak pernah dihapus**: bila salah satu OT → OT yang dipertahankan (`sumber` tetap OT); keduanya OT → merge **ditolak**. Dasbor OT tidak berubah (dikunci tes di `TimbangDashboardTerkunciTest`).
- FK `data_anak.id_anak`, `imunisasi.id_anak`, `verifikasi_anak.id_anak`, `anak_kandidat.*` ber-`ON DELETE CASCADE` → **pindahkan dulu, hapus kemudian**. `intervensi_gizi.id_anak` & `prioritas_gizi.id_anak` tanpa FK (`prioritas_gizi.id_anak` UNIQUE) → pindah manual; `anak_tautan` tanpa FK.
- `prioritas_gizi` adalah snapshot turunan: baris milik yang dihapus dibuang, lalu `PrioritasGiziService::refreshAnak($idDipertahankan)` (bukan dipindah).
- Kolom yang bisa dipilih: `nik, no_kk, nama, nama_ibu, nama_ayah, jk, tempat_lahir, tgl_lahir, alamat, alamat_ktp, id_kec, id_kel, id_rt, id_posyandu, id_puskesmas, golda, anak, catatan`. Default: identitas (`nik, no_kk, nama, nama_ibu, nama_ayah, jk, tempat_lahir, tgl_lahir, alamat_ktp`) dari baris `capil`, domisili (`alamat, id_kec, id_kel, id_rt, id_posyandu, id_puskesmas`) dari baris non-capil, sisanya dari yang dipertahankan.
- `sumber_gabungan` = daftar unik `sumber` kedua baris (+ isi `sumber_gabungan` lama masing-masing). `pj_nama/pj_updated_*` dari yang dihapus dipakai hanya bila yang dipertahankan kosong.
- Hanya **superadmin** yang boleh menggabungkan/membatalkan (`abort_if(!isSuperAdmin(), 403)`), lewat rute di grup `['auth','is_admin']`.
- Semua tulisan ke `anak` di tahap ini lewat Eloquent boleh menyentuh `updated_at` (ini perubahan data sungguhan).
- Jalankan PHP lewat path penuh; satu proses PHPUnit pada satu waktu; commit lokal di `feat/verifikasi-rt`; patch multi-baris via Edit tool / skrip scratchpad.

---

## File Structure

| Berkas | Tanggung jawab |
|---|---|
| `database/migrations/2026_09_17_000001_create_anak_merge_log_table.php` | log + snapshot |
| `database/migrations/2026_09_17_000002_add_sumber_gabungan_to_anak_table.php` | kolom JSON |
| `app/Models/AnakMergeLog.php` | model |
| `app/Services/IdentitasMergeService.php` | pratinjau / gabung / batalkan |
| `app/Http/Controllers/MergeIdentitasController.php` | antrean gabung, pemilih kolom, eksekusi, log, batalkan |
| `resources/views/admin/verifikasi-rt/gabung/index.blade.php` | antrean "menunggu penggabungan" + log |
| `resources/views/admin/verifikasi-rt/gabung/show.blade.php` | pemilih kolom |
| `resources/views/admin/verifikasi-rt/index.blade.php` (modify) | tautan ke antrean gabung |
| `routes/web.php` (modify) | 5 rute |
| `tests/Feature/VerifikasiRt/MergeSkemaTest.php`, `MergePratinjauTest.php`, `MergeGabungTest.php`, `MergeBatalkanTest.php`, `MergeControllerTest.php`; `tests/Feature/TimbangDashboardTerkunciTest.php` (modify) | tes |

---

### Task 1: Skema `anak_merge_log` + `anak.sumber_gabungan`

**Files:**
- Create: `database/migrations/2026_09_17_000001_create_anak_merge_log_table.php`
- Create: `database/migrations/2026_09_17_000002_add_sumber_gabungan_to_anak_table.php`
- Create: `app/Models/AnakMergeLog.php`
- Modify: `app/Models/Anak.php` (cast `sumber_gabungan` → `array`)
- Test: `tests/Feature/VerifikasiRt/MergeSkemaTest.php`

**Interfaces:**
- Produces `AnakMergeLog` (tabel `anak_merge_log`; kolom `id_dipertahankan, id_dihapus, id_tautan, snapshot (JSON→array), oleh, dibatalkan_oleh, dibatalkan_at, timestamps`; relasi `dipertahankan()`, `tautan()`, `pelaku()`, `pembatal()`; scope `aktif()` = `dibatalkan_at IS NULL`), `anak.sumber_gabungan` (JSON nullable, cast array).

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php
// tests/Feature/VerifikasiRt/MergeSkemaTest.php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Anak;
use App\Models\AnakMergeLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MergeSkemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabel_log_dan_kolom_sumber_gabungan_ada(): void
    {
        $this->assertTrue(Schema::hasColumns('anak_merge_log', ['id_dipertahankan', 'id_dihapus', 'id_tautan', 'snapshot', 'oleh', 'dibatalkan_oleh', 'dibatalkan_at']));
        $this->assertTrue(Schema::hasColumn('anak', 'sumber_gabungan'));
    }

    public function test_log_menyimpan_snapshot_array_dan_scope_aktif(): void
    {
        $u = User::factory()->create(['type' => 0]);
        $a = Anak::create(['nama' => 'A', 'nik' => '3201000000016001', 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'operasi_timbang', 'sumber_gabungan' => ['operasi_timbang', 'capil']]);

        $log = AnakMergeLog::create(['id_dipertahankan' => $a->id, 'id_dihapus' => 999, 'snapshot' => ['anak' => ['nik' => 'x']], 'oleh' => $u->id]);

        $this->assertSame(['nik' => 'x'], $log->fresh()->snapshot['anak']);
        $this->assertSame(['operasi_timbang', 'capil'], $a->fresh()->sumber_gabungan);
        $this->assertSame(1, AnakMergeLog::aktif()->count());
        $log->update(['dibatalkan_at' => now(), 'dibatalkan_oleh' => $u->id]);
        $this->assertSame(0, AnakMergeLog::aktif()->count());
        $this->assertSame($a->id, $log->dipertahankan->id);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal** — `…/phpunit tests/Feature/VerifikasiRt/MergeSkemaTest.php` → FAIL `Class "App\Models\AnakMergeLog" not found`.

- [ ] **Step 3: Migrasi & model**

```php
<?php
// database/migrations/2026_09_17_000001_create_anak_merge_log_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Jejak penggabungan dua baris anak (spec verifikasi RT §3.1) — snapshot cukup untuk membatalkan. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anak_merge_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_dipertahankan')->index();
            $table->unsignedBigInteger('id_dihapus')->index();
            $table->unsignedBigInteger('id_tautan')->nullable();
            $table->json('snapshot');
            $table->unsignedBigInteger('oleh');
            $table->unsignedBigInteger('dibatalkan_oleh')->nullable();
            $table->timestamp('dibatalkan_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anak_merge_log');
    }
};
```

```php
<?php
// database/migrations/2026_09_17_000002_add_sumber_gabungan_to_anak_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Daftar sumber yang pernah dilebur ke baris ini (spec §3.2); `sumber` tetap satu nilai. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anak', function (Blueprint $table) {
            $table->json('sumber_gabungan')->nullable()->after('sumber');
        });
    }

    public function down(): void
    {
        Schema::table('anak', function (Blueprint $table) {
            $table->dropColumn('sumber_gabungan');
        });
    }
};
```

```php
<?php
// app/Models/AnakMergeLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu baris per penggabungan; `snapshot` memuat semua yang dibutuhkan untuk membatalkan. */
class AnakMergeLog extends Model
{
    protected $table = 'anak_merge_log';
    protected $guarded = [];
    protected $casts = ['snapshot' => 'array', 'dibatalkan_at' => 'datetime'];

    public function scopeAktif(Builder $q): Builder
    {
        return $q->whereNull('dibatalkan_at');
    }

    public function dipertahankan(): BelongsTo
    {
        return $this->belongsTo(Anak::class, 'id_dipertahankan');
    }

    public function tautan(): BelongsTo
    {
        return $this->belongsTo(AnakTautan::class, 'id_tautan');
    }

    public function pelaku(): BelongsTo
    {
        return $this->belongsTo(User::class, 'oleh');
    }

    public function pembatal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibatalkan_oleh');
    }
}
```

`app/Models/Anak.php`: tambahkan `protected $casts = ['sumber_gabungan' => 'array'];` (bila sudah ada `$casts`, tambahkan kuncinya).

- [ ] **Step 4: Jalankan tes, pastikan lulus** → `OK (2 tests)`.
- [ ] **Step 5: Commit** — `git add database/migrations/2026_09_17_000001_create_anak_merge_log_table.php database/migrations/2026_09_17_000002_add_sumber_gabungan_to_anak_table.php app/Models/AnakMergeLog.php app/Models/Anak.php tests/Feature/VerifikasiRt/MergeSkemaTest.php && git commit -m "feat(verifikasi-rt): skema anak_merge_log & anak.sumber_gabungan"`

---

### Task 2: `IdentitasMergeService::pratinjau()` — baris dipertahankan & nilai default

**Files:**
- Create: `app/Services/IdentitasMergeService.php`
- Test: `tests/Feature/VerifikasiRt/MergePratinjauTest.php`

**Interfaces:**
- `IdentitasMergeService::KOLOM` (18 kolom di atas), `KOLOM_IDENTITAS`, `KOLOM_DOMISILI`.
- `pratinjau(AnakTautan $t): array{tautan:AnakTautan, a:Anak, b:Anak, dipertahankan:'a'|'b', kunci_dipertahankan:bool, default:array<string,'a'|'b'>, boleh_pilih_baris:bool}` — `InvalidArgumentException` bila `keputusan!=='sama'` atau `status!=='disetujui'`, atau keduanya OT ("Kedua baris berasal dari Operasi Timbang — tidak digabung"), atau salah satu anak sudah tidak ada.
- Aturan `dipertahankan`: tepat satu OT → OT (`kunci_dipertahankan=true`); selain itu non-capil bila hanya satu yang capil, else `a` (`kunci_dipertahankan=false`).

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php
// tests/Feature/VerifikasiRt/MergePratinjauTest.php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Anak;
use App\Models\AnakTautan;
use App\Models\User;
use App\Services\IdentitasMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MergePratinjauTest extends TestCase
{
    use RefreshDatabase;

    private IdentitasMergeService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(IdentitasMergeService::class);
    }

    private function anak(string $nik, array $o = []): Anak
    {
        return Anak::create(array_merge(['nama' => 'Anak '.$nik, 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'capil'], $o));
    }

    private function tautan(Anak $x, Anak $y, string $status = 'disetujui', string $keputusan = 'sama'): AnakTautan
    {
        [$a, $b] = AnakTautan::urut($x->id, $y->id);
        return AnakTautan::create(['id_anak_a' => $a, 'id_anak_b' => $b, 'keputusan' => $keputusan, 'status' => $status,
            'diusulkan_oleh' => User::factory()->create()->id, 'diusulkan_at' => now()]);
    }

    public function test_baris_ot_selalu_dipertahankan_dan_terkunci(): void
    {
        $cap = $this->anak('3201000000017001', ['alamat_ktp' => 'KTP Capil']);
        $ot  = $this->anak('3201000000017002', ['sumber' => 'operasi_timbang', 'alamat' => 'Domisili OT']);
        $p = $this->svc->pratinjau($this->tautan($cap, $ot));

        $this->assertSame('b', $p['dipertahankan']); // ot punya id lebih besar → b
        $this->assertTrue($p['kunci_dipertahankan']);
        $this->assertSame('a', $p['default']['nik'], 'identitas dari capil');
        $this->assertSame('a', $p['default']['alamat_ktp']);
        $this->assertSame('b', $p['default']['alamat'], 'domisili dari non-capil');
        $this->assertSame('b', $p['default']['golda'], 'sisanya dari yang dipertahankan');
    }

    public function test_dua_ot_ditolak(): void
    {
        $x = $this->anak('3201000000017003', ['sumber' => 'operasi_timbang']);
        $y = $this->anak('3201000000017004', ['sumber' => 'operasi_timbang']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Operasi Timbang');
        $this->svc->pratinjau($this->tautan($x, $y));
    }

    public function test_tanpa_ot_dinkes_boleh_memilih_default_non_capil(): void
    {
        $man = $this->anak('3201000000017005', ['sumber' => 'manual']);
        $cap = $this->anak('3201000000017006');
        $p = $this->svc->pratinjau($this->tautan($man, $cap));

        $this->assertSame('a', $p['dipertahankan']);
        $this->assertFalse($p['kunci_dipertahankan']);
        $this->assertTrue($p['boleh_pilih_baris']);
    }

    public function test_hanya_tautan_sama_yang_disetujui(): void
    {
        $x = $this->anak('3201000000017007');
        $y = $this->anak('3201000000017008');

        $this->expectException(\InvalidArgumentException::class);
        $this->svc->pratinjau($this->tautan($x, $y, 'diusulkan'));
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal** → `Class "App\Services\IdentitasMergeService" not found`.

- [ ] **Step 3: Tulis service (bagian pratinjau)**

```php
<?php
// app/Services/IdentitasMergeService.php

namespace App\Services;

use App\Models\Anak;
use App\Models\AnakMergeLog;
use App\Models\AnakTautan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Penggabungan dua baris anak yang dinyatakan satu orang (spec verifikasi RT §6.3).
 * Baris OT tidak pernah dihapus; semua data anak berpindah SEBELUM baris lain dihapus
 * (FK cascade); snapshot di anak_merge_log memungkinkan pembatalan.
 */
class IdentitasMergeService
{
    public const KOLOM_IDENTITAS = ['nik', 'no_kk', 'nama', 'nama_ibu', 'nama_ayah', 'jk', 'tempat_lahir', 'tgl_lahir', 'alamat_ktp'];
    public const KOLOM_DOMISILI  = ['alamat', 'id_kec', 'id_kel', 'id_rt', 'id_posyandu', 'id_puskesmas'];
    public const KOLOM_LAIN      = ['golda', 'anak', 'catatan'];
    public const KOLOM           = [...self::KOLOM_IDENTITAS, ...self::KOLOM_DOMISILI, ...self::KOLOM_LAIN];

    public function __construct(private readonly PrioritasGiziService $prioritas)
    {
    }

    /**
     * @return array{tautan:AnakTautan, a:Anak, b:Anak, dipertahankan:string, kunci_dipertahankan:bool, default:array<string,string>, boleh_pilih_baris:bool}
     */
    public function pratinjau(AnakTautan $t): array
    {
        if ($t->keputusan !== 'sama' || $t->status !== 'disetujui') {
            throw new InvalidArgumentException('Hanya tautan "sama" yang sudah disetujui yang bisa digabung.');
        }
        $a = Anak::find($t->id_anak_a);
        $b = Anak::find($t->id_anak_b);
        if (!$a || !$b) {
            throw new InvalidArgumentException('Salah satu baris anak sudah tidak ada.');
        }

        $aOt = $a->sumber === 'operasi_timbang';
        $bOt = $b->sumber === 'operasi_timbang';
        if ($aOt && $bOt) {
            throw new InvalidArgumentException('Kedua baris berasal dari Operasi Timbang — tidak digabung agar populasi OT tidak berubah.');
        }

        if ($aOt || $bOt) {
            $dipertahankan = $aOt ? 'a' : 'b';
            $kunci = true;
        } else {
            $dipertahankan = ($a->sumber === 'capil' && $b->sumber !== 'capil') ? 'b' : 'a';
            $kunci = false;
        }

        $capil = $a->sumber === 'capil' ? 'a' : ($b->sumber === 'capil' ? 'b' : null);
        $nonCapil = $capil === 'a' ? 'b' : ($capil === 'b' ? 'a' : null);

        $default = [];
        foreach (self::KOLOM as $k) {
            if (in_array($k, self::KOLOM_IDENTITAS, true) && $capil) {
                $default[$k] = $capil;
            } elseif (in_array($k, self::KOLOM_DOMISILI, true) && $nonCapil) {
                $default[$k] = $nonCapil;
            } else {
                $default[$k] = $dipertahankan;
            }
        }

        return [
            'tautan'              => $t,
            'a'                   => $a,
            'b'                   => $b,
            'dipertahankan'       => $dipertahankan,
            'kunci_dipertahankan' => $kunci,
            'default'             => $default,
            'boleh_pilih_baris'   => !$kunci,
        ];
    }
}
```

- [ ] **Step 4: Jalankan tes, pastikan lulus** → `OK (4 tests)`.
- [ ] **Step 5: Commit** — `git add app/Services/IdentitasMergeService.php tests/Feature/VerifikasiRt/MergePratinjauTest.php && git commit -m "feat(verifikasi-rt): pratinjau penggabungan — baris dipertahankan & nilai default"`

---

### Task 3: `gabung()` — eksekusi dalam satu transaksi

**Files:**
- Modify: `app/Services/IdentitasMergeService.php`
- Test: `tests/Feature/VerifikasiRt/MergeGabungTest.php`
- Modify: `tests/Feature/TimbangDashboardTerkunciTest.php` (+1 tes penjaga)

**Interfaces:**
- `gabung(AnakTautan $t, User $oleh, array $pilihan, ?string $dipertahankan = null): AnakMergeLog` — `$pilihan` = `['nik'=>'a'|'b', …]` (kolom di luar `KOLOM` diabaikan; kolom yang tak disebut memakai default); `$dipertahankan` `'a'|'b'` hanya dihormati bila `boleh_pilih_baris`.
- Urutan dalam `DB::transaction`:
  1. hitung `keep`/`drop`; snapshot: `anak_dihapus` (seluruh atribut), `nilai_lama_dipertahankan` (nilai kolom KOLOM + `sumber_gabungan`, `pj_*` sebelum ditimpa), `pilihan`, `dipindah` = `{data_anak:[id…], imunisasi:[id…], intervensi_gizi:[id…], verifikasi_anak:[id…]}`, `prioritas_dihapus` (baris prioritas milik drop bila ada), `tautan_lain` = `[{id, status_lama, catatan_reviu_lama}]`;
  2. tulis `AnakMergeLog`;
  3. `UPDATE … SET id_anak = keep WHERE id_anak = drop` untuk `data_anak`, `imunisasi`, `intervensi_gizi`, `verifikasi_anak`; `DELETE prioritas_gizi WHERE id_anak = drop`;
  4. tautan lain yang memuat `drop` dan berstatus `diusulkan`/`disetujui` → `status='ditolak'`, `catatan_reviu='Baris digabung ke anak #keep'`;
  5. `$drop->delete()` (melepas NIK unik; `anak_kandidat` cascade);
  6. timpa kolom terpilih di `keep` (nilai dari `drop` bila pilihan menunjuk drop), `sumber` **tetap** milik keep, `sumber_gabungan` = unik gabungan `[keep.sumber, drop.sumber] + keep.sumber_gabungan + drop.sumber_gabungan`, `pj_*` dari drop bila keep kosong;
  7. `t->update(['status' => 'digabung'])`;
  8. `$this->prioritas->refreshAnak($keep->id)`;
  9. `Log::info`.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php
// tests/Feature/VerifikasiRt/MergeGabungTest.php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Anak;
use App\Models\AnakKandidat;
use App\Models\AnakMergeLog;
use App\Models\AnakTautan;
use App\Models\DataAnak;
use App\Models\Imunisasi;
use App\Models\IntervensiGizi;
use App\Models\Rt;
use App\Models\User;
use App\Models\VerifikasiAnak;
use App\Services\IdentitasMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MergeGabungTest extends TestCase
{
    use RefreshDatabase;

    private IdentitasMergeService $svc;
    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc   = app(IdentitasMergeService::class);
        $this->super = User::factory()->create(['type' => 0]);
    }

    private function anak(string $nik, array $o = []): Anak
    {
        return Anak::create(array_merge(['nama' => 'Anak '.$nik, 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'capil', 'nama_ibu' => 'Ibu', 'nama_ayah' => 'Ayah'], $o));
    }

    private function ukur(Anak $a, float $tb = 80): DataAnak
    {
        return DataAnak::create(['id_anak' => $a->id, 'tgl_kunjungan' => '2026-01-10', 'bln' => 24, 'posisi' => 'berdiri',
            'tb' => $tb, 'bb' => 10, 'lla' => 0, 'lk' => 0, 'id_user' => 1, 'zscore_bb_u' => 0, 'zscore_pb_u' => -2.5, 'zscore_bb_pb' => 0,
            'sumber' => $a->sumber === 'operasi_timbang' ? 'operasi_timbang' : 'manual']);
    }

    private function tautanSetuju(Anak $x, Anak $y): AnakTautan
    {
        [$a, $b] = AnakTautan::urut($x->id, $y->id);
        return AnakTautan::create(['id_anak_a' => $a, 'id_anak_b' => $b, 'keputusan' => 'sama', 'status' => 'disetujui',
            'diusulkan_oleh' => $this->super->id, 'diusulkan_at' => now()]);
    }

    public function test_gabung_memindahkan_semua_data_anak_sebelum_menghapus(): void
    {
        $ot  = $this->anak('3201000000018001', ['sumber' => 'operasi_timbang', 'alamat' => 'Jl. OT', 'pj_nama' => null]);
        $cap = $this->anak('3201000000018002', ['nama' => 'Nama Capil', 'no_kk' => '6474000000000018', 'alamat_ktp' => 'Jl. KTP', 'pj_nama' => 'Kader X']);
        $u1 = $this->ukur($ot); $u2 = $this->ukur($cap, 81);
        $im = Imunisasi::create(['kode' => 'IMZ-18002', 'id_anak' => $cap->id, 'id_jenis_vaksin' => \App\Models\JenisVaksin::factory()->create()->id, 'tanggal' => '2025-01-01']);
        $iv = IntervensiGizi::create(['id_anak' => $cap->id, 'jenis' => IntervensiGizi::JENIS[0], 'status' => 'Direncanakan']);
        $rt = Rt::factory()->create();
        $va = VerifikasiAnak::create(['id_anak' => $cap->id, 'id_rt' => $rt->id, 'status' => 'berdomisili', 'diusulkan_oleh' => $this->super->id, 'diusulkan_at' => now()]);
        DB::table('prioritas_gizi')->insert(['id_anak' => $cap->id, 'stunting' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $t = $this->tautanSetuju($ot, $cap);

        $log = $this->svc->gabung($t, $this->super, ['nama' => 'b', 'no_kk' => 'b', 'alamat_ktp' => 'b']);

        $this->assertNull(Anak::find($cap->id));
        $keep = $ot->fresh();
        $this->assertSame('Nama Capil', $keep->nama);
        $this->assertSame('6474000000000018', $keep->no_kk);
        $this->assertSame('3201000000018001', $keep->nik, 'nik tidak dipilih → tetap milik yang dipertahankan');
        $this->assertSame('operasi_timbang', $keep->sumber, 'sumber OT tidak pernah berubah');
        $this->assertSame(['operasi_timbang', 'capil'], $keep->sumber_gabungan);
        $this->assertSame('Kader X', $keep->pj_nama, 'PJ diisi dari baris yang dihapus karena kosong');
        $this->assertSame(2, DataAnak::where('id_anak', $keep->id)->count());
        $this->assertSame($keep->id, (int) $im->fresh()->id_anak);
        $this->assertSame($keep->id, (int) $iv->fresh()->id_anak);
        $this->assertSame($keep->id, (int) $va->fresh()->id_anak);
        $this->assertSame(1, DB::table('prioritas_gizi')->where('id_anak', $keep->id)->count());
        $this->assertSame(0, DB::table('prioritas_gizi')->where('id_anak', $cap->id)->count());
        $this->assertSame('digabung', $t->fresh()->status);
        $this->assertSame($keep->id, (int) $log->id_dipertahankan);
        $this->assertSame([$u2->id], $log->snapshot['dipindah']['data_anak']);
        $this->assertSame('3201000000018002', $log->snapshot['anak_dihapus']['nik']);
    }

    public function test_dua_ot_ditolak_dan_tak_ada_yang_berubah(): void
    {
        $x = $this->anak('3201000000018003', ['sumber' => 'operasi_timbang']);
        $y = $this->anak('3201000000018004', ['sumber' => 'operasi_timbang']);
        $t = $this->tautanSetuju($x, $y);

        try {
            $this->svc->gabung($t, $this->super, []);
            $this->fail('harus ditolak');
        } catch (\InvalidArgumentException) {
        }
        $this->assertSame(2, Anak::whereIn('id', [$x->id, $y->id])->count());
        $this->assertSame(0, AnakMergeLog::count());
    }

    public function test_tautan_lain_yang_memuat_baris_terhapus_ditolak_otomatis(): void
    {
        $ot  = $this->anak('3201000000018005', ['sumber' => 'operasi_timbang']);
        $cap = $this->anak('3201000000018006');
        $lain = $this->anak('3201000000018007');
        $t = $this->tautanSetuju($ot, $cap);
        [$p, $q] = AnakTautan::urut($cap->id, $lain->id);
        $tLain = AnakTautan::create(['id_anak_a' => $p, 'id_anak_b' => $q, 'keputusan' => 'sama', 'status' => 'diusulkan',
            'diusulkan_oleh' => $this->super->id, 'diusulkan_at' => now()]);
        AnakKandidat::create(['id_anak_a' => $p, 'id_anak_b' => $q, 'skor' => 1, 'via' => 'ortu', 'child_sim' => 1, 'parent_sim' => 1, 'dipindai_at' => now()]);

        $this->svc->gabung($t, $this->super, []);

        $this->assertSame('ditolak', $tLain->fresh()->status);
        $this->assertStringContainsString('digabung', $tLain->fresh()->catatan_reviu);
        $this->assertSame(0, AnakKandidat::count(), 'kandidat ikut cascade');
    }

    public function test_pilihan_baris_dihormati_hanya_bila_tanpa_ot(): void
    {
        $man = $this->anak('3201000000018008', ['sumber' => 'manual']);
        $cap = $this->anak('3201000000018009');
        $t = $this->tautanSetuju($man, $cap);

        $this->svc->gabung($t, $this->super, [], 'b'); // pertahankan capil

        $this->assertNull(Anak::find($man->id));
        $this->assertNotNull(Anak::find($cap->id));
        $this->assertSame(['capil', 'manual'], Anak::find($cap->id)->sumber_gabungan);
    }
}
```

Penjaga OT — tambahkan ke `TimbangDashboardTerkunciTest` (impor `App\Models\AnakTautan`, `App\Services\IdentitasMergeService`):

```php
    /** Spec §6.3: merge OT×Capil mempertahankan baris OT → angka dasbor OT tidak berubah. */
    public function test_merge_ot_dengan_capil_tidak_mengubah_angka_dasbor_ot(): void
    {
        $super = User::factory()->create(['type' => 0]);
        $ot = $this->anakOt('3201000000005201', -2.5);
        $cap = Anak::create(['nama' => 'Kembaran Capil', 'nik' => '3201000000005202', 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => $ot->tgl_lahir, 'status' => 1, 'sumber' => 'capil']);
        [$a, $b] = AnakTautan::urut($ot->id, $cap->id);
        $t = AnakTautan::create(['id_anak_a' => $a, 'id_anak_b' => $b, 'keputusan' => 'sama', 'status' => 'disetujui',
            'diusulkan_oleh' => $super->id, 'diusulkan_at' => now()]);

        $sebelum = [
            $this->actingAs($super)->getJson(route('admin.timbang.ringkasan'))->json(),
            $this->actingAs($super)->getJson(route('admin.timbang.gizi'))->json(),
        ];

        app(IdentitasMergeService::class)->gabung($t, $super, ['nama' => 'b']);

        $sesudah = [
            $this->actingAs($super)->getJson(route('admin.timbang.ringkasan'))->json(),
            $this->actingAs($super)->getJson(route('admin.timbang.gizi'))->json(),
        ];
        $this->assertSame($sebelum, $sesudah);
        $this->assertSame('operasi_timbang', $ot->fresh()->sumber);
    }
```

Periksa dulu apakah `JenisVaksin` punya factory (`database/factories/JenisVaksinFactory.php`) dan kolom wajib `imunisasi` (`kode`, `id_anak`, `id_jenis_vaksin`, `tanggal`, …) — sesuaikan pemanggilan `Imunisasi::create` dengan kolom NOT NULL di migrasinya; bila lebih sederhana, buat lewat `DB::table('imunisasi')->insertGetId([...])` dan cek `DB::table('imunisasi')->where('id', …)->value('id_anak')`.

- [ ] **Step 2: Jalankan, pastikan gagal** → `Call to undefined method …::gabung()`.

- [ ] **Step 3: Tambahkan `gabung()`**

```php
    /** Eksekusi penggabungan. $pilihan = ['nik' => 'a'|'b', …]; $dipertahankan 'a'|'b' hanya bila boleh_pilih_baris. */
    public function gabung(AnakTautan $t, User $oleh, array $pilihan, ?string $dipertahankan = null): AnakMergeLog
    {
        $p = $this->pratinjau($t);
        $sisi = $p['dipertahankan'];
        if ($p['boleh_pilih_baris'] && in_array($dipertahankan, ['a', 'b'], true)) {
            $sisi = $dipertahankan;
        }
        /** @var Anak $keep */
        $keep = $p[$sisi];
        /** @var Anak $drop */
        $drop = $p[$sisi === 'a' ? 'b' : 'a'];
        $sisiDrop = $sisi === 'a' ? 'b' : 'a';

        $final = $p['default'];
        foreach ($pilihan as $k => $v) {
            if (in_array($k, self::KOLOM, true) && in_array($v, ['a', 'b'], true)) {
                $final[$k] = $v;
            }
        }

        return DB::transaction(function () use ($t, $oleh, $keep, $drop, $sisiDrop, $final) {
            $dipindah = [
                'data_anak'       => DB::table('data_anak')->where('id_anak', $drop->id)->pluck('id')->all(),
                'imunisasi'       => DB::table('imunisasi')->where('id_anak', $drop->id)->pluck('id')->all(),
                'intervensi_gizi' => DB::table('intervensi_gizi')->where('id_anak', $drop->id)->pluck('id')->all(),
                'verifikasi_anak' => DB::table('verifikasi_anak')->where('id_anak', $drop->id)->pluck('id')->all(),
            ];
            $prioritasDrop = (array) DB::table('prioritas_gizi')->where('id_anak', $drop->id)->first();
            $tautanLain = AnakTautan::where('id', '!=', $t->id)
                ->where(fn ($q) => $q->where('id_anak_a', $drop->id)->orWhere('id_anak_b', $drop->id))
                ->whereIn('status', ['diusulkan', 'disetujui'])
                ->get(['id', 'status', 'catatan_reviu'])
                ->map(fn ($x) => ['id' => $x->id, 'status_lama' => $x->status, 'catatan_reviu_lama' => $x->catatan_reviu])
                ->all();

            $nilaiLama = [];
            foreach ([...self::KOLOM, 'sumber_gabungan', 'pj_nama', 'pj_updated_by', 'pj_updated_at'] as $k) {
                $nilaiLama[$k] = $keep->getAttribute($k);
            }

            $log = AnakMergeLog::create([
                'id_dipertahankan' => $keep->id,
                'id_dihapus'       => $drop->id,
                'id_tautan'        => $t->id,
                'oleh'             => $oleh->id,
                'snapshot'         => [
                    'anak_dihapus'             => $drop->getAttributes(),
                    'nilai_lama_dipertahankan' => $nilaiLama,
                    'pilihan'                  => $final,
                    'dipindah'                 => $dipindah,
                    'prioritas_dihapus'        => $prioritasDrop ?: null,
                    'tautan_lain'              => $tautanLain,
                    'id_anak_dipertahankan_saat_merge' => [
                        'data_anak' => DB::table('data_anak')->where('id_anak', $keep->id)->pluck('id')->all(),
                        'imunisasi' => DB::table('imunisasi')->where('id_anak', $keep->id)->pluck('id')->all(),
                    ],
                ],
            ]);

            // 1) Pindahkan SEMUA data anak sebelum menghapus (FK cascade akan menghapusnya kalau tidak).
            foreach (['data_anak', 'imunisasi', 'intervensi_gizi', 'verifikasi_anak'] as $tabel) {
                DB::table($tabel)->where('id_anak', $drop->id)->update(['id_anak' => $keep->id]);
            }
            DB::table('prioritas_gizi')->where('id_anak', $drop->id)->delete();

            // 2) Tautan lain yang memuat baris yang dihapus tak lagi bermakna.
            foreach ($tautanLain as $x) {
                AnakTautan::where('id', $x['id'])->update([
                    'status'        => 'ditolak',
                    'catatan_reviu' => "Baris digabung ke anak #{$keep->id}",
                ]);
            }

            // 3) Hapus baris yang dilebur — melepas NIK dari unique key; anak_kandidat ikut cascade.
            $drop->delete();

            // 4) Timpa kolom terpilih; sumber milik yang dipertahankan TIDAK berubah (OT tetap OT).
            $update = [];
            foreach ($final as $k => $sisiPilih) {
                if ($sisiPilih === $sisiDrop) {
                    $update[$k] = $drop->getAttribute($k);
                }
            }
            $update['sumber_gabungan'] = array_values(array_unique(array_filter(array_merge(
                [$keep->sumber, $drop->sumber],
                (array) ($keep->sumber_gabungan ?? []),
                (array) ($drop->sumber_gabungan ?? []),
            ))));
            if (!$keep->pj_nama && $drop->pj_nama) {
                $update['pj_nama']       = $drop->pj_nama;
                $update['pj_updated_by'] = $drop->pj_updated_by;
                $update['pj_updated_at'] = $drop->pj_updated_at;
            }
            $keep->update($update);

            $t->update(['status' => 'digabung']);
            $this->prioritas->refreshAnak($keep->id);

            Log::info("Merge anak #{$drop->id} → #{$keep->id} (log #{$log->id}) oleh user #{$oleh->id}");

            return $log;
        });
    }
```

- [ ] **Step 4: Jalankan tes** — `…/phpunit tests/Feature/VerifikasiRt/MergeGabungTest.php tests/Feature/TimbangDashboardTerkunciTest.php` → semua lulus.
- [ ] **Step 5: Commit** — `git add app/Services/IdentitasMergeService.php tests/Feature/VerifikasiRt/MergeGabungTest.php tests/Feature/TimbangDashboardTerkunciTest.php && git commit -m "feat(verifikasi-rt): gabung dua baris anak — pindah dulu, hapus kemudian, tercatat"`

---

### Task 4: `batalkan()` — pulihkan dari snapshot

**Files:**
- Modify: `app/Services/IdentitasMergeService.php`
- Test: `tests/Feature/VerifikasiRt/MergeBatalkanTest.php`

**Interfaces:**
- `batalkan(AnakMergeLog $log, User $oleh): Anak` — mengembalikan baris yang dipulihkan. `InvalidArgumentException` bila sudah dibatalkan, bila baris yang dipertahankan sudah tidak ada, bila NIK baris lama kini dipakai baris lain, atau bila ada baris `data_anak`/`imunisasi` milik keep yang **tidak** ada di `dipindah` maupun `id_anak_dipertahankan_saat_merge` (data baru yang tak bisa dipetakan).
- Urutan: insert ulang `anak_dihapus` (id lama, tanpa `sumber_gabungan` lama yang mungkin null) → pindahkan kembali id di `dipindah` → pulihkan `nilai_lama_dipertahankan` di keep → pulihkan `prioritas_dihapus` bila ada → tautan lain kembali ke `status_lama`/`catatan_reviu_lama` → tautan utama `status='disetujui'` → `log.dibatalkan_*` → `refreshAnak` keduanya.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php
// tests/Feature/VerifikasiRt/MergeBatalkanTest.php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Anak;
use App\Models\AnakTautan;
use App\Models\DataAnak;
use App\Models\User;
use App\Services\IdentitasMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MergeBatalkanTest extends TestCase
{
    use RefreshDatabase;

    private IdentitasMergeService $svc;
    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc   = app(IdentitasMergeService::class);
        $this->super = User::factory()->create(['type' => 0]);
    }

    private function anak(string $nik, array $o = []): Anak
    {
        return Anak::create(array_merge(['nama' => 'Anak '.$nik, 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'capil', 'nama_ibu' => 'Ibu', 'nama_ayah' => 'Ayah'], $o));
    }

    private function ukur(Anak $a): DataAnak
    {
        return DataAnak::create(['id_anak' => $a->id, 'tgl_kunjungan' => '2026-01-10', 'bln' => 24, 'posisi' => 'berdiri',
            'tb' => 80, 'bb' => 10, 'lla' => 0, 'lk' => 0, 'id_user' => 1, 'zscore_bb_u' => 0, 'zscore_pb_u' => 0, 'zscore_bb_pb' => 0, 'sumber' => 'manual']);
    }

    private function gabungOtCapil(string $nikOt, string $nikCap): array
    {
        $ot  = $this->anak($nikOt, ['sumber' => 'operasi_timbang', 'nama' => 'Nama OT']);
        $cap = $this->anak($nikCap, ['nama' => 'Nama Capil']);
        $u = $this->ukur($cap);
        [$a, $b] = AnakTautan::urut($ot->id, $cap->id);
        $t = AnakTautan::create(['id_anak_a' => $a, 'id_anak_b' => $b, 'keputusan' => 'sama', 'status' => 'disetujui',
            'diusulkan_oleh' => $this->super->id, 'diusulkan_at' => now()]);
        $log = $this->svc->gabung($t, $this->super, ['nama' => $ot->id < $cap->id ? 'b' : 'a']);
        return [$ot, $cap, $u, $t, $log];
    }

    public function test_batalkan_memulihkan_persis(): void
    {
        [$ot, $cap, $u, $t, $log] = $this->gabungOtCapil('3201000000019001', '3201000000019002');
        $this->assertSame('Nama Capil', $ot->fresh()->nama);

        $pulih = $this->svc->batalkan($log, $this->super);

        $this->assertSame($cap->id, $pulih->id);
        $this->assertSame('3201000000019002', $pulih->nik);
        $this->assertSame('Nama OT', $ot->fresh()->nama, 'nilai lama dipulihkan');
        $this->assertNull($ot->fresh()->sumber_gabungan);
        $this->assertSame($cap->id, (int) $u->fresh()->id_anak, 'pengukuran kembali ke pemilik asal');
        $this->assertSame('disetujui', $t->fresh()->status);
        $this->assertNotNull($log->fresh()->dibatalkan_at);
    }

    public function test_batalkan_dua_kali_ditolak(): void
    {
        [, , , , $log] = $this->gabungOtCapil('3201000000019003', '3201000000019004');
        $this->svc->batalkan($log, $this->super);

        $this->expectException(\InvalidArgumentException::class);
        $this->svc->batalkan($log->fresh(), $this->super);
    }

    public function test_batalkan_ditolak_bila_ada_pengukuran_baru_yang_tak_bisa_dipetakan(): void
    {
        [$ot, , , , $log] = $this->gabungOtCapil('3201000000019005', '3201000000019006');
        $this->ukur($ot->fresh()); // data baru setelah merge

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('baru');
        $this->svc->batalkan($log, $this->super);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal** → `Call to undefined method …::batalkan()`.

- [ ] **Step 3: Tambahkan `batalkan()`**

```php
    /** Pulihkan penggabungan dari snapshot. Ditolak bila ada data baru yang tak bisa dipetakan. */
    public function batalkan(AnakMergeLog $log, User $oleh): Anak
    {
        if ($log->dibatalkan_at) {
            throw new InvalidArgumentException('Penggabungan ini sudah dibatalkan.');
        }
        $keep = Anak::find($log->id_dipertahankan);
        if (!$keep) {
            throw new InvalidArgumentException('Baris yang dipertahankan sudah tidak ada.');
        }
        $s = $log->snapshot;
        $anakLama = $s['anak_dihapus'];

        if (Anak::where('nik', $anakLama['nik'])->where('id', '!=', $keep->id)->exists()) {
            throw new InvalidArgumentException("NIK {$anakLama['nik']} kini dipakai baris lain — tidak bisa dipulihkan otomatis.");
        }

        foreach (['data_anak', 'imunisasi'] as $tabel) {
            $dikenal = array_merge($s['dipindah'][$tabel] ?? [], $s['id_anak_dipertahankan_saat_merge'][$tabel] ?? []);
            $baru = DB::table($tabel)->where('id_anak', $keep->id)->whereNotIn('id', $dikenal)->count();
            if ($baru > 0) {
                throw new InvalidArgumentException("Ada {$baru} baris {$tabel} baru sejak penggabungan yang tak bisa dipetakan ke salah satu anak — tangani manual.");
            }
        }

        return DB::transaction(function () use ($log, $oleh, $keep, $s, $anakLama) {
            // 1) Hidupkan kembali baris lama dengan id aslinya.
            $kolomValid = array_flip(\Schema::getColumnListing('anak'));
            DB::table('anak')->insert(array_intersect_key($anakLama, $kolomValid));

            // 2) Kembalikan data anak yang dipindah.
            foreach ($s['dipindah'] as $tabel => $ids) {
                if (!empty($ids)) {
                    DB::table($tabel)->whereIn('id', $ids)->update(['id_anak' => $anakLama['id']]);
                }
            }
            if (!empty($s['prioritas_dihapus'])) {
                $row = $s['prioritas_dihapus'];
                unset($row['id']);
                DB::table('prioritas_gizi')->where('id_anak', $anakLama['id'])->delete();
                DB::table('prioritas_gizi')->insert($row);
            }

            // 3) Pulihkan nilai lama baris yang dipertahankan.
            $keep->update($s['nilai_lama_dipertahankan']);

            // 4) Tautan lain & tautan utama.
            foreach ($s['tautan_lain'] ?? [] as $x) {
                AnakTautan::where('id', $x['id'])->update(['status' => $x['status_lama'], 'catatan_reviu' => $x['catatan_reviu_lama']]);
            }
            if ($log->id_tautan) {
                AnakTautan::where('id', $log->id_tautan)->update(['status' => 'disetujui']);
            }

            $log->update(['dibatalkan_oleh' => $oleh->id, 'dibatalkan_at' => now()]);

            $this->prioritas->refreshAnak($keep->id);
            $this->prioritas->refreshAnak((int) $anakLama['id']);

            Log::info("Batal merge log #{$log->id}: anak #{$anakLama['id']} dipulihkan oleh user #{$oleh->id}");

            return Anak::findOrFail($anakLama['id']);
        });
    }
```

Tambahkan `use Illuminate\Support\Facades\Schema;` dan ganti `\Schema::` dengan `Schema::`. Catatan: `getAttributes()` menyimpan `sumber_gabungan` sebagai string JSON (bukan array) — `insert` langsung aman.

- [ ] **Step 4: Jalankan tes** — `…/phpunit tests/Feature/VerifikasiRt/MergeBatalkanTest.php tests/Feature/VerifikasiRt/MergeGabungTest.php` → semua lulus.
- [ ] **Step 5: Commit** — `git add app/Services/IdentitasMergeService.php tests/Feature/VerifikasiRt/MergeBatalkanTest.php && git commit -m "feat(verifikasi-rt): batalkan penggabungan dari snapshot"`

---

### Task 5: Halaman superadmin — antrean gabung, pemilih kolom, log & batalkan

**Files:**
- Create: `app/Http/Controllers/MergeIdentitasController.php`
- Create: `resources/views/admin/verifikasi-rt/gabung/index.blade.php`, `resources/views/admin/verifikasi-rt/gabung/show.blade.php`
- Modify: `resources/views/admin/verifikasi-rt/index.blade.php` (tautan "menunggu penggabungan" → antrean gabung)
- Modify: `routes/web.php`
- Test: `tests/Feature/VerifikasiRt/MergeControllerTest.php`

**Interfaces (rute, semua superadmin — `abort_if(!isSuperAdmin(), 403)` di konstruktor lewat middleware closure atau tiap aksi):**
- `admin.gabung.index` GET `/admin/verifikasi-rt/gabung` — daftar `AnakTautan` `keputusan=sama, status=disetujui` (+ `anakA`, `anakB`, `pengusul`) dan 50 log terakhir (`AnakMergeLog` dengan `dipertahankan`, `pelaku`, `pembatal`).
- `admin.gabung.show` GET `/admin/verifikasi-rt/gabung/{tautan}` — pemilih kolom (`pratinjau`); bila `InvalidArgumentException` → redirect index dengan `error`.
- `admin.gabung.store` POST `/admin/verifikasi-rt/gabung/{tautan}` body `pilihan[nik]=a|b …`, `dipertahankan=a|b` (opsional) → `gabung()` → redirect index `success` "Digabung ke anak #id (log #n)".
- `admin.gabung.batalkan` POST `/admin/verifikasi-rt/gabung/log/{log}/batalkan` → `batalkan()` → redirect index `success`/`error`.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php
// tests/Feature/VerifikasiRt/MergeControllerTest.php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Anak;
use App\Models\AnakMergeLog;
use App\Models\AnakTautan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MergeControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->super = User::factory()->create(['type' => 0]);
    }

    private function anak(string $nik, array $o = []): Anak
    {
        return Anak::create(array_merge(['nama' => 'Anak '.$nik, 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'capil', 'nama_ibu' => 'Ibu', 'nama_ayah' => 'Ayah'], $o));
    }

    private function tautanSetuju(Anak $x, Anak $y): AnakTautan
    {
        [$a, $b] = AnakTautan::urut($x->id, $y->id);
        return AnakTautan::create(['id_anak_a' => $a, 'id_anak_b' => $b, 'keputusan' => 'sama', 'status' => 'disetujui',
            'diusulkan_oleh' => $this->super->id, 'diusulkan_at' => now()]);
    }

    public function test_hanya_superadmin(): void
    {
        $faskes = User::factory()->create(['type' => 1, 'id_kel' => 1]);
        $ot = $this->anak('3201000000020001', ['sumber' => 'operasi_timbang']);
        $t = $this->tautanSetuju($ot, $this->anak('3201000000020002'));

        $this->actingAs($faskes)->get(route('admin.gabung.index'))->assertForbidden();
        $this->actingAs($faskes)->get(route('admin.gabung.show', $t))->assertForbidden();
        $this->actingAs($faskes)->post(route('admin.gabung.store', $t))->assertForbidden();
    }

    public function test_index_menampilkan_antrean_dan_show_pemilih_kolom(): void
    {
        $ot  = $this->anak('3201000000020003', ['sumber' => 'operasi_timbang', 'nama' => 'Nama OT']);
        $cap = $this->anak('3201000000020004', ['nama' => 'Nama Capil']);
        $t = $this->tautanSetuju($ot, $cap);

        $this->actingAs($this->super)->get(route('admin.gabung.index'))
            ->assertOk()->assertSee('Nama OT')->assertSee(route('admin.gabung.show', $t));

        $this->actingAs($this->super)->get(route('admin.gabung.show', $t))
            ->assertOk()
            ->assertSee('Nama Capil')
            ->assertSee('name="pilihan[nama]"', false)
            ->assertSee('name="pilihan[nik]"', false)
            ->assertSee('Operasi Timbang');
    }

    public function test_store_menggabungkan_sesuai_pilihan(): void
    {
        $ot  = $this->anak('3201000000020005', ['sumber' => 'operasi_timbang', 'nama' => 'Nama OT']);
        $cap = $this->anak('3201000000020006', ['nama' => 'Nama Capil']);
        $t = $this->tautanSetuju($ot, $cap);
        $sisiCap = $ot->id < $cap->id ? 'b' : 'a';

        $this->actingAs($this->super)->from(route('admin.gabung.show', $t))
            ->post(route('admin.gabung.store', $t), ['pilihan' => ['nama' => $sisiCap]])
            ->assertRedirect(route('admin.gabung.index'))
            ->assertSessionHas('success');

        $this->assertSame('Nama Capil', $ot->fresh()->nama);
        $this->assertNull(Anak::find($cap->id));
        $this->assertSame(1, AnakMergeLog::count());
    }

    public function test_dua_ot_diarahkan_kembali_dengan_error(): void
    {
        $x = $this->anak('3201000000020007', ['sumber' => 'operasi_timbang']);
        $y = $this->anak('3201000000020008', ['sumber' => 'operasi_timbang']);
        $t = $this->tautanSetuju($x, $y);

        $this->actingAs($this->super)->get(route('admin.gabung.show', $t))
            ->assertRedirect(route('admin.gabung.index'))->assertSessionHas('error');
    }

    public function test_batalkan_dari_log(): void
    {
        $ot  = $this->anak('3201000000020009', ['sumber' => 'operasi_timbang']);
        $cap = $this->anak('3201000000020010');
        $t = $this->tautanSetuju($ot, $cap);
        $this->actingAs($this->super)->post(route('admin.gabung.store', $t));
        $log = AnakMergeLog::sole();

        $this->actingAs($this->super)->get(route('admin.gabung.index'))->assertSee(route('admin.gabung.batalkan', $log));
        $this->actingAs($this->super)->post(route('admin.gabung.batalkan', $log))
            ->assertRedirect(route('admin.gabung.index'))->assertSessionHas('success');

        $this->assertNotNull(Anak::find($cap->id));
        $this->assertNotNull($log->fresh()->dibatalkan_at);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal** → `Route [admin.gabung.index] not defined.`

- [ ] **Step 3: Controller & rute**

```php
<?php
// app/Http/Controllers/MergeIdentitasController.php

namespace App\Http\Controllers;

use App\Models\AnakMergeLog;
use App\Models\AnakTautan;
use App\Services\IdentitasMergeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/** Penggabungan baris anak oleh Dinkes (spec §6.3) — hanya superadmin. */
class MergeIdentitasController extends Controller
{
    public function __construct(private readonly IdentitasMergeService $svc)
    {
        $this->middleware(function ($request, $next) {
            abort_if(!$request->user()?->isSuperAdmin(), 403);
            return $next($request);
        });
    }

    public function index(): View
    {
        return view('admin.verifikasi-rt.gabung.index', [
            'antrean' => AnakTautan::with(['anakA', 'anakB', 'pengusul.rt'])
                ->where('keputusan', 'sama')->where('status', 'disetujui')->orderBy('ditinjau_at')->get(),
            'log'     => AnakMergeLog::with(['dipertahankan', 'pelaku', 'pembatal'])->latest('id')->limit(50)->get(),
            'kolom'   => IdentitasMergeService::KOLOM,
        ]);
    }

    public function show(AnakTautan $tautan): View|RedirectResponse
    {
        try {
            $p = $this->svc->pratinjau($tautan);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('admin.gabung.index')->with('error', $e->getMessage());
        }

        return view('admin.verifikasi-rt.gabung.show', $p + ['kolom' => IdentitasMergeService::KOLOM]);
    }

    public function store(Request $request, AnakTautan $tautan): RedirectResponse
    {
        $data = $request->validate([
            'pilihan'       => 'nullable|array',
            'pilihan.*'     => 'in:a,b',
            'dipertahankan' => 'nullable|in:a,b',
        ]);

        try {
            $log = $this->svc->gabung($tautan, $request->user(), $data['pilihan'] ?? [], $data['dipertahankan'] ?? null);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('admin.gabung.index')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.gabung.index')
            ->with('success', "Digabung ke anak #{$log->id_dipertahankan} (log #{$log->id}). Bisa dibatalkan dari daftar log.");
    }

    public function batalkan(Request $request, AnakMergeLog $log): RedirectResponse
    {
        try {
            $anak = $this->svc->batalkan($log, $request->user());
        } catch (InvalidArgumentException $e) {
            return redirect()->route('admin.gabung.index')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.gabung.index')->with('success', "Penggabungan dibatalkan; anak #{$anak->id} dipulihkan.");
    }
}
```

Rute (grup admin, setelah `verifikasi-rt/pindai`):

```php
    // Penggabungan baris anak (spec §6.3) — superadmin saja (dicek di controller)
    Route::get('verifikasi-rt/gabung', [App\Http\Controllers\MergeIdentitasController::class, 'index'])->name('admin.gabung.index');
    Route::get('verifikasi-rt/gabung/{tautan}', [App\Http\Controllers\MergeIdentitasController::class, 'show'])->name('admin.gabung.show');
    Route::post('verifikasi-rt/gabung/{tautan}', [App\Http\Controllers\MergeIdentitasController::class, 'store'])->name('admin.gabung.store');
    Route::post('verifikasi-rt/gabung/log/{log}/batalkan', [App\Http\Controllers\MergeIdentitasController::class, 'batalkan'])->name('admin.gabung.batalkan');
```

Catatan: rute `verifikasi-rt/gabung` harus **sebelum** `verifikasi-rt/{verifikasi}/tinjau`? Tidak bentrok (metode/segmen berbeda), tetapi `verifikasi-rt/gabung/{tautan}` GET vs `verifikasi-rt/tautan/{tautan}/tinjau` POST juga aman.

- [ ] **Step 4: View index**

```blade
{{-- resources/views/admin/verifikasi-rt/gabung/index.blade.php --}}
@extends('admin::layouts.app')

@section('title') Penggabungan Data Anak — SIRINDU @endsection
@section('title-content') Penggabungan Data Anak @endsection
@section('item') Verifikasi RT @endsection
@section('item-active') Penggabungan @endsection

@section('content')
<div class="page-header"><div class="row"><div class="col-md-12"><div class="title">
    <h4>Menunggu penggabungan</h4>
    <p class="text-muted mb-0">Tautan "sama" yang sudah disetujui. Penggabungan memindahkan seluruh pengukuran/imunisasi ke satu baris, mencatat log, dan bisa dibatalkan. Baris Operasi Timbang selalu dipertahankan.</p>
</div></div></div></div>

@if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
@if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif

<div class="card-box mb-3">
    <a href="{{ route('admin.verifikasiRt.index', ['tab' => 'tautan']) }}" class="btn btn-link pl-0">&larr; Antrean reviu</a>
    <div class="table-responsive">
        <table class="table table-striped table-sm">
            <thead><tr><th>Baris A</th><th>Baris B</th><th>Pengusul</th><th>Disetujui</th><th></th></tr></thead>
            <tbody>
            @forelse($antrean as $t)
                <tr>
                    <td><b>{{ $t->anakA?->nama }}</b><br><small class="text-muted">NIK {{ $t->anakA?->nik }} · {{ $t->anakA?->sumber }}</small></td>
                    <td><b>{{ $t->anakB?->nama }}</b><br><small class="text-muted">NIK {{ $t->anakB?->nik }} · {{ $t->anakB?->sumber }}</small></td>
                    <td><small>{{ $t->pengusul?->name }} ({{ $t->pengusul?->rt?->name }})</small></td>
                    <td><small>{{ $t->ditinjau_at?->format('d/m/Y H:i') }}</small></td>
                    <td class="text-right"><a class="btn btn-sm btn-primary" href="{{ route('admin.gabung.show', $t) }}">Gabungkan…</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">Tidak ada tautan yang menunggu penggabungan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card-box">
    <h5>Riwayat penggabungan</h5>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr><th>#</th><th>Dipertahankan</th><th>Dihapus</th><th>Oleh</th><th>Waktu</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($log as $l)
                <tr class="{{ $l->dibatalkan_at ? 'text-muted' : '' }}">
                    <td>{{ $l->id }}</td>
                    <td>#{{ $l->id_dipertahankan }} {{ $l->dipertahankan?->nama }}</td>
                    <td>#{{ $l->id_dihapus }} {{ $l->snapshot['anak_dihapus']['nama'] ?? '' }}<br><small>NIK {{ $l->snapshot['anak_dihapus']['nik'] ?? '' }}</small></td>
                    <td>{{ $l->pelaku?->name }}</td>
                    <td><small>{{ $l->created_at?->format('d/m/Y H:i') }}</small></td>
                    <td>{{ $l->dibatalkan_at ? 'Dibatalkan '.$l->dibatalkan_at->format('d/m/Y H:i').' oleh '.$l->pembatal?->name : 'Aktif' }}</td>
                    <td class="text-right">
                        @if(!$l->dibatalkan_at)
                        <form method="POST" action="{{ route('admin.gabung.batalkan', $l) }}" onsubmit="return confirm('Batalkan penggabungan #{{ $l->id }}? Baris yang dihapus akan dipulihkan.');">@csrf
                            <button class="btn btn-sm btn-outline-danger">Batalkan</button>
                        </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-3">Belum ada penggabungan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
```

- [ ] **Step 5: View show (pemilih kolom)**

```blade
{{-- resources/views/admin/verifikasi-rt/gabung/show.blade.php --}}
@extends('admin::layouts.app')

@section('title') Gabungkan Data Anak — SIRINDU @endsection
@section('title-content') Gabungkan Data Anak @endsection
@section('item') Penggabungan @endsection
@section('item-active') Pilih kolom @endsection

@section('content')
@php
    $labelKolom = ['nik' => 'NIK', 'no_kk' => 'No KK', 'nama' => 'Nama', 'nama_ibu' => 'Nama ibu', 'nama_ayah' => 'Nama ayah', 'jk' => 'JK',
        'tempat_lahir' => 'Tempat lahir', 'tgl_lahir' => 'Tgl lahir', 'alamat_ktp' => 'Alamat KTP', 'alamat' => 'Alamat domisili',
        'id_kec' => 'Kecamatan (id)', 'id_kel' => 'Kelurahan (id)', 'id_rt' => 'RT (id)', 'id_posyandu' => 'Posyandu (id)',
        'id_puskesmas' => 'Puskesmas (id)', 'golda' => 'Gol. darah', 'anak' => 'Anak ke-', 'catatan' => 'Catatan'];
    $nilai = fn ($anak, $k) => $anak->$k === null || $anak->$k === '' ? '—' : $anak->$k;
@endphp
<div class="page-header"><div class="row"><div class="col-md-12"><div class="title">
    <h4>Pilih nilai per kolom</h4>
    <p class="text-muted mb-0">
        Baris yang <b>dipertahankan</b>: <b>{{ strtoupper($dipertahankan) }}</b>
        @if($kunci_dipertahankan) — terkunci karena berasal dari <b>Operasi Timbang</b> (baris OT tidak pernah dihapus). @endif
        Seluruh pengukuran, imunisasi, intervensi, dan verifikasi dari baris lain akan dipindahkan ke baris ini.
    </p>
</div></div></div></div>

<form method="POST" action="{{ route('admin.gabung.store', $tautan) }}" class="card-box">
    @csrf
    @if($boleh_pilih_baris)
    <div class="form-group">
        <label class="mr-3">Baris yang dipertahankan:</label>
        <label class="mr-3"><input type="radio" name="dipertahankan" value="a" {{ $dipertahankan === 'a' ? 'checked' : '' }}> A (#{{ $a->id }}, {{ $a->sumber }})</label>
        <label><input type="radio" name="dipertahankan" value="b" {{ $dipertahankan === 'b' ? 'checked' : '' }}> B (#{{ $b->id }}, {{ $b->sumber }})</label>
    </div>
    @endif
    <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr><th style="width:160px">Kolom</th><th>A — #{{ $a->id }} <span class="badge badge-light">{{ $a->sumber }}</span></th><th>B — #{{ $b->id }} <span class="badge badge-light">{{ $b->sumber }}</span></th></tr></thead>
            <tbody>
            @foreach($kolom as $k)
                @php $beda = trim((string) $a->$k) !== trim((string) $b->$k); @endphp
                <tr class="{{ $beda ? 'table-warning' : '' }}">
                    <th>{{ $labelKolom[$k] ?? $k }}</th>
                    <td><label class="mb-0 d-block"><input type="radio" name="pilihan[{{ $k }}]" value="a" {{ $default[$k] === 'a' ? 'checked' : '' }}> {{ $nilai($a, $k) }}</label></td>
                    <td><label class="mb-0 d-block"><input type="radio" name="pilihan[{{ $k }}]" value="b" {{ $default[$k] === 'b' ? 'checked' : '' }}> {{ $nilai($b, $k) }}</label></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <p class="text-muted"><small>Kolom berlatar kuning berisi nilai berbeda. Pilihan awal: identitas dari Capil, domisili dari data non-Capil. <code>sumber</code> baris yang dipertahankan tidak berubah; asal-usul kedua baris disimpan di <code>sumber_gabungan</code>.</small></p>
    <button class="btn btn-primary" onclick="return confirm('Gabungkan kedua baris? Tindakan ini tercatat dan bisa dibatalkan dari riwayat.');">Gabungkan sekarang</button>
    <a href="{{ route('admin.gabung.index') }}" class="btn btn-secondary ml-2">Batal</a>
</form>
@endsection
```

Tautan dari tab reviu — di `resources/views/admin/verifikasi-rt/index.blade.php` ganti
`<span class="text-muted mr-3"><b>{{ $menungguGabung }} tautan menunggu penggabungan</b></span>` menjadi
`<a class="mr-3" href="{{ route('admin.gabung.index') }}"><b>{{ $menungguGabung }} tautan menunggu penggabungan</b></a>` (tes `ReviuTautanTest` tetap mencari frasa yang sama).

- [ ] **Step 6: Jalankan tes** — `view:clear` lalu `…/phpunit tests/Feature/VerifikasiRt/MergeControllerTest.php tests/Feature/VerifikasiRt/ReviuTautanTest.php` → semua lulus.
- [ ] **Step 7: Cek visual singkat** (opsional): seed OT+Capil kembar, setujui tautan, buka `/admin/verifikasi-rt/gabung/{id}`, gabungkan, batalkan. Bersihkan demo.
- [ ] **Step 8: Commit** — `git add app/Http/Controllers/MergeIdentitasController.php resources/views/admin/verifikasi-rt/gabung resources/views/admin/verifikasi-rt/index.blade.php routes/web.php tests/Feature/VerifikasiRt/MergeControllerTest.php && git commit -m "feat(verifikasi-rt): halaman penggabungan Dinkes — pemilih kolom, riwayat, batalkan"`

---

### Task 6: Suite penuh & memori

- [ ] **Step 1:** `php artisan migrate` di DB dev.
- [ ] **Step 2:** Suite penuh (pastikan tak ada `php.exe` yatim) → `OK`.
- [ ] **Step 3:** Perbarui memori `project_verifikasi_rt.md` (T3 selesai, gotcha: snapshot `getAttributes()` menyimpan JSON string; `refreshAnak` dipakai alih-alih memindahkan `prioritas_gizi`).

---

## Self-Review

- **Spec coverage T3:** §3.1 `anak_merge_log` (Task 1), §3.2 `sumber_gabungan` (Task 1, 3), §6.3 pemilih kolom + default + baris OT dipertahankan + OT×OT ditolak + urutan pindah-dulu + `prioritas_gizi` + `pj` + status `digabung` + `verifikasi_anak` dipindah + Batalkan + penolakan bila data baru (Task 2–5), §10 f–g (Task 3–4 + penjaga OT). `refreshAnak` menggantikan "pindah baris prioritas" (setara: snapshot turunan).
- **Placeholder:** tidak ada; satu instruksi kondisional (bentuk `Imunisasi::create`) diberi alternatif konkret.
- **Konsistensi nama:** `IdentitasMergeService::{pratinjau,gabung,batalkan}`, `KOLOM*`, `AnakMergeLog::aktif()`, rute `admin.gabung.{index,show,store,batalkan}`, kunci snapshot `anak_dihapus / nilai_lama_dipertahankan / pilihan / dipindah / prioritas_dihapus / tautan_lain / id_anak_dipertahankan_saat_merge` sama di Task 3, 4, 5.

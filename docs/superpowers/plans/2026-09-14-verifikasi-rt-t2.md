# Verifikasi RT — Tahap 2 (Pemindai Kemiripan + Tautan Identitas) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aplikasi memindai seluruh tabel `anak` untuk pasangan baris yang kemungkinan satu orang (lintas sumber), RT melihatnya di tab "Kemungkinan sama" dan memutuskan **Sama/Beda**, puskesmas/Dinkes meninjau; tautan `sama` yang disetujui menunggu penggabungan (T3).

**Architecture:** Aturan skor lama di `CapilDedupService` diekstrak ke `IdentitasMatcher` (satu sumber kebenaran, `CapilDedupService` mendelegasikan). `TautanIdentitasService` memegang pindai → `anak_kandidat` (precompute), cakupan kandidat per RT, keputusan RT → `anak_tautan`, dan reviu. Controller RT/admin yang sudah ada di T1 ditambah endpoint & tab.

**Tech Stack:** Laravel 12, PHP 8.4, MySQL 8, Blade + vanilla JS, PHPUnit 11 (`RefreshDatabase`, DB `sirindu_testing`), queue `database`.

**Spec:** `docs/superpowers/specs/2026-09-14-verifikasi-rt-design.md` — §3.1 (`anak_tautan`, `anak_kandidat`), §4 tab 3, §5, §6.1 tab Tautan, §6.2 baris `beda`/`sama`, §10 e.

## Global Constraints

- **Dasbor OT tidak disentuh** (spec §1.1). Pindai **mengecualikan pasangan OT×OT** (`sumber='operasi_timbang'` keduanya) agar fitur ini tak pernah mengurangi populasi OT.
- Ambang kemiripan **tidak berubah** dari `CapilDedupService`: `CHILD_MIN=70`, `CHILD_NEAR_MIN=90`, `PARENT_MIN=87`, `DATE_TOLERANCE_DAYS=1`, `CHILD_STRONG=95`, `PARENT_STRONG=95`, blocking prefiks nama 3 huruf. `tests/Feature/CapilDedupTest.php` (23 tes) harus tetap hijau.
- Pasangan selalu disimpan ternormalisasi `id_anak_a < id_anak_b` (dilakukan service, bukan UI).
- Pasangan `beda` yang `disetujui` tidak pernah disarankan lagi; pasangan yang punya `anak_tautan` berstatus selain `ditolak` tidak tampil sebagai kandidat.
- RT hanya memutuskan pasangan yang **ada di `anak_kandidat`** dan minimal satu anggotanya dalam cakupan RT (§4). RT tidak bisa menautkan dua baris sembarang.
- Tulisan ke `anak` tidak ada di tahap ini (tanpa merge; tanpa perubahan `updated_at`).
- Jalankan PHP lewat path penuh `/d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe`; satu proses PHPUnit pada satu waktu; commit lokal di branch `feat/verifikasi-rt`, jangan push.
- Patch multi-baris: pakai Edit tool atau skrip Python di scratchpad — heredoc bash merusak backslash.

---

## File Structure

| Berkas | Tanggung jawab |
|---|---|
| `app/Services/IdentitasMatcher.php` (create) | aturan kemiripan + pindai semua pasangan (blocking) |
| `app/Services/CapilDedupService.php` (modify) | delegasi ke matcher; konstanta jadi alias |
| `database/migrations/2026_09_16_000001_create_anak_kandidat_table.php` | precompute kandidat |
| `database/migrations/2026_09_16_000002_create_anak_tautan_table.php` | keputusan pasangan |
| `app/Models/AnakKandidat.php`, `app/Models/AnakTautan.php` | model + helper urut pasangan |
| `app/Services/TautanIdentitasService.php` (create) | pindai, kandidat per RT, putuskan, tinjau, antrean |
| `app/Console/Commands/PindaiIdentitas.php` (create) | `identitas:pindai` |
| `app/Jobs/PindaiIdentitasJob.php` (create) | pindai di antrean (tombol "Pindai ulang") |
| `app/Jobs/ImportCapilJob.php`, `app/Jobs/ImportAnakJob.php` (modify) | pindai ulang setelah impor sukses |
| `app/Http/Controllers/Rt/VerifikasiRtController.php` (modify) | `kandidat`, `putuskan` |
| `resources/views/rt/verifikasi.blade.php` (modify) | tab 3 |
| `app/Http/Controllers/VerifikasiRtReviuController.php` (modify) | tab tautan, `tinjauTautan`, `pindai` |
| `resources/views/admin/verifikasi-rt/index.blade.php` (modify) | tab tautan + tombol pindai |
| `routes/web.php` (modify) | 4 rute baru |
| `tests/Feature/VerifikasiRt/IdentitasMatcherTest.php`, `PindaiKandidatTest.php`, `TautanIdentitasServiceTest.php`, `EndpointTautanRtTest.php`, `ReviuTautanTest.php` | tes per tugas |

---

### Task 1: `IdentitasMatcher` — ekstraksi aturan dari `CapilDedupService`

**Files:**
- Create: `app/Services/IdentitasMatcher.php`
- Modify: `app/Services/CapilDedupService.php` (konstanta, `nameSim`, `parentMatch`, `evaluate`, `findPairs` memakai matcher)
- Test: `tests/Feature/VerifikasiRt/IdentitasMatcherTest.php`

**Interfaces:**
- Produces `App\Services\IdentitasMatcher`:
  - konstanta publik `CHILD_MIN, CHILD_NEAR_MIN, PARENT_MIN, DATE_TOLERANCE_DAYS, CHILD_STRONG, PARENT_STRONG, NAME_BLOCK_LEN`
  - `nameSim(?string $a, ?string $b): float`, `parentMatch(Anak $a, Anak $b): float`, `kkSame(Anak $a, Anak $b): bool`
  - `evaluate(Anak $x, Anak $y): ?array{via:'kk'|'ortu'|'nama_kuat', score:float, child:float, parent:float}` — `via='nama_kuat'` bila lolos hanya lewat jalur nama≥95 & ortu≥95 dengan tanggal di luar toleransi
  - `nameKey(?string)`, `dateKey($v)`, `neighborDateKeys($v): array`, `dayDiff($a,$b): int`
  - `pindaiSemua(iterable $anak): array<int, array{a:int, b:int, via:string, score:float, child:float, parent:float}>` — semua pasangan unik (a<b) yang lolos `evaluate`, **kecuali** NIK sama atau keduanya `sumber='operasi_timbang'`; `$anak` = objek dengan properti `id, nik, nama, tgl_lahir, no_kk, nama_ibu, nama_ayah, sumber` (model `Anak` atau stdClass — `parentMatch` memakai `->nama_ibu`/`->nama_ayah`, jadi terima `object`).

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php
// tests/Feature/VerifikasiRt/IdentitasMatcherTest.php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Anak;
use App\Services\CapilDedupService;
use App\Services\IdentitasMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdentitasMatcherTest extends TestCase
{
    use RefreshDatabase;

    private IdentitasMatcher $m;

    protected function setUp(): void
    {
        parent::setUp();
        $this->m = new IdentitasMatcher();
    }

    private function anak(array $o): Anak
    {
        static $n = 0;
        $n++;
        return Anak::create(array_merge([
            'nama' => 'Anak', 'nik' => '3201000000010'.str_pad((string) $n, 3, '0', STR_PAD_LEFT), 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'capil',
            'nama_ibu' => 'Siti Aminah', 'nama_ayah' => 'Budi Santoso', 'no_kk' => null,
        ], $o));
    }

    public function test_konstanta_sama_dengan_capil_dedup(): void
    {
        $this->assertSame(CapilDedupService::CHILD_MIN, IdentitasMatcher::CHILD_MIN);
        $this->assertSame(CapilDedupService::CHILD_NEAR_MIN, IdentitasMatcher::CHILD_NEAR_MIN);
        $this->assertSame(CapilDedupService::PARENT_MIN, IdentitasMatcher::PARENT_MIN);
        $this->assertSame(CapilDedupService::DATE_TOLERANCE_DAYS, IdentitasMatcher::DATE_TOLERANCE_DAYS);
        $this->assertSame(CapilDedupService::CHILD_STRONG, IdentitasMatcher::CHILD_STRONG);
        $this->assertSame(CapilDedupService::PARENT_STRONG, IdentitasMatcher::PARENT_STRONG);
    }

    public function test_tgl_tepat_nama_70_dan_kk_sama_via_kk(): void
    {
        $a = $this->anak(['nama' => 'Muhammad Rizky', 'no_kk' => '6474010101010001', 'sumber' => 'operasi_timbang']);
        $b = $this->anak(['nama' => 'Muhamad Rizki', 'no_kk' => '6474010101010001', 'nama_ibu' => 'Orang Lain', 'nama_ayah' => 'Lain']);

        $r = $this->m->evaluate($a, $b);
        $this->assertNotNull($r);
        $this->assertSame('kk', $r['via']);
    }

    public function test_tgl_meleset_satu_hari_butuh_nama_90_via_ortu(): void
    {
        $a = $this->anak(['nama' => 'Aisyah Putri', 'tgl_lahir' => '2024-01-01']);
        $b = $this->anak(['nama' => 'Aisyah Putri', 'tgl_lahir' => '2024-01-02']);
        $c = $this->anak(['nama' => 'Aisyah Putry Ramadhani', 'tgl_lahir' => '2024-01-02']);

        $this->assertSame('ortu', $this->m->evaluate($a, $b)['via']);
        $this->assertNull($this->m->evaluate($a, $c), 'nama < 90% saat tanggal meleset → bukan kandidat');
    }

    public function test_nama_dan_ortu_sangat_mirip_abaikan_tanggal_via_nama_kuat(): void
    {
        $a = $this->anak(['nama' => 'Kevin Pratama', 'tgl_lahir' => '2024-01-01']);
        $b = $this->anak(['nama' => 'Kevin Pratama', 'tgl_lahir' => '2023-01-01']);

        $r = $this->m->evaluate($a, $b);
        $this->assertNotNull($r);
        $this->assertSame('nama_kuat', $r['via']);
    }

    public function test_tanpa_kk_dan_ortu_beda_bukan_kandidat(): void
    {
        $a = $this->anak(['nama' => 'Dewi Lestari']);
        $b = $this->anak(['nama' => 'Dewi Lestari', 'nama_ibu' => 'Rina', 'nama_ayah' => 'Joko']);

        $this->assertNull($this->m->evaluate($a, $b));
    }

    public function test_pindai_semua_mengembalikan_pasangan_unik_terurut_dan_mengecualikan_ot_ot(): void
    {
        $ot1 = $this->anak(['nama' => 'Farhan Akbar', 'sumber' => 'operasi_timbang']);
        $ot2 = $this->anak(['nama' => 'Farhan Akbar', 'sumber' => 'operasi_timbang']);
        $cap = $this->anak(['nama' => 'Farhan Akbar', 'sumber' => 'capil']);
        $this->anak(['nama' => 'Zulaikha', 'nama_ibu' => 'X', 'nama_ayah' => 'Y']);

        $pairs = $this->m->pindaiSemua(Anak::all());
        $set = array_map(fn ($p) => [$p['a'], $p['b']], $pairs);
        sort($set);

        $this->assertSame([[$ot1->id, $cap->id], [$ot2->id, $cap->id]], $set, 'OT×Capil masuk, OT×OT tidak');
        foreach ($pairs as $p) {
            $this->assertLessThan($p['b'], $p['a']);
            $this->assertArrayHasKey('score', $p);
        }
    }

    public function test_capil_dedup_masih_memakai_aturan_yang_sama(): void
    {
        $svc = new CapilDedupService();
        $a = $this->anak(['nama' => 'Nadia Salsabila', 'no_kk' => '6474000000000009']);
        $b = $this->anak(['nama' => 'Nadia Salsabilla', 'no_kk' => '6474000000000009']);

        $this->assertSame($this->m->evaluate($a, $b), $svc->evaluate($a, $b));
        $this->assertSame($this->m->nameSim('abc', 'abd'), $svc->nameSim('abc', 'abd'));
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `/d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe vendor/bin/phpunit tests/Feature/VerifikasiRt/IdentitasMatcherTest.php`
Expected: FAIL — `Class "App\Services\IdentitasMatcher" not found`.

- [ ] **Step 3: Tulis `IdentitasMatcher`**

```php
<?php
// app/Services/IdentitasMatcher.php

namespace App\Services;

/**
 * Aturan kemiripan identitas anak — satu-satunya sumber kebenaran, dipakai
 * CapilDedupService (dedup impor Capil) dan TautanIdentitasService (kandidat untuk RT).
 *
 * Kalibrasi asli (kontrol +/-, FP ~0,2%): lihat komentar CapilDedupService.
 *   1. tgl lahir TEPAT sama  → nama anak >= CHILD_MIN      dan (No KK sama atau ortu >= PARENT_MIN)
 *   2. tgl lahir meleset ±1  → nama anak >= CHILD_NEAR_MIN dan syarat yang sama
 *   3. nama >= CHILD_STRONG dan ortu >= PARENT_STRONG → tanggal diabaikan (typo tahun/bulan)
 */
class IdentitasMatcher
{
    public const CHILD_MIN = 70.0;
    public const CHILD_NEAR_MIN = 90.0;
    public const PARENT_MIN = 87.0;
    public const DATE_TOLERANCE_DAYS = 1;
    public const CHILD_STRONG = 95.0;
    public const PARENT_STRONG = 95.0;
    public const NAME_BLOCK_LEN = 3;

    /** Kemiripan dua string (persen, case-insensitive). */
    public function nameSim(?string $a, ?string $b): float
    {
        similar_text(trim(mb_strtolower((string) $a)), trim(mb_strtolower((string) $b)), $pct);
        return $pct;
    }

    /** Token nama ortu (nama_ibu + nama_ayah), dipecah pada '/' — sigizi sering "IBU / AYAH" di satu kolom. */
    private function parentTokens(object $a): array
    {
        $tokens = [];
        foreach (['nama_ibu', 'nama_ayah'] as $field) {
            foreach (explode('/', (string) ($a->$field ?? '')) as $seg) {
                $seg = trim(mb_strtolower($seg));
                if ($seg !== '') {
                    $tokens[] = $seg;
                }
            }
        }
        return $tokens;
    }

    /** Kemiripan terbaik antar-segmen nama ortu kedua record (persen). */
    public function parentMatch(object $a, object $b): float
    {
        $best = 0.0;
        foreach ($this->parentTokens($a) as $x) {
            foreach ($this->parentTokens($b) as $y) {
                similar_text($x, $y, $pct);
                if ($pct > $best) {
                    $best = $pct;
                }
            }
        }
        return $best;
    }

    public function kkSame(object $a, object $b): bool
    {
        $x = trim((string) ($a->no_kk ?? ''));
        $y = trim((string) ($b->no_kk ?? ''));
        return $x !== '' && $y !== '' && $x === $y;
    }

    public function dateKey($value): string
    {
        return substr((string) $value, 0, 10);
    }

    /** Kunci blocking nama: prefiks nama ternormalisasi. */
    public function nameKey(?string $nama): string
    {
        return mb_substr(trim(mb_strtolower((string) $nama)), 0, self::NAME_BLOCK_LEN);
    }

    /** Tgl tepat + tetangga dalam toleransi. */
    public function neighborDateKeys($value): array
    {
        $ts = strtotime($this->dateKey($value));
        if ($ts === false) {
            return [$this->dateKey($value)];
        }
        $keys = [];
        for ($d = -self::DATE_TOLERANCE_DAYS; $d <= self::DATE_TOLERANCE_DAYS; $d++) {
            $keys[] = date('Y-m-d', $ts + $d * 86400);
        }
        return $keys;
    }

    /** Selisih hari (mutlak); PHP_INT_MAX bila tak terbaca. */
    public function dayDiff($a, $b): int
    {
        $ta = strtotime($this->dateKey($a));
        $tb = strtotime($this->dateKey($b));
        if ($ta === false || $tb === false) {
            return PHP_INT_MAX;
        }
        return (int) round(abs($ta - $tb) / 86400);
    }

    /**
     * Nilai satu pasangan. null bila tak memenuhi aturan.
     *
     * @return array{via:string, score:float, child:float, parent:float}|null
     */
    public function evaluate(object $x, object $y): ?array
    {
        $child  = $this->nameSim($x->nama ?? null, $y->nama ?? null);
        $parent = $this->parentMatch($x, $y);
        $kkSame = $this->kkSame($x, $y);
        $diff   = $this->dayDiff($x->tgl_lahir ?? null, $y->tgl_lahir ?? null);
        $exactDate = $diff === 0;

        $strongName = $child >= self::CHILD_STRONG && $parent >= self::PARENT_STRONG;

        if (!$strongName) {
            if ($diff > self::DATE_TOLERANCE_DAYS) {
                return null;
            }
            if ($child < ($exactDate ? self::CHILD_MIN : self::CHILD_NEAR_MIN)) {
                return null;
            }
            if (!$kkSame && $parent < self::PARENT_MIN) {
                return null;
            }
        }

        $via = $kkSame ? 'kk' : 'ortu';
        if ($strongName && $diff > self::DATE_TOLERANCE_DAYS) {
            $via = 'nama_kuat'; // lolos hanya karena identitas nama sangat kuat
        }

        return [
            'via'    => $via,
            'score'  => ($kkSame ? 1000.0 : 0.0) + ($exactDate ? 100.0 : 0.0) + $parent + $child,
            'child'  => $child,
            'parent' => $parent,
        ];
    }

    /**
     * Semua pasangan kandidat di satu himpunan anak (lintas sumber), unik & terurut a<b.
     * Dikecualikan: NIK sama (mustahil di DB, jaga-jaga) dan OT×OT (spec verifikasi RT §5).
     *
     * @param  iterable<object> $anak  objek dengan id, nik, nama, tgl_lahir, no_kk, nama_ibu, nama_ayah, sumber
     * @return array<int, array{a:int, b:int, via:string, score:float, child:float, parent:float}>
     */
    public function pindaiSemua(iterable $anak): array
    {
        $rows = [];
        $byTgl = [];
        $byName = [];
        foreach ($anak as $r) {
            $rows[$r->id] = $r;
            $byTgl[$this->dateKey($r->tgl_lahir ?? null)][] = $r->id;
            $byName[$this->nameKey($r->nama ?? null)][] = $r->id;
        }

        $hasil = [];
        foreach ($rows as $id => $x) {
            $bucket = [];
            foreach ($this->neighborDateKeys($x->tgl_lahir ?? null) as $key) {
                foreach ($byTgl[$key] ?? [] as $other) {
                    $bucket[$other] = true;
                }
            }
            foreach ($byName[$this->nameKey($x->nama ?? null)] ?? [] as $other) {
                $bucket[$other] = true;
            }

            foreach (array_keys($bucket) as $otherId) {
                if ($otherId <= $id) {
                    continue; // tiap pasangan dinilai sekali, dari sisi id kecil
                }
                $y = $rows[$otherId];
                if ((string) ($x->nik ?? '') === (string) ($y->nik ?? '')) {
                    continue;
                }
                if (($x->sumber ?? null) === 'operasi_timbang' && ($y->sumber ?? null) === 'operasi_timbang') {
                    continue;
                }
                if ($info = $this->evaluate($x, $y)) {
                    $hasil[] = ['a' => (int) $id, 'b' => (int) $otherId] + $info;
                }
            }
        }

        return $hasil;
    }
}
```

- [ ] **Step 4: `CapilDedupService` mendelegasikan**

Di `CapilDedupService`:
- Ganti tujuh konstanta menjadi alias: `public const CHILD_MIN = IdentitasMatcher::CHILD_MIN;` (dst. untuk `CHILD_NEAR_MIN, PARENT_MIN, DATE_TOLERANCE_DAYS, CHILD_STRONG, PARENT_STRONG`) dan `private const NAME_BLOCK_LEN = IdentitasMatcher::NAME_BLOCK_LEN;`.
- Tambahkan properti + konstruktor dengan default (tes memakai `new CapilDedupService()`):

```php
    private IdentitasMatcher $matcher;

    public function __construct(?IdentitasMatcher $matcher = null)
    {
        $this->matcher = $matcher ?? new IdentitasMatcher();
    }
```

- Ganti isi `nameSim`, `parentMatch`, `kkSame`, `dateKey`, `nameKey`, `neighborDateKeys`, `dayDiff` menjadi pemanggilan `$this->matcher->…()` (hapus `parentTokens` privat). `evaluate(Anak $capil, Anak $sigizi)` menjadi:

```php
    public function evaluate(Anak $capil, Anak $sigizi): ?array
    {
        $info = $this->matcher->evaluate($capil, $sigizi);
        if ($info === null) {
            return null;
        }
        // Jalur dedup Capil hanya mengenal via kk|ortu (nama_kuat = ortu tanpa KK sama)
        $info['via'] = $this->matcher->kkSame($capil, $sigizi) ? 'kk' : 'ortu';
        return $info;
    }
```

`findPairs` tidak berubah (memakai helper yang kini mendelegasikan).

- [ ] **Step 5: Jalankan tes matcher + tes dedup lama**

Run: `/d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe vendor/bin/phpunit tests/Feature/VerifikasiRt/IdentitasMatcherTest.php tests/Feature/CapilDedupTest.php`
Expected: semua lulus (7 + 23).

- [ ] **Step 6: Commit**

```bash
git add app/Services/IdentitasMatcher.php app/Services/CapilDedupService.php tests/Feature/VerifikasiRt/IdentitasMatcherTest.php
git commit -m "refactor(identitas): ekstrak aturan kemiripan ke IdentitasMatcher + pindaiSemua"
```

---

### Task 2: Skema `anak_kandidat` & `anak_tautan` + model

**Files:**
- Create: `database/migrations/2026_09_16_000001_create_anak_kandidat_table.php`
- Create: `database/migrations/2026_09_16_000002_create_anak_tautan_table.php`
- Create: `app/Models/AnakKandidat.php`, `app/Models/AnakTautan.php`
- Test: `tests/Feature/VerifikasiRt/SkemaTautanTest.php`

**Interfaces:**
- Produces `AnakKandidat` (guarded [], relasi `anakA()`, `anakB()`), `AnakTautan` (konstanta `KEPUTUSAN=['sama','beda']`, `STATUS=['diusulkan','disetujui','ditolak','digabung']`, relasi `anakA()`, `anakB()`, `pengusul()`, `peninjau()`, static `urut(int $x, int $y): array{0:int,1:int}` mengembalikan `[min, max]`).

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php
// tests/Feature/VerifikasiRt/SkemaTautanTest.php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Anak;
use App\Models\AnakKandidat;
use App\Models\AnakTautan;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SkemaTautanTest extends TestCase
{
    use RefreshDatabase;

    private function anak(string $nik): Anak
    {
        return Anak::create(['nama' => 'Anak '.$nik, 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'capil']);
    }

    public function test_tabel_dan_kolom_ada(): void
    {
        $this->assertTrue(Schema::hasColumns('anak_kandidat', ['id_anak_a', 'id_anak_b', 'skor', 'via', 'child_sim', 'parent_sim', 'dipindai_at']));
        $this->assertTrue(Schema::hasColumns('anak_tautan', ['id_anak_a', 'id_anak_b', 'keputusan', 'skor', 'via', 'status',
            'diusulkan_oleh', 'diusulkan_at', 'ditinjau_oleh', 'ditinjau_at', 'catatan', 'catatan_reviu']));
    }

    public function test_urut_pasangan_dan_unik(): void
    {
        $this->assertSame([3, 9], AnakTautan::urut(9, 3));
        $a = $this->anak('3201000000011001');
        $b = $this->anak('3201000000011002');
        $u = User::factory()->create(['type' => 0]);

        AnakTautan::create(['id_anak_a' => $a->id, 'id_anak_b' => $b->id, 'keputusan' => 'sama',
            'diusulkan_oleh' => $u->id, 'diusulkan_at' => now()]);
        $this->assertSame('diusulkan', AnakTautan::first()->status);

        $this->expectException(QueryException::class);
        AnakTautan::create(['id_anak_a' => $a->id, 'id_anak_b' => $b->id, 'keputusan' => 'beda',
            'diusulkan_oleh' => $u->id, 'diusulkan_at' => now()]);
    }

    public function test_kandidat_ikut_terhapus_bila_anak_dihapus(): void
    {
        $a = $this->anak('3201000000011003');
        $b = $this->anak('3201000000011004');
        AnakKandidat::create(['id_anak_a' => $a->id, 'id_anak_b' => $b->id, 'skor' => 190, 'via' => 'ortu',
            'child_sim' => 100, 'parent_sim' => 90, 'dipindai_at' => now()]);

        $a->delete();

        $this->assertSame(0, AnakKandidat::count());
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `/d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe vendor/bin/phpunit tests/Feature/VerifikasiRt/SkemaTautanTest.php`
Expected: FAIL — `Class "App\Models\AnakTautan" not found` / kolom tidak ada.

- [ ] **Step 3: Migrasi & model**

```php
<?php
// database/migrations/2026_09_16_000001_create_anak_kandidat_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Hasil pindai kemiripan (precompute, diisi ulang penuh oleh identitas:pindai) — spec verifikasi RT §3.1. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anak_kandidat', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_anak_a');
            $table->unsignedBigInteger('id_anak_b');
            $table->decimal('skor', 8, 2);
            $table->string('via', 16);
            $table->decimal('child_sim', 5, 2);
            $table->decimal('parent_sim', 5, 2);
            $table->timestamp('dipindai_at');

            $table->unique(['id_anak_a', 'id_anak_b']);
            $table->index('id_anak_b');
            $table->foreign('id_anak_a')->references('id')->on('anak')->cascadeOnDelete();
            $table->foreign('id_anak_b')->references('id')->on('anak')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anak_kandidat');
    }
};
```

```php
<?php
// database/migrations/2026_09_16_000002_create_anak_tautan_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keputusan pasangan (sama/beda) oleh RT + reviu — spec verifikasi RT §3.1.
 * Tanpa FK cascade ke anak: baris `digabung` harus bertahan sebagai riwayat setelah merge (T3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anak_tautan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_anak_a');
            $table->unsignedBigInteger('id_anak_b');
            $table->enum('keputusan', ['sama', 'beda']);
            $table->decimal('skor', 8, 2)->nullable();
            $table->string('via', 16)->nullable();
            $table->enum('status', ['diusulkan', 'disetujui', 'ditolak', 'digabung'])->default('diusulkan');
            $table->unsignedBigInteger('diusulkan_oleh');
            $table->timestamp('diusulkan_at');
            $table->unsignedBigInteger('ditinjau_oleh')->nullable();
            $table->timestamp('ditinjau_at')->nullable();
            $table->text('catatan')->nullable();
            $table->text('catatan_reviu')->nullable();
            $table->timestamps();

            $table->unique(['id_anak_a', 'id_anak_b']);
            $table->index('id_anak_b');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anak_tautan');
    }
};
```

```php
<?php
// app/Models/AnakKandidat.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pasangan kandidat hasil pindai (a<b). Derived data — aman dihapus & dipindai ulang. */
class AnakKandidat extends Model
{
    protected $table = 'anak_kandidat';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['dipindai_at' => 'datetime'];

    public function anakA(): BelongsTo
    {
        return $this->belongsTo(Anak::class, 'id_anak_a');
    }

    public function anakB(): BelongsTo
    {
        return $this->belongsTo(Anak::class, 'id_anak_b');
    }
}
```

```php
<?php
// app/Models/AnakTautan.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Keputusan RT atas satu pasangan (a<b) + reviu. Ditulis hanya lewat TautanIdentitasService. */
class AnakTautan extends Model
{
    protected $table = 'anak_tautan';

    public const KEPUTUSAN = ['sama', 'beda'];
    public const STATUS    = ['diusulkan', 'disetujui', 'ditolak', 'digabung'];

    protected $guarded = [];
    protected $casts = ['diusulkan_at' => 'datetime', 'ditinjau_at' => 'datetime'];

    /** @return array{0:int,1:int} [min, max] */
    public static function urut(int $x, int $y): array
    {
        return $x < $y ? [$x, $y] : [$y, $x];
    }

    public function anakA(): BelongsTo
    {
        return $this->belongsTo(Anak::class, 'id_anak_a');
    }

    public function anakB(): BelongsTo
    {
        return $this->belongsTo(Anak::class, 'id_anak_b');
    }

    public function pengusul(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diusulkan_oleh');
    }

    public function peninjau(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditinjau_oleh');
    }
}
```

- [ ] **Step 4: Jalankan tes, pastikan lulus**

Run: `/d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe vendor/bin/phpunit tests/Feature/VerifikasiRt/SkemaTautanTest.php`
Expected: `OK (3 tests)`.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_09_16_000001_create_anak_kandidat_table.php database/migrations/2026_09_16_000002_create_anak_tautan_table.php app/Models/AnakKandidat.php app/Models/AnakTautan.php tests/Feature/VerifikasiRt/SkemaTautanTest.php
git commit -m "feat(verifikasi-rt): skema anak_kandidat & anak_tautan"
```

---

### Task 3: Pindai → `anak_kandidat` (service, command, job, hook impor)

**Files:**
- Create: `app/Services/TautanIdentitasService.php` (bagian pindai; bagian keputusan di Task 4)
- Create: `app/Console/Commands/PindaiIdentitas.php`
- Create: `app/Jobs/PindaiIdentitasJob.php`
- Modify: `app/Jobs/ImportCapilJob.php` (setelah `refreshAll()`), `app/Jobs/ImportAnakJob.php` (tempat yang setara)
- Test: `tests/Feature/VerifikasiRt/PindaiKandidatTest.php`

**Interfaces:**
- Consumes `IdentitasMatcher::pindaiSemua()`.
- Produces `TautanIdentitasService::pindai(): array{pasangan:int, via:array<string,int>, dipindai_at:string}` — memuat kolom `id, nik, nama, tgl_lahir, no_kk, nama_ibu, nama_ayah, sumber` seluruh `anak` lewat `DB::table('anak')->get()`, truncate `anak_kandidat`, insert per 500 baris dalam satu transaksi; `TautanIdentitasService::ringkasanKandidat(): array{jumlah:int, dipindai_at:?string}`.
- Command `identitas:pindai` mencetak ringkasan; `PindaiIdentitasJob` (`ShouldQueue`, `$timeout=1800`, `$tries=1`) memanggil `pindai()`.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php
// tests/Feature/VerifikasiRt/PindaiKandidatTest.php

namespace Tests\Feature\VerifikasiRt;

use App\Jobs\PindaiIdentitasJob;
use App\Models\Anak;
use App\Models\AnakKandidat;
use App\Services\TautanIdentitasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PindaiKandidatTest extends TestCase
{
    use RefreshDatabase;

    private function anak(string $nik, array $o = []): Anak
    {
        return Anak::create(array_merge([
            'nama' => 'Anak', 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'capil',
            'nama_ibu' => 'Siti Aminah', 'nama_ayah' => 'Budi Santoso',
        ], $o));
    }

    public function test_pindai_mengisi_anak_kandidat_dan_mengganti_hasil_lama(): void
    {
        $svc = app(TautanIdentitasService::class);
        $a = $this->anak('3201000000012001', ['nama' => 'Raka Wijaya', 'sumber' => 'operasi_timbang']);
        $b = $this->anak('3201000000012002', ['nama' => 'Raka Wijaya']);
        $this->anak('3201000000012003', ['nama' => 'Bunga', 'nama_ibu' => 'X', 'nama_ayah' => 'Y']);
        AnakKandidat::create(['id_anak_a' => $a->id, 'id_anak_b' => $b->id, 'skor' => 1, 'via' => 'lama',
            'child_sim' => 1, 'parent_sim' => 1, 'dipindai_at' => now()->subDay()]);

        $ringkas = $svc->pindai();

        $this->assertSame(1, $ringkas['pasangan']);
        $this->assertSame(['ortu' => 1], $ringkas['via']);
        $k = AnakKandidat::sole();
        $this->assertSame([$a->id, $b->id], [(int) $k->id_anak_a, (int) $k->id_anak_b]);
        $this->assertSame('ortu', $k->via, 'hasil lama diganti');
        $this->assertSame(1, $svc->ringkasanKandidat()['jumlah']);
    }

    public function test_command_identitas_pindai_mencetak_ringkasan(): void
    {
        $this->anak('3201000000012004', ['nama' => 'Dimas']);
        $this->anak('3201000000012005', ['nama' => 'Dimas']);

        $this->artisan('identitas:pindai')
            ->expectsOutputToContain('1 pasangan')
            ->assertExitCode(0);
    }

    public function test_job_pindai_menjalankan_service(): void
    {
        $this->anak('3201000000012006', ['nama' => 'Laras']);
        $this->anak('3201000000012007', ['nama' => 'Laras']);

        (new PindaiIdentitasJob())->handle(app(TautanIdentitasService::class));

        $this->assertSame(1, AnakKandidat::count());
    }

    public function test_import_job_memanggil_pindai_setelah_sukses(): void
    {
        $src = file_get_contents(app_path('Jobs/ImportCapilJob.php'));
        $this->assertStringContainsString('TautanIdentitasService::class)->pindai()', $src);
        $srcAnak = file_get_contents(app_path('Jobs/ImportAnakJob.php'));
        $this->assertStringContainsString('TautanIdentitasService::class)->pindai()', $srcAnak);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `/d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe vendor/bin/phpunit tests/Feature/VerifikasiRt/PindaiKandidatTest.php`
Expected: FAIL — `Class "App\Services\TautanIdentitasService" not found`.

- [ ] **Step 3: Service (bagian pindai), command, job**

```php
<?php
// app/Services/TautanIdentitasService.php

namespace App\Services;

use App\Models\AnakKandidat;
use Illuminate\Support\Facades\DB;

/**
 * Tautan identitas anak (spec verifikasi RT §3.1, §4 tab 3, §6): pindai kandidat,
 * cakupan per RT, keputusan RT (sama/beda), reviu. Bagian keputusan ditambah di Task 4.
 */
class TautanIdentitasService
{
    public function __construct(private readonly IdentitasMatcher $matcher)
    {
    }

    /**
     * Pindai ulang seluruh anak → anak_kandidat (truncate + insert). Idempoten.
     *
     * @return array{pasangan:int, via:array<string,int>, dipindai_at:string}
     */
    public function pindai(): array
    {
        $anak = DB::table('anak')
            ->select('id', 'nik', 'nama', 'tgl_lahir', 'no_kk', 'nama_ibu', 'nama_ayah', 'sumber')
            ->get();

        $pairs = $this->matcher->pindaiSemua($anak);
        $now   = now();
        $via   = [];

        DB::transaction(function () use ($pairs, $now, &$via) {
            DB::table('anak_kandidat')->delete();
            foreach (array_chunk($pairs, 500) as $chunk) {
                DB::table('anak_kandidat')->insert(array_map(fn ($p) => [
                    'id_anak_a'   => $p['a'],
                    'id_anak_b'   => $p['b'],
                    'skor'        => round($p['score'], 2),
                    'via'         => $p['via'],
                    'child_sim'   => round($p['child'], 2),
                    'parent_sim'  => round($p['parent'], 2),
                    'dipindai_at' => $now,
                ], $chunk));
            }
            foreach ($pairs as $p) {
                $via[$p['via']] = ($via[$p['via']] ?? 0) + 1;
            }
        });

        ksort($via);

        return ['pasangan' => count($pairs), 'via' => $via, 'dipindai_at' => $now->toDateTimeString()];
    }

    /** @return array{jumlah:int, dipindai_at:?string} */
    public function ringkasanKandidat(): array
    {
        return [
            'jumlah'      => AnakKandidat::count(),
            'dipindai_at' => AnakKandidat::max('dipindai_at'),
        ];
    }
}
```

```php
<?php
// app/Console/Commands/PindaiIdentitas.php

namespace App\Console\Commands;

use App\Services\TautanIdentitasService;
use Illuminate\Console\Command;

class PindaiIdentitas extends Command
{
    protected $signature = 'identitas:pindai';
    protected $description = 'Pindai seluruh anak untuk pasangan yang kemungkinan satu orang (isi ulang anak_kandidat)';

    public function handle(TautanIdentitasService $svc): int
    {
        $r = $svc->pindai();
        $this->info("{$r['pasangan']} pasangan kandidat ditemukan ({$r['dipindai_at']}).");
        foreach ($r['via'] as $via => $n) {
            $this->line("  via {$via}: {$n}");
        }

        return self::SUCCESS;
    }
}
```

```php
<?php
// app/Jobs/PindaiIdentitasJob.php

namespace App\Jobs;

use App\Services\TautanIdentitasService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/** Pindai ulang kandidat identitas di antrean — dipicu tombol "Pindai ulang" (superadmin). */
class PindaiIdentitasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    public int $tries = 1;

    public function handle(TautanIdentitasService $svc): void
    {
        $r = $svc->pindai();
        Log::info("PindaiIdentitasJob selesai: {$r['pasangan']} pasangan.");
    }
}
```

Hook impor — `app/Jobs/ImportCapilJob.php`, tepat setelah `app(PrioritasGiziService::class)->refreshAll();`:

```php
            // Data anak berubah → segarkan kandidat tautan identitas untuk halaman RT (spec verifikasi RT §5)
            app(\App\Services\TautanIdentitasService::class)->pindai();
```

`app/Jobs/ImportAnakJob.php` — baca dulu; sisipkan baris yang sama tepat sebelum `$this->importLog->update([ 'status' => 'done', …` di jalur sukses (bila job itu sudah memanggil `refreshAll()`, letakkan setelahnya).

- [ ] **Step 4: Jalankan tes, pastikan lulus**

Run: `/d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe vendor/bin/phpunit tests/Feature/VerifikasiRt/PindaiKandidatTest.php tests/Feature/ImportCapilTest.php`
Expected: semua lulus (tes impor Capil lama tetap hijau meski kini memanggil pindai).

- [ ] **Step 5: Commit**

```bash
git add app/Services/TautanIdentitasService.php app/Console/Commands/PindaiIdentitas.php app/Jobs/PindaiIdentitasJob.php app/Jobs/ImportCapilJob.php app/Jobs/ImportAnakJob.php tests/Feature/VerifikasiRt/PindaiKandidatTest.php
git commit -m "feat(verifikasi-rt): pindai kandidat identitas (service, command, job, hook impor)"
```

---

### Task 4: Kandidat per RT, keputusan Sama/Beda, reviu tautan

**Files:**
- Modify: `app/Services/TautanIdentitasService.php`
- Test: `tests/Feature/VerifikasiRt/TautanIdentitasServiceTest.php`

**Interfaces:**
- Consumes `VerifikasiRtService::wargaQuery()`, `::tanpaRtQuery()`, `::dalamCakupan()`.
- Produces di `TautanIdentitasService`:
  - `kandidatUntukRt(Rt $rt): \Illuminate\Support\Collection<AnakKandidat>` — kandidat yang ≥1 anggotanya ada di cakupan RT (warga ∪ tanpa-RT), **tanpa** pasangan yang punya `anak_tautan` berstatus ≠ `ditolak`; eager `anakA.posyandu`, `anakB.posyandu`; urut skor desc.
  - `putuskan(int $idX, int $idY, Rt $rt, User $oleh, string $keputusan, ?string $catatan = null): AnakTautan` — normalisasi urut; `InvalidArgumentException` bila keputusan tak dikenal / pasangan bukan kandidat / sudah `disetujui`/`digabung`; `AuthorizationException` bila tak ada anggota dalam cakupan; bila sudah ada baris `diusulkan`/`ditolak` → timpa (keputusan, skor, via, pengusul, `status='diusulkan'`, kosongkan kolom reviu).
  - `tinjauTautan(AnakTautan $t, User $peninjau, bool $setuju, ?string $catatan = null): AnakTautan` — `InvalidArgumentException` bila `status !== 'diusulkan'`; non-super: kelurahan salah satu anak (atau `id_kel` NULL → lewat RT pengusul: `pengusul->rt->id_kelurahan`) harus = `user.id_kel`, selain itu `AuthorizationException`.
  - `antreanTautanQuery(User $peninjau): Builder` — `status='diusulkan'`, scope kelurahan seperti di atas, eager `anakA, anakB, pengusul`.
  - `menungguGabung(): int` — jumlah `keputusan='sama' AND status='disetujui'` (dipakai T3; ditampilkan di reviu).

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php
// tests/Feature/VerifikasiRt/TautanIdentitasServiceTest.php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Anak;
use App\Models\AnakKandidat;
use App\Models\AnakTautan;
use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\User;
use App\Services\TautanIdentitasService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TautanIdentitasServiceTest extends TestCase
{
    use RefreshDatabase;

    private TautanIdentitasService $svc;
    private Rt $rt;
    private User $userRt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc    = app(TautanIdentitasService::class);
        $this->rt     = Rt::factory()->create();
        $this->userRt = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => $this->rt->id, 'id_kel' => $this->rt->id_kelurahan]);
    }

    private function anak(string $nik, array $o = []): Anak
    {
        return Anak::create(array_merge([
            'nama' => 'Anak '.$nik, 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'capil',
            'id_kel' => $this->rt->id_kelurahan, 'id_rt' => $this->rt->id,
        ], $o));
    }

    private function kandidat(Anak $x, Anak $y): AnakKandidat
    {
        [$a, $b] = AnakTautan::urut($x->id, $y->id);
        return AnakKandidat::create(['id_anak_a' => $a, 'id_anak_b' => $b, 'skor' => 190, 'via' => 'ortu',
            'child_sim' => 100, 'parent_sim' => 90, 'dipindai_at' => now()]);
    }

    public function test_kandidat_untuk_rt_minimal_satu_anggota_dalam_cakupan(): void
    {
        $warga  = $this->anak('3201000000013001');
        $luar1  = $this->anak('3201000000013002', ['id_rt' => null, 'id_kel' => Kelurahan::factory()->create()->id]);
        $luar2  = $this->anak('3201000000013003', ['id_rt' => null, 'id_kel' => Kelurahan::factory()->create()->id]);
        $tanpa  = $this->anak('3201000000013004', ['id_rt' => null]);
        $k1 = $this->kandidat($warga, $luar1);   // warga × luar → tampil
        $k2 = $this->kandidat($luar1, $luar2);   // luar × luar → tidak
        $k3 = $this->kandidat($tanpa, $luar2);   // tanpa-RT sekelurahan × luar → tampil

        $ids = $this->svc->kandidatUntukRt($this->rt)->pluck('id')->sort()->values()->all();
        $this->assertSame([$k1->id, $k3->id], $ids);
    }

    public function test_kandidat_yang_sudah_diputus_tidak_tampil_kecuali_ditolak(): void
    {
        $a = $this->anak('3201000000013005');
        $b = $this->anak('3201000000013006', ['id_rt' => null]);
        $this->kandidat($a, $b);

        $t = $this->svc->putuskan($a->id, $b->id, $this->rt, $this->userRt, 'beda');
        $this->assertCount(0, $this->svc->kandidatUntukRt($this->rt));

        $super = User::factory()->create(['type' => 0]);
        $this->svc->tinjauTautan($t, $super, false, 'cek lagi');
        $this->assertCount(1, $this->svc->kandidatUntukRt($this->rt), 'ditolak peninjau → muncul lagi');
    }

    public function test_putuskan_menormalkan_urutan_dan_menyalin_skor(): void
    {
        $a = $this->anak('3201000000013007');
        $b = $this->anak('3201000000013008', ['id_rt' => null]);
        $this->kandidat($a, $b);

        $t = $this->svc->putuskan($b->id, $a->id, $this->rt, $this->userRt, 'sama', 'anak yang sama, NIK lama salah');

        $this->assertSame([$a->id, $b->id], [(int) $t->id_anak_a, (int) $t->id_anak_b]);
        $this->assertSame('sama', $t->keputusan);
        $this->assertSame('diusulkan', $t->status);
        $this->assertSame('ortu', $t->via);
        $this->assertEquals(190, $t->skor);
        $this->assertSame($this->userRt->id, (int) $t->diusulkan_oleh);
    }

    public function test_putuskan_bukan_kandidat_ditolak(): void
    {
        $a = $this->anak('3201000000013009');
        $b = $this->anak('3201000000013010');

        $this->expectException(\InvalidArgumentException::class);
        $this->svc->putuskan($a->id, $b->id, $this->rt, $this->userRt, 'sama');
    }

    public function test_putuskan_di_luar_cakupan_ditolak(): void
    {
        $kelLain = Kelurahan::factory()->create()->id;
        $a = $this->anak('3201000000013011', ['id_rt' => null, 'id_kel' => $kelLain]);
        $b = $this->anak('3201000000013012', ['id_rt' => null, 'id_kel' => $kelLain]);
        $this->kandidat($a, $b);

        $this->expectException(AuthorizationException::class);
        $this->svc->putuskan($a->id, $b->id, $this->rt, $this->userRt, 'sama');
    }

    public function test_putuskan_ulang_menimpa_usulan_yang_belum_disetujui(): void
    {
        $a = $this->anak('3201000000013013');
        $b = $this->anak('3201000000013014', ['id_rt' => null]);
        $this->kandidat($a, $b);
        $t1 = $this->svc->putuskan($a->id, $b->id, $this->rt, $this->userRt, 'beda');
        $t2 = $this->svc->putuskan($a->id, $b->id, $this->rt, $this->userRt, 'sama');

        $this->assertSame($t1->id, $t2->id);
        $this->assertSame('sama', $t2->keputusan);
        $this->assertSame(1, AnakTautan::count());
    }

    public function test_tautan_yang_sudah_disetujui_tidak_bisa_diputus_ulang(): void
    {
        $a = $this->anak('3201000000013015');
        $b = $this->anak('3201000000013016', ['id_rt' => null]);
        $this->kandidat($a, $b);
        $t = $this->svc->putuskan($a->id, $b->id, $this->rt, $this->userRt, 'sama');
        $this->svc->tinjauTautan($t, User::factory()->create(['type' => 0]), true);

        $this->expectException(\InvalidArgumentException::class);
        $this->svc->putuskan($a->id, $b->id, $this->rt, $this->userRt, 'beda');
    }

    public function test_reviu_sama_disetujui_masuk_hitungan_menunggu_gabung(): void
    {
        $a = $this->anak('3201000000013017');
        $b = $this->anak('3201000000013018', ['id_rt' => null]);
        $this->kandidat($a, $b);
        $t = $this->svc->putuskan($a->id, $b->id, $this->rt, $this->userRt, 'sama');
        $faskes = User::factory()->create(['type' => 1, 'id_kel' => $this->rt->id_kelurahan]);

        $this->assertSame([$t->id], $this->svc->antreanTautanQuery($faskes)->pluck('id')->all());
        $this->svc->tinjauTautan($t, $faskes, true, 'ok');

        $this->assertSame('disetujui', $t->fresh()->status);
        $this->assertSame(1, $this->svc->menungguGabung());
        $this->assertSame([], $this->svc->antreanTautanQuery($faskes)->pluck('id')->all());
    }

    public function test_faskes_kelurahan_lain_tidak_bisa_meninjau(): void
    {
        $a = $this->anak('3201000000013019');
        $b = $this->anak('3201000000013020', ['id_rt' => null]);
        $this->kandidat($a, $b);
        $t = $this->svc->putuskan($a->id, $b->id, $this->rt, $this->userRt, 'beda');
        $faskesLain = User::factory()->create(['type' => 1, 'id_kel' => Kelurahan::factory()->create()->id]);

        $this->assertSame([], $this->svc->antreanTautanQuery($faskesLain)->pluck('id')->all());
        $this->expectException(AuthorizationException::class);
        $this->svc->tinjauTautan($t, $faskesLain, true);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `/d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe vendor/bin/phpunit tests/Feature/VerifikasiRt/TautanIdentitasServiceTest.php`
Expected: FAIL — `Call to undefined method …::kandidatUntukRt()`.

- [ ] **Step 3: Tambahkan ke `TautanIdentitasService`**

Tambahkan `use` berikut di atas: `App\Models\Anak`, `App\Models\AnakTautan`, `App\Models\Rt`, `App\Models\User`, `Illuminate\Auth\Access\AuthorizationException`, `Illuminate\Database\Eloquent\Builder`, `Illuminate\Support\Collection`, `InvalidArgumentException`. Konstruktor menjadi:

```php
    public function __construct(
        private readonly IdentitasMatcher $matcher,
        private readonly VerifikasiRtService $verifikasi,
    ) {
    }
```

Metode baru:

```php
    // =========================================================================
    // Kandidat per RT & keputusan (spec §4 tab 3, §6)
    // =========================================================================

    /** Id anak dalam cakupan RT: warga ∪ sekelurahan tanpa RT (yang belum dinyatakan bukan warga). */
    private function idCakupan(Rt $rt): array
    {
        return array_values(array_unique(array_merge(
            $this->verifikasi->wargaQuery($rt)->pluck('id')->all(),
            $this->verifikasi->tanpaRtQuery($rt)->pluck('id')->all(),
        )));
    }

    /** Kandidat yang ≥1 anggotanya dalam cakupan RT, minus pasangan yang sudah diputus (kecuali ditolak). */
    public function kandidatUntukRt(Rt $rt): Collection
    {
        $ids = $this->idCakupan($rt);
        if (empty($ids)) {
            return collect();
        }

        return AnakKandidat::query()
            ->with(['anakA.posyandu:id,name', 'anakB.posyandu:id,name'])
            ->where(fn ($q) => $q->whereIn('id_anak_a', $ids)->orWhereIn('id_anak_b', $ids))
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('anak_tautan as t')
                  ->whereColumn('t.id_anak_a', 'anak_kandidat.id_anak_a')
                  ->whereColumn('t.id_anak_b', 'anak_kandidat.id_anak_b')
                  ->where('t.status', '!=', 'ditolak');
            })
            ->orderByDesc('skor')
            ->get();
    }

    /** Keputusan RT atas satu kandidat. Menimpa usulan lama yang belum disetujui. */
    public function putuskan(int $idX, int $idY, Rt $rt, User $oleh, string $keputusan, ?string $catatan = null): AnakTautan
    {
        if (!in_array($keputusan, AnakTautan::KEPUTUSAN, true)) {
            throw new InvalidArgumentException("Keputusan tidak dikenal: {$keputusan}");
        }
        [$a, $b] = AnakTautan::urut($idX, $idY);

        $kandidat = AnakKandidat::where('id_anak_a', $a)->where('id_anak_b', $b)->first();
        if (!$kandidat) {
            throw new InvalidArgumentException('Pasangan ini bukan kandidat hasil pindai.');
        }

        $anakA = Anak::findOrFail($a);
        $anakB = Anak::findOrFail($b);
        if (!$this->verifikasi->dalamCakupan($anakA, $rt) && !$this->verifikasi->dalamCakupan($anakB, $rt)) {
            throw new AuthorizationException('Pasangan di luar cakupan RT ini.');
        }

        $t = AnakTautan::where('id_anak_a', $a)->where('id_anak_b', $b)->first();
        if ($t && in_array($t->status, ['disetujui', 'digabung'], true)) {
            throw new InvalidArgumentException('Pasangan ini sudah diputus dan disetujui.');
        }

        $data = [
            'keputusan'      => $keputusan,
            'skor'           => $kandidat->skor,
            'via'            => $kandidat->via,
            'status'         => 'diusulkan',
            'diusulkan_oleh' => $oleh->id,
            'diusulkan_at'   => now(),
            'ditinjau_oleh'  => null,
            'ditinjau_at'    => null,
            'catatan'        => $catatan ?: null,
            'catatan_reviu'  => null,
        ];

        if ($t) {
            $t->update($data);
            return $t->fresh();
        }

        return AnakTautan::create(['id_anak_a' => $a, 'id_anak_b' => $b] + $data);
    }

    /** Reviu puskesmas (kelurahan salah satu anak) / Dinkes. */
    public function tinjauTautan(AnakTautan $t, User $peninjau, bool $setuju, ?string $catatan = null): AnakTautan
    {
        if ($t->status !== 'diusulkan') {
            throw new InvalidArgumentException('Tautan ini sudah ditinjau.');
        }
        if (!$peninjau->isSuperAdmin()) {
            if (!$peninjau->id_kel || !in_array((int) $peninjau->id_kel, $this->kelurahanTautan($t), true)) {
                throw new AuthorizationException('Tautan di luar kelurahan peninjau.');
            }
        }

        $t->update([
            'status'        => $setuju ? 'disetujui' : 'ditolak',
            'ditinjau_oleh' => $peninjau->id,
            'ditinjau_at'   => now(),
            'catatan_reviu' => $catatan ?: null,
        ]);

        return $t->fresh();
    }

    /** Kelurahan yang "memiliki" tautan: id_kel kedua anak, atau kelurahan RT pengusul bila keduanya kosong. */
    private function kelurahanTautan(AnakTautan $t): array
    {
        $kel = array_filter([(int) $t->anakA?->id_kel, (int) $t->anakB?->id_kel]);
        if (empty($kel)) {
            $kel = [(int) $t->pengusul?->rt?->id_kelurahan];
        }
        return array_values(array_unique($kel));
    }

    /** Antrean tautan `diusulkan`, dibatasi kelurahan untuk non-superadmin. */
    public function antreanTautanQuery(User $peninjau): Builder
    {
        $q = AnakTautan::query()->with(['anakA', 'anakB', 'pengusul.rt'])->where('status', 'diusulkan');

        if (!$peninjau->isSuperAdmin()) {
            $kel = (int) $peninjau->id_kel;
            $q->where(function ($w) use ($kel) {
                $w->whereHas('anakA', fn ($a) => $a->where('id_kel', $kel))
                  ->orWhereHas('anakB', fn ($a) => $a->where('id_kel', $kel))
                  ->orWhere(fn ($x) => $x->whereHas('anakA', fn ($a) => $a->whereNull('id_kel'))
                                          ->whereHas('anakB', fn ($a) => $a->whereNull('id_kel'))
                                          ->whereHas('pengusul.rt', fn ($r) => $r->where('id_kelurahan', $kel)));
            });
        }

        return $q->orderByDesc('skor')->orderBy('id');
    }

    /** Tautan `sama` yang disetujui dan belum digabung (dikonsumsi T3). */
    public function menungguGabung(): int
    {
        return AnakTautan::where('keputusan', 'sama')->where('status', 'disetujui')->count();
    }
```

Catatan: `whereHas('pengusul.rt', …)` memerlukan relasi `User::rt()` (ada sejak T1).

- [ ] **Step 4: Jalankan tes, pastikan lulus**

Run: `/d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe vendor/bin/phpunit tests/Feature/VerifikasiRt/TautanIdentitasServiceTest.php tests/Feature/VerifikasiRt/PindaiKandidatTest.php`
Expected: semua lulus (9 + 4).

- [ ] **Step 5: Commit**

```bash
git add app/Services/TautanIdentitasService.php tests/Feature/VerifikasiRt/TautanIdentitasServiceTest.php
git commit -m "feat(verifikasi-rt): kandidat per RT, keputusan sama/beda, reviu tautan"
```

---

### Task 5: Endpoint RT + tab "Kemungkinan sama"

**Files:**
- Modify: `app/Http/Controllers/Rt/VerifikasiRtController.php` (+`kandidat`, `putuskan`; `rows()`→ pecah `row(Anak)` agar dipakai ulang)
- Modify: `routes/web.php` (grup `rt.`)
- Modify: `resources/views/rt/verifikasi.blade.php` (tab 3)
- Test: `tests/Feature/VerifikasiRt/EndpointTautanRtTest.php`

**Interfaces:**
- Routes: `rt.api.kandidat` GET `/rt/api/kandidat` → `{rows:[{a:{…row}, b:{…row}, skor, via, via_label, beda:[field,…]}], jumlah:int}`; `rt.api.putuskan` POST `/rt/api/tautan` body `{a: hashid, b: hashid, keputusan: sama|beda, catatan?}` → `{id, keputusan, status}`.
- `beda` = daftar nama field identitas yang nilainya berbeda antara a dan b (`nik, nama, tgl_lahir, jk, nama_ibu, nama_ayah, no_kk, alamat, alamat_ktp, posyandu`) untuk disorot UI.
- Hashid → id: `Anak::findByHashIdOrFail($hash)` (trait `HasHashId`).

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php
// tests/Feature/VerifikasiRt/EndpointTautanRtTest.php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Anak;
use App\Models\AnakKandidat;
use App\Models\AnakTautan;
use App\Models\Rt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndpointTautanRtTest extends TestCase
{
    use RefreshDatabase;

    private Rt $rt;
    private User $userRt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rt     = Rt::factory()->create();
        $this->userRt = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => $this->rt->id, 'id_kel' => $this->rt->id_kelurahan]);
    }

    private function anak(string $nik, array $o = []): Anak
    {
        return Anak::create(array_merge([
            'nama' => 'Anak '.$nik, 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'capil',
            'id_kel' => $this->rt->id_kelurahan, 'id_rt' => $this->rt->id, 'nama_ibu' => 'Ibu', 'nama_ayah' => 'Ayah',
        ], $o));
    }

    private function kandidat(Anak $x, Anak $y): void
    {
        [$a, $b] = AnakTautan::urut($x->id, $y->id);
        AnakKandidat::create(['id_anak_a' => $a, 'id_anak_b' => $b, 'skor' => 1190, 'via' => 'kk',
            'child_sim' => 100, 'parent_sim' => 90, 'dipindai_at' => now()]);
    }

    public function test_kandidat_mengembalikan_pasangan_dengan_field_yang_berbeda(): void
    {
        $a = $this->anak('3201000000014001', ['nama' => 'Rafi Ahmad', 'sumber' => 'operasi_timbang']);
        $b = $this->anak('3201000000014002', ['nama' => 'Rafi Ahmad', 'alamat' => 'Jl. Lain', 'id_rt' => null]);
        $this->kandidat($a, $b);

        $res = $this->actingAs($this->userRt)->getJson(route('rt.api.kandidat'))->assertOk()->json();

        $this->assertSame(1, $res['jumlah']);
        $row = $res['rows'][0];
        $this->assertSame($a->hashid, $row['a']['id']);
        $this->assertSame($b->hashid, $row['b']['id']);
        $this->assertSame('OT', $row['a']['sumber_label']);
        $this->assertSame('kk', $row['via']);
        $this->assertContains('nik', $row['beda']);
        $this->assertContains('alamat', $row['beda']);
        $this->assertNotContains('nama', $row['beda']);
    }

    public function test_putuskan_sama_menyimpan_tautan_dan_menghilangkan_kandidat(): void
    {
        $a = $this->anak('3201000000014003');
        $b = $this->anak('3201000000014004', ['id_rt' => null]);
        $this->kandidat($a, $b);

        $this->actingAs($this->userRt)
            ->postJson(route('rt.api.putuskan'), ['a' => $b->hashid, 'b' => $a->hashid, 'keputusan' => 'sama', 'catatan' => 'sama'])
            ->assertOk()
            ->assertJsonPath('keputusan', 'sama')
            ->assertJsonPath('status', 'diusulkan');

        $this->assertSame(1, AnakTautan::count());
        $this->assertSame(0, $this->actingAs($this->userRt)->getJson(route('rt.api.kandidat'))->json('jumlah'));
    }

    public function test_putuskan_pasangan_di_luar_cakupan_403_dan_bukan_kandidat_422(): void
    {
        $rtLain = Rt::factory()->create();
        $x = $this->anak('3201000000014005', ['id_rt' => $rtLain->id, 'id_kel' => $rtLain->id_kelurahan]);
        $y = $this->anak('3201000000014006', ['id_rt' => $rtLain->id, 'id_kel' => $rtLain->id_kelurahan]);
        $this->kandidat($x, $y);
        $this->actingAs($this->userRt)
            ->postJson(route('rt.api.putuskan'), ['a' => $x->hashid, 'b' => $y->hashid, 'keputusan' => 'sama'])
            ->assertForbidden();

        $p = $this->anak('3201000000014007');
        $q = $this->anak('3201000000014008');
        $this->actingAs($this->userRt)
            ->postJson(route('rt.api.putuskan'), ['a' => $p->hashid, 'b' => $q->hashid, 'keputusan' => 'beda'])
            ->assertStatus(422);
    }

    public function test_halaman_rt_memuat_tab_kemungkinan_sama(): void
    {
        $this->actingAs($this->userRt)->get(route('rt.verifikasi'))
            ->assertOk()
            ->assertSee('Kemungkinan sama')
            ->assertSee(route('rt.api.kandidat'))
            ->assertSee(route('rt.api.putuskan'))
            ->assertSee('data-keputusan="sama"', false)
            ->assertSee('data-keputusan="beda"', false);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `/d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe vendor/bin/phpunit tests/Feature/VerifikasiRt/EndpointTautanRtTest.php`
Expected: FAIL — `Route [rt.api.kandidat] not defined.`

- [ ] **Step 3: Controller & rute**

Di `VerifikasiRtController`:
- Konstruktor: `public function __construct(private readonly VerifikasiRtService $svc, private readonly TautanIdentitasService $tautan) {}` (+`use App\Services\TautanIdentitasService; use App\Models\AnakTautan;`).
- Pecah `rows()`: pindahkan pemetaan per anak ke `private function row(Anak $a): array` (isi persis array lama), lalu `rows()` = `$q->with('posyandu:id,name')->orderBy('nama')->get()->map(fn (Anak $a) => $this->row($a))->values()->all()`.
- Tambahkan:

```php
    private const LABEL_VIA = ['kk' => 'No KK sama', 'ortu' => 'Nama & orang tua mirip', 'nama_kuat' => 'Nama & orang tua sangat mirip (tanggal beda)'];
    private const FIELD_BANDING = ['nik', 'nama', 'tgl_lahir', 'jk', 'nama_ibu', 'nama_ayah', 'no_kk', 'alamat', 'alamat_ktp', 'posyandu'];

    public function kandidat(Request $request): JsonResponse
    {
        $rt = $this->rt($request);
        $rows = $this->tautan->kandidatUntukRt($rt)->map(function ($k) {
            $a = $this->row($k->anakA);
            $b = $this->row($k->anakB);
            $beda = array_values(array_filter(self::FIELD_BANDING, fn ($f) => trim((string) $a[$f]) !== trim((string) $b[$f])));
            return [
                'a'         => $a,
                'b'         => $b,
                'skor'      => (float) $k->skor,
                'via'       => $k->via,
                'via_label' => self::LABEL_VIA[$k->via] ?? $k->via,
                'beda'      => $beda,
            ];
        })->values()->all();

        return response()->json(['rows' => $rows, 'jumlah' => count($rows)]);
    }

    public function putuskan(Request $request): JsonResponse
    {
        $rt   = $this->rt($request);
        $data = $request->validate([
            'a'         => 'required|string',
            'b'         => 'required|string',
            'keputusan' => ['required', Rule::in(AnakTautan::KEPUTUSAN)],
            'catatan'   => 'nullable|string|max:1000',
        ]);
        $a = Anak::findByHashIdOrFail($data['a']);
        $b = Anak::findByHashIdOrFail($data['b']);

        try {
            $t = $this->tautan->putuskan($a->id, $b->id, $rt, $request->user(), $data['keputusan'], $data['catatan'] ?? null);
        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['id' => $t->id, 'keputusan' => $t->keputusan, 'status' => $t->status]);
    }
```

Rute (grup `rt.`):

```php
    Route::get('api/kandidat', [App\Http\Controllers\Rt\VerifikasiRtController::class, 'kandidat'])->name('api.kandidat');
    Route::post('api/tautan',  [App\Http\Controllers\Rt\VerifikasiRtController::class, 'putuskan'])->name('api.putuskan');
```

- [ ] **Step 4: Tab 3 di blade**

Di `resources/views/rt/verifikasi.blade.php`:

1. CSS — tambahkan setelah `.rt-toast{…}`:

```css
.pair{ border:1px solid var(--line); border-radius:12px; background:var(--card); margin-bottom:12px; overflow:hidden; }
.pair__head{ display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; padding:10px 14px; background:oklch(0.96 0.016 145); font-size:.8rem; color:var(--muted); }
.pair__head b{ color:var(--ink); }
.pair table{ width:100%; border-collapse:collapse; font-size:.85rem; }
.pair th{ text-align:left; width:140px; padding:.45rem .8rem; font-size:.68rem; text-transform:uppercase; letter-spacing:.05em; color:var(--muted); border-top:1px solid var(--line); }
.pair td{ padding:.45rem .8rem; border-top:1px solid var(--line); width:calc((100% - 140px)/2); }
.pair tr.beda td{ background:var(--amber-soft); font-weight:700; }
.pair__foot{ display:flex; gap:8px; padding:10px 14px; justify-content:flex-end; }
.pair__foot button{ font:inherit; font-weight:800; padding:8px 16px; border-radius:8px; border:1px solid var(--line); background:var(--card); cursor:pointer; }
.pair__foot button[data-keputusan="sama"]{ background:var(--green); border-color:var(--green); color:#fff; }
```

2. Tab ketiga di `<nav class="rt-tabs">`:

```blade
    <button class="rt-tab" data-tab="kandidat">Kemungkinan sama <span class="n" id="n-kandidat">–</span></button>
```

3. Petunjuk tab 3 setelah `#hint-tanpa`:

```blade
  <p class="rt-hint" id="hint-kandidat" style="display:none">Dua baris data yang mungkin adalah <b>anak yang sama</b> (mis. dari Operasi Timbang dan Capil). Bandingkan lalu putuskan <b>Sama</b> atau <b>Beda</b>. Kolom berlatar kuning = isinya berbeda.</p>
```

4. Template tombol setelah `#tpl-aksi-tanpa`:

```blade
<template id="tpl-aksi-pair">
  <div class="pair__foot">
    <button data-keputusan="beda">Beda orang</button>
    <button data-keputusan="sama">Sama — satu anak</button>
  </div>
</template>
```

5. JS — konstanta & data:

```javascript
var API_KANDIDAT = '{{ route("rt.api.kandidat", request()->only("rt")) }}';
var API_PUTUSKAN = '{{ route("rt.api.putuskan", request()->only("rt")) }}';
var FIELD_LABEL = { sumber_label:'Sumber', nik:'NIK', nama:'Nama', jk:'JK', tgl_lahir:'Tgl lahir', nama_ibu:'Ibu', nama_ayah:'Ayah', no_kk:'No KK', alamat:'Alamat domisili', alamat_ktp:'Alamat KTP', posyandu:'Posyandu' };
```

ubah `var data = { warga:[], tanpa:[] };` → `var data = { warga:[], tanpa:[], kandidat:[] };`

6. JS — render pasangan (tambahkan fungsi baru, dan di awal `render()` cabangkan):

```javascript
function renderKandidat(){
  var rows = data.kandidat;
  var q = document.getElementById('cari').value.trim().toLowerCase();
  if(q){ rows = rows.filter(function(p){ return [p.a.nama,p.a.nik,p.b.nama,p.b.nik,p.a.nama_ibu,p.b.nama_ibu].join(' ').toLowerCase().indexOf(q) >= 0; }); }
  document.getElementById('n-kandidat').textContent = data.kandidat.length;
  if(!rows.length){ document.getElementById('tabel').innerHTML = '<div class="rt-empty">Tidak ada pasangan yang perlu diputuskan</div>'; return; }
  var h = '';
  rows.forEach(function(p){
    h += '<div class="pair" data-a="'+esc(p.a.id)+'" data-b="'+esc(p.b.id)+'"><div class="pair__head"><span>Alasan: <b>'+esc(p.via_label)+'</b></span><span>'+p.beda.length+' kolom berbeda</span></div><table>';
    Object.keys(FIELD_LABEL).forEach(function(f){
      var beda = p.beda.indexOf(f) >= 0;
      var va = f === 'sumber_label' ? badgeSumber(p.a) : esc(p.a[f] || '-');
      var vb = f === 'sumber_label' ? badgeSumber(p.b) : esc(p.b[f] || '-');
      h += '<tr class="'+(beda ? 'beda' : '')+'"><th>'+FIELD_LABEL[f]+'</th><td>'+va+'</td><td>'+vb+'</td></tr>';
    });
    var foot = document.getElementById('tpl-aksi-pair').content.cloneNode(true);
    var box = document.createElement('div'); box.appendChild(foot);
    h += '</table>'+box.innerHTML+'</div>';
  });
  document.getElementById('tabel').innerHTML = h;
}
```

Di `render()` baris pertama: `if(tab === 'kandidat'){ renderKandidat(); return; }`. Di `muat()` tambahkan fetch ketiga `API_KANDIDAT` → `data.kandidat = res[2].rows || []`. Di handler klik tab, tambahkan `document.getElementById('hint-kandidat').style.display = tab === 'kandidat' ? '' : 'none';` dan sembunyikan `#f-status` saat tab kandidat (`document.getElementById('f-status').style.display = tab === 'kandidat' ? 'none' : ''`).

7. JS — keputusan:

```javascript
function putuskan(card, keputusan, btn){
  var catatan = window.prompt(keputusan === 'sama' ? 'Catatan (opsional), mis. "NIK lama salah ketik":' : 'Catatan (opsional), mis. "kembar / kakak-adik":', '');
  if(catatan === null) return;
  btn.disabled = true;
  fetch(API_PUTUSKAN, {
    method:'POST', headers:{ 'X-CSRF-TOKEN':CSRF, 'Content-Type':'application/json', Accept:'application/json' },
    body: JSON.stringify({ a: card.getAttribute('data-a'), b: card.getAttribute('data-b'), keputusan: keputusan, catatan: catatan })
  })
  .then(function(r){ return r.json().then(function(j){ if(!r.ok) throw new Error(j.message || 'HTTP '+r.status); return j; }); })
  .then(function(){
    data.kandidat = data.kandidat.filter(function(p){ return !(p.a.id === card.getAttribute('data-a') && p.b.id === card.getAttribute('data-b')); });
    render(); toast(keputusan === 'sama' ? 'Ditandai satu anak — menunggu reviu puskesmas' : 'Ditandai beda orang — menunggu reviu puskesmas');
  })
  .catch(function(e){ btn.disabled = false; toast('Gagal: '+e.message); });
}
```

Di listener klik `#tabel` tambahkan sebelum cabang `button[data-status]`:

```javascript
  var kb = e.target.closest('button[data-keputusan]');
  if(kb){ putuskan(kb.closest('.pair'), kb.getAttribute('data-keputusan'), kb); return; }
```

- [ ] **Step 5: Jalankan tes, pastikan lulus**

Run: `/d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe artisan view:clear && /d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe vendor/bin/phpunit tests/Feature/VerifikasiRt/EndpointTautanRtTest.php tests/Feature/VerifikasiRt/EndpointRtTest.php tests/Feature/VerifikasiRt/HalamanRtTest.php`
Expected: semua lulus.

- [ ] **Step 6: Cek visual** — seperti T1 Task 6: seed 2 anak mirip (OT + Capil) di RT demo, jalankan `identitas:pindai`, login RT, buka tab "Kemungkinan sama", klik **Sama** (tangani `prompt` — pakai `javascript_tool` untuk `window.prompt = () => 'demo'` sebelum klik), pastikan kartu hilang & `anak_tautan` terisi. Bersihkan demo.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Rt/VerifikasiRtController.php routes/web.php resources/views/rt/verifikasi.blade.php tests/Feature/VerifikasiRt/EndpointTautanRtTest.php
git commit -m "feat(verifikasi-rt): tab Kemungkinan sama — RT memutuskan sama/beda"
```

---

### Task 6: Reviu tautan + tombol "Pindai ulang"

**Files:**
- Modify: `app/Http/Controllers/VerifikasiRtReviuController.php` (+`tinjauTautan`, `pindai`; `index` memuat kedua antrean)
- Modify: `resources/views/admin/verifikasi-rt/index.blade.php` (tab)
- Modify: `routes/web.php`
- Test: `tests/Feature/VerifikasiRt/ReviuTautanTest.php`

**Interfaces:**
- Routes: `admin.verifikasiRt.tinjauTautan` POST `/admin/verifikasi-rt/tautan/{tautan}/tinjau` `{setuju, catatan?}`; `admin.verifikasiRt.pindai` POST `/admin/verifikasi-rt/pindai` (superadmin saja, dispatch `PindaiIdentitasJob`).
- `index` menerima `?tab=domisili|tautan` (default `domisili`) dan mengirim ke view: `antrean` (domisili, paginate), `antreanTautan` (paginate 30), `tab`, `ringkasanKandidat`, `menungguGabung`.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php
// tests/Feature/VerifikasiRt/ReviuTautanTest.php

namespace Tests\Feature\VerifikasiRt;

use App\Jobs\PindaiIdentitasJob;
use App\Models\Anak;
use App\Models\AnakKandidat;
use App\Models\AnakTautan;
use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\User;
use App\Services\TautanIdentitasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReviuTautanTest extends TestCase
{
    use RefreshDatabase;

    private Rt $rt;
    private User $userRt;
    private TautanIdentitasService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc    = app(TautanIdentitasService::class);
        $this->rt     = Rt::factory()->create();
        $this->userRt = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => $this->rt->id, 'id_kel' => $this->rt->id_kelurahan]);
    }

    private function anak(string $nik, array $o = []): Anak
    {
        return Anak::create(array_merge([
            'nama' => 'Anak '.$nik, 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'capil',
            'id_kel' => $this->rt->id_kelurahan, 'id_rt' => $this->rt->id,
        ], $o));
    }

    private function tautan(string $nikA, string $nikB, string $keputusan = 'sama'): AnakTautan
    {
        $a = $this->anak($nikA);
        $b = $this->anak($nikB, ['id_rt' => null]);
        [$x, $y] = AnakTautan::urut($a->id, $b->id);
        AnakKandidat::create(['id_anak_a' => $x, 'id_anak_b' => $y, 'skor' => 190, 'via' => 'ortu',
            'child_sim' => 100, 'parent_sim' => 90, 'dipindai_at' => now()]);
        return $this->svc->putuskan($a->id, $b->id, $this->rt, $this->userRt, $keputusan);
    }

    public function test_tab_tautan_menampilkan_pasangan_kelurahan_peninjau(): void
    {
        $t = $this->tautan('3201000000015001', '3201000000015002');
        $faskes = User::factory()->create(['type' => 1, 'id_kel' => $this->rt->id_kelurahan]);

        $this->actingAs($faskes)->get(route('admin.verifikasiRt.index', ['tab' => 'tautan']))
            ->assertOk()
            ->assertSee('Anak 3201000000015001')
            ->assertSee('Anak 3201000000015002')
            ->assertSee(route('admin.verifikasiRt.tinjauTautan', $t));
    }

    public function test_setujui_tautan_sama_menambah_menunggu_gabung(): void
    {
        $t = $this->tautan('3201000000015003', '3201000000015004');
        $super = User::factory()->create(['type' => 0]);

        $this->actingAs($super)->from(route('admin.verifikasiRt.index', ['tab' => 'tautan']))
            ->post(route('admin.verifikasiRt.tinjauTautan', $t), ['setuju' => 1])
            ->assertRedirect(route('admin.verifikasiRt.index', ['tab' => 'tautan']))
            ->assertSessionHas('success');

        $this->assertSame('disetujui', $t->fresh()->status);
        $this->actingAs($super)->get(route('admin.verifikasiRt.index', ['tab' => 'tautan']))
            ->assertSee('1 tautan menunggu penggabungan');
    }

    public function test_tolak_tautan_membuka_kembali_kandidat(): void
    {
        $t = $this->tautan('3201000000015005', '3201000000015006', 'beda');
        $super = User::factory()->create(['type' => 0]);

        $this->actingAs($super)->post(route('admin.verifikasiRt.tinjauTautan', $t), ['setuju' => 0, 'catatan' => 'cek KK']);

        $this->assertSame('ditolak', $t->fresh()->status);
        $this->assertCount(1, $this->svc->kandidatUntukRt($this->rt));
    }

    public function test_faskes_kelurahan_lain_403(): void
    {
        $t = $this->tautan('3201000000015007', '3201000000015008');
        $lain = User::factory()->create(['type' => 1, 'id_kel' => Kelurahan::factory()->create()->id]);

        $this->actingAs($lain)->post(route('admin.verifikasiRt.tinjauTautan', $t), ['setuju' => 1])->assertForbidden();
    }

    public function test_pindai_ulang_hanya_superadmin_dan_mengantrekan_job(): void
    {
        Queue::fake();
        $faskes = User::factory()->create(['type' => 1, 'id_kel' => $this->rt->id_kelurahan]);
        $this->actingAs($faskes)->post(route('admin.verifikasiRt.pindai'))->assertForbidden();

        $super = User::factory()->create(['type' => 0]);
        $this->actingAs($super)->from(route('admin.verifikasiRt.index'))
            ->post(route('admin.verifikasiRt.pindai'))
            ->assertRedirect(route('admin.verifikasiRt.index', ['tab' => 'tautan']))
            ->assertSessionHas('success');

        Queue::assertPushed(PindaiIdentitasJob::class, 1);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `/d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe vendor/bin/phpunit tests/Feature/VerifikasiRt/ReviuTautanTest.php`
Expected: FAIL — `Route [admin.verifikasiRt.tinjauTautan] not defined.`

- [ ] **Step 3: Controller & rute**

`VerifikasiRtReviuController`:
- Konstruktor: `public function __construct(private readonly VerifikasiRtService $svc, private readonly TautanIdentitasService $tautan) {}` (+`use App\Services\TautanIdentitasService; use App\Models\AnakTautan; use App\Jobs\PindaiIdentitasJob;`).
- Di `index()`, sebelum `return view(...)` tambahkan:

```php
        $tab = $request->query('tab') === 'tautan' ? 'tautan' : 'domisili';
        $antreanTautan = $this->tautan->antreanTautanQuery($user)->paginate(30, ['*'], 'halaman_tautan')->withQueryString();
```

dan lengkapi array view dengan `'tab' => $tab, 'antreanTautan' => $antreanTautan, 'ringkasanKandidat' => $this->tautan->ringkasanKandidat(), 'menungguGabung' => $this->tautan->menungguGabung(),`.
- Metode baru:

```php
    public function tinjauTautan(Request $request, AnakTautan $tautan): RedirectResponse
    {
        $data = $request->validate([
            'setuju'  => 'required|boolean',
            'catatan' => 'nullable|string|max:1000',
        ]);

        try {
            $this->tautan->tinjauTautan($tautan, $request->user(), (bool) $data['setuju'], $data['catatan'] ?? null);
        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.verifikasiRt.index', ['tab' => 'tautan'] + $request->only('rt', 'halaman_tautan'))
            ->with('success', $data['setuju'] ? 'Tautan disetujui.' : 'Tautan ditolak.');
    }

    /** Pindai ulang kandidat (antrean) — hanya Dinkes. */
    public function pindai(Request $request): RedirectResponse
    {
        abort_if(!$request->user()->isSuperAdmin(), 403);
        PindaiIdentitasJob::dispatch();

        return redirect()->route('admin.verifikasiRt.index', ['tab' => 'tautan'])
            ->with('success', 'Pindai ulang dijadwalkan. Hasil muncul setelah worker antrean memprosesnya.');
    }
```

Rute (grup admin, setelah `verifikasi-rt/{verifikasi}/tinjau`):

```php
    Route::post('verifikasi-rt/tautan/{tautan}/tinjau', [App\Http\Controllers\VerifikasiRtReviuController::class, 'tinjauTautan'])->name('admin.verifikasiRt.tinjauTautan');
    Route::post('verifikasi-rt/pindai', [App\Http\Controllers\VerifikasiRtReviuController::class, 'pindai'])->name('admin.verifikasiRt.pindai');
```

- [ ] **Step 4: View — tab**

Di `resources/views/admin/verifikasi-rt/index.blade.php`, ganti blok filter + tabel dengan struktur tab: setelah alert, sisipkan navigasi:

```blade
<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link {{ $tab === 'domisili' ? 'active' : '' }}" href="{{ route('admin.verifikasiRt.index') }}">Status domisili <span class="badge badge-light">{{ $antrean->total() }}</span></a></li>
    <li class="nav-item"><a class="nav-link {{ $tab === 'tautan' ? 'active' : '' }}" href="{{ route('admin.verifikasiRt.index', ['tab' => 'tautan']) }}">Tautan identitas <span class="badge badge-light">{{ $antreanTautan->total() }}</span></a></li>
</ul>
```

Bungkus blok filter+tabel yang ada dengan `@if($tab === 'domisili') … @else … @endif`; isi cabang `@else`:

```blade
<div class="card-box mb-3">
    <div class="d-flex flex-wrap align-items-center">
        <span class="text-muted mr-3">Kandidat hasil pindai: <b>{{ number_format($ringkasanKandidat['jumlah']) }}</b>
            @if($ringkasanKandidat['dipindai_at']) · dipindai {{ \Carbon\Carbon::parse($ringkasanKandidat['dipindai_at'])->format('d/m/Y H:i') }} @endif</span>
        <span class="text-muted mr-3"><b>{{ $menungguGabung }}</b> tautan menunggu penggabungan</span>
        @if(auth()->user()->isSuperAdmin())
        <form method="POST" action="{{ route('admin.verifikasiRt.pindai') }}" class="ml-auto">@csrf
            <button class="btn btn-outline-primary btn-sm"><i class="fa fa-refresh mr-1"></i> Pindai ulang</button>
        </form>
        @endif
    </div>
</div>

<div class="card-box">
    @forelse($antreanTautan as $t)
    <div class="border rounded mb-3">
        <div class="px-3 py-2 bg-light d-flex justify-content-between flex-wrap">
            <span><b>{{ $t->keputusan === 'sama' ? 'SAMA — satu anak' : 'BEDA orang' }}</b> · alasan pindai: {{ $t->via }} · skor {{ $t->skor }}</span>
            <small class="text-muted">{{ $t->pengusul?->name }} ({{ $t->pengusul?->rt?->name }}) · {{ $t->diusulkan_at?->format('d/m/Y H:i') }}{{ $t->catatan ? ' · "'.$t->catatan.'"' : '' }}</small>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th style="width:140px"></th><th>Baris A ({{ $t->anakA?->sumber }})</th><th>Baris B ({{ $t->anakB?->sumber }})</th></tr></thead>
                <tbody>
                @foreach(['nik' => 'NIK', 'nama' => 'Nama', 'tgl_lahir' => 'Tgl lahir', 'nama_ibu' => 'Ibu', 'nama_ayah' => 'Ayah', 'no_kk' => 'No KK', 'alamat' => 'Alamat', 'alamat_ktp' => 'Alamat KTP'] as $f => $label)
                    @php $beda = trim((string) $t->anakA?->$f) !== trim((string) $t->anakB?->$f); @endphp
                    <tr class="{{ $beda ? 'table-warning' : '' }}"><th>{{ $label }}</th><td>{{ $t->anakA?->$f ?: '-' }}</td><td>{{ $t->anakB?->$f ?: '-' }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <form method="POST" action="{{ route('admin.verifikasiRt.tinjauTautan', $t) }}" class="form-inline px-3 py-2">
            @csrf
            <input type="text" name="catatan" class="form-control form-control-sm mr-2" placeholder="Catatan reviu" maxlength="1000" style="min-width:260px">
            <button class="btn btn-sm btn-success mr-1" name="setuju" value="1">Setujui</button>
            <button class="btn btn-sm btn-outline-danger" name="setuju" value="0">Tolak</button>
        </form>
    </div>
    @empty
    <div class="text-center text-muted py-4">Tidak ada tautan yang menunggu.</div>
    @endforelse
    {{ $antreanTautan->onEachSide(1)->links('pagination::bootstrap-4') }}
</div>
```

- [ ] **Step 5: Jalankan tes, pastikan lulus**

Run: `/d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe artisan view:clear && /d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe vendor/bin/phpunit tests/Feature/VerifikasiRt/ReviuTautanTest.php tests/Feature/VerifikasiRt/ReviuVerifikasiRtTest.php`
Expected: semua lulus.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/VerifikasiRtReviuController.php resources/views/admin/verifikasi-rt/index.blade.php routes/web.php tests/Feature/VerifikasiRt/ReviuTautanTest.php
git commit -m "feat(verifikasi-rt): reviu tautan identitas + pindai ulang"
```

---

### Task 7: Suite penuh & rapikan

- [ ] **Step 1:** `php artisan migrate` di DB dev, lalu `php artisan identitas:pindai` untuk memastikan command jalan di data nyata (laporkan jumlah pasangan; jangan tampilkan isi baris — data Capil rahasia).
- [ ] **Step 2:** Suite penuh: `/d/apps/laragon/bin/php/php-8.4.7-Win32-vs17-x64/php.exe vendor/bin/phpunit` — harus `OK`. Pastikan tidak ada `artisan serve` yatim (`Get-Process php`) sebelum menjalankan.
- [ ] **Step 3:** Perbarui memori `project_verifikasi_rt.md` (status T2, gotcha baru).
- [ ] **Step 4:** Tidak ada commit terpisah kecuali ada perbaikan.

---

## Self-Review

- **Spec coverage T2:** §3.1 `anak_tautan`/`anak_kandidat` (Task 2), §5 matcher & pindai & hook impor & tombol pindai ulang (Task 1, 3, 6), §4 tab 3 + berdampingan + Sama/Beda + hilang setelah diputus (Task 5), §6.1 tab Tautan + Setujui/Tolak (Task 6), §6.2 `beda` disetujui tak disarankan lagi & `sama` disetujui → "menunggu penggabungan" (Task 4, 6), §10 e (Task 1, 4). Merge fisik & `sumber_gabungan` = T3; Dasbor Gizi = T4.
- **Placeholder:** tidak ada.
- **Konsistensi nama:** `IdentitasMatcher::{evaluate,pindaiSemua}`, `TautanIdentitasService::{pindai,ringkasanKandidat,kandidatUntukRt,putuskan,tinjauTautan,antreanTautanQuery,menungguGabung}`, rute `rt.api.kandidat`, `rt.api.putuskan`, `admin.verifikasiRt.tinjauTautan`, `admin.verifikasiRt.pindai`; JSON `rows[].{a,b,skor,via,via_label,beda}` sama di controller, blade, tes.

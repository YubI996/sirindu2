# Data Kesmas Anak — Rencana Implementasi

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambah data Kesmas per anak (8 field), riwayat lahir & skrining neonatal (8 kolom baru + 7 kolom lama yang belum pernah tampil), dan layanan Kesmas per kunjungan (16 kolom) ke modul Data Anak, tampil di detail anak, dan bisa diunduh lewat Export Kesmas (Excel 2 sheet).

**Architecture:** Kolom baru ditambahkan langsung ke `anak` dan `data_anak` (tanpa tabel baru, tanpa DEFAULT — NULL = belum diisi). Satu `config/kesmas.php` jadi sumber opsi/label untuk form, detail, export. Tiga partial Blade (kartu collapse tertutup, tanpa `required`) di-include ke form yang sudah ada; `AnakRepository` menambah dua helper yang hanya menyentuh field yang dikirim form. Export = controller + `KesmasExport` (`WithMultipleSheets`) dengan dua sheet `FromQuery`.

**Tech Stack:** Laravel 12, PHP 8.4, MySQL 8, PHPUnit 11 (`RefreshDatabase`, DB `sirindu_testing`), Maatwebsite Excel 3.1 + PhpSpreadsheet, Bootstrap 4 + jQuery (layout `admin::layouts.app`).

**Spec:** `docs/superpowers/specs/2026-09-15-data-kesmas-design.md` — baca dulu; nomor § di bawah merujuk ke sana.

## Global Constraints

- **NULL = belum diisi.** Tidak ada `->default()` di kolom baru. Select tiga keadaan mengirim `''` → disimpan `null`.
- **Checkbox = hidden(`0`) + checkbox(`1`), dibaca `$request->boolean()`.** `$request->has()` hanya dipakai untuk mendeteksi *field dikirim atau tidak* (spec §3.4), bukan untuk membaca nilai.
- **Tidak boleh ada `required` di dalam ketiga partial** (kartu collapse tertutup → submit mati senyap). Juga tidak ada `min`/`max` HTML pada input angka di dalamnya.
- **Bootstrap 4:** `data-toggle`/`data-target`, bukan `data-bs-*`.
- **Blade:** `@section('x') isi @endsection` selalu dengan spasi; setelah mengubah blade jalankan `php artisan view:clear` sebelum menyimpulkan perubahan tak berefek.
- **Nama kolom & FK:** `id_anak` (bukan `anak_id`), tanpa awalan `is_`; tabel `data_anak` (bukan `pengukuran_anak`).
- **Kolom lama dipakai ulang:** `bb`, `mbg`, `kelas_ibu_balita`, `imd`, `usia_kehamilan_lahir`, `penolong_lahir`, `komplikasi_persalinan`, `bbl`, `pbl`, `lk_lahir`. `rujuk` (tinyint lama) tidak disentuh.
- **Tidak disentuh:** VIEW `alldata`, semua `App\Imports\*`, Export Anak lama, dasbor apa pun.
- **Tes:** payload meniru form asli (`'0'` untuk checkbox tak dicentang, `''` untuk select kosong). Jangan jalankan dua proses PHPUnit bersamaan (DB `sirindu_testing` dipakai bersama). MySQL Laragon harus sudah nyala.
- **Perintah:** `php` = `D:\apps\laragon\bin\php\php-8.4.7-Win32-vs17-x64\php.exe` bila tidak ada di PATH. Jalankan tes dengan `php artisan test <path>`.
- **Commit:** pesan Indonesia gaya `feat(kesmas): …`, diakhiri dua baris:
  `Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>` dan
  `Claude-Session: https://claude.ai/code/session_011UJEVC5xw87p91CXS1BnFv`.
- Tidak ada `TODO`/placeholder di kode yang di-commit.

---

## Peta berkas

| Berkas | Tanggung jawab | Task |
|---|---|---|
| `database/migrations/2026_09_22_000001_add_kesmas_fields_to_anak_and_data_anak.php` | 16 + 16 kolom nullable tanpa default | 1 |
| `config/kesmas.php` | Daftar opsi select, label layanan, badge, judul kolom export | 2 |
| `app/Services/KesmasPresenter.php` | Label Ya/Tidak/enum/teks & ringkasan layanan per kunjungan (detail + export) | 2 |
| `app/Http/Requests/Admin/Anak/KesmasRules.php` | Rule validasi `anak()` & `kunjungan()` + daftar kolom boolean | 3 |
| `app/Http/Requests/Admin/Anak/storeAnakRequest.php` | + `KesmasRules::anak()` | 3 |
| `app/Repositories/Admin/Anak/AnakRepository.php` | helper `kesmasAnakAttributes`, `layananKesmasAttributes`, `kolomKesmas` | 3, 5 |
| `app/Http/Controllers/AdminController.php` | validasi di `updateAnak`, `storeDataAnak`, `updateDataAnak`; `layanan` di `showAnak` | 3, 5, 7 |
| `resources/views/admin/anak/partials/form-kesmas.blade.php` | Kartu Data Kesmas & Lingkungan | 4 |
| `resources/views/admin/anak/partials/form-riwayat-lahir.blade.php` | Kartu Riwayat Kelahiran & Skrining Neonatal | 4 |
| `resources/views/admin/anak/partials/form-layanan-kesmas.blade.php` | Kartu Layanan Kesmas per kunjungan | 6 |
| `resources/views/admin/anak/{create,edit,data-anak}.blade.php` | include partial | 4, 6 |
| `resources/views/admin/anak/show.blade.php` | 2 kartu baru + kolom Layanan Kesmas | 7 |
| `app/Exports/{KesmasExport,KesmasAnakSheet,KesmasKunjunganSheet}.php` | Export 2 sheet | 8 |
| `app/Http/Controllers/ExportKesmasController.php`, `resources/views/admin/export/kesmas.blade.php`, `routes/web.php`, `resources/views/vendor/admin/layouts/partials/leftsidebar.blade.php` | Halaman & rute export, menu | 8 |
| `tests/Feature/Kesmas/*.php`, `tests/Unit/KesmasPresenterTest.php` | Pengujian | 1–8 |

---

### Task 1: Migrasi kolom Kesmas

**Files:**
- Create: `database/migrations/2026_09_22_000001_add_kesmas_fields_to_anak_and_data_anak.php`
- Test: `tests/Feature/Kesmas/MigrasiKesmasTest.php`

**Interfaces:**
- Produces: kolom `anak.{no_id_epus,fktp_bpjs,air_bersih,jamban_sehat,merokok_keluarga,status_tk_paud,penyakit_penyerta,pjb,riwayat_kek_ibu,tempat_bersalin,jenis_persalinan,skrining_shk,skrining_shak,skrining_g6pd,pemeriksaan_hepatitis_b,komplikasi_neonatal}` dan `data_anak.{tgl_penanda_ckg,mtbm,mtbs,skrining_atresia_bilier,kn1,kn3,pkat,oralit_zinc,pemeriksaan_gigi,rujukan,mt_pangan_lokal,catatan_pengukuran,pemeriksaan_lainnya,pola_makan,pola_asuh,intervensi}` — semua nullable, tanpa default.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php
// tests/Feature/Kesmas/MigrasiKesmasTest.php

namespace Tests\Feature\Kesmas;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Mengunci keputusan spec §1.3: kolom Kesmas nullable TANPA default —
 * NULL berarti "belum diisi", bukan "Ya"/"Tidak"/"Belum" hasil tebakan.
 */
class MigrasiKesmasTest extends TestCase
{
    use RefreshDatabase;

    private const ANAK = [
        'no_id_epus', 'fktp_bpjs', 'air_bersih', 'jamban_sehat', 'merokok_keluarga', 'status_tk_paud',
        'penyakit_penyerta', 'pjb', 'riwayat_kek_ibu', 'tempat_bersalin', 'jenis_persalinan',
        'skrining_shk', 'skrining_shak', 'skrining_g6pd', 'pemeriksaan_hepatitis_b', 'komplikasi_neonatal',
    ];

    private const DATA_ANAK = [
        'tgl_penanda_ckg', 'mtbm', 'mtbs', 'skrining_atresia_bilier', 'kn1', 'kn3', 'pkat', 'oralit_zinc',
        'pemeriksaan_gigi', 'rujukan', 'mt_pangan_lokal', 'catatan_pengukuran', 'pemeriksaan_lainnya',
        'pola_makan', 'pola_asuh', 'intervensi',
    ];

    public function test_kolom_kesmas_ada_nullable_dan_tanpa_default(): void
    {
        foreach (['anak' => self::ANAK, 'data_anak' => self::DATA_ANAK] as $tabel => $daftar) {
            $kolom = collect(DB::select(
                'SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_TYPE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$tabel]
            ))->keyBy('COLUMN_NAME');

            foreach ($daftar as $nama) {
                $this->assertTrue($kolom->has($nama), "$tabel.$nama tidak ada");
                $this->assertSame('YES', $kolom[$nama]->IS_NULLABLE, "$tabel.$nama harus nullable");
                $this->assertNull($kolom[$nama]->COLUMN_DEFAULT, "$tabel.$nama tidak boleh punya DEFAULT");
            }
        }
    }

    public function test_boolean_bertipe_tinyint_dan_skrining_bertipe_enum(): void
    {
        $kolom = collect(DB::select(
            'SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            ['anak']
        ))->keyBy('COLUMN_NAME');

        $this->assertSame('tinyint(1)', $kolom['air_bersih']->COLUMN_TYPE);
        $this->assertSame("enum('normal','tidak_normal','belum')", $kolom['skrining_shk']->COLUMN_TYPE);
        $this->assertSame("enum('reaktif','non_reaktif','belum')", $kolom['pemeriksaan_hepatitis_b']->COLUMN_TYPE);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test tests/Feature/Kesmas/MigrasiKesmasTest.php`
Expected: FAIL — `anak.no_id_epus tidak ada`

- [ ] **Step 3: Tulis migrasi**

```php
<?php
// database/migrations/2026_09_22_000001_add_kesmas_fields_to_anak_and_data_anak.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data Kesmas anak — spec docs/superpowers/specs/2026-09-15-data-kesmas-design.md §2.
 * Semua kolom nullable TANPA default: NULL = belum diisi. DDL klien memberi
 * default YA/TIDAK/BELUM; kalau diterapkan, ±15.000 anak lama langsung tercatat
 * "punya air bersih / tidak PJB" tanpa pernah ditanya.
 *
 * Kolom riwayat lahir yang SUDAH ada dan dipakai ulang (jangan dibuat lagi):
 * imd, usia_kehamilan_lahir, penolong_lahir, komplikasi_persalinan, bbl, pbl, lk_lahir.
 */
return new class extends Migration
{
    private const SKRINING = ['normal', 'tidak_normal', 'belum'];

    private const ANAK = [
        'no_id_epus', 'fktp_bpjs', 'air_bersih', 'jamban_sehat', 'merokok_keluarga', 'status_tk_paud',
        'penyakit_penyerta', 'pjb', 'riwayat_kek_ibu', 'tempat_bersalin', 'jenis_persalinan',
        'skrining_shk', 'skrining_shak', 'skrining_g6pd', 'pemeriksaan_hepatitis_b', 'komplikasi_neonatal',
    ];

    private const DATA_ANAK = [
        'tgl_penanda_ckg', 'mtbm', 'mtbs', 'skrining_atresia_bilier', 'kn1', 'kn3', 'pkat', 'oralit_zinc',
        'pemeriksaan_gigi', 'rujukan', 'mt_pangan_lokal', 'catatan_pengukuran', 'pemeriksaan_lainnya',
        'pola_makan', 'pola_asuh', 'intervensi',
    ];

    public function up(): void
    {
        Schema::table('anak', function (Blueprint $t) {
            // Kesmas & lingkungan
            $t->string('no_id_epus', 50)->nullable();
            $t->string('fktp_bpjs', 100)->nullable();
            $t->boolean('air_bersih')->nullable();
            $t->boolean('jamban_sehat')->nullable();
            $t->boolean('merokok_keluarga')->nullable();
            $t->string('status_tk_paud', 100)->nullable();
            $t->string('penyakit_penyerta', 255)->nullable();
            $t->string('pjb', 100)->nullable();
            // Riwayat lahir & skrining neonatal
            $t->boolean('riwayat_kek_ibu')->nullable();
            $t->string('tempat_bersalin', 150)->nullable();
            $t->string('jenis_persalinan', 50)->nullable();
            $t->enum('skrining_shk', self::SKRINING)->nullable();
            $t->enum('skrining_shak', self::SKRINING)->nullable();
            $t->enum('skrining_g6pd', self::SKRINING)->nullable();
            $t->enum('pemeriksaan_hepatitis_b', ['reaktif', 'non_reaktif', 'belum'])->nullable();
            $t->text('komplikasi_neonatal')->nullable();
        });

        Schema::table('data_anak', function (Blueprint $t) {
            $t->date('tgl_penanda_ckg')->nullable();
            foreach (['mtbm', 'mtbs', 'skrining_atresia_bilier', 'kn1', 'kn3', 'pkat', 'oralit_zinc'] as $k) {
                $t->boolean($k)->nullable();
            }
            $t->string('pemeriksaan_gigi', 150)->nullable();
            $t->string('rujukan', 150)->nullable();
            $t->string('mt_pangan_lokal', 100)->nullable();
            foreach (['catatan_pengukuran', 'pemeriksaan_lainnya', 'pola_makan', 'pola_asuh', 'intervensi'] as $k) {
                $t->text($k)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('anak', fn (Blueprint $t) => $t->dropColumn(self::ANAK));
        Schema::table('data_anak', fn (Blueprint $t) => $t->dropColumn(self::DATA_ANAK));
    }
};
```

- [ ] **Step 4: Jalankan tes, pastikan lolos**

Run: `php artisan test tests/Feature/Kesmas/MigrasiKesmasTest.php`
Expected: PASS (2 tests)

- [ ] **Step 5: Migrasikan DB dev & commit**

```bash
php artisan migrate
git add database/migrations/2026_09_22_000001_add_kesmas_fields_to_anak_and_data_anak.php tests/Feature/Kesmas/MigrasiKesmasTest.php
git commit -m "$(cat <<'EOF'
feat(kesmas): kolom data Kesmas & riwayat lahir di anak dan layanan per kunjungan di data_anak

Semua nullable tanpa default — NULL = belum diisi (spec §1.3).

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_011UJEVC5xw87p91CXS1BnFv
EOF
)"
```

---

### Task 2: `config/kesmas.php` + `KesmasPresenter`

**Files:**
- Create: `config/kesmas.php`
- Create: `app/Services/KesmasPresenter.php`
- Test: `tests/Unit/KesmasPresenterTest.php`

**Interfaces:**
- Produces: `config('kesmas.status_tk_paud'|'jenis_persalinan'|'penolong_lahir'|'pemeriksaan_gigi'|'rujukan')` → `string[]`; `config('kesmas.skrining'|'hepatitis_b')` → `array<kode,label>`; `config('kesmas.layanan')` → `array<kolom, {label,badge,kolom}>`; `config('kesmas.keterangan_kunjungan')` → `array<kolom,label>`.
- Produces: `KesmasPresenter::yaTidak($nilai, string $kosong = ''): string`, `::enumLabel(string $grup, ?string $kode, string $kosong = ''): string`, `::teks($nilai, string $kosong = '—'): string`, `::layananKunjungan(object $baris): array{badge: string[], keterangan: string[]}`.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php
// tests/Unit/KesmasPresenterTest.php

namespace Tests\Unit;

use App\Services\KesmasPresenter;
use Tests\TestCase;

class KesmasPresenterTest extends TestCase
{
    public function test_ya_tidak_membedakan_belum_diisi_dari_tidak(): void
    {
        $this->assertSame('', KesmasPresenter::yaTidak(null));
        $this->assertSame('—', KesmasPresenter::yaTidak('', '—'));
        $this->assertSame('Ya', KesmasPresenter::yaTidak(1));
        $this->assertSame('Ya', KesmasPresenter::yaTidak('1'));
        $this->assertSame('Tidak', KesmasPresenter::yaTidak(0));
        $this->assertSame('Tidak', KesmasPresenter::yaTidak('0'));
    }

    public function test_enum_label_dari_config(): void
    {
        $this->assertSame('Tidak normal', KesmasPresenter::enumLabel('skrining', 'tidak_normal'));
        $this->assertSame('Non reaktif', KesmasPresenter::enumLabel('hepatitis_b', 'non_reaktif'));
        $this->assertSame('', KesmasPresenter::enumLabel('skrining', null));
        $this->assertSame('—', KesmasPresenter::enumLabel('skrining', '', '—'));
        $this->assertSame('xyz', KesmasPresenter::enumLabel('skrining', 'xyz'));
    }

    public function test_teks_kosong_jadi_strip(): void
    {
        $this->assertSame('—', KesmasPresenter::teks(null));
        $this->assertSame('—', KesmasPresenter::teks('   '));
        $this->assertSame('Bidan', KesmasPresenter::teks('Bidan'));
    }

    public function test_layanan_kunjungan_hanya_yang_bernilai_satu(): void
    {
        $baris = (object) [
            'kn1' => 1, 'kn3' => 0, 'mtbm' => null, 'mtbs' => '1', 'pkat' => 0,
            'skrining_atresia_bilier' => 0, 'oralit_zinc' => 0, 'mbg' => 1, 'kelas_ibu_balita' => 0,
            'tgl_penanda_ckg' => '2026-03-05',
            'pemeriksaan_gigi' => 'Karies', 'rujukan' => null, 'mt_pangan_lokal' => '  ',
            'catatan_pengukuran' => null, 'pemeriksaan_lainnya' => null, 'pola_makan' => null,
            'pola_asuh' => null, 'intervensi' => 'PMT 30 hari',
        ];

        $r = KesmasPresenter::layananKunjungan($baris);

        $this->assertSame(['KN1', 'MTBS', 'MBG', 'CKG 05/03'], $r['badge']);
        $this->assertSame(['Gigi: Karies', 'Intervensi: PMT 30 hari'], $r['keterangan']);
    }

    public function test_layanan_kunjungan_kosong_bila_tak_ada_apa_pun(): void
    {
        $r = KesmasPresenter::layananKunjungan((object) []);

        $this->assertSame([], $r['badge']);
        $this->assertSame([], $r['keterangan']);
    }

    public function test_config_layanan_lengkap_dan_berurutan(): void
    {
        $this->assertSame(
            ['kn1', 'kn3', 'mtbm', 'mtbs', 'pkat', 'skrining_atresia_bilier', 'oralit_zinc', 'mbg', 'kelas_ibu_balita'],
            array_keys(config('kesmas.layanan'))
        );
        foreach (config('kesmas.layanan') as $kolom => $def) {
            $this->assertArrayHasKey('label', $def, $kolom);
            $this->assertArrayHasKey('badge', $def, $kolom);
            $this->assertArrayHasKey('kolom', $def, $kolom);
        }
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test tests/Unit/KesmasPresenterTest.php`
Expected: FAIL — `Class "App\Services\KesmasPresenter" not found`

- [ ] **Step 3: Tulis config**

```php
<?php
// config/kesmas.php

/*
 * Daftar opsi & label data Kesmas anak — SATU sumber untuk form (opsi select),
 * halaman detail (label/badge), Export Kesmas (judul kolom), dan dasbor Kesmas nanti.
 * Spec: docs/superpowers/specs/2026-09-15-data-kesmas-design.md §2.3.
 *
 * Nilai select disimpan apa adanya (teks label) untuk daftar tanpa kunci;
 * untuk `skrining` dan `hepatitis_b` yang disimpan adalah KUNCI (enum di DB).
 */
return [
    'status_tk_paud'   => ['Tidak ikut', 'PAUD/KB', 'TK A', 'TK B'],
    'jenis_persalinan' => ['Spontan', 'SC', 'Vakum', 'Forsep', 'Lainnya'],
    'penolong_lahir'   => ['Dokter Spesialis', 'Dokter Umum', 'Bidan', 'Lainnya'],
    'skrining'         => ['belum' => 'Belum', 'normal' => 'Normal', 'tidak_normal' => 'Tidak normal'],
    'hepatitis_b'      => ['belum' => 'Belum', 'non_reaktif' => 'Non reaktif', 'reaktif' => 'Reaktif'],
    'pemeriksaan_gigi' => ['Sehat', 'Karies', 'Masalah lain'],
    'rujukan'          => ['Tidak dirujuk', 'Dokter gigi', 'Dokter spesialis anak', 'Rumah sakit', 'Lainnya'],

    // Checkbox layanan per kunjungan (kolom data_anak): label form, singkatan badge
    // di detail anak, dan judul kolom di export. Urutan = urutan tampil.
    'layanan' => [
        'kn1'  => ['label' => 'KN1 — Kunjungan Neonatal 1 (6–48 jam)',            'badge' => 'KN1',  'kolom' => 'KN1'],
        'kn3'  => ['label' => 'KN3 — Kunjungan Neonatal 3 (hari ke-8 s/d 28)',    'badge' => 'KN3',  'kolom' => 'KN3'],
        'mtbm' => ['label' => 'MTBM — Manajemen Terpadu Bayi Muda',               'badge' => 'MTBM', 'kolom' => 'MTBM'],
        'mtbs' => ['label' => 'MTBS — Manajemen Terpadu Balita Sakit',            'badge' => 'MTBS', 'kolom' => 'MTBS'],
        'pkat' => ['label' => 'PKAT — Pelayanan Kesehatan Anak Terpadu (6 bulan)', 'badge' => 'PKAT', 'kolom' => 'PKAT'],
        'skrining_atresia_bilier' => ['label' => 'Skrining atresia bilier (kartu warna tinja)', 'badge' => 'AB', 'kolom' => 'Atresia Bilier'],
        'oralit_zinc'      => ['label' => 'Oralit & zinc sesuai standar', 'badge' => 'O+Z', 'kolom' => 'Oralit+Zinc'],
        'mbg'              => ['label' => 'Makan Bergizi Gratis (MBG)',   'badge' => 'MBG', 'kolom' => 'MBG'],
        'kelas_ibu_balita' => ['label' => 'Ikut Kelas Ibu Balita',        'badge' => 'KIB', 'kolom' => 'Kelas Ibu Balita'],
    ],

    // Field teks per kunjungan yang ditampilkan sebagai keterangan di detail anak.
    'keterangan_kunjungan' => [
        'pemeriksaan_gigi'    => 'Gigi',
        'rujukan'             => 'Rujukan',
        'mt_pangan_lokal'     => 'MT pangan lokal',
        'catatan_pengukuran'  => 'Catatan',
        'pemeriksaan_lainnya' => 'Pemeriksaan lain',
        'pola_makan'          => 'Pola makan',
        'pola_asuh'           => 'Pola asuh',
        'intervensi'          => 'Intervensi',
    ],
];
```

- [ ] **Step 4: Tulis presenter**

```php
<?php
// app/Services/KesmasPresenter.php

namespace App\Services;

use Carbon\Carbon;

/**
 * Pembentuk label data Kesmas untuk detail anak & Export Kesmas — supaya
 * "Ya / Tidak / belum diisi" dan label enum ditulis satu kali (spec §2.3, §4, §5).
 * Semua method statis dan tanpa efek samping.
 */
final class KesmasPresenter
{
    /** null/'' → $kosong; 1 → "Ya"; selain itu → "Tidak". */
    public static function yaTidak($nilai, string $kosong = ''): string
    {
        if ($nilai === null || $nilai === '') {
            return $kosong;
        }

        return ((int) $nilai) === 1 ? 'Ya' : 'Tidak';
    }

    /** Label enum dari config('kesmas.<grup>'); kode yang tak dikenal dikembalikan apa adanya. */
    public static function enumLabel(string $grup, ?string $kode, string $kosong = ''): string
    {
        if ($kode === null || $kode === '') {
            return $kosong;
        }

        return config("kesmas.$grup")[$kode] ?? $kode;
    }

    /** Teks bebas: null/'' (setelah trim) → $kosong. */
    public static function teks($nilai, string $kosong = '—'): string
    {
        return ($nilai === null || trim((string) $nilai) === '') ? $kosong : (string) $nilai;
    }

    /**
     * Ringkasan layanan satu kunjungan untuk kolom "Layanan Kesmas" di detail anak.
     * Badge hanya untuk nilai 1 (0 dan NULL sama-sama tidak tampil); CKG ikut bila tanggalnya ada.
     *
     * @return array{badge: string[], keterangan: string[]}
     */
    public static function layananKunjungan(object $baris): array
    {
        $badge = [];
        foreach (config('kesmas.layanan') as $kolom => $def) {
            if (!empty($baris->$kolom)) {
                $badge[] = $def['badge'];
            }
        }
        if (!empty($baris->tgl_penanda_ckg)) {
            $badge[] = 'CKG ' . Carbon::parse($baris->tgl_penanda_ckg)->format('d/m');
        }

        $keterangan = [];
        foreach (config('kesmas.keterangan_kunjungan') as $kolom => $label) {
            $v = $baris->$kolom ?? null;
            if ($v !== null && trim((string) $v) !== '') {
                $keterangan[] = $label . ': ' . $v;
            }
        }

        return ['badge' => $badge, 'keterangan' => $keterangan];
    }
}
```

- [ ] **Step 5: Jalankan tes, pastikan lolos**

Run: `php artisan test tests/Unit/KesmasPresenterTest.php`
Expected: PASS (6 tests)

- [ ] **Step 6: Commit**

```bash
git add config/kesmas.php app/Services/KesmasPresenter.php tests/Unit/KesmasPresenterTest.php
git commit -m "$(cat <<'EOF'
feat(kesmas): config opsi Kesmas dan presenter label untuk detail & export

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_011UJEVC5xw87p91CXS1BnFv
EOF
)"
```

---

### Task 3: Validasi & penyimpanan field Kesmas di Tambah/Edit Anak (backend)

**Files:**
- Create: `app/Http/Requests/Admin/Anak/KesmasRules.php`
- Modify: `app/Http/Requests/Admin/Anak/storeAnakRequest.php:23-45`
- Modify: `app/Repositories/Admin/Anak/AnakRepository.php:28-193` (`storeAnak`, `updateAnak`, helper baru)
- Modify: `app/Http/Controllers/AdminController.php:234-245` (`updateAnak`)
- Test: `tests/Feature/Kesmas/FormAnakKesmasTest.php`

**Interfaces:**
- Consumes: `config('kesmas.*')` (Task 2).
- Produces: `KesmasRules::anak(?string $penolongLama = null): array`, `KesmasRules::kunjungan(): array`, `KesmasRules::BOOL_ANAK` (`string[]`); `AnakRepository::kesmasAnakAttributes($request): array` (privat), `AnakRepository::kolomKesmas($request, array $kolom, array $boolean): array` (privat, dipakai Task 5).

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php
// tests/Feature/Kesmas/FormAnakKesmasTest.php

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

/**
 * Field Kesmas & riwayat lahir di form Tambah/Edit Anak (spec §3.3–3.4).
 * Payload meniru form asli: select kosong mengirim '', bukan menghilangkan field.
 */
class FormAnakKesmasTest extends TestCase
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
        $this->wilayah = [
            'id_kec' => $kec->id, 'id_kel' => $kel->id, 'id_puskesmas' => $pkm->id,
            'id_posyandu' => $pos->id, 'id_rt' => $rt->id,
        ];
    }

    /** Payload minimum form Tambah Anak (field wajib lama). */
    private function payloadDasar(array $extra = []): array
    {
        return array_merge([
            'no_kk' => '6474010101010001', 'nik' => '6474010101230001', 'nama' => 'Anak Kesmas',
            'nik_ortu' => '6474010101900001', 'nama_ibu' => 'Ibu', 'nama_ayah' => 'Ayah', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2025-01-10', 'golda' => 'O', 'anak' => 1, 'no' => '1',
            'tb' => 60, 'bb' => 5.5, 'lla' => 12, 'lk' => 40, 'asi' => 1, 'obat_cacing' => 0,
            'tgl_kunjungan' => '2025-06-10',
        ], $this->wilayah, $extra);
    }

    /** Field Kesmas persis seperti form baru: semua dikirim, select kosong = ''. */
    private function payloadKesmasKosong(): array
    {
        return [
            'no_id_epus' => '', 'fktp_bpjs' => '', 'air_bersih' => '', 'jamban_sehat' => '', 'merokok_keluarga' => '',
            'status_tk_paud' => '', 'penyakit_penyerta' => '', 'pjb' => '',
            'bbl' => '', 'pbl' => '', 'lk_lahir' => '', 'usia_kehamilan_lahir' => '', 'tempat_bersalin' => '',
            'jenis_persalinan' => '', 'penolong_lahir' => '', 'imd' => '', 'riwayat_kek_ibu' => '',
            'komplikasi_persalinan' => '', 'skrining_shk' => '', 'skrining_shak' => '', 'skrining_g6pd' => '',
            'pemeriksaan_hepatitis_b' => '', 'komplikasi_neonatal' => '',
        ];
    }

    public function test_store_menyimpan_field_kesmas_dan_riwayat_lahir(): void
    {
        $this->actingAs($this->admin)->post(route('admin.storeAnak'), $this->payloadDasar(array_merge($this->payloadKesmasKosong(), [
            'no_id_epus' => 'EP-001', 'fktp_bpjs' => 'PKM Bontang Utara', 'air_bersih' => '1', 'jamban_sehat' => '0',
            'status_tk_paud' => 'PAUD/KB', 'pjb' => 'Tidak Ada',
            'bbl' => '3.2', 'usia_kehamilan_lahir' => '38', 'jenis_persalinan' => 'SC', 'penolong_lahir' => 'Bidan',
            'imd' => '1', 'riwayat_kek_ibu' => '0', 'skrining_shk' => 'normal', 'pemeriksaan_hepatitis_b' => 'non_reaktif',
            'komplikasi_neonatal' => 'Ikterus, fototerapi 2 hari',
        ])))->assertRedirect(route('admin.anak'));

        $anak = Anak::where('nik', '6474010101230001')->firstOrFail();
        $this->assertSame('EP-001', $anak->no_id_epus);
        $this->assertSame('PKM Bontang Utara', $anak->fktp_bpjs);
        $this->assertSame(1, (int) $anak->air_bersih);
        $this->assertSame(0, (int) $anak->jamban_sehat);
        $this->assertNull($anak->merokok_keluarga);           // select '' → null, BUKAN 0
        $this->assertSame('PAUD/KB', $anak->status_tk_paud);
        $this->assertSame('Tidak Ada', $anak->pjb);
        $this->assertSame(3.2, (float) $anak->bbl);
        $this->assertSame(38, (int) $anak->usia_kehamilan_lahir);
        $this->assertSame('SC', $anak->jenis_persalinan);
        $this->assertSame('Bidan', $anak->penolong_lahir);
        $this->assertSame(1, (int) $anak->imd);
        $this->assertSame(0, (int) $anak->riwayat_kek_ibu);
        $this->assertSame('normal', $anak->skrining_shk);
        $this->assertNull($anak->skrining_shak);
        $this->assertSame('non_reaktif', $anak->pemeriksaan_hepatitis_b);
        $this->assertSame('Ikterus, fototerapi 2 hari', $anak->komplikasi_neonatal);
    }

    public function test_store_tanpa_field_kesmas_tetap_sukses(): void
    {
        $this->actingAs($this->admin)->post(route('admin.storeAnak'), $this->payloadDasar())
            ->assertRedirect(route('admin.anak'));

        $anak = Anak::where('nik', '6474010101230001')->firstOrFail();
        $this->assertNull($anak->air_bersih);
        $this->assertNull($anak->skrining_shk);
        $this->assertNull($anak->no_id_epus);
    }

    public function test_store_menolak_nilai_di_luar_daftar(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.createAnak'))
            ->post(route('admin.storeAnak'), $this->payloadDasar([
                'status_tk_paud' => 'SMA', 'skrining_shk' => 'positif', 'usia_kehamilan_lahir' => '60', 'air_bersih' => 'ya',
            ]))
            ->assertRedirect(route('admin.createAnak'))
            ->assertSessionHasErrors(['status_tk_paud', 'skrining_shk', 'usia_kehamilan_lahir', 'air_bersih']);

        $this->assertDatabaseMissing('anak', ['nik' => '6474010101230001']);
    }

    private function anakTersimpan(array $kesmas = []): Anak
    {
        return Anak::create(array_merge([
            'no_kk' => '6474010101010002', 'nik' => '6474010101230002', 'nama' => 'Anak Edit',
            'nik_ortu' => '6474010101900002', 'nama_ibu' => 'Ibu', 'nama_ayah' => 'Ayah', 'jk' => 2,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2024-05-05', 'golda' => 'A', 'anak' => 2, 'no' => '2',
            'status' => 1, 'sumber' => 'manual',
        ], $this->wilayah, $kesmas));
    }

    /** Payload minimum form Edit Anak tanpa ganti lokasi (id_kec tidak dikirim → cabang pertama updateAnak). */
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

    public function test_update_mengubah_field_kesmas(): void
    {
        $anak = $this->anakTersimpan(['air_bersih' => 1, 'skrining_shk' => 'belum']);

        $this->actingAs($this->admin)->put(route('admin.updateAnak', $anak->hashid), $this->payloadEdit($anak, array_merge($this->payloadKesmasKosong(), [
            'air_bersih' => '0', 'merokok_keluarga' => '1', 'skrining_shk' => 'tidak_normal',
            'tempat_bersalin' => 'RSUD Taman Husada',
        ])))->assertRedirect(route('admin.anak'));

        $anak->refresh();
        $this->assertSame(0, (int) $anak->air_bersih);
        $this->assertSame(1, (int) $anak->merokok_keluarga);
        $this->assertSame('tidak_normal', $anak->skrining_shk);
        $this->assertSame('RSUD Taman Husada', $anak->tempat_bersalin);
        $this->assertNull($anak->jamban_sehat);
    }

    public function test_update_tanpa_field_kesmas_tidak_menimpa_data_lama(): void
    {
        $anak = $this->anakTersimpan(['air_bersih' => 1, 'no_id_epus' => 'EP-777', 'skrining_g6pd' => 'normal']);

        $this->actingAs($this->admin)->put(route('admin.updateAnak', $anak->hashid), $this->payloadEdit($anak))
            ->assertRedirect(route('admin.anak'));

        $anak->refresh();
        $this->assertSame(1, (int) $anak->air_bersih);
        $this->assertSame('EP-777', $anak->no_id_epus);
        $this->assertSame('normal', $anak->skrining_g6pd);
    }

    public function test_update_menerima_penolong_lahir_lama_di_luar_daftar(): void
    {
        $anak = $this->anakTersimpan(['penolong_lahir' => 'Dukun terlatih']);

        $this->actingAs($this->admin)->put(route('admin.updateAnak', $anak->hashid), $this->payloadEdit($anak, array_merge($this->payloadKesmasKosong(), [
            'penolong_lahir' => 'Dukun terlatih', 'imd' => '1',
        ])))->assertRedirect(route('admin.anak'))->assertSessionDoesntHaveErrors();

        $anak->refresh();
        $this->assertSame('Dukun terlatih', $anak->penolong_lahir);
        $this->assertSame(1, (int) $anak->imd);
    }

    public function test_update_menolak_penolong_lahir_baru_di_luar_daftar(): void
    {
        $anak = $this->anakTersimpan();

        $this->actingAs($this->admin)
            ->from(route('admin.editAnak', $anak->hashid))
            ->put(route('admin.updateAnak', $anak->hashid), $this->payloadEdit($anak, ['penolong_lahir' => 'Tetangga']))
            ->assertRedirect(route('admin.editAnak', $anak->hashid))
            ->assertSessionHasErrors('penolong_lahir');

        $this->assertNull($anak->refresh()->penolong_lahir);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test tests/Feature/Kesmas/FormAnakKesmasTest.php`
Expected: FAIL — `test_store_menyimpan_field_kesmas_dan_riwayat_lahir` gagal di `assertSame('EP-001', null)`; `test_store_menolak_nilai_di_luar_daftar` gagal karena tidak ada error sesi.

- [ ] **Step 3: Tulis `KesmasRules`**

```php
<?php
// app/Http/Requests/Admin/Anak/KesmasRules.php

namespace App\Http\Requests\Admin\Anak;

use Illuminate\Validation\Rule;

/**
 * Aturan validasi field Kesmas (spec §3.3). Semua nullable — form/klien lama yang tidak
 * mengirim field ini tetap lolos. Dipakai storeAnakRequest, AdminController::updateAnak,
 * AdminController::storeDataAnak, AdminController::updateDataAnak.
 *
 * array_keys(anak()) dan array_keys(kunjungan()) juga menjadi daftar kolom yang
 * dibaca AnakRepository — jangan menaruh key yang bukan kolom di sini.
 */
final class KesmasRules
{
    /** Boolean per anak yang diisi lewat select tiga keadaan ('' / 1 / 0). */
    public const BOOL_ANAK = ['air_bersih', 'jamban_sehat', 'merokok_keluarga', 'imd', 'riwayat_kek_ibu'];

    /**
     * @param string|null $penolongLama nilai penolong_lahir yang sudah tersimpan (import lama)
     *                                  agar tetap sah saat edit meski tidak ada di daftar config
     */
    public static function anak(?string $penolongLama = null): array
    {
        $c = config('kesmas');
        $penolong = array_values(array_unique(array_merge($c['penolong_lahir'], array_filter([$penolongLama]))));
        $skrining = Rule::in(array_keys($c['skrining']));

        return [
            'no_id_epus'              => 'nullable|string|max:50',
            'fktp_bpjs'               => 'nullable|string|max:100',
            'air_bersih'              => 'nullable|boolean',
            'jamban_sehat'            => 'nullable|boolean',
            'merokok_keluarga'        => 'nullable|boolean',
            'status_tk_paud'          => ['nullable', Rule::in($c['status_tk_paud'])],
            'penyakit_penyerta'       => 'nullable|string|max:255',
            'pjb'                     => 'nullable|string|max:100',
            'bbl'                     => 'nullable|numeric|min:0',
            'pbl'                     => 'nullable|numeric|min:0',
            'lk_lahir'                => 'nullable|numeric|min:0',
            'usia_kehamilan_lahir'    => 'nullable|integer|between:20,45',
            'tempat_bersalin'         => 'nullable|string|max:150',
            'jenis_persalinan'        => ['nullable', Rule::in($c['jenis_persalinan'])],
            'penolong_lahir'          => ['nullable', Rule::in($penolong)],
            'imd'                     => 'nullable|boolean',
            'riwayat_kek_ibu'         => 'nullable|boolean',
            'komplikasi_persalinan'   => 'nullable|string|max:255',
            'skrining_shk'            => ['nullable', $skrining],
            'skrining_shak'           => ['nullable', $skrining],
            'skrining_g6pd'           => ['nullable', $skrining],
            'pemeriksaan_hepatitis_b' => ['nullable', Rule::in(array_keys($c['hepatitis_b']))],
            'komplikasi_neonatal'     => 'nullable|string',
        ];
    }

    /** Layanan Kesmas per kunjungan (data_anak). Checkbox = nullable|boolean. */
    public static function kunjungan(): array
    {
        $c = config('kesmas');
        $rules = [
            'tgl_penanda_ckg'     => 'nullable|date',
            'pemeriksaan_gigi'    => ['nullable', Rule::in($c['pemeriksaan_gigi'])],
            'rujukan'             => ['nullable', Rule::in($c['rujukan'])],
            'mt_pangan_lokal'     => 'nullable|string|max:100',
            'catatan_pengukuran'  => 'nullable|string',
            'pemeriksaan_lainnya' => 'nullable|string',
            'pola_makan'          => 'nullable|string',
            'pola_asuh'           => 'nullable|string',
            'intervensi'          => 'nullable|string',
        ];
        foreach (array_keys($c['layanan']) as $kolom) {
            $rules[$kolom] = 'nullable|boolean';
        }

        return $rules;
    }
}
```

- [ ] **Step 4: Tambahkan rule ke `storeAnakRequest`**

Di `app/Http/Requests/Admin/Anak/storeAnakRequest.php`, ganti `return [` … `];` pada `rules()` menjadi:

```php
    public function rules()
    {
        return array_merge([
            'no_kk' => 'required',
            'nik' => 'required',
            'nama' => 'required',
            'nik_ortu' => 'required',
            'nama_ibu' => 'required',
            'nama_ayah' => 'required',
            'jk' => 'required',
            'tempat_lahir' => 'required',
            'tgl_lahir' => 'required',
            'golda' => 'required',
            'anak' => 'required',
            'id_kec' => 'required',
            'id_kel' => 'required',
            'id_rt' => 'required',
            'id_puskesmas' => 'required',
            'id_posyandu' => 'required',
            'alamat' => 'nullable|string',
            'alamat_ktp' => 'nullable|string',
        ], KesmasRules::anak()); // field Kesmas & riwayat lahir (spec §3.3), semua opsional
    }
```

(`KesmasRules` satu namespace, tidak perlu `use`.)

- [ ] **Step 5: Validasi Kesmas di `AdminController::updateAnak`**

Ganti method `updateAnak` (baris 234–245) menjadi:

```php
    public function updateAnak(Request $request, $id)
    {
        $anak = Anak::findByHashIdOrFail($id);
        // Validasi field Kesmas saja (opsional semua); field lama tetap tanpa validasi
        // server seperti sebelumnya. Harus SEBELUM try — ValidationException tidak boleh
        // tertelan catch Throwable menjadi "Gagal Mengubah Data".
        $request->validate(KesmasRules::anak($anak->penolong_lahir));

        try {
            $this->anakRepository->updateAnak($request, $anak->id);
            Alert::success('Anak', 'Berhasil Mengubah Data');
            return redirect()->route('admin.anak');
        } catch (\Throwable $e) {
            Alert::error('Anak', 'Gagal Mengubah Data');
            return redirect()->route('admin.anak');
        }
    }
```

Tambahkan di blok `use` atas berkas: `use App\Http\Requests\Admin\Anak\KesmasRules;`

- [ ] **Step 6: Helper & pemakaian di `AnakRepository`**

Tambahkan `use App\Http\Requests\Admin\Anak\KesmasRules;` di blok `use`. Tambahkan tiga method privat di akhir class (sebelum `}` penutup):

```php
    // ==================== KESMAS (spec 2026-09-15 §3.4) ====================

    /**
     * Kolom Kesmas & riwayat lahir di `anak`. Hanya field yang DIKIRIM form yang
     * disentuh — form/klien lama tanpa field ini tidak menimpa data menjadi null.
     */
    private function kesmasAnakAttributes($request): array
    {
        return $this->kolomKesmas($request, array_keys(KesmasRules::anak()), KesmasRules::BOOL_ANAK);
    }

    /** Layanan Kesmas per kunjungan di `data_anak` (checkbox hidden+checkbox → 0/1). */
    private function layananKesmasAttributes($request): array
    {
        return $this->kolomKesmas($request, array_keys(KesmasRules::kunjungan()), array_keys(config('kesmas.layanan')));
    }

    /**
     * `has()` di sini mendeteksi KEBERADAAN field (dikirim atau tidak), bukan membaca
     * nilainya — nilai boolean tetap dibaca lewat boolean(). '' (select "— belum diisi —") → null.
     */
    private function kolomKesmas($request, array $kolom, array $boolean): array
    {
        $out = [];
        foreach ($kolom as $f) {
            if (!$request->has($f)) {
                continue;
            }
            $v = $request->input($f);
            if ($v === null || $v === '') {
                $out[$f] = null;
                continue;
            }
            $out[$f] = in_array($f, $boolean, true) ? (int) $request->boolean($f) : $v;
        }

        return $out;
    }
```

Lalu pakai di `storeAnak`: ganti `$anak_baru = Anak::create([` … `]);` menjadi `$anak_baru = Anak::create(array_merge([` … `], $this->kesmasAnakAttributes($request)));` — yaitu tambahkan `array_merge(` sebelum `[` dan `, $this->kesmasAnakAttributes($request))` setelah `]` penutup array yang berakhir `'sumber' => 'manual',`.

Di `updateAnak`, **kedua** cabang `$anak->update([` … `'catatan' => $request->catatan ?? '',` `]);` diubah dengan cara yang sama menjadi `$anak->update(array_merge([` … `], $this->kesmasAnakAttributes($request)));`.

- [ ] **Step 7: Jalankan tes, pastikan lolos**

Run: `php artisan test tests/Feature/Kesmas/FormAnakKesmasTest.php`
Expected: PASS (7 tests)

Run juga: `php artisan test tests/Feature/EditAnakTanpaDataAnakTest.php tests/Feature/PenanggungJawabAnakTest.php`
Expected: PASS (regresi form anak lama)

- [ ] **Step 8: Commit**

```bash
git add app/Http/Requests/Admin/Anak/KesmasRules.php app/Http/Requests/Admin/Anak/storeAnakRequest.php app/Repositories/Admin/Anak/AnakRepository.php app/Http/Controllers/AdminController.php tests/Feature/Kesmas/FormAnakKesmasTest.php
git commit -m "$(cat <<'EOF'
feat(kesmas): validasi dan simpan field Kesmas & riwayat lahir di tambah/edit anak

Hanya field yang dikirim form yang disentuh; select kosong → null.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_011UJEVC5xw87p91CXS1BnFv
EOF
)"
```

---

### Task 4: Partial form Kesmas & Riwayat Lahir di Tambah/Edit Anak

**Files:**
- Create: `resources/views/admin/anak/partials/form-kesmas.blade.php`
- Create: `resources/views/admin/anak/partials/form-riwayat-lahir.blade.php`
- Modify: `resources/views/admin/anak/create.blade.php:225-233` (sebelum tombol Simpan)
- Modify: `resources/views/admin/anak/edit.blade.php:294-302` (sebelum tombol Simpan form utama)
- Test: `tests/Feature/Kesmas/FormKesmasBladeTest.php`, tambah 1 tes di `tests/Feature/Kesmas/FormAnakKesmasTest.php`

**Interfaces:**
- Consumes: `config('kesmas.*')`; variabel `$anak` (nullable) dari view pemanggil.
- Produces: partial `admin.anak.partials.form-kesmas` dan `admin.anak.partials.form-riwayat-lahir`, keduanya `@include(..., ['anak' => $anak ?? null])`.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php
// tests/Feature/Kesmas/FormKesmasBladeTest.php

namespace Tests\Feature\Kesmas;

use Tests\TestCase;

/**
 * Mengunci pola partial form Kesmas (spec §3.1): di-include di form yang benar; TIDAK ada
 * `required`/`min`/`max` di dalam kartu collapse (panel tertutup tak bisa difokus → submit
 * mati senyap); Bootstrap 4 (`data-toggle`); tanpa `@section('x')isi@endsection` tanpa spasi.
 */
class FormKesmasBladeTest extends TestCase
{
    /** Partial yang ada; Task 6 menambahkan 'form-layanan-kesmas'. */
    private const PARTIAL = ['form-kesmas', 'form-riwayat-lahir'];

    private function sumber(string $relatif): string
    {
        return file_get_contents(resource_path("views/admin/anak/$relatif.blade.php"));
    }

    public function test_partial_anak_diinclude_di_create_dan_edit(): void
    {
        foreach (['create', 'edit'] as $view) {
            $src = $this->sumber($view);
            $this->assertStringContainsString("admin.anak.partials.form-kesmas", $src, "$view tidak meng-include form-kesmas");
            $this->assertStringContainsString("admin.anak.partials.form-riwayat-lahir", $src, "$view tidak meng-include form-riwayat-lahir");
        }
    }

    public function test_partial_tanpa_required_dan_memakai_bootstrap4(): void
    {
        foreach (self::PARTIAL as $p) {
            // komentar Blade dibuang dulu agar kata di dalam komentar tidak ikut terdeteksi
            $src = preg_replace('/\{\{--.*?--\}\}/s', '', $this->sumber("partials/$p"));
            $this->assertDoesNotMatchRegularExpression('/\brequired\b/', $src, "$p memuat `required` di dalam kartu collapse");
            $this->assertDoesNotMatchRegularExpression('/\b(min|max)="/', $src, "$p memuat min/max HTML yang memblokir submit senyap");
            $this->assertStringNotContainsString('data-bs-toggle', $src, "$p memakai atribut Bootstrap 5");
            $this->assertStringContainsString('data-toggle="collapse"', $src, "$p bukan kartu collapse");
            $this->assertDoesNotMatchRegularExpression("/@section\('[a-z-]+'\)\S.*@endsection/", $src);
        }
    }
}
```

Tambahkan juga ke `tests/Feature/Kesmas/FormAnakKesmasTest.php` (di dalam class, sebelum `}` penutup):

```php
    public function test_form_edit_menampilkan_nilai_kesmas_tersimpan(): void
    {
        $anak = $this->anakTersimpan([
            'no_id_epus' => 'EP-777', 'air_bersih' => 0, 'skrining_shk' => 'tidak_normal', 'penolong_lahir' => 'Dukun terlatih',
        ]);

        $html = $this->actingAs($this->admin)->get(route('admin.editAnak', $anak->hashid))->assertOk()->getContent();

        $this->assertStringContainsString('Data Kesmas &amp; Lingkungan (opsional)', $html);
        $this->assertStringContainsString('Riwayat Kelahiran &amp; Skrining Neonatal (opsional)', $html);
        $this->assertStringContainsString('value="EP-777"', $html);
        $this->assertMatchesRegularExpression('/<select name="air_bersih"[^>]*>.*?<option value="0" selected/s', $html);
        $this->assertMatchesRegularExpression('/<select name="skrining_shk"[^>]*>.*?<option value="tidak_normal" selected/s', $html);
        // nilai lama di luar daftar tetap jadi opsi terpilih agar tidak hilang saat disimpan
        $this->assertMatchesRegularExpression('/<option value="Dukun terlatih" selected/', $html);
    }

    public function test_form_create_tampil_dengan_kartu_kesmas_tertutup(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.createAnak'))->assertOk()->getContent();

        $this->assertStringContainsString('Data Kesmas &amp; Lingkungan (opsional)', $html);
        $this->assertMatchesRegularExpression('/id="kartuKesmas" class="collapse"/', $html);
        $this->assertMatchesRegularExpression('/id="kartuRiwayatLahir" class="collapse"/', $html);
    }
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test tests/Feature/Kesmas/FormKesmasBladeTest.php tests/Feature/Kesmas/FormAnakKesmasTest.php`
Expected: FAIL — partial tidak ditemukan / string "Data Kesmas" tidak ada.

- [ ] **Step 3: Tulis partial `form-kesmas`**

```blade
{{-- resources/views/admin/anak/partials/form-kesmas.blade.php
     Kartu "Data Kesmas & Lingkungan" — spec docs/superpowers/specs/2026-09-15-data-kesmas-design.md §3.
     Dipakai create (tanpa $anak) & edit. Tertutup default; TIDAK BOLEH ada atribut validasi HTML
     (wajib isi, batas bawah/atas angka) di dalamnya: panel tertutup tak bisa difokus browser → submit mati senyap.
     Select tiga keadaan: '' = belum diisi (→ null), 1 = Ya, 0 = Tidak. --}}
@php
    $anak = $anak ?? null;
    $k = config('kesmas');
    $nilai = fn (string $f) => (string) old($f, $anak->$f ?? '');
@endphp
<div class="col-12 mb-3">
    <div class="card">
        <div class="card-header p-0">
            <button type="button" class="btn btn-link btn-block text-left font-weight-bold" data-toggle="collapse" data-target="#kartuKesmas" aria-expanded="false" aria-controls="kartuKesmas">
                Data Kesmas &amp; Lingkungan (opsional)
            </button>
        </div>
        <div id="kartuKesmas" class="collapse">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 col-sm-12">
                        <div class="form-group">
                            <label for="no_id_epus">No. ID ePuskesmas</label>
                            <input type="text" name="no_id_epus" id="no_id_epus" class="form-control" maxlength="50" value="{{ $nilai('no_id_epus') }}">
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-12">
                        <div class="form-group">
                            <label for="fktp_bpjs">FKTP BPJS terdaftar</label>
                            <input type="text" name="fktp_bpjs" id="fktp_bpjs" class="form-control" maxlength="100" value="{{ $nilai('fktp_bpjs') }}">
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-12">
                        <div class="form-group">
                            <label for="status_tk_paud">Keikutsertaan TK/PAUD</label>
                            <select name="status_tk_paud" id="status_tk_paud" class="form-control">
                                <option value="">— belum diisi —</option>
                                @foreach ($k['status_tk_paud'] as $opsi)
                                <option value="{{ $opsi }}" @selected($nilai('status_tk_paud') === $opsi)>{{ $opsi }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @foreach (['air_bersih' => 'Akses air bersih di rumah', 'jamban_sehat' => 'Jamban sehat', 'merokok_keluarga' => 'Ada anggota keluarga serumah yang merokok'] as $f => $label)
                    <div class="col-md-4 col-sm-12">
                        <div class="form-group">
                            <label for="{{ $f }}">{{ $label }}</label>
                            <select name="{{ $f }}" id="{{ $f }}" class="form-control">
                                <option value="" @selected($nilai($f) === '')>— belum diisi —</option>
                                <option value="1" @selected($nilai($f) === '1')>Ya</option>
                                <option value="0" @selected($nilai($f) === '0')>Tidak</option>
                            </select>
                        </div>
                    </div>
                    @endforeach
                    <div class="col-md-8 col-sm-12">
                        <div class="form-group">
                            <label for="penyakit_penyerta">Riwayat penyakit penyerta</label>
                            <input type="text" name="penyakit_penyerta" id="penyakit_penyerta" class="form-control" maxlength="255" placeholder="mis. Asma, Alergi, TBC" value="{{ $nilai('penyakit_penyerta') }}">
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-12">
                        <div class="form-group">
                            <label for="pjb">Penyakit Jantung Bawaan (PJB)</label>
                            <input type="text" name="pjb" id="pjb" class="form-control" maxlength="100" placeholder="Tidak Ada" value="{{ $nilai('pjb') }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
```

- [ ] **Step 4: Tulis partial `form-riwayat-lahir`**

```blade
{{-- resources/views/admin/anak/partials/form-riwayat-lahir.blade.php
     Kartu "Riwayat Kelahiran & Skrining Neonatal" — spec §3. Memuat 7 kolom lama yang belum pernah
     tampil di form (bbl, pbl, lk_lahir, usia_kehamilan_lahir, penolong_lahir, imd, komplikasi_persalinan)
     + 8 kolom neonatal baru. Tertutup default; TIDAK BOLEH ada atribut validasi HTML (wajib isi, batas angka) di dalamnya.
     Nilai penolong_lahir lama dari import yang tak ada di daftar tetap ditawarkan sebagai opsi. --}}
@php
    $anak = $anak ?? null;
    $k = config('kesmas');
    $nilai = fn (string $f) => (string) old($f, $anak->$f ?? '');
    $penolongOpsi = $k['penolong_lahir'];
    $penolongLama = $nilai('penolong_lahir');
    if ($penolongLama !== '' && !in_array($penolongLama, $penolongOpsi, true)) {
        $penolongOpsi[] = $penolongLama;
    }
@endphp
<div class="col-12 mb-3">
    <div class="card">
        <div class="card-header p-0">
            <button type="button" class="btn btn-link btn-block text-left font-weight-bold" data-toggle="collapse" data-target="#kartuRiwayatLahir" aria-expanded="false" aria-controls="kartuRiwayatLahir">
                Riwayat Kelahiran &amp; Skrining Neonatal (opsional)
            </button>
        </div>
        <div id="kartuRiwayatLahir" class="collapse">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 col-sm-12">
                        <div class="form-group">
                            <label for="bbl">Berat lahir (kg)</label>
                            <input type="number" step="0.01" name="bbl" id="bbl" class="form-control" value="{{ $nilai('bbl') }}">
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-12">
                        <div class="form-group">
                            <label for="pbl">Panjang lahir (cm)</label>
                            <input type="number" step="0.1" name="pbl" id="pbl" class="form-control" value="{{ $nilai('pbl') }}">
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-12">
                        <div class="form-group">
                            <label for="lk_lahir">Lingkar kepala lahir (cm)</label>
                            <input type="number" step="0.1" name="lk_lahir" id="lk_lahir" class="form-control" value="{{ $nilai('lk_lahir') }}">
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-12">
                        <div class="form-group">
                            <label for="usia_kehamilan_lahir">Usia kehamilan saat lahir (minggu)</label>
                            <input type="number" step="1" name="usia_kehamilan_lahir" id="usia_kehamilan_lahir" class="form-control" placeholder="20–45" value="{{ $nilai('usia_kehamilan_lahir') }}">
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-12">
                        <div class="form-group">
                            <label for="tempat_bersalin">Tempat bersalin (faskes)</label>
                            <input type="text" name="tempat_bersalin" id="tempat_bersalin" class="form-control" maxlength="150" value="{{ $nilai('tempat_bersalin') }}">
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-12">
                        <div class="form-group">
                            <label for="jenis_persalinan">Jenis persalinan</label>
                            <select name="jenis_persalinan" id="jenis_persalinan" class="form-control">
                                <option value="">— belum diisi —</option>
                                @foreach ($k['jenis_persalinan'] as $opsi)
                                <option value="{{ $opsi }}" @selected($nilai('jenis_persalinan') === $opsi)>{{ $opsi }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-12">
                        <div class="form-group">
                            <label for="penolong_lahir">Penolong persalinan</label>
                            <select name="penolong_lahir" id="penolong_lahir" class="form-control">
                                <option value="">— belum diisi —</option>
                                @foreach ($penolongOpsi as $opsi)
                                <option value="{{ $opsi }}" @selected($penolongLama === $opsi)>{{ $opsi }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @foreach (['imd' => 'Inisiasi Menyusu Dini (IMD)', 'riwayat_kek_ibu' => 'Riwayat KEK ibu saat hamil'] as $f => $label)
                    <div class="col-md-4 col-sm-12">
                        <div class="form-group">
                            <label for="{{ $f }}">{{ $label }}</label>
                            <select name="{{ $f }}" id="{{ $f }}" class="form-control">
                                <option value="" @selected($nilai($f) === '')>— belum diisi —</option>
                                <option value="1" @selected($nilai($f) === '1')>Ya</option>
                                <option value="0" @selected($nilai($f) === '0')>Tidak</option>
                            </select>
                        </div>
                    </div>
                    @endforeach
                    <div class="col-md-4 col-sm-12">
                        <div class="form-group">
                            <label for="komplikasi_persalinan">Komplikasi persalinan</label>
                            <input type="text" name="komplikasi_persalinan" id="komplikasi_persalinan" class="form-control" maxlength="255" value="{{ $nilai('komplikasi_persalinan') }}">
                        </div>
                    </div>
                    @foreach (['skrining_shk' => 'Skrining Hipotiroid Kongenital (SHK)', 'skrining_shak' => 'Skrining Hiperplasia Adrenal Kongenital (SHAK)', 'skrining_g6pd' => 'Skrining G6PD'] as $f => $label)
                    <div class="col-md-4 col-sm-12">
                        <div class="form-group">
                            <label for="{{ $f }}">{{ $label }}</label>
                            <select name="{{ $f }}" id="{{ $f }}" class="form-control">
                                <option value="">— belum diisi —</option>
                                @foreach ($k['skrining'] as $kode => $teks)
                                <option value="{{ $kode }}" @selected($nilai($f) === $kode)>{{ $teks }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @endforeach
                    <div class="col-md-4 col-sm-12">
                        <div class="form-group">
                            <label for="pemeriksaan_hepatitis_b">Pemeriksaan Hepatitis B</label>
                            <select name="pemeriksaan_hepatitis_b" id="pemeriksaan_hepatitis_b" class="form-control">
                                <option value="">— belum diisi —</option>
                                @foreach ($k['hepatitis_b'] as $kode => $teks)
                                <option value="{{ $kode }}" @selected($nilai('pemeriksaan_hepatitis_b') === $kode)>{{ $teks }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-8 col-sm-12">
                        <div class="form-group">
                            <label for="komplikasi_neonatal">Pelayanan / tindakan komplikasi neonatal</label>
                            <textarea name="komplikasi_neonatal" id="komplikasi_neonatal" class="form-control" rows="2">{{ $nilai('komplikasi_neonatal') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
```

- [ ] **Step 5: Include di `create.blade.php` dan `edit.blade.php`**

Di `create.blade.php`, tepat **sebelum** blok tombol Simpan:

```blade
        {{-- Data Kesmas & riwayat lahir (spec 2026-09-15 §3) — kartu tertutup, semua opsional --}}
        @include('admin.anak.partials.form-kesmas', ['anak' => null])
        @include('admin.anak.partials.form-riwayat-lahir', ['anak' => null])
        <div class="col-md-12 col-sm-12">
            <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
```

Di `edit.blade.php`, tepat **sebelum** blok tombol Simpan form utama (yang ada di dalam `<form ... admin.updateAnak ...>`, sekitar baris 299–301):

```blade
        {{-- Data Kesmas & riwayat lahir (spec 2026-09-15 §3) — kartu tertutup, semua opsional --}}
        @include('admin.anak.partials.form-kesmas', ['anak' => $anak])
        @include('admin.anak.partials.form-riwayat-lahir', ['anak' => $anak])
        <div class="col-md-12 col-sm-12">
            <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
```

- [ ] **Step 6: Bersihkan cache view, jalankan tes**

Run: `php artisan view:clear && php artisan test tests/Feature/Kesmas/FormKesmasBladeTest.php tests/Feature/Kesmas/FormAnakKesmasTest.php`
Expected: PASS (2 + 9 tests)

- [ ] **Step 7: Cek visual di browser (Chrome, akun `dinkes@sirindu.go.id` / `Sirindu@2026`)**

Buka `/admin/create-data-dasar-anak`: dua kartu tertutup di atas tombol Simpan; klik judul → terbuka; isi field wajib saja, Simpan → berhasil. Buka Edit Anak yang baru dibuat: kartu memuat nilai tersimpan. Jika ada masalah tampilan, perbaiki di partial (bukan di tes).

- [ ] **Step 8: Commit**

```bash
git add resources/views/admin/anak/partials/form-kesmas.blade.php resources/views/admin/anak/partials/form-riwayat-lahir.blade.php resources/views/admin/anak/create.blade.php resources/views/admin/anak/edit.blade.php tests/Feature/Kesmas/FormKesmasBladeTest.php tests/Feature/Kesmas/FormAnakKesmasTest.php
git commit -m "$(cat <<'EOF'
feat(kesmas): kartu Data Kesmas dan Riwayat Kelahiran di form tambah/edit anak

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_011UJEVC5xw87p91CXS1BnFv
EOF
)"
```

---

### Task 5: Validasi & penyimpanan layanan Kesmas per kunjungan (backend)

**Files:**
- Modify: `app/Http/Controllers/AdminController.php:471-495` (`storeDataAnak` validate) dan `:551-561` (`updateDataAnak`)
- Modify: `app/Repositories/Admin/Anak/AnakRepository.php` (`storeDataAnak`, `updateDataAnak`)
- Test: `tests/Feature/Kesmas/FormPengukuranKesmasTest.php`

**Interfaces:**
- Consumes: `KesmasRules::kunjungan()`, `AnakRepository::layananKesmasAttributes($request)` (Task 3).

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php
// tests/Feature/Kesmas/FormPengukuranKesmasTest.php

namespace Tests\Feature\Kesmas;

use App\Http\Requests\Admin\Anak\KesmasRules;
use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Layanan Kesmas per kunjungan di form Tambah Pengukuran & edit per-kunjungan (spec §3).
 * Payload meniru form asli: checkbox tak dicentang mengirim '0' lewat hidden input.
 */
class FormPengukuranKesmasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Anak $anak;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['type' => 1]);
        $this->anak = Anak::create([
            'nama' => 'Anak Kunjungan', 'nik' => '6474010101250001', 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2025-01-10', 'status' => 1, 'sumber' => 'manual', 'no' => '1',
        ]);
    }

    /** Payload form Tambah Pengukuran persis seperti form: semua field Kesmas dikirim. */
    private function payloadKunjungan(array $extra = []): array
    {
        $checkbox = array_fill_keys(array_keys(config('kesmas.layanan')), '0');

        return array_merge([
            'id_anak_hash' => $this->anak->hashid, 'tgl_kunjungan' => '2025-02-10', 'posisi' => 'L',
            'tb' => 55, 'bb' => 4.5, 'lla' => 11, 'lk' => 38, 'asi' => 1, 'vit_a' => 0, 'obat_cacing' => 0, 'ddtka' => '',
            'tgl_penanda_ckg' => '', 'pemeriksaan_gigi' => '', 'rujukan' => '', 'mt_pangan_lokal' => '',
            'catatan_pengukuran' => '', 'pemeriksaan_lainnya' => '', 'pola_makan' => '', 'pola_asuh' => '', 'intervensi' => '',
        ], $checkbox, $extra);
    }

    private function kunjunganTersimpan(array $extra = []): DataAnak
    {
        return DataAnak::create(array_merge([
            'id_anak' => $this->anak->id, 'tgl_kunjungan' => '2025-02-10', 'bln' => 1, 'posisi' => 'L',
            'tb' => 55, 'bb' => 4.5, 'lla' => 11, 'lk' => 38, 'id_user' => $this->admin->id,
        ], $extra));
    }

    public function test_store_pengukuran_menyimpan_layanan(): void
    {
        $this->actingAs($this->admin)->post(route('admin.storeDataAnak'), $this->payloadKunjungan([
            'kn1' => '1', 'mtbs' => '1', 'mbg' => '1', 'tgl_penanda_ckg' => '2025-02-10',
            'pemeriksaan_gigi' => 'Sehat', 'rujukan' => 'Tidak dirujuk', 'pola_makan' => 'ASI eksklusif',
        ]))->assertRedirect(route('admin.anak'));

        $d = DataAnak::where('id_anak', $this->anak->id)->firstOrFail();
        $this->assertSame(1, (int) $d->kn1);
        $this->assertSame(0, (int) $d->kn3);           // '0' dari hidden input → 0, bukan null
        $this->assertNotNull($d->kn3);
        $this->assertSame(1, (int) $d->mtbs);
        $this->assertSame(0, (int) $d->mtbm);
        $this->assertSame(1, (int) $d->mbg);
        $this->assertSame(0, (int) $d->kelas_ibu_balita);
        $this->assertSame('2025-02-10', $d->tgl_penanda_ckg);
        $this->assertSame('Sehat', $d->pemeriksaan_gigi);
        $this->assertSame('Tidak dirujuk', $d->rujukan);
        $this->assertSame('ASI eksklusif', $d->pola_makan);
        $this->assertNull($d->intervensi);              // '' → null
    }

    public function test_store_pengukuran_tanpa_field_kesmas_tetap_sukses(): void
    {
        $payload = $this->payloadKunjungan();
        foreach (array_keys(KesmasRules::kunjungan()) as $f) {
            unset($payload[$f]);
        }

        $this->actingAs($this->admin)->post(route('admin.storeDataAnak'), $payload)->assertRedirect(route('admin.anak'));

        $d = DataAnak::where('id_anak', $this->anak->id)->firstOrFail();
        $this->assertNull($d->kn1);
        $this->assertNull($d->pemeriksaan_gigi);
    }

    public function test_store_pengukuran_menolak_gigi_rujukan_dan_tanggal_tidak_sah(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.dataAnak', $this->anak->hashid))
            ->post(route('admin.storeDataAnak'), $this->payloadKunjungan([
                'pemeriksaan_gigi' => 'Bagus', 'rujukan' => 'Ke tetangga', 'tgl_penanda_ckg' => 'bukan-tanggal', 'kn1' => 'ya',
            ]))
            ->assertRedirect(route('admin.dataAnak', $this->anak->hashid))
            ->assertSessionHasErrors(['pemeriksaan_gigi', 'rujukan', 'tgl_penanda_ckg', 'kn1']);

        $this->assertSame(0, DataAnak::where('id_anak', $this->anak->id)->count());
    }

    public function test_update_pengukuran_mengubah_layanan(): void
    {
        $d = $this->kunjunganTersimpan(['kn1' => 1, 'rujukan' => 'Rumah sakit']);

        $payload = $this->payloadKunjungan(['kn1' => '0', 'kn3' => '1', 'rujukan' => '', 'intervensi' => 'Rujuk gizi']);
        unset($payload['id_anak_hash']);

        $this->actingAs($this->admin)->put(route('admin.updateDataAnak', $d->id), $payload)
            ->assertRedirect(route('admin.anak'));

        $d->refresh();
        $this->assertSame(0, (int) $d->kn1);
        $this->assertSame(1, (int) $d->kn3);
        $this->assertNull($d->rujukan);
        $this->assertSame('Rujuk gizi', $d->intervensi);
    }

    public function test_update_pengukuran_tanpa_field_kesmas_tidak_menimpa(): void
    {
        $d = $this->kunjunganTersimpan(['kn1' => 1, 'pemeriksaan_gigi' => 'Karies']);

        $this->actingAs($this->admin)->put(route('admin.updateDataAnak', $d->id), [
            'tgl_kunjungan' => '2025-02-10', 'posisi' => 'L', 'tb' => 56, 'bb' => 4.6, 'lla' => 11, 'lk' => 38,
            'asi' => 1, 'vit_a' => 0, 'obat_cacing' => 0, 'ddtka' => '',
        ])->assertRedirect(route('admin.anak'));

        $d->refresh();
        $this->assertSame(1, (int) $d->kn1);
        $this->assertSame('Karies', $d->pemeriksaan_gigi);
        $this->assertSame(56.0, (float) $d->tb);
    }

    public function test_update_pengukuran_menolak_nilai_tidak_sah(): void
    {
        $d = $this->kunjunganTersimpan();

        $payload = $this->payloadKunjungan(['pemeriksaan_gigi' => 'Bagus']);
        unset($payload['id_anak_hash']);

        $this->actingAs($this->admin)
            ->from(route('admin.editAnak', $this->anak->hashid))
            ->put(route('admin.updateDataAnak', $d->id), $payload)
            ->assertRedirect(route('admin.editAnak', $this->anak->hashid))
            ->assertSessionHasErrors('pemeriksaan_gigi');

        $this->assertNull($d->refresh()->pemeriksaan_gigi);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test tests/Feature/Kesmas/FormPengukuranKesmasTest.php`
Expected: FAIL — `assertSame(1, 0)` pada `kn1` (kolom tidak diisi); tes penolakan gagal karena tidak ada error sesi.

- [ ] **Step 3: Validasi di controller**

Di `AdminController::storeDataAnak`, bungkus array rule (argumen pertama `validate`) dengan `array_merge(…, KesmasRules::kunjungan())`; array pesan (argumen kedua) tidak berubah:

```php
        $request->validate(array_merge([
            'id_anak_hash' => 'required',
            'tgl_kunjungan' => 'required|date',
            'posisi' => 'required|in:H,L',
            'tb' => 'required|numeric',
            'bb' => 'required|numeric',
            'lla' => 'required|numeric',
            'lk' => 'required|numeric',
        ], KesmasRules::kunjungan()), [ // layanan Kesmas per kunjungan (spec §3.3), semua opsional
            'tgl_kunjungan.required' => 'Tanggal kunjungan wajib diisi.',
            // … pesan lama tidak berubah …
        ]);
```

Ganti method `updateDataAnak` menjadi:

```php
    public function updateDataAnak(Request $request, $id)
    {
        // Hanya field Kesmas yang divalidasi (semua opsional); field lama tetap seperti
        // sebelumnya. Harus SEBELUM try agar ValidationException tidak tertelan catch.
        $request->validate(KesmasRules::kunjungan());

        try {
            $this->anakRepository->updateDataAnak($request, $id);
            Alert::success('Anak', 'Berhasil Mengubah Data Berkala Anak');
            return redirect()->route('admin.anak');
        } catch (Throwable $e) {
            Alert::error('Anak', 'Gagal Mengubah Data Berkala Anak');
            return redirect()->route('admin.anak');
        }
    }
```

- [ ] **Step 4: Simpan di repository**

Di `AnakRepository::storeDataAnak`, ubah `$anak = DataAnak::create([` … `'sumber' => 'manual',` `]);` menjadi `$anak = DataAnak::create(array_merge([` … `], $this->layananKesmasAttributes($request)));`.

Di `AnakRepository::updateDataAnak`, ubah `$dataAnak->update([` … `'id_user' => Auth::user()->id,` `]);` menjadi `$dataAnak->update(array_merge([` … `], $this->layananKesmasAttributes($request)));`.

- [ ] **Step 5: Jalankan tes, pastikan lolos**

Run: `php artisan test tests/Feature/Kesmas/FormPengukuranKesmasTest.php`
Expected: PASS (6 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/AdminController.php app/Repositories/Admin/Anak/AnakRepository.php tests/Feature/Kesmas/FormPengukuranKesmasTest.php
git commit -m "$(cat <<'EOF'
feat(kesmas): validasi dan simpan layanan Kesmas per kunjungan

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_011UJEVC5xw87p91CXS1BnFv
EOF
)"
```

---

### Task 6: Partial Layanan Kesmas di Tambah Pengukuran & edit per-kunjungan

**Files:**
- Create: `resources/views/admin/anak/partials/form-layanan-kesmas.blade.php`
- Modify: `resources/views/admin/anak/data-anak.blade.php:142-143` (sebelum `{{-- Section Imunisasi (Opsional) --}}`)
- Modify: `resources/views/admin/anak/edit.blade.php` form per-kunjungan (sebelum tombol `Submit` di dalam `@foreach ($dataAnak as $data)`)
- Test: `tests/Feature/Kesmas/FormKesmasBladeTest.php` (perluas), `tests/Feature/Kesmas/FormPengukuranKesmasTest.php` (+2 tes render)

**Interfaces:**
- Consumes: `config('kesmas.layanan')`, `config('kesmas.pemeriksaan_gigi')`, `config('kesmas.rujukan')`.
- Produces: partial `admin.anak.partials.form-layanan-kesmas` dengan parameter `['data' => DataAnak|null, 'p' => string]` — `p` = awalan `id` elemen (wajib unik per form dalam satu halaman).

- [ ] **Step 1: Perluas tes Blade & tambah tes render**

Di `tests/Feature/Kesmas/FormKesmasBladeTest.php`: ubah konstanta menjadi `private const PARTIAL = ['form-kesmas', 'form-riwayat-lahir', 'form-layanan-kesmas'];` dan tambahkan method:

```php
    public function test_partial_layanan_diinclude_di_tambah_pengukuran_dan_edit_per_kunjungan(): void
    {
        $this->assertStringContainsString('admin.anak.partials.form-layanan-kesmas', $this->sumber('data-anak'));
        $this->assertStringContainsString('admin.anak.partials.form-layanan-kesmas', $this->sumber('edit'));
        $this->assertStringNotContainsString('form-layanan-kesmas', $this->sumber('create'));
        // edit merender satu form per kunjungan → awalan id harus dari id kunjungan
        $this->assertMatchesRegularExpression("/form-layanan-kesmas',\s*\['data' => \\\$data,\s*'p' => 'k'\s*\.\s*\\\$data->id\s*\.\s*'_'\]/", $this->sumber('edit'));
    }

    public function test_partial_layanan_memakai_hidden_dan_checkbox_berpasangan(): void
    {
        $src = $this->sumber('partials/form-layanan-kesmas');
        $this->assertStringContainsString('<input type="hidden" name="{{ $f }}" value="0">', $src);
        $this->assertStringContainsString('type="checkbox" name="{{ $f }}" id="{{ $p }}{{ $f }}" value="1"', $src);
    }
```

Di `tests/Feature/Kesmas/FormPengukuranKesmasTest.php` tambahkan:

```php
    public function test_form_tambah_pengukuran_memuat_kartu_layanan(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.dataAnak', $this->anak->hashid))->assertOk()->getContent();

        $this->assertStringContainsString('Layanan Kesmas (opsional)', $html);
        $this->assertMatchesRegularExpression('/id="kartuLayanan" class="collapse"/', $html);
        $this->assertStringContainsString('<input type="hidden" name="kn1" value="0">', $html);
        $this->assertStringContainsString('id="kn1"', $html);
    }

    public function test_form_edit_per_kunjungan_punya_id_unik_dan_nilai_tersimpan(): void
    {
        $a = $this->kunjunganTersimpan(['kn1' => 1, 'pemeriksaan_gigi' => 'Karies']);
        $b = $this->kunjunganTersimpan(['tgl_kunjungan' => '2025-03-10', 'bln' => 2]);

        $html = $this->actingAs($this->admin)->get(route('admin.editAnak', $this->anak->hashid))->assertOk()->getContent();

        $this->assertStringContainsString('id="k' . $a->id . '_kn1"', $html);
        $this->assertStringContainsString('id="k' . $b->id . '_kn1"', $html);
        $this->assertMatchesRegularExpression('/id="k' . $a->id . '_kn1" value="1" checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="k' . $b->id . '_kn1" value="1" checked/', $html);
        $this->assertMatchesRegularExpression('/id="k' . $a->id . '_pemeriksaan_gigi".*?<option value="Karies" selected/s', $html);
        // id tidak boleh ganda di satu halaman
        $this->assertSame(1, substr_count($html, 'id="k' . $a->id . '_kartuLayanan"'));
    }
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test tests/Feature/Kesmas/FormKesmasBladeTest.php tests/Feature/Kesmas/FormPengukuranKesmasTest.php`
Expected: FAIL — partial belum ada.

- [ ] **Step 3: Tulis partial `form-layanan-kesmas`**

```blade
{{-- resources/views/admin/anak/partials/form-layanan-kesmas.blade.php
     Kartu "Layanan Kesmas" per kunjungan — spec §3. $data nullable (form Tambah Pengukuran);
     $p = awalan id unik karena Edit Anak merender satu form per kunjungan di satu halaman.
     Checkbox hidden(0)+checkbox(1) → dibaca $request->boolean() (lihat CLAUDE.md).
     Tertutup default; TIDAK BOLEH ada atribut validasi HTML (wajib isi, batas angka) di dalamnya.
     old() hanya dipakai bila satu form di halaman ($p === '') agar nilai gagal-validasi
     tidak bocor ke semua form kunjungan di halaman edit. --}}
@php
    $data = $data ?? null;
    $p = $p ?? '';
    $k = config('kesmas');
    $nilai = fn (string $f) => (string) ($p === '' ? old($f, $data->$f ?? '') : ($data->$f ?? ''));
@endphp
<div class="col-12 mb-3">
    <div class="card">
        <div class="card-header p-0">
            <button type="button" class="btn btn-link btn-block text-left font-weight-bold" data-toggle="collapse" data-target="#{{ $p }}kartuLayanan" aria-expanded="false" aria-controls="{{ $p }}kartuLayanan">
                Layanan Kesmas (opsional)
            </button>
        </div>
        <div id="{{ $p }}kartuLayanan" class="collapse">
            <div class="card-body">
                <div class="row">
                    @foreach ($k['layanan'] as $f => $def)
                    <div class="col-md-4 col-sm-12">
                        <div class="form-check mb-2">
                            <input type="hidden" name="{{ $f }}" value="0">
                            <input class="form-check-input" type="checkbox" name="{{ $f }}" id="{{ $p }}{{ $f }}" value="1" @checked($nilai($f) === '1')>
                            <label class="form-check-label" for="{{ $p }}{{ $f }}">{{ $def['label'] }}</label>
                        </div>
                    </div>
                    @endforeach
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-4 col-sm-12">
                        <div class="form-group">
                            <label for="{{ $p }}tgl_penanda_ckg">Tanggal penanda CKG (cek kesehatan gigi)</label>
                            <input type="date" name="tgl_penanda_ckg" id="{{ $p }}tgl_penanda_ckg" class="form-control" value="{{ $nilai('tgl_penanda_ckg') }}">
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-12">
                        <div class="form-group">
                            <label for="{{ $p }}pemeriksaan_gigi">Hasil pemeriksaan gigi</label>
                            <select name="pemeriksaan_gigi" id="{{ $p }}pemeriksaan_gigi" class="form-control">
                                <option value="">— belum diisi —</option>
                                @foreach ($k['pemeriksaan_gigi'] as $opsi)
                                <option value="{{ $opsi }}" @selected($nilai('pemeriksaan_gigi') === $opsi)>{{ $opsi }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-12">
                        <div class="form-group">
                            <label for="{{ $p }}rujukan">Rujukan</label>
                            <select name="rujukan" id="{{ $p }}rujukan" class="form-control">
                                <option value="">— belum diisi —</option>
                                @foreach ($k['rujukan'] as $opsi)
                                <option value="{{ $opsi }}" @selected($nilai('rujukan') === $opsi)>{{ $opsi }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-12">
                        <div class="form-group">
                            <label for="{{ $p }}mt_pangan_lokal">Makanan tambahan (MT) pangan lokal</label>
                            <input type="text" name="mt_pangan_lokal" id="{{ $p }}mt_pangan_lokal" class="form-control" maxlength="100" value="{{ $nilai('mt_pangan_lokal') }}">
                        </div>
                    </div>
                    @foreach (['catatan_pengukuran' => 'Catatan perkembangan / hasil pemeriksaan', 'pemeriksaan_lainnya' => 'Hasil pemeriksaan kesehatan lainnya', 'pola_makan' => 'Pola makan anak (frekuensi, ragam menu, ASI/MPASI)', 'pola_asuh' => 'Pola asuh orang tua / keluarga', 'intervensi' => 'Intervensi spesifik/sensitif yang diberikan'] as $f => $label)
                    <div class="col-md-6 col-sm-12">
                        <div class="form-group">
                            <label for="{{ $p }}{{ $f }}">{{ $label }}</label>
                            <textarea name="{{ $f }}" id="{{ $p }}{{ $f }}" class="form-control" rows="2">{{ $nilai($f) }}</textarea>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
```

- [ ] **Step 4: Include di `data-anak.blade.php`**

Tepat sebelum baris `{{-- Section Imunisasi (Opsional) --}}`:

```blade
    {{-- Layanan Kesmas kunjungan ini (spec 2026-09-15 §3) — kartu tertutup, semua opsional --}}
    <div class="row">
        @include('admin.anak.partials.form-layanan-kesmas', ['data' => null, 'p' => ''])
    </div>

```

- [ ] **Step 5: Include di form per-kunjungan `edit.blade.php`**

Di dalam `@foreach ($dataAnak as $data)` … `<form method="post" action="{{route('admin.updateDataAnak',$data->id)}}">`, tepat sebelum blok tombol `Submit`:

```blade
        {{-- Layanan Kesmas kunjungan ini (spec 2026-09-15 §3); awalan id unik per kunjungan --}}
        @include('admin.anak.partials.form-layanan-kesmas', ['data' => $data, 'p' => 'k' . $data->id . '_'])
        <div class="col-md-12 col-sm-12">
            <button type="submit" class="btn btn-primary">Submit</button>
        </div>
```

- [ ] **Step 6: Bersihkan cache view, jalankan tes**

Run: `php artisan view:clear && php artisan test tests/Feature/Kesmas/`
Expected: PASS (semua tes Kesmas sejauh ini)

- [ ] **Step 7: Cek visual di browser**

Buka Tambah Pengukuran seorang anak: kartu "Layanan Kesmas" tertutup di atas seksi imunisasi; centang KN1, Simpan; buka Edit Anak → form kunjungan itu menampilkan KN1 tercentang, kartu kunjungan lain tetap kosong; klik judul kartu kunjungan kedua tidak membuka kartu kunjungan pertama.

- [ ] **Step 8: Commit**

```bash
git add resources/views/admin/anak/partials/form-layanan-kesmas.blade.php resources/views/admin/anak/data-anak.blade.php resources/views/admin/anak/edit.blade.php tests/Feature/Kesmas/FormKesmasBladeTest.php tests/Feature/Kesmas/FormPengukuranKesmasTest.php
git commit -m "$(cat <<'EOF'
feat(kesmas): kartu Layanan Kesmas di tambah pengukuran dan edit per kunjungan

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_011UJEVC5xw87p91CXS1BnFv
EOF
)"
```

---

### Task 7: Tampilan detail anak — kartu Kesmas, Riwayat Kelahiran, kolom Layanan

**Files:**
- Modify: `app/Http/Controllers/AdminController.php:298-343` (`showAnak`: select + `layanan`)
- Modify: `resources/views/admin/anak/show.blade.php:553` (dua kartu baru sebelum `</section>` info) dan `:816-869` (tabel Data Berkala Lengkap)
- Test: `tests/Feature/Kesmas/DetailAnakKesmasTest.php`

**Interfaces:**
- Consumes: `KesmasPresenter::{yaTidak,enumLabel,teks,layananKunjungan}` (Task 2).
- Produces: `$hasilx[$key]['layanan']` = `['badge' => string[], 'keterangan' => string[]]`; `id="kesmas-info-title"`, `id="lahir-info-title"` pada `<h2>` kartu baru.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php
// tests/Feature/Kesmas/DetailAnakKesmasTest.php

namespace Tests\Feature\Kesmas;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman detail anak (spec §4): kartu Kesmas & Riwayat Kelahiran, kolom Layanan Kesmas
 * per kunjungan. NULL tampil "—" (bukan "Tidak"); kartu kosong bilang "Belum diisi".
 */
class DetailAnakKesmasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['type' => 1]);
    }

    private function anak(array $extra = []): Anak
    {
        return Anak::create(array_merge([
            'nama' => 'Anak Detail', 'nik' => '6474010101250009', 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2025-01-10', 'status' => 1, 'sumber' => 'manual', 'no' => '1',
        ], $extra));
    }

    private function kunjungan(Anak $anak, string $tgl, int $bln, array $extra = []): DataAnak
    {
        return DataAnak::create(array_merge([
            'id_anak' => $anak->id, 'tgl_kunjungan' => $tgl, 'bln' => $bln, 'posisi' => 'L',
            'tb' => 55, 'bb' => 4.5, 'lla' => 11, 'lk' => 38, 'id_user' => $this->admin->id,
        ], $extra));
    }

    private function render(Anak $anak): string
    {
        return $this->actingAs($this->admin)->get(route('admin.showAnak', $anak->hashid))->assertOk()->getContent();
    }

    /** Kurung assertion pada satu <article> agar tidak cocok ke kartu lain. */
    private function kartu(string $html, string $judulId): string
    {
        $re = '/<article[^>]*>(?:(?!<\/article>).)*id="' . $judulId . '".*?<\/article>/s';
        $this->assertMatchesRegularExpression($re, $html, "Kartu $judulId tidak ditemukan");
        preg_match($re, $html, $m);

        return $m[0];
    }

    /** Kurung assertion pada satu <tr> (pola FormulirFp1RendersTest::baris). */
    private function baris(string $html, string $needle): string
    {
        $re = '/<tr>(?:(?!<\/tr>).)*' . preg_quote($needle, '/') . '.*?<\/tr>/s';
        $this->assertMatchesRegularExpression($re, $html, "Baris yang memuat '$needle' tidak ditemukan");
        preg_match($re, $html, $m);

        return $m[0];
    }

    public function test_kartu_kesmas_dan_riwayat_lahir_menampilkan_nilai(): void
    {
        $anak = $this->anak([
            'no_id_epus' => 'EP-123', 'air_bersih' => 1, 'jamban_sehat' => 0,
            'bbl' => 3.1, 'penolong_lahir' => 'Bidan', 'skrining_shk' => 'tidak_normal',
            'pemeriksaan_hepatitis_b' => 'non_reaktif',
        ]);

        $html = $this->render($anak);

        $kesmas = $this->kartu($html, 'kesmas-info-title');
        $this->assertStringContainsString('EP-123', $kesmas);
        $this->assertMatchesRegularExpression('/Air bersih<\/dt>\s*<dd[^>]*>\s*Ya\s*</', $kesmas);
        $this->assertMatchesRegularExpression('/Jamban sehat<\/dt>\s*<dd[^>]*>\s*Tidak\s*</', $kesmas);
        $this->assertMatchesRegularExpression('/Perokok serumah<\/dt>\s*<dd[^>]*>\s*—\s*</', $kesmas);  // NULL → —, bukan Tidak

        $lahir = $this->kartu($html, 'lahir-info-title');
        $this->assertStringContainsString('Bidan', $lahir);
        $this->assertMatchesRegularExpression('/SHK<\/dt>\s*<dd[^>]*>\s*<span class="badge badge-accessible-danger">Tidak normal<\/span>/', $lahir);
        $this->assertMatchesRegularExpression('/Hepatitis B<\/dt>\s*<dd[^>]*>\s*<span class="badge badge-accessible-success">Non reaktif<\/span>/', $lahir);
        $this->assertMatchesRegularExpression('/SHAK<\/dt>\s*<dd[^>]*>\s*—\s*</', $lahir);
    }

    public function test_kartu_kosong_menampilkan_belum_diisi(): void
    {
        $html = $this->render($this->anak());

        $this->assertStringContainsString('Belum diisi — lengkapi lewat Edit Anak', $this->kartu($html, 'kesmas-info-title'));
        $this->assertStringContainsString('Belum diisi — lengkapi lewat Edit Anak', $this->kartu($html, 'lahir-info-title'));
    }

    public function test_kolom_layanan_kesmas_per_kunjungan(): void
    {
        $anak = $this->anak();
        $this->kunjungan($anak, '2025-02-10', 1, ['kn1' => 1, 'kn3' => 0, 'mtbs' => 1, 'tgl_penanda_ckg' => '2025-02-10', 'pemeriksaan_gigi' => 'Karies']);
        $this->kunjungan($anak, '2025-03-10', 2);

        $html = $this->render($anak);

        $this->assertStringContainsString('Layanan Kesmas</th>', $html);

        $b1 = $this->baris($html, '10/02/2025');
        $this->assertStringContainsString('>KN1<', $b1);
        $this->assertStringContainsString('>MTBS<', $b1);
        $this->assertStringNotContainsString('>KN3<', $b1);
        $this->assertStringContainsString('>CKG 10/02<', $b1);
        $this->assertStringContainsString('Gigi: Karies', $b1);

        $b2 = $this->baris($html, '10/03/2025');
        $this->assertStringNotContainsString('>KN1<', $b2);
        $this->assertMatchesRegularExpression('/<td[^>]*>\s*—\s*<\/td>\s*<\/tr>/s', $b2);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test tests/Feature/Kesmas/DetailAnakKesmasTest.php`
Expected: FAIL — `Kartu kesmas-info-title tidak ditemukan`

- [ ] **Step 3: Controller `showAnak` — kolom layanan ke `$hasilx`**

Tambahkan `use App\Services\KesmasPresenter;` di blok `use` `AdminController`. Ubah query `$dataAnak` (sekitar baris 298–303) menjadi:

```php
        $dataAnak = DB::table('anak')
            ->join('data_anak', 'anak.id', '=', 'data_anak.id_anak')
            ->select(array_merge(
                ['data_anak.id', 'jk', 'tgl_kunjungan', 'bln', 'posisi', 'tb', 'bb', 'data_anak.tgl_penanda_ckg'],
                array_map(fn ($k) => "data_anak.$k", array_keys(config('kesmas.layanan'))),
                array_map(fn ($k) => "data_anak.$k", array_keys(config('kesmas.keterangan_kunjungan')))
            ))
            ->where('data_anak.id_anak', $anak->id)
            ->orderBy('tgl_kunjungan', 'desc')
            ->get();
```

Di array `$hasilx[$key] = [` tambahkan satu kunci setelah `"err" => $err,`:

```php
                "layanan" => KesmasPresenter::layananKunjungan($data), // badge & keterangan Kesmas (spec §4)
```

- [ ] **Step 4: Dua kartu baru di `show.blade.php`**

Tepat setelah `</div>` penutup kolom "Location Information" (kolom `col-lg-4` yang memuat `id="location-info-title"`) dan sebelum `</section>`:

```blade
        @php
            $K = \App\Services\KesmasPresenter::class;
            $warnaSkrining = fn (?string $kode) => in_array($kode, ['normal', 'non_reaktif'], true) ? 'success'
                : (in_array($kode, ['tidak_normal', 'reaktif'], true) ? 'danger' : 'secondary');
            $isiKesmas = collect(['no_id_epus', 'fktp_bpjs', 'air_bersih', 'jamban_sehat', 'merokok_keluarga', 'status_tk_paud', 'penyakit_penyerta', 'pjb'])
                ->contains(fn ($f) => $anak->$f !== null && $anak->$f !== '');
            $isiLahir = collect(['bbl', 'pbl', 'lk_lahir', 'usia_kehamilan_lahir', 'tempat_bersalin', 'jenis_persalinan', 'penolong_lahir', 'imd', 'riwayat_kek_ibu', 'komplikasi_persalinan', 'skrining_shk', 'skrining_shak', 'skrining_g6pd', 'pemeriksaan_hepatitis_b', 'komplikasi_neonatal'])
                ->contains(fn ($f) => $anak->$f !== null && $anak->$f !== '');
        @endphp

        {{-- Kesmas & Lingkungan (spec 2026-09-15 §4) — NULL tampil "—", bukan "Tidak" --}}
        <div class="col-lg-4 mb-4">
            <article class="card info-card h-100">
                <div class="card-header">
                    <h2 id="kesmas-info-title">
                        <span aria-hidden="true" class="icon-copy dw dw-house-1 mr-2"></span>
                        Kesmas &amp; Lingkungan
                    </h2>
                </div>
                <div class="card-body">
                    @if (!$isiKesmas)
                    <p class="text-accessible-muted mb-0">Belum diisi — lengkapi lewat Edit Anak.</p>
                    @else
                    <dl class="row mb-0">
                        <dt class="col-sm-5 text-accessible-muted">No. ID ePuskesmas</dt>
                        <dd class="col-sm-7">{{ $K::teks($anak->no_id_epus) }}</dd>

                        <dt class="col-sm-5 text-accessible-muted">FKTP BPJS</dt>
                        <dd class="col-sm-7">{{ $K::teks($anak->fktp_bpjs) }}</dd>

                        <dt class="col-sm-5 text-accessible-muted">Air bersih</dt>
                        <dd class="col-sm-7">{{ $K::yaTidak($anak->air_bersih, '—') }}</dd>

                        <dt class="col-sm-5 text-accessible-muted">Jamban sehat</dt>
                        <dd class="col-sm-7">{{ $K::yaTidak($anak->jamban_sehat, '—') }}</dd>

                        <dt class="col-sm-5 text-accessible-muted">Perokok serumah</dt>
                        <dd class="col-sm-7">{{ $K::yaTidak($anak->merokok_keluarga, '—') }}</dd>

                        <dt class="col-sm-5 text-accessible-muted">TK/PAUD</dt>
                        <dd class="col-sm-7">{{ $K::teks($anak->status_tk_paud) }}</dd>

                        <dt class="col-sm-5 text-accessible-muted">Penyakit penyerta</dt>
                        <dd class="col-sm-7">{{ $K::teks($anak->penyakit_penyerta) }}</dd>

                        <dt class="col-sm-5 text-accessible-muted">PJB</dt>
                        <dd class="col-sm-7 mb-0">{{ $K::teks($anak->pjb) }}</dd>
                    </dl>
                    @endif
                </div>
            </article>
        </div>

        {{-- Riwayat Kelahiran & Skrining Neonatal (spec 2026-09-15 §4) --}}
        <div class="col-lg-4 mb-4">
            <article class="card info-card h-100">
                <div class="card-header">
                    <h2 id="lahir-info-title">
                        <span aria-hidden="true" class="icon-copy dw dw-baby mr-2"></span>
                        Riwayat Kelahiran
                    </h2>
                </div>
                <div class="card-body">
                    @if (!$isiLahir)
                    <p class="text-accessible-muted mb-0">Belum diisi — lengkapi lewat Edit Anak.</p>
                    @else
                    <dl class="row mb-0">
                        <dt class="col-sm-5 text-accessible-muted">BBL / PBL / LK</dt>
                        <dd class="col-sm-7">{{ $K::teks($anak->bbl) }} kg / {{ $K::teks($anak->pbl) }} cm / {{ $K::teks($anak->lk_lahir) }} cm</dd>

                        <dt class="col-sm-5 text-accessible-muted">Usia kehamilan</dt>
                        <dd class="col-sm-7">{{ $anak->usia_kehamilan_lahir !== null ? $anak->usia_kehamilan_lahir . ' minggu' : '—' }}</dd>

                        <dt class="col-sm-5 text-accessible-muted">Tempat bersalin</dt>
                        <dd class="col-sm-7">{{ $K::teks($anak->tempat_bersalin) }}</dd>

                        <dt class="col-sm-5 text-accessible-muted">Jenis / penolong</dt>
                        <dd class="col-sm-7">{{ $K::teks($anak->jenis_persalinan) }} / {{ $K::teks($anak->penolong_lahir) }}</dd>

                        <dt class="col-sm-5 text-accessible-muted">IMD</dt>
                        <dd class="col-sm-7">{{ $K::yaTidak($anak->imd, '—') }}</dd>

                        <dt class="col-sm-5 text-accessible-muted">KEK ibu</dt>
                        <dd class="col-sm-7">{{ $K::yaTidak($anak->riwayat_kek_ibu, '—') }}</dd>

                        @foreach (['skrining_shk' => ['SHK', 'skrining'], 'skrining_shak' => ['SHAK', 'skrining'], 'skrining_g6pd' => ['G6PD', 'skrining'], 'pemeriksaan_hepatitis_b' => ['Hepatitis B', 'hepatitis_b']] as $f => [$label, $grup])
                        <dt class="col-sm-5 text-accessible-muted">{{ $label }}</dt>
                        <dd class="col-sm-7">
                            @if ($anak->$f)
                            <span class="badge badge-accessible-{{ $warnaSkrining($anak->$f) }}">{{ $K::enumLabel($grup, $anak->$f) }}</span>
                            @else
                            —
                            @endif
                        </dd>
                        @endforeach

                        <dt class="col-sm-5 text-accessible-muted">Komplikasi persalinan</dt>
                        <dd class="col-sm-7">{{ $K::teks($anak->komplikasi_persalinan) }}</dd>

                        <dt class="col-sm-5 text-accessible-muted">Komplikasi neonatal</dt>
                        <dd class="col-sm-7 mb-0">{{ $K::teks($anak->komplikasi_neonatal) }}</dd>
                    </dl>
                    @endif
                </div>
            </article>
        </div>
```

Catatan: kelas `badge-accessible-success|danger|secondary` sudah didefinisikan di `<style>` halaman ini. Jika ikon `dw-house-1`/`dw-baby` tidak ada di font ikon, ganti dengan `dw-home` / `dw-user-1` — cek visual di Step 7.

- [ ] **Step 5: Kolom "Layanan Kesmas" di tabel Data Berkala Lengkap**

Di `<thead>` tabel `id="dataTable"`, tambahkan setelah `<th scope="col"><abbr title="Berat Badan menurut Tinggi Badan">BB/TB</abbr></th>`:

```blade
                                    <th scope="col">Layanan Kesmas</th>
```

Di `<tbody>`, setelah `<td>` terakhir baris (yang memuat `$hasil['bt']`) dan sebelum `</tr>`:

```blade
                                    <td>
                                        @foreach ($hasil['layanan']['badge'] as $b)
                                        <span class="badge badge-accessible-info mr-1">{{ $b }}</span>
                                        @endforeach
                                        @if ($hasil['layanan']['keterangan'])
                                        <span class="icon-copy dw dw-file" role="img" aria-label="{{ implode(' · ', $hasil['layanan']['keterangan']) }}" title="{{ implode(' · ', $hasil['layanan']['keterangan']) }}"></span>
                                        <span class="sr-only">{{ implode(' · ', $hasil['layanan']['keterangan']) }}</span>
                                        @endif
                                        @if (!$hasil['layanan']['badge'] && !$hasil['layanan']['keterangan'])
                                        —
                                        @endif
                                    </td>
```

- [ ] **Step 6: Bersihkan cache view, jalankan tes**

Run: `php artisan view:clear && php artisan test tests/Feature/Kesmas/DetailAnakKesmasTest.php`
Expected: PASS (3 tests)

- [ ] **Step 7: Cek visual di browser**

Buka detail anak yang tadi diisi: dua kartu baru sejajar kartu Orang Tua/Wilayah (membungkus ke baris berikutnya di desktop), badge skrining berwarna, kolom Layanan Kesmas di tabel bawah. Cek lebar 390px: kartu menumpuk rapi. Ikon judul tampil (jika kotak kosong → ganti kelas ikon, lihat catatan Step 4).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/AdminController.php resources/views/admin/anak/show.blade.php tests/Feature/Kesmas/DetailAnakKesmasTest.php
git commit -m "$(cat <<'EOF'
feat(kesmas): kartu Kesmas & Riwayat Kelahiran dan kolom layanan di detail anak

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_011UJEVC5xw87p91CXS1BnFv
EOF
)"
```

---

### Task 8: Export Kesmas (rute, controller, view, menu, dua sheet)

**Files:**
- Create: `app/Exports/KesmasExport.php`, `app/Exports/KesmasAnakSheet.php`, `app/Exports/KesmasKunjunganSheet.php`
- Create: `app/Http/Controllers/ExportKesmasController.php`
- Create: `resources/views/admin/export/kesmas.blade.php`
- Modify: `routes/web.php:262` (setelah grup `export-pd3i`)
- Modify: `resources/views/vendor/admin/layouts/partials/leftsidebar.blade.php:72` dan `:175` (kedua salinan menu Export Data)
- Test: `tests/Feature/Kesmas/ExportKesmasTest.php`

**Interfaces:**
- Consumes: `KesmasPresenter::{yaTidak,enumLabel}`, `config('kesmas.layanan')`.
- Produces: rute `admin.export.kesmas.index` (GET `admin/export-kesmas`), `admin.export.kesmas.download` (GET `admin/export-kesmas/download?id_kec&id_kel&id_puskesmas&id_posyandu&dari&sampai`); `KesmasExport::__construct(array $filter)`, `::sheets(): array`, `::filename(): string`; `KesmasAnakSheet::terapkanWilayah(Builder $q, array $filter, string $prefix = ''): Builder` (statis, dipakai kedua sheet).

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php
// tests/Feature/Kesmas/ExportKesmasTest.php

namespace Tests\Feature\Kesmas;

use App\Exports\KesmasExport;
use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

/**
 * Export Kesmas (spec §5): dua sheet, label Ya/Tidak/kosong, filter wilayah & tanggal,
 * faskes surveilans ditolak, NIK tetap teks.
 */
class ExportKesmasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Kelurahan $kelA;
    private Kelurahan $kelB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['type' => 1]);
        $kec = Kecamatan::create(['name' => 'Bontang Selatan']);
        $this->kelA = Kelurahan::create(['name' => 'Berbas Tengah', 'id_kecamatan' => $kec->id]);
        $this->kelB = Kelurahan::create(['name' => 'Tanjung Laut', 'id_kecamatan' => $kec->id]);
    }

    private function anak(string $nik, Kelurahan $kel, array $extra = []): Anak
    {
        return Anak::create(array_merge([
            'nama' => 'Anak ' . $nik, 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2025-01-10',
            'status' => 1, 'sumber' => 'manual', 'no' => '1', 'id_kec' => $kel->id_kecamatan, 'id_kel' => $kel->id,
        ], $extra));
    }

    private function kunjungan(Anak $anak, string $tgl, array $extra = []): DataAnak
    {
        return DataAnak::create(array_merge([
            'id_anak' => $anak->id, 'tgl_kunjungan' => $tgl, 'bln' => 1, 'posisi' => 'L',
            'tb' => 55, 'bb' => 4.5, 'lla' => 11, 'lk' => 38, 'id_user' => $this->admin->id,
        ], $extra));
    }

    /** @return array{0: Worksheet, 1: Worksheet} sheet "Per Anak" dan "Per Kunjungan" */
    private function sheets(array $filter): array
    {
        $path = tempnam(sys_get_temp_dir(), 'kesmas');
        try {
            file_put_contents($path, Excel::raw(new KesmasExport($filter), \Maatwebsite\Excel\Excel::XLSX));
            $book = IOFactory::load($path);

            return [$book->getSheetByName('Per Anak'), $book->getSheetByName('Per Kunjungan')];
        } finally {
            unlink($path);
        }
    }

    public function test_halaman_export_tampil_dengan_menu_sidebar(): void
    {
        $this->actingAs($this->admin)->get(route('admin.export.kesmas.index'))
            ->assertOk()
            ->assertSee('Export Kesmas')
            ->assertSee(route('admin.export.kesmas.download'), false);
    }

    public function test_faskes_surveilans_ditolak(): void
    {
        $faskes = User::factory()->create(['type' => 1, 'role' => 'surveilans_puskesmas']);

        $this->actingAs($faskes)->get(route('admin.export.kesmas.index'))->assertForbidden();
        $this->actingAs($faskes)->get(route('admin.export.kesmas.download'))->assertForbidden();
    }

    public function test_download_memicu_export_dengan_nama_berkas(): void
    {
        Excel::fake();

        $this->actingAs($this->admin)->get(route('admin.export.kesmas.download', ['id_kel' => $this->kelA->id]))->assertOk();

        Excel::assertDownloaded('kesmas-berbas-tengah-' . now()->format('Ymd') . '.xlsx', fn (KesmasExport $e) => count($e->sheets()) === 2);
    }

    public function test_download_tanpa_filter_bernama_semua(): void
    {
        Excel::fake();

        $this->actingAs($this->admin)->get(route('admin.export.kesmas.download'))->assertOk();

        Excel::assertDownloaded('kesmas-semua-' . now()->format('Ymd') . '.xlsx');
    }

    public function test_download_menolak_filter_tidak_sah(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.export.kesmas.index'))
            ->get(route('admin.export.kesmas.download', ['id_kel' => 999999, 'dari' => '2026-02-01', 'sampai' => '2026-01-01']))
            ->assertRedirect(route('admin.export.kesmas.index'))
            ->assertSessionHasErrors(['id_kel', 'sampai']);
    }

    public function test_sheet_per_anak_berisi_label_dan_terfilter_kelurahan(): void
    {
        $this->anak('6474010101250001', $this->kelA, ['air_bersih' => 1, 'jamban_sehat' => 0, 'skrining_shk' => 'tidak_normal', 'no_id_epus' => 'EP-1']);
        $this->anak('6474010101250002', $this->kelB, ['air_bersih' => 1]);

        [$anak] = $this->sheets(['id_kel' => $this->kelA->id]);

        $this->assertSame('NIK', $anak->getCell('A1')->getValue());
        $this->assertSame('Air Bersih', $anak->getCell('L1')->getValue());
        $this->assertSame('SHK', $anak->getCell('AA1')->getValue());
        $this->assertSame('6474010101250001', $anak->getCell('A2')->getValue());
        $this->assertSame('s', $anak->getCell('A2')->getDataType());       // NIK tetap teks
        $this->assertSame('Berbas Tengah', $anak->getCell('F2')->getValue());
        $this->assertSame('EP-1', $anak->getCell('J2')->getValue());
        $this->assertSame('Ya', $anak->getCell('L2')->getValue());
        $this->assertSame('Tidak', $anak->getCell('M2')->getValue());
        $this->assertSame('', (string) $anak->getCell('N2')->getValue());  // NULL → kosong
        $this->assertSame('Tidak normal', $anak->getCell('AA2')->getValue());
        $this->assertNull($anak->getCell('A3')->getValue());                 // anak kelurahan B tak ikut
    }

    public function test_sheet_per_kunjungan_terfilter_tanggal(): void
    {
        $a = $this->anak('6474010101250003', $this->kelA);
        $this->kunjungan($a, '2026-01-15', ['kn1' => 1, 'mtbs' => 0, 'pemeriksaan_gigi' => 'Karies']);
        $this->kunjungan($a, '2026-03-15', ['kn3' => 1]);

        [, $kunj] = $this->sheets(['dari' => '2026-01-01', 'sampai' => '2026-01-31']);

        $this->assertSame('KN1', $kunj->getCell('H1')->getValue());
        $this->assertSame('Atresia Bilier', $kunj->getCell('M1')->getValue());
        $this->assertSame('6474010101250003', $kunj->getCell('A2')->getValue());
        $this->assertSame('s', $kunj->getCell('A2')->getDataType());
        $this->assertSame('2026-01-15', $kunj->getCell('C2')->getValue());
        $this->assertSame('Ya', $kunj->getCell('H2')->getValue());          // kn1
        $this->assertSame('', (string) $kunj->getCell('J2')->getValue());   // mtbm NULL
        $this->assertSame('Tidak', $kunj->getCell('K2')->getValue());       // mtbs = 0
        $this->assertSame('Karies', $kunj->getCell('Q2')->getValue());
        $this->assertNull($kunj->getCell('A3')->getValue());                 // kunjungan Maret tak ikut
    }

    public function test_sheet_per_kunjungan_ikut_filter_wilayah(): void
    {
        $a = $this->anak('6474010101250004', $this->kelA);
        $b = $this->anak('6474010101250005', $this->kelB);
        $this->kunjungan($a, '2026-01-15');
        $this->kunjungan($b, '2026-01-16');

        [, $kunj] = $this->sheets(['id_kel' => $this->kelB->id]);

        $this->assertSame('6474010101250005', $kunj->getCell('A2')->getValue());
        $this->assertNull($kunj->getCell('A3')->getValue());
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test tests/Feature/Kesmas/ExportKesmasTest.php`
Expected: FAIL — `Route [admin.export.kesmas.index] not defined` / class `KesmasExport` tidak ada.

- [ ] **Step 3: Tulis tiga class export**

```php
<?php
// app/Exports/KesmasExport.php

namespace App\Exports;

use App\Models\Kelurahan;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Export Kesmas — dua sheet: "Per Anak" (identitas + Kesmas + riwayat lahir) dan
 * "Per Kunjungan" (layanan per baris data_anak). Bahan dasbor Kesmas (spec §5).
 *
 * @phpstan-type Filter array{id_kec?:mixed,id_kel?:mixed,id_puskesmas?:mixed,id_posyandu?:mixed,dari?:mixed,sampai?:mixed}
 */
final class KesmasExport implements WithMultipleSheets
{
    use Exportable;

    /** @param Filter $filter */
    public function __construct(private array $filter) {}

    public function sheets(): array
    {
        return [new KesmasAnakSheet($this->filter), new KesmasKunjunganSheet($this->filter)];
    }

    public function filename(): string
    {
        $kel = !empty($this->filter['id_kel']) ? Kelurahan::find($this->filter['id_kel'])?->name : null;

        return 'kesmas-' . ($kel ? Str::slug($kel) : 'semua') . '-' . now()->format('Ymd') . '.xlsx';
    }
}
```

```php
<?php
// app/Exports/KesmasAnakSheet.php

namespace App\Exports;

use App\Models\Anak;
use App\Services\KesmasPresenter as K;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/** Sheet "Per Anak" — satu baris per anak yang lolos filter wilayah (spec §5). */
final class KesmasAnakSheet extends DefaultValueBinder implements FromQuery, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithCustomValueBinder
{
    public function __construct(private array $filter) {}

    /** Filter wilayah dipakai kedua sheet; $prefix 'anak.' bila query-nya join. */
    public static function terapkanWilayah(Builder $q, array $filter, string $prefix = ''): Builder
    {
        foreach (['id_kec', 'id_kel', 'id_puskesmas', 'id_posyandu'] as $k) {
            if (!empty($filter[$k])) {
                $q->where($prefix . $k, $filter[$k]);
            }
        }

        return $q;
    }

    public function query()
    {
        return self::terapkanWilayah(
            Anak::query()->with(['kec', 'kel', 'rt', 'puskesmas', 'posyandu'])->orderBy('nama'),
            $this->filter
        );
    }

    public function title(): string
    {
        return 'Per Anak';
    }

    public function headings(): array
    {
        return [
            'NIK', 'Nama', 'JK', 'Tgl Lahir', 'Kecamatan', 'Kelurahan', 'RT', 'Puskesmas', 'Posyandu',
            'No ID ePus', 'FKTP BPJS', 'Air Bersih', 'Jamban Sehat', 'Perokok Serumah', 'TK/PAUD', 'Penyakit Penyerta', 'PJB',
            'BBL (kg)', 'PBL (cm)', 'LK Lahir (cm)', 'Usia Kehamilan (mgg)', 'Tempat Bersalin', 'Jenis Persalinan', 'Penolong',
            'IMD', 'KEK Ibu', 'SHK', 'SHAK', 'G6PD', 'Hepatitis B', 'Komplikasi Persalinan', 'Komplikasi Neonatal',
        ];
    }

    /** @param Anak $a */
    public function map($a): array
    {
        return [
            $a->nik, $a->nama, $a->jk == 1 ? 'L' : 'P', $a->tgl_lahir,
            $a->kec->name ?? '', $a->kel->name ?? '', $a->rt->name ?? '', $a->puskesmas->name ?? '', $a->posyandu->name ?? '',
            $a->no_id_epus, $a->fktp_bpjs, K::yaTidak($a->air_bersih), K::yaTidak($a->jamban_sehat), K::yaTidak($a->merokok_keluarga),
            $a->status_tk_paud, $a->penyakit_penyerta, $a->pjb,
            $a->bbl, $a->pbl, $a->lk_lahir, $a->usia_kehamilan_lahir, $a->tempat_bersalin, $a->jenis_persalinan, $a->penolong_lahir,
            K::yaTidak($a->imd), K::yaTidak($a->riwayat_kek_ibu),
            K::enumLabel('skrining', $a->skrining_shk), K::enumLabel('skrining', $a->skrining_shak),
            K::enumLabel('skrining', $a->skrining_g6pd), K::enumLabel('hepatitis_b', $a->pemeriksaan_hepatitis_b),
            $a->komplikasi_persalinan, $a->komplikasi_neonatal,
        ];
    }

    /** NIK 16 digit harus tetap teks — Excel memotong presisi angka >15 digit. */
    public function bindValue(Cell $cell, $value)
    {
        if ($cell->getColumn() === 'A') {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
```

```php
<?php
// app/Exports/KesmasKunjunganSheet.php

namespace App\Exports;

use App\Models\DataAnak;
use App\Services\KesmasPresenter as K;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/** Sheet "Per Kunjungan" — satu baris per data_anak (filter wilayah anak + rentang tgl_kunjungan). */
final class KesmasKunjunganSheet extends DefaultValueBinder implements FromQuery, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithCustomValueBinder
{
    public function __construct(private array $filter) {}

    public function query()
    {
        // join, bukan whereHas('anak'): DataAnak::anak() memakai FK bawaan `anak_id` yang tidak ada.
        $q = DataAnak::query()
            ->join('anak', 'anak.id', '=', 'data_anak.id_anak')
            ->select('data_anak.*', 'anak.nik', 'anak.nama')
            ->orderBy('anak.nama')
            ->orderBy('data_anak.tgl_kunjungan');

        KesmasAnakSheet::terapkanWilayah($q, $this->filter, 'anak.');

        if (!empty($this->filter['dari'])) {
            $q->whereDate('data_anak.tgl_kunjungan', '>=', $this->filter['dari']);
        }
        if (!empty($this->filter['sampai'])) {
            $q->whereDate('data_anak.tgl_kunjungan', '<=', $this->filter['sampai']);
        }

        return $q;
    }

    public function title(): string
    {
        return 'Per Kunjungan';
    }

    public function headings(): array
    {
        $h = ['NIK', 'Nama', 'Tgl Kunjungan', 'Usia (bln)', 'BB (kg)', 'TB (cm)', 'Tgl Penanda CKG'];
        foreach (config('kesmas.layanan') as $def) {
            $h[] = $def['kolom'];
        }

        return array_merge($h, [
            'Pemeriksaan Gigi', 'Rujukan', 'MT Pangan Lokal', 'Catatan', 'Pemeriksaan Lainnya', 'Pola Makan', 'Pola Asuh', 'Intervensi',
        ]);
    }

    /** @param DataAnak $d (dengan kolom join nik, nama) */
    public function map($d): array
    {
        $r = [$d->nik, $d->nama, $d->tgl_kunjungan, $d->bln, $d->bb, $d->tb, $d->tgl_penanda_ckg];
        foreach (array_keys(config('kesmas.layanan')) as $k) {
            $r[] = K::yaTidak($d->$k);
        }

        return array_merge($r, [
            $d->pemeriksaan_gigi, $d->rujukan, $d->mt_pangan_lokal, $d->catatan_pengukuran,
            $d->pemeriksaan_lainnya, $d->pola_makan, $d->pola_asuh, $d->intervensi,
        ]);
    }

    /** NIK 16 digit harus tetap teks. */
    public function bindValue(Cell $cell, $value)
    {
        if ($cell->getColumn() === 'A') {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
```

- [ ] **Step 4: Controller**

```php
<?php
// app/Http/Controllers/ExportKesmasController.php

namespace App\Http\Controllers;

use App\Exports\KesmasExport;
use App\Models\Kecamatan;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Export Kesmas — Excel dua sheet (Per Anak, Per Kunjungan) sebagai bahan dasbor Kesmas.
 * Spec: docs/superpowers/specs/2026-09-15-data-kesmas-design.md §5.
 * Sama dengan Export Anak lama: tidak ada scoping per faskes selain menolak
 * pengguna modul surveilans (pola ExportImunisasiController).
 */
class ExportKesmasController extends Controller
{
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

    public function index()
    {
        $kec = Kecamatan::orderBy('name')->get();

        return view('admin.export.kesmas', compact('kec'));
    }

    public function download(Request $request)
    {
        $filter = $request->validate([
            'id_kec'       => 'nullable|integer|exists:kecamatan,id',
            'id_kel'       => 'nullable|integer|exists:kelurahan,id',
            'id_puskesmas' => 'nullable|integer|exists:puskesmas,id',
            'id_posyandu'  => 'nullable|integer|exists:posyandu,id',
            'dari'         => 'nullable|date',
            'sampai'       => 'nullable|date|after_or_equal:dari',
        ]);

        $export = new KesmasExport($filter);

        return Excel::download($export, $export->filename());
    }
}
```

- [ ] **Step 5: Rute**

Di `routes/web.php`, tepat setelah blok `Route::prefix('export-pd3i')->group(function () { … });` (masih di dalam grup `admin/`):

```php
    // Export Kesmas — spec docs/superpowers/specs/2026-09-15-data-kesmas-design.md §5
    Route::prefix('export-kesmas')->group(function () {
        Route::get('/', [App\Http\Controllers\ExportKesmasController::class, 'index'])
             ->name('admin.export.kesmas.index');
        Route::get('download', [App\Http\Controllers\ExportKesmasController::class, 'download'])
             ->name('admin.export.kesmas.download');
    });
```

- [ ] **Step 6: View filter**

```blade
{{-- resources/views/admin/export/kesmas.blade.php — form filter Export Kesmas (spec §5).
     Cascade wilayah memakai endpoint AJAX yang sama dengan Export Anak; placeholder value=""
     supaya rule nullable|exists lolos tanpa filter. --}}
@extends('admin::layouts.app')
@section('title') Admin @endsection
@section('title-content') Export Data @endsection
@section('item') Export @endsection
@section('item-active') Kesmas @endsection
@section('content')
<div class="card">
    <div class="card-header">
        <h1 class="h5 mb-0">Export Kesmas</h1>
        <p class="text-muted mb-0 small">Excel dua sheet: <strong>Per Anak</strong> (identitas, data Kesmas, riwayat kelahiran & skrining neonatal) dan <strong>Per Kunjungan</strong> (layanan Kesmas tiap pengukuran). Kosongkan filter untuk semua data.</p>
    </div>
    <div class="card-body">
        @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif
        <form method="get" action="{{ route('admin.export.kesmas.download') }}">
            <div class="row">
                <div class="col-md-3 col-sm-12">
                    <div class="form-group">
                        <label for="kec">Kecamatan</label>
                        <select id="kec" name="id_kec" class="form-control">
                            <option value="">Semua kecamatan</option>
                            @foreach ($kec as $k)
                            <option value="{{ $k->id }}" @selected((string) old('id_kec') === (string) $k->id)>{{ $k->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3 col-sm-12">
                    <div class="form-group">
                        <label for="kel">Kelurahan</label>
                        <select id="kel" name="id_kel" class="form-control">
                            <option value="">Semua kelurahan</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3 col-sm-12">
                    <div class="form-group">
                        <label for="puskesmas">Puskesmas</label>
                        <select id="puskesmas" name="id_puskesmas" class="form-control">
                            <option value="">Semua puskesmas</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3 col-sm-12">
                    <div class="form-group">
                        <label for="posyandu">Posyandu</label>
                        <select id="posyandu" name="id_posyandu" class="form-control">
                            <option value="">Semua posyandu</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3 col-sm-12">
                    <div class="form-group">
                        <label for="dari">Kunjungan dari tanggal</label>
                        <input type="date" name="dari" id="dari" class="form-control" value="{{ old('dari') }}">
                        <small class="form-text text-muted">Hanya menyaring sheet Per Kunjungan.</small>
                    </div>
                </div>
                <div class="col-md-3 col-sm-12">
                    <div class="form-group">
                        <label for="sampai">Sampai tanggal</label>
                        <input type="date" name="sampai" id="sampai" class="form-control" value="{{ old('sampai') }}">
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <span aria-hidden="true" class="fa fa-download mr-1"></span> Unduh Excel
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
@section('custom_scripts')
<script type="text/javascript">
    $(function() {
        $('#kec').on('change', function() {
            var id = $(this).val();
            $('#kel').html('<option value="">Semua kelurahan</option>');
            $('#puskesmas').html('<option value="">Semua puskesmas</option>');
            $('#posyandu').html('<option value="">Semua posyandu</option>');
            if (!id) return;

            $.ajax({
                url: '{{ url("admin/get-kel-dasar-anak") }}' + '/' + id,
                success: function(response) {
                    $.each(response, function(id, name) {
                        $('#kel').append(new Option(name, id));
                    });
                }
            });
            $.ajax({
                url: '{{ url("admin/get-puskesmas-dasar-anak") }}' + '/' + id,
                success: function(response) {
                    $.each(response, function(id, name) {
                        $('#puskesmas').append(new Option(name, id));
                    });
                }
            });
        });

        $('#puskesmas').on('change', function() {
            var id = $(this).val();
            $('#posyandu').html('<option value="">Semua posyandu</option>');
            if (!id) return;

            $.ajax({
                url: '{{ url("admin/get-posyandu-dasar-anak") }}' + '/' + id,
                success: function(response) {
                    $.each(response, function(id, name) {
                        $('#posyandu').append(new Option(name, id));
                    });
                }
            });
        });
    });
</script>
@endsection
```

- [ ] **Step 7: Menu sidebar (dua salinan)**

Di `resources/views/vendor/admin/layouts/partials/leftsidebar.blade.php`, setelah **setiap** baris
`<li><a href="{{route('admin.export.pd3i.index')}}" … >Laporan Kasus PD3I</a></li>` (ada dua: ±baris 72 dan ±175), tambahkan:

```blade
						<li><a href="{{route('admin.export.kesmas.index')}}" class="{{ request()->routeIs('admin.export.kesmas.*') ? 'active' : '' }}">Export Kesmas</a></li>
```

Verifikasi: `grep -c "admin.export.kesmas.index" resources/views/vendor/admin/layouts/partials/leftsidebar.blade.php` → `2`.

- [ ] **Step 8: Bersihkan cache, jalankan tes**

Run: `php artisan view:clear && php artisan route:clear && php artisan test tests/Feature/Kesmas/ExportKesmasTest.php`
Expected: PASS (8 tests)

- [ ] **Step 9: Cek di browser**

Sidebar → Export Data → Export Kesmas; pilih kecamatan → kelurahan terisi; Unduh → berkas `kesmas-<kel>-<Ymd>.xlsx` terbuka dengan 2 sheet; kolom NIK berformat teks (tidak jadi `6.47E+15`).

- [ ] **Step 10: Commit**

```bash
git add app/Exports/KesmasExport.php app/Exports/KesmasAnakSheet.php app/Exports/KesmasKunjunganSheet.php app/Http/Controllers/ExportKesmasController.php resources/views/admin/export/kesmas.blade.php routes/web.php resources/views/vendor/admin/layouts/partials/leftsidebar.blade.php tests/Feature/Kesmas/ExportKesmasTest.php
git commit -m "$(cat <<'EOF'
feat(kesmas): Export Kesmas dua sheet (per anak, per kunjungan) dengan filter wilayah & tanggal

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_011UJEVC5xw87p91CXS1BnFv
EOF
)"
```

---

### Task 9: Regresi penuh & catatan proyek

**Files:**
- Modify: `CLAUDE.md` (proyek `www/sirindu`) — satu seksi pendek

- [ ] **Step 1: Jalankan seluruh suite**

Run: `php artisan test`
Expected: semua PASS (sebelum fitur ini: 694 tes; kini ±740). Jika ada kegagalan di luar `tests/Feature/Kesmas`, itu regresi dari perubahan `AnakRepository`/`AdminController`/`show.blade.php` — perbaiki sebelum lanjut, jangan ubah tes lama.

- [ ] **Step 2: Cek pola Blade berbahaya di seluruh view yang disentuh**

Run: `grep -rnE "@section\('[a-z-]+'\)\S.*@endsection" resources/views/admin/anak resources/views/admin/export/kesmas.blade.php`
Expected: tidak ada keluaran.

Run: `grep -rn "required" resources/views/admin/anak/partials/`
Expected: tidak ada keluaran.

- [ ] **Step 3: Tambah catatan gotcha ke `CLAUDE.md` proyek**

Tambahkan di akhir `D:\apps\laragon\www\sirindu\CLAUDE.md`:

```markdown
### Field Kesmas: NULL = belum diisi, dan hanya field yang dikirim yang disentuh

Kolom Kesmas di `anak`/`data_anak` (spec `docs/superpowers/specs/2026-09-15-data-kesmas-design.md`)
sengaja **tanpa DEFAULT** — NULL berarti petugas belum mengisi, bukan "Tidak". Jangan
menambah `->default()` atau backfill 0 ke kolom ini: dasbor akan menampilkan cakupan palsu
untuk 15.000 anak lama. Boolean per anak memakai select tiga keadaan (`''`/`1`/`0`),
layanan per kunjungan memakai hidden+checkbox.

`AnakRepository::kolomKesmas()` hanya menulis field yang **ada di request** (`$request->has()`
di sini mendeteksi keberadaan field, nilainya tetap dibaca `boolean()`). Kalau menambah field
Kesmas baru: tambah rule-nya di `KesmasRules::anak()`/`kunjungan()` (daftar kolom diambil dari
`array_keys()` rule itu), opsinya di `config/kesmas.php`, kontrolnya di partial
`admin/anak/partials/form-*.blade.php` — dan pastikan tidak ada `required`/`min`/`max` di
dalam kartu collapse (dikunci `FormKesmasBladeTest`).
```

- [ ] **Step 4: Commit**

```bash
git add CLAUDE.md
git commit -m "$(cat <<'EOF'
docs(kesmas): catatan NULL=belum diisi dan cara menambah field Kesmas

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_011UJEVC5xw87p91CXS1BnFv
EOF
)"
```

- [ ] **Step 5: Laporkan ke pemilik produk**

Sampaikan: jumlah tes, apa yang sudah bisa dicoba di dev (Tambah/Edit Anak, Tambah Pengukuran, detail, Export Kesmas), dan bahwa prod butuh `git pull` + `php artisan migrate --force` + `view:clear` (cek `git log origin/main..HEAD` di prod dulu — prod pernah punya commit lokal sendiri). Dasbor Kesmas = Spec 2, belum dimulai.

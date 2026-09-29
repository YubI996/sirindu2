# Dasbor & Master Data SPM Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Superadmin bisa mendaftarkan kategori SPM bebas dengan sasaran per tahun dan capaian per triwulan, dan semua akun admin bisa melihat dasbor yang menunjukkan kategori mana yang aman dan mana yang tertinggal.

**Architecture:** Dua tabel (`spm_kategori` definisi + `spm_capaian` satu baris per kategori per tahun dengan kolom `tw1..tw4`). Seluruh aritmetika di dua value object murni tanpa DB (`App\Support\CapaianSpm` per kategori, `App\Support\RingkasanSpm` untuk agregat) supaya bisa diuji unit tanpa migrasi. Master data memakai pola `MasterDataPenyakitController` (DataTables server-side + modal Bootstrap 4); dasbor server-render penuh dengan Chart.js dari CDN.

**Tech Stack:** Laravel 12 · PHP 8.4 · MySQL 8 · Bootstrap 4 · jQuery · Yajra DataTables · SweetAlert2 · Chart.js (CDN jsdelivr) · PHPUnit

**Spec:** `docs/superpowers/specs/2026-09-29-dasbor-spm-design.md`

## Global Constraints

- **Bootstrap 4, bukan 5.** Atribut modal `data-toggle="modal"` / `data-dismiss="modal"` — `data-bs-*` tidak berfungsi di layout ini.
- **`@section` wajib berspasi:** `@section('title') Dasbor SPM @endsection`. `@section('x')isi@endsection` membuat Blade tidak mem-parse `@endsection`, section tak pernah ditutup, dan breadcrumb tampil kosong tanpa error.
- **Setelah mengubah blade, jalankan `php artisan view:clear`** sebelum menyimpulkan perubahan tak berefek — compiled view lama tetap disajikan.
- **`tw1..tw4` nullable tanpa DEFAULT.** NULL = triwulan belum dilaporkan, 0 = capaiannya benar-benar nol. Jangan menambah `->default()` atau backfill 0.
- **`CapaianSpm` dan `RingkasanSpm` tidak boleh memanggil `config()`, `now()`, atau apa pun dari container.** Unit test di proyek ini memakai `PHPUnit\Framework\TestCase` polos (lihat `tests/Unit/Support/KuantilTest.php`), jadi aplikasi Laravel tidak di-boot. Ambang dan "sekarang" masuk sebagai parameter.
- **Ambang, label, dan warna status hanya di `config/spm.php`** — satu sumber. Controller yang membacanya lalu meneruskannya ke value object.
- Decimal DB: `decimal(14,2)` untuk sasaran dan tiap triwulan.
- Master data: `module.role:superadmin`. Dasbor: grup `is_admin` (superadmin, admin legacy `type=1`, dan faskes).
- Font Barlow, warna utama Kemenkes green `oklch(0.48 0.14 145)` — pakai token `--st-*` yang sudah ada di view master data lain.
- Badge status memakai kelas yang sudah lolos kontras di view master data (`bg-success`, `bg-info`, `bg-warning`, `bg-danger`, `bg-secondary`). Jangan membuat badge teal baru.
- Test DB = `sirindu_testing` (dari `phpunit.xml`). **Jangan menjalankan dua proses tes bersamaan** — keduanya memakai database yang sama dan akan saling menghapus. Suite penuh >10 menit; selalu pakai `--filter`.
- MySQL lokal harus dinyalakan manual sebelum tes (`root`, password kosong).

## Review Focus

Lima hal yang tersirat di spec tapi tidak dijaga oleh tes mana pun kalau tidak sengaja ditambahkan. Tiap baris sudah punya tes di task yang memiliki kodenya:

1. **Mengosongkan kembali triwulan yang sudah terisi** — petugas salah ketik TW III lalu menghapusnya dan menyimpan. Kalau kunci `tw3` tidak dikirim ulang sebagai NULL, `updateOrCreate` mempertahankan nilai lama dan angka salah itu tidak bisa dihapus dari UI. → Task 4.
2. **`?tahun=` yang ngawur** (`abcd`, `1900`, `9999`, kosong) di master data maupun dasbor — harus jatuh ke tahun ini, bukan 500 atau tabel kosong tanpa penjelasan. → Task 3 & Task 6.
3. **Nama kategori berisi HTML** (`<script>`, `&`, `"`) — kolom aksi DataTables memakai `rawColumns`, jadi nama yang tidak di-escape bisa tereksekusi di halaman superadmin. → Task 3.
4. **Capaian melebihi sasaran** (kumulatif 1.120 dari sasaran 1.000) — angka tetap `112%`, lebar bar dipotong 100% supaya tidak meluber keluar kartu, dan rata-rata kota tidak ikut membengkak lewat pembulatan. → Task 2 & Task 6.
5. **Angka besar dan desimal** (sasaran `1234567.89`) — tersimpan utuh di `decimal(14,2)`, tampil dengan pemisah ribuan Indonesia, dan `1234567.895` tidak ditolak mentah-mentah sebagai galat validasi. → Task 4.

---

### Task 1: Migrasi & model

**Files:**
- Create: `database/migrations/2026_09_29_000001_create_spm_tables.php`
- Create: `app/Models/SpmKategori.php`
- Create: `app/Models/SpmCapaian.php`
- Test: `tests/Feature/Spm/SpmModelTest.php`

**Interfaces:**
- Consumes: —
- Produces: tabel `spm_kategori` (`id`, `nama`, `satuan`, `keterangan`, `urutan`, `is_active`, `deleted_at`, timestamps) dan `spm_capaian` (`id`, `id_kategori`, `tahun`, `sasaran`, `tw1`, `tw2`, `tw3`, `tw4`, `catatan`, timestamps, `unique(id_kategori, tahun)`); model `App\Models\SpmKategori` (SoftDeletes, relasi `capaian()`) dan `App\Models\SpmCapaian` (relasi `kategori()`).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Spm/SpmModelTest.php`:

```php
<?php

namespace Tests\Feature\Spm;

use App\Models\SpmCapaian;
use App\Models\SpmKategori;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpmModelTest extends TestCase
{
    use RefreshDatabase;

    private function kategori(string $nama = 'Pelayanan Kesehatan Balita'): SpmKategori
    {
        return SpmKategori::create(['nama' => $nama, 'satuan' => 'anak']);
    }

    public function test_triwulan_yang_belum_dilaporkan_tersimpan_null(): void
    {
        $kategori = $this->kategori();

        SpmCapaian::create([
            'id_kategori' => $kategori->id,
            'tahun'       => 2026,
            'sasaran'     => 1000,
            'tw1'         => 200,
        ]);

        $baris = SpmCapaian::first();

        $this->assertSame(200.0, $baris->tw1);
        $this->assertNull($baris->tw2, 'TW II yang tidak dikirim harus NULL, bukan 0');
        $this->assertNull($baris->tw3);
        $this->assertNull($baris->tw4);
    }

    public function test_nol_berbeda_dari_belum_dilaporkan(): void
    {
        $kategori = $this->kategori();

        SpmCapaian::create([
            'id_kategori' => $kategori->id,
            'tahun'       => 2026,
            'sasaran'     => 1000,
            'tw1'         => 0,
        ]);

        $baris = SpmCapaian::first();

        $this->assertSame(0.0, $baris->tw1, 'capaian nol harus tetap 0, bukan NULL');
        $this->assertNull($baris->tw2);
    }

    public function test_satu_kategori_satu_baris_per_tahun(): void
    {
        $kategori = $this->kategori();
        SpmCapaian::create(['id_kategori' => $kategori->id, 'tahun' => 2026, 'sasaran' => 1000]);

        $this->expectException(QueryException::class);
        SpmCapaian::create(['id_kategori' => $kategori->id, 'tahun' => 2026, 'sasaran' => 2000]);
    }

    public function test_kategori_yang_sama_boleh_punya_beberapa_tahun(): void
    {
        $kategori = $this->kategori();
        SpmCapaian::create(['id_kategori' => $kategori->id, 'tahun' => 2025, 'sasaran' => 900]);
        SpmCapaian::create(['id_kategori' => $kategori->id, 'tahun' => 2026, 'sasaran' => 1000]);

        $this->assertCount(2, $kategori->fresh()->capaian);
    }

    public function test_soft_delete_kategori_tidak_menghapus_angka_tahun_lalu(): void
    {
        $kategori = $this->kategori();
        SpmCapaian::create(['id_kategori' => $kategori->id, 'tahun' => 2025, 'sasaran' => 900, 'tw1' => 100]);

        $kategori->delete();

        $this->assertSoftDeleted('spm_kategori', ['id' => $kategori->id]);
        $this->assertDatabaseHas('spm_capaian', ['id_kategori' => $kategori->id, 'tahun' => 2025]);

        $kategori->restore();
        $this->assertCount(1, $kategori->fresh()->capaian);
    }

    public function test_sasaran_besar_dan_desimal_tersimpan_utuh(): void
    {
        $kategori = $this->kategori();

        SpmCapaian::create([
            'id_kategori' => $kategori->id,
            'tahun'       => 2026,
            'sasaran'     => 1234567.89,
            'tw1'         => 0.5,
        ]);

        $baris = SpmCapaian::first();

        $this->assertSame(1234567.89, $baris->sasaran);
        $this->assertSame(0.5, $baris->tw1);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SpmModelTest`
Expected: FAIL — `Class "App\Models\SpmKategori" not found`.

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_09_29_000001_create_spm_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dasbor & Master Data SPM — spec docs/superpowers/specs/2026-09-29-dasbor-spm-design.md §2.
 *
 * tw1..tw4 SENGAJA nullable tanpa DEFAULT: NULL = triwulan belum dilaporkan,
 * 0 = capaiannya benar-benar nol. Jangan menambah default atau backfill 0 —
 * dasbor akan melaporkan kegagalan program yang tidak pernah terjadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spm_kategori', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 200);
            $table->string('satuan', 50);
            $table->text('keterangan')->nullable();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index('urutan');
        });

        Schema::create('spm_capaian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_kategori')->constrained('spm_kategori')->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->decimal('sasaran', 14, 2);
            $table->decimal('tw1', 14, 2)->nullable();
            $table->decimal('tw2', 14, 2)->nullable();
            $table->decimal('tw3', 14, 2)->nullable();
            $table->decimal('tw4', 14, 2)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['id_kategori', 'tahun']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spm_capaian');
        Schema::dropIfExists('spm_kategori');
    }
};
```

Catatan: **tidak ada** unique pada `spm_kategori.nama`. Keunikan ditegakkan di validasi dengan `whereNull('deleted_at')` (Task 3) supaya nama kategori yang pernah dihapus masih bisa dipakai lagi.

- [ ] **Step 4: Write the models**

Create `app/Models/SpmKategori.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SpmKategori extends Model
{
    use SoftDeletes;

    protected $table = 'spm_kategori';

    protected $fillable = ['nama', 'satuan', 'keterangan', 'urutan', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'urutan'    => 'integer',
    ];

    public function capaian()
    {
        return $this->hasMany(SpmCapaian::class, 'id_kategori');
    }

    /** Baris angka untuk satu tahun, atau null bila tahun itu belum diisi. */
    public function capaianTahun(int $tahun): ?SpmCapaian
    {
        return $this->capaian()->where('tahun', $tahun)->first();
    }
}
```

Create `app/Models/SpmCapaian.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris = satu kategori × satu tahun. tw1..tw4 boleh NULL
 * (triwulan belum dilaporkan) — cast 'float' mempertahankan NULL.
 */
class SpmCapaian extends Model
{
    protected $table = 'spm_capaian';

    protected $fillable = ['id_kategori', 'tahun', 'sasaran', 'tw1', 'tw2', 'tw3', 'tw4', 'catatan'];

    protected $casts = [
        'tahun'   => 'integer',
        'sasaran' => 'float',
        'tw1'     => 'float',
        'tw2'     => 'float',
        'tw3'     => 'float',
        'tw4'     => 'float',
    ];

    public function kategori()
    {
        return $this->belongsTo(SpmKategori::class, 'id_kategori');
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=SpmModelTest`
Expected: PASS (7 tests).

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_09_29_000001_create_spm_tables.php app/Models/SpmKategori.php app/Models/SpmCapaian.php tests/Feature/Spm/SpmModelTest.php
git commit -m "feat(spm): tabel & model kategori SPM dengan capaian per triwulan"
```

---

### Task 2: Hitungan murni — `CapaianSpm`, `RingkasanSpm`, `config/spm.php`

**Files:**
- Create: `app/Support/CapaianSpm.php`
- Create: `app/Support/RingkasanSpm.php`
- Create: `config/spm.php`
- Test: `tests/Unit/Support/CapaianSpmTest.php`
- Test: `tests/Unit/Support/RingkasanSpmTest.php`

**Interfaces:**
- Consumes: — (murni PHP, tidak menyentuh model Task 1)
- Produces:
  - `CapaianSpm::dari(?float $sasaran, array $tw, int $tahun, array $ambang, ?CarbonImmutable $sekarang = null): self` — `$tw` adalah `[tw1, tw2, tw3, tw4]` (boleh null/string), `$ambang` adalah `['sesuai' => 0.90, 'tertinggal' => 0.60]`.
  - Method: `sasaran(): ?float` · `tw(int $n): ?float` · `twTerisi(): int` · `twKalender(): int` · `kumulatif(): ?float` · `persen(): ?float` · `prorata(): ?float` · `prorataTw(int $n): ?float` · `rasioLaju(): ?float` · `selisih(): ?float` · `twKosong(): array` · `laporanTertinggal(): bool` · `status(): string` · `kumulatifPerTw(): array`
  - Konstanta status: `CapaianSpm::STATUS_BELUM`, `STATUS_TANPA_SASARAN`, `STATUS_TERCAPAI`, `STATUS_SESUAI`, `STATUS_TERTINGGAL`, `STATUS_KRITIS`.
  - `RingkasanSpm::dari(array $daftarCapaianSpm): array` dengan kunci `jumlah_kategori`, `rata_rata`, `dihitung`, `tercapai`, `tertinggal`, `belum`, `tanpa_sasaran`, `per_status`.
  - `config('spm.ambang')`, `config('spm.status')`, `config('spm.tahun_min')`.

- [ ] **Step 1: Write the failing test for CapaianSpm**

Create `tests/Unit/Support/CapaianSpmTest.php`:

```php
<?php

namespace Tests\Unit\Support;

use App\Support\CapaianSpm;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class CapaianSpmTest extends TestCase
{
    private const AMBANG = ['sesuai' => 0.90, 'tertinggal' => 0.60];

    /** Bulan Juni 2026 = TW II berjalan. */
    private function juni2026(): CarbonImmutable
    {
        return CarbonImmutable::create(2026, 6, 15);
    }

    private function buat(?float $sasaran, array $tw, int $tahun = 2026, ?CarbonImmutable $sekarang = null): CapaianSpm
    {
        return CapaianSpm::dari($sasaran, $tw, $tahun, self::AMBANG, $sekarang ?? $this->juni2026());
    }

    public function test_kumulatif_menjumlahkan_triwulan_terisi_dan_mengabaikan_null(): void
    {
        $c = $this->buat(1000, [200, 150, null, null]);

        $this->assertSame(350.0, $c->kumulatif());
        $this->assertSame(2, $c->twTerisi());
        $this->assertSame(35.0, $c->persen());
        $this->assertSame(650.0, $c->selisih());
    }

    public function test_belum_ada_triwulan_terisi_berarti_belum_dilaporkan(): void
    {
        $c = $this->buat(1000, [null, null, null, null]);

        $this->assertNull($c->kumulatif());
        $this->assertNull($c->persen());
        $this->assertNull($c->selisih());
        $this->assertSame(0, $c->twTerisi());
        $this->assertSame(CapaianSpm::STATUS_BELUM, $c->status());
    }

    public function test_nol_adalah_laporan_yang_sah_bukan_kekosongan(): void
    {
        $c = $this->buat(1000, [0.0, null, null, null]);

        $this->assertSame(1, $c->twTerisi());
        $this->assertSame(0.0, $c->kumulatif());
        $this->assertSame(0.0, $c->persen());
        $this->assertSame(CapaianSpm::STATUS_KRITIS, $c->status());
    }

    public function test_sasaran_nol_tidak_membagi_nol(): void
    {
        $c = $this->buat(0.0, [10, null, null, null]);

        $this->assertNull($c->persen());
        $this->assertNull($c->prorata());
        $this->assertNull($c->rasioLaju());
        $this->assertSame(CapaianSpm::STATUS_TANPA_SASARAN, $c->status());
    }

    public function test_sasaran_null_diperlakukan_sebagai_tanpa_sasaran(): void
    {
        $c = $this->buat(null, [10, null, null, null]);

        $this->assertNull($c->persen());
        $this->assertSame(CapaianSpm::STATUS_TANPA_SASARAN, $c->status());
    }

    public function test_prorata_diukur_terhadap_triwulan_yang_dilaporkan(): void
    {
        // Lapor s.d. TW II: target 500 dari 1000, capaian 480 → 96% dari laju.
        $c = $this->buat(1000, [250, 230, null, null]);

        $this->assertSame(500.0, $c->prorata());
        $this->assertSame(0.96, round($c->rasioLaju(), 4));
        $this->assertSame(CapaianSpm::STATUS_SESUAI, $c->status());
        $this->assertSame(48.0, $c->persen());
    }

    public function test_persen_sama_bisa_berbeda_status_tergantung_triwulan(): void
    {
        $twII = $this->buat(1000, [250, 200, null, null]);          // 45% di TW II
        $twIV = $this->buat(1000, [150, 100, 100, 100]);            // 45% di TW IV

        $this->assertSame(45.0, $twII->persen());
        $this->assertSame(45.0, $twIV->persen());
        $this->assertSame(CapaianSpm::STATUS_SESUAI, $twII->status());
        $this->assertSame(CapaianSpm::STATUS_KRITIS, $twIV->status());
    }

    public function test_ambang_tertinggal_dan_kritis(): void
    {
        // Lapor s.d. TW II (prorata 500): 375 → rasio 0,75 = tertinggal.
        $tertinggal = $this->buat(1000, [200, 175, null, null]);
        // 250 → rasio 0,50 = kritis.
        $kritis = $this->buat(1000, [150, 100, null, null]);

        $this->assertSame(CapaianSpm::STATUS_TERTINGGAL, $tertinggal->status());
        $this->assertSame(CapaianSpm::STATUS_KRITIS, $kritis->status());
    }

    public function test_tercapai_menang_atas_laju(): void
    {
        // Sudah 100% padahal baru TW I — tetap 'tercapai', bukan 'sesuai'.
        $c = $this->buat(1000, [1000, null, null, null]);

        $this->assertSame(CapaianSpm::STATUS_TERCAPAI, $c->status());
    }

    public function test_capaian_melebihi_sasaran_dilaporkan_apa_adanya(): void
    {
        $c = $this->buat(1000, [600, 520, null, null]);

        $this->assertSame(1120.0, $c->kumulatif());
        $this->assertSame(112.0, $c->persen());
        $this->assertSame(-120.0, $c->selisih(), 'selisih negatif = melampaui sasaran');
        $this->assertSame(CapaianSpm::STATUS_TERCAPAI, $c->status());
    }

    public function test_laporan_tertinggal_saat_triwulan_kalender_lebih_maju(): void
    {
        $september = CarbonImmutable::create(2026, 9, 10); // TW III berjalan
        $c = $this->buat(1000, [250, 250, null, null], 2026, $september);

        $this->assertSame(3, $c->twKalender());
        $this->assertSame(2, $c->twTerisi());
        $this->assertTrue($c->laporanTertinggal());
        // Prorata TETAP terhadap TW II, jadi jangan dicap gagal:
        $this->assertSame(500.0, $c->prorata());
        $this->assertSame(CapaianSpm::STATUS_SESUAI, $c->status());
    }

    public function test_laporan_tidak_tertinggal_saat_triwulan_terakhir_sudah_masuk(): void
    {
        $c = $this->buat(1000, [250, 250, null, null]); // Juni = TW II, terisi s.d. TW II

        $this->assertFalse($c->laporanTertinggal());
    }

    public function test_lubang_di_tengah_terdeteksi(): void
    {
        $september = CarbonImmutable::create(2026, 9, 10);
        $c = $this->buat(1000, [200, null, 300, null], 2026, $september);

        $this->assertSame([2], $c->twKosong());
        $this->assertSame(3, $c->twTerisi());
        $this->assertSame(500.0, $c->kumulatif());
    }

    public function test_tanpa_lubang_daftar_kosong(): void
    {
        $c = $this->buat(1000, [200, 300, null, null]);

        $this->assertSame([], $c->twKosong());
    }

    public function test_tahun_lampau_memakai_triwulan_kalender_empat(): void
    {
        $c = $this->buat(1000, [200, 200, 200, null], 2025);

        $this->assertSame(4, $c->twKalender());
        $this->assertTrue($c->laporanTertinggal(), 'TW IV 2025 tidak pernah dilaporkan');
    }

    public function test_tahun_depan_belum_punya_triwulan_kalender(): void
    {
        $c = $this->buat(1000, [null, null, null, null], 2027);

        $this->assertSame(0, $c->twKalender());
        $this->assertFalse($c->laporanTertinggal());
        $this->assertSame(CapaianSpm::STATUS_BELUM, $c->status());
    }

    public function test_prorata_tw_selalu_target_penuh_untuk_grafik(): void
    {
        $c = $this->buat(1000, [250, null, null, null]);

        $this->assertSame([250.0, 500.0, 750.0, 1000.0], [
            $c->prorataTw(1), $c->prorataTw(2), $c->prorataTw(3), $c->prorataTw(4),
        ]);
    }

    public function test_kumulatif_per_tw_berhenti_di_triwulan_terakhir_terisi(): void
    {
        $c = $this->buat(1000, [200, 150, null, null]);

        $this->assertSame([200.0, 350.0, null, null], $c->kumulatifPerTw());
    }

    public function test_lubang_di_tengah_tidak_memutus_garis_kumulatif(): void
    {
        $september = CarbonImmutable::create(2026, 9, 10);
        $c = $this->buat(1000, [200, null, 300, null], 2026, $september);

        $this->assertSame([200.0, 200.0, 500.0, null], $c->kumulatifPerTw());
    }

    public function test_string_dari_database_diterima(): void
    {
        // Kolom decimal dari MySQL datang sebagai string "200.00".
        $c = $this->buat(1000, ['200.00', '', null, null]);

        $this->assertSame(200.0, $c->kumulatif());
        $this->assertSame(1, $c->twTerisi(), 'string kosong harus dianggap belum dilaporkan');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CapaianSpmTest`
Expected: FAIL — `Class "App\Support\CapaianSpm" not found`.

- [ ] **Step 3: Write `CapaianSpm`**

Create `app/Support/CapaianSpm.php`:

```php
<?php
// app/Support/CapaianSpm.php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Ketercapaian satu kategori SPM untuk satu tahun.
 * Spec: docs/superpowers/specs/2026-09-29-dasbor-spm-design.md §3.
 *
 * MURNI PHP — tanpa DB, tanpa config(), tanpa now(). Unit test proyek ini
 * memakai PHPUnit\Framework\TestCase polos (aplikasi tidak di-boot), jadi
 * ambang dan "sekarang" WAJIB masuk sebagai parameter.
 *
 * Dua hal yang membuat prorata jujur, jangan disederhanakan:
 *  - prorata diukur terhadap triwulan yang DILAPORKAN (twTerisi), bukan
 *    triwulan kalender — keterlambatan laporan bukan kegagalan program;
 *  - keterlambatan itu penanda terpisah (laporanTertinggal / twKosong).
 */
final class CapaianSpm
{
    public const STATUS_BELUM         = 'belum';
    public const STATUS_TANPA_SASARAN = 'tanpa_sasaran';
    public const STATUS_TERCAPAI      = 'tercapai';
    public const STATUS_SESUAI        = 'sesuai';
    public const STATUS_TERTINGGAL    = 'tertinggal';
    public const STATUS_KRITIS        = 'kritis';

    /** @param array<int,float|null> $tw nilai TW I..IV pada indeks 0..3 */
    private function __construct(
        private readonly ?float $sasaran,
        private readonly array $tw,
        private readonly int $twKalender,
        private readonly array $ambang,
    ) {
    }

    /**
     * @param array<int,float|string|null> $tw [tw1, tw2, tw3, tw4]
     * @param array{sesuai: float, tertinggal: float} $ambang dari config('spm.ambang')
     */
    public static function dari(
        ?float $sasaran,
        array $tw,
        int $tahun,
        array $ambang,
        ?CarbonImmutable $sekarang = null,
    ): self {
        $sekarang ??= CarbonImmutable::now();

        $twKalender = match (true) {
            $tahun < $sekarang->year => 4,
            $tahun > $sekarang->year => 0,
            default                  => (int) ceil($sekarang->month / 3),
        };

        $nilai = [];
        for ($i = 0; $i < 4; $i++) {
            $v = $tw[$i] ?? null;
            $nilai[$i] = ($v === null || $v === '') ? null : (float) $v;
        }

        return new self($sasaran === null ? null : (float) $sasaran, $nilai, $twKalender, $ambang);
    }

    public function sasaran(): ?float
    {
        return $this->sasaran;
    }

    /** Nilai TW ke-n (1..4). */
    public function tw(int $n): ?float
    {
        return $this->tw[$n - 1] ?? null;
    }

    /** Triwulan terakhir yang sudah dilaporkan; 0 bila belum ada. */
    public function twTerisi(): int
    {
        for ($i = 3; $i >= 0; $i--) {
            if ($this->tw[$i] !== null) {
                return $i + 1;
            }
        }

        return 0;
    }

    /** Triwulan menurut kalender: tahun lampau 4, tahun depan 0. */
    public function twKalender(): int
    {
        return $this->twKalender;
    }

    public function kumulatif(): ?float
    {
        $terisi = array_filter($this->tw, fn ($v) => $v !== null);

        return $terisi === [] ? null : (float) array_sum($terisi);
    }

    public function persen(): ?float
    {
        $kumulatif = $this->kumulatif();

        if ($kumulatif === null || !$this->punyaSasaran()) {
            return null;
        }

        return $kumulatif / $this->sasaran * 100;
    }

    /** Target sampai triwulan yang DILAPORKAN. */
    public function prorata(): ?float
    {
        return $this->punyaSasaran() ? $this->sasaran * $this->twTerisi() / 4 : null;
    }

    /** Target penuh TW ke-n (1..4) — garis target di grafik. */
    public function prorataTw(int $n): ?float
    {
        return $this->punyaSasaran() ? $this->sasaran * $n / 4 : null;
    }

    public function rasioLaju(): ?float
    {
        $prorata   = $this->prorata();
        $kumulatif = $this->kumulatif();

        if ($prorata === null || $prorata <= 0 || $kumulatif === null) {
            return null;
        }

        return $kumulatif / $prorata;
    }

    /** Sisa menuju sasaran tahunan; negatif berarti melampaui. */
    public function selisih(): ?float
    {
        $kumulatif = $this->kumulatif();

        if ($kumulatif === null || $this->sasaran === null) {
            return null;
        }

        return $this->sasaran - $kumulatif;
    }

    /** @return array<int,int> nomor TW (1..4) yang kosong DI BAWAH twTerisi. */
    public function twKosong(): array
    {
        $kosong = [];

        for ($i = 0; $i < $this->twTerisi() - 1; $i++) {
            if ($this->tw[$i] === null) {
                $kosong[] = $i + 1;
            }
        }

        return $kosong;
    }

    public function laporanTertinggal(): bool
    {
        return $this->twKalender > $this->twTerisi();
    }

    public function status(): string
    {
        if ($this->twTerisi() === 0) {
            return self::STATUS_BELUM;
        }

        if (!$this->punyaSasaran()) {
            return self::STATUS_TANPA_SASARAN;
        }

        if ($this->persen() >= 100) {
            return self::STATUS_TERCAPAI;
        }

        $rasio = $this->rasioLaju();

        if ($rasio >= $this->ambang['sesuai']) {
            return self::STATUS_SESUAI;
        }

        if ($rasio >= $this->ambang['tertinggal']) {
            return self::STATUS_TERTINGGAL;
        }

        return self::STATUS_KRITIS;
    }

    /**
     * Kumulatif di tiap TW untuk grafik garis; null setelah twTerisi supaya
     * triwulan yang belum dilaporkan tidak digambar sebagai penurunan ke 0.
     *
     * @return array<int,float|null>
     */
    public function kumulatifPerTw(): array
    {
        $out    = [];
        $jalan  = 0.0;
        $batas  = $this->twTerisi();

        for ($i = 0; $i < 4; $i++) {
            if ($i + 1 > $batas) {
                $out[] = null;
                continue;
            }

            $jalan += $this->tw[$i] ?? 0.0;
            $out[]  = $jalan;
        }

        return $out;
    }

    private function punyaSasaran(): bool
    {
        return $this->sasaran !== null && $this->sasaran > 0;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=CapaianSpmTest`
Expected: PASS (20 tests).

- [ ] **Step 5: Write the failing test for `RingkasanSpm`**

Create `tests/Unit/Support/RingkasanSpmTest.php`:

```php
<?php

namespace Tests\Unit\Support;

use App\Support\CapaianSpm;
use App\Support\RingkasanSpm;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class RingkasanSpmTest extends TestCase
{
    private const AMBANG = ['sesuai' => 0.90, 'tertinggal' => 0.60];

    private function capaian(?float $sasaran, array $tw): CapaianSpm
    {
        return CapaianSpm::dari($sasaran, $tw, 2026, self::AMBANG, CarbonImmutable::create(2026, 6, 15));
    }

    public function test_rata_rata_tidak_dibobot_dan_hanya_yang_punya_persen(): void
    {
        $r = RingkasanSpm::dari([
            $this->capaian(1000, [400, null, null, null]),   // 40 %
            $this->capaian(10, [6, null, null, null]),       // 60 %
            $this->capaian(1000, [null, null, null, null]),  // belum — tidak ikut
        ]);

        $this->assertSame(3, $r['jumlah_kategori']);
        $this->assertSame(2, $r['dihitung']);
        $this->assertSame(50.0, $r['rata_rata'], 'rata-rata aritmetik 40 & 60, bukan (406/1010)');
        $this->assertSame(1, $r['belum']);
    }

    public function test_kategori_belum_dilaporkan_tidak_dihitung_nol(): void
    {
        $r = RingkasanSpm::dari([
            $this->capaian(1000, [900, null, null, null]),   // 90 %
            $this->capaian(1000, [null, null, null, null]),  // belum
        ]);

        $this->assertSame(90.0, $r['rata_rata'], 'yang belum dilaporkan tidak boleh menarik rata-rata ke 45%');
    }

    public function test_tanpa_sasaran_tidak_masuk_tercapai_maupun_tertinggal(): void
    {
        $r = RingkasanSpm::dari([
            $this->capaian(0.0, [10, null, null, null]),
        ]);

        $this->assertSame(1, $r['tanpa_sasaran']);
        $this->assertSame(0, $r['tercapai']);
        $this->assertSame(0, $r['tertinggal']);
        $this->assertNull($r['rata_rata']);
        $this->assertSame(0, $r['dihitung']);
    }

    public function test_tertinggal_menggabungkan_tertinggal_dan_kritis(): void
    {
        $r = RingkasanSpm::dari([
            $this->capaian(1000, [200, 175, null, null]),  // tertinggal (0,75)
            $this->capaian(1000, [150, 100, null, null]),  // kritis (0,50)
            $this->capaian(1000, [1000, null, null, null]) // tercapai
        ]);

        $this->assertSame(2, $r['tertinggal']);
        $this->assertSame(1, $r['tercapai']);
        $this->assertSame(1, $r['per_status'][CapaianSpm::STATUS_KRITIS]);
    }

    public function test_daftar_kosong_aman(): void
    {
        $r = RingkasanSpm::dari([]);

        $this->assertSame(0, $r['jumlah_kategori']);
        $this->assertNull($r['rata_rata']);
        $this->assertSame(0, $r['tercapai']);
    }
}
```

- [ ] **Step 6: Run test to verify it fails**

Run: `php artisan test --filter=RingkasanSpmTest`
Expected: FAIL — `Class "App\Support\RingkasanSpm" not found`.

- [ ] **Step 7: Write `RingkasanSpm` and `config/spm.php`**

Create `app/Support/RingkasanSpm.php`:

```php
<?php
// app/Support/RingkasanSpm.php

namespace App\Support;

/**
 * Agregat lima kartu dasbor SPM dari sekumpulan CapaianSpm.
 * Spec: docs/superpowers/specs/2026-09-29-dasbor-spm-design.md §5.
 *
 * Rata-rata = rata-rata ARITMETIK persen antar kategori, tanpa pembobotan —
 * bukan Σkumulatif ÷ Σsasaran, karena satuan antar kategori berbeda
 * (1.000 orang + 40 posyandu bukan 1.040 apa pun). Kategori yang belum
 * dilaporkan atau tanpa sasaran TIDAK ikut sebagai 0.
 */
final class RingkasanSpm
{
    /**
     * @param array<int,CapaianSpm> $daftar
     * @return array{jumlah_kategori:int, rata_rata:?float, dihitung:int, tercapai:int, tertinggal:int, belum:int, tanpa_sasaran:int, per_status:array<string,int>}
     */
    public static function dari(array $daftar): array
    {
        $perStatus = [
            CapaianSpm::STATUS_BELUM         => 0,
            CapaianSpm::STATUS_TANPA_SASARAN => 0,
            CapaianSpm::STATUS_TERCAPAI      => 0,
            CapaianSpm::STATUS_SESUAI        => 0,
            CapaianSpm::STATUS_TERTINGGAL    => 0,
            CapaianSpm::STATUS_KRITIS        => 0,
        ];

        $persen = [];

        foreach ($daftar as $capaian) {
            $perStatus[$capaian->status()]++;

            if ($capaian->persen() !== null) {
                $persen[] = $capaian->persen();
            }
        }

        return [
            'jumlah_kategori' => count($daftar),
            'rata_rata'       => $persen === [] ? null : array_sum($persen) / count($persen),
            'dihitung'        => count($persen),
            'tercapai'        => $perStatus[CapaianSpm::STATUS_TERCAPAI],
            'tertinggal'      => $perStatus[CapaianSpm::STATUS_TERTINGGAL] + $perStatus[CapaianSpm::STATUS_KRITIS],
            'belum'           => $perStatus[CapaianSpm::STATUS_BELUM],
            'tanpa_sasaran'   => $perStatus[CapaianSpm::STATUS_TANPA_SASARAN],
            'per_status'      => $perStatus,
        ];
    }
}
```

Create `config/spm.php`:

```php
<?php

/*
 * Dasbor & Master Data SPM — SATU sumber ambang, label, dan warna status.
 * Spec: docs/superpowers/specs/2026-09-29-dasbor-spm-design.md §3.
 *
 * Ambang dibandingkan terhadap rasioLaju (kumulatif ÷ prorata), BUKAN persen:
 * 45 % di TW II sehat, 45 % di TW IV kritis.
 */
return [
    'ambang' => [
        'sesuai'     => 0.90,
        'tertinggal' => 0.60,
    ],

    'status' => [
        'belum'         => ['label' => 'Belum dilaporkan', 'badge' => 'bg-secondary', 'warna' => '#94a3b8'],
        'tanpa_sasaran' => ['label' => 'Tanpa sasaran',    'badge' => 'bg-secondary', 'warna' => '#cbd5e1'],
        'tercapai'      => ['label' => 'Tercapai',         'badge' => 'bg-success',   'warna' => '#047857'],
        'sesuai'        => ['label' => 'Sesuai laju',      'badge' => 'bg-info',      'warna' => '#1d4ed8'],
        'tertinggal'    => ['label' => 'Tertinggal',       'badge' => 'bg-warning',   'warna' => '#b45309'],
        'kritis'        => ['label' => 'Kritis',           'badge' => 'bg-danger',    'warna' => '#b91c1c'],
    ],

    'tahun_min' => 2020,
];
```

- [ ] **Step 8: Run both unit tests**

Run: `php artisan test --filter="CapaianSpmTest|RingkasanSpmTest"`
Expected: PASS (25 tests).

- [ ] **Step 9: Commit**

```bash
git add app/Support/CapaianSpm.php app/Support/RingkasanSpm.php config/spm.php tests/Unit/Support/CapaianSpmTest.php tests/Unit/Support/RingkasanSpmTest.php
git commit -m "feat(spm): hitungan ketercapaian & ringkasan sebagai value object murni"
```

---

### Task 3: Master data — rute & CRUD kategori

**Files:**
- Create: `app/Http/Controllers/MasterDataSpmController.php`
- Modify: `routes/web.php` (setelah blok `master-data/penduduk`, ±baris 243)
- Test: `tests/Feature/Spm/MasterDataSpmTest.php`

**Interfaces:**
- Consumes: `App\Models\SpmKategori`, `App\Models\SpmCapaian` (Task 1); `App\Support\CapaianSpm` + `config('spm.*')` (Task 2).
- Produces: rute bernama `admin.masterdata.spm.index`, `admin.masterdata.spm.getData`, `admin.masterdata.spm.store`, `admin.masterdata.spm.update`, `admin.masterdata.spm.toggleStatus`, `admin.masterdata.spm.destroy`, `admin.masterdata.spm.restore`. Controller menyediakan `protected function tahunTervalidasi(Request $request): int` yang dipakai `index` dan `getData`; `getData` mengembalikan kolom DataTables `id`, `nama`, `satuan`, `sasaran`, `tw1`–`tw4`, `kumulatif`, `persen_badge`, `status_badge`, `catatan`, `is_active`, `action`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Spm/MasterDataSpmTest.php`:

```php
<?php

namespace Tests\Feature\Spm;

use App\Models\SpmCapaian;
use App\Models\SpmKategori;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataSpmTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $adminBiasa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['type' => 0]);
        $this->adminBiasa = User::factory()->create(['type' => 1]);
    }

    public function test_superadmin_bisa_membuka_halaman(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('admin.masterdata.spm.index'))
            ->assertStatus(200);
    }

    public function test_admin_biasa_ditolak(): void
    {
        $this->actingAs($this->adminBiasa)
            ->get(route('admin.masterdata.spm.index'))
            ->assertStatus(403);
    }

    public function test_tamu_dialihkan_ke_login(): void
    {
        $this->get(route('admin.masterdata.spm.index'))->assertRedirect(route('login'));
    }

    public function test_superadmin_bisa_menambah_kategori(): void
    {
        $this->actingAs($this->superAdmin)
            ->postJson(route('admin.masterdata.spm.store'), [
                'nama'       => 'Pelayanan Kesehatan Ibu Hamil',
                'satuan'     => 'orang',
                'keterangan' => 'Indikator SPM 1',
                'urutan'     => 1,
                'is_active'  => 1,
            ])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('spm_kategori', [
            'nama'   => 'Pelayanan Kesehatan Ibu Hamil',
            'satuan' => 'orang',
        ]);
    }

    public function test_nama_wajib_dan_tidak_boleh_ganda(): void
    {
        SpmKategori::create(['nama' => 'Pelayanan TB', 'satuan' => 'orang']);

        $this->actingAs($this->superAdmin)
            ->postJson(route('admin.masterdata.spm.store'), ['satuan' => 'orang'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nama');

        $this->actingAs($this->superAdmin)
            ->postJson(route('admin.masterdata.spm.store'), ['nama' => 'Pelayanan TB', 'satuan' => 'orang'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nama');
    }

    public function test_nama_kategori_yang_sudah_dihapus_boleh_dipakai_lagi(): void
    {
        $lama = SpmKategori::create(['nama' => 'Pelayanan ODGJ', 'satuan' => 'orang']);
        $lama->delete();

        $this->actingAs($this->superAdmin)
            ->postJson(route('admin.masterdata.spm.store'), ['nama' => 'Pelayanan ODGJ', 'satuan' => 'orang'])
            ->assertStatus(200);

        $this->assertSame(2, SpmKategori::withTrashed()->where('nama', 'Pelayanan ODGJ')->count());
    }

    public function test_superadmin_bisa_mengubah_kategori(): void
    {
        $kategori = SpmKategori::create(['nama' => 'Pelayanan HIV', 'satuan' => 'orang']);

        $this->actingAs($this->superAdmin)
            ->putJson(route('admin.masterdata.spm.update', $kategori->id), [
                'nama'   => 'Pelayanan Kesehatan Orang dengan Risiko HIV',
                'satuan' => 'orang',
                'urutan' => 12,
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('spm_kategori', [
            'id'     => $kategori->id,
            'nama'   => 'Pelayanan Kesehatan Orang dengan Risiko HIV',
            'urutan' => 12,
        ]);
    }

    public function test_update_boleh_memakai_namanya_sendiri(): void
    {
        $kategori = SpmKategori::create(['nama' => 'Pelayanan DM', 'satuan' => 'orang']);

        $this->actingAs($this->superAdmin)
            ->putJson(route('admin.masterdata.spm.update', $kategori->id), [
                'nama'   => 'Pelayanan DM',
                'satuan' => 'pasien',
            ])
            ->assertStatus(200);
    }

    public function test_toggle_hapus_dan_pulihkan(): void
    {
        $kategori = SpmKategori::create(['nama' => 'Pelayanan Lansia', 'satuan' => 'orang']);

        $this->actingAs($this->superAdmin)
            ->patchJson(route('admin.masterdata.spm.toggleStatus', $kategori->id))
            ->assertStatus(200);
        $this->assertFalse($kategori->fresh()->is_active);

        $this->actingAs($this->superAdmin)
            ->deleteJson(route('admin.masterdata.spm.destroy', $kategori->id))
            ->assertStatus(200);
        $this->assertSoftDeleted('spm_kategori', ['id' => $kategori->id]);

        $this->actingAs($this->superAdmin)
            ->patchJson(route('admin.masterdata.spm.restore', $kategori->id))
            ->assertStatus(200);
        $this->assertNotNull(SpmKategori::find($kategori->id));
    }

    public function test_get_data_menampilkan_angka_tahun_terpilih(): void
    {
        $kategori = SpmKategori::create(['nama' => 'Pelayanan Balita', 'satuan' => 'anak']);
        SpmCapaian::create(['id_kategori' => $kategori->id, 'tahun' => 2025, 'sasaran' => 800, 'tw1' => 800]);
        SpmCapaian::create(['id_kategori' => $kategori->id, 'tahun' => 2026, 'sasaran' => 1000, 'tw1' => 200]);

        $data = $this->actingAs($this->superAdmin)
            ->getJson(route('admin.masterdata.spm.getData', ['tahun' => 2026]))
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('1000.00', (string) $data[0]['sasaran']);
        $this->assertSame(200.0, (float) $data[0]['kumulatif']);
    }

    public function test_get_data_tetap_menampilkan_kategori_tanpa_angka_tahun_itu(): void
    {
        SpmKategori::create(['nama' => 'Pelayanan Hipertensi', 'satuan' => 'orang']);

        $data = $this->actingAs($this->superAdmin)
            ->getJson(route('admin.masterdata.spm.getData', ['tahun' => 2026]))
            ->json('data');

        $this->assertCount(1, $data);
        $this->assertNull($data[0]['sasaran']);
        $this->assertStringContainsString('Belum dilaporkan', $data[0]['status_badge']);
    }

    public function test_tahun_ngawur_jatuh_ke_tahun_ini_tanpa_error(): void
    {
        SpmKategori::create(['nama' => 'Pelayanan TB', 'satuan' => 'orang']);

        foreach (['abcd', '1900', '9999', ''] as $tahun) {
            $this->actingAs($this->superAdmin)
                ->getJson(route('admin.masterdata.spm.getData', ['tahun' => $tahun]))
                ->assertStatus(200)
                ->assertJsonCount(1, 'data');
        }
    }

    public function test_nama_kategori_berisi_html_tidak_lolos_mentah(): void
    {
        SpmKategori::create(['nama' => '<script>alert(1)</script> & "kutip"', 'satuan' => 'orang']);

        $data = $this->actingAs($this->superAdmin)
            ->getJson(route('admin.masterdata.spm.getData', ['tahun' => 2026]))
            ->json('data');

        $this->assertStringNotContainsString('<script>', $data[0]['nama']);
        $this->assertStringContainsString('&lt;script&gt;', $data[0]['nama']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MasterDataSpmTest`
Expected: FAIL — `Route [admin.masterdata.spm.index] not defined.`

- [ ] **Step 3: Add the routes**

In `routes/web.php`, immediately after the closing `});` of the `master-data/penduduk` group (±line 243), insert:

```php
    Route::prefix('master-data/spm')->group(function () {
        Route::get('/', [App\Http\Controllers\MasterDataSpmController::class, 'index'])
             ->name('admin.masterdata.spm.index');
        Route::get('get-data', [App\Http\Controllers\MasterDataSpmController::class, 'getData'])
             ->name('admin.masterdata.spm.getData');
        Route::post('store', [App\Http\Controllers\MasterDataSpmController::class, 'store'])
             ->name('admin.masterdata.spm.store');
        Route::put('update/{id}', [App\Http\Controllers\MasterDataSpmController::class, 'update'])
             ->name('admin.masterdata.spm.update');
        Route::put('angka/{id}', [App\Http\Controllers\MasterDataSpmController::class, 'angka'])
             ->name('admin.masterdata.spm.angka');
        Route::patch('toggle-status/{id}', [App\Http\Controllers\MasterDataSpmController::class, 'toggleStatus'])
             ->name('admin.masterdata.spm.toggleStatus');
        Route::delete('destroy/{id}', [App\Http\Controllers\MasterDataSpmController::class, 'destroy'])
             ->name('admin.masterdata.spm.destroy');
        Route::patch('restore/{id}', [App\Http\Controllers\MasterDataSpmController::class, 'restore'])
             ->name('admin.masterdata.spm.restore');
    });
```

(`angka` is wired here but implemented in Task 4 — the route must exist so both tasks share one routes edit.)

- [ ] **Step 4: Write the controller**

Create `app/Http/Controllers/MasterDataSpmController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\SpmCapaian;
use App\Models\SpmKategori;
use App\Support\CapaianSpm;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;

/**
 * Master data SPM — spec docs/superpowers/specs/2026-09-29-dasbor-spm-design.md §4.
 * Definisi kategori (jarang berubah) dan angka per tahun (4× setahun) diedit
 * lewat dua aksi terpisah karena umurnya berbeda.
 */
class MasterDataSpmController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('module.role:superadmin');
    }

    public function index(Request $request)
    {
        return view('admin.master-data.spm.index', [
            'tahun'     => $this->tahunTervalidasi($request),
            'tahunOpsi' => $this->tahunOpsi(),
        ]);
    }

    public function getData(Request $request)
    {
        $tahun  = $this->tahunTervalidasi($request);
        $ambang = config('spm.ambang');
        $status = config('spm.status');

        $query = SpmKategori::withTrashed()
            ->leftJoin('spm_capaian', function ($join) use ($tahun) {
                $join->on('spm_capaian.id_kategori', '=', 'spm_kategori.id')
                     ->where('spm_capaian.tahun', '=', $tahun);
            })
            ->select([
                'spm_kategori.*',
                'spm_capaian.sasaran',
                'spm_capaian.tw1',
                'spm_capaian.tw2',
                'spm_capaian.tw3',
                'spm_capaian.tw4',
                'spm_capaian.catatan',
            ]);

        $hitung = fn ($row) => CapaianSpm::dari(
            $row->sasaran,
            [$row->tw1, $row->tw2, $row->tw3, $row->tw4],
            $tahun,
            $ambang,
        );

        return DataTables::of($query)
            ->editColumn('nama', fn ($row) => e($row->nama))
            ->editColumn('satuan', fn ($row) => e($row->satuan))
            ->addColumn('kumulatif', fn ($row) => $hitung($row)->kumulatif())
            ->addColumn('persen_badge', function ($row) use ($hitung) {
                $persen = $hitung($row)->persen();

                return $persen === null
                    ? '<span class="text-muted">—</span>'
                    : '<strong>' . number_format($persen, 1, ',', '.') . '%</strong>';
            })
            ->addColumn('status_badge', function ($row) use ($hitung, $status) {
                $capaian = $hitung($row);
                $meta    = $status[$capaian->status()];
                $badge   = '<span class="badge ' . $meta['badge'] . '">' . e($meta['label']) . '</span>';

                if ($capaian->laporanTertinggal() && $capaian->twKalender() > 0) {
                    $badge .= ' <span class="badge bg-secondary">TW ' . $capaian->twKalender() . ' belum masuk</span>';
                }

                return $badge;
            })
            ->addColumn('action', function ($row) {
                if ($row->trashed()) {
                    return '<div class="btn-group"><button class="btn btn-sm btn-success btn-restore" data-id="' . $row->id . '" title="Pulihkan"><i class="fa fa-undo"></i></button></div>';
                }

                return '<div class="btn-group">'
                    . '<button class="btn btn-sm btn-primary btn-angka" data-id="' . $row->id . '" title="Isi angka"><i class="fa fa-calculator"></i></button>'
                    . '<button class="btn btn-sm btn-warning btn-edit" data-id="' . $row->id . '" title="Edit kategori"><i class="fa fa-edit"></i></button>'
                    . '<button class="btn btn-sm btn-info btn-toggle" data-id="' . $row->id . '" title="Aktif / nonaktif"><i class="fa fa-sync-alt"></i></button>'
                    . '<button class="btn btn-sm btn-danger btn-delete" data-id="' . $row->id . '" title="Hapus"><i class="fa fa-trash"></i></button>'
                    . '</div>';
            })
            ->rawColumns(['persen_badge', 'status_badge', 'action'])
            ->make(true);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->aturanKategori());
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['urutan'] = (int) $request->input('urutan', 0);

        SpmKategori::create($validated);

        return response()->json(['success' => true, 'message' => 'Kategori SPM berhasil ditambahkan']);
    }

    public function update(Request $request, $id)
    {
        $kategori = SpmKategori::findOrFail($id);

        $validated = $request->validate($this->aturanKategori($id));
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['urutan'] = (int) $request->input('urutan', 0);

        $kategori->update($validated);

        return response()->json(['success' => true, 'message' => 'Kategori SPM berhasil diperbarui']);
    }

    public function toggleStatus($id)
    {
        $kategori = SpmKategori::findOrFail($id);
        $kategori->update(['is_active' => !$kategori->is_active]);

        return response()->json([
            'success' => true,
            'message' => $kategori->is_active ? 'Kategori diaktifkan' : 'Kategori dinonaktifkan',
        ]);
    }

    public function destroy($id)
    {
        SpmKategori::findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kategori dihapus. Angka tahun-tahun sebelumnya tetap tersimpan.',
        ]);
    }

    public function restore($id)
    {
        SpmKategori::withTrashed()->findOrFail($id)->restore();

        return response()->json(['success' => true, 'message' => 'Kategori dipulihkan']);
    }

    /** Keunikan nama mengabaikan baris terhapus supaya namanya bisa dipakai lagi. */
    private function aturanKategori($id = null): array
    {
        return [
            'nama' => [
                'required', 'string', 'max:200',
                Rule::unique('spm_kategori', 'nama')->whereNull('deleted_at')->ignore($id),
            ],
            'satuan'     => ['required', 'string', 'max:50'],
            'keterangan' => ['nullable', 'string'],
            'urutan'     => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active'  => ['nullable', 'boolean'],
        ];
    }

    /**
     * Tahun dari request, dijatuhkan ke tahun ini bila bukan angka atau di luar
     * rentang — halaman master data tidak boleh 500 karena query string iseng.
     */
    protected function tahunTervalidasi(Request $request): int
    {
        $tahun = $request->input('tahun');
        $min   = (int) config('spm.tahun_min');
        $max   = (int) now()->year + 1;

        if (!is_numeric($tahun)) {
            return (int) now()->year;
        }

        $tahun = (int) $tahun;

        return ($tahun < $min || $tahun > $max) ? (int) now()->year : $tahun;
    }

    /** Tahun yang punya data + tahun ini, terbaru dulu. */
    protected function tahunOpsi(): array
    {
        $tahun = SpmCapaian::query()->distinct()->pluck('tahun')->map(fn ($t) => (int) $t)->all();
        $tahun[] = (int) now()->year;

        $tahun = array_values(array_unique($tahun));
        rsort($tahun);

        return $tahun;
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=MasterDataSpmTest`
Expected: all tests except `test_superadmin_bisa_membuka_halaman` PASS; that one FAILS with `View [admin.master-data.spm.index] not found` (the view lands in Task 5).

To keep this task green on its own, temporarily assert only the JSON endpoints: mark the index test skipped with a note, then unskip in Task 5.

```php
    public function test_superadmin_bisa_membuka_halaman(): void
    {
        $this->markTestSkipped('View dibuat di Task 5 — unskip di sana.');
    }
```

Run again: `php artisan test --filter=MasterDataSpmTest`
Expected: PASS (12 passed, 1 skipped).

- [ ] **Step 6: Commit**

```bash
git add routes/web.php app/Http/Controllers/MasterDataSpmController.php tests/Feature/Spm/MasterDataSpmTest.php
git commit -m "feat(spm): CRUD kategori SPM di master data (superadmin)"
```

---

### Task 4: Master data — simpan angka per tahun

**Files:**
- Modify: `app/Http/Controllers/MasterDataSpmController.php` (add `angka()`)
- Test: `tests/Feature/Spm/SimpanAngkaSpmTest.php`

**Interfaces:**
- Consumes: rute `admin.masterdata.spm.angka` (Task 3), model `SpmCapaian` (Task 1).
- Produces: `MasterDataSpmController::angka(Request $request, $id)` — upsert satu baris `spm_capaian` per `(id_kategori, tahun)`; keempat kunci `tw1..tw4` selalu dikirim ulang, kosong berarti NULL.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Spm/SimpanAngkaSpmTest.php`:

```php
<?php

namespace Tests\Feature\Spm;

use App\Models\SpmCapaian;
use App\Models\SpmKategori;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimpanAngkaSpmTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected SpmKategori $kategori;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['type' => 0]);
        $this->kategori   = SpmKategori::create(['nama' => 'Pelayanan Balita', 'satuan' => 'anak']);
    }

    /** Payload seperti form aslinya: keempat kotak TW selalu terkirim, yang kosong berupa ''. */
    private function payload(array $ganti = []): array
    {
        return array_merge([
            'tahun'   => 2026,
            'sasaran' => 1000,
            'tw1'     => '200',
            'tw2'     => '',
            'tw3'     => '',
            'tw4'     => '',
            'catatan' => '',
        ], $ganti);
    }

    public function test_menyimpan_angka_untuk_satu_tahun(): void
    {
        $this->actingAs($this->superAdmin)
            ->putJson(route('admin.masterdata.spm.angka', $this->kategori->id), $this->payload())
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $baris = SpmCapaian::first();

        $this->assertSame(2026, $baris->tahun);
        $this->assertSame(1000.0, $baris->sasaran);
        $this->assertSame(200.0, $baris->tw1);
    }

    public function test_kotak_triwulan_kosong_tersimpan_null_bukan_nol(): void
    {
        $this->actingAs($this->superAdmin)
            ->putJson(route('admin.masterdata.spm.angka', $this->kategori->id), $this->payload())
            ->assertStatus(200);

        $baris = SpmCapaian::first();

        $this->assertNull($baris->tw2, 'kotak kosong = belum dilaporkan, bukan capaian 0');
        $this->assertNull($baris->tw3);
        $this->assertNull($baris->tw4);
    }

    public function test_nol_yang_diketik_sengaja_tersimpan_sebagai_nol(): void
    {
        $this->actingAs($this->superAdmin)
            ->putJson(route('admin.masterdata.spm.angka', $this->kategori->id), $this->payload(['tw2' => '0']))
            ->assertStatus(200);

        $this->assertSame(0.0, SpmCapaian::first()->tw2);
    }

    public function test_menyimpan_dua_kali_tidak_menggandakan_baris(): void
    {
        $url = route('admin.masterdata.spm.angka', $this->kategori->id);

        $this->actingAs($this->superAdmin)->putJson($url, $this->payload())->assertStatus(200);
        $this->actingAs($this->superAdmin)->putJson($url, $this->payload(['tw2' => '150']))->assertStatus(200);

        $this->assertSame(1, SpmCapaian::count());
        $this->assertSame(150.0, SpmCapaian::first()->tw2);
    }

    public function test_mengosongkan_kembali_triwulan_yang_terisi_menghapus_nilainya(): void
    {
        $url = route('admin.masterdata.spm.angka', $this->kategori->id);

        // Petugas salah ketik TW III, lalu mengosongkannya dan menyimpan ulang.
        $this->actingAs($this->superAdmin)->putJson($url, $this->payload(['tw3' => '999']))->assertStatus(200);
        $this->assertSame(999.0, SpmCapaian::first()->tw3);

        $this->actingAs($this->superAdmin)->putJson($url, $this->payload(['tw3' => '']))->assertStatus(200);

        $this->assertNull(SpmCapaian::first()->tw3, 'angka yang dihapus di form harus benar-benar hilang');
    }

    public function test_tahun_berbeda_adalah_baris_berbeda(): void
    {
        $url = route('admin.masterdata.spm.angka', $this->kategori->id);

        $this->actingAs($this->superAdmin)->putJson($url, $this->payload(['tahun' => 2025]))->assertStatus(200);
        $this->actingAs($this->superAdmin)->putJson($url, $this->payload(['tahun' => 2026]))->assertStatus(200);

        $this->assertSame(2, SpmCapaian::count());
    }

    public function test_catatan_tersimpan_dan_bisa_dikosongkan(): void
    {
        $url = route('admin.masterdata.spm.angka', $this->kategori->id);

        $this->actingAs($this->superAdmin)
            ->putJson($url, $this->payload(['catatan' => 'Stok vaksin terlambat di TW II']))
            ->assertStatus(200);
        $this->assertSame('Stok vaksin terlambat di TW II', SpmCapaian::first()->catatan);

        $this->actingAs($this->superAdmin)->putJson($url, $this->payload(['catatan' => '']))->assertStatus(200);
        $this->assertNull(SpmCapaian::first()->catatan);
    }

    public function test_angka_besar_dan_desimal_diterima(): void
    {
        $this->actingAs($this->superAdmin)
            ->putJson(route('admin.masterdata.spm.angka', $this->kategori->id), $this->payload([
                'sasaran' => '1234567.89',
                'tw1'     => '0.5',
            ]))
            ->assertStatus(200);

        $baris = SpmCapaian::first();

        $this->assertSame(1234567.89, $baris->sasaran);
        $this->assertSame(0.5, $baris->tw1);
    }

    public function test_sasaran_wajib_dan_tidak_boleh_negatif(): void
    {
        $url = route('admin.masterdata.spm.angka', $this->kategori->id);

        $this->actingAs($this->superAdmin)
            ->putJson($url, $this->payload(['sasaran' => '']))
            ->assertStatus(422)->assertJsonValidationErrors('sasaran');

        $this->actingAs($this->superAdmin)
            ->putJson($url, $this->payload(['sasaran' => '-5']))
            ->assertStatus(422)->assertJsonValidationErrors('sasaran');

        $this->actingAs($this->superAdmin)
            ->putJson($url, $this->payload(['tw1' => '-1']))
            ->assertStatus(422)->assertJsonValidationErrors('tw1');
    }

    public function test_tahun_di_luar_rentang_ditolak(): void
    {
        $url = route('admin.masterdata.spm.angka', $this->kategori->id);

        $this->actingAs($this->superAdmin)
            ->putJson($url, $this->payload(['tahun' => 1999]))
            ->assertStatus(422)->assertJsonValidationErrors('tahun');

        $this->actingAs($this->superAdmin)
            ->putJson($url, $this->payload(['tahun' => 'abcd']))
            ->assertStatus(422)->assertJsonValidationErrors('tahun');
    }

    public function test_admin_biasa_tidak_bisa_menyimpan_angka(): void
    {
        $admin = User::factory()->create(['type' => 1]);

        $this->actingAs($admin)
            ->putJson(route('admin.masterdata.spm.angka', $this->kategori->id), $this->payload())
            ->assertStatus(403);

        $this->assertSame(0, SpmCapaian::count());
    }

    public function test_kategori_tidak_ada_menghasilkan_404(): void
    {
        $this->actingAs($this->superAdmin)
            ->putJson(route('admin.masterdata.spm.angka', 99999), $this->payload())
            ->assertStatus(404);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SimpanAngkaSpmTest`
Expected: FAIL — `Method App\Http\Controllers\MasterDataSpmController::angka does not exist.`

- [ ] **Step 3: Implement `angka()`**

Add to `app/Http/Controllers/MasterDataSpmController.php`, after `update()`:

```php
    /**
     * Simpan sasaran + capaian TW I–IV untuk satu tahun (upsert).
     *
     * Keempat kunci tw1..tw4 SELALU ditulis ulang, termasuk sebagai NULL.
     * Kalau hanya kunci yang terisi yang diteruskan, angka yang dihapus
     * petugas di form akan dipertahankan oleh updateOrCreate dan tidak bisa
     * dibatalkan dari UI. NULL = triwulan belum dilaporkan, 0 = capaiannya nol.
     */
    public function angka(Request $request, $id)
    {
        $kategori = SpmKategori::findOrFail($id);

        $validated = $request->validate([
            'tahun'   => ['required', 'integer', 'min:' . (int) config('spm.tahun_min'), 'max:' . ((int) now()->year + 1)],
            'sasaran' => ['required', 'numeric', 'min:0'],
            'tw1'     => ['nullable', 'numeric', 'min:0'],
            'tw2'     => ['nullable', 'numeric', 'min:0'],
            'tw3'     => ['nullable', 'numeric', 'min:0'],
            'tw4'     => ['nullable', 'numeric', 'min:0'],
            'catatan' => ['nullable', 'string'],
        ]);

        $nilai = [
            'sasaran' => (float) $validated['sasaran'],
            'catatan' => $this->kosongJadiNull($request->input('catatan')),
        ];

        foreach (['tw1', 'tw2', 'tw3', 'tw4'] as $tw) {
            $isi = $this->kosongJadiNull($request->input($tw));
            $nilai[$tw] = $isi === null ? null : (float) $isi;
        }

        SpmCapaian::updateOrCreate(
            ['id_kategori' => $kategori->id, 'tahun' => (int) $validated['tahun']],
            $nilai,
        );

        return response()->json(['success' => true, 'message' => 'Angka SPM tersimpan']);
    }

    /** '' dan null sama-sama berarti "tidak diisi". */
    private function kosongJadiNull($nilai)
    {
        if ($nilai === null) {
            return null;
        }

        return trim((string) $nilai) === '' ? null : $nilai;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SimpanAngkaSpmTest`
Expected: PASS (13 tests).

- [ ] **Step 5: Run the neighbouring suite to catch regressions**

Run: `php artisan test --filter="MasterDataSpmTest|SpmModelTest"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/MasterDataSpmController.php tests/Feature/Spm/SimpanAngkaSpmTest.php
git commit -m "feat(spm): simpan sasaran & capaian per triwulan (kosong tetap NULL)"
```

---

### Task 5: Halaman master data SPM

**Files:**
- Create: `resources/views/admin/master-data/spm/index.blade.php`
- Modify: `tests/Feature/Spm/MasterDataSpmTest.php` (unskip `test_superadmin_bisa_membuka_halaman`, add render assertions)

**Interfaces:**
- Consumes: `$tahun` (int) dan `$tahunOpsi` (array int) dari `MasterDataSpmController::index`; semua rute `admin.masterdata.spm.*` (Task 3 & 4).
- Produces: halaman dengan tabel `#spmTable`, modal `#formModal` (definisi kategori), modal `#angkaModal` (sasaran + TW I–IV + catatan), modal `#deleteModal`.

- [ ] **Step 1: Update the test first**

Replace the skipped test in `tests/Feature/Spm/MasterDataSpmTest.php` with:

```php
    public function test_superadmin_bisa_membuka_halaman(): void
    {
        SpmKategori::create(['nama' => 'Pelayanan Balita', 'satuan' => 'anak']);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.masterdata.spm.index'));

        $response->assertStatus(200)
            ->assertSee('Master Data SPM')
            ->assertSee('spmTable', false)
            ->assertSee('angkaModal', false)
            ->assertSee('Tambah Kategori');
    }

    public function test_halaman_memakai_tahun_dari_query_string(): void
    {
        SpmCapaian::create([
            'id_kategori' => SpmKategori::create(['nama' => 'Pelayanan TB', 'satuan' => 'orang'])->id,
            'tahun'       => 2025,
            'sasaran'     => 500,
        ]);

        $this->actingAs($this->superAdmin)
            ->get(route('admin.masterdata.spm.index', ['tahun' => 2025]))
            ->assertStatus(200)
            ->assertSee('<option value="2025" selected', false);
    }

    public function test_breadcrumb_dan_judul_terisi(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('admin.masterdata.spm.index'))
            ->assertStatus(200)
            ->assertSee('Master Data')      // @yield('item')
            ->assertSee('SPM')              // @yield('item-active')
            ->assertDontSee('@endsection'); // jebakan directive nempel
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter="MasterDataSpmTest::test_superadmin_bisa_membuka_halaman"`
Expected: FAIL — `View [admin.master-data.spm.index] not found`.

- [ ] **Step 3: Create the view**

Create `resources/views/admin/master-data/spm/index.blade.php`.

Salin dulu blok CSS dari halaman master data yang sudah ada supaya token `--st-*` dan komponen `.md-header`, `.st-btn`, `.st-card`, `.st-form-group`, `.st-form-check`, `.st-modal` identik dengan halaman Antigen/Penyakit — jangan menulis ulang dari ingatan:

```bash
sed -n '19,165p' resources/views/admin/master-data/penyakit/index.blade.php > /tmp/spm-style.txt
```

Isi berkas `/tmp/spm-style.txt` itulah yang menggantikan baris `{{-- ... salin blok style ... --}}` di bawah (di dalam `<style>`, sebelum aturan `.spm-*` yang khusus halaman ini). Verifikasi setelah menempel: berkas hasil harus memuat `--st-primary`, `.md-header__title`, `.st-card__header`, `.st-form-group label`, dan `.st-modal .modal-header`.

Badan viewnya:

```blade
@extends('admin::layouts.app')
@push('styles')
<link rel="stylesheet" type="text/css" href="{{ asset('admin/src/plugins/datatables/css/dataTables.bootstrap4.min.css') }}">
<link rel="stylesheet" type="text/css" href="{{ asset('admin/src/plugins/datatables/css/responsive.bootstrap4.min.css') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">
@endpush
@push('js')
<script src="{{ asset('admin/src/plugins/datatables/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('admin/src/plugins/datatables/js/dataTables.bootstrap4.min.js') }}"></script>
<script src="{{ asset('admin/src/plugins/datatables/js/dataTables.responsive.min.js') }}"></script>
<script src="{{ asset('admin/src/plugins/datatables/js/responsive.bootstrap4.min.js') }}"></script>
@endpush
@section('title') Master Data SPM @endsection
@section('title-content') Master Data SPM @endsection
@section('item') Master Data @endsection
@section('item-active') SPM @endsection

@section('content')
<style>
    {{-- ... salin blok style dari master-data/penyakit/index.blade.php ... --}}
    .spm-tahun { display: flex; align-items: center; gap: 10px; }
    .spm-tahun select {
        height: 42px; padding: 0 14px; border-radius: var(--st-radius);
        border: 1.5px solid var(--st-border); background: #fff;
        font-family: 'Barlow', sans-serif; font-weight: 700; font-size: 0.8125rem;
    }
    .spm-tw-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
    @media (max-width: 576px) { .spm-tw-grid { grid-template-columns: repeat(2, 1fr); } }
</style>

<div class="md-page">
    <div class="md-header">
        <div class="md-header__left">
            <div class="md-header__icon">
                <span class="material-symbols-outlined">flag</span>
            </div>
            <div>
                <h1 class="md-header__title">Master Data SPM</h1>
                <p class="md-header__subtitle">Kategori Standar Pelayanan Minimal, sasaran setahun, dan capaian per triwulan</p>
            </div>
        </div>
        <div class="spm-tahun">
            <form method="GET" action="{{ route('admin.masterdata.spm.index') }}" class="spm-tahun">
                <label for="pilihTahun" style="margin:0; font-weight:700; font-size:0.8125rem;">Tahun</label>
                <select name="tahun" id="pilihTahun" onchange="this.form.submit()">
                    @foreach ($tahunOpsi as $opsi)
                        <option value="{{ $opsi }}" {{ $opsi === $tahun ? 'selected' : '' }}>{{ $opsi }}</option>
                    @endforeach
                </select>
            </form>
            <button class="st-btn st-btn-primary" id="btnTambah">
                <span class="material-symbols-outlined">add</span>
                Tambah Kategori
            </button>
        </div>
    </div>

    <div class="st-card">
        <div class="st-card__header">
            <h3 class="st-card__header-title">
                <span class="material-symbols-outlined">list_alt</span>
                Kategori SPM &amp; Capaian {{ $tahun }}
            </h3>
        </div>
        <div class="table-responsive" style="padding: 0;">
            <table id="spmTable" class="table" style="width:100%; margin-bottom: 0;">
                <thead>
                    <tr>
                        <th>Kategori</th>
                        <th>Satuan</th>
                        <th>Sasaran</th>
                        <th>TW I</th>
                        <th>TW II</th>
                        <th>TW III</th>
                        <th>TW IV</th>
                        <th>Kumulatif</th>
                        <th>%</th>
                        <th>Status</th>
                        <th style="text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

{{-- Modal definisi kategori --}}
<div class="modal fade st-modal" id="formModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="formModalTitle">Tambah Kategori SPM</h5>
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:0.9;"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="kategoriForm">
                    <input type="hidden" id="form_id">
                    <div class="st-form-group">
                        <label for="form_nama">Nama Kategori *</label>
                        <input type="text" id="form_nama" name="nama" placeholder="cth: Pelayanan Kesehatan Ibu Hamil">
                        <div class="text-danger" id="error_nama"></div>
                    </div>
                    <div class="st-form-group">
                        <label for="form_satuan">Satuan *</label>
                        <input type="text" id="form_satuan" name="satuan" placeholder="cth: orang, anak, posyandu">
                        <div class="text-danger" id="error_satuan"></div>
                        <small class="text-muted">Satu satuan dipakai untuk sasaran maupun capaian.</small>
                    </div>
                    <div class="st-form-group">
                        <label for="form_urutan">Urutan Tampil</label>
                        <input type="number" id="form_urutan" name="urutan" min="0" value="0">
                        <div class="text-danger" id="error_urutan"></div>
                    </div>
                    <div class="st-form-group">
                        <label for="form_keterangan">Keterangan</label>
                        <textarea id="form_keterangan" name="keterangan" rows="2" placeholder="Opsional"></textarea>
                    </div>
                    <div class="st-form-check">
                        <input type="checkbox" id="form_aktif" name="is_active" checked>
                        <label for="form_aktif">Aktif</label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" data-dismiss="modal" style="background:#fff; color:var(--st-text-muted); border:1px solid var(--st-border); border-radius:var(--st-radius); font-family:'Barlow',sans-serif; font-weight:700;">Batal</button>
                <button type="button" class="btn" id="btnSimpan" style="background:var(--st-primary); color:#fff; border-radius:var(--st-radius); font-family:'Barlow',sans-serif; font-weight:700;">Simpan</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal angka per tahun --}}
<div class="modal fade st-modal" id="angkaModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Isi Angka <span id="angkaNamaKategori"></span> — {{ $tahun }}</h5>
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:0.9;"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="angkaForm">
                    <input type="hidden" id="angka_id">
                    <input type="hidden" id="angka_tahun" name="tahun" value="{{ $tahun }}">
                    <div class="st-form-group">
                        <label for="angka_sasaran">Sasaran Setahun *</label>
                        <input type="number" step="any" min="0" id="angka_sasaran" name="sasaran">
                        <div class="text-danger" id="error_sasaran"></div>
                    </div>
                    <label style="font-weight:700; font-size:0.8125rem;">Capaian per Triwulan</label>
                    <p class="text-muted" style="font-size:0.75rem; margin-bottom:8px;">
                        Isi hasil triwulan itu saja — sistem yang menjumlahkan. Kosongkan bila belum dilaporkan;
                        <strong>kosong tidak sama dengan 0</strong>.
                    </p>
                    <div class="spm-tw-grid">
                        @foreach (['tw1' => 'TW I', 'tw2' => 'TW II', 'tw3' => 'TW III', 'tw4' => 'TW IV'] as $key => $label)
                            <div class="st-form-group">
                                <label for="angka_{{ $key }}">{{ $label }}</label>
                                <input type="number" step="any" min="0" id="angka_{{ $key }}" name="{{ $key }}">
                                <div class="text-danger" id="error_{{ $key }}"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="st-form-group">
                        <label for="angka_catatan">Catatan / Kendala</label>
                        <textarea id="angka_catatan" name="catatan" rows="2" placeholder="cth: stok vaksin terlambat di TW II"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" data-dismiss="modal" style="background:#fff; color:var(--st-text-muted); border:1px solid var(--st-border); border-radius:var(--st-radius); font-family:'Barlow',sans-serif; font-weight:700;">Batal</button>
                <button type="button" class="btn" id="btnSimpanAngka" style="background:var(--st-primary); color:#fff; border-radius:var(--st-radius); font-family:'Barlow',sans-serif; font-weight:700;">Simpan Angka</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal hapus --}}
<div class="modal fade st-modal" id="deleteModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header modal-header-danger">
                <h5 class="modal-title">Konfirmasi Hapus</h5>
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:0.9;"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <p>Hapus kategori <strong id="deleteNama"></strong>?</p>
                <p style="color:#dc2626; font-weight:600;">Angka tahun-tahun sebelumnya tetap tersimpan dan kembali bila kategori dipulihkan.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" data-dismiss="modal" style="background:#fff; color:var(--st-text-muted); border:1px solid var(--st-border); border-radius:var(--st-radius); font-family:'Barlow',sans-serif; font-weight:700;">Batal</button>
                <button type="button" class="btn" id="confirmDelete" style="background:#dc2626; color:#fff; border-radius:var(--st-radius); font-family:'Barlow',sans-serif; font-weight:700;">Hapus</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
@parent
<script>
$(document).ready(function () {
    var TAHUN = {{ $tahun }};
    var angkaKosong = function (v) { return (v === null || v === undefined) ? '—' : v; };

    var table = $('#spmTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("admin.masterdata.spm.getData") }}?tahun=' + TAHUN,
        columns: [
            { data: 'nama', name: 'spm_kategori.nama' },
            { data: 'satuan', name: 'spm_kategori.satuan' },
            { data: 'sasaran', name: 'spm_capaian.sasaran', render: angkaKosong },
            { data: 'tw1', name: 'spm_capaian.tw1', render: angkaKosong },
            { data: 'tw2', name: 'spm_capaian.tw2', render: angkaKosong },
            { data: 'tw3', name: 'spm_capaian.tw3', render: angkaKosong },
            { data: 'tw4', name: 'spm_capaian.tw4', render: angkaKosong },
            { data: 'kumulatif', name: 'kumulatif', orderable: false, searchable: false, render: angkaKosong },
            { data: 'persen_badge', name: 'persen_badge', orderable: false, searchable: false },
            { data: 'status_badge', name: 'status_badge', orderable: false, searchable: false },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        order: [[0, 'asc']],
        pageLength: 25,
        language: { url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/id.json' }
    });

    function clearErrors() { $('.text-danger').text(''); }

    $('#btnTambah').on('click', function () {
        $('#kategoriForm')[0].reset();
        $('#form_id').val('');
        $('#form_aktif').prop('checked', true);
        clearErrors();
        $('#formModalTitle').text('Tambah Kategori SPM');
        $('#formModal').modal('show');
    });

    $(document).on('click', '.btn-edit', function () {
        var row = table.row($(this).closest('tr')).data();
        clearErrors();
        $('#form_id').val(row.id);
        $('#form_nama').val($('<div>').html(row.nama).text());
        $('#form_satuan').val($('<div>').html(row.satuan).text());
        $('#form_urutan').val(row.urutan);
        $('#form_keterangan').val(row.keterangan);
        $('#form_aktif').prop('checked', row.is_active == 1);
        $('#formModalTitle').text('Edit Kategori SPM');
        $('#formModal').modal('show');
    });

    $('#btnSimpan').on('click', function () {
        clearErrors();
        var id = $('#form_id').val();
        $.ajax({
            url: id
                ? '{{ route("admin.masterdata.spm.update", ":id") }}'.replace(':id', id)
                : '{{ route("admin.masterdata.spm.store") }}',
            type: id ? 'PUT' : 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                nama: $('#form_nama').val(),
                satuan: $('#form_satuan').val(),
                urutan: $('#form_urutan').val(),
                keterangan: $('#form_keterangan').val(),
                is_active: $('#form_aktif').is(':checked') ? 1 : 0
            },
            success: function (res) {
                $('#formModal').modal('hide');
                Swal.fire({ icon: 'success', title: 'Berhasil', text: res.message, timer: 2000 });
                table.draw();
            },
            error: tampilkanGalat
        });
    });

    $(document).on('click', '.btn-angka', function () {
        var row = table.row($(this).closest('tr')).data();
        clearErrors();
        $('#angka_id').val(row.id);
        $('#angkaNamaKategori').text($('<div>').html(row.nama).text());
        $('#angka_sasaran').val(row.sasaran);
        $('#angka_tw1').val(row.tw1);
        $('#angka_tw2').val(row.tw2);
        $('#angka_tw3').val(row.tw3);
        $('#angka_tw4').val(row.tw4);
        $('#angka_catatan').val(row.catatan);
        $('#angkaModal').modal('show');
    });

    $('#btnSimpanAngka').on('click', function () {
        clearErrors();
        // Keempat TW SELALU dikirim, termasuk yang kosong — supaya angka yang
        // dihapus petugas benar-benar hilang, bukan dipertahankan nilai lamanya.
        $.ajax({
            url: '{{ route("admin.masterdata.spm.angka", ":id") }}'.replace(':id', $('#angka_id').val()),
            type: 'PUT',
            data: {
                _token: '{{ csrf_token() }}',
                tahun: $('#angka_tahun').val(),
                sasaran: $('#angka_sasaran').val(),
                tw1: $('#angka_tw1').val(),
                tw2: $('#angka_tw2').val(),
                tw3: $('#angka_tw3').val(),
                tw4: $('#angka_tw4').val(),
                catatan: $('#angka_catatan').val()
            },
            success: function (res) {
                $('#angkaModal').modal('hide');
                Swal.fire({ icon: 'success', title: 'Berhasil', text: res.message, timer: 2000 });
                table.draw();
            },
            error: tampilkanGalat
        });
    });

    $(document).on('click', '.btn-toggle', function () {
        kirim('{{ route("admin.masterdata.spm.toggleStatus", ":id") }}'.replace(':id', $(this).data('id')), 'PATCH');
    });

    var idHapus = null;
    $(document).on('click', '.btn-delete', function () {
        var row = table.row($(this).closest('tr')).data();
        idHapus = row.id;
        $('#deleteNama').text($('<div>').html(row.nama).text());
        $('#deleteModal').modal('show');
    });

    $('#confirmDelete').on('click', function () {
        $('#deleteModal').modal('hide');
        kirim('{{ route("admin.masterdata.spm.destroy", ":id") }}'.replace(':id', idHapus), 'DELETE');
    });

    $(document).on('click', '.btn-restore', function () {
        kirim('{{ route("admin.masterdata.spm.restore", ":id") }}'.replace(':id', $(this).data('id')), 'PATCH');
    });

    function kirim(url, method) {
        $.ajax({
            url: url,
            type: method,
            data: { _token: '{{ csrf_token() }}' },
            success: function (res) {
                Swal.fire({ icon: 'success', title: 'Berhasil', text: res.message, timer: 1500 });
                table.draw();
            },
            error: tampilkanGalat
        });
    }

    function tampilkanGalat(xhr) {
        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
            $.each(xhr.responseJSON.errors, function (field, messages) {
                $('#error_' + field).text(messages[0]);
            });
            return;
        }
        Swal.fire({
            icon: 'error',
            title: 'Gagal',
            text: xhr.responseJSON ? xhr.responseJSON.message : 'Terjadi kesalahan'
        });
    }
});
</script>
@endsection
```

- [ ] **Step 4: Clear the view cache and run the tests**

```bash
php artisan view:clear
php artisan test --filter=MasterDataSpmTest
```
Expected: PASS (15 tests, none skipped).

- [ ] **Step 5: Look at the page in a browser**

Start the app (`php artisan serve --port=8001` — port 8000 belongs to another project on this machine; MySQL must be running), log in as superadmin, open `/admin/master-data/spm`. Confirm: tambah kategori, isi angka (kosongkan satu TW lalu simpan, buka lagi — harus tetap kosong), ganti tahun di pemilih tahun, dan tombol hapus/pulihkan.

- [ ] **Step 6: Commit**

```bash
git add resources/views/admin/master-data/spm/index.blade.php tests/Feature/Spm/MasterDataSpmTest.php
git commit -m "feat(spm): halaman master data SPM dengan modal kategori & modal angka"
```

---

### Task 6: Dasbor SPM — kartu, tabel, dan bar prorata

**Files:**
- Create: `app/Http/Controllers/SpmDashboardController.php`
- Create: `resources/views/admin/spm/dashboard.blade.php`
- Modify: `routes/web.php` (in the `is_admin` group, after the `imunisasi-dashboard` route ±line 180)
- Test: `tests/Feature/Spm/SpmDashboardTest.php`

**Interfaces:**
- Consumes: model Task 1, `CapaianSpm` + `RingkasanSpm` + `config('spm.*')` Task 2.
- Produces: rute `admin.spm.dashboard`; view menerima `$tahun` (int), `$tahunOpsi` (array int), `$baris` (array of `['kategori' => SpmKategori, 'capaian' => CapaianSpm]`), `$ringkasan` (array dari `RingkasanSpm::dari`), `$statusMeta` (`config('spm.status')`), `$grafik` (array siap `@json`, dipakai Task 7).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Spm/SpmDashboardTest.php`:

```php
<?php

namespace Tests\Feature\Spm;

use App\Models\SpmCapaian;
use App\Models\SpmKategori;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpmDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function kategori(string $nama, array $angka = [], bool $aktif = true): SpmKategori
    {
        $kategori = SpmKategori::create(['nama' => $nama, 'satuan' => 'orang', 'is_active' => $aktif]);

        if ($angka !== []) {
            SpmCapaian::create(array_merge(['id_kategori' => $kategori->id, 'tahun' => now()->year], $angka));
        }

        return $kategori;
    }

    public function test_superadmin_bisa_membuka(): void
    {
        $this->actingAs(User::factory()->create(['type' => 0]))
            ->get(route('admin.spm.dashboard'))
            ->assertStatus(200);
    }

    public function test_admin_bisa_membuka(): void
    {
        $this->actingAs(User::factory()->create(['type' => 1]))
            ->get(route('admin.spm.dashboard'))
            ->assertStatus(200);
    }

    public function test_faskes_surveilans_bisa_membuka(): void
    {
        $faskes = User::factory()->create(['type' => 1, 'role' => 'surveilans_puskesmas']);

        $this->actingAs($faskes)
            ->get(route('admin.spm.dashboard'))
            ->assertStatus(200);
    }

    public function test_tamu_dialihkan_ke_login(): void
    {
        $this->get(route('admin.spm.dashboard'))->assertRedirect(route('login'));
    }

    public function test_kategori_belum_dilaporkan_tidak_menurunkan_rata_rata(): void
    {
        $this->kategori('Pelayanan A', ['sasaran' => 1000, 'tw1' => 900]);  // 90 %
        $this->kategori('Pelayanan B', ['sasaran' => 1000]);               // belum dilaporkan

        $response = $this->actingAs(User::factory()->create(['type' => 0]))
            ->get(route('admin.spm.dashboard'))
            ->assertStatus(200);

        $ringkasan = $response->viewData('ringkasan');

        $this->assertSame(90.0, $ringkasan['rata_rata']);
        $this->assertSame(1, $ringkasan['belum']);
        $this->assertSame(1, $ringkasan['dihitung']);
        $response->assertSee('belum dilaporkan');
    }

    public function test_sasaran_nol_tampil_tanpa_persen_dan_tanpa_error(): void
    {
        $this->kategori('Pelayanan Tanpa Sasaran', ['sasaran' => 0, 'tw1' => 5]);

        $response = $this->actingAs(User::factory()->create(['type' => 0]))
            ->get(route('admin.spm.dashboard'))
            ->assertStatus(200);

        $this->assertSame(1, $response->viewData('ringkasan')['tanpa_sasaran']);
        $this->assertNull($response->viewData('ringkasan')['rata_rata']);
        $response->assertSee('Tanpa sasaran');
    }

    public function test_kategori_tanpa_baris_capaian_tetap_muncul_sebagai_belum(): void
    {
        $this->kategori('Pelayanan Tanpa Angka');

        $response = $this->actingAs(User::factory()->create(['type' => 0]))
            ->get(route('admin.spm.dashboard'))
            ->assertStatus(200);

        $response->assertSee('Pelayanan Tanpa Angka')->assertSee('Belum dilaporkan');
        $this->assertSame(1, $response->viewData('ringkasan')['belum']);
    }

    public function test_kategori_nonaktif_tidak_ikut(): void
    {
        $this->kategori('Pelayanan Nonaktif', ['sasaran' => 100, 'tw1' => 10], false);

        $response = $this->actingAs(User::factory()->create(['type' => 0]))
            ->get(route('admin.spm.dashboard'))
            ->assertStatus(200);

        $response->assertDontSee('Pelayanan Nonaktif');
        $this->assertSame(0, $response->viewData('ringkasan')['jumlah_kategori']);
    }

    public function test_capaian_melebihi_sasaran_bar_dipotong_angka_tidak(): void
    {
        $this->kategori('Pelayanan Melebihi', ['sasaran' => 1000, 'tw1' => 1120]);

        $response = $this->actingAs(User::factory()->create(['type' => 0]))
            ->get(route('admin.spm.dashboard'))
            ->assertStatus(200);

        $response->assertSee('112,0%');
        $this->assertStringNotContainsString('width: 112', $response->getContent());
        $response->assertSee('width: 100%', false);
    }

    public function test_tahun_ngawur_jatuh_ke_tahun_ini(): void
    {
        $this->kategori('Pelayanan A', ['sasaran' => 1000, 'tw1' => 500]);

        foreach (['abcd', '1900', '9999', ''] as $tahun) {
            $response = $this->actingAs(User::factory()->create(['type' => 0]))
                ->get(route('admin.spm.dashboard', ['tahun' => $tahun]))
                ->assertStatus(200);

            $this->assertSame((int) now()->year, $response->viewData('tahun'));
        }
    }

    public function test_tahun_lain_menampilkan_angka_tahun_itu(): void
    {
        $kategori = $this->kategori('Pelayanan A', ['sasaran' => 1000, 'tw1' => 500]);
        SpmCapaian::create(['id_kategori' => $kategori->id, 'tahun' => 2025, 'sasaran' => 800, 'tw1' => 800]);

        $response = $this->actingAs(User::factory()->create(['type' => 0]))
            ->get(route('admin.spm.dashboard', ['tahun' => 2025]))
            ->assertStatus(200);

        $this->assertSame(2025, $response->viewData('tahun'));
        $this->assertSame(100.0, $response->viewData('ringkasan')['rata_rata']);
    }

    public function test_belum_ada_kategori_menampilkan_empty_state(): void
    {
        $response = $this->actingAs(User::factory()->create(['type' => 0]))
            ->get(route('admin.spm.dashboard'))
            ->assertStatus(200);

        $response->assertSee('Belum ada kategori SPM');
        $response->assertSee(route('admin.masterdata.spm.index'), false);
    }

    public function test_empty_state_tidak_menawarkan_master_data_ke_admin_biasa(): void
    {
        $response = $this->actingAs(User::factory()->create(['type' => 1]))
            ->get(route('admin.spm.dashboard'))
            ->assertStatus(200);

        $response->assertSee('Belum ada kategori SPM');
        $response->assertDontSee(route('admin.masterdata.spm.index'), false);
    }

    public function test_judul_dan_breadcrumb_terisi(): void
    {
        $this->actingAs(User::factory()->create(['type' => 0]))
            ->get(route('admin.spm.dashboard'))
            ->assertStatus(200)
            ->assertSee('Dasbor SPM')
            ->assertDontSee('@endsection');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SpmDashboardTest`
Expected: FAIL — `Route [admin.spm.dashboard] not defined.`

- [ ] **Step 3: Add the route**

In `routes/web.php`, inside the `Route::middleware(['auth', 'is_admin'])->prefix('admin/')` group, right after the `imunisasi-dashboard` line (±line 180):

```php
    // Dasbor SPM — spec docs/superpowers/specs/2026-09-29-dasbor-spm-design.md §5
    Route::get('spm-dashboard', [App\Http\Controllers\SpmDashboardController::class, 'index'])
         ->name('admin.spm.dashboard');
```

- [ ] **Step 4: Write the controller**

Create `app/Http/Controllers/SpmDashboardController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\SpmCapaian;
use App\Models\SpmKategori;
use App\Support\CapaianSpm;
use App\Support\RingkasanSpm;
use Illuminate\Http\Request;

/**
 * Dasbor SPM — spec docs/superpowers/specs/2026-09-29-dasbor-spm-design.md §5.
 *
 * Server-render penuh dengan SATU query leftJoin. Datanya puluhan baris, bukan
 * populasi — peringatan chunking di CLAUDE.md tidak berlaku di sini, dan
 * memecahnya jadi endpoint JSON tidak meringankan apa pun.
 */
class SpmDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $tahun  = $this->tahunTervalidasi($request);
        $ambang = config('spm.ambang');

        $rows = SpmKategori::query()
            ->where('spm_kategori.is_active', true)
            ->leftJoin('spm_capaian', function ($join) use ($tahun) {
                $join->on('spm_capaian.id_kategori', '=', 'spm_kategori.id')
                     ->where('spm_capaian.tahun', '=', $tahun);
            })
            ->select([
                'spm_kategori.id',
                'spm_kategori.nama',
                'spm_kategori.satuan',
                'spm_kategori.keterangan',
                'spm_kategori.urutan',
                'spm_capaian.sasaran',
                'spm_capaian.tw1',
                'spm_capaian.tw2',
                'spm_capaian.tw3',
                'spm_capaian.tw4',
                'spm_capaian.catatan',
            ])
            ->orderBy('spm_kategori.urutan')
            ->orderBy('spm_kategori.nama')
            ->get();

        $baris = $rows->map(fn ($row) => [
            'kategori' => $row,
            'capaian'  => CapaianSpm::dari(
                $row->sasaran,
                [$row->tw1, $row->tw2, $row->tw3, $row->tw4],
                $tahun,
                $ambang,
            ),
        ])->all();

        return view('admin.spm.dashboard', [
            'tahun'      => $tahun,
            'tahunOpsi'  => $this->tahunOpsi(),
            'baris'      => $baris,
            'ringkasan'  => RingkasanSpm::dari(array_column($baris, 'capaian')),
            'statusMeta' => config('spm.status'),
            'grafik'     => $this->grafik($baris),
        ]);
    }

    /**
     * Data grafik: batang persen (urut tertinggal dulu) + garis kumulatif vs prorata.
     * Dipakai view lewat @json — tidak ada endpoint JSON terpisah.
     */
    private function grafik(array $baris): array
    {
        $batang = [];
        $garis  = [];

        foreach ($baris as $item) {
            /** @var CapaianSpm $capaian */
            $capaian = $item['capaian'];
            $status  = $capaian->status();

            $batang[] = [
                'nama'   => $item['kategori']->nama,
                'persen' => $capaian->persen(),
                'status' => $status,
                'warna'  => config("spm.status.{$status}.warna"),
            ];

            $garis[] = [
                'id'        => $item['kategori']->id,
                'nama'      => $item['kategori']->nama,
                'satuan'    => $item['kategori']->satuan,
                'kumulatif' => $capaian->kumulatifPerTw(),
                'prorata'   => [
                    $capaian->prorataTw(1), $capaian->prorataTw(2),
                    $capaian->prorataTw(3), $capaian->prorataTw(4),
                ],
            ];
        }

        // Paling tertinggal di atas; yang belum punya persen ditaruh terakhir.
        usort($batang, function ($a, $b) {
            if ($a['persen'] === null && $b['persen'] === null) {
                return strcmp($a['nama'], $b['nama']);
            }
            if ($a['persen'] === null) {
                return 1;
            }
            if ($b['persen'] === null) {
                return -1;
            }

            return $a['persen'] <=> $b['persen'];
        });

        return ['batang' => $batang, 'garis' => $garis];
    }

    /** Tahun ngawur jatuh ke tahun ini — dasbor tidak boleh 500 karena query string. */
    private function tahunTervalidasi(Request $request): int
    {
        $tahun = $request->input('tahun');
        $min   = (int) config('spm.tahun_min');
        $max   = (int) now()->year + 1;

        if (!is_numeric($tahun)) {
            return (int) now()->year;
        }

        $tahun = (int) $tahun;

        return ($tahun < $min || $tahun > $max) ? (int) now()->year : $tahun;
    }

    private function tahunOpsi(): array
    {
        $tahun = SpmCapaian::query()->distinct()->pluck('tahun')->map(fn ($t) => (int) $t)->all();
        $tahun[] = (int) now()->year;

        $tahun = array_values(array_unique($tahun));
        rsort($tahun);

        return $tahun;
    }
}
```

- [ ] **Step 5: Write the view (cards + table; charts land in Task 7)**

Create `resources/views/admin/spm/dashboard.blade.php`:

```blade
@extends('admin::layouts.app')
@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">
@endpush
@section('title') Dasbor SPM @endsection
@section('title-content') Dasbor SPM @endsection
@section('item') Dashboard @endsection
@section('item-active') SPM @endsection

@section('content')
<style>
    :root {
        --spm-primary: oklch(0.48 0.14 145);
        --spm-bg: #f5f7f8;
        --spm-surface: #ffffff;
        --spm-text: #0f172a;
        --spm-muted: #64748b;
        --spm-border: #e2e8f0;
        --spm-radius: 12px;
        --spm-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.06), 0 1px 2px -1px rgb(0 0 0 / 0.06);
    }
    .spm-page { font-family: 'Barlow', sans-serif; color: var(--spm-text); }
    .spm-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 24px; }
    .spm-header__title { font-size: 1.75rem; font-weight: 800; margin: 0; letter-spacing: -0.02em; }
    .spm-header__sub { font-size: 0.875rem; color: var(--spm-muted); font-weight: 500; margin: 2px 0 0; }
    .spm-filter select { height: 42px; padding: 0 14px; border-radius: var(--spm-radius); border: 1.5px solid var(--spm-border); background: #fff; font-family: 'Barlow', sans-serif; font-weight: 700; font-size: 0.8125rem; }

    .spm-cards { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; margin-bottom: 24px; }
    @media (max-width: 1200px) { .spm-cards { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 700px)  { .spm-cards { grid-template-columns: repeat(2, 1fr); } }
    .spm-card { background: var(--spm-surface); border: 1px solid var(--spm-border); border-radius: var(--spm-radius); box-shadow: var(--spm-shadow); padding: 18px 20px; }
    .spm-card__label { font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--spm-muted); }
    .spm-card__angka { font-size: 1.75rem; font-weight: 800; line-height: 1.1; margin-top: 6px; }
    .spm-card__ket { font-size: 0.75rem; color: var(--spm-muted); margin-top: 4px; }

    .spm-panel { background: var(--spm-surface); border: 1px solid var(--spm-border); border-radius: var(--spm-radius); box-shadow: var(--spm-shadow); overflow: hidden; margin-bottom: 24px; }
    .spm-panel__header { background: linear-gradient(135deg, #047857 0%, var(--spm-primary) 100%); color: #fff; padding: 14px 22px; font-weight: 700; font-size: 1.05rem; }
    .spm-table { width: 100%; border-collapse: collapse; }
    .spm-table th { background: #f8fafc; border-bottom: 2px solid var(--spm-border); color: var(--spm-muted); font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; padding: 12px 14px; white-space: nowrap; }
    .spm-table td { padding: 12px 14px; font-size: 0.8125rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .spm-table tbody tr:nth-child(even) { background: rgba(248, 250, 252, 0.5); }
    .spm-angka { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }

    /* Bar + tanda prorata. Tanda digambar dengan ::after supaya SELALU sejajar
       dengan bar-nya sendiri; jangan diganti anotasi canvas. */
    .spm-bar { position: relative; height: 10px; background: #eef2f6; border-radius: 9999px; min-width: 90px; }
    .spm-bar__isi { height: 100%; border-radius: 9999px; }
    .spm-bar__prorata { position: absolute; top: -3px; width: 2px; height: 16px; background: #0f172a; opacity: 0.55; }

    .spm-chip { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 9999px; font-size: 0.6875rem; font-weight: 700; border: 1px solid transparent; }
    .spm-chip.bg-success   { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
    .spm-chip.bg-info      { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
    .spm-chip.bg-warning   { background: #fffbeb; color: #b45309; border-color: #fde68a; }
    .spm-chip.bg-danger    { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
    .spm-chip.bg-secondary { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }

    .spm-catatan td { background: #fffdf5; font-size: 0.78rem; color: #78350f; border-top: none; }
    .spm-empty { padding: 48px 24px; text-align: center; color: var(--spm-muted); }
    .spm-empty__judul { font-size: 1.1rem; font-weight: 700; color: var(--spm-text); margin-bottom: 6px; }
</style>

@php
    $angka = fn ($nilai, $desimal = 0) => $nilai === null ? '—' : number_format($nilai, $desimal, ',', '.');
@endphp

<div class="spm-page">
    <div class="spm-header">
        <div>
            <h1 class="spm-header__title">Dasbor SPM {{ $tahun }}</h1>
            <p class="spm-header__sub">Ketercapaian Standar Pelayanan Minimal — sasaran setahun, capaian per triwulan</p>
        </div>
        <form method="GET" action="{{ route('admin.spm.dashboard') }}" class="spm-filter">
            <label for="pilihTahun" style="margin:0 8px 0 0; font-weight:700; font-size:0.8125rem;">Tahun</label>
            <select name="tahun" id="pilihTahun" onchange="this.form.submit()">
                @foreach ($tahunOpsi as $opsi)
                    <option value="{{ $opsi }}" {{ $opsi === $tahun ? 'selected' : '' }}>{{ $opsi }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @if (count($baris) === 0)
        <div class="spm-panel">
            <div class="spm-empty">
                <p class="spm-empty__judul">Belum ada kategori SPM yang aktif</p>
                <p>Kategori, sasaran, dan capaian triwulan diisi di Master Data SPM.</p>
                @if (auth()->user()->isSuperAdmin())
                    <a href="{{ route('admin.masterdata.spm.index') }}" style="font-weight:700; color:var(--spm-primary);">
                        Buka Master Data SPM →
                    </a>
                @else
                    <p style="font-size:0.8125rem;">Hubungi admin Dinkes untuk menambahkannya.</p>
                @endif
            </div>
        </div>
    @else
        <div class="spm-cards">
            <div class="spm-card">
                <div class="spm-card__label">Kategori Aktif</div>
                <div class="spm-card__angka">{{ $ringkasan['jumlah_kategori'] }}</div>
                <div class="spm-card__ket">dipantau tahun {{ $tahun }}</div>
            </div>
            <div class="spm-card">
                <div class="spm-card__label">Rata-rata Capaian</div>
                <div class="spm-card__angka">{{ $ringkasan['rata_rata'] === null ? '—' : $angka($ringkasan['rata_rata'], 1) . '%' }}</div>
                <div class="spm-card__ket">
                    dari {{ $ringkasan['dihitung'] }} kategori
                    @if ($ringkasan['belum'] + $ringkasan['tanpa_sasaran'] > 0)
                        · {{ $ringkasan['belum'] }} belum dilaporkan, tidak dihitung
                    @endif
                </div>
            </div>
            <div class="spm-card">
                <div class="spm-card__label">Tercapai</div>
                <div class="spm-card__angka" style="color:#047857;">{{ $ringkasan['tercapai'] }}</div>
                <div class="spm-card__ket">capaian ≥ 100% sasaran</div>
            </div>
            <div class="spm-card">
                <div class="spm-card__label">Tertinggal</div>
                <div class="spm-card__angka" style="color:#b45309;">{{ $ringkasan['tertinggal'] }}</div>
                <div class="spm-card__ket">di bawah laju triwulan</div>
            </div>
            <div class="spm-card">
                <div class="spm-card__label">Belum Dilaporkan</div>
                <div class="spm-card__angka" style="color:#64748b;">{{ $ringkasan['belum'] }}</div>
                <div class="spm-card__ket">tidak sama dengan capaian nol</div>
            </div>
        </div>

        <div class="spm-panel">
            <div class="spm-panel__header">Rincian per Kategori</div>
            <div class="table-responsive">
                <table class="spm-table">
                    <thead>
                        <tr>
                            <th>Kategori</th>
                            <th class="spm-angka">Sasaran</th>
                            <th class="spm-angka">TW I</th>
                            <th class="spm-angka">TW II</th>
                            <th class="spm-angka">TW III</th>
                            <th class="spm-angka">TW IV</th>
                            <th class="spm-angka">Kumulatif</th>
                            <th style="min-width:120px;">Laju</th>
                            <th class="spm-angka">%</th>
                            <th>Status</th>
                            <th class="spm-angka">Selisih</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($baris as $item)
                            @php
                                $kategori = $item['kategori'];
                                $capaian  = $item['capaian'];
                                $status   = $capaian->status();
                                $meta     = $statusMeta[$status];
                                $persen   = $capaian->persen();
                                $lebar    = $persen === null ? 0 : min(100, max(0, $persen));
                                $prorata  = $capaian->sasaran() > 0 ? min(100, $capaian->twTerisi() * 25) : null;
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $kategori->nama }}</strong>
                                    <div style="font-size:0.72rem; color:var(--spm-muted);">{{ $kategori->satuan }}</div>
                                </td>
                                <td class="spm-angka">{{ $angka($capaian->sasaran(), 0) }}</td>
                                @foreach ([1, 2, 3, 4] as $n)
                                    <td class="spm-angka">{{ $angka($capaian->tw($n), 0) }}</td>
                                @endforeach
                                <td class="spm-angka"><strong>{{ $angka($capaian->kumulatif(), 0) }}</strong></td>
                                <td>
                                    <div class="spm-bar">
                                        <div class="spm-bar__isi" style="width: {{ $lebar }}%; background: {{ $meta['warna'] }};"></div>
                                        @if ($prorata !== null)
                                            <span class="spm-bar__prorata" style="left: {{ $prorata }}%;"
                                                  title="Target s.d. TW {{ $capaian->twTerisi() }}"></span>
                                        @endif
                                    </div>
                                </td>
                                <td class="spm-angka">{{ $persen === null ? '—' : $angka($persen, 1) . '%' }}</td>
                                <td>
                                    <span class="spm-chip {{ $meta['badge'] }}">{{ $meta['label'] }}</span>
                                    @if ($capaian->laporanTertinggal() && $capaian->twKalender() > 0)
                                        <span class="spm-chip bg-secondary" style="margin-top:4px;">
                                            laporan TW {{ $capaian->twKalender() }} belum masuk
                                        </span>
                                    @endif
                                    @foreach ($capaian->twKosong() as $kosong)
                                        <span class="spm-chip bg-secondary" style="margin-top:4px;">TW {{ $kosong }} kosong</span>
                                    @endforeach
                                </td>
                                <td class="spm-angka">{{ $angka($capaian->selisih(), 0) }}</td>
                            </tr>
                            @if (!empty($kategori->catatan))
                                <tr class="spm-catatan">
                                    <td colspan="11"><strong>Catatan:</strong> {{ $kategori->catatan }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
```

Catatan: `$lebar` selalu dipotong di 100 sehingga bar tidak meluber, sementara kolom `%` mencetak angka aslinya (112,0%). `$prorata` memakai `twTerisi() * 25` — posisi tanda target s.d. triwulan yang dilaporkan.

- [ ] **Step 6: Clear the view cache and run the tests**

```bash
php artisan view:clear
php artisan test --filter=SpmDashboardTest
```
Expected: PASS (14 tests).

- [ ] **Step 7: Commit**

```bash
git add routes/web.php app/Http/Controllers/SpmDashboardController.php resources/views/admin/spm/dashboard.blade.php tests/Feature/Spm/SpmDashboardTest.php
git commit -m "feat(spm): dasbor SPM dengan kartu ringkasan, tabel rincian, dan tanda prorata"
```

---

### Task 7: Grafik dasbor — batang tertinggal-dulu & garis kumulatif vs prorata

**Files:**
- Modify: `resources/views/admin/spm/dashboard.blade.php` (add two panels + `@section('custom_scripts')`)
- Test: `tests/Feature/Spm/SpmGrafikTest.php`

**Interfaces:**
- Consumes: `$grafik['batang']` (`[['nama','persen','status','warna'], …]` sudah terurut tertinggal dulu) dan `$grafik['garis']` (`[['id','nama','satuan','kumulatif' => [4], 'prorata' => [4]], …]`) dari `SpmDashboardController` (Task 6).
- Produces: dua `<canvas>` (`#spmBatang`, `#spmGaris`) dan `<select id="spmPilihKategori">`; tidak ada endpoint baru.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Spm/SpmGrafikTest.php`:

```php
<?php

namespace Tests\Feature\Spm;

use App\Models\SpmCapaian;
use App\Models\SpmKategori;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpmGrafikTest extends TestCase
{
    use RefreshDatabase;

    private function kategori(string $nama, array $angka): SpmKategori
    {
        $kategori = SpmKategori::create(['nama' => $nama, 'satuan' => 'orang']);
        SpmCapaian::create(array_merge(['id_kategori' => $kategori->id, 'tahun' => now()->year], $angka));

        return $kategori;
    }

    private function buka()
    {
        return $this->actingAs(User::factory()->create(['type' => 0]))
            ->get(route('admin.spm.dashboard'))
            ->assertStatus(200);
    }

    public function test_batang_diurutkan_paling_tertinggal_di_atas(): void
    {
        $this->kategori('Pelayanan Tinggi', ['sasaran' => 100, 'tw1' => 90]);   // 90 %
        $this->kategori('Pelayanan Rendah', ['sasaran' => 100, 'tw1' => 10]);   // 10 %
        $this->kategori('Pelayanan Sedang', ['sasaran' => 100, 'tw1' => 50]);   // 50 %

        $batang = $this->buka()->viewData('grafik')['batang'];

        $this->assertSame(['Pelayanan Rendah', 'Pelayanan Sedang', 'Pelayanan Tinggi'], array_column($batang, 'nama'));
    }

    public function test_kategori_tanpa_persen_ditaruh_terakhir(): void
    {
        $this->kategori('Pelayanan Rendah', ['sasaran' => 100, 'tw1' => 10]);
        $this->kategori('Pelayanan Belum', ['sasaran' => 100]);

        $batang = $this->buka()->viewData('grafik')['batang'];

        $this->assertSame('Pelayanan Rendah', $batang[0]['nama']);
        $this->assertNull($batang[1]['persen']);
        $this->assertSame('Pelayanan Belum', $batang[1]['nama']);
    }

    public function test_garis_membawa_kumulatif_dan_prorata_penuh(): void
    {
        $this->kategori('Pelayanan A', ['sasaran' => 1000, 'tw1' => 200, 'tw2' => 150]);

        $garis = $this->buka()->viewData('grafik')['garis'];

        $this->assertSame([200.0, 350.0, null, null], $garis[0]['kumulatif']);
        $this->assertSame([250.0, 500.0, 750.0, 1000.0], $garis[0]['prorata'], 'garis target selalu penuh 4 titik');
    }

    public function test_halaman_memuat_kedua_kanvas_dan_pemilih_kategori(): void
    {
        $this->kategori('Pelayanan A', ['sasaran' => 1000, 'tw1' => 200]);

        $this->buka()
            ->assertSee('spmBatang', false)
            ->assertSee('spmGaris', false)
            ->assertSee('spmPilihKategori', false)
            ->assertSee('cdn.jsdelivr.net/npm/chart.js', false);
    }

    public function test_tanpa_kategori_tidak_memuat_chart_js(): void
    {
        $this->buka()->assertDontSee('cdn.jsdelivr.net/npm/chart.js', false);
    }

    public function test_nama_kategori_berisi_html_ter_escape_di_payload_json(): void
    {
        $this->kategori('<script>alert(1)</script>', ['sasaran' => 100, 'tw1' => 10]);

        $html = $this->buka()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SpmGrafikTest`
Expected: FAIL on `test_halaman_memuat_kedua_kanvas_dan_pemilih_kategori` — `spmBatang` not found (the ordering tests already pass from Task 6).

- [ ] **Step 3: Add the chart panels to the view**

In `resources/views/admin/spm/dashboard.blade.php`, inside the `@else` branch, between the `.spm-cards` block and the "Rincian per Kategori" panel, insert:

```blade
        <div class="spm-panel">
            <div class="spm-panel__header">Ketercapaian per Kategori — paling tertinggal di atas</div>
            <div style="padding: 18px 22px;">
                <canvas id="spmBatang" height="{{ max(160, count($grafik['batang']) * 34) }}"></canvas>
            </div>
        </div>

        <div class="spm-panel">
            <div class="spm-panel__header">Laju Kumulatif per Triwulan</div>
            <div style="padding: 18px 22px;">
                <div style="margin-bottom: 14px;">
                    <label for="spmPilihKategori" style="font-weight:700; font-size:0.8125rem; margin-right:8px;">Kategori</label>
                    <select id="spmPilihKategori" style="height:38px; padding:0 12px; border-radius:10px; border:1.5px solid var(--spm-border); font-family:'Barlow',sans-serif; font-weight:600;">
                        @foreach ($grafik['garis'] as $g)
                            <option value="{{ $g['id'] }}">{{ $g['nama'] }}</option>
                        @endforeach
                    </select>
                </div>
                <canvas id="spmGaris" height="240"></canvas>
                <p style="font-size:0.75rem; color:var(--spm-muted); margin-top:10px;">
                    Garis putus-putus = target penuh tiap triwulan (25/50/75/100% sasaran).
                    Titik kumulatif berhenti di triwulan terakhir yang dilaporkan — triwulan yang belum masuk
                    tidak digambar sebagai nol.
                </p>
            </div>
        </div>
```

Then append at the end of the file:

```blade
@if (count($baris) > 0)
@section('custom_scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function () {
    var data = @json($grafik);

    var batang = new Chart(document.getElementById('spmBatang').getContext('2d'), {
        type: 'bar',
        data: {
            labels: data.batang.map(function (d) { return d.nama; }),
            datasets: [{
                label: 'Capaian (%)',
                data: data.batang.map(function (d) { return d.persen === null ? 0 : d.persen; }),
                backgroundColor: data.batang.map(function (d) { return d.warna; }),
                borderRadius: 6,
                barThickness: 18
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function (ctx) {
                            var d = data.batang[ctx.dataIndex];
                            if (d.persen === null) { return 'Belum dilaporkan'; }
                            return d.persen.toFixed(1).replace('.', ',') + '%';
                        }
                    }
                }
            },
            scales: {
                x: { beginAtZero: true, ticks: { callback: function (v) { return v + '%'; } } },
                y: { ticks: { font: { family: 'Barlow', size: 11 } } }
            }
        }
    });

    var ctxGaris = document.getElementById('spmGaris').getContext('2d');
    var garis = null;

    function gambarGaris(id) {
        var pilihan = data.garis.filter(function (g) { return String(g.id) === String(id); })[0];
        if (!pilihan) { return; }

        if (garis) { garis.destroy(); }

        garis = new Chart(ctxGaris, {
            type: 'line',
            data: {
                labels: ['TW I', 'TW II', 'TW III', 'TW IV'],
                datasets: [
                    {
                        label: 'Kumulatif (' + pilihan.satuan + ')',
                        data: pilihan.kumulatif,
                        borderColor: '#047857',
                        backgroundColor: 'rgba(4, 120, 87, 0.12)',
                        tension: 0.25,
                        fill: true,
                        spanGaps: false
                    },
                    {
                        label: 'Target prorata',
                        data: pilihan.prorata,
                        borderColor: '#64748b',
                        borderDash: [6, 4],
                        pointRadius: 0,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { font: { family: 'Barlow' } } } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    var pemilih = document.getElementById('spmPilihKategori');
    if (pemilih) {
        gambarGaris(pemilih.value);
        pemilih.addEventListener('change', function () { gambarGaris(this.value); });
    }
})();
</script>
@endsection
@endif
```

- [ ] **Step 4: Clear the view cache and run the tests**

```bash
php artisan view:clear
php artisan test --filter=SpmGrafikTest
```
Expected: PASS (6 tests).

- [ ] **Step 5: Re-run the dashboard suite**

Run: `php artisan test --filter=SpmDashboardTest`
Expected: PASS — the empty-state tests must still pass, because Chart.js is only emitted when there is at least one category.

- [ ] **Step 6: Check it in the browser**

Open `/admin/spm-dashboard` with at least three categories (one tercapai, one kritis, one belum dilaporkan). Confirm: bar chart sorted with the worst on top, the line chart switches when the select changes, no console errors, and the page is readable at 375 px width.

- [ ] **Step 7: Commit**

```bash
git add resources/views/admin/spm/dashboard.blade.php tests/Feature/Spm/SpmGrafikTest.php
git commit -m "feat(spm): grafik batang tertinggal-dulu & garis kumulatif vs prorata"
```

---

### Task 8: Menu sidebar & catatan CLAUDE.md

**Files:**
- Modify: `resources/views/vendor/admin/layouts/partials/leftsidebar.blade.php` (3 role branches: ±21, ±91, ±112, ±142)
- Modify: `CLAUDE.md`
- Test: `tests/Feature/Spm/MenuSpmTest.php`

**Interfaces:**
- Consumes: `admin.spm.dashboard` (Task 6), `admin.masterdata.spm.index` (Task 3).
- Produces: menu SPM; tidak ada antarmuka baru untuk kode lain.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Spm/MenuSpmTest.php`:

```php
<?php

namespace Tests\Feature\Spm;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuSpmTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_melihat_kedua_menu(): void
    {
        $response = $this->actingAs(User::factory()->create(['type' => 0]))
            ->get(route('admin.spm.dashboard'))
            ->assertStatus(200);

        $response->assertSee(route('admin.spm.dashboard'), false);
        $response->assertSee(route('admin.masterdata.spm.index'), false);
    }

    public function test_admin_biasa_melihat_menu_dasbor_tanpa_master_data(): void
    {
        $response = $this->actingAs(User::factory()->create(['type' => 1]))
            ->get(route('admin.spm.dashboard'))
            ->assertStatus(200);

        $response->assertSee(route('admin.spm.dashboard'), false);
        $response->assertDontSee(route('admin.masterdata.spm.index'), false);
    }

    public function test_faskes_surveilans_melihat_menu_dasbor(): void
    {
        $faskes = User::factory()->create(['type' => 1, 'role' => 'surveilans_puskesmas']);

        $this->actingAs($faskes)
            ->get(route('admin.spm.dashboard'))
            ->assertStatus(200)
            ->assertSee(route('admin.spm.dashboard'), false);
    }

    public function test_menu_dasbor_menyala_saat_halaman_spm_dibuka(): void
    {
        $html = $this->actingAs(User::factory()->create(['type' => 0]))
            ->get(route('admin.spm.dashboard'))
            ->assertStatus(200)
            ->getContent();

        // Submenu Dashboard harus terbuka (bukan tertutup) saat rute SPM aktif.
        $this->assertStringContainsString('style="display:block;"', $html);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MenuSpmTest`
Expected: FAIL on `test_superadmin_melihat_kedua_menu` — the sidebar has no SPM link yet.

- [ ] **Step 3: Edit the sidebar — superadmin branch**

In `resources/views/vendor/admin/layouts/partials/leftsidebar.blade.php`:

Line ±21, add `'admin.spm.dashboard'` to the superadmin `$dashboard` list:

```php
					$dashboard = request()->routeIs('admin.analytics', 'admin.imunisasiDashboard', 'admin.map', 'admin.earlyWarning', 'admin.intervensi.index', 'admin.verifikasiRt.*', 'admin.aksesTautan.*', 'admin.epidemiologi.dashboard', 'admin.epidemiologi.map', 'admin.pd3i.dashboard', 'admin.timbang.*', 'admin.spm.dashboard', 'admin.home', 'super.admin.home');
```

After the "Surveilans (legacy)" item (line ±39), add:

```blade
						<li><a href="{{route('admin.spm.dashboard')}}" class="{{ request()->routeIs('admin.spm.dashboard') ? 'active' : '' }}">SPM</a></li>
```

After the "Jumlah Penduduk" master data item (line ±93), add:

```blade
						<li><a href="{{route('admin.masterdata.spm.index')}}" class="{{ request()->routeIs('admin.masterdata.spm.*') ? 'active' : '' }}">SPM</a></li>
```

- [ ] **Step 4: Edit the sidebar — faskes surveilans branch**

Line ±112, add `'admin.spm.dashboard'`:

```php
					$dashboard = request()->routeIs('admin.epidemiologi.dashboard', 'admin.pd3i.dashboard', 'admin.spm.dashboard', 'admin.home');
```

After its "Surveilans (legacy)" item (line ±122), add:

```blade
						<li><a href="{{route('admin.spm.dashboard')}}" class="{{ request()->routeIs('admin.spm.dashboard') ? 'active' : '' }}">SPM</a></li>
```

- [ ] **Step 5: Edit the sidebar — legacy admin branch**

Line ±142, add `'admin.spm.dashboard'`:

```php
					$dashboard = request()->routeIs('admin.analytics', 'admin.imunisasiDashboard', 'admin.map', 'admin.earlyWarning', 'admin.intervensi.index', 'admin.verifikasiRt.*', 'admin.aksesTautan.*', 'admin.spm.dashboard', 'admin.home');
```

After its "Verifikasi RT" item (line ±158), add:

```blade
						<li><a href="{{route('admin.spm.dashboard')}}" class="{{ request()->routeIs('admin.spm.dashboard') ? 'active' : '' }}">SPM</a></li>
```

- [ ] **Step 6: Clear the view cache and run the test**

```bash
php artisan view:clear
php artisan test --filter=MenuSpmTest
```
Expected: PASS (4 tests).

- [ ] **Step 7: Add the CLAUDE.md note**

Append this section to `CLAUDE.md` (project root), after the last existing `###` section:

```markdown
### SPM manual: NULL triwulan, dan prorata yang mengikuti laporan

Modul SPM (`spm_kategori` + `spm_capaian`, spec
`docs/superpowers/specs/2026-09-29-dasbor-spm-design.md`) berisi angka yang **diisi tangan**,
bukan dihitung dari data anak — beda dari kartu SPM K1–K4 di dasbor Kesmas.

- **`tw1..tw4` nullable tanpa DEFAULT.** NULL = triwulan belum dilaporkan, 0 = capaiannya nol.
  Endpoint `angka` **selalu** menulis ulang keempat kunci termasuk sebagai NULL; kalau hanya
  yang terisi yang dikirim, `updateOrCreate` mempertahankan angka lama dan salah ketik yang
  sudah dihapus petugas tidak bisa dibatalkan dari UI. Dikunci
  `SimpanAngkaSpmTest::test_mengosongkan_kembali_triwulan_yang_terisi_menghapus_nilainya`.
- **Prorata diukur terhadap triwulan yang DILAPORKAN (`twTerisi`), bukan triwulan kalender.**
  Kategori yang baru lapor s.d. TW II pada bulan September tidak dicap gagal. Keterlambatan
  laporan adalah penanda terpisah (`laporanTertinggal()`, `twKosong()`). Kalau prorata dipatok
  ke kalender, setiap laporan yang telat terlihat seperti kegagalan program — dan itu jauh
  lebih sering.
- **Rata-rata dasbor = rata-rata aritmetik persen antar kategori, tanpa pembobotan**, dan
  kategori yang belum dilaporkan **tidak** dihitung 0. Satuan antar kategori berbeda, jadi
  `Σ kumulatif ÷ Σ sasaran` tidak bermakna.
- `CapaianSpm` dan `RingkasanSpm` **tidak boleh** memanggil `config()`/`now()` — unit test
  proyek ini memakai `PHPUnit\Framework\TestCase` polos tanpa boot aplikasi. Ambang dari
  `config('spm.ambang')` diteruskan controller sebagai parameter.
```

- [ ] **Step 8: Run the whole SPM suite**

```bash
php artisan test --filter="Spm"
```
Expected: PASS — `SpmModelTest`, `CapaianSpmTest`, `RingkasanSpmTest`, `MasterDataSpmTest`, `SimpanAngkaSpmTest`, `SpmDashboardTest`, `SpmGrafikTest`, `MenuSpmTest`.

- [ ] **Step 9: Run the suites most likely to be disturbed by the routes and sidebar edits**

```bash
php artisan test --filter="MasterDataPenyakitTest|MasterDataVaksinTest|TimbangDashboardTerkunciTest|LandingPublikTest"
```
Expected: PASS. (Do not start a second test process in parallel — `sirindu_testing` is shared.)

- [ ] **Step 10: Commit**

```bash
git add resources/views/vendor/admin/layouts/partials/leftsidebar.blade.php CLAUDE.md tests/Feature/Spm/MenuSpmTest.php
git commit -m "feat(spm): menu sidebar SPM & catat jebakan NULL triwulan di CLAUDE.md"
```

---

## Verifikasi akhir sebelum menyatakan selesai

- [ ] `php artisan test --filter="Spm"` hijau, dengan jumlah tes disebutkan apa adanya.
- [ ] `php artisan migrate` berjalan di database dev `sirindu` (bukan hanya `sirindu_testing`).
- [ ] Buka `/admin/master-data/spm` sebagai superadmin: tambah kategori, isi angka, kosongkan satu TW lalu simpan dan buka lagi — harus tetap kosong.
- [ ] Buka `/admin/spm-dashboard` sebagai superadmin **dan** sebagai admin biasa: kartu, grafik, tabel terisi; menu Master Data SPM hanya muncul untuk superadmin.
- [ ] Buka `/admin/master-data/spm` sebagai admin biasa: 403.
- [ ] Lebar 375 px: tidak ada scroll horizontal pada halaman (tabel boleh scroll di dalam `.table-responsive`).

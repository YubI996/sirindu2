# Rekap Sasaran Imunisasi + Fix Import & Tabel Anak — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tambah rekap sasaran imunisasi harian (hari ini/besok) di halaman Proyeksi, plus dua perbaikan: selektor format tanggal saat import imunisasi dan fix dropdown terpotong di tabel `/data-dasar-anak`.

**Architecture:** Tiga fase mandiri. **D2** menambah kolom `import_logs.date_format` yang menyetir `ImunisasiImport::parseDate()`. **D1** merestruktur scroll tabel DataTables di satu view. **C** unit baru (`SasaranImunisasiService` + `Controller` + `Export`) yang di-load AJAX dari section baru di halaman Proyeksi. Tak ada ketergantungan urutan antar-fase; kerjakan D2 → D1 → C.

**Tech Stack:** Laravel 12, PHP 8.4, PHPUnit + `RefreshDatabase`, Maatwebsite/Excel, DataTables (jQuery) di halaman anak, vanilla JS `fetch` di halaman Proyeksi, Carbon.

## Global Constraints

- MySQL lokal: user `root`, password kosong.
- Checkbox boolean pakai `$request->boolean()` (tidak relevan di plan ini, tapi berlaku umum).
- Test wajib `use RefreshDatabase;`. Master vaksin di-seed via `$this->seed(\Database\Seeders\JenisVaksinSeeder::class);`.
- Superadmin di test: `User::factory()->create(['type' => 0])`. Admin biasa: `['type' => 1]`.
- Anak dibuat langsung via `Anak::create([...])` (tidak ada factory Anak). Field wajib: `nama, nik, jk, tempat_lahir, tgl_lahir, status`.
- Jalankan test dengan binary Laragon: `& D:\apps\laragon\bin\php\php-8.4.7-Win32-vs17-x64\php.exe artisan test --filter=<Name>` (PowerShell) atau `php artisan test` bila `php` sudah di PATH.
- Import date_format valid values: `dmy` | `mdy` | `auto`. Default `auto` untuk tipe non-imunisasi; default `dmy` (via form) untuk imunisasi.
- Commit sering (tiap task). Jangan push kecuali diminta. Branch dulu bila masih di `main`.

---

## FASE D2 — Selektor Format Tanggal Import Imunisasi

### Task 1: Kolom `import_logs.date_format` + fillable

**Files:**
- Create: `database/migrations/2026_08_05_100000_add_date_format_to_import_logs_table.php`
- Modify: `app/Models/ImportLog.php:9-20` (tambah `date_format` ke `$fillable`)
- Test: `tests/Feature/Imports/ImportImunisasiDateFormatTest.php`

**Interfaces:**
- Produces: kolom `import_logs.date_format` (nullable string 10); `ImportLog` mass-assignable pada `date_format`.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php
namespace Tests\Feature\Imports;

use App\Models\ImportLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportImunisasiDateFormatTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_logs_has_date_format_column_and_is_fillable(): void
    {
        $this->assertTrue(Schema::hasColumn('import_logs', 'date_format'));

        $user = User::factory()->create(['type' => 0]);
        $log = ImportLog::create([
            'user_id' => $user->id, 'filename' => 'x.csv', 'file_path' => 'imports/imunisasi/x.csv',
            'type' => 'imunisasi', 'status' => 'pending', 'date_format' => 'mdy',
        ]);

        $this->assertSame('mdy', $log->fresh()->date_format);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan GAGAL**

Run: `php artisan test --filter=ImportImunisasiDateFormatTest::test_import_logs_has_date_format_column_and_is_fillable`
Expected: FAIL (kolom belum ada / tidak fillable).

- [ ] **Step 3: Buat migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_logs', function (Blueprint $table) {
            $table->string('date_format', 10)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('import_logs', function (Blueprint $table) {
            $table->dropColumn('date_format');
        });
    }
};
```

- [ ] **Step 4: Tambah ke `$fillable`**

Di `app/Models/ImportLog.php`, tambahkan `'date_format',` setelah `'type',` di array `$fillable`.

- [ ] **Step 5: Jalankan test, pastikan LULUS**

Run: `php artisan test --filter=ImportImunisasiDateFormatTest::test_import_logs_has_date_format_column_and_is_fillable`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations app/Models/ImportLog.php tests/Feature/Imports/ImportImunisasiDateFormatTest.php
git commit -m "feat(import): kolom import_logs.date_format"
```

---

### Task 2: `ImunisasiImport` parsing berdasarkan format

**Files:**
- Modify: `app/Imports/ImunisasiImport.php:31-55` (property + konstruktor), `:64-73` (`parseDate`)
- Test: `tests/Feature/Imports/ImportImunisasiDateFormatTest.php` (tambah metode)

**Interfaces:**
- Consumes: master vaksin dari `JenisVaksinSeeder`.
- Produces: `new ImunisasiImport(int $userId, string $dateFormat = 'auto')`; `parseDate()` menghormati `dmy`/`mdy`/`auto`. Serial Excel numerik tetap seperti sekarang.

- [ ] **Step 1: Tulis test yang gagal**

```php
// Tambahkan di ImportImunisasiDateFormatTest, plus imports berikut di atas:
// use App\Imports\ImunisasiImport;
// use App\Models\Anak;
// use App\Models\JenisVaksin;
// use Database\Seeders\JenisVaksinSeeder;
// use Illuminate\Support\Collection;

private function seedVaksinDanAnak(): Anak
{
    $this->seed(JenisVaksinSeeder::class);
    return Anak::create([
        'nama' => 'Budi Santoso', 'nik' => '3201011501200001', 'jk' => 1,
        'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2020-01-15', 'status' => 1,
    ]);
}

private function longRows(string $tglLahir, string $tglPemberian): Collection
{
    return collect([
        ['nik_anak','nama_anak','tgl_lahir_anak','kode_vaksin','tanggal_pemberian','status'],
        ['3201011501200001','Budi Santoso',$tglLahir,'HB0',$tglPemberian,'sudah'],
    ]);
}

public function test_dmy_parses_day_first(): void
{
    $anak = $this->seedVaksinDanAnak();
    $admin = User::factory()->create(['type' => 0]);
    (new ImunisasiImport($admin->id, 'dmy'))->collection($this->longRows('15/01/2020', '13/04/2026'));

    $hb0 = JenisVaksin::where('kode', 'HB0')->value('id');
    $this->assertDatabaseHas('imunisasi', [
        'id_anak' => $anak->id, 'id_jenis_vaksin' => $hb0, 'tanggal_pemberian' => '2026-04-13',
    ]);
}

public function test_mdy_parses_month_first(): void
{
    $anak = $this->seedVaksinDanAnak();
    $admin = User::factory()->create(['type' => 0]);
    (new ImunisasiImport($admin->id, 'mdy'))->collection($this->longRows('01/15/2020', '04/13/2026'));

    $hb0 = JenisVaksin::where('kode', 'HB0')->value('id');
    $this->assertDatabaseHas('imunisasi', [
        'id_anak' => $anak->id, 'id_jenis_vaksin' => $hb0, 'tanggal_pemberian' => '2026-04-13',
    ]);
}

public function test_auto_default_parses_iso(): void
{
    $anak = $this->seedVaksinDanAnak();
    $admin = User::factory()->create(['type' => 0]);
    (new ImunisasiImport($admin->id))->collection($this->longRows('2020-01-15', '2026-04-13'));

    $hb0 = JenisVaksin::where('kode', 'HB0')->value('id');
    $this->assertDatabaseHas('imunisasi', [
        'id_anak' => $anak->id, 'id_jenis_vaksin' => $hb0, 'tanggal_pemberian' => '2026-04-13',
    ]);
}
```

- [ ] **Step 2: Jalankan, pastikan GAGAL**

Run: `php artisan test --filter=ImportImunisasiDateFormatTest`
Expected: `test_dmy_parses_day_first` & `test_mdy_parses_month_first` FAIL (konstruktor belum terima arg ke-2 / parseDate belum hormati format). `test_auto_default_parses_iso` mungkin sudah lulus.

- [ ] **Step 3: Tambah property + konstruktor**

Di `app/Imports/ImunisasiImport.php`, tambah property setelah `protected int $rowOffset = 0;`:

```php
    /** Format tanggal string: 'dmy' | 'mdy' | 'auto'. */
    protected string $dateFormat = 'auto';
```

Ganti konstruktor menjadi:

```php
    public function __construct(int $userId, string $dateFormat = 'auto')
    {
        $this->userId     = $userId;
        $this->dateFormat = in_array($dateFormat, ['dmy', 'mdy', 'auto'], true) ? $dateFormat : 'auto';
        $this->vaksinCache = JenisVaksin::pluck('id', 'kode')->toArray();
    }
```

- [ ] **Step 4: Perbarui `parseDate`**

```php
    protected function parseDate($value): ?string
    {
        if ($value === null || $value === '') return null;

        // Serial Excel numerik: format tak berlaku, konversi seperti biasa.
        if (is_numeric($value)) {
            try { return Carbon::instance(Date::excelToDateTimeObject((float) $value))->format('Y-m-d'); }
            catch (\Exception $e) { return null; }
        }

        $str = trim((string) $value);

        if ($this->dateFormat === 'dmy' || $this->dateFormat === 'mdy') {
            $fmt  = $this->dateFormat === 'dmy' ? 'd/m/Y' : 'm/d/Y';
            $norm = str_replace(['-', '.'], '/', $str); // toleransi pemisah - dan .
            try { return Carbon::createFromFormat($fmt, $norm)->format('Y-m-d'); }
            catch (\Exception $e) { return null; }
        }

        try { return Carbon::parse($str)->format('Y-m-d'); }
        catch (\Exception $e) { return null; }
    }
```

- [ ] **Step 5: Jalankan test, pastikan LULUS**

Run: `php artisan test --filter=ImportImunisasiDateFormatTest`
Expected: semua PASS.

- [ ] **Step 6: Pastikan test import lama tak rusak**

Run: `php artisan test --filter=ImunisasiImportWideTest`
Expected: PASS (konstruktor default `'auto'` menjaga perilaku lama).

- [ ] **Step 7: Commit**

```bash
git add app/Imports/ImunisasiImport.php tests/Feature/Imports/ImportImunisasiDateFormatTest.php
git commit -m "feat(import): ImunisasiImport hormati format tanggal dmy/mdy/auto"
```

---

### Task 3: Wiring form + controller + job

**Files:**
- Modify: `resources/views/admin/import/index.blade.php:513-517` (tambah select di form imunisasi)
- Modify: `app/Http/Controllers/ImportCsvController.php:94-149` (`handleUpload`), `:155-190` (`reimport`)
- Modify: `app/Jobs/ImportImunisasiJob.php:37`
- Test: `tests/Feature/Imports/ImportImunisasiDateFormatTest.php` (tambah metode)

**Interfaces:**
- Consumes: `import_logs.date_format` (Task 1), `ImunisasiImport($userId, $dateFormat)` (Task 2).
- Produces: form imunisasi mengirim `date_format`; log menyimpannya; job memakainya; reimport mewarisinya.

- [ ] **Step 1: Tulis feature test yang gagal**

```php
// Tambahkan imports: use Illuminate\Http\UploadedFile; use Illuminate\Support\Facades\Queue; use Illuminate\Support\Facades\Storage; use App\Jobs\ImportImunisasiJob;

public function test_upload_imunisasi_menyimpan_date_format_ke_log(): void
{
    Storage::fake('local');
    Queue::fake();
    $admin = User::factory()->create(['type' => 0]);

    $csv = "nik_anak,nama_anak,tgl_lahir_anak,kode_vaksin,tanggal_pemberian,status\n";
    $file = UploadedFile::fake()->createWithContent('im.csv', $csv);

    $this->actingAs($admin)
        ->post(route('admin.importCsv.imunisasi'), ['file_imunisasi' => $file, 'date_format' => 'mdy'])
        ->assertSessionHas('import_queued');

    $this->assertDatabaseHas('import_logs', ['type' => 'imunisasi', 'date_format' => 'mdy']);
    Queue::assertPushed(ImportImunisasiJob::class);
}
```

- [ ] **Step 2: Jalankan, pastikan GAGAL**

Run: `php artisan test --filter=ImportImunisasiDateFormatTest::test_upload_imunisasi_menyimpan_date_format_ke_log`
Expected: FAIL (`date_format` null di log).

- [ ] **Step 3: `handleUpload` — validasi + simpan**

Di `ImportCsvController::handleUpload`, setelah blok `$request->validate([...])` yang ada, tambahkan validasi khusus imunisasi dan hitung nilainya:

```php
        $dateFormat = null;
        if ($type === 'imunisasi') {
            $request->validate(['date_format' => 'nullable|in:dmy,mdy,auto']);
            $dateFormat = $request->input('date_format') ?: 'auto';
        }
```

Lalu di pemanggilan `ImportLog::create([...])`, tambahkan baris:

```php
            'date_format' => $dateFormat,
```

- [ ] **Step 4: `reimport` — wariskan date_format**

Di `ImportCsvController::reimport`, pada `ImportLog::create([...])` untuk `$newLog`, tambahkan:

```php
            'date_format' => $log->date_format,
```

- [ ] **Step 5: Job memakai format**

Di `app/Jobs/ImportImunisasiJob.php`, ganti baris `$import = new ImunisasiImport($this->importLog->user_id);` menjadi:

```php
            $import = new ImunisasiImport($this->importLog->user_id, $this->importLog->date_format ?? 'auto');
```

- [ ] **Step 6: Tambah select di form**

Di `resources/views/admin/import/index.blade.php`, di dalam `<form ... importCsv.imunisasi>`, sisipkan sebelum tombol submit (`<button type="submit" class="imp-btn-upload">`):

```blade
                <div class="imp-form-field" style="margin:.75rem 0;">
                    <label for="date-format-imunisasi" style="display:block;font-weight:600;font-size:.82rem;margin-bottom:.35rem;">
                        Format tanggal di file
                    </label>
                    <select id="date-format-imunisasi" name="date_format"
                            style="width:100%;padding:.5rem .7rem;border:1px solid #cbd5e1;border-radius:8px;">
                        <option value="dmy" selected>dd/mm/yyyy (hari/bulan/tahun)</option>
                        <option value="mdy">mm/dd/yyyy (bulan/hari/tahun)</option>
                        <option value="auto">Otomatis (deteksi)</option>
                    </select>
                </div>
```

> Form ini disubmit oleh handler `data-type="imunisasi"`. Bila handler memakai `new FormData(form)`, field ini otomatis ikut. Bila handler mengirim field manual, tambahkan `date_format` ke payload. Verifikasi di Step 8.

- [ ] **Step 7: Jalankan feature test, pastikan LULUS**

Run: `php artisan test --filter=ImportImunisasiDateFormatTest`
Expected: semua PASS.

- [ ] **Step 8: Verifikasi manual di browser**

Buka `/admin/import` (login superadmin), tab Imunisasi. Pastikan dropdown "Format tanggal" muncul. Upload file kecil dengan format `mdy`, lalu cek di panel status/`import_logs` bahwa `date_format` tersimpan. (Bila handler JS tidak mengirim field, sesuaikan handler di file yang sama untuk menyertakan `date_format`.)

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/ImportCsvController.php app/Jobs/ImportImunisasiJob.php resources/views/admin/import/index.blade.php tests/Feature/Imports/ImportImunisasiDateFormatTest.php
git commit -m "feat(import): selektor format tanggal di form imunisasi + wiring job/reimport"
```

---

## FASE D1 — Fix Dropdown Terpotong di `/data-dasar-anak`

### Task 4: Restruktur scroll tabel anak

**Files:**
- Modify: `resources/views/admin/anak/index.blade.php:153-167` (markup), `:171-184` (init DataTable)

**Interfaces:**
- Produces: tidak ada API baru. Perubahan visual + `scrollX` pada DataTable `#tabel-anak`.

**Latar:** `#tabel-anak` dibungkus `<div class="table-responsive">` (Bootstrap `overflow-x:auto`, memaksa `overflow-y:auto` terkomputasi) → menu "Show N entries" / kontrol DataTables terpotong saat tabel pendek. Perbaikan: jangan bungkus seluruh wrapper DataTables dengan konteks overflow; biar DataTables sendiri yang scroll body tabel via `scrollX`.

- [ ] **Step 1: Ganti pembungkus tabel**

Ganti blok `<div class="table-responsive"> ... </div>` (baris 153–167) menjadi tanpa `.table-responsive`:

```blade
<table id="tabel-anak" class="table table-striped table-sm" style="width:100%">
    <thead>
        <tr>
            <th scope="col">No</th>
            <th scope="col">NIK</th>
            <th scope="col">Nama</th>
            <th scope="col">Nama Ibu</th>
            <th scope="col">Pilihan</th>
        </tr>
    </thead>
    <tbody>
    </tbody>
</table>
```

- [ ] **Step 2: Aktifkan `scrollX` pada init**

Di blok `$('#tabel-anak').DataTable({ ... })`, tambahkan `scrollX: true,` tepat setelah `responsive: false,`:

```javascript
        var table = $('#tabel-anak').DataTable({
            responsive: false,
            scrollX: true,
            processing: true,
            serverSide: true,
            ajax: "{{ route('admin.getAnak') }}",
```

- [ ] **Step 3: Smoke test halaman tetap render**

Tambah test ringan di `tests/Feature/DataAnakIndexRendersTest.php`:

```php
<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataAnakIndexRendersTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_data_anak_render_200(): void
    {
        $admin = User::factory()->create(['type' => 0]);
        $this->actingAs($admin)
            ->get(route('admin.anak'))
            ->assertOk()
            ->assertSee('tabel-anak', false);
    }
}
```

Run: `php artisan test --filter=DataAnakIndexRendersTest`
Expected: PASS.

- [ ] **Step 4: Verifikasi manual di Chrome (WAJIB)**

Buka `/data-dasar-anak`. Ketik di kotak search hingga hasil tinggal 1–2 baris (tabel pendek). Klik menu **"Show N entries"** — pastikan opsi TIDAK terpotong. Cek juga scroll horizontal tabel bekerja di layar sempit. Bila masih terpotong, tambahkan override CSS di `@push('styles')` halaman ini:

```css
#tabel-anak_wrapper { overflow: visible; }
```

- [ ] **Step 5: Commit**

```bash
git add resources/views/admin/anak/index.blade.php tests/Feature/DataAnakIndexRendersTest.php
git commit -m "fix(anak): dropdown DataTables tak lagi terpotong di /data-dasar-anak"
```

---

## FASE C — Rekap Sasaran Hari Ini & Besok

### Definisi sasaran (dikunci di sini)

Anak `A` adalah **sasaran** antigen `V` pada tanggal `T` bila **salah satu**:

1. **Jalur `jadwal`:** ada baris `imunisasi` untuk `(A, V)` dengan `tanggal_selanjutnya = T`. Antigen = `V` (vaksin baris itu). `status` tampil = `sudah` bila baris berstatus `sudah`, selain itu `belum`. (Keterbatasan model: `tanggal_selanjutnya` disimpan pada baris vaksinnya sendiri; kita perlakukan vaksin baris itu sebagai antigen sasaran.)
2. **Jalur `umur`:** item `getJadwal($A)` dengan `tanggal_min = T` (yaitu `tgl_lahir + usia_pemberian_min` hari) dan `status ∉ {sudah, kadaluarsa, tidak_relevan}`. `status` tampil = `belum`.

**Dedup** per `(A, V)`; bila kedua jalur mengenai pasangan sama, **jalur `jadwal` menang**.

---

### Task 5: `SasaranImunisasiService`

**Files:**
- Create: `app/Services/SasaranImunisasiService.php`
- Test: `tests/Unit/Services/SasaranImunisasiServiceTest.php`

**Interfaces:**
- Consumes: `ImunisasiStatusService::getJadwal(Anak): array` (tiap item punya `vaksin: JenisVaksin`, `tanggal_min: string Y-m-d`, `status: string`).
- Produces:
  `SasaranImunisasiService::getSasaran(\Carbon\Carbon $tanggal, array $filters = []): \Illuminate\Support\Collection`
  → tiap elemen array: `{ anak: Anak, posyandu: ?string, vaksin: JenisVaksin, status: 'sudah'|'belum', sumber: 'jadwal'|'umur' }`.
  `$filters`: `id_kecamatan|id_kelurahan|id_posyandu` (paling spesifik menang).

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php
namespace Tests\Unit\Services;

use App\Models\Anak;
use App\Models\Imunisasi;
use App\Models\JenisVaksin;
use App\Models\User;
use App\Services\SasaranImunisasiService;
use Carbon\Carbon;
use Database\Seeders\JenisVaksinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SasaranImunisasiServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(JenisVaksinSeeder::class);
    }

    private function anak(array $o = []): Anak
    {
        return Anak::create(array_merge([
            'nama' => 'Budi', 'nik' => '3201010101200001', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => now()->toDateString(), 'status' => 1,
        ], $o));
    }

    public function test_jalur_umur_anak_lahir_hari_ini_jadi_sasaran_bcg(): void
    {
        // BCG usia_pemberian_min = 0 → tanggal_min = tgl_lahir = hari ini.
        $anak = $this->anak(['tgl_lahir' => Carbon::today()->toDateString()]);

        $rows = app(SasaranImunisasiService::class)->getSasaran(Carbon::today());

        $bcg = $rows->first(fn ($r) => $r['vaksin']->kode === 'BCG');
        $this->assertNotNull($bcg);
        $this->assertSame('umur', $bcg['sumber']);
        $this->assertSame('belum', $bcg['status']);
        $this->assertTrue($bcg['anak']->is($anak));
    }

    public function test_jalur_umur_besok_untuk_dpt1(): void
    {
        // DPT-HB-HIB1 min = 60 hari. Lahir 59 hari lalu → tanggal_min = besok.
        $this->anak(['tgl_lahir' => Carbon::today()->subDays(59)->toDateString()]);

        $besok = app(SasaranImunisasiService::class)->getSasaran(Carbon::tomorrow());

        $this->assertTrue($besok->contains(fn ($r) => $r['vaksin']->kode === 'DPT-HB-HIB1' && $r['sumber'] === 'umur'));
    }

    public function test_jalur_jadwal_dari_tanggal_selanjutnya(): void
    {
        $anak = $this->anak(['tgl_lahir' => Carbon::today()->subYears(2)->toDateString()]);
        $bcgId = JenisVaksin::where('kode', 'BCG')->value('id');
        Imunisasi::create([
            'id_anak' => $anak->id, 'id_jenis_vaksin' => $bcgId, 'status' => 'belum',
            'tanggal_selanjutnya' => Carbon::today()->toDateString(),
        ]);

        $rows = app(SasaranImunisasiService::class)->getSasaran(Carbon::today());

        $row = $rows->first(fn ($r) => $r['vaksin']->kode === 'BCG');
        $this->assertNotNull($row);
        $this->assertSame('jadwal', $row['sumber']);
        $this->assertSame('belum', $row['status']);
    }

    public function test_dedup_jadwal_menang_atas_umur(): void
    {
        // Anak lahir hari ini → BCG jalur umur; sekaligus buat record tanggal_selanjutnya hari ini → jalur jadwal.
        $anak = $this->anak(['tgl_lahir' => Carbon::today()->toDateString()]);
        $bcgId = JenisVaksin::where('kode', 'BCG')->value('id');
        Imunisasi::create([
            'id_anak' => $anak->id, 'id_jenis_vaksin' => $bcgId, 'status' => 'belum',
            'tanggal_selanjutnya' => Carbon::today()->toDateString(),
        ]);

        $rows = app(SasaranImunisasiService::class)->getSasaran(Carbon::today());

        $bcgRows = $rows->filter(fn ($r) => $r['vaksin']->kode === 'BCG' && $r['anak']->id === $anak->id);
        $this->assertCount(1, $bcgRows);
        $this->assertSame('jadwal', $bcgRows->first()['sumber']);
    }

    public function test_filter_wilayah_membatasi_hasil(): void
    {
        $kelA = \App\Models\Kelurahan::factory()->create();
        $kelB = \App\Models\Kelurahan::factory()->create();
        $this->anak(['nik' => '3201010101200002', 'tgl_lahir' => Carbon::today()->toDateString(), 'id_kel' => $kelA->id]);
        $this->anak(['nik' => '3201010101200003', 'tgl_lahir' => Carbon::today()->toDateString(), 'id_kel' => $kelB->id]);

        $rows = app(SasaranImunisasiService::class)->getSasaran(Carbon::today(), ['id_kelurahan' => $kelA->id]);

        $this->assertTrue($rows->every(fn ($r) => $r['anak']->id_kel === $kelA->id));
        $this->assertTrue($rows->isNotEmpty());
    }
}
```

- [ ] **Step 2: Jalankan, pastikan GAGAL**

Run: `php artisan test --filter=SasaranImunisasiServiceTest`
Expected: FAIL (kelas belum ada).

- [ ] **Step 3: Implementasi service**

```php
<?php
namespace App\Services;

use App\Models\Anak;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SasaranImunisasiService
{
    public function __construct(private ImunisasiStatusService $status) {}

    /**
     * @param array{id_kecamatan?:int,id_kelurahan?:int,id_posyandu?:int} $filters
     * @return Collection<int, array{anak:Anak, posyandu:?string, vaksin:\App\Models\JenisVaksin, status:string, sumber:string}>
     */
    public function getSasaran(Carbon $tanggal, array $filters = []): Collection
    {
        $t   = $tanggal->toDateString();
        $out = [];

        foreach ($this->anakQuery($filters)->get() as $anak) {
            // Jalur jadwal: record dengan tanggal_selanjutnya == T.
            foreach ($anak->imunisasi as $im) {
                if (! $im->jenisVaksin) continue;
                if (optional($im->tanggal_selanjutnya)->toDateString() !== $t) continue;

                $key = $anak->id . '-' . $im->id_jenis_vaksin;
                $out[$key] = [
                    'anak'     => $anak,
                    'posyandu' => $anak->posyandu?->name,
                    'vaksin'   => $im->jenisVaksin,
                    'status'   => $im->status === 'sudah' ? 'sudah' : 'belum',
                    'sumber'   => 'jadwal',
                ];
            }

            // Jalur umur: tanggal_min == T, masih bisa dikejar.
            foreach ($this->status->getJadwal($anak) as $item) {
                if ($item['tanggal_min'] !== $t) continue;
                if (in_array($item['status'], ['sudah', 'kadaluarsa', 'tidak_relevan'], true)) continue;

                $key = $anak->id . '-' . $item['vaksin']->id;
                if (isset($out[$key])) continue; // jadwal menang

                $out[$key] = [
                    'anak'     => $anak,
                    'posyandu' => $anak->posyandu?->name,
                    'vaksin'   => $item['vaksin'],
                    'status'   => 'belum',
                    'sumber'   => 'umur',
                ];
            }
        }

        return collect(array_values($out))
            ->sortBy(fn ($r) => ($r['posyandu'] ?? '~') . '|' . $r['anak']->nama)
            ->values();
    }

    private function anakQuery(array $filters)
    {
        $q = Anak::with(['imunisasi.jenisVaksin', 'posyandu', 'kel'])->orderBy('nama');

        if (!empty($filters['id_posyandu'])) {
            $q->where('id_posyandu', $filters['id_posyandu']);
        } elseif (!empty($filters['id_kelurahan'])) {
            $q->where('id_kel', $filters['id_kelurahan']);
        } elseif (!empty($filters['id_kecamatan'])) {
            $q->where('id_kec', $filters['id_kecamatan']);
        }

        return $q;
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan LULUS**

Run: `php artisan test --filter=SasaranImunisasiServiceTest`
Expected: semua PASS. (Bila `test_filter_wilayah` gagal karena field `id_kel` tak ter-set pada Anak, pastikan `Anak::create` menerima `id_kel` — kolom itu sudah dipakai di query dashboard, jadi seharusnya fillable.)

- [ ] **Step 5: Commit**

```bash
git add app/Services/SasaranImunisasiService.php tests/Unit/Services/SasaranImunisasiServiceTest.php
git commit -m "feat(imunisasi): SasaranImunisasiService (gabungan jadwal + umur)"
```

---

### Task 6: `SasaranImunisasiController::data` + route

**Files:**
- Create: `app/Http/Controllers/SasaranImunisasiController.php`
- Modify: `routes/web.php` (dalam grup admin, dekat baris 143 `admin.imunisasiDashboard`)
- Test: `tests/Feature/SasaranImunisasiEndpointTest.php`

**Interfaces:**
- Consumes: `SasaranImunisasiService::getSasaran()` (Task 5).
- Produces: route `admin.sasaran.data` (GET) → JSON `{ data: [{hashid,nama,nik,posyandu,antigen,status}] }`; helper `resolveTanggal(?string): Carbon` (`today`|`tomorrow`|`Y-m-d`, default `today`) & `filters(Request): array`.

- [ ] **Step 1: Tulis feature test yang gagal**

```php
<?php
namespace Tests\Feature;

use App\Models\Anak;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\JenisVaksinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SasaranImunisasiEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(JenisVaksinSeeder::class);
    }

    public function test_endpoint_data_mengembalikan_sasaran_hari_ini(): void
    {
        $admin = User::factory()->create(['type' => 0]);
        Anak::create([
            'nama' => 'Budi', 'nik' => '3201010101200001', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => Carbon::today()->toDateString(), 'status' => 1,
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.sasaran.data', ['tanggal' => 'today']))
            ->assertOk()
            ->assertJsonStructure(['data' => [['hashid', 'nama', 'nik', 'posyandu', 'antigen', 'status']]])
            ->assertJsonFragment(['antigen' => 'BCG']);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan GAGAL**

Run: `php artisan test --filter=SasaranImunisasiEndpointTest::test_endpoint_data_mengembalikan_sasaran_hari_ini`
Expected: FAIL (route belum ada).

- [ ] **Step 3: Buat controller**

```php
<?php
namespace App\Http\Controllers;

use App\Services\SasaranImunisasiService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SasaranImunisasiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function data(Request $request)
    {
        $tanggal = $this->resolveTanggal($request->query('tanggal'));
        $rows    = app(SasaranImunisasiService::class)->getSasaran($tanggal, $this->filters($request));

        return response()->json([
            'tanggal' => $tanggal->toDateString(),
            'data'    => $rows->map(fn ($r) => [
                'hashid'   => $r['anak']->hashid,
                'nama'     => $r['anak']->nama,
                'nik'      => $r['anak']->nik,
                'posyandu' => $r['posyandu'] ?? '-',
                'antigen'  => $r['vaksin']->nama,
                'status'   => $r['status'],
            ])->values(),
        ]);
    }

    protected function resolveTanggal(?string $v): Carbon
    {
        if ($v === 'tomorrow') return Carbon::tomorrow();
        if ($v === null || $v === '' || $v === 'today') return Carbon::today();
        return Carbon::hasFormat($v, 'Y-m-d') ? Carbon::parse($v)->startOfDay() : Carbon::today();
    }

    protected function filters(Request $request): array
    {
        return array_filter([
            'id_kecamatan' => $request->integer('id_kecamatan') ?: null,
            'id_kelurahan' => $request->integer('id_kelurahan') ?: null,
            'id_posyandu'  => $request->integer('id_posyandu') ?: null,
        ]);
    }
}
```

> Catatan: `route('admin.sasaran.data', ['tanggal'=>'today'])` mengembalikan `antigen` = `nama` vaksin. Test memakai fragment `'BCG'`; nama vaksin BCG di seeder = `'BCG'`, cocok.

- [ ] **Step 4: Daftarkan route**

Di `routes/web.php`, dalam grup admin yang sama dengan `admin.imunisasiDashboard` (sekitar baris 143), tambahkan:

```php
    Route::get('sasaran-imunisasi/data',   [App\Http\Controllers\SasaranImunisasiController::class, 'data'])->name('admin.sasaran.data');
    Route::get('sasaran-imunisasi/export', [App\Http\Controllers\SasaranImunisasiController::class, 'export'])->name('admin.sasaran.export');
```

> Route `export` dipasang sekarang agar Task 7 tinggal menambah method; bila `export` belum ada saat Task 6 dijalankan, PHP tak error hanya bila route tak dipanggil. Aman karena test Task 6 hanya memanggil `data`. (Jika linimasa route menuntut method ada, pindahkan baris `export` ke Task 7 Step 4.)

- [ ] **Step 5: Jalankan test, pastikan LULUS**

Run: `php artisan test --filter=SasaranImunisasiEndpointTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/SasaranImunisasiController.php routes/web.php tests/Feature/SasaranImunisasiEndpointTest.php
git commit -m "feat(imunisasi): endpoint admin.sasaran.data (rekap sasaran JSON)"
```

---

### Task 7: `SasaranImunisasiExport` + `export`

**Files:**
- Create: `app/Exports/SasaranImunisasiExport.php`
- Modify: `app/Http/Controllers/SasaranImunisasiController.php` (tambah method `export`)
- Test: `tests/Feature/SasaranImunisasiEndpointTest.php` (tambah metode)

**Interfaces:**
- Consumes: `SasaranImunisasiService::getSasaran()` (Task 5).
- Produces: `new SasaranImunisasiExport(Carbon $tanggal, array $filters)` (`FromCollection, WithHeadings, WithMapping`), method `filename(): string`; route `admin.sasaran.export` → unduhan `.xlsx`.

- [ ] **Step 1: Tulis feature test yang gagal**

```php
// Tambahkan di SasaranImunisasiEndpointTest:
public function test_endpoint_export_mengembalikan_xlsx(): void
{
    $admin = User::factory()->create(['type' => 0]);
    Anak::create([
        'nama' => 'Budi', 'nik' => '3201010101200009', 'jk' => 1,
        'tempat_lahir' => 'Bontang', 'tgl_lahir' => Carbon::today()->toDateString(), 'status' => 1,
    ]);

    $res = $this->actingAs($admin)->get(route('admin.sasaran.export', ['tanggal' => 'today']));

    $res->assertOk();
    $this->assertStringContainsString(
        'spreadsheetml',
        $res->headers->get('content-type') . $res->headers->get('content-disposition')
    );
    $this->assertStringContainsString('sasaran-imunisasi-', $res->headers->get('content-disposition'));
}
```

- [ ] **Step 2: Jalankan, pastikan GAGAL**

Run: `php artisan test --filter=SasaranImunisasiEndpointTest::test_endpoint_export_mengembalikan_xlsx`
Expected: FAIL (method `export` belum ada).

- [ ] **Step 3: Buat export class**

```php
<?php
namespace App\Exports;

use App\Services\SasaranImunisasiService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SasaranImunisasiExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private Carbon $tanggal, private array $filters = []) {}

    public function collection(): Collection
    {
        return app(SasaranImunisasiService::class)->getSasaran($this->tanggal, $this->filters);
    }

    public function headings(): array
    {
        return ['Nama', 'NIK', 'Posyandu', 'Kelurahan', 'Antigen', 'Status', 'Sumber', 'Tanggal'];
    }

    public function map($row): array
    {
        return [
            $row['anak']->nama,
            $row['anak']->nik,
            $row['posyandu'] ?? '-',
            $row['anak']->kel?->name ?? '-',
            $row['vaksin']->nama,
            $row['status'] === 'sudah' ? 'Sudah' : 'Belum',
            $row['sumber'] === 'jadwal' ? 'Jadwal' : 'Umur',
            $this->tanggal->toDateString(),
        ];
    }

    public function filename(): string
    {
        return 'sasaran-imunisasi-' . $this->tanggal->toDateString() . '.xlsx';
    }
}
```

- [ ] **Step 4: Tambah method `export` di controller**

Tambahkan `use App\Exports\SasaranImunisasiExport;` dan `use Maatwebsite\Excel\Facades\Excel;` di atas, lalu method:

```php
    public function export(Request $request)
    {
        $tanggal = $this->resolveTanggal($request->query('tanggal'));
        $export  = new SasaranImunisasiExport($tanggal, $this->filters($request));

        return Excel::download($export, $export->filename());
    }
```

(Route `admin.sasaran.export` sudah didaftarkan di Task 6 Step 4.)

- [ ] **Step 5: Jalankan test, pastikan LULUS**

Run: `php artisan test --filter=SasaranImunisasiEndpointTest`
Expected: semua PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Exports/SasaranImunisasiExport.php app/Http/Controllers/SasaranImunisasiController.php tests/Feature/SasaranImunisasiEndpointTest.php
git commit -m "feat(imunisasi): export Excel rekap sasaran"
```

---

### Task 8: Section UI di halaman Proyeksi

**Files:**
- Modify: `app/Http/Controllers/AdminController.php:2219-2230` (tambah 3 list wilayah ke `compact`), sebelum `return view(...)` di `earlyWarningSystem`
- Modify: `resources/views/admin/dashboard/early-warning.blade.php` (section baru sebelum `@endsection` konten di baris ~1160; JS di `@section('scripts')`)

**Interfaces:**
- Consumes: route `admin.sasaran.data` & `admin.sasaran.export` (Task 6–7).
- Produces: tidak ada API baru.

- [ ] **Step 1: Kirim list wilayah ke view**

Di `AdminController::earlyWarningSystem`, tepat sebelum `return view('admin.dashboard.early-warning', compact(`, tambahkan:

```php
        $kecamatanList = \App\Models\Kecamatan::orderBy('name')->get();
        $kelurahanList = \App\Models\Kelurahan::orderBy('name')->get();
        $posyanduList  = \App\Models\Posyandu::orderBy('name')->get();
```

Lalu tambahkan `'kecamatanList', 'kelurahanList', 'posyanduList',` ke dalam `compact(...)`.

- [ ] **Step 2: Tambah section UI**

Di `resources/views/admin/dashboard/early-warning.blade.php`, sisipkan tepat sebelum baris `@endsection` yang menutup `@section('content')` (baris ~1160):

```blade
{{-- ═══ Rekap Sasaran Imunisasi (hari ini / besok) ═══ --}}
<section class="vaccine-card" style="margin-top:24px;" aria-label="Rekap Sasaran Imunisasi">
    <div style="display:flex;align-items:center;gap:10px;padding:14px 20px;background:var(--primary);color:#fff;border-radius:10px 10px 0 0;">
        <i class="fa fa-syringe"></i><strong>Rekap Sasaran Imunisasi</strong>
    </div>
    <div style="padding:16px 20px;background:#fff;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 10px 10px;">

        {{-- Filter wilayah --}}
        <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
            <select id="sasaranKec" style="padding:.45rem .6rem;border:1px solid #cbd5e1;border-radius:8px;min-width:170px;">
                <option value="">Semua Kecamatan</option>
                @foreach($kecamatanList as $kec)<option value="{{ $kec->id }}">{{ $kec->name }}</option>@endforeach
            </select>
            <select id="sasaranKel" style="padding:.45rem .6rem;border:1px solid #cbd5e1;border-radius:8px;min-width:170px;">
                <option value="">Semua Kelurahan</option>
                @foreach($kelurahanList as $kel)<option value="{{ $kel->id }}" data-kec="{{ $kel->id_kec ?? '' }}">{{ $kel->name }}</option>@endforeach
            </select>
            <select id="sasaranPos" style="padding:.45rem .6rem;border:1px solid #cbd5e1;border-radius:8px;min-width:170px;">
                <option value="">Semua Posyandu</option>
                @foreach($posyanduList as $pos)<option value="{{ $pos->id }}" data-kel="{{ $pos->id_kel ?? '' }}">{{ $pos->name }}</option>@endforeach
            </select>
        </div>

        {{-- Tab hari ini / besok --}}
        <div class="tab-container" role="tablist" style="margin-bottom:12px;">
            <button type="button" class="tab-btn active" id="sasaranTabToday" onclick="showSasaranTab('today', this)">Hari Ini</button>
            <button type="button" class="tab-btn" id="sasaranTabTomorrow" onclick="showSasaranTab('tomorrow', this)">Besok</button>
            <a id="sasaranExport" href="#" class="btn btn-sm btn-light" style="margin-left:auto;"><i class="fa fa-file-excel"></i> Export Excel</a>
        </div>

        <div id="sasaranTableWrap" style="overflow-x:auto;">
            <table class="table table-sm" style="width:100%;">
                <thead><tr><th>Nama</th><th>Posyandu</th><th>Antigen</th><th>Status</th></tr></thead>
                <tbody id="sasaranBody"><tr><td colspan="4">Memuat…</td></tr></tbody>
            </table>
        </div>
    </div>
</section>
```

- [ ] **Step 3: Tambah JS**

Di `@section('scripts')` (setelah `@parent`), tambahkan:

```javascript
(function () {
    var current = 'today';
    // Basis URL detail anak dari named route (hindari hardcode prefix /admin).
    var SHOW_ANAK_BASE = '{{ route("admin.showAnak", "__HASHID__") }}';

    function filters() {
        var p = new URLSearchParams();
        p.set('tanggal', current);
        var kec = document.getElementById('sasaranKec').value;
        var kel = document.getElementById('sasaranKel').value;
        var pos = document.getElementById('sasaranPos').value;
        if (kec) p.set('id_kecamatan', kec);
        if (kel) p.set('id_kelurahan', kel);
        if (pos) p.set('id_posyandu', pos);
        return p;
    }

    function statusBadge(s) {
        return s === 'sudah'
            ? '<span class="badge" style="background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;">Sudah</span>'
            : '<span class="badge" style="background:#fffbeb;color:#b45309;border:1px solid #fde68a;">Belum</span>';
    }

    function load() {
        var body = document.getElementById('sasaranBody');
        body.innerHTML = '<tr><td colspan="4">Memuat…</td></tr>';
        var qs = filters().toString();
        document.getElementById('sasaranExport').href = '{{ route("admin.sasaran.export") }}?' + qs;

        fetch('{{ route("admin.sasaran.data") }}?' + qs, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j.data || !j.data.length) {
                    body.innerHTML = '<tr><td colspan="4" style="text-align:center;color:#6b7280;padding:18px;">Tidak ada sasaran pada tanggal & filter ini.</td></tr>';
                    return;
                }
                body.innerHTML = j.data.map(function (row) {
                    var href = SHOW_ANAK_BASE.replace('__HASHID__', row.hashid);
                    return '<tr><td><a href="' + href + '">' + row.nama +
                        '</a><div style="font-size:.72rem;color:#94a3b8;">' + (row.nik || '') + '</div></td>' +
                        '<td>' + row.posyandu + '</td><td>' + row.antigen + '</td><td>' + statusBadge(row.status) + '</td></tr>';
                }).join('');
            })
            .catch(function () {
                body.innerHTML = '<tr><td colspan="4" style="color:#dc2626;">Gagal memuat data.</td></tr>';
            });
    }

    window.showSasaranTab = function (which, btn) {
        current = which;
        document.getElementById('sasaranTabToday').classList.toggle('active', which === 'today');
        document.getElementById('sasaranTabTomorrow').classList.toggle('active', which === 'tomorrow');
        load();
    };

    ['sasaranKec', 'sasaranKel', 'sasaranPos'].forEach(function (id) {
        document.getElementById(id).addEventListener('change', load);
    });

    load(); // muat "Hari Ini" saat halaman siap
})();
```

- [ ] **Step 4: Verifikasi manual di Chrome (WAJIB)**

Buka `/admin/early-warning` (nama route `admin.earlyWarning`). Pastikan:
1. Section "Rekap Sasaran Imunisasi" tampil di bawah, tab **Hari Ini** aktif & tabel termuat (atau pesan kosong yang benar).
2. Klik **Besok** → tabel berganti.
3. Ubah filter Kecamatan/Kelurahan/Posyandu → tabel & tautan Export ikut menyesuaikan.
4. Klik **Export Excel** → file `sasaran-imunisasi-YYYY-MM-DD.xlsx` terunduh dengan filter aktif.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/AdminController.php resources/views/admin/dashboard/early-warning.blade.php
git commit -m "feat(imunisasi): section rekap sasaran (hari ini/besok) di halaman Proyeksi"
```

---

## Verifikasi Akhir

- [ ] Jalankan seluruh suite terkait:

Run: `php artisan test --filter="ImportImunisasiDateFormat|ImunisasiImportWide|DataAnakIndexRenders|SasaranImunisasiService|SasaranImunisasiEndpoint"`
Expected: semua PASS.

- [ ] Verifikasi browser untuk ketiga area (Task 3 Step 8, Task 4 Step 4, Task 8 Step 4) sudah dilakukan.

---

## Catatan Penutup

- **Cakupan spec:** D2 (format tanggal), D1 (fix clipping), C (rekap sasaran) — semua tercakup di Task 1–8.
- **Di luar lingkup:** paket A (pengelompokan sasaran umur & WUS, istilah cakupan→capaian, kolom target antigen) dan B (redesain dashboard dari coretan). Dikerjakan terpisah.
- **Keterbatasan diketahui:** jalur `jadwal` memakai vaksin baris `imunisasi` itu sendiri sebagai antigen sasaran (model tak menyimpan "vaksin berikutnya" secara eksplisit). Sumber utama & paling andal adalah jalur `umur`.
- **Performa:** `getSasaran` tanpa filter memindai seluruh anak × vaksin aktif (murni PHP, 0 query tambahan setelah eager-load). Filter wilayah menjaganya ringan; section di-load AJAX sehingga tak memblokir render halaman Proyeksi.

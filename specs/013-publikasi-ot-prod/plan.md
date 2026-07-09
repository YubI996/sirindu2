# Publikasi Data OT ke Produksi — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Prod berisi hanya peserta Operasi Timbang (±10,35k anak, semua punya hasil ukur): 9.140 dikenali + ±1.227 anak baru ber-NIK dummy, dibangun di DB staging lokal lalu dikirim sebagai satu dump SQL.

**Architecture:** Flag baru `--buat-tak-cocok` pada jalur import OT yang sudah teruji (`import:operasi-timbang`) membuat `Anak` ber-NIK dummy untuk baris TAK_COCOK memakai `NikDummyService` + trait `ResolvesWilayah` yang sudah ada. Dataset final dibangun di DB `sirindu_staging` (copy dev), dipangkas ke peserta OT saja, di-dump, lalu di-restore di prod dengan runbook ber-backup.

**Tech Stack:** Laravel 12, Maatwebsite/Excel, MySQL (Laragon), PHPUnit.

**Spec:** `specs/013-publikasi-ot-prod/design.md`

## Global Constraints

- Default **dry-run**; penulisan DB hanya dengan `--commit` (perilaku existing wajib dipertahankan).
- Tanpa flag `--buat-tak-cocok`, perilaku lama tidak berubah sama sekali.
- Wilayah TIDAK pernah auto-create (trait `ResolvesWilayah`, threshold fuzzy 85).
- NIK dummy: format `NikDummyService` (digit ke-13 = '9'), `findExisting` dulu sebelum `generate` (idempoten).
- Test suite pakai 1 DB test bersama → jalankan `php artisan test` **serial** (tanpa --parallel).
- DB dev lokal TIDAK boleh dimodifikasi — semua penulisan hanya ke `sirindu_staging`.
- Branch kerja: `feat/ot-buat-tak-cocok` dari `main` (working tree utama sedang ada WIP `feat/landing-publik` — gunakan worktree terisolasi via superpowers:using-git-worktrees).

---

### Task 1: `OperasiTimbangImport` — buat anak NIK dummy untuk baris TAK_COCOK

**Files:**
- Modify: `app/Imports/OperasiTimbangImport.php`
- Test: `tests/Feature/OperasiTimbangImportTest.php`

**Interfaces:**
- Consumes: `App\Services\NikDummyService` (`findExisting(string $nama, string $tgl, string $jkChar): ?string`, `generate(?string $kodeWilayah, string $tgl, string $jkChar): string`, `isDummy(string $nik): bool`, const `DEFAULT_KODE_WILAYAH`); trait `App\Traits\ResolvesWilayah` (`initWilayahCache()`, `resolveKecamatan(string): ?int`, `resolveKelurahan(string, ?int): ?int`, `resolveRt(string, ?int): ?int` — mengisi `$this->failures` bila gagal resolve).
- Produces: konstruktor param baru `bool $buatTakCocok = false` (posisi ke-6, setelah `$keputusan`); `getResults()` bertambah kunci `'dibuat'` (int), `'dibuat_list'` (array of `['baris','nama','tgl_lahir','nik']`), `'failures'` (array of string). Task 2 bergantung pada ketiganya.

- [ ] **Step 1: Tulis failing tests**

Tambahkan di akhir `tests/Feature/OperasiTimbangImportTest.php` (sebelum kurung tutup class). Tambahkan juga import di atas file: `use App\Models\Kecamatan;`, `use App\Models\Kelurahan;`, `use App\Services\NikDummyService;`, `use Carbon\Carbon;`.

```php
    // ── Jalur --buat-tak-cocok: baris TAK_COCOK dibuatkan anak NIK dummy ──

    /** Header lengkap dengan kolom wilayah (meniru file e-PPGBM asli). */
    private function headerLengkap(): array
    {
        return [
            'No', 'NIK', 'Nama', 'JK', 'Tgl Lahir', 'Nama Ortu',
            'Kec', 'Desa/Kel', 'RT', 'Alamat',
            'Tanggal Pengukuran', 'Berat', 'Tinggi', 'Cara Ukur', 'LiLA',
            'ZS BB/U', 'ZS TB/U', 'ZS BB/TB', 'Naik Berat Badan',
            'Jml Vit A', 'Kelas Ibu Balita', 'MBG',
        ];
    }

    private function barisLengkap(array $o = []): array
    {
        return array_values(array_merge([
            'no' => '1', 'nik' => '02022**********', 'nama' => 'MUHAMMAD ABIZAR',
            'jk' => 'L', 'tgl_lahir' => '2024-02-02',
            'nama_ortu' => 'BUDI SANTOSO / AGIL ANASTASYA F',
            'kec' => 'BONTANG UTARA', 'kel' => 'LOK TUAN', 'rt' => '12',
            'alamat' => 'JL. RE. MARTADINATA',
            'tgl_ukur' => '2026-06-09', 'berat' => '10.6', 'tinggi' => '81.5',
            'cara_ukur' => 'Terlentang', 'lila' => '14', 'zs_bbu' => '-1.73',
            'zs_tbu' => '-2.95', 'zs_bbtb' => '-0.15', 'naik_bb' => 'N',
            'vit_a' => '-', 'kelas_ibu' => 'Tidak', 'mbg' => 'Tidak',
        ], $o));
    }

    private function importBuat(bool $commit = true): OperasiTimbangImport
    {
        return new OperasiTimbangImport(
            userId: 1, commit: $commit, minNama: 88, sheet: 0,
            keputusan: null, buatTakCocok: true,
        );
    }

    public function test_buat_tak_cocok_membuat_anak_dummy_dengan_measurement(): void
    {
        $kec = Kecamatan::create(['name' => 'BONTANG UTARA']);
        $kel = Kelurahan::create(['id_kecamatan' => $kec->id, 'name' => 'LOK TUAN']);

        $import = $this->importBuat();
        $import->collection(collect([$this->headerLengkap(), $this->barisLengkap()]));

        $anak = Anak::first();
        $this->assertNotNull($anak);
        $this->assertTrue(NikDummyService::isDummy($anak->nik));
        $this->assertEquals('MUHAMMAD ABIZAR', $anak->nama);
        $this->assertEquals(1, (int) $anak->jk);
        $this->assertEquals('2024-02-02', Carbon::parse($anak->tgl_lahir)->format('Y-m-d'));
        $this->assertEquals('BUDI SANTOSO', $anak->nama_ayah);
        $this->assertEquals('AGIL ANASTASYA F', $anak->nama_ibu);
        $this->assertEquals($kec->id, (int) $anak->id_kec);
        $this->assertEquals($kel->id, (int) $anak->id_kel);
        $this->assertEquals(1, DataAnak::where('id_anak', $anak->id)->where('tgl_kunjungan', '2026-06-09')->count());

        $r = $import->getResults();
        $this->assertEquals(1, $r['dibuat']);
        $this->assertCount(0, $r['unmatched']);
        $this->assertEquals($anak->nik, $r['dibuat_list'][0]['nik']);
    }

    public function test_buat_tak_cocok_dry_run_tidak_menulis(): void
    {
        $import = $this->importBuat(commit: false);
        $import->collection(collect([$this->headerLengkap(), $this->barisLengkap()]));

        $this->assertEquals(0, Anak::count());
        $this->assertEquals(0, DataAnak::count());
        $r = $import->getResults();
        $this->assertEquals(1, $r['dibuat']); // dihitung sbg "akan dibuat"
        $this->assertEquals('(dry-run)', $r['dibuat_list'][0]['nik']);
    }

    public function test_buat_tak_cocok_idempoten_run_kedua_tidak_menggandakan(): void
    {
        foreach (range(1, 2) as $_) {
            $import = $this->importBuat();
            $import->collection(collect([$this->headerLengkap(), $this->barisLengkap()]));
        }

        $this->assertEquals(1, Anak::count());
        $this->assertEquals(1, DataAnak::count());
    }

    public function test_buat_tak_cocok_wilayah_tak_dikenal_tetap_dibuat_dengan_id_null(): void
    {
        // Tidak ada master wilayah sama sekali
        $import = $this->importBuat();
        $import->collection(collect([$this->headerLengkap(), $this->barisLengkap()]));

        $anak = Anak::first();
        $this->assertNotNull($anak);
        $this->assertNull($anak->id_kec);
        $this->assertNull($anak->id_kel);
        $r = $import->getResults();
        $this->assertNotEmpty(array_filter($r['failures'], fn ($f) => str_contains($f, 'Kelurahan')));
    }

    public function test_buat_tak_cocok_perempuan_nik_encode_dd_plus_40(): void
    {
        $import = $this->importBuat();
        $import->collection(collect([
            $this->headerLengkap(),
            $this->barisLengkap(['nama' => 'SITI AMINAH', 'jk' => 'P', 'nama_ortu' => 'AHMAD / RINA']),
        ]));

        $anak = Anak::first();
        $this->assertEquals(2, (int) $anak->jk);
        // tgl lahir 2024-02-02, P → DD+40 = 42 → digit 7-8 NIK = '42'
        $this->assertEquals('42', substr($anak->nik, 6, 2));
    }

    public function test_tanpa_flag_buat_tak_cocok_tidak_membuat_anak(): void
    {
        $import = new OperasiTimbangImport(userId: 1, commit: true, minNama: 88);
        $import->collection(collect([$this->headerLengkap(), $this->barisLengkap()]));

        $this->assertEquals(0, Anak::count());
        $this->assertCount(1, $import->getResults()['unmatched']);
    }
```

- [ ] **Step 2: Jalankan tests, pastikan gagal**

Run: `php artisan test --filter OperasiTimbangImportTest`
Expected: 6 test baru FAIL (`Unknown named parameter $buatTakCocok` / undefined key `'dibuat'`), test lama tetap PASS.

- [ ] **Step 3: Implementasi di `OperasiTimbangImport`**

Ubah `app/Imports/OperasiTimbangImport.php`:

(a) Tambah `use` di atas file:

```php
use App\Services\NikDummyService;
use App\Traits\ResolvesWilayah;
```

(b) Tambah trait + properti + param konstruktor:

```php
class OperasiTimbangImport implements ToCollection, WithStartRow, WithChunkReading, WithMultipleSheets
{
    use ResolvesAnakByTwoOfThree, ResolvesWilayah;

    protected OperasiTimbangMatcher $matcher;
    protected NikDummyService $nikService;

    protected int $matched = 0;
    protected int $skipped = 0;
    protected int $resolved = 0;       // baris ambigu diselesaikan via keputusan → ditulis
    protected int $resolvedSkip = 0;   // baris ambigu di-skip via keputusan
    protected int $dibuat = 0;         // baris TAK_COCOK → anak baru NIK dummy (--buat-tak-cocok)
    protected array $dibuatList = [];
    protected array $ambiguous = [];
    protected array $unmatched = [];
    protected array $keputusanError = [];
    protected array $failures = [];    // diisi ResolvesWilayah::flagUnresolvedWilayah

    protected ?array $columnMap = null;
    protected int $headerRowIdx = 0;
    protected int $rowOffset = 0;

    /**
     * @param array<int,string>|null $keputusan Peta baris(rowNum)→keputusan_id ('skip' atau id anak)
     *                                           untuk menyelesaikan baris ambigu secara manual.
     * @param bool $buatTakCocok Baris TAK_COCOK dibuatkan Anak baru ber-NIK dummy + measurement
     *                           (untuk publikasi hasil OT; lihat specs/013-publikasi-ot-prod).
     */
    public function __construct(
        protected int $userId,
        protected bool $commit = false,
        protected int $minNama = 88,
        protected int|string $sheet = 0,
        protected ?array $keputusan = null,
        protected bool $buatTakCocok = false,
    ) {
        $this->matcher = new OperasiTimbangMatcher($minNama);
        if ($this->buatTakCocok) {
            $this->nikService = new NikDummyService();
            $this->initWilayahCache();
        }
    }
```

(c) Di `collection()`, ganti cabang TAK_COCOK (blok `} else { $this->unmatched[] = $catatan; }` di dalam `if ($res['status'] === 'AMBIGU')`):

```php
                } else {
                    if ($this->buatTakCocok) {
                        $this->buatDanTulis($row, $map, $rowNum, $nama, $tglLahir, $jk, $namaOrtu ? (string) $namaOrtu : null, $tglUkur);
                    } else {
                        $this->unmatched[] = $catatan;
                    }
                }
```

(d) Tambah method baru (letakkan setelah `terapkanKeputusan()`):

```php
    /**
     * Buat Anak baru ber-NIK dummy untuk baris TAK_COCOK, lalu tulis measurement-nya.
     * Dry-run: hanya menghitung, tidak menulis apa pun.
     */
    protected function buatDanTulis($row, array $map, int $rowNum, string $nama, ?string $tglLahir, string $jk, ?string $namaOrtu, string $tglUkur): void
    {
        $this->dibuat++;

        if (!$this->commit) {
            $this->dibuatList[] = ['baris' => $rowNum, 'nama' => $nama, 'tgl_lahir' => $tglLahir, 'nik' => '(dry-run)'];
            return;
        }

        $jkInt  = strtoupper(trim($jk)) === 'L' ? 1 : 2;
        $jkChar = $jkInt === 1 ? 'L' : 'P';
        $tgl    = $tglLahir ?? date('Y-m-d');

        [$namaAyah, $namaIbu] = $this->pecahNamaOrtu($namaOrtu);

        $kecNama = trim((string) ($this->colVal($row, $map, 'kec') ?? ''));
        $kelNama = trim((string) ($this->colVal($row, $map, 'desa/kel') ?? ''));
        $rtNama  = trim((string) ($this->colVal($row, $map, 'rt') ?? ''));

        $idKec = $kecNama !== '' ? $this->resolveKecamatan($kecNama) : null;
        $idKel = $kelNama !== '' ? $this->resolveKelurahan($kelNama, $idKec) : null;
        $idRt  = $rtNama  !== '' ? $this->resolveRt($rtNama, $idKel) : null;

        // findExisting dulu → run ulang tidak menggandakan anak dummy (idempoten).
        $nik = $this->nikService->findExisting($nama, $tgl, $jkChar)
            ?? $this->nikService->generate(NikDummyService::DEFAULT_KODE_WILAYAH, $tgl, $jkChar);

        $anak = Anak::updateOrCreate(['nik' => $nik], [
            'nama'      => $nama,
            'jk'        => $jkInt,
            'tgl_lahir' => $tglLahir,
            'nama_ayah' => $namaAyah,
            'nama_ibu'  => $namaIbu,
            'alamat'    => $this->trimOrNull($this->colVal($row, $map, 'alamat')),
            'id_kec'    => $idKec,
            'id_kel'    => $idKel,
            'id_rt'     => $idRt,
            'no'        => 'OT-' . str_pad((string) $rowNum, 5, '0', STR_PAD_LEFT),
            'status'    => 1,
        ]);

        $this->dibuatList[] = ['baris' => $rowNum, 'nama' => $nama, 'tgl_lahir' => $tglLahir, 'nik' => $anak->nik];
        $this->tulis($anak, $row, $map, $tglUkur);
    }

    /**
     * Pecah "Nama Ortu" e-PPGBM (format "AYAH / IBU") → [ayah, ibu].
     * Satu nama tanpa '/' dianggap ibu (konsisten dgn tie-break matcher yang memakai nama_ibu).
     */
    protected function pecahNamaOrtu(?string $namaOrtu): array
    {
        $v = trim((string) $namaOrtu);
        if ($v === '') {
            return [null, null];
        }
        $parts = array_values(array_filter(array_map('trim', explode('/', $v)), fn ($p) => $p !== ''));
        if (count($parts) >= 2) {
            return [$parts[0], $parts[1]];
        }
        return [null, $parts[0] ?? null];
    }
```

(e) Perluas `getResults()`:

```php
    public function getResults(): array
    {
        return [
            'matched'         => $this->matched,
            'ambiguous'       => $this->ambiguous,
            'unmatched'       => $this->unmatched,
            'skipped'         => $this->skipped,
            'resolved'        => $this->resolved,
            'resolved_skip'   => $this->resolvedSkip,
            'dibuat'          => $this->dibuat,
            'dibuat_list'     => $this->dibuatList,
            'keputusan_error' => $this->keputusanError,
            'failures'        => $this->failures,
        ];
    }
```

Catatan: baris yang `Tanggal Pengukuran` kosong TETAP masuk `unmatched` (tidak dibuatkan anak) — tanpa tanggal ukur tidak ada measurement yang bisa ditulis; sengaja.

- [ ] **Step 4: Jalankan tests, pastikan hijau**

Run: `php artisan test --filter OperasiTimbangImportTest`
Expected: semua PASS (test lama + 6 baru).

- [ ] **Step 5: Commit**

```bash
git add app/Imports/OperasiTimbangImport.php tests/Feature/OperasiTimbangImportTest.php
git commit -m "feat(timbang): baris TAK_COCOK bisa dibuatkan anak NIK dummy (--buat-tak-cocok)"
```

---

### Task 2: Command `import:operasi-timbang` — flag `--buat-tak-cocok`, laporan, CSV audit

**Files:**
- Modify: `app/Console/Commands/ImportOperasiTimbang.php`
- Test: `tests/Feature/ImportOperasiTimbangCommandTest.php`

**Interfaces:**
- Consumes: `OperasiTimbangImport` konstruktor param ke-6 `bool $buatTakCocok`; `getResults()['dibuat']`, `['dibuat_list']`, `['failures']` (dari Task 1).
- Produces: opsi CLI `--buat-tak-cocok`; output berisi `DIBUAT      : n`; file audit `storage/app/timbang/{base}-dibuat.csv` (kolom `baris,nama,tgl_lahir,nik`). Task 3 memakai opsi & output ini.

- [ ] **Step 1: Tulis failing test**

Tambahkan di akhir `tests/Feature/ImportOperasiTimbangCommandTest.php`:

```php
    public function test_buat_tak_cocok_membuat_anak_dummy_dan_ekspor_csv_dibuat(): void
    {
        Storage::fake('local');
        Anak::create(['nik' => '1111111111111111', 'nama' => 'MUHAMMAD ABIZAR', 'jk' => 1, 'tgl_lahir' => '2024-02-02', 'nama_ibu' => 'AGIL ANASTASYA F', 'nama_ayah' => 'X', 'no' => 'R1', 'status' => 1]);

        $this->artisan('import:operasi-timbang', ['file' => $this->buatFile(), '--commit' => true, '--user' => 1, '--buat-tak-cocok' => true])
            ->expectsOutputToContain('DIBUAT')
            ->assertExitCode(0);

        // Baris 'ANAK TIDAK ADA' kini jadi anak baru ber-NIK dummy + measurement
        $baru = Anak::where('nama', 'ANAK TIDAK ADA')->first();
        $this->assertNotNull($baru);
        $this->assertEquals('9', substr($baru->nik, 12, 1));
        $this->assertEquals(1, DataAnak::where('id_anak', $baru->id)->count());

        // CSV audit ditulis, CSV takcocok tidak (tidak ada yang tersisa tak-cocok)
        $files = Storage::disk('local')->allFiles('timbang');
        $this->assertNotEmpty(array_filter($files, fn ($f) => str_contains($f, 'dibuat')));
        $this->assertEmpty(array_filter($files, fn ($f) => str_contains($f, 'takcocok')));
    }
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter ImportOperasiTimbangCommandTest`
Expected: test baru FAIL (`The "--buat-tak-cocok" option does not exist`), test lama PASS.

- [ ] **Step 3: Implementasi di command**

Ubah `app/Console/Commands/ImportOperasiTimbang.php`:

(a) Tambah baris opsi pada `$signature` (setelah baris `--keputusan=`):

```php
        {--buat-tak-cocok : Baris TAK_COCOK dibuatkan anak baru ber-NIK dummy + measurement}';
```

(b) Di `handle()`, baca opsi dan teruskan ke import (ganti baris `new OperasiTimbangImport(...)`):

```php
        $buatTakCocok = (bool) $this->option('buat-tak-cocok');

        $import = new OperasiTimbangImport($userId, $commit, $minNama, $target, $keputusan, $buatTakCocok);
```

(c) Tambah pelaporan setelah blok `RES-SKIP` (setelah `if ($keputusan !== null) {...}`):

```php
        if ($buatTakCocok) {
            $this->info("DIBUAT     : {$r['dibuat']}" . ($commit ? ' (anak baru NIK dummy)' : ' (akan dibuat, NIK dummy)'));
        }
```

(d) Tampilkan peringatan wilayah gagal-resolve, setelah blok `keputusan_error`:

```php
        if (!empty($r['failures'])) {
            $this->newLine();
            $this->warn('⚠ Peringatan wilayah: ' . count($r['failures']));
            foreach (array_slice($r['failures'], 0, 15) as $f) {
                $this->line("    {$f}");
            }
        }
```

(e) Generalisasi `tulisCsv()` agar bisa menulis header berbeda, lalu tulis CSV audit. Ganti method `tulisCsv` dan kedua pemanggilnya:

```php
        $base = pathinfo($file, PATHINFO_FILENAME);
        $this->tulisCsv("timbang/{$base}-ambigu.csv", $r['ambiguous'], ['baris', 'nama', 'tgl_lahir', 'alasan', 'kandidat']);
        $this->tulisCsv("timbang/{$base}-takcocok.csv", $r['unmatched'], ['baris', 'nama', 'tgl_lahir', 'alasan', 'kandidat']);
        $this->tulisCsv("timbang/{$base}-dibuat.csv", $r['dibuat_list'], ['baris', 'nama', 'tgl_lahir', 'nik']);
```

```php
    private function tulisCsv(string $path, array $rows, array $header): void
    {
        if (empty($rows)) return;

        $lines = [implode(',', $header)];
        foreach ($rows as $r) {
            $lines[] = implode(',', array_map(
                fn ($k) => '"' . str_replace('"', '""', (string) ($r[$k] ?? '')) . '"',
                $header
            ));
        }
        Storage::disk('local')->put($path, implode("\n", $lines));
        $this->line("  → tinjau manual: storage/app/{$path} (" . count($rows) . " baris)");
    }
```

- [ ] **Step 4: Jalankan seluruh suite, pastikan hijau**

Run: `php artisan test`
Expected: semua PASS (termasuk 3 test command lama + 1 baru; regresi 234+ test hijau).

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/ImportOperasiTimbang.php tests/Feature/ImportOperasiTimbangCommandTest.php
git commit -m "feat(timbang): opsi --buat-tak-cocok di import:operasi-timbang + CSV audit dibuat"
```

---

### Task 3: Bangun & verifikasi dataset di `sirindu_staging`

**Files:**
- Tidak ada perubahan kode — eksekusi terhadap DB staging. Semua perintah dijalankan dari root repo di branch berisi Task 1–2.

**Interfaces:**
- Consumes: command `import:operasi-timbang` dengan `--commit --keputusan --buat-tak-cocok` (Task 2); file `docs/Modul Import/Data OT2.xlsx`; `storage/app/timbang/Data OT-keputusan.csv`.
- Produces: DB `sirindu_staging` berisi HANYA peserta OT (±10,35k `anak`, tiap anak ≥1 `data_anak`). Task 4 men-dump DB ini.

- [ ] **Step 1: Ketahui nama DB dev**

```powershell
php artisan tinker --execute="echo config('database.connections.mysql.database');"
```
Catat hasilnya — di langkah berikut disebut `<DB_DEV>`.

- [ ] **Step 2: Buat staging = copy penuh DB dev**

```powershell
mysql -uroot -e "DROP DATABASE IF EXISTS sirindu_staging; CREATE DATABASE sirindu_staging CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysqldump -uroot --single-transaction --routines <DB_DEV> | mysql -uroot sirindu_staging
```
Verifikasi: `mysql -uroot sirindu_staging -e "SELECT COUNT(*) FROM anak;"` → harus **14507**.

- [ ] **Step 3: Kosongkan `data_anak` staging** (hasil import lama memakai keputusan versi 1-skip)

```powershell
mysql -uroot sirindu_staging -e "SET FOREIGN_KEY_CHECKS=0; TRUNCATE data_anak; SET FOREIGN_KEY_CHECKS=1; SELECT COUNT(*) AS data_anak FROM data_anak;"
```
Expected: `data_anak` = 0.

- [ ] **Step 4: Dry-run import terhadap staging — verifikasi angka SEBELUM menulis**

Env var OS menang atas `.env` (phpdotenv immutable) — jangan lupa hapus lagi setelah selesai.

```powershell
php artisan config:clear
$env:DB_DATABASE = 'sirindu_staging'
php artisan import:operasi-timbang "docs/Modul Import/Data OT2.xlsx" --keputusan="storage/app/timbang/Data OT-keputusan.csv" --buat-tak-cocok
```
Expected (wajib cocok sebelum lanjut):
- `COCOK      : 9035`
- `RESOLVED   : 105`
- `RES-SKIP   : 0`
- `AMBIGU     : 0`
- `TAK_COCOK  : 0`
- `DIBUAT     : 1227`
- `DILEWATI   : 0`

Bila meleset → STOP, selidiki dulu (bandingkan dengan rekap di spec).

- [ ] **Step 5: Commit import ke staging**

```powershell
php artisan import:operasi-timbang "docs/Modul Import/Data OT2.xlsx" --commit --keputusan="storage/app/timbang/Data OT-keputusan.csv" --buat-tak-cocok
Remove-Item Env:DB_DATABASE
```
Catatan: `DIBUAT` pada run commit bisa < 1227 sedikit bila `findExisting` memetakan beberapa baris ke anak dummy yang sudah ada (dedup, wajar). Simpan CSV audit `storage/app/timbang/Data OT2-dibuat.csv`.

- [ ] **Step 6: Pangkas — hapus anak tanpa measurement**

FK `imunisasi.id_anak` ber-cascade, biarkan FK checks ON supaya ikut bersih:

```powershell
mysql -uroot sirindu_staging -e "DELETE a FROM anak a LEFT JOIN data_anak d ON d.id_anak = a.id WHERE d.id IS NULL;"
```

- [ ] **Step 7: Verifikasi angka akhir staging**

```powershell
mysql -uroot sirindu_staging -e "SELECT (SELECT COUNT(*) FROM anak) AS anak, (SELECT COUNT(*) FROM data_anak) AS data_anak, (SELECT COUNT(DISTINCT id_anak) FROM data_anak) AS anak_terukur, (SELECT COUNT(*) FROM anak WHERE \`no\` LIKE 'OT-%') AS dibuat_ot, (SELECT COUNT(*) FROM anak a WHERE NOT EXISTS (SELECT 1 FROM data_anak d WHERE d.id_anak=a.id)) AS anak_tanpa_ukur;"
```
Expected:
- `anak` ≈ 10.35k dan `anak` = `anak_terukur`
- `anak_tanpa_ukur` = **0**
- `dibuat_ot` ≈ 1227 (boleh kurang sedikit karena dedup findExisting)
- `data_anak` ≥ `anak` (anak bisa punya >1 tanggal ukur)

Sampling manual (bandingkan 2-3 nama dengan file Excel): 1 anak cocok, 1 anak dari keputusan ambigu, 1 anak dummy (`no` LIKE 'OT-%').

- [ ] **Step 8: Catat hasil**

Tulis angka final (anak, data_anak, dibuat_ot) di pesan commit dokumentasi Task 4 — tidak ada commit kode di task ini.

---

### Task 4: Dump, runbook prod, dan eksekusi

**Files:**
- Create: `specs/013-publikasi-ot-prod/runbook-prod.md`

**Interfaces:**
- Consumes: DB `sirindu_staging` terverifikasi (Task 3).
- Produces: file dump `storage/app/timbang/ot_prod.sql` (di luar git) + runbook ter-commit yang dieksekusi user via SSH.

- [ ] **Step 1: Buat dump**

```powershell
mysqldump -uroot --single-transaction --no-tablespaces sirindu_staging anak data_anak > storage/app/timbang/ot_prod.sql
```
Catatan: dump berisi `DROP TABLE` + `CREATE TABLE` + data, dan secara default menonaktifkan FK checks saat restore — jadi di prod tidak perlu TRUNCATE manual; restore = ganti tabel utuh dengan skema dev yang sudah termigrasi penuh.

Verifikasi isi tanpa membuka data: `Select-String -Path storage/app/timbang/ot_prod.sql -Pattern 'CREATE TABLE' | ForEach-Object Line` → harus tepat 2 tabel (`anak`, `data_anak`).

- [ ] **Step 2: Tulis `specs/013-publikasi-ot-prod/runbook-prod.md`**

```markdown
# Runbook Publikasi Data OT ke Produksi

Prasyarat (SEBELUM data): deploy kode terbaru (origin/main sudah di-push berisi
paket operasi-timbang-eksekutif) + `php artisan migrate` sukses di prod.
Semua langkah dijalankan via SSH. STOP di langkah mana pun yang hasilnya tak sesuai.

## 1. Pre-check
```bash
mysql -u<user> -p <db_prod> -e "SELECT
  (SELECT COUNT(*) FROM anak)            AS anak,
  (SELECT COUNT(*) FROM data_anak)       AS data_anak,
  (SELECT COUNT(*) FROM imunisasi)       AS imunisasi,
  (SELECT COUNT(*) FROM intervensi_gizi) AS intervensi;"
```
Expected: anak=9738. **imunisasi & intervensi HARUS 0** — bila tidak, STOP
(tabel anak akan diganti total; record itu akan yatim/terhapus).

## 2. Backup penuh
```bash
mysqldump -u<user> -p --single-transaction <db_prod> > ~/backup_pre_ot_$(date +%Y%m%d_%H%M%S).sql
ls -lh ~/backup_pre_ot_*.sql   # pastikan ukuran wajar (bukan 0 byte)
```
Unduh salinannya ke lokal sebelum lanjut.

## 3. Upload & restore
Upload `ot_prod.sql` (scp/panel) ke server, lalu:
```bash
mysql -u<user> -p <db_prod> < ot_prod.sql
```

## 4. Verifikasi data
```bash
mysql -u<user> -p <db_prod> -e "SELECT
  (SELECT COUNT(*) FROM anak)      AS anak,
  (SELECT COUNT(*) FROM data_anak) AS data_anak,
  (SELECT COUNT(*) FROM anak a WHERE NOT EXISTS
     (SELECT 1 FROM data_anak d WHERE d.id_anak=a.id)) AS anak_tanpa_ukur;"
```
Expected: angka `anak` & `data_anak` = angka staging (lihat Task 3 Step 7);
`anak_tanpa_ukur` = 0.

## 5. Snapshot & cache
```bash
cd <app_dir>
php artisan prioritas:refresh
php artisan cache:clear && php artisan view:clear
```

## 6. Smoke test
- Buka dashboard timbang → total anak terukur = angka `anak` di atas.
- Buka Early Warning → tab P1–P3 terisi.
- Buka 1 anak dummy (cari NIK berakhiran urutan 9xxx) → profil & hasil ukur tampil.

## Rollback
```bash
mysql -u<user> -p <db_prod> < ~/backup_pre_ot_<timestamp>.sql
php artisan prioritas:refresh && php artisan cache:clear
```
```

Isi placeholder `<db_prod>`, `<user>`, `<app_dir>` sesuai server sebelum eksekusi; angka expected di §4 diisi dari hasil nyata Task 3 Step 7.

- [ ] **Step 3: Commit runbook**

```bash
git add specs/013-publikasi-ot-prod/runbook-prod.md
git commit -m "docs(timbang): runbook publikasi data OT ke produksi"
```

- [ ] **Step 4: Serah terima eksekusi prod ke user**

Eksekusi runbook di server dilakukan **oleh user** (aturan kerahasiaan + akses SSH). Dampingi: minta user paste hasil tiap langkah, cocokkan dengan expected, dan berhenti bila ada selisih.

---

## Self-Review

- **Spec coverage:** flag `--buat-tak-cocok` (Task 1–2 ✓), staging build + pangkas + verifikasi (Task 3 ✓), dump + backup + restore + refresh + smoke test (Task 4 ✓), prasyarat deploy kode (runbook prasyarat ✓), dry-run default & idempoten (test ✓), wilayah no-auto-create (pakai trait existing ✓).
- **Deviasi kecil dari spec (disengaja):** `id_rt` ikut di-resolve (satu baris, trait sudah ada, menopang peta per-RT); `id_posyandu`/`id_puskesmas` TIDAK di-resolve (di luar spec, YAGNI).
- **Type consistency:** param ke-6 `buatTakCocok` konsisten di Task 1 (definisi) & Task 2 (pemanggil); kunci results `dibuat`/`dibuat_list`/`failures` konsisten; header CSV `baris,nama,tgl_lahir,nik` cocok dengan bentuk `dibuatList`.

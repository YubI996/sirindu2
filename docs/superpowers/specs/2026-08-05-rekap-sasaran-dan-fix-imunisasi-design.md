# Rekap Sasaran Imunisasi + Perbaikan Import & Tabel Anak

**Tanggal:** 2026-08-05
**Modul:** Imunisasi (Sirindu)
**Lingkup:** Paket **C + D** dari daftar masukan imunisasi. Item **A** (mekanisme
pengelompokan sasaran per umur & WUS) dan **B** (redesain dashboard dari coretan)
dikerjakan terpisah, di luar spec ini.

---

## 1. Tujuan

Tiga perbaikan mandiri pada modul imunisasi:

- **C — Rekap Sasaran Hari Ini & Besok.** Daftar operasional anak yang jadi sasaran
  imunisasi pada hari ini / besok, ditempatkan di halaman **Proyeksi** (Early Warning),
  bisa difilter per wilayah, dan diekspor ke Excel.
- **D1 — Fix dropdown terpotong** di tabel `/data-dasar-anak`.
- **D2 — Selektor format tanggal** saat import data imunisasi (dd/mm/yyyy vs mm/dd/yyyy).

Setiap item berdiri sendiri; tidak ada ketergantungan urutan antar-item.

---

## 2. D2 — Selektor Format Tanggal Import Imunisasi

### Masalah
`ImunisasiImport::parseDate()` memakai `Carbon::parse()` untuk string tanggal. Untuk
tanggal ber-slash yang ambigu (mis. `03/04/2026`) hasilnya tidak dapat diprediksi:
sebagian Puskesmas mengirim `dd/mm/yyyy`, sebagian `mm/dd/yyyy`. Tidak ada cara bagi
petugas untuk menyatakan format yang benar.

### Solusi — selektor eksplisit saat upload
Petugas memilih format tanggal saat meng-upload file imunisasi.

- **Form import imunisasi** (`resources/views/admin/import/index.blade.php`): tambah
  `<select name="date_format">` (label tampil → value yang dikirim):
  - `dd/mm/yyyy` → value **`dmy`** — **default** (norma Indonesia)
  - `mm/dd/yyyy` → value **`mdy`**
  - `Otomatis (deteksi)` → value **`auto`** — perilaku sekarang (`Carbon::parse`),
    untuk kompatibilitas mundur
- **Persistensi:** kolom baru `import_logs.date_format` (nullable string, mis.
  `enum`/`varchar`). Disimpan di log agar **reimport** memakai format yang sama.
- **`ImportCsvController`:**
  - `handleUpload()` menerima & memvalidasi `date_format` (`nullable|in:dmy,mdy,auto`);
    hanya relevan untuk `type === 'imunisasi'`. Simpan ke `ImportLog`.
  - `reimport()` menyalin `date_format` dari log lama ke log baru.
- **`ImportImunisasiJob::handle()`:** baca `$this->importLog->date_format` dan teruskan
  ke konstruktor: `new ImunisasiImport($userId, $dateFormat)`.
- **`ImunisasiImport`:**
  - Konstruktor menerima `?string $dateFormat = null` (default `auto`).
  - `parseDate()`:
    - Angka (serial Excel) → tetap `Date::excelToDateTimeObject()` seperti sekarang
      (format tanggal tidak berlaku untuk sel numerik).
    - String + format eksplisit → `Carbon::createFromFormat('d/m/Y'|'m/d/Y', $value)`
      dengan penanganan gagal (return `null`, jangan lempar). Toleransi pemisah `-`
      juga bila mudah (normalisasi `-` → `/` sebelum parse).
    - String + `auto` → `Carbon::parse()` (perilaku sekarang).

### Catatan uji
- Unit test `ImunisasiImport::parseDate` untuk: `13/04/2026` dgn `dmy` → `2026-04-13`;
  `04/13/2026` dgn `mdy` → `2026-04-13`; serial Excel tetap benar di kedua format;
  string tak valid → `null`.
- Ruang lingkup: hanya import **imunisasi**. Tipe import lain tidak berubah.

---

## 3. D1 — Fix Dropdown Terpotong di `/data-dasar-anak`

### Masalah
`admin/anak/index.blade.php`: `#tabel-anak` dibungkus `<div class="table-responsive">`.
Bootstrap 4 memberi `overflow-x:auto`; per spesifikasi CSS, `overflow-y` yang `visible`
ikut dipaksa `auto` bila sumbu lain non-visible. Akibatnya, saat tabel pendek (hasil
pencarian sedikit), kontrol DataTables (menu "Show N entries" / area filter) terpotong
oleh kotak overflow.

### Solusi
Pastikan **hanya elemen tabel** yang punya konteks scroll, bukan seluruh wrapper berisi
kontrol DataTables:

- Aktifkan `scrollX: true` pada inisialisasi DataTable `#tabel-anak` (scroll horizontal
  ditangani DataTables pada body tabel).
- Lepas pembungkus `.table-responsive` di sekitar wrapper DataTables **atau** override
  `overflow: visible` untuk `.dataTables_wrapper` di halaman ini, sehingga menu length &
  search tidak lagi terpotong.
- Tidak mengubah kolom, sumber data, atau perilaku server-side yang ada.

### Verifikasi
Perilaku clipping hanya bisa dipastikan di browser. **Wajib** verifikasi manual di Chrome:
buka `/data-dasar-anak`, kurangi hasil via search hingga tabel pendek, buka menu
"Show N entries" — pastikan tidak terpotong. Test PHPUnit tidak mengunci perilaku visual
ini; cukup pastikan halaman tetap render tanpa error.

---

## 4. C — Rekap Sasaran Hari Ini & Besok

### 4.1 Definisi "sasaran" (Gabungan)
Seorang anak adalah **sasaran** antigen X pada tanggal **T** bila memenuhi **salah satu**:

1. **Jalur jadwal:** ada record `imunisasi` untuk `(anak, X)` dengan
   `tanggal_selanjutnya = T` **dan** antigen X belum berstatus `sudah`.
2. **Jalur umur:** tanggal ideal antigen dari lahir
   (`tgl_lahir + usia_pemberian_min` hari) jatuh tepat pada **T**, antigen X belum
   diterima, dan masih dalam window kejar (bukan `kadaluarsa` menurut
   `ImunisasiStatusService::getVaccineStatus`).

**Dedup** per `(id_anak, id_jenis_vaksin)`. Bila kedua jalur mengenai pasangan yang sama,
ambil satu baris; label **sumber** "jadwal" diutamakan atas "umur".

**Status** (kolom tabel):
- `sudah` — pasangan `(anak, X)` sudah punya record `status = 'sudah'`.
- `belum` — selain itu.

> Konsekuensi wajar: baris jalur-umur selalu `belum` (kalau sudah, tak jadi sasaran);
> baris jalur-jadwal bisa `sudah` bila anak sudah dilayani pada hari itu. Ini disengaja —
> rekap menampilkan siapa yang dijadwalkan/jatuh tempo dan apakah sudah terlayani.

### 4.2 Layanan — `SasaranImunisasiService`
Unit mandiri, dapat diuji lepas dari controller.

```
getSasaran(Carbon $tanggal, array $filters = []): Collection
```
- `$filters`: `id_kecamatan` / `id_kelurahan` / `id_posyandu` (paling spesifik menang,
  pola sama dgn `ImunisasiStatusService::getIdlCoverage`).
- Mengembalikan koleksi baris:
  `{ anak, posyandu, vaksin (JenisVaksin), status: 'sudah'|'belum', sumber: 'jadwal'|'umur' }`.
- Memakai ulang `ImunisasiStatusService` untuk perhitungan window umur & status kelayakan
  (jangan duplikasi logika umur/kadaluarsa).
- Query anak difilter wilayah dulu agar tidak memindai seluruh populasi tanpa perlu;
  eager-load `imunisasi.jenisVaksin`, `posyandu`, `kel`.

### 4.3 Controller — `SasaranImunisasiController`
Terpisah dari `earlyWarningSystem` (agar halaman Proyeksi yang sudah berat tidak
bertambah beban; rekap di-load AJAX).

- `data(Request)` — param `tanggal` (`today`/`tomorrow` atau `Y-m-d`) + filter wilayah.
  Kembalikan JSON baris untuk tabel. (Validasi `tanggal` dibatasi hari ini / besok.)
- `export(Request)` — filter & tanggal sama, kembalikan Excel via `SasaranImunisasiExport`.
- Middleware/otorisasi mengikuti pola route admin imunisasi yang ada.

### 4.4 Export — `SasaranImunisasiExport`
Maatwebsite export. Kolom: **Nama, NIK, Posyandu, Kelurahan, Antigen, Status, Sumber,
Tanggal.** Nama file mengandung tanggal, mis. `sasaran-imunisasi-2026-08-05.xlsx`.

### 4.5 UI — di halaman Proyeksi (`admin/dashboard/early-warning.blade.php`)
Section baru "Rekap Sasaran Imunisasi":
- **Filter wilayah** Kecamatan → Kelurahan → Posyandu (cascading; ikuti pola dropdown
  wilayah yang sudah dipakai di dashboard imunisasi / halaman lain).
- **Tab Hari Ini / Besok** (pola tab yang sudah ada di halaman ini).
- Tiap tab: tabel di-fetch AJAX dari `admin.sasaran.data` saat load & saat filter berubah.
  Kolom tampil: **Nama** (link ke `admin.showAnak`, NIK kecil di bawah) · **Posyandu** ·
  **Antigen** · **Status** (badge `sudah`/`belum`). Dihias DataTable client biasa
  (bukan serverSide — volume per hari kecil).
- **Tombol Export Excel** per tab → `admin.sasaran.export` dengan `tanggal` + filter aktif.

### 4.6 Route (`routes/web.php`, grup admin)
```
GET admin/sasaran-imunisasi/data     -> admin.sasaran.data
GET admin/sasaran-imunisasi/export   -> admin.sasaran.export
```

### 4.7 Catatan uji
- Unit test `SasaranImunisasiService`:
  - jalur jadwal: anak dgn `tanggal_selanjutnya = T`, status belum → muncul, `sumber=jadwal`.
  - jalur umur: anak yang `usia_pemberian_min` jatuh di T, antigen belum → muncul,
    `sumber=umur`, `status=belum`.
  - dedup: kedua jalur untuk pasangan sama → satu baris, `sumber=jadwal`.
  - kadaluarsa (jalur umur di luar window kejar) → tidak muncul.
  - filter wilayah membatasi hasil dengan benar.
  - status `sudah` untuk record yang sudah diberikan pada T (jalur jadwal).
- Feature test endpoint `data` & `export` (status 200, kolom/format benar, filter jalan).

---

## 5. Berkas yang tersentuh (ringkasan)

| Item | Berkas |
|------|--------|
| D2 | migration baru `import_logs.date_format`; `app/Http/Controllers/ImportCsvController.php`; `resources/views/admin/import/index.blade.php`; `app/Jobs/ImportImunisasiJob.php`; `app/Imports/ImunisasiImport.php` |
| D1 | `resources/views/admin/anak/index.blade.php` |
| C  | `app/Services/SasaranImunisasiService.php` (baru); `app/Http/Controllers/SasaranImunisasiController.php` (baru); `app/Exports/SasaranImunisasiExport.php` (baru); `resources/views/admin/dashboard/early-warning.blade.php`; `routes/web.php` |

---

## 6. Di luar lingkup
- Item **A** (pengelompokan sasaran per umur: Bayi Baru Lahir / Surviving Infant /
  Baduta, dan WUS) & perubahan istilah "cakupan → capaian", kolom target antigen.
- Item **B** (redesain dashboard imunisasi dari coretan).
- Perubahan pada tipe import selain imunisasi.

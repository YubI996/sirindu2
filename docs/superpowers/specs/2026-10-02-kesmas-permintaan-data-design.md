# Tindak Lanjut Permintaan Data Klien — Field Data Anak & Statistik Dasbor Kesmas

Tanggal: 2026-10-02 · Status: disetujui per bagian dalam percakapan 2 Okt 2026, menunggu reviu spec
tertulis · Lingkup: form Data Anak, detail anak, Export Kesmas, dasbor Kesmas. Modul imunisasi,
Operasi Timbang (OT), dan PD3I **tidak** disentuh (§10).

## 1. Latar

Lembar klien "PERMINTAAN DATA" mencatat status tindak lanjut di kolom E ("sudah di tindak lanjuti").
Berkasnya tidak ada di repo; sumber spec ini adalah ringkasan teks yang ditempel pengguna pada
2 Okt 2026: **10 item bernilai FALSE** dan **7 indikator dasbor dengan kolom E kosong**.

Temuan eksplorasi yang membentuk desain:

1. **Ketujuh indikator dasbor sudah dibangun.** Teksnya identik dengan komentar kartu mockup
   `docs/dasbor kesmas/code.html` (Card 1 "Pelayanan Kesehatan Balita (0 - 5 Tahun)", Card 4 "Balita
   Dilayani Tumbuh Kembang (0 - 60 Bulan)", Panel A "Cakupan Balita & Anak Prasekolah Dilayani SDIDTK"),
   dan dasbor Kesmas (`944d468`) mengimplementasikannya: K1 = bayi + anak balita, total SDIDTK =
   penjumlahan empat kelompok. Kolom E kosong kemungkinan karena lembarnya belum diperbarui.
   **Satu penyimpangan nyata:** K4 dibangun 0–72 bln, padahal mockup dan lembar klien menulis 0–60.
2. Spec Data Kesmas (`2026-09-15-data-kesmas-design.md` §2.2 dan §7) sengaja menunda input gram untuk
   BB bayi < 2 bulan. Spec ini melunasinya, terbatas pada form pengukuran.
3. `data_anak.bb` (FLOAT, kg) dibaca z-score, `PrioritasGiziService` (lewat `DataAnakObserver` di setiap
   simpan), `OtGiziService`, seluruh dasbor, dan importer. Kolomnya tetap kg; gram dikonversi
   **sebelum** disimpan.
4. Baris placeholder `ImunisasiImport` menyimpan `bb = tb = lla = lk = 0` (`sumber = 'imunisasi'`) dan
   ikut tampil sebagai form per kunjungan di Edit Anak.
5. `AnakObserver::saved` memicu `PrioritasGiziService::refreshAnak` (OT), dan
   `CapilDedupService::sigiziUntouched()` mengenali anak "belum tersentuh Capil" dari
   `updated_at = created_at`. Penulisan massal ke `anak` lewat Eloquent akan memicu ribuan refresh
   prioritas dan merusak heuristik Capil. Jalur massal **wajib** query builder tanpa `updated_at` — pola
   yang sudah dipakai `VerifikasiRtService::segarkanDenormalisasi()`.
6. `KohortImunisasi` memakai cut-off 1 Apr–31 Mar dan menilai IBL di tahun Baduta (≈ tahun lahir + 2).
   Rumus klien murni kalender dan menaruh IBL di tahun lahir + 3. Keduanya dibiarkan berbeda — modul
   imunisasi tidak disentuh — dan perbedaannya ditulis di halaman.
7. Belum ada dasbor yang mengecualikan anak "Tidak Aktif" (`anak.status = 0`) atau pindah/meninggal
   (`anak.verif_rt_status`).

## 2. Keputusan pemilik produk (jangan ditawar ulang)

1. **HBIG = field tanggal `anak.tgl_hbig`**, bukan jenis vaksin — otomatis tidak ikut IDL, jadwal,
   kejar, funnel, maupun cakupan antigen.
2. **Sasaran Balita Kesmas = opt-in harfiah.** Hanya nilai `1` yang dihitung; NULL (belum ditandai) dan
   `0` (dilepas) tidak. Anak lama (NULL) keluar dari dasbor Kesmas sampai ditandai.
3. Label **"Sasaran Balita Kesmas"**; di Tambah Anak **default tercentang**.
4. Penandaan anak lama lewat **perintah artisan** (dry-run default, log audit, bisa dibatalkan).
5. Tanda membatasi **seluruh dasbor Kesmas** (semua kartu dan registri). Kartu serta chip IDL/IBL tetap
   dari modul imunisasi apa adanya, dengan keterangan.
6. **Rumus tahun sasaran harfiah**: tahun lahir +1 (IDL), +2 (24 bln), +3 (IBL), +4 (48 bln),
   +5 (60 bln), +6 (72 bln) — hanya tampilan, di detail anak dan Export Kesmas.
7. **K4 = 0–59 bulan** ("0–60" dibaca "di bawah 60 bulan" = definisi balita, sama dengan K1).
8. **Aturan gram hanya di form Update Data Pengukuran**: Tambah Data Pengukuran (`data-anak.blade.php`)
   dan form per kunjungan di Edit Anak. Form identitas Tambah/Edit Anak tetap kg; BBL tetap kg.
9. **Pendekatan A**: perluas jalur Kesmas yang ada + `TahunSasaranKesmas`, `SatuanBeratBadan`, perintah
   `kesmas:tandai-sasaran`, dan tabel `sasaran_kesmas_log`.

## 3. Pemetaan item klien

| Item klien | Jenis | Hasil |
|---|---|---|
| Imunisasi HBIG (No. 22), isian tanggal | field | `anak.tgl_hbig` — §5.1, §6 |
| Penandaan Sasaran Balita | field + statistik | `anak.sasaran_balita_kesmas` + populasi dasbor — §5.2, §5.3, §6.3 |
| Rumus IDL / 24 bln / IBL / 48 / 60 / 72 bln | field turunan | `TahunSasaranKesmas` — §5.4, §6.1, §6.2 |
| Aturan satuan BB (No. 1), wajib | aturan input | `SatuanBeratBadan` — §5.5 |
| Pelayanan Kesehatan Balita (0–5 th) | statistik | K1 (sudah) + rincian bayi/anak balita — §6.3 |
| Balita Dilayani Tumbuh Kembang (0–60 bln) | statistik | K4 → 0–59 bln — §6.3 |
| Cakupan SDIDTK 0–72 + kelompok 0–11, 12–23, 24–59, 60–72 | statistik | sudah benar; judul disamakan — §6.3 |

## 4. Model data

Dua migrasi aditif; `down()` menghapus semuanya. Model `Anak` memakai `$guarded = []`, jadi tidak perlu
perubahan fillable. VIEW `alldata`, Export Anak lama, dan seluruh `App\Imports\*` tidak berubah — anak
dari import tetap NULL (tidak terhitung) sampai ditandai.

`database/migrations/2026_10_02_000001_add_hbig_dan_sasaran_kesmas_to_anak_table.php`

| Kolom | Tipe | Catatan |
|---|---|---|
| `tgl_hbig` | `date` nullable | tanpa DEFAULT |
| `sasaran_balita_kesmas` | `boolean` (tinyint 1) nullable | tanpa DEFAULT. NULL = belum pernah ditandai, `0` = dilepas, `1` = sasaran |

`database/migrations/2026_10_02_000002_create_sasaran_kesmas_log_table.php` — tabel `sasaran_kesmas_log`:

| Kolom | Tipe |
|---|---|
| `id` | bigIncrements |
| `id_anak` | unsignedBigInteger, index |
| `nilai_lama`, `nilai_baru` | tinyInteger nullable |
| `sumber` | enum `form_tambah`, `form_edit`, `perintah`, `batal` |
| `batch` | char(36) nullable, index |
| `alasan` | string(255) nullable |
| `id_user` | unsignedBigInteger nullable |
| `created_at` | timestamp, default CURRENT_TIMESTAMP |

Tanpa `updated_at`, tanpa FK — baris audit tetap ada walau anaknya dihapus, sejalan dengan
`epid_renumber_log`. Model `App\Models\SasaranKesmasLog` (`UPDATED_AT = null`, `$guarded = []`).

## 5. Aturan input & penyimpanan

### 5.1 HBIG

- Kontrol `type="date"` `name="tgl_hbig"` di partial `admin/anak/partials/form-riwayat-lahir.blade.php`,
  setelah select Pemeriksaan Hepatitis B. Label "Tanggal pemberian HBIG"; keterangan "Untuk bayi dari
  ibu HBsAg reaktif. Bukan bagian Imunisasi Dasar Lengkap." **Tanpa atribut `required`/`min`/`max`** —
  kartu collapse yang tertutup tak bisa difokus browser (CLAUDE.md, dikunci `FormKesmasBladeTest`).
- Rule di `KesmasRules::anak()`:
  `'tgl_hbig' => 'nullable|date|after_or_equal:tgl_lahir|before_or_equal:today'`. Karena kunci `anak()`
  sekaligus daftar kolom `kolomKesmas()`, field hanya ditulis bila dikirim, dan `''` menjadi NULL.

### 5.2 Sasaran Balita Kesmas

- Checkbox pola hidden+checkbox (`value="0"` lalu `value="1"`) di grid identitas `create.blade.php` dan
  `edit.blade.php`, **di luar** kartu collapse. Label "Sasaran Balita Kesmas"; keterangan "Centang bila
  anak dihitung sebagai sasaran Dasbor Kesmas. Tidak memengaruhi dasbor imunisasi dan operasi timbang."
- Tambah Anak: tercentang default (`old('sasaran_balita_kesmas', '1')`). Edit Anak: tercentang bila
  tersimpan `1`; bila NULL tampil keterangan "Status: belum pernah ditandai (tidak dihitung)".
- Rule terpisah `KesmasRules::sasaran()` = `['sasaran_balita_kesmas' => 'nullable|boolean']`, dipakai
  `storeAnakRequest` dan `AdminController::updateAnak`. Sengaja **tidak** masuk `KesmasRules::anak()`,
  karena penyimpanannya tidak boleh lewat `kolomKesmas()` (lihat tabel di bawah).
- Aturan simpan (helper di `AnakRepository`):

  | Asal | Field tak dikirim | Dikirim `1` | Dikirim `0` |
  |---|---|---|---|
  | `storeAnak` | NULL | 1 | **0** — melepas centang default adalah tindakan sengaja |
  | `updateAnak`, tersimpan NULL | NULL | 1 | **tetap NULL** |
  | `updateAnak`, tersimpan 0/1 | tidak berubah | 1 | 0 |

  Alasan baris kedua: di Edit Anak, NULL dirender tidak tercentang, jadi `0` di situ bukan keputusan
  petugas. Kalau ditulis `0`, membetulkan nama anak lama diam-diam mengubahnya jadi "dilepas", dan
  perintah massal (yang hanya menandai NULL) tidak lagi menjangkaunya — tanpa error apa pun.
- Setiap perubahan nilai (lama ≠ baru) mencatat satu baris `sasaran_kesmas_log`
  (`form_tambah`/`form_edit`, `id_user` = pengguna login), dalam transaksi yang sama dengan simpan anak.

### 5.3 Perintah `kesmas:tandai-sasaran`

`app/Console/Commands/TandaiSasaranKesmas.php`, memakai trait `App\Support\FilterWilayahAnak` (alias
`a`) supaya "wilayah" berarti sama dengan dasbor.

Opsi: `--kecamatan= --kelurahan= --rt= --posyandu= --puskesmas=` (satu ID per opsi; puskesmas →
catchment kelurahan `WilkerPuskesmas`), `--lahir-sejak= --lahir-sampai=` (Y-m-d), `--semua`,
`--termasuk-pindah`, `--termasuk-tidak-aktif`, `--jalankan`, `--alasan=`, `--batalkan=<batch>`.

- Tanpa filter wilayah **dan** tanpa `--semua` → gagal (exit 1). ID yang tidak ada, tanggal yang bukan
  Y-m-d, atau `--jalankan` tanpa `--alasan` → gagal, tidak menulis apa pun.
- Rekap per kelurahan selalu dicetak: total, **akan ditandai**, sudah bertanda, dilepas (`0`),
  pindah/meninggal, Tidak Aktif. Dua kelompok terakhir dilewati kecuali `--termasuk-pindah` /
  `--termasuk-tidak-aktif`; anak yang terkena keduanya dihitung sekali (pindah/meninggal lebih dulu).
  Anak bernilai `0` **selalu** dilewati — tidak ada opsi untuk menimpa centang yang sengaja dilepas.
- **Tanpa `--jalankan` = dry-run**: tidak ada tulisan ke tabel mana pun.
- Dengan `--jalankan --alasan="…"` (≤ 255 karakter): satu UUID batch. Per potongan 500 id, dalam
  transaksi: pilih ulang `whereNull('sasaran_balita_kesmas')->lockForUpdate()` (centang yang baru diubah
  lewat form tidak ditimpa), lalu **`DB::table('anak')->whereIn('id', …)->update(['sasaran_balita_kesmas'
  => 1])` — query builder, tanpa `updated_at`, tanpa event model** (§1 butir 5), lalu satu baris log per
  anak (`perintah`, batch, alasan, `id_user` NULL). Cetak kode batch di akhir.
- `--batalkan=<batch>` (tanpa `--jalankan` = dry-run): kembalikan ke NULL hanya anak yang nilainya masih
  `1` **dan** baris log terakhirnya milik batch itu; sisanya dilaporkan "sudah berubah, dilewati". Ditulis
  dengan query builder yang sama, dicatat sebagai `batal` dengan batch yang sama.
- Idempoten: menjalankan ulang dengan filter yang sama menandai 0 anak.

### 5.4 Tahun sasaran (rumus Kesmas)

`App\Support\TahunSasaranKesmas` — final, murni PHP, tanpa DB, `config()`, atau `now()` (unit test
proyek ini memakai `PHPUnit\Framework\TestCase` polos tanpa boot aplikasi).

```php
public const TAHAP = [
    'idl'    => [1, 'IDL'],
    '24_bln' => [2, 'Sasaran 24 bulan'],
    'ibl'    => [3, 'IBL'],
    '48_bln' => [4, 'Sasaran 48 bulan'],
    '60_bln' => [5, 'Sasaran 60 bulan'],
    '72_bln' => [6, 'Sasaran 72 bulan'],
];
public static function coba(?string $tglLahir): ?self  // null bila kosong, bukan Y-m-d, atau tahun < 1900
public function tahun(string $tahap): int              // tahun lahir + selisih tahap
public function semua(): array                          // kode => ['label' => …, 'tahun' => …], urut TAHAP
```

Tidak disimpan — dihitung dari `tgl_lahir` saat tampil, jadi koreksi tanggal lahir langsung ikut. Kalau
klien kelak mengoreksi posisi IBL, cukup satu baris `TAHAP` yang berubah.

### 5.5 Satuan BB (form pengukuran saja)

`App\Support\SatuanBeratBadan` — final, murni (boleh `CarbonImmutable`, tanpa `now()`/`config()`):

```php
public const RENTANG = ['g' => [300, 8000], 'kg' => [1, 150]];
public static function batasGram(string $tglLahir): string                     // Y-m-d: tgl_lahir + 2 bln tanpa luapan
public static function untuk(string $tglLahir, string $tglKunjungan): string   // kunjungan < batas → 'g', selain itu 'kg'
public static function keKg(float $nilai, string $satuan): float               // 'g' → ÷ 1000
public static function untukTampil(float $kg, string $satuan): int|float       // 'g' → (int) round(kg × 1000)
public static function dalamRentang(float $nilai, string $satuan): bool
public static function sama(float $kgA, float $kgB): bool                       // |a − b| < 0,0005
```

- **Batas** = `addMonthsNoOverflow(2)`: 15 Jan → 15 Mar; 31 Des 2026 → 28 Feb 2027; 31 Des 2027 →
  29 Feb 2028; 29 Feb 2024 → 29 Apr 2024. Kunjungan **tepat** di tanggal batas = kg ("< 2 bulan" = belum
  genap 2 bulan). Setara dengan MySQL `DATE_ADD(tgl_lahir, INTERVAL 2 MONTH)` — dipakai di pengukuran
  prod (§9).
- **Server yang memutuskan satuan** dari `anak.tgl_lahir` + `tgl_kunjungan` yang dikirim; form hanya
  menampilkannya. Rule dan konversinya ada di `App\Http\Requests\Admin\Anak\AturanBeratBadan`
  (`rentang()`, `untukDisimpan()`), dipakai `storeDataAnak` dan `updateDataAnak`.
- Rentang gram (≥ 300) dan kg (≤ 150) **tidak beririsan**, jadi salah satuan pasti ditolak dan tidak
  pernah tersimpan diam-diam. Pesan: "Untuk umur di bawah 2 bulan, berat badan diisi dalam gram
  (300–8.000), mis. 3250." / "Berat badan diisi dalam kg (1–150), mis. 7.5."
- **`storeDataAnak`**: `Anak::findByHashIdOrFail()` dipindah **sebelum** validasi (aturan BB butuh
  `tgl_lahir`; hash salah kini 404, bukan pesan "Gagal"). `bb` tetap `required|numeric`, ditambah cek
  rentang sesuai satuan; lalu `$request->merge(['bb' => keKg(...)])`. Sisa alur (bln, imunisasi,
  transaksi) tidak berubah.
- **`updateDataAnak`**: tambah `tgl_kunjungan` `required|date` dan `bb` `required|numeric` (memenuhi
  "wajib diisi"). Rentang dicek **hanya bila nilainya berubah**, yaitu `!sama(keKg(input), bb_tersimpan)`;
  bila tidak berubah, nilai tersimpan ditulis ulang apa adanya (tanpa pembulatan float). Baris placeholder
  `bb = 0` dan data lama ganjil tetap bisa disimpan; nilai baru yang salah satuan tetap ditolak.
- Kolom `data_anak.bb` tetap kg → z-score, `PrioritasGiziService`, `OtGiziService`, dan semua dasbor
  tidak berubah. Halaman detail, tabel, grafik, Export Kesmas sheet "Per Kunjungan" tetap kg.
- Tampilan form:
  - `data-anak.blade.php`: input BB ber-`data-batas-gram="{{ batasGram }}"`, label satuan dinamis,
    keterangan aturan, dan `<small aria-live="polite">` untuk pemberitahuan konversi.
  - `edit.blade.php`, form per kunjungan: nilai awal `untukTampil(bb, satuan)` (3,25 kg → `3250` bila
    kunjungan < 2 bln), label "(gram)"/"(kg)", `step` sesuai, `id="k{id}_bb"` + `label for` (perbaikan
    terarah hanya untuk kontrol yang disentuh).
  - JS bersama `public/js/satuan-bb.js`: satuan = `tgl_kunjungan < data-batas-gram` (perbandingan string
    ISO — tanpa aritmetika bulan di JS); tanggal kosong → acuan tanggal hari ini. Saat satuan bergeser
    dan kolom berisi nilai: konversi (×1000 / ÷1000), ganti label dan `step`, umumkan lewat `aria-live`.
    Bila JS gagal, server tetap menolak salah satuan.
  - Form identitas Tambah/Edit Anak: hanya label menjadi "Berat Badan Lahir (kg)" — di halaman Edit,
    kunjungan pertama juga tampil di daftar kunjungan (bisa dalam gram), jadi satuannya harus tertulis.
    Perilaku simpannya tidak berubah.

## 6. Tampilan

### 6.1 Detail anak (`show.blade.php`)

- Kartu baru **"Sasaran Kesmas"** (`col-lg-4`, setelah Riwayat Kelahiran, melengkapi barisnya):
  - status "Ya" (`1`) / "Tidak (dilepas)" (`0`) / "Belum ditandai" (NULL), plus "tidak dihitung di
    Dasbor Kesmas" bila bukan `1`;
  - tabel enam tahun sasaran dari `TahunSasaranKesmas::semua()`;
  - catatan "Rumus Kesmas: tahun lahir + n. Dasbor imunisasi memakai kohort 1 April–31 Maret, jadi
    tahunnya bisa berbeda."
  - Selalu tampil (tidak ikut aturan "Belum diisi" kartu lain).
- Kartu Riwayat Kelahiran: baris "HBIG" (`d/m/Y` atau "—") setelah Hepatitis B; `tgl_hbig` masuk daftar
  `$isiLahir`.
- Label lewat `KesmasPresenter` (method baru `sasaran(?int): string`).

### 6.2 Export Kesmas — sheet "Per Anak"

Delapan kolom baru di **ujung kanan** (AG–AN), supaya kolom numerik R–U yang dikunci `bindValue()` tidak
bergeser:

| Kolom | Judul | Isi |
|---|---|---|
| AG | Tgl HBIG | teks `Y-m-d`, kosong bila NULL |
| AH | Sasaran Balita Kesmas | Ya / Tidak / kosong = belum ditandai |
| AI–AN | Thn Sasaran IDL, Thn Sasaran 24 bln, Thn Sasaran IBL, Thn Sasaran 48 bln, Thn Sasaran 60 bln, Thn Sasaran 72 bln | angka — AI–AN ditambahkan ke pengecualian numerik `bindValue()` |

Baris tetap **semua** anak di wilayah terfilter, tanpa memandang tanda — supaya Dinkes bisa melihat siapa
yang belum ditandai sebelum meminta penandaan massal. Sheet "Per Kunjungan" tidak berubah.

### 6.3 Dasbor Kesmas

1. **Populasi.** `sasaranSub()` dan query dasar `registri()` mendapat
   `where('a.sasaran_balita_kesmas', 1)`. Semua agregat (K1–K4, SDIDTK, CKG beserta gigi/rujuk, layanan,
   skrining, sanitasi) ikut otomatis lewat `sasaranSub()`.
2. **Baris penandaan.** Method baru `penandaanSasaran(PeriodeKesmas, array $filters): array{total,
   bertanda, dilepas, belum}` — anak 0–72 bln (umur pada akhir periode) di wilayah terfilter, **tanpa**
   filter tanda, satu query. Ditampilkan di bawah judul: "Sasaran Balita Kesmas: *n* dari *N* anak
   0–72 bln sudah ditandai · *x* belum ditandai · *y* dilepas". Bila *n* = 0: peringatan `role="status"`
   — "Belum ada anak bertanda Sasaran Balita Kesmas di wilayah ini, jadi kartu menampilkan '—'. Centang
   di Edit Anak, atau minta admin menjalankan penandaan massal."
3. **K1.** Subjudul "Usia 0–5 tahun (0–59 bulan): gabungan Pelayanan Kesehatan Bayi + Anak Balita".
   Rincian di bawah bar "*a* bayi (0–11 bln) + *b* anak balita (12–59 bln)" dari `spmKohort()` yang
   sudah ada (`bayi.lengkap`, `anak_balita.lengkap`).
4. **K4.** Kicker "SPM Tumbuh Kembang Balita", judul "Balita Dilayani Tumbuh Kembang", sub "0–59 bulan
   dengan min. T8× timbang + T2× DDTKA dalam periode". `pemantauanTk()`: sasaran, lengkap, dan
   *perhatian* memakai `umur BETWEEN 0 AND 59`. `KesmasDashboardService::USIA` mendapat
   `'balita_0_59' => [0, 59]`; chip "Semua balita (0–59)" setelah "Semua"; tautan "perlu perhatian" di
   `public/js/kesmas-registri.js` memilih `balita_0_59`, bukan `semua`. Invarian (dikunci tes):
   total `registri(…, 'balita_0_59', '', 'perhatian')` = `pemantauanTk()['perhatian']`.
5. **SDIDTK.** Judul "Cakupan Balita & Anak Prasekolah Dilayani SDIDTK", sub "0–72 bulan · penjumlahan
   kelompok 0–11, 12–23, 24–59, 60–72". Perhitungan tidak berubah.
6. **Skrining neonatal.** Baris "HBIG diberikan dalam periode: *n* bayi" + "(bayi dari ibu HBsAg reaktif
   — jumlah, bukan cakupan)". *n* = anak bertanda dengan `tgl_hbig` dalam `awal..akhir`; `tgl_hbig` masuk
   `KOLOM_KESMAS_ANAK`. Tetap tampil walau data skrining kosong.
7. **IDL/IBL.** Kartu "Imunisasi Dasar & Lanjut" dan chip IDL/IBL di K2/K3 diberi keterangan "populasi
   kohort imunisasi; tidak memakai tanda Sasaran Balita Kesmas". Pemanggilan `ImunisasiStatusService`
   tidak berubah.

`KesmasDashboardController::index()` meneruskan `penandaan` ke view. Validasi `usia` sudah membaca
`array_keys(USIA)`, jadi kode baru otomatis diterima. Tidak ada rute baru dan menu tidak berubah.

## 7. Penanganan galat

| Kondisi | Perilaku |
|---|---|
| BB nilai baru salah satuan / di luar rentang | redirect back + pesan satuan dan contoh; input lama dipertahankan |
| BB tidak diubah di form edit (placeholder 0, data lama ganjil) | disimpan apa adanya |
| `tgl_hbig` sebelum tanggal lahir / di masa depan | pesan validasi |
| Hash anak salah di `storeDataAnak` | 404 (sebelumnya pesan "Gagal Menambahkan Data") |
| Perintah: tanpa filter, ID tak ada, tanggal salah, `--jalankan` tanpa `--alasan` | exit 1 + pesan; tidak menulis |
| Perintah: sebagian anak berubah lewat form di tengah proses | dilewati (dipilih ulang `whereNull` + `lockForUpdate` per potongan) |
| Dasbor: anak bertanda 0 di wilayah/periode | peringatan + kartu "—"; tidak ada pembagian nol |
| JS satuan gagal dimuat | server tetap memutuskan satuan dan menolak salah satuan |
| Prod belum `migrate` | prasyarat rilis (§9), tidak ditangani khusus |

## 8. Pengujian

TDD. Payload tes **meniru form asli**: checkbox tak dicentang mengirim `'0'`, select kosong mengirim
`''`, bukan menghilangkan field (CLAUDE.md). Tes yang menyentuh `ImunisasiStatusService` memanggil
`flushCache()` di `setUp()`; tes `--puskesmas` memanggil `WilkerPuskesmas::flushCache()`.

**Unit (tanpa DB)**

| Tes | Yang dikunci |
|---|---|
| `tests/Unit/Support/TahunSasaranKesmasTest` | enam tahap untuk lahir 10 Mei 2025 (2026…2031); lahir 1 Jan dan 31 Des tahun yang sama memberi tahun sama; urutan `semua()`; `coba()` → null untuk NULL, `''`, `0000-00-00`, bukan tanggal |
| `tests/Unit/Support/SatuanBeratBadanTest` | batas untuk 15 Jan, 31 Des (biasa & menjelang kabisat), 29 Feb; sehari sebelum batas = `g`, tepat batas = `kg`; `keKg`; `untukTampil` dengan sisa float (3,2000000477 → 3200); rentang tidak beririsan; `sama()` |

**Fitur (`tests/Feature/Kesmas/`, `RefreshDatabase`, DB `sirindu_testing`)**

| Tes | Yang dikunci |
|---|---|
| `MigrasiSasaranHbigTest` | dua kolom ada, nullable, **tanpa DEFAULT** (dibaca dari `information_schema.COLUMNS`); tabel log dan kolomnya |
| `FormSasaranKesmasTest` | view Tambah Anak: checkbox tercentang + hidden `0`, di luar `.collapse`; seluruh matriks §5.2 termasuk NULL+`'0'` → tetap NULL **tanpa** baris log; perubahan → satu baris log dengan sumber dan user yang benar; field tak dikirim → tidak disentuh; view Edit Anak: NULL → tak tercentang + teks "belum pernah ditandai" |
| `FormHbigTest` | tanggal tersimpan; `''` → NULL; sebelum lahir dan masa depan ditolak; tak ada `required`/`min`/`max` di partial |
| `SatuanBeratBadanFormTest` | `storeDataAnak` < 2 bln: `'3250'` → 3,25 kg, `'3.25'` ditolak dengan pesan gram; ≥ 2 bln: `'5.4'` → 5,4, `'5400'` ditolak; sehari sebelum batas = gram, tepat batas = kg; `updateDataAnak`: placeholder `bb = 0` dikirim ulang → tersimpan, data lama `3250` (kg) dikirim ulang tanpa diubah → tersimpan tanpa error, diubah ke nilai salah satuan → ditolak, diubah ke `'3300'` → 3,3; render: `data-batas-gram`, nilai gram bulat dan label "(gram)" di form per kunjungan, label "(kg)" di form identitas; `storeAnak` tetap menyimpan `'3.2'` sebagai 3,2 walau kunjungan < 2 bln (form identitas tetap kg) |
| `TandaiSasaranKesmasCommandTest` | tanpa filter → exit 1, tak berubah; dry-run → 0 perubahan, 0 log; `--jalankan` tanpa `--alasan` → exit 1; `--kelurahan` → hanya NULL di kelurahan itu jadi 1; `0`, pindah/meninggal, Tidak Aktif dilewati; `--termasuk-*`; `--puskesmas` lewat catchment; rentang lahir; idempoten; satu batch di log; **`anak.updated_at` tidak berubah dan `prioritas_gizi.refreshed_at` tidak berubah** (observer tidak terpicu); `--batalkan` mengembalikan hanya baris yang log terakhirnya batch itu dan melewati anak yang sudah diubah lewat form |
| `KesmasDashboardServiceTest` | fixture helper `anak()` diberi tanda `1` secara eksplisit (factory global tidak diubah); tes baru: anak NULL dan `0` tidak masuk `sasaran()`, `spmKohort()`, `pemantauanTk()`, `sdidtk()`, `ckg()`, `layananLingkungan()`, `registri()`; `penandaanSasaran()`; K4: anak 60 bln dengan syarat lengkap dan anak 65 bln `ntob = 'T'` tidak ikut; HBIG dalam/luar periode |
| `KesmasSwarmAgregatTest` | invarian perhatian K4 = registri memakai `balita_0_59` (diganti, bukan dilonggarkan) |
| `KesmasDashboardControllerTest`, `KesmasDashboardBladeTest` | peringatan saat bertanda 0; baris penandaan; rincian K1; judul K4 dan SDIDTK; chip `balita_0_59` dan `usia=balita_0_59` diterima (nilai asing tetap 422); keterangan IDL/IBL; `label for` = `id`; tidak ada `@section('x')isi@endsection` atau `data-bs-toggle` |
| `KesmasDashboardMemoriTest` | fixture 2.000 anak diberi tanda `1` **dan** diassert jumlah bertandanya 2.000 sebelum mengukur — tanpa itu populasinya kosong dan tes memori/kueri **lolos palsu**; anggaran kueri memasukkan `penandaanSasaran()` |
| `DetailAnakKesmasTest` | kartu Sasaran Kesmas (tiga status, enam tahun, catatan); baris HBIG; assertion dikurung per kartu/baris |
| `ExportKesmasTest`, `KesmasSwarmDataExportTest` | judul AG–AN; AI–AN numerik, AG teks; R–U tetap numerik (regresi); anak tak bertanda tetap terekspor |

**Browser** (Playwright CLI — `npx.cmd playwright test … --project=chromium --no-deps --workers=1`; DB dev
`sirindu` lebih dulu ditandai lewat `kesmas:tandai-sasaran --semua --jalankan --alasan="dev e2e"`, kalau
tidak dasbor kosong): `e2e/kesmas-dashboard.spec.ts` dan `e2e/kesmas-swarm.spec.ts` diperbarui untuk
tautan K4 → chip 0–59 (`kesmas-swarm` berjalan atas data dev nyata); berkas baru
`e2e/satuan-bb.spec.ts` — label gram/kg berganti saat tanggal kunjungan diubah, nilai ikut dikonversi,
form per kunjungan menampilkan gram; lebar 375 px.

## 9. Rilis & rollback

**Sebelum rilis**

1. Pastikan instalasi prod yang benar-benar dipakai petugas (pernah ada dua instalasi aktif); cek
   `git log origin/main..HEAD` di sana sebelum `pull`.
2. Minta kriteria penandaan ke Dinkes (wilayah, rentang lahir, perlakuan pindah/meninggal & Tidak Aktif).
3. Ukur read-only di prod:

   ```sql
   -- calon sasaran per kelurahan
   SELECT id_kel, COUNT(*) total,
          SUM(verif_rt_status IN ('pindah','meninggal')) pindah_meninggal,
          SUM(status = 0) tidak_aktif
   FROM anak GROUP BY id_kel;
   -- kunjungan < 2 bln yang BB-nya ganjil bila dibaca sebagai gram (informasi; hanya ditolak bila diubah)
   SELECT COUNT(*) FROM data_anak d JOIN anak a ON a.id = d.id_anak
   WHERE d.tgl_kunjungan < DATE_ADD(a.tgl_lahir, INTERVAL 2 MONTH) AND d.bb > 0 AND (d.bb < 0.3 OR d.bb > 8);
   -- kunjungan ≥ 2 bln dengan BB di luar 1–150 kg
   SELECT COUNT(*) FROM data_anak d JOIN anak a ON a.id = d.id_anak
   WHERE d.tgl_kunjungan >= DATE_ADD(a.tgl_lahir, INTERVAL 2 MONTH) AND (d.bb > 150 OR (d.bb > 0 AND d.bb < 1));
   ```

4. Jalankan dry-run perintah di salinan DB prod (staging lokal) dengan kriteria Dinkes; cocokkan angkanya.
5. Catat angka kartu dasbor imunisasi (tahun berjalan: BBL/SI/Baduta, IDL, IBL, butuh kejar) dan dasbor
   timbang/OT (KPI stunting, wasting, underweight).

**Rilis — satu sesi**

1. `mysqldump` DB prod.
2. `git pull` → `php artisan migrate --force` (dua migrasi aditif) → `php artisan view:clear`.
3. **Langsung**: `php artisan kesmas:tandai-sasaran <kriteria>` (dry-run) → cocokkan dengan langkah
   persiapan 4 → `--jalankan --alasan="Penandaan awal sesuai kriteria Dinkes <tanggal>"` → catat kode
   batch. Jendela dasbor kosong hanya beberapa menit, dan selama itu peringatannya menjelaskan sebabnya.

**Sesudah rilis**: angka dasbor imunisasi dan OT **identik** dengan catatan langkah persiapan 5; baris
penandaan sesuai hasil dry-run; buka satu form Tambah Data Pengukuran dan satu Edit Anak (tanpa menyimpan)
untuk melihat label satuan.

**Rollback**

- Penandaan: `kesmas:tandai-sasaran --batalkan=<batch> --jalankan --alasan="…"` (`--alasan` wajib
  bersama `--jalankan`; `--batalkan` selalu seluruh batch, tanpa opsi cakupan seperti `--kelurahan`).
- Kode: revert commit → `php artisan migrate:rollback --step=2`. Sebelumnya ekspor
  `SELECT id, tgl_hbig, sasaran_balita_kesmas FROM anak WHERE tgl_hbig IS NOT NULL OR
  sasaran_balita_kesmas IS NOT NULL` dan `sasaran_kesmas_log`, karena kolom dan tabelnya ikut terhapus.
- BB tidak butuh migrasi data: isinya tetap kg sejak awal.

## 10. Pagar "modul lain tidak tersentuh"

Tugas terakhir rencana implementasi membandingkan `git diff --name-only main...HEAD` dengan daftar izin
berikut. Berkas apa pun di luar daftar → berhenti dan tinjau sebelum merge.

**Boleh berubah:**

- `database/migrations/2026_10_02_000001_*`, `2026_10_02_000002_*` (baru)
- `app/Models/SasaranKesmasLog.php`, `app/Support/TahunSasaranKesmas.php`,
  `app/Support/SatuanBeratBadan.php`, `app/Console/Commands/TandaiSasaranKesmas.php` (baru)
- `app/Http/Requests/Admin/Anak/KesmasRules.php`, `app/Http/Requests/Admin/Anak/storeAnakRequest.php`,
  `app/Http/Requests/Admin/Anak/AturanBeratBadan.php` (baru — rule & konversi BB §5.5)
- `app/Http/Controllers/AdminController.php` — hanya `updateAnak`, `storeDataAnak`, `updateDataAnak`
- `app/Repositories/Admin/Anak/AnakRepository.php` — hanya `storeAnak`, `updateAnak`, helper baru
- `app/Services/KesmasDashboardService.php`, `app/Services/KesmasPresenter.php`,
  `app/Http/Controllers/KesmasDashboardController.php`, `app/Exports/KesmasAnakSheet.php`
- `resources/views/admin/anak/{create,edit,data-anak,show}.blade.php`,
  `resources/views/admin/anak/partials/form-riwayat-lahir.blade.php`
- `resources/views/admin/kesmas/dashboard.blade.php`,
  `resources/views/admin/kesmas/partials/{_spm,_sdidtk-ckg-idl,_layanan}.blade.php`
- `public/js/kesmas-registri.js`, `public/js/satuan-bb.js` (baru), `public/css/kesmas-dashboard.css`
- `tests/Unit/Support/*`, `tests/Unit/KesmasPresenterTest.php`, `tests/Feature/Kesmas/*`,
  `e2e/kesmas-dashboard.spec.ts`, `e2e/kesmas-swarm.spec.ts`, `e2e/satuan-bb.spec.ts` (baru)
- `CLAUDE.md`, `docs/**` (`git add -f`)

**Tidak boleh muncul di diff** (contoh yang paling mungkin tersenggol): `ImunisasiStatusService`,
`KohortImunisasi`, `FilterWilayahAnak`, `WilkerPuskesmas`, `app/Imports/**`, `app/Observers/**`,
`PrioritasGiziService`, `OtGiziService`, `CapilDedupService`, `VerifikasiRtService`,
`resources/views/admin/imunisasi/**`, `resources/views/vendor/**` (sidebar), berkas PD3I/Epidemiologi,
`routes/web.php`, `database/factories/**`.

**Regresi** dijalankan dalam **satu proses** (DB `sirindu_testing` dipakai bersama — jangan dua proses
tes serentak): suite imunisasi (`ImunisasiRutinDashboardServiceTest`, `ImunisasiDashboardControllerTest`,
`ImunisasiDashboardMemoriTest`, `KohortImunisasiTest`), OT/prioritas gizi, `ExportAllDataMemoriTest`,
lalu suite penuh (> 10 menit), log ke `storage/logs/`. Ditambah `php -l` berkas yang berubah,
`git diff --check`, `php artisan view:clear`.

## 11. Di luar lingkup

- Importer (Anak, Kohort, OT, OT Final, Capil, Pengukuran) tidak mengisi tanda/HBIG; BB import tetap kg.
- Form identitas Tambah/Edit Anak dan BBL tetap kg (keputusan 8).
- `storeAnak`/`updateAnak` menghitung `bln` kunjungan pertama dari **hari ini**, bukan dari tanggal
  kunjungan — bug lama; nilainya dibaca modul OT, jadi butuh tiket sendiri.
- Validasi "tanggal kunjungan tidak boleh sebelum tanggal lahir" — tidak ditambah.
- Kolom/filter tanda di daftar Data Anak dan halaman tandai massal (UI) — tidak dibuat; bisa menyusul.
- Dasbor imunisasi, OT, PD3I, landing publik, serta kartu/chip IDL/IBL di dasbor Kesmas tidak memakai tanda.
- Persentase cakupan HBIG — status HBsAg ibu tidak tercatat, jadi pembaginya tidak ada.
- Tanda per tahun (sasaran berbeda tiap tahun).
- Temuan audit Kesmas lama (badge teal, tooltip, collapse saat validasi gagal) — pekerjaan terpisah.

## 12. Alternatif yang ditolak

- **HBIG sebagai jenis vaksin** — menyentuh jadwal, kejar, funnel, cakupan antigen, dan berisiko masuk IDL.
- **Konversi gram hanya di JS** — bila JS gagal, gram tersimpan sebagai kg tanpa jejak; z-score dan
  "BB tidak naik" di prioritas gizi rusak diam-diam.
- **Opt-out (NULL = ikut)** — pemilik produk memilih opt-in harfiah.
- **Tanda hanya untuk kelompok 0–59** — total SDIDTK jadi campuran anak bertanda dan semua anak.
- **Edit Anak menulis `0` untuk NULL yang tak dicentang** — anak lama diam-diam kebal terhadap penandaan massal.
- **Penandaan massal lewat Eloquent atau menyentuh `updated_at`** — memicu ribuan refresh prioritas gizi
  (OT) dan merusak heuristik `sigiziUntouched()` (Capil).
- **Halaman tandai massal (UI)** — lingkup dan risiko lebih besar; perintah artisan dulu.
- **Tahun sasaran mengikuti kohort imunisasi (1 Apr–31 Mar)** — menyimpang dari rumus klien, dan tahap
  48/60/72 bln tidak terdefinisi di modul imunisasi.
- **Tahun sasaran disimpan sebagai kolom** — basi bila `tgl_lahir` dikoreksi.
- **Batas satuan dihitung di JS dengan aritmetika bulan** — tidak dijamin sama dengan PHP; diganti tanggal
  batas yang dihitung server.
- **Aturan gram juga di Tambah/Edit Anak dan BBL** — pemilik produk membatasinya ke form pengukuran.
- **K4 tetap 0–72 / 0–60 inklusif** — mockup dan lembar klien menyebut balita; 0–60 inklusif tumpang
  tindih dengan kelompok prasekolah SDIDTK.

## 13. Lampiran — ringkasan untuk klien

Dibuat saat implementasi sebagai `docs/kesmas-pemetaan-permintaan-data.md` (bahasa nonteknis), supaya
klien bisa mengisi kolom E:

| Permintaan | Status | Letak di aplikasi | Catatan |
|---|---|---|---|
| Imunisasi HBIG (tanggal) | Ditindaklanjuti | Edit Anak → kartu "Riwayat Kelahiran & Skrining Neonatal"; detail anak; Export Kesmas; dasbor Kesmas panel Skrining neonatal | Bukan bagian IDL; dasbor menampilkan jumlah, bukan persen |
| Penandaan Sasaran Balita | Ditindaklanjuti | Centang "Sasaran Balita Kesmas" di Tambah/Edit Anak | Hanya anak bercentang yang dihitung di Dasbor Kesmas; anak lama ditandai massal sesuai kriteria Dinkes |
| Rumus IDL, 24 bln, IBL, 48, 60, 72 bln | Ditindaklanjuti | Detail anak (kartu Sasaran Kesmas); Export Kesmas | Tahun lahir +1 … +6. Dasbor imunisasi memakai kohort 1 April–31 Maret dan menilai IBL di tahun Baduta (± tahun lahir + 2) |
| Satuan BB (< 2 bln gram, ≥ 2 bln kg, wajib) | Ditindaklanjuti | Tambah Data Pengukuran; edit per kunjungan | Form identitas Tambah/Edit Anak tetap kg; data disimpan dan ditampilkan dalam kg |
| Pelayanan Kesehatan Balita (0–5 th) | Sudah ada | Dasbor Kesmas, kartu 1 | Gabungan bayi 0–11 bln + anak balita 12–59 bln, kini dengan rinciannya |
| Balita Dilayani Tumbuh Kembang (0–60 bln) | Disesuaikan | Dasbor Kesmas, kartu 4 | Dihitung 0–59 bln (di bawah 60 bulan), min. 8× timbang + 2× DDTKA setahun (prorata per periode) |
| Cakupan SDIDTK 0–72 dan kelompok 0–11, 12–23, 24–59, 60–72 | Sudah ada | Dasbor Kesmas, blok SDIDTK | Total = penjumlahan keempat kelompok |

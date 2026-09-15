# Data Kesehatan Masyarakat (Kesmas) Anak — Desain

Tanggal: 2026-09-15 · Status: disetujui (percakapan 15 Sep 2026) · Spec 1 dari 2 (Spec 2 = Dasbor Kesmas, brainstorming terpisah)

## 1. Ringkasan

Menambah data Kesmas per anak (identitas layanan, lingkungan rumah, riwayat kelahiran & skrining
neonatal) dan layanan Kesmas per kunjungan (KN, MTBM/MTBS, CKG, gigi, rujukan, suplementasi, pola
makan/asuh, intervensi) ke modul Data Anak yang ada, lalu menyediakan **Export Kesmas** (Excel dua
sheet) sebagai bahan dasbor Kesmas yang dirancang terpisah.

Sumber permintaan: DDL dari klien (tabel `pengukuran_anak`, `riwayat_kelahiran_neonatal`, kolom
tambahan `anak`). DDL itu ditulis tanpa melihat skema Sirindu; keputusan pemilik produk:
**adaptasi ke skema yang ada, bukan terapkan persis.** Tidak ada template Excel pendamping.

Keputusan yang mengikat:

1. **Kolom yang sudah ada dipakai ulang, tidak dibuat duplikat.** `bb` (bukan `berat_badan_kg`),
   `mbg` (bukan `is_mbg`), `kelas_ibu_balita` (bukan `is_kelas_ibu`), `imd`, `usia_kehamilan_lahir`,
   `penolong_lahir`, `komplikasi_persalinan`, `bbl`, `pbl`, `lk_lahir`.
2. **Tidak ada tabel `riwayat_kelahiran_neonatal`.** Relasinya 1:1 dan tujuh kolom riwayat lahir
   sudah ada di `anak`; kolom neonatal yang baru ditambahkan ke `anak` juga (Opsi 1, §11).
3. **Tidak ada DEFAULT pada kolom baru; NULL = belum diisi.** DDL memberi default YA/TIDAK/BELUM;
   kalau diterapkan, ±15.000 anak yang sudah ada langsung tercatat "punya air bersih, tidak ada
   perokok, tidak PJB" tanpa pernah ditanya dan dasbor menampilkan cakupan 100% palsu.
4. Konvensi proyek: boolean `tinyint(1)` + `$request->boolean()`; FK `id_anak`; nama kolom
   Indonesia tanpa awalan `is_`; nama tabel tetap `data_anak` (bukan `pengukuran_anak`).
5. Semua field baru **opsional** — form lama dan klien lama yang tidak mengirim field ini tetap
   berjalan tanpa perubahan perilaku.
6. Penempatan UI = **lebur ke form yang ada** (Opsi A, §11), bukan halaman Kesmas terpisah.
7. `no_rm_epus` di DDL dinamai **`no_id_epus`** (label "No. ID ePuskesmas") atas permintaan pemilik produk.

## 2. Model data

Satu migrasi: `database/migrations/2026_09_22_000001_add_kesmas_fields_to_anak_and_data_anak.php`.
`down()` menghapus seluruh kolom yang ditambahkan. Model `Anak` dan `DataAnak` memakai
`$guarded = []`, tidak perlu perubahan fillable. VIEW `alldata` dan seluruh `App\Imports\*` **tidak
disentuh**.

### 2.1 `anak` — 16 kolom baru, semua `nullable()`, tanpa default

| Kelompok | Kolom | Tipe | Asal di DDL |
|---|---|---|---|
| Kesmas | `no_id_epus` | `string(50)` | `no_rm_epus` |
| | `fktp_bpjs` | `string(100)` | sama |
| | `air_bersih` | `tinyint(1)` | `ENUM('YA','TIDAK') DEFAULT 'YA'` |
| | `jamban_sehat` | `tinyint(1)` | `ENUM DEFAULT 'YA'` |
| | `merokok_keluarga` | `tinyint(1)` | `ENUM DEFAULT 'TIDAK'` |
| | `status_tk_paud` | `string(100)` | sama |
| | `penyakit_penyerta` | `string(255)` | sama |
| | `pjb` | `string(100)` | `DEFAULT 'Tidak Ada'` — default dibuang |
| Riwayat lahir | `riwayat_kek_ibu` | `tinyint(1)` | `ENUM DEFAULT 'TIDAK'` |
| | `tempat_bersalin` | `string(150)` | sama |
| | `jenis_persalinan` | `string(50)` | sama |
| | `skrining_shk` | `enum('normal','tidak_normal','belum')` | `status_shk` |
| | `skrining_shak` | `enum('normal','tidak_normal','belum')` | `status_shak` |
| | `skrining_g6pd` | `enum('normal','tidak_normal','belum')` | `status_g6pd` |
| | `pemeriksaan_hepatitis_b` | `enum('reaktif','non_reaktif','belum')` | `pemeriksaan_hepatitis` |
| | `komplikasi_neonatal` | `text` | `pelayanan_komplikasi_neonatal` |

Dipetakan ke kolom yang **sudah ada** (tidak dibuat): `is_imd` → `imd`; `usia_kehamilan_minggu` →
`usia_kehamilan_lahir`; `penolong_persalinan` → `penolong_lahir`.

### 2.2 `data_anak` — 16 kolom baru, semua `nullable()`, tanpa default

| Kolom | Tipe | Asal di DDL |
|---|---|---|
| `tgl_penanda_ckg` | `date` | sama |
| `mtbm` | `tinyint(1)` | `is_mtbm` |
| `mtbs` | `tinyint(1)` | `is_mtbs` |
| `skrining_atresia_bilier` | `tinyint(1)` | sama |
| `kn1` | `tinyint(1)` | `is_kn1` |
| `kn3` | `tinyint(1)` | `is_kn3` |
| `pkat` | `tinyint(1)` | `is_pkat` |
| `oralit_zinc` | `tinyint(1)` | `oralit_zinc_standar` |
| `pemeriksaan_gigi` | `string(150)` | sama — **isi dari select** (§3.3), bukan teks bebas |
| `rujukan` | `string(150)` | sama — **isi dari select** (§3.3) |
| `mt_pangan_lokal` | `string(100)` | sama |
| `catatan_pengukuran` | `text` | sama |
| `pemeriksaan_lainnya` | `text` | `pemeriksaan_kesehatan_lainnya` |
| `pola_makan` | `text` | `pola_makan_anak` |
| `pola_asuh` | `text` | sama |
| `intervensi` | `text` | sama |

Dipetakan ke kolom yang sudah ada: `berat_badan_kg` → `bb` (sudah kg; catatan DDL soal input gram
untuk <2 bulan **tidak diimplementasikan**, input tetap kg); `is_mbg` → `mbg`; `is_kelas_ibu` →
`kelas_ibu_balita`. Kolom lama `rujuk` (tinyint, diisi import OT) dibiarkan apa adanya dan tidak
disinkronkan dengan `rujukan`.

### 2.3 `config/kesmas.php` — satu sumber daftar opsi

Dipakai oleh form (opsi select), tampilan detail (label), export (label), dan nanti dasbor.

```php
return [
    'status_tk_paud'   => ['Tidak ikut', 'PAUD/KB', 'TK A', 'TK B'],
    'jenis_persalinan' => ['Spontan', 'SC', 'Vakum', 'Forsep', 'Lainnya'],
    'penolong_lahir'   => ['Dokter Spesialis', 'Dokter Umum', 'Bidan', 'Lainnya'],
    'skrining'         => ['belum' => 'Belum', 'normal' => 'Normal', 'tidak_normal' => 'Tidak normal'],
    'hepatitis_b'      => ['belum' => 'Belum', 'non_reaktif' => 'Non reaktif', 'reaktif' => 'Reaktif'],
    'pemeriksaan_gigi' => ['Sehat', 'Karies', 'Masalah lain'],
    'rujukan'          => ['Tidak dirujuk', 'Dokter gigi', 'Dokter spesialis anak', 'Rumah sakit', 'Lainnya'],
    // Checkbox layanan per kunjungan (kolom data_anak): label form, singkatan badge
    // di detail anak (§4), dan judul kolom di export (§5). Urutan = urutan tampil.
    'layanan' => [
        'kn1'  => ['label' => 'KN1 — Kunjungan Neonatal 1 (6–48 jam)',        'badge' => 'KN1',  'kolom' => 'KN1'],
        'kn3'  => ['label' => 'KN3 — Kunjungan Neonatal 3 (hari ke-8 s/d 28)', 'badge' => 'KN3',  'kolom' => 'KN3'],
        'mtbm' => ['label' => 'MTBM — Manajemen Terpadu Bayi Muda',           'badge' => 'MTBM', 'kolom' => 'MTBM'],
        'mtbs' => ['label' => 'MTBS — Manajemen Terpadu Balita Sakit',        'badge' => 'MTBS', 'kolom' => 'MTBS'],
        'pkat' => ['label' => 'PKAT — Pelayanan Kesehatan Anak Terpadu (6 bulan)', 'badge' => 'PKAT', 'kolom' => 'PKAT'],
        'skrining_atresia_bilier' => ['label' => 'Skrining atresia bilier (kartu warna tinja)', 'badge' => 'AB', 'kolom' => 'Atresia Bilier'],
        'oralit_zinc'      => ['label' => 'Oralit & zinc sesuai standar',  'badge' => 'O+Z', 'kolom' => 'Oralit+Zinc'],
        'mbg'              => ['label' => 'Makan Bergizi Gratis (MBG)',    'badge' => 'MBG', 'kolom' => 'MBG'],
        'kelas_ibu_balita' => ['label' => 'Ikut Kelas Ibu Balita',         'badge' => 'KIB', 'kolom' => 'Kelas Ibu Balita'],
    ],
    // Field teks per kunjungan yang ditampilkan sebagai keterangan (detail §4).
    'keterangan_kunjungan' => [
        'pemeriksaan_gigi' => 'Gigi', 'rujukan' => 'Rujukan', 'mt_pangan_lokal' => 'MT pangan lokal',
        'catatan_pengukuran' => 'Catatan', 'pemeriksaan_lainnya' => 'Pemeriksaan lain',
        'pola_makan' => 'Pola makan', 'pola_asuh' => 'Pola asuh', 'intervensi' => 'Intervensi',
    ],
];
```

Nilai lama `penolong_lahir` dari import kohort yang tidak ada di daftar tetap ditampilkan sebagai
opsi tambahan (`selected`) di form edit supaya tidak hilang saat disimpan.

## 3. Form & validasi

### 3.1 Partial baru (`resources/views/admin/anak/partials/`)

| Partial | Isi | Di-include oleh |
|---|---|---|
| `form-kesmas.blade.php` | 8 field Kesmas (§2.1 kelompok Kesmas) | `create`, `edit` |
| `form-riwayat-lahir.blade.php` | 7 kolom lama (`bbl`, `pbl`, `lk_lahir`, `usia_kehamilan_lahir`, `penolong_lahir`, `imd`, `komplikasi_persalinan`) + 9 kolom neonatal baru | `create`, `edit` |
| `form-layanan-kesmas.blade.php` | 16 kolom `data_anak` (§2.2) + `mbg` + `kelas_ibu_balita` | `data-anak`, form per-kunjungan di `edit` |

Setiap partial menerima `$model` nullable (`$anak` / `$data`) dan `$prefix` string kosong default —
`edit.blade.php` merender banyak form kunjungan di satu halaman, jadi `id` elemen harus unik
(`id="{{ $prefix }}kn1"`) agar `label for` tetap sah (temuan audit P0 label/for).

Tiap partial dibungkus **kartu Bootstrap 4 collapse, tertutup default** (`data-toggle="collapse"`,
bukan `data-bs-toggle`), judul:
"Data Kesmas & Lingkungan (opsional)", "Riwayat Kelahiran & Skrining Neonatal (opsional)",
"Layanan Kesmas (opsional)". **Tidak boleh ada `required` di dalam kartu** — panel tertutup tidak
bisa difokus browser dan submit mati senyap (lihat CLAUDE.md). Dikunci oleh tes §6.

### 3.2 Kontrol per field

- **Select tiga keadaan** `— / Ya / Tidak` (value `''`/`1`/`0`) untuk boolean per anak:
  `air_bersih`, `jamban_sehat`, `merokok_keluarga`, `riwayat_kek_ibu`, `imd`. `''` → `null`.
  Alasannya: "belum ditanya" harus bisa dibedakan dari "tidak".
- **Checkbox hidden+checkbox** (`value="0"` hidden + `value="1"` checkbox, dibaca
  `$request->boolean()`) untuk layanan per kunjungan: `mtbm`, `mtbs`, `skrining_atresia_bilier`,
  `kn1`, `kn3`, `pkat`, `oralit_zinc`, `mbg`, `kelas_ibu_balita`. Tidak dicentang = layanan tidak
  diberikan pada kunjungan itu, memang `0`.
- **Select dari `config/kesmas.php`**: `status_tk_paud`, `jenis_persalinan`, `penolong_lahir`,
  `skrining_shk/shak/g6pd`, `pemeriksaan_hepatitis_b`, `pemeriksaan_gigi`, `rujukan`. Semua punya
  opsi kosong "— pilih —" → `null`.
- **Teks/angka**: `no_id_epus`, `fktp_bpjs`, `penyakit_penyerta`, `pjb` (placeholder "Tidak Ada"),
  `tempat_bersalin`, `mt_pangan_lokal`, `bbl` (gram), `pbl`/`lk_lahir` (cm), `usia_kehamilan_lahir`
  (minggu), `tgl_penanda_ckg` (`type="date"`); textarea untuk `komplikasi_persalinan`,
  `komplikasi_neonatal`, `catatan_pengukuran`, `pemeriksaan_lainnya`, `pola_makan`, `pola_asuh`,
  `intervensi`.

### 3.3 Validasi

- `storeAnakRequest` & `updateAnakRequest`: tambah rule `nullable` untuk semua field §2.1 +
  7 kolom lama; `in:` mengacu `config('kesmas.*')` (untuk `penolong_lahir` di update: `in:` daftar
  config **plus nilai lama** anak itu); `max:` sesuai panjang kolom; `boolean` untuk tiga keadaan
  (`nullable|boolean` menerima `'0'`/`'1'`, `''` dikonversi ke null lewat `prepareForValidation`);
  `integer|between:20,45` untuk `usia_kehamilan_lahir`; `numeric` untuk `bbl`/`pbl`/`lk_lahir`.
- `AdminController::storeDataAnak` (validate inline, pola sekarang) dan `updateDataAnak`
  (**belum punya validasi** — ditambah validasi minimal yang sama untuk field Kesmas saja, field lama
  tidak diubah agar perilaku lama tetap).

### 3.4 Penyimpanan (`AnakRepository`)

Dua helper privat agar kolom tidak ditulis empat kali:

- `kesmasAnakAttributes(Request $r): array` — 16 kolom `anak` + 7 kolom lama; select `''` → `null`;
  dipanggil dari `storeAnak` dan kedua cabang `updateAnak` (`array_merge` ke array yang ada).
- `layananKesmasAttributes(Request $r): array` — 16 kolom `data_anak`; checkbox lewat
  `$r->boolean()`; dipanggil dari `storeDataAnak` dan `updateDataAnak`. `mbg`/`kelas_ibu_balita`
  masuk helper ini; di `updateAnak` (baris `DataAnak` pertama) keduanya tetap ditulis seperti sekarang.

Aturan **hanya menyentuh field yang dikirim**: helper memakai `$r->has($field)` sebagai penjaga
untuk field Kesmas yang bukan checkbox — jika form/klien tidak mengirim field (mis. form lama),
kolom tidak ikut di-`update` menjadi null. Untuk checkbox, hidden input menjamin field selalu ada
saat form barunya dipakai; form lama tanpa hidden input berarti `has()` false → kolom tak disentuh.
(Ini berbeda dari larangan `has()` untuk *membaca nilai* checkbox di CLAUDE.md — di sini `has()`
dipakai untuk *mendeteksi keberadaan field*, lalu nilainya tetap dibaca lewat `boolean()`.)

## 4. Tampilan detail (`show.blade.php`)

Dua `article.card.info-card` baru di `section#info-section-title`, gaya `<dl class="row">` yang sama:

- **Kesmas & Lingkungan** — No. ID ePus, FKTP BPJS, Air bersih, Jamban sehat, Perokok serumah,
  TK/PAUD, Penyakit penyerta, PJB.
- **Riwayat Kelahiran** — BBL/PBL/LK lahir, Usia kehamilan, Tempat/Jenis/Penolong persalinan, IMD,
  KEK ibu, 4 skrining sebagai badge (hijau `normal`/`non_reaktif`, merah `tidak_normal`/`reaktif`,
  abu `belum`), Komplikasi persalinan, Komplikasi neonatal.

NULL tampil `—`. Bila seluruh field kartu NULL, kartu tetap tampil dengan satu baris
"Belum diisi — lengkapi lewat Edit Anak".

Tabel **Data Berkala Lengkap** mendapat kolom "Layanan Kesmas": badge untuk yang bernilai 1
(`KN1`, `KN3`, `MTBM`, `MTBS`, `PKAT`, `AB` = atresia bilier, `O+Z`, `MBG`, `KIB`) + `CKG dd/mm`
bila `tgl_penanda_ckg` terisi + label `Gigi: …`/`Rujuk: …` bila terisi + ikon catatan (`title` =
isi) bila salah satu textarea terisi. Untuk itu `AdminController::show` menambah kunci
`'layanan' => [...]` pada tiap elemen `$hasilx` — struktur lain `$hasilx` tidak berubah. Lima
kartu "Riwayat Kunjungan" tidak disentuh. Label pendek dan warnanya diambil dari
`config('kesmas.layanan')` agar dasbor nanti memakai singkatan yang sama.

## 5. Export Kesmas

- Rute (di grup `is_admin`, blok "Export Data Routes" `routes/web.php`):
  `GET admin/export-kesmas` → `admin.export.kesmas.index`; `GET admin/export-kesmas/download` →
  `admin.export.kesmas.download`.
- Controller baru `App\Http\Controllers\ExportKesmasController` — konstruktor sama dengan
  `ExportImunisasiController` (`auth` + `abort(403)` untuk `isFaskesSurveilans()`).
- Menu: item "Export Kesmas" di grup **Export Data** sidebar
  (`resources/views/vendor/admin/layouts/partials/leftsidebar.blade.php`), **kedua salinan menu**
  (baris ±71 dan ±174), `active` pada `admin.export.kesmas.*`.
- View `resources/views/admin/export/kesmas.blade.php`: filter Kecamatan → Kelurahan (cascade
  memeriksa `data-kec`, bukan jQuery `:hidden`), Puskesmas, Posyandu, rentang `tgl_kunjungan`
  (`dari`, `sampai`; hanya menyaring sheet Per Kunjungan), tombol Unduh.
- Validasi `download`: semua filter `nullable`; id `exists:`; tanggal `date`, `sampai >= dari`.
- `App\Exports\KesmasExport implements WithMultipleSheets` → dua sheet, masing-masing
  `FromQuery + WithHeadings + WithMapping` (chunked):
  - **Sheet "Per Anak"** (`KesmasAnakSheet`): NIK, Nama, JK, Tgl lahir, Kecamatan, Kelurahan, RT,
    Puskesmas, Posyandu, lalu 8 kolom Kesmas, lalu riwayat lahir (BBL, PBL, LK lahir, Usia kehamilan,
    Tempat bersalin, Jenis persalinan, Penolong, IMD, KEK ibu, SHK, SHAK, G6PD, Hepatitis B,
    Komplikasi persalinan, Komplikasi neonatal). Satu baris per `anak` yang lolos filter wilayah.
  - **Sheet "Per Kunjungan"** (`KesmasKunjunganSheet`): NIK, Nama, Tgl kunjungan, Usia (bln), BB,
    TB, lalu 16 kolom layanan (§2.2) + MBG + Kelas Ibu Balita. Satu baris per `data_anak` milik anak
    yang lolos filter wilayah dan `tgl_kunjungan` dalam rentang (bila diisi).
  - Boolean → `Ya`/`Tidak`/`` (kosong = NULL). Enum → label `config/kesmas.php`. Tanggal `Y-m-d`.
- Nama berkas: `kesmas-{slug kelurahan|semua}-{Ymd}.xlsx`.
- Tidak ada scoping per faskes selain penolakan surveilans — sama dengan Export Anak yang ada
  (petugas memilih wilayah sendiri).

## 6. Pengujian

`tests/Feature/Kesmas/`, `RefreshDatabase`, DB `sirindu_testing`. Payload tes **meniru form
asli** (checkbox tak dicentang kirim `'0'`, select kosong kirim `''`), bukan menghilangkan field.

| Tes | Yang dikunci |
|---|---|
| `MigrasiKesmasTest` | 16 kolom `anak` + 16 `data_anak` ada; semua nullable; **tidak ada DEFAULT** (baca `information_schema.COLUMNS`). |
| `FormAnakKesmasTest` | `storeAnak`/`updateAnak`: select `''` → `null`, `'1'`/`'0'` tersimpan; enum tersimpan; nilai di luar `in:` → 422/redirect error; payload **tanpa** field Kesmas tetap sukses dan tidak menimpa kolom Kesmas yang sudah terisi; nilai `penolong_lahir` lama non-daftar tetap lolos di update. |
| `FormPengukuranKesmasTest` | `storeDataAnak`/`updateDataAnak`: checkbox `'0'` → `0` (bukan hilang), `'1'` → `1`; `mbg`/`kelas_ibu_balita` per kunjungan; select gigi/rujukan; `tgl_penanda_ckg` tanggal. |
| `DetailAnakKesmasTest` | `show`: nilai terisi tampil; NULL → `—`; kartu kosong → "Belum diisi"; badge layanan muncul hanya untuk nilai 1; `CKG dd/mm` muncul bila terisi. Assertion dikurung per `<tr>`/kartu (helper `baris()` seperti `FormulirFp1RendersTest`). |
| `ExportKesmasTest` | Unduhan berisi 2 sheet dengan heading yang ditetapkan; filter kelurahan/posyandu/rentang tanggal menyaring baris; NULL → kosong, 1 → `Ya`; faskes surveilans → 403; pengguna admin → 200; menu tampil di sidebar. |
| `FormKesmasBladeTest` | Partial di-include di `create`, `edit`, `data-anak`; **tidak ada `required`** di dalam ketiga partial; tidak ada `@section('x')isi@endsection` tanpa spasi di view baru; tidak ada `data-bs-toggle`. |

Browser (manual, Chrome, sebelum commit akhir): Tambah Anak — buka/tutup kartu, simpan tanpa
mengisi Kesmas → berhasil; Edit Anak dengan ≥2 kunjungan — kartu per kunjungan independen, `label
for` unik; isi sebagian → tampil di detail; Export → berkas terbuka dengan 2 sheet.

## 7. Di luar lingkup (Spec 2 atau tidak dikerjakan)

- **Dasbor Kesmas** (`docs/dasbor kesmas/`): sub-proyek sendiri. Pertanyaan yang harus dijawab di
  brainstorming Spec 2: definisi "8x timbang"/"2x DDTKA" dalam periode triwulan; struktur `ddtka`
  (masih teks bebas) untuk SDIDTK per domain per usia; apakah field Kesmas yang tidak ada di mockup
  (sanitasi, KN, MTBS, skrining neonatal) perlu seksi sendiri; sumber denominator sasaran.
- Kolom Kesmas di import (`AnakImport`, `KohortImport`, `OtFinalRegistriImport`) — tidak ditambah.
- Kolom Kesmas di Export Anak lama / VIEW `alldata` — tidak ditambah.
- Konversi gram→kg untuk `bb` bayi <2 bulan — tidak dibuat.
- Sinkronisasi `rujuk` (tinyint lama) ↔ `rujukan` (select baru) — tidak dibuat.

## 8. Alternatif yang ditolak

- **Terapkan DDL persis** — duplikat `bb`/`mbg`/`imd` = dua sumber kebenaran; `pengukuran_anak`
  tidak ada; ENUM YA/TIDAK dan `anak_id` menyalahi konvensi. Ditolak pemilik produk.
- **Tabel `riwayat_kelahiran_neonatal` hanya untuk kolom baru** — riwayat lahir terbelah dua tabel.
- **Tabel terpisah + pindahkan 7 kolom lama** — menyentuh tiga importer, VIEW `alldata`, dan migrasi
  data prod; tidak sebanding.
- **Halaman Kesmas terpisah per anak (Opsi B/C)** — pemilik produk memilih lebur ke form yang ada.
- **Export Kesmas ikut Export Anak** — pemilik produk memilih export sendiri (bahan dasbor).
- **DEFAULT sesuai DDL** — memalsukan data 15.000 anak lama (§1 butir 3).

# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Sirindu is a Laravel 12 web application for managing child health data (Sistem Informasi Anak Rindu). It tracks children's growth metrics, immunization records, and calculates Z-score nutritional status indicators based on WHO standards.

## Architecture

### Authentication and Roles
Two user types with middleware protection:
- `super-admin`: User management, routes prefixed with `/super-admin/`
- `admin`: Child data management, routes prefixed with `/admin/`
- `IsAdmin` middleware allows both admin types
- `UserAccess` middleware for role-specific access

### Z-Score Calculation
`app/Helpers/helpers.php` contains the `z_score()` function that calculates:
- IMT/U (BMI for Age)
- BB/U (Weight for Age)
- TB/U (Height for Age)
- BB/TB (Weight for Height)

These are calculated against WHO reference data stored in the `z_score` database table.

### Checkbox Boolean: baca nilainya, bukan keberadaannya

Form di aplikasi ini (mis. `form-section-d`, `form-section-e`) memakai pola hidden+checkbox:

```html
<input type="hidden"   name="gejala_demam" value="0">
<input type="checkbox" name="gejala_demam" value="1">
```

Konsekuensinya field itu **selalu** ada di request. Maka:

- **Pakai `$request->boolean($field)`.** JANGAN `$request->has($field)` / `filled()` / `isset()` — semuanya bernilai true walau checkbox tidak dicentang, sehingga seluruh field tersimpan `1`.
- Berlaku untuk `BOOLEAN_FIELDS` di `SurveillanceRepository` (23 gejala + 8 komplikasi + `riwayat_kontak_kasus`).
- `$request->has()` tetap sah untuk parameter filter/query (pola `has($x) && $x != ''`), bukan untuk checkbox.

Test checkbox **wajib mengirim payload seperti form aslinya** — string `'0'` untuk yang tak dicentang, bukan menghilangkan field-nya. Test yang menghilangkan field hanya memverifikasi skenario yang tak pernah terjadi dan akan lolos walau kodenya salah. Lihat `EpidemiologiControllerTest::test_store_keeps_unchecked_checkboxes_false`.

Latar: bug 2026-03-06 — backend menulis `has()` (benar saat itu, form belum punya hidden input), lalu redesign form 2 menit kemudian menambahkan hidden input dan diam-diam membatalkan asumsinya. Dua perubahan yang masing-masing benar, digabung jadi salah. Ditemukan client 2026-07-21.

### `required` HTML5 + Accordion = Submit Mati Senyap

Form surveilans (`create`/`edit`) adalah accordion single-open (`data-parent`), hanya
section A terbuka default. Panel tertutup ber-`display:none`, dan **browser tidak bisa
mem-fokus kontrol tersembunyi** — bila ada field `required` kosong di panel tertutup,
submit dibatalkan tanpa pesan apa pun. Gejalanya: klik "Simpan", tidak terjadi apa-apa.
Terverifikasi di Chrome: `An invalid form control with name='id_jenis_kasus' is not focusable.`

Penanganannya di `components/form-accordion-validation.blade.php` (di-include kedua form):
`novalidate` + handler submit sendiri yang membuka panel → tunggu `shown.bs.collapse` →
scroll → fokus → `reportValidity()`. **Jangan lepas partial ini** selama masih ada
`required` di panel yang bisa tertutup, dan jangan fokus ke elemen sebelum panelnya terbuka.

Kalau menambah field `required` baru, pastikan ia berada di form yang meng-include partial
tersebut. Test PHPUnit hanya mengunci keberadaan `novalidate` — perilaku fokus/scroll cuma
bisa diuji di browser.

### Blade PDF membaca kolom yang tak ada = kosong senyap

Formulir `*1` (`pdf/formulir-*.blade.php`) mengambil data lewat `$case->nama_kolom`.
Eloquent mengembalikan `null` untuk properti yang bukan kolom — **tanpa error**.
Akibatnya salah ketik nama kolom tidak pernah ketahuan sampai klien mengeluh
isiannya kosong. Kasus nyata (reviu Agustus 2026): `tanggal_penyelidikan`
(kolom aslinya `tanggal_penyidikan`), `nama_wali`/`no_hp_wali`/`alamat_wali`
(aslinya `nama_orang_tua`/`no_hp_orang_tua`/`alamat_lengkap`), `alamat_kerja`
(aslinya `tempat_kerja_sekolah`), `antibiotik` (aslinya `jenis_antibiotik`),
`obat_lain` (aslinya `obat_lainnya`).

Sebelum menambah isian di formulir cetak, **cocokkan namanya dengan kolom nyata**:

```bash
php artisan tinker --execute="print_r(Schema::getColumnListing('surveillance_cases'));"
```

Waspadai juga `{!! $cb(false) !!}` — checkbox yang sengaja dimatikan saat formulir
dibuat, lalu terlupakan meski datanya sudah tersedia. Kuncinya dengan test render
(`tests/Feature/Epidemiologi/Formulir*RendersTest.php`): buat kasus berisi data,
render view, assert nilainya muncul. Gunakan helper `baris()` di
`FormulirFp1RendersTest` agar assertion terkurung pada satu `<tr>` — regex dengan
`.*?` tak berbatas gampang lolos palsu karena mencocoki baris lain.

### `.disease-section` di dalam kartu accordion tidak ikut di-toggle

Kartu accordion per penyakit (`.disease-card`) ditampilkan JS berdasarkan pilihan
`#id_jenis_kasus`. Tetapi blok `.disease-section` / `.disease-field` DI DALAM kartu
(komplikasi & status gizi di D2, pengobatan Difteri dan pemeriksaan AFP di D3)
dulu hanya dirender tampak dari `$case` saat render server. Di halaman **create**
`$case` belum ada → blok itu permanen `display:none`: kartunya terbuka, isinya
tak pernah muncul, dan status gizi, antibiotik, kelumpuhan, serta sanitasi
**mustahil diisi saat kasus baru dibuat**. Ini sebab banyak isian formulir `*1`
tercetak kosong meski kolomnya sudah lama ada.

`toggleDiseaseCards()` di `create.blade.php` dan `edit.blade.php` kini men-toggle
keduanya. Kalau menambah blok khusus penyakit, beri `data-diseases` (dipisah koma)
dan pastikan fungsi itu ikut menanganinya — dikunci oleh
`FormSurveilansFieldBaruTest::test_blok_penyakit_dalam_kartu_ikut_ditoggle_javascript`.

### Menghapus kasus merapatkan nomor EPID kasus lain

Sejak permintaan Dinkes (Agustus 2026), `SurveillanceRepository::deleteCase()`
tidak sekadar menghapus: seluruh kasus dengan **prefix + tahun yang sama** dan
urutan lebih tinggi diturunkan satu (deret 1..10, hapus 007 → 008;009;010 jadi
007;008;009). Logikanya di `EpidCounter::rapatkanSetelahHapus()`.

Yang wajib diingat:

- **Nomor EPID kasus lain berubah tanpa disentuh petugasnya.** Perubahan dicatat
  di tabel `epid_renumber_log` (lama → baru, dipicu oleh nomor apa, oleh siapa) —
  itu satu-satunya cara menjawab "kenapa nomor kasus saya berubah?".
- **`no_registrasi` adalah kunci upsert `HasilLabImport` dan `Pd3iImport`.** Hasil
  lab yang terlanjur dikirim memakai nomor lama akan menempel ke kasus yang kini
  memegang nomor itu — pasien yang salah, tanpa error. Risiko ini disadari dan
  diterima klien; jangan "diperbaiki" diam-diam dengan melewati kasus tertentu.
  Penanganan yang dipilih klien adalah **peringatan, bukan penolakan**: modal
  import hasil lab menampilkan pergeseran terakhir (lama → baru + tanggal) dan
  jumlah perubahan 30 hari terakhir, lalu petugas yang memutuskan. Aplikasi
  sengaja TIDAK menerjemahkan nomor lama lewat `epid_renumber_log` secara
  otomatis — nomor yang sama bisa berarti dua kasus berbeda tergantung file itu
  dibuat sebelum atau sesudah pergeseran, dan menebaknya berarti salah pasien
  secara senyap. Dikunci `PeringatanImportHasilLabTest`.
- **Data lab di database tidak perlu disinkronkan saat nomor bergeser.** Baris
  spesimen menempel ke kasus lewat `id_surveillance_case`, bukan lewat nomor
  EPID, jadi ia sudah ikut induknya. Kalau suatu saat nomor EPID disalin ke
  baris spesimen atau tabel lab, salinan itu wajib ikut diperbarui di
  `rapatkanSetelahHapus()` — dikunci
  `EpidRenumberSetelahHapusTest::test_hasil_lab_ikut_nomor_baru_induknya`.
- Penggeseran diproses **menaik** (008→007 dulu, baru 009→008). Kalau dibalik,
  pasti bentrok karena `no_registrasi` UNIQUE.
- Deret berjalan per prefix per tahun, jadi menghapus Difteri tak menyentuh nomor
  Campak/AFP, dan tahun lain aman. Nomor legacy di luar format resmi (mis. `KTM9`)
  tak pernah disentuh, baik sebagai pemicu maupun sebagai korban geser.
- Seluruhnya dalam satu transaksi. Penghapusan foto dipindah ke SETELAH transaksi
  berhasil — kalau dibuang lebih dulu lalu proses gagal, kasus tetap ada tapi
  fotonya hilang permanen.

Dikunci oleh `tests/Feature/Epidemiologi/EpidRenumberSetelahHapusTest.php`.

### `ImunisasiStatusService` — cache statis bocor antar test PHPUnit

`ImunisasiStatusService` menyimpan `KelompokVaksin`/`JenisVaksin` di properti
`static` (didesain "sekali per request" — aman di produksi karena PHP
membersihkannya tiap akhir request). Di PHPUnit, `RefreshDatabase` membungkus
tiap test dalam transaksi yang di-rollback, TAPI auto-increment MySQL **tidak**
ikut rollback — jadi `KelompokVaksin::where('kode','IDL')->first()->id` beda
nilainya di tiap test method, sementara cache statis dari test method
sebelumnya (dalam proses PHPUnit yang sama) masih menunjuk id lama. Akibatnya
`isIdlLengkap()` diam-diam selalu `false` di test yang berjalan setelah test
pertama yang memicu cache — tanpa error, cuma assert gagal dengan alasan yang
membingungkan.

Panggil `ImunisasiStatusService::flushCache()` di `setUp()` tiap test yang
memakai service ini bersama `RefreshDatabase` (pola yang sama dengan
`WilkerPuskesmas::flushCache()` yang sudah ada). Lihat
`tests/Feature/Imunisasi/ImunisasiRutinDashboardServiceTest.php`.

### Filter cascade Kecamatan→Kelurahan: jangan pakai jQuery `:hidden` pada `<option>`

`<option>` tidak punya box model saat `<select>`-nya tertutup, jadi jQuery
`:hidden`/`:visible` SELALU menganggapnya hidden — tidak peduli `display`
sebenarnya. Kode yang mengecek `$sel.find('option:selected').is(':hidden')`
untuk memutuskan "apakah pilihan lama masih valid setelah difilter" akan
selalu true dan mereset pilihan yang sebenarnya masih benar (kejadian nyata:
buka dashboard imunisasi dengan `?id_kecamatan=1&id_kelurahan=1` di URL,
Kelurahan yang seharusnya ter-pre-select malah balik ke "Semua kelurahan").
Cek validitas dari atribut data (`data-kec`) langsung, bukan dari visibility
jQuery. Lihat `filterKelOptionsByKec()` di
`resources/views/admin/imunisasi/dashboard.blade.php`.

### DB lokal `sirindu` (dev) bisa kosong dari user — cek sebelum asumsi kredensial seed jalan

`php artisan db:seed` penuh (`DatabaseSeeder`) berhenti di tengah kalau salah
satu seeder gagal (mis. `SurveillanceCaseSeeder` pernah gagal karena data dummy
`status_lab` kepanjangan untuk kolom enum) — seeder SETELAHNYA di daftar
(termasuk `RoleUserSeeder`, sumber akun `dinkes@sirindu.go.id`/`Sirindu@2026`)
tidak ikut jalan, walau tidak ada pesan error yang jelas soal itu. Kalau login
dev lokal gagal padahal kredensial di memori/dokumentasi benar, cek dulu
`SELECT COUNT(*) FROM users` sebelum curiga ke hal lain — MySQL & tabel users
di `sirindu` (beda dari `sirindu_testing`) tidak auto-start/ter-seed di mesin
ini.

### `@section('x')isi@endsection` satu baris tanpa spasi — Blade diam-diam TIDAK mem-parse `@endsection`

Blade menolak mengenali directive (`@endsection`, dst.) kalau `@`-nya nempel
langsung ke huruf/angka sebelumnya tanpa spasi/baris baru (`\B` di regex
compiler Blade). Jadi `@section('title')Dashboard Imunisasi@endsection` —
`@section` ke-compile, tapi `@endsection` TIDAK, dan `@endsection` ikut
tercetak sebagai teks literal di halaman. Section jadi tidak pernah ditutup,
sehingga `@yield('title')`/breadcrumb (lihat `partials/breadcrumb.blade.php`)
tampil kosong — tanpa error apa pun, di Blade maupun di browser console. Bug
ini nyata terjadi di `resources/views/admin/imunisasi/dashboard.blade.php`
(disalin dari kode lama yang sudah begini) sampai breadcrumb-nya dilaporkan
kosong oleh user. Cek pola ini di file manapun sebelum menyalahkan hal lain
kalau breadcrumb/title halaman admin kosong:

```
grep -rnE "@section\('[a-z-]+'\)\S.*@endsection" resources/views
```

Perbaikannya sekadar kasih spasi: `@section('title') Dashboard Imunisasi @endsection`
(pola yang sudah dipakai `pd3i-dashboard.blade.php`). Setelah mengubah blade
manapun, `php artisan view:clear` dulu sebelum menyimpulkan perubahan tak
berefek — compiled view lama tetap disajikan sampai di-clear.

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

### Agregat dasbor: JANGAN `Anak::query()->with('imunisasi')->get()` se-kota

Prod ±10 rb anak, `memory_limit` prod 128 MB (bawaan PHP), dev cuma 39 anak
dengan 512 MB — jadi "memuat semua anak lalu hitung di PHP" selalu lolos di
dev dan mati di prod (insiden 16 Sep 2026: dasbor imunisasi `Allowed memory
size of 134217728 bytes exhausted`; 70 kolom + relasi imunisasi ≈ 17–80 KB
per model, dan halaman itu memindai populasi enam kali per request).

Untuk agregat per anak di `ImunisasiStatusService` pakai `eachAnak($query,
$fn, $with)` — `select` kolom seperlunya (`KOLOM_ANAK_AGREGAT`) + `chunkById(500)`,
memori puncak sebatas satu potongan. Kalau logika per anak butuh kolom baru,
tambahkan ke konstanta itu (model yang di-select sebagian mengembalikan `null`
tanpa error untuk kolom yang tak ikut). Dikunci
`ImunisasiDashboardMemoriTest` (2.000 anak, kenaikan memori puncak < 16 MB).
Untuk agregat yang bisa dihitung SQL (COUNT/GROUP BY) atau cukup `DB::table()`
dengan sedikit kolom (stdClass ≈ 0,5 KB/baris), itu lebih baik lagi.

### Tanggal berkas import: `05/02/2020` tak terbaca tanpa tahu urutannya

`Carbon::parse('05/02/2020')` mengembalikan **2 Mei** — gaya AS, tanpa peringatan apa
pun. Berkas Indonesia yang wajar tersimpan sebagai tanggal yang salah dan tak ada
jejaknya. Karena itu kelas Import yang membaca kolom tanggal memakai
`App\Support\TanggalBerkas` lewat trait `App\Traits\MembacaTanggalBerkas`, **bukan**
`Carbon::parse()`.

- Petugas memilih di form (`format_tanggal`: `auto` | `dmy` | `mdy`), tersimpan di
  `import_logs.format_tanggal` — supaya **reimport** membaca berkas yang sama dengan
  cara yang sama, dan riwayat bisa menjawab "dulu dibaca sebagai format apa".
- `auto` = disimpulkan dari isi berkas oleh `kunciFormatTanggal()` di awal
  `collection()`: ketemu `25/02` → hari di depan. Baris disimpan sambil berjalan, jadi
  bukti yang baru muncul di potongan ke-3 tak bisa menarik kembali baris yang sudah
  masuk — **potongan pertama itulah batas buktinya**, dan itu disengaja.
- Berkas yang seluruh tanggalnya masih bisa dua arti **menghentikan import**
  (`FormatTanggalAmbigu`), bukan ditebak. Sel tanggal Excel, serial Excel, `2020-01-15`,
  dan `2020/01/15` tak pernah ambigu → pilihan format tak terpakai di sana.
- Pilihan tegas yang bertabrakan dengan isi (`25/12/2025` dibaca sebagai bulan 25)
  menghasilkan `null` → baris masuk daftar peringatan, **tidak** ditukar diam-diam.
- Nama bulan (`15-Jan-2020`) dan tahun dua digit (`05/02/20`) sengaja tidak didukung;
  dulu lolos lewat `Carbon::parse`, sekarang jadi peringatan baris. Jangan menambahkan
  fallback `Carbon::parse` untuk "menyelamatkan" bentuk lain — itu pintu masuk `'x'`
  (tanda "sudah" dari petugas) terbaca sebagai hari ini dan `'2020'` sebagai serial
  Excel 1905.
- Masih memakai `Carbon::parse`: `CapilImport`, `HasilLabImport`,
  `OperasiTimbangImport`, `OtFinalRegistriImport` (dua terakhir dipanggil dari artisan,
  tanpa form). Kalau menyentuh salah satunya, pindahkan sekalian.

Dikunci `tests/Unit/Support/TanggalBerkasTest.php` dan
`tests/Feature/Imports/FormatTanggalImportTest.php`.

### SPM manual: NULL triwulan, dan prorata yang mengikuti laporan

Modul SPM (`spm_kategori` + `spm_capaian`, spec
`docs/superpowers/specs/2026-09-29-dasbor-spm-design.md`) berisi angka yang **diisi tangan**,
bukan dihitung dari data anak — beda dari kartu SPM K1–K4 di dasbor Kesmas. Kategorinya bebas;
aplikasi tidak tahu maknanya.

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
  `Σ kumulatif ÷ Σ sasaran` tidak bermakna (1.000 orang + 40 posyandu bukan 1.040 apa pun).
- `CapaianSpm` dan `RingkasanSpm` **tidak boleh** memanggil `config()`/`now()` — unit test
  proyek ini memakai `PHPUnit\Framework\TestCase` polos tanpa boot aplikasi. Ambang dari
  `config('spm.ambang')` diteruskan controller lewat trait `App\Traits\MemakaiTahunSpm` dan
  parameter, bukan diambil di dalam value object.
- **Tinggi grafik Chart.js ada di kontainernya, bukan di atribut `height` `<canvas>`.** Dengan
  `maintainAspectRatio:false` atribut itu diabaikan dan grafik memanjang melewati satu layar.
  Pola ini berlaku untuk dasbor mana pun yang memakai opsi tersebut.
- **Yajra DataTables sudah meng-escape kolom non-`rawColumns`.** Menambah `e()` di `editColumn`
  membuat double-escape: nama kategori tercetak `&lt;script&gt;` harfiah di tabel.

### Sasaran Balita Kesmas: opt-in, NULL tetap NULL saat edit, penandaan massal lewat query builder

Dasbor Kesmas (spec `docs/superpowers/specs/2026-10-02-kesmas-permintaan-data-design.md`) hanya menghitung
`anak.sasaran_balita_kesmas = 1`. NULL = belum pernah ditandai, 0 = dilepas; keduanya **tidak** dihitung.

- Form **Edit Anak** merender NULL sebagai checkbox tak tercentang, jadi `'0'` dari anak NULL **bukan
  keputusan** — `AnakRepository::sasaranEdit()` membiarkannya NULL. Kalau ditulis 0, membetulkan nama anak
  lama diam-diam membuatnya "dilepas" dan perintah massal tak lagi menjangkaunya. Tambah Anak (default
  tercentang) menyimpan `'0'` apa adanya.
- **Penandaan massal hanya lewat `php artisan kesmas:tandai-sasaran`** (dry-run bawaan, `--jalankan
  --alasan="…"`, `--batalkan=<batch>`; jejak di `sasaran_kesmas_log`). Perintah itu wajib `DB::table()`
  tanpa `updated_at`: `AnakObserver::saved` memicu refresh prioritas gizi (OT) per anak, dan
  `CapilDedupService::sigiziUntouched()` membaca `updated_at = created_at` sebagai "belum tersentuh Capil".
  Jangan "menyederhanakannya" jadi `Anak::query()->update()` atau loop `save()`.
- Anak dari import (OT, Kohort, Capil) masuk NULL → tidak terhitung sampai ditandai.
- **Fixture tes dasbor Kesmas wajib bertanda 1.** Tanpa itu populasinya kosong dan tes — terutama
  `KesmasDashboardMemoriTest` — lolos palsu (memori kecil karena tak ada yang dihitung).
- Kartu/chip IDL & IBL di dasbor Kesmas tetap dari `ImunisasiStatusService` dan **tidak** memakai tanda ini.
- **Anak yang keluar tidak dihitung** (`App\Support\KeluarWilayah`): `anak.status = 0`, atau verifikasi RT
  pindah/meninggal yang **sudah disetujui** (`verif_rt_reviu = 'disetujui'`; usulan belum menjadi keputusan).
  Tanda sasarannya tidak disentuh, jadi anak terhitung lagi bila statusnya berbalik. Aturan ini satu-satunya
  sumber untuk `sasaranSub()`, `registri()`, banner, dan `kesmas:tandai-sasaran` — jangan menulis ulang
  kondisinya di tempat lain. Wajib `COALESCE`: `NOT (NULL OR …)` bernilai NULL dan membuang anak yang tak
  pernah diverifikasi secara senyap (dikunci `KesmasKeluarWilayahTest`). "Pindah" tak mencatat tujuan, jadi
  anak yang pindah ke RT lain di Bontang baru terhitung lagi setelah RT barunya memverifikasi "berdomisili".
- `IdentitasMergeService` membawa `sasaran_balita_kesmas` dan `tgl_hbig` ke baris yang dipertahankan dengan
  aturan "isi bila kosong" (0 = sengaja dilepas tidak ditimpa), tercatat di `sasaran_kesmas_log` (sumber `gabung`).

### BB < 2 bulan diinput dalam gram — hanya di form pengukuran, yang disimpan tetap kg

`data_anak.bb` selalu kg (dibaca z-score, `PrioritasGiziService`, `OtGiziService`, dasbor, importer). Di
Tambah Data Pengukuran dan form per kunjungan Edit Anak, satuan input = gram bila
`tgl_kunjungan < tgl_lahir + 2 bulan` (`addMonthsNoOverflow` — 31 Des → 28/29 Feb; tepat di batas = kg),
diputuskan **server** (`App\Support\SatuanBeratBadan`, `App\Http\Requests\Admin\Anak\AturanBeratBadan`).
`public/js/satuan-bb.js` hanya membandingkan tanggal dengan `data-batas-gram` dari server — jangan menambah
aritmetika bulan di JS.

- Rentang gram 300–8.000 dan kg 1–150 sengaja **tidak beririsan** supaya salah satuan pasti ditolak.
- Di `updateDataAnak`, rentang hanya dicek bila nilai **berubah** — baris placeholder import imunisasi
  (`bb = 0`) dan data lama ganjil harus tetap bisa disimpan.
- Form identitas Tambah/Edit Anak dan BBL tetap kg (keputusan pemilik produk). Importer tidak disentuh.

### Tahun sasaran Kesmas ≠ kohort imunisasi

`App\Support\TahunSasaranKesmas` = rumus klien: tahun lahir +1 (IDL), +2 (24 bln), +3 (IBL), +4, +5, +6 —
kalender murni, hanya tampilan (detail anak, Export Kesmas). `KohortImunisasi` memakai cut-off
1 Apr–31 Mar dan menilai IBL di tahun Baduta (≈ +2). Perbedaan ini disengaja dan tertulis di halaman;
jangan "menyamakan" salah satunya tanpa keputusan pemilik produk.

### Export Kesmas: ditulis streaming (OpenSpout), JANGAN kembali ke Maatwebsite

Versi pertama memakai `FromQuery` + `ShouldAutoSize`, yang menumpuk seluruh buku di memori
(60,8 MB untuk 800 anak di tes, ±440 MB diekstrapolasi untuk 10 rb). Di prod ekspor seluruh kota mati
dengan "This page isn't working" **tanpa satu baris pun di `laravel.log`**, sementara satu kelurahan
berhasil — jadi di dev tak pernah ketahuan. Kini `KesmasExport` menulis lewat `OpenSpout\Writer\XLSX`
ke berkas sementara; memori puncak ±5 MB berapa pun jumlah anaknya (`ExportKesmasMemoriTest`).

- **Jangan `Row::fromValues()` / FastExcel untuk sel teks.** OpenSpout mengubah setiap string berawalan
  `=` jadi RUMUS (`Cell::fromValue`) — nama/catatan seperti `=HYPERLINK(...)` dijalankan Excel.
  `KesmasExport::sel()` memakai `StringCell` eksplisit; dikunci `ExportKesmasTest::test_teks_tetap_literal…`.
- **Jangan `get()` seluruhnya, dan jangan join + `ORDER BY nama` + offset** untuk sheet Per Kunjungan:
  pengurutan hasil join diulang di tiap halaman (18,8 dtk vs 0,6 dtk untuk 16 rb baris). Anak dibaca
  per halaman, kunjungan satu kelompok kecil anak diambil dengan satu `whereIn`. Satu baris `data_anak`
  ±8 KB (±80 kolom) — makanya kelompok kecil (`POTONGAN`), dan jangan menyaring kolom: kolom yang
  terlupa dari `map()` jadi sel kosong tanpa error.
- `openspout/openspout` hanya dependensi transitif `rap2hpoutre/fast-excel`; bila FastExcel pernah
  dicopot, tambahkan ia ke `composer.json` langsung.

### AnakImport: `sumber = 'import_anak'`, dan jalur NIK tak boleh menimpa dengan NULL

Anak yang **dibuat** `AnakImport` bertanda `anak.sumber = 'import_anak'` (dulu 'manual', sama dengan input tangan —
insiden Okt 2026: 1.490 anak hasil import tak muncul di Export Data Anak dan tak ada cara membedakannya dari
input manual selain awalan nomor `IMP-`). Anak yang sudah ada **tidak** diubah sumbernya.

- Jalur "NIK valid yang sudah ada" dulu `updateOrCreate(['nik'=>…], $data)` penuh: sel kosong menimpa NULL, `no` diganti
  `IMP-YYYYMM-nnnn`, `status` direset 1 — itu sebab ±3.000 anak Operasi Timbang ikut berawalan `IMP-`. Kini memakai
  `perbaruiAnakAda()` (aturan "isi yang diberikan"); `no`/`status` hanya berubah bila berkas **mengisinya**, identitas
  (nama/tgl lahir/jk) boleh dikoreksi karena NIK kunci kuat. Dikunci `ImportAnakNikAdaTest`.
- Migrasi `2026_10_07_000001` menambah nilai enum dan mengisi ulang `manual` + `no LIKE 'IMP-%'` → `import_anak`
  (awalan itu hanya dibuat AnakImport). Pakai `DB::table()` — jangan `Anak::update()` (menyentuh `updated_at` yang
  dibaca `CapilDedupService::sigiziUntouched()`).
- Nomor `IMP-` pada anak berumur lama **tidak** bisa dipulihkan dari DB; nomor registrasi aslinya hanya ada di cadangan.

### Export Data Anak: dua tombol, satu `AnakExport` streaming, dan anak tanpa kunjungan

View `alldata` = `data_anak INNER JOIN anak`, satu baris per KUNJUNGAN. Anak tanpa kunjungan (semua anak hasil
`AnakImport`) tak ada di sana — prod 7 Okt 2026: 11.666 anak, tetapi export cuma 10.176 baris (selisih persis 1.490).

- Halaman export kini **satu tombol "Unduh Excel"** (`formViewExport`): tanggal & wilayah opsional (kosong = semua; tanggal
  boleh diisi salah satu), centang anak tanpa kunjungan **tercentang bawaan**. Tombol hijau "Export Data All" dilebur karena
  ia link `<a href>` yang tak membaca centang/filter. Rute `exportAllExcel` dipertahankan untuk bookmark lama dan memakai
  `AnakExport` tanpa filter + anak tanpa kunjungan — jangan kembalikan ke `DB::table('alldata')` saja.
- `AnakExport` ditulis STREAMING (OpenSpout, pola `KesmasExport`), bukan Maatwebsite `FromQuery` + `ShouldAutoSize`:
  10 rb baris × 32 kolom ≈ 182 MB di PhpSpreadsheet, sehingga di prod (memory_limit 128 MB) tombol kuning mati dengan
  "This page isn't working" tanpa satu baris pun di `laravel.log`. Dikunci `ExportAnakMemoriTest`.
- Anak tanpa kunjungan dibaca sebagai fase kedua (`lazyById` pada `a.id`), bukan `UNION` — tak ada `SHOW COLUMNS` / kolom
  `urut`. Rentang tanggal hanya menyaring kunjungan; filter wilayah berlaku untuk keduanya.
- Kolom angka didaftar di `KOLOM_ANGKA` (huruf kolom Excel); selain itu **teks literal** (No KK/NIK 16 digit, dan nilai
  berawalan `=` tidak jadi rumus). Kolom baru yang harus numerik wajib ditambahkan ke daftar itu.

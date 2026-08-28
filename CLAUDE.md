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
- Penggeseran diproses **menaik** (008→007 dulu, baru 009→008). Kalau dibalik,
  pasti bentrok karena `no_registrasi` UNIQUE.
- Deret berjalan per prefix per tahun, jadi menghapus Difteri tak menyentuh nomor
  Campak/AFP, dan tahun lain aman. Nomor legacy di luar format resmi (mis. `KTM9`)
  tak pernah disentuh, baik sebagai pemicu maupun sebagai korban geser.
- Seluruhnya dalam satu transaksi. Penghapusan foto dipindah ke SETELAH transaksi
  berhasil — kalau dibuang lebih dulu lalu proses gagal, kasus tetap ada tapi
  fotonya hilang permanen.

Dikunci oleh `tests/Feature/Epidemiologi/EpidRenumberSetelahHapusTest.php`.

# Paket A — Dua Alamat Sasaran (KTP & Domisili)

Tanggal: 2026-06-11
Status: Disetujui (siap rencana implementasi)

## Latar Belakang

Masukan client: setiap sasaran punya **dua alamat** — KTP dan Domisili (boleh sama).
**Alamat domisili adalah alamat operasional** yang dipakai algoritma (pengelompokan
RT/Kelurahan/Posyandu di dashboard, peta sebaran, kejar imunisasi).

Konteks lebih luas dari client: tabel `anak` akan menjadi tabel **sasaran**
(ke depan mencakup remaja, ibu hamil, lansia), namun **untuk sekarang cukup balita
seperti kondisi sekarang**. Secara skema, modul imunisasi (`imunisasi.id_anak`) dan
gizi (`data_anak.id_anak`) sudah sama-sama menunjuk ke tabel `anak`, sehingga tidak
ada fragmentasi yang perlu disatukan saat ini.

## Lingkup

Satu-satunya perubahan konkret untuk sekarang: **menambah alamat KTP** di samping
alamat domisili yang sudah ada.

### Di luar lingkup (YAGNI — sesuai "cukup seperti ini")
- Rename tabel `anak` → `sasaran`.
- Tipe sasaran lain (remaja, ibu hamil, lansia).
- Perubahan algoritma operasional (sudah memakai field domisili existing).
- **Import & Export alamat_ktp** — ditunda ke Paket C (pekerjaan export).

## Keputusan Desain

1. **Alamat KTP = satu kolom teks bebas.** Boleh berisi kota/provinsi luar Bontang,
   tidak terstruktur, tidak dipakai algoritma. Mirip cara surveilans menyimpan
   `kab_kota` sebagai teks bebas.
2. **Kolom alamat terstruktur yang ada = DOMISILI.** Field `id_kec`, `id_kel`,
   `id_rt`, `id_posyandu`, `id_puskesmas` plus kolom teks `alamat` (sudah ada,
   selama ini hanya terisi via import) adalah alamat domisili. **Tidak ada migrasi
   arti data**; algoritma yang ada langsung tetap jalan.

## Perubahan

### 1. Skema database
- Migrasi baru: tambah kolom `alamat_ktp` (TEXT, nullable) pada tabel `anak`,
  diletakkan setelah kolom `alamat`.
- Tidak ada perubahan pada kolom terstruktur maupun data existing.

### 2. Form Tambah/Edit Anak (`resources/views/admin/anak/create.blade.php`, `edit.blade.php`)
Alamat dikelompokkan menjadi dua seksi berlabel jelas:
- **"Alamat Domisili (operasional)"** — dropdown cascading Kec/Kel/RT/Puskesmas/Posyandu
  yang sudah ada, **ditambah** field teks `alamat` (detail jalan/RT) yang sebelumnya
  belum ada di form.
- **"Alamat KTP"** — satu `textarea` untuk `alamat_ktp`.
  - Helper text: "Boleh sama dengan domisili".
  - Tombol "Samakan dengan domisili" yang menyalin teks domisili (`alamat`) ke
    `alamat_ktp` secara client-side (JS murni).

### 3. Validasi (`storeAnakRequest`, `updateAnakRequest`)
- `alamat` → `nullable|string`.
- `alamat_ktp` → `nullable|string`.
- Field domisili terstruktur tetap `required` seperti sekarang.

### 4. Controller penyimpanan (AdminController store/update Anak)
- Pastikan `alamat` dan `alamat_ktp` ikut tersimpan (model `Anak` memakai
  `$guarded = []`, jadi cukup memastikan input ada di request dan tidak di-strip).

### 5. Tampilan Detail (`resources/views/admin/anak/show.blade.php`)
Pada card "Informasi Wilayah":
- Beri konteks bahwa blok terstruktur adalah **Domisili**.
- Tambah baris **"Alamat Domisili"** menampilkan teks `alamat` (fallback "-").
- Tambah baris **"Alamat KTP"** menampilkan `alamat_ktp` (fallback "-").

## Edge Cases
- `alamat_ktp` kosong → tampilkan "-".
- `alamat` (domisili teks) kosong → tampilkan "-".
- Tombol "Samakan dengan domisili" hanya menyalin field teks; tidak menyentuh
  dropdown terstruktur.

## Kriteria Sukses
- Bisa menambah & mengedit anak dengan alamat KTP terpisah dari domisili.
- Alamat domisili & KTP tampil di halaman detail.
- Tidak ada perubahan perilaku pada dashboard/peta/kejar-imunisasi (regresi nol).

# Paket C — Perbaikan Dashboard PD3I

Tanggal: 2026-06-11
Status: Disetujui (siap rencana implementasi)

## Latar Belakang

Masukan client untuk dashboard surveilans PD3I (`/admin/epidemiologi/pd3i-dashboard`):
1. Kasus campak-rubella belum ter-update di dashboard.
2. Tabs di dashboard PD3I belum berfungsi.
3. Export epidemiologi perlu mencakup semua field.

Catatan: item "dilengkapi filter wilayah" yang sempat dikira bagian PD3I ternyata
ditujukan untuk **dashboard opt timbang** → dipindah ke Paket D. Filter wilayah PD3I
(Kota/Kab., Wilker, Kelurahan) sudah lengkap.

## Lingkup
1. Perbaikan tabs.
2. Perbaikan angka Kasus Campak/Rubella.
3. Perluasan export ke "satu sheet lebar" mencakup semua field.

---

## 1. Perbaikan Tabs

### Akar masalah (terverifikasi)
- `public/admin/vendors/scripts/core.js` memuat **Bootstrap v4.4.1 + jQuery 3.2.1**.
- View `pd3i-dashboard.blade.php` memakai atribut **Bootstrap 5**:
  `data-bs-toggle="tab"` dan `data-bs-target="#..."`, yang **tidak dikenali** Bootstrap 4
  → plugin tab tak pernah aktif → klik tab tidak berpindah.

### Perubahan
- Ganti di `pd3i-dashboard.blade.php`:
  - `data-bs-toggle="tab"` → `data-toggle="tab"`
  - `data-bs-target="#x"` → `data-target="#x"`
- Event `shown.bs.tab` pada Bootstrap 4 dipicu lewat jQuery, **tidak** tertangkap
  `addEventListener`. Ganti handler resize chart + `leafletMap.invalidateSize()` menjadi:
  `$('[data-toggle="tab"]').on('shown.bs.tab', function () { ... })`.
- Pastikan tidak ada regresi pada panel/skeleton (kelas `.tab-pane fade show active`
  identik di BS4 & BS5).

---

## 2. Perbaikan Kasus Campak/Rubella

### Akar masalah (TERVERIFIKASI dari DB live, 2026-06-11)
Verifikasi 590 kasus Campak-Rubella (`id_jenis_kasus=1`):

| Sumber | Isi |
|--------|-----|
| `surveillance_case_spesimen.penyakit_terkonfirmasi` (yang **dibaca** dashboard) | **NULL semua** (396 baris) |
| `status_kasus` | confirmed **159**, discarded **122**, suspected 309 |
| `status_lab` | positif **159**, negatif 121, belum_diperiksa 296, proses 14 |
| `hasil_lab` (kolom teks `surveillance_cases`) | "Campak: Positif" (82), "Campak: Positif \| Rubella: Negatif" (70), "Rubella: Positif" (2), "Campak: Negatif \| Rubella: Negatif" (114), NULL (310), dll |

- Dashboard (`SurveillanceRepository::getPd3iKinerja`) menghitung "Kasus Campak/Rubella"
  dari `penyakit_terkonfirmasi IN ('Campak','Rubella')` — tapi field itu **100% kosong**
  → kartu selalu 0 walau "Suspek" (= total kasus) bertambah. (Sesuai laporan client
  "Suspek jalan, Kasus 0".)
- Data konfirmasi **ADA**, hanya di tempat berbeda: pemisahan Campak vs Rubella di teks
  `hasil_lab`, confirmed/discarded di `status_kasus` (= `status_lab` positif/negatif).
- Hardcode `id_jenis_kasus` 1–4 di dashboard ternyata **benar** (590 kasus CR ada di id=1).
  Baris penyakit **duplikat** id 7=CAMPAK, 8=RUBELLA, 9=POLIO, 10=DIFTERI (hasil
  `firstOrCreate(['nama_penyakit'])` di import) ada tapi **0 kasus** — bukan biang bug,
  hanya sampah di dropdown filter.

### Keputusan desain (FINAL — dipilih client)
Ubah query dashboard agar membaca dari sumber yang benar-benar berisi data, defensif dua arah
(legacy `hasil_lab` + `penyakit_terkonfirmasi` untuk entri manual mendatang). **Tanpa migrasi
data.** Parsing hanya pada "Positif" (typo data hanya pada kata "Negatif", mis. "Neagtif").

### Perubahan di `SurveillanceRepository::getPd3iKinerja` (panel Campak-Rubella)
- **Kasus Campak** = `hasil_lab LIKE '%Campak: Positif%'` **OR** `penyakit_terkonfirmasi='Campak'`.
- **Kasus Rubella** = `hasil_lab LIKE '%Rubella: Positif%'` **OR** `penyakit_terkonfirmasi='Rubella'`.
- **Discarded / Negatif** = `status_kasus='discarded'`.
- **% Pengambilan Spesimen / % Lab / Positivity** disesuaikan agar konsisten dengan
  `status_lab`/`status_kasus` (denominator = kasus dgn status_lab final; positivity =
  confirmed / diperiksa). Detail ambang difinalkan saat implementasi, mengacu angka di atas
  (159 positif = 159 confirmed).
- Catatan implementasi: `hasil_lab` berada di tabel utama (mencakup semua 590 kasus, tak
  bergantung ada/tidaknya baris spesimen child), jadi subquery `whereHas('spesimen')` lama
  diganti kondisi pada kasus utama + `orWhereHas` untuk `penyakit_terkonfirmasi`.

### Cleanup minor (opsional, aman)
- Non-aktifkan / hapus baris penyakit duplikat tak terpakai (id 7–10, 0 kasus) agar tidak
  muncul di dropdown filter penyakit. Pertimbangkan juga guard di `Pd3iImport::resolveJenisKasus`
  agar tidak membuat duplikat di masa depan (match by `kode_penyakit`). Terkait [[project_pd3i_import]].

---

## 3. Export "Satu Sheet Lebar"

File: `app/Exports/SurveillanceExport.php` (kelas `WithMapping`/`WithHeadings`).

### Lengkapi kolom tabel utama yang ketinggalan
- Gejala baru: `gejala_pseudomembran`, `gejala_leher_bengkak`, `gejala_apnea`,
  `gejala_adenopathy`, `gejala_arthralgia`, `gejala_kehamilan`.
- `tanggal_onset_gejala`, `kategori_umur`, `pekerjaan` (bila ada), dan field lain di
  `surveillance_cases` yang belum ada di export.
- Foto dokumentasi: nama/URL `foto_dokumentasi` & `foto_dokumentasi_2`.

### Ratakan tabel relasi (one-to-many) jadi kolom berulang
Eager-load relasi di `query()`; tambahkan kolom dengan cap default **3** (kecuali imunisasi):
- **Imunisasi** — 5 set tetap: `Imunisasi {1..5} Diberikan / Tanggal / Sumber Informasi`.
- **Spesimen** — cap 3 set: jenis, tgl ambil, tgl kirim, tgl terima lab, status pemeriksaan,
  **penyakit terkonfirmasi**, nama variant/genotype.
- **Kontak Erat** — cap 3 set: nama, hubungan, tgl lahir, no telp, alamat, tgl kontak
  terakhir, ada gejala, jumlah imunisasi MR + kolom "Jumlah Kontak Erat".
- **Faskes Berobat (MoD)** — cap 3 set: jenis faskes, nama faskes, tgl berobat,
  jenis perawatan, tgl keluar.

Cap dijadikan konstanta agar mudah disesuaikan.

---

## Kriteria Sukses
- Klik antar tab PD3I berpindah panel; chart & peta re-render dengan benar.
- Setelah verifikasi & fix, kartu "Kasus Campak/Rubella" menampilkan angka sesuai data
  terkonfirmasi (bukan selalu 0).
- File export Excel berisi seluruh field kasus + data relasi (imunisasi/spesimen/kontak/
  faskes) dalam satu sheet, tanpa error pada dataset nyata.

## Di luar lingkup
- Filter wilayah dashboard timbang (Paket D).
- Perubahan struktur normalisasi spesimen legacy vs child table di luar yang diperlukan
  untuk perbaikan #2.

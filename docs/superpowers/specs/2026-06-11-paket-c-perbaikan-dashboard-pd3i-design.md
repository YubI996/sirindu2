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

### Akar masalah (terverifikasi dari kode)
- Dashboard (`SurveillanceRepository::getPd3iKinerja`) menghitung "Kasus Campak/Rubella"
  dari `surveillance_case_spesimen.penyakit_terkonfirmasi IN ('Campak','Rubella')`.
- Form manual (Section F) **memang** menangkap `spesimen[][penyakit_terkonfirmasi]`.
- **Import (`Pd3iImport`) tidak** membuat baris `surveillance_case_spesimen` dan tidak
  pernah mengisi `penyakit_terkonfirmasi`; semua kasus impor diberi `status_kasus='suspected'`.
- Karena mayoritas data berasal dari import → `penyakit_terkonfirmasi` kosong → kartu
  "Kasus Campak/Rubella" = 0 walau "Suspek" bertambah. (Gejala yang dilaporkan client:
  "Suspek jalan, Kasus 0".)

### Keputusan desain
- **`penyakit_terkonfirmasi` tetap sumber kebenaran** (epidemiologis benar; dashboard
  sudah memakainya). `status_lab` ditolak sebagai sumber karena tidak memisahkan
  Campak vs Rubella.

### Langkah
1. **Verifikasi (langkah pertama, saat MySQL hidup):**
   - Distribusi `surveillance_cases.id_jenis_kasus` (cek baris penyakit duplikat hasil
     `firstOrCreate(['nama_penyakit'])` di import).
   - Berapa kasus punya baris `surveillance_case_spesimen`, berapa yang
     `penyakit_terkonfirmasi`-nya terisi.
   - **Cek file `pd3i.xlsx`**: apakah ada kolom hasil lab / klasifikasi akhir yang bisa
     dipetakan.
2. **Jika ada baris penyakit duplikat** → dashboard resolve ID penyakit lewat
   `kode_penyakit` (bukan hardcode `id` 1–4) + rencana merge/normalisasi duplikat.
3. **Jika Excel punya kolom hasil lab** (Opsi B) → tambah pemetaan di `Pd3iImport`:
   buat baris `surveillance_case_spesimen` + isi `penyakit_terkonfirmasi`, lalu
   **backfill** data lama. Jika tidak ada → data lama diisi manual; form manual sudah cukup
   untuk data baru.
4. Pastikan form manual menyimpan `penyakit_terkonfirmasi` dengan benar (regression check).

### Catatan
Cabang B (mapping import + backfill) bersifat **kondisional** pada hasil verifikasi.
Spec ini sengaja tidak menebak isi Excel; keputusan final diambil setelah langkah 1.

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

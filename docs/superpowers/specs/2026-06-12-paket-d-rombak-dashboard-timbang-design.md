# Paket D — Rombak Dashboard Operasi Timbang

Tanggal: 2026-06-12
Status: Disetujui (siap rencana implementasi)

## Latar Belakang

Masukan client (item #10 & #11):
- Buang statistik **MBG** dari dashboard timbang.
- Tambah kartu ringkas: **balita sasaran, hadir, stunting, gizi kurang, gizi buruk,
  BB tidak naik**.
- **Peta per RT dan Kelurahan**.
- **Output yang bisa ditindak**: kartu/grafik **clickable → modal** berisi **tabel daftar
  nama & alamat** yang perlu ditangani, **bisa diekspor**, plus grafik.
- **Filter wilayah** (sebelumnya dikira bagian PD3I) sebenarnya untuk dashboard ini.

## Aset yang sudah ada (dipakai ulang)

- `TimbangDashboardController` + `resources/views/admin/dashboard/timbang.blade.php`:
  sudah ada filter (Tahun + Kelurahan), KPI (ditimbang/kunjungan/vitA/MBG), status gizi
  (TB/U, BB/U, IMT/U donut), tren, coverage per kelurahan, indikator program.
  Helper `latestVisitQuery()` (kunjungan terakhir per anak), `parseFilters()`,
  perhitungan z-score inline (koreksi posisi ±0.7).
- **Peta lengkap** `resources/views/admin/dashboard/map.blade.php` (Leaflet + GeoJSON
  `public/geojson/{Kota Bontang-KECAMATAN,Kota Bontang-KEL_DESA,batas-rt-bontang}.geojson`
  + `geojson/mapping.json`): layer Kecamatan/Kelurahan/RT, mode warna count/stunting/
  imunisasi, popup stats. **Dipakai ulang**, ditambah mode gizi.
- Geo hierarki: `anak.id_kec/id_kel/id_rt/id_posyandu` → tabel `kecamatan/kelurahan/rt/posyandu`.

## Keputusan desain (dikonfirmasi client)

1. **Gizi kurang/buruk = IMT/U (BB/TB)** standar Kemenkes: gizi kurang z −3..−2,
   gizi buruk z <−3 (sudah dihitung `imt_u.kurang` / `imt_u.buruk`).
2. **BB tidak naik = perbandingan 2 kunjungan terakhir** (BB terakhir ≤ BB sebelumnya
   per anak); bila anak hanya punya 1 kunjungan, **fallback** ke `data_anak.ntob='T'`
   bila terisi.
3. **Peta = pakai ulang** `dashboard/map` + tambah mode warna gizi (gizi kurang/buruk &
   BB tidak naik). Disematkan/ditaut dari dashboard timbang.
4. **Filter wilayah = Tahun + Kecamatan + Kelurahan + RT + Posyandu** (cascading).
5. **Buang MBG** dari KPI & indikator program.

## Perubahan

### 1. Filter wilayah cascading (controller + view)
- View: tambah dropdown **Kecamatan**, **RT**, **Posyandu** di `tb-filter`, cascading:
  Kecamatan → Kelurahan → RT/Posyandu. Non-super-admin tetap terkunci ke `id_kel`-nya.
- `parseFilters()` diperluas mengembalikan `[tahun, kecId, kelId, rtId, posyanduId]`.
  Semua query (`baseQuery`, `latestVisitQuery`, `gizi`, `tren`, `coverage`, `program`)
  menerima filter wilayah tambahan (where pada `a.id_kec/id_kel/id_rt/id_posyandu`).
- Endpoint kecil untuk opsi cascading (kelurahan-per-kecamatan, RT/posyandu-per-kelurahan)
  — pakai endpoint geografis yang sudah ada di `routes/api.php` bila tersedia, kalau tidak
  tambah method ringan di controller.

### 2. Kartu ringkas baru (KPI)
Ganti grid KPI menjadi 6 kartu **clickable** sesuai permintaan:
| Kartu | Sumber |
|-------|--------|
| Balita Sasaran | `totalAnakQuery()` (total anak terfilter wilayah) |
| Hadir (Ditimbang) | `total_ditimbang` (distinct id_anak ada kunjungan, terfilter) |
| Stunting | `tb_u.sangat_pendek + pendek` (kunjungan terakhir) |
| Gizi Kurang | `imt_u.kurang` |
| Gizi Buruk | `imt_u.buruk` |
| BB Tidak Naik | hasil perbandingan 2 kunjungan terakhir (+ntob fallback) |

- **Hapus** kartu MBG dan blok MBG di `program()` + view.
- Tiap kartu menyimpan `data-kategori` (sasaran/hadir/stunting/gizi_kurang/gizi_buruk/
  bb_tidak_naik) untuk dipakai modal.

### 3. Endpoint daftar nama (actionable list) + modal
Inti permintaan #11: kartu/segmen grafik di-klik → modal daftar anak yang bisa ditindak.
- **Endpoint baru** `GET admin.timbang.daftar?kategori=...&{filter wilayah/tahun}`
  mengembalikan baris anak: `nama`, `nik`, `alamat` (domisili — kolom `alamat`),
  `kecamatan`, `kelurahan`, `rt`, `posyandu`, `umur_bln`, nilai indikator terkait
  (mis. z-score TB/U untuk stunting; BB sebelumnya→terakhir untuk BB tidak naik),
  `tgl_kunjungan terakhir`. Reuse `latestVisitQuery()` + join geografi.
- **Modal** (komponen Blade + JS): tabel (DataTables/atau tabel ringan + search),
  grafik ringkas opsional (distribusi per kelurahan/RT untuk kategori itu), tombol
  **Export Excel**.
- Kartu & segmen donut (Chart.js `onClick`) memanggil modal dengan kategori terkait.

### 4. Export daftar per kategori
- **Export baru** `app/Exports/TimbangDaftarExport.php` (`FromCollection`/`FromQuery` +
  `WithHeadings` + `WithMapping`) menerima kategori + filter, kolom = identitas + alamat
  domisili + wilayah + indikator. Route `GET admin.timbang.daftar.export`.
- Pertimbangkan reuse pola `AnakExport`. Format: satu sheet, kolom rata.

### 5. Peta — tambah mode gizi (reuse `dashboard/map`)
- Di controller peta (yang menyiapkan `kelurahanZScore`/`rtZScore`): tambah agregat
  **gizi_kurang**, **gizi_buruk**, **bb_tidak_naik** per kelurahan & per RT.
- Di `map.blade.php`: tambah tombol mode **Gizi Kurang/Buruk** dan **BB Tidak Naik** +
  fungsi warna + legend + popup. Pola identik mode `stunting` yang ada.
- Dari dashboard timbang: tombol "Buka Peta Sebaran" (tautan, membawa filter wilayah/tahun
  sebagai query string) — embedding penuh opsional bila waktu cukup.

### 6. Perhitungan "BB tidak naik" (helper terpusat)
- Tambah method di controller (atau service kecil) yang, untuk himpunan anak terfilter,
  mengambil **dua kunjungan terakhir** per anak (extend pola `latestVisitQuery` → ambil 2
  tgl terbesar) lalu hitung `bb_terakhir <= bb_sebelumnya`. Anak 1 kunjungan → cek `ntob`.
- Dipakai oleh: KPI count, endpoint daftar, dan agregat peta. Satu sumber kebenaran.

## Edge cases
- Anak tanpa kunjungan tahun terfilter → tidak masuk "hadir"; tetap dihitung "sasaran".
- Anak 1 kunjungan & `ntob` kosong → tidak masuk "BB tidak naik" (tak bisa disimpulkan).
- Alamat domisili `alamat` kosong → tampilkan "-" di modal/export (pakai gabungan
  RT/Kelurahan sebagai konteks).
- Non-super-admin: semua filter & daftar terkunci ke kelurahannya (abort 403 bila tak ada).

## Di luar lingkup
- Penggabungan dashboard ke beranda (Paket F).
- Grafik korelasi stunting ↔ vaksin (Paket E).
- Perubahan struktur GeoJSON / name-mapping (pakai yang ada).

## Kriteria sukses
- Filter Tahun + Kecamatan/Kelurahan/RT/Posyandu cascading bekerja & memfilter semua panel.
- 6 kartu (sasaran/hadir/stunting/gizi kurang/gizi buruk/BB tidak naik) tampil benar; MBG hilang.
- Klik kartu/segmen → modal daftar nama+alamat domisili, bisa diekspor ke Excel tanpa error.
- Peta menampilkan mode gizi kurang/buruk & BB tidak naik per Kelurahan & RT.
- Tidak ada regresi pada perhitungan z-score/coverage existing.

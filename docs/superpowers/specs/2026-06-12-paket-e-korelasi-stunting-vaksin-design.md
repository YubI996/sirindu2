# Paket E — Grafik Korelasi Stunting ↔ Vaksin

Tanggal: 2026-06-12
Status: Disetujui (siap rencana implementasi)

## Latar Belakang

Masukan client (item #6): grafik untuk **membandingkan/mengorelasikan stunting dengan
pemberian vaksin**. Tujuan: menunjukkan pola "wilayah dengan cakupan imunisasi lebih
lengkap cenderung prevalensi stunting lebih rendah" untuk advokasi program.

## Keputusan desain (dikonfirmasi client)

1. **Bentuk = scatter per wilayah.** Tiap titik = 1 **kelurahan**: sumbu-X = **% Imunisasi
   Dasar Lengkap (IDL)**, sumbu-Y = **% stunting**. Ukuran titik (opsional) = jumlah balita.
2. **Metrik vaksin = status IDL** per anak (`ImunisasiStatusService::isIdlLengkap()` /
   `getIdlCoverage()`), bukan jumlah dosis.
3. **Lokasi = Dashboard Imunisasi** (`admin.imunisasiDashboard`,
   `resources/views/admin/imunisasi/dashboard.blade.php`).

## Aset yang dipakai ulang

- `App\Services\ImunisasiStatusService`: `getIdlCoverage($filters)`,
  `isIdlLengkap($anak)`, dan agregat coverage per Kelurahan/RT yang sudah ada di
  `AdminController` (≈baris 2467 `coverage by Kelurahan`, 2532 `by RT`).
- Perhitungan **stunting per kelurahan** (TB/U <−2SD pada kunjungan terakhir) — pola sama
  dengan `kelurahanZScore` di `dashboard/map` & `TimbangDashboardController::gizi`.
- `AdminController::imunisasiDashboard` (server-rendered, filter via GET).

## Perubahan

### 1. Data korelasi per kelurahan (server-side)
Di `imunisasiDashboard` (atau method privat `korelasiStuntingVaksin($filters)`), hasilkan
koleksi per kelurahan:
```
[ nama, total_balita, idl_lengkap, idl_pct, stunting, stunting_pct ]
```
- **idl_pct** = `idl_lengkap / balita_dgn_status × 100` (anak ≥ usia syarat IDL,
  pakai service yang ada).
- **stunting_pct** = `stunting / balita_diukur × 100` (TB/U pada kunjungan terakhir,
  reuse perhitungan z-score yang ada — pertimbangkan ekstraksi helper bersama agar tidak
  menduplikasi rumus koreksi posisi ±0.7).
- Hormati filter wilayah/tahun yang sudah dipakai dashboard imunisasi.
- Kelurahan tanpa data (balita 0) di-skip.

### 2. Card grafik scatter (view)
- Tambah satu `card` "Korelasi Cakupan IDL vs Prevalensi Stunting" di dashboard imunisasi.
- Chart.js **scatter**: titik `{x: idl_pct, y: stunting_pct}`, tooltip = nama kelurahan +
  angka (balita, idl%, stunting%). Garis tren/regresi linear opsional (hitung sederhana di
  JS) untuk menegaskan arah hubungan.
- Sumbu X "% Imunisasi Dasar Lengkap" (0–100), Y "% Stunting" (0–100).
- Data dilewatkan via `@json($korelasiData)` (dashboard sudah server-rendered; tidak perlu
  endpoint AJAX baru).

### 3. Catatan interpretasi
- Sertakan sub-judul/disclaimer kecil: "Korelasi tingkat wilayah, bukan kausalitas
  individual" agar tidak salah tafsir.

## Edge cases
- Kelurahan dengan sedikit balita (mis. <5) → tetap ditampilkan tapi bisa diberi opacity
  lebih rendah / catatan; tidak dibuang agar peta wilayah utuh.
- Pembagi 0 (tak ada anak terukur/berstatus) → kelurahan di-skip.
- Filter mempersempit ke 1 kelurahan → scatter berisi 1 titik (tetap valid).

## Di luar lingkup
- Analisis statistik formal (uji korelasi/p-value) — cukup garis tren visual.
- Korelasi level individu/RT (bisa menyusul; default kelurahan).
- Menempatkan grafik di dashboard timbang/beranda (lokasi final = dashboard imunisasi).

## Kriteria sukses
- Dashboard imunisasi menampilkan scatter kelurahan (IDL% vs stunting%) yang konsisten
  dengan angka coverage & status gizi di tempat lain.
- Mengikuti filter wilayah/tahun dashboard imunisasi.
- Tidak ada duplikasi rumus z-score yang menyimpang dari sumber kebenaran.

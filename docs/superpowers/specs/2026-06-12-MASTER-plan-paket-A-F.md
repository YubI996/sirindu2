# Master Plan — Implementasi Paket A–F (Masukan Client Juni 2026)

Tanggal: 2026-06-12
Status: Rangkuman lintas-spec, siap eksekusi

Rangkuman & urutan eksekusi dari 6 paket. Tiap paket punya spec rinci tersendiri
(`docs/superpowers/specs/`). **Paket B sudah selesai diimplementasikan** (belum commit).

## Peta paket

| Paket | Ringkas | Spec | Status |
|-------|---------|------|--------|
| A | Dua alamat KTP & Domisili (kolom `alamat_ktp`) | `2026-06-11-paket-a-...` | Spec ✅ |
| B | Template & form pengukuran (posisi/bln/alasan) | plan `moonlit-skipping-bentley` | **Impl ✅** |
| C | Perbaikan dashboard PD3I (tabs/campak-rubella/export) | `2026-06-11-paket-c-...` | Spec ✅ |
| D | Rombak dashboard timbang (card/filter/peta/modal) | `2026-06-12-paket-d-...` | Spec ✅ |
| E | Korelasi stunting↔vaksin (scatter di dasbor imunisasi) | `2026-06-12-paket-e-...` | Spec ✅ |
| F | Konsolidasi menu Dashboard + quicklink beranda | `2026-06-12-paket-f-...` | Spec ✅ |

## Temuan review lintas-spec (kunci)

1. **Rumus z-score / status gizi terduplikasi ~6 tempat** (`helpers.php::z_score`,
   `TimbangDashboardController::gizi`, perhitungan `kelurahanZScore`/`rtZScore` di
   `AdminController` peta, dll). **D dan E sama-sama** menghitung stunting/status gizi.
   → **Fondasi: ekstrak `App\Services\StatusGiziService`** (klasifikasi TB/U, BB/U, IMT/U +
   koreksi posisi ±0.7) dan pakai ulang di semua titik. Hindari menambah duplikasi baru.
2. **Backfill `posisi` lama** (ditunda dari B): data lama menyimpan "berdiri"/"terlentang"/
   "Bb". D (peta + gizi) & E membaca posisi; angka historis baru benar setelah dinormalkan.
   → **Fondasi: migrasi backfill** `UPDATE data_anak SET posisi = normalisasi(posisi)`.
3. **A → D**: `alamat` (domisili) yang ditetapkan A dipakai modal "daftar nama+alamat" D;
   export `alamat_ktp` yang ditunda A ikut diselesaikan di pekerjaan export D.
4. **C → F**: rapikan PD3I (C) sebelum menata ulang menu (F).

## Urutan eksekusi

### Fase 0 — Fondasi bersama (prasyarat D & E)
- [F0.1] Ekstrak `StatusGiziService` dari `helpers.php::z_score` + perhitungan inline di
  `TimbangDashboardController::gizi` & peta. API: `klasifikasi($bb,$tb,$bln,$posisi,$jk)` →
  `['tb_u'=>..,'bb_u'=>..,'imt_u'=>..]`. Ganti pemanggil agar tak ada rumus ganda.
- [F0.2] Migrasi backfill `data_anak.posisi` → kanonik H/L via `normalisasi_posisi()`
  (helper Paket B sudah ada). Verifikasi jumlah baris terdampak sebelum & sesudah.

### Fase 1 — Paket A (dua alamat) — kecil, mandiri
- Migrasi `anak.alamat_ktp` (TEXT nullable). Form create/edit (2 seksi: Domisili + KTP +
  tombol "Samakan"). Validasi nullable. Tampilan show. (Detail: spec A.)

### Fase 2 — Paket C (dashboard PD3I) — mandiri
- Tabs BS4 (`data-toggle`/`data-target` + jQuery `shown.bs.tab`). Kasus campak/rubella baca
  `hasil_lab`+`penyakit_terkonfirmasi` & `status_kasus`. Export "satu sheet lebar"
  (`SurveillanceExport`). (Detail: spec C.)

### Fase 3 — Paket D (dashboard timbang) — besar; pakai Fase 0
- Filter cascading Tahun+Kec+Kel+RT+Posyandu (`parseFilters` + semua query).
- 6 kartu (buang MBG). Endpoint `daftar` + modal daftar nama/alamat + `TimbangDaftarExport`.
- "BB tidak naik" helper (2 kunjungan + ntob fallback) — pakai `StatusGiziService`.
- Peta: tambah mode gizi kurang/buruk & BB tidak naik ke `dashboard/map` (reuse). (Detail: spec D.)

### Fase 4 — Paket E (korelasi) — kecil; pakai Fase 0
- Data korelasi per kelurahan (IDL% via `ImunisasiStatusService`, stunting% via
  `StatusGiziService`). Card scatter di dashboard imunisasi. (Detail: spec E.)

### Fase 5 — Paket F (menu + beranda) — terakhir
- Sidebar: grup "Dashboard" tunggal per role. Migrasi `users.beranda_quicklinks` (JSON).
  Daftar quicklink terpusat (role-aware) + UI pilih card + `updateQuicklinks`. (Detail: spec F.)

## Verifikasi menyeluruh
- Setiap fase: `php artisan test` hijau + smoke test halaman terkait.
- Fase 0: bandingkan angka stunting/gizi sebelum-sesudah ekstraksi (harus identik kecuali
  perbaikan akibat backfill posisi). Fase 3/4 mengacu angka yang sama (konsistensi silang).
- Commit per paket (pesan `feat(paket-x): ...`), spec di-`git add -f` (docs gitignored).

## Catatan
- Tidak ada perubahan menyentuh kontrak API publik `routes/api.php`.
- Bootstrap 4.4.1 (bukan 5) — semua komponen modal/tabs pakai konvensi BS4.

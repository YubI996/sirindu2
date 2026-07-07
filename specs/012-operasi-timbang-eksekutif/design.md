# Desain — Paket Respons Eksekutif Operasi Timbang

- **Tanggal:** 2026-07-07
- **Status:** Disetujui (menunggu review spec sebelum penyusunan rencana implementasi)
- **Konteks:** Merespons permintaan eksekutif (Walikota) atas operasi timbang: penyajian data gizi, daftar anak prioritas berjenjang, peta wilayah prioritas, dan pelacakan intervensi. Dibangun di atas dashboard yang sudah ada (Gizi & Timbang, Peta Sebaran, Proyeksi/Early Warning).

## Ringkasan keputusan

| Poin permintaan | Keputusan |
|---|---|
| I. Penyajian data (sasaran, hadir, stunting, gizi kurang/buruk, BB tidak naik, peta) | Sudah ada di dashboard timbang — dipertahankan |
| II. Daftar prioritas P1–P4 | Ditambahkan sebagai **subsection collapsible P1–P3 di dalam section "Daftar Prioritas Intervensi" yang ada**; skor gabungan **tidak diubah** |
| BB tidak naik | **Logika sekarang dipertahankan** (BB terakhir ≤ sebelumnya, atau `ntob='T'`) |
| III. Kunjungan serentak (survei rumah) | **Di luar lingkup paket ini** |
| IV/V&VI. Intervensi | Modul **Intervensi Gizi** — log per anak + rekap cakupan |
| V. Peta RT prioritas | Pewarnaan **kuantil Q1 hijau – M kuning – Q3 merah** per indikator, prevalensi ditampilkan |
| Wilker di proyeksi | Tambah baris **Puskesmas (wilker)** di kartu anak |
| VII. Data keluarga miskin (DTKS/P3KE) | **Skip** — slot P4 & kolom disiapkan, diaktifkan bila data tersedia |

## Prinsip

- **Satu sumber kebenaran klasifikasi gizi:** `App\Services\StatusGiziService`. Snapshot dihitung dengan service ini agar angka P1/P2 **konsisten dengan KPI dashboard timbang**.
- **YAGNI:** tidak membangun survei kunjungan rumah maupun integrasi DTKS pada paket ini.
- **Tidak mengubah** skor gabungan/risk score Early Warning yang sudah ada.

## Catatan sumber data

`data_anak` menyimpan angka z-score dari e-PPGBM (`zscore_bb_u`, `zscore_pb_u`, `zscore_bb_pb`) dan `ntob` (kolom "Naik Berat Badan" pada ekspor operasi timbang), tetapi **tidak** menyimpan teks status. Klasifikasi status tetap **dihitung ulang** dari `bb`/`tb`/`bln`/`posisi`/`jk` via `StatusGiziService` (perilaku dashboard saat ini). Angka z-score e-PPGBM tersimpan hanya sebagai cross-check, bukan sumber primer, karena tidak semua baris `data_anak` (mis. dari `UkurImport` / input manual) memiliki kolom tersebut terisi.

---

## Komponen 1 — Tabel snapshot `prioritas_gizi` (fondasi)

Satu baris per anak; sumber cepat untuk tab Prioritas, peta kuantil RT, dan rekap Intervensi.

### Skema

| Kolom | Tipe | Isi |
|---|---|---|
| `id` | PK | |
| `id_anak` | FK unik → `anak.id` | satu baris per anak |
| `id_kec, id_kel, id_rt, id_posyandu` | FK nullable | denormalisasi wilayah untuk agregasi peta |
| `gizi_buruk` | bool | BB/TB `severely_wasted` (< −3SD) |
| `gizi_kurang` | bool | BB/TB `wasted` (−2 s/d −3SD) |
| `stunting` | bool | TB/U `stunted` atau `severely_stunted` (< −2SD) |
| `bb_tidak_naik` | bool | logika existing (2 kunjungan / `ntob='T'`) |
| `prioritas` | tinyint nullable | P1 gizi buruk → P2 stunting → P3 BB tidak naik (ambil paling berat); P4 dicadangkan |
| `usia_bln` | int nullable | usia bulan saat kunjungan terakhir dihitung |
| `refreshed_at` | timestamp | waktu recompute terakhir |
| `created_at, updated_at` | timestamps | |

Index: unik `id_anak`; index pada `id_rt`, `id_kel`, `prioritas`.

### `PrioritasGiziService`

- `hitungUntukAnak(Anak $anak): array` — ambil kunjungan terakhir (≤60 bln, bb/tb > 0), klasifikasi via `StatusGiziService`, tentukan flag + `prioritas`. Untuk `bb_tidak_naik`, gunakan helper yang sama dengan `TimbangDashboardController::bbTidakNaikIds` (dipindah/dibagikan agar tidak diduplikasi).
- `refreshAnak(int $idAnak): void` — upsert satu baris snapshot (hapus bila anak sudah tidak memenuhi/valid → set semua flag false, `prioritas=null`).
- `refreshBatch(array $idAnak): void` — untuk import.
- `refreshAll(): void` — rebuild penuh.

**Penentuan `prioritas`** (paling berat menang): `gizi_buruk` → 1; else `stunting` → 2; else `bb_tidak_naik` → 3; else `null`. Flag individual tetap disimpan agar peta bisa menghitung prevalensi tiap indikator secara terpisah.

### Refresh "setiap ada data masuk"

- **Observer** `DataAnak` (created/updated/deleted) → `refreshAnak(id_anak)`.
- **Observer** `Anak` (created/updated pada kolom wilayah/tgl_lahir/deleted) → `refreshAnak` atau hapus baris.
- **Setelah import** (`OperasiTimbangImport`, `UkurImport`, `AnakImport`, `KohortImport`) → kumpulkan `id_anak` terdampak, panggil `refreshBatch` **sekali di akhir** (bukan per baris — hindari 14k recompute berulang).
- **Command** `php artisan prioritas:refresh` → `refreshAll()` untuk seed awal & pemulihan.

### Testing
- Unit `PrioritasGiziService`: P1/P2/P3 dengan referensi `z_score` yang di-seed (pola `StatusGiziService::useRefs`), termasuk kasus multi-flag (gizi buruk + stunting → prioritas 1, kedua flag true).
- Feature: observer mengisi/menghapus snapshot saat `DataAnak` berubah.

---

## Komponen 2 — Subsection Prioritas P1–P3 (Poin 1)

Di halaman **Proyeksi & Early Warning** (`admin.dashboard.early-warning`), **di dalam** section "Daftar Prioritas Intervensi" yang sudah ada, tambahkan **3 subsection collapsible**:

- **P1 — Gizi Buruk**, **P2 — Stunting**, **P3 — BB Tidak Naik**, dan **P4** (nonaktif; tooltip "butuh data keluarga miskin — belum tersedia").
- Sumber: query ringan ke `prioritas_gizi` (bukan recompute).
- Tiap baris: Nama, NIK, Usia, Posyandu, **Puskesmas (wilker)**, Kelurahan, RT, tombol Detail.
- **Export Excel** per tier.
- Daftar kartu skor gabungan yang sekarang **tetap ada** di bawah subsection ini (tidak diubah).

### Testing
- Feature: subsection menampilkan anak sesuai flag snapshot; export menghasilkan baris yang benar.

---

## Komponen 3 — Wilker Puskesmas di kartu anak (Poin 4)

Tambah baris **Puskesmas** pada tiap kartu anak di Daftar Prioritas Intervensi, via `WilkerPuskesmas::wilkerForKelurahanId($child->id_kel)`. Murni tampilan; data sudah tersedia.

### Testing
- Feature: kartu memuat nama wilker yang benar untuk kelurahan tertentu.

---

## Komponen 4 — Peta RT prioritas kuantil (Poin 3)

Di **Peta Sebaran** (`admin.dashboard.map`), ubah pewarnaan dari ambang tetap → **kuantil**:

- Hitung nilai tiap wilayah (RT/Kelurahan/Kecamatan sesuai layer aktif), lalu bagi jadi 3 kelas berdasarkan kuantil terhadap **semua wilayah pada layer itu**: **Q1 → Hijau**, **M (tengah) → Kuning**, **Q3 → Merah**. Wilayah tanpa data tetap abu-abu.
- **Mode indikator** (toggle, memperluas mode yang ada): prevalensi **stunting %**, prevalensi **gizi buruk/kurang %**, prevalensi **BB tidak naik %**, dan **jumlah anak prioritas**.
- **Popup/panel** wilayah menampilkan **angka prevalensi tiap indikator** (bukan hanya warna): total anak, stunting (n, %), gizi buruk/kurang (n, %), BB tidak naik (n, %), jumlah anak prioritas.
- Sumber agregasi: `prioritas_gizi` (cepat), digroup per `id_rt`/`id_kel`/`id_kec`.
- Ambang kuantil dihitung server-side (mis. controller/endpoint peta) agar konsisten; wilayah dengan total anak 0 dikeluarkan dari perhitungan kuantil.

### Testing
- Unit pembagian kuantil (Q1/M/Q3) dengan daftar nilai sintetis (termasuk kasus nilai seri & wilayah tanpa data).
- Feature: endpoint agregasi peta mengembalikan prevalensi per wilayah yang benar.

---

## Komponen 5 — Modul Intervensi Gizi (Poin 5&6)

Menu/halaman baru **Intervensi Gizi**.

### Skema `intervensi_gizi`

| Kolom | Tipe | Isi |
|---|---|---|
| `id` | PK | |
| `id_anak` | FK → `anak.id` | |
| `jenis` | enum/string | PMT, Pemeriksaan Kesehatan, Suplementasi, Rujukan, Bansos, Dukungan Pangan, Pendampingan Keluarga |
| `tanggal` | date nullable | |
| `pelaksana` | string nullable | dinas/petugas (teks bebas) |
| `status` | enum/string | Direncanakan, Berjalan, Selesai |
| `catatan` | text nullable | |
| `created_by` | FK → `users.id` | |
| `created_at, updated_at` | timestamps | |

Satu anak → banyak baris intervensi.

### Fungsi

- **Input** intervensi dari daftar prioritas (tombol "+ Intervensi" per anak) dan/atau dari halaman Intervensi Gizi.
- **Rekap cakupan:** "X dari Y anak prioritas sudah diintervensi (%)". `Y` = anak dengan `prioritas` P1–P3 di `prioritas_gizi`; `X` = subset yang memiliki ≥1 baris `intervensi_gizi`. Dapat difilter per wilayah (kec/kel/rt/posyandu).
- Daftar/riwayat intervensi per anak dapat dilihat & di-edit.
- Scoping akses mengikuti pola existing (super-admin lihat semua; faskes/kelurahan sesuai wilayahnya).

### Testing
- Feature: CRUD intervensi; satu anak banyak baris.
- Feature: rekap cakupan menghitung X/Y benar, termasuk filter wilayah dan anak prioritas tanpa intervensi.

---

## Komponen 6 — Data keluarga miskin (Poin 7)

**Skip.** Slot `prioritas = 4` dan konsep subsection P4 disiapkan namun nonaktif. Diaktifkan pada iterasi mendatang bila data DTKS/P3KE tersedia dari Dinas Sosial.

---

## Urutan implementasi

1. **Fondasi** — migrasi `prioritas_gizi`, `PrioritasGiziService`, observer + hook import, command `prioritas:refresh`, seed awal.
2. **Subsection P1–P3** + baris wilker Puskesmas di Early Warning.
3. **Peta kuantil RT** + prevalensi per indikator.
4. **Modul Intervensi Gizi** (tabel, CRUD, rekap).

Tiap tahap dapat dirilis independen; tahap 1 memblokir 2–4.

## Risiko & catatan

- **Biaya refresh saat import besar:** wajib `refreshBatch` sekali di akhir import, bukan per baris.
- **Konsistensi angka:** karena snapshot memakai `StatusGiziService`, angka P1/P2 harus sama dengan KPI dashboard timbang untuk filter yang sama — jadikan asersi test bila memungkinkan.
- **Definisi indikator wasting:** dashboard timbang memakai **BB/TB** untuk gizi buruk/kurang (bukan IMT/U). Snapshot mengikuti BB/TB agar konsisten dengan konteks operasi timbang. (Catatan: loop Early Warning yang lama memakai IMT/U untuk skor gabungan — skor itu tidak diubah; hanya tab prioritas baru yang memakai snapshot berbasis BB/TB.)

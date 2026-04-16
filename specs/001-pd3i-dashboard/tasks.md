# Tasks: Dashboard Surveilans PD3I

**Input**: Design documents dari `specs/001-pd3i-dashboard/`
**Prerequisites**: plan.md ✅ spec.md ✅ research.md ✅ data-model.md ✅ contracts/ ✅

**Tests**: Tidak diminta — tidak ada test tasks.

**Organization**: Tasks dikelompokkan per user story untuk implementasi dan validasi independen.

## Format: `[ID] [P?] [Story] Deskripsi dengan path file`

- **[P]**: Dapat berjalan paralel (file berbeda, tidak ada dependensi antar task)
- **[Story]**: User story yang dilayani (US1–US4)

---

## Phase 1: Setup (Infrastruktur & Skeleton)

**Purpose**: Buat semua file kosong/skeleton dan daftarkan route — tidak ada logika bisnis di fase ini.

- [x] T001 Daftarkan 6 route baru di `routes/web.php` dalam blok middleware `auth` yang sudah ada: `GET /admin/epidemiologi/pd3i-dashboard` (index), 4 GET `/api/*` (kinerja/demografi/tren/wilayah), `POST /export-pdf` — semua dengan middleware `module.role:superadmin`
- [x] T002 [P] Buat `app/Http/Controllers/Pd3iDashboardController.php` dengan constructor yang inject `SurveillanceRepository`, dan 6 method kosong: `index()`, `kinerja()`, `demografi()`, `tren()`, `wilayah()`, `exportPdf()`
- [x] T003 Buat `resources/views/admin/epidemiologi/pd3i-dashboard.blade.php` — skeleton `@extends('admin::layouts.app')` dengan header, filter bar (dropdown tahun/penyakit/wilker + tombol Export PDF), tab navigation (Kinerja | Demografi | Tren | Wilayah), dan 4 tab pane kosong dengan ID `#tab-kinerja`, `#tab-demografi`, `#tab-tren`, `#tab-wilayah`
- [x] T004 [P] Buat `resources/views/admin/epidemiologi/pdf/pd3i-dashboard.blade.php` — skeleton Blade inline-CSS A4 Landscape dengan placeholder untuk 4 section (Kinerja, Demografi, Tren, Wilayah) dan footer filter info

**Checkpoint**: Halaman `GET /admin/epidemiologi/pd3i-dashboard` dapat diakses dan menampilkan layout kosong tanpa error 404/500.

---

## Phase 2: Foundational (Prasyarat Semua User Story)

**Purpose**: Infrastruktur query, filter, dan JS yang digunakan oleh SEMUA tab.

**⚠️ CRITICAL**: Tidak ada user story yang dapat dimulai sebelum fase ini selesai.

- [x] T005 Tambahkan private method `pd3iBaseQuery(int $tahun, ?int $jenisKasusId, ?string $wilker): Builder` ke `app/Repositories/Admin/Epidemiologi/SurveillanceRepository.php` — apply filter `YEAR(tanggal_lapor) = $tahun`, opsional `id_jenis_kasus`, opsional `wilker_puskesmas`
- [x] T006 [P] Tambahkan private method `parsePd3iFilters(Request $request): array` ke `app/Http/Controllers/Pd3iDashboardController.php` — validasi dan return `['tahun' => int, 'jenis_kasus_id' => ?int, 'wilker' => ?string]` dengan default `tahun = year(now())`
- [x] T007 Tambahkan method `index()` lengkap ke `app/Http/Controllers/Pd3iDashboardController.php` — query `JenisKasusEpidemiologi::active()->get()` dan list unik `wilker_puskesmas` dari `surveillance_cases`, pass ke view `pd3i-dashboard`
- [x] T008 Tambahkan blok `<script>` ke `resources/views/admin/epidemiologi/pd3i-dashboard.blade.php` — fungsi `fetchAllTabs(params)` yang memanggil 4 API endpoint paralel via `Promise.all`, fungsi `showSkeletons()` / `hideSkeletons()` untuk semua komponen, dan fungsi `destroyCharts()` untuk cleanup Chart.js instance sebelum re-render
- [x] T009 [P] Tambahkan event listener filter ke `resources/views/admin/epidemiologi/pd3i-dashboard.blade.php` — saat nilai dropdown tahun/penyakit/wilker berubah, panggil `fetchAllTabs(buildParams())` dan tampilkan skeleton per komponen

**Checkpoint**: Filter bar functional; console network tab menunjukkan 4 API call paralel saat filter diubah (response boleh kosong/404 sementara).

---

## Phase 3: User Story 1 – Kinerja Surveilans (Priority: P1) 🎯 MVP

**Goal**: Pengguna dapat melihat scorecard kinerja surveilans per panel penyakit (Campak-Rubella, AFP, Difteri, Pertusis) beserta % sampel dan positivity rate, berdasarkan filter aktif.

**Independent Test**: Buka `/admin/epidemiologi/pd3i-dashboard`, pilih tahun, verifikasi 4 panel scorecard menampilkan angka sesuai query langsung ke `surveillance_cases` di DB.

- [x] T010 [US1] Tambahkan method `getPd3iKinerja(int $tahun, ?int $jenisKasusId, ?string $wilker): array` ke `app/Repositories/Admin/Epidemiologi/SurveillanceRepository.php` — 4 sub-query per panel: (a) campak-rubella: suspek/confirmed_campak/confirmed_rubella/discarded/meninggal/pct_sampel/pct_lab/positivity_rate; (b) AFP: total/confirmed/npafp_rate=null; (c) Difteri: observasi/confirmed; (d) Pertusis: suspek
- [x] T011 [P] [US1] Implementasi method `kinerja(Request $request): JsonResponse` di `app/Http/Controllers/Pd3iDashboardController.php` — panggil `parsePd3iFilters`, panggil `repo->getPd3iKinerja(...)`, return `response()->json($data)`
- [x] T012 [US1] Tambahkan HTML 4 panel scorecard ke tab `#tab-kinerja` di `resources/views/admin/epidemiologi/pd3i-dashboard.blade.php` — Panel Campak-Rubella (8 scorecard card), Panel AFP (3 card + "–" untuk NPAFP Rate), Panel Difteri (2 card), Panel Pertusis (1 card); setiap card punya `data-field` attribute dan skeleton placeholder div
- [x] T013 [US1] Tambahkan fungsi `renderKinerja(data)` ke blok script di `resources/views/admin/epidemiologi/pd3i-dashboard.blade.php` — populate semua scorecard dari response JSON; untuk `npafp_rate = null` tampilkan "–" dengan tooltip "Data populasi belum tersedia"

**Checkpoint**: Tab Kinerja menampilkan angka real sesuai data DB. Angka konsisten dengan query manual ke `surveillance_cases`.

---

## Phase 4: User Story 2 – Tren Kasus (Priority: P2)

**Goal**: Pengguna dapat melihat kurva epidemi mingguan, tren laporan bulanan, dan tren per faskes/kecamatan/kelurahan dalam bentuk grafik.

**Independent Test**: Buka tab Tren, verifikasi kurva epiweek menampilkan batang per minggu sesuai distribusi `tanggal_onset` di DB untuk tahun terpilih.

- [x] T014 [US2] Tambahkan method `getPd3iTren(int $tahun, ?int $jenisKasusId, ?string $wilker): array` ke `app/Repositories/Admin/Epidemiologi/SurveillanceRepository.php` — 5 query: (a) epiweek via `YEARWEEK(tanggal_onset, 3)` untuk tahun-1 s/d tahun; (b) bulanan 12 bulan via `MONTH(tanggal_lapor)` dengan padding 0 untuk bulan kosong; (c) per_faskes: JOIN `rumah_sakits`; (d) per_kecamatan: JOIN `kecamatan`; (e) per_kelurahan: JOIN `kelurahan` + `kecamatan`
- [x] T015 [P] [US2] Implementasi method `tren(Request $request): JsonResponse` di `app/Http/Controllers/Pd3iDashboardController.php`
- [x] T016 [US2] Tambahkan HTML ke tab `#tab-tren` di `resources/views/admin/epidemiologi/pd3i-dashboard.blade.php` — 5 canvas element: `#chart-epiweek` (bar grouped), `#chart-bulanan` (line), `#chart-per-faskes` (stacked bar), `#chart-per-kecamatan` (stacked bar), `#chart-per-kelurahan` (stacked bar); setiap canvas dalam card dengan skeleton
- [x] T017 [US2] Tambahkan fungsi `renderTren(data)` ke blok script — buat/update 5 Chart.js instance menggunakan `destroyCharts()` sebelum re-render; gunakan palet warna konsisten per faskes/kecamatan/kelurahan

**Checkpoint**: Tab Tren menampilkan 5 grafik. Kurva epiweek cocok dengan distribusi data `tanggal_onset` di DB.

---

## Phase 5: User Story 3 – Demografi Kasus (Priority: P2)

**Goal**: Pengguna dapat melihat distribusi kelompok umur (8+1 bucket), status vaksinasi (4 kategori), dan panel severity (rawat inap, 8 komplikasi, kematian).

**Independent Test**: Buka tab Demografi, verifikasi bar chart kelompok umur menampilkan 9 bucket dengan nilai sesuai kalkulasi `TIMESTAMPDIFF(MONTH, tanggal_lahir, tanggal_onset)`.

- [x] T018 [US3] Tambahkan method `getPd3iDemografi(int $tahun, ?int $jenisKasusId, ?string $wilker): array` ke `app/Repositories/Admin/Epidemiologi/SurveillanceRepository.php` — 3 query: (a) kelompok_umur via CASE TIMESTAMPDIFF 9 bucket, GROUP BY kelompok + status_kasus; (b) status_vaksinasi: COUNT per enum riwayat_imunisasi; (c) severity: pct_rawat_inap + SUM 8 kolom komplikasi boolean + COUNT meninggal
- [x] T019 [P] [US3] Implementasi method `demografi(Request $request): JsonResponse` di `app/Http/Controllers/Pd3iDashboardController.php`
- [x] T020 [US3] Tambahkan HTML ke tab `#tab-demografi` di `resources/views/admin/epidemiologi/pd3i-dashboard.blade.php` — canvas `#chart-kelompok-umur` (bar grouped 3 kolom: suspek/confirmed/discarded), canvas `#chart-status-vaksinasi` (pie 4 slice), panel severity: card % rawat inap + horizontal bar `#chart-komplikasi` (8 item) + card case fatality
- [x] T021 [US3] Tambahkan fungsi `renderDemografi(data)` ke blok script — 3 Chart.js instance (bar umur, pie vaksinasi, horizontal bar komplikasi) + populate severity cards

**Checkpoint**: Tab Demografi menampilkan 3 chart dan 2 severity card. Total kasus di pie vaksinasi sama dengan total di bar kelompok umur (untuk filter sama).

---

## Phase 6: User Story 4 – Distribusi Wilayah (Priority: P3)

**Goal**: Pengguna dapat melihat tabel agregasi kasus per Puskesmas, kecamatan, kelurahan, dan peta marker Leaflet.

**Independent Test**: Buka tab Wilayah, verifikasi tabel per Puskesmas menampilkan semua wilker yang ada di `surveillance_cases` dengan angka suspek/confirmed/meninggal yang benar.

- [x] T022 [US4] Tambahkan method `getPd3iWilayah(int $tahun, ?int $jenisKasusId, ?string $wilker): array` ke `app/Repositories/Admin/Epidemiologi/SurveillanceRepository.php` — 4 query: (a) per_puskesmas: GROUP BY `wilker_puskesmas`; (b) per_kecamatan: JOIN `kecamatan` GROUP BY kecamatan; (c) per_kelurahan: JOIN `kelurahan`+`kecamatan` GROUP BY kelurahan; (d) peta: SELECT id/latitude/longitude/nama_lengkap(disamarkan)/jenis_kasus/status_kasus WHERE lat IS NOT NULL
- [x] T023 [P] [US4] Implementasi method `wilayah(Request $request): JsonResponse` di `app/Http/Controllers/Pd3iDashboardController.php`
- [x] T024 [US4] Tambahkan HTML ke tab `#tab-wilayah` di `resources/views/admin/epidemiologi/pd3i-dashboard.blade.php` — 3 tabel HTML responsive (per_puskesmas, per_kecamatan, per_kelurahan) dengan `<tbody id="...">` kosong + div `id="map-wilayah"` dengan height 400px untuk Leaflet
- [x] T025 [US4] Tambahkan fungsi `renderWilayah(data)` ke blok script — populate 3 tabel dari JSON; inisialisasi Leaflet map (atau clear layer jika sudah ada) dengan tile OpenStreetMap + marker per item `peta`; marker popup menampilkan penyakit + status kasus

**Checkpoint**: Tab Wilayah menampilkan 3 tabel berisi data dan peta Bontang dengan marker kasus yang memiliki koordinat GPS.

---

## Phase 7: Polish & Export PDF

**Purpose**: Export PDF, edge cases, navigation, dan penyempurnaan lintas tab.

- [x] T026 Implementasi method `exportPdf(Request $request): Response` di `app/Http/Controllers/Pd3iDashboardController.php` — panggil `parsePd3iFilters`, jalankan 4 metode repository sekaligus, pass data ke view `pdf.pd3i-dashboard`, generate PDF via `Pdf::loadView(...)->setPaper('a4', 'landscape')->download("pd3i-dashboard-{$tahun}.pdf")`
- [x] T027 [P] Isi konten `resources/views/admin/epidemiologi/pdf/pd3i-dashboard.blade.php` — Section 1 (Kinerja: 4 tabel per panel), Section 2 (Demografi: tabel kelompok umur + tabel vaksinasi + tabel komplikasi), Section 3 (Tren: tabel bulanan per faskes 12 kolom), Section 4 (Wilayah: tabel per puskesmas + per kecamatan + per kelurahan); footer dengan filter aktif + tanggal generate; semua CSS inline untuk DomPDF
- [x] T028 [P] Hubungkan tombol "Export PDF" di header `resources/views/admin/epidemiologi/pd3i-dashboard.blade.php` ke form POST `/admin/epidemiologi/pd3i-dashboard/export-pdf` yang menyertakan nilai filter aktif sebagai hidden inputs
- [x] T029 [P] Tambahkan link menu "Dashboard PD3I" ke sidebar navigasi admin (cari file layout nav yang relevan, tambahkan route `admin.pd3i.dashboard`) dengan icon yang sesuai
- [x] T030 Tambahkan penanganan error dan empty state ke `resources/views/admin/epidemiologi/pd3i-dashboard.blade.php` — jika API call gagal tampilkan alert per tab; jika data kosong tampilkan pesan "Tidak ada data untuk filter yang dipilih" di setiap komponen (scorecard = 0, chart = empty, tabel = baris kosong)

**Checkpoint**: Export PDF berhasil di-download berisi data 4 tab. Menu sidebar dapat diklik dan mengarah ke dashboard.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: Tidak ada dependensi — mulai langsung
- **Phase 2 (Foundational)**: Depends on Phase 1 — **BLOKIR semua user story**
- **Phase 3–6 (User Stories)**: Semua depends on Phase 2; dapat dikerjakan sequensial P1 → P2 → P3
- **Phase 7 (Polish)**: Depends on semua user story yang diinginkan selesai

### User Story Dependencies

- **US1 (P1)**: Mulai setelah Phase 2 — tidak depends pada US lain
- **US2 (P2)**: Mulai setelah Phase 2 — tidak depends pada US lain
- **US3 (P2)**: Mulai setelah Phase 2 — tidak depends pada US lain
- **US4 (P3)**: Mulai setelah Phase 2 — tidak depends pada US lain

### Dalam Setiap User Story

```
Repository method → Controller method → View HTML → JS render function
(T0x0)             (T0x1 [P])          (T0x2)       (T0x3)
```

Repository dan Controller method dapat dikerjakan paralel (file berbeda).

### Parallel Opportunities

```bash
# Phase 1 (parallel):
T001 routes/web.php  ||  T002 Pd3iDashboardController.php
T003 pd3i-dashboard.blade.php (setelah T001)  ||  T004 pdf/pd3i-dashboard.blade.php

# Phase 2 (partial parallel):
T005 Repository pd3iBaseQuery  ||  T006 Controller parsePd3iFilters
T007 JS fetchAllTabs           →   T008 JS filter listeners (sequential, same file)

# Per US (partial parallel):
T0x0 Repository method  ||  T0x1 Controller method
T0x2 View HTML          →   T0x3 JS render function (sequential, same file)

# Phase 7 (parallel):
T026 exportPdf() method  ||  T027 PDF Blade template
T028 Export button       ||  T029 Nav link
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Selesaikan Phase 1: Setup
2. Selesaikan Phase 2: Foundational (**CRITICAL**)
3. Selesaikan Phase 3: US1 – Kinerja Surveilans
4. **STOP dan VALIDASI**: Tab Kinerja menampilkan data benar, filter functional
5. Demo ke stakeholder

### Incremental Delivery

1. Phase 1 + 2 → Foundation siap
2. + Phase 3 (US1) → Tab Kinerja functional → **Demo MVP**
3. + Phase 4 (US2) → Tab Tren functional
4. + Phase 5 (US3) → Tab Demografi functional
5. + Phase 6 (US4) → Tab Wilayah + Peta functional
6. + Phase 7 → Export PDF + nav + polish → **Production ready**

---

## Notes

- [P] = file berbeda, tidak ada blocking dependency
- [Story] = traceability ke user story di spec.md
- Setiap user story dapat diimplementasi dan ditest secara independen
- Commit setelah setiap task atau checkpoint
- Dashboard bersifat **read-only** — tidak ada migration baru diperlukan
- DomPDF tidak support canvas/JS — PDF menggunakan tabel HTML, bukan screenshot grafik
- Leaflet map: gunakan tile `https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png` (sama dengan `map.blade.php` yang sudah ada)
- Nama pasien di peta disamarkan (hanya inisial) untuk privasi

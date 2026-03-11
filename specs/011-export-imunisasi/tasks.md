# Tasks: Export Data Imunisasi Anak

**Input**: Design documents from `/specs/011-export-imunisasi/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/export-routes.md

**Tests**: Tidak diminta secara eksplisit di spec. Test tasks tidak disertakan.

**Organization**: Tasks dikelompokkan per user story untuk implementasi dan testing independen.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Bisa dijalankan paralel (file berbeda, tanpa dependensi)
- **[Story]**: User story terkait (US1, US2, US3)
- Path mengacu ke root repository

## Phase 1: Setup

**Purpose**: Routing dan navigasi dasar

- [X] T001 Daftarkan 3 route export imunisasi (index, getData, download) di routes/web.php
- [X] T002 Tambahkan section group "Export Data" dengan menu "Export Imunisasi" di sidebar untuk super-admin dan legacy admin di resources/views/vendor/admin/layouts/partials/leftsidebar.blade.php
- [X] T003 Tambahkan active state detection untuk route export di sidebar resources/views/vendor/admin/layouts/partials/leftsidebar.blade.php

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Export class dan controller dasar yang dibutuhkan semua user story

- [X] T004 Buat ImunisasiExport class (FromQuery, WithMapping, WithHeadings, ShouldAutoSize) dengan query filter opsional (bulan, kelurahan, antigen, status) di app/Exports/ImunisasiExport.php
- [X] T005 Buat ExportImunisasiController dengan method index() yang return view dengan data dropdown (kelurahan, jenis vaksin aktif) di app/Http/Controllers/ExportImunisasiController.php

**Checkpoint**: Route terdaftar, sidebar ada menu Export Data, controller dan export class siap

---

## Phase 3: User Story 1 - Export Data Imunisasi dengan Filter (Priority: P1) — MVP

**Goal**: Admin bisa mengekspor data imunisasi ke CSV dengan filter bulan, kelurahan, antigen, dan status

**Independent Test**: Buka halaman export → pilih filter → klik Export → verifikasi file CSV berisi data sesuai filter

### Implementation

- [X] T006 [US1] Buat halaman export dengan form filter (dropdown bulan/tahun, kelurahan, antigen, status) dan tombol Export CSV di resources/views/admin/export/imunisasi.blade.php
- [X] T007 [US1] Implementasi method download() di ExportImunisasiController yang memanggil ImunisasiExport dengan filter dari query params dan return CSV download di app/Http/Controllers/ExportImunisasiController.php
- [X] T008 [US1] Implementasi dynamic filename di ImunisasiExport yang mencerminkan filter aktif (contoh: imunisasi_jan-2026_bontang-lestari_bcg.csv) di app/Exports/ImunisasiExport.php
- [X] T009 [US1] Tambahkan UTF-8 BOM dan format tanggal DD/MM/YYYY pada output CSV di app/Exports/ImunisasiExport.php
- [X] T010 [US1] Nonaktifkan tombol Export jika tidak ada filter bulan dipilih atau data kosong di resources/views/admin/export/imunisasi.blade.php

**Checkpoint**: User Story 1 selesai — admin bisa filter dan download CSV. Ini adalah MVP.

---

## Phase 4: User Story 2 - Preview Data Sebelum Export (Priority: P2)

**Goal**: Admin bisa melihat preview data di tabel sebelum mengekspor

**Independent Test**: Pilih filter → tabel preview menampilkan data sesuai filter dengan total record

### Implementation

- [X] T011 [US2] Implementasi method getData() di ExportImunisasiController yang return DataTables server-side JSON dengan filter query params di app/Http/Controllers/ExportImunisasiController.php
- [X] T012 [US2] Tambahkan DataTables preview table di halaman export yang load data via AJAX ke endpoint getData di resources/views/admin/export/imunisasi.blade.php
- [X] T013 [US2] Tambahkan auto-reload DataTables saat filter berubah dan tampilkan total record info di resources/views/admin/export/imunisasi.blade.php
- [X] T014 [US2] Tampilkan pesan "Tidak ada data yang sesuai filter" dan nonaktifkan tombol export saat DataTables kosong di resources/views/admin/export/imunisasi.blade.php

**Checkpoint**: User Story 1 + 2 selesai — admin bisa preview data lalu export

---

## Phase 5: User Story 3 - Informasi Ringkasan Filter (Priority: P3)

**Goal**: Admin melihat badge/tag ringkasan filter aktif di atas tabel preview

**Independent Test**: Pilih filter → badge ringkasan muncul menunjukkan filter yang dipilih

### Implementation

- [X] T015 [US3] Tambahkan container ringkasan filter di atas tabel preview yang menampilkan badge untuk setiap filter aktif (Bulan, Kelurahan, Antigen, Status) di resources/views/admin/export/imunisasi.blade.php
- [X] T016 [US3] Update badge ringkasan secara dinamis saat filter berubah via JavaScript di resources/views/admin/export/imunisasi.blade.php

**Checkpoint**: Semua user story selesai — fitur export lengkap

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Perbaikan yang mempengaruhi semua user story

- [X] T017 Pastikan halaman export menggunakan style yang konsisten dengan dashboard epidemiologi (shared-styles variables) di resources/views/admin/export/imunisasi.blade.php
- [X] T018 Verifikasi akses halaman export hanya untuk super-admin dan legacy admin (bukan faskes surveilans) di app/Http/Controllers/ExportImunisasiController.php
- [X] T019 Validasi file CSV bisa dibuka tanpa error di Excel dan Google Sheets (UTF-8 BOM, encoding, separator)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: Tidak ada dependensi — mulai langsung
- **Foundational (Phase 2)**: Tergantung Phase 1 selesai — BLOCK semua user story
- **User Stories (Phase 3-5)**: Tergantung Phase 2 selesai
  - US1 (Phase 3): Bisa mulai setelah Phase 2
  - US2 (Phase 4): Tergantung US1 (butuh halaman view dari T006)
  - US3 (Phase 5): Tergantung US2 (badge di atas tabel preview)
- **Polish (Phase 6)**: Tergantung semua user story selesai

### User Story Dependencies

- **US1 (P1)**: Independen setelah Phase 2 — bisa di-deliver sebagai MVP
- **US2 (P2)**: Butuh view dari US1 (T006) untuk menambah DataTables
- **US3 (P3)**: Butuh tabel preview dari US2 untuk menambah badge di atasnya

### Parallel Opportunities

- T001, T002, T003 bisa paralel (file berbeda)
- T004, T005 bisa paralel (file berbeda)
- T008, T009 bisa paralel (section berbeda di file yang sama, tapi aman)

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup (route + sidebar)
2. Complete Phase 2: Foundational (export class + controller)
3. Complete Phase 3: User Story 1 (filter form + download)
4. **STOP dan VALIDASI**: Test export CSV dengan berbagai kombinasi filter
5. Deploy jika siap

### Incremental Delivery

1. Setup + Foundational → infrastruktur siap
2. User Story 1 → export CSV berfungsi → **MVP**
3. User Story 2 → preview data sebelum export
4. User Story 3 → badge ringkasan filter
5. Polish → konsistensi style, validasi akses

---

## Notes

- Tidak ada migration baru — semua tabel sudah ada
- Semua filter single-select dropdown
- CSV harus UTF-8 + BOM untuk kompatibilitas Excel
- Format tanggal: DD/MM/YYYY
- Filename dinamis berdasarkan filter aktif
- Section sidebar baru "Export Data" sesuai permintaan user

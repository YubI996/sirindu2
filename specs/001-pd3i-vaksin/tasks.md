# Tasks: Peningkatan Modul Surveilans PD3I & Kelompok Vaksin

**Input**: Design documents from `/specs/001-pd3i-vaksin/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md

**Tests**: Not explicitly requested in spec. Test tasks omitted.

**Organization**: Tasks grouped by user story for independent implementation and testing.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

---

## Phase 1: Setup

**Purpose**: Install new dependency and prepare project

- [x] T001 Install barryvdh/laravel-dompdf package via `composer require barryvdh/laravel-dompdf`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Database tables, models, and seeders that multiple user stories depend on

**CRITICAL**: No user story work can begin until this phase is complete

- [x] T002 [P] Create migration for `kelompok_vaksin` table in `database/migrations/2026_04_01_000001_create_kelompok_vaksin_table.php` with fields: id, kode (string 10, unique), nama (string 100), usia_pemberian_min (int, nullable), usia_pemberian_max (int, nullable), batas_usia_kejar (int, nullable), keterangan (text, nullable), timestamps. See data-model.md for details
- [x] T003 [P] Create migration for `epid_counter` table in `database/migrations/2026_04_01_000002_create_epid_counter_table.php` with fields: id, tahun (int, unique), last_sequence (int, default 0), timestamps. See data-model.md for details
- [x] T004 [P] Create migration for `lokasi_penularan_master` table in `database/migrations/2026_04_01_000003_create_lokasi_penularan_master_table.php` with fields: id, nama (string 255), kategori (enum: Sekolah, Tempat Kerja, Gym, Tempat Ibadah, Lainnya), is_custom (boolean, default false), timestamps. See data-model.md for details
- [x] T005 [P] Create migration to add `id_kelompok_vaksin` FK to `jenis_vaksin` table in `database/migrations/2026_04_01_000004_add_kelompok_to_jenis_vaksin_table.php`. Add nullable foreign key referencing `kelompok_vaksin.id`
- [x] T006 [P] Create KelompokVaksin model in `app/Models/KelompokVaksin.php` with fillable fields, hasMany relationship to JenisVaksin
- [x] T007 [P] Create EpidCounter model in `app/Models/EpidCounter.php` with fillable fields and method `getNextSequence(int $tahun): int` using `lockForUpdate()` transaction
- [x] T008 [P] Create LokasiPenularanMaster model in `app/Models/LokasiPenularanMaster.php` with fillable fields, enum cast for kategori
- [x] T009 Update JenisVaksin model in `app/Models/JenisVaksin.php`: add `id_kelompok_vaksin` to fillable, add `belongsTo(KelompokVaksin)` relationship
- [x] T010 Run migrations via `php artisan migrate`
- [x] T011 [P] Create KelompokVaksinSeeder in `database/seeders/KelompokVaksinSeeder.php` to seed 3 rows: IDL (usia 0-11, kejar 60), IBL (usia 12-23, kejar 60), ISL (usia 84-144, kejar null). See data-model.md seed data
- [x] T012 [P] Create LokasiPenularanSeeder in `database/seeders/LokasiPenularanSeeder.php` reading 160 schools from `docs/list sekolah di bontang.txt`, all with kategori='Sekolah', is_custom=false
- [x] T013 [P] Create UpdateJenisVaksinKelompokSeeder in `database/seeders/UpdateJenisVaksinKelompokSeeder.php`: (1) assign existing 11 vaccines to IDL kelompok, (2) add 15 new vaccine records (IPV2, PCV1-2, PCV3, RV1-3, DPT-HB-HIB4, MR2, MR3, DT, TD1, TD2, HPV1, HPV2) with correct kelompok assignments. See research.md R-001 and data-model.md for full mapping
- [x] T014 Run seeders via `php artisan db:seed --class=KelompokVaksinSeeder && php artisan db:seed --class=LokasiPenularanSeeder && php artisan db:seed --class=UpdateJenisVaksinKelompokSeeder`

**Checkpoint**: All new tables, models, and seed data ready. User story implementation can begin.

---

## Phase 3: User Story 1 - Auto-Generate Nomor Epidemiologi (Priority: P1) MVP

**Goal**: Sistem auto-generate nomor epid saat submit kasus baru dengan format `[kode penyakit]-1710[YY][NNN]`, urutan global per tahun, dengan database locking.

**Independent Test**: Buat beberapa kasus baru untuk penyakit berbeda, verifikasi format dan urutan nomor epid.

### Implementation for User Story 1

- [x] T015 [US1] Add nomor epid generation logic in `app/Repositories/Admin/Epidemiologi/SurveillanceRepository.php`: create private method `generateNoRegistrasi(int $idJenisKasus): string` that (1) looks up kode_penyakit from JenisKasusEpidemiologi, (2) maps to prefix (C/D/P/TN/empty), (3) gets next sequence from EpidCounter using lockForUpdate transaction, (4) formats as `[prefix]-1710[YY][NNN]` or `1710[YY][NNN]` for AFP/Polio. Call this in `storeCase()` before insert. See research.md R-002 for mapping
- [x] T016 [US1] Ensure `kode_penyakit` field exists and is populated in JenisKasusEpidemiologi records. Check `app/Models/JenisKasusEpidemiologi.php` and verify seeder/migration has kode_penyakit for each PD3I disease (C, D, P, empty for AFP, TN)
- [x] T017 [US1] Update form view `resources/views/admin/epidemiologi/components/form-section-a.blade.php`: make no_registrasi field readonly with placeholder text "Otomatis di-generate saat simpan". On create form, hide value; on edit form, show existing value as readonly
- [x] T018 [US1] Update `app/Http/Requests/Epidemiologi/StoreSurveillanceCaseRequest.php`: remove no_registrasi from validation rules (it's now auto-generated, not user-submitted)

**Checkpoint**: Nomor epid auto-generated on case creation with correct format and unique sequence.

---

## Phase 4: User Story 2 - Kelompok Vaksin IDL/IBL/ISL (Priority: P1)

**Goal**: Setiap vaksin tergabung ke kelompok IDL/IBL/ISL, dan setiap anak memiliki status kelengkapan per kelompok yang terhitung otomatis.

**Independent Test**: Lihat daftar vaksin (kelompok terlihat), lihat profil anak (status IDL/IBL/ISL terlihat).

### Implementation for User Story 2

- [x] T019 [US2] Add computed methods to Anak model in `app/Models/Anak.php`: (1) `statusKelengkapanVaksin(): array` returns ['IDL' => 'Lengkap'/'Belum Lengkap', 'IBL' => ..., 'ISL' => ...], (2) `detailKelengkapanVaksin(): array` returns per-group: required count, received count, missing vaccines list. Logic: query JenisVaksin by kelompok, compare with Imunisasi where status='sudah'. For ISL+HPV: exclude HPV1/HPV2 if anak is male (jk field). See data-model.md computed properties
- [x] T020 [US2] Update master vaksin view to show kelompok column in `resources/views/admin/master-data/vaksin/index.blade.php`: add "Kelompok" column displaying kelompokVaksin->kode (IDL/IBL/ISL) with badge styling
- [x] T021 [US2] Update MasterDataVaksinController in `app/Http/Controllers/MasterDataVaksinController.php`: eager-load kelompokVaksin relationship in index/DataTables, add kelompok filter if applicable
- [x] T022 [US2] Display vaccination completeness status in child profile view. Find the child detail/show view (likely in `resources/views/admin/anak/` or rendered by AdminController) and add a section showing IDL/IBL/ISL status badges (Lengkap=green, Belum Lengkap=red) with detail of missing vaccines per group

**Checkpoint**: Kelompok vaksin visible in master data, child profiles show IDL/IBL/ISL completeness status.

---

## Phase 5: User Story 3 - Perubahan Input Alamat Kasus PD3I (Priority: P2)

**Goal**: Alamat KTP diisi manual (dropdown), koordinat peta terpisah. Dashboard menggunakan koordinat untuk statistik geografis.

**Independent Test**: Isi form kasus baru, pilih alamat KTP manual, klik peta untuk koordinat, verifikasi keduanya independen.

### Implementation for User Story 3

- [x] T023 [US3] Modify map picker in `resources/views/admin/epidemiologi/components/form-map-picker.blade.php`: remove the JavaScript code that auto-fills kecamatan/kelurahan/RT dropdowns on map click. Keep only the latitude/longitude field population. Remove the point-in-polygon reverse geocoding that sets address dropdowns. Keep marker placement and coordinate display functionality
- [x] T024 [US3] Verify `resources/views/admin/epidemiologi/components/form-section-a.blade.php`: ensure kecamatan/kelurahan/RT dropdowns remain as manual cascading selects (AJAX-based) without any map-triggered autofill. The existing cascading AJAX endpoints (`get-kelurahan/{id_kec}`, `get-rt/{id_kel}`) should continue working
- [x] T025 [US3] Verify dashboard geographic calculations in `app/Repositories/Admin/Epidemiologi/SurveillanceRepository.php` method `getCasesByGeography()`: confirm it uses latitude/longitude coordinates (not id_kec/id_kel address fields) for geographic distribution. If it currently uses address fields, update to use coordinate-based grouping

**Checkpoint**: Map click only sets coordinates; address filled manually; dashboard stats use coordinates.

---

## Phase 6: User Story 4 - Status Kejar Vaksin & Prioritas Intervensi (Priority: P2)

**Goal**: Anak yang melewati usia pemberian IDL/IBL tapi belum lengkap mendapat status "Kejar" dan poin risk_score tambahan di Early Warning System.

**Depends on**: US2 (kelompok vaksin + status kelengkapan must be implemented first)

**Independent Test**: Buat data anak usia >11 bulan dengan IDL belum lengkap, verifikasi status kejar muncul dan risk_score bertambah di EWS.

### Implementation for User Story 4

- [x] T026 [US4] Add kejar status methods to Anak model in `app/Models/Anak.php`: (1) `statusKejarVaksin(): array` returns ['kejar_idl' => bool, 'kejar_ibl' => bool] based on age and completeness status. kejar_idl = age >11 months AND <=60 months AND IDL belum lengkap. kejar_ibl = age >23 months AND <=60 months AND IBL belum lengkap. See data-model.md computed properties
- [x] T027 [US4] Update Early Warning System scoring in `app/Http/Controllers/AdminController.php` method `earlyWarningSystem()`: after existing vaccine scoring block (~line 1950-1970), add kejar vaksin scoring. Load kelompok vaksin data, compute kejar status per child, add +15 for kejar_idl, +10 for kejar_ibl. Add alert messages: "Kejar IDL - imunisasi dasar belum lengkap" and "Kejar IBL - imunisasi booster belum lengkap". See research.md R-005
- [x] T028 [US4] Update Early Warning view `resources/views/admin/dashboard/early-warning.blade.php`: ensure kejar vaksin alerts display with appropriate icon and styling alongside existing alerts (stunting, wasting, etc.)
- [x] T029 [US4] Display kejar status in child profile view (same view updated in T022): add kejar badges (Kejar IDL / Kejar IBL) with warning color below the completeness status section

**Checkpoint**: Children with catch-up status visible in EWS with increased risk scores and in child profiles.

---

## Phase 7: User Story 5 - Chart Kasus di Tempat Umum (Priority: P2)

**Goal**: Dashboard PD3I menampilkan chart distribusi kasus berdasarkan lokasi penularan di fasilitas umum, dengan dropdown searchable untuk input lokasi.

**Independent Test**: Input beberapa kasus dengan lokasi penularan berbeda, verifikasi chart tampil di dashboard.

### Implementation for User Story 5

- [x] T030 [US5] Add AJAX endpoint for lokasi penularan dropdown in `app/Http/Controllers/EpidemiologiController.php`: create method `getLokasiPenularan(Request $request)` returning JSON list from LokasiPenularanMaster, searchable by nama, grouped by kategori. Add route in `routes/web.php`
- [x] T031 [US5] Add endpoint to store custom lokasi in `app/Http/Controllers/EpidemiologiController.php`: create method `storeLokasiPenularan(Request $request)` that creates new LokasiPenularanMaster with is_custom=true and user-specified kategori. Add route in `routes/web.php`
- [x] T032 [US5] Update form view `resources/views/admin/epidemiologi/components/form-section-c.blade.php`: replace lokasi_penularan text input with Select2 searchable dropdown populated via AJAX from T030 endpoint. Group options by kategori (Sekolah, Tempat Kerja, Gym, Tempat Ibadah, Lainnya). Add "Tambah Lokasi Baru" option that opens a modal/inline form to add custom location via T031 endpoint
- [x] T033 [US5] Add dashboard data method in `app/Repositories/Admin/Epidemiologi/SurveillanceRepository.php`: create method `getCasesByFacilityType(?array $faskesScope, ?int $diseaseId): Collection` that groups surveillance_cases by lokasi_penularan, matching against lokasi_penularan_master to get kategori. Return counts per category. Handle unmatched old data as "Lainnya"
- [x] T034 [US5] Add facility chart to dashboard in `resources/views/admin/epidemiologi/dashboard.blade.php`: add new Chart.js bar/pie chart titled "Distribusi Kasus Berdasarkan Lokasi Penularan di Fasilitas Umum" using data from T033. Show "Tidak ada data" when empty. Wire to existing disease filter AJAX
- [x] T035 [US5] Update `app/Http/Controllers/EpidemiologiController.php` method `getDashboardData()`: include facility type distribution data from new repository method (T033) in the AJAX response

**Checkpoint**: Lokasi penularan uses searchable dropdown, dashboard shows facility distribution chart.

---

## Phase 8: User Story 6 - Export PDF Formulir Investigasi Kasus (Priority: P3)

**Goal**: Generate PDF formulir investigasi MR-01 dari data kasus PD3I, template sama untuk semua penyakit dengan judul dinamis.

**Independent Test**: Buat kasus lengkap, klik export PDF, verifikasi layout sesuai desain MR-01 dengan logo Kemenkes.

### Implementation for User Story 6

- [x] T036 [US6] Create PDF Blade template in `resources/views/admin/epidemiologi/pdf/formulir-mr01.blade.php`: replicate the MR-01 form layout from `docs/Export formulir/form.png`. Include: header with Kemenkes logo (`docs/Export formulir/logo.png`) and dynamic title "FORM INVESTIGASI KASUS [nama penyakit]", sections for patient identity, clinical info (checkboxes for symptoms), complications, treatment history, immunization history, epidemiological info, laboratory data, final condition. Use inline CSS for dompdf compatibility. Accept `$case` (SurveillanceCase) and `$disease` (JenisKasusEpidemiologi) variables
- [x] T037 [US6] Copy Kemenkes logo to public path: copy `docs/Export formulir/logo.png` to `public/images/logo-kemenkes.png` for PDF access
- [x] T038 [US6] Add PDF export method in `app/Http/Controllers/EpidemiologiController.php`: create method `exportPdfMR01(int $id)` that loads SurveillanceCase with relationships, renders the Blade template via `Pdf::loadView()`, and returns PDF download with filename `MR01_[no_registrasi].pdf`. Add route `GET admin/epidemiologi/export-pdf-mr01/{id}` in `routes/web.php`
- [x] T039 [US6] Add "Export PDF" button to case detail/show view in `resources/views/admin/epidemiologi/show.blade.php`: add button linking to the PDF export route (T038)

**Checkpoint**: PDF export generates MR-01 form with all data sections and Kemenkes logo for any PD3I case.

---

## Phase 9: User Story 7 - Export Data Agregat Imunisasi (Priority: P3)

**Goal**: Export data agregat imunisasi per kelurahan dalam format Excel, difilter per bulan/tahun, dengan kolom per vaksin dan per kelompok IDL/IBL/ISL.

**Depends on**: US2 (kelompok vaksin data must exist)

**Independent Test**: Isi data imunisasi untuk beberapa anak di kelurahan berbeda, export agregat, verifikasi angka.

### Implementation for User Story 7

- [x] T040 [US7] Create AgregatImunisasiExport class in `app/Exports/AgregatImunisasiExport.php` using Maatwebsite/Excel: accept bulan and tahun parameters. Query imunisasi data grouped by kelurahan. Build multi-row header: Row 1 = merged headers (Kelurahan, each vaccine name, IDL, IBL, ISL), Row 2 = sub-headers (#L, %L, #P, %P, #Jml, %Jml) repeated per vaccine and per group. Data rows: per kelurahan, count male (L), female (P), total immunized for each vaccine and each group. Title row: "Data Agregat Imunisasi Bulan {Bulan} Tahun {Tahun}". See research.md R-006 and spec FR-027-030
- [x] T041 [US7] Add aggregate export endpoint in `app/Http/Controllers/ExportImunisasiController.php`: create method `downloadAgregat(Request $request)` that validates bulan/tahun params, creates AgregatImunisasiExport instance, returns Excel download. Add route `GET admin/export-imunisasi/download-agregat` in `routes/web.php`
- [x] T042 [US7] Update export imunisasi view `resources/views/admin/export-imunisasi/index.blade.php`: add "Export Agregat" section/tab with bulan (month dropdown) and tahun (year dropdown) filters and download button linking to T041 endpoint

**Checkpoint**: Aggregate immunization export generates correct Excel with per-kelurahan data broken by vaccine and group.

---

## Phase 10: Polish & Cross-Cutting Concerns

**Purpose**: Final cleanup and validation

- [x] T043 [P] Verify all new routes are registered correctly via `php artisan route:list`
- [x] T044 [P] Run `npm run build` to ensure frontend assets compile without errors
- [x] T045 Clear all caches via `php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan view:clear`
- [x] T046 Manual smoke test: create new PD3I case, verify nomor epid auto-generated, address/coordinates independent, lokasi dropdown works
- [x] T047 Manual smoke test: check child profile for IDL/IBL/ISL status and kejar status, verify EWS risk scores updated
- [x] T048 Manual smoke test: verify dashboard facility chart, PDF export, and aggregate immunization export

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - start immediately
- **Foundational (Phase 2)**: Depends on Phase 1 - BLOCKS all user stories
- **US1 (Phase 3)**: Depends on Phase 2 only - no dependencies on other stories
- **US2 (Phase 4)**: Depends on Phase 2 only - no dependencies on other stories
- **US3 (Phase 5)**: Depends on Phase 2 only - no dependencies on other stories
- **US4 (Phase 6)**: Depends on Phase 2 AND **US2 (Phase 4)** - needs kelompok vaksin + status kelengkapan
- **US5 (Phase 7)**: Depends on Phase 2 only - no dependencies on other stories
- **US6 (Phase 8)**: Depends on Phase 2 only - no dependencies on other stories (but benefits from US1 nomor epid)
- **US7 (Phase 9)**: Depends on Phase 2 AND **US2 (Phase 4)** - needs kelompok vaksin data
- **Polish (Phase 10)**: Depends on all user stories being complete

### User Story Dependencies

```
Phase 1: Setup
    │
Phase 2: Foundational
    │
    ├── US1 (P1): Nomor Epid ──────────────────────┐
    ├── US2 (P1): Kelompok Vaksin ──┬──────────────┤
    ├── US3 (P2): Input Alamat ─────┤              │
    ├── US5 (P2): Chart Tempat Umum ┤              │
    ├── US6 (P3): Export PDF ───────┤              │
    │                               │              │
    ├── US4 (P2): Kejar & EWS ──────┘ (needs US2)  │
    ├── US7 (P3): Export Agregat ───┘ (needs US2)   │
    │                                               │
Phase 10: Polish ───────────────────────────────────┘
```

### Parallel Opportunities

After Phase 2 completes, these can run in parallel:
- **Group A** (independent): US1, US3, US5, US6
- **Group B** (sequential): US2 → US4, US7

### Within Each User Story

- Models/repository before controllers
- Controllers before views
- Backend before frontend

---

## Parallel Example: After Foundational

```
# These 4 stories can start simultaneously:
US1: T015-T018 (Nomor Epid)
US2: T019-T022 (Kelompok Vaksin)
US3: T023-T025 (Input Alamat)
US5: T030-T035 (Chart Tempat Umum)

# After US2 completes, these can start:
US4: T026-T029 (Kejar & EWS)
US7: T040-T042 (Export Agregat)

# Independent of others:
US6: T036-T039 (Export PDF)
```

---

## Implementation Strategy

### MVP First (US1 + US2)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (CRITICAL - blocks all stories)
3. Complete Phase 3: US1 - Nomor Epid auto-generate
4. Complete Phase 4: US2 - Kelompok Vaksin + Status Kelengkapan
5. **STOP and VALIDATE**: Test nomor epid generation and kelompok vaksin independently
6. Deploy/demo if ready

### Incremental Delivery

1. Setup + Foundational → Foundation ready
2. US1 (Nomor Epid) → Test → Deploy (MVP core)
3. US2 (Kelompok Vaksin) → Test → Deploy (enables US4, US7)
4. US3 (Input Alamat) + US5 (Chart) → Test → Deploy (P2 features)
5. US4 (Kejar & EWS) → Test → Deploy (P2, depends on US2)
6. US6 (Export PDF) + US7 (Export Agregat) → Test → Deploy (P3 features)
7. Polish → Final validation

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- US4 and US7 MUST wait for US2 completion (kelompok vaksin dependency)
- Commit after each task or logical group
- Stop at any checkpoint to validate story independently

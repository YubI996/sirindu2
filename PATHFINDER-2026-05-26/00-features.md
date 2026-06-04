# Sirindu — Feature Inventory
Generated: 2026-05-26

## Feature Boundaries

### F01 · Child Health Management (Manajemen Data Dasar Anak)
- **Entry Points**: `AdminController@anak` (GET /admin/data-dasar-anak), `storeAnak`, `editAnak`, `updateAnak`, `destroyAnak`
- **Core Files**: `app/Models/Anak.php`, `app/Repositories/Admin/Anak/AnakRepository.php`, `app/Http/Requests/Admin/Anak/`, `resources/views/admin/anak/`
- **Purpose**: CRUD for child (Anak) basic demographic data including NIK, birth date, parents, geographic assignment (Kecamatan/Kelurahan/RT/Puskesmas/Posyandu).

### F02 · Periodic Child Measurements (Pengukuran Berkala)
- **Entry Points**: `AdminController@dataAnak` (GET /admin/data-anak/{id}), `storeDataAnak`, `updateDataAnak`, `chartAnak`
- **Core Files**: `app/Models/DataAnak.php`, `app/Helpers/helpers.php` (z_score() fn), `app/Jobs/ImportPengukuranJob.php`, `app/Imports/PengukuranImport.php`
- **Purpose**: Periodic height/weight/LILA measurements per child; Z-score nutritional status (IMT/U, BB/U, TB/U, BB/TB) against WHO reference data.

### F03 · Immunization Management (Manajemen Imunisasi)
- **Entry Points**: `AdminController@imunisasiLengkap`, `jadwalImunisasi`, `storeImunisasiDetail`, `updateImunisasiDetail`, `deleteImunisasiDetail`, `imunisasiDashboard`
- **Core Files**: `app/Models/Imunisasi.php`, `app/Models/JenisVaksin.php`, `app/Models/KelompokVaksin.php`, `app/Services/ImunisasiStatusService.php`, `app/Imports/ImunisasiImport.php`, `app/Exports/ImunisasiExport.php`
- **Purpose**: Record and track child immunization history by vaccine/group (IDL/IBL/ISL); catch-up schedule per KMK 1098/2024; vaccine completeness dashboard.

### F04 · Data Import (Queue-Based CSV/Excel)
- **Entry Points**: `ImportCsvController@index/uploadAnak/uploadPengukuran/uploadImunisasi/uploadHasilLab/importStatus` (routes under /admin/import-csv/), `AdminController@importKohort`
- **Core Files**: `app/Http/Controllers/ImportCsvController.php`, `app/Jobs/Import{Anak|Pengukuran|Imunisasi|HasilLab|Kohort}Job.php`, `app/Imports/*.php`, `app/Models/ImportLog.php`
- **Purpose**: Queue-based batch import of CSV/Excel for anak, measurements, immunizations, lab results, cohorts; ImportLog tracks status per batch.

### F05 · Data Export
- **Entry Points**: `AdminController@exportView/formViewExport/exportExcel/exportAllExcel/exportVaccineNeeds`, `ExportImunisasiController@index/download`
- **Core Files**: `app/Http/Controllers/ExportImunisasiController.php`, `app/Exports/AnakExport.php`, `AllExport.php`, `ImunisasiExport.php`, `VaccineNeedsExport.php`, `AgregatImunisasiExport.php`
- **Purpose**: Export child data and immunization records to Excel; filterable by date range, district, puskesmas, posyandu; includes aggregated and per-child views.

### F06 · Epidemiology Surveillance (Pencatatan Kasus PD3I)
- **Entry Points**: `EpidemiologiController@index/store/show/edit/update/destroy/dashboard/mapDashboard/importExcel/exportExcel`
- **Core Files**: `app/Http/Controllers/EpidemiologiController.php`, `app/Models/SurveillanceCase.php` + related models (Imunisasi/Spesimen/Faskesberobat/KontakErat), `app/Repositories/Admin/Epidemiologi/SurveillanceRepository.php`, `app/Jobs/ImportPd3iJob.php`, `app/Imports/Pd3iImport.php`, `app/Exports/SurveillanceExport.php`
- **Purpose**: Full lifecycle for notifiable disease (PD3I) cases — registration, symptoms, lab specimens, contact tracing, healthcare facilities, immunization history, photo docs, mr01 PDF export.

### F07 · PD3I Analytics Dashboard (Dinas Kesehatan)
- **Entry Points**: `Pd3iDashboardController@index/kinerja/demografi/tren/wilayah/exportPdf` (routes under /admin/epidemiologi/pd3i-dashboard/)
- **Core Files**: `app/Http/Controllers/Pd3iDashboardController.php`, queries via `SurveillanceRepository`, views at `resources/views/admin/epidemiologi/pd3i-dashboard/`
- **Purpose**: Super-admin analytics dashboard for disease surveillance: KPI performance, demographic distributions, trend analysis, regional breakdown; PDF export.

### F08 · Child Health Analytics & Map Dashboard
- **Entry Points**: `AdminController@analyticsDashboard/analyticsFilterImunisasi/mapDashboard/getMapData` (routes /admin/analytics, /admin/map)
- **Core Files**: `AdminController` (lines ~1136-1284, 1642-1731), Blade views with Chart.js/Leaflet
- **Purpose**: District-level analytics for child health metrics and immunization coverage; geographic map visualization by kecamatan/kelurahan.

### F09 · Early Warning System (Vaksin Shortfall & Catch-Up)
- **Entry Points**: `AdminController@earlyWarningSystem/exportVaccineNeeds` (routes /admin/early-warning)
- **Core Files**: `AdminController` (lines ~1759-2205), `app/Exports/VaccineNeedsExport.php`
- **Purpose**: Identify children with incomplete/overdue vaccinations; generate vaccine procurement forecast reports.

### F10 · Master Data Management (Vaksin, Penyakit, Penduduk)
- **Entry Points**: `MasterDataVaksinController`, `MasterDataPenyakitController`, `MasterDataPendudukController` (routes under /admin/master-data/)
- **Core Files**: `app/Http/Controllers/MasterData{Vaksin|Penyakit|Penduduk}Controller.php`, `app/Models/JenisVaksin.php`, `JenisKasusEpidemiologi.php`, `KelompokVaksin.php`, `JumlahPenduduk.php`
- **Purpose**: Maintain vaccine types (with soft delete/restore), disease categories, vaccine groups, and population statistics used as reference data across the system.

### F11 · User Management (Super Admin)
- **Entry Points**: `AdminController@user/storeUser/editUser/updateUser/destroyUser` (routes under /super-admin/)
- **Core Files**: `app/Models/User.php`, `app/Repositories/Admin/User/UserRepository.php`, `app/Http/Requests/Admin/User/storeUserRequest.php`, `resources/views/admin/user/`
- **Purpose**: Create/manage system users with role assignment (super-admin/admin) and geographic scope binding.

### F12 · Public & Internal API
- **Entry Points**: `ApiController@getKecApi/getPuskesmasApi/getKelApi/getRtApi/getPosyanduApi` (public), Sanctum-protected `/api/allDataAnak` etc.
- **Core Files**: `app/Http/Controllers/ApiController.php`, geographic models, `Anak.php`
- **Purpose**: Cascading geographic dropdown endpoints for forms (kecamatan→kelurahan→RT, puskesmas→posyandu); protected child data REST API.

---

## Supporting Infrastructure (not flowcharted separately)
- **Authentication**: Laravel Auth controllers, `IsAdmin`/`UserAccess` middleware, Breeze/built-in auth views
- **Geographic Hierarchy**: `Kecamatan`, `Kelurahan`, `Rt`, `Puskesmas`, `Posyandu`, `RumahSakit` models — cross-cutting data; accessed via F12 API and embedded in all forms

## Boundary Notes
- F04 (Import) and F06 (Epidemiology) both have their own import jobs/flows — duplication candidate
- F05 (Export) and F06 (Epidemiology export) both produce Excel — duplication candidate
- F08 (Analytics) and F07 (PD3I Dashboard) both aggregate data for visualization — duplication candidate
- F09 (Early Warning) is a sub-section of AdminController sharing analytics infrastructure with F08

# Research: Export Data Imunisasi Anak

**Feature**: 011-export-imunisasi
**Date**: 2026-03-10

## R1: Export Pattern

**Decision**: Gunakan Maatwebsite\Excel dengan pattern `FromQuery` + `WithMapping` + `WithHeadings` untuk export CSV.

**Rationale**: Package sudah terinstall dan dipakai di project (`AnakExport`, `VaccineNeedsExport`). Pattern `FromQuery` lebih efisien untuk dataset besar karena menggunakan query chunking dibanding `FromCollection` yang load semua ke memory.

**Alternatives considered**:
- `FromCollection` (seperti VaccineNeedsExport) — lebih fleksibel tapi kurang efisien untuk data besar
- FastExcel — sudah terinstall tapi kurang fitur mapping/heading
- Raw PHP fputcsv — terlalu low-level, kehilangan fitur auto-size dan encoding

## R2: Query Strategy

**Decision**: Query dari model `Imunisasi` dengan eager load relasi `anak.kel`, `anak.kec`, `anak.posyandu`, `jenisVaksin`.

**Rationale**: Model Imunisasi sudah punya semua relasi yang dibutuhkan. Eager loading mencegah N+1 query. Filter diterapkan via query builder chaining.

**Alternatives considered**:
- DB::table() join — lebih cepat tapi kehilangan accessor dan relationship, sulit maintenance
- Query dari Anak lalu filter imunisasi — arah relasi terbalik, lebih kompleks

## R3: Filter Implementation

**Decision**: Filter via GET parameters pada halaman preview, POST untuk export. Semua filter opsional.

**Rationale**: GET untuk preview memungkinkan bookmark/share URL. POST untuk export mencegah URL panjang dan cocok dengan pattern existing (`formViewExport`).

**Alternatives considered**:
- Semua GET — URL bisa terlalu panjang
- AJAX filter — lebih responsive tapi tambah kompleksitas, DataTables server-side sudah cukup

## R4: Preview Implementation

**Decision**: Gunakan Yajra DataTables server-side untuk preview data.

**Rationale**: Project sudah pakai DataTables di semua halaman data. Server-side processing menangani pagination dan large dataset otomatis.

**Alternatives considered**:
- Client-side table — tidak cocok untuk data besar
- Custom pagination — reinventing the wheel

## R5: CSV Format

**Decision**: UTF-8 dengan BOM (`\xEF\xBB\xBF`) untuk kompatibilitas Excel. Separator koma. Format tanggal DD/MM/YYYY.

**Rationale**: Excel membutuhkan BOM untuk mendeteksi UTF-8 encoding. Tanpa BOM, karakter Indonesia (nama daerah) bisa rusak di Excel.

**Alternatives considered**:
- UTF-8 tanpa BOM — Google Sheets ok, tapi Excel bisa gagal
- XLSX format — lebih berat, user minta CSV

## R6: Sidebar Placement

**Decision**: Buat section group baru "EXPORT DATA" di sidebar dengan menu "Export Imunisasi" di dalamnya. Ditampilkan untuk super-admin dan legacy admin (bukan faskes surveilans).

**Rationale**: User secara eksplisit meminta dropdown menu baru bernama "EXPORT DATA". Ini juga memisahkan fitur export dari section data operasional.

**Alternatives considered**:
- Tambah di section "Anak" — user sudah minta section terpisah
- Menu tanpa dropdown — kurang scalable jika nanti ada export lain

## R7: Controller Placement

**Decision**: Buat controller baru `ExportImunisasiController` terpisah dari `AdminController`.

**Rationale**: AdminController sudah 39K+ baris, menambah method di sana akan memperburuk maintainability. Pattern baru menggunakan controller dedicated sudah diterapkan di `MasterDataVaksinController`, `MasterDataPenyakitController`, dan `EpidemiologiController`.

**Alternatives considered**:
- Method di AdminController — terlalu besar, melanggar SRP
- Trait — menambah indirection tanpa manfaat jelas

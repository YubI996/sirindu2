# Route Contract: Export Imunisasi

**Feature**: 011-export-imunisasi
**Date**: 2026-03-10

## Routes

Semua route di bawah prefix `/admin/` dan middleware `IsAdmin`.

### Preview & Halaman Export

| Method | URI | Controller Method | Route Name | Description |
|--------|-----|-------------------|------------|-------------|
| GET | `/admin/export-imunisasi` | `ExportImunisasiController@index` | `admin.export.imunisasi.index` | Halaman filter + preview |
| GET | `/admin/export-imunisasi/get-data` | `ExportImunisasiController@getData` | `admin.export.imunisasi.getData` | DataTables server-side JSON |

### Download

| Method | URI | Controller Method | Route Name | Description |
|--------|-----|-------------------|------------|-------------|
| GET | `/admin/export-imunisasi/download` | `ExportImunisasiController@download` | `admin.export.imunisasi.download` | Download file CSV |

## Query Parameters (shared by getData & download)

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| bulan | string | No | Format: `YYYY-MM` (misal: `2026-01`) |
| kelurahan | integer | No | ID kelurahan |
| antigen | integer | No | ID jenis vaksin |
| status | string | No | Enum: `belum`, `sudah`, `terlambat` |

## Response

### getData (DataTables JSON)

```json
{
  "draw": 1,
  "recordsTotal": 150,
  "recordsFiltered": 42,
  "data": [
    {
      "nama_anak": "Budi Santoso",
      "nik": "6474...",
      "jenis_kelamin": "Laki-laki",
      "tanggal_lahir": "15/03/2025",
      "kelurahan": "Bontang Lestari",
      "kecamatan": "Bontang Selatan",
      "posyandu": "Melati",
      "jenis_vaksin": "BCG",
      "dosis": 1,
      "tanggal_pemberian": "20/01/2026",
      "status": "sudah",
      "lokasi_pemberian": "Puskesmas Bontang Selatan"
    }
  ]
}
```

### download (CSV File)

- Content-Type: `text/csv; charset=UTF-8`
- Content-Disposition: `attachment; filename="imunisasi_jan-2026_bontang-lestari_bcg.csv"`
- Encoding: UTF-8 with BOM
- Separator: Comma (`,`)

## Sidebar Entry

Section group baru **"Export Data"** di sidebar:
- Posisi: setelah section "PD3I", sebelum "Data Master"
- Visible untuk: super-admin dan legacy admin
- Hidden untuk: faskes surveilans
- Menu item: "Export Imunisasi" → `admin.export.imunisasi.index`

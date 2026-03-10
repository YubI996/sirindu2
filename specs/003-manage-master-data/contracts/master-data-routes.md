# Route Contracts: Master Data CRUD

**Date**: 2026-03-09 (updated post-clarification)

All routes require authentication (`auth` middleware) and superadmin role (`module.role:superadmin`).
All routes are prefixed with `/admin/`.

## Jenis Vaksin Routes

### GET /master-data/vaksin/
**Response**: HTML page (Blade view `admin.master-data.vaksin.index`)

### GET /master-data/vaksin/get-data
**Query Params**: DataTables server-side parameters (draw, start, length, search, order)
**Behavior**: Uses `withTrashed()` to include soft-deleted records.
**Response** (200):
```json
{
  "draw": 1,
  "recordsTotal": 10,
  "recordsFiltered": 10,
  "data": [
    {
      "kode": "BCG",
      "nama": "Bacillus Calmette-Guérin",
      "kategori": "Wajib",
      "usia_pemberian_min": 0,
      "usia_pemberian_max": 1,
      "interval_hari": null,
      "status_badge": "<span class='badge bg-success'>Aktif</span>",
      "action": "<button>...</button>",
      "deleted_at": null
    },
    {
      "kode": "OLD_VACCINE",
      "nama": "Deprecated Vaccine",
      "kategori": "Tambahan",
      "status_badge": "<span class='badge bg-danger'>Dihapus</span>",
      "action": "<button class='btn-restore'>Restore</button>",
      "deleted_at": "2026-03-09T10:00:00.000000Z"
    }
  ]
}
```

### POST /master-data/vaksin/store
**Request Body** (JSON/form-data):
```json
{
  "kode": "string (required, max:50, regex:/^[A-Za-z0-9_-]+$/, unique)",
  "nama": "string (required, max:255)",
  "kategori": "string (required, in:Wajib,Tambahan,Booster)",
  "usia_pemberian_min": "integer|null (min:0)",
  "usia_pemberian_max": "integer|null (min:0)",
  "interval_hari": "integer|null (min:0)",
  "keterangan": "string|null",
  "aktif": "boolean|null (default: true)"
}
```
**Response** (200): `{ "success": true, "message": "Jenis vaksin berhasil ditambahkan" }`
**Response** (422): `{ "message": "...", "errors": { "kode": ["..."] } }`

### PUT /master-data/vaksin/update/{id}
**Request Body**: Same as store (kode unique ignores self)
**Constraint**: Cannot update soft-deleted records (return 403/404).
**Response** (200): `{ "success": true, "message": "Jenis vaksin berhasil diperbarui" }`

### PATCH /master-data/vaksin/toggle-status/{id}
**Request Body**: None
**Constraint**: Cannot toggle soft-deleted records.
**Response** (200): `{ "success": true, "message": "Status vaksin berhasil diubah", "aktif": false }`

### DELETE /master-data/vaksin/destroy/{id}
**Request Body**: None
**Behavior**:
- If `imunisasi()->count() == 0` → hard-delete (forceDelete)
- If `imunisasi()->count() > 0` → soft-delete (set deleted_at)
**Response** (200, hard-delete): `{ "success": true, "message": "Jenis vaksin berhasil dihapus" }`
**Response** (200, soft-delete): `{ "success": true, "message": "Jenis vaksin di-nonaktifkan (soft-delete) karena masih digunakan oleh N data imunisasi", "soft_deleted": true }`

### PATCH /master-data/vaksin/restore/{id} (NEW)
**Request Body**: None
**Behavior**: Restores a soft-deleted record (clears `deleted_at`).
**Response** (200): `{ "success": true, "message": "Jenis vaksin berhasil di-restore" }`
**Response** (404): Record not found or not soft-deleted.

---

## Jenis Penyakit Routes

### GET /master-data/penyakit/
**Response**: HTML page (Blade view `admin.master-data.penyakit.index`)

### GET /master-data/penyakit/get-data
**Behavior**: Uses `withTrashed()` to include soft-deleted records.
**Response**: Same DataTables format with fields: kode_penyakit, nama_penyakit, kategori (badge), status_badge, action, deleted_at

### POST /master-data/penyakit/store
**Request Body**:
```json
{
  "kode_penyakit": "string (required, max:20, regex:/^[A-Za-z0-9_]+$/, unique)",
  "nama_penyakit": "string (required, max:255)",
  "kategori": "string (required, in:PD3I,menular_langsung,vector_borne,zoonosis,lainnya)",
  "deskripsi": "string|null",
  "is_active": "boolean|null (default: true)"
}
```
**Response** (200): `{ "success": true, "message": "Jenis penyakit berhasil ditambahkan" }`
**Response** (422): Validation errors

### PUT /master-data/penyakit/update/{id}
**Constraint**: Cannot update soft-deleted records.
**Response** (200): `{ "success": true, "message": "Jenis penyakit berhasil diperbarui" }`

### PATCH /master-data/penyakit/toggle-status/{id}
**Constraint**: Cannot toggle soft-deleted records.
**Response** (200): `{ "success": true, "message": "Status penyakit berhasil diubah", "is_active": false }`

### DELETE /master-data/penyakit/destroy/{id}
**Behavior**:
- If `surveillanceCases()->count() == 0` → hard-delete (forceDelete)
- If `surveillanceCases()->count() > 0` → soft-delete (set deleted_at)
**Response** (200, hard-delete): `{ "success": true, "message": "Jenis penyakit berhasil dihapus" }`
**Response** (200, soft-delete): `{ "success": true, "message": "Jenis penyakit di-nonaktifkan (soft-delete) karena masih digunakan oleh N data kasus", "soft_deleted": true }`

### PATCH /master-data/penyakit/restore/{id} (NEW)
**Request Body**: None
**Response** (200): `{ "success": true, "message": "Jenis penyakit berhasil di-restore" }`
**Response** (404): Record not found or not soft-deleted.

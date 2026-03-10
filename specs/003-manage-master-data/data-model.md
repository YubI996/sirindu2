# Data Model: Manajemen Master Data Imunisasi & Penyakit Surveilans

**Date**: 2026-03-09 (updated post-clarification)

## Entity: JenisVaksin

**Table**: `jenis_vaksin`
**Model**: `App\Models\JenisVaksin`
**Traits**: `SoftDeletes` (TO ADD)

### Fields

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | bigint unsigned | PK, auto-increment | Primary key |
| kode | varchar(50) | unique, not null, regex: `^[A-Za-z0-9_-]+$` | Unique vaccine code |
| nama | varchar(255) | not null | Vaccine name |
| kategori | enum('Wajib','Tambahan','Booster') | not null | Vaccine category (CHANGED from varchar) |
| usia_pemberian_min | int | nullable, min:0 | Minimum age for administration (months) |
| usia_pemberian_max | int | nullable, min:0 | Maximum age for administration (months) |
| interval_hari | int | nullable, min:0 | Days between doses |
| keterangan | text | nullable | Notes/description |
| aktif | boolean | default: true | Active status flag |
| created_at | timestamp | auto | Creation timestamp |
| updated_at | timestamp | auto | Last update timestamp |
| deleted_at | timestamp | nullable (NEW) | Soft-delete timestamp |

### Relationships

| Relation | Type | Target | FK | On Delete |
|----------|------|--------|----|-----------|
| imunisasi | hasMany | Imunisasi | id_jenis_vaksin | RESTRICT |

### Scopes

- `scopeAktif($query)` — filters to `aktif = true` AND `deleted_at IS NULL`

### State Transitions

```
                    ┌─────────────────────────────┐
                    │                             │
                    ▼                             │
Created ──► Active (aktif=true) ◄──► Inactive (aktif=false)
                    │                             │
                    ▼                             ▼
            Hard-Delete              Soft-Delete (deleted_at set)
         (if 0 children)           (if children > 0)
                                          │
                                          ▼
                                    Restored (deleted_at = null)
                                    → returns to Active or Inactive
```

**Delete Logic**:
1. Check `imunisasi()->count()`
2. If count == 0 → `forceDelete()` (permanent removal)
3. If count > 0 → `delete()` (sets `deleted_at`, record remains in DB)

**Restore Logic**:
- `JenisVaksin::onlyTrashed()->findOrFail($id)->restore()` → clears `deleted_at`

---

## Entity: JenisKasusEpidemiologi

**Table**: `jenis_kasus_epidemiologi`
**Model**: `App\Models\JenisKasusEpidemiologi`
**Traits**: `SoftDeletes` (TO ADD)

### Fields

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | bigint unsigned | PK, auto-increment | Primary key |
| kode_penyakit | varchar(20) | unique, not null, regex: `^[A-Za-z0-9_]+$` | Unique disease code |
| nama_penyakit | varchar(255) | not null | Disease name |
| kategori | enum | not null, values: PD3I, menular_langsung, vector_borne, zoonosis, lainnya | Disease classification |
| deskripsi | text | nullable | Description of the disease |
| is_active | boolean | default: true | Active status flag |
| created_at | timestamp | auto | Creation timestamp |
| updated_at | timestamp | auto | Last update timestamp |
| deleted_at | timestamp | nullable (NEW) | Soft-delete timestamp |

### Relationships

| Relation | Type | Target | FK | On Delete |
|----------|------|--------|----|-----------|
| surveillanceCases | hasMany | SurveillanceCase | id_jenis_kasus | RESTRICT |

### Scopes

- `scopeActive($query)` — filters to `is_active = true` AND `deleted_at IS NULL`
- `scopeByCategory($query, $category)` — filters by kategori value

### State Transitions

Same as JenisVaksin above, with `surveillanceCases()->count()` instead of `imunisasi()->count()`.

---

## Entity Relationship Diagram

```
┌──────────────────┐         ┌──────────────────┐
│   JenisVaksin    │         │    Imunisasi      │
├──────────────────┤    1:N  ├──────────────────┤
│ id (PK)          │◄────────│ id_jenis_vaksin  │
│ kode (unique)    │RESTRICT │ id_anak (FK)     │
│ nama             │         │ dosis            │
│ kategori (enum)  │         │ tanggal_pemberian│
│ usia_min/max     │         │ status           │
│ interval_hari    │         │ ...              │
│ aktif            │         └──────────────────┘
│ deleted_at (NEW) │
└──────────────────┘

┌──────────────────────────┐         ┌──────────────────┐
│ JenisKasusEpidemiologi   │         │ SurveillanceCase  │
├──────────────────────────┤    1:N  ├──────────────────┤
│ id (PK)                  │◄────────│ id_jenis_kasus   │
│ kode_penyakit (unique)   │RESTRICT │ patient_name     │
│ nama_penyakit            │         │ diagnosis_date   │
│ kategori (enum)          │         │ status           │
│ deskripsi                │         │ ...              │
│ is_active                │         └──────────────────┘
│ deleted_at (NEW)         │
└──────────────────────────┘
```

## Validation Rules Summary

### JenisVaksin (store)
- `kode`: required | max:50 | regex:/^[A-Za-z0-9_-]+$/ | unique:jenis_vaksin,kode
- `nama`: required | max:255
- `kategori`: required | in:Wajib,Tambahan,Booster
- `usia_pemberian_min`: nullable | integer | min:0
- `usia_pemberian_max`: nullable | integer | min:0
- `interval_hari`: nullable | integer | min:0
- `keterangan`: nullable | string
- `aktif`: nullable | boolean (defaults true)

### JenisVaksin (update)
- Same as store, except `kode` unique ignores self: `unique:jenis_vaksin,kode,{id}`

### JenisKasusEpidemiologi (store)
- `kode_penyakit`: required | max:20 | regex:/^[A-Za-z0-9_]+$/ | unique:jenis_kasus_epidemiologi,kode_penyakit
- `nama_penyakit`: required | max:255
- `kategori`: required | in:PD3I,menular_langsung,vector_borne,zoonosis,lainnya
- `deskripsi`: nullable | string
- `is_active`: nullable | boolean (defaults true)

### JenisKasusEpidemiologi (update)
- Same as store, except `kode_penyakit` unique ignores self: `unique:jenis_kasus_epidemiologi,kode_penyakit,{id}`

## Required Migrations

### Migration 1: Add SoftDeletes to master data tables
```
2026_03_09_000001_add_soft_deletes_to_master_data_tables.php
- jenis_vaksin: add deleted_at (nullable timestamp)
- jenis_kasus_epidemiologi: add deleted_at (nullable timestamp)
```

### Migration 2: Change jenis_vaksin kategori to enum
```
2026_03_09_000002_change_jenis_vaksin_kategori_to_enum.php
- Data migration: map existing values → closest enum
- Column change: varchar(100) → enum('Wajib','Tambahan','Booster')
```

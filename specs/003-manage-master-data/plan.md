# Implementation Plan: Manajemen Master Data Imunisasi & Penyakit Surveilans

**Branch**: `003-manage-master-data` | **Date**: 2026-03-09 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/003-manage-master-data/spec.md`

## Summary

Provide CRUD management pages for two master data entities — **Jenis Vaksin** (immunization types) and **Jenis Penyakit Epidemiologi** (surveillance disease types) — accessible only to Super Admin (Dinkes) via contextual sidebar navigation. Implementation uses Laravel 9 with server-side DataTables, Bootstrap 5 modals, and AJAX-based form handling.

**Current Status**: Base CRUD (models, controllers, views, routes, tests, sidebar) already exists. Key gaps remain from clarification updates:
1. **SoftDeletes** — neither model supports it; no `deleted_at` columns exist
2. **Hybrid deletion** — controllers reject deletion instead of soft-deleting when children exist
3. **Restore** — no route, controller method, or UI button for restoring soft-deleted records
4. **Vaksin kategori enum** — currently free-text; must become fixed enum (Wajib, Tambahan, Booster)
5. **UI for soft-deleted records** — no "Dihapus" badge, no restore button, no edit-lock

## Technical Context

**Language/Version**: PHP 8.1 / Laravel 9
**Primary Dependencies**: Yajra DataTables, Bootstrap 5, SweetAlert, jQuery
**Storage**: MySQL — tables `jenis_vaksin`, `jenis_kasus_epidemiologi` with FK constraints (`RESTRICT`) to `imunisasi` and `surveillance_cases`
**Testing**: PHPUnit via `php artisan test`
**Target Platform**: Web (server-rendered Blade + AJAX)
**Project Type**: Web application (Laravel monolith)
**Performance Goals**: N/A (admin-only, low-traffic pages)
**Constraints**: Must follow existing UI patterns (Bootstrap 5 modals, DataTables, SweetAlert notifications)
**Scale/Scope**: 2 CRUD pages, ~17 routes total (including restore), superadmin-only access

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

Constitution is unconfigured (template placeholders only). No project-specific gates to enforce. **PASS** — proceeding with standard Laravel conventions as documented in CLAUDE.md.

**Post-Phase 1 Re-check**: Design follows existing controller/view patterns. SoftDeletes is a standard Laravel feature. No violations. **PASS**.

## Project Structure

### Documentation (this feature)

```text
specs/003-manage-master-data/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output (route contracts)
│   └── master-data-routes.md
└── tasks.md             # Phase 2 output (via /speckit.tasks)
```

### Source Code (repository root)

```text
app/
├── Http/
│   ├── Controllers/
│   │   ├── MasterDataVaksinController.php      # CRUD + DataTables + restore for vaksin
│   │   └── MasterDataPenyakitController.php    # CRUD + DataTables + restore for penyakit
│   └── Middleware/
│       └── CheckModuleRole.php                 # module.role:superadmin gate
├── Models/
│   ├── JenisVaksin.php                         # + SoftDeletes trait (TO ADD)
│   ├── JenisKasusEpidemiologi.php              # + SoftDeletes trait (TO ADD)
│   ├── Imunisasi.php                           # Child table (FK reference)
│   └── SurveillanceCase.php                    # Child table (FK reference)
│
database/migrations/
├── 2026_01_15_053308_create_imunisasi_table.php
├── 2026_02_13_000001_create_jenis_kasus_epidemiologi_table.php
├── 2026_03_06_000001_change_imunisasi_vaksin_fk_to_restrict.php
├── 2026_03_09_000001_add_soft_deletes_to_master_data_tables.php     # TO CREATE
├── 2026_03_09_000002_change_jenis_vaksin_kategori_to_enum.php       # TO CREATE
│
resources/views/admin/master-data/
├── vaksin/index.blade.php                      # + Dihapus badge, restore btn, enum dropdown (TO UPDATE)
└── penyakit/index.blade.php                    # + Dihapus badge, restore btn (TO UPDATE)
│
resources/views/vendor/admin/layouts/partials/
└── leftsidebar.blade.php                       # Already has master-data links ✓
│
routes/web.php                                  # + restore routes (TO UPDATE)
│
tests/Feature/
├── MasterDataVaksinTest.php                    # + soft-delete, restore, enum tests (TO UPDATE)
└── MasterDataPenyakitTest.php                  # + soft-delete, restore tests (TO UPDATE)
```

**Structure Decision**: Standard Laravel monolith structure. No new directories needed — all files placed in existing conventional locations.

## Implementation Gap Summary

| Area | Current State | Required State | Priority |
|------|--------------|----------------|----------|
| Models (SoftDeletes) | No SoftDeletes trait | Add `use SoftDeletes` to both models | CRITICAL |
| Migrations (deleted_at) | No `deleted_at` column | Add column to both tables | CRITICAL |
| Migration (vaksin kategori) | String type, free-text | Enum: Wajib, Tambahan, Booster | CRITICAL |
| Controllers (destroy) | Rejects deletion if children exist (409) | Hard-delete if no children, soft-delete if children | CRITICAL |
| Controllers (restore) | No method | Add `restore()` method to both controllers | CRITICAL |
| Controllers (getData) | Only shows non-deleted | Include soft-deleted with "Dihapus" badge | CRITICAL |
| Controllers (vaksin validation) | `kategori: required\|string\|max:100` | `kategori: required\|in:Wajib,Tambahan,Booster` | HIGH |
| Routes (restore) | Not defined | Add PATCH restore routes for both resources | CRITICAL |
| Views (soft-deleted UI) | No "Dihapus" badge or restore button | Show badge, disable edit, show restore button | HIGH |
| Views (vaksin kategori) | Datalist (free-text) | Fixed dropdown/select | HIGH |
| Tests (soft-delete) | Only tests rejection | Test hybrid delete + restore + enum | MEDIUM |

## Complexity Tracking

No violations to justify. SoftDeletes, enum validation, and restore are standard Laravel patterns requiring no architectural complexity.

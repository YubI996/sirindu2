# Tasks: Manajemen Master Data Imunisasi & Penyakit Surveilans

**Input**: Design documents from `/specs/003-manage-master-data/`
**Prerequisites**: plan.md (required), spec.md (required), research.md, data-model.md, contracts/

**Tests**: Included — existing tests need updating for new soft-delete/restore/enum behavior.

**Organization**: Tasks are grouped by user story. Base CRUD already exists; tasks focus on closing gaps identified in plan.md (SoftDeletes, hybrid deletion, restore, vaksin kategori enum, soft-deleted UI).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

- Laravel monolith: `app/`, `database/`, `resources/`, `routes/`, `tests/` at repository root

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Database migrations and model updates shared by US1 and US2

- [x] T001 Create migration to add `deleted_at` (softDeletes) column to both `jenis_vaksin` and `jenis_kasus_epidemiologi` tables in `database/migrations/2026_03_09_000001_add_soft_deletes_to_master_data_tables.php`
- [x] T002 Create migration to change `jenis_vaksin.kategori` from `varchar(100)` to `enum('Wajib','Tambahan','Booster')` with data migration step (map existing values, default unrecognized to 'Wajib') in `database/migrations/2026_03_09_000002_change_jenis_vaksin_kategori_to_enum.php`
- [x] T003 Run `php artisan migrate` to apply both migrations and verify no errors

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Model updates that MUST be complete before controller/view work

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [x] T004 [P] Add `use SoftDeletes` trait and `'deleted_at'` to `$dates` array in `app/Models/JenisVaksin.php`. Ensure `scopeAktif` also excludes soft-deleted records (SoftDeletes handles this automatically via global scope).
- [x] T005 [P] Add `use SoftDeletes` trait and `'deleted_at'` to `$dates` array in `app/Models/JenisKasusEpidemiologi.php`. Ensure `scopeActive` also excludes soft-deleted records.

**Checkpoint**: Foundation ready — both models support SoftDeletes, migrations applied

---

## Phase 3: User Story 1 — CRUD Jenis Vaksin (Priority: P1) 🎯 MVP

**Goal**: Complete vaksin CRUD with hybrid deletion (hard/soft), restore capability, enum kategori, and soft-deleted record display.

**Independent Test**: Login as superadmin → open master data vaksin → add vaksin with enum kategori → toggle status → delete (verify hard-delete without children, soft-delete with children) → verify "Dihapus" badge appears → restore soft-deleted record.

### Implementation for User Story 1

- [x] T006 [US1] Update `destroy()` method in `app/Http/Controllers/MasterDataVaksinController.php`: replace rejection (409) with hybrid logic — `forceDelete()` if `imunisasi()->count() == 0`, `delete()` (soft-delete) if children exist. Return different messages for hard-delete vs soft-delete (include `soft_deleted: true` flag in response).
- [x] T007 [US1] Add `restore()` method to `app/Http/Controllers/MasterDataVaksinController.php`: use `JenisVaksin::onlyTrashed()->findOrFail($id)->restore()`, return JSON success response.
- [x] T008 [US1] Update `getData()` method in `app/Http/Controllers/MasterDataVaksinController.php`: change query from `JenisVaksin::query()` to `JenisVaksin::withTrashed()`. Update `status_badge` column to show `<span class='badge bg-danger'>Dihapus</span>` when `deleted_at` is not null (overrides aktif badge). Update `action` column to show only restore button for soft-deleted records (no edit/toggle/delete).
- [x] T009 [US1] Update validation rules for `store()` and `update()` in `app/Http/Controllers/MasterDataVaksinController.php`: change `kategori` from `'required|string|max:100'` to `'required|in:Wajib,Tambahan,Booster'`.
- [x] T010 [US1] Add restore route in `routes/web.php`: `Route::patch('restore/{id}', [MasterDataVaksinController::class, 'restore'])->name('admin.masterdata.vaksin.restore');` inside the existing `master-data/vaksin` prefix group.
- [x] T011 [US1] Update `resources/views/admin/master-data/vaksin/index.blade.php`: (a) Change kategori input from datalist to `<select>` dropdown with options Wajib, Tambahan, Booster. (b) Add "Restore" button in DataTables action column for soft-deleted rows. (c) Add AJAX handler for restore button (PATCH to restore route). (d) Handle soft-delete response from delete action — show appropriate SweetAlert message differentiating hard-delete vs soft-delete.

### Tests for User Story 1

- [x] T012 [US1] Update `tests/Feature/MasterDataVaksinTest.php`: (a) Change existing `test_delete_blocked_when_vaksin_has_imunisasi_records` to verify soft-delete instead of 409 rejection — assert `deleted_at` is set, record still in DB. (b) Add `test_delete_without_children_hard_deletes` — assert record fully removed from DB. (c) Add `test_superadmin_can_restore_soft_deleted_vaksin` — soft-delete a record then PATCH restore, assert `deleted_at` is null. (d) Add `test_store_fails_with_invalid_kategori` — submit with kategori='Invalid', assert 422. (e) Add `test_store_succeeds_with_valid_enum_kategori` — submit with kategori='Wajib', assert success.

**Checkpoint**: User Story 1 fully functional — vaksin CRUD with hybrid delete, restore, enum kategori

---

## Phase 4: User Story 2 — CRUD Jenis Penyakit Surveilans (Priority: P1)

**Goal**: Complete penyakit CRUD with hybrid deletion (hard/soft), restore capability, and soft-deleted record display.

**Independent Test**: Login as superadmin → open master data penyakit → add penyakit → toggle status → delete (verify hard-delete without children, soft-delete with children) → verify "Dihapus" badge appears → restore soft-deleted record.

### Implementation for User Story 2

- [x] T013 [US2] Update `destroy()` method in `app/Http/Controllers/MasterDataPenyakitController.php`: replace rejection (409) with hybrid logic — `forceDelete()` if `surveillanceCases()->count() == 0`, `delete()` (soft-delete) if children exist. Return different messages for hard-delete vs soft-delete.
- [x] T014 [US2] Add `restore()` method to `app/Http/Controllers/MasterDataPenyakitController.php`: use `JenisKasusEpidemiologi::onlyTrashed()->findOrFail($id)->restore()`, return JSON success response.
- [x] T015 [US2] Update `getData()` method in `app/Http/Controllers/MasterDataPenyakitController.php`: change query to `JenisKasusEpidemiologi::withTrashed()`. Update `status_badge` column to show "Dihapus" badge when `deleted_at` is not null. Update `action` column to show only restore button for soft-deleted records.
- [x] T016 [US2] Add restore route in `routes/web.php`: `Route::patch('restore/{id}', [MasterDataPenyakitController::class, 'restore'])->name('admin.masterdata.penyakit.restore');` inside the existing `master-data/penyakit` prefix group.
- [x] T017 [US2] Update `resources/views/admin/master-data/penyakit/index.blade.php`: (a) Add "Restore" button in DataTables action column for soft-deleted rows. (b) Add AJAX handler for restore button. (c) Handle soft-delete response from delete action with appropriate SweetAlert message.

### Tests for User Story 2

- [x] T018 [US2] Update `tests/Feature/MasterDataPenyakitTest.php`: (a) Change existing `test_delete_blocked_when_penyakit_has_surveillance_records` to verify soft-delete instead of 409 rejection. (b) Add `test_delete_without_children_hard_deletes`. (c) Add `test_superadmin_can_restore_soft_deleted_penyakit`.

**Checkpoint**: User Stories 1 AND 2 both work independently

---

## Phase 5: User Story 3 — Navigasi Melekat di Modul (Priority: P2)

**Goal**: Verify sidebar navigation links for master data pages are correctly placed and role-restricted.

**Independent Test**: Login as superadmin → verify "Jenis Vaksin" under "Data" dropdown and "Jenis Penyakit" under "Epidemiologi" dropdown. Login as non-superadmin → verify links are NOT visible.

### Implementation for User Story 3

- [x] T019 [US3] Verify sidebar navigation in `resources/views/vendor/admin/layouts/partials/leftsidebar.blade.php`: confirm "Jenis Vaksin" appears under "Data" dropdown (after "Data Anak") and "Jenis Penyakit" appears under "Epidemiologi" dropdown, both visible only for superadmin role. Fix if not matching spec.

**Checkpoint**: All user stories independently functional

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Verify integration and edge cases across all stories

- [x] T020 Run full test suite: `php artisan test --filter MasterData` — verify all existing and new tests pass
- [x] T021 Verify edge case: soft-deleted and inactive vaksin/penyakit do NOT appear in dropdown forms used by imunisasi and surveilans modules (FR-012b). Check any views/controllers that populate vaksin/penyakit dropdowns and ensure they use `aktif` / `active` scopes (which exclude soft-deleted by default via SoftDeletes global scope).
- [x] T022 Verify edge case: when all penyakit are deactivated, the surveilans form dropdown shows an empty state with informative message

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately
- **Foundational (Phase 2)**: Depends on Phase 1 (migrations must be applied first)
- **User Story 1 (Phase 3)**: Depends on Phase 2 (models must have SoftDeletes)
- **User Story 2 (Phase 4)**: Depends on Phase 2 (models must have SoftDeletes)
- **User Story 3 (Phase 5)**: No dependencies on other phases (sidebar already exists)
- **Polish (Phase 6)**: Depends on Phases 3 + 4 completion

### User Story Dependencies

- **US1 (P1)**: Can start after Phase 2 — no dependency on US2 or US3
- **US2 (P1)**: Can start after Phase 2 — no dependency on US1 or US3
- **US3 (P2)**: Independent — can start anytime (verification only)

### Within Each User Story

- Controller changes (destroy → restore → getData → validation) are sequential
- Route must be added before view AJAX handler can reference it
- Tests should run after implementation

### Parallel Opportunities

- T004 and T005 (model updates) can run in parallel
- US1 (Phase 3) and US2 (Phase 4) can run in parallel after Phase 2
- US3 (Phase 5) can run in parallel with any other phase

---

## Parallel Example: After Phase 2

```bash
# Launch US1 and US2 in parallel (different controllers, different views):
Agent 1: T006 → T007 → T008 → T009 → T010 → T011 → T012  (vaksin)
Agent 2: T013 → T014 → T015 → T016 → T017 → T018          (penyakit)
Agent 3: T019                                                (sidebar verification)
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Migrations (T001–T003)
2. Complete Phase 2: Model SoftDeletes (T004–T005)
3. Complete Phase 3: Vaksin CRUD updates (T006–T012)
4. **STOP and VALIDATE**: Test vaksin independently
5. Deploy/demo if ready

### Incremental Delivery

1. Phase 1 + 2 → Foundation ready
2. Add US1 (vaksin) → Test → Deploy (MVP!)
3. Add US2 (penyakit) → Test → Deploy
4. Verify US3 (sidebar) → Complete
5. Polish → Final validation

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story
- Base CRUD already exists — tasks focus on gap closure (SoftDeletes, hybrid delete, restore, enum)
- All controller/view changes are updates to existing files, not new file creation
- Commit after each task or logical group

# Research: Manajemen Master Data Imunisasi & Penyakit Surveilans

**Date**: 2026-03-09 | **Status**: Complete (updated post-clarification)

## Research Tasks & Findings

### 1. How should SoftDeletes interact with existing FK RESTRICT constraints?

**Decision**: Use Laravel `SoftDeletes` trait. The FK `RESTRICT` constraint only blocks hard-deletes — `SoftDeletes` sets `deleted_at` without triggering FK checks since the row is not physically removed.
**Rationale**: When a child record exists (imunisasi or surveillance_case), the controller soft-deletes instead of hard-deleting. The FK RESTRICT constraint acts as a safety net only for the hard-delete path (no children). The `withTrashed()` scope ensures soft-deleted parents remain accessible when viewing historical child records.
**Alternatives considered**:
- Remove RESTRICT constraint — rejected because it removes the safety net for hard-delete path.
- Always soft-delete — rejected because the spec explicitly requires hard-delete when no children exist.

### 2. How should the hybrid deletion strategy work in the controller?

**Decision**: In `destroy()`, check child count first. If count > 0, call `$record->delete()` (SoftDeletes intercepts this). If count == 0, call `$record->forceDelete()` for permanent removal.
**Rationale**: `forceDelete()` bypasses SoftDeletes and performs a real DELETE. The FK RESTRICT constraint provides an additional safety net — if somehow children were created between the count check and the delete, the DB will block it.
**Alternatives considered**:
- Transaction lock — rejected as over-engineering for admin-only, low-concurrency pages.
- Always use `delete()` then conditionally `forceDelete()` — rejected as unnecessarily complex.

### 3. How should getData() include soft-deleted records?

**Decision**: Use `JenisVaksin::withTrashed()` / `JenisKasusEpidemiologi::withTrashed()` in the DataTables query. Add a computed column `status_badge` that shows "Dihapus" (red badge) when `deleted_at` is not null, overriding the aktif/is_active badge.
**Rationale**: `withTrashed()` is the standard Laravel scope for including soft-deleted records. The UI differentiates via badge color and disabling action buttons.
**Alternatives considered**:
- Separate "Deleted" tab — rejected because it adds UI complexity and the spec says records should appear in the same list.
- Filter toggle — rejected per spec: soft-deleted records must always be visible.

### 4. How should restore work?

**Decision**: Add `restore()` method to both controllers. Route: `PATCH /restore/{id}`. Use `JenisVaksin::onlyTrashed()->findOrFail($id)->restore()`. Return JSON success response.
**Rationale**: Standard Laravel SoftDeletes restore pattern. PATCH is semantically correct (partial update of the `deleted_at` field). `onlyTrashed()` ensures only soft-deleted records can be targeted.
**Alternatives considered**:
- POST method — rejected because PATCH better represents a partial state change.
- Restore via update endpoint — rejected because restore is a distinct action with different authorization semantics.

### 5. What deletion strategy should be used? (updated from v1)

**Decision**: Hybrid: hard-delete (`forceDelete()`) if no child records, soft-delete (`delete()`) if child records exist.
**Rationale**: Per clarification session — user explicitly chose this strategy. Hard-delete keeps the table clean for truly unused records. Soft-delete preserves referential integrity for records in use.
**Alternatives considered**:
- Always reject deletion (v1 approach) — rejected per user clarification.
- Always soft-delete — rejected per user preference for clean table when safe.

### 6. What authorization pattern should be used?

**Decision**: `CheckModuleRole` middleware with `module.role:superadmin` parameter.
**Rationale**: Already in use by both controllers. No changes needed.
**Alternatives considered**: None — existing pattern is correct.

### 7. What UI pattern should the CRUD pages follow?

**Decision**: Bootstrap 5 modal-based CRUD with DataTables and SweetAlert. Extended with "Dihapus" badge and restore button for soft-deleted records.
**Rationale**: Extends the established pattern. Soft-deleted rows disable edit/toggle/delete buttons and show a restore button instead.
**Alternatives considered**: None — extending existing pattern is the correct approach.

### 8. Vaksin kategori: enum values and validation

**Decision**: Fixed enum `in:Wajib,Tambahan,Booster`. Migration changes column from `varchar(100)` to `enum('Wajib','Tambahan','Booster')`. View changes from datalist to `<select>` dropdown.
**Rationale**: Per clarification session — user explicitly chose fixed enum. Consistent with how penyakit kategori works.
**Alternatives considered**:
- Free-text with datalist (v1 approach) — rejected per user clarification.
- Separate master table — rejected as over-engineering for 3 values.

### 9. Migration strategy for existing data

**Decision**: The migration to change vaksin kategori to enum should include a data migration step: map any existing free-text values to the closest enum value, defaulting unrecognized values to 'Wajib'.
**Rationale**: Existing data may have arbitrary kategori strings. The migration must handle this to avoid constraint violations.
**Alternatives considered**:
- Abort migration if invalid data exists — rejected because it blocks deployment.
- Keep as string with validation-only enum — rejected because DB-level enum provides stronger guarantee.

## All NEEDS CLARIFICATION: Resolved

No unresolved questions remain. All technical decisions align with spec clarifications and existing codebase patterns.

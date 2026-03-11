# General Requirements Quality Checklist: Export Data Imunisasi Anak

**Purpose**: Validate spec completeness, clarity, consistency, and coverage for reviewer assessment
**Created**: 2026-03-11
**Feature**: [spec.md](../spec.md)
**Depth**: Standard | **Audience**: Reviewer | **Focus**: Data/Export + UX

## Requirement Completeness

- [ ] CHK001 - Are all CSV column data sources explicitly mapped to database fields/relationships? [Completeness, Spec §FR-009]
- [ ] CHK002 - Is the "Dosis" column in FR-009 defined — does it refer to dose number (1st, 2nd) or dose amount (ml)? [Clarity, Spec §FR-009]
- [ ] CHK003 - Is the "Lokasi Pemberian" column in FR-009 defined — does it mean Posyandu name, Puskesmas, or physical address? [Clarity, Spec §FR-009]
- [ ] CHK004 - Are requirements specified for CSV column ordering? [Gap, Spec §FR-009]
- [ ] CHK005 - Is the month/year filter input type specified — dropdown, date picker, or separate month+year selects? [Clarity, Spec §FR-002]
- [ ] CHK006 - Are the dropdown population requirements for the month filter defined — how far back should months be listed? [Gap, Spec §FR-002]
- [ ] CHK007 - Is "jenis vaksin aktif" in FR-004 clearly distinguished from soft-deleted vaccines mentioned in edge cases? [Consistency, Spec §FR-004]
- [ ] CHK008 - Are requirements defined for how historical data with inactive antigens appears when using the antigen filter? [Gap, Spec §FR-004 + Edge Cases]
- [ ] CHK009 - Is the CSV row ordering specified (e.g., by date, by child name, by kelurahan)? [Gap]

## Requirement Clarity

- [ ] CHK010 - Is the filename pattern in FR-008 fully specified for all filter combinations (e.g., what if only status is selected, or no filter at all)? [Clarity, Spec §FR-008]
- [ ] CHK011 - Is "tanggal_pemberian" clearly defined as the sole date field used for month filtering across spec, assumptions, and edge cases? [Consistency, Spec §FR-002 + Assumptions]
- [ ] CHK012 - Is the status value set (belum/sudah/terlambat) exhaustive — are there other possible statuses in the data model? [Completeness, Spec §FR-005]
- [ ] CHK013 - Are the terms "super-admin", "admin", and "faskes surveilans" clearly defined and consistent with the codebase role system? [Clarity, Spec §FR-012]
- [ ] CHK014 - Is "semua kelurahan yang tersedia di sistem" in Assumptions unambiguous — does it include inactive/archived kelurahan? [Clarity, Assumptions]

## Requirement Consistency

- [ ] CHK015 - Does the spec consistently define filter optionality — FR-006 says all optional, but T010 in tasks disables export without month filter? [Conflict, Spec §FR-006 vs tasks T010]
- [ ] CHK016 - Are acceptance scenario 3 (no filters at all) and the "all filters optional" requirement (FR-006) consistent with FR-011 (disable export when no data)? [Consistency, Spec §FR-006 + FR-011]
- [ ] CHK017 - Is the scope of "data imunisasi yang tersedia" in scenario 3 bounded — does it mean all historical data without any date limit? [Ambiguity, Spec §US1 Scenario 3]

## Acceptance Criteria Quality

- [ ] CHK018 - Is SC-001's "5 detik untuk 5.000 record" measured from button click to download start, or to download completion? [Measurability, Spec §SC-001]
- [ ] CHK019 - Is SC-004's "kurang dari 1 menit" measured for a specific user skill level or scenario complexity? [Measurability, Spec §SC-004]
- [ ] CHK020 - Can SC-002 ("dibuka tanpa error di Excel dan Google Sheets") be objectively verified — which Excel/Sheets versions? [Measurability, Spec §SC-002]
- [ ] CHK021 - Is SC-005's "tidak ada data yang salah masuk atau terlewat" testable — what is the verification method? [Measurability, Spec §SC-005]

## Scenario Coverage

- [ ] CHK022 - Are requirements defined for what happens during export of very large datasets (e.g., 10.000+ records) — timeout handling, progress indicator, memory limits? [Gap, Edge Cases]
- [ ] CHK023 - Are concurrent export requirements specified — what happens if the same admin or multiple admins trigger exports simultaneously? [Gap]
- [ ] CHK024 - Are requirements defined for browser download behavior — does the download start immediately or via a generated link? [Gap]
- [ ] CHK025 - Are requirements specified for handling special characters in child names (e.g., apostrophes, non-Latin characters) in CSV output? [Gap]

## Edge Case Coverage

- [ ] CHK026 - Are requirements defined for the scenario where a child belongs to a kelurahan but has no immunization records? [Gap]
- [ ] CHK027 - Is the behavior specified when the antigen dropdown shows active vaccines but no data exists for the selected antigen+month combination? [Completeness, Edge Cases]
- [ ] CHK028 - Are requirements defined for CSV output when a child record has null/missing values for optional fields (e.g., no NIK, no Posyandu)? [Gap, Spec §FR-009]

## Non-Functional Requirements

- [ ] CHK029 - Are accessibility requirements specified for the filter form and preview table (keyboard navigation, screen reader support)? [Gap]
- [ ] CHK030 - Are requirements defined for responsive/mobile behavior of the export page? [Gap]
- [ ] CHK031 - Is there a requirement for audit logging of export actions (who exported what, when)? [Gap]

## Dependencies & Assumptions

- [ ] CHK032 - Is the assumption that "semua tabel sudah ada" validated — is the `imunisasi` table structure documented with relevant columns? [Assumption]
- [ ] CHK033 - Is the relationship between `imunisasi` records and `anak` records clearly documented (1:N, mandatory FK)? [Assumption]
- [ ] CHK034 - Is the CSV separator assumption (comma) consistent with Indonesian locale conventions where comma is a decimal separator? [Assumption]

## Notes

- CHK015 is a potential conflict: spec says all filters are optional (FR-006), but tasks.md T010 implies month filter may be required. This needs resolution.
- CHK002 and CHK003 flag ambiguous CSV column names that could be interpreted differently by implementers.
- Focus areas: Data/Export integrity and UX filter interaction received deepest coverage.

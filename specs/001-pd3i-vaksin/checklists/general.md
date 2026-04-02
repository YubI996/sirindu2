# Requirements Quality Checklist: Peningkatan Modul Surveilans PD3I & Kelompok Vaksin

**Purpose**: Comprehensive validation of specification quality, completeness, clarity, consistency, and measurability across all 7 user stories. Pre-gate checklist for formal sign-off readiness.  
**Created**: 2026-04-02  
**Feature**: [spec.md](../spec.md) | [plan.md](../plan.md) | [tasks.md](../tasks.md)  
**Depth**: Comprehensive pre-gate validation (data accuracy, integration, edge cases, exports focus)

---

## Requirement Completeness

*Are all necessary requirements documented? Do gaps exist in critical flows?*

- [ ] CHK001 - Are nomor epidemiologi generation requirements complete for all 5 disease types (Campak, Difteri, Pertusis, AFP/Polio, Tetanus Neonatorum)? [Completeness, Spec §FR-001, FR-002]
- [ ] CHK002 - Are sequence counter reset requirements defined for year transitions (e.g., January 1 behavior)? [Gap, Spec §FR-004]
- [ ] CHK003 - Are database locking/transaction requirements explicitly documented for concurrent epid generation? [Completeness, Spec §FR-005]
- [ ] CHK004 - Are all three vaccine group definitions complete (IDL/IBL/ISL with min/max age, kejar duration)? [Completeness, Spec §FR-015 to FR-022]
- [ ] CHK005 - Is the vaccine completeness status calculation logic fully specified (required vs. received vaccines per group)? [Gap, Spec §FR-018, FR-019]
- [ ] CHK006 - Are gender-specific vaccine requirements documented (e.g., HPV vaccine for ISL group)? [Gap, Spec §FR-022]
- [ ] CHK007 - Are address input requirements complete (KTP address fields AND map coordinate fields separately documented)? [Completeness, Spec §FR-006, FR-007]
- [ ] CHK008 - Is the "kejar" status calculation fully specified (age thresholds, vaccine groups affected, kejar duration boundaries)? [Completeness, Spec §FR-023 to FR-026]
- [ ] CHK009 - Are Early Warning System integration requirements documented (which scores change, by how much)? [Gap, Spec §FR-025]
- [ ] CHK010 - Is the chart "Distribusi Kasus di Fasilitas Umum" fully specified (data source, grouping logic, empty state behavior)? [Completeness, Spec §FR-009 to FR-011]
- [ ] CHK011 - Are facility/location categories fully defined (Sekolah, Tempat Kerja, Gym, Tempat Ibadah, Lainnya, Custom - are these all?)? [Gap, Spec §FR-010]
- [ ] CHK012 - Is the PDF export requirements complete for all required sections (7 sections listed - are all defined)? [Completeness, Spec §FR-012 to FR-014]
- [ ] CHK013 - Is the aggregate immunization export completely specified (all columns, filter options, header row, empty state)? [Completeness, Spec §FR-027 to FR-030]
- [ ] CHK014 - Are export file naming conventions completely defined for both PDF and Excel exports? [Completeness, Spec §FR-012, FR-030]
- [ ] CHK015 - Are data backward compatibility requirements documented (old text-based lokasi_penularan field handling)? [Gap, Spec §FR-011a]

---

## Requirement Clarity

*Are requirements specific, unambiguous, and measurable? Do vague terms need quantification?*

- [ ] CHK016 - Is the nomor epid format specification unambiguous (e.g., "YY" = last 2 digits of year, zero-padded)? [Clarity, Spec §FR-001]
- [ ] CHK017 - Is "urutan 3 digit" quantified precisely (e.g., zero-padded to 3 digits, NNN format)? [Clarity, Spec §FR-003]
- [ ] CHK018 - Is the vaccine completeness status binary clearly defined (exactly "Lengkap" or "Belum Lengkap" - no partial states)? [Clarity, Spec §FR-018]
- [ ] CHK019 - Is "kejar" status clearly defined as a binary state per vaccine group (either "Kejar IDL", "Kejar IBL", or none)? [Clarity, Spec §FR-023, FR-024]
- [ ] CHK020 - Is the age boundary for IDL kejar explicitly quantified (>11 months AND <5 years, or inclusive/exclusive)? [Clarity, Spec §FR-023, FR-026]
- [ ] CHK021 - Is the age boundary for IBL kejar explicitly quantified (>23 months AND <5 years, or inclusive/exclusive)? [Clarity, Spec §FR-024, FR-026]
- [ ] CHK022 - Is ISL "no kejar" requirement unambiguous (can child age 7-12 receive kejar, or never)? [Ambiguity, Spec §FR-022]
- [ ] CHK023 - Is "tempat umum" (public facilities) term clearly defined or is it open to interpretation? [Ambiguity, Spec §FR-009, FR-010]
- [ ] CHK024 - Is the "poin prioritas intervensi" scoring system clearly quantified (e.g., +10 for single kejar, +20 for dual kejar)? [Clarity, Spec §FR-025]
- [ ] CHK025 - Is "readonly" field behavior for nomor epid explicitly defined (no edit in create, no edit in update)? [Clarity, Spec §FR-005a]
- [ ] CHK026 - Are the 7 PDF sections explicitly listed and defined (informasi kasus, klinis, komplikasi, pengobatan, imunisasi, epidemiologis, lab)? [Clarity, Spec §FR-014]
- [ ] CHK027 - Is the Excel export column structure unambiguously specified (exact order, exact labels, L/P/Total meaning)? [Clarity, Spec §FR-029]
- [ ] CHK028 - Is "Kota Bontang" the scope boundary clearly defined (is this the only geographic scope)? [Clarity, Spec Assumptions]

---

## Requirement Consistency

*Do requirements align without conflicts across user stories?*

- [ ] CHK029 - Are vaccine group age ranges consistent and non-overlapping (0-11 months IDL, 12-23 months IBL, 7-12 years ISL)? [Consistency, Spec §FR-020 to FR-022]
- [ ] CHK030 - Is "kejar" duration consistent (60 months / 5 years) for both IDL and IBL? [Consistency, Spec §FR-020, FR-021, FR-026]
- [ ] CHK031 - Is the vaccine group definition consistent between §FR-015-FR-017 (master definition) and §FR-020-FR-022 (age requirements)? [Consistency, Spec §FR-015 to FR-022]
- [ ] CHK032 - Are address field requirements consistent (both §FR-006 for manual input AND upstream usage in case entry and dashboard)? [Consistency, Spec §FR-006 to FR-008]
- [ ] CHK033 - Is the coordinate handling consistent (map click ONLY sets coordinates per §FR-007, never modifies address)? [Consistency, Spec §FR-006, FR-007]
- [ ] CHK034 - Are all field readonly requirements consistently specified across the form (nomor epid readonly, are others)? [Consistency, Spec §FR-005a]
- [ ] CHK035 - Is the "kejar" terminology consistent across user stories and acceptance scenarios (same term, same definition)? [Consistency, Spec §US4, FR-023 to FR-026]

---

## Acceptance Criteria Quality

*Are success criteria (§SC) measurable and testable for all user stories?*

- [ ] CHK036 - Is SC-001 testable (nomor epid generation <30 sec) - what constitutes "success"? [Measurability, Spec §SC-001]
- [ ] CHK037 - Is SC-002 testable (100% unique nomor epid) - how is uniqueness verified in test? [Measurability, Spec §SC-002]
- [ ] CHK038 - Is SC-003 testable (independent address + coordinate entry) - defined test steps? [Measurability, Spec §SC-003]
- [ ] CHK039 - Is SC-004 testable (geographic accuracy) - what is the baseline/comparison data? [Measurability, Spec §SC-004]
- [ ] CHK040 - Is SC-005 testable (visible status in profile) - defined view/screen location? [Measurability, Spec §SC-005]
- [ ] CHK041 - Is SC-006 testable (kejar sorting in priority) - defined sort order or ranking? [Measurability, Spec §SC-006]
- [ ] CHK042 - Is SC-007 testable (PDF format compliance) - defined reference document or standard? [Measurability, Spec §SC-007]
- [ ] CHK043 - Is SC-008 testable (Excel aggregate accuracy) - sample data + expected output defined? [Measurability, Spec §SC-008]
- [ ] CHK044 - Is SC-009 testable (chart accuracy + filter responsiveness) - defined test data and expectations? [Measurability, Spec §SC-009]

---

## Scenario Coverage

*Are primary, alternate, exception, and recovery flows addressed?*

### Nomor Epidemiologi (US1)

- [ ] CHK045 - Are all acceptance scenarios defined for nomor epid (create campak, AFP/Polio without prefix, year boundary)? [Coverage, Spec §US1]
- [ ] CHK046 - Is the scenario for simultaneous case creation addressed (race condition handling)? [Coverage, Spec §FR-005]
- [ ] CHK047 - Is a scenario for manual nomor epid override addressed (or explicitly forbidden)? [Coverage, Spec §FR-005a]

### Kelompok Vaksin & Status (US2, US4)

- [ ] CHK048 - Are scenarios for child age transitions (e.g., 11→12 months) affecting vaccine group eligibility addressed? [Coverage, Spec §US2]
- [ ] CHK049 - Is the scenario for new vaccines added after groups are created addressed? [Coverage, Spec §Edge Cases]
- [ ] CHK050 - Are gender-specific vaccine scenarios (HPV for females only in ISL) addressed? [Coverage, Spec §US2]
- [ ] CHK051 - Is the scenario for child receiving vaccines out-of-order addressed? [Coverage]
- [ ] CHK052 - Are kejar status transitions (entering kejar, exiting kejar, overlapping kejar IDL+IBL) addressed? [Coverage, Spec §US4 scenarios]

### Address & Coordinates (US3)

- [ ] CHK053 - Is the scenario for user selecting peta point AFTER filling address dropdowns addressed? [Coverage, Spec §US3]
- [ ] CHK054 - Is the scenario for user filling address AFTER selecting peta point addressed? [Coverage, Spec §US3]
- [ ] CHK055 - Is the scenario for address/coordinate mismatch in dashboard queries addressed? [Coverage, Spec §US3]

### Chart & Dashboard (US5)

- [ ] CHK056 - Is the scenario for zero cases in selected facility category addressed? [Coverage, Spec §US5 scenarios, FR-011a]
- [ ] CHK057 - Is the scenario for old text-based lokasi_penularan data (pre-dropdown) display in chart addressed? [Coverage, Spec §FR-011a]

### Export PDF (US6)

- [ ] CHK058 - Is the scenario for cases with missing optional fields (e.g., no lab data) addressed? [Coverage, Spec §Edge Cases]
- [ ] CHK059 - Is the scenario for coordinate-less cases generating PDF addressed? [Coverage, Spec §Edge Cases]

### Export Agregat (US7)

- [ ] CHK060 - Is the scenario for export with zero data for selected month/year addressed? [Coverage, Spec §Edge Cases]
- [ ] CHK061 - Is the scenario for new vaccines added mid-year affecting export columns addressed? [Coverage]

---

## Edge Case Coverage

*Are boundary conditions, error states, and exceptional behaviors defined?*

### Data Boundaries

- [ ] CHK062 - Is the nomor epid boundary case documented (what happens at 999 sequence)? [Edge Case, Spec §Edge Cases]
- [ ] CHK063 - Is the vaccine group age boundary behavior documented (e.g., exactly 12 months = IDL or IBL)? [Edge Case, Spec §FR-020, FR-021]
- [ ] CHK064 - Is the kejar age cutoff behavior documented (e.g., exactly 5 years 0 months = kejar or not)? [Edge Case, Spec §Edge Cases]
- [ ] CHK065 - Is the ISL age 13+ scenario addressed (child too old for ISL at data entry time)? [Edge Case]

### Concurrent & Race Conditions

- [ ] CHK066 - Is the simultaneous dual case creation race condition fully mitigated (lock mechanism defined)? [Edge Case, Spec §FR-005]
- [ ] CHK067 - Is the scenario for lock timeout or database unavailability addressed? [Edge Case]

### Data Integrity

- [ ] CHK068 - Is backward compatibility for old lokasi_penularan text entries fully specified? [Edge Case, Spec §FR-011a]
- [ ] CHK069 - Is the scenario for vaccine group reassignment (if vaccines are re-grouped later) addressed? [Edge Case]
- [ ] CHK070 - Is the scenario for child date-of-birth correction affecting all calculated statuses addressed? [Edge Case]

### Empty States

- [ ] CHK071 - Are empty state messages defined for chart, exports, and dashboard sections? [Edge Case, Spec §US5, US7]
- [ ] CHK072 - Is the behavior for child with zero vaccines received documented? [Edge Case]

---

## Non-Functional Requirements

*Are performance, security, data integrity, and system requirements specified?*

### Performance

- [ ] CHK073 - Is nomor epid generation performance target specified (see plan.md goals)? [Non-Functional]
- [ ] CHK074 - Is PDF generation performance target specified (see plan.md <5s goal)? [Non-Functional]
- [ ] CHK075 - Is aggregate export performance target specified (see plan.md <30s goal)? [Non-Functional]
- [ ] CHK076 - Is dashboard chart rendering performance expectation documented? [Gap, Non-Functional]

### Data Integrity & Consistency

- [ ] CHK077 - Are data consistency requirements documented for vaccine group transitions (atomicity across status calculations)? [Non-Functional]
- [ ] CHK078 - Is the accuracy requirement for nomor epid sequence documented (zero loss, monotonically increasing)? [Non-Functional]
- [ ] CHK079 - Is the vaccine status recalculation trigger fully specified (on child age change, vaccine receipt, data correction)? [Non-Functional]

### Security

- [ ] CHK080 - Are access control requirements documented (who can create cases, export data, modify vaccine groups)? [Gap, Non-Functional]
- [ ] CHK081 - Are data privacy requirements for child information in PDF/Excel exports documented? [Gap, Non-Functional]

---

## Dependencies & Assumptions

*Are dependencies documented and assumptions validated?*

### External & System Dependencies

- [ ] CHK082 - Is the Kota Bontang geographic scope assumption validated and documented? [Dependency, Spec Assumptions]
- [ ] CHK083 - Is the "1710" area code permanence assumption stated? [Dependency, Spec Assumptions]
- [ ] CHK084 - Is the dependency on existing Early Warning System documented (version, API, schema)? [Dependency, Spec §FR-025]
- [ ] CHK085 - Is the dependency on Kemenkes school list (160 entries) fully specified (format, source, update frequency)? [Dependency, Spec §FR-010]
- [ ] CHK086 - Is the dependency on MR-01 form template documented (version, design reference)? [Dependency, Spec §FR-013]

### Data Dependencies

- [ ] CHK087 - Is the existing vaccine master data dependency documented (current count, structure)? [Dependency]
- [ ] CHK088 - Is the existing child & imunisasi data structure assumption documented? [Dependency]
- [ ] CHK089 - Is the JenisKasusEpidemiologi table schema assumption documented (kode_penyakit field exists)? [Dependency]

### Assumptions

- [ ] CHK090 - Is the assumption that vaccine status is always computed (not cached) documented and justified? [Assumption, Spec Assumptions]
- [ ] CHK091 - Is the assumption that nomor epid is immutable (never edited) documented? [Assumption, Spec §FR-005a]
- [ ] CHK092 - Is the assumption that child's jenis kelamin (gender) is always known documented? [Assumption]

---

## Ambiguities & Conflicts

*What requires clarification or could conflict?*

### Potential Ambiguities

- [ ] CHK093 - Is "prominent display" of vaccination status in profile defined with layout/styling specifics? [Ambiguity, Spec §SC-005]
- [ ] CHK094 - Is "sesuai standar pelaporan" MR-01 format defined in detail or left to designer interpretation? [Ambiguity, Spec §FR-013]
- [ ] CHK095 - Is the "poin prioritas intervensi" formula (single vs. dual kejar scoring) defined or left to implementation? [Ambiguity, Spec §FR-025]
- [ ] CHK096 - Is "custom lokasi penularan" input validation/categorization logic documented (free text or must select category)? [Ambiguity, Spec §FR-010]
- [ ] CHK097 - Is the exact vaccine list for each group defined or sourced from external standards? [Ambiguity, Spec §FR-017, research.md]

### Potential Conflicts

- [ ] CHK098 - Could address KTP and coordinate mismatch cause dashboard geographic analysis conflicts? [Conflict, Spec §US3, SC-004]
- [ ] CHK099 - Could ISL age range (7-12) conflict with other age-based system logic elsewhere? [Conflict]
- [ ] CHK100 - Could nomor epid format (variable prefix) conflict with existing case numbering systems? [Conflict]

---

## Traceability & Completeness Summary

- **Total checklist items**: 100
- **Spec sections covered**: FR-001 through FR-030, US1-US7, SC-001 through SC-009, Edge Cases, Assumptions
- **Specification gaps identified** (marked [Gap]): Gender-specific vaccines, EWS integration scoring, facility category completeness, kejar formula, access control, privacy requirements
- **Items marked [Ambiguity]**: Kejar scoring formula, "prominent" display definition, custom lokasi input logic, vaccine group sourcing

---

## Usage Notes

- Check items off as completed: `[x]`
- For items marked [Gap] or [Ambiguity], consider opening clarification questions or updating the spec
- Items are organized by quality dimension for easy focus
- All items trace to specific spec sections (§) for reference

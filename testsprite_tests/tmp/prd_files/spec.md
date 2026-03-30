# Feature Specification: Sirindu - Sistem Informasi Anak Rindu (Child Health Information System)

**Feature Branch**: `012-child-health-system`
**Created**: 2026-03-27
**Status**: Draft
**Input**: User description: "Create a product specification document for Sirindu (Sistem Informasi Anak Rindu) - a Laravel 12 web application for managing child health data that tracks children's growth metrics, immunization records, and calculates Z-score nutritional status indicators based on WHO standards."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Register and Track a Child's Basic Information (Priority: P1)

An admin at a Posyandu (integrated health post) registers a new child into the system by entering their identity data: family card number (No KK), national ID (NIK), full name, birth date and place, gender, blood type, parents' information, and geographic assignment (Kecamatan, Kelurahan, RT, Puskesmas, Posyandu). The system validates uniqueness of NIK and stores the child record for ongoing tracking.

**Why this priority**: Child registration is the foundation of all other features. No growth tracking, immunization, or analysis is possible without a registered child record.

**Independent Test**: Can be fully tested by registering a child via the form and verifying the record appears in the child data list, filtered by location.

**Acceptance Scenarios**:

1. **Given** the admin is on the child registration form, **When** they fill in all required fields with valid data and submit, **Then** the child record is saved and appears in the data list.
2. **Given** an admin enters a NIK that already exists, **When** they submit the form, **Then** the system rejects the submission with a clear duplicate error message.
3. **Given** an admin selects a Kecamatan, **When** they proceed to select Kelurahan, **Then** only Kelurahan belonging to that Kecamatan are shown (cascading dropdowns for Kecamatan > Kelurahan > RT, and Kecamatan > Puskesmas > Posyandu).
4. **Given** an admin views the child data list, **When** they search or filter by name, NIK, or location, **Then** matching records are displayed with correct pagination.

---

### User Story 2 - Record Periodic Growth Measurements (Priority: P1)

During a Posyandu visit, an admin records a child's growth measurements: visit date, age in months, measurement position (lying/standing), height, weight, mid-upper arm circumference (LLA), head circumference (LK), breastfeeding status, vitamin A, deworming, and developmental notes. The system automatically adjusts height based on measurement position and calculates the child's age in months.

**Why this priority**: Growth monitoring is the core function of the system. Periodic measurements drive Z-score calculations, analytics, and early warning alerts.

**Independent Test**: Can be tested by adding a periodic measurement for a registered child and verifying the measurement appears with correct calculated values.

**Acceptance Scenarios**:

1. **Given** a registered child, **When** the admin enters a new measurement with position "H" (lying) for a child under 24 months, **Then** the system stores the record and applies the +0.7 cm height adjustment for calculations.
2. **Given** a registered child older than 24 months measured standing, **When** the admin records the measurement with position "L", **Then** the system applies the -0.7 cm height adjustment.
3. **Given** a child with multiple periodic measurements, **When** the admin views the child's growth chart, **Then** the system displays height and weight trends over time with all recorded data points.
4. **Given** a measurement has been recorded, **When** the admin views the child's detail page, **Then** the Z-score nutritional status (IMT/U, BB/U, TB/U, BB/TB) is calculated and displayed according to WHO standards.

---

### User Story 3 - Calculate and Display Z-Score Nutritional Status (Priority: P1)

After each measurement entry, the system automatically calculates Z-score indicators based on WHO growth reference data: BMI-for-Age (IMT/U), Weight-for-Age (BB/U), Height-for-Age (TB/U), and Weight-for-Height (BB/TB). Each indicator is classified into a nutritional status category (e.g., normal, stunted, wasted, overweight, obese) using standard deviation cutoffs.

**Why this priority**: Z-score classification is the primary analytical output that determines a child's nutritional status and drives clinical decision-making.

**Independent Test**: Can be tested by entering known measurement values for a specific age and gender, then verifying the Z-score classification matches WHO reference tables.

**Acceptance Scenarios**:

1. **Given** a male child aged 12 months with specific weight and height values, **When** the Z-score is calculated, **Then** the TB/U result correctly classifies the child as "Normal", "Pendek (Stunted)", or "Sangat Pendek (Severely Stunted)" according to WHO reference data.
2. **Given** a female child aged 36 months with low weight-for-age, **When** the BB/U Z-score is calculated, **Then** the result correctly classifies as "Berat badan kurang (Underweight)" when between -3SD and -2SD.
3. **Given** a child with BMI above +2SD for their age, **When** the IMT/U Z-score is calculated, **Then** the system classifies the child as "Gizi lebih (Overweight)" or "Obesitas (Obese)" accordingly.
4. **Given** a child's Z-score changes between visits, **When** viewing the child's history, **Then** the nutritional status trend is visible across all measurement records.

---

### User Story 4 - Manage Immunization Records and Schedules (Priority: P1)

An admin records immunization events for a child, including vaccine type, dose number, administration date, batch number, location, administering officer, and any adverse events (KIPI). The system automatically generates an immunization schedule based on the child's age and the vaccine master data (minimum/maximum age ranges, intervals between doses), flagging vaccines as "not yet given" (belum), "completed" (sudah), or "overdue" (terlambat).

**Why this priority**: Immunization tracking is a core public health function. Ensuring complete and timely vaccination is critical for child health outcomes.

**Independent Test**: Can be tested by viewing a child's immunization schedule, recording a vaccination, and verifying the schedule updates with correct status flags.

**Acceptance Scenarios**:

1. **Given** a newly registered child, **When** the admin views the immunization schedule, **Then** all mandatory vaccines are listed with expected dates based on the child's birth date and vaccine age ranges.
2. **Given** a vaccine with status "belum" (not yet given), **When** the admin records the vaccination with date and batch number, **Then** the status updates to "sudah" (completed) and the next dose date is calculated.
3. **Given** a child has passed the maximum age for a vaccine dose, **When** the admin views the schedule, **Then** that vaccine is flagged as "terlambat" (overdue) with visual indication.
4. **Given** an adverse event occurs after vaccination, **When** the admin records the KIPI reaction, **Then** the reaction is stored with the immunization record and visible in the child's history.

---

### User Story 5 - View Analytics Dashboard and Geographic Visualizations (Priority: P2)

An admin or super-admin views the analytics dashboard showing aggregate statistics: total children, gender distribution, age distribution, geographic distribution by location hierarchy, immunization coverage rates, breastfeeding statistics, average growth trends, Z-score distribution, and monthly visit trends. Interactive maps display child counts, stunting prevalence, and immunization coverage by geographic area.

**Why this priority**: Analytics provide the situational awareness needed for program planning and resource allocation, but depend on data from P1 stories.

**Independent Test**: Can be tested by navigating to the analytics and map dashboards and verifying that charts, statistics, and map markers render with correct aggregate data.

**Acceptance Scenarios**:

1. **Given** the system has registered children with measurements, **When** the admin opens the analytics dashboard, **Then** summary widgets display correct counts for total children, growth records, and immunizations.
2. **Given** children are registered across multiple locations, **When** viewing geographic distribution charts, **Then** data is correctly grouped by Kecamatan, Kelurahan, Posyandu, and RT.
3. **Given** the admin opens the map dashboard, **When** data loads, **Then** location markers show child counts per area, with color-coded Z-score prevalence indicators.
4. **Given** immunization records exist, **When** viewing vaccine coverage analytics, **Then** each vaccine type shows the count and percentage of children who have completed that dose.
5. **Given** a filter is applied (e.g., specific Kelurahan or vaccine type), **When** the analytics update, **Then** all charts and statistics reflect only the filtered data.

---

### User Story 6 - Early Warning System for At-Risk Children (Priority: P2)

The system automatically identifies children who may be at risk based on a composite risk scoring algorithm. Risk factors include: no measurement data recorded (+30 points), no visit in the last 60 days (+15 points), severe stunting (+40 points), stunting (+25 points), severe wasting (+40 points), wasting (+25 points), severe underweight (+35 points), incomplete immunization (+5-10 points per missing vaccine), and overdue vaccines (+15 points per vaccine). Children are classified as Normal (0-20), Warning (21-50), or High Risk (51+).

**Why this priority**: The early warning system is a critical intervention tool but requires sufficient measurement and immunization data from P1 stories to be meaningful.

**Independent Test**: Can be tested by creating children with known risk factors and verifying their risk scores and alert classifications match expected values.

**Acceptance Scenarios**:

1. **Given** a child has no measurement data recorded, **When** the early warning system runs, **Then** the child receives a risk score of at least 30 with an alert "Belum pernah dilakukan pengukuran".
2. **Given** a child's latest TB/U Z-score is below -3SD, **When** the early warning system evaluates, **Then** the child is flagged as "SANGAT PENDEK (Severely Stunted)" with +40 risk points and classified as High Risk.
3. **Given** a child is missing 3 mandatory vaccines past their due date, **When** the system evaluates, **Then** the child receives risk points for incomplete immunization and the specific missing vaccines are listed.
4. **Given** the admin views the early warning dashboard, **When** filtering by location, **Then** only children from that area are shown, sorted by risk score (highest first).

---

### User Story 7 - Disease Surveillance and Epidemiology Case Management (Priority: P2)

Surveillance officers at Puskesmas or hospitals register and manage PD3I (Penyakit Dapat Dicegah Dengan Imunisasi) disease cases. Each case captures comprehensive data across 10+ categories: patient identity, reporter identity, case data with ICD-10 codes, clinical symptoms (17+ symptom types), complications, treatment, laboratory results, hospitalization management, final outcome, and contact investigation. Cases are tracked through statuses: suspected, probable, confirmed, or discarded.

**Why this priority**: Epidemiology surveillance is a separate but important module that supports outbreak detection and public health response.

**Independent Test**: Can be tested by creating a surveillance case, progressing it through status changes, and verifying all data categories are captured and displayed correctly.

**Acceptance Scenarios**:

1. **Given** a surveillance officer at a Puskesmas, **When** they create a new case with patient identity and disease type, **Then** the case is saved with status "suspected" and assigned to their facility.
2. **Given** an existing case, **When** laboratory results confirm the disease, **Then** the officer updates the case status to "confirmed" and the lab result fields reflect the findings.
3. **Given** multiple surveillance cases exist, **When** viewing the epidemiology dashboard, **Then** trend charts show monthly case counts, disease distribution, status breakdown, and geographic spread.
4. **Given** cases have geographic coordinates, **When** viewing the epidemiology map, **Then** case locations are plotted with clustering by Kelurahan, Kecamatan, and RT.
5. **Given** a surveillance officer is scoped to a specific Puskesmas, **When** they view cases, **Then** they only see cases from their assigned facility.

---

### User Story 8 - Export Data to Excel and PDF (Priority: P3)

Admins export child data, immunization records, and vaccine needs projections as Excel files, with flexible filters (date range, location hierarchy, vaccine type, immunization status). Epidemiology cases can be exported individually as PDF reports or in bulk as Excel files.

**Why this priority**: Export is a reporting utility that enhances existing features but is not required for core data entry or analysis.

**Independent Test**: Can be tested by applying filters and downloading an export file, then verifying the file contains the correct filtered data in the expected format.

**Acceptance Scenarios**:

1. **Given** the admin selects a date range and Kelurahan filter, **When** they export child data, **Then** an Excel file downloads containing only children matching the filter criteria.
2. **Given** the admin filters immunization data by vaccine type and status "terlambat", **When** they export, **Then** the Excel file contains only overdue immunization records for that vaccine.
3. **Given** the early warning system has identified vaccine needs, **When** the admin exports vaccine needs, **Then** the Excel file includes child details, missing vaccines, and location aggregations.
4. **Given** a surveillance case exists, **When** the admin exports it as PDF, **Then** a formatted report is generated with all case categories and data fields.

---

### User Story 9 - User Management and Role-Based Access Control (Priority: P3)

Super-admins manage user accounts and assign roles: superadmin (full access), imunisasi_faskes (immunization module scoped to facility), surveilans_puskesmas (surveillance at Puskesmas), and surveilans_rs (surveillance at hospital). Each role determines which modules are accessible and what data scope applies based on the user's assigned healthcare facility.

**Why this priority**: User management is an administrative function that supports system operation but is not end-user facing.

**Independent Test**: Can be tested by creating users with different roles and verifying each can only access their permitted modules and data scope.

**Acceptance Scenarios**:

1. **Given** a super-admin creates a new user with role "imunisasi_faskes" assigned to a specific Puskesmas, **When** that user logs in, **Then** they can only access the immunization module and see data scoped to their Puskesmas.
2. **Given** a user with role "surveilans_puskesmas", **When** they attempt to access the child data management module, **Then** they are denied access with a 403 response.
3. **Given** a super-admin, **When** they view any module, **Then** they see all data across all facilities without scope restrictions.
4. **Given** a user with role "surveilans_rs" at a specific hospital, **When** they create a surveillance case, **Then** the case is automatically associated with their hospital.

---

### User Story 10 - Public API Access for Child Data (Priority: P3)

External systems access child data through public API endpoints that return JSON: all children with measurements, basic child information, individual child data (basic and complete), and geographic reference data (Kecamatan, Kelurahan, Puskesmas, Posyandu, RT).

**Why this priority**: API access enables integration with external systems but is not required for the primary web application functionality.

**Independent Test**: Can be tested by calling each API endpoint and verifying the JSON response contains correct data structures and values.

**Acceptance Scenarios**:

1. **Given** the API endpoint `/api/allDataAnak` is called, **When** the response is returned, **Then** it contains all children with their measurement data in JSON format.
2. **Given** a valid child ID, **When** calling `/api/showAllDataAnak/{id}`, **Then** the response includes the child's basic information and all periodic measurements.
3. **Given** the geographic endpoint `/api/getKelurahan/{id_kecamatan}` is called, **Then** only Kelurahan belonging to that Kecamatan are returned.

---

### Edge Cases

- What happens when a child's birth date is in the future? The system should reject registration with a validation error.
- How does the system handle a Z-score calculation when WHO reference data is missing for a specific age/gender combination? The system should display "Data referensi tidak tersedia" and skip the calculation.
- What happens when an admin tries to delete a child who has associated periodic data and immunization records? The system should confirm the cascading impact before proceeding.
- How does the system handle concurrent edits to the same child record by two different admins? The most recent save wins, with standard Laravel timestamp-based conflict detection.
- What happens when a vaccine in the master data is deactivated while children have pending doses? The deactivated vaccine should no longer appear in new schedules but existing records are preserved.
- How does the system handle measurements for children older than 60 months? Different Z-score thresholds apply for children >60 months (e.g., IMT/U wasting threshold changes).
- What happens when a surveillance case has incomplete mandatory fields? The system allows partial saves for cases since data arrives incrementally from field investigations.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST allow admins to register child records with identity information (NIK, name, birth date, gender, parents' data) and geographic assignment (Kecamatan, Kelurahan, RT, Puskesmas, Posyandu).
- **FR-002**: System MUST enforce unique NIK per child and validate all required fields during registration and updates.
- **FR-003**: System MUST provide cascading geographic dropdowns (Kecamatan > Kelurahan > RT; Kecamatan > Puskesmas > Posyandu) that dynamically load via AJAX.
- **FR-004**: System MUST allow recording of periodic growth measurements per child: visit date, age (months), measurement position (lying/standing), height, weight, mid-upper arm circumference, head circumference, breastfeeding status, vitamin A, deworming, and developmental notes.
- **FR-005**: System MUST automatically adjust height measurements based on position: +0.7 cm for children under 24 months measured lying, -0.7 cm for children 24+ months measured standing.
- **FR-006**: System MUST calculate BMI as (10000 * weight / height^2) and compute Z-scores for four WHO indicators: IMT/U, BB/U, TB/U, and BB/TB using stored WHO reference data.
- **FR-007**: System MUST classify each Z-score indicator into nutritional status categories using standard deviation cutoffs (e.g., <-3SD = severely stunted, -3SD to -2SD = stunted, etc.) with different thresholds for children under and over 60 months.
- **FR-008**: System MUST maintain a master data registry of vaccine types with code, name, category (Wajib/Tambahan/Booster), age ranges, dose intervals, and active/inactive status with soft delete support.
- **FR-009**: System MUST auto-generate immunization schedules per child based on birth date and vaccine master data, tracking each vaccine dose as "belum" (not yet), "sudah" (completed), or "terlambat" (overdue).
- **FR-010**: System MUST allow recording of immunization events with vaccine type, dose number, date, batch number, location, officer, adverse events (KIPI), and notes.
- **FR-011**: System MUST display analytics dashboards showing aggregate statistics: child counts, gender/age distribution, geographic distribution, immunization coverage, breastfeeding rates, growth trends, Z-score distribution, and monthly visit trends.
- **FR-012**: System MUST provide interactive map dashboards showing child distribution, Z-score prevalence (stunting, wasting, overweight), and immunization coverage by geographic area.
- **FR-013**: System MUST implement an early warning system that calculates risk scores (0-100+) based on weighted factors: missing measurements, infrequent visits, stunting severity, wasting severity, underweight status, and immunization gaps.
- **FR-014**: System MUST classify children into risk levels: Normal (0-20), Warning (21-50), and High Risk (51+) with specific alerts describing each risk factor.
- **FR-015**: System MUST project vaccine needs for 1-month, 6-month, and 12-month windows, aggregated by location.
- **FR-016**: System MUST support comprehensive disease surveillance case management with 10+ data categories: patient identity, reporter identity, case data (ICD-10), clinical symptoms, complications, treatment, laboratory results, hospitalization, final outcome, and contact investigation.
- **FR-017**: System MUST track surveillance case statuses: suspected, probable, confirmed, and discarded, with final outcomes: recovered, deceased, in treatment, transferred, or unknown.
- **FR-018**: System MUST provide epidemiology dashboards with case trends, disease distribution, status breakdown, and geographic case mapping with location clustering.
- **FR-019**: System MUST export child data, immunization records, and vaccine needs as Excel files with flexible filtering by date range, location, vaccine type, and status.
- **FR-020**: System MUST export individual surveillance cases as formatted PDF reports.
- **FR-021**: System MUST enforce role-based access control with four roles: superadmin (full access), imunisasi_faskes (immunization module scoped to facility), surveilans_puskesmas (surveillance at Puskesmas), and surveilans_rs (surveillance at hospital).
- **FR-022**: System MUST scope data visibility per user's assigned healthcare facility (faskes_type + id_faskes), with superadmin bypassing all scope restrictions.
- **FR-023**: System MUST provide public API endpoints returning JSON for child data (basic and complete), individual child records, and geographic reference data.
- **FR-024**: System MUST maintain a master data registry of disease types (PD3I) with code, name, category, description, and active/inactive status with soft delete and restore support.
- **FR-025**: System MUST support Tetanus Neonatorum-specific surveillance fields including maternal health data, birth conditions, sanitation, and ANC visit history.
- **FR-026**: System MUST display child growth charts showing height and weight trends across all recorded measurements.
- **FR-027**: System MUST encode entity IDs in URLs using hash-based obfuscation to prevent sequential ID enumeration.

### Key Entities

- **Anak (Child)**: The primary entity representing a registered child. Contains identity data (NIK, name, birth date, gender), parent information, geographic assignment (Kecamatan, Kelurahan, RT, Puskesmas, Posyandu), and status. Each child has many periodic measurements and immunization records.
- **DataAnak (Growth Measurement)**: A periodic measurement record linked to a child. Contains visit date, age in months, measurement position, height, weight, circumferences (arm and head), breastfeeding status, vitamin A, deworming, and developmental notes. Drives Z-score calculation.
- **Imunisasi (Immunization Record)**: A vaccination event linked to a child and vaccine type. Contains dose number, administration date, batch number, location, officer, adverse events, and status (belum/sudah/terlambat).
- **JenisVaksin (Vaccine Type)**: Master data defining available vaccines with code, name, category, age ranges for administration, dose intervals, and active status. Supports soft delete.
- **ZScore (WHO Reference Data)**: Lookup table containing WHO growth standard values organized by indicator type, age/height reference, gender, and standard deviation cutoffs (-3SD through +3SD).
- **SurveillanceCase (Disease Case)**: A disease surveillance record with 200+ fields across patient identity, symptoms, complications, treatment, lab results, hospitalization, outcomes, and contact investigation. Linked to disease type and geographic location.
- **JenisKasusEpidemiologi (Disease Type)**: Master data for PD3I diseases with code, name, category, and active status. Supports soft delete.
- **Geographic Hierarchy**: Five entities forming the location structure - Kecamatan (sub-district) > Kelurahan (village) > RT (neighborhood), and Puskesmas (health center) > Posyandu (health post). RT is linked to both Kelurahan and Posyandu.
- **User**: System user with identity, credentials, role assignment, facility type, and geographic/facility scoping for data access control.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Admins can register a new child and record their first growth measurement in under 5 minutes.
- **SC-002**: Z-score nutritional status is calculated and displayed within 2 seconds after a measurement is saved, with classification accuracy matching WHO reference tables for all four indicators.
- **SC-003**: The immunization schedule for a child is automatically generated and displays correct status flags (belum/sudah/terlambat) with 100% accuracy based on the child's age and vaccination history.
- **SC-004**: The early warning system identifies at least 95% of children with severe malnutrition (Z-score < -3SD on any indicator) as "High Risk".
- **SC-005**: Analytics dashboards load and render all charts within 5 seconds for datasets of up to 50,000 children.
- **SC-006**: Data exports (Excel) complete within 30 seconds for datasets of up to 10,000 records with applied filters.
- **SC-007**: Role-based access control prevents 100% of unauthorized access attempts, with facility-scoped users seeing only data from their assigned facility.
- **SC-008**: Surveillance cases can be created with all mandatory identity and case data fields in under 10 minutes.
- **SC-009**: The epidemiology map displays case locations with correct geographic clustering within 5 seconds of loading.
- **SC-010**: Vaccine needs projections accurately predict required doses for the next 1, 6, and 12 months based on each child's current vaccination status and age.
- **SC-011**: The system supports at least 50 concurrent users without performance degradation in core data entry and viewing operations.
- **SC-012**: Geographic cascading dropdowns load dependent options within 1 second of parent selection.

## Assumptions

- The system is used within the Indonesian public health infrastructure, following standard geographic hierarchies (Kecamatan > Kelurahan > RT) and healthcare facility structures (Puskesmas > Posyandu).
- WHO Z-score reference data (growth standards) is pre-loaded into the database and covers children aged 0 to 60+ months for both genders.
- Vaccine master data follows the Indonesian national immunization program schedule, with categories aligned to government mandates (Wajib/Tambahan/Booster).
- Users access the system via modern web browsers on desktop or laptop computers at healthcare facilities with internet connectivity.
- The PD3I surveillance form structure follows Indonesian Ministry of Health standard epidemiology investigation forms.
- Data entry is performed by trained healthcare staff (admins) who understand clinical measurement procedures and health terminology.
- ICD-10 codes used in surveillance cases follow the international standard classification.
- The geographic reference data (Kecamatan, Kelurahan, RT, Puskesmas, Posyandu) is pre-populated and maintained by super-admins.

## Dependencies

- WHO growth standard reference data must be available in the z_score database table for all Z-score calculations.
- Geographic reference data (Kecamatan, Kelurahan, RT, Puskesmas, Posyandu, Rumah Sakit) must be seeded before child registration or case entry.
- Vaccine master data must be configured before immunization tracking can function.
- Disease type master data must be configured before surveillance cases can be created.
- User accounts with appropriate roles must be provisioned before facility-scoped staff can access their modules.

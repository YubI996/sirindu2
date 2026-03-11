# Feature Specification: Export Data Imunisasi Anak

**Feature Branch**: `011-export-imunisasi`
**Created**: 2026-03-10
**Status**: Draft
**Input**: User description: "buat modul export data imunisasi anak dalam bentuk csv, user bisa filter data berdasarkan bulan, kelurahan, dan jenis antigen"

## Clarifications

### Session 2026-03-10

- Q: Apakah filter kelurahan dan antigen single-select atau multi-select? → A: Single-select — satu kelurahan dan satu antigen per export.
- Q: Apakah perlu filter status imunisasi (belum/sudah/terlambat)? → A: Ya, tambahkan sebagai filter opsional keempat.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Export Data Imunisasi dengan Filter (Priority: P1)

Sebagai admin Dinkes, saya ingin mengekspor data imunisasi anak ke file CSV dengan memilih filter bulan, kelurahan, jenis antigen, dan status imunisasi, agar saya bisa mengolah data untuk pelaporan bulanan ke dinas kesehatan provinsi.

**Why this priority**: Ini adalah inti dari fitur — kemampuan export dengan filter. Tanpa ini, fitur tidak memiliki nilai.

**Independent Test**: Dapat diuji dengan membuka halaman export, memilih filter (misal: Januari 2026, Kelurahan Bontang Lestari, vaksin BCG, status "sudah"), klik tombol export, dan memverifikasi file CSV yang diunduh berisi data sesuai filter.

**Acceptance Scenarios**:

1. **Given** admin berada di halaman export imunisasi, **When** admin memilih bulan "Januari 2026", kelurahan "Bontang Lestari", antigen "BCG", dan status "sudah" lalu klik Export, **Then** sistem mengunduh file CSV yang hanya berisi data imunisasi BCG berstatus sudah di Kelurahan Bontang Lestari pada Januari 2026.
2. **Given** admin berada di halaman export imunisasi, **When** admin memilih bulan "Februari 2026" tanpa memilih kelurahan, antigen, dan status, **Then** sistem mengunduh file CSV berisi semua data imunisasi pada Februari 2026 dari semua kelurahan, semua jenis antigen, dan semua status.
3. **Given** admin berada di halaman export imunisasi, **When** admin tidak memilih filter apapun dan klik Export, **Then** sistem mengunduh file CSV berisi semua data imunisasi yang tersedia.
4. **Given** admin berada di halaman export imunisasi, **When** admin memilih status "terlambat" tanpa filter lain, **Then** sistem mengunduh file CSV berisi semua data imunisasi yang berstatus terlambat.

---

### User Story 2 - Preview Data Sebelum Export (Priority: P2)

Sebagai admin, saya ingin melihat preview data di tabel sebelum mengekspor, agar saya bisa memastikan filter yang dipilih sudah benar dan data yang akan diekspor sesuai kebutuhan.

**Why this priority**: Mencegah admin mengunduh file yang salah dan harus mengulang proses. Meningkatkan kepercayaan pengguna terhadap data yang diekspor.

**Independent Test**: Dapat diuji dengan memilih filter lalu melihat tabel preview menampilkan data yang sesuai, termasuk jumlah total record.

**Acceptance Scenarios**:

1. **Given** admin memilih filter bulan dan kelurahan, **When** filter diterapkan, **Then** tabel preview menampilkan data imunisasi yang sesuai filter beserta total jumlah record.
2. **Given** admin melihat preview data, **When** tidak ada data yang cocok dengan filter, **Then** tabel menampilkan pesan "Tidak ada data yang sesuai filter" dan tombol export dinonaktifkan.

---

### User Story 3 - Informasi Ringkasan Filter (Priority: P3)

Sebagai admin, saya ingin melihat ringkasan filter yang sedang aktif di atas tabel, agar saya tahu konteks data yang sedang ditampilkan.

**Why this priority**: Fitur pendukung yang meningkatkan kejelasan konteks, bukan fungsionalitas inti.

**Independent Test**: Dapat diuji dengan memilih filter dan memverifikasi badge/tag ringkasan muncul di atas tabel menunjukkan filter aktif.

**Acceptance Scenarios**:

1. **Given** admin memilih filter bulan "Maret 2026", antigen "Polio 1", dan status "sudah", **When** filter diterapkan, **Then** muncul ringkasan "Bulan: Maret 2026 | Antigen: Polio 1 | Status: Sudah" di atas tabel preview.

---

### Edge Cases

- Apa yang terjadi jika data imunisasi kosong untuk filter yang dipilih? → Tampilkan pesan kosong, nonaktifkan tombol export.
- Apa yang terjadi jika admin memilih kelurahan yang tidak memiliki data anak? → Tampilkan tabel kosong dengan pesan informatif.
- Bagaimana sistem menangani jenis antigen yang sudah dinonaktifkan (soft deleted)? → Tetap tampilkan data imunisasi historis yang menggunakan antigen tersebut, karena data adalah catatan historis.
- Apa yang terjadi jika jumlah data sangat besar (ribuan record)? → Preview menampilkan data dengan pagination, export tetap mengunduh semua data sesuai filter.
- Bagaimana format tanggal di file CSV? → Gunakan format standar Indonesia: DD/MM/YYYY.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Sistem HARUS menyediakan halaman export data imunisasi yang dapat diakses dari sidebar menu.
- **FR-002**: Sistem HARUS menyediakan filter berdasarkan bulan (bulan dan tahun) untuk menyaring data berdasarkan `tanggal_pemberian`.
- **FR-003**: Sistem HARUS menyediakan filter berdasarkan kelurahan menggunakan dropdown single-select dari daftar kelurahan yang tersedia.
- **FR-004**: Sistem HARUS menyediakan filter berdasarkan jenis antigen (vaksin) menggunakan dropdown single-select dari daftar jenis vaksin aktif.
- **FR-005**: Sistem HARUS menyediakan filter berdasarkan status imunisasi (belum, sudah, terlambat) menggunakan dropdown single-select.
- **FR-006**: Semua filter HARUS bersifat opsional — admin dapat memilih satu, sebagian, atau tanpa filter sama sekali.
- **FR-007**: Sistem HARUS menghasilkan file CSV dengan encoding UTF-8 yang dapat dibuka di Microsoft Excel dan Google Sheets.
- **FR-008**: File CSV HARUS memiliki nama file deskriptif yang mencerminkan filter, contoh: `imunisasi_jan-2026_bontang-lestari_bcg.csv`.
- **FR-009**: Kolom CSV HARUS mencakup minimal: Nama Anak, NIK, Jenis Kelamin, Tanggal Lahir, Kelurahan, Kecamatan, Posyandu, Jenis Vaksin, Dosis, Tanggal Pemberian, Status, Lokasi Pemberian.
- **FR-010**: Sistem HARUS menampilkan preview data di tabel sebelum export dilakukan.
- **FR-011**: Tombol export HARUS dinonaktifkan jika tidak ada data yang sesuai filter.
- **FR-012**: Halaman export HARUS hanya dapat diakses oleh user dengan role super-admin dan admin (bukan faskes surveilans).

### Key Entities

- **Imunisasi**: Catatan pemberian imunisasi — terhubung ke Anak (penerima), JenisVaksin (antigen), dan User (petugas). Memiliki field `status` (belum/sudah/terlambat) yang digunakan sebagai filter.
- **Anak**: Data anak penerima imunisasi — memiliki relasi ke Kelurahan, Kecamatan, Posyandu untuk informasi geografis.
- **JenisVaksin (Antigen)**: Master data jenis vaksin — digunakan sebagai filter dan label kolom di CSV.
- **Kelurahan**: Wilayah administratif — digunakan sebagai filter geografis.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Admin dapat mengekspor data imunisasi ke CSV dalam waktu kurang dari 5 detik untuk dataset hingga 5.000 record.
- **SC-002**: File CSV yang dihasilkan dapat dibuka tanpa error di Microsoft Excel dan Google Sheets.
- **SC-003**: 100% data di file CSV cocok dengan data yang ditampilkan di preview tabel sesuai filter yang dipilih.
- **SC-004**: Admin dapat menyelesaikan alur filter → preview → export dalam waktu kurang dari 1 menit.
- **SC-005**: Filter kombinasi (bulan + kelurahan + antigen + status) menghasilkan data yang akurat — tidak ada data yang salah masuk atau terlewat.

## Assumptions

- Filter bulan menggunakan `tanggal_pemberian` (tanggal pemberian vaksin), bukan `created_at`.
- Dropdown kelurahan menampilkan semua kelurahan yang tersedia di sistem, bukan hanya yang memiliki data imunisasi.
- Dropdown antigen menampilkan semua jenis vaksin aktif (tidak termasuk yang soft deleted), namun data historis dengan antigen nonaktif tetap muncul di hasil export jika sesuai filter.
- Format CSV menggunakan separator koma (`,`) sesuai standar internasional.
- Halaman export ditambahkan di sidebar di bawah section "Anak" karena konteksnya adalah data imunisasi anak.
- Tidak ada batasan jumlah record yang bisa diekspor — semua data sesuai filter akan diunduh.
- Semua filter menggunakan single-select dropdown (satu pilihan per filter per export).

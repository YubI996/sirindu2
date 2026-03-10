# Feature Specification: Manajemen Master Data Imunisasi & Penyakit Surveilans

**Feature Branch**: `003-manage-master-data`
**Created**: 2026-03-06
**Status**: Draft
**Input**: User description: "Tambah fitur tambah imunisasi dan fitur tambah penyakit surveilans"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - CRUD Jenis Vaksin / Imunisasi (Priority: P1)

Sebagai admin, saya ingin dapat menambah, melihat, mengedit, dan menghapus data jenis vaksin (master data imunisasi) melalui halaman khusus, agar data vaksin yang tersedia di sistem selalu up-to-date tanpa harus mengubah database secara langsung.

**Why this priority**: Jenis vaksin adalah master data inti yang digunakan saat pencatatan imunisasi anak. Tanpa kemampuan mengelola data ini, pengguna tidak bisa menambah vaksin baru atau menonaktifkan vaksin yang sudah tidak digunakan.

**Independent Test**: Dapat diuji secara mandiri dengan membuka halaman master data vaksin, menambah vaksin baru, mengedit nama/kategori, dan menonaktifkan vaksin — tanpa memerlukan fitur lain.

**Acceptance Scenarios**:

1. **Given** admin berada di halaman master data vaksin, **When** admin mengklik tombol "Tambah Vaksin" dan mengisi form (kode, nama, kategori, usia pemberian min/max, interval hari, keterangan), **Then** data vaksin baru tersimpan dan muncul di daftar.
2. **Given** admin melihat daftar vaksin, **When** admin mengklik tombol edit pada salah satu vaksin dan mengubah data, **Then** perubahan tersimpan dan daftar ter-update.
3. **Given** admin ingin menonaktifkan vaksin, **When** admin mengubah status vaksin menjadi tidak aktif, **Then** vaksin tersebut tidak lagi muncul di dropdown saat pencatatan imunisasi anak, tetapi tetap ada di master data.
4. **Given** admin mencoba menambah vaksin dengan kode yang sudah ada, **When** form di-submit, **Then** sistem menampilkan pesan error bahwa kode sudah digunakan.

---

### User Story 2 - CRUD Jenis Penyakit Surveilans (Priority: P1)

Sebagai admin, saya ingin dapat menambah, melihat, mengedit, dan menghapus data jenis penyakit surveilans epidemiologi, agar daftar penyakit yang bisa dilaporkan dalam modul epidemiologi selalu sesuai kebutuhan.

**Why this priority**: Jenis penyakit adalah master data yang menentukan klasifikasi kasus surveilans. Admin perlu bisa menambah penyakit baru (misalnya saat ada wabah baru) atau menonaktifkan penyakit yang tidak lagi dipantau.

**Independent Test**: Dapat diuji secara mandiri dengan membuka halaman master data penyakit, menambah penyakit baru, mengedit deskripsi, dan mengubah status aktif — tanpa memerlukan fitur lain.

**Acceptance Scenarios**:

1. **Given** admin berada di halaman master data penyakit, **When** admin mengklik tombol "Tambah Penyakit" dan mengisi form (kode penyakit, nama penyakit, kategori, deskripsi), **Then** data penyakit baru tersimpan dan muncul di daftar.
2. **Given** admin melihat daftar penyakit, **When** admin mengklik edit dan mengubah data, **Then** perubahan tersimpan dan daftar ter-update.
3. **Given** admin menonaktifkan suatu penyakit, **When** petugas membuat laporan kasus baru, **Then** penyakit yang tidak aktif tidak muncul di dropdown pilihan jenis kasus.
4. **Given** ada kasus surveilans yang menggunakan suatu penyakit, **When** admin mencoba menghapus penyakit tersebut, **Then** sistem melakukan soft-delete dan menampilkan pesan bahwa penyakit di-soft-delete karena masih digunakan.

---

### User Story 3 - Navigasi Melekat di Modul Masing-Masing (Priority: P2)

Sebagai Super Admin (Dinkes), saya ingin mengakses halaman master data jenis vaksin dan jenis penyakit langsung dari menu modul terkait di sidebar, agar pengelolaan master data terasa natural dan tidak perlu mencari menu terpisah.

**Why this priority**: Navigasi yang kontekstual (melekat di modul terkait) memudahkan admin menemukan fitur tanpa harus mengingat lokasi menu terpisah.

**Independent Test**: Dapat diuji dengan login sebagai Super Admin (Dinkes) dan memverifikasi sub-menu muncul di dropdown modul terkait.

**Acceptance Scenarios**:

1. **Given** Super Admin (Dinkes) sudah login, **When** admin melihat sidebar dropdown "Data", **Then** terdapat sub-menu "Jenis Vaksin" di bawah "Data Anak".
2. **Given** Super Admin (Dinkes) sudah login, **When** admin melihat sidebar dropdown "Epidemiologi", **Then** terdapat sub-menu "Jenis Penyakit" di bawah item menu lainnya.
3. **Given** pengguna login sebagai Faskes Surveilans atau admin biasa, **When** pengguna melihat sidebar, **Then** sub-menu "Jenis Vaksin" dan "Jenis Penyakit" TIDAK muncul.

---

### Edge Cases

- Apa yang terjadi jika admin menghapus jenis vaksin yang sudah dipakai di data imunisasi anak? Sistem melakukan soft-delete (set `deleted_at`, tetap tersimpan di database) dan menampilkan pesan konfirmasi bahwa data di-soft-delete karena masih digunakan. Jika tidak ada child records, hard-delete dilakukan.
- Apa yang terjadi jika admin menonaktifkan semua penyakit? Form surveilans tetap bisa dibuka tetapi dropdown kosong dengan pesan informatif.
- Bagaimana jika dua admin mengedit data yang sama secara bersamaan? Perubahan terakhir yang disimpan akan menang (last-write-wins), sesuai perilaku standar Laravel.
- Apa yang terjadi jika kode vaksin/penyakit mengandung karakter spesial? Sistem hanya menerima huruf, angka, dan underscore.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Sistem HARUS menyediakan halaman daftar jenis vaksin dengan fitur pencarian dan pengurutan.
- **FR-002**: Sistem HARUS memungkinkan admin menambah jenis vaksin baru dengan field: kode, nama, kategori (enum: Wajib, Tambahan, Booster), usia pemberian minimum, usia pemberian maksimum, interval hari, dan keterangan.
- **FR-003**: Sistem HARUS memungkinkan admin mengedit data jenis vaksin yang sudah ada.
- **FR-004**: Sistem HARUS memungkinkan admin mengubah status aktif/tidak aktif jenis vaksin.
- **FR-005**: Sistem HARUS melakukan soft-delete (SoftDeletes) pada jenis vaksin yang masih direferensikan oleh data imunisasi anak, dan hard-delete jika tidak ada referensi.
- **FR-006**: Sistem HARUS memvalidasi keunikan kode vaksin saat penambahan dan pengeditan.
- **FR-007**: Sistem HARUS menyediakan halaman daftar jenis penyakit surveilans dengan fitur pencarian dan pengurutan.
- **FR-008**: Sistem HARUS memungkinkan admin menambah jenis penyakit baru dengan field: kode penyakit, nama penyakit, kategori, dan deskripsi.
- **FR-009**: Sistem HARUS memungkinkan admin mengedit data jenis penyakit yang sudah ada.
- **FR-010**: Sistem HARUS memungkinkan admin mengubah status aktif/tidak aktif jenis penyakit.
- **FR-011**: Sistem HARUS melakukan soft-delete (SoftDeletes) pada jenis penyakit yang masih direferensikan oleh data kasus surveilans, dan hard-delete jika tidak ada referensi.
- **FR-012**: Sistem HARUS memvalidasi keunikan kode penyakit saat penambahan dan pengeditan.
- **FR-012a**: Soft-deleted records HARUS tetap tampil di daftar master data dengan badge "Dihapus", tidak dapat diedit, dan memiliki tombol "Restore" untuk mengembalikan data.
- **FR-012b**: Soft-deleted vaksin/penyakit TIDAK boleh muncul di dropdown form imunisasi/surveilans (sama seperti status tidak aktif).
- **FR-013**: Hanya pengguna dengan role Super Admin (Dinkes) yang dapat mengakses halaman master data jenis vaksin dan jenis penyakit. Faskes Surveilans dan admin biasa TIDAK memiliki akses.
- **FR-014**: Sub-menu "Jenis Vaksin" HARUS muncul di bawah dropdown "Data" (setelah "Data Anak") pada sidebar Super Admin (Dinkes).
- **FR-015**: Sub-menu "Jenis Penyakit" HARUS muncul di bawah dropdown "Epidemiologi" (setelah item menu lainnya) pada sidebar Super Admin (Dinkes).

### Key Entities

- **Jenis Vaksin (JenisVaksin)**: Master data vaksin yang tersedia untuk imunisasi. Atribut utama: kode, nama, kategori (enum: Wajib/Tambahan/Booster), rentang usia pemberian, interval dosis, status aktif. Mendukung SoftDeletes. Berelasi ke data Imunisasi anak.
- **Jenis Penyakit Epidemiologi (JenisKasusEpidemiologi)**: Master data penyakit yang dapat dilaporkan dalam surveilans. Atribut utama: kode penyakit, nama penyakit, kategori (enum: PD3I/menular_langsung/vector_borne/zoonosis/lainnya), deskripsi, status aktif. Mendukung SoftDeletes. Berelasi ke data kasus surveilans.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Admin dapat menambah jenis vaksin baru dalam waktu kurang dari 1 menit.
- **SC-002**: Admin dapat menambah jenis penyakit surveilans baru dalam waktu kurang dari 1 menit.
- **SC-003**: Perubahan status aktif/tidak aktif pada master data langsung terrefleksi di form terkait (imunisasi/surveilans) tanpa perlu tindakan tambahan.
- **SC-004**: Sistem menjaga 100% integritas referensial — data master yang masih digunakan di-soft-delete (bukan hard-delete), dan dapat di-restore.
- **SC-005**: Halaman jenis vaksin dapat diakses dari dropdown "Data" dan halaman jenis penyakit dari dropdown "Epidemiologi" di sidebar, masing-masing dalam 2 klik.

## Clarifications

### Session 2026-03-09

- Q: How should the vaksin `kategori` field be entered? → A: Fixed dropdown/enum (same pattern as penyakit kategori). New categories require a migration.
- Q: What happens when admin deletes a vaksin/penyakit with child records? → A: Hard-delete if no child records exist; soft-delete (SoftDeletes trait, `deleted_at` column) if child records exist.
- Q: How should soft-deleted records appear on the master data page? → A: Visible in list with "Dihapus" badge, not editable, with option to restore.
- Q: What are the fixed enum values for vaksin kategori? → A: Wajib, Tambahan, Booster.

## Assumptions

- Model `JenisVaksin` dan `JenisKasusEpidemiologi` beserta tabel database-nya sudah ada di sistem.
- Halaman master data mengikuti pattern UI yang sama dengan halaman lain di sistem (Bootstrap 5, DataTables, SweetAlert).
- Penghapusan menggunakan strategi hybrid: hard-delete jika tidak ada child records, soft-delete (Laravel SoftDeletes trait dengan kolom `deleted_at`) jika masih ada child records yang mereferensikan data tersebut.
- Fitur ini hanya untuk role Super Admin (Dinkes), tidak untuk Faskes Surveilans maupun admin biasa/faskes imunisasi.
- Kategori vaksin dan penyakit menggunakan fixed enum/dropdown. Penambahan kategori baru memerlukan perubahan migrasi/kode.

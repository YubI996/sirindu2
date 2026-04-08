# Feature Specification: Perbaikan Modul Import PD3I

**Feature Branch**: `001-fix-pd3i-import`  
**Created**: 2026-04-07  
**Status**: Draft  
**Input**: User description: "perbaiki modul import pd3i hingga berjalan maksimal"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Import File Excel PD3I Berhasil (Priority: P1)

Sebagai petugas Dinkes (superadmin), saya ingin mengunggah file Excel format PD3I resmi ke sistem, sehingga ratusan data kasus penyakit dapat masuk ke database secara otomatis tanpa harus menginput satu per satu.

**Why this priority**: Ini adalah inti dari fitur — tanpa import yang berhasil, seluruh modul tidak memberikan nilai. Petugas Dinkes menangani file dengan ratusan hingga ribuan baris data setiap periode pelaporan.

**Independent Test**: Dapat diuji sepenuhnya dengan mengunggah file pd3i.xlsx yang valid dan memverifikasi bahwa data muncul di daftar kasus epidemiologi.

**Acceptance Scenarios**:

1. **Given** petugas superadmin sudah login, **When** mengunggah file Excel PD3I valid (format .xlsx, berisi data mulai baris ke-3), **Then** seluruh baris data tersimpan ke database dan halaman menampilkan ringkasan "X data berhasil diimpor"
2. **Given** file Excel berisi nomor registrasi yang sudah ada di database, **When** file tersebut diunggah, **Then** data lama di-update dengan data baru (tidak ada duplikasi), dan ringkasan mencatat jumlah yang di-update
3. **Given** proses import berjalan, **When** import selesai, **Then** pengguna diarahkan kembali ke halaman daftar kasus dengan data terbaru sudah tampil

---

### User Story 2 - Penanganan Baris Bermasalah (Priority: P2)

Sebagai petugas Dinkes, saya ingin mengetahui baris mana di file Excel yang gagal diproses beserta alasannya, sehingga saya dapat memperbaiki data tersebut dan mengimpor ulang tanpa kehilangan data yang sudah berhasil masuk.

**Why this priority**: File PD3I dari lapangan seringkali memiliki isian tidak lengkap atau format tanggal yang tidak konsisten. Petugas perlu transparansi penuh tentang apa yang gagal agar dapat bertindak.

**Independent Test**: Dapat diuji dengan mengunggah file yang mengandung beberapa baris valid dan beberapa baris dengan data bermasalah (misal: tanggal format salah, nama wilayah tidak dikenal).

**Acceptance Scenarios**:

1. **Given** file Excel mengandung campuran baris valid dan baris bermasalah, **When** import dijalankan, **Then** baris valid tersimpan dan baris bermasalah dilewati — ringkasan menampilkan "X berhasil, Y dilewati"
2. **Given** ada baris yang dilewati, **When** pengguna melihat ringkasan, **Then** tersedia daftar detail per baris (nomor baris + nomor registrasi + alasan kegagalan) yang bisa dibuka/tutup
3. **Given** seluruh baris dalam file bermasalah, **When** import dijalankan, **Then** sistem menampilkan pesan error yang jelas bahwa tidak ada data yang berhasil diimpor

---

### User Story 3 - Validasi File Sebelum Import (Priority: P3)

Sebagai petugas Dinkes, saya ingin sistem menolak file yang jelas-jelas tidak valid sebelum proses import dimulai, sehingga saya tidak menunggu lama hanya untuk mengetahui file salah.

**Why this priority**: Mencegah pemborosan waktu akibat upload file yang salah format, terlalu besar, atau bukan template PD3I.

**Independent Test**: Dapat diuji dengan mengunggah file berformat salah (PDF, CSV), file kosong, atau file melebihi batas ukuran.

**Acceptance Scenarios**:

1. **Given** pengguna mencoba upload file non-Excel (PDF, Word, CSV), **When** memilih file di dialog upload, **Then** file ditolak sebelum dikirim ke server dengan pesan "Format file tidak didukung"
2. **Given** pengguna mencoba upload file Excel melebihi batas ukuran (20 MB), **When** tombol import diklik, **Then** sistem menampilkan pesan batas ukuran yang jelas
3. **Given** file Excel valid tapi tidak memiliki data sama sekali (header saja), **When** import dijalankan, **Then** sistem menampilkan "Tidak ada data yang ditemukan pada file"

---

### User Story 4 - Pemetaan Kolom Lengkap (Priority: P2)

Sebagai petugas Dinkes, saya ingin seluruh kolom penting dari template Excel PD3I tersimpan ke database, termasuk identitas pasien, tanggal-tanggal kasus, gejala, dan data wilayah, sehingga laporan surveillance memiliki data yang lengkap.

**Why this priority**: Pemetaan kolom yang tidak lengkap menyebabkan kehilangan data yang kritis untuk analisis epidemiologi.

**Independent Test**: Dapat diuji dengan mengunggah file PD3I lengkap dan memverifikasi semua field penting terisi di database (NIK, tanggal lahir, kecamatan/kelurahan, semua gejala, diagnosis).

**Acceptance Scenarios**:

1. **Given** file Excel PD3I berisi data lengkap, **When** import berhasil, **Then** field identitas pasien (NIK, nama, jenis kelamin, tanggal lahir, alamat) tersimpan dengan benar
2. **Given** file Excel berisi nama kecamatan/kelurahan, **When** import berjalan, **Then** sistem mencocokkan nama wilayah ke ID referensi secara otomatis (case-insensitive), dan mencatat peringatan jika wilayah tidak ditemukan
3. **Given** file Excel berisi kolom gejala dan diagnosis, **When** import berhasil, **Then** seluruh gejala (demam, batuk, pilek, ruam, mata merah, dll.) dan diagnosis akhir tersimpan dengan benar

---

### Edge Cases

- Apa yang terjadi jika nama kecamatan/kelurahan di Excel berbeda ejaan dengan data master (misal: "Bontang Selatan" vs "BONTANG SELATAN")?
- Apa yang terjadi jika kolom tanggal di Excel berformat campuran (sebagian angka Excel, sebagian teks)?
- Apa yang terjadi jika nomor registrasi di Excel kosong/null?
- Apa yang terjadi jika file sangat besar (ratusan baris) dan proses import membutuhkan waktu lama?
- Apa yang terjadi jika pengguna bukan superadmin mencoba mengakses URL import secara langsung?

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Sistem HARUS menolak akses ke fitur import bagi pengguna yang bukan superadmin (menampilkan halaman error 403)
- **FR-002**: Sistem HARUS menerima file berformat .xlsx dan .xls dengan ukuran maksimum 20 MB
- **FR-003**: Sistem HARUS mulai membaca data dari baris ke-3 (baris 1–2 adalah header template PD3I)
- **FR-004**: Sistem HARUS memetakan kolom-kolom berikut dari Excel ke database: nomor registrasi, NIK, nama lengkap, jenis kelamin, tanggal lahir, kategori umur, alamat, provinsi, kab/kota, kecamatan, kelurahan, RT, instansi pelapor, tanggal onset, tanggal lapor, tanggal terima laporan, tanggal penyidikan, jenis kasus, semua gejala klinis (demam, batuk, pilek, ruam, mata merah, dll.), dan diagnosis akhir
- **FR-005**: Sistem HARUS mencocokkan nama kecamatan dan kelurahan secara case-insensitive ke ID referensi yang ada di database
- **FR-006**: Sistem HARUS menggunakan nomor registrasi sebagai kunci unik: jika sudah ada, data di-update; jika belum ada, data dibuat baru
- **FR-007**: Sistem HARUS melewati baris yang gagal diproses (bukan membatalkan seluruh import) dan melanjutkan ke baris berikutnya
- **FR-008**: Sistem HARUS menampilkan ringkasan hasil import: jumlah data berhasil, jumlah baris dilewati
- **FR-009**: Sistem HARUS menampilkan detail error per baris yang dilewati: nomor baris, nomor registrasi, dan alasan kegagalan
- **FR-010**: Sistem HARUS mencatat (log) semua baris yang dilewati beserta alasannya untuk keperluan audit
- **FR-011**: Sistem HARUS mengisi field audit (siapa yang mengimpor, kapan) secara otomatis berdasarkan pengguna yang sedang login
- **FR-012**: Jika nomor registrasi pada baris Excel kosong, sistem HARUS membuat nomor registrasi sementara secara otomatis agar baris tidak terlewati begitu saja

### Key Entities

- **Kasus Surveillance (SurveillanceCase)**: Entitas utama yang menyimpan data kasus PD3I — mencakup identitas pasien, data pelapor, tanggal-tanggal kritis, gejala klinis, dan diagnosis akhir
- **Kecamatan / Kelurahan**: Data referensi wilayah yang digunakan untuk mencocokkan nama wilayah dari Excel ke ID internal database
- **Jenis Kasus Epidemiologi**: Data referensi jenis penyakit PD3I (misal: Campak, AFP, Difteri) yang dicocokkan dengan nama di kolom Excel

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Petugas dapat menyelesaikan proses upload dan import file Excel PD3I dalam waktu kurang dari 2 menit untuk file berisi hingga 500 baris
- **SC-002**: 100% baris data yang valid dalam file Excel tersimpan ke database tanpa ada yang terlewat secara diam-diam
- **SC-003**: Petugas dapat mengidentifikasi dan memperbaiki baris bermasalah tanpa bantuan teknis — informasi error cukup jelas untuk dipahami pengguna non-teknis
- **SC-004**: File yang sama dapat diunggah berulang kali tanpa menyebabkan data ganda (upsert berdasarkan nomor registrasi)
- **SC-005**: Seluruh field penting dari template PD3I resmi (identitas, wilayah, gejala, diagnosis) tersimpan dengan akurasi 100% untuk baris yang berhasil diproses
- **SC-006**: Pengguna non-superadmin tidak dapat mengakses fitur import dalam kondisi apapun (termasuk akses URL langsung)

## Assumptions

- Template Excel PD3I yang digunakan adalah format resmi dengan struktur kolom yang konsisten dan tetap (data mulai baris ke-3)
- Indeks kolom dalam file Excel PD3I bersifat tetap sesuai dengan template resmi yang sudah diverifikasi
- Nama kecamatan dan kelurahan di data master database sudah sesuai dengan nama yang digunakan dalam template Excel PD3I
- Ukuran file maksimum 20 MB mencukupi untuk kebutuhan pelaporan periodik (hingga beberapa ratus kasus per periode)
- Proses import dijalankan secara sinkron (tidak menggunakan antrean latar belakang) karena volume data per upload relatif kecil

## Out of Scope

- Import dari format file selain Excel (.csv, .pdf, Google Sheets, dll.)
- Preview data sebelum import dikonfirmasi
- Rollback/pembatalan import setelah selesai
- Penjadwalan import otomatis
- Import oleh pengguna selain superadmin (surveilans faskes tetap input manual)

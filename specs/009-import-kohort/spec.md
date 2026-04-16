# Feature Specification: Import Data Anak & Imunisasi dari Kohort Puskesmas

**Feature Branch**: `009-import-kohort`  
**Created**: 2026-04-08  
**Status**: Draft  
**Input**: User description: "baca /docs/kohort puskesmas.xlsx kita tambah modul import data anak dan imunisasi"

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Import Identitas Anak dari Sheet Balita (Priority: P1)

Sebagai admin puskesmas, saya ingin mengimpor data identitas anak (bayi/balita 0–5 tahun) dari file kohort puskesmas ke dalam sistem, agar data anak yang sebelumnya hanya ada di Excel dapat dikelola secara digital, dicari, dan dianalisis.

File kohort (`Kohort puskesmas.xlsx`) memiliki sheet **balita** dengan ~905 baris data anak. Header dimulai di baris 4. Setiap baris berisi: NIK anak, nama lengkap, tanggal lahir, jenis kelamin, nomor KK, data orang tua (NIK/nama ayah & ibu), no HP, alamat, RT, data kelahiran (BBL, PBL, LK lahir, IMD, usia kehamilan, tempat melahirkan, penolong, komplikasi).

**Why this priority**: Tanpa data identitas anak, tidak ada dasar untuk menyimpan data posyandu maupun imunisasi. Ini adalah blok fondasi untuk semua user story berikutnya.

**Independent Test**: Upload file kohort → sistem menampilkan daftar anak yang berhasil diimpor dengan nama dan NIK terlihat di halaman daftar anak. Tidak ada error integrity constraint untuk baris dengan data lengkap.

**Acceptance Scenarios**:

1. **Given** file kohort dengan 905 baris data balita valid, **When** admin mengupload file dan memilih sheet "balita", **Then** sistem menyimpan identitas anak (NIK, nama, tgl lahir, JK, data orang tua, alamat) dan menampilkan ringkasan "X berhasil diimpor, Y dilewati"
2. **Given** file kohort dengan baris yang tidak memiliki NIK anak dan nama kosong, **When** diproses, **Then** baris tersebut dilewati dan dicatat sebagai failure dengan pesan yang dapat dibaca petugas
3. **Given** baris dengan NIK yang sudah ada di sistem, **When** diproses, **Then** sistem memperbarui data yang ada (upsert berdasarkan NIK) tanpa membuat duplikat
4. **Given** field tanggal lahir kosong, **When** diproses, **Then** data tetap disimpan dengan tanggal lahir null, dan peringatan dicatat (bukan skip)

---

### User Story 2 — Import Data Kunjungan Posyandu Bulanan (Priority: P2)

Sebagai admin puskesmas, saya ingin mengimpor data pengukuran bulanan anak (berat badan, panjang badan, LILA, LK, z-score, ASI, gizi) yang ada di setiap kolom bulan (Januari–Desember) dalam sheet balita, agar riwayat tumbuh kembang anak dapat terpantau dalam sistem.

File kohort memiliki 12 kelompok kolom per bulan (Jan–Des), setiap bulan berisi: tanggal posyandu, umur (bulan), LK, hasil LK, LILA, hasil LILA, BB, PB, skor BB/U, PB/U, BB/PB, status gizi, ASI, rujuk, dan beberapa kolom tambahan (Vit A, makanan pokok, POPM, Taburia).

**Why this priority**: Data kunjungan posyandu adalah inti dari fungsi pemantauan tumbuh kembang. Tanpa ini, sistem hanya menyimpan identitas, bukan rekam medis yang berguna.

**Independent Test**: Setelah import, pilih satu anak → halaman detail anak menampilkan tabel riwayat kunjungan posyandu dengan tanggal, BB, PB, dan status gizi sesuai data Excel.

**Acceptance Scenarios**:

1. **Given** baris anak dengan data pengukuran bulan Januari hingga Juni terisi, **When** diproses, **Then** sistem menyimpan 6 record kunjungan posyandu untuk anak tersebut, masing-masing dengan tanggal dan nilai pengukuran yang benar
2. **Given** bulan di Excel yang tidak memiliki tanggal posyandu (kosong), **When** diproses, **Then** bulan tersebut tidak menghasilkan record kunjungan (tidak membuat baris kosong)
3. **Given** nilai BB/U, PB/U, BB/PB berisi formula error (#DIV/0!, #N/A), **When** diproses, **Then** nilai tersebut disimpan sebagai null tanpa menghentikan proses import
4. **Given** import dijalankan dua kali untuk file yang sama, **When** kedua kali diproses, **Then** jumlah record kunjungan tidak berlipat ganda (upsert berdasarkan anak + bulan/tanggal)

---

### User Story 3 — Import Data Imunisasi Anak (Priority: P2)

Sebagai admin puskesmas, saya ingin mengimpor catatan imunisasi anak (HB 0, BCG, Polio 1–4, DPT 1–3 + Booster, PCV 1–3, Rotavirus 1–3, Campak + Booster, IPV 1–2) dari kolom imunisasi di sheet balita, agar status imunisasi setiap anak dapat dipantau dan dilaporkan.

Kolom imunisasi berada di area akhir sheet (sekitar kolom JU–KO, baris 4 sebagai header). Setiap kolom berisi tanggal pemberian vaksin (atau kosong jika belum/tidak diberikan). Terdapat juga kolom "Alasan tidak imunisasi".

**Why this priority**: Imunisasi adalah data kritis untuk program kesehatan anak. Setara prioritasnya dengan data posyandu karena keduanya merupakan output utama dari sheet balita.

**Independent Test**: Setelah import, buka detail salah satu anak → tab/section Imunisasi menampilkan vaksin yang sudah diberikan dengan tanggal, sesuai data Excel.

**Acceptance Scenarios**:

1. **Given** baris anak dengan tanggal Campak = 28 September 2019 dan DPT 3 = tanggal tertentu, **When** diproses, **Then** sistem menyimpan catatan imunisasi dengan vaksin dan tanggal yang benar
2. **Given** kolom imunisasi kosong untuk vaksin tertentu, **When** diproses, **Then** vaksin tersebut tidak disimpan atau disimpan dengan status "belum diberikan" (bukan error)
3. **Given** kolom "Alasan tidak imunisasi" berisi teks, **When** diproses, **Then** alasan tersebut disimpan bersama catatan imunisasi anak

---

### User Story 4 — Laporan Hasil Import & Penanganan Error (Priority: P3)

Sebagai admin puskesmas, saya ingin melihat laporan hasil import yang jelas — berapa baris berhasil, berapa dilewati, dan apa alasan spesifik per baris yang gagal — agar saya dapat menindaklanjuti data yang tidak terimport.

**Why this priority**: Transparansi error penting tapi bukan blocking untuk import itu sendiri. Import yang berhasil sebagian lebih baik dari tidak sama sekali.

**Independent Test**: Upload file dengan 5 baris valid + 1 baris tanpa nama → laporan menampilkan "5 berhasil, 1 dilewati" dengan pesan error yang dapat dibaca petugas non-teknis.

**Acceptance Scenarios**:

1. **Given** import selesai, **When** admin membuka halaman status import, **Then** sistem menampilkan: total baris diproses, jumlah berhasil, jumlah dilewati, dan daftar error per baris (no urut + alasan)
2. **Given** import berjalan di background, **When** admin menunggu, **Then** halaman status diperbarui otomatis tanpa perlu refresh manual
3. **Given** file kohort berukuran besar (>1000 baris), **When** diproses, **Then** sistem tidak timeout dan petugas dapat melanjutkan aktivitas lain selama proses berlangsung

---

### Edge Cases

- Apa yang terjadi jika NIK anak duplikat dalam satu file (baris berbeda, NIK sama)?
- Bagaimana jika sheet "balita" tidak ditemukan dalam file yang diupload (file salah)?
- Bagaimana penanganan nilai BB/PB dengan #DIV/0! atau #N/A dari formula Excel?
- Apa yang terjadi jika nomor RT di Excel tidak ada dalam master data RT?
- Bagaimana jika file bukan format .xlsx (misalnya .xls lama atau .csv)?

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Sistem HARUS menerima upload file Excel (.xlsx) dari format kohort puskesmas dengan validasi format sebelum diproses
- **FR-002**: Sistem HARUS membaca sheet "balita" sebagai sumber data utama; sheet lain (bumilbufas, dewasa, lansia, remaja) diabaikan untuk tahap ini
- **FR-003**: Sistem HARUS menggunakan NIK anak sebagai kunci upsert — baris baru dibuat jika NIK belum ada, baris yang ada diperbarui jika NIK sudah ada
- **FR-004**: Sistem HARUS memetakan kolom identitas anak (A–W di baris 4) ke entitas Anak dalam sistem
- **FR-005**: Sistem HARUS memetakan setiap kelompok kolom bulanan (12 bulan: Jan–Des) ke record kunjungan posyandu, hanya untuk bulan yang memiliki tanggal posyandu terisi
- **FR-006**: Sistem HARUS memetakan kolom imunisasi (HB 0 hingga Campak Booster) ke catatan imunisasi anak, satu record per vaksin per anak
- **FR-007**: Sistem HARUS melewati baris tanpa nama anak dan tanpa NIK anak secara bersamaan, dengan mencatat pesan error yang informatif
- **FR-008**: Sistem HARUS mengabaikan nilai error formula Excel (#DIV/0!, #N/A, #VALUE!) dan menyimpan field tersebut sebagai null
- **FR-009**: Sistem HARUS menjalankan proses import di latar belakang (background job) agar antarmuka tidak membekukan browser
- **FR-010**: Sistem HARUS menyediakan halaman/panel status yang menampilkan kemajuan dan hasil import secara real-time
- **FR-011**: Hanya pengguna dengan peran superadmin yang dapat mengakses fitur import ini

### Key Entities

- **Anak**: Data identitas anak — NIK, nama, tanggal lahir, jenis kelamin, nomor KK, nama/NIK orang tua, no HP, alamat, RT, data kelahiran (BBL, PBL, LK lahir, IMD, tempat lahir, penolong, komplikasi persalinan, usia kehamilan)
- **DataAnak** (Kunjungan Posyandu): Record per kunjungan bulanan — tanggal posyandu, umur bulan, BB, PB, LILA, LK, z-score (BB/U, PB/U, BB/PB), status gizi, ASI, rujuk, Vit A, POPM, Taburia
- **ImunisasiAnak**: Catatan vaksin per anak — jenis vaksin, tanggal pemberian, alasan tidak imunisasi
- **ImportLog**: Log pelacak status import — nama file, status (pending/processing/done/failed), jumlah berhasil, jumlah gagal, detail error per baris

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Admin dapat mengimpor seluruh ~905 baris data balita dari file kohort dalam satu sesi tanpa error sistem (bukan error data)
- **SC-002**: Setelah import, 100% baris dengan data NIK + nama lengkap yang valid tersimpan di sistem tanpa perlu intervensi manual
- **SC-003**: Data kunjungan posyandu per bulan yang terisi di Excel terimport dengan akurasi nilai BB, PB, dan status gizi yang sama dengan sumber
- **SC-004**: Data imunisasi (tanggal vaksin) terimport dengan akurasi 100% untuk baris yang memiliki tanggal terisi
- **SC-005**: Proses import file kohort penuh (~905 baris) selesai dalam waktu kurang dari 5 menit
- **SC-006**: Admin menerima laporan hasil import yang menjelaskan setiap baris yang tidak terimport beserta alasannya dalam bahasa yang dipahami petugas non-teknis
- **SC-007**: Import yang dijalankan dua kali untuk file yang sama tidak menghasilkan data duplikat

## Assumptions

- Sheet "balita" adalah satu-satunya sheet yang diimpor dalam tahap ini; sheet bumilbufas, dewasa, lansia, dan remaja direncanakan sebagai pengembangan terpisah
- Header kolom dimulai di baris 4; baris 1–3 adalah baris judul dan sub-header yang diabaikan
- NIK anak (kolom B) digunakan sebagai kunci unik; jika NIK kosong, sistem akan menggunakan kombinasi nama + tanggal lahir sebagai fallback
- Kolom RT (kolom N) berisi angka/teks RT yang akan di-lookup ke master data RT; jika tidak ditemukan, `id_rt` disimpan sebagai null (tidak auto-create)
- Nilai z-score (BB/U, PB/U, BB/PB) yang berupa formula Excel (#DIV/0!, #N/A) dianggap "tidak dapat dihitung" dan disimpan sebagai null
- Model ImunisasiAnak mungkin sudah ada sebagian; implementasi akan menyesuaikan dengan struktur yang ada atau membuat tabel baru jika diperlukan
- Format file yang diterima hanya .xlsx (Excel 2007+); format .xls lama tidak didukung

## Dependencies

- Model `Anak` dan `DataAnak` sudah ada di sistem — import harus kompatibel dengan skema yang ada
- Modul background job (queue worker) sudah tersedia dari fitur import PD3I sebelumnya (`ImportPd3iJob`, `ImportLog`)
- Master data wilayah (Kecamatan, Kelurahan, RT) harus sudah ada di DB agar lookup alamat berfungsi
- File kohort yang diterima harus mengikuti format standar dari aplikasi Kohort digital Puskesmas Bontang

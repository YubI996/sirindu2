# Feature Specification: Peningkatan Modul Surveilans PD3I & Kelompok Vaksin

**Feature Branch**: `001-pd3i-vaksin`  
**Created**: 2026-03-31  
**Status**: Draft  
**Input**: User description: "Modul surveilans PD3I: auto-generate nomor epid, perubahan input alamat, chart kasus tempat umum, export PDF formulir investigasi. Modul Vaksin: kelompok vaksin IDL/IBL/ISL, status kelengkapan vaksin anak, aturan usia pemberian, status kejar vaksin, export data agregat imunisasi."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Auto-Generate Nomor Epidemiologi (Priority: P1)

Sebagai petugas surveilans, saat membuat kasus PD3I baru, sistem otomatis menghasilkan nomor epidemiologi dengan format yang benar berdasarkan jenis penyakit dan urutan kasus di tahun berjalan, sehingga tidak perlu mengisi manual dan menghindari duplikasi.

Format: `[kode penyakit][-][1710][dua digit tahun][urutan 3 digit]`
- Campak: `C-171026001`
- Difteri: `D-171026002`
- Pertusis: `P-171026003`
- AFP/Polio: `171026004` (tanpa prefix)
- Tetanus Neonatorum: `TN-171026005`

Kode `1710` adalah kode wilayah tetap. Urutan kasus dihitung per tahun secara global (lintas penyakit).

**Why this priority**: Nomor epid adalah identitas utama kasus yang digunakan di seluruh alur pelaporan. Format yang konsisten dan otomatis mencegah kesalahan entri data dan duplikasi.

**Independent Test**: Dapat diuji dengan membuat beberapa kasus baru untuk penyakit berbeda dan memverifikasi format serta urutan nomor epid yang dihasilkan.

**Acceptance Scenarios**:

1. **Given** petugas membuka form kasus baru dan memilih penyakit Campak, **When** form ditampilkan, **Then** nomor epid terisi otomatis dengan format `C-1710[YY][NNN]` di mana YY = dua digit tahun dan NNN = urutan berikutnya
2. **Given** sudah ada 5 kasus di tahun 2026, **When** petugas membuat kasus baru AFP/Polio, **Then** nomor epid = `171026006` (tanpa prefix, urutan 006)
3. **Given** tahun berganti ke 2027, **When** petugas membuat kasus pertama, **Then** urutan dimulai dari 001

---

### User Story 2 - Kelompok Vaksin IDL/IBL/ISL (Priority: P1)

Sebagai admin, sistem mengelompokkan setiap jenis vaksin ke dalam salah satu kelompok: IDL (Imunisasi Dasar Lengkap), IBL (Imunisasi Booster Lengkap), atau ISL (Imunisasi Sesuai Usia Lengkap). Setiap anak memiliki status kelengkapan per kelompok vaksin.

Aturan usia pemberian:
- IDL: diberikan usia 0-11 bulan, kejar hingga 5 tahun
- IBL: diberikan usia 12-23 bulan, kejar hingga 5 tahun
- ISL: diberikan usia 7-12 tahun, tanpa masa kejar

**Why this priority**: Kelompok vaksin adalah fondasi fitur monitoring kelengkapan vaksin anak dan menjadi dasar untuk fitur status kejar serta export agregat.

**Independent Test**: Dapat diuji dengan memverifikasi bahwa setiap vaksin tergabung ke kelompok yang benar, dan status kelengkapan anak terhitung otomatis.

**Acceptance Scenarios**:

1. **Given** admin melihat daftar vaksin, **When** setiap vaksin ditampilkan, **Then** terlihat kelompok vaksin (IDL/IBL/ISL) yang sesuai
2. **Given** anak berusia 14 bulan telah menerima semua vaksin IDL, **When** petugas melihat profil anak, **Then** status IDL = "Lengkap"
3. **Given** anak berusia 14 bulan belum menerima semua vaksin IDL, **When** petugas melihat profil anak, **Then** status IDL = "Belum Lengkap" dan anak berstatus "Kejar IDL"

---

### User Story 3 - Perubahan Input Alamat Kasus PD3I (Priority: P2)

Sebagai petugas surveilans, saat mengisi form kasus PD3I, alamat/kecamatan/kelurahan/RT diisi manual berdasarkan KTP sasaran (bukan autofill dari titik koordinat). Titik koordinat tetap dicatat terpisah untuk keperluan analisis spasial di dashboard.

**Why this priority**: Perubahan alur input ini penting untuk akurasi data domisili resmi, sementara analisis spasial tetap berbasis lokasi sebenarnya (koordinat). Ini memisahkan kebutuhan administrasi dari kebutuhan epidemiologis.

**Independent Test**: Dapat diuji dengan mengisi form kasus baru, memilih alamat KTP secara manual, lalu memilih titik koordinat berbeda di peta, dan memverifikasi keduanya tersimpan independen.

**Acceptance Scenarios**:

1. **Given** petugas membuka form kasus baru, **When** petugas mengklik lokasi di peta, **Then** hanya latitude dan longitude yang terisi; dropdown kecamatan/kelurahan/RT TIDAK berubah
2. **Given** petugas telah mengisi alamat KTP dan titik koordinat, **When** data disimpan, **Then** alamat KTP dan koordinat tersimpan sebagai data terpisah
3. **Given** dashboard PD3I menampilkan statistik lokasi, **When** data diolah, **Then** perhitungan distribusi geografis menggunakan titik koordinat, bukan alamat KTP

---

### User Story 4 - Status Kejar Vaksin & Prioritas Intervensi (Priority: P2)

Sebagai petugas, anak yang melewati usia pemberian vaksin IDL atau IBL namun belum lengkap mendapat status "Kejar". Status kejar ini menjadi faktor risiko tambahan dalam sistem skoring Prioritas Intervensi yang sudah ada di Early Warning System (`/admin/early-warning`), menambah poin ke `risk_score` kumulatif anak sehingga anak tersebut naik prioritas dalam daftar intervensi.

**Why this priority**: Identifikasi anak yang perlu kejar vaksin membantu petugas memprioritaskan intervensi kesehatan secara efektif.

**Independent Test**: Dapat diuji dengan data anak yang melewati usia pemberian dan memverifikasi status kejar serta poin prioritas muncul.

**Acceptance Scenarios**:

1. **Given** anak berusia 18 bulan dengan vaksin IDL belum lengkap, **When** petugas melihat data anak, **Then** anak berstatus "Kejar IDL" dengan poin risk_score bertambah di Early Warning System
2. **Given** anak berusia 3 tahun dengan IDL dan IBL belum lengkap, **When** petugas melihat data anak, **Then** anak berstatus "Kejar IDL" dan "Kejar IBL" dengan poin risk_score lebih tinggi
3. **Given** anak berusia 5 tahun 1 bulan dengan IDL belum lengkap, **When** petugas melihat data anak, **Then** anak TIDAK berstatus kejar IDL (masa kejar berakhir di usia 5 tahun) dan poin risk_score untuk kejar tidak ditambahkan

---

### User Story 5 - Chart Kasus di Tempat Umum (Priority: P2)

Sebagai petugas surveilans, di dashboard PD3I terdapat chart baru yang menampilkan distribusi kasus berdasarkan lokasi tempat umum yang sering dikunjungi (tempat kerja, sekolah, gym, tempat ibadah), dengan judul "Distribusi Kasus Berdasarkan Lokasi Penularan di Fasilitas Umum".

**Why this priority**: Visualisasi ini membantu identifikasi hotspot penularan di fasilitas umum untuk intervensi kesehatan masyarakat yang lebih terarah.

**Independent Test**: Dapat diuji dengan memasukkan beberapa kasus dengan lokasi penularan berbeda dan memverifikasi chart menampilkan distribusi yang benar.

**Acceptance Scenarios**:

1. **Given** terdapat kasus dengan lokasi penularan di fasilitas umum, **When** petugas membuka dashboard PD3I, **Then** chart menampilkan jumlah kasus per kategori fasilitas umum
2. **Given** tidak ada kasus dengan lokasi penularan di fasilitas umum, **When** petugas membuka dashboard, **Then** chart menampilkan pesan "Tidak ada data"

---

### User Story 6 - Export PDF Formulir Investigasi Kasus (Priority: P3)

Sebagai petugas surveilans, petugas dapat meng-generate PDF formulir investigasi dari data kasus PD3I yang sudah tercatat. PDF menggunakan template yang sama untuk semua jenis penyakit PD3I, dengan judul yang disesuaikan per jenis penyakit. PDF mengikuti desain resmi Kemenkes (format MR-01) dengan logo Kemenkes, dan berisi seluruh informasi kasus termasuk: data pasien, informasi klinis, komplikasi, riwayat pengobatan, riwayat imunisasi, informasi epidemiologis, dan data laboratorium.

**Why this priority**: Export PDF diperlukan untuk pelaporan resmi ke dinas kesehatan dan dokumentasi, tetapi tidak memblokir operasional harian.

**Independent Test**: Dapat diuji dengan membuat kasus lengkap lalu men-generate PDF dan memverifikasi semua field terisi sesuai desain formulir.

**Acceptance Scenarios**:

1. **Given** kasus PD3I sudah tercatat lengkap, **When** petugas klik tombol "Export PDF", **Then** sistem menghasilkan PDF dengan layout sesuai desain formulir MR-01
2. **Given** PDF di-generate, **When** petugas membuka file, **Then** terdapat logo Kemenkes, semua section terisi (informasi kasus, klinis, komplikasi, pengobatan, imunisasi, epidemiologis, lab), dan format sesuai standar pelaporan

---

### User Story 7 - Export Data Agregat Imunisasi (Priority: P3)

Sebagai petugas, dapat meng-export data agregat imunisasi dalam format Excel dengan filter bulan dan tahun. Tabel menampilkan data per kelurahan dengan kolom untuk setiap jenis vaksin (jumlah & persentase per jenis kelamin), serta kolom agregat untuk kelompok IDL, IBL, dan ISL.

Format kolom: `Nomor | Kelurahan | (per vaksin: #L %L #P %P #Jml %Jml) | IDL (#L %L #P %P #Jml %Jml) | IBL (...) | ISL (...)`

**Why this priority**: Export agregat berguna untuk pelaporan bulanan ke dinas kesehatan, namun bukan fitur operasional harian.

**Independent Test**: Dapat diuji dengan mengisi data imunisasi untuk beberapa anak di kelurahan berbeda, lalu men-generate export dan memverifikasi angka agregat.

**Acceptance Scenarios**:

1. **Given** terdapat data imunisasi bulan Maret 2026, **When** petugas memilih bulan Maret 2026 dan klik export, **Then** file Excel ter-download dengan judul "Data Agregat Imunisasi Bulan Maret Tahun 2026"
2. **Given** data imunisasi ada di beberapa kelurahan, **When** file dibuka, **Then** setiap baris menampilkan satu kelurahan dengan jumlah dan persentase per vaksin dan per kelompok, dipisah berdasarkan jenis kelamin (L/P) dan total

---

### Edge Cases

- Apa yang terjadi jika nomor epid sudah mencapai 999 dalam satu tahun? Sistem melanjutkan ke 4 digit (1000, dst.)
- Apa yang terjadi jika dua petugas submit kasus secara bersamaan? Database lock memastikan urutan nomor epid unik; jika terjadi konflik, sistem otomatis retry dengan nomor berikutnya
- Bagaimana jika anak pindah ke usia di luar rentang kejar saat data diperbarui? Status kejar dihitung ulang setiap kali data anak diakses berdasarkan usia terkini
- Apa yang terjadi jika kasus PD3I tidak memiliki koordinat saat export PDF? Field koordinat dikosongkan, PDF tetap dapat di-generate
- Bagaimana jika tidak ada data imunisasi untuk bulan/tahun yang dipilih saat export agregat? Sistem menghasilkan file Excel dengan tabel kosong (header tetap ada)
- Bagaimana jika jenis vaksin baru ditambahkan setelah kelompok vaksin sudah ada? Vaksin baru harus di-assign ke kelompok vaksin saat pembuatan

## Requirements *(mandatory)*

### Functional Requirements

**Nomor Epidemiologi:**
- **FR-001**: Sistem HARUS auto-generate nomor epid saat kasus baru dibuat dengan format `[kode penyakit]-1710[YY][NNN]`
- **FR-002**: Kode penyakit: Campak=C, Difteri=D, Pertusis=P, AFP/Polio=(tanpa kode, tanpa dash), Tetanus Neonatorum=TN
- **FR-003**: Urutan (NNN) HARUS bersifat global (lintas penyakit) per tahun dan auto-increment
- **FR-004**: Urutan HARUS reset ke 001 setiap awal tahun baru
- **FR-005**: Nomor epid HARUS di-generate saat submit (bukan saat form dibuka) dengan mekanisme locking untuk mencegah duplikasi pada akses bersamaan, plus validasi unik di database
- **FR-005a**: Field nomor epid HARUS readonly dan tidak dapat diubah manual oleh pengguna

**Perubahan Input Alamat:**
- **FR-006**: Form kasus PD3I HARUS menyediakan input alamat KTP secara manual (dropdown kecamatan/kelurahan/RT) tanpa autofill dari peta
- **FR-007**: Pemilihan titik koordinat di peta HARUS hanya mengisi latitude dan longitude, TIDAK mengubah dropdown alamat
- **FR-008**: Dashboard PD3I HARUS menggunakan titik koordinat (bukan alamat KTP) untuk semua perhitungan statistik geografis

**Chart Kasus Tempat Umum:**
- **FR-009**: Dashboard PD3I HARUS menampilkan chart baru "Distribusi Kasus Berdasarkan Lokasi Penularan di Fasilitas Umum"
- **FR-010**: Field `lokasi_penularan` HARUS diubah dari input teks bebas menjadi dropdown yang dipopulasi dengan daftar sekolah di Kota Bontang (sumber: `docs/list sekolah di bontang.txt`, 160 entri) dan fasilitas umum lainnya (tempat kerja, gym, tempat ibadah), dengan kemampuan menambah lokasi custom
- **FR-011**: Chart HARUS menampilkan jumlah kasus per kategori fasilitas dan mendukung filter penyakit yang sudah ada di dashboard
- **FR-011a**: Data lama dari field `lokasi_penularan` HARUS tetap dipertahankan dan kompatibel dengan format dropdown baru

**Export PDF:**
- **FR-012**: Sistem HARUS dapat meng-generate PDF formulir investigasi (format MR-01) dari data kasus PD3I manapun, dengan judul disesuaikan per jenis penyakit
- **FR-013**: PDF HARUS menyertakan logo Kemenkes dan mengikuti layout desain standar formulir investigasi, menggunakan satu template yang sama untuk semua jenis penyakit
- **FR-014**: PDF HARUS mencakup seluruh section: informasi kasus, klinis, komplikasi, riwayat pengobatan, riwayat imunisasi, informasi epidemiologis, dan data laboratorium

**Kelompok Vaksin:**
- **FR-015**: Sistem HARUS memiliki entitas kelompok vaksin dengan tiga nilai: IDL, IBL, ISL
- **FR-016**: Setiap jenis vaksin HARUS tergabung ke tepat satu kelompok vaksin
- **FR-017**: Pengelompokan vaksin default HARUS diisi berdasarkan standar program imunisasi nasional Indonesia

**Status Kelengkapan Vaksin Anak:**
- **FR-018**: Setiap anak HARUS memiliki status kelengkapan per kelompok vaksin (IDL, IBL, ISL): Lengkap atau Belum Lengkap
- **FR-019**: Status kelengkapan HARUS dihitung otomatis berdasarkan vaksin yang sudah diterima vs vaksin yang diperlukan dalam kelompok tersebut
- **FR-020**: IDL diberikan untuk anak 0-11 bulan dengan masa kejar hingga 5 tahun
- **FR-021**: IBL diberikan untuk anak 12-23 bulan dengan masa kejar hingga 5 tahun
- **FR-022**: ISL diberikan untuk anak 7-12 tahun tanpa masa kejar

**Status Kejar Vaksin:**
- **FR-023**: Anak yang melewati usia pemberian IDL (>11 bulan) namun belum lengkap dan masih dalam masa kejar (<5 tahun) HARUS berstatus "Kejar IDL"
- **FR-024**: Anak yang melewati usia pemberian IBL (>23 bulan) namun belum lengkap dan masih dalam masa kejar (<5 tahun) HARUS berstatus "Kejar IBL"
- **FR-025**: Status kejar HARUS menjadi faktor risiko tambahan dalam sistem skoring Prioritas Intervensi di Early Warning System (`/admin/early-warning`), menambah poin ke `risk_score` kumulatif anak. Anak dengan kejar ganda (IDL+IBL) mendapat poin lebih tinggi dari kejar tunggal
- **FR-026**: Status kejar HARUS tidak berlaku jika anak melewati batas usia kejar (>5 tahun untuk IDL/IBL)

**Export Agregat Imunisasi:**
- **FR-027**: Sistem HARUS menyediakan export data agregat imunisasi dalam format Excel
- **FR-028**: Export HARUS dapat difilter berdasarkan bulan dan tahun
- **FR-029**: Tabel HARUS menampilkan data per kelurahan dengan kolom: nomor, kelurahan, jumlah per vaksin (L/P/Total dengan persentase), jumlah per kelompok IDL/IBL/ISL (L/P/Total dengan persentase)
- **FR-030**: Judul file HARUS mengikuti format "Data Agregat Imunisasi Bulan {Bulan} Tahun {Tahun}"

### Key Entities

- **Kelompok Vaksin**: Pengelompokan vaksin berdasarkan program imunisasi nasional (IDL, IBL, ISL). Satu kelompok memiliki banyak jenis vaksin. Memiliki atribut: nama kelompok, usia pemberian minimum, usia pemberian maksimum, batas usia kejar (nullable untuk ISL)
- **Jenis Vaksin**: Diperluas dengan relasi ke kelompok vaksin (many-to-one). Setiap vaksin tergolong ke tepat satu kelompok
- **Status Kelengkapan Vaksin Anak**: Status kalkulasi (bukan entitas tersimpan) yang menentukan kelengkapan IDL/IBL/ISL per anak berdasarkan riwayat imunisasi vs daftar vaksin yang diperlukan dalam kelompok
- **Kasus Surveilans (SurveillanceCase)**: Diperluas dengan nomor epid yang auto-generate. Alamat KTP dan koordinat lokasi menjadi data terpisah yang tidak saling mengisi

## Clarifications

### Session 2026-03-31

- Q: Apakah export PDF formulir investigasi hanya untuk Campak atau semua penyakit PD3I? → A: Untuk semua penyakit PD3I, dengan template yang sama tapi judul disesuaikan per jenis penyakit.
- Q: Bagaimana cara menentukan kategori fasilitas umum untuk chart lokasi penularan? → A: Ubah field `lokasi_penularan` dari teks bebas menjadi dropdown yang dipopulasi dengan daftar sekolah di Kota Bontang dan fasilitas umum lainnya, dengan kemampuan menambah lokasi custom. Data lama tetap dipertahankan.
- Q: Bagaimana "poin prioritas intervensi" bekerja dengan status kejar vaksin? → A: Status kejar vaksin IDL/IBL menjadi faktor risiko tambahan dalam sistem skoring Prioritas Intervensi yang sudah ada di `/admin/early-warning`, menambah poin ke risk_score kumulatif anak.
- Q: Apakah daftar vaksin wajib saat ini sudah benar sebagai dasar pengelompokan IDL/IBL/ISL? → A: Gunakan daftar vaksin program imunisasi nasional terkini sebagai dasar, sesuaikan saat implementasi melalui riset standar Kemenkes.
- Q: Bagaimana penanganan concurrent nomor epid generation? → A: Nomor epid di-generate saat submit (bukan saat form dibuka) dengan database-level locking untuk mencegah race condition, plus validasi unik di database.

## Assumptions

- Kode wilayah `1710` adalah kode tetap untuk Kota Bontang dan tidak akan berubah
- Urutan nomor epid bersifat global (bukan per penyakit) berdasarkan contoh yang diberikan (001, 002, 003, 004, 005 berurutan lintas penyakit)
- Pengelompokan vaksin ke IDL/IBL/ISL mengacu pada program imunisasi nasional Indonesia terkini (berdasarkan riset standar Kemenkes)
- Status kelengkapan vaksin dihitung secara real-time (bukan disimpan sebagai field statis) agar selalu akurat saat vaksin baru diberikan
- "Poin prioritas intervensi" adalah nilai numerik yang meningkat dengan setiap status kejar aktif, digunakan untuk pengurutan/filtering
- Formulir PDF MR-01 mengikuti desain yang disediakan di `docs/Export formulir/form.png` dengan penyesuaian data dari field yang tersedia di sistem
- Field `lokasi_penularan` yang sudah ada di model SurveillanceCase akan digunakan sebagai basis data untuk chart kasus tempat umum, dengan penambahan kategorisasi fasilitas umum

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Petugas dapat membuat kasus PD3I baru dengan nomor epid otomatis dalam waktu kurang dari 30 detik tanpa perlu mengisi nomor manual
- **SC-002**: 100% nomor epid yang dihasilkan mengikuti format yang benar dan unik (tidak ada duplikasi)
- **SC-003**: Petugas dapat mengisi alamat KTP dan titik koordinat secara independen tanpa saling mempengaruhi
- **SC-004**: Dashboard PD3I menampilkan distribusi geografis yang akurat berdasarkan koordinat, bukan alamat KTP
- **SC-005**: Setiap anak memiliki status kelengkapan vaksin (IDL/IBL/ISL) yang terlihat di profil anak dan terhitung otomatis
- **SC-006**: Anak dengan status kejar dapat diidentifikasi dan diurutkan berdasarkan prioritas intervensi
- **SC-007**: PDF formulir investigasi kasus dapat di-generate dan sesuai dengan format standar MR-01 Kemenkes
- **SC-008**: Export data agregat imunisasi menghasilkan file Excel yang lengkap dan akurat sesuai format yang diminta dalam waktu kurang dari 30 detik
- **SC-009**: Chart distribusi kasus di fasilitas umum menampilkan data yang akurat dan responsif terhadap filter penyakit

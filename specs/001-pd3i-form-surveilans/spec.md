# Feature Specification: Pembaruan Modul Surveilans PD3I

**Feature Branch**: `001-pd3i-form-surveilans`  
**Created**: 2026-04-10  
**Status**: Draft  
**Input**: Pembaruan Form Input Kasus PD3I (A), Tabel Data Surveilans PD3I (B), dan NIK Dummy pada Import (C)

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Petugas Puskesmas Mengisi Form Kasus PD3I dengan Wilker Otomatis (Priority: P1)

Petugas puskesmas membuka form input kasus PD3I. Saat memilih kelurahan pasien, field Wilayah Kerja (Wilker) Puskesmas terisi otomatis sesuai pemetaan kelurahan ke puskesmas. Petugas tidak perlu memilih puskesmas secara manual, mengurangi kesalahan input.

**Why this priority**: Wilker otomatis menghilangkan risiko salah klaim kasus antar puskesmas, langsung berdampak pada akurasi pelaporan.

**Independent Test**: Dapat diuji dengan memilih kelurahan berbeda-beda di form dan memverifikasi bahwa field Wilker berubah sesuai tabel pemetaan yang telah ditentukan.

**Acceptance Scenarios**:

1. **Given** petugas membuka form input kasus PD3I, **When** memilih kelurahan "SATIMPO", **Then** field Wilker terisi otomatis "Bontang Selatan 1" dan tidak bisa diubah manual.
2. **Given** petugas memilih kelurahan "LOK TUAN", **When** field kelurahan disimpan, **Then** field Wilker otomatis berubah ke "Bontang Utara 2".
3. **Given** petugas mengubah kelurahan setelah Wilker sudah terisi, **When** kelurahan diganti, **Then** Wilker diperbarui mengikuti kelurahan baru.

---

### User Story 2 - Petugas Menentukan Lokasi Kejadian via Coordinate Picker (Priority: P1)

Petugas menggunakan peta interaktif pada form untuk menentukan titik lokasi kejadian kasus PD3I. Peta mendukung: klik titik di peta, gunakan GPS "Titik Lokasi Ini", atau isi koordinat manual. Setelah titik dipilih, field kecamatan, kelurahan, dan RT terisi otomatis berdasarkan batas wilayah pada peta — terpisah dari isian domisili KTP pasien.

**Why this priority**: Lokasi koordinat digunakan untuk statistik dashboard (bukan domisili KTP), sehingga akurasi titik koordinat kritis untuk analisis spasial kasus.

**Independent Test**: Dapat diuji dengan menempatkan titik di peta pada RT/kelurahan yang berbeda dan memverifikasi autofill kecamatan/kelurahan/RT selalu sesuai — termasuk saat titik dipindah berkali-kali.

**Acceptance Scenarios**:

1. **Given** petugas membuka peta, **When** menekan tombol "Titik Lokasi Ini", **Then** browser meminta izin GPS dan titik berpindah ke lokasi perangkat dengan zoom in.
2. **Given** titik sudah ada di peta, **When** petugas mengklik titik berbeda, **Then** field kecamatan, kelurahan, RT diperbarui sesuai batas wilayah titik baru — tidak error.
3. **Given** petugas mengisi field Latitude dan Longitude secara manual, **When** menekan tombol "Sesuaikan", **Then** peta menampilkan titik di koordinat tersebut dengan zoom tinggi.
4. **Given** peta dalam kondisi zoom out jauh, **When** banyak RT ditampilkan, **Then** label nama RT tidak saling bertumpuk — ukuran teks menyesuaikan zoom.

---

### User Story 3 - Petugas Melengkapi Tab-Tab Form MR01 yang Diperbarui (Priority: P2)

Petugas mengisi form MR01 multi-tab yang diperbarui: Tab E (riwayat imunisasi per antigen dengan ya/tidak + sumber + tanggal), Tab G (tempat berobat MoD: bisa tambah lebih dari satu), Tab J (input kontak erat MoD, field faskes pelapor dihapus), Tab C (lokasi penularan jadi text biasa), Tab F (spesimen MoD dengan data lab tambahan). Halaman 2 MR01 tersedia namun data belum diisi — hanya tampilan desain.

**Why this priority**: Tab-tab ini membentuk isi laporan surveilans yang lengkap; ketidaklengkapan menyebabkan laporan ditolak.

**Independent Test**: Dapat diuji dengan mengisi setiap tab secara terpisah, menyimpan, dan memverifikasi data tersimpan dan tampil kembali dengan benar.

**Acceptance Scenarios**:

1. **Given** petugas membuka Tab E, **When** mengisi riwayat imunisasi per antigen, **Then** setiap antigen memiliki pilihan ya/tidak, field sumber informasi, dan field tanggal imunisasi.
2. **Given** petugas berada di Tab G, **When** menekan "Tambah Tempat Berobat", **Then** deret field baru muncul (jenis faskes, nama faskes, tanggal berobat, jenis perawatan, tanggal keluar).
3. **Given** petugas berada di Tab J, **When** menekan "Tambah Kontak Erat", **Then** field input kontak erat baru muncul; field "Faskes Pelapor" tidak ada lagi.
4. **Given** petugas membuka Tab C, **When** melihat field lokasi penularan, **Then** field tersebut adalah text biasa (bukan coordinate picker).
5. **Given** petugas membuka Tab F (spesimen), **When** menambah spesimen, **Then** setiap spesimen memiliki: field lama + tanggal kirim, tanggal terima lab, status pemeriksaan, jenis penyakit terkonfirmasi (dropdown 5 penyakit), nama variant/genotype/serotype.
6. **Given** petugas mencetak/mengekspor MR01, **When** memilih generate PDF, **Then** halaman 2 tampil dalam desain (tanpa data nyata, placeholder saja).

---

### User Story 4 - Petugas Tempat Kerja/Sekolah Diintegrasikan ke Tab A (Priority: P2)

Field "Tempat Kerja / Sekolah / PAUD / TPA" di Tab A berfungsi seperti coordinate picker lokasi penularan (sebelumnya di Tab C). Petugas bisa menentukan titik lokasi tempat kerja/sekolah di peta sekaligus mengisi nama tempatnya.

**Why this priority**: Memindahkan coordinate picker ke Tab A menyederhanakan alur pengisian dan konsisten dengan tujuan analisis "kasus di tempat umum".

**Independent Test**: Dapat diuji dengan mengisi Tab A, menyematkan titik di peta untuk tempat kerja, dan memverifikasi data koordinat tersimpan bersama data Tab A.

**Acceptance Scenarios**:

1. **Given** petugas berada di Tab A, **When** mengisi field Tempat Kerja/Sekolah/PAUD/TPA, **Then** tersedia coordinate picker seperti yang sebelumnya ada di Tab C.
2. **Given** petugas membuka Tab C, **When** melihat field lokasi penularan, **Then** field tersebut adalah text input biasa tanpa peta.

---

### User Story 5 - Petugas & Admin Mengelola Tabel Data Surveilans PD3I dengan Filter Wilker (Priority: P2)

Pengguna dengan akun puskesmas melihat tabel data surveilans PD3I yang dapat difilter berdasarkan wilayah kerja puskesmas mereka. Mereka dapat menambah, melihat, dan mengedit semua data surveilans. Penghapusan data hanya dapat dilakukan oleh admin Dinkes.

**Why this priority**: Kontrol akses yang jelas mencegah penghapusan data tidak sengaja dan memastikan integritas data surveilans jangka panjang.

**Independent Test**: Dapat diuji dengan login sebagai user puskesmas dan memverifikasi tombol hapus tidak muncul; login sebagai admin Dinkes dan verifikasi tombol hapus muncul.

**Acceptance Scenarios**:

1. **Given** user puskesmas login dan membuka tabel surveilans, **When** memilih filter "Wilker Saya", **Then** hanya kasus yang wilker-nya sesuai puskesmas user yang ditampilkan.
2. **Given** user puskesmas melihat tabel, **When** melihat baris data, **Then** tombol Tambah dan Edit tersedia, tombol Hapus tidak ada.
3. **Given** admin Dinkes login dan membuka tabel surveilans, **When** melihat baris data, **Then** tombol Hapus tersedia di setiap baris.
4. **Given** user puskesmas ingin melihat laporan yang dilaporkan ke mereka, **When** memilih filter "Dilaporkan ke Saya" (alternatif dari filter wilker), **Then** data yang relevan tampil — hanya salah satu filter aktif sekaligus.

---

### User Story 6 - Import PD3I & Kohort dengan NIK Dummy untuk Sasaran Tanpa NIK (Priority: P3)

Saat melakukan import data PD3I atau kohort, sasaran yang tidak memiliki NIK diberikan NIK dummy yang digenerate secara otomatis mengikuti format NIK standar. NIK dummy dapat dikenali dari 4 digit terakhir yang diawali angka 9. Pada tampilan detail sasaran, NIK dummy ditandai dengan flag visual "NIK Dummy".

**Why this priority**: Integrasi data historis penting namun bukan blocker untuk fitur inti surveilans; dapat diimplementasikan setelah fitur utama berjalan.

**Independent Test**: Dapat diuji dengan mengimpor file yang berisi baris tanpa NIK dan memverifikasi NIK dummy terbuat dengan format benar serta flag muncul di halaman detail.

**Acceptance Scenarios**:

1. **Given** file import memiliki baris dengan kolom NIK kosong, **When** proses import dijalankan, **Then** sistem mengenerate NIK dummy dengan format: [6 digit kode wilayah][6 digit tanggal lahir, +40 hari jika perempuan][4 digit urutan diawali 9 → 9001, 9002, dst].
2. **Given** sasaran dengan NIK dummy tersimpan, **When** admin membuka halaman detail sasaran tersebut, **Then** muncul label/badge "NIK Dummy" di dekat field NIK.
3. **Given** NIK dummy sudah digenerate, **When** import dijalankan ulang dengan data yang sama, **Then** sistem tidak membuat duplikat NIK dummy untuk sasaran yang sama.

---

### Edge Cases

- Apa yang terjadi jika koordinat GPS tidak tersedia (perangkat tidak mendukung atau izin ditolak)?
- Bagaimana jika kelurahan yang dipilih tidak ada dalam pemetaan wilker puskesmas?
- Jika titik koordinat ditempatkan di luar batas wilayah terdefinisi: titik dikembalikan ke posisi sebelumnya dan muncul pesan "Titik di luar wilayah yang didukung".
- Bagaimana jika nomor urut NIK dummy sudah mencapai 9999 (batas 4 digit)?
- Apa yang terjadi jika user puskesmas mencoba mengakses URL penghapusan data surveilans secara langsung (bypass UI)?
- Bagaimana jika petugas menambah terlalu banyak spesimen atau tempat berobat (tidak ada batas yang ditetapkan)?

---

## Requirements *(mandatory)*

### Functional Requirements

**[A] Form Input Kasus PD3I**

- **FR-001**: Sistem HARUS mengisi field Wilker Puskesmas secara otomatis berdasarkan kelurahan pasien yang dipilih, menggunakan pemetaan kelurahan-ke-puskesmas yang sudah ditentukan.
- **FR-002**: Pemetaan wilker yang digunakan adalah:
  - Bontang Utara 1: API-API, BONTANG BARU, GUNUNG ELAI, BONTANG KUALA
  - Bontang Utara 2: GUNTUNG, LOK TUAN
  - Bontang Barat: BELIMBING, KANAAN, GUNUNG TELIHAN
  - Bontang Lestari: BONTANG LESTARI
  - Bontang Selatan 1: TANJUNG LAUT, TANJUNG LAUT INDAH, SATIMPO
  - Bontang Selatan 2: BERBAS PANTAI, BEREBAS TENGAH
- **FR-003**: Coordinate picker HARUS menyediakan tombol "Titik Lokasi Ini" yang meminta izin GPS dan memindahkan titik ke lokasi pengguna.
- **FR-004**: Saat titik di peta dipindahkan (oleh klik atau GPS), sistem HARUS memperbarui field kecamatan, kelurahan, dan RT berdasarkan batas wilayah — tanpa error saat pengguna berganti-ganti titik. Jika titik ditempatkan di luar batas wilayah yang terdefinisi, sistem HARUS mengembalikan titik ke posisi sebelumnya dan menampilkan pesan "Titik di luar wilayah yang didukung".
- **FR-005**: Label nama RT pada peta HARUS responsif terhadap zoom: tidak bertumpuk saat zoom out, terbaca dan proporsional saat zoom in.
- **FR-006**: Pengguna HARUS dapat mengisi field Latitude dan Longitude secara manual, dan tombol "Sesuaikan" HARUS memindahkan tampilan peta ke koordinat tersebut dengan zoom tinggi.
- **FR-007**: Tab E HARUS menampilkan daftar antigen imunisasi, masing-masing dengan pilihan ya/tidak, field sumber informasi, dan field tanggal imunisasi.
- **FR-008**: Tab G HARUS menggunakan pola Multiple on Demand (MoD): pengguna dapat menambah deret field tempat berobat (jenis faskes, nama faskes, tanggal berobat, jenis perawatan, tanggal keluar) dengan menekan tombol "Tambah Tempat Berobat".
- **FR-009**: Tab J HARUS menggunakan pola MoD untuk input kontak erat sesuai kebutuhan form MR01; field "Faskes Pelapor" HARUS dihapus dari Tab J.
- **FR-010**: Field coordinate picker untuk lokasi penularan HARUS dipindahkan dari Tab C ke field "Tempat Kerja / Sekolah / PAUD / TPA" di Tab A; field lokasi penularan di Tab C HARUS diubah menjadi field teks biasa.
- **FR-011**: Tab F (spesimen) HARUS menggunakan pola MoD; setiap spesimen HARUS memiliki field-field berikut: jenis spesimen, tanggal ambil spesimen, tanggal kirim sampel, tanggal terima lab, status pemeriksaan (teks), jenis penyakit terkonfirmasi (dropdown: Campak, Difteri, Pertusis, AFP/Polio, Tetanus Neonatorum), dan nama variant/genotype/serotype (teks).
- **FR-012**: Halaman 2 form MR01 HARUS tersedia dalam tampilan/PDF dengan desain sesuai dokumen di `/docs/Export formulir` — placeholder data diperbolehkan (data belum siap).

**[B] Tabel Data Surveilans PD3I**

- **FR-013**: Pengguna dengan peran puskesmas HARUS dapat memfilter tabel surveilans berdasarkan wilayah kerja puskesmas mereka ATAU berdasarkan laporan yang ditujukan ke mereka (tidak keduanya sekaligus).
- **FR-014**: Pengguna dengan peran puskesmas HARUS dapat menambah, melihat, dan mengedit semua data surveilans. Kasus langsung aktif saat disimpan — tidak ada status draft atau alur persetujuan.
- **FR-015**: Penghapusan data surveilans HANYA boleh dilakukan oleh pengguna dengan peran admin Dinkes — pengguna puskesmas tidak boleh memiliki akses hapus meskipun mengakses URL langsung.

**[C] NIK Dummy pada Import**

- **FR-016**: Saat import data PD3I atau kohort, baris dengan NIK kosong HARUS digenerate NIK dummy dengan format: [6 digit kode wilayah] + [6 digit tanggal lahir; jika perempuan, hari ditambah 40] + [4 digit nomor urut diawali 9: 9001, 9002, ...].
- **FR-017**: Halaman detail sasaran dengan NIK dummy HARUS menampilkan flag/badge "NIK Dummy" yang terlihat jelas di dekat NIK (identifikasi: 4 digit terakhir NIK diawali angka 9).
- **FR-018**: Sistem HARUS mencegah duplikasi NIK dummy saat import dijalankan ulang. Identifikasi "sasaran yang sama" menggunakan kombinasi: nama lengkap (fuzzy match ≥87%) + tanggal lahir (exact) + jenis kelamin (exact). Jika cocok, NIK dummy yang sudah ada dipakai ulang — tidak dibuat yang baru.

### Key Entities

- **Kasus PD3I**: Data kasus penyakit PD3I dengan lokasi koordinat (titik kejadian), domisili KTP (terpisah), data klinis, riwayat imunisasi, spesimen, kontak erat, dan tempat berobat.
- **Wilker Puskesmas**: Pemetaan statis dari kelurahan ke nama puskesmas yang bertanggung jawab.
- **Spesimen (MoD)**: Entitas anak dari kasus PD3I — bisa banyak per kasus; memiliki data pengiriman, penerimaan lab, dan hasil konfirmasi.
- **Tempat Berobat (MoD)**: Entitas anak dari kasus PD3I — bisa banyak; mencatat riwayat kunjungan faskes.
- **Kontak Erat (MoD)**: Entitas anak dari kasus PD3I — bisa banyak; dicatat di Tab J.
- **NIK Dummy**: NIK yang digenerate sistem untuk sasaran tanpa NIK; dapat diidentifikasi dari format 4 digit terakhir diawali angka 9.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Petugas puskesmas dapat menyelesaikan pengisian form kasus PD3I lengkap (semua tab) dalam waktu di bawah 15 menit untuk kasus standar.
- **SC-002**: Field Wilker terisi otomatis dalam kurang dari 1 detik setelah kelurahan dipilih — tanpa interaksi manual tambahan.
- **SC-003**: Pemindahan titik koordinat di peta (termasuk berganti-ganti titik) tidak menyebabkan error pada 100% skenario pengujian.
- **SC-004**: Label RT pada peta terbaca dan tidak bertumpuk pada semua level zoom yang tersedia.
- **SC-005**: Pengguna puskesmas tidak dapat menghapus data surveilans dalam kondisi apapun — termasuk akses langsung ke URL penghapusan.
- **SC-006**: Import file dengan NIK kosong menghasilkan NIK dummy yang valid (sesuai format) untuk 100% baris yang terdampak.
- **SC-007**: Flag "NIK Dummy" tampil di halaman detail untuk semua sasaran dengan NIK dummy — tidak ada yang terlewat.
- **SC-008**: Setiap pola Multiple on Demand (MoD) di Tab G, J, dan F dapat menambah minimal 10 entri tanpa degradasi performa yang terasa.

---

## Clarifications

### Session 2026-04-10

- Q: Apakah kasus PD3I memiliki status/lifecycle workflow? → A: Tidak ada status — semua kasus langsung aktif saat disimpan.
- Q: Dari mana sumber data geospasial batas wilayah (RT/kelurahan/kecamatan) untuk autofill koordinat peta? → A: File GeoJSON/Shapefile lokal yang di-load oleh server.
- Q: Apa yang terjadi jika titik koordinat ditempatkan di luar batas wilayah yang terdefinisi? → A: Titik dikembalikan ke posisi sebelumnya — penempatan di luar batas tidak diizinkan.
- Q: Field apa saja yang sudah ada ("field lama") di Tab F spesimen? → A: Jenis spesimen dan tanggal ambil spesimen.
- Q: Bagaimana sistem mengidentifikasi "sasaran yang sama" saat re-import untuk mencegah duplikasi NIK dummy? → A: Kombinasi nama lengkap (fuzzy match ≥87%) + tanggal lahir + jenis kelamin.

---

## Assumptions

- Pemetaan kelurahan ke wilker puskesmas bersifat statis dan mengikuti tabel yang sudah didefinisikan di `tambahan.md`; tidak ada kelurahan yang berada di lebih dari satu wilker.
- Batas wilayah RT/kelurahan/kecamatan tersedia sebagai file GeoJSON/Shapefile lokal yang di-load oleh server — bukan dari API eksternal atau tabel database geometry.
- Lima penyakit yang diawasi untuk dropdown Tab F adalah: Campak, Difteri, Pertusis, AFP/Polio, dan Tetanus Neonatorum.
- "Admin Dinkes" adalah role yang sudah ada dalam sistem; tidak perlu role baru.
- Desain halaman 2 MR01 mengacu pada dokumen di `/docs/Export formulir` — data aktual akan ditambahkan di iterasi berikutnya.
- Kode wilayah 6 digit untuk NIK dummy diambil dari data wilayah yang sudah tersimpan di sistem (tabel kecamatan/kelurahan).
- Antigen imunisasi untuk Tab E mengikuti daftar yang sudah ada di sistem (tidak perlu daftar baru).

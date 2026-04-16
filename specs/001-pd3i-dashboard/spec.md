# Feature Specification: Dashboard Surveilans PD3I

**Feature Branch**: `001-pd3i-dashboard`
**Created**: 2026-04-11
**Status**: Draft
**Input**: User description: "pd3i dashboard plan md"

## Clarifications

### Session 2026-04-11

- Q: Apakah peran `admin` dan `dinas` adalah entitas berbeda dengan pembatasan data berbeda? → A: Hanya satu peran (`super-admin`) yang merupakan pengguna Dinas Kesehatan (dinkes). Tidak ada pembatasan data per peran; semua data seluruh Puskesmas dapat dilihat.
- Q: Saat filter global diubah, data tab mana yang diperbarui? → A: Semua 4 tab diperbarui sekaligus saat filter diubah; navigasi antar tab instan tanpa loading tambahan.
- Q: Apa yang ditampilkan saat data sedang dimuat? → A: Spinner/skeleton per komponen grafik; tab tetap dapat dibuka kapan saja — tidak ada blokir navigasi.
- Q: Apakah fitur export PDF termasuk dalam scope dashboard ini? → A: In-scope — satu tombol export PDF di header dashboard yang menghasilkan satu file PDF berisi semua grafik dan scorecard dari keempat tab sekaligus.
- Q: Berapa perkiraan jumlah kasus surveilans PD3I per tahun? → A: Ratusan kasus per tahun (kota kecil ~170 ribu penduduk); tabel wilayah tidak memerlukan paginasi, semua data dapat dimuat sekaligus.

## User Scenarios & Testing *(mandatory)*

### User Story 1 – Melihat Kinerja Surveilans (Priority: P1)

Sebagai petugas epidemiologi atau pengelola data Puskesmas, saya ingin melihat ringkasan kinerja surveilans PD3I (jumlah suspek, konfirmasi, kematian, persentase pengambilan sampel, dan positivity rate) berdasarkan filter tahun, jenis penyakit, dan Puskesmas — sehingga saya dapat menilai kelengkapan pelaporan dan respons kasus secara cepat.

**Why this priority**: Kinerja surveilans adalah inti dari pemantauan PD3I. Tanpa data ini, tujuan utama dashboard tidak terpenuhi.

**Independent Test**: Dapat diuji dengan membuka tab Kinerja Surveilans, memilih filter, dan memverifikasi scorecard sesuai data di tabel `surveillance_cases`.

**Acceptance Scenarios**:

1. **Given** pengguna membuka dashboard PD3I, **When** memilih tahun dan jenis penyakit, **Then** scorecard menampilkan jumlah suspek, konfirmasi, discarded, kematian, % pengambilan sampel, % hasil lab diterima, dan positivity rate yang sesuai.
2. **Given** tidak ada kasus di rentang filter yang dipilih, **When** dashboard dimuat, **Then** semua scorecard menampilkan angka nol tanpa error.
3. **Given** filter Puskesmas dipilih, **When** dashboard dimuat, **Then** scorecard hanya mencerminkan data dari Puskesmas tersebut.

---

### User Story 2 – Melihat Tren Kasus (Priority: P2)

Sebagai petugas epidemiologi, saya ingin melihat tren kasus PD3I dalam bentuk kurva epidemi mingguan dan tren bulanan — per faskes, kecamatan, dan kelurahan — sehingga saya dapat mengidentifikasi pola penularan dan puncak wabah.

**Why this priority**: Analisis tren adalah langkah kedua setelah mengetahui angka keseluruhan; membantu perencanaan respons dan intervensi.

**Independent Test**: Dapat diuji dengan memilih tab Tren, memilih filter tahun, dan memverifikasi grafik sesuai distribusi data `tanggal_onset` dan `tanggal_lapor`.

**Acceptance Scenarios**:

1. **Given** data kasus tersedia untuk tahun yang dipilih, **When** pengguna membuka tab Tren, **Then** kurva epidemi mingguan menampilkan batang per epiweek dengan pembedaan suspek dan konfirmasi.
2. **Given** filter tahun dipilih, **When** melihat tren bulanan, **Then** grafik menampilkan jumlah laporan per bulan dikelompokkan per faskes pelapor.
3. **Given** data kelurahan tersedia, **When** melihat tren wilayah, **Then** grafik stacked bar mengelompokkan kasus per kelurahan dalam tiap kecamatan.

---

### User Story 3 – Melihat Demografi Kasus (Priority: P2)

Sebagai petugas epidemiologi, saya ingin melihat distribusi kasus berdasarkan kelompok umur, status vaksinasi, status rawat inap, komplikasi, dan angka kematian — sehingga saya dapat menilai kelompok rentan dan beban penyakit.

**Why this priority**: Data demografi dibutuhkan untuk memprioritaskan intervensi vaksinasi dan klinis.

**Independent Test**: Dapat diuji dengan membuka tab Demografi dan memverifikasi grafik umur, pie chart status vaksinasi, dan panel komplikasi sesuai data kasus yang difilter.

**Acceptance Scenarios**:

1. **Given** data kasus tersedia, **When** pengguna membuka tab Demografi, **Then** bar chart menampilkan distribusi kasus ke 8 kelompok umur (< 6 bulan hingga ≥ 15 tahun) dengan kolom terpisah untuk suspek, konfirmasi, dan discarded.
2. **Given** data riwayat imunisasi tersedia, **When** melihat pie chart status vaksinasi, **Then** grafik memisahkan kasus berdasarkan 4 kategori: tidak ada, tidak lengkap, lengkap, tidak tahu.
3. **Given** data komplikasi tersedia, **When** melihat panel severity, **Then** horizontal bar chart menampilkan 8 jenis komplikasi beserta jumlah kasusnya.

---

### User Story 4 – Melihat Distribusi Wilayah (Priority: P3)

Sebagai pengelola program, saya ingin melihat tabel persebaran kasus per Puskesmas, kecamatan, dan kelurahan, serta peta sebaran kasus — sehingga saya dapat mengidentifikasi wilayah dengan beban kasus tertinggi.

**Why this priority**: Analisis spasial melengkapi dashboard namun bukan kebutuhan kritis pertama; berguna untuk alokasi sumber daya.

**Independent Test**: Dapat diuji dengan membuka tab Wilayah, memverifikasi tabel agregasi per Puskesmas/kecamatan/kelurahan, dan memastikan peta menampilkan marker sesuai koordinat kasus.

**Acceptance Scenarios**:

1. **Given** data kasus tersedia, **When** pengguna membuka tab Wilayah, **Then** tabel menampilkan jumlah suspek, konfirmasi, dan kematian per Puskesmas.
2. **Given** data kelurahan tersedia, **When** melihat tabel per kelurahan, **Then** data dikelompokkan dalam kecamatan masing-masing.
3. **Given** kasus memiliki koordinat GPS, **When** peta dimuat, **Then** setiap kasus ditampilkan sebagai marker di peta; kasus tanpa koordinat tidak menyebabkan error.

---

- Bagaimana tampilan saat data sedang dimuat? → Setiap komponen grafik/scorecard menampilkan spinner atau placeholder skeleton sampai data API-nya selesai; pengguna tetap bisa berpindah tab kapan saja.

### Edge Cases

- Apa yang terjadi jika tidak ada kasus untuk kombinasi filter yang dipilih? → Semua komponen menampilkan nilai nol / grafik kosong dengan pesan informatif.
- Bagaimana jika kasus tidak memiliki koordinat GPS (`latitude`/`longitude` NULL)? → Kasus tersebut tidak ditampilkan di peta tanpa menyebabkan error.
- Bagaimana jika `tanggal_lahir` NULL saat menghitung kelompok umur? → Kasus dikategorikan sebagai "Tidak Diketahui" dalam distribusi umur.
- Bagaimana jika ada penyakit baru yang belum ada di filter? → Filter penyakit secara dinamis mengambil nilai dari tabel `jenis_kasus_epidemiologi`.
- Apa yang ditampilkan untuk Non Polio AFP Rate jika data populasi belum tersedia? → Ditampilkan "–" (tidak tersedia) dengan keterangan singkat.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Sistem HARUS menampilkan halaman dashboard PD3I yang dapat diakses oleh pengguna dengan peran `super-admin` (pengguna Dinas Kesehatan). Dashboard tidak membatasi data berdasarkan peran — semua data seluruh Puskesmas dapat dilihat.
- **FR-002**: Sistem HARUS menyediakan filter global (tahun, jenis penyakit, Puskesmas) yang berlaku pada semua tab dashboard.
- **FR-003**: Sistem HARUS menampilkan Tab Kinerja Surveilans dengan scorecard per penyakit: total suspek, terkonfirmasi, discarded, kematian, % pengambilan sampel, % hasil lab diterima, dan positivity rate.
- **FR-004**: Kinerja Surveilans HARUS mengelompokkan scorecard ke dalam 4 panel penyakit: Campak-Rubella, AFP/Polio, Difteri, Pertusis.
- **FR-005**: Sistem HARUS menampilkan Tab Tren dengan kurva epidemi mingguan berdasarkan `tanggal_onset`.
- **FR-006**: Tab Tren HARUS menampilkan tren laporan bulanan berdasarkan `tanggal_lapor`, dikelompokkan per faskes pelapor dan per kecamatan.
- **FR-007**: Tab Tren HARUS menampilkan tren per kelurahan sebagai grafik stacked bar yang dikelompokkan dalam kecamatan.
- **FR-008**: Sistem HARUS menampilkan Tab Demografi dengan distribusi kasus berdasarkan 8 kelompok umur, dihitung dari `tanggal_lahir` relatif terhadap `tanggal_onset`.
- **FR-009**: Tab Demografi HARUS menampilkan pie chart status vaksinasi dengan 4 kategori: tidak ada, tidak lengkap, lengkap, tidak tahu.
- **FR-010**: Tab Demografi HARUS menampilkan panel severity yang mencakup % rawat inap, horizontal bar chart 8 komplikasi, dan angka case fatality.
- **FR-011**: Sistem HARUS menampilkan Tab Wilayah dengan tabel agregasi kasus per Puskesmas, kecamatan, dan kelurahan (suspek, konfirmasi, kematian).
- **FR-012**: Tab Wilayah HARUS menampilkan peta interaktif dengan marker per kasus yang memiliki koordinat GPS.
- **FR-013**: Semua data dashboard HARUS diambil melalui endpoint API terpisah per tab (kinerja, demografi, tren, wilayah) secara paralel saat halaman pertama kali dimuat atau saat filter diubah, sehingga semua tab tersedia tanpa loading saat pengguna berpindah tab.
- **FR-014**: Untuk Non Polio AFP Rate, sistem HARUS menampilkan nilai "–" dengan keterangan "Data populasi belum tersedia" hingga data populasi dimasukkan.
- **FR-015**: Filter tahun HARUS default ke tahun berjalan saat halaman pertama kali dibuka.
- **FR-016**: Dashboard HARUS menyediakan satu tombol "Export PDF" di header yang menghasilkan satu file PDF berisi semua grafik dan scorecard dari keempat tab (Kinerja Surveilans, Demografi, Tren, Wilayah) sesuai filter yang sedang aktif.

### Key Entities

- **SurveillanceCase** (`surveillance_cases`): Entitas utama kasus PD3I — mencakup status kasus, jenis penyakit, tanggal onset/lapor, data lab, kondisi akhir, riwayat imunisasi, komplikasi, rawat inap, dan koordinat GPS.
- **JenisKasusEpidemiologi** (`jenis_kasus_epidemiologi`): Daftar jenis penyakit PD3I — digunakan sebagai sumber filter penyakit.
- **Kecamatan / Kelurahan**: Entitas geografis — digunakan untuk agregasi wilayah dan tren per area.
- **RumahSakit / Faskes** (`rumah_sakits`): Fasilitas kesehatan pelapor — digunakan untuk tren per faskes.
- **WilkerPuskesmas**: Wilayah kerja Puskesmas — digunakan sebagai dimensi filter dan agregasi.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Pengguna dapat melihat semua scorecard kinerja surveilans dalam satu layar tanpa perlu scroll saat menggunakan layar desktop standar.
- **SC-002**: Semua tab dashboard memuat data dan menampilkan grafik dalam kurang dari 3 detik pada kondisi jaringan normal.
- **SC-003**: Pengguna dapat mengubah kombinasi filter (tahun + penyakit + Puskesmas) dan semua 4 tab diperbarui sekaligus secara asinkron; pengguna dapat langsung berpindah ke tab mana pun tanpa loading tambahan.
- **SC-004**: Seluruh 4 tab (Kinerja, Demografi, Tren, Wilayah) dapat diakses dan menampilkan data yang akurat tanpa error pada 100% kunjungan dengan data tersedia.
- **SC-005**: Peta persebaran kasus menampilkan marker untuk semua kasus yang memiliki koordinat GPS tanpa kehilangan titik data.
- **SC-006**: Data pada dashboard konsisten dengan data di halaman daftar kasus PD3I — tidak ada perbedaan angka antara dua tampilan untuk filter yang sama.

## Assumptions

- Semua data kasus PD3I tersimpan di tabel `surveillance_cases` dan sudah memiliki relasi ke `jenis_kasus_epidemiologi`, `kecamatan`, `kelurahan`, dan `rumah_sakits`.
- Volume data diperkirakan ratusan kasus per tahun; tabel wilayah tidak memerlukan paginasi.
- Pengguna yang mengakses dashboard adalah satu entitas: peran `super-admin` yang merupakan pengguna Dinas Kesehatan (dinkes). Tidak ada pembatasan data per peran.
- Kolom `latitude` dan `longitude` sudah ada di tabel `surveillance_cases` (dari migrasi `2026_03_04`).
- Chart.js sudah tersedia atau dapat ditambahkan ke project untuk rendering grafik.
- Leaflet.js akan digunakan untuk peta interaktif di Tab Wilayah.
- Kolom `komplikasi_dbd` belum ada — tidak akan ditampilkan di dashboard sampai migrasi baru dibuat.
- Non Polio AFP Rate tidak dapat dihitung tanpa data populasi eksternal; ditampilkan sebagai "–".

## Dependencies

- Tabel `surveillance_cases` terisi dengan data kasus PD3I yang valid.
- Relasi FK ke `jenis_kasus_epidemiologi`, `kecamatan`, `kelurahan`, `rumah_sakits` berfungsi dengan benar.
- Middleware autentikasi dan otorisasi peran `super-admin` sudah aktif.

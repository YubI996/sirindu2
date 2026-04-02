# Pertanyaan Klarifikasi Berdasarkan Gaps & Ambiguities

**Dari**: Requirement Quality Checklist (general.md)  
**Tanggal**: 2026-04-02  
**Tujuan**: Konfirmasi item-item gap dan ambiguitas sebelum lanjut implementasi

---

## GAPS (Persyaratan yang belum didokumentasikan)

### G1. Persyaratan Vaksin Spesifik Gender (Gap dari CHK006)

**Pertanyaan**:
Untuk vaksin HPV dalam kelompok ISL (usia 7-12 tahun), apakah:
- HPV hanya diberikan kepada anak perempuan dan dilewatkan untuk anak laki-laki?
- Atau sistem tetap menandai sebagai "Belum Lengkap" untuk anak laki-laki yang belum menerima HPV?

**Konteks**: Spec menyebutkan ISL mencakup HPV1/HPV2, namun tidak jelas apakah ini gender-specific.

---

### G2. Formula Poin Prioritas Intervensi untuk Status Kejar (Gap dari CHK009)

**Pertanyaan**:
Untuk sistem Early Warning System yang sudah ada di `/admin/early-warning`:
- Berapa poin yang ditambahkan ke `risk_score` ketika anak berstatus "Kejar IDL" tunggal?
- Berapa poin yang ditambahkan ketika anak berstatus "Kejar IDL" dan "Kejar IBL" (dual kejar)?
- Apakah poin kejar dapat di-customize per wilayah atau fixed?

**Konteks**: Spec §FR-025 hanya menyatakan "menambah poin ke risk_score kumulatif" tanpa spesifikasi nilai.

---

### G3. Daftar Lengkap Kategori Lokasi Penularan (Gap dari CHK011)

**Pertanyaan**:
Apakah kategori lokasi penularan untuk dropdown sekolah/fasilitas umum adalah:
1. Sekolah
2. Tempat Kerja
3. Gym/Olahraga
4. Tempat Ibadah
5. Lainnya (custom)

Atau ada kategori tambahan yang perlu ditambahkan (misalnya: Pasar, Transportasi Umum, Rumah Sakit, dll)?

**Konteks**: Spec §FR-010 menyebutkan 4 kategori eksplisit + kemampuan custom, tapi tidak menyatakan apakah ini exhaustive.

---

### G4. Penanganan Data Lama Lokasi Penularan (Gap dari CHK015)

**Pertanyaan**:
Untuk kasus PD3I yang sudah ada dengan field `lokasi_penularan` berisi teks bebas (bukan dari dropdown):
- Apakah data lama tetap ditampilkan apa adanya di chart, atau harus di-kategorisasi manual ke salah satu kategori?
- Apakah ada batching process untuk migrasi data lama atau user harus update satu per satu?
- Apakah kasus lama tanpa lokasi penularan akan menunjukkan "Lainnya" atau ditampilkan sebagai empty?

**Konteks**: Spec §FR-011a menyatakan "data lama tetap dipertahankan dan kompatibel" namun mekanisme kompatibilitas tidak jelas.

---

### G5. Lingkup Geografis & Kode Area (Gap dari CHK028)

**Pertanyaan**:
- Sistem ini hanya untuk Kota Bontang atau akan diperluas ke area lain di masa depan?
- Jika diperluas, apakah kode wilayah `1710` akan tetap fixed untuk semua area, atau ada mapping per area?
- Apakah validasi input alamat hanya terbatas pada Kota Bontang saja?

**Konteks**: Spec Assumptions menyatakan Kota Bontang sebagai scope, tapi tidak jelas apakah ini permanent constraint.

---

### G6. Kontrol Akses & Peran Pengguna (Gap dari CHK080)

**Pertanyaan**:
- Siapa yang dapat membuat kasus PD3I baru (hanya Petugas Surveilans atau juga user lain)?
- Siapa yang dapat memodifikasi kelompok vaksin master data (Super Admin, Admin, atau Petugas Vaksin)?
- Siapa yang dapat melihat/mengunduh PDF dan Excel export (semua user atau role tertentu)?
- Apakah ada perbedaan akses berdasarkan wilayah (Puskesmas, Posyandu)?

**Konteks**: Spec tidak mendokumentasikan persyaratan kontrol akses per role/fitur.

---

### G7. Privasi Data & Perlindungan Informasi (Gap dari CHK081)

**Pertanyaan**:
- Apakah PDF dan Excel export mengandung PII (nama lengkap, NIK, alamat lengkap anak)?
- Jika iya, bagaimana perlindungan data (enkripsi, watermark, audit log)?
- Apakah ada batasan pada siapa yang dapat mengunduh export (hanya staff tertentu)?
- Apakah export harus di-log untuk audit trail?

**Konteks**: Spec tidak mendokumentasikan persyaratan privasi/keamanan data.

---

## AMBIGUITIES (Persyaratan yang ambigu atau tidak jelas)

### A1. Status "Kejar" untuk Kelompok ISL (Ambiguity dari CHK022)

**Pertanyaan**:
Untuk vaksin ISL yang diberikan usia 7-12 tahun dengan "tanpa masa kejar":
- Apakah anak usia 13+ tahun yang belum menerima ISL akan berstatus "Belum Lengkap" tapi TIDAK "Kejar ISL"?
- Atau anak usia 13+ sepenuhnya tidak termasuk dalam tracking ISL?

**Konteks**: Spec §FR-022 menyatakan ISL "tanpa masa kejar" namun tidak jelas dampaknya pada status setelah usia 12 tahun.

---

### A2. Formula Poin Kejar Vaksin Ganda (Ambiguity dari CHK095)

**Pertanyaan**:
Ketika anak memiliki "Kejar IDL" dan "Kejar IBL" secara bersamaan:
- Apakah sistem menambahkan poin KEDUA-DUANYA (poin IDL + poin IBL)?
- Atau apakah ada poin bonus untuk dual kejar yang lebih tinggi dari penjumlahan?
- Contoh: IDL kejar = +10 poin, IBL kejar = +10 poin, dual = +10 + +10 = +20, atau dual = +30?

**Konteks**: Spec §FR-025 menyatakan "poin lebih tinggi dari kejar tunggal" tapi formula exact tidak dijelaskan.

---

### A3. Input & Validasi Lokasi Penularan Custom (Ambiguity dari CHK096)

**Pertanyaan**:
Untuk fitur "menambah lokasi custom" dalam dropdown lokasi penularan:
- Apakah user dapat memasukkan teks bebas baru atau harus memilih kategori terlebih dahulu?
- Siapa yang dapat menambah lokasi baru (Petugas Surveilans, Admin, atau Super Admin)?
- Apakah lokasi custom yang ditambah satu user akan tersedia untuk user lain?
- Apakah ada validasi duplikasi (mencegah dua lokasi dengan nama sama)?

**Konteks**: Spec §FR-010 menyebutkan "kemampuan menambah lokasi custom" tapi mekanisme input tidak didefinisikan.

---

### A4. Definisi & Sumber Daftar Vaksin per Kelompok (Ambiguity dari CHK097)

**Pertanyaan**:
Untuk pengelompokan vaksin ke IDL/IBL/ISL:
- Apakah daftar vaksin didasarkan pada standar Kemenkes 2026 yang terkini, atau ada versi spesifik yang harus digunakan?
- Siapa yang bertanggung jawab update daftar vaksin jika standar Kemenkes berubah?
- Apakah ada mekanisme untuk menambah/menghapus vaksin dari kelompok setelah deployment?
- Jika vaksin baru ditambahkan, apakah harus di-assign ke kelompok saat pembuatan atau ada default?

**Konteks**: Spec Assumptions menyatakan "mengacu pada program imunisasi nasional Indonesia terkini" tapi sumber & ownership update tidak jelas.

---

### A5. Tampilan "Menonjol" Status Kelengkapan Vaksin di Profil Anak (Ambiguity dari CHK093)

**Pertanyaan**:
Untuk persyaratan "prominent display" (Spec §SC-005) status kelengkapan vaksin (Lengkap/Belum Lengkap) di profil anak:
- Apakah status ditampilkan di section terpisah atau inline dengan data vaksin existing?
- Apakah menggunakan badge warna (hijau=Lengkap, merah=Belum Lengkap), atau format lain?
- Apakah status kejar juga ditampilkan di profil atau hanya di Early Warning System?
- Apakah ada detail dropdown/expandable untuk lihat detail missing vaccines per group?

**Konteks**: Spec menyatakan "terlihat di profil anak" (Spec §US2) tapi layout/styling tidak didefinisikan.

---

### A6. Format & Layout PDF Formulir MR-01 (Ambiguity dari CHK094)

**Pertanyaan**:
Untuk PDF formulir investigasi MR-01:
- Apakah referensi desain sudah tersedia di repo (Spec menyebutkan `docs/Export formulir/form.png`) dan template itu yang digunakan?
- Jika tidak, apakah ada standar resmi Kemenkes yang harus diikuti atau bisa design sendiri selama mencakup 7 section?
- Apakah logo Kemenkes harus vector/image, dan di mana source-nya?
- Apakah font, spacing, margin ada spesifikasi atau bisa fleksibel selama MR-01 compliant?

**Konteks**: Spec §FR-013 menyatakan "format MR-01" tapi detail design tidak dijelaskan.

---

## RINGKASAN UNTUK KONFIRMASI

| # | Tipe | Item | Status |
|---|------|------|--------|
| G1 | Gap | Vaksin HPV gender-specific untuk ISL | ❓ |
| G2 | Gap | Formula poin kejar di EWS (nilai eksak) | ❓ |
| G3 | Gap | Daftar lengkap kategori lokasi penularan | ❓ |
| G4 | Gap | Migrasi data lokasi penularan lama | ❓ |
| G5 | Gap | Lingkup geografis & skalabilitas wilayah | ❓ |
| G6 | Gap | Kontrol akses per fitur & role | ❓ |
| G7 | Gap | Privacy & keamanan data di export | ❓ |
| A1 | Ambiguity | Status kejar ISL usia 13+ tahun | ❓ |
| A2 | Ambiguity | Formula poin dual kejar (IDL+IBL) | ❓ |
| A3 | Ambiguity | Mekanisme input lokasi custom | ❓ |
| A4 | Ambiguity | Definisi & sumber daftar vaksin | ❓ |
| A5 | Ambiguity | UI/styling status kelengkapan vaksin | ❓ |
| A6 | Ambiguity | Format detail PDF MR-01 | ❓ |

---

**Instruksi Penggunaan**:
1. Tinjau setiap pertanyaan dengan stakeholder/user
2. Dokumentasikan jawaban di kolom "Jawaban" atau langsung update spec.md
3. Tandai status setiap pertanyaan: ✅ (confirmed), ❌ (needs revision), atau ⏳ (pending clarification)
4. Update spec.md dengan findings sebelum lanjut ke implementation phase

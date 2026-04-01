# Research: Peningkatan Modul Surveilans PD3I & Kelompok Vaksin

**Date**: 2026-03-31  
**Branch**: `001-pd3i-vaksin`

## R-001: Pengelompokan Vaksin IDL/IBL/ISL

**Decision**: Menggunakan standar program imunisasi nasional Indonesia (Permenkes terkini) dengan penyesuaian untuk vaksin yang sudah ada di sistem.

**Rationale**: Sistem saat ini sudah memiliki 11 antigen IDL klasik (HB0, BCG, POLIO1-4, DPT-HB-HIB1-3, IPV, CAMPAK). Program nasional terbaru menambahkan IPV 2, PCV 1-2, Rotavirus 1-3. Untuk implementasi awal, menggunakan vaksin yang sudah ada di master data dan menambahkan vaksin baru yang diperlukan.

**Mapping Vaksin ke Kelompok:**

### IDL (Imunisasi Dasar Lengkap) - Usia 0-11 bulan, kejar s.d. 5 tahun
| Vaksin | Usia Pemberian | Keterangan |
|--------|---------------|------------|
| HB-0 | 0-24 jam lahir | Hepatitis B dosis awal |
| BCG | 1 bulan | Tuberkulosis |
| Polio (bOPV) 1 | 1 bulan | Oral Polio |
| Polio (bOPV) 2 | 2 bulan | |
| Polio (bOPV) 3 | 3 bulan | |
| Polio (bOPV) 4 | 4 bulan | |
| DPT-HB-Hib 1 | 2 bulan | Pentavalen |
| DPT-HB-Hib 2 | 3 bulan | |
| DPT-HB-Hib 3 | 4 bulan | |
| IPV 1 | 4 bulan | Injectable Polio |
| IPV 2 | 9 bulan | Tambahan jadwal baru |
| Campak-Rubella (MR) 1 | 9 bulan | Menggantikan Campak tunggal |
| PCV 1 | 2 bulan | Pneumococcal (baru) |
| PCV 2 | 3 bulan | (baru) |
| Rotavirus 1 | 2 bulan | (baru) |
| Rotavirus 2 | 3 bulan | (baru) |
| Rotavirus 3 | 4 bulan | (baru) |

### IBL (Imunisasi Booster Lengkap) - Usia 12-23 bulan, kejar s.d. 5 tahun
| Vaksin | Usia Pemberian | Keterangan |
|--------|---------------|------------|
| DPT-HB-Hib 4 (Booster) | 18 bulan | Booster pentavalen |
| Campak-Rubella (MR) 2 | 18 bulan | Dosis kedua MR |
| PCV 3 (Booster) | 12 bulan | Booster pneumococcal |

### ISL (Imunisasi Sekolah Lengkap) - Usia 7-12 tahun, tanpa masa kejar
| Vaksin | Kelas | Waktu | Keterangan |
|--------|-------|-------|------------|
| Campak-Rubella (MR) 3 | Kelas 1 SD | Agustus | Dosis ketiga MR |
| DT | Kelas 1 SD | November | Difteri-Tetanus |
| Td 1 | Kelas 2 SD | November | Tetanus-difteri |
| Td 2 | Kelas 5 SD | November | Tetanus-difteri |
| HPV 1 | Kelas 5 SD | Agustus | Khusus perempuan |
| HPV 2 | Kelas 6 SD | Agustus | Khusus perempuan |

**Catatan Implementasi**:
- Vaksin baru (IPV 2, PCV 1-2, Rotavirus 1-3, MR 2-3, DT, Td 1-2, HPV 1-2) perlu ditambahkan ke tabel `jenis_vaksin`
- "CAMPAK" yang sudah ada di sistem di-rename/mapping ke "Campak-Rubella (MR) 1"
- HPV hanya untuk anak perempuan - perlu logic khusus saat menghitung kelengkapan ISL

**Alternatives considered**: Hanya menggunakan vaksin yang sudah ada (11 antigen) - ditolak karena tidak sesuai standar nasional terkini.

---

## R-002: Auto-Generate Nomor Epidemiologi

**Decision**: Generate nomor saat `store()` menggunakan database transaction lock. Format: `[kode]-1710[YY][NNN]`.

**Rationale**: Field `no_registrasi` sudah ada di model (nullable, unique, max 50 chars). Saat ini tidak ada logika auto-generate. Implementasi menggunakan `DB::transaction()` dengan `lockForUpdate()` pada query MAX untuk mencegah race condition.

**Implementasi**:
- Query: `SELECT MAX(CAST(SUBSTRING(no_registrasi, -3) AS UNSIGNED)) FROM surveillance_cases WHERE no_registrasi LIKE '%1710{YY}%' AND YEAR(created_at) = {tahun}`
- Alternatif lebih reliable: tabel counter terpisah dengan row-level lock
- Generate di `SurveillanceRepository::storeCase()` sebelum insert

**Mapping kode penyakit** (dari `JenisKasusEpidemiologi.kode_penyakit`):
| Penyakit | Kode | Format |
|----------|------|--------|
| Campak | C | C-1710YYNNN |
| Difteri | D | D-1710YYNNN |
| Pertusis | P | P-1710YYNNN |
| AFP/Polio | (kosong) | 1710YYNNN |
| Tetanus Neonatorum | TN | TN-1710YYNNN |

**Alternatives considered**: 
- Generate saat form dibuka (ditolak - race condition lebih tinggi)
- UUID/ULID (ditolak - format harus sesuai standar pelaporan)

---

## R-003: Perubahan lokasi_penularan ke Dropdown

**Decision**: Ubah field `lokasi_penularan` (TEXT) menjadi dropdown searchable dengan data sekolah Kota Bontang (160 entri dari `docs/list sekolah di bontang.txt`) plus kategori fasilitas umum, dengan opsi custom input.

**Rationale**: Data terstruktur diperlukan untuk chart dashboard. Menggunakan Select2 atau komponen dropdown searchable yang sudah ada di project.

**Pendekatan**:
- Buat tabel master `lokasi_penularan_master` berisi: nama lokasi, kategori (Sekolah, Tempat Kerja, Gym, Tempat Ibadah, Lainnya)
- Seed dengan 160 sekolah dari file TXT (semua kategori "Sekolah")
- Field `lokasi_penularan` tetap TEXT di `surveillance_cases` tapi dipopulasi dari dropdown
- Opsi "Tambah Baru" memungkinkan input custom yang juga tersimpan di master
- Data lama tetap valid (string matching)

**Alternatives considered**: Enum/fixed list (ditolak - 160+ entri terlalu banyak untuk enum, perlu dinamis).

---

## R-004: Export PDF Formulir MR-01

**Decision**: Menggunakan package `barryvdh/laravel-dompdf` untuk generate PDF. Satu template Blade untuk semua jenis penyakit PD3I dengan judul dinamis.

**Rationale**: dompdf belum terinstall di project. `barryvdh/laravel-dompdf` adalah package standar Laravel untuk PDF generation dan mendukung HTML/CSS to PDF yang diperlukan untuk mereplikasi layout formulir MR-01.

**Layout PDF** (berdasarkan `docs/Export formulir/form.png`):
- Header: Logo Kemenkes + "FORM INVESTIGASI KASUS [NAMA PENYAKIT]" + MR-01
- Section: Provinsi, Kabupaten, Nomor Epid, Status KLB, Sumber Laporan, Tanggal
- Informasi Kasus: NIK, Nama, Tanggal Lahir, Umur, Alamat, Kelurahan, Kecamatan, Orangtua/Wali
- Informasi Klinis: Demam, Ruam, Gejala lain (checkbox-style), Komplikasi
- Riwayat Pengobatan: Status rawat, Nama RS, Rekam Medik
- Riwayat Imunisasi: Dosis 1-2, BIAS, MMR, Tanggal terakhir
- Informasi Epidemiologis: Vitamin A, Kontak, Perjalanan
- Informasi Laboratorium: Spesimen, Tanggal, Hasil
- Kondisi Akhir: Hidup/Meninggal/Lost to Follow Up

**Alternatives considered**: 
- TCPDF (ditolak - lebih kompleks, dompdf lebih sederhana untuk HTML-to-PDF)
- Snappy/wkhtmltopdf (ditolak - memerlukan binary eksternal)

---

## R-005: Skoring Kejar Vaksin di Early Warning System

**Decision**: Tambahkan faktor risiko kejar vaksin ke skoring `earlyWarningSystem()` di `AdminController`.

**Rationale**: Sistem EWS sudah menghitung skor berdasarkan: data pengukuran, stunting, wasting, underweight, overweight, dan vaksin kurang (+3/vaksin). Status kejar menambah layer prioritas tambahan.

**Skoring yang diusulkan**:
| Faktor | Poin |
|--------|------|
| Kejar IDL (IDL belum lengkap, usia >11 bln s.d. 5 tahun) | +15 |
| Kejar IBL (IBL belum lengkap, usia >23 bln s.d. 5 tahun) | +10 |
| Kejar ganda (IDL + IBL) | +25 (kumulatif) |

**Integrasi**: Ditambahkan di `AdminController::earlyWarningSystem()` setelah blok perhitungan vaksin yang sudah ada (sekitar line 1950-1970).

**Alternatives considered**: Poin lebih tinggi (30+) - ditolak karena harus proporsional dengan faktor risiko lain (severely stunted = 40, stunted = 25).

---

## R-006: Export Data Agregat Imunisasi

**Decision**: Menggunakan `Maatwebsite/Excel` dengan class export baru `AgregatImunisasiExport` yang menghasilkan file Excel multi-kolom per kelurahan.

**Rationale**: Package sudah terinstall dan sudah digunakan untuk export imunisasi per individu. Export agregat memerlukan grouping per kelurahan dengan sub-kolom per vaksin dan per kelompok.

**Struktur kolom**:
```
No | Kelurahan | [Per Vaksin: #L %L #P %P #Jml %Jml] | IDL [#L %L #P %P #Jml %Jml] | IBL [...] | ISL [...]
```

**Alternatives considered**: 
- fast-excel (ditolak - kurang fleksibel untuk multi-header complex layout)
- CSV (ditolak - format multi-header tidak didukung)

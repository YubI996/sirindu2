# Laporan Status Data Anak — Sirindu

**Tanggal laporan:** 6 Juli 2026
**Sumber:** Basis data produksi `sirindu` (tabel `anak`, `data_anak`)
**Sifat:** Agregat/statistik — tidak memuat data individu (identitas dirahasiakan)

---

## Ringkasan Eksekutif

| Indikator utama | Nilai |
|---|---|
| **Total anak terdaftar** | **14.507** |
| Balita sasaran (0–59 bulan) | 14.187 (97,8%) |
| Sudah terpetakan wilayah (kelurahan) | 9.857 (67,9%) |
| Belum terpetakan wilayah | 4.650 (32,1%) |
| Sudah ditimbang/diukur | 8.966 (61,8%) |
| **Prevalensi stunting** (dari yang terukur) | **11,8%** (1.060 anak) |
| **Prevalensi wasting/gizi kurang akut** | **5,6%** (500 anak) |
| **Prevalensi underweight** | **10,9%** (975 anak) |
| Cakupan data imunisasi | **0% — belum ada data** |

**Tiga hal yang perlu perhatian pimpinan:**
1. **Sepertiga anak (4.650) belum punya alamat domisili terstruktur** — mayoritas data Capil baru yang belum dipetakan ke kelurahan/posyandu, sehingga belum bisa masuk sasaran layanan per-wilayah.
2. **38% anak belum ditimbang** pada siklus ini — angka gizi hanya mewakili 8.966 anak yang terukur, bukan seluruh populasi.
3. **Modul imunisasi kosong total** — belum ada satu pun catatan imunisasi di sistem.

---

## 1. Populasi & Identitas

| Aspek | Jumlah | % |
|---|---:|---:|
| Total anak | 14.507 | 100% |
| Laki-laki | 7.430 | 51,2% |
| Perempuan | 7.077 | 48,8% |
| NIK 16-digit | 14.507 | 100% |
| — NIK asli (resmi) | 14.181 | 97,8% |
| — NIK dummy/sementara | 326 | 2,2% |

**Catatan:** Semua anak memiliki NIK 16-digit yang valid secara format. 326 anak memakai NIK dummy (sementara) karena belum memiliki NIK resmi dari Dukcapil — ini normal untuk bayi/anak yang belum terbit dokumen.

---

## 2. Kelengkapan Data (Kualitas Isi Kolom)

Persentase terhadap 14.507 anak:

| Kolom | Terisi | % | Status |
|---|---:|---:|:--:|
| NIK | 14.507 | 100% | ✅ |
| Nama | 14.507 | 100% | ✅ |
| Tanggal lahir | 14.507 | 100% | ✅ |
| Nama ibu | 14.427 | 99,4% | ✅ |
| No. KK | 13.944 | 96,1% | ✅ |
| Alamat KTP | 13.476 | 92,9% | ✅ |
| Nama ayah | 12.418 | 85,6% | 🟡 |
| Alamat domisili | 10.343 | 71,3% | 🟡 |
| Puskesmas | 9.858 | 68,0% | 🟡 |
| Kecamatan | 9.858 | 68,0% | 🟡 |
| Kelurahan | 9.857 | 67,9% | 🟡 |
| RT | 8.971 | 61,8% | 🟡 |
| No. HP | 8.331 | 57,4% | 🟠 |
| Posyandu | 7.512 | 51,8% | 🟠 |
| NIK ibu | 7.505 | 51,7% | 🟠 |
| **Tempat lahir** | **0** | **0%** | 🔴 |
| **Golongan darah** | **0** | **0%** | 🔴 |
| **NIK ayah** | **0** | **0%** | 🔴 |

**Temuan:** Tiga kolom (tempat lahir, golongan darah, NIK ayah) **tidak terisi sama sekali** — kemungkinan tidak tersedia di sumber import dan belum pernah diisi manual. Kolom wilayah (kecamatan/kelurahan/posyandu) terisi ~52–68%, sejalan dengan 4.650 anak yang belum dipetakan wilayahnya.

---

## 3. Cakupan Wilayah

| Status wilayah | Jumlah | % |
|---|---:|---:|
| Terpetakan sampai kelurahan | 9.857 | 67,9% |
| Belum terpetakan (tanpa kelurahan) | 4.650 | 32,1% |
| — di antaranya data Capil baru (punya alamat KTP, belum dipetakan) | 4.572 | — |

**Distribusi per kecamatan (dari yang terpetakan):**

| Kecamatan | Jumlah anak |
|---|---:|
| Bontang Utara | 4.291 |
| Bontang Selatan | 3.629 |
| Bontang Barat | 1.938 |
| *(belum terpetakan)* | 4.649 |

---

## 4. Struktur Umur (per 6 Juli 2026)

| Kelompok umur | Jumlah | % |
|---|---:|---:|
| 0–5 bulan | 1.033 | 7,1% |
| 6–11 bulan | 1.186 | 8,2% |
| 12–23 bulan | 2.526 | 17,4% |
| 24–59 bulan | 9.442 | 65,1% |
| 60–71 bulan (>5 tahun) | 320 | 2,2% |

**Balita sasaran (0–59 bulan): 14.187 anak (97,8%).** Tidak ada data anak di atas 6 tahun — populasi terkonsentrasi pada rentang balita, sesuai fokus program.

---

## 5. Cakupan Penimbangan/Pengukuran

| Aspek | Jumlah | % |
|---|---:|---:|
| Total baris pengukuran (`data_anak`) | 9.124 | — |
| Anak yang pernah diukur | 8.966 | 61,8% |
| Anak belum diukur | 5.541 | 38,2% |
| Diukur dalam 3 bulan terakhir | 8.964 | 99,98% dari yg terukur |

**Rentang tanggal kunjungan:** 26 Juni 2021 – 30 Juni 2026.

**Temuan:** Hampir seluruh data pengukuran **sangat segar** (99,98% dari Operasi Timbang Juni 2026). Namun **38% anak belum tersentuh pengukuran** pada siklus ini — mayoritas kemungkinan beririsan dengan anak yang belum dipetakan wilayahnya.

---

## 6. Status Gizi (Basis: 8.966 Anak Terukur)

> Prevalensi dihitung terhadap anak yang **terukur**, bukan seluruh 14.507. 2 pengukuran tidak valid (bb/tb ≤ 0) dikeluarkan.

### 6a. Tinggi Badan menurut Umur (TB/U) — indikator STUNTING
| Kategori | Jumlah | % |
|---|---:|---:|
| Sangat pendek (severely stunted) | 155 | 1,7% |
| Pendek (stunted) | 905 | 10,1% |
| **Total stunting** | **1.060** | **11,8%** |
| Normal | 7.876 | 87,9% |
| Tinggi | 28 | 0,3% |

### 6b. Berat Badan menurut Tinggi (BB/TB) — indikator WASTING & gizi lebih
| Kategori | Jumlah | % |
|---|---:|---:|
| Gizi buruk (severely wasted) | 20 | 0,2% |
| Gizi kurang (wasted) | 480 | 5,4% |
| **Total wasting** | **500** | **5,6%** |
| Gizi baik (normal) | 7.504 | 83,7% |
| Berisiko gizi lebih | 645 | 7,2% |
| Gizi lebih (overweight) | 196 | 2,2% |
| Obesitas | 113 | 1,3% |
| **Gizi lebih + obesitas** | **309** | **3,4%** |

### 6c. Berat Badan menurut Umur (BB/U) — indikator UNDERWEIGHT
| Kategori | Jumlah | % |
|---|---:|---:|
| Sangat kurang (severely underweight) | 117 | 1,3% |
| Kurang (underweight) | 858 | 9,6% |
| **Total underweight** | **975** | **10,9%** |
| Normal | 7.388 | 82,4% |
| Risiko lebih | 601 | 6,7% |

---

## 7. Kualitas Data — Duplikat Internal (Perlu Ditinjau)

Sinyal kemungkinan duplikat pada tabel registri gabungan (`anak`):

| Sinyal | Grup | Baris |
|---|---:|---:|
| Nama + tanggal lahir identik | 42 | 84 |
| No. KK + nama identik | 9 | 18 |
| **Total** | **51** | **102** |

**Penting — ini SINYAL, bukan duplikat terkonfirmasi:** dapat mencakup anak kembar, saudara dengan nama mirip, atau kesalahan input. Perlu tinjauan manual sebelum penggabungan/penghapusan. NIK identik = **0** (mustahil terjadi karena kolom NIK bersifat unik di database). Setara 0,7% dari populasi.

---

## 8. Imunisasi

| Aspek | Nilai |
|---|---:|
| Catatan di tabel `imunisasi` (modul baru) | 0 |
| Kolom imunisasi legacy di tabel `anak` (HB0/BCG/Polio/DPT/Campak) | Semua kosong |

**Temuan kritis:** Belum ada satu pun data imunisasi di sistem. Modul imunisasi (termasuk fitur kejar imunisasi yang sudah dibangun) belum terisi data operasional, sehingga **cakupan imunisasi/IDL belum dapat dilaporkan**.

---

## 9. Prioritas Tindak Lanjut (Rekomendasi)

| Prioritas | Isu | Rekomendasi |
|:--:|---|---|
| **P1** | 4.650 anak (32%) tanpa wilayah domisili | Pemetaan kelurahan/posyandu untuk data Capil baru agar bisa masuk sasaran layanan |
| **P1** | Imunisasi 0% terisi | Mulai pengisian data imunisasi (import/entri) untuk mengaktifkan modul kejar imunisasi |
| **P2** | 38% balita belum ditimbang | Sisir sasaran yang belum terukur pada Operasi Timbang berikutnya |
| **P2** | 51 grup duplikat perlu tinjau | Verifikasi manual (kembar vs duplikat) — file review sudah tersedia di `storage/app/exports` |
| **P3** | 3 kolom kosong total (tempat lahir, golda, NIK ayah) | Evaluasi apakah dibutuhkan; jika ya, lengkapi dari sumber lain |

---

## Catatan Metodologi & Kehati-hatian

- **Kerahasiaan:** Laporan ini hanya memuat hitungan/agregat. Tidak ada NIK, nama, atau baris data individu yang ditampilkan.
- **Basis prevalensi gizi:** dihitung terhadap anak yang **terukur** (8.966), bukan seluruh populasi (14.507). Angka nasional biasanya juga berbasis anak terukur, tetapi selalu sebutkan basisnya saat presentasi.
- **Status gizi** dihitung dengan `StatusGiziService` (standar WHO/Kemenkes, termasuk koreksi posisi ±0,7 cm) atas pengukuran **terakhir** tiap anak.
- **Duplikat** adalah sinyal untuk ditinjau, bukan angka final duplikat.
- Angka dapat berubah seiring pembaruan data; laporan ini potret per 6 Juli 2026.

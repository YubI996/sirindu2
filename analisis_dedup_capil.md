# Analisis Dedup Capil — Rekonsiliasi Selisih Data

> Dokumen ini hanya memuat **jumlah (count) agregat**. Tidak ada data pribadi
> (nama, NIK, alamat) yang ditampilkan. Dibuat 2026-06-29.

## 1. Komposisi total anak (14.935)

Setelah import Capil, tabel `anak` terbagi tiga kelompok berdasarkan jejak yang
ditinggalkan import:

| Kelompok | Penanda teknis | Jumlah |
|---|---|---:|
| Sigizi yang **sudah** di-merge Capil saat import | di-update, `alamat_ktp` terisi | **8.925** |
| Capil-baru (anak baru dari Capil, tanpa basis sigizi) | `alamat_ktp` terisi, `alamat` & `id_kel` NULL | **4.551** |
| Sigizi **belum tersentuh** Capil | `alamat_ktp` NULL, `updated_at == created_at` | **1.459** |
| **Total** | | **14.935** |

> Catatan angka **1.403 vs 1.459**: angka 1.403 adalah perkiraan awal "anak sigizi
> yang NIK-nya salah/dummy". Diskriminator final (`alamat_ktp` NULL **dan**
> `updated_at == created_at`) menangkap **1.459** record — yaitu seluruh record
> sigizi yang import Capil tidak pernah sentuh sama sekali. 1.459 inilah angka
> kerja yang dipakai dedup.

## 2. Hasil pencocokan duplikat (dry-run)

Aturan jodoh: **nama anak ≥ 70%** DAN (**No KK sama** ATAU **nama ortu ≥ 87%**),
tanggal lahir wajib sama. Dikalibrasi via kontrol positif/negatif (false-positive ~0,2%).

| Jalur kecocokan | Pasangan |
|---|---:|
| via **No KK sama** | 178 |
| via **nama ortu** (≥87%, ibu/ayah dipecah `/`) | 210 |
| **Total pasangan terkonfirmasi** | **388** |

Setiap pasangan = 1 record Capil-baru + 1 record sigizi-untouched yang sebenarnya
**orang yang sama**, tetapi lolos pencocokan saat import karena NIK beda **dan**
ejaan nama beda tipis (dua identifier meleset → aturan 2-dari-3 tak terpenuhi).

## 3. Dampak dedup terhadap baris data

| Aksi | Baris |
|---|---:|
| Capil-baru kembaran → **dihapus** (identitas pindah ke sigizi) | 388 |
| Sigizi-untouched kembaran → **di-update** (ambil identitas Capil) | 388 |
| **Total baris tersentuh** | **776** |

- **Tidak ada data hilang.** Record Capil yang dihapus, identitasnya (NIK, No KK,
  nama ortu, alamat KTP) berpindah ke record sigizi kembarannya yang sudah punya
  domisili + data kesehatan.
- Merge mempertahankan **keduanya**: `alamat` (domisili, dari sigizi) **dan**
  `alamat_ktp` (alamat KTP, dari Capil).

## 4. Baris yang TIDAK terpengaruh dedup (14.159)

| Kelompok | Jumlah | Keterangan |
|---|---:|---|
| Sigizi sudah di-merge Capil saat import | 8.925 | Sudah lengkap, tak disentuh |
| Capil-baru **tanpa** kembaran sigizi | 4.163 | Anak Capil yang memang baru (= 4.551 − 388) |
| Sigizi-untouched **tanpa** kembaran Capil | 1.071 | Tetap apa adanya (= 1.459 − 388) |
| **Total tidak tersentuh** | **14.159** | |

Verifikasi: 8.925 + 4.163 + 1.071 + 776 = **14.935** ✔

## 5. Ringkasan sebelum vs sesudah

| | Sebelum | Sesudah |
|---|---:|---:|
| Total anak | 14.935 | **14.547** |
| Selisih | | −388 (duplikat tergabung) |
| Baris berubah | | 776 (5,2%) |
| Baris utuh | | 14.159 (94,8%) |

> Cakupan dedup sangat bedah: hanya 5,2% baris berubah, dan yang dihapus hanya 388
> record yang **terbukti** duplikat (lewat No KK atau nama ortu yang cocok kuat).
> Angka 388 adalah **lantai terkonfirmasi**, bukan plafon — masih mungkin ada
> duplikat lain yang tak bisa dipastikan tanpa data tambahan, sehingga sengaja
> tidak digabung demi keamanan.

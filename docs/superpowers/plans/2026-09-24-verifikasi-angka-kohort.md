# Verifikasi Angka Agregat Kohort Imunisasi Sebelum Rilis

**Status:** Catatan prosedur — pengukuran belum dilakukan.

**Tanggal catatan:** 24 September 2026

## Ringkasan

Tugas 11 dari plan 2026-09-24-kohort-sasaran-imunisasi mengubah perhitungan agregat dasbor imunisasi
supaya **penyebut cakupan antigen mengikuti penyempitan kohort** (dari SELURUH menjadi BBL/SI/BADUTA).
Perubahan ini dimaksudkan untuk:

1. Memperbaiki anomali dasbor: sitologi IDL yg terdaftar < SDGs 2020 karena mengandalkan penyebut lama.
2. Memastikan tes memori tetap mengukur skenario realistis dan terus mencegah OOM produksi.

Angka-angka cakupan akan bergeser. Sebelum merilis, diperlukan verifikasi bahwa:
- Pergeseran dapat dijelaskan sepenuhnya dari penyempitan penyebut.
- Tidak ada regreswi dalam logika perhitungan yang membuat angka salah.
- Dasbor tetap konsisten dengan laporan ekspor dan riwayat terdahulu.

## Prosedur Verifikasi Harus Dijalankan di Salinan Data Produksi

**PENTING:** Pengukuran ini TIDAK boleh dilakukan langsung di produksi. Gunakan dump
data produksi terbaru yang di-restore ke mesin pengujian sendiri atau staging khusus.

Alasan: perintah yang dijalankan tidak mengubah data, tetapi persiapan dump
(menyalin 10 rb+ anak) memerlukan downtime atau akses eksklusif. Jangan menggangu
petugas.

## Pengukuran di Data Terdahulu (Sebelum Rilis)

Jalankan perintah berikut di salinan data produksi menggunakan Tinker:

```bash
php artisan tinker --execute="
\$s = app(App\Services\ImunisasiStatusService::class);
\$k = App\Support\KohortImunisasi::dari((int) date('Y'));
\$sasaran = \$s->getRingkasanSasaran(\$k);
\$idl = \$s->getIdlCoverage(\$k);
\$ibl = \$s->getIblCoverage(\$k);
printf(\"BBL %d | SI %d | Baduta %d\n\", \$sasaran['bbl']['jumlah'], \$sasaran['si']['jumlah'], \$sasaran['baduta']['jumlah']);
printf(\"IDL %d/%d = %.1f%%\n\", \$idl['idl_lengkap'], \$idl['total'], \$idl['persen']);
printf(\"IBL %d/%d = %.1f%%\n\", \$ibl['ibl_lengkap'], \$ibl['total'], \$ibl['persen']);
printf(\"Butuh kejar (operasional) %d\n\", \$s->getButuhKejar());
"
```

Catat hasil ini dengan cermat. Format output adalah:
```
BBL <n> | SI <n> | Baduta <n>
IDL <x>/<y> = <z>%
IBL <x>/<y> = <z>%
Butuh kejar (operasional) <n>
```

## Perbandingan dengan Angka Terdahulu

Ambil laporan cakupan dari dashboard imunisasi produksi SEBELUM perubahan disertakan ke prod:
- Catat IDL % (keseluruhan kohort)
- Catat IBL % (keseluruhan kohort)
- Catat Butuh Kejar (angka operasional)

Setelah merilis kode baru ke prod, jalankan Tinker command yang sama dan
bandingkan hasilnya:

### Tanda Terjadinya Pergeseran Wajar

Pergeseran **diperkirakan terjadi** karena penyebut berkurang (dari SELURUH ke BBL/SI/BADUTA saja).

Misalnya:
- Jika dulu 100 anak, 80 IDL lengkap → 80%
- Perubahan memindai hanya 50 anak BADUTA, 40 IDL lengkap → 80% (SAMA)
- Tetapi jika 30 dari 50 BADUTA IDL lengkap → 60% (TURUN dari 80%)

**Pola yang wajar:**
- Persen dapat naik atau turun tergantung komposisi kohort.
- Jumlah absolut (pembilang, penyebut) pasti berbeda karena penyebut berubah.
- **Tidak ada alasan untuk perubahan tiba-tiba lebih dari ~15%** dalam persen, kecuali
  data produksi punya distribusi umur sangat tidak seimbang.

### Tanda Kekeliruan (Hentikan Rilis)

**Hentikan rilis dan telusuri penyebabnya** jika:

1. **Persen bergeser lebih dari 15% tanpa penjelasan** dari komposisi kohort
   (misalnya: IDL turun dari 75% jadi 45%, sementara jumlah anak yang bergerak
   tidak menjelaskan selisih sebesar 30 poin).

2. **Pembilang (anak IDL lengkap) bertambah** saat penyebut berkurang
   (artinya logika perhitungan salah, bukan sekadar penyempitan).

3. **Butuh Kejar tidak bergeser wajar** (operasional menggunakan filter internal
   berbeda, tetapi angkanya harus selaras dengan apa yg tampil di dashboard).

4. **Jumlah Baduta lebih besar dari SI**, atau satu kohort kosong (0 anak).
   Ini menandakan error dalam algoritma kohort atau bug filtering.

## Data Aktual (Diisi Setelah Pengukuran)

Pembaruan ini harus dilakukan **sebelum merilis ke produksi**. Jangan rilis
tanpa data dari prod. Jika tidak ada prod, jangan rilis sampai ada mekanisme
untuk mengeceknya.

**Pengukuran di prod:**

```
[CATATAN: Data belum tersedia — pengukuran harus dilakukan pada salinan prod
sebelum rilis, bukan di prod langsung. Jalankan Tinker command di atas, catat
hasilnya di sini dalam format aslinya.]
```

**Angka terdahulu (sebelum perubahan):**

```
[CATATAN: Ambil dari dashboard sebelum merilis, atau dari doc riwayat jika
tersedia; format dapat berupa screenshot atau copy-paste.]
```

**Analisis pergeseran:**

```
[CATATAN: Bandingkan kedua hasil. Jelaskan apakah pergeseran konsisten dengan
penyempitan penyebut. Jika ada pergeseran aneh, jelaskan atau hentikan rilis.]
```

## Kebijakan Rilis

**Tidak boleh rilis ke produksi sampai:**

1. Pengukuran telah dilakukan di salinan data produksi (bukan di dev lokal).
2. Pergeseran angka telah dianalisis dan dijelaskan.
3. Tidak ada pergeseran "aneh" (poin 1–4 di atas) yang belum diselidiki.

Jika ada pertanyaan tentang angka, konsultasi dengan pemilik produk sebelum
merilis. Jangan anggap angka baru otomatis benar.

## Catatan Teknis

- **Pengukuran dev lokal TIDAK cukup:** Prod punya ±10.000 anak dengan distribusi
  umur yg berbeda. Dev lokal hanya 39 anak.
- **Layar admin dashboard hanya menampilkan satu tahun:** Tinker command di atas
  menampilkan agregat untuk kohort tahun berjalan. Jika ingin membandingkan tahun
  lain, ubah `date('Y')` menjadi tahun spesifik.
- **Cache service:** `ImunisasiStatusService` menyimpan beberapa hasil di static.
  Kalau ingin menjalankan Tinker command dua kali berturut-turut untuk tes, panggil
  `ImunisasiStatusService::flushCache()` di antara keduanya.

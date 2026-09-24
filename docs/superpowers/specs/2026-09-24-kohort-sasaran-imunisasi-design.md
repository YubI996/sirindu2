# Kohort Sasaran Imunisasi — cut-off 1 April–31 Maret

Tanggal: 2026-09-24 · Status: menunggu reviu pemilik produk · Lingkup: modul imunisasi saja

## 1. Tujuan & prinsip

Dasbor imunisasi rutin (`admin/imunisasi-dashboard`) saat ini **tidak punya konsep tahun sama
sekali**. Setiap penyebut dihitung relatif hari ini lewat `TIMESTAMPDIFF(MONTH, tgl_lahir,
CURDATE())` — "bayi 0–11 bulan", "anak ≥ 12 bulan", "anak ≥ 24 bulan". Akibatnya tidak ada satu pun
angka di halaman itu yang bisa dikunci sebagai capaian tahun tertentu: buka halaman yang sama bulan
depan, penyebutnya sudah bergeser.

Spec ini memasang kohort tahunan program imunisasi di atas data yang sudah ada, dengan satu prinsip
pemisah yang ditetapkan pemilik produk:

> **Statistik dan SPM memakai kohort tahunan. Operasional memakai tanggal berjalan.**

Prinsip itu yang menentukan blok mana ikut berubah dan mana tidak — bukan daftar pengecualian yang
disusun kasus per kasus.

### 1.1 Keputusan yang sudah diambil

Jangan ditawar ulang tanpa pemilik produk.

1. **Sasaran tahun X = anak lahir 1 April X-1 s.d. 31 Maret X** (inklusif).
2. **Umur dipotret pada 31 Maret X.** BBL dan SI jadi partisi bersih: tiap anak masuk tepat satu
   kelompok, dan BBL + SI = seluruh kohort. Tidak ada anak terhitung dua kali.
3. **Batas atas SI dibaca "belum genap 12 bulan"**, bukan "≤ 11 bulan 29 hari" harfiah (§2.2).
4. **Baduta X = seluruh kohort X-1**, bukan hanya yang tahun lalu tergolong SI (§2.3).
5. **Penyebut cakupan antigen bayi baru lahir = seluruh kelahiran periode**, bukan kartu BBL (§4.1).
6. **Dropdown tahun**, default tahun kalender berjalan.
7. **Kartu "Balita 0–59 bulan" dihapus** — tidak punya padanan di skema tiga kelompok, dan kalau
   dipertahankan ia jadi satu-satunya angka di bagian itu yang masih memakai umur hari ini.
8. **`butuh_kejar` dikeluarkan dari `getIdlCoverage()`** dan dihitung atas tanggal berjalan (§4.2).
9. **Cakupan IBL ikut kohort Baduta**, meninggalkan penyebut "anak ≥ 24 bulan". Keputusan ini
   membalik jawaban sebelumnya dalam percakapan yang sama, setelah prinsip statistik/operasional
   ditetapkan: cakupan IBL adalah angka SPM, jadi ia tidak boleh mengecualikan diri dari kohort.
10. **Pendekatan: value object + parameter eksplisit** (§3), mengikuti pola `PeriodeKesmas`.

## 2. Aturan kohort

### 2.1 Definisi

`App\Support\KohortImunisasi` — immutable, murni PHP, tanpa akses DB, named constructor `dari()`,
sebangun dengan `App\Support\PeriodeKesmas`.

```
KohortImunisasi::dari(2026)

  periode lahir   1 Apr 2025 .. 31 Mar 2026   (inklusif)
  potret umur     31 Mar 2026

  rentang('BBL')      lahir 1 Feb 2026 .. 31 Mar 2026   umur 0 – 1 bln 29 hr di 31 Mar 2026
  rentang('SI')       lahir 1 Apr 2025 .. 31 Jan 2026   umur 2 bln – belum genap 12 bln
  rentang('SELURUH')  lahir 1 Apr 2025 .. 31 Mar 2026   BBL + SI
  rentang('BADUTA')   lahir 1 Apr 2024 .. 31 Mar 2025   kohort 2025 utuh
```

Umum: untuk tahun X, BBL = `[1 Feb X, 31 Mar X]`, SI = `[1 Apr X-1, 31 Jan X]`,
SELURUH = `[1 Apr X-1, 31 Mar X]`, BADUTA = `[1 Apr X-2, 31 Mar X-1]`.

### 2.2 Kenapa "belum genap 12 bulan", bukan "11 bulan 29 hari"

Anak yang lahir **1 April X-1** — hari pertama periode — berumur **11 bulan 30 hari** pada 31 Maret X,
bukan 11 bulan 29 hari. Membaca angka 29 hari secara harfiah justru membuang hari pertama kohortnya
sendiri. Dengan pembacaan "belum genap 12 bulan", SI = seluruh kohort dikurangi BBL, tanpa syarat
tambahan, dan tidak ada tanggal lahir dalam periode yang jatuh di luar kedua kelompok.

### 2.3 Kenapa Baduta = seluruh kohort tahun sebelumnya

Bunyi harfiah aturan ("Baduta = sasaran yang tergolong SI di tahun sebelumnya") akan membatasi Baduta
X pada `[1 Apr X-2, 31 Jan X-1]`. Anak yang lahir Februari–Maret X-1 tergolong BBL di tahun X-1, lalu
di tahun X ia bukan sasaran (lahir sebelum 1 April X-1) dan bukan Baduta — sekitar dua bulan kelahiran
hilang dari pendataan setiap tahun. Karena itu Baduta X diambil sebagai kohort X-1 utuh, yang juga
pas dengan artinya: selama tahun X mereka berumur kira-kira 12–23 bulan.

### 2.4 Tidak ada aritmetika bulan yang bisa meleset

Keempat batas jatuh di tanggal kalender tetap (1 Feb, 31 Jan, 1 Apr, 31 Mar), jadi tidak ada operasi
"tambah 2 bulan" yang bisa salah di akhir bulan atau tahun kabisat — perbandingannya
`whereBetween('tgl_lahir', ...)` biasa. `anak.tgl_lahir` NOT NULL (dikunci commit `bdc6783`), jadi
tidak ada baris yang lolos diam-diam karena NULL.

## 3. Pendekatan

Value object dibawa sebagai **parameter eksplisit** ke tiap method service:

```php
public function getRingkasanSasaran(KohortImunisasi $kohort, array $filters = []): array
```

Satu helper privat `scopeKohort($query, string $kelompok)` menerapkan `whereBetween('tgl_lahir', ...)`,
menggantikan tiap `whereRaw('TIMESTAMPDIFF(MONTH, tgl_lahir, CURDATE()) ...')`.

Alternatif yang **ditolak**: menitipkan tahun di array `$filters`. Diff-nya lebih kecil, tapi method
yang lupa menerapkan filter kohort akan diam-diam memindai seluruh populasi dan menghasilkan angka
salah tanpa satu pun error — kelas bug yang sudah berulang kali tercatat di CLAUDE.md repo ini
(`has()` vs `boolean()`, blade membaca kolom yang tak ada, jQuery `:hidden` pada `<option>`). Dengan
parameter eksplisit, yang terlewat langsung gagal.

Alternatif yang juga ditolak: service terpisah. Logika populasi (`eachAnak()`, cache statis,
`isIdlLengkap()`) akan terduplikasi.

## 4. Pembagian per blok

| Blok | Sifat | Dasar |
|---|---|---|
| Kartu Data sasaran BBL/SI/Baduta | statistik | kohort |
| Cakupan IDL | SPM | kohort — penyebut **SI** |
| Cakupan IBL | SPM | kohort — penyebut **Baduta** |
| Cakupan per antigen | SPM | kohort — per jendela antigen (§4.1) |
| Funnel dosis | statistik | kohort — **SI** |
| Kohort per kecamatan & kelurahan | statistik | kohort — kolom BBL/SI/Baduta |
| Rincian per puskesmas | SPM | kohort — **SI** |
| Korelasi IDL ↔ stunting | statistik | kohort (ikut `per_kelurahan` dari cakupan IDL) |
| **Butuh kejar** | **operasional** | **tanggal berjalan** — dipisah dari `getIdlCoverage` (§4.2) |
| Sasaran hari ini & besok | operasional | tanggal berjalan — tidak disentuh |
| Halaman Proyeksi (`admin.earlyWarning`) | operasional | tidak disentuh |
| Export proyeksi kebutuhan vaksin | operasional | tidak disentuh |
| Alasan Tidak Imunisasi | — | tidak disentuh; basisnya kunjungan terakhir, tak punya penyebut kohort |

Tanda tangan yang berubah (`ImunisasiStatusService`): `getRingkasanSasaran`, `getIdlCoverage`,
`getIblCoverage`, `getFunnelDosis`, `getCakupanAntigen`, `getKohortWilayah`, `getRincianPuskesmas`.
Pemanggilnya hanya `AdminController::imunisasiDashboard` dan dua berkas tes
(`ImunisasiRutinDashboardServiceTest`, `ImunisasiDashboardMemoriTest`) — tidak ada pemakai lain.

### 4.1 Penyebut cakupan antigen

Ditentukan dari `jenis_vaksin.usia_pemberian_max` (satuan **hari**), bukan daftar kode yang ditulis
tangan, supaya antigen baru otomatis kebagian:

| `usia_pemberian_max` | Penyebut | Antigen aktif saat ini |
|---|---|---|
| ≤ 59 hari | SELURUH kohort | HB0 (7), BCG (30), Polio 1 (30) |
| 60 – 364 hari | SI | RV1 (70), Polio 2, DPT-HB-Hib 1, PCV1, RV2, Polio 3, DPT2, PCV2, Polio 4, IPV1, DPT3, IPV2, MR1 (300) |
| ≥ 365 hari | Baduta | PCV3 (395), MR2 (540), DPT-HB-Hib 4 (720) |

Kategori `Tambahan` (DT, Td, HPV 1/2, MR Anak Sekolah — kelompok ISL) tetap dikecualikan seperti
sekarang; di luar lingkup dasbor rutin.

**Kenapa penyebut antigen BBL adalah seluruh kohort, bukan kartu BBL.** HB0 diberikan 0–7 hari
setelah lahir, jadi setiap anak dalam periode menerimanya. Kartu BBL hanya berisi anak lahir
Februari–Maret (±2 bulan kelahiran). Kalau cakupan HB0 penyebutnya kartu itu, HB0 milik ±10 bulan
kelahiran lainnya tidak masuk pembilang maupun penyebut mana pun, dan angka kota jadi tidak bisa
dibandingkan dengan data Dinkes. Kartu BBL tetap menampilkan potret 31 Maret apa adanya.

**Kasus batas RV1.** Jendelanya 42–70 hari, melintasi batas 59. Dengan aturan batas atas ia masuk SI,
yang benar: RV1 baru bisa dinilai setelah bayi melewati 70 hari.

### 4.2 `butuh_kejar` dipisah dari `getIdlCoverage()`

Saat ini `$butuhKejar` dihitung di dalam `getIdlCoverage()` lewat `withKejar: true`
(`ImunisasiStatusService.php:348`), menumpang pass populasi yang sama agar tidak memindai dua kali.

Kalau penyebut IDL pindah ke kohort SI sementara perhitungan kejar dibiarkan menumpang, kartu "Butuh
kejar" ikut menyempit jadi "butuh kejar **di kohort tahun terpilih**" — anak kohort lama yang masih
tertinggal lenyap dari angkanya. Kartu itu menaut ke halaman Proyeksi (`admin.earlyWarning`, blade
baris 273) yang menghitung dengan tanggal berjalan, jadi kartunya menampilkan satu angka sementara
daftarnya berisi jumlah yang lain. Tidak ada error — hanya dua angka yang tak cocok.

Karena itu `butuh_kejar` menjadi method sendiri berbasis tanggal berjalan —
`getButuhKejar(array $filters): int`, tanpa parameter kohort — dan parameter `withKejar` dihapus dari
`getIdlCoverage()`. Biayanya satu pindaian populasi tambahan. Itu aman: sejak perbaikan
16 September tiap pass sudah `chunkById(500)` dengan `select` kolom seperlunya — yang memicu OOM prod
adalah menghidrasi seluruh populasi sekaligus, bukan banyaknya pass.

## 5. Antarmuka

- **Dropdown "Tahun sasaran"** di paling kiri baris filter wilayah, diisi tahun berjalan mundur 4
  tahun (5 pilihan); kohort lebih tua sudah lewat usia imunisasi rutin.
- **Judul bagian Data sasaran** menyebut periodenya:
  `Kohort 2026 · lahir 1 Apr 2025 – 31 Mar 2026 · potret umur 31 Mar 2026`.
- **Tiap kartu mencantumkan rentang lahirnya sendiri**, supaya angkanya bisa ditelusuri tanpa membuka
  kode.
- **Blok operasional diberi penanda "tidak mengikuti tahun sasaran"** (Butuh kejar, Sasaran hari ini
  & besok). Tanpa itu, masalah "dua arti sasaran dalam satu halaman" masuk lewat pintu belakang.
- **Teks penjelas di blade baris 243 dan 373 ditulis ulang.** Keduanya sekarang menjelaskan
  metodologi lama ("kohort anak yang usianya sudah lewat jendela kelompok tsb", "% dari anak yang
  usianya sudah lewat jendela pemberian antigen tsb"). Membiarkannya lebih menyesatkan daripada
  angkanya sendiri.
- Satu baris keterangan bahwa anak di luar ketiga kohort tidak masuk blok statistik mana pun, jadi
  jumlah di halaman ini tidak sama dengan jumlah anak terdaftar.

## 6. Dampak ke angka yang dilihat klien

Penyebut IDL berubah dari "semua anak ≥ 12 bulan" (termasuk yang sudah 4–5 tahun) menjadi satu tahun
kelahiran saja. Penyebutnya menyusut banyak dan persennya bergerak — arah dan besarannya **tidak
diperkirakan di spec ini**: dev hanya punya 39 anak, jadi angka nyatanya wajib diukur di data prod
sebelum rilis. Itu langkah verifikasi di rencana implementasi, bukan tebakan di sini.

Yang pasti turun adalah **cakupan per antigen**. Metodologi sekarang mengeluarkan bayi yang jadwalnya
belum tiba dari penyebut; dengan penyebut kohort penuh mereka ikut masuk, jadi semua batang antigen
memendek. Untuk tahun berjalan ini perilaku yang benar — cakupan tahunan memang merangkak naik sampai
kohortnya matang — tetapi harus dijelaskan di halaman, bukan dibiarkan terbaca sebagai penurunan
kinerja.

Efek serupa yang lebih halus pada cakupan IDL tahun berjalan: anggota SI termuda (lahir 31 Januari X)
baru berumur sekitar 8 bulan di pertengahan tahun, sedangkan MR1 dijadwalkan 270–300 hari. Sebagian
SI memang belum mungkin lengkap, dan persen tahun berjalan tertekan secara struktural sampai kohortnya
matang.

## 7. Batas & penanganan salah

- Parameter `tahun` yang bukan angka atau di luar rentang 5 tahun dikembalikan ke default, bukan
  error 500.
- `KohortImunisasi::dari()` menolak tahun tak masuk akal dengan `InvalidArgumentException`, sebangun
  dengan `PeriodeKesmas::dari()` yang menolak kode periode asing.
- Anak di luar ketiga kohort tidak muncul di blok statistik mana pun. Itu memang maksudnya; lihat §5
  untuk keterangan yang menemani.

## 8. Rencana tes

- `tests/Unit/Support/KohortImunisasiTest.php`, tanpa DB — keempat batas tanggal; anak lahir tepat
  1 April (hari pertama kohort, **harus masuk SI**, bukan terbuang); tepat 31 Maret (harus BBL);
  29 Februari tahun kabisat; Baduta = kohort tahun sebelumnya utuh; tahun di luar akal ditolak.
- Feature di `ImunisasiRutinDashboardServiceTest` — fixture dengan `tgl_lahir` persis di tiap sisi
  batas, lalu assert tiap method menempatkannya di kelompok yang benar; satu tes bahwa anak di luar
  kohort tidak ikut terhitung.
- Penyebut antigen: HB0 → SELURUH, **RV1 → SI** (kasus batas 42–70 hari), PCV3 → Baduta.
- **Anti-regresi utama: `butuh_kejar` tidak berubah nilainya saat dropdown tahun diganti.** Ini kunci
  yang menjaga batas operasional/statistik tetap terpisah.
- `ImunisasiDashboardMemoriTest` diperbarui untuk tanda tangan baru; ambang kenaikan memori puncak
  tetap < 16 MB walau ada satu pass tambahan.
- `ImunisasiStatusService::flushCache()` di `setUp()` tiap tes yang menyentuh service ini — jebakan
  cache statis yang sudah tercatat di CLAUDE.md.

## 9. Di luar lingkup

Halaman Proyeksi (`admin.earlyWarning`), export proyeksi kebutuhan vaksin, blok Alasan Tidak
Imunisasi, tab BIAS (belum dibangun), dan seluruh dasbor lain (timbang, Kesmas, PD3I) — masing-masing
punya definisi sasaran sendiri dan tidak disentuh spec ini. Kartu SPM imunisasi juga belum dibuat di
sini; spec ini hanya menyiapkan kohort yang benar sebagai dasarnya.

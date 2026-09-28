# Serah-terima — Kohort Sasaran Imunisasi

Tanggal: 2026-09-24 · Status: **dilanjutkan; perbaikan mandiri selesai, I3/I5 menunggu keputusan produk, verifikasi salinan produksi belum dilakukan**

## Pembaruan sesi lanjutan — 24 September 2026

Permintaan pemilik produk: lanjutkan pekerjaan. Pembaruan ini menjadi status terkini;
§1–7 di bawah dipertahankan sebagai catatan handover awal, bukan daftar status terbaru.

- **Selesai:** I1 (label wilayah dan tahun kohort), I2 (penjelasan dampak metode di halaman),
  I4 (prosedur verifikasi ditulis ulang), M6 (grid tiga kolom), M7 (gaya catatan),
  M12 (fixture tepat 2.000 anak / 6.000 imunisasi), M14 (tes nyata controller → view lintas tahun).
- **M8 ditangani sesuai saran minimum:** komentar pada `isKelompokLengkap()` menjelaskan bahwa
  pembilang masih dapat berubah karena waktu untuk vaksin yang tidak dapat dikejar. Kebijakannya
  tidak diubah; batas ini juga dicatat di prosedur verifikasi.
- **Temuan tambahan diperbaiki:** subteks IDL/IBL masih menyebut anak ≥12/≥24 bulan. Kini label
  IDL menyebut SI tahun terpilih dan subteks kedua kartu menyebut kelompok penyebut sebenarnya.
- **Belum:** I3 (penanganan batas usia NULL/di luar Baduta) dan I5 (nasib placeholder WUS).
  Pertanyaan keputusan sudah dikirim. Belum ada jawaban pada saat catatan ini ditulis;
  implementasi kedua kebijakan belum diubah. Rekomendasi: keluarkan antigen tersebut dari
  cakupan dengan catatan terlihat, dan pulihkan kartu WUS sebagai penanda data belum tersedia.
- **Verifikasi:** `php artisan test tests/Feature/Imunisasi tests/Unit/Support/KohortImunisasiTest.php`
  lulus **47 tes / 156 assertion** (139,54 detik), satu proses. Tes memori tetap di bawah 16 MB.
  Ada peringatan deprecation metadata doc-comment PHPUnit yang sudah ada sebelumnya.
  `git diff --check` bersih; `php artisan view:clear` berhasil.
- **Reviu lokal atas diff perbaikan:** label penyebut dicocokkan ke service; contoh API lama di
  prosedur dicocokkan ke `086b58a`. Belum dilakukan reviu independen atas seluruh perbaikan
  I1–I5 karena keputusan I3/I5 belum masuk.
- Perubahan sesi lanjutan masih di working tree, belum di-commit. WIP pemilik tetap dipertahankan;
  branch backup dan workspace ledger belum dihapus karena pekerjaan belum selesai.
- **Gerbang rilis tetap tertutup:** salinan produksi belum diukur. Lokasi salinan sudah ditanyakan.
  Prosedur yang telah dikoreksi berada di `2026-09-24-verifikasi-angka-kohort.md`; jalankan sebelum
  deploy, dengan snapshot, filter, akses, dan tanggal PHP/MySQL yang sama pada kedua versi.

Langkah selanjutnya: terapkan keputusan I3/I5 setelah jawaban diterima, lengkapi tes kebijakannya,
jalankan kembali suite terkait, lakukan reviu atas seluruh perbaikan, lalu ukur data salinan produksi.

Dokumen ini untuk siapa pun (orang atau sesi lain) yang melanjutkan pekerjaan ini. Ia menjelaskan di
mana semuanya berada, apa yang sudah beres, apa yang belum, dan keputusan apa yang diambil tanpa
menanyakan pemilik produk — supaya bisa dibatalkan kalau ternyata salah.

## 1. Di mana semuanya

| Hal | Lokasi |
|---|---|
| Branch | `feat/kohort-sasaran-imunisasi`, HEAD `b841a26`, 15 commit dari `086b58a` |
| Spec (otoritas pengikat) | `docs/superpowers/specs/2026-09-24-kohort-sasaran-imunisasi-design.md` |
| Rencana implementasi | `docs/superpowers/plans/2026-09-24-kohort-sasaran-imunisasi.md` |
| Prosedur verifikasi pra-rilis | `docs/superpowers/plans/2026-09-24-verifikasi-angka-kohort.md` — **isinya cacat, lihat §4 I4** |
| Ledger eksekusi (semua ruling) | `.superpowers/sdd/2026-09-24-kohort-sasaran-imunisasi/progress.md` |
| Brief & laporan tiap task | direktori yang sama, `task-*-brief.md` / `task-*-report.md` |
| Paket diff tiap reviu | direktori yang sama, `review-<base>..<head>.diff` |
| Jaring pengaman operasi sejarah | branch `sdd-backup-2323897` — **jangan dihapus sebelum §4 beres** |

Working tree menyimpan **29 berkas WIP yang TIDAK terkait** pekerjaan ini (`TanggalBerkas`, modul
import, dsb) milik pemilik produk. Semuanya utuh dan belum di-commit. Jangan pernah `git add -A`.

## 2. Apa yang dibangun

Dasbor imunisasi rutin dipindahkan dari "umur relatif hari ini" ke kohort tahunan tetap.

Sebelumnya tiap penyebut dihitung `TIMESTAMPDIFF(MONTH, tgl_lahir, CURDATE())`, sehingga tak satu pun
angka di halaman itu bisa dikunci sebagai capaian tahun tertentu — buka lagi bulan depan, penyebutnya
sudah bergeser.

Prinsip pengikatnya, ditetapkan pemilik produk:
**statistik & SPM memakai kohort tahunan; operasional tetap memakai tanggal berjalan.**

Sasaran tahun X = anak lahir 1 April X-1 s.d. 31 Maret X, umur dipotret 31 Maret X, dipilah BBL
(0–1 bln 29 hr), SI (2 bln–belum genap 12 bln), dan Baduta (kohort tahun sebelumnya utuh).

### Yang ikut kohort
`getRingkasanSasaran` · `getIdlCoverage` (penyebut SI) · `getIblCoverage` (Baduta) · `getFunnelDosis`
(SI) · `getCakupanAntigen` (per jendela antigen) · `getKohortWilayah` · `getRincianPuskesmas` (SI) ·
korelasi IDL↔stunting (ikut lewat `per_kelurahan`)

### Yang tetap tanggal berjalan
`getButuhKejar()` (baru, dipisah dari `getIdlCoverage`) · `getSasaranHarianBesok` · halaman Proyeksi ·
export proyeksi kebutuhan vaksin · Alasan Tidak Imunisasi

### Berkas yang berubah
`app/Support/KohortImunisasi.php` (baru) · `app/Services/ImunisasiStatusService.php` ·
`app/Http/Controllers/AdminController.php` · `resources/views/admin/imunisasi/dashboard.blade.php` ·
empat berkas tes.

## 3. Status pengujian

Terakhir dijalankan penuh pada `b841a26`: **46 lulus / 147 assertion** untuk `tests/Feature/Imunisasi`
plus `tests/Unit/Support/KohortImunisasiTest.php`. Tes memori lulus di bawah ambang 16 MB dengan 2.000
anak.

Menjalankan tes di repo ini: **jangan dua proses sekaligus** — DB `sirindu_testing` dipakai bersama.
Suite penuh proyek lebih dari 10 menit; pakai `--filter` kecuali memang butuh keseluruhan.

## 4. Yang BELUM beres — inilah pekerjaan berikutnya

Reviu akhir seluruh branch: **0 Critical, 5 Important, 10 Minor.** Ketujuh Minor yang ditunda selama
reviu per-task ditriase "biarkan". Pereviu memverifikasi batas kohort dengan *menjalankan*
`KohortImunisasi` lewat skrip, bukan hanya membacanya, dan tidak menemukan kasus yang menghasilkan
angka salah terhadap data hari ini.

### I1 — Dua tabel terbesar berubah angka tanpa mengaku *(cacat rencana, bukan implementasi)*

`resources/views/admin/imunisasi/dashboard.blade.php`

- Legenda kolom "Kohort per kecamatan & kelurahan" masih `RT · Bayi · Baduta · Total · Porsi kota`,
  padahal kolomnya kini RT · **BBL · SI** · Baduta · Total · % Kota.
- Subtitle "Sasaran per kecamatan" masih *"Porsi populasi anak terdaftar"*, padahal kini hanya
  memindai `[1 Apr X-2 … 31 Mar X]`, bukan seluruh registri.
- Keduanya tidak menyebut tahun kohort, sementara empat seksi lain sudah.

Keduanya **benar sebelum perubahan ini** dan menjadi salah karenanya. Grep verifikasi di rencana Task
10 hanya mencari kunci array (`['bayi']`, `baduta_min`, …), jadi mustahil menangkap kata harfiah di
teks label. Perbaikannya tiga baris.

### I2 — Penjelasan penurunan angka tak pernah sampai ke halaman *(cacat rencana)*

Spec §6 tegas: penurunan batang antigen *"harus dijelaskan di halaman, bukan dibiarkan terbaca sebagai
penurunan kinerja"*. Syarat itu hilang antara spec dan rencana — tidak ada task yang membawanya. Teks
`<small>` yang ditulis ulang menjelaskan penyebut barunya, tapi tak satu pun menyebut bahwa kohort
yang belum matang secara struktural berada di bawah target dan akan merangkak naik.

Di hari rilis setiap batang antigen memendek. Tanpa kalimat itu, bedanya "perubahan metodologi yang
diharapkan" versus "capaian anjlok". Rumah paling wajar: paragraf `im-note` di bawah Data sasaran.

### I3 — Antigen tanpa batas usia dapat penyebut terluas *(butuh keputusan)*

`ImunisasiStatusService::kelompokPenyebutAntigen()` — `(int) null === 0`, yang lolos `<= 59` dan
menghasilkan `SELURUH`. Kolom `jenis_vaksin.usia_pemberian_max` `nullable` di migrasi,
`'nullable|integer|min:0'` di `MasterDataVaksinController`, dan `admin/master-data/vaksin/index.blade.php`
mengirim `val() || null`. Jadi petugas **benar-benar bisa** menambah antigen Wajib aktif tanpa batas
usia lewat UI, dan ia diam-diam dinilai terhadap penyebut yang paling menekan angka cakupan.

Lubang simetrisnya di ujung atas: `default → BADUTA` tak berbatas, jadi antigen non-`Tambahan` untuk
usia 3 tahun ke atas pun akan dinilai terhadap kohort Baduta.

Pilihannya: tolak NULL/di luar rentang secara eksplisit, atau keluarkan baris itu dari blok antigen
dengan catatan yang terlihat. **Ini keputusan pemilik produk, bukan keputusan teknis.**

### I4 — Gerbang rilis punya kriteria berhenti TERBALIK *(paling berbahaya)*

`docs/superpowers/plans/2026-09-24-verifikasi-angka-kohort.md` menulis *"Jumlah Baduta lebih besar dari
SI menandakan error dalam algoritma kohort"*. **Terbalik.** Baduta = 12 bulan kelahiran, SI = 10 bulan
kelahiran; pada populasi stabil Baduta normalnya lebih besar ~20%. Dituruti harfiah, dokumen ini
**menghentikan rilis yang benar**.

Kekeliruan lain di berkas yang sama:

- Contoh perhitungan IDL memakai denominator BADUTA — penyebut IDL itu SI.
- Perubahan antigen ditulis *"dari SELURUH menjadi BBL/SI/BADUTA"*; SELURUH justru salah satu nilai
  baru, dan aturan lamanya "anak yang jendela usianya sudah lewat".
- Kriteria "Butuh Kejar bergeser wajar" — angka itu **tidak boleh bergeser sama sekali**; justru itu
  inti spec §4.2.
- Ambang berhenti ~15% bertentangan dengan spec §6 yang menolak menebak arah maupun besaran.
- Beberapa kalimat rusak (`"sitologi IDL yg terdaftar < SDGs 2020"`, *regreswi*, *menggangu*).

Berkas ini satu-satunya gerbang antara rilis yang mengubah angka dan data Dinkes sendiri. Ia butuh
koreksi menyeluruh sebelum dipakai.

### I5 — Dua kartu placeholder WUS hilang tanpa catatan *(butuh konfirmasi pemilik produk)*

Spec keputusan 7 hanya memerintahkan menghapus kartu "Balita 0–59 bulan". Tapi rencana Task 10
mengganti blok blade 212–240 sekaligus, dan kartu "WUS hamil"/"WUS tidak hamil" ikut tersapu.

Memori proyek mencatat placeholder WUS sebagai keputusan produk yang disengaja dari redesign
sebelumnya, dan gunanya — mengungkap bahwa sistem ini sama sekali tidak mencatat ibu/wanita usia subur
— tidak ada hubungannya dengan kohort kelahiran mana pun. Ada argumen yang masuk akal untuk
menghapusnya; yang tidak ada adalah catatan bahwa seseorang memutuskan begitu. CSS `.im-card--na`
kini mati apa pun keputusannya.

### Minor yang paling layak diambil

- **M6** Data sasaran merender 3 kartu ke grid 4 kolom (`im-cards` tanpa `im-cards--3`), lebarnya jadi
  beda dengan Data capaian tepat di bawahnya. Perbaikan satu kata.
- **M7** `.im-note` tidak punya aturan CSS sama sekali — catatan kaki tampil sebesar dan setebal teks
  utama.
- **M12** `ImunisasiDashboardMemoriTest::seedAnakBanyak()` menyisipkan imunisasi dua kali untuk batch
  pertama (`where('nama','like','Anak Massal %')` sudah mencakup 2.000 anak saat panggilan kedua), jadi
  fixture-nya 9.000 baris bukan 6.000 — menggelembungkan justru angka yang diukur tes itu.
- **M14** Tes anti-regresi `butuh_kejar` nyaris hampa di tempatnya sekarang: `getButuhKejar()` tak
  menerima kohort, jadi tanda tangannya sendiri sudah membuktikan assertion-nya. Batas yang benar-benar
  bisa regresi adalah controller→view; tes satu baris di `ImunisasiDashboardTahunTest` yang
  membandingkan variabel view `butuhKejar` untuk `?tahun=X` dan `?tahun=X-2` akan menjaga hal yang
  sebenarnya dimaksud spec §8.
- **M8** Pembilang masih sedikit today-relative: `isKelompokLengkap()` menghitung umur dari `now()` dan
  melewati vaksin `!bisa_dikejar` yang lewat jendela. Saat ini terbatas pada HB0 (satu-satunya baris
  non-catchable), jadi dampaknya beberapa baris di awal Februari. Tidak mendesak, tapi ini satu-satunya
  jalur tersisa yang bisa membuat "angkanya berubah padahal tak ada yang menyentuh" — dan jadi material
  begitu ada antigen kedua ditandai non-catchable. Minimal beri komentar di method itu.

Daftar Minor selengkapnya ada di ledger.

## 5. Belum dikerjakan dan WAJIB sebelum rilis

**Ukur pergeseran angka di data prod.** Spec §6 sengaja tidak menebak arah maupun besarannya karena dev
hanya punya 39 anak. Prosedurnya di `2026-09-24-verifikasi-angka-kohort.md` — **perbaiki dulu I4**,
baru pakai.

Yang harus dijawab: berapa persen IDL bergeser, dan apakah pergeseran itu bisa dijelaskan sepenuhnya
oleh penyempitan penyebut. Kalau ada selisih yang tidak bisa dijelaskan, **hentikan rilis** dan
telusuri. Jangan anggap angka baru otomatis benar.

**Saat deploy: `php artisan view:clear`.** Tidak disebut di laporan mana pun. Tanpa itu compiled view
lama tetap disajikan dan perubahannya terlihat tidak berefek.

## 6. Keputusan yang saya ambil tanpa bertanya

Semua tercatat di ledger dengan alasan dan biayanya kalau salah. Yang paling perlu ditinjau:

1. **Kerja di tempat, bukan worktree baru.** Proyek ini butuh `vendor/` dan punya jebakan
   worktree+vendor junction yang tercatat di memori proyek.
2. **WIP pemilik produk sempat ikut ter-commit, lalu saya perbaiki sejarahnya.** Task 9 harus menyunting
   `AdminController.php` yang sudah berisi pekerjaan `TanggalBerkas`/`format_tanggal`. Larangan
   `git add -A` tidak menolong — `git add <berkas>` pun membawa seluruh isinya. Saya buat branch backup
   `sdd-backup-2323897`, pecah patch per hunk, hanya stage hunk `imunisasiDashboard`, kembalikan sisanya
   ke working tree, commit ulang jadi `c62562d`. Terverifikasi: 38 baris ter-stage, 20 baris kembali
   jadi WIP, hitungan WIP pulih ke 29. **Pelajaran: kalau task menyentuh berkas yang sudah ber-WIP,
   pemisahan stage itu tugas pengendali, bukan aturan yang bisa dititipkan ke implementer.**
3. **Cakupan IBL dibalik ke kohort Baduta** setelah prinsip statistik/operasional ditetapkan —
   ditanyakan ulang dan disetujui pemilik produk.
4. **Task 9 dan 10 digabung**, karena tes Task 9 meng-assert HTTP 200 pada rute yang merender blade
   Task 10. Terpisah, keduanya dijamin gagal gerbangnya sendiri. Rencana saya salah urut.
5. **Task 4, 5, 8 dibatch** jadi satu dispatch — pekerjaan sebentuk di berkas yang sama.
6. **Fixture tes memori ditambal.** Aslinya memberi 2.000 anak satu tanggal lahir di luar semua kelompok
   kohort, sehingga tiap agregat memindai nol baris dan ambang 16 MB lolos karena ketiadaan pekerjaan —
   penjaga regresi OOM prod 16 September praktis mati. Tanggal kini diturunkan dari rentang kohort, plus
   assert bukan-nol.
7. **Tes Task 7 & 8 menyemai data wilayah di dalam satu method tes.** Tabel `kecamatan`/`kelurahan`/
   `puskesmas` **kosong** di `sirindu_testing`; tanpa seeder, tesnya null-pointer sebelum menyentuh
   logika kohort.
8. **Sisa task dijalankan di model termurah** setelah limit mingguan akun tercapai pada model menengah.
   Dikompensasi dengan daftar pemeriksaan bernomor di tiap dispatch reviu, dan reviu akhir di model
   paling mampu.
9. **Jendela merah yang disengaja.** `ImunisasiDashboardMemoriTest`, `ImunisasiDashboardControllerTest`,
   `QuicklinkDasborImunisasiTest`, dan `AdminController` sengaja dibiarkan merah dari Task 2 sampai
   diperbaiki Task 9/11. Konsekuensinya "suite hijau" bukan syarat di tengah rencana.

## 7. Cara melanjutkan

1. Perbaiki I1, I2, I5, M6 — hitungan menit, semuanya di blade kecuali keputusan I5.
2. Ambil keputusan untuk I3 (kebijakan NULL `usia_pemberian_max`) dan I5 (nasib kartu WUS) bersama
   pemilik produk.
3. Tulis ulang `2026-09-24-verifikasi-angka-kohort.md` (I4) — jangan dipakai sebelum diperbaiki.
4. Kirim **satu** gelombang perbaikan berisi seluruh temuan sekaligus, bukan satu agen per temuan, lalu
   **satu** scoped re-review atas rentang perbaikan itu.
5. Jalankan `tests/Feature/Imunisasi` + `tests/Unit/Support/KohortImunisasiTest.php` sampai hijau.
6. Ukur pergeseran angka di salinan data prod (§5).
7. Hapus branch `sdd-backup-2323897` dan direktori workspace `.superpowers/sdd/2026-09-24-kohort-sasaran-imunisasi/`
   setelah semuanya bersih — sejarah git jadi catatannya.
8. Baru `superpowers:finishing-a-development-branch`.

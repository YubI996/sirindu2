# Verifikasi RT & Tautan Identitas Anak — Desain

Tanggal: 2026-09-14 · Status: disetujui (percakapan 14 Sep 2026) · Tahap implementasi: T1–T4 (lihat §9)

## 1. Ringkasan

Menambah peran **RT** yang memverifikasi keberadaan & domisili anak di RT-nya lewat satu halaman
tabel interaktif, dan menautkan/memisahkan baris `anak` yang kemungkinan satu orang (hasil impor
dari sumber berbeda: Operasi Timbang, Capil, input manual). Keputusan RT masuk antrean reviu
puskesmas/Dinkes. Penggabungan fisik dua baris hanya dilakukan Dinkes dengan pemilih kolom, tercatat,
dan bisa dibatalkan.

Prinsip yang tidak boleh dilanggar:

1. **Dasbor Operasi Timbang (`/admin/timbang-dashboard`) tidak disentuh** — tanpa kolom, badge, filter,
   maupun perubahan angka. Populasi OT terkunci (`anak.sumber='operasi_timbang'` + pengukuran OT) harus
   tetap sama sebelum dan sesudah seluruh fitur ini dipakai.
2. Hasil verifikasi RT **tampil dan berpengaruh di Dasbor Gizi (`/admin/analytics`)**, yang memang
   membaca seluruh tabel `anak` tanpa filter sumber.
3. RT tidak pernah menghapus atau mengubah data langsung; semua keputusan RT = usulan yang ditinjau.
4. Setiap keputusan mencatat siapa & kapan; merge menyimpan snapshot agar bisa dipulihkan.

## 2. Peran & akun RT

- `users.role = 'rt'`, kolom baru `users.id_rt` (unsigned big int, nullable, FK ke `rt.id`).
  `id_kel`/`id_kec` diisi otomatis dari RT yang dipilih. `type = 2` (legacy "user") sehingga
  `IsAdmin` menolak akun RT dari seluruh rute `/admin/*`.
- Dibuat **hanya oleh superadmin** di manajemen user yang ada (`admin/user/create.blade.php`):
  pilihan role "RT" memunculkan cascade Kecamatan → Kelurahan → RT (endpoint RT-per-kelurahan
  yang sudah ada, `admin.getRtByKelAnak`). Validasi `storeUser`/`updateUser`: `id_rt` wajib untuk role `rt`.
- Login biasa (email + password; email boleh dibuatkan, mis. `rt05.telihan@sirindu.go.id`).
  `LoginController::login()` mengarahkan role `rt` ke `route('rt.verifikasi')`.
- Middleware baru `role:rt` (alias di `bootstrap/app.php`), grup rute `Route::middleware(['auth','role:rt'])->prefix('rt')`.
- Helper model: `User::isRt()`, `User::rt()` (belongsTo). `RoleUserSeeder` tidak diubah; akun RT dibuat manual.

## 3. Model data

### 3.1 Tabel baru

**`verifikasi_anak`** — riwayat keputusan RT per anak (satu baris per usulan; yang terakhir = berlaku).

| kolom | tipe | keterangan |
|---|---|---|
| id | bigint PK | |
| id_anak | FK anak, index | |
| id_rt | FK rt | RT pengusul |
| status | enum `berdomisili`, `pindah`, `meninggal`, `tidak_dikenal`, `bukan_rt_ini` | |
| klaim_id_rt | FK rt, nullable | diisi saat RT mengklaim anak tanpa RT sebagai warganya ("warga RT saya") |
| catatan | text nullable | |
| diusulkan_oleh / diusulkan_at | FK users / timestamp | |
| reviu | enum `diusulkan`, `disetujui`, `ditolak` | default `diusulkan` |
| ditinjau_oleh / ditinjau_at / catatan_reviu | nullable | |

**`anak_tautan`** — keputusan pasangan (link/split).

| kolom | tipe | keterangan |
|---|---|---|
| id_anak_a, id_anak_b | FK anak; **a < b**, unique(a,b) | dinormalisasi oleh service, bukan oleh UI |
| keputusan | enum `sama`, `beda` | |
| skor, via | decimal, string nullable | disalin dari kandidat saat diputus |
| status | enum `diusulkan`, `disetujui`, `ditolak`, `digabung` | `digabung` diisi service merge |
| diusulkan_oleh/at, ditinjau_oleh/at, catatan, catatan_reviu | | |

Pasangan `beda` yang `disetujui` → kandidat itu tidak pernah disarankan lagi. Pasangan `beda` yang
masih `diusulkan` sudah disembunyikan dari tab RT (RT tak perlu melihat pasangan yang ia tolak),
tetapi puskesmas bisa menolak keputusan RT sehingga pasangan muncul kembali.

**`anak_kandidat`** — hasil pindai kemiripan (precompute).

| kolom | keterangan |
|---|---|
| id_anak_a, id_anak_b (a<b, unique) | |
| skor, via (`kk`, `ortu`, `nama_kuat`), child_sim, parent_sim | |
| dipindai_at | |

Diisi ulang penuh oleh command `identitas:pindai` (truncate + insert). Baris yang sudah punya
`anak_tautan` (apa pun statusnya kecuali `ditolak`) tidak ditampilkan sebagai kandidat.

**`anak_merge_log`** — jejak penggabungan.

| kolom | keterangan |
|---|---|
| id_dipertahankan, id_dihapus | id anak |
| id_tautan | FK anak_tautan nullable |
| snapshot | JSON: baris `anak` yang dihapus utuh, `pilihan` per kolom (`a`/`b`), nilai lama kolom yang ditimpa di baris yang dipertahankan, daftar id `data_anak`/`imunisasi`/`intervensi_gizi`/`prioritas_gizi` yang dipindah |
| oleh, at, dibatalkan_oleh, dibatalkan_at | |

### 3.2 Kolom baru di `anak`

| kolom | keterangan |
|---|---|
| verif_rt_status | enum nullable = `status` verifikasi terakhir |
| verif_rt_reviu | enum nullable (`diusulkan`/`disetujui`/`ditolak`) |
| verif_rt_at | timestamp nullable |
| sumber_gabungan | JSON nullable, daftar sumber yang pernah dilebur, mis. `["operasi_timbang","capil"]`. `sumber` tetap satu nilai (lihat §6.3) |

Denormalisasi diperbarui oleh `VerifikasiRtService` setiap usulan/reviu; tidak pernah ditulis langsung dari controller.
Pengecualian: `bukan_rt_ini` adalah keputusan negatif milik satu RT (anak itu bukan warganya) dan
**tidak** menyentuh `verif_rt_*` — ia hanya menyembunyikan anak dari tab "Belum ber-RT" RT tersebut.

## 4. Cakupan tabel RT

Halaman `/rt/verifikasi` (satu halaman, tiga tab, layout ringan tanpa sidebar admin):

| tab | isi | aksi |
|---|---|---|
| **Warga RT** | `anak.id_rt = user.id_rt` | status: Berdomisili / Pindah / Meninggal / Tidak dikenal (+catatan); ikon "kemungkinan kembar" bila ada kandidat |
| **Belum ber-RT (sekelurahan)** | `anak.id_kel = rt.id_kelurahan AND anak.id_rt IS NULL` | "Warga RT saya" (= `berdomisili` + `klaim_id_rt`) / "Bukan" (= `bukan_rt_ini`) |
| **Kemungkinan sama** | pasangan `anak_kandidat` di mana ≥1 anggota ada di tab 1 atau 2; pasangannya boleh di mana saja | tampilan berdampingan (semua kolom identitas) → **Sama** / **Beda** |

- Kolom tiap baris: badge **sumber** (`operasi_timbang`→"OT", `capil`→"Capil", `manual`→"Manual"; bila
  `sumber_gabungan` terisi, badge bertumpuk), NIK **lengkap**, nama, JK, tgl lahir, nama ibu/ayah,
  No KK, alamat domisili, alamat KTP, posyandu, status verifikasi terakhir + reviu-nya.
- Tanpa data kesehatan/pengukuran/imunisasi. Tidak ada aksi tambah anak.
- Progres "X dari Y warga diverifikasi" (Y = tab 1; X = yang punya `verif_rt_status` ≠ null).
- Baris yang sudah diputus tetap tampil dengan badge keputusan + status reviu, boleh diubah (membuat
  usulan baru; usulan lama yang masih `diusulkan` ditandai `ditolak` otomatis dengan catatan "digantikan").
- Pencarian teks + filter status di sisi klien (data satu RT kecil).

Pengamanan: seluruh endpoint `/rt/*` memverifikasi objek berada di cakupan di atas (403 bila tidak) —
termasuk pasangan tautan (minimal satu anggota di cakupan).

## 5. Pemindai kemiripan — `IdentitasMatcher`

- Ekstrak aturan skor dari `CapilDedupService` ke `App\Services\IdentitasMatcher` (nilai ambang tetap
  konstanta publik yang sama; `CapilDedupService` memakainya agar kedua jalur satu aturan):
  1. tgl lahir tepat sama → nama ≥ 70 % **dan** (No KK sama **atau** ortu ≥ 87 %) → via `kk`/`ortu`
  2. tgl lahir ±1 hari → nama ≥ 90 % dan syarat yang sama
  3. nama ≥ 95 % **dan** ortu ≥ 95 % → abaikan tanggal → via `nama_kuat`
  - Blocking: 3 huruf pertama nama (normalisasi huruf kecil, spasi tunggal) — seperti sekarang.
- Cakupan pindai: **seluruh `anak`** silang sumber apa pun (bukan hanya Capil × sigizi), kecuali
  pasangan **OT × OT** (dua baris `sumber='operasi_timbang'`) — dikeluarkan supaya fitur ini tidak
  pernah mengurangi populasi OT (§1.1).
- Command `php artisan identitas:pindai` (idempoten, laporan ringkas: jumlah pasangan per `via`).
  Dijalankan otomatis di akhir `ImportCapilJob`/impor anak lainnya, dan lewat tombol "Pindai ulang"
  di halaman reviu (superadmin, dijalankan sebagai job antrean).
- Skala: ~25 rb baris, blocking prefix 3 → detik hingga puluhan detik; cukup sebagai job.

## 6. Reviu & penggabungan — `/admin/verifikasi-rt`

Akses: `is_admin`; faskes (non-super) hanya melihat kelurahannya (`user.id_kel`), superadmin semua.

### 6.1 Antrean
Dua tab: **Status domisili** (`verifikasi_anak.reviu='diusulkan'`) dan **Tautan identitas**
(`anak_tautan.status='diusulkan'`). Tiap baris: data anak (dan pasangannya), RT pengusul, catatan,
tombol Setujui / Tolak (+catatan reviu). Filter kelurahan/RT/status.

### 6.2 Efek persetujuan
| yang disetujui | efek |
|---|---|
| `berdomisili` dengan `klaim_id_rt` | `anak.id_rt = klaim_id_rt` (+`id_posyandu` dari `rt.id_posyandu` **hanya jika** `id_posyandu` kosong) |
| `berdomisili` tanpa klaim, `pindah`, `meninggal`, `tidak_dikenal`, `bukan_rt_ini` | hanya tag (`verif_rt_*`) |
| tautan `beda` | pasangan tak pernah disarankan lagi |
| tautan `sama` | masuk antrean **"Menunggu penggabungan"** (superadmin saja) |

`bukan_rt_ini` yang disetujui juga menghapus anak itu dari tab "Belum ber-RT" RT tersebut saja
(disimpan sebagai `verifikasi_anak` per RT; RT lain masih melihatnya).

### 6.3 Penggabungan (superadmin) — `IdentitasMergeService`
- Halaman pemilih kolom: dua baris berdampingan; radio per kolom untuk `nik, no_kk, nama, nama_ibu,
  nama_ayah, jk, tempat_lahir, tgl_lahir, alamat, alamat_ktp, id_kec, id_kel, id_rt, id_posyandu,
  id_puskesmas, golda, anak, catatan`. Default: identitas/kependudukan dari baris `capil`, domisili dari
  baris non-capil (mengikuti `CapilDedupService::merge`), sisanya dari baris yang dipertahankan.
- **Baris yang dipertahankan**: bila salah satu `sumber='operasi_timbang'`, baris OT **selalu** yang
  dipertahankan (id-nya tetap, `sumber` tetap `operasi_timbang`). Bila keduanya OT → merge **ditolak**
  dengan pesan (lihat §5). Selain itu Dinkes memilih.
- Eksekusi dalam satu transaksi, urutan wajib:
  1. tulis `anak_merge_log` (snapshot lengkap);
  2. pindahkan `data_anak`, `imunisasi`, `intervensi_gizi`, `prioritas_gizi` (`id_anak` → dipertahankan) —
     **sebelum** menghapus, karena FK `imunisasi.id_anak`/`data_anak.id_anak` ber-`ON DELETE CASCADE`;
     `prioritas_gizi.id_anak` UNIQUE → bila keduanya punya baris, baris milik yang dihapus dibuang (snapshot menyimpannya);
  3. hapus baris yang dilebur (NIK-nya bebas dari unique key);
  4. timpa kolom terpilih di baris yang dipertahankan; `sumber_gabungan` = gabungan unik kedua sumber;
     `pj_nama` dari baris yang dihapus dipakai hanya bila yang dipertahankan kosong;
  5. `anak_tautan.status='digabung'`; `verifikasi_anak` milik baris yang dihapus dipindah ke yang dipertahankan.
- **Batalkan**: pulihkan baris dari snapshot (id lama), kembalikan kolom yang ditimpa, pindahkan
  kembali baris anak sesuai daftar id di snapshot, `anak_tautan.status` kembali `disetujui`,
  `dibatalkan_*` diisi. Pembatalan ditolak bila sejak merge ada baris `data_anak`/`imunisasi` baru
  yang tak bisa dipetakan ke salah satu pihak (pesan jelas; Dinkes menangani manual).

### 6.4 Yang sengaja tidak dilakukan (disetujui pemilik produk)
- Dasbor **tidak** menghitung "satu anak" sebelum merge dieksekusi (dedup di level query akan menyentuh
  semua kueri statistik). Antrean menampilkan jumlah yang menunggu penggabungan.
- Dasbor OT tidak menampilkan apa pun dari fitur ini (§1.1).

## 7. Efek ke Dasbor Gizi (`/admin/analytics`) & export

- Kartu baru "Verifikasi RT": terverifikasi berdomisili / belum / pindah / meninggal / tidak dikenal
  (hanya yang `verif_rt_reviu='disetujui'` dihitung sebagai final; `diusulkan` ditampilkan sebagai
  "menunggu reviu").
- Filter baru "Status verifikasi RT" (Semua / Berdomisili / Belum diverifikasi / Pindah / Meninggal /
  Tidak dikenal). **Default "Semua"** — angka lama tidak bergeser diam-diam; pengguna yang memilih
  "Berdomisili" melihat angka bersih.
- Distribusi wilayah memakai `anak.id_rt`/`id_kel` terkini, sehingga klaim RT yang disetujui langsung
  memperbarui grafik.
- Kolom "Sumber", "Verifikasi RT", "Reviu" di export daftar anak modul **Anak** (bukan export OT).

## 8. Keamanan & privasi

- RT melihat NIK lengkap (keputusan pemilik produk) — tetapi hanya untuk cakupan §4; tidak ada
  pencarian lintas kelurahan; tidak ada export di sisi RT.
- Semua tabel keputusan menyimpan `oleh/at`; snapshot merge tidak pernah dihapus.
- Rate: tanpa batasan khusus; endpoint RT bekerja per baris (JSON), CSRF standar.
- Log aplikasi mencatat setiap merge/batalkan (`Log::info` dengan id log).

## 9. Tahapan implementasi

| tahap | isi | bisa dipakai sendiri? |
|---|---|---|
| **T1** | migrasi (`users.id_rt`, `verifikasi_anak`, kolom `verif_rt_*`), role/middleware/redirect, form akun RT, halaman RT tab Warga & Belum ber-RT, antrean reviu Status domisili + efek klaim | ya — verifikasi domisili sudah berjalan |
| **T2** | `IdentitasMatcher` (ekstraksi dari `CapilDedupService`), `anak_kandidat`, command `identitas:pindai` + hook impor, tab Kemungkinan sama, `anak_tautan`, antrean reviu Tautan | ya — link/split terekam, belum merge |
| **T3** | `anak_merge_log`, `sumber_gabungan`, halaman pemilih kolom, `IdentitasMergeService` + Batalkan | ya |
| **T4** | Dasbor Gizi: kartu, filter, kolom export Anak | ya |

## 10. Pengujian

- Feature test per tahap dengan `RefreshDatabase` (DB `sirindu_testing`; jangan jalankan dua proses tes bersamaan).
- Wajib ada: (a) scope RT — anak di RT lain → 403, pasangan tanpa anggota di cakupan → 403; (b) akun RT
  → `/admin/*` 403; (c) klaim disetujui mengisi `id_rt` dan tidak menimpa `id_posyandu` yang terisi;
  (d) usulan baru menggantikan usulan lama yang masih `diusulkan`; (e) matcher: tiga aturan + OT×OT
  dikecualikan + pasangan `beda` disetujui tidak muncul lagi; (f) merge: baris OT selalu dipertahankan,
  OT×OT ditolak, baris anak berpindah sebelum delete (tak ada yang hilang oleh cascade), `sumber_gabungan`
  benar, batalkan memulihkan persis (bandingkan snapshot); (g) **regresi dasbor OT**: `TimbangDashboardTerkunciTest`
  ditambah kasus "setelah merge OT×Capil dan setelah tag pindah/meninggal, angka OT tidak berubah";
  (h) analytics: filter default "Semua" menghasilkan angka yang sama dengan sebelum fitur.
- Blade RT dikunci dengan test render (`assertSee` badge sumber, `assertDontSee` kolom pengukuran).

## 11. Alternatif yang ditolak

- **Kelompok identitas di level query** (`anak.id_kelompok` + dedup di semua kueri): menyentuh seluruh
  statistik termasuk OT terkunci — bertentangan dengan §1.1.
- **Merge langsung saat RT menautkan** (seperti `capil:dedup`): salah tautan = data hilang permanen, RT
  bukan pemilik data.
- **Tautan bertoken tanpa akun**: tanpa jejak siapa yang memutuskan.
- **Perluas enum `anak.sumber`** dengan `verifikasi_rt`: menghilangkan jejak asal data; diganti
  `sumber_gabungan` + `verif_rt_*`.

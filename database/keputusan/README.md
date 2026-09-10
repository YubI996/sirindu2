# Berkas keputusan pemetaan posyandu

Rekaman keputusan Dinas Kesehatan untuk baris berkas Operasi Timbang yang
**sengaja tidak ditebak** sistem — nama yang tak punya padanan persis di data
induk, nama yang kembar, atau baris yang kolom posyandunya memang kosong.

Disimpan di sini, bukan di `storage/app/`, karena `storage/app/*` di-gitignore:
keputusan ini hasil verifikasi manusia dan harus bertahan lintas deploy, bisa
ditinjau ulang, dan terlihat riwayat perubahannya.

## Cara pakai

```bash
php artisan posyandu:backfill-ot "<berkas OT>.csv" \
    --keputusan=database/keputusan/posyandu-ot-juni-2026.csv
```

Tanpa `--commit` perintah ini hanya melapor. Keputusan **selalu menang** atas
tebakan matcher — itu memang gunanya.

## Format

| Kolom | Isi |
|---|---|
| `posyandu_berkas` | Nama posyandu persis seperti tertulis di berkas e-PPGBM. Boleh kosong — justru itu kasus yang tak bisa ditangani matcher. |
| `puskesmas` | Puskesmas di baris berkas yang sama. |
| `kelurahan` | Kelurahan di baris berkas yang sama. Ikut jadi kunci karena nama + puskesmas yang sama bisa menunjuk posyandu berbeda — persis kasus `ANGGREK` Belimbing vs `ANGGREK1` Gunung Telihan. |
| `posyandu_master` | Nama posyandu tujuan di data induk. Dicocokkan dengan normalisasi yang sama seperti matcher, jadi `Sejahtera 5` tetap menemukan `Sejahtera V`. |

Nama `posyandu_master` yang tidak ada di data induk **dilaporkan, tidak
didiamkan** — salah ketik di sini jangan sampai lolos jadi baris yang diam-diam
tak terisi.

## Riwayat

### `posyandu-ot-juni-2026.csv`

Diverifikasi Dinkes, September 2026. Menutup 414 baris (4,2% dari 9.884) yang
tersisa setelah perbaikan matcher — setelah berkas ini dipakai, berkas Juni 2026
tercocokkan **100%**.

Dua koreksi data induk yang menyertainya dikerjakan lewat migration
`2026_09_09_000001_koreksi_master_posyandu_hasil_verifikasi_dinkes`:

- Salah satu dari dua baris `Anggrek` (Puskesmas Bontang Barat) dinamai ulang
  jadi `Anggrek1` untuk Gunung Telihan, sesuai penetapan Dinkes.
- `Flamboyan 2` dipindah dari Bontang Utara 1 ke **Bontang Utara 2** — data induk
  yang keliru, ditegaskan Dinkes.

Catatan Dinkes menyebut "Posyandu Edelweis" dan "Posyandu Bakung" untuk baris
yang di data induk bernama `Griya Edelweis` dan `Bakung 2`. Karena di puskesmas
tersebut hanya ada satu kandidat, keduanya diperlakukan sebagai **sinonim** —
nama di data induk sengaja TIDAK diubah, dan variasi namanya dicatat di berkas
ini. Dengan begitu berkas yang menulis salah satu nama sama-sama ketemu.

### Tambahan 10 September 2026 — hasil membandingkan daftar berkas dengan server

Angka "cocok 100%" ternyata berarti *semuanya ketemu*, bukan *semuanya benar*.
Menjejerkan 113 nama posyandu berkas Juni dengan 127 baris data induk server
memunculkan **250 anak (2,5%)** yang mendarat di posyandu keliru tanpa satu pun
baris dilaporkan gagal:

- **`CENDRAWASIH` + `CENDRAWASIH1`** — keduanya jatuh ke satu baris
  `Cendrawasih` lewat tahap "nomor 1 opsional", sementara `Cendrawasih II`
  tinggal kosong. Yang salah kandang 29 anak Belimbing. Bentuknya sama persis
  dengan `ANGGREK`/`ANGGREK1`.
- **`SEJAHTERA 2` di Kanaan (147 anak)** — nama yang sama dipakai di DUA
  kelurahan (Kanaan 147, Gunung Telihan 119), sehingga Kanaan ikut tersedot ke
  `Sejahtera II` dan `Sejahtera IV` tampak kosong di dasbor. Inilah keluhan yang
  memicu seluruh penelusuran ini.
- **`Cendana` (43 anak)** dan **`Nisa Indah` (31 anak)** diperbaiki di data induk
  lewat migration `2026_09_10_000001_koreksi_cendana_dan_nisa_indah`, bukan di
  berkas ini — nama dan puskesmas posyandu tampil di layar petugas, jadi
  membiarkannya salah berarti membiarkan petugas membaca yang keliru walau
  anaknya sudah mendarat benar.

Arah dua kasus pertama ditetapkan dari **urutan id data induk**, bukan tebakan.
Seeder menyusun posyandu per kelurahan, alfabetis dalam tiap blok:

| Blok id | Kelurahan | Isi |
|---|---|---|
| 1–2 | Kanaan | Sejahtera I, **Sejahtera IV** |
| 3–10 | Gunung Telihan | Anggrek, Bakung 1, **Cendrawasih**, Jasmine, Sejahtera II/III/V, Tulip |
| 11–22 | Belimbing | Anggrek, Anyelir, **Cendrawasih II**, Gotong Royong … Suka Makmur |

`rt.id_posyandu` cocok dengan blok itu di 14 dari 15 kelurahan; setiap
penyimpangan yang tersisa selalu berupa nama yang KEMBAR di data induk (Mawar,
Tulip, Kartini, Nusa Indah) — jejak bahwa peta RT pun dulu diisi lewat pencarian
nama dan kena bug `pluck` yang sama. Untuk nama yang unik seperti `Cendrawasih`
dan `Cendrawasih II`, penunjuknya tak mungkin tercemar, dan ia sepakat dengan
blok id.

Maka: Belimbing = `Cendrawasih II`, Gunung Telihan = `Cendrawasih`, Kanaan =
`Sejahtera IV`. **Ini kebalikan dari dugaan awal** yang menyamakan penulisan
berkas dengan penomoran data induk. Untuk `Sejahtera IV` ada penguat kedua: 12
RT Kanaan terbagi rata 6/6 antara Sejahtera I dan Sejahtera IV, sedangkan berkas
mencatat 121 + 147 anak di sana — kalau Sejahtera IV benar-benar kosong, 6 RT-nya
nihil sementara 6 RT tetangganya menampung 268 anak.

`ANGGREK`/`ANGGREK1` sendiri sudah benar di server: baris blok Gunung Telihan
(id kecil) bernama `Anggrek1`, baris blok Belimbing bernama `Anggrek`.

Supaya ini tidak perlu ditemukan manual lagi, `posyandu:backfill-ot` kini
melaporkan **TABRAKAN NAMA**: dua nama berbeda di berkas yang jatuh ke satu
posyandu. Yang dihitung hanya tebakan sistem — pemetaan yang diputuskan di
berkas ini memang sengaja mengarahkan beberapa penulisan ke satu posyandu, dan
melaporkannya cuma jadi derau. Pada berkas Juni 2026 aturan itu menyisakan tepat
dua baris: `Cendrawasih` dan `Nusa Indah` — keduanya kini tertutup.

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

# Fix wave — kesmas-permintaan-data

1. TandaiSasaranKesmas: `--batalkan` bersama opsi cakupan (--kecamatan/--kelurahan/--rt/--posyandu/--puskesmas/--lahir-sejak/--lahir-sampai/--semua/--termasuk-pindah/--termasuk-tidak-aktif) kini FAILURE dengan pesan yang menyebut opsinya dan bahwa --batalkan selalu seluruh batch (helper `opsiCakupanDipakai()`).
2. `Kode batch: <uuid>` dicetak sebelum potongan pertama ditulis (jalur --jalankan); baris "Selesai" tetap memuat kode batch.
3. Spec §9 Rollback: ditambah `--alasan="…"`. Grep spec/plan/CLAUDE.md: tidak ada `--batalkan=<batch> --jalankan` lain tanpa --alasan.
4. FormSasaranKesmasTest: kasus [NULL tersimpan, '0' dikirim → NULL, 0 log] di kedua cabang update; asersi null-aware.
5. TandaiSasaranKesmasCommandTest: 4 tes baru (batalkan+cakupan ditolak & tak menulis; pesan menyebut opsi; kode batch sebelum "Selesai"; angka dry-run "akan ditandai N" == baris tertulis pada fixture campuran). Sebelum perbaikan: 3 gagal (fix 1 x2, fix 2), setelah: lulus.
6. docs/kesmas-pemetaan-permintaan-data.md: baris BB dikoreksi.

Command: `php artisan test --filter='TandaiSasaranKesmasCommandTest|FormSasaranKesmasTest'`
Hasil sebelum fix: 3 failed, 22 passed. Sesudah fix: 25 passed (239 assertions), 38 s.
Perintah tidak pernah dijalankan dengan --jalankan terhadap DB dev.

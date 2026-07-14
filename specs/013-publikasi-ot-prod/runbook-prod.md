# Runbook Publikasi Data OT ke Produksi

**Sumber dump:** `storage/app/timbang/ot_prod.sql` (7,1 MB) — hasil staging build 2026-07-09:
`anak` = **10.194** (1.227 di antaranya anak baru NIK dummy, `no` LIKE 'OT-%'),
`data_anak` = **10.352**, semua anak punya ≥1 hasil ukur.

Prasyarat (SEBELUM data): deploy kode terbaru (origin/main sudah di-push berisi
paket operasi-timbang-eksekutif + flag `--buat-tak-cocok`) + `php artisan migrate`
sukses di prod. Semua langkah dijalankan via SSH. **STOP di langkah mana pun yang
hasilnya tak sesuai.**

## 1. Pre-check

```bash
mysql -u<user> -p <db_prod> -e "SELECT
  (SELECT COUNT(*) FROM anak)            AS anak,
  (SELECT COUNT(*) FROM data_anak)       AS data_anak,
  (SELECT COUNT(*) FROM imunisasi)       AS imunisasi,
  (SELECT COUNT(*) FROM intervensi_gizi) AS intervensi;"
```

Expected: `anak` = 9738. **`imunisasi` & `intervensi` HARUS 0** — bila tidak, STOP
(tabel anak akan diganti total; record itu akan yatim/terhapus).

## 2. Backup penuh

```bash
mysqldump -u<user> -p --single-transaction <db_prod> > ~/backup_pre_ot_$(date +%Y%m%d_%H%M%S).sql
ls -lh ~/backup_pre_ot_*.sql   # pastikan ukuran wajar (bukan 0 byte)
```

Unduh salinannya ke lokal sebelum lanjut.

## 3. Upload & restore

Upload `ot_prod.sql` (scp/panel) ke server, lalu:

```bash
mysql -u<user> -p <db_prod> < ot_prod.sql
```

Dump berisi `DROP TABLE` + `CREATE TABLE` + data dan menonaktifkan FK checks
sendiri saat restore — tidak perlu TRUNCATE manual.

## 4. Verifikasi data

```bash
mysql -u<user> -p <db_prod> -e "SELECT
  (SELECT COUNT(*) FROM anak)      AS anak,
  (SELECT COUNT(*) FROM data_anak) AS data_anak,
  (SELECT COUNT(*) FROM anak WHERE \`no\` LIKE 'OT-%') AS dibuat_ot,
  (SELECT COUNT(*) FROM anak a WHERE NOT EXISTS
     (SELECT 1 FROM data_anak d WHERE d.id_anak=a.id)) AS anak_tanpa_ukur;"
```

Expected: `anak` = **10194**, `data_anak` = **10352**, `dibuat_ot` = **1227**,
`anak_tanpa_ukur` = **0**.

## 5. Snapshot & cache

```bash
cd <app_dir>
php artisan prioritas:refresh
php artisan cache:clear && php artisan view:clear
```

## 6. Smoke test

- Buka dashboard timbang → total anak terukur = 10.194.
- Buka Early Warning → tab P1–P3 terisi.
- Buka 1 anak dummy (NIK digit ke-13 = '9', mis. cari `no` LIKE 'OT-%') → profil
  & hasil ukur tampil normal.

## Rollback

```bash
mysql -u<user> -p <db_prod> < ~/backup_pre_ot_<timestamp>.sql
php artisan prioritas:refresh && php artisan cache:clear
```

Isi placeholder `<db_prod>`, `<user>`, `<app_dir>` sesuai server sebelum eksekusi.

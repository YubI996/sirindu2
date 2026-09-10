#!/usr/bin/env bash
# =====================================================================
# SIRINDU — cek cepat kondisi server (BACA-SAJA)
#
#   bash database/diagnosa/cek-cepat.sh
#
# Kredensial dibaca sendiri dari .env, jadi tak perlu mengetik user/password.
# Semua pertanyaannya SELECT — aman dijalankan di produksi kapan saja.
#
# Untuk pemeriksaan yang lebih rinci (daftar posyandu kosong, sebaran nomor
# EPID, aktivitas mingguan), pakai database/diagnosa/kondisi-server.sql.
# =====================================================================
set -uo pipefail
cd "$(dirname "$0")/../.." || exit 1

# Ambil satu nilai dari .env: buang CR milik Windows dan tanda kutip pembungkus.
env_get() {
    sed -n "s/^$1=//p" .env | head -1 | sed -e 's/\r$//' -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'$/\1/"
}

if [ ! -f .env ]; then
    echo "Tidak menemukan .env — jalankan dari dalam folder aplikasi." >&2
    exit 1
fi

DB_HOST_V="$(env_get DB_HOST)";     DB_HOST_V="${DB_HOST_V:-127.0.0.1}"
DB_PORT_V="$(env_get DB_PORT)";     DB_PORT_V="${DB_PORT_V:-3306}"
DB_NAME_V="$(env_get DB_DATABASE)"
DB_USER_V="$(env_get DB_USERNAME)"

echo "KODE : $(git log -1 --format='%h %ad %s' --date=short 2>/dev/null || echo '(bukan repo git)')"
echo "PHP  : $(php -r 'echo PHP_VERSION;') | ENV: $(env_get APP_ENV) | URL: $(env_get APP_URL)"
echo "DB   : ${DB_NAME_V}@${DB_HOST_V}:${DB_PORT_V}"
echo

MYSQL_PWD="$(env_get DB_PASSWORD)" mysql \
    -h"$DB_HOST_V" -P"$DB_PORT_V" -u"$DB_USER_V" -t "$DB_NAME_V" <<'SQL'
SELECT 'migrasi: perbaikan No. EPID' AS pemeriksaan,
       IF(EXISTS(SELECT 1 FROM migrations WHERE migration LIKE '%add_prefix_to_epid_counter%'),
          'SUDAH', 'BELUM  <-- kode lama, rawan nomor kembar') AS hasil
UNION ALL SELECT 'migrasi: posyandu baru',
       IF(EXISTS(SELECT 1 FROM migrations WHERE migration LIKE '%tambah_posyandu_baru%'), 'SUDAH', 'BELUM')
UNION ALL SELECT 'migrasi: koreksi master posyandu',
       IF(EXISTS(SELECT 1 FROM migrations WHERE migration LIKE '%koreksi_master_posyandu%'), 'SUDAH', 'BELUM')
UNION ALL SELECT 'migrasi: penamaan Anggrek tuntas',
       IF(EXISTS(SELECT 1 FROM migrations WHERE migration LIKE '%selesaikan_penamaan_anggrek%'), 'SUDAH', 'BELUM')
UNION ALL SELECT 'migrasi: koreksi Cendana & Nisa Indah',
       IF(EXISTS(SELECT 1 FROM migrations WHERE migration LIKE '%koreksi_cendana_dan_nisa_indah%'), 'SUDAH', 'BELUM')
UNION ALL SELECT 'counter EPID per penyakit',
       IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'epid_counter'
                   AND COLUMN_NAME = 'prefix'),
          'SUDAH', 'BELUM  <-- satu counter utk semua penyakit')
UNION ALL SELECT 'anak Operasi Timbang',
       (SELECT COUNT(*) FROM anak WHERE sumber = 'operasi_timbang')
UNION ALL SELECT 'posyandu kosong (%)',
       (SELECT IFNULL(ROUND(100 * SUM(id_posyandu IS NULL) / NULLIF(COUNT(*), 0), 1), 0)
        FROM anak WHERE sumber = 'operasi_timbang')
UNION ALL SELECT 'puskesmas kosong (%)',
       (SELECT IFNULL(ROUND(100 * SUM(id_puskesmas IS NULL) / NULLIF(COUNT(*), 0), 1), 0)
        FROM anak WHERE sumber = 'operasi_timbang')
UNION ALL SELECT 'posyandu nama kembar (harus 0)',
       (SELECT COUNT(*) FROM (SELECT 1 FROM posyandu GROUP BY name, id_puskesmas HAVING COUNT(*) > 1) x)
UNION ALL SELECT 'No. EPID kembar (harus 0)',
       (SELECT COUNT(*) FROM (SELECT 1 FROM surveillance_cases GROUP BY no_registrasi HAVING COUNT(*) > 1) y)
UNION ALL SELECT 'kasus PD3I',
       (SELECT COUNT(*) FROM surveillance_cases)
UNION ALL SELECT 'input kasus terakhir',
       (SELECT IFNULL(MAX(created_at), '(belum ada)') FROM surveillance_cases)
UNION ALL SELECT 'input anak terakhir',
       (SELECT IFNULL(MAX(created_at), '(belum ada)') FROM anak);
SQL

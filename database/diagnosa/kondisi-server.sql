-- =====================================================================
-- SIRINDU — Diagnosa kondisi server
-- =====================================================================
-- SELURUHNYA BACA-SAJA. Tidak ada INSERT/UPDATE/DELETE/ALTER di berkas ini,
-- jadi aman dijalankan di produksi kapan pun.
--
--   mysql -u <user> -p <nama_db> < database/diagnosa/kondisi-server.sql
--
-- Di Docker:
--   docker compose exec -T db mysql -u root -p sirindu < database/diagnosa/kondisi-server.sql
--
-- Atau tempel per bagian ke phpMyAdmin.
--
-- Tujuannya menjawab empat hal sebelum deploy:
--   A. Server mana ini, dan versi kode/migrasi apa yang berjalan
--   B. Seberapa parah masalah posyandu di sini, dan apa perbaikannya sudah masuk
--   C. Apakah perbaikan No. EPID sudah masuk, dan adakah risiko tabrakan nomor
--   D. Seberapa aktif server ini — untuk membandingkan dua instalasi
-- =====================================================================


-- =====================================================================
-- A. IDENTITAS SERVER
-- =====================================================================
-- Cocokkan `basis_data` dan `host` dengan server yang Anda maksud. Pesan error
-- Laravel juga mencantumkan Host — kalau berbeda, Anda sedang melihat mesin lain.

SELECT '=== A. IDENTITAS SERVER ===' AS bagian;

SELECT
    DATABASE()             AS basis_data,
    @@hostname             AS host_mysql,
    VERSION()              AS versi_mysql,
    NOW()                  AS waktu_server;

-- Migrasi terakhir. Kalau `2026_09_09_000001_koreksi_master_posyandu...` belum
-- muncul, perbaikan posyandu terbaru BELUM terpasang di server ini.
SELECT '--- 10 migrasi terakhir ---' AS bagian;
SELECT batch, migration FROM migrations ORDER BY id DESC LIMIT 10;

-- Migrasi penting untuk pekerjaan yang tertunda — semuanya harus 'SUDAH'.
SELECT '--- migrasi kunci ---' AS bagian;
SELECT
    'add_prefix_to_epid_counter (perbaikan No. EPID)' AS migrasi,
    IF(EXISTS(SELECT 1 FROM migrations WHERE migration LIKE '%add_prefix_to_epid_counter%'), 'SUDAH', 'BELUM') AS status
UNION ALL SELECT
    'tambah_posyandu_baru_ot_juni_2026',
    IF(EXISTS(SELECT 1 FROM migrations WHERE migration LIKE '%tambah_posyandu_baru_ot_juni_2026%'), 'SUDAH', 'BELUM')
UNION ALL SELECT
    'koreksi_master_posyandu_hasil_verifikasi_dinkes',
    IF(EXISTS(SELECT 1 FROM migrations WHERE migration LIKE '%koreksi_master_posyandu%'), 'SUDAH', 'BELUM');


-- =====================================================================
-- B. KONDISI POSYANDU
-- =====================================================================

SELECT '=== B. KONDISI POSYANDU ===' AS bagian;

-- Inti masalahnya. Sebelum perbaikan, di berkas Juni 2026: id_posyandu kosong
-- 23,8% dan id_puskesmas kosong 74,7%. Sesudah perbaikan + keputusan Dinkes,
-- keduanya harus mendekati 0%.
SELECT '--- anak Operasi Timbang: berapa yang wilayahnya kosong ---' AS bagian;
SELECT
    COUNT(*)                                                     AS total_anak_ot,
    SUM(id_posyandu IS NULL)                                     AS posyandu_kosong,
    ROUND(100 * SUM(id_posyandu IS NULL) / NULLIF(COUNT(*), 0), 1)  AS persen_posyandu_kosong,
    SUM(id_puskesmas IS NULL)                                    AS puskesmas_kosong,
    ROUND(100 * SUM(id_puskesmas IS NULL) / NULLIF(COUNT(*), 0), 1) AS persen_puskesmas_kosong
FROM anak
WHERE sumber = 'operasi_timbang';

-- Berapa posyandu yang tampil kosong di dashboard. Semula 35 dari 121;
-- sesudah perbaikan + keputusan Dinkes seharusnya tinggal 7 (yang memang
-- tidak punya anak di berkas Juni).
SELECT '--- posyandu tanpa satu pun anak OT ---' AS bagian;
SELECT
    COUNT(*)                                       AS posyandu_master,
    SUM(jumlah_anak = 0)                           AS tampil_kosong
FROM (
    SELECT p.id, COUNT(a.id) AS jumlah_anak
    FROM posyandu p
    LEFT JOIN anak a ON a.id_posyandu = p.id AND a.sumber = 'operasi_timbang'
    GROUP BY p.id
) t;

SELECT '--- daftar posyandu yang tampil kosong ---' AS bagian;
SELECT p.name AS posyandu, k.name AS puskesmas
FROM posyandu p
LEFT JOIN puskesmas k ON k.id = p.id_puskesmas
LEFT JOIN anak a ON a.id_posyandu = p.id AND a.sumber = 'operasi_timbang'
GROUP BY p.id, p.name, k.name
HAVING COUNT(a.id) = 0
ORDER BY k.name, p.name;

-- Nama kembar dalam satu puskesmas membuat sistem menyerah dan datanya
-- tak terlihat. Setelah koreksi Dinkes, hasilnya harus KOSONG.
SELECT '--- nama posyandu kembar dalam puskesmas yang sama (harus kosong) ---' AS bagian;
SELECT p.name AS posyandu, k.name AS puskesmas, COUNT(*) AS jumlah_baris,
       GROUP_CONCAT(p.id ORDER BY p.id) AS id_baris
FROM posyandu p
LEFT JOIN puskesmas k ON k.id = p.id_puskesmas
GROUP BY p.name, p.id_puskesmas, k.name
HAVING COUNT(*) > 1;

-- Apakah 6 posyandu tambahan sudah ada, dan apakah koreksi Dinkes sudah masuk.
SELECT '--- posyandu yang seharusnya ada setelah perbaikan ---' AS bagian;
SELECT nama_dicari AS posyandu,
       IF(EXISTS(SELECT 1 FROM posyandu WHERE name = nama_dicari), 'ADA', 'BELUM ADA') AS status
FROM (
    SELECT 'Sejahtera Etam' AS nama_dicari UNION ALL SELECT 'Menur 1'
    UNION ALL SELECT 'Nusa Indah 3' UNION ALL SELECT 'Sekatup'
    UNION ALL SELECT 'Pasir Putih 11' UNION ALL SELECT 'Mawar Merah'
    UNION ALL SELECT 'Anggrek1'
) d;


-- =====================================================================
-- C. KONDISI NOMOR EPID
-- =====================================================================

SELECT '=== C. KONDISI NOMOR EPID ===' AS bagian;

-- Kalau kolom `prefix` BELUM ADA, server ini menjalankan kode lama: satu counter
-- dipakai bersama semua penyakit, dan tabrakan nomor tinggal menunggu waktu.
SELECT '--- apakah perbaikan counter sudah terpasang ---' AS bagian;
SELECT IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'epid_counter'
          AND COLUMN_NAME = 'prefix'
    ),
    'SUDAH — counter per penyakit (kode baru)',
    'BELUM — satu counter untuk semua penyakit (kode lama, rawan tabrakan)'
) AS status_counter;

SELECT '--- isi epid_counter ---' AS bagian;
SELECT * FROM epid_counter ORDER BY tahun DESC, prefix;

-- Deteksi risiko tabrakan: nomor tertinggi yang BENAR-BENAR terpakai per prefix,
-- dibanding nilai counter. Kalau counter < terpakai pada kode LAMA, nomor
-- berikutnya pasti bentrok. Pada kode baru ini aman (counter menyembuhkan diri),
-- tapi tetap berguna untuk melihat apakah counter tertinggal.
SELECT '--- nomor terpakai vs counter, per prefix tahun ini ---' AS bagian;
SELECT
    SUBSTRING_INDEX(no_registrasi, '-', 1)                       AS prefix,
    COUNT(*)                                                     AS jumlah_kasus,
    MAX(CAST(RIGHT(no_registrasi, 3) AS UNSIGNED))               AS urutan_tertinggi_terpakai
FROM surveillance_cases
WHERE no_registrasi REGEXP CONCAT('^([A-Z]{1,3}-)?1710', RIGHT(YEAR(CURDATE()), 2), '[0-9]{3}$')
GROUP BY prefix
ORDER BY prefix;

-- Nomor EPID kembar. Kolomnya UNIQUE, jadi ini HARUS kosong — kalau ada isinya,
-- indeks uniknya hilang dan itu masalah serius tersendiri.
SELECT '--- nomor EPID kembar (harus kosong) ---' AS bagian;
SELECT no_registrasi, COUNT(*) AS jumlah
FROM surveillance_cases
GROUP BY no_registrasi
HAVING COUNT(*) > 1;

-- Nomor di luar format resmi. Sengaja diabaikan counter — bukan kesalahan,
-- tapi berguna diketahui saat menelusuri keluhan penomoran.
SELECT '--- nomor di luar format resmi ---' AS bagian;
SELECT COUNT(*) AS jumlah_nomor_legacy
FROM surveillance_cases
WHERE no_registrasi NOT REGEXP '^([A-Z]{1,3}-)?1710[0-9]{5}$';


-- =====================================================================
-- D. SEBERAPA AKTIF SERVER INI
-- =====================================================================
-- Jalankan bagian ini di KEDUA server lalu bandingkan. Kalau server lama masih
-- menerima input baru, ada data yang hanya hidup di sana.

SELECT '=== D. AKTIVITAS ===' AS bagian;

SELECT 'anak'               AS tabel, COUNT(*) AS jumlah, MAX(created_at) AS input_terakhir FROM anak
UNION ALL SELECT 'data_anak',          COUNT(*), MAX(created_at) FROM data_anak
UNION ALL SELECT 'surveillance_cases', COUNT(*), MAX(created_at) FROM surveillance_cases
UNION ALL SELECT 'imunisasi',          COUNT(*), MAX(created_at) FROM imunisasi
UNION ALL SELECT 'users',              COUNT(*), MAX(created_at) FROM users;

SELECT '--- anak per sumber ---' AS bagian;
SELECT sumber, COUNT(*) AS jumlah, MAX(created_at) AS terakhir
FROM anak GROUP BY sumber ORDER BY jumlah DESC;

SELECT '--- kasus PD3I 90 hari terakhir (per minggu) ---' AS bagian;
SELECT YEARWEEK(created_at, 3) AS minggu, COUNT(*) AS kasus_baru
FROM surveillance_cases
WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
GROUP BY minggu ORDER BY minggu DESC;

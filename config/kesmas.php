<?php

/*
 * Daftar opsi & label data Kesmas anak — SATU sumber untuk form (opsi select),
 * halaman detail (label/badge), Export Kesmas (judul kolom), dan dasbor Kesmas nanti.
 * Spec: docs/superpowers/specs/2026-09-15-data-kesmas-design.md §2.3.
 *
 * Nilai select disimpan apa adanya (teks label) untuk daftar tanpa kunci;
 * untuk `skrining` dan `hepatitis_b` yang disimpan adalah KUNCI (enum di DB).
 */
return [
    'status_tk_paud'   => ['Tidak ikut', 'PAUD/KB', 'TK A', 'TK B'],
    'jenis_persalinan' => ['Spontan', 'SC', 'Vakum', 'Forsep', 'Lainnya'],
    'penolong_lahir'   => ['Dokter Spesialis', 'Dokter Umum', 'Bidan', 'Lainnya'],
    'skrining'         => ['belum' => 'Belum', 'normal' => 'Normal', 'tidak_normal' => 'Tidak normal'],
    'hepatitis_b'      => ['belum' => 'Belum', 'non_reaktif' => 'Non reaktif', 'reaktif' => 'Reaktif'],
    'pemeriksaan_gigi' => ['Sehat', 'Karies', 'Masalah lain'],
    'rujukan'          => ['Tidak dirujuk', 'Dokter gigi', 'Dokter spesialis anak', 'Rumah sakit', 'Lainnya'],

    // Checkbox layanan per kunjungan (kolom data_anak): label form, singkatan badge
    // di detail anak, dan judul kolom di export. Urutan = urutan tampil.
    'layanan' => [
        'kn1'  => ['label' => 'KN1 — Kunjungan Neonatal 1 (6–48 jam)',            'badge' => 'KN1',  'kolom' => 'KN1'],
        'kn3'  => ['label' => 'KN3 — Kunjungan Neonatal 3 (hari ke-8 s/d 28)',    'badge' => 'KN3',  'kolom' => 'KN3'],
        'mtbm' => ['label' => 'MTBM — Manajemen Terpadu Bayi Muda',               'badge' => 'MTBM', 'kolom' => 'MTBM'],
        'mtbs' => ['label' => 'MTBS — Manajemen Terpadu Balita Sakit',            'badge' => 'MTBS', 'kolom' => 'MTBS'],
        'pkat' => ['label' => 'PKAT — Pelayanan Kesehatan Anak Terpadu (6 bulan)', 'badge' => 'PKAT', 'kolom' => 'PKAT'],
        'skrining_atresia_bilier' => ['label' => 'Skrining atresia bilier (kartu warna tinja)', 'badge' => 'AB', 'kolom' => 'Atresia Bilier'],
        'oralit_zinc'      => ['label' => 'Oralit & zinc sesuai standar', 'badge' => 'O+Z', 'kolom' => 'Oralit+Zinc'],
        'mbg'              => ['label' => 'Makan Bergizi Gratis (MBG)',   'badge' => 'MBG', 'kolom' => 'MBG'],
        'kelas_ibu_balita' => ['label' => 'Ikut Kelas Ibu Balita',        'badge' => 'KIB', 'kolom' => 'Kelas Ibu Balita'],
    ],

    // Field teks per kunjungan yang ditampilkan sebagai keterangan di detail anak.
    'keterangan_kunjungan' => [
        'pemeriksaan_gigi'    => 'Gigi',
        'rujukan'             => 'Rujukan',
        'mt_pangan_lokal'     => 'MT pangan lokal',
        'catatan_pengukuran'  => 'Catatan',
        'pemeriksaan_lainnya' => 'Pemeriksaan lain',
        'pola_makan'          => 'Pola makan',
        'pola_asuh'           => 'Pola asuh',
        'intervensi'          => 'Intervensi',
    ],
];

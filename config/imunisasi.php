<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Alasan Tidak Menerima Imunisasi
    |--------------------------------------------------------------------------
    |
    | Daftar opsi dropdown "Alasan Tidak Imunisasi" pada form pengukuran berkala
    | (resources/views/admin/anak/data-anak.blade.php). Opsi "Lainnya" tidak
    | dicantumkan di sini — ditambahkan otomatis di view dan, bila dipilih,
    | nilainya diambil dari input teks `alasan_tidak_imunisasi_lain`.
    |
    | Daftar di bawah adalah daftar final dari client (2026-06-12).
    |
    */

    'alasan_tidak_imunisasi' => [
        'Pasangan/keluarga melarang',
        'Halal/haram vaksin terkait keyakinan/kepercayaan/tradisi',
        'Anak sakit saat jadwal imunisasi',
        'Trauma KIPI (demam, bengkak, rewel pasca imunisasi, dll)',
        'Usia belum cukup imunisasi',
        'Orang tua bekerja',
        'Tidak ada yang mengantar',
        'Tidak tahu jadwal dan tempat imunisasi',
        'Masih mengejar imunisasi',
    ],

];

<?php

/**
 * Hitung usia dalam bulan penuh dari tanggal lahir ke tanggal acuan
 * (mis. tgl kunjungan / tgl ukur).
 *
 * Mengembalikan null jika salah satu tanggal kosong/tidak valid, atau jika
 * tanggal acuan mendahului tanggal lahir (data tidak masuk akal). Pemanggil
 * yang butuh nilai NOT NULL bisa pakai `usia_bulan(...) ?? 0`.
 */
function usia_bulan($tglLahir, $tglAcuan): ?int
{
    if (empty($tglLahir) || empty($tglAcuan) || $tglLahir === '0000-00-00') {
        return null;
    }
    try {
        $lahir = \Carbon\Carbon::parse($tglLahir)->startOfDay();
        $acuan = \Carbon\Carbon::parse($tglAcuan)->startOfDay();
    } catch (\Throwable $e) {
        return null;
    }
    if ($acuan->lt($lahir)) {
        return null;
    }
    // diffInMonths Carbon 3 = float bertanda; dipastikan >= 0 oleh guard di atas.
    return (int) $lahir->diffInMonths($acuan);
}

/**
 * Normalkan nilai posisi/cara ukur ke kanonik yang dikenal z_score():
 *   'H' = Berdiri / Tinggi Badan
 *   'L' = Terlentang / Panjang Badan
 *
 * Importer & form historis menyimpan beragam kosakata ("Bb", "berdiri",
 * "terlentang", "panjang", "1"/"2", dst). z_score() hanya mengenal 'H'/'L',
 * jadi semua varian harus dipetakan ke sini sebelum disimpan. Default 'L'
 * (panjang badan) bila kosong/tidak dikenali — selaras default importer lama.
 */
function normalisasi_posisi($raw): string
{
    $v = strtolower(trim((string) $raw));

    $berdiri = ['h', 'b', 'bb', 'berdiri', 'tinggi', 'tinggi badan', 'standing', 'std', '2'];
    if (in_array($v, $berdiri, true)) {
        return 'H';
    }
    // Sisanya (l, terlentang, berbaring, panjang, lying, '1', kosong) → 'L'.
    return 'L';
}

/**
 * Klasifikasi status gizi untuk koleksi pengukuran.
 *
 * Delegasi ke App\Services\StatusGiziService (satu sumber kebenaran) agar rumus
 * z-score + koreksi posisi tidak terduplikasi. Mengembalikan array berindeks
 * sama dgn input, tiap elemen: bln, tinggi, berat, imt, bb, tb, bt.
 *
 * @param iterable $x objek dgn properti tb, bb, bln, posisi, jk
 */
function z_score($x)
{
    $svc = app(\App\Services\StatusGiziService::class);
    $hasilx = [];
    foreach ($x as $key => $data) {
        $r = $svc->klasifikasi($data->bb, $data->tb, $data->bln, $data->posisi, $data->jk);
        $hasilx[$key] = [
            'bln' => $r['bln'],
            'tinggi' => $r['tinggi'],
            'berat' => $r['berat'],
            'imt' => $r['imt'],
            'bb' => $r['bb'],
            'tb' => $r['tb'],
            'bt' => $r['bt'],
        ];
    }
    return $hasilx;
}

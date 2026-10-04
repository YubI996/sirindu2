<?php

namespace App\Support;

/**
 * Seberapa "real" sebuah NIK untuk anak tertentu. Dipakai importer saat dua baris ternyata anak yang
 * sama dan harus memilih NIK mana yang bertahan: yang lebih real menggantikan, seri -> NIK di DB.
 *
 * Petunjuk terkuat adalah bagian tanggal lahir di dalam NIK (posisi 7-12: DDMMYY, perempuan DD+40).
 * NIK ibu yang terselip di kolom NIK anak membawa tanggal lahir ibu, sehingga kalah dari NIK yang
 * tanggalnya cocok dengan anak. Murni (tanpa framework) supaya bisa diuji tanpa boot aplikasi.
 */
final class KeaslianNik
{
    public const KOSONG           = 0;   // kosong atau bukan angka
    public const LAINNYA          = 5;   // angka tapi bukan 15/16 digit
    public const DUMMY            = 10;  // NIK buatan sistem (digit ke-13 = 9), 16 digit
    public const POTONGAN         = 30;  // 15 digit: satu digit hilang (mis. pembulatan Excel)
    public const TAK_SESUAI       = 50;  // 16 digit tapi tanggal lahir/jk di dalamnya tak cocok dengan anak
    public const TANPA_PEMBANDING = 60;  // 16 digit masuk akal, tapi tgl lahir/jk anak tak diketahui
    public const SESUAI           = 80;  // 16 digit, tanggal lahir + jk di dalamnya cocok dengan anak

    /**
     * @param  string|null  $tglLahir  YYYY-MM-DD
     * @param  int|null     $jk        1 = laki-laki, 2 = perempuan
     */
    public static function skor(?string $nik, ?string $tglLahir, ?int $jk): int
    {
        $nik = trim((string) $nik);
        if ($nik === '' || !ctype_digit($nik)) {
            return self::KOSONG;
        }

        $panjang = strlen($nik);
        if ($panjang === 16 && $nik[12] === '9') {
            return self::DUMMY;
        }
        if ($panjang === 15) {
            return self::POTONGAN;
        }
        if ($panjang !== 16) {
            return self::LAINNYA;
        }

        $dd    = (int) substr($nik, 6, 2);
        $mm    = (int) substr($nik, 8, 2);
        $yy    = (int) substr($nik, 10, 2);
        $hari  = $dd > 40 ? $dd - 40 : $dd;
        if ($mm < 1 || $mm > 12 || $hari < 1 || $hari > 31) {
            return self::TAK_SESUAI;
        }

        if ($tglLahir === null || strlen($tglLahir) < 10 || $jk === null) {
            return self::TANPA_PEMBANDING;
        }

        $tahun = (int) substr($tglLahir, 0, 4);
        $bulan = (int) substr($tglLahir, 5, 2);
        $tgl   = (int) substr($tglLahir, 8, 2);
        $nikPerempuan = $dd > 40;

        return ($nikPerempuan === ($jk === 2) && $hari === $tgl && $mm === $bulan && $yy === $tahun % 100)
            ? self::SESUAI
            : self::TAK_SESUAI;
    }
}

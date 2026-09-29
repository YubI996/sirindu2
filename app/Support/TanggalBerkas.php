<?php

namespace App\Support;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Pembacaan tanggal dari berkas import — satu pintu untuk semua kelas Import.
 *
 * Masalahnya cuma satu: 05/02/2020 tidak bisa dibaca tanpa tahu urutan berkasnya.
 * Carbon::parse() membacanya gaya AS (2 Mei) tanpa peringatan apa pun, jadi berkas
 * Indonesia yang wajar tersimpan sebagai tanggal yang salah dan tak seorang pun tahu.
 *
 * deteksi() menyimpulkan urutan dari isi berkas (ketemu 25/02 → hari di depan);
 * parse() menerapkan kesimpulan atau pilihan tegas petugas. Sel tanggal Excel,
 * serial Excel, dan 2020-01-15 tidak pernah ambigu — bentuk itu dibaca apa adanya.
 */
class TanggalBerkas
{
    /** Pilihan format yang disimpan di import_logs.format_tanggal. */
    public const OTOMATIS = 'auto';
    public const DMY      = 'dmy';
    public const MDY      = 'mdy';

    /** Aturan validasi untuk pilihan format di form import. */
    public const ATURAN = 'nullable|in:'.self::OTOMATIS.','.self::DMY.','.self::MDY;

    /** Hasil deteksi() selain DMY/MDY. */
    public const KOSONG = 'kosong'; // tak ada triplet sama sekali — format tak relevan
    public const AMBIGU = 'ambigu'; // ada triplet, tapi tak ada yang menentukan (atau saling bertabrakan)

    /** 05/02/2020 atau 05-02-2020, boleh berekor jam. */
    private const TRIPLET = '~^(\d{1,2})[/-](\d{1,2})[/-](\d{4})(?:[ T]\d{1,2}:\d{2}(?::\d{2})?)?$~';

    /** 2020-01-15 atau 2020/01/15 — tahun di depan, tak mungkin salah baca. */
    private const ISO = '~^(\d{4})[/-](\d{1,2})[/-](\d{1,2})(?:[ T]\d{1,2}:\d{2}(?::\d{2})?)?$~';

    /** Pilihan untuk dropdown form; kuncinya nilai yang disimpan. */
    public static function pilihan(): array
    {
        return [
            self::OTOMATIS => 'Otomatis (deteksi dari isi berkas)',
            self::DMY      => 'Hari/Bulan/Tahun — 31/12/2025',
            self::MDY      => 'Bulan/Hari/Tahun — 12/31/2025',
        ];
    }

    /**
     * Simpulkan urutan triplet dari sekumpulan nilai mentah.
     *
     * @return self::DMY|self::MDY|self::KOSONG|self::AMBIGU
     */
    public static function deteksi(iterable $nilai): string
    {
        $adaTriplet = false;
        $dmy = 0;
        $mdy = 0;

        foreach ($nilai as $v) {
            if (!is_scalar($v)) continue;

            $s = trim((string) $v);
            if ($s === '' || !preg_match(self::TRIPLET, $s, $m)) continue;

            $adaTriplet = true;
            [$a, $b] = [(int) $m[1], (int) $m[2]];

            if ($a > 12 && $b <= 12) $dmy++;
            if ($b > 12 && $a <= 12) $mdy++;
        }

        if (!$adaTriplet) return self::KOSONG;

        // Satu berkas ditulis dengan satu urutan. Kalau buktinya bertabrakan,
        // yang minoritas hampir pasti salah ketik — ikut mayoritas, jangan
        // membuang seluruh berkas karena satu sel menyimpang.
        if ($dmy > $mdy) return self::DMY;
        if ($mdy > $dmy) return self::MDY;

        // Seri (bukti sama banyak) atau tak ada bukti sama sekali: tak ada
        // mayoritas untuk diikuti. Ditolak, bukan ditebak — petugas memilih
        // format tegas di form import lalu mengulang.
        return self::AMBIGU;
    }

    /**
     * Contoh nilai yang masih bisa dua arti — dipakai di pesan error agar petugas
     * melihat nilai dari berkasnya sendiri, bukan contoh karangan.
     */
    public static function contohAmbigu(iterable $nilai): ?string
    {
        foreach ($nilai as $v) {
            if (!is_scalar($v)) continue;

            $s = trim((string) $v);
            if ($s === '' || !preg_match(self::TRIPLET, $s, $m)) continue;

            if ((int) $m[1] <= 12 && (int) $m[2] <= 12) return $s;
        }

        return null;
    }

    /**
     * Baca satu nilai jadi 'Y-m-d', atau null kalau tak terbaca.
     *
     * Dengan format OTOMATIS, triplet yang masih bisa dua arti sengaja dikembalikan
     * null — menebaknya berarti menyimpan tanggal yang salah tanpa jejak.
     */
    public static function parse($nilai, string $format = self::OTOMATIS): ?string
    {
        if ($nilai === null || $nilai === '') return null;

        if ($nilai instanceof \DateTimeInterface) {
            return Carbon::instance($nilai)->format('Y-m-d');
        }
        if (!is_scalar($nilai)) return null;

        $s = trim((string) $nilai);
        if ($s === '') return null;

        if (is_numeric($s)) {
            try {
                return Carbon::instance(Date::excelToDateTimeObject((float) $s))->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }

        if (preg_match(self::ISO, $s, $m)) {
            return self::rakit((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        if (preg_match(self::TRIPLET, $s, $m)) {
            [$a, $b, $tahun] = [(int) $m[1], (int) $m[2], (int) $m[3]];

            if ($format === self::DMY)          [$hari, $bulan] = [$a, $b];
            elseif ($format === self::MDY)      [$hari, $bulan] = [$b, $a];
            elseif ($a > 12 && $b <= 12)        [$hari, $bulan] = [$a, $b]; // hanya satu bacaan yang mungkin
            elseif ($b > 12 && $a <= 12)        [$hari, $bulan] = [$b, $a];
            else                                return null;

            return self::rakit($tahun, $bulan, $hari);
        }

        return null;
    }

    private static function rakit(int $tahun, int $bulan, int $hari): ?string
    {
        if (!checkdate($bulan, $hari, $tahun)) return null; // 31/02/2020, atau bulan 25 karena format salah

        return sprintf('%04d-%02d-%02d', $tahun, $bulan, $hari);
    }
}

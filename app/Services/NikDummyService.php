<?php

namespace App\Services;

use App\Models\Anak;
use Illuminate\Support\Facades\Log;

/**
 * Service untuk membuat dan mendeteksi NIK Dummy.
 *
 * Format NIK Dummy: [6 digit kode wilayah][6 digit tgl lahir][4 digit urutan 9001–9999]
 *
 * Tgl lahir encoding:
 *   - Laki-laki: DDMMYY (e.g., 010100)
 *   - Perempuan: (DD+40)MMYY (e.g., 410100 untuk tanggal 01/01/00)
 *
 * Urutan dimulai dari 9001 — indeks ke-12 (basis 0) / digit ke-13 (basis 1) selalu '9' sebagai tanda NIK dummy.
 */
class NikDummyService
{
    /** Kode wilayah default (Kota Bontang) jika tidak ditemukan */
    const DEFAULT_KODE_WILAYAH = '647400';

    /** Urutan minimum untuk NIK dummy */
    const URUTAN_MIN = 9001;

    /** Urutan maksimum untuk NIK dummy */
    const URUTAN_MAX = 9999;

    /**
     * Generate NIK dummy baru.
     *
     * @param  string|null $kodeWilayah  6-digit kode BPS wilayah
     * @param  string      $tanggalLahir Format Y-m-d
     * @param  string      $jenisKelamin 'L' atau 'P'
     * @return string NIK 16 digit
     * @throws \RuntimeException jika urutan melebihi batas
     */
    public function generate(?string $kodeWilayah, string $tanggalLahir, string $jenisKelamin): string
    {
        if (empty($kodeWilayah) || strlen($kodeWilayah) !== 6) {
            Log::warning('NikDummyService: kodeWilayah kosong/tidak valid, pakai default.', [
                'kodeWilayah' => $kodeWilayah,
            ]);
            $kodeWilayah = self::DEFAULT_KODE_WILAYAH;
        }

        $tglPart = $this->encodeTanggalLahir($tanggalLahir, $jenisKelamin);
        $prefix  = $kodeWilayah . $tglPart;

        $urutan  = $this->nextUrutan($prefix);

        return $prefix . str_pad((string) $urutan, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Cari NIK dummy yang sudah ada untuk orang dengan data yang sama.
     * Fuzzy match nama ≥87% + exact tanggal_lahir + exact jenis_kelamin.
     *
     * Pembeda No KK: bila $noKk diberikan dan kandidat juga punya no_kk, tetapi
     * keduanya BERBEDA → dianggap anak berbeda (keluarga beda) sehingga tidak
     * digabung. Bila salah satu KK kosong, tak bisa dibedakan → jatuh ke fuzzy
     * nama (perilaku lama).
     *
     * @return string|null NIK yang ditemukan atau null
     */
    public function findExisting(string $nama, string $tanggalLahir, string $jenisKelamin, ?string $noKk = null): ?string
    {
        $jkValue = strtoupper($jenisKelamin) === 'L' ? 1 : 2;
        $noKk    = $noKk !== null ? trim($noKk) : '';

        $kandidat = Anak::where('tgl_lahir', $tanggalLahir)
            ->where('jk', $jkValue)
            ->whereRaw("SUBSTRING(nik, 13, 1) = '9'")
            ->whereRaw("LENGTH(nik) = 16")
            ->get(['nik', 'nama', 'no_kk']);

        foreach ($kandidat as $anak) {
            similar_text($nama, $anak->nama, $pct);
            if ($pct < 87) {
                continue;
            }

            // Keduanya punya KK tapi beda → bukan anak yang sama.
            $kkKandidat = trim((string) ($anak->no_kk ?? ''));
            if ($noKk !== '' && $kkKandidat !== '' && $kkKandidat !== $noKk) {
                continue;
            }

            return $anak->nik;
        }

        return null;
    }

    /**
     * Cek apakah sebuah NIK adalah NIK dummy (indeks ke-12 / digit ke-13 basis 1 = '9').
     */
    public static function isDummy(string $nik): bool
    {
        return strlen($nik) === 16 && $nik[12] === '9';
    }

    /**
     * Dapatkan urutan berikutnya (9001–9999) untuk prefix tertentu.
     *
     * @throws \RuntimeException jika sudah mencapai 9999
     */
    public function nextUrutan(string $prefix): int
    {
        $likePattern = $prefix . '%';
        $rawLength   = "LENGTH(nik) = 16";
        $rawDigit    = "SUBSTRING(nik, 13, 1) = '9'";
        $rawMax      = "MAX(CAST(SUBSTRING(nik, 13, 4) AS UNSIGNED)) as max_urutan";

        $maxAnak = Anak::where('nik', 'like', $likePattern)
            ->whereRaw($rawLength)->whereRaw($rawDigit)
            ->selectRaw($rawMax)->value('max_urutan');

        $maxSurv = \App\Models\SurveillanceCase::where('nik', 'like', $likePattern)
            ->whereRaw($rawLength)->whereRaw($rawDigit)
            ->selectRaw($rawMax)->value('max_urutan');

        $max  = max($maxAnak ?? (self::URUTAN_MIN - 1), $maxSurv ?? (self::URUTAN_MIN - 1));
        $next = $max + 1;

        if ($next > self::URUTAN_MAX) {
            Log::warning('NikDummyService: urutan NIK dummy mencapai batas 9999.', ['prefix' => $prefix]);
            throw new \RuntimeException(
                "Kuota NIK dummy habis untuk wilayah/tanggal lahir ini (prefix: {$prefix}). Hubungi administrator."
            );
        }

        return $next;
    }

    /**
     * Encode tanggal lahir ke 6 digit untuk bagian tengah NIK.
     * Perempuan: DD + 40.
     */
    private function encodeTanggalLahir(string $tanggalLahir, string $jenisKelamin): string
    {
        try {
            $carbon = \Carbon\Carbon::parse($tanggalLahir);
        } catch (\Exception $e) {
            // Fallback: gunakan 010100
            return '010100';
        }

        $dd = (int) $carbon->format('d');
        $mm = $carbon->format('m');
        $yy = $carbon->format('y');

        if (strtoupper($jenisKelamin) === 'P') {
            $dd = $dd + 40;
        }

        return str_pad((string) $dd, 2, '0', STR_PAD_LEFT) . $mm . $yy;
    }
}

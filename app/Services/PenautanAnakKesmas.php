<?php

namespace App\Services;

use App\Models\Anak;

/**
 * Penautan baris Import Data Kesmas ke anak yang SUDAH ADA (spec 2026-10-15-import-kesmas-design.md §4).
 * Hanya mencari: tidak membuat anak, tidak mengubah NIK.
 *
 * NIK dipercaya hanya bila tgl lahir sama ATAU nama mirip — 72% anak ganda di prod ber-NIK ibu atau
 * salah ketik, dan salah tautan di sini berarti data Kesmas menempel ke anak orang lain tanpa jejak.
 */
class PenautanAnakKesmas
{
    private const MIRIP = 87;

    private PenautanAnakImport $penautan;

    public function __construct(?PenautanAnakImport $penautan = null)
    {
        $this->penautan = $penautan ?? new PenautanAnakImport(new NikDummyService());
    }

    /** @return array{anak: ?Anak, pesan: list<array{0:string,1:string}>} */
    public function cari(string $nama, string $jk, string $tglLahir, string $namaOrtu, string $nik): array
    {
        $pesan = [];
        $nik = substr($nik, 0, 16);
        $nikSah = strlen($nik) >= 15 && ctype_digit($nik);

        if ($nikSah && ($lewatNik = Anak::where('nik', $nik)->first())) {
            if (substr((string) $lewatNik->tgl_lahir, 0, 10) === $tglLahir || self::mirip($nama, (string) $lewatNik->nama)) {
                return ['anak' => $lewatNik, 'pesan' => []];
            }
            $pesan[] = ['PERINGATAN', "NIK {$nik} tercatat untuk anak lain ({$lewatNik->nama}, lahir "
                .substr((string) $lewatNik->tgl_lahir, 0, 10).') — dicocokkan lewat nama.'];
        }

        $t = $this->penautan->tautkan($nama, $tglLahir, $jk, null, $nikSah ? $nik : null, $namaOrtu);

        if ($t['hasil'] === PenautanAnakImport::SAMA) {
            $pesan[] = ['INFO', "dicocokkan lewat nama + tgl lahir + jk dengan anak ber-NIK {$t['anak']->nik}"
                .($nikSah ? "; NIK berkas {$nik} tidak dipakai dan NIK di data tidak diubah." : '.')];

            return ['anak' => $t['anak'], 'pesan' => $pesan];
        }

        $pesan[] = ['ERROR', $t['hasil'] === PenautanAnakImport::AMBIGU
            ? "ditemukan {$t['jumlah']} anak yang cocok (nama + tgl lahir + jk) — isi nik_anak supaya pasti."
            : 'anak tidak ditemukan (nama + tgl lahir + jk + nama ortu) — tambahkan dulu lewat Tambah Anak atau Import Anak.'];

        return ['anak' => null, 'pesan' => $pesan];
    }

    private static function mirip(string $a, string $b): bool
    {
        similar_text(mb_strtolower(trim($a)), mb_strtolower(trim($b)), $pct);

        return $pct >= self::MIRIP;
    }
}

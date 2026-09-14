<?php

namespace App\Services;

use App\Models\Anak;
use App\Models\AnakMergeLog;
use App\Models\AnakTautan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Penggabungan dua baris anak yang dinyatakan satu orang (spec verifikasi RT §6.3).
 * Baris OT tidak pernah dihapus; semua data anak berpindah SEBELUM baris lain dihapus
 * (FK cascade); snapshot di anak_merge_log memungkinkan pembatalan.
 */
class IdentitasMergeService
{
    public const KOLOM_IDENTITAS = ['nik', 'no_kk', 'nama', 'nama_ibu', 'nama_ayah', 'jk', 'tempat_lahir', 'tgl_lahir', 'alamat_ktp'];
    public const KOLOM_DOMISILI  = ['alamat', 'id_kec', 'id_kel', 'id_rt', 'id_posyandu', 'id_puskesmas'];
    public const KOLOM_LAIN      = ['golda', 'anak', 'catatan'];
    public const KOLOM           = [...self::KOLOM_IDENTITAS, ...self::KOLOM_DOMISILI, ...self::KOLOM_LAIN];

    public function __construct(private readonly PrioritasGiziService $prioritas)
    {
    }

    /**
     * Baris mana yang dipertahankan + nilai default per kolom, tanpa mengubah apa pun.
     *
     * @return array{tautan:AnakTautan, a:Anak, b:Anak, dipertahankan:string, kunci_dipertahankan:bool, default:array<string,string>, boleh_pilih_baris:bool}
     */
    public function pratinjau(AnakTautan $t): array
    {
        if ($t->keputusan !== 'sama' || $t->status !== 'disetujui') {
            throw new InvalidArgumentException('Hanya tautan "sama" yang sudah disetujui yang bisa digabung.');
        }
        $a = Anak::find($t->id_anak_a);
        $b = Anak::find($t->id_anak_b);
        if (!$a || !$b) {
            throw new InvalidArgumentException('Salah satu baris anak sudah tidak ada.');
        }

        $aOt = $a->sumber === 'operasi_timbang';
        $bOt = $b->sumber === 'operasi_timbang';
        if ($aOt && $bOt) {
            throw new InvalidArgumentException('Kedua baris berasal dari Operasi Timbang — tidak digabung agar populasi OT tidak berubah.');
        }

        if ($aOt || $bOt) {
            $dipertahankan = $aOt ? 'a' : 'b';
            $kunci = true;
        } else {
            $dipertahankan = ($a->sumber === 'capil' && $b->sumber !== 'capil') ? 'b' : 'a';
            $kunci = false;
        }

        $capil = $a->sumber === 'capil' ? 'a' : ($b->sumber === 'capil' ? 'b' : null);
        $nonCapil = $capil === 'a' ? 'b' : ($capil === 'b' ? 'a' : null);

        $default = [];
        foreach (self::KOLOM as $k) {
            if (in_array($k, self::KOLOM_IDENTITAS, true) && $capil) {
                $default[$k] = $capil;
            } elseif (in_array($k, self::KOLOM_DOMISILI, true) && $nonCapil) {
                $default[$k] = $nonCapil;
            } else {
                $default[$k] = $dipertahankan;
            }
        }

        return [
            'tautan'              => $t,
            'a'                   => $a,
            'b'                   => $b,
            'dipertahankan'       => $dipertahankan,
            'kunci_dipertahankan' => $kunci,
            'default'             => $default,
            'boleh_pilih_baris'   => !$kunci,
        ];
    }
}

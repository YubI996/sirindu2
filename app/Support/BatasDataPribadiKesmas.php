<?php

namespace App\Support;

use App\Models\Puskesmas;
use App\Models\User;

/**
 * Siapa boleh melihat DATA PRIBADI anak (NIK, nama orang tua, penyakit penyerta, riwayat kunjungan)
 * lewat registri dasbor Kesmas dan Export Kesmas — dan sebatas wilayah mana. Angka agregat dasbor
 * tidak termasuk: itu tetap terbuka se-kota untuk semua pengguna yang boleh membuka dasbor.
 *
 * Keputusan pemilik produk (audit Kesmas 2026-10-06, SEC-001): akun `imunisasi_faskes` memantau
 * cakupan se-kota lewat angka, tetapi tidak perlu NIK dan nama orang tua anak di luar wilayahnya.
 *
 *  - Admin/superadmin/legacy : seluruh kota.
 *  - imunisasi_faskes puskesmas: hanya anak di kelurahan catchment puskesmas-nya (WilkerPuskesmas —
 *    definisi "wilayah puskesmas" yang sama dengan filter puskesmas di dasbor).
 *  - imunisasi_faskes RS, atau puskesmas tanpa id_puskesmas: DITOLAK. Mereka tak punya wilayah anak,
 *    dan menebak wilayah berarti membuka data se-kota diam-diam (gagal tertutup).
 */
final class BatasDataPribadiKesmas
{
    /**
     * @return array{boleh: bool, puskesmas: int|null} `puskesmas` terisi = batasi ke catchment-nya;
     *         null (dengan boleh = true) = tidak dibatasi.
     */
    public static function untuk(User $user): array
    {
        if (!$user->isFaskesImunisasi()) {
            return ['boleh' => true, 'puskesmas' => null];
        }

        if ($user->faskes_type === 'puskesmas' && $user->id_puskesmas) {
            return ['boleh' => true, 'puskesmas' => (int) $user->id_puskesmas];
        }

        return ['boleh' => false, 'puskesmas' => null];
    }

    /**
     * ID kelurahan catchment sebuah puskesmas. Kosong → [0] supaya `whereIn` tak menghasilkan apa pun
     * (puskesmas yang namanya tak dikenal tabel wilker tidak boleh berarti "tanpa batas").
     *
     * @return list<int>
     */
    public static function kelurahanCatchment(int $idPuskesmas): array
    {
        $nama = Puskesmas::whereKey($idPuskesmas)->value('name');
        $ids = $nama ? WilkerPuskesmas::catchmentKelurahanIds($nama) : [];

        return $ids ?: [0];
    }
}

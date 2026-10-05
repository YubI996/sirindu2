<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;

/**
 * Anak yang sudah KELUAR dan tak boleh dihitung di dasbor Kesmas: Tidak Aktif (anak.status = 0)
 * atau verifikasi RT "pindah"/"meninggal" yang sudah DISETUJUI reviu. Satu-satunya sumber aturan:
 * dipakai KesmasDashboardService (kartu, registri, banner) dan kesmas:tandai-sasaran, supaya
 * perintah penandaan dan dasbor tak pernah berbeda pendapat soal siapa yang "keluar".
 *
 * Usulan RT yang belum disetujui BUKAN keputusan — anak tetap terhitung sampai reviu menyetujui.
 * "Pindah" tak mencatat tujuan, jadi anak yang pindah ke RT lain di Bontang kembali terhitung begitu
 * RT barunya memverifikasi "berdomisili" (verifikasi terbaru yang berlaku, anak.verif_rt_*) dan
 * dasbor mengikuti alamatnya (id_kel/id_posyandu) — pastikan alamatnya diperbarui.
 *
 * Tanda anak.sasaran_balita_kesmas TIDAK disentuh: anak hanya dikecualikan saat menghitung, jadi
 * kembali terhitung bila statusnya berbalik. Semua SQL memakai COALESCE: `NOT (NULL OR …)` bernilai
 * NULL dan akan membuang anak yang tak pernah diverifikasi secara senyap.
 */
final class KeluarWilayah
{
    /** Verifikasi RT pindah/meninggal yang sudah disetujui. */
    public static function pindahMeninggalSql(string $alias = 'a'): string
    {
        return "(COALESCE({$alias}.verif_rt_status, '') IN ('pindah', 'meninggal') AND COALESCE({$alias}.verif_rt_reviu, '') = 'disetujui')";
    }

    /** Status Tidak Aktif. */
    public static function tidakAktifSql(string $alias = 'a'): string
    {
        return "({$alias}.status = 0)";
    }

    /** Salah satu dari keduanya. */
    public static function sql(string $alias = 'a'): string
    {
        return '(' . self::tidakAktifSql($alias) . ' OR ' . self::pindahMeninggalSql($alias) . ')';
    }

    /** Buang anak yang sudah keluar dari kueri (alias tabel anak = $alias). */
    public static function kecualikan(Builder $q, string $alias = 'a'): Builder
    {
        return $q->whereRaw('NOT ' . self::sql($alias));
    }
}

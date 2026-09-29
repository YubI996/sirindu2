<?php

namespace App\Traits;

use App\Models\SpmCapaian;
use Illuminate\Http\Request;

/**
 * Pemilihan tahun untuk modul SPM — dipakai bersama oleh master data dan dasbor.
 *
 * Satu salinan saja: dua salinan aturan "tahun ngawur jatuh ke tahun ini" pasti
 * berbeda suatu saat, dan halaman yang satu akan 500 sementara yang lain tidak.
 */
trait MemakaiTahunSpm
{
    /**
     * Tahun dari request, dijatuhkan ke tahun ini bila bukan angka atau di luar
     * rentang — halaman SPM tidak boleh 500 karena query string iseng.
     */
    protected function tahunTervalidasi(Request $request): int
    {
        $tahun = $request->input('tahun');
        $min   = (int) config('spm.tahun_min');
        $max   = (int) now()->year + 1;

        if (!is_numeric($tahun)) {
            return (int) now()->year;
        }

        $tahun = (int) $tahun;

        return ($tahun < $min || $tahun > $max) ? (int) now()->year : $tahun;
    }

    /**
     * Rentang penuh tahun_min..tahun ini+1, terbaru dulu — untuk master data
     * (spec §4). Harus rentang, bukan "tahun yang punya data": sasaran tahun
     * depan hanya bisa dibuat dari halaman yang tahunnya sudah terpilih, jadi
     * daftar berbasis data membuat tahun baru mustahil dijangkau dari UI.
     */
    protected function tahunOpsiPenuh(): array
    {
        $tahun = range((int) config('spm.tahun_min'), (int) now()->year + 1);
        rsort($tahun);

        return $tahun;
    }

    /** Tahun yang punya data + tahun ini, terbaru dulu — untuk dasbor (spec §5). */
    protected function tahunOpsi(): array
    {
        $tahun = SpmCapaian::query()->distinct()->pluck('tahun')->map(fn ($t) => (int) $t)->all();
        $tahun[] = (int) now()->year;

        $tahun = array_values(array_unique($tahun));
        rsort($tahun);

        return $tahun;
    }
}

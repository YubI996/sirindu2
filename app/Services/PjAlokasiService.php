<?php

namespace App\Services;

use App\Http\Controllers\TimbangDashboardController;
use App\Models\Kelurahan;
use Illuminate\Support\Facades\DB;

/**
 * Pasangkan daftar nama PJ ke anak sasaran stunting/wasting/underweight
 * di kelurahan sasaran saja (config pj.kelurahan_sasaran = Bontang Lestari).
 * Sasaran mengikuti kunjungan OT terakhir (OtGiziService); dibagi rata bergilir
 * sesuai urutan CSV, tanpa pengelompokan posyandu. Anak di kelurahan lain tidak
 * disentuh sama sekali — PJ lama mereka (bila ada) dibiarkan, juga saat $timpa.
 * Anak yang sudah punya PJ dilewati kecuali $timpa. Hasil tetap bisa diubah di modal OT.
 */
class PjAlokasiService
{
    public function __construct(private readonly OtGiziService $otGizi)
    {
    }

    /**
     * @param  array<int, array{nama_pj:string, baris:int}> $baris
     * @return array{dialokasikan:int, dilewati:int, pj:int, anak:int, gagal:string[], kelurahan:string}
     *
     * @throws \RuntimeException bila kelurahan sasaran tidak ada di master wilayah —
     *                           lebih baik import gagal terang-terangan daripada "0 anak dialokasikan".
     */
    public function alokasikan(array $baris, bool $timpa, int $userId): array
    {
        $kelurahan = $this->kelurahanSasaran();

        // Identitas PJ = nama (tanpa peduli huruf besar/kecil); ejaan pertama yang dipakai.
        $daftarPj = [];
        $gagal = [];
        foreach ($baris as $b) {
            $namaPj = trim((string) ($b['nama_pj'] ?? ''));
            if ($namaPj === '' || mb_strlen($namaPj) > 100) {
                $gagal[] = "Baris {$b['baris']}: nama PJ wajib diisi (maksimal 100 karakter).";
                continue;
            }
            $daftarPj[mb_strtolower($namaPj)] ??= ['nama' => $namaPj];
        }

        $pj = array_values($daftarPj);
        $ringkasan = ['dialokasikan' => 0, 'dilewati' => 0, 'pj' => count($pj), 'anak' => 0, 'gagal' => $gagal, 'kelurahan' => self::namaKelurahanSasaran()];
        if ($pj === []) {
            return $ringkasan;
        }

        return DB::transaction(function () use ($pj, $timpa, $userId, $ringkasan, $kelurahan) {
            $filter = $this->otGizi->filterKosong(['kel' => $kelurahan->id]);
            $ids = $this->otGizi->idAnakMasalahGizi($filter, TimbangDashboardController::KATEGORI_PJ);
            sort($ids);

            $sudah = $timpa || empty($ids) ? [] : DB::table('anak')->whereIn('id', $ids)
                ->whereNotNull('pj_nama')->where('pj_nama', '!=', '')->pluck('id')->map(fn ($i) => (int) $i)->all();
            $sasaran = array_values(array_diff($ids, $sudah));

            foreach ($sasaran as $i => $idAnak) {
                $penanggungJawab = $pj[$i % count($pj)];
                DB::table('anak')->where('id', $idAnak)->update([
                    'pj_nama'       => $penanggungJawab['nama'],
                    'pj_updated_by' => $userId,
                    'pj_updated_at' => now(),
                ]);
            }

            $ringkasan['anak'] = count($ids);
            $ringkasan['dialokasikan'] = count($sasaran);
            $ringkasan['dilewati'] = count($sudah);
            return $ringkasan;
        });
    }

    /** Nama kelurahan sasaran (ejaan config) — dipakai di ringkasan & teks UI. */
    private static function namaKelurahanSasaran(): string
    {
        return trim((string) config('pj.kelurahan_sasaran'));
    }

    /** Baris kelurahan sasaran di master wilayah, dicari tanpa peduli huruf besar/kecil. */
    private function kelurahanSasaran(): Kelurahan
    {
        $nama = self::namaKelurahanSasaran();
        $kelurahan = Kelurahan::whereRaw('LOWER(name) = ?', [mb_strtolower($nama)])->first();
        if (!$kelurahan) {
            throw new \RuntimeException("Kelurahan sasaran PJ \"{$nama}\" tidak ditemukan di master wilayah; alokasi PJ dibatalkan.");
        }

        return $kelurahan;
    }
}

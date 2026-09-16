<?php

namespace App\Services;

use App\Http\Controllers\TimbangDashboardController;
use App\Models\Kelurahan;
use Illuminate\Support\Facades\DB;

/**
 * Pasangkan daftar NIP/nama PJ ke anak sasaran stunting/wasting/underweight
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
     * @param  array<int, array{nip_pj:string, nama_pj:string, baris:int}> $baris
     * @return array{dialokasikan:int, dilewati:int, pj:int, anak:int, gagal:string[], kelurahan:string}
     *
     * @throws \RuntimeException bila kelurahan sasaran tidak ada di master wilayah —
     *                           lebih baik import gagal terang-terangan daripada "0 anak dialokasikan".
     */
    public function alokasikan(array $baris, bool $timpa, int $userId): array
    {
        $kelurahan = $this->kelurahanSasaran();

        // Identitas PJ berdasarkan NIP; nama sama dengan NIP berbeda tetap dua orang.
        $daftarPj = [];
        $gagal = [];
        foreach ($baris as $b) {
            $namaPj = trim((string) ($b['nama_pj'] ?? ''));
            $nipPj = trim((string) ($b['nip_pj'] ?? ''));
            if (!preg_match('/^[0-9]{18}$/', $nipPj) || $namaPj === '' || mb_strlen($namaPj) > 100) {
                $gagal[] = "Baris {$b['baris']}: NIP harus 18 digit dan nama PJ wajib diisi (maksimal 100 karakter).";
                continue;
            }
            if (isset($daftarPj[$nipPj])) {
                if (mb_strtolower($daftarPj[$nipPj]['nama']) !== mb_strtolower($namaPj)) {
                    $gagal[] = "Baris {$b['baris']}: NIP yang sama memiliki nama PJ berbeda dalam daftar ini.";
                }
                continue;
            }
            $daftarPj[$nipPj] = ['nip' => $nipPj, 'nama' => $namaPj];
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
                    'pj_nip'        => $penanggungJawab['nip'],
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

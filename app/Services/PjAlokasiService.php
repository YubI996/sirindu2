<?php

namespace App\Services;

use App\Http\Controllers\TimbangDashboardController;
use Illuminate\Support\Facades\DB;

/**
 * Pasangkan daftar NIP/nama PJ ke seluruh anak sasaran stunting/wasting/underweight.
 * Sasaran mengikuti kunjungan OT terakhir (OtGiziService); dibagi rata bergilir
 * sesuai urutan CSV, tanpa pengelompokan kelurahan atau posyandu.
 * Anak yang sudah punya PJ dilewati kecuali $timpa. Hasil tetap bisa diubah di modal OT.
 */
class PjAlokasiService
{
    public function __construct(private readonly OtGiziService $otGizi)
    {
    }

    /**
     * @param  array<int, array{nip_pj:string, nama_pj:string, baris:int}> $baris
     * @return array{dialokasikan:int, dilewati:int, pj:int, anak:int, gagal:string[]}
     */
    public function alokasikan(array $baris, bool $timpa, int $userId): array
    {
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
        $ringkasan = ['dialokasikan' => 0, 'dilewati' => 0, 'pj' => count($pj), 'anak' => 0, 'gagal' => $gagal];
        if ($pj === []) {
            return $ringkasan;
        }

        return DB::transaction(function () use ($pj, $timpa, $userId, $ringkasan) {
            $ids = $this->otGizi->idAnakMasalahGizi($this->otGizi->filterKosong(), TimbangDashboardController::KATEGORI_PJ);
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
}

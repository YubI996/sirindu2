<?php

namespace App\Services;

use App\Http\Controllers\TimbangDashboardController;
use App\Models\Kelurahan;
use Illuminate\Support\Facades\DB;

/**
 * Alokasi otomatis nama Penanggung Jawab (PJ) dari CSV `kelurahan, posyandu, nama_pj`
 * (permintaan klien 15 Sep 2026). Per wilayah (kelurahan, atau kelurahan+posyandu),
 * anak yang saat ini stunting/wasting/underweight — aturan yang sama dengan modal dasbor OT
 * (OtGiziService) — dibagi rata bergilir ke PJ yang terdaftar di wilayah itu, urut CSV.
 * Anak yang sudah punya PJ dilewati kecuali $timpa. Hasil tetap bisa diubah di modal OT.
 */
class PjAlokasiService
{
    public function __construct(
        private readonly OtGiziService $otGizi,
        private readonly FaskesMatcher $matcher,
    ) {
    }

    /**
     * @param  array<int, array{kelurahan:string, posyandu:?string, nama_pj:string, baris:int}> $baris
     * @return array{dialokasikan:int, dilewati:int, wilayah:array<int, array{label:string, pj:int, anak:int, dialokasikan:int, dilewati:int}>, gagal:string[]}
     */
    public function alokasikan(array $baris, bool $timpa, int $userId): array
    {
        $kelurahan = [];
        foreach (Kelurahan::select('id', 'name')->get() as $k) {
            $kelurahan[FaskesMatcher::normalisasi($k->name)] = ['id' => (int) $k->id, 'nama' => $k->name];
        }

        // Kelompokkan PJ per wilayah, urutan CSV dipertahankan; duplikat nama dalam satu wilayah dibuang.
        $kelompok = [];
        $gagal = [];
        foreach ($baris as $b) {
            $namaPj = trim((string) ($b['nama_pj'] ?? ''));
            $kel = $kelurahan[FaskesMatcher::normalisasi((string) ($b['kelurahan'] ?? ''))] ?? null;
            if (!$kel) {
                $gagal[] = "Baris {$b['baris']}: kelurahan \"{$b['kelurahan']}\" tidak dikenal.";
                continue;
            }
            $posId = null;
            $label = 'Kel. '.$kel['nama'];
            $posNama = trim((string) ($b['posyandu'] ?? ''));
            if ($posNama !== '') {
                $cocok = $this->matcher->cocokkan($posNama);
                if (!$cocok['id']) {
                    $gagal[] = "Baris {$b['baris']}: posyandu \"{$posNama}\" tidak dikenal ({$cocok['alasan']}).";
                    continue;
                }
                $posId = (int) $cocok['id'];
                $label .= ' / Posyandu '.$posNama;
            }
            if ($namaPj === '') {
                $gagal[] = "Baris {$b['baris']}: nama PJ kosong.";
                continue;
            }
            $key = $kel['id'].'|'.($posId ?? '');
            $kelompok[$key] ??= ['kel' => $kel['id'], 'pos' => $posId, 'label' => $label, 'pj' => []];
            if (!in_array(mb_strtolower($namaPj), array_map('mb_strtolower', $kelompok[$key]['pj']), true)) {
                $kelompok[$key]['pj'][] = $namaPj;
            }
        }

        $ringkasan = ['dialokasikan' => 0, 'dilewati' => 0, 'wilayah' => [], 'gagal' => $gagal];
        $kategori  = TimbangDashboardController::KATEGORI_PJ;

        DB::transaction(function () use ($kelompok, $timpa, $userId, $kategori, &$ringkasan) {
            foreach ($kelompok as $g) {
                $f   = $this->otGizi->filterKosong(['kel' => $g['kel'], 'posyandu' => $g['pos']]);
                $ids = $this->otGizi->idAnakMasalahGizi($f, $kategori);
                sort($ids);

                $sudah = $timpa || empty($ids) ? [] : DB::table('anak')->whereIn('id', $ids)
                    ->whereNotNull('pj_nama')->where('pj_nama', '!=', '')->pluck('id')->map(fn ($i) => (int) $i)->all();
                $sasaran = array_values(array_diff($ids, $sudah));

                $n = count($g['pj']);
                foreach ($sasaran as $i => $idAnak) {
                    DB::table('anak')->where('id', $idAnak)->update([
                        'pj_nama'       => $g['pj'][$i % $n],
                        'pj_updated_by' => $userId,
                        'pj_updated_at' => now(),
                    ]);
                }

                $ringkasan['wilayah'][] = [
                    'label'        => $g['label'],
                    'pj'           => $n,
                    'anak'         => count($ids),
                    'dialokasikan' => count($sasaran),
                    'dilewati'     => count($sudah),
                ];
                $ringkasan['dialokasikan'] += count($sasaran);
                $ringkasan['dilewati']     += count($sudah);
            }
        });

        return $ringkasan;
    }
}

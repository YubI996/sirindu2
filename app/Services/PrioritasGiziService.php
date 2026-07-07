<?php

namespace App\Services;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\PrioritasGizi;

/**
 * Menghitung & menyimpan snapshot prioritas gizi per anak.
 *
 * Klasifikasi status memakai StatusGiziService (satu sumber kebenaran).
 * bb_tidak_naik mengikuti logika TimbangDashboardController::bbTidakNaikIds
 * (BB kunjungan terakhir <= sebelumnya bila ada >=2 kunjungan; selain itu ntob='T').
 */
class PrioritasGiziService
{
    /** Saat true, observer melewati refresh (dipakai selama import massal). */
    public static bool $muted = false;

    public function __construct(private StatusGiziService $statusGizi) {}

    /**
     * @return array{gizi_buruk:bool,gizi_kurang:bool,stunting:bool,bb_tidak_naik:bool,prioritas:?int,usia_bln:?int}
     */
    public function hitungUntukAnak(Anak $anak): array
    {
        $giziBuruk = false;
        $giziKurang = false;
        $stunting = false;
        $usiaBln = null;

        // Kunjungan terakhir yang valid untuk klasifikasi status (<=60 bln, bb/tb > 0).
        $latest = DataAnak::where('id_anak', $anak->id)
            ->whereNotNull('tgl_kunjungan')
            ->where('bln', '<=', 60)
            ->where('bb', '>', 0)
            ->where('tb', '>', 0)
            ->orderByDesc('tgl_kunjungan')
            ->orderByDesc('id')
            ->first();

        if ($latest) {
            $usiaBln = (int) $latest->bln;
            $g = $this->statusGizi->klasifikasi($latest->bb, $latest->tb, $latest->bln, $latest->posisi, $anak->jk);
            $giziBuruk = $g['enum']['bb_tb'] === 'severely_wasted';
            $giziKurang = $g['enum']['bb_tb'] === 'wasted';
            $stunting = in_array($g['enum']['tb_u'], ['stunted', 'severely_stunted'], true);
        }

        $bbTidakNaik = $this->bbTidakNaik($anak->id);

        $prioritas = $giziBuruk ? 1 : ($stunting ? 2 : ($bbTidakNaik ? 3 : null));

        return [
            'gizi_buruk' => $giziBuruk,
            'gizi_kurang' => $giziKurang,
            'stunting' => $stunting,
            'bb_tidak_naik' => $bbTidakNaik,
            'prioritas' => $prioritas,
            'usia_bln' => $usiaBln,
        ];
    }

    /** BB tidak naik: 2 kunjungan terakhir turun/tetap, atau ntob='T' bila hanya 1 kunjungan. */
    private function bbTidakNaik(int $idAnak): bool
    {
        $visits = DataAnak::where('id_anak', $idAnak)
            ->whereNotNull('tgl_kunjungan')
            ->where('bb', '>', 0)
            ->orderByDesc('tgl_kunjungan')
            ->orderByDesc('id')
            ->get(['bb', 'ntob']);

        if ($visits->count() >= 2) {
            return (float) $visits[0]->bb <= (float) $visits[1]->bb;
        }
        if ($visits->count() === 1) {
            return strtoupper(trim((string) $visits[0]->ntob)) === 'T';
        }
        return false;
    }

    public function refreshAnak(int $idAnak): void
    {
        $anak = Anak::find($idAnak);
        if (!$anak) {
            PrioritasGizi::where('id_anak', $idAnak)->delete();
            return;
        }

        $hasil = $this->hitungUntukAnak($anak);

        PrioritasGizi::updateOrCreate(
            ['id_anak' => $anak->id],
            $hasil + [
                'id_kec' => $anak->id_kec,
                'id_kel' => $anak->id_kel,
                'id_rt' => $anak->id_rt,
                'id_posyandu' => $anak->id_posyandu,
                'refreshed_at' => now(),
            ]
        );
    }

    /** @param array<int> $idAnak */
    public function refreshBatch(array $idAnak): void
    {
        foreach (array_unique($idAnak) as $id) {
            $this->refreshAnak((int) $id);
        }
    }

    public function refreshAll(): int
    {
        $ditulis = 0;
        Anak::query()->select('id')->chunkById(500, function ($anaks) use (&$ditulis) {
            foreach ($anaks as $a) {
                $this->refreshAnak($a->id);
                $ditulis++;
            }
        });
        return $ditulis;
    }
}

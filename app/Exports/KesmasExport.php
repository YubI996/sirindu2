<?php

namespace App\Exports;

use App\Models\Kelurahan;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Export Kesmas — dua sheet: "Per Anak" (identitas + Kesmas + riwayat lahir) dan
 * "Per Kunjungan" (layanan per baris data_anak). Bahan dasbor Kesmas (spec §5).
 *
 * @phpstan-type Filter array{id_kec?:mixed,id_kel?:mixed,id_puskesmas?:mixed,id_posyandu?:mixed,dari?:mixed,sampai?:mixed}
 */
final class KesmasExport implements WithMultipleSheets
{
    use Exportable;

    /** @param Filter $filter */
    public function __construct(private array $filter) {}

    public function sheets(): array
    {
        return [new KesmasAnakSheet($this->filter), new KesmasKunjunganSheet($this->filter)];
    }

    public function filename(): string
    {
        $kel = !empty($this->filter['id_kel']) ? Kelurahan::find($this->filter['id_kel'])?->name : null;

        return 'kesmas-' . ($kel ? Str::slug($kel) : 'semua') . '-' . now()->format('Ymd') . '.xlsx';
    }
}

<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\Exportable;

class VaccineNeedsExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    use Exportable;

    protected $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function collection()
    {
        return new Collection($this->rows);
    }

    public function headings(): array
    {
        return [
            'Nama Anak',
            'Usia (bulan)',
            'Jenis Kelamin',
            'Posyandu',
            'Kelurahan',
            'Kecamatan',
            'Vaksin Belum Lengkap',
            'Jumlah Vaksin',
        ];
    }
}

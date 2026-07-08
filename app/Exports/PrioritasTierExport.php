<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PrioritasTierExport implements FromArray, WithHeadings
{
    /** @param array<int,array<string,mixed>> $rows */
    public function __construct(private array $rows, private string $judul) {}

    public function array(): array
    {
        return array_map(fn ($r) => [
            $r['nama'], $r['nik'], $r['usia_bln'] ?? '-',
            $r['posyandu'], $r['puskesmas'], $r['kelurahan'], $r['rt'],
        ], $this->rows);
    }

    public function headings(): array
    {
        return ['Nama', 'NIK', 'Usia (bln)', 'Posyandu', 'Puskesmas', 'Kelurahan', 'RT'];
    }
}

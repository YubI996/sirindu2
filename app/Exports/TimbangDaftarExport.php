<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Export daftar anak yang perlu ditindak (per kategori kartu dashboard timbang).
 * Menerima array baris yang sudah dibangun controller (nama+alamat domisili+wilayah+indikator).
 */
class TimbangDaftarExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize
{
    public function __construct(
        protected array $rows,
        protected string $kategori = 'Daftar'
    ) {
    }

    public function array(): array
    {
        $no = 0;
        return array_map(function ($r) use (&$no) {
            $no++;
            return [
                $no,
                $r['nama'] ?? '-',
                $r['nik'] ?? '-',
                $r['kecamatan'] ?? '-',
                $r['kelurahan'] ?? '-',
                $r['rt'] ?? '-',
                $r['posyandu'] ?? '-',
                $r['alamat'] ?? '-',
                $r['indikator'] ?? '-',
                $r['tgl_kunjungan'] ?? '-',
            ];
        }, $this->rows);
    }

    public function headings(): array
    {
        return [
            'No', 'Nama', 'NIK', 'Kecamatan', 'Kelurahan', 'RT', 'Posyandu',
            'Alamat Domisili', 'Indikator', 'Tgl Kunjungan Terakhir',
        ];
    }

    public function title(): string
    {
        return substr($this->kategori, 0, 31);
    }
}

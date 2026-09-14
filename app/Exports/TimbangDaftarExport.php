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
        protected string $kategori = 'Daftar',
        protected bool $denganPj = false
    ) {
    }

    public function array(): array
    {
        $no = 0;
        return array_map(function ($r) use (&$no) {
            $no++;
            $baris = [
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
            if ($this->denganPj) {
                $baris[] = $r['pj_nama'] ?? '-';
            }
            return $baris;
        }, $this->rows);
    }

    public function headings(): array
    {
        $h = [
            'No', 'Nama', 'NIK', 'Kecamatan', 'Kelurahan', 'RT', 'Posyandu',
            'Alamat Domisili', 'Indikator', 'Tgl Kunjungan Terakhir',
        ];
        if ($this->denganPj) {
            $h[] = 'Penanggung Jawab';
        }
        return $h;
    }

    public function title(): string
    {
        return substr($this->kategori, 0, 31);
    }
}

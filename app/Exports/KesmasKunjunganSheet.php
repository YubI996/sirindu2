<?php

namespace App\Exports;

use App\Models\DataAnak;
use App\Services\KesmasPresenter as K;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/** Sheet "Per Kunjungan" — satu baris per data_anak (filter wilayah anak + rentang tgl_kunjungan). */
final class KesmasKunjunganSheet extends DefaultValueBinder implements FromQuery, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithCustomValueBinder
{
    public function __construct(private array $filter) {}

    public function query()
    {
        // join, bukan whereHas('anak'): DataAnak::anak() memakai FK bawaan `anak_id` yang tidak ada.
        $q = DataAnak::query()
            ->join('anak', 'anak.id', '=', 'data_anak.id_anak')
            ->select('data_anak.*', 'anak.nik', 'anak.nama')
            ->orderBy('anak.nama')
            ->orderBy('data_anak.tgl_kunjungan');

        KesmasAnakSheet::terapkanWilayah($q, $this->filter, 'anak.');

        if (!empty($this->filter['dari'])) {
            $q->whereDate('data_anak.tgl_kunjungan', '>=', $this->filter['dari']);
        }
        if (!empty($this->filter['sampai'])) {
            $q->whereDate('data_anak.tgl_kunjungan', '<=', $this->filter['sampai']);
        }

        return $q;
    }

    public function title(): string
    {
        return 'Per Kunjungan';
    }

    public function headings(): array
    {
        $h = ['NIK', 'Nama', 'Tgl Kunjungan', 'Usia (bln)', 'BB (kg)', 'TB (cm)', 'Tgl Penanda CKG'];
        foreach (config('kesmas.layanan') as $def) {
            $h[] = $def['kolom'];
        }

        return array_merge($h, [
            'Pemeriksaan Gigi', 'Rujukan', 'MT Pangan Lokal', 'Catatan', 'Pemeriksaan Lainnya', 'Pola Makan', 'Pola Asuh', 'Intervensi',
        ]);
    }

    /** @param DataAnak $d (dengan kolom join nik, nama) */
    public function map($d): array
    {
        $r = [$d->nik, $d->nama, $d->tgl_kunjungan, $d->bln, $d->bb, $d->tb, $d->tgl_penanda_ckg];
        foreach (array_keys(config('kesmas.layanan')) as $k) {
            $r[] = K::yaTidak($d->$k);
        }

        return array_merge($r, [
            $d->pemeriksaan_gigi, $d->rujukan, $d->mt_pangan_lokal, $d->catatan_pengukuran,
            $d->pemeriksaan_lainnya, $d->pola_makan, $d->pola_asuh, $d->intervensi,
        ]);
    }

    /** NIK 16 digit harus tetap teks. */
    public function bindValue(Cell $cell, $value)
    {
        if ($cell->getColumn() === 'A') {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}

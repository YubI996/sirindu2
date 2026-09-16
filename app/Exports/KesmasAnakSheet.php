<?php

namespace App\Exports;

use App\Models\Anak;
use App\Services\KesmasPresenter as K;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/** Sheet "Per Anak" — satu baris per anak yang lolos filter wilayah (spec §5). */
final class KesmasAnakSheet extends DefaultValueBinder implements FromQuery, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithCustomValueBinder
{
    public function __construct(private array $filter) {}

    /** Filter wilayah dipakai kedua sheet; $prefix 'anak.' bila query-nya join. */
    public static function terapkanWilayah(Builder $q, array $filter, string $prefix = ''): Builder
    {
        foreach (['id_kec', 'id_kel', 'id_puskesmas', 'id_posyandu'] as $k) {
            if (!empty($filter[$k])) {
                $q->where($prefix . $k, $filter[$k]);
            }
        }

        return $q;
    }

    public function query()
    {
        return self::terapkanWilayah(
            Anak::query()->with(['kec', 'kel', 'rt', 'puskesmas', 'posyandu'])->orderBy('nama'),
            $this->filter
        );
    }

    public function title(): string
    {
        return 'Per Anak';
    }

    public function headings(): array
    {
        return [
            'NIK', 'Nama', 'JK', 'Tgl Lahir', 'Kecamatan', 'Kelurahan', 'RT', 'Puskesmas', 'Posyandu',
            'No ID ePus', 'FKTP BPJS', 'Air Bersih', 'Jamban Sehat', 'Perokok Serumah', 'TK/PAUD', 'Penyakit Penyerta', 'PJB',
            'BBL (kg)', 'PBL (cm)', 'LK Lahir (cm)', 'Usia Kehamilan (mgg)', 'Tempat Bersalin', 'Jenis Persalinan', 'Penolong',
            'IMD', 'KEK Ibu', 'SHK', 'SHAK', 'G6PD', 'Hepatitis B', 'Komplikasi Persalinan', 'Komplikasi Neonatal',
        ];
    }

    /** @param Anak $a */
    public function map($a): array
    {
        return [
            $a->nik, $a->nama, $a->jk == 1 ? 'L' : 'P', $a->tgl_lahir,
            $a->kec->name ?? '', $a->kel->name ?? '', $a->rt->name ?? '', $a->puskesmas->name ?? '', $a->posyandu->name ?? '',
            $a->no_id_epus, $a->fktp_bpjs, K::yaTidak($a->air_bersih), K::yaTidak($a->jamban_sehat), K::yaTidak($a->merokok_keluarga),
            $a->status_tk_paud, $a->penyakit_penyerta, $a->pjb,
            $a->bbl, $a->pbl, $a->lk_lahir, $a->usia_kehamilan_lahir, $a->tempat_bersalin, $a->jenis_persalinan, $a->penolong_lahir,
            K::yaTidak($a->imd), K::yaTidak($a->riwayat_kek_ibu),
            K::enumLabel('skrining', $a->skrining_shk), K::enumLabel('skrining', $a->skrining_shak),
            K::enumLabel('skrining', $a->skrining_g6pd), K::enumLabel('hepatitis_b', $a->pemeriksaan_hepatitis_b),
            $a->komplikasi_persalinan, $a->komplikasi_neonatal,
        ];
    }

    /** NIK 16 digit harus tetap teks — Excel memotong presisi angka >15 digit. */
    public function bindValue(Cell $cell, $value)
    {
        if ($cell->getColumn() === 'A') {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}

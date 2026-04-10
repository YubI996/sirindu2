<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Support\Facades\DB;


class AnakExport implements FromQuery, WithMapping, WithHeadings, ShouldAutoSize
{
    use Exportable;

    protected $req;

    function __construct($req)
    {
        $this->req = $req;
    }

    public function query()
    {
        $data = DB::table('alldata')->orderBy('id');

        if ($this->req->from_date != '' && $this->req->to_date != '') {
            $data->whereBetween('tgl_kunjungan', [$this->req->from_date, $this->req->to_date]);
        }

        if ($this->req->id_kec !== "0" && $this->req->id_kec !== null && $this->req->id_kec !== '') {
            $data->where('idKec', $this->req->id_kec);

            if ($this->req->id_puskesmas !== "0" && $this->req->id_puskesmas !== null && $this->req->id_puskesmas !== '') {
                $data->where('idPuskes', $this->req->id_puskesmas);

                if ($this->req->id_posyandu !== "0" && $this->req->id_posyandu !== null && $this->req->id_posyandu !== '') {
                    $data->where('idPos', $this->req->id_posyandu);
                }
            } elseif ($this->req->id_kelurahan !== "0" && $this->req->id_kelurahan !== null && $this->req->id_kelurahan !== '') {
                $data->where('idKel', $this->req->id_kelurahan);

                if ($this->req->id_rt !== "0" && $this->req->id_rt !== null && $this->req->id_rt !== '') {
                    $data->where('idRt', $this->req->id_rt);
                }
            }
        }

        return $data;
    }

    public function headings(): array
    {
        return [
            'No KK',
            'NIK',
            'Nama',
            'NIK Orang Tua',
            'Nama Ibu',
            'Nama Ayah',
            'Jenis Kelamin',
            'Tempat Lahir',
            'Tanggal Lahir',
            'Golongan Darah',
            'Anak Ke-',
            'Catatan',
            'Kecamatan',
            'Kelurahan',
            'Puskesmas',
            'Posyandu',
            'RT',
            'Tanggal Kunjungan',
            'Bulan',
            'Posisi',
            'Tinggi Badan (cm)',
            'Berat Badan (kg)',
            'BMI',
            'Lingkar Lengan Atas',
            'Lingkar Kepala',
            'NTOB',
            'ASI',
            'Vitamin A',
            'Nama Petugas',
        ];
    }

    public function map($data): array
    {
        return [
            $data->no_kk,
            $data->nik,
            $data->nama,
            $data->nik_ortu,
            $data->nama_ibu,
            $data->nama_ayah,
            $data->jk,
            $data->tempat_lahir,
            $data->tgl_lahir,
            $data->golda,
            $data->anak,
            $data->catatan,
            $data->nameKec,
            $data->nameKel,
            $data->namePuskes,
            $data->namePos,
            $data->nameRt,
            $data->tgl_kunjungan,
            $data->bln,
            $data->posisi,
            $data->tb,
            $data->bb,
            $data->tb > 0 ? round(10000 * $data->bb / pow($data->tb, 2), 2) : null,
            $data->lla,
            $data->lk,
            $data->ntob,
            $data->asi,
            $data->vit_a,
            $data->namaPetugas,
        ];
    }
}

<?php

namespace App\Exports;

use App\Models\Imunisasi;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

class ImunisasiExport implements FromQuery, WithMapping, WithHeadings, ShouldAutoSize, WithCustomCsvSettings
{
    use Exportable;

    protected $bulan;
    protected $kelurahan;
    protected $antigen;
    protected $status;

    public function __construct($bulan = null, $kelurahan = null, $antigen = null, $status = null)
    {
        $this->bulan = $bulan;
        $this->kelurahan = $kelurahan;
        $this->antigen = $antigen;
        $this->status = $status;
    }

    public function query()
    {
        $query = Imunisasi::query()
            ->with(['anak.kel', 'anak.kec', 'anak.posyandu', 'jenisVaksin']);

        if ($this->bulan) {
            $parts = explode('-', $this->bulan);
            if (count($parts) === 2) {
                $query->whereYear('tanggal_pemberian', $parts[0])
                      ->whereMonth('tanggal_pemberian', $parts[1]);
            }
        }

        if ($this->kelurahan) {
            $query->whereHas('anak', function ($q) {
                $q->where('id_kel', $this->kelurahan);
            });
        }

        if ($this->antigen) {
            $query->where('id_jenis_vaksin', $this->antigen);
        }

        if ($this->status) {
            $query->where('status', $this->status);
        }

        return $query->orderBy('tanggal_pemberian', 'desc');
    }

    public function headings(): array
    {
        return [
            'Nama Anak',
            'NIK',
            'Jenis Kelamin',
            'Tanggal Lahir',
            'Kelurahan',
            'Kecamatan',
            'Posyandu',
            'Jenis Vaksin',
            'Dosis',
            'Tanggal Pemberian',
            'Status',
            'Lokasi Pemberian',
        ];
    }

    public function map($imunisasi): array
    {
        return [
            $imunisasi->anak?->nama ?? '-',
            $imunisasi->anak?->nik ?? '-',
            ($imunisasi->anak?->jk) == 1 ? 'Laki-laki' : 'Perempuan',
            $imunisasi->anak?->tgl_lahir ? Carbon::parse($imunisasi->anak->tgl_lahir)->format('d/m/Y') : '-',
            $imunisasi->anak?->kel?->name ?? '-',
            $imunisasi->anak?->kec?->name ?? '-',
            $imunisasi->anak?->posyandu?->name ?? '-',
            $imunisasi->jenisVaksin?->nama ?? '-',
            $imunisasi->dosis ?? 1,
            $imunisasi->tanggal_pemberian ? $imunisasi->tanggal_pemberian->format('d/m/Y') : '-',
            $imunisasi->status ?? '-',
            $imunisasi->lokasi_pemberian ?? '-',
        ];
    }

    public function getCsvSettings(): array
    {
        return [
            'use_bom' => true,
        ];
    }

    public function filename(): string
    {
        $parts = ['imunisasi'];

        if ($this->bulan) {
            $date = Carbon::createFromFormat('Y-m', $this->bulan);
            $parts[] = Str::lower($date->translatedFormat('M-Y'));
        }

        if ($this->kelurahan) {
            $kel = \App\Models\Kelurahan::find($this->kelurahan);
            if ($kel) {
                $parts[] = Str::slug($kel->name);
            }
        }

        if ($this->antigen) {
            $vaksin = \App\Models\JenisVaksin::find($this->antigen);
            if ($vaksin) {
                $parts[] = Str::slug($vaksin->nama);
            }
        }

        if ($this->status) {
            $parts[] = $this->status;
        }

        return implode('_', $parts) . '.csv';
    }
}

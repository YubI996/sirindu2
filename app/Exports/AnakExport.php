<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\DefaultValueBinder;
use App\Models\VerifikasiAnak;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

/**
 * Export Data Anak — satu baris per KUNJUNGAN (view `alldata` = data_anak INNER JOIN anak).
 *
 * Opsi `sertakan_tanpa_kunjungan`: tambahkan juga anak yang belum punya kunjungan (mis. hasil AnakImport,
 * yang hanya membuat identitas), satu baris per anak dengan kolom pengukuran kosong. Tanpa opsi ini mereka
 * tak pernah muncul di export, sehingga jumlah baris tak cocok dengan berkas import.
 */
class AnakExport extends DefaultValueBinder implements FromQuery, WithMapping, WithHeadings, ShouldAutoSize, WithCustomValueBinder
{
    use Exportable;

    /**
     * Kolom identitas panjang: No KK (A), NIK (B), NIK Orang Tua (D). Wajib teks eksplisit — string 16
     * digit lolos sebagai ANGKA di binder bawaan, dan Excel (presisi 15 digit) menampilkannya 6,47401E+15
     * lalu mengganti digit ke-16 dengan 0 saat berkas disimpan ulang. Berkas itu kemudian dibaca import
     * sebagai NIK tak valid → NIK dummy. Pola sama dengan KesmasExport::sel().
     */
    private const KOLOM_TEKS = ['A', 'B', 'D'];

    public function bindValue(Cell $cell, $value)
    {
        if (in_array($cell->getColumn(), self::KOLOM_TEKS, true) && is_string($value) && $value !== '') {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    protected $req;

    function __construct($req)
    {
        $this->req = $req;
    }

    public function query()
    {
        $data = DB::table('alldata');
        $this->terapkanTanggal($data);
        $this->terapkanWilayah($data);

        if (!$this->req->boolean('sertakan_tanpa_kunjungan')) {
            return $data->orderBy('id');
        }

        // `urut` unik per baris (kunjungan atau anak): `id` kunjungan dan `id` anak bisa kembar, dan
        // pengurut yang tak unik membuat halaman query (FromQuery) menggandakan/melewatkan baris.
        $data->select('alldata.*')->selectRaw("CONCAT('1', LPAD(alldata.id, 12, '0')) as urut");
        $tanpa = DB::query()->fromSub($this->anakTanpaKunjungan(), 't')->select('t.*');
        $this->terapkanWilayah($tanpa); // rentang tanggal sengaja tidak: anak ini tak punya tanggal kunjungan

        return $data->unionAll($tanpa)->orderBy('urut');
    }

    private function terapkanTanggal(Builder $data): void
    {
        if ($this->req->from_date != '' && $this->req->to_date != '') {
            $data->whereBetween('tgl_kunjungan', [$this->req->from_date, $this->req->to_date]);
        }
    }

    private function terapkanWilayah(Builder $data): void
    {
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
    }

    /**
     * Anak yang belum punya satu pun kunjungan, dengan kolom PERSIS view `alldata` (urutan sama, wajib
     * untuk UNION). Kolom dibaca dari view-nya, jadi kolom baru di view tidak merusak UNION — isinya
     * NULL di baris ini sampai dipetakan di bawah.
     */
    private function anakTanpaKunjungan(): Builder
    {
        $ekspresi = [
            'id' => 'a.id', 'no_kk' => 'a.no_kk', 'nik' => 'a.nik', 'nama' => 'a.nama', 'nik_ortu' => 'a.nik_ortu',
            'nama_ibu' => 'a.nama_ibu', 'nama_ayah' => 'a.nama_ayah', 'jk' => 'a.jk', 'tempat_lahir' => 'a.tempat_lahir',
            'tgl_lahir' => 'a.tgl_lahir', 'golda' => 'a.golda', 'anak' => 'a.anak', 'catatan' => 'a.catatan',
            'sumber' => 'a.sumber', 'sumber_gabungan' => 'a.sumber_gabungan',
            'verifRtStatus' => 'a.verif_rt_status', 'verifRtReviu' => 'a.verif_rt_reviu',
            'idKec' => 'a.id_kec', 'idKel' => 'a.id_kel', 'idPuskes' => 'a.id_puskesmas', 'idPos' => 'a.id_posyandu', 'idRt' => 'a.id_rt',
            'nameKec' => 'kec.name', 'nameKel' => 'kel.name', 'namePuskes' => 'pus.name', 'namePos' => 'pos.name', 'nameRt' => 'rt.name',
        ];
        // SHOW COLUMNS urut menurut posisi kolom (UNION mencocokkan per posisi); getColumnListing tak menjaminnya.
        $kolom = array_map(
            fn ($baris) => ($ekspresi[$baris->Field] ?? 'NULL') . ' as `' . $baris->Field . '`',
            DB::select('SHOW COLUMNS FROM `alldata`')
        );

        return DB::table('anak as a')
            ->leftJoin('kecamatan as kec', 'kec.id', '=', 'a.id_kec')
            ->leftJoin('kelurahan as kel', 'kel.id', '=', 'a.id_kel')
            ->leftJoin('puskesmas as pus', 'pus.id', '=', 'a.id_puskesmas')
            ->leftJoin('posyandu as pos', 'pos.id', '=', 'a.id_posyandu')
            ->leftJoin('rt', 'rt.id', '=', 'a.id_rt')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('data_anak as d')->whereColumn('d.id_anak', 'a.id'))
            ->selectRaw(implode(', ', $kolom))
            ->selectRaw("CONCAT('2', LPAD(a.id, 12, '0')) as urut");
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
            // Verifikasi RT (spec §7): asal data & status domisili hasil verifikasi RT
            'Sumber Data',
            'Verifikasi RT',
            'Reviu Verifikasi',
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
            $this->labelSumber($data),
            $data->verifRtStatus ? (VerifikasiAnak::LABEL_STATUS[$data->verifRtStatus] ?? $data->verifRtStatus) : '-',
            $data->verifRtReviu ?: '-',
        ];
    }

    /** "operasi_timbang (+capil)" — sumber utama + sumber lain yang pernah dilebur (verifikasi RT §3.2). */
    private function labelSumber($data): string
    {
        $lain = array_values(array_diff((array) (json_decode((string) $data->sumber_gabungan, true) ?: []), [$data->sumber]));
        return (string) $data->sumber . ($lain ? ' (+'.implode(', ', $lain).')' : '');
    }
}

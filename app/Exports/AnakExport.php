<?php

namespace App\Exports;

use App\Models\VerifikasiAnak;
use Generator;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export Data Anak — satu baris per KUNJUNGAN (view `alldata` = data_anak INNER JOIN anak).
 *
 * Opsi `sertakan_tanpa_kunjungan`: tambahkan juga anak yang belum punya kunjungan (mis. hasil AnakImport,
 * yang hanya membuat identitas), satu baris per anak dengan kolom pengukuran kosong. Tanpa opsi ini mereka
 * tak pernah muncul di export, sehingga jumlah baris tak cocok dengan berkas import.
 *
 * Ditulis STREAMING (OpenSpout), bukan Maatwebsite FromQuery + ShouldAutoSize: yang terakhir menumpuk
 * seluruh buku di PhpSpreadsheet (±182 MB untuk 10 rb baris × 32 kolom, belum termasuk penulisan berkas),
 * sehingga di prod (memory_limit 128 MB) tombol Export mati dengan "This page isn't working" tanpa satu
 * baris pun di laravel.log. Baris dibaca per 500 lewat generator (keyset, bukan offset) dan ditulis
 * langsung ke berkas sementara; memori puncak tak bergantung jumlah baris. Dikunci ExportAnakMemoriTest.
 * Jangan kembalikan ke FromQuery/ShouldAutoSize. Pola sama dengan KesmasExport.
 */
final class AnakExport
{
    private const POTONGAN = 500;

    private const LEBAR_BAWAAN = 18;
    private const LEBAR_NAMA = 32;

    /**
     * Kolom yang tetap ANGKA: Jenis Kelamin (G), Anak Ke- (K), Bulan (S), TB, BB, BMI, LLA, LK (U–Y),
     * ASI (AA), Vitamin A (AB). Selain itu teks literal — terutama No KK (A), NIK (B), NIK Orang Tua (D):
     * 16 digit sebagai ANGKA tampil 6,47401E+15 di Excel (presisi 15 digit) dan digit ke-16 menjadi 0
     * bila berkas disimpan ulang, lalu dibaca import sebagai NIK tak valid.
     */
    private const KOLOM_ANGKA = ['G', 'K', 'S', 'U', 'V', 'W', 'X', 'Y', 'AA', 'AB'];

    /** @var array<int, true> */
    private array $kolomAngka;

    public function __construct(private Request $req)
    {
        $this->kolomAngka = KesmasExport::indeksKolom(self::KOLOM_ANGKA);
    }

    /**
     * Unduhan: berkas ditulis bertahap ke berkas sementara saat respons dikirim, lalu dialirkan dan dihapus.
     */
    public function unduh(string $namaBerkas): StreamedResponse
    {
        return response()->streamDownload(function () {
            $sementara = tempnam(sys_get_temp_dir(), 'anak');
            try {
                $this->simpan($sementara);
                readfile($sementara);
            } finally {
                @unlink($sementara);
            }
        }, $namaBerkas, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** Tulis ke berkas (tes, atau pemakaian dari artisan). Jalur penulisannya sama dengan unduh(). */
    public function simpan(string $path): string
    {
        $opsi = new Options();
        // Shared string (bukan inline): teks terbaca sebagai string biasa oleh pembaca xlsx mana pun.
        $opsi->SHOULD_USE_INLINE_STRINGS = false;
        $opsi->DEFAULT_COLUMN_WIDTH = self::LEBAR_BAWAAN;
        $opsi->setColumnWidth(self::LEBAR_NAMA, 3); // Nama

        $penulis = new Writer($opsi);
        $penulis->openToFile($path);
        $penulis->addRow(new Row(array_map([KesmasExport::class, 'sel'], $this->headings())));
        foreach ($this->baris() as $baris) {
            $penulis->addRow(new Row(array_map([KesmasExport::class, 'sel'], $baris)));
        }
        $penulis->close();

        return $path;
    }

    /** @return Generator<int, list<string|int|float|null>> */
    public function baris(): Generator
    {
        $data = DB::table('alldata');
        $this->terapkanTanggal($data);
        $this->terapkanWilayah($data, ['kec' => 'idKec', 'puskes' => 'idPuskes', 'pos' => 'idPos', 'kel' => 'idKel', 'rt' => 'idRt']);

        foreach ($data->lazyById(self::POTONGAN, 'id') as $baris) {
            yield KesmasExport::rapikan($this->map($baris), $this->kolomAngka);
        }

        if (!$this->req->boolean('sertakan_tanpa_kunjungan')) {
            return;
        }

        // Rentang tanggal sengaja tidak diterapkan: anak ini tak punya tanggal kunjungan.
        $tanpa = $this->anakTanpaKunjungan();
        $this->terapkanWilayah($tanpa, ['kec' => 'a.id_kec', 'puskes' => 'a.id_puskesmas', 'pos' => 'a.id_posyandu', 'kel' => 'a.id_kel', 'rt' => 'a.id_rt']);

        foreach ($tanpa->lazyById(self::POTONGAN, 'a.id', 'id') as $baris) {
            yield KesmasExport::rapikan($this->map($baris), $this->kolomAngka);
        }
    }

    private function terapkanTanggal(Builder $data): void
    {
        // Boleh diisi salah satu saja: tanggal yang diisi tetap berlaku, bukan diabaikan diam-diam.
        if ($this->req->from_date != '') {
            $data->where('tgl_kunjungan', '>=', $this->req->from_date);
        }
        if ($this->req->to_date != '') {
            $data->where('tgl_kunjungan', '<=', $this->req->to_date);
        }
    }

    /** @param array{kec:string,puskes:string,pos:string,kel:string,rt:string} $kolom nama kolom per tingkat wilayah */
    private function terapkanWilayah(Builder $data, array $kolom): void
    {
        $ada = fn ($nilai) => $nilai !== "0" && $nilai !== null && $nilai !== '';

        if ($ada($this->req->id_kec)) {
            $data->where($kolom['kec'], $this->req->id_kec);

            if ($ada($this->req->id_puskesmas)) {
                $data->where($kolom['puskes'], $this->req->id_puskesmas);

                if ($ada($this->req->id_posyandu)) {
                    $data->where($kolom['pos'], $this->req->id_posyandu);
                }
            } elseif ($ada($this->req->id_kelurahan)) {
                $data->where($kolom['kel'], $this->req->id_kelurahan);

                if ($ada($this->req->id_rt)) {
                    $data->where($kolom['rt'], $this->req->id_rt);
                }
            }
        }
    }

    /**
     * Anak yang belum punya satu pun kunjungan, dengan nama kolom yang sama dengan view `alldata`
     * (map() membaca properti itu). Kolom pengukuran NULL → sel kosong.
     */
    private function anakTanpaKunjungan(): Builder
    {
        return DB::table('anak as a')
            ->leftJoin('kecamatan as kec', 'kec.id', '=', 'a.id_kec')
            ->leftJoin('kelurahan as kel', 'kel.id', '=', 'a.id_kel')
            ->leftJoin('puskesmas as pus', 'pus.id', '=', 'a.id_puskesmas')
            ->leftJoin('posyandu as pos', 'pos.id', '=', 'a.id_posyandu')
            ->leftJoin('rt', 'rt.id', '=', 'a.id_rt')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('data_anak as d')->whereColumn('d.id_anak', 'a.id'))
            ->select([
                'a.id', 'a.no_kk', 'a.nik', 'a.nama', 'a.nik_ortu', 'a.nama_ibu', 'a.nama_ayah', 'a.jk', 'a.tempat_lahir',
                'a.tgl_lahir', 'a.golda', 'a.anak', 'a.catatan', 'a.sumber', 'a.sumber_gabungan',
                'a.verif_rt_status as verifRtStatus', 'a.verif_rt_reviu as verifRtReviu',
                'kec.name as nameKec', 'kel.name as nameKel', 'pus.name as namePuskes', 'pos.name as namePos', 'rt.name as nameRt',
            ])
            ->selectRaw('NULL as tgl_kunjungan, NULL as bln, NULL as posisi, NULL as tb, NULL as bb, NULL as lla, NULL as lk')
            ->selectRaw('NULL as ntob, NULL as asi, NULL as vit_a, NULL as namaPetugas');
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

    /** @return list<mixed> */
    private function map(object $data): array
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
    private function labelSumber(object $data): string
    {
        $lain = array_values(array_diff((array) (json_decode((string) $data->sumber_gabungan, true) ?: []), [$data->sumber]));

        return (string) $data->sumber . ($lain ? ' (+' . implode(', ', $lain) . ')' : '');
    }
}

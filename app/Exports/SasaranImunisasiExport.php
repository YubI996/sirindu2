<?php

namespace App\Exports;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Daftar nama dan alamat (BNBA) anak sasaran satu kelompok kohort — tombol Export di modal
 * kartu Data sasaran dasbor imunisasi. Baris dari ImunisasiStatusService::getDaftarSasaran(),
 * sumber yang sama dengan angka kartu, jadi jumlah baris berkas = angka kartu.
 *
 * Ditulis lewat OpenSpout dengan StringCell (KesmasExport::sel): NIK 16 digit tetap teks, dan
 * nama/alamat berawalan '=' tidak menjadi rumus. Hanya kolom No yang angka.
 */
final class SasaranImunisasiExport
{
    private const LEBAR_BAWAAN = 16;

    /** @param list<array<string, mixed>> $rows */
    public function __construct(private array $rows, private string $judul)
    {
    }

    public function unduh(string $namaBerkas): StreamedResponse
    {
        return response()->streamDownload(function () {
            $sementara = tempnam(sys_get_temp_dir(), 'sasaran');
            try {
                $this->simpan($sementara);
                readfile($sementara);
            } finally {
                @unlink($sementara);
            }
        }, $namaBerkas, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function simpan(string $path): string
    {
        $opsi = new Options();
        $opsi->SHOULD_USE_INLINE_STRINGS = false;
        $opsi->DEFAULT_COLUMN_WIDTH = self::LEBAR_BAWAAN;
        $opsi->setColumnWidth(6, 1);   // No
        $opsi->setColumnWidth(32, 2);  // Nama
        $opsi->setColumnWidth(40, 11); // Alamat

        $penulis = new Writer($opsi);
        $penulis->openToFile($path);
        $penulis->getCurrentSheet()->setName(mb_substr($this->judul, 0, 31));
        $penulis->addRow(new Row(array_map([KesmasExport::class, 'sel'], $this->headings())));

        $no = 0;
        foreach ($this->rows as $r) {
            $baris = [++$no];
            foreach (['nama', 'nik', 'jk', 'tgl_lahir', 'nama_ibu', 'kecamatan', 'kelurahan', 'rt', 'posyandu', 'alamat'] as $kolom) {
                $baris[] = $r[$kolom] === null ? null : (string) $r[$kolom];
            }
            $penulis->addRow(new Row(array_map([KesmasExport::class, 'sel'], $baris)));
        }
        $penulis->close();

        return $path;
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['No', 'Nama', 'NIK', 'L/P', 'Tanggal Lahir', 'Nama Ibu', 'Kecamatan', 'Kelurahan', 'RT', 'Posyandu', 'Alamat Domisili'];
    }
}

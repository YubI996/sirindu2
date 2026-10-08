<?php

namespace App\Exports;

use App\Models\Kelurahan;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export Kesmas — dua sheet: "Per Anak" (identitas + Kesmas + riwayat lahir) dan
 * "Per Kunjungan" (layanan per baris data_anak). Bahan dasbor Kesmas (spec §5).
 *
 * Ditulis STREAMING (OpenSpout langsung, dependensi rap2hpoutre/fast-excel), bukan
 * Maatwebsite/PhpSpreadsheet: yang terakhir
 * menumpuk seluruh buku di memori (±76 KB per anak di uji, ±440 MB untuk 10 rb anak) dan di prod
 * ekspor seluruh kota mati dengan "This page isn't working" sementara satu kelurahan berhasil.
 * Sumber barisnya dibaca per 500 lewat generator, jadi memori puncak tetap kecil berapa pun
 * jumlah anaknya. Dikunci ExportKesmasMemoriTest. Jangan kembalikan ke FromQuery/ShouldAutoSize.
 *
 * @phpstan-type Filter array{id_kec?:mixed,id_kel?:mixed,id_puskesmas?:mixed,id_posyandu?:mixed,dari?:mixed,sampai?:mixed}
 */
final class KesmasExport
{
    /** Lebar kolom bawaan (satuan karakter); kolom Nama dilebarkan. Pengganti ShouldAutoSize. */
    private const LEBAR_BAWAAN = 18;
    private const LEBAR_NAMA = 32;

    /** @param Filter $filter */
    public function __construct(private array $filter) {}

    /** @return list<KesmasAnakSheet|KesmasKunjunganSheet> */
    public function sheets(): array
    {
        return [new KesmasAnakSheet($this->filter), new KesmasKunjunganSheet($this->filter)];
    }

    public function filename(): string
    {
        $kel = !empty($this->filter['id_kel']) ? Kelurahan::find($this->filter['id_kel'])?->name : null;

        return 'kesmas-' . ($kel ? Str::slug($kel) : 'semua') . '-' . now()->format('Ymd') . '.xlsx';
    }

    /**
     * Unduhan: berkas ditulis bertahap ke berkas sementara saat respons dikirim (bukan sebelumnya),
     * lalu dialirkan dan dihapus. Memori puncak tak bergantung jumlah baris.
     */
    public function unduh(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $sementara = tempnam(sys_get_temp_dir(), 'kesmas');
            try {
                $this->simpan($sementara);
                readfile($sementara);
            } finally {
                @unlink($sementara);
            }
        }, $this->filename(), ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** Tulis ke berkas (tes, atau pemakaian dari artisan). Jalur penulisannya sama dengan unduh(). */
    public function simpan(string $path): string
    {
        $opsi = new Options();
        // Shared string (bukan inline): teks terbaca sebagai string biasa oleh pembaca xlsx mana pun.
        $opsi->SHOULD_USE_INLINE_STRINGS = false;
        $opsi->DEFAULT_COLUMN_WIDTH = self::LEBAR_BAWAAN;
        $opsi->setColumnWidth(self::LEBAR_NAMA, 2);

        $penulis = new Writer($opsi);
        $penulis->openToFile($path);

        foreach ($this->sheets() as $i => $sheet) {
            if ($i > 0) {
                $penulis->addNewSheetAndMakeItCurrent();
            }
            $penulis->getCurrentSheet()->setName($sheet->title());
            foreach ($sheet->baris() as $baris) {
                $penulis->addRow(new Row(array_map([self::class, 'sel'], $baris)));
            }
        }
        $penulis->close();

        return $path;
    }

    /**
     * Sel bertipe EKSPLISIT. Jangan memakai Row::fromValues()/FastExcel di sini: OpenSpout mengubah
     * setiap string berawalan '=' menjadi RUMUS (Cell::fromValue), sehingga nama atau catatan seperti
     * "=HYPERLINK(...)" dijalankan Excel. StringCell menjaganya tetap teks literal.
     */
    public static function sel(string|int|float|null $nilai): Cell
    {
        return match (true) {
            $nilai === null => new EmptyCell(null, null),
            is_string($nilai) => new StringCell($nilai, null),
            default => new NumericCell($nilai, null),
        };
    }

    /**
     * Indeks kolom (0-based) dari huruf kolom Excel, untuk daftar kolom yang harus tetap angka.
     *
     * @param  list<string> $huruf
     * @return array<int, true>
     */
    public static function indeksKolom(array $huruf): array
    {
        $indeks = [];
        foreach ($huruf as $h) {
            $indeks[Coordinate::columnIndexFromString($h) - 1] = true;
        }

        return $indeks;
    }

    /**
     * Satu baris siap tulis. Teks tetap literal — termasuk NIK dan isian berawalan '=' — sedangkan
     * kolom angka tetap numerik (termasuk nol). NULL tetap NULL → sel kosong.
     *
     * @param  list<mixed>      $baris
     * @param  array<int, true> $kolomAngka hasil indeksKolom()
     * @return list<string|int|float|null>
     */
    public static function rapikan(array $baris, array $kolomAngka): array
    {
        foreach ($baris as $i => $nilai) {
            if ($nilai === null) {
                continue;
            }
            $baris[$i] = isset($kolomAngka[$i]) && is_numeric($nilai) ? $nilai + 0 : (string) $nilai;
        }

        return $baris;
    }
}

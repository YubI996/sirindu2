<?php

namespace App\Exports;

use App\Models\Imunisasi;
use App\Models\JenisVaksin;
use App\Models\Kelurahan;
use App\Models\KelompokVaksin;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class AgregatImunisasiExport implements FromCollection, ShouldAutoSize, WithTitle, WithEvents
{
    protected int $bulan;
    protected int $tahun;
    protected Collection $vaccines;
    protected Collection $kelompoks;

    public function __construct(int $bulan, int $tahun)
    {
        $this->bulan = $bulan;
        $this->tahun = $tahun;
        $this->vaccines = JenisVaksin::aktif()
            ->whereNotNull('id_kelompok_vaksin')
            ->orderBy('id_kelompok_vaksin')
            ->orderBy('id')
            ->get();
        $this->kelompoks = KelompokVaksin::orderBy('id')->get();
    }

    public function collection(): Collection
    {
        $rows = collect();

        // Title row
        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $titleRow = collect(["Data Agregat Imunisasi Bulan {$monthNames[$this->bulan]} Tahun {$this->tahun}"]);
        $rows->push($titleRow);

        // Empty row
        $rows->push(collect(['']));

        // Header Row 1: No | Kelurahan | [vaccine names...] | IDL | IBL | ISL
        $header1 = collect(['No', 'Kelurahan']);
        foreach ($this->vaccines as $v) {
            $header1->push($v->nama);
            // 5 empty cells for sub-headers (%L, #P, %P, #Jml, %Jml)
            for ($i = 0; $i < 5; $i++) {
                $header1->push('');
            }
        }
        foreach ($this->kelompoks as $k) {
            $header1->push($k->kode);
            for ($i = 0; $i < 5; $i++) {
                $header1->push('');
            }
        }
        $rows->push($header1);

        // Header Row 2: empty | empty | [#L, %L, #P, %P, #Jml, %Jml] repeated
        $header2 = collect(['', '']);
        $subHeaders = ['#L', '%L', '#P', '%P', '#Jml', '%Jml'];
        $totalGroups = $this->vaccines->count() + $this->kelompoks->count();
        for ($g = 0; $g < $totalGroups; $g++) {
            foreach ($subHeaders as $sh) {
                $header2->push($sh);
            }
        }
        $rows->push($header2);

        // Query immunization data for the month/year
        $imunisasiData = Imunisasi::query()
            ->where('status', 'sudah')
            ->whereYear('tanggal_pemberian', $this->tahun)
            ->whereMonth('tanggal_pemberian', $this->bulan)
            ->with(['anak:id,id_kel,jk', 'anak.kel:id,name'])
            ->get();

        // Get all kelurahan that have data
        $kelurahanList = Kelurahan::orderBy('name')->get();

        // Group data: kelurahan -> vaccine -> gender counts
        $grouped = [];
        foreach ($imunisasiData as $imun) {
            $kelId = $imun->anak->id_kel ?? 0;
            $vaksinId = $imun->id_jenis_vaksin;
            $gender = $imun->anak->jk ?? null;

            if (!isset($grouped[$kelId])) {
                $grouped[$kelId] = [];
            }
            if (!isset($grouped[$kelId][$vaksinId])) {
                $grouped[$kelId][$vaksinId] = ['L' => 0, 'P' => 0];
            }

            // jk: 'L' or 1 = male, 'P' or 2 = female
            if ($gender === 'L' || $gender === 1 || $gender === '1') {
                $grouped[$kelId][$vaksinId]['L']++;
            } else {
                $grouped[$kelId][$vaksinId]['P']++;
            }
        }

        // Build vaccine-to-kelompok mapping
        $vaccineKelompok = $this->vaccines->pluck('id_kelompok_vaksin', 'id');

        // Data rows per kelurahan
        $no = 1;
        foreach ($kelurahanList as $kel) {
            $kelData = $grouped[$kel->id] ?? [];
            $row = collect([$no, $kel->name]);

            // Per-kelompok accumulators
            $kelompokCounts = [];
            foreach ($this->kelompoks as $k) {
                $kelompokCounts[$k->id] = ['L' => 0, 'P' => 0];
            }

            // Per vaccine columns
            foreach ($this->vaccines as $v) {
                $counts = $kelData[$v->id] ?? ['L' => 0, 'P' => 0];
                $l = $counts['L'];
                $p = $counts['P'];
                $jml = $l + $p;

                $row->push($l);
                $row->push($jml > 0 ? round(($l / $jml) * 100, 1) : 0);
                $row->push($p);
                $row->push($jml > 0 ? round(($p / $jml) * 100, 1) : 0);
                $row->push($jml);
                $row->push(100); // %Jml is always 100% of itself

                // Accumulate into kelompok
                $kId = $vaccineKelompok[$v->id] ?? null;
                if ($kId && isset($kelompokCounts[$kId])) {
                    $kelompokCounts[$kId]['L'] += $l;
                    $kelompokCounts[$kId]['P'] += $p;
                }
            }

            // Per kelompok columns
            foreach ($this->kelompoks as $k) {
                $l = $kelompokCounts[$k->id]['L'];
                $p = $kelompokCounts[$k->id]['P'];
                $jml = $l + $p;

                $row->push($l);
                $row->push($jml > 0 ? round(($l / $jml) * 100, 1) : 0);
                $row->push($p);
                $row->push($jml > 0 ? round(($p / $jml) * 100, 1) : 0);
                $row->push($jml);
                $row->push(100);
            }

            // Only include kelurahan that have any data (to keep the export clean)
            if (!empty($kelData)) {
                $rows->push($row);
                $no++;
            }
        }

        // If no data at all, add an empty info row
        if ($no === 1) {
            $rows->push(collect(['', 'Tidak ada data imunisasi untuk periode ini']));
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Agregat Imunisasi';
    }

    public function registerEvents(): array
    {
        $vaccineCount = $this->vaccines->count();
        $kelompokCount = $this->kelompoks->count();

        return [
            AfterSheet::class => function (AfterSheet $event) use ($vaccineCount, $kelompokCount) {
                $sheet = $event->sheet->getDelegate();

                // Bold title row
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

                // Bold + center header rows 3-4
                $lastCol = 2 + ($vaccineCount + $kelompokCount) * 6;
                $lastColLetter = $this->colLetter($lastCol);

                $sheet->getStyle("A3:{$lastColLetter}4")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 9],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN],
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFE2E8F0'],
                    ],
                ]);

                // Merge vaccine name headers (row 3)
                $col = 3; // start after No, Kelurahan
                for ($i = 0; $i < $vaccineCount + $kelompokCount; $i++) {
                    $startLetter = $this->colLetter($col);
                    $endLetter = $this->colLetter($col + 5);
                    $sheet->mergeCells("{$startLetter}3:{$endLetter}3");
                    $col += 6;
                }

                // Merge title row
                $sheet->mergeCells("A1:{$lastColLetter}1");

                // Merge No and Kelurahan across rows 3-4
                $sheet->mergeCells('A3:A4');
                $sheet->mergeCells('B3:B4');

                // Add borders to data rows
                $highestRow = $sheet->getHighestRow();
                if ($highestRow > 4) {
                    $sheet->getStyle("A5:{$lastColLetter}{$highestRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN],
                        ],
                        'font' => ['size' => 9],
                    ]);
                }

                // Color kelompok header cells differently
                $kelompokStart = 3 + $vaccineCount * 6;
                for ($i = 0; $i < $kelompokCount; $i++) {
                    $startLetter = $this->colLetter($kelompokStart + $i * 6);
                    $endLetter = $this->colLetter($kelompokStart + $i * 6 + 5);
                    $sheet->getStyle("{$startLetter}3:{$endLetter}4")->applyFromArray([
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FFBDD7EE'],
                        ],
                    ]);
                }
            },
        ];
    }

    private function colLetter(int $colNumber): string
    {
        $letter = '';
        while ($colNumber > 0) {
            $colNumber--;
            $letter = chr(65 + ($colNumber % 26)) . $letter;
            $colNumber = intdiv($colNumber, 26);
        }
        return $letter;
    }

    public function filename(): string
    {
        return "agregat_imunisasi_{$this->tahun}_{$this->bulan}.xlsx";
    }
}

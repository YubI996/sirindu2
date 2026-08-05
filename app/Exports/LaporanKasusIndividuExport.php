<?php

namespace App\Exports;

use App\Models\JenisKasusEpidemiologi;
use App\Models\SurveillanceCase;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Laporan "List Kasus Individu" per penyakit (format mengikuti LIST INDIVIDU.xlsx).
 *
 * Tiap file diberi blok judul di atas: Judul, Kota, Tanggal, Waktu (permintaan klien).
 * Kolom menyesuaikan penyakit terpilih; field yang tak tersimpan sistem dibiarkan
 * kosong (lihat docs/export formulir/FIELD-TIDAK-TERISI-per-penyakit.md).
 */
class LaporanKasusIndividuExport implements FromQuery, WithHeadings, WithMapping, WithTitle, WithEvents, ShouldAutoSize
{
    /** @var array<int, array{0:string,1:callable}> [label, value(fn)] */
    private array $columns;

    private int $rowNum = 0;
    private string $namaPenyakit;

    public function __construct(
        private int $tahun,
        private int $jenisKasusId,
        private string $kota = 'Bontang'
    ) {
        $disease = JenisKasusEpidemiologi::find($jenisKasusId);
        $this->namaPenyakit = $disease?->nama_penyakit ?? 'PD3I';
        $this->columns = $this->columnsFor($disease?->kode_penyakit ?? '');
    }

    public function query()
    {
        return SurveillanceCase::query()
            ->with(['jenisKasus:id,nama_penyakit', 'kecamatan:id,name', 'kelurahan:id,name', 'spesimen'])
            ->whereYear('tanggal_lapor', $this->tahun)
            ->where('id_jenis_kasus', $this->jenisKasusId)
            ->orderBy('tanggal_lapor')
            ->orderBy('no_registrasi');
    }

    public function headings(): array
    {
        return array_map(fn ($c) => $c[0], $this->columns);
    }

    public function map($case): array
    {
        $this->rowNum++;
        return array_map(fn ($c) => $c[1]($case, $this->rowNum), $this->columns);
    }

    public function title(): string
    {
        return substr($this->namaPenyakit, 0, 28);
    }

    /** Blok judul di atas tabel: Judul, Kota, Tanggal, Waktu. */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = Coordinate::stringFromColumnIndex(count($this->columns));

                $sheet->insertNewRowBefore(1, 5);
                $sheet->setCellValue('A1', 'LIST KASUS INDIVIDU — ' . strtoupper($this->namaPenyakit) . ' TAHUN ' . $this->tahun);
                $sheet->setCellValue('A2', 'Kota: ' . $this->kota);
                $sheet->setCellValue('A3', 'Tanggal: ' . now()->format('d/m/Y'));
                $sheet->setCellValue('A4', 'Waktu: ' . now()->format('H:i') . ' WITA');

                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
                $sheet->getStyle('A2:A4')->getFont()->setSize(10);
                // Baris heading kolom kini di baris 6.
                $sheet->getStyle("A6:{$lastCol}6")->getFont()->setBold(true);
                $sheet->getStyle("A6:{$lastCol}6")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('D9E1F2');
            },
        ];
    }

    // ==================== KOLOM PER PENYAKIT ====================

    private function columnsFor(string $kode): array
    {
        return match ($kode) {
            'AFP'            => $this->kolomAfp(),
            'DIFTERI_OBS'    => $this->kolomDifteri(),
            'PERTUSIS'       => $this->kolomPertusis(),
            'TETANUS_NEO'    => $this->kolomTn(),
            default          => $this->kolomCampakRubella(),
        };
    }

    /** Kolom identitas yang dipakai hampir semua penyakit. */
    private function identitas(): array
    {
        return [
            ['No', fn ($c, $n) => $n],
            ['Nomor EPID', fn ($c) => $c->no_registrasi],
            ['Nama', fn ($c) => $c->nama_lengkap],
            ['Jenis Kelamin', fn ($c) => $c->jenis_kelamin],
            ['Umur (Tahun)', fn ($c) => $this->umurTahun($c)],
            ['Umur (Bulan)', fn ($c) => $this->umurBulan($c)],
            ['Alamat', fn ($c) => $c->alamat_lengkap],
            ['Kelurahan/Desa', fn ($c) => $c->kelurahan->name ?? null],
            ['Kecamatan', fn ($c) => $c->kecamatan->name ?? null],
            ['Kabupaten/Kota', fn ($c) => $c->kab_kota ?? $this->kota],
            ['Provinsi', fn ($c) => $c->provinsi ?? 'Kalimantan Timur'],
            ['Tanggal Laporan Diterima', fn ($c) => $this->d($c->tanggal_lapor)],
            ['Tanggal Pelacakan', fn () => null],
        ];
    }

    private function kolomAfp(): array
    {
        return array_merge($this->identitas(), [
            ['Tanggal Mulai Sakit', fn ($c) => $this->d($c->tanggal_onset)],
            ['Tanggal Mulai Lumpuh', fn () => null],
            ['Kelumpuhan Tungkai Kanan', fn () => null],
            ['Kelumpuhan Tungkai Kiri', fn () => null],
            ['Kelumpuhan Lengan Kanan', fn () => null],
            ['Kelumpuhan Lengan Kiri', fn () => null],
            ['Status Imunisasi OPV', fn () => null],
            ['Status Imunisasi IPV', fn () => null],
            ['PIN Polio / ORI', fn () => null],
            ['Tanggal Imunisasi Polio Terakhir', fn ($c) => $this->d($c->tanggal_imunisasi_terakhir)],
            ['Spesimen I Tgl Ambil', fn ($c) => $this->d($this->sp($c, 0)?->tanggal_ambil_spesimen)],
            ['Spesimen I Tgl Kirim', fn ($c) => $this->d($this->sp($c, 0)?->tanggal_kirim_sampel)],
            ['Spesimen II Tgl Ambil', fn ($c) => $this->d($this->sp($c, 1)?->tanggal_ambil_spesimen)],
            ['Spesimen II Tgl Kirim', fn ($c) => $this->d($this->sp($c, 1)?->tanggal_kirim_sampel)],
            ['Hasil Lab', fn ($c) => $c->hasil_lab],
            ['Diagnosis DSA/DSS', fn () => null],
            ['Klasifikasi Final', fn ($c) => $c->status_kasus],
            ['Kunjungan Ulang', fn () => null],
        ]);
    }

    private function kolomCampakRubella(): array
    {
        return array_merge($this->identitas(), [
            ['Nama Orangtua/Wali', fn ($c) => $c->nama_orang_tua ?? null],
            ['Tanggal Mulai Demam', fn ($c) => $this->d($c->tanggal_demam ?? null)],
            ['Tanggal Mulai Rash', fn ($c) => $this->d($c->tanggal_onset)],
            ['Gejala Batuk', fn ($c) => $this->yn($c->gejala_batuk)],
            ['Gejala Pilek', fn ($c) => $this->yn($c->gejala_pilek)],
            ['Gejala Mata Merah', fn ($c) => $this->yn($c->gejala_mata_merah)],
            ['Dirawat di RS', fn ($c) => $this->yn($c->status_rawat === 'rawat_inap')],
            ['MCV1 (9 bln)', fn () => null],
            ['MCV2 (18 bln)', fn () => null],
            ['Campak BIAS Kelas 1 SD', fn () => null],
            ['Tanggal Vaksin MR Terakhir', fn ($c) => $this->d($c->tanggal_imunisasi_terakhir)],
            ['Tgl Ambil Serum', fn ($c) => $this->d($c->tanggal_pengambilan_spesimen)],
            ['Hasil IgM Campak', fn ($c) => str_contains(strtolower($c->hasil_lab ?? ''), 'campak: positif') ? 'Positif' : null],
            ['Hasil IgM Rubella', fn ($c) => str_contains(strtolower($c->hasil_lab ?? ''), 'rubella: positif') ? 'Positif' : null],
            ['Klasifikasi Final', fn ($c) => $c->status_kasus],
        ]);
    }

    private function kolomDifteri(): array
    {
        return array_merge($this->identitas(), [
            ['Tanggal Mulai Sakit (Sakit Tenggorok)', fn ($c) => $this->d($c->tanggal_onset)],
            ['Gejala Demam', fn ($c) => $this->yn($c->gejala_demam)],
            ['Gejala Sesak Napas', fn ($c) => $this->yn($c->gejala_sesak_napas)],
            ['Status Imunisasi Difteri', fn ($c) => $c->riwayat_imunisasi],
            ['Tanggal Vaksinasi Terakhir', fn ($c) => $this->d($c->tanggal_imunisasi_terakhir)],
            ['Spesimen Tgl Ambil', fn ($c) => $this->d($this->sp($c, 0)?->tanggal_ambil_spesimen)],
            ['Spesimen Tgl Kirim', fn ($c) => $this->d($this->sp($c, 0)?->tanggal_kirim_sampel)],
            ['Hasil Kultur', fn ($c) => $c->hasil_lab],
            ['Klasifikasi Difteri', fn ($c) => $c->status_kasus],
            ['Keadaan Akhir', fn ($c) => $c->kondisi_akhir],
        ]);
    }

    private function kolomPertusis(): array
    {
        return array_merge($this->identitas(), [
            ['Tanggal Mulai Sakit (Batuk/Apnea)', fn ($c) => $this->d($c->tanggal_onset)],
            ['Gejala Batuk', fn ($c) => $this->yn($c->gejala_batuk)],
            ['Muntah Setelah Batuk', fn ($c) => $this->yn($c->gejala_muntah)],
            ['Status Imunisasi Pertusis', fn ($c) => $c->riwayat_imunisasi],
            ['Tanggal Vaksinasi Terakhir', fn ($c) => $this->d($c->tanggal_imunisasi_terakhir)],
            ['Spesimen Tgl Ambil', fn ($c) => $this->d($this->sp($c, 0)?->tanggal_ambil_spesimen)],
            ['Spesimen Tgl Kirim', fn ($c) => $this->d($this->sp($c, 0)?->tanggal_kirim_sampel)],
            ['Hasil Kultur', fn ($c) => $c->hasil_lab],
            ['Klasifikasi Pertusis', fn ($c) => $c->status_kasus],
            ['Keadaan Akhir', fn ($c) => $c->kondisi_akhir],
        ]);
    }

    private function kolomTn(): array
    {
        return array_merge($this->identitas(), [
            ['Tanggal Lahir Bayi', fn ($c) => $this->d($c->tanggal_lahir)],
            ['Tanggal Mulai Sakit', fn ($c) => $this->d($c->tanggal_onset)],
            ['Bayi Dirawat', fn ($c) => $this->yn($c->status_rawat === 'rawat_inap')],
            ['Tempat Perawatan', fn ($c) => $c->nama_faskes_rawat],
            ['Keadaan Akhir', fn ($c) => $c->kondisi_akhir],
            ['Tanggal Meninggal', fn ($c) => $c->kondisi_akhir === 'meninggal' ? $this->d($c->tanggal_kondisi_akhir) : null],
            ['Klasifikasi Final', fn ($c) => $c->status_kasus],
        ]);
    }

    // ==================== HELPERS ====================

    private function sp($case, int $i)
    {
        return ($case->spesimen ?? collect())->values()->get($i);
    }

    private function umurTahun($case): ?int
    {
        return $case->tanggal_lahir ? $case->tanggal_lahir->age : null;
    }

    private function umurBulan($case): ?int
    {
        return $case->tanggal_lahir ? (int) $case->tanggal_lahir->diffInMonths(now()) % 12 : null;
    }

    private function yn($v): string
    {
        return $v ? 'Ya' : 'Tidak';
    }

    private function d($value): ?string
    {
        if (empty($value)) {
            return null;
        }
        try {
            return Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }
}

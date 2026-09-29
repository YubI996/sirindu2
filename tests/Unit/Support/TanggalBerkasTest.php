<?php

namespace Tests\Unit\Support;

use App\Support\TanggalBerkas;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Satu pintu pembacaan tanggal berkas import. Triplet seperti 05/02/2020 tak bisa
 * dibaca tanpa tahu urutan berkasnya — deteksi() menyimpulkannya dari isi berkas,
 * parse() menerapkannya. Sel tanggal Excel, serial, dan 2020-01-15 tak pernah ambigu.
 */
class TanggalBerkasTest extends TestCase
{
    // ---------------------------------------------------------------- deteksi

    public function test_deteksi_menyimpulkan_dmy_dari_hari_lebih_dari_12(): void
    {
        $this->assertSame(
            TanggalBerkas::DMY,
            TanggalBerkas::deteksi(['05/02/2020', '25/02/2020', 'Budi'])
        );
    }

    public function test_deteksi_menyimpulkan_mdy_dari_bulan_lebih_dari_12(): void
    {
        $this->assertSame(
            TanggalBerkas::MDY,
            TanggalBerkas::deteksi(['05/02/2020', '2/25/2020'])
        );
    }

    public function test_deteksi_kosong_kalau_berkas_tak_punya_triplet_sama_sekali(): void
    {
        $this->assertSame(
            TanggalBerkas::KOSONG,
            TanggalBerkas::deteksi([
                new \DateTime('2020-01-15'),
                44000.0,
                '2020-01-15',
                '3201011501200001',
                '',
                null,
            ])
        );
    }

    public function test_deteksi_ambigu_kalau_seluruh_triplet_bisa_dua_arti(): void
    {
        $this->assertSame(
            TanggalBerkas::AMBIGU,
            TanggalBerkas::deteksi(['05/02/2020', '01/12/2020'])
        );
    }

    public function test_deteksi_ambigu_kalau_bukti_dua_arah_seri(): void
    {
        // Satu bukti DMY lawan satu bukti MDY: tak ada mayoritas untuk diikuti.
        $this->assertSame(
            TanggalBerkas::AMBIGU,
            TanggalBerkas::deteksi(['25/02/2020', '2/25/2020'])
        );
    }

    public function test_deteksi_ikut_mayoritas_kalau_bukti_bertabrakan(): void
    {
        // Satu berkas ditulis dengan satu urutan; yang menyimpang hampir pasti
        // salah ketik. Dua bukti DMY lawan satu MDY -> DMY, bukan tolak berkas.
        $this->assertSame(
            TanggalBerkas::DMY,
            TanggalBerkas::deteksi(['25/02/2020', '26/02/2020', '2/25/2020'])
        );

        $this->assertSame(
            TanggalBerkas::MDY,
            TanggalBerkas::deteksi(['2/25/2020', '3/26/2020', '25/02/2020'])
        );
    }

    public function test_contoh_ambigu_mengambil_nilai_nyata_dari_berkas_untuk_pesan_error(): void
    {
        $this->assertSame('05/02/2020', TanggalBerkas::contohAmbigu(['Budi', '25/02/2020', '05/02/2020']));
        $this->assertNull(TanggalBerkas::contohAmbigu(['2020-01-15', '25/02/2020']));
    }

    // ------------------------------------------------------------------ parse

    public function test_parse_mengikuti_format_yang_dipilih(): void
    {
        $this->assertSame('2020-02-05', TanggalBerkas::parse('05/02/2020', TanggalBerkas::DMY));
        $this->assertSame('2020-05-02', TanggalBerkas::parse('05/02/2020', TanggalBerkas::MDY));
    }

    public function test_parse_memperlakukan_strip_sama_dengan_garis_miring(): void
    {
        $this->assertSame('2020-02-05', TanggalBerkas::parse('05-02-2020', TanggalBerkas::DMY));
        $this->assertSame('2020-05-02', TanggalBerkas::parse('05-02-2020', TanggalBerkas::MDY));
    }

    public function test_parse_mengabaikan_jam_di_belakang_tanggal(): void
    {
        $this->assertSame('2020-02-05', TanggalBerkas::parse('05/02/2020 07:30', TanggalBerkas::DMY));
        $this->assertSame('2020-01-15', TanggalBerkas::parse('2020-01-15 00:00:00', TanggalBerkas::DMY));
    }

    public function test_parse_menolak_tanggal_yang_tak_ada_di_kalender(): void
    {
        $this->assertNull(TanggalBerkas::parse('31/02/2020', TanggalBerkas::DMY));
        // 25 dibaca sebagai bulan karena petugas memilih Bulan/Hari/Tahun — tidak ditukar diam-diam.
        $this->assertNull(TanggalBerkas::parse('25/12/2025', TanggalBerkas::MDY));
    }

    public function test_parse_otomatis_membaca_yang_tak_ambigu_dan_menolak_yang_ambigu(): void
    {
        $this->assertSame('2020-02-25', TanggalBerkas::parse('25/02/2020', TanggalBerkas::OTOMATIS));
        $this->assertSame('2020-02-25', TanggalBerkas::parse('2/25/2020', TanggalBerkas::OTOMATIS));
        $this->assertNull(TanggalBerkas::parse('05/02/2020', TanggalBerkas::OTOMATIS));
    }

    #[DataProvider('tanggalTakAmbigu')]
    public function test_bentuk_tak_ambigu_dibaca_sama_apa_pun_formatnya($nilai, string $harapan): void
    {
        foreach ([TanggalBerkas::OTOMATIS, TanggalBerkas::DMY, TanggalBerkas::MDY] as $format) {
            $this->assertSame($harapan, TanggalBerkas::parse($nilai, $format), "format {$format}");
        }
    }

    public static function tanggalTakAmbigu(): array
    {
        return [
            'objek tanggal'  => [new \DateTime('2020-01-15'), '2020-01-15'],
            'serial excel'   => [Date::PHPToExcel(new \DateTime('2020-01-15')), '2020-01-15'],
            'iso'            => ['2020-01-15', '2020-01-15'],
            'iso satu digit' => ['2020-1-5', '2020-01-05'],
            'tahun di depan bergaris miring' => ['2020/01/15', '2020-01-15'],
        ];
    }

    #[DataProvider('bukanTanggal')]
    public function test_nilai_yang_bukan_tanggal_menghasilkan_null($nilai): void
    {
        $this->assertNull(TanggalBerkas::parse($nilai, TanggalBerkas::DMY));
    }

    public static function bukanTanggal(): array
    {
        return [
            'kosong'        => [''],
            'null'          => [null],
            'tanda sudah'   => ['x'],
            'tahun dua digit' => ['05/02/20'],
            'teks bebas'    => ['belum imunisasi'],
        ];
    }
}

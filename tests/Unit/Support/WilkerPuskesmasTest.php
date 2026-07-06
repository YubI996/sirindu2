<?php

namespace Tests\Unit\Support;

use App\Support\WilkerPuskesmas;
use PHPUnit\Framework\TestCase;

/**
 * Menguji logika pemetaan Kelurahan ⇄ Wilker Puskesmas (bagian murni tanpa DB).
 * Fokus: normalisasi nama (Romawi/Arab, prefix "Puskesmas") dan resolusi
 * catchment kelurahan per puskesmas — dasar scoping data surveilans faskes.
 */
class WilkerPuskesmasTest extends TestCase
{
    public function test_normalisasi_romawi_dan_prefix(): void
    {
        // Romawi di akhir → Arab; prefix "Puskesmas" dibuang; uppercase.
        $this->assertSame('BONTANG UTARA 1', WilkerPuskesmas::normalizeName('Bontang Utara I'));
        $this->assertSame('BONTANG UTARA 1', WilkerPuskesmas::normalizeName('Puskesmas Bontang Utara 1'));
        $this->assertSame('BONTANG UTARA 2', WilkerPuskesmas::normalizeName('Bontang Utara II'));
        $this->assertSame('BONTANG SELATAN 2', WilkerPuskesmas::normalizeName('  Puskesmas   Bontang Selatan II '));
        $this->assertSame('BONTANG BARAT', WilkerPuskesmas::normalizeName('Bontang Barat'));
    }

    public function test_lestari_tidak_salah_potong_romawi(): void
    {
        // "LESTARI" berakhiran I tapi bukan angka Romawi standalone → jangan diubah.
        $this->assertSame('BONTANG LESTARI', WilkerPuskesmas::normalizeName('Bontang Lestari'));
    }

    public function test_catchment_bontang_utara_1(): void
    {
        $names = WilkerPuskesmas::catchmentKelurahanNames('Bontang Utara I');
        sort($names);
        $this->assertSame(['API-API', 'BONTANG BARU', 'BONTANG KUALA', 'GUNUNG ELAI'], $names);
    }

    public function test_catchment_lewat_nama_arab_dan_prefix(): void
    {
        // Input "Puskesmas Bontang Selatan 2" (Arab + prefix) harus resolve sama.
        $names = WilkerPuskesmas::catchmentKelurahanNames('Puskesmas Bontang Selatan 2');
        sort($names);
        $this->assertSame(['BERBAS PANTAI', 'BEREBAS TENGAH'], $names);
    }

    public function test_catchment_single_kelurahan(): void
    {
        $this->assertSame(['BONTANG LESTARI'], WilkerPuskesmas::catchmentKelurahanNames('Bontang Lestari'));
    }

    public function test_puskesmas_tak_dikenal_catchment_kosong(): void
    {
        $this->assertSame([], WilkerPuskesmas::catchmentKelurahanNames('Puskesmas Antah Berantah'));
    }

    public function test_semua_kelurahan_terpetakan_ke_enam_puskesmas(): void
    {
        $puskesmas = [
            'Bontang Barat', 'Bontang Utara I', 'Bontang Utara II',
            'Bontang Lestari', 'Bontang Selatan I', 'Bontang Selatan II',
        ];

        $covered = [];
        foreach ($puskesmas as $p) {
            foreach (WilkerPuskesmas::catchmentKelurahanNames($p) as $kel) {
                $covered[$kel] = ($covered[$kel] ?? 0) + 1;
            }
        }

        // Setiap kelurahan kanonik tercakup tepat sekali, total 15.
        $this->assertCount(count(WilkerPuskesmas::KELURAHAN_TO_WILKER), $covered);
        foreach ($covered as $kel => $count) {
            $this->assertSame(1, $count, "Kelurahan {$kel} tercakup lebih dari satu puskesmas");
        }
    }
}

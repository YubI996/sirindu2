<?php

namespace Tests\Unit\Support;

use App\Support\KohortImunisasi;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class KohortImunisasiTest extends TestCase
{
    public function test_rentang_tiap_kelompok_untuk_tahun_2026(): void
    {
        $k = KohortImunisasi::dari(2026);

        $this->assertSame(['2026-02-01', '2026-03-31'], $k->rentang('BBL'));
        $this->assertSame(['2025-04-01', '2026-01-31'], $k->rentang('SI'));
        $this->assertSame(['2025-04-01', '2026-03-31'], $k->rentang('SELURUH'));
        $this->assertSame(['2024-04-01', '2025-03-31'], $k->rentang('BADUTA'));
    }

    public function test_anak_lahir_hari_pertama_kohort_masuk_si_bukan_terbuang(): void
    {
        // Lahir 1 Apr 2025 berumur 11 bln 30 hr pada 31 Mar 2026. Membaca
        // "11 bln 29 hr" harfiah akan membuang hari pertama kohortnya sendiri.
        $this->assertSame(['SI', 'SELURUH'], KohortImunisasi::dari(2026)->kelompokDari('2025-04-01'));
    }

    public function test_keempat_batas_tanggal_jatuh_di_kelompok_yang_benar(): void
    {
        $k = KohortImunisasi::dari(2026);

        $this->assertSame(['SI', 'SELURUH'],  $k->kelompokDari('2026-01-31'), 'Hari terakhir SI.');
        $this->assertSame(['BBL', 'SELURUH'], $k->kelompokDari('2026-02-01'), 'Hari pertama BBL.');
        $this->assertSame(['BBL', 'SELURUH'], $k->kelompokDari('2026-03-31'), 'Hari terakhir kohort.');
        $this->assertSame(['BADUTA'],         $k->kelompokDari('2025-03-31'), 'Hari terakhir kohort 2025.');
    }

    public function test_bbl_dan_si_partisi_bersih_tanpa_tumpang_tindih(): void
    {
        $k = KohortImunisasi::dari(2026);

        foreach (['2025-04-01', '2025-09-15', '2026-01-31', '2026-02-01', '2026-03-31'] as $tgl) {
            $anggota = array_intersect($k->kelompokDari($tgl), ['BBL', 'SI']);
            $this->assertCount(1, $anggota, "Tanggal {$tgl} harus masuk tepat satu dari BBL/SI.");
        }
    }

    public function test_tanggal_lahir_kabisat_29_februari(): void
    {
        $this->assertSame(['BBL', 'SELURUH'], KohortImunisasi::dari(2024)->kelompokDari('2024-02-29'));
        $this->assertSame(['BADUTA'],         KohortImunisasi::dari(2025)->kelompokDari('2024-02-29'));
    }

    public function test_tanggal_di_luar_semua_kelompok(): void
    {
        $k = KohortImunisasi::dari(2026);

        $this->assertSame([], $k->kelompokDari('2024-03-31'), 'Sebelum kohort Baduta.');
        $this->assertSame([], $k->kelompokDari('2026-04-01'), 'Sesudah kohort berjalan.');
        $this->assertSame([], $k->kelompokDari('2030-01-01'), 'Tanggal masa depan salah ketik.');
    }

    public function test_pilihan_tahun_lima_tahun_menurun(): void
    {
        $this->assertSame([2026, 2025, 2024, 2023, 2022], KohortImunisasi::pilihanTahun(2026));
    }

    public function test_tahun_tervalidasi_mengembalikan_default_untuk_input_ngawur(): void
    {
        foreach ([null, '', 'abc', '0', '-5', '2099', '2026.5', '1999'] as $input) {
            $this->assertSame(
                2026,
                KohortImunisasi::tahunTervalidasi($input, 2026),
                'Input ngawur harus jatuh ke default, bukan melempar atau membuat kohort aneh.'
            );
        }

        $this->assertSame(2024, KohortImunisasi::tahunTervalidasi('2024', 2026));
        $this->assertSame(2024, KohortImunisasi::tahunTervalidasi(2024, 2026));
    }

    public function test_kelompok_tak_dikenal_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        KohortImunisasi::dari(2026)->rentang('BALITA');
    }

    public function test_tahun_tak_masuk_akal_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        KohortImunisasi::dari(1900);
    }

    public function test_label_menyebut_periode_dan_potret(): void
    {
        $this->assertSame(
            'Kohort 2026 · lahir 1 Apr 2025 – 31 Mar 2026 · potret umur 31 Mar 2026',
            KohortImunisasi::dari(2026)->label()
        );
    }
}

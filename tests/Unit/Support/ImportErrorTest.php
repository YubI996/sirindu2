<?php

namespace Tests\Unit\Support;

use App\Support\ImportError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ImportErrorTest extends TestCase
{
    #[DataProvider('pesanDatabase')]
    public function test_pesan_menyebut_kolom_dan_penyebab_tanpa_sql(string $driver, string $expected): void
    {
        $message = $driver.' (Connection: mysql, SQL: insert into `anak` (`nama`) values (RAHASIA))';
        $this->assertSame($expected, ImportError::message($message));
    }

    public static function pesanDatabase(): array
    {
        return [
            ['SQLSTATE[22001]: String data, right truncated: 1406 Data too long for column \'no_hp\' at row 1', 'Data terlalu panjang (kolom: no_hp).'],
            ["Incorrect date value: 'rusak' for column 'tgl_lahir' at row 1", 'Format tanggal tidak valid (kolom: tgl_lahir).'],
            ["Incorrect datetime value: 'rusak' for column 'created_at' at row 1", 'Format tanggal tidak valid (kolom: created_at).'],
            ["Incorrect integer value: 'rusak' for column 'anak' at row 1", 'Format angka tidak valid (kolom: anak).'],
            ['Incorrect decimal value: \'rusak\' for column `bb` at row 1', 'Format angka tidak valid (kolom: bb).'],
            ['Out of range value for column "usia_kehamilan_lahir" at row 1', 'Angka di luar rentang yang diizinkan (kolom: usia_kehamilan_lahir).'],
            ["SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'nama' cannot be null", 'Data wajib belum diisi (kolom: nama).'],
            ["Field 'tgl_lahir' doesn't have a default value", 'Data wajib belum diisi (kolom: tgl_lahir).'],
            ["Data truncated for column 'status_kasus' at row 1", 'Nilai tidak sesuai pilihan atau format yang diizinkan (kolom: status_kasus).'],
            ['Integrity constraint violation: 1452 Cannot add or update a child row: a foreign key constraint fails (`db`.`data_anak`, CONSTRAINT `data_anak_id_anak_foreign` FOREIGN KEY (`id_anak`) REFERENCES `anak` (`id`))', 'Data referensi tidak ditemukan atau masih digunakan (kolom: id_anak).'],
            ["Integrity constraint violation: 1062 Duplicate entry 'RAHASIA' for key 'anak.nik_unique'", 'Data sudah digunakan oleh baris lain (kunci: anak.nik_unique).'],
            ['SQLSTATE[HY000]: General error: connection lost', 'Gagal menyimpan data; periksa isian baris ini.'],
        ];
    }
}

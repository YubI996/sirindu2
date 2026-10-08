<?php

namespace Tests\Unit\Models;

use App\Models\ImportLog;
use Tests\TestCase;

class ImportLogUrutanPesanTest extends TestCase
{
    public function test_pesan_diurutkan_error_peringatan_lalu_info_dengan_urutan_baris_tetap(): void
    {
        $log = new ImportLog(['failures' => [
            '[INFO] Baris 2 (A): NIK kosong.',
            '[ERROR] Baris 9 (B): nama kosong.',
            '[PERINGATAN] Baris 4 (C): periksa salah ketik.',
            '[ERROR] Baris 3 (D): Data terlalu panjang.',
            '[INFO] Baris 1 (E): dicocokkan.',
            '[PERINGATAN] Kelurahan "X" tidak cocok.',
        ]]);

        $this->assertSame([
            '[ERROR] Baris 9 (B): nama kosong.',
            '[ERROR] Baris 3 (D): Data terlalu panjang.',
            '[PERINGATAN] Baris 4 (C): periksa salah ketik.',
            '[PERINGATAN] Kelurahan "X" tidak cocok.',
            '[INFO] Baris 2 (A): NIK kosong.',
            '[INFO] Baris 1 (E): dicocokkan.',
        ], $log->failures);
    }

    public function test_baris_tanpa_tanda_seperti_ringkasan_tetap_paling_atas(): void
    {
        $log = new ImportLog(['failures' => [
            'Ringkasan: 10 berhasil, 2 gagal.',
            '[INFO] Baris 2: info.',
            '[ERROR] Baris 5: error.',
        ]]);

        $this->assertSame([
            'Ringkasan: 10 berhasil, 2 gagal.',
            '[ERROR] Baris 5: error.',
            '[INFO] Baris 2: info.',
        ], $log->failures);
    }

    public function test_log_lama_yang_tersimpan_tak_terurut_ikut_terurut_saat_dibaca(): void
    {
        $log = (new ImportLog)->setRawAttributes([
            'failures' => json_encode(['[INFO] i', '[ERROR] e', '[PERINGATAN] p']),
        ]);

        $this->assertSame(['[ERROR] e', '[PERINGATAN] p', '[INFO] i'], $log->failures);
    }

    public function test_failures_kosong_tetap_null(): void
    {
        $this->assertNull((new ImportLog(['failures' => null]))->failures);
    }
}

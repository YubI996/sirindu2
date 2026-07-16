<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Selaraskan akun petugas RS dengan master RS yang sudah dikoreksi
 * (lihat 2026_07_16_000001_fix_rumah_sakit_master_amalia_badak).
 *
 * Master RS-003/RS-005 semula bernama Siloam/Pertamina — RS yang tidak ada di
 * Bontang. Setelah master dikoreksi ke RS Amalia & RS Badak LNG (id 3 & 5
 * dipertahankan), akun petugas yang menunjuk ke id_rs tersebut ikut disesuaikan
 * agar nama & email tidak menyesatkan.
 *
 * Idempoten: hanya menyentuh akun yang masih memakai email lama.
 */
return new class extends Migration
{
    /** email lama => [email baru, nama baru] */
    private const KOREKSI = [
        'rs.siloam@sirindu.go.id' => [
            'email' => 'rs.amalia@sirindu.go.id',
            'name'  => 'Petugas RS Amalia Bontang',
        ],
        'rs.pertamina@sirindu.go.id' => [
            'email' => 'rs.badaklng@sirindu.go.id',
            'name'  => 'Petugas RS Badak LNG',
        ],
    ];

    public function up(): void
    {
        foreach (self::KOREKSI as $emailLama => $baru) {
            DB::table('users')
                ->where('email', $emailLama)
                ->update($baru + ['updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $lamaByBaru = [
            'rs.amalia@sirindu.go.id'   => ['email' => 'rs.siloam@sirindu.go.id',    'name' => 'Petugas RS Siloam Bontang'],
            'rs.badaklng@sirindu.go.id' => ['email' => 'rs.pertamina@sirindu.go.id', 'name' => 'Petugas RS Pertamina Bontang'],
        ];

        foreach ($lamaByBaru as $emailBaru => $lama) {
            DB::table('users')
                ->where('email', $emailBaru)
                ->update($lama + ['updated_at' => now()]);
        }
    }
};

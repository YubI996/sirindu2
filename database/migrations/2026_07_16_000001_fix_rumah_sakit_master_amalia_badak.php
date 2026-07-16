<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Koreksi master Rumah Sakit agar sesuai realita Kota Bontang.
 *
 * Master di DB terisi keliru: RS-003 = "RS Siloam Bontang" dan RS-005 =
 * "RS Pertamina Bontang" — keduanya TIDAK ada di Bontang. Yang nyata adalah
 * RS Amalia dan RS Badak LNG (RumahSakitSeeder sudah benar; DB & RoleUserSeeder
 * yang menyimpang).
 *
 * Dampaknya ke bug visibilitas RS: instansi_pelapor "RS Amalia" (42 kasus) dan
 * "RS LNG Badak" (21 kasus) tidak punya padanan di master, sehingga kasusnya tak
 * bisa diatribusikan ke RS mana pun dan tak pernah terlihat user surveilans_rs.
 *
 * Nama dikoreksi DI TEMPAT (id 3 & 5 dipertahankan) agar users.id_rs yang sudah
 * menunjuk ke sana tetap valid. Idempoten: hanya menyentuh baris yang masih keliru.
 */
return new class extends Migration
{
    /** kode_rs => [nama keliru saat ini, data yang benar] */
    private const KOREKSI = [
        'RS-003' => [
            'salah' => 'RS Siloam Bontang',
            'benar' => [
                'name'     => 'RS Amalia Bontang',
                'alamat'   => 'Jl. MT. Haryono, Bontang Utara',
                'telepon'  => '(0548) 25555',
                'jenis_rs' => 'RS Swasta',
            ],
        ],
        'RS-005' => [
            'salah' => 'RS Pertamina Bontang',
            'benar' => [
                'name'     => 'RS Badak LNG',
                'alamat'   => 'Komplek LNG Badak, Bontang Utara',
                'telepon'  => '(0548) 41500',
                'jenis_rs' => 'RS Swasta',
            ],
        ],
    ];

    public function up(): void
    {
        foreach (self::KOREKSI as $kode => $item) {
            DB::table('rumah_sakits')
                ->where('kode_rs', $kode)
                ->where('name', $item['salah'])
                ->update($item['benar'] + ['updated_at' => now()]);
        }
    }

    /**
     * Mengembalikan nama yang keliru (Siloam/Pertamina) — hanya demi reversibilitas
     * migrasi; nama tersebut memang tidak mencerminkan RS nyata di Bontang.
     */
    public function down(): void
    {
        foreach (self::KOREKSI as $kode => $item) {
            DB::table('rumah_sakits')
                ->where('kode_rs', $kode)
                ->where('name', $item['benar']['name'])
                ->update(['name' => $item['salah'], 'updated_at' => now()]);
        }
    }
};

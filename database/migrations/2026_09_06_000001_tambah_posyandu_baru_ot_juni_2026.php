<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Enam posyandu punya anak di berkas Operasi Timbang Juni 2026 (±366 baris)
 * tetapi tidak pernah ada di master `posyandu`, sehingga id_posyandu-nya NULL
 * dan datanya tak terlihat di dashboard mana pun.
 *
 * Master prod berasal dari PosyanduTableSeeder yang hanya jalan sekali, jadi
 * penambahannya lewat migration — mengubah seeder tidak akan menyentuh server
 * yang sudah ter-seed.
 *
 * Nama & puskesmas induk diambil dari kolom Posyandu + Pukesmas berkas itu
 * sendiri, dikonfirmasi klien (Dinkes) sebelum ditambahkan.
 */
return new class extends Migration
{
    /** Nama posyandu => nama puskesmas induk (sesuai berkas OT Juni 2026). */
    private const BARU = [
        'Sejahtera Etam' => 'Bontang Utara 2',   // kel. Guntung
        'Menur 1'        => 'Bontang Utara 1',   // kel. Gunung Elai
        'Nusa Indah 3'   => 'Bontang Selatan 1', // kel. Satimpo
        'Sekatup'        => 'Bontang Utara 1',   // kel. Gunung Elai
        'Pasir Putih 11' => 'Bontang Lestari',   // kel. Bontang Lestari
        'Mawar Merah'    => 'Bontang Utara 2',   // kel. Lok Tuan
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::BARU as $nama => $puskesmas) {
            // Idempoten: nama kembar dalam satu puskesmas justru membuat
            // FaskesMatcher menyerah ('ambigu') dan data kembali tak terlihat.
            if (DB::table('posyandu')->where('name', $nama)->exists()) {
                continue;
            }

            $idPuskesmas = DB::table('puskesmas')->where('name', $puskesmas)->value('id');
            if ($idPuskesmas === null) {
                continue; // master puskesmas belum ada — jangan tebak induknya
            }

            DB::table('posyandu')->insert([
                'name'         => $nama,
                'id_puskesmas' => $idPuskesmas,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::BARU) as $nama) {
            $id = DB::table('posyandu')->where('name', $nama)->value('id');
            if ($id === null) {
                continue;
            }

            // Jangan tinggalkan anak menunjuk posyandu yang sudah tak ada.
            if (DB::table('anak')->where('id_posyandu', $id)->exists()) {
                continue;
            }

            DB::table('posyandu')->where('id', $id)->delete();
        }
    }
};

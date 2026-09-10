<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dua salah tulis di data induk posyandu, ketahuan saat mencocokkan daftar
 * posyandu berkas OT Juni 2026 dengan isi server (9.884 baris).
 *
 * 1. `Cendana` terdaftar di Bontang Utara 1, padahal ke-43 anaknya berada di
 *    Kelurahan LOK TUAN — wilayah Bontang Utara 2. Dua baris lain di Lok Tuan
 *    (`Anggrek Putih`, dan `Flamboyan 2` setelah koreksi Dinkes) memang sudah
 *    di Bontang Utara 2, jadi Cendana yang meleset.
 *
 * 2. `Nisa Indah` @ Bontang Utara 1 tak pernah menerima satu anak pun,
 *    sementara 31 anak yang menulis "nusa indah" di puskesmas yang sama nyasar
 *    ke `Nusa Indah` milik Bontang Utara 2 — lolos lewat tahap "global-unik"
 *    (nama identik dan waktu itu unik se-master). "Nisa" salah ketik "Nusa".
 *
 * Diperbaiki di data induk, bukan lewat berkas keputusan, karena nama dan
 * puskesmas posyandu TAMPIL di layar petugas: membiarkannya salah berarti
 * membiarkan petugas membaca yang keliru walau anaknya sudah mendarat benar.
 *
 * Setiap langkah menolak bekerja bila hasilnya akan MELAHIRKAN nama kembar
 * dalam satu puskesmas — nama kembar itulah yang membuat matcher menyerah dan
 * datanya lenyap dari dashboard.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->pindahkanCendana('Bontang Utara 1', 'Bontang Utara 2');
        $this->namaiUlang('Bontang Utara 1', 'Nisa Indah', 'Nusa Indah');
    }

    public function down(): void
    {
        $this->pindahkanCendana('Bontang Utara 2', 'Bontang Utara 1');
        $this->namaiUlang('Bontang Utara 1', 'Nusa Indah', 'Nisa Indah');
    }

    private function pindahkanCendana(string $dariNama, string $keNama): void
    {
        $dari = DB::table('puskesmas')->where('name', $dariNama)->value('id');
        $ke   = DB::table('puskesmas')->where('name', $keNama)->value('id');

        if ($dari === null || $ke === null) {
            return;
        }

        $baris = DB::table('posyandu')
            ->where('name', 'Cendana')
            ->where('id_puskesmas', $dari)
            ->pluck('id');

        // Nol = sudah pindah. Lebih dari satu = tak jelas yang mana; menebak di
        // sini justru menambah kerusakan yang sedang dibereskan.
        if ($baris->count() !== 1) {
            return;
        }

        if (DB::table('posyandu')->where('name', 'Cendana')->where('id_puskesmas', $ke)->exists()) {
            return;
        }

        $id = (int) $baris[0];

        DB::table('posyandu')->where('id', $id)->update([
            'id_puskesmas' => $ke,
            'updated_at'   => now(),
        ]);

        // `anak` menyimpan id_puskesmas terpisah dari id_posyandu, jadi ia tidak
        // ikut sendiri. Hanya yang tadinya SELARAS yang dibetulkan; baris yang
        // sudah menyimpang punya sebab sendiri dan bukan urusan migration ini.
        DB::table('anak')
            ->where('id_posyandu', $id)
            ->where('id_puskesmas', $dari)
            ->update([
                'id_puskesmas' => $ke,
                'updated_at'   => now(),
            ]);
    }

    /**
     * Selalu terikat pada SATU puskesmas.
     *
     * `Nusa Indah` yang asli hidup di Bontang Utara 2 dan tak pernah disentuh
     * up(); tanpa batasan ini down() akan ikut menamainya `Nisa Indah` — membuat
     * posyandu yang sehat justru salah nama demi membatalkan koreksi tetangganya.
     */
    private function namaiUlang(string $namaPuskesmas, string $dari, string $ke): void
    {
        $idPus = DB::table('puskesmas')->where('name', $namaPuskesmas)->value('id');
        if ($idPus === null) {
            return;
        }

        $baris = DB::table('posyandu')
            ->where('name', $dari)
            ->where('id_puskesmas', $idPus)
            ->pluck('id');

        // Nol = sudah dinamai ulang. Lebih dari satu = tak jelas yang mana.
        if ($baris->count() !== 1) {
            return;
        }

        if (DB::table('posyandu')->where('name', $ke)->where('id_puskesmas', $idPus)->exists()) {
            return;
        }

        DB::table('posyandu')->where('id', $baris[0])->update([
            'name'       => $ke,
            'updated_at' => now(),
        ]);
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menuntaskan koreksi "Anggrek" yang batal di produksi.
 *
 * Migration 2026_09_09_000001 memilih baris ber-id LEBIH BESAR untuk dinamai
 * ulang jadi "Anggrek1", lalu menolak menyentuhnya kalau sudah dipakai anak.
 * Di produksi keduanya bertabrakan: matcher LAMA memakai `pluck('id','name')`,
 * yang untuk nama kembar hanya menyisakan id TERAKHIR, jadi seluruh anak
 * "ANGGREK" justru menumpuk di baris yang hendak dinamai ulang. Penjaganya
 * bekerja sebagaimana mestinya, penamaannya batal, "Anggrek1" tak pernah lahir
 * — dan 178 baris berkas Juni 2026 tetap menggantung meski keputusan Dinkes
 * sudah ada (91 menyebut "Anggrek1" yang tak ada, 87 menyebut "Anggrek" yang
 * ambigu).
 *
 * Yang perlu diperbaiki bukan penjaganya, melainkan pilihan barisnya. Nama mana
 * menempel ke baris mana memang sembarang: data induk tidak menyimpan kelurahan,
 * jadi kedua baris tak punya identitas sendiri untuk dipertahankan. Maka urutan
 * pilihannya dibalik menjadi:
 *
 *   1. baris yang belum dipakai anak mana pun — nol risiko;
 *   2. baris yang isinya HANYA anak Operasi Timbang — mereka disortir ulang oleh
 *      `posyandu:backfill-ot` tepat setelah ini, mengikuti kelurahan pada berkas;
 *   3. selain itu tidak disentuh — memindahkan anak Capil/entri manual berarti
 *      kerusakan senyap, persis yang sedang diperbaiki.
 *
 * Di antara baris yang sama-sama aman, yang ber-id lebih besar tetap dipilih
 * supaya pemasangan baru berperilaku sama dengan migration sebelumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        $idBarat = DB::table('puskesmas')->where('name', 'Bontang Barat')->value('id');
        if ($idBarat === null) {
            return;
        }

        // Sudah tuntas — baik oleh migration sebelumnya maupun oleh jalannya ini.
        if (DB::table('posyandu')->where('name', 'Anggrek1')->exists()) {
            return;
        }

        $baris = DB::table('posyandu')
            ->where('name', 'Anggrek')
            ->where('id_puskesmas', $idBarat)
            ->orderBy('id')
            ->pluck('id');

        if ($baris->count() < 2) {
            return;
        }

        $aman = [];
        foreach ($baris as $id) {
            $anak = DB::table('anak')->where('id_posyandu', $id);

            // `sumber` NULL bukan anak Operasi Timbang, jadi ikut menghalangi.
            $adaLuarOt = (clone $anak)
                ->where(fn ($q) => $q->where('sumber', '!=', 'operasi_timbang')->orWhereNull('sumber'))
                ->exists();

            if ($adaLuarOt) {
                continue;
            }

            $aman[] = ['id' => (int) $id, 'kosong' => ! $anak->exists()];
        }

        if ($aman === []) {
            return;
        }

        // Yang belum berisi anak lebih dulu; di antara yang setara, id terbesar.
        usort($aman, fn (array $a, array $b) => [$b['kosong'], $b['id']] <=> [$a['kosong'], $a['id']]);

        DB::table('posyandu')->where('id', $aman[0]['id'])->update([
            'name'       => 'Anggrek1',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $idBarat = DB::table('puskesmas')->where('name', 'Bontang Barat')->value('id');
        if ($idBarat === null) {
            return;
        }

        DB::table('posyandu')
            ->where('name', 'Anggrek1')
            ->where('id_puskesmas', $idBarat)
            ->update(['name' => 'Anggrek', 'updated_at' => now()]);
    }
};

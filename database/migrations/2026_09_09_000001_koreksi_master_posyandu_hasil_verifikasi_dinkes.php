<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Koreksi data induk posyandu hasil verifikasi Dinas Kesehatan (September 2026).
 *
 * Dua temuan dari penelusuran berkas Operasi Timbang Juni 2026:
 *
 * 1. DUA baris bernama "Anggrek" di Puskesmas Bontang Barat. Namanya sama
 *    persis dan data induk tidak menyimpan kelurahan, jadi tak ada satu pun
 *    keterangan yang membedakannya — matcher menyerah dan 178 anak menggantung.
 *    Dinkes menetapkan: "Anggrek" melayani Kelurahan Belimbing, "Anggrek1"
 *    melayani Gunung Telihan (mengikuti penulisan di berkas e-PPGBM).
 *    Baris ber-id lebih besar yang dinamai ulang; pilihan itu sembarang tetapi
 *    TIDAK berbahaya selama keduanya belum dipakai anak mana pun.
 *
 * 2. "Flamboyan 2" tercatat di Bontang Utara 1, sedangkan berkas menyebut
 *    Bontang Utara II. Dinkes menegaskan data induk yang keliru.
 *
 * Keduanya dijaga: baris yang SUDAH dipakai anak tidak disentuh. Menamai ulang
 * posyandu yang sudah berisi anak berarti memindahkan mereka tanpa sepengetahuan
 * petugas — persis jenis kerusakan senyap yang sedang kita perbaiki.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->namaiUlangAnggrek();
        $this->pindahkanFlamboyan2('Bontang Utara 2');
    }

    public function down(): void
    {
        DB::table('posyandu')->where('name', 'Anggrek1')->update(['name' => 'Anggrek']);
        $this->pindahkanFlamboyan2('Bontang Utara 1');
    }

    private function namaiUlangAnggrek(): void
    {
        $idBarat = DB::table('puskesmas')->where('name', 'Bontang Barat')->value('id');
        if ($idBarat === null) {
            return;
        }

        $anggrek = DB::table('posyandu')
            ->where('name', 'Anggrek')
            ->where('id_puskesmas', $idBarat)
            ->orderBy('id')
            ->get(['id']);

        // Sudah dikoreksi (atau memang cuma satu) — tak ada yang perlu dikerjakan.
        if ($anggrek->count() < 2) {
            return;
        }

        // Baris pertama tetap "Anggrek" (Belimbing); sisanya jadi "Anggrek1".
        foreach ($anggrek->slice(1) as $baris) {
            $dipakai = DB::table('anak')->where('id_posyandu', $baris->id)->exists();
            if ($dipakai) {
                continue;
            }

            DB::table('posyandu')->where('id', $baris->id)->update([
                'name'       => 'Anggrek1',
                'updated_at' => now(),
            ]);
        }
    }

    private function pindahkanFlamboyan2(string $namaPuskesmas): void
    {
        $idPuskesmas = DB::table('puskesmas')->where('name', $namaPuskesmas)->value('id');
        if ($idPuskesmas === null) {
            return; // master puskesmas belum ada — jangan tebak induknya
        }

        DB::table('posyandu')
            ->where('name', 'Flamboyan 2')
            ->update(['id_puskesmas' => $idPuskesmas, 'updated_at' => now()]);
    }
};

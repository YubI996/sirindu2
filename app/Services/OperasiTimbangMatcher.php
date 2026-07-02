<?php

namespace App\Services;

use App\Models\Anak;

/**
 * Cocokkan satu baris operasi timbang (e-PPGBM) ke record Anak yang sudah ada.
 *
 * NIK e-PPGBM disensor & tidak punya daya pembeda, jadi diabaikan. Pencocokan:
 * tgl_lahir + jk exact → fuzzy nama (similar_text >= ambang) → tie-break Nama Ortu.
 */
class OperasiTimbangMatcher
{
    public function __construct(private int $minNama = 88) {}

    /**
     * @return array{status:string, anak:?Anak, kandidat:array, alasan:?string}
     */
    public function match(string $nama, ?string $tglLahir, ?string $jk, ?string $namaOrtu = null): array
    {
        if (empty($tglLahir)) {
            return ['status' => 'TAK_COCOK', 'anak' => null, 'kandidat' => [], 'alasan' => 'Tanggal lahir kosong/tidak valid — tidak dapat dicocokkan.'];
        }

        $jkValue = strtoupper(trim((string) $jk)) === 'L' ? 1 : 2;

        $kandidat = Anak::whereDate('tgl_lahir', $tglLahir)
            ->where('jk', $jkValue)
            ->get(['id', 'nama', 'tgl_lahir', 'nama_ibu', 'nama_ayah']);

        // Saring berdasarkan kemiripan nama.
        $lolos = $kandidat->filter(fn ($a) => $this->mirip($nama, $a->nama))->values();

        if ($lolos->isEmpty()) {
            return ['status' => 'TAK_COCOK', 'anak' => null, 'kandidat' => [], 'alasan' => 'Tidak ada anak dengan nama mirip pada tgl lahir & jenis kelamin yang sama.'];
        }

        if ($lolos->count() === 1) {
            return ['status' => 'COCOK', 'anak' => $lolos->first(), 'kandidat' => $lolos->all(), 'alasan' => null];
        }

        // >1 kandidat → tie-break Nama Ortu.
        if (!empty($namaOrtu)) {
            $ortuTerpilih = $lolos->filter(fn ($a) => $this->cocokOrtu($namaOrtu, $a))->values();
            if ($ortuTerpilih->count() === 1) {
                return ['status' => 'COCOK', 'anak' => $ortuTerpilih->first(), 'kandidat' => $ortuTerpilih->all(), 'alasan' => null];
            }
        }

        return ['status' => 'AMBIGU', 'anak' => null, 'kandidat' => $lolos->all(), 'alasan' => "Ditemukan {$lolos->count()} anak mirip; tidak dapat dibedakan otomatis."];
    }

    private function mirip(string $a, string $b): bool
    {
        similar_text(strtoupper(trim($a)), strtoupper(trim($b)), $pct);
        return $pct >= $this->minNama;
    }

    /** Nama Ortu e-PPGBM bisa "AYAH / IBU"; cocokkan tiap bagian ke nama_ibu/nama_ayah. */
    private function cocokOrtu(string $namaOrtu, Anak $a): bool
    {
        $bagian = array_filter(array_map('trim', explode('/', $namaOrtu)));
        $target = array_filter([$a->nama_ibu, $a->nama_ayah]);
        foreach ($bagian as $o) {
            foreach ($target as $t) {
                if ($this->mirip($o, (string) $t)) return true;
            }
        }
        return false;
    }
}

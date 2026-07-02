<?php

namespace App\Services;

use App\Models\Anak;

/**
 * Cocokkan satu baris operasi timbang (e-PPGBM) ke record Anak yang sudah ada.
 *
 * NIK e-PPGBM disensor & tidak punya daya pembeda, jadi diabaikan. Gerbang
 * kandidat: tgl_lahir + jk exact, lalu fuzzy nama (similar_text >= ambang).
 * Bila kandidat >1, dibedakan (tie-break) pakai Nama Ortu (swap ibu/ayah &
 * versi digabung ditoleransi) dan Kelurahan. Kelurahan/ortu bukan gerbang
 * keras karena banyak record sigizi domisilinya kosong.
 */
class OperasiTimbangMatcher
{
    public function __construct(private int $minNama = 88) {}

    /**
     * @return array{status:string, anak:?Anak, kandidat:array, alasan:?string}
     */
    public function match(
        string $nama,
        ?string $tglLahir,
        ?string $jk,
        ?string $namaOrtu = null,
        ?string $kelurahan = null,
    ): array {
        if (empty($tglLahir)) {
            return ['status' => 'TAK_COCOK', 'anak' => null, 'kandidat' => [], 'alasan' => 'Tanggal lahir kosong/tidak valid — tidak dapat dicocokkan.'];
        }

        $jkValue = strtoupper(trim((string) $jk)) === 'L' ? 1 : 2;

        // Gerbang kandidat: tgl_lahir + jk exact. Sertakan nama kelurahan (via id_kel)
        // untuk pembeda; left join agar anak tanpa domisili tetap ikut.
        $kandidat = Anak::query()
            ->leftJoin('kelurahan', 'anak.id_kel', '=', 'kelurahan.id')
            ->whereDate('anak.tgl_lahir', $tglLahir)
            ->where('anak.jk', $jkValue)
            ->get(['anak.id', 'anak.nama', 'anak.tgl_lahir', 'anak.nama_ibu', 'anak.nama_ayah', 'kelurahan.name as kel_name']);

        // Saring berdasarkan kemiripan nama.
        $lolos = $kandidat->filter(fn ($a) => $this->mirip($nama, $a->nama))->values();

        if ($lolos->isEmpty()) {
            return ['status' => 'TAK_COCOK', 'anak' => null, 'kandidat' => [], 'alasan' => 'Tidak ada anak dengan nama mirip pada tgl lahir & jenis kelamin yang sama.'];
        }

        if ($lolos->count() === 1) {
            return ['status' => 'COCOK', 'anak' => $lolos->first(), 'kandidat' => $lolos->all(), 'alasan' => null];
        }

        // >1 kandidat → tie-break: skor per kandidat dari kecocokan ortu + kelurahan.
        $scored = $lolos->map(fn ($a) => [
            'anak' => $a,
            'skor' => (!empty($namaOrtu) && $this->cocokOrtu($namaOrtu, $a) ? 1 : 0)
                    + (!empty($kelurahan) && !empty($a->kel_name) && $this->mirip($kelurahan, $a->kel_name) ? 1 : 0),
        ]);

        $maxSkor = (int) $scored->max('skor');
        if ($maxSkor >= 1) {
            $teratas = $scored->where('skor', $maxSkor)->values();
            if ($teratas->count() === 1) {
                return ['status' => 'COCOK', 'anak' => $teratas->first()['anak'], 'kandidat' => $lolos->all(), 'alasan' => null];
            }
        }

        return ['status' => 'AMBIGU', 'anak' => null, 'kandidat' => $lolos->all(), 'alasan' => "Ditemukan {$lolos->count()} anak mirip; tidak dapat dibedakan otomatis."];
    }

    private function mirip(string $a, string $b): bool
    {
        similar_text($this->norm($a), $this->norm($b), $pct);
        return $pct >= $this->minNama;
    }

    private function norm(string $s): string
    {
        return strtoupper(trim($s));
    }

    /**
     * Cocokkan Nama Ortu file dengan nama_ibu/nama_ayah kandidat.
     * Toleran urutan tertukar (bandingkan tiap bagian ke kedua field) dan
     * versi digabung (bandingkan string utuh ke gabungan ibu+ayah).
     */
    private function cocokOrtu(string $namaOrtu, Anak $a): bool
    {
        $bagian = array_filter(array_map('trim', preg_split('#[/,;]+#', $namaOrtu)));
        $target = array_filter([$a->nama_ibu, $a->nama_ayah]);

        // Per-bagian × per-field (toleran ibu/ayah tertukar).
        foreach ($bagian as $o) {
            foreach ($target as $t) {
                if ($this->mirip($o, (string) $t)) return true;
            }
        }

        // Digabung: string ortu utuh vs gabungan ibu+ayah (dua urutan).
        if (!empty($target)) {
            $combo1 = trim(($a->nama_ibu ?? '') . ' ' . ($a->nama_ayah ?? ''));
            $combo2 = trim(($a->nama_ayah ?? '') . ' ' . ($a->nama_ibu ?? ''));
            foreach ([$combo1, $combo2] as $c) {
                if ($c !== '' && $this->mirip($namaOrtu, $c)) return true;
            }
        }

        return false;
    }
}

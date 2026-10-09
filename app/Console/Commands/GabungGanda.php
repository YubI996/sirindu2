<?php

namespace App\Console\Commands;

use App\Models\Anak;
use App\Models\AnakKandidat;
use App\Models\AnakTautan;
use App\Services\IdentitasMergeService;
use App\Services\NikDummyService;
use App\Services\PenautanAnakImport;
use App\Services\TautanIdentitasService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * DRY-RUN penggabungan anak ganda: memilah kandidat hasil pindai (anak_kandidat) menjadi
 * AMAN (boleh digabung massal) dan TINJAU (wajib diputuskan manual di tab "Dicurigai sama"),
 * lalu menulis CSV untuk diperiksa klien. TIDAK mengubah data apa pun.
 *
 * AMAN hanya bila SEMUA terpenuhi: tgl lahir & jk sama, nama sama persis (abaikan spasi/tanda
 * baca/besar-kecil), No KK sama dan terisi, tidak ada dua NIK asli yang berbeda, nama ibu tidak
 * jelas berbeda / tidak bertanda kembar (aturan PenautanAnakImport), dan kedua anak hanya muncul
 * di pasangan ini. Kriteria ini sengaja lebih ketat dari matcher: salah gabung anak kembar
 * menghapus satu anak dan mencampur riwayat timbangnya tanpa error.
 */
class GabungGanda extends Command
{
    protected $signature = 'anak:gabung-ganda
        {--pindai : Jalankan identitas:pindai lebih dulu (isi ulang anak_kandidat)}';

    protected $description = 'DRY-RUN: pilah anak ganda jadi AMAN digabung massal vs TINJAU manual, tulis CSV (tanpa mengubah data)';

    private const KOLOM_ANAK = ['id', 'sumber', 'nik', 'no_kk', 'nama', 'jk', 'tgl_lahir', 'nama_ibu', 'nama_ayah', 'kelurahan', 'posyandu', 'kunjungan', 'imunisasi', 'dibuat'];

    public function handle(TautanIdentitasService $tautan, IdentitasMergeService $merge): int
    {
        if ($this->option('pindai')) {
            $r = $tautan->pindai();
            $this->info("Pindai: {$r['pasangan']} pasangan kandidat ({$r['dipindai_at']}).");
        }

        $dipindai = AnakKandidat::max('dipindai_at');
        if (!$dipindai) {
            $this->warn('anak_kandidat kosong. Jalankan dulu: php artisan anak:gabung-ganda --pindai');
            return self::SUCCESS;
        }

        $kandidat = $tautan->kandidatDicurigaiQuery()->setEagerLoads([])->get(['id_anak_a', 'id_anak_b', 'via', 'skor']);

        $ids = $kandidat->flatMap(fn ($k) => [$k->id_anak_a, $k->id_anak_b])->unique()->values();
        $anak = Anak::with(['kel:id,name', 'posyandu:id,name'])->whereIn('id', $ids)->get()->keyBy('id');
        $kunjungan = $this->hitung('data_anak', $ids);
        $imunisasi = $this->hitung('imunisasi', $ids);

        // Anak yang muncul di >1 pasangan = kelompok ≥3 baris (atau rantai) → tak boleh massal.
        $frekuensi = $kandidat->flatMap(fn ($k) => [$k->id_anak_a, $k->id_anak_b])->countBy();

        $baris = [];
        $hitung = ['AMAN' => 0, 'TINJAU' => 0, 'TIDAK BISA' => 0];
        $perAlasan = [];
        $digabung = [];

        foreach ($kandidat as $k) {
            $a = $anak[$k->id_anak_a] ?? null;
            $b = $anak[$k->id_anak_b] ?? null;
            if (!$a || !$b) {
                continue; // baris sudah terhapus sejak pindai
            }

            $alasan = $this->alasanTinjau($a, $b, $frekuensi);
            [$keep, $drop, $kelas, $nikAkhir] = $this->sisi($merge, $a, $b, $alasan);

            $hitung[$kelas]++;
            foreach ($alasan as $x) {
                $perAlasan[$x] = ($perAlasan[$x] ?? 0) + 1;
            }

            $r = [
                'kelas'            => $kelas,
                'alasan'           => implode('; ', $alasan),
                'id_dipertahankan' => $keep,
                'id_dilebur'       => $drop,
                'via'              => $k->via,
                'skor'             => $k->skor,
            ];
            foreach (['a' => $a, 'b' => $b] as $s => $x) {
                foreach ($this->kolomAnak($x, $kunjungan, $imunisasi) as $kol => $v) {
                    $r["{$kol}_{$s}"] = $v;
                }
            }
            $baris[] = $r;

            if ($kelas === 'AMAN') {
                $k1 = $anak[$keep];
                $d1 = $anak[$drop];
                $digabung[] = [
                    'keep'      => "#{$k1->id} ({$k1->sumber})",
                    'drop'      => "#{$d1->id} ({$d1->sumber})",
                    'nama'      => $k1->nama,
                    'tgl_lahir' => substr((string) $k1->tgl_lahir, 0, 10),
                    'kelurahan' => $k1->kel?->name,
                    'posyandu'  => $k1->posyandu?->name,
                    'nik'       => $nikAkhir,
                    'pindah'    => ($kunjungan[$d1->id] ?? 0) . ' kunj / ' . ($imunisasi[$d1->id] ?? 0) . ' imun',
                ];
            }
        }

        // AMAN di atas, lalu TINJAU, lalu TIDAK BISA.
        $urut = ['AMAN' => 0, 'TINJAU' => 1, 'TIDAK BISA' => 2];
        usort($baris, fn ($x, $y) => [$urut[$x['kelas']], $x['id_a']] <=> [$urut[$y['kelas']], $y['id_a']]);

        $path = 'gabung-ganda/dry-run-' . now()->format('Ymd-His') . '.csv';
        $this->tulisCsv($path, $baris);

        $this->line("Kandidat dipindai : {$dipindai}");
        $this->line('Pasangan dinilai  : ' . count($baris) . ' (yang sudah diputus/digabung dilewati)');
        $this->info("AMAN              : {$hitung['AMAN']}   <- bisa digabung massal");
        $this->warn("TINJAU            : {$hitung['TINJAU']}   <- putuskan manual di tab \"Dicurigai sama\"");
        $this->line("TIDAK BISA        : {$hitung['TIDAK BISA']}   <- dua baris Operasi Timbang");
        if ($perAlasan) {
            arsort($perAlasan);
            $this->line('Alasan TINJAU (satu pasangan bisa >1 alasan):');
            foreach ($perAlasan as $x => $n) {
                $this->line("  {$n}\t{$x}");
            }
        }
        if ($digabung) {
            usort($digabung, fn ($x, $y) => [$x['kelurahan'], $x['nama']] <=> [$y['kelurahan'], $y['nama']]);
            $this->newLine();
            $this->info('Akan digabung (AMAN) — baris "dilebur" dihapus, datanya pindah ke "dipertahankan":');
            $this->table(
                ['No', 'Dipertahankan', 'Dilebur', 'Nama', 'Tgl lahir', 'Kelurahan', 'Posyandu', 'NIK akhir', 'Data dipindah'],
                array_map(fn ($r, $i) => [$i + 1, ...array_values($r)], $digabung, array_keys($digabung)),
            );
        }
        $this->line("CSV: storage/app/{$path}");
        $this->line('DRY-RUN: tidak ada data yang diubah.');

        return self::SUCCESS;
    }

    /** @return list<string> kosong = AMAN */
    private function alasanTinjau(Anak $a, Anak $b, $frekuensi): array
    {
        $alasan = [];

        if (substr((string) $a->tgl_lahir, 0, 10) !== substr((string) $b->tgl_lahir, 0, 10)) {
            $alasan[] = 'tgl lahir beda';
        }
        if ((int) $a->jk !== (int) $b->jk) {
            $alasan[] = 'jenis kelamin beda';
        }
        if (self::huruf($a->nama) !== self::huruf($b->nama)) {
            $alasan[] = 'nama tidak persis sama';
        }

        $kkA = trim((string) $a->no_kk);
        $kkB = trim((string) $b->no_kk);
        if ($kkA === '' || $kkB === '') {
            $alasan[] = 'No KK kosong';
        } elseif ($kkA !== $kkB) {
            $alasan[] = 'No KK beda';
        }

        if (self::nikAsli($a->nik) && self::nikAsli($b->nik) && $a->nik !== $b->nik) {
            $alasan[] = 'dua NIK asli berbeda';
        }

        if (PenautanAnakImport::bukanAnakYangSama((string) $a->nama, $a->nik, $a->nama_ibu, $b)) {
            $alasan[] = 'nama ibu beda / tanda kembar';
        }

        if (($frekuensi[$a->id] ?? 0) > 1 || ($frekuensi[$b->id] ?? 0) > 1) {
            $alasan[] = 'kelompok lebih dari dua baris';
        }

        return $alasan;
    }

    /**
     * Baris yang dipertahankan memakai aturan IdentitasMergeService::pratinjau() (OT dipertahankan;
     * Capil dilebur ke non-Capil) lewat tautan sementara yang TIDAK disimpan.
     *
     * @return array{0:?int, 1:?int, 2:string, 3:?string} [dipertahankan, dilebur, kelas, NIK setelah digabung]
     */
    private function sisi(IdentitasMergeService $merge, Anak $a, Anak $b, array $alasan): array
    {
        if (IdentitasMergeService::keduanyaOperasiTimbang($a, $b)) {
            return [null, null, 'TIDAK BISA', null];
        }

        $t = new AnakTautan(['id_anak_a' => $a->id, 'id_anak_b' => $b->id, 'keputusan' => 'sama', 'status' => 'disetujui']);
        try {
            $p = $merge->pratinjau($t);
        } catch (InvalidArgumentException) {
            return [null, null, 'TIDAK BISA', null];
        }
        $keep = $p['dipertahankan'] === 'a' ? $a : $b;
        $drop = $keep->is($a) ? $b : $a;

        $nikAkhir = ($p['default']['nik'] === 'a' ? $a : $b)->nik;

        return [$keep->id, $drop->id, $alasan ? 'TINJAU' : 'AMAN', $nikAkhir];
    }

    private function kolomAnak(Anak $x, array $kunjungan, array $imunisasi): array
    {
        return [
            'id'        => $x->id,
            'sumber'    => $x->sumber,
            'nik'       => self::teks($x->nik),
            'no_kk'     => self::teks($x->no_kk),
            'nama'      => $x->nama,
            'jk'        => (int) $x->jk === 1 ? 'L' : 'P',
            'tgl_lahir' => substr((string) $x->tgl_lahir, 0, 10),
            'nama_ibu'  => $x->nama_ibu,
            'nama_ayah' => $x->nama_ayah,
            'kelurahan' => $x->kel?->name,
            'posyandu'  => $x->posyandu?->name,
            'kunjungan' => $kunjungan[$x->id] ?? 0,
            'imunisasi' => $imunisasi[$x->id] ?? 0,
            'dibuat'    => (string) $x->created_at,
        ];
    }

    /** @return array<int,int> id_anak => jumlah baris */
    private function hitung(string $tabel, $ids): array
    {
        $hasil = [];
        foreach ($ids->chunk(1000) as $potong) {
            $hasil += DB::table($tabel)->whereIn('id_anak', $potong->all())
                ->groupBy('id_anak')->pluck(DB::raw('COUNT(*)'), 'id_anak')->map(fn ($n) => (int) $n)->all();
        }
        return $hasil;
    }

    private function tulisCsv(string $path, array $baris): void
    {
        $header = ['kelas', 'alasan', 'id_dipertahankan', 'id_dilebur', 'via', 'skor'];
        foreach (['a', 'b'] as $s) {
            foreach (self::KOLOM_ANAK as $k) {
                $header[] = "{$k}_{$s}";
            }
        }

        $lines = [implode(',', $header)];
        foreach ($baris as $r) {
            $lines[] = implode(',', array_map(
                fn ($k) => '"' . str_replace('"', '""', (string) ($r[$k] ?? '')) . '"',
                $header
            ));
        }

        // BOM supaya Excel membaca UTF-8 dengan benar.
        Storage::disk('local')->put($path, "\xEF\xBB\xBF" . implode("\n", $lines));
    }

    /** NIK/KK 16 digit sebagai teks Excel (="…"), selain itu apa adanya. Hanya angka → tak bisa jadi rumus lain. */
    private static function teks(?string $v): string
    {
        $v = trim((string) $v);
        return $v !== '' && ctype_digit($v) ? '="' . $v . '"' : $v;
    }

    private static function nikAsli(?string $nik): bool
    {
        return $nik !== null && strlen($nik) === 16 && ctype_digit($nik) && !NikDummyService::isDummy($nik);
    }

    private static function huruf(?string $teks): string
    {
        return preg_replace('/[^a-z]/', '', mb_strtolower((string) $teks));
    }
}

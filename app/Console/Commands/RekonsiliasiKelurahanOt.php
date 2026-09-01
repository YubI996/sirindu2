<?php

namespace App\Console\Commands;

use App\Models\Anak;
use App\Traits\ResolvesWilayah;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Rekonsiliasi id_kel anak sumber='operasi_timbang' terhadap daftar
 * nama+kelurahan resmi (CSV) dari sumber lain (mis. sheet Dinkes).
 *
 * Latar: statistik per-kelurahan dashboard Gizi & Timbang berbeda dari sheet
 * resmi Dinkes meski total kota sama persis (9.884=9.884) — murni tertukar
 * antar kelurahan, bukan data hilang/dobel. resolveKelurahan() (fuzzy match
 * dari kolom Kelurahan berkas OT) tampaknya salah resolve untuk sebagian
 * anak. Command ini TIDAK menebak algoritmanya — ia mencocokkan tiap anak
 * by nama ke daftar resmi, lalu mengoreksi id_kel yang berbeda.
 *
 * Pencocokan by NAMA SAJA punya risiko (nama kembar/duplikat) — kasus
 * ambigu (nama muncul >1 kali di anak ATAU di CSV dengan kelurahan beda)
 * TIDAK pernah ditebak, selalu diekspor untuk tinjauan manual.
 *
 * Default DRY-RUN (hanya laporan + ekspor CSV). Tulis sungguhan hanya
 * dengan --commit, dan hanya untuk pasangan nama yang cocok TUNGGAL.
 */
class RekonsiliasiKelurahanOt extends Command
{
    use ResolvesWilayah;

    protected $signature = 'wilayah:rekonsiliasi-kelurahan
        {csv : Path CSV kolom Nama,Desa/Kel (daftar resmi)}
        {--commit : Tulis koreksi id_kel ke DB (tanpa flag ini hanya dry-run)}';

    protected $description = 'Cocokkan id_kel anak sumber=operasi_timbang terhadap daftar nama+kelurahan resmi (default dry-run).';

    public function handle(): int
    {
        $path = (string) $this->argument('csv');
        if (!is_file($path)) {
            $this->error("File tidak ditemukan: {$path}");
            return self::FAILURE;
        }

        $commit = (bool) $this->option('commit');
        $this->warn('Mode: ' . ($commit ? 'COMMIT (menulis id_kel)' : 'DRY-RUN (tidak menulis apa pun)'));

        $this->initWilayahCache();

        $baris = $this->bacaCsv($path);
        if ($baris === null) {
            return self::FAILURE;
        }

        // 1. Kelompokkan CSV by nama ternormalisasi. Nama yang sama dengan
        //    kelurahan BEDA di dalam CSV sendiri → ambigu-sheet.
        $csvByNama = [];   // NAMA => ['kelurahan_raw' => [...unik...], 'id_kel' => [...unik...]]
        foreach ($baris as [$nama, $kelRaw]) {
            $key = $this->normalisasiNama($nama);
            if ($key === '') continue;
            $idKel = $this->resolveKelurahan($kelRaw);
            $csvByNama[$key]['nama_asli'] ??= $nama;
            $csvByNama[$key]['kelurahan_raw'][$kelRaw] = true;
            $csvByNama[$key]['id_kel'][$idKel === null ? 'null' : $idKel] = $idKel;
        }

        // 2. Kelompokkan anak (sumber=operasi_timbang) by nama ternormalisasi.
        $anakByNama = [];
        Anak::where('sumber', 'operasi_timbang')
            ->select('id', 'nik', 'nama', 'id_kel')
            ->orderBy('id')
            ->chunk(1000, function ($rows) use (&$anakByNama) {
                foreach ($rows as $a) {
                    $anakByNama[$this->normalisasiNama($a->nama)][] = $a;
                }
            });

        $koreksi = [];        // 1:1, kelurahan beda -> perlu update
        $sudahBenar = 0;
        $ambiguDb = [];       // nama dobel di tabel anak
        $ambiguSheet = [];    // nama dobel di CSV dgn kelurahan beda
        $takDitemukan = [];   // nama di CSV, tak ada di anak
        $gagalResolve = [];   // kelurahan CSV tak match master manapun

        $kelurahanNamaById = collect($this->kelurahanCache)->flip(); // id => NAMA (uppercase)

        foreach ($csvByNama as $key => $info) {
            if (count($info['id_kel']) > 1) {
                $ambiguSheet[] = [
                    'nama' => $info['nama_asli'],
                    'kelurahan_variasi' => implode(' | ', array_keys($info['kelurahan_raw'])),
                ];
                continue;
            }

            $idKelTarget = array_values($info['id_kel'])[0];
            if ($idKelTarget === null) {
                $gagalResolve[] = [
                    'nama' => $info['nama_asli'],
                    'kelurahan_raw' => implode(' | ', array_keys($info['kelurahan_raw'])),
                ];
                continue;
            }

            $kandidat = $anakByNama[$key] ?? [];
            if (count($kandidat) === 0) {
                $takDitemukan[] = ['nama' => $info['nama_asli'], 'kelurahan_target' => $kelurahanNamaById[$idKelTarget] ?? $idKelTarget];
                continue;
            }
            if (count($kandidat) > 1) {
                $ambiguDb[] = [
                    'nama' => $info['nama_asli'],
                    'jumlah_kandidat' => count($kandidat),
                    'nik_kandidat' => implode(' | ', array_map(fn ($a) => $a->nik, $kandidat)),
                ];
                continue;
            }

            $anak = $kandidat[0];
            if ((int) $anak->id_kel === (int) $idKelTarget) {
                $sudahBenar++;
                continue;
            }

            $koreksi[] = [
                'id' => $anak->id,
                'nik' => $anak->nik,
                'nama' => $anak->nama,
                'kelurahan_lama' => $kelurahanNamaById[$anak->id_kel] ?? ($anak->id_kel === null ? '(kosong)' : $anak->id_kel),
                'kelurahan_baru' => $kelurahanNamaById[$idKelTarget] ?? $idKelTarget,
                'id_kel_baru' => $idKelTarget,
            ];
        }

        $this->newLine();
        $this->info('SUDAH BENAR      : ' . $sudahBenar);
        $this->info('PERLU KOREKSI    : ' . count($koreksi) . ($commit ? ' (ditulis)' : ' (akan ditulis)'));
        $this->line('AMBIGU (anak)    : ' . count($ambiguDb) . ' — nama dobel di tabel anak, dilewati');
        $this->line('AMBIGU (sheet)   : ' . count($ambiguSheet) . ' — nama dgn kelurahan beda di CSV, dilewati');
        $this->line('TAK DITEMUKAN    : ' . count($takDitemukan) . ' — nama CSV tak ada di anak sumber OT');
        $this->line('KELURAHAN GAGAL  : ' . count($gagalResolve) . ' — teks kelurahan CSV tak cocok master manapun');
        $this->newLine();

        $base = pathinfo($path, PATHINFO_FILENAME);
        $this->tulisCsv("wilayah/{$base}-koreksi.csv", $koreksi, ['id', 'nik', 'nama', 'kelurahan_lama', 'kelurahan_baru']);
        $this->tulisCsv("wilayah/{$base}-ambigu-anak.csv", $ambiguDb, ['nama', 'jumlah_kandidat', 'nik_kandidat']);
        $this->tulisCsv("wilayah/{$base}-ambigu-sheet.csv", $ambiguSheet, ['nama', 'kelurahan_variasi']);
        $this->tulisCsv("wilayah/{$base}-tak-ditemukan.csv", $takDitemukan, ['nama', 'kelurahan_target']);
        $this->tulisCsv("wilayah/{$base}-gagal-resolve.csv", $gagalResolve, ['nama', 'kelurahan_raw']);

        if ($commit && !empty($koreksi)) {
            DB::transaction(function () use ($koreksi) {
                foreach ($koreksi as $k) {
                    Anak::where('id', $k['id'])->update(['id_kel' => $k['id_kel_baru']]);
                }
            });
            $this->info('Koreksi ditulis ke DB.');
        } elseif (!$commit) {
            $this->warn('[DRY-RUN] Tidak ada yang ditulis. Jalankan ulang dengan --commit untuk menyimpan koreksi.');
        }

        return self::SUCCESS;
    }

    /** @return array<int,array{0:string,1:string}>|null null bila header wajib tidak ada */
    private function bacaCsv(string $path): ?array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($lines)) return [];

        // Buang BOM UTF-8 di awal file bila ada.
        $lines[0] = preg_replace('/^\x{FEFF}/u', '', $lines[0]);

        $header = array_map(fn ($h) => strtolower(trim($h)), str_getcsv(array_shift($lines), ',', '"', '\\'));
        $ni = array_search('nama', $header, true);
        $ki = array_search('desa/kel', $header, true) !== false
            ? array_search('desa/kel', $header, true)
            : array_search('kelurahan', $header, true);

        if ($ni === false || $ki === false) {
            $this->error('CSV wajib punya kolom "Nama" dan "Desa/Kel" (atau "Kelurahan").');
            return null;
        }

        $rows = [];
        foreach ($lines as $line) {
            $row = str_getcsv($line, ',', '"', '\\');
            $nama = trim((string) ($row[$ni] ?? ''));
            $kel  = trim((string) ($row[$ki] ?? ''));
            if ($nama === '' || $kel === '') continue;
            $rows[] = [$nama, $kel];
        }

        return $rows;
    }

    private function normalisasiNama(string $nama): string
    {
        $n = strtoupper(trim($nama));
        $n = preg_replace('/\s+/', ' ', $n);
        return $n;
    }

    private function tulisCsv(string $path, array $rows, array $header): void
    {
        if (empty($rows)) return;

        $lines = [implode(',', $header)];
        foreach ($rows as $r) {
            $lines[] = implode(',', array_map(
                fn ($k) => '"' . str_replace('"', '""', (string) ($r[$k] ?? '')) . '"',
                $header
            ));
        }
        Storage::disk('local')->put($path, implode("\n", $lines));
        $this->line("  → tinjau manual: storage/app/{$path} (" . count($rows) . " baris)");
    }
}

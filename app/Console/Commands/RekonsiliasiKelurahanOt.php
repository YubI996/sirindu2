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
 * ke daftar resmi, lalu mengoreksi id_kel yang berbeda.
 *
 * Pencocokan by NAMA SAJA berisiko (nama kembar sangat umum — puluhan
 * "MUHAMMAD ..." kolisi dalam data nyata). Kalau CSV menyediakan kolom
 * Tgl Lahir (+ opsional Nama Ortu), pencocokan naik ke (nama, tgl_lahir) —
 * jauh lebih presisi, dgn nama_ortu sbg penentu akhir kalau MASIH ada >1
 * kandidat (mis. kembar nama+tgl lahir sama). Tanpa kolom Tgl Lahir,
 * command tetap jalan dgn pencocokan nama-saja (format CSV lama).
 *
 * Kasus yang MASIH ambigu setelah semua sinyal dicoba TIDAK PERNAH ditebak
 * — selalu diekspor ke storage/app/wilayah/ untuk tinjauan manual.
 *
 * Default DRY-RUN (hanya laporan + ekspor CSV). Tulis sungguhan hanya
 * dengan --commit, dan hanya untuk pasangan yang cocok TUNGGAL.
 */
class RekonsiliasiKelurahanOt extends Command
{
    use ResolvesWilayah;

    protected $signature = 'wilayah:rekonsiliasi-kelurahan
        {csv : Path CSV kolom Nama,Desa/Kel (+ opsional Tgl Lahir, Nama Ortu)}
        {--commit : Tulis koreksi id_kel ke DB (tanpa flag ini hanya dry-run)}';

    protected $description = 'Cocokkan id_kel anak sumber=operasi_timbang terhadap daftar nama(+tgl lahir)+kelurahan resmi (default dry-run).';

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

        $parsed = $this->bacaCsv($path);
        if ($parsed === null) {
            return self::FAILURE;
        }
        [$baris, $pakaiTglLahir] = $parsed;
        $this->line($pakaiTglLahir
            ? 'Kolom Tgl Lahir terdeteksi — pencocokan (nama, tgl lahir)' . '.'
            : 'Kolom Tgl Lahir TIDAK ada — pencocokan nama saja (lebih rawan kembar nama).');

        // 1. Kelompokkan CSV by kunci (nama[, tgl_lahir]). Kunci yang sama
        //    dengan kelurahan BEDA di dalam CSV sendiri → ambigu-sheet.
        $csvByKey = [];
        foreach ($baris as $b) {
            $key = $this->kunci($b['nama'], $pakaiTglLahir ? $b['tgl_lahir'] : null);
            if ($key === '') continue;
            $idKel = $this->resolveKelurahan($b['kelurahan']);
            $csvByKey[$key]['nama_asli'] ??= $b['nama'];
            $csvByKey[$key]['tgl_lahir'] ??= $b['tgl_lahir'];
            $csvByKey[$key]['nama_ortu'] ??= $b['nama_ortu'];
            $csvByKey[$key]['kelurahan_raw'][$b['kelurahan']] = true;
            $csvByKey[$key]['id_kel'][$idKel === null ? 'null' : $idKel] = $idKel;
        }

        // 2. Kelompokkan anak (sumber=operasi_timbang) by kunci yang sama.
        $anakByKey = [];
        Anak::where('sumber', 'operasi_timbang')
            ->select('id', 'nik', 'nama', 'tgl_lahir', 'nama_ibu', 'nama_ayah', 'id_kel')
            ->orderBy('id')
            ->chunk(1000, function ($rows) use (&$anakByKey, $pakaiTglLahir) {
                foreach ($rows as $a) {
                    $tgl = $pakaiTglLahir ? substr((string) $a->tgl_lahir, 0, 10) : null;
                    $anakByKey[$this->kunci($a->nama, $tgl)][] = $a;
                }
            });

        $koreksi = [];
        $sudahBenar = 0;
        $ambiguDb = [];
        $ambiguSheet = [];
        $takDitemukan = [];
        $gagalResolve = [];

        $kelurahanNamaById = collect($this->kelurahanCache)->flip(); // id => NAMA (uppercase)

        foreach ($csvByKey as $key => $info) {
            if (count($info['id_kel']) > 1) {
                $ambiguSheet[] = [
                    'nama' => $info['nama_asli'],
                    'tgl_lahir' => $info['tgl_lahir'],
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

            $kandidat = $anakByKey[$key] ?? [];
            if (count($kandidat) === 0) {
                $takDitemukan[] = ['nama' => $info['nama_asli'], 'tgl_lahir' => $info['tgl_lahir'], 'kelurahan_target' => $kelurahanNamaById[$idKelTarget] ?? $idKelTarget];
                continue;
            }

            if (count($kandidat) > 1) {
                // Nama (+tgl lahir) masih kembar — coba penentu akhir: nama ortu.
                [$csvAyah, $csvIbu] = $this->pecahNamaOrtu((string) ($info['nama_ortu'] ?? ''));
                $cocokOrtu = array_values(array_filter(
                    $kandidat,
                    fn ($a) => $this->ortuCocok($csvAyah, $csvIbu, $a->nama_ayah, $a->nama_ibu)
                ));
                if (count($cocokOrtu) === 1) {
                    $kandidat = $cocokOrtu;
                } else {
                    $ambiguDb[] = [
                        'nama' => $info['nama_asli'],
                        'tgl_lahir' => $info['tgl_lahir'],
                        'jumlah_kandidat' => count($kandidat),
                        'nik_kandidat' => implode(' | ', array_map(fn ($a) => $a->nik, $kandidat)),
                    ];
                    continue;
                }
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
        $this->line('AMBIGU (anak)    : ' . count($ambiguDb) . ' — kembar di tabel anak, dilewati');
        $this->line('AMBIGU (sheet)   : ' . count($ambiguSheet) . ' — kembar di CSV dgn kelurahan beda, dilewati');
        $this->line('TAK DITEMUKAN    : ' . count($takDitemukan) . ' — di CSV, tak ada di anak sumber OT');
        $this->line('KELURAHAN GAGAL  : ' . count($gagalResolve) . ' — teks kelurahan CSV tak cocok master manapun');
        $this->newLine();

        $base = pathinfo($path, PATHINFO_FILENAME);
        $this->tulisCsv("wilayah/{$base}-koreksi.csv", $koreksi, ['id', 'nik', 'nama', 'kelurahan_lama', 'kelurahan_baru']);
        $this->tulisCsv("wilayah/{$base}-ambigu-anak.csv", $ambiguDb, ['nama', 'tgl_lahir', 'jumlah_kandidat', 'nik_kandidat']);
        $this->tulisCsv("wilayah/{$base}-ambigu-sheet.csv", $ambiguSheet, ['nama', 'tgl_lahir', 'kelurahan_variasi']);
        $this->tulisCsv("wilayah/{$base}-tak-ditemukan.csv", $takDitemukan, ['nama', 'tgl_lahir', 'kelurahan_target']);
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

    /**
     * @return array{0:array<int,array{nama:string,tgl_lahir:?string,nama_ortu:?string,kelurahan:string}>,1:bool}|null
     *         null bila header wajib tidak ada. Elemen ke-2: apakah kolom Tgl Lahir terdeteksi.
     */
    private function bacaCsv(string $path): ?array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($lines)) return [[], false];

        // Buang BOM UTF-8 di awal file bila ada.
        $lines[0] = preg_replace('/^\x{FEFF}/u', '', $lines[0]);

        $header = array_map(fn ($h) => strtolower(trim($h)), str_getcsv(array_shift($lines), ',', '"', '\\'));
        $ni = array_search('nama', $header, true);
        $ki = array_search('desa/kel', $header, true) !== false
            ? array_search('desa/kel', $header, true)
            : array_search('kelurahan', $header, true);
        $ti = array_search('tgl lahir', $header, true) !== false
            ? array_search('tgl lahir', $header, true)
            : array_search('tanggal lahir', $header, true);
        $oi = array_search('nama ortu', $header, true) !== false
            ? array_search('nama ortu', $header, true)
            : array_search('nama orang tua', $header, true);

        if ($ni === false || $ki === false) {
            $this->error('CSV wajib punya kolom "Nama" dan "Desa/Kel" (atau "Kelurahan").');
            return null;
        }

        $pakaiTglLahir = $ti !== false;

        $rows = [];
        foreach ($lines as $line) {
            $row = str_getcsv($line, ',', '"', '\\');
            $nama = trim((string) ($row[$ni] ?? ''));
            $kel  = trim((string) ($row[$ki] ?? ''));
            if ($nama === '' || $kel === '') continue;

            $rows[] = [
                'nama' => $nama,
                'tgl_lahir' => $pakaiTglLahir ? $this->parseTglLahir((string) ($row[$ti] ?? '')) : null,
                'nama_ortu' => $oi !== false ? trim((string) ($row[$oi] ?? '')) : null,
                'kelurahan' => $kel,
            ];
        }

        return [$rows, $pakaiTglLahir];
    }

    /** "28/06/2021" (DD/MM/YYYY) -> "2021-06-28". Null bila tak bisa diparse. */
    private function parseTglLahir(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') return null;
        $d = \DateTime::createFromFormat('d/m/Y', $raw);
        if ($d === false) return null;
        return $d->format('Y-m-d');
    }

    /** Kunci pencocokan: NAMA ternormalisasi (+ "|tgl_lahir" bila dipakai). */
    private function kunci(string $nama, ?string $tglLahir): string
    {
        $n = $this->normalisasiNama($nama);
        if ($n === '') return '';
        return $tglLahir !== null ? "{$n}|{$tglLahir}" : $n;
    }

    private function normalisasiNama(string $nama): string
    {
        $n = strtoupper(trim($nama));
        $n = preg_replace('/\s+/', ' ', $n);
        return $n;
    }

    /** "AYAH / IBU" atau "IBU" saja -> [ayah, ibu]. Sama pola dgn OtFinalRegistriImport. */
    private function pecahNamaOrtu(string $namaOrtu): array
    {
        $v = trim($namaOrtu);
        if ($v === '') return [null, null];
        $parts = array_values(array_filter(array_map('trim', explode('/', $v)), fn ($p) => $p !== ''));
        if (count($parts) >= 2) return [$parts[0], $parts[1]];
        return [null, $parts[0] ?? null];
    }

    /** Cocok longgar (substring dua arah, ternormalisasi) — hindari gagal karena selisih gelar/spasi kecil. */
    private function ortuCocok(?string $csvAyah, ?string $csvIbu, ?string $dbAyah, ?string $dbIbu): bool
    {
        $norm = fn (?string $s) => $s ? $this->normalisasiNama($s) : null;
        $csvAyah = $norm($csvAyah);
        $csvIbu  = $norm($csvIbu);
        $dbAyah  = $norm($dbAyah);
        $dbIbu   = $norm($dbIbu);

        $cocok = fn (?string $a, ?string $b) => $a && $b && (str_contains($a, $b) || str_contains($b, $a));

        return $cocok($csvAyah, $dbAyah) || $cocok($csvIbu, $dbIbu);
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

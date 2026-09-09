<?php

namespace App\Console\Commands;

use App\Models\Anak;
use App\Models\Posyandu;
use App\Services\FaskesMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Perbaiki id_posyandu (dan id_puskesmas) anak sumber='operasi_timbang' yang
 * terlanjur salah/NULL di server akibat matcher faskes lama.
 *
 * Latar: OtFinalRegistriImport dulu mencocokkan nama posyandu apa adanya lalu
 * jatuh ke LIKE '%nama%' GLOBAL. Master ditulis Romawi ("Sejahtera II"), berkas
 * e-PPGBM memakai Arab ("SEJAHTERA 2"), sehingga pada berkas Juni 2026:
 *   - 2.352 baris (23,8%) berakhir id_posyandu NULL → 35 dari 121 posyandu
 *     tampil kosong walau anaknya ada;
 *   - 644 baris (6,5%) menempel diam-diam ke posyandu lain;
 *   - 7.386 baris (74,7%) berakhir id_puskesmas NULL.
 * Command ini hanya menyentuh dua kolom itu — data ukur/z-score tidak diusik.
 *
 * NIK di ekspor e-PPGBM tersensor ("00030**********"), jadi anak dicocokkan
 * lewat (nama, tgl lahir) dengan nama ortu sebagai penentu akhir bila masih
 * kembar — pola sama dengan `wilayah:rekonsiliasi-kelurahan`.
 *
 * Yang MASIH ambigu tidak pernah ditebak: diekspor ke
 * storage/app/posyandu/<berkas>-perlu-keputusan.csv untuk diputuskan Dinkes.
 *
 * Default DRY-RUN; menulis hanya dengan --commit.
 */
class BackfillPosyanduOt extends Command
{
    protected $signature = 'posyandu:backfill-ot
        {csv : Path CSV berkas OT (kolom Nama, Tgl Lahir, Posyandu, Pukesmas; opsional Nama Ortu)}
        {--keputusan= : Path CSV keputusan Dinkes (posyandu_berkas,puskesmas,kelurahan,posyandu_master)}
        {--commit : Tulis koreksi ke DB (tanpa flag ini hanya dry-run)}';

    protected $description = 'Perbaiki id_posyandu/id_puskesmas anak sumber=operasi_timbang dari berkas OT (default dry-run).';

    public function handle(): int
    {
        $path = (string) $this->argument('csv');
        if (!is_file($path)) {
            $this->error("File tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $commit = (bool) $this->option('commit');
        $this->warn('Mode: ' . ($commit ? 'COMMIT (menulis id_posyandu/id_puskesmas)' : 'DRY-RUN (tidak menulis apa pun)'));

        $baris = $this->bacaCsv($path);
        if ($baris === null) {
            return self::FAILURE;
        }

        $matcher    = new FaskesMatcher();
        $namaByIdPos = Posyandu::pluck('name', 'id')->all();

        $keputusan = $this->bacaKeputusan((string) $this->option('keputusan'));
        if ($keputusan === null) {
            return self::FAILURE;
        }

        // 1. Kelompokkan anak OT by kunci (NAMA|tgl_lahir).
        $anakByKey = [];
        Anak::where('sumber', 'operasi_timbang')
            ->select('id', 'nik', 'nama', 'tgl_lahir', 'nama_ibu', 'nama_ayah', 'id_posyandu', 'id_puskesmas')
            ->orderBy('id')
            ->chunk(1000, function ($rows) use (&$anakByKey) {
                foreach ($rows as $a) {
                    $anakByKey[$this->kunci($a->nama, substr((string) $a->tgl_lahir, 0, 10))][] = $a;
                }
            });

        $koreksi = [];
        $sudahBenar = 0;
        $perluKeputusan = [];
        $ambiguAnak = [];
        $takDitemukan = 0;

        $dariKeputusan = 0;

        foreach ($baris as $b) {
            $idPus = $matcher->cocokkanPuskesmas($b['puskesmas'])['id'];

            // Keputusan Dinkes menang atas tebakan matcher — itu gunanya. Ia juga
            // satu-satunya cara mengisi baris yang kolom posyandunya kosong di
            // berkas, karena di situ tak ada nama untuk dicocokkan.
            $ditetapkan = $keputusan[$this->kunciKeputusan($b['posyandu'], $b['puskesmas'], $b['kelurahan'])] ?? null;

            if ($ditetapkan !== null) {
                $hasil = ['id' => $ditetapkan, 'alasan' => 'keputusan-dinkes', 'kandidat' => []];
                $dariKeputusan++;
            } else {
                $hasil = $matcher->cocokkan($b['posyandu'], $b['puskesmas']);
            }

            if ($hasil['id'] === null) {
                $kunci = $b['posyandu'] . '|' . $b['puskesmas'] . '|' . $b['kelurahan'];

                // Kandidat yang ditolak karena ambigu sudah paling relevan;
                // untuk 'tak-ada' pakai saran nama mirip agar berkasnya bisa
                // dipakai memutuskan, bukan sekadar daftar kosong.
                $kandidat = $hasil['kandidat'] ?: $matcher->saran($b['posyandu']);

                $perluKeputusan[$kunci] = [
                    'posyandu_berkas' => $b['posyandu'],
                    'puskesmas'       => $b['puskesmas'],
                    'kelurahan'       => $b['kelurahan'],
                    'alasan'          => $hasil['alasan'],
                    'kandidat_master' => implode(' | ', $kandidat),
                    'jumlah_baris'    => (int) ($perluKeputusan[$kunci]['jumlah_baris'] ?? 0) + 1,
                ];
                continue;
            }

            $kandidat = $anakByKey[$this->kunci($b['nama'], $b['tgl_lahir'])] ?? [];
            if (count($kandidat) === 0) {
                $takDitemukan++;
                continue;
            }

            if (count($kandidat) > 1) {
                [$csvAyah, $csvIbu] = $this->pecahNamaOrtu((string) ($b['nama_ortu'] ?? ''));
                $cocokOrtu = array_values(array_filter(
                    $kandidat,
                    fn ($a) => $this->ortuCocok($csvAyah, $csvIbu, $a->nama_ayah, $a->nama_ibu)
                ));

                if (count($cocokOrtu) !== 1) {
                    $ambiguAnak[] = [
                        'nama'            => $b['nama'],
                        'tgl_lahir'       => $b['tgl_lahir'],
                        'jumlah_kandidat' => count($kandidat),
                        'posyandu_target' => $namaByIdPos[$hasil['id']] ?? $hasil['id'],
                    ];
                    continue;
                }

                $kandidat = $cocokOrtu;
            }

            $anak = $kandidat[0];
            $posSama = (int) $anak->id_posyandu === (int) $hasil['id'];
            $pusSama = $idPus === null || (int) $anak->id_puskesmas === (int) $idPus;

            if ($posSama && $pusSama) {
                $sudahBenar++;
                continue;
            }

            $koreksi[$anak->id] = [
                'id'              => $anak->id,
                'nama'            => $anak->nama,
                'posyandu_lama'   => $anak->id_posyandu === null
                    ? '(kosong)'
                    : ($namaByIdPos[$anak->id_posyandu] ?? $anak->id_posyandu),
                'posyandu_baru'   => $namaByIdPos[$hasil['id']] ?? $hasil['id'],
                'cara_cocok'      => $hasil['alasan'],
                'id_posyandu'     => (int) $hasil['id'],
                'id_puskesmas'    => $idPus,
            ];
        }

        $this->newLine();
        if ($dariKeputusan > 0) {
            $this->info('DARI KEPUTUSAN   : ' . $dariKeputusan . ' baris dipetakan mengikuti keputusan Dinkes');
        }
        $this->info('SUDAH BENAR      : ' . $sudahBenar);
        $this->info('PERLU KOREKSI    : ' . count($koreksi) . ($commit ? ' (ditulis)' : ' (akan ditulis)'));
        $this->line('PERLU KEPUTUSAN  : ' . array_sum(array_column($perluKeputusan, 'jumlah_baris'))
            . ' baris / ' . count($perluKeputusan) . ' nama posyandu — tidak ditebak');
        $this->line('AMBIGU (anak)    : ' . count($ambiguAnak) . ' — kembar di tabel anak, dilewati');
        $this->line('TAK DITEMUKAN    : ' . $takDitemukan . ' — ada di CSV, tak ada di anak sumber OT');
        $this->newLine();

        $base = pathinfo($path, PATHINFO_FILENAME);
        $this->tulisCsv("posyandu/{$base}-koreksi.csv", array_values($koreksi),
            ['id', 'nama', 'posyandu_lama', 'posyandu_baru', 'cara_cocok']);
        $this->tulisCsv("posyandu/{$base}-perlu-keputusan.csv", array_values($perluKeputusan),
            ['posyandu_berkas', 'puskesmas', 'kelurahan', 'jumlah_baris', 'alasan', 'kandidat_master']);
        $this->tulisCsv("posyandu/{$base}-ambigu-anak.csv", $ambiguAnak,
            ['nama', 'tgl_lahir', 'jumlah_kandidat', 'posyandu_target']);

        if ($commit && !empty($koreksi)) {
            DB::transaction(function () use ($koreksi) {
                foreach ($koreksi as $k) {
                    $isi = ['id_posyandu' => $k['id_posyandu']];
                    if ($k['id_puskesmas'] !== null) {
                        $isi['id_puskesmas'] = $k['id_puskesmas'];
                    }
                    Anak::where('id', $k['id'])->update($isi);
                }
            });
            $this->info('Koreksi ditulis ke DB.');
        } elseif (!$commit) {
            $this->warn('[DRY-RUN] Tidak ada yang ditulis. Jalankan ulang dengan --commit untuk menyimpan koreksi.');
        }

        return self::SUCCESS;
    }

    /**
     * Kunci pemetaan keputusan: nama posyandu di berkas + puskesmas + kelurahan,
     * semuanya ternormalisasi. Kelurahan ikut karena dua baris bisa punya nama
     * dan puskesmas sama tetapi posyandu berbeda — persis kasus "Anggrek"
     * Belimbing vs "Anggrek1" Gunung Telihan.
     */
    private function kunciKeputusan(string $posyandu, string $puskesmas, string $kelurahan): string
    {
        return FaskesMatcher::normalisasi($posyandu)
            . '|' . FaskesMatcher::normalisasi($puskesmas)
            . '|' . FaskesMatcher::normalisasi($kelurahan);
    }

    /**
     * Baca CSV keputusan Dinkes menjadi peta kunci => id posyandu.
     *
     * Kolom: posyandu_berkas, puskesmas, kelurahan, posyandu_master.
     * `posyandu_berkas` boleh kosong — itu justru kasus yang tak bisa ditangani
     * matcher. Nama posyandu_master yang tak ada di data induk DILAPORKAN, tidak
     * didiamkan: salah ketik di berkas keputusan jangan sampai lolos jadi baris
     * yang diam-diam tak terisi.
     *
     * @return array<string,int>|null null bila berkasnya bermasalah
     */
    private function bacaKeputusan(string $path): ?array
    {
        if ($path === '') {
            return [];
        }

        if (!is_file($path)) {
            $this->error("Berkas keputusan tidak ditemukan: {$path}");

            return null;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($lines)) {
            return [];
        }

        $lines[0] = preg_replace('/^\x{FEFF}/u', '', $lines[0]);
        $header = array_map(
            fn ($h) => strtolower(trim($h)),
            str_getcsv(array_shift($lines), ',', '"', '\\')
        );

        foreach (['posyandu_berkas', 'puskesmas', 'kelurahan', 'posyandu_master'] as $wajib) {
            if (!in_array($wajib, $header, true)) {
                $this->error("Berkas keputusan wajib punya kolom \"{$wajib}\".");

                return null;
            }
        }

        $idx = array_flip($header);

        // Cocokkan nama posyandu_master ke data induk lewat normalisasi yang sama
        // dengan matcher, supaya "Sejahtera 5" tetap ketemu "Sejahtera V".
        $master = [];
        foreach (Posyandu::select('id', 'name')->get() as $p) {
            $master[FaskesMatcher::normalisasi((string) $p->name)][] = (int) $p->id;
        }

        $peta = [];
        $takDikenal = [];

        foreach ($lines as $line) {
            $row = str_getcsv($line, ',', '"', '\\');

            $namaMaster = trim((string) ($row[$idx['posyandu_master']] ?? ''));
            if ($namaMaster === '') {
                continue; // baris tanpa keputusan — dilewati, bukan kesalahan
            }

            $kandidat = $master[FaskesMatcher::normalisasi($namaMaster)] ?? [];
            if (count($kandidat) !== 1) {
                $takDikenal[$namaMaster] = count($kandidat) > 1
                    ? 'ada lebih dari satu di data induk'
                    : 'tidak ada di data induk';
                continue;
            }

            $peta[$this->kunciKeputusan(
                (string) ($row[$idx['posyandu_berkas']] ?? ''),
                (string) ($row[$idx['puskesmas']] ?? ''),
                (string) ($row[$idx['kelurahan']] ?? '')
            )] = $kandidat[0];
        }

        foreach ($takDikenal as $nama => $sebab) {
            $this->warn("Keputusan dilewati — posyandu \"{$nama}\" {$sebab}.");
        }

        $this->line('Keputusan dimuat: ' . count($peta) . ' pemetaan'
            . ($takDikenal ? ', ' . count($takDikenal) . ' dilewati' : '') . '.');

        return $peta;
    }

    /**
     * @return array<int,array{nama:string,tgl_lahir:?string,nama_ortu:?string,posyandu:string,puskesmas:string,kelurahan:string}>|null
     */
    private function bacaCsv(string $path): ?array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($lines)) {
            return [];
        }

        $lines[0] = preg_replace('/^\x{FEFF}/u', '', $lines[0]);
        $header = array_map(fn ($h) => strtolower(trim($h)), str_getcsv(array_shift($lines), ',', '"', '\\'));

        $cari = function (array $alias) use ($header) {
            foreach ($alias as $a) {
                $i = array_search($a, $header, true);
                if ($i !== false) {
                    return $i;
                }
            }

            return false;
        };

        $ni = $cari(['nama', 'nama anak']);
        $pi = $cari(['posyandu']);
        $ti = $cari(['tgl lahir', 'tanggal lahir']);
        $ui = $cari(['pukesmas', 'puskesmas']);
        $ki = $cari(['desa/kel', 'kelurahan']);
        $oi = $cari(['nama ortu', 'nama orang tua', 'nama orang tua (ibu/ayah)']);

        if ($ni === false || $pi === false) {
            $this->error('CSV wajib punya kolom "Nama" dan "Posyandu".');

            return null;
        }
        if ($ti === false) {
            $this->error('CSV wajib punya kolom "Tgl Lahir" — pencocokan nama saja terlalu rawan kembar nama.');

            return null;
        }

        $rows = [];
        foreach ($lines as $line) {
            $row  = str_getcsv($line, ',', '"', '\\');
            $nama = trim((string) ($row[$ni] ?? ''));
            if ($nama === '') {
                continue;
            }

            $rows[] = [
                'nama'      => $nama,
                'tgl_lahir' => $this->parseTglLahir((string) ($row[$ti] ?? '')),
                'nama_ortu' => $oi !== false ? trim((string) ($row[$oi] ?? '')) : null,
                'posyandu'  => trim((string) ($row[$pi] ?? '')),
                'puskesmas' => $ui !== false ? trim((string) ($row[$ui] ?? '')) : '',
                'kelurahan' => $ki !== false ? trim((string) ($row[$ki] ?? '')) : '',
            ];
        }

        return $rows;
    }

    /** "28/06/2021" (DD/MM/YYYY) atau "2021-06-28" -> "2021-06-28". */
    private function parseTglLahir(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        foreach (['d/m/Y', 'Y-m-d'] as $format) {
            $d = \DateTime::createFromFormat($format, $raw);
            if ($d !== false) {
                return $d->format('Y-m-d');
            }
        }

        return null;
    }

    private function kunci(string $nama, ?string $tglLahir): string
    {
        $n = preg_replace('/\s+/', ' ', strtoupper(trim($nama)));

        return $n === '' ? '' : "{$n}|{$tglLahir}";
    }

    /** "AYAH / IBU" atau "IBU" saja -> [ayah, ibu]. */
    private function pecahNamaOrtu(string $namaOrtu): array
    {
        $v = trim($namaOrtu);
        if ($v === '') {
            return [null, null];
        }

        $parts = array_values(array_filter(array_map('trim', explode('/', $v)), fn ($p) => $p !== ''));

        return count($parts) >= 2 ? [$parts[0], $parts[1]] : [null, $parts[0] ?? null];
    }

    private function ortuCocok(?string $csvAyah, ?string $csvIbu, ?string $dbAyah, ?string $dbIbu): bool
    {
        $norm = fn (?string $s) => $s ? preg_replace('/\s+/', ' ', strtoupper(trim($s))) : null;
        $cocok = fn (?string $a, ?string $b) => $a && $b && (str_contains($a, $b) || str_contains($b, $a));

        return $cocok($norm($csvAyah), $norm($dbAyah)) || $cocok($norm($csvIbu), $norm($dbIbu));
    }

    private function tulisCsv(string $path, array $rows, array $header): void
    {
        if (empty($rows)) {
            return;
        }

        $lines = [implode(',', $header)];
        foreach ($rows as $r) {
            $lines[] = implode(',', array_map(
                fn ($k) => '"' . str_replace('"', '""', (string) ($r[$k] ?? '')) . '"',
                $header
            ));
        }

        Storage::disk('local')->put($path, implode("\n", $lines));
        $this->line("  → tinjau manual: storage/app/{$path} (" . count($rows) . ' baris)');
    }
}

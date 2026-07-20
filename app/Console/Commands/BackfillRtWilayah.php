<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Isi ulang id_rt yang kosong dari berkas import aslinya.
 *
 * Latar: saat import lama, master kelurahan masih bernama "Telihan" sehingga
 * "GUNUNG TELIHAN" dari sumber gagal di-resolve. Karena resolveRt() menyerah
 * begitu id_kel null, id_rt ikut kosong. id_kel kemudian diperbaiki lewat rename
 * master, tapi id_rt tidak pernah diisi ulang — baris jadi tak tergambar di peta.
 */
class BackfillRtWilayah extends Command
{
    protected $signature = 'wilayah:backfill-rt
        {--apply : Terapkan perubahan (tanpa flag ini hanya dry-run)}
        {--kel=3 : Id kelurahan yang diperbaiki}
        {--anak-csv= : Berkas sumber anak (kolom nama_rt)}
        {--pd3i-csv= : Berkas sumber surveilans PD3I (kolom RT)}';

    protected $description = 'Isi id_rt yang kosong dari berkas import asli, dicocokkan via NIK lalu nama+tgl lahir.';

    /** Nama RT -> id, untuk kelurahan yang sedang diproses. */
    private array $rtByName = [];

    /** Suffix seragam nama RT di kelurahan ini, mis. "TELIHAN" dari "23TELIHAN". */
    private string $suffix = '';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $idKel = (int) $this->option('kel');

        if (!$this->muatMasterRt($idKel)) {
            return self::FAILURE;
        }

        $this->line("Kelurahan id={$idKel} — " . count($this->rtByName) . " RT, suffix '{$this->suffix}'.");
        $this->line($apply ? 'Mode: APPLY (menulis ke database).' : 'Mode: DRY-RUN (tidak menulis apa pun).');
        $this->newLine();

        $total = 0;

        $anakCsv = $this->option('anak-csv')
            ?? $this->cariBerkas('storage/app/imports/anak/*.csv');
        if ($anakCsv) {
            $total += $this->proses(
                label: 'anak',
                tabel: 'anak',
                idKel: $idKel,
                apply: $apply,
                sumber: $this->bacaCsv($anakCsv, kelIdx: 19, nikIdx: 4, namaIdx: 5, tglIdx: 1, rtIdx: 16),
                kolomNama: 'nama',
                kolomTgl: 'tgl_lahir',
            );
        } else {
            $this->warn('Sumber anak tidak ditemukan — dilewati.');
        }

        $pd3iCsv = $this->option('pd3i-csv')
            ?? $this->cariBerkas('storage/app/imports/pd3i/*.csv');
        if ($pd3iCsv) {
            $total += $this->proses(
                label: 'surveillance_cases',
                tabel: 'surveillance_cases',
                idKel: $idKel,
                apply: $apply,
                sumber: $this->bacaCsv($pd3iCsv, kelIdx: 19, nikIdx: 8, namaIdx: 9, tglIdx: 11, rtIdx: 20),
                kolomNama: 'nama_lengkap',
                kolomTgl: 'tanggal_lahir',
            );
        } else {
            $this->warn('Sumber PD3I tidak ditemukan — dilewati.');
        }

        $this->newLine();
        $this->info($apply
            ? "Selesai. {$total} baris diisi."
            : "Dry-run selesai. {$total} baris akan diisi. Jalankan ulang dengan --apply untuk menerapkan.");

        return self::SUCCESS;
    }

    /**
     * Muat master RT. Suffix diturunkan dari data, bukan ditebak — kalau tidak
     * seragam, pencocokan persis tidak aman dan command berhenti.
     */
    private function muatMasterRt(int $idKel): bool
    {
        $rows = DB::table('rt')->where('id_kelurahan', $idKel)->get(['id', 'name']);
        if ($rows->isEmpty()) {
            $this->error("Kelurahan id={$idKel} tidak punya master RT.");
            return false;
        }

        $suffixes = [];
        foreach ($rows as $r) {
            $this->rtByName[strtoupper(trim($r->name))] = $r->id;
            if (preg_match('/^(\d+)(.*)$/', trim($r->name), $m)) {
                $suffixes[strtoupper($m[2])] = true;
            }
        }

        if (count($suffixes) !== 1) {
            $this->error('Suffix nama RT tidak seragam (' . implode(', ', array_keys($suffixes))
                . ') — pencocokan persis tidak aman.');
            return false;
        }

        $this->suffix = array_key_first($suffixes);
        return true;
    }

    /** Ambil berkas terbaru — kalau ada beberapa hasil import, yang relevan yang terakhir. */
    private function cariBerkas(string $pola): ?string
    {
        $files = glob(base_path($pola)) ?: [];
        if (!$files) {
            return null;
        }
        usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
        $this->line('Sumber: ' . basename($files[0]) . (count($files) > 1
            ? ' (terbaru dari ' . count($files) . ' berkas)' : ''));

        return $files[0];
    }

    /** @return array<int, array{nik:string, nama:string, tgl:?string, rt:string}> */
    private function bacaCsv(string $path, int $kelIdx, int $nikIdx, int $namaIdx, int $tglIdx, int $rtIdx): array
    {
        $idKelName = strtoupper(trim((string) DB::table('kelurahan')->where('id', (int) $this->option('kel'))->value('name')));

        $h = fopen($path, 'r');
        @fgetcsv($h, 0, ',');

        $out = [];
        while (($r = @fgetcsv($h, 0, ',')) !== false) {
            if ($this->normNama($r[$kelIdx] ?? '') !== $idKelName) {
                continue;
            }
            $out[] = [
                'nik'  => trim((string) ($r[$nikIdx] ?? '')),
                'nama' => $this->normNama($r[$namaIdx] ?? ''),
                'tgl'  => $this->normTgl($r[$tglIdx] ?? ''),
                'rt'   => trim((string) ($r[$rtIdx] ?? '')),
            ];
        }
        fclose($h);

        return $out;
    }

    /**
     * Cocokkan baris sasaran ke sumber lewat tiga kunci berurutan: NIK, lalu
     * nama+tgl lahir, lalu nama saja. Dua kunci terakhir hanya diterima bila
     * pasangannya unik di kedua sisi — kalau kembar, dilewati daripada menebak.
     */
    private function proses(
        string $label,
        string $tabel,
        int $idKel,
        bool $apply,
        array $sumber,
        string $kolomNama,
        string $kolomTgl,
    ): int {
        $byNik = [];
        $byNamaTgl = [];
        $byNama = [];
        foreach ($sumber as $s) {
            if ($s['nik'] !== '') {
                $byNik[$s['nik']] = $s['rt'];
            }
            $byNamaTgl[$s['nama'] . '|' . ($s['tgl'] ?? '?')][] = $s['rt'];
            $byNama[$s['nama']][] = $s['rt'];
        }

        $targets = DB::table($tabel)
            ->where('id_kel', $idKel)
            ->whereNull('id_rt')
            ->get(['id', 'nik', $kolomNama, $kolomTgl]);

        // Tabrakan di sisi DB — kunci yang dimiliki lebih dari satu baris sasaran.
        $dbNamaTgl = [];
        $dbNama = [];
        foreach ($targets as $row) {
            $nama = $this->normNama($row->{$kolomNama});
            $dbNamaTgl[$nama . '|' . ($this->normTgl($row->{$kolomTgl}) ?? '?')][] = $row->id;
            $dbNama[$nama][] = $row->id;
        }

        $stat = ['nik' => 0, 'nama_tgl' => 0, 'nama' => 0, 'ambigu' => 0, 'tanpa_pasangan' => 0, 'rt_kosong' => 0];
        $rtAneh = [];
        $updates = [];

        foreach ($targets as $row) {
            $nama = $this->normNama($row->{$kolomNama});
            $key  = $nama . '|' . ($this->normTgl($row->{$kolomTgl}) ?? '?');
            $nik  = trim((string) $row->nik);

            $raw = null;
            $via = null;

            if ($nik !== '' && array_key_exists($nik, $byNik)) {
                $raw = $byNik[$nik];
                $via = 'nik';
            } elseif ($this->unik($byNamaTgl[$key] ?? [], $dbNamaTgl[$key] ?? [])) {
                $raw = $byNamaTgl[$key][0];
                $via = 'nama_tgl';
            } elseif ($this->unik($byNama[$nama] ?? [], $dbNama[$nama] ?? [])) {
                $raw = $byNama[$nama][0];
                $via = 'nama';
            } elseif (isset($byNamaTgl[$key]) || isset($byNama[$nama])) {
                $stat['ambigu']++;
                continue;
            } else {
                $stat['tanpa_pasangan']++;
                continue;
            }

            if (trim((string) $raw) === '') {
                $stat['rt_kosong']++;
                continue;
            }

            $idRt = $this->resolveRtPersis($raw);
            if ($idRt === null) {
                $rtAneh[$raw] = ($rtAneh[$raw] ?? 0) + 1;
                continue;
            }

            $stat[$via]++;
            $updates[$idRt][] = $row->id;
        }

        $akan = $stat['nik'] + $stat['nama_tgl'] + $stat['nama'];

        $this->line("[{$label}] sasaran id_rt NULL: " . $targets->count());
        $this->line("  cocok via NIK={$stat['nik']}, nama+tgl={$stat['nama_tgl']}, nama={$stat['nama']}  => {$akan}");
        $this->line("  dilewati: ambigu={$stat['ambigu']}, tanpa pasangan={$stat['tanpa_pasangan']}, "
            . "RT kosong di sumber={$stat['rt_kosong']}, RT tak dikenal=" . (count($rtAneh) ? json_encode($rtAneh) : '0'));

        if ($apply && $updates) {
            DB::transaction(function () use ($tabel, $updates) {
                foreach ($updates as $idRt => $ids) {
                    // Guard: hanya sentuh baris yang masih NULL, biar idempoten.
                    DB::table($tabel)->whereIn('id', $ids)->whereNull('id_rt')->update(['id_rt' => $idRt]);
                }
            });
            $this->info("  -> {$akan} baris diperbarui.");
        }

        return $akan;
    }

    /** Pasangan diterima hanya bila tepat satu kandidat di sumber dan satu di DB. */
    private function unik(array $sumber, array $db): bool
    {
        return count($sumber) === 1 && count($db) === 1;
    }

    /** "023" -> "23TELIHAN". Sengaja persis, bukan LIKE, agar 1 tak nyangkut ke 10/21. */
    private function resolveRtPersis(string $raw): ?int
    {
        $raw = trim($raw);
        if ($raw === '' || !ctype_digit($raw)) {
            return null;
        }
        return $this->rtByName[((int) $raw) . $this->suffix] ?? null;
    }

    private function normNama(?string $s): string
    {
        return preg_replace('/\s+/', ' ', strtoupper(trim((string) $s)));
    }

    private function normTgl(?string $s): ?string
    {
        $s = trim((string) $s);
        if ($s === '') {
            return null;
        }
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $f) {
            $d = \DateTime::createFromFormat($f, $s);
            if ($d && $d->format($f) === $s) {
                return $d->format('Y-m-d');
            }
        }
        try {
            return (new \DateTime($s))->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}

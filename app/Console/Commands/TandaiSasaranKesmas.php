<?php

namespace App\Console\Commands;

use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Posyandu;
use App\Models\Puskesmas;
use App\Models\Rt;
use App\Support\FilterWilayahAnak;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Penandaan massal "Sasaran Balita Kesmas"
 * (spec docs/superpowers/specs/2026-10-02-kesmas-permintaan-data-design.md §5.3).
 *
 * Dasbor Kesmas opt-in: hanya anak.sasaran_balita_kesmas = 1 yang dihitung. Perintah ini hanya
 * mengubah NULL → 1 (atau, lewat --batalkan, 1 → NULL untuk satu batch). Nilai 0 — centang yang
 * sengaja dilepas petugas — tidak pernah ditimpa.
 *
 * WAJIB query builder, tanpa updated_at, tanpa event model:
 *  - AnakObserver::saved memicu PrioritasGiziService::refreshAnak (modul OT) per anak;
 *  - CapilDedupService::sigiziUntouched() membaca updated_at = created_at sebagai
 *    "belum tersentuh Capil".
 */
class TandaiSasaranKesmas extends Command
{
    use FilterWilayahAnak;

    protected $signature = 'kesmas:tandai-sasaran
        {--kecamatan= : ID kecamatan}
        {--kelurahan= : ID kelurahan}
        {--rt= : ID RT}
        {--posyandu= : ID posyandu}
        {--puskesmas= : ID puskesmas (catchment kelurahan wilker)}
        {--lahir-sejak= : Tanggal lahir paling awal, Y-m-d (inklusif)}
        {--lahir-sampai= : Tanggal lahir paling akhir, Y-m-d (inklusif)}
        {--semua : Izinkan tanpa filter wilayah (seluruh kota)}
        {--termasuk-pindah : Ikut tandai anak dengan verifikasi RT pindah/meninggal}
        {--termasuk-tidak-aktif : Ikut tandai anak berstatus Tidak Aktif}
        {--jalankan : Tulis ke database (tanpa ini = dry-run)}
        {--alasan= : Wajib bersama --jalankan; disimpan di sasaran_kesmas_log}
        {--batalkan= : Kode batch yang dikembalikan ke belum ditandai (NULL)}';

    protected $description = 'Tandai anak sebagai Sasaran Balita Kesmas (dry-run default; hanya NULL → 1; tercatat di sasaran_kesmas_log).';

    /** opsi => [kunci filter FilterWilayahAnak, model untuk cek keberadaan ID]. */
    private const WILAYAH = [
        'kecamatan' => ['id_kecamatan', Kecamatan::class],
        'kelurahan' => ['id_kelurahan', Kelurahan::class],
        'rt'        => ['id_rt', Rt::class],
        'posyandu'  => ['id_posyandu', Posyandu::class],
        'puskesmas' => ['id_puskesmas', Puskesmas::class],
    ];

    private const POTONGAN = 500;

    public function handle(): int
    {
        $alasan = $this->alasanTervalidasi();
        if ($alasan === false) {
            return self::FAILURE;
        }

        if ($this->option('batalkan') !== null) {
            return $this->batalkan((string) $this->option('batalkan'), $alasan);
        }

        $filters = $this->filterTervalidasi();
        if ($filters === null) {
            return self::FAILURE;
        }

        $sejak = $this->option('lahir-sejak');
        $sampai = $this->option('lahir-sampai');
        foreach (['lahir-sejak' => $sejak, 'lahir-sampai' => $sampai] as $opsi => $tgl) {
            if ($tgl !== null && !$this->tanggalSah((string) $tgl)) {
                $this->error("--{$opsi} harus berformat Y-m-d (mis. 2020-01-31).");

                return self::FAILURE;
            }
        }

        // Pengecualian bawaan; '0' = tidak ada pengecualian (opsi --termasuk-*).
        $pindah = $this->option('termasuk-pindah') ? '0' : "COALESCE(a.verif_rt_status, '') IN ('pindah', 'meninggal')";
        $tidakAktif = $this->option('termasuk-tidak-aktif') ? '0' : 'a.status = 0';

        $rekap = $this->dasar($filters, $sejak, $sampai)
            ->selectRaw("a.id_kel, COUNT(*) as total,
                SUM(a.sasaran_balita_kesmas = 1) as bertanda,
                SUM(a.sasaran_balita_kesmas = 0) as dilepas,
                SUM(a.sasaran_balita_kesmas IS NULL AND {$pindah}) as pindah_meninggal,
                SUM(a.sasaran_balita_kesmas IS NULL AND NOT ({$pindah}) AND {$tidakAktif}) as tidak_aktif,
                SUM(a.sasaran_balita_kesmas IS NULL AND NOT ({$pindah}) AND NOT ({$tidakAktif})) as akan")
            ->groupBy('a.id_kel')->orderBy('a.id_kel')->get();
        $this->cetakRekap($rekap);
        $akan = (int) $rekap->sum('akan');

        if (!$this->option('jalankan')) {
            $this->info("DRY-RUN — tidak ada yang diubah. {$akan} anak akan ditandai. Jalankan ulang dengan --jalankan --alasan=\"…\".");

            return self::SUCCESS;
        }

        $ids = $this->dasar($filters, $sejak, $sampai)
            ->whereNull('a.sasaran_balita_kesmas')
            ->whereRaw("NOT ({$pindah})")
            ->whereRaw("NOT ({$tidakAktif})")
            ->orderBy('a.id')->pluck('a.id')->all();

        $batch = (string) Str::uuid();
        $ditandai = 0;
        foreach (array_chunk($ids, self::POTONGAN) as $potongan) {
            $ditandai += $this->tulis($potongan, null, 1, 'perintah', $batch, $alasan);
        }

        $this->info("Selesai: {$ditandai} anak ditandai." . ($ditandai > 0 ? " Kode batch: {$batch} (simpan untuk --batalkan)." : ''));

        return self::SUCCESS;
    }

    private function batalkan(string $batch, ?string $alasan): int
    {
        $diBatch = DB::table('sasaran_kesmas_log')->where('batch', $batch)->where('sumber', 'perintah');
        if (!Str::isUuid($batch) || !(clone $diBatch)->exists()) {
            $this->error("Batch {$batch} tidak ditemukan.");

            return self::FAILURE;
        }

        $semua = (clone $diBatch)->count();
        // Hanya anak yang log TERAKHIR-nya milik batch ini dan nilainya masih 1: perubahan
        // sesudah batch (form atau batch lain) adalah keputusan yang tidak boleh dibatalkan.
        $terakhir = DB::table('sasaran_kesmas_log')
            ->whereIn('id_anak', (clone $diBatch)->select('id_anak'))
            ->selectRaw('id_anak, MAX(id) as id_terakhir')
            ->groupBy('id_anak');
        $ids = DB::table('sasaran_kesmas_log as l')
            ->joinSub($terakhir, 't', 't.id_terakhir', '=', 'l.id')
            ->join('anak as a', 'a.id', '=', 'l.id_anak')
            ->where('l.batch', $batch)->where('l.sumber', 'perintah')
            ->where('a.sasaran_balita_kesmas', 1)
            ->orderBy('l.id_anak')->pluck('l.id_anak')->all();
        $dilewati = $semua - count($ids);

        $this->line("Batch {$batch}: {$semua} anak ditandai; " . count($ids) . " dapat dikembalikan ke belum ditandai, {$dilewati} sudah berubah sejak itu (dilewati).");
        if (!$this->option('jalankan')) {
            $this->info('DRY-RUN — tidak ada yang diubah. Jalankan ulang dengan --jalankan --alasan="…".');

            return self::SUCCESS;
        }

        $dikembalikan = 0;
        foreach (array_chunk($ids, self::POTONGAN) as $potongan) {
            $dikembalikan += $this->tulis($potongan, 1, null, 'batal', $batch, $alasan);
        }
        $this->info("Selesai: {$dikembalikan} anak dikembalikan ke belum ditandai; {$dilewati} dilewati.");

        return self::SUCCESS;
    }

    /**
     * Ubah satu potongan id dari $dari ke $ke dalam satu transaksi. Baris dipilih ULANG dengan kunci
     * (lockForUpdate) supaya perubahan lewat form di sela proses tidak tertimpa.
     *
     * @param  list<int>  $potongan
     */
    private function tulis(array $potongan, ?int $dari, ?int $ke, string $sumber, string $batch, string $alasan): int
    {
        return DB::transaction(function () use ($potongan, $dari, $ke, $sumber, $batch, $alasan) {
            $q = DB::table('anak')->whereIn('id', $potongan);
            $dari === null ? $q->whereNull('sasaran_balita_kesmas') : $q->where('sasaran_balita_kesmas', $dari);
            $kunci = $q->lockForUpdate()->pluck('id')->all();
            if ($kunci === []) {
                return 0;
            }

            // Query builder: TANPA updated_at & TANPA event model (lihat docblock kelas).
            DB::table('anak')->whereIn('id', $kunci)->update(['sasaran_balita_kesmas' => $ke]);
            $waktu = now();
            DB::table('sasaran_kesmas_log')->insert(array_map(fn ($id) => [
                'id_anak' => (int) $id, 'nilai_lama' => $dari, 'nilai_baru' => $ke, 'sumber' => $sumber,
                'batch' => $batch, 'alasan' => $alasan, 'id_user' => null, 'created_at' => $waktu,
            ], $kunci));

            return count($kunci);
        });
    }

    private function dasar(array $filters, ?string $sejak, ?string $sampai): Builder
    {
        $q = DB::table('anak as a');
        $this->applyWilayahFilters($q, $filters, 'a');
        if ($sejak !== null) {
            $q->where('a.tgl_lahir', '>=', $sejak);
        }
        if ($sampai !== null) {
            $q->where('a.tgl_lahir', '<=', $sampai);
        }

        return $q;
    }

    /** @return array<string, int>|null  null = tidak sah (pesan sudah dicetak). */
    private function filterTervalidasi(): ?array
    {
        $filters = [];
        foreach (self::WILAYAH as $opsi => [$kunci, $model]) {
            $nilai = $this->option($opsi);
            if ($nilai === null) {
                continue;
            }
            $id = filter_var($nilai, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false || !$model::whereKey($id)->exists()) {
                $this->error("--{$opsi}={$nilai} tidak ditemukan.");

                return null;
            }
            $filters[$kunci] = $id;
        }
        if ($filters === [] && !$this->option('semua')) {
            $this->error('Sebutkan minimal satu filter wilayah (--kecamatan/--kelurahan/--rt/--posyandu/--puskesmas), atau --semua untuk seluruh kota.');

            return null;
        }

        return $filters;
    }

    /** @return string|null|false  false = tidak sah (pesan sudah dicetak). */
    private function alasanTervalidasi(): string|null|false
    {
        $alasan = $this->option('alasan');
        $alasan = $alasan === null ? null : trim((string) $alasan);
        if ($this->option('jalankan') && ($alasan === null || $alasan === '')) {
            $this->error('--jalankan wajib disertai --alasan="…" (dicatat di sasaran_kesmas_log).');

            return false;
        }
        if ($alasan !== null && mb_strlen($alasan) > 255) {
            $this->error('--alasan maksimal 255 karakter.');

            return false;
        }

        return $alasan;
    }

    private function tanggalSah(string $tgl): bool
    {
        return (bool) preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $tgl, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    private function cetakRekap(Collection $rekap): void
    {
        $nama = Kelurahan::whereIn('id', $rekap->pluck('id_kel')->filter())->pluck('name', 'id');
        $this->table(
            ['Kelurahan', 'Total', 'Akan ditandai', 'Sudah bertanda', 'Dilepas (0)', 'Pindah/meninggal', 'Tidak Aktif'],
            $rekap->map(fn ($r) => [
                $r->id_kel ? ($nama[$r->id_kel] ?? "#{$r->id_kel}") : '(tanpa kelurahan)',
                (int) $r->total, (int) $r->akan, (int) $r->bertanda, (int) $r->dilepas,
                (int) $r->pindah_meninggal, (int) $r->tidak_aktif,
            ])->all()
        );
        $this->line(sprintf(
            'Jumlah: %d anak · akan ditandai %d · sudah bertanda %d · dilepas %d (selalu dilewati) · pindah/meninggal %d · Tidak Aktif %d',
            $rekap->sum('total'), $rekap->sum('akan'), $rekap->sum('bertanda'), $rekap->sum('dilepas'),
            $rekap->sum('pindah_meninggal'), $rekap->sum('tidak_aktif')
        ));
    }
}

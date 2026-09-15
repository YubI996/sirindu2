<?php

namespace App\Jobs;

use App\Imports\PjImport;
use App\Models\ImportLog;
use App\Services\PjAlokasiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Import NIP/nama PJ + alokasi otomatis ke anak sasaran (lihat PjAlokasiService).
 * Ringkasan alokasi ditulis ke ImportLog.failures agar tampil di Riwayat Import.
 */
class ImportPjJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 600;

    public function __construct(protected ImportLog $importLog, public bool $timpa = false) {}

    public function handle(): void
    {
        $this->importLog->update(['status' => 'processing', 'started_at' => now()]);

        try {
            $path = Storage::path($this->importLog->file_path);
            if (!file_exists($path)) {
                throw new \RuntimeException("File tidak ditemukan: {$this->importLog->file_path}");
            }

            $baca = (new PjImport())->baca($path);
            $r    = app(PjAlokasiService::class)->alokasikan($baca['baris'], $this->timpa, (int) $this->importLog->user_id);

            $catatan = array_merge(
                $baca['gagal'],
                $r['gagal'],
                ["{$r['pj']} PJ, {$r['anak']} anak sasaran, {$r['dialokasikan']} dialokasikan, {$r['dilewati']} dilewati"],
                ["{$r['dilewati']} anak dilewati karena sudah punya PJ".($this->timpa ? ' (mode timpa)' : ' — centang "Timpa" untuk mengganti')]
            );

            $this->importLog->update([
                'status'        => 'done',
                'success_count' => $r['dialokasikan'],
                'failure_count' => count($baca['gagal']) + count($r['gagal']),
                'failures'      => $catatan,
                'completed_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error("ImportPjJob gagal [{$this->importLog->id}]: ".$e->getMessage());
            $this->importLog->update([
                'status'       => 'failed',
                'failures'     => ['Import gagal: '.$e->getMessage()],
                'completed_at' => now(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->importLog->update([
            'status'       => 'failed',
            'failures'     => ['Job gagal dieksekusi: '.$exception->getMessage()],
            'completed_at' => now(),
        ]);
    }
}

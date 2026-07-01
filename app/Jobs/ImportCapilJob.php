<?php

namespace App\Jobs;

use App\Imports\CapilImport;
use App\Models\ImportLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ImportCapilJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 1800; // 30 menit — file Capil ~13k baris (xlsx)

    public function __construct(protected ImportLog $importLog) {}

    public function handle(): void
    {
        $this->importLog->update(['status' => 'processing', 'started_at' => now()]);

        try {
            $path = Storage::path($this->importLog->file_path);

            if (!file_exists($path)) {
                throw new \RuntimeException("File tidak ditemukan: {$this->importLog->file_path}");
            }

            // Penjaga sheet: pilih sheet pertama yang TERLIHAT (hindari sheet
            // tersembunyi yang pernah merusak impor — insiden 2026-06-26).
            $sheets = CapilImport::inspectSheets($path);
            $target = CapilImport::firstVisibleSheet($sheets);

            $import = new CapilImport($this->importLog->user_id, null, $target);
            Excel::import($import, $path);
            $results = $import->getResults();

            // Selipkan peringatan multi-sheet di awal daftar kegagalan/info.
            $failures = $results['failures'] ?: [];
            if ($warn = CapilImport::sheetWarning($sheets, $target)) {
                array_unshift($failures, $warn);
            }

            $this->importLog->update([
                'status'        => 'done',
                'success_count' => $results['success'],
                'failure_count' => $results['error_count'],
                'failures'      => $failures ?: null,
                'completed_at'  => now(),
            ]);

        } catch (\Throwable $e) {
            Log::error("ImportCapilJob gagal [{$this->importLog->id}]: " . $e->getMessage());
            $this->importLog->update([
                'status'       => 'failed',
                'failures'     => ["Import gagal: " . $e->getMessage()],
                'completed_at' => now(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->importLog->update([
            'status'       => 'failed',
            'failures'     => ["Job gagal dieksekusi: " . $exception->getMessage()],
            'completed_at' => now(),
        ]);
    }
}

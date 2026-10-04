<?php

namespace App\Jobs;

use App\Services\TautanIdentitasService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/** Pindai ulang kandidat identitas di antrean — dipicu tombol "Pindai ulang" (superadmin). */
class PindaiIdentitasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Kunci cache "pindai sedang berjalan" — mencegah dua pindai bertumpuk. */
    public const KUNCI = 'pindai_identitas_berjalan';

    /** Umur kunci (detik): melebihi $timeout supaya pindai yang mati mendadak tak mengunci selamanya. */
    public const KUNCI_DETIK = 2100;

    public int $timeout = 1800;
    public int $tries = 1;

    public function handle(TautanIdentitasService $svc): void
    {
        try {
            $r = $svc->pindai();
            Log::info("PindaiIdentitasJob selesai: {$r['pasangan']} pasangan.");
        } finally {
            Cache::forget(self::KUNCI);
        }
    }

    public function failed(\Throwable $e): void
    {
        Cache::forget(self::KUNCI);
        Log::error('PindaiIdentitasJob gagal: '.$e->getMessage());
    }
}

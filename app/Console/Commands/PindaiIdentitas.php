<?php

namespace App\Console\Commands;

use App\Services\TautanIdentitasService;
use Illuminate\Console\Command;

class PindaiIdentitas extends Command
{
    protected $signature = 'identitas:pindai';
    protected $description = 'Pindai seluruh anak untuk pasangan yang kemungkinan satu orang (isi ulang anak_kandidat)';

    public function handle(TautanIdentitasService $svc): int
    {
        $r = $svc->pindai();
        $this->info("{$r['pasangan']} pasangan kandidat ditemukan ({$r['dipindai_at']}).");
        foreach ($r['via'] as $via => $n) {
            $this->line("  via {$via}: {$n}");
        }

        return self::SUCCESS;
    }
}

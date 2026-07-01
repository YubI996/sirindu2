<?php

namespace App\Console\Commands;

use App\Models\Anak;
use App\Services\CapilDedupService;
use Illuminate\Console\Command;

/**
 * Gabungkan duplikat hasil import Capil: anak "Capil-baru" (NIK asli, id_kel NULL,
 * tanpa kesehatan) yang sebenarnya kembaran dari anak sigizi "belum tersentuh"
 * (NIK salah/dummy), lolos pencocokan karena NIK beda + ejaan nama beda tipis.
 *
 * Aturan: nama anak >=70% DAN (No KK sama ATAU nama ortu [ibu/ayah, split '/'] >=87%).
 * Merge: kependudukan ikut Capil, domisili+kesehatan ikut sigizi, record Capil dihapus.
 *
 * Default DRY-RUN (hanya hitung, tidak mengubah). Tambah --apply untuk menggabung.
 * Hanya melaporkan jumlah (count) — tidak menampilkan data pribadi apa pun.
 */
class DedupCapil extends Command
{
    protected $signature = 'capil:dedup {--apply : Terapkan merge (tanpa flag ini hanya dry-run)}';

    protected $description = 'Gabungkan duplikat anak Capil-baru dengan record sigizi yang NIK-nya salah.';

    public function handle(CapilDedupService $svc): int
    {
        $apply = (bool) $this->option('apply');
        $this->warn('Mode: ' . ($apply ? 'APPLY (mengubah data)' : 'DRY-RUN (tidak mengubah apa pun)'));
        $this->newLine();

        $r = $svc->run($apply);

        $this->line("Kelompok terdeteksi:");
        $this->line("  Capil-baru (alamat_ktp terisi, alamat & id_kel NULL) : {$r['capil_new']}");
        $this->line("  Sigizi belum tersentuh (alamat_ktp NULL)             : {$r['sigizi_untouched']}");
        $this->newLine();
        $this->info("Pasangan duplikat terkonfirmasi: {$r['pairs']}");
        $this->line("  - via No KK sama       : {$r['via_kk']}");
        $this->line("  - via nama ortu (>=87%): {$r['via_ortu']}");
        $this->newLine();

        if ($apply) {
            $this->info("DITERAPKAN: {$r['merged']} pasang digabung. Total anak sekarang: " . Anak::count());
        } else {
            $this->warn("DRY-RUN — tidak ada perubahan. Jalankan ulang dengan --apply untuk menggabung.");
        }

        return self::SUCCESS;
    }
}

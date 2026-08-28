<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Counter urutan nomor epidemiologi (`no_registrasi`).
 *
 * Format nomor: `[prefix]-1710[YY][NNN]` (AFP/Polio tanpa prefix: `1710[YY][NNN]`).
 * Deret NNN berjalan **per prefix per tahun** — C dan D punya urutan sendiri-sendiri.
 */
class EpidCounter extends Model
{
    use HasFactory;

    protected $table = 'epid_counter';

    protected $fillable = [
        'tahun',
        'prefix',
        'last_sequence',
    ];

    /**
     * Ambil nomor urut berikutnya untuk $prefix di $tahun.
     *
     * Nilai counter diselaraskan dengan nomor yang SUDAH terpakai di
     * surveillance_cases, karena importer (Pd3iImport/HasilLabImport) menulis
     * no_registrasi langsung tanpa menaikkan counter ini. Tanpa penyelarasan,
     * counter tertinggal dan generator mengeluarkan nomor yang sudah ada →
     * kolom no_registrasi UNIQUE → error 500 saat menyimpan kasus baru.
     *
     * @param string $prefix Prefix penyakit ('C','D','P','TN'); '' untuk AFP/Polio.
     */
    public static function getNextSequence(int $tahun, string $prefix = ''): int
    {
        return DB::transaction(function () use ($tahun, $prefix) {
            $counter = static::where('tahun', $tahun)
                ->where('prefix', $prefix)
                ->lockForUpdate()
                ->first();

            // Titik mulai = yang tertinggi antara counter dan nomor terpakai di DB.
            $next = max(
                $counter->last_sequence ?? 0,
                static::maxSequenceTerpakai($tahun, $prefix)
            ) + 1;

            if ($counter) {
                $counter->update(['last_sequence' => $next]);
            } else {
                static::create([
                    'tahun'         => $tahun,
                    'prefix'        => $prefix,
                    'last_sequence' => $next,
                ]);
            }

            return $next;
        });
    }

    /**
     * Nomor urut tertinggi yang sudah terpakai di surveillance_cases untuk
     * $prefix + $tahun. Hanya nomor yang persis mengikuti format resmi yang
     * dihitung — nomor legacy (mis. "KTM9", "KKR3") sengaja diabaikan.
     */
    public static function maxSequenceTerpakai(int $tahun, string $prefix): int
    {
        $yy = substr((string) $tahun, -2);

        // Prefix hanya berisi huruf (lihat peta prefix di SurveillanceRepository),
        // jadi aman disisipkan ke pola regex.
        $pola = $prefix !== ''
            ? '^' . $prefix . '-1710' . $yy . '[0-9]{3}$'
            : '^1710' . $yy . '[0-9]{3}$';

        $row = DB::table('surveillance_cases')
            ->whereRaw('no_registrasi REGEXP ?', [$pola])
            ->selectRaw('MAX(CAST(RIGHT(no_registrasi, 3) AS UNSIGNED)) as maks')
            ->first();

        return (int) ($row->maks ?? 0);
    }

    /**
     * Uraikan no_registrasi resmi menjadi tahun + prefix.
     * Format: `[PREFIX]-1710[YY][NNN]` atau `1710[YY][NNN]` (AFP/Polio tanpa prefix).
     * Kembalikan null untuk nomor legacy/non-format (mis. "KTM9").
     *
     * @return array{tahun:int, prefix:string, urutan:int}|null
     */
    public static function parseNoRegistrasi(string $noReg): ?array
    {
        if (preg_match('/^([A-Z]{1,3})-1710(\d{2})(\d{3})$/', $noReg, $m)) {
            return ['tahun' => 2000 + (int) $m[2], 'prefix' => $m[1], 'urutan' => (int) $m[3]];
        }
        if (preg_match('/^1710(\d{2})(\d{3})$/', $noReg, $m)) {
            return ['tahun' => 2000 + (int) $m[1], 'prefix' => '', 'urutan' => (int) $m[2]];
        }
        return null;
    }

    /**
     * Susun nomor resmi dari komponennya: `[prefix]-1710[YY][NNN]`
     * (AFP/Polio tanpa prefix: `1710[YY][NNN]`).
     */
    public static function formatNoRegistrasi(int $tahun, string $prefix, int $urutan): string
    {
        $nomor = '1710' . substr((string) $tahun, -2) . str_pad((string) $urutan, 3, '0', STR_PAD_LEFT);

        return $prefix === '' ? $nomor : $prefix . '-' . $nomor;
    }

    /**
     * Rapatkan deret setelah sebuah nomor dihapus: semua kasus dengan prefix +
     * tahun yang sama dan urutan LEBIH TINGGI diturunkan satu.
     * Contoh: deret 1..10, nomor 007 hilang → 008;009;010 menjadi 007;008;009.
     *
     * Diproses menaik supaya nomor tujuan selalu baru saja dikosongkan — kolom
     * no_registrasi UNIQUE, kalau menurun urutannya pasti bentrok.
     *
     * PERINGATAN: ini mengubah nomor resmi kasus LAIN yang tak disentuh petugas.
     * Nomor EPID adalah kunci pencocokan HasilLabImport & Pd3iImport, jadi hasil
     * lab yang terlanjur dikirim memakai nomor lama akan menempel ke kasus yang
     * kini memegang nomor itu. Setiap perubahan dicatat di epid_renumber_log.
     * Nomor legacy di luar format resmi (mis. "KTM9") tidak pernah disentuh.
     *
     * @return array<int, array{id:int, lama:string, baru:string}> daftar perubahan
     */
    public static function rapatkanSetelahHapus(int $tahun, string $prefix, int $urutanDihapus): array
    {
        $yy = substr((string) $tahun, -2);
        $pola = $prefix !== ''
            ? '^' . $prefix . '-1710' . $yy . '[0-9]{3}$'
            : '^1710' . $yy . '[0-9]{3}$';

        $kasus = DB::table('surveillance_cases')
            ->whereRaw('no_registrasi REGEXP ?', [$pola])
            ->whereRaw('CAST(RIGHT(no_registrasi, 3) AS UNSIGNED) > ?', [$urutanDihapus])
            ->orderByRaw('CAST(RIGHT(no_registrasi, 3) AS UNSIGNED) ASC')
            ->get(['id', 'no_registrasi']);

        $perubahan = [];

        foreach ($kasus as $baris) {
            $urutanLama = (int) substr($baris->no_registrasi, -3);
            $baru = static::formatNoRegistrasi($tahun, $prefix, $urutanLama - 1);

            DB::table('surveillance_cases')
                ->where('id', $baris->id)
                ->update(['no_registrasi' => $baru]);

            $perubahan[] = [
                'id'   => (int) $baris->id,
                'lama' => $baris->no_registrasi,
                'baru' => $baru,
            ];
        }

        return $perubahan;
    }

    /**
     * Selaraskan counter ke nomor tertinggi yang MASIH terpakai untuk prefix+tahun.
     *
     * Dipanggil setelah penghapusan kasus: counter turun ke nomor tertinggi yang
     * masih ada, sehingga nomor berikutnya menyambung tanpa melompat. Perapatan
     * deretnya sendiri dikerjakan rapatkanSetelahHapus() — method ini hanya
     * menyelaraskan counter dengan keadaan tabel setelahnya.
     */
    public static function syncToUsed(int $tahun, string $prefix): void
    {
        static::updateOrCreate(
            ['tahun' => $tahun, 'prefix' => $prefix],
            ['last_sequence' => static::maxSequenceTerpakai($tahun, $prefix)]
        );
    }
}

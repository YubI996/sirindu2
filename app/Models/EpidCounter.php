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
     * @return array{tahun:int, prefix:string}|null
     */
    public static function parseNoRegistrasi(string $noReg): ?array
    {
        if (preg_match('/^([A-Z]{1,3})-1710(\d{2})\d{3}$/', $noReg, $m)) {
            return ['tahun' => 2000 + (int) $m[2], 'prefix' => $m[1]];
        }
        if (preg_match('/^1710(\d{2})\d{3}$/', $noReg, $m)) {
            return ['tahun' => 2000 + (int) $m[1], 'prefix' => ''];
        }
        return null;
    }

    /**
     * Selaraskan counter ke nomor tertinggi yang MASIH terpakai untuk prefix+tahun.
     *
     * Dipanggil setelah penghapusan kasus: bila yang dihapus adalah nomor terakhir,
     * counter turun sehingga nomor itu bisa dipakai lagi (tak melompat). TIDAK pernah
     * mengisi gap di tengah — nomor bisa milik register resmi/import.
     */
    public static function syncToUsed(int $tahun, string $prefix): void
    {
        static::updateOrCreate(
            ['tahun' => $tahun, 'prefix' => $prefix],
            ['last_sequence' => static::maxSequenceTerpakai($tahun, $prefix)]
        );
    }
}

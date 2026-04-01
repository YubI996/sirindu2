<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EpidCounter extends Model
{
    use HasFactory;

    protected $table = 'epid_counter';

    protected $fillable = [
        'tahun',
        'last_sequence',
    ];

    public static function getNextSequence(int $tahun): int
    {
        return DB::transaction(function () use ($tahun) {
            $counter = static::where('tahun', $tahun)->lockForUpdate()->first();

            if (!$counter) {
                $counter = static::create([
                    'tahun' => $tahun,
                    'last_sequence' => 1,
                ]);

                return 1;
            }

            $counter->increment('last_sequence');

            return $counter->last_sequence;
        });
    }
}

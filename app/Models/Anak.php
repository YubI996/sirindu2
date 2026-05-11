<?php

namespace App\Models;

use App\Traits\HasHashId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Anak extends Model
{
    use HasFactory, HasHashId;

    protected $table = 'anak';
    protected $guarded = [];
    protected $appends = ['hashid'];

    public function kec()
    {
        return $this->belongsTo(Kecamatan::class, 'id_kec', 'id');
    }

    public function kel()
    {
        return $this->belongsTo(Kelurahan::class, 'id_kel', 'id');
    }

    public function rt()
    {
        return $this->belongsTo(Rt::class, 'id_rt', 'id');
    }

    public function puskesmas()
    {
        return $this->belongsTo(Puskesmas::class, 'id_puskesmas', 'id');
    }

    public function posyandu()
    {
        return $this->belongsTo(Posyandu::class, 'id_posyandu', 'id');
    }

    public function dataAnak()
    {
        return $this->hasMany(DataAnak::class, 'id_anak', 'id');
    }

    public function latestDataAnak()
    {
        return $this->hasOne(DataAnak::class, 'id_anak', 'id')->latestOfMany('tgl_kunjungan');
    }

    public function imunisasi()
    {
        return $this->hasMany(Imunisasi::class, 'id_anak', 'id');
    }

    /**
     * Cek apakah NIK anak adalah NIK dummy (digit ke-13 = '9').
     */
    public function isDummyNik(): bool
    {
        return strlen((string) $this->nik) === 16
            && isset($this->nik[12])
            && $this->nik[12] === '9';
    }

    /**
     * Get vaccination completeness status per kelompok (IDL/IBL/ISL).
     *
     * @return array ['IDL' => 'Lengkap'|'Belum Lengkap', 'IBL' => ..., 'ISL' => ...]
     */
    public function statusKelengkapanVaksin(): array
    {
        $detail = $this->detailKelengkapanVaksin();

        return collect($detail)->map(fn($group) => $group['status'])->all();
    }

    /**
     * Get detailed vaccination completeness per kelompok.
     *
     * @return array keyed by kelompok kode with required/received counts and missing vaccines
     */
    public function detailKelengkapanVaksin(): array
    {
        $kelompokList = KelompokVaksin::with(['jenisVaksin' => fn($q) => $q->whereNull('deleted_at')])->get();
        $receivedIds = $this->imunisasi()
            ->where('status', 'sudah')
            ->pluck('id_jenis_vaksin')
            ->toArray();

        $isLakiLaki = $this->jk == 1;
        $result = [];

        foreach ($kelompokList as $kelompok) {
            $requiredVaccines = $kelompok->jenisVaksin;

            // For ISL: exclude HPV vaccines for males
            if ($kelompok->kode === 'ISL' && $isLakiLaki) {
                $requiredVaccines = $requiredVaccines->filter(
                    fn($v) => !in_array($v->kode, ['HPV1', 'HPV2'])
                );
            }

            $requiredCount = $requiredVaccines->count();
            $receivedCount = $requiredVaccines->whereIn('id', $receivedIds)->count();
            $missingVaccines = $requiredVaccines->whereNotIn('id', $receivedIds)->pluck('nama')->values()->all();

            $result[$kelompok->kode] = [
                'nama' => $kelompok->nama,
                'required' => $requiredCount,
                'received' => $receivedCount,
                'missing' => $missingVaccines,
                'status' => $receivedCount >= $requiredCount ? 'Lengkap' : 'Belum Lengkap',
            ];
        }

        return $result;
    }

    /**
     * Get catch-up (kejar) vaccination status.
     * kejar_idl: age >11 months AND <=60 months AND IDL belum lengkap.
     * kejar_ibl: age >23 months AND <=60 months AND IBL belum lengkap.
     *
     * @return array ['kejar_idl' => bool, 'kejar_ibl' => bool]
     */
    public function statusKejarVaksin(): array
    {
        $usiaBulan = Carbon::parse($this->tgl_lahir)->diffInMonths(now());
        $status = $this->statusKelengkapanVaksin();

        return [
            'kejar_idl' => $usiaBulan > 11 && $usiaBulan <= 60 && ($status['IDL'] ?? '') === 'Belum Lengkap',
            'kejar_ibl' => $usiaBulan > 23 && $usiaBulan <= 60 && ($status['IBL'] ?? '') === 'Belum Lengkap',
        ];
    }
}

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
     * Cek apakah NIK anak adalah NIK dummy (indeks ke-12 / digit ke-13 basis 1 = '9').
     */
    public function isDummyNik(): bool
    {
        return \App\Services\NikDummyService::isDummy((string) $this->nik);
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
     * Get catch-up (kejar) vaccination status using per-vaccine rules.
     *
     * @return array{kejar_idl: bool, kejar_ibl: bool, vaksin_kejar: string[]}
     */
    public function statusKejarVaksin(): array
    {
        $service = app(\App\Services\ImunisasiStatusService::class);
        $jadwal  = $service->getJadwal($this);

        $vaksinKejar = [];
        $kejarIdl    = false;
        $kejarIbl    = false;

        foreach ($jadwal as $item) {
            if ($item['status'] !== 'terlambat') {
                continue;
            }

            $vaksin = $item['vaksin'];
            $vaksinKejar[] = $vaksin->nama;

            $kelompokKode = $vaksin->kelompokVaksin?->kode;
            if ($kelompokKode === 'IDL') {
                $kejarIdl = true;
            } elseif ($kelompokKode === 'IBL') {
                $kejarIbl = true;
            }
        }

        return [
            'kejar_idl'    => $kejarIdl,
            'kejar_ibl'    => $kejarIbl,
            'vaksin_kejar' => $vaksinKejar,
        ];
    }

    /**
     * Check if child has completed IDL (all required vaccines for 0–12 months).
     */
    public function statusIdl(): bool
    {
        return app(\App\Services\ImunisasiStatusService::class)->isIdlLengkap($this);
    }
}

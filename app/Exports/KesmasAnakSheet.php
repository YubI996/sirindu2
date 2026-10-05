<?php

namespace App\Exports;

use App\Models\Anak;
use App\Services\KesmasPresenter as K;
use App\Support\TahunSasaranKesmas;
use Generator;
use Illuminate\Database\Eloquent\Builder;

/** Sheet "Per Anak" — satu baris per anak yang lolos filter wilayah (spec §5). */
final class KesmasAnakSheet
{
    /** Kolom yang tetap numerik: BBL, PBL, LK lahir, usia kehamilan (R–U) + enam tahun sasaran (AI–AN). */
    private const KOLOM_ANGKA = ['R', 'S', 'T', 'U', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN'];

    public function __construct(private array $filter) {}

    /** Filter wilayah dipakai kedua sheet; $prefix 'anak.' bila query-nya join. */
    public static function terapkanWilayah(Builder $q, array $filter, string $prefix = ''): Builder
    {
        foreach (['id_kec', 'id_kel', 'id_puskesmas', 'id_posyandu'] as $k) {
            if (!empty($filter[$k])) {
                $q->where($prefix . $k, $filter[$k]);
            }
        }

        return $q;
    }

    /** `id` sebagai pengurut kedua: nama kembar tanpa itu bisa terduplikasi/terlewat antar potongan. */
    public function query(): Builder
    {
        return self::terapkanWilayah(
            Anak::query()->with(['kec', 'kel', 'rt', 'puskesmas', 'posyandu'])->orderBy('nama')->orderBy('id'),
            $this->filter
        );
    }

    /**
     * Judul lalu satu baris per anak, dibaca per 200 (eager load per potongan) — tidak pernah
     * `get()` seluruhnya. Anak berkolom ±70, jadi potongan besar cepat menjadi puluhan MB.
     * Judul sengaja bagian dari aliran ini agar hasil kosong tetap berjudul.
     *
     * @return Generator<int, list<string|int|float|null>>
     */
    public function baris(): Generator
    {
        yield $this->headings();

        $angka = KesmasExport::indeksKolom(self::KOLOM_ANGKA);
        foreach ($this->query()->lazy(200) as $anak) {
            yield KesmasExport::rapikan($this->map($anak), $angka);
        }
    }

    public function title(): string
    {
        return 'Per Anak';
    }

    public function headings(): array
    {
        return [
            'NIK', 'Nama', 'JK', 'Tgl Lahir', 'Kecamatan', 'Kelurahan', 'RT', 'Puskesmas', 'Posyandu',
            'No ID ePus', 'FKTP BPJS', 'Air Bersih', 'Jamban Sehat', 'Perokok Serumah', 'TK/PAUD', 'Penyakit Penyerta', 'PJB',
            'BBL (kg)', 'PBL (cm)', 'LK Lahir (cm)', 'Usia Kehamilan (mgg)', 'Tempat Bersalin', 'Jenis Persalinan', 'Penolong',
            'IMD', 'KEK Ibu', 'SHK', 'SHAK', 'G6PD', 'Hepatitis B', 'Komplikasi Persalinan', 'Komplikasi Neonatal',
            // Spec 2026-10-02 §6.2 — di ujung kanan agar kolom numerik R–U tidak bergeser.
            'Tgl HBIG', 'Sasaran Balita Kesmas', 'Thn Sasaran IDL', 'Thn Sasaran 24 bln', 'Thn Sasaran IBL',
            'Thn Sasaran 48 bln', 'Thn Sasaran 60 bln', 'Thn Sasaran 72 bln',
        ];
    }

    /** @param Anak $a */
    public function map($a): array
    {
        return [
            $a->nik, $a->nama, $a->jk == 1 ? 'L' : 'P', $a->tgl_lahir,
            $a->kec->name ?? '', $a->kel->name ?? '', $a->rt->name ?? '', $a->puskesmas->name ?? '', $a->posyandu->name ?? '',
            $a->no_id_epus, $a->fktp_bpjs, K::yaTidak($a->air_bersih), K::yaTidak($a->jamban_sehat), K::yaTidak($a->merokok_keluarga),
            $a->status_tk_paud, $a->penyakit_penyerta, $a->pjb,
            $a->bbl, $a->pbl, $a->lk_lahir, $a->usia_kehamilan_lahir, $a->tempat_bersalin, $a->jenis_persalinan, $a->penolong_lahir,
            K::yaTidak($a->imd), K::yaTidak($a->riwayat_kek_ibu),
            K::enumLabel('skrining', $a->skrining_shk), K::enumLabel('skrining', $a->skrining_shak),
            K::enumLabel('skrining', $a->skrining_g6pd), K::enumLabel('hepatitis_b', $a->pemeriksaan_hepatitis_b),
            $a->komplikasi_persalinan, $a->komplikasi_neonatal,
            $a->tgl_hbig, K::yaTidak($a->sasaran_balita_kesmas),
            ...self::tahunSasaran($a->tgl_lahir),
        ];
    }

    /** @return list<int|null> enam tahun sasaran (rumus Kesmas), kosong bila tanggal lahir tak sah. */
    private static function tahunSasaran(?string $tglLahir): array
    {
        $t = TahunSasaranKesmas::coba($tglLahir);

        return $t ? array_column($t->semua(), 'tahun') : array_fill(0, count(TahunSasaranKesmas::TAHAP), null);
    }
}

<?php

namespace App\Support;

use App\Models\Puskesmas;

/**
 * Filter wilayah untuk query anak — dipakai ImunisasiStatusService dan
 * KesmasDashboardService supaya "wilayah terpilih" berarti sama di semua dasbor.
 *
 * Semua dimensi yang diisi digabung dengan AND (bukan saling meniadakan) —
 * cascading filter UI hanya pernah mengisi satu jalur konsisten (mis. kelurahan
 * yang benar-benar ada di kecamatan terpilih), sekaligus bisa mengombinasikan
 * puskesmas (wilker, lintas kelurahan) dengan RT tanpa saling menimpa.
 */
trait FilterWilayahAnak
{
    /** Resolusi wilayah sekali per ID pada instance service untuk request ini. */
    private array $catchmentPuskesmas = [];

    /**
     * @template TQuery of \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     * @param  TQuery  $query
     * @param  array{id_kecamatan?: int, id_kelurahan?: int, id_rt?: int, id_posyandu?: int, id_puskesmas?: int}  $filters
     * @param  string  $alias  alias tabel anak bila query memakai `anak as a` (kosong = tanpa prefiks)
     * @return TQuery
     */
    protected function applyWilayahFilters($query, array $filters, string $alias = '')
    {
        $kolom = fn (string $nama) => $alias === '' ? $nama : "{$alias}.{$nama}";

        if (!empty($filters['id_kecamatan'])) {
            $query->where($kolom('id_kec'), $filters['id_kecamatan']);
        }
        if (!empty($filters['id_kelurahan'])) {
            $query->where($kolom('id_kel'), $filters['id_kelurahan']);
        }
        if (!empty($filters['id_rt'])) {
            $query->where($kolom('id_rt'), $filters['id_rt']);
        }
        if (!empty($filters['id_posyandu'])) {
            $query->where($kolom('id_posyandu'), $filters['id_posyandu']);
        }
        if (!empty($filters['id_puskesmas'])) {
            $id = (int) $filters['id_puskesmas'];
            if (!array_key_exists($id, $this->catchmentPuskesmas)) {
                $namaPuskesmas = Puskesmas::whereKey($id)->value('name');
                $this->catchmentPuskesmas[$id] = $namaPuskesmas ? WilkerPuskesmas::catchmentKelurahanIds($namaPuskesmas) : [];
            }
            $kelIds = $this->catchmentPuskesmas[$id];
            $query->whereIn($kolom('id_kel'), $kelIds ?: [0]);
        }

        return $query;
    }
}

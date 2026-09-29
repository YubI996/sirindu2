<?php
// app/Support/RingkasanSpm.php

namespace App\Support;

/**
 * Agregat lima kartu dasbor SPM dari sekumpulan CapaianSpm.
 * Spec: docs/superpowers/specs/2026-09-29-dasbor-spm-design.md §5.
 *
 * Rata-rata = rata-rata ARITMETIK persen antar kategori, tanpa pembobotan —
 * bukan Σkumulatif ÷ Σsasaran, karena satuan antar kategori berbeda
 * (1.000 orang + 40 posyandu bukan 1.040 apa pun). Kategori yang belum
 * dilaporkan atau tanpa sasaran TIDAK ikut sebagai 0.
 */
final class RingkasanSpm
{
    /**
     * @param array<int,CapaianSpm> $daftar
     * @return array{jumlah_kategori:int, rata_rata:?float, dihitung:int, tercapai:int, tertinggal:int, belum:int, tanpa_sasaran:int, per_status:array<string,int>}
     */
    public static function dari(array $daftar): array
    {
        $perStatus = [
            CapaianSpm::STATUS_BELUM         => 0,
            CapaianSpm::STATUS_TANPA_SASARAN => 0,
            CapaianSpm::STATUS_TERCAPAI      => 0,
            CapaianSpm::STATUS_SESUAI        => 0,
            CapaianSpm::STATUS_TERTINGGAL    => 0,
            CapaianSpm::STATUS_KRITIS        => 0,
        ];

        $persen = [];

        foreach ($daftar as $capaian) {
            $perStatus[$capaian->status()]++;

            if ($capaian->persen() !== null) {
                $persen[] = $capaian->persen();
            }
        }

        return [
            'jumlah_kategori' => count($daftar),
            'rata_rata'       => $persen === [] ? null : array_sum($persen) / count($persen),
            'dihitung'        => count($persen),
            'tercapai'        => $perStatus[CapaianSpm::STATUS_TERCAPAI],
            'tertinggal'      => $perStatus[CapaianSpm::STATUS_TERTINGGAL] + $perStatus[CapaianSpm::STATUS_KRITIS],
            'belum'           => $perStatus[CapaianSpm::STATUS_BELUM],
            'tanpa_sasaran'   => $perStatus[CapaianSpm::STATUS_TANPA_SASARAN],
            'per_status'      => $perStatus,
        ];
    }
}

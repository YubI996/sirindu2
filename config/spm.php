<?php

/*
 * Dasbor & Master Data SPM — SATU sumber ambang, label, dan warna status.
 * Spec: docs/superpowers/specs/2026-09-29-dasbor-spm-design.md §3.
 *
 * Ambang dibandingkan terhadap rasioLaju (kumulatif ÷ prorata), BUKAN persen:
 * 45 % di TW II sehat, 45 % di TW IV kritis.
 */
return [
    'ambang' => [
        'sesuai'     => 0.90,
        'tertinggal' => 0.60,
    ],

    'status' => [
        'belum'         => ['label' => 'Belum dilaporkan', 'badge' => 'bg-secondary', 'warna' => '#94a3b8'],
        'tanpa_sasaran' => ['label' => 'Tanpa sasaran',    'badge' => 'bg-secondary', 'warna' => '#cbd5e1'],
        'tercapai'      => ['label' => 'Tercapai',         'badge' => 'bg-success',   'warna' => '#047857'],
        'sesuai'        => ['label' => 'Sesuai laju',      'badge' => 'bg-info',      'warna' => '#1d4ed8'],
        'tertinggal'    => ['label' => 'Tertinggal',       'badge' => 'bg-warning',   'warna' => '#b45309'],
        'kritis'        => ['label' => 'Kritis',           'badge' => 'bg-danger',    'warna' => '#b91c1c'],
    ],

    'tahun_min' => 2020,
];

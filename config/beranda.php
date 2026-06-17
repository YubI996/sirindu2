<?php

/*
|--------------------------------------------------------------------------
| Quicklink Beranda (Paket F)
|--------------------------------------------------------------------------
|
| Daftar kanonik kandidat quicklink yang tampil di beranda. Saat render,
| daftar disaring berdasar "role beranda" user (lihat AdminController::
| berandaRole) lalu diiris dengan preferensi tampil milik user.
|
| Token role: 'superadmin' | 'surveilans' | 'imunisasi'
| Urutan array = urutan tampil (tanpa drag-reorder, sesuai spec Paket F).
|
*/

return [
    'quicklinks' => [
        [
            'key'   => 'analytics',
            'label' => 'Dashboard Imunisasi',
            'icon'  => 'fa-chart-bar',
            'route' => 'admin.analytics',
            'roles' => ['superadmin', 'imunisasi'],
        ],
        [
            'key'   => 'timbang',
            'label' => 'Dashboard Timbang',
            'icon'  => 'fa-weight',
            'route' => 'admin.timbang.dashboard',
            'roles' => ['superadmin'],
        ],
        [
            'key'   => 'pd3i',
            'label' => 'Dashboard PD3I',
            'icon'  => 'fa-clipboard-list',
            'route' => 'admin.pd3i.dashboard',
            'roles' => ['superadmin'],
        ],
        [
            'key'   => 'surveilans',
            'label' => 'Dashboard Surveilans',
            'icon'  => 'fa-notes-medical',
            'route' => 'admin.epidemiologi.dashboard',
            'roles' => ['superadmin', 'surveilans'],
        ],
        [
            'key'   => 'map',
            'label' => 'Peta Statistik',
            'icon'  => 'fa-map-marked-alt',
            'route' => 'admin.map',
            'roles' => ['superadmin', 'imunisasi'],
        ],
        [
            'key'   => 'epidemiologi_map',
            'label' => 'Peta Sebaran',
            'icon'  => 'fa-map',
            'route' => 'admin.epidemiologi.map',
            'roles' => ['superadmin', 'surveilans'],
        ],
        [
            'key'   => 'early_warning',
            'label' => 'Proyeksi',
            'icon'  => 'fa-chart-line',
            'route' => 'admin.earlyWarning',
            'roles' => ['superadmin', 'imunisasi'],
        ],
        [
            'key'   => 'anak',
            'label' => 'Data Anak',
            'icon'  => 'fa-child',
            'route' => 'admin.anak',
            'roles' => ['superadmin', 'imunisasi'],
        ],
        [
            'key'   => 'export',
            'label' => 'Export Data',
            'icon'  => 'fa-file-export',
            'route' => 'admin.export.imunisasi.index',
            'roles' => ['superadmin', 'imunisasi'],
        ],
        [
            'key'   => 'import',
            'label' => 'Import CSV',
            'icon'  => 'fa-file-upload',
            'route' => 'admin.importCsv.index',
            'roles' => ['superadmin', 'imunisasi'],
        ],
        [
            'key'   => 'pengguna',
            'label' => 'Pengguna',
            'icon'  => 'fa-user-shield',
            'route' => 'super.admin.user',
            'roles' => ['superadmin'],
        ],
    ],
];

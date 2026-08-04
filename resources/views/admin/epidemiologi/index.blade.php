@extends('admin::layouts.app')
@push('styles')
<link rel="stylesheet" type="text/css" href="{{ asset('admin/src/plugins/datatables/css/dataTables.bootstrap4.min.css') }}">
<link rel="stylesheet" type="text/css" href="{{ asset('admin/src/plugins/datatables/css/responsive.bootstrap4.min.css') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">
@endpush
@push('js')
<script src="{{ asset('admin/src/plugins/datatables/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('admin/src/plugins/datatables/js/dataTables.bootstrap4.min.js') }}"></script>
<script src="{{ asset('admin/src/plugins/datatables/js/dataTables.responsive.min.js') }}"></script>
<script src="{{ asset('admin/src/plugins/datatables/js/responsive.bootstrap4.min.js') }}"></script>
@endpush
@section('title') Admin @endsection
@section('title-content') Epidemiologi @endsection
@section('item') Surveillance @endsection
@section('item-active') Kasus @endsection

@section('content')
{{-- Skip Link for Accessibility --}}
<a href="#main-content" class="sr-only sr-only-focusable skip-link">Langsung ke konten utama</a>

<style>
    :root {
        /* Brand primary — Kemenkes RI green */
        --st-primary: oklch(0.48 0.14 145);
        --st-primary-dark: oklch(0.38 0.13 145);
        --st-primary-light: oklch(0.96 0.022 145);
        --st-secondary: #047857;
        --st-bg: #f5f7f8;
        --st-surface: #ffffff;
        --st-text: #0f172a;
        --st-text-muted: #64748b;
        --st-border: #e2e8f0;
        --st-border-light: #f1f5f9;
        --st-radius: 12px;
        --st-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.06), 0 1px 2px -1px rgb(0 0 0 / 0.06);
        --st-shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.07), 0 2px 4px -2px rgb(0 0 0 / 0.07);
    }

    .epi-page {
        font-family: 'Barlow', sans-serif;
        color: var(--st-text);
    }

    /* -- Page Header -- */
    .epi-header {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        margin-bottom: 28px;
    }
    .epi-header__left {
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .epi-header__icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 48px; height: 48px;
        background: var(--st-primary-light);
        border-radius: 12px;
        color: var(--st-primary);
    }
    .epi-header__icon .material-symbols-outlined { font-size: 28px; }
    .epi-header__title {
        font-size: 1.75rem;
        font-weight: 800;
        color: var(--st-text);
        letter-spacing: -0.02em;
        margin: 0; line-height: 1.2;
    }
    .epi-header__subtitle {
        font-size: 0.875rem;
        color: var(--st-text-muted);
        font-weight: 500;
        margin: 2px 0 0;
    }
    .epi-header__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    /* -- Stitch Buttons -- */
    .st-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 0 18px;
        height: 42px;
        border-radius: var(--st-radius);
        font-family: 'Barlow', sans-serif;
        font-weight: 700;
        font-size: 0.8125rem;
        border: 1.5px solid transparent;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none !important;
        white-space: nowrap;
    }
    .st-btn .material-symbols-outlined { font-size: 20px; }
    .st-btn-primary {
        background: var(--st-primary);
        color: #fff;
        border-color: var(--st-primary);
        box-shadow: var(--st-shadow-md);
    }
    .st-btn-primary:hover {
        background: var(--st-primary-dark);
        border-color: var(--st-primary-dark);
        color: #fff;
        box-shadow: 0 6px 12px -3px oklch(0.48 0.14 145 / 0.35);
        transform: translateY(-1px);
    }
    .st-btn-outline-teal {
        background: #fff;
        color: #0d9488;
        border-color: #0d9488;
    }
    .st-btn-outline-teal:hover {
        background: #f0fdfa;
        color: #0f766e;
    }
    .st-btn-outline-green {
        background: #fff;
        color: #059669;
        border-color: #059669;
    }
    .st-btn-outline-green:hover {
        background: #ecfdf5;
        color: #047857;
    }
    .st-btn-outline-secondary {
        background: #fff;
        color: var(--st-text-muted);
        border-color: var(--st-border);
    }
    .st-btn-outline-secondary:hover {
        background: #f8fafc;
        color: var(--st-text);
    }

    /* -- Cards -- */
    .st-card {
        background: var(--st-surface);
        border-radius: var(--st-radius);
        border: 1px solid var(--st-border);
        box-shadow: var(--st-shadow);
        overflow: hidden;
    }
    .st-card__header {
        background: var(--st-primary);
        padding: 14px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .st-card__header-title {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #fff;
        font-size: 1.05rem;
        font-weight: 700;
        margin: 0;
    }
    .st-card__header-title .material-symbols-outlined { font-size: 22px; }
    .st-card__body {
        padding: 24px;
    }

    /* -- Filter Grid -- */
    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }
    .filter-group label {
        font-size: 0.8125rem;
        font-weight: 700;
        color: #334155;
        margin-bottom: 8px;
        display: block;
    }
    .filter-group select,
    .filter-group input {
        width: 100%;
        height: 42px;
        padding: 0 14px;
        border-radius: var(--st-radius);
        border: 1px solid #cbd5e1;
        background: #f8fafc;
        color: #334155;
        font-family: 'Barlow', sans-serif;
        font-size: 0.875rem;
        font-weight: 500;
        transition: border-color 0.2s, box-shadow 0.2s;
        -webkit-appearance: none;
        appearance: none;
    }
    .filter-group select {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 24 24' fill='%2394a3b8'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 12px center;
        padding-right: 38px;
    }
    .filter-group select:focus,
    .filter-group input:focus {
        outline: none;
        border-color: var(--st-primary);
        box-shadow: 0 0 0 3px oklch(0.48 0.14 145 / 0.15);
    }
    .filter-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        padding-top: 16px;
        border-top: 1px solid var(--st-border-light);
    }

    /* -- Table Header Bar -- */
    .st-table-bar {
        padding: 16px 24px;
        border-bottom: 1px solid var(--st-border-light);
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        background: linear-gradient(to right, #f8fafc, #ffffff);
    }
    .st-table-bar h3 {
        font-size: 1.05rem;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
    }
    .st-table-bar h3 .count {
        font-weight: 500;
        font-size: 0.8125rem;
        color: var(--st-text-muted);
        margin-left: 8px;
    }

    /* -- DataTable Override Styles -- */
    .st-card .dataTables_wrapper {
        font-family: 'Barlow', sans-serif;
    }
    .st-card .dataTables_wrapper .dataTables_filter input {
        height: 38px;
        padding: 0 14px 0 38px;
        border-radius: var(--st-radius);
        border: 1px solid #cbd5e1;
        background: #fff;
        font-size: 0.8125rem;
        font-family: 'Barlow', sans-serif;
        min-width: 250px;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .st-card .dataTables_wrapper .dataTables_filter input:focus {
        border-color: var(--st-primary);
        box-shadow: 0 0 0 3px oklch(0.48 0.14 145 / 0.15);
        outline: none;
    }
    .st-card .dataTables_wrapper .dataTables_length select {
        height: 32px;
        padding: 0 28px 0 10px;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        font-family: 'Barlow', sans-serif;
        font-weight: 600;
        font-size: 0.8125rem;
    }
    .st-card table.dataTable {
        border-collapse: collapse !important;
        width: 100% !important;
    }
    .st-card table.dataTable thead th {
        background: #f8fafc;
        border-bottom: 2px solid var(--st-border);
        color: var(--st-text-muted);
        font-size: 0.6875rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        padding: 14px 16px;
        white-space: nowrap;
    }
    .st-card table.dataTable tbody td {
        padding: 14px 16px;
        font-size: 0.8125rem;
        font-weight: 500;
        color: #334155;
        border-bottom: 1px solid var(--st-border-light);
        vertical-align: middle;
    }
    .st-card table.dataTable tbody tr:nth-child(even) {
        background: rgba(248, 250, 252, 0.5);
    }
    .st-card table.dataTable tbody tr:hover {
        background: oklch(0.96 0.022 145 / 0.5) !important;
    }

    /* -- Badge overrides for DataTable content -- */
    .st-card .badge.bg-warning,
    .st-card .badge.bg-info,
    .st-card .badge.bg-danger,
    .st-card .badge.bg-secondary,
    .st-card .badge.bg-success {
        display: inline-flex;
        align-items: center;
        padding: 4px 12px;
        border-radius: 9999px;
        font-size: 0.6875rem;
        font-weight: 700;
        font-family: 'Barlow', sans-serif;
        letter-spacing: 0.01em;
        border: 1px solid transparent;
    }
    .st-card .badge.bg-danger {
        background: #fef2f2 !important;
        color: #b91c1c !important;
        border-color: #fecaca;
    }
    .st-card .badge.bg-warning {
        background: #fffbeb !important;
        color: #b45309 !important;
        border-color: #fde68a;
    }
    .st-card .badge.bg-info {
        background: #eff6ff !important;
        color: #1d4ed8 !important;
        border-color: #bfdbfe;
    }
    .st-card .badge.bg-secondary {
        background: #f1f5f9 !important;
        color: #475569 !important;
        border-color: #e2e8f0;
    }
    .st-card .badge.bg-success {
        background: #ecfdf5 !important;
        color: #047857 !important;
        border-color: #a7f3d0;
    }

    /* -- Action Buttons in Table -- */
    .st-card .btn-group .btn {
        width: 32px; height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        border: none;
        padding: 0;
        font-size: 14px;
        opacity: 0.75;
        transition: all 0.2s ease;
    }
    .st-card table.dataTable tbody tr:hover .btn-group .btn {
        opacity: 1;
    }
    .st-card .btn-group .btn.btn-sm.btn-info {
        background: transparent;
        color: #2563eb;
    }
    .st-card .btn-group .btn.btn-sm.btn-info:hover {
        background: #dbeafe;
    }
    .st-card .btn-group .btn.btn-sm.btn-warning {
        background: transparent;
        color: #d97706;
    }
    .st-card .btn-group .btn.btn-sm.btn-warning:hover {
        background: #fef3c7;
    }
    .st-card .btn-group .btn.btn-sm.btn-danger {
        background: transparent;
        color: #dc2626;
    }
    .st-card .btn-group .btn.btn-sm.btn-danger:hover {
        background: #fee2e2;
    }

    /* -- DataTable Pagination Override -- */
    .st-card .dataTables_wrapper .dataTables_paginate .paginate_button {
        min-width: 32px; height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px !important;
        border: 1px solid var(--st-border) !important;
        background: #fff !important;
        color: var(--st-text-muted) !important;
        font-size: 0.8125rem;
        font-weight: 600;
        font-family: 'Barlow', sans-serif;
        margin: 0 2px;
        transition: all 0.15s ease;
        padding: 0 8px !important;
    }
    .st-card .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: #f1f5f9 !important;
        color: var(--st-text) !important;
        border-color: #cbd5e1 !important;
    }
    .st-card .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: var(--st-primary) !important;
        color: #fff !important;
        border-color: var(--st-primary) !important;
        box-shadow: 0 1px 3px rgba(0, 102, 204, 0.3);
    }
    .st-card .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
        opacity: 0.4;
    }
    .st-card .dataTables_wrapper .dataTables_info {
        font-size: 0.8125rem;
        font-weight: 500;
        color: var(--st-text-muted);
        font-family: 'Barlow', sans-serif;
        padding: 16px 24px;
    }
    .st-card .dataTables_wrapper .dataTables_paginate {
        padding: 16px 24px;
    }

    /* -- Delete Modal Override -- */
    .st-modal .modal-content {
        border-radius: var(--st-radius);
        border: none;
        box-shadow: 0 20px 60px -15px rgba(0, 0, 0, 0.25);
        overflow: hidden;
    }
    .st-modal .modal-header {
        background: linear-gradient(135deg, #dc2626, #e11d48);
        color: #fff;
        border: none;
        padding: 18px 24px;
    }
    .st-modal .modal-title {
        font-family: 'Barlow', sans-serif;
        font-weight: 700;
        font-size: 1.05rem;
    }
    .st-modal .modal-body {
        padding: 24px;
        font-family: 'Barlow', sans-serif;
    }
    .st-modal .modal-body p {
        font-size: 0.9375rem;
        color: #334155;
    }
    .st-modal .modal-footer {
        border-top: 1px solid var(--st-border-light);
        padding: 16px 24px;
    }
    .st-modal .modal-footer .btn {
        border-radius: var(--st-radius);
        font-family: 'Barlow', sans-serif;
        font-weight: 700;
        font-size: 0.8125rem;
        padding: 8px 20px;
    }

    /* -- Responsive helpers -- */
    @media (max-width: 768px) {
        .epi-header { flex-direction: column; }
        .epi-header__title { font-size: 1.4rem; }
        .filter-grid { grid-template-columns: 1fr; }
        .filter-actions { justify-content: stretch; }
        .filter-actions .st-btn { flex: 1; justify-content: center; }
    }
</style>

<div class="epi-page" id="main-content" role="main">
    {{-- ===== Page Header ===== --}}
    <div class="epi-header">
        <div class="epi-header__left">
            <div class="epi-header__icon">
                <span class="material-symbols-outlined">assignment</span>
            </div>
            <div>
                <h1 class="epi-header__title">Daftar Kasus Surveillance</h1>
                <p class="epi-header__subtitle">Kelola data kasus penyakit menular & tidak menular</p>
            </div>
        </div>
        <div class="epi-header__actions">
            <a href="{{ route('admin.epidemiologi.dashboard') }}" class="st-btn st-btn-outline-teal" aria-label="Buka Dashboard Analytics">
                <span class="material-symbols-outlined">analytics</span>
                Dashboard Analytics
            </a>
            <a href="{{ route('admin.pd3i.dashboard') }}" class="st-btn st-btn-outline-green" aria-label="Buka Peta Sebaran (Dasbor PD3I)">
                <span class="material-symbols-outlined">map</span>
                Peta Sebaran
            </a>
            @if(!$isFaskes)
            <a href="{{ route('admin.epidemiologi.exportExcel') }}" class="st-btn st-btn-success" aria-label="Export ke Excel">
                <span class="material-symbols-outlined">download</span>
                Export Excel
            </a>
            <button type="button" class="st-btn st-btn-outline-teal" data-toggle="modal" data-target="#modalImportPd3i" aria-label="Import Excel PD3I">
                <span class="material-symbols-outlined">upload_file</span>
                Import Excel PD3I
            </button>
            <button type="button" class="st-btn st-btn-outline-teal" data-toggle="modal" data-target="#modalImportHasilLab" aria-label="Import Hasil Lab CSV">
                <span class="material-symbols-outlined">biotech</span>
                Import Hasil Lab
            </button>
            @endif
            <a href="{{ route('admin.epidemiologi.create') }}" class="st-btn st-btn-primary" aria-label="Tambah Kasus Baru">
                <span class="material-symbols-outlined">add</span>
                Tambah Kasus Baru
            </a>
        </div>
    </div>

    {{-- ===== Panel Status Import ===== --}}
    @if(!$isFaskes && $importLogs->isNotEmpty())
    <div class="st-card mb-4" id="import-status-panel">
        <div class="st-card__header" style="display:flex;justify-content:space-between;align-items:center;">
            <h3 class="st-card__header-title" style="margin:0;">
                <span class="material-symbols-outlined">history</span>
                Status Import PD3I
            </h3>
            <div class="d-flex align-items-center gap-2">
                <span class="small text-muted mr-2" id="import-last-refresh"></span>
                @if($importLogs->whereIn('status', ['done','failed'])->isNotEmpty())
                <button id="btn-hapus-semua-log" class="btn btn-xs btn-outline-danger">
                    <span class="material-symbols-outlined" style="font-size:.9rem;vertical-align:middle;">delete_sweep</span>
                    Hapus Semua
                </button>
                @endif
            </div>
        </div>
        <div class="st-card__body p-0">
            <table class="table table-sm mb-0" id="import-log-table">
                <thead class="thead-light">
                    <tr>
                        <th>File</th>
                        <th>Status</th>
                        <th>Berhasil</th>
                        <th>Dilewati</th>
                        <th>Waktu Selesai</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($importLogs as $log)
                    <tr data-log-id="{{ $log->id }}" data-status="{{ $log->status }}">
                        <td class="small">{{ $log->filename }}</td>
                        <td>
                            <span class="badge badge-{{ $log->statusColor() }}">{{ $log->statusLabel() }}</span>
                        </td>
                        <td>{{ $log->success_count ?? '—' }}</td>
                        <td>{{ $log->failure_count ?? '—' }}</td>
                        <td class="small">{{ $log->completed_at ? $log->completed_at->format('d/m/Y H:i') : '—' }}</td>
                        <td class="text-nowrap">
                            @if($log->isDone() && $log->failure_count > 0)
                            <button class="btn btn-xs btn-outline-warning btn-lihat-error mr-1" data-id="{{ $log->id }}" data-failures="{{ json_encode($log->failures) }}">
                                Lihat Error
                            </button>
                            @elseif($log->isFailed())
                            <button class="btn btn-xs btn-outline-danger btn-lihat-error mr-1" data-id="{{ $log->id }}" data-failures="{{ json_encode($log->failures) }}">
                                Lihat Detail
                            </button>
                            @endif
                            @if($log->isDone() || $log->isFailed())
                            <form method="POST" action="{{ route('admin.epidemiologi.reimport', $log->id) }}" class="d-inline" onsubmit="return confirm('Ulangi import file ini? Data PD3I yang sudah ada tidak akan dihapus otomatis.')">
                                @csrf
                                <button type="submit" class="btn btn-xs btn-outline-secondary">
                                    <span class="material-symbols-outlined" style="font-size:.9rem;vertical-align:middle;">replay</span>
                                    Reimport
                                </button>
                            </form>
                            <button class="btn btn-xs btn-outline-danger btn-hapus-log ml-1"
                                aria-label="Hapus log import"
                                data-id="{{ $log->id }}"
                                data-url="{{ route('admin.epidemiologi.destroyImportLog', $log->id) }}">
                                <span class="material-symbols-outlined" style="font-size:.9rem;vertical-align:middle;" aria-hidden="true">delete</span>
                            </button>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ===== Filter Card ===== --}}
    <div class="st-card mb-4">
        <div class="st-card__header">
            <h3 class="st-card__header-title">
                <span class="material-symbols-outlined">filter_alt</span>
                Filter Data
            </h3>
        </div>
        <div class="st-card__body">
            {{-- Filter Wilker (hanya untuk user puskesmas) --}}
            @if($isFaskes && auth()->user()->faskes_type === 'puskesmas')
            <div class="mb-3">
                <label class="font-weight-bold small d-block mb-1">Tampilkan Kasus:</label>
                <div class="btn-group btn-group-toggle" data-toggle="buttons" role="group" aria-label="Filter mode kasus">
                    <label class="btn btn-sm btn-outline-primary active" for="filter_wilker">
                        <input type="radio" name="filter_mode" id="filter_wilker" value="wilker" checked>
                        <i class="fa fa-map-marker-alt"></i> Wilker Saya
                    </label>
                    <label class="btn btn-sm btn-outline-secondary" for="filter_dilaporkan">
                        <input type="radio" name="filter_mode" id="filter_dilaporkan" value="dilaporkan">
                        <i class="fa fa-hospital"></i> Dilaporkan ke Saya
                    </label>
                </div>
                <small class="text-muted ml-2">Wilker: <strong>{{ auth()->user()->puskesmas->name ?? '-' }}</strong></small>
            </div>
            @endif
            <div class="filter-grid">
                <div class="filter-group">
                    <label for="disease_filter">Jenis Penyakit</label>
                    <select id="disease_filter" aria-label="Filter berdasarkan jenis penyakit">
                        <option value="">Semua Penyakit</option>
                        @foreach($diseases as $disease)
                            <option value="{{ $disease->id }}">{{ $disease->nama_penyakit }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-group">
                    <label for="status_filter">Status Kasus</label>
                    <select id="status_filter" aria-label="Filter berdasarkan status kasus">
                        <option value="">Semua Status</option>
                        <option value="suspected">Suspected</option>
                        <option value="probable">Probable</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="discarded">Discarded</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="kecamatan_filter">Kecamatan</label>
                    <select id="kecamatan_filter" aria-label="Filter berdasarkan kecamatan">
                        <option value="">Semua Kecamatan</option>
                        @foreach($kecamatanList as $kec)
                            <option value="{{ $kec->id }}">{{ $kec->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="filter-actions">
                <button id="reset_filter" class="st-btn st-btn-outline-secondary" aria-label="Reset semua filter">
                    <span class="material-symbols-outlined" style="font-size:18px;">restart_alt</span>
                    Reset
                </button>
                <button id="export_excel" class="st-btn st-btn-outline-green" aria-label="Export data ke Excel">
                    <span class="material-symbols-outlined" style="font-size:18px;">table_view</span>
                    Export Excel
                </button>
                <button id="apply_filter" class="st-btn st-btn-primary" aria-label="Cari data">
                    <span class="material-symbols-outlined" style="font-size:18px;">search</span>
                    Cari Data
                </button>
            </div>
        </div>
    </div>

    {{-- ===== Data Table Card ===== --}}
    <div class="st-card">
        <div class="st-card__header">
            <h3 class="st-card__header-title">
                <span class="material-symbols-outlined">list_alt</span>
                Data Kasus Surveillance
            </h3>
        </div>
        <div class="table-responsive" style="padding: 0;">
            <table id="casesTable" class="table" style="width:100%; margin-bottom: 0;" aria-label="Tabel daftar kasus surveillance">
                <thead>
                    <tr>
                        <th scope="col">No. Epid</th>
                        <th scope="col">NIK</th>
                        <th scope="col">Nama Lengkap</th>
                        <th scope="col">Jenis Penyakit</th>
                        <th scope="col">Lokasi</th>
                        <th scope="col">Tanggal Onset</th>
                        <th scope="col">Status Kasus</th>
                        <th scope="col">Kondisi Akhir</th>
                        <th scope="col" style="text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ===== Delete Confirmation Modal ===== --}}
<div class="modal fade st-modal" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">
                    <span class="material-symbols-outlined" style="vertical-align: middle; margin-right: 8px;">warning</span>
                    Konfirmasi Hapus
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Tutup" style="opacity: 0.9;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p style="font-size: 0.9375rem;">Apakah Anda yakin ingin menghapus kasus ini?</p>
                <p style="color: #dc2626; font-weight: 600; margin-top: 8px;">
                    <span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">error</span>
                    Tindakan ini tidak dapat dibatalkan.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn st-btn st-btn-outline-secondary" data-dismiss="modal">Batal</button>
                <button type="button" class="btn" id="confirmDelete" style="background: #dc2626; color: #fff; border-radius: var(--st-radius); font-family: 'Barlow', sans-serif; font-weight: 700;">
                    <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle; margin-right: 4px;">delete</span>
                    Hapus
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ===== Flash: File Diterima ===== --}}
@if(session('import_queued'))
<div class="alert alert-info alert-dismissible fade show mt-3" role="alert">
    <span class="material-symbols-outlined" style="vertical-align:middle;font-size:1.1rem;">sync</span>
    {{ session('import_queued') }}
    <button type="button" class="close" data-dismiss="alert" aria-label="Tutup">&times;</button>
</div>
@endif

{{-- Modal detail error --}}
<div class="modal fade" id="modalErrorDetail" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Baris Bermasalah</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <ul id="error-list" class="small mb-0"></ul>
            </div>
        </div>
    </div>
</div>

{{-- ===== Modal: Import Excel PD3I ===== --}}
@if(!$isFaskes)
{{-- ===== Modal Import Hasil Lab CSV ===== --}}
<div class="modal fade" id="modalImportHasilLab" tabindex="-1" role="dialog" aria-labelledby="modalImportHasilLabLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalImportHasilLabLabel">
                    <span class="material-symbols-outlined" style="vertical-align:middle;">biotech</span>
                    Import Hasil Laboratorium CSV
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span>&times;</span></button>
            </div>
            <form action="{{ route('admin.importCsv.hasilLab') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="file_hasil_lab" class="form-label fw-semibold">Pilih File CSV Hasil Lab</label>
                        <input type="file" name="file_hasil_lab" id="file_hasil_lab" class="form-control" accept=".csv,text/csv" required>
                        <div class="form-text">Format: .csv. Maksimal ukuran file: 10 MB.</div>
                    </div>
                    <div class="alert alert-info small mb-0">
                        <strong>Catatan:</strong>
                        <ul class="mb-0 mt-1">
                            <li>Gunakan file CSV hasil lab dengan 19 kolom (format standar Campak/Rubella/Polio/Difteri/Pertusis).</li>
                            <li>Kasus yang cocok dengan <em>No Epid</em> akan di-<em>update</em> data lab-nya; kasus baru dibuat secara minimal.</li>
                            <li>Baris dengan <em>No Epid</em> atau <em>Jenis Penyakit</em> berisi <code>#N/A</code> akan dilewati otomatis.</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="st-btn st-btn-primary">
                        <span class="material-symbols-outlined">upload</span>
                        Upload &amp; Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalImportPd3i" tabindex="-1" role="dialog" aria-labelledby="modalImportPd3iLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalImportPd3iLabel">
                    <span class="material-symbols-outlined" style="vertical-align:middle;">upload_file</span>
                    Import Excel PD3I
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span>&times;</span></button>
            </div>
            <form action="{{ route('admin.epidemiologi.importExcel') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="file_import" class="form-label fw-semibold">Pilih File Excel / CSV PD3I</label>
                        <input type="file" name="file_import" id="file_import" class="form-control" accept=".xlsx,.xls,.csv" required>
                        <div class="form-text">Format: .xlsx, .xls, atau .csv. Maksimal ukuran file: 20 MB.</div>
                    </div>
                    <div class="alert alert-info small mb-0">
                        <strong>Catatan:</strong>
                        <ul class="mb-0 mt-1">
                            <li>Gunakan template Excel PD3I resmi. Data mulai dibaca dari baris ke-3.</li>
                            <li>Data dengan nomor registrasi yang sama akan di-<em>update</em> otomatis (tidak digandakan).</li>
                            <li>Baris yang gagal diproses akan dilewati dan ditampilkan sebagai laporan.</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="st-btn st-btn-primary">
                        <span class="material-symbols-outlined">upload</span>
                        Upload &amp; Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@endsection

@section('scripts')
@parent
<script>
$(document).ready(function() {
    // Initialize DataTable
    var table = $('#casesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("admin.epidemiologi.getCases") }}',
            data: function(d) {
                d.disease_filter = $('#disease_filter').val();
                d.status_filter = $('#status_filter').val();
                d.kecamatan_filter = $('#kecamatan_filter').val();
                d.filter_mode = $('input[name="filter_mode"]:checked').val() || 'wilker';
            }
        },
        columns: [
            { data: 'no_registrasi', name: 'no_registrasi', render: function(data) { return data || '<span class="text-muted">-</span>'; } },
            { data: 'nik', name: 'nik' },
            { data: 'nama_lengkap', name: 'nama_lengkap' },
            { data: 'disease', name: 'jenisKasus.nama_penyakit' },
            { data: 'location', name: 'location', orderable: false, searchable: false },
            { data: 'tanggal_onset', name: 'tanggal_onset' },
            { data: 'status_badge', name: 'status_kasus' },
            { data: 'outcome_badge', name: 'kondisi_akhir' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        order: [[5, 'desc']],
        pageLength: 25,
        language: {
            url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/id.json'
        },
        drawCallback: function() {
            // Re-apply Barlow font to dynamically loaded content
            $('#casesTable td').css('font-family', "'Barlow', sans-serif");
        }
    });

    // Filter change events — auto-search on change
    $('#disease_filter, #status_filter, #kecamatan_filter').on('change', function() {
        table.draw();
    });

    // Wilker/faskes filter mode toggle
    $('input[name="filter_mode"]').on('change', function() {
        table.draw();
    });

    // Apply filter button
    $('#apply_filter').on('click', function() {
        table.draw();
    });

    // Reset filters
    $('#reset_filter').on('click', function() {
        $('#disease_filter').val('');
        $('#status_filter').val('');
        $('#kecamatan_filter').val('');
        table.draw();
    });

    // Export Excel
    $('#export_excel').on('click', function() {
        var disease = $('#disease_filter').val();
        var status = $('#status_filter').val();
        var kecamatan = $('#kecamatan_filter').val();

        var params = [];
        if (disease) params.push('disease_id=' + disease);
        if (status) params.push('status=' + status);
        if (kecamatan) params.push('kecamatan_id=' + kecamatan);

        var url = '{{ route("admin.epidemiologi.exportExcel") }}' + (params.length ? '?' + params.join('&') : '');
        window.location.href = url;
    });

    // Delete case
    var deleteId = null;
    window.deleteCase = function(id) {
        deleteId = id;
        $('#deleteModal').modal('show');
    };

    $('#confirmDelete').on('click', function() {
        if (deleteId) {
            $.ajax({
                url: '{{ route("admin.epidemiologi.destroy", ":id") }}'.replace(':id', deleteId),
                type: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    $('#deleteModal').modal('hide');
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil',
                        text: response.message,
                        timer: 2000
                    });
                    table.draw();
                },
                error: function(xhr) {
                    $('#deleteModal').modal('hide');
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: xhr.responseJSON ? xhr.responseJSON.message : 'Terjadi kesalahan'
                    });
                }
            });
        }
    });
});

@if(!$isFaskes && $importLogs->isNotEmpty())
// ===== Import Status Polling =====
(function() {
    var statusUrl    = '{{ route("admin.epidemiologi.importStatus") }}';
    var deleteLogUrl = '{{ route("admin.epidemiologi.destroyImportLog", "_ID_") }}';
    var deleteAllUrl = '{{ route("admin.epidemiologi.destroyAllImportLogs") }}';
    var csrfToken    = '{{ csrf_token() }}';
    var pollTimer    = null;

    var statusColors = { pending: 'warning', processing: 'info', done: 'success', failed: 'danger' };
    var statusLabels = { pending: 'Menunggu', processing: 'Diproses', done: 'Selesai', failed: 'Gagal' };

    function buildActionCell(log) {
        var html = '';
        var reimportUrl = '{{ route("admin.epidemiologi.reimport", "_ID_") }}'.replace('_ID_', log.id);
        var delUrl      = deleteLogUrl.replace('_ID_', log.id);

        if (log.status === 'done' && log.failure_count > 0) {
            html += '<button class="btn btn-xs btn-outline-warning btn-lihat-error mr-1" data-id="' + log.id + '" data-failures=\'' + JSON.stringify(log.failures || []) + '\'>Lihat Error</button>';
        } else if (log.status === 'failed') {
            html += '<button class="btn btn-xs btn-outline-danger btn-lihat-error mr-1" data-id="' + log.id + '" data-failures=\'' + JSON.stringify(log.failures || []) + '\'>Lihat Detail</button>';
        }
        if (log.status === 'done' || log.status === 'failed') {
            html += '<form method="POST" action="' + reimportUrl + '" class="d-inline" onsubmit="return confirm(\'Ulangi import file ini?\')">'
                 + '<input type="hidden" name="_token" value="' + csrfToken + '">'
                 + '<button type="submit" class="btn btn-xs btn-outline-secondary mr-1">'
                 + '<span class="material-symbols-outlined" style="font-size:.9rem;vertical-align:middle;">replay</span> Reimport</button></form>';
            html += '<button class="btn btn-xs btn-outline-danger btn-hapus-log" data-id="' + log.id + '" data-url="' + delUrl + '">'
                 + '<span class="material-symbols-outlined" style="font-size:.9rem;vertical-align:middle;">delete</span></button>';
        }
        return html;
    }

    function refreshStatus() {
        $.getJSON(statusUrl, function(logs) {
            var stillActive = false;
            logs.forEach(function(log) {
                var $row = $('tr[data-log-id="' + log.id + '"]');
                if (!$row.length) return;

                var oldStatus = $row.data('status');
                $row.data('status', log.status);

                $row.find('.badge')
                    .removeClass('badge-warning badge-info badge-success badge-danger')
                    .addClass('badge-' + (statusColors[log.status] || 'secondary'))
                    .text(statusLabels[log.status] || log.status);

                $row.find('td').eq(2).text(log.success_count !== null ? log.success_count : '—');
                $row.find('td').eq(3).text(log.failure_count !== null ? log.failure_count : '—');
                $row.find('td').eq(4).text(log.completed_at ? log.completed_at.substring(0, 16).replace('T', ' ') : '—');

                if (oldStatus !== log.status) {
                    $row.find('td').eq(5).html(buildActionCell(log));

                    if (log.status === 'done' && oldStatus !== 'done') {
                        var msg = log.success_count + ' data berhasil diimpor';
                        if (log.failure_count > 0) msg += ', ' + log.failure_count + ' baris gagal';
                        toastr && toastr.success(msg + '.', 'Import Selesai');
                    } else if (log.status === 'failed' && oldStatus !== 'failed') {
                        toastr && toastr.error('Import gagal. Lihat detail untuk informasi lebih lanjut.', 'Import Gagal');
                    }
                }

                if (log.status === 'pending' || log.status === 'processing') stillActive = true;
            });

            $('#import-last-refresh').text('Update: ' + new Date().toLocaleTimeString('id-ID'));

            if (!stillActive && pollTimer) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
        });
    }

    // Lihat detail error — warnai [ERROR]/[PERINGATAN]/[INFO]
    $(document).on('click', '.btn-lihat-error', function() {
        var failures = $(this).data('failures');
        if (typeof failures === 'string') { try { failures = JSON.parse(failures); } catch(e) { failures = [failures]; } }
        var $list = $('#error-list').empty();
        (failures || []).forEach(function(f) {
            var cls = f.indexOf('[ERROR]') === 0 ? 'text-danger'
                    : (f.indexOf('[PERINGATAN]') === 0 ? 'text-warning' : 'text-muted');
            $list.append('<li class="' + cls + '">' + $('<span>').text(f).html() + '</li>');
        });
        $('#modalErrorDetail').modal('show');
    });

    // Hapus satu log via AJAX
    $(document).on('click', '.btn-hapus-log', function() {
        if (!confirm('Hapus riwayat import ini?')) return;
        var $btn = $(this);
        $.ajax({
            url: $btn.data('url'), method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken },
            success: function() {
                $btn.closest('tr').remove();
                if ($('#import-log-table tbody tr').length === 0) {
                    $('#import-status-panel').remove();
                }
            },
            error: function() { toastr && toastr.error('Gagal menghapus riwayat.'); }
        });
    });

    // Hapus semua log via AJAX
    $('#btn-hapus-semua-log').on('click', function() {
        if (!confirm('Hapus semua riwayat import yang sudah selesai?')) return;
        $.ajax({
            url: deleteAllUrl, method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken },
            success: function() { $('#import-status-panel').remove(); },
            error: function() { toastr && toastr.error('Gagal menghapus riwayat.'); }
        });
    });

    // Selalu mulai polling — berhenti sendiri setelah semua log selesai
    pollTimer = setInterval(refreshStatus, 5000);
    refreshStatus();
})();
@endif
</script>
@endsection

@extends('admin::layouts.app')
@section('title') Admin @endsection
@section('title-content') Export Data @endsection
@section('item') Export @endsection
@section('item-active') Imunisasi @endsection

@section('content')
<style>
    @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap');
    @import url('https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap');

    :root {
        --st-primary: #0066cc;
        --st-primary-dark: #0052a3;
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

    .ex-page { font-family: 'Manrope', sans-serif; color: var(--st-text); }

    .ex-header {
        display: flex; flex-wrap: wrap; justify-content: space-between;
        align-items: flex-start; gap: 16px; margin-bottom: 28px;
    }
    .ex-header__left { display: flex; align-items: center; gap: 14px; }
    .ex-header__icon {
        display: flex; align-items: center; justify-content: center;
        width: 48px; height: 48px; background: rgba(0, 102, 204, 0.1);
        border-radius: 12px; color: var(--st-primary);
    }
    .ex-header__icon .material-symbols-outlined { font-size: 28px; }
    .ex-header__title {
        font-size: 1.75rem; font-weight: 800; color: var(--st-text);
        letter-spacing: -0.02em; margin: 0; line-height: 1.2;
    }
    .ex-header__subtitle { font-size: 0.875rem; color: var(--st-text-muted); font-weight: 500; margin: 2px 0 0; }

    .st-btn {
        display: inline-flex; align-items: center; gap: 8px; padding: 0 18px;
        height: 42px; border-radius: var(--st-radius); font-family: 'Manrope', sans-serif;
        font-weight: 700; font-size: 0.8125rem; border: 1.5px solid transparent;
        cursor: pointer; transition: all 0.2s ease; text-decoration: none !important; white-space: nowrap;
    }
    .st-btn .material-symbols-outlined { font-size: 20px; }
    .st-btn-primary { background: var(--st-primary); color: #fff; border-color: var(--st-primary); box-shadow: var(--st-shadow-md); }
    .st-btn-primary:hover { background: var(--st-primary-dark); border-color: var(--st-primary-dark); color: #fff; transform: translateY(-1px); }
    .st-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; }

    .st-card {
        background: var(--st-surface); border-radius: var(--st-radius);
        border: 1px solid var(--st-border); box-shadow: var(--st-shadow); overflow: hidden;
        margin-bottom: 24px;
    }
    .st-card__header {
        background: linear-gradient(135deg, var(--st-primary) 0%, #059669 100%);
        padding: 14px 24px; display: flex; align-items: center; justify-content: space-between;margin-bottom: 1%;
    }
    .st-card__header-title {
        display: flex; align-items: center; gap: 10px; color: #fff;
        font-size: 1.05rem; font-weight: 700; 
    }
    .st-card__header-title .material-symbols-outlined { font-size: 22px; }
    .st-card__body { padding: 24px; }

    .st-form-group { margin-bottom: 16px; }
    .st-form-group label { font-size: 0.8125rem; font-weight: 700; color: #334155; margin-bottom: 6px; display: block; }
    .st-form-group select, .st-form-group input {
        width: 100%; padding: 10px 14px; border-radius: var(--st-radius); border: 1px solid #cbd5e1;
        background: #f8fafc; color: #334155; font-family: 'Manrope', sans-serif; font-size: 0.875rem; font-weight: 500;
    }
    .st-form-group select:focus, .st-form-group input:focus {
        outline: none; border-color: var(--st-primary); box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.12);
    }

    .filter-badges { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; min-height: 32px; }
    .filter-badge {
        display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px;
        background: rgba(0, 102, 204, 0.08); border: 1px solid rgba(0, 102, 204, 0.2);
        border-radius: 9999px; font-size: 0.75rem; font-weight: 600; color: var(--st-primary);
        font-family: 'Manrope', sans-serif;
    }
    .filter-badge .badge-label { color: var(--st-text-muted); font-weight: 500; }

    .record-info {
        display: flex; align-items: center; gap: 8px; padding: 12px 24px;
        background: #f8fafc; border-bottom: 1px solid var(--st-border-light);
        font-size: 0.8125rem; font-weight: 600; color: var(--st-text-muted);
        font-family: 'Manrope', sans-serif;
    }
    .record-info .material-symbols-outlined { font-size: 18px; }
    .record-count { color: var(--st-primary); font-weight: 700; }

    .empty-state {
        text-align: center; padding: 48px 24px; color: var(--st-text-muted);
        font-family: 'Manrope', sans-serif;
    }
    .empty-state .material-symbols-outlined { font-size: 48px; margin-bottom: 12px; opacity: 0.4; }
    .empty-state p { font-size: 0.875rem; font-weight: 500; margin: 0; }

    .st-card .dataTables_wrapper { font-family: 'Manrope', sans-serif; }
    .st-card .dataTables_wrapper .dataTables_filter input {
        height: 38px; padding: 0 14px; border-radius: var(--st-radius);
        border: 1px solid #cbd5e1; background: #fff; font-size: 0.8125rem;
        font-family: 'Manrope', sans-serif; min-width: 250px;
    }
    .st-card .dataTables_wrapper .dataTables_filter input:focus {
        border-color: var(--st-primary); box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.12); outline: none;
    }
    .st-card .dataTables_wrapper .dataTables_length select {
        height: 32px; padding: 0 28px 0 10px; border-radius: 8px;
        border: 1px solid #cbd5e1; font-family: 'Manrope', sans-serif; font-weight: 600; font-size: 0.8125rem;
    }
    .st-card table.dataTable { border-collapse: collapse !important; width: 100% !important; }
    .st-card table.dataTable thead th {
        background: #f8fafc; border-bottom: 2px solid var(--st-border); color: var(--st-text-muted);
        font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;
        padding: 14px 16px; white-space: nowrap;
    }
    .st-card table.dataTable tbody td {
        padding: 14px 16px; font-size: 0.8125rem; font-weight: 500; color: #334155;
        border-bottom: 1px solid var(--st-border-light); vertical-align: middle;
    }
    .st-card table.dataTable tbody tr:nth-child(even) { background: rgba(248, 250, 252, 0.5); }
    .st-card table.dataTable tbody tr:hover { background: rgba(219, 234, 254, 0.3) !important; }

    .st-card .badge { display: inline-flex; align-items: center; padding: 4px 12px; border-radius: 9999px; font-size: 0.6875rem; font-weight: 700; font-family: 'Manrope', sans-serif; border: 1px solid transparent; }
    .st-card .badge.bg-success { background: #ecfdf5 !important; color: #047857 !important; border-color: #a7f3d0; }
    .st-card .badge.bg-warning { background: #fffbeb !important; color: #b45309 !important; border-color: #fde68a; }
    .st-card .badge.bg-danger { background: #fef2f2 !important; color: #dc2626 !important; border-color: #fecaca; }
    .st-card .badge.bg-secondary { background: #f1f5f9 !important; color: #475569 !important; border-color: #e2e8f0; }

    .st-card .dataTables_wrapper .dataTables_paginate .paginate_button {
        min-width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center;
        border-radius: 8px !important; border: 1px solid var(--st-border) !important; background: #fff !important;
        color: var(--st-text-muted) !important; font-size: 0.8125rem; font-weight: 600; font-family: 'Manrope', sans-serif; margin: 0 2px; padding: 0 8px !important;
    }
    .st-card .dataTables_wrapper .dataTables_paginate .paginate_button:hover { background: #f1f5f9 !important; color: var(--st-text) !important; }
    .st-card .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: var(--st-primary) !important; color: #fff !important; border-color: var(--st-primary) !important; }
    .st-card .dataTables_wrapper .dataTables_paginate .paginate_button.disabled { opacity: 0.4; }
    .st-card .dataTables_wrapper .dataTables_info { font-size: 0.8125rem; font-weight: 500; color: var(--st-text-muted); font-family: 'Manrope', sans-serif; padding: 16px 24px; }
    .st-card .dataTables_wrapper .dataTables_paginate { padding: 16px 24px; }

    @media (max-width: 768px) {
        .ex-header { flex-direction: column; }
        .ex-header__title { font-size: 1.4rem;}
    }
</style>

<div class="ex-page">
    {{-- Header --}}
    <div class="ex-header">
        <div class="ex-header__left">
            <div class="ex-header__icon">
                <span class="material-symbols-outlined">download</span>
            </div>
            <div>
                <h1 class="ex-header__title">Export Data Imunisasi</h1>
                <p class="ex-header__subtitle">Filter dan ekspor data imunisasi anak ke format CSV</p>
            </div>
        </div>
        <div>
            <button class="st-btn st-btn-primary" id="btnExport" disabled>
                <span class="material-symbols-outlined">download</span>
                Export CSV
            </button>
        </div>
    </div>

    {{-- Filter Card --}}
    <div class="st-card">
        <div class="st-card__header">
            <h3 class="st-card__header-title">
                <span class="material-symbols-outlined">filter_alt</span>
                Filter Data
            </h3>
        </div>
        <div class="st-card__body">
            <div class="row">
                <div class="col-md-3">
                    <div class="st-form-group">
                        <label for="filterBulan">Bulan</label>
                        <input type="month" id="filterBulan" placeholder="Pilih Bulan">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="st-form-group">
                        <label for="filterKelurahan">Kelurahan</label>
                        <select id="filterKelurahan">
                            <option value="">-- Semua Kelurahan --</option>
                            @foreach ($kelurahanList as $kel)
                                <option value="{{ $kel->id }}">{{ $kel->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="st-form-group">
                        <label for="filterAntigen">Jenis Antigen</label>
                        <select id="filterAntigen">
                            <option value="">-- Semua Antigen --</option>
                            @foreach ($vaksinList as $vaksin)
                                <option value="{{ $vaksin->id }}">{{ $vaksin->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="st-form-group">
                        <label for="filterStatus">Status</label>
                        <select id="filterStatus">
                            <option value="">-- Semua Status --</option>
                            <option value="belum">Belum</option>
                            <option value="sudah">Sudah</option>
                            <option value="terlambat">Terlambat</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Badges (US3) --}}
    <div class="filter-badges" id="filterBadges"></div>

    {{-- Preview Table Card (US2) --}}
    <div class="st-card">
        <div class="st-card__header">
            <h3 class="st-card__header-title">
                <span class="material-symbols-outlined">preview</span>
                Preview Data
            </h3>
            <span id="recordCount" style="color: rgba(255,255,255,0.85); font-size: 0.8125rem; font-weight: 600; font-family: 'Manrope', sans-serif;"></span>
        </div>
        <div class="table-responsive" style="padding: 0;">
            <table id="previewTable" class="table" style="width:100%; margin-bottom: 0;">
                <thead>
                    <tr>
                        <th>Nama Anak</th>
                        <th>NIK</th>
                        <th>Jenis Kelamin</th>
                        <th>Tgl Lahir</th>
                        <th>Kelurahan</th>
                        <th>Kecamatan</th>
                        <th>Posyandu</th>
                        <th>Jenis Vaksin</th>
                        <th>Dosis</th>
                        <th>Tgl Pemberian</th>
                        <th>Status</th>
                        <th>Lokasi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
@parent
<script>
$(document).ready(function() {
    var table = $('#previewTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("admin.export.imunisasi.getData") }}',
            data: function(d) {
                d.bulan = $('#filterBulan').val();
                d.kelurahan = $('#filterKelurahan').val();
                d.antigen = $('#filterAntigen').val();
                d.status = $('#filterStatus').val();
            }
        },
        columns: [
            { data: 'nama_anak', name: 'nama_anak', orderable: false },
            { data: 'nik', name: 'nik', orderable: false },
            { data: 'jenis_kelamin', name: 'jenis_kelamin', orderable: false },
            { data: 'tanggal_lahir', name: 'tanggal_lahir', orderable: false },
            { data: 'kelurahan', name: 'kelurahan', orderable: false },
            { data: 'kecamatan', name: 'kecamatan', orderable: false },
            { data: 'posyandu', name: 'posyandu', orderable: false },
            { data: 'jenis_vaksin', name: 'jenis_vaksin', orderable: false },
            { data: 'dosis', name: 'dosis' },
            { data: 'tanggal_pemberian_fmt', name: 'tanggal_pemberian', orderable: false },
            { data: 'status_badge', name: 'status' },
            { data: 'lokasi_pemberian', name: 'lokasi_pemberian', orderable: false }
        ],
        order: [],
        pageLength: 25,
        language: {
            url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/id.json',
            emptyTable: 'Tidak ada data yang sesuai filter'
        },
        drawCallback: function(settings) {
            var total = settings._iRecordsDisplay;
            $('#recordCount').text(total > 0 ? total + ' record ditemukan' : '');
            updateExportButton(total);
        }
    });

    // Filter change handler - reload DataTables
    $('#filterBulan, #filterKelurahan, #filterAntigen, #filterStatus').on('change', function() {
        table.draw();
        updateBadges();
    });

    // Export button
    $('#btnExport').on('click', function() {
        var params = $.param({
            bulan: $('#filterBulan').val(),
            kelurahan: $('#filterKelurahan').val(),
            antigen: $('#filterAntigen').val(),
            status: $('#filterStatus').val()
        });
        window.location.href = '{{ route("admin.export.imunisasi.download") }}?' + params;
    });

    function updateExportButton(totalRecords) {
        var hasBulan = $('#filterBulan').val() !== '';
        var hasData = totalRecords > 0;
        $('#btnExport').prop('disabled', !hasBulan || !hasData);
    }

    // US3: Update filter badges
    function updateBadges() {
        var badges = [];
        var bulan = $('#filterBulan').val();
        if (bulan) {
            var parts = bulan.split('-');
            var monthNames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
            badges.push({ label: 'Bulan', value: monthNames[parseInt(parts[1]) - 1] + ' ' + parts[0] });
        }

        var kelurahan = $('#filterKelurahan option:selected').text();
        if ($('#filterKelurahan').val()) {
            badges.push({ label: 'Kelurahan', value: kelurahan });
        }

        var antigen = $('#filterAntigen option:selected').text();
        if ($('#filterAntigen').val()) {
            badges.push({ label: 'Antigen', value: antigen });
        }

        var status = $('#filterStatus').val();
        if (status) {
            badges.push({ label: 'Status', value: status.charAt(0).toUpperCase() + status.slice(1) });
        }

        var html = '';
        badges.forEach(function(b) {
            html += '<span class="filter-badge"><span class="badge-label">' + b.label + ':</span> ' + b.value + '</span>';
        });
        $('#filterBadges').html(html);
    }
});
</script>
@endsection

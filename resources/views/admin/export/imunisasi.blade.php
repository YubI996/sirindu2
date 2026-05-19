@extends('admin::layouts.app')
@push('styles')
<link rel="stylesheet" type="text/css" href="{{ asset('admin/src/plugins/datatables/css/dataTables.bootstrap4.min.css') }}">
<link rel="stylesheet" type="text/css" href="{{ asset('admin/src/plugins/datatables/css/responsive.bootstrap4.min.css') }}">
@endpush
@push('js')
<script src="{{ asset('admin/src/plugins/datatables/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('admin/src/plugins/datatables/js/dataTables.bootstrap4.min.js') }}"></script>
<script src="{{ asset('admin/src/plugins/datatables/js/dataTables.responsive.min.js') }}"></script>
<script src="{{ asset('admin/src/plugins/datatables/js/responsive.bootstrap4.min.js') }}"></script>
@endpush
@section('title') Admin @endsection
@section('title-content') Export Data @endsection
@section('item') Export @endsection
@section('item-active') Imunisasi @endsection

@section('content')
<style>
    /* sr-only untuk caption tabel — accessible tapi tidak visible */
    .visually-hidden {
        position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
        overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0;
    }

    .ex-page {
        /* Token lokal — hanya untuk nilai tanpa padanan di --srd-* global */
        --ex-radius: 12px;
        --ex-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.06), 0 1px 2px -1px rgb(0 0 0 / 0.06);
        --ex-shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.07), 0 2px 4px -2px rgb(0 0 0 / 0.07);
        --ex-text: oklch(0.19 0.014 145);
        --ex-surface-input: oklch(0.975 0.012 145);
        --ex-border-input: oklch(0.82 0.025 145);
        --ex-border-light: oklch(0.93 0.012 145);
        font-family: 'Barlow', sans-serif;
        color: var(--ex-text);
    }

    .ex-header {
        display: flex; flex-wrap: wrap; justify-content: space-between;
        align-items: flex-start; gap: 16px; margin-bottom: 28px;
    }
    .ex-header__left { display: flex; align-items: center; gap: 14px; }
    .ex-header__title {
        font-size: 1.75rem; font-weight: 800; color: var(--ex-text);
        letter-spacing: -0.02em; margin: 0; line-height: 1.2;
    }
    .ex-header__subtitle { font-size: 0.875rem; color: var(--srd-text-2); font-weight: 500; margin: 2px 0 0; }

    .st-btn {
        display: inline-flex; align-items: center; gap: 8px; padding: 0 18px;
        height: 42px; border-radius: var(--ex-radius); font-family: 'Barlow', sans-serif;
        font-weight: 700; font-size: 0.8125rem; border: 1.5px solid transparent;
        cursor: pointer;
        transition: background-color 0.16s ease-out, border-color 0.16s ease-out,
                    box-shadow 0.18s ease-out, transform 0.1s ease-out;
        text-decoration: none !important; white-space: nowrap;
    }
    .st-btn .material-symbols-outlined { font-size: 20px; }
    .st-btn-primary {
        background: var(--srd-green); color: var(--srd-on-dark);
        border-color: var(--srd-green); box-shadow: var(--ex-shadow-md);
    }
    @media (hover: hover) {
        .st-btn-primary:hover {
            background: oklch(0.38 0.13 145); border-color: oklch(0.38 0.13 145);
            color: var(--srd-on-dark); transform: translateY(-1px);
            box-shadow: 0 4px 18px oklch(0.48 0.14 145 / 0.32);
        }
    }
    .st-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; }

    .st-card {
        background: var(--srd-surface); border-radius: var(--ex-radius);
        border: 1px solid var(--srd-border); box-shadow: var(--ex-shadow); overflow: hidden;
        margin-bottom: 24px;
    }
    .st-card__header {
        background: var(--srd-green);
        padding: 14px 24px; display: flex; align-items: center; justify-content: space-between;
    }
    .st-card__header-title {
        display: flex; align-items: center; gap: 10px; color: var(--srd-on-dark);
        font-size: 1.05rem; font-weight: 700;
    }
    .st-card__header-title .material-symbols-outlined { font-size: 22px; }
    .st-card__body { padding: 24px; }

    .st-form-group { margin-bottom: 16px; }
    .st-form-group label {
        font-size: 0.8125rem; font-weight: 700; color: var(--srd-text-2);
        margin-bottom: 6px; display: block;
    }
    .st-form-group select, .st-form-group input {
        width: 100%; padding: 10px 14px; border-radius: var(--ex-radius);
        border: 1px solid var(--ex-border-input);
        background: var(--ex-surface-input); color: var(--ex-text);
        font-family: 'Barlow', sans-serif; font-size: 0.875rem; font-weight: 500;
    }
    .st-form-group select:focus, .st-form-group input:focus {
        outline: none; border-color: var(--srd-green);
        box-shadow: 0 0 0 3px oklch(0.48 0.14 145 / 0.15);
    }

    .filter-badges { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; min-height: 32px; }
    .filter-badge {
        display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px;
        background: var(--srd-surface-subtle); border: 1px solid var(--srd-green-border);
        border-radius: 9999px; font-size: 0.75rem; font-weight: 600; color: var(--srd-green);
        font-family: 'Barlow', sans-serif;
    }
    .filter-badge .badge-label { color: var(--srd-text-2); font-weight: 500; }

    .record-info {
        display: flex; align-items: center; gap: 8px; padding: 12px 24px;
        background: var(--srd-surface-subtle); border-bottom: 1px solid var(--ex-border-light);
        font-size: 0.8125rem; font-weight: 600; color: var(--srd-text-2);
        font-family: 'Barlow', sans-serif;
    }
    .record-info .material-symbols-outlined { font-size: 18px; }
    .record-count { color: var(--srd-green); font-weight: 700; }

    .empty-state {
        text-align: center; padding: 48px 24px; color: var(--srd-text-2);
        font-family: 'Barlow', sans-serif;
    }
    .empty-state .material-symbols-outlined { font-size: 48px; margin-bottom: 12px; opacity: 0.4; }
    .empty-state p { font-size: 0.875rem; font-weight: 500; margin: 0; }

    .st-card .dataTables_wrapper { font-family: 'Barlow', sans-serif; }
    .st-card .dataTables_wrapper .dataTables_filter input {
        height: 38px; padding: 0 14px; border-radius: var(--ex-radius);
        border: 1px solid var(--ex-border-input); background: var(--srd-surface);
        font-size: 0.8125rem; font-family: 'Barlow', sans-serif; min-width: 250px;
    }
    .st-card .dataTables_wrapper .dataTables_filter input:focus {
        border-color: var(--srd-green);
        box-shadow: 0 0 0 3px oklch(0.48 0.14 145 / 0.15); outline: none;
    }
    .st-card .dataTables_wrapper .dataTables_length select {
        height: 32px; padding: 0 28px 0 10px; border-radius: 8px;
        border: 1px solid var(--ex-border-input);
        font-family: 'Barlow', sans-serif; font-weight: 600; font-size: 0.8125rem;
    }
    .st-card table.dataTable { border-collapse: collapse !important; width: 100% !important; }
    .st-card table.dataTable thead th {
        background: var(--srd-surface-subtle); border-bottom: 2px solid var(--srd-border);
        color: var(--srd-text-2); font-size: 0.6875rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.06em; padding: 14px 16px; white-space: nowrap;
    }
    .st-card table.dataTable tbody td {
        padding: 14px 16px; font-size: 0.8125rem; font-weight: 500; color: var(--ex-text);
        border-bottom: 1px solid var(--ex-border-light); vertical-align: middle;
    }
    .st-card table.dataTable tbody tr:nth-child(even) { background: oklch(0.975 0.010 145 / 0.5); }
    @media (hover: hover) {
        .st-card table.dataTable tbody tr:hover { background: var(--srd-green-hover) !important; }
    }

    .st-card .badge {
        display: inline-flex; align-items: center; padding: 4px 12px;
        border-radius: 9999px; font-size: 0.6875rem; font-weight: 700;
        font-family: 'Barlow', sans-serif; border: 1px solid transparent;
    }
    .st-card .badge.bg-success  { background: #ecfdf5 !important; color: #047857 !important; border-color: #a7f3d0; }
    .st-card .badge.bg-warning  { background: #fffbeb !important; color: #b45309 !important; border-color: #fde68a; }
    .st-card .badge.bg-danger   { background: #fef2f2 !important; color: #dc2626 !important; border-color: #fecaca; }
    .st-card .badge.bg-secondary {
        background: var(--srd-surface-subtle) !important;
        color: var(--srd-text-2) !important; border-color: var(--srd-border);
    }

    .st-card .dataTables_wrapper .dataTables_paginate .paginate_button {
        min-width: 40px; height: 40px; display: inline-flex; align-items: center; justify-content: center;
        border-radius: 8px !important; border: 1px solid var(--srd-border) !important;
        background: var(--srd-surface) !important; color: var(--srd-text-2) !important;
        font-size: 0.8125rem; font-weight: 600; font-family: 'Barlow', sans-serif;
        margin: 0 2px; padding: 0 8px !important;
    }
    .st-card .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: var(--srd-surface-subtle) !important; color: var(--ex-text) !important;
    }
    .st-card .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: var(--srd-green) !important; color: var(--srd-on-dark) !important;
        border-color: var(--srd-green) !important;
    }
    .st-card .dataTables_wrapper .dataTables_paginate .paginate_button.disabled { opacity: 0.4; }
    .st-card .dataTables_wrapper .dataTables_info {
        font-size: 0.8125rem; font-weight: 500; color: var(--srd-text-2);
        font-family: 'Barlow', sans-serif; padding: 16px 24px;
    }
    .st-card .dataTables_wrapper .dataTables_paginate { padding: 16px 24px; }

    @media (max-width: 768px) {
        .ex-header { flex-direction: column; }
        .ex-header__title { font-size: 1.4rem; }
    }
</style>

<div class="ex-page">
    {{-- Header --}}
    <div class="ex-header">
        <div class="ex-header__left">
            <div>
                <h1 class="ex-header__title">Export Data Imunisasi</h1>
                <p class="ex-header__subtitle">Filter dan ekspor data imunisasi anak ke format CSV</p>
            </div>
        </div>
        <div style="display:flex; flex-direction:column; align-items:flex-end; gap:6px;">
            <button class="st-btn st-btn-primary" id="btnExport" disabled
                aria-describedby="exportHint">
                <span class="material-symbols-outlined" aria-hidden="true">download</span>
                Export CSV
            </button>
            <span id="exportHint" style="font-size:0.75rem; color:var(--srd-text-2); font-family:'Barlow',sans-serif; font-weight:500;">
                Pilih bulan terlebih dahulu untuk mengaktifkan export
            </span>
        </div>
    </div>

    {{-- Filter Card --}}
    <div class="st-card">
        <div class="st-card__header">
            <h2 class="st-card__header-title">
                <span class="material-symbols-outlined" aria-hidden="true">filter_alt</span>
                Filter Data
            </h2>
        </div>
        <div class="st-card__body">
            <div class="row">
                <div class="col-md-3">
                    <div class="st-form-group">
                        <label for="filterBulanNative" id="filterBulanLabel">Bulan</label>
                        {{-- Hidden input holds the final value used by all JS handlers --}}
                        <input type="hidden" id="filterBulan">
                        {{-- Native month picker (Chrome/Edge) --}}
                        <input type="month" id="filterBulanNative" aria-labelledby="filterBulanLabel"
                               title="Format: Tahun-Bulan (mis. 2025-01)">
                        {{-- Firefox/Safari fallback: two selects, hidden until needed --}}
                        <div id="filterBulanFallback" style="display:none; gap:6px; flex-wrap:nowrap;">
                            <select id="fbMonth" aria-label="Bulan" style="width:55%;">
                                <option value="">-- Bulan --</option>
                                @foreach(['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'] as $i => $nb)
                                    <option value="{{ str_pad($i+1,2,'0',STR_PAD_LEFT) }}">{{ $nb }}</option>
                                @endforeach
                            </select>
                            <select id="fbYear" aria-label="Tahun" style="width:calc(45% - 6px);">
                                <option value="">-- Tahun --</option>
                                @for($y = date('Y'); $y >= 2020; $y--)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
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

    {{-- Export Agregat Card --}}
    <div class="st-card">
        <div class="st-card__header" style="background: oklch(0.30 0.09 145);">
            <h2 class="st-card__header-title">
                <span class="material-symbols-outlined" aria-hidden="true">table_chart</span>
                Export Agregat per Kelurahan
            </h2>
        </div>
        <div class="st-card__body">
            <p style="font-size: 0.8125rem; color: var(--srd-text-2); margin-bottom: 16px; font-weight: 500;">
                Export data agregat imunisasi per kelurahan dengan rincian per vaksin dan per kelompok (IDL/IBL/ISL) dalam format Excel.
            </p>
            <div class="row align-items-end">
                <div class="col-md-3">
                    <div class="st-form-group">
                        <label for="agregatBulan">Bulan</label>
                        <select id="agregatBulan">
                            <option value="">-- Pilih Bulan --</option>
                            @php
                                $bulanNames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
                            @endphp
                            @foreach($bulanNames as $idx => $namaBulan)
                                <option value="{{ $idx + 1 }}">{{ $namaBulan }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="st-form-group">
                        <label for="agregatTahun">Tahun</label>
                        <select id="agregatTahun">
                            <option value="">-- Pilih Tahun --</option>
                            @for($y = date('Y'); $y >= 2020; $y--)
                                <option value="{{ $y }}">{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="st-form-group">
                        <label>&nbsp;</label>
                        <button class="st-btn st-btn-primary" id="btnExportAgregat" disabled style="width: 100%;"
                            title="Pilih Bulan dan Tahun untuk mengaktifkan export agregat">
                            <span class="material-symbols-outlined" aria-hidden="true">download</span>
                            Export Agregat Excel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Badges (US3) --}}
    <div class="filter-badges" id="filterBadges"
         aria-live="polite" aria-label="Filter aktif"></div>

    {{-- Preview Table Card (US2) --}}
    <div class="st-card">
        <div class="st-card__header">
            <h2 class="st-card__header-title">
                <span class="material-symbols-outlined" aria-hidden="true">preview</span>
                Preview Data
            </h2>
            <span id="recordCount" aria-live="polite"
                  style="color: var(--srd-on-dark); opacity: 0.85; font-size: 0.8125rem; font-weight: 600; font-family: 'Barlow', sans-serif;"></span>
        </div>
        <div class="table-responsive" style="padding: 0;">
            <table id="previewTable" class="table" style="width:100%; margin-bottom: 0;">
                <caption class="visually-hidden">Data imunisasi anak sesuai filter yang dipilih</caption>
                <thead>
                    <tr>
                        <th scope="col">Nama Anak</th>
                        <th scope="col">NIK</th>
                        <th scope="col">Jenis Kelamin</th>
                        <th scope="col">Tgl Lahir</th>
                        <th scope="col">Kelurahan</th>
                        <th scope="col">Kecamatan</th>
                        <th scope="col">Posyandu</th>
                        <th scope="col">Jenis Vaksin</th>
                        <th scope="col">Dosis</th>
                        <th scope="col">Tgl Pemberian</th>
                        <th scope="col">Status</th>
                        <th scope="col">Lokasi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('custom_scripts')
<script>
$(document).ready(function() {

    // ── Firefox/Safari month input polyfill ───────────────────────────
    (function() {
        var testInput = document.createElement('input');
        testInput.setAttribute('type', 'month');
        testInput.setAttribute('value', ':)');
        var nativeSupport = testInput.value !== ':)';

        if (nativeSupport) {
            // Chrome/Edge: sync native → hidden, then trigger filter change
            $('#filterBulanNative').on('change', function() {
                $('#filterBulan').val(this.value).trigger('change');
            });
        } else {
            // Firefox/Safari fallback
            $('#filterBulanNative').hide();
            $('#filterBulanFallback').css('display', 'flex');

            function syncFallback() {
                var m = $('#fbMonth').val();
                var y = $('#fbYear').val();
                $('#filterBulan').val((m && y) ? y + '-' + m : '').trigger('change');
            }
            $('#fbMonth, #fbYear').on('change', syncFallback);
        }
    })();

    var table = $('#previewTable').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
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
            url: '{{ asset("admin/src/plugins/datatables/lang/id.json") }}',
            emptyTable: 'Tidak ada data yang sesuai filter'
        },
        drawCallback: function(settings) {
            var total = settings._iRecordsDisplay;
            $('#recordCount').text(total > 0 ? total + ' record ditemukan' : '');
            updateExportButton(total);
        }
    });

    // Filter change handler — hidden #filterBulan, selects, and other filters
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
        var disabled = !hasBulan || !hasData;
        $('#btnExport').prop('disabled', disabled);
        var hint = !hasBulan
            ? 'Pilih bulan terlebih dahulu untuk mengaktifkan export'
            : (hasData ? '' : 'Tidak ada data untuk filter yang dipilih');
        $('#exportHint').text(hint);
    }

    // Export Agregat
    $('#agregatBulan, #agregatTahun').on('change', function() {
        var hasBulan = $('#agregatBulan').val() !== '';
        var hasTahun = $('#agregatTahun').val() !== '';
        $('#btnExportAgregat').prop('disabled', !hasBulan || !hasTahun);
    });

    $('#btnExportAgregat').on('click', function() {
        var params = $.param({
            bulan: $('#agregatBulan').val(),
            tahun: $('#agregatTahun').val()
        });
        window.location.href = '{{ route("admin.export.imunisasi.downloadAgregat") }}?' + params;
    });

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


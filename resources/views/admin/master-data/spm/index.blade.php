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
@section('title') Master Data SPM @endsection
@section('title-content') Master Data SPM @endsection
@section('item') Master Data @endsection
@section('item-active') SPM @endsection

@section('content')
<style>
    :root {
        --st-primary: oklch(0.48 0.14 145);
        --st-primary-dark: oklch(0.38 0.13 145);
        --st-primary-light: oklch(0.96 0.022 145);
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

    .md-page { font-family: 'Barlow', sans-serif; color: var(--st-text); }

    .md-header {
        display: flex; flex-wrap: wrap; justify-content: space-between;
        align-items: flex-start; gap: 16px; margin-bottom: 28px;
    }
    .md-header__left { display: flex; align-items: center; gap: 14px; }
    .md-header__icon {
        display: flex; align-items: center; justify-content: center;
        width: 48px; height: 48px; background: rgba(4, 120, 87, 0.1);
        border-radius: 12px; color: #047857;
    }
    .md-header__icon .material-symbols-outlined { font-size: 28px; }
    .md-header__title {
        font-size: 1.75rem; font-weight: 800; color: var(--st-text);
        letter-spacing: -0.02em; margin: 0; line-height: 1.2;
    }
    .md-header__subtitle { font-size: 0.875rem; color: var(--st-text-muted); font-weight: 500; margin: 2px 0 0; }

    .st-btn {
        display: inline-flex; align-items: center; gap: 8px; padding: 0 18px;
        height: 42px; border-radius: var(--st-radius); font-family: 'Barlow', sans-serif;
        font-weight: 700; font-size: 0.8125rem; border: 1.5px solid transparent;
        cursor: pointer; transition: all 0.2s ease; text-decoration: none !important; white-space: nowrap;
    }
    .st-btn .material-symbols-outlined { font-size: 20px; }
    .st-btn-primary { background: var(--st-primary); color: #fff; border-color: var(--st-primary); box-shadow: var(--st-shadow-md); }
    .st-btn-primary:hover { background: var(--st-primary-dark); border-color: var(--st-primary-dark); color: #fff; transform: translateY(-1px); }

    .st-card {
        background: var(--st-surface); border-radius: var(--st-radius);
        border: 1px solid var(--st-border); box-shadow: var(--st-shadow); overflow: hidden;
    }
    .st-card__header {
        background: linear-gradient(135deg, #047857 0%, var(--st-primary) 100%);
        padding: 14px 24px; display: flex; align-items: center; justify-content: space-between;
    }
    .st-card__header-title {
        display: flex; align-items: center; gap: 10px; color: #fff;
        font-size: 1.05rem; font-weight: 700; margin: 0;
    }
    .st-card__header-title .material-symbols-outlined { font-size: 22px; }

    .st-card .dataTables_wrapper { font-family: 'Barlow', sans-serif; }
    .st-card .dataTables_wrapper .dataTables_filter input {
        height: 38px; padding: 0 14px; border-radius: var(--st-radius);
        border: 1px solid #cbd5e1; background: #fff; font-size: 0.8125rem;
        font-family: 'Barlow', sans-serif; min-width: 250px;
    }
    .st-card .dataTables_wrapper .dataTables_filter input:focus {
        border-color: var(--st-primary); box-shadow: 0 0 0 3px oklch(0.48 0.14 145 / 0.15); outline: none;
    }
    .st-card .dataTables_wrapper .dataTables_length select {
        height: 32px; padding: 0 28px 0 10px; border-radius: 8px;
        border: 1px solid #cbd5e1; font-family: 'Barlow', sans-serif; font-weight: 600; font-size: 0.8125rem;
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

    .st-card .badge { display: inline-flex; align-items: center; padding: 4px 12px; border-radius: 9999px; font-size: 0.6875rem; font-weight: 700; font-family: 'Barlow', sans-serif; border: 1px solid transparent; }
    .st-card .badge.bg-success { background: #ecfdf5 !important; color: #047857 !important; border-color: #a7f3d0; }
    .st-card .badge.bg-secondary { background: #f1f5f9 !important; color: #475569 !important; border-color: #e2e8f0; }
    .st-card .badge.bg-danger { background: #fef2f2 !important; color: #b91c1c !important; border-color: #fecaca; }
    .st-card .badge.bg-warning { background: #fffbeb !important; color: #b45309 !important; border-color: #fde68a; }
    .st-card .badge.bg-info { background: #eff6ff !important; color: #1d4ed8 !important; border-color: #bfdbfe; }
    .st-card .badge.bg-primary { background: #eff6ff !important; color: #1e40af !important; border-color: #bfdbfe; }

    .st-card .btn-group .btn {
        width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center;
        border-radius: 8px; border: none; padding: 0; font-size: 14px; opacity: 0.75; transition: all 0.2s ease;
    }
    .st-card table.dataTable tbody tr:hover .btn-group .btn { opacity: 1; }
    .st-card .btn-group .btn.btn-sm.btn-warning { background: transparent; color: #d97706; }
    .st-card .btn-group .btn.btn-sm.btn-warning:hover { background: #fef3c7; }
    .st-card .btn-group .btn.btn-sm.btn-info { background: transparent; color: #2563eb; }
    .st-card .btn-group .btn.btn-sm.btn-info:hover { background: #dbeafe; }
    .st-card .btn-group .btn.btn-sm.btn-danger { background: transparent; color: #dc2626; }
    .st-card .btn-group .btn.btn-sm.btn-danger:hover { background: #fee2e2; }
    .st-card .btn-group .btn.btn-sm.btn-success { background: transparent; color: #047857; }
    .st-card .btn-group .btn.btn-sm.btn-success:hover { background: #ecfdf5; }

    .st-card .dataTables_wrapper .dataTables_paginate .paginate_button {
        min-width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center;
        border-radius: 8px !important; border: 1px solid var(--st-border) !important; background: #fff !important;
        color: var(--st-text-muted) !important; font-size: 0.8125rem; font-weight: 600; font-family: 'Barlow', sans-serif; margin: 0 2px; padding: 0 8px !important;
    }
    .st-card .dataTables_wrapper .dataTables_paginate .paginate_button:hover { background: #f1f5f9 !important; color: var(--st-text) !important; }
    .st-card .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: var(--st-primary) !important; color: #fff !important; border-color: var(--st-primary) !important; }
    .st-card .dataTables_wrapper .dataTables_paginate .paginate_button.disabled { opacity: 0.4; }
    .st-card .dataTables_wrapper .dataTables_info { font-size: 0.8125rem; font-weight: 500; color: var(--st-text-muted); font-family: 'Barlow', sans-serif; padding: 16px 24px; }
    .st-card .dataTables_wrapper .dataTables_paginate { padding: 16px 24px; }

    .st-modal .modal-content { border-radius: var(--st-radius); border: none; box-shadow: 0 20px 60px -15px rgba(0,0,0,0.25); overflow: hidden; }
    .st-modal .modal-header { background: linear-gradient(135deg, #047857, var(--st-primary)); color: #fff; border: none; padding: 18px 24px; }
    .st-modal .modal-header.modal-header-danger { background: linear-gradient(135deg, #dc2626, #e11d48); }
    .st-modal .modal-title { font-family: 'Barlow', sans-serif; font-weight: 700; font-size: 1.05rem; }
    .st-modal .modal-body { padding: 24px; font-family: 'Barlow', sans-serif; }
    .st-modal .modal-footer { border-top: 1px solid var(--st-border-light); padding: 16px 24px; }
    .st-modal .modal-footer .btn { border-radius: var(--st-radius); font-family: 'Barlow', sans-serif; font-weight: 700; font-size: 0.8125rem; padding: 8px 20px; }

    .st-form-group { margin-bottom: 16px; }
    .st-form-group label { font-size: 0.8125rem; font-weight: 700; color: #334155; margin-bottom: 6px; display: block; }
    .st-form-group input, .st-form-group textarea, .st-form-group select {
        width: 100%; padding: 10px 14px; border-radius: var(--st-radius); border: 1px solid #cbd5e1;
        background: #f8fafc; color: #334155; font-family: 'Barlow', sans-serif; font-size: 0.875rem; font-weight: 500;
    }
    .st-form-group input:focus, .st-form-group textarea:focus, .st-form-group select:focus {
        outline: none; border-color: var(--st-primary); box-shadow: 0 0 0 3px oklch(0.48 0.14 145 / 0.15);
    }
    .st-form-group .text-danger { font-size: 0.75rem; margin-top: 4px; }

    .st-form-check { display: flex; align-items: center; gap: 8px; margin-top: 8px; }
    .st-form-check input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--st-primary); }
    .st-form-check label { margin: 0; font-size: 0.875rem; font-weight: 600; }

    @media (max-width: 768px) {
        .md-header { flex-direction: column; }
        .md-header__title { font-size: 1.4rem; }
    }
    .spm-tahun { display: flex; align-items: center; gap: 10px; }
    .spm-tahun select {
        height: 42px; padding: 0 14px; border-radius: var(--st-radius);
        border: 1.5px solid var(--st-border); background: #fff;
        font-family: 'Barlow', sans-serif; font-weight: 700; font-size: 0.8125rem;
    }
    .spm-tw-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
    @media (max-width: 576px) { .spm-tw-grid { grid-template-columns: repeat(2, 1fr); } }
</style>

<div class="md-page">
    <div class="md-header">
        <div class="md-header__left">
            <div class="md-header__icon">
                <span class="material-symbols-outlined">flag</span>
            </div>
            <div>
                <h1 class="md-header__title">Master Data SPM</h1>
                <p class="md-header__subtitle">Kategori Standar Pelayanan Minimal, sasaran setahun, dan capaian per triwulan</p>
            </div>
        </div>
        <div class="spm-tahun">
            <form method="GET" action="{{ route('admin.masterdata.spm.index') }}" class="spm-tahun">
                <label for="pilihTahun" style="margin:0; font-weight:700; font-size:0.8125rem;">Tahun</label>
                <select name="tahun" id="pilihTahun" onchange="this.form.submit()">
                    @foreach ($tahunOpsi as $opsi)
                        <option value="{{ $opsi }}" {{ $opsi === $tahun ? 'selected' : '' }}>{{ $opsi }}</option>
                    @endforeach
                </select>
            </form>
            <button class="st-btn st-btn-primary" id="btnTambah">
                <span class="material-symbols-outlined">add</span>
                Tambah Kategori
            </button>
        </div>
    </div>

    <div class="st-card">
        <div class="st-card__header">
            <h3 class="st-card__header-title">
                <span class="material-symbols-outlined">list_alt</span>
                Kategori SPM &amp; Capaian {{ $tahun }}
            </h3>
        </div>
        <div class="table-responsive" style="padding: 0;">
            <table id="spmTable" class="table" style="width:100%; margin-bottom: 0;">
                <thead>
                    <tr>
                        <th>Kategori</th>
                        <th>Satuan</th>
                        <th>Sasaran</th>
                        <th>TW I</th>
                        <th>TW II</th>
                        <th>TW III</th>
                        <th>TW IV</th>
                        <th>Kumulatif</th>
                        <th>%</th>
                        <th>Status</th>
                        <th style="text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

{{-- Modal definisi kategori --}}
<div class="modal fade st-modal" id="formModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="formModalTitle">Tambah Kategori SPM</h5>
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:0.9;"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="kategoriForm">
                    <input type="hidden" id="form_id">
                    <div class="st-form-group">
                        <label for="form_nama">Nama Kategori *</label>
                        <input type="text" id="form_nama" name="nama" placeholder="cth: Pelayanan Kesehatan Ibu Hamil">
                        <div class="text-danger" id="error_nama"></div>
                    </div>
                    <div class="st-form-group">
                        <label for="form_satuan">Satuan *</label>
                        <input type="text" id="form_satuan" name="satuan" placeholder="cth: orang, anak, posyandu">
                        <div class="text-danger" id="error_satuan"></div>
                        <small class="text-muted">Satu satuan dipakai untuk sasaran maupun capaian.</small>
                    </div>
                    <div class="st-form-group">
                        <label for="form_urutan">Urutan Tampil</label>
                        <input type="number" id="form_urutan" name="urutan" min="0" value="0">
                        <div class="text-danger" id="error_urutan"></div>
                    </div>
                    <div class="st-form-group">
                        <label for="form_keterangan">Keterangan</label>
                        <textarea id="form_keterangan" name="keterangan" rows="2" placeholder="Opsional"></textarea>
                    </div>
                    <div class="st-form-check">
                        <input type="checkbox" id="form_aktif" name="is_active" checked>
                        <label for="form_aktif">Aktif</label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" data-dismiss="modal" style="background:#fff; color:var(--st-text-muted); border:1px solid var(--st-border); border-radius:var(--st-radius); font-family:'Barlow',sans-serif; font-weight:700;">Batal</button>
                <button type="button" class="btn" id="btnSimpan" style="background:var(--st-primary); color:#fff; border-radius:var(--st-radius); font-family:'Barlow',sans-serif; font-weight:700;">Simpan</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal angka per tahun --}}
<div class="modal fade st-modal" id="angkaModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Isi Angka <span id="angkaNamaKategori"></span> — {{ $tahun }}</h5>
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:0.9;"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="angkaForm">
                    <input type="hidden" id="angka_id">
                    <input type="hidden" id="angka_tahun" name="tahun" value="{{ $tahun }}">
                    <div class="st-form-group">
                        <label for="angka_sasaran">Sasaran Setahun *</label>
                        <input type="number" step="any" min="0" id="angka_sasaran" name="sasaran">
                        <div class="text-danger" id="error_sasaran"></div>
                    </div>
                    <label style="font-weight:700; font-size:0.8125rem;">Capaian per Triwulan</label>
                    <p class="text-muted" style="font-size:0.75rem; margin-bottom:8px;">
                        Isi hasil triwulan itu saja — sistem yang menjumlahkan. Kosongkan bila belum dilaporkan;
                        <strong>kosong tidak sama dengan 0</strong>.
                    </p>
                    <div class="spm-tw-grid">
                        @foreach (['tw1' => 'TW I', 'tw2' => 'TW II', 'tw3' => 'TW III', 'tw4' => 'TW IV'] as $key => $label)
                            <div class="st-form-group">
                                <label for="angka_{{ $key }}">{{ $label }}</label>
                                <input type="number" step="any" min="0" id="angka_{{ $key }}" name="{{ $key }}">
                                <div class="text-danger" id="error_{{ $key }}"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="st-form-group">
                        <label for="angka_catatan">Catatan / Kendala</label>
                        <textarea id="angka_catatan" name="catatan" rows="2" placeholder="cth: stok vaksin terlambat di TW II"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" data-dismiss="modal" style="background:#fff; color:var(--st-text-muted); border:1px solid var(--st-border); border-radius:var(--st-radius); font-family:'Barlow',sans-serif; font-weight:700;">Batal</button>
                <button type="button" class="btn" id="btnSimpanAngka" style="background:var(--st-primary); color:#fff; border-radius:var(--st-radius); font-family:'Barlow',sans-serif; font-weight:700;">Simpan Angka</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal hapus --}}
<div class="modal fade st-modal" id="deleteModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header modal-header-danger">
                <h5 class="modal-title">Konfirmasi Hapus</h5>
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:0.9;"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <p>Hapus kategori <strong id="deleteNama"></strong>?</p>
                <p style="color:#dc2626; font-weight:600;">Angka tahun-tahun sebelumnya tetap tersimpan dan kembali bila kategori dipulihkan.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" data-dismiss="modal" style="background:#fff; color:var(--st-text-muted); border:1px solid var(--st-border); border-radius:var(--st-radius); font-family:'Barlow',sans-serif; font-weight:700;">Batal</button>
                <button type="button" class="btn" id="confirmDelete" style="background:#dc2626; color:#fff; border-radius:var(--st-radius); font-family:'Barlow',sans-serif; font-weight:700;">Hapus</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
@parent
<script>
$(document).ready(function () {
    var TAHUN = {{ $tahun }};
    var angkaKosong = function (v) { return (v === null || v === undefined) ? '—' : v; };

    var table = $('#spmTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("admin.masterdata.spm.getData") }}?tahun=' + TAHUN,
        columns: [
            { data: 'nama', name: 'spm_kategori.nama' },
            { data: 'satuan', name: 'spm_kategori.satuan' },
            { data: 'sasaran', name: 'spm_capaian.sasaran', render: angkaKosong },
            { data: 'tw1', name: 'spm_capaian.tw1', render: angkaKosong },
            { data: 'tw2', name: 'spm_capaian.tw2', render: angkaKosong },
            { data: 'tw3', name: 'spm_capaian.tw3', render: angkaKosong },
            { data: 'tw4', name: 'spm_capaian.tw4', render: angkaKosong },
            { data: 'kumulatif', name: 'kumulatif', orderable: false, searchable: false, render: angkaKosong },
            { data: 'persen_badge', name: 'persen_badge', orderable: false, searchable: false },
            { data: 'status_badge', name: 'status_badge', orderable: false, searchable: false },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        order: [[0, 'asc']],
        pageLength: 25,
        language: { url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/id.json' }
    });

    function clearErrors() { $('.text-danger').text(''); }

    $('#btnTambah').on('click', function () {
        $('#kategoriForm')[0].reset();
        $('#form_id').val('');
        $('#form_aktif').prop('checked', true);
        clearErrors();
        $('#formModalTitle').text('Tambah Kategori SPM');
        $('#formModal').modal('show');
    });

    $(document).on('click', '.btn-edit', function () {
        var row = table.row($(this).closest('tr')).data();
        clearErrors();
        $('#form_id').val(row.id);
        $('#form_nama').val($('<div>').html(row.nama).text());
        $('#form_satuan').val($('<div>').html(row.satuan).text());
        $('#form_urutan').val(row.urutan);
        $('#form_keterangan').val(row.keterangan);
        $('#form_aktif').prop('checked', row.is_active == 1);
        $('#formModalTitle').text('Edit Kategori SPM');
        $('#formModal').modal('show');
    });

    $('#btnSimpan').on('click', function () {
        clearErrors();
        var id = $('#form_id').val();
        $.ajax({
            url: id
                ? '{{ route("admin.masterdata.spm.update", ":id") }}'.replace(':id', id)
                : '{{ route("admin.masterdata.spm.store") }}',
            type: id ? 'PUT' : 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                nama: $('#form_nama').val(),
                satuan: $('#form_satuan').val(),
                urutan: $('#form_urutan').val(),
                keterangan: $('#form_keterangan').val(),
                is_active: $('#form_aktif').is(':checked') ? 1 : 0
            },
            success: function (res) {
                $('#formModal').modal('hide');
                Swal.fire({ icon: 'success', title: 'Berhasil', text: res.message, timer: 2000 });
                table.draw();
            },
            error: tampilkanGalat
        });
    });

    $(document).on('click', '.btn-angka', function () {
        var row = table.row($(this).closest('tr')).data();
        clearErrors();
        $('#angka_id').val(row.id);
        $('#angkaNamaKategori').text($('<div>').html(row.nama).text());
        $('#angka_sasaran').val(row.sasaran);
        $('#angka_tw1').val(row.tw1);
        $('#angka_tw2').val(row.tw2);
        $('#angka_tw3').val(row.tw3);
        $('#angka_tw4').val(row.tw4);
        $('#angka_catatan').val(row.catatan);
        $('#angkaModal').modal('show');
    });

    $('#btnSimpanAngka').on('click', function () {
        clearErrors();
        // Keempat TW SELALU dikirim, termasuk yang kosong — supaya angka yang
        // dihapus petugas benar-benar hilang, bukan dipertahankan nilai lamanya.
        $.ajax({
            url: '{{ route("admin.masterdata.spm.angka", ":id") }}'.replace(':id', $('#angka_id').val()),
            type: 'PUT',
            data: {
                _token: '{{ csrf_token() }}',
                tahun: $('#angka_tahun').val(),
                sasaran: $('#angka_sasaran').val(),
                tw1: $('#angka_tw1').val(),
                tw2: $('#angka_tw2').val(),
                tw3: $('#angka_tw3').val(),
                tw4: $('#angka_tw4').val(),
                catatan: $('#angka_catatan').val()
            },
            success: function (res) {
                $('#angkaModal').modal('hide');
                Swal.fire({ icon: 'success', title: 'Berhasil', text: res.message, timer: 2000 });
                table.draw();
            },
            error: tampilkanGalat
        });
    });

    $(document).on('click', '.btn-toggle', function () {
        kirim('{{ route("admin.masterdata.spm.toggleStatus", ":id") }}'.replace(':id', $(this).data('id')), 'PATCH');
    });

    var idHapus = null;
    $(document).on('click', '.btn-delete', function () {
        var row = table.row($(this).closest('tr')).data();
        idHapus = row.id;
        $('#deleteNama').text($('<div>').html(row.nama).text());
        $('#deleteModal').modal('show');
    });

    $('#confirmDelete').on('click', function () {
        $('#deleteModal').modal('hide');
        kirim('{{ route("admin.masterdata.spm.destroy", ":id") }}'.replace(':id', idHapus), 'DELETE');
    });

    $(document).on('click', '.btn-restore', function () {
        kirim('{{ route("admin.masterdata.spm.restore", ":id") }}'.replace(':id', $(this).data('id')), 'PATCH');
    });

    function kirim(url, method) {
        $.ajax({
            url: url,
            type: method,
            data: { _token: '{{ csrf_token() }}' },
            success: function (res) {
                Swal.fire({ icon: 'success', title: 'Berhasil', text: res.message, timer: 1500 });
                table.draw();
            },
            error: tampilkanGalat
        });
    }

    function tampilkanGalat(xhr) {
        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
            $.each(xhr.responseJSON.errors, function (field, messages) {
                $('#error_' + field).text(messages[0]);
            });
            return;
        }
        Swal.fire({
            icon: 'error',
            title: 'Gagal',
            text: xhr.responseJSON ? xhr.responseJSON.message : 'Terjadi kesalahan'
        });
    }
});
</script>
@endsection

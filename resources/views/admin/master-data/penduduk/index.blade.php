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
@section('title') Master Data Penduduk @endsection
@section('title-content') Master Data @endsection
@section('item') Epidemiologi @endsection
@section('item-active') Jumlah Penduduk @endsection

@section('content')
<style>
    :root {
        --st-primary: oklch(0.48 0.14 145); --st-primary-dark: oklch(0.38 0.13 145);
        --st-primary-light: oklch(0.96 0.022 145);
        --st-bg:#f5f7f8; --st-surface:#ffffff;
        --st-text:#0f172a; --st-text-muted:#64748b; --st-border:#e2e8f0; --st-border-light:#f1f5f9;
        --st-radius:12px;
        --st-shadow:0 1px 3px 0 rgb(0 0 0/.06),0 1px 2px -1px rgb(0 0 0/.06);
        --st-shadow-md:0 4px 6px -1px rgb(0 0 0/.07),0 2px 4px -2px rgb(0 0 0/.07);
    }
    .md-page { font-family:'Barlow',sans-serif; color:var(--st-text); }
    .md-header { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:28px; }
    .md-header__left { display:flex; align-items:center; gap:14px; }
    .md-header__icon { display:flex; align-items:center; justify-content:center; width:48px; height:48px; background:var(--st-primary-light); border-radius:12px; color:var(--st-primary); }
    .md-header__icon .material-symbols-outlined { font-size:28px; }
    .md-header__title { font-size:1.75rem; font-weight:800; color:var(--st-text); letter-spacing:-.02em; margin:0; line-height:1.2; }
    .md-header__subtitle { font-size:.875rem; color:var(--st-text-muted); font-weight:500; margin:2px 0 0; }
    .st-btn { display:inline-flex; align-items:center; gap:8px; padding:0 18px; height:42px; border-radius:var(--st-radius); font-family:'Barlow',sans-serif; font-weight:700; font-size:.8125rem; border:1.5px solid transparent; cursor:pointer; transition:all .2s ease; text-decoration:none !important; white-space:nowrap; }
    .st-btn .material-symbols-outlined { font-size:20px; }
    .st-btn-primary { background:var(--st-primary); color:#fff; border-color:var(--st-primary); box-shadow:var(--st-shadow-md); }
    .st-btn-primary:hover { background:var(--st-primary-dark); border-color:var(--st-primary-dark); color:#fff; transform:translateY(-1px); }
    .st-card { background:var(--st-surface); border-radius:var(--st-radius); border:1px solid var(--st-border); box-shadow:var(--st-shadow); overflow:hidden; }
    .st-card__header { background:var(--st-primary); padding:14px 24px; display:flex; align-items:center; justify-content:space-between; }
    .st-card__header-title { display:flex; align-items:center; gap:10px; color:#fff; font-size:1.05rem; font-weight:700; margin:0; }
    .st-card__header-title .material-symbols-outlined { font-size:22px; }
    .st-card .dataTables_wrapper { font-family:'Barlow',sans-serif; }
    .st-card .dataTables_wrapper .dataTables_filter input { height:38px; padding:0 14px; border-radius:var(--st-radius); border:1px solid #cbd5e1; background:#fff; font-size:.8125rem; font-family:'Barlow',sans-serif; min-width:250px; }
    .st-card .dataTables_wrapper .dataTables_filter input:focus { border-color:var(--st-primary); box-shadow:0 0 0 3px oklch(0.48 0.14 145 / 0.15); outline:none; }
    .st-card .dataTables_wrapper .dataTables_length select { height:32px; padding:0 28px 0 10px; border-radius:8px; border:1px solid #cbd5e1; font-family:'Barlow',sans-serif; font-weight:600; font-size:.8125rem; }
    .st-card table.dataTable { border-collapse:collapse !important; width:100% !important; }
    .st-card table.dataTable thead th { background:#f8fafc; border-bottom:2px solid var(--st-border); color:var(--st-text-muted); font-size:.6875rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; padding:14px 16px; white-space:nowrap; }
    .st-card table.dataTable tbody td { padding:14px 16px; font-size:.8125rem; font-weight:500; color:#334155; border-bottom:1px solid var(--st-border-light); vertical-align:middle; }
    .st-card table.dataTable tbody tr:nth-child(even) { background:rgba(248,250,252,.5); }
    .st-card table.dataTable tbody tr:hover { background:rgba(219,234,254,.3) !important; }
    .st-card .badge { display:inline-flex; align-items:center; padding:4px 12px; border-radius:9999px; font-size:.6875rem; font-weight:700; font-family:'Barlow',sans-serif; border:1px solid transparent; }
    .st-card .badge.bg-primary { background:#eff6ff !important; color:#1e40af !important; border-color:#bfdbfe; }
    .st-card .badge.bg-info    { background:#f0f9ff !important; color:#0369a1 !important; border-color:#bae6fd; }
    .st-card .btn-group .btn { width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; border:none; padding:0; font-size:14px; opacity:.75; transition:all .2s ease; }
    .st-card table.dataTable tbody tr:hover .btn-group .btn { opacity:1; }
    .st-card .btn-group .btn.btn-warning { background:transparent; color:#d97706; }
    .st-card .btn-group .btn.btn-warning:hover { background:#fef3c7; }
    .st-card .btn-group .btn.btn-danger  { background:transparent; color:#dc2626; }
    .st-card .btn-group .btn.btn-danger:hover  { background:#fee2e2; }
    .st-card .dataTables_wrapper .dataTables_paginate .paginate_button { min-width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center; border-radius:8px !important; border:1px solid var(--st-border) !important; background:#fff !important; color:var(--st-text-muted) !important; font-size:.8125rem; font-weight:600; font-family:'Barlow',sans-serif; margin:0 2px; padding:0 8px !important; }
    .st-card .dataTables_wrapper .dataTables_paginate .paginate_button:hover { background:#f1f5f9 !important; color:var(--st-text) !important; }
    .st-card .dataTables_wrapper .dataTables_paginate .paginate_button.current { background:var(--st-primary) !important; color:#fff !important; border-color:var(--st-primary) !important; }
    .st-card .dataTables_wrapper .dataTables_paginate .paginate_button.disabled { opacity:.4; }
    .st-card .dataTables_wrapper .dataTables_info { font-size:.8125rem; font-weight:500; color:var(--st-text-muted); font-family:'Barlow',sans-serif; padding:16px 24px; }
    .st-card .dataTables_wrapper .dataTables_paginate { padding:16px 24px; }
    .st-modal .modal-content { border-radius:var(--st-radius); border:none; box-shadow:0 20px 60px -15px rgba(0,0,0,.25); overflow:hidden; }
    .st-modal .modal-header { background:var(--st-primary); color:#fff; border:none; padding:18px 24px; }
    .st-modal .modal-header.modal-header-danger { background:linear-gradient(135deg,#dc2626,#e11d48); }
    .st-modal .modal-title { font-family:'Barlow',sans-serif; font-weight:700; font-size:1.05rem; }
    .st-modal .modal-body { padding:24px; font-family:'Barlow',sans-serif; }
    .st-modal .modal-footer { border-top:1px solid var(--st-border-light); padding:16px 24px; }
    .st-modal .modal-footer .btn { border-radius:var(--st-radius); font-family:'Barlow',sans-serif; font-weight:700; font-size:.8125rem; padding:8px 20px; }
    .st-form-group { margin-bottom:16px; }
    .st-form-group label { font-size:.8125rem; font-weight:700; color:#334155; margin-bottom:6px; display:block; }
    .st-form-group input, .st-form-group select { width:100%; padding:10px 14px; border-radius:var(--st-radius); border:1px solid #cbd5e1; background:#f8fafc; color:#334155; font-family:'Barlow',sans-serif; font-size:.875rem; font-weight:500; }
    .st-form-group input:focus, .st-form-group select:focus { outline:none; border-color:var(--st-primary); box-shadow:0 0 0 3px oklch(0.48 0.14 145 / 0.15); }
    .st-form-group .text-danger { font-size:.75rem; margin-top:4px; }
    .filter-bar { display:flex; flex-wrap:wrap; gap:10px; padding:16px 24px 0; align-items:flex-end; }
    .filter-bar select { height:38px; padding:0 12px; border-radius:var(--st-radius); border:1px solid #cbd5e1; font-family:'Barlow',sans-serif; font-size:.8125rem; font-weight:600; min-width:140px; }
    @media(max-width:768px) { .md-header { flex-direction:column; } .md-header__title { font-size:1.4rem; } }
</style>

<div class="md-page">
    <div class="md-header">
        <div class="md-header__left">
            <div class="md-header__icon">
                <span class="material-symbols-outlined">groups</span>
            </div>
            <div>
                <h1 class="md-header__title">Data Jumlah Penduduk</h1>
                <p class="md-header__subtitle">Kelola data jumlah penduduk per kelurahan per tahun</p>
            </div>
        </div>
        <div>
            <button class="st-btn st-btn-primary" id="btnTambah">
                <span class="material-symbols-outlined">add</span>
                Tambah Data
            </button>
        </div>
    </div>

    <div class="st-card">
        <div class="st-card__header">
            <h3 class="st-card__header-title">
                <span class="material-symbols-outlined">table_view</span>
                Daftar Data Penduduk
            </h3>
        </div>
        <div class="filter-bar">
            <select id="filterTahun">
                <option value="">Semua Tahun</option>
                @foreach($tahunList as $t)
                    <option value="{{ $t }}">{{ $t }}</option>
                @endforeach
            </select>
            <select id="filterKategori">
                <option value="">Semua Kategori</option>
                <option value="Total">Total</option>
                <option value="Dibawah 15 Tahun">Dibawah 15 Tahun</option>
            </select>
        </div>
        <div class="table-responsive" style="padding:0;">
            <table id="pendudukTable" class="table" style="width:100%; margin-bottom:0;">
                <thead>
                    <tr>
                        <th>Tahun</th>
                        <th>Kategori</th>
                        <th>Kelurahan</th>
                        <th style="text-align:right;">Jumlah Penduduk</th>
                        <th style="text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

{{-- Create/Edit Modal --}}
<div class="modal fade st-modal" id="formModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="formModalTitle">Tambah Data Penduduk</h5>
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:.9;"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="pendudukForm">
                    <input type="hidden" id="form_id">
                    <div class="st-form-group">
                        <label for="form_tahun">Tahun *</label>
                        <select id="form_tahun" name="tahun">
                            <option value="">-- Pilih Tahun --</option>
                            @foreach($tahunList as $t)
                                <option value="{{ $t }}">{{ $t }}</option>
                            @endforeach
                        </select>
                        <div class="text-danger" id="error_tahun"></div>
                    </div>
                    <div class="st-form-group">
                        <label for="form_kategori">Kategori *</label>
                        <select id="form_kategori" name="kategori">
                            <option value="">-- Pilih Kategori --</option>
                            <option value="Total">Total</option>
                            <option value="Dibawah 15 Tahun">Dibawah 15 Tahun</option>
                        </select>
                        <div class="text-danger" id="error_kategori"></div>
                    </div>
                    <div class="st-form-group">
                        <label for="form_kelurahan">Kelurahan *</label>
                        <select id="form_kelurahan" name="id_kelurahan">
                            <option value="">-- Pilih Kelurahan --</option>
                            @foreach($kelurahans as $k)
                                <option value="{{ $k->id }}">{{ $k->name }}</option>
                            @endforeach
                        </select>
                        <div class="text-danger" id="error_id_kelurahan"></div>
                    </div>
                    <div class="st-form-group">
                        <label for="form_jumlah">Jumlah Penduduk *</label>
                        <input type="number" id="form_jumlah" name="jumlah_penduduk" min="0" placeholder="cth: 15000">
                        <div class="text-danger" id="error_jumlah_penduduk"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" data-dismiss="modal" style="background:#fff;color:var(--st-text-muted);border:1px solid var(--st-border);border-radius:var(--st-radius);font-family:'Barlow',sans-serif;font-weight:700;">Batal</button>
                <button type="button" class="btn" id="btnSimpan" style="background:var(--st-primary);color:#fff;border-radius:var(--st-radius);font-family:'Barlow',sans-serif;font-weight:700;">
                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:4px;">save</span>
                    Simpan
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Delete Modal --}}
<div class="modal fade st-modal" id="deleteModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header modal-header-danger">
                <h5 class="modal-title">
                    <span class="material-symbols-outlined" style="vertical-align:middle;margin-right:8px;">warning</span>
                    Konfirmasi Hapus
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:.9;"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <p>Hapus data penduduk <strong id="deleteLabel"></strong>?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" data-dismiss="modal" style="background:#fff;color:var(--st-text-muted);border:1px solid var(--st-border);border-radius:var(--st-radius);font-family:'Barlow',sans-serif;font-weight:700;">Batal</button>
                <button type="button" class="btn" id="confirmDelete" style="background:#dc2626;color:#fff;border-radius:var(--st-radius);font-family:'Barlow',sans-serif;font-weight:700;">
                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:4px;">delete</span>
                    Hapus
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
@parent
<script>
$(document).ready(function() {
    var table = $('#pendudukTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("admin.masterdata.penduduk.getData") }}',
            data: function(d) {
                d.tahun    = $('#filterTahun').val();
                d.kategori = $('#filterKategori').val();
            }
        },
        columns: [
            { data: 'tahun',          name: 'tahun' },
            { data: 'kategori',       name: 'kategori', render: function(d) {
                var color = d === 'Total' ? 'bg-primary' : 'bg-info';
                return '<span class="badge ' + color + '">' + d + '</span>';
            }},
            { data: 'nama_kelurahan', name: 'kelurahan.name' },
            { data: 'jumlah_fmt',     name: 'jumlah_penduduk', className: 'text-right', orderable: true, searchable: false },
            { data: 'action',         name: 'action', orderable: false, searchable: false, className: 'text-center' }
        ],
        order: [[0, 'desc'], [2, 'asc']],
        pageLength: 25,
        language: { url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/id.json' }
    });

    $('#filterTahun, #filterKategori').on('change', function() { table.draw(); });

    function clearErrors() { $('.text-danger').text(''); }
    function resetForm() { $('#pendudukForm')[0].reset(); $('#form_id').val(''); clearErrors(); }

    $('#btnTambah').on('click', function() {
        resetForm();
        $('#formModalTitle').text('Tambah Data Penduduk');
        $('#formModal').modal('show');
    });

    $(document).on('click', '.btn-edit', function() {
        var row = table.row($(this).closest('tr')).data();
        resetForm();
        $('#formModalTitle').text('Edit Data Penduduk');
        $('#form_id').val(row.id);
        $('#form_tahun').val(row.tahun);
        $('#form_kategori').val(row.kategori);
        $('#form_kelurahan').val(row.id_kelurahan);
        $('#form_jumlah').val(row.jumlah_penduduk);
        $('#formModal').modal('show');
    });

    $('#btnSimpan').on('click', function() {
        clearErrors();
        var id  = $('#form_id').val();
        var url = id
            ? '{{ route("admin.masterdata.penduduk.update", ":id") }}'.replace(':id', id)
            : '{{ route("admin.masterdata.penduduk.store") }}';

        $.ajax({
            url: url, type: id ? 'PUT' : 'POST',
            data: {
                _token:          '{{ csrf_token() }}',
                tahun:           $('#form_tahun').val(),
                kategori:        $('#form_kategori').val(),
                id_kelurahan:    $('#form_kelurahan').val(),
                jumlah_penduduk: $('#form_jumlah').val(),
            },
            success: function(r) {
                $('#formModal').modal('hide');
                Swal.fire({ icon: 'success', title: 'Berhasil', text: r.message, timer: 2000 });
                table.draw();
            },
            error: function(xhr) {
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    $.each(xhr.responseJSON.errors, function(f, m) { $('#error_' + f).text(m[0]); });
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal', text: xhr.responseJSON ? xhr.responseJSON.message : 'Terjadi kesalahan' });
                }
            }
        });
    });

    var deleteId = null;
    $(document).on('click', '.btn-delete', function() {
        var row = table.row($(this).closest('tr')).data();
        deleteId = row.id;
        $('#deleteLabel').text(row.tahun + ' – ' + row.kategori + ' – ' + row.nama_kelurahan);
        $('#deleteModal').modal('show');
    });

    $('#confirmDelete').on('click', function() {
        if (!deleteId) return;
        $.ajax({
            url: '{{ route("admin.masterdata.penduduk.destroy", ":id") }}'.replace(':id', deleteId),
            type: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: function(r) {
                $('#deleteModal').modal('hide');
                Swal.fire({ icon: 'success', title: 'Berhasil', text: r.message, timer: 2000 });
                table.draw();
            },
            error: function(xhr) {
                $('#deleteModal').modal('hide');
                Swal.fire({ icon: 'error', title: 'Gagal', text: xhr.responseJSON ? xhr.responseJSON.message : 'Terjadi kesalahan' });
            }
        });
    });
});
</script>
@endsection

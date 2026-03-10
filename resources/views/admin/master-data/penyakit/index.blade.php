@extends('admin::layouts.app')
@section('title') Admin @endsection
@section('title-content') Master Data @endsection
@section('item') Epidemiologi @endsection
@section('item-active') Jenis Penyakit @endsection

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

    .md-page { font-family: 'Manrope', sans-serif; color: var(--st-text); }

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
        height: 42px; border-radius: var(--st-radius); font-family: 'Manrope', sans-serif;
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
        color: var(--st-text-muted) !important; font-size: 0.8125rem; font-weight: 600; font-family: 'Manrope', sans-serif; margin: 0 2px; padding: 0 8px !important;
    }
    .st-card .dataTables_wrapper .dataTables_paginate .paginate_button:hover { background: #f1f5f9 !important; color: var(--st-text) !important; }
    .st-card .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: var(--st-primary) !important; color: #fff !important; border-color: var(--st-primary) !important; }
    .st-card .dataTables_wrapper .dataTables_paginate .paginate_button.disabled { opacity: 0.4; }
    .st-card .dataTables_wrapper .dataTables_info { font-size: 0.8125rem; font-weight: 500; color: var(--st-text-muted); font-family: 'Manrope', sans-serif; padding: 16px 24px; }
    .st-card .dataTables_wrapper .dataTables_paginate { padding: 16px 24px; }

    .st-modal .modal-content { border-radius: var(--st-radius); border: none; box-shadow: 0 20px 60px -15px rgba(0,0,0,0.25); overflow: hidden; }
    .st-modal .modal-header { background: linear-gradient(135deg, #047857, var(--st-primary)); color: #fff; border: none; padding: 18px 24px; }
    .st-modal .modal-header.modal-header-danger { background: linear-gradient(135deg, #dc2626, #e11d48); }
    .st-modal .modal-title { font-family: 'Manrope', sans-serif; font-weight: 700; font-size: 1.05rem; }
    .st-modal .modal-body { padding: 24px; font-family: 'Manrope', sans-serif; }
    .st-modal .modal-footer { border-top: 1px solid var(--st-border-light); padding: 16px 24px; }
    .st-modal .modal-footer .btn { border-radius: var(--st-radius); font-family: 'Manrope', sans-serif; font-weight: 700; font-size: 0.8125rem; padding: 8px 20px; }

    .st-form-group { margin-bottom: 16px; }
    .st-form-group label { font-size: 0.8125rem; font-weight: 700; color: #334155; margin-bottom: 6px; display: block; }
    .st-form-group input, .st-form-group textarea, .st-form-group select {
        width: 100%; padding: 10px 14px; border-radius: var(--st-radius); border: 1px solid #cbd5e1;
        background: #f8fafc; color: #334155; font-family: 'Manrope', sans-serif; font-size: 0.875rem; font-weight: 500;
    }
    .st-form-group input:focus, .st-form-group textarea:focus, .st-form-group select:focus {
        outline: none; border-color: var(--st-primary); box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.12);
    }
    .st-form-group .text-danger { font-size: 0.75rem; margin-top: 4px; }

    .st-form-check { display: flex; align-items: center; gap: 8px; margin-top: 8px; }
    .st-form-check input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--st-primary); }
    .st-form-check label { margin: 0; font-size: 0.875rem; font-weight: 600; }

    @media (max-width: 768px) {
        .md-header { flex-direction: column; }
        .md-header__title { font-size: 1.4rem; }
    }
</style>

<div class="md-page">
    <div class="md-header">
        <div class="md-header__left">
            <div class="md-header__icon">
                <span class="material-symbols-outlined">coronavirus</span>
            </div>
            <div>
                <h1 class="md-header__title">Master Data Jenis Penyakit</h1>
                <p class="md-header__subtitle">Kelola data referensi jenis penyakit untuk surveilans epidemiologi</p>
            </div>
        </div>
        <div>
            <button class="st-btn st-btn-primary" id="btnTambah">
                <span class="material-symbols-outlined">add</span>
                Tambah Penyakit
            </button>
        </div>
    </div>

    <div class="st-card">
        <div class="st-card__header">
            <h3 class="st-card__header-title">
                <span class="material-symbols-outlined">list_alt</span>
                Daftar Jenis Penyakit Surveilans
            </h3>
        </div>
        <div class="table-responsive" style="padding: 0;">
            <table id="penyakitTable" class="table" style="width:100%; margin-bottom: 0;">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama Penyakit</th>
                        <th>Kategori</th>
                        <th>Deskripsi</th>
                        <th>Status</th>
                        <th style="text-align: center;">Aksi</th>
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
                <h5 class="modal-title" id="formModalTitle">Tambah Jenis Penyakit</h5>
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity: 0.9;"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="penyakitForm">
                    <input type="hidden" id="form_id">
                    <div class="st-form-group">
                        <label for="form_kode">Kode Penyakit *</label>
                        <input type="text" id="form_kode" name="kode_penyakit" placeholder="cth: CAMPAK, DBD, DIARE">
                        <div class="text-danger" id="error_kode_penyakit"></div>
                    </div>
                    <div class="st-form-group">
                        <label for="form_nama">Nama Penyakit *</label>
                        <input type="text" id="form_nama" name="nama_penyakit" placeholder="cth: Campak / Measles">
                        <div class="text-danger" id="error_nama_penyakit"></div>
                    </div>
                    <div class="st-form-group">
                        <label for="form_kategori">Kategori *</label>
                        <select id="form_kategori" name="kategori">
                            <option value="">-- Pilih Kategori --</option>
                            <option value="PD3I">PD3I</option>
                            <option value="menular_langsung">Menular Langsung</option>
                            <option value="vector_borne">Vector Borne</option>
                            <option value="zoonosis">Zoonosis</option>
                            <option value="lainnya">Lainnya</option>
                        </select>
                        <div class="text-danger" id="error_kategori"></div>
                    </div>
                    <div class="st-form-group">
                        <label for="form_deskripsi">Deskripsi</label>
                        <textarea id="form_deskripsi" name="deskripsi" rows="3" placeholder="Deskripsi penyakit (opsional)"></textarea>
                    </div>
                    <div class="st-form-check">
                        <input type="checkbox" id="form_aktif" name="is_active" checked>
                        <label for="form_aktif">Aktif</label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" data-dismiss="modal" style="background:#fff; color:var(--st-text-muted); border:1px solid var(--st-border); border-radius:var(--st-radius); font-family:'Manrope',sans-serif; font-weight:700;">Batal</button>
                <button type="button" class="btn" id="btnSimpan" style="background:var(--st-primary); color:#fff; border-radius:var(--st-radius); font-family:'Manrope',sans-serif; font-weight:700;">
                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align:middle; margin-right:4px;">save</span>
                    Simpan
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Delete Confirmation Modal --}}
<div class="modal fade st-modal" id="deleteModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header modal-header-danger">
                <h5 class="modal-title">
                    <span class="material-symbols-outlined" style="vertical-align:middle; margin-right:8px;">warning</span>
                    Konfirmasi Hapus
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:0.9;"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus jenis penyakit <strong id="deletePenyakitName"></strong>?</p>
                <p style="color:#dc2626; font-weight:600; margin-top:8px;">
                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">error</span>
                    Jika masih digunakan oleh data surveilans, penyakit akan di-nonaktifkan (soft-delete).
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" data-dismiss="modal" style="background:#fff; color:var(--st-text-muted); border:1px solid var(--st-border); border-radius:var(--st-radius); font-family:'Manrope',sans-serif; font-weight:700;">Batal</button>
                <button type="button" class="btn" id="confirmDelete" style="background:#dc2626; color:#fff; border-radius:var(--st-radius); font-family:'Manrope',sans-serif; font-weight:700;">
                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align:middle; margin-right:4px;">delete</span>
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
    var table = $('#penyakitTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("admin.masterdata.penyakit.getData") }}',
        columns: [
            { data: 'kode_penyakit', name: 'kode_penyakit' },
            { data: 'nama_penyakit', name: 'nama_penyakit' },
            { data: 'kategori_badge', name: 'kategori' },
            { data: 'deskripsi', name: 'deskripsi', render: function(d) { return d ? (d.length > 80 ? d.substring(0, 80) + '...' : d) : '-'; } },
            { data: 'status_badge', name: 'is_active' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        order: [[0, 'asc']],
        pageLength: 25,
        language: { url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/id.json' }
    });

    function clearErrors() {
        $('.text-danger').text('');
    }

    function resetForm() {
        $('#penyakitForm')[0].reset();
        $('#form_id').val('');
        $('#form_aktif').prop('checked', true);
        clearErrors();
    }

    // Open create modal
    $('#btnTambah').on('click', function() {
        resetForm();
        $('#formModalTitle').text('Tambah Jenis Penyakit');
        $('#formModal').modal('show');
    });

    // Open edit modal
    $(document).on('click', '.btn-edit', function() {
        var id = $(this).data('id');
        resetForm();
        $('#formModalTitle').text('Edit Jenis Penyakit');

        var rowData = table.row($(this).closest('tr')).data();
        $('#form_id').val(rowData.id);
        $('#form_kode').val(rowData.kode_penyakit);
        $('#form_nama').val(rowData.nama_penyakit);
        $('#form_kategori').val(rowData.kategori);
        $('#form_deskripsi').val(rowData.deskripsi);
        $('#form_aktif').prop('checked', rowData.is_active == 1);
        $('#formModal').modal('show');
    });

    // Save (create or update)
    $('#btnSimpan').on('click', function() {
        clearErrors();
        var id = $('#form_id').val();
        var url = id
            ? '{{ route("admin.masterdata.penyakit.update", ":id") }}'.replace(':id', id)
            : '{{ route("admin.masterdata.penyakit.store") }}';
        var method = id ? 'PUT' : 'POST';

        $.ajax({
            url: url,
            type: method,
            data: {
                _token: '{{ csrf_token() }}',
                kode_penyakit: $('#form_kode').val(),
                nama_penyakit: $('#form_nama').val(),
                kategori: $('#form_kategori').val(),
                deskripsi: $('#form_deskripsi').val(),
                is_active: $('#form_aktif').is(':checked') ? 1 : 0,
            },
            success: function(response) {
                $('#formModal').modal('hide');
                Swal.fire({ icon: 'success', title: 'Berhasil', text: response.message, timer: 2000 });
                table.draw();
            },
            error: function(xhr) {
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    var errors = xhr.responseJSON.errors;
                    $.each(errors, function(field, messages) {
                        $('#error_' + field).text(messages[0]);
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal', text: xhr.responseJSON ? xhr.responseJSON.message : 'Terjadi kesalahan' });
                }
            }
        });
    });

    // Toggle status
    $(document).on('click', '.btn-toggle', function() {
        var id = $(this).data('id');
        $.ajax({
            url: '{{ route("admin.masterdata.penyakit.toggleStatus", ":id") }}'.replace(':id', id),
            type: 'PATCH',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                Swal.fire({ icon: 'success', title: 'Berhasil', text: response.message, timer: 1500 });
                table.draw();
            },
            error: function(xhr) {
                Swal.fire({ icon: 'error', title: 'Gagal', text: xhr.responseJSON ? xhr.responseJSON.message : 'Terjadi kesalahan' });
            }
        });
    });

    // Delete
    var deleteId = null;
    $(document).on('click', '.btn-delete', function() {
        deleteId = $(this).data('id');
        var rowData = table.row($(this).closest('tr')).data();
        $('#deletePenyakitName').text(rowData.nama_penyakit);
        $('#deleteModal').modal('show');
    });

    $('#confirmDelete').on('click', function() {
        if (!deleteId) return;
        $.ajax({
            url: '{{ route("admin.masterdata.penyakit.destroy", ":id") }}'.replace(':id', deleteId),
            type: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                $('#deleteModal').modal('hide');
                Swal.fire({
                    icon: response.soft_deleted ? 'info' : 'success',
                    title: response.soft_deleted ? 'Soft-Deleted' : 'Berhasil',
                    text: response.message,
                    timer: 3000
                });
                table.draw();
            },
            error: function(xhr) {
                $('#deleteModal').modal('hide');
                Swal.fire({ icon: 'error', title: 'Gagal', text: xhr.responseJSON ? xhr.responseJSON.message : 'Terjadi kesalahan' });
            }
        });
    });

    // Restore soft-deleted record
    $(document).on('click', '.btn-restore', function() {
        var id = $(this).data('id');
        Swal.fire({
            title: 'Restore Penyakit?',
            text: 'Jenis penyakit ini akan dikembalikan ke status aktif.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#047857',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Restore',
            cancelButtonText: 'Batal'
        }).then(function(result) {
            if (result.isConfirmed) {
                $.ajax({
                    url: '{{ route("admin.masterdata.penyakit.restore", ":id") }}'.replace(':id', id),
                    type: 'PATCH',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function(response) {
                        Swal.fire({ icon: 'success', title: 'Berhasil', text: response.message, timer: 2000 });
                        table.draw();
                    },
                    error: function(xhr) {
                        Swal.fire({ icon: 'error', title: 'Gagal', text: xhr.responseJSON ? xhr.responseJSON.message : 'Terjadi kesalahan' });
                    }
                });
            }
        });
    });
});
</script>
@endsection

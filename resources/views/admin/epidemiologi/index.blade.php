@extends('admin::layouts.app')
@section('title') Daftar Kasus Epidemiologi - Si Rindu @endsection
@section('title-content') Epidemiologi @endsection
@section('item') Epidemiologi @endsection
@section('item-active') Daftar Kasus @endsection

@section('content')
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">
    {{ session('success') }}<button type="button" class="close" data-dismiss="alert">&times;</button>
</div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <a href="{{ route('admin.epidemiologi.create') }}" class="btn btn-primary"><i class="fa fa-plus mr-1"></i>Tambah Kasus</a>
        <a href="{{ route('admin.epidemiologi.dashboard') }}" class="btn btn-info"><i class="fa fa-chart-line mr-1"></i>Dashboard</a>
        <a href="{{ route('admin.epidemiologi.map') }}" class="btn btn-success"><i class="fa fa-map mr-1"></i>Peta Sebaran</a>
    </div>
    <a href="{{ route('admin.epidemiologi.exportExcel') }}" class="btn btn-warning"><i class="fa fa-download mr-1"></i>Export CSV</a>
</div>

<!-- Filter -->
<div class="card mb-3">
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <select id="filter-disease" class="form-control form-control-sm">
                    <option value="">Semua Penyakit</option>
                    @foreach($diseases as $d)
                    <option value="{{ $d->id }}">{{ $d->nama_penyakit }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select id="filter-status" class="form-control form-control-sm">
                    <option value="">Semua Status</option>
                    <option value="suspek">Suspek</option>
                    <option value="probable">Probable</option>
                    <option value="konfirmasi">Konfirmasi</option>
                    <option value="discarded">Discarded</option>
                </select>
            </div>
            <div class="col-md-2">
                <select id="filter-kec" class="form-control form-control-sm">
                    <option value="">Semua Kecamatan</option>
                    @foreach($kecamatanList as $kec)
                    <option value="{{ $kec->id }}">{{ $kec->nama_kecamatan }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" id="filter-start" class="form-control form-control-sm" placeholder="Dari tanggal">
            </div>
            <div class="col-md-2">
                <input type="date" id="filter-end" class="form-control form-control-sm" placeholder="Sampai tanggal">
            </div>
            <div class="col-md-1">
                <button id="btn-filter" class="btn btn-primary btn-sm btn-block">Cari</button>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table id="tbl-epidemiologi" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>No Reg</th>
                        <th>Nama</th>
                        <th>Jenis Kasus</th>
                        <th>Tgl Onset</th>
                        <th>Wilayah</th>
                        <th>Status Kasus</th>
                        <th>Kondisi</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="modalDelete" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Hapus Kasus</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">Yakin ingin menghapus kasus ini?</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                <button type="button" id="btn-confirm-delete" class="btn btn-danger btn-sm">Hapus</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('custom_scripts')
<script>
var deleteId = null;
var table = $('#tbl-epidemiologi').DataTable({
    processing: true,
    serverSide: true,
    ajax: {
        url: '{{ route("admin.epidemiologi.getCases") }}',
        data: function(d) {
            d.id_jenis_kasus = $('#filter-disease').val();
            d.status_kasus   = $('#filter-status').val();
            d.id_kec         = $('#filter-kec').val();
            d.start_date     = $('#filter-start').val();
            d.end_date       = $('#filter-end').val();
        }
    },
    columns: [
        { data: null, render: function(d, t, r, m) { return m.row + 1; }, orderable: false },
        { data: 'no_registrasi' },
        { data: 'nama_lengkap' },
        { data: 'jenis_kasus_nama', orderable: false },
        { data: 'tanggal_onset' },
        { data: 'wilayah', orderable: false },
        { data: 'status_kasus_badge', orderable: false },
        { data: 'kondisi_badge', orderable: false },
        { data: 'action', orderable: false }
    ],
    order: [[4, 'desc']],
    language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/id.json' }
});

$('#btn-filter').on('click', function() { table.ajax.reload(); });

$(document).on('click', '.btn-delete', function() {
    deleteId = $(this).data('id');
    $('#modalDelete').modal('show');
});

$('#btn-confirm-delete').on('click', function() {
    $.ajax({
        url: '/admin/epidemiologi/' + deleteId,
        type: 'DELETE',
        data: { _token: '{{ csrf_token() }}' },
        success: function(res) {
            $('#modalDelete').modal('hide');
            table.ajax.reload();
        }
    });
});
</script>
@endsection

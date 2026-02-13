@extends('admin::layouts.app')
@section('title') Admin @endsection
@section('title-content') Epidemiologi @endsection
@section('item') Surveillance @endsection
@section('item-active') Kasus @endsection

@section('content')
<div class="container-fluid">
    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">
            <i class="fa fa-virus mr-2"></i>
            Surveillance Kasus Penyakit Menular
        </h2>
        <div>
            <a href="{{ route('admin.epidemiologi.dashboard') }}" class="btn btn-info">
                <i class="fa fa-chart-line"></i> Dashboard Analytics
            </a>
            <a href="{{ route('admin.epidemiologi.map') }}" class="btn btn-success">
                <i class="fa fa-map-marked-alt"></i> Peta Sebaran
            </a>
            <a href="{{ route('admin.epidemiologi.create') }}" class="btn btn-primary">
                <i class="fa fa-plus"></i> Tambah Kasus Baru
            </a>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fa fa-filter"></i> Filter Data</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Jenis Penyakit</label>
                        <select id="disease_filter" class="form-control">
                            <option value="">Semua Penyakit</option>
                            @foreach($diseases as $disease)
                                <option value="{{ $disease->id }}">{{ $disease->nama_penyakit }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Status Kasus</label>
                        <select id="status_filter" class="form-control">
                            <option value="">Semua Status</option>
                            <option value="suspected">Suspected</option>
                            <option value="probable">Probable</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="discarded">Discarded</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Kecamatan</label>
                        <select id="kecamatan_filter" class="form-control">
                            <option value="">Semua Kecamatan</option>
                            @foreach($kecamatanList as $kec)
                                <option value="{{ $kec->id }}">{{ $kec->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button id="reset_filter" class="btn btn-secondary btn-block">
                            <i class="fa fa-redo"></i> Reset Filter
                        </button>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <button id="export_excel" class="btn btn-success">
                        <i class="fa fa-file-excel"></i> Export Excel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table Card -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fa fa-list"></i> Daftar Kasus Surveillance</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="casesTable" class="table table-bordered table-striped table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th>No. Registrasi</th>
                            <th>NIK</th>
                            <th>Nama Lengkap</th>
                            <th>Jenis Penyakit</th>
                            <th>Lokasi</th>
                            <th>Tanggal Onset</th>
                            <th>Status Kasus</th>
                            <th>Kondisi Akhir</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Konfirmasi Hapus</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus kasus ini?</p>
                <p class="text-danger"><strong>Tindakan ini tidak dapat dibatalkan.</strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">Hapus</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
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
            }
        },
        columns: [
            { data: 'no_registrasi', name: 'no_registrasi' },
            { data: 'nik', name: 'nik' },
            { data: 'nama_lengkap', name: 'nama_lengkap' },
            { data: 'disease', name: 'jenisKasus.nama_penyakit' },
            { data: 'location', name: 'location', orderable: false, searchable: false },
            { data: 'tanggal_onset', name: 'tanggal_onset' },
            { data: 'status_badge', name: 'status_kasus' },
            { data: 'outcome_badge', name: 'kondisi_akhir' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        order: [[5, 'desc']], // Order by tanggal_onset descending
        pageLength: 25,
        language: {
            url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/id.json'
        }
    });

    // Filter change events
    $('#disease_filter, #status_filter, #kecamatan_filter').on('change', function() {
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
        window.open(url, '_blank');
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
</script>
@endsection

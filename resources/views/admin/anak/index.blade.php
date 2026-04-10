@extends('admin::layouts.app')
@section('title')
Admin - Si Rindu
@endsection
@section('title-content')
Data
@endsection
@section('item')
Data
@endsection
@section('item-active')
Anak
@endsection
@section('content')
<a href="{{route('admin.createAnak')}}" class="btn btn-primary">Create Data</a>
<a href="{{route('admin.exportView')}}"  class="btn btn-warning">Export Data</a>
@if($isSuperAdmin)
<button type="button" class="btn btn-success" data-toggle="modal" data-target="#modalImportKohort">
    <i class="fa fa-upload"></i> Import Kohort Excel
</button>
@endif
<br><br>

{{-- Flash: File Diterima --}}
@if(session('import_queued'))
<div class="alert alert-info alert-dismissible fade show mt-2" role="alert">
    {{ session('import_queued') }}
    <button type="button" class="close" data-dismiss="alert" aria-label="Tutup">&times;</button>
</div>
@endif

{{-- Panel Status Import Kohort --}}
@if($isSuperAdmin && $kohortImportLogs->isNotEmpty())
<div class="card mt-3" id="kohort-import-status-panel">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="fa fa-history"></i> Status Import Kohort</h6>
        <small class="text-muted" id="kohort-import-last-refresh"></small>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0" id="kohort-import-log-table">
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
                @foreach($kohortImportLogs as $log)
                <tr data-log-id="{{ $log->id }}" data-status="{{ $log->status }}">
                    <td class="small">{{ $log->filename }}</td>
                    <td>
                        <span class="badge badge-{{ $log->statusColor() }}">{{ $log->statusLabel() }}</span>
                    </td>
                    <td>{{ $log->success_count ?? '—' }}</td>
                    <td>{{ $log->failure_count ?? '—' }}</td>
                    <td class="small">{{ $log->completed_at ? $log->completed_at->format('d/m/Y H:i') : '—' }}</td>
                    <td>
                        @if($log->isDone() && $log->failure_count > 0)
                        <button class="btn btn-xs btn-outline-warning btn-lihat-error-kohort"
                            data-id="{{ $log->id }}"
                            data-failures="{{ json_encode($log->failures) }}">
                            Lihat Error
                        </button>
                        @elseif($log->isFailed())
                        <button class="btn btn-xs btn-outline-danger btn-lihat-error-kohort"
                            data-id="{{ $log->id }}"
                            data-failures="{{ json_encode($log->failures) }}">
                            Lihat Detail
                        </button>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Modal detail error --}}
<div class="modal fade" id="modalKohortErrorDetail" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-exclamation-triangle text-warning mr-1"></i> Detail Baris Bermasalah</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-0">
                <div class="px-3 pt-3 pb-2 border-bottom">
                    <small id="kohort-error-count" class="text-muted"></small>
                </div>
                <ol id="kohort-error-list" class="small mb-0 px-4 py-3" style="max-height:400px;overflow-y:auto;"></ol>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Modal Import Kohort Excel --}}
@if($isSuperAdmin)
<div class="modal fade" id="modalImportKohort" tabindex="-1" role="dialog" aria-labelledby="modalImportKohortLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalImportKohortLabel">
                    <i class="fa fa-upload"></i> Import Kohort Excel
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span>&times;</span></button>
            </div>
            <form action="{{ route('admin.importKohort') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="file_kohort" class="form-label fw-semibold">Pilih File Excel Kohort Puskesmas</label>
                        <input type="file" name="file_kohort" id="file_kohort" class="form-control" accept=".xlsx" required>
                        <small class="form-text text-muted">Format: .xlsx. Maksimal 20 MB. Sheet "balita" akan diproses.</small>
                    </div>
                    <div class="alert alert-info small mb-0">
                        <strong>Catatan:</strong>
                        <ul class="mb-0 mt-1">
                            <li>Gunakan file Excel Kohort Puskesmas resmi. Data dibaca dari sheet <em>balita</em>.</li>
                            <li>Data anak dengan NIK yang sama akan diperbarui otomatis (tidak digandakan).</li>
                            <li>Kunjungan posyandu dan data imunisasi ikut diimpor sekaligus.</li>
                            <li>Proses berjalan di latar belakang — halaman boleh ditinggal.</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fa fa-upload"></i> Upload &amp; Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<div class="table-responsive">
    <table id="tabel-anak" class="table table-striped">
        <thead>
            <tr>
                <th scope="col">No</th>
                <th scope="col">No KK</th>
                <th scope="col">NIK</th>
                <th scope="col">Nama</th>
                <th scope="col">NIK Orang Tua</th>
                <th scope="col">Nama Ibu</th>
                <th scope="col">Nama Ayah</th>
                <th scope="col">Edit</th>
                <th scope="col">Delete</th>
            </tr>
        </thead>
        <tbody>
        </tbody>
    </table>
</div>
@endsection
@section('custom_scripts')
<script type="text/javascript">
    $(function() {
        var table = $('#tabel-anak').DataTable({
            responsive: true,
            processing: true,
            serverSide: true,
            ajax: "{{ route('admin.getAnak') }}",
            columns: [{
                    data: 'DT_RowIndex',
                    name: 'DT_RowIndex',
                    orderable: false,
                    searchable: false
                },
                {
                    data: 'no_kk',
                    name: 'no_kk',
                },
                {
                    data: 'nik',
                    name: 'nik',
                },
                {
                    data: 'nama',
                    name: 'nama',
                },
                {
                    data: 'nik_ortu',
                    name: 'nik_ortu',
                },
                {
                    data: 'nama_ibu',
                    name: 'nama_ibu',
                },
                {
                    data: 'nama_ayah',
                    name: 'nama_ayah',
                },
                {
                    data: 'edit',
                    name: 'edit',
                    orderable: false,
                    searchable: false
                },
                {
                    data: 'delete',
                    name: 'delete',
                    orderable: false,
                    searchable: false
                }
            ],
            columnDefs: [{
                targets: 5,
                function(data, type, row) {
                    return data.substr(0, 50);
                }
            }]
        });

    });

    //--------------Fungsi Delete ------------
    function deleteItemAnak(e) {

        let id = e.getAttribute('data-id');
        let token = '{{ csrf_token() }}';

        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'No, cancel!',
            reverseButtons: true
        }).then((result) => {
            if (result.value) {
                if (result.isConfirmed) {

                    $.ajax({
                        type: "DELETE",
                        url: '{{ url("admin/destroy-data-dasar-anak")}}' + '/' + id,
                        data: {
                            id: id,
                            '_token': token
                        },
                        success: function(data) {
                            if (data.success) {
                                Swal.fire(
                                    'Deleted!',
                                    'Your file has been deleted.',
                                    "success"
                                );
                                $("#" + id + "").remove();
                                window.location.reload(true); // you can add name div to remove
                            }

                        }
                    });

                }

            } else if (
                result.dismiss === Swal.DismissReason.cancel
            ) {
                Swal.fire(
                    'Cancelled',
                    'Your imaginary file is safe :)',
                    'error'
                );
            }
        });

    }

@if($isSuperAdmin && $kohortImportLogs->isNotEmpty())
// ===== Import Kohort Status Polling =====
(function() {
    var statusUrl = '{{ route("admin.importKohortStatus") }}';
    var hasActive = {{ $kohortImportLogs->whereIn('status', ['pending', 'processing'])->isNotEmpty() ? 'true' : 'false' }};
    var pollTimer = null;

    var statusColors = { pending: 'warning', processing: 'info', done: 'success', failed: 'danger' };
    var statusLabels = { pending: 'Menunggu', processing: 'Diproses', done: 'Selesai', failed: 'Gagal' };

    function refreshKohortStatus() {
        $.getJSON(statusUrl, function(logs) {
            var stillActive = false;
            logs.forEach(function(log) {
                var $row = $('tr[data-log-id="' + log.id + '"]');
                if (!$row.length) return;

                var oldStatus = $row.data('status');
                if (oldStatus === log.status) {
                    if (log.status === 'pending' || log.status === 'processing') stillActive = true;
                    return;
                }

                $row.data('status', log.status);
                $row.find('.badge')
                    .removeClass('badge-warning badge-info badge-success badge-danger')
                    .addClass('badge-' + (statusColors[log.status] || 'secondary'))
                    .text(statusLabels[log.status] || log.status);

                $row.find('td').eq(2).text(log.success_count !== null ? log.success_count : '—');
                $row.find('td').eq(3).text(log.failure_count !== null ? log.failure_count : '—');
                $row.find('td').eq(4).text(log.completed_at ? log.completed_at.substring(0, 16).replace('T', ' ') : '—');

                if (log.status === 'done' && log.failure_count > 0) {
                    var $btn = $('<button>').addClass('btn btn-xs btn-outline-warning btn-lihat-error-kohort')
                        .attr('data-id', log.id).text('Lihat Error').data('failures', log.failures || []);
                    $row.find('td').eq(5).empty().append($btn);
                } else if (log.status === 'failed') {
                    var $btn = $('<button>').addClass('btn btn-xs btn-outline-danger btn-lihat-error-kohort')
                        .attr('data-id', log.id).text('Lihat Detail').data('failures', log.failures || []);
                    $row.find('td').eq(5).empty().append($btn);
                }

                if (log.status === 'done' && oldStatus !== 'done') {
                    var msg = log.success_count + ' anak berhasil diimpor';
                    if (log.failure_count > 0) msg += ', ' + log.failure_count + ' baris dilewati';
                    toastr && toastr.success(msg + '.', 'Import Kohort Selesai');
                } else if (log.status === 'failed' && oldStatus !== 'failed') {
                    toastr && toastr.error('Import kohort gagal. Lihat detail untuk informasi lebih lanjut.', 'Import Gagal');
                }

                if (log.status === 'pending' || log.status === 'processing') stillActive = true;
            });

            $('#kohort-import-last-refresh').text('Update: ' + new Date().toLocaleTimeString('id-ID'));

            if (!stillActive && pollTimer) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
        });
    }

    $(document).on('click', '.btn-lihat-error-kohort', function() {
        var failures = $(this).data('failures');
        if (typeof failures === 'string') { try { failures = JSON.parse(failures); } catch(e) { failures = [failures]; } }
        failures = failures || [];

        var $list = $('#kohort-error-list').empty();
        if (failures.length === 0) {
            $list.append($('<li class="text-muted">').text('Tidak ada detail error tersedia.'));
        } else {
            failures.forEach(function(f) {
                $list.append($('<li class="mb-1 lh-sm">').text(f));
            });
        }
        $('#kohort-error-count').text('Ditemukan ' + failures.length + ' baris bermasalah.');
        $('#modalKohortErrorDetail').modal('show');
    });

    if (hasActive) {
        pollTimer = setInterval(refreshKohortStatus, 5000);
        refreshKohortStatus();
    }
})();
@endif
</script>
@endsection

{{-- Form filter Export Kesmas (spec docs/superpowers/specs/2026-09-15-data-kesmas-design.md §5).
     Cascade wilayah memakai endpoint AJAX yang sama dengan Export Anak; placeholder value=""
     supaya rule nullable|exists lolos tanpa filter. --}}
@extends('admin::layouts.app')
@section('title') Admin @endsection
@section('title-content') Export Data @endsection
@section('item') Export @endsection
@section('item-active') Kesmas @endsection
@section('content')
<div class="card">
    <div class="card-header">
        <h1 class="h5 mb-0">Export Kesmas</h1>
        <p class="text-muted mb-0 small">Excel dua sheet: <strong>Per Anak</strong> (identitas, data Kesmas, riwayat kelahiran &amp; skrining neonatal) dan <strong>Per Kunjungan</strong> (layanan Kesmas tiap pengukuran). Kosongkan filter untuk semua data.</p>
    </div>
    <div class="card-body">
        @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif
        <form method="get" action="{{ route('admin.export.kesmas.download') }}">
            <div class="row">
                <div class="col-md-3 col-sm-12">
                    <div class="form-group">
                        <label for="kec">Kecamatan</label>
                        <select id="kec" name="id_kec" class="form-control">
                            <option value="">Semua kecamatan</option>
                            @foreach ($kec as $k)
                            <option value="{{ $k->id }}" @selected((string) old('id_kec') === (string) $k->id)>{{ $k->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3 col-sm-12">
                    <div class="form-group">
                        <label for="kel">Kelurahan</label>
                        <select id="kel" name="id_kel" class="form-control">
                            <option value="">Semua kelurahan</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3 col-sm-12">
                    <div class="form-group">
                        <label for="puskesmas">Puskesmas</label>
                        <select id="puskesmas" name="id_puskesmas" class="form-control">
                            <option value="">Semua puskesmas</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3 col-sm-12">
                    <div class="form-group">
                        <label for="posyandu">Posyandu</label>
                        <select id="posyandu" name="id_posyandu" class="form-control">
                            <option value="">Semua posyandu</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3 col-sm-12">
                    <div class="form-group">
                        <label for="dari">Kunjungan dari tanggal</label>
                        <input type="date" name="dari" id="dari" class="form-control" value="{{ old('dari') }}">
                        <small class="form-text text-muted">Hanya menyaring sheet Per Kunjungan.</small>
                    </div>
                </div>
                <div class="col-md-3 col-sm-12">
                    <div class="form-group">
                        <label for="sampai">Sampai tanggal</label>
                        <input type="date" name="sampai" id="sampai" class="form-control" value="{{ old('sampai') }}">
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <span aria-hidden="true" class="fa fa-download mr-1"></span> Unduh Excel
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
@section('custom_scripts')
<script type="text/javascript">
    $(function() {
        $('#kec').on('change', function() {
            var id = $(this).val();
            $('#kel').html('<option value="">Semua kelurahan</option>');
            $('#puskesmas').html('<option value="">Semua puskesmas</option>');
            $('#posyandu').html('<option value="">Semua posyandu</option>');
            if (!id) return;

            $.ajax({
                url: '{{ url("admin/get-kel-dasar-anak") }}' + '/' + id,
                success: function(response) {
                    $.each(response, function(id, name) {
                        $('#kel').append(new Option(name, id));
                    });
                }
            });
            $.ajax({
                url: '{{ url("admin/get-puskesmas-dasar-anak") }}' + '/' + id,
                success: function(response) {
                    $.each(response, function(id, name) {
                        $('#puskesmas').append(new Option(name, id));
                    });
                }
            });
        });

        $('#puskesmas').on('change', function() {
            var id = $(this).val();
            $('#posyandu').html('<option value="">Semua posyandu</option>');
            if (!id) return;

            $.ajax({
                url: '{{ url("admin/get-posyandu-dasar-anak") }}' + '/' + id,
                success: function(response) {
                    $.each(response, function(id, name) {
                        $('#posyandu').append(new Option(name, id));
                    });
                }
            });
        });
    });
</script>
@endsection

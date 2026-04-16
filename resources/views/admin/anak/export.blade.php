@extends('admin::layouts.app')
@section('title')
Admin
@endsection
@section('title-content')
Data
@endsection
@section('item')
Data
@endsection
@section('item-active')
Export Data Anak
@endsection
@section('content')
<form method="post" action="{{route('admin.formViewExport')}}">
    @csrf
    <div class="row">
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label>From Date <font color="red">*</font></label>
                <input type="date" name="from_date" class="form-control" required>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label>To Date <font color="red">*</font></label>
                <input type="date" name="to_date" class="form-control" required>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label>Kecamatan</label>
                <select id="kec" name="id_kec" class="form-control">
                    <option value="">== Select Kecamatan ==</option>
                    @foreach ($kec as $id => $data)
                    <option value="{{$data->id}}">{{$data->name}}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label>Puskesmas</label>
                <select id="puskesmas" name="id_puskesmas" class="form-control">
                    <option value="">== Select Puskesmas ==</option>
                </select>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label>Kelurahan</label>
                <select id="kel" name="id_kelurahan" class="form-control">
                    <option value="">== Select Kelurahan ==</option>
                </select>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label>Posyandu</label>
                <select id="posyandu" name="id_posyandu" class="form-control">
                    <option value="">== Select Posyandu ==</option>
                </select>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label>RT</label>
                <select id="rt" name="id_rt" class="form-control">
                    <option value="">== Select RT ==</option>
                </select>
            </div>
        </div>
        <div class="col-md-12 col-sm-12">
            <button type="submit" class="btn btn-warning">Export</button>
            <a href="{{route('admin.exportAllExcel')}}"  class="btn btn-success">Export Data All</a>
        </div>
    </div>
</form>

@endsection
@section('custom_scripts')
<script type="text/javascript">
    $(function() {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        $('#kec').on('change', function() {
            var id = $(this).val();

            // Reset downstream dropdowns
            $('#kel').html('<option value="">== Select Kelurahan ==</option>');
            $('#puskesmas').html('<option value="">== Select Puskesmas ==</option>');
            $('#posyandu').html('<option value="">== Select Posyandu ==</option>');
            $('#rt').html('<option value="">== Select RT ==</option>');

            if (!id) return;

            $.ajax({
                url: '{{url("admin/get-kel-dasar-anak")}}' + '/' + id,
                success: function(response) {
                    $.each(response, function(id, name) {
                        $('#kel').append(new Option(name, id));
                    });
                }
            });

            $.ajax({
                url: '{{url("admin/get-puskesmas-dasar-anak")}}' + '/' + id,
                success: function(response) {
                    $.each(response, function(id, name) {
                        $('#puskesmas').append(new Option(name, id));
                    });
                }
            });
        });

        $('#kel').on('change', function() {
            var id = $(this).val();
            $('#rt').html('<option value="">== Select RT ==</option>');
            if (!id) return;

            $.ajax({
                url: '{{url("admin/get-rt-by-kel-anak")}}' + '/' + id,
                success: function(response) {
                    $.each(response, function(id, name) {
                        $('#rt').append(new Option(name, id));
                    });
                }
            });
        });

        $('#puskesmas').on('change', function() {
            var id = $(this).val();
            $('#posyandu').html('<option value="">== Select Posyandu ==</option>');
            if (!id) return;

            $.ajax({
                url: '{{url("admin/get-posyandu-dasar-anak")}}' + '/' + id,
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

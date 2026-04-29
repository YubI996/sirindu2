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
Anak
@endsection
@section('content')
@if ($errors->any())
<div class="alert alert-danger">
    <ul>
        @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif
<form method="post" action="{{route('admin.storeAnak')}}">
    @csrf
    <div class="row">
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="no_kk">No KK <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" name="no_kk" id="no_kk" class="form-control" pattern="[0-9]+" maxlength="16" inputmode="numeric" required>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="nik">NIK <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" name="nik" id="nik" class="form-control" pattern="[0-9]+" maxlength="16" inputmode="numeric" required>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="nama">Nama <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" name="nama" id="nama" class="form-control" required>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="nik_ortu">NIK Orang Tua <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" name="nik_ortu" id="nik_ortu" class="form-control" pattern="[0-9]+" maxlength="16" inputmode="numeric" required>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="nama_ibu">Nama Ibu <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" name="nama_ibu" id="nama_ibu" class="form-control" required>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="nama_ayah">Nama Ayah <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" name="nama_ayah" id="nama_ayah" class="form-control" required>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="jk">Jenis Kelamin <span class="text-danger" aria-hidden="true">*</span></label>
                <select name="jk" id="jk" class="form-control" required>
                    <option value="1">Laki-Laki</option>
                    <option value="2">Perempuan</option>
                </select>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="tempat_lahir">Tempat Lahir <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" name="tempat_lahir" id="tempat_lahir" class="form-control" required>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="tgl_lahir">Tanggal Lahir <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="date" name="tgl_lahir" id="tgl_lahir" class="form-control" required>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="golda">Golongan Darah <span class="text-danger" aria-hidden="true">*</span></label>
                <select name="golda" id="golda" class="form-control" required>
                    <option value="A">A</option>
                    <option value="B">B</option>
                    <option value="AB">AB</option>
                    <option value="O">O</option>
                </select>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="anak_ke">Anak Ke - <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="number" name="anak" id="anak_ke" class="form-control" required>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="no_hp">No HP</label>
                <input type="number" name="no" id="no_hp" class="form-control">
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="kec">Kecamatan <span class="text-danger" aria-hidden="true">*</span></label>
                <select id="kec" name="id_kec" class="form-control" required>
                    <option value="">== Pilih Kecamatan ==</option>
                    @foreach ($kec as $id => $data)
                    <option value="{{$data->id}}">{{$data->name}}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="puskesmas">Puskesmas <span class="text-danger" aria-hidden="true">*</span></label>
                <select id="puskesmas" name="id_puskesmas" class="form-control" required>
                    <option value="">== Pilih Puskesmas ==</option>
                </select>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="kel">Kelurahan <span class="text-danger" aria-hidden="true">*</span></label>
                <select id="kel" name="id_kel" class="form-control" required>
                    <option value="">== Pilih Kelurahan ==</option>
                </select>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="posyandu">Posyandu <span class="text-danger" aria-hidden="true">*</span></label>
                <select id="posyandu" name="id_posyandu" class="form-control" required>
                    <option value="">== Pilih Posyandu ==</option>
                </select>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="rt">RT <span class="text-danger" aria-hidden="true">*</span></label>
                <select id="rt" name="id_rt" class="form-control" required>
                    <option value="">== Pilih RT ==</option>
                </select>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="tb">Tinggi Badan Lahir <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" name="tb" id="tb" class="form-control" required>
                <small class="form-text text-muted">Gunakan titik (.) untuk angka desimal.</small>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="bb">Berat Badan Lahir <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" name="bb" id="bb" class="form-control" required>
                <small class="form-text text-muted">Gunakan titik (.) untuk angka desimal.</small>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="lla">Lingkar Lengan Atas <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" name="lla" id="lla" class="form-control" required>
                <small class="form-text text-muted">Gunakan titik (.) untuk angka desimal.</small>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="lk">Lingkar Kepala <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" name="lk" id="lk" class="form-control" required>
                <small class="form-text text-muted">Gunakan titik (.) untuk angka desimal.</small>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="asi">ASI Eksklusif <span class="text-danger" aria-hidden="true">*</span></label>
                <select name="asi" id="asi" class="form-control" required>
                    <option value="0">Tidak</option>
                    <option value="1">Ya</option>
                </select>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="obat_cacing">Obat Cacing <span class="text-danger" aria-hidden="true">*</span></label>
                <select name="obat_cacing" id="obat_cacing" class="form-control" required>
                    <option value="0">Tidak</option>
                    <option value="1">Ya</option>
                </select>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="tgl_kunjungan">Tanggal Kunjungan <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="date" name="tgl_kunjungan" id="tgl_kunjungan" class="form-control" required>
            </div>
        </div>
        <div class="col-md-12 col-sm-12">
            <div class="form-group">
                <label for="catatan">Catatan</label>
                <textarea class="form-control" name="catatan" id="catatan" cols="30" rows="10"></textarea>
            </div>
        </div>
        <div class="col-md-12 col-sm-12">
            <button type="submit" class="btn btn-primary">Simpan</button>
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
            $.ajax({
                    url: '{{url("admin/get-kel-dasar-anak")}}' + '/' + id,
                    success: function(response) {
                        $('#kel').empty();
                        $('#kel').append(new Option('====== Kelurahan ======', 0));
                        $.each(response, function(id, name) {
                            $('#kel').append(new Option(name, id))
                        })
                    }
                }),
                $.ajax({
                    url: '{{url("admin/get-puskesmas-dasar-anak")}}' + '/' + id,
                    success: function(response) {
                        $('#puskesmas').empty();
                        $('#puskesmas').append(new Option('====== Puskesmas ======', 0));
                        $.each(response, function(id, name) {
                            $('#puskesmas').append(new Option(name, id))
                        })
                    }
                })
        });

        $('#puskesmas').on('change', function() {
            var id = $(this).val();
            $.ajax({
                url: '{{url("admin/get-posyandu-dasar-anak")}}' + '/' + id,
                success: function(response) {
                    $('#posyandu').empty();
                    $('#posyandu').append(new Option('====== Posyandu ======', 0));
                    $.each(response, function(id, name) {
                        $('#posyandu').append(new Option(name, id))
                    })
                }
            })
        });

        $('#posyandu').on('change', function() {
            var id = $(this).val();
            $.ajax({
                url: '{{url("admin/get-rt-dasar-anak")}}' + '/' + id,
                success: function(response) {
                    $('#rt').empty();
                    $('#rt').append(new Option('====== RT ======', 0));
                    $.each(response, function(id, name) {
                        $('#rt').append(new Option(name, id))
                    })
                }
            })
        });
    });
</script>
@endsection

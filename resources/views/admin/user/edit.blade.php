@extends('admin::layouts.app')
@section('title')
Admin — SIRINDU
@endsection
@section('title-content')
User
@endsection
@section('item')
User
@endsection
@section('item-active')
Edit User
@endsection
@section('content')
@if($errors->any())
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    @foreach ($errors->all() as $error)
    <ul>
        <li><strong>{{ $error }}</strong></li>
    </ul>
    @endforeach
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
</div>
@endif
<form method="post" action="{{route('super.admin.updateUser', $user->id)}}">
    @csrf
    <input type="hidden" name="_method" value="PUT">
    <div class="row">

        {{-- Nama & Email --}}
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label>Nama</label>
                <input name="name" value="{{$user->name}}" class="form-control" type="text" placeholder="Nama">
                @error('name') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label>Email</label>
                <input name="email" value="{{$user->email}}" class="form-control" type="email" placeholder="Email">
                @error('email') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
        </div>

        {{-- Role --}}
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label>Role</label>
                <select class="form-control" name="role" id="edit_role">
                    <option value="superadmin"           {{$user->role === 'superadmin'            ? 'selected' : ''}}>Super Admin (Dinkes)</option>
                    <option value="imunisasi_faskes"     {{$user->role === 'imunisasi_faskes'      ? 'selected' : ''}}>Faskes Imunisasi</option>
                    <option value="surveilans_puskesmas" {{$user->role === 'surveilans_puskesmas'  ? 'selected' : ''}}>Surveilans PD3I – Puskesmas</option>
                    <option value="surveilans_rs"        {{$user->role === 'surveilans_rs'         ? 'selected' : ''}}>Surveilans PD3I – Rumah Sakit</option>
                </select>
                @error('role') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
        </div>

        {{-- Faskes Type (hanya untuk imunisasi_faskes) --}}
        <div class="col-md-4 col-sm-12 {{$user->role !== 'imunisasi_faskes' ? 'd-none' : ''}}" id="edit_faskes_type_group">
            <div class="form-group">
                <label>Tipe Faskes</label>
                <select class="form-control" name="faskes_type" id="edit_faskes_type">
                    <option value="">== Pilih ==</option>
                    <option value="puskesmas" {{$user->faskes_type === 'puskesmas' ? 'selected' : ''}}>Puskesmas</option>
                    <option value="rs"        {{$user->faskes_type === 'rs'        ? 'selected' : ''}}>Rumah Sakit</option>
                </select>
                @error('faskes_type') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
        </div>

        {{-- Puskesmas --}}
        @php
            $showPuskesmas = in_array($user->role, ['surveilans_puskesmas']) ||
                             ($user->role === 'imunisasi_faskes' && $user->faskes_type === 'puskesmas');
        @endphp
        <div class="col-md-4 col-sm-12 {{!$showPuskesmas ? 'd-none' : ''}}" id="edit_puskesmas_group">
            <div class="form-group">
                <label>Puskesmas</label>
                <select class="form-control" name="id_puskesmas">
                    <option value="">== Pilih Puskesmas ==</option>
                    @foreach($puskesmas as $p)
                    <option value="{{$p->id}}" {{$user->id_puskesmas == $p->id ? 'selected' : ''}}>{{$p->name}}</option>
                    @endforeach
                </select>
                @error('id_puskesmas') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
        </div>

        {{-- Rumah Sakit --}}
        @php
            $showRS = $user->role === 'surveilans_rs' ||
                      ($user->role === 'imunisasi_faskes' && $user->faskes_type === 'rs');
        @endphp
        <div class="col-md-4 col-sm-12 {{!$showRS ? 'd-none' : ''}}" id="edit_rs_group">
            <div class="form-group">
                <label>Rumah Sakit</label>
                <select class="form-control" name="id_rs">
                    <option value="">== Pilih Rumah Sakit ==</option>
                    @foreach($rs as $r)
                    <option value="{{$r->id}}" {{$user->id_rs == $r->id ? 'selected' : ''}}>{{$r->name}}</option>
                    @endforeach
                </select>
                @error('id_rs') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
        </div>

        {{-- Password --}}
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <input type="checkbox" id="gantiPassword" class="i-checks" onclick="passwordChange()"> Ganti Password<br>
                <label>Password Baru</label>
                <input id="pass" name="password" class="form-control" type="password" disabled placeholder="***********">
                @error('password') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
        </div>

        {{-- Ganti Lokasi --}}
        <div class="col-md-12">
            <hr>
            <input type="checkbox" id="gantiLokasi" class="i-checks" onclick="lokasiToggle()"> Ganti Lokasi Alamat
        </div>
        <div class="col-md-3 col-sm-12 mt-2">
            <div class="form-group">
                <label>Kecamatan</label>
                <select id="kecx" name="id_kecx" class="form-control" disabled>
                    <option value="">== Pilih Kecamatan ==</option>
                    @foreach ($kec as $data)
                    <option value="{{$data->id}}">{{$data->name}}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="col-md-3 col-sm-12 mt-2">
            <div class="form-group">
                <label>Kelurahan</label>
                <select id="kelx" name="id_kelx" class="form-control" disabled>
                    <option value="">== Pilih Kelurahan ==</option>
                </select>
            </div>
        </div>
        <div class="col-md-3 col-sm-12 mt-2">
            <div class="form-group">
                <label>Puskesmas (Lokasi)</label>
                <select id="puskesmasx" name="id_puskesmasx" class="form-control" disabled>
                    <option value="">== Pilih Puskesmas ==</option>
                </select>
            </div>
        </div>
        <div class="col-md-3 col-sm-12 mt-2">
            <div class="form-group">
                <label>Posyandu</label>
                <select id="posyandux" name="id_posyandux" class="form-control" disabled>
                    <option value="">== Pilih Posyandu ==</option>
                </select>
            </div>
        </div>

        <div class="col-md-12 mt-3">
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            <a href="{{ route('super.admin.user') }}" class="btn btn-secondary ml-2">Batal</a>
        </div>
    </div>
</form>
@endsection
@section('custom_scripts')
<script>
    function passwordChange() {
        var cb = document.getElementById('gantiPassword');
        var pass = document.getElementById('pass');
        pass.disabled = !cb.checked;
        if (cb.checked) pass.focus();
    }

    function lokasiToggle() {
        var cb = document.getElementById('gantiLokasi');
        ['kecx', 'kelx', 'puskesmasx', 'posyandux'].forEach(function (id) {
            document.getElementById(id).disabled = !cb.checked;
        });
        if (cb.checked) document.getElementById('kecx').focus();
    }

    function updateEditFaskesVisibility() {
        var role = document.getElementById('edit_role').value;
        var faskesType = document.getElementById('edit_faskes_type').value;

        document.getElementById('edit_faskes_type_group').classList.add('d-none');
        document.getElementById('edit_puskesmas_group').classList.add('d-none');
        document.getElementById('edit_rs_group').classList.add('d-none');

        if (role === 'surveilans_puskesmas') {
            document.getElementById('edit_puskesmas_group').classList.remove('d-none');
        } else if (role === 'surveilans_rs') {
            document.getElementById('edit_rs_group').classList.remove('d-none');
        } else if (role === 'imunisasi_faskes') {
            document.getElementById('edit_faskes_type_group').classList.remove('d-none');
            if (faskesType === 'puskesmas') {
                document.getElementById('edit_puskesmas_group').classList.remove('d-none');
            } else if (faskesType === 'rs') {
                document.getElementById('edit_rs_group').classList.remove('d-none');
            }
        }
    }

    document.getElementById('edit_role').addEventListener('change', updateEditFaskesVisibility);
    document.getElementById('edit_faskes_type').addEventListener('change', updateEditFaskesVisibility);

    $(function () {
        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

        $('#kecx').on('change', function () {
            var id = $(this).val();
            $.ajax({
                url: '{{ url("admin/get-kel-dasar-anak") }}' + '/' + id,
                success: function (response) {
                    $('#kelx').empty().append('<option value="">== Pilih Kelurahan ==</option>');
                    $.each(response, function (id, name) { $('#kelx').append(new Option(name, id)); });
                }
            });
            $.ajax({
                url: '{{ url("admin/get-puskesmas-dasar-anak") }}' + '/' + id,
                success: function (response) {
                    $('#puskesmasx').empty().append('<option value="">== Pilih Puskesmas ==</option>');
                    $.each(response, function (id, name) { $('#puskesmasx').append(new Option(name, id)); });
                }
            });
        });

        $('#puskesmasx').on('change', function () {
            var id = $(this).val();
            $.ajax({
                url: '{{ url("admin/get-posyandu-dasar-anak") }}' + '/' + id,
                success: function (response) {
                    $('#posyandux').empty().append('<option value="">== Pilih Posyandu ==</option>');
                    $.each(response, function (id, name) { $('#posyandux').append(new Option(name, id)); });
                }
            });
        });
    });
</script>
@endsection

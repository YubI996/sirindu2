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
Edit Anak
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
<form method="post" action="{{route('admin.updateAnak',$anak->hashid)}}">
    @csrf
    <input type="hidden" name="_method" value="PUT">
    <div class="row">
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="no_kk">No KK <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" name="no_kk" id="no_kk" value="{{$anak->no_kk}}" class="form-control" pattern="[0-9]+" maxlength="16" inputmode="numeric" required>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="nik">NIK <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" name="nik" id="nik" value="{{$anak->nik}}" class="form-control" pattern="[0-9]+" maxlength="16" inputmode="numeric" required>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="nama">Nama <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" name="nama" id="nama" value="{{$anak->nama}}" class="form-control" required>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="nik_ortu">NIK Orang Tua <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" name="nik_ortu" id="nik_ortu" value="{{$anak->nik_ortu}}" class="form-control" pattern="[0-9]+" maxlength="16" inputmode="numeric" required>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="nama_ibu">Nama Ibu <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" name="nama_ibu" id="nama_ibu" value="{{$anak->nama_ibu}}" class="form-control" required>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="nama_ayah">Nama Ayah <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" name="nama_ayah" id="nama_ayah" value="{{$anak->nama_ayah}}" class="form-control" required>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="jk">Jenis Kelamin <span class="text-danger" aria-hidden="true">*</span></label>
                <select name="jk" id="jk" class="form-control" required>
                    <option value="1" @if ($anak->jk == 1) selected @endif>Laki-Laki</option>
                    <option value="2" @if ($anak->jk == 2) selected @endif>Perempuan</option>
                </select>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="tempat_lahir">Tempat Lahir <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" name="tempat_lahir" id="tempat_lahir" value="{{$anak->tempat_lahir}}" class="form-control" required>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="tgl_lahir">Tanggal Lahir <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="date" name="tgl_lahir" id="tgl_lahir" value="{{$anak->tgl_lahir}}" class="form-control" required>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="golda">Golongan Darah <span class="text-danger" aria-hidden="true">*</span></label>
                <select name="golda" id="golda" class="form-control" required>
                    <option value="A" @if ($anak->golda == 'A') selected @endif>A</option>
                    <option value="B" @if ($anak->golda == 'B') selected @endif>B</option>
                    <option value="AB" @if ($anak->golda == 'AB') selected @endif>AB</option>
                    <option value="O" @if ($anak->golda == 'O') selected @endif>O</option>
                </select>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="anak_ke">Anak Ke - <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="number" name="anak" id="anak_ke" value="{{$anak->anak}}" class="form-control" required>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="no_hp">No HP</label>
                <input type="number" name="no" id="no_hp" value="{{$anak->no}}" class="form-control">
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <input type="checkbox" id="gantiLokasi" name="centang" class="i-checks" onclick="lokasi()"> Ganti Lokasi Alamat <br>
            <div class="form-group">
                <label for="kec">Kecamatan <span class="text-danger" aria-hidden="true">*</span></label>
                <select id="kec" name="id_kec" class="form-control" disabled="true" required>
                    <option value="">== Select Kecamatan ==</option>
                    @foreach ($kec as $id => $data)
                    <option value="{{$data->id}}">{{$data->name}}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="puskesmas">Puskesmas <span class="text-danger" aria-hidden="true">*</span></label>
                <select id="puskesmas" name="id_puskesmas" class="form-control" disabled="true" required>
                    <option value="">== Pilih Puskesmas ==</option>
                </select>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="kel">Kelurahan <span class="text-danger" aria-hidden="true">*</span></label>
                <select id="kel" name="id_kel" class="form-control" disabled="true" required>
                    <option value="">== Pilih Kelurahan ==</option>
                </select>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="posyandu">Posyandu <span class="text-danger" aria-hidden="true">*</span></label>
                <select id="posyandu" name="id_posyandu" class="form-control" disabled="true" required>
                    <option value="">== Pilih Posyandu ==</option>
                </select>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="rt">RT <span class="text-danger" aria-hidden="true">*</span></label>
                <select id="rt" name="id_rt" class="form-control" disabled="true" required>
                    <option value="">== Pilih RT ==</option>
                </select>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="tb">Tinggi Badan Lahir <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" name="tb" id="tb" value="{{$dt->tb}}" class="form-control" required>
                <small class="form-text text-muted">Gunakan titik (.) untuk angka desimal.</small>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="bb">Berat Badan Lahir <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" name="bb" id="bb" value="{{$dt->bb}}" class="form-control" required>
                <small class="form-text text-muted">Gunakan titik (.) untuk angka desimal.</small>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="lla">Lingkar Lengan Atas <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" name="lla" id="lla" value="{{$dt->lla}}" class="form-control" required>
                <small class="form-text text-muted">Gunakan titik (.) untuk angka desimal.</small>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="lk">Lingkar Kepala <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" name="lk" id="lk" value="{{$dt->lk}}" class="form-control" required>
                <small class="form-text text-muted">Gunakan titik (.) untuk angka desimal.</small>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="asi">ASI Eksklusif <span class="text-danger" aria-hidden="true">*</span></label>
                <select name="asi" id="asi" class="form-control" required>
                    <option value="0" @if($dt->asi == '0') selected @endif>Tidak</option>
                    <option value="1" @if($dt->asi == '1') selected @endif>Ya</option>
                </select>
            </div>
        </div>

        {{-- ===== Data Operasi Timbang ===== --}}
        <div class="col-12">
            <hr>
            <h6 class="text-muted mb-3">Data Operasi Timbang</h6>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="posisi">Cara Ukur (Posisi)</label>
                <select name="posisi" id="posisi" class="form-control">
                    <option value="L" @if($dt->posisi == 'L') selected @endif>Berdiri (L)</option>
                    <option value="H" @if($dt->posisi == 'H') selected @endif>Telentang (H)</option>
                </select>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="vit_a">Vitamin A</label>
                <select name="vit_a" id="vit_a" class="form-control">
                    <option value="0" @if($dt->vit_a == '0') selected @endif>Tidak</option>
                    <option value="1" @if($dt->vit_a == '1') selected @endif>Ya</option>
                </select>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="pitting_edema">Pitting Edema</label>
                <select name="pitting_edema" id="pitting_edema" class="form-control">
                    <option value="0" @if($dt->pitting_edema === 0 || $dt->pitting_edema === '0') selected @endif>Tidak ada (0)</option>
                    <option value="1" @if($dt->pitting_edema == '1') selected @endif>Derajat 1 (+)</option>
                    <option value="2" @if($dt->pitting_edema == '2') selected @endif>Derajat 2 (++)</option>
                    <option value="3" @if($dt->pitting_edema == '3') selected @endif>Derajat 3 (+++)</option>
                </select>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="kelas_ibu_balita">Kelas Ibu Balita</label>
                <select name="kelas_ibu_balita" id="kelas_ibu_balita" class="form-control">
                    <option value="0" @if(!$dt->kelas_ibu_balita) selected @endif>Tidak</option>
                    <option value="1" @if($dt->kelas_ibu_balita) selected @endif>Ya</option>
                </select>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="mbg">Makan Bergizi Gratis (MBG)</label>
                <select name="mbg" id="mbg" class="form-control">
                    <option value="0" @if(!$dt->mbg) selected @endif>Tidak</option>
                    <option value="1" @if($dt->mbg) selected @endif>Ya</option>
                </select>
            </div>
        </div>
        <div class="col-12">
            <div class="form-group">
                <label class="d-block">Pemberian ASI per Bulan (0–6)</label>
                @for ($b = 0; $b <= 6; $b++)
                <div class="form-check form-check-inline">
                    <input type="hidden" name="asi_bulan_{{ $b }}" value="0">
                    <input class="form-check-input" type="checkbox" id="asi_bulan_{{ $b }}" name="asi_bulan_{{ $b }}" value="1" @if($dt->{'asi_bulan_'.$b}) checked @endif>
                    <label class="form-check-label" for="asi_bulan_{{ $b }}">Bulan {{ $b }}</label>
                </div>
                @endfor
            </div>
        </div>
        {{-- ===== /Data Operasi Timbang ===== --}}

        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="status">Status <span class="text-danger" aria-hidden="true">*</span></label>
                <select name="status" id="status" class="form-control" required>
                    <option value="0" @if($anak->status == '0') selected @endif>Tidak Aktif</option>
                    <option value="1" @if($anak->status == '1') selected @endif>Aktif</option>
                </select>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="form-group">
                <label for="tgl_kunjungan">Tanggal Kunjungan <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="date" name="tgl_kunjungan" id="tgl_kunjungan" value="{{$dt->tgl_kunjungan}}" class="form-control" required>
            </div>
        </div>
        <div class="col-md-12 col-sm-12">
            <div class="form-group">
                <label for="catatan">Catatan</label>
                <textarea class="form-control" name="catatan" id="catatan" cols="30" rows="10">{{$anak->catatan}}</textarea>
            </div>
        </div>
        <div class="col-md-12 col-sm-12">
            <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
    </div>
</form>
<div class="row">
    @foreach ($dataAnak as $data)
    <form method="post" action="{{route('admin.updateDataAnak',$data->id)}}">
        @csrf
        <input type="hidden" name="_method" value="PUT">
        <div class="col">
            <label>Umur {{$data->bln}} Bulan</label>
            <div class="form-group">
                <label>Tanggal Kunjungan</label>
                <input type="date" name="tgl_kunjungan" value="{{$data->tgl_kunjungan}}" class="form-control" required>
                <label>Posisi</label>
                <select name="posisi" class="form-control" required>
                    <option value="H" @if($data->posisi == 'H') selected @endif>H</option>
                    <option value="L" @if($data->posisi == 'L') selected @endif>L</option>
                </select>
                <label>Tinggi Badan <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" name="tb" value="{{$data->tb}}" class="form-control" required>
                <small class="form-text text-muted">Gunakan titik (.) untuk desimal.</small>
                <label>Berat Badan <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" name="bb" value="{{$data->bb}}" class="form-control" required>
                <small class="form-text text-muted">Gunakan titik (.) untuk desimal.</small>
                <label>Lingkar Lengan Atas <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" name="lla" value="{{$data->lla}}" class="form-control" required>
                <small class="form-text text-muted">Gunakan titik (.) untuk desimal.</small>
                <label>Lingkar Kepala <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" name="lk" value="{{$data->lk}}" class="form-control" required>
                <small class="form-text text-muted">Gunakan titik (.) untuk desimal.</small>
                <label>ASI Eksklusif</label>
                <select name="asi" class="form-control" required>
                    <option value="0" @if($data->asi == '0') selected @endif>Tidak</option>
                    <option value="1" @if($data->asi == '1') selected @endif>Ya</option>
                </select>
                <label>Vitamin A</label>
                <select name="vit_a" class="form-control" required>
                    <option value="0" @if($data->vit_a == '0') selected @endif>Tidak</option>
                    <option value="1" @if($data->vit_a == '1') selected @endif>Ya</option>
                </select>
                <label>Obat Cacing</label>
                <select name="obat_cacing" class="form-control" required>
                    <option value="0" @if($data->obat_cacing == '0') selected @endif>Tidak</option>
                    <option value="1" @if($data->obat_cacing == '1') selected @endif>Ya</option>
                </select>
                <label>DDTKA</label>
                <input type="text" class="form-control" name="ddtka" value="{{$data->ddtka}}">
            </div>
        </div>
        <div class="col-md-12 col-sm-12">
            <button type="submit" class="btn btn-primary">Submit</button>
        </div>
    </form>
    @endforeach
</div>
@endsection
@section('custom_scripts')
<script type="text/javascript">
    function lokasi() {
        var cb = document.getElementById('gantiLokasi');
        var kec = document.getElementById('kec');
        var kel = document.getElementById('kel');
        var posyandu = document.getElementById('posyandu');
        var puskesmas = document.getElementById('puskesmas');
        var rt = document.getElementById('rt');
        if (cb.checked) {
            kec.disabled = false;
            kec.focus();
            posyandu.disabled = false;
            posyandu.focus();
            puskesmas.disabled = false;
            puskesmas.focus();
            kel.disabled = false;
            kel.focus();
            rt.disabled = false;
            rt.focus();
        } else {
            kec.disabled = true;
            puskesmas.disabled = true;

            posyandu.disabled = true;

            kel.disabled = true;

            rt.disabled = true;

        }
    }

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
                        $('#kel').append(new Option('====== Kelurahan ======',0));
                        $.each(response, function(id, name) {
                            $('#kel').append(new Option(name, id))
                        })
                    }
                }),
                $.ajax({
                    url: '{{url("admin/get-puskesmas-dasar-anak")}}' + '/' + id,
                    success: function(response) {
                        $('#puskesmas').empty();
                        $('#puskesmas').append(new Option('====== Puskesmas ======',0));
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
                    $('#posyandu').append(new Option('====== Posyandu ======',0));
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
                    $('#rt').append(new Option('====== RT ======',0));

                    $.each(response, function(id, name) {
                        $('#rt').append(new Option(name, id))
                    })
                }
            })
        });
    });
</script>
@endsection
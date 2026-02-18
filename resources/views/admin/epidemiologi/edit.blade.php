@extends('admin::layouts.app')
@section('title') Edit Kasus Epidemiologi - Si Rindu @endsection
@section('title-content') Epidemiologi @endsection
@section('item') Epidemiologi @endsection
@section('item-active') Edit Kasus @endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="{{ route('admin.epidemiologi.show', $case->id) }}" class="btn btn-secondary btn-sm">
        <i class="fa fa-arrow-left mr-1"></i>Kembali ke Detail
    </a>
    <div>
        <button type="button" id="btn-expand-all" class="btn btn-outline-primary btn-sm">Buka Semua</button>
        <button type="button" id="btn-collapse-all" class="btn btn-outline-secondary btn-sm">Tutup Semua</button>
    </div>
</div>

<div class="alert alert-info alert-dismissible fade show">
    <i class="fa fa-info-circle mr-2"></i>
    Mengedit kasus: <strong>{{ $case->no_registrasi }}</strong> — {{ $case->nama_lengkap }}
    <button type="button" class="close" data-dismiss="alert">&times;</button>
</div>

@if($errors->any())
<div class="alert alert-danger">
    <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<form action="{{ route('admin.epidemiologi.update', $case->id) }}" method="POST" id="form-epi">
@csrf
@method('PUT')

<div class="accordion" id="accordionEpi">

    <div class="card border-primary mb-2">
        <div class="card-header bg-primary text-white" id="headA">
            <h6 class="mb-0 d-flex justify-content-between align-items-center">
                <span><i class="fa fa-user mr-2"></i>A. Identitas Penderita</span>
                <button class="btn btn-sm btn-outline-light" type="button" data-toggle="collapse" data-target="#collapseA"><i class="fa fa-chevron-down"></i></button>
            </h6>
        </div>
        <div id="collapseA" class="collapse show">
            <div class="card-body">@include('admin.epidemiologi.components.form-section-a')</div>
        </div>
    </div>

    <div class="card border-secondary mb-2">
        <div class="card-header bg-secondary text-white">
            <h6 class="mb-0 d-flex justify-content-between align-items-center">
                <span><i class="fa fa-id-card mr-2"></i>B. Data Pelapor</span>
                <button class="btn btn-sm btn-outline-light" type="button" data-toggle="collapse" data-target="#collapseB"><i class="fa fa-chevron-down"></i></button>
            </h6>
        </div>
        <div id="collapseB" class="collapse">
            <div class="card-body">@include('admin.epidemiologi.components.form-section-b')</div>
        </div>
    </div>

    <div class="card border-info mb-2">
        <div class="card-header bg-info text-white">
            <h6 class="mb-0 d-flex justify-content-between align-items-center">
                <span><i class="fa fa-clipboard mr-2"></i>C. Data Kasus</span>
                <button class="btn btn-sm btn-outline-light" type="button" data-toggle="collapse" data-target="#collapseC"><i class="fa fa-chevron-down"></i></button>
            </h6>
        </div>
        <div id="collapseC" class="collapse show">
            <div class="card-body">@include('admin.epidemiologi.components.form-section-c')</div>
        </div>
    </div>

    <div class="card border-warning mb-2">
        <div class="card-header bg-warning">
            <h6 class="mb-0 d-flex justify-content-between align-items-center">
                <span><i class="fa fa-thermometer mr-2"></i>D. Gejala Klinis</span>
                <button class="btn btn-sm btn-outline-dark" type="button" data-toggle="collapse" data-target="#collapseD"><i class="fa fa-chevron-down"></i></button>
            </h6>
        </div>
        <div id="collapseD" class="collapse">
            <div class="card-body">@include('admin.epidemiologi.components.form-section-d')</div>
        </div>
    </div>

    <div class="card border-success mb-2">
        <div class="card-header bg-success text-white">
            <h6 class="mb-0 d-flex justify-content-between align-items-center">
                <span><i class="fa fa-history mr-2"></i>E. Riwayat</span>
                <button class="btn btn-sm btn-outline-light" type="button" data-toggle="collapse" data-target="#collapseE"><i class="fa fa-chevron-down"></i></button>
            </h6>
        </div>
        <div id="collapseE" class="collapse">
            <div class="card-body">@include('admin.epidemiologi.components.form-section-e')</div>
        </div>
    </div>

    <div class="card border-danger mb-2">
        <div class="card-header bg-danger text-white">
            <h6 class="mb-0 d-flex justify-content-between align-items-center">
                <span><i class="fa fa-flask mr-2"></i>F. Laboratorium</span>
                <button class="btn btn-sm btn-outline-light" type="button" data-toggle="collapse" data-target="#collapseF"><i class="fa fa-chevron-down"></i></button>
            </h6>
        </div>
        <div id="collapseF" class="collapse">
            <div class="card-body">@include('admin.epidemiologi.components.form-section-f')</div>
        </div>
    </div>

    <div class="card border-dark mb-2">
        <div class="card-header bg-dark text-white">
            <h6 class="mb-0 d-flex justify-content-between align-items-center">
                <span><i class="fa fa-hospital mr-2"></i>G. Tata Laksana</span>
                <button class="btn btn-sm btn-outline-light" type="button" data-toggle="collapse" data-target="#collapseG"><i class="fa fa-chevron-down"></i></button>
            </h6>
        </div>
        <div id="collapseG" class="collapse">
            <div class="card-body">@include('admin.epidemiologi.components.form-section-g')</div>
        </div>
    </div>

    <div class="card mb-2" style="border-color:#6f42c1;">
        <div class="card-header text-white" style="background:#6f42c1;">
            <h6 class="mb-0 d-flex justify-content-between align-items-center">
                <span><i class="fa fa-flag-checkered mr-2"></i>H. Status Akhir</span>
                <button class="btn btn-sm btn-outline-light" type="button" data-toggle="collapse" data-target="#collapseH"><i class="fa fa-chevron-down"></i></button>
            </h6>
        </div>
        <div id="collapseH" class="collapse show">
            <div class="card-body">@include('admin.epidemiologi.components.form-section-h')</div>
        </div>
    </div>

    <div class="card mb-2" style="border-color:#20c997;">
        <div class="card-header text-white" style="background:#20c997;">
            <h6 class="mb-0 d-flex justify-content-between align-items-center">
                <span><i class="fa fa-people-arrows mr-2"></i>I. Kontak Erat</span>
                <button class="btn btn-sm btn-outline-light" type="button" data-toggle="collapse" data-target="#collapseI"><i class="fa fa-chevron-down"></i></button>
            </h6>
        </div>
        <div id="collapseI" class="collapse">
            <div class="card-body">@include('admin.epidemiologi.components.form-section-i')</div>
        </div>
    </div>

    <div class="card border-primary mb-2">
        <div class="card-header text-white" style="background:#0d6efd;">
            <h6 class="mb-0 d-flex justify-content-between align-items-center">
                <span><i class="fa fa-tag mr-2"></i>J. Klasifikasi Kasus</span>
                <button class="btn btn-sm btn-outline-light" type="button" data-toggle="collapse" data-target="#collapseJ"><i class="fa fa-chevron-down"></i></button>
            </h6>
        </div>
        <div id="collapseJ" class="collapse show">
            <div class="card-body">@include('admin.epidemiologi.components.form-section-j')</div>
        </div>
    </div>
</div>

<div class="mt-4 mb-5 d-flex justify-content-between">
    <a href="{{ route('admin.epidemiologi.show', $case->id) }}" class="btn btn-secondary">Batal</a>
    <button type="submit" class="btn btn-warning btn-lg"><i class="fa fa-save mr-2"></i>Simpan Perubahan</button>
</div>
</form>

<div class="card mt-3 bg-light">
    <div class="card-body py-2">
        <small class="text-muted">
            Dibuat: {{ $case->createdBy->name ?? '-' }} pada {{ $case->created_at?->format('d/m/Y H:i') }} |
            Diperbarui: {{ $case->updatedBy->name ?? '-' }} pada {{ $case->updated_at?->format('d/m/Y H:i') }}
        </small>
    </div>
</div>
@endsection

@section('custom_scripts')
<script>
$('#btn-expand-all').on('click', function() { $('#accordionEpi .collapse').collapse('show'); });
$('#btn-collapse-all').on('click', function() { $('#accordionEpi .collapse').collapse('hide'); });

$('#id_kec').on('change', function() {
    var id = $(this).val();
    $('#id_kel').html('<option value="">Pilih Kelurahan</option>');
    $('#id_rt').html('<option value="">Pilih RT</option>');
    if (!id) return;
    $.get('/admin/epidemiologi/ajax/get-kelurahan/' + id, function(data) {
        data.forEach(function(k) {
            $('#id_kel').append('<option value="' + k.id + '">' + k.nama_kelurahan + '</option>');
        });
    });
});

$('#id_kel').on('change', function() {
    var id = $(this).val();
    $('#id_rt').html('<option value="">Pilih RT</option>');
    if (!id) return;
    $.get('/admin/epidemiologi/ajax/get-rt/' + id, function(data) {
        data.forEach(function(r) {
            $('#id_rt').append('<option value="' + r.id + '">RT ' + r.no_rt + '</option>');
        });
    });
});

$('#tanggal_lahir').on('change', function() {
    var dob = new Date($(this).val()); var today = new Date();
    var years = today.getFullYear() - dob.getFullYear();
    var m = today.getMonth() - dob.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) { years--; }
    $('#display-umur').text(years + ' tahun');
}).trigger('change');

$('#status_lab').on('change', function() {
    var v = $(this).val();
    if (v === 'positif' || v === 'negatif') { $('#row-tgl-hasil-lab').show(); } else { $('#row-tgl-hasil-lab').hide(); }
}).trigger('change');

$('#status_rawat').on('change', function() {
    if ($(this).val() === 'rawat_inap') { $('#row-rawat-tanggal').show(); } else { $('#row-rawat-tanggal').hide(); }
}).trigger('change');

$('#tanggal_masuk_rs, #tanggal_keluar_rs').on('change', function() {
    var masuk = $('#tanggal_masuk_rs').val(); var keluar = $('#tanggal_keluar_rs').val();
    if (masuk && keluar) { $('#display-lama-rawat').text(Math.round((new Date(keluar) - new Date(masuk)) / 86400000) + ' hari'); }
});

$('#kondisi_akhir').on('change', function() {
    if ($(this).val() === 'meninggal') { $('#row-penyebab-kematian').show(); } else { $('#row-penyebab-kematian').hide(); }
}).trigger('change');
</script>
@endsection

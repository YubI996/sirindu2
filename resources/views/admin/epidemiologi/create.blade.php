@extends('admin::layouts.app')
@section('title') Tambah Kasus Epidemiologi - Si Rindu @endsection
@section('title-content') Epidemiologi @endsection
@section('item') Epidemiologi @endsection
@section('item-active') Tambah Kasus @endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="{{ route('admin.epidemiologi.index') }}" class="btn btn-secondary btn-sm">
        <i class="fa fa-arrow-left mr-1"></i>Kembali
    </a>
    <div>
        <button type="button" id="btn-expand-all" class="btn btn-outline-primary btn-sm">Buka Semua</button>
        <button type="button" id="btn-collapse-all" class="btn btn-outline-secondary btn-sm">Tutup Semua</button>
    </div>
</div>

@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show">
    <strong>Terdapat kesalahan:</strong>
    <ul class="mb-0 mt-1">
        @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
    </ul>
    <button type="button" class="close" data-dismiss="alert">&times;</button>
</div>
@endif

<form action="{{ route('admin.epidemiologi.store') }}" method="POST" id="form-epi">
@csrf

<div class="accordion" id="accordionEpi">

    <!-- A: IDENTITAS PENDERITA -->
    <div class="card border-primary mb-2">
        <div class="card-header bg-primary text-white" id="headA">
            <h6 class="mb-0 d-flex justify-content-between align-items-center">
                <span><i class="fa fa-user mr-2"></i>A. Identitas Penderita</span>
                <button class="btn btn-sm btn-outline-light" type="button" data-toggle="collapse" data-target="#collapseA">
                    <i class="fa fa-chevron-down"></i>
                </button>
            </h6>
        </div>
        <div id="collapseA" class="collapse show" data-parent="#accordionEpi">
            <div class="card-body">
                @include('admin.epidemiologi.components.form-section-a')
            </div>
        </div>
    </div>

    <!-- B: PELAPOR -->
    <div class="card border-secondary mb-2">
        <div class="card-header bg-secondary text-white" id="headB">
            <h6 class="mb-0 d-flex justify-content-between align-items-center">
                <span><i class="fa fa-id-card mr-2"></i>B. Data Pelapor</span>
                <button class="btn btn-sm btn-outline-light" type="button" data-toggle="collapse" data-target="#collapseB">
                    <i class="fa fa-chevron-down"></i>
                </button>
            </h6>
        </div>
        <div id="collapseB" class="collapse" data-parent="#accordionEpi">
            <div class="card-body">
                @include('admin.epidemiologi.components.form-section-b')
            </div>
        </div>
    </div>

    <!-- C: DATA KASUS -->
    <div class="card border-info mb-2">
        <div class="card-header bg-info text-white" id="headC">
            <h6 class="mb-0 d-flex justify-content-between align-items-center">
                <span><i class="fa fa-clipboard mr-2"></i>C. Data Kasus</span>
                <button class="btn btn-sm btn-outline-light" type="button" data-toggle="collapse" data-target="#collapseC">
                    <i class="fa fa-chevron-down"></i>
                </button>
            </h6>
        </div>
        <div id="collapseC" class="collapse show" data-parent="#accordionEpi">
            <div class="card-body">
                @include('admin.epidemiologi.components.form-section-c')
            </div>
        </div>
    </div>

    <!-- D: GEJALA KLINIS -->
    <div class="card border-warning mb-2">
        <div class="card-header bg-warning" id="headD">
            <h6 class="mb-0 d-flex justify-content-between align-items-center">
                <span><i class="fa fa-thermometer mr-2"></i>D. Gejala Klinis</span>
                <button class="btn btn-sm btn-outline-dark" type="button" data-toggle="collapse" data-target="#collapseD">
                    <i class="fa fa-chevron-down"></i>
                </button>
            </h6>
        </div>
        <div id="collapseD" class="collapse" data-parent="#accordionEpi">
            <div class="card-body">
                @include('admin.epidemiologi.components.form-section-d')
            </div>
        </div>
    </div>

    <!-- E: RIWAYAT -->
    <div class="card border-success mb-2">
        <div class="card-header bg-success text-white" id="headE">
            <h6 class="mb-0 d-flex justify-content-between align-items-center">
                <span><i class="fa fa-history mr-2"></i>E. Riwayat</span>
                <button class="btn btn-sm btn-outline-light" type="button" data-toggle="collapse" data-target="#collapseE">
                    <i class="fa fa-chevron-down"></i>
                </button>
            </h6>
        </div>
        <div id="collapseE" class="collapse" data-parent="#accordionEpi">
            <div class="card-body">
                @include('admin.epidemiologi.components.form-section-e')
            </div>
        </div>
    </div>

    <!-- F: LABORATORIUM -->
    <div class="card border-danger mb-2">
        <div class="card-header bg-danger text-white" id="headF">
            <h6 class="mb-0 d-flex justify-content-between align-items-center">
                <span><i class="fa fa-flask mr-2"></i>F. Laboratorium</span>
                <button class="btn btn-sm btn-outline-light" type="button" data-toggle="collapse" data-target="#collapseF">
                    <i class="fa fa-chevron-down"></i>
                </button>
            </h6>
        </div>
        <div id="collapseF" class="collapse" data-parent="#accordionEpi">
            <div class="card-body">
                @include('admin.epidemiologi.components.form-section-f')
            </div>
        </div>
    </div>

    <!-- G: TATA LAKSANA -->
    <div class="card border-dark mb-2">
        <div class="card-header bg-dark text-white" id="headG">
            <h6 class="mb-0 d-flex justify-content-between align-items-center">
                <span><i class="fa fa-hospital mr-2"></i>G. Tata Laksana</span>
                <button class="btn btn-sm btn-outline-light" type="button" data-toggle="collapse" data-target="#collapseG">
                    <i class="fa fa-chevron-down"></i>
                </button>
            </h6>
        </div>
        <div id="collapseG" class="collapse" data-parent="#accordionEpi">
            <div class="card-body">
                @include('admin.epidemiologi.components.form-section-g')
            </div>
        </div>
    </div>

    <!-- H: STATUS AKHIR -->
    <div class="card mb-2" style="border-color:#6f42c1;">
        <div class="card-header text-white" style="background:#6f42c1;" id="headH">
            <h6 class="mb-0 d-flex justify-content-between align-items-center">
                <span><i class="fa fa-flag-checkered mr-2"></i>H. Status Akhir</span>
                <button class="btn btn-sm btn-outline-light" type="button" data-toggle="collapse" data-target="#collapseH">
                    <i class="fa fa-chevron-down"></i>
                </button>
            </h6>
        </div>
        <div id="collapseH" class="collapse show" data-parent="#accordionEpi">
            <div class="card-body">
                @include('admin.epidemiologi.components.form-section-h')
            </div>
        </div>
    </div>

    <!-- I: KONTAK -->
    <div class="card mb-2" style="border-color:#20c997;">
        <div class="card-header text-white" style="background:#20c997;" id="headI">
            <h6 class="mb-0 d-flex justify-content-between align-items-center">
                <span><i class="fa fa-people-arrows mr-2"></i>I. Kontak Erat</span>
                <button class="btn btn-sm btn-outline-light" type="button" data-toggle="collapse" data-target="#collapseI">
                    <i class="fa fa-chevron-down"></i>
                </button>
            </h6>
        </div>
        <div id="collapseI" class="collapse" data-parent="#accordionEpi">
            <div class="card-body">
                @include('admin.epidemiologi.components.form-section-i')
            </div>
        </div>
    </div>

    <!-- J: KLASIFIKASI KASUS -->
    <div class="card border-primary mb-2">
        <div class="card-header text-white" style="background:#0d6efd;" id="headJ">
            <h6 class="mb-0 d-flex justify-content-between align-items-center">
                <span><i class="fa fa-tag mr-2"></i>J. Klasifikasi Kasus</span>
                <button class="btn btn-sm btn-outline-light" type="button" data-toggle="collapse" data-target="#collapseJ">
                    <i class="fa fa-chevron-down"></i>
                </button>
            </h6>
        </div>
        <div id="collapseJ" class="collapse show" data-parent="#accordionEpi">
            <div class="card-body">
                @include('admin.epidemiologi.components.form-section-j')
            </div>
        </div>
    </div>

</div><!-- end accordion -->

<div class="mt-4 mb-5 d-flex justify-content-between">
    <a href="{{ route('admin.epidemiologi.index') }}" class="btn btn-secondary">Batal</a>
    <button type="submit" class="btn btn-primary btn-lg">
        <i class="fa fa-save mr-2"></i>Simpan Kasus
    </button>
</div>
</form>
@endsection

@section('custom_scripts')
<script>
// Expand/collapse all
$('#btn-expand-all').on('click', function() {
    $('#accordionEpi .collapse').collapse('show');
});
$('#btn-collapse-all').on('click', function() {
    $('#accordionEpi .collapse').collapse('hide');
});

// Cascading selects
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

// Auto-calculate umur display
$('#tanggal_lahir').on('change', function() {
    var dob = new Date($(this).val());
    var today = new Date();
    var years = today.getFullYear() - dob.getFullYear();
    var m = today.getMonth() - dob.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) { years--; }
    $('#display-umur').text(years + ' tahun');
});

// NIK duplicate check
var nikTimer;
$('#nik').on('input', function() {
    clearTimeout(nikTimer);
    var nik = $(this).val();
    $('#nik-warning').addClass('d-none');
    if (nik.length === 16) {
        nikTimer = setTimeout(function() {
            $.post('/admin/epidemiologi/ajax/check-nik', {nik: nik, _token: '{{ csrf_token() }}'}, function(res) {
                if (res.exists) $('#nik-warning').removeClass('d-none');
            });
        }, 500);
    }
});

// Conditional: lab result date
$('#status_lab').on('change', function() {
    var v = $(this).val();
    if (v === 'positif' || v === 'negatif') {
        $('#row-tgl-hasil-lab').show();
    } else {
        $('#row-tgl-hasil-lab').hide();
    }
});

// Conditional: rawat inap dates
$('#status_rawat').on('change', function() {
    if ($(this).val() === 'rawat_inap') {
        $('#row-rawat-tanggal').show();
    } else {
        $('#row-rawat-tanggal').hide();
    }
});

// Auto-calculate lama rawat
$('#tanggal_masuk_rs, #tanggal_keluar_rs').on('change', function() {
    var masuk  = $('#tanggal_masuk_rs').val();
    var keluar = $('#tanggal_keluar_rs').val();
    if (masuk && keluar) {
        var d = Math.round((new Date(keluar) - new Date(masuk)) / 86400000);
        $('#display-lama-rawat').text(d + ' hari');
    }
});

// Conditional: penyebab kematian
$('#kondisi_akhir').on('change', function() {
    if ($(this).val() === 'meninggal') {
        $('#row-penyebab-kematian').show();
    } else {
        $('#row-penyebab-kematian').hide();
    }
});

// Initial state
$('#status_lab').trigger('change');
$('#status_rawat').trigger('change');
$('#kondisi_akhir').trigger('change');
</script>
@endsection

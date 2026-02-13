@extends('admin::layouts.app')
@section('title') Admin @endsection
@section('title-content') Epidemiologi @endsection
@section('item') Surveillance @endsection
@section('item-active') Tambah Kasus @endsection

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">
            <i class="fa fa-plus-circle mr-2"></i>
            Tambah Kasus Surveillance Baru
        </h2>
        <a href="{{ route('admin.epidemiologi.index') }}" class="btn btn-secondary">
            <i class="fa fa-arrow-left"></i> Kembali
        </a>
    </div>

    @if ($errors->any())
    <div class="alert alert-danger">
        <h5><i class="fa fa-exclamation-triangle"></i> Terdapat kesalahan validasi:</h5>
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('admin.epidemiologi.store') }}" id="surveillanceForm">
        @csrf

        <!-- Accordion Form -->
        <div class="accordion" id="formAccordion">

            <!-- Section A: Patient Identity -->
            <div class="card">
                <div class="card-header bg-primary text-white" id="headingA">
                    <h5 class="mb-0">
                        <button class="btn btn-link text-white" type="button" data-toggle="collapse" data-target="#collapseA">
                            <i class="fa fa-user"></i> A. Identitas Pasien <span class="text-danger">*</span>
                        </button>
                    </h5>
                </div>
                <div id="collapseA" class="collapse show" data-parent="#formAccordion">
                    <div class="card-body">
                        @include('admin.epidemiologi.components.form-section-a')
                    </div>
                </div>
            </div>

            <!-- Section B: Reporter Identity -->
            <div class="card">
                <div class="card-header bg-info text-white" id="headingB">
                    <h5 class="mb-0">
                        <button class="btn btn-link text-white collapsed" type="button" data-toggle="collapse" data-target="#collapseB">
                            <i class="fa fa-user-tie"></i> B. Identitas Pelapor <span class="text-danger">*</span>
                        </button>
                    </h5>
                </div>
                <div id="collapseB" class="collapse" data-parent="#formAccordion">
                    <div class="card-body">
                        @include('admin.epidemiologi.components.form-section-b')
                    </div>
                </div>
            </div>

            <!-- Section C: Case Data -->
            <div class="card">
                <div class="card-header bg-warning text-dark" id="headingC">
                    <h5 class="mb-0">
                        <button class="btn btn-link text-dark collapsed" type="button" data-toggle="collapse" data-target="#collapseC">
                            <i class="fa fa-file-medical"></i> C. Data Kasus <span class="text-danger">*</span>
                        </button>
                    </h5>
                </div>
                <div id="collapseC" class="collapse" data-parent="#formAccordion">
                    <div class="card-body">
                        @include('admin.epidemiologi.components.form-section-c')
                    </div>
                </div>
            </div>

            <!-- Section D: Clinical Symptoms -->
            <div class="card">
                <div class="card-header bg-danger text-white" id="headingD">
                    <h5 class="mb-0">
                        <button class="btn btn-link text-white collapsed" type="button" data-toggle="collapse" data-target="#collapseD">
                            <i class="fa fa-thermometer-half"></i> D. Gejala Klinis
                        </button>
                    </h5>
                </div>
                <div id="collapseD" class="collapse" data-parent="#formAccordion">
                    <div class="card-body">
                        @include('admin.epidemiologi.components.form-section-d')
                    </div>
                </div>
            </div>

            <!-- Section E: History -->
            <div class="card">
                <div class="card-header bg-secondary text-white" id="headingE">
                    <h5 class="mb-0">
                        <button class="btn btn-link text-white collapsed" type="button" data-toggle="collapse" data-target="#collapseE">
                            <i class="fa fa-history"></i> E. Riwayat
                        </button>
                    </h5>
                </div>
                <div id="collapseE" class="collapse" data-parent="#formAccordion">
                    <div class="card-body">
                        @include('admin.epidemiologi.components.form-section-e')
                    </div>
                </div>
            </div>

            <!-- Section F: Laboratory -->
            <div class="card">
                <div class="card-header bg-success text-white" id="headingF">
                    <h5 class="mb-0">
                        <button class="btn btn-link text-white collapsed" type="button" data-toggle="collapse" data-target="#collapseF">
                            <i class="fa fa-flask"></i> F. Pemeriksaan Laboratorium
                        </button>
                    </h5>
                </div>
                <div id="collapseF" class="collapse" data-parent="#formAccordion">
                    <div class="card-body">
                        @include('admin.epidemiologi.components.form-section-f')
                    </div>
                </div>
            </div>

            <!-- Section G: Management -->
            <div class="card">
                <div class="card-header bg-primary text-white" id="headingG">
                    <h5 class="mb-0">
                        <button class="btn btn-link text-white collapsed" type="button" data-toggle="collapse" data-target="#collapseG">
                            <i class="fa fa-hospital"></i> G. Tatalaksana <span class="text-danger">*</span>
                        </button>
                    </h5>
                </div>
                <div id="collapseG" class="collapse" data-parent="#formAccordion">
                    <div class="card-body">
                        @include('admin.epidemiologi.components.form-section-g')
                    </div>
                </div>
            </div>

            <!-- Section H: Final Status -->
            <div class="card">
                <div class="card-header bg-dark text-white" id="headingH">
                    <h5 class="mb-0">
                        <button class="btn btn-link text-white collapsed" type="button" data-toggle="collapse" data-target="#collapseH">
                            <i class="fa fa-heartbeat"></i> H. Status Akhir
                        </button>
                    </h5>
                </div>
                <div id="collapseH" class="collapse" data-parent="#formAccordion">
                    <div class="card-body">
                        @include('admin.epidemiologi.components.form-section-h')
                    </div>
                </div>
            </div>

            <!-- Section I: Contact Investigation -->
            <div class="card">
                <div class="card-header bg-info text-white" id="headingI">
                    <h5 class="mb-0">
                        <button class="btn btn-link text-white collapsed" type="button" data-toggle="collapse" data-target="#collapseI">
                            <i class="fa fa-users"></i> I. Investigasi Kontak
                        </button>
                    </h5>
                </div>
                <div id="collapseI" class="collapse" data-parent="#formAccordion">
                    <div class="card-body">
                        @include('admin.epidemiologi.components.form-section-i')
                    </div>
                </div>
            </div>

            <!-- Section J: Metadata -->
            <div class="card">
                <div class="card-header bg-secondary text-white" id="headingJ">
                    <h5 class="mb-0">
                        <button class="btn btn-link text-white collapsed" type="button" data-toggle="collapse" data-target="#collapseJ">
                            <i class="fa fa-info-circle"></i> J. Informasi Tambahan
                        </button>
                    </h5>
                </div>
                <div id="collapseJ" class="collapse" data-parent="#formAccordion">
                    <div class="card-body">
                        @include('admin.epidemiologi.components.form-section-j')
                    </div>
                </div>
            </div>

        </div>

        <!-- Form Actions -->
        <div class="card mt-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fa fa-save"></i> Simpan Kasus
                        </button>
                        <a href="{{ route('admin.epidemiologi.index') }}" class="btn btn-secondary btn-lg">
                            <i class="fa fa-times"></i> Batal
                        </a>
                        <button type="button" class="btn btn-info btn-lg" id="expandAll">
                            <i class="fa fa-expand"></i> Buka Semua Section
                        </button>
                        <button type="button" class="btn btn-outline-info btn-lg" id="collapseAll">
                            <i class="fa fa-compress"></i> Tutup Semua Section
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Expand/Collapse all sections
    $('#expandAll').on('click', function() {
        $('.collapse').collapse('show');
    });

    $('#collapseAll').on('click', function() {
        $('.collapse').collapse('hide');
    });

    // Auto-scroll to validation errors
    @if ($errors->any())
        $('html, body').animate({
            scrollTop: $(".alert-danger").offset().top - 100
        }, 500);
    @endif

    // Form submission confirmation
    $('#surveillanceForm').on('submit', function(e) {
        // You can add additional client-side validation here if needed
        return true;
    });
});
</script>
@endsection

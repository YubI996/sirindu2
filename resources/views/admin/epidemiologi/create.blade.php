@extends('admin::layouts.app')
@section('title') Tambah Kasus Surveillance @endsection
@section('title-content') Epidemiologi @endsection
@section('item') Surveillance @endsection
@section('item-active') Tambah Kasus @endsection

@section('content')
{{-- Skip Link for Accessibility --}}
<a href="#main-content" class="sr-only sr-only-focusable skip-link">Langsung ke konten utama</a>

<style>
    @include('admin.epidemiologi.components.shared-styles')

    /* Form-specific overrides */
    .form-actions-card {
        position: sticky;
        bottom: 0;
        z-index: 10;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 -4px 15px rgba(0, 0, 0, 0.08);
    }
</style>

<main id="main-content" role="main" aria-label="Form tambah kasus surveillance baru">
<div class="container-fluid">
    <!-- Header Section -->
    <header class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0" style="color: var(--primary-blue-dark);">
            <i class="fa fa-plus-circle mr-2" aria-hidden="true"></i>
            Tambah Kasus Surveillance Baru
        </h2>
        <a href="{{ route('admin.epidemiologi.index') }}" class="btn btn-outline-secondary" aria-label="Kembali ke daftar kasus">
            <i class="fa fa-arrow-left" aria-hidden="true"></i> Kembali
        </a>
    </header>

    @if ($errors->any())
    <div class="alert alert-danger" role="alert">
        <h5 class="alert-heading">
            <i class="fa fa-exclamation-triangle" aria-hidden="true"></i> Terdapat kesalahan validasi:
        </h5>
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
                <div class="card-header section-header-a text-white" id="headingA">
                    <h5 class="mb-0">
                        <button class="btn btn-link text-white" type="button" data-toggle="collapse" data-target="#collapseA" aria-expanded="true" aria-controls="collapseA">
                            <i class="fa fa-user" aria-hidden="true"></i> A. Identitas Pasien <span style="color: #fca5a5;">*</span>
                        </button>
                    </h5>
                </div>
                <div id="collapseA" class="collapse show" data-parent="#formAccordion" aria-labelledby="headingA">
                    <div class="card-body">
                        @include('admin.epidemiologi.components.form-section-a')
                    </div>
                </div>
            </div>

            <!-- Section B: Reporter Identity -->
            <div class="card">
                <div class="card-header section-header-b text-white" id="headingB">
                    <h5 class="mb-0">
                        <button class="btn btn-link text-white collapsed" type="button" data-toggle="collapse" data-target="#collapseB" aria-expanded="false" aria-controls="collapseB">
                            <i class="fa fa-user-tie" aria-hidden="true"></i> B. Identitas Pelapor <span style="color: #fca5a5;">*</span>
                        </button>
                    </h5>
                </div>
                <div id="collapseB" class="collapse" data-parent="#formAccordion" aria-labelledby="headingB">
                    <div class="card-body">
                        @include('admin.epidemiologi.components.form-section-b')
                    </div>
                </div>
            </div>

            <!-- Section C: Case Data -->
            <div class="card">
                <div class="card-header section-header-c text-white" id="headingC">
                    <h5 class="mb-0">
                        <button class="btn btn-link text-white collapsed" type="button" data-toggle="collapse" data-target="#collapseC" aria-expanded="false" aria-controls="collapseC">
                            <i class="fa fa-file-medical" aria-hidden="true"></i> C. Data Kasus <span style="color: #fca5a5;">*</span>
                        </button>
                    </h5>
                </div>
                <div id="collapseC" class="collapse" data-parent="#formAccordion" aria-labelledby="headingC">
                    <div class="card-body">
                        @include('admin.epidemiologi.components.form-section-c')
                    </div>
                </div>
            </div>

            <!-- Section D: Clinical Symptoms -->
            <div class="card">
                <div class="card-header section-header-d text-white" id="headingD">
                    <h5 class="mb-0">
                        <button class="btn btn-link text-white collapsed" type="button" data-toggle="collapse" data-target="#collapseD" aria-expanded="false" aria-controls="collapseD">
                            <i class="fa fa-thermometer-half" aria-hidden="true"></i> D. Gejala Klinis
                        </button>
                    </h5>
                </div>
                <div id="collapseD" class="collapse" data-parent="#formAccordion" aria-labelledby="headingD">
                    <div class="card-body">
                        @include('admin.epidemiologi.components.form-section-d')
                    </div>
                </div>
            </div>

            <!-- Section E: History -->
            <div class="card">
                <div class="card-header section-header-e text-white" id="headingE">
                    <h5 class="mb-0">
                        <button class="btn btn-link text-white collapsed" type="button" data-toggle="collapse" data-target="#collapseE" aria-expanded="false" aria-controls="collapseE">
                            <i class="fa fa-history" aria-hidden="true"></i> E. Riwayat
                        </button>
                    </h5>
                </div>
                <div id="collapseE" class="collapse" data-parent="#formAccordion" aria-labelledby="headingE">
                    <div class="card-body">
                        @include('admin.epidemiologi.components.form-section-e')
                    </div>
                </div>
            </div>

            <!-- Section F: Laboratory -->
            <div class="card">
                <div class="card-header section-header-f text-white" id="headingF">
                    <h5 class="mb-0">
                        <button class="btn btn-link text-white collapsed" type="button" data-toggle="collapse" data-target="#collapseF" aria-expanded="false" aria-controls="collapseF">
                            <i class="fa fa-flask" aria-hidden="true"></i> F. Pemeriksaan Laboratorium
                        </button>
                    </h5>
                </div>
                <div id="collapseF" class="collapse" data-parent="#formAccordion" aria-labelledby="headingF">
                    <div class="card-body">
                        @include('admin.epidemiologi.components.form-section-f')
                    </div>
                </div>
            </div>

            <!-- Section G: Management -->
            <div class="card">
                <div class="card-header section-header-g text-white" id="headingG">
                    <h5 class="mb-0">
                        <button class="btn btn-link text-white collapsed" type="button" data-toggle="collapse" data-target="#collapseG" aria-expanded="false" aria-controls="collapseG">
                            <i class="fa fa-hospital" aria-hidden="true"></i> G. Tatalaksana <span style="color: #fca5a5;">*</span>
                        </button>
                    </h5>
                </div>
                <div id="collapseG" class="collapse" data-parent="#formAccordion" aria-labelledby="headingG">
                    <div class="card-body">
                        @include('admin.epidemiologi.components.form-section-g')
                    </div>
                </div>
            </div>

            <!-- Section H: Final Status -->
            <div class="card">
                <div class="card-header section-header-h text-white" id="headingH">
                    <h5 class="mb-0">
                        <button class="btn btn-link text-white collapsed" type="button" data-toggle="collapse" data-target="#collapseH" aria-expanded="false" aria-controls="collapseH">
                            <i class="fa fa-heartbeat" aria-hidden="true"></i> H. Status Akhir
                        </button>
                    </h5>
                </div>
                <div id="collapseH" class="collapse" data-parent="#formAccordion" aria-labelledby="headingH">
                    <div class="card-body">
                        @include('admin.epidemiologi.components.form-section-h')
                    </div>
                </div>
            </div>

            <!-- Section I: Contact Investigation -->
            <div class="card">
                <div class="card-header section-header-i text-white" id="headingI">
                    <h5 class="mb-0">
                        <button class="btn btn-link text-white collapsed" type="button" data-toggle="collapse" data-target="#collapseI" aria-expanded="false" aria-controls="collapseI">
                            <i class="fa fa-users" aria-hidden="true"></i> I. Investigasi Kontak
                        </button>
                    </h5>
                </div>
                <div id="collapseI" class="collapse" data-parent="#formAccordion" aria-labelledby="headingI">
                    <div class="card-body">
                        @include('admin.epidemiologi.components.form-section-i')
                    </div>
                </div>
            </div>

            <!-- Section J: Metadata -->
            <div class="card">
                <div class="card-header section-header-j text-white" id="headingJ">
                    <h5 class="mb-0">
                        <button class="btn btn-link text-white collapsed" type="button" data-toggle="collapse" data-target="#collapseJ" aria-expanded="false" aria-controls="collapseJ">
                            <i class="fa fa-info-circle" aria-hidden="true"></i> J. Informasi Tambahan
                        </button>
                    </h5>
                </div>
                <div id="collapseJ" class="collapse" data-parent="#formAccordion" aria-labelledby="headingJ">
                    <div class="card-body">
                        @include('admin.epidemiologi.components.form-section-j')
                    </div>
                </div>
            </div>

        </div>

        <!-- Form Actions -->
        <div class="card form-actions-card mt-4">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary btn-lg" aria-label="Simpan kasus baru">
                        <i class="fa fa-save" aria-hidden="true"></i> Simpan Kasus
                    </button>
                    <a href="{{ route('admin.epidemiologi.index') }}" class="btn btn-outline-secondary btn-lg">
                        <i class="fa fa-times" aria-hidden="true"></i> Batal
                    </a>
                    <button type="button" class="btn btn-outline-info btn-lg" id="expandAll" aria-label="Buka semua section accordion">
                        <i class="fa fa-expand" aria-hidden="true"></i> Buka Semua
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-lg" id="collapseAll" aria-label="Tutup semua section accordion">
                        <i class="fa fa-compress" aria-hidden="true"></i> Tutup Semua
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
</main>
@endsection

@section('scripts')
@parent
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
        return true;
    });
});
</script>
@endsection

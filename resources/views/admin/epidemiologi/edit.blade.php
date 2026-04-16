@extends('admin::layouts.app')
@section('title') Edit Kasus - {{ $case->no_registrasi }} @endsection
@section('title-content') Epidemiologi @endsection
@section('item') Surveillance @endsection
@section('item-active') Edit Kasus @endsection

@section('content')
{{-- Skip Link for Accessibility --}}
<a href="#main-content" class="sr-only sr-only-focusable skip-link">Langsung ke konten utama</a>

<style>
    @include('admin.epidemiologi.components.shared-styles')

    /* Form-specific overrides */
    .case-info-strip {
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        background: linear-gradient(135deg, var(--primary-blue-light) 0%, var(--success-green-light) 100%);
    }
    .section-check { color: #047857; margin-left: 0.5rem; display: none; }
    .section-check.visible { display: inline; }
</style>

<main id="main-content" role="main" aria-label="Edit kasus surveillance {{ $case->no_registrasi }}">
<div class="container-fluid">
    <!-- Header Section -->
    <header class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0" style="color: var(--primary-blue-dark);">
            <i class="fa fa-edit mr-2" aria-hidden="true"></i>
            Edit Kasus Surveillance
        </h2>
        <nav aria-label="Navigasi kasus">
            <a href="{{ route('admin.epidemiologi.show', $case->id) }}" class="btn btn-outline-info" aria-label="Lihat detail kasus {{ $case->no_registrasi }}">
                <i class="fa fa-eye" aria-hidden="true"></i> Lihat Detail
            </a>
            <a href="{{ route('admin.epidemiologi.index') }}" class="btn btn-outline-secondary" aria-label="Kembali ke daftar kasus">
                <i class="fa fa-arrow-left" aria-hidden="true"></i> Kembali
            </a>
        </nav>
    </header>

    <!-- Case Info Strip -->
    <div class="case-info-strip p-3 mb-4" role="status" aria-label="Informasi ringkas kasus yang diedit">
        <div class="row">
            <div class="col-md-3">
                <small class="text-accessible-muted">No. Epid</small>
                <p class="mb-0"><strong>{{ $case->no_registrasi ?: '-' }}</strong></p>
            </div>
            <div class="col-md-3">
                <small class="text-accessible-muted">NIK</small>
                <p class="mb-0">{{ $case->nik }}</p>
            </div>
            <div class="col-md-3">
                <small class="text-accessible-muted">Nama</small>
                <p class="mb-0"><strong>{{ $case->nama_lengkap }}</strong></p>
            </div>
            <div class="col-md-3">
                <small class="text-accessible-muted">Penyakit</small>
                <p class="mb-0">{{ $case->jenisKasus->nama_penyakit ?? '-' }}</p>
            </div>
        </div>
    </div>

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

    <form method="POST" action="{{ route('admin.epidemiologi.update', $case->id) }}" id="surveillanceForm">
        @csrf
        @method('PUT')

        <!-- Accordion Form -->
        <div class="accordion" id="formAccordion">

            <!-- Section A: Patient Identity -->
            <div class="card" data-section="A">
                <div class="card-header section-header-a" id="headingA">
                    <h5 class="mb-0">
                        <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#collapseA" aria-expanded="true" aria-controls="collapseA">
                            <i class="fa fa-user" aria-hidden="true"></i> A. Identitas Pasien <span class="text-danger ml-1">*</span>
                            <span class="section-check" title="Semua field wajib terisi"><i class="fa fa-check-circle"></i></span>
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
            <div class="card" data-section="B">
                <div class="card-header section-header-b" id="headingB">
                    <h5 class="mb-0">
                        <button class="btn btn-link collapsed" type="button" data-toggle="collapse" data-target="#collapseB" aria-expanded="false" aria-controls="collapseB">
                            <i class="fa fa-user-tie" aria-hidden="true"></i> B. Identitas Pelapor <span class="text-danger ml-1">*</span>
                            <span class="section-check" title="Semua field wajib terisi"><i class="fa fa-check-circle"></i></span>
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
            <div class="card" data-section="C">
                <div class="card-header section-header-c" id="headingC">
                    <h5 class="mb-0">
                        <button class="btn btn-link collapsed" type="button" data-toggle="collapse" data-target="#collapseC" aria-expanded="false" aria-controls="collapseC">
                            <i class="fa fa-file-medical" aria-hidden="true"></i> C. Data Kasus <span class="text-danger ml-1">*</span>
                            <span class="section-check" title="Semua field wajib terisi"><i class="fa fa-check-circle"></i></span>
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
            <div class="card" data-section="D">
                <div class="card-header section-header-d" id="headingD">
                    <h5 class="mb-0">
                        <button class="btn btn-link collapsed" type="button" data-toggle="collapse" data-target="#collapseD" aria-expanded="false" aria-controls="collapseD">
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

            <!-- Section D2: Komplikasi (Campak/Rubella) -->
            <div class="card disease-card" data-diseases="CAMPAK_RUBELLA" style="display:none;">
                <div class="card-header section-header-d" id="headingD2">
                    <h5 class="mb-0">
                        <button class="btn btn-link collapsed" type="button" data-toggle="collapse" data-target="#collapseD2" aria-expanded="false" aria-controls="collapseD2">
                            <i class="fa fa-exclamation-triangle" aria-hidden="true"></i> D2. Komplikasi
                        </button>
                    </h5>
                </div>
                <div id="collapseD2" class="collapse" data-parent="#formAccordion" aria-labelledby="headingD2">
                    <div class="card-body">
                        @include('admin.epidemiologi.components.form-section-d2')
                    </div>
                </div>
            </div>

            <!-- Section D3: Pengobatan & AFP -->
            <div class="card disease-card" data-diseases="DIFTERI_OBS,AFP" style="display:none;">
                <div class="card-header section-header-d" id="headingD3">
                    <h5 class="mb-0">
                        <button class="btn btn-link collapsed" type="button" data-toggle="collapse" data-target="#collapseD3" aria-expanded="false" aria-controls="collapseD3">
                            <i class="fa fa-pills" aria-hidden="true"></i> D3. Pengobatan & AFP
                        </button>
                    </h5>
                </div>
                <div id="collapseD3" class="collapse" data-parent="#formAccordion" aria-labelledby="headingD3">
                    <div class="card-body">
                        @include('admin.epidemiologi.components.form-section-d3')
                    </div>
                </div>
            </div>

            <!-- Section TN: Tetanus Neonatorum -->
            <div class="card disease-card" data-diseases="TETANUS_NEO" style="display:none;">
                <div class="card-header section-header-d" id="headingTN">
                    <h5 class="mb-0">
                        <button class="btn btn-link collapsed" type="button" data-toggle="collapse" data-target="#collapseTN" aria-expanded="false" aria-controls="collapseTN">
                            <i class="fa fa-baby" aria-hidden="true"></i> TN. Tetanus Neonatorum
                        </button>
                    </h5>
                </div>
                <div id="collapseTN" class="collapse" data-parent="#formAccordion" aria-labelledby="headingTN">
                    <div class="card-body">
                        @include('admin.epidemiologi.components.form-section-tn')
                    </div>
                </div>
            </div>

            <!-- Section E: History -->
            <div class="card" data-section="E">
                <div class="card-header section-header-e" id="headingE">
                    <h5 class="mb-0">
                        <button class="btn btn-link collapsed" type="button" data-toggle="collapse" data-target="#collapseE" aria-expanded="false" aria-controls="collapseE">
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
            <div class="card" data-section="F">
                <div class="card-header section-header-f" id="headingF">
                    <h5 class="mb-0">
                        <button class="btn btn-link collapsed" type="button" data-toggle="collapse" data-target="#collapseF" aria-expanded="false" aria-controls="collapseF">
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
            <div class="card" data-section="G">
                <div class="card-header section-header-g" id="headingG">
                    <h5 class="mb-0">
                        <button class="btn btn-link collapsed" type="button" data-toggle="collapse" data-target="#collapseG" aria-expanded="false" aria-controls="collapseG">
                            <i class="fa fa-hospital" aria-hidden="true"></i> G. Tatalaksana <span class="text-danger ml-1">*</span>
                            <span class="section-check" title="Semua field wajib terisi"><i class="fa fa-check-circle"></i></span>
                        </button>
                    </h5>
                </div>
                <div id="collapseG" class="collapse" data-parent="#formAccordion" aria-labelledby="headingG">
                    <div class="card-body">
                        @include('admin.epidemiologi.components.form-section-g')
                    </div>
                </div>
            </div>

            <!-- Section G2: Dokter & Tempat Berobat -->
            <div class="card" data-section="G2">
                <div class="card-header section-header-g" id="headingG2">
                    <h5 class="mb-0">
                        <button class="btn btn-link collapsed" type="button" data-toggle="collapse" data-target="#collapseG2" aria-expanded="false" aria-controls="collapseG2">
                            <i class="fa fa-user-md" aria-hidden="true"></i> G2. Dokter & Tempat Berobat
                        </button>
                    </h5>
                </div>
                <div id="collapseG2" class="collapse" data-parent="#formAccordion" aria-labelledby="headingG2">
                    <div class="card-body">
                        @include('admin.epidemiologi.components.form-section-g2')
                    </div>
                </div>
            </div>

            <!-- Section H: Final Status -->
            <div class="card" data-section="H">
                <div class="card-header section-header-h" id="headingH">
                    <h5 class="mb-0">
                        <button class="btn btn-link collapsed" type="button" data-toggle="collapse" data-target="#collapseH" aria-expanded="false" aria-controls="collapseH">
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
            <div class="card" data-section="I">
                <div class="card-header section-header-i" id="headingI">
                    <h5 class="mb-0">
                        <button class="btn btn-link collapsed" type="button" data-toggle="collapse" data-target="#collapseI" aria-expanded="false" aria-controls="collapseI">
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
            <div class="card" data-section="J">
                <div class="card-header section-header-j" id="headingJ">
                    <h5 class="mb-0">
                        <button class="btn btn-link collapsed" type="button" data-toggle="collapse" data-target="#collapseJ" aria-expanded="false" aria-controls="collapseJ">
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
                    <button type="submit" class="btn btn-primary btn-lg" aria-label="Simpan perubahan kasus">
                        <i class="fa fa-save" aria-hidden="true"></i>
                        <span class="d-none d-sm-inline"> Simpan Perubahan</span>
                    </button>
                    <a href="{{ route('admin.epidemiologi.show', $case->id) }}" class="btn btn-outline-info btn-lg" aria-label="Lihat Detail">
                        <i class="fa fa-eye" aria-hidden="true"></i>
                        <span class="d-none d-sm-inline"> Lihat Detail</span>
                    </a>
                    <a href="{{ route('admin.epidemiologi.index') }}" class="btn btn-outline-secondary btn-lg" aria-label="Batal">
                        <i class="fa fa-times" aria-hidden="true"></i>
                        <span class="d-none d-sm-inline"> Batal</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Audit Info -->
        <div class="card info-card mt-3">
            <div class="card-body" style="background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);">
                <small class="text-accessible-muted">
                    <i class="fa fa-info-circle" aria-hidden="true"></i>
                    Dibuat oleh: <strong>{{ $case->creator->name ?? 'Unknown' }}</strong>
                    pada <time datetime="{{ $case->created_at->format('Y-m-d\TH:i:s') }}">{{ $case->created_at->format('d/m/Y H:i') }}</time>
                    <span aria-hidden="true">|</span>
                    Terakhir diubah: <time datetime="{{ $case->updated_at->format('Y-m-d\TH:i:s') }}">{{ $case->updated_at->format('d/m/Y H:i') }}</time>
                </small>
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
    // ── Disease-specific card visibility ──
    function toggleDiseaseCards() {
        var kode = $('#id_jenis_kasus').find('option:selected').data('kode') || '';

        $('.disease-card').each(function() {
            var diseases = ($(this).data('diseases') || '').toString().split(',');
            if (kode && diseases.indexOf(kode) !== -1) {
                $(this).show();
            } else {
                $(this).find('.collapse').collapse('hide');
                $(this).hide();
            }
        });
    }

    $('#id_jenis_kasus').on('change', toggleDiseaseCards);
    toggleDiseaseCards(); // on page load for existing case

    // ── Section completion indicators ──
    function updateSectionChecks() {
        $('#formAccordion .card[data-section]').each(function() {
            var $card = $(this);
            var $check = $card.find('.section-check');
            if ($check.length === 0) return;

            var allFilled = true;
            $card.find('[required]').each(function() {
                if (!$(this).val()) {
                    allFilled = false;
                    return false;
                }
            });

            $check.toggleClass('visible', allFilled);
        });
    }

    $('#surveillanceForm').on('change input', '[required]', function() {
        updateSectionChecks();
    });
    updateSectionChecks();

    // Auto-scroll to validation errors
    @if ($errors->any())
        $('html, body').animate({
            scrollTop: $(".alert-danger").offset().top - 100
        }, 500);
    @endif
});
</script>
@endsection

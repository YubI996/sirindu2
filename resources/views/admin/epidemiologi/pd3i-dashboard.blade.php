@extends('admin::layouts.app')
@section('title') Dashboard Surveilans PD3I @endsection
@section('title-content') Epidemiologi @endsection
@section('item') PD3I @endsection
@section('item-active') Dashboard PD3I @endsection

@section('content')
<style>
    @include('admin.epidemiologi.components.shared-styles')

    .pd3i-panel-header {
        padding: 0.75rem 1.25rem;
        font-weight: 700;
        font-size: 0.9rem;
        color: #fff;
        border-radius: 10px 10px 0 0;
    }
    .pd3i-panel { border-radius: 10px; border: 1px solid #e5e7eb; overflow: hidden; margin-bottom: 1.25rem; }
    .pd3i-panel-body { padding: 1rem; }
    .panel-campak  { background: linear-gradient(135deg, #047857, #065f46); }
    .panel-afp     { background: linear-gradient(135deg, #b45309, #92400e); }
    .panel-difteri { background: linear-gradient(135deg, #1d4ed8, #1e40af); }
    .panel-pertusis{ background: linear-gradient(135deg, #7c3aed, #6d28d9); }

    .kinerja-card { border-radius: 8px; border: 1px solid var(--srd-border, #e5e7eb); padding: 0.75rem 1rem; text-align: center; background: var(--srd-surface, #fff); }
    .kinerja-card .k-label { font-size: 0.72rem; color: var(--text-secondary, #6b7280); margin-bottom: 2px; font-weight: 500; letter-spacing: 0.2px; }
    .kinerja-card .k-value { font-size: 1.6rem; font-weight: 700; color: var(--primary-blue-dark, #1e40af); line-height: 1.1; }
    .kinerja-card .k-value.pct { font-size: 1.3rem; color: var(--success-green, #047857); }
    .kinerja-card .k-value.danger { color: var(--danger-rose, #be123c); }
    .kinerja-card .k-value.active-case { color: #d97706; }
    .kinerja-card.disabled .k-value { color: #9ca3af; font-size: 1rem; }
    .kinerja-card.primary { border-color: var(--srd-border, #e5e7eb); }
    .kinerja-card.primary .k-value { font-size: 2.4rem; }
    .kinerja-card.primary .k-label { font-size: 0.75rem; font-weight: 600; }

    .pd3i-quality-row {
        padding-top: 0.625rem;
        margin-top: 0.375rem;
        border-top: 1px solid var(--srd-border, #f3f4f6);
    }
    .pd3i-quality-row .kinerja-card { background: var(--srd-surface-subtle, #f8fafc); }
    .pd3i-quality-row .k-value { font-size: 1.25rem; }

    .skeleton { background: linear-gradient(90deg, #f3f4f6 25%, #e5e7eb 50%, #f3f4f6 75%);
                background-size: 200% 100%; animation: shimmer 1.4s infinite; border-radius: 4px; }
    @keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
    .skel-text { height: 1.4rem; width: 60%; margin: 0.25rem auto; }
    .skel-value { height: 2rem; width: 50%; margin: 0.15rem auto; }

    .filter-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1.25rem; }
    .nav-tabs .nav-link { font-weight: 600; font-size: 0.85rem; padding: 0.6rem 1.1rem; color: #374151; border-radius: 8px 8px 0 0; }
    .nav-tabs .nav-link.active { color: #1e40af; border-color: #e5e7eb #e5e7eb #fff; background: #fff; }
    .tab-content { border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 10px 10px; padding: 1.25rem; background: #fff; }
</style>

<div class="container-fluid" id="main-content">

    {{-- ===== HEADER ===== --}}
    <header class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0" style="color: var(--primary-blue-dark); font-size:1.3rem;">
                <i class="fa fa-chart-bar mr-2" aria-hidden="true"></i>
                Dashboard Surveilans PD3I
            </h2>
            <small class="text-muted">Kota Bontang — Data real-time dari sistem surveilans</small>
        </div>
        <div class="d-flex gap-2 flex-wrap justify-content-end">
            <a href="{{ route('admin.epidemiologi.dashboard') }}" class="btn btn-sm btn-outline-secondary">
                <i class="fa fa-chart-line mr-1"></i> Dashboard Umum
            </a>
            <a href="{{ route('admin.epidemiologi.index') }}" class="btn btn-sm btn-outline-primary">
                <i class="fa fa-list mr-1"></i> Daftar Kasus
            </a>
            <a id="btnExportExcel" href="#" class="btn btn-sm btn-success">
                <i class="fa fa-file-excel mr-1"></i> Export Excel
            </a>
            <form id="formExportPdf" method="POST" action="{{ route('admin.pd3i.exportPdf') }}" style="display:inline;">
                @csrf
                <input type="hidden" name="tahun" id="pdf_tahun">
                <input type="hidden" name="jenis_kasus_id" id="pdf_jenis_kasus_id">
                <input type="hidden" name="wilker" id="pdf_wilker">
                <input type="hidden" name="kelurahan_id" id="pdf_kelurahan_id">
                <button type="submit" class="btn btn-sm btn-danger">
                    <i class="fa fa-file-pdf mr-1"></i> Export PDF
                </button>
            </form>
        </div>
    </header>

    {{-- ===== FILTER BAR ===== --}}
    <div class="filter-card">
        <div class="row align-items-end g-2">
            <div class="col-6 col-md-2">
                <label for="filter-tahun" class="mb-1"><i class="fa fa-calendar-alt mr-1"></i> Tahun</label>
                <select id="filter-tahun" class="form-control">
                    @for($y = now()->year; $y >= now()->year - 5; $y--)
                        <option value="{{ $y }}" {{ $y == now()->year ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label for="filter-penyakit" class="mb-1"><i class="fa fa-virus mr-1"></i> Jenis Penyakit</label>
                <select id="filter-penyakit" class="form-control">
                    <option value="">Semua PD3I</option>
                    @foreach($diseases as $d)
                        <option value="{{ $d->id }}">{{ $d->nama_penyakit }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label for="filter-wilker" class="mb-1"><i class="fa fa-hospital mr-1"></i> Wilker Puskesmas</label>
                <select id="filter-wilker" class="form-control">
                    <option value="">Semua Puskesmas</option>
                    @foreach($wilkers as $w)
                        <option value="{{ $w }}">{{ $w }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-4">
                <label for="filter-kelurahan" class="mb-1"><i class="fa fa-map-marker-alt mr-1"></i> Kelurahan</label>
                <select id="filter-kelurahan" class="form-control">
                    <option value="">Semua Kelurahan</option>
                    @foreach($kelurahans as $kel)
                        <option value="{{ $kel->id }}">{{ $kel->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- ===== TABS ===== --}}
    <ul class="nav nav-tabs" id="pd3iTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="tab-kinerja-link" data-toggle="tab" href="#tab-kinerja" role="tab">
                <i class="fa fa-tachometer-alt mr-1"></i> Kinerja Surveilans
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="tab-demografi-link" data-toggle="tab" href="#tab-demografi" role="tab">
                <i class="fa fa-users mr-1"></i> Demografi
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="tab-tren-link" data-toggle="tab" href="#tab-tren" role="tab">
                <i class="fa fa-chart-area mr-1"></i> Tren
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="tab-tempat-link" data-toggle="tab" href="#tab-tempat" role="tab">
                <i class="fa fa-map-marked-alt mr-1"></i> Tempat
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="tab-peta-link" data-toggle="tab" href="#tab-peta" role="tab">
                <i class="fa fa-map mr-1"></i> Peta
            </a>
        </li>
    </ul>

    <div class="tab-content" id="pd3iTabContent">

        {{-- ===== TAB 1: KINERJA SURVEILANS ===== --}}
        <div class="tab-pane fade show active" id="tab-kinerja" role="tabpanel">

            {{-- PANEL: Campak-Rubella --}}
            <div class="pd3i-panel">
                <div class="pd3i-panel-header panel-campak">
                    <i class="fa fa-circle mr-1" style="font-size:.6rem;"></i> Campak-Rubella
                </div>
                <div class="pd3i-panel-body">
                    {{-- Baris utama: 4 metrik kasus --}}
                    <div class="row g-2">
                        <div class="col-6 col-md-3">
                            <div class="kinerja-card primary">
                                <div class="k-label">Suspek</div>
                                <div class="k-value" id="cr-suspek"><div class="skeleton skel-value"></div></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="kinerja-card">
                                <div class="k-label">Kasus Campak</div>
                                <div class="k-value" id="cr-kasus-campak"><div class="skeleton skel-value"></div></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="kinerja-card">
                                <div class="k-label">Kasus Rubella</div>
                                <div class="k-value" id="cr-kasus-rubella"><div class="skeleton skel-value"></div></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="kinerja-card">
                                <div class="k-label">Discarded / Negatif</div>
                                <div class="k-value" id="cr-discarded"><div class="skeleton skel-value"></div></div>
                                <div id="cr-discarded-note" style="font-size:0.65rem; color:#6b7280; margin-top:2px;"></div>
                            </div>
                        </div>
                    </div>
                    {{-- Baris kualitas: status akhir + indikator rate --}}
                    <div class="row g-2 pd3i-quality-row">
                        <div class="col-sm-6 col-md-4">
                            <div class="kinerja-card">
                                <div class="k-label">Kematian</div>
                                <div class="k-value danger" id="cr-kematian"><div class="skeleton skel-value"></div></div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <div class="kinerja-card">
                                <div class="k-label">Kasus Aktif (Dalam Perawatan)</div>
                                <div class="k-value active-case" id="cr-kasus-aktif"><div class="skeleton skel-value"></div></div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <div class="kinerja-card">
                                <div class="k-label">% Pengambilan Spesimen</div>
                                <div class="k-value pct" id="cr-pct-sampel"><div class="skeleton skel-value"></div></div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-6">
                            <div class="kinerja-card">
                                <div class="k-label">% Hasil Lab Diterima</div>
                                <div class="k-value pct" id="cr-pct-lab"><div class="skeleton skel-value"></div></div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-6">
                            <div class="kinerja-card">
                                <div class="k-label">Positivity Rate</div>
                                <div class="k-value pct" id="cr-positivity"><div class="skeleton skel-value"></div></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- PANEL: AFP/Polio --}}
            <div class="pd3i-panel">
                <div class="pd3i-panel-header panel-afp">
                    <i class="fa fa-circle mr-1" style="font-size:.6rem;"></i> AFP / Polio
                </div>
                <div class="pd3i-panel-body">
                    <div class="row g-2">
                        <div class="col-6 col-md-4">
                            <div class="kinerja-card">
                                <div class="k-label">Total AFP</div>
                                <div class="k-value" id="afp-total"><div class="skeleton skel-value"></div></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="kinerja-card">
                                <div class="k-label">Terkonfirmasi Polio</div>
                                <div class="k-value" id="afp-confirmed"><div class="skeleton skel-value"></div></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="kinerja-card">
                                <div class="k-label">Non-Polio AFP Rate</div>
                                <div class="k-value" id="afp-npafp"><div class="skeleton skel-value"></div></div>
                                <div id="afp-npafp-note" style="font-size:0.65rem; color:#6b7280; margin-top:2px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- PANEL: Difteri & Pertusis --}}
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="pd3i-panel">
                        <div class="pd3i-panel-header panel-difteri">
                            <i class="fa fa-circle mr-1" style="font-size:.6rem;"></i> Difteri
                        </div>
                        <div class="pd3i-panel-body">
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="kinerja-card">
                                        <div class="k-label">Laporan Observasi</div>
                                        <div class="k-value" id="difteri-observasi"><div class="skeleton skel-value"></div></div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="kinerja-card">
                                        <div class="k-label">Terkonfirmasi</div>
                                        <div class="k-value" id="difteri-confirmed"><div class="skeleton skel-value"></div></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="pd3i-panel">
                        <div class="pd3i-panel-header panel-pertusis">
                            <i class="fa fa-circle mr-1" style="font-size:.6rem;"></i> Pertusis
                        </div>
                        <div class="pd3i-panel-body">
                            <div class="row g-2">
                                <div class="col-6 col-md-8 mx-auto">
                                    <div class="kinerja-card">
                                        <div class="k-label">Suspek</div>
                                        <div class="k-value" id="pertusis-suspek"><div class="skeleton skel-value"></div></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>{{-- /tab-kinerja --}}

        {{-- ===== TAB 2: DEMOGRAFI ===== --}}
        <div class="tab-pane fade" id="tab-demografi" role="tabpanel">

            {{-- Jenis Kelamin + Kelompok Umur --}}
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="info-card h-100">
                        <div class="card-header"><i class="fa fa-venus-mars mr-1"></i> Proporsi Jenis Kelamin</div>
                        <div class="card-body d-flex align-items-center justify-content-center">
                            <div style="height:200px; width:200px; position:relative;">
                                <canvas id="chart-gender"></canvas>
                                <div id="skel-gender" class="skeleton" style="position:absolute;inset:0;border-radius:50%;"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="info-card h-100">
                        <div class="card-header"><i class="fa fa-birthday-cake mr-1"></i> Distribusi Kelompok Umur</div>
                        <div class="card-body">
                            <div style="height:220px; position:relative;">
                                <canvas id="chart-kelompok-umur"></canvas>
                                <div id="skel-kelompok-umur" class="skeleton" style="position:absolute;inset:0;border-radius:6px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Gejala --}}
            <div class="info-card mb-3">
                <div class="card-header"><i class="fa fa-thermometer-half mr-1"></i> Distribusi Gejala</div>
                <div class="card-body">
                    <div style="height:240px; position:relative;">
                        <canvas id="chart-gejala"></canvas>
                        <div id="skel-gejala" class="skeleton" style="position:absolute;inset:0;border-radius:6px;"></div>
                    </div>
                </div>
            </div>

            {{-- Vaksinasi + Severity --}}
            <div class="row g-3 mb-3">
                <div class="col-md-5">
                    <div class="info-card h-100">
                        <div class="card-header"><i class="fa fa-syringe mr-1"></i> Status Imunisasi</div>
                        <div class="card-body d-flex align-items-center justify-content-center">
                            <div style="height:220px; width:220px; position:relative;">
                                <canvas id="chart-status-vaksinasi"></canvas>
                                <div id="skel-vaksinasi" class="skeleton" style="position:absolute;inset:0;border-radius:50%;"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-7">
                    <div class="info-card h-100">
                        <div class="card-header"><i class="fa fa-heartbeat mr-1"></i> Rawat Inap &amp; Komplikasi</div>
                        <div class="card-body">
                            <div class="row g-2 mb-3">
                                <div class="col-4">
                                    <div class="kinerja-card">
                                        <div class="k-label">Jumlah Dirawat</div>
                                        <div class="k-value" id="sev-jumlah-dirawat"><div class="skeleton skel-value"></div></div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="kinerja-card">
                                        <div class="k-label">% Rawat Inap</div>
                                        <div class="k-value pct" id="sev-rawat-inap"><div class="skeleton skel-value"></div></div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="kinerja-card">
                                        <div class="k-label">Kematian</div>
                                        <div class="k-value danger" id="sev-meninggal"><div class="skeleton skel-value"></div></div>
                                    </div>
                                </div>
                            </div>
                            <div style="height:150px; position:relative;">
                                <canvas id="chart-komplikasi"></canvas>
                                <div id="skel-komplikasi" class="skeleton" style="position:absolute;inset:0;border-radius:6px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>{{-- /tab-demografi --}}

        {{-- ===== TAB 3: TREN ===== --}}
        <div class="tab-pane fade" id="tab-tren" role="tabpanel">

            {{-- Tren Tahunan --}}
            <div class="info-card mb-3">
                <div class="card-header"><i class="fa fa-chart-bar mr-1"></i> Tren Kasus Tahunan (5 Tahun Terakhir)</div>
                <div class="card-body">
                    <div style="height:220px; position:relative;">
                        <canvas id="chart-tahunan"></canvas>
                        <div id="skel-tahunan" class="skeleton" style="position:absolute;inset:0;border-radius:6px;"></div>
                    </div>
                </div>
            </div>

            {{-- Kurva Epidemi (Mingguan) --}}
            <div class="info-card mb-3">
                <div class="card-header"><i class="fa fa-chart-bar mr-1"></i> Kurva Epidemi (Mingguan — Epiweek)</div>
                <div class="card-body">
                    <div style="height:260px; position:relative;">
                        <canvas id="chart-epiweek"></canvas>
                        <div id="skel-epiweek" class="skeleton" style="position:absolute;inset:0;border-radius:6px;"></div>
                    </div>
                </div>
            </div>

            {{-- Tren Bulanan --}}
            <div class="info-card mb-3">
                <div class="card-header"><i class="fa fa-chart-line mr-1"></i> Tren Laporan Bulanan</div>
                <div class="card-body">
                    <div style="height:220px; position:relative;">
                        <canvas id="chart-bulanan"></canvas>
                        <div id="skel-bulanan" class="skeleton" style="position:absolute;inset:0;border-radius:6px;"></div>
                    </div>
                </div>
            </div>

            {{-- Tren per Faskes --}}
            <div class="info-card mb-3">
                <div class="card-header"><i class="fa fa-hospital mr-1"></i> Tren per Faskes Pelapor</div>
                <div class="card-body">
                    <div style="height:240px; position:relative;">
                        <canvas id="chart-per-faskes"></canvas>
                        <div id="skel-per-faskes" class="skeleton" style="position:absolute;inset:0;border-radius:6px;"></div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="info-card">
                        <div class="card-header"><i class="fa fa-map-pin mr-1"></i> Tren per Kecamatan</div>
                        <div class="card-body">
                            <div style="height:220px; position:relative;">
                                <canvas id="chart-per-kecamatan"></canvas>
                                <div id="skel-per-kecamatan" class="skeleton" style="position:absolute;inset:0;border-radius:6px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-card">
                        <div class="card-header"><i class="fa fa-map-marker-alt mr-1"></i> Tren per Kelurahan</div>
                        <div class="card-body">
                            <div style="height:220px; position:relative;">
                                <canvas id="chart-per-kelurahan"></canvas>
                                <div id="skel-per-kelurahan" class="skeleton" style="position:absolute;inset:0;border-radius:6px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>{{-- /tab-tren --}}

        {{-- ===== TAB 4: TEMPAT ===== --}}
        <div class="tab-pane fade" id="tab-tempat" role="tabpanel">

            {{-- Per Wilker Puskesmas --}}
            <div class="info-card mb-3">
                <div class="card-header"><i class="fa fa-clinic-medical mr-1"></i> Per Wilker Puskesmas</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-accessible mb-0">
                            <thead><tr><th>Wilker Puskesmas</th><th class="text-center">Suspek</th><th class="text-center">Confirmed</th><th class="text-center">Meninggal</th></tr></thead>
                            <tbody id="tbody-per-puskesmas"><tr><td colspan="4" class="text-center py-3 text-muted">Memuat data...</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Per Faskes Pelapor --}}
            <div class="info-card mb-3">
                <div class="card-header"><i class="fa fa-hospital mr-1"></i> Per Faskes Pelapor</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-accessible mb-0">
                            <thead><tr><th>Faskes Pelapor</th><th class="text-center">Total</th><th class="text-center">Suspek</th><th class="text-center">Confirmed</th><th class="text-center">Meninggal</th></tr></thead>
                            <tbody id="tbody-per-faskes-pelapor"><tr><td colspan="5" class="text-center py-3 text-muted">Memuat data...</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                {{-- Per Kecamatan --}}
                <div class="col-md-6">
                    <div class="info-card">
                        <div class="card-header"><i class="fa fa-map-pin mr-1"></i> Per Kecamatan</div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-accessible mb-0">
                                    <thead><tr><th>Kecamatan</th><th class="text-center">Suspek</th><th class="text-center">Confirmed</th><th class="text-center">Meninggal</th></tr></thead>
                                    <tbody id="tbody-per-kecamatan"><tr><td colspan="4" class="text-center py-3 text-muted">Memuat data...</td></tr></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                {{-- Per Kelurahan --}}
                <div class="col-md-6">
                    <div class="info-card">
                        <div class="card-header"><i class="fa fa-map-marker-alt mr-1"></i> Per Kelurahan</div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-accessible mb-0">
                                    <thead><tr><th>Kelurahan</th><th>Kecamatan</th><th class="text-center">Suspek</th><th class="text-center">Confirmed</th><th class="text-center">Meninggal</th></tr></thead>
                                    <tbody id="tbody-per-kelurahan"><tr><td colspan="5" class="text-center py-3 text-muted">Memuat data...</td></tr></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Per RT --}}
            <div class="info-card">
                <div class="card-header"><i class="fa fa-home mr-1"></i> Per RT</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-accessible mb-0">
                            <thead><tr><th>RT</th><th>Kelurahan</th><th>Kecamatan</th><th class="text-center">Total</th><th class="text-center">Suspek</th><th class="text-center">Confirmed</th><th class="text-center">Meninggal</th></tr></thead>
                            <tbody id="tbody-per-rt"><tr><td colspan="7" class="text-center py-3 text-muted">Memuat data...</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>{{-- /tab-tempat --}}

        {{-- ===== TAB 5: PETA ===== --}}
        <div class="tab-pane fade" id="tab-peta" role="tabpanel">
            <div class="info-card">
                <div class="card-header"><i class="fa fa-map mr-1"></i> Peta Persebaran Kasus</div>
                <div class="card-body p-0">
                    <div id="map-wilayah" style="height:500px; border-radius:0 0 10px 10px;"></div>
                </div>
            </div>
        </div>{{-- /tab-peta --}}

    </div>{{-- /tab-content --}}

</div>{{-- /container-fluid --}}
@endsection

@section('scripts')
@parent
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    'use strict';

    const API = {
        kinerja:  '{{ route("admin.pd3i.apiKinerja") }}',
        demografi:'{{ route("admin.pd3i.apiDemografi") }}',
        tren:     '{{ route("admin.pd3i.apiTren") }}',
        wilayah:  '{{ route("admin.pd3i.apiWilayah") }}',
        excel:    '{{ route("admin.pd3i.exportExcel") }}',
    };

    const charts = {};
    let leafletMap = null;
    let markersLayer = null;

    const PALETTE = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#f97316','#84cc16','#ec4899','#6366f1'];
    const BULAN_LABELS = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

    // ======= SKELETON =======
    function showSkeletons() {
        document.querySelectorAll('.k-value').forEach(el => {
            el.innerHTML = '<div class="skeleton skel-value"></div>';
        });
        ['cr-discarded-note','afp-npafp-note'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = '';
        });
        ['skel-gender','skel-kelompok-umur','skel-gejala','skel-vaksinasi','skel-komplikasi',
         'skel-tahunan','skel-epiweek','skel-bulanan','skel-per-faskes','skel-per-kecamatan','skel-per-kelurahan'
        ].forEach(id => { const el = document.getElementById(id); if (el) el.style.display = ''; });
    }

    function hideSkel(id) {
        const el = document.getElementById(id);
        if (el) el.style.display = 'none';
    }

    function setVal(id, val) {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
    }

    function esc(str) {
        const d = document.createElement('div');
        d.textContent = str ?? '–';
        return d.innerHTML;
    }

    function destroyChart(key) {
        if (charts[key]) { charts[key].destroy(); delete charts[key]; }
    }

    // ======= PARAMS =======
    function buildParams() {
        const params    = new URLSearchParams();
        const tahun     = document.getElementById('filter-tahun').value;
        const penyakit  = document.getElementById('filter-penyakit').value;
        const wilker    = document.getElementById('filter-wilker').value;
        const kelurahan = document.getElementById('filter-kelurahan').value;

        if (tahun)     params.set('tahun', tahun);
        if (penyakit)  params.set('jenis_kasus_id', penyakit);
        if (wilker)    params.set('wilker', wilker);
        if (kelurahan) params.set('kelurahan_id', kelurahan);

        // PDF hidden inputs
        document.getElementById('pdf_tahun').value = tahun;
        document.getElementById('pdf_jenis_kasus_id').value = penyakit;
        document.getElementById('pdf_wilker').value = wilker;
        document.getElementById('pdf_kelurahan_id').value = kelurahan;

        // Excel href
        const excelBtn = document.getElementById('btnExportExcel');
        if (excelBtn) excelBtn.href = API.excel + '?' + params.toString();

        return params.toString();
    }

    // ======= FETCH ALL =======
    async function fetchAllTabs() {
        showSkeletons();
        const qs = buildParams();

        try {
            const [kinerjaData, demografiData, trenData, wilayahData] = await Promise.all([
                fetch(API.kinerja   + '?' + qs).then(r => r.json()),
                fetch(API.demografi + '?' + qs).then(r => r.json()),
                fetch(API.tren      + '?' + qs).then(r => r.json()),
                fetch(API.wilayah   + '?' + qs).then(r => r.json()),
            ]);
            renderKinerja(kinerjaData);
            renderDemografi(demografiData);
            renderTren(trenData);
            renderTempat(wilayahData);
            renderPeta(wilayahData);
        } catch (err) {
            console.error('Gagal memuat data dashboard:', err);
            renderKinerjaError();
        }
    }

    // ======= RENDER KINERJA =======
    function renderKinerja(data) {
        if (!data) { renderKinerjaError(); return; }

        const cr = data.campak_rubella || {};
        setVal('cr-suspek',       cr.suspek ?? 0);
        setVal('cr-kasus-campak', cr.kasus_campak ?? 0);
        setVal('cr-kasus-rubella',cr.kasus_rubella ?? 0);

        const discardedRate = cr.discarded_rate;
        setVal('cr-discarded', discardedRate !== null && discardedRate !== undefined
            ? discardedRate.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2})
            : '–');
        const crNote = document.getElementById('cr-discarded-note');
        if (crNote) {
            crNote.textContent = discardedRate !== null && discardedRate !== undefined
                ? 'per 100.000 pddk (' + (cr.discarded ?? 0) + ' kasus)'
                : 'Data penduduk belum tersedia (' + (cr.discarded ?? 0) + ' kasus)';
        }

        setVal('cr-kematian',    cr.kematian ?? 0);
        setVal('cr-kasus-aktif', cr.kasus_aktif ?? 0);
        setVal('cr-pct-sampel',  (cr.pct_sampel ?? 0) + '%');
        setVal('cr-pct-lab',     (cr.pct_lab_diterima ?? 0) + '%');
        setVal('cr-positivity',  (cr.positivity_rate ?? 0) + '%');

        const afp = data.afp || {};
        setVal('afp-total',     afp.total ?? 0);
        setVal('afp-confirmed', afp.confirmed ?? 0);
        const npafpRate = afp.npafp_rate;
        setVal('afp-npafp', npafpRate !== null && npafpRate !== undefined
            ? npafpRate.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2})
            : '–');
        const afpNote = document.getElementById('afp-npafp-note');
        if (afpNote) {
            afpNote.textContent = npafpRate !== null && npafpRate !== undefined
                ? 'per 100.000 pddk <15 tahun'
                : 'Data penduduk <15 tahun belum tersedia';
        }

        const difteri = data.difteri || {};
        setVal('difteri-observasi', difteri.observasi ?? 0);
        setVal('difteri-confirmed', difteri.confirmed ?? 0);

        const pertusis = data.pertusis || {};
        setVal('pertusis-suspek', pertusis.suspek ?? 0);
    }

    function renderKinerjaError() {
        ['cr-suspek','cr-kasus-campak','cr-kasus-rubella','cr-discarded','cr-kematian','cr-kasus-aktif',
         'cr-pct-sampel','cr-pct-lab','cr-positivity','afp-total','afp-confirmed','afp-npafp',
         'difteri-observasi','difteri-confirmed','pertusis-suspek'].forEach(id => setVal(id, '–'));
        ['cr-discarded-note','afp-npafp-note'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = '';
        });
    }

    // ======= RENDER DEMOGRAFI =======
    function renderDemografi(data) {
        if (!data) return;

        hideSkel('skel-gender');
        hideSkel('skel-kelompok-umur');
        hideSkel('skel-gejala');
        hideSkel('skel-vaksinasi');
        hideSkel('skel-komplikasi');

        // Jenis Kelamin — pie
        const jk = data.jenis_kelamin || {};
        destroyChart('gender');
        charts.gender = new Chart(document.getElementById('chart-gender'), {
            type: 'pie',
            data: {
                labels: ['Laki-laki', 'Perempuan'],
                datasets: [{
                    data: [jk.L || 0, jk.P || 0],
                    backgroundColor: ['#3b82f6', '#ec4899'],
                }],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } },
            },
        });

        // Kelompok Umur — grouped bar
        const ku = data.kelompok_umur || [];
        destroyChart('kelompokUmur');
        charts.kelompokUmur = new Chart(document.getElementById('chart-kelompok-umur'), {
            type: 'bar',
            data: {
                labels: ku.map(r => r.label),
                datasets: [
                    { label: 'Suspek',    data: ku.map(r => r.suspek),    backgroundColor: 'rgba(59,130,246,0.7)'  },
                    { label: 'Confirmed', data: ku.map(r => r.confirmed), backgroundColor: 'rgba(16,185,129,0.7)'  },
                    { label: 'Discarded', data: ku.map(r => r.discarded), backgroundColor: 'rgba(156,163,175,0.6)' },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: { x: { stacked: false }, y: { beginAtZero: true, ticks: { precision: 0 } } },
            },
        });

        // Gejala — horizontal bar
        const gejala = data.gejala || {};
        const gejalaMap = {
            demam: 'Demam', batuk: 'Batuk', pilek: 'Pilek', sakit_kepala: 'Sakit Kepala',
            mual: 'Mual', muntah: 'Muntah', diare: 'Diare', ruam: 'Ruam',
            sesak_napas: 'Sesak Napas', nyeri_otot: 'Nyeri Otot', nyeri_sendi: 'Nyeri Sendi',
            lemas: 'Lemas', kehilangan_nafsu_makan: 'Tdk Nafsu Makan',
            mata_merah: 'Mata Merah', pembengkakan_kelenjar: 'Pmbkk Kelenjar',
            kejang: 'Kejang', penurunan_kesadaran: 'Penur. Kesadaran',
        };
        const gejalaKeys = Object.keys(gejalaMap);
        const gejalaValues = gejalaKeys.map(k => gejala[k] || 0);
        const gejalaLabels = gejalaKeys.map(k => gejalaMap[k]);

        // Sort descending
        const gejalaIdx = gejalaKeys.map((_, i) => i).sort((a, b) => gejalaValues[b] - gejalaValues[a]);
        const sortedLabels = gejalaIdx.map(i => gejalaLabels[i]);
        const sortedValues = gejalaIdx.map(i => gejalaValues[i]);

        destroyChart('gejala');
        charts.gejala = new Chart(document.getElementById('chart-gejala'), {
            type: 'bar',
            data: {
                labels: sortedLabels,
                datasets: [{ label: 'Jumlah Kasus', data: sortedValues, backgroundColor: 'rgba(245,158,11,0.75)' }],
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true, ticks: { precision: 0 } } },
            },
        });

        // Status Vaksinasi — pie
        const sv = data.status_vaksinasi || {};
        destroyChart('statusVaksinasi');
        charts.statusVaksinasi = new Chart(document.getElementById('chart-status-vaksinasi'), {
            type: 'pie',
            data: {
                labels: ['Tidak Ada', 'Tidak Lengkap', 'Lengkap', 'Tidak Tahu'],
                datasets: [{
                    data: [sv.tidak_ada || 0, sv.tidak_lengkap || 0, sv.lengkap || 0, sv.tidak_tahu || 0],
                    backgroundColor: ['#ef4444','#f59e0b','#10b981','#9ca3af'],
                }],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'right' } },
            },
        });

        // Severity cards
        const sev = data.severity || {};
        setVal('sev-jumlah-dirawat', sev.jumlah_dirawat ?? 0);
        setVal('sev-rawat-inap', (sev.pct_rawat_inap ?? 0) + '%');
        setVal('sev-meninggal',  sev.meninggal ?? 0);

        // Komplikasi — horizontal bar
        const komp = sev.komplikasi || {};
        const kompLabels = ['Diare','Kebutaan','Pneumonia','Malnutrisi','Bronchopneumonia','Otitis Media','Encephalitis','Ulkus Mukosa'];
        const kompData   = [
            komp.diare || 0, komp.kebutaan || 0, komp.pneumonia || 0, komp.malnutrisi || 0,
            komp.bronchopneumonia || 0, komp.otitis_media || 0, komp.encephalitis || 0, komp.ulkus_mukosa_mulut || 0,
        ];
        destroyChart('komplikasi');
        charts.komplikasi = new Chart(document.getElementById('chart-komplikasi'), {
            type: 'bar',
            data: {
                labels: kompLabels,
                datasets: [{ label: 'Kasus', data: kompData, backgroundColor: 'rgba(239,68,68,0.7)' }],
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true, ticks: { precision: 0 } } },
            },
        });
    }

    // ======= RENDER TREN =======
    function renderTren(data) {
        if (!data) return;

        hideSkel('skel-tahunan');
        hideSkel('skel-epiweek');
        hideSkel('skel-bulanan');
        hideSkel('skel-per-faskes');
        hideSkel('skel-per-kecamatan');
        hideSkel('skel-per-kelurahan');

        // Tahunan — grouped bar
        const tah = data.tahunan || [];
        destroyChart('tahunan');
        charts.tahunan = new Chart(document.getElementById('chart-tahunan'), {
            type: 'bar',
            data: {
                labels: tah.map(r => r.tahun),
                datasets: [
                    { label: 'Total Laporan', data: tah.map(r => r.total),     backgroundColor: 'rgba(59,130,246,0.65)'  },
                    { label: 'Confirmed',     data: tah.map(r => r.confirmed), backgroundColor: 'rgba(16,185,129,0.85)'  },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
            },
        });

        // Kurva Epidemi — grouped bar epiweek
        const epi = data.epiweek || [];
        destroyChart('epiweek');
        charts.epiweek = new Chart(document.getElementById('chart-epiweek'), {
            type: 'bar',
            data: {
                labels: epi.map(r => r.week),
                datasets: [
                    { label: 'Suspek',    data: epi.map(r => r.suspek),    backgroundColor: 'rgba(59,130,246,0.6)'  },
                    { label: 'Confirmed', data: epi.map(r => r.confirmed), backgroundColor: 'rgba(16,185,129,0.85)' },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: { x: { ticks: { maxRotation: 60, font: { size: 9 } } }, y: { beginAtZero: true, ticks: { precision: 0 } } },
            },
        });

        // Tren Bulanan — line
        const bul = data.bulanan || [];
        destroyChart('bulanan');
        charts.bulanan = new Chart(document.getElementById('chart-bulanan'), {
            type: 'line',
            data: {
                labels: bul.map(r => r.label),
                datasets: [
                    { label: 'Total Laporan', data: bul.map(r => r.total),     borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.1)', tension: 0.3, fill: true },
                    { label: 'Confirmed',     data: bul.map(r => r.confirmed), borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.1)', tension: 0.3 },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
            },
        });

        destroyChart('perFaskes');
        charts.perFaskes = buildGroupedLineChart('chart-per-faskes', data.per_faskes || [], 'faskes');

        destroyChart('perKecamatan');
        charts.perKecamatan = buildGroupedLineChart('chart-per-kecamatan', data.per_kecamatan || [], 'kecamatan');

        destroyChart('perKelurahan');
        charts.perKelurahan = buildGroupedLineChart('chart-per-kelurahan', data.per_kelurahan || [], 'kelurahan');
    }

    function buildGroupedLineChart(canvasId, rows, groupKey) {
        const groups = {};
        rows.forEach(r => {
            const k = r[groupKey] || 'Tidak Diketahui';
            if (!groups[k]) groups[k] = Array(12).fill(0);
            const idx = r.bulan - 1;
            if (idx >= 0 && idx < 12) groups[k][idx] += r.jumlah;
        });
        const keys = Object.keys(groups);
        const datasets = keys.map((k, i) => ({
            label: k,
            data: groups[k],
            borderColor: PALETTE[i % PALETTE.length],
            backgroundColor: 'transparent',
            tension: 0.3,
            pointRadius: 3,
        }));
        return new Chart(document.getElementById(canvasId), {
            type: 'line',
            data: { labels: BULAN_LABELS, datasets },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'top', labels: { font: { size: 10 } } } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
            },
        });
    }

    // ======= RENDER TEMPAT =======
    function renderTempat(data) {
        if (!data) return;

        const empty4 = '<tr><td colspan="4" class="text-center text-muted py-3">Tidak ada data</td></tr>';
        const empty5 = '<tr><td colspan="5" class="text-center text-muted py-3">Tidak ada data</td></tr>';
        const empty7 = '<tr><td colspan="7" class="text-center text-muted py-3">Tidak ada data</td></tr>';

        // Per Puskesmas
        const tbPusk = document.getElementById('tbody-per-puskesmas');
        if (tbPusk) {
            const rows = data.per_puskesmas || [];
            tbPusk.innerHTML = rows.length === 0 ? empty4 : rows.map(r =>
                `<tr>
                    <td>${esc(r.wilker)}</td>
                    <td class="text-center">${r.suspek}</td>
                    <td class="text-center">${r.confirmed}</td>
                    <td class="text-center${r.meninggal > 0 ? ' text-danger fw-bold' : ''}">${r.meninggal}</td>
                </tr>`
            ).join('');
        }

        // Per Faskes Pelapor
        const tbFaskes = document.getElementById('tbody-per-faskes-pelapor');
        if (tbFaskes) {
            const rows = data.per_faskes_pelapor || [];
            tbFaskes.innerHTML = rows.length === 0 ? empty5 : rows.map(r =>
                `<tr>
                    <td>${esc(r.faskes)}</td>
                    <td class="text-center">${r.total}</td>
                    <td class="text-center">${r.suspek}</td>
                    <td class="text-center">${r.confirmed}</td>
                    <td class="text-center${r.meninggal > 0 ? ' text-danger fw-bold' : ''}">${r.meninggal}</td>
                </tr>`
            ).join('');
        }

        // Per Kecamatan
        const tbKec = document.getElementById('tbody-per-kecamatan');
        if (tbKec) {
            const rows = data.per_kecamatan || [];
            tbKec.innerHTML = rows.length === 0 ? empty4 : rows.map(r =>
                `<tr>
                    <td>${esc(r.kecamatan)}</td>
                    <td class="text-center">${r.suspek}</td>
                    <td class="text-center">${r.confirmed}</td>
                    <td class="text-center${r.meninggal > 0 ? ' text-danger fw-bold' : ''}">${r.meninggal}</td>
                </tr>`
            ).join('');
        }

        // Per Kelurahan
        const tbKel = document.getElementById('tbody-per-kelurahan');
        if (tbKel) {
            const rows = data.per_kelurahan || [];
            tbKel.innerHTML = rows.length === 0 ? empty5 : rows.map(r =>
                `<tr>
                    <td>${esc(r.kelurahan)}</td>
                    <td>${esc(r.kecamatan)}</td>
                    <td class="text-center">${r.suspek}</td>
                    <td class="text-center">${r.confirmed}</td>
                    <td class="text-center${r.meninggal > 0 ? ' text-danger fw-bold' : ''}">${r.meninggal}</td>
                </tr>`
            ).join('');
        }

        // Per RT
        const tbRt = document.getElementById('tbody-per-rt');
        if (tbRt) {
            const rows = data.per_rt || [];
            tbRt.innerHTML = rows.length === 0 ? empty7 : rows.map(r =>
                `<tr>
                    <td>${esc(r.rt)}</td>
                    <td>${esc(r.kelurahan)}</td>
                    <td>${esc(r.kecamatan)}</td>
                    <td class="text-center">${r.total}</td>
                    <td class="text-center">${r.suspek}</td>
                    <td class="text-center">${r.confirmed}</td>
                    <td class="text-center${r.meninggal > 0 ? ' text-danger fw-bold' : ''}">${r.meninggal}</td>
                </tr>`
            ).join('');
        }
    }

    // ======= RENDER PETA =======
    function renderPeta(data) {
        if (!data) return;
        const peta = data.peta || [];

        if (!leafletMap) {
            leafletMap = L.map('map-wilayah').setView([0.1236, 117.4753], 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 18,
            }).addTo(leafletMap);
            markersLayer = L.layerGroup().addTo(leafletMap);
        } else {
            markersLayer.clearLayers();
        }

        peta.forEach(p => {
            const colorMap = { confirmed: '#ef4444', discarded: '#9ca3af', suspected: '#3b82f6' };
            const color = colorMap[p.status] || '#6b7280';
            L.circleMarker([p.lat, p.lng], {
                radius: 7, color, fillColor: color, fillOpacity: 0.75, weight: 2,
            })
            .bindPopup(`<b>${esc(p.nama)}</b><br>${esc(p.penyakit)}<br><em>${p.status}</em>`)
            .addTo(markersLayer);
        });
    }

    // ======= FILTER LISTENERS =======
    ['filter-tahun','filter-penyakit','filter-wilker','filter-kelurahan'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', fetchAllTabs);
    });

    // ======= TAB SHOWN =======
    document.querySelectorAll('[data-toggle="tab"]').forEach(tabEl => {
        tabEl.addEventListener('shown.bs.tab', function () {
            Object.values(charts).forEach(c => { try { c.resize(); } catch(e) {} });
            if (leafletMap) { leafletMap.invalidateSize(); }
        });
    });

    // ======= INITIAL LOAD =======
    document.addEventListener('DOMContentLoaded', function () {
        buildParams();
        fetchAllTabs();
    });

})();
</script>
@endsection

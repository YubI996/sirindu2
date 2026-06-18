@extends('admin::layouts.app')
@section('title') Dashboard Surveilans PD3I @endsection
@section('title-content') Epidemiologi @endsection
@section('item') PD3I @endsection
@section('item-active') Dashboard PD3I @endsection

@section('content')
<style>
@import url('https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;500;600;700&family=Barlow:wght@400;500;600&display=swap');

    @include('admin.epidemiologi.components.shared-styles')

    /* ─── TYPOGRAPHY BASE ─── */
    body, .tab-content, .pd3i-panel-body, .filter-card,
    .kinerja-card, .nav-tabs .nav-link {
        font-family: 'Barlow', Arial, sans-serif;
    }

    /* ─── PAGE HEADER ─── */
    .pd3i-page-title {
        font-family: 'Barlow Condensed', 'Arial Narrow', sans-serif;
        font-size: 1.4rem;
        font-weight: 700;
        letter-spacing: 0.03em;
        color: var(--primary-dark);
        line-height: 1.2;
    }
    .pd3i-page-subtitle {
        font-size: 0.78rem;
        color: var(--text-secondary);
        margin-top: 2px;
    }

    /* ─── FILTER BAR ─── */
    .filter-card {
        background: #fff;
        border: 1px solid oklch(0.88 0.02 145);
        border-top: 3px solid var(--primary);
        border-radius: 0 0 8px 8px;
        padding: 0.875rem 1.25rem;
        margin-bottom: 1.25rem;
    }
    .filter-card label {
        font-family: 'Barlow Condensed', 'Arial Narrow', sans-serif;
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.09em;
        text-transform: uppercase;
        color: var(--text-secondary);
        display: block;
        margin-bottom: 4px;
    }

    /* ─── MULTISELECT DROPDOWN ─── */
    .ms-dropdown { position: relative; }
    .ms-toggle {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 6px;
        width: 100%;
        text-align: left;
        background: #fff;
        cursor: pointer;
    }
    .ms-toggle .ms-label {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .ms-toggle .ms-caret {
        font-size: 0.65rem;
        color: var(--text-secondary);
        flex-shrink: 0;
        transition: transform 0.15s ease;
    }
    .ms-dropdown.open .ms-toggle { border-color: var(--primary); }
    .ms-dropdown.open .ms-toggle .ms-caret { transform: rotate(180deg); }
    .ms-menu {
        position: absolute;
        z-index: 1050;
        top: calc(100% + 4px);
        left: 0;
        right: 0;
        display: none;
        max-height: 280px;
        overflow-y: auto;
        padding: 4px;
        background: #fff;
        border: 1px solid oklch(0.85 0.02 145);
        border-radius: 6px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    }
    .ms-dropdown.open .ms-menu { display: block; }
    .ms-menu .ms-option {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        padding: 6px 8px;
        border-radius: 4px;
        font-family: 'Barlow', Arial, sans-serif;
        font-size: 0.82rem;
        font-weight: 500;
        letter-spacing: normal;
        text-transform: none;
        color: var(--text-primary, #1a2e1a);
        cursor: pointer;
    }
    .ms-menu .ms-option:hover { background: oklch(0.95 0.012 145); }
    .ms-menu .ms-option input {
        flex-shrink: 0;
        width: 15px;
        height: 15px;
        margin: 0;
        accent-color: var(--primary);
        cursor: pointer;
    }
    .ms-menu .ms-empty {
        padding: 8px;
        font-size: 0.78rem;
        color: var(--text-secondary);
        text-align: center;
    }

    /* ─── TABS ─── */
    .nav-tabs {
        border-bottom: 2px solid oklch(0.88 0.02 145);
        gap: 0;
        flex-wrap: nowrap;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }
    .nav-tabs::-webkit-scrollbar { display: none; }
    .nav-tabs .nav-item { flex-shrink: 0; }
    .nav-tabs .nav-link {
        font-family: 'Barlow Condensed', 'Arial Narrow', sans-serif;
        font-size: 0.82rem;
        font-weight: 600;
        letter-spacing: 0.07em;
        text-transform: uppercase;
        padding: 0.6rem 1.1rem 0.5rem;
        color: var(--text-secondary);
        border: none;
        border-radius: 0;
        border-bottom: 2px solid transparent;
        margin-bottom: -2px;
        background: transparent;
        transition: color 0.15s ease, border-color 0.15s ease;
        white-space: nowrap;
    }
    .nav-tabs .nav-link:hover {
        color: var(--primary-dark);
        border-bottom-color: oklch(0.60 0.10 145);
        background: transparent;
    }
    .nav-tabs .nav-link.active {
        color: var(--primary-dark);
        border-bottom-color: var(--primary);
        background: transparent;
        font-weight: 700;
    }
    .tab-content {
        border: 1px solid oklch(0.88 0.02 145);
        border-top: none;
        border-radius: 0 0 8px 8px;
        padding: 1.25rem;
        background: #fff;
    }

    /* ─── DISEASE PANELS ─── */
    .pd3i-panel {
        border-radius: 8px;
        border: 1px solid oklch(0.88 0.02 145);
        overflow: hidden;
        margin-bottom: 1.25rem;
    }
    .pd3i-panel-header {
        padding: 0.65rem 1.25rem;
        font-family: 'Barlow Condensed', 'Arial Narrow', sans-serif;
        font-size: 0.88rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #fff;
        line-height: 1.2;
    }
    .pd3i-panel-body { padding: 1rem; }

    /* Solid brand-derived panel colors — no gradients */
    .panel-campak  { background: #003d1f; }  /* forest — primary disease */
    .panel-afp     { background: #3d1f00; }  /* umber  — warm, alert */
    .panel-difteri { background: #001f3d; }  /* prussian — cool, bacterial */
    .panel-pertusis{ background: #33001a; }  /* wine   — respiratory */

    /* ─── KINERJA CARDS ─── */
    .kinerja-card {
        border-radius: 6px;
        border: 1px solid oklch(0.88 0.02 145);
        padding: 0.75rem 1rem;
        text-align: center;
        background: #fff;
    }
    .kinerja-card .k-label {
        font-family: 'Barlow Condensed', 'Arial Narrow', sans-serif;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.07em;
        text-transform: uppercase;
        color: var(--text-secondary);
        margin-bottom: 3px;
    }
    .kinerja-card .k-value {
        font-family: 'Barlow Condensed', 'Arial Narrow', sans-serif;
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--primary-dark);
        line-height: 1.1;
    }
    .kinerja-card .k-value.pct         { font-size: 1.4rem;  color: var(--primary); }
    .kinerja-card .k-value.danger      { color: var(--danger-rose); }
    .kinerja-card .k-value.active-case { font-size: 1.4rem;  color: var(--warning-amber); }
    .kinerja-card.disabled .k-value    { color: #9ca3af; font-size: 1rem; }
    .kinerja-card.primary .k-value     { font-size: 2.5rem; }
    .kinerja-card.primary .k-label     { font-size: 0.75rem; }

    .pd3i-quality-row {
        padding-top: 0.75rem;
        margin-top: 0.5rem;
        border-top: 1px solid oklch(0.92 0.015 145);
    }
    .pd3i-quality-row .kinerja-card { background: oklch(0.97 0.008 145); }
    .pd3i-quality-row .k-value      { font-size: 1.35rem; }

    /* ─── SKELETON (green-tinted) ─── */
    .skeleton {
        background: linear-gradient(90deg, oklch(0.95 0.012 145) 25%, oklch(0.91 0.018 145) 50%, oklch(0.95 0.012 145) 75%);
        background-size: 200% 100%;
        animation: shimmer 1.4s infinite;
        border-radius: 4px;
    }
    @keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
    .skel-text  { height: 1.4rem; width: 60%; margin: 0.25rem auto; }
    .skel-value { height: 2rem;   width: 50%; margin: 0.15rem auto; }

    /* ─── PETA (choropleth) ─── */
    .peta-toolbar .btn-group .btn {
        font-family: 'Barlow Condensed', 'Arial Narrow', sans-serif;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }
    .peta-legend {
        position: absolute;
        z-index: 1000;
        bottom: 12px;
        right: 12px;
        background: rgba(255, 255, 255, 0.94);
        border: 1px solid oklch(0.88 0.02 145);
        border-radius: 6px;
        padding: 8px 10px;
        font-family: 'Barlow', Arial, sans-serif;
        font-size: 0.72rem;
        color: var(--text-primary, #1a2e1a);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.10);
        line-height: 1.5;
    }
    .peta-legend .legend-title {
        font-family: 'Barlow Condensed', 'Arial Narrow', sans-serif;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        font-size: 0.68rem;
        color: var(--text-secondary);
        margin-bottom: 4px;
    }
    .peta-legend .legend-row { display: flex; align-items: center; gap: 6px; }
    .peta-legend .legend-swatch {
        width: 16px; height: 12px; border-radius: 2px; flex-shrink: 0;
        border: 1px solid rgba(0, 0, 0, 0.12);
    }
    .peta-overlay {
        position: absolute;
        inset: 0;
        z-index: 1100;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.82);
        font-family: 'Barlow', Arial, sans-serif;
        font-size: 0.85rem;
        color: var(--text-secondary);
        text-align: center;
        padding: 1rem;
    }
</style>

<div class="container-fluid" id="main-content">

    {{-- ===== HEADER ===== --}}
    <header class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <div class="pd3i-page-title">
                <i class="fa fa-chart-bar mr-2" aria-hidden="true"></i>Dashboard Surveilans PD3I
            </div>
            <div class="pd3i-page-subtitle">Kota Bontang — Data real-time dari sistem surveilans</div>
        </div>
        <div class="d-flex gap-2 flex-wrap justify-content-end">
            <a href="{{ route('admin.epidemiologi.dashboard') }}" class="btn btn-sm btn-outline-secondary">
                <i class="fa fa-chart-line mr-1"></i> Dashboard Umum
            </a>
            <a href="{{ route('admin.epidemiologi.index') }}" class="btn btn-sm btn-outline-primary">
                <i class="fa fa-list mr-1"></i> Daftar Kasus
            </a>
            <a id="btnExportExcel" href="#" class="btn btn-sm btn-outline-success">
                <i class="fa fa-file-excel mr-1"></i> Excel
            </a>
            <form id="formExportPdf" method="POST" action="{{ route('admin.pd3i.exportPdf') }}" style="display:inline;">
                @csrf
                <input type="hidden" name="tahun" id="pdf_tahun">
                <input type="hidden" name="jenis_kasus_id" id="pdf_jenis_kasus_id">
                <span id="pdf_extra_inputs"></span>
                <button type="submit" class="btn btn-sm btn-danger">
                    <i class="fa fa-file-pdf mr-1"></i> PDF
                </button>
            </form>
        </div>
    </header>

    {{-- ===== FILTER BAR ===== --}}
    <div class="filter-card">
        <div class="row align-items-end g-2">
            <div class="col-6 col-md-2">
                <label for="filter-tahun"><i class="fa fa-calendar-alt mr-1"></i>Tahun</label>
                <select id="filter-tahun" class="form-control">
                    @for($y = now()->year; $y >= now()->year - 5; $y--)
                        <option value="{{ $y }}" {{ $y == now()->year ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label for="filter-penyakit"><i class="fa fa-virus mr-1"></i>Jenis Penyakit</label>
                <select id="filter-penyakit" class="form-control">
                    <option value="">Semua PD3I</option>
                    @foreach($diseases as $d)
                        <option value="{{ $d->id }}">{{ $d->nama_penyakit }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label id="lbl-kabkota"><i class="fa fa-city mr-1"></i>Kota / Kab.</label>
                <div class="ms-dropdown" data-ms="kab_kota" data-all-label="Semua Kota/Kab.">
                    <button type="button" class="form-control ms-toggle" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="lbl-kabkota">
                        <span class="ms-label">Semua Kota/Kab.</span>
                        <i class="fa fa-chevron-down ms-caret" aria-hidden="true"></i>
                    </button>
                    <div class="ms-menu" role="listbox" aria-multiselectable="true">
                        @forelse($kabKotas as $kk)
                            <label class="ms-option"><input type="checkbox" value="{{ $kk }}"><span>{{ $kk }}</span></label>
                        @empty
                            <div class="ms-empty">Tidak ada data</div>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <label id="lbl-wilker"><i class="fa fa-hospital mr-1"></i>Wilker Puskesmas</label>
                <div class="ms-dropdown" data-ms="wilker" data-all-label="Semua Puskesmas">
                    <button type="button" class="form-control ms-toggle" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="lbl-wilker">
                        <span class="ms-label">Semua Puskesmas</span>
                        <i class="fa fa-chevron-down ms-caret" aria-hidden="true"></i>
                    </button>
                    <div class="ms-menu" role="listbox" aria-multiselectable="true">
                        @forelse($wilkers as $w)
                            <label class="ms-option"><input type="checkbox" value="{{ $w }}"><span>{{ $w }}</span></label>
                        @empty
                            <div class="ms-empty">Tidak ada data</div>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <label id="lbl-kelurahan"><i class="fa fa-map-marker-alt mr-1"></i>Kelurahan</label>
                <div class="ms-dropdown" data-ms="kelurahan_id" data-all-label="Semua Kelurahan">
                    <button type="button" class="form-control ms-toggle" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="lbl-kelurahan">
                        <span class="ms-label">Semua Kelurahan</span>
                        <i class="fa fa-chevron-down ms-caret" aria-hidden="true"></i>
                    </button>
                    <div class="ms-menu" role="listbox" aria-multiselectable="true">
                        @forelse($kelurahans as $kel)
                            <label class="ms-option"><input type="checkbox" value="{{ $kel->id }}"><span>{{ $kel->name }}</span></label>
                        @empty
                            <div class="ms-empty">Tidak ada data</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== TABS ===== --}}
    <ul class="nav nav-tabs" id="pd3iTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-kinerja-link"
                    data-toggle="tab" data-target="#tab-kinerja"
                    type="button" role="tab" aria-controls="tab-kinerja" aria-selected="true">
                <i class="fa fa-tachometer-alt mr-1"></i> Kinerja Surveilans
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-demografi-link"
                    data-toggle="tab" data-target="#tab-demografi"
                    type="button" role="tab" aria-controls="tab-demografi" aria-selected="false">
                <i class="fa fa-users mr-1"></i> Demografi
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-tren-link"
                    data-toggle="tab" data-target="#tab-tren"
                    type="button" role="tab" aria-controls="tab-tren" aria-selected="false">
                <i class="fa fa-chart-area mr-1"></i> Tren
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-tempat-link"
                    data-toggle="tab" data-target="#tab-tempat"
                    type="button" role="tab" aria-controls="tab-tempat" aria-selected="false">
                <i class="fa fa-map-marked-alt mr-1"></i> Tempat
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-peta-link"
                    data-toggle="tab" data-target="#tab-peta"
                    type="button" role="tab" aria-controls="tab-peta" aria-selected="false">
                <i class="fa fa-map mr-1"></i> Peta
            </button>
        </li>
    </ul>

    <div class="tab-content" id="pd3iTabContent">

        {{-- ===== TAB 1: KINERJA SURVEILANS ===== --}}
        <div class="tab-pane fade show active" id="tab-kinerja" role="tabpanel" aria-labelledby="tab-kinerja-link">

            {{-- PANEL: Campak-Rubella --}}
            <div class="pd3i-panel">
                <div class="pd3i-panel-header panel-campak">
                    <i class="fa fa-circle mr-1" style="font-size:.6rem;"></i> Campak-Rubella
                </div>
                <div class="pd3i-panel-body">
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
                                <div id="cr-discarded-note" style="font-size:0.65rem; color:var(--text-secondary); margin-top:2px;"></div>
                            </div>
                        </div>
                    </div>
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
                                <div id="afp-npafp-note" style="font-size:0.65rem; color:var(--text-secondary); margin-top:2px;"></div>
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
        <div class="tab-pane fade" id="tab-demografi" role="tabpanel" aria-labelledby="tab-demografi-link">

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

            <div class="info-card mb-3">
                <div class="card-header"><i class="fa fa-thermometer-half mr-1"></i> Distribusi Gejala</div>
                <div class="card-body">
                    <div style="height:240px; position:relative;">
                        <canvas id="chart-gejala"></canvas>
                        <div id="skel-gejala" class="skeleton" style="position:absolute;inset:0;border-radius:6px;"></div>
                    </div>
                </div>
            </div>

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
        <div class="tab-pane fade" id="tab-tren" role="tabpanel" aria-labelledby="tab-tren-link">

            <div class="info-card mb-3">
                <div class="card-header"><i class="fa fa-chart-bar mr-1"></i> Tren Kasus Tahunan (5 Tahun Terakhir)</div>
                <div class="card-body">
                    <div style="height:220px; position:relative;">
                        <canvas id="chart-tahunan"></canvas>
                        <div id="skel-tahunan" class="skeleton" style="position:absolute;inset:0;border-radius:6px;"></div>
                    </div>
                </div>
            </div>

            <div class="info-card mb-3">
                <div class="card-header"><i class="fa fa-chart-bar mr-1"></i> Kurva Epidemi (Mingguan — Epiweek)</div>
                <div class="card-body">
                    <div style="height:260px; position:relative;">
                        <canvas id="chart-epiweek"></canvas>
                        <div id="skel-epiweek" class="skeleton" style="position:absolute;inset:0;border-radius:6px;"></div>
                    </div>
                </div>
            </div>

            <div class="info-card mb-3">
                <div class="card-header"><i class="fa fa-chart-line mr-1"></i> Tren Laporan Bulanan</div>
                <div class="card-body">
                    <div style="height:220px; position:relative;">
                        <canvas id="chart-bulanan"></canvas>
                        <div id="skel-bulanan" class="skeleton" style="position:absolute;inset:0;border-radius:6px;"></div>
                    </div>
                </div>
            </div>

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
        <div class="tab-pane fade" id="tab-tempat" role="tabpanel" aria-labelledby="tab-tempat-link">

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
        <div class="tab-pane fade" id="tab-peta" role="tabpanel" aria-labelledby="tab-peta-link">
            <div class="info-card">
                <div class="card-header peta-toolbar d-flex justify-content-between align-items-center flex-wrap" style="gap:.5rem;">
                    <span><i class="fa fa-map mr-1"></i> Peta Kepadatan Kasus per Wilayah</span>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Pilih tingkat wilayah peta">
                        <button type="button" class="btn btn-outline-primary" id="peta-btn-kecamatan" data-peta-layer="kecamatan">
                            <i class="fa fa-city mr-1"></i> Kecamatan
                        </button>
                        <button type="button" class="btn btn-primary" id="peta-btn-kelurahan" data-peta-layer="kelurahan">
                            <i class="fa fa-map mr-1"></i> Kelurahan
                        </button>
                    </div>
                </div>
                <div class="card-body p-0" style="position:relative;">
                    <div id="map-wilayah" style="height:500px; border-radius:0 0 10px 10px;"></div>
                    <div id="peta-legend" class="peta-legend" style="display:none;">
                        <div class="legend-title">Jumlah Kasus (Suspek + Confirmed)</div>
                        <div class="legend-row"><span class="legend-swatch" style="background:#7f1d1d;"></span> &gt; 50</div>
                        <div class="legend-row"><span class="legend-swatch" style="background:#b91c1c;"></span> 21 – 50</div>
                        <div class="legend-row"><span class="legend-swatch" style="background:#e08a00;"></span> 11 – 20</div>
                        <div class="legend-row"><span class="legend-swatch" style="background:#00A651;"></span> 1 – 10</div>
                        <div class="legend-row"><span class="legend-swatch" style="background:#e5e7eb;"></span> 0</div>
                    </div>
                    <div id="peta-overlay" class="peta-overlay">Memuat peta…</div>
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

    // ── Peta state ──
    let leafletMap     = null;   // Leaflet map instance (created on first peta tab show)
    let petaGeoLayer   = null;   // current choropleth GeoJSON layer on the map
    let petaWilayahData= null;   // last wilayah payload from API (per_kecamatan / per_kelurahan)
    let petaLayerType  = 'kelurahan';
    let petaMapping    = null;   // mapping.json (name normalisation)
    const petaGeoCache = { kecamatan: null, kelurahan: null };
    const PETA_GEOJSON = {
        kecamatan: '/geojson/Kota Bontang-KECAMATAN.geojson',
        kelurahan: '/geojson/Kota Bontang-KEL_DESA.geojson',
    };

    const PALETTE = [
        '#00A651','#c68200','#b91c1c','#0f4c81','#7d1847',
        '#2e8b57','#cf6f00','#991b1b','#1a4a7a','#6b1a3f',
    ];
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
        const kabKota   = getMsValues('kab_kota');
        const wilker    = getMsValues('wilker');
        const kelurahan = getMsValues('kelurahan_id');

        if (tahun)    params.set('tahun', tahun);
        if (penyakit) params.set('jenis_kasus_id', penyakit);
        kabKota.forEach(v   => params.append('kab_kota[]', v));
        wilker.forEach(v    => params.append('wilker[]', v));
        kelurahan.forEach(v => params.append('kelurahan_id[]', v));

        // PDF hidden inputs — scalars + one hidden field per selected multiselect value
        document.getElementById('pdf_tahun').value = tahun;
        document.getElementById('pdf_jenis_kasus_id').value = penyakit;
        const extra = document.getElementById('pdf_extra_inputs');
        if (extra) {
            extra.innerHTML = '';
            const addHidden = (name, value) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                extra.appendChild(input);
            };
            kabKota.forEach(v   => addHidden('kab_kota[]', v));
            wilker.forEach(v    => addHidden('wilker[]', v));
            kelurahan.forEach(v => addHidden('kelurahan_id[]', v));
        }

        // Excel href
        const excelBtn = document.getElementById('btnExportExcel');
        if (excelBtn) excelBtn.href = API.excel + '?' + params.toString();

        return params.toString();
    }

    // ======= MULTISELECT DROPDOWNS =======
    function getMsValues(key) {
        const dd = document.querySelector('.ms-dropdown[data-ms="' + key + '"]');
        if (!dd) return [];
        return Array.from(dd.querySelectorAll('.ms-option input:checked')).map(i => i.value);
    }

    function closeAllMs() {
        document.querySelectorAll('.ms-dropdown.open').forEach(d => {
            d.classList.remove('open');
            const btn = d.querySelector('.ms-toggle');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        });
    }

    function updateMsLabel(dd) {
        const labelEl  = dd.querySelector('.ms-label');
        const allLabel = dd.dataset.allLabel || 'Semua';
        const checked  = dd.querySelectorAll('.ms-option input:checked');
        if (checked.length === 0) {
            labelEl.textContent = allLabel;
        } else if (checked.length === 1) {
            labelEl.textContent = checked[0].parentElement.querySelector('span').textContent;
        } else {
            labelEl.textContent = checked.length + ' dipilih';
        }
    }

    function initMultiselects() {
        document.querySelectorAll('.ms-dropdown').forEach(dd => {
            const toggle = dd.querySelector('.ms-toggle');
            const menu   = dd.querySelector('.ms-menu');

            toggle.addEventListener('click', function (e) {
                e.stopPropagation();
                const willOpen = !dd.classList.contains('open');
                closeAllMs();
                if (willOpen) {
                    dd.classList.add('open');
                    toggle.setAttribute('aria-expanded', 'true');
                }
            });

            menu.addEventListener('click', e => e.stopPropagation());
            menu.addEventListener('change', function () {
                updateMsLabel(dd);
                fetchAllTabs();
            });

            updateMsLabel(dd);
        });

        document.addEventListener('click', closeAllMs);
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
                    backgroundColor: ['#003d1f', '#c68200'],
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
                    { label: 'Suspek',    data: ku.map(r => r.suspek),    backgroundColor: 'rgba(198,130,0,0.70)'  },
                    { label: 'Confirmed', data: ku.map(r => r.confirmed), backgroundColor: 'rgba(0,166,81,0.80)'   },
                    { label: 'Discarded', data: ku.map(r => r.discarded), backgroundColor: 'rgba(110,130,110,0.55)' },
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
        const gejalaKeys   = Object.keys(gejalaMap);
        const gejalaValues = gejalaKeys.map(k => gejala[k] || 0);
        const gejalaLabels = gejalaKeys.map(k => gejalaMap[k]);

        const gejalaIdx    = gejalaKeys.map((_, i) => i).sort((a, b) => gejalaValues[b] - gejalaValues[a]);
        const sortedLabels = gejalaIdx.map(i => gejalaLabels[i]);
        const sortedValues = gejalaIdx.map(i => gejalaValues[i]);

        destroyChart('gejala');
        charts.gejala = new Chart(document.getElementById('chart-gejala'), {
            type: 'bar',
            data: {
                labels: sortedLabels,
                datasets: [{ label: 'Jumlah Kasus', data: sortedValues, backgroundColor: 'rgba(198,130,0,0.75)' }],
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
                    backgroundColor: ['#b91c1c', '#c68200', '#00A651', '#8a9e8a'],
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
        const komp      = sev.komplikasi || {};
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
                datasets: [{ label: 'Kasus', data: kompData, backgroundColor: 'rgba(185,28,28,0.72)' }],
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
                    { label: 'Total Laporan', data: tah.map(r => r.total),     backgroundColor: 'rgba(198,130,0,0.65)'  },
                    { label: 'Confirmed',     data: tah.map(r => r.confirmed), backgroundColor: 'rgba(0,166,81,0.85)'   },
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
                    { label: 'Suspek',    data: epi.map(r => r.suspek),    backgroundColor: 'rgba(198,130,0,0.62)'  },
                    { label: 'Confirmed', data: epi.map(r => r.confirmed), backgroundColor: 'rgba(0,166,81,0.85)'  },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: {
                    x: { ticks: { maxRotation: 60, font: { size: 9 } } },
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                },
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
                    { label: 'Total Laporan', data: bul.map(r => r.total),     borderColor: '#c68200', backgroundColor: 'rgba(198,130,0,0.10)', tension: 0.3, fill: true  },
                    { label: 'Confirmed',     data: bul.map(r => r.confirmed), borderColor: '#00A651', backgroundColor: 'rgba(0,166,81,0.10)',  tension: 0.3 },
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
        const keys     = Object.keys(groups);
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

    // ======= RENDER PETA (choropleth per wilayah) =======
    // Kasus surveilans tidak menyimpan koordinat titik (lat/lng kosong), jadi
    // peta digambar sebagai choropleth: tiap poligon kecamatan/kelurahan
    // diwarnai menurut jumlah kasus (suspek + confirmed) dari agregat API
    // `per_kecamatan` / `per_kelurahan`. Mengikuti pola peta statistik
    // (epidemiologi/map) yang sudah terbukti jalan.

    function renderPeta(data) {
        petaWilayahData = data || null;
        // Hanya render bila map sudah dibuat (tab Peta pernah dibuka). Saat tab
        // dibuka, handler shown.bs.tab memanggil petaActivate() untuk render.
        if (leafletMap) petaRenderLayer();
    }

    function petaColor(count) {
        return count > 50 ? '#7f1d1d' :
               count > 20 ? '#b91c1c' :
               count > 10 ? '#e08a00' :
               count > 0  ? '#00A651' : '#e5e7eb';
    }

    function petaCountOf(row) {
        return row ? ((row.suspek || 0) + (row.confirmed || 0)) : 0;
    }

    function petaFeatureName(feature) {
        const p = feature.properties || {};
        return p.Name || p.nama || p.kel_desa || p.NAME || 'Tidak Diketahui';
    }

    // Normalkan nama fitur GeoJSON kelurahan ke nama di database via mapping.json.
    function petaResolveKelName(name) {
        if (!name) return '';
        let n = name;
        const norm = petaMapping && petaMapping.normalisasi;
        if (norm) {
            if (norm[n]) n = norm[n];
            else {
                const low = n.toLowerCase();
                for (const k in norm) if (k.toLowerCase() === low) { n = norm[k]; break; }
            }
        }
        const kel = petaMapping && petaMapping.kelurahan;
        if (kel) {
            if (kel[n]) return kel[n];
            const low = n.toLowerCase();
            for (const k in kel) if (k.toLowerCase() === low) return kel[k];
        }
        return n;
    }

    // Agregat kasus untuk satu poligon wilayah. Sebuah nama kelurahan bisa
    // muncul di >1 baris (kasus terdaftar di kecamatan berbeda akibat data
    // tidak konsisten), jadi semua baris yang cocok dijumlahkan. Exact match
    // diutamakan agar "Tanjung Laut Indah" tidak ikut terhitung di "Tanjung
    // Laut"; pencocokan toleran hanya dipakai bila tak ada yang persis.
    function petaLookup(featureName) {
        const rows = petaLayerType === 'kecamatan'
            ? (petaWilayahData && petaWilayahData.per_kecamatan) || []
            : (petaWilayahData && petaWilayahData.per_kelurahan) || [];
        const key = petaLayerType === 'kecamatan' ? 'kecamatan' : 'kelurahan';

        let target = featureName || '';
        if (petaLayerType === 'kecamatan') target = target.replace(/^Kecamatan\s+/i, '');
        else                               target = petaResolveKelName(target);
        const low = target.toLowerCase();

        let matched = rows.filter(r => (r[key] || '').toLowerCase() === low);
        if (matched.length === 0) {
            matched = rows.filter(r => {
                const nm = (r[key] || '').toLowerCase();
                return nm && low && (nm.includes(low) || low.includes(nm));
            });
        }
        if (matched.length === 0) return null;

        return matched.reduce((a, r) => ({
            suspek:    a.suspek    + (r.suspek    || 0),
            confirmed: a.confirmed + (r.confirmed || 0),
            meninggal: a.meninggal + (r.meninggal || 0),
        }), { suspek: 0, confirmed: 0, meninggal: 0 });
    }

    function petaPopup(feature) {
        const name    = petaFeatureName(feature);
        const display = petaLayerType === 'kecamatan' ? name.replace(/^Kecamatan\s+/i, '') : name;
        const row     = petaLookup(name);
        const total   = petaCountOf(row);
        let html = '<div style="min-width:170px;"><b>' + esc(display) + '</b>';
        if (row && total > 0) {
            html += '<hr style="margin:6px 0;">'
                 +  '<div>Suspek: <b>' + (row.suspek || 0) + '</b></div>'
                 +  '<div>Confirmed: <b>' + (row.confirmed || 0) + '</b></div>'
                 +  '<div>Meninggal: <b' + (row.meninggal > 0 ? ' style="color:#b91c1c;"' : '') + '>' + (row.meninggal || 0) + '</b></div>'
                 +  '<div style="margin-top:4px; border-top:1px solid #eee; padding-top:4px;">Total: <b>' + total + '</b> kasus</div>';
        } else {
            html += '<br><span style="color:#6b7280;">Tidak ada kasus</span>';
        }
        return html + '</div>';
    }

    function petaStyle(feature) {
        const row = petaLookup(petaFeatureName(feature));
        return {
            fillColor: petaColor(petaCountOf(row)),
            weight: petaLayerType === 'kecamatan' ? 3 : 2,
            color: '#ffffff',
            opacity: 1,
            fillOpacity: 0.72,
        };
    }

    // Dipanggil saat tab Peta ditampilkan: buat map (sekali), muat mapping.json,
    // lalu render layer aktif.
    async function petaActivate() {
        const overlay = document.getElementById('peta-overlay');
        try {
            if (!leafletMap) {
                leafletMap = L.map('map-wilayah').setView([0.1236, 117.4753], 12);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors',
                    maxZoom: 18,
                }).addTo(leafletMap);
            }
            leafletMap.invalidateSize();

            if (petaMapping === null) {
                petaMapping = await fetch('/geojson/mapping.json').then(r => r.json()).catch(() => ({}));
            }
            await petaRenderLayer();
        } catch (e) {
            console.error('Gagal menampilkan peta:', e);
            if (overlay) { overlay.textContent = 'Gagal memuat peta.'; overlay.style.display = 'flex'; }
        }
    }

    async function petaRenderLayer() {
        const overlay = document.getElementById('peta-overlay');
        const legend  = document.getElementById('peta-legend');
        if (!leafletMap) return;

        // Lazy-load GeoJSON untuk layer aktif
        if (!petaGeoCache[petaLayerType]) {
            if (overlay) { overlay.textContent = 'Memuat peta…'; overlay.style.display = 'flex'; }
            try {
                petaGeoCache[petaLayerType] = await fetch(PETA_GEOJSON[petaLayerType]).then(r => {
                    if (!r.ok) throw new Error('GeoJSON tidak tersedia');
                    return r.json();
                });
            } catch (e) {
                if (overlay) { overlay.textContent = 'Data batas wilayah tidak tersedia.'; overlay.style.display = 'flex'; }
                return;
            }
        }

        if (petaGeoLayer) { leafletMap.removeLayer(petaGeoLayer); petaGeoLayer = null; }

        petaGeoLayer = L.geoJSON(petaGeoCache[petaLayerType], {
            style: petaStyle,
            onEachFeature: function (feature, layer) {
                layer.bindPopup(petaPopup(feature));
                layer.on({
                    mouseover: e => { e.target.setStyle({ weight: 4, color: '#3d3d3d', fillOpacity: 0.88 }); e.target.bringToFront(); },
                    mouseout:  e => { if (petaGeoLayer) petaGeoLayer.resetStyle(e.target); },
                });
            },
        }).addTo(leafletMap);

        try { leafletMap.fitBounds(petaGeoLayer.getBounds(), { padding: [12, 12] }); } catch (e) {}

        if (overlay) overlay.style.display = 'none';
        if (legend)  legend.style.display = 'block';
    }

    function petaSwitchLayer(type) {
        if (type === petaLayerType || !PETA_GEOJSON[type]) return;
        petaLayerType = type;
        document.querySelectorAll('[data-peta-layer]').forEach(btn => {
            const active = btn.dataset.petaLayer === type;
            btn.classList.toggle('btn-primary', active);
            btn.classList.toggle('btn-outline-primary', !active);
        });
        petaRenderLayer();
    }

    // ======= FILTER LISTENERS =======
    // Multiselect dropdowns (kab_kota / wilker / kelurahan) trigger fetch via their own change handlers.
    ['filter-tahun','filter-penyakit'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', fetchAllTabs);
    });

    // ======= TAB SHOWN — resize charts & invalidate map =======
    // Bootstrap 4 memicu event 'shown.bs.tab' via jQuery, bukan native event,
    // jadi handler harus pakai jQuery .on() (addEventListener tak menangkapnya).
    $('[data-toggle="tab"]').on('shown.bs.tab', function () {
        Object.values(charts).forEach(c => { try { c.resize(); } catch(e) {} });
        if (this.dataset && this.dataset.target === '#tab-peta') {
            petaActivate();                       // init map + render choropleth (lazy)
        } else if (leafletMap) {
            leafletMap.invalidateSize();
        }
    });

    // ======= INITIAL LOAD =======
    document.addEventListener('DOMContentLoaded', function () {
        initMultiselects();
        document.querySelectorAll('[data-peta-layer]').forEach(btn => {
            btn.addEventListener('click', () => petaSwitchLayer(btn.dataset.petaLayer));
        });
        buildParams();
        fetchAllTabs();
    });

})();
</script>
@endsection

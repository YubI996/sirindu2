@extends('admin::layouts.app')
@section('title')
Proyeksi - Si Rindu
@endsection
@section('title-content')
Proyeksi & Early Warning System
@endsection
@section('item')
Dashboard
@endsection
@section('item-active')
Proyeksi
@endsection

@section('content')
{{-- Skip Link for Accessibility --}}
<a href="#main-content" class="sr-only sr-only-focusable skip-link">Langsung ke konten utama</a>
<style>
    :root {
        --primary-blue: #0066cc;
        --primary-blue-dark: #004d99;
        --primary-blue-light: #e6f2ff;
        --success-green: #047857;
        --success-green-dark: #065f46;
        --success-green-light: #d1fae5;
        --warning-amber: #b45309;
        --danger-rose: #be123c;
        --info-teal: #0891b2;
        --text-muted: #4b5563;
        --text-secondary: #6b7280;
    }

    /* Skip Link */
    .skip-link {
        position: absolute;
        top: -40px;
        left: 0;
        background: var(--primary-blue-dark);
        color: #fff;
        padding: 8px 16px;
        z-index: 9999;
        text-decoration: none;
    }
    .skip-link:focus {
        top: 0;
    }

    /* Enhanced Focus Indicators */
    a:focus,
    button:focus,
    .btn:focus,
    input:focus,
    select:focus,
    textarea:focus,
    [tabindex]:focus {
        outline: 3px solid var(--primary-blue) !important;
        outline-offset: 2px !important;
        box-shadow: 0 0 0 4px rgba(0, 102, 204, 0.25) !important;
    }

    .text-accessible-muted {
        color: var(--text-muted) !important;
    }

    .text-muted {
        color: var(--text-muted) !important;
    }

    @media (prefers-reduced-motion: reduce) {
        * {
            scroll-behavior: auto !important;
        }
        .alert-card,
        .child-card,
        .stat-box,
        .stat-card,
        .vaccine-card,
        .chart-card {
            transition: none !important;
        }
    }

    .alert-card {
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        margin-bottom: 1.5rem;
        overflow: hidden;
    }

    .alert-card-header {
        padding: 1rem 1.5rem;
        color: #fff;
        font-weight: 600;
    }

    .alert-card-header.high { background: linear-gradient(135deg, #be123c 0%, #f43f5e 100%); }
    .alert-card-header.medium { background: linear-gradient(135deg, #b45309 0%, #f59e0b 100%); }
    .alert-card-header.low { background: linear-gradient(135deg, #0891b2 0%, #06b6d4 100%); }

    .alert-card-body {
        padding: 1.5rem;
        background: #fff;
    }

    .stat-box {
        background: #fff;
        border-radius: 12px;
        padding: 1.25rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        text-align: center;
        border-left: 4px solid #e5e7eb;
    }

    .stat-box.danger { border-left-color: #be123c; }
    .stat-box.warning { border-left-color: #b45309; }
    .stat-box.info { border-left-color: #0891b2; }
    .stat-box.success { border-left-color: #047857; }

    .stat-box h2 { font-size: 2rem; font-weight: 700; margin: 0; }
    .stat-box p { color: var(--text-muted); font-size: 0.875rem; margin: 0; }

    .risk-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .risk-badge.high { background: #fee2e2; color: #be123c; }
    .risk-badge.medium { background: #fef3c7; color: #b45309; }
    .risk-badge.low { background: #e0f2fe; color: #0891b2; }

    .alert-item {
        display: flex;
        align-items: center;
        padding: 0.5rem 0.75rem;
        margin-bottom: 0.5rem;
        border-radius: 8px;
        font-size: 0.85rem;
    }

    .alert-item.danger { background: #fee2e2; color: #be123c; }
    .alert-item.warning { background: #fef3c7; color: #b45309; }
    .alert-item.info { background: #e0f2fe; color: #0891b2; }

    .alert-item i { margin-right: 0.5rem; width: 16px; }

    .child-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        margin-bottom: 1rem;
        overflow: hidden;
        transition: transform 0.2s;
    }

    .child-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
    }

    .child-card-header {
        padding: 1rem 1.25rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #f3f4f6;
    }

    .child-card-header.high { border-left: 4px solid #be123c; }
    .child-card-header.medium { border-left: 4px solid #b45309; }
    .child-card-header.low { border-left: 4px solid #0891b2; }

    .child-card-body {
        padding: 1rem 1.25rem;
    }

    .child-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .child-info-item {
        display: flex;
        flex-direction: column;
    }

    .child-info-item label {
        font-size: 0.75rem;
        color: var(--text-muted);
        margin-bottom: 0.25rem;
    }

    .child-info-item span {
        font-weight: 600;
        color: #1f2937;
    }

    .filter-bar {
        background: #fff;
        border-radius: 12px;
        padding: 1rem 1.5rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        margin-bottom: 1.5rem;
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: center;
    }

    .filter-btn {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        border: 2px solid #e5e7eb;
        background: #fff;
        color: var(--text-muted);
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.2s;
    }

    .filter-btn:hover { border-color: var(--primary-blue); color: var(--primary-blue); }
    .filter-btn.active { background: var(--primary-blue); color: #fff; border-color: var(--primary-blue); }

    .section-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
    }

    .section-title i { margin-right: 0.5rem; color: var(--primary-blue); }

    .empty-state {
        text-align: center;
        padding: 3rem;
        color: var(--text-muted);
    }

    .empty-state i {
        font-size: 4rem;
        color: #d1d5db;
        margin-bottom: 1rem;
    }

    .progress-ring {
        width: 80px;
        height: 80px;
    }

    .action-btn {
        padding: 0.4rem 0.8rem;
        border-radius: 6px;
        font-size: 0.8rem;
        margin-right: 0.5rem;
    }

    .pagination-wrapper {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        margin-top: 1.5rem;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .pagination-info {
        color: var(--text-muted);
        font-size: 0.9rem;
    }

    .pagination-controls {
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }

    .page-btn {
        padding: 0.5rem 1rem;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        background: #fff;
        color: #1f2937;
        cursor: pointer;
        transition: all 0.2s;
    }

    .page-btn:hover:not(:disabled) {
        background: var(--primary-blue);
        color: #fff;
        border-color: var(--primary-blue);
    }

    .page-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .page-btn.active {
        background: var(--primary-blue);
        color: #fff;
        border-color: var(--primary-blue);
    }

    .per-page-select {
        padding: 0.5rem 0.75rem;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        font-size: 0.85rem;
    }

    .vaccine-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        margin-bottom: 1.5rem;
        overflow: hidden;
    }

    .vaccine-card-header {
        padding: 1rem 1.5rem;
        background: linear-gradient(135deg, var(--info-teal) 0%, #06b6d4 100%);
        color: #fff;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .vaccine-card-body {
        padding: 1.5rem;
    }

    .vaccine-table {
        width: 100%;
        border-collapse: collapse;
    }

    .vaccine-table th,
    .vaccine-table td {
        padding: 0.75rem;
        text-align: left;
        border-bottom: 1px solid #f3f4f6;
    }

    .vaccine-table th {
        background: var(--primary-blue-light);
        font-weight: 600;
        font-size: 0.85rem;
        color: var(--primary-blue-dark);
    }

    .vaccine-table tr:hover {
        background: #f9fafb;
    }

    .vaccine-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 32px;
        height: 32px;
        padding: 0 0.5rem;
        background: var(--danger-rose);
        color: #fff;
        border-radius: 8px;
        font-weight: 700;
        font-size: 0.9rem;
    }

    .vaccine-count.medium {
        background: var(--warning-amber);
    }

    .vaccine-count.low {
        background: var(--info-teal);
    }

    .location-tag {
        display: inline-block;
        padding: 0.25rem 0.5rem;
        background: #e0f2fe;
        color: #0891b2;
        border-radius: 4px;
        font-size: 0.75rem;
        margin: 0.125rem;
    }

    .tab-container {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1rem;
        border-bottom: 2px solid #e5e7eb;
        padding-bottom: 0;
    }

    .tab-btn {
        padding: 0.75rem 1.5rem;
        border: none;
        background: transparent;
        color: var(--text-muted);
        font-weight: 500;
        cursor: pointer;
        border-bottom: 3px solid transparent;
        margin-bottom: -2px;
        transition: all 0.2s;
    }

    .tab-btn:hover {
        color: var(--primary-blue);
    }

    .tab-btn.active {
        color: var(--primary-blue);
        border-bottom-color: var(--primary-blue);
    }

    .badge-light {
        background: var(--primary-blue-light);
        color: var(--primary-blue-dark);
        border: 1px solid rgba(0, 77, 153, 0.15);
    }

    .badge-secondary {
        background: var(--text-muted);
        color: #ffffff;
    }

    .alert-info {
        background: #e0f2fe;
        color: #0f172a;
        border-color: #bae6fd;
    }

    /* Screen reader only class */
    .sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }

    .sr-only-focusable:focus {
        position: static;
        width: auto;
        height: auto;
        padding: inherit;
        margin: inherit;
        overflow: visible;
        clip: auto;
        white-space: normal;
    }

    a:not(.btn) {
        color: var(--primary-blue);
    }

    a:not(.btn):hover {
        color: var(--primary-blue-dark);
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    .projection-tab-content {
        display: none;
    }

    .projection-tab-content.active {
        display: block;
    }
</style>

<main id="main-content" role="main" aria-label="Proyeksi & Early Warning System">
{{-- Summary Statistics --}}
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stat-box danger">
            <h2>{{ $summary['high_risk'] }}</h2>
            <p><i class="fa fa-exclamation-triangle mr-1"></i> Risiko Tinggi</p>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stat-box warning">
            <h2>{{ $summary['medium_risk'] }}</h2>
            <p><i class="fa fa-exclamation-circle mr-1"></i> Risiko Sedang</p>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stat-box info">
            <h2>{{ $summary['low_risk'] }}</h2>
            <p><i class="fa fa-info-circle mr-1"></i> Risiko Rendah</p>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stat-box success" data-toggle="tooltip" data-placement="top" title="Anak dengan pemantauan normal (tidak ada alert terdeteksi)">
            <h2>{{ $summary['total_children'] - $summary['children_with_alerts'] }}</h2>
            <p><i class="fa fa-check-circle mr-1"></i> Monitoring Normal</p>
        </div>
    </div>
</div>

{{-- Issue Categories --}}
<div class="row mb-4">
    <div class="col-md-3 col-6 mb-3">
        <div class="stat-box">
            <h2 class="text-danger">{{ $summary['stunting_cases'] }}</h2>
            <p><i class="fa fa-child mr-1"></i> Stunting</p>
        </div>
    </div>
    <div class="col-md-3 col-6 mb-3">
        <div class="stat-box">
            <h2 class="text-warning">{{ $summary['wasting_cases'] }}</h2>
            <p><i class="fa fa-weight mr-1"></i> Wasting</p>
        </div>
    </div>
    <div class="col-md-3 col-6 mb-3">
        <div class="stat-box">
            <h2 class="text-info">{{ $summary['incomplete_immunization'] }}</h2>
            <p><i class="fa fa-syringe mr-1"></i> Imunisasi Belum Lengkap</p>
        </div>
    </div>
    <div class="col-md-3 col-6 mb-3">
        <div class="stat-box">
            <h2 class="text-secondary">{{ $summary['no_measurement'] }}</h2>
            <p><i class="fa fa-ruler mr-1"></i> Belum Pernah Diukur</p>
        </div>
    </div>
</div>

{{-- Kebutuhan Vaksin Section --}}
<div class="vaccine-card mb-4">
    <div class="vaccine-card-header">
        <div>
            <i class="fa fa-syringe mr-2"></i> Proyeksi Kebutuhan Vaksin
        </div>
        <a href="{{ route('admin.exportVaccineNeeds') }}" class="btn btn-sm btn-light">
            <i class="fa fa-file-excel mr-1"></i> Export Excel
        </a>
    </div>
    <div class="vaccine-card-body">
        <div class="tab-container" role="tablist" aria-label="Periode proyeksi kebutuhan vaksin">
            <button class="tab-btn active" id="tabBtn1month" role="tab" aria-selected="true" aria-controls="tab1month" onclick="showProjectionTab('1month', this)" type="button">
                1 Bulan Ke Depan
                <span class="badge badge-light ml-1">{{ array_sum(array_column($vaccineProjection['1_month'] ?? [], 'count')) }}</span>
            </button>
            <button class="tab-btn" id="tabBtn6months" role="tab" aria-selected="false" aria-controls="tab6months" onclick="showProjectionTab('6months', this)" type="button">
                6 Bulan Ke Depan
                <span class="badge badge-light ml-1">{{ array_sum(array_column($vaccineProjection['6_months'] ?? [], 'count')) }}</span>
            </button>
            <button class="tab-btn" id="tabBtn12months" role="tab" aria-selected="false" aria-controls="tab12months" onclick="showProjectionTab('12months', this)" type="button">
                12 Bulan Ke Depan
                <span class="badge badge-light ml-1">{{ array_sum(array_column($vaccineProjection['12_months'] ?? [], 'count')) }}</span>
            </button>
            <button class="tab-btn" id="tabBtnbyLocation" role="tab" aria-selected="false" aria-controls="tabbyLocation" onclick="showProjectionTab('byLocation', this)" type="button">Per Lokasi ({{ count($vaccineNeedsByLocation) }})</button>
        </div>

        {{-- Tab: 1 Bulan Ke Depan --}}
        <div class="projection-tab-content active" id="tab1month" role="tabpanel" aria-labelledby="tabBtn1month">
            <div class="alert alert-info py-2 mb-3">
                <i class="fa fa-info-circle mr-1"></i> Proyeksi kebutuhan vaksin untuk <strong>1 bulan ke depan</strong> berdasarkan usia anak.
            </div>
            <div class="table-responsive">
                <table class="vaccine-table">
                    <thead>
                        <tr>
                            <th>Jenis Vaksin</th>
                            <th class="text-center">Jumlah Dosis</th>
                            <th>Posyandu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($vaccineProjection['1_month'] ?? [] as $vaccine => $data)
                        <tr>
                            <td><strong>{{ $vaccine }}</strong></td>
                            <td class="text-center">
                                <span class="vaccine-count {{ $data['count'] > 50 ? '' : ($data['count'] > 20 ? 'medium' : 'low') }}">
                                    {{ $data['count'] }}
                                </span>
                            </td>
                            <td>
                                @php $posyanduList = array_keys($data['posyandu']); @endphp
                                @foreach(array_slice($posyanduList, 0, 5) as $loc)
                                <span class="location-tag">{{ $loc }} ({{ $data['posyandu'][$loc] }})</span>
                                @endforeach
                                @if(count($posyanduList) > 5)
                                <span class="location-tag">+{{ count($posyanduList) - 5 }} lainnya</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted py-4">
                                <i class="fa fa-check-circle fa-2x mb-2 d-block text-success"></i>
                                Tidak ada kebutuhan vaksin dalam 1 bulan ke depan
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Tab: 6 Bulan Ke Depan --}}
        <div class="projection-tab-content" id="tab6months" role="tabpanel" aria-labelledby="tabBtn6months">
            <div class="alert alert-info py-2 mb-3">
                <i class="fa fa-info-circle mr-1"></i> Proyeksi kebutuhan vaksin untuk <strong>6 bulan ke depan</strong> berdasarkan usia anak.
            </div>
            <div class="table-responsive">
                <table class="vaccine-table">
                    <thead>
                        <tr>
                            <th>Jenis Vaksin</th>
                            <th class="text-center">Jumlah Dosis</th>
                            <th>Posyandu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($vaccineProjection['6_months'] ?? [] as $vaccine => $data)
                        <tr>
                            <td><strong>{{ $vaccine }}</strong></td>
                            <td class="text-center">
                                <span class="vaccine-count {{ $data['count'] > 50 ? '' : ($data['count'] > 20 ? 'medium' : 'low') }}">
                                    {{ $data['count'] }}
                                </span>
                            </td>
                            <td>
                                @php $posyanduList = array_keys($data['posyandu']); @endphp
                                @foreach(array_slice($posyanduList, 0, 5) as $loc)
                                <span class="location-tag">{{ $loc }} ({{ $data['posyandu'][$loc] }})</span>
                                @endforeach
                                @if(count($posyanduList) > 5)
                                <span class="location-tag">+{{ count($posyanduList) - 5 }} lainnya</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted py-4">
                                <i class="fa fa-check-circle fa-2x mb-2 d-block text-success"></i>
                                Tidak ada kebutuhan vaksin dalam 6 bulan ke depan
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Tab: 12 Bulan Ke Depan --}}
        <div class="projection-tab-content" id="tab12months" role="tabpanel" aria-labelledby="tabBtn12months">
            <div class="alert alert-info py-2 mb-3">
                <i class="fa fa-info-circle mr-1"></i> Proyeksi kebutuhan vaksin untuk <strong>12 bulan ke depan</strong> berdasarkan usia anak.
            </div>
            <div class="table-responsive">
                <table class="vaccine-table">
                    <thead>
                        <tr>
                            <th>Jenis Vaksin</th>
                            <th class="text-center">Jumlah Dosis</th>
                            <th>Posyandu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($vaccineProjection['12_months'] ?? [] as $vaccine => $data)
                        <tr>
                            <td><strong>{{ $vaccine }}</strong></td>
                            <td class="text-center">
                                <span class="vaccine-count {{ $data['count'] > 50 ? '' : ($data['count'] > 20 ? 'medium' : 'low') }}">
                                    {{ $data['count'] }}
                                </span>
                            </td>
                            <td>
                                @php $posyanduList = array_keys($data['posyandu']); @endphp
                                @foreach(array_slice($posyanduList, 0, 5) as $loc)
                                <span class="location-tag">{{ $loc }} ({{ $data['posyandu'][$loc] }})</span>
                                @endforeach
                                @if(count($posyanduList) > 5)
                                <span class="location-tag">+{{ count($posyanduList) - 5 }} lainnya</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted py-4">
                                <i class="fa fa-check-circle fa-2x mb-2 d-block text-success"></i>
                                Tidak ada kebutuhan vaksin dalam 12 bulan ke depan
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Tab: Per Lokasi --}}
        <div class="projection-tab-content" id="tabbyLocation" role="tabpanel" aria-labelledby="tabBtnbyLocation">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="input-group" style="max-width: 300px;">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-search"></i></span>
                    </div>
                    <input type="text" class="form-control form-control-sm" id="searchLocation" placeholder="Cari lokasi..." onkeyup="filterLocationTable()">
                </div>
                <div class="d-flex align-items-center">
                    <span class="mr-2 text-muted small">Per halaman:</span>
                    <select class="form-control form-control-sm" style="width: 80px;" id="locationPerPage" onchange="updateLocationPagination()">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
            </div>
            <div class="table-responsive">
                <table class="vaccine-table" id="vaccineLocationTable">
                    <thead>
                        <tr>
                            <th>Lokasi (Posyandu/Kelurahan)</th>
                            <th>Kecamatan</th>
                            <th class="text-center">Jumlah Anak</th>
                            <th>Vaksin Dibutuhkan</th>
                        </tr>
                    </thead>
                    <tbody id="locationTableBody">
                        @forelse($vaccineNeedsByLocation as $location => $data)
                        <tr class="location-row" data-location="{{ strtolower($data['posyandu'] . ' ' . $data['kelurahan'] . ' ' . $data['kecamatan']) }}">
                            <td>
                                <strong>{{ $data['posyandu'] !== '-' ? $data['posyandu'] : $data['kelurahan'] }}</strong>
                                @if($data['posyandu'] !== '-')
                                <br><small class="text-muted">{{ $data['kelurahan'] }}</small>
                                @endif
                            </td>
                            <td>{{ $data['kecamatan'] }}</td>
                            <td class="text-center">
                                <span class="vaccine-count {{ $data['total_children'] > 50 ? '' : ($data['total_children'] > 20 ? 'medium' : 'low') }}">
                                    {{ $data['total_children'] }}
                                </span>
                            </td>
                            <td>
                                @foreach($data['vaccines'] as $vaccine => $count)
                                <span class="badge badge-secondary mr-1 mb-1">{{ $vaccine }}: {{ $count }}</span>
                                @endforeach
                            </td>
                        </tr>
                        @empty
                        <tr class="empty-row">
                            <td colspan="4" class="text-center text-muted py-4">
                                <i class="fa fa-check-circle fa-2x mb-2 d-block text-success"></i>
                                Tidak ada kebutuhan vaksin
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{-- Location Pagination --}}
            <div class="pagination-wrapper mt-3" id="locationPaginationWrapper" style="padding: 0.75rem;">
                <div class="pagination-info" id="locationPaginationInfo">
                    Menampilkan 1 - {{ min(20, count($vaccineNeedsByLocation)) }} dari {{ count($vaccineNeedsByLocation) }} lokasi
                </div>
                <div class="pagination-controls" id="locationPaginationControls">
                    <button class="page-btn" onclick="changeLocationPage('first')" id="locFirstBtn"><i class="fa fa-angle-double-left"></i></button>
                    <button class="page-btn" onclick="changeLocationPage('prev')" id="locPrevBtn"><i class="fa fa-angle-left"></i></button>
                    <span class="mx-2" id="locationPageInfo">Halaman 1 dari 1</span>
                    <button class="page-btn" onclick="changeLocationPage('next')" id="locNextBtn"><i class="fa fa-angle-right"></i></button>
                    <button class="page-btn" onclick="changeLocationPage('last')" id="locLastBtn"><i class="fa fa-angle-double-right"></i></button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Filter Bar --}}
<div class="filter-bar">
    <span class="font-weight-bold"><i class="fa fa-filter mr-2"></i>Filter:</span>
    <a href="{{ route('admin.earlyWarning', ['filter' => 'all']) }}" class="filter-btn {{ $filter == 'all' ? 'active' : '' }}">Semua ({{ count($priorityList) }})</a>
    <a href="{{ route('admin.earlyWarning', ['filter' => 'high']) }}" class="filter-btn {{ $filter == 'high' ? 'active' : '' }}">Risiko Tinggi ({{ $summary['high_risk'] }})</a>
    <a href="{{ route('admin.earlyWarning', ['filter' => 'medium']) }}" class="filter-btn {{ $filter == 'medium' ? 'active' : '' }}">Risiko Sedang ({{ $summary['medium_risk'] }})</a>
    <a href="{{ route('admin.earlyWarning', ['filter' => 'low']) }}" class="filter-btn {{ $filter == 'low' ? 'active' : '' }}">Risiko Rendah ({{ $summary['low_risk'] }})</a>
    <a href="{{ route('admin.earlyWarning', ['filter' => 'stunting']) }}" class="filter-btn {{ $filter == 'stunting' ? 'active' : '' }}">Stunting ({{ $summary['stunting_cases'] }})</a>
    <a href="{{ route('admin.earlyWarning', ['filter' => 'immunization']) }}" class="filter-btn {{ $filter == 'immunization' ? 'active' : '' }}">Imunisasi ({{ $summary['incomplete_immunization'] }})</a>
</div>

{{-- Priority List --}}
<div class="section-title">
    <i class="fa fa-list-ol"></i> Daftar Prioritas Intervensi
    <span class="ml-2 text-muted" style="font-size: 0.85rem; font-weight: normal;">
        ({{ $pagination['total'] }} anak)
    </span>
    <button type="button" class="btn btn-sm btn-outline-primary ml-3" data-toggle="modal" data-target="#scoreInfoModal" aria-describedby="scoreInfoHelp">
        <i class="fa fa-info-circle mr-1"></i> Cara Skor
    </button>
    <span id="scoreInfoHelp" class="sr-only">Membuka penjelasan perhitungan skor prioritas</span>
</div>

<div id="childrenList">
    @forelse($paginatedList as $index => $child)
    @php $globalIndex = ($pagination['current_page'] - 1) * $pagination['per_page'] + $index + 1; @endphp
    <div class="child-card" data-risk="{{ $child['risk_level'] }}"
         data-stunting="{{ isset($child['zscore_status']['tb_u']) && in_array($child['zscore_status']['tb_u'], ['stunted', 'severely_stunted']) ? '1' : '0' }}"
         data-immunization="{{ count($child['imunisasi_missing']) > 0 ? '1' : '0' }}">
        <div class="child-card-header {{ $child['risk_level'] }}">
            <div>
                <strong class="mr-2">#{{ $globalIndex }}</strong>
                <span style="font-size: 1.1rem;">{{ $child['nama'] }}</span>
                <span class="text-muted ml-2">({{ $child['usia_bulan'] }} bulan, {{ $child['jk'] }})</span>
            </div>
            <div class="d-flex align-items-center">
                <span class="risk-badge {{ $child['risk_level'] }} mr-3">
                    <i class="fa fa-exclamation-triangle mr-1"></i>
                    Score: {{ $child['risk_score'] }}
                </span>
                <a href="{{ route('admin.showAnak', $child['hashid']) }}" class="btn btn-sm btn-primary action-btn">
                    <i class="fa fa-eye mr-1"></i> Lihat Detail
                </a>
            </div>
        </div>
        <div class="child-card-body">
            <div class="child-info">
                <div class="child-info-item">
                    <label>Posyandu</label>
                    <span>{{ $child['posyandu'] }}</span>
                </div>
                <div class="child-info-item">
                    <label>Kelurahan</label>
                    <span>{{ $child['kelurahan'] }}</span>
                </div>
                <div class="child-info-item">
                    <label>Kecamatan</label>
                    <span>{{ $child['kecamatan'] }}</span>
                </div>
                <div class="child-info-item">
                    <label>Kunjungan Terakhir</label>
                    <span>{{ $child['last_visit'] ? \Carbon\Carbon::parse($child['last_visit'])->format('d M Y') : 'Belum pernah' }}</span>
                </div>
                @if(isset($child['bb']))
                <div class="child-info-item">
                    <label>Berat Badan</label>
                    <span>{{ $child['bb'] }} kg</span>
                </div>
                @endif
                @if(isset($child['tb']))
                <div class="child-info-item">
                    <label>Tinggi Badan</label>
                    <span>{{ $child['tb'] }} cm</span>
                </div>
                @endif
                <div class="child-info-item">
                    <label>Imunisasi</label>
                    <span>{{ $child['imunisasi_lengkap'] }}/11 lengkap</span>
                </div>
            </div>

            {{-- Alerts --}}
            <div class="alerts-container">
                @foreach($child['alerts'] as $alert)
                <div class="alert-item {{ $alert['type'] }}">
                    <i class="fa {{ $alert['icon'] }}"></i>
                    {{ $alert['message'] }}
                </div>
                @endforeach
            </div>

            {{-- Missing Vaccines --}}
            @if(count($child['imunisasi_missing']) > 0)
            <div class="mt-2">
                <small class="text-muted">Vaksin belum lengkap:</small>
                <div class="mt-1">
                    @foreach($child['imunisasi_missing'] as $vaccine)
                    <span class="badge badge-secondary mr-1">{{ $vaccine }}</span>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
    @empty
    <div class="empty-state">
        <i class="fa fa-check-circle"></i>
        <h5>Tidak Ada Peringatan</h5>
        <p>Semua anak dalam kondisi baik dan tidak memerlukan intervensi khusus.</p>
    </div>
    @endforelse
</div>

{{-- Pagination --}}
@if($pagination['total'] > 0)
<div class="pagination-wrapper">
    <div class="pagination-info">
        Menampilkan {{ (($pagination['current_page'] - 1) * $pagination['per_page']) + 1 }} -
        {{ min($pagination['current_page'] * $pagination['per_page'], $pagination['total']) }}
        dari {{ $pagination['total'] }} anak
        @if($filter !== 'all')
        (filter: {{ $filter }})
        @endif
    </div>
    <div class="pagination-controls">
        <select class="per-page-select" onchange="changePerPage(this.value)">
            <option value="10" {{ $pagination['per_page'] == 10 ? 'selected' : '' }}>10 per halaman</option>
            <option value="20" {{ $pagination['per_page'] == 20 ? 'selected' : '' }}>20 per halaman</option>
            <option value="50" {{ $pagination['per_page'] == 50 ? 'selected' : '' }}>50 per halaman</option>
            <option value="100" {{ $pagination['per_page'] == 100 ? 'selected' : '' }}>100 per halaman</option>
        </select>

        <a href="{{ route('admin.earlyWarning', ['page' => 1, 'per_page' => $pagination['per_page'], 'filter' => $filter]) }}"
           class="page-btn {{ !$pagination['has_prev'] ? 'disabled' : '' }}"
           {{ !$pagination['has_prev'] ? 'onclick=return false;' : '' }}>
            <i class="fa fa-angle-double-left"></i>
        </a>
        <a href="{{ route('admin.earlyWarning', ['page' => $pagination['current_page'] - 1, 'per_page' => $pagination['per_page'], 'filter' => $filter]) }}"
           class="page-btn {{ !$pagination['has_prev'] ? 'disabled' : '' }}"
           {{ !$pagination['has_prev'] ? 'onclick=return false;' : '' }}>
            <i class="fa fa-angle-left"></i>
        </a>

        <span class="mx-2">
            Halaman {{ $pagination['current_page'] }} dari {{ $pagination['total_pages'] }}
        </span>

        <a href="{{ route('admin.earlyWarning', ['page' => $pagination['current_page'] + 1, 'per_page' => $pagination['per_page'], 'filter' => $filter]) }}"
           class="page-btn {{ !$pagination['has_next'] ? 'disabled' : '' }}"
           {{ !$pagination['has_next'] ? 'onclick=return false;' : '' }}>
            <i class="fa fa-angle-right"></i>
        </a>
        <a href="{{ route('admin.earlyWarning', ['page' => $pagination['total_pages'], 'per_page' => $pagination['per_page'], 'filter' => $filter]) }}"
           class="page-btn {{ !$pagination['has_next'] ? 'disabled' : '' }}"
           {{ !$pagination['has_next'] ? 'onclick=return false;' : '' }}>
            <i class="fa fa-angle-double-right"></i>
        </a>
    </div>
</div>
@endif

@if(count($priorityList) > 0)
<div class="text-center mt-4">
    <p class="text-muted">
        <i class="fa fa-info-circle mr-1"></i>
        Total {{ count($priorityList) }} anak memerlukan perhatian dari {{ $summary['total_children'] }} anak terdaftar.
    </p>
</div>
@endif

{{-- Score Explanation Modal --}}
<div class="modal fade" id="scoreInfoModal" tabindex="-1" role="dialog" aria-labelledby="scoreInfoTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scoreInfoTitle">Cara Menghitung Skor Prioritas Intervensi</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-accessible-muted mb-3">
                    Skor merupakan penjumlahan beberapa faktor risiko. Semakin tinggi skor, semakin tinggi prioritas intervensi.
                </p>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Faktor Risiko</th>
                                <th class="text-center">Skor</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Belum pernah dilakukan pengukuran</td>
                                <td class="text-center">+30</td>
                                <td>Data kunjungan belum ada.</td>
                            </tr>
                            <tr>
                                <td>Tidak ada kunjungan &gt; 2 bulan</td>
                                <td class="text-center">+15</td>
                                <td>Berdasarkan tanggal kunjungan terakhir.</td>
                            </tr>
                            <tr>
                                <td>Sangat pendek (Severely Stunted)</td>
                                <td class="text-center">+40</td>
                                <td>TB/U &lt; -3 SD.</td>
                            </tr>
                            <tr>
                                <td>Pendek (Stunted)</td>
                                <td class="text-center">+25</td>
                                <td>TB/U &lt; -2 SD.</td>
                            </tr>
                            <tr>
                                <td>Gizi buruk (Severely Wasted)</td>
                                <td class="text-center">+40</td>
                                <td>IMT/U &lt; -3 SD.</td>
                            </tr>
                            <tr>
                                <td>Gizi kurang (Wasted)</td>
                                <td class="text-center">+25</td>
                                <td>IMT/U &lt; -2 SD.</td>
                            </tr>
                            <tr>
                                <td>BB sangat kurang (Severely Underweight)</td>
                                <td class="text-center">+35</td>
                                <td>BB/U &lt; -3 SD.</td>
                            </tr>
                            <tr>
                                <td>BB kurang (Underweight)</td>
                                <td class="text-center">+20</td>
                                <td>BB/U &lt; -2 SD.</td>
                            </tr>
                            <tr>
                                <td>Gizi lebih (Overweight)</td>
                                <td class="text-center">+10</td>
                                <td>IMT/U &gt; +2 SD.</td>
                            </tr>
                            <tr>
                                <td>Obesitas</td>
                                <td class="text-center">+20</td>
                                <td>IMT/U &gt; +3 SD.</td>
                            </tr>
                            <tr>
                                <td>Imunisasi belum lengkap</td>
                                <td class="text-center">+3 / vaksin</td>
                                <td>Setiap vaksin yang belum lengkap menambah skor.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-accessible-muted mb-0">
                    Kategori risiko: <strong>Tinggi</strong> (skor ≥ 50), <strong>Sedang</strong> (skor 25–49), <strong>Rendah</strong> (skor 1–24).
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
</main>
@endsection

@section('scripts')
@parent
<script>
// Tab switching for vaccine projection
function showProjectionTab(tabName, buttonEl) {
    // Update button states
    document.querySelectorAll('.vaccine-card .tab-btn').forEach(btn => {
        btn.classList.remove('active');
        btn.setAttribute('aria-selected', 'false');
        btn.setAttribute('tabindex', '-1');
    });
    if (buttonEl) {
        buttonEl.classList.add('active');
        buttonEl.setAttribute('aria-selected', 'true');
        buttonEl.setAttribute('tabindex', '0');
    }

    // Show/hide content
    document.querySelectorAll('.projection-tab-content').forEach(content => {
        content.classList.remove('active');
        content.setAttribute('aria-hidden', 'true');
    });
    const targetTab = document.getElementById('tab' + tabName);
    if (targetTab) {
        targetTab.classList.add('active');
        targetTab.setAttribute('aria-hidden', 'false');
    }

    // Initialize location pagination when showing location tab
    if (tabName === 'byLocation') {
        updateLocationPagination();
    }
}

// Legacy tab switching (kept for compatibility)
function showTab(tabName, buttonEl) {
    // Update button states
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
        btn.setAttribute('aria-selected', 'false');
        btn.setAttribute('tabindex', '-1');
    });
    if (buttonEl) {
        buttonEl.classList.add('active');
        buttonEl.setAttribute('aria-selected', 'true');
        buttonEl.setAttribute('tabindex', '0');
    }

    // Show/hide content
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
        content.setAttribute('aria-hidden', 'true');
    });
    const targetTab = document.getElementById('tab' + tabName.charAt(0).toUpperCase() + tabName.slice(1));
    if (targetTab) {
        targetTab.classList.add('active');
        targetTab.setAttribute('aria-hidden', 'false');
    }

    // Initialize location pagination when showing location tab
    if (tabName === 'byLocation') {
        updateLocationPagination();
    }
}

// Change items per page for priority list
function changePerPage(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', value);
    url.searchParams.set('page', 1); // Reset to page 1
    window.location.href = url.toString();
}

// Location table pagination
let locationCurrentPage = 1;
let locationPerPage = 20;
let locationRows = [];
let filteredRows = [];

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    if (typeof $ !== 'undefined' && $.fn.tooltip) {
        $('[data-toggle="tooltip"]').tooltip();
    }

    // Initialize location rows
    locationRows = Array.from(document.querySelectorAll('.location-row'));
    filteredRows = [...locationRows];
    updateLocationPagination();
});

function filterLocationTable() {
    const search = document.getElementById('searchLocation').value.toLowerCase();
    filteredRows = locationRows.filter(row => {
        const location = row.dataset.location || '';
        return location.includes(search);
    });
    locationCurrentPage = 1;
    updateLocationPagination();
}

function updateLocationPagination() {
    locationPerPage = parseInt(document.getElementById('locationPerPage').value);
    const totalRows = filteredRows.length;
    const totalPages = Math.ceil(totalRows / locationPerPage) || 1;

    // Ensure current page is valid
    if (locationCurrentPage > totalPages) locationCurrentPage = totalPages;
    if (locationCurrentPage < 1) locationCurrentPage = 1;

    // Hide all rows first
    locationRows.forEach(row => row.style.display = 'none');

    // Show rows for current page
    const startIdx = (locationCurrentPage - 1) * locationPerPage;
    const endIdx = Math.min(startIdx + locationPerPage, totalRows);

    for (let i = startIdx; i < endIdx; i++) {
        filteredRows[i].style.display = '';
    }

    // Update pagination info
    if (totalRows > 0) {
        document.getElementById('locationPaginationInfo').textContent =
            `Menampilkan ${startIdx + 1} - ${endIdx} dari ${totalRows} lokasi`;
    } else {
        document.getElementById('locationPaginationInfo').textContent = 'Tidak ada data';
    }

    document.getElementById('locationPageInfo').textContent =
        `Halaman ${locationCurrentPage} dari ${totalPages}`;

    // Update button states
    document.getElementById('locFirstBtn').disabled = locationCurrentPage <= 1;
    document.getElementById('locPrevBtn').disabled = locationCurrentPage <= 1;
    document.getElementById('locNextBtn').disabled = locationCurrentPage >= totalPages;
    document.getElementById('locLastBtn').disabled = locationCurrentPage >= totalPages;

    // Hide pagination if not needed
    const paginationWrapper = document.getElementById('locationPaginationWrapper');
    if (totalRows <= locationPerPage) {
        paginationWrapper.style.display = 'none';
    } else {
        paginationWrapper.style.display = 'flex';
    }
}

function changeLocationPage(action) {
    const totalPages = Math.ceil(filteredRows.length / locationPerPage) || 1;

    switch(action) {
        case 'first': locationCurrentPage = 1; break;
        case 'prev': locationCurrentPage = Math.max(1, locationCurrentPage - 1); break;
        case 'next': locationCurrentPage = Math.min(totalPages, locationCurrentPage + 1); break;
        case 'last': locationCurrentPage = totalPages; break;
    }

    updateLocationPagination();
}
</script>
@endsection

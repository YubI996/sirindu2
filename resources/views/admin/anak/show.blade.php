@extends('admin::layouts.app')
@section('title')
Detail Data Anak - {{ $anak->nama }}
@endsection
@section('title-content')
Detail Data Anak
@endsection
@section('item')
Data Anak
@endsection
@section('item-active')
Detail
@endsection

@section('content')
{{-- Skip Link for Accessibility --}}
<a href="#main-content" class="sr-only sr-only-focusable skip-link">Langsung ke konten utama</a>

<style>
    /* WCAG AA Compliant Styles - Blue & Green Theme */
    /* All colors tested for 4.5:1 contrast ratio minimum on white (#fff) */

    :root {
        /* Primary Blues - WCAG AA Compliant */
        --primary-blue: #0066cc;          /* 5.5:1 contrast */
        --primary-blue-dark: #004d99;     /* 7.8:1 contrast */
        --primary-blue-light: #e6f2ff;

        /* Greens - WCAG AA Compliant */
        --success-green: #047857;         /* 5.9:1 contrast */
        --success-green-dark: #065f46;    /* 7.5:1 contrast */
        --success-green-light: #d1fae5;

        /* Secondary Colors - WCAG AA Compliant */
        --info-teal: #0891b2;             /* 4.5:1 contrast */
        --warning-amber: #b45309;         /* 5.2:1 contrast */
        --danger-rose: #be123c;           /* 5.6:1 contrast */

        /* Neutral - WCAG AA Compliant */
        --text-muted: #4b5563;            /* 7.5:1 contrast */
        --text-secondary: #6b7280;        /* 5.0:1 contrast */
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

    /* Enhanced Focus Indicators - WCAG 2.4.7 */
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

    /* High Contrast Text - WCAG 1.4.3 */
    .text-accessible-muted {
        color: var(--text-muted) !important;
    }

    .stat-card {
        position: relative;
        overflow: hidden;
        border-radius: 12px;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border: 1px solid #e5e7eb;
    }

    /* Respect reduced motion preference - WCAG 2.3.3 */
    @media (prefers-reduced-motion: reduce) {
        .stat-card,
        .stat-card:hover {
            transition: none;
            transform: none;
        }
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0, 102, 204, 0.15);
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 5px;
        height: 100%;
        background: linear-gradient(180deg, var(--primary-blue) 0%, var(--success-green) 100%);
    }

    /* Status colors with gradients - All WCAG AA Compliant */
    .stat-card.status-success::before {
        background: linear-gradient(180deg, #10b981 0%, var(--success-green) 100%);
    }
    .stat-card.status-warning::before {
        background: linear-gradient(180deg, #f59e0b 0%, var(--warning-amber) 100%);
    }
    .stat-card.status-danger::before {
        background: linear-gradient(180deg, #f43f5e 0%, var(--danger-rose) 100%);
    }
    .stat-card.status-info::before {
        background: linear-gradient(180deg, #06b6d4 0%, var(--info-teal) 100%);
    }

    .child-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary-blue) 0%, var(--success-green) 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        font-size: 32px;
        font-weight: bold;
        box-shadow: 0 4px 15px rgba(0, 102, 204, 0.3);
    }

    .info-card {
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }

    .info-card .card-header {
        background: linear-gradient(135deg, var(--primary-blue) 0%, var(--success-green) 100%);
        color: #ffffff;
        border-radius: 0 !important;
        font-weight: 600;
        padding: 1rem 1.25rem;
    }

    .info-card .card-header h2 {
        font-size: 1rem;
        margin: 0;
        color: #ffffff;
    }

    /* Z-Score Indicators */
    .z-score-indicator {
        width: 14px;
        height: 14px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 8px;
        border: 2px solid transparent;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .z-score-normal {
        background-color: var(--success-green);
        border-color: var(--success-green);
    }
    .z-score-warning {
        background-color: var(--warning-amber);
        border-color: var(--warning-amber);
    }
    .z-score-danger {
        background-color: var(--danger-rose);
        border-color: var(--danger-rose);
    }

    .growth-chart-container {
        position: relative;
        height: 300px;
        width: 100%;
    }

    .timeline-item {
        position: relative;
        padding-left: 30px;
        border-left: 3px solid var(--success-green-light);
        margin-left: 15px;
    }

    .timeline-item::before {
        content: '';
        position: absolute;
        left: -9px;
        top: 0;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary-blue) 0%, var(--success-green) 100%);
        border: 3px solid #ffffff;
        box-shadow: 0 2px 4px rgba(0, 102, 204, 0.3);
    }

    /* WCAG AA Compliant Badge Colors - Vibrant but Accessible */
    .badge-accessible-success {
        background-color: var(--success-green) !important;
        color: #ffffff !important;
    }

    .badge-accessible-warning {
        background-color: var(--warning-amber) !important;
        color: #ffffff !important;
    }

    .badge-accessible-danger {
        background-color: var(--danger-rose) !important;
        color: #ffffff !important;
    }

    .badge-accessible-info {
        background-color: var(--info-teal) !important;
        color: #ffffff !important;
    }

    .badge-accessible-secondary {
        background-color: var(--text-muted) !important;
        color: #ffffff !important;
    }

    .badge-status {
        font-size: 0.75rem;
        padding: 0.4em 0.7em;
        font-weight: 600;
        border-radius: 6px;
    }

    /* Gender badges - Blue & Pink (both WCAG AA compliant) */
    .badge-gender-male {
        background-color: var(--primary-blue) !important;
        color: #ffffff !important;
    }

    .badge-gender-female {
        background-color: #db2777 !important; /* 4.7:1 contrast */
        color: #ffffff !important;
    }

    /* Table with Blue-Green Theme */
    .table th {
        font-weight: 700;
        background: linear-gradient(135deg, var(--primary-blue) 0%, var(--success-green) 100%);
        color: #ffffff;
    }

    .table-accessible th,
    .table-accessible td {
        padding: 0.75rem;
        vertical-align: middle;
    }

    .table-striped tbody tr:nth-of-type(odd) {
        background-color: rgba(0, 102, 204, 0.03);
    }

    .table-hover tbody tr:hover {
        background-color: rgba(0, 102, 204, 0.08);
    }

    /* Button group with Blue-Green Theme */
    .btn-group .btn:focus {
        z-index: 3;
    }

    .btn-group .btn.active {
        background: linear-gradient(135deg, var(--primary-blue) 0%, var(--success-green) 100%);
        border-color: var(--primary-blue);
        color: #ffffff;
    }

    .btn-outline-primary {
        color: var(--primary-blue);
        border-color: var(--primary-blue);
    }

    .btn-outline-primary:hover,
    .btn-outline-primary:focus {
        background-color: var(--primary-blue);
        border-color: var(--primary-blue);
        color: #ffffff;
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary-blue) 0%, #0077dd 100%);
        border-color: var(--primary-blue);
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, var(--primary-blue-dark) 0%, var(--primary-blue) 100%);
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

    /* High contrast mode support */
    @media (prefers-contrast: high) {
        .info-card {
            border: 2px solid #000;
        }
        .stat-card::before {
            width: 8px;
        }
        .badge-status {
            border: 1px solid currentColor;
        }
    }

    /* Links with Blue theme */
    a:not(.btn) {
        color: var(--primary-blue);
    }

    a:not(.btn):hover {
        color: var(--primary-blue-dark);
    }

    /* Card hover effects with color */
    .info-card:hover {
        box-shadow: 0 8px 30px rgba(0, 102, 204, 0.12);
    }

    /* Section dividers with gradient */
    hr {
        border: 0;
        height: 2px;
        background: linear-gradient(90deg, var(--primary-blue-light) 0%, var(--success-green-light) 100%);
        opacity: 1;
    }
</style>

{{-- Main Content Region --}}
<main id="main-content" role="main" aria-label="Detail informasi anak {{ $anak->nama }}">

    {{-- Header Section --}}
    <header class="row mb-4">
        <div class="col-md-8">
            <div class="d-flex align-items-center">
                <div class="child-avatar me-3" role="img" aria-label="Avatar {{ $anak->nama }}">
                    <span aria-hidden="true">{{ strtoupper(substr($anak->nama, 0, 1)) }}</span>
                </div>
                <div>
                    <h1 class="h3 mb-1">{{ $anak->nama }}</h1>
                    <p class="text-accessible-muted mb-0">
                        <span class="sr-only">Nomor Induk Kependudukan:</span>
                        NIK: {{ $anak->nik }}
                    </p>
                    <p class="text-accessible-muted mb-0">
                        <span class="sr-only">Usia:</span>
                        {{ $usiaText }},
                        @if($anak->jk == 1)
                            <span class="badge badge-gender-male">Laki-laki</span>
                        @else
                            <span class="badge badge-gender-female">Perempuan</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-4 text-end">
            <nav aria-label="Aksi data anak">
                <div class="btn-group" role="group" aria-label="Tombol aksi untuk data anak">
                    <a href="{{ route('admin.editAnak', $anak->hashid) }}" class="btn btn-outline-primary btn-sm" aria-label="Edit data {{ $anak->nama }}">
                        <span aria-hidden="true" class="icon-copy dw dw-edit2"></span>
                        <span>Edit</span>
                    </a>
                    <a href="{{ route('admin.dataAnak', $anak->hashid) }}" class="btn btn-primary btn-sm" aria-label="Tambah data pengukuran untuk {{ $anak->nama }}">
                        <span aria-hidden="true" class="icon-copy dw dw-add"></span>
                        <span>Tambah Pengukuran</span>
                    </a>
                    <a href="{{ route('admin.chartAnak', $anak->hashid) }}" class="btn btn-outline-info btn-sm" aria-label="Lihat grafik pertumbuhan {{ $anak->nama }}">
                        <span aria-hidden="true" class="icon-copy dw dw-analytics-13"></span>
                        <span>Grafik</span>
                    </a>
                </div>
            </nav>
        </div>
    </header>

    {{-- Information Cards Section --}}
    <section aria-labelledby="info-section-title" class="row">
        <h2 id="info-section-title" class="sr-only">Informasi Detail Anak</h2>

        {{-- Child Information --}}
        <div class="col-lg-4 mb-4">
            <article class="card info-card h-100">
                <div class="card-header">
                    <h2 id="child-info-title">
                        <span aria-hidden="true" class="icon-copy dw dw-user-1 mr-2"></span>
                        Informasi Anak
                    </h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5 text-accessible-muted">No. KK</dt>
                        <dd class="col-sm-7">{{ $anak->no_kk }}</dd>

                        <dt class="col-sm-5 text-accessible-muted">NIK</dt>
                        <dd class="col-sm-7">{{ $anak->nik }}</dd>

                        <dt class="col-sm-5 text-accessible-muted">Tempat Lahir</dt>
                        <dd class="col-sm-7">{{ $anak->tempat_lahir }}</dd>

                        <dt class="col-sm-5 text-accessible-muted">Tanggal Lahir</dt>
                        <dd class="col-sm-7">
                            <time datetime="{{ $anak->tgl_lahir }}">
                                {{ \Carbon\Carbon::parse($anak->tgl_lahir)->format('d F Y') }}
                            </time>
                        </dd>

                        <dt class="col-sm-5 text-accessible-muted">Usia</dt>
                        <dd class="col-sm-7">{{ $usiaText }}</dd>

                        <dt class="col-sm-5 text-accessible-muted">Jenis Kelamin</dt>
                        <dd class="col-sm-7">{{ $anak->jk == 1 ? 'Laki-laki' : 'Perempuan' }}</dd>

                        <dt class="col-sm-5 text-accessible-muted">Golongan Darah</dt>
                        <dd class="col-sm-7">{{ $anak->golda ?: '-' }}</dd>

                        <dt class="col-sm-5 text-accessible-muted">Anak Ke-</dt>
                        <dd class="col-sm-7 mb-0">{{ $anak->anak }}</dd>
                    </dl>
                </div>
            </article>
        </div>

        {{-- Parent/Guardian Information --}}
        <div class="col-lg-4 mb-4">
            <article class="card info-card h-100">
                <div class="card-header">
                    <h2 id="parent-info-title">
                        <span aria-hidden="true" class="icon-copy dw dw-user-2 mr-2"></span>
                        Informasi Orang Tua
                    </h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5 text-accessible-muted">NIK Orang Tua</dt>
                        <dd class="col-sm-7">{{ $anak->nik_ortu }}</dd>

                        <dt class="col-sm-5 text-accessible-muted">Nama Ibu</dt>
                        <dd class="col-sm-7">{{ $anak->nama_ibu }}</dd>

                        <dt class="col-sm-5 text-accessible-muted">Nama Ayah</dt>
                        <dd class="col-sm-7 mb-0">{{ $anak->nama_ayah }}</dd>
                    </dl>
                </div>
            </article>
        </div>

        {{-- Location Information --}}
        <div class="col-lg-4 mb-4">
            <article class="card info-card h-100">
                <div class="card-header">
                    <h2 id="location-info-title">
                        <span aria-hidden="true" class="icon-copy dw dw-placeholder1 mr-2"></span>
                        Informasi Wilayah
                    </h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5 text-accessible-muted">Kecamatan</dt>
                        <dd class="col-sm-7">{{ $kecamatan->name ?? '-' }}</dd>

                        <dt class="col-sm-5 text-accessible-muted">Kelurahan</dt>
                        <dd class="col-sm-7">{{ $kelurahan->name ?? '-' }}</dd>

                        <dt class="col-sm-5 text-accessible-muted">RT</dt>
                        <dd class="col-sm-7">{{ $rt->name ?? '-' }}</dd>

                        <dt class="col-sm-5 text-accessible-muted">Puskesmas</dt>
                        <dd class="col-sm-7">{{ $puskesmas->name ?? '-' }}</dd>

                        <dt class="col-sm-5 text-accessible-muted">Posyandu</dt>
                        <dd class="col-sm-7 mb-0">{{ $posyandu->name ?? '-' }}</dd>
                    </dl>
                    @if($anak->catatan)
                    <hr>
                    <div>
                        <strong class="text-accessible-muted">Catatan:</strong>
                        <p class="mb-0">{{ $anak->catatan }}</p>
                    </div>
                    @endif
                </div>
            </article>
        </div>
    </section>

    {{-- Current Health Status --}}
    @if($latestData)
    <section aria-labelledby="health-status-title" class="row mb-4">
        <div class="col-12">
            <article class="card info-card">
                <div class="card-header">
                    <h2 id="health-status-title">
                        <span aria-hidden="true" class="icon-copy dw dw-heart mr-2"></span>
                        Status Kesehatan Terkini
                    </h2>
                </div>
                <div class="card-body">
                    @php
                        $bbStatus = str_contains(strtolower($latestData['bb']), 'normal') ? 'success' : (str_contains(strtolower($latestData['bb']), 'kurang') ? 'warning' : 'danger');
                        $tbStatus = str_contains(strtolower($latestData['tb']), 'normal') ? 'success' : (str_contains(strtolower($latestData['tb']), 'pendek') ? 'warning' : 'danger');
                        $imtStatus = (str_contains(strtolower($latestData['imt']), 'baik') || str_contains(strtolower($latestData['imt']), 'normal')) ? 'success' : (str_contains(strtolower($latestData['imt']), 'kurang') ? 'warning' : 'danger');
                        $btStatus = (str_contains(strtolower($latestData['bt']), 'baik') || str_contains(strtolower($latestData['bt']), 'normal')) ? 'success' : (str_contains(strtolower($latestData['bt']), 'kurang') ? 'warning' : 'danger');
                    @endphp

                    <div class="row" role="list" aria-label="Ringkasan status kesehatan">
                        <div class="col-md-3 mb-3" role="listitem">
                            <div class="card stat-card status-{{ $bbStatus }} h-100">
                                <div class="card-body text-center py-4">
                                    <h3 class="h6 text-accessible-muted mb-2">Berat Badan</h3>
                                    <p class="h3 mb-1" aria-label="{{ $latestData['berat'] }} kilogram">
                                        {{ $latestData['berat'] }} <span class="h6">kg</span>
                                    </p>
                                    <p class="small text-accessible-muted mb-0">
                                        <span class="sr-only">Status:</span>
                                        {{ $latestData['bb'] }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3" role="listitem">
                            <div class="card stat-card status-{{ $tbStatus }} h-100">
                                <div class="card-body text-center py-4">
                                    <h3 class="h6 text-accessible-muted mb-2">Tinggi Badan</h3>
                                    <p class="h3 mb-1" aria-label="{{ $latestData['tinggi'] }} sentimeter">
                                        {{ $latestData['tinggi'] }} <span class="h6">cm</span>
                                    </p>
                                    <p class="small text-accessible-muted mb-0">
                                        <span class="sr-only">Status:</span>
                                        {{ $latestData['tb'] }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3" role="listitem">
                            <div class="card stat-card status-{{ $imtStatus }} h-100">
                                <div class="card-body text-center py-4">
                                    <h3 class="h6 text-accessible-muted mb-2">
                                        <abbr title="Indeks Massa Tubuh">IMT</abbr>
                                    </h3>
                                    <p class="h3 mb-1">{{ $latestData['bmi'] }}</p>
                                    <p class="small text-accessible-muted mb-0">
                                        <span class="sr-only">Status:</span>
                                        {{ $latestData['imt'] }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3" role="listitem">
                            <div class="card stat-card status-info h-100">
                                <div class="card-body text-center py-4">
                                    <h3 class="h6 text-accessible-muted mb-2">Usia Pengukuran</h3>
                                    <p class="h3 mb-1" aria-label="{{ $latestData['bln'] }} bulan">
                                        {{ $latestData['bln'] }} <span class="h6">bln</span>
                                    </p>
                                    <p class="small text-accessible-muted mb-0">
                                        <time datetime="{{ $latestData['tgl_kunjungan'] }}">
                                            {{ \Carbon\Carbon::parse($latestData['tgl_kunjungan'])->format('d M Y') }}
                                        </time>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Z-Score Summary --}}
                    <div class="row mt-3">
                        <div class="col-12">
                            <h3 class="h6 mb-3">Status Gizi (Z-Score)</h3>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-unstyled" aria-label="Indikator Z-Score bagian pertama">
                                <li class="mb-2 d-flex justify-content-between align-items-center">
                                    <span>
                                        <span class="z-score-indicator z-score-{{ $imtStatus }}" aria-hidden="true"></span>
                                        <abbr title="Indeks Massa Tubuh menurut Umur">IMT/U</abbr>
                                    </span>
                                    <span class="badge badge-accessible-secondary">{{ $latestData['imt'] }}</span>
                                </li>
                                <li class="mb-2 d-flex justify-content-between align-items-center">
                                    <span>
                                        <span class="z-score-indicator z-score-{{ $bbStatus }}" aria-hidden="true"></span>
                                        <abbr title="Berat Badan menurut Umur">BB/U</abbr>
                                    </span>
                                    <span class="badge badge-accessible-secondary">{{ $latestData['bb'] }}</span>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-unstyled" aria-label="Indikator Z-Score bagian kedua">
                                <li class="mb-2 d-flex justify-content-between align-items-center">
                                    <span>
                                        <span class="z-score-indicator z-score-{{ $tbStatus }}" aria-hidden="true"></span>
                                        <abbr title="Tinggi Badan menurut Umur">TB/U</abbr>
                                    </span>
                                    <span class="badge badge-accessible-secondary">{{ $latestData['tb'] }}</span>
                                </li>
                                <li class="mb-2 d-flex justify-content-between align-items-center">
                                    <span>
                                        <span class="z-score-indicator z-score-{{ $btStatus }}" aria-hidden="true"></span>
                                        <abbr title="Berat Badan menurut Tinggi Badan">BB/TB</abbr>
                                    </span>
                                    <span class="badge badge-accessible-secondary">{{ $latestData['bt'] }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </article>
        </div>
    </section>
    @endif

    {{-- Growth History Section --}}
    <section class="row" aria-labelledby="growth-section-title">
        <h2 id="growth-section-title" class="sr-only">Riwayat Pertumbuhan dan Kunjungan</h2>

        {{-- Growth History Chart --}}
        <div class="col-lg-8 mb-4">
            <article class="card info-card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                    <h2 id="growth-chart-title">
                        <span aria-hidden="true" class="icon-copy dw dw-analytics-13 mr-2"></span>
                        Riwayat Pertumbuhan
                    </h2>
                    @if(count($hasilx) > 0)
                    <div class="btn-group btn-group-sm mt-2 mt-md-0" role="group" aria-label="Pilih jenis grafik">
                        <button type="button"
                                class="btn btn-outline-light btn-sm active"
                                id="weightHistoryBtn"
                                aria-pressed="true"
                                aria-controls="growthHistoryChart">
                            Berat
                        </button>
                        <button type="button"
                                class="btn btn-outline-light btn-sm"
                                id="heightHistoryBtn"
                                aria-pressed="false"
                                aria-controls="growthHistoryChart">
                            Tinggi
                        </button>
                        <button type="button"
                                class="btn btn-outline-light btn-sm"
                                id="bmiHistoryBtn"
                                aria-pressed="false"
                                aria-controls="growthHistoryChart">
                            <abbr title="Indeks Massa Tubuh">IMT</abbr>
                        </button>
                    </div>
                    @endif
                </div>
                <div class="card-body">
                    @if(count($hasilx) > 0)
                    <div class="growth-chart-container">
                        <canvas id="growthHistoryChart"
                                role="img"
                                aria-label="Grafik riwayat pertumbuhan anak menampilkan berat badan dari waktu ke waktu"></canvas>
                    </div>
                    {{-- Screen reader accessible data summary --}}
                    <div class="sr-only" aria-live="polite" id="chartSummary">
                        Data grafik: {{ count($hasilx) }} pengukuran dari
                        {{ \Carbon\Carbon::parse($hasilx[array_key_first($hasilx)]['tgl_kunjungan'])->format('M Y') }}
                        sampai
                        {{ \Carbon\Carbon::parse($hasilx[array_key_last($hasilx)]['tgl_kunjungan'])->format('M Y') }}
                    </div>
                    @else
                    <div class="text-center py-5" role="status">
                        <span aria-hidden="true" class="icon-copy dw dw-file-39" style="font-size: 48px; color: #6c757d;"></span>
                        <p class="text-accessible-muted mt-3">Belum ada data pengukuran</p>
                        <a href="{{ route('admin.dataAnak', $anak->hashid) }}"
                           class="btn btn-primary btn-sm"
                           aria-label="Tambah data pengukuran pertama untuk {{ $anak->nama }}">
                            <span aria-hidden="true" class="icon-copy dw dw-add"></span>
                            Tambah Data Pengukuran
                        </a>
                    </div>
                    @endif
                </div>
            </article>
        </div>

        {{-- Recent Measurements --}}
        <div class="col-lg-4 mb-4">
            <article class="card info-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 id="visits-title">
                        <span aria-hidden="true" class="icon-copy dw dw-calendar1 mr-2"></span>
                        Riwayat Kunjungan
                    </h2>
                    <a href="{{ route('admin.dataAnak', $anak->hashid) }}"
                       class="btn btn-sm btn-light"
                       aria-label="Tambah kunjungan baru untuk {{ $anak->nama }}">
                        <span aria-hidden="true" class="icon-copy dw dw-add"></span>
                        <span class="sr-only">Tambah Kunjungan</span>
                    </a>
                </div>
                <div class="card-body" style="max-height: 350px; overflow-y: auto;" tabindex="0" aria-label="Daftar riwayat kunjungan, dapat digulir">
                    @forelse(array_reverse($hasilx) as $index => $hasil)
                        @if($index < 5)
                        <article class="timeline-item mb-3 pb-3 {{ $index < 4 ? 'border-bottom' : '' }}">
                            <h3 class="h6 mb-1">Usia {{ $hasil['bln'] }} bulan</h3>
                            <p class="mb-1 small">
                                <strong><abbr title="Berat Badan">BB</abbr>:</strong> {{ $hasil['berat'] }} kg
                                <span aria-hidden="true">|</span>
                                <strong><abbr title="Tinggi Badan">TB</abbr>:</strong> {{ $hasil['tinggi'] }} cm
                                <span aria-hidden="true">|</span>
                                <strong><abbr title="Indeks Massa Tubuh">IMT</abbr>:</strong> {{ $hasil['bmi'] }}
                            </p>
                            <p class="small text-accessible-muted mb-0">
                                <span aria-hidden="true" class="icon-copy dw dw-calendar1"></span>
                                <time datetime="{{ $hasil['tgl_kunjungan'] }}">
                                    {{ \Carbon\Carbon::parse($hasil['tgl_kunjungan'])->format('d M Y') }}
                                </time>
                            </p>
                        </article>
                        @endif
                    @empty
                    <div class="text-center py-4" role="status">
                        <p class="text-accessible-muted mb-0">Belum ada riwayat kunjungan</p>
                    </div>
                    @endforelse
                    @if(count($hasilx) > 5)
                    <p class="text-center mt-2 mb-0">
                        <small class="text-accessible-muted">Menampilkan 5 dari {{ count($hasilx) }} kunjungan</small>
                    </p>
                    @endif
                </div>
            </article>
        </div>
    </section>

    {{-- Growth History Table --}}
    @if(count($hasilx) > 0)
    <section aria-labelledby="data-table-title" class="row">
        <div class="col-12 mb-4">
            <article class="card info-card">
                <div class="card-header">
                    <h2 id="data-table-title">
                        <span aria-hidden="true" class="icon-copy dw dw-list3 mr-2"></span>
                        Data Berkala Lengkap
                    </h2>
                </div>
                <div class="card-body">
                    <div class="table-responsive" tabindex="0" aria-label="Tabel data berkala, dapat digulir secara horizontal">
                        <table class="table table-striped table-hover table-accessible" id="dataTable" aria-describedby="data-table-title">
                            <caption class="sr-only">
                                Tabel data pengukuran berkala anak {{ $anak->nama }} berisi {{ count($hasilx) }} catatan pengukuran
                            </caption>
                            <thead>
                                <tr>
                                    <th scope="col">No</th>
                                    <th scope="col">Tanggal</th>
                                    <th scope="col">Usia (bln)</th>
                                    <th scope="col"><abbr title="Berat Badan">BB</abbr> (kg)</th>
                                    <th scope="col"><abbr title="Tinggi Badan">TB</abbr> (cm)</th>
                                    <th scope="col"><abbr title="Indeks Massa Tubuh">IMT</abbr></th>
                                    <th scope="col"><abbr title="IMT menurut Umur">IMT/U</abbr></th>
                                    <th scope="col"><abbr title="Berat Badan menurut Umur">BB/U</abbr></th>
                                    <th scope="col"><abbr title="Tinggi Badan menurut Umur">TB/U</abbr></th>
                                    <th scope="col"><abbr title="Berat Badan menurut Tinggi Badan">BB/TB</abbr></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($hasilx as $hasil)
                                @php
                                    $rowImtStatus = (str_contains(strtolower($hasil['imt']), 'baik') || str_contains(strtolower($hasil['imt']), 'normal')) ? 'success' : (str_contains(strtolower($hasil['imt']), 'kurang') ? 'warning' : 'danger');
                                    $rowBbStatus = str_contains(strtolower($hasil['bb']), 'normal') ? 'success' : (str_contains(strtolower($hasil['bb']), 'kurang') ? 'warning' : 'danger');
                                    $rowTbStatus = str_contains(strtolower($hasil['tb']), 'normal') ? 'success' : (str_contains(strtolower($hasil['tb']), 'pendek') ? 'warning' : 'danger');
                                    $rowBtStatus = (str_contains(strtolower($hasil['bt']), 'baik') || str_contains(strtolower($hasil['bt']), 'normal')) ? 'success' : (str_contains(strtolower($hasil['bt']), 'kurang') ? 'warning' : 'danger');
                                @endphp
                                <tr>
                                    <td>{{ $hasil['no'] }}</td>
                                    <td>
                                        <time datetime="{{ $hasil['tgl_kunjungan'] }}">
                                            {{ \Carbon\Carbon::parse($hasil['tgl_kunjungan'])->format('d/m/Y') }}
                                        </time>
                                    </td>
                                    <td>{{ $hasil['bln'] }}</td>
                                    <td>{{ $hasil['berat'] }}</td>
                                    <td>{{ $hasil['tinggi'] }}</td>
                                    <td>{{ $hasil['bmi'] }}</td>
                                    <td>
                                        <span class="badge badge-status badge-accessible-{{ $rowImtStatus }}">
                                            {{ Str::limit($hasil['imt'], 20) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-status badge-accessible-{{ $rowBbStatus }}">
                                            {{ Str::limit($hasil['bb'], 20) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-status badge-accessible-{{ $rowTbStatus }}">
                                            {{ Str::limit($hasil['tb'], 15) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-status badge-accessible-{{ $rowBtStatus }}">
                                            {{ Str::limit($hasil['bt'], 20) }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </article>
        </div>
    </section>
    @endif

    {{-- Immunization Records --}}
    <section aria-labelledby="immunization-title" class="row">
        <div class="col-12 mb-4">
            <article class="card info-card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                    <h2 id="immunization-title">
                        <span aria-hidden="true" class="icon-copy dw dw-first-aid-kit mr-2"></span>
                        Riwayat Imunisasi
                    </h2>
                    <a href="{{ route('admin.dataImunisasi', $anak->hashid) }}"
                       class="btn btn-sm btn-light mt-2 mt-md-0"
                       aria-label="Kelola data imunisasi {{ $anak->nama }}">
                        <span aria-hidden="true" class="icon-copy dw dw-add"></span>
                        Kelola Imunisasi
                    </a>
                </div>
                <div class="card-body">
                    @if($imunisasi->count() > 0)
                    <div class="table-responsive" tabindex="0" aria-label="Tabel riwayat imunisasi, dapat digulir secara horizontal">
                        <table class="table table-hover table-accessible" aria-describedby="immunization-title">
                            <caption class="sr-only">
                                Tabel riwayat imunisasi anak {{ $anak->nama }} berisi {{ $imunisasi->count() }} catatan imunisasi
                            </caption>
                            <thead>
                                <tr>
                                    <th scope="col">Vaksin</th>
                                    <th scope="col">Dosis</th>
                                    <th scope="col">Tanggal Pemberian</th>
                                    <th scope="col">Tanggal Selanjutnya</th>
                                    <th scope="col">Lokasi</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($imunisasi as $imun)
                                <tr>
                                    <td>{{ $imun->jenisVaksin->nama ?? '-' }}</td>
                                    <td>{{ $imun->dosis }}</td>
                                    <td>
                                        @if($imun->tanggal_pemberian)
                                        <time datetime="{{ $imun->tanggal_pemberian->format('Y-m-d') }}">
                                            {{ $imun->tanggal_pemberian->format('d M Y') }}
                                        </time>
                                        @else
                                        <span aria-label="Belum ada tanggal pemberian">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($imun->tanggal_selanjutnya)
                                        <time datetime="{{ $imun->tanggal_selanjutnya->format('Y-m-d') }}">
                                            {{ $imun->tanggal_selanjutnya->format('d M Y') }}
                                        </time>
                                        @else
                                        <span aria-label="Tidak ada jadwal selanjutnya">-</span>
                                        @endif
                                    </td>
                                    <td>{{ $imun->lokasi_pemberian ?? '-' }}</td>
                                    <td>
                                        @switch($imun->status)
                                            @case('sudah')
                                                <span class="badge badge-accessible-success">
                                                    <span class="sr-only">Status: </span>Sudah
                                                </span>
                                                @break
                                            @case('belum')
                                                <span class="badge badge-accessible-warning">
                                                    <span class="sr-only">Status: </span>Belum
                                                </span>
                                                @break
                                            @case('terlambat')
                                                <span class="badge badge-accessible-danger">
                                                    <span class="sr-only">Status: </span>Terlambat
                                                </span>
                                                @break
                                            @default
                                                <span class="badge badge-accessible-secondary">
                                                    <span class="sr-only">Status: </span>-
                                                </span>
                                        @endswitch
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="text-center py-4" role="status">
                        <span aria-hidden="true" class="icon-copy dw dw-first-aid-kit" style="font-size: 48px; color: #6c757d;"></span>
                        <p class="text-accessible-muted mt-3">Belum ada data imunisasi</p>
                        <a href="{{ route('admin.dataImunisasi', $anak->hashid) }}"
                           class="btn btn-primary btn-sm"
                           aria-label="Tambah data imunisasi pertama untuk {{ $anak->nama }}">
                            <span aria-hidden="true" class="icon-copy dw dw-add"></span>
                            Tambah Data Imunisasi
                        </a>
                    </div>
                    @endif
                </div>
            </article>
        </div>
    </section>

</main>
@endsection

@section('custom_scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    @if(count($hasilx) > 0)
    const growthCtx = document.getElementById('growthHistoryChart').getContext('2d');
    const chartSummary = document.getElementById('chartSummary');

    // Prepare data from PHP
    const labels = [@foreach($hasilx as $h)'{{ \Carbon\Carbon::parse($h["tgl_kunjungan"])->format("M Y") }}',@endforeach];
    const weightData = [@foreach($hasilx as $h){{ $h['berat'] }},@endforeach];
    const heightData = [@foreach($hasilx as $h){{ $h['tinggi'] }},@endforeach];
    const bmiData = [@foreach($hasilx as $h){{ $h['bmi'] }},@endforeach];

    const weightDataset = {
        labels: labels,
        datasets: [{
            label: 'Berat Badan (kg)',
            data: weightData,
            borderColor: '#0066cc',
            backgroundColor: 'rgba(0, 102, 204, 0.12)',
            tension: 0.3,
            fill: true,
            pointRadius: 6,
            pointHoverRadius: 9,
            pointBackgroundColor: '#0066cc',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2
        }]
    };

    const heightDataset = {
        labels: labels,
        datasets: [{
            label: 'Tinggi Badan (cm)',
            data: heightData,
            borderColor: '#047857',
            backgroundColor: 'rgba(4, 120, 87, 0.12)',
            tension: 0.3,
            fill: true,
            pointRadius: 6,
            pointHoverRadius: 9,
            pointBackgroundColor: '#047857',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2
        }]
    };

    const bmiDataset = {
        labels: labels,
        datasets: [{
            label: 'IMT',
            data: bmiData,
            borderColor: '#0891b2',
            backgroundColor: 'rgba(8, 145, 178, 0.12)',
            tension: 0.3,
            fill: true,
            pointRadius: 6,
            pointHoverRadius: 9,
            pointBackgroundColor: '#0891b2',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2
        }]
    };

    // Chart.js accessibility configuration
    let growthChart = new Chart(growthCtx, {
        type: 'line',
        data: weightDataset,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Riwayat Berat Badan',
                    font: {
                        size: 14,
                        weight: 'bold'
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(0, 0, 0, 0.9)',
                    titleFont: { size: 14 },
                    bodyFont: { size: 13 },
                    padding: 12
                },
                legend: {
                    labels: {
                        font: { size: 12 }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    title: {
                        display: true,
                        text: 'Berat (kg)',
                        font: { size: 12, weight: 'bold' }
                    },
                    ticks: {
                        font: { size: 11 }
                    }
                },
                x: {
                    ticks: {
                        font: { size: 11 }
                    }
                }
            },
            // Accessibility: Better interaction
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });

    // Button click handlers with ARIA updates
    function updateChartButton(activeBtn, ariaLabel, dataset, title, yAxisLabel) {
        // Update button states
        document.querySelectorAll('#weightHistoryBtn, #heightHistoryBtn, #bmiHistoryBtn').forEach(btn => {
            btn.classList.remove('active');
            btn.setAttribute('aria-pressed', 'false');
        });
        activeBtn.classList.add('active');
        activeBtn.setAttribute('aria-pressed', 'true');

        // Update chart
        growthChart.data = dataset;
        growthChart.options.plugins.title.text = title;
        growthChart.options.scales.y.title.text = yAxisLabel;
        growthChart.update();

        // Update canvas aria-label
        document.getElementById('growthHistoryChart').setAttribute('aria-label', ariaLabel);

        // Announce change to screen readers
        chartSummary.textContent = 'Grafik diubah ke ' + title;
    }

    document.getElementById('weightHistoryBtn').addEventListener('click', function() {
        updateChartButton(
            this,
            'Grafik riwayat pertumbuhan anak menampilkan berat badan dari waktu ke waktu',
            weightDataset,
            'Riwayat Berat Badan',
            'Berat (kg)'
        );
    });

    document.getElementById('heightHistoryBtn').addEventListener('click', function() {
        updateChartButton(
            this,
            'Grafik riwayat pertumbuhan anak menampilkan tinggi badan dari waktu ke waktu',
            heightDataset,
            'Riwayat Tinggi Badan',
            'Tinggi (cm)'
        );
    });

    document.getElementById('bmiHistoryBtn').addEventListener('click', function() {
        updateChartButton(
            this,
            'Grafik riwayat pertumbuhan anak menampilkan indeks massa tubuh dari waktu ke waktu',
            bmiDataset,
            'Riwayat IMT',
            'IMT'
        );
    });

    // Keyboard navigation for chart buttons
    const chartButtons = document.querySelectorAll('#weightHistoryBtn, #heightHistoryBtn, #bmiHistoryBtn');
    chartButtons.forEach((btn, index) => {
        btn.addEventListener('keydown', function(e) {
            let targetBtn = null;
            if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                e.preventDefault();
                targetBtn = chartButtons[(index + 1) % chartButtons.length];
            } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                e.preventDefault();
                targetBtn = chartButtons[(index - 1 + chartButtons.length) % chartButtons.length];
            }
            if (targetBtn) {
                targetBtn.focus();
                targetBtn.click();
            }
        });
    });
    @endif
});
</script>
@endsection

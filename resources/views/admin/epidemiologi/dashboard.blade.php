@extends('admin::layouts.app')
@section('title') Dashboard Analytics Surveillance @endsection
@section('title-content') Epidemiologi @endsection
@section('item') Surveillance @endsection
@section('item-active') Dashboard Analytics @endsection

@section('content')
{{-- Skip Link for Accessibility --}}
<a href="#main-content" class="sr-only sr-only-focusable skip-link">Langsung ke konten utama</a>

<style>
    @include('admin.epidemiologi.components.shared-styles')

    /* Dashboard-specific styles */
    .chart-container {
        position: relative;
        height: 300px;
        width: 100%;
    }
    .chart-container-lg {
        position: relative;
        height: 250px;
        width: 100%;
    }
    .chart-container-tall {
        position: relative;
        height: 400px;
        width: 100%;
    }
</style>

<div class="container-fluid" id="main-content" role="main" aria-label="Dashboard Analytics Surveillance">
    <!-- Header Section -->
    <header class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0" style="color: var(--primary-blue-dark);">
            <i class="fa fa-chart-line mr-2" aria-hidden="true"></i>
            Dashboard Analytics Surveillance
        </h2>
        <nav aria-label="Navigasi modul epidemiologi">
            <a href="{{ route('admin.epidemiologi.map') }}" class="btn btn-outline-success" aria-label="Buka Peta Sebaran">
                <i class="fa fa-map-marked-alt" aria-hidden="true"></i> Peta Sebaran
            </a>
            <a href="{{ route('admin.epidemiologi.index') }}" class="btn btn-outline-primary" aria-label="Buka Daftar Kasus">
                <i class="fa fa-list" aria-hidden="true"></i> Daftar Kasus
            </a>
            <a href="{{ route('admin.epidemiologi.create') }}" class="btn btn-primary" aria-label="Tambah Kasus Baru">
                <i class="fa fa-plus" aria-hidden="true"></i> Tambah Kasus
            </a>
        </nav>
    </header>

    <!-- Summary Stats Cards -->
    <section aria-labelledby="stats-section-title" class="row mb-4">
        <h2 id="stats-section-title" class="sr-only">Ringkasan Statistik Kasus</h2>

        <div class="col-xl-3 col-md-6 mb-3" role="listitem">
            <div class="card stat-card status-info h-100">
                <div class="card-body text-center py-4">
                    <h3 class="h6 text-accessible-muted mb-2">Total Kasus</h3>
                    <p class="h2 mb-1" aria-label="{{ $stats['total_cases'] }} total kasus">
                        {{ $stats['total_cases'] }}
                    </p>
                    <p class="small text-accessible-muted mb-0">
                        <i class="fa fa-virus" aria-hidden="true"></i> Semua status
                    </p>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3" role="listitem">
            <div class="card stat-card status-warning h-100">
                <div class="card-body text-center py-4">
                    <h3 class="h6 text-accessible-muted mb-2">Suspected</h3>
                    <p class="h2 mb-1" aria-label="{{ $stats['suspected_cases'] }} kasus suspected">
                        {{ $stats['suspected_cases'] }}
                    </p>
                    <p class="small text-accessible-muted mb-0">
                        <i class="fa fa-question-circle" aria-hidden="true"></i> Menunggu konfirmasi
                    </p>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3" role="listitem">
            <div class="card stat-card status-danger h-100">
                <div class="card-body text-center py-4">
                    <h3 class="h6 text-accessible-muted mb-2">Confirmed</h3>
                    <p class="h2 mb-1" aria-label="{{ $stats['confirmed_cases'] }} kasus confirmed">
                        {{ $stats['confirmed_cases'] }}
                    </p>
                    <p class="small text-accessible-muted mb-0">
                        <i class="fa fa-exclamation-triangle" aria-hidden="true"></i> Terkonfirmasi
                    </p>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3" role="listitem">
            <div class="card stat-card status-success h-100">
                <div class="card-body text-center py-4">
                    <h3 class="h6 text-accessible-muted mb-2">Sembuh</h3>
                    <p class="h2 mb-1" aria-label="{{ $stats['recovered_cases'] }} kasus sembuh">
                        {{ $stats['recovered_cases'] }}
                    </p>
                    <p class="small text-accessible-muted mb-0">
                        <i class="fa fa-heartbeat" aria-hidden="true"></i> Pulih total
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Charts Row 1 -->
    <section aria-labelledby="charts-row1-title" class="row mb-4">
        <h2 id="charts-row1-title" class="sr-only">Grafik Distribusi Kasus</h2>

        <!-- Disease Distribution Chart -->
        <div class="col-lg-6 mb-3">
            <article class="card info-card h-100">
                <div class="card-header" style="background: linear-gradient(135deg, var(--danger-rose) 0%, #e11d48 100%) !important;">
                    <h2 id="disease-chart-title">
                        <i class="fa fa-chart-pie" aria-hidden="true"></i> Distribusi Kasus per Jenis Penyakit
                    </h2>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="diseaseChart" role="img" aria-label="Grafik batang horizontal distribusi kasus per jenis penyakit"></canvas>
                    </div>
                </div>
            </article>
        </div>

        <!-- Status Distribution Chart -->
        <div class="col-lg-6 mb-3">
            <article class="card info-card h-100">
                <div class="card-header" style="background: linear-gradient(135deg, var(--info-teal) 0%, #0e7490 100%) !important;">
                    <h2 id="status-chart-title">
                        <i class="fa fa-chart-pie" aria-hidden="true"></i> Distribusi Status Kasus
                    </h2>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="statusChart" role="img" aria-label="Grafik donat distribusi status kasus"></canvas>
                    </div>
                </div>
            </article>
        </div>
    </section>

    <!-- Charts Row 2 -->
    <section aria-labelledby="charts-row2-title" class="row mb-4">
        <h2 id="charts-row2-title" class="sr-only">Grafik Tren dan Sebaran Geografis</h2>

        <!-- Trend Chart -->
        <div class="col-lg-8 mb-3">
            <article class="card info-card h-100">
                <div class="card-header" style="background: linear-gradient(135deg, var(--success-green) 0%, #059669 100%) !important;">
                    <h2 id="trend-chart-title">
                        <i class="fa fa-chart-line" aria-hidden="true"></i> Trend Kasus Bulanan (12 Bulan Terakhir)
                    </h2>
                </div>
                <div class="card-body">
                    <div class="chart-container-lg">
                        <canvas id="trendChart" role="img" aria-label="Grafik garis tren kasus bulanan 12 bulan terakhir"></canvas>
                    </div>
                </div>
            </article>
        </div>

        <!-- Geographic Distribution -->
        <div class="col-lg-4 mb-3">
            <article class="card info-card h-100">
                <div class="card-header" style="background: linear-gradient(135deg, var(--warning-amber) 0%, #d97706 100%) !important;">
                    <h2 id="geo-chart-title">
                        <i class="fa fa-map-marker-alt" aria-hidden="true"></i> Top 10 Kecamatan
                    </h2>
                </div>
                <div class="card-body">
                    <div class="chart-container-tall">
                        <canvas id="geoChart" role="img" aria-label="Grafik batang horizontal top 10 kecamatan dengan kasus terbanyak"></canvas>
                    </div>
                </div>
            </article>
        </div>
    </section>

    <!-- Recent Cases Table -->
    <section aria-labelledby="recent-cases-title" class="row">
        <div class="col-lg-12">
            <article class="card info-card">
                <div class="card-header" style="background: linear-gradient(135deg, var(--text-muted) 0%, #374151 100%) !important;">
                    <h2 id="recent-cases-title">
                        <i class="fa fa-list" aria-hidden="true"></i> Kasus Terbaru (10 Terakhir)
                    </h2>
                </div>
                <div class="card-body">
                    <div class="table-responsive" tabindex="0" aria-label="Tabel kasus terbaru, dapat digulir horizontal">
                        <table class="table table-sm table-striped table-hover table-accessible" aria-describedby="recent-cases-title">
                            <caption class="sr-only">Tabel 10 kasus surveillance terbaru</caption>
                            <thead>
                                <tr>
                                    <th scope="col">No. Reg</th>
                                    <th scope="col">Nama</th>
                                    <th scope="col">Penyakit</th>
                                    <th scope="col">Lokasi</th>
                                    <th scope="col">Tanggal Onset</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentCases as $case)
                                <tr>
                                    <td><strong>{{ $case->no_registrasi }}</strong></td>
                                    <td>{{ $case->nama_lengkap }}</td>
                                    <td>{{ $case->jenisKasus->nama_penyakit ?? '-' }}</td>
                                    <td>{{ $case->kecamatan->name ?? '-' }} / {{ $case->kelurahan->name ?? '-' }}</td>
                                    <td>
                                        <time datetime="{{ $case->tanggal_onset->format('Y-m-d') }}">
                                            {{ $case->tanggal_onset->format('d/m/Y') }}
                                        </time>
                                    </td>
                                    <td>
                                        @php
                                            $statusClass = match($case->status_kasus) {
                                                'confirmed' => 'badge-accessible-danger',
                                                'suspected' => 'badge-accessible-warning',
                                                'probable'  => 'badge-accessible-info',
                                                default     => 'badge-accessible-secondary',
                                            };
                                        @endphp
                                        <span class="badge badge-status {{ $statusClass }}">
                                            {{ ucfirst($case->status_kasus) }}
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.epidemiologi.show', $case->id) }}" class="btn btn-sm btn-outline-info" aria-label="Lihat detail kasus {{ $case->no_registrasi }}">
                                            <i class="fa fa-eye" aria-hidden="true"></i>
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center text-accessible-muted py-4" role="status">
                                        <i class="fa fa-inbox fa-2x mb-2 d-block" aria-hidden="true"></i>
                                        Belum ada data kasus
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </article>
        </div>
    </section>
</div>
@endsection

@section('scripts')
@parent
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
$(document).ready(function() {
    // WCAG AA Compliant Chart Colors
    const colors = {
        primary: 'rgba(0, 102, 204, 0.85)',    // var(--primary-blue)
        success: 'rgba(4, 120, 87, 0.85)',      // var(--success-green)
        info:    'rgba(8, 145, 178, 0.85)',      // var(--info-teal)
        warning: 'rgba(180, 83, 9, 0.85)',       // var(--warning-amber)
        danger:  'rgba(190, 18, 60, 0.85)',      // var(--danger-rose)
        secondary: 'rgba(75, 85, 99, 0.85)',     // var(--text-muted)
        primaryLight: 'rgba(0, 102, 204, 0.15)',
        successLight: 'rgba(4, 120, 87, 0.15)',
    };

    // 1. Disease Distribution Chart (Horizontal Bar)
    const diseaseData = @json($diseaseData);
    const diseaseLabels = diseaseData.map(item => item.jenis_kasus ? item.jenis_kasus.nama_penyakit : 'Unknown');
    const diseaseCounts = diseaseData.map(item => item.total);

    new Chart(document.getElementById('diseaseChart'), {
        type: 'bar',
        data: {
            labels: diseaseLabels,
            datasets: [{
                label: 'Jumlah Kasus',
                data: diseaseCounts,
                backgroundColor: [
                    colors.danger,
                    colors.warning,
                    colors.info,
                    colors.success,
                    colors.primary,
                    colors.secondary
                ],
                borderWidth: 0,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: { beginAtZero: true, ticks: { precision: 0 } }
            }
        }
    });

    // 2. Status Distribution Chart (Doughnut)
    const statusData = @json($statusData);
    const statusLabels = statusData.map(item => item.status_kasus.charAt(0).toUpperCase() + item.status_kasus.slice(1));
    const statusCounts = statusData.map(item => item.total);

    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusCounts,
                backgroundColor: [
                    colors.warning,
                    colors.info,
                    colors.danger,
                    colors.secondary
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    // 3. Trend Chart (Line)
    const trendData = @json($trendData);
    const trendLabels = trendData.map(item => {
        const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        return monthNames[item.month - 1] + ' ' + item.year;
    });
    const trendCounts = trendData.map(item => item.total);

    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [{
                label: 'Jumlah Kasus',
                data: trendCounts,
                borderColor: colors.success,
                backgroundColor: colors.successLight,
                fill: true,
                tension: 0.4,
                borderWidth: 2,
                pointRadius: 4,
                pointBackgroundColor: colors.success
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } }
            }
        }
    });

    // 4. Geographic Distribution Chart (Horizontal Bar)
    const geoData = @json($geoData);
    const geoLabels = geoData.slice(0, 10).map(item => item.kecamatan ? item.kecamatan.name : 'Unknown');
    const geoCounts = geoData.slice(0, 10).map(item => item.total);

    new Chart(document.getElementById('geoChart'), {
        type: 'bar',
        data: {
            labels: geoLabels,
            datasets: [{
                label: 'Jumlah Kasus',
                data: geoCounts,
                backgroundColor: colors.warning,
                borderWidth: 0,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, ticks: { precision: 0 } }
            }
        }
    });
});
</script>
@endsection

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
    .filter-active {
        border: 2px solid var(--primary) !important;
        box-shadow: 0 0 0 0.15rem oklch(0.48 0.14 145 / 0.25);
    }
    .dashboard-loading {
        position: relative;
        pointer-events: none;
        opacity: 0.5;
    }
    .dashboard-loading::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 32px;
        height: 32px;
        margin: -16px 0 0 -16px;
        border: 3px solid #e5e7eb;
        border-top-color: var(--primary);
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
        z-index: 10;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
</style>

<div class="container-fluid" id="main-content" role="main" aria-label="Dashboard Analytics Surveillance">
    <!-- Header Section -->
    <header class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0" style="color: var(--primary-dark);">
            <i class="fa fa-chart-line mr-2" aria-hidden="true"></i>
            Dashboard Analytics Surveillance
        </h2>
        <nav aria-label="Navigasi modul epidemiologi">
            <a href="{{ route('admin.pd3i.dashboard') }}" class="btn btn-outline-success" aria-label="Buka Peta Sebaran (Dasbor PD3I)">
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

    <!-- Disease Filter -->
    <div class="card mb-4">
        <div class="card-body py-3">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <div class="form-group mb-0">
                        <label for="diseaseFilter" class="mb-1"><i class="fa fa-filter"></i> Filter Jenis Penyakit</label>
                        <select id="diseaseFilter" class="form-control">
                            <option value="">Semua Penyakit</option>
                            @foreach($diseases as $disease)
                                <option value="{{ $disease->id }}">{{ $disease->nama_penyakit }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="button" id="resetDiseaseFilter" class="btn btn-outline-secondary mt-md-4" style="display:none;">
                        <i class="fa fa-times"></i> Reset Filter
                    </button>
                    <span id="filterLabel" class="ml-2 mt-md-4 text-muted" style="display:none;">
                        <i class="fa fa-info-circle"></i> Menampilkan data untuk: <strong id="filterName"></strong>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Stats Cards -->
    <section aria-labelledby="stats-section-title" class="row mb-4" id="statsSection">
        <h2 id="stats-section-title" class="sr-only">Ringkasan Statistik Kasus</h2>

        <div class="col-xl-3 col-md-6 mb-3" role="listitem">
            <div class="card stat-card status-info h-100">
                <div class="card-body text-center py-4">
                    <h3 class="h6 text-accessible-muted mb-2">Total Kasus</h3>
                    <p class="h2 mb-1" id="statTotal">{{ $stats['total_cases'] }}</p>
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
                    <p class="h2 mb-1" id="statSuspected">{{ $stats['suspected_cases'] }}</p>
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
                    <p class="h2 mb-1" id="statConfirmed">{{ $stats['confirmed_cases'] }}</p>
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
                    <p class="h2 mb-1" id="statRecovered">{{ $stats['recovered_cases'] }}</p>
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
            <article class="card info-card h-100" id="diseaseChartCard">
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
            <article class="card info-card h-100" id="statusChartCard">
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
            <article class="card info-card h-100" id="trendChartCard">
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
            <article class="card info-card h-100" id="geoChartCard">
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

    <!-- Charts Row 3: Facility Distribution -->
    <section aria-labelledby="charts-row3-title" class="row mb-4">
        <h2 id="charts-row3-title" class="sr-only">Distribusi Kasus Berdasarkan Fasilitas</h2>

        <div class="col-lg-6 mb-3">
            <article class="card info-card h-100" id="facilityChartCard">
                <div class="card-header" style="background: linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%) !important;">
                    <h2 id="facility-chart-title">
                        <i class="fa fa-building" aria-hidden="true"></i> Distribusi Kasus Berdasarkan Lokasi Penularan
                    </h2>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="facilityChart" role="img" aria-label="Grafik distribusi kasus berdasarkan lokasi penularan di fasilitas umum"></canvas>
                    </div>
                </div>
            </article>
        </div>
    </section>

    <!-- Recent Cases Table -->
    <section aria-labelledby="recent-cases-title" class="row">
        <div class="col-lg-12">
            <article class="card info-card" id="recentCasesCard">
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
                            <tbody id="recentCasesBody">
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
    const chartColors = {
        primary: 'rgba(0, 102, 204, 0.85)',
        success: 'rgba(4, 120, 87, 0.85)',
        info:    'rgba(8, 145, 178, 0.85)',
        warning: 'rgba(180, 83, 9, 0.85)',
        danger:  'rgba(190, 18, 60, 0.85)',
        secondary: 'rgba(75, 85, 99, 0.85)',
        primaryLight: 'rgba(0, 102, 204, 0.15)',
        successLight: 'rgba(4, 120, 87, 0.15)',
    };

    const barColors = [
        chartColors.danger,
        chartColors.warning,
        chartColors.info,
        chartColors.success,
        chartColors.primary,
        chartColors.secondary
    ];

    // Chart instances (for destroy/recreate on filter)
    let diseaseChartInstance = null;
    let statusChartInstance = null;
    let trendChartInstance = null;
    let geoChartInstance = null;
    let facilityChartInstance = null;

    // Initial data from server
    let currentData = {
        diseaseData: @json($diseaseData),
        statusData: @json($statusData),
        trendData: @json($trendData),
        geoData: @json($geoData),
        facilityData: @json($facilityData ?? []),
    };

    function renderAllCharts(data) {
        renderDiseaseChart(data.diseaseData || []);
        renderStatusChart(data.statusData || []);
        renderTrendChart(data.trendData || []);
        renderGeoChart(data.geoData || []);
        renderFacilityChart(data.facilityData || []);
    }

    function renderDiseaseChart(diseaseData) {
        if (diseaseChartInstance) diseaseChartInstance.destroy();

        var labels = diseaseData.map(function(item) {
            return item.jenis_kasus ? item.jenis_kasus.nama_penyakit : 'Unknown';
        });
        var counts = diseaseData.map(function(item) { return item.total; });

        diseaseChartInstance = new Chart(document.getElementById('diseaseChart'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Jumlah Kasus',
                    data: counts,
                    backgroundColor: barColors.slice(0, labels.length),
                    borderWidth: 0,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    }

    function renderStatusChart(statusData) {
        if (statusChartInstance) statusChartInstance.destroy();

        var labels = statusData.map(function(item) {
            return item.status_kasus.charAt(0).toUpperCase() + item.status_kasus.slice(1);
        });
        var counts = statusData.map(function(item) { return item.total; });

        var statusColors = statusData.map(function(item) {
            switch (item.status_kasus) {
                case 'suspected': return chartColors.warning;
                case 'probable':  return chartColors.info;
                case 'confirmed': return chartColors.danger;
                case 'discarded': return chartColors.secondary;
                default:          return chartColors.secondary;
            }
        });

        statusChartInstance = new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: counts,
                    backgroundColor: statusColors,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }

    function renderTrendChart(trendData) {
        if (trendChartInstance) trendChartInstance.destroy();

        var monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        var labels = trendData.map(function(item) {
            return monthNames[item.month - 1] + ' ' + item.year;
        });
        var counts = trendData.map(function(item) { return item.total; });

        trendChartInstance = new Chart(document.getElementById('trendChart'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Jumlah Kasus',
                    data: counts,
                    borderColor: chartColors.success,
                    backgroundColor: chartColors.successLight,
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2,
                    pointRadius: 4,
                    pointBackgroundColor: chartColors.success
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    }

    function renderGeoChart(geoData) {
        if (geoChartInstance) geoChartInstance.destroy();

        var sliced = geoData.slice(0, 10);
        var labels = sliced.map(function(item) {
            return item.kecamatan ? item.kecamatan.name : 'Unknown';
        });
        var counts = sliced.map(function(item) { return item.total; });

        geoChartInstance = new Chart(document.getElementById('geoChart'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Jumlah Kasus',
                    data: counts,
                    backgroundColor: chartColors.warning,
                    borderWidth: 0,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    }

    function renderFacilityChart(facilityData) {
        if (facilityChartInstance) facilityChartInstance.destroy();

        var canvas = document.getElementById('facilityChart');
        if (!facilityData || facilityData.length === 0) {
            facilityChartInstance = new Chart(canvas, {
                type: 'bar',
                data: { labels: ['Tidak ada data'], datasets: [{ data: [0], backgroundColor: chartColors.secondary }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
            });
            return;
        }

        var labels = facilityData.map(function(item) { return item.kategori; });
        var counts = facilityData.map(function(item) { return item.total; });

        facilityChartInstance = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Jumlah Kasus',
                    data: counts,
                    backgroundColor: barColors.slice(0, labels.length),
                    borderWidth: 0,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    }

    function updateStats(stats) {
        $('#statTotal').text(stats.total_cases);
        $('#statSuspected').text(stats.suspected_cases);
        $('#statConfirmed').text(stats.confirmed_cases);
        $('#statRecovered').text(stats.recovered_cases);
    }

    function updateRecentCases(cases) {
        var $tbody = $('#recentCasesBody');
        $tbody.empty();

        if (!cases || cases.length === 0) {
            $tbody.append(
                '<tr><td colspan="7" class="text-center text-accessible-muted py-4">' +
                '<i class="fa fa-inbox fa-2x mb-2 d-block"></i>Belum ada data kasus</td></tr>'
            );
            return;
        }

        cases.forEach(function(c) {
            var statusClass = 'badge-accessible-secondary';
            if (c.status_kasus === 'confirmed') statusClass = 'badge-accessible-danger';
            else if (c.status_kasus === 'suspected') statusClass = 'badge-accessible-warning';
            else if (c.status_kasus === 'probable') statusClass = 'badge-accessible-info';

            var statusLabel = c.status_kasus ? c.status_kasus.charAt(0).toUpperCase() + c.status_kasus.slice(1) : '-';

            $tbody.append(
                '<tr>' +
                '<td><strong>' + (c.no_registrasi || '-') + '</strong></td>' +
                '<td>' + c.nama_lengkap + '</td>' +
                '<td>' + c.penyakit + '</td>' +
                '<td>' + c.kecamatan + ' / ' + c.kelurahan + '</td>' +
                '<td><time datetime="' + c.tanggal_onset_iso + '">' + c.tanggal_onset + '</time></td>' +
                '<td><span class="badge badge-status ' + statusClass + '">' + statusLabel + '</span></td>' +
                '<td><a href="' + c.show_url + '" class="btn btn-sm btn-outline-info"><i class="fa fa-eye"></i></a></td>' +
                '</tr>'
            );
        });
    }

    function setLoading(on) {
        var sections = ['#statsSection', '#diseaseChartCard', '#statusChartCard', '#trendChartCard', '#geoChartCard', '#facilityChartCard', '#recentCasesCard'];
        sections.forEach(function(s) {
            if (on) $(s).addClass('dashboard-loading');
            else $(s).removeClass('dashboard-loading');
        });
    }

    function loadFilteredData(diseaseId) {
        setLoading(true);

        $.ajax({
            url: '{{ route("admin.epidemiologi.dashboardData") }}',
            type: 'GET',
            data: { disease_id: diseaseId || '' },
            success: function(response) {
                updateStats(response.stats);
                renderAllCharts(response);
                updateRecentCases(response.recentCases);
                setLoading(false);
            },
            error: function() {
                alert('Gagal memuat data dashboard');
                setLoading(false);
            }
        });
    }

    // Disease filter handler
    $('#diseaseFilter').on('change', function() {
        var diseaseId = $(this).val();
        var diseaseName = $(this).find('option:selected').text();

        if (diseaseId) {
            $(this).addClass('filter-active');
            $('#resetDiseaseFilter').show();
            $('#filterLabel').show();
            $('#filterName').text(diseaseName);
        } else {
            $(this).removeClass('filter-active');
            $('#resetDiseaseFilter').hide();
            $('#filterLabel').hide();
        }

        loadFilteredData(diseaseId);
    });

    $('#resetDiseaseFilter').on('click', function() {
        $('#diseaseFilter').val('').removeClass('filter-active');
        $(this).hide();
        $('#filterLabel').hide();
        loadFilteredData('');
    });

    // Initial render
    renderAllCharts(currentData);
});
</script>
@endsection

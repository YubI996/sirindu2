@extends('admin::layouts.app')
@section('title') Admin @endsection
@section('title-content') Epidemiologi @endsection
@section('item') Surveillance @endsection
@section('item-active') Dashboard Analytics @endsection

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">
            <i class="fa fa-chart-line mr-2"></i>
            Dashboard Analytics Surveillance
        </h2>
        <div>
            <a href="{{ route('admin.epidemiologi.map') }}" class="btn btn-success">
                <i class="fa fa-map-marked-alt"></i> Peta Sebaran
            </a>
            <a href="{{ route('admin.epidemiologi.index') }}" class="btn btn-primary">
                <i class="fa fa-list"></i> Daftar Kasus
            </a>
            <a href="{{ route('admin.epidemiologi.create') }}" class="btn btn-warning">
                <i class="fa fa-plus"></i> Tambah Kasus
            </a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Kasus
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['total_cases'] }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-virus fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Suspected
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['suspected_cases'] }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-question-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Confirmed
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['confirmed_cases'] }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Sembuh
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['recovered_cases'] }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-heartbeat fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 1 -->
    <div class="row mb-4">
        <!-- Disease Distribution Chart -->
        <div class="col-lg-6 mb-3">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fa fa-chart-pie"></i> Distribusi Kasus per Jenis Penyakit
                    </h6>
                </div>
                <div class="card-body">
                    <canvas id="diseaseChart" height="300"></canvas>
                </div>
            </div>
        </div>

        <!-- Status Distribution Chart -->
        <div class="col-lg-6 mb-3">
            <div class="card shadow">
                <div class="card-header bg-info text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fa fa-chart-pie"></i> Distribusi Status Kasus
                    </h6>
                </div>
                <div class="card-body">
                    <canvas id="statusChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 2 -->
    <div class="row mb-4">
        <!-- Trend Chart -->
        <div class="col-lg-8 mb-3">
            <div class="card shadow">
                <div class="card-header bg-success text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fa fa-chart-line"></i> Trend Kasus Bulanan (12 Bulan Terakhir)
                    </h6>
                </div>
                <div class="card-body">
                    <canvas id="trendChart" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Geographic Distribution -->
        <div class="col-lg-4 mb-3">
            <div class="card shadow">
                <div class="card-header bg-warning text-dark">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fa fa-map-marker-alt"></i> Top 10 Kecamatan
                    </h6>
                </div>
                <div class="card-body">
                    <canvas id="geoChart" height="400"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Cases Table -->
    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow">
                <div class="card-header bg-secondary text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fa fa-list"></i> Kasus Terbaru (10 Terakhir)
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>No. Reg</th>
                                    <th>Nama</th>
                                    <th>Penyakit</th>
                                    <th>Lokasi</th>
                                    <th>Tanggal Onset</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentCases as $case)
                                <tr>
                                    <td><strong>{{ $case->no_registrasi }}</strong></td>
                                    <td>{{ $case->nama_lengkap }}</td>
                                    <td>{{ $case->jenisKasus->nama_penyakit ?? '-' }}</td>
                                    <td>{{ $case->kecamatan->name ?? '-' }} / {{ $case->kelurahan->name ?? '-' }}</td>
                                    <td>{{ $case->tanggal_onset->format('d/m/Y') }}</td>
                                    <td>
                                        <span class="badge badge-{{ $case->status_kasus == 'confirmed' ? 'danger' : ($case->status_kasus == 'suspected' ? 'warning' : 'secondary') }}">
                                            {{ ucfirst($case->status_kasus) }}
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.epidemiologi.show', $case->id) }}" class="btn btn-sm btn-info">
                                            <i class="fa fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted">Belum ada data kasus</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
$(document).ready(function() {
    // Chart Colors
    const colors = {
        primary: 'rgba(78, 115, 223, 0.8)',
        success: 'rgba(28, 200, 138, 0.8)',
        info: 'rgba(54, 185, 204, 0.8)',
        warning: 'rgba(246, 194, 62, 0.8)',
        danger: 'rgba(231, 74, 59, 0.8)',
        secondary: 'rgba(133, 135, 150, 0.8)'
    };

    // 1. Disease Distribution Chart (Bar Chart)
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
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
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
                    colors.warning,  // suspected
                    colors.info,     // probable
                    colors.danger,   // confirmed
                    colors.secondary // discarded
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // 3. Trend Chart (Line Chart)
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
                backgroundColor: 'rgba(28, 200, 138, 0.1)',
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
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
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
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
});
</script>
@endsection

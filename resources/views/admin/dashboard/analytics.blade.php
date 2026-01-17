@extends('admin::layouts.app')
@section('title')
Dashboard Analytics - Si Rindu
@endsection
@section('title-content')
Dashboard Analytics
@endsection
@section('item')
Dashboard
@endsection
@section('item-active')
Analytics
@endsection

@section('content')
<style>
    :root {
        --primary-blue: #0066cc;
        --primary-blue-dark: #004d99;
        --success-green: #047857;
        --warning-amber: #b45309;
        --danger-rose: #be123c;
        --info-teal: #0891b2;
    }

    .stat-card {
        border-radius: 12px;
        border: none;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        overflow: hidden;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 102, 204, 0.15);
    }

    .stat-card .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
    }

    .stat-card.primary .stat-icon { background: linear-gradient(135deg, #0066cc 0%, #0891b2 100%); color: #fff; }
    .stat-card.success .stat-icon { background: linear-gradient(135deg, #047857 0%, #10b981 100%); color: #fff; }
    .stat-card.warning .stat-icon { background: linear-gradient(135deg, #b45309 0%, #f59e0b 100%); color: #fff; }
    .stat-card.danger .stat-icon { background: linear-gradient(135deg, #be123c 0%, #f43f5e 100%); color: #fff; }

    .stat-value { font-size: 2rem; font-weight: 700; color: #1f2937; }
    .stat-label { color: #6b7280; font-size: 0.875rem; }

    .chart-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .chart-card h5 {
        color: #1f2937;
        font-weight: 600;
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid #e5e7eb;
    }

    .chart-container {
        position: relative;
        height: 300px;
    }

    .chart-container-sm {
        position: relative;
        height: 250px;
    }

    .legend-item {
        display: flex;
        align-items: center;
        padding: 0.5rem 0;
        border-bottom: 1px solid #f3f4f6;
    }

    .legend-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        margin-right: 10px;
    }

    .progress-thin {
        height: 8px;
        border-radius: 4px;
    }

    .alert-card {
        border-left: 4px solid var(--warning-amber);
        background: #fffbeb;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 0.75rem;
    }

    .recent-activity-item {
        padding: 0.75rem 0;
        border-bottom: 1px solid #f3f4f6;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .recent-activity-item:last-child {
        border-bottom: none;
    }

    .badge-status {
        padding: 0.35rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .badge-normal { background: #d1fae5; color: #047857; }
    .badge-warning { background: #fef3c7; color: #b45309; }
    .badge-danger { background: #fee2e2; color: #be123c; }

    .section-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1f2937;
        margin: 2rem 0 1rem 0;
        padding-bottom: 0.5rem;
        border-bottom: 3px solid var(--primary-blue);
        display: inline-block;
    }
</style>

{{-- Summary Statistics --}}
<div class="row mb-4">
    <div class="col-xl-3 col-lg-6 col-md-6 mb-3">
        <div class="stat-card primary p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value">{{ number_format($totalAnak) }}</div>
                    <div class="stat-label">Total Anak Terdaftar</div>
                </div>
                <div class="stat-icon">
                    <i class="fa fa-child"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6 col-md-6 mb-3">
        <div class="stat-card success p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value">{{ number_format($totalDataAnak) }}</div>
                    <div class="stat-label">Total Pengukuran</div>
                </div>
                <div class="stat-icon">
                    <i class="fa fa-chart-line"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6 col-md-6 mb-3">
        <div class="stat-card warning p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value">{{ number_format($totalImunisasi) }}</div>
                    <div class="stat-label">Total Imunisasi</div>
                </div>
                <div class="stat-icon">
                    <i class="fa fa-syringe"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6 col-md-6 mb-3">
        <div class="stat-card danger p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value">{{ number_format($incompleteImunisasiCount) }}</div>
                    <div class="stat-label">Imunisasi Belum Lengkap</div>
                </div>
                <div class="stat-icon">
                    <i class="fa fa-exclamation-triangle"></i>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Z-Score Status Cards --}}
<h4 class="section-title"><i class="fa fa-heartbeat mr-2"></i> Status Gizi Anak</h4>
<div class="row mb-4">
    <div class="col-lg-4 mb-3">
        <div class="chart-card">
            <h5><i class="fa fa-weight text-primary mr-2"></i> IMT/U (BMI per Umur)</h5>
            <div class="chart-container-sm">
                <canvas id="imtChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4 mb-3">
        <div class="chart-card">
            <h5><i class="fa fa-balance-scale text-success mr-2"></i> BB/U (Berat per Umur)</h5>
            <div class="chart-container-sm">
                <canvas id="bbChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4 mb-3">
        <div class="chart-card">
            <h5><i class="fa fa-ruler-vertical text-info mr-2"></i> TB/U (Tinggi per Umur)</h5>
            <div class="chart-container-sm">
                <canvas id="tbChart"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- Demographics & Geography --}}
<h4 class="section-title"><i class="fa fa-map-marker-alt mr-2"></i> Demografi & Distribusi Wilayah</h4>
<div class="row mb-4">
    <div class="col-lg-4 mb-3">
        <div class="chart-card">
            <h5><i class="fa fa-venus-mars text-primary mr-2"></i> Distribusi Jenis Kelamin</h5>
            <div class="chart-container-sm">
                <canvas id="genderChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4 mb-3">
        <div class="chart-card">
            <h5><i class="fa fa-birthday-cake text-warning mr-2"></i> Distribusi Usia</h5>
            <div class="chart-container-sm">
                <canvas id="ageChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4 mb-3">
        <div class="chart-card">
            <h5><i class="fa fa-map text-success mr-2"></i> Per Kecamatan</h5>
            <div class="chart-container-sm">
                <canvas id="kecamatanChart"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- Growth Trends & Immunization --}}
<h4 class="section-title"><i class="fa fa-chart-area mr-2"></i> Tren Pertumbuhan & Imunisasi</h4>
<div class="row mb-4">
    <div class="col-lg-8 mb-3">
        <div class="chart-card">
            <h5><i class="fa fa-chart-line text-primary mr-2"></i> Tren Rata-rata Pertumbuhan per Bulan</h5>
            <div class="chart-container">
                <canvas id="growthTrendChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4 mb-3">
        <div class="chart-card">
            <h5><i class="fa fa-syringe text-success mr-2"></i> Status Imunisasi</h5>
            <div class="chart-container-sm">
                <canvas id="imunisasiStatusChart"></canvas>
            </div>
            <div class="mt-3">
                @foreach($imunisasiStatus as $status => $count)
                <div class="legend-item">
                    <span class="legend-dot" style="background: {{ $status == 'sudah' ? '#047857' : ($status == 'terlambat' ? '#b45309' : '#6b7280') }}"></span>
                    <span class="flex-grow-1">{{ ucfirst($status) }}</span>
                    <span class="font-weight-bold">{{ $count }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

{{-- Vaccine Coverage --}}
<div class="row mb-4">
    <div class="col-lg-8 mb-3">
        <div class="chart-card">
            <h5><i class="fa fa-shield-alt text-info mr-2"></i> Cakupan Vaksin (Imunisasi Lengkap)</h5>
            <div class="chart-container">
                <canvas id="vaccineCoverageChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4 mb-3">
        <div class="chart-card">
            <h5><i class="fa fa-baby-carriage text-warning mr-2"></i> Status ASI Eksklusif</h5>
            <div class="chart-container-sm">
                <canvas id="asiChart"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- Monthly Visits & Alerts --}}
<div class="row mb-4">
    <div class="col-lg-8 mb-3">
        <div class="chart-card">
            <h5><i class="fa fa-calendar-check text-primary mr-2"></i> Tren Kunjungan Bulanan (12 Bulan Terakhir)</h5>
            <div class="chart-container">
                <canvas id="visitTrendChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4 mb-3">
        <div class="chart-card">
            <h5><i class="fa fa-exclamation-circle text-danger mr-2"></i> Ringkasan Imunisasi</h5>
            <div class="text-center py-4">
                @if($incompleteImunisasiCount > 0)
                <div class="mb-3">
                    <i class="fa fa-exclamation-triangle fa-3x text-warning mb-2"></i>
                    <h3 class="text-warning mb-1">{{ number_format($incompleteImunisasiCount) }}</h3>
                    <p class="text-muted">Anak dengan imunisasi belum lengkap</p>
                </div>
                <div class="progress progress-thin mb-3" style="height: 20px;">
                    @php $completeRate = $totalAnak > 0 ? (($totalAnak - $incompleteImunisasiCount) / $totalAnak) * 100 : 0; @endphp
                    <div class="progress-bar bg-success" style="width: {{ $completeRate }}%">{{ number_format($completeRate, 1) }}%</div>
                </div>
                <p class="small text-muted">
                    <strong>{{ number_format($totalAnak - $incompleteImunisasiCount) }}</strong> dari <strong>{{ number_format($totalAnak) }}</strong> anak sudah lengkap
                </p>
                <a href="{{ route('admin.earlyWarning') }}" class="btn btn-warning btn-sm mt-2">
                    <i class="fa fa-eye mr-1"></i> Lihat Detail di Early Warning
                </a>
                @else
                <i class="fa fa-check-circle fa-3x text-success mb-2"></i>
                <h5 class="text-success">Semua Lengkap!</h5>
                <p class="text-muted">Semua anak memiliki imunisasi dasar lengkap</p>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Recent Activities --}}
<h4 class="section-title"><i class="fa fa-history mr-2"></i> Aktivitas Terbaru</h4>
<div class="row mb-4">
    <div class="col-12">
        <div class="chart-card">
            <h5><i class="fa fa-clipboard-list text-primary mr-2"></i> 10 Kunjungan Terakhir</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="thead-light">
                        <tr>
                            <th>Nama Anak</th>
                            <th>Tanggal Kunjungan</th>
                            <th>Umur (Bulan)</th>
                            <th>Berat Badan</th>
                            <th>Tinggi Badan</th>
                            <th>BMI</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentActivities as $activity)
                        <tr>
                            <td><strong>{{ $activity->nama }}</strong></td>
                            <td>{{ \Carbon\Carbon::parse($activity->tgl_kunjungan)->format('d M Y') }}</td>
                            <td>{{ $activity->bln }} bulan</td>
                            <td>{{ $activity->bb }} kg</td>
                            <td>{{ $activity->tb }} cm</td>
                            <td>
                                @php
                                    $bmi = $activity->tb > 0 ? round(10000 * $activity->bb / pow($activity->tb, 2), 1) : 0;
                                @endphp
                                {{ $bmi }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted">Belum ada data kunjungan</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Posyandu Distribution --}}
<div class="row mb-4">
    <div class="col-lg-6 mb-3">
        <div class="chart-card">
            <h5><i class="fa fa-hospital text-success mr-2"></i> Top 10 Posyandu</h5>
            <div class="chart-container">
                <canvas id="posyanduChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6 mb-3">
        <div class="chart-card">
            <h5><i class="fa fa-building text-info mr-2"></i> Distribusi per Kelurahan</h5>
            <div class="chart-container">
                <canvas id="kelurahanChart"></canvas>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
@parent
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Color Palette
    const colors = {
        primary: '#0066cc',
        success: '#047857',
        warning: '#b45309',
        danger: '#be123c',
        info: '#0891b2',
        purple: '#7c3aed',
        pink: '#db2777',
        gray: '#6b7280'
    };

    const gradientColors = [
        'rgba(0, 102, 204, 0.8)',
        'rgba(4, 120, 87, 0.8)',
        'rgba(180, 83, 9, 0.8)',
        'rgba(190, 18, 60, 0.8)',
        'rgba(8, 145, 178, 0.8)',
        'rgba(124, 58, 237, 0.8)'
    ];

    // Chart.js Global Defaults
    Chart.defaults.font.family = "'Inter', 'Segoe UI', sans-serif";
    Chart.defaults.plugins.legend.labels.usePointStyle = true;

    // IMT/U Chart
    new Chart(document.getElementById('imtChart'), {
        type: 'doughnut',
        data: {
            labels: ['Normal', 'Gizi Kurang', 'Gizi Buruk', 'Gizi Lebih', 'Obesitas'],
            datasets: [{
                data: [
                    {{ $zScoreAnalysis['imt_u']['normal'] }},
                    {{ $zScoreAnalysis['imt_u']['kurang'] }},
                    {{ $zScoreAnalysis['imt_u']['buruk'] }},
                    {{ $zScoreAnalysis['imt_u']['lebih'] }},
                    {{ $zScoreAnalysis['imt_u']['obesitas'] }}
                ],
                backgroundColor: [colors.success, colors.warning, colors.danger, colors.info, colors.purple]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });

    // BB/U Chart
    new Chart(document.getElementById('bbChart'), {
        type: 'doughnut',
        data: {
            labels: ['Normal', 'BB Kurang', 'BB Sangat Kurang', 'Risiko BB Lebih'],
            datasets: [{
                data: [
                    {{ $zScoreAnalysis['bb_u']['normal'] }},
                    {{ $zScoreAnalysis['bb_u']['kurang'] }},
                    {{ $zScoreAnalysis['bb_u']['sangat_kurang'] }},
                    {{ $zScoreAnalysis['bb_u']['lebih'] }}
                ],
                backgroundColor: [colors.success, colors.warning, colors.danger, colors.info]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });

    // TB/U Chart
    new Chart(document.getElementById('tbChart'), {
        type: 'doughnut',
        data: {
            labels: ['Normal', 'Pendek (Stunted)', 'Sangat Pendek', 'Tinggi'],
            datasets: [{
                data: [
                    {{ $zScoreAnalysis['tb_u']['normal'] }},
                    {{ $zScoreAnalysis['tb_u']['pendek'] }},
                    {{ $zScoreAnalysis['tb_u']['sangat_pendek'] }},
                    {{ $zScoreAnalysis['tb_u']['tinggi'] }}
                ],
                backgroundColor: [colors.success, colors.warning, colors.danger, colors.info]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });

    // Gender Distribution Chart
    new Chart(document.getElementById('genderChart'), {
        type: 'pie',
        data: {
            labels: {!! json_encode($genderDistribution->keys()) !!},
            datasets: [{
                data: {!! json_encode($genderDistribution->values()) !!},
                backgroundColor: [colors.primary, colors.pink]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });

    // Age Distribution Chart
    new Chart(document.getElementById('ageChart'), {
        type: 'bar',
        data: {
            labels: {!! json_encode($ageDistribution->pluck('age_year')->map(fn($a) => $a . ' Tahun')) !!},
            datasets: [{
                label: 'Jumlah Anak',
                data: {!! json_encode($ageDistribution->pluck('total')) !!},
                backgroundColor: colors.primary
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });

    // Kecamatan Distribution Chart
    new Chart(document.getElementById('kecamatanChart'), {
        type: 'pie',
        data: {
            labels: {!! json_encode($kecamatanDistribution->pluck('name')) !!},
            datasets: [{
                data: {!! json_encode($kecamatanDistribution->pluck('total')) !!},
                backgroundColor: gradientColors
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });

    // Growth Trend Chart
    new Chart(document.getElementById('growthTrendChart'), {
        type: 'line',
        data: {
            labels: {!! json_encode($growthTrend->pluck('bln')->map(fn($b) => 'Bln ' . $b)) !!},
            datasets: [{
                label: 'Rata-rata BB (kg)',
                data: {!! json_encode($growthTrend->pluck('avg_bb')->map(fn($v) => round($v, 2))) !!},
                borderColor: colors.primary,
                backgroundColor: 'rgba(0, 102, 204, 0.1)',
                tension: 0.3,
                fill: true
            }, {
                label: 'Rata-rata TB (cm)',
                data: {!! json_encode($growthTrend->pluck('avg_tb')->map(fn($v) => round($v, 2))) !!},
                borderColor: colors.success,
                backgroundColor: 'rgba(4, 120, 87, 0.1)',
                tension: 0.3,
                fill: true,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: { type: 'linear', position: 'left', title: { display: true, text: 'Berat (kg)' } },
                y1: { type: 'linear', position: 'right', title: { display: true, text: 'Tinggi (cm)' }, grid: { drawOnChartArea: false } }
            }
        }
    });

    // Immunization Status Chart
    new Chart(document.getElementById('imunisasiStatusChart'), {
        type: 'doughnut',
        data: {
            labels: {!! json_encode(array_map('ucfirst', array_keys($imunisasiStatus->toArray()))) !!},
            datasets: [{
                data: {!! json_encode(array_values($imunisasiStatus->toArray())) !!},
                backgroundColor: [colors.success, colors.warning, colors.gray]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } }
        }
    });

    // Vaccine Coverage Chart
    new Chart(document.getElementById('vaccineCoverageChart'), {
        type: 'bar',
        data: {
            labels: {!! json_encode($vaccineCoverage->pluck('kode')) !!},
            datasets: [{
                label: 'Jumlah Anak',
                data: {!! json_encode($vaccineCoverage->pluck('total')) !!},
                backgroundColor: colors.success
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });

    // ASI Chart
    new Chart(document.getElementById('asiChart'), {
        type: 'pie',
        data: {
            labels: {!! json_encode($asiStatus->keys()) !!},
            datasets: [{
                data: {!! json_encode($asiStatus->values()) !!},
                backgroundColor: [colors.success, colors.warning, colors.gray]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });

    // Visit Trend Chart
    new Chart(document.getElementById('visitTrendChart'), {
        type: 'bar',
        data: {
            labels: {!! json_encode($visitTrend->pluck('month')) !!},
            datasets: [{
                label: 'Jumlah Kunjungan',
                data: {!! json_encode($visitTrend->pluck('total')) !!},
                backgroundColor: colors.info
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });

    // Posyandu Chart
    new Chart(document.getElementById('posyanduChart'), {
        type: 'bar',
        data: {
            labels: {!! json_encode($posyanduDistribution->pluck('name')) !!},
            datasets: [{
                label: 'Jumlah Anak',
                data: {!! json_encode($posyanduDistribution->pluck('total')) !!},
                backgroundColor: gradientColors
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } }
        }
    });

    // Kelurahan Chart
    new Chart(document.getElementById('kelurahanChart'), {
        type: 'bar',
        data: {
            labels: {!! json_encode($kelurahanDistribution->pluck('name')) !!},
            datasets: [{
                label: 'Jumlah Anak',
                data: {!! json_encode($kelurahanDistribution->pluck('total')) !!},
                backgroundColor: colors.purple
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } }
        }
    });
});
</script>
@endsection

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
<style>
    .stat-card {
        position: relative;
        overflow: hidden;
        border-radius: 10px;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background-color: #0d6efd;
    }
    .stat-card.success::before { background-color: #198754; }
    .stat-card.warning::before { background-color: #ffc107; }
    .stat-card.danger::before { background-color: #dc3545; }
    .stat-card.info::before { background-color: #0dcaf0; }

    .child-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        object-fit: cover;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 32px;
        font-weight: bold;
    }

    .info-card {
        border-radius: 10px;
        border: none;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    .info-card .card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 10px 10px 0 0 !important;
        font-weight: 600;
    }

    .z-score-indicator {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 8px;
    }
    .z-score-normal { background-color: #198754; }
    .z-score-warning { background-color: #ffc107; }
    .z-score-danger { background-color: #dc3545; }

    .growth-chart-container {
        position: relative;
        height: 300px;
        width: 100%;
    }

    .timeline-item {
        position: relative;
        padding-left: 30px;
        border-left: 2px solid #e9ecef;
        margin-left: 15px;
    }
    .timeline-item::before {
        content: '';
        position: absolute;
        left: -8px;
        top: 0;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background: #0d6efd;
        border: 2px solid white;
    }

    .badge-status {
        font-size: 0.75rem;
        padding: 0.35em 0.65em;
    }
</style>

<!-- Header Section -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="d-flex align-items-center">
            <div class="child-avatar me-3">
                {{ strtoupper(substr($anak->nama, 0, 1)) }}
            </div>
            <div>
                <h3 class="mb-1">{{ $anak->nama }}</h3>
                <p class="text-muted mb-0">NIK: {{ $anak->nik }}</p>
                <p class="text-muted mb-0">
                    {{ $usiaText }},
                    @if($anak->jk == 1)
                        <span class="badge bg-primary">Laki-laki</span>
                    @else
                        <span class="badge bg-danger">Perempuan</span>
                    @endif
                </p>
            </div>
        </div>
    </div>
    <div class="col-md-4 text-end">
        <div class="btn-group" role="group">
            <a href="{{ route('admin.editAnak', $anak->hashid) }}" class="btn btn-outline-primary btn-sm">
                <i class="icon-copy dw dw-edit2"></i> Edit
            </a>
            <a href="{{ route('admin.dataAnak', $anak->hashid) }}" class="btn btn-primary btn-sm">
                <i class="icon-copy dw dw-add"></i> Tambah Pengukuran
            </a>
            <a href="{{ route('admin.chartAnak', $anak->hashid) }}" class="btn btn-outline-info btn-sm">
                <i class="icon-copy dw dw-analytics-13"></i> Grafik
            </a>
        </div>
    </div>
</div>

<div class="row">
    <!-- Child Information -->
    <div class="col-lg-4 mb-4">
        <div class="card info-card h-100">
            <div class="card-header">
                <h6 class="mb-0"><i class="icon-copy dw dw-user-1 mr-2"></i> Informasi Anak</h6>
            </div>
            <div class="card-body">
                <table class="table table-borderless table-sm">
                    <tr>
                        <td class="text-muted" width="40%">No. KK</td>
                        <td>{{ $anak->no_kk }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">NIK</td>
                        <td>{{ $anak->nik }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Tempat Lahir</td>
                        <td>{{ $anak->tempat_lahir }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Tanggal Lahir</td>
                        <td>{{ \Carbon\Carbon::parse($anak->tgl_lahir)->format('d F Y') }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Usia</td>
                        <td>{{ $usiaText }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Jenis Kelamin</td>
                        <td>{{ $anak->jk == 1 ? 'Laki-laki' : 'Perempuan' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Golongan Darah</td>
                        <td>{{ $anak->golda }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Anak Ke-</td>
                        <td>{{ $anak->anak }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Parent/Guardian Information -->
    <div class="col-lg-4 mb-4">
        <div class="card info-card h-100">
            <div class="card-header">
                <h6 class="mb-0"><i class="icon-copy dw dw-user-2 mr-2"></i> Informasi Orang Tua</h6>
            </div>
            <div class="card-body">
                <table class="table table-borderless table-sm">
                    <tr>
                        <td class="text-muted" width="40%">NIK Orang Tua</td>
                        <td>{{ $anak->nik_ortu }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Nama Ibu</td>
                        <td>{{ $anak->nama_ibu }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Nama Ayah</td>
                        <td>{{ $anak->nama_ayah }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Location Information -->
    <div class="col-lg-4 mb-4">
        <div class="card info-card h-100">
            <div class="card-header">
                <h6 class="mb-0"><i class="icon-copy dw dw-placeholder1 mr-2"></i> Informasi Wilayah</h6>
            </div>
            <div class="card-body">
                <table class="table table-borderless table-sm">
                    <tr>
                        <td class="text-muted" width="40%">Kecamatan</td>
                        <td>{{ $kecamatan->name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Kelurahan</td>
                        <td>{{ $kelurahan->name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">RT</td>
                        <td>{{ $rt->name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Puskesmas</td>
                        <td>{{ $puskesmas->name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Posyandu</td>
                        <td>{{ $posyandu->name ?? '-' }}</td>
                    </tr>
                </table>
                @if($anak->catatan)
                <hr>
                <small class="text-muted">Catatan:</small>
                <p class="mb-0">{{ $anak->catatan }}</p>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Current Health Status -->
@if($latestData)
<div class="row mb-4">
    <div class="col-12">
        <div class="card info-card">
            <div class="card-header">
                <h6 class="mb-0"><i class="icon-copy dw dw-heart mr-2"></i> Status Kesehatan Terkini</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card {{ str_contains(strtolower($latestData['bb']), 'normal') ? 'success' : (str_contains(strtolower($latestData['bb']), 'kurang') ? 'warning' : 'danger') }} h-100">
                            <div class="card-body text-center py-4">
                                <h6 class="text-muted mb-2">Berat Badan</h6>
                                <h3 class="mb-1">{{ $latestData['berat'] }} kg</h3>
                                <small class="text-muted">{{ $latestData['bb'] }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card {{ str_contains(strtolower($latestData['tb']), 'normal') ? 'success' : (str_contains(strtolower($latestData['tb']), 'pendek') ? 'warning' : 'danger') }} h-100">
                            <div class="card-body text-center py-4">
                                <h6 class="text-muted mb-2">Tinggi Badan</h6>
                                <h3 class="mb-1">{{ $latestData['tinggi'] }} cm</h3>
                                <small class="text-muted">{{ $latestData['tb'] }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card {{ str_contains(strtolower($latestData['imt']), 'baik') || str_contains(strtolower($latestData['imt']), 'normal') ? 'success' : (str_contains(strtolower($latestData['imt']), 'kurang') ? 'warning' : 'danger') }} h-100">
                            <div class="card-body text-center py-4">
                                <h6 class="text-muted mb-2">IMT</h6>
                                <h3 class="mb-1">{{ $latestData['bmi'] }}</h3>
                                <small class="text-muted">{{ $latestData['imt'] }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card info h-100">
                            <div class="card-body text-center py-4">
                                <h6 class="text-muted mb-2">Usia Pengukuran</h6>
                                <h3 class="mb-1">{{ $latestData['bln'] }} bln</h3>
                                <small class="text-muted">{{ \Carbon\Carbon::parse($latestData['tgl_kunjungan'])->format('d M Y') }}</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Z-Score Summary -->
                <div class="row mt-3">
                    <div class="col-md-6">
                        <h6 class="mb-3">Status Gizi (Z-Score)</h6>
                        <div class="mb-2 d-flex justify-content-between align-items-center">
                            <span>
                                <span class="z-score-indicator {{ str_contains(strtolower($latestData['imt']), 'baik') || str_contains(strtolower($latestData['imt']), 'normal') ? 'z-score-normal' : (str_contains(strtolower($latestData['imt']), 'kurang') ? 'z-score-warning' : 'z-score-danger') }}"></span>
                                IMT/U (IMT menurut Umur)
                            </span>
                            <span class="badge bg-secondary">{{ $latestData['imt'] }}</span>
                        </div>
                        <div class="mb-2 d-flex justify-content-between align-items-center">
                            <span>
                                <span class="z-score-indicator {{ str_contains(strtolower($latestData['bb']), 'normal') ? 'z-score-normal' : (str_contains(strtolower($latestData['bb']), 'kurang') ? 'z-score-warning' : 'z-score-danger') }}"></span>
                                BB/U (Berat Badan menurut Umur)
                            </span>
                            <span class="badge bg-secondary">{{ $latestData['bb'] }}</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="mb-3">&nbsp;</h6>
                        <div class="mb-2 d-flex justify-content-between align-items-center">
                            <span>
                                <span class="z-score-indicator {{ str_contains(strtolower($latestData['tb']), 'normal') ? 'z-score-normal' : (str_contains(strtolower($latestData['tb']), 'pendek') ? 'z-score-warning' : 'z-score-danger') }}"></span>
                                TB/U (Tinggi Badan menurut Umur)
                            </span>
                            <span class="badge bg-secondary">{{ $latestData['tb'] }}</span>
                        </div>
                        <div class="mb-2 d-flex justify-content-between align-items-center">
                            <span>
                                <span class="z-score-indicator {{ str_contains(strtolower($latestData['bt']), 'baik') || str_contains(strtolower($latestData['bt']), 'normal') ? 'z-score-normal' : (str_contains(strtolower($latestData['bt']), 'kurang') ? 'z-score-warning' : 'z-score-danger') }}"></span>
                                BB/TB (Berat Badan menurut Tinggi Badan)
                            </span>
                            <span class="badge bg-secondary">{{ $latestData['bt'] }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<div class="row">
    <!-- Growth History Chart -->
    <div class="col-lg-8 mb-4">
        <div class="card info-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="icon-copy dw dw-analytics-13 mr-2"></i> Riwayat Pertumbuhan</h6>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-light btn-sm active" id="weightHistoryBtn">Berat</button>
                    <button type="button" class="btn btn-outline-light btn-sm" id="heightHistoryBtn">Tinggi</button>
                    <button type="button" class="btn btn-outline-light btn-sm" id="bmiHistoryBtn">IMT</button>
                </div>
            </div>
            <div class="card-body">
                @if(count($hasilx) > 0)
                <div class="growth-chart-container">
                    <canvas id="growthHistoryChart"></canvas>
                </div>
                @else
                <div class="text-center py-5">
                    <i class="icon-copy dw dw-file-39" style="font-size: 48px; color: #ccc;"></i>
                    <p class="text-muted mt-3">Belum ada data pengukuran</p>
                    <a href="{{ route('admin.dataAnak', $anak->hashid) }}" class="btn btn-primary btn-sm">
                        <i class="icon-copy dw dw-add"></i> Tambah Data Pengukuran
                    </a>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Recent Measurements -->
    <div class="col-lg-4 mb-4">
        <div class="card info-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="icon-copy dw dw-calendar1 mr-2"></i> Riwayat Kunjungan</h6>
                <a href="{{ route('admin.dataAnak', $anak->hashid) }}" class="btn btn-sm btn-light">
                    <i class="icon-copy dw dw-add"></i>
                </a>
            </div>
            <div class="card-body" style="max-height: 350px; overflow-y: auto;">
                @forelse(array_reverse($hasilx) as $index => $hasil)
                    @if($index < 5)
                    <div class="timeline-item mb-3 pb-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                        <h6 class="mb-1">Usia {{ $hasil['bln'] }} bulan</h6>
                        <p class="mb-1 small">
                            <strong>BB:</strong> {{ $hasil['berat'] }} kg |
                            <strong>TB:</strong> {{ $hasil['tinggi'] }} cm |
                            <strong>IMT:</strong> {{ $hasil['bmi'] }}
                        </p>
                        <small class="text-muted">
                            <i class="icon-copy dw dw-calendar1"></i>
                            {{ \Carbon\Carbon::parse($hasil['tgl_kunjungan'])->format('d M Y') }}
                        </small>
                    </div>
                    @endif
                @empty
                <div class="text-center py-4">
                    <p class="text-muted mb-0">Belum ada riwayat kunjungan</p>
                </div>
                @endforelse
                @if(count($hasilx) > 5)
                <div class="text-center mt-2">
                    <small class="text-muted">Menampilkan 5 dari {{ count($hasilx) }} kunjungan</small>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Growth History Table -->
@if(count($hasilx) > 0)
<div class="row">
    <div class="col-12 mb-4">
        <div class="card info-card">
            <div class="card-header">
                <h6 class="mb-0"><i class="icon-copy dw dw-list3 mr-2"></i> Data Berkala Lengkap</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="dataTable">
                        <thead class="table-dark">
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Usia (bln)</th>
                                <th>BB (kg)</th>
                                <th>TB (cm)</th>
                                <th>IMT</th>
                                <th>IMT/U</th>
                                <th>BB/U</th>
                                <th>TB/U</th>
                                <th>BB/TB</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($hasilx as $hasil)
                            <tr>
                                <td>{{ $hasil['no'] }}</td>
                                <td>{{ \Carbon\Carbon::parse($hasil['tgl_kunjungan'])->format('d/m/Y') }}</td>
                                <td>{{ $hasil['bln'] }}</td>
                                <td>{{ $hasil['berat'] }}</td>
                                <td>{{ $hasil['tinggi'] }}</td>
                                <td>{{ $hasil['bmi'] }}</td>
                                <td>
                                    <span class="badge badge-status {{ str_contains(strtolower($hasil['imt']), 'baik') || str_contains(strtolower($hasil['imt']), 'normal') ? 'bg-success' : (str_contains(strtolower($hasil['imt']), 'kurang') ? 'bg-warning' : 'bg-danger') }}">
                                        {{ Str::limit($hasil['imt'], 20) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-status {{ str_contains(strtolower($hasil['bb']), 'normal') ? 'bg-success' : (str_contains(strtolower($hasil['bb']), 'kurang') ? 'bg-warning' : 'bg-danger') }}">
                                        {{ Str::limit($hasil['bb'], 20) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-status {{ str_contains(strtolower($hasil['tb']), 'normal') ? 'bg-success' : (str_contains(strtolower($hasil['tb']), 'pendek') ? 'bg-warning' : 'bg-danger') }}">
                                        {{ Str::limit($hasil['tb'], 15) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-status {{ str_contains(strtolower($hasil['bt']), 'baik') || str_contains(strtolower($hasil['bt']), 'normal') ? 'bg-success' : (str_contains(strtolower($hasil['bt']), 'kurang') ? 'bg-warning' : 'bg-danger') }}">
                                        {{ Str::limit($hasil['bt'], 20) }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Immunization Records -->
<div class="row">
    <div class="col-12 mb-4">
        <div class="card info-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="icon-copy dw dw-first-aid-kit mr-2"></i> Riwayat Imunisasi</h6>
                <a href="{{ route('admin.dataImunisasi', $anak->hashid) }}" class="btn btn-sm btn-light">
                    <i class="icon-copy dw dw-add"></i> Kelola Imunisasi
                </a>
            </div>
            <div class="card-body">
                @if($imunisasi->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Vaksin</th>
                                <th>Dosis</th>
                                <th>Tanggal Pemberian</th>
                                <th>Tanggal Selanjutnya</th>
                                <th>Lokasi</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($imunisasi as $imun)
                            <tr>
                                <td>{{ $imun->jenisVaksin->nama ?? '-' }}</td>
                                <td>{{ $imun->dosis }}</td>
                                <td>{{ $imun->tanggal_pemberian ? $imun->tanggal_pemberian->format('d M Y') : '-' }}</td>
                                <td>{{ $imun->tanggal_selanjutnya ? $imun->tanggal_selanjutnya->format('d M Y') : '-' }}</td>
                                <td>{{ $imun->lokasi_pemberian ?? '-' }}</td>
                                <td>{!! $imun->status_badge !!}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-4">
                    <i class="icon-copy dw dw-first-aid-kit" style="font-size: 48px; color: #ccc;"></i>
                    <p class="text-muted mt-3">Belum ada data imunisasi</p>
                    <a href="{{ route('admin.dataImunisasi', $anak->hashid) }}" class="btn btn-primary btn-sm">
                        <i class="icon-copy dw dw-add"></i> Tambah Data Imunisasi
                    </a>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('custom_scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    @if(count($hasilx) > 0)
    const growthCtx = document.getElementById('growthHistoryChart').getContext('2d');

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
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13, 110, 253, 0.1)',
            tension: 0.3,
            fill: true
        }]
    };

    const heightDataset = {
        labels: labels,
        datasets: [{
            label: 'Tinggi Badan (cm)',
            data: heightData,
            borderColor: '#198754',
            backgroundColor: 'rgba(25, 135, 84, 0.1)',
            tension: 0.3,
            fill: true
        }]
    };

    const bmiDataset = {
        labels: labels,
        datasets: [{
            label: 'IMT',
            data: bmiData,
            borderColor: '#ffc107',
            backgroundColor: 'rgba(255, 193, 7, 0.1)',
            tension: 0.3,
            fill: true
        }]
    };

    let growthChart = new Chart(growthCtx, {
        type: 'line',
        data: weightDataset,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Riwayat Berat Badan'
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    title: {
                        display: true,
                        text: 'Berat (kg)'
                    }
                }
            }
        }
    });

    document.getElementById('weightHistoryBtn').addEventListener('click', function() {
        document.querySelectorAll('#weightHistoryBtn, #heightHistoryBtn, #bmiHistoryBtn').forEach(btn => btn.classList.remove('active'));
        this.classList.add('active');
        growthChart.data = weightDataset;
        growthChart.options.plugins.title.text = 'Riwayat Berat Badan';
        growthChart.options.scales.y.title.text = 'Berat (kg)';
        growthChart.update();
    });

    document.getElementById('heightHistoryBtn').addEventListener('click', function() {
        document.querySelectorAll('#weightHistoryBtn, #heightHistoryBtn, #bmiHistoryBtn').forEach(btn => btn.classList.remove('active'));
        this.classList.add('active');
        growthChart.data = heightDataset;
        growthChart.options.plugins.title.text = 'Riwayat Tinggi Badan';
        growthChart.options.scales.y.title.text = 'Tinggi (cm)';
        growthChart.update();
    });

    document.getElementById('bmiHistoryBtn').addEventListener('click', function() {
        document.querySelectorAll('#weightHistoryBtn, #heightHistoryBtn, #bmiHistoryBtn').forEach(btn => btn.classList.remove('active'));
        this.classList.add('active');
        growthChart.data = bmiDataset;
        growthChart.options.plugins.title.text = 'Riwayat IMT';
        growthChart.options.scales.y.title.text = 'IMT';
        growthChart.update();
    });
    @endif
});
</script>
@endsection

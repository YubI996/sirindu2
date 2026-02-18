@extends('admin::layouts.app')
@section('title') Dashboard Epidemiologi - Si Rindu @endsection
@section('title-content') Epidemiologi @endsection
@section('item') Epidemiologi @endsection
@section('item-active') Dashboard Analytics @endsection

@section('content')
<!-- Quick Stats -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Kasus</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['total'] }}</div>
                    </div>
                    <div class="col-auto"><i class="fa fa-virus fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Bulan Ini</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['bulan_ini'] }}</div>
                    </div>
                    <div class="col-auto"><i class="fa fa-calendar fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Konfirmasi</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['konfirmasi'] }}</div>
                    </div>
                    <div class="col-auto"><i class="fa fa-exclamation-circle fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Meninggal</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['meninggal'] }}</div>
                    </div>
                    <div class="col-auto"><i class="fa fa-heart-broken fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row 1 -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header bg-white font-weight-bold">
                <i class="fa fa-chart-bar mr-2 text-primary"></i>Distribusi per Jenis Penyakit
            </div>
            <div class="card-body"><canvas id="chartDisease" height="120"></canvas></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow">
            <div class="card-header bg-white font-weight-bold">
                <i class="fa fa-chart-pie mr-2 text-success"></i>Status Kasus
            </div>
            <div class="card-body"><canvas id="chartStatus" height="200"></canvas></div>
        </div>
    </div>
</div>

<!-- Charts Row 2 -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-header bg-white font-weight-bold">
                <i class="fa fa-chart-line mr-2 text-warning"></i>Tren Kasus 12 Bulan Terakhir
            </div>
            <div class="card-body"><canvas id="chartTrend" height="150"></canvas></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-header bg-white font-weight-bold">
                <i class="fa fa-map-marker mr-2 text-danger"></i>Top Kecamatan
            </div>
            <div class="card-body"><canvas id="chartKec" height="150"></canvas></div>
        </div>
    </div>
</div>

<!-- Recent Cases -->
<div class="card shadow">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="font-weight-bold"><i class="fa fa-list mr-2 text-primary"></i>10 Kasus Terbaru</span>
        <a href="{{ route('admin.epidemiologi.index') }}" class="btn btn-sm btn-primary">Lihat Semua</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>No Reg</th><th>Nama</th><th>Jenis Kasus</th><th>Tgl Onset</th><th>Wilayah</th><th>Status</th><th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($recentCases as $c)
                <tr>
                    <td>{{ $c->no_registrasi }}</td>
                    <td>{{ $c->nama_lengkap }}</td>
                    <td>{{ $c->jenisKasus->nama_penyakit ?? '-' }}</td>
                    <td>{{ $c->tanggal_onset?->format('d/m/Y') }}</td>
                    <td>{{ $c->kelurahan->nama_kelurahan ?? '-' }}</td>
                    <td>
                        @php $colors = ['suspek'=>'warning','probable'=>'info','konfirmasi'=>'danger','discarded'=>'secondary']; @endphp
                        <span class="badge badge-{{ $colors[$c->status_kasus] ?? 'secondary' }}">{{ $c->status_kasus }}</span>
                    </td>
                    <td><a href="{{ route('admin.epidemiologi.show', $c->id) }}" class="btn btn-sm btn-info"><i class="fa fa-eye"></i></a></td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-3">Belum ada data kasus</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('custom_scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
// Chart 1: Disease distribution
var diseaseLabels = {!! json_encode($byDisease->map(fn($d) => $d->jenisKasus->nama_penyakit ?? 'Unknown')) !!};
var diseaseData   = {!! json_encode($byDisease->pluck('total')) !!};
new Chart(document.getElementById('chartDisease'), {
    type: 'bar',
    data: {
        labels: diseaseLabels,
        datasets: [{ label: 'Jumlah Kasus', data: diseaseData,
            backgroundColor: 'rgba(54,162,235,0.7)', borderColor: 'rgba(54,162,235,1)', borderWidth: 1 }]
    },
    options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } } }
});

// Chart 2: Status
var statusLabels = {!! json_encode($byStatus->pluck('status_kasus')) !!};
var statusData   = {!! json_encode($byStatus->pluck('total')) !!};
new Chart(document.getElementById('chartStatus'), {
    type: 'doughnut',
    data: {
        labels: statusLabels,
        datasets: [{ data: statusData,
            backgroundColor: ['#f6c23e','#36b9cc','#e74a3b','#858796'] }]
    },
    options: { responsive: true }
});

// Chart 3: Trend
var trendLabels = {!! json_encode($trend->map(fn($t) => $t->bulan.'/'.$t->tahun)) !!};
var trendData   = {!! json_encode($trend->pluck('total')) !!};
new Chart(document.getElementById('chartTrend'), {
    type: 'line',
    data: {
        labels: trendLabels,
        datasets: [{ label: 'Kasus', data: trendData, fill: true,
            backgroundColor: 'rgba(28,200,138,0.1)', borderColor: '#1cc88a', tension: 0.4 }]
    },
    options: { responsive: true, scales: { y: { beginAtZero: true } } }
});

// Chart 4: By Kecamatan
var kecLabels = {!! json_encode($byKecamatan->map(fn($k) => $k->kecamatan->nama_kecamatan ?? 'Unknown')) !!};
var kecData   = {!! json_encode($byKecamatan->pluck('total')) !!};
new Chart(document.getElementById('chartKec'), {
    type: 'bar',
    data: {
        labels: kecLabels,
        datasets: [{ label: 'Kasus', data: kecData,
            backgroundColor: 'rgba(231,74,59,0.7)', borderColor: 'rgba(231,74,59,1)', borderWidth: 1 }]
    },
    options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } } }
});
</script>
@endsection

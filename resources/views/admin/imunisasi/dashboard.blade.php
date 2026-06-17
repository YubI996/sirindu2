@extends('admin::layouts.app')
@section('title')Dashboard Imunisasi@endsection
@section('title-content')Dashboard Imunisasi@endsection
@section('item')Imunisasi@endsection
@section('item-active')Dashboard IDL@endsection

@push('styles')
<link rel="stylesheet" type="text/css" href="{{ asset('admin/src/plugins/datatables/css/dataTables.bootstrap4.min.css') }}">
<link rel="stylesheet" type="text/css" href="{{ asset('admin/src/plugins/datatables/css/responsive.bootstrap4.min.css') }}">
@endpush

@section('content')

{{-- Filter --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0">Filter Wilayah</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('admin.imunisasiDashboard') }}" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Kecamatan</label>
                        <select name="id_kecamatan" class="form-select" id="filterKec">
                            <option value="">Semua Kecamatan</option>
                            @foreach($kecamatanList as $kec)
                                <option value="{{ $kec->id }}" {{ ($filters['id_kecamatan'] ?? null) == $kec->id ? 'selected' : '' }}>
                                    {{ $kec->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Kelurahan</label>
                        <select name="id_kelurahan" class="form-select" id="filterKel">
                            <option value="">Semua Kelurahan</option>
                            @foreach($kelurahanList as $kel)
                                <option value="{{ $kel->id }}" {{ ($filters['id_kelurahan'] ?? null) == $kel->id ? 'selected' : '' }}>
                                    {{ $kel->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Posyandu</label>
                        <select name="id_posyandu" class="form-select">
                            <option value="">Semua Posyandu</option>
                            @foreach($posyanduList as $pos)
                                <option value="{{ $pos->id }}" {{ ($filters['id_posyandu'] ?? null) == $pos->id ? 'selected' : '' }}>
                                    {{ $pos->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">Terapkan Filter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- KPI Cards --}}
@php
    $totalAnak       = $coverage['total'];
    $idlLengkap      = $coverage['idl_lengkap'];
    $persen          = $coverage['persen'];
    // $butuhKejar dihitung akurat lintas seluruh populasi di controller (bukan per halaman).
    $targetTercapai  = $persen >= 95;
@endphp

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="fs-1 fw-bold text-primary">{{ number_format($totalAnak) }}</div>
                <div class="text-muted">Total Anak (≥12 bln)</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center h-100 {{ $targetTercapai ? 'border-success' : 'border-danger' }}">
            <div class="card-body">
                <div class="fs-1 fw-bold {{ $targetTercapai ? 'text-success' : 'text-danger' }}">{{ $persen }}%</div>
                <div class="text-muted">IDL Lengkap</div>
                <small class="{{ $targetTercapai ? 'text-success' : 'text-danger' }}">
                    {{ $idlLengkap }}/{{ $totalAnak }} anak | Target 95%
                </small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center h-100 border-warning">
            <div class="card-body">
                <div class="fs-1 fw-bold text-danger">{{ $butuhKejar }}</div>
                <div class="text-muted">Butuh Kejar</div>
                <small class="text-muted">IDL/IBL belum lengkap</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="fs-1 fw-bold text-{{ $targetTercapai ? 'success' : 'danger' }}">
                    {{ $targetTercapai ? '✓' : '✗' }}
                </div>
                <div class="text-muted">Target Nasional</div>
                <small class="text-muted">95% cakupan IDL</small>
            </div>
        </div>
    </div>
</div>

{{-- Coverage per Kelurahan --}}
@if(count($coverage['per_kelurahan']) > 0)
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0">Cakupan IDL per Kelurahan</h5>
            </div>
            <div class="card-body">
                @foreach($coverage['per_kelurahan'] as $row)
                @php
                    $pct     = $row['persen'];
                    $barColor = $pct >= 95 ? 'bg-success' : ($pct >= 80 ? 'bg-warning' : 'bg-danger');
                @endphp
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-semibold">{{ $row['nama'] }}</span>
                        <span class="{{ $pct >= 95 ? 'text-success' : 'text-danger' }} fw-semibold">
                            {{ $pct }}% ({{ $row['lengkap'] }}/{{ $row['total'] }})
                        </span>
                    </div>
                    <div class="progress" style="height:18px;">
                        <div class="progress-bar {{ $barColor }}" role="progressbar"
                             style="width: {{ $pct }}%;" aria-valuenow="{{ $pct }}"
                             aria-valuemin="0" aria-valuemax="100">
                            {{ $pct >= 10 ? $pct . '%' : '' }}
                        </div>
                        {{-- Target line at 95% --}}
                    </div>
                    @if($pct < 95)
                    <small class="text-danger">Kurang {{ 95 - $pct }}% dari target nasional</small>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endif

{{-- Children Table --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Daftar Anak</h5>
                <span class="badge bg-secondary">{{ number_format($anakList->total()) }} anak</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm mb-0" id="tabelAnakImunisasi">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Nama Anak</th>
                                <th>Usia</th>
                                <th>Kelurahan</th>
                                <th>Posyandu</th>
                                <th>Status IDL</th>
                                <th>Vaksin Terlewat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($anakList as $row)
                            @php
                                $anak    = $row['anak'];
                                $diff    = (new DateTime($anak->tgl_lahir))->diff(new DateTime());
                                $usiaStr = $diff->y . 'thn ' . $diff->m . 'bln';
                                $rowClass = $row['idl_lengkap'] ? '' : ($row['kejar_idl'] || $row['kejar_ibl'] ? 'table-warning' : '');
                            @endphp
                            <tr class="{{ $rowClass }}">
                                <td>{{ $anakList->firstItem() + $loop->index }}</td>
                                <td>
                                    <a href="{{ route('admin.showAnak', $anak->hashid) }}" class="fw-semibold text-decoration-none">
                                        {{ $anak->nama }}
                                    </a>
                                    <br><small class="text-muted">{{ $anak->nik }}
                                        @if($anak->isDummyNik())
                                            <span class="badge bg-warning text-dark ms-1 fw-normal" style="font-size:.7em">NIK Dummy</span>
                                        @endif
                                    </small>
                                </td>
                                <td>{{ $usiaStr }}</td>
                                <td>{{ $anak->kel?->nama ?? '—' }}</td>
                                <td>{{ $anak->posyandu?->nama ?? '—' }}</td>
                                <td>
                                    @if($row['idl_lengkap'])
                                        <span class="badge bg-success">Lengkap</span>
                                    @elseif($row['kejar_idl'])
                                        <span class="badge bg-danger">Kejar IDL</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Belum Lengkap</span>
                                    @endif
                                    @if($row['kejar_ibl'])
                                        <span class="badge bg-orange ms-1" style="background:#fd7e14;">Kejar IBL</span>
                                    @endif
                                </td>
                                <td>
                                    @if(count($row['vaksin_kejar']) > 0)
                                        <small>{{ implode(', ', array_slice($row['vaksin_kejar'], 0, 3)) }}
                                        @if(count($row['vaksin_kejar']) > 3)
                                            <span class="text-muted">+{{ count($row['vaksin_kejar']) - 3 }} lainnya</span>
                                        @endif
                                        </small>
                                    @else
                                        <small class="text-muted">—</small>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('admin.jadwalImunisasi', $anak->hashid) }}" class="btn btn-sm btn-outline-primary">Jadwal</a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">Tidak ada anak pada filter ini.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($anakList->hasPages())
            <div class="card-footer bg-white">
                {{ $anakList->links('pagination::bootstrap-4') }}
            </div>
            @endif
        </div>
    </div>
</div>

{{-- Korelasi IDL vs Stunting (Paket E) --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0">Korelasi Cakupan IDL vs Prevalensi Stunting</h5>
                <small class="text-muted">Tiap titik = 1 kelurahan. Korelasi tingkat wilayah, bukan kausalitas individual.</small>
            </div>
            <div class="card-body">
                @if(count($korelasiData) > 0)
                <canvas id="chartKorelasi" height="110"></canvas>
                @else
                <div class="text-center text-muted py-4">Belum ada data balita terukur untuk membentuk korelasi.</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('custom_scripts')
{{-- Daftar Anak kini dipaginasi server-side (lihat AdminController::imunisasiDashboard);
     DataTables client-side dilepas agar tak menduplikasi paging. --}}

{{-- Scatter korelasi IDL vs stunting --}}
@if(count($korelasiData) > 0)
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function () {
    var data = @json($korelasiData);
    var points = data.map(function (d) {
        return { x: d.idl_pct, y: d.stunting_pct, nama: d.nama, total: d.total_balita, idl: d.idl_lengkap, stunt: d.stunting };
    });

    // Garis tren linear sederhana (least squares).
    var n = points.length, sx = 0, sy = 0, sxy = 0, sxx = 0;
    points.forEach(function (p) { sx += p.x; sy += p.y; sxy += p.x * p.y; sxx += p.x * p.x; });
    var trend = [];
    if (n >= 2 && (n * sxx - sx * sx) !== 0) {
        var b = (n * sxy - sx * sy) / (n * sxx - sx * sx);
        var a = (sy - b * sx) / n;
        trend = [{ x: 0, y: a }, { x: 100, y: a + b * 100 }];
    }

    var ctx = document.getElementById('chartKorelasi');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'scatter',
        data: {
            datasets: [
                {
                    label: 'Kelurahan',
                    data: points,
                    backgroundColor: 'rgba(8,145,178,0.7)',
                    pointRadius: 6, pointHoverRadius: 8
                },
                {
                    type: 'line', label: 'Tren', data: trend,
                    borderColor: '#dc2626', borderDash: [6, 4], borderWidth: 2,
                    pointRadius: 0, fill: false
                }
            ]
        },
        options: {
            scales: {
                x: { title: { display: true, text: '% Imunisasi Dasar Lengkap (IDL)' }, min: 0, max: 100 },
                y: { title: { display: true, text: '% Stunting' }, min: 0, max: 100 }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function (c) {
                            var p = c.raw;
                            if (p.nama === undefined) return '';
                            return [p.nama, 'IDL: ' + p.idl + '/' + p.total + ' (' + p.x + '%)', 'Stunting: ' + p.stunt + '/' + p.total + ' (' + p.y + '%)'];
                        }
                    }
                }
            }
        }
    });
})();
</script>
@endif
@endsection

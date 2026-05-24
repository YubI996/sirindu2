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
                                    {{ $kec->nama }}
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
                                    {{ $kel->nama }}
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
                                    {{ $pos->nama }}
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
    $butuhKejar      = $anakList->where('kejar_idl', true)->count() + $anakList->where('kejar_ibl', true)->count();
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
                <span class="badge bg-secondary">{{ $anakList->count() }} anak</span>
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
                            @foreach($anakList as $i => $row)
                            @php
                                $anak    = $row['anak'];
                                $diff    = (new DateTime($anak->tgl_lahir))->diff(new DateTime());
                                $usiaStr = $diff->y . 'thn ' . $diff->m . 'bln';
                                $rowClass = $row['idl_lengkap'] ? '' : ($row['kejar_idl'] || $row['kejar_ibl'] ? 'table-warning' : '');
                            @endphp
                            <tr class="{{ $rowClass }}">
                                <td>{{ $i + 1 }}</td>
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
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('custom_scripts')
<script src="{{ asset('admin/src/plugins/datatables/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('admin/src/plugins/datatables/js/dataTables.bootstrap4.min.js') }}"></script>
<script>
$(document).ready(function () {
    $('#tabelAnakImunisasi').DataTable({
        responsive: true,
        pageLength: 25,
        language: {
            search: 'Cari:',
            lengthMenu: 'Tampilkan _MENU_ data',
            info: 'Menampilkan _START_ s/d _END_ dari _TOTAL_ data',
            paginate: { previous: 'Sebelumnya', next: 'Selanjutnya' },
        },
        columnDefs: [{ orderable: false, targets: [7] }],
    });
});
</script>
@endsection

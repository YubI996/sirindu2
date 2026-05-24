@extends('admin::layouts.app')
@section('title')Admin@endsection
@section('title-content')Jadwal Imunisasi@endsection
@section('item')Data Anak@endsection
@section('item-active')Jadwal Imunisasi - {{ $data->nama }}@endsection

@php
$statusRowClass = fn(string $status): string => match($status) {
    'sudah'         => 'table-success',
    'terlambat'     => 'table-danger',
    'kadaluarsa'    => 'table-secondary',
    'tidak_relevan' => 'table-light',
    default         => '',
};

$statusBadge = fn(string $status): string => match($status) {
    'sudah'         => '<span class="badge bg-success">Sudah</span>',
    'terlambat'     => '<span class="badge bg-danger">Terlambat</span>',
    'kadaluarsa'    => '<span class="badge bg-secondary">Kedaluwarsa</span>',
    'tidak_relevan' => '<span class="badge bg-light text-secondary border">Tidak Relevan</span>',
    default         => '<span class="badge bg-warning text-dark">Belum</span>',
};

$usiaLabel = function(int $min, int $max): string {
    if ($max < 60)  return "$min – $max hari";
    if ($max < 730) return floor($min/30) . ' – ' . ceil($max/30) . ' bln';
    return floor($min/365) . ' – ' . ceil($max/365) . ' thn';
};
@endphp

@section('content')
@if ($errors->any())
<div class="alert alert-danger">
    <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
</div>
@endif

{{-- Child Info --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-primary text-white"><h5 class="mb-0">Informasi Anak</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <p><strong>Nama:</strong> {{ $data->nama }}</p>
                        <p><strong>NIK:</strong> {{ $data->nik }}</p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>Tgl Lahir:</strong> {{ date('d-m-Y', strtotime($data->tgl_lahir)) }}</p>
                        <p><strong>Jenis Kelamin:</strong> {{ $data->jk == 1 ? 'Laki-laki' : 'Perempuan' }}</p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>Usia:</strong>
                            @php
                                $diff = (new DateTime($data->tgl_lahir))->diff(new DateTime());
                                echo $diff->y . ' thn ' . $diff->m . ' bln';
                            @endphp
                        </p>
                        <p><strong>Nama Ibu:</strong> {{ $data->nama_ibu }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Action Bar --}}
<div class="row mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Jadwal Imunisasi Berdasarkan Usia</h5>
        <a href="{{ route('admin.imunisasiLengkap', $data->hashid) }}" class="btn btn-success btn-sm">+ Catat Imunisasi</a>
    </div>
</div>

@php
// Group jadwal by kategori
$idlItems    = collect($jadwal)->filter(fn($i) => $i['vaksin']->kategori === 'Wajib');
$iblItems    = collect($jadwal)->filter(fn($i) => $i['vaksin']->kategori === 'Booster');
$islItems    = collect($jadwal)->filter(fn($i) => $i['vaksin']->kategori === 'Tambahan');
$statusCount = collect($jadwal)->groupBy('status')->map->count();
@endphp

{{-- IDL Table --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Imunisasi Dasar Lengkap (IDL)</h5>
                @php $idlLengkap = $idlItems->whereIn('status',['sudah','tidak_relevan'])->count() >= $idlItems->count(); @endphp
                @if($idlLengkap)
                    <span class="badge bg-success fs-6">Lengkap</span>
                @else
                    <span class="badge bg-danger fs-6">Belum Lengkap</span>
                @endif
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Vaksin</th>
                                <th>Usia Pemberian</th>
                                <th>Jadwal Min</th>
                                <th>Jadwal Max</th>
                                <th>Batas Kejar</th>
                                <th>Status</th>
                                <th>Tgl Diberikan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($idlItems as $item)
                            @php $rowClass = $statusRowClass($item['status']); @endphp
                            <tr class="{{ $rowClass }}">
                                <td><strong>{{ $item['vaksin']->nama }}</strong><br><small class="text-muted">{{ $item['vaksin']->kode }}</small></td>
                                <td>{{ $usiaLabel($item['vaksin']->usia_pemberian_min, $item['vaksin']->usia_pemberian_max) }}</td>
                                <td>{{ date('d-m-Y', strtotime($item['tanggal_min'])) }}</td>
                                <td>{{ date('d-m-Y', strtotime($item['tanggal_max'])) }}</td>
                                <td>
                                    @if($item['catchup_deadline'])
                                        {{ date('d-m-Y', strtotime($item['catchup_deadline'])) }}
                                    @elseif(!$item['vaksin']->bisa_dikejar)
                                        <span class="text-danger fw-semibold">Tidak bisa dikejar</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>{!! $statusBadge($item['status']) !!}</td>
                                <td>
                                    @if($item['imunisasi'])
                                        {{ date('d-m-Y', strtotime($item['imunisasi']->tanggal_pemberian)) }}
                                    @else —
                                    @endif
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

{{-- IBL Table --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-warning d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Imunisasi Booster (IBL)</h5>
                @php $iblLengkap = $iblItems->whereIn('status',['sudah','tidak_relevan'])->count() >= $iblItems->count(); @endphp
                @if($iblItems->isNotEmpty())
                    @if($iblLengkap)
                        <span class="badge bg-success fs-6">Lengkap</span>
                    @else
                        <span class="badge bg-danger fs-6">Belum Lengkap</span>
                    @endif
                @endif
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Vaksin</th>
                                <th>Usia Pemberian</th>
                                <th>Jadwal Min</th>
                                <th>Jadwal Max</th>
                                <th>Batas Kejar</th>
                                <th>Status</th>
                                <th>Tgl Diberikan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($iblItems as $item)
                            @php $rowClass = $statusRowClass($item['status']); @endphp
                            <tr class="{{ $rowClass }}">
                                <td><strong>{{ $item['vaksin']->nama }}</strong><br><small class="text-muted">{{ $item['vaksin']->kode }}</small></td>
                                <td>{{ $usiaLabel($item['vaksin']->usia_pemberian_min, $item['vaksin']->usia_pemberian_max) }}</td>
                                <td>{{ date('d-m-Y', strtotime($item['tanggal_min'])) }}</td>
                                <td>{{ date('d-m-Y', strtotime($item['tanggal_max'])) }}</td>
                                <td>
                                    @if($item['catchup_deadline'])
                                        {{ date('d-m-Y', strtotime($item['catchup_deadline'])) }}
                                    @elseif(!$item['vaksin']->bisa_dikejar)
                                        <span class="text-danger fw-semibold">Tidak bisa dikejar</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>{!! $statusBadge($item['status']) !!}</td>
                                <td>
                                    @if($item['imunisasi'])
                                        {{ date('d-m-Y', strtotime($item['imunisasi']->tanggal_pemberian)) }}
                                    @else —
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center text-muted">Tidak ada data</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ISL Table --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">Imunisasi Anak Sekolah / BIAS (ISL)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Vaksin</th>
                                <th>Usia Pemberian</th>
                                <th>Jadwal Min</th>
                                <th>Jadwal Max</th>
                                <th>Batas Kejar</th>
                                <th>Status</th>
                                <th>Tgl Diberikan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($islItems as $item)
                            @php $rowClass = $statusRowClass($item['status']); @endphp
                            <tr class="{{ $rowClass }}">
                                <td>
                                    <strong>{{ $item['vaksin']->nama }}</strong>
                                    <br><small class="text-muted">{{ $item['vaksin']->kode }}</small>
                                    @if(in_array($item['vaksin']->kode, ['HPV1','HPV2']))
                                        <br><small class="text-info"><em>Khusus perempuan</em></small>
                                    @endif
                                </td>
                                <td>{{ $usiaLabel($item['vaksin']->usia_pemberian_min, $item['vaksin']->usia_pemberian_max) }}</td>
                                <td>{{ date('d-m-Y', strtotime($item['tanggal_min'])) }}</td>
                                <td>{{ date('d-m-Y', strtotime($item['tanggal_max'])) }}</td>
                                <td>
                                    @if($item['catchup_deadline'])
                                        {{ date('d-m-Y', strtotime($item['catchup_deadline'])) }}
                                    @elseif(!$item['vaksin']->bisa_dikejar)
                                        <span class="text-danger fw-semibold">Tidak bisa dikejar</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>{!! $statusBadge($item['status']) !!}</td>
                                <td>
                                    @if($item['imunisasi'])
                                        {{ date('d-m-Y', strtotime($item['imunisasi']->tanggal_pemberian)) }}
                                    @else —
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center text-muted">Tidak ada data</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Summary --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-dark text-white"><h5 class="mb-0">Ringkasan Status</h5></div>
            <div class="card-body">
                <div class="row g-3 text-center">
                    <div class="col-6 col-md-3">
                        <div class="p-3 bg-success text-white rounded">
                            <div class="fs-2 fw-bold">{{ $statusCount['sudah'] ?? 0 }}</div>
                            <div>Sudah</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-3 bg-warning rounded">
                            <div class="fs-2 fw-bold">{{ $statusCount['belum'] ?? 0 }}</div>
                            <div>Belum</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-3 bg-danger text-white rounded">
                            <div class="fs-2 fw-bold">{{ $statusCount['terlambat'] ?? 0 }}</div>
                            <div>Terlambat</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-3 bg-secondary text-white rounded">
                            <div class="fs-2 fw-bold">{{ $statusCount['kadaluarsa'] ?? 0 }}</div>
                            <div>Kedaluwarsa</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Catch-up Planner --}}
@if(count($catchupPlan) > 0)
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Rencana Imunisasi Kejar ({{ count($catchupPlan) }} vaksin)
                </h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">Vaksin berikut terlewat dari jadwal ideal dan harus segera diberikan. Urutan pemberian berdasarkan prioritas usia.</p>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead class="table-danger">
                            <tr>
                                <th>#</th>
                                <th>Vaksin</th>
                                <th>Tanggal Anjuran</th>
                                <th>Catatan Interval</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($catchupPlan as $i => $plan)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>
                                    <strong>{{ $plan['vaksin']->nama }}</strong>
                                    <br><small class="text-muted">{{ $plan['vaksin']->kode }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-warning text-dark">{{ date('d-m-Y', strtotime($plan['tanggal_anjuran'])) }}</span>
                                </td>
                                <td>
                                    @if($plan['catatan'])
                                        <small class="text-info">{{ $plan['catatan'] }}</small>
                                    @elseif($plan['vaksin']->interval_hari)
                                        <small class="text-muted">Interval min. {{ $plan['vaksin']->interval_hari }} hari dari dosis sebelumnya</small>
                                    @else
                                        <small class="text-muted">—</small>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('admin.imunisasiLengkap', $data->hashid) }}"
                                       class="btn btn-sm btn-outline-danger">Catat</a>
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

<div class="row mt-3 mb-4">
    <div class="col-12">
        <a href="{{ route('admin.anak') }}" class="btn btn-secondary btn-sm">Kembali</a>
        <a href="{{ route('admin.imunisasiLengkap', $data->hashid) }}" class="btn btn-success btn-sm">Catat Imunisasi</a>
    </div>
</div>
@endsection
@section('custom_scripts')
@endsection

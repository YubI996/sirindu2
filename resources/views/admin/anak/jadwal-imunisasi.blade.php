@extends('admin::layouts.app')
@section('title')
Admin
@endsection
@section('title-content')
Jadwal Imunisasi
@endsection
@section('item')
Data Anak
@endsection
@section('item-active')
Jadwal Imunisasi - {{ $data->nama }}
@endsection
@section('content')
@if ($errors->any())
<div class="alert alert-danger">
    <ul>
        @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Informasi Anak</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <p><strong>Nama:</strong> {{ $data->nama }}</p>
                        <p><strong>NIK:</strong> {{ $data->nik }}</p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>Tanggal Lahir:</strong> {{ date('d-m-Y', strtotime($data->tgl_lahir)) }}</p>
                        <p><strong>Jenis Kelamin:</strong> {{ $data->jk == 'L' ? 'Laki-laki' : 'Perempuan' }}</p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>Usia:</strong>
                            @php
                                $lahir = new DateTime($data->tgl_lahir);
                                $now = new DateTime();
                                $diff = $lahir->diff($now);
                                echo $diff->y . ' tahun ' . $diff->m . ' bulan';
                            @endphp
                        </p>
                        <p><strong>Nama Ibu:</strong> {{ $data->nama_ibu }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center">
            <h5>Jadwal Imunisasi Berdasarkan Usia</h5>
            <div>
                <a href="{{ route('admin.imunisasiLengkap', $data->hashid) }}" class="btn btn-success">Catat Imunisasi Baru</a>
            </div>
        </div>
    </div>
</div>

<!-- Imunisasi Dasar -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Imunisasi Dasar (0-11 Bulan)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th>Vaksin</th>
                                <th>Usia Pemberian</th>
                                <th>Jadwal Min</th>
                                <th>Jadwal Max</th>
                                <th>Status</th>
                                <th>Tanggal Diberikan</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($jadwal as $item)
                                @if($item['vaksin']->kategori == 'Imunisasi Dasar')
                                <tr class="{{ $item['status'] == 'sudah' ? 'table-success' : ($item['status'] == 'terlambat' ? 'table-danger' : '') }}">
                                    <td>
                                        <strong>{{ $item['vaksin']->nama }}</strong>
                                        <br><small class="text-muted">{{ $item['vaksin']->kode }}</small>
                                    </td>
                                    <td>
                                        @php
                                            $minDays = $item['vaksin']->usia_pemberian_min;
                                            $maxDays = $item['vaksin']->usia_pemberian_max;
                                            if ($minDays < 30) {
                                                echo $minDays . ' - ' . $maxDays . ' hari';
                                            } else {
                                                echo floor($minDays/30) . ' - ' . floor($maxDays/30) . ' bulan';
                                            }
                                        @endphp
                                    </td>
                                    <td>{{ date('d-m-Y', strtotime($item['tanggal_min'])) }}</td>
                                    <td>{{ date('d-m-Y', strtotime($item['tanggal_max'])) }}</td>
                                    <td>
                                        @if($item['status'] == 'sudah')
                                        <span class="badge badge-success">Sudah Diberikan</span>
                                        @elseif($item['status'] == 'terlambat')
                                        <span class="badge badge-danger">Terlambat</span>
                                        @else
                                        <span class="badge badge-warning">Belum Diberikan</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($item['imunisasi'])
                                            {{ date('d-m-Y', strtotime($item['imunisasi']->tanggal_pemberian)) }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ $item['vaksin']->keterangan }}</td>
                                </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Imunisasi Lanjutan -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-warning">
                <h5 class="mb-0">Imunisasi Lanjutan (Baduta 18-24 Bulan)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th>Vaksin</th>
                                <th>Usia Pemberian</th>
                                <th>Jadwal Min</th>
                                <th>Jadwal Max</th>
                                <th>Status</th>
                                <th>Tanggal Diberikan</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $hasLanjutan = false; @endphp
                            @foreach($jadwal as $item)
                                @if($item['vaksin']->kategori == 'Imunisasi Lanjutan')
                                @php $hasLanjutan = true; @endphp
                                <tr class="{{ $item['status'] == 'sudah' ? 'table-success' : ($item['status'] == 'terlambat' ? 'table-danger' : '') }}">
                                    <td>
                                        <strong>{{ $item['vaksin']->nama }}</strong>
                                        <br><small class="text-muted">{{ $item['vaksin']->kode }}</small>
                                    </td>
                                    <td>
                                        @php
                                            $minDays = $item['vaksin']->usia_pemberian_min;
                                            $maxDays = $item['vaksin']->usia_pemberian_max;
                                            echo floor($minDays/30) . ' - ' . floor($maxDays/30) . ' bulan';
                                        @endphp
                                    </td>
                                    <td>{{ date('d-m-Y', strtotime($item['tanggal_min'])) }}</td>
                                    <td>{{ date('d-m-Y', strtotime($item['tanggal_max'])) }}</td>
                                    <td>
                                        @if($item['status'] == 'sudah')
                                        <span class="badge badge-success">Sudah Diberikan</span>
                                        @elseif($item['status'] == 'terlambat')
                                        <span class="badge badge-danger">Terlambat</span>
                                        @else
                                        <span class="badge badge-warning">Belum Diberikan</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($item['imunisasi'])
                                            {{ date('d-m-Y', strtotime($item['imunisasi']->tanggal_pemberian)) }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ $item['vaksin']->keterangan }}</td>
                                </tr>
                                @endif
                            @endforeach
                            @if(!$hasLanjutan)
                            <tr>
                                <td colspan="7" class="text-center text-muted">Tidak ada data imunisasi lanjutan</td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Imunisasi Anak Sekolah -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">Imunisasi Anak Sekolah (6+ Tahun)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th>Vaksin</th>
                                <th>Usia Pemberian</th>
                                <th>Jadwal Min</th>
                                <th>Jadwal Max</th>
                                <th>Status</th>
                                <th>Tanggal Diberikan</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $hasSekolah = false; @endphp
                            @foreach($jadwal as $item)
                                @if($item['vaksin']->kategori == 'Imunisasi Anak Sekolah')
                                @php $hasSekolah = true; @endphp
                                <tr class="{{ $item['status'] == 'sudah' ? 'table-success' : ($item['status'] == 'terlambat' ? 'table-danger' : '') }}">
                                    <td>
                                        <strong>{{ $item['vaksin']->nama }}</strong>
                                        <br><small class="text-muted">{{ $item['vaksin']->kode }}</small>
                                    </td>
                                    <td>
                                        @php
                                            $minDays = $item['vaksin']->usia_pemberian_min;
                                            $maxDays = $item['vaksin']->usia_pemberian_max;
                                            echo floor($minDays/365) . ' - ' . floor($maxDays/365) . ' tahun';
                                        @endphp
                                    </td>
                                    <td>{{ date('d-m-Y', strtotime($item['tanggal_min'])) }}</td>
                                    <td>{{ date('d-m-Y', strtotime($item['tanggal_max'])) }}</td>
                                    <td>
                                        @if($item['status'] == 'sudah')
                                        <span class="badge badge-success">Sudah Diberikan</span>
                                        @elseif($item['status'] == 'terlambat')
                                        <span class="badge badge-danger">Terlambat</span>
                                        @else
                                        <span class="badge badge-warning">Belum Diberikan</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($item['imunisasi'])
                                            {{ date('d-m-Y', strtotime($item['imunisasi']->tanggal_pemberian)) }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ $item['vaksin']->keterangan }}</td>
                                </tr>
                                @endif
                            @endforeach
                            @if(!$hasSekolah)
                            <tr>
                                <td colspan="7" class="text-center text-muted">Tidak ada data imunisasi anak sekolah</td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Summary -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">Ringkasan Status Imunisasi</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-4">
                        <div class="p-3 bg-success text-white rounded">
                            <h3>{{ collect($jadwal)->where('status', 'sudah')->count() }}</h3>
                            <p class="mb-0">Sudah Diberikan</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-warning rounded">
                            <h3>{{ collect($jadwal)->where('status', 'belum')->count() }}</h3>
                            <p class="mb-0">Belum Diberikan</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-danger text-white rounded">
                            <h3>{{ collect($jadwal)->where('status', 'terlambat')->count() }}</h3>
                            <p class="mb-0">Terlambat</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-3">
    <div class="col-md-12">
        <a href="{{ route('admin.anak') }}" class="btn btn-secondary">Kembali ke Daftar Anak</a>
        <a href="{{ route('admin.imunisasiLengkap', $data->hashid) }}" class="btn btn-success">Catat Imunisasi</a>
    </div>
</div>
@endsection
@section('custom_scripts')
@endsection

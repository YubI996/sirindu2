@extends('admin::layouts.app')

@section('title') Verifikasi RT — SIRINDU @endsection
@section('title-content') Verifikasi RT @endsection
@section('item') Gizi & Timbang @endsection
@section('item-active') Verifikasi RT @endsection

@section('content')
<div class="page-header">
    <div class="row">
        <div class="col-md-12">
            <div class="title">
                <h4>Antrean Verifikasi RT</h4>
                <p class="text-muted mb-0">Usulan status domisili dari RT. Menyetujui klaim "warga RT saya" akan mengisi RT anak; status lain hanya menjadi penanda.</p>
            </div>
        </div>
    </div>
</div>

@if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
@if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link {{ $tab === 'domisili' ? 'active' : '' }}" href="{{ route('admin.verifikasiRt.index') }}">Status domisili <span class="badge badge-light">{{ $antrean->total() }}</span></a></li>
    <li class="nav-item"><a class="nav-link {{ $tab === 'tautan' ? 'active' : '' }}" href="{{ route('admin.verifikasiRt.index', ['tab' => 'tautan']) }}">Tautan identitas <span class="badge badge-light">{{ $antreanTautan->total() }}</span></a></li>
</ul>

@if($tab === 'domisili')
<div class="card-box mb-3">
    <form method="GET" class="form-inline">
        <select name="rt" class="form-control mr-2">
            <option value="">Semua RT</option>
            @foreach($rtList as $rt)
            <option value="{{ $rt->id }}" {{ (string) $filter['rt'] === (string) $rt->id ? 'selected' : '' }}>{{ $rt->name }}</option>
            @endforeach
        </select>
        <select name="status" class="form-control mr-2">
            <option value="">Semua status</option>
            @foreach($label as $k => $v)
            <option value="{{ $k }}" {{ $filter['status'] === $k ? 'selected' : '' }}>{{ $v }}</option>
            @endforeach
        </select>
        <button class="btn btn-primary">Terapkan</button>
        <a href="{{ route('admin.verifikasiRt.index') }}" class="btn btn-link">Reset</a>
        <span class="ml-auto text-muted">{{ $antrean->total() }} usulan menunggu</span>
    </form>
</div>

<div class="card-box">
    <div class="table-responsive">
        <table class="table table-striped table-sm">
            <thead>
                <tr><th>Anak</th><th>Wilayah</th><th>Usulan RT</th><th>Catatan</th><th>Diusulkan</th><th style="width:260px">Tinjauan</th></tr>
            </thead>
            <tbody>
            @forelse($antrean as $v)
                <tr>
                    <td>
                        <b>{{ $v->anak->nama }}</b><br>
                        <small class="text-muted">NIK {{ $v->anak->nik }} · lahir {{ $v->anak->tgl_lahir }} · sumber {{ $v->anak->sumber }}</small>
                    </td>
                    <td>{{ $v->rt->name }}<br><small class="text-muted">{{ $v->anak->alamat ?: '-' }}</small></td>
                    <td>
                        <span class="badge badge-{{ $v->status === 'berdomisili' ? 'success' : ($v->status === 'meninggal' ? 'danger' : 'warning') }}">{{ $label[$v->status] }}</span>
                        @if($v->klaim_id_rt) <br><small class="text-muted">klaim: masukkan ke {{ $v->rt->name }}</small> @endif
                    </td>
                    <td>{{ $v->catatan ?: '-' }}</td>
                    <td><small>{{ $v->pengusul?->name }}<br>{{ $v->diusulkan_at?->format('d/m/Y H:i') }}</small></td>
                    <td>
                        <form method="POST" action="{{ route('admin.verifikasiRt.tinjau', $v) }}" class="form-inline">
                            @csrf
                            <input type="hidden" name="rt" value="{{ $filter['rt'] }}">
                            <input type="hidden" name="status" value="{{ $filter['status'] }}">
                            <input type="text" name="catatan" class="form-control form-control-sm mr-1 mb-1" placeholder="Catatan reviu" maxlength="1000" style="width:100%">
                            <button class="btn btn-sm btn-success mr-1" name="setuju" value="1">Setujui</button>
                            <button class="btn btn-sm btn-outline-danger" name="setuju" value="0">Tolak</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada usulan yang menunggu.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $antrean->onEachSide(1)->links('pagination::bootstrap-4') }}
</div>
@else
{{-- Tab Tautan identitas: keputusan RT "sama"/"beda" atas kandidat hasil pindai (spec §6.1) --}}
<div class="card-box mb-3">
    <div class="d-flex flex-wrap align-items-center">
        <span class="text-muted mr-3">Kandidat hasil pindai: <b>{{ number_format($ringkasanKandidat['jumlah']) }}</b>
            @if($ringkasanKandidat['dipindai_at']) · dipindai {{ \Carbon\Carbon::parse($ringkasanKandidat['dipindai_at'])->format('d/m/Y H:i') }} @endif</span>
        <a class="mr-3" href="{{ route('admin.gabung.index') }}"><b>{{ $menungguGabung }} tautan menunggu penggabungan</b></a>
        @if(auth()->user()->isSuperAdmin())
        <form method="POST" action="{{ route('admin.verifikasiRt.pindai') }}" class="ml-auto">@csrf
            <button class="btn btn-outline-primary btn-sm"><i class="fa fa-refresh mr-1"></i> Pindai ulang</button>
        </form>
        @endif
    </div>
</div>

<div class="card-box">
    @forelse($antreanTautan as $t)
    <div class="border rounded mb-3">
        <div class="px-3 py-2 bg-light d-flex justify-content-between flex-wrap">
            <span><b>{{ $t->keputusan === 'sama' ? 'SAMA — satu anak' : 'BEDA orang' }}</b> · alasan pindai: {{ $t->via }} · skor {{ $t->skor }}</span>
            <small class="text-muted">{{ $t->pengusul?->name }} ({{ $t->pengusul?->rt?->name }}) · {{ $t->diusulkan_at?->format('d/m/Y H:i') }}{{ $t->catatan ? ' · "'.$t->catatan.'"' : '' }}</small>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th style="width:140px"></th><th>Baris A ({{ $t->anakA?->sumber }})</th><th>Baris B ({{ $t->anakB?->sumber }})</th></tr></thead>
                <tbody>
                @foreach(['nik' => 'NIK', 'nama' => 'Nama', 'tgl_lahir' => 'Tgl lahir', 'nama_ibu' => 'Ibu', 'nama_ayah' => 'Ayah', 'no_kk' => 'No KK', 'alamat' => 'Alamat', 'alamat_ktp' => 'Alamat KTP'] as $f => $labelKolom)
                    @php $beda = trim((string) $t->anakA?->$f) !== trim((string) $t->anakB?->$f); @endphp
                    <tr class="{{ $beda ? 'table-warning' : '' }}"><th>{{ $labelKolom }}</th><td>{{ $t->anakA?->$f ?: '-' }}</td><td>{{ $t->anakB?->$f ?: '-' }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <form method="POST" action="{{ route('admin.verifikasiRt.tinjauTautan', $t) }}" class="form-inline px-3 py-2">
            @csrf
            <input type="text" name="catatan" class="form-control form-control-sm mr-2" placeholder="Catatan reviu" maxlength="1000" style="min-width:260px">
            <button class="btn btn-sm btn-success mr-1" name="setuju" value="1">Setujui</button>
            <button class="btn btn-sm btn-outline-danger" name="setuju" value="0">Tolak</button>
        </form>
    </div>
    @empty
    <div class="text-center text-muted py-4">Tidak ada tautan yang menunggu.</div>
    @endforelse
    {{ $antreanTautan->onEachSide(1)->links('pagination::bootstrap-4') }}
</div>
@endif
@endsection

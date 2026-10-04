@extends('admin::layouts.app')

@section('title') Verifikasi RT — SIRINDU @endsection
@section('title-content') Verifikasi RT @endsection
@section('item') Data Master @endsection
@section('item-active') Verifikasi RT @endsection

@section('content')
@include('admin.verifikasi-rt.styles')
<div class="rt-admin">
<div class="page-header">
    <div class="row">
        <div class="col-md-12">
            <div class="title">
                <h4>Verifikasi RT</h4>
                <p class="text-muted mb-0">Usulan status domisili dari RT (menyetujui klaim "warga RT saya" mengisi RT anak; status lain hanya penanda) dan pasangan anak yang dicurigai sama.</p>
            </div>
        </div>
    </div>
</div>

@if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
@if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif

{{-- Pindai: kumpulkan ulang pasangan kandidat dari seluruh data anak (Dinkes; satu pindai pada satu waktu) --}}
@if(auth()->user()->isSuperAdmin())
<div class="card-box mb-3">
    <div class="d-flex flex-wrap align-items-center">
        <span class="text-muted mr-3">Kandidat hasil pindai: <b>{{ number_format($ringkasanKandidat['jumlah']) }}</b>
            @if($ringkasanKandidat['dipindai_at']) · terakhir dipindai {{ \Carbon\Carbon::parse($ringkasanKandidat['dipindai_at'])->format('d/m/Y H:i') }} @else · belum pernah dipindai @endif</span>
        <a class="mr-3" href="{{ route('admin.gabung.index') }}"><b>{{ $menungguGabung }} tautan menunggu penggabungan</b></a>
        <form method="POST" action="{{ route('admin.verifikasiRt.pindai') }}" class="ml-auto" onsubmit="return confirm('Pindai ulang seluruh data anak? Prosesnya berjalan di latar belakang dan bisa beberapa menit.')">@csrf
            <button class="btn btn-outline-primary btn-sm" {{ $sedangMemindai ? 'disabled' : '' }}><i class="fa fa-refresh mr-1"></i> Pindai ulang</button>
        </form>
    </div>
    @if($sedangMemindai)<small class="text-muted d-block mt-2">Pindai sedang berjalan. Muat ulang halaman setelah beberapa saat.</small>@endif
</div>
@endif

<nav class="rt-section-nav" aria-label="Menu verifikasi RT">
<ul class="nav nav-tabs">
    @if(auth()->user()->isSuperAdmin())
    <li class="nav-item"><a class="nav-link {{ $tab === 'dicurigai' ? 'active' : '' }}" href="{{ route('admin.verifikasiRt.index', ['tab' => 'dicurigai']) }}">Dicurigai sama <span class="badge badge-light">{{ number_format($jumlahDicurigai) }}</span></a></li>
    @endif
    <li class="nav-item"><a class="nav-link {{ $tab === 'domisili' ? 'active' : '' }}" href="{{ route('admin.verifikasiRt.index', ['tab' => 'domisili']) }}">Status domisili <span class="badge badge-light">{{ $antrean->total() }}</span></a></li>
    <li class="nav-item"><a class="nav-link {{ $tab === 'tautan' ? 'active' : '' }}" href="{{ route('admin.verifikasiRt.index', ['tab' => 'tautan']) }}">Tautan identitas <span class="badge badge-light">{{ $antreanTautan->total() }}</span></a></li>
</ul>
@if(auth()->user()->isSuperAdmin() || auth()->user()->id_kel)
<a class="btn btn-outline-primary rt-access-link" href="{{ route('admin.aksesTautan.index') }}"><i class="fa fa-link" aria-hidden="true"></i> Kelola tautan akses RT</a>
@endif
</nav>

@if($tab === 'dicurigai')
{{-- Tab Dicurigai sama: pasangan hasil pindai yang belum diputus. Keputusan Dinkes langsung berlaku (tanpa usulan RT). --}}
@php
    $kolomBanding = [
        'NIK'        => fn ($x) => $x->nik,
        'Nama'       => fn ($x) => $x->nama,
        'Tgl lahir'  => fn ($x) => $x->tgl_lahir,
        'JK'         => fn ($x) => $x->jk == 1 ? 'L' : 'P',
        'No KK'      => fn ($x) => $x->no_kk,
        'Ibu'        => fn ($x) => $x->nama_ibu,
        'Ayah'       => fn ($x) => $x->nama_ayah,
        'Alamat'     => fn ($x) => $x->alamat,
        'Alamat KTP' => fn ($x) => $x->alamat_ktp,
        'Posyandu'   => fn ($x) => $x->posyandu?->name,
        'Kelurahan'  => fn ($x) => $x->kel?->name,
        'RT'         => fn ($x) => $x->rt?->name,
        'Sumber'     => fn ($x) => $x->sumber,
        'Dibuat'     => fn ($x) => $x->created_at?->format('d/m/Y'),
    ];
@endphp
<div class="card-box">
    <p class="text-muted">Pasangan anak yang dicurigai sama, skor tertinggi dulu. Keputusan Anda <b>langsung berlaku</b>: "Sama" masuk antrean Penggabungan, "Beda" mengeluarkan pasangan dari daftar. Ingin RT yang memastikan? Buat tautan RT dari kartu pasangan.</p>
    @forelse($kandidat as $k)
    @php
        $a = $k->anakA; $b = $k->anakB;
    @endphp
    @continue(!$a || !$b)
    @php
        $keduanyaOt = \App\Services\IdentitasMergeService::keduanyaOperasiTimbang($a, $b);
        $rtPasangan = collect([$a->rt, $b->rt])->filter()->unique('id');
    @endphp
    <div class="border rounded mb-3">
        <div class="px-3 py-2 bg-light d-flex justify-content-between flex-wrap">
            <span><b>Dicurigai sama</b> · alasan pindai: {{ $k->via }} · skor {{ $k->skor }}
                @if($keduanyaOt) <span class="badge badge-warning">keduanya Operasi Timbang</span> @endif</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th style="width:140px"></th><th>Baris A</th><th>Baris B</th></tr></thead>
                <tbody>
                @foreach($kolomBanding as $labelKolom => $ambil)
                    @php $va = $ambil($a); $vb = $ambil($b); @endphp
                    <tr class="{{ trim((string) $va) !== trim((string) $vb) ? 'table-warning' : '' }}"><th>{{ $labelKolom }}</th><td>{{ $va ?: '-' }}</td><td>{{ $vb ?: '-' }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <form method="POST" action="{{ route('admin.verifikasiRt.putuskan') }}" class="form-inline px-3 py-2">
            @csrf
            <input type="hidden" name="id_anak_a" value="{{ $k->id_anak_a }}">
            <input type="hidden" name="id_anak_b" value="{{ $k->id_anak_b }}">
            <input type="hidden" name="halaman_kandidat" value="{{ $kandidat->currentPage() }}">
            <input type="text" name="catatan" class="form-control form-control-sm mr-2" placeholder="Catatan (opsional)" maxlength="1000" style="min-width:260px">
            <button class="btn btn-sm btn-success mr-1" name="keputusan" value="sama" {{ $keduanyaOt ? 'disabled' : '' }}>Sama — satu anak</button>
            <button class="btn btn-sm btn-outline-danger" name="keputusan" value="beda">Beda orang</button>
        </form>
        @if($keduanyaOt)
        <small class="text-muted d-block px-3 pb-2">Keduanya berasal dari Operasi Timbang, jadi tidak bisa digabung. Hanya boleh diputus "Beda orang".</small>
        @endif
        @if($rtPasangan->isNotEmpty())
        <div class="px-3 pb-2">
            @foreach($rtPasangan as $rtp)
            <form method="POST" action="{{ route('admin.aksesTautan.buat', $rtp) }}" class="d-inline mr-3">@csrf
                <button class="btn btn-link btn-sm p-0"><i class="fa fa-link mr-1" aria-hidden="true"></i> Buat tautan RT {{ $rtp->name }}</button>
            </form>
            @endforeach
        </div>
        @endif
    </div>
    @empty
    <div class="text-center text-muted py-4">Tidak ada pasangan yang dicurigai. Tekan <b>Pindai ulang</b> untuk mengumpulkan kandidat dari data anak terbaru.</div>
    @endforelse
    {{ $kandidat->onEachSide(1)->links('pagination::bootstrap-4') }}
</div>
@elseif($tab === 'domisili')
<div class="card-box mb-3">
    <form method="GET" class="form-inline rt-filter-form">
        <input type="hidden" name="tab" value="domisili">
        <label class="rt-filter-field"><span>Wilayah RT</span>
        <select name="rt" class="form-control mr-2">
            <option value="">Semua RT</option>
            @foreach($rtList as $rt)
            <option value="{{ $rt->id }}" {{ (string) $filter['rt'] === (string) $rt->id ? 'selected' : '' }}>{{ $rt->name }}</option>
            @endforeach
        </select>
        </label>
        <label class="rt-filter-field"><span>Status domisili</span>
        <select name="status" class="form-control mr-2">
            <option value="">Semua status</option>
            @foreach($label as $k => $v)
            <option value="{{ $k }}" {{ $filter['status'] === $k ? 'selected' : '' }}>{{ $v }}</option>
            @endforeach
        </select>
        </label>
        <button class="btn btn-primary">Terapkan</button>
        <a href="{{ route('admin.verifikasiRt.index', ['tab' => 'domisili']) }}" class="btn btn-link">Reset</a>
        <span class="rt-filter-summary text-muted">{{ $antrean->total() }} usulan menunggu</span>
    </form>
</div>

<div class="card-box">
    <div class="table-responsive">
        <table class="table table-striped table-sm rt-review-table">
            <thead>
                <tr><th>Anak</th><th>Wilayah</th><th>Usulan RT</th><th>Catatan</th><th>Diusulkan</th><th style="width:260px">Tinjauan</th></tr>
            </thead>
            <tbody>
            @forelse($antrean as $v)
                <tr>
                    <td data-label="Anak">
                        <b>{{ $v->anak->nama }}</b><br>
                        <small class="text-muted">NIK {{ $v->anak->nik }} · lahir {{ $v->anak->tgl_lahir }} · sumber {{ $v->anak->sumber }}</small>
                    </td>
                    <td data-label="Wilayah">{{ $v->rt->name }}<br><small class="text-muted">{{ $v->anak->alamat ?: '-' }}</small></td>
                    <td data-label="Usulan RT">
                        <span class="badge badge-{{ $v->status === 'berdomisili' ? 'success' : ($v->status === 'meninggal' ? 'danger' : 'warning') }}">{{ $label[$v->status] }}</span>
                        @if($v->klaim_id_rt) <br><small class="text-muted">klaim: masukkan ke {{ $v->rt->name }}</small> @endif
                    </td>
                    <td data-label="Catatan">{{ $v->catatan ?: '-' }}</td>
                    <td data-label="Diusulkan"><small>{{ $v->pengusul?->name ?? 'Tautan RT' }}@if($v->pelaksana)<br>pengisi: <b>{{ $v->pelaksana }}</b>@endif<br>{{ $v->diusulkan_at?->format('d/m/Y H:i') }}</small></td>
                    <td data-label="Tinjauan">
                        <form method="POST" action="{{ route('admin.verifikasiRt.tinjau', $v) }}" class="form-inline rt-review-form">
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
                <tr><td colspan="6" class="rt-empty"><strong>Tidak ada usulan yang menunggu.</strong>Usulan dari RT akan muncul di sini untuk ditinjau.</td></tr>
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
    </div>
</div>

<div class="card-box">
    @forelse($antreanTautan as $t)
    <div class="border rounded mb-3">
        <div class="px-3 py-2 bg-light d-flex justify-content-between flex-wrap">
            <span><b>{{ $t->keputusan === 'sama' ? 'SAMA — satu anak' : 'BEDA orang' }}</b> · alasan pindai: {{ $t->via }} · skor {{ $t->skor }}</span>
            <small class="text-muted">{{ $t->pengusul?->name ?? 'Tautan RT' }} ({{ $t->rt?->name ?? $t->pengusul?->rt?->name }}){{ $t->pelaksana ? ' · pengisi: '.$t->pelaksana : '' }} · {{ $t->diusulkan_at?->format('d/m/Y H:i') }}{{ $t->catatan ? ' · "'.$t->catatan.'"' : '' }}</small>
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
</div>
@endsection

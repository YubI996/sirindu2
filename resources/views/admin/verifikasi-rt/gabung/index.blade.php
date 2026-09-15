@extends('admin::layouts.app')

@section('title') Penggabungan Data Anak — SIRINDU @endsection
@section('title-content') Penggabungan Data Anak @endsection
@section('item') Verifikasi RT @endsection
@section('item-active') Penggabungan @endsection

@section('content')
<div class="page-header"><div class="row"><div class="col-md-12"><div class="title">
    <h4>Menunggu penggabungan</h4>
    <p class="text-muted mb-0">Tautan "sama" yang sudah disetujui. Penggabungan memindahkan seluruh pengukuran/imunisasi ke satu baris, mencatat log, dan bisa dibatalkan. Baris Operasi Timbang selalu dipertahankan.</p>
</div></div></div></div>

@if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
@if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif

<div class="card-box mb-3">
    <a href="{{ route('admin.verifikasiRt.index', ['tab' => 'tautan']) }}" class="btn btn-link pl-0">&larr; Antrean reviu</a>
    <div class="table-responsive">
        <table class="table table-striped table-sm">
            <thead><tr><th>Baris A</th><th>Baris B</th><th>Pengusul</th><th>Disetujui</th><th></th></tr></thead>
            <tbody>
            @forelse($antrean as $t)
                <tr>
                    <td><b>{{ $t->anakA?->nama }}</b><br><small class="text-muted">NIK {{ $t->anakA?->nik }} · {{ $t->anakA?->sumber }}</small></td>
                    <td><b>{{ $t->anakB?->nama }}</b><br><small class="text-muted">NIK {{ $t->anakB?->nik }} · {{ $t->anakB?->sumber }}</small></td>
                    <td><small>{{ $t->pengusul?->name ?? 'Tautan RT' }} ({{ $t->rt?->name ?? $t->pengusul?->rt?->name }}){{ $t->pelaksana ? ' · pengisi: '.$t->pelaksana : '' }}</small></td>
                    <td><small>{{ $t->ditinjau_at?->format('d/m/Y H:i') }}</small></td>
                    <td class="text-right"><a class="btn btn-sm btn-primary" href="{{ route('admin.gabung.show', $t) }}">Gabungkan…</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">Tidak ada tautan yang menunggu penggabungan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card-box">
    <h5>Riwayat penggabungan</h5>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr><th>#</th><th>Dipertahankan</th><th>Dihapus</th><th>Oleh</th><th>Waktu</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($log as $l)
                <tr class="{{ $l->dibatalkan_at ? 'text-muted' : '' }}">
                    <td>{{ $l->id }}</td>
                    <td>#{{ $l->id_dipertahankan }} {{ $l->dipertahankan?->nama }}</td>
                    <td>#{{ $l->id_dihapus }} {{ $l->snapshot['anak_dihapus']['nama'] ?? '' }}<br><small>NIK {{ $l->snapshot['anak_dihapus']['nik'] ?? '' }}</small></td>
                    <td>{{ $l->pelaku?->name }}</td>
                    <td><small>{{ $l->created_at?->format('d/m/Y H:i') }}</small></td>
                    <td>{{ $l->dibatalkan_at ? 'Dibatalkan '.$l->dibatalkan_at->format('d/m/Y H:i').' oleh '.$l->pembatal?->name : 'Aktif' }}</td>
                    <td class="text-right">
                        @if(!$l->dibatalkan_at)
                        <form method="POST" action="{{ route('admin.gabung.batalkan', $l) }}" onsubmit="return confirm('Batalkan penggabungan #{{ $l->id }}? Baris yang dihapus akan dipulihkan.');">@csrf
                            <button class="btn btn-sm btn-outline-danger">Batalkan</button>
                        </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-3">Belum ada penggabungan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

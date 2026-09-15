@extends('admin::layouts.app')

@section('title') Tautan Akses RT — SIRINDU @endsection
@section('title-content') Tautan Akses RT @endsection
@section('item') Operasi Timbang @endsection
@section('item-active') Tautan Akses RT @endsection

@section('content')
@include('admin.verifikasi-rt.styles')
<div class="rt-admin">
<a class="rt-back" href="{{ route('admin.verifikasiRt.index') }}"><i class="fa fa-arrow-left" aria-hidden="true"></i> Antrean Verifikasi RT</a>
<div class="page-header">
    <div class="row">
        <div class="col-md-12">
            <div class="title">
                <h4>Tautan Akses RT</h4>
                <p class="text-muted mb-0">
                    Ketua RT membuka halaman verifikasi warga lewat tautan ini <b>tanpa akun</b>. Kirim tautan lewat WhatsApp.
                    Satu RT hanya punya satu tautan aktif — membuat yang baru otomatis mematikan yang lama.
                    Setiap keputusan tetap mencatat nama pengisinya.
                </p>
            </div>
        </div>
    </div>
</div>

@if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
@if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif

@if($baru = session('tautan_baru'))
{{-- Token hanya tampil sekali di sini; muat ulang halaman = hilang. --}}
<div class="alert alert-success" id="tautan-baru">
    <h5 class="mb-2">Tautan {{ $baru['rt'] }}{{ $baru['kelurahan'] ? ' — Kel. '.$baru['kelurahan'] : '' }} siap dikirim <small class="text-muted">(berlaku s.d. {{ $baru['kedaluwarsa'] }})</small></h5>
    <p class="mb-2"><b>Salin sekarang.</b> Tautan ini tidak ditampilkan lagi setelah halaman ini ditutup — kalau hilang, buat tautan baru.</p>
    <div class="input-group mb-2">
        <input type="text" class="form-control" id="url-baru" value="{{ $baru['url'] }}" readonly onclick="this.select()">
        <div class="input-group-append">
            <button class="btn btn-primary" type="button" onclick="salin('url-baru', this)">Salin tautan</button>
        </div>
    </div>
    <details>
        <summary class="text-muted" style="cursor:pointer">Teks pesan WhatsApp siap kirim</summary>
        <textarea class="form-control mt-2" id="pesan-baru" rows="5" readonly onclick="this.select()">Assalamualaikum Bapak/Ibu Ketua {{ $baru['rt'] }}{{ $baru['kelurahan'] ? ' Kel. '.$baru['kelurahan'] : '' }},

Mohon bantuannya memverifikasi data anak balita warga RT melalui tautan SIRINDU berikut (tanpa perlu akun, buka dari HP):
{{ $baru['url'] }}

Tandai tiap anak: masih berdomisili / pindah / meninggal / tidak dikenal. Tautan berlaku s.d. {{ $baru['kedaluwarsa'] }}. Terima kasih.</textarea>
        <button class="btn btn-outline-secondary btn-sm mt-2" type="button" onclick="salin('pesan-baru', this)">Salin pesan</button>
    </details>
</div>
@endif

@if($kelList->isNotEmpty())
<div class="card-box mb-3">
    <form method="GET" class="form-inline rt-filter-form">
        <label class="rt-filter-field"><span>Kelurahan</span>
        <select name="kel" class="form-control mr-2">
            <option value="">Semua kelurahan</option>
            @foreach($kelList as $k)
            <option value="{{ $k->id }}" {{ (int) $kel === (int) $k->id ? 'selected' : '' }}>{{ $k->name }}</option>
            @endforeach
        </select>
        </label>
        <button class="btn btn-primary">Terapkan</button>
        <a href="{{ route('admin.aksesTautan.index') }}" class="btn btn-link">Reset</a>
        <span class="rt-filter-summary text-muted">{{ $rtList->count() }} RT · {{ $rtList->filter(fn ($r) => $r->tautanAktif)->count() }} tautan aktif</span>
    </form>
</div>
@endif

<div class="card-box">
    <div class="table-responsive">
        <table class="table table-striped table-sm">
            <thead>
                <tr><th>RT</th><th>Kelurahan</th><th>Status tautan</th><th>Dipakai</th><th style="width:300px">Aksi</th></tr>
            </thead>
            <tbody>
            @forelse($rtList as $rt)
                @php($t = $rt->tautanAktif)
                <tr>
                    <td><b>{{ $rt->name }}</b></td>
                    <td>{{ $rt->kelurahan?->name ?? '-' }}</td>
                    <td>
                        @if($t)
                            <span class="badge badge-success">Aktif s.d. {{ $t->kedaluwarsa_at->format('d/m/Y') }}</span>
                            <br><small class="text-muted">dibuat {{ $t->created_at->format('d/m/Y') }} oleh {{ $t->pembuat?->name ?? '-' }}</small>
                        @else
                            <span class="text-muted">Belum ada tautan</span>
                        @endif
                    </td>
                    <td>
                        @if($t)
                            {{ $t->jumlah_pakai }}× @if($t->terakhir_dipakai_at)<br><small class="text-muted">terakhir {{ $t->terakhir_dipakai_at->format('d/m/Y H:i') }}</small>@endif
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        <form method="POST" action="{{ route('admin.aksesTautan.buat', $rt) }}" class="form-inline d-inline-flex mr-1 mb-1" @if($t) onsubmit="return confirm('Tautan lama {{ $rt->name }} akan dimatikan dan diganti yang baru. Lanjutkan?')" @endif>
                            @csrf
                            <select name="hari" class="form-control form-control-sm mr-1" aria-label="Masa berlaku">
                                @foreach($hariOpsi as $h)
                                <option value="{{ $h }}" {{ $h === \App\Models\RtAksesTautan::HARI_DEFAULT ? 'selected' : '' }}>{{ $h }} hari</option>
                                @endforeach
                            </select>
                            <button class="btn btn-sm {{ $t ? 'btn-outline-primary' : 'btn-primary' }}">{{ $t ? 'Buat ulang' : 'Buat tautan' }}</button>
                        </form>
                        @if($t)
                        <form method="POST" action="{{ route('admin.aksesTautan.cabut', $t) }}" class="d-inline mb-1" onsubmit="return confirm('Cabut tautan {{ $rt->name }}? Ketua RT tidak bisa membukanya lagi.')">
                            @csrf
                            <button class="btn btn-sm btn-outline-danger">Cabut</button>
                        </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">Tidak ada RT di cakupan ini.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
function salin(id, btn){
    var el = document.getElementById(id); el.select();
    var selesai = function(){ var lama = btn.textContent; btn.textContent = 'Tersalin ✓'; setTimeout(function(){ btn.textContent = lama; }, 1500); };
    if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(el.value).then(selesai, function(){ document.execCommand('copy'); selesai(); }); }
    else { document.execCommand('copy'); selesai(); }
}
</script>
</div>
@endsection

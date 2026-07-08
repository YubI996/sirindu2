@extends('admin::layouts.app')
@section('title') Intervensi Gizi — SIRINDU @endsection
@section('title-content') Intervensi Gizi @endsection
@section('item') Gizi & Timbang @endsection
@section('item-active') Intervensi @endsection

@section('content')
<style>
    .ig-stat { background:#fff; border-radius:12px; padding:1.1rem 1.25rem; box-shadow:0 4px 15px rgba(0,0,0,.06); border-left:4px solid #e5e7eb; }
    .ig-stat.total { border-left-color:#0891b2; }
    .ig-stat.sudah { border-left-color:#047857; }
    .ig-stat.persen { border-left-color:oklch(0.48 0.14 145); }
    .ig-stat h2 { font-size:1.9rem; font-weight:800; margin:0; color:#1f2937; }
    .ig-stat p { margin:0; color:#4b5563; font-size:.85rem; }
    .ig-badge-p { font-weight:700; padding:.2rem .5rem; border-radius:6px; font-size:.75rem; color:#fff; }
    .ig-badge-p.p1 { background:#be123c; }
    .ig-badge-p.p2 { background:#b45309; }
    .ig-badge-p.p3 { background:#0891b2; }
    .ig-iv { font-size:.8rem; padding:.25rem 0; border-bottom:1px dashed #eee; }
    .ig-st { font-size:.7rem; padding:.05rem .4rem; border-radius:10px; }
    .ig-st.Direncanakan { background:#e5e7eb; color:#374151; }
    .ig-st.Berjalan { background:#fef3c7; color:#b45309; }
    .ig-st.Selesai { background:#d1fae5; color:#047857; }
    .ig-modal-back { display:none; position:fixed; inset:0; z-index:1080; background:rgba(0,0,0,.45); padding:5vh 16px; overflow:auto; }
    .ig-modal-back.open { display:block; }
    .ig-modal { background:#fff; border-radius:12px; max-width:560px; margin:0 auto; padding:1.5rem; box-shadow:0 20px 60px rgba(0,0,0,.3); }
</style>

@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif

{{-- Rekap cakupan --}}
<div class="row mb-4">
    <div class="col-md-4 mb-3"><div class="ig-stat total">
        <h2>{{ number_format($rekap['total_prioritas']) }}</h2>
        <p>Anak Prioritas (P1–P3)</p>
    </div></div>
    <div class="col-md-4 mb-3"><div class="ig-stat sudah">
        <h2>{{ number_format($rekap['sudah']) }}</h2>
        <p>Sudah Diintervensi</p>
    </div></div>
    <div class="col-md-4 mb-3"><div class="ig-stat persen">
        <h2>{{ $rekap['persen'] }}%</h2>
        <p>Cakupan Intervensi ({{ number_format($rekap['total_prioritas'] - $rekap['sudah']) }} belum)</p>
    </div></div>
</div>

{{-- Filter wilayah (super-admin) --}}
@if($isSuper)
<form method="GET" class="card card-body mb-3 flex-row flex-wrap align-items-end" style="gap:.75rem;">
    <div>
        <label class="small mb-1 d-block">Kecamatan</label>
        <select name="kecamatan" class="form-control form-control-sm">
            <option value="">Semua</option>
            @foreach($kecamatanList as $kc)
            <option value="{{ $kc->id }}" {{ ($filter['kec'] ?? null) == $kc->id ? 'selected' : '' }}>{{ $kc->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="small mb-1 d-block">Kelurahan</label>
        <select name="kelurahan" class="form-control form-control-sm">
            <option value="">Semua</option>
            @foreach($kelurahanList as $kl)
            <option value="{{ $kl->id }}" {{ ($filter['kel'] ?? null) == $kl->id ? 'selected' : '' }}>{{ $kl->name }}</option>
            @endforeach
        </select>
    </div>
    <button class="btn btn-sm btn-primary"><i class="fa fa-filter mr-1"></i>Terapkan</button>
    <a href="{{ route('admin.intervensi.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
</form>
@endif

{{-- Daftar anak prioritas --}}
<div class="card"><div class="card-body table-responsive">
    <table class="table table-sm table-hover">
        <thead class="thead-light">
            <tr>
                <th>Prioritas</th><th>Nama</th><th>NIK</th><th>Kelurahan</th><th>RT</th><th>Posyandu</th>
                <th>Intervensi</th><th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($daftar as $d)
            <tr>
                <td><span class="ig-badge-p p{{ $d['prioritas'] }}">P{{ $d['prioritas'] }}</span></td>
                <td><strong>{{ $d['nama'] }}</strong></td>
                <td>{{ $d['nik'] }}</td>
                <td>{{ $d['kelurahan'] }}</td>
                <td>{{ $d['rt'] }}</td>
                <td>{{ $d['posyandu'] }}</td>
                <td style="min-width:240px;">
                    @forelse($d['intervensi'] as $iv)
                    <div class="ig-iv d-flex align-items-center justify-content-between">
                        <span>
                            <strong>{{ $iv['jenis'] }}</strong>
                            <span class="ig-st {{ $iv['status'] }}">{{ $iv['status'] }}</span>
                            @if($iv['tanggal']) <span class="text-muted">· {{ \Carbon\Carbon::parse($iv['tanggal'])->format('d M Y') }}</span> @endif
                            @if($iv['pelaksana']) <span class="text-muted">· {{ $iv['pelaksana'] }}</span> @endif
                            @if($iv['catatan']) <br><small class="text-muted">{{ $iv['catatan'] }}</small> @endif
                        </span>
                        <span class="text-nowrap ml-2">
                            <button type="button" class="btn btn-xs btn-link p-0 mr-1"
                                onclick="igEdit(this)"
                                data-id="{{ $iv['id'] }}" data-jenis="{{ $iv['jenis'] }}"
                                data-status="{{ $iv['status'] }}" data-tanggal="{{ $iv['tanggal'] }}"
                                data-pelaksana="{{ $iv['pelaksana'] }}" data-catatan="{{ $iv['catatan'] }}">
                                <i class="fa fa-pen"></i>
                            </button>
                            <form method="POST" action="{{ route('admin.intervensi.destroy', $iv['id']) }}" class="d-inline"
                                onsubmit="return confirm('Hapus intervensi ini?');">
                                @csrf @method('DELETE')
                                <button class="btn btn-xs btn-link text-danger p-0"><i class="fa fa-trash"></i></button>
                            </form>
                        </span>
                    </div>
                    @empty
                    <span class="text-muted small">Belum ada intervensi</span>
                    @endforelse
                </td>
                <td class="text-nowrap">
                    <button type="button" class="btn btn-sm btn-primary" onclick="igTambah({{ $d['id_anak'] }}, @js($d['nama']))">
                        <i class="fa fa-plus mr-1"></i>Intervensi
                    </button>
                </td>
            </tr>
            @empty
            <tr><td colspan="8" class="text-center text-muted py-4">
                Tidak ada anak prioritas. Pastikan snapshot terisi (<code>php artisan prioritas:refresh</code>).
            </td></tr>
            @endforelse
        </tbody>
    </table>
</div></div>

{{-- Modal tambah/edit --}}
<div class="ig-modal-back" id="ig-modal-back">
    <div class="ig-modal">
        <h5 id="ig-modal-title" class="mb-3">Tambah Intervensi</h5>
        <form id="ig-form" method="POST" action="{{ route('admin.intervensi.store') }}">
            @csrf
            <input type="hidden" name="_method" id="ig-method" value="POST">
            <input type="hidden" name="id_anak" id="ig-id-anak">
            <p class="mb-3 text-muted">Anak: <strong id="ig-anak-nama"></strong></p>
            <div class="form-group">
                <label>Jenis</label>
                <select name="jenis" id="ig-jenis" class="form-control" required>
                    @foreach($jenisList as $j)<option value="{{ $j }}">{{ $j }}</option>@endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" id="ig-status" class="form-control" required>
                    @foreach($statusList as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Tanggal</label>
                <input type="date" name="tanggal" id="ig-tanggal" class="form-control">
            </div>
            <div class="form-group">
                <label>Pelaksana / Dinas</label>
                <input type="text" name="pelaksana" id="ig-pelaksana" class="form-control" maxlength="255">
            </div>
            <div class="form-group">
                <label>Catatan</label>
                <textarea name="catatan" id="ig-catatan" class="form-control" rows="2" maxlength="2000"></textarea>
            </div>
            <div class="text-right">
                <button type="button" class="btn btn-secondary" onclick="igTutup()">Batal</button>
                <button class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
@parent
<script>
(function () {
    'use strict';
    var STORE_URL = '{{ route("admin.intervensi.index") }}'.replace(/\/intervensi-gizi.*$/, '/intervensi-gizi');
    var back = document.getElementById('ig-modal-back');
    var form = document.getElementById('ig-form');

    function reset() {
        form.reset();
        document.getElementById('ig-method').value = 'POST';
    }
    window.igTambah = function (idAnak, nama) {
        reset();
        document.getElementById('ig-modal-title').textContent = 'Tambah Intervensi';
        form.action = STORE_URL;
        document.getElementById('ig-id-anak').value = idAnak;
        document.getElementById('ig-anak-nama').textContent = nama;
        back.classList.add('open');
    };
    window.igEdit = function (btn) {
        reset();
        document.getElementById('ig-modal-title').textContent = 'Edit Intervensi';
        form.action = STORE_URL + '/' + btn.dataset.id;
        document.getElementById('ig-method').value = 'PUT';
        document.getElementById('ig-anak-nama').textContent = '(intervensi terpilih)';
        document.getElementById('ig-jenis').value = btn.dataset.jenis;
        document.getElementById('ig-status').value = btn.dataset.status;
        document.getElementById('ig-tanggal').value = btn.dataset.tanggal || '';
        document.getElementById('ig-pelaksana').value = btn.dataset.pelaksana || '';
        document.getElementById('ig-catatan').value = btn.dataset.catatan || '';
        back.classList.add('open');
    };
    window.igTutup = function () { back.classList.remove('open'); };
    back.addEventListener('click', function (e) { if (e.target === back) igTutup(); });
})();
</script>
@endsection

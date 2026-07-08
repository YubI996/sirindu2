@extends('admin::layouts.app')
@section('title') Intervensi Gizi — SIRINDU @endsection
@section('title-content') Intervensi Gizi @endsection
@section('item') Gizi & Timbang @endsection
@section('item-active') Intervensi @endsection

@section('content')
<div class="ig-page">
<style>
@import url('https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@600;700&display=swap');

.ig-page{
    --green:oklch(0.60 0.15 145); --green-d:oklch(0.48 0.14 145); --green-dk:oklch(0.38 0.13 145);
    --ink:oklch(0.24 0.02 145); --muted:oklch(0.50 0.015 145); --faint:oklch(0.62 0.012 145);
    --line:oklch(0.90 0.012 145); --bg:oklch(0.98 0.012 145); --card:#fff;
    font-family:'Barlow',system-ui,sans-serif; color:var(--ink);
}
.ig-page *{ box-sizing:border-box; }
.ig-num{ font-family:'Barlow Condensed','Barlow',sans-serif; font-variant-numeric:tabular-nums; }

/* Flash */
.ig-flash{
    display:flex; align-items:center; gap:.6rem; background:oklch(0.95 0.06 145);
    color:var(--green-dk); border:1px solid oklch(0.85 0.07 145); border-radius:10px;
    padding:.7rem 1rem; margin-bottom:1.25rem; font-weight:600; font-size:.9rem;
}

/* Coverage panel */
.ig-cover{
    background:var(--card); border:1px solid var(--line); border-radius:14px;
    padding:1.4rem 1.6rem; margin-bottom:1.5rem; box-shadow:0 1px 3px oklch(0 0 0 / .04);
    display:grid; grid-template-columns:auto 1fr; gap:1.6rem; align-items:center;
}
.ig-cover__pct{ font-family:'Barlow Condensed','Barlow',sans-serif; font-weight:700; font-size:3.4rem; line-height:.9; color:var(--green-dk); }
.ig-cover__pct span{ font-size:1.4rem; color:var(--faint); margin-left:.1rem; }
.ig-cover__body{ min-width:0; }
.ig-cover__label{ font-size:.7rem; font-weight:700; letter-spacing:.09em; text-transform:uppercase; color:var(--muted); margin-bottom:.5rem; }
.ig-cover__bar{ height:10px; border-radius:6px; background:oklch(0.93 0.02 145); overflow:hidden; }
.ig-cover__fill{ height:100%; border-radius:6px; background:var(--green); transition:width .5s cubic-bezier(.22,1,.36,1); }
.ig-cover__sub{ margin-top:.6rem; font-size:.9rem; color:var(--muted); }
.ig-cover__sub b{ color:var(--ink); font-weight:700; }
@media(max-width:560px){ .ig-cover{ grid-template-columns:1fr; gap:.9rem; } .ig-cover__pct{ font-size:2.8rem; } }

/* Filter */
.ig-filter{ display:flex; flex-wrap:wrap; align-items:flex-end; gap:.75rem; margin-bottom:1.25rem; }
.ig-filter label{ display:block; font-size:.68rem; font-weight:700; letter-spacing:.05em; text-transform:uppercase; color:var(--muted); margin-bottom:.25rem; }
.ig-filter select{ height:38px; padding:0 .7rem; border:1px solid oklch(0.84 0.012 145); border-radius:8px; background:var(--bg); font-family:inherit; font-size:.85rem; min-width:170px; }
.ig-btn{ display:inline-flex; align-items:center; gap:.4rem; height:38px; padding:0 1rem; border-radius:8px; font-family:inherit; font-weight:600; font-size:.85rem; border:1px solid transparent; cursor:pointer; text-decoration:none; transition:background .14s,border-color .14s; }
.ig-btn--primary{ background:var(--green-d); color:#fff; }
.ig-btn--primary:hover{ background:var(--green-dk); color:#fff; }
.ig-btn--ghost{ background:transparent; border-color:var(--line); color:var(--muted); }
.ig-btn--ghost:hover{ border-color:var(--faint); color:var(--ink); }
.ig-btn--sm{ height:30px; padding:0 .6rem; font-size:.78rem; }

/* Table */
.ig-tablewrap{ background:var(--card); border:1px solid var(--line); border-radius:14px; overflow:hidden; box-shadow:0 1px 3px oklch(0 0 0 / .04); }
.ig-scroll{ overflow-x:auto; }
.ig-table{ width:100%; border-collapse:collapse; font-size:.86rem; }
.ig-table thead th{ background:oklch(0.96 0.015 145); text-align:left; padding:.7rem .9rem; font-size:.66rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:var(--muted); white-space:nowrap; }
.ig-table tbody td{ padding:.75rem .9rem; border-top:1px solid var(--line); vertical-align:top; }
.ig-table tbody tr:hover{ background:oklch(0.98 0.012 145); }
.ig-name{ font-weight:700; color:var(--ink); }
.ig-nik{ font-size:.75rem; color:var(--faint); font-variant-numeric:tabular-nums; }
.ig-geo{ font-size:.8rem; color:var(--muted); white-space:nowrap; }

/* Priority pill (tinted, no stripe) */
.ig-p{ font-family:'Barlow Condensed','Barlow',sans-serif; font-weight:700; font-size:.82rem; letter-spacing:.02em; padding:.12rem .5rem; border-radius:6px; white-space:nowrap; }
.ig-p--1{ background:oklch(0.94 0.055 25); color:oklch(0.46 0.17 25); }
.ig-p--2{ background:oklch(0.94 0.06 70); color:oklch(0.44 0.13 70); }
.ig-p--3{ background:oklch(0.93 0.05 235); color:oklch(0.45 0.12 235); }

/* Intervention entries */
.ig-ivlist{ display:flex; flex-direction:column; gap:.4rem; min-width:230px; }
.ig-iv{ display:flex; align-items:flex-start; justify-content:space-between; gap:.5rem; }
.ig-iv__t{ font-size:.82rem; line-height:1.35; }
.ig-iv__t b{ font-weight:700; }
.ig-iv__meta{ color:var(--faint); }
.ig-iv__note{ display:block; color:var(--muted); font-size:.76rem; margin-top:.1rem; }
.ig-iv__act{ display:flex; gap:.15rem; flex-shrink:0; }
.ig-ico{ background:none; border:none; cursor:pointer; padding:.2rem .3rem; border-radius:6px; color:var(--faint); line-height:1; }
.ig-ico:hover{ background:oklch(0.94 0.012 145); color:var(--ink); }
.ig-ico--del:hover{ color:oklch(0.5 0.18 25); background:oklch(0.95 0.05 25); }
.ig-empty-iv{ font-size:.8rem; color:var(--faint); font-style:italic; }

/* Status pill */
.ig-st{ font-size:.68rem; font-weight:700; padding:.08rem .45rem; border-radius:20px; white-space:nowrap; }
.ig-st.Direncanakan{ background:oklch(0.93 0.008 145); color:oklch(0.42 0.01 145); }
.ig-st.Berjalan{ background:oklch(0.94 0.06 70); color:oklch(0.44 0.13 70); }
.ig-st.Selesai{ background:oklch(0.94 0.06 145); color:var(--green-dk); }

/* Empty */
.ig-empty{ text-align:center; padding:3rem 1rem; color:var(--muted); }
.ig-empty .ig-num{ font-size:1.1rem; font-weight:700; color:var(--ink); display:block; margin-bottom:.35rem; }
.ig-empty code{ background:oklch(0.94 0.012 145); padding:.1rem .35rem; border-radius:5px; font-size:.85em; }

/* Modal */
.ig-modal-back{ display:none; position:fixed; inset:0; z-index:1080; background:oklch(0.2 0.02 145 / .5); padding:5vh 16px; overflow:auto; }
.ig-modal-back.open{ display:block; }
.ig-modal{ background:var(--card); border-radius:14px; max-width:520px; margin:0 auto; padding:1.6rem 1.7rem; box-shadow:0 24px 60px oklch(0 0 0 / .28); }
.ig-modal h5{ font-family:'Barlow Condensed','Barlow',sans-serif; font-weight:700; font-size:1.3rem; margin:0 0 .2rem; color:var(--ink); }
.ig-modal__who{ color:var(--muted); font-size:.85rem; margin-bottom:1.1rem; }
.ig-field{ margin-bottom:.9rem; }
.ig-field label{ display:block; font-size:.72rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; color:var(--muted); margin-bottom:.3rem; }
.ig-field select,.ig-field input,.ig-field textarea{ width:100%; padding:.5rem .65rem; border:1px solid oklch(0.84 0.012 145); border-radius:8px; font-family:inherit; font-size:.9rem; background:#fff; color:var(--ink); }
.ig-field select:focus,.ig-field input:focus,.ig-field textarea:focus{ outline:2px solid oklch(0.60 0.15 145 / .4); outline-offset:1px; border-color:var(--green); }
.ig-grid2{ display:grid; grid-template-columns:1fr 1fr; gap:.9rem; }
.ig-modal__foot{ display:flex; justify-content:flex-end; gap:.6rem; margin-top:1.2rem; }
</style>

@if(session('success'))
<div class="ig-flash"><span class="material-symbols-outlined" style="font-size:20px;">check_circle</span>{{ session('success') }}</div>
@endif

{{-- Cakupan intervensi --}}
<section class="ig-cover">
    <div class="ig-cover__pct ig-num">{{ $rekap['persen'] }}<span>%</span></div>
    <div class="ig-cover__body">
        <div class="ig-cover__label">Cakupan Intervensi Anak Prioritas</div>
        <div class="ig-cover__bar"><div class="ig-cover__fill" style="width:{{ min(100, $rekap['persen']) }}%"></div></div>
        <div class="ig-cover__sub">
            <b class="ig-num">{{ number_format($rekap['sudah']) }}</b> dari
            <b class="ig-num">{{ number_format($rekap['total_prioritas']) }}</b> anak prioritas (P1–P3) sudah ditangani ·
            <b class="ig-num">{{ number_format($rekap['total_prioritas'] - $rekap['sudah']) }}</b> belum
        </div>
    </div>
</section>

{{-- Filter wilayah (super-admin) --}}
@if($isSuper)
<form method="GET" class="ig-filter">
    <div>
        <label for="f-kec">Kecamatan</label>
        <select name="kecamatan" id="f-kec">
            <option value="">Semua kecamatan</option>
            @foreach($kecamatanList as $kc)
            <option value="{{ $kc->id }}" {{ ($filter['kec'] ?? null) == $kc->id ? 'selected' : '' }}>{{ $kc->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="f-kel">Kelurahan</label>
        <select name="kelurahan" id="f-kel">
            <option value="">Semua kelurahan</option>
            @foreach($kelurahanList as $kl)
            <option value="{{ $kl->id }}" {{ ($filter['kel'] ?? null) == $kl->id ? 'selected' : '' }}>{{ $kl->name }}</option>
            @endforeach
        </select>
    </div>
    <button class="ig-btn ig-btn--primary"><span class="material-symbols-outlined" style="font-size:18px;">filter_alt</span>Terapkan</button>
    <a href="{{ route('admin.intervensi.index') }}" class="ig-btn ig-btn--ghost">Reset</a>
</form>
@endif

{{-- Daftar anak prioritas --}}
<div class="ig-tablewrap"><div class="ig-scroll">
    <table class="ig-table">
        <thead>
            <tr>
                <th>Prioritas</th><th>Anak</th><th>Kelurahan</th><th>RT</th><th>Posyandu</th>
                <th>Intervensi</th><th></th>
            </tr>
        </thead>
        <tbody>
            @php $plabel = [1 => 'P1 · Gizi Buruk', 2 => 'P2 · Stunting', 3 => 'P3 · BB Tidak Naik']; @endphp
            @forelse($daftar as $d)
            <tr>
                <td><span class="ig-p ig-p--{{ $d['prioritas'] }}">{{ $plabel[$d['prioritas']] ?? ('P' . $d['prioritas']) }}</span></td>
                <td><div class="ig-name">{{ $d['nama'] }}</div><div class="ig-nik">{{ $d['nik'] }}</div></td>
                <td class="ig-geo">{{ $d['kelurahan'] }}</td>
                <td class="ig-geo">{{ $d['rt'] }}</td>
                <td class="ig-geo">{{ $d['posyandu'] }}</td>
                <td>
                    @if(count($d['intervensi']))
                    <div class="ig-ivlist">
                        @foreach($d['intervensi'] as $iv)
                        <div class="ig-iv">
                            <div class="ig-iv__t">
                                <b>{{ $iv['jenis'] }}</b> <span class="ig-st {{ $iv['status'] }}">{{ $iv['status'] }}</span>
                                <span class="ig-iv__meta">
                                    @if($iv['tanggal']) · {{ \Carbon\Carbon::parse($iv['tanggal'])->format('d M Y') }} @endif
                                    @if($iv['pelaksana']) · {{ $iv['pelaksana'] }} @endif
                                </span>
                                @if($iv['catatan'])<span class="ig-iv__note">{{ $iv['catatan'] }}</span>@endif
                            </div>
                            <div class="ig-iv__act">
                                <button type="button" class="ig-ico" title="Edit" onclick="igEdit(this)"
                                    data-id="{{ $iv['id'] }}" data-jenis="{{ $iv['jenis'] }}" data-status="{{ $iv['status'] }}"
                                    data-tanggal="{{ $iv['tanggal'] }}" data-pelaksana="{{ $iv['pelaksana'] }}" data-catatan="{{ $iv['catatan'] }}">
                                    <span class="material-symbols-outlined" style="font-size:17px;">edit</span>
                                </button>
                                <form method="POST" action="{{ route('admin.intervensi.destroy', $iv['id']) }}" class="d-inline"
                                    onsubmit="return confirm('Hapus intervensi ini?');">
                                    @csrf @method('DELETE')
                                    <button class="ig-ico ig-ico--del" title="Hapus"><span class="material-symbols-outlined" style="font-size:17px;">delete</span></button>
                                </form>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <span class="ig-empty-iv">Belum ada intervensi</span>
                    @endif
                </td>
                <td>
                    <button type="button" class="ig-btn ig-btn--primary ig-btn--sm" onclick="igTambah({{ $d['id_anak'] }}, @js($d['nama']))">
                        <span class="material-symbols-outlined" style="font-size:17px;">add</span>Intervensi
                    </button>
                </td>
            </tr>
            @empty
            <tr><td colspan="7"><div class="ig-empty">
                <span class="ig-num">Belum ada anak prioritas</span>
                Daftar mengambil anak P1–P3 dari snapshot gizi. Jalankan <code>php artisan prioritas:refresh</code> untuk mengisinya,
                atau sesuaikan filter wilayah di atas.
            </div></td></tr>
            @endforelse
        </tbody>
    </table>
</div></div>

{{-- Modal tambah/edit --}}
<div class="ig-modal-back" id="ig-modal-back">
    <div class="ig-modal">
        <h5 id="ig-modal-title">Tambah Intervensi</h5>
        <p class="ig-modal__who">Anak: <strong id="ig-anak-nama" style="color:var(--ink);"></strong></p>
        <form id="ig-form" method="POST" action="{{ route('admin.intervensi.store') }}">
            @csrf
            <input type="hidden" name="_method" id="ig-method" value="POST">
            <input type="hidden" name="id_anak" id="ig-id-anak">
            <div class="ig-grid2">
                <div class="ig-field">
                    <label for="ig-jenis">Jenis</label>
                    <select name="jenis" id="ig-jenis" required>
                        @foreach($jenisList as $j)<option value="{{ $j }}">{{ $j }}</option>@endforeach
                    </select>
                </div>
                <div class="ig-field">
                    <label for="ig-status">Status</label>
                    <select name="status" id="ig-status" required>
                        @foreach($statusList as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach
                    </select>
                </div>
                <div class="ig-field">
                    <label for="ig-tanggal">Tanggal</label>
                    <input type="date" name="tanggal" id="ig-tanggal">
                </div>
                <div class="ig-field">
                    <label for="ig-pelaksana">Pelaksana / Dinas</label>
                    <input type="text" name="pelaksana" id="ig-pelaksana" maxlength="255" placeholder="mis. Dinkes, PKK">
                </div>
            </div>
            <div class="ig-field">
                <label for="ig-catatan">Catatan</label>
                <textarea name="catatan" id="ig-catatan" rows="2" maxlength="2000" placeholder="Detail singkat (opsional)"></textarea>
            </div>
            <div class="ig-modal__foot">
                <button type="button" class="ig-btn ig-btn--ghost" onclick="igTutup()">Batal</button>
                <button class="ig-btn ig-btn--primary">Simpan</button>
            </div>
        </form>
    </div>
</div>
</div>{{-- /ig-page --}}
@endsection

@section('scripts')
@parent
<script>
(function () {
    'use strict';
    var STORE_URL = '{{ route("admin.intervensi.store") }}';
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
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') igTutup(); });
})();
</script>
@endsection

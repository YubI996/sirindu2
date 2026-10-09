@extends('admin::layouts.app')
@section('title') Dashboard Imunisasi @endsection
@section('title-content') Dashboard Imunisasi @endsection
@section('item') Imunisasi @endsection
@section('item-active') Dashboard IDL @endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/dasbor-base.css') }}">
@endpush

@section('content')
<div class="im-page">
<style>

/* Tab hari ini / besok (role=tablist). Tab aktif = garis bawah tebal + latar putih, bukan hanya beda tint. */
.im-daytabs{ display:flex; flex-wrap:wrap; gap:.4rem; padding:.9rem 1.4rem 0; background:oklch(0.97 0.012 145); border-bottom:1px solid var(--line); }
.im-daytab{ padding:.6rem 1rem; border:1px solid transparent; border-bottom:0; border-radius:8px 8px 0 0; background:transparent; font-family:inherit; font-weight:600; font-size:.88rem; color:var(--muted); cursor:pointer; margin-bottom:-1px; box-shadow:inset 0 -3px 0 transparent; }
.im-daytab:hover{ color:var(--ink); }
.im-daytab[aria-selected="true"]{ background:#fff; color:var(--ink); border-color:var(--line); box-shadow:inset 0 -3px 0 var(--green-d); }

/* Ritme antar bagian: jarak besar sebelum judul, rapat ke isinya */
.im-page > .im-h{ margin-top:2.4rem; margin-bottom:1rem; }
.im-page > .im-filter + .im-tabs + .im-h{ margin-top:.4rem; }

/* Strip angka sasaran: satu wadah berpembatas tipis, bukan lima kartu identik */
.im-strip{ display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:1px; background:var(--line); border:1px solid var(--line); border-radius:14px; overflow:hidden; margin-bottom:.4rem; }
@media(max-width:900px){ .im-strip{ grid-template-columns:repeat(2,minmax(0,1fr)); } .im-strip__cell--na{ grid-column:1 / -1; } }
.im-strip__cell{ background:var(--card); padding:1.1rem 1.4rem; display:flex; flex-direction:column; gap:.35rem; }
.im-strip__lbl{ font-size:.78rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--muted); }
.im-strip__val{ font-family:'Barlow Condensed','Barlow',sans-serif; font-weight:700; font-size:2.3rem; line-height:1; }
.im-strip__sub{ font-size:.85rem; color:var(--muted); }
.im-strip__cell--na .im-strip__val{ font-size:1.1rem; color:var(--muted); font-weight:600; }
.im-strip__na{ display:flex; justify-content:space-between; gap:.6rem; font-size:.9rem; font-weight:600; padding:.15rem 0; }
.im-strip__na span:last-child{ color:var(--muted); font-weight:400; }

/* Wadah tabel yang bisa digulir: dibuat fokusable (tabindex=0) agar terjangkau keyboard */
.im-scroll{ overflow-x:auto; }

/* Antigen due-date chips */
.im-chip{ display:inline-block; padding:.15rem .5rem; border-radius:5px; font-size:.78rem; font-weight:600; white-space:nowrap; }
.im-chip--ok{ background:oklch(0.94 0.06 145); color:var(--green-dk); }
.im-chip--belum{ background:oklch(0.95 0.012 145); color:var(--muted); }

/* Progress bar (dekoratif: angka selalu ada di teks sebelahnya, bar di-aria-hidden).
   ok/mid/low = status performa — JANGAN dipakai untuk bar yang bukan status (mis. porsi populasi); pakai .neutral.
   Warna dari token --bar-* (>=3:1 thd track). Status JUGA ditulis lewat glyph+teks (.im-st), bukan warna saja. */
.im-bar{ position:relative; height:8px; border-radius:8px; background:oklch(0.93 0.02 145); overflow:hidden; }
.im-bar__fill{ height:100%; border-radius:8px; }
.im-bar__fill.ok{ background:var(--bar-ok); }
.im-bar__fill.mid{ background:var(--bar-mid); }
.im-bar__fill.low{ background:var(--bar-low); }
.im-bar__fill.neutral{ background:var(--bar-neutral); }
.im-st{ font-weight:700; }
.im-st.ok{ color:var(--green-dk); } .im-st.mid{ color:var(--amber); } .im-st.low{ color:var(--red-d); }

/* Generic panel */
.im-panel{ background:var(--card); border:1px solid var(--line); border-radius:16px; padding:1.4rem 1.6rem; margin-bottom:1.5rem; box-shadow:0 1px 3px oklch(0 0 0 / .04); }
.im-panel--flush{ padding:0; overflow:hidden; }

/* Two-up grid */
.im-grid2{ display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:1.5rem; margin-bottom:1.5rem; align-items:start; }
.im-grid2 > *{ min-width:0; } /* tanpa ini tabel/kanvas di dalam panel melebarkan kolom & halaman di HP */
@media(max-width:980px){ .im-grid2{ grid-template-columns:minmax(0,1fr); } }

/* Kohort table (details/summary drilldown) */
.im-kec__share{ display:inline-flex; align-items:center; gap:.5rem; min-width:150px; }
.im-kec__share .im-bar{ flex:1; min-width:70px; }
.im-kec{ border-top:1px solid var(--line); }
.im-kec:first-of-type{ border-top:none; }
.im-kec > summary{ list-style:none; cursor:pointer; display:flex; flex-wrap:wrap; align-items:center; gap:.4rem .9rem; padding:.85rem 1.4rem; font-weight:700; font-size:.95rem; }
.im-kec > summary::-webkit-details-marker{ display:none; }
.im-kec > summary::before{ content:'▸'; content:'▸' / ''; color:var(--green-d); font-size:.8rem; width:10px; }
.im-kec[open] > summary::before{ content:'▾'; content:'▾' / ''; }
.im-kec > summary .im-kec__meta{ color:var(--muted); font-weight:400; font-size:.85rem; }
.im-kec > summary .im-kec__stats{ margin-left:auto; display:flex; flex-wrap:wrap; align-items:center; gap:.2rem 1rem; font-size:.9rem; }
.im-kec table{ width:100%; min-width:600px; border-collapse:collapse; }
.im-kec thead th{ padding:.5rem 1.4rem; text-align:left; font-size:.75rem; font-weight:700; color:var(--muted); background:oklch(0.985 0.008 145); }
.im-kec thead th.r{ text-align:right; }
.im-kec td, .im-kec tbody th{ padding:.55rem 1.4rem; font-size:.88rem; border-top:1px solid oklch(0.95 0.012 145); }
.im-kec tbody th{ text-align:left; background:transparent; font-weight:400; padding-left:2.6rem; color:var(--muted); }
.im-kec td.r{ text-align:right; font-variant-numeric:tabular-nums; }
.im-kec td.bar{ width:150px; }
@media(max-width:600px){
    .im-kec > summary{ padding:.85rem 1rem; }
    .im-kec > summary .im-kec__stats{ margin-left:0; flex-basis:100%; }
}

/* Legend (dipakai cakupan per antigen) */
.im-legend{ display:flex; flex-wrap:wrap; gap:.6rem 1.2rem; font-size:.82rem; color:var(--muted); }
.im-legend span{ display:inline-flex; align-items:center; gap:.4rem; }
.im-legend i{ width:12px; height:8px; border-radius:2px; display:inline-block; }
.im-legend i.tgt{ width:2px; height:14px; border-radius:0; background:var(--ink); opacity:.5; }

/* Puskesmas rincian table */
.im-table{ width:100%; border-collapse:collapse; font-size:.9rem; }
.im-table thead th{ background:oklch(0.96 0.015 145); text-align:left; padding:.65rem .9rem; font-size:.75rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; color:var(--muted); white-space:nowrap; }
.im-table tbody td, .im-table tbody th{ padding:.7rem .9rem; border-top:1px solid var(--line); vertical-align:middle; }
.im-table tbody th{ text-align:left; font-weight:600; background:transparent; }
.im-table tbody tr:hover{ background:oklch(0.985 0.012 145); }
.im-table .r{ text-align:right; font-variant-numeric:tabular-nums; }

/* Funnel */
.im-funnel__row{ display:flex; align-items:center; gap:.4rem .7rem; margin-bottom:.55rem; }
.im-funnel__lbl{ width:120px; font-size:.85rem; font-weight:600; flex-shrink:0; }
.im-funnel__track{ flex:1; min-width:100px; height:18px; border-radius:4px; background:oklch(0.93 0.02 145); overflow:hidden; }
.im-funnel__fill{ height:100%; background:var(--green-d); }
.im-funnel__val{ width:110px; text-align:right; font-size:.85rem; font-weight:600; flex-shrink:0; }

/* Antigen coverage bars */
.im-antigen{ display:grid; grid-template-columns:1fr 1fr; gap:.8rem 2rem; }
@media(max-width:900px){ .im-antigen{ grid-template-columns:1fr; } }
.im-antigen__row{ display:flex; align-items:center; gap:.3rem .7rem; }
.im-antigen__lbl{ width:150px; font-size:.85rem; font-weight:600; flex-shrink:0; }
.im-antigen__grp{ display:block; font-size:.75rem; font-weight:400; color:var(--muted); text-transform:lowercase; }
.im-antigen__track{ position:relative; flex:1; min-width:100px; height:8px; border-radius:99px; background:oklch(0.93 0.02 145); overflow:hidden; }
.im-antigen__target{ position:absolute; top:0; bottom:0; left:95%; width:2px; background:var(--ink); opacity:.5; }
.im-antigen__val{ width:140px; text-align:right; font-size:.85rem; font-weight:600; flex-shrink:0; }
.im-antigen__val small{ color:var(--muted); font-weight:400; font-size:.8rem; }
/* HP: label di atas, bar + angka sebaris di bawahnya — tak ada lebar tetap yang meluap di 320px */
@media(max-width:600px){
    .im-antigen__row, .im-funnel__row{ flex-wrap:wrap; }
    .im-antigen__lbl, .im-funnel__lbl{ width:100%; }
    .im-antigen__val, .im-funnel__val{ width:auto; min-width:90px; }
    .im-panel{ padding:1.1rem 1rem; }
}

/* Data table (alasan, korelasi) */
.im-mini-table{ width:100%; border-collapse:collapse; font-size:.88rem; margin-top:1rem; }
.im-mini-table th{ text-align:left; padding:.45rem .6rem; font-size:.75rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; color:var(--muted); border-bottom:1px solid var(--line); }
.im-mini-table th.r,.im-mini-table td.r{ text-align:right; }
.im-mini-table td, .im-mini-table tbody th{ padding:.45rem .6rem; border-bottom:1px solid var(--line); text-align:left; font-weight:400; }
.im-mini-table td.r{ text-align:right; font-family:'Barlow Condensed','Barlow',sans-serif; font-weight:700; font-variant-numeric:tabular-nums; color:var(--ink); }
.im-mini-table tbody tr:last-child td, .im-mini-table tbody tr:last-child th{ border-bottom:none; }
.im-chartbox{ min-height:60px; }
.im-datatable{ margin-top:1rem; }
.im-datatable > summary{ cursor:pointer; font-weight:600; font-size:.88rem; color:var(--green-d); padding:.3rem 0; }

/* Empty */
.im-empty{ text-align:center; padding:2.4rem 1rem; color:var(--muted); font-size:.92rem; }
.im-empty strong{ display:block; font-family:'Barlow Condensed','Barlow',sans-serif; font-size:1.1rem; color:var(--ink); margin-bottom:.3rem; }
</style>

@php
    $totalAnak      = $coverage['total'];
    $idlLengkap     = $coverage['idl_lengkap'];
    $persenIdl      = $coverage['persen'];
    $bucketOf = function (float $p) { return $p >= 95 ? 'ok' : ($p >= 60 ? 'mid' : 'low'); };
    // Status performa harus terbaca TANPA warna: glyph + kata (WCAG 1.4.1). Glyph aria-hidden, kata untuk pembaca layar.
    $bucketTanda = ['ok' => ['✓', 'sesuai target'], 'mid' => ['▲', 'perlu perhatian'], 'low' => ['✕', 'kritis']];
@endphp

{{-- @include('admin.imunisasi.partials._kelompok-kosong') --}}

{{-- Filter wilayah --}}
<form method="GET" action="{{ route('admin.imunisasiDashboard') }}" class="im-filter" aria-label="Filter dasbor imunisasi">
    <div>
        <label for="filterTahun">Tahun sasaran</label>
        <select name="tahun" id="filterTahun">
            @foreach($pilihanTahun as $t)
                <option value="{{ $t }}" {{ $tahun == $t ? 'selected' : '' }}>{{ $t }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="filterKec">Kecamatan</label>
        <select name="id_kecamatan" id="filterKec">
            <option value="">Semua kecamatan</option>
            @foreach($kecamatanList as $kec)
                <option value="{{ $kec->id }}" {{ ($filters['id_kecamatan'] ?? null) == $kec->id ? 'selected' : '' }}>{{ $kec->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="filterKel">Kelurahan</label>
        <select name="id_kelurahan" id="filterKel">
            <option value="">Semua kelurahan</option>
            @foreach($kelurahanList as $kel)
                <option value="{{ $kel->id }}" data-kec="{{ $kel->id_kecamatan }}" {{ ($filters['id_kelurahan'] ?? null) == $kel->id ? 'selected' : '' }}>{{ $kel->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="filterRt">RT</label>
        <select name="id_rt" id="filterRt">
            <option value="">Semua RT</option>
        </select>
    </div>
    <div>
        <label for="filterPkm">Puskesmas</label>
        <select name="id_puskesmas" id="filterPkm">
            <option value="">Semua puskesmas</option>
            @foreach($puskesmasList as $pkm)
                <option value="{{ $pkm->id }}" {{ ($filters['id_puskesmas'] ?? null) == $pkm->id ? 'selected' : '' }}>{{ $pkm->name }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="im-btn im-btn--primary">
        <span class="material-symbols-outlined" style="font-size:18px;" aria-hidden="true">filter_alt</span>Terapkan
    </button>
    @if(!empty($filters))
    <a href="{{ route('admin.imunisasiDashboard') }}" class="im-btn im-btn--ghost">Reset</a>
    @endif
</form>

{{-- Jenis imunisasi (BIAS belum dibangun). Navigasi, bukan tombol: aria-current menandai halaman aktif. --}}
<nav class="im-tabs" aria-label="Jenis imunisasi">
    <span class="im-tab" aria-current="page">Imunisasi Rutin</span>
    <span class="im-tab im-tab--off" aria-disabled="true">BIAS <span class="im-soon">Segera hadir</span></span>
</nav>

{{-- Data sasaran: populasi kohort tahun terpilih, bukan capaian. Judul pendek; rincian di balik "?". --}}
<div class="im-h">
    <h2>Data sasaran</h2>
    <span class="im-pill">Kohort {{ $tahun }}</span>
    <x-im-help id="bantuan-sasaran" label="Data sasaran">
        <p><strong>Ini jumlah anak, bukan ukuran capaian.</strong> {{ $sasaran['label'] }}.</p>
        <p>BBL: umur 0 &ndash; 1 bln 29 hr, lahir {{ $sasaran['bbl']['rentang'][0] }} s.d. {{ $sasaran['bbl']['rentang'][1] }}.<br>
        SI: umur 2 bln &ndash; belum genap 12 bln, lahir {{ $sasaran['si']['rentang'][0] }} s.d. {{ $sasaran['si']['rentang'][1] }}.<br>
        Baduta: kohort {{ $sasaran['tahun'] - 1 }}, lahir {{ $sasaran['baduta']['rentang'][0] }} s.d. {{ $sasaran['baduta']['rentang'][1] }}.</p>
        <p>BBL, SI, dan Baduta tidak tumpang tindih &mdash; tiap anak masuk tepat satu kelompok, jadi BBL + SI =
        seluruh kelahiran periode. Anak di luar ketiga kohort tidak muncul di bagian statistik mana pun, sehingga
        jumlah di halaman ini tidak sama dengan jumlah anak terdaftar.</p>
        <p>WUS belum tersedia karena sistem ini belum mencatat data ibu/wanita usia subur.</p>
    </x-im-help>
</div>
<div class="im-strip">
    <div class="im-strip__cell">
        <div class="im-strip__lbl">BBL &middot; bayi baru lahir</div>
        <div class="im-strip__val">{{ number_format($sasaran['bbl']['jumlah']) }}</div>
        <div class="im-strip__sub">umur 0 &ndash; 1 bln 29 hr</div>
    </div>
    <div class="im-strip__cell">
        <div class="im-strip__lbl">SI &middot; Surviving Infant</div>
        <div class="im-strip__val">{{ number_format($sasaran['si']['jumlah']) }}</div>
        <div class="im-strip__sub">umur 2 &ndash; 11 bln</div>
    </div>
    <div class="im-strip__cell">
        <div class="im-strip__lbl">Baduta &middot; bawah dua tahun</div>
        <div class="im-strip__val">{{ number_format($sasaran['baduta']['jumlah']) }}</div>
        <div class="im-strip__sub">kohort {{ $sasaran['tahun'] - 1 }}</div>
    </div>
    <div class="im-strip__cell im-strip__cell--na">
        <div class="im-strip__lbl">WUS</div>
        <div class="im-strip__na"><span>WUS hamil</span><span>belum tersedia</span></div>
        <div class="im-strip__na"><span>WUS tidak hamil</span><span>belum tersedia</span></div>
    </div>
</div>

{{-- Data capaian: performa per kelompok imunisasi wajib, terpisah jelas dari data sasaran di atas --}}
<div class="im-h">
    <h2>Data capaian</h2>
    <span class="im-pill">Target 95% &middot; kohort {{ $tahun }}</span>
    <x-im-help id="bantuan-capaian" label="Data capaian">
        <p>Penyebut capaian adalah kohort tahun {{ $tahun }}: IDL dihitung atas kelompok SI, sedangkan IBL atas
        kelompok Baduta (kohort {{ $tahun - 1 }}).</p>
        <p>IDL = Imunisasi Dasar Lengkap. IBL = Imunisasi Lanjutan Baduta (booster DPT-HB-Hib, PCV, dan Campak-Rubela).</p>
        <p>Cakupan dihitung terhadap kohort tahunan, termasuk anak yang jadwal vaksinnya belum tiba. Karena itu angka
        tahun berjalan dapat di bawah target dan naik seiring anak menerima vaksin.</p>
    </x-im-help>
</div>
<div class="im-cards im-cards--3">
    <div class="im-card">
        <div class="im-card__lbl" style="display:flex; align-items:baseline; justify-content:space-between; gap:.5rem;">
            <span>IDL &middot; kelompok SI</span>
            <span class="im-badge {{ $persenIdl >= 95 ? 'im-badge--ok' : 'im-badge--warn' }}">{{ $persenIdl >= 95 ? 'Sesuai target' : 'Tertinggal' }}</span>
        </div>
        <div class="im-card__val im-num {{ $bucketOf($persenIdl) }}">{{ $persenIdl }}%</div>
        <div class="im-bar" aria-hidden="true"><div class="im-bar__fill {{ $bucketOf($persenIdl) }}" style="width:{{ min(100,$persenIdl) }}%"></div></div>
        <div class="im-card__sub">{{ number_format($idlLengkap) }} dari {{ number_format($totalAnak) }} anak</div>
    </div>
    <div class="im-card">
        <div class="im-card__lbl" style="display:flex; align-items:baseline; justify-content:space-between; gap:.5rem;">
            <span>IBL &middot; kelompok Baduta</span>
            <span class="im-badge {{ $iblCoverage['persen'] >= 95 ? 'im-badge--ok' : 'im-badge--warn' }}">{{ $iblCoverage['persen'] >= 95 ? 'Sesuai target' : 'Tertinggal' }}</span>
        </div>
        <div class="im-card__val im-num {{ $bucketOf($iblCoverage['persen']) }}">{{ $iblCoverage['persen'] }}%</div>
        <div class="im-bar" aria-hidden="true"><div class="im-bar__fill {{ $bucketOf($iblCoverage['persen']) }}" style="width:{{ min(100,$iblCoverage['persen']) }}%"></div></div>
        <div class="im-card__sub">{{ number_format($iblCoverage['ibl_lengkap']) }} dari {{ number_format($iblCoverage['total']) }} anak</div>
    </div>
    <div class="im-card">
        <div class="im-card__lbl" style="display:flex; align-items:baseline; justify-content:space-between; gap:.5rem;">
            <span>Kejar &middot; IDL/IBL</span>
            <span class="im-badge {{ $butuhKejar > 0 ? 'im-badge--warn' : 'im-badge--ok' }}">{{ $butuhKejar > 0 ? 'Perlu tindak lanjut' : 'Tidak ada' }}</span>
        </div>
        <div class="im-card__val im-num {{ $butuhKejar > 0 ? 'warn' : 'ok' }}">{{ number_format($butuhKejar) }}</div>
        <div class="im-card__sub">per hari ini, bukan tahun sasaran</div>
        <div class="im-card__foot">
            <a href="{{ route('admin.earlyWarning') }}" class="im-card__link">Lihat daftar anaknya <span aria-hidden="true">&rarr;</span></a>
        </div>
    </div>
</div>
{{-- Satu-satunya catatan metode yang TETAP terlihat: mencegah angka tahun berjalan dibaca sebagai penurunan kinerja. --}}
<p class="im-note" style="margin-top:.9rem;">
    Angka tahun berjalan bisa di bawah target dan naik seiring anak menerima vaksin &mdash; penurunan setelah
    perubahan metode ini tidak serta-merta berarti penurunan kinerja layanan.
</p>

{{-- Kohort per kecamatan & kelurahan (sudah memuat porsi % kota per kecamatan — panel "Sasaran per kecamatan" terpisah dibuang karena angka yang sama) --}}
<div class="im-h"><h2>Kohort per kecamatan &amp; kelurahan</h2></div>
<div class="im-panel im-panel--flush" style="margin-bottom:1.5rem;">
    @if(count($kohortWilayah) > 0)
    @foreach($kohortWilayah as $kec)
    <details class="im-kec">
        <summary>
            {{ $kec['nama'] }}
            <span class="im-kec__meta">{{ $kec['jumlah_rt'] }} RT</span>
            <span class="im-kec__stats im-num">
                <span>{{ number_format($kec['bbl']) }} BBL</span>
                <span>{{ number_format($kec['si']) }} SI</span>
                <span>{{ number_format($kec['baduta']) }} baduta</span>
                <strong>{{ number_format($kec['total']) }} total</strong>
                <span class="im-kec__share" style="color:var(--muted);">
                    <span class="im-bar" aria-hidden="true"><span class="im-bar__fill neutral" style="display:block; width:{{ min(100,$kec['persen_kota']) }}%"></span></span>
                    {{ $kec['persen_kota'] }}% kota
                </span>
            </span>
        </summary>
        <div class="im-scroll" tabindex="0" role="region" aria-label="Rincian kelurahan {{ $kec['nama'] }}">
        <table>
            <caption class="im-sr">Sasaran per kelurahan di kecamatan {{ $kec['nama'] }}, tahun {{ $tahun }}</caption>
            <thead>
                <tr>
                    <th scope="col">Kelurahan</th>
                    <th scope="col" class="r">RT</th>
                    <th scope="col" class="r">BBL</th>
                    <th scope="col" class="r">SI</th>
                    <th scope="col" class="r">Baduta</th>
                    <th scope="col" class="r">Total</th>
                    <th scope="col" class="bar">Distribusi</th>
                    <th scope="col" class="r">% Kota</th>
                </tr>
            </thead>
            <tbody>
            @foreach($kec['kelurahan'] as $kel)
            <tr>
                <th scope="row" class="im-kel-nama">{{ $kel['nama'] }}</th>
                <td class="r">{{ $kel['jumlah_rt'] }} RT</td>
                <td class="r">{{ number_format($kel['bbl']) }} BBL</td>
                <td class="r">{{ number_format($kel['si']) }} SI</td>
                <td class="r">{{ number_format($kel['baduta']) }} baduta</td>
                <td class="r"><strong>{{ number_format($kel['total']) }}</strong></td>
                <td class="bar">
                    <div class="im-bar" aria-hidden="true"><div class="im-bar__fill neutral" style="width:{{ min(100, $kel['persen_kota']) }}%"></div></div>
                </td>
                <td class="r" style="color:var(--muted);">{{ $kel['persen_kota'] }}%</td>
            </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    </details>
    @endforeach
    @else
    <div class="im-empty"><strong>Tidak ada data</strong>Tidak ada anak pada filter wilayah ini.</div>
    @endif
</div>

{{-- Rincian per puskesmas --}}
<div class="im-h">
    <h2>Rincian per puskesmas</h2>
    <x-im-help id="bantuan-puskesmas" label="Rincian per puskesmas">
        <p>Sasaran = kohort SI {{ $tahun }}. Wilayah kerja tiap puskesmas ditentukan lewat catchment kelurahan.
        DO rate di atas 5% ditandai dengan simbol &#9650;.</p>
    </x-im-help>
</div>
<div class="im-panel im-panel--flush" style="margin-bottom:1.5rem;">
    <div class="im-scroll" tabindex="0" role="region" aria-label="Tabel rincian per puskesmas">
        <table class="im-table">
            <caption class="im-sr">Capaian IDL per puskesmas, kohort SI {{ $tahun }}</caption>
            <thead><tr>
                <th scope="col">Puskesmas</th><th scope="col" class="r">Sasaran</th><th scope="col" class="r">Capaian IDL</th>
                <th scope="col" style="min-width:190px;">% terhadap target</th><th scope="col" class="r">DO rate</th><th scope="col" class="r">Status</th>
            </tr></thead>
            <tbody>
            @forelse($rincianPuskesmas as $pkm)
            <tr>
                <th scope="row">{{ $pkm['nama'] }}</th>
                <td class="r">{{ number_format($pkm['sasaran']) }}</td>
                <td class="r">{{ number_format($pkm['capaian_idl']) }}</td>
                <td>
                    <div style="display:flex; align-items:center; gap:.6rem;">
                        <div class="im-bar" style="flex:1;" aria-hidden="true"><div class="im-bar__fill {{ $bucketOf($pkm['persen']) }}" style="width:{{ min(100,$pkm['persen']) }}%"></div></div>
                        <span class="im-num" style="min-width:44px; text-align:right;">{{ $pkm['persen'] }}%</span>
                    </div>
                </td>
                <td class="r im-num" style="font-weight:700; color:{{ $pkm['do_rate'] > 5 ? 'var(--red-d)' : 'var(--ink)' }};">
                    @if($pkm['do_rate'] > 5)<span aria-hidden="true">&#9650;</span><span class="im-sr">di atas 5%:</span>@endif
                    {{ $pkm['do_rate'] }}%
                </td>
                <td class="r">
                    <span class="im-badge {{ $pkm['status'] === 'tertinggal' ? 'im-badge--warn' : ($pkm['status'] === 'perhatian' ? 'im-badge--warn' : 'im-badge--ok') }}">
                        {{ ['on_track' => 'Sesuai target', 'perhatian' => 'Perhatian', 'tertinggal' => 'Tertinggal', 'tidak_ada_data' => 'Tanpa data'][$pkm['status']] ?? $pkm['status'] }}
                    </span>
                </td>
            </tr>
            @empty
            <tr><td colspan="6"><div class="im-empty"><strong>Tidak ada data</strong></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Cakupan per antigen: section wajib ke-3, urut dari yang paling tertinggal --}}
<div class="im-h">
    <h2>Cakupan per antigen</h2>
    <span class="im-pill">terendah di atas</span>
    <x-im-help id="bantuan-antigen" label="Cakupan per antigen">
        <p>Persentase dihitung terhadap kelompok kohort yang sesuai dengan jendela usia pemberian antigen tersebut.
        Garis tegak pada bar menandai target 95%.</p>
        <p>Status ditulis dengan simbol dan kata (sesuai target, perlu perhatian, kritis), tidak hanya warna bar.</p>
    </x-im-help>
</div>
<div class="im-panel" style="margin-bottom:1.5rem;">
    @php $antigenUrut = collect($cakupanAntigen)->sortBy('persen')->values(); @endphp
    @if($antigenUrut->count() > 0)
    <div class="im-antigen">
        @foreach($antigenUrut as $ag)
        @php $bk = $bucketOf($ag['persen']); @endphp
        <div class="im-antigen__row">
            <span class="im-antigen__lbl">{{ $ag['nama'] }}<small class="im-antigen__grp">kelompok {{ $ag['kelompok'] }}</small></span>
            <div class="im-antigen__track" aria-hidden="true">
                <div class="im-bar__fill {{ $bk }}" style="height:100%; width:{{ min(100,$ag['persen']) }}%"></div>
                <div class="im-antigen__target"></div>
            </div>
            <span class="im-antigen__val im-num">
                <span class="im-st {{ $bk }}"><span aria-hidden="true">{{ $bucketTanda[$bk][0] }}</span><span class="im-sr">{{ $bucketTanda[$bk][1] }}:</span></span>
                {{ $ag['persen'] }}% <small>&middot; {{ number_format($ag['jumlah_sudah']) }}/{{ number_format($ag['jumlah_penyebut']) }}</small>
            </span>
        </div>
        @endforeach
    </div>
    <div class="im-legend" style="margin-top:1rem; padding-top:.8rem; border-top:1px solid var(--line);">
        <span><i style="background:var(--bar-ok)"></i><span class="im-st ok" aria-hidden="true">✓</span> &ge;95% sesuai</span>
        <span><i style="background:var(--bar-mid)"></i><span class="im-st mid" aria-hidden="true">▲</span> 60&ndash;94% perhatian</span>
        <span><i style="background:var(--bar-low)"></i><span class="im-st low" aria-hidden="true">✕</span> &lt;60% kritis</span>
        <span><i class="tgt"></i> target 95%</span>
    </div>
    @else
    <div class="im-empty"><strong>Tidak ada data</strong></div>
    @endif
</div>
@if(!empty($antigenDilewati))
<p class="im-note">
    {{ count($antigenDilewati) }} antigen dilewatkan dari cakupan (usia pemberian maksimal belum diisi di Master Data
    Vaksin): {{ implode(', ', $antigenDilewati) }}.
</p>
@endif

{{-- Funnel dosis: pelengkap cakupan per antigen, fokus ke rangkaian dosis kunci IDL --}}
<div class="im-h">
    <h2>Funnel dosis</h2>
    <span class="im-pill">Kohort SI {{ $tahun }}</span>
    @if(count($funnel) > 0)
    <x-im-help id="bantuan-funnel" label="Funnel dosis">
        <p>Jumlah anak yang sudah menerima tiap dosis, berurutan sesuai jadwal. Persentase relatif terhadap tahap pertama
        ({{ $funnel[0]['label'] }} = 100%), untuk melihat di mana populasi paling banyak berhenti &mdash; gambaran
        drop-out per dosis, lebih rinci daripada satu angka drop-out tunggal.</p>
    </x-im-help>
    @endif
</div>
<div class="im-panel" style="margin-bottom:1.5rem;">
    @if(count($funnel) > 0)
    @php $funnelBase = max(1, $funnel[0]['jumlah']); @endphp
    @foreach($funnel as $tahap)
    @php $retensi = round($tahap['jumlah'] / $funnelBase * 100, 1); @endphp
    <div class="im-funnel__row">
        <span class="im-funnel__lbl">{{ $tahap['label'] }}</span>
        <div class="im-funnel__track" aria-hidden="true"><div class="im-funnel__fill" style="width:{{ min(100, $retensi) }}%"></div></div>
        <span class="im-funnel__val im-num">{{ number_format($tahap['jumlah']) }} <small style="color:var(--muted); font-weight:400;">&middot; {{ $retensi }}%</small></span>
    </div>
    @endforeach
    @else
    <div class="im-empty"><strong>Tidak ada data</strong></div>
    @endif
</div>

{{-- Sasaran hari ini & besok: murni jadwal usia, bukan sesi posyandu/konfirmasi kehadiran --}}
<div class="im-h">
    <h2>Sasaran hari ini &amp; besok</h2>
    <span class="im-pill">tidak mengikuti tahun sasaran</span>
    <x-im-help id="bantuan-harian" label="Sasaran hari ini dan besok">
        <p>Jadwal murni menurut usia anak pada tanggal hari ini, bukan sesi posyandu atau konfirmasi kehadiran.</p>
    </x-im-help>
</div>
<div class="im-panel im-panel--flush" style="margin-bottom:1.5rem;">
    <div class="im-daytabs" role="tablist" aria-label="Hari sasaran">
        <button type="button" role="tab" id="btnHariIni" aria-selected="true" aria-controls="tabelHariIni" class="im-daytab">Hari ini &middot; {{ count($sasaranHarian['hari_ini']) }} anak</button>
        <button type="button" role="tab" id="btnBesok" aria-selected="false" aria-controls="tabelBesok" tabindex="-1" class="im-daytab">Besok &middot; {{ count($sasaranHarian['besok']) }} anak</button>
    </div>

    @foreach(['hari_ini' => ['tabelHariIni', 'btnHariIni', 'hari ini'], 'besok' => ['tabelBesok', 'btnBesok', 'besok']] as $key => [$domId, $tabId, $hariLabel])
    <div id="{{ $domId }}" role="tabpanel" aria-labelledby="{{ $tabId }}" {{ $key === 'besok' ? 'hidden' : '' }}>
        @if(count($sasaranHarian[$key]) > 0)
        <div class="im-scroll" tabindex="0" role="region" aria-label="Tabel sasaran {{ $hariLabel }}">
            <table class="im-table">
                <caption class="im-sr">Anak dengan antigen jatuh tempo {{ $hariLabel }}</caption>
                <thead><tr>
                    <th scope="col">Nama anak</th><th scope="col">Usia</th><th scope="col">Kelurahan / RT</th><th scope="col">Posyandu</th><th scope="col">Antigen jatuh tempo</th>
                </tr></thead>
                <tbody>
                @foreach($sasaranHarian[$key] as $baris)
                @php
                    $anak = $baris['anak'];
                    $usiaHari = (int) \Carbon\Carbon::parse($anak->tgl_lahir)->diffInDays(now());
                    $usiaStr = $usiaHari < 60 ? $usiaHari . ' hari' : (new DateTime($anak->tgl_lahir))->diff(new DateTime())->format('%y th %m bl');
                @endphp
                <tr>
                    <th scope="row">{{ $anak->nama }}</th>
                    <td class="im-num" style="white-space:nowrap;">{{ $usiaStr }}</td>
                    <td style="color:var(--muted); font-size:.86rem;">{{ $anak->kel->name ?? '—' }} @if($anak->rt) / RT {{ $anak->rt->name }} @endif</td>
                    <td style="color:var(--muted); font-size:.86rem;">{{ $anak->posyandu->name ?? '—' }}</td>
                    <td>
                        <div style="display:flex; gap:.35rem; flex-wrap:wrap;">
                            @foreach($baris['antigen'] as $ag)
                            <span class="im-chip {{ $ag['status'] === 'sudah' ? 'im-chip--ok' : 'im-chip--belum' }}">{{ $ag['nama'] }} &middot; {{ $ag['status'] === 'sudah' ? 'Sudah' : 'Belum' }}</span>
                            @endforeach
                        </div>
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="im-empty"><strong>Tidak ada anak jatuh tempo</strong>Tidak ada antigen yang jatuh tempo pada {{ $hariLabel }} untuk filter wilayah ini.</div>
        @endif
    </div>
    @endforeach
</div>

{{-- Analitik: alasan + korelasi (dipertahankan dari dashboard lama) --}}
<div class="im-grid2">
    <div class="im-panel" style="margin-bottom:0;">
        <div class="im-h">
            <h2>Alasan tidak imunisasi</h2>
            <x-im-help id="bantuan-alasan" label="Alasan tidak imunisasi">
                <p>Diambil dari kunjungan terakhir tiap anak pada filter yang aktif.</p>
            </x-im-help>
        </div>
        @if(count($alasanTidakImunisasi) > 0)
        <div class="im-chartbox">
            <canvas id="chartAlasan" height="150" role="img" aria-label="Diagram batang alasan tidak imunisasi. Angka rinci ada pada tabel di bawah diagram.">Angka rinci ada pada tabel di bawah.</canvas>
        </div>
        <table class="im-mini-table">
            <caption class="im-sr">Jumlah anak menurut alasan tidak imunisasi</caption>
            <thead><tr><th scope="col">Alasan</th><th scope="col" class="r" style="width:110px;">Jumlah</th></tr></thead>
            <tbody>
            @foreach($alasanTidakImunisasi as $alasan => $jumlah)
                <tr><th scope="row">{{ $alasan }}</th><td class="r im-num">{{ $jumlah }}</td></tr>
            @endforeach
            </tbody>
        </table>
        @else
        <div class="im-empty"><strong>Belum ada data</strong>Tidak ada catatan alasan tidak imunisasi pada filter ini.</div>
        @endif
    </div>

    <div class="im-panel" style="margin-bottom:0;">
        <div class="im-h">
            <h2>IDL vs stunting</h2>
            <span class="im-pill">bukan sebab-akibat</span>
            <x-im-help id="bantuan-korelasi" label="IDL vs stunting">
                <p>Tiap titik adalah satu kelurahan. Ini korelasi tingkat wilayah, bukan kausalitas individual.</p>
            </x-im-help>
        </div>
        @if(count($korelasiData) > 0)
        <div class="im-chartbox">
            <canvas id="chartKorelasi" height="150" role="img" aria-label="Diagram sebar persentase IDL terhadap persentase stunting untuk {{ count($korelasiData) }} kelurahan. Data lengkap ada pada tabel di bawah diagram.">Data lengkap ada pada tabel di bawah.</canvas>
        </div>
        <details class="im-datatable">
            <summary>Lihat data dalam tabel</summary>
            <div class="im-scroll" tabindex="0" role="region" aria-label="Tabel IDL dan stunting per kelurahan">
                <table class="im-mini-table">
                    <caption class="im-sr">IDL dan stunting per kelurahan</caption>
                    <thead><tr>
                        <th scope="col">Kelurahan</th><th scope="col" class="r">Balita terukur</th>
                        <th scope="col" class="r">IDL lengkap</th><th scope="col" class="r">% IDL</th>
                        <th scope="col" class="r">Stunting</th><th scope="col" class="r">% Stunting</th>
                    </tr></thead>
                    <tbody>
                    @foreach(collect($korelasiData)->sortBy('nama') as $k)
                        <tr>
                            <th scope="row">{{ $k['nama'] }}</th>
                            <td class="r">{{ number_format($k['total_balita']) }}</td>
                            <td class="r">{{ number_format($k['idl_lengkap']) }}</td>
                            <td class="r">{{ $k['idl_pct'] }}%</td>
                            <td class="r">{{ number_format($k['stunting']) }}</td>
                            <td class="r">{{ $k['stunting_pct'] }}%</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </details>
        @else
        <div class="im-empty"><strong>Belum ada data</strong>Belum ada balita terukur untuk membentuk korelasi.</div>
        @endif
    </div>
</div>
</div>{{-- /im-page --}}
@endsection

@section('custom_scripts')
<script src="{{ asset('js/dasbor-a11y.js') }}"></script>
<script>
(function () {
    var URL_RT_BY_KEL  = '{{ url("admin/get-rt-by-kel-anak") }}';
    var SELECTED_RT     = '{{ $filters['id_rt'] ?? '' }}';
    var $kec = $('#filterKec'), $kel = $('#filterKel'), $rt = $('#filterRt');

    function fillSelect($sel, data, placeholder, selected) {
        $sel.empty().append($('<option>', { value: '', text: placeholder }));
        $.each(data, function (id, name) {
            $sel.append($('<option>', { value: id, text: name, selected: String(id) === String(selected) }));
        });
    }

    function loadRt(kelId, selected) {
        if (!kelId) { fillSelect($rt, {}, 'Semua RT'); return; }
        $.getJSON(URL_RT_BY_KEL + '/' + kelId, function (d) { fillSelect($rt, d, 'Semua RT', selected); });
    }

    // Daftar kelurahan lengkap disimpan sekali; <option> di luar kecamatan terpilih DIBUANG dari DOM,
    // bukan di-`.toggle()`: Safari/iOS mengabaikan display:none pada <option>, jadi di iPhone
    // semua kelurahan tetap tampil. Validitas pilihan dicek dari data-kec, BUKAN dari jQuery `:hidden`
    // (yang selalu true untuk <option>) — supaya pilihan dari query string tidak ikut ke-reset.
    var semuaKel = $kel.find('option[data-kec]').get();
    function filterKelOptionsByKec(kecId) {
        var currentVal = $kel.val();
        var stillValid = false;
        $kel.find('option[data-kec]').remove();
        $.each(semuaKel, function () {
            var match = !kecId || String($(this).data('kec')) === String(kecId);
            if (!match) { return; }
            $kel.append(this);
            if (this.value === currentVal) { stillValid = true; }
        });
        $kel.val(stillValid ? currentVal : '');
    }

    $kec.on('change', function () {
        filterKelOptionsByKec(this.value);
        $kel.val('');
        fillSelect($rt, {}, 'Semua RT');
    });

    $kel.on('change', function () { loadRt(this.value, ''); });

    // State awal (reload dengan filter aktif dari query string).
    if ($kec.val()) { filterKelOptionsByKec($kec.val()); }
    if ($kel.val()) { loadRt($kel.val(), SELECTED_RT); }
})();
</script>

@php $needChart = count($korelasiData) > 0 || count($alasanTidakImunisasi) > 0; @endphp
@if($needChart)
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(function () {
    if (typeof Chart === 'undefined') return;
    Chart.defaults.font.family = "'Barlow', system-ui, sans-serif";
    Chart.defaults.font.size = 13;
    Chart.defaults.color = '#535a53'; // = --muted
    // Hormati preferensi "kurangi gerakan" (sistem operasi).
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        Chart.defaults.animation = false;
    }

    // Hex padanan token CSS (kanvas lama tak selalu paham oklch). Semua >=3:1 thd putih (WCAG 1.4.11).
    var AMBER = '#b17000', AMBER_S = '#8f5b00';   // = --bar-mid (gelap)
    var GREEN = '#157123', GREEN_S = '#0f5a1c';   // = --bar-ok
    var INK   = '#535a53';                        // = --muted (garis tren)
    var GRID  = 'rgba(60,70,60,0.12)';

    {{-- Bar horizontal: alasan tidak imunisasi --}}
    @if(count($alasanTidakImunisasi) > 0)
    (function () {
        var ctx = document.getElementById('chartAlasan');
        if (!ctx) return;
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: @json(array_keys($alasanTidakImunisasi)),
                datasets: [{
                    data: @json(array_values($alasanTidakImunisasi)),
                    backgroundColor: AMBER, hoverBackgroundColor: AMBER_S,
                    borderRadius: 4, barThickness: 'flex', maxBarThickness: 26
                }]
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: true,
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: GRID } },
                    y: { grid: { display: false } }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: function (c) { return c.parsed.x + ' anak'; } } }
                }
            }
        });
    })();
    @endif

    {{-- Scatter korelasi IDL vs stunting --}}
    @if(count($korelasiData) > 0)
    (function () {
        var ctx = document.getElementById('chartKorelasi');
        if (!ctx) return;
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

        new Chart(ctx, {
            type: 'scatter',
            data: {
                datasets: [
                    { label: 'Kelurahan', data: points, backgroundColor: GREEN, hoverBackgroundColor: GREEN_S, pointRadius: 6, pointHoverRadius: 8 },
                    { type: 'line', label: 'Tren', data: trend, borderColor: INK, borderDash: [6, 4], borderWidth: 2, pointRadius: 0, fill: false }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: true,
                scales: {
                    x: { title: { display: true, text: '% IDL' }, min: 0, max: 100, grid: { color: GRID } },
                    y: { title: { display: true, text: '% Stunting' }, min: 0, max: 100, grid: { color: GRID } }
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
    @endif
})();
</script>
@endif
@endsection

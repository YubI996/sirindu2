@extends('admin::layouts.app')
@section('title') Dashboard Kesmas @endsection
@section('title-content') Dashboard Kesmas @endsection
@section('item') Kesmas @endsection
@section('item-active') Tumbuh Kembang Balita @endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/dasbor-base.css') }}">
<style>
/* ── Khusus dasbor Kesmas (token & komponen dasar .im-* ada di dasbor-base.css) ── */
.km-head{ display:flex; flex-wrap:wrap; align-items:flex-end; justify-content:space-between; gap:.75rem 1rem; margin-bottom:1.1rem; }
.km-head h1{ font-family:'Barlow Condensed','Barlow',sans-serif; font-weight:700; font-size:1.7rem; line-height:1.1; margin:0; color:var(--ink); }
.km-head .km-sub{ color:var(--muted); font-size:.85rem; margin-top:.3rem; }
.km-usia{ display:flex; flex-wrap:wrap; gap:.4rem; align-items:center; margin:-.6rem 0 1.4rem; }
.km-usia .km-lbl{ font-size:.68rem; font-weight:700; letter-spacing:.05em; text-transform:uppercase; color:var(--muted); margin-right:.3rem; }
.km-chip{ height:30px; padding:0 .8rem; border-radius:99px; border:1px solid var(--line); background:var(--card); font-family:inherit; font-weight:600; font-size:.78rem; color:var(--ink); cursor:pointer; }
.km-chip[aria-pressed="true"]{ background:var(--green-dk); border-color:var(--green-dk); color:#fff; }
.km-chip:focus-visible{ outline:2px solid oklch(0.60 0.15 145 / .5); outline-offset:2px; }
/* Kartu SPM */
.km-kicker{ display:flex; justify-content:space-between; align-items:center; gap:.5rem; }
.km-pill{ font-size:.66rem; font-weight:700; padding:.15rem .5rem; border-radius:99px; background:oklch(0.95 0.012 145); color:var(--muted); white-space:nowrap; }
.km-pill--ok{ background:oklch(0.94 0.06 145); color:var(--green-dk); }
.km-pill--mid{ background:var(--amber-bg); color:var(--amber); }
.km-pill--low{ background:var(--red-bg); color:var(--red-d); }
.km-title{ font-weight:700; font-size:1.02rem; line-height:1.25; color:var(--ink); }
.km-big{ display:flex; align-items:baseline; gap:.4rem; flex-wrap:wrap; }
.km-big .n{ font-family:'Barlow Condensed','Barlow',sans-serif; font-weight:700; font-size:2rem; line-height:1; color:var(--ink); }
.km-big .d{ color:var(--faint); font-size:.9rem; }
.km-big .p{ font-weight:700; font-size:1rem; margin-left:auto; }
.km-big .p.ok{ color:var(--green-dk); } .km-big .p.mid{ color:var(--amber); } .km-big .p.low{ color:var(--red-d); } .km-big .p.na{ color:var(--faint); }
.km-prog{ height:7px; border-radius:99px; background:oklch(0.93 0.012 145); overflow:hidden; }
.km-prog > span{ display:block; height:100%; border-radius:99px; background:var(--green-d); }
.km-prog > span.mid{ background:var(--amber); } .km-prog > span.low{ background:var(--red-d); }
.km-subs{ display:flex; flex-wrap:wrap; gap:.35rem; }
.km-subs .km-sub{ font-size:.7rem; font-weight:600; padding:.16rem .5rem; border-radius:6px; border:1px solid var(--line); background:oklch(0.985 0.008 145); color:var(--ink); margin-top:0; }
.km-subs .km-sub.ok{ border-color:oklch(0.85 0.08 145); color:var(--green-dk); } .km-subs .km-sub.mid{ border-color:oklch(0.85 0.09 70); color:var(--amber); } .km-subs .km-sub.low{ border-color:oklch(0.85 0.09 25); color:var(--red-d); }
.km-foot{ margin-top:auto; font-size:.78rem; color:var(--muted); display:flex; gap:.5rem; align-items:center; flex-wrap:wrap; }
.km-foot b{ color:var(--ink); }
.km-foot .warn{ color:var(--red-d); font-weight:700; }
.km-foot--grid{ display:grid; grid-template-columns:1fr 1fr; gap:.5rem; align-items:start; }
.km-foot__big{ font-size:1.1rem; }
.km-dot{ display:inline-block; width:8px; height:8px; border-radius:50%; background:var(--red); }
/* Baris SDIDTK / CKG */
.km-row{ display:grid; grid-template-columns:1fr auto; gap:.15rem .6rem; align-items:baseline; padding:.45rem 0; border-top:1px solid var(--line); }
.km-row:first-of-type{ border-top:0; }
.km-row .lbl{ font-size:.85rem; font-weight:600; color:var(--ink); }
.km-row .dom{ font-size:.72rem; color:var(--faint); }
.km-row .val{ font-size:.82rem; color:var(--muted); white-space:nowrap; }
.km-row .val b{ color:var(--ink); }
.km-row .km-prog{ grid-column:1 / -1; height:6px; }
.km-row.hl .lbl{ color:var(--green-dk); }
.km-callout{ margin-top:.8rem; padding:.7rem .85rem; border-radius:10px; background:var(--amber-bg); color:oklch(0.35 0.10 70); font-size:.8rem; line-height:1.45; }
.km-callout b{ color:oklch(0.30 0.11 70); }
.km-empty{ padding:1rem; border:1px dashed var(--line); border-radius:10px; color:var(--muted); font-size:.85rem; text-align:center; }
/* IDL */
.km-donut{ display:flex; gap:1rem; align-items:center; }
.km-donut canvas{ width:120px !important; height:120px !important; }
.km-legend{ font-size:.8rem; color:var(--muted); display:flex; flex-direction:column; gap:.3rem; }
.km-legend b{ color:var(--ink); }
.km-alasan{ margin:.6rem 0 0; padding:0; list-style:none; font-size:.8rem; }
.km-alasan li{ display:flex; justify-content:space-between; gap:.5rem; padding:.28rem 0; border-top:1px solid var(--line); }
.km-alasan li b{ color:var(--ink); }
@media(max-width:700px){ .km-head h1{ font-size:1.35rem; } }
</style>
<link rel="stylesheet" href="{{ asset('css/kesmas-dashboard.css') }}">
@endpush

@section('content')
@php
    $fmt  = fn ($n) => number_format((int) $n, 0, ',', '.');
    $pct  = fn ($p) => $p === null ? '—' : number_format($p, 1, ',', '.') . ' %';
    $tone = fn ($p) => $p === null ? 'na' : ($p >= 80 ? 'ok' : ($p >= 60 ? 'mid' : 'low'));

    $namaWilayah = 'Kota Bontang';
    if (!empty($filters['id_posyandu']))      $namaWilayah = 'Posyandu ' . optional($posyanduList->firstWhere('id', $filters['id_posyandu']))->name;
    elseif (!empty($filters['id_kelurahan'])) $namaWilayah = 'Kel. ' . optional($kelurahanList->firstWhere('id', $filters['id_kelurahan']))->name;
    elseif (!empty($filters['id_puskesmas'])) $namaWilayah = 'Puskesmas ' . optional($puskesmasList->firstWhere('id', $filters['id_puskesmas']))->name;
    elseif (!empty($filters['id_kecamatan'])) $namaWilayah = 'Kec. ' . optional($kecamatanList->firstWhere('id', $filters['id_kecamatan']))->name;

    $exportQs = array_filter([
        'id_kec' => $filters['id_kecamatan'] ?? null, 'id_kel' => $filters['id_kelurahan'] ?? null,
        'id_puskesmas' => $filters['id_puskesmas'] ?? null, 'id_posyandu' => $filters['id_posyandu'] ?? null,
    ]);
@endphp
<div class="im-page km-page">

    <header class="km-head">
        <div>
            <h1>Dashboard Kesmas — Tumbuh Kembang Balita</h1>
            <div class="km-sub">{{ $namaWilayah }} &middot; {{ $periode->label() }}</div>
        </div>
        <a href="{{ route('admin.export.kesmas.index', $exportQs) }}" class="im-btn im-btn--ghost">
            <span class="material-symbols-outlined" style="font-size:18px;">download</span>Export Data
        </a>
    </header>

    @include('admin.kesmas.partials._filter')

    <div class="km-usia" role="group" aria-label="Kelompok usia untuk registri">
        <span class="km-lbl">Kelompok usia</span>
        @foreach(['semua' => 'Semua (0–72 bln)', 'bayi' => 'Bayi (0–11)', 'baduta' => 'Baduta (12–23)', 'balita' => 'Balita (24–59)', 'prasekolah' => 'Prasekolah (60–72)'] as $kode => $label)
            <button type="button" class="km-chip" data-usia="{{ $kode }}" aria-pressed="{{ $usia === $kode ? 'true' : 'false' }}">{{ $label }}</button>
        @endforeach
    </div>

    @include('admin.kesmas.partials._spm')

    @include('admin.kesmas.partials._sdidtk-ckg-idl')

    @include('admin.kesmas.partials._layanan')

    @include('admin.kesmas.partials._registri')

</div>
@endsection

@push('js')
@if($idl['total'] > 0)
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function () {
    var el = document.getElementById('idlDonut');
    if (!el || typeof Chart === 'undefined') return;
    new Chart(el.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['IDL lengkap', 'Belum lengkap'],
            datasets: [{ data: [{{ (int) $idl['idl_lengkap'] }}, {{ (int) ($idl['total'] - $idl['idl_lengkap']) }}],
                backgroundColor: ['#2f7d4f', '#e4e8e4'], borderWidth: 0 }]
        },
        options: { cutout: '72%', plugins: { legend: { display: false } }, responsive: false }
    });
})();
</script>
@endif
<script>
(function () {
    var URL_RT_BY_KEL  = '{{ url("admin/get-rt-by-kel-anak") }}';
    var SELECTED_RT    = '{{ $filters['id_rt'] ?? '' }}';
    var $kec = $('#filterKec'), $kel = $('#filterKel'), $rt = $('#filterRt');
    var rtRequest = null, rtVersion = 0;

    function fillSelect($sel, data, placeholder, selected) {
        $sel.empty().append($('<option>', { value: '', text: placeholder }));
        $.each(data, function (id, name) {
            $sel.append($('<option>', { value: id, text: name, selected: String(id) === String(selected) }));
        });
    }

    function loadRt(kelId, selected) {
        var version = ++rtVersion;
        if (rtRequest) { rtRequest.abort(); rtRequest = null; }
        fillSelect($rt, {}, 'Semua RT');
        if (!kelId) return;
        rtRequest = $.getJSON(URL_RT_BY_KEL + '/' + kelId, function (d) {
            if (version !== rtVersion || String($kel.val()) !== String(kelId)) return;
            fillSelect($rt, d, 'Semua RT', selected);
        });
    }

    // Validitas pilihan kelurahan dicek dari data-kec, BUKAN jQuery :hidden — <option> tak punya
    // box model saat select tertutup, jadi :hidden selalu true dan pilihan awal ke-reset.
    function filterKelOptionsByKec(kecId) {
        var currentVal = $kel.val();
        var stillValid = false;
        $kel.find('option[data-kec]').each(function () {
            var match = !kecId || String($(this).data('kec')) === String(kecId);
            $(this).toggle(match);
            if (match && this.value === currentVal) { stillValid = true; }
        });
        if (currentVal && !stillValid) { $kel.val(''); }
    }

    $kec.on('change', function () { filterKelOptionsByKec(this.value); $kel.val(''); loadRt('', ''); });
    $kel.on('change', function () { loadRt(this.value, ''); });

    if ($kec.val()) { filterKelOptionsByKec($kec.val()); }
    if ($kel.val()) { loadRt($kel.val(), SELECTED_RT); }
})();
</script>
<script src="{{ asset('js/kesmas-registri.js') }}" defer></script>
@endpush

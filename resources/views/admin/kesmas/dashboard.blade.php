```blade
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
@media(max-width:700px){ .km-head h1{ font-size:1.35rem; } }
</style>
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

</div>
@endsection

@push('js')
<script>
(function () {
    var URL_KEL_BY_KEC = '{{ url("admin/get-kel-dasar-anak") }}';
    var URL_RT_BY_KEL  = '{{ url("admin/get-rt-by-kel-anak") }}';
    var SELECTED_RT    = '{{ $filters['id_rt'] ?? '' }}';
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

    $kec.on('change', function () { filterKelOptionsByKec(this.value); $kel.val(''); fillSelect($rt, {}, 'Semua RT'); });
    $kel.on('change', function () { loadRt(this.value, ''); });

    if ($kec.val()) { filterKelOptionsByKec($kec.val()); }
    if ($kel.val()) { loadRt($kel.val(), SELECTED_RT); }
})();
</script>
@endpush

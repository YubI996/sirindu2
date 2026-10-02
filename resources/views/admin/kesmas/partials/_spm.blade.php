{{-- Baris 1 — kartu SPM (definisi angka: KesmasDashboardService::spmKohort / pemantauanTk).
     Persen null (denominator 0) tampil "—" lewat $pct, tidak pernah "0,0 %". --}}
@php $sy = $spm['syarat']; @endphp
<section class="im-cards" aria-label="Kartu SPM tumbuh kembang">

    <article class="im-card" data-blok="spm-balita">
        <div class="km-kicker"><span class="im-card__lbl">SPM Kemenkes No. 4/2019</span>
            <span class="km-pill km-pill--{{ $tone($spm['balita']['persen']) }}">{{ $pct($spm['balita']['persen']) }}</span></div>
        <div class="km-title">Pelayanan Kesehatan Balita</div>
        <div class="im-card__sub">Usia 0–5 tahun (0–59 bulan): gabungan Pelayanan Kesehatan Bayi + Anak Balita yang mendapat pelayanan sesuai standar</div>
        <div class="km-big im-num"><span class="n">{{ $fmt($spm['balita']['lengkap']) }}</span><span class="d">/ {{ $fmt($spm['balita']['sasaran']) }} balita</span>
            <span class="p {{ $tone($spm['balita']['persen']) }}">{{ $pct($spm['balita']['persen']) }}</span></div>
        <div class="km-prog" role="img" aria-label="{{ $pct($spm['balita']['persen']) }}"><span class="{{ $tone($spm['balita']['persen']) }}" style="width:{{ (int) ($spm['balita']['persen'] ?? 0) }}%"></span></div>
        <div class="km-k1-rincian im-num">{{ $fmt($spm['bayi']['lengkap']) }} bayi (0–11 bln) + {{ $fmt($spm['anak_balita']['lengkap']) }} anak balita (12–59 bln)</div>
        <div class="km-foot">Syarat periode ini: {{ $sy['timbang'] }}× timbang · {{ $sy['ddtka'] }}× DDTKA · Vit A</div>
    </article><!-- /spm-balita -->

    <article class="im-card" data-blok="spm-bayi">
        <div class="km-kicker"><span class="im-card__lbl">Kohort bayi 0–11 bulan</span>
            <span class="km-pill km-pill--{{ $tone($spm['bayi']['persen']) }}">{{ $pct($spm['bayi']['persen']) }}</span></div>
        <div class="km-title">Pelayanan Kesehatan Bayi</div>
        <div class="im-card__sub">{{ $sy['timbang'] }}× timbang, {{ $sy['ddtka'] }}× DDTKA, Vit A (≥6 bln), lingkar kepala terukur</div>
        <div class="km-big im-num"><span class="n">{{ $fmt($spm['bayi']['lengkap']) }}</span><span class="d">/ {{ $fmt($spm['bayi']['sasaran']) }} bayi</span>
            <span class="p {{ $tone($spm['bayi']['persen']) }}">{{ $pct($spm['bayi']['persen']) }}</span></div>
        <div class="km-subs">
            @foreach(['timbang' => $sy['timbang'] . '× Tbg', 'ddtka' => $sy['ddtka'] . '× DDTKA', 'vita' => 'Vit A', 'lk' => 'LK'] as $k => $lbl)
                @php $s = $spm['bayi']['sub'][$k]; @endphp
                <span class="km-sub {{ $tone($s['persen']) }}" title="{{ $fmt($s['n']) }} dari {{ $fmt($s['sasaran']) }}">{{ $lbl }}: {{ $pct($s['persen']) }}</span>
            @endforeach
            @php $idlPersen = $idl['total'] > 0 ? $idl['persen'] : null; @endphp
            <span class="km-sub {{ $tone($idlPersen) }}" title="Kohort SI {{ $periode->tahun() }}, tidak mengikuti semester/triwulan; populasi kohort imunisasi, tidak memakai tanda Sasaran Balita Kesmas">IDL: {{ $pct($idlPersen) }}</span>
        </div>
        <div class="km-foot">Sisa belum lengkap: <b>{{ $fmt($spm['bayi']['sisa']) }} bayi</b>
            @if($spm['bayi']['sasaran'] > 0)<span>({{ $pct(round($spm['bayi']['sisa'] / $spm['bayi']['sasaran'] * 100, 1)) }})</span>@endif</div>
    </article><!-- /spm-bayi -->

    <article class="im-card" data-blok="spm-anak-balita">
        <div class="km-kicker"><span class="im-card__lbl">Kohort anak balita 12–59 bulan</span>
            <span class="km-pill km-pill--{{ $tone($spm['anak_balita']['persen']) }}">{{ $pct($spm['anak_balita']['persen']) }}</span></div>
        <div class="km-title">Pelayanan Kesehatan Anak Balita</div>
        <div class="im-card__sub">{{ $sy['timbang'] }}× timbang, {{ $sy['ddtka'] }}× DDTKA, {{ $sy['vita'] }}× Vit A; imunisasi lanjutan dipantau lewat IBL</div>
        <div class="km-big im-num"><span class="n">{{ $fmt($spm['anak_balita']['lengkap']) }}</span><span class="d">/ {{ $fmt($spm['anak_balita']['sasaran']) }} anak</span>
            <span class="p {{ $tone($spm['anak_balita']['persen']) }}">{{ $pct($spm['anak_balita']['persen']) }}</span></div>
        <div class="km-subs">
            @foreach(['timbang' => $sy['timbang'] . '× Tbg', 'ddtka' => $sy['ddtka'] . '× DDTKA', 'vita' => 'Vit A (' . $sy['vita'] . '×)'] as $k => $lbl)
                @php $s = $spm['anak_balita']['sub'][$k]; @endphp
                <span class="km-sub {{ $tone($s['persen']) }}" title="{{ $fmt($s['n']) }} dari {{ $fmt($s['sasaran']) }}">{{ $lbl }}: {{ $pct($s['persen']) }}</span>
            @endforeach
            @php $iblPersen = $ibl['total'] > 0 ? $ibl['persen'] : null; @endphp
            <span class="km-sub {{ $tone($iblPersen) }}" title="Kohort Baduta {{ $periode->tahun() }}, tidak mengikuti semester/triwulan; populasi kohort imunisasi, tidak memakai tanda Sasaran Balita Kesmas">IBL: {{ $pct($iblPersen) }}</span>
        </div>
        <div class="km-foot">Kesenjangan target: <b>{{ $fmt($spm['anak_balita']['gap']) }} anak</b>
            @if($spm['anak_balita']['sasaran'] > 0)<span>({{ $pct(round($spm['anak_balita']['gap'] / $spm['anak_balita']['sasaran'] * 100, 1)) }})</span>@endif</div>
    </article><!-- /spm-anak-balita -->

    <article class="im-card" data-blok="spm-tk">
        <div class="km-kicker"><span class="im-card__lbl">SPM Tumbuh Kembang Balita</span>
            <span class="km-pill km-pill--{{ $tone($tk['persen']) }}">{{ $pct($tk['persen']) }}</span></div>
        <div class="km-title">Balita Dilayani Tumbuh Kembang</div>
        <div class="im-card__sub">0–59 bulan dengan min. {{ $sy['timbang'] }}× timbang + {{ $sy['ddtka'] }}× DDTKA dalam periode</div>
        <div class="km-big im-num"><span class="n">{{ $fmt($tk['lengkap']) }}</span><span class="d">/ {{ $fmt($tk['sasaran']) }} balita</span>
            <span class="p {{ $tone($tk['persen']) }}">{{ $pct($tk['persen']) }}</span></div>
        <div class="km-prog" role="img" aria-label="{{ $pct($tk['persen']) }}"><span class="{{ $tone($tk['persen']) }}" style="width:{{ (int) ($tk['persen'] ?? 0) }}%"></span></div>
        <div class="km-foot"><span class="km-dot" aria-hidden="true"></span>
            <a href="#registri" class="warn" data-registri-status="perhatian">{{ $fmt($tk['perhatian']) }} balita perlu perhatian</a>
            <span>(BB tidak naik / BB/U &lt; −2 SD pada kunjungan terakhir)</span></div>
    </article><!-- /spm-tk -->

</section>

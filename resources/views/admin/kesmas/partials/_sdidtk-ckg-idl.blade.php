{{-- Baris 2 — SDIDTK, CKG, IDL & IBL. IDL/IBL mengikuti TAHUN kohort, bukan semester/triwulan. --}}
<section class="im-cards im-cards--3" aria-label="SDIDTK, CKG, dan imunisasi">

    <article class="im-card" data-blok="sdidtk">
        <div class="im-h" style="margin-bottom:.2rem;"><h2>Cakupan Layanan SDIDTK</h2></div>
        <div class="im-card__sub">Stimulasi, Deteksi &amp; Intervensi Dini Tumbuh Kembang (0–72 bulan)</div>
        <div class="km-big im-num"><span class="n">{{ $fmt($sdidtk['total']['realisasi']) }}</span><span class="d">dari {{ $fmt($sdidtk['total']['sasaran']) }} anak</span>
            <span class="p {{ $tone($sdidtk['total']['persen']) }}">{{ $pct($sdidtk['total']['persen']) }}</span></div>
        <div id="sdidtkRows">
            @foreach($sdidtk['kelompok'] as $kode => $k)
                <div class="km-row {{ $usia === $kode ? 'hl' : '' }}" data-usia="{{ $kode }}">
                    <div><span class="lbl">{{ $k['label'] }} ({{ $k['min'] }}–{{ $k['max'] }} bln)</span> <span class="dom">{{ $k['domain'] }}</span></div>
                    <div class="val im-num"><b>{{ $fmt($k['realisasi']) }}</b> / {{ $fmt($k['sasaran']) }} ({{ $pct($k['persen']) }})</div>
                    <div class="km-prog"><span class="{{ $tone($k['persen']) }}" style="width:{{ (int) ($k['persen'] ?? 0) }}%"></span></div>
                </div>
            @endforeach
        </div>
        @if($sdidtk['fokus'])
            <div class="km-callout"><b>Fokus intervensi:</b> kelompok {{ $sdidtk['fokus']['label'] }} ({{ $sdidtk['fokus']['min'] }}–{{ $sdidtk['fokus']['max'] }} bln) memiliki gap terbesar ({{ number_format($sdidtk['fokus']['gap'], 1, ',', '.') }} %). Prioritaskan skrining di posyandu/PAUD wilayah ini.</div>
        @endif
    </article><!-- /sdidtk -->

    <article class="im-card" data-blok="ckg">
        <div class="im-h" style="margin-bottom:.2rem;"><h2>Cek Kesehatan Gigi &amp; CKG</h2></div>
        <div class="im-card__sub">Anak dengan penanda CKG (Cek Kesehatan Gratis) dalam periode, per usia</div>
        <div>
            @foreach($ckg['kelompok'] as $k)
                <div class="km-row">
                    <div><span class="lbl">{{ $k['label'] }}</span></div>
                    <div class="val im-num"><b>{{ $fmt($k['realisasi']) }}</b> / {{ $fmt($k['sasaran']) }} ({{ $pct($k['persen']) }})</div>
                    <div class="km-prog"><span class="{{ $tone($k['persen']) }}" style="width:{{ (int) ($k['persen'] ?? 0) }}%"></span></div>
                </div>
            @endforeach
        </div>
        <div class="km-foot km-foot--grid">
            <div>Bebas karies (gigi sehat)<br><b class="im-num km-foot__big">{{ $pct($ckg['gigi']['persen_sehat']) }}</b>
                <small>dari {{ $fmt($ckg['gigi']['terisi']) }} pemeriksaan</small></div>
            <div>Rujuk dokter gigi<br><b class="im-num km-foot__big">{{ $fmt($ckg['rujuk_gigi']) }} anak</b></div>
        </div>
    </article><!-- /ckg -->

    <article class="im-card" data-blok="idl">
        @php
            $idlP = $idl['total'] > 0 ? $idl['persen'] : null;
            $iblP = $ibl['total'] > 0 ? $ibl['persen'] : null;
        @endphp
        <div class="im-h" style="margin-bottom:.2rem;"><h2>Imunisasi Dasar &amp; Lanjut</h2></div>
        <div class="im-card__sub">Cakupan IDL (kohort SI) &amp; IBL (kohort Baduta) {{ $periode->tahun() }} — mengikuti tahun, bukan semester/triwulan</div>
        <div class="km-donut">
            <canvas id="idlDonut" width="120" height="120" role="img" aria-label="IDL lengkap {{ $pct($idlP) }}"></canvas>
            <div class="km-legend im-num">
                <span><b>{{ $pct($idlP) }}</b> IDL lengkap ({{ $fmt($idl['idl_lengkap']) }}/{{ $fmt($idl['total']) }})</span>
                <span><b>{{ $pct($idlP === null ? null : round(100 - $idlP, 1)) }}</b> belum lengkap</span>
                <span><b>{{ $pct($iblP) }}</b> IBL booster ({{ $fmt($ibl['ibl_lengkap']) }}/{{ $fmt($ibl['total']) }})</span>
            </div>
        </div>
        @if(count($alasan) > 0)
            <div class="im-card__lbl" style="margin-top:.5rem;">Penyebab belum lengkap</div>
            <ul class="km-alasan">
                @php $totalAlasan = array_sum($alasan); @endphp
                @foreach($alasan as $nama => $jumlah)
                    <li><span>{{ $nama }}</span><b class="im-num">{{ $pct(round($jumlah / max(1, $totalAlasan) * 100, 1)) }}</b></li>
                @endforeach
            </ul>
        @endif
        <div class="km-foot"><a href="{{ route('admin.imunisasiDashboard', array_merge($filters, ['tahun' => $periode->tahun()])) }}" class="im-card__link">Lihat dasbor imunisasi &rarr;</a></div>
    </article><!-- /idl -->

</section>

    {{-- Baris 3 — Layanan & Lingkungan: pembagi = anak yang datanya TERISI; NULL = belum ditanya --}}
    <section class="km-wide" data-blok="layanan" aria-label="Layanan dan lingkungan">
        <div class="im-h"><h2>Layanan &amp; Lingkungan</h2><small>persentase dihitung dari anak yang datanya sudah diisi</small></div>
        <div class="km-panels">

            <div class="km-panel" data-panel="layanan">
                <h3>Layanan per kunjungan</h3>
                <div class="im-card__sub">Anak 0–72 bln dengan ≥1 kunjungan dalam periode; dari {{ $fmt($layanan['sasaran']) }} sasaran</div>
                @if($layanan['layanan']['ada_data'])
                    @foreach($layanan['layanan']['baris'] as $kode => $b)
                        <div class="km-bar" data-baris="{{ $kode }}">
                            <span class="lbl" title="{{ $b['label'] }}">{{ $b['badge'] }} <small style="font-weight:400;color:var(--faint);">{{ \Illuminate\Support\Str::after($b['label'], '— ') }}</small></span>
                            <span class="val im-num"><b>{{ $fmt($b['ya']) }}</b> / {{ $fmt($b['terisi']) }} ({{ $pct($b['persen']) }})</span>
                            <div class="km-prog"><span class="{{ $tone($b['persen']) }}" style="width:{{ (int) ($b['persen'] ?? 0) }}%"></span></div>
                            <span class="km-belum">{{ $fmt($b['belum_diisi']) }} anak belum diisi</span>
                        </div>
                    @endforeach
                @else
                    <div class="km-empty">Belum ada data layanan — lengkapi lewat Edit Anak (kartu Kesmas per kunjungan)</div>
                    <p class="km-belum">{{ $fmt($layanan['sasaran']) }} anak belum diisi untuk setiap layanan</p>
                @endif
            </div>

            <div class="km-panel" data-panel="skrining">
                <h3>Skrining neonatal</h3>
                <div class="im-card__sub">Bayi 0–11 bln; dari {{ $fmt($layanan['bayi']) }} bayi</div>
                @if($layanan['skrining']['ada_data'])
                    @foreach($layanan['skrining']['baris'] as $kode => $b)
                        <div class="km-bar" data-baris="{{ $kode }}">
                            <span class="lbl">{{ $b['label'] }}</span>
                            <span class="val im-num">{{ $fmt($b['terisi']) }} terisi</span>
                            <div class="km-stack" role="img" aria-label="{{ collect($b['sebaran'])->map(fn ($s) => $s['label'] . ' ' . $fmt($s['n']))->implode(', ') }}">
                                @foreach($b['sebaran'] as $nilai => $s)
                                    <span class="s-{{ $nilai }}" style="width:{{ (int) ($s['persen'] ?? 0) }}%"></span>
                                @endforeach
                            </div>
                            <span class="km-legend-inline">
                                @foreach($b['sebaran'] as $s)<span>{{ $s['label'] }} <b>{{ $fmt($s['n']) }}</b></span>@endforeach
                            </span>
                            <span class="km-belum">{{ $fmt($b['belum_diisi']) }} bayi belum diisi</span>
                        </div>
                    @endforeach
                @else
                    <div class="km-empty">Belum ada data skrining — lengkapi lewat Edit Anak (kartu Riwayat lahir &amp; skrining)</div>
                    <p class="km-belum">{{ $fmt($layanan['bayi']) }} bayi belum diisi untuk setiap skrining</p>
                @endif
            </div>

            <div class="km-panel" data-panel="sanitasi">
                <h3>Sanitasi rumah</h3>
                <div class="im-card__sub">Anak 0–72 bln; dari {{ $fmt($layanan['sasaran']) }} sasaran</div>
                @if($layanan['sanitasi']['ada_data'])
                    @foreach($layanan['sanitasi']['baris'] as $kode => $b)
                        <div class="km-bar {{ $b['terbalik'] ? 'terbalik' : '' }}" data-baris="{{ $kode }}">
                            <span class="lbl">{{ $b['label'] }}</span>
                            <span class="val im-num"><b>{{ $fmt($b['ya']) }}</b> / {{ $fmt($b['terisi']) }} ({{ $pct($b['persen']) }})</span>
                            <div class="km-prog"><span class="{{ $b['terbalik'] ? '' : $tone($b['persen']) }}" style="width:{{ (int) ($b['persen'] ?? 0) }}%"></span></div>
                            <span class="km-belum">{{ $fmt($b['belum_diisi']) }} anak belum diisi</span>
                        </div>
                    @endforeach
                @else
                    <div class="km-empty">Belum ada data sanitasi — lengkapi lewat Edit Anak (kartu Kesmas &amp; lingkungan)</div>
                    <p class="km-belum">{{ $fmt($layanan['sasaran']) }} anak belum diisi untuk setiap indikator sanitasi</p>
                @endif
            </div>

        </div>
    </section><!-- /layanan -->

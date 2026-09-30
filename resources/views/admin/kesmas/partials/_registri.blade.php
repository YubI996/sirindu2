{{-- Registri longitudinal — data dimuat JS dari admin.kesmas.registri (20/halaman) supaya balik halaman tak menghitung ulang agregat. --}}
<section class="km-wide" id="registri" data-blok="registri" aria-label="Registri longitudinal balita"
    data-endpoint="{{ route('admin.kesmas.registri') }}"
    data-filters="{{ json_encode(array_merge(['tahun' => $periode->tahun(), 'periode' => $periode->kode()], $filters)) }}">
    <div class="km-reg-head">
        <div>
            <div class="im-h" style="margin-bottom:.1rem;"><h2>Registri Longitudinal &amp; Pelayanan Balita</h2></div>
            <div class="im-card__sub">Antropometri terakhir dalam periode, status IDL/IBL saat ini, dan catatan petugas</div>
        </div>
        <span class="km-pill" id="registriTotal">Memuat…</span>
        <div class="km-reg-tools">
            <label for="registriCari" class="sr-only">Cari nama, NIK, atau nama orang tua</label>
            <input type="search" id="registriCari" placeholder="Cari nama, NIK, atau orang tua" autocomplete="off" maxlength="100">
            <label for="registriGizi" class="sr-only">Status gizi</label>
            <select id="registriGizi">
                <option value="semua">Semua status gizi</option>
                <option value="normal">Gizi baik</option>
                <option value="stunted">Pendek (stunting)</option>
                <option value="underweight">BB kurang (underweight)</option>
                <option value="wasted">Gizi kurang (wasting)</option>
                <option value="perhatian">Perlu perhatian (BB tidak naik / BB/U &lt; −2 SD)</option>
            </select>
        </div>
    </div>
    <div class="km-table-wrap">
        <table class="km-table">
            <thead>
                <tr>
                    <th scope="col">No</th>
                    <th scope="col">Nama balita &amp; NIK</th>
                    <th scope="col">Orang tua</th>
                    <th scope="col">Wilayah &amp; posyandu</th>
                    <th scope="col">Antropometri terakhir</th>
                    <th scope="col">IDL</th>
                    <th scope="col">IBL</th>
                    <th scope="col">Catatan</th>
                    <th scope="col"><span class="sr-only">Aksi</span></th>
                </tr>
            </thead>
            <tbody id="registriBody" aria-live="polite" aria-busy="true">
                @for($i = 0; $i < 5; $i++)
                    <tr class="km-skel"><td colspan="9"><span></span></td></tr>
                @endfor
            </tbody>
        </table>
    </div>
    <div class="km-reg-foot">
        <span id="registriInfo" class="im-card__sub"></span>
        <nav id="registriPag" class="km-pag" aria-label="Halaman registri"></nav>
    </div>
</section><!-- /registri -->

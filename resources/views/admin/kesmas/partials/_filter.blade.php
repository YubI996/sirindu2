{{-- Filter periode & wilayah — submit = reload halaman (agregat server-side). --}}
<form method="GET" action="{{ route('admin.kesmas.dashboard') }}" class="im-filter" id="kmFilter">
    <input type="hidden" name="usia" value="{{ $usia }}" id="filterUsia">
    <div>
        <label for="filterTahun">Tahun</label>
        <select name="tahun" id="filterTahun">
            @foreach($tahunList as $th)
                <option value="{{ $th }}" {{ $periode->tahun() === $th ? 'selected' : '' }}>{{ $th }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="filterPeriode">Periode</label>
        <select name="periode" id="filterPeriode">
            @foreach(['tahun' => 'Setahun penuh', 's1' => 'Semester I (Jan–Jun)', 's2' => 'Semester II (Jul–Des)', 'tw1' => 'Triwulan I (Jan–Mar)', 'tw2' => 'Triwulan II (Apr–Jun)', 'tw3' => 'Triwulan III (Jul–Sep)', 'tw4' => 'Triwulan IV (Okt–Des)'] as $kode => $label)
                <option value="{{ $kode }}" {{ $periode->kode() === $kode ? 'selected' : '' }}>{{ $label }}</option>
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
        <label for="filterPos">Posyandu</label>
        <select name="id_posyandu" id="filterPos">
            <option value="">Semua posyandu</option>
            @foreach($posyanduList as $pos)
                <option value="{{ $pos->id }}" {{ ($filters['id_posyandu'] ?? null) == $pos->id ? 'selected' : '' }}>{{ $pos->name }}</option>
            @endforeach
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
        <span class="material-symbols-outlined" style="font-size:18px;">filter_alt</span>Terapkan
    </button>
    @if(!empty($filters) || $periode->kode() !== 'tahun' || $periode->tahun() !== (int) now()->year)
        <a href="{{ route('admin.kesmas.dashboard') }}" class="im-btn im-btn--ghost">Reset</a>
    @endif
</form>

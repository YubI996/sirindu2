{{-- Section D3: Pengobatan & Terapi + AFP/Polio + Pemeriksaan Fisik --}}

{{-- Pengobatan (Difteri specific) --}}
<div class="disease-section" data-diseases="DIFTERI_OBS"
     style="{{ in_array(optional($case->jenisKasus ?? null)->kode_penyakit ?? '', ['DIFTERI_OBS']) ? '' : 'display:none;' }}">
<h6 class="section-subtitle"><i class="fa fa-pills"></i> Pengobatan / Terapi (Difteri)</h6>
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Jenis Antibiotik yang diberikan</label>
            <input type="text" name="jenis_antibiotik" class="form-control"
                   value="{{ old('jenis_antibiotik', $case->jenis_antibiotik ?? '') }}">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Pemberian Dosis Anti Difteri Serum (ADS)</label>
            <input type="text" name="dosis_ads" class="form-control"
                   value="{{ old('dosis_ads', $case->dosis_ads ?? '') }}">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Tracheostomi</label>
            <select name="tracheostomi" class="form-control">
                <option value="">-- Pilih --</option>
                <option value="ya" {{ old('tracheostomi', $case->tracheostomi ?? '') == 'ya' ? 'selected' : '' }}>Ya</option>
                <option value="tidak" {{ old('tracheostomi', $case->tracheostomi ?? '') == 'tidak' ? 'selected' : '' }}>Tidak</option>
            </select>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Obat Lainnya</label>
            <textarea name="obat_lainnya" class="form-control" rows="1">{{ old('obat_lainnya', $case->obat_lainnya ?? '') }}</textarea>
        </div>
    </div>
</div>
</div>

{{-- AFP/Polio Riwayat Sakit --}}
<div class="disease-section" data-diseases="AFP"
     style="{{ in_array(optional($case->jenisKasus ?? null)->kode_penyakit ?? '', ['AFP']) ? '' : 'display:none;' }}">
<h6 class="section-subtitle"><i class="fa fa-wheelchair"></i> Riwayat Sakit (AFP / Kelumpuhan)</h6>
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Apakah kelemahan/kelumpuhan sifatnya akut (1-14 hari)?</label>
            <select name="kelumpuhan_akut" class="form-control">
                <option value="">-- Pilih --</option>
                <option value="ya" {{ old('kelumpuhan_akut', $case->kelumpuhan_akut ?? '') == 'ya' ? 'selected' : '' }}>Ya</option>
                <option value="tidak" {{ old('kelumpuhan_akut', $case->kelumpuhan_akut ?? '') == 'tidak' ? 'selected' : '' }}>Tidak</option>
            </select>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Apakah kelemahan/kelumpuhan sifatnya layuh (flaccid)?</label>
            <select name="kelumpuhan_flaccid" class="form-control">
                <option value="">-- Pilih --</option>
                <option value="ya" {{ old('kelumpuhan_flaccid', $case->kelumpuhan_flaccid ?? '') == 'ya' ? 'selected' : '' }}>Ya</option>
                <option value="tidak" {{ old('kelumpuhan_flaccid', $case->kelumpuhan_flaccid ?? '') == 'tidak' ? 'selected' : '' }}>Tidak</option>
            </select>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Apakah kelemahan/kelumpuhan disebabkan rudapaksa?</label>
            <select name="kelumpuhan_rudapaksa" class="form-control">
                <option value="">-- Pilih --</option>
                <option value="ya" {{ old('kelumpuhan_rudapaksa', $case->kelumpuhan_rudapaksa ?? '') == 'ya' ? 'selected' : '' }}>Ya</option>
                <option value="tidak" {{ old('kelumpuhan_rudapaksa', $case->kelumpuhan_rudapaksa ?? '') == 'tidak' ? 'selected' : '' }}>Tidak</option>
            </select>
        </div>
    </div>
</div>

<hr>

{{-- Pemeriksaan Fisik / Gejala Tanda --}}
<h6 class="section-subtitle"><i class="fa fa-stethoscope"></i> Pemeriksaan Fisik & Gejala/Tanda</h6>
<div class="row">
    <div class="col-md-3">
        <div class="form-group">
            <label>Gejala/Tanda — Tungkai Kanan</label>
            <input type="text" name="tanda_tungkai_kanan" class="form-control"
                   value="{{ old('tanda_tungkai_kanan', $case->tanda_tungkai_kanan ?? '') }}">
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Gejala/Tanda — Tungkai Kiri</label>
            <input type="text" name="tanda_tungkai_kiri" class="form-control"
                   value="{{ old('tanda_tungkai_kiri', $case->tanda_tungkai_kiri ?? '') }}">
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Gejala/Tanda — Lengan Kanan</label>
            <input type="text" name="tanda_lengan_kanan" class="form-control"
                   value="{{ old('tanda_lengan_kanan', $case->tanda_lengan_kanan ?? '') }}">
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Gejala/Tanda — Lengan Kiri</label>
            <input type="text" name="tanda_lengan_kiri" class="form-control"
                   value="{{ old('tanda_lengan_kiri', $case->tanda_lengan_kiri ?? '') }}">
        </div>
    </div>
</div>

{{-- FP-1 kolom "Gangguan rasa raba" per anggota gerak. --}}
<div class="row">
    @foreach ([
        'rasa_raba_tungkai_kanan' => 'Tungkai Kanan',
        'rasa_raba_tungkai_kiri'  => 'Tungkai Kiri',
        'rasa_raba_lengan_kanan'  => 'Lengan Kanan',
        'rasa_raba_lengan_kiri'   => 'Lengan Kiri',
    ] as $field => $label)
    <div class="col-md-3">
        <div class="form-group">
            <label>Gangguan Rasa Raba — {{ $label }}</label>
            <select name="{{ $field }}" class="form-control">
                <option value="">-- Pilih --</option>
                <option value="ya" {{ old($field, $case->{$field} ?? '') == 'ya' ? 'selected' : '' }}>Ya</option>
                <option value="tidak" {{ old($field, $case->{$field} ?? '') == 'tidak' ? 'selected' : '' }}>Tidak</option>
            </select>
        </div>
    </div>
    @endforeach
</div>

<div class="row">
    <div class="col-md-3">
        <div class="form-group">
            <label>Kekuatan Otot (0-5)</label>
            <input type="number" name="kekuatan_otot" class="form-control" min="0" max="5"
                   value="{{ old('kekuatan_otot', $case->kekuatan_otot ?? '') }}">
            <small class="form-text text-muted">Skala 0 = paralisis total, 5 = kekuatan normal</small>
        </div>
    </div>
    <div class="col-md-5">
        <div class="form-group">
            <label>Lokasi lainnya yang mengalami kelemahan/lumpuh</label>
            <input type="text" name="lokasi_kelemahan_lain" class="form-control"
                   value="{{ old('lokasi_kelemahan_lain', $case->lokasi_kelemahan_lain ?? '') }}"
                   placeholder="Opsional">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Tanda penyakit yang dapat diobservasi</label>
            <textarea name="tanda_penyakit_observasi" class="form-control" rows="1">{{ old('tanda_penyakit_observasi', $case->tanda_penyakit_observasi ?? '') }}</textarea>
        </div>
    </div>
</div>

<hr>

{{-- Kontak Polio --}}
<h6 class="section-subtitle"><i class="fa fa-syringe"></i> Kontak Polio</h6>
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Dalam 75 hari terakhir sebelum sakit, apakah penderita pernah berkontak dengan anak yang baru mendapat imunisasi polio oral?</label>
            <select name="kontak_polio_oral" class="form-control">
                <option value="">-- Pilih --</option>
                <option value="ya" {{ old('kontak_polio_oral', $case->kontak_polio_oral ?? '') == 'ya' ? 'selected' : '' }}>Ya</option>
                <option value="tidak" {{ old('kontak_polio_oral', $case->kontak_polio_oral ?? '') == 'tidak' ? 'selected' : '' }}>Tidak</option>
                <option value="tidak_tahu" {{ old('kontak_polio_oral', $case->kontak_polio_oral ?? '') == 'tidak_tahu' ? 'selected' : '' }}>Tidak Tahu</option>
            </select>
        </div>
    </div>
</div>

<hr>

{{-- Sanitasi (AFP specific) --}}
<h6 class="section-subtitle"><i class="fa fa-toilet"></i> Sanitasi Dasar: Jamban dan Pembuangan Tinja</h6>
<div class="row">
    <div class="col-md-3">
        <div class="form-group">
            <label>Apakah memiliki jamban sendiri?</label>
            <select name="jamban_sendiri" class="form-control">
                <option value="">-- Pilih --</option>
                <option value="ya" {{ old('jamban_sendiri', $case->jamban_sendiri ?? '') == 'ya' ? 'selected' : '' }}>Ya</option>
                <option value="tidak" {{ old('jamban_sendiri', $case->jamban_sendiri ?? '') == 'tidak' ? 'selected' : '' }}>Tidak</option>
            </select>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Saluran pembuangan kedap dan aman?</label>
            <select name="jamban_saluran_kedap" class="form-control">
                <option value="">-- Pilih --</option>
                <option value="ya" {{ old('jamban_saluran_kedap', $case->jamban_saluran_kedap ?? '') == 'ya' ? 'selected' : '' }}>Ya</option>
                <option value="tidak" {{ old('jamban_saluran_kedap', $case->jamban_saluran_kedap ?? '') == 'tidak' ? 'selected' : '' }}>Tidak</option>
            </select>
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-group">
            <label>Jenis Jamban</label>
            {{-- Pilihannya disamakan dengan FP-1 bagian V agar centang di PDF akurat. --}}
            @php $jenisJambanVal = old('jenis_jamban', $case->jenis_jamban ?? ''); @endphp
            <select name="jenis_jamban" class="form-control">
                <option value="">-- Pilih --</option>
                @foreach ([
                    'leher_angsa_septic' => 'Leher angsa dengan septic tank',
                    'cemplung'           => 'Cemplung (tanpa septic tank)',
                    'sungai_kebun_kolam' => 'Di sungai/kebun/kolam',
                    'lainnya'            => 'Lainnya',
                ] as $val => $label)
                <option value="{{ $val }}" {{ $jenisJambanVal === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-group">
            <label>Selalu gunakan jamban?</label>
            <select name="selalu_gunakan_jamban" class="form-control">
                <option value="">-- Pilih --</option>
                <option value="ya" {{ old('selalu_gunakan_jamban', $case->selalu_gunakan_jamban ?? '') == 'ya' ? 'selected' : '' }}>Ya</option>
                <option value="tidak" {{ old('selalu_gunakan_jamban', $case->selalu_gunakan_jamban ?? '') == 'tidak' ? 'selected' : '' }}>Tidak</option>
                <option value="kadang_kadang" {{ old('selalu_gunakan_jamban', $case->selalu_gunakan_jamban ?? '') == 'kadang_kadang' ? 'selected' : '' }}>Kadang-kadang</option>
            </select>
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-group">
            <label>Pembuangan Diapers</label>
            @php $diapersVal = old('pembuangan_diapers', $case->pembuangan_diapers ?? ''); @endphp
            <select name="pembuangan_diapers" class="form-control">
                <option value="">-- Pilih --</option>
                @foreach ([
                    'sampah_tertutup' => 'Sampah tertutup',
                    'sungai_kebun'    => 'Sungai/kebun',
                    'dibakar'         => 'Dibakar',
                    'lainnya'         => 'Lainnya',
                ] as $val => $label)
                <option value="{{ $val }}" {{ $diapersVal === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <small class="form-text text-muted">Jika masih pakai diapers</small>
        </div>
    </div>
</div>
</div>{{-- end AFP disease-section --}}

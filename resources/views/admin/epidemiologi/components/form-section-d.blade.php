{{-- Section D: Clinical Symptoms (17 boolean fields) --}}
<p class="text-muted mb-3">
    <i class="fa fa-info-circle"></i> Centang gejala yang dialami pasien
</p>

<div class="row">
    <div class="col-md-3">
        <div class="custom-control custom-checkbox mb-3">
            <input type="checkbox" class="custom-control-input" id="gejala_demam" name="gejala_demam"
                   {{ old('gejala_demam', $case->gejala_demam ?? false) ? 'checked' : '' }}>
            <label class="custom-control-label" for="gejala_demam">
                <i class="fa fa-thermometer-half text-danger"></i> Demam
            </label>
        </div>
    </div>
    <div class="col-md-3">
        <div class="custom-control custom-checkbox mb-3">
            <input type="checkbox" class="custom-control-input" id="gejala_batuk" name="gejala_batuk"
                   {{ old('gejala_batuk', $case->gejala_batuk ?? false) ? 'checked' : '' }}>
            <label class="custom-control-label" for="gejala_batuk">
                <i class="fa fa-lungs text-info"></i> Batuk
            </label>
        </div>
    </div>
    <div class="col-md-3">
        <div class="custom-control custom-checkbox mb-3">
            <input type="checkbox" class="custom-control-input" id="gejala_pilek" name="gejala_pilek"
                   {{ old('gejala_pilek', $case->gejala_pilek ?? false) ? 'checked' : '' }}>
            <label class="custom-control-label" for="gejala_pilek">
                <i class="fa fa-head-side-cough text-primary"></i> Pilek
            </label>
        </div>
    </div>
    <div class="col-md-3">
        <div class="custom-control custom-checkbox mb-3">
            <input type="checkbox" class="custom-control-input" id="gejala_sakit_kepala" name="gejala_sakit_kepala"
                   {{ old('gejala_sakit_kepala', $case->gejala_sakit_kepala ?? false) ? 'checked' : '' }}>
            <label class="custom-control-label" for="gejala_sakit_kepala">
                <i class="fa fa-head-side-virus text-warning"></i> Sakit Kepala
            </label>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-3">
        <div class="custom-control custom-checkbox mb-3">
            <input type="checkbox" class="custom-control-input" id="gejala_mual" name="gejala_mual"
                   {{ old('gejala_mual', $case->gejala_mual ?? false) ? 'checked' : '' }}>
            <label class="custom-control-label" for="gejala_mual">
                <i class="fa fa-dizzy text-success"></i> Mual
            </label>
        </div>
    </div>
    <div class="col-md-3">
        <div class="custom-control custom-checkbox mb-3">
            <input type="checkbox" class="custom-control-input" id="gejala_muntah" name="gejala_muntah"
                   {{ old('gejala_muntah', $case->gejala_muntah ?? false) ? 'checked' : '' }}>
            <label class="custom-control-label" for="gejala_muntah">
                <i class="fa fa-sad-tear text-danger"></i> Muntah
            </label>
        </div>
    </div>
    <div class="col-md-3">
        <div class="custom-control custom-checkbox mb-3">
            <input type="checkbox" class="custom-control-input" id="gejala_diare" name="gejala_diare"
                   {{ old('gejala_diare', $case->gejala_diare ?? false) ? 'checked' : '' }}>
            <label class="custom-control-label" for="gejala_diare">
                <i class="fa fa-toilet text-warning"></i> Diare
            </label>
        </div>
    </div>
    <div class="col-md-3">
        <div class="custom-control custom-checkbox mb-3">
            <input type="checkbox" class="custom-control-input" id="gejala_ruam" name="gejala_ruam"
                   {{ old('gejala_ruam', $case->gejala_ruam ?? false) ? 'checked' : '' }}>
            <label class="custom-control-label" for="gejala_ruam">
                <i class="fa fa-allergies text-danger"></i> Ruam/Bercak Merah
            </label>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-3">
        <div class="custom-control custom-checkbox mb-3">
            <input type="checkbox" class="custom-control-input" id="gejala_sesak_napas" name="gejala_sesak_napas"
                   {{ old('gejala_sesak_napas', $case->gejala_sesak_napas ?? false) ? 'checked' : '' }}>
            <label class="custom-control-label" for="gejala_sesak_napas">
                <i class="fa fa-wind text-danger"></i> Sesak Napas
            </label>
        </div>
    </div>
    <div class="col-md-3">
        <div class="custom-control custom-checkbox mb-3">
            <input type="checkbox" class="custom-control-input" id="gejala_nyeri_otot" name="gejala_nyeri_otot"
                   {{ old('gejala_nyeri_otot', $case->gejala_nyeri_otot ?? false) ? 'checked' : '' }}>
            <label class="custom-control-label" for="gejala_nyeri_otot">
                <i class="fa fa-dumbbell text-info"></i> Nyeri Otot
            </label>
        </div>
    </div>
    <div class="col-md-3">
        <div class="custom-control custom-checkbox mb-3">
            <input type="checkbox" class="custom-control-input" id="gejala_nyeri_sendi" name="gejala_nyeri_sendi"
                   {{ old('gejala_nyeri_sendi', $case->gejala_nyeri_sendi ?? false) ? 'checked' : '' }}>
            <label class="custom-control-label" for="gejala_nyeri_sendi">
                <i class="fa fa-walking text-warning"></i> Nyeri Sendi
            </label>
        </div>
    </div>
    <div class="col-md-3">
        <div class="custom-control custom-checkbox mb-3">
            <input type="checkbox" class="custom-control-input" id="gejala_lemas" name="gejala_lemas"
                   {{ old('gejala_lemas', $case->gejala_lemas ?? false) ? 'checked' : '' }}>
            <label class="custom-control-label" for="gejala_lemas">
                <i class="fa fa-tired text-secondary"></i> Lemas
            </label>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-3">
        <div class="custom-control custom-checkbox mb-3">
            <input type="checkbox" class="custom-control-input" id="gejala_kehilangan_nafsu_makan" name="gejala_kehilangan_nafsu_makan"
                   {{ old('gejala_kehilangan_nafsu_makan', $case->gejala_kehilangan_nafsu_makan ?? false) ? 'checked' : '' }}>
            <label class="custom-control-label" for="gejala_kehilangan_nafsu_makan">
                <i class="fa fa-utensils text-muted"></i> Hilang Nafsu Makan
            </label>
        </div>
    </div>
    <div class="col-md-3">
        <div class="custom-control custom-checkbox mb-3">
            <input type="checkbox" class="custom-control-input" id="gejala_mata_merah" name="gejala_mata_merah"
                   {{ old('gejala_mata_merah', $case->gejala_mata_merah ?? false) ? 'checked' : '' }}>
            <label class="custom-control-label" for="gejala_mata_merah">
                <i class="fa fa-eye text-danger"></i> Mata Merah
            </label>
        </div>
    </div>
    <div class="col-md-3">
        <div class="custom-control custom-checkbox mb-3">
            <input type="checkbox" class="custom-control-input" id="gejala_pembengkakan_kelenjar" name="gejala_pembengkakan_kelenjar"
                   {{ old('gejala_pembengkakan_kelenjar', $case->gejala_pembengkakan_kelenjar ?? false) ? 'checked' : '' }}>
            <label class="custom-control-label" for="gejala_pembengkakan_kelenjar">
                <i class="fa fa-circle text-info"></i> Pembengkakan Kelenjar
            </label>
        </div>
    </div>
    <div class="col-md-3">
        <div class="custom-control custom-checkbox mb-3">
            <input type="checkbox" class="custom-control-input" id="gejala_kejang" name="gejala_kejang"
                   {{ old('gejala_kejang', $case->gejala_kejang ?? false) ? 'checked' : '' }}>
            <label class="custom-control-label" for="gejala_kejang">
                <i class="fa fa-bolt text-warning"></i> Kejang
            </label>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-3">
        <div class="custom-control custom-checkbox mb-3">
            <input type="checkbox" class="custom-control-input" id="gejala_penurunan_kesadaran" name="gejala_penurunan_kesadaran"
                   {{ old('gejala_penurunan_kesadaran', $case->gejala_penurunan_kesadaran ?? false) ? 'checked' : '' }}>
            <label class="custom-control-label" for="gejala_penurunan_kesadaran">
                <i class="fa fa-brain text-danger"></i> Penurunan Kesadaran
            </label>
        </div>
    </div>
</div>

<div class="alert alert-info mt-3">
    <i class="fa fa-lightbulb"></i> <strong>Tips:</strong> Gejala yang dicentang akan membantu dalam analisis pola penyakit dan diagnosis.
</div>

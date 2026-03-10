{{-- Section D: Clinical Symptoms (expanded with Google Form fields) --}}
<p class="text-muted mb-3">
    <i class="fa fa-info-circle"></i> Centang semua gejala yang dialami pasien sejak onset penyakit.
</p>

<h6 class="section-subtitle"><i class="fa fa-thermometer-half"></i> Gejala Utama</h6>
<div class="check-grid mb-4">
    @php
    $mainSymptoms = [
        ['name' => 'gejala_demam', 'label' => 'Demam', 'icon' => 'fa-thermometer-full'],
        ['name' => 'gejala_batuk', 'label' => 'Batuk', 'icon' => 'fa-head-side-cough'],
        ['name' => 'gejala_pilek', 'label' => 'Pilek', 'icon' => 'fa-head-side-virus'],
        ['name' => 'gejala_sakit_kepala', 'label' => 'Sakit Kepala', 'icon' => 'fa-brain'],
        ['name' => 'gejala_mual', 'label' => 'Mual', 'icon' => 'fa-dizzy'],
        ['name' => 'gejala_muntah', 'label' => 'Muntah', 'icon' => 'fa-procedures'],
        ['name' => 'gejala_diare', 'label' => 'Diare', 'icon' => 'fa-toiletpaper'],
        ['name' => 'gejala_ruam', 'label' => 'Ruam', 'icon' => 'fa-allergies'],
        ['name' => 'gejala_sesak_napas', 'label' => 'Sesak Napas', 'icon' => 'fa-lungs-virus'],
        ['name' => 'gejala_nyeri_otot', 'label' => 'Nyeri Otot', 'icon' => 'fa-running'],
        ['name' => 'gejala_nyeri_sendi', 'label' => 'Nyeri Sendi', 'icon' => 'fa-bone'],
        ['name' => 'gejala_lemas', 'label' => 'Lemas', 'icon' => 'fa-battery-quarter'],
        ['name' => 'gejala_kehilangan_nafsu_makan', 'label' => 'Hilang Nafsu Makan', 'icon' => 'fa-utensils'],
        ['name' => 'gejala_mata_merah', 'label' => 'Mata Merah', 'icon' => 'fa-eye'],
        ['name' => 'gejala_pembengkakan_kelenjar', 'label' => 'Pembengkakan Kelenjar', 'icon' => 'fa-expand-arrows-alt'],
        ['name' => 'gejala_kejang', 'label' => 'Kejang', 'icon' => 'fa-bolt'],
        ['name' => 'gejala_penurunan_kesadaran', 'label' => 'Penurunan Kesadaran', 'icon' => 'fa-bed'],
    ];
    @endphp

    @foreach ($mainSymptoms as $symptom)
    <div class="check-card {{ old($symptom['name'], $case->{$symptom['name']} ?? false) ? 'checked' : '' }}">
        <span class="check-icon"><i class="fas {{ $symptom['icon'] }}"></i></span>
        <input type="hidden" name="{{ $symptom['name'] }}" value="0">
        <input type="checkbox" name="{{ $symptom['name'] }}" value="1" id="{{ $symptom['name'] }}"
               {{ old($symptom['name'], $case->{$symptom['name']} ?? false) ? 'checked' : '' }}>
        <label for="{{ $symptom['name'] }}">{{ $symptom['label'] }}</label>
    </div>
    @endforeach
</div>

{{-- Google Form additional symptoms (Campak/Rubella specific) --}}
<div class="disease-field" data-diseases="CAMPAK_RUBELLA"
     style="{{ in_array(optional($case->jenisKasus ?? null)->kode_penyakit ?? old('_disease_kode', ''), ['CAMPAK_RUBELLA']) ? '' : 'display:none;' }}">
<h6 class="section-subtitle"><i class="fa fa-plus-circle"></i> Gejala Lain (Campak/Rubella)</h6>
<div class="check-grid mb-4">
    @php
    $additionalSymptoms = [
        ['name' => 'gejala_adenopathy', 'label' => 'Adenopathy (Kelenjar Limfa)', 'icon' => 'fa-compress-arrows-alt'],
        ['name' => 'gejala_arthralgia', 'label' => 'Arthralgia (Nyeri Sendi)', 'icon' => 'fa-hand-paper'],
        ['name' => 'gejala_kehamilan', 'label' => 'Kehamilan', 'icon' => 'fa-baby'],
    ];
    @endphp

    @foreach ($additionalSymptoms as $symptom)
    <div class="check-card {{ old($symptom['name'], $case->{$symptom['name']} ?? false) ? 'checked' : '' }}">
        <span class="check-icon"><i class="fas {{ $symptom['icon'] }}"></i></span>
        <input type="hidden" name="{{ $symptom['name'] }}" value="0">
        <input type="checkbox" name="{{ $symptom['name'] }}" value="1" id="{{ $symptom['name'] }}"
               {{ old($symptom['name'], $case->{$symptom['name']} ?? false) ? 'checked' : '' }}>
        <label for="{{ $symptom['name'] }}">{{ $symptom['label'] }}</label>
    </div>
    @endforeach
</div>
</div>

{{-- Disease-specific dates: Campak/Rubella --}}
<div class="disease-field" data-diseases="CAMPAK_RUBELLA"
     style="{{ in_array(optional($case->jenisKasus ?? null)->kode_penyakit ?? '', ['CAMPAK_RUBELLA']) ? '' : 'display:none;' }}">
<h6 class="section-subtitle"><i class="fa fa-calendar-alt"></i> Tanggal Onset Gejala (Campak/Rubella)</h6>
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Tanggal Demam</label>
            <small class="form-text text-muted">Sudah diisi di Section C</small>
        </div>
    </div>
</div>
</div>

{{-- Disease-specific dates: Difteri --}}
<div class="disease-field" data-diseases="DIFTERI_OBS"
     style="{{ in_array(optional($case->jenisKasus ?? null)->kode_penyakit ?? '', ['DIFTERI_OBS']) ? '' : 'display:none;' }}">
<h6 class="section-subtitle"><i class="fa fa-calendar-alt"></i> Tanggal Onset Gejala (Difteri)</h6>
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Tanggal Leher Bengkak</label>
            <input type="date" name="tanggal_leher_bengkak" class="form-control"
                   value="{{ old('tanggal_leher_bengkak', isset($case) && $case->tanggal_leher_bengkak ? $case->tanggal_leher_bengkak->format('Y-m-d') : '') }}"
                   max="{{ date('Y-m-d') }}">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Tanggal Sesak Napas</label>
            <input type="date" name="tanggal_sesak_nafas" class="form-control"
                   value="{{ old('tanggal_sesak_nafas', isset($case) && $case->tanggal_sesak_nafas ? $case->tanggal_sesak_nafas->format('Y-m-d') : '') }}"
                   max="{{ date('Y-m-d') }}">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Tanggal Pseudomembran</label>
            <input type="date" name="tanggal_pseudomembran" class="form-control"
                   value="{{ old('tanggal_pseudomembran', isset($case) && $case->tanggal_pseudomembran ? $case->tanggal_pseudomembran->format('Y-m-d') : '') }}"
                   max="{{ date('Y-m-d') }}">
        </div>
    </div>
</div>
</div>

{{-- Disease-specific dates: Pertusis --}}
<div class="disease-field" data-diseases="PERTUSIS"
     style="{{ in_array(optional($case->jenisKasus ?? null)->kode_penyakit ?? '', ['PERTUSIS']) ? '' : 'display:none;' }}">
<h6 class="section-subtitle"><i class="fa fa-calendar-alt"></i> Tanggal Onset Gejala (Pertusis)</h6>
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Tanggal Apnea</label>
            <input type="date" name="tanggal_apnea" class="form-control"
                   value="{{ old('tanggal_apnea', isset($case) && $case->tanggal_apnea ? $case->tanggal_apnea->format('Y-m-d') : '') }}"
                   max="{{ date('Y-m-d') }}">
        </div>
    </div>
</div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            <label>Gejala Lainnya (Teks Bebas)</label>
            <textarea name="gejala_lainnya" class="form-control" rows="2"
                      placeholder="Tuliskan gejala lain yang tidak tercantum di atas...">{{ old('gejala_lainnya', $case->gejala_lainnya ?? '') }}</textarea>
        </div>
    </div>
</div>

@push('js')
<script>
$(document).ready(function() {
    // Toggle checked class on symptom item click
    $('.check-card').on('click', function(e) {
        if ($(e.target).is('input[type="checkbox"]') || $(e.target).is('label')) return;
        var cb = $(this).find('input[type="checkbox"]');
        cb.prop('checked', !cb.prop('checked')).trigger('change');
    });

    $('input[type="checkbox"]').on('change', function() {
        $(this).closest('.check-card').toggleClass('checked', this.checked);
    });
});
</script>
@endpush

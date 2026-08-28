{{-- Section D: Clinical Symptoms (expanded with Google Form fields) --}}
<p class="text-muted mb-3">
    <i class="fa fa-info-circle"></i> Centang semua gejala yang dialami pasien sejak onset penyakit. Tanggal onset akan muncul setelah dicentang.
</p>

<h6 class="section-subtitle"><i class="fa fa-thermometer-half"></i> Gejala Utama</h6>
<div class="check-grid mb-4">
    @php
    $mainSymptoms = [
        ['name' => 'gejala_demam',                  'label' => 'Demam',                    'icon' => 'fa-thermometer-full',    'date_field' => 'tanggal_demam'],
        ['name' => 'gejala_batuk',                  'label' => 'Batuk',                    'icon' => 'fa-head-side-cough',     'date_field' => 'tanggal_batuk'],
        ['name' => 'gejala_pilek',                  'label' => 'Pilek',                    'icon' => 'fa-head-side-virus',     'date_field' => 'tanggal_pilek'],
        ['name' => 'gejala_sakit_kepala',            'label' => 'Sakit Kepala',             'icon' => 'fa-brain',               'date_field' => 'tanggal_sakit_kepala'],
        ['name' => 'gejala_mual',                   'label' => 'Mual',                     'icon' => 'fa-dizzy',               'date_field' => 'tanggal_mual'],
        ['name' => 'gejala_muntah',                 'label' => 'Muntah',                   'icon' => 'fa-procedures',          'date_field' => 'tanggal_muntah'],
        ['name' => 'gejala_diare',                  'label' => 'Diare',                    'icon' => 'fa-toiletpaper',         'date_field' => 'tanggal_diare'],
        ['name' => 'gejala_ruam',                   'label' => 'Ruam',                     'icon' => 'fa-allergies',           'date_field' => 'tanggal_ruam'],
        ['name' => 'gejala_sesak_napas',             'label' => 'Sesak Napas',              'icon' => 'fa-lungs-virus',         'date_field' => 'tanggal_sesak_nafas'],
        ['name' => 'gejala_nyeri_otot',              'label' => 'Nyeri Otot',               'icon' => 'fa-running',             'date_field' => 'tanggal_nyeri_otot'],
        ['name' => 'gejala_nyeri_sendi',             'label' => 'Nyeri Sendi',              'icon' => 'fa-bone',                'date_field' => 'tanggal_nyeri_sendi'],
        ['name' => 'gejala_lemas',                  'label' => 'Lemas',                    'icon' => 'fa-battery-quarter',     'date_field' => 'tanggal_lemas'],
        ['name' => 'gejala_kehilangan_nafsu_makan',  'label' => 'Hilang Nafsu Makan',       'icon' => 'fa-utensils',            'date_field' => 'tanggal_kehilangan_nafsu_makan'],
        ['name' => 'gejala_mata_merah',              'label' => 'Mata Merah',               'icon' => 'fa-eye',                 'date_field' => 'tanggal_mata_merah'],
        ['name' => 'gejala_pembengkakan_kelenjar',   'label' => 'Pembengkakan Kelenjar',    'icon' => 'fa-expand-arrows-alt',   'date_field' => 'tanggal_pembengkakan_kelenjar'],
        ['name' => 'gejala_kejang',                 'label' => 'Kejang',                   'icon' => 'fa-bolt',                'date_field' => 'tanggal_kejang'],
        ['name' => 'gejala_penurunan_kesadaran',     'label' => 'Penurunan Kesadaran',      'icon' => 'fa-bed',                 'date_field' => 'tanggal_penurunan_kesadaran'],
        ['name' => 'gejala_pseudomembran',           'label' => 'Pseudomembran',            'icon' => 'fa-layer-group',         'date_field' => 'tanggal_pseudomembran'],
        ['name' => 'gejala_leher_bengkak',           'label' => 'Leher Bengkak',            'icon' => 'fa-arrows-alt-v',        'date_field' => 'tanggal_leher_bengkak'],
        ['name' => 'gejala_apnea',                  'label' => 'Apnea',                    'icon' => 'fa-wind',                'date_field' => 'tanggal_apnea'],
        ['name' => 'gejala_sakit_tenggorokan',       'label' => 'Sakit Tenggorokan',        'icon' => 'fa-comment-medical',     'date_field' => 'tanggal_sakit_tenggorokan'],
        ['name' => 'gejala_batuk_rejan',             'label' => 'Batuk Rejan',              'icon' => 'fa-lungs',               'date_field' => 'tanggal_batuk_rejan'],
    ];
    @endphp

    @foreach ($mainSymptoms as $symptom)
    @php $isChecked = old($symptom['name'], $case->{$symptom['name']} ?? false); @endphp
    <div class="check-card {{ $isChecked ? 'checked' : '' }}">
        <div class="check-card-top">
            <span class="check-icon"><i class="fas {{ $symptom['icon'] }}"></i></span>
            <input type="hidden" name="{{ $symptom['name'] }}" value="0">
            <input type="checkbox" name="{{ $symptom['name'] }}" value="1" id="{{ $symptom['name'] }}"
                   {{ $isChecked ? 'checked' : '' }}>
            <label for="{{ $symptom['name'] }}">{{ $symptom['label'] }}</label>
        </div>
        <div class="symptom-date-wrap" style="{{ $isChecked ? '' : 'display:none;' }}">
            <input type="date" name="{{ $symptom['date_field'] }}" class="form-control symptom-date"
                   value="{{ old($symptom['date_field'], isset($case) && $case->{$symptom['date_field']} ? $case->{$symptom['date_field']}->format('Y-m-d') : '') }}"
                   max="{{ date('Y-m-d') }}"
                   placeholder="Tanggal onset">
        </div>
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
        <div class="check-card-top">
            <span class="check-icon"><i class="fas {{ $symptom['icon'] }}"></i></span>
            <input type="hidden" name="{{ $symptom['name'] }}" value="0">
            <input type="checkbox" name="{{ $symptom['name'] }}" value="1" id="{{ $symptom['name'] }}"
                   {{ old($symptom['name'], $case->{$symptom['name']} ?? false) ? 'checked' : '' }}>
            <label for="{{ $symptom['name'] }}">{{ $symptom['label'] }}</label>
        </div>
    </div>
    @endforeach
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

<hr class="my-3">

<h6 class="section-subtitle"><i class="fa fa-camera"></i> Foto Dokumentasi Gejala</h6>

{{-- Foto 1 --}}
<div class="row mb-3">
    <div class="col-md-8">
        <div class="form-group mb-0">
            <label for="foto_dokumentasi" class="form-label">
                Foto 1 <span class="text-muted fw-normal">(opsional)</span>
            </label>
            <input type="file" class="form-control @error('foto_dokumentasi') is-invalid @enderror"
                   id="foto_dokumentasi" name="foto_dokumentasi"
                   accept="image/jpeg,image/png">
            <div class="form-text text-muted">Format: JPG atau PNG. Maksimal 2 MB.</div>
            @error('foto_dokumentasi')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-4" id="foto-new-preview-wrap" style="display:none;">
        <label class="form-label">Preview</label>
        <img id="foto-new-preview" src="#" alt="Preview"
             class="img-fluid rounded border" style="max-height:160px; object-fit:contain;">
    </div>
</div>

@if (!empty($case->foto_dokumentasi ?? null))
<div class="row mb-3" id="foto-existing-wrap">
    <div class="col-md-12">
        <label class="form-label d-block">Foto 1 Tersimpan</label>
        <div class="d-flex align-items-start gap-3">
            <img src="{{ route('admin.epidemiologi.foto', [$case->id, 1]) }}"
                 alt="Foto dokumentasi 1" class="img-fluid rounded border"
                 style="max-height:180px; object-fit:contain;">
            <div>
                <p class="text-muted small mb-2">Upload foto baru untuk mengganti foto ini.</p>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="hapus_foto_dokumentasi"
                           id="hapus_foto_dokumentasi" value="1">
                    <label class="form-check-label text-danger" for="hapus_foto_dokumentasi">
                        <i class="fa fa-trash"></i> Hapus foto ini
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Foto 2 --}}
<div class="row mb-3">
    <div class="col-md-8">
        <div class="form-group mb-0">
            <label for="foto_dokumentasi_2" class="form-label">
                Foto 2 <span class="text-muted fw-normal">(opsional)</span>
            </label>
            <input type="file" class="form-control @error('foto_dokumentasi_2') is-invalid @enderror"
                   id="foto_dokumentasi_2" name="foto_dokumentasi_2"
                   accept="image/jpeg,image/png">
            <div class="form-text text-muted">Format: JPG atau PNG. Maksimal 2 MB.</div>
            @error('foto_dokumentasi_2')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-4" id="foto2-new-preview-wrap" style="display:none;">
        <label class="form-label">Preview</label>
        <img id="foto2-new-preview" src="#" alt="Preview"
             class="img-fluid rounded border" style="max-height:160px; object-fit:contain;">
    </div>
</div>

@if (!empty($case->foto_dokumentasi_2 ?? null))
<div class="row mb-3" id="foto2-existing-wrap">
    <div class="col-md-12">
        <label class="form-label d-block">Foto 2 Tersimpan</label>
        <div class="d-flex align-items-start gap-3">
            <img src="{{ route('admin.epidemiologi.foto', [$case->id, 2]) }}"
                 alt="Foto dokumentasi 2" class="img-fluid rounded border"
                 style="max-height:180px; object-fit:contain;">
            <div>
                <p class="text-muted small mb-2">Upload foto baru untuk mengganti foto ini.</p>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="hapus_foto_dokumentasi_2"
                           id="hapus_foto_dokumentasi_2" value="1">
                    <label class="form-check-label text-danger" for="hapus_foto_dokumentasi_2">
                        <i class="fa fa-trash"></i> Hapus foto ini
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

@push('js')
<script>
$(document).ready(function() {
    // Toggle checked + date field on card click (not on date input itself)
    $('.check-card').on('click', function(e) {
        if ($(e.target).closest('.symptom-date-wrap').length) return;
        if ($(e.target).is('input[type="checkbox"]') || $(e.target).is('label')) return;
        var cb = $(this).find('input[type="checkbox"]');
        cb.prop('checked', !cb.prop('checked')).trigger('change');
    });

    $('input[type="checkbox"]').on('change', function() {
        var $card = $(this).closest('.check-card');
        var checked = this.checked;
        $card.toggleClass('checked', checked);
        var $dateWrap = $card.find('.symptom-date-wrap');
        if ($dateWrap.length) {
            $dateWrap.toggle(checked);
            if (!checked) {
                $dateWrap.find('input[type="date"]').val('');
            }
        }
    });

    // Foto dokumentasi: live preview + client-side size guard
    function setupFotoPreview(inputId, previewWrapId, previewImgId) {
        $('#' + inputId).on('change', function() {
            var file = this.files[0];
            if (!file) {
                $('#' + previewWrapId).hide();
                return;
            }
            if (file.size > 2 * 1024 * 1024) {
                alert('Ukuran file melebihi 2 MB. Silakan pilih file yang lebih kecil.');
                $(this).val('');
                $('#' + previewWrapId).hide();
                return;
            }
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#' + previewImgId).attr('src', e.target.result);
                $('#' + previewWrapId).show();
            };
            reader.readAsDataURL(file);
        });
    }
    setupFotoPreview('foto_dokumentasi',   'foto-new-preview-wrap',  'foto-new-preview');
    setupFotoPreview('foto_dokumentasi_2', 'foto2-new-preview-wrap', 'foto2-new-preview');
});
</script>
@endpush

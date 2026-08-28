{{-- Section D2: Komplikasi (Campak/Rubella only) --}}
<div class="disease-section" data-diseases="CAMPAK_RUBELLA"
     style="{{ in_array(optional($case->jenisKasus ?? null)->kode_penyakit ?? '', ['CAMPAK_RUBELLA']) ? '' : 'display:none;' }}">
<p class="text-muted mb-3">
    <i class="fa fa-info-circle"></i> Centang semua komplikasi yang dialami pasien.
</p>

<div class="check-grid mb-4">
    @php
    $komplikasiList = [
        ['name' => 'komplikasi_diare', 'label' => 'Diare', 'icon' => 'fa-toilet'],
        ['name' => 'komplikasi_kebutaan', 'label' => 'Kebutaan', 'icon' => 'fa-eye-slash'],
        ['name' => 'komplikasi_pneumonia', 'label' => 'Pneumonia', 'icon' => 'fa-lungs'],
        ['name' => 'komplikasi_malnutrisi', 'label' => 'Malnutrisi', 'icon' => 'fa-weight'],
        ['name' => 'komplikasi_bronchopneumonia', 'label' => 'Bronchopneumonia', 'icon' => 'fa-lungs-virus'],
        ['name' => 'komplikasi_otitis_media', 'label' => 'Otitis Media', 'icon' => 'fa-deaf'],
        ['name' => 'komplikasi_encephalitis', 'label' => 'Encephalitis', 'icon' => 'fa-brain'],
        ['name' => 'komplikasi_ulkus_mukosa_mulut', 'label' => 'Ulkus Mukosa Mulut', 'icon' => 'fa-teeth'],
    ];
    @endphp

    @foreach ($komplikasiList as $item)
    <div class="check-card check-danger {{ old($item['name'], $case->{$item['name']} ?? false) ? 'checked' : '' }}">
        <span class="check-icon"><i class="fas {{ $item['icon'] }}"></i></span>
        <input type="hidden" name="{{ $item['name'] }}" value="0">
        <input type="checkbox" name="{{ $item['name'] }}" value="1" id="{{ $item['name'] }}"
               {{ old($item['name'], $case->{$item['name']} ?? false) ? 'checked' : '' }}>
        <label for="{{ $item['name'] }}">{{ $item['label'] }}</label>
    </div>
    @endforeach
</div>

</div>

{{-- Vitamin A & Status Gizi — Campak/Rubella DAN Difteri.
     DIF-1 no.5 "Status Gizi" diminta klien tersedia saat penyakit Difteri dipilih
     (reviu Agustus 2026: "Minta tambahan pertanyaan bag A, jika di pilih difteri").
     BB/TB dipakai DIF-1 no.5-6. --}}
<div class="disease-section" data-diseases="CAMPAK_RUBELLA,DIFTERI_OBS"
     style="{{ in_array(optional($case->jenisKasus ?? null)->kode_penyakit ?? '', ['CAMPAK_RUBELLA', 'DIFTERI_OBS']) ? '' : 'display:none;' }}">
<h6 class="section-subtitle"><i class="fa fa-capsules"></i> Vitamin A & Status Gizi</h6>
<div class="row">
    <div class="col-md-3 disease-field" data-diseases="CAMPAK_RUBELLA"
         style="{{ (optional($case->jenisKasus ?? null)->kode_penyakit ?? '') === 'CAMPAK_RUBELLA' ? '' : 'display:none;' }}">
        <div class="form-group">
            <label>Apakah diberikan Vitamin A?</label>
            <select name="vitamin_a" class="form-control">
                <option value="">-- Pilih --</option>
                <option value="ya" {{ old('vitamin_a', $case->vitamin_a ?? '') == 'ya' ? 'selected' : '' }}>Ya</option>
                <option value="tidak" {{ old('vitamin_a', $case->vitamin_a ?? '') == 'tidak' ? 'selected' : '' }}>Tidak</option>
                <option value="tidak_tahu" {{ old('vitamin_a', $case->vitamin_a ?? '') == 'tidak_tahu' ? 'selected' : '' }}>Tidak Tahu</option>
            </select>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Berat Badan (Kg)</label>
            <input type="number" name="berat_badan" class="form-control" step="0.1" min="0"
                   value="{{ old('berat_badan', $case->berat_badan ?? '') }}">
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Tinggi Badan (CM)</label>
            <input type="number" name="tinggi_badan" class="form-control" step="0.1" min="0"
                   value="{{ old('tinggi_badan', $case->tinggi_badan ?? '') }}">
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Status Gizi</label>
            <select name="status_gizi" class="form-control">
                <option value="">-- Pilih --</option>
                <option value="baik" {{ old('status_gizi', $case->status_gizi ?? '') == 'baik' ? 'selected' : '' }}>Baik</option>
                <option value="kurang" {{ old('status_gizi', $case->status_gizi ?? '') == 'kurang' ? 'selected' : '' }}>Kurang</option>
                <option value="buruk" {{ old('status_gizi', $case->status_gizi ?? '') == 'buruk' ? 'selected' : '' }}>Buruk</option>
                <option value="lebih" {{ old('status_gizi', $case->status_gizi ?? '') == 'lebih' ? 'selected' : '' }}>Lebih</option>
            </select>
        </div>
    </div>
</div>

@push('js')
<script>
$(document).ready(function() {
    // Toggle checked class on komplikasi item click
    $('.check-card.check-danger').on('click', function(e) {
        if ($(e.target).is('input[type="checkbox"]') || $(e.target).is('label')) return;
        var cb = $(this).find('input[type="checkbox"]');
        cb.prop('checked', !cb.prop('checked')).trigger('change');
    });

    $('.check-card.check-danger input[type="checkbox"]').on('change', function() {
        $(this).closest('.check-card').toggleClass('checked', this.checked);
    });
});
</script>
@endpush
</div>

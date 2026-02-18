{{-- Section D: Gejala Klinis --}}
@php
$gejalaList = [
    'gejala_demam'               => ['Demam',               'fa-thermometer-half'],
    'gejala_ruam'                => ['Ruam Kulit',          'fa-allergies'],
    'gejala_batuk'               => ['Batuk',               'fa-lungs'],
    'gejala_pilek'               => ['Pilek',               'fa-head-side-cough'],
    'gejala_konjungtivitis'      => ['Konjungtivitis',      'fa-eye'],
    'gejala_sesak_napas'         => ['Sesak Napas',         'fa-wind'],
    'gejala_nyeri_tenggorokan'   => ['Nyeri Tenggorokan',   'fa-comment-medical'],
    'gejala_membran_tenggorokan' => ['Membran Tenggorokan', 'fa-notes-medical'],
    'gejala_kejang'              => ['Kejang',              'fa-bolt'],
    'gejala_lumpuh_layuh'        => ['Lumpuh Layuh (AFP)',  'fa-wheelchair'],
    'gejala_kaku_rahang'         => ['Kaku Rahang',         'fa-teeth'],
    'gejala_spasme'              => ['Spasme Otot',         'fa-dumbbell'],
    'gejala_tali_pusat'          => ['Infeksi Tali Pusat',  'fa-baby'],
    'gejala_diare'               => ['Diare',               'fa-toilet'],
    'gejala_muntah'              => ['Muntah',              'fa-dizzy'],
    'gejala_pendarahan'          => ['Pendarahan',          'fa-tint'],
    'gejala_nyeri_sendi'         => ['Nyeri Sendi',         'fa-bone'],
];
@endphp
<div class="row">
    @foreach($gejalaList as $field => [$label, $icon])
    <div class="col-md-3 col-6 mb-2">
        <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" name="{{ $field }}" id="{{ $field }}" value="1"
                {{ old($field, isset($case) && $case->$field ? '1' : '') == '1' ? 'checked' : '' }}>
            <label class="custom-control-label" for="{{ $field }}">
                <i class="fa {{ $icon }} mr-1 text-muted"></i>{{ $label }}
            </label>
        </div>
    </div>
    @endforeach
</div>
<div class="row mt-2">
    <div class="col-md-3">
        <div class="form-group">
            <label>Suhu Tubuh (°C)</label>
            <input type="number" name="suhu_tubuh" class="form-control" step="0.1" min="30" max="45"
                value="{{ old('suhu_tubuh', $case->suhu_tubuh ?? '') }}" placeholder="cth: 38.5">
        </div>
    </div>
    <div class="col-md-9">
        <div class="form-group">
            <label>Gejala Lainnya</label>
            <input type="text" name="gejala_lainnya" class="form-control"
                value="{{ old('gejala_lainnya', $case->gejala_lainnya ?? '') }}" placeholder="Gejala lain yang tidak tercantum di atas">
        </div>
    </div>
</div>

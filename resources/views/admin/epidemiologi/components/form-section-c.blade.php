{{-- Section C: Case Data (expanded with tanggal_demam) --}}
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Jenis Penyakit <span class="text-danger">*</span></label>
            @if($diseases->isEmpty())
                <select name="id_jenis_kasus" id="id_jenis_kasus" class="form-control" disabled>
                    <option value="">-- Tidak ada jenis penyakit yang aktif --</option>
                </select>
                <small class="form-text text-danger">
                    <i class="fa fa-exclamation-circle" aria-hidden="true"></i>
                    Semua jenis penyakit sedang nonaktif. Hubungi superadmin untuk mengaktifkan data master penyakit.
                </small>
            @else
                <select name="id_jenis_kasus" id="id_jenis_kasus" class="form-control" required>
                    <option value="">-- Pilih Jenis Penyakit --</option>
                    @foreach ($diseases as $disease)
                        <option value="{{ $disease->id }}" data-nama="{{ $disease->nama_penyakit }}" data-kode="{{ $disease->kode_penyakit ?? '' }}"
                            {{ old('id_jenis_kasus', $case->id_jenis_kasus ?? '') == $disease->id ? 'selected' : '' }}>
                            {{ $disease->nama_penyakit }} ({{ $disease->kode_penyakit ?? '-' }})
                        </option>
                    @endforeach
                </select>
            @endif
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Kode ICD-10</label>
            <input type="text" name="kode_icd10" class="form-control"
                   value="{{ old('kode_icd10', $case->kode_icd10 ?? '') }}" maxlength="10">
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Diagnosis</label>
            <input type="text" name="diagnosis" class="form-control"
                   value="{{ old('diagnosis', $case->diagnosis ?? '') }}">
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-3">
        <div class="form-group">
            <label>Tanggal Demam</label>
            <input type="date" name="tanggal_demam" class="form-control"
                   value="{{ old('tanggal_demam', isset($case) && $case->tanggal_demam ? $case->tanggal_demam->format('Y-m-d') : '') }}"
                   max="{{ date('Y-m-d') }}">
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Tanggal Onset <span class="text-danger">*</span></label>
            <input type="date" name="tanggal_onset" id="tanggal_onset" class="form-control"
                   value="{{ old('tanggal_onset', isset($case) ? $case->tanggal_onset->format('Y-m-d') : '') }}"
                   max="{{ date('Y-m-d') }}" required>
            <small class="form-text text-muted">Ruam/Sakit Tenggorok/Lumpuh/Batuk terus-menerus</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Tanggal Konsultasi <span class="text-danger">*</span></label>
            <input type="date" name="tanggal_konsultasi" class="form-control"
                   value="{{ old('tanggal_konsultasi', isset($case) ? $case->tanggal_konsultasi->format('Y-m-d') : '') }}"
                   max="{{ date('Y-m-d') }}" required>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Tanggal Lapor</label>
            <input type="date" name="tanggal_lapor" class="form-control"
                   value="{{ old('tanggal_lapor', isset($case) && $case->tanggal_lapor ? $case->tanggal_lapor->format('Y-m-d') : date('Y-m-d')) }}"
                   max="{{ date('Y-m-d') }}">
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Sumber Penularan</label>
            <select name="sumber_penularan" class="form-control">
                <option value="">-- Pilih --</option>
                <option value="lokal" {{ old('sumber_penularan', $case->sumber_penularan ?? '') == 'lokal' ? 'selected' : '' }}>Lokal</option>
                <option value="import" {{ old('sumber_penularan', $case->sumber_penularan ?? '') == 'import' ? 'selected' : '' }}>Import</option>
                <option value="unknown" {{ old('sumber_penularan', $case->sumber_penularan ?? '') == 'unknown' ? 'selected' : '' }}>Tidak Diketahui</option>
            </select>
        </div>
    </div>
    <div class="col-md-8">
        <div class="form-group">
            <label>Lokasi Penularan</label>
            <textarea name="lokasi_penularan" class="form-control" rows="2"
                      placeholder="Tuliskan lokasi penularan (sekolah, tempat kerja, dll)">{{ old('lokasi_penularan', $case->lokasi_penularan ?? '') }}</textarea>
            <small class="form-text text-muted">Isi bebas: nama sekolah, tempat kerja, rumah tangga, dll.</small>
        </div>
    </div>
</div>

@push('js')
<script>
$(document).ready(function() {
    // Date validations
    $('#tanggal_onset').on('change', function() {
        var onsetDate = $(this).val();
        $('input[name="tanggal_konsultasi"]').attr('min', onsetDate);
    });

    // Disease-conditional show/hide sections & fields
    $('#id_jenis_kasus').on('change', function() {
        var kode = $(this).find('option:selected').data('kode') || '';

        // Hide all disease-specific sections & fields
        $('.disease-section, .disease-field').hide();

        if (kode) {
            // Show sections/fields matching current disease code
            $('.disease-section, .disease-field').each(function() {
                var diseases = $(this).data('diseases') || '';
                if (diseases === 'ALL' || diseases.toString().split(',').indexOf(kode) !== -1) {
                    $(this).show();
                }
            });
        }

        // Always show sections marked for ALL diseases
        $('.disease-section[data-diseases="ALL"], .disease-field[data-diseases="ALL"]').show();
    }).trigger('change'); // trigger on load for edit mode
});
</script>
@endpush

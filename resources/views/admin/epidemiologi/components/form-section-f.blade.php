{{-- Section F: Laboratory (5 fields) --}}
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Status Pemeriksaan Lab</label>
            <select name="status_lab" id="status_lab" class="form-control">
                <option value="belum_diperiksa" {{ old('status_lab', $case->status_lab ?? 'belum_diperiksa') == 'belum_diperiksa' ? 'selected' : '' }}>Belum Diperiksa</option>
                <option value="proses" {{ old('status_lab', $case->status_lab ?? '') == 'proses' ? 'selected' : '' }}>Dalam Proses</option>
                <option value="positif" {{ old('status_lab', $case->status_lab ?? '') == 'positif' ? 'selected' : '' }}>Positif</option>
                <option value="negatif" {{ old('status_lab', $case->status_lab ?? '') == 'negatif' ? 'selected' : '' }}>Negatif</option>
            </select>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Tanggal Pengambilan Spesimen</label>
            <input type="date" name="tanggal_pengambilan_spesimen" id="tanggal_pengambilan_spesimen" class="form-control"
                   value="{{ old('tanggal_pengambilan_spesimen', isset($case->tanggal_pengambilan_spesimen) ? $case->tanggal_pengambilan_spesimen->format('Y-m-d') : '') }}"
                   max="{{ date('Y-m-d') }}">
        </div>
    </div>
</div>

<div class="row" id="lab_details" style="display: none;">
    <div class="col-md-6">
        <div class="form-group">
            <label>Jenis Spesimen</label>
            <input type="text" name="jenis_spesimen" class="form-control"
                   value="{{ old('jenis_spesimen', $case->jenis_spesimen ?? '') }}">
            <small class="form-text text-muted">Contoh: Darah, Serum, Urin, Swab Nasofaring, dll</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Tanggal Hasil Lab</label>
            <input type="date" name="tanggal_hasil_lab" id="tanggal_hasil_lab" class="form-control"
                   value="{{ old('tanggal_hasil_lab', isset($case->tanggal_hasil_lab) ? $case->tanggal_hasil_lab->format('Y-m-d') : '') }}"
                   max="{{ date('Y-m-d') }}">
        </div>
    </div>
</div>

<div class="row" id="lab_results" style="display: none;">
    <div class="col-md-12">
        <div class="form-group">
            <label>Hasil Laboratorium</label>
            <textarea name="hasil_lab" class="form-control" rows="3">{{ old('hasil_lab', $case->hasil_lab ?? '') }}</textarea>
            <small class="form-text text-muted">Detail hasil pemeriksaan laboratorium</small>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function() {
    // Show/hide lab fields based on status
    function toggleLabFields() {
        var status = $('#status_lab').val();

        if (status === 'proses' || status === 'positif' || status === 'negatif') {
            $('#lab_details').show();
        } else {
            $('#lab_details').hide();
        }

        if (status === 'positif' || status === 'negatif') {
            $('#lab_results').show();
        } else {
            $('#lab_results').hide();
        }
    }

    $('#status_lab').on('change', toggleLabFields);
    toggleLabFields(); // Initial check
});
</script>
@endpush

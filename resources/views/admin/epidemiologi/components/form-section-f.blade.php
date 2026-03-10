{{-- Section F: Pemeriksaan Laboratorium (expanded with multiple specimens) --}}
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Status Pemeriksaan Lab</label>
            <select name="status_lab" id="status_lab" class="form-control">
                <option value="">-- Pilih --</option>
                <option value="belum_diperiksa" {{ old('status_lab', $case->status_lab ?? '') == 'belum_diperiksa' ? 'selected' : '' }}>Belum Diperiksa</option>
                <option value="proses" {{ old('status_lab', $case->status_lab ?? '') == 'proses' ? 'selected' : '' }}>Dalam Proses</option>
                <option value="positif" {{ old('status_lab', $case->status_lab ?? '') == 'positif' ? 'selected' : '' }}>Positif</option>
                <option value="negatif" {{ old('status_lab', $case->status_lab ?? '') == 'negatif' ? 'selected' : '' }}>Negatif</option>
            </select>
        </div>
    </div>
</div>

{{-- Spesimen 1 --}}
<h6 class="section-subtitle"><i class="fa fa-vial"></i> Spesimen 1</h6>
<div class="row" id="specimen_fields">
    <div class="col-md-4">
        <div class="form-group">
            <label>Jenis Spesimen 1</label>
            <input type="text" name="jenis_spesimen" class="form-control"
                   value="{{ old('jenis_spesimen', $case->jenis_spesimen ?? '') }}">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Tanggal Pengambilan Spesimen 1</label>
            <input type="date" name="tanggal_pengambilan_spesimen" class="form-control"
                   value="{{ old('tanggal_pengambilan_spesimen', isset($case) && $case->tanggal_pengambilan_spesimen ? $case->tanggal_pengambilan_spesimen->format('Y-m-d') : '') }}"
                   max="{{ date('Y-m-d') }}">
        </div>
    </div>
</div>

{{-- Spesimen 2 --}}
<h6 class="section-subtitle"><i class="fa fa-vial"></i> Spesimen 2</h6>
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Jenis Spesimen 2</label>
            <input type="text" name="jenis_spesimen_2" class="form-control"
                   value="{{ old('jenis_spesimen_2', $case->jenis_spesimen_2 ?? '') }}">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Tanggal Pengambilan Spesimen 2</label>
            <input type="date" name="tanggal_spesimen_2" class="form-control"
                   value="{{ old('tanggal_spesimen_2', isset($case) && $case->tanggal_spesimen_2 ? $case->tanggal_spesimen_2->format('Y-m-d') : '') }}"
                   max="{{ date('Y-m-d') }}">
        </div>
    </div>
</div>

{{-- Spesimen 3 --}}
<h6 class="section-subtitle"><i class="fa fa-vial"></i> Spesimen 3</h6>
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Jenis Spesimen 3</label>
            <input type="text" name="jenis_spesimen_3" class="form-control"
                   value="{{ old('jenis_spesimen_3', $case->jenis_spesimen_3 ?? '') }}">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Tanggal Pengambilan Spesimen 3</label>
            <input type="date" name="tanggal_spesimen_3" class="form-control"
                   value="{{ old('tanggal_spesimen_3', isset($case) && $case->tanggal_spesimen_3 ? $case->tanggal_spesimen_3->format('Y-m-d') : '') }}"
                   max="{{ date('Y-m-d') }}">
        </div>
    </div>
</div>

{{-- Hasil Lab --}}
<div class="row" id="lab_result_fields" style="{{ in_array(old('status_lab', $case->status_lab ?? ''), ['positif', 'negatif']) ? '' : 'display:none;' }}">
    <div class="col-md-4">
        <div class="form-group">
            <label>Tanggal Hasil Lab</label>
            <input type="date" name="tanggal_hasil_lab" class="form-control"
                   value="{{ old('tanggal_hasil_lab', isset($case) && $case->tanggal_hasil_lab ? $case->tanggal_hasil_lab->format('Y-m-d') : '') }}"
                   max="{{ date('Y-m-d') }}">
        </div>
    </div>
    <div class="col-md-8">
        <div class="form-group">
            <label>Hasil Laboratorium</label>
            <textarea name="hasil_lab" class="form-control" rows="2">{{ old('hasil_lab', $case->hasil_lab ?? '') }}</textarea>
        </div>
    </div>
</div>

@push('js')
<script>
$(document).ready(function() {
    // Show/hide lab fields based on status
    $('#status_lab').on('change', function() {
        var val = $(this).val();
        var showSpecimen = (val === 'proses' || val === 'positif' || val === 'negatif');
        var showResult = (val === 'positif' || val === 'negatif');
        $('#lab_result_fields').toggle(showResult);
    });
});
</script>
@endpush

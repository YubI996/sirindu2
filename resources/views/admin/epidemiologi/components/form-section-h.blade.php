{{-- Section H: Final Status (3 fields) --}}
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Kondisi Akhir</label>
            <select name="kondisi_akhir" id="kondisi_akhir" class="form-control">
                <option value="dalam_perawatan" {{ old('kondisi_akhir', $case->kondisi_akhir ?? 'dalam_perawatan') == 'dalam_perawatan' ? 'selected' : '' }}>Dalam Perawatan</option>
                <option value="sembuh" {{ old('kondisi_akhir', $case->kondisi_akhir ?? '') == 'sembuh' ? 'selected' : '' }}>Sembuh</option>
                <option value="meninggal" {{ old('kondisi_akhir', $case->kondisi_akhir ?? '') == 'meninggal' ? 'selected' : '' }}>Meninggal</option>
                <option value="pindah" {{ old('kondisi_akhir', $case->kondisi_akhir ?? '') == 'pindah' ? 'selected' : '' }}>Pindah Fasilitas/Daerah</option>
                <option value="unknown" {{ old('kondisi_akhir', $case->kondisi_akhir ?? '') == 'unknown' ? 'selected' : '' }}>Tidak Diketahui</option>
            </select>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Tanggal Kondisi Akhir</label>
            <input type="date" name="tanggal_kondisi_akhir" class="form-control"
                   value="{{ old('tanggal_kondisi_akhir', isset($case->tanggal_kondisi_akhir) ? $case->tanggal_kondisi_akhir->format('Y-m-d') : '') }}"
                   max="{{ date('Y-m-d') }}">
            <small class="form-text text-muted">Tanggal sembuh/meninggal/pindah</small>
        </div>
    </div>
</div>

<div class="row" id="death_details" style="display: none;">
    <div class="col-md-12">
        <div class="form-group">
            <label>Penyebab Kematian <span class="text-danger" id="death_required">*</span></label>
            <textarea name="penyebab_kematian" id="penyebab_kematian" class="form-control" rows="3">{{ old('penyebab_kematian', $case->penyebab_kematian ?? '') }}</textarea>
            <small class="form-text text-muted">Wajib diisi jika kondisi akhir meninggal</small>
        </div>
    </div>
</div>

<div class="alert alert-warning">
    <i class="fa fa-info-circle"></i> <strong>Catatan:</strong> Update kondisi akhir sesuai perkembangan kasus.
</div>

@push('scripts')
<script>
$(document).ready(function() {
    // Show/hide death cause field
    function toggleDeathDetails() {
        var kondisi = $('#kondisi_akhir').val();
        if (kondisi === 'meninggal') {
            $('#death_details').show();
            $('#penyebab_kematian').prop('required', true);
        } else {
            $('#death_details').hide();
            $('#penyebab_kematian').prop('required', false);
        }
    }

    $('#kondisi_akhir').on('change', toggleDeathDetails);
    toggleDeathDetails(); // Initial check
});
</script>
@endpush

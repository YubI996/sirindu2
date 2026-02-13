{{-- Section G: Management (5 fields) --}}
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Status Perawatan <span class="text-danger">*</span></label>
            <select name="status_rawat" id="status_rawat" class="form-control" required>
                <option value="">== Pilih Status ==</option>
                <option value="rawat_jalan" {{ old('status_rawat', $case->status_rawat ?? '') == 'rawat_jalan' ? 'selected' : '' }}>Rawat Jalan</option>
                <option value="rawat_inap" {{ old('status_rawat', $case->status_rawat ?? '') == 'rawat_inap' ? 'selected' : '' }}>Rawat Inap</option>
                <option value="isolasi_mandiri" {{ old('status_rawat', $case->status_rawat ?? '') == 'isolasi_mandiri' ? 'selected' : '' }}>Isolasi Mandiri</option>
                <option value="rujuk" {{ old('status_rawat', $case->status_rawat ?? '') == 'rujuk' ? 'selected' : '' }}>Rujuk</option>
            </select>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Nama Fasilitas Kesehatan <span class="text-danger">*</span></label>
            <input type="text" name="nama_faskes_rawat" class="form-control"
                   value="{{ old('nama_faskes_rawat', $case->nama_faskes_rawat ?? '') }}" required>
            <small class="form-text text-muted">Puskesmas/RS/Klinik tempat perawatan</small>
        </div>
    </div>
</div>

<div class="row" id="rawat_dates" style="display: none;">
    <div class="col-md-4">
        <div class="form-group">
            <label>Tanggal Masuk Rawat</label>
            <input type="date" name="tanggal_masuk_rawat" id="tanggal_masuk_rawat" class="form-control"
                   value="{{ old('tanggal_masuk_rawat', isset($case->tanggal_masuk_rawat) ? $case->tanggal_masuk_rawat->format('Y-m-d') : '') }}"
                   max="{{ date('Y-m-d') }}">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Tanggal Keluar Rawat</label>
            <input type="date" name="tanggal_keluar_rawat" id="tanggal_keluar_rawat" class="form-control"
                   value="{{ old('tanggal_keluar_rawat', isset($case->tanggal_keluar_rawat) ? $case->tanggal_keluar_rawat->format('Y-m-d') : '') }}"
                   max="{{ date('Y-m-d') }}">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Lama Rawat (Hari)</label>
            <input type="text" id="lama_rawat_display" class="form-control" readonly>
            <small class="form-text text-muted">Dihitung otomatis</small>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function() {
    // Show/hide date fields based on treatment status
    function toggleRawatDates() {
        var status = $('#status_rawat').val();
        if (status === 'rawat_inap' || status === 'isolasi_mandiri') {
            $('#rawat_dates').show();
        } else {
            $('#rawat_dates').hide();
        }
    }

    $('#status_rawat').on('change', toggleRawatDates);
    toggleRawatDates(); // Initial check

    // Auto-calculate length of stay
    function calculateLamaRawat() {
        var masuk = new Date($('#tanggal_masuk_rawat').val());
        var keluar = new Date($('#tanggal_keluar_rawat').val());

        if (masuk && keluar && keluar >= masuk) {
            var diff = Math.floor((keluar - masuk) / (1000 * 60 * 60 * 24));
            $('#lama_rawat_display').val(diff + ' hari');
        } else {
            $('#lama_rawat_display').val('');
        }
    }

    $('#tanggal_masuk_rawat, #tanggal_keluar_rawat').on('change', function() {
        calculateLamaRawat();

        // Validation
        var masuk = new Date($('#tanggal_masuk_rawat').val());
        var keluar = new Date($('#tanggal_keluar_rawat').val());

        if (keluar && keluar < masuk) {
            alert('Tanggal keluar rawat tidak boleh sebelum tanggal masuk rawat');
            $('#tanggal_keluar_rawat').val('');
        }
    });

    // Initial calculation if dates already set
    if ($('#tanggal_masuk_rawat').val() && $('#tanggal_keluar_rawat').val()) {
        calculateLamaRawat();
    }
});
</script>
@endpush

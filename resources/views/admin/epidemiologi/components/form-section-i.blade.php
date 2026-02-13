{{-- Section I: Contact Investigation (4 fields) --}}
<div class="alert alert-info">
    <i class="fa fa-info-circle"></i> <strong>Investigasi Kontak:</strong> Catat jumlah orang yang pernah kontak dengan pasien dalam masa penularan.
</div>

<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Jumlah Kontak Serumah</label>
            <input type="number" name="jumlah_kontak_serumah" id="jumlah_kontak_serumah" class="form-control"
                   value="{{ old('jumlah_kontak_serumah', $case->jumlah_kontak_serumah ?? 0) }}"
                   min="0">
            <small class="form-text text-muted">Orang yang tinggal satu rumah</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Jumlah Kontak Diluar Rumah</label>
            <input type="number" name="jumlah_kontak_diluar_rumah" id="jumlah_kontak_diluar_rumah" class="form-control"
                   value="{{ old('jumlah_kontak_diluar_rumah', $case->jumlah_kontak_diluar_rumah ?? 0) }}"
                   min="0">
            <small class="form-text text-muted">Teman, rekan kerja, tetangga</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Jumlah Kontak yang Bergejala</label>
            <input type="number" name="jumlah_kontak_bergejala" id="jumlah_kontak_bergejala" class="form-control"
                   value="{{ old('jumlah_kontak_bergejala', $case->jumlah_kontak_bergejala ?? 0) }}"
                   min="0">
            <small class="form-text text-muted">Yang menunjukkan gejala serupa</small>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            <label>Tindak Lanjut Kontak</label>
            <textarea name="tindak_lanjut_kontak" class="form-control" rows="3">{{ old('tindak_lanjut_kontak', $case->tindak_lanjut_kontak ?? '') }}</textarea>
            <small class="form-text text-muted">
                Jelaskan tindakan yang dilakukan: pemantauan, tes, isolasi, karantina, dll.
            </small>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div id="contact_summary" class="alert alert-secondary">
            <strong>Total Kontak:</strong> <span id="total_kontak">0</span> orang
            (<span id="total_bergejala">0</span> bergejala)
        </div>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function() {
    // Calculate total contacts
    function updateContactSummary() {
        var serumah = parseInt($('#jumlah_kontak_serumah').val()) || 0;
        var diluar = parseInt($('#jumlah_kontak_diluar_rumah').val()) || 0;
        var bergejala = parseInt($('#jumlah_kontak_bergejala').val()) || 0;
        var total = serumah + diluar;

        $('#total_kontak').text(total);
        $('#total_bergejala').text(bergejala);

        // Validation: bergejala can't be more than total
        if (bergejala > total) {
            $('#contact_summary').removeClass('alert-secondary').addClass('alert-warning');
            $('#contact_summary').html('<i class="fa fa-exclamation-triangle"></i> <strong>Peringatan:</strong> Jumlah kontak bergejala melebihi total kontak');
        } else {
            $('#contact_summary').removeClass('alert-warning').addClass('alert-secondary');
            $('#contact_summary').html('<strong>Total Kontak:</strong> <span id="total_kontak">' + total + '</span> orang (<span id="total_bergejala">' + bergejala + '</span> bergejala)');
        }
    }

    $('#jumlah_kontak_serumah, #jumlah_kontak_diluar_rumah, #jumlah_kontak_bergejala').on('input', updateContactSummary);
    updateContactSummary(); // Initial calculation
});
</script>
@endpush

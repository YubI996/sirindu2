{{-- Section C: Case Data (7 fields) --}}
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Jenis Kasus / Penyakit <span class="text-danger">*</span></label>
            <select name="id_jenis_kasus" class="form-control" required>
                <option value="">== Pilih Jenis Penyakit ==</option>
                @foreach($diseases as $disease)
                    <option value="{{ $disease->id }}" {{ old('id_jenis_kasus', $case->id_jenis_kasus ?? '') == $disease->id ? 'selected' : '' }}>
                        {{ $disease->nama_penyakit }} ({{ $disease->kode_penyakit }})
                    </option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Kode ICD-10</label>
            <input type="text" name="kode_icd10" class="form-control"
                   value="{{ old('kode_icd10', $case->kode_icd10 ?? '') }}"
                   maxlength="10">
            <small class="form-text text-muted">Opsional - Kode diagnosis ICD-10</small>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Tanggal Onset <span class="text-danger">*</span></label>
            <input type="date" name="tanggal_onset" id="tanggal_onset" class="form-control"
                   value="{{ old('tanggal_onset', isset($case) ? $case->tanggal_onset->format('Y-m-d') : '') }}"
                   max="{{ date('Y-m-d') }}" required>
            <small class="form-text text-muted">Tanggal mulai gejala</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Tanggal Konsultasi <span class="text-danger">*</span></label>
            <input type="date" name="tanggal_konsultasi" id="tanggal_konsultasi" class="form-control"
                   value="{{ old('tanggal_konsultasi', isset($case) ? $case->tanggal_konsultasi->format('Y-m-d') : date('Y-m-d')) }}"
                   max="{{ date('Y-m-d') }}" required>
            <small class="form-text text-muted">Tanggal berobat pertama kali</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Tanggal Lapor</label>
            <input type="date" name="tanggal_lapor" id="tanggal_lapor" class="form-control"
                   value="{{ old('tanggal_lapor', isset($case) ? $case->tanggal_lapor->format('Y-m-d') : date('Y-m-d')) }}"
                   max="{{ date('Y-m-d') }}">
            <small class="form-text text-muted">Default: hari ini</small>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Sumber Penularan</label>
            <select name="sumber_penularan" class="form-control">
                <option value="unknown" {{ old('sumber_penularan', $case->sumber_penularan ?? 'unknown') == 'unknown' ? 'selected' : '' }}>Tidak Diketahui</option>
                <option value="lokal" {{ old('sumber_penularan', $case->sumber_penularan ?? '') == 'lokal' ? 'selected' : '' }}>Lokal</option>
                <option value="import" {{ old('sumber_penularan', $case->sumber_penularan ?? '') == 'import' ? 'selected' : '' }}>Import (dari luar daerah)</option>
            </select>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Lokasi Penularan</label>
            <input type="text" name="lokasi_penularan" class="form-control"
                   value="{{ old('lokasi_penularan', $case->lokasi_penularan ?? '') }}">
            <small class="form-text text-muted">Jika sumber import, sebutkan lokasi</small>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function() {
    // Date validation: onset <= konsultasi <= lapor
    $('#tanggal_onset, #tanggal_konsultasi, #tanggal_lapor').on('change', function() {
        var onset = new Date($('#tanggal_onset').val());
        var konsultasi = new Date($('#tanggal_konsultasi').val());
        var lapor = new Date($('#tanggal_lapor').val());

        if (konsultasi < onset) {
            alert('Tanggal konsultasi tidak boleh sebelum tanggal onset');
            $('#tanggal_konsultasi').val('');
        }

        if (lapor < konsultasi) {
            alert('Tanggal lapor tidak boleh sebelum tanggal konsultasi');
            $('#tanggal_lapor').val('');
        }
    });
});
</script>
@endpush

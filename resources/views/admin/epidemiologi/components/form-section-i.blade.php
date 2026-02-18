{{-- Section I: Kontak Erat --}}
<div class="row">
    <div class="col-md-3">
        <div class="form-group">
            <label>Jumlah Kontak Erat</label>
            <input type="number" name="jumlah_kontak_erat" class="form-control" min="0"
                value="{{ old('jumlah_kontak_erat', $case->jumlah_kontak_erat ?? 0) }}">
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Kontak Dipantau</label>
            <input type="number" name="kontak_dipantau" class="form-control" min="0"
                value="{{ old('kontak_dipantau', $case->kontak_dipantau ?? 0) }}">
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Kontak Positif</label>
            <input type="number" name="kontak_positif" class="form-control" min="0"
                value="{{ old('kontak_positif', $case->kontak_positif ?? 0) }}">
        </div>
    </div>
</div>
<div class="form-group">
    <label>Keterangan Kontak</label>
    <textarea name="keterangan_kontak" class="form-control" rows="3">{{ old('keterangan_kontak', $case->keterangan_kontak ?? '') }}</textarea>
</div>

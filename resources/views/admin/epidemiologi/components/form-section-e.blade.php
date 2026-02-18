{{-- Section E: Riwayat --}}
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Riwayat Imunisasi</label>
            <textarea name="riwayat_imunisasi" class="form-control" rows="3"
                placeholder="Jenis vaksin yang pernah diterima, tanggal, dll.">{{ old('riwayat_imunisasi', $case->riwayat_imunisasi ?? '') }}</textarea>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Riwayat Perjalanan</label>
            <textarea name="riwayat_perjalanan" class="form-control" rows="3"
                placeholder="Perjalanan 14 hari terakhir ke daerah endemis.">{{ old('riwayat_perjalanan', $case->riwayat_perjalanan ?? '') }}</textarea>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Riwayat Kontak</label>
            <textarea name="riwayat_kontak" class="form-control" rows="3"
                placeholder="Kontak dengan penderita serupa.">{{ old('riwayat_kontak', $case->riwayat_kontak ?? '') }}</textarea>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Riwayat Penyakit Dahulu</label>
            <textarea name="riwayat_penyakit_dahulu" class="form-control" rows="3">{{ old('riwayat_penyakit_dahulu', $case->riwayat_penyakit_dahulu ?? '') }}</textarea>
        </div>
    </div>
</div>

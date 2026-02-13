{{-- Section J: Metadata (5 fields) --}}
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Status Kasus</label>
            <select name="status_kasus" class="form-control">
                <option value="suspected" {{ old('status_kasus', $case->status_kasus ?? 'suspected') == 'suspected' ? 'selected' : '' }}>Suspected (Suspek)</option>
                <option value="probable" {{ old('status_kasus', $case->status_kasus ?? '') == 'probable' ? 'selected' : '' }}>Probable (Kemungkinan)</option>
                <option value="confirmed" {{ old('status_kasus', $case->status_kasus ?? '') == 'confirmed' ? 'selected' : '' }}>Confirmed (Terkonfirmasi)</option>
                <option value="discarded" {{ old('status_kasus', $case->status_kasus ?? '') == 'discarded' ? 'selected' : '' }}>Discarded (Dibuang/Bukan Kasus)</option>
            </select>
            <small class="form-text text-muted">
                Klasifikasi berdasarkan kriteria WHO/Kemenkes
            </small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Fasilitas Kesehatan Pelapor</label>
            <input type="text" name="id_faskes_pelapor" class="form-control"
                   value="{{ old('id_faskes_pelapor', $case->id_faskes_pelapor ?? '') }}">
            <small class="form-text text-muted">Opsional - Nama fasilitas kesehatan yang melaporkan</small>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            <label>Catatan Tambahan</label>
            <textarea name="catatan_tambahan" class="form-control" rows="4">{{ old('catatan_tambahan', $case->catatan_tambahan ?? '') }}</textarea>
            <small class="form-text text-muted">
                Informasi tambahan yang relevan: komorbid, faktor risiko khusus, hasil investigasi, dll.
            </small>
        </div>
    </div>
</div>

<div class="alert alert-secondary">
    <strong>Informasi:</strong> Petugas yang menginput data akan tercatat secara otomatis dalam sistem.
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card bg-light">
            <div class="card-body">
                <h6 class="card-title"><i class="fa fa-book"></i> Panduan Klasifikasi Status Kasus</h6>
                <ul class="mb-0">
                    <li><strong>Suspected:</strong> Memenuhi kriteria klinis/epidemiologi, belum ada konfirmasi lab</li>
                    <li><strong>Probable:</strong> Memenuhi kriteria klinis + epidemiologi, lab tidak konklusif/tidak dilakukan</li>
                    <li><strong>Confirmed:</strong> Dikonfirmasi melalui pemeriksaan laboratorium</li>
                    <li><strong>Discarded:</strong> Hasil lab negatif atau diagnosis alternatif ditemukan</li>
                </ul>
            </div>
        </div>
    </div>
</div>

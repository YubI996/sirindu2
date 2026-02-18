{{-- Section J: Klasifikasi Kasus --}}
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Klasifikasi Kasus <span class="text-danger">*</span></label>
            <select name="status_kasus" class="form-control @error('status_kasus') is-invalid @enderror" required>
                <option value="">Pilih Klasifikasi</option>
                @foreach(['suspek'=>'Suspek','probable'=>'Probable','konfirmasi'=>'Konfirmasi','discarded'=>'Discarded'] as $val => $lbl)
                <option value="{{ $val }}" {{ old('status_kasus', $case->status_kasus ?? '') == $val ? 'selected' : '' }}>{{ $lbl }}</option>
                @endforeach
            </select>
            @error('status_kasus') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="card bg-light p-3">
            <small>
                <strong>Panduan Klasifikasi:</strong><br>
                <span class="badge badge-warning">Suspek</span> Memenuhi kriteria klinis/epidemiologis, belum konfirmasi lab<br>
                <span class="badge badge-info">Probable</span> Suspek + hubungan epidemiologis kuat dengan kasus konfirmasi<br>
                <span class="badge badge-danger">Konfirmasi</span> Terbukti positif melalui pemeriksaan laboratorium<br>
                <span class="badge badge-secondary">Discarded</span> Bukan kasus berdasarkan hasil investigasi/lab
            </small>
        </div>
    </div>
</div>

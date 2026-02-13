{{-- Section E: History (4 fields) --}}
<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            <label>Riwayat Perjalanan</label>
            <textarea name="riwayat_perjalanan" class="form-control" rows="3">{{ old('riwayat_perjalanan', $case->riwayat_perjalanan ?? '') }}</textarea>
            <small class="form-text text-muted">Sebutkan tempat dan tanggal perjalanan dalam 14 hari terakhir sebelum onset</small>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input" id="riwayat_kontak_kasus" name="riwayat_kontak_kasus"
                       {{ old('riwayat_kontak_kasus', $case->riwayat_kontak_kasus ?? false) ? 'checked' : '' }}>
                <label class="custom-control-label" for="riwayat_kontak_kasus">
                    <i class="fa fa-users"></i> Ada Riwayat Kontak dengan Kasus Terkonfirmasi
                </label>
            </div>
            <small class="form-text text-muted">Centang jika pasien pernah kontak dengan kasus terkonfirmasi</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Status Imunisasi</label>
            <select name="riwayat_imunisasi" class="form-control">
                <option value="tidak_tahu" {{ old('riwayat_imunisasi', $case->riwayat_imunisasi ?? 'tidak_tahu') == 'tidak_tahu' ? 'selected' : '' }}>Tidak Diketahui</option>
                <option value="lengkap" {{ old('riwayat_imunisasi', $case->riwayat_imunisasi ?? '') == 'lengkap' ? 'selected' : '' }}>Lengkap</option>
                <option value="tidak_lengkap" {{ old('riwayat_imunisasi', $case->riwayat_imunisasi ?? '') == 'tidak_lengkap' ? 'selected' : '' }}>Tidak Lengkap</option>
                <option value="tidak_ada" {{ old('riwayat_imunisasi', $case->riwayat_imunisasi ?? '') == 'tidak_ada' ? 'selected' : '' }}>Tidak Ada</option>
            </select>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Tanggal Imunisasi Terakhir</label>
            <input type="date" name="tanggal_imunisasi_terakhir" class="form-control"
                   value="{{ old('tanggal_imunisasi_terakhir', isset($case->tanggal_imunisasi_terakhir) ? $case->tanggal_imunisasi_terakhir->format('Y-m-d') : '') }}"
                   max="{{ date('Y-m-d') }}">
            <small class="form-text text-muted">Jika diketahui</small>
        </div>
    </div>
</div>

{{-- Section E: Riwayat & Imunisasi (expanded with Google Form) --}}

<h6 class="section-subtitle"><i class="fa fa-history"></i> Riwayat Perjalanan & Kontak</h6>
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Riwayat Perjalanan</label>
            <textarea name="riwayat_perjalanan" class="form-control" rows="2"
                      placeholder="Tuliskan riwayat perjalanan pasien...">{{ old('riwayat_perjalanan', $case->riwayat_perjalanan ?? '') }}</textarea>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Riwayat Kontak Kasus Terkonfirmasi</label>
            <div class="mt-2">
                <input type="hidden" name="riwayat_kontak_kasus" value="0">
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" name="riwayat_kontak_kasus" value="1"
                           class="custom-control-input" id="riwayat_kontak_kasus"
                           {{ old('riwayat_kontak_kasus', $case->riwayat_kontak_kasus ?? false) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="riwayat_kontak_kasus">Ya, ada kontak</label>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Riwayat Bepergian ke Luar Kab/Prov/Negeri</label>
            <select name="riwayat_bepergian" class="form-control" id="riwayat_bepergian">
                <option value="">-- Pilih --</option>
                <option value="ya" {{ old('riwayat_bepergian', $case->riwayat_bepergian ?? '') == 'ya' ? 'selected' : '' }}>Ya</option>
                <option value="tidak" {{ old('riwayat_bepergian', $case->riwayat_bepergian ?? '') == 'tidak' ? 'selected' : '' }}>Tidak</option>
            </select>
        </div>
    </div>
</div>

<div class="row" id="riwayat_bepergian_detail" style="{{ old('riwayat_bepergian', $case->riwayat_bepergian ?? '') == 'ya' ? '' : 'display:none;' }}">
    <div class="col-md-6">
        <div class="form-group">
            <label>Lokasi Bepergian</label>
            <input type="text" name="lokasi_bepergian" class="form-control"
                   value="{{ old('lokasi_bepergian', $case->lokasi_bepergian ?? '') }}">
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Tanggal Bepergian</label>
            <input type="date" name="tanggal_bepergian" class="form-control"
                   value="{{ old('tanggal_bepergian', isset($case) && $case->tanggal_bepergian ? $case->tanggal_bepergian->format('Y-m-d') : '') }}"
                   max="{{ date('Y-m-d') }}">
        </div>
    </div>
</div>

<hr>

{{-- Riwayat Imunisasi --}}
<h6 class="section-subtitle"><i class="fa fa-syringe"></i> Riwayat Imunisasi</h6>
<div class="row">
    <div class="col-md-3">
        <div class="form-group">
            <label>Pengisian Riwayat Imunisasi</label>
            <select name="riwayat_imunisasi" class="form-control" id="riwayat_imunisasi_select">
                <option value="">-- Pilih --</option>
                <option value="lengkap" {{ old('riwayat_imunisasi', $case->riwayat_imunisasi ?? '') == 'lengkap' ? 'selected' : '' }}>Lengkap</option>
                <option value="tidak_lengkap" {{ old('riwayat_imunisasi', $case->riwayat_imunisasi ?? '') == 'tidak_lengkap' ? 'selected' : '' }}>Tidak Lengkap</option>
                <option value="tidak_tahu" {{ old('riwayat_imunisasi', $case->riwayat_imunisasi ?? '') == 'tidak_tahu' ? 'selected' : '' }}>Tidak Tahu</option>
                <option value="tidak_ada" {{ old('riwayat_imunisasi', $case->riwayat_imunisasi ?? '') == 'tidak_ada' ? 'selected' : '' }}>Tidak Ada</option>
            </select>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Tanggal Imunisasi Terakhir</label>
            <input type="date" name="tanggal_imunisasi_terakhir" class="form-control"
                   value="{{ old('tanggal_imunisasi_terakhir', isset($case) && $case->tanggal_imunisasi_terakhir ? $case->tanggal_imunisasi_terakhir->format('Y-m-d') : '') }}">
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Sumber Informasi</label>
            <input type="text" name="sumber_informasi_imunisasi" class="form-control"
                   value="{{ old('sumber_informasi_imunisasi', $case->sumber_informasi_imunisasi ?? '') }}"
                   placeholder="KMS, KIA, wawancara, dll">
        </div>
    </div>
    <div class="col-md-3" id="alasan_imunisasi_wrapper" style="{{ old('riwayat_imunisasi', $case->riwayat_imunisasi ?? '') == 'tidak_lengkap' ? '' : 'display:none;' }}">
        <div class="form-group">
            <label>Alasan Imunisasi Tidak Lengkap</label>
            <textarea name="alasan_imunisasi_tidak_lengkap" class="form-control" rows="1">{{ old('alasan_imunisasi_tidak_lengkap', $case->alasan_imunisasi_tidak_lengkap ?? '') }}</textarea>
        </div>
    </div>
</div>

{{-- Detail Imunisasi --}}
<div class="row">
    <div class="col-md-12">
        <p class="text-muted mb-2"><i class="fa fa-info-circle"></i> Isi sesuai riwayat imunisasi pasien (opsional)</p>
    </div>
</div>
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Imunisasi 1</label>
            <input type="text" name="imunisasi_1" class="form-control"
                   value="{{ old('imunisasi_1', $case->imunisasi_1 ?? '') }}"
                   placeholder="MR1 - 9 bulan / DPT-HB-Hib 1,2,3 / OPV1">
            <small class="form-text text-muted">MR1 - 9 bulan / DPT-HB-Hib 1,2,3 / OPV1</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Imunisasi 2</label>
            <input type="text" name="imunisasi_2" class="form-control"
                   value="{{ old('imunisasi_2', $case->imunisasi_2 ?? '') }}"
                   placeholder="MR2 - 18 bulan / DPT-HB-Hib Booster / OPV2">
            <small class="form-text text-muted">MR2 - 18 bulan / Booster / OPV2</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Imunisasi 3</label>
            <input type="text" name="imunisasi_3" class="form-control"
                   value="{{ old('imunisasi_3', $case->imunisasi_3 ?? '') }}"
                   placeholder="MR3 - kelas 1 SD / DT kelas 1 / OPV2">
            <small class="form-text text-muted">MR3 - kelas 1 SD / DT kelas 1</small>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Imunisasi 4</label>
            <input type="text" name="imunisasi_4" class="form-control"
                   value="{{ old('imunisasi_4', $case->imunisasi_4 ?? '') }}"
                   placeholder="MMR / TD kelas 2 dan 5">
            <small class="form-text text-muted">MMR / TD kelas 2 dan 5</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Imunisasi 5 (Tambahan)</label>
            <input type="text" name="imunisasi_5" class="form-control"
                   value="{{ old('imunisasi_5', $case->imunisasi_5 ?? '') }}"
                   placeholder="Kampanye / ORI / SUBPIN / PIN">
            <small class="form-text text-muted">Kampanye / ORI / SUBPIN / PIN</small>
        </div>
    </div>
</div>

@push('js')
<script>
$(document).ready(function() {
    // Toggle riwayat bepergian detail
    $('#riwayat_bepergian').on('change', function() {
        $('#riwayat_bepergian_detail').toggle($(this).val() === 'ya');
    });

    // Toggle alasan imunisasi tidak lengkap
    $('#riwayat_imunisasi_select').on('change', function() {
        $('#alasan_imunisasi_wrapper').toggle($(this).val() === 'tidak_lengkap');
    });
});
</script>
@endpush

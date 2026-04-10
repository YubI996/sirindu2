{{-- Section J: Status Kasus + Kontak Erat MoD --}}
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Status Kasus</label>
            <select name="status_kasus" class="form-control">
                <option value="suspected" {{ old('status_kasus', $case->status_kasus ?? 'suspected') == 'suspected' ? 'selected' : '' }}>Suspected (Suspek)</option>
                <option value="probable"  {{ old('status_kasus', $case->status_kasus ?? '') == 'probable'  ? 'selected' : '' }}>Probable (Kemungkinan)</option>
                <option value="confirmed" {{ old('status_kasus', $case->status_kasus ?? '') == 'confirmed' ? 'selected' : '' }}>Confirmed (Terkonfirmasi)</option>
                <option value="discarded" {{ old('status_kasus', $case->status_kasus ?? '') == 'discarded' ? 'selected' : '' }}>Discarded (Dibuang/Bukan Kasus)</option>
            </select>
            <small class="form-text text-muted">Klasifikasi berdasarkan kriteria WHO/Kemenkes</small>
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

<hr>

{{-- Kontak Erat MoD --}}
<h6 class="section-subtitle"><i class="fa fa-users"></i> Daftar Kontak Erat</h6>
<p class="text-muted mb-3"><i class="fa fa-info-circle"></i> Tambahkan semua orang yang pernah kontak erat dengan pasien selama masa menular.</p>

@php
    $kontakRows = old('kontak_erat', []);
    $kontakExisting = $case->kontakErat ?? collect();
    if (empty($kontakRows)) {
        $kontakRows = $kontakExisting->map(fn($k) => [
            'nama'                    => $k->nama,
            'hubungan'                => $k->hubungan,
            'no_telepon'              => $k->no_telepon,
            'alamat'                  => $k->alamat,
            'tanggal_kontak_terakhir' => $k->tanggal_kontak_terakhir?->format('Y-m-d'),
            'ada_gejala'              => $k->ada_gejala,
            'catatan'                 => $k->catatan,
        ])->toArray();
    }
@endphp

{{-- Template tersembunyi --}}
<template id="kontakTpl">
    <div class="kontak-erat-row card card-body mb-2 py-2 px-3 bg-light">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong class="small text-secondary">Kontak Erat</strong>
            <button type="button" class="btn btn-sm btn-outline-danger remove-kontak-row">
                <i class="fa fa-times"></i> Hapus
            </button>
        </div>
        <div class="row">
            <div class="col-md-3">
                <div class="form-group mb-2">
                    <label class="small mb-1">Nama <span class="text-danger">*</span></label>
                    <input type="text" name="kontak_erat[__IDX__][nama]" class="form-control form-control-sm" required>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group mb-2">
                    <label class="small mb-1">Hubungan</label>
                    <input type="text" name="kontak_erat[__IDX__][hubungan]" class="form-control form-control-sm" placeholder="teman, keluarga, dll">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group mb-2">
                    <label class="small mb-1">No. Telepon</label>
                    <input type="text" name="kontak_erat[__IDX__][no_telepon]" class="form-control form-control-sm" maxlength="20">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group mb-2">
                    <label class="small mb-1">Alamat</label>
                    <textarea name="kontak_erat[__IDX__][alamat]" class="form-control form-control-sm" rows="1"></textarea>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group mb-2">
                    <label class="small mb-1">Tgl Kontak Terakhir</label>
                    <input type="date" name="kontak_erat[__IDX__][tanggal_kontak_terakhir]" class="form-control form-control-sm" max="{{ date('Y-m-d') }}">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-2">
                <div class="form-group mb-0">
                    <label class="small mb-1">Ada Gejala?</label>
                    <div class="custom-control custom-checkbox mt-1">
                        <input type="hidden" name="kontak_erat[__IDX__][ada_gejala]" value="0">
                        <input type="checkbox" name="kontak_erat[__IDX__][ada_gejala]" value="1"
                               class="custom-control-input" id="ada_gejala___IDX__">
                        <label class="custom-control-label" for="ada_gejala___IDX__">Ya, bergejala</label>
                    </div>
                </div>
            </div>
            <div class="col-md-10">
                <div class="form-group mb-0">
                    <label class="small mb-1">Catatan</label>
                    <input type="text" name="kontak_erat[__IDX__][catatan]" class="form-control form-control-sm" placeholder="Catatan tambahan (opsional)">
                </div>
            </div>
        </div>
    </div>
</template>

<div id="kontakEratList">
    @foreach ($kontakRows as $idx => $kk)
    <div class="kontak-erat-row card card-body mb-2 py-2 px-3 bg-light">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong class="small text-secondary">Kontak Erat {{ $idx + 1 }}</strong>
            <button type="button" class="btn btn-sm btn-outline-danger remove-kontak-row">
                <i class="fa fa-times"></i> Hapus
            </button>
        </div>
        <div class="row">
            <div class="col-md-3">
                <div class="form-group mb-2">
                    <label class="small mb-1">Nama <span class="text-danger">*</span></label>
                    <input type="text" name="kontak_erat[{{ $idx }}][nama]" class="form-control form-control-sm"
                           value="{{ $kk['nama'] ?? '' }}" required>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group mb-2">
                    <label class="small mb-1">Hubungan</label>
                    <input type="text" name="kontak_erat[{{ $idx }}][hubungan]" class="form-control form-control-sm"
                           value="{{ $kk['hubungan'] ?? '' }}" placeholder="teman, keluarga, dll">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group mb-2">
                    <label class="small mb-1">No. Telepon</label>
                    <input type="text" name="kontak_erat[{{ $idx }}][no_telepon]" class="form-control form-control-sm"
                           value="{{ $kk['no_telepon'] ?? '' }}" maxlength="20">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group mb-2">
                    <label class="small mb-1">Alamat</label>
                    <textarea name="kontak_erat[{{ $idx }}][alamat]" class="form-control form-control-sm" rows="1">{{ $kk['alamat'] ?? '' }}</textarea>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group mb-2">
                    <label class="small mb-1">Tgl Kontak Terakhir</label>
                    <input type="date" name="kontak_erat[{{ $idx }}][tanggal_kontak_terakhir]" class="form-control form-control-sm"
                           value="{{ $kk['tanggal_kontak_terakhir'] ?? '' }}" max="{{ date('Y-m-d') }}">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-2">
                <div class="form-group mb-0">
                    <label class="small mb-1">Ada Gejala?</label>
                    <div class="custom-control custom-checkbox mt-1">
                        <input type="hidden" name="kontak_erat[{{ $idx }}][ada_gejala]" value="0">
                        <input type="checkbox" name="kontak_erat[{{ $idx }}][ada_gejala]" value="1"
                               class="custom-control-input" id="ada_gejala_{{ $idx }}"
                               {{ !empty($kk['ada_gejala']) ? 'checked' : '' }}>
                        <label class="custom-control-label" for="ada_gejala_{{ $idx }}">Ya, bergejala</label>
                    </div>
                </div>
            </div>
            <div class="col-md-10">
                <div class="form-group mb-0">
                    <label class="small mb-1">Catatan</label>
                    <input type="text" name="kontak_erat[{{ $idx }}][catatan]" class="form-control form-control-sm"
                           value="{{ $kk['catatan'] ?? '' }}" placeholder="Catatan tambahan (opsional)">
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

<button type="button" id="addKontakErat" class="btn btn-sm btn-outline-primary mt-1">
    <i class="fa fa-plus"></i> Tambah Kontak Erat
</button>

<div class="alert alert-secondary mt-3">
    <strong>Informasi:</strong> Petugas yang menginput data akan tercatat secara otomatis dalam sistem.
</div>

<div class="row mt-2">
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

@push('js')
<script>
$(document).ready(function() {
    var kontakIdx = {{ count($kontakRows ?? []) }};

    $('#addKontakErat').on('click', function() {
        var tpl = document.querySelector('#kontakTpl').innerHTML;
        tpl = tpl.replace(/__IDX__/g, kontakIdx);
        kontakIdx++;
        $('#kontakEratList').append(tpl);
    });

    $(document).on('click', '.remove-kontak-row', function() {
        $(this).closest('.kontak-erat-row').remove();
    });
});
</script>
@endpush

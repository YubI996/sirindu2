{{-- Section I: Investigasi Kontak --}}
<div class="alert alert-info">
    <i class="fa fa-info-circle"></i> <strong>Investigasi Kontak:</strong> Catat jumlah orang yang pernah kontak dengan pasien dalam masa penularan, lalu daftarkan detail kontak erat di bawah.
</div>

{{-- Ringkasan jumlah kontak --}}
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
        <div id="contact_summary" class="alert alert-secondary">
            <strong>Total Kontak:</strong> <span id="total_kontak">0</span> orang
            (<span id="total_bergejala">0</span> bergejala)
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

<hr>

{{-- Daftar Kontak Erat MoD --}}
<h6 class="section-subtitle"><i class="fa fa-users"></i> Daftar Kontak Erat</h6>
<p class="text-muted mb-3"><i class="fa fa-info-circle"></i> Tambahkan semua orang yang pernah kontak erat dengan pasien selama masa menular.</p>

@php
    $kontakRows = old('kontak_erat', []);
    $kontakExisting = $case->kontakErat ?? collect();
    if (empty($kontakRows)) {
        $kontakRows = $kontakExisting->map(fn($k) => [
            'nama'                            => $k->nama,
            'hubungan'                        => $k->hubungan,
            'tanggal_lahir'                   => $k->tanggal_lahir?->format('Y-m-d'),
            'no_telepon'                      => $k->no_telepon,
            'alamat'                          => $k->alamat,
            'tanggal_kontak_terakhir'         => $k->tanggal_kontak_terakhir?->format('Y-m-d'),
            'ada_gejala'                      => $k->ada_gejala,
            'jumlah_imunisasi_campak_rubella' => $k->jumlah_imunisasi_campak_rubella,
            'catatan'                         => $k->catatan,
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
                    <label class="small mb-1">Tgl Lahir</label>
                    <input type="date" name="kontak_erat[__IDX__][tanggal_lahir]" class="form-control form-control-sm" max="{{ date('Y-m-d') }}">
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
        </div>
        <div class="row">
            <div class="col-md-3">
                <div class="form-group mb-0">
                    <label class="small mb-1">Tgl Kontak Terakhir</label>
                    <input type="date" name="kontak_erat[__IDX__][tanggal_kontak_terakhir]" class="form-control form-control-sm" max="{{ date('Y-m-d') }}">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group mb-0">
                    <label class="small mb-1">Status Imunisasi {{ $case->jenisKasus->nama_penyakit ?? 'Campak-Rubella' }}</label>
                    <select name="kontak_erat[__IDX__][jumlah_imunisasi_campak_rubella]" class="form-control form-control-sm">
                        <option value="">-- Tidak Diketahui --</option>
                        <option value="0">Belum</option>
                        <option value="1">Sudah (1x)</option>
                        <option value="2">Sudah (2x)</option>
                        <option value="3">Sudah (3x+)</option>
                    </select>
                </div>
            </div>
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
            <div class="col-md-5">
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
                    <label class="small mb-1">Tgl Lahir</label>
                    <input type="date" name="kontak_erat[{{ $idx }}][tanggal_lahir]" class="form-control form-control-sm"
                           value="{{ $kk['tanggal_lahir'] ?? '' }}" max="{{ date('Y-m-d') }}">
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
        </div>
        <div class="row">
            <div class="col-md-3">
                <div class="form-group mb-0">
                    <label class="small mb-1">Tgl Kontak Terakhir</label>
                    <input type="date" name="kontak_erat[{{ $idx }}][tanggal_kontak_terakhir]" class="form-control form-control-sm"
                           value="{{ $kk['tanggal_kontak_terakhir'] ?? '' }}" max="{{ date('Y-m-d') }}">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group mb-0">
                    <label class="small mb-1">Status Imunisasi {{ $case->jenisKasus->nama_penyakit ?? 'Campak-Rubella' }}</label>
                    <select name="kontak_erat[{{ $idx }}][jumlah_imunisasi_campak_rubella]" class="form-control form-control-sm">
                        <option value="">-- Tidak Diketahui --</option>
                        @foreach([0 => 'Belum', 1 => 'Sudah (1x)', 2 => 'Sudah (2x)', 3 => 'Sudah (3x+)'] as $val => $lbl)
                        <option value="{{ $val }}" {{ (string)($kk['jumlah_imunisasi_campak_rubella'] ?? '') === (string)$val ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
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
            <div class="col-md-5">
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

@push('js')
<script>
$(document).ready(function() {
    // Ringkasan jumlah kontak
    function updateContactSummary() {
        var serumah  = parseInt($('#jumlah_kontak_serumah').val()) || 0;
        var diluar   = parseInt($('#jumlah_kontak_diluar_rumah').val()) || 0;
        var bergejala = parseInt($('#jumlah_kontak_bergejala').val()) || 0;
        var total = serumah + diluar;

        if (bergejala > total) {
            $('#contact_summary')
                .removeClass('alert-secondary').addClass('alert-warning')
                .html('<i class="fa fa-exclamation-triangle"></i> <strong>Peringatan:</strong> Jumlah kontak bergejala melebihi total kontak');
        } else {
            $('#contact_summary')
                .removeClass('alert-warning').addClass('alert-secondary')
                .html('<strong>Total Kontak:</strong> <span id="total_kontak">' + total + '</span> orang (<span id="total_bergejala">' + bergejala + '</span> bergejala)');
        }
    }

    $('#jumlah_kontak_serumah, #jumlah_kontak_diluar_rumah, #jumlah_kontak_bergejala').on('input', updateContactSummary);
    updateContactSummary();

    // Daftar kontak erat MoD
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

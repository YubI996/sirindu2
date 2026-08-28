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

{{-- Detail Imunisasi Per Antigen --}}
@php
    // Lima slot antigen dipakai bersama oleh semua penyakit; labelnya menyesuaikan
    // penyakit agar cocok dengan baris di formulir *1 masing-masing (FP-1 VI,
    // DIF-1 no.4, PERT-01 RIWAYAT VAKSINASI). Urutan slot = urutan baris formulir.
    $antigenLabelSets = [
        'CAMPAK_RUBELLA' => [
            1 => 'MR1 (9 bulan)',
            2 => 'MR2 (18 bulan)',
            3 => 'MR3 kelas 1 SD',
            4 => 'MMR',
            5 => 'Kampanye / ORI / SUBPIN / PIN',
        ],
        'DIFTERI_OBS' => [
            1 => 'DPT-HB-Hib 1, 2 dan 3',
            2 => 'DPT-HB-Hib Booster (18 bulan)',
            3 => 'DT kelas 1',
            4 => 'Td kelas 2 dan 5',
            5 => 'Kampanye / ORI',
        ],
        'PERTUSIS' => [
            1 => 'DPT-HB-Hib usia 2 bulan',
            2 => 'DPT-HB-Hib usia 3 bulan',
            3 => 'DPT-HB-Hib usia 4 bulan',
            4 => 'DPT-HB-Hib usia 18 bulan (booster)',
            5 => 'DPT-HB-Hib saat ORI',
        ],
        'AFP' => [
            1 => 'Imunisasi rutin — OPV',
            2 => 'Imunisasi rutin — IPV',
            3 => 'Imunisasi rutin — Hexavalen',
            4 => 'Imunisasi tambahan — OPV',
            5 => 'Imunisasi tambahan — IPV',
        ],
        'TETANUS_NEO' => [
            1 => 'Td ibu — kehamilan ini',
            2 => 'Td ibu — kehamilan sebelumnya',
            3 => 'Td ibu — calon pengantin',
            4 => 'DPT-HB-Hib / DT / Td masa kecil ibu',
            5 => 'Td saat investigasi',
        ],
    ];
    $antigenDefault = [
        1 => 'Dosis 1',
        2 => 'Dosis 2',
        3 => 'Dosis 3',
        4 => 'Dosis 4 / Booster',
        5 => 'Kampanye / ORI / SUBPIN / PIN',
    ];
    $kodePenyakitKasus = optional($case->jenisKasus ?? null)->kode_penyakit ?? '';
    $antigenLabels = $antigenLabelSets[$kodePenyakitKasus] ?? $antigenDefault;
    $imunisasiRows = collect($case->imunisasi ?? [])->keyBy('imunisasi_ke');
    $oldImunisasi = old('imunisasi', []);
@endphp

<p class="text-muted mb-2"><i class="fa fa-info-circle"></i> Isi riwayat imunisasi per antigen (opsional)</p>
<div class="table-responsive">
    <table class="table table-sm table-bordered">
        <thead class="thead-light">
            <tr>
                <th style="width:30%">Antigen / Dosis</th>
                <th style="width:20%">Diberikan</th>
                <th style="width:25%">Sumber Informasi</th>
                <th style="width:25%">Tanggal Imunisasi</th>
            </tr>
        </thead>
        <tbody>
            @for ($ke = 1; $ke <= 5; $ke++)
                @php
                    $rowDb    = $imunisasiRows->get($ke);
                    $rowOld   = $oldImunisasi[$ke] ?? [];
                    $diberikan      = $rowOld['diberikan']        ?? ($rowDb->diberikan ?? 'tidak_tahu');
                    $sumber         = $rowOld['sumber_informasi'] ?? ($rowDb->sumber_informasi ?? '');
                    $tglImunisasi   = $rowOld['tanggal_imunisasi'] ?? (isset($rowDb->tanggal_imunisasi) ? $rowDb->tanggal_imunisasi->format('Y-m-d') : '');
                @endphp
                <tr>
                    <td class="align-middle"><strong>{{ $ke }}.</strong> <span class="antigen-label" data-slot="{{ $ke }}">{{ $antigenLabels[$ke] }}</span></td>
                    <td>
                        <select name="imunisasi[{{ $ke }}][diberikan]" class="form-control form-control-sm">
                            <option value="tidak_tahu" {{ $diberikan === 'tidak_tahu' ? 'selected' : '' }}>Tidak Tahu</option>
                            <option value="ya"         {{ $diberikan === 'ya'         ? 'selected' : '' }}>Ya</option>
                            <option value="tidak"      {{ $diberikan === 'tidak'      ? 'selected' : '' }}>Tidak</option>
                        </select>
                    </td>
                    <td>
                        <input type="text" name="imunisasi[{{ $ke }}][sumber_informasi]"
                               class="form-control form-control-sm"
                               value="{{ $sumber }}"
                               placeholder="KMS, KIA, wawancara…">
                    </td>
                    <td>
                        <input type="date" name="imunisasi[{{ $ke }}][tanggal_imunisasi]"
                               class="form-control form-control-sm"
                               value="{{ $tglImunisasi }}">
                    </td>
                </tr>
            @endfor
        </tbody>
    </table>
</div>

@push('js')
<script>
$(document).ready(function() {
    // Label antigen mengikuti penyakit yang dipilih. Di halaman create $case belum
    // ada, jadi label awal memakai set default sampai jenis kasus dipilih.
    var antigenLabelSets = @json($antigenLabelSets);
    var antigenDefault   = @json($antigenDefault);

    function syncAntigenLabels() {
        var kode = $('#id_jenis_kasus').find('option:selected').data('kode') || '';
        var set  = antigenLabelSets[kode] || antigenDefault;
        $('.antigen-label').each(function() {
            var slot = $(this).data('slot');
            if (set[slot]) $(this).text(set[slot]);
        });
    }
    $('#id_jenis_kasus').on('change', syncAntigenLabels);
    syncAntigenLabels();

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

{{-- Kartu "Data Kesmas & Lingkungan" — spec docs/superpowers/specs/2026-09-15-data-kesmas-design.md §3.
     Dipakai create (tanpa $anak) & edit. Tertutup default; TIDAK BOLEH ada atribut validasi HTML
     (wajib isi, batas bawah/atas angka) di dalamnya: panel tertutup tak bisa difokus browser → submit mati senyap.
     Select tiga keadaan: '' = belum diisi (→ null), 1 = Ya, 0 = Tidak. --}}
@php
    $anak = $anak ?? null;
    $k = config('kesmas');
    $nilai = fn (string $f) => (string) old($f, $anak->$f ?? '');
@endphp
<div class="col-12 mb-3">
    <div class="card">
        <div class="card-header p-0">
            <button type="button" class="btn btn-link btn-block text-left font-weight-bold" data-toggle="collapse" data-target="#kartuKesmas" aria-expanded="false" aria-controls="kartuKesmas">
                Data Kesmas &amp; Lingkungan (opsional)
            </button>
        </div>
        <div id="kartuKesmas" class="collapse">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 col-sm-12">
                        <div class="form-group">
                            <label for="no_id_epus">No. ID ePuskesmas</label>
                            <input type="text" name="no_id_epus" id="no_id_epus" class="form-control" maxlength="50" value="{{ $nilai('no_id_epus') }}">
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-12">
                        <div class="form-group">
                            <label for="fktp_bpjs">FKTP BPJS terdaftar</label>
                            <input type="text" name="fktp_bpjs" id="fktp_bpjs" class="form-control" maxlength="100" value="{{ $nilai('fktp_bpjs') }}">
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-12">
                        <div class="form-group">
                            <label for="status_tk_paud">Keikutsertaan TK/PAUD</label>
                            <select name="status_tk_paud" id="status_tk_paud" class="form-control">
                                <option value="">— belum diisi —</option>
                                @foreach ($k['status_tk_paud'] as $opsi)
                                <option value="{{ $opsi }}" @selected($nilai('status_tk_paud') === $opsi)>{{ $opsi }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @foreach (['air_bersih' => 'Akses air bersih di rumah', 'jamban_sehat' => 'Jamban sehat', 'merokok_keluarga' => 'Ada anggota keluarga serumah yang merokok'] as $f => $label)
                    <div class="col-md-4 col-sm-12">
                        <div class="form-group">
                            <label for="{{ $f }}">{{ $label }}</label>
                            <select name="{{ $f }}" id="{{ $f }}" class="form-control">
                                <option value="" @selected($nilai($f) === '')>— belum diisi —</option>
                                <option value="1" @selected($nilai($f) === '1')>Ya</option>
                                <option value="0" @selected($nilai($f) === '0')>Tidak</option>
                            </select>
                        </div>
                    </div>
                    @endforeach
                    <div class="col-md-8 col-sm-12">
                        <div class="form-group">
                            <label for="penyakit_penyerta">Riwayat penyakit penyerta</label>
                            <input type="text" name="penyakit_penyerta" id="penyakit_penyerta" class="form-control" maxlength="255" placeholder="mis. Asma, Alergi, TBC" value="{{ $nilai('penyakit_penyerta') }}">
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-12">
                        <div class="form-group">
                            <label for="pjb">Penyakit Jantung Bawaan (PJB)</label>
                            <input type="text" name="pjb" id="pjb" class="form-control" maxlength="100" placeholder="Tidak Ada" value="{{ $nilai('pjb') }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

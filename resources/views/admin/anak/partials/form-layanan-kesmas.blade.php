{{-- Kartu "Layanan Kesmas" per kunjungan — spec §3. $data nullable (form Tambah Pengukuran);
     $p = awalan id unik karena Edit Anak merender satu form per kunjungan di satu halaman.
     Checkbox hidden(0)+checkbox(1) → dibaca $request->boolean() (lihat CLAUDE.md).
     Tertutup default; TIDAK BOLEH ada atribut validasi HTML (wajib isi, batas angka) di dalamnya.
     old() hanya dipakai bila satu form di halaman ($p === '') agar nilai gagal-validasi
     tidak bocor ke semua form kunjungan di halaman edit. --}}
@php
    $data = $data ?? null;
    $p = $p ?? '';
    $k = config('kesmas');
    $nilai = fn (string $f) => (string) ($p === '' ? old($f, $data->$f ?? '') : ($data->$f ?? ''));
@endphp
<div class="col-12 mb-3">
    <div class="card">
        <div class="card-header p-0">
            <button type="button" class="btn btn-link btn-block text-left font-weight-bold" data-toggle="collapse" data-target="#{{ $p }}kartuLayanan" aria-expanded="false" aria-controls="{{ $p }}kartuLayanan">
                Layanan Kesmas (opsional)
            </button>
        </div>
        <div id="{{ $p }}kartuLayanan" class="collapse">
            <div class="card-body">
                <div class="row">
                    @foreach ($k['layanan'] as $f => $def)
                    <div class="col-md-4 col-sm-12">
                        <div class="form-check mb-2">
                            <input type="hidden" name="{{ $f }}" value="0">
                            <input class="form-check-input" type="checkbox" name="{{ $f }}" id="{{ $p }}{{ $f }}" value="1" @checked($nilai($f) === '1')>
                            <label class="form-check-label" for="{{ $p }}{{ $f }}">{{ $def['label'] }}</label>
                        </div>
                    </div>
                    @endforeach
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-4 col-sm-12">
                        <div class="form-group">
                            <label for="{{ $p }}tgl_penanda_ckg">Tanggal penanda CKG (cek kesehatan gigi)</label>
                            <input type="date" name="tgl_penanda_ckg" id="{{ $p }}tgl_penanda_ckg" class="form-control" value="{{ $nilai('tgl_penanda_ckg') }}">
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-12">
                        <div class="form-group">
                            <label for="{{ $p }}pemeriksaan_gigi">Hasil pemeriksaan gigi</label>
                            <select name="pemeriksaan_gigi" id="{{ $p }}pemeriksaan_gigi" class="form-control">
                                <option value="">— belum diisi —</option>
                                @foreach ($k['pemeriksaan_gigi'] as $opsi)
                                <option value="{{ $opsi }}" @selected($nilai('pemeriksaan_gigi') === $opsi)>{{ $opsi }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-12">
                        <div class="form-group">
                            <label for="{{ $p }}rujukan">Rujukan</label>
                            <select name="rujukan" id="{{ $p }}rujukan" class="form-control">
                                <option value="">— belum diisi —</option>
                                @foreach ($k['rujukan'] as $opsi)
                                <option value="{{ $opsi }}" @selected($nilai('rujukan') === $opsi)>{{ $opsi }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-12">
                        <div class="form-group">
                            <label for="{{ $p }}mt_pangan_lokal">Makanan tambahan (MT) pangan lokal</label>
                            <input type="text" name="mt_pangan_lokal" id="{{ $p }}mt_pangan_lokal" class="form-control" maxlength="100" value="{{ $nilai('mt_pangan_lokal') }}">
                        </div>
                    </div>
                    @foreach (['catatan_pengukuran' => 'Catatan perkembangan / hasil pemeriksaan', 'pemeriksaan_lainnya' => 'Hasil pemeriksaan kesehatan lainnya', 'pola_makan' => 'Pola makan anak (frekuensi, ragam menu, ASI/MPASI)', 'pola_asuh' => 'Pola asuh orang tua / keluarga', 'intervensi' => 'Intervensi spesifik/sensitif yang diberikan'] as $f => $label)
                    <div class="col-md-6 col-sm-12">
                        <div class="form-group">
                            <label for="{{ $p }}{{ $f }}">{{ $label }}</label>
                            <textarea name="{{ $f }}" id="{{ $p }}{{ $f }}" class="form-control" rows="2">{{ $nilai($f) }}</textarea>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

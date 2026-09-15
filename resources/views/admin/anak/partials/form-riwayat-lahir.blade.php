{{-- Kartu "Riwayat Kelahiran & Skrining Neonatal" — spec §3. Memuat 7 kolom lama yang belum pernah
     tampil di form (bbl, pbl, lk_lahir, usia_kehamilan_lahir, penolong_lahir, imd, komplikasi_persalinan)
     + 8 kolom neonatal baru. Tertutup default; TIDAK BOLEH ada atribut validasi HTML (wajib isi, batas angka) di dalamnya.
     Nilai penolong_lahir lama dari import yang tak ada di daftar tetap ditawarkan sebagai opsi. --}}
@php
    $anak = $anak ?? null;
    $k = config('kesmas');
    $nilai = fn (string $f) => (string) old($f, $anak->$f ?? '');
    $penolongOpsi = $k['penolong_lahir'];
    $penolongLama = $nilai('penolong_lahir');
    if ($penolongLama !== '' && !in_array($penolongLama, $penolongOpsi, true)) {
        $penolongOpsi[] = $penolongLama;
    }
@endphp
<div class="col-12 mb-3">
    <div class="card">
        <div class="card-header p-0">
            <button type="button" class="btn btn-link btn-block text-left font-weight-bold" data-toggle="collapse" data-target="#kartuRiwayatLahir" aria-expanded="false" aria-controls="kartuRiwayatLahir">
                Riwayat Kelahiran &amp; Skrining Neonatal (opsional)
            </button>
        </div>
        <div id="kartuRiwayatLahir" class="collapse">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 col-sm-12">
                        <div class="form-group">
                            <label for="bbl">Berat lahir (kg)</label>
                            <input type="number" step="0.01" name="bbl" id="bbl" class="form-control" value="{{ $nilai('bbl') }}">
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-12">
                        <div class="form-group">
                            <label for="pbl">Panjang lahir (cm)</label>
                            <input type="number" step="0.1" name="pbl" id="pbl" class="form-control" value="{{ $nilai('pbl') }}">
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-12">
                        <div class="form-group">
                            <label for="lk_lahir">Lingkar kepala lahir (cm)</label>
                            <input type="number" step="0.1" name="lk_lahir" id="lk_lahir" class="form-control" value="{{ $nilai('lk_lahir') }}">
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-12">
                        <div class="form-group">
                            <label for="usia_kehamilan_lahir">Usia kehamilan saat lahir (minggu)</label>
                            <input type="number" step="1" name="usia_kehamilan_lahir" id="usia_kehamilan_lahir" class="form-control" placeholder="20–45" value="{{ $nilai('usia_kehamilan_lahir') }}">
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-12">
                        <div class="form-group">
                            <label for="tempat_bersalin">Tempat bersalin (faskes)</label>
                            <input type="text" name="tempat_bersalin" id="tempat_bersalin" class="form-control" maxlength="150" value="{{ $nilai('tempat_bersalin') }}">
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-12">
                        <div class="form-group">
                            <label for="jenis_persalinan">Jenis persalinan</label>
                            <select name="jenis_persalinan" id="jenis_persalinan" class="form-control">
                                <option value="">— belum diisi —</option>
                                @foreach ($k['jenis_persalinan'] as $opsi)
                                <option value="{{ $opsi }}" @selected($nilai('jenis_persalinan') === $opsi)>{{ $opsi }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-12">
                        <div class="form-group">
                            <label for="penolong_lahir">Penolong persalinan</label>
                            <select name="penolong_lahir" id="penolong_lahir" class="form-control">
                                <option value="">— belum diisi —</option>
                                @foreach ($penolongOpsi as $opsi)
                                <option value="{{ $opsi }}" @selected($penolongLama === $opsi)>{{ $opsi }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @foreach (['imd' => 'Inisiasi Menyusu Dini (IMD)', 'riwayat_kek_ibu' => 'Riwayat KEK ibu saat hamil'] as $f => $label)
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
                    <div class="col-md-4 col-sm-12">
                        <div class="form-group">
                            <label for="komplikasi_persalinan">Komplikasi persalinan</label>
                            <input type="text" name="komplikasi_persalinan" id="komplikasi_persalinan" class="form-control" maxlength="255" value="{{ $nilai('komplikasi_persalinan') }}">
                        </div>
                    </div>
                    @foreach (['skrining_shk' => 'Skrining Hipotiroid Kongenital (SHK)', 'skrining_shak' => 'Skrining Hiperplasia Adrenal Kongenital (SHAK)', 'skrining_g6pd' => 'Skrining G6PD'] as $f => $label)
                    <div class="col-md-4 col-sm-12">
                        <div class="form-group">
                            <label for="{{ $f }}">{{ $label }}</label>
                            <select name="{{ $f }}" id="{{ $f }}" class="form-control">
                                <option value="">— belum diisi —</option>
                                @foreach ($k['skrining'] as $kode => $teks)
                                <option value="{{ $kode }}" @selected($nilai($f) === $kode)>{{ $teks }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @endforeach
                    <div class="col-md-4 col-sm-12">
                        <div class="form-group">
                            <label for="pemeriksaan_hepatitis_b">Pemeriksaan Hepatitis B</label>
                            <select name="pemeriksaan_hepatitis_b" id="pemeriksaan_hepatitis_b" class="form-control">
                                <option value="">— belum diisi —</option>
                                @foreach ($k['hepatitis_b'] as $kode => $teks)
                                <option value="{{ $kode }}" @selected($nilai('pemeriksaan_hepatitis_b') === $kode)>{{ $teks }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-8 col-sm-12">
                        <div class="form-group">
                            <label for="komplikasi_neonatal">Pelayanan / tindakan komplikasi neonatal</label>
                            <textarea name="komplikasi_neonatal" id="komplikasi_neonatal" class="form-control" rows="2">{{ $nilai('komplikasi_neonatal') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

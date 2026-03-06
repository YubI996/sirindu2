{{-- Section TN: Tetanus Neonatorum --}}
<div class="disease-section" data-diseases="TETANUS_NEO"
     style="{{ in_array(optional($case->jenisKasus ?? null)->kode_penyakit ?? '', ['TETANUS_NEO']) ? '' : 'display:none;' }}">

<h6 class="section-subtitle"><i class="fa fa-baby"></i> Data Ibu & Tempat Tinggal</h6>
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Sudah berapa lama Ibu tinggal di desa ini?</label>
            <input type="text" name="lama_tinggal_desa" class="form-control"
                   value="{{ old('lama_tinggal_desa', $case->lama_tinggal_desa ?? '') }}"
                   placeholder="Contoh: 5 tahun">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Apakah bayi lahir hidup?</label>
            <select name="bayi_lahir_hidup" class="form-control">
                <option value="">-- Pilih --</option>
                <option value="ya" {{ old('bayi_lahir_hidup', $case->bayi_lahir_hidup ?? '') == 'ya' ? 'selected' : '' }}>Ya</option>
                <option value="tidak" {{ old('bayi_lahir_hidup', $case->bayi_lahir_hidup ?? '') == 'tidak' ? 'selected' : '' }}>Tidak</option>
            </select>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Umur bayi meninggal (hari)</label>
            <input type="number" name="umur_bayi_meninggal_hari" class="form-control" min="0"
                   value="{{ old('umur_bayi_meninggal_hari', $case->umur_bayi_meninggal_hari ?? '') }}">
        </div>
    </div>
</div>

<hr>

<h6 class="section-subtitle"><i class="fa fa-child"></i> Kondisi Lahir</h6>
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Apakah bayi menangis saat lahir?</label>
            <select name="bayi_menangis_lahir" class="form-control">
                <option value="">-- Pilih --</option>
                <option value="ya" {{ old('bayi_menangis_lahir', $case->bayi_menangis_lahir ?? '') == 'ya' ? 'selected' : '' }}>Ya</option>
                <option value="tidak" {{ old('bayi_menangis_lahir', $case->bayi_menangis_lahir ?? '') == 'tidak' ? 'selected' : '' }}>Tidak</option>
                <option value="tidak_tahu" {{ old('bayi_menangis_lahir', $case->bayi_menangis_lahir ?? '') == 'tidak_tahu' ? 'selected' : '' }}>Tidak Tahu</option>
            </select>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Apakah terlihat tanda kelahiran hidup (gerakan)?</label>
            <select name="tanda_kelahiran_hidup" class="form-control">
                <option value="">-- Pilih --</option>
                <option value="ya" {{ old('tanda_kelahiran_hidup', $case->tanda_kelahiran_hidup ?? '') == 'ya' ? 'selected' : '' }}>Ya</option>
                <option value="tidak" {{ old('tanda_kelahiran_hidup', $case->tanda_kelahiran_hidup ?? '') == 'tidak' ? 'selected' : '' }}>Tidak</option>
                <option value="tidak_tahu" {{ old('tanda_kelahiran_hidup', $case->tanda_kelahiran_hidup ?? '') == 'tidak_tahu' ? 'selected' : '' }}>Tidak Tahu</option>
            </select>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Apakah bayi bisa menyusu?</label>
            <select name="bayi_bisa_menyusu" class="form-control">
                <option value="">-- Pilih --</option>
                <option value="ya" {{ old('bayi_bisa_menyusu', $case->bayi_bisa_menyusu ?? '') == 'ya' ? 'selected' : '' }}>Ya</option>
                <option value="tidak" {{ old('bayi_bisa_menyusu', $case->bayi_bisa_menyusu ?? '') == 'tidak' ? 'selected' : '' }}>Tidak</option>
                <option value="tidak_tahu" {{ old('bayi_bisa_menyusu', $case->bayi_bisa_menyusu ?? '') == 'tidak_tahu' ? 'selected' : '' }}>Tidak Tahu</option>
            </select>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Apakah 3 hari kemudian mulut bayi mencucu dan tidak bisa menyusu?</label>
            <select name="bayi_mulut_mencucu" class="form-control">
                <option value="">-- Pilih --</option>
                <option value="ya" {{ old('bayi_mulut_mencucu', $case->bayi_mulut_mencucu ?? '') == 'ya' ? 'selected' : '' }}>Ya</option>
                <option value="tidak" {{ old('bayi_mulut_mencucu', $case->bayi_mulut_mencucu ?? '') == 'tidak' ? 'selected' : '' }}>Tidak</option>
                <option value="tidak_tahu" {{ old('bayi_mulut_mencucu', $case->bayi_mulut_mencucu ?? '') == 'tidak_tahu' ? 'selected' : '' }}>Tidak Tahu</option>
            </select>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Apakah bayi mudah kejang jika disentuh/terkena sinar/bunyi?</label>
            <select name="bayi_mudah_kejang" class="form-control">
                <option value="">-- Pilih --</option>
                <option value="ya" {{ old('bayi_mudah_kejang', $case->bayi_mudah_kejang ?? '') == 'ya' ? 'selected' : '' }}>Ya</option>
                <option value="tidak" {{ old('bayi_mudah_kejang', $case->bayi_mudah_kejang ?? '') == 'tidak' ? 'selected' : '' }}>Tidak</option>
                <option value="tidak_tahu" {{ old('bayi_mudah_kejang', $case->bayi_mudah_kejang ?? '') == 'tidak_tahu' ? 'selected' : '' }}>Tidak Tahu</option>
            </select>
        </div>
    </div>
</div>

<hr>

<h6 class="section-subtitle"><i class="fa fa-notes-medical"></i> Kunjungan ANC (Antenatal Care)</h6>
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Berapa kali kunjungan ANC?</label>
            <input type="number" name="jumlah_kunjungan_anc" class="form-control" min="0"
                   value="{{ old('jumlah_kunjungan_anc', $case->jumlah_kunjungan_anc ?? '') }}">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Tempat pemeriksaan hamil</label>
            <input type="text" name="tempat_pemeriksaan_hamil" class="form-control"
                   value="{{ old('tempat_pemeriksaan_hamil', $case->tempat_pemeriksaan_hamil ?? '') }}"
                   placeholder="Puskesmas, Bidan, RS, dll">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Pemeriksa kehamilan</label>
            <input type="text" name="pemeriksa_kehamilan" class="form-control"
                   value="{{ old('pemeriksa_kehamilan', $case->pemeriksa_kehamilan ?? '') }}"
                   placeholder="Bidan, Dokter, dll">
        </div>
    </div>
</div>

<hr>

<h6 class="section-subtitle"><i class="fa fa-procedures"></i> Persalinan</h6>
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Tempat persalinan</label>
            <input type="text" name="tempat_persalinan" class="form-control"
                   value="{{ old('tempat_persalinan', $case->tempat_persalinan ?? '') }}"
                   placeholder="Rumah, Puskesmas, RS, dll">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Usia kehamilan saat persalinan (bulan)</label>
            <input type="number" name="usia_kehamilan_bulan" class="form-control" min="1" max="12"
                   value="{{ old('usia_kehamilan_bulan', $case->usia_kehamilan_bulan ?? '') }}">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Penolong persalinan</label>
            <input type="text" name="penolong_persalinan" class="form-control"
                   value="{{ old('penolong_persalinan', $case->penolong_persalinan ?? '') }}"
                   placeholder="Bidan, Dokter, Dukun, dll">
        </div>
    </div>
</div>

<hr>

<h6 class="section-subtitle"><i class="fa fa-cut"></i> Perawatan Tali Pusat</h6>
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Alat potong tali pusat</label>
            <input type="text" name="alat_potong_tali_pusat" class="form-control"
                   value="{{ old('alat_potong_tali_pusat', $case->alat_potong_tali_pusat ?? '') }}"
                   placeholder="Gunting steril, Pisau, dll">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Ramuan/perawatan tali pusat</label>
            <input type="text" name="perawatan_tali_pusat" class="form-control"
                   value="{{ old('perawatan_tali_pusat', $case->perawatan_tali_pusat ?? '') }}"
                   placeholder="Betadine, Ramuan tradisional, dll">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Keadaan ibu saat ini</label>
            <input type="text" name="keadaan_ibu_saat_ini" class="form-control"
                   value="{{ old('keadaan_ibu_saat_ini', $case->keadaan_ibu_saat_ini ?? '') }}"
                   placeholder="Sehat, Sakit, Meninggal, dll">
        </div>
    </div>
</div>

</div>{{-- end TN disease-section --}}

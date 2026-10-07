<?php

namespace App\Repositories\Admin\Anak;

use App\Repositories\Admin\Core\Anak\AnakRepositoryInterface;
use App\Http\Requests\Admin\Anak\KesmasRules;
use App\Models\Anak;
use App\Models\User;
use App\Models\DataAnak;
use App\Models\SasaranKesmasLog;
use App\Models\Imunisasi;
use App\Models\JenisVaksin;
use App\Services\ImunisasiStatusService;
use GuzzleHttp\Promise\Create;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

use function PHPUnit\Framework\isEmpty;

class AnakRepository implements AnakRepositoryInterface
{
    protected $anak;

    public function __contruct(
        anak $anak
    ) {
        $this->anak = $anak;
    }

    public function storeAnak($request)
    {
        // Anak, kunjungan pertama, dan log tanda sasaran ditulis sebagai satu kesatuan
        // (spec 2026-10-02 §5.2): log yang gagal tidak boleh meninggalkan anak tanpa jejak.
        return DB::transaction(fn () => $this->simpanAnakBaru($request));
    }

    private function simpanAnakBaru($request)
    {
        $lahir = strtotime($request->tgl_lahir);
        $now = strtotime(date('Y-m-d H:i:s'));
        $y1 = date('Y', $lahir);
        $y2 = date('Y', $now);
        $m1 = date('m', $lahir);
        $m2 = date('m', $now);
        $umur = (($y2 - $y1) * 12) + ($m2 - $m1);

        $sasaran = $this->sasaranTambah($request);

        $anak_baru = Anak::create(array_merge([
            'no_kk' => $request->no_kk,
            'nik' => $request->nik,
            'nama' => $request->nama,
            'nik_ortu' => $request->nik_ortu,
            'nama_ibu' => $request->nama_ibu,
            'nama_ayah' => $request->nama_ayah,
            'jk' => $request->jk,
            'tempat_lahir' => $request->tempat_lahir,
            'tgl_lahir' => $request->tgl_lahir,
            'golda' => $request->golda,
            'anak' => $request->anak,
            'no' => $request->no,
            'status' => 1,
            'id_kec' => $request->id_kec,
            'id_kel' => $request->id_kel,
            'id_rt' => $request->id_rt,
            'id_posyandu' => $request->id_posyandu,
            'id_puskesmas' => $request->id_puskesmas,
            'alamat' => $request->alamat,
            'alamat_ktp' => $request->alamat_ktp,
            'catatan' => $request->catatan ?? '',
            'sumber' => 'manual',
        ], $this->kesmasAnakAttributes($request), ['sasaran_balita_kesmas' => $sasaran]));

        DataAnak::create([
            'id_anak' => $anak_baru->id,
            'bln' => $umur,
            'posisi' => 'L',
            'tb' => $request->tb,
            'bb' => $request->bb,
            'lla' => $request->lla,
            'lk' => $request->lk,
            'ntob' => null,
            'asi' => $request->asi,
            'tgl_kunjungan' => $request->tgl_kunjungan,
            'obat_cacing' => $request->obat_cacing,
            'ddtka' => $request->ddtka,
            'id_user' => Auth::user()->id,
            'sumber' => 'manual',
        ]);

        $this->catatSasaran($anak_baru->id, null, $sasaran, 'form_tambah');

        return $anak_baru;
    }

    public function updateAnak($request, $id)
    {
        DB::transaction(fn () => $this->ubahAnak($request, $id));
    }

    private function ubahAnak($request, $id)
    {
        $lahir = strtotime($request->tgl_lahir);
        $now = strtotime(date('Y-m-d H:i:s'));
        $y1 = date('Y', $lahir);
        $y2 = date('Y', $now);
        $m1 = date('m', $lahir);
        $m2 = date('m', $now);
        $umur = (($y2 - $y1) * 12) + ($m2 - $m1);
        $anak = Anak::find($id);
        $sasaranLama = $anak->sasaran_balita_kesmas === null ? null : (int) $anak->sasaran_balita_kesmas;
        $sasaran = $this->sasaranEdit($request, $sasaranLama);
        // Anak hasil import bisa belum punya baris DataAnak; firstOrNew agar
        // save() membuat baris baru, bukan diam-diam gagal seperti update() pada model null.
        $dt = DataAnak::firstOrNew(['id_anak' => $id]);
        if ($request->id_kec == null) {
            $anak->update(array_merge([
                'no_kk' => $request->no_kk,
                'nik' => $request->nik,
                'nama' => $request->nama,
                'nik_ortu' => $request->nik_ortu,
                'nama_ibu' => $request->nama_ibu,
                'nama_ayah' => $request->nama_ayah,
                'jk' => $request->jk,
                'tempat_lahir' => $request->tempat_lahir,
                'tgl_lahir' => $request->tgl_lahir,
                'golda' => $request->golda,
                'anak' => $request->anak,
                'no' => $request->no,
                'status' => $request->status,
                'id_kec' => $anak->id_kec,
                'id_kel' => $anak->id_kel,
                'id_rt' => $anak->id_rt,
                'id_posyandu' => $anak->id_posyandu,
                'id_puskesmas' => $anak->id_puskesmas,
                'alamat' => $request->alamat,
                'alamat_ktp' => $request->alamat_ktp,
                'catatan' => $request->catatan ?? '',
            ], $this->kesmasAnakAttributes($request), $sasaran));
            $dt->fill([
                'bln' => $umur,
                'posisi' => $request->posisi ?? 'L',
                'tb' => $request->tb,
                'bb' => $request->bb,
                'lla' => $request->lla,
                'lk' => $request->lk,
                'asi' => $request->asi,
                'vit_a' => $request->vit_a,
                'pitting_edema' => $request->pitting_edema,
                'tgl_kunjungan' => $request->tgl_kunjungan,
                'id_user' => Auth::user()->id,
            ])->fill(array_merge(
                $this->kolomKunjunganTanpaInputDiEdit($request),
                $this->layananTigaKeadaan($request, ['kelas_ibu_balita', 'mbg'])
            ));
            $this->kosongkanNtobBilaBbBerubah($dt);
            $dt->save();
        } else {
            $anak->update(array_merge([
                'no_kk' => $request->no_kk,
                'nik' => $request->nik,
                'nama' => $request->nama,
                'nik_ortu' => $request->nik_ortu,
                'nama_ibu' => $request->nama_ibu,
                'nama_ayah' => $request->nama_ayah,
                'jk' => $request->jk,
                'tempat_lahir' => $request->tempat_lahir,
                'tgl_lahir' => $request->tgl_lahir,
                'golda' => $request->golda,
                'anak' => $request->anak,
                'no' => $request->no,
                'status' => $request->status,
                'id_kec' => $request->id_kec,
                'id_kel' => $request->id_kel,
                'id_rt' => $request->id_rt,
                'id_posyandu' => $request->id_posyandu,
                'id_puskesmas' => $request->id_puskesmas,
                'alamat' => $request->alamat,
                'alamat_ktp' => $request->alamat_ktp,
                'catatan' => $request->catatan ?? '',
            ], $this->kesmasAnakAttributes($request), $sasaran));
            $dt->fill([
                'bln' => $umur,
                'posisi' => $request->posisi ?? 'L',
                'tb' => $request->tb,
                'bb' => $request->bb,
                'lla' => $request->lla,
                'lk' => $request->lk,
                'asi' => $request->asi,
                'vit_a' => $request->vit_a,
                'pitting_edema' => $request->pitting_edema,
                'tgl_kunjungan' => $request->tgl_kunjungan,
                'id_user' => Auth::user()->id,
            ])->fill(array_merge(
                $this->kolomKunjunganTanpaInputDiEdit($request),
                $this->layananTigaKeadaan($request, ['kelas_ibu_balita', 'mbg'])
            ));
            $this->kosongkanNtobBilaBbBerubah($dt);
            $dt->save();
        }

        $this->catatSasaran(
            $anak->id,
            $sasaranLama,
            array_key_exists('sasaran_balita_kesmas', $sasaran) ? $sasaran['sasaran_balita_kesmas'] : $sasaranLama,
            'form_edit'
        );
    }

    public function destroyAnak($id)
    {
        try {
            $anak = Anak::find($id);
            $anak->delete();
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function storeDataAnak($request)
    {
        $anak = DataAnak::create(array_merge([
            'id_anak' => $request->id_anak,
            'tgl_kunjungan' => $request->tgl_kunjungan,
            'bln' => $request->bln,
            'posisi' => $request->posisi,
            'tb' => $request->tb,
            'bb' => $request->bb,
            'lla' => $request->lla,
            'lk' => $request->lk,
            'ntob' => null,
            'asi' => $request->asi,
            'vit_a' => $request->vit_a,
            'obat_cacing' => $request->obat_cacing,
            'ddtka' => $request->ddtka,
            'imunisasi_terakhir' => $request->imunisasi_terakhir,
            'alasan_tidak_imunisasi' => $request->alasan_tidak_imunisasi,
            'id_user' => Auth::user()->id,
            'sumber' => 'manual',
        ], $this->layananKesmasAttributes($request)));
    }

    public function updateDataAnak($request, $id)
    {
        $dataAnak = DataAnak::find($id);

        // Recompute umur (bln) bila tgl_kunjungan berubah agar tidak basi.
        $anak = Anak::find($dataAnak->id_anak);
        $bln = usia_bulan($anak?->tgl_lahir, $request->tgl_kunjungan) ?? $dataAnak->bln;

        $dataAnak->fill(array_merge([
            'bln' => $bln,
            'posisi' => normalisasi_posisi($request->posisi),
            'tb' => $request->tb,
            'bb' => $request->bb,
            'lla' => $request->lla,
            'lk' => $request->lk,
            'asi' => $request->asi,
            'vit_a' => $request->vit_a,
            'tgl_kunjungan' => $request->tgl_kunjungan,
            'obat_cacing' => $request->obat_cacing,
            'ddtka' => $request->ddtka,
            'imunisasi_terakhir' => $request->imunisasi_terakhir,
            'alasan_tidak_imunisasi' => $request->alasan_tidak_imunisasi,
            'id_user' => Auth::user()->id,
        ], $this->layananKesmasAttributes($request)));
        $this->kosongkanNtobBilaBbBerubah($dataAnak);
        $dataAnak->save();
    }

    /**
     * Kolom kunjungan yang TIDAK punya input di form Edit Anak (identitas): ddtka, obat_cacing,
     * asi_bulan_0..6. Hanya ditulis bila dikirim — kalau tidak, nilai lama (dari import/OT atau form
     * per kunjungan) dipertahankan. Dulu ditulis ulang null/false tiap kali nama anak dibetulkan, dan
     * dasbor Kesmas (SDIDTK, K2–K4) membacanya. `has()` di sini mendeteksi keberadaan field; nilai
     * boolean tetap dibaca `boolean()`. (audit Kesmas 2026-10-06, REQ-002)
     */
    private function kolomKunjunganTanpaInputDiEdit($request): array
    {
        $kolom = [];
        foreach (['obat_cacing', 'ddtka'] as $f) {
            if ($request->has($f)) {
                $kolom[$f] = $request->input($f);
            }
        }
        foreach (range(0, 6) as $i) {
            if ($request->has("asi_bulan_$i")) {
                $kolom["asi_bulan_$i"] = $request->boolean("asi_bulan_$i");
            }
        }

        return $kolom;
    }

    /**
     * Select tiga keadaan di form Edit Anak: '' = belum diisi (NULL), 1 = Ya, 0 = Tidak. Field yang tak
     * dikirim tidak disentuh. Dulu `boolean()` mengubah '' menjadi 0, sehingga menyimpan form apa pun
     * mengubah "belum diisi" menjadi "Tidak" dan menggeser pembagi "terisi" di dasbor Layanan.
     * `has()` di sini mendeteksi keberadaan field; nilainya tetap dibaca `boolean()`.
     * (audit Kesmas 2026-10-06, UX-001/REQ-003)
     */
    private function layananTigaKeadaan($request, array $kolom): array
    {
        $nilai = [];
        foreach ($kolom as $f) {
            if ($request->has($f)) {
                $nilai[$f] = $request->input($f) === null ? null : (int) $request->boolean($f);
            }
        }

        return $nilai;
    }

    /**
     * `ntob` ('T' = BB tidak naik) tak punya input di form mana pun — diisi import OT/Pengukuran dan
     * dibaca dasbor Kesmas serta PrioritasGiziService. Menyimpan form tak boleh menghapusnya; ia hanya
     * basi bila BB pada baris itu berubah. Perbandingan numerik: '8' dan '8.00' bukan perubahan.
     */
    private function kosongkanNtobBilaBbBerubah(DataAnak $dt): void
    {
        if ($dt->exists && abs((float) $dt->bb - (float) $dt->getOriginal('bb')) > 0.0001) {
            $dt->ntob = null;
        }
    }

    // ==================== ENHANCED IMUNISASI METHODS ====================

    public function getImunisasiByAnak($idAnak)
    {
        return Imunisasi::with(['jenisVaksin', 'petugas'])
            ->where('id_anak', $idAnak)
            ->orderBy('tanggal_pemberian', 'desc')
            ->get();
    }

    public function getJenisVaksin()
    {
        return JenisVaksin::aktif()->orderBy('kategori')->orderBy('usia_pemberian_min')->get();
    }

    public function storeImunisasiDetail($request)
    {
        return Imunisasi::create([
            'id_anak' => $request->id_anak,
            'id_jenis_vaksin' => $request->id_jenis_vaksin,
            'dosis' => $request->dosis ?? 1,
            'tanggal_pemberian' => $request->tanggal_pemberian,
            'tanggal_selanjutnya' => $request->tanggal_selanjutnya,
            'batch_number' => $request->batch_number,
            'lokasi_pemberian' => $request->lokasi_pemberian,
            'id_petugas' => Auth::user()->id,
            'status' => 'sudah',
            'reaksi_kipi' => $request->reaksi_kipi,
            'catatan' => $request->catatan ?? '',
        ]);
    }

    public function updateImunisasiDetail($request, $id)
    {
        $imunisasi = Imunisasi::find($id);
        $imunisasi->update([
            'id_jenis_vaksin' => $request->id_jenis_vaksin,
            'dosis' => $request->dosis ?? 1,
            'tanggal_pemberian' => $request->tanggal_pemberian,
            'tanggal_selanjutnya' => $request->tanggal_selanjutnya,
            'batch_number' => $request->batch_number,
            'lokasi_pemberian' => $request->lokasi_pemberian,
            'status' => $request->status ?? 'sudah',
            'reaksi_kipi' => $request->reaksi_kipi,
            'catatan' => $request->catatan ?? '',
        ]);
        return $imunisasi;
    }

    public function deleteImunisasiDetail($id)
    {
        $imunisasi = Imunisasi::find($id);
        return $imunisasi->delete();
    }

    public function getJadwalImunisasi($idAnak)
    {
        $anak = Anak::find($idAnak);
        return app(ImunisasiStatusService::class)->getJadwal($anak);
    }

    public function getCatchupPlan($idAnak)
    {
        $anak = Anak::find($idAnak);
        return app(ImunisasiStatusService::class)->getCatchupPlan($anak);
    }

    // ==================== KESMAS (spec 2026-09-15 §3.4) ====================

    /**
     * Kolom Kesmas & riwayat lahir di `anak`. Hanya field yang DIKIRIM form yang
     * disentuh — form/klien lama tanpa field ini tidak menimpa data menjadi null.
     */
    private function kesmasAnakAttributes($request): array
    {
        return $this->kolomKesmas($request, array_keys(KesmasRules::anak()), KesmasRules::BOOL_ANAK);
    }

    /** Layanan Kesmas per kunjungan di `data_anak` (checkbox hidden+checkbox → 0/1). */
    private function layananKesmasAttributes($request): array
    {
        return $this->kolomKesmas($request, array_keys(KesmasRules::kunjungan()), array_keys(config('kesmas.layanan')));
    }

    /**
     * `has()` di sini mendeteksi KEBERADAAN field (dikirim atau tidak), bukan membaca
     * nilainya — nilai boolean tetap dibaca lewat boolean(). '' (select "— belum diisi —") → null.
     */
    private function kolomKesmas($request, array $kolom, array $boolean): array
    {
        $out = [];
        foreach ($kolom as $f) {
            if (!$request->has($f)) {
                continue;
            }
            $v = $request->input($f);
            if ($v === null || $v === '') {
                $out[$f] = null;
                continue;
            }
            $out[$f] = in_array($f, $boolean, true) ? (int) $request->boolean($f) : $v;
        }

        return $out;
    }

    // ==================== SASARAN BALITA KESMAS (spec 2026-10-02 §5.2) ====================

    /** Tambah Anak: dikirim → 0/1 apa adanya (melepas centang default = keputusan); tak dikirim → NULL. */
    private function sasaranTambah($request): ?int
    {
        return $request->has('sasaran_balita_kesmas') ? (int) $request->boolean('sasaran_balita_kesmas') : null;
    }

    /**
     * Edit Anak: kolom yang perlu ditulis, atau [] bila tidak disentuh.
     * Anak NULL + '0' TETAP NULL — form Edit merender NULL sebagai tak tercentang, jadi '0' di situ
     * bukan keputusan petugas. Menulis 0 membuat anak lama diam-diam "dilepas" setiap kali namanya
     * dibetulkan, dan perintah kesmas:tandai-sasaran (hanya NULL → 1) tak lagi menjangkaunya.
     */
    private function sasaranEdit($request, ?int $lama): array
    {
        if (!$request->has('sasaran_balita_kesmas')) {
            return [];
        }
        $baru = (int) $request->boolean('sasaran_balita_kesmas');
        if ($lama === null && $baru === 0) {
            return [];
        }

        return ['sasaran_balita_kesmas' => $baru];
    }

    private function catatSasaran(int $idAnak, ?int $lama, ?int $baru, string $sumber): void
    {
        if ($lama === $baru) {
            return;
        }
        SasaranKesmasLog::create([
            'id_anak' => $idAnak, 'nilai_lama' => $lama, 'nilai_baru' => $baru,
            'sumber' => $sumber, 'id_user' => Auth::id(),
        ]);
    }
}

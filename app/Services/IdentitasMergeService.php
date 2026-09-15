<?php

namespace App\Services;

use App\Models\Anak;
use App\Models\AnakMergeLog;
use App\Models\AnakTautan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Penggabungan dua baris anak yang dinyatakan satu orang (spec verifikasi RT §6.3).
 * Baris OT tidak pernah dihapus; semua data anak berpindah SEBELUM baris lain dihapus
 * (FK cascade); snapshot di anak_merge_log memungkinkan pembatalan.
 */
class IdentitasMergeService
{
    public const KOLOM_IDENTITAS = ['nik', 'no_kk', 'nama', 'nama_ibu', 'nama_ayah', 'jk', 'tempat_lahir', 'tgl_lahir', 'alamat_ktp'];
    public const KOLOM_DOMISILI  = ['alamat', 'id_kec', 'id_kel', 'id_rt', 'id_posyandu', 'id_puskesmas'];
    public const KOLOM_LAIN      = ['golda', 'anak', 'catatan'];
    public const KOLOM           = [...self::KOLOM_IDENTITAS, ...self::KOLOM_DOMISILI, ...self::KOLOM_LAIN];

    public function __construct(private readonly PrioritasGiziService $prioritas)
    {
    }

    /**
     * Baris mana yang dipertahankan + nilai default per kolom, tanpa mengubah apa pun.
     *
     * @return array{tautan:AnakTautan, a:Anak, b:Anak, dipertahankan:string, kunci_dipertahankan:bool, default:array<string,string>, boleh_pilih_baris:bool}
     */
    public function pratinjau(AnakTautan $t): array
    {
        if ($t->keputusan !== 'sama' || $t->status !== 'disetujui') {
            throw new InvalidArgumentException('Hanya tautan "sama" yang sudah disetujui yang bisa digabung.');
        }
        $a = Anak::find($t->id_anak_a);
        $b = Anak::find($t->id_anak_b);
        if (!$a || !$b) {
            throw new InvalidArgumentException('Salah satu baris anak sudah tidak ada.');
        }

        $aOt = $a->sumber === 'operasi_timbang';
        $bOt = $b->sumber === 'operasi_timbang';
        if ($aOt && $bOt) {
            throw new InvalidArgumentException('Kedua baris berasal dari Operasi Timbang — tidak digabung agar populasi OT tidak berubah.');
        }

        if ($aOt || $bOt) {
            $dipertahankan = $aOt ? 'a' : 'b';
            $kunci = true;
        } else {
            $dipertahankan = ($a->sumber === 'capil' && $b->sumber !== 'capil') ? 'b' : 'a';
            $kunci = false;
        }

        $capil = $a->sumber === 'capil' ? 'a' : ($b->sumber === 'capil' ? 'b' : null);
        $nonCapil = $capil === 'a' ? 'b' : ($capil === 'b' ? 'a' : null);

        $default = [];
        foreach (self::KOLOM as $k) {
            if (in_array($k, self::KOLOM_IDENTITAS, true) && $capil) {
                $default[$k] = $capil;
            } elseif (in_array($k, self::KOLOM_DOMISILI, true) && $nonCapil) {
                $default[$k] = $nonCapil;
            } else {
                $default[$k] = $dipertahankan;
            }
        }

        return [
            'tautan'              => $t,
            'a'                   => $a,
            'b'                   => $b,
            'dipertahankan'       => $dipertahankan,
            'kunci_dipertahankan' => $kunci,
            'default'             => $default,
            'boleh_pilih_baris'   => !$kunci,
        ];
    }

    /**
     * Eksekusi penggabungan dalam satu transaksi.
     * $pilihan = ['nik' => 'a'|'b', …] (kolom di luar KOLOM diabaikan, yang tak disebut memakai default);
     * $dipertahankan 'a'|'b' hanya dihormati bila boleh_pilih_baris (tanpa baris OT).
     */
    public function gabung(AnakTautan $t, User $oleh, array $pilihan, ?string $dipertahankan = null): AnakMergeLog
    {
        $p = $this->pratinjau($t);
        $sisi = $p['dipertahankan'];
        if ($p['boleh_pilih_baris'] && in_array($dipertahankan, ['a', 'b'], true)) {
            $sisi = $dipertahankan;
        }
        /** @var Anak $keep */
        $keep = $p[$sisi];
        $sisiDrop = $sisi === 'a' ? 'b' : 'a';
        /** @var Anak $drop */
        $drop = $p[$sisiDrop];

        $final = $p['default'];
        foreach ($pilihan as $k => $v) {
            if (in_array($k, self::KOLOM, true) && in_array($v, ['a', 'b'], true)) {
                $final[$k] = $v;
            }
        }

        return DB::transaction(function () use ($t, $oleh, $keep, $drop, $sisiDrop, $final) {
            $dipindah = [
                'data_anak'       => DB::table('data_anak')->where('id_anak', $drop->id)->pluck('id')->all(),
                'imunisasi'       => DB::table('imunisasi')->where('id_anak', $drop->id)->pluck('id')->all(),
                'intervensi_gizi' => DB::table('intervensi_gizi')->where('id_anak', $drop->id)->pluck('id')->all(),
                'verifikasi_anak' => DB::table('verifikasi_anak')->where('id_anak', $drop->id)->pluck('id')->all(),
            ];
            $prioritasDrop = (array) DB::table('prioritas_gizi')->where('id_anak', $drop->id)->first();
            $tautanLain = AnakTautan::where('id', '!=', $t->id)
                ->where(fn ($q) => $q->where('id_anak_a', $drop->id)->orWhere('id_anak_b', $drop->id))
                ->whereIn('status', ['diusulkan', 'disetujui'])
                ->get(['id', 'status', 'catatan_reviu'])
                ->map(fn ($x) => ['id' => $x->id, 'status_lama' => $x->status, 'catatan_reviu_lama' => $x->catatan_reviu])
                ->all();

            $nilaiLama = [];
            foreach ([...self::KOLOM, 'sumber_gabungan', 'pj_nama', 'pj_nip', 'pj_updated_by', 'pj_updated_at'] as $k) {
                $nilaiLama[$k] = $keep->getAttribute($k);
            }

            $log = AnakMergeLog::create([
                'id_dipertahankan' => $keep->id,
                'id_dihapus'       => $drop->id,
                'id_tautan'        => $t->id,
                'oleh'             => $oleh->id,
                'snapshot'         => [
                    'anak_dihapus'             => $drop->getAttributes(),
                    'nilai_lama_dipertahankan' => $nilaiLama,
                    'pilihan'                  => $final,
                    'dipindah'                 => $dipindah,
                    'prioritas_dihapus'        => $prioritasDrop ?: null,
                    'tautan_lain'              => $tautanLain,
                    'id_anak_dipertahankan_saat_merge' => [
                        'data_anak' => DB::table('data_anak')->where('id_anak', $keep->id)->pluck('id')->all(),
                        'imunisasi' => DB::table('imunisasi')->where('id_anak', $keep->id)->pluck('id')->all(),
                    ],
                ],
            ]);

            // 1) Pindahkan SEMUA data anak sebelum menghapus (FK cascade akan menghapusnya kalau tidak).
            foreach (['data_anak', 'imunisasi', 'intervensi_gizi', 'verifikasi_anak'] as $tabel) {
                DB::table($tabel)->where('id_anak', $drop->id)->update(['id_anak' => $keep->id]);
            }
            DB::table('prioritas_gizi')->where('id_anak', $drop->id)->delete();

            // 2) Tautan lain yang memuat baris yang dihapus tak lagi bermakna.
            foreach ($tautanLain as $x) {
                AnakTautan::where('id', $x['id'])->update([
                    'status'        => 'ditolak',
                    'catatan_reviu' => "Baris digabung ke anak #{$keep->id}",
                ]);
            }

            // 3) Hapus baris yang dilebur — melepas NIK dari unique key; anak_kandidat ikut cascade.
            $drop->delete();

            // 4) Timpa kolom terpilih; sumber milik yang dipertahankan TIDAK berubah (OT tetap OT).
            $update = [];
            foreach ($final as $k => $sisiPilih) {
                if ($sisiPilih === $sisiDrop) {
                    $update[$k] = $drop->getAttribute($k);
                }
            }
            $update['sumber_gabungan'] = array_values(array_unique(array_filter(array_merge(
                [$keep->sumber, $drop->sumber],
                (array) ($keep->sumber_gabungan ?? []),
                (array) ($drop->sumber_gabungan ?? []),
            ))));
            if (!$keep->pj_nama && $drop->pj_nama) {
                $update['pj_nama']       = $drop->pj_nama;
                $update['pj_nip']        = $drop->pj_nip;
                $update['pj_updated_by'] = $drop->pj_updated_by;
                $update['pj_updated_at'] = $drop->pj_updated_at;
            }
            $keep->update($update);

            $t->update(['status' => 'digabung']);
            $this->prioritas->refreshAnak($keep->id);

            Log::info("Merge anak #{$drop->id} → #{$keep->id} (log #{$log->id}) oleh user #{$oleh->id}");

            return $log;
        });
    }

    /** Pulihkan penggabungan dari snapshot. Ditolak bila ada data baru yang tak bisa dipetakan. */
    public function batalkan(AnakMergeLog $log, User $oleh): Anak
    {
        if ($log->dibatalkan_at) {
            throw new InvalidArgumentException('Penggabungan ini sudah dibatalkan.');
        }
        $keep = Anak::find($log->id_dipertahankan);
        if (!$keep) {
            throw new InvalidArgumentException('Baris yang dipertahankan sudah tidak ada.');
        }
        $s = $log->snapshot;
        $anakLama = $s['anak_dihapus'];

        if (Anak::where('nik', $anakLama['nik'])->where('id', '!=', $keep->id)->exists()) {
            throw new InvalidArgumentException("NIK {$anakLama['nik']} kini dipakai baris lain — tidak bisa dipulihkan otomatis.");
        }

        // Baris data anak yang muncul SETELAH merge tak bisa dipetakan ke salah satu pihak → tangani manual.
        foreach (['data_anak', 'imunisasi'] as $tabel) {
            $dikenal = array_merge($s['dipindah'][$tabel] ?? [], $s['id_anak_dipertahankan_saat_merge'][$tabel] ?? []);
            $baru = DB::table($tabel)->where('id_anak', $keep->id)->whereNotIn('id', $dikenal)->count();
            if ($baru > 0) {
                throw new InvalidArgumentException("Ada {$baru} baris {$tabel} baru sejak penggabungan yang tak bisa dipetakan ke salah satu anak — tangani manual.");
            }
        }

        return DB::transaction(function () use ($log, $oleh, $keep, $s, $anakLama) {
            // 1) Pulihkan nilai lama baris yang dipertahankan DULU — melepas NIK/KK yang tadinya
            //    diambil dari baris yang dihapus, supaya insert ulang di bawah tak bentrok unique key.
            $keep->update($s['nilai_lama_dipertahankan']);

            // 2) Hidupkan kembali baris lama dengan id aslinya (kolom yang sudah tak ada di skema diabaikan).
            $kolomValid = array_flip(Schema::getColumnListing('anak'));
            DB::table('anak')->insert(array_intersect_key($anakLama, $kolomValid));

            // 3) Kembalikan data anak yang dipindah.
            foreach ($s['dipindah'] as $tabel => $ids) {
                if (!empty($ids)) {
                    DB::table($tabel)->whereIn('id', $ids)->update(['id_anak' => $anakLama['id']]);
                }
            }
            if (!empty($s['prioritas_dihapus'])) {
                $row = $s['prioritas_dihapus'];
                unset($row['id']);
                DB::table('prioritas_gizi')->where('id_anak', $anakLama['id'])->delete();
                DB::table('prioritas_gizi')->insert($row);
            }

            // 4) Tautan lain & tautan utama.
            foreach ($s['tautan_lain'] ?? [] as $x) {
                AnakTautan::where('id', $x['id'])->update(['status' => $x['status_lama'], 'catatan_reviu' => $x['catatan_reviu_lama']]);
            }
            if ($log->id_tautan) {
                AnakTautan::where('id', $log->id_tautan)->update(['status' => 'disetujui']);
            }

            $log->update(['dibatalkan_oleh' => $oleh->id, 'dibatalkan_at' => now()]);

            $this->prioritas->refreshAnak($keep->id);
            $this->prioritas->refreshAnak((int) $anakLama['id']);

            Log::info("Batal merge log #{$log->id}: anak #{$anakLama['id']} dipulihkan oleh user #{$oleh->id}");

            return Anak::findOrFail($anakLama['id']);
        });
    }
}

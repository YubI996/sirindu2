<?php

namespace App\Imports;

use App\Exceptions\BarisImportBatal;
use App\Http\Requests\Admin\Anak\KesmasRules;
use App\Models\Anak;
use App\Models\DataAnak;
use App\Services\KolomKesmasImport;
use App\Services\PenautanAnakKesmas;
use App\Support\ImportError;
use App\Support\NamaAnak;
use App\Support\NilaiSelKesmas;
use App\Support\SatuanBeratBadan;
use App\Traits\MembacaTanggalBerkas;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Import Data Kesmas (spec docs/superpowers/specs/2026-10-15-import-kesmas-design.md).
 *
 * Mengisi field Kesmas anak yang SUDAH ADA; satu baris = satu anak pada satu kunjungan (tanpa
 * tgl_kunjungan = hanya field per anak). Hanya sheet pertama yang dibaca: tanpa WithMultipleSheets
 * Maatwebsite memproses SEMUA sheet, dan sheet Petunjuk template akan terbaca sebagai data.
 */
class KesmasImport implements ToCollection, WithChunkReading, WithMultipleSheets
{
    use MembacaTanggalBerkas;

    public const KOLOM_IDENTITAS = ['nama_anak', 'jk', 'tgl_lahir', 'nama_ortu'];
    public const KOLOM_UKUR = ['bb', 'tb_cm', 'posisi', 'lk_cm', 'lila_cm', 'zscore_bb_u', 'zscore_pb_u', 'zscore_bb_pb'];
    private const KOLOM_TANGGAL = ['tgl_lahir', 'tgl_kunjungan', 'tgl_hbig', 'tgl_penanda_ckg'];

    /** kolom berkas => kolom data_anak */
    private const UKUR = [
        'bb' => 'bb', 'tb_cm' => 'tb', 'posisi' => 'posisi', 'lk_cm' => 'lk', 'lila_cm' => 'lla',
        'zscore_bb_u' => 'zscore_bb_u', 'zscore_pb_u' => 'zscore_pb_u', 'zscore_bb_pb' => 'zscore_bb_pb',
    ];

    /** Ukuran wajib untuk kunjungan baru: kolom data_anak => kolom berkas. */
    private const UKUR_WAJIB = ['bb' => 'bb', 'tb' => 'tb_cm', 'posisi' => 'posisi'];

    private int $sukses = 0;
    private int $gagal = 0;
    /** @var string[] */
    private array $pesan = [];
    /** @var array<string,int>|null kolom => indeks sel; null = header belum dibaca */
    private ?array $peta = null;
    private int $barisDibaca = 0;
    private bool $berhenti = false;
    /** @var array<string, array{anak: ?Anak, pesan: array}> hasil penautan per identitas baris */
    private array $tautan = [];
    /** @var array<int, array<string, array{0:mixed, 1:int}>> id anak => kolom => [nilai, baris] */
    private array $nilaiAnakBerkas = [];
    private PenautanAnakKesmas $penautan;

    public function __construct(private readonly int $userId)
    {
        $this->penautan = new PenautanAnakKesmas();
    }

    public function sheets(): array
    {
        return [0 => $this];
    }

    public function chunkSize(): int
    {
        return 200;
    }

    /** @return string[] */
    public static function kolomDikenal(): array
    {
        return array_merge(
            ['nik_anak'], self::KOLOM_IDENTITAS, ['tgl_kunjungan'], self::KOLOM_UKUR,
            KolomKesmasImport::kolom('kunjungan'), KolomKesmasImport::kolom('anak')
        );
    }

    public function collection(Collection $rows): void
    {
        $awal = $this->barisDibaca;
        $this->barisDibaca += $rows->count();
        if ($this->berhenti) {
            return;
        }

        $rows = $rows->map(fn ($r) => $r instanceof Collection ? $r->all() : (array) $r);

        if ($this->peta === null) {
            if (! $this->bacaHeader($rows->first() ?? [])) {
                $this->berhenti = true;

                return;
            }
            $rows = $rows->slice(1);
            $this->kunciFormatTanggal($this->nilaiTanggal($rows));
        }

        foreach ($rows as $i => $row) {
            $this->prosesBaris($awal + $i + 1, $row);
        }
    }

    public function getResults(): array
    {
        return ['success' => $this->sukses, 'error_count' => $this->gagal, 'failures' => $this->pesan];
    }

    private function bacaHeader(array $header): bool
    {
        $peta = [];
        foreach ($header as $idx => $nama) {
            $kunci = mb_strtolower(trim((string) $nama));
            if ($kunci !== '') {
                $peta[$kunci] = $idx;
            }
        }

        $hilang = array_diff(self::KOLOM_IDENTITAS, array_keys($peta));
        if ($hilang !== []) {
            $this->pesan[] = '[ERROR] Header: kolom wajib tidak ada: '.implode(', ', $hilang)
                .' — gunakan template Import Data Kesmas.';

            return false;
        }

        $dikenal = self::kolomDikenal();
        foreach (array_diff(array_keys($peta), $dikenal) as $asing) {
            $this->pesan[] = "[PERINGATAN] Header: kolom '{$asing}' tidak dikenal — diabaikan.";
        }
        $this->peta = array_intersect_key($peta, array_flip($dikenal));

        return true;
    }

    private function nilaiTanggal(Collection $rows): \Generator
    {
        foreach ($rows as $row) {
            foreach (self::KOLOM_TANGGAL as $kolom) {
                $v = $this->sel($row, $kolom);
                if (! NilaiSelKesmas::kosong($v)) {
                    yield $v;
                }
            }
        }
    }

    private function sel(array $row, string $kolom): mixed
    {
        return isset($this->peta[$kolom]) ? ($row[$this->peta[$kolom]] ?? null) : null;
    }

    /** @param string[] $kolom @return array<string,mixed> hanya sel yang tidak kosong */
    private function selKolom(array $row, array $kolom): array
    {
        $sel = [];
        foreach ($kolom as $k) {
            $v = $this->sel($row, $k);
            if (! NilaiSelKesmas::kosong($v)) {
                $sel[$k] = $v;
            }
        }

        return $sel;
    }

    private function prosesBaris(int $n, array $row): void
    {
        if ($this->selKolom($row, array_keys($this->peta)) === []) {
            return;   // baris kosong (sisa format Excel)
        }

        $nama = NilaiSelKesmas::teks($this->sel($row, 'nama_anak'));
        $label = $nama !== '' ? $nama : '-';
        $catat = function (string $tingkat, string $teks) use ($n, $label): void {
            $this->pesan[] = "[{$tingkat}] Baris {$n} ({$label}): {$teks}";
        };

        $jk = NilaiSelKesmas::jk($this->sel($row, 'jk'));
        $tglLahir = $this->bacaTanggal($this->sel($row, 'tgl_lahir'));
        $namaOrtu = NilaiSelKesmas::teks($this->sel($row, 'nama_ortu'));

        $kurang = [];
        if (NamaAnak::kosong($nama)) $kurang[] = 'nama_anak';
        if ($jk === null) $kurang[] = 'jk (L/P)';
        if ($tglLahir === null) $kurang[] = 'tgl_lahir';
        if (NamaAnak::kosong($namaOrtu)) $kurang[] = 'nama_ortu';
        if ($kurang !== []) {
            $catat('ERROR', 'kolom wajib kosong atau tidak terbaca: '.implode(', ', $kurang).' — baris dilewati.');
            $this->gagal++;

            return;
        }

        $nik = NilaiSelKesmas::nik($this->sel($row, 'nik_anak'));
        $kunci = implode('|', [mb_strtolower($nama), $jk, $tglLahir, mb_strtolower($namaOrtu), $nik]);
        $tautan = $this->tautan[$kunci] ??= $this->penautan->cari($nama, $jk, $tglLahir, $namaOrtu, $nik);
        foreach ($tautan['pesan'] as [$tingkat, $teks]) {
            $catat($tingkat, $teks);
        }
        if ($tautan['anak'] === null) {
            $this->gagal++;

            return;
        }

        try {
            $ditulis = DB::transaction(fn () => $this->tulis($n, $row, $tautan['anak'], $catat));
        } catch (BarisImportBatal $e) {
            $catat('ERROR', $e->getMessage());
            $this->gagal++;

            return;
        } catch (\Throwable $e) {
            // Model anak di-cache antarbaris: transaksi sudah di-rollback, tapi nilai fill() yang gagal
            // masih menempel di model dan akan ikut tersimpan oleh baris berikutnya anak yang sama.
            $tautan['anak']->refresh();
            $catat('ERROR', ImportError::message($e->getMessage()));
            $this->gagal++;
            Log::warning("KesmasImport baris {$n}: ".$e->getMessage());

            return;
        }

        if ($ditulis) {
            $this->sukses++;
        } else {
            $catat('PERINGATAN', 'tidak ada isian Kesmas atau kunjungan — tidak ada yang disimpan.');
        }
    }

    /** @return bool apakah ada yang ditulis */
    private function tulis(int $n, array $row, Anak $anak, callable $catat): bool
    {
        $tglSel = $this->sel($row, 'tgl_kunjungan');
        $kunjunganDitulis = false;
        if (! NilaiSelKesmas::kosong($tglSel)) {
            $kunjunganDitulis = $this->tulisKunjungan($row, $anak, $tglSel, $catat);
        } elseif ($this->selKolom($row, array_merge(self::KOLOM_UKUR, KolomKesmasImport::kolom('kunjungan'))) !== []) {
            $catat('PERINGATAN', 'kolom kunjungan (ukur/layanan) diabaikan karena tgl_kunjungan kosong.');
        }
        // Kunjungan lebih dulu: bila baris batal, field anak tidak sempat dicatat sebagai nilai berkas.
        $anakDitulis = $this->tulisAnak($n, $row, $anak, $catat);

        return $kunjunganDitulis || $anakDitulis;
    }

    private function tulisKunjungan(array $row, Anak $anak, mixed $tglSel, callable $catat): bool
    {
        $tgl = $this->bacaTanggal($tglSel);
        if ($tgl === null) {
            throw new BarisImportBatal("tgl_kunjungan '".NilaiSelKesmas::teks($tglSel)."' tidak terbaca — seluruh baris dilewati.");
        }

        $ukur = $this->ukurBerkas($row, $anak, $tgl);
        $sel = $this->selKolom($row, KolomKesmasImport::kolom('kunjungan'));
        ['nilai' => $kesmas, 'salah' => $salah] = $sel === []
            ? ['nilai' => [], 'salah' => []]
            : KolomKesmasImport::ubah('kunjungan', $sel, $anak, fn ($v) => $this->bacaTanggal($v));

        $ada = DataAnak::where('id_anak', $anak->id)->whereDate('tgl_kunjungan', $tgl)->first();

        if ($ada === null) {
            $kurang = [];
            foreach (self::UKUR_WAJIB as $kolom => $kolomBerkas) {
                if (! array_key_exists($kolom, $ukur['nilai'])) {
                    $kurang[] = $ukur['salah'][$kolom] ?? "{$kolomBerkas} wajib diisi";
                }
            }
            if ($kurang !== []) {
                throw new BarisImportBatal("kunjungan {$tgl} belum ada dan ukurannya tidak lengkap: ".implode('; ', $kurang).' — seluruh baris dilewati.');
            }
            foreach ($ukur['salah'] as $teks) {
                $catat('PERINGATAN', $teks.'; kolom dilewati.');
            }
            foreach ($salah as $teks) {
                $catat('PERINGATAN', $teks.'; kolom dilewati.');
            }

            DataAnak::create(array_merge(
                ['lk' => 0, 'lla' => 0],
                $ukur['nilai'],
                $kesmas,
                [
                    'id_anak' => $anak->id, 'tgl_kunjungan' => $tgl,
                    'bln' => usia_bulan($anak->tgl_lahir, $tgl) ?? 0, 'id_user' => $this->userId,
                ]
            ));

            return true;
        }

        foreach ($salah as $teks) {
            $catat('PERINGATAN', $teks.'; kolom dilewati.');
        }
        $beda = $this->bedaUkur($ada, $ukur['nilai']);
        if ($beda !== []) {
            $catat('PERINGATAN', "kunjungan {$tgl} sudah ada — ukuran tidak diubah: ".implode('; ', $beda).'.');
        }
        if ($kesmas === []) {
            return false;
        }
        $ada->fill($kesmas)->save();

        return true;
    }

    /** @return array{nilai: array<string,mixed>, salah: array<string,string>} kunci = kolom data_anak */
    private function ukurBerkas(array $row, Anak $anak, string $tgl): array
    {
        $nilai = [];
        $salah = [];
        foreach (self::UKUR as $kolomBerkas => $kolom) {
            $sel = $this->sel($row, $kolomBerkas);
            if (NilaiSelKesmas::kosong($sel)) {
                continue;
            }
            $teks = NilaiSelKesmas::teks($sel);

            if ($kolom === 'posisi') {
                $v = NilaiSelKesmas::posisi($sel);
                if ($v === null) {
                    $salah[$kolom] = "posisi '{$teks}' tidak dikenal (Berdiri/Terlentang)";
                } else {
                    $nilai[$kolom] = $v;
                }
                continue;
            }

            $v = NilaiSelKesmas::angka($sel);
            if ($v === null) {
                $salah[$kolom] = "{$kolomBerkas} '{$teks}' bukan angka";
                continue;
            }
            if ($kolom === 'bb') {
                $satuan = SatuanBeratBadan::untuk($anak->tgl_lahir, $tgl);
                if (! SatuanBeratBadan::dalamRentang($v, $satuan)) {
                    [$min, $maks] = SatuanBeratBadan::RENTANG[$satuan];
                    $salah[$kolom] = $satuan === SatuanBeratBadan::GRAM
                        ? "bb '{$teks}' di luar {$min}–{$maks} gram — anak berumur di bawah 2 bulan pada {$tgl}, isi berat dalam gram (mis. 3250)"
                        : "bb '{$teks}' di luar {$min}–{$maks} kg — anak berumur 2 bulan atau lebih pada {$tgl}, isi berat dalam kg";
                    continue;
                }
                $v = SatuanBeratBadan::keKg($v, $satuan);
            } elseif (in_array($kolom, ['tb', 'lk', 'lla'], true) && $v <= 0) {
                $salah[$kolom] = "{$kolomBerkas} '{$teks}' harus lebih dari 0";
                continue;
            }
            $nilai[$kolom] = $v;
        }

        return ['nilai' => $nilai, 'salah' => $salah];
    }

    /** @return string[] kalimat "bb di berkas 10,5, tersimpan 10,2" per ukuran yang berbeda */
    private function bedaUkur(DataAnak $ada, array $ukur): array
    {
        $beda = [];
        foreach ($ukur as $kolom => $v) {
            $lama = $ada->{$kolom};
            $sama = match (true) {
                $kolom === 'posisi' => normalisasi_posisi($lama) === $v,
                $lama === null => false,
                $kolom === 'bb' => SatuanBeratBadan::sama((float) $lama, (float) $v),
                default => abs((float) $lama - (float) $v) < 0.005,
            };
            if (! $sama) {
                $beda[] = "{$kolom} di berkas ".self::tampil($v).', tersimpan '.($lama === null ? 'kosong' : self::tampil($lama));
            }
        }

        return $beda;
    }

    private static function tampil(mixed $v): string
    {
        return is_numeric($v) ? str_replace('.', ',', (string) (0 + $v)) : (string) $v;
    }

    private function tulisAnak(int $n, array $row, Anak $anak, callable $catat): bool
    {
        $sel = $this->selKolom($row, KolomKesmasImport::kolom('anak'));
        if ($sel === []) {
            return false;
        }

        ['nilai' => $nilai, 'salah' => $salah] = KolomKesmasImport::ubah('anak', $sel, $anak, fn ($v) => $this->bacaTanggal($v));
        foreach ($salah as $teks) {
            $catat('PERINGATAN', $teks.'; kolom dilewati.');
        }
        if ($nilai === []) {
            return false;
        }

        $anak->fill($nilai)->save();

        // Dicatat SETELAH tersimpan: nilai baris yang gagal bukan "nilai berkas" pembanding baris berikutnya.
        $label = KesmasRules::atribut();
        foreach ($nilai as $kolom => $v) {
            $lama = $this->nilaiAnakBerkas[$anak->id][$kolom] ?? null;
            if ($lama !== null && (string) $lama[0] !== (string) $v) {
                $catat('PERINGATAN', ($label[$kolom] ?? $kolom)." berbeda dengan baris {$lama[1]} ('{$lama[0]}' → '{$v}') — nilai baris ini yang dipakai.");
            }
            $this->nilaiAnakBerkas[$anak->id][$kolom] = [$v, $n];
        }

        return true;
    }
}

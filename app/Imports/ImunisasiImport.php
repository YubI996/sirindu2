<?php

namespace App\Imports;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\Imunisasi;
use App\Models\JenisVaksin;
use App\Support\ImportError;
use App\Traits\ResolvesAnakByTwoOfThree;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithStartRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Import data imunisasi dari CSV. Dua format dideteksi otomatis dari header:
 *  - LONG : ada kolom `kode_vaksin` → 1 baris = 1 vaksin per anak (format lama).
 *  - WIDE : tiap vaksin jadi kolom berisi tanggal pemberian; kolom opsional
 *           `alasan_tidak_imunisasi` ditulis ke data_anak.
 *
 * Identifikasi anak: logika 2-dari-3 (NIK, nama, tgl_lahir).
 * Upsert Imunisasi by (id_anak, id_jenis_vaksin).
 */
class ImunisasiImport implements ToCollection, WithStartRow, WithChunkReading
{
    use ResolvesAnakByTwoOfThree;

    /** Kolom format wide yang bukan kode vaksin; sisanya di header dianggap kode vaksin. */
    private const KOLOM_BUKAN_VAKSIN = ['nik_anak', 'nama_anak', 'tgl_lahir_anak', 'alasan_tidak_imunisasi'];

    protected int $userId;
    protected int $successCount = 0;
    protected int $errorCount   = 0;
    protected array $failures   = [];
    protected int $rowOffset    = 0;

    /** Baris data terisi (bukan header/komentar/kosong) yang dibaca — untuk ringkasan. */
    protected int $rowsRead = 0;
    /** Baris yang dilewati utuh dengan [PERINGATAN] (identitas kurang, kode vaksin kosong/asing). */
    protected int $skippedCount = 0;
    /** id anak yang minimal satu vaksinnya tersimpan; ringkasan menyebut jumlah anak, bukan hanya vaksin. */
    protected array $anakTersimpan = [];
    /** Kode vaksin format long yang tak ada di master, unik, urut kemunculan — dilaporkan sekali di akhir. */
    protected array $kodeTakDikenal = [];

    protected ?array $columnMap    = null;
    protected int    $headerRowIdx = 0;

    /** Mode wide (true) / long (false). Ditentukan sekali di chunk pertama, persist antar-chunk. */
    protected bool $wideMode = false;

    /** Mode wide: peta kolom vaksin -> header_lowercase => id_jenis_vaksin. */
    protected array $vaksinColumns = [];

    /** Cache kode_vaksin (UPPERCASE) → id_jenis_vaksin */
    protected array $vaksinCache = [];

    protected array $validStatuses = ['belum', 'sudah', 'terlambat'];

    public function __construct(int $userId)
    {
        $this->userId = $userId;
        $this->vaksinCache = JenisVaksin::pluck('id', 'kode')->toArray();
    }

    public function startRow(): int { return 1; }
    public function chunkSize(): int { return 500; }

    // =========================================================================
    // Parse helpers
    // =========================================================================

    protected function parseDate($value): ?string
    {
        if ($value === null || $value === '') return null;
        if (is_numeric($value)) {
            try { return Carbon::instance(Date::excelToDateTimeObject((float) $value))->format('Y-m-d'); }
            catch (\Exception $e) { return null; }
        }
        try { return Carbon::parse((string) $value)->format('Y-m-d'); }
        catch (\Exception $e) { return null; }
    }

    protected function parseStatus($value): string
    {
        $val = strtolower(trim((string) ($value ?? '')));
        return in_array($val, $this->validStatuses) ? $val : 'belum';
    }

    protected function parseIntVal($value): ?int
    {
        if ($value === null || $value === '') return null;
        return is_numeric($value) ? (int) $value : null;
    }

    // =========================================================================
    // Main processor — deteksi format & dispatch
    // =========================================================================

    public function collection(Collection $rows)
    {
        $isFirstChunk = $this->rowOffset === 0;
        $originalSize = count($rows);

        if ($isFirstChunk) {
            $detected = $this->detectImportHeader($rows);
            if ($detected === null) {
                $this->failures[] = '[ERROR] Header tidak ditemukan. Baris pertama yang tidak diawali \'#\' harus berisi nama kolom: '
                    . 'nik_anak, nama_anak, tgl_lahir_anak, lalu satu kolom per kode vaksin (HB0, BCG, POLIO1, …) berisi tanggal pemberian — '
                    . 'atau kolom kode_vaksin untuk format lama (1 baris per vaksin).';
                $this->rowOffset += $originalSize;
                return;
            }
            [$this->headerRowIdx, $this->columnMap] = $detected;
            // Header dengan kolom kode_vaksin = format long lama; selain itu wide.
            $this->wideMode = !array_key_exists('kode_vaksin', $this->columnMap);
            if ($this->wideMode) {
                $this->vaksinColumns = $this->detectVaksinColumns($this->columnMap);
                $this->laporkanKolomHeader($this->columnMap);
            }
            $rows = $rows->slice($this->headerRowIdx + 1)->values();
        }

        $map        = $this->columnMap ?? [];
        $baseOffset = $isFirstChunk ? ($this->headerRowIdx + 1) : 0;

        foreach ($rows as $index => $row) {
            $rowNum = $this->rowOffset + $index + 1 + ($isFirstChunk ? $baseOffset : 0);
            if (!$this->barisTerisi($row)) continue; // baris kosong (mis. trailing newline) tak dihitung
            $this->rowsRead++;
            if ($this->wideMode) {
                $this->processWideRow($row, $map, $rowNum);
            } else {
                $this->processLongRow($row, $map, $rowNum);
            }
        }

        $this->rowOffset += $originalSize;
    }

    // =========================================================================
    // LONG — format lama (dipindah dari collection, logika tak berubah)
    // =========================================================================

    protected function processLongRow($row, array $map, int $rowNum): void
    {
        $nikAnakRaw      = (string) ($this->colVal($row, $map, 'nik_anak') ?? '');
        $namaAnakRaw     = (string) ($this->colVal($row, $map, 'nama_anak') ?? '');
        $tglLahirAnakRaw = $this->colVal($row, $map, 'tgl_lahir_anak');
        $tglLahirAnak    = $this->parseDate($tglLahirAnakRaw);

        if (!$this->identitasCukup($nikAnakRaw, $namaAnakRaw, $tglLahirAnak, $tglLahirAnakRaw, $rowNum)) {
            return;
        }

        $kodeVaksin = strtoupper(trim((string) ($this->colVal($row, $map, 'kode_vaksin') ?? '')));
        if (empty($kodeVaksin)) {
            $this->failures[] = "[PERINGATAN] Baris {$rowNum}: kolom kode_vaksin kosong — baris dilewati. Isi kode vaksin (mis. HB0, BCG, POLIO1).";
            $this->skippedCount++;
            return;
        }

        try {
            $result = $this->resolveAnakByTwoOfThree($nikAnakRaw, $namaAnakRaw, $tglLahirAnak);
            $label  = $namaAnakRaw ?: $nikAnakRaw;

            if ($result['anak'] === null) {
                $this->failures[] = $this->pesanAnakTidakDitemukan($result, $rowNum, $label, $nikAnakRaw, $namaAnakRaw, $tglLahirAnak);
                $this->errorCount++;
                return;
            }
            $this->catatInfoPencocokan($result, $rowNum, $label, $nikAnakRaw);

            $anak = $result['anak'];

            $idVaksin = $this->vaksinCache[$kodeVaksin] ?? null;
            if (!$idVaksin) {
                $this->failures[] = "[PERINGATAN] Baris {$rowNum} ({$label}): Kode vaksin '{$kodeVaksin}' tidak ditemukan di master data — baris dilewati.";
                $this->kodeTakDikenal[$kodeVaksin] = true;
                $this->skippedCount++;
                return;
            }

            Imunisasi::updateOrCreate(
                ['id_anak' => $anak->id, 'id_jenis_vaksin' => $idVaksin],
                [
                    'dosis'               => $this->parseIntVal($this->colVal($row, $map, 'dosis')) ?? 1,
                    'tanggal_pemberian'   => $this->parseDate($this->colVal($row, $map, 'tanggal_pemberian')),
                    'tanggal_selanjutnya' => $this->parseDate($this->colVal($row, $map, 'tanggal_selanjutnya')),
                    'batch_number'        => $this->colVal($row, $map, 'batch_number'),
                    'lokasi_pemberian'    => $this->colVal($row, $map, 'lokasi_pemberian'),
                    'status'              => $this->parseStatus($this->colVal($row, $map, 'status')),
                    'reaksi_kipi'         => $this->colVal($row, $map, 'reaksi_kipi'),
                    'catatan'             => $this->colVal($row, $map, 'catatan'),
                    'id_petugas'          => $this->userId,
                ]
            );

            $this->successCount++;
            $this->anakTersimpan[$anak->id] = true;

        } catch (\Exception $e) {
            $label = $namaAnakRaw ?: $nikAnakRaw;
            $this->failures[] = "[ERROR] Baris {$rowNum} ({$label}, {$kodeVaksin}): " . $this->simplifyError($e->getMessage());
            $this->errorCount++;
            Log::warning("ImunisasiImport skip baris {$rowNum}: " . $e->getMessage());
        }
    }

    // =========================================================================
    // WIDE — format baru
    // =========================================================================

    /** Dari columnMap, ambil kolom yang headernya cocok kode vaksin master. */
    protected function detectVaksinColumns(array $map): array
    {
        $cols = [];
        foreach ($map as $key => $idx) {
            $kode = strtoupper(trim((string) $key));
            if (isset($this->vaksinCache[$kode])) {
                $cols[$key] = $this->vaksinCache[$kode]; // key = header lowercase (key di columnMap)
            }
        }
        return $cols;
    }

    protected function processWideRow($row, array $map, int $rowNum): void
    {
        $nikAnakRaw      = (string) ($this->colVal($row, $map, 'nik_anak') ?? '');
        $namaAnakRaw     = (string) ($this->colVal($row, $map, 'nama_anak') ?? '');
        $tglLahirAnakRaw = $this->colVal($row, $map, 'tgl_lahir_anak');
        $tglLahirAnak    = $this->parseDate($tglLahirAnakRaw);

        if (!$this->identitasCukup($nikAnakRaw, $namaAnakRaw, $tglLahirAnak, $tglLahirAnakRaw, $rowNum)) {
            return;
        }

        try {
            $result = $this->resolveAnakByTwoOfThree($nikAnakRaw, $namaAnakRaw, $tglLahirAnak);
            $label  = $namaAnakRaw ?: $nikAnakRaw;

            if ($result['anak'] === null) {
                $this->failures[] = $this->pesanAnakTidakDitemukan($result, $rowNum, $label, $nikAnakRaw, $namaAnakRaw, $tglLahirAnak);
                $this->errorCount++;
                return;
            }
            $this->catatInfoPencocokan($result, $rowNum, $label, $nikAnakRaw);

            $anak = $result['anak'];

            // Upsert tiap vaksin yang selnya berisi tanggal valid. Sel kosong dilewati;
            // sel terisi tapi tak terbaca dikumpulkan lalu dilaporkan sekali per baris.
            $takTerbaca = [];
            $adaTanggal = false;
            foreach ($this->vaksinColumns as $key => $idVaksin) {
                $raw = $this->colVal($row, $map, $key);
                if ($raw === null) continue;
                $tgl = $this->parseDate($raw);
                if ($tgl === null) {
                    $takTerbaca[] = strtoupper($key) . " ('" . trim((string) $raw) . "')";
                    continue;
                }
                Imunisasi::updateOrCreate(
                    ['id_anak' => $anak->id, 'id_jenis_vaksin' => $idVaksin],
                    [
                        'tanggal_pemberian' => $tgl,
                        'status'            => 'sudah',
                        'id_petugas'        => $this->userId,
                    ]
                );
                $this->successCount++;
                $this->anakTersimpan[$anak->id] = true;
                $adaTanggal = true;
            }
            if ($takTerbaca !== []) {
                $this->failures[] = "[PERINGATAN] Baris {$rowNum} ({$label}): tanggal tidak terbaca pada " . implode(', ', $takTerbaca)
                    . ' — vaksin itu dilewati. Gunakan format YYYY-MM-DD.';
            }

            // Kolom trailing alasan_tidak_imunisasi → tulis ke data_anak.
            $alasan = $this->colVal($row, $map, 'alasan_tidak_imunisasi');
            if (!empty($alasan)) {
                $this->writeAlasanTidakImunisasi($anak, trim((string) $alasan));
            }

            // Anak ketemu tapi tak ada apa pun yang disimpan: beri tahu, supaya selisih
            // "baris dibaca" vs "vaksin disimpan" di ringkasan bisa dijelaskan.
            if (!$adaTanggal && $takTerbaca === [] && empty($alasan) && $this->vaksinColumns !== []) {
                $this->failures[] = "[INFO] Baris {$rowNum} ({$label}): tidak ada tanggal vaksin maupun alasan_tidak_imunisasi yang terisi — tidak ada yang disimpan untuk baris ini.";
            }

        } catch (\Exception $e) {
            $label = $namaAnakRaw ?: $nikAnakRaw;
            $this->failures[] = "[ERROR] Baris {$rowNum} ({$label}): " . $this->simplifyError($e->getMessage());
            $this->errorCount++;
            Log::warning("ImunisasiImport wide skip baris {$rowNum}: " . $e->getMessage());
        }
    }

    /**
     * Tulis alasan ke data_anak kunjungan terakhir anak. Bila anak belum punya
     * data_anak, buat baris minimal (bb/tb/lla/lk = 0) — query stunting memfilter
     * bb>0 AND tb>0 sehingga baris ini tidak mencemari prevalensi.
     */
    protected function writeAlasanTidakImunisasi(Anak $anak, string $alasan): void
    {
        $latest = DataAnak::where('id_anak', $anak->id)
            ->orderByDesc('tgl_kunjungan')
            ->orderByDesc('id')
            ->first();

        if ($latest) {
            $latest->update(['alasan_tidak_imunisasi' => $alasan]);
            return;
        }

        $today = Carbon::today();
        $bln   = $anak->tgl_lahir
            ? (int) abs(Carbon::parse($anak->tgl_lahir)->diffInMonths($today))
            : 0;

        DataAnak::create([
            'id_anak'                => $anak->id,
            'tgl_kunjungan'          => $today->format('Y-m-d'),
            'bln'                    => $bln,
            'posisi'                 => $bln < 24 ? 'terlentang' : 'berdiri',
            'tb'                     => 0,
            'bb'                     => 0,
            'lla'                    => 0,
            'lk'                     => 0,
            'id_user'                => $this->userId,
            'alasan_tidak_imunisasi' => $alasan,
            'sumber'                 => 'imunisasi',
        ]);
    }

    protected function simplifyError(string $message): string
    {
        return ImportError::message($message);
    }

    // =========================================================================
    // Pesan untuk petugas — sebut sebab dan cara memperbaikinya
    // =========================================================================

    /** Ada sel terisi di baris ini? Baris kosong tidak dihitung maupun dilaporkan. */
    protected function barisTerisi($row): bool
    {
        foreach ($row as $v) {
            if (trim((string) $v) !== '') return true;
        }
        return false;
    }

    /** Daftar kode vaksin master, untuk disebut di pesan header/kode asing. */
    protected function daftarKodeSah(): string
    {
        return implode(', ', array_keys($this->vaksinCache));
    }

    /**
     * Format wide: laporkan header yang tidak dikenali. Tanpa satu pun kolom vaksin,
     * import pasti 0 vaksin — dulu diam saja dan tampak "berhasil".
     */
    protected function laporkanKolomHeader(array $map): void
    {
        $kutip = fn (string $k) => "'{$k}'";

        if ($this->vaksinColumns === []) {
            $this->failures[] = '[ERROR] Tidak ada kolom vaksin yang dikenali di header, sehingga tidak ada tanggal imunisasi yang bisa disimpan. '
                . 'Kolom yang ada: ' . implode(', ', array_map($kutip, array_keys($map))) . '. '
                . 'Nama kolom vaksin harus sama persis dengan kode master: ' . $this->daftarKodeSah() . '.';
            return;
        }

        $takDikenal = [];
        foreach (array_keys($map) as $key) {
            if (in_array($key, self::KOLOM_BUKAN_VAKSIN, true) || isset($this->vaksinColumns[$key])) continue;
            $takDikenal[] = $kutip($key);
        }
        if ($takDikenal !== []) {
            $this->failures[] = '[PERINGATAN] Kolom ' . implode(', ', $takDikenal) . ' tidak dikenali sebagai kode vaksin dan diabaikan. '
                . 'Kode yang sah: ' . $this->daftarKodeSah() . '.';
        }
    }

    /**
     * Aturan 2-dari-3 (NIK, nama, tgl lahir). Bila kurang: tulis peringatan yang
     * menyebut kolom mana yang terisi/kosong (dan tgl lahir yang tak terbaca), lalu false.
     */
    protected function identitasCukup(string $nik, string $nama, ?string $tglLahir, $tglLahirRaw, int $rowNum): bool
    {
        $status = ['nik_anak' => $nik !== '', 'nama_anak' => $nama !== '', 'tgl_lahir_anak' => $tglLahir !== null];
        if (count(array_filter($status)) >= 2) return true;

        $terisi = array_keys(array_filter($status));
        $kosong = array_keys(array_filter($status, fn ($ada) => !$ada));
        $tglTakTerbaca = $tglLahir === null && trim((string) $tglLahirRaw) !== '';
        if ($tglTakTerbaca) {
            $kosong = array_values(array_diff($kosong, ['tgl_lahir_anak']));
        }

        $rincian = 'terisi: ' . ($terisi ? implode(', ', $terisi) : 'tidak ada');
        if ($kosong) $rincian .= '; kosong: ' . implode(', ', $kosong);
        if ($tglTakTerbaca) $rincian .= "; tgl_lahir_anak '" . trim((string) $tglLahirRaw) . "' tidak terbaca, pakai YYYY-MM-DD";

        $this->failures[] = "[PERINGATAN] Baris {$rowNum}: identitas anak kurang ({$rincian}). "
            . 'Isi minimal 2 dari nik_anak, nama_anak, tgl_lahir_anak — baris dilewati.';
        $this->skippedCount++;
        return false;
    }

    /** NIK terisi tapi bukan 15–16 digit angka (mis. "3.2E+15" hasil pembulatan Excel) — mengikuti aturan ResolvesAnakByTwoOfThree. */
    protected function nikRusak(string $nik): bool
    {
        if ($nik === '') return false;
        $n = substr(trim($nik), 0, 16);
        return !(ctype_digit($n) && strlen($n) >= 15);
    }

    protected function pesanAnakTidakDitemukan(array $result, int $rowNum, string $label, string $nik, string $nama, ?string $tglLahir): string
    {
        if ($result['match'] === 'ambigu') {
            return "[ERROR] Baris {$rowNum} ({$label}): " . $result['warning'];
        }

        $dicari = [];
        if ($nik !== '' && !$this->nikRusak($nik)) $dicari[] = "NIK {$nik}";
        if ($nama !== '') $dicari[] = "nama '{$nama}'";
        if ($tglLahir !== null) $dicari[] = "tgl lahir {$tglLahir}";

        $pesan = "[ERROR] Baris {$rowNum} ({$label}): Anak tidak ditemukan — dicari dengan " . implode(', ', $dicari) . '. '
            . 'Pastikan data anak sudah diimport lebih dulu, atau periksa ejaan nama dan tanggal lahir.';
        if ($this->nikRusak($nik)) {
            $pesan .= " NIK '{$nik}' bukan 15–16 digit angka sehingga tidak dipakai mencari (simpan kolom NIK sebagai teks di Excel agar tidak dibulatkan).";
        }
        return $pesan;
    }

    /** Anak ketemu, tapi lewat jalur cadangan — catat supaya petugas tahu datanya perlu dibetulkan. */
    protected function catatInfoPencocokan(array $result, int $rowNum, string $label, string $nik): void
    {
        if ($result['warning']) {
            $this->failures[] = "[INFO] Baris {$rowNum}: " . $result['warning'];
        }
        if ($this->nikRusak($nik)) {
            $this->failures[] = "[INFO] Baris {$rowNum} ({$label}): NIK '{$nik}' bukan 15–16 digit angka "
                . '(simpan kolom NIK sebagai teks di Excel agar tidak dibulatkan); anak dicocokkan lewat nama+tgl_lahir.';
        }
    }

    /** Baris pertama di "Lihat detail error": angka-angka yang menjelaskan hitungan di Riwayat Import + arti awalan. */
    protected function ringkasan(): string
    {
        return "Ringkasan: {$this->rowsRead} baris data dibaca, {$this->successCount} vaksin disimpan/diperbarui untuk "
            . count($this->anakTersimpan) . " anak, {$this->errorCount} baris gagal, {$this->skippedCount} baris dilewati. "
            . 'Arti awalan: [ERROR] baris gagal disimpan; [PERINGATAN] baris atau sebagian isinya dilewati; [INFO] catatan saja, data tetap tersimpan.';
    }

    public function getResults(): array
    {
        $catatanAkhir = [];
        if ($this->kodeTakDikenal !== []) {
            $catatanAkhir[] = '[INFO] Kode vaksin yang tidak dikenal: ' . implode(', ', array_keys($this->kodeTakDikenal))
                . '. Kode yang sah: ' . $this->daftarKodeSah() . '.';
        }

        return [
            'success'     => $this->successCount,
            'error_count' => $this->errorCount,
            'failures'    => array_merge([$this->ringkasan()], $this->failures, $catatanAkhir),
        ];
    }
}

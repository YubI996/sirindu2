<?php

namespace App\Services;

use App\Models\Anak;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Menggabungkan duplikat hasil import Capil.
 *
 * Konteks: import Capil membuat ~4.5k record "baru" (NIK asli dari Capil, id_kel NULL,
 * tanpa data kesehatan). Sebagian sebenarnya kembaran dari anak sigizi yang "belum
 * tersentuh" (NIK salah/dummy) — gagal dicocokkan karena NIK beda DAN ejaan nama
 * beda tipis (dua identifier meleset, sehingga 2-dari-3 tak terpenuhi).
 *
 * Aturan jodoh (dikalibrasi via kontrol positif/negatif, FP ~0,2%):
 *   nama anak >= 70%  DAN  (No KK sama  ATAU  nama ortu [ibu/ayah, dipecah '/'] >= 87%).
 *
 * Merge: identitas/kependudukan ikut Capil (otoritatif), domisili + kesehatan ikut
 * sigizi; record Capil yang terserap dihapus.
 *
 * Catatan: nama ayah pada sigizi sering tergabung di kolom `nama_ibu` ("IBU / AYAH"),
 * maka pencocokan ortu memecah pada '/' dan membandingkan seluruh segmen silang.
 */
class CapilDedupService
{
    /** Ambang minimum kemiripan nama anak saat tgl lahir TEPAT sama (persen). */
    public const CHILD_MIN = 70.0;

    /** Ambang minimum kemiripan nama anak saat tgl lahir MELESET (lebih ketat, cegah sibling/kembar). */
    public const CHILD_NEAR_MIN = 90.0;

    /** Ambang minimum kemiripan nama ortu (persen). */
    public const PARENT_MIN = 87.0;

    /** Toleransi selisih tanggal lahir (hari) untuk menoleransi typo tanggal. */
    public const DATE_TOLERANCE_DAYS = 1;

    /**
     * Ambang "kemiripan sangat tinggi": bila nama anak >= CHILD_STRONG DAN nama ortu
     * >= PARENT_STRONG, identitas dianggap sangat kuat sehingga selisih tanggal lahir
     * DIABAIKAN (menoleransi typo tahun/bulan). No KK sama saja TIDAK cukup membuka
     * jendela ini — harus lewat kemiripan nama+ortu, agar sibling (KK sama, nama ortu
     * sama, tapi anak beda) tidak ikut tergabung.
     */
    public const CHILD_STRONG = 95.0;
    public const PARENT_STRONG = 95.0;

    /** Panjang prefiks nama untuk blocking-index pencarian lintas-tanggal (kinerja). */
    private const NAME_BLOCK_LEN = 3;

    // =========================================================================
    // Identifikasi kelompok
    // =========================================================================

    /**
     * Record Capil-baru sejati: dibuat oleh Capil (alamat_ktp terisi) TANPA basis sigizi
     * di bawahnya — domisili (alamat) NULL & kelurahan (id_kel) NULL. Ini memisahkan
     * record yang benar-benar baru dari record sigizi yang sekadar di-update Capil
     * (yang sudah punya alamat/kelurahan dan tak boleh ikut digabung lagi).
     */
    public function capilNew(): Collection
    {
        return Anak::whereNotNull('alamat_ktp')
            ->whereNull('alamat')
            ->whereNull('id_kel')
            ->get();
    }

    /** Record sigizi belum tersentuh Capil: alamat_ktp NULL & belum pernah di-update (updated_at == created_at). */
    public function sigiziUntouched(): Collection
    {
        return Anak::whereNull('alamat_ktp')
            ->whereColumn('updated_at', 'created_at')
            ->get();
    }

    /**
     * Ekspor seluruh record sigizi-belum-tersentuh ke berkas CSV.
     * Mengembalikan jumlah baris data yang ditulis (tanpa header).
     *
     * Catatan kerahasiaan: data ditulis langsung ke berkas; pemanggil tidak boleh
     * membaca isinya ke dalam log/percakapan — cukup laporkan jumlah baris & path.
     */
    public function exportSigiziUntouched(string $path): int
    {
        return $this->writeAnakCsv($path, $this->sigiziUntouched());
    }

    /**
     * Ekspor record sigizi-belum-tersentuh yang TIDAK punya padanan di Capil
     * (sisa setelah dedup): himpunan sigiziUntouched dikurangi yang masuk pasangan.
     * Inilah anak sigizi yang tetap "yatim" — perlu verifikasi data tersendiri.
     */
    public function exportSigiziUnpaired(string $path): int
    {
        $pairedSigiziIds = [];
        foreach ($this->findPairs($this->capilNew(), $this->sigiziUntouched()) as $p) {
            $pairedSigiziIds[$p['sigizi']->id] = true;
        }

        $unpaired = $this->sigiziUntouched()
            ->reject(fn($row) => isset($pairedSigiziIds[$row->id]));

        return $this->writeAnakCsv($path, $unpaired);
    }

    /**
     * Ekspor KANDIDAT dedup longgar berbasis kemiripan NAMA ANAK saja (>= $minChild persen),
     * untuk perburuan manual calon lain. Membandingkan sisa Capil-baru x sisa sigizi
     * (yang BELUM masuk 428 pasangan terkonfirmasi), mengabaikan tanggal/ortu/KK pada
     * filter — tetapi menampilkan kolom konteks (parent_sim, selisih hari, KK sama) supaya
     * bisa dinilai mata manusia. BUKAN untuk auto-merge. Urut nama termirip dulu.
     */
    public function exportNameCandidates(string $path, float $minChild = 80.0): int
    {
        $rows = $this->nameCandidateRows($minChild);

        $fh = fopen($path, 'w');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, $this->nameCandidateHeader());

        foreach ($rows as $r) {
            fputcsv($fh, $this->nameCandidateLine($r));
        }

        fclose($fh);

        return count($rows);
    }

    /**
     * Ekspor kandidat dedup longgar ke XLSX. Sama isinya dengan exportNameCandidates,
     * tapi kolom NIK & No.KK ditulis sebagai TEKS (DataType string) agar Excel tak
     * merusak angka 16-digit menjadi notasi ilmiah / membuang angka depan.
     */
    public function exportNameCandidatesXlsx(string $path, float $minChild = 80.0): int
    {
        $rows   = $this->nameCandidateRows($minChild);
        $header = $this->nameCandidateHeader();

        // Kolom yang harus dipaksa teks (indeks 0-based sesuai urutan header).
        $textCols = [];
        foreach (['nik_sigizi', 'no_kk_sigizi', 'nik_capil', 'no_kk_capil'] as $name) {
            $textCols[] = array_search($name, $header, true);
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($header as $i => $title) {
            $sheet->setCellValueExplicit(
                [$i + 1, 1],
                $title,
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );
        }

        $rowNum = 2;
        foreach ($rows as $r) {
            $line = $this->nameCandidateLine($r);
            foreach ($line as $i => $value) {
                if (in_array($i, $textCols, true)) {
                    $sheet->setCellValueExplicit([$i + 1, $rowNum], (string) $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue([$i + 1, $rowNum], $value);
                }
            }
            $rowNum++;
        }

        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return count($rows);
    }

    /** Header kolom kandidat dedup (dipakai bersama CSV & XLSX). */
    private function nameCandidateHeader(): array
    {
        return [
            'child_sim', 'parent_sim', 'selisih_hari', 'kk_sama',
            'id_sigizi', 'nik_sigizi', 'nama_sigizi', 'tgl_lahir_sigizi', 'no_kk_sigizi', 'nama_ibu_sigizi', 'nama_ayah_sigizi',
            'id_capil', 'nik_capil', 'nama_capil', 'tgl_lahir_capil', 'no_kk_capil', 'nama_ibu_capil', 'nama_ayah_capil',
        ];
    }

    /** Satu baris nilai kandidat (urutan cocok dengan nameCandidateHeader). */
    private function nameCandidateLine(array $r): array
    {
        $s = $r['s'];
        $c = $r['c'];
        return [
            round($r['child'], 1), round($r['parent'], 1), $r['diff'] === PHP_INT_MAX ? '' : $r['diff'], $r['kk'],
            $s->id, $s->nik, $s->nama, $s->tgl_lahir, $s->no_kk, $s->nama_ibu, $s->nama_ayah,
            $c->id, $c->nik, $c->nama, $c->tgl_lahir, $c->no_kk, $c->nama_ibu, $c->nama_ayah,
        ];
    }

    /**
     * Bangun daftar baris kandidat dedup longgar (nama anak >= $minChild persen), dari sisa
     * Capil-baru x sisa sigizi yang BELUM masuk 428 pasangan terkonfirmasi. Mengabaikan
     * tanggal/ortu/KK pada filter, tapi menghitungnya sebagai kolom konteks. Urut nama termirip dulu.
     *
     * @return array<int, array{child:float, parent:float, diff:int, kk:int, s:Anak, c:Anak}>
     */
    private function nameCandidateRows(float $minChild): array
    {
        $usedCapil = [];
        $usedSigizi = [];
        foreach ($this->findPairs($this->capilNew(), $this->sigiziUntouched()) as $p) {
            $usedCapil[$p['capil']->id] = true;
            $usedSigizi[$p['sigizi']->id] = true;
        }

        $capil  = $this->capilNew()->reject(fn($c) => isset($usedCapil[$c->id]))->values();
        $sigizi = $this->sigiziUntouched()->reject(fn($s) => isset($usedSigizi[$s->id]))->values();

        $rows = [];
        foreach ($sigizi as $s) {
            foreach ($capil as $c) {
                if ((string) $s->nik === (string) $c->nik) {
                    continue;
                }
                $child = $this->nameSim($c->nama, $s->nama);
                if ($child < $minChild) {
                    continue;
                }
                $rows[] = [
                    'child'  => $child,
                    'parent' => $this->parentMatch($c, $s),
                    'diff'   => $this->dayDiff($c->tgl_lahir, $s->tgl_lahir),
                    'kk'     => $this->kkSame($c, $s) ? 1 : 0,
                    's'      => $s,
                    'c'      => $c,
                ];
            }
        }

        usort($rows, fn($a, $b) => $b['child'] <=> $a['child']);

        return $rows;
    }

    /** Tulis kumpulan record Anak ke CSV (kolom identitas standar). Return jumlah baris. */
    private function writeAnakCsv(string $path, iterable $rows): int
    {
        $columns = [
            'id', 'nik', 'no_kk', 'nama', 'jk', 'tempat_lahir', 'tgl_lahir', 'anak',
            'nama_ibu', 'nama_ayah', 'alamat', 'id_kec', 'id_kel', 'id_rt',
            'catatan', 'created_at', 'updated_at',
        ];

        $fh = fopen($path, 'w');
        // BOM agar Excel membaca UTF-8 dengan benar.
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, $columns);

        $count = 0;
        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $col) {
                $line[] = $row->getAttribute($col);
            }
            fputcsv($fh, $line);
            $count++;
        }

        fclose($fh);

        return $count;
    }

    /**
     * Ekspor pasangan duplikat terkonfirmasi (Capil-baru ↔ sigizi-untouched) ke CSV
     * untuk review manual sebelum --apply. Mengembalikan jumlah pasangan.
     *
     * Berisi id + kolom identitas kedua record berdampingan plus skor kecocokan,
     * agar bisa dinilai mata manusia. Sama seperti export lain: tulis ke berkas,
     * jangan tampilkan isinya ke layar/log.
     */
    public function exportPairs(string $path): int
    {
        $pairs = $this->findPairs($this->capilNew(), $this->sigiziUntouched());

        $header = [
            'no', 'via', 'score', 'child_sim', 'parent_sim',
            'id_capil', 'nik_capil', 'nama_capil', 'tgl_lahir_capil', 'no_kk_capil', 'nama_ibu_capil', 'nama_ayah_capil',
            'id_sigizi', 'nik_sigizi', 'nama_sigizi', 'tgl_lahir_sigizi', 'no_kk_sigizi', 'nama_ibu_sigizi', 'nama_ayah_sigizi',
        ];

        $fh = fopen($path, 'w');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, $header);

        $no = 0;
        foreach ($pairs as $p) {
            $c = $p['capil'];
            $s = $p['sigizi'];
            $no++;
            fputcsv($fh, [
                $no, $p['via'], round($p['score'], 1), round($p['child'], 1), round($p['parent'], 1),
                $c->id, $c->nik, $c->nama, $c->tgl_lahir, $c->no_kk, $c->nama_ibu, $c->nama_ayah,
                $s->id, $s->nik, $s->nama, $s->tgl_lahir, $s->no_kk, $s->nama_ibu, $s->nama_ayah,
            ]);
        }

        fclose($fh);

        return count($pairs);
    }

    // =========================================================================
    // Duplikat INTERNAL tabel `anak` (dugaan record kembar di registri gabungan)
    // =========================================================================
    //
    // Dihitung atas SELURUH tabel `anak` (registri hasil merge sigizi+Capil), bukan
    // hanya Capil-baru — karena dobel-input bisa berada di record sigizi maupun Capil.
    // Tiga sinyal:
    //   A. NIK asli identik  → MUSTAHIL: kolom `nik` UNIQUE, DB menolaknya (selalu 0).
    //   B. Nama + tgl lahir identik (meski NIK beda/kosong).
    //   C. No. KK + nama identik (tgl boleh beda; tangkap dobel yang tgl-nya salah ketik).
    // Ini SINYAL untuk ditinjau manusia, bukan auto-merge (bisa saja kakak-adik dsb).

    /**
     * Kelompok record dengan NAMA + TGL LAHIR identik (seluruh tabel `anak`).
     * Nama dinormalisasi (trim + lowercase), tanggal dipangkas ke Y-m-d; nama kosong diabaikan.
     *
     * @return array<int, Collection<int, Anak>>
     */
    public function duplicateGroupsByNameDob(): array
    {
        return Anak::all()
            ->filter(fn($a) => trim((string) $a->nama) !== '')
            ->groupBy(fn($a) => trim(mb_strtolower((string) $a->nama)) . '|' . $this->dateKey($a->tgl_lahir))
            ->filter(fn($grp) => $grp->count() > 1)
            ->values()
            ->all();
    }

    /**
     * Kelompok record dengan No. KK + NAMA identik (seluruh tabel `anak`).
     * No.KK kosong diabaikan (banyak record sigizi tak punya KK → bukan sinyal duplikat).
     *
     * @return array<int, Collection<int, Anak>>
     */
    public function duplicateGroupsByKkName(): array
    {
        return Anak::all()
            ->filter(fn($a) => trim((string) $a->no_kk) !== '' && trim((string) $a->nama) !== '')
            ->groupBy(fn($a) => trim((string) $a->no_kk) . '|' . trim(mb_strtolower((string) $a->nama)))
            ->filter(fn($grp) => $grp->count() > 1)
            ->values()
            ->all();
    }

    /**
     * Ekspor gabungan dugaan duplikat internal (sinyal B nama+tgl & C No.KK+nama) ke satu
     * CSV berkolom `signal`. Mengembalikan rincian jumlah per sinyal + total.
     *
     * Kerahasiaan: data ditulis langsung ke berkas; pemanggil hanya laporkan jumlah & path.
     *
     * @return array{name_dob:array{groups:int,rows:int}, kk_name:array{groups:int,rows:int}, total_groups:int, total_rows:int}
     */
    public function exportInternalDuplicates(string $path): array
    {
        $byNameDob = $this->duplicateGroupsByNameDob();
        $byKkName  = $this->duplicateGroupsByKkName();

        $header = [
            'signal', 'group', 'dup_count',
            'id', 'nik', 'no_kk', 'nama', 'jk', 'tgl_lahir',
            'nama_ibu', 'nama_ayah', 'alamat_ktp', 'alamat', 'id_kel',
        ];

        $fh = fopen($path, 'w');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, $header);

        $write = function (string $signal, array $groups) use ($fh): int {
            $rows = 0;
            $no = 0;
            foreach ($groups as $group) {
                $no++;
                $size = $group->count();
                foreach ($group as $a) {
                    fputcsv($fh, [
                        $signal, $no, $size,
                        $a->id, $a->nik, $a->no_kk, $a->nama, $a->jk, $a->tgl_lahir,
                        $a->nama_ibu, $a->nama_ayah, $a->alamat_ktp, $a->alamat, $a->id_kel,
                    ]);
                    $rows++;
                }
            }
            return $rows;
        };

        $rowsNameDob = $write('B_nama_tgl', $byNameDob);
        $rowsKkName  = $write('C_kk_nama', $byKkName);

        fclose($fh);

        return [
            'name_dob'     => ['groups' => count($byNameDob), 'rows' => $rowsNameDob],
            'kk_name'      => ['groups' => count($byKkName),  'rows' => $rowsKkName],
            'total_groups' => count($byNameDob) + count($byKkName),
            'total_rows'   => $rowsNameDob + $rowsKkName,
        ];
    }

    // =========================================================================
    // Pencocokan
    // =========================================================================

    /** Kemiripan dua string (persen, case-insensitive). */
    public function nameSim(?string $a, ?string $b): float
    {
        similar_text(trim(mb_strtolower((string) $a)), trim(mb_strtolower((string) $b)), $pct);
        return $pct;
    }

    /** Kumpulkan token nama ortu (nama_ibu + nama_ayah), dipecah pada '/'. */
    private function parentTokens(Anak $a): array
    {
        $tokens = [];
        foreach (['nama_ibu', 'nama_ayah'] as $field) {
            foreach (explode('/', (string) $a->$field) as $seg) {
                $seg = trim(mb_strtolower($seg));
                if ($seg !== '') {
                    $tokens[] = $seg;
                }
            }
        }
        return $tokens;
    }

    /** Kemiripan terbaik antar-segmen nama ortu kedua record (persen). */
    public function parentMatch(Anak $a, Anak $b): float
    {
        $best = 0.0;
        foreach ($this->parentTokens($a) as $x) {
            foreach ($this->parentTokens($b) as $y) {
                similar_text($x, $y, $pct);
                if ($pct > $best) {
                    $best = $pct;
                }
            }
        }
        return $best;
    }

    private function kkSame(Anak $a, Anak $b): bool
    {
        $x = trim((string) $a->no_kk);
        $y = trim((string) $b->no_kk);
        return $x !== '' && $y !== '' && $x === $y;
    }

    private function dateKey($value): string
    {
        return substr((string) $value, 0, 10);
    }

    /** Kunci blocking nama: prefiks nama ternormalisasi (untuk lookup lintas-tanggal). */
    private function nameKey(?string $nama): string
    {
        return mb_substr(trim(mb_strtolower((string) $nama)), 0, self::NAME_BLOCK_LEN);
    }

    /** Kunci tanggal kandidat: tgl tepat + tetangga dalam toleransi (untuk bucket lookup). */
    private function neighborDateKeys($value): array
    {
        $ts = strtotime($this->dateKey($value));
        if ($ts === false) {
            return [$this->dateKey($value)];
        }
        $keys = [];
        for ($d = -self::DATE_TOLERANCE_DAYS; $d <= self::DATE_TOLERANCE_DAYS; $d++) {
            $keys[] = date('Y-m-d', $ts + $d * 86400);
        }
        return $keys;
    }

    /** Selisih hari (mutlak) dua tanggal lahir; PHP_INT_MAX bila tak terbaca. */
    private function dayDiff($a, $b): int
    {
        $ta = strtotime($this->dateKey($a));
        $tb = strtotime($this->dateKey($b));
        if ($ta === false || $tb === false) {
            return PHP_INT_MAX;
        }
        return (int) round(abs($ta - $tb) / 86400);
    }

    /**
     * Nilai satu kandidat pasangan. Return info match atau null bila tak memenuhi aturan.
     *
     * Tanggal lahir boleh meleset hingga DATE_TOLERANCE_DAYS hari (toleransi typo).
     * Saat tgl TEPAT sama: nama anak cukup >= CHILD_MIN. Saat tgl MELESET: nama anak
     * harus >= CHILD_NEAR_MIN (lebih ketat) agar tak salah gabung sibling/kembar.
     *
     * @return array{via:string, score:float, child:float, parent:float}|null
     */
    public function evaluate(Anak $capil, Anak $sigizi): ?array
    {
        $child  = $this->nameSim($capil->nama, $sigizi->nama);
        $parent = $this->parentMatch($capil, $sigizi);
        $kkSame = $this->kkSame($capil, $sigizi);
        $diff   = $this->dayDiff($capil->tgl_lahir, $sigizi->tgl_lahir);
        $exactDate = $diff === 0;

        // Identitas nama sangat kuat → boleh abaikan tanggal (typo tahun/bulan).
        $strongName = $child >= self::CHILD_STRONG && $parent >= self::PARENT_STRONG;

        if (!$strongName) {
            // Jalur normal: tanggal wajib dalam toleransi.
            if ($diff > self::DATE_TOLERANCE_DAYS) {
                return null;
            }
            if ($child < ($exactDate ? self::CHILD_MIN : self::CHILD_NEAR_MIN)) {
                return null; // jaga-jaga kembar/sibling: nama anak harus cukup mirip
            }
            if (!$kkSame && $parent < self::PARENT_MIN) {
                return null;
            }
        }

        return [
            'via'    => $kkSame ? 'kk' : 'ortu',
            // Bonus tgl tepat agar saat berebut sigizi yang sama, kandidat tgl-tepat menang.
            'score'  => ($kkSame ? 1000.0 : 0.0) + ($exactDate ? 100.0 : 0.0) + $parent + $child,
            'child'  => $child,
            'parent' => $parent,
        ];
    }

    /**
     * Cari pasangan duplikat satu-lawan-satu (greedy, skor tertinggi menang).
     *
     * @return array<int, array{capil:Anak, sigizi:Anak, via:string, score:float, child:float, parent:float}>
     */
    public function findPairs(Collection $capilNew, Collection $sigiziUntouched): array
    {
        // Indeks ganda: per tanggal (untuk jalur normal +-1 hari) dan per prefiks nama
        // (untuk jalur "nama sangat mirip" yang mengabaikan tanggal — agar tak perlu
        // membandingkan seluruh pasangan secara penuh).
        $byTgl = [];
        $byName = [];
        foreach ($sigiziUntouched as $s) {
            $byTgl[$this->dateKey($s->tgl_lahir)][] = $s;
            $byName[$this->nameKey($s->nama)][] = $s;
        }

        $candidates = [];
        foreach ($capilNew as $c) {
            $seen = [];
            // Kandidat dari tanggal (tepat + tetangga +-toleransi) dan dari prefiks nama.
            $bucket = [];
            foreach ($this->neighborDateKeys($c->tgl_lahir) as $key) {
                foreach ($byTgl[$key] ?? [] as $s) {
                    $bucket[] = $s;
                }
            }
            foreach ($byName[$this->nameKey($c->nama)] ?? [] as $s) {
                $bucket[] = $s;
            }

            foreach ($bucket as $s) {
                if (isset($seen[$s->id])) {
                    continue;
                }
                $seen[$s->id] = true;
                if ((string) $s->nik === (string) $c->nik) {
                    continue;
                }
                if ($info = $this->evaluate($c, $s)) {
                    $candidates[] = ['capil' => $c, 'sigizi' => $s] + $info;
                }
            }
        }

        usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);

        $usedCapil = [];
        $usedSigizi = [];
        $pairs = [];
        foreach ($candidates as $cand) {
            $ci = $cand['capil']->id;
            $si = $cand['sigizi']->id;
            if (isset($usedCapil[$ci]) || isset($usedSigizi[$si])) {
                continue;
            }
            $usedCapil[$ci] = true;
            $usedSigizi[$si] = true;
            $pairs[] = $cand;
        }

        return $pairs;
    }

    // =========================================================================
    // Merge
    // =========================================================================

    /**
     * Gabungkan: record sigizi bertahan (membawa domisili + kesehatan),
     * identitas/kependudukan diambil dari Capil, record Capil dihapus.
     */
    public function merge(Anak $sigizi, Anak $capil): void
    {
        DB::transaction(function () use ($sigizi, $capil) {
            $identitas = [
                'nik'        => $capil->nik,
                'no_kk'      => $capil->no_kk,
                'nama'       => $capil->nama,
                'nama_ibu'   => $capil->nama_ibu,
                'nama_ayah'  => $capil->nama_ayah,
                'jk'         => $capil->jk,
                'alamat_ktp' => $capil->alamat_ktp,
            ];

            // Hapus dulu agar NIK Capil tak bentrok unique key saat dipindah ke record sigizi.
            $capil->delete();
            $sigizi->update($identitas);
        });
    }

    /**
     * Jalankan dedup. Default dry-run (tanpa perubahan); set $execute=true untuk merge.
     *
     * @return array{capil_new:int, sigizi_untouched:int, pairs:int, via_kk:int, via_ortu:int, merged:int}
     */
    public function run(bool $execute = false): array
    {
        $capilNew = $this->capilNew();
        $sigizi   = $this->sigiziUntouched();
        $pairs    = $this->findPairs($capilNew, $sigizi);

        $viaKk = count(array_filter($pairs, fn($p) => $p['via'] === 'kk'));

        $merged = 0;
        if ($execute) {
            foreach ($pairs as $pair) {
                $this->merge($pair['sigizi'], $pair['capil']);
                $merged++;
            }
        }

        return [
            'capil_new'        => $capilNew->count(),
            'sigizi_untouched' => $sigizi->count(),
            'pairs'            => count($pairs),
            'via_kk'           => $viaKk,
            'via_ortu'         => count($pairs) - $viaKk,
            'merged'           => $merged,
        ];
    }
}

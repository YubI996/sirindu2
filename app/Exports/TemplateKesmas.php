<?php

namespace App\Exports;

use App\Http\Requests\Admin\Anak\KesmasRules;
use App\Services\KolomKesmasImport;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Template Import Data Kesmas, dibangun saat diunduh dari config('kesmas') supaya dropdown selalu sama
 * dengan form (spec 2026-10-15-import-kesmas-design.md §2). Sheet "Data" HARUS sheet pertama — hanya
 * sheet itu yang dibaca KesmasImport. Tanpa baris contoh: contoh yang lupa dihapus ikut terimpor.
 */
final class TemplateKesmas
{
    private const BARIS_DROPDOWN = 2000;

    /** @return list<array{kolom:string,label:string,wajib:string,keterangan:string,pilihan:?array}> */
    public static function kolom(): array
    {
        $baris = [
            ['nik_anak', 'NIK anak', 'Dianjurkan', '16 digit, ketik sebagai teks. Dipakai lebih dulu untuk mencari anak.', null],
            ['nama_anak', 'Nama anak', 'Wajib', 'Sesuai data anak di SIRINDU.', null],
            ['jk', 'Jenis kelamin', 'Wajib', 'L atau P.', ['L', 'P']],
            ['tgl_lahir', 'Tanggal lahir', 'Wajib', 'Sel tanggal Excel, 2023-01-31, atau 31/01/2023 (pilih format tanggal saat mengunggah).', null],
            ['nama_ortu', 'Nama ibu/orang tua', 'Wajib', 'Membedakan anak bernama mirip yang lahir di hari yang sama.', null],
            ['tgl_kunjungan', 'Tanggal kunjungan', 'Opsional', 'Kosongkan bila baris hanya mengisi data Kesmas per anak; kolom kunjungan di baris itu diabaikan.', null],
            ['bb', 'Berat badan', 'Wajib untuk kunjungan baru', 'GRAM bila anak berumur di bawah 2 bulan pada tanggal kunjungan (300–8000, mis. 3250); selain itu KG (1–150). Tanpa titik ribuan.', null],
            ['tb_cm', 'Tinggi/panjang badan (cm)', 'Wajib untuk kunjungan baru', 'Angka, koma desimal boleh.', null],
            ['posisi', 'Posisi ukur', 'Wajib untuk kunjungan baru', 'Berdiri atau Terlentang.', ['Berdiri', 'Terlentang']],
            ['lk_cm', 'Lingkar kepala (cm)', 'Opsional', 'Angka.', null],
            ['lila_cm', 'LiLA (cm)', 'Opsional', 'Angka.', null],
            ['zscore_bb_u', 'Z-score BB/U', 'Opsional', 'Angka.', null],
            ['zscore_pb_u', 'Z-score PB/U', 'Opsional', 'Angka.', null],
            ['zscore_bb_pb', 'Z-score BB/PB', 'Opsional', 'Angka.', null],
        ];
        $hasil = array_map(fn ($b) => array_combine(['kolom', 'label', 'wajib', 'keterangan', 'pilihan'], $b), $baris);

        $label = KesmasRules::atribut();
        foreach (['kunjungan' => 'Layanan per kunjungan — butuh tgl_kunjungan.', 'anak' => 'Data per anak.'] as $tingkat => $ket) {
            foreach (KolomKesmasImport::kolom($tingkat) as $kolom) {
                $pilihan = KolomKesmasImport::pilihan($kolom);
                $hasil[] = [
                    'kolom' => $kolom, 'label' => $label[$kolom] ?? $kolom, 'wajib' => 'Opsional',
                    'keterangan' => $ket.' '.match (true) {
                        $pilihan !== null => 'Pilih dari daftar.',
                        in_array($kolom, ['tgl_hbig', 'tgl_penanda_ckg'], true) => 'Tanggal.',
                        in_array($kolom, ['bbl', 'pbl', 'lk_lahir', 'usia_kehamilan_lahir'], true) => 'Angka.',
                        default => 'Teks bebas.',
                    },
                    'pilihan' => $pilihan,
                ];
            }
        }

        return $hasil;
    }

    /** @return string path berkas .xlsx sementara; pemanggil yang menghapusnya */
    public static function buat(): string
    {
        $kolom = self::kolom();
        $ss = new Spreadsheet();

        $data = $ss->getActiveSheet()->setTitle('Data');
        foreach ($kolom as $i => $k) {
            $c = $i + 1;
            $data->getCell([$c, 1])->setValue($k['kolom']);
            $data->getColumnDimensionByColumn($c)->setWidth(max(14, strlen($k['kolom']) + 4));
            $huruf = $data->getCell([$c, 1])->getColumn();
            if ($k['kolom'] === 'nik_anak') {
                $data->getStyle("{$huruf}2:{$huruf}".(self::BARIS_DROPDOWN + 1))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
            }
            if ($k['pilihan'] !== null) {
                $v = $data->getCell([$c, 2])->getDataValidation();
                $v->setType(DataValidation::TYPE_LIST)->setAllowBlank(true)->setShowDropDown(true)
                    ->setShowErrorMessage(true)->setErrorTitle('Pilihan tidak dikenal')
                    ->setError('Pilih salah satu dari daftar.')
                    ->setFormula1('"'.implode(',', $k['pilihan']).'"');
                $data->setDataValidation("{$huruf}2:{$huruf}".(self::BARIS_DROPDOWN + 1), $v);
            }
        }
        $data->getStyle('1:1')->getFont()->setBold(true);
        $data->freezePane('A2');

        $petunjuk = $ss->createSheet()->setTitle('Petunjuk');
        $petunjuk->fromArray([
            ['Import Data Kesmas — isi sheet "Data" mulai baris 2. Satu baris = satu anak pada satu kunjungan.'],
            ['Anak harus SUDAH ADA di SIRINDU; import ini tidak membuat anak baru dan tidak mengubah NIK.'],
            ['Sel kosong tidak mengubah data yang sudah ada. Ukuran kunjungan yang sudah tercatat tidak diubah.'],
            [],
            ['Kolom', 'Label', 'Wajib', 'Keterangan', 'Pilihan'],
        ]);
        foreach ($kolom as $i => $k) {
            $petunjuk->fromArray([[$k['kolom'], $k['label'], $k['wajib'], $k['keterangan'], $k['pilihan'] ? implode(', ', $k['pilihan']) : '']], null, 'A'.($i + 6));
        }
        $petunjuk->getStyle('A5:E5')->getFont()->setBold(true);
        foreach (['A' => 24, 'B' => 40, 'C' => 26, 'D' => 70, 'E' => 50] as $kol => $lebar) {
            $petunjuk->getColumnDimension($kol)->setWidth($lebar);
        }

        $ss->setActiveSheetIndex(0);
        $path = tempnam(sys_get_temp_dir(), 'tpl_kesmas').'.xlsx';
        (new Xlsx($ss))->save($path);

        return $path;
    }
}

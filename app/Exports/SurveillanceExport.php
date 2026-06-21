<?php

namespace App\Exports;

use App\Models\SurveillanceCase;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

/**
 * Export surveilans "satu sheet lebar": seluruh field kasus utama + data relasi
 * (imunisasi/spesimen/kontak erat/faskes berobat) diratakan jadi kolom berulang.
 *
 * Jumlah set kolom relasi DINAMIS — dihitung dari jumlah maksimum record relasi
 * pada kasus terfilter (klien minta data penuh, tanpa cap statis yang memotong).
 * Max dihitung lewat satu query agregat (withCount), jadi streaming FromQuery tetap
 * terjaga (tidak memuat seluruh baris ke memori).
 */
class SurveillanceExport implements FromQuery, WithHeadings, WithMapping, WithTitle, ShouldAutoSize
{
    /** Jumlah set kolom relasi per kasus, dihitung sekali dari data. */
    private ?array $caps = null;

    public function __construct(
        protected ?int $tahun = null,
        protected ?int $jenisKasusId = null,
        protected ?array $wilker = null,
        protected ?array $kelurahanId = null,
        protected ?array $kabKota = null
    ) {
        $this->tahun = $tahun ?? now()->year;
    }

    /** Query dasar (filter saja) — dipakai untuk caps & query export. */
    private function baseQuery()
    {
        $q = SurveillanceCase::query()->whereYear('tanggal_lapor', $this->tahun);

        if ($this->jenisKasusId) {
            $q->where('id_jenis_kasus', $this->jenisKasusId);
        }
        if (!empty($this->kabKota)) {
            $q->whereIn('kab_kota', $this->kabKota);
        }
        if (!empty($this->wilker)) {
            $q->whereIn('wilker_puskesmas', $this->wilker);
        }
        if (!empty($this->kelurahanId)) {
            $q->whereIn('id_kel', $this->kelurahanId);
        }

        return $q;
    }

    /**
     * Jumlah set kolom tiap relasi = max record per kasus pada data terfilter.
     * Minimum 1 agar struktur header tetap stabil walau relasi kosong.
     */
    private function caps(): array
    {
        if ($this->caps !== null) {
            return $this->caps;
        }

        $counts = $this->baseQuery()
            ->withCount(['spesimen', 'kontakErat', 'faskesBerobat'])
            // Imunisasi dipetakan via slot `imunisasi_ke` (bisa renggang), jadi cap-nya
            // = nilai slot tertinggi, bukan jumlah baris.
            ->withMax('imunisasi', 'imunisasi_ke')
            ->get(['id']);

        return $this->caps = [
            'imunisasi' => max(1, (int) $counts->max('imunisasi_max_imunisasi_ke')),
            'spesimen'  => max(1, (int) $counts->max('spesimen_count')),
            'kontak'    => max(1, (int) $counts->max('kontak_erat_count')),
            'faskes'    => max(1, (int) $counts->max('faskes_berobat_count')),
        ];
    }

    public function query()
    {
        return $this->baseQuery()
            ->with([
                'jenisKasus:id,nama_penyakit', 'kecamatan:id,name', 'kelurahan:id,name', 'rt:id,name',
                'imunisasi', 'spesimen', 'kontakErat', 'faskesBerobat',
            ])
            ->orderBy('tanggal_lapor')
            ->orderBy('no_registrasi');
    }

    public function headings(): array
    {
        $h = [
            // A: Identitas
            'No. Registrasi', 'NIK', 'Nama Lengkap', 'Tanggal Lahir', 'Umur (saat onset)', 'Kategori Umur',
            'Jenis Kelamin', 'Alamat Lengkap', 'Kecamatan', 'Kelurahan', 'RT',
            'Provinsi', 'Kab/Kota', 'Latitude', 'Longitude',
            'No. Telepon', 'Tempat Kerja/Sekolah', 'Nama Orang Tua', 'No. HP Orang Tua',
            // B: Pelapor
            'Nama Pelapor', 'Jabatan Pelapor', 'Instansi Pelapor', 'Telepon Pelapor',
            'Wilker Puskesmas', 'Tanggal Terima Laporan', 'Tanggal Penyidikan',
            // C: Data Kasus
            'Jenis Penyakit', 'Kode ICD-10', 'Tanggal Onset', 'Tanggal Konsultasi',
            'Tanggal Lapor', 'Sumber Penularan', 'Lokasi Penularan',
            // D: Gejala
            'Gejala Demam', 'Gejala Batuk', 'Gejala Pilek', 'Gejala Sakit Kepala',
            'Gejala Mual', 'Gejala Muntah', 'Gejala Diare', 'Gejala Ruam',
            'Gejala Sesak Napas', 'Gejala Nyeri Otot', 'Gejala Nyeri Sendi', 'Gejala Lemas',
            'Gejala Kehilangan Nafsu Makan', 'Gejala Mata Merah', 'Gejala Pembengkakan Kelenjar',
            'Gejala Kejang', 'Gejala Penurunan Kesadaran',
            'Gejala Pseudomembran', 'Gejala Leher Bengkak', 'Gejala Apnea',
            'Gejala Adenopathy', 'Gejala Arthralgia', 'Gejala Kehamilan', 'Gejala Lainnya',
            // Komplikasi
            'Komplikasi Diare', 'Komplikasi Kebutaan', 'Komplikasi Pneumonia',
            'Komplikasi Malnutrisi', 'Komplikasi Bronchopneumonia',
            'Komplikasi Otitis Media', 'Komplikasi Encephalitis', 'Komplikasi Ulkus Mukosa',
            // E: Riwayat
            'Riwayat Perjalanan', 'Riwayat Kontak Kasus',
            'Riwayat Imunisasi', 'Tanggal Imunisasi Terakhir',
            // F: Lab (ringkas kasus utama)
            'Status Lab', 'Tanggal Pengambilan Spesimen', 'Jenis Spesimen',
            'Tanggal Hasil Lab', 'Hasil Lab',
            // G: Manajemen
            'Status Rawat', 'Nama Faskes Rawat', 'Tanggal Masuk Rawat', 'Tanggal Keluar Rawat',
            // H: Status Akhir
            'Kondisi Akhir', 'Tanggal Kondisi Akhir', 'Penyebab Kematian',
            // J: Metadata
            'Status Kasus', 'Catatan Tambahan', 'Foto Dokumentasi', 'Foto Dokumentasi 2', 'Tanggal Input',
        ];

        $caps = $this->caps();

        // Imunisasi (set dinamis)
        for ($i = 1; $i <= $caps['imunisasi']; $i++) {
            $h[] = "Imunisasi $i Antigen";
            $h[] = "Imunisasi $i Diberikan";
            $h[] = "Imunisasi $i Tanggal";
            $h[] = "Imunisasi $i Sumber Informasi";
        }
        // Spesimen (set dinamis)
        for ($i = 1; $i <= $caps['spesimen']; $i++) {
            $h[] = "Spesimen $i Jenis";
            $h[] = "Spesimen $i Tgl Ambil";
            $h[] = "Spesimen $i Tgl Kirim";
            $h[] = "Spesimen $i Tgl Terima Lab";
            $h[] = "Spesimen $i Status Pemeriksaan";
            $h[] = "Spesimen $i Penyakit Terkonfirmasi";
            $h[] = "Spesimen $i Variant/Genotype";
        }
        // Kontak Erat (set dinamis) + jumlah
        $h[] = 'Jumlah Kontak Erat';
        for ($i = 1; $i <= $caps['kontak']; $i++) {
            $h[] = "Kontak $i Nama";
            $h[] = "Kontak $i Hubungan";
            $h[] = "Kontak $i Tgl Lahir";
            $h[] = "Kontak $i No. Telp";
            $h[] = "Kontak $i Alamat";
            $h[] = "Kontak $i Tgl Kontak Terakhir";
            $h[] = "Kontak $i Ada Gejala";
            $h[] = "Kontak $i Jumlah Imunisasi MR";
        }
        // Faskes Berobat (set dinamis)
        for ($i = 1; $i <= $caps['faskes']; $i++) {
            $h[] = "Faskes $i Jenis";
            $h[] = "Faskes $i Nama";
            $h[] = "Faskes $i Tgl Berobat";
            $h[] = "Faskes $i Jenis Perawatan";
            $h[] = "Faskes $i Tgl Keluar";
        }

        return $h;
    }

    public function map($case): array
    {
        $bool = fn($v) => $v ? 'Ya' : 'Tidak';

        $row = [
            $case->no_registrasi,
            $case->nik,
            $case->nama_lengkap,
            $case->tanggal_lahir?->format('d/m/Y'),
            $case->umur,
            $case->kategori_umur,
            $case->jenis_kelamin === 'L' ? 'Laki-laki' : ($case->jenis_kelamin === 'P' ? 'Perempuan' : $case->jenis_kelamin),
            $case->alamat_lengkap,
            $case->kecamatan?->name,
            $case->kelurahan?->name,
            $case->rt?->name,
            $case->provinsi,
            $case->kab_kota,
            $case->latitude,
            $case->longitude,
            $case->no_telepon,
            $case->tempat_kerja_sekolah,
            $case->nama_orang_tua,
            $case->no_hp_orang_tua,
            $case->nama_pelapor,
            $case->jabatan_pelapor,
            $case->instansi_pelapor,
            $case->telepon_pelapor,
            $case->wilker_puskesmas,
            $case->tanggal_terima_laporan?->format('d/m/Y'),
            $case->tanggal_penyidikan?->format('d/m/Y'),
            $case->jenisKasus?->nama_penyakit,
            $case->kode_icd10,
            $case->tanggal_onset?->format('d/m/Y'),
            $case->tanggal_konsultasi?->format('d/m/Y'),
            $case->tanggal_lapor?->format('d/m/Y'),
            $case->sumber_penularan,
            $case->lokasi_penularan,
            $bool($case->gejala_demam),
            $bool($case->gejala_batuk),
            $bool($case->gejala_pilek),
            $bool($case->gejala_sakit_kepala),
            $bool($case->gejala_mual),
            $bool($case->gejala_muntah),
            $bool($case->gejala_diare),
            $bool($case->gejala_ruam),
            $bool($case->gejala_sesak_napas),
            $bool($case->gejala_nyeri_otot),
            $bool($case->gejala_nyeri_sendi),
            $bool($case->gejala_lemas),
            $bool($case->gejala_kehilangan_nafsu_makan),
            $bool($case->gejala_mata_merah),
            $bool($case->gejala_pembengkakan_kelenjar),
            $bool($case->gejala_kejang),
            $bool($case->gejala_penurunan_kesadaran),
            $bool($case->gejala_pseudomembran),
            $bool($case->gejala_leher_bengkak),
            $bool($case->gejala_apnea),
            $bool($case->gejala_adenopathy),
            $bool($case->gejala_arthralgia),
            $bool($case->gejala_kehamilan),
            $case->gejala_lainnya,
            $bool($case->komplikasi_diare),
            $bool($case->komplikasi_kebutaan),
            $bool($case->komplikasi_pneumonia),
            $bool($case->komplikasi_malnutrisi),
            $bool($case->komplikasi_bronchopneumonia),
            $bool($case->komplikasi_otitis_media),
            $bool($case->komplikasi_encephalitis),
            $bool($case->komplikasi_ulkus_mukosa_mulut),
            $case->riwayat_perjalanan,
            $bool($case->riwayat_kontak_kasus),
            $case->riwayat_imunisasi,
            $case->tanggal_imunisasi_terakhir?->format('d/m/Y'),
            $case->status_lab,
            $case->tanggal_pengambilan_spesimen?->format('d/m/Y'),
            $case->jenis_spesimen,
            $case->tanggal_hasil_lab?->format('d/m/Y'),
            $case->hasil_lab,
            $case->status_rawat,
            $case->nama_faskes_rawat,
            $case->tanggal_masuk_rawat?->format('d/m/Y'),
            $case->tanggal_keluar_rawat?->format('d/m/Y'),
            $case->kondisi_akhir,
            $case->tanggal_kondisi_akhir?->format('d/m/Y'),
            $case->penyebab_kematian,
            $case->status_kasus,
            $case->catatan_tambahan,
            $case->foto_dokumentasi,
            $case->foto_dokumentasi_2,
            $case->created_at?->format('d/m/Y H:i'),
        ];

        $caps = $this->caps();

        // ===== Imunisasi (set dinamis, dipetakan via imunisasi_ke) =====
        $imun = $case->imunisasi->keyBy('imunisasi_ke');
        for ($i = 1; $i <= $caps['imunisasi']; $i++) {
            $r = $imun->get($i);
            $row[] = $r?->nama_antigen;
            $row[] = $r?->diberikan;
            $row[] = $this->d($r?->tanggal_imunisasi);
            $row[] = $r?->sumber_informasi;
        }

        // ===== Spesimen (set dinamis) =====
        $spesimen = $case->spesimen->values();
        for ($i = 0; $i < $caps['spesimen']; $i++) {
            $r = $spesimen->get($i);
            $row[] = $r?->jenis_spesimen;
            $row[] = $this->d($r?->tanggal_ambil_spesimen);
            $row[] = $this->d($r?->tanggal_kirim_sampel);
            $row[] = $this->d($r?->tanggal_terima_lab);
            $row[] = $r?->status_pemeriksaan;
            $row[] = $r?->penyakit_terkonfirmasi;
            $row[] = $r?->nama_variant_genotype;
        }

        // ===== Kontak Erat (set dinamis) + jumlah =====
        $row[] = $case->kontakErat->count();
        $kontak = $case->kontakErat->values();
        for ($i = 0; $i < $caps['kontak']; $i++) {
            $r = $kontak->get($i);
            $row[] = $r?->nama;
            $row[] = $r?->hubungan;
            $row[] = $this->d($r?->tanggal_lahir);
            $row[] = $r?->no_telepon;
            $row[] = $r?->alamat;
            $row[] = $this->d($r?->tanggal_kontak_terakhir);
            $row[] = $r ? $bool($r->ada_gejala) : null;
            $row[] = $r?->jumlah_imunisasi_campak_rubella;
        }

        // ===== Faskes Berobat (set dinamis) =====
        $faskes = $case->faskesBerobat->values();
        for ($i = 0; $i < $caps['faskes']; $i++) {
            $r = $faskes->get($i);
            $row[] = $r?->jenis_faskes;
            $row[] = $r?->nama_faskes;
            $row[] = $this->d($r?->tanggal_berobat);
            $row[] = $r?->jenis_perawatan;
            $row[] = $this->d($r?->tanggal_keluar);
        }

        return $row;
    }

    /** Format tanggal relasi (kolom string/date) secara defensif. */
    private function d($value): ?string
    {
        if (empty($value)) {
            return null;
        }
        try {
            return Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    public function title(): string
    {
        return 'Data Surveilans ' . $this->tahun;
    }
}

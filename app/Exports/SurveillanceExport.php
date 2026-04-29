<?php

namespace App\Exports;

use App\Models\SurveillanceCase;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class SurveillanceExport implements FromQuery, WithHeadings, WithMapping, WithTitle, ShouldAutoSize
{
    public function __construct(
        protected ?int $tahun = null,
        protected ?int $jenisKasusId = null,
        protected ?string $wilker = null,
        protected ?int $kelurahanId = null
    ) {
        $this->tahun = $tahun ?? now()->year;
    }

    public function query()
    {
        $q = SurveillanceCase::with(['jenisKasus:id,nama_penyakit', 'kecamatan:id,name', 'kelurahan:id,name', 'rt:id,name'])
            ->whereYear('tanggal_lapor', $this->tahun)
            ->orderBy('tanggal_lapor')
            ->orderBy('no_registrasi');

        if ($this->jenisKasusId) {
            $q->where('id_jenis_kasus', $this->jenisKasusId);
        }
        if ($this->wilker) {
            $q->where('wilker_puskesmas', $this->wilker);
        }
        if ($this->kelurahanId) {
            $q->where('id_kel', $this->kelurahanId);
        }

        return $q;
    }

    public function headings(): array
    {
        return [
            // A: Identitas
            'No. Registrasi', 'NIK', 'Nama Lengkap', 'Tanggal Lahir', 'Umur (saat onset)',
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
            // Komplikasi
            'Komplikasi Diare', 'Komplikasi Kebutaan', 'Komplikasi Pneumonia',
            'Komplikasi Malnutrisi', 'Komplikasi Bronchopneumonia',
            'Komplikasi Otitis Media', 'Komplikasi Encephalitis', 'Komplikasi Ulkus Mukosa',
            // E: Riwayat
            'Riwayat Perjalanan', 'Riwayat Kontak Kasus',
            'Riwayat Imunisasi', 'Tanggal Imunisasi Terakhir',
            // F: Lab
            'Status Lab', 'Tanggal Pengambilan Spesimen', 'Jenis Spesimen',
            'Tanggal Hasil Lab', 'Hasil Lab',
            // G: Manajemen
            'Status Rawat', 'Nama Faskes Rawat', 'Tanggal Masuk Rawat', 'Tanggal Keluar Rawat',
            // H: Status Akhir
            'Kondisi Akhir', 'Tanggal Kondisi Akhir', 'Penyebab Kematian',
            // J: Metadata
            'Status Kasus', 'Catatan Tambahan', 'Tanggal Input',
        ];
    }

    public function map($case): array
    {
        $bool = fn($v) => $v ? 'Ya' : 'Tidak';

        return [
            $case->no_registrasi,
            $case->nik,
            $case->nama_lengkap,
            $case->tanggal_lahir?->format('d/m/Y'),
            $case->umur,
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
            $case->created_at?->format('d/m/Y H:i'),
        ];
    }

    public function title(): string
    {
        return 'Data Surveilans ' . $this->tahun;
    }
}

<?php

namespace Database\Factories;

use App\Models\SurveillanceCase;
use App\Models\JenisKasusEpidemiologi;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SurveillanceCaseFactory extends Factory
{
    protected $model = SurveillanceCase::class;

    public function definition()
    {
        $tanggalLahir = $this->faker->dateTimeBetween('-60 years', '-1 year');
        $tanggalOnset = $this->faker->dateTimeBetween('-30 days', 'now');
        $tanggalKonsultasi = $this->faker->dateTimeBetween($tanggalOnset, 'now');

        return [
            'no_registrasi' => 'EPI-' . date('Y') . '-' . $this->faker->unique()->numerify('####'),
            'nik' => $this->faker->numerify('################'),
            'nama_lengkap' => $this->faker->name(),
            'tanggal_lahir' => $tanggalLahir,
            'kategori_umur' => 'dewasa',
            'jenis_kelamin' => $this->faker->randomElement(['L', 'P']),
            'alamat_lengkap' => $this->faker->address(),
            'id_kec' => Kecamatan::factory(),
            'id_kel' => Kelurahan::factory(),
            'id_rt' => Rt::factory(),
            'no_telepon' => $this->faker->phoneNumber(),

            'nama_pelapor' => $this->faker->name(),
            'jabatan_pelapor' => 'Petugas Surveilans',
            'instansi_pelapor' => 'Puskesmas ' . $this->faker->city(),
            'telepon_pelapor' => $this->faker->phoneNumber(),

            'id_jenis_kasus' => JenisKasusEpidemiologi::factory(),
            'kode_icd10' => $this->faker->lexify('???.#'),
            'tanggal_onset' => $tanggalOnset,
            'tanggal_konsultasi' => $tanggalKonsultasi,
            'tanggal_lapor' => $tanggalKonsultasi,
            'sumber_penularan' => $this->faker->randomElement(['lokal', 'import', 'unknown']),
            'lokasi_penularan' => $this->faker->address(),

            'gejala_demam' => $this->faker->boolean(),
            'gejala_batuk' => $this->faker->boolean(),
            'gejala_pilek' => false,
            'gejala_sakit_kepala' => false,
            'gejala_mual' => false,
            'gejala_muntah' => false,
            'gejala_diare' => false,
            'gejala_ruam' => false,
            'gejala_sesak_napas' => false,
            'gejala_nyeri_otot' => false,
            'gejala_nyeri_sendi' => false,
            'gejala_lemas' => false,
            'gejala_kehilangan_nafsu_makan' => false,
            'gejala_mata_merah' => false,
            'gejala_pembengkakan_kelenjar' => false,
            'gejala_kejang' => false,
            'gejala_penurunan_kesadaran' => false,

            'riwayat_perjalanan' => null,
            'riwayat_kontak_kasus' => false,
            'riwayat_imunisasi' => 'tidak_tahu',
            'tanggal_imunisasi_terakhir' => null,

            'status_lab' => 'belum_diperiksa',
            'tanggal_pengambilan_spesimen' => null,
            'jenis_spesimen' => null,
            'hasil_lab' => null,
            'tanggal_hasil_lab' => null,

            'status_rawat' => $this->faker->randomElement(['rawat_jalan', 'rawat_inap', 'isolasi_mandiri', 'rujuk']),
            'nama_faskes_rawat' => 'RS ' . $this->faker->city(),
            'tanggal_masuk_rawat' => null,
            'tanggal_keluar_rawat' => null,

            'kondisi_akhir' => 'dalam_perawatan',
            'tanggal_kondisi_akhir' => null,
            'penyebab_kematian' => null,

            'jumlah_kontak_serumah' => $this->faker->numberBetween(0, 5),
            'jumlah_kontak_diluar_rumah' => $this->faker->numberBetween(0, 10),
            'jumlah_kontak_bergejala' => $this->faker->numberBetween(0, 3),
            'tindak_lanjut_kontak' => null,

            'status_kasus' => $this->faker->randomElement(['suspected', 'probable', 'confirmed', 'discarded']),
            'id_petugas_input' => User::factory(),
            'id_faskes_pelapor' => null,
            'catatan_tambahan' => null,
            'created_by' => fn (array $attrs) => $attrs['id_petugas_input'],
            'updated_by' => fn (array $attrs) => $attrs['id_petugas_input'],
        ];
    }

    public function confirmed()
    {
        return $this->state(fn () => ['status_kasus' => 'confirmed']);
    }

    public function suspected()
    {
        return $this->state(fn () => ['status_kasus' => 'suspected']);
    }

    public function meninggal()
    {
        return $this->state(fn () => [
            'kondisi_akhir' => 'meninggal',
            'tanggal_kondisi_akhir' => now(),
            'penyebab_kematian' => 'Komplikasi penyakit',
        ]);
    }

    public function sembuh()
    {
        return $this->state(fn () => [
            'kondisi_akhir' => 'sembuh',
            'tanggal_kondisi_akhir' => now(),
        ]);
    }
}

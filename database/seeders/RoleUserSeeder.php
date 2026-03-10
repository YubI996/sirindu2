<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

/**
 * RoleUserSeeder
 *
 * Seed user untuk setiap role dalam sistem SIRINDU:
 *   - 1 superadmin Dinkes (akses penuh semua modul)
 *   - 6 user Puskesmas (surveilans_puskesmas) — input + dashboard + peta PD3I
 *   - 5 user RS (surveilans_rs) — input + dashboard + peta PD3I
 *   - 1 user imunisasi faskes (contoh, bisa dikembangkan)
 *
 * Default password semua: Sirindu@2026
 */
class RoleUserSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Seeding Role Users...');

        // Ambil data referensi
        $kecamatanMap = DB::table('kecamatan')
            ->whereIn('name', ['Bontang Utara', 'Bontang Selatan', 'Bontang Barat'])
            ->pluck('id', 'name');

        $puskesmasMap = DB::table('puskesmas')->pluck('id', 'name');
        $rsMap        = DB::table('rumah_sakits')->pluck('id', 'kode_rs');

        $pass = Hash::make('Sirindu@2026');

        // ─────────────────────────────────────────────────
        // 1. DINKES — Superadmin (akses penuh semua modul)
        //    Satu akun untuk imunisasi + PD3I
        // ─────────────────────────────────────────────────
        User::updateOrCreate(
            ['email' => 'dinkes@sirindu.go.id'],
            [
                'name'         => 'Admin Dinkes Kota Bontang',
                'password'     => $pass,
                'type'         => 0,
                'role'         => 'superadmin',
                'faskes_type'  => 'dinkes',
                'id_kec'       => 0,
                'id_kel'       => 0,
                'id_puskesmas' => 0,
                'id_posyandu'  => 0,
            ]
        );

        // ─────────────────────────────────────────────────
        // 2. 6 PUSKESMAS — Surveilans PD3I
        //    Akses: input data (scoped) + dashboard + peta sebaran
        // ─────────────────────────────────────────────────
        $puskesmasUsers = [
            [
                'email'      => 'puskesmas.bontangutara1@sirindu.go.id',
                'name'       => 'Petugas Puskesmas Bontang Utara I',
                'puskesmas'  => 'Bontang Utara I',
                'kecamatan'  => 'Bontang Utara',
            ],
            [
                'email'      => 'puskesmas.bontangutara2@sirindu.go.id',
                'name'       => 'Petugas Puskesmas Bontang Utara II',
                'puskesmas'  => 'Bontang Utara II',
                'kecamatan'  => 'Bontang Utara',
            ],
            [
                'email'      => 'puskesmas.bontangselatan1@sirindu.go.id',
                'name'       => 'Petugas Puskesmas Bontang Selatan I',
                'puskesmas'  => 'Bontang Selatan I',
                'kecamatan'  => 'Bontang Selatan',
            ],
            [
                'email'      => 'puskesmas.bontangselatan2@sirindu.go.id',
                'name'       => 'Petugas Puskesmas Bontang Selatan II',
                'puskesmas'  => 'Bontang Selatan II',
                'kecamatan'  => 'Bontang Selatan',
            ],
            [
                'email'      => 'puskesmas.bontangbarat@sirindu.go.id',
                'name'       => 'Petugas Puskesmas Bontang Barat',
                'puskesmas'  => 'Bontang Barat',
                'kecamatan'  => 'Bontang Barat',
            ],
            [
                'email'      => 'puskesmas.bontanglestari@sirindu.go.id',
                'name'       => 'Petugas Puskesmas Bontang Lestari',
                'puskesmas'  => 'Bontang Lestari',
                'kecamatan'  => 'Bontang Selatan',
            ],
        ];

        foreach ($puskesmasUsers as $u) {
            User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'name'         => $u['name'],
                    'password'     => $pass,
                    'type'         => 0,
                    'role'         => 'surveilans_puskesmas',
                    'faskes_type'  => 'puskesmas',
                    'id_kec'       => $kecamatanMap->get($u['kecamatan'], 0),
                    'id_kel'       => 0,
                    'id_puskesmas' => $puskesmasMap->get($u['puskesmas'], 0),
                    'id_posyandu'  => 0,
                ]
            );
        }

        // ─────────────────────────────────────────────────
        // 3. 5 RUMAH SAKIT — Surveilans PD3I
        //    Akses: input data (scoped) + dashboard + peta sebaran
        // ─────────────────────────────────────────────────
        $rsUsers = [
            [
                'email'     => 'rs.tamanhusada@sirindu.go.id',
                'name'      => 'Petugas RSUD Taman Husada Bontang',
                'kode_rs'   => 'RS-001',
                'kecamatan' => 'Bontang Selatan',
            ],
            [
                'email'     => 'rs.pupukkaltim@sirindu.go.id',
                'name'      => 'Petugas RS Pupuk Kaltim',
                'kode_rs'   => 'RS-002',
                'kecamatan' => 'Bontang Utara',
            ],
            [
                'email'     => 'rs.siloam@sirindu.go.id',
                'name'      => 'Petugas RS Siloam Bontang',
                'kode_rs'   => 'RS-003',
                'kecamatan' => 'Bontang Utara',
            ],
            [
                'email'     => 'rs.islam@sirindu.go.id',
                'name'      => 'Petugas RS Islam Bontang',
                'kode_rs'   => 'RS-004',
                'kecamatan' => 'Bontang Barat',
            ],
            [
                'email'     => 'rs.pertamina@sirindu.go.id',
                'name'      => 'Petugas RS Pertamina Bontang',
                'kode_rs'   => 'RS-005',
                'kecamatan' => 'Bontang Utara',
            ],
        ];

        foreach ($rsUsers as $u) {
            User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'name'         => $u['name'],
                    'password'     => $pass,
                    'type'         => 0,
                    'role'         => 'surveilans_rs',
                    'faskes_type'  => 'rs',
                    'id_kec'       => $kecamatanMap->get($u['kecamatan'], 0),
                    'id_kel'       => 0,
                    'id_puskesmas' => 0,
                    'id_rs'        => $rsMap->get($u['kode_rs']),
                    'id_posyandu'  => 0,
                ]
            );
        }

        // ─────────────────────────────────────────────────
        // 4. CONTOH: Faskes Imunisasi
        //    (dikembangkan lebih lanjut sesuai data faskes)
        // ─────────────────────────────────────────────────
        User::updateOrCreate(
            ['email' => 'imunisasi.faskes@sirindu.go.id'],
            [
                'name'         => 'Petugas Imunisasi Puskesmas (Contoh)',
                'password'     => $pass,
                'type'         => 0,
                'role'         => 'imunisasi_faskes',
                'faskes_type'  => 'puskesmas',
                'id_kec'       => $kecamatanMap->get('Bontang Barat', 0),
                'id_kel'       => 0,
                'id_puskesmas' => $puskesmasMap->first() ?? 0,
                'id_posyandu'  => 0,
            ]
        );

        $total = 1 + 6 + 5 + 1;
        $this->command?->info("✓ {$total} user role selesai di-seed.");
        $this->command?->newLine();
        $this->command?->table(
            ['Email', 'Role', 'Faskes Type'],
            User::whereNotNull('role')
                ->get(['email', 'role', 'faskes_type'])
                ->map(fn($u) => [$u->email, $u->role, $u->faskes_type])
                ->toArray()
        );
    }
}

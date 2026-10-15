<?php

namespace App\Repositories\Admin\User;

use App\Repositories\Admin\Core\User\UserRepositoryInterface;
use App\Models\Kelurahan;
use App\Models\Posyandu;
use App\Models\Rt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserRepository implements UserRepositoryInterface
{
    protected $user;

    public function __construct(
        User $user
    ) {
        $this->user = $user;
    }

    private function deriveFaskesType(string $role, $request): ?string
    {
        return match($role) {
            'superadmin'           => 'dinkes',
            'surveilans_puskesmas' => 'puskesmas',
            'surveilans_rs'        => 'rs',
            'imunisasi_faskes'     => $request->faskes_type ?: null,
            default                => null,
        };
    }

    private function typeFromRole(string $role): int
    {
        // 0 = superadmin, 1 = admin/faskes (akses /admin), 2 = user biasa (tidak boleh /admin).
        // Daftar 1 ditulis eksplisit: peran yang ditambahkan nanti TIDAK diam-diam menjadi admin.
        return match ($role) {
            'superadmin' => 0,
            'imunisasi_faskes', 'surveilans_puskesmas', 'surveilans_rs' => 1,
            default => 2,
        };
    }

    /** Peran RT: wilayah diturunkan dari RT yang dipilih, bukan dari isian form. */
    private function wilayahDariRt($request): array
    {
        if ($request->role !== 'rt') {
            return [];
        }
        if ($request->boolean('rt_sekelurahan')) {
            $kel = Kelurahan::findOrFail($request->id_kel);

            return [
                'id_rt' => null,
                'rt_sekelurahan' => true,
                'id_kel' => $kel->id,
                'id_kec' => $kel->id_kecamatan,
                'id_posyandu' => null,
            ];
        }
        $rt  = Rt::findOrFail($request->id_rt);
        $kel = Kelurahan::find($rt->id_kelurahan);

        return [
            'id_rt'       => $rt->id,
            'rt_sekelurahan' => false,
            'id_kel'      => $rt->id_kelurahan,
            'id_kec'      => $kel?->id_kecamatan,
            'id_posyandu' => $rt->id_posyandu,
        ];
    }

    /**
     * Peran Kesmas: seluruh kolom lingkup ditulis ulang — yang tak relevan dikosongkan, supaya akun yang
     * berganti peran tidak membawa lingkup lama (spec akun Kesmas §6). [] untuk peran lain.
     */
    private function lingkupKesmas($request): array
    {
        $kosong = [
            'id_kec' => null, 'id_kel' => null, 'id_puskesmas' => null, 'id_rs' => null, 'id_posyandu' => null,
            'id_rt' => null, 'rt_sekelurahan' => false, 'nama_sarana' => null, 'faskes_type' => null,
        ];

        switch ($request->role) {
            case 'pimpinan':
                return $kosong;
            case 'kelurahan':
                $kel = Kelurahan::findOrFail($request->kesmas_id_kel);
                return ['id_kel' => $kel->id, 'id_kec' => $kel->id_kecamatan] + $kosong;
            case 'kader':
                // Tabel posyandu tak punya kolom kelurahan — yang bisa diturunkan hanya puskesmas.
                $pos = Posyandu::findOrFail($request->kesmas_id_posyandu);
                return ['id_posyandu' => $pos->id, 'id_puskesmas' => $pos->id_puskesmas] + $kosong;
            case 'kesmas_puskesmas':
                return ['id_puskesmas' => (int) $request->id_puskesmas, 'faskes_type' => 'puskesmas'] + $kosong;
            case 'kesmas_rs':
                return ['id_rs' => (int) $request->id_rs, 'faskes_type' => 'rs'] + $kosong;
            case 'sarkes':
                return ['nama_sarana' => trim((string) $request->nama_sarana)] + $kosong;
            default:
                return [];
        }
    }

    /** Cek ulang di dalam transaksi: dua super-admin yang menyimpan bersamaan tak boleh lolos validasi berdua. */
    private function pastikanKelurahanBelumPunyaAkun(array $data, ?int $kecuali): void
    {
        if (($data['role'] ?? null) !== 'kelurahan') {
            return;
        }
        $ada = User::where('role', 'kelurahan')->where('id_kel', $data['id_kel'])
            ->when($kecuali, fn ($q) => $q->whereKeyNot($kecuali))->lockForUpdate()->first();
        if ($ada) {
            $nama = Kelurahan::whereKey($data['id_kel'])->value('name');
            throw ValidationException::withMessages([
                'kesmas_id_kel' => "Kelurahan {$nama} sudah memiliki akun ({$ada->email}). Satu kelurahan hanya boleh memiliki satu akun.",
            ]);
        }
    }

    public function storeUser($request): string
    {
        $role = $request->role;
        $plainPassword = Str::random(12);

        $data = array_merge([
            'name'        => $request->name,
            'email'       => $request->email,
            'type'        => $this->typeFromRole($role),
            'role'        => $role,
            'faskes_type' => $this->deriveFaskesType($role, $request),
            'password'    => bcrypt($plainPassword),
            'id_kec'      => $request->id_kec ?: null,
            'id_kel'      => $request->id_kel ?: null,
            'id_puskesmas'=> $request->id_puskesmas ?: null,
            'id_rs'       => $request->id_rs ?: null,
            'id_posyandu' => $request->id_posyandu ?: null,
        ], $this->wilayahDariRt($request), $this->lingkupKesmas($request));

        DB::transaction(function () use ($data) {
            $this->pastikanKelurahanBelumPunyaAkun($data, null);
            User::create($data);
        });

        return $plainPassword;
    }

    public function updateUser($request, $id): void
    {
        $user = User::findOrFail($id);
        $role = $request->role;

        $data = [
            'name'        => $request->name,
            'email'       => $request->email,
            'type'        => $this->typeFromRole($role),
            'role'        => $role,
            'faskes_type' => $this->deriveFaskesType($role, $request),
            'id_rt'       => null, // ditimpa wilayahDariRt() untuk peran rt
            'rt_sekelurahan' => false,
            'id_puskesmas'=> $request->id_puskesmas ?: null,
            'id_rs'       => $request->id_rs ?: null,
            'nama_sarana' => null,
        ];

        if ($request->filled('password')) {
            $data['password'] = bcrypt($request->password);
        }

        if ($request->id_kecx) {
            $data['id_kec']      = $request->id_kecx;
            $data['id_kel']      = $request->id_kelx;
            $data['id_posyandu'] = $request->id_posyandux ?: null;
        }

        $data = array_merge($data, $this->wilayahDariRt($request), $this->lingkupKesmas($request));

        DB::transaction(function () use ($user, $data) {
            $this->pastikanKelurahanBelumPunyaAkun($data, $user->id);
            $user->update($data);
        });
    }

    public function destroyUser($id): void
    {
        $user = User::findOrFail($id);
        $user->delete();
    }
}

<?php

namespace App\Repositories\Admin\User;

use App\Repositories\Admin\Core\User\UserRepositoryInterface;
use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\User;
use Illuminate\Support\Str;

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
        // 0 = superadmin, 1 = admin/faskes (akses /admin), 2 = user biasa (peran rt: TIDAK boleh /admin)
        return match ($role) {
            'superadmin' => 0,
            'rt'         => 2,
            default      => 1,
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

    public function storeUser($request): string
    {
        $role = $request->role;
        $plainPassword = Str::random(12);

        User::create(array_merge([
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
        ], $this->wilayahDariRt($request)));

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
        ];

        if ($request->filled('password')) {
            $data['password'] = bcrypt($request->password);
        }

        if ($request->id_kecx) {
            $data['id_kec']      = $request->id_kecx;
            $data['id_kel']      = $request->id_kelx;
            $data['id_posyandu'] = $request->id_posyandux ?: null;
        }

        $data = array_merge($data, $this->wilayahDariRt($request));

        $user->update($data);
    }

    public function destroyUser($id): void
    {
        $user = User::findOrFail($id);
        $user->delete();
    }
}

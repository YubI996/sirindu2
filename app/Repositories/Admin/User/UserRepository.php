<?php

namespace App\Repositories\Admin\User;

use App\Repositories\Admin\Core\User\UserRepositoryInterface;
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
        return $role === 'superadmin' ? 0 : 1;
    }

    public function storeUser($request): string
    {
        $role = $request->role;
        $plainPassword = Str::random(12);

        User::create([
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
        ]);

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
        ];

        if ($request->filled('password')) {
            $data['password'] = bcrypt($request->password);
        }

        if ($request->id_kecx) {
            $data['id_kec']       = $request->id_kecx;
            $data['id_kel']       = $request->id_kelx;
            $data['id_puskesmas'] = $request->id_puskesmasx ?: null;
            $data['id_rs']        = $request->id_rsx ?: null;
            $data['id_posyandu']  = $request->id_posyandux ?: null;
        }

        $user->update($data);
    }

    public function destroyUser($id): void
    {
        $user = User::findOrFail($id);
        $user->delete();
    }
}

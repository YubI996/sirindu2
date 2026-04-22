<?php
namespace App\Http\Requests\Admin\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class storeUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        $userId = $this->route('id');

        $role = $this->role;

        return [
            'name'         => 'required|string',
            'email'        => [
                'required',
                'email',
                $userId
                    ? Rule::unique('users', 'email')->ignore($userId)
                    : Rule::unique('users', 'email'),
            ],
            'role'         => 'required|in:superadmin,imunisasi_faskes,surveilans_puskesmas,surveilans_rs',
            'faskes_type'  => [
                Rule::requiredIf($role === 'imunisasi_faskes'),
                'nullable',
                'in:puskesmas,rs',
            ],
            'id_puskesmas' => [
                Rule::requiredIf(
                    $role === 'surveilans_puskesmas' ||
                    ($role === 'imunisasi_faskes' && $this->faskes_type === 'puskesmas')
                ),
                'nullable', 'integer', 'exists:puskesmas,id',
            ],
            'id_rs'        => [
                Rule::requiredIf(
                    $role === 'surveilans_rs' ||
                    ($role === 'imunisasi_faskes' && $this->faskes_type === 'rs')
                ),
                'nullable', 'integer', 'exists:rumah_sakits,id',
            ],
            'id_kec'       => 'nullable|integer|exists:kecamatan,id',
            'id_kel'       => 'nullable|integer|exists:kelurahan,id',
            'id_posyandu'  => 'nullable|integer|exists:posyandu,id',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'        => 'Email sudah digunakan oleh user lain.',
            'role.required'       => 'Role wajib dipilih.',
            'role.in'             => 'Role tidak valid.',
            'faskes_type.required'=> 'Tipe faskes wajib dipilih untuk role ini.',
            'id_puskesmas.required' => 'Puskesmas wajib dipilih untuk role ini.',
            'id_rs.required'      => 'Rumah sakit wajib dipilih untuk role ini.',
            'id_kec.exists'       => 'Kecamatan tidak ditemukan.',
            'id_kel.exists'       => 'Kelurahan tidak ditemukan.',
            'id_puskesmas.exists' => 'Puskesmas tidak ditemukan.',
            'id_rs.exists'        => 'Rumah sakit tidak ditemukan.',
            'id_posyandu.exists'  => 'Posyandu tidak ditemukan.',
        ];
    }
}

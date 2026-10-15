<?php
namespace App\Http\Requests\Admin\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class storeUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // Form edit memakai akhiran x; validasi wilayah yang benar-benar akan disimpan.
        if ($this->route('id') && $this->has('id_kelx')) {
            $this->merge(['id_kel' => $this->input('id_kelx')]);
        }
    }

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
            'role'         => 'required|in:superadmin,imunisasi_faskes,surveilans_puskesmas,surveilans_rs,rt,pimpinan,kelurahan,kader,kesmas_puskesmas,kesmas_rs,sarkes',
            // Akun RT biasa wajib memilih RT; akun kelurahan wajib memilih kelurahan.
            'rt_sekelurahan' => 'nullable|boolean',
            'id_rt'        => [Rule::requiredIf($role === 'rt' && !$this->boolean('rt_sekelurahan')), 'nullable', 'integer', 'exists:rt,id'],
            'faskes_type'  => [
                Rule::requiredIf($role === 'imunisasi_faskes'),
                'nullable',
                'in:puskesmas,rs',
            ],
            'id_puskesmas' => [
                Rule::requiredIf(
                    $role === 'surveilans_puskesmas' ||
                    $role === 'kesmas_puskesmas' ||
                    ($role === 'imunisasi_faskes' && $this->faskes_type === 'puskesmas')
                ),
                'nullable', 'integer', 'exists:puskesmas,id',
            ],
            'id_rs'        => [
                Rule::requiredIf(
                    $role === 'surveilans_rs' ||
                    $role === 'kesmas_rs' ||
                    ($role === 'imunisasi_faskes' && $this->faskes_type === 'rs')
                ),
                'nullable', 'integer', 'exists:rumah_sakits,id',
            ],
            'id_kec'       => 'nullable|integer|exists:kecamatan,id',
            'id_kel'       => [Rule::requiredIf($role === 'rt' && $this->boolean('rt_sekelurahan')), 'nullable', 'integer', 'exists:kelurahan,id'],
            'id_posyandu'  => 'nullable|integer|exists:posyandu,id',
            'kesmas_id_kel' => [
                Rule::requiredIf($role === 'kelurahan'), 'nullable', 'integer', 'exists:kelurahan,id',
                // Satu akun per kelurahan (spec akun Kesmas §6); akun soft-deleted tak dihitung, akun ini sendiri dikecualikan.
                function ($attribute, $value, $fail) use ($role, $userId) {
                    if ($role !== 'kelurahan' || !$value) {
                        return;
                    }
                    $ada = \App\Models\User::where('role', 'kelurahan')->where('id_kel', $value)
                        ->when($userId, fn ($q) => $q->whereKeyNot($userId))->first();
                    if ($ada) {
                        $nama = \App\Models\Kelurahan::whereKey($value)->value('name');
                        $fail("Kelurahan {$nama} sudah memiliki akun ({$ada->email}). Satu kelurahan hanya boleh memiliki satu akun.");
                    }
                },
            ],
            'kesmas_id_posyandu' => [Rule::requiredIf($role === 'kader'), 'nullable', 'integer', 'exists:posyandu,id'],
            'nama_sarana'        => [Rule::requiredIf($role === 'sarkes'), 'nullable', 'string', 'max:150'],
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
            'id_kel.required'     => 'Kelurahan wajib dipilih untuk akun RT lingkup kelurahan.',
            'id_puskesmas.exists' => 'Puskesmas tidak ditemukan.',
            'id_rs.exists'        => 'Rumah sakit tidak ditemukan.',
            'id_posyandu.exists'  => 'Posyandu tidak ditemukan.',
            'kesmas_id_kel.required'      => 'Kelurahan wajib dipilih untuk akun kelurahan.',
            'kesmas_id_posyandu.required' => 'Posyandu wajib dipilih untuk akun kader.',
            'nama_sarana.required'        => 'Nama sarana wajib diisi untuk akun sarkes.',
            'id_rt.required'      => 'RT wajib dipilih untuk peran RT.',
            'id_rt.exists'        => 'RT tidak ditemukan.',
        ];
    }
}

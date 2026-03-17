<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * Role values (Opsi A — kolom tunggal):
     *   superadmin            — Dinkes: akses penuh SEMUA modul (imunisasi + PD3I)
     *   imunisasi_faskes      — Puskesmas/RS: input & edit data imunisasi faskes sendiri
     *   surveilans_puskesmas  — Puskesmas: input kasus + dashboard/peta scoped ke puskesmas
     *   surveilans_rs         — RS: input kasus + dashboard/peta scoped ke RS
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'type',       // legacy: 0=user, 1=super-admin, 2=admin (kept for backward compat)
        'role',       // new role-based system
        'faskes_type',// dinkes | puskesmas | rs
        'id_kec',
        'id_kel',
        'id_puskesmas',
        'id_rs',
        'id_posyandu',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    // ───────────────────────────────────────────
    // Relationships
    // ───────────────────────────────────────────

    public function puskesmas()
    {
        return $this->belongsTo(Puskesmas::class, 'id_puskesmas');
    }

    public function rumahSakit()
    {
        return $this->belongsTo(RumahSakit::class, 'id_rs');
    }

    public function kecamatan()
    {
        return $this->belongsTo(Kecamatan::class, 'id_kec');
    }

    // ───────────────────────────────────────────
    // Role Helper Methods — Global
    // ───────────────────────────────────────────

    /** Apakah user adalah superadmin sistem (akses semua modul) */
    public function isSuperAdmin(): bool
    {
        // Cek role baru, fallback ke legacy type untuk backward compat
        // Legacy type: 0=super-admin, 1=admin, 2=user
        return $this->role === 'superadmin' || ($this->role === null && $this->type == 0);
    }

    /** Apakah user punya akses ke modul tertentu */
    public function hasModuleAccess(string $module): bool
    {
        if ($this->isSuperAdmin()) return true;

        return match($module) {
            'imunisasi'   => in_array($this->role, ['imunisasi_faskes']),
            'surveilans'  => in_array($this->role, ['surveilans_puskesmas', 'surveilans_rs']),
            default       => false,
        };
    }

    // ───────────────────────────────────────────
    // Role Helpers — Imunisasi Module
    // ───────────────────────────────────────────

    /** Dinkes: full access modul imunisasi */
    public function isDinkesImunisasi(): bool
    {
        return $this->isSuperAdmin();
    }

    /** Puskesmas/RS: petugas input data imunisasi */
    public function isFaskesImunisasi(): bool
    {
        return $this->role === 'imunisasi_faskes';
    }

    // ───────────────────────────────────────────
    // Role Helpers — PD3I / Surveilans Module
    // ───────────────────────────────────────────

    /** Dinkes: full access modul surveilans PD3I */
    public function isDinkesSurveilans(): bool
    {
        return $this->isSuperAdmin();
    }

    /** Puskesmas: petugas surveilans PD3I */
    public function isSurveilansPuskesmas(): bool
    {
        return $this->role === 'surveilans_puskesmas';
    }

    /** RS: petugas surveilans PD3I */
    public function isSurveilansRS(): bool
    {
        return $this->role === 'surveilans_rs';
    }

    /**
     * Apakah user adalah faskes (bukan Dinkes) di modul surveilans PD3I.
     * Faskes PD3I bisa: input data scoped, lihat dashboard, lihat peta sebaran.
     * Faskes PD3I tidak bisa: export, hapus, lihat data faskes lain.
     */
    public function isFaskesSurveilans(): bool
    {
        return in_array($this->role, ['surveilans_puskesmas', 'surveilans_rs']);
    }

    // ───────────────────────────────────────────
    // Data Scoping Helpers
    // ───────────────────────────────────────────

    /**
     * Dapatkan ID faskes user (untuk scoping query surveillance cases).
     * Return null jika Dinkes (tidak di-scope).
     */
    public function getFaskesId(): ?int
    {
        return match($this->faskes_type) {
            'puskesmas' => $this->id_puskesmas,
            'rs'        => $this->id_rs,
            default     => null,
        };
    }

    /**
     * Dapatkan nama institusi user untuk display.
     */
    public function getInstitusiNameAttribute(): string
    {
        return match($this->faskes_type) {
            'dinkes'    => 'Dinas Kesehatan Kota Bontang',
            'puskesmas' => $this->puskesmas?->name ?? 'Puskesmas',
            'rs'        => $this->rumahSakit?->name ?? 'Rumah Sakit',
            default     => $this->name,
        };
    }

    // ───────────────────────────────────────────
    // Legacy compatibility (type accessor)
    // kept agar middleware lama (IsAdmin) tidak error
    // ───────────────────────────────────────────

    /**
     * @deprecated Gunakan $user->role dan helper methods
     */
    public function getTypeDisplayAttribute(): string
    {
        return match($this->role) {
            'superadmin'           => 'super-admin',
            'imunisasi_faskes',
            'surveilans_puskesmas',
            'surveilans_rs'        => 'admin',
            default                => 'user',
        };
    }
}

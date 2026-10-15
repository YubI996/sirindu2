<?php

namespace App\Support;

use App\Models\Kelurahan;
use App\Models\Posyandu;
use App\Models\Puskesmas;
use App\Models\RumahSakit;
use App\Models\User;

/**
 * Siapa boleh membuka dasbor mana, melihat daftar nama (NIK, nama orang tua, riwayat), dan
 * mengunduh Export Kesmas — dan sebatas wilayah mana. Spec docs/superpowers/specs/2026-10-14-akun-kesmas-design.md.
 *
 * Menggantikan BatasDataPribadiKesmas; aturan lamanya dipertahankan persis (audit Kesmas 2026-10-06, SEC-001):
 * akun `imunisasi_faskes` melihat ANGKA se-kota, data pribadi hanya catchment puskesmas-nya; akun RS atau
 * puskesmas tanpa id_puskesmas ditolak (gagal tertutup).
 *
 * Peran terbatas (PERAN_TERBATAS) TIDAK PERNAH admin-setara, apa pun `type`-nya — kalau lolos IsAdmin,
 * mereka membuka seluruh /admin (Data Anak, Import, Master Data).
 */
final class AksesKesmas
{
    public const PERAN_TERBATAS = ['pimpinan', 'kelurahan', 'kader', 'kesmas_puskesmas', 'kesmas_rs', 'sarkes'];

    public const DASBOR = ['kesmas', 'imunisasi', 'timbang', 'spm'];

    /**
     * @param list<string>       $dasbor
     * @param array<string,int>  $kunci        kunci seluruh dasbor
     * @param array<string,int>  $kunciPribadi kunci registri & export
     */
    private function __construct(
        private readonly bool $terbatas,
        private readonly array $dasbor,
        private readonly bool $nama,
        private readonly bool $export,
        private readonly array $kunci,
        private readonly array $kunciPribadi,
    ) {
    }

    public static function untuk(?User $user): self
    {
        if (!$user) {
            return self::tanpaAkses(false);
        }
        if (in_array($user->role, self::PERAN_TERBATAS, true)) {
            return self::peranTerbatas($user);
        }
        if (!self::adminSetara($user)) {
            return self::tanpaAkses(false);
        }
        if ($user->isFaskesImunisasi()) {
            if ($user->faskes_type === 'puskesmas' && $user->id_puskesmas) {
                return new self(false, self::DASBOR, true, true, [], ['id_puskesmas' => (int) $user->id_puskesmas]);
            }

            return new self(false, self::DASBOR, false, false, [], []);
        }

        return new self(false, self::DASBOR, true, true, [], []);
    }

    /** Aturan IsAdmin sebelum peran Kesmas ada — kini tanpa peran terbatas. */
    public static function adminSetara(User $user): bool
    {
        if (in_array($user->role, self::PERAN_TERBATAS, true)) {
            return false;
        }

        // Sengaja sama persis dengan IsAdmin lama, termasuk perbandingan longgar `type`.
        return $user->isSuperAdmin()
            || $user->isFaskesSurveilans()
            || $user->isFaskesImunisasi()
            || in_array($user->type, [0, 1]);
    }

    private static function peranTerbatas(User $u): self
    {
        return match ($u->role) {
            'pimpinan'         => new self(true, self::DASBOR, true, false, [], []),
            // Lingkup yang menunjuk baris yang sudah dihapus sama dengan lingkup kosong (gagal tertutup),
            // bukan dasbor kosong yang tampak "jalan".
            'kelurahan'        => self::ada(Kelurahan::class, $u->id_kel) ? self::lokal(['id_kelurahan' => (int) $u->id_kel], true) : self::tanpaAkses(true),
            'kader'            => self::ada(Posyandu::class, $u->id_posyandu) ? self::lokal(['id_posyandu' => (int) $u->id_posyandu], false) : self::tanpaAkses(true),
            'kesmas_puskesmas' => self::ada(Puskesmas::class, $u->id_puskesmas) ? self::lokal(['id_puskesmas' => (int) $u->id_puskesmas], false) : self::tanpaAkses(true),
            // RS tak punya wilayah anak: angka se-kota, tanpa nama (SEC-001).
            'kesmas_rs'        => self::ada(RumahSakit::class, $u->id_rs) ? new self(true, ['kesmas'], false, false, [], []) : self::tanpaAkses(true),
            default            => self::tanpaAkses(true), // sarkes: akses belum diputuskan
        };
    }

    /** @param class-string<\Illuminate\Database\Eloquent\Model> $model */
    private static function ada(string $model, mixed $id): bool
    {
        return $id && $model::whereKey($id)->exists();
    }

    /** @param array<string,int> $kunci */
    private static function lokal(array $kunci, bool $export): self
    {
        return new self(true, ['kesmas'], true, $export, $kunci, $kunci);
    }

    private static function tanpaAkses(bool $terbatas): self
    {
        return new self($terbatas, [], false, false, [], []);
    }

    public function terbatas(): bool
    {
        return $this->terbatas;
    }

    public function bolehDasbor(string $nama): bool
    {
        return in_array($nama, $this->dasbor, true);
    }

    public function bolehNama(): bool
    {
        return $this->nama;
    }

    public function bolehExport(): bool
    {
        return $this->export;
    }

    /** @return array<string,int> */
    public function kunciFilter(): array
    {
        return $this->kunci;
    }

    /** @return array<string,int> */
    public function kunciDataPribadi(): array
    {
        return $this->kunciPribadi;
    }

    /** @return list<int>|null null = tidak dibatasi */
    public function batasKelurahanExport(): ?array
    {
        if (isset($this->kunciPribadi['id_kelurahan'])) {
            return [$this->kunciPribadi['id_kelurahan']];
        }
        if (isset($this->kunciPribadi['id_puskesmas'])) {
            return self::kelurahanCatchment($this->kunciPribadi['id_puskesmas']);
        }

        return null;
    }

    /**
     * ID kelurahan catchment sebuah puskesmas. Kosong → [0] supaya `whereIn` tak menghasilkan apa pun
     * (puskesmas yang namanya tak dikenal tabel wilker tidak boleh berarti "tanpa batas").
     *
     * @return list<int>
     */
    public static function kelurahanCatchment(int $idPuskesmas): array
    {
        $nama = Puskesmas::whereKey($idPuskesmas)->value('name');
        $ids = $nama ? WilkerPuskesmas::catchmentKelurahanIds($nama) : [];

        return $ids ?: [0];
    }
}

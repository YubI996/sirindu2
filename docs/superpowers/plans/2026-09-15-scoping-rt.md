# Scoping Akses RT: Akun per Kelurahan + Pemilih RT (A) dan Tautan Bertoken per RT (B) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengurangi jumlah akun RT (498 RT) tanpa kehilangan jejak: (A) satu akun peran RT per kelurahan yang memilih RT setelah masuk, (B) tautan bertoken per RT yang bisa dibuka tanpa akun. Kedua mode memakai halaman & aturan `/rt/verifikasi` yang sama; setiap usulan menyimpan **nama pengisi** (`pelaksana`). Akun per-RT yang sudah ada tetap berfungsi.

**Architecture:** Satu resolver akses `RtAkses` (middleware + helper) menentukan RT aktif dari tiga sumber: sesi tautan, akun RT (`id_rt`), atau akun kelurahan (`id_rt` NULL + `?rt=`/sesi pilihan). Controller & service menerima `?User $oleh` dan `?string $pelaksana`. Token disimpan sebagai hash di `rt_akses_tautan`, dikelola Dinkes/puskesmas.

**Tech Stack:** Laravel 12, sesi database/file, PHPUnit 11.

**Spec:** keputusan klien 15 Sep 2026 (A + B); tautan berlaku 30 hari; nama pengisi wajib untuk mode kelurahan & tautan.

## Global Constraints

- Semua aturan cakupan/keputusan di `VerifikasiRtService`/`TautanIdentitasService` tidak berubah; hanya cara memilih RT & identitas pengusul yang bertambah.
- Tes lama (`PersonaRtTest`, `EndpointRtTest`, `AkunRtTest`, `ReviuVerifikasiRtTest`, dst.) harus tetap hijau **tanpa diubah**, kecuali penambahan argumen opsional.
- Token: 40 karakter acak (`Str::random(40)`), disimpan `hash('sha256')`, tidak pernah dicetak ulang setelah dibuat (hanya sekali saat pembuatan). Kedaluwarsa default 30 hari.
- Mode tautan tidak boleh mengakses apa pun di luar `/rt/*`.
- Satu proses PHPUnit; commit lokal di `main`; patch via Edit tool / skrip scratchpad (heredoc bash merusak backslash).

---

## File Structure

| Berkas | Tanggung jawab |
|---|---|
| `database/migrations/2026_09_19_000001_add_pelaksana_and_nullable_pengusul.php` | `pelaksana` di `verifikasi_anak` & `anak_tautan`; `diusulkan_oleh` nullable |
| `database/migrations/2026_09_19_000002_create_rt_akses_tautan_table.php` | tabel token |
| `app/Models/RtAksesTautan.php` | model + `aktif()` + `buat()` + `cariToken()` |
| `app/Services/RtAksesService.php` | resolusi RT aktif & mode dari request/sesi |
| `app/Http/Middleware/RtAkses.php` | pengganti `auth`+`module.role:rt` di grup `/rt` |
| `app/Http/Controllers/Rt/AksesTautanController.php` | `/rt/akses/{token}`, keluar |
| `app/Http/Controllers/Rt/VerifikasiRtController.php` (modify) | `rt()` via service, `pelaksana`, pemilih RT |
| `app/Services/VerifikasiRtService.php`, `app/Services/TautanIdentitasService.php` (modify) | `?User $oleh`, `?string $pelaksana` |
| `app/Http/Controllers/AksesTautanAdminController.php` + `resources/views/admin/verifikasi-rt/tautan-akses.blade.php` | halaman Dinkes/puskesmas |
| `resources/views/rt/verifikasi.blade.php`, `resources/views/rt/akses-tidak-berlaku.blade.php` (modify/create) | pemilih RT, modal nama pengisi, mode tautan |
| `resources/views/admin/verifikasi-rt/index.blade.php`, `gabung/index.blade.php` (modify) | tampil `pelaksana` |
| `app/Http/Requests/Admin/User/storeUserRequest.php`, `app/Repositories/Admin/User/UserRepository.php`, `resources/views/admin/user/{create,edit}.blade.php` (modify) | RT opsional untuk peran rt |
| `routes/web.php` (modify) | grup `/rt` pakai `rt.akses`; rute tautan; rute admin |
| `tests/Feature/ScopingRt/*.php` | tes |

---

### Task 1: Skema — `pelaksana`, `diusulkan_oleh` nullable, tabel `rt_akses_tautan`, model

**Files:** dua migrasi; `app/Models/RtAksesTautan.php`; `app/Models/VerifikasiAnak.php` & `AnakTautan.php` (tidak perlu berubah — `$guarded=[]`). **Test:** `tests/Feature/ScopingRt/SkemaAksesTest.php`.

**Interfaces:**
- `rt_akses_tautan`: `id, id_rt (FK rt cascade), token_hash (string 64 unique), kedaluwarsa_at, dibuat_oleh (FK users), dicabut_at, terakhir_dipakai_at, jumlah_pakai (int default 0), timestamps`.
- `RtAksesTautan::buat(Rt $rt, User $oleh, int $hari = 30): array{model: RtAksesTautan, token: string}` — mencabut tautan aktif lain untuk RT yang sama (`dicabut_at = now()`), membuat baru, mengembalikan token plaintext sekali.
- `RtAksesTautan::cariToken(string $token): ?RtAksesTautan` — hash → aktif (belum dicabut, belum kedaluwarsa) atau null.
- scope `aktif()`; relasi `rt()`, `pembuat()`; `catatPakai()` (naikkan `jumlah_pakai`, set `terakhir_dipakai_at`).

- [ ] **Step 1: Tes gagal**

```php
<?php
// tests/Feature/ScopingRt/SkemaAksesTest.php
namespace Tests\Feature\ScopingRt;

use App\Models\Rt;
use App\Models\RtAksesTautan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SkemaAksesTest extends TestCase
{
    use RefreshDatabase;

    public function test_kolom_dan_tabel_ada(): void
    {
        $this->assertTrue(Schema::hasColumn('verifikasi_anak', 'pelaksana'));
        $this->assertTrue(Schema::hasColumn('anak_tautan', 'pelaksana'));
        $this->assertTrue(Schema::hasColumns('rt_akses_tautan', ['id_rt', 'token_hash', 'kedaluwarsa_at', 'dibuat_oleh', 'dicabut_at', 'terakhir_dipakai_at', 'jumlah_pakai']));
        // diusulkan_oleh boleh NULL (mode tautan tanpa akun)
        $this->assertTrue(\DB::selectOne("SELECT IS_NULLABLE AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'verifikasi_anak' AND COLUMN_NAME = 'diusulkan_oleh'")->n === 'YES');
    }

    public function test_buat_cari_dan_cabut_tautan(): void
    {
        $rt = Rt::factory()->create();
        $u  = User::factory()->create(['type' => 0]);

        $pertama = RtAksesTautan::buat($rt, $u);
        $kedua   = RtAksesTautan::buat($rt, $u, 7);

        $this->assertSame(40, strlen($kedua['token']));
        $this->assertNull(RtAksesTautan::cariToken($pertama['token']), 'tautan lama dicabut saat yang baru dibuat');
        $this->assertSame($rt->id, RtAksesTautan::cariToken($kedua['token'])->id_rt);
        $this->assertNull(RtAksesTautan::cariToken('token-ngawur'));
        $this->assertSame(1, RtAksesTautan::aktif()->count());

        $this->travel(8)->days();
        $this->assertNull(RtAksesTautan::cariToken($kedua['token']), 'kedaluwarsa');
    }
}
```

- [ ] **Step 2: Jalankan → FAIL.**
- [ ] **Step 3: Migrasi & model**

```php
// 2026_09_19_000001_add_pelaksana_and_nullable_pengusul.php  (up)
Schema::table('verifikasi_anak', function (Blueprint $t) {
    $t->string('pelaksana', 100)->nullable()->after('catatan');
    $t->unsignedBigInteger('diusulkan_oleh')->nullable()->change();
});
Schema::table('anak_tautan', function (Blueprint $t) {
    $t->string('pelaksana', 100)->nullable()->after('catatan');
    $t->unsignedBigInteger('diusulkan_oleh')->nullable()->change();
});
// down: dropColumn pelaksana keduanya (nullable dibiarkan — tak ada FK)
```

```php
// 2026_09_19_000002_create_rt_akses_tautan_table.php (up)
Schema::create('rt_akses_tautan', function (Blueprint $t) {
    $t->id();
    $t->unsignedBigInteger('id_rt');
    $t->string('token_hash', 64)->unique();
    $t->timestamp('kedaluwarsa_at');
    $t->unsignedBigInteger('dibuat_oleh');
    $t->timestamp('dicabut_at')->nullable();
    $t->timestamp('terakhir_dipakai_at')->nullable();
    $t->unsignedInteger('jumlah_pakai')->default(0);
    $t->timestamps();
    $t->foreign('id_rt')->references('id')->on('rt')->cascadeOnDelete();
    $t->index(['id_rt', 'dicabut_at']);
});
```

```php
<?php
// app/Models/RtAksesTautan.php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** Tautan bertoken per RT (mode B) — token hanya diketahui saat dibuat; DB menyimpan hash. */
class RtAksesTautan extends Model
{
    protected $table = 'rt_akses_tautan';
    protected $guarded = [];
    protected $casts = ['kedaluwarsa_at' => 'datetime', 'dicabut_at' => 'datetime', 'terakhir_dipakai_at' => 'datetime'];

    public function scopeAktif(Builder $q): Builder
    {
        return $q->whereNull('dicabut_at')->where('kedaluwarsa_at', '>', now());
    }

    public function rt(): BelongsTo { return $this->belongsTo(Rt::class, 'id_rt'); }
    public function pembuat(): BelongsTo { return $this->belongsTo(User::class, 'dibuat_oleh'); }

    /** @return array{model: self, token: string} */
    public static function buat(Rt $rt, User $oleh, int $hari = 30): array
    {
        static::where('id_rt', $rt->id)->whereNull('dicabut_at')->update(['dicabut_at' => now()]);
        $token = Str::random(40);
        $model = static::create([
            'id_rt' => $rt->id, 'token_hash' => hash('sha256', $token),
            'kedaluwarsa_at' => now()->addDays($hari), 'dibuat_oleh' => $oleh->id,
        ]);
        return ['model' => $model, 'token' => $token];
    }

    public static function cariToken(string $token): ?self
    {
        return static::aktif()->where('token_hash', hash('sha256', $token))->first();
    }

    public function catatPakai(): void
    {
        $this->increment('jumlah_pakai', 1, ['terakhir_dipakai_at' => now()]);
    }
}
```

- [ ] **Step 4: Jalankan → lulus.** (`change()` pada MySQL butuh `doctrine/dbal`? Laravel 11+ tidak — bawaan.)
- [ ] **Step 5: Commit** — `feat(scoping-rt): skema pelaksana, pengusul nullable, tabel rt_akses_tautan`

---

### Task 2: `RtAksesService` + middleware `RtAkses` + rute

**Files:** `app/Services/RtAksesService.php`, `app/Http/Middleware/RtAkses.php`, `bootstrap/app.php` (alias `rt.akses`), `routes/web.php`. **Test:** `tests/Feature/ScopingRt/AksesRtTest.php`.

**Interfaces:**
- `RtAksesService::konteks(Request $r): array{mode:'tautan'|'akun_rt'|'akun_kel'|'superadmin', rt:?Rt, user:?User, tautan:?RtAksesTautan, rt_list:Collection, butuh_pelaksana:bool}`:
  - sesi `rt_akses_id` → `RtAksesTautan::aktif()->find()` → mode `tautan`, `rt` = tautannya (sesi basi → lupakan & lanjut ke aturan berikut);
  - user superadmin → `?rt=` (wajib untuk API; halaman boleh tanpa → picker semua RT);
  - user rt dengan `id_rt` → mode `akun_rt`;
  - user rt tanpa `id_rt` → mode `akun_kel`: `rt_list` = RT di `user.id_kel`; `rt` = `?rt=` bila ada & sekelurahan (simpan ke sesi `rt_pilihan`), else sesi `rt_pilihan` bila masih sekelurahan, else null;
  - `butuh_pelaksana` = mode `tautan` atau `akun_kel`.
- `RtAkses` middleware: lolos bila sesi tautan aktif ATAU (`auth()->check()` dan (`isRt()` atau `isSuperAdmin()`)). Gagal: `expectsJson()` → 401 JSON `{"message":"Unauthenticated."}` bila tak login, 403 bila login tapi bukan rt; non-JSON → redirect `route('login')` / abort 403.
- Rute: grup `/rt` memakai `['rt.akses']` (bukan `auth`+`module.role`); tambahan di luar grup: `GET rt/akses/{token}` → `AksesTautanController@masuk` (`rt.akses.masuk`), `POST rt/akses/keluar` → `@keluar` (`rt.akses.keluar`).

- [ ] **Step 1: Tes gagal**

```php
<?php
// tests/Feature/ScopingRt/AksesRtTest.php
namespace Tests\Feature\ScopingRt;

use App\Models\Anak;
use App\Models\Rt;
use App\Models\RtAksesTautan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AksesRtTest extends TestCase
{
    use RefreshDatabase;

    private function anak(string $nik, Rt $rt): Anak
    {
        return Anak::create(['nama' => 'Anak '.$nik, 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2024-01-01',
            'status' => 1, 'sumber' => 'capil', 'id_kel' => $rt->id_kelurahan, 'id_rt' => $rt->id]);
    }

    public function test_akun_kelurahan_harus_memilih_rt_sekelurahan(): void
    {
        $rt1 = Rt::factory()->create();
        $rt2 = Rt::factory()->create(['id_kelurahan' => $rt1->id_kelurahan]);
        $rtLain = Rt::factory()->create();
        $u = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => null, 'id_kel' => $rt1->id_kelurahan]);
        $this->anak('3201000000060001', $rt1);
        $this->anak('3201000000060002', $rtLain);

        $this->actingAs($u)->get(route('rt.verifikasi'))->assertOk()->assertSee('Pilih RT')->assertSee($rt2->name);
        $this->actingAs($u)->getJson(route('rt.api.warga'))->assertStatus(422); // belum memilih RT
        $this->actingAs($u)->getJson(route('rt.api.warga', ['rt' => $rtLain->id]))->assertForbidden();

        $rows = $this->actingAs($u)->getJson(route('rt.api.warga', ['rt' => $rt1->id]))->assertOk()->json('rows');
        $this->assertCount(1, $rows);
        // pilihan diingat di sesi
        $this->assertCount(1, $this->getJson(route('rt.api.warga'))->assertOk()->json('rows'));
    }

    public function test_tautan_token_membuka_halaman_rt_tanpa_login(): void
    {
        $rt = Rt::factory()->create();
        $super = User::factory()->create(['type' => 0]);
        $t = RtAksesTautan::buat($rt, $super);
        $this->anak('3201000000060003', $rt);

        $this->get(route('rt.akses.masuk', $t['token']))->assertRedirect(route('rt.verifikasi'));
        $this->get(route('rt.verifikasi'))->assertOk()->assertSee($rt->name)->assertSee(route('rt.akses.keluar'));
        $this->assertCount(1, $this->getJson(route('rt.api.warga'))->assertOk()->json('rows'));
        $this->assertSame(1, (int) $t['model']->fresh()->jumlah_pakai);

        $this->get('/admin/home')->assertRedirect(route('login'));   // mode tautan tak menjangkau admin
        $this->post(route('rt.akses.keluar'))->assertRedirect();
        $this->getJson(route('rt.api.warga'))->assertStatus(401);
    }

    public function test_tautan_kedaluwarsa_atau_dicabut_ditolak(): void
    {
        $rt = Rt::factory()->create();
        $super = User::factory()->create(['type' => 0]);
        $t = RtAksesTautan::buat($rt, $super, 1);

        $this->travel(2)->days();
        $this->get(route('rt.akses.masuk', $t['token']))->assertStatus(410)->assertSee('tidak berlaku');
        $this->get(route('rt.akses.masuk', 'ngawur'))->assertStatus(410);
    }

    public function test_akun_rt_dan_superadmin_tetap_seperti_semula(): void
    {
        $rt = Rt::factory()->create();
        $u = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => $rt->id, 'id_kel' => $rt->id_kelurahan]);
        $this->anak('3201000000060004', $rt);
        $this->assertCount(1, $this->actingAs($u)->getJson(route('rt.api.warga'))->assertOk()->json('rows'));

        $super = User::factory()->create(['type' => 0]);
        $this->actingAs($super)->getJson(route('rt.api.warga', ['rt' => $rt->id]))->assertOk();
        $faskes = User::factory()->create(['type' => 1, 'role' => 'imunisasi_faskes']);
        $this->actingAs($faskes)->get(route('rt.verifikasi'))->assertForbidden();
        $this->getJson(route('rt.api.warga'))->assertStatus(401);
    }
}
```

Catatan: tes `getJson` tanpa `actingAs` setelah `actingAs` sebelumnya dalam satu method mewarisi sesi (lihat gotcha `AkunRtTest`) — di sini justru dimanfaatkan untuk "pilihan diingat di sesi" dan mode tautan.

- [ ] **Step 2: Jalankan → FAIL** (`Route [rt.akses.masuk] not defined` / 403).
- [ ] **Step 3: Tulis service, middleware, controller tautan, rute**

```php
<?php
// app/Services/RtAksesService.php
namespace App\Services;

use App\Models\Rt;
use App\Models\RtAksesTautan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/** Siapa yang sedang membuka halaman RT dan RT mana yang aktif (mode A/B/akun RT/superadmin). */
class RtAksesService
{
    public const SESI_TAUTAN  = 'rt_akses_id';
    public const SESI_PILIHAN = 'rt_pilihan';

    /** @return array{mode:string, rt:?Rt, user:?User, tautan:?RtAksesTautan, rt_list:Collection, butuh_pelaksana:bool} */
    public function konteks(Request $r): array
    {
        $tautan = $this->tautanAktif($r);
        if ($tautan) {
            return ['mode' => 'tautan', 'rt' => $tautan->rt, 'user' => null, 'tautan' => $tautan, 'rt_list' => collect(), 'butuh_pelaksana' => true];
        }

        $user = $r->user();
        if (!$user) {
            return ['mode' => 'tamu', 'rt' => null, 'user' => null, 'tautan' => null, 'rt_list' => collect(), 'butuh_pelaksana' => false];
        }

        if ($user->isSuperAdmin()) {
            $rt = $r->query('rt') ? Rt::find((int) $r->query('rt')) : null;
            return ['mode' => 'superadmin', 'rt' => $rt, 'user' => $user, 'tautan' => null, 'rt_list' => Rt::orderBy('name')->get(), 'butuh_pelaksana' => false];
        }

        if ($user->id_rt) {
            return ['mode' => 'akun_rt', 'rt' => Rt::find($user->id_rt), 'user' => $user, 'tautan' => null, 'rt_list' => collect(), 'butuh_pelaksana' => false];
        }

        // Akun kelurahan: pilih RT di kelurahannya, diingat di sesi.
        $rtList = Rt::where('id_kelurahan', (int) $user->id_kel)->orderBy('name')->get();
        $rt = null;
        if ($r->query('rt')) {
            $rt = $rtList->firstWhere('id', (int) $r->query('rt'));
            if (!$rt) {
                abort(403, 'RT di luar kelurahan akun ini.');
            }
            $r->session()->put(self::SESI_PILIHAN, $rt->id);
        } elseif ($r->session()->has(self::SESI_PILIHAN)) {
            $rt = $rtList->firstWhere('id', (int) $r->session()->get(self::SESI_PILIHAN));
        }

        return ['mode' => 'akun_kel', 'rt' => $rt, 'user' => $user, 'tautan' => null, 'rt_list' => $rtList, 'butuh_pelaksana' => true];
    }

    public function tautanAktif(Request $r): ?RtAksesTautan
    {
        $id = $r->session()->get(self::SESI_TAUTAN);
        if (!$id) return null;
        $t = RtAksesTautan::aktif()->with('rt')->find($id);
        if (!$t) {
            $r->session()->forget(self::SESI_TAUTAN);
        }
        return $t;
    }
}
```

```php
<?php
// app/Http/Middleware/RtAkses.php
namespace App\Http\Middleware;

use App\Services\RtAksesService;
use Closure;
use Illuminate\Http\Request;

/** Grup /rt: boleh masuk lewat sesi tautan (tanpa akun) ATAU akun peran rt / superadmin. */
class RtAkses
{
    public function __construct(private RtAksesService $akses) {}

    public function handle(Request $request, Closure $next)
    {
        if ($this->akses->tautanAktif($request)) {
            return $next($request);
        }
        $user = $request->user();
        if (!$user) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated.'], 401)
                : redirect()->route('login');
        }
        if ($user->isRt() || $user->isSuperAdmin()) {
            return $next($request);
        }
        abort(403, 'Halaman ini hanya untuk akun RT.');
    }
}
```

`bootstrap/app.php`: tambah alias `'rt.akses' => \App\Http\Middleware\RtAkses::class`.

```php
<?php
// app/Http/Controllers/Rt/AksesTautanController.php
namespace App\Http\Controllers\Rt;

use App\Http\Controllers\Controller;
use App\Models\RtAksesTautan;
use App\Services\RtAksesService;
use Illuminate\Http\Request;

class AksesTautanController extends Controller
{
    /** Buka tautan → simpan di sesi → halaman RT. Tautan basi → 410 dengan penjelasan. */
    public function masuk(Request $request, string $token)
    {
        $t = RtAksesTautan::cariToken($token);
        if (!$t) {
            return response()->view('rt.akses-tidak-berlaku', [], 410);
        }
        $t->catatPakai();
        $request->session()->put(RtAksesService::SESI_TAUTAN, $t->id);

        return redirect()->route('rt.verifikasi');
    }

    public function keluar(Request $request)
    {
        $request->session()->forget([RtAksesService::SESI_TAUTAN, RtAksesService::SESI_PILIHAN, 'rt_pelaksana']);

        return redirect()->route('landing');
    }
}
```

`resources/views/rt/akses-tidak-berlaku.blade.php`: halaman mandiri (gaya `errors/403`): "Tautan ini tidak berlaku (kedaluwarsa atau sudah diganti). Minta tautan baru ke puskesmas/Dinkes."

Rute:
```php
Route::get('rt/akses/{token}', [App\Http\Controllers\Rt\AksesTautanController::class, 'masuk'])->name('rt.akses.masuk');
Route::post('rt/akses/keluar', [App\Http\Controllers\Rt\AksesTautanController::class, 'keluar'])->name('rt.akses.keluar');

Route::middleware(['rt.akses'])->prefix('rt')->name('rt.')->group(function () { …rute lama tetap… });
```

Controller `VerifikasiRtController::rt()` → ganti dengan:
```php
    private function rt(Request $request): Rt
    {
        $k = $this->akses->konteks($request);
        if (!$k['rt']) {
            if ($k['mode'] === 'superadmin') abort(403, 'Pilih RT lewat parameter ?rt=');
            abort(422, 'Pilih RT terlebih dahulu.');
        }
        return $k['rt'];
    }
```
(`RtAksesService` disuntik di konstruktor.) `index()` mengirim `konteks` ke view: `mode`, `rt` (boleh null → halaman menampilkan pemilih RT), `rt_list`, `butuh_pelaksana`.

- [ ] **Step 4: View — pemilih RT & mode tautan** (di `rt/verifikasi.blade.php`): bila `$rt === null`: tampilkan kartu "Pilih RT" dengan `<select>` dari `$rt_list` + tombol → `GET /rt/verifikasi?rt=ID`; sembunyikan tab/tabel. Header: mode `akun_kel` tampilkan `<select>` kecil ganti RT (`?rt=`); mode `tautan` tampilkan "Akses lewat tautan" dan tombol Keluar mem-POST `route('rt.akses.keluar')`; mode lain tetap `route('logout')`. `{{ auth()->user()->name }}` → `{{ $user?->name ?? 'Tautan RT' }}`.
- [ ] **Step 5: Jalankan** `AksesRtTest` + seluruh `tests/Feature/VerifikasiRt` → lulus.
- [ ] **Step 6: Commit** — `feat(scoping-rt): akses RT lewat akun kelurahan (pemilih RT) dan tautan bertoken`

---

### Task 3: `pelaksana` — nama pengisi di usulan & tampil di reviu

**Files:** `VerifikasiRtService.php`, `TautanIdentitasService.php`, `Rt/VerifikasiRtController.php`, `rt/verifikasi.blade.php`, `admin/verifikasi-rt/index.blade.php`, `admin/verifikasi-rt/gabung/index.blade.php`. **Test:** `tests/Feature/ScopingRt/PelaksanaTest.php`.

**Interfaces:**
- `VerifikasiRtService::usulkan(Anak, Rt, ?User $oleh, string $status, ?string $catatan = null, ?string $pelaksana = null)`; `TautanIdentitasService::putuskan(int, int, Rt, ?User $oleh, string $keputusan, ?string $catatan = null, ?string $pelaksana = null)` — `diusulkan_oleh = $oleh?->id`, `pelaksana` disimpan.
- Controller: validasi `pelaksana` `nullable|string|max:100`, **wajib** bila `konteks['butuh_pelaksana']` (422 "Nama pengisi wajib diisi."). Disimpan juga ke sesi `rt_pelaksana` agar request berikutnya boleh tanpa mengirim ulang (fallback).
- View: saat `butuh_pelaksana` & belum ada di sesi/`localStorage` → modal kecil "Siapa yang mengisi?" sebelum keputusan pertama; nilai dikirim di tiap POST (`pelaksana`).
- Reviu: kolom "Diusulkan" → `{{ $v->pengusul?->name ?? 'Tautan RT' }}` + `<br><small>pengisi: {{ $v->pelaksana }}</small>` bila ada; sama di tab tautan & antrean gabung.
- `antreanQuery` fallback kelurahan via `rt` sudah ada; `kelurahanTautan()` memakai `pengusul?->rt` → tambahkan fallback ke RT tautan? Tidak perlu: kedua anak selalu punya `id_kel` di jalur RT (kandidat); biarkan.

- [ ] **Step 1: Tes gagal**

```php
<?php
// tests/Feature/ScopingRt/PelaksanaTest.php
namespace Tests\Feature\ScopingRt;

use App\Models\Anak;
use App\Models\Rt;
use App\Models\RtAksesTautan;
use App\Models\User;
use App\Models\VerifikasiAnak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PelaksanaTest extends TestCase
{
    use RefreshDatabase;

    private function anak(string $nik, Rt $rt): Anak
    {
        return Anak::create(['nama' => 'Anak '.$nik, 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2024-01-01',
            'status' => 1, 'sumber' => 'capil', 'id_kel' => $rt->id_kelurahan, 'id_rt' => $rt->id]);
    }

    public function test_mode_tautan_wajib_nama_pengisi_dan_tersimpan_tanpa_user(): void
    {
        $rt = Rt::factory()->create();
        $super = User::factory()->create(['type' => 0]);
        $t = RtAksesTautan::buat($rt, $super);
        $anak = $this->anak('3201000000061001', $rt);
        $this->get(route('rt.akses.masuk', $t['token']));

        $this->postJson(route('rt.api.usulkan', $anak), ['status' => 'berdomisili'])->assertStatus(422);
        $this->postJson(route('rt.api.usulkan', $anak), ['status' => 'berdomisili', 'pelaksana' => 'Bu Sari, Ketua RT'])->assertOk();

        $v = VerifikasiAnak::sole();
        $this->assertNull($v->diusulkan_oleh);
        $this->assertSame('Bu Sari, Ketua RT', $v->pelaksana);
        // pengisi diingat di sesi → request berikutnya boleh tanpa mengirim ulang
        $this->postJson(route('rt.api.usulkan', $anak), ['status' => 'pindah'])->assertOk();
        $this->assertSame('Bu Sari, Ketua RT', VerifikasiAnak::latest('id')->first()->pelaksana);
    }

    public function test_akun_rt_tidak_wajib_pelaksana_dan_reviu_menampilkan_pengisi(): void
    {
        $rt = Rt::factory()->create();
        $u = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => $rt->id, 'id_kel' => $rt->id_kelurahan, 'name' => 'Akun RT 05']);
        $anak = $this->anak('3201000000061002', $rt);
        $this->actingAs($u)->postJson(route('rt.api.usulkan', $anak), ['status' => 'pindah', 'pelaksana' => 'Pak Joko'])->assertOk();

        $super = User::factory()->create(['type' => 0]);
        $this->actingAs($super)->get(route('admin.verifikasiRt.index'))->assertOk()->assertSee('Akun RT 05')->assertSee('Pak Joko');
    }

    public function test_akun_kelurahan_wajib_pelaksana(): void
    {
        $rt = Rt::factory()->create();
        $u = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => null, 'id_kel' => $rt->id_kelurahan]);
        $anak = $this->anak('3201000000061003', $rt);

        $this->actingAs($u)->postJson(route('rt.api.usulkan', ['anak' => $anak, 'rt' => $rt->id]), ['status' => 'berdomisili'])->assertStatus(422);
        $this->actingAs($u)->postJson(route('rt.api.usulkan', ['anak' => $anak, 'rt' => $rt->id]), ['status' => 'berdomisili', 'pelaksana' => 'Kader Nia'])->assertOk();
    }
}
```

- [ ] **Step 2: Jalankan → FAIL.**
- [ ] **Step 3: Implementasi** sesuai Interfaces (service: tambah parameter opsional di akhir; `diusulkan_oleh => $oleh?->id`; `pelaksana => $pelaksana ?: null`. Controller `usulkan`/`putuskan`: `$pelaksana = trim((string) $request->input('pelaksana')) ?: $request->session()->get('rt_pelaksana'); if ($k['butuh_pelaksana'] && !$pelaksana) return 422 ['message' => 'Nama pengisi wajib diisi.', 'errors' => ['pelaksana' => [...]]]; if ($pelaksana) session()->put('rt_pelaksana', $pelaksana);` lalu teruskan ke service dengan `$k['user']`). View: modal pengisi (input + tombol) muncul bila `BUTUH_PELAKSANA && !localStorage.rt_pelaksana`; JS sertakan `pelaksana` di body `usulkan`/`putuskan`.
- [ ] **Step 4: Jalankan** `PelaksanaTest` + `tests/Feature/VerifikasiRt` → lulus.
- [ ] **Step 5: Commit** — `feat(scoping-rt): nama pengisi (pelaksana) di tiap usulan RT`

---

### Task 4: Halaman kelola tautan (Dinkes/puskesmas) + akun kelurahan di manajemen user

**Files:** `app/Http/Controllers/AksesTautanAdminController.php`, `resources/views/admin/verifikasi-rt/tautan-akses.blade.php`, `routes/web.php`, `admin/verifikasi-rt/index.blade.php` (tautan menu ke halaman ini), `storeUserRequest.php`, `UserRepository.php`, `admin/user/create.blade.php` & `edit.blade.php`. **Test:** `tests/Feature/ScopingRt/KelolaTautanTest.php`.

**Interfaces:**
- `GET admin/verifikasi-rt/tautan-akses` (`admin.aksesTautan.index`): faskes → RT di `user.id_kel`; superadmin → semua, filter `?kel=`. Tabel: RT, status tautan (aktif s.d. tgl / tidak ada / dicabut), terakhir dipakai, jumlah pakai, tombol **Buat tautan** (`POST …/tautan-akses/{rt}` → `admin.aksesTautan.buat`, param `hari` default 30) dan **Cabut** (`POST …/tautan-akses/{tautan}/cabut` → `admin.aksesTautan.cabut`). Setelah buat: flash `tautan_baru` = URL lengkap `route('rt.akses.masuk', $token)` ditampilkan sekali dengan tombol salin + teks WhatsApp siap kirim.
- Scope: faskes hanya RT sekelurahan (403 selain itu).
- Manajemen user: `id_rt` **opsional** untuk peran rt; bila kosong wajib `id_kel` (`Rule::requiredIf($role === 'rt' && !$this->id_rt)` pada `id_kel`); repository: bila peran rt tanpa `id_rt` → `id_rt = null`, `id_kel` dari form, `id_kec` dari kelurahan. Form: label "RT (kosongkan = akun seluruh kelurahan, memilih RT saat masuk)".
- Tautan di tab reviu: tombol "Kelola tautan akses RT".

- [ ] **Step 1: Tes gagal**

```php
<?php
// tests/Feature/ScopingRt/KelolaTautanTest.php
namespace Tests\Feature\ScopingRt;

use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\RtAksesTautan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KelolaTautanTest extends TestCase
{
    use RefreshDatabase;

    public function test_faskes_membuat_dan_mencabut_tautan_rt_sekelurahan_saja(): void
    {
        $rt = Rt::factory()->create();
        $rtLain = Rt::factory()->create();
        $faskes = User::factory()->create(['type' => 1, 'id_kel' => $rt->id_kelurahan]);

        $this->actingAs($faskes)->get(route('admin.aksesTautan.index'))->assertOk()->assertSee($rt->name)->assertDontSee($rtLain->name);
        $this->actingAs($faskes)->post(route('admin.aksesTautan.buat', $rtLain))->assertForbidden();

        $res = $this->actingAs($faskes)->post(route('admin.aksesTautan.buat', $rt), ['hari' => 14])->assertRedirect();
        $url = session('tautan_baru');
        $this->assertStringContainsString('/rt/akses/', $url);
        $t = RtAksesTautan::sole();
        $this->assertEqualsWithDelta(now()->addDays(14)->timestamp, $t->kedaluwarsa_at->timestamp, 5);

        $this->actingAs($faskes)->post(route('admin.aksesTautan.cabut', $t))->assertRedirect();
        $this->assertNotNull($t->fresh()->dicabut_at);
        $this->get($url)->assertStatus(410);
    }

    public function test_superadmin_membuat_akun_rt_tanpa_rt_hanya_kelurahan(): void
    {
        $super = User::factory()->create(['type' => 0, 'role' => 'superadmin']);
        $kel = Kelurahan::factory()->create();

        $this->actingAs($super)->post(route('super.admin.storeUser'), [
            'name' => 'Akun Kel', 'email' => 'kel@sirindu.go.id', 'role' => 'rt', 'id_kec' => $kel->id_kecamatan, 'id_kel' => $kel->id,
        ])->assertRedirect(route('super.admin.user'));

        $u = User::where('email', 'kel@sirindu.go.id')->firstOrFail();
        $this->assertNull($u->id_rt);
        $this->assertSame($kel->id, (int) $u->id_kel);

        $this->actingAs($super)->from(route('super.admin.user'))->post(route('super.admin.storeUser'), [
            'name' => 'Tanpa Wilayah', 'email' => 'x@sirindu.go.id', 'role' => 'rt',
        ])->assertSessionHasErrors('id_kel');
    }
}
```

- [ ] **Step 2: Jalankan → FAIL.**
- [ ] **Step 3: Implementasi** sesuai Interfaces (controller ~60 baris; view tabel Bootstrap 4 dengan `@if(session('tautan_baru'))` kotak hijau: URL + `<button onclick="navigator.clipboard.writeText(...)">Salin</button>` + teks "Assalamualaikum Pak/Bu RT, berikut tautan verifikasi warga: …"; request rules; repository `wilayahDariRt` → bila role rt tanpa id_rt: `['id_rt'=>null,'id_kel'=>$request->id_kel,'id_kec'=>Kelurahan::find($request->id_kel)?->id_kecamatan]`; form label).
- [ ] **Step 4: Jalankan** `KelolaTautanTest` + `tests/Feature/VerifikasiRt/BuatAkunRtTest.php` → lulus.
- [ ] **Step 5: Commit** — `feat(scoping-rt): kelola tautan akses RT + akun rt per kelurahan`

---

### Task 5: Suite penuh, cek visual, memori

- [ ] `php artisan migrate` DB dev; buat tautan untuk RT 1BELIMBING dari `/admin/verifikasi-rt/tautan-akses`, buka di tab penyamaran → halaman RT tanpa login, modal nama pengisi, keputusan tersimpan dengan `pelaksana`; ubah akun `rt01.belimbing` jadi akun kelurahan (id_rt NULL) → pemilih RT muncul.
- [ ] Suite penuh → `OK`.
- [ ] Memori `project_verifikasi_rt.md`: mode akses A/B, tabel `rt_akses_tautan`, sesi `rt_akses_id`/`rt_pilihan`/`rt_pelaksana`.

## Self-Review
- Cakupan: A (akun kelurahan + pemilih RT + pelaksana) Task 2–4; B (tautan bertoken, 30 hari, cabut, tercatat, tanpa akun, 410 saat basi) Task 1–2, 4; reviu menampilkan pengisi Task 3; akun per-RT lama tetap (Task 2 tes). Placeholder: tidak ada. Nama: `RtAksesService::{konteks,tautanAktif}`, `RtAksesTautan::{buat,cariToken,catatPakai,aktif}`, middleware `rt.akses`, rute `rt.akses.masuk/keluar`, `admin.aksesTautan.{index,buat,cabut}`, sesi `rt_akses_id/rt_pilihan/rt_pelaksana` konsisten.

# Handoff — Scoping Akses RT (A: akun kelurahan + pemilih RT, B: tautan bertoken)

## Pembaruan penyelesaian — 15 September 2026

**T1–T5 sudah selesai dan diverifikasi di lokal.** Baseline membuktikan 120 tes pekerjaan lama lolos;
3 kegagalan T4 sudah diselesaikan. Suite final: **694 tes / 2.241 assertion, tanpa failure/error**
(13 PHPUnit deprecation). Tiga migrasi dev sudah dijalankan; cek browser desktop/ponsel lolos.
Rincian perubahan, bukti, dan batas pengujian: [laporan verifikasi](verification-2026-09-15-scoping-rt.md).
Belum push/deploy produksi. Bagian berikut dipertahankan sebagai **jejak handoff awal**, bukan status terkini.

## Jejak handoff awal (sebelum dilanjutkan)

Repo `D:\apps\laragon\www\sirindu`, branch `main` (lokal, **belum push**; origin di `6b45feb`).
Plan lengkap: `docs/superpowers/plans/2026-09-15-scoping-rt.md` (5 task; `/docs` gitignored → `git add -f`).

## Status

| Task | Status | Commit |
|---|---|---|
| Plan | ✅ | `d6f88e2` |
| T1 skema: `verifikasi_anak.pelaksana`, `anak_tautan.pelaksana` + `anak_tautan.id_rt`, `diusulkan_oleh` nullable, tabel `rt_akses_tautan`, `users.rt_sekelurahan`, model `RtAksesTautan` | ✅ | `b6b80a4` (+ migrasi diubah di `63fb8b4`) |
| T2 `RtAksesService`, middleware `rt.akses`, `/rt/akses/{token}`, pemilih RT di halaman RT | ✅ | `b9e8f7b` |
| T3 `pelaksana` (nama pengisi) wajib utk mode tautan & akun kelurahan, modal di halaman RT, tampil di antrean reviu | ✅ | `63fb8b4` |
| T4 halaman kelola tautan (Dinkes/puskesmas) + akun rt lingkup kelurahan di manajemen user | ⏸ **setengah jalan, belum commit** | — |
| T5 suite penuh, cek visual, memori | ⬜ | — |

Tes hijau saat ini: `tests/Feature/ScopingRt/{SkemaAksesTest,AksesRtTest,PelaksanaTest}.php` + seluruh `tests/Feature/VerifikasiRt` (120 tes).

## Sisa T4 — file yang sudah ada (uncommitted, `git status`)

- `app/Http/Controllers/AksesTautanAdminController.php` — `index` (faskes: RT sekelurahan; superadmin: semua + filter `?kel=`), `buat(Rt)` (`hari` 1–365, default 30; flash `tautan_baru` = `['rt','kelurahan','url','kedaluwarsa']`), `cabut(RtAksesTautan)`; `bolehKelola()` → 403 utk peran rt / user tanpa `id_kel` / RT beda kelurahan.
- `resources/views/admin/verifikasi-rt/tautan-akses.blade.php` — tabel RT + status + tombol Buat/Buat ulang/Cabut; kotak hijau sekali-tampil dengan tombol Salin + teks WhatsApp.
- `routes/web.php` — `admin.aksesTautan.index|buat|cabut` (grup `admin/`, `['auth','is_admin']`).
- `app/Models/Rt.php` — relasi `tautanAktif()` (hasOne ofMany, scope `aktif`).
- `resources/views/admin/verifikasi-rt/index.blade.php` — tautan "Kelola tautan akses RT" di nav-tabs.
- `resources/views/vendor/admin/layouts/partials/leftsidebar.blade.php` — `admin.aksesTautan.*` ditambah ke pola `routeIs` (2 tempat + 2 menu).
- `tests/Feature/ScopingRt/KelolaTautanTest.php` — 5 tes; **3 masih merah**:
  1. `test_faskes_membuat_dan_mencabut_tautan_rt_sekelurahan_saja` gagal di baris 37 `assertDontSee(basename($url))`: **tes-nya yang salah** — flash `tautan_baru` memang tampil di request berikutnya (GET pertama setelah POST). Perbaiki tes: lakukan GET dua kali, atau assert `assertDontSee` di GET kedua.
  2. `test_superadmin_membuat_akun_rt_lingkup_kelurahan` — **belum diimplementasi** (lihat bawah).
  3. `test_form_user_menyediakan_pilihan_lingkup_kelurahan` — **belum diimplementasi**.

## Yang belum dikerjakan di T4 (manajemen user)

- `app/Http/Requests/Admin/User/storeUserRequest.php`: tambah `'rt_sekelurahan' => 'nullable|boolean'`; `id_rt` → `Rule::requiredIf($role === 'rt' && !$this->boolean('rt_sekelurahan'))`; `id_kel` → tambah `Rule::requiredIf($role === 'rt' && $this->boolean('rt_sekelurahan'))` + pesan `id_kel.required` "Kelurahan wajib dipilih untuk akun RT lingkup kelurahan." (tes lama `BuatAkunRtTest::test_role_rt_tanpa_id_rt_ditolak` harus tetap lolos → tanpa checkbox, `id_rt` tetap wajib).
- `app/Repositories/Admin/User/UserRepository.php`: `wilayahDariRt()` — bila `role === 'rt' && $request->boolean('rt_sekelurahan')` → `['id_rt' => null, 'rt_sekelurahan' => true, 'id_kel' => $kel, 'id_kec' => Kelurahan::find($kel)?->id_kecamatan, 'id_posyandu' => null]` dengan `$kel = $request->id_kel ?: $request->id_kelx` (form edit memakai `id_kecx/id_kelx`); jalur RT biasa → tambahkan `'rt_sekelurahan' => false`; `updateUser()` set `'rt_sekelurahan' => false` default lalu ditimpa `wilayahDariRt`.
- `resources/views/admin/user/create.blade.php` & `edit.blade.php`: checkbox pola hidden+checkbox (aturan CLAUDE.md: baca dengan `$request->boolean()`): `<input type="hidden" name="rt_sekelurahan" value="0"><input type="checkbox" name="rt_sekelurahan" value="1" id="...">` label "Akun lingkup kelurahan — tidak terikat satu RT, memilih RT saat masuk"; di grup RT (`create_rt_group` / `edit_rt_group`); JS: bila dicentang, select RT di-disable/dikosongkan; edit: `checked` bila `$user->rt_sekelurahan`.
- Setelah hijau: `php artisan view:clear`, jalankan `tests/Feature/ScopingRt tests/Feature/VerifikasiRt`, commit `feat(scoping-rt): kelola tautan akses RT + akun rt per kelurahan`.

## T5

1. `php artisan migrate` di DB dev `sirindu` (3 migrasi baru `2026_09_19_*`). MySQL harus dinyalakan manual: `mysqld.exe --defaults-file=D:\apps\laragon\bin\mysql\mysql-8.0.30-winx64\my.ini`.
2. Cek visual: login `dinkes@sirindu.go.id` / `Sirindu@2026` → `/admin/verifikasi-rt/tautan-akses` → buat tautan RT 1BELIMBING → buka di tab penyamaran → modal "Siapa yang mengisi?" → tandai anak `CONTOH000001` → cek `/admin/verifikasi-rt` tampil "pengisi: …". Lalu ubah akun `rt01.belimbing@sirindu.go.id` jadi lingkup kelurahan → login → pemilih RT muncul.
3. Suite penuh (satu proses PHPUnit saja, ~4–14 menit): `php vendor/bin/phpunit` → harus `OK` (ada "OK, but there were issues" = deprecation, bukan kegagalan).
4. Update memori `C:\Users\diskominfo\.claude\projects\D--apps-laragon-www-sirindu\memory\project_verifikasi_rt.md` + `MEMORY.md`: mode akses A/B, tabel `rt_akses_tautan`, sesi `rt_akses_id`/`rt_pilihan`/`rt_pelaksana`, `users.rt_sekelurahan`; feature 1 (import PJ + rename "Operasi Timbang") selesai commit `a511c9e`.
5. User push sendiri (`git push` diblokir untuk agen): `! git push origin main`. Deploy prod: `git pull`, `php artisan optimize:clear`, `php artisan migrate --force`, `php artisan queue:restart`.

## Keputusan desain yang mengikat

- Akun per-RT lama (`users.id_rt` terisi) tidak berubah perilakunya; akun RT yang RT-nya terhapus (`id_rt` NULL, `rt_sekelurahan` 0) tetap 403 — TIDAK naik jadi akun kelurahan (itu sebab ada kolom penanda `rt_sekelurahan`).
- Superadmin di `/rt/verifikasi` tetap wajib `?rt=` (403 tanpa itu; `findOrFail` → 404 bila RT tak ada) — tes lama mengunci ini.
- Token 40 karakter `Str::random`, DB simpan `sha256`, plaintext hanya di flash sekali; satu tautan aktif per RT; 30 hari default; `/rt/akses/{token}` basi → 410 view `rt.akses-tidak-berlaku`.
- `pelaksana` wajib hanya bila `butuh_pelaksana` (mode `tautan` / `akun_kel`), diingat di sesi `rt_pelaksana`; akun per-RT opsional.
- Dasbor OT (`/admin/timbang-dashboard`) tidak disentuh. Data Capil rahasia: jangan `SELECT` isi baris; hanya hitungan.
- Gotcha alat: heredoc bash merusak backslash & kadang gagal parse → tulis skrip patch Python lewat Write tool ke scratchpad; blade `@section('x') teks @endsection` wajib berspasi; `view:clear` setelah ubah blade.

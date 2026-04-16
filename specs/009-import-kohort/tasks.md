# Tasks: Import Data Anak & Imunisasi dari Kohort Puskesmas

**Input**: Design documents dari `specs/009-import-kohort/`  
**Branch**: `009-import-kohort`  
**Tests**: Tidak diminta secara eksplisit — hanya smoke test manual di akhir

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Dapat dijalankan paralel (file berbeda, tidak bergantung task lain yang belum selesai)
- **[Story]**: User story mana yang dilayani task ini (US1–US4)

---

## Phase 1: Setup (Persiapan)

**Purpose**: Verifikasi prasyarat sebelum menulis kode apapun.

- [x] T001 Baca dan pahami pemetaan kolom lengkap di `specs/009-import-kohort/data-model.md` dan `research.md` sebelum mengubah kode apapun

---

## Phase 2: Foundational (Blokir Semua User Story)

**Purpose**: Migration DB dan infrastruktur yang HARUS selesai sebelum import class ditulis.

**⚠️ CRITICAL**: Semua user story bergantung pada skema DB yang benar di fase ini.

- [x] T002 Buat migration `add_kohort_fields_to_anak_table` di `database/migrations/` — tambah 12 kolom baru (nik_ayah, nik_ibu, tgl_lahir_ibu, no_hp, alamat, bbl, pbl, lk_lahir, imd, usia_kehamilan_lahir, penolong_lahir, komplikasi_persalinan) semua nullable
- [x] T003 Dalam migration yang sama (T002): ubah 8 kolom existing menjadi nullable (no_kk, nik_ortu, tempat_lahir, golda, anak, id_posyandu, id_puskesmas, catatan) di tabel `anak`
- [x] T004 Buat migration `add_kohort_fields_to_data_anak_table` di `database/migrations/` — tambah 18 kolom baru (hasil_lk, hasil_lila, zscore_bb_u, zscore_pb_u, zscore_bb_pb, pb_meter, imt, imt_u, rujuk, taburia, popm, makanan_pokok, mkn_kacang, mkn_susu, mkn_daging, mkn_telur, mkn_buah_vita, mkn_buah_lain) semua nullable
- [x] T005 Jalankan `php artisan migrate` — verifikasi kedua migration berhasil tanpa error
- [x] T006 Tambah kolom-kolom baru T002 dan T004 ke `$fillable` atau pastikan `$guarded = []` di `app/Models/Anak.php` dan `app/Models/DataAnak.php`

**Checkpoint**: `php artisan migrate:status` menampilkan kedua migration sebagai "Ran". Skema DB siap — implementasi import class dapat dimulai.

---

## Phase 3: User Story 1 — Import Identitas Anak (Priority: P1) 🎯 MVP

**Goal**: Import data identitas ~905 anak dari sheet "balita" ke tabel `anak` dengan upsert berdasarkan NIK.

**Independent Test**: Upload `Kohort puskesmas.xlsx` → daftar anak di `/admin/anak` bertambah dengan nama dan NIK dari file. Tidak ada error integrity constraint untuk baris berdata lengkap.

### Implementasi User Story 1

- [x] T007 Buat class `app/Imports/KohortImport.php` dengan skeleton: implements `ToCollection`, `WithStartRow`, `WithChunkReading`; konstruktor menerima `int $userId`; `startRow()` returns `4`; `chunkSize()` returns `200`; properti `$successCount`, `$failures[]`, `$rowOffset`
- [x] T008 Di `KohortImport.php`: tambah helper `parseDate` (handle numeric Excel date via `PhpOffice\PhpSpreadsheet\Shared\Date`, string Carbon, atau null)
- [x] T009 [P] Di `KohortImport.php`: tambah helper `parseBoolean`, `parseIntOrNull`, `parseDecimalOrNull`
- [x] T010 Di `KohortImport.php`: tambah method `detectColumns(Collection $headerRow)` yang membaca baris header (baris 4) dan mengembalikan: `vaccine_columns`, `month_tgl_cols`, `month_extra`, `alasan_col`
- [x] T011 Di `KohortImport.php`: implementasi `collection()` — US1: upsert `Anak` dari kolom A–W (index 0–22); kunci upsert = NIK (index 1); skip jika NIK DAN nama keduanya kosong; fallback NIK kosong → TEMP-{uniqid()}
- [x] T012 Di `KohortImport.php`: tangani RT lookup (kolom N, index 13) via cache `$rtCache`
- [x] T013 Di `KohortImport.php`: tangani lookup Kecamatan/Kelurahan via `resolveKecamatan`/`resolveKelurahan`
- [x] T014 [P] Buat `app/Jobs/ImportKohortJob.php` — `tries=1`, `timeout=600`; `handle()`, `failed()`, `finally`
- [x] T015 [P] Tambah method `importKohort()` di `AdminController` — validasi, store, ImportLog, dispatch
- [x] T016 [P] Tambah method `importKohortStatus()` di `AdminController` — JSON 5 log terakhir bertipe 'kohort'
- [x] T017 [P] Tambah 2 route di `routes/web.php`: POST `admin/import-kohort` dan GET `admin/import-kohort-status`
- [ ] T018 [US1] Verifikasi manual (smoke test): jalankan `php artisan queue:work --tries=1 --timeout=600`; upload `docs/Modul Import/Kohort puskesmas.xlsx`; cek tabel `anak` di DB

**Checkpoint**: US1 selesai — identitas anak terimport, daftar anak di admin bertambah.

---

## Phase 4: User Story 2 — Import Kunjungan Posyandu Bulanan (Priority: P2)

**Goal**: Untuk setiap baris anak, simpan record `DataAnak` per bulan yang memiliki tanggal posyandu terisi (12 bulan × ~905 anak = ~10.860 record potensial).

**Independent Test**: Setelah import, buka detail salah satu anak → halaman riwayat kunjungan menampilkan tabel BB, PB, LILA per bulan sesuai data Excel.

### Implementasi User Story 2

- [x] T019 [US2] Di `KohortImport::collection()`: implementasi loop 12 bulan — upsert DataAnak per (id_anak, tgl_kunjungan); offset +1..+16 dipetakan ke field DB
- [x] T020 [US2] Di loop bulan: tangani nilai formula Excel error (#DIV/0!, #N/A) via `parseDecimalOrNull`
- [x] T021 [US2] Di loop bulan: tangani kolom tambahan via `detectColumns()` + `month_extra` map
- [ ] T022 [US2] Verifikasi manual: setelah import, query `DB::table('data_anak')->where('id_anak', $someId)->orderBy('tgl_kunjungan')->get()` — pastikan jumlah record sesuai bulan terisi di Excel

**Checkpoint**: US2 selesai — riwayat kunjungan posyandu tersimpan per bulan per anak.

---

## Phase 5: User Story 3 — Import Data Imunisasi (Priority: P2)

**Goal**: Untuk setiap anak, buat record `Imunisasi` per vaksin yang tanggalnya terisi di kolom imunisasi (JU–KO).

**Independent Test**: Setelah import, buka detail salah satu anak → section imunisasi menampilkan vaksin dengan tanggal sesuai Excel; vaksin kosong tidak muncul (bukan error).

### Implementasi User Story 3

- [x] T023 Di `KohortImport.php`: tambah cache `$vaksinCache` di konstruktor — `JenisVaksin::pluck('id', 'kode')->toArray()`
- [x] T024 [US3] Di `KohortImport::collection()`: loop imunisasi via `vaccine_columns`; upsert Imunisasi (id_anak, id_jenis_vaksin); skip jika tanggal kosong
- [x] T025 [US3] Kolom "Alasan tidak imunisasi" disimpan ke `anak.catatan` via `$alasanCol`
- [ ] T026 [US3] Verifikasi manual: query `DB::table('imunisasi')->where('id_anak', $someId)->get()` — pastikan jumlah record sesuai kolom vaksin terisi

**Checkpoint**: US3 selesai — catatan imunisasi tersimpan per vaksin per anak.

---

## Phase 6: User Story 4 — UI Import & Laporan Hasil (Priority: P3)

**Goal**: Tambahkan tombol import di halaman daftar anak dengan modal upload, flash message, panel status polling, dan laporan error yang ramah pengguna.

**Independent Test**: Upload file kohort → modal tertutup → flash "sedang diproses" muncul → setelah selesai, panel status menampilkan jumlah berhasil + daftar error per baris.

### Implementasi User Story 4

- [x] T027 [P] [US4] Tombol "Import Kohort Excel" ditambah di `anak/index.blade.php` (hanya superadmin)
- [x] T028 [P] [US4] Modal Bootstrap 5 upload file, flash message area ditambah
- [x] T029 [US4] Panel status import + modal detail error ditambah (pola sama dengan epidemiologi)
- [x] T030 [US4] JavaScript polling `setInterval(refreshKohortStatus, 5000)` + toastr + stop polling saat idle
- [x] T031 [US4] `AdminController::anak()` mengirim `$kohortImportLogs` dan `$isSuperAdmin` ke view
- [x] T032 [US4] `KohortImport::simplifyError()` diimplementasi
- [ ] T033 [US4] Verifikasi manual: upload file → panel status menampilkan jumlah berhasil + daftar error

**Checkpoint**: US4 selesai — UI lengkap, laporan hasil import transparan dan ramah pengguna.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Pembersihan, konsistensi, dan verifikasi end-to-end.

- [ ] T034 [P] Jalankan `php artisan route:clear && php artisan config:clear && php artisan view:clear` — pastikan tidak ada cache lama
- [ ] T035 [P] Review `app/Imports/KohortImport.php` — verifikasi tidak ada duplikasi mapping, semua 20 vaksin terpetakan, semua 12 bulan diiterasi
- [ ] T036 Upload `docs/Modul Import/Kohort puskesmas.xlsx` end-to-end: login superadmin → klik Import Kohort Excel → pilih file → submit → tunggu selesai → verifikasi data muncul di daftar anak dan riwayat kunjungan
- [ ] T037 Upload file yang sama kedua kali → verifikasi jumlah baris `anak`, `data_anak`, `imunisasi` TIDAK bertambah (upsert berfungsi)
- [ ] T038 [P] Pastikan non-superadmin tidak bisa akses POST route `admin/anak/import-kohort` (coba langsung → 403)
- [ ] T039 [P] Update `docs/import-pd3i-status.md` atau buat `docs/import-kohort-status.md` dengan dokumentasi file utama, cara menjalankan, dan pemetaan kolom kritis

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: Tidak ada dependensi — langsung mulai
- **Phase 2 (Foundational)**: Bergantung pada Phase 1 — **BLOKIR semua US**
- **Phase 3 (US1)**: Bergantung pada Phase 2 — inti MVP
- **Phase 4 (US2)**: Bergantung pada Phase 3 (membutuhkan `id_anak` yang sudah tersimpan)
- **Phase 5 (US3)**: Bergantung pada Phase 3 (membutuhkan `id_anak`); paralel dengan Phase 4
- **Phase 6 (US4)**: Dapat dimulai setelah Phase 3 selesai (UI tidak tergantung US2/US3)
- **Phase 7 (Polish)**: Setelah semua US selesai

### User Story Dependencies

- **US1 (P1)**: Fondasi — harus selesai lebih dulu; T007–T018
- **US2 (P2)**: Bergantung US1 (butuh `id_anak`); T019–T022
- **US3 (P2)**: Bergantung US1 (butuh `id_anak`); dapat paralel dengan US2; T023–T026
- **US4 (P3)**: Bergantung US1 (butuh route & controller method); T027–T033

### Paralel dalam Phase 3 (US1)

T014 (Job), T015 (Controller importKohort), T016 (Controller status), T017 (Routes) tidak saling bergantung dan bisa dikerjakan paralel setelah T007–T013 selesai.

### Paralel dalam Phase 4 & 5

US2 (T019–T022) dan US3 (T023–T026) dapat dikerjakan bersamaan setelah US1 selesai karena menyentuh bagian yang berbeda di `collection()`.

---

## Parallel Example: Phase 3 (US1) — Batch Terakhir

```text
Setelah T007–T013 selesai, kerjakan sekaligus:

Batch A: T014 — ImportKohortJob.php
Batch B: T015 — AdminController::importKohort()
Batch C: T016 — AdminController::importKohortStatus()
Batch D: T017 — routes/web.php
```

---

## Implementation Strategy

### MVP First (User Story 1 Saja)

1. Selesaikan Phase 2 (Foundational — dua migration)
2. Selesaikan Phase 3 (US1 — import identitas anak)
3. **STOP & VALIDASI**: Upload kohort → cek DB `anak` bertambah
4. Lanjut ke Phase 4–6 jika MVP valid

### Incremental Delivery

1. Phase 2 + Phase 3 → Identitas anak terimport
2. Phase 4 → Kunjungan posyandu terimport
3. Phase 5 → Imunisasi terimport
4. Phase 6 → UI lengkap + laporan error
5. Phase 7 → Verifikasi end-to-end + upsert test

---

## Notes

- Semua perubahan kode terpusat di **`app/Imports/KohortImport.php`** — satu file utama
- Pola arsitektur identik dengan `Pd3iImport.php` — rujuk file tersebut untuk helper, cache, dan error handling
- Gunakan `docs/Modul Import/Kohort puskesmas.xlsx` sebagai file uji nyata
- **Header detection** (T010) kritis — mapping kolom bulan dan imunisasi dideteksi dari nama header baris 4, BUKAN dari index tetap
- Commit setelah setiap checkpoint (US1, US2+US3, US4)
- Restart queue worker setiap kali `KohortImport.php` atau `ImportKohortJob.php` diubah

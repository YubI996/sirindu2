# Import Data Kesmas — Desain

Tanggal: 2026-10-15 · Status: disetujui di percakapan (15 Okt 2026), menunggu reviu spec tertulis

## 1. Ringkasan

Tipe import baru **"Data Kesmas"** di halaman Import CSV. Berkas Excel mengisi field Kesmas
(per anak di `anak`, per kunjungan di `data_anak` — spec `2026-09-15-data-kesmas-design.md`) untuk
anak yang **sudah ada**, ditautkan lewat nama, NIK, tanggal lahir, jenis kelamin, dan nama orang tua.

Latar: klien Kesmas meminta data import diprioritaskan **sebagai urutan pekerjaan**. Pengisian field
Kesmas otomatis dari catatan program (imunisasi, gizi/OT, MBG, makanan, dll.) menyusul nanti dan akan
penuh otomatis — import ini jembatan sementara, **bukan** mekanisme "sumber mana yang menang". Tidak
ada logika prioritas antar-sumber di desain ini.

Keputusan yang mengikat:

1. **Hanya mengisi anak yang sudah ada.** Anak yang tak ketemu/ambigu → baris ditolak. Tidak ada anak
   baru, NIK di DB tidak pernah diubah.
2. **Field Kesmas mengikuti berkas, ukuran yang sudah tercatat tidak.** Field Kesmas (per anak dan per
   kunjungan) memakai aturan "isi yang diberikan": sel berisi menimpa, sel kosong membiarkan. Ukuran
   (BB/TB/posisi/LK/LILA/z-score) kunjungan yang sudah ada **tidak disentuh**; bentrok → peringatan.
3. **Satu baris = satu anak pada satu kunjungan.** Baris tanpa `tgl_kunjungan` sah dan hanya mengisi
   field per anak.
4. **Kunjungan baru wajib membawa ukuran** (BB, TB, posisi). Aturan satuan gram (< 2 bulan) berlaku.
5. **Sel tak sah hanya membatalkan sel itu**, bukan barisnya — kecuali kegagalan identitas/kunjungan (§5).
6. `anak.sasaran_balita_kesmas` **tidak disentuh** import ini.
7. Format & tampilan pesan, format tanggal, riwayat, reimport, dan akses sama dengan tipe import lain.

## 2. Berkas & template

**Format berkas:** Excel (`.xlsx`/`.xls`), maksimal 20 MB. CSV tidak diterima untuk tipe ini.

**Hanya sheet pertama ("Data") yang dibaca** (`WithMultipleSheets`). Maatwebsite tanpa itu memproses
SEMUA sheet (gotcha import Capil, Juni 2026) — sheet Petunjuk akan terbaca sebagai baris data.

**Template dibuat saat diunduh** (`App\Exports\TemplateKesmas`), bukan file statis di
`public/templates`, supaya pilihan dropdown selalu sama dengan `config/kesmas.php`. Dua sheet:

- **Data** — baris 1 = nama kolom kunci (tabel §2.1); data validation (dropdown) untuk kolom Ya/Tidak,
  `jk`, `posisi`, dan kolom pilihan; tanpa baris contoh (mencegah contoh ikut terimpor).
- **Petunjuk** — per kolom: label (= label form, dari `KesmasRules::atribut()`), wajib/opsional,
  satuan, pilihan sah. Termasuk penjelasan satuan BB dan baris tanpa tanggal kunjungan.

Pembacaan header: baris pertama, nama kolom dinormalisasi (huruf kecil, trim). Kolom identitas wajib
(`nama_anak`, `jk`, `tgl_lahir`, `nama_ortu`) tidak ada di header → import gagal dengan `[ERROR]`
menyebut kolom yang hilang. Kolom tak dikenal → satu `[PERINGATAN]` per kolom, kolomnya diabaikan.

### 2.1 Kolom

| Kelompok | Kolom | Ke | Aturan |
|---|---|---|---|
| Identitas | `nama_anak` | — | wajib, minimal satu huruf (`NamaAnak`) |
| | `jk` | — | wajib, L/P |
| | `tgl_lahir` | — | wajib |
| | `nama_ortu` | — | wajib (nama ibu/ortu, untuk penautan) |
| | `nik_anak` | — | opsional, dianjurkan |
| Kunjungan | `tgl_kunjungan` | `data_anak.tgl_kunjungan` | opsional (§4) |
| Ukur | `bb` | `bb` | wajib untuk kunjungan baru; satuan §3 |
| | `tb_cm` | `tb` | wajib untuk kunjungan baru |
| | `posisi` | `posisi` | wajib untuk kunjungan baru; Berdiri/Terlentang (`normalisasi_posisi`) |
| | `lk_cm`, `lila_cm` | `lk`, `lla` | opsional |
| | `zscore_bb_u`, `zscore_pb_u`, `zscore_bb_pb` | sama | opsional |
| Kesmas per kunjungan | kunci `KesmasRules::kunjungan()` (9 layanan Ya/Tidak dari `config('kesmas.layanan')`, `tgl_penanda_ckg`, `pemeriksaan_gigi`, `rujukan`, `mt_pangan_lokal`, `catatan_pengukuran`, `pemeriksaan_lainnya`, `pola_makan`, `pola_asuh`, `intervensi`) | sama | opsional |
| Kesmas per anak | 24 kunci `KesmasRules::anak()` (`no_id_epus` … `komplikasi_neonatal`) | `anak.*` | opsional |

Daftar kolom Kesmas **diturunkan dari `array_keys(KesmasRules::anak())` / `kunjungan()`**, bukan
ditulis ulang — field Kesmas baru otomatis ikut template dan import.

## 3. Nilai sel (`App\Support\NilaiSelKesmas`)

Unit murni (tanpa DB/`config()`/`now()`; opsi diteruskan pemanggil) yang mengubah sel mentah menjadi
nilai kolom:

- **Kosong** (`null`, `''`, spasi) → "tidak diberikan", kolom tidak ditulis.
- **Ya/Tidak**: `Ya/Y/1/True` → 1, `Tidak/T/0/False` → 0, tanpa beda huruf besar-kecil. Lainnya → tak sah.
- **Pilihan berlabel tanpa kunci** (`status_tk_paud`, `jenis_persalinan`, `penolong_lahir`,
  `pemeriksaan_gigi`, `rujukan`): dicocokkan tanpa beda huruf, disimpan dalam ejaan config.
- **Pilihan berkunci** (`skrining_*`, `pemeriksaan_hepatitis_b`): menerima kunci (`tidak_normal`) atau
  label (`Tidak normal`), disimpan sebagai kunci.
- **Tanggal** (`tgl_hbig`, `tgl_penanda_ckg`): lewat `MembacaTanggalBerkas` dengan format yang terkunci.
- **Angka**: koma desimal diterima (`3,25`).

Setelah dikonversi, nilai divalidasi dengan aturan `KesmasRules::anak()` / `kunjungan()` per kolom
(sumber yang sama dengan form; `tgl_hbig` membutuhkan `tgl_lahir` anak dari DB). Gagal konversi atau
validasi → kolom itu dilewati + `[PERINGATAN] Baris N (nama): <Label> — nilai '<x>' <pesan>; kolom
dilewati.` Kolom lain di baris yang sama tetap ditulis.

**Satuan BB** (keputusan §1.4): `SatuanBeratBadan::untuk(anak.tgl_lahir dari DB, tgl_kunjungan)`.
Gram bila kunjungan < tgl lahir + 2 bulan (`addMonthsNoOverflow`; tepat di batas = kg), selain itu kg.
Rentang gram 300–8.000 dan kg 1–150 tidak beririsan, jadi salah satuan pasti ditolak. Yang disimpan
selalu kg (`keKg`). BB di luar rentang pada kunjungan baru → kunjungan tak bisa dibuat (§5).
Untuk kunjungan yang sudah ada, BB berkas hanya dibandingkan (`SatuanBeratBadan::sama`) dengan nilai
tersimpan untuk peringatan.

## 4. Penautan anak (`App\Services\PenautanAnakKesmas`)

Hanya mencari, tidak pernah membuat atau mengubah identitas anak.

1. Identitas wajib (`nama_anak`, `jk`, `tgl_lahir`, `nama_ortu`) kosong/tak sah → `[ERROR]`, baris dilewati.
2. `nik_anak` valid (15–16 digit) ditemukan di `anak.nik` **dan** (tgl lahir sama **atau** nama mirip
   ≥ 87%) → anak itu. Ditemukan tapi keduanya tak cocok → `[PERINGATAN]` "NIK <x> tercatat untuk anak
   lain (<nama>, lahir <tgl>) — dicocokkan lewat nama", lanjut ke langkah 3. (Dasar: 72% anak ganda di
   prod ber-NIK ibu/salah ketik.)
3. `PenautanAnakImport::tautkan(nama, tgl_lahir, jk, null, nik, nama_ortu)` — nama ≥ 87% + tgl lahir +
   jk, nama ortu dan aturan kembar menyingkirkan orang lain:
   - SAMA → anak itu; `[INFO]` "dicocokkan lewat nama + tgl lahir (NIK di DB <y>)". Saran penggantian NIK
     dari `tautkan()` **diabaikan** (NIK tidak diubah), hanya dilaporkan `[INFO]`.
   - BARU → `[ERROR]` "anak tidak ditemukan — tambahkan dulu lewat Tambah Anak / Import Anak".
   - AMBIGU → `[ERROR]` "ditemukan N anak yang cocok — isi nik_anak".

Anak yang sudah ditemukan disimpan dalam peta per import (kunci identitas baris → id anak) supaya
baris kunjungan berikutnya dari anak yang sama tidak mencari ulang.

## 5. Menulis

Per baris, satu transaksi:

1. Tautkan anak (§4). Gagal → baris dilewati.
2. **Ada `tgl_kunjungan`:**
   - Tanggal tak terbaca → `[ERROR]`, seluruh baris dilewati (termasuk field per anak — baris yang
     tanggalnya rusak tak dapat dipercaya).
   - Kunjungan `(id_anak, tgl_kunjungan)` **belum ada** → wajib `bb` sah (satuan §3), `tb_cm`, `posisi`.
     Kurang/tak sah → `[ERROR]` menyebut kolom, **seluruh baris batal**. Sah → buat `DataAnak` dengan
     ukur + `bln = usia_bulan()` + z-score dari berkas (atau NULL) + field Kesmas kunjungan yang
     diberikan + `id_user`.
   - Kunjungan **sudah ada** → tulis field Kesmas kunjungan yang diberikan saja. Ukuran tak disentuh;
     tiap ukuran berkas yang berbeda dari tersimpan → `[PERINGATAN]` "BB di berkas 10,5 kg, tersimpan
     10,2 kg — tidak diubah".
3. **Tanpa `tgl_kunjungan`:** kolom ukur/Kesmas kunjungan yang terisi → satu `[PERINGATAN]` "kolom
   kunjungan diabaikan karena tgl_kunjungan kosong".
4. **Field Kesmas per anak** yang diberikan → `anak`. Anak muncul di beberapa baris: baris lebih bawah
   menang; nilai berbeda dari baris sebelumnya dalam berkas yang sama → `[PERINGATAN]`.
5. Ditulis lewat Eloquent (`save()`), jadi observer `DataAnak`/`Anak` (refresh prioritas gizi) tetap
   berjalan. `sasaran_balita_kesmas` tidak ada di daftar kolom import.

Baris tanpa isi sama sekali dilewati diam-diam. Pengecualian tak terduga → `[ERROR]` lewat
`ImportError::message()` (menyebut kolom), baris dilewati, import lanjut.

**Hitungan ringkasan:** berhasil = baris yang menulis sesuatu; gagal = baris `[ERROR]`.

## 6. Pesan & riwayat

Sama dengan tipe import lain: `[ERROR]` / `[PERINGATAN]` / `[INFO] Baris N (nama): …`, nomor baris =
baris Excel, diurutkan ERROR → PERINGATAN → INFO oleh `ImportLog` (commit 17b7a2b), ringkasan paling
atas. Riwayat, polling status, reimport, dan hapus log memakai endpoint `importCsv` yang ada.

**Format tanggal:** pilihan `auto` / `dmy` / `mdy` (`TanggalBerkas::ATURAN`), tersimpan di
`import_logs.format_tanggal` dan dipakai saat reimport. `auto` dikunci dari potongan pertama
(`kunciFormatTanggal()`); berkas yang semua tanggalnya ambigu menghentikan import
(`FormatTanggalAmbigu`). Sel tanggal Excel tak pernah ambigu.

## 7. Komponen

| Unit | Tugas |
|---|---|
| `App\Imports\KesmasImport` | `WithMultipleSheets` → sheet indeks 0 (`ToCollection`, `WithChunkReading` 200); header, loop baris, ringkasan; trait `MembacaTanggalBerkas` |
| `App\Services\PenautanAnakKesmas` | §4; bisa dites tanpa berkas |
| `App\Support\NilaiSelKesmas` | §3; unit murni |
| `App\Jobs\ImportKesmasJob` | pola `ImportPengukuranJob` |
| `App\Exports\TemplateKesmas` | template dua sheet + dropdown (PhpSpreadsheet) |
| `ImportCsvController` | tipe `kesmas` di `VALID_TYPES`, `uploadKesmas` (mimes `xlsx,xls`), cabang Job, `downloadTemplate('kesmas')` memanggil `TemplateKesmas` |
| `routes/web.php` | `POST import-csv/kesmas` → `admin.importCsv.kesmas` |
| `admin/import/index.blade.php` | tab/kartu "Data Kesmas" dengan unggah, pilihan format tanggal, tautan template, ringkasan aturan |

Tidak ada migrasi: `import_logs.type` adalah `string(30)`.

**Akses:** hanya superadmin (sama dengan seluruh `ImportCsvController`). Peran Kesmas tetap baca-saja.

## 8. Pengujian

PHPUnit + `RefreshDatabase`; berkas uji `.xlsx` dibangun di tes (bukan fixture statis).

- **Penautan:** lewat NIK; NIK milik anak lain → jatuh ke nama + PERINGATAN; kembar tidak tertukar;
  nama ortu berbeda menyingkirkan kandidat; tidak ketemu / ambigu → ERROR; NIK di DB tak berubah.
- **Tulis:** sel kosong tidak menimpa; sel berisi menimpa; kunjungan yang ada tidak berubah ukurannya +
  PERINGATAN; kunjungan baru tanpa BB → seluruh baris batal (field anak juga tidak tertulis); sel tak
  sah hanya sel itu yang dilewati; `sasaran_balita_kesmas` tetap; baris bawah menang + PERINGATAN.
- **Nilai:** unit test `NilaiSelKesmas` — Ya/Tidak, pilihan beda huruf, label → kunci, angka koma.
- **Gram:** bayi < 2 bln `bb=3250` → 3,25 kg; `bb=3,25` pada bayi < 2 bln → ERROR; tepat 2 bulan = kg.
- **Berkas:** sheet Petunjuk tak terbaca sebagai data; baris tanpa tanggal hanya mengisi field anak +
  PERINGATAN bila kolom kunjungan terisi; format tanggal ambigu menghentikan import; kolom wajib hilang
  di header → gagal; template unduhan bisa diisi dan diimpor ulang (round-trip).
- **Pesan:** urutan ERROR → PERINGATAN → INFO dan nomor baris = baris Excel.
- **Akses:** non-superadmin 403 untuk upload dan template.

## 9. Di luar cakupan

- Mengisi field Kesmas otomatis dari modul imunisasi/OT/MBG (menyusul, penuh otomatis).
- Membuat anak baru dari berkas Kesmas.
- Kunjungan tanpa ukur (sub-proyek terpisah).
- Pratinjau sebelum terapkan.

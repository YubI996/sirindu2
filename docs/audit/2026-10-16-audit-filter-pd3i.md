# Laporan Audit Aplikasi -- SIRINDU — filter Faskes Pelapor & rentang Tanggal Laporan Dasbor PD3I (dd44bea..d6a4395)

**Tanggal audit**: 2026-10-16
**Fase selesai**: static, dinamis
**Status gate**: ✅ Gate lolos -- tidak ada temuan Kritis.
**Catatan Fase 2**: Dijalankan setelah perbaikan, di app lokal (php artisan serve 127.0.0.1:8123, DB dev tanpa kasus surveilans) lewat Playwright: rentang sah terkirim & periode tampil, tanggal tahun lain ditandai + pesan teks + tidak dikirim, ganti Tahun mengosongkan tanggal, debounce = satu batch fetch. Claude in Chrome tidak tersambung. Tidak diuji: opsi faskes berisi data (DB dev kosong), reflow 320px, screen reader sungguhan.

## Ringkasan Eksekutif

Total temuan: **30** dari 4 track.

| Track | 🔴 Kritis | 🟠 Tinggi | 🟡 Sedang | 🔵 Rendah | ⚪ Info | Total |
|---|---|---|---|---|---|---|
| Aksesibilitas | 0 | 1 | 4 | 2 | 1 | 8 |
| Keamanan | 0 | 0 | 1 | 4 | 2 | 7 |
| Persyaratan (Requirements Conformance) | 0 | 0 | 1 | 2 | 2 | 5 |
| UI/UX | 0 | 0 | 4 | 5 | 1 | 10 |
| **Total** | **0** | **1** | **10** | **13** | **6** | **30** |

## Detail Temuan

## 🟠 Severity: Tinggi

### [A11Y-001] Tanggal lapor tak sah hanya ditandai border merah, tanpa pesan teks
- **Track**: Aksesibilitas (static)
- **Lokasi**: `resources/views/admin/epidemiologi/pd3i-dashboard.blade.php:984-990 (input di :416-425)`
- **Deskripsi**: nilaiTanggal() menambah class is-invalid dan aria-invalid=true, lalu DIAM-DIAM tidak mengirim tanggal itu — dasbor menampilkan angka setahun penuh. Satu-satunya penanda adalah border merah + ikon background Bootstrap (bukan teks, tak terbaca screen reader). Tidak ada .invalid-feedback / aria-describedby / aria-live, jadi pengguna buta warna atau screen reader tidak tahu kenapa filternya tak berlaku, dan aria-invalid baru terumumkan bila fokus kembali ke input. Komentar kode sendiri menyebut tujuan penandaan ini adalah mencegah layar 'terlihat tersaring padahal angkanya setahun penuh' — tujuan itu hanya tercapai lewat warna, bertentangan dengan aturan proyek 'status tidak boleh hanya warna'.
- **Rekomendasi**: Tambah <div id="err-tgl-dari" class="invalid-feedback">Tanggal harus di dalam tahun {Tahun}; filter tanggal tidak diterapkan.</div> (dan untuk s/d) setelah tiap input, hubungkan via aria-describedby, perbarui teksnya di nilaiTanggal() bersama tahun terpilih. Pertimbangkan satu wilayah role=status (aria-live=polite) di filter bar yang mengumumkan 'Tanggal lapor diabaikan: di luar tahun 2026'.
- **Referensi**: WCAG 3.3.1, 1.4.1, 3.3.3, 4.1.3


## 🟡 Severity: Sedang

### [A11Y-002] Tak ada instruksi bahwa rentang tanggal harus di dalam Tahun
- **Track**: Aksesibilitas (static)
- **Lokasi**: `resources/views/admin/epidemiologi/pd3i-dashboard.blade.php:416-425, 1716-1721`
- **Deskripsi**: Batas min/max input date di-set ke tahun terpilih, tetapi tidak ada teks bantuan yang terlihat maupun aria-describedby yang menyatakan aturan itu. Mengganti Tahun juga mengosongkan kedua tanggal tanpa pemberitahuan. Pengguna baru tahu aturannya setelah gagal (border merah, lihat A11Y-001).
- **Rekomendasi**: Tambah teks bantuan singkat (mis. small 'Di dalam tahun terpilih') yang terhubung via aria-describedby ke kedua input; bila tanggal dikosongkan karena Tahun berganti, umumkan lewat wilayah role=status yang sama.
- **Referensi**: WCAG 3.3.2

### [A11Y-003] (warisan) role=listbox berisi checkbox, bukan role=option
- **Track**: Aksesibilitas (static)
- **Lokasi**: `resources/views/admin/epidemiologi/pd3i-dashboard.blade.php:403,407-413`
- **Deskripsi**: Dropdown Faskes Pelapor mewarisi pola .ms-dropdown: .ms-menu ber-role="listbox" aria-multiselectable, tetapi anaknya <label><input type=checkbox>, bukan elemen role=option dengan aria-selected. Struktur ARIA tidak sah (listbox wajib berisi option); sebagian screen reader mengumumkan 'listbox, 0 item' atau menelan semantik checkbox. Daftar faskes pelapor (teks bebas instansi_pelapor) bisa panjang sehingga dampaknya lebih besar daripada dropdown lain.
- **Rekomendasi**: Paling sederhana: buang role=listbox/aria-multiselectable dan aria-haspopup=listbox (biarkan grup checkbox native, mis. role=group + aria-labelledby=lbl-faskes-pelapor) — pola disclosure. Perbaiki sekaligus di keempat .ms-dropdown.
- **Referensi**: WCAG 4.1.2, 1.3.1

### [A11Y-004] (warisan) Nilai terpilih tidak masuk accessible name tombol dropdown
- **Track**: Aksesibilitas (static)
- **Lokasi**: `resources/views/admin/epidemiologi/pd3i-dashboard.blade.php:403-404, 1007-1018`
- **Deskripsi**: Tombol .ms-toggle memakai aria-labelledby="lbl-faskes-pelapor" saja, sehingga accessible name = 'Faskes Pelapor' dan teks yang terlihat ('Semua Faskes' / nama faskes / 'N dipilih' dari updateMsLabel) tidak diumumkan. Pengguna screen reader tidak bisa tahu filter faskes sedang aktif atau tidak tanpa membuka menu dan memeriksa tiap checkbox.
- **Rekomendasi**: Beri id pada .ms-label dan ubah ke aria-labelledby="lbl-faskes-pelapor ms-label-faskes" (pola sama untuk dropdown lain), sehingga terbaca 'Faskes Pelapor, Semua Faskes'.
- **Referensi**: WCAG 4.1.2, 2.5.3

### [A11Y-005] (warisan) Dropdown tak bisa ditutup dengan Esc dan tetap terbuka saat fokus keluar
- **Track**: Aksesibilitas (static)
- **Lokasi**: `resources/views/admin/epidemiologi/pd3i-dashboard.blade.php:1020-1046`
- **Deskripsi**: initMultiselects() hanya menangani click: tidak ada keydown Escape, dan menu tidak tertutup saat fokus Tab keluar dari .ms-dropdown (penutupan hanya lewat click di dokumen). Pengguna keyboard yang men-Tab melewati daftar faskes yang panjang meninggalkan menu absolut (z-index 1050, max-height 280px) terbuka menutupi kontrol Tgl lapor di sebelah/di bawahnya. Bisa dioperasikan (Enter/Space membuka, checkbox native), jadi bukan pemblokir total.
- **Rekomendasi**: Tambah handler keydown Escape (tutup + kembalikan fokus ke toggle) dan focusout pada .ms-dropdown yang menutup menu bila relatedTarget di luar dropdown. Pola Esc sudah dipakai di public/js/dasbor-a11y.js.
- **Referensi**: WCAG 2.1.1, 2.4.3 (praktik ARIA APG disclosure)

### [SEC-001] Formula injection (CSV/Excel injection) di ekspor Excel dasbor PD3I — instansi_pelapor & kolom teks lain ditulis sebagai rumus
- **Track**: Keamanan (static)
- **Lokasi**: `app/Exports/SurveillanceExport.php:213-240 (map), app/Exports/SurveillanceExport.php:23 (tanpa WithCustomValueBinder), config/excel.php:211`
- **Deskripsi**: SurveillanceExport (FromQuery + WithMapping) memakai value binder bawaan Maatwebsite\Excel\DefaultValueBinder (config/excel.php:211), yang meneruskan ke PhpSpreadsheet DefaultValueBinder: string berawalan '=' dengan panjang >1 diberi TYPE_FORMULA (vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Cell/DefaultValueBinder.php:61). map() mengirim teks bebas apa adanya: instansi_pelapor (SurveillanceExport.php:239), nama_lengkap, alamat, dst. instansi_pelapor hanya divalidasi 'nullable|string|max:255' (StoreSurveillanceCaseRequest.php:58, UpdateSurveillanceCaseRequest.php:59) dan juga masuk lewat Pd3iImport/HasilLabImport. Jalur lintas-pengguna: petugas faskes (surveilans_puskesmas/rs) menginput teks berawalan '=' → superadmin Dinkes yang ekspor se-kota membuka berkas dan rumusnya dievaluasi (mis. HYPERLINK untuk eksfiltrasi isi sel, atau DDE pada Excel lama). Fitur baru menambah jalur untukDasborPd3i() dan membuat instansi_pelapor menjadi nilai filter yang menonjol; kelemahannya sendiri sudah ada sebelum diff. Proyek ini sudah mengenali pola yang sama di KesmasExport/AnakExport (StringCell eksplisit, lihat CLAUDE.md) tetapi SurveillanceExport belum.
- **Rekomendasi**: Implementasikan WithCustomValueBinder pada SurveillanceExport dengan binder yang memaksa TYPE_STRING untuk kolom teks (atau StringValueBinder untuk kolom non-angka), atau prefiks apostrof/tab untuk nilai berawalan = + - @ \t \r. Verifikasi manual: baca ulang map()/binder dan pastikan setiap kolom teks bebas (instansi_pelapor, nama_lengkap, alamat_lengkap, catatan) melewati jalur string; tambahkan tes unit yang memeriksa getDataType() sel instansi_pelapor = 's' untuk nilai '=1+1' (tanpa membuka berkas di Excel).
- **Referensi**: OWASP A03:2021 (Injection) — CSV/Formula Injection
- **Kepastian**: Terverifikasi manual (binder vendor dan map() dibaca)

### [REQ-001] Opsi Faskes Pelapor berspasi di depan tidak bisa dipakai menyaring: opsi ditampilkan mentah, nilai filter di-trim
- **Track**: Persyaratan (Requirements Conformance) (static)
- **Lokasi**: `app/Http/Controllers/Pd3iDashboardController.php:47-51; app/Support/FilterPd3i.php:97-98,156-158`
- **Deskripsi**: index() mengambil distinct instansi_pelapor apa adanya (hanya membuang NULL dan ''), sementara FilterPd3i::multi() men-trim tiap nilai (dan middleware TrimStrings Laravel juga men-trim input request) sebelum whereIn('surveillance_cases.instansi_pelapor', ...). Pd3iImport menyimpan $row[4] tanpa trim (app/Imports/Pd3iImport.php:416). Untuk nilai berspasi di depan (mis. ' PKM Loktuan'), opsinya muncul di dropdown tetapi memilihnya menghasilkan 0 kasus, berlawanan dengan R2 (opsi = nilai di data, filter whereIn nilai itu). Spasi di belakang aman karena kolasi utf8mb4_unicode_ci bersifat PAD SPACE (config/database.php:56). Nilai yang hanya berisi spasi juga lolos sebagai opsi kosong. Memori proyek sudah mencatat 'cek spasi instansi_pelapor di prod saat deploy', jadi risikonya disadari tetapi kodenya belum menanganinya. Kepastian: Inferensi (tergantung isi data prod).
- **Rekomendasi**: Samakan kedua sisi: ambil opsi dengan DISTINCT TRIM(instansi_pelapor) dan WHERE TRIM(instansi_pelapor) <> '', lalu saring dengan whereIn(DB::raw('TRIM(surveillance_cases.instansi_pelapor)'), ...), atau bersihkan datanya sekali lewat migrasi (UPDATE ... SET instansi_pelapor = TRIM(instansi_pelapor)) dan trim di Pd3iImport. Tambahkan tes dengan instansi_pelapor berspasi di depan.
- **Referensi**: R2 - spec docs/superpowers/specs/2026-10-15-filter-pd3i-faskes-tanggal-design.md:24-25
- **Kepastian**: Inferensi

### [UX-001] Input tanggal ditandai is-invalid tanpa pesan penjelasan
- **Track**: UI/UX (static)
- **Lokasi**: `resources/views/admin/epidemiologi/pd3i-dashboard.blade.php:984-990 (input di :416-425)`
- **Deskripsi**: nilaiTanggal() hanya menambah kelas .is-invalid dan aria-invalid saat tanggal di luar Tahun. Tidak ada elemen .invalid-feedback, teks bantuan, atau aria-describedby, jadi pengguna hanya melihat bingkai merah. Ia tidak tahu kenapa tanggalnya salah (harus di dalam Tahun terpilih) dan bahwa tanggal itu diabaikan sehingga angka yang tampil adalah data setahun penuh.
- **Rekomendasi**: Tambahkan <div class="invalid-feedback" id="...-err">Tanggal harus di dalam tahun {tahun}; filter tanggal ini tidak dipakai.</div> di bawah tiap input dan hubungkan lewat aria-describedby. Isi teksnya dari JS memakai tahun yang terpilih.
- **Referensi**: heuristik Nielsen #9 (Help users recognize, diagnose, and recover from errors)

### [UX-002] Mengetik tahun di input date memicu fetch berulang dan tanda merah sesaat
- **Track**: UI/UX (static)
- **Lokasi**: `resources/views/admin/epidemiologi/pd3i-dashboard.blade.php:1724`
- **Deskripsi**: Listener 'change' dipasang di input date (TGL_IDS -> fetchAllTabs). Di Chrome/Edge, saat segmen tahun diketik digit demi digit, nilainya sudah menjadi tanggal lengkap yang sah tetapi di luar min (mis. 0002-03-01). Akibatnya change terpicu di tiap ketukan: input sempat merah (is-invalid) dan fetchAllTabs (4 endpoint) berjalan berulang kali sebelum pengguna selesai mengetik. Layar berkedip, skeleton muncul lagi, dan respons lama bisa tiba setelah respons baru.
- **Rekomendasi**: Debounce fetch tanggal (sekitar 400 ms) atau picu fetch pada blur/Enter. Jangan tandai invalid selama input masih fokus; validasi saat blur.
- **Referensi**: heuristik Nielsen #1 (Visibility of system status), #5 (Error prevention)

### [UX-003] Opsi Faskes Pelapor dari teks bebas tanpa normalisasi: opsi ganda, dan sebagian memberi hasil kosong
- **Track**: UI/UX (static)
- **Lokasi**: `app/Http/Controllers/Pd3iDashboardController.php:47-51; app/Support/FilterPd3i.php:151-156`
- **Deskripsi**: Daftar opsi diambil dari distinct instansi_pelapor (teks bebas) tanpa TRIM atau penyatuan huruf besar-kecil. Varian seperti 'RSUD Taman Husada' dan 'RSUD Taman Husada ' muncul sebagai dua opsi yang tampak identik. Di sisi lain, FilterPd3i::multi() men-trim nilai yang dikirim, sehingga memilih varian berspasi tidak pernah cocok dengan barisnya sendiri. Dasbor lalu kosong tanpa penjelasan. Daftar juga tidak disaring per Tahun, jadi faskes yang tak punya laporan di tahun terpilih tetap muncul dan memilihnya menghasilkan dasbor nol.
- **Rekomendasi**: Bangun opsi dengan TRIM(instansi_pelapor) yang sudah disatukan, dan cocokkan di query dengan TRIM juga (atau bersihkan datanya). Pertimbangkan menampilkan jumlah kasus per opsi untuk tahun terpilih, atau menandai faskes yang tak punya data tahun itu.
- **Referensi**: heuristik Nielsen #4 (Consistency and standards), #5 (Error prevention)

### [UX-004] Tidak ada ringkasan periode/filter aktif di layar, dan tidak ada tombol reset
- **Track**: UI/UX (static)
- **Lokasi**: `resources/views/admin/epidemiologi/pd3i-dashboard.blade.php:332-427`
- **Deskripsi**: Dasbor kini punya 8 filter, termasuk rentang tanggal yang mudah luput karena berada di baris kedua bilah filter. Tidak ada chip, teks ringkasan, atau judul berisi periode aktif (mis. '1 Mar - 30 Jun 2026') di area konten. Karena itu angka kartu Kinerja/Demografi bisa disalahbaca sebagai data setahun. Satu-satunya penanda adalah .catatan-rentang, yang hanya ada di tab Tren. Tidak ada tombol 'Reset filter' juga: mengosongkan rentang dan 3-4 multiselect harus dilakukan satu per satu, dan tiap langkah memicu fetch.
- **Rekomendasi**: Tambahkan baris status di bawah filter-card yang memakai label yang sama dengan FilterPd3i::labelPeriode() (mis. 'Menampilkan: 1 Mar 2026 - 30 Jun 2026, 2 faskes'), plus tombol 'Reset filter' yang mengosongkan semua filter lalu memanggil fetchAllTabs sekali.
- **Referensi**: heuristik Nielsen #1 (Visibility of system status), #3 (User control and freedom), #6 (Recognition rather than recall)


## 🔵 Severity: Rendah

### [A11Y-006] Perubahan hasil setelah filter baru tidak diumumkan
- **Track**: Aksesibilitas (static)
- **Lokasi**: `resources/views/admin/epidemiologi/pd3i-dashboard.blade.php:1716-1722, 1036-1039`
- **Deskripsi**: Mengubah Faskes Pelapor atau Tgl lapor langsung memicu fetchAllTabs() tanpa wilayah aria-live/role=status. Pengguna screen reader tak mendapat konfirmasi bahwa angka sudah diperbarui (atau bahwa tanggal diabaikan, A11Y-001). Masalah ini sudah ada pada filter lama; kontrol baru menambah pemicunya.
- **Rekomendasi**: Satu <div role="status" class="sr-only"> di filter bar yang diisi setelah fetch selesai, mis. 'Data diperbarui: 12 kasus, Faskes: 2 dipilih, 1–31 Mar 2026'.
- **Referensi**: WCAG 4.1.3

### [A11Y-007] Catatan rentang tidak dikaitkan ke grafiknya dan label memakai singkatan
- **Track**: Aksesibilitas (static)
- **Lokasi**: `resources/views/admin/epidemiologi/pd3i-dashboard.blade.php:712, 723, 416-425`
- **Deskripsi**: <p class="catatan-rentang"> sudah terlihat (bukan tooltip) dan muncul saat rentang aktif — sesuai aturan proyek. Namun tidak dihubungkan ke <canvas> (aria-describedby), sehingga pembaca layar yang menelusuri grafik tak mendapat konteks bahwa grafik tidak mengikuti rentang. Label 'Tgl lapor s/d' dibacakan 's garis miring d' oleh screen reader; 'Tgl' singkatan.
- **Rekomendasi**: Beri id pada kedua catatan dan aria-describedby di canvas terkait; ganti label jadi 'Tanggal lapor dari' / 'Tanggal lapor sampai' (atau tambahkan aria-label lengkap tanpa membuang teks yang terlihat).
- **Referensi**: WCAG 1.3.1, 2.4.6; Praktik UX literasi digital

### [SEC-002] API agregat dasbor PD3I tidak memakai visibleTo; filter baru (faskes pelapor + rentang tanggal sampai 1 hari) mempersempit agregat hingga sel bernilai 1
- **Track**: Keamanan (static)
- **Lokasi**: `app/Repositories/Admin/Epidemiologi/SurveillanceRepository.php:580-585 (pd3iBaseQuery), routes/web.php:357-375, app/Support/FilterPd3i.php:63-100`
- **Deskripsi**: pd3iBaseQuery() dan query tren/epiweek di SurveillanceRepository memulai dari SurveillanceCase::query() tanpa visibleTo(), jadi semua pengguna grup is_admin (superadmin, admin, surveilans_puskesmas, surveilans_rs, imunisasi_faskes) melihat angka se-kota. Ini KEPUTUSAN DESAIN yang tertulis: routes/web.php:357-358 'Dasbor PD3I dibuka untuk semua pengguna modul (admin + faskes) — data tetap city-wide untuk semua peran'. Respons API hanya berisi hitungan (tanpa nama/NIK/alamat). Namun diff ini menambah dua dimensi penyempit: faskes_pelapor (filter + tabel per_faskes_pelapor) dan tanggal_dari/tanggal_sampai (bisa 1 hari). Dikombinasikan dengan jenis penyakit + kelurahan + tabel per RT + demografi (kelompok umur, jenis kelamin, status vaksinasi, meninggal), petugas RS A bisa mendapatkan sel berisi 1 kasus milik RS B/puskesmas lain dan menyimpulkan atribut kesehatan individu (risiko re-identifikasi data kesehatan). Excel ekspor TETAP ter-scope visibleTo (SurveillanceExport.php:64), sesuai spec 2026-10-15 baris 103.
- **Rekomendasi**: Konfirmasi dengan pemilik produk bahwa granularitas harian + per faskes + per RT memang boleh untuk peran faskes. Bila tidak: (a) terapkan small-cell suppression (mis. tampilkan '<3') untuk non-superadmin pada tabel per RT/kelurahan/faskes dan demografi, atau (b) batasi rentang minimal (mis. ≥1 bulan) untuk peran faskes. Verifikasi manual: baca getPd3iWilayah/getPd3iDemografi dan pastikan cabang non-superadmin melewati penekan sel kecil; tes fitur dengan akun surveilans_rs yang memastikan sel <3 tersamarkan.
- **Referensi**: OWASP A01:2021 (Broken Access Control) — information exposure via aggregate inference
- **Kepastian**: Terverifikasi manual (desain city-wide eksplisit; risiko inferensi adalah analisis)

### [SEC-003] Input multiselect bersarang (mis. wilker[][]=x) memicu 500 'Array to string conversion' di FilterPd3i::multi()
- **Track**: Keamanan (static)
- **Lokasi**: `app/Support/FilterPd3i.php:143-161 (multi), app/Support/FilterPd3i.php:51-52 (tahun/jenis_kasus_id)`
- **Deskripsi**: multi() menerima array dari request lalu, untuk mode non-int, menjalankan trim((string) $v) pada tiap elemen (FilterPd3i.php:158). Elemen yang berupa array (dari query string name[][]=...) menghasilkan warning 'Array to string conversion' yang diubah Laravel menjadi ErrorException → HTTP 500 pada keempat API, ekspor PDF, dan ekspor Excel. Berlaku untuk wilker, kab_kota, faskes_pelapor. kelurahan_id aman (intval(array) = 1), tanggal_dari/sampai aman (dicek is_string), tahun & jenis_kasus_id aman ((int) array = 1, tanpa error — hanya hasil semu). Dampak: hanya pengguna terautentikasi yang bisa memicunya, tanpa kebocoran data; tetapi mengotori laravel.log dan, bila APP_DEBUG=true, menampilkan stack trace.
- **Rekomendasi**: Di multi(), saring elemen non-skalar: array_filter($items, fn($v) => is_scalar($v) && $v !== ''). Untuk tahun/jenis_kasus_id, baca hanya bila is_scalar/ctype_digit dan batasi tahun ke rentang wajar (mis. 2000..tahun berjalan+1). Verifikasi manual: baca ulang multi() dan tambahkan kasus FilterPd3iTest dengan Request::create('/', 'GET', ['wilker' => [['x']]]) yang mengharapkan null tanpa exception.
- **Referensi**: OWASP A04:2021 (Insecure Design) — improper input validation
- **Kepastian**: Terverifikasi manual (alur tipe dibaca; tidak dijalankan)

### [SEC-004] Nama berkas Excel dirakit dari nilai wilker request — karakter '/' atau '\' memicu 500
- **Track**: Keamanan (static)
- **Lokasi**: `app/Http/Controllers/Pd3iDashboardController.php:125-131`
- **Deskripsi**: exportExcel() menyusun nama berkas 'surveilans-pd3i-{tahun}-{wilker...}.xlsx' langsung dari nilai wilker request (hanya spasi diganti '_'). Symfony HeaderUtils::makeDisposition menolak nama berkas yang mengandung '/' atau '\' dengan InvalidArgumentException → 500. Header injection tidak mungkin (Symfony memvalidasi), jadi dampaknya hanya kegagalan ekspor bila nilai wilker (dari URL atau data wilker_puskesmas yang mengandung garis miring) memuat karakter itu. Pola ini sudah ada sebelum diff (versi lama baris 147-151), dipindahkan apa adanya.
- **Rekomendasi**: Sanitasi nama berkas: Str::slug(implode('-', $f->wilker)) atau preg_replace('/[^A-Za-z0-9_-]+/', '_', ...), dan batasi panjangnya. Verifikasi manual: baca ulang baris penyusun $namaFile dan pastikan hanya karakter aman yang lolos.
- **Referensi**: OWASP A04:2021 (Insecure Design)
- **Kepastian**: Terverifikasi manual

### [SEC-005] Endpoint agregat/ekspor PD3I tanpa throttle dan tanpa batas jumlah nilai whereIn
- **Track**: Keamanan (static)
- **Lokasi**: `routes/web.php:360-375, app/Support/FilterPd3i.php:143-161, app/Exports/SurveillanceExport.php:23`
- **Deskripsi**: Empat API (kinerja/demografi/tren/wilayah), export-pdf (4 query agregat + render DomPDF) dan export-excel (FromQuery + ShouldAutoSize, memuat relasi imunisasi/spesimen/kontak/faskes) tidak memiliki middleware throttle. Array filter (wilker, kab_kota, kelurahan_id, faskes_pelapor) tidak dibatasi panjangnya di FilterPd3i; batas praktisnya hanya max_input_vars PHP (bawaan 1000). Pengguna terautentikasi dapat mengulang ekspor PDF/Excel se-kota untuk membebani server (CLAUDE.md mencatat riwayat OOM ekspor di prod dengan memory_limit 128 MB). Tidak ada jalur eksploitasi tanpa login.
- **Rekomendasi**: Tambahkan ->middleware('throttle:30,1') (atau sejenis) pada export-pdf/export-excel, dan batasi jumlah nilai per multiselect di multi() (mis. array_slice(..., 0, 200)). Verifikasi manual: baca definisi rute untuk memastikan throttle terpasang; cek multi() memotong array.
- **Referensi**: OWASP A04:2021 (Insecure Design) — resource consumption
- **Kepastian**: Terverifikasi manual

### [REQ-002] Baris 'Faskes pelapor' di header PDF selalu tampil ('Semua Faskes'), padahal spec menyebut 'bila dipilih'
- **Track**: Persyaratan (Requirements Conformance) (static)
- **Lokasi**: `resources/views/admin/epidemiologi/pdf/pd3i-dashboard.blade.php:209 (footer :526)`
- **Deskripsi**: Spec meminta baris Periode selalu ada dan baris Faskes pelapor muncul hanya bila faskes dipilih. Implementasinya selalu mencetak baris itu dengan fallback 'Semua Faskes'. Pilihan ini konsisten dengan baris Wilker/Kelurahan dan tidak merugikan pembaca, tetapi menyimpang dari teks spec. Footer PDF juga tidak mencantumkan faskes, padahal filter wilayah lain ada di footer. Spec tidak mewajibkan faskes di footer.
- **Rekomendasi**: Pilih salah satu: ubah spec agar sesuai perilaku sekarang (lebih konsisten, disarankan), atau bungkus baris dengan @if($namaFaskes). Bila filter faskes aktif, pertimbangkan menambahkannya juga ke footer.
- **Referensi**: R9 - spec :116
- **Kepastian**: Eksplisit

### [REQ-003] multi() memicu 500 bila faskes_pelapor (atau filter multi lain) dikirim sebagai array bersarang
- **Track**: Persyaratan (Requirements Conformance) (static)
- **Lokasi**: `app/Support/FilterPd3i.php:149-158`
- **Deskripsi**: Spec menekankan normalisasi di server 'karena URL bisa diketik tangan'. multi() hanya menyaring null dan '', lalu memanggil trim((string) $v). Bila URL berisi faskes_pelapor[0][]=x, $v berupa array, sehingga terjadi 'Array to string conversion' (ErrorException, HTTP 500), bukan nilai yang diabaikan. Pola ini sudah ada untuk wilker/kab_kota sebelum fitur ini, tetapi kini terkumpul di FilterPd3i dan juga menjangkau parameter baru. Tanggal sudah aman karena $teks() mensyaratkan is_string.
- **Rekomendasi**: Saring dengan is_scalar sebelum di-cast: array_filter($items, fn ($v) => is_scalar($v) && $v !== ''). Tambahkan kasus array bersarang ke tes Pd3iFilterFaskesTanggalTest::test_tanggal_dari_url_yang_tak_sah_diabaikan atau tes setara.
- **Referensi**: R5 (semangat normalisasi input URL) - spec :34
- **Kepastian**: Eksplisit

### [UX-005] Tanggal 'dari' > 's/d' ditukar diam-diam oleh server
- **Track**: UI/UX (static)
- **Lokasi**: `resources/views/admin/epidemiologi/pd3i-dashboard.blade.php:1708-1724; app/Support/FilterPd3i.php:37-39`
- **Deskripsi**: FilterPd3i menukar rentang yang terbalik, sementara input di layar tetap menampilkan urutan terbalik tanpa umpan balik. Hasilnya benar, tetapi tampilan tidak sama dengan apa yang dipakai server. Tidak ada min/max dinamis antar kedua input (dari.max = sampai.value) untuk mencegahnya.
- **Rekomendasi**: Set filter-tgl-sampai.min = nilai 'dari' dan filter-tgl-dari.max = nilai 's/d' saat berubah, atau tukar juga nilainya di UI agar sama dengan perlakuan server.
- **Referensi**: heuristik Nielsen #5 (Error prevention)

### [UX-006] Tren Bulanan menampilkan 0 untuk bulan di luar rentang tanpa catatan
- **Track**: UI/UX (static)
- **Lokasi**: `resources/views/admin/epidemiologi/pd3i-dashboard.blade.php:731 & 1359-1362; resources/views/admin/epidemiologi/pdf/pd3i-dashboard.blade.php:368-392; app/Repositories/Admin/Epidemiologi/SurveillanceRepository.php:743-760`
- **Deskripsi**: Tren bulanan memakai terapkan() (ikut rentang) tetapi tetap merender 12 bulan. Bulan di luar rentang tampil sebagai 0 kasus, baik di grafik web (chart-bulanan) maupun di tabel 'Tren Bulanan' PDF, sehingga terbaca seperti nol laporan. Catatan .catatan-rentang hanya dipasang di grafik 5 tahunan & epiweek, yang justru tidak ikut rentang.
- **Rekomendasi**: Saat rentang aktif, tampilkan bulan di luar rentang sebagai kosong/abu-abu ('di luar periode') alih-alih 0, atau tambahkan catatan 'Bulan di luar periode tidak dihitung'.
- **Referensi**: heuristik Nielsen #2 (Match between system and the real world)

### [UX-007] Nama berkas ekspor tidak membedakan rentang tanggal/faskes
- **Track**: UI/UX (static)
- **Lokasi**: `app/Http/Controllers/Pd3iDashboardController.php:118, 125-129; resources/views/admin/epidemiologi/pdf/pd3i-dashboard.blade.php:5`
- **Deskripsi**: PDF selalu 'pd3i-dashboard-{tahun}.pdf'. Excel 'surveilans-pd3i-{tahun}[-wilker].xlsx' mengabaikan faskes pelapor dan rentang. Unduhan beberapa periode dalam tahun yang sama mendapat nama identik (browser menambah ' (1)'), dan berkas tidak bisa dikenali periodenya tanpa dibuka. <title> PDF juga hanya 'Laporan Surveilans PD3I - {tahun}'.
- **Rekomendasi**: Sertakan rentang bila aktif, mis. 'pd3i-dashboard-2026-03-01_sd_2026-06-30.pdf' (berkas yang sama untuk Excel), dan pakai labelPeriode() di <title> PDF.
- **Referensi**: heuristik Nielsen #6 (Recognition rather than recall)

### [UX-008] Footer PDF tidak memuat Faskes pelapor, padahal header memuatnya
- **Track**: UI/UX (static)
- **Lokasi**: `resources/views/admin/epidemiologi/pdf/pd3i-dashboard.blade.php:526`
- **Deskripsi**: Header PDF menambah baris 'Faskes pelapor', tetapi doc-footer-right (yang tercetak di tiap halaman) hanya berisi periode, penyakit, kab/kota, wilker, dan kelurahan. Halaman lepas dari laporan yang difilter faskes tidak menunjukkan bahwa datanya hanya dari faskes tertentu.
- **Rekomendasi**: Tambahkan '&bull; {{ $namaFaskes ?? 'Semua Faskes' }}' ke footer. Bila daftarnya panjang, ringkas jadi 'N faskes'.
- **Referensi**: heuristik Nielsen #4 (Consistency and standards)

### [UX-009] Label filter baru tidak seragam dengan label lain dan tata letak md menyisakan baris kedua timpang
- **Track**: UI/UX (static)
- **Lokasi**: `resources/views/admin/epidemiologi/pd3i-dashboard.blade.php:334-427; resources/views/admin/epidemiologi/pdf/pd3i-dashboard.blade.php:209`
- **Deskripsi**: Label 'Tgl lapor dari' / 'Tgl lapor s/d' memakai singkatan dan huruf kecil, sementara label lain memakai Title Case penuh ('Jenis Penyakit', 'Wilker Puskesmas', 'Faskes Pelapor'). Header PDF juga menulis 'Faskes pelapor:' sementara layar menulis 'Faskes Pelapor'. Di md, jumlah kolom 2+3+3+2+2+3+2+2 = 19 sehingga baris kedua hanya terisi 7/12 (faskes + 2 tanggal). Rentang tanggal tampak terpisah dari Tahun yang membatasinya, dan hubungan 'rentang harus di dalam Tahun' tidak terlihat.
- **Rekomendasi**: Seragamkan label ('Tanggal Lapor Dari' / 'Tanggal Lapor Sampai', atau satu label grup 'Tanggal Lapor' dengan dua input). Letakkan rentang tepat di sebelah Tahun, atau tambahkan hint 'dalam tahun terpilih'. Atur lebar kolom agar dua baris seimbang (mis. col-md-3 x4 per baris).
- **Referensi**: heuristik Nielsen #4 (Consistency and standards), #8 (Aesthetic and minimalist design)


## ⚪ Severity: Info

### [A11Y-008] Kontras catatan .text-muted lolos AA
- **Track**: Aksesibilitas (static)
- **Lokasi**: `resources/views/admin/epidemiologi/pd3i-dashboard.blade.php:712,723; public/css/app.css:7772`
- **Deskripsi**: Admin memuat Bootstrap 5.1.3 (public/css/app.css): .text-muted = #6c757d, 4,69:1 di atas putih — lolos 4,5:1 untuk teks kecil .small. Label filter (#6b7280, 5,0:1) juga lolos. Margin tipis: bila latar card-body diubah ke tint (mis. --primary-light), kontras turun di bawah 4,5:1. Fokus terlihat pada toggle/checkbox/input date dijamin outline 3px dari shared-styles.
- **Rekomendasi**: Tidak wajib; bila ingin margin aman, pakai var(--text-muted) #4b5563 (7,5:1) dari shared-styles untuk catatan pencegah salah baca.
- **Referensi**: WCAG 1.4.3, 2.4.7

### [SEC-006] Daftar opsi Faskes Pelapor diambil se-kota dari teks bebas instansi_pelapor dan ditampilkan ke semua peran
- **Track**: Keamanan (static)
- **Lokasi**: `app/Http/Controllers/Pd3iDashboardController.php:46-51`
- **Deskripsi**: index() mengirim DISTINCT instansi_pelapor seluruh kasus (tanpa visibleTo) ke dropdown untuk setiap pengguna grup is_admin. Konsisten dengan keputusan city-wide (routes/web.php:357-358) dan isinya nama institusi, tetapi kolom ini teks bebas — bila petugas pernah mengetik nama orang/dokter atau catatan, nilai itu terlihat oleh semua faskes.
- **Rekomendasi**: Tinjau sekali isi DISTINCT instansi_pelapor di prod untuk memastikan tidak berisi data pribadi; jangka panjang normalisasi ke master faskes (id_faskes_pelapor). Verifikasi manual: query SELECT DISTINCT instansi_pelapor oleh DBA.
- **Referensi**: OWASP A01:2021
- **Kepastian**: Terverifikasi manual

### [SEC-007] Kontrol yang sudah benar: akses rute, CSRF, SQL binding, dan escaping XSS
- **Track**: Keamanan (static)
- **Lokasi**: `routes/web.php:91, app/Http/Middleware/IsAdmin.php:20, resources/views/admin/epidemiologi/pd3i-dashboard.blade.php:320-321,408,919-922,1440-1448, resources/views/admin/epidemiologi/pdf/pd3i-dashboard.blade.php:204-209`
- **Deskripsi**: (1) Rute pd3i-dashboard berada di grup Route::middleware(['auth','is_admin']) (routes/web.php:91); IsAdmin memakai AksesKesmas::adminSetara() yang menolak PERAN_TERBATAS (AksesKesmas.php:66-77) → akun Kesmas terbatas tidak lolos, plus $this->middleware('auth') di controller. (2) Ekspor Excel tetap visibleTo(auth()->user()) sebelum filterPd3i diterapkan (SurveillanceExport.php:64-70); imunisasi_faskes/peran tak dikenal fail-closed (SurveillanceCase.php:576-577). (3) Form PDF POST memuat @csrf (pd3i-dashboard.blade.php:320-321); API & Excel GET read-only. (4) Semua filter memakai where/whereIn/whereYear berbinding; tanggal divalidasi ketat createFromFormat('!Y-m-d') + round-trip (FilterPd3i.php:134-135) sehingga labelPeriode() hanya menerima Y-m-d sah. (5) XSS: opsi faskes dirender {{ }} (blade:408), label multiselect via textContent (blade:1012-1016), tabel per_faskes_pelapor via esc() (blade:919-922, 1442), hidden input PDF via createElement + .value, blade PDF memakai {{ }} untuk namaFaskes/periode. Tidak ditemukan {!! !!} baru.
- **Rekomendasi**: Pertahankan. Catatan kecil: esc() tidak meng-escape tanda kutip — aman selama hanya dipakai di konten elemen, jangan dipakai di dalam atribut.
- **Referensi**: OWASP A01/A03/A07:2021
- **Kepastian**: Terverifikasi manual

### [REQ-004] Angka dasbor se-kota, tetapi Excel dibatasi visibleTo: dengan filter faskes, akun faskes bisa melihat angka faskes lain di layar dan mendapat ekspor kosong
- **Track**: Persyaratan (Requirements Conformance) (static)
- **Lokasi**: `app/Repositories/Admin/Epidemiologi/SurveillanceRepository.php:580-586; app/Exports/SurveillanceExport.php:64-70; app/Http/Controllers/Pd3iDashboardController.php:47-51`
- **Deskripsi**: Rute dasbor sengaja city-wide untuk semua peran (routes/web.php:357-358), dan pd3iBaseQuery tidak memasang visibleTo. SurveillanceExport::untukDasborPd3i tetap memasang visibleTo lebih dulu sesuai R10. Akibatnya, akun faskes yang memilih faskes lain di filter baru melihat angkanya di dasbor dan PDF, tetapi Excel berisi 0 baris. Ini sesuai spec dan bukan regresi, tetapi filter faskes membuat perbedaan ini jauh lebih mudah terlihat. Opsi dropdown juga mendaftar nama seluruh faskes pelapor kepada semua peran.
- **Rekomendasi**: Tidak perlu tindakan kode. Pertimbangkan catatan di UI atau konfirmasi pemilik produk bahwa perbedaan dasbor vs Excel untuk akun faskes memang diterima.
- **Referensi**: R10 - spec :103; routes/web.php:357-358
- **Kepastian**: Eksplisit

### [REQ-005] Butir R1, R3-R8, R10-R13 terpenuhi; R2 dan R9 sebagian (lihat REQ-001/002)
- **Track**: Persyaratan (Requirements Conformance) (static)
- **Lokasi**: `app/Support/FilterPd3i.php:1-162; resources/views/admin/epidemiologi/pd3i-dashboard.blade.php:400-425,930-990,1707-1744`
- **Deskripsi**: Terverifikasi: R1 ms-dropdown data-ms=faskes_pelapor setelah Kelurahan (blade :400-415). R3 dua input date dengan label for/id, min/max dikunci ke Tahun lewat aturBatasTanggal(), ganti Tahun mengosongkan keduanya lalu fetch (blade :418-425,1707-1724); nilai di luar min/max ditandai is-invalid dan tidak dikirim (:984-990). R4 satu ujung terbuka (FilterPd3i :68-73, labelPeriode :114-116). R5 createFromFormat('!Y-m-d') dengan pemeriksaan round-trip, tanggal di luar tahun menjadi null, dari > s/d ditukar (:35-43,126-140). Spec menyebut whereDate, implementasinya where langsung; ini setara karena kolom bertipe DATE (migrasi create_surveillance_cases :45). R6 Kinerja/Demografi/Wilayah/Tren bulanan dan per faskes/kec/kel, PDF, dan Excel semuanya lewat pd3iBaseQuery atau terapkan(). R7 tren 5 tahunan dan epiweek memakai terapkanTanpaWaktu(), catatan <p> selalu terlihat saat rentang aktif (blade :712,723,970). R8 JumlahPenduduk memakai $f->tahun (repo :599-600). R10 konstruktor bernama dipertahankan, visibleTo dipasang lebih dulu (Export :37-49,64). R11 tanpa config(), now() hanya di dariRequest(), unit test memakai TestCase polos PHPUnit. R12 semua kolom terapkan*() berprefix surveillance_cases.. R13 tidak ada normalisasi nama, id_faskes_pelapor tidak disentuh, tidak ada lintas tahun. Seluruh butir pengujian di spec :120-138 punya padanan tes.
- **Rekomendasi**: -
- **Referensi**: R1-R13 - spec :19-144
- **Kepastian**: Eksplisit

### [UX-010] Mengganti Tahun mengosongkan rentang tanggal tanpa pemberitahuan
- **Track**: UI/UX (static)
- **Lokasi**: `resources/views/admin/epidemiologi/pd3i-dashboard.blade.php:1719-1723`
- **Deskripsi**: Listener filter-tahun menghapus nilai kedua input tanggal sebelum fetch. Ini sesuai aturan 'rentang di dalam Tahun' dan mencegah tanggal tak sah, tetapi pengguna tidak diberi tahu bahwa rentangnya hilang. Ini perlu diketahui bila nanti ada ringkasan filter aktif (UX-004).
- **Rekomendasi**: Opsional: tampilkan teks singkat 'Rentang tanggal dikosongkan karena Tahun diganti', atau geser bulan-hari yang sama ke tahun baru.
- **Referensi**: heuristik Nielsen #1 (Visibility of system status)


---
*Laporan dihasilkan otomatis oleh skill `audit-aplikasi-swarm`. Temuan dengan kepastian "Kandidat" atau "Inferensi" tetap perlu ditinjau manual sebelum ditindaklanjuti.*

## Tindak lanjut (16 Okt 2026, branch fix/audit-filter-pd3i)

| Temuan | Status | Perbaikan |
|---|---|---|
| A11Y-001 (Tinggi), UX-001 | Diperbaiki | Tanggal tak sah: border merah + `aria-invalid` + pesan teks di baris status (`aria-describedby`), tidak dikirim |
| UX-004, A11Y-006, A11Y-002, UX-010 | Diperbaiki | Baris "Periode berlaku: …" `role=status aria-live=polite` di bawah bilah filter |
| UX-002 | Diperbaiki | Debounce 600 ms pada input tanggal |
| UX-005 | Diperbaiki | min/max silang antar "dari" dan "sampai" |
| UX-006 | Diperbaiki | Catatan "Bulan di luar rentang … ditampilkan 0" di Tren Bulanan |
| A11Y-007, UX-009 | Diperbaiki | Catatan ditautkan ke `<canvas>`; label "Tgl Lapor Dari/Sampai"; kolom tanggal `col-md-3` (tahun tak terpotong) |
| REQ-001, UX-003 | Diperbaiki | `TRIM` di opsi dan filter faskes pelapor |
| REQ-003, SEC-003 | Diperbaiki | `FilterPd3i::multi()` hanya menerima skalar |
| SEC-001 | Diperbaiki | `SurveillanceExport` binder: teks berawalan `=` jadi teks literal |
| SEC-004, UX-007 | Diperbaiki | Nama berkas Excel disanitasi; nama Excel/PDF memuat rentang; `<title>` PDF memuat periode |
| UX-008 | Diperbaiki | Footer PDF memuat faskes |
| REQ-002 | Spec disesuaikan | Baris Faskes di PDF selalu tampil ("Semua Faskes"), konsisten dg baris lain |
| SEC-002 | Tidak diubah | Dasbor se-kota = keputusan desain (`routes/web.php`); penyamaran sel kecil perlu keputusan pemilik produk |
| SEC-005 | Tidak diubah | Throttle/batas array berlaku lintas aplikasi, di luar lingkup fitur |
| A11Y-003/004/005 | Tidak diubah | Warisan komponen `.ms-dropdown` yang dipakai semua filter; perlu perbaikan komponen tersendiri |

Tes: 185/185 Epidemiologi + unit lolos; suite penuh 1.479 lolos, 3 gagal yang sudah ada di main (KelompokVaksinKosongTest ×2, ExportAnakVerifikasiRtTest).

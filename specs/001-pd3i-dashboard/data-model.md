# Data Model: Dashboard Surveilans PD3I

**Branch**: `001-pd3i-dashboard` | **Date**: 2026-04-11

---

## Entitas yang Digunakan (Existing)

Semua entitas sudah ada — dashboard bersifat **read-only**, tidak ada skema baru.

### SurveillanceCase (`surveillance_cases`)

Entitas utama. Kolom yang dipakai dashboard:

| Kolom | Tipe | Dipakai di Tab |
|-------|------|----------------|
| `id` | bigint PK | semua |
| `id_jenis_kasus` | FK → `jenis_kasus_epidemiologi` | semua (filter) |
| `wilker_puskesmas` | string | semua (filter) |
| `status_kasus` | enum: suspected/confirmed/discarded | Kinerja, Demografi |
| `kondisi_akhir` | enum: ... / meninggal | Kinerja, Demografi |
| `tanggal_onset` | date | Tren (kurva epidemi epiweek) |
| `tanggal_lapor` | date | Tren (bulanan), filter tahun |
| `tanggal_pengambilan_spesimen` | date nullable | Kinerja (% sampel) |
| `tanggal_hasil_lab` | date nullable | Kinerja (% lab diterima) |
| `status_lab` | enum: positif/negatif/belum_diperiksa | Kinerja (positivity rate) |
| `hasil_lab` | text nullable | Kinerja (campak vs rubella) |
| `tanggal_lahir` | date nullable | Demografi (kelompok umur) |
| `riwayat_imunisasi` | enum: tidak_ada/tidak_lengkap/lengkap/tidak_tahu | Demografi |
| `status_rawat` | enum: rawat_inap/rawat_jalan | Demografi (severity) |
| `komplikasi_diare` | boolean | Demografi |
| `komplikasi_kebutaan` | boolean | Demografi |
| `komplikasi_pneumonia` | boolean | Demografi |
| `komplikasi_malnutrisi` | boolean | Demografi |
| `komplikasi_bronchopneumonia` | boolean | Demografi |
| `komplikasi_otitis_media` | boolean | Demografi |
| `komplikasi_encephalitis` | boolean | Demografi |
| `komplikasi_ulkus_mukosa_mulut` | boolean | Demografi |
| `id_kec` | FK → `kecamatan` | Tren, Wilayah |
| `id_kel` | FK → `kelurahan` | Tren, Wilayah |
| `id_faskes_pelapor` | FK → `rumah_sakits` | Tren (per faskes) |
| `latitude` | decimal(10,8) nullable | Wilayah (peta) |
| `longitude` | decimal(11,8) nullable | Wilayah (peta) |

---

### JenisKasusEpidemiologi (`jenis_kasus_epidemiologi`)

Digunakan sebagai sumber data dropdown filter penyakit.

| Kolom | Tipe | Catatan |
|-------|------|---------|
| `id` | bigint PK | — |
| `nama_penyakit` | string | Ditampilkan di filter |
| Scope: `active()` | — | Filter hanya penyakit aktif |

---

### Kecamatan (`kecamatan`) & Kelurahan (`kelurahan`)

Digunakan untuk agregasi tabel dan tren wilayah.

| Kolom | Tipe | Catatan |
|-------|------|---------|
| `id` | bigint PK | — |
| `name` | string | Nama kecamatan/kelurahan |
| Relasi kelurahan → kecamatan | FK `id_kecamatan` | Untuk grouping di tabel wilayah |

---

### RumahSakit (`rumah_sakits`)

Digunakan untuk tren per faskes pelapor.

| Kolom | Tipe | Catatan |
|-------|------|---------|
| `id` | bigint PK | — |
| `nama` | string | Nama faskes |

---

## Struktur Response API per Tab

### `/api/kinerja` → `array`

```json
{
  "campak_rubella": {
    "suspek": 45,
    "confirmed_campak": 12,
    "confirmed_rubella": 3,
    "discarded": 28,
    "meninggal": 1,
    "pct_sampel": 86.7,
    "pct_lab_diterima": 75.6,
    "positivity_rate": 33.3
  },
  "afp": {
    "total": 8,
    "confirmed": 2,
    "npafp_rate": null
  },
  "difteri": {
    "observasi": 5,
    "confirmed": 1
  },
  "pertusis": {
    "suspek": 3
  }
}
```

### `/api/demografi` → `array`

```json
{
  "kelompok_umur": [
    { "label": "< 6 bulan", "suspek": 3, "confirmed": 1, "discarded": 2 },
    { "label": "6–8 bulan", "suspek": 5, "confirmed": 2, "discarded": 3 },
    ...
  ],
  "status_vaksinasi": {
    "tidak_ada": 15, "tidak_lengkap": 22, "lengkap": 8, "tidak_tahu": 5
  },
  "severity": {
    "pct_rawat_inap": 42.0,
    "komplikasi": {
      "diare": 4, "kebutaan": 0, "pneumonia": 7, "malnutrisi": 2,
      "bronchopneumonia": 5, "otitis_media": 3, "encephalitis": 1, "ulkus_mukosa_mulut": 2
    },
    "meninggal": 1
  }
}
```

### `/api/tren` → `array`

```json
{
  "epiweek": [
    { "week": "2025-W01", "suspek": 3, "confirmed": 1 },
    { "week": "2025-W02", "suspek": 5, "confirmed": 0 },
    ...
  ],
  "bulanan": [
    { "bulan": 1, "label": "Jan", "total": 8, "confirmed": 2 },
    ...
  ],
  "per_faskes": [
    { "bulan": 1, "faskes": "Puskesmas Bontang Utara 1", "jumlah": 3 },
    ...
  ],
  "per_kecamatan": [
    { "bulan": 1, "kecamatan": "Bontang Utara", "jumlah": 5 },
    ...
  ],
  "per_kelurahan": [
    { "bulan": 1, "kelurahan": "Api-Api", "kecamatan": "Bontang Utara", "jumlah": 2 },
    ...
  ]
}
```

### `/api/wilayah` → `array`

```json
{
  "per_puskesmas": [
    { "wilker": "Bontang Utara 1", "suspek": 12, "confirmed": 4, "meninggal": 0 },
    ...
  ],
  "per_kecamatan": [
    { "kecamatan": "Bontang Utara", "suspek": 20, "confirmed": 7, "meninggal": 1 },
    ...
  ],
  "per_kelurahan": [
    { "kelurahan": "Api-Api", "kecamatan": "Bontang Utara", "suspek": 5, "confirmed": 2, "meninggal": 0 },
    ...
  ],
  "peta": [
    { "id": 123, "lat": -0.123, "lng": 117.456, "nama": "Budi S.", "penyakit": "Campak", "status": "confirmed" },
    ...
  ]
}
```

---

## Kelompok Umur (Referensi Kalkulasi)

Dihitung via `TIMESTAMPDIFF(MONTH, tanggal_lahir, tanggal_onset)`:

| Label | Kondisi |
|-------|---------|
| < 6 bulan | bulan < 6 |
| 6–8 bulan | 6 ≤ bulan ≤ 8 |
| 9–11 bulan | 9 ≤ bulan ≤ 11 |
| 12–17 bulan | 12 ≤ bulan ≤ 17 |
| 18–59 bulan | 18 ≤ bulan ≤ 59 |
| 5–9 tahun | 60 ≤ bulan ≤ 119 |
| 10–14 tahun | 120 ≤ bulan ≤ 179 |
| ≥ 15 tahun | bulan ≥ 180 |
| Tidak Diketahui | `tanggal_lahir` IS NULL |

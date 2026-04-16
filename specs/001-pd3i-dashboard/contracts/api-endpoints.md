# API Contracts: Dashboard Surveilans PD3I

**Branch**: `001-pd3i-dashboard` | **Date**: 2026-04-11  
**Base path**: `/admin/epidemiologi/pd3i-dashboard`  
**Auth**: Session cookie (`super-admin` role required)  
**Format**: JSON (GET endpoints), application/pdf (POST export)

---

## Filter Parameters (semua GET endpoint)

| Parameter | Tipe | Wajib | Default | Keterangan |
|-----------|------|-------|---------|------------|
| `tahun` | integer | Tidak | tahun berjalan | Tahun filter `tanggal_lapor` |
| `jenis_kasus_id` | integer | Tidak | null (semua) | FK ke `jenis_kasus_epidemiologi.id` |
| `wilker` | string | Tidak | null (semua) | Nilai dari `wilker_puskesmas` |

---

## GET `/`

Halaman utama dashboard (Blade view).

**Response**: HTML `200 OK`

---

## GET `/api/kinerja`

Scorecard kinerja surveilans per panel penyakit.

**Response `200 OK`**:
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

**Catatan**:
- `npafp_rate` selalu `null` hingga data populasi tersedia
- `pct_sampel` = COUNT(tanggal_pengambilan_spesimen IS NOT NULL) / COUNT(*) * 100
- `positivity_rate` = COUNT(status_lab = 'positif') / COUNT(status_lab != 'belum_diperiksa') * 100

---

## GET `/api/demografi`

Distribusi kelompok umur, status vaksinasi, dan severity.

**Response `200 OK`**:
```json
{
  "kelompok_umur": [
    { "label": "< 6 bulan", "suspek": 3, "confirmed": 1, "discarded": 2 }
  ],
  "status_vaksinasi": {
    "tidak_ada": 15, "tidak_lengkap": 22, "lengkap": 8, "tidak_tahu": 5
  },
  "severity": {
    "pct_rawat_inap": 42.0,
    "komplikasi": {
      "diare": 4, "kebutaan": 0, "pneumonia": 7, "malnutrisi": 2,
      "bronchopneumonia": 5, "otitis_media": 3, "encephalitis": 1,
      "ulkus_mukosa_mulut": 2
    },
    "meninggal": 1
  }
}
```

**Catatan**:
- `kelompok_umur` array selalu berisi 9 item (8 grup + "Tidak Diketahui"), nilai 0 jika kosong
- `status_vaksinasi` selalu berisi 4 key, nilai 0 jika kosong

---

## GET `/api/tren`

Data tren untuk kurva epidemi dan grafik bulanan.

**Response `200 OK`**:
```json
{
  "epiweek": [
    { "week": "2025-W01", "suspek": 3, "confirmed": 1 }
  ],
  "bulanan": [
    { "bulan": 1, "label": "Jan", "total": 8, "confirmed": 2 }
  ],
  "per_faskes": [
    { "bulan": 1, "faskes": "Puskesmas Bontang Utara 1", "jumlah": 3 }
  ],
  "per_kecamatan": [
    { "bulan": 1, "kecamatan": "Bontang Utara", "jumlah": 5 }
  ],
  "per_kelurahan": [
    { "bulan": 1, "kelurahan": "Api-Api", "kecamatan": "Bontang Utara", "jumlah": 2 }
  ]
}
```

**Catatan**:
- `epiweek` mencakup tahun yang dipilih + tahun sebelumnya (untuk konteks)
- `bulanan` selalu 12 item (Jan–Des), nilai 0 untuk bulan kosong

---

## GET `/api/wilayah`

Tabel agregasi wilayah dan data peta.

**Response `200 OK`**:
```json
{
  "per_puskesmas": [
    { "wilker": "Bontang Utara 1", "suspek": 12, "confirmed": 4, "meninggal": 0 }
  ],
  "per_kecamatan": [
    { "kecamatan": "Bontang Utara", "suspek": 20, "confirmed": 7, "meninggal": 1 }
  ],
  "per_kelurahan": [
    { "kelurahan": "Api-Api", "kecamatan": "Bontang Utara", "suspek": 5, "confirmed": 2, "meninggal": 0 }
  ],
  "peta": [
    { "id": 123, "lat": -0.123, "lng": 117.456, "nama": "B***", "penyakit": "Campak-Rubella", "status": "confirmed" }
  ]
}
```

**Catatan**:
- `peta` hanya berisi kasus dengan `latitude IS NOT NULL AND longitude IS NOT NULL`
- `nama` pada peta disamarkan (initial saja) untuk privasi

---

## POST `/export-pdf`

Generate dan download PDF seluruh dashboard.

**Request body** (JSON):
```json
{
  "tahun": 2025,
  "jenis_kasus_id": null,
  "wilker": null
}
```

**Response**: `application/pdf` attachment — `pd3i-dashboard-{tahun}.pdf`

**Catatan**:
- Controller melakukan query ulang semua 4 tab data server-side
- Layout: A4 Landscape, 4 section (satu per tab)
- Grafik direpresentasikan sebagai tabel HTML (DomPDF tidak support canvas)

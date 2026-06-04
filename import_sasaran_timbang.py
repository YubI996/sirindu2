"""
Import sasaran op timbang CSV → tabel anak (sirindu)

Jalankan:
    python import_sasaran_timbang.py
    python import_sasaran_timbang.py --dry-run          # simulasi tanpa tulis DB
    python import_sasaran_timbang.py --csv path/to.csv  # custom path CSV

Kolom CSV (26):
    No, anak_ke, tgl_lahir, jenis_kelamin, nomor_KK, NIK, nama_anak,
    usia_hamil, berat_lahir, panjang_lahir, lingkar_kepala_lahir,
    kia, kia_bayi_kecil, imd, nama_ortu, nik_ortu, hp_ortu, alamat,
    rt, rw, Prov, Kab/Kota, Kec, Puskesmas, Desa/Kel, Posyandu

Dependensi:
    pip install pymysql

Encoding file CSV: cp1252 (Windows-1252, bukan UTF-8)
"""

import argparse
import csv
import os
import re
import sys
from datetime import datetime
from difflib import SequenceMatcher

import pymysql
import pymysql.cursors

# ─────────────────────────────────────────────
# Konfigurasi
# ─────────────────────────────────────────────
DB = dict(host='127.0.0.1', port=3306, database='sirindu', user='root', password='',
          charset='utf8mb4', cursorclass=pymysql.cursors.DictCursor)

DEFAULT_CSV = os.path.join(os.path.dirname(__file__),
                           'docs', 'Modul import', 'sasaran op timbang.csv')

KODE_WILAYAH_DEFAULT = '647400'   # Kota Bontang (6474), kec. 00 = fallback jujur
FUZZY_THRESHOLD       = 0.80
CHUNK_LOG             = 500        # print progres setiap N baris


# ─────────────────────────────────────────────
# Helper: bersihkan string
# ─────────────────────────────────────────────
def clean_digits(value: str, max_len: int = 16) -> str:
    """Ambil hanya digit, potong sesuai max_len."""
    return re.sub(r'\D', '', str(value or ''))[:max_len]


def clean_phone(value: str) -> str | None:
    """Bersihkan nomor HP: hapus Â, NBSP, dan karakter non-standar; simpan digit/+/-/()/ ."""
    if not value:
        return None
    cleaned = re.sub(r'[^\d+\-()\s]', '', value).strip()
    return cleaned[:20] or None


def normalize(name: str) -> str:
    return re.sub(r'\s+', ' ', name.upper().strip())


# ─────────────────────────────────────────────
# Helper: parse tipe data
# ─────────────────────────────────────────────
def parse_date(value: str):
    if not value or not value.strip():
        return None
    for fmt in ('%Y-%m-%d', '%d/%m/%Y', '%d-%m-%Y'):
        try:
            return datetime.strptime(value.strip(), fmt).strftime('%Y-%m-%d')
        except ValueError:
            pass
    return None


def parse_jk(value: str) -> int:
    v = str(value or '').upper().strip()
    return 1 if v in ('L', 'LAKI', 'LAKI-LAKI', '1') else 2


def parse_bbl(value: str):
    """
    berat_lahir di CSV: inkonsisten — ada gram (2435) dan kg (2.7).
    Heuristik: nilai > 10 → gram, bagi 1000. Nilai 0 → None.
    Hasil disimpan dalam kg (decimal 6,1 di DB).
    """
    try:
        v = float(str(value or '0').strip() or '0')
    except ValueError:
        return None
    if v <= 0:
        return None
    if v > 10:           # kemungkinan gram
        v = v / 1000
    return round(v, 1)


def parse_decimal(value: str, max_val: float = None):
    try:
        v = float(str(value or '').strip())
        if v <= 0:
            return None
        if max_val is not None and v > max_val:
            return None
        return round(v, 1)
    except ValueError:
        return None


def parse_int(value: str, max_val: int = None):
    try:
        v = int(str(value or '').strip())
        if v <= 0:
            return None
        if max_val is not None and v > max_val:
            return None
        return v
    except ValueError:
        return None


def parse_imd(value: str):
    """1 = ya (IMD dilakukan), 2/0 = tidak, kosong = None."""
    v = str(value or '').strip()
    if v == '1':
        return 1
    if v in ('2', '0'):
        return 0
    return None


# ─────────────────────────────────────────────
# Fuzzy match wilayah
# ─────────────────────────────────────────────
def fuzzy_match(name: str, cache: dict, threshold: float = FUZZY_THRESHOLD):
    """Kembalikan ID terbaik dari cache, atau None jika < threshold."""
    normed = normalize(name)
    if not normed:
        return None
    best_score, best_id = 0.0, None
    for cached_name, cached_id in cache.items():
        score = SequenceMatcher(None, normed, normalize(cached_name)).ratio()
        if score > best_score:
            best_score, best_id = score, cached_id
    return best_id if best_score >= threshold else None


# ─────────────────────────────────────────────
# Load master data dari DB
# ─────────────────────────────────────────────
def load_master(cursor) -> dict:
    """Muat semua lookup table ke memory. Return dict berisi cache-cache."""

    cursor.execute("SELECT id, name FROM kecamatan")
    kec_cache = {r['name']: r['id'] for r in cursor.fetchall()}

    cursor.execute("SELECT id, name FROM kelurahan")
    kel_cache = {r['name']: r['id'] for r in cursor.fetchall()}

    cursor.execute("SELECT id, name FROM puskesmas")
    pkm_cache = {r['name']: r['id'] for r in cursor.fetchall()}

    cursor.execute("SELECT id, name FROM posyandu")
    pos_cache = {r['name']: r['id'] for r in cursor.fetchall()}

    # RT: nama format "{nomor}{nama_kelurahan}" — ekstrak nomor di depan
    cursor.execute("SELECT id, name, id_kelurahan FROM rt")
    rt_lookup: dict[int, dict[int, int]] = {}   # {kel_id: {rt_num: rt_id}}
    for r in cursor.fetchall():
        m = re.match(r'^(\d+)', str(r['name'] or ''))
        if m:
            rt_num = int(m.group(1))
            kel_id = r['id_kelurahan']
            if kel_id not in rt_lookup:
                rt_lookup[kel_id] = {}
            rt_lookup[kel_id][rt_num] = r['id']

    return dict(kec=kec_cache, kel=kel_cache, pkm=pkm_cache,
                pos=pos_cache, rt=rt_lookup)


# ─────────────────────────────────────────────
# Resolve wilayah dengan exact → fuzzy
# ─────────────────────────────────────────────
def resolve(name: str, cache: dict) -> int | None:
    if not name or not name.strip():
        return None
    key = normalize(name)
    # exact (case-insensitive)
    for k, v in cache.items():
        if normalize(k) == key:
            return v
    # fuzzy
    return fuzzy_match(name, cache)


def resolve_rt(rt_str: str, kel_id: int | None, rt_lookup: dict) -> int | None:
    if not rt_str or not kel_id:
        return None
    try:
        rt_num = int(str(rt_str).strip())
    except ValueError:
        return None
    return rt_lookup.get(kel_id, {}).get(rt_num)


# ─────────────────────────────────────────────
# NIK dummy — sama dengan NikDummyService.php
# ─────────────────────────────────────────────
_dummy_counters: dict[str, int] = {}


def _encode_tgl(tgl: str, jk: int) -> str:
    """jk: 1=L, 2=P"""
    d = datetime.strptime(tgl, '%Y-%m-%d')
    day = d.day + (40 if jk == 2 else 0)
    return f"{day:02d}{d.month:02d}{str(d.year)[-2:]}"


def next_dummy_nik(tgl: str, jk: int, cursor) -> str:
    kode  = KODE_WILAYAH_DEFAULT
    tgl_p = _encode_tgl(tgl, jk)
    prefix = kode + tgl_p

    if prefix not in _dummy_counters:
        cursor.execute(
            "SELECT MAX(CAST(SUBSTRING(nik, 13, 4) AS UNSIGNED)) AS mx "
            "FROM anak WHERE nik LIKE %s AND SUBSTRING(nik, 13, 1) = '9'",
            (prefix + '%',)
        )
        row = cursor.fetchone()
        mx  = row['mx'] if row and row['mx'] else 9000
        _dummy_counters[prefix] = max(9001, mx + 1)

    urutan = _dummy_counters[prefix]
    _dummy_counters[prefix] += 1
    return prefix + str(urutan).zfill(4)


# ─────────────────────────────────────────────
# Main import
# ─────────────────────────────────────────────
def run(csv_path: str, dry_run: bool):
    conn   = pymysql.connect(**DB)
    cursor = conn.cursor()

    print(f"[INFO] Memuat master data ...")
    master = load_master(cursor)
    print(f"       Kecamatan : {len(master['kec'])}")
    print(f"       Kelurahan : {len(master['kel'])}")
    print(f"       Puskesmas : {len(master['pkm'])}")
    print(f"       Posyandu  : {len(master['pos'])}")

    success, skipped, errors = 0, 0, 0
    error_log = []

    print(f"\n[INFO] Membaca CSV: {csv_path}")
    with open(csv_path, encoding='cp1252', newline='') as f:
        reader = csv.DictReader(f)

        for row_num, row in enumerate(reader, start=2):   # baris 2 = data pertama
            nama = (row.get('nama_anak') or '').strip()
            if not nama:
                skipped += 1
                continue

            try:
                # ── Tanggal lahir ──────────────────────────────
                tgl_lahir = parse_date(row.get('tgl_lahir') or '')
                if not tgl_lahir:
                    raise ValueError(f"tgl_lahir tidak valid: '{row.get('tgl_lahir')}'")

                jk = parse_jk(row.get('jenis_kelamin') or '')

                # ── NIK ───────────────────────────────────────
                nik_raw = clean_digits(row.get('NIK') or '', 16)

                if len(nik_raw) >= 15:
                    # Standar valid (15-16 digit)
                    nik = nik_raw[:16]
                elif nik_raw:
                    # Ada konten digit tapi pendek — simpan as-is, jangan fabrikasi
                    nik = nik_raw
                    note = f"Baris {row_num} ({nama}): NIK '{row.get('NIK','').strip()}' disimpan as-is ({len(nik_raw)} digit)"
                    error_log.append('[NIK-PENDEK] ' + note)
                else:
                    # Benar-benar kosong — generate dummy
                    nik = next_dummy_nik(tgl_lahir, jk, cursor)
                    note = f"Baris {row_num} ({nama}): NIK kosong → dummy {nik}"
                    error_log.append('[NIK-DUMMY] ' + note)

                # ── Wilayah ───────────────────────────────────
                id_kec = resolve(row.get('Kec') or '', master['kec'])
                id_kel = resolve(row.get('Desa/Kel') or '', master['kel'])
                id_pkm = resolve(row.get('Puskesmas') or '', master['pkm'])
                id_pos = resolve(row.get('Posyandu') or '', master['pos'])
                id_rt  = resolve_rt(row.get('rt') or '', id_kel, master['rt'])

                # ── Susun data ─────────────────────────────────
                data = {
                    'nik'                 : nik,
                    'nama'                : nama,
                    'tgl_lahir'           : tgl_lahir,
                    'jk'                  : jk,
                    'no'                  : str(row.get('No') or '').strip() or f"IMP-{row_num}",
                    'status'              : 1,
                    'no_kk'               : clean_digits(row.get('nomor_KK') or '') or None,
                    'anak'                : parse_int(row.get('anak_ke') or ''),
                    'usia_kehamilan_lahir' : parse_int(row.get('usia_hamil') or '', max_val=60),
                    'bbl'                 : parse_bbl(row.get('berat_lahir') or ''),
                    'pbl'                 : parse_decimal(row.get('panjang_lahir') or '', max_val=70.0),
                    'lk_lahir'            : parse_decimal(row.get('lingkar_kepala_lahir') or '', max_val=60.0),
                    'imd'                 : parse_imd(row.get('imd') or ''),
                    'nama_ibu'            : (row.get('nama_ortu') or '').strip() or None,
                    'nik_ortu'            : clean_digits(row.get('nik_ortu') or '') or None,
                    'nik_ibu'             : clean_digits(row.get('nik_ortu') or '', 16) or None,
                    'no_hp'               : clean_phone(row.get('hp_ortu') or ''),
                    'alamat'              : (row.get('alamat') or '').strip() or None,
                    'id_kec'              : id_kec,
                    'id_kel'              : id_kel,
                    'id_puskesmas'        : id_pkm,
                    'id_posyandu'         : id_pos,
                    'id_rt'               : id_rt,
                }

                if not dry_run:
                    cols    = ', '.join(f"`{k}`" for k in data)
                    vals    = ', '.join(['%s'] * len(data))
                    updates = ', '.join(
                        f"`{k}` = VALUES(`{k}`)"
                        for k in data if k != 'nik'
                    )
                    sql = (
                        f"INSERT INTO `anak` ({cols}) VALUES ({vals}) "
                        f"ON DUPLICATE KEY UPDATE {updates}"
                    )
                    cursor.execute(sql, list(data.values()))

                success += 1

            except Exception as e:
                errors += 1
                error_log.append(f"[ERROR] Baris {row_num} ({nama}): {e}")

            if (row_num - 1) % CHUNK_LOG == 0:
                print(f"  ... {row_num - 1} baris diproses | ok={success} skip={skipped} err={errors}")

    if not dry_run:
        conn.commit()

    conn.close()

    # ── Ringkasan ─────────────────────────────────
    mode = "[DRY-RUN]" if dry_run else "[SELESAI]"
    print(f"\n{mode} Berhasil={success}  Skip={skipped}  Error={errors}")

    if error_log:
        log_path = os.path.join(os.path.dirname(__file__),
                                f"import_log_{datetime.now().strftime('%Y%m%d_%H%M%S')}.txt")
        with open(log_path, 'w', encoding='utf-8') as lf:
            lf.write('\n'.join(error_log))
        print(f"[LOG]  {len(error_log)} catatan ditulis ke: {log_path}")


# ─────────────────────────────────────────────
if __name__ == '__main__':
    ap = argparse.ArgumentParser(description='Import sasaran op timbang CSV ke DB sirindu')
    ap.add_argument('--csv',     default=DEFAULT_CSV, help='Path file CSV')
    ap.add_argument('--dry-run', action='store_true',  help='Simulasi tanpa tulis ke DB')
    args = ap.parse_args()

    if not os.path.exists(args.csv):
        sys.exit(f"[ERROR] File tidak ditemukan: {args.csv}")

    run(args.csv, args.dry_run)

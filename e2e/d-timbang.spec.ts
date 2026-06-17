import { test, expect } from '@playwright/test';

/**
 * Paket D — Dashboard Gizi & Timbang (6 kartu actionable, filter cascading, modal daftar)
 * + mode peta Gizi Kurang/Buruk.
 *
 * data_anak dev kosong → angka boleh "—"/0 & daftar kosong; struktur & perilaku tetap diuji.
 * Nilai status gizi (z-score) = unit test, bukan di sini.
 */
test.use({ storageState: 'e2e/.auth/superadmin.json' });

const URL = '/admin/timbang-dashboard/';

// Kartu/modal/filter di-render server-side + JS attach saat DOMContentLoaded; tak perlu
// menunggu 'load' (6 AJAX + chart CDN) yang bisa starve aksi saat FPM ramai.
const GOTO = { waitUntil: 'domcontentloaded' as const };

test('6 kartu KPI (tanpa MBG) dengan label yang benar', async ({ page }) => {
  await page.goto(URL, GOTO);

  const cards = page.locator('.tb-kpi--click');
  await expect(cards).toHaveCount(6);

  const grid = page.locator('#kpi-grid');
  for (const label of ['Balita Sasaran', 'Hadir (Ditimbang)', 'Stunting', 'Gizi Kurang', 'Gizi Buruk', 'BB Tidak Naik']) {
    await expect(grid.getByText(label, { exact: true })).toBeVisible();
  }
  // Indikator MBG sudah dihapus dari dashboard.
  await expect(grid).not.toContainText('MBG');
});

test('filter cascading: pilih Kecamatan → Kelurahan terisi otomatis', async ({ page }) => {
  await page.goto(URL, GOTO);

  // Kelurahan awal hanya placeholder sampai kecamatan dipilih.
  await page.locator('#f-kec').selectOption({ index: 1 });
  await expect
    .poll(async () => page.locator('#f-kel option').count(), { timeout: 8_000 })
    .toBeGreaterThan(1);

  // Pilih kelurahan → RT/Posyandu di-fetch (selesai tanpa error JS).
  await page.locator('#f-kel').selectOption({ index: 1 });
  await expect(page.locator('#f-posyandu')).toBeVisible();
});

test('klik kartu → modal daftar terbuka dengan search + export', async ({ page }) => {
  await page.goto(URL, GOTO);

  await page.locator('.tb-kpi--click[data-kategori="stunting"]').click();

  const modal = page.locator('#daftar-modal');
  await expect(modal).toHaveClass(/open/);
  await expect(page.locator('#daftar-title')).toHaveText('Stunting');
  await expect(page.locator('#daftar-search')).toBeVisible();
  await expect(page.locator('#daftar-export')).toHaveAttribute('href', /kategori=stunting/);

  await page.locator('#daftar-close').click();
  await expect(modal).not.toHaveClass(/open/);
});

test('peta: mode Gizi Kurang/Buruk dapat diaktifkan', async ({ page }) => {
  // Tombol "Peta Sebaran" mengarah ke /admin/map.
  await page.goto('/admin/map', GOTO);

  const btnGizi = page.getByRole('button', { name: /Gizi Kurang\/Buruk/ });
  await expect(btnGizi).toBeVisible();
  await btnGizi.click();
  await expect(btnGizi).toHaveClass(/active/);
  // Legenda tetap hadir setelah mode berganti (render ulang sukses).
  await expect(page.locator('.legend-box').first()).toBeVisible();
});

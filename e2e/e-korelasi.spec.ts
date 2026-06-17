import { test, expect } from '@playwright/test';

/**
 * Paket E — Korelasi cakupan IDL vs prevalensi stunting (dashboard imunisasi).
 *
 * Bercabang sesuai data: ada data → canvas scatter; kosong → empty-state.
 * Disclaimer "bukan kausalitas" selalu hadir. Dropdown wilayah pakai ->name
 * (regresi ->nama) → opsi harus terisi nama, bukan blank.
 */
test.use({ storageState: 'e2e/.auth/superadmin.json' });

// Halaman ini membangun status kejar imunisasi per anak; tanpa filter ia memproses
// SELURUH anak ≥12 bln (~9.7rb) dan tak selesai dalam puluhan detik (kandidat optimasi).
// Untuk E2E, scope ke satu posyandu kecil (dev DB) agar cepat & deterministik. Dropdown
// wilayah tetap berisi daftar penuh (independen filter), jadi assertion tetap valid.
const URL = '/admin/imunisasi-dashboard?id_posyandu=72';

// Konten korelasi & dropdown di-render server-side → domcontentloaded cukup; timeout longgar.
const GOTO = { waitUntil: 'domcontentloaded' as const, timeout: 70_000 };

test('card korelasi + disclaimer; scatter ATAU empty-state sesuai data', async ({ page }) => {
  test.slow();
  await page.goto(URL, GOTO);

  const card = page.locator('.card', {
    has: page.getByRole('heading', { name: 'Korelasi Cakupan IDL vs Prevalensi Stunting' }),
  });
  await expect(card).toBeVisible();
  await expect(card.getByText(/bukan kausalitas/i)).toBeVisible();

  const canvas = card.locator('#chartKorelasi');
  if (await canvas.count() > 0) {
    await expect(canvas).toBeVisible(); // ada data balita terukur
  } else {
    await expect(card.getByText(/Belum ada data balita terukur/i)).toBeVisible();
  }
});

test('dropdown wilayah terisi nama (tidak blank)', async ({ page }) => {
  test.slow();
  await page.goto(URL, GOTO);

  const kel = page.locator('#filterKel');
  await expect(kel.locator('option').first()).toHaveText('Semua Kelurahan');
  // Selain placeholder, ada opsi kelurahan ber-nama (regresi ->name terbukti).
  expect(await kel.locator('option').count()).toBeGreaterThan(1);
  await expect(page.locator('#filterKec option').nth(1)).not.toHaveText('');
});

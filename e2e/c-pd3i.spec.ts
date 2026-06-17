import { test, expect } from '@playwright/test';

/**
 * Paket C — Dashboard Surveilans PD3I.
 *
 * Fokus: regresi tab (migrasi BS5→BS4) — klik tab harus menukar panel.
 * Angka kartu = wiring AJAX hadir (bukan validasi nilai; itu unit/feature test).
 */
test.use({ storageState: 'e2e/.auth/superadmin.json' });

const URL = '/admin/epidemiologi/pd3i-dashboard/';

test('tab berpindah: klik tab → panel target tampil, lainnya tersembunyi', async ({ page }) => {
  await page.goto(URL);

  // Default: panel Kinerja aktif.
  await expect(page.locator('#tab-kinerja')).toBeVisible();
  await expect(page.locator('#tab-demografi')).toBeHidden();

  // Pindah ke Demografi.
  await page.getByRole('tab', { name: 'Demografi' }).click();
  await expect(page.locator('#tab-demografi')).toBeVisible();
  await expect(page.locator('#tab-kinerja')).toBeHidden();

  // Pindah ke Tren.
  await page.getByRole('tab', { name: 'Tren' }).click();
  await expect(page.locator('#tab-tren')).toBeVisible();
  await expect(page.locator('#tab-demografi')).toBeHidden();

  // Pindah ke Tempat lalu Peta.
  await page.getByRole('tab', { name: 'Tempat' }).click();
  await expect(page.locator('#tab-tempat')).toBeVisible();
  await page.getByRole('tab', { name: 'Peta' }).click();
  await expect(page.locator('#tab-peta')).toBeVisible();
  await expect(page.locator('#tab-tempat')).toBeHidden();
});

test('kartu Campak/Rubella/Discarded terisi via AJAX (skeleton hilang)', async ({ page }) => {
  await page.goto(URL);

  for (const id of ['#cr-kasus-campak', '#cr-kasus-rubella', '#cr-discarded']) {
    // Skeleton loader tergantikan oleh nilai → bukti data ter-fetch & ter-render.
    // AJAX-bound (4 endpoint paralel) → beri tenggat lebih longgar.
    await expect(page.locator(`${id} .skeleton`)).toHaveCount(0, { timeout: 20_000 });
    await expect(page.locator(id)).toHaveText(/\d|–/, { timeout: 20_000 });
  }
});

test('export wired: tombol Excel ber-href ke endpoint, form PDF hadir', async ({ page }) => {
  await page.goto(URL);

  // buildParams() menyetel href Excel saat load.
  await expect(page.locator('#btnExportExcel')).toHaveAttribute('href', /export-excel/i);
  await expect(page.locator('#formExportPdf')).toHaveAttribute('action', /export-pdf/i);
  await expect(page.getByRole('button', { name: /PDF/ })).toBeVisible();
});

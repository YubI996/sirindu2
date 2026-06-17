import { test, expect } from '@playwright/test';

/**
 * Paket B — Form pengukuran berkala (Tambah Data Berkala).
 *
 * Inti UI: (a) tak ada input manual `bln`, (b) posisi sbg dropdown Terlentang/Berdiri,
 * (c) alasan tidak imunisasi sbg dropdown + opsi "Lainnya" yang memunculkan input teks.
 * Kebenaran `bln` (auto dari tgl_lahir) & normalisasi posisi H/L = unit test.
 */
test.use({ storageState: 'e2e/.auth/superadmin.json' });

// Form butuh anak existing → ambil link "Tambah Data Berkala" dari daftar anak.
async function gotoFormDataBerkala(page: import('@playwright/test').Page) {
  await page.goto('/admin/data-dasar-anak');
  const link = page.locator('a[href*="/admin/data-anak/"]').first();
  await link.waitFor({ state: 'attached', timeout: 15_000 });
  const href = await link.getAttribute('href');
  expect(href, 'butuh ≥1 anak di daftar').toBeTruthy();
  await page.goto(href!);
}

test('tidak ada input manual umur (bln)', async ({ page }) => {
  await gotoFormDataBerkala(page);
  await expect(page.locator('[name="bln"]')).toHaveCount(0);
});

test('posisi = dropdown Terlentang/Berdiri (default Terlentang)', async ({ page }) => {
  await gotoFormDataBerkala(page);

  const posisi = page.locator('select#posisi');
  await expect(posisi.locator('option')).toHaveCount(2);
  await expect(posisi).toHaveValue('L'); // Terlentang default
  await expect(posisi.locator('option', { hasText: 'Terlentang' })).toHaveCount(1);
  await expect(posisi.locator('option', { hasText: 'Berdiri' })).toHaveCount(1);
});

test('alasan tidak imunisasi = dropdown + "Lainnya" memunculkan input teks', async ({ page }) => {
  await gotoFormDataBerkala(page);

  const alasan = page.locator('select#alasan_tidak_imunisasi');
  await expect(alasan.locator('option', { hasText: 'Lainnya' })).toHaveCount(1);

  // Input "Lainnya" tersembunyi sampai opsi dipilih.
  await expect(page.locator('#alasanLainWrap')).toBeHidden();
  await alasan.selectOption('Lainnya');
  await expect(page.locator('#alasanLainWrap')).toBeVisible();
  await expect(page.locator('#alasan_tidak_imunisasi_lain')).toBeVisible();
});

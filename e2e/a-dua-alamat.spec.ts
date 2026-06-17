import { test, expect } from '@playwright/test';

/**
 * Paket A — Dua alamat: Domisili (operasional) & KTP (referensi).
 *
 * Inti UI: dua seksi alamat terpisah + tombol "Samakan".
 * Round-trip simpan→tampil memerlukan tulis DB → di-gate E2E_ALLOW_WRITE (default skip)
 * agar tes idempoten & tak mengotori data.
 */
test.use({ storageState: 'e2e/.auth/superadmin.json' });

const URL = '/admin/create-data-dasar-anak';

test('form punya dua seksi alamat (Domisili & KTP) dengan field terpisah', async ({ page }) => {
  await page.goto(URL);

  await expect(page.getByRole('heading', { name: 'Alamat Domisili (operasional)' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Alamat KTP' })).toBeVisible();

  await expect(page.locator('textarea#alamat')).toBeVisible();      // domisili
  await expect(page.locator('textarea#alamat_ktp')).toBeVisible();  // KTP
});

test('tombol Samakan menyalin alamat domisili ke alamat KTP', async ({ page }) => {
  await page.goto(URL);

  const domisili = 'Jl. Mawar No. 10, RT 05';
  await page.locator('#alamat').fill(domisili);
  await expect(page.locator('#alamat_ktp')).toHaveValue('');

  await page.getByRole('button', { name: 'Samakan dengan domisili' }).click();
  await expect(page.locator('#alamat_ktp')).toHaveValue(domisili);
});

test('round-trip: simpan anak (domisili ≠ KTP) lalu tampil terpisah', async ({ page }) => {
  test.skip(process.env.E2E_ALLOW_WRITE !== '1', 'Set E2E_ALLOW_WRITE=1 untuk uji tulis DB.');
  // Implementasi pembuatan + verifikasi halaman show ditambahkan saat write-mode diaktifkan.
});

import { test, expect } from '@playwright/test';

test.use({ storageState: 'e2e/.auth/superadmin.json' });

const iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

test('label & nilai BB mengikuti tanggal kunjungan terhadap batas dari server', async ({ page }) => {
  const res = await page.request.get('/admin/kesmas-dashboard/api/registri?usia=semua');
  expect(res.ok()).toBe(true);
  const anak = (await res.json()).data[0];
  expect(anak, 'DB dev butuh minimal satu anak bertanda Sasaran Balita Kesmas').toBeTruthy();
  const hash = anak.url_detail.split('/').pop();

  await page.goto(`/admin/data-anak/${hash}`);
  const bb = page.locator('#bb');
  const label = page.locator('[data-satuan-label="bb"]');
  const batas = await bb.getAttribute('data-batas-gram');
  expect(batas).toMatch(/^\d{4}-\d{2}-\d{2}$/);
  const sebelum = new Date(`${batas}T00:00:00`);
  sebelum.setDate(sebelum.getDate() - 1);

  await page.locator('#tgl_kunjungan').fill(iso(sebelum));
  await expect(label).toHaveText('(gram)');
  await bb.fill('3250');

  await page.locator('#tgl_kunjungan').fill(batas!);
  await expect(label).toHaveText('(kg)');
  await expect(bb).toHaveValue('3.25');
  await expect(page.locator('[data-satuan-info="bb"]')).toContainText('kg');

  await page.locator('#tgl_kunjungan').fill(iso(sebelum));
  await expect(label).toHaveText('(gram)');
  await expect(bb).toHaveValue('3250');
});

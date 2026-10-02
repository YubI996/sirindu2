import { test, expect } from '@playwright/test';

test.use({ storageState: 'e2e/.auth/superadmin.json' });

const endpoint = '**/kesmas-dashboard/api/registri?**';
const row = (nama = 'Anak Uji Registri') => ({
  no: 1, id: 1, nama, nik: '0000000000000001', jk: 'L', umur_bln: 30,
  tgl_lahir: '2023-06-30', nama_ibu: 'Ibu Uji', nama_ayah: null,
  kelurahan: 'Wilayah Uji', rt: '1', posyandu: null, kunjungan: null,
  idl: 'belum', ibl: 'belum', catatan: null, url_detail: '/admin/kesmas-dashboard',
});
const result = (nama?: string) => ({ data: [row(nama)], total: 1, page: 1, last_page: 1, per_page: 20 });

test('respons pencarian lama tidak mengganti hasil terbaru', async ({ page }) => {
  let releaseOld!: () => void;
  let oldStarted!: () => void;
  let oldFinished!: () => void;
  const started = new Promise<void>(resolve => { oldStarted = resolve; });
  const held = new Promise<void>(resolve => { releaseOld = resolve; });
  const finished = new Promise<void>(resolve => { oldFinished = resolve; });
  await page.route(endpoint, async route => {
    const query = new URL(route.request().url()).searchParams.get('q');
    if (query === 'lama') {
      oldStarted();
      await held;
      try { await route.fulfill({ json: result('Hasil lama') }); }
      finally { oldFinished(); }
      return;
    }
    await route.fulfill({ json: result(query === 'baru' ? 'Hasil terbaru' : 'Anak Uji Registri') });
  });
  await page.goto('/admin/kesmas-dashboard');
  await expect(page.locator('#registriBody')).toContainText('Anak Uji Registri');
  await page.locator('#registriCari').fill('lama');
  await started;
  await page.locator('#registriCari').fill('baru');
  await expect(page.locator('#registriBody')).toContainText('Hasil terbaru');
  releaseOld();
  await finished;
  await expect(page.locator('#registriBody')).not.toContainText('Hasil lama');
  await expect(page.locator('#registriBody')).toHaveAttribute('aria-busy', 'false');
});

test('filter usia, rincian K4, paginasi, dan pemulihan galat tanpa reload', async ({ page }) => {
  let failing = false;
  const requests: URLSearchParams[] = [];
  await page.route(endpoint, async route => {
    const params = new URL(route.request().url()).searchParams;
    requests.push(params);
    if (failing) return route.fulfill({ status: 500, json: {} });
    const current = Number(params.get('page'));
    await route.fulfill({ json: { ...result('Halaman ' + current), total: 21, page: current, last_page: 2 } });
  });
  await page.goto('/admin/kesmas-dashboard');
  await expect(page.locator('#registriBody')).toContainText('Halaman 1');
  await page.evaluate(() => { document.body.dataset.navigationMarker = 'tetap'; });
  await page.getByRole('button', { name: 'Berikutnya', exact: true }).click();
  await expect(page.locator('#registriBody')).toContainText('Halaman 2');
  await page.locator('.km-chip[data-usia="bayi"]').click();
  await expect(page).toHaveURL(/usia=bayi/);
  await expect(page.locator('#sdidtkRows [data-usia="bayi"]')).toHaveClass(/hl/);
  await page.locator('#registriCari').fill('Nama terbatas');
  await expect.poll(() => requests.at(-1)?.get('q')).toBe('Nama terbatas');
  await page.locator('[data-registri-status="perhatian"]').click();
  await expect(page.locator('#registriGizi')).toHaveValue('perhatian');
  await expect.poll(() => requests.at(-1)?.get('status_gizi')).toBe('perhatian');
  expect(requests.at(-1)?.get('usia')).toBe('balita_0_59');
  expect(requests.at(-1)?.get('q')).toBe('');

  failing = true;
  await page.locator('#registriGizi').selectOption('stunted');
  await expect(page.getByRole('button', { name: 'Coba lagi' })).toBeVisible();
  await expect(page.locator('#registriPag button')).toHaveCount(0);
  await expect(page.locator('#registriTotal')).toHaveText('Belum dimuat');
  failing = false;
  await page.getByRole('button', { name: 'Coba lagi' }).click();
  await expect(page.locator('#registriBody')).toContainText('Halaman 1');
  await expect(page.locator('body')).toHaveAttribute('data-navigation-marker', 'tetap');
});

test('registri aman untuk teks HTML dan tetap di dalam layar HP', async ({ page }) => {
  await page.setViewportSize({ width: 375, height: 812 });
  const text = '<img src=x onerror=alert(1)> Anak & Orang Tua';
  await page.route(endpoint, route => route.fulfill({ json: result(text) }));
  await page.goto('/admin/kesmas-dashboard');
  await expect(page.locator('#registriBody')).toContainText(text);
  await expect(page.locator('#registriBody img')).toHaveCount(0);
  const sizes = await page.evaluate(() => {
    const wrap = document.querySelector('.km-table-wrap')!;
    return {
      viewport: innerWidth, page: document.documentElement.scrollWidth,
      table: wrap.scrollWidth, container: wrap.clientWidth,
      columns: getComputedStyle(document.querySelector('.im-cards')!).gridTemplateColumns.split(' ').length,
    };
  });
  expect(sizes.page).toBeLessThanOrEqual(sizes.viewport);
  expect(sizes.table).toBeGreaterThan(sizes.container);
  expect(sizes.columns).toBe(1);
});

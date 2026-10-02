import { test, expect, type Page } from '@playwright/test';

// Read-only integration checks against the local app. Registry data is never mocked,
// except one deliberate transport failure to exercise the retry control.
test.use({ storageState: 'e2e/.auth/superadmin.json' });
test.setTimeout(60_000);
const dashboard = '/admin/kesmas-dashboard';
const registryPath = '/kesmas-dashboard/api/registri';
const year = 2026;

test.beforeEach(async ({ page }) => {
  await page.route('**/admin/**', async route => {
    if (!['GET', 'HEAD'].includes(route.request().method())) return route.abort();
    await route.continue();
  });
});

async function load(page: Page, url = dashboard) {
  const pending = page.waitForResponse(r => r.url().includes(registryPath));
  const response = await page.goto(url);
  expect(response?.status()).toBe(200);
  const api = await pending;
  expect(api.status()).toBe(200);
  const result = await api.json();
  await expect(page.locator('#registriBody')).toHaveAttribute('aria-busy', 'false');
  return result;
}

async function registryAction(page: Page, action: () => Promise<unknown>) {
  const pending = page.waitForResponse(r => r.url().includes(registryPath));
  await action();
  const response = await pending;
  expect(response.status()).toBe(200);
  const result = await response.json();
  await expect(page.locator('#registriBody')).toHaveAttribute('aria-busy', 'false');
  return { result, params: new URL(response.url()).searchParams };
}

const periods = [
  ['tahun', 'Tahun 2026', 8, 2],
  ['s1', 'Semester I 2026 (1 Jan–30 Jun)', 4, 1],
  ['s2', 'Semester II 2026 (1 Jul–31 Des)', 4, 1],
  ['tw1', 'Triwulan I 2026 (1 Jan–31 Mar)', 2, 1],
  ['tw2', 'Triwulan II 2026 (1 Apr–30 Jun)', 2, 1],
  ['tw3', 'Triwulan III 2026 (1 Jul–30 Sep)', 2, 1],
  ['tw4', 'Triwulan IV 2026 (1 Okt–31 Des)', 2, 1],
] as const;

for (const [period, label, weighings, screenings] of periods) {
  test(`periode nyata ${period}: label, prorata, JSON dan tahun imunisasi`, async ({ page }) => {
    const exceptions: string[] = [];
    page.on('pageerror', error => exceptions.push(error.message));
    const result = await load(page, `${dashboard}?tahun=${year}&periode=${period}`);
    await expect(page.locator('.km-head .km-sub')).toContainText(label);
    await expect(page.locator('[data-blok="spm-balita"] .km-foot')).toContainText(`${weighings}× timbang · ${screenings}× DDTKA`);
    await expect(page.locator('#filterPeriode')).toHaveValue(period);
    expect(result.per_page).toBe(20);
    expect(result.data.length).toBeLessThanOrEqual(20);
    expect(result.data.every((row: { umur_bln: number }) => row.umur_bln >= 0 && row.umur_bln <= 72)).toBe(true);
    const link = await page.locator('[data-blok="idl"] a').getAttribute('href');
    expect(new URL(link!).searchParams.get('tahun')).toBe(String(year));
    expect(exceptions).toEqual([]);
  });
}

test('cascade Kec/Kel/RT serta Posyandu/Puskesmas bertahan setelah Terapkan', async ({ page }) => {
  await load(page);
  const kel = await page.locator('#filterKel option[data-kec]').first().evaluate(el => ({ value: (el as HTMLOptionElement).value, kec: el.getAttribute('data-kec')! }));
  await page.locator('#filterKec').selectOption(kel.kec);
  await page.locator('#filterKel').selectOption(kel.value);
  await expect.poll(() => page.locator('#filterRt option').count()).toBeGreaterThan(1);
  const rt = await page.locator('#filterRt option').nth(1).getAttribute('value');
  const pos = await page.locator('#filterPos option').nth(1).getAttribute('value');
  const pkm = await page.locator('#filterPkm option').nth(1).getAttribute('value');
  await page.locator('#filterRt').selectOption(rt!);
  await page.locator('#filterPos').selectOption(pos!);
  await page.locator('#filterPkm').selectOption(pkm!);
  const loaded = await registryAction(page, () => page.getByRole('button', { name: 'Terapkan' }).click());
  for (const [id, expected, param] of [
    ['filterKec', kel.kec, 'id_kecamatan'], ['filterKel', kel.value, 'id_kelurahan'],
    ['filterRt', rt!, 'id_rt'], ['filterPos', pos!, 'id_posyandu'], ['filterPkm', pkm!, 'id_puskesmas'],
  ]) {
    await expect(page.locator(`#${id}`)).toHaveValue(expected);
    expect(loaded.params.get(param)).toBe(expected);
  }
  const otherKec = await page.locator(`#filterKec option:not([value=""]):not([value="${kel.kec}"])`).first().getAttribute('value');
  await page.locator('#filterKec').selectOption(otherKec!);
  await expect(page.locator('#filterKel')).toHaveValue('');
  await expect(page.locator('#filterRt option')).toHaveCount(1);
  await page.getByRole('link', { name: 'Reset', exact: true }).click();
  await expect(page.locator('#filterKec')).toHaveValue('');
  await expect(page.locator('#filterPos')).toHaveValue('');
  await expect(page.locator('#filterPkm')).toHaveValue('');
});

test('registri nyata: paginasi, enam usia, pencarian nama/NIK/orang tua, enam status dan K4', async ({ page }) => {
  test.setTimeout(180_000);
  const initial = await load(page);
  expect(initial.total).toBeGreaterThan(20);
  await page.evaluate(() => { document.body.dataset.swarmNavigation = 'unchanged'; });
  const next = await registryAction(page, () => page.getByRole('button', { name: 'Berikutnya', exact: true }).click());
  expect(next.result.page).toBe(2);
  expect(next.result.data.some((r: { id: number }) => initial.data.some((i: { id: number }) => i.id === r.id))).toBe(false);
  await registryAction(page, () => page.getByRole('button', { name: 'Sebelumnya', exact: true }).click());

  for (const [age, min, max] of [['bayi', 0, 11], ['baduta', 12, 23], ['balita', 24, 59], ['prasekolah', 60, 72], ['balita_0_59', 0, 59], ['semua', 0, 72]] as const) {
    const current = await registryAction(page, () => page.locator(`.km-chip[data-usia="${age}"]`).click());
    expect(current.params.get('usia')).toBe(age);
    expect(current.result.data.every((r: { umur_bln: number }) => r.umur_bln >= min && r.umur_bln <= max)).toBe(true);
    await expect(page.locator(`.km-chip[data-usia="${age}"]`)).toHaveAttribute('aria-pressed', 'true');
    if (age !== 'semua' && age !== 'balita_0_59') await expect(page.locator(`#sdidtkRows [data-usia="${age}"]`)).toHaveClass(/hl/);
  }
  const sample = initial.data.find((r: { nama: string; nik: string; nama_ibu: string; nama_ayah: string }) => r.nama && r.nik && r.nama_ibu && r.nama_ayah);
  expect(Boolean(sample)).toBe(true);
  for (const field of ['nama', 'nik', 'nama_ibu', 'nama_ayah']) {
    const found = await registryAction(page, () => page.locator('#registriCari').fill(sample[field]));
    expect(found.result.data.some((r: { id: number }) => r.id === sample.id)).toBe(true);
  }
  const empty = await registryAction(page, () => page.locator('#registriCari').fill('swarm-no-matching-child-729430'));
  expect(empty.result.total).toBe(0);
  await expect(page.locator('#registriBody')).toContainText('Tidak ada balita yang cocok');
  await registryAction(page, () => page.locator('#registriCari').fill(''));
  for (const status of ['normal', 'stunted', 'underweight', 'wasted', 'perhatian', 'semua']) {
    const filtered = await registryAction(page, () => page.locator('#registriGizi').selectOption(status));
    expect(filtered.params.get('status_gizi')).toBe(status);
    if (status !== 'semua') expect(filtered.result.data.every((r: { kunjungan: unknown }) => r.kunjungan !== null)).toBe(true);
  }
  await registryAction(page, () => page.locator('.km-chip[data-usia="bayi"]').click());
  await registryAction(page, () => page.locator('#registriCari').fill('swarm-no-matching-child-729430'));
  const attentionCount = Number((await page.locator('[data-registri-status="perhatian"]').innerText()).match(/^[\d.]+/)![0].replaceAll('.', ''));
  const attention = await registryAction(page, () => page.locator('[data-registri-status="perhatian"]').click());
  expect(attention.params.get('q')).toBe('');
  expect(attention.params.get('usia')).toBe('balita_0_59');
  expect(attention.result.total).toBe(attentionCount);
  await expect(page.locator('body')).toHaveAttribute('data-swarm-navigation', 'unchanged');
});

test('registri pulih dari kegagalan jaringan ke respons backend nyata', async ({ page }) => {
  await load(page);
  await page.route('**/kesmas-dashboard/api/registri?**', route => route.fulfill({ status: 503, json: {} }), { times: 1 });
  await page.locator('#registriGizi').selectOption('stunted');
  await expect(page.getByRole('button', { name: 'Coba lagi' })).toBeVisible();
  await expect(page.locator('#registriPag button')).toHaveCount(0);
  await registryAction(page, () => page.getByRole('button', { name: 'Coba lagi' }).click());
  await expect(page.locator('#registriTotal')).toContainText('Total:');
  await expect(page.getByRole('button', { name: 'Coba lagi' })).toHaveCount(0);
});

for (const width of [1440, 375]) {
  test(`dashboard nyata lebar ${width}: tanpa overflow halaman dan seluruh kontrol dapat dipakai`, async ({ page }) => {
    await page.setViewportSize({ width, height: 900 });
    await load(page);
    const layout = await page.evaluate(() => {
      const table = document.querySelector('.km-table-wrap')!;
      return { page: document.documentElement.scrollWidth, viewport: innerWidth, table: table.scrollWidth, container: table.clientWidth, columns: getComputedStyle(document.querySelector('.im-cards')!).gridTemplateColumns.split(' ').length };
    });
    expect(layout.page).toBeLessThanOrEqual(layout.viewport);
    if (width === 375) {
      expect(layout.columns).toBe(1);
      expect(layout.table).toBeGreaterThan(layout.container);
    }
    await registryAction(page, () => page.locator('.km-chip[data-usia="baduta"]').click());
    await registryAction(page, () => page.locator('#registriCari').fill('swarm-empty-729430'));
    await expect(page.locator('#registriBody')).toContainText('Tidak ada balita');
  });
}

test('tautan imunisasi membuka tahun dan wilayah yang dipilih', async ({ page }) => {
  await load(page);
  const kec = await page.locator('#filterKec option').nth(1).getAttribute('value');
  await load(page, `${dashboard}?tahun=2025&periode=tw2&id_kecamatan=${kec}`);
  await page.locator('[data-blok="idl"] a').click();
  expect(new URL(page.url()).searchParams.get('tahun')).toBe('2025');
  expect(new URL(page.url()).searchParams.get('id_kecamatan')).toBe(kec);
  await expect(page.locator('.im-page')).toBeVisible();
  await expect(page.locator('.im-card')).toHaveCount(8);
});

test('Export Data mempertahankan filter wilayah dari dashboard sampai form unduhan', async ({ page }) => {
  await load(page);
  const kel = await page.locator('#filterKel option[data-kec]').first().evaluate(el => ({ value: (el as HTMLOptionElement).value, kec: el.getAttribute('data-kec')! }));
  const pkm = await page.locator('#filterPkm option').nth(1).getAttribute('value');
  const pos = await page.locator('#filterPos option').nth(1).getAttribute('value');
  await load(page, `${dashboard}?id_kecamatan=${kel.kec}&id_kelurahan=${kel.value}&id_puskesmas=${pkm}&id_posyandu=${pos}`);
  await page.locator('.km-head a').click();
  await expect(page.getByRole('heading', { name: 'Export Kesmas' })).toBeVisible();
  await expect.soft(page.locator('#kec')).toHaveValue(kel.kec);
  await expect.soft(page.locator('#kel')).toHaveValue(kel.value);
  await expect.soft(page.locator('#puskesmas')).toHaveValue(pkm!);
  await expect.soft(page.locator('#posyandu')).toHaveValue(pos!);
});

test('Export Kesmas: cascade manual dan unduhan Excel valid', async ({ page }) => {
  const response = await page.goto('/admin/export-kesmas');
  expect(response?.status()).toBe(200);
  const kec = await page.locator('#kec option').nth(1).getAttribute('value');
  await page.locator('#kec').selectOption(kec!);
  await expect.poll(() => page.locator('#kel option').count()).toBeGreaterThan(1);
  await expect.poll(() => page.locator('#puskesmas option').count()).toBeGreaterThan(1);
  await page.locator('#kel').selectOption({ index: 1 });
  await page.locator('#puskesmas').selectOption({ index: 1 });
  await expect.poll(() => page.locator('#posyandu option').count()).toBeGreaterThan(1);
  await page.locator('#posyandu').selectOption({ index: 1 });
  await page.locator('#dari').fill('2026-01-01');
  await page.locator('#sampai').fill('2026-09-30');
  const pending = page.waitForEvent('download');
  await page.getByRole('button', { name: 'Unduh Excel' }).click();
  const download = await pending;
  expect(download.suggestedFilename()).toMatch(/^kesmas-.+\.xlsx$/);
  expect(await download.failure()).toBeNull();
  const stream = await download.createReadStream();
  const chunks: Buffer[] = [];
  for await (const chunk of stream!) chunks.push(chunk);
  const content = Buffer.concat(chunks);
  expect(content.subarray(0, 2).toString()).toBe('PK');
  expect(content.length).toBeGreaterThan(1000);
});

async function checkPanel(page: Page, id: string) {
  const panel = page.locator(`[id="${id}"]`);
  await expect(panel).toBeHidden();
  await page.locator(`[data-target="#${id}"]`).click();
  await expect(panel).toBeVisible();
  await expect(panel.locator('[required], [min], [max]')).toHaveCount(0);
  const labelsValid = await panel.locator('label[for]').evaluateAll(labels => labels.every(label => {
    const id = label.getAttribute('for');
    return id && document.querySelectorAll(`[id="${CSS.escape(id)}"]`).length === 1;
  }));
  expect(labelsValid).toBe(true);
}

test('Tambah Anak: dua kartu Kesmas tertutup default, label valid, boolean tiga keadaan', async ({ page }) => {
  expect((await page.goto('/admin/create-data-dasar-anak'))?.status()).toBe(200);
  await checkPanel(page, 'kartuKesmas');
  await checkPanel(page, 'kartuRiwayatLahir');
  for (const name of ['air_bersih', 'jamban_sehat', 'merokok_keluarga', 'riwayat_kek_ibu', 'imd']) {
    const select = page.locator(`select[name="${name}"]`);
    expect(await select.locator('option').evaluateAll(xs => xs.map(x => (x as HTMLOptionElement).value))).toEqual(['', '1', '0']);
    await select.selectOption('0');
    await expect(select).toHaveValue('0');
    await select.selectOption('');
    await expect(select).toHaveValue('');
  }
});

test('Detail/Edit Anak dan Tambah Kunjungan: kartu dan checkbox Kesmas independen', async ({ page }) => {
  const data = await load(page);
  expect(data.data.length).toBeGreaterThan(0);
  // Locate a dev child with multiple visits using read-only HTML requests.
  let editUrl = '';
  let detailUrl = '';
  for (const child of data.data.slice(0, 10)) {
    const candidate = child.url_detail.replace('show-data-dasar-anak', 'edit-data-dasar-anak');
    const response = await page.request.get(candidate);
    expect(response.status()).toBe(200);
    if (((await response.text()).match(/id="k\d+_kartuLayanan"/g) ?? []).length >= 2) { editUrl = candidate; detailUrl = child.url_detail; break; }
  }
  expect(Boolean(editUrl), 'Fixture lokal membutuhkan anak dengan minimal dua kunjungan').toBe(true);
  expect((await page.goto(detailUrl))?.status()).toBe(200);
  await expect(page.getByText('Kesmas & Lingkungan', { exact: true })).toBeVisible();
  expect((await page.goto(editUrl))?.status()).toBe(200);
  await checkPanel(page, 'kartuKesmas');
  await checkPanel(page, 'kartuRiwayatLahir');
  const ids = await page.locator('[id$="_kartuLayanan"]').evaluateAll(xs => xs.map(x => x.id));
  expect(ids.length).toBeGreaterThanOrEqual(2);
  await checkPanel(page, ids[0]);
  await expect(page.locator(`[id="${ids[1]}"]`)).toBeHidden();
  await checkPanel(page, ids[1]);
  const first = page.locator(`[id="${ids[0]}"] input[type="checkbox"]`).first();
  const second = page.locator(`[id="${ids[1]}"] input[type="checkbox"]`).first();
  const secondBefore = await second.isChecked();
  await first.setChecked(!(await first.isChecked()));
  expect(await second.isChecked()).toBe(secondBefore);
  const hash = detailUrl.split('/').pop();
  expect((await page.goto(`/admin/data-anak/${hash}`))?.status()).toBe(200);
  await checkPanel(page, 'kartuLayanan');
  const pairsValid = await page.locator('#kartuLayanan input[type="checkbox"]').evaluateAll(xs => xs.every(x => {
    const checkbox = x as HTMLInputElement;
    return checkbox.value === '1' && checkbox.parentElement?.querySelector(`input[type="hidden"][name="${checkbox.name}"]`)?.getAttribute('value') === '0';
  }));
  expect(pairsValid).toBe(true);
});

test('Form kunjungan menjelaskan CKG sebagai Cek Kesehatan Gratis', async ({ page }) => {
  const data = await load(page);
  expect(data.data.length).toBeGreaterThan(0);
  const hash = data.data[0].url_detail.split('/').pop();
  expect((await page.goto(`/admin/data-anak/${hash}`))?.status()).toBe(200);
  await checkPanel(page, 'kartuLayanan');
  await expect(page.locator('label[for="tgl_penanda_ckg"]')).toContainText(/cek kesehatan gratis/i);
});

test('cascade RT mengabaikan respons kelurahan lama setelah pilihan diganti cepat', async ({ page }) => {
  await load(page);
  const kelIds = await page.locator('#filterKel option[data-kec]').evaluateAll(xs => xs.slice(0, 2).map(x => (x as HTMLOptionElement).value));
  expect(kelIds.length).toBe(2);
  let releaseOld!: () => void;
  let oldStarted!: () => void;
  let oldFinished!: () => void;
  const held = new Promise<void>(resolve => { releaseOld = resolve; });
  const started = new Promise<void>(resolve => { oldStarted = resolve; });
  const finished = new Promise<void>(resolve => { oldFinished = resolve; });
  await page.route('**/admin/get-rt-by-kel-anak/*', async route => {
    if (route.request().url().endsWith('/' + kelIds[0])) {
      oldStarted();
      await held;
      try { await route.fulfill({ json: { '111111': 'RT Kelurahan Lama' } }); }
      finally { oldFinished(); }
      return;
    }
    await route.fulfill({ json: { '222222': 'RT Kelurahan Baru' } });
  });
  await page.locator('#filterKel').selectOption(kelIds[0]);
  await started;
  await page.locator('#filterKel').selectOption(kelIds[1]);
  await expect(page.locator('#filterRt')).toContainText('RT Kelurahan Baru');
  releaseOld();
  await finished;
  await page.waitForLoadState('networkidle');
  await expect(page.locator('#filterKel')).toHaveValue(kelIds[1]);
  await expect(page.locator('#filterRt')).toContainText('RT Kelurahan Baru');
  await expect(page.locator('#filterRt')).not.toContainText('RT Kelurahan Lama');
});

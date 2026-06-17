import { test, expect } from '@playwright/test';

/**
 * Paket F — Menu Dashboard tunggal + quicklink beranda (config-driven, role-aware).
 *
 * Verifikasi UI:
 *  - Sidebar: satu grup "Dashboard" dengan jumlah item sesuai role.
 *  - Ikon sidebar ter-render (font ikon teraplikasi, ukuran > 0) — bukan display:none.
 *    (Catatan jujur: E2E tak memastikan glyph benar/bukan "tofu"; itu ranah audit visual.
 *     Di sini dipastikan ikon hadir & font ikon termuat.)
 *  - Beranda: jumlah quicklink terlihat = jumlah tercentang di modal Kelola.
 *  - Modal Kelola: uncheck 2 → simpan → reload → berkurang 2, lalu dipulihkan.
 *  - Role-aware: superadmin (11 kandidat), imunisasi (6), surveilans sidebar (2 item).
 *
 * Anti-tamper key palsu = Feature test (updateQuicklinks), bukan di sini.
 */

// ─────────────────────────────────────────────────────────────
test.describe('Superadmin', () => {
  // Viewport lebar → sidebar on-canvas (di bawahnya template menyembunyikan sidebar).
  test.use({ storageState: 'e2e/.auth/superadmin.json', viewport: { width: 1600, height: 900 } });

  test('sidebar: satu grup Dashboard berisi 7 item, ikon ter-render', async ({ page }) => {
    await page.goto('/admin/home');

    const sidebar = page.locator('.left-side-bar');
    const group = sidebar.locator('li.section-group').filter({
      has: page.locator('a.section-toggle', { hasText: 'Dashboard' }),
    });
    await expect(group).toHaveCount(1);

    // 7 item submenu (count = DOM, tahan terhadap accordion collapse).
    const submenuLinks = group.locator('ul.submenu a');
    await expect(submenuLinks).toHaveCount(7);
    for (const label of [
      'Imunisasi', 'Gizi & Timbang', 'Surveilans PD3I', 'Surveilans (legacy)',
      'Peta Statistik', 'Peta Sebaran', 'Proyeksi',
    ]) {
      await expect(group.locator('ul.submenu a', { hasText: label })).toHaveCount(1);
    }

    // Ikon hadir & font ikon teraplikasi (regresi tofu — verifikasi font, bukan glyph).
    const icon = group.locator('a.section-toggle .micon').first();
    const box = await icon.boundingBox();
    expect(box?.width ?? 0).toBeGreaterThan(0);
    const fontFamily = await icon.evaluate((el) => getComputedStyle(el).fontFamily);
    expect(fontFamily.toLowerCase()).toContain('fontawesome');
  });

  test('beranda: quicklink terlihat = jumlah tercentang; modal punya 11 kandidat', async ({ page }) => {
    await page.goto('/admin/home');

    const checkboxes = page.locator('#modalKelolaQuicklink input[name="keys[]"]');
    await expect(checkboxes).toHaveCount(11); // superadmin melihat seluruh kandidat

    const checkedCount = await checkboxes.evaluateAll(
      (els) => els.filter((e) => (e as HTMLInputElement).checked).length,
    );
    await expect(page.locator('nav.srd-quicklinks a.srd-ql')).toHaveCount(checkedCount);
  });

  test('modal Kelola: uncheck 2 → simpan → reload → berkurang 2, lalu dipulihkan', async ({ page }) => {
    await page.goto('/admin/home');

    const visible = page.locator('nav.srd-quicklinks a.srd-ql');
    const before = await visible.count();
    test.skip(before < 2, 'Butuh ≥2 quicklink aktif untuk skenario ini.');

    // Ambil 2 key yang sedang tercentang (snapshot nilai, bukan locator dinamis).
    await page.locator('button.srd-ql-manage').click();
    await expect(page.locator('#modalKelolaQuicklink')).toBeVisible();
    const checkedKeys: string[] = await page
      .locator('#modalKelolaQuicklink input[name="keys[]"]:checked')
      .evaluateAll((els) => els.map((e) => (e as HTMLInputElement).value));
    const toRestore = checkedKeys.slice(0, 2);

    for (const key of toRestore) {
      await page.locator(`#modalKelolaQuicklink input[value="${key}"]`).uncheck();
    }
    await page.getByRole('button', { name: 'Simpan' }).click();
    await expect(visible).toHaveCount(before - 2); // reload otomatis (Swal/timer)

    // Pulihkan agar tes idempoten.
    await page.locator('button.srd-ql-manage').click();
    await expect(page.locator('#modalKelolaQuicklink')).toBeVisible();
    for (const key of toRestore) {
      await page.locator(`#modalKelolaQuicklink input[value="${key}"]`).check();
    }
    await page.getByRole('button', { name: 'Simpan' }).click();
    await expect(visible).toHaveCount(before);
  });
});

// ─────────────────────────────────────────────────────────────
test.describe('Imunisasi faskes', () => {
  test.use({ storageState: 'e2e/.auth/imunisasi.json' });

  test('beranda: hanya 6 kandidat quicklink', async ({ page }) => {
    await page.goto('/admin/home');
    await expect(page.locator('#modalKelolaQuicklink input[name="keys[]"]')).toHaveCount(6);
  });
});

// ─────────────────────────────────────────────────────────────
test.describe('Surveilans (Puskesmas)', () => {
  test.use({ storageState: 'e2e/.auth/surveilans.json', viewport: { width: 1600, height: 900 } });

  test('sidebar: grup Dashboard berisi 2 item (tanpa item khas superadmin)', async ({ page }) => {
    await page.goto('/admin/epidemiologi/dashboard');

    const sidebar = page.locator('.left-side-bar');
    const group = sidebar.locator('li.section-group').filter({
      has: page.locator('a.section-toggle', { hasText: 'Dashboard' }),
    });
    await expect(group).toHaveCount(1);

    const submenuLinks = group.locator('ul.submenu a');
    await expect(submenuLinks).toHaveCount(2);
    await expect(group.locator('ul.submenu a', { hasText: 'Surveilans' })).toHaveCount(1);
    await expect(group.locator('ul.submenu a', { hasText: 'Peta Sebaran' })).toHaveCount(1);
    // Tidak ada item khas superadmin.
    await expect(sidebar.locator('ul.submenu a', { hasText: 'Proyeksi' })).toHaveCount(0);
  });
});

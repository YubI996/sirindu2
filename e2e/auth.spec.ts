import { test, expect, type Page } from '@playwright/test';

/**
 * Lintas-paket — alur login.
 * Berjalan LOGGED OUT (abaikan storageState role).
 *
 * Catatan: login butuh reCAPTCHA dibypass di server env-testing
 * (lihat global.setup.ts). Spec ini menguji perilaku form, bukan reCAPTCHA itu sendiri.
 */
test.use({ storageState: { cookies: [], origins: [] } });

const PASSWORD = 'Sirindu@2026';

async function submitLogin(page: Page, email: string, password: string): Promise<void> {
  await page.locator('#email').fill(email);
  await page.locator('#password').fill(password);
  // Lewati listener grecaptcha JS — submit form native dengan token dummy.
  await page.evaluate(() => {
    const t = document.getElementById('g-recaptcha-response') as HTMLInputElement | null;
    if (t) t.value = 'e2e-bypass';
    (document.getElementById('login-form') as HTMLFormElement).submit();
  });
}

test('login valid (superadmin) → masuk ke area admin', async ({ page }) => {
  await page.goto('/login');
  await submitLogin(page, 'dinkes@sirindu.go.id', PASSWORD);

  await page.waitForURL(/\/admin\//);
  await expect(page).not.toHaveURL(/\/login/);
  // Sidebar admin hadir → bukti sesi terautentikasi.
  await expect(page.locator('.left-side-bar')).toBeVisible();
});

test('login gagal (password salah) → tetap di /login', async ({ page }) => {
  await page.goto('/login');
  await submitLogin(page, 'dinkes@sirindu.go.id', 'password-salah');

  await page.waitForURL(/\/login/);
  await expect(page).toHaveURL(/\/login/);
  // Form login masih tampil (tidak masuk ke admin).
  await expect(page.getByLabel('Email')).toBeVisible();
  await expect(page.locator('.left-side-bar')).toHaveCount(0);
});

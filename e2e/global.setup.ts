import { test as setup, expect, type Page } from '@playwright/test';

/**
 * Auth setup — login tiap role SEKALI, simpan storageState.
 *
 * PRASYARAT (lihat catatan saat menjalankan): login form memakai reCAPTCHA v3
 * (invisible, score-based) yang DIVERIFIKASI server di LoginController. Tanpa
 * bypass, login terprogram akan ditolak. Setup ini meng-submit form secara
 * native (lewat JS grecaptcha) dengan token dummy, jadi HANYA berhasil bila
 * verifikasi reCAPTCHA dilewati di env test (RECAPTCHA_ENABLED=false + guard
 * di LoginController). Lihat ringkasan FASE 1.
 */

const PASSWORD = 'Sirindu@2026';

const ROLES = [
  { name: 'superadmin', email: 'dinkes@sirindu.go.id',                   file: 'e2e/.auth/superadmin.json' },
  { name: 'surveilans', email: 'puskesmas.bontangutara1@sirindu.go.id', file: 'e2e/.auth/surveilans.json' },
  { name: 'imunisasi',  email: 'imunisasi.faskes@sirindu.go.id',        file: 'e2e/.auth/imunisasi.json'  },
] as const;

async function login(page: Page, email: string, password: string): Promise<void> {
  await page.goto('/login');

  await page.locator('#email').fill(email);
  await page.locator('#password').fill(password);

  // Lewati listener JS yang memanggil grecaptcha.execute(): set token dummy lalu
  // panggil form.submit() native. Token tak diverifikasi bila reCAPTCHA di-bypass.
  await page.evaluate(() => {
    const token = document.getElementById('g-recaptcha-response') as HTMLInputElement | null;
    if (token) token.value = 'e2e-bypass';
    (document.getElementById('login-form') as HTMLFormElement).submit();
  });

  // Login sukses → keluar dari /login menuju area /admin/*.
  await page.waitForURL(/\/admin\//, { timeout: 15_000 });
  await expect(page).not.toHaveURL(/\/login/);
}

for (const role of ROLES) {
  setup(`authenticate ${role.name}`, async ({ page }) => {
    await login(page, role.email, PASSWORD);
    await page.context().storageState({ path: role.file });
  });
}

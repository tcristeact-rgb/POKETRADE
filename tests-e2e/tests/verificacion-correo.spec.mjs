// Verificación del correo por código (spec 2026-09-19-verificacion-de-correo).
//
// En local la API tiene MAIL_MAILER=log: el correo no se envía, se escribe en
// api/storage/logs/laravel.log, y de ahí lee el test el código de 6 dígitos.
// El usuario de prueba se borra antes de empezar (globalSetup) para que la
// ejecución sea repetible.
import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

export const EMAIL_PRUEBA = 'e2e-verificacion@poketrade.local';
const PASSWORD = 'secreta123';
const LOG = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../api/storage/logs/laravel.log');

// El código del último correo escrito en el log a partir de una posición
async function codigoDelLog(desde) {
  await expect.poll(() => (fs.existsSync(LOG) ? fs.statSync(LOG).size : 0), { timeout: 10_000 }).toBeGreaterThan(desde);
  const nuevo = fs.readFileSync(LOG, 'utf8').slice(desde);
  const m = nuevo.match(/^(\d{6})\r?$/m);   // la versión en texto plano lleva el código en una línea sola
  expect(m, 'no aparece un código de 6 dígitos en el log').not.toBeNull();
  return m[1];
}

const tamanoLog = () => (fs.existsSync(LOG) ? fs.statSync(LOG).size : 0);

test.describe('verificación de correo', () => {

  test('registrarse pide el código, el código del correo verifica y entonces se puede entrar', async ({ page }) => {
    const antes = tamanoLog();

    await page.goto('/pages/registro.html');
    await page.fill('#nombre', 'E2E');
    await page.fill('#apellido', 'Verificación');
    await page.fill('#email', EMAIL_PRUEBA);
    await page.fill('#password', PASSWORD);
    await page.fill('#confirmar', PASSWORD);
    await page.click('#form-registro button[type="submit"]');

    // A la página del código, con el email puesto
    await expect(page).toHaveURL(/\/pages\/verificar\.html\?email=/);
    await expect(page.locator('#email')).toHaveValue(EMAIL_PRUEBA);
    await expect(page.locator('#codigo')).toHaveAttribute('autocomplete', 'one-time-code');

    // Un código erróneo no sale de la página y lo dice
    await page.fill('#codigo', '000000');
    await page.click('#form-verificar button[type="submit"]');
    await expect(page.locator('#error-mensaje')).not.toBeEmpty();
    await expect(page).toHaveURL(/verificar\.html/);

    // El código real, del correo escrito en el log
    const codigo = await codigoDelLog(antes);
    await page.fill('#codigo', codigo);
    await page.click('#form-verificar button[type="submit"]');

    await expect(page).toHaveURL(/\/pages\/login\.html\?verificado=ok$/);
    await expect(page.locator('#aviso-registro')).toBeVisible();

    // Y ya se puede iniciar sesión
    await page.fill('#email', EMAIL_PRUEBA);
    await page.fill('#password', PASSWORD);
    await page.click('#form-login button[type="submit"]');
    await expect(page).toHaveURL(/\/(index\.html)?$/);
    await expect(page.locator('#menu-usuario .btn-dropdown-nombre')).toHaveText('E2E');
  });

  test('iniciar sesión con una cuenta sin verificar lleva a la página del código', async ({ page }) => {
    // Cuenta nueva sin verificar, creada por la API directamente
    const email = 'e2e-sin-verificar@poketrade.local';
    const res = await page.request.post('http://127.0.0.1:8000/api/auth/registro', {
      data: { nombre: 'Sin', apellido: 'Verificar', email, password: PASSWORD },
      headers: { Accept: 'application/json' },
    });
    expect([201, 422]).toContain(res.status());   // 422 = ya existía de una ejecución anterior

    await page.goto('/pages/login.html');
    await page.fill('#email', email);
    await page.fill('#password', PASSWORD);
    await page.click('#form-login button[type="submit"]');

    await expect(page).toHaveURL(/\/pages\/verificar\.html\?email=.*&motivo=login$/);
    await expect(page.locator('#aviso-motivo')).toBeVisible();
    expect(await page.evaluate(() => localStorage.getItem('token'))).toBeNull();
  });
});

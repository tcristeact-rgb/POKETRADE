// Detalle resumido de una carta desde el inventario
// (spec 2026-09-19-detalle-carta-en-inventario). Usa la cuenta de demo del
// seeder, que en la BD de desarrollo tiene cartas en el inventario.
import { test, expect } from '@playwright/test';

const DEMO = { email: 'teo@poketrade.es', password: '12345678' };

async function entrar(page) {
  await page.goto('/pages/login.html');
  await page.fill('#email', DEMO.email);
  await page.fill('#password', DEMO.password);
  await page.click('#form-login button[type="submit"]');
  await expect(page.locator('#menu-usuario .btn-dropdown-nombre')).toHaveText('Teo');
}

test.describe('inventario', () => {

  test('cada carta abre un modal resumido con sus datos, sin pedir nada a la API', async ({ page }) => {
    await entrar(page);
    await page.goto('/pages/inventario.html');

    const tarjetas = page.locator('.carta-inventario');
    await expect(tarjetas.first()).toBeVisible();
    expect(await tarjetas.count()).toBeGreaterThan(0);

    // El botón que abre el detalle lleva el nombre en su aria-label, y el de
    // eliminar no está dentro (ningún control anidado)
    const abrir = tarjetas.first().locator('.carta-inventario-abrir');
    const nombre = await tarjetas.first().locator('h3').textContent();
    await expect(abrir).toHaveAttribute('aria-label', `Ver detalles de ${nombre}`);
    expect(await abrir.locator('.btn-eliminar').count()).toBe(0);

    const peticiones = [];
    page.on('request', (r) => { if (r.url().includes('/api/')) peticiones.push(r.url()); });

    await abrir.click();

    const modal = page.locator('#modal-detalle-inv');
    await expect(modal).toBeVisible();
    await expect(modal).toHaveAttribute('role', 'dialog');
    await expect(modal.locator('#detalle-inv-titulo')).toHaveText(nombre);
    await expect(modal.locator('.detalle-inv-cantidad')).toContainText('En tu inventario:');
    expect(await modal.locator('.detalle-inv-atributos dt').count()).toBeGreaterThan(0);
    await expect(modal.locator('#detalle-inv-ficha')).toHaveAttribute('href', /^detalle-carta\.html\?id=\d+$/);
    expect(peticiones).toEqual([]);

    // Escape cierra y el foco vuelve a la tarjeta que lo abrió
    await page.keyboard.press('Escape');
    await expect(modal).toBeHidden();
    expect(await page.evaluate(() => document.activeElement?.className)).toBe('carta-inventario-abrir');
  });
});

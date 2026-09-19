// Portada y registro (spec 2026-09-19-portada-destacadas-aleatorias-y-registro-
// sin-nacionalidad): el carrusel infinito de cartas destacadas al azar y el
// formulario de registro sin nacionalidad. Depende de la BD de desarrollo con
// más de 4 cartas con imagen.
import { test, expect } from '@playwright/test';

test.describe('portada', () => {

  test('la sección "Cartas destacadas" es un carrusel sin barra y con flechas', async ({ page }) => {
    await page.goto('/');

    await expect(page.locator('#titulo-destacadas')).toHaveText('Cartas destacadas');

    const pista    = page.locator('#pista-destacadas');
    const tarjetas = pista.locator('.carta-card:not(.skeleton)');
    await expect(tarjetas.first()).toBeVisible();
    expect(await tarjetas.count()).toBeGreaterThan(4);
    await expect(pista).toHaveAttribute('aria-busy', 'false');

    // Sin barra de scroll: la ventana recorta, no desplaza
    const overflow = await page.locator('.carrusel-cartas-ventana').evaluate(el => getComputedStyle(el).overflowX);
    expect(['auto', 'scroll']).not.toContain(overflow);

    await expect(page.locator('#destacadas-prev')).toBeVisible();
    await expect(page.locator('#destacadas-next')).toBeVisible();
  });

  test('las flechas mueven una carta y el bucle es infinito', async ({ page }) => {
    await page.goto('/');
    const pista = page.locator('#pista-destacadas');
    await expect(pista.locator('.carta-card:not(.skeleton)').first()).toBeVisible();

    const orden = () => pista.locator('.carta-card').evaluateAll(as => as.map(a => a.getAttribute('href')));
    const inicial = await orden();
    const n = inicial.length;

    // Siguiente: la primera pasa al final
    await page.locator('#destacadas-next').click();
    await expect.poll(orden).toEqual([...inicial.slice(1), inicial[0]]);

    // Anterior: vuelve al orden inicial
    await page.locator('#destacadas-prev').click();
    await expect.poll(orden).toEqual(inicial);

    // N veces "siguiente" → vuelta completa, mismo orden: no hay final
    for (let i = 0; i < n; i++) {
      await page.locator('#destacadas-next').click();
      await expect.poll(orden).toEqual([...inicial.slice((i + 1) % n), ...inicial.slice(0, (i + 1) % n)]);
    }
    expect(await orden()).toEqual(inicial);

    // La pista no queda desplazada tras cada paso
    await expect.poll(() => pista.evaluate(el => el.style.transform)).toBe('');
  });

  test('en inglés la sección y las flechas están traducidas', async ({ page }) => {
    await page.goto('/en/');
    await expect(page.locator('#titulo-destacadas')).toHaveText('Featured cards');
    await expect(page.locator('#destacadas-next')).toHaveAttribute('aria-label', 'Next card');
    await expect(page.locator('#hero-escaparate')).toHaveAttribute('aria-label', 'Showcase');
  });
});

test.describe('registro', () => {

  test('el formulario no pide la nacionalidad', async ({ page }) => {
    await page.goto('/pages/registro.html');
    await expect(page.locator('#form-registro')).toBeVisible();
    await expect(page.locator('#nacionalidad, [name="nacionalidad"]')).toHaveCount(0);
    // Los demás campos siguen ahí
    for (const id of ['nombre', 'apellido', 'email', 'fecha_nacimiento', 'password']) {
      await expect(page.locator(`#${id}`)).toHaveCount(1);
    }
  });
});

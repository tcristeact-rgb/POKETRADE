// Los cuatro caminos que, si se rompen, la web no sirve: la portada con su
// hero, el catálogo, la búsqueda global y el cambio de idioma. Nada más:
// el resto lo cubren los 125 tests de PHPUnit del backend.
//
// Dependen de la BD de desarrollo (api/database/database.sqlite) con el índice
// de sets sincronizado (php artisan tcgdex:sync-sets) y con al menos cuatro
// cartas abiertas alguna vez, que es lo que le da precio al hero.
import { test, expect } from '@playwright/test';

test.describe('caminos críticos', () => {

  test('la portada carga y muestra 4 cartas destacadas', async ({ page }) => {
    await page.goto('/');

    // El hero pinta un punto por carta destacada y el nombre de la activa
    const puntos = page.locator('.carrusel-punto');
    await expect(puntos).toHaveCount(4);
    await expect(page.locator('#hero-carta-nombre')).not.toBeEmpty();

    // Y la carta activa es una imagen real, no el skeleton
    await expect(page.locator('#carrusel-carta img.carrusel-img').first()).toBeVisible();
  });

  test('el catálogo lista los sets de una serie', async ({ page }) => {
    await page.goto('/pages/catalogo.html?serie=sv');

    // Al terminar de cargar, los skeletons se sustituyen por tarjetas reales
    const sets = page.locator('#grid-catalogo .set-card:not(.skeleton)');
    await expect(sets.first()).toBeVisible();
    expect(await sets.count()).toBeGreaterThan(0);

    // Cada tarjeta enlaza a las cartas de su set
    await expect(sets.first()).toHaveAttribute('href', /set=/);
  });

  test('la búsqueda global navega a catalogo.html con el parámetro q', async ({ page }) => {
    await page.goto('/');

    const buscador = page.locator('#buscador');
    await expect(buscador).toBeVisible();
    await buscador.fill('pikachu');
    await buscador.press('Enter');

    // El término viaja en la URL, que es donde vive el estado del filtro…
    await expect(page).toHaveURL(/\/pages\/catalogo\.html\?q=pikachu$/);
    // …y el catálogo lo recoge en su barra de filtros
    await expect(page.locator('#filtro-nombre')).toHaveValue('pikachu');
  });

  test('el cambio de idioma ES/EN funciona en los dos sentidos', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('html')).toHaveAttribute('lang', 'es');
    await expect(page.locator('header nav a[href$="catalogo.html"]').first()).toHaveText('Catálogo');

    // A inglés: la misma página bajo /en/, con <html lang> y textos traducidos
    await page.locator('#selector-idioma').selectOption('en');
    await expect(page).toHaveURL(/\/en\/(index\.html)?$/);
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    await expect(page.locator('header nav a[href$="catalogo.html"]').first()).toHaveText('Catalogue');

    // Y vuelta a español: sin prefijo
    await page.locator('#selector-idioma').selectOption('es');
    await expect(page).not.toHaveURL(/\/en\//);
    await expect(page.locator('html')).toHaveAttribute('lang', 'es');
    await expect(page.locator('header nav a[href$="catalogo.html"]').first()).toHaveText('Catálogo');
  });
});

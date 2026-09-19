// Taxonomía cerrada de rarezas (spec 2026-09-19-taxonomia-de-rarezas):
// el desplegable del catálogo va en orden canónico con su glifo, y el
// detalle enseña símbolo + nombre abajo a la izquierda, en ES y en EN.
// Depende de la BD de desarrollo, con cartas abiertas alguna vez (con rareza).
import { test, expect } from '@playwright/test';

const API = 'http://127.0.0.1:8000/api';

// El mismo orden que App\Support\Rarezas::ORDEN, con el glifo que dibuja
// utils.js para cada una ('' = sin símbolo)
const ORDEN = [
  ['comun', '●'], ['infrecuente', '◆'], ['rara', '★'], ['doble_rara', '★★'],
  ['ace_spec', '★'], ['ilustracion', '★'], ['ultra_rara', '★★'],
  ['ilustracion_especial', '★★'], ['hiper_rara', '★★★'], ['excepciones', ''],
];

test.describe('rarezas', () => {

  test('el filtro del catálogo lista las rarezas en orden canónico, con su glifo', async ({ page }) => {
    await page.goto('/pages/catalogo.html?serie=sv');

    const opciones = page.locator('#filtro-rareza option');
    // Más que "Todas las rarezas": la API ha respondido y hay cartas con rareza
    await expect.poll(async () => opciones.count()).toBeGreaterThan(1);

    const valores = await opciones.evaluateAll(os => os.slice(1).map(o => [o.value, o.textContent]));
    expect(valores.length).toBeGreaterThan(1);

    // Las claves aparecen en el orden de la taxonomía (no alfabético ni por conteo)
    const posiciones = valores.map(([clave]) => ORDEN.findIndex(([c]) => c === clave));
    expect(posiciones).not.toContain(-1);
    expect(posiciones).toEqual([...posiciones].sort((a, b) => a - b));

    // Y cada texto empieza por su glifo seguido del nombre
    for (const [clave, texto] of valores) {
      const glifo = ORDEN.find(([c]) => c === clave)[1];
      expect(texto.startsWith(glifo ? `${glifo} ` : '')).toBe(true);
      expect(texto.length).toBeGreaterThan(glifo.length + 1);
      if (!glifo) expect(texto).not.toMatch(/[●◆★]/);
    }
  });

  test('el detalle muestra el símbolo junto al nombre de la rareza, en ES y en EN', async ({ page, request }) => {
    // Una carta con rareza y símbolo (cualquier "rara" vale)
    const res = await request.get(`${API}/cartas?rareza=rara&por_pagina=1`);
    const { data } = await res.json();
    expect(data.length).toBe(1);
    const id = data[0].id;

    await page.goto(`/pages/detalle-carta.html?id=${id}`);

    const rareza = page.locator('.detalle-rareza');
    await expect(rareza).toBeVisible();
    const simbolo = rareza.locator('.rareza-simbolo');
    await expect(simbolo).toHaveAttribute('aria-hidden', 'true');
    await expect(simbolo).toHaveText('★');
    await expect(simbolo).toHaveClass(/rareza-simbolo--negro/);
    await expect(rareza.locator('.badge-rareza > span:last-child')).toHaveText('Rara');
    // Abajo a la izquierda: es lo último de la columna de información
    expect(await rareza.evaluate(el => el.parentElement.lastElementChild === el)).toBe(true);

    // Ya no está duplicada en la lista de atributos
    await expect(page.locator('.atributos .badge-rareza')).toHaveCount(0);

    // En inglés: mismo símbolo, nombre traducido
    await page.goto(`/en/pages/detalle-carta.html?id=${id}`);
    await expect(page.locator('.detalle-rareza .rareza-simbolo')).toHaveText('★');
    await expect(page.locator('.detalle-rareza .badge-rareza > span:last-child')).toHaveText('Rare');
  });
});

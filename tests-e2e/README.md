# tests-e2e — caminos críticos en navegador

Suite mínima de [Playwright](https://playwright.dev) que abre la web de verdad
(API en `php artisan serve`, frontend en `node tools/servidor.mjs`) y comprueba
lo que, si se rompe, deja la web inservible:

1. La portada carga y el hero muestra 4 cartas destacadas.
2. El catálogo lista los sets de una serie.
3. La búsqueda global del header navega a `catalogo.html?q=…`.
4. El cambio de idioma ES → EN → ES funciona (`/en/`, `<html lang>`, textos).
5. El autocompletado del buscador sugiere desde la primera letra sin pedir nada por tecla.
6. Registrarse pide el código de verificación (leído de `api/storage/logs/laravel.log`,
   porque en local `MAIL_MAILER=log`), el código verifica y entonces se puede entrar;
   una cuenta sin verificar no puede iniciar sesión. `global-setup.mjs` borra los
   usuarios de prueba antes de cada ejecución (con `node:sqlite`, sin dependencias).

Todo lo demás lo cubren los tests de PHPUnit de `api/`.

## Ejecutar

```
cd tests-e2e
npm install                      # solo la primera vez
npm run instalar-navegador       # solo la primera vez: descarga Chromium
npm test
```

Playwright arranca los dos servidores él mismo y los para al terminar; si ya
están levantados (puertos 8000 y 5500), los reutiliza. `npm run test:ui` abre el
inspector para ver cada paso.

## Qué necesita

- `api/` preparado con `composer setup` y el índice de sets sincronizado
  (`php artisan tcgdex:sync-sets`): el test del catálogo pide la serie `sv`.
- Al menos cuatro cartas con precio en la BD local (abrir cuatro fichas de
  carta basta): es lo que el hero enseña. En una BD recién creada el hero se
  completa desde TCGdex en vivo, así que el test también pasa con internet.
- La búsqueda global solo comprueba la navegación y el estado del filtro, no
  los resultados: `/api/cartas/buscar` es un proxy en vivo a TCGdex y un test no
  debería depender de él.

Esta carpeta es la única del repo con dependencias de npm. El frontend sigue
sin paso de build y sin `node_modules`: la convención no cambia.

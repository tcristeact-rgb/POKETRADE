---
spec: docs/specs/2026-09-19-portada-destacadas-aleatorias-y-registro-sin-nacionalidad.md
fecha: 2026-09-19
resultado: completo
---

# Portada: carrusel infinito de cartas aleatorias; registro sin nacionalidad

## Qué se implementó

- **Registro**: fuera el grupo `nacionalidad` de `registro.html` y del payload
  de `registro.js`; clave i18n `auth.nacionalidadPlaceholder` eliminada
  (ES/EN). La API la sigue aceptando como opcional y el perfil la conserva.
- **API**: `GET /cartas/aleatorias?cantidad=N` (1–24, 12 por defecto): N
  cartas con imagen en orden aleatorio (`conImagen()->inRandomOrder()`), sin
  caché. Ruta antes de `/cartas/{id}`. `CartasAleatoriasTest` (4).
- **Portada**: la sección "Últimas novedades" pasa a "Cartas destacadas" /
  "Featured cards" (`#titulo-destacadas`), "Ver todos →" enlaza al catálogo.
  El escaparate del hero pasa a llamarse "Escaparate" / "Showcase" para no
  tener dos regiones con el mismo nombre.
- **Carrusel** (`inicio.js`): flecha · ventana (`overflow: hidden`) · flecha.
  Infinito por rotación del DOM sin clones: al avanzar, `translateX(-paso)`
  y en `transitionend` la primera tarjeta pasa al final y el transform
  vuelve a 0 sin animar; al retroceder, la última pasa al principio, la
  pista se coloca en `-paso` sin animar y se anima a 0. El paso se mide en
  cada pulsación (ancho de tarjeta + gap), así que responde al viewport.
  Flechas ocultas si todas caben. `prefers-reduced-motion` → salto seco.
  Foco por teclado en una tarjeta fuera de la ventana → rota sin animar
  para ponerla primera y anula el scroll del navegador.
- **CSS**: `.carrusel-cartas`, `.carrusel-cartas-ventana`, pista con
  `transition: transform`; las flechas reutilizan `.carrusel-flecha` con
  colores para fondo claro/oscuro (las del hero son para fondo oscuro).
- **e2e**: `tests-e2e/tests/portada.spec.mjs` (4).

## Desviaciones respecto a la spec

1. **Un clic durante la animación no se descarta**: remata el paso en curso
   al instante y arranca el siguiente. La primera versión ignoraba clics
   mientras la pista se movía (350 ms) y el e2e de "N pulsaciones → vuelta
   completa" lo delató: pulsar rápido perdía pasos. Es mejor UX y es lo que
   el test comprueba ahora.
2. **Temporizador de respaldo** (600 ms) por si `transitionend` no llega
   (pestaña en segundo plano, transición anulada): sin él la pista se
   quedaría bloqueada. Y se ignoran los `transitionend` que burbujean de las
   tarjetas (su hover anima `transform`), que cerraban el paso antes de hora.
3. Las claves i18n `home.novedadesTitulo`, `home.errorNovedades` y
   `home.errorNovedadesTarde` se conservan: las usa `novedades.html`, que
   sigue en el menú.

## Archivos tocados

- `frontend/pages/registro.html`, `frontend/js/registro.js`, `frontend/js/auth.js` (comentario)
- `api/app/Http/Controllers/CartaController.php`, `api/routes/api.php`
- `api/tests/Feature/CartasAleatoriasTest.php` — nuevo
- `frontend/index.html`, `frontend/js/inicio.js`, `frontend/css/estilos.css`
- `frontend/js/i18n/es.js`, `frontend/js/i18n/en.js`
- `tests-e2e/tests/portada.spec.mjs` — nuevo

## Verificación

- `composer test` → **164 passed** (160 + 4), 793 aserciones.
- `npx playwright test --grep-invert verificaci` → **12 passed** (4 nuevos +
  los 8 anteriores). El de verificación de correo sigue sin poder pasar en
  local con `MAIL_MAILER=smtp`.
- Criterios: 12 cartas con imagen / `cantidad` respetada y acotada / orden
  aleatorio / `[]` sin cartas / sin TCGdex (`Http::assertNothingSent`);
  `registro.html` sin `#nacionalidad` y con el resto de campos; sección con
  N > 4 tarjetas, `overflow-x` no es `auto`/`scroll`, dos flechas visibles;
  siguiente/anterior rotan una carta y N pulsaciones vuelven al orden inicial
  con `transform` vacío; ES/EN.
- Capturas (escritorio claro y móvil oscuro): flechas a los lados, sin barra,
  tarjetas recortadas por la ventana; la sombra del hover no se corta.
- Ningún `package.json` bajo `frontend/`.

## Pendiente

- Nada. Quitar la nacionalidad del perfil y de la BD sería otra spec.

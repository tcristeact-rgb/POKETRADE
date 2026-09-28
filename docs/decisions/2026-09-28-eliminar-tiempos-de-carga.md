---
spec: docs/specs/2026-09-28-eliminar-tiempos-de-carga.md
fecha: 2026-09-28
resultado: parcial         # completo | parcial | abandonado
---

# Eliminar los tiempos de carga: servidor siempre despierto y catálogo cacheado en la CDN

## Qué se implementó

- **Middleware `CachePublica`** (`cache.publica`): en un GET con respuesta 2xx pone
  `public, max-age=60, s-maxage=3600, stale-while-revalidate=86400`. Cualquier otra
  respuesta (404, 503, errores) se queda con el `no-cache, private` de Laravel.
  El `Vary: Accept-Language` sigue saliendo de `EstablecerIdioma`.
- **Rutas con `cache.publica`**: `/series`, `/series/{id}`, `/sets`, `/sets/{id}`,
  `/sets/{id}/cartas`, `/cartas`, `/cartas/filtros`, `/cartas/destacadas`,
  `/cartas/nombres`, `/cartas/buscar`, `/cartas/{id}`. Sin él: `/health`,
  `/cartas/aleatorias`, `/tradeos*`, todo lo autenticado y todo lo que no es GET.
  Se aplica ruta a ruta en el bloque de cartas para no alterar el orden de registro
  (las literales antes de `/cartas/{id}`), y como grupo en el bloque de series/sets.
- **`frontend/vercel.json`**: rewrite `/api/:ruta*` →
  `https://poketrade-api-3nwm.onrender.com/api/:ruta*`, primero de la lista (antes
  de las reglas de `/en`).
- **`frontend/js/config.js`**: en producción `API_URL = '/api'`. En localhost no cambia.
- **17 páginas HTML**: se quita del script inline el `preconnect` a Render, que ya
  no tiene sentido porque el navegador solo habla con su propio origen.
- **`.github/workflows/keep-alive.yml`**: cron `*/5 * * * *` más `workflow_dispatch`.
  Hace `curl --fail` directo a Render (`/api/health`) con `--max-time 90` y
  `--retry 3 --retry-all-errors`, y `permissions: {}`.
- **Tests nuevos** en `CachePublicaTest` (6): cacheabilidad de las 11 rutas, max-age
  por defecto frente al que fija el controlador, las no cacheables (health,
  aleatorias, tradeos, inventario autenticado), 404, 503 con TCGdex caído, y
  throttle por `X-Forwarded-For`.
- **Documentación**: README (aviso de la cabecera, sección de arranques en frío,
  trade-offs, contador de tests) y `CLAUDE.md` (contador y convención de
  `cache.publica`).

## Desviaciones respecto a la spec

1. **`/cartas/nombres` conserva su `max-age=86400`.** El controlador ya fijaba 24 h
   de caché en el navegador con ETag. Si el middleware lo hubiera pisado con
   `max-age=60`, el índice de autocompletado se volvería a descargar cada minuto. Por
   eso el middleware solo pone `max-age=60` cuando la respuesta no trae ya un
   `max-age`. Consecuencia: el test existente
   `NombresDeCartasTest::test_es_cacheable_24_horas_en_el_navegador` comparaba la
   cabecera entera (`'max-age=86400, public'`) y ahora sale con `s-maxage` y
   `stale-while-revalidate` añadidos. Lo he cambiado para que compruebe que contiene
   `max-age=86400` y `public`, que es lo que pretendía comprobar desde el principio.
2. **El test de throttle usa `/api/health`, no una ruta del catálogo.** La spec pedía
   "una ruta pública" y `/health` lo es. Es la más barata (no toca la BD) y mide
   exactamente lo que interesa: el limiter `api` por IP.
3. **El contador de tests pasa a 190, no a 164 + 6.** La suite ya tenía 184 tests
   porque hay trabajo de la fase 0 sin commitear en el árbol
   (`ConservacionDeCartasTest` y otros). El README seguía diciendo 125 y lo he
   corregido también.
4. **Convención añadida a `CLAUDE.md`** (no estaba en la lista de archivos de la
   spec). Sin ella, la próxima ruta pública del catálogo se quedaría sin caché sin
   que nadie lo notara.

## Archivos tocados

- `api/app/Http/Middleware/CachePublica.php` — nuevo
- `api/bootstrap/app.php` — alias `cache.publica`
- `api/routes/api.php` — `cache.publica` en las 11 rutas del catálogo
- `api/tests/Feature/CachePublicaTest.php` — nuevo, 6 tests
- `api/tests/Feature/NombresDeCartasTest.php` — aserción de `Cache-Control` por contenido (desviación 1)
- `frontend/vercel.json` — rewrite `/api/:ruta*`
- `frontend/js/config.js` — `API_URL = '/api'` en producción
- `frontend/index.html`, `frontend/pages/*.html` (16) — fuera el `preconnect` a Render
- `.github/workflows/keep-alive.yml` — nuevo
- `README.md`, `CLAUDE.md` — documentación y contadores

## Verificación

### Local

- [x] `composer test` pasa entero → `php artisan config:clear && php artisan test`:
      **190 passed (1195 assertions)**.
- [x] Las 11 rutas cacheables llevan `public`, `s-maxage=3600` y
      `Vary: Accept-Language` →
      `CachePublicaTest::test_las_rutas_publicas_del_catalogo_son_cacheables_por_la_cdn`: pasa.
- [x] No llevan `public` health, aleatorias, tradeos, inventario autenticado, el 404
      y el 503 con TCGdex caído → tres tests de `CachePublicaTest`: pasan.
- [x] 121 peticiones con `X-Forwarded-For: 1.1.1.1` → la 121 da 429 y `2.2.2.2` da
      200 → `test_el_throttle_cuenta_por_la_ip_real_del_cliente`: pasa.
- [x] `vercel.json` es JSON válido y `/api/:ruta*` va antes de `/en/:ruta*` →
      `node -e JSON.parse(...)`: índices 0 y 3.
- [x] `grep -rn onrender frontend --include=*.js --include=*.html` no devuelve nada
      → exit 1, sin salida.
- [x] En local, el frontend sigue apuntando a `localhost:8000` → prueba de humo con
      `php artisan serve` y `node tools/servidor.mjs`: `/`, `/pages/catalogo.html` y
      `/js/config.js` responden 200, `config.js` resuelve a
      `'http://localhost:8000/api'`, y la API local responde a
      `Origin: http://localhost:5500` con CORS. `/api/sets` sale con
      `max-age=60, public, s-maxage=3600, stale-while-revalidate=86400`, y
      `/api/cartas/aleatorias` y `/api/health` con `no-cache, private`. No lo he
      abierto en un navegador: la comprobación es a nivel HTTP.
- [x] `keep-alive.yml` existe con `*/5 * * * *` y `workflow_dispatch`, y es YAML
      válido → `npx js-yaml .github/workflows/keep-alive.yml`: sin errores.

### Tras desplegar — NO verificados (hace falta hacer push, y no lo he hecho)

- [ ] El workflow `keep-alive` lanzado con `workflow_dispatch` termina en verde.
- [ ] Tras ≥ 30 min sin visitas humanas, `/api/health` en Render responde en < 2 s.
- [ ] `GET https://poketrade-beryl.vercel.app/api/sets` dos veces: la segunda con
      `x-vercel-cache: HIT` (o `STALE`) y < 150 ms.
- [ ] Con `Accept-Language: en` y con `es` en la misma URL, cada una recibe su
      `Content-Language` (si falla, plan B de la spec: `?idioma=`).
- [ ] `/api/sets/{id}/cartas` de un set ya visitado: segunda petición `HIT` y < 150 ms.
- [ ] Login a través de Vercel y `/api/inventario` 200 sin `x-vercel-cache: HIT`.
- [ ] `/api/cartas/aleatorias` dos veces devuelve listas distintas.

## Pendiente

- **Desplegar y pasar los 7 criterios de producción.** Por eso el resultado es
  `parcial`. Hay tres riesgos que solo se ven en Vercel real:
  1. que la CDN respete `Vary: Accept-Language`;
  2. que cachee las respuestas de un rewrite externo según `s-maxage`;
  3. el límite de tiempo de Vercel para un rewrite externo, en caso de que el
     keep-alive falle y Render tarde ~60 s en arrancar. `espera.js` sigue
     reintentando los 502/503/504, que cubren también los errores de proxy de Vercel.
- Tras desplegar, `FRONTEND_URL` (CORS) en Render deja de ser necesaria para el
  frontend de producción, porque ahora es mismo origen. No estorba y no la he tocado.
- Después del push, lanzar una vez el workflow a mano desde la pestaña Actions para
  confirmar que funciona.

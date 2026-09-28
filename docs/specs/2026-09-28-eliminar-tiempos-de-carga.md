---
estado: parcial            # propuesta | en-curso | implementada | parcial | descartada
fecha: 2026-09-28
origen: claude-code
---

# Eliminar los tiempos de carga: servidor siempre despierto y catálogo cacheado en la CDN

## Problema

PokeTrade es la pieza principal del portfolio y la primera impresión es una espera.
Medido en producción el 2026-09-28:

- **Arranque en frío de Render (plan free)**: el servicio se apaga tras 15 min sin
  tráfico y la primera petición espera **hasta ~60 s** (`frontend/js/espera.js` lo
  documenta y avisa al usuario, pero no lo evita). Quien abre un portfolio casi
  siempre es "la primera visita".
- **Servidor despierto**, cada petición cruza Vercel → Render → Supabase:
  `/health` 300 ms, `/cartas/aleatorias` 400 ms, `/cartas/destacadas` 440 ms,
  `/sets` 570 ms, `/sets/{id}/cartas` 380 ms (650–1100 ms la primera vez que se
  cachea el set desde TCGdex).
- **Nada se cachea fuera del servidor**: todas las respuestas llevan
  `Cache-Control: no-cache, private`, así que ni el navegador ni la CDN reutilizan
  nada y cada navegación vuelve a pagar el viaje completo.
- Frontend (`poketrade-beryl.vercel.app`) y API (`poketrade-api-3nwm.onrender.com`)
  están en orígenes distintos: las peticiones con `Authorization` pagan además un
  preflight CORS.

## Decisión

Tres piezas que se refuerzan entre sí:

1. **Keep-alive con GitHub Actions**: un workflow programado cada 5 minutos hace
   `GET` a `https://poketrade-api-3nwm.onrender.com/api/health` **directamente contra
   Render** (no a través de Vercel, que podría servirlo de caché y no despertar
   nada). Render no llega nunca a los 15 min de inactividad. Se asume
   conscientemente que el servicio consume ~744 de las 750 h gratuitas del mes.
2. **Cabeceras de caché en las rutas públicas de solo lectura del catálogo**, solo
   para respuestas 2xx:
   `Cache-Control: public, max-age=60, s-maxage=3600, stale-while-revalidate=86400`,
   conservando el `Vary: Accept-Language` que ya pone `EstablecerIdioma`.
   - **Cacheables**: `GET /series`, `/series/{id}`, `/sets`, `/sets/{id}`,
     `/sets/{id}/cartas`, `/cartas`, `/cartas/filtros`, `/cartas/destacadas`,
     `/cartas/nombres`, `/cartas/buscar`, `/cartas/{id}`.
   - **No cacheables** (se quedan como hoy): `/health`, `/cartas/aleatorias` (la
     portada promete cartas distintas en cada visita), `/tradeos` y `/tradeos/{id}`
     (cambian con la actividad de los usuarios), todo lo autenticado y todo lo que
     no sea GET.
   - Un 404, un 503 (`tcgdex_caido`) o cualquier respuesta no 2xx **nunca** lleva
     `public`: una caída de TCGdex no puede quedarse congelada una hora en la CDN.
3. **La API se sirve desde el mismo origen que el frontend**: un rewrite
   `/api/:ruta*` → `https://poketrade-api-3nwm.onrender.com/api/:ruta*` en
   `frontend/vercel.json`, y en producción `API_URL = '/api'` en `config.js`. La CDN
   de Vercel cachea y sirve en el borde las respuestas marcadas en (2), y desaparecen
   CORS y los preflights. En local nada cambia (`http://localhost:8000/api`).

`espera.js` se queda como red de seguridad por si el keep-alive falla.

### Riesgos que la implementación tiene que comprobar, no suponer

- **Idioma en la CDN.** El idioma viaja en `Accept-Language` y la respuesta lleva
  `Vary: Accept-Language`. Si en el despliegue de preview se comprueba que la CDN de
  Vercel **no** separa las variantes por esa cabecera (una petición `en` recibe la
  respuesta cacheada en `es`), el plan B es: `EstablecerIdioma` acepta además un
  parámetro `?idioma=` con prioridad sobre la cabecera, y `apiFetch` lo añade a las
  peticiones GET. Así el idioma forma parte de la URL y de la clave de caché. Si hay
  que activar el plan B, se registra en `docs/decisions/`.
- **Throttle por IP detrás de un proxy más.** Con Vercel delante, todas las
  peticiones llegan a Render desde IPs de Vercel. El limiter `api` (120/min) y
  `buscar` (30/min) usan `$request->ip()`; con `trustProxies(at: '*')` ya se lee
  `X-Forwarded-For`, pero hay que comprobar que dos clientes distintos siguen
  teniendo cupos separados y no comparten uno global.
- **Rewrites externos cacheados.** Que Vercel cachee las respuestas de un rewrite a
  un origen externo según su `s-maxage` hay que verificarlo en el preview (cabecera
  `x-vercel-cache`), no darlo por hecho. Si no las cachea, se registra en decisions y
  se evalúa `CDN-Cache-Control` / `Vercel-CDN-Cache-Control` antes de dar la spec por
  cumplida.

## Alternativas descartadas

- **Snapshot estático del catálogo en JSON (opción B del análisis)**: es la más rápida
  pero contradice el cache-aside del `CLAUDE.md`, desactualiza los datos entre
  generaciones y es 1–2 días de trabajo. Queda como siguiente paso si esto no basta.
- **Render Starter de pago (7 $/mes)**: resuelve el arranque en frío sin código, pero
  es un coste fijo que el usuario prefiere no asumir mientras sea un solo servicio.
- **cron-job.org como keep-alive**: más puntual que el cron de GitHub, pero se
  configura a mano fuera del repo y no queda versionado. Elegido GitHub Actions.
- **Cachear `/cartas/aleatorias` unos segundos**: haría que todos los visitantes de
  ese intervalo vieran el mismo carrusel, lo contrario de lo que promete la portada.
  Con el servidor siempre despierto, 400 ms es aceptable para esa ruta.

## Criterios de aceptación

### Verificables en local

- [ ] `composer test` pasa entero (los 164 existentes más los nuevos).
- [ ] Hay tests nuevos que comprueban, para **cada** ruta de la lista cacheable, que
      una respuesta 200 lleva `Cache-Control` con `public` y `s-maxage=3600`, y
      `Vary` con `Accept-Language`.
- [ ] Hay tests nuevos que comprueban que **no** llevan `public`:
      `GET /api/health`, `GET /api/cartas/aleatorias`, `GET /api/tradeos`,
      `GET /api/inventario` autenticado, `GET /api/sets/no-existe` (404) y
      `GET /api/sets/{id}/cartas` con TCGdex caído (503, con `Http::fake`).
- [ ] Hay un test que manda 121 peticiones a una ruta pública con
      `X-Forwarded-For: 1.1.1.1` y comprueba que la 121 da 429, mientras que una
      petición con `X-Forwarded-For: 2.2.2.2` a continuación da 200.
- [ ] `node -e "JSON.parse(require('fs').readFileSync('frontend/vercel.json'))"` no
      falla, y en `rewrites` la regla `/api/:ruta*` está **antes** de `/en/:ruta*`.
- [ ] `grep -rn onrender frontend --include=*.js --include=*.html` no devuelve nada
      (desaparece la URL absoluta de `config.js` y el `preconnect` a Render de
      todas las páginas; la URL de Render solo vive en `vercel.json`).
- [ ] Con `node tools/servidor.mjs` y la API local, la portada y el catálogo cargan
      contra `http://localhost:8000/api` como hoy (`config.js` en localhost no cambia).
- [ ] Existe `.github/workflows/keep-alive.yml` con `schedule: cron: '*/5 * * * *'`
      y `workflow_dispatch`, que hace `curl --fail` con reintentos y timeout a
      `https://poketrade-api-3nwm.onrender.com/api/health`, y el YAML es válido
      (`node -e` con un parser YAML o `python -c "import yaml; yaml.safe_load(...)"`).

### Verificables tras desplegar (requieren push; el push lo autoriza el usuario)

- [ ] El workflow `keep-alive` lanzado a mano con `workflow_dispatch` termina en verde.
- [ ] Tras ≥ 30 min sin visitas humanas, `GET https://poketrade-api-3nwm.onrender.com/api/health`
      responde en < 2 s (el servicio no se ha dormido).
- [ ] `GET https://poketrade-beryl.vercel.app/api/sets` dos veces seguidas con
      `Accept-Language: es`: la segunda lleva `x-vercel-cache: HIT` (o `STALE`) y
      tarda < 150 ms.
- [ ] La misma URL con `Accept-Language: en` devuelve `Content-Language: en` y
      nombres en inglés, y con `es` devuelve `Content-Language: es`; ninguna recibe
      la variante del otro idioma. (Si falla: plan B de idioma, ver Decisión.)
- [ ] `GET https://poketrade-beryl.vercel.app/api/sets/{id}/cartas` para un set ya
      visitado: segunda petición con `x-vercel-cache: HIT` y < 150 ms.
- [ ] Login a través de `https://poketrade-beryl.vercel.app/api/auth/login` funciona,
      y `GET /api/inventario` con el token devuelve 200 **sin** `x-vercel-cache: HIT`.
- [ ] `GET https://poketrade-beryl.vercel.app/api/cartas/aleatorias` dos veces
      devuelve dos listas distintas.

## Archivos afectados (previsión)

- `api/routes/api.php` — aplicar el middleware de caché a las rutas cacheables
- `api/app/Http/Middleware/` — middleware nuevo de cabeceras de caché que solo marca
  `public` las respuestas 2xx (el `cache.headers` de Laravel no mira el código de
  estado)
- `api/bootstrap/app.php` — alias del middleware
- `api/tests/Feature/` — test nuevo de cabeceras de caché y de throttle por IP
- `frontend/vercel.json` — rewrite `/api/:ruta*`
- `frontend/js/config.js` — `API_URL = '/api'` en producción
- `frontend/index.html` y `frontend/pages/*.html` — quitar el `preconnect` a Render
  del script inline
- `.github/workflows/keep-alive.yml` — nuevo
- `README.md` y `CLAUDE.md` — contador de tests; nota de arquitectura sobre el
  keep-alive y la caché en la CDN
- (solo si hace falta el plan B) `api/app/Http/Middleware/EstablecerIdioma.php`,
  `frontend/js/auth.js`

## Fuera de alcance

- Snapshot estático del catálogo (opción B) y cambio de plan en Render (opción C).
- Imágenes de cartas: vienen de `assets.tcgdex.net` y ya se piden en `low.webp` con
  `loading="lazy"`; no se proxifican ni se rehospedan.
- Invalidación activa de la CDN cuando un admin edita una carta: se acepta que el
  cambio tarde hasta 1 h (`s-maxage`) en verse para visitantes anónimos.
- Cambiar el orden o la lógica del cache-aside contra TCGdex.
- Quitar `espera.js`: se queda como red de seguridad.

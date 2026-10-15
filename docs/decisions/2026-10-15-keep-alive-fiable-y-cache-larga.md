---
spec: docs/specs/2026-10-15-keep-alive-fiable-y-cache-larga.md
fecha: 2026-10-15
resultado: parcial         # completo | parcial | abandonado
---

# Keep-alive que de verdad se ejecuta y caché de catálogo de 24 h en la CDN

## Diagnóstico (medido en producción el 2026-10-15)

- `gh run list --workflow keep-alive.yml`: 81 ejecuciones en 17 días. Hueco entre
  ejecuciones: mín. 143 min, mediana 310 min, máx. 581 min. Ninguna a menos de 15 min
  de la anterior, así que Render dormía casi siempre.
- Paso de ping de la ejecución 38716173936: 24 s. Con Render despierto, `/api/health`
  responde en 0,12 s. Esos 24 s son un arranque en frío.
- Con Render despierto, a través de Vercel: `/sets/{id}/cartas` MISS 650–1010 ms y
  HIT 26–33 ms. `/sets` MISS 650 ms y HIT 95 ms. La API no es el cuello de botella.

## Qué se implementó

- **`CachePublica`**: `s-maxage` por defecto pasa de 3600 a **86400** y
  `stale-while-revalidate` de 86400 a **604800**. Acepta un parámetro opcional con el
  `s-maxage` (`cache.publica:3600`). Los valores están en constantes de la clase.
- **`/cartas/destacadas`** usa `cache.publica:3600`, porque el controlador rota la
  selección cada hora (`Cache::put(..., 3600)`).
- **`CachePublicaTest`**: el helper comprueba el `s-maxage` exacto con una regex,
  para que `s-maxage=3600` no pase por `s-maxage=36000` ni al revés. También comprueba
  `stale-while-revalidate=604800`. Hay un test nuevo para destacadas a 3600, que sale
  de la lista general.
- **`tools/keep-alive-supabase.sql`**: `pg_cron` + `pg_net`, job
  `poketrade-keep-alive` a `*/10 * * * *` con `GET` a `/api/health` en Render y
  timeout de 60 s. Es idempotente, porque `cron.schedule` con un nombre que ya existe
  actualiza el job. Trae las consultas de comprobación y la de desinstalar.
- **Borrado `.github/workflows/keep-alive.yml`.**
- README (sección de arranques en frío y trade-offs) y `CLAUDE.md` (duración por
  defecto de `cache.publica`, el parámetro y dónde vive el keep-alive).

## Desviaciones respecto a la spec

- Ninguna en lo implementado.
- **El contador de tests no se ha tocado.** `CLAUDE.md` y el README dicen 190, pero
  `composer test` en un HEAD limpio da **171**. Los 19 que faltan son los tests "de la
  fase 0 sin commitear" que mencionaba la decisión `2026-09-28-eliminar-tiempos-de-carga`:
  se contaron en otra máquina y nunca entraron en el repo. Con este cambio la suite
  queda en 172. No corrijo el contador porque no sé si esos tests siguen existiendo
  en otro árbol.
- **Entorno local**: esta máquina tiene PHP 8.4 y `composer.lock` fija
  `lcobucci/clock 2.3.0` (PHP ≤ 8.2), así que `composer setup` falla en
  `composer install`. Lo he instalado con `--ignore-platform-req=php` y he ejecutado a
  mano el resto de pasos de `setup`. Solo afecta a `vendor/` y al `.env` recién creado,
  que no existía; el lockfile no cambia.

## Archivos tocados

- `api/app/Http/Middleware/CachePublica.php` — 24 h / 7 días, parámetro `s-maxage`
- `api/routes/api.php` — `cache.publica:3600` en `/cartas/destacadas`
- `api/tests/Feature/CachePublicaTest.php` — nuevos valores, test de destacadas
- `tools/keep-alive-supabase.sql` — nuevo
- `.github/workflows/keep-alive.yml` — borrado
- `README.md`, `CLAUDE.md`

## Verificación

- [x] `composer test` pasa → **172 passed** (HEAD limpio: 171; +1 test nuevo).
- [x] Rutas cacheables con `s-maxage=86400` y `stale-while-revalidate=604800` →
      `test_las_rutas_publicas_del_catalogo_son_cacheables_por_la_cdn`: pasa.
- [x] `/cartas/destacadas` con `s-maxage=3600` →
      `test_las_destacadas_solo_se_guardan_una_hora_en_la_cdn`: pasa.
- [x] No cacheables, 404 y 503 sin `public` → tests existentes sin cambios: pasan.
- [x] `tools/keep-alive-supabase.sql` existe, es idempotente (`cron.schedule` con nombre
      fijo), usa `*/10 * * * *` contra la URL de Render e incluye la consulta de
      historial → revisión del archivo. **No lo he ejecutado contra Supabase**
      (no tengo acceso a producción).
- [x] `.github/workflows/keep-alive.yml` no existe → `git rm`.
- [x] README y `CLAUDE.md` actualizados. `grep` de `keep-alive.yml`, `s-maxage=3600` y
      `stale-while-revalidate=86400` fuera de `docs/` → sin resultados.
- [ ] **Tras aplicar el SQL y desplegar**: `net._http_response` con 200 cada ~10 min, y
      `/api/health` < 2 s a cualquier hora. Pendiente.

## Pendiente

- Ejecutar `tools/keep-alive-supabase.sql` en el SQL Editor de Supabase.
- Commit + push para que Render y Vercel cojan las nuevas cabeceras.
- Comprobar al día siguiente con la consulta de `net._http_response` del propio SQL.

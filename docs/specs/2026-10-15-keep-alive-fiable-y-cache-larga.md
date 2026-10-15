---
estado: propuesta          # propuesta | en-curso | implementada | parcial | descartada
fecha: 2026-10-15
origen: claude-code
---

# Keep-alive que de verdad se ejecuta y caché de catálogo de 24 h en la CDN

## Problema

Las cartas siguen tardando en cargar. La spec `2026-09-28-eliminar-tiempos-de-carga`
puso un ping cada 5 minutos con GitHub Actions, pero GitHub no lo cumple:

- 81 ejecuciones del 2026-09-28 al 2026-10-15. **Hueco mediano: 310 min. Mínimo: 143 min.
  Máximo: 581 min.** Ningún hueco por debajo de los 15 min tras los que Render duerme
  el contenedor. El `schedule` de GitHub es *best effort* y en repos con poca
  actividad se retrasa horas.
- El paso de ping de la última ejecución tardó **24 s** en un endpoint que despierto
  responde en 0,1 s: el contenedor estaba dormido. El workflow sale en verde igual,
  porque el ping acaba respondiendo.

Con Render despierto, la API va bien: `/sets/{id}/cartas` tarda 0,6–1 s en un MISS de
la CDN y ~30 ms en un HIT. El problema son los arranques en frío.

Además, `s-maxage=3600` hace que una respuesta de la CDN caduque a la hora. Con poco
tráfico, casi cada visita es un MISS que va a Render, y si Render duerme le toca
esperar al arranque.

## Decisión

1. Llevar el ping a **Supabase con `pg_cron` + `pg_net`**: un job cada 10 min hace
   `GET /api/health` directo a Render. El SQL va versionado en el repo, se aplica a
   mano en el SQL editor de Supabase y se retira el workflow de GitHub.
2. Subir la caché de la CDN del catálogo a **`s-maxage=86400` y
   `stale-while-revalidate=604800`**. Coincide con la caducidad de 24 h que ya tienen
   los datos de cartas en el servidor. Una ruta puede pedir menos con un parámetro
   del middleware (`cache.publica:3600`). Así lo hace `/cartas/destacadas`, que
   rota cada hora.

## Alternativas descartadas

- **Seguir con GitHub Actions o bajar el cron**: el problema es que GitHub no
  respeta el horario, no el intervalo que se le pide.
- **cron-job.org / UptimeRobot**: funcionan, pero son otra cuenta más. Supabase ya
  está en el stack.
- **Vercel Cron**: en el plan Hobby solo permite una ejecución al día.
- **Plan de pago en Render**: es la solución de verdad, pero cuesta dinero. Queda
  apuntada en el README.

## Criterios de aceptación

- [ ] `composer test` pasa entero.
- [ ] Las rutas cacheables del catálogo llevan `s-maxage=86400` y
      `stale-while-revalidate=604800` (`CachePublicaTest`).
- [ ] `/cartas/destacadas` lleva `s-maxage=3600`, no 86400 (test nuevo).
- [ ] Las rutas no cacheables y las respuestas 4xx/5xx siguen sin `public` (los tests
      existentes no cambian).
- [ ] Existe `tools/keep-alive-supabase.sql`. Es idempotente (se puede ejecutar dos
      veces sin duplicar el job), programa `*/10 * * * *` contra
      `https://poketrade-api-3nwm.onrender.com/api/health` e incluye la consulta para
      ver el historial de respuestas.
- [ ] `.github/workflows/keep-alive.yml` ya no existe.
- [ ] README y `CLAUDE.md` describen el nuevo keep-alive y los nuevos tiempos de caché.
- [ ] Tras desplegar y aplicar el SQL (no verificable sin acceso a producción):
      en `net._http_response` hay respuestas 200 con menos de 11 min de separación,
      y `/api/health` responde en < 2 s a cualquier hora.

## Archivos afectados (previsión)

- `api/app/Http/Middleware/CachePublica.php`
- `api/routes/api.php`
- `api/tests/Feature/CachePublicaTest.php`
- `tools/keep-alive-supabase.sql` — nuevo
- `.github/workflows/keep-alive.yml` — se borra
- `README.md`, `CLAUDE.md`

## Fuera de alcance

- Purgar la CDN al cambiar datos de cartas: con 24 h de caducidad en el servidor, la
  CDN no sirve nada más viejo que lo que ya servía la propia API.
- Imágenes de cartas: se sirven desde `assets.tcgdex.net` y no pasan por la API.

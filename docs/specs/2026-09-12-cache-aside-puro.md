---
estado: implementada
fecha: 2026-09-12
origen: cowork
---

# Cache-aside puro: eliminar el sembrado de catálogo y datos de prueba

## Problema

El proyecto tiene hoy **dos mecanismos** para meter cartas en la base de datos:

1. **Cache-aside** — las cartas de un set se descargan y persisten la primera vez
   que alguien abre ese set. Es el diseño principal y el que sostiene el catálogo
   de 20.386 cartas.
2. **`CartasSeeder`** — precarga dos sets curados (`sv03.5` y `swsh12.5`, unas 437
   cartas) para que la base no esté vacía el primer día.

El segundo ensucia la base de datos y el código sin aportar capacidad: todo lo que
siembra, cache-aside lo consigue igual. Y arrastra dependencias: `InventarioSeeder`
y `TradeosSeeder` necesitan que existan cartas, de modo que un sembrado con la red
caída revienta con `SQLSTATE[23000] FOREIGN KEY constraint failed` en vez de
degradarse.

Hay además un fallo latente en `CartaController::destacadas()`: cachea su resultado
una hora **aunque sea vacío**. Sin seeder, el hero de la portada se quedaría en
blanco hasta 60 minutos después de que alguien cacheara cartas.

## Decisión

Dejar **un único mecanismo**: cache-aside.

- Eliminar `CartasSeeder`, `InventarioSeeder` y `TradeosSeeder`.
- `UsuariosSeeder` **se mantiene**: no depende de cartas y da login local.
- Hacer que `/api/cartas/destacadas` se complete desde TCGdex en vivo cuando la
  base de datos no tenga suficientes cartas con precio, en lugar de devolver una
  lista corta o vacía.
- No cachear nunca un resultado vacío de `destacadas`.

## Alternativas descartadas

- **Conservar `CartasSeeder` fuera del encadenado por defecto**: dejaría código que
  nadie ejecuta y dos formas de poblar el catálogo conviviendo. Es justo lo que se
  quiere eliminar.
- **Dejar el hero vacío hasta la primera navegación**: la portada es lo primero que
  ve quien abre la demo. Un hero en blanco no es aceptable.
- **Precargar las 20.386 cartas**: entre 1 y 3 horas de sincronización, por idioma,
  contra una API pública gratuita, y rozando el límite de 500 MB del plan gratuito
  de Supabase. Contradice el diseño cache-aside.

## Criterios de aceptación

**Eliminación limpia**

- [ ] `api/database/seeders/CartasSeeder.php`, `InventarioSeeder.php` y `TradeosSeeder.php` ya no existen.
- [ ] `api/database/seeders/UsuariosSeeder.php` **sigue existiendo** y `DatabaseSeeder` solo lo llama a él.
- [ ] `grep -ri "CartasSeeder\|InventarioSeeder\|TradeosSeeder" api/ README.md` no devuelve ninguna coincidencia. Incluye comentarios, mensajes de consola y documentación — en particular el aviso de `SincronizarCartasTcgdex`, que hoy dice "Ejecuta antes el seeder: php artisan db:seed --class=CartasSeeder" y debe pasar a indicar cómo se puebla ahora (navegar un set, o `tcgdex:sync-sets`).
- [ ] `php artisan migrate:fresh --seed` termina con código de salida 0 sobre una base vacía, sin errores de clave foránea.

**Destacadas con respaldo en vivo**

- [ ] Con cartas suficientes en la BD, `/api/cartas/destacadas` se comporta exactamente como hoy. Los tests existentes de `DestacadasTest` siguen pasando sin modificarlos.
- [ ] Con la BD vacía, `/api/cartas/destacadas` devuelve 4 cartas obtenidas de TCGdex, con los mismos campos que consume el hero hoy.
- [ ] Con menos de 4 cartas con precio en la BD, la respuesta se completa hasta 4 con cartas de TCGdex, sin repetir ninguna.
- [ ] Si TCGdex tampoco responde, la respuesta es `{"data": []}` con código 200. No 500, ni excepción sin capturar.
- [ ] **Un resultado vacío no se cachea.** Tras un fallo, la siguiente petición vuelve a intentarlo. Mismo criterio que ya aplica `TcgdexService::get()` con sus `null`.
- [ ] La caché sigue siendo de una hora y sigue siendo independiente por idioma.

**Tests**

- [ ] Tests nuevos en `api/tests/Feature/DestacadasTest.php` que cubran: base vacía completada desde TCGdex, relleno parcial sin duplicados, TCGdex caído devolviendo `data` vacío con 200, y que el vacío no queda cacheado.
- [ ] TCGdex mockeado con `Http::fake`, como el resto de la suite. Ningún test sale a la red.
- [ ] `cd api && composer test` pasa **entero**.

**Documentación**

- [ ] El README refleja que el catálogo se puebla solo por cache-aside, y que `php artisan tcgdex:sync-sets` es lo que deja series y sets navegables.
- [ ] Las instrucciones de puesta en marcha no mencionan sembrar cartas.

## Archivos afectados (previsión)

- `api/database/seeders/CartasSeeder.php` — eliminar
- `api/database/seeders/InventarioSeeder.php` — eliminar
- `api/database/seeders/TradeosSeeder.php` — eliminar
- `api/database/seeders/DatabaseSeeder.php` — dejar solo `UsuariosSeeder`
- `api/app/Http/Controllers/CartaController.php` — `destacadas()`
- `api/app/Console/Commands/SincronizarCartasTcgdex.php` — mensaje de aviso
- `api/tests/Feature/DestacadasTest.php` — tests nuevos
- `README.md`

## Fuera de alcance

- Tocar el mecanismo de cache-aside en sí (`SetController`, `TcgdexService`).
- Cambiar el diseño o el aspecto del hero.
- Eliminar `UsuariosSeeder`.
- Precargar el catálogo completo.

## Notas de implementación

- El respaldo en vivo puede apoyarse en el índice de sets que ya está en la base
  de datos tras `tcgdex:sync-sets` — por ejemplo, el set más reciente no excluido —
  para no tener que recorrer TCGdex a ciegas.
- La forma de las cartas devueltas debe coincidir con la que ya produce
  `CartaController::buscar()`, que monta `imagen_low` / `imagen_high` a mano para
  que el frontend reutilice `tarjetaCarta()` sin cambios.
- Obtener el precio exige el detalle de cada carta, que es una petición por carta.
  Limita cuántas hidrata: bastan las necesarias para completar 4.

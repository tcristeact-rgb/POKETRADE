---
estado: propuesta
fecha: 2026-09-12
origen: cowork
---

# Autocompletado de sugerencias en los buscadores

## Problema

Los buscadores no dan ninguna ayuda mientras el usuario escribe. Hoy:

- El buscador del header (`#buscador` y su gemelo móvil `#buscador-drawer`) solo
  reacciona a Enter o al clic del botón, y navega a `pages/catalogo.html?q=<término>`.
  Hasta que no navegas, no sabes si lo que escribiste existe.
- El filtro del catálogo llama a `/api/cartas/buscar` pero tampoco sugiere nada:
  o aciertas el nombre completo, o no encuentras.

Si el usuario escribe `pik` no tiene forma de saber que la carta se llama
`Pikachu` hasta que lo escribe entero y correctamente.

## Decisión

Crear un módulo de autocompletado reutilizable (`frontend/js/autocompletado.js`)
que se enchufe a los tres inputs de búsqueda existentes y muestre bajo cada uno
una lista de **nombres de carta sugeridos**, navegables con teclado.

Las sugerencias son **términos de búsqueda, no cartas**: se deduplican por nombre.
`pikachu` aparece una sola vez, aunque TCGdex devuelva cincuenta cartas con ese
nombre en distintos sets.

## Alternativas descartadas

- **Implementarlo dos veces, en `header.js` y en `catalogo.js`**: duplicaría el
  manejo de teclado, el debounce y la accesibilidad en dos sitios que luego
  divergen. Un solo módulo con tres montajes.
- **Sugerir cartas individuales con imagen**: `/cartas/buscar` devuelve una carta
  por set, así que `pikachu` daría decenas de filas casi idénticas. La lista sería
  ruido, no ayuda.
- **Cachear el catálogo entero en el cliente para buscar en local**: son 20.386
  cartas. Contradice la carga cache-aside que ya usa el proyecto.
- **Añadir una librería de autocompletado (Awesomplete, Tippy…)**: el frontend no
  tiene paso de build ni dependencias de npm, y esa es una convención del proyecto,
  no un accidente.

## Criterios de aceptación

**Backend**

- [ ] `GET /api/cartas/buscar` acepta un parámetro opcional `limit` (entero, 1-50).
      Sin él, el comportamiento actual no cambia.
- [ ] Con `limit`, la respuesta contiene como máximo ese número de elementos en `data`.
- [ ] Existe un test nuevo en `api/tests/Feature/` que cubre: `limit` respetado,
      `limit` ausente, y `limit` fuera de rango rechazado con 422.
- [ ] `cd api && composer test` pasa **entero**, sin fallos nuevos ni existentes.
      (Ejecutar `composer setup` antes si el entorno es nuevo: `jwt:secret` es
      necesario para los tests de tradeos.)

**Frontend — verificable por inspección del diff**

- [ ] Existe `frontend/js/autocompletado.js` y exporta una función de montaje
      reutilizable.
- [ ] `header.js` y `catalogo.js` la usan. Ninguno de los dos contiene lógica de
      autocompletado propia ni un debounce duplicado.
- [ ] `git diff --stat` no muestra ningún `package.json`, `node_modules` ni
      bundler nuevo bajo `frontend/`.
- [ ] Todos los textos visibles y `aria-label` nuevos pasan por `t(...)`, con sus
      claves añadidas a los archivos de i18n en ES y EN. Ninguna cadena literal
      en español o inglés incrustada en el JS.

**Frontend — verificable abriendo la página**

- [ ] Escribir `pik` en el buscador del header muestra una lista bajo el input que
      incluye `Pikachu`.
- [ ] La lista no repite el mismo nombre dos veces.
- [ ] Con menos de 2 caracteres no se hace ninguna petición de red
      (comprobable en la pestaña Network del navegador). Es el mínimo que ya
      impone el endpoint, que responde 422 por debajo de 2.
- [ ] Escribir rápido `p`, `pi`, `pik` genera **una** petición, no tres
      (debounce), y las peticiones anteriores que sigan en vuelo se cancelan.
- [ ] Flechas arriba/abajo recorren las sugerencias, Enter selecciona la
      resaltada, Escape cierra la lista, y un clic fuera también la cierra.
- [ ] Seleccionar una sugerencia desde el header navega a
      `pages/catalogo.html?q=<nombre>`; seleccionarla desde el catálogo aplica el
      filtro sin recargar, igual que hoy.
- [ ] El input declara `role="combobox"`, `aria-expanded` que refleja el estado
      real, y `aria-activedescendant` apuntando a la opción resaltada. La lista
      es un `role="listbox"` con opciones `role="option"`.
- [ ] Si TCGdex responde 503, la lista no aparece y el buscador sigue permitiendo
      buscar con Enter. Sin errores en consola.
- [ ] Funciona en el drawer móvil (`#buscador-drawer`) igual que en el del header.

## Archivos afectados (previsión)

- `frontend/js/autocompletado.js` — nuevo
- `frontend/js/header.js` — montar el módulo en `configurarBuscadores()`
- `frontend/js/catalogo.js` — montar el módulo en `#filtro-nombre`
- `frontend/css/` — estilos de la lista, respetando las custom properties de tema
- `api/app/Http/Controllers/CartaController.php` — parámetro `limit` en `buscar()`
- `api/tests/Feature/` — test nuevo
- archivos de i18n ES/EN — claves nuevas

## Fuera de alcance

- Búsqueda por tipo o rareza desde el autocompletado. Solo nombre.
- Historial de búsquedas recientes.
- Resaltar el fragmento coincidente dentro de la sugerencia.
- Tests automatizados de frontend. El proyecto no tiene suite de frontend y esta
  spec no la introduce.
- Cambiar el comportamiento actual de búsqueda con Enter.

## Notas de implementación

- El endpoint devuelve `{ data: [...], total }` con cartas ya normalizadas
  (`nombre`, `tcgdex_id`, `imagen_low`…). Para las sugerencias basta `nombre`,
  deduplicado preservando el orden de relevancia que ya trae TCGdex.
- `/cartas/buscar` es un proxy en vivo a TCGdex, no una consulta a la BD local:
  cada pulsación cuesta una llamada externa. Por eso el debounce, la cancelación
  de peticiones en vuelo y una caché por prefijo en memoria son requisitos, no
  optimizaciones opcionales.
- `catalogo.js` ya reflejaba `q` en la URL. Ese contrato se mantiene.

---
estado: implementada
fecha: 2026-09-28
origen: claude-code
---

# Expansiones del inventario como filas desplegables

## Problema

La vista "Por expansión" del inventario (spec
`2026-09-28-inventario-por-expansion-y-busqueda`) pinta todas las secciones
abiertas, una debajo de otra. Con muchos sets hay que bajar un buen rato para
llegar al que interesa, y no se ve de un vistazo qué expansiones tiene uno.
Esa spec dejó "plegar/desplegar secciones" fuera de alcance; ahora se pide.

## Decisión

Cada expansión pasa a ser una **fila desplegable** (acordeón): una cabecera
con símbolo, nombre, resumen "N cartas · M copias" y una flecha. Al pulsarla
se despliegan debajo sus cartas.

- `<details>` / `<summary>` nativos: teclado (Enter/Espacio), foco y estado
  expandido para lectores de pantalla sin ARIA a mano ni JS de apertura.
- **Por defecto, todas plegadas**: la vista agrupada es un índice de
  expansiones.
- **Buscando**, las filas con coincidencias salen **desplegadas** (si no, la
  búsqueda no enseñaría nada); las que no tienen coincidencias siguen sin
  aparecer.
- Las filas que el usuario abre se **recuerdan mientras dure la página**:
  escribir, borrar una carta o añadir cartas re-pinta el inventario y no debe
  plegárselas. Lo que se abre solo por la búsqueda no cuenta como abierto por
  el usuario. No se guarda entre visitas.
- La flecha gira al desplegar; sin animación de altura.
- La vista "Todas" y la búsqueda no cambian.

## Alternativas descartadas

- **Botón + `aria-expanded` + JS propio**: lo mismo que `<details>` pero
  reimplementando teclado y estado a mano.
- **Recordar las filas abiertas en `localStorage`**: al volver otro día lo
  útil es el índice plegado; y crece con claves de sets que ya no se tienen.
- **Todas desplegadas por defecto**: es exactamente el problema de ahora.

## Criterios de aceptación

- [ ] `node --check` pasa en `frontend/js/inventario.js`.
- [ ] En la app local, vista "Por expansión" sin búsqueda: una fila por set,
      todas plegadas (ninguna carta visible), con nombre, resumen y flecha.
- [ ] Pulsar una fila la despliega y muestra sus cartas; volver a pulsarla la
      pliega. Con el teclado (Tab + Enter) también.
- [ ] Una fila abierta sigue abierta tras escribir y borrar texto en el
      buscador.
- [ ] Con texto buscado, las filas con coincidencias salen desplegadas; al
      vaciar la búsqueda vuelven a su estado anterior (plegadas salvo las que
      abrió el usuario).
- [ ] Ninguna petición a `/api` al abrir o cerrar filas.
- [ ] Móvil (390 px): sin scroll horizontal; tema oscuro legible.
- [ ] Sin errores en la consola.

## Archivos afectados (previsión)

- `frontend/js/inventario.js`
- `frontend/css/estilos.css`

## Fuera de alcance

- Botones "desplegar todo / plegar todo".
- Animar la apertura.
- Cambios en la vista "Todas" o en el backend.

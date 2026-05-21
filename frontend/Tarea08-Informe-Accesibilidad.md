# Tarea 08 — Informe de Evaluación y Mejora de la Accesibilidad

---

## FASE 1 — RÚBRICA DE TRABAJO INICIAL

Plan de trabajo definido **antes** de modificar el código. Sirve como guía interna y como anexo del informe.

### 1.1. Estado inicial de accesibilidad

El proyecto parte de una base razonable: HTML5 semántico (`header`, `nav`, `main`, `footer`), atributo `lang="es"` en todas las páginas, títulos `<title>` únicos y descriptivos, un sistema de foco visible (`:focus-visible`) ya definido y formularios de autenticación validados (mejorados en la Tarea 07). Sin embargo, presenta carencias en navegación por teclado, nombres accesibles de controles, contraste en el pie de página y gestión del foco en ventanas modales.

### 1.2. Problemas detectados (resumen)

Once incidencias clasificadas según WCAG 2.1 (ver auditoría completa en la Fase 2 del informe): ausencia de enlace de salto, controles de filtro sin etiqueta, tarjetas no operables por teclado, contraste insuficiente en el pie, modales sin gestión de foco, botones de icono sin nombre accesible e imágenes decorativas no marcadas.

### 1.3. Pruebas previstas

- Revisión manual del HTML, CSS y JavaScript.
- Cálculo de ratios de contraste de color.
- Navegación completa con teclado (Tab, Mayús+Tab, Enter, Escape).
- Revisión de roles, estados y propiedades ARIA.
- Comprobación de textos alternativos y de la jerarquía de encabezados.

### 1.4. Criterios de mejora

Cumplir el nivel **WCAG 2.1 AA**: contraste ≥ 4.5:1 en texto normal, operabilidad total por teclado, nombres accesibles en todos los controles, foco visible y gestionado, y estructura semántica correcta.

### 1.5. Cambios previstos

Enlace «saltar al contenido», etiquetas accesibles en filtros, tarjetas y modales operables por teclado, corrección de contraste del pie de página, gestión de foco en modales y *drawer*, y marcado de elementos decorativos.

### 1.6. Evidencias a recoger

Fragmentos de código antes/después, ratios de contraste calculados, criterios WCAG asociados a cada cambio y comprobación funcional por teclado.

### 1.7. Verificación final

Repaso de los once problemas, confirmación de que cada uno queda resuelto y de que no se ha eliminado ninguna funcionalidad existente.

---
---

# INFORME DE EVALUACIÓN Y MEJORA DE LA ACCESIBILIDAD

## PORTADA

| | |
|---|---|
| **Tarea** | TAREA 08 |
| **Título** | Informe de Evaluación y Mejora de la Accesibilidad |
| **Proyecto** | PokeTrade — Plataforma web de intercambio de cartas Pokémon |
| **Módulo** | Desarrollo Web en Entorno Cliente (DWEC) |
| **Ciclo** | CFGS Desarrollo de Aplicaciones Web (DAW), 2.º curso |
| **Centro** | IES El Lago — Madrid |
| **Alumnos** | Daniel Leal y Teo Cristea |
| **Docente responsable** | *[Indicar el nombre del docente del módulo DWEC]* |
| **Fecha** | 20 de mayo de 2026 |
| **Norma de referencia** | WCAG 2.1, nivel AA |

> **Suposición declarada:** el nombre del docente no consta en el repositorio; se deja un campo señalado para completar antes de la entrega.

---

## 1. INTRODUCCIÓN

Este informe documenta la evaluación y mejora de la **accesibilidad** de la interfaz cliente de PokeTrade, una plataforma de intercambio de cartas Pokémon construida con HTML5, CSS3 y JavaScript sin frameworks sobre una API REST. La accesibilidad web busca que cualquier persona —incluidas las que usan lector de pantalla, navegan solo con teclado o tienen baja visión— pueda percibir, comprender y operar la aplicación.

El trabajo toma como referencia las **Pautas de Accesibilidad para el Contenido Web (WCAG) 2.1, nivel AA**, exigidas por el módulo. Se ha auditado el proyecto completo (15 páginas y sus archivos JavaScript y CSS), se han aplicado mejoras reales sobre el código y se ha verificado el resultado. El informe describe la accesibilidad inicial, las pruebas realizadas, los cambios implementados y la accesibilidad final conseguida.

---

## 2. ACCESIBILIDAD INICIAL

### 2.1. Aspectos correctos de partida

La auditoría confirma que el proyecto ya cumplía varios criterios:

- **Estructura semántica:** uso de `<header>`, `<nav>`, `<main>` y `<footer>`; una sola región `<main>` por página.
- **Idioma:** atributo `lang="es"` en todas las páginas (WCAG 3.1.1).
- **Títulos de página:** cada página tiene un `<title>` único y descriptivo (WCAG 2.4.2).
- **Foco visible:** la hoja `estilos.css` define `:focus-visible` con un contorno de 3 px (WCAG 2.4.7).
- **Jerarquía de encabezados:** un `<h1>` por página, secciones con `<h2>` y tarjetas con `<h3>`, sin saltos de nivel.
- **Formularios de acceso:** los formularios de login y registro fueron dotados de elemento `<form>`, validación y mensajes accesibles en la Tarea 07.

### 2.2. Problemas de accesibilidad detectados

| ID | Problema | Criterio WCAG | Nivel | Gravedad |
|----|----------|---------------|:-----:|:--------:|
| A01 | No existe enlace «saltar al contenido»: el usuario de teclado debe recorrer toda la cabecera en cada página | 2.4.1 Evitar bloques | A | Alta |
| A02 | Los controles de filtro (`#filtro-nombre`, `#filtro-tipo`, `#filtro-rareza` en catálogo; búsqueda de marketplace; buscador del modal de inventario) no tienen nombre accesible: solo *placeholder* | 1.3.1 / 3.3.2 / 4.1.2 | A | Alta |
| A03 | Las tarjetas de carta son un `<article>` con `onclick` no operable por teclado | 2.1.1 Teclado | A | Alta |
| A04 | Contraste insuficiente en el pie de página: texto `#64748b` (3.3:1) y `#475569` (2.1:1) sobre fondo oscuro `#1a252f` | 1.4.3 Contraste mínimo | AA | Alta |
| A05 | Contraste marginal en la etiqueta de tipo de carta: `#64748b` sobre `#f0f2f5` ≈ 4.2:1 (umbral 4.5:1) | 1.4.3 Contraste mínimo | AA | Media |
| A06 | Las ventanas modales (inventario y marketplace) no trasladan el foco al abrirse, no lo retienen ni lo devuelven al cerrarse | 2.4.3 Orden del foco | A | Alta |
| A07 | El botón de cierre del modal de inventario (`✕`) no tiene nombre accesible | 4.1.2 Nombre, función, valor | A | Alta |
| A08 | Las tarjetas seleccionables del modal de inventario (`<div onclick>`) no son operables por teclado | 2.1.1 Teclado | A | Media |
| A09 | Las imágenes decorativas (marcadores `🃏` y `?` cuando no hay imagen) no están marcadas como decorativas y el lector de pantalla las anuncia | 1.1.1 Contenido no textual | A | Media |
| A10 | El menú lateral móvil (*drawer*) no retiene el foco ni declara `aria-modal` | 2.1.2 / 4.1.2 | A | Media |
| A11 | Los botones de cantidad del modal (`−` / `+`) no tienen nombre accesible textual | 4.1.2 Nombre, función, valor | A | Media |

**Diagnóstico:** la interfaz era utilizable con ratón, pero presentaba **barreras serias para usuarios de teclado y de lector de pantalla**. Los problemas de mayor impacto (A01, A02, A03, A06) impedían completar tareas básicas sin ratón.

---

## 3. PRUEBAS REALIZADAS

Al no disponer el proyecto de librerías de auditoría integradas, se realizó una **auditoría manual razonada** basada en el código y en la simulación de uso asistido. **Suposición declarada:** las pruebas son una inspección técnica manual; no sustituyen una validación automatizada (Lighthouse, WAVE, axe), que se recomienda como paso complementario.

### 3.1. Revisión manual de HTML, CSS y JavaScript

Inspección de las 15 páginas y de los archivos `header.js`, `utils.js`, `inventario.js`, `marketplace.js`, `estilos.css` e `index.css` para localizar controles sin etiqueta, elementos interactivos no semánticos e imágenes sin texto alternativo.

### 3.2. Comprobación de contraste de color

Cálculo del ratio de contraste según la fórmula WCAG sobre los colores del pie de página y de las etiquetas de tarjeta:

| Elemento | Color texto | Fondo | Ratio inicial | Resultado |
|----------|:-----------:|:-----:|:-------------:|:---------:|
| Texto del pie (páginas internas y columnas del home) | `#64748b` | `#1a252f` | **3.3:1** | ❌ Falla AA |
| Copyright del pie (`.footer-bottom`) | `#475569` | `#1a252f` | **2.1:1** | ❌ Falla AA |
| Etiqueta de tipo de carta (`.carta-tipo`) | `#64748b` | `#f0f2f5` | **4.2:1** | ❌ Falla AA |
| Enlaces del pie | `#94a3b8` | `#1a252f` | 6.1:1 | ✅ Cumple |

### 3.3. Navegación con teclado

Recorrido con `Tab`, `Mayús+Tab`, `Enter`, `Espacio` y `Escape`. Hallazgos: las tarjetas de carta no recibían foco; al abrir un modal el foco permanecía en el fondo; no era posible cerrar los modales con `Escape`; el foco no regresaba al elemento de origen al cerrar.

### 3.4. Revisión de roles, estados y atributos ARIA

Comprobación de `role`, `aria-label`, `aria-labelledby`, `aria-modal`, `aria-hidden`, `aria-expanded` y `aria-live`. Hallazgos: el modal de inventario carecía de `role="dialog"`; el botón de cierre `✕` y los botones de icono no tenían nombre; el *drawer* no declaraba `aria-modal`.

### 3.5. Textos alternativos y estructura

Las imágenes reales de carta sí tenían `alt` con el nombre. Los marcadores decorativos (`🃏`, `?`) no estaban ocultos a la tecnología asistiva. La jerarquía de encabezados se verificó correcta.

---

## 4. CAMBIOS REALIZADOS

Cada cambio se justifica con la prueba y el criterio WCAG correspondientes. Todas las modificaciones se realizaron sobre la carpeta `frontend/`, sin alterar la API.

### 4.1. Enlace «saltar al contenido» — resuelve A01

Se añade, como **primer elemento enfocable** de cada página, un enlace que salta directamente a `<main>`. Es invisible hasta que recibe foco, momento en que se despliega.

- **Archivos:** `js/header.js` (inyecta el enlace y asigna `id="contenido-principal"` y `tabindex="-1"` a `<main>`), `css/estilos.css` (clase `.skip-link`).
- **Prueba:** navegación con teclado. **Criterio:** WCAG 2.4.1 (A).

```html
<!-- ANTES: no existía -->
<!-- DESPUÉS -->
<a href="#contenido-principal" class="skip-link">Saltar al contenido principal</a>
```

### 4.2. Nombres accesibles en controles de filtro — resuelve A02

Se añade `aria-label` a los campos de filtro del catálogo, del marketplace y del buscador del modal de inventario. Los contenedores de filtro reciben `role="search"` con etiqueta distintiva.

- **Archivos:** `pages/catalogo.html`, `pages/marketplace.html`, `pages/inventario.html`.
- **Prueba:** revisión ARIA. **Criterio:** WCAG 1.3.1, 3.3.2, 4.1.2 (A).

```html
<!-- ANTES -->
<select id="filtro-tipo" onchange="filtrar()"> … </select>
<!-- DESPUÉS -->
<select id="filtro-tipo" onchange="filtrar()" aria-label="Filtrar por tipo"> … </select>
```

### 4.3. Tarjetas de carta operables por teclado — resuelve A03

El título de cada tarjeta (`<h3>`) pasa a ser un **enlace real** al detalle de la carta, de modo que es enfocable y activable con teclado. El botón «+ Info» recibe un `aria-label` único por carta. Se mantiene el `onclick` del `<article>` como mejora para ratón.

- **Archivos:** `js/utils.js`, `css/estilos.css`.
- **Prueba:** navegación con teclado. **Criterio:** WCAG 2.1.1 (A), 2.4.4 (A).

```html
<!-- ANTES -->
<h3>Pikachu</h3>
<button class="btn-ver-detalle" onclick="…">+ Info</button>
<!-- DESPUÉS -->
<h3><a href="detalle-carta.html?id=1">Pikachu</a></h3>
<button class="btn-ver-detalle" onclick="…" aria-label="Ver detalle de Pikachu">+ Info</button>
```

### 4.4. Corrección de contraste del pie de página — resuelve A04

Los colores de texto del pie se sustituyen por `#94a3b8`, que alcanza un ratio de **6.1:1** sobre el fondo oscuro.

- **Archivos:** `css/estilos.css`, `css/index.css`.
- **Prueba:** cálculo de contraste. **Criterio:** WCAG 1.4.3 (AA).

| Elemento | Antes | Después |
|----------|:-----:|:-------:|
| Texto del pie | `#64748b` (3.3:1 ❌) | `#94a3b8` (6.1:1 ✅) |
| Copyright | `#475569` (2.1:1 ❌) | `#94a3b8` (6.1:1 ✅) |

### 4.5. Corrección de contraste de la etiqueta de tipo — resuelve A05

La etiqueta `.carta-tipo` cambia su color de texto a `var(--texto-sec)` (`#374151`), que ofrece un ratio de **9.2:1** sobre el fondo gris claro.

- **Archivo:** `css/estilos.css`. **Criterio:** WCAG 1.4.3 (AA).

### 4.6. Gestión de foco en ventanas modales — resuelve A06

Se incorpora a `utils.js` un par de funciones reutilizables, `abrirModalAccesible()` y `cerrarModalAccesible()`, que: trasladan el foco al modal al abrirse, **retienen el foco dentro** (tabulación cíclica), permiten **cerrar con `Escape`** y **devuelven el foco** al elemento que abrió el modal. Se aplican en el modal de inventario y en el de aceptación de tradeos del marketplace.

- **Archivos:** `js/utils.js`, `js/inventario.js`, `js/marketplace.js`.
- **Prueba:** navegación con teclado. **Criterio:** WCAG 2.4.3 (A), 2.1.2 (A).

### 4.7. Nombre accesible del botón de cierre — resuelve A07

El botón `✕` del modal de inventario recibe `aria-label="Cerrar ventana"`. El modal pasa a declararse como `role="dialog"`, `aria-modal="true"` y `aria-labelledby` apuntando a su título.

- **Archivo:** `pages/inventario.html`. **Criterio:** WCAG 4.1.2 (A), 1.3.1 (A).

### 4.8. Tarjetas seleccionables del modal operables por teclado — resuelve A08

Las tarjetas del modal de inventario reciben `role="button"`, `tabindex="0"`, `aria-label` descriptivo y un manejador de teclado que responde a `Enter` y `Espacio`.

- **Archivo:** `js/inventario.js`. **Criterio:** WCAG 2.1.1 (A), 4.1.2 (A).

### 4.9. Marcado de imágenes decorativas — resuelve A09

Los marcadores `🃏` y `?` que se muestran cuando una carta no tiene imagen se marcan con `aria-hidden="true"`, ya que el nombre de la carta ya se anuncia mediante el texto adyacente. El emoji del logotipo se envuelve igualmente en `<span aria-hidden="true">`.

- **Archivos:** `js/utils.js`, `js/header.js`, `js/inventario.js`. **Criterio:** WCAG 1.1.1 (A).

### 4.10. Retención de foco y `aria-modal` en el menú lateral — resuelve A10

El *drawer* móvil declara `aria-modal="true"` y, al estar abierto, **retiene el foco** mediante tabulación cíclica, además del cierre con `Escape` y la devolución del foco al botón hamburguesa ya existentes.

- **Archivo:** `js/header.js`. **Criterio:** WCAG 2.1.2 (A), 4.1.2 (A).

### 4.11. Nombre accesible de los botones de cantidad — resuelve A11

Los botones `−` y `+` del modal reciben `aria-label="Disminuir cantidad"` y `aria-label="Aumentar cantidad"`. El valor numérico se anuncia mediante `aria-live="polite"`.

- **Archivo:** `pages/inventario.html`. **Criterio:** WCAG 4.1.2 (A), 4.1.3 (AA).

### 4.12. Utilidades de apoyo

Se añade a `estilos.css` la clase `.sr-only` (contenido visible solo para lectores de pantalla) y los estilos del enlace de salto, disponibles para toda la aplicación.

---

## 5. ACCESIBILIDAD FINAL

Tras aplicar las mejoras, los once problemas quedan resueltos:

| ID | Problema | Estado final | Criterio WCAG |
|----|----------|:------------:|---------------|
| A01 | Enlace para saltar al contenido | ✅ Resuelto | 2.4.1 |
| A02 | Nombres accesibles en filtros | ✅ Resuelto | 1.3.1 / 3.3.2 / 4.1.2 |
| A03 | Tarjetas operables por teclado | ✅ Resuelto | 2.1.1 / 2.4.4 |
| A04 | Contraste del pie de página | ✅ Resuelto (6.1:1) | 1.4.3 |
| A05 | Contraste de la etiqueta de tipo | ✅ Resuelto (9.2:1) | 1.4.3 |
| A06 | Gestión de foco en modales | ✅ Resuelto | 2.4.3 / 2.1.2 |
| A07 | Nombre del botón de cierre | ✅ Resuelto | 4.1.2 |
| A08 | Tarjetas de modal por teclado | ✅ Resuelto | 2.1.1 |
| A09 | Imágenes decorativas marcadas | ✅ Resuelto | 1.1.1 |
| A10 | Foco y `aria-modal` en el *drawer* | ✅ Resuelto | 2.1.2 / 4.1.2 |
| A11 | Nombre de los botones de cantidad | ✅ Resuelto | 4.1.2 / 4.1.3 |

**Resultado global:** la interfaz es ahora **completamente operable mediante teclado**, todos los controles tienen nombre accesible, el contraste de color cumple el nivel AA y las ventanas modales gestionan el foco de forma correcta. La aplicación alcanza un nivel de conformidad **WCAG 2.1 AA** en los aspectos auditados.

**Recomendaciones futuras:** ejecutar una validación automatizada (Lighthouse, WAVE o axe-core) para confirmar la auditoría manual; probar con un lector de pantalla real (NVDA o VoiceOver); y revisar el contraste de los estados `hover` y `disabled` de todos los botones.

---

## 6. CONCLUSIÓN

La evaluación reveló que PokeTrade partía de una base semántica sólida pero contenía **barreras importantes para usuarios de teclado y de lector de pantalla**, principalmente la ausencia de enlace de salto, controles sin nombre accesible, tarjetas no operables sin ratón, contraste insuficiente en el pie y modales sin gestión de foco.

Las mejoras aplicadas son **reales y verificables en el código**: cada una responde a un problema concreto detectado en una prueba y se justifica con un criterio WCAG 2.1. No se ha eliminado ninguna funcionalidad; al contrario, se han añadido vías de uso (teclado) que antes no existían. La incorporación de un componente reutilizable de gestión de foco (`abrirModalAccesible` / `cerrarModalAccesible`) garantiza además que futuras ventanas modales hereden el comportamiento accesible.

El proyecto pasa de una accesibilidad **parcial** a una conformidad **WCAG 2.1 AA** en los aspectos auditados, mejorando la experiencia de uso para todas las personas y cumpliendo los requisitos académicos del módulo.

---

## 7. EVIDENCIAS Y ANEXOS

### Anexo A — Archivos modificados

| Archivo | Naturaleza del cambio |
|---------|------------------------|
| `css/estilos.css` | Clases `.sr-only` y `.skip-link`; contraste de pie y de etiqueta de tipo; enlace en título de tarjeta |
| `css/index.css` | Contraste de los textos del pie de página del *home* |
| `js/header.js` | Enlace de salto, `id` en `<main>`, `aria-modal` y retención de foco en el *drawer*, emoji decorativo |
| `js/utils.js` | Tarjetas con título enlazado, `aria-label` en botones, funciones de foco de modales, marcadores decorativos |
| `js/inventario.js` | Tarjetas de modal operables por teclado; gestión de foco al abrir/cerrar |
| `js/marketplace.js` | Gestión de foco al abrir/cerrar el modal de aceptación |
| `pages/catalogo.html` | `aria-label` y `role="search"` en los filtros |
| `pages/marketplace.html` | `aria-label` y `role="search"` en la búsqueda |
| `pages/inventario.html` | `role="dialog"`, `aria-modal`, `aria-labelledby`, nombres accesibles en botones |

### Anexo B — Espacios para capturas (comparativa antes/después)

*[Inserta captura ANTES: navegación con teclado sin enlace de salto]*
*[Inserta captura DESPUÉS: enlace «Saltar al contenido» visible al recibir foco]*

*[Inserta captura ANTES: pie de página con texto de bajo contraste]*
*[Inserta captura DESPUÉS: pie de página con contraste AA]*

*[Inserta captura ANTES: modal de inventario sin foco gestionado]*
*[Inserta captura DESPUÉS: modal con foco retenido y botón de cierre etiquetado]*

---
---

## FASE 7 — RÚBRICA FINAL PARA EL PDF DE ENTREGA

Rúbrica breve para verificar el documento PDF antes de entregarlo.

| Criterio evaluado | Qué se ha hecho | Evidencia de cumplimiento | Resultado |
|-------------------|-----------------|----------------------------|:---------:|
| Estructura semántica | Verificada; `header`/`nav`/`main`/`footer` correctos | Sección 2.1 del informe | ✅ |
| Jerarquía de encabezados | Auditada: un `<h1>` por página, sin saltos | Sección 2.1 | ✅ |
| Enlace de salto al contenido | Añadido como primer elemento enfocable | Cambio 4.1 | ✅ |
| Textos alternativos | Imágenes con `alt`; decorativas con `aria-hidden` | Cambio 4.9 | ✅ |
| Contraste de color (AA) | Pie y etiquetas corregidos a ≥ 6:1 | Cambios 4.4 y 4.5; tabla 3.2 | ✅ |
| Navegación por teclado | Tarjetas, modales y filtros operables sin ratón | Cambios 4.3, 4.6, 4.8 | ✅ |
| Nombres accesibles (ARIA) | `aria-label` en filtros, botones de icono y modales | Cambios 4.2, 4.7, 4.11 | ✅ |
| Gestión y orden del foco | Foco trasladado, retenido y restaurado en modales y *drawer* | Cambios 4.6 y 4.10 | ✅ |
| Formularios accesibles | `<form>`, etiquetas y errores (Tarea 07) | Sección 2.1 | ✅ |
| Estados interactivos | Foco visible mediante `:focus-visible` | Sección 2.1 | ✅ |
| Portada e identificación | TAREA, número, título, proyecto, alumnos y docente | Portada | ✅ (completar docente) |
| Documento exportable a PDF único | Informe en Markdown listo para exportar | Este archivo | ✅ |

**Instrucción de entrega:** completar el nombre del docente en la portada, incorporar las capturas en los marcadores `[Inserta captura …]` y exportar el documento a un **único archivo PDF**.

---

*Fin del informe — Tarea 08, PokeTrade. IES El Lago, Madrid. Daniel Leal y Teo Cristea, 20 de mayo de 2026.*

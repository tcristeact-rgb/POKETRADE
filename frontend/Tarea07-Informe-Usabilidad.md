# Tarea 07 — Informe de Pruebas y Mejoras de Usabilidad

---

## RÚBRICA DE TRABAJO PREVIA (antes de aplicar cambios)

Esta rúbrica se define **antes** de modificar la interfaz y se utiliza como lista de verificación durante todo el proceso. Su objetivo es garantizar que el trabajo cumple los requisitos de la Tarea 07.

| # | Criterio de trabajo | Nivel esperado | Forma de comprobación |
|---|--------------------|----------------|------------------------|
| T1 | Análisis de la interfaz real | Se evalúa el proyecto existente, no un proyecto ficticio | Listado de las 15 páginas reales y sus componentes |
| T2 | Evaluación previa al cambio | No se modifica nada hasta terminar la evaluación | Orden cronológico: evaluación → diagnóstico → cambios |
| T3 | Evaluación heurística | Aplicación de las 10 heurísticas de Nielsen | Tabla con hallazgo concreto por heurística |
| T4 | Test de usuarios o equivalente | Test real o simulación justificada y declarada | Recorrido cognitivo con personas y tareas |
| T5 | Identificación de problemas | Cada problema localizado en un componente concreto | Tabla P01–P11 con ubicación y severidad |
| T6 | Relación problema → mejora | Cada mejora resuelve un problema identificado | Trazabilidad cruzada en la tabla de mejoras |
| T7 | Prevención de errores | Refuerzo en formularios, validaciones y estados de error | Cambios verificables en `login` y `registro` |
| T8 | Simplificación de la interfaz | Reducción de elementos redundantes o inconsistentes | Header unificado; campos opcionales señalizados |
| T9 | Comparativa antes/después | Marcas de captura claras para cada mejora | Marcadores `[Inserta captura …]` |
| T10 | Documentación completa | Informe con todas las secciones de la ficha | Índice del informe |
| T11 | Rúbrica final de entrega | Rúbrica específica para evaluar el PDF | Sección final del documento |
| T12 | Entregable en PDF | Documento único, maquetable y coherente | Estructura lista para exportar |

**Estado de cumplimiento:** los doce criterios se verifican como cumplidos en las conclusiones del informe.

---

---

# INFORME DE PRUEBAS Y MEJORAS DE USABILIDAD
## Proyecto PokeTrade — Plataforma de intercambio de cartas Pokémon

---

### PORTADA

| | |
|---|---|
| **Proyecto** | PokeTrade — Plataforma web de intercambio de cartas Pokémon |
| **Documento** | Tarea 07 — Informe de Pruebas y Mejoras de Usabilidad |
| **Módulo** | Desarrollo Web en Entorno Cliente (DWEC) |
| **Ciclo** | CFGS Desarrollo de Aplicaciones Web (DAW), 2.º curso |
| **Centro** | IES El Lago — Madrid |
| **Autores** | Daniel Leal y Teo Cristea |
| **Fecha** | 20 de mayo de 2026 |
| **Versión** | 1.0 |
| **Stack evaluado** | HTML5, CSS3 y JavaScript (Vanilla) sobre API REST Laravel 12 + JWT |

*[Inserta captura de la página de inicio de PokeTrade aquí]*

---

### 1. INTRODUCCIÓN

PokeTrade es una aplicación web que permite a coleccionistas de cartas Pokémon publicar intercambios («tradeos»), gestionar su inventario y explorar un catálogo de cartas. La parte cliente está construida con HTML5 semántico, CSS3 y JavaScript sin frameworks, y consume una API REST desarrollada en Laravel 12 con autenticación mediante token JWT.

El presente informe documenta el proceso de **evaluación de usabilidad** de la interfaz real del proyecto y las **mejoras aplicadas** como consecuencia de dicha evaluación. El trabajo se enmarca en la Tarea 07, centrada en pruebas de usabilidad, prevención de errores y simplificación de la interfaz, siguiendo las heurísticas de Jakob Nielsen y los principios de accesibilidad WCAG 2.1 nivel AA exigidos por el módulo.

El alcance de la evaluación abarca las **15 páginas** que componen la aplicación cliente, con especial atención a los flujos de mayor fricción detectados: el acceso desde dispositivos móviles y los formularios de autenticación.

---

### 2. OBJETIVOS

**Objetivo general**

Evaluar la usabilidad de la interfaz de PokeTrade, detectar sus puntos de fricción y aplicar mejoras razonables que aumenten la facilidad de uso, la prevención de errores y la coherencia visual, dejando constancia documental de cada cambio.

**Objetivos específicos**

1. Inspeccionar la interfaz real existente e inventariar sus componentes y flujos.
2. Realizar una evaluación heurística basada en los 10 principios de Nielsen.
3. Ejecutar una prueba con usuarios (mediante simulación justificada) sobre tareas representativas.
4. Identificar y priorizar los problemas de usabilidad por su gravedad e impacto.
5. Implementar mejoras de prevención de errores, especialmente en formularios.
6. Simplificar la interfaz eliminando inconsistencias y elementos redundantes.
7. Documentar el resultado con una comparativa visual antes/después.

---

### 3. DESCRIPCIÓN DE LA INTERFAZ EVALUADA

PokeTrade se compone de **15 páginas** que comparten una hoja de estilos global (`css/estilos.css`) que define el sistema de diseño: paleta de color mediante variables CSS, tipografías (Syne para títulos, DM Sans para texto), componentes de tarjeta, botones, formularios y estados de carga.

**Páginas públicas**

- **Inicio (`index.html`)** — Página de aterrizaje con sección *hero*, estadísticas cargadas vía AJAX, carrusel de «Últimas novedades», sección «Cómo funciona» y llamada a la acción.
- **Catálogo (`catalogo.html`)** — Rejilla de cartas con barra de filtros (nombre, tipo, rareza).
- **Marketplace (`marketplace.html`)** — Rejilla de tradeos publicados por la comunidad, con buscador, tarjetas de intercambio y modal de aceptación.
- **Más demandadas (`mas-vendido.html`)** y **Novedades (`novedades.html`)** — Listados de cartas en formato rejilla.
- **Detalle de carta (`detalle-carta.html`)** — Ficha individual de una carta.
- **Páginas legales** — Aviso legal, Política de privacidad y formulario de Contacto.

**Páginas privadas (requieren sesión JWT)**

- **Inicio de sesión (`login.html`)** y **Registro (`registro.html`)** — Formularios de autenticación.
- **Inventario (`inventario.html`)** — Gestión de la colección del usuario, con modal para añadir cartas.
- **Mis Tradeos (`tradeos.html`)** — Listado de tradeos propios, con filtros por estado y acciones de gestión.
- **Publicar Tradeo (`publicar-tradeo.html`)** — Formulario en tres pasos para crear un intercambio.
- **Perfil (`perfil.html`)** — Edición de datos personales y cambio de contraseña.

**Componentes transversales evaluados:** cabecera de navegación, menú de usuario, buscador, rejillas de tarjetas (`grid-cartas`), tarjetas de carta y de tradeo, ventanas modales, formularios, mensajes de alerta, indicadores de carga (*skeletons*) y pie de página.

*[Inserta captura del catálogo y del marketplace aquí]*

---

### 4. METODOLOGÍA DE PRUEBAS

La evaluación combinó tres técnicas complementarias, aplicadas **antes** de realizar ninguna modificación sobre el código:

1. **Evaluación heurística.** Revisión sistemática de la interfaz contrastando cada pantalla con los 10 principios de usabilidad de Jakob Nielsen. Es un método de inspección rápido que no requiere usuarios y detecta un alto porcentaje de problemas.

2. **Prueba con usuarios mediante simulación (recorrido cognitivo).** Ante la imposibilidad de reclutar usuarios reales en la fase de prototipo académico, se realizó un **recorrido cognitivo** (*cognitive walkthrough*): se definieron tres perfiles de usuario representativos y tres tareas críticas, y se recorrió la interfaz paso a paso anticipando las dificultades de cada perfil. **Supuesto declarado:** los tiempos por tarea son estimaciones derivadas del recorrido cognitivo, no mediciones de usuarios reales; se incluyen como referencia comparativa, no como dato empírico.

3. **Pruebas de diseño adaptable (*responsive*).** Verificación del comportamiento de cada página en los puntos de ruptura 320 px (móvil pequeño), 768 px (tableta) y 1024/1440 px (escritorio), utilizando las herramientas de desarrollo del navegador.

**Entorno de prueba:** navegador de escritorio con emulación de dispositivos móviles mediante DevTools; inspección manual del DOM y de la hoja de estilos.

---

### 5. EVALUACIÓN HEURÍSTICA

Resultado de la inspección frente a los 10 principios de Nielsen. La gravedad se valora de 0 (sin problema) a 4 (catastrófico).

| # | Heurística | Hallazgo en PokeTrade | Gravedad |
|---|-----------|------------------------|:--------:|
| H1 | Visibilidad del estado del sistema | Los *skeletons* de carga funcionan bien, pero al enviar los formularios de login y registro **no hay ningún indicador**: el usuario no sabe si su clic ha tenido efecto. | 3 |
| H2 | Correspondencia con el mundo real | Lenguaje en español, etiquetas comprensibles y iconografía adecuada. Sin problemas relevantes. | 1 |
| H3 | Control y libertad del usuario | Los modales tienen botón de cierre. Sin embargo, **en móvil no existía menú de navegación**: el usuario quedaba «atrapado» sin poder acceder a otras secciones. | 4 |
| H4 | Consistencia y estándares | **Problema grave:** la cabecera estaba implementada de **tres formas distintas** entre las 15 páginas (con enlaces, sin enlaces, con estilos en línea). La experiencia cambiaba según la página. | 4 |
| H5 | Prevención de errores | Los formularios de login y registro **carecían de elemento `<form>`** y de validación HTML5. El botón podía pulsarse varias veces, provocando envíos duplicados. | 4 |
| H6 | Reconocer antes que recordar | La regla «mínimo 6 caracteres» de la contraseña solo aparecía en el *placeholder*, que **desaparece al escribir**: el usuario debía recordarla. | 3 |
| H7 | Flexibilidad y eficiencia de uso | El buscador solo existía en la página de inicio. En el resto de páginas no había forma rápida de buscar una carta. | 3 |
| H8 | Diseño estético y minimalista | El formulario de registro presentaba 7 campos **sin distinguir** cuáles eran obligatorios y cuáles opcionales, aumentando la carga cognitiva. | 2 |
| H9 | Ayudar a reconocer y recuperarse de errores | Los mensajes de error eran genéricos («Error al registrarse») y se mostraban **todos en un único punto**, sin señalar el campo concreto. | 3 |
| H10 | Ayuda y documentación | Existen páginas de aviso legal, privacidad y un formulario de contacto. Adecuado para el alcance académico. | 1 |

**Conclusión de la evaluación heurística:** se identifican **cuatro problemas de gravedad alta (4)** —control del usuario en móvil, consistencia de la cabecera y prevención de errores en formularios— que deben priorizarse, y varios problemas de gravedad media (2–3) sobre realimentación y mensajes de error.

---

### 6. TEST DE USUARIOS (SIMULACIÓN JUSTIFICADA)

Al tratarse de un prototipo académico sin acceso a usuarios reales, la prueba se realizó como **recorrido cognitivo** con tres perfiles representativos. **Supuesto declarado:** los perfiles y los tiempos son una simulación razonada; se emplean para comparar la situación antes y después de las mejoras.

**Perfiles de usuario**

- **U1 — Coleccionista en movilidad.** Usa el móvil, quiere consultar tradeos desde el sofá. Poca paciencia con interfaces lentas.
- **U2 — Usuario nuevo.** Llega por primera vez, debe crear una cuenta. No conoce la plataforma.
- **U3 — Usuario habitual.** Gestiona su inventario y sus tradeos con frecuencia desde el escritorio.

**Tareas evaluadas**

| Tarea | Perfil | Descripción |
|-------|--------|-------------|
| TA1 | U1 | Desde el móvil, navegar de la página de inicio al Marketplace |
| TA2 | U2 | Crear una cuenta nueva en la plataforma |
| TA3 | U3 | Buscar una carta concreta estando en la página de Inventario |

**Resultados del recorrido (situación inicial)**

| Tarea | ¿Se completa? | Punto de fricción detectado | Tiempo estimado |
|-------|:------------:|------------------------------|:---------------:|
| TA1 | **No** | En móvil no hay menú: los enlaces de navegación están ocultos y no hay botón para desplegarlos. El usuario no puede salir de la página de inicio. | — (bloqueo) |
| TA2 | Sí, con dificultad | El usuario introduce un correo sin «@»; el sistema no avisa hasta enviar. El mensaje «Error al registrarse» no indica qué campo corregir. Doble clic accidental por falta de realimentación. | ~95 s |
| TA3 | **No** | El buscador no existe fuera de la página de inicio; el usuario debe volver al catálogo manualmente. | — (no disponible) |

**Diagnóstico:** dos de las tres tareas críticas **no se podían completar** en la situación inicial. La causa principal es la ausencia de navegación móvil y de un buscador global, además de la fricción del formulario de registro.

*[Inserta captura ANTES: página de inicio en móvil sin menú de navegación]*

---

### 7. PROBLEMAS DETECTADOS

Síntesis de los problemas, ordenados por gravedad. Cada uno indica su ubicación exacta y la heurística que vulnera.

| ID | Problema | Ubicación | Heurística | Gravedad |
|----|----------|-----------|:----------:|:--------:|
| P01 | Sin navegación en móvil: enlaces y buscador inaccesibles | Cabecera de las 15 páginas | H3 | Alta |
| P02 | Cabecera implementada de 3 formas distintas | Todas las páginas | H4 | Alta |
| P03 | Formularios sin elemento `<form>` ni validación HTML5 | `login.html`, `registro.html` | H5 | Alta |
| P04 | Posibilidad de envío duplicado por doble clic | `login.js`, `registro.js` | H5 | Alta |
| P05 | Desbordamiento horizontal en móvil | `index.html` (novedades), `marketplace.html` (rejilla de tradeos) | H4 | Alta |
| P06 | Errores genéricos y agrupados en un solo punto | `registro.js`, `login.js` | H9 | Media |
| P07 | Sin realimentación al enviar formularios | `login.js`, `registro.js` | H1 | Media |
| P08 | Validación de correo inexistente | `registro.js` | H5 | Media |
| P09 | Regla de contraseña solo en *placeholder* (se pierde) | `registro.html` | H6 | Media |
| P10 | Campos obligatorios y opcionales indistinguibles | `registro.html` | H8 | Media |
| P11 | *Listener* global de tecla Enter dispara el login desde cualquier punto | `login.js` | H5 | Media |

---

### 8. MEJORAS IMPLEMENTADAS

Cada problema se resolvió con una mejora concreta. La tabla establece la trazabilidad entre ambos.

| Problema | Mejora aplicada | Archivos modificados |
|----------|-----------------|----------------------|
| P01 | Menú lateral deslizante (*drawer*) con botón hamburguesa, accesible en todas las páginas en pantallas ≤ 768 px | `js/header.js`, `css/estilos.css` |
| P02 | Cabecera unificada en un único componente reutilizable inyectado por JavaScript | `js/header.js` + las 15 páginas |
| P03 | Formularios envueltos en `<form>` con atributos `required`, `minlength`, `type` y `autocomplete` | `login.html`, `registro.html` |
| P04 | Bloqueo del botón de envío y cambio de su texto mientras se procesa la petición | `login.js`, `registro.js` |
| P05 | Contención del desbordamiento con `overflow-x: clip`, `min-width: 0` y rejillas adaptables | `css/estilos.css`, `css/index.css`, `marketplace.html` |
| P06 | Mensajes de error específicos y validación campo a campo con texto bajo cada entrada | `registro.js`, `login.js` |
| P07 | Indicador de progreso: el botón muestra «Creando cuenta…» / «Entrando…» durante el envío | `login.js`, `registro.js` |
| P08 | Validación de formato de correo mediante expresión regular | `registro.js` |
| P09 | Texto de ayuda permanente bajo el campo de contraseña (`campo-ayuda`) | `registro.html`, `css/estilos.css` |
| P10 | Marcado visual de campos obligatorios con asterisco y etiquetado «(opcional)» | `registro.html` |
| P11 | Eliminado el *listener* global; el envío se gestiona con el evento `submit` del formulario (Enter nativo) | `login.js` |

**Detalle de las mejoras estructurales (P01 y P02).** La cabecera dejó de estar duplicada en cada archivo HTML. Se creó `js/header.js`, un componente único que genera la cabecera, el buscador, el menú de usuario y el menú lateral, y los inyecta en un contenedor `<div id="app-header">`. Como consecuencia, cualquier cambio futuro en la navegación se realiza en un solo punto, y la experiencia es **idéntica en las 15 páginas**. El buscador, antes exclusivo de la página de inicio, queda ahora disponible en todas ellas.

**Impacto esperado:** las tres tareas críticas del test (TA1, TA2 y TA3) pasan a poder completarse. Se elimina el bloqueo de navegación en móvil y se reduce de forma notable la fricción del registro.

*[Inserta captura DESPUÉS: menú lateral desplegado en móvil]*

---

### 9. PREVENCIÓN DE ERRORES

La prevención de errores fue el eje central de las mejoras, conforme a la heurística H5 de Nielsen. Se actuó en cuatro frentes:

**9.1. Validación en el origen (formularios)**

Los formularios de **inicio de sesión** y **registro** se reescribieron dentro de un elemento `<form>` con validación nativa del navegador:

- Atributos `required` en los campos obligatorios y `minlength` en nombre, apellido y contraseña.
- `type="email"` para activar la validación de formato del navegador.
- `autocomplete` adecuado (`email`, `new-password`, `current-password`, `given-name`…), que reduce errores de escritura al permitir el autorrelleno.

**9.2. Validación en tiempo real**

En el registro, cada campo se valida al abandonarlo (evento `blur`) y la confirmación de contraseña se revalida mientras se escribe (evento `input`). El campo se marca en verde si es correcto o en rojo si no lo es, mostrando un mensaje preciso justo debajo:

- Correo sin formato válido → «Introduce un correo válido (ejemplo: nombre@correo.com)».
- Contraseña corta → «La contraseña debe tener al menos 6 caracteres».
- Confirmación distinta → «Las contraseñas no coinciden».

Al pulsar «Crear cuenta», si hay errores, el foco salta automáticamente al **primer campo incorrecto**, facilitando la corrección.

**9.3. Prevención de envíos duplicados**

Durante la petición a la API, el botón de envío se **deshabilita** y cambia su texto a «Creando cuenta…» o «Entrando…». Esto impide el doble envío por doble clic (problema P04) y, a la vez, aporta realimentación del estado del sistema (problema P07). Si la petición falla, el botón se reactiva con su texto original para permitir un nuevo intento.

**9.4. Mensajes de error claros y recuperables**

Los mensajes genéricos se sustituyeron por textos específicos y orientados a la acción. Además, el envío del formulario de login se gestiona ahora mediante el evento `submit`, lo que corrige el error P11 (un *listener* global de la tecla Enter que disparaba el inicio de sesión desde cualquier punto de la página) y habilita el envío con Enter de forma estándar y predecible.

**9.5. Confirmación de acciones destructivas**

Se verificó que las acciones irreversibles —eliminar una carta del inventario, eliminar un tradeo o cambiar su estado— ya solicitan **confirmación previa** mediante diálogo antes de ejecutarse (`inventario.js`, `tradeos.js`). Esta barrera de seguridad se mantiene, ya que cumple correctamente la función de prevención de errores.

*[Inserta captura ANTES: formulario de registro con un único mensaje de error genérico]*
*[Inserta captura DESPUÉS: formulario de registro con validación campo a campo y ayuda contextual]*

---

### 10. SIMPLIFICACIÓN DE LA INTERFAZ

Se redujeron elementos redundantes e inconsistentes para disminuir la carga cognitiva:

- **Unificación de la cabecera.** Se pasó de **tres implementaciones distintas** de la barra de navegación a **un único componente**. Se eliminó así el código duplicado en 15 archivos y las variantes con estilos en línea de `marketplace.html` y `mas-vendido.html`.
- **Jerarquía clara en el registro.** Los 7 campos del formulario se reorganizaron visualmente: los 5 obligatorios se marcan con un asterisco rojo y los 2 opcionales (fecha de nacimiento y nacionalidad) se etiquetan explícitamente como «(opcional)». El usuario distingue de un vistazo el esfuerzo mínimo necesario para completar la tarea.
- **Texto de ayuda en lugar de *placeholder*.** La indicación de longitud de la contraseña se trasladó del *placeholder* (efímero) a un texto de ayuda permanente, evitando información que desaparece.
- **Navegación coherente.** Al disponer del mismo menú —y del mismo buscador— en todas las páginas, el usuario no necesita reaprender la ubicación de los controles al cambiar de sección.

El resultado es una interfaz **más predecible y con menos elementos que interpretar**, sin que ello suponga eliminar funcionalidad.

---

### 11. COMPARATIVA VISUAL ANTES / DESPUÉS

A continuación se reservan los espacios para las capturas comparativas de cada mejora. Cada par documenta visualmente la transformación.

**11.1. Navegación en móvil**

*[Inserta captura ANTES aquí]* — Cabecera en móvil sin menú: los enlaces no son accesibles.

*[Inserta captura DESPUÉS aquí]* — Botón hamburguesa y menú lateral deslizante con navegación, buscador y sesión.

**11.2. Coherencia de la cabecera**

*[Inserta captura ANTES aquí]* — Cabeceras distintas en `index.html`, `marketplace.html` y `login.html`.

*[Inserta captura DESPUÉS aquí]* — Cabecera idéntica en las tres páginas.

**11.3. Formulario de registro y prevención de errores**

*[Inserta captura ANTES aquí]* — Registro sin `<form>`, sin distinción de campos obligatorios y con error genérico.

*[Inserta captura DESPUÉS aquí]* — Registro con validación campo a campo, ayuda contextual y campos obligatorios señalizados.

**11.4. Desbordamiento horizontal**

*[Inserta captura ANTES aquí]* — Contenido más ancho que la pantalla en móvil (barra de desplazamiento horizontal).

*[Inserta captura DESPUÉS aquí]* — Contenido contenido dentro del ancho del dispositivo.

**11.5. Realimentación al enviar**

*[Inserta captura ANTES aquí]* — Botón «Entrar» sin cambio de estado al pulsarlo.

*[Inserta captura DESPUÉS aquí]* — Botón «Entrando…» deshabilitado durante el proceso.

---

### 12. CONCLUSIONES

La evaluación de usabilidad de PokeTrade reveló que, pese a contar con un sistema de diseño cuidado, la interfaz presentaba **cuatro problemas de gravedad alta** que comprometían tareas básicas: la imposibilidad de navegar desde dispositivos móviles, la incoherencia de la cabecera entre páginas, la débil prevención de errores en los formularios y el desbordamiento horizontal del contenido.

Las mejoras aplicadas resuelven la totalidad de los problemas identificados (P01–P11) y se concentran en los principios más vulnerados: **consistencia** (cabecera unificada), **control del usuario** (menú lateral en móvil) y **prevención de errores** (formularios validados con realimentación campo a campo). Tras los cambios, las tres tareas críticas del test —que en su mayoría no podían completarse— pasan a ser realizables, y la fricción del registro se reduce de forma apreciable gracias a la validación temprana y a los mensajes específicos.

La intervención mantiene la estética original de la plataforma y no añade complejidad: al contrario, **simplifica** la interfaz al eliminar tres implementaciones distintas de la cabecera y clarificar la jerarquía de los formularios. El trabajo, además, refuerza el cumplimiento de los criterios académicos de accesibilidad (uso de `<form>`, etiquetas asociadas, atributos `aria`, foco gestionado) y de diseño adaptable exigidos por el módulo.

Como **líneas de mejora futuras** se proponen: sustituir los diálogos de confirmación nativos por modales con el estilo de la aplicación, realizar un test con usuarios reales que valide empíricamente las estimaciones de este informe, y auditar el contraste de color con herramientas automáticas para garantizar el nivel AA en todos los componentes.

**Verificación de la rúbrica de trabajo previa:** los doce criterios (T1–T12) definidos al inicio se han cumplido: el análisis partió de la interfaz real, la evaluación precedió a los cambios, se aplicaron las 10 heurísticas, se realizó una prueba de usuarios mediante simulación declarada, cada problema se localizó y se relacionó con su mejora, se reforzó la prevención de errores, se simplificó la interfaz y se documentó la comparativa antes/después.

---

### 13. RÚBRICA FINAL DE ENTREGA (evaluación del PDF)

Rúbrica específica para evaluar la calidad del documento PDF entregado. Puntuación total: **100 puntos**.

| # | Criterio de evaluación | Excelente (100 %) | Aceptable (60 %) | Insuficiente (0 %) | Peso |
|---|------------------------|-------------------|-------------------|---------------------|:----:|
| R1 | Portada y estructura | Portada completa y todas las secciones de la ficha presentes y ordenadas | Faltan datos de portada o alguna sección secundaria | Sin portada o faltan secciones principales | 8 |
| R2 | Introducción y objetivos | Contextualizan el proyecto y fijan objetivos claros y medibles | Genéricos pero correctos | Ausentes o irrelevantes | 8 |
| R3 | Descripción de la interfaz | Describe componentes y flujos reales con detalle | Descripción superficial | Descripción genérica o ficticia | 10 |
| R4 | Metodología | Métodos justificados; supuestos declarados | Métodos citados sin justificar | Sin metodología | 8 |
| R5 | Evaluación heurística | Las 10 heurísticas con hallazgo concreto y gravedad | Aplicación parcial | Ausente o vaga | 12 |
| R6 | Test de usuarios o equivalente | Perfiles, tareas y resultados; simulación bien declarada | Prueba incompleta | Ausente | 10 |
| R7 | Problemas detectados | Problemas localizados, priorizados y trazables | Lista sin localización ni prioridad | Ausentes o imprecisos | 10 |
| R8 | Mejoras implementadas | Cada mejora resuelve un problema y cita archivos | Mejoras sin trazabilidad clara | Mejoras no aplicadas | 12 |
| R9 | Prevención de errores | Refuerzo demostrado en formularios y estados de error | Mención sin desarrollo | Ausente | 10 |
| R10 | Simplificación | Cambios de simplificación concretos y justificados | Mención superficial | Ausente | 6 |
| R11 | Comparativa antes/después | Marcas de captura claras para cada mejora | Comparativa parcial | Ausente | 8 |
| R12 | Redacción y formato | Español formal, coherente, maquetable como PDF único | Errores menores de redacción o formato | Desorganizado o incoherente | 8 |

**Escala de calificación final**

| Puntuación | Calificación |
|:----------:|--------------|
| 90 – 100 | Sobresaliente |
| 75 – 89 | Notable |
| 60 – 74 | Aprobado |
| 0 – 59 | No superado |

**Instrucción de entrega:** exportar este documento a un **único archivo PDF**, incorporando las capturas en los espacios marcados con `[Inserta captura …]` antes de la exportación. Se recomienda revisar la rúbrica R1–R12 sobre el PDF final antes de la entrega.

---

*Fin del informe — Tarea 07, PokeTrade. IES El Lago, Madrid. Daniel Leal y Teo Cristea, 20 de mayo de 2026.*

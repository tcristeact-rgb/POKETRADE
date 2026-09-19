---
estado: implementada
fecha: 2026-09-19
origen: propietario + claude-code
---

# Portada: carrusel infinito de cartas aleatorias; registro sin nacionalidad

## Problema

1. El formulario de registro pide la nacionalidad. No aporta nada al
   producto y es un campo más entre la persona y su cuenta.
2. La sección "Últimas novedades" de la portada enseña las 8 cartas con id
   más alto. Como el cache-aside inserta sets enteros de golpe, "lo último"
   es siempre el último set que alguien abrió: la portada apenas cambia.
3. Esa sección es una fila con scroll horizontal y barra de desplazamiento.
   Con ratón hay que arrastrar la barra o hacer scroll lateral, y se acaba.

## Decisión

### 1. Registro sin nacionalidad

- Fuera el grupo `nacionalidad` de `registro.html` y del payload de
  `registro.js`. La API sigue aceptándola como opcional (`nullable`): no hay
  motivo para romper clientes ni para una migración. El perfil (`perfil.html`)
  conserva el campo: es otro formulario y no se ha pedido tocarlo.
- Se elimina la clave i18n `auth.nacionalidadPlaceholder` (solo la usaba el
  registro). `auth.nacionalidad` se queda: la usa el perfil.

### 2. "Cartas destacadas" aleatorias

- Endpoint nuevo `GET /api/cartas/aleatorias?cantidad=N` (público, N entre 1
  y 24, 12 por defecto): N cartas **con imagen** en orden aleatorio
  (`inRandomOrder()`, portable SQLite/PostgreSQL), sin caché — que cada
  visita sea distinta es el objetivo. Va en `routes/api.php` antes de
  `/cartas/{id}`. Devuelve un array plano (como `/cartas/destacadas`).
- La sección pasa a titularse "Cartas destacadas" / "Featured cards"; el
  enlace "Ver todos →" lleva al catálogo. La página `novedades.html` no se
  toca: sigue en el menú.
- Para no tener dos regiones con el mismo nombre en la página, el
  `aria-label` del escaparate del hero pasa de "Cartas destacadas" a
  "Escaparate" / "Showcase".

### 3. Carrusel infinito con flechas

- La fila deja de ser scroll nativo: `overflow: hidden`, sin barra. Dos
  flechas (reutilizan `.carrusel-flecha` y las claves `comun.cartaAnterior`
  / `comun.cartaSiguiente`) mueven la pista una carta cada vez.
- **Infinito por rotación del DOM**, sin clones: al avanzar, la pista se
  desplaza una carta con `transform` y, al acabar la transición, la primera
  carta pasa al final y el desplazamiento vuelve a 0 sin transición. Al
  retroceder, la última pasa al principio, la pista se coloca en
  `-1 carta` sin transición y se anima a 0. Con N cartas el bucle no tiene
  fin ni principio.
- Si todas las cartas caben en el ancho visible, las flechas se ocultan y
  la fila se queda quieta.
- Sin autoplay: se mueve solo cuando se le pide. `prefers-reduced-motion`
  → el salto es instantáneo.
- Teclado: las tarjetas son enlaces y siguen en el orden del DOM. Si el
  foco entra en una carta que no está visible, la pista rota (sin animar)
  para ponerla la primera, y se anula el scroll que el navegador hace al
  enfocar dentro de un `overflow: hidden`.
- El módulo vive en `frontend/js/inicio.js` (misma página); `tarjetaCarta`
  de `utils.js` pinta cada carta como en cualquier grid.

## Alternativas descartadas

- **Scroll nativo con barra oculta + flechas que hacen `scrollBy`**: no es
  infinito; para serlo hay que clonar cartas en los extremos y saltar de
  posición al llegar a ellas, y el salto se nota con `scroll-behavior:
  smooth`.
- **`?orden=aleatorio` en `/cartas`**: paginar un orden aleatorio no tiene
  sentido (la página 2 repite cartas). Un endpoint de N cartas es más
  honesto.
- **Quitar la nacionalidad también del perfil y de la BD**: no se ha pedido
  y es dato de usuarios existentes. Si se quiere, es otra spec con migración.

## Criterios de aceptación

**Backend** (`tests/Feature/CartasAleatoriasTest.php`, nuevo)

- [ ] `GET /api/cartas/aleatorias` devuelve 12 cartas, todas con imagen; con
      `?cantidad=5`, 5; `?cantidad=100` se acota a 24; sin cartas → `[]`.
- [ ] No llama a TCGdex.
- [ ] `composer test` en verde.

**Frontend** (`tests-e2e/`)

- [ ] `registro.html` no tiene ningún campo `nacionalidad`, y el registro
      sigue funcionando sin él (el e2e de verificación de correo, que registra
      una cuenta, no toca ese campo — se comprueba que no exista).
- [ ] La portada tiene la sección "Cartas destacadas" con N > 4 tarjetas, sin
      barra de scroll (`overflow` no es `auto`/`scroll`) y con dos flechas.
- [ ] Pulsar "Siguiente" cambia la primera carta visible; pulsar "Anterior"
      la devuelve. Tras N pulsaciones de "Siguiente" se vuelve a la carta
      inicial (bucle).
- [ ] Textos en ES y EN; ningún `package.json` bajo `frontend/`.

## Archivos afectados (previsión)

- `frontend/pages/registro.html`, `frontend/js/registro.js`
- `api/app/Http/Controllers/CartaController.php`, `api/routes/api.php`
- `api/tests/Feature/CartasAleatoriasTest.php` — nuevo
- `frontend/index.html`, `frontend/js/inicio.js`, `frontend/css/estilos.css`
- `frontend/js/i18n/{es,en}.js`
- `tests-e2e/tests/portada.spec.mjs` — nuevo

## Fuera de alcance

- Quitar la nacionalidad del perfil o de la base de datos.
- Autoplay o swipe táctil en el carrusel nuevo (el hero ya tiene swipe).
- Cambiar el carrusel del hero.

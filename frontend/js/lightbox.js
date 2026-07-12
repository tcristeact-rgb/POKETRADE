// lightbox.js — Zoom de ilustraciones de cartas (módulo ES6)
// Popup modal reutilizable, sin librerías: al abrirlo muestra la
// versión ligera (low.webp) como placeholder y la sustituye por la de
// alta calidad (high.webp) cuando termina de cargar. Cierre con X,
// click fuera o Escape; flechas ←/→ para moverse entre las cartas del
// mismo grid sin cerrar. La gestión de foco (trampa + devolución al
// cerrar) reutiliza abrirModalAccesible/cerrarModalAccesible de utils.

import { paginaUrl } from './auth.js';
import { abrirModalAccesible, cerrarModalAccesible, URL_DORSO } from './utils.js';

let lightbox = null;   // Nodo raíz (se crea una sola vez, al primer uso)
let cartas   = [];     // Cartas navegables en la sesión de zoom actual
let indice   = 0;
let conFicha = false;  // ¿Mostrar el enlace a la ficha completa?

// ── API pública ────────────────────────────────────

// Abre el lightbox sobre la carta `posicion` de la lista `lista`.
// Cada carta necesita nombre y alguna imagen (imagen_high/imagen_low).
//
// opciones.enlaceFicha añade una salida al detalle de la carta. Va por
// opción y no siempre porque solo tiene sentido donde no hay otro camino:
// se abre desde el modal de un tradeo, que es un callejón. En el catálogo
// la propia tarjeta ya enlaza al detalle, y en la ficha ya estás en ella.
export function abrirLightbox(lista, posicion = 0, opciones = {}) {
    cartas   = lista;
    indice   = posicion;
    conFicha = opciones.enlaceFicha === true;

    crearLightbox();
    mostrarCarta();

    lightbox.hidden = false;

    // La clase .visible entra un frame después para que la transición
    // de entrada (fade + scale) se ejecute desde el estado inicial
    requestAnimationFrame(() => lightbox.classList.add('visible'));

    abrirModalAccesible(lightbox, cerrarLightbox);
}

// Engancha el zoom a un grid de tarjetas de carta: click en la imagen
// (no en el resto de la tarjeta, que sigue llevando al detalle) → abre
// el lightbox con las cartas visibles de ese grid. obtenerCartas es un
// callback para leer la lista actual (cambia al paginar o filtrar).
export function activarLightboxEnGrid(idGrid, obtenerCartas) {
    const grid = document.getElementById(idGrid);

    grid?.addEventListener('click', (e) => {
        if (!(e.target instanceof Element) || !e.target.matches('.carta-card > img')) return;

        const tarjeta = e.target.closest('.carta-card');
        const posicion = [...grid.querySelectorAll('.carta-card')].indexOf(tarjeta);
        const lista = obtenerCartas();
        if (posicion < 0 || !lista[posicion]) return;

        // La tarjeta es un enlace al detalle: el zoom lo sustituye solo
        // cuando el click cae exactamente sobre la ilustración
        e.preventDefault();
        abrirLightbox(lista, posicion);
    });
}

// ── Interno ────────────────────────────────────────

function crearLightbox() {
    if (lightbox) return;

    lightbox = document.createElement('div');
    lightbox.className = 'lightbox';
    lightbox.setAttribute('role', 'dialog');
    lightbox.setAttribute('aria-modal', 'true');
    lightbox.setAttribute('aria-label', 'Ilustración ampliada de la carta');
    lightbox.hidden = true;

    lightbox.innerHTML = `
        <button class="lightbox-cerrar" type="button" aria-label="Cerrar">✕</button>
        <button class="lightbox-flecha lightbox-anterior" type="button" aria-label="Carta anterior">❮</button>
        <figure class="lightbox-cuerpo">
            <img class="lightbox-img" src="" alt="" />
            <figcaption class="lightbox-pie">
                <span class="lightbox-nombre"></span>
                <a class="lightbox-ficha" href="#" hidden>Ver ficha completa →</a>
            </figcaption>
        </figure>
        <button class="lightbox-flecha lightbox-siguiente" type="button" aria-label="Carta siguiente">❯</button>`;

    document.body.appendChild(lightbox);

    lightbox.querySelector('.lightbox-cerrar').addEventListener('click', cerrarLightbox);
    lightbox.querySelector('.lightbox-anterior').addEventListener('click', () => moverA(indice - 1));
    lightbox.querySelector('.lightbox-siguiente').addEventListener('click', () => moverA(indice + 1));

    // Click fuera de la imagen (fondo oscurecido) → cerrar
    lightbox.addEventListener('click', (e) => {
        if (e.target === lightbox || e.target.classList.contains('lightbox-cuerpo')) cerrarLightbox();
    });

    // Flechas del teclado (Escape y Tab los gestiona abrirModalAccesible)
    document.addEventListener('keydown', (e) => {
        if (lightbox.hidden) return;
        if (e.key === 'ArrowLeft')  moverA(indice - 1);
        if (e.key === 'ArrowRight') moverA(indice + 1);
    });
}

// Se exporta porque quien lo abre por encima de un modal debe poder
// cerrarlo al cerrarse ese modal (p. ej. Atrás del navegador sobre el
// detalle de un tradeo): si no, la carta ampliada quedaría flotando sola.
// Es idempotente: si ya está cerrado, no hace nada.
export function cerrarLightbox() {
    if (!lightbox || lightbox.hidden) return;

    lightbox.classList.remove('visible');
    lightbox.hidden = true;
    // El scroll del fondo lo libera la pila al vaciarse: si el lightbox se
    // abrió sobre un modal, ese modal sigue abierto y debe seguir bloqueando
    cerrarModalAccesible();
}

function moverA(nuevo) {
    if (nuevo < 0 || nuevo >= cartas.length) return;
    indice = nuevo;
    mostrarCarta();
}

// Pinta la carta actual: primero la imagen ligera como placeholder y,
// cuando la de alta calidad termina de cargar, la sustituye (si el
// usuario no ha navegado a otra carta mientras tanto)
function mostrarCarta() {
    const carta = cartas[indice];
    const img   = lightbox.querySelector('.lightbox-img');

    const low  = carta.imagen_low  || carta.imagen_url || '';
    const high = carta.imagen_high || low;

    img.alt = `Ilustración de ${carta.nombre || 'la carta'}`;
    lightbox.querySelector('.lightbox-nombre').textContent = carta.nombre || '';

    // Salida a la ficha completa (precio, PS, ilustrador, set...). Solo si
    // el llamador la pidió y la carta existe ya en nuestro catálogo.
    const ficha = lightbox.querySelector('.lightbox-ficha');
    const idCarta = carta.id ?? carta.tcgdex_id;
    ficha.hidden = !(conFicha && idCarta);
    if (!ficha.hidden) {
        ficha.href = paginaUrl(`pages/detalle-carta.html?id=${encodeURIComponent(idCarta)}`);
        ficha.setAttribute('aria-label', `Ver ficha completa de ${carta.nombre || 'la carta'}`);
    }

    // Sin ilustración (o si la URL muere): dorso propio en grande, no un
    // hueco vacío. El dorso es un SVG local que no falla.
    if (!low && !high) {
        img.onload = img.onerror = null;
        img.src = URL_DORSO;
        img.classList.add('lightbox-dorso');
        img.style.visibility = '';
        return;
    }
    img.classList.remove('lightbox-dorso');
    img.onload  = () => { img.style.visibility = ''; };
    img.onerror = () => { img.onerror = null; img.src = URL_DORSO; img.classList.add('lightbox-dorso'); };

    img.src = low || high;

    if (high && high !== low) {
        const posicionPedida = indice;
        const grande = new Image();
        grande.onload = () => {
            if (indice === posicionPedida && !lightbox.hidden) img.src = high;
        };
        grande.src = high;
    }

    // Flechas: solo visibles si hay más cartas, deshabilitadas en los extremos
    const anterior  = lightbox.querySelector('.lightbox-anterior');
    const siguiente = lightbox.querySelector('.lightbox-siguiente');
    const haySerie  = cartas.length > 1;
    anterior.hidden  = !haySerie;
    siguiente.hidden = !haySerie;
    anterior.disabled  = indice === 0;
    siguiente.disabled = indice === cartas.length - 1;
}

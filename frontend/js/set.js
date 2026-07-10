// set.js — Cartas de un set de expansión (módulo ES6)
// Recibe el ID de TCGdex del set por ?id= (ej: set.html?id=sv03.5).
// La primera visita de cualquier usuario a un set dispara su cacheo en
// el backend (cache-aside), que puede tardar unos segundos: mientras
// tanto se muestran skeletons, y si TCGdex no responde el backend
// devuelve 503 con un mensaje claro y aquí ofrecemos reintentar.

import { API_URL } from './auth.js';
import { escapeHtml, tarjetaCarta } from './utils.js';
import { crearPaginacion } from './paginacion.js';
import { activarLightboxEnGrid } from './lightbox.js';

const CARTAS_POR_PAGINA = 20;
let setId = null;
let cartasActuales = [];

// Zoom: click en la ilustración de una tarjeta → lightbox con las
// cartas de la página actual (flechas ←/→ para moverse entre ellas)
activarLightboxEnGrid('grid-cartas', () => cartasActuales);

const paginacion = crearPaginacion({
    contenedorId: 'paginacion',
    infoId:       'paginacion-info',
    alCambiar:    cargarPagina,
});

document.addEventListener('DOMContentLoaded', () => {
    setId = new URLSearchParams(window.location.search).get('id');

    if (!setId) {
        mostrarError('No se especificó ningún set.');
        return;
    }

    // La cabecera y las cartas se piden en paralelo: la cabecera sale
    // del índice (rápida) y no depende del cacheo de las cartas
    cargarCabecera();

    const paginaInicial = Math.max(1, parseInt(new URLSearchParams(window.location.search).get('page'), 10) || 1);
    cargarPagina(paginaInicial);
});

// ── Cabecera: logo, nombre, año y nº de cartas ─────

async function cargarCabecera() {
    const cabecera = document.getElementById('cabecera-set');

    try {
        const res = await fetch(`${API_URL}/sets/${encodeURIComponent(setId)}`);
        if (res.status === 404) { mostrarError('Este set no existe.'); return; }
        if (!res.ok) throw new Error('Error al conectar con la API');
        const set = await res.json();

        const nombre = escapeHtml(set.nombre);
        document.title = `${set.nombre} - PokeTrade`;
        document.getElementById('breadcrumb-set').textContent = set.nombre;

        // Breadcrumb completo: Inicio › Expansiones › Serie › Set
        if (set.serie) {
            document.getElementById('breadcrumb').innerHTML =
                `<a href="../index.html">Inicio</a> › ` +
                `<a href="expansiones.html">Expansiones</a> › ` +
                `<a href="expansiones.html?serie=${encodeURIComponent(set.serie.tcgdex_id)}">${escapeHtml(set.serie.nombre)}</a> › ` +
                `<span>${nombre}</span>`;
        }

        const anio = set.fecha_lanzamiento ? set.fecha_lanzamiento.slice(0, 4) : null;
        const meta = [anio, `${set.numero_cartas} cartas`].filter(Boolean).join(' · ');

        cabecera.innerHTML = `
            ${set.logo ? `<div class="set-cabecera-logo"><img src="${escapeHtml(set.logo)}" alt="" /></div>` : ''}
            <div>
                <h1>${nombre}</h1>
                <p class="set-meta">${meta}</p>
            </div>`;
    } catch (_) {
        // La cabecera es secundaria: sin ella el grid sigue funcionando
        cabecera.innerHTML = '';
    }
}

// ── Grid de cartas con paginación ──────────────────

async function cargarPagina(pagina) {
    const grid = document.getElementById('grid-cartas');
    grid.innerHTML = Array(8).fill('<div class="carta-card skeleton" aria-hidden="true"></div>').join('');

    try {
        const params = new URLSearchParams({ page: pagina, por_pagina: CARTAS_POR_PAGINA });
        const res    = await fetch(`${API_URL}/sets/${encodeURIComponent(setId)}/cartas?${params}`);

        if (res.status === 404) { mostrarError('Este set no existe.'); return; }

        // 503: TCGdex no respondió al cacheo del set. El backend manda
        // un mensaje claro; lo mostramos con un botón de reintento
        if (res.status === 503) {
            const datos = await res.json().catch(() => ({}));
            mostrarErrorConReintento(datos.error || 'El catálogo externo no responde. Inténtalo de nuevo en unos minutos.', pagina);
            return;
        }

        if (!res.ok) throw new Error('Error al conectar con la API');
        const datos = await res.json();

        actualizarUrlPagina(datos.current_page);
        mostrarCartas(datos.data);
        paginacion.actualizar(datos);

        // Scroll suave al inicio del grid al cambiar de página
        if (pagina > 1) {
            document.querySelector('.catalogo-contenedor')?.scrollIntoView({ behavior: 'smooth' });
        }
    } catch (e) {
        mostrarErrorConReintento(`Error al cargar las cartas: ${e.message}`, pagina);
    }
}

function mostrarCartas(cartas) {
    const grid = document.getElementById('grid-cartas');
    cartasActuales = cartas;

    if (!cartas.length) {
        grid.innerHTML = '<p class="grid-mensaje">Este set no tiene cartas.</p>';
        return;
    }

    grid.innerHTML = cartas.map(c => tarjetaCarta(c)).join('');
}

// Refleja la página actual en la URL (?page=N) sin añadir entradas al
// historial, para que el botón "atrás" devuelva al usuario a la misma
// página del set en la que estaba antes de abrir una carta.
function actualizarUrlPagina(pagina) {
    const url = new URL(window.location.href);
    if (pagina > 1) {
        url.searchParams.set('page', pagina);
    } else {
        url.searchParams.delete('page');
    }
    history.replaceState(null, '', url);
}

function mostrarError(msg) {
    document.getElementById('grid-cartas').innerHTML =
        `<p class="grid-mensaje error-texto">${msg}</p>`;
}

// Error recuperable (TCGdex caído o red): mensaje + botón de reintento
function mostrarErrorConReintento(msg, pagina) {
    const grid = document.getElementById('grid-cartas');
    grid.innerHTML =
        `<div class="grid-mensaje">` +
        `<p class="error-texto">${escapeHtml(msg)}</p>` +
        `<button class="btn-primario btn-reintentar" type="button">Reintentar</button>` +
        `</div>`;
    grid.querySelector('.btn-reintentar')?.addEventListener('click', () => cargarPagina(pagina));
}

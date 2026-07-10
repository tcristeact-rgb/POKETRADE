// expansiones.js — Navegación por expansiones del TCG (módulo ES6)
// Una misma página con dos vistas, al estilo multipágina del proyecto:
//   expansiones.html           → grid de series (más recientes primero)
//   expansiones.html?serie=sv  → grid de sets de esa serie
// La navegación entre vistas usa enlaces reales, así el botón atrás del
// navegador funciona sin código extra.

import { API_URL, paginaUrl } from './auth.js';
import { escapeHtml } from './utils.js';

document.addEventListener('DOMContentLoaded', () => {
    const serieId = new URLSearchParams(window.location.search).get('serie');

    if (serieId) {
        cargarSets(serieId);
    } else {
        cargarSeries();
    }
});

// ── Vista 1: todas las series ──────────────────────

async function cargarSeries() {
    const grid = document.getElementById('grid-expansiones');

    try {
        const res = await fetch(`${API_URL}/series`);
        if (!res.ok) throw new Error('Error al conectar con la API');
        const series = await res.json();

        if (!series.length) {
            grid.innerHTML = '<p class="grid-mensaje">Todavía no hay expansiones en el catálogo.</p>';
            return;
        }

        grid.innerHTML = series.map(s => tarjetaSerie(s)).join('');
    } catch (e) {
        grid.innerHTML =
            `<p class="grid-mensaje error-texto">Error al cargar las expansiones: ${e.message}</p>`;
    }
}

function tarjetaSerie(serie) {
    const nombre = escapeHtml(serie.nombre);
    const anio   = serie.fecha_ultimo_set ? serie.fecha_ultimo_set.slice(0, 4) : null;

    return `
        <a class="set-card" href="expansiones.html?serie=${encodeURIComponent(serie.tcgdex_id)}"
           aria-label="Ver sets de la serie ${nombre}">
            ${logoHTML(serie.logo, null)}
            <div class="set-info">
                <h3>${nombre}</h3>
                <p class="set-meta">${serie.sets_count} sets${anio ? ` · hasta ${anio}` : ''}</p>
            </div>
        </a>`;
}

// ── Vista 2: sets de una serie ─────────────────────

async function cargarSets(serieId) {
    const grid = document.getElementById('grid-expansiones');

    try {
        const res = await fetch(`${API_URL}/series/${encodeURIComponent(serieId)}`);
        if (res.status === 404) { mostrarError('Esta serie no existe.'); return; }
        if (!res.ok) throw new Error('Error al conectar con la API');
        const serie = await res.json();

        const nombre   = escapeHtml(serie.nombre);
        document.title = `${serie.nombre} - PokeTrade`;
        document.getElementById('titulo-expansiones').textContent = serie.nombre;
        document.getElementById('subtitulo-expansiones').textContent =
            `${serie.sets.length} sets de expansión, del más reciente al más antiguo.`;

        // Breadcrumb: Inicio › Expansiones › Serie
        document.getElementById('breadcrumb').innerHTML =
            `<a href="../index.html">Inicio</a> › ` +
            `<a href="expansiones.html">Expansiones</a> › ` +
            `<span>${nombre}</span>`;

        if (!serie.sets.length) {
            grid.innerHTML = '<p class="grid-mensaje">Esta serie no tiene sets todavía.</p>';
            return;
        }

        grid.innerHTML = serie.sets.map(s => tarjetaSet(s)).join('');
    } catch (e) {
        mostrarError(`Error al cargar la serie: ${e.message}`);
    }
}

function tarjetaSet(set) {
    const nombre = escapeHtml(set.nombre);
    const anio   = set.fecha_lanzamiento ? set.fecha_lanzamiento.slice(0, 4) : null;
    const meta   = [anio, `${set.numero_cartas} cartas`].filter(Boolean).join(' · ');

    return `
        <a class="set-card" href="set.html?id=${encodeURIComponent(set.tcgdex_id)}"
           aria-label="Ver cartas del set ${nombre}">
            ${logoHTML(set.logo, set.simbolo)}
            <div class="set-info">
                <h3>${nombre}</h3>
                <p class="set-meta">${meta}</p>
            </div>
        </a>`;
}

// ── Compartido ─────────────────────────────────────

// Logo del set o de la serie con degradación elegante: logo → símbolo
// pequeño → icono de carta como último recurso (nunca imagen rota)
function logoHTML(logo, simbolo) {
    if (logo) {
        return `<div class="set-logo"><img src="${escapeHtml(logo)}" alt="" loading="lazy" /></div>`;
    }
    if (simbolo) {
        return `<div class="set-logo"><img class="set-simbolo" src="${escapeHtml(simbolo)}" alt="" loading="lazy" /></div>`;
    }
    return `<div class="set-logo set-sin-logo" aria-hidden="true">` +
           `<img class="icono" src="${paginaUrl('img/icons/carta.svg')}" alt="" /></div>`;
}

function mostrarError(msg) {
    document.getElementById('grid-expansiones').innerHTML =
        `<p class="grid-mensaje error-texto">${msg}</p>`;
}

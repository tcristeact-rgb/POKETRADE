// publicar-tradeo.js — Crear y publicar un tradeo (módulo ES6)

import { API_URL, headersAuth, protegerRuta, manejarErrorHTTP, parsearRespuesta } from './auth.js';
import { buscarCartasCatalogo, debounce, escapeHtml, mostrarAlerta, dorsoCarta } from './utils.js';

protegerRuta('publicar');

const MAX_BUSCA_VISIBLE = 60;

let inventario      = [];
let resultadosBusca = [];             // Resultados de la búsqueda actual
const cartasConocidas = new Map();    // id → carta, para las previews
let idsOfrece = new Set();
let idsBusca  = new Set();

document.addEventListener('DOMContentLoaded', () => {
    cargarInventarioOfrece();
    cargarCatalogoBusca();

    // Controles estáticos. La búsqueda va al backend (el catálogo por
    // expansiones ya no cabe entero en el navegador), con debounce
    // para no lanzar una petición por tecla
    document.getElementById('buscar-busca')?.addEventListener('input', debounce(filtrarBusca, 300));
    document.getElementById('descripcion')?.addEventListener('input', actualizarContador);
    document.getElementById('btn-publicar')?.addEventListener('click', publicarTradeo);

    // Delegación: selección de cartas (ratón y teclado)
    enlazarSeleccion('grid-ofrece', toggleOfrece);
    enlazarSeleccion('grid-busca', toggleBusca);

    // Delegación: quitar carta desde las previsualizaciones
    document.getElementById('preview-ofrece')?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-carta-id]');
        if (btn) quitarSeleccion(Number(btn.dataset.cartaId), 'preview-ofrece');
    });
    document.getElementById('preview-busca')?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-carta-id]');
        if (btn) quitarSeleccion(Number(btn.dataset.cartaId), 'preview-busca');
    });
});

// Enlaza un grid de cartas seleccionables con su función de selección
function enlazarSeleccion(gridId, toggleFn) {
    const grid = document.getElementById(gridId);
    if (!grid) return;
    grid.addEventListener('click', (e) => {
        const card = e.target.closest('[data-carta-id]');
        if (card) toggleFn(Number(card.dataset.cartaId), card);
    });
    grid.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        const card = e.target.closest('[data-carta-id]');
        if (card) { e.preventDefault(); toggleFn(Number(card.dataset.cartaId), card); }
    });
}

// ─── Cargar datos ─────────────────────────────────────

async function cargarInventarioOfrece() {
    const grid = document.getElementById('grid-ofrece');
    try {
        const res = await fetch(`${API_URL}/inventario`, { headers: headersAuth() });
        if (!res.ok) throw new Error(manejarErrorHTTP(res.status));
        inventario = await res.json();
        renderizarOfrece(inventario);
    } catch (e) {
        grid.innerHTML = `<p class="vacio-seccion error-texto">Error al cargar inventario: ${e.message}</p>`;
    }
}

// Pide al backend la primera página de resultados del filtro por
// nombre (server-side: la BD crece con cada set que alguien visita y
// descargarla entera al navegador ya no es viable). Las cartas vistas
// se recuerdan en cartasConocidas para que las previews de selección
// conserven el nombre aunque la búsqueda cambie.
async function cargarCatalogoBusca(texto = '') {
    const grid = document.getElementById('grid-busca');
    try {
        resultadosBusca = await buscarCartasCatalogo(texto, MAX_BUSCA_VISIBLE);
        resultadosBusca.forEach(c => cartasConocidas.set(c.id, c));
        renderizarBusca(resultadosBusca);
    } catch (e) {
        grid.innerHTML = `<p class="vacio-seccion error-texto">Error al cargar catálogo: ${e.message}</p>`;
    }
}

// ─── Renderizado ──────────────────────────────────────

// HTML de una carta seleccionable accesible (teclado + ratón)
function cartaSeleccionableHTML(carta, seleccionada, infoExtra) {
    const nombre = escapeHtml(carta.nombre);
    const imagen = carta.imagen_low || carta.imagen_url;
    const img = imagen
        ? `<img src="${escapeHtml(imagen)}" alt="${nombre}" loading="lazy" />`
        : dorsoCarta();
    return `
        <div class="carta-seleccionable${seleccionada ? ' seleccionada' : ''}"
             role="button" tabindex="0" aria-pressed="${seleccionada}"
             data-carta-id="${carta.id}" aria-label="Seleccionar ${nombre}">
            ${img}
            <p>${nombre}</p>
            <small>${infoExtra}</small>
        </div>`;
}

function renderizarOfrece(items) {
    const grid = document.getElementById('grid-ofrece');

    if (!items.length) {
        grid.innerHTML = `<p class="vacio-seccion">Tu inventario está vacío. <a href="inventario.html">Añade cartas primero</a>.</p>`;
        return;
    }

    grid.innerHTML = items.map(item =>
        cartaSeleccionableHTML(item.carta, idsOfrece.has(item.carta.id), `x${item.cantidad}`)
    ).join('');
}

function renderizarBusca(cartas) {
    const grid = document.getElementById('grid-busca');

    if (!cartas.length) {
        grid.innerHTML = `<p class="vacio-seccion">No se encontraron cartas.</p>`;
        return;
    }

    grid.innerHTML = cartas.map(carta =>
        cartaSeleccionableHTML(carta, idsBusca.has(carta.id), carta.numero ? `Nº ${carta.numero}` : '')
    ).join('');
}

// ─── Selección ────────────────────────────────────────

function toggleOfrece(id, el) {
    if (idsOfrece.has(id)) {
        idsOfrece.delete(id);
        el.classList.remove('seleccionada');
        el.setAttribute('aria-pressed', 'false');
    } else {
        idsOfrece.add(id);
        el.classList.add('seleccionada');
        el.setAttribute('aria-pressed', 'true');
    }
    actualizarPreview('preview-ofrece', idsOfrece, inventario.map(i => i.carta));
}

function toggleBusca(id, el) {
    if (idsBusca.has(id)) {
        idsBusca.delete(id);
        el.classList.remove('seleccionada');
        el.setAttribute('aria-pressed', 'false');
    } else {
        idsBusca.add(id);
        el.classList.add('seleccionada');
        el.setAttribute('aria-pressed', 'true');
    }
    actualizarPreview('preview-busca', idsBusca, [...cartasConocidas.values()]);
}

function actualizarPreview(contenedorId, ids, fuente) {
    const preview = document.getElementById(contenedorId);
    if (!ids.size) {
        preview.innerHTML = '<span class="preview-vacio">Ninguna seleccionada</span>';
        return;
    }
    preview.innerHTML = [...ids].map(id => {
        const carta = fuente.find(c => c.id === id);
        const nombre = escapeHtml(carta?.nombre || `#${id}`);
        return `<span class="chip-carta">${nombre}<button type="button" data-carta-id="${id}" aria-label="Quitar ${nombre}">×</button></span>`;
    }).join('');
}

function quitarSeleccion(id, contenedorId) {
    if (contenedorId === 'preview-ofrece') {
        idsOfrece.delete(id);
        actualizarPreview('preview-ofrece', idsOfrece, inventario.map(i => i.carta));
        renderizarOfrece(inventario);
    } else {
        idsBusca.delete(id);
        actualizarPreview('preview-busca', idsBusca, [...cartasConocidas.values()]);
        renderizarBusca(resultadosBusca);
    }
}

// El texto cambió: nueva búsqueda contra el backend
function filtrarBusca() {
    cargarCatalogoBusca(document.getElementById('buscar-busca').value.trim());
}

function actualizarContador() {
    const val = document.getElementById('descripcion').value.length;
    document.getElementById('contador-chars').textContent = val;
}

// ─── Publicar ─────────────────────────────────────────

async function publicarTradeo() {
    if (!idsOfrece.size) {
        mostrarAlerta('Selecciona al menos una carta que ofrecer.', 'error');
        return;
    }
    if (!idsBusca.size) {
        mostrarAlerta('Selecciona al menos una carta que buscar.', 'error');
        return;
    }

    // Las cartas buscadas ya son cartas del catálogo del backend:
    // basta con enviar sus IDs, igual que las ofrecidas
    const payload = {
        cartas_ofrece: [...idsOfrece],
        cartas_busca:  [...idsBusca],
        descripcion:   document.getElementById('descripcion').value.trim() || null,
    };

    try {
        const res = await fetch(`${API_URL}/tradeos`, {
            method: 'POST',
            headers: headersAuth(),
            body: JSON.stringify(payload)
        });
        const datos = await parsearRespuesta(res);
        if (!res.ok) throw new Error(datos.error || manejarErrorHTTP(res.status));
        mostrarAlerta('¡Tradeo publicado correctamente!', 'exito');
        setTimeout(() => { window.location.href = 'tradeos.html'; }, 1600);
    } catch (e) {
        mostrarAlerta(`Error al publicar: ${e.message}`, 'error');
    }
}

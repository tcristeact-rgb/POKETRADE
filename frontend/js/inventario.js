// inventario.js — Gestión del inventario del usuario

import { API_URL, headersAuth, protegerRuta, manejarErrorHTTP, parsearRespuesta } from './auth.js';
import { buscarCartasCatalogo, debounce, escapeHtml, mostrarAlerta, dorsoCarta, abrirModalAccesible, cerrarModalAccesible } from './utils.js';

protegerRuta();

const MAX_MODAL_VISIBLE = 60;

let resultadosModal      = [];   // Resultados de la búsqueda actual
let cartaSeleccionadaId  = null;
let cantidadSeleccionada = 1;

document.addEventListener('DOMContentLoaded', () => {
    cargarInventario();
    cargarCatalogoModal();

    // Botones estáticos. La búsqueda del modal va al backend (el
    // catálogo por expansiones ya no cabe entero en el navegador),
    // con debounce para no lanzar una petición por tecla
    document.getElementById('btn-abrir-modal')?.addEventListener('click', abrirModal);
    document.getElementById('btn-cerrar-modal-inv')?.addEventListener('click', cerrarModal);
    document.getElementById('modal-buscar')?.addEventListener('input', debounce(filtrarModal, 300));
    document.getElementById('btn-cantidad-menos')?.addEventListener('click', () => cambiarCantidad(-1));
    document.getElementById('btn-cantidad-mas')?.addEventListener('click', () => cambiarCantidad(1));
    document.getElementById('btn-confirmar-anadir')?.addEventListener('click', confirmarAnadir);

    // Cerrar el modal al hacer clic fuera de la caja
    document.getElementById('modal-overlay')?.addEventListener('click', (e) => {
        if (e.target === e.currentTarget) cerrarModal();
    });

    // Delegación: acciones sobre las cartas del inventario
    document.getElementById('grid-inventario')?.addEventListener('click', (e) => {
        const el = e.target.closest('[data-accion]');
        if (!el) return;
        if (el.dataset.accion === 'eliminar') eliminarItem(Number(el.dataset.itemId));
        else if (el.dataset.accion === 'abrir-modal') abrirModal();
    });

    // Delegación: selección de carta en el modal (ratón y teclado)
    const lista = document.getElementById('lista-cartas-modal');
    lista?.addEventListener('click', (e) => {
        const card = e.target.closest('[data-carta-id]');
        if (card) seleccionarCarta(Number(card.dataset.cartaId), card);
    });
    lista?.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        const card = e.target.closest('[data-carta-id]');
        if (card) { e.preventDefault(); seleccionarCarta(Number(card.dataset.cartaId), card); }
    });
});

// ─── Inventario ───────────────────────────────────────

async function cargarInventario() {
    const grid = document.getElementById('grid-inventario');
    try {
        const res = await fetch(`${API_URL}/inventario`, { headers: headersAuth() });
        if (!res.ok) throw new Error(manejarErrorHTTP(res.status));
        const items = await res.json();
        renderizarInventario(items);
    } catch (e) {
        grid.innerHTML = `<p class="error-texto">Error al cargar el inventario: ${e.message}</p>`;
    }
}

function renderizarInventario(items) {
    const grid = document.getElementById('grid-inventario');

    if (!items.length) {
        grid.innerHTML = `
            <div class="vacio-msg">
                <p>Tu inventario está vacío.</p>
                <button class="btn-primario" type="button" data-accion="abrir-modal">Añadir primera carta</button>
            </div>`;
        return;
    }

    grid.innerHTML = items.map(item => {
        const nombre = item.carta?.nombre || 'Carta';
        return `
        <div class="carta-inventario">
            <span class="badge-cantidad">${item.cantidad}</span>
            ${item.carta?.imagen_low || item.carta?.imagen_url
                ? `<img src="${escapeHtml(item.carta.imagen_low || item.carta.imagen_url)}" alt="${escapeHtml(nombre)}" />`
                : dorsoCarta()}
            <h3>${escapeHtml(nombre)}</h3>
            <span class="carta-tipo">${escapeHtml(item.carta?.tipo || '—')}</span>
            <span class="carta-rareza">${escapeHtml(item.carta?.rareza || '')}</span>
            <button class="btn-eliminar" type="button" data-accion="eliminar" data-item-id="${item.id}"
                    aria-label="Eliminar ${escapeHtml(nombre)} del inventario">Eliminar</button>
        </div>`;
    }).join('');
}

async function eliminarItem(id) {
    if (!confirm('¿Eliminar esta carta del inventario? Se quitarán todas las copias que tengas de ella.')) return;
    try {
        const res = await fetch(`${API_URL}/inventario/${id}`, {
            method: 'DELETE',
            headers: headersAuth()
        });
        if (!res.ok) throw new Error(manejarErrorHTTP(res.status));
        mostrarAlerta('Carta eliminada del inventario.', 'exito');
        cargarInventario();
    } catch (e) {
        mostrarAlerta(`Error: ${e.message}`, 'error');
    }
}

// ─── Modal: catálogo ──────────────────────────────────

// Pide al backend la primera página de resultados del filtro por
// nombre (server-side: la BD crece con cada set que alguien visita y
// descargarla entera al navegador ya no es viable)
async function cargarCatalogoModal(texto = '') {
    try {
        resultadosModal = await buscarCartasCatalogo(texto, MAX_MODAL_VISIBLE);
        renderizarModal(resultadosModal);
    } catch (e) {
        document.getElementById('lista-cartas-modal').innerHTML =
            `<p class="error-texto">No se pudo cargar el catálogo: ${e.message}</p>`;
    }
}

function renderizarModal(cartas) {
    const lista = document.getElementById('lista-cartas-modal');

    if (!cartas.length) {
        lista.innerHTML = '<p>No se encontraron cartas.</p>';
        return;
    }

    lista.innerHTML = cartas.map(carta => `
        <div class="carta-seleccionable" role="button" tabindex="0"
             data-carta-id="${carta.id}"
             aria-label="Seleccionar ${escapeHtml(carta.nombre)}">
            ${carta.imagen_url
                ? `<img src="${escapeHtml(carta.imagen_url)}" alt="${escapeHtml(carta.nombre)}" />`
                : dorsoCarta()}
            <p>${escapeHtml(carta.nombre)}</p>
        </div>
    `).join('');
}

// El texto cambió: nueva búsqueda contra el backend
function filtrarModal() {
    cargarCatalogoModal(document.getElementById('modal-buscar').value.trim());
}

function seleccionarCarta(id, el) {
    // La carta seleccionada siempre está en los resultados visibles
    const carta = resultadosModal.find(c => c.id === id);
    cartaSeleccionadaId  = id;
    cantidadSeleccionada = 1;
    document.getElementById('nombre-seleccionada').textContent = carta?.nombre || `Carta #${id}`;
    document.getElementById('cantidad-valor').textContent = 1;
    document.getElementById('seleccion-panel').hidden = false;

    document.querySelectorAll('.carta-seleccionable').forEach(c => c.classList.remove('seleccionada'));
    if (el) el.classList.add('seleccionada');
}

function cambiarCantidad(delta) {
    cantidadSeleccionada = Math.max(1, Math.min(99, cantidadSeleccionada + delta));
    document.getElementById('cantidad-valor').textContent = cantidadSeleccionada;
}

async function confirmarAnadir() {
    if (!cartaSeleccionadaId) return;
    try {
        // La carta ya existe en el catálogo del backend: basta con su ID
        const res = await fetch(`${API_URL}/inventario`, {
            method: 'POST',
            headers: headersAuth(),
            body: JSON.stringify({
                carta_id: cartaSeleccionadaId,
                cantidad: cantidadSeleccionada
            })
        });
        const datos = await parsearRespuesta(res);
        if (!res.ok) throw new Error(datos.error || manejarErrorHTTP(res.status));
        cerrarModal();
        mostrarAlerta('Carta añadida al inventario.', 'exito');
        cargarInventario();
    } catch (e) {
        mostrarAlerta(`Error: ${e.message}`, 'error');
    }
}

function abrirModal() {
    const overlay = document.getElementById('modal-overlay');
    overlay.hidden = false;
    document.getElementById('seleccion-panel').hidden = true;
    cartaSeleccionadaId = null;
    // Accesibilidad: foco al modal, retención de Tab y cierre con Escape
    abrirModalAccesible(overlay, cerrarModal);
}

function cerrarModal() {
    document.getElementById('modal-overlay').hidden = true;
    document.getElementById('modal-buscar').value = '';
    cargarCatalogoModal();
    // Accesibilidad: devuelve el foco al botón que abrió el modal
    cerrarModalAccesible();
}

// inventario.js — Gestión del inventario del usuario

import { apiFetch, protegerRuta, manejarErrorHTTP, parsearRespuesta } from './auth.js';
import { t } from './i18n.js';
import { alCargarDOM, buscarCartasCatalogo, debounce, escapeHtml, mostrarAlerta, dorsoCarta, abrirModalAccesible, cerrarModalAccesible } from './utils.js';

protegerRuta('inventario');

const MAX_MODAL_VISIBLE = 60;

let resultadosModal      = [];   // Resultados de la búsqueda actual
let cartaSeleccionadaId  = null;
let cantidadSeleccionada = 1;

alCargarDOM(() => {
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
    grid.innerHTML = Array(10)
        .fill('<div class="carta-inventario skeleton" aria-hidden="true"></div>').join('');
    try {
        const res = await apiFetch(`/inventario`);
        if (!res.ok) throw new Error(manejarErrorHTTP(res.status));
        const items = await res.json();
        renderizarInventario(items);
    } catch (e) {
        grid.innerHTML = `<p class="error-texto">${escapeHtml(t('inv.errorCargar', { mensaje: e.message }))}</p>`;
    }
}

function renderizarInventario(items) {
    const grid = document.getElementById('grid-inventario');

    if (!items.length) {
        grid.innerHTML = `
            <div class="vacio-msg">
                <p>${escapeHtml(t('inv.vacio'))}</p>
                <button class="btn-primario" type="button" data-accion="abrir-modal">${escapeHtml(t('inv.anadirPrimera'))}</button>
            </div>`;
        return;
    }

    grid.innerHTML = items.map(item => {
        const nombre = item.carta?.nombre || t('carta.breadcrumb');
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
                    aria-label="${escapeHtml(t('inv.eliminarAria', { nombre }))}">${escapeHtml(t('comun.eliminar'))}</button>
        </div>`;
    }).join('');
}

async function eliminarItem(id) {
    if (!confirm(t('inv.confirmarEliminar'))) return;
    try {
        const res = await apiFetch(`/inventario/${id}`, {
            method: 'DELETE',
        });
        if (!res.ok) throw new Error(manejarErrorHTTP(res.status));
        mostrarAlerta(t('inv.eliminada'), 'exito');
        cargarInventario();
    } catch (e) {
        mostrarAlerta(t('comun.error', { mensaje: e.message }), 'error');
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
            `<p class="error-texto">${escapeHtml(t('inv.errorCatalogo', { mensaje: e.message }))}</p>`;
    }
}

function renderizarModal(cartas) {
    const lista = document.getElementById('lista-cartas-modal');

    if (!cartas.length) {
        lista.innerHTML = `<p>${escapeHtml(t('inv.sinResultados'))}</p>`;
        return;
    }

    lista.innerHTML = cartas.map(carta => `
        <div class="carta-seleccionable" role="button" tabindex="0"
             data-carta-id="${carta.id}"
             aria-label="${escapeHtml(t('inv.seleccionarAria', { nombre: carta.nombre }))}">
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
    document.getElementById('nombre-seleccionada').textContent =
        carta?.nombre || t('inv.cartaNum', { id: String(id) });
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
        const res = await apiFetch(`/inventario`, {
            method: 'POST',
            body: JSON.stringify({
                carta_id: cartaSeleccionadaId,
                cantidad: cantidadSeleccionada
            })
        });
        const datos = await parsearRespuesta(res);
        if (!res.ok) throw new Error(datos.error || manejarErrorHTTP(res.status));
        cerrarModal();
        mostrarAlerta(t('carta.anadida'), 'exito');
        cargarInventario();
    } catch (e) {
        mostrarAlerta(t('comun.error', { mensaje: e.message }), 'error');
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

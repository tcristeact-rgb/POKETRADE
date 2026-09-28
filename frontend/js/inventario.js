// inventario.js — Gestión del inventario del usuario

import { apiFetch, protegerRuta, manejarErrorHTTP, parsearRespuesta } from './auth.js';
import { t } from './i18n.js';
import { alCargarDOM, buscarCartasCatalogo, debounce, escapeHtml, mostrarAlerta, dorsoCarta, abrirModalAccesible, cerrarModalAccesible, formatearPrecio } from './utils.js';
import { abrirLightbox } from './lightbox.js';

protegerRuta('inventario');

const MAX_MODAL_VISIBLE = 60;

let resultadosModal      = [];   // Resultados de la búsqueda actual
let itemsInventario      = [];   // Lo que trajo GET /inventario: de aquí sale el detalle
let itemDetalleAbierto   = null; // id del item cuyo modal de detalle está abierto
// Cartas elegidas en el modal: id → { carta, cantidad }. Guarda la carta
// entera porque sobrevive a los cambios de búsqueda y deja de estar en
// resultadosModal
const seleccion = new Map();
let anadiendo = false;           // evita un doble envío mientras se guardan

alCargarDOM(() => {
    cargarInventario();
    cargarCatalogoModal();

    // Botones estáticos. La búsqueda del modal va al backend (el
    // catálogo por expansiones ya no cabe entero en el navegador),
    // con debounce para no lanzar una petición por tecla
    document.getElementById('btn-abrir-modal')?.addEventListener('click', abrirModal);
    document.getElementById('btn-cerrar-modal-inv')?.addEventListener('click', cerrarModal);
    document.getElementById('modal-buscar')?.addEventListener('input', debounce(filtrarModal, 300));
    document.getElementById('btn-confirmar-anadir')?.addEventListener('click', confirmarAnadir);

    // Delegación: cantidad y quitar en las filas de la selección
    document.getElementById('lista-seleccion')?.addEventListener('click', (e) => {
        const el = e.target.closest('[data-accion]');
        if (!el) return;
        const id = Number(el.dataset.cartaId);
        if (el.dataset.accion === 'menos') cambiarCantidad(id, -1);
        else if (el.dataset.accion === 'mas') cambiarCantidad(id, 1);
        else if (el.dataset.accion === 'quitar') {
            alternarCarta(id);
            // La fila con el foco ha desaparecido: que no caiga al body
            document.getElementById(seleccion.size ? 'btn-confirmar-anadir' : 'modal-buscar')?.focus();
        }
    });

    // Cerrar el modal al hacer clic fuera de la caja
    document.getElementById('modal-overlay')?.addEventListener('click', (e) => {
        if (e.target === e.currentTarget) cerrarModal();
    });

    // Delegación: acciones sobre las cartas del inventario
    document.getElementById('grid-inventario')?.addEventListener('click', (e) => {
        const el = e.target.closest('[data-accion]');
        if (!el) return;
        if (el.dataset.accion === 'eliminar') eliminarItem(Number(el.dataset.itemId));
        else if (el.dataset.accion === 'detalle') abrirDetalle(Number(el.dataset.itemId));
        else if (el.dataset.accion === 'abrir-modal') abrirModal();
    });

    // Modal de detalle: los dos botones de cerrar y el clic fuera de la caja
    document.getElementById('btn-cerrar-detalle-inv')?.addEventListener('click', cerrarDetalle);
    document.getElementById('btn-cerrar-detalle-inv-pie')?.addEventListener('click', cerrarDetalle);
    document.getElementById('modal-detalle-inv')?.addEventListener('click', (e) => {
        if (e.target === e.currentTarget) cerrarDetalle();
    });

    // Delegación: selección de carta en el modal (ratón y teclado)
    const lista = document.getElementById('lista-cartas-modal');
    lista?.addEventListener('click', (e) => {
        const card = e.target.closest('[data-carta-id]');
        if (card) alternarCarta(Number(card.dataset.cartaId));
    });
    lista?.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        const card = e.target.closest('[data-carta-id]');
        if (card) { e.preventDefault(); alternarCarta(Number(card.dataset.cartaId)); }
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
        itemsInventario = items;
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

    // La imagen, el nombre y las etiquetas van dentro de un botón que abre el
    // detalle; el de eliminar queda fuera (un control nunca dentro de otro)
    grid.innerHTML = items.map(item => {
        const nombre = item.carta?.nombre || t('carta.breadcrumb');
        return `
        <div class="carta-inventario">
            <span class="badge-cantidad">${item.cantidad}</span>
            <button class="carta-inventario-abrir" type="button" data-accion="detalle" data-item-id="${item.id}"
                    aria-label="${escapeHtml(t('inv.verDetalle', { nombre }))}">
                ${item.carta?.imagen_low || item.carta?.imagen_url
                    ? `<img src="${escapeHtml(item.carta.imagen_low || item.carta.imagen_url)}" alt="" />`
                    : dorsoCarta()}
                <h3>${escapeHtml(nombre)}</h3>
                <span class="carta-tipo">${escapeHtml(item.carta?.tipo || '—')}</span>
                <span class="carta-rareza">${escapeHtml(item.carta?.rareza || '')}</span>
            </button>
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
        if (itemDetalleAbierto === id) cerrarDetalle();
        cargarInventario();
    } catch (e) {
        mostrarAlerta(t('comun.error', { mensaje: e.message }), 'error');
    }
}

// ─── Modal: detalle resumido de una carta del inventario ──────────
// Todo sale de itemsInventario: cero peticiones. El lightbox se apila
// encima (abrirModalAccesible lleva una pila de modales).

function abrirDetalle(itemId) {
    const item = itemsInventario.find(i => i.id === itemId);
    if (!item?.carta) return;
    const carta  = item.carta;
    const nombre = carta.nombre || t('carta.breadcrumb');

    document.getElementById('detalle-inv-titulo').textContent = nombre;
    document.getElementById('detalle-inv-ficha').href = `detalle-carta.html?id=${carta.id}`;

    const imagen = carta.imagen_high || carta.imagen_low || carta.imagen_url;
    const precio = formatearPrecio(carta.precio_cardmarket);
    const atributos = [
        [t('carta.tipo'),        carta.tipo],
        [t('carta.rareza'),      carta.rareza],
        [t('carta.set'),         carta.set_expansion],
        [t('inv.numero'),        carta.numero],
        [t('carta.ps'),          carta.hp ? t('carta.psValor', { n: String(carta.hp) }) : null],
        [t('carta.ilustracion'), carta.ilustrador],
        [t('carta.precioMedio'), precio ? `${precio} ${t('carta.fuentePrecio')}` : null],
    ].filter(([, valor]) => valor);

    document.getElementById('detalle-inv-cuerpo').innerHTML = `
        <div class="detalle-inv">
            ${imagen
                ? `<button type="button" class="detalle-inv-imagen" id="detalle-inv-zoom"
                           aria-label="${escapeHtml(t('carta.ampliar', { nombre }))}">
                       <img src="${escapeHtml(imagen)}" alt="" />
                   </button>`
                : `<div class="detalle-inv-imagen">${dorsoCarta()}</div>`}
            <div class="detalle-inv-texto">
                <dl class="detalle-inv-atributos">
                    ${atributos.map(([etiqueta, valor]) =>
                        `<dt>${escapeHtml(etiqueta)}</dt><dd>${escapeHtml(String(valor))}</dd>`).join('')}
                </dl>
                <p class="detalle-inv-cantidad">${escapeHtml(t('inv.enTuInventario', { n: String(item.cantidad) }))}</p>
                ${carta.descripcion ? `<p class="detalle-inv-descripcion">${escapeHtml(carta.descripcion)}</p>` : ''}
            </div>
        </div>`;

    document.getElementById('detalle-inv-zoom')
        ?.addEventListener('click', () => abrirLightbox([carta]));

    itemDetalleAbierto = itemId;
    const overlay = document.getElementById('modal-detalle-inv');
    overlay.hidden = false;
    abrirModalAccesible(overlay, cerrarDetalle);
}

function cerrarDetalle() {
    const overlay = document.getElementById('modal-detalle-inv');
    if (overlay.hidden) return;
    overlay.hidden = true;
    itemDetalleAbierto = null;
    cerrarModalAccesible();   // devuelve el foco a la tarjeta que lo abrió
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
        <div class="carta-seleccionable${seleccion.has(carta.id) ? ' seleccionada' : ''}" role="button" tabindex="0"
             data-carta-id="${carta.id}" aria-pressed="${seleccion.has(carta.id)}"
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

// Clic en una carta del catálogo (o en "quitar" de su fila): entra en la
// selección con cantidad 1, o sale de ella si ya estaba
function alternarCarta(id) {
    if (seleccion.has(id)) {
        seleccion.delete(id);
    } else {
        // Al entrar, la carta siempre está en los resultados visibles
        const carta = resultadosModal.find(c => c.id === id);
        if (!carta) return;
        seleccion.set(id, { carta, cantidad: 1 });
    }

    const tarjeta = document.querySelector(`.carta-seleccionable[data-carta-id="${id}"]`);
    if (tarjeta) {
        tarjeta.classList.toggle('seleccionada', seleccion.has(id));
        tarjeta.setAttribute('aria-pressed', String(seleccion.has(id)));
    }
    renderizarSeleccion();
}

function cambiarCantidad(id, delta) {
    const entrada = seleccion.get(id);
    if (!entrada) return;
    entrada.cantidad = Math.max(1, Math.min(99, entrada.cantidad + delta));
    const valor = document.querySelector(`#lista-seleccion [data-cantidad-de="${id}"]`);
    if (valor) valor.textContent = entrada.cantidad;
}

function renderizarSeleccion() {
    const panel = document.getElementById('seleccion-panel');
    panel.hidden = seleccion.size === 0;
    if (!seleccion.size) return;

    document.getElementById('lista-seleccion').innerHTML = [...seleccion.values()].map(({ carta, cantidad }) => {
        const nombre = carta.nombre || t('inv.cartaNum', { id: String(carta.id) });
        return `
        <li class="fila-seleccion">
            <span class="fila-seleccion-nombre">${escapeHtml(nombre)}</span>
            <div class="cantidad-control">
                <button type="button" data-accion="menos" data-carta-id="${carta.id}"
                        aria-label="${escapeHtml(t('inv.disminuir', { nombre }))}">−</button>
                <span data-cantidad-de="${carta.id}" aria-live="polite">${cantidad}</span>
                <button type="button" data-accion="mas" data-carta-id="${carta.id}"
                        aria-label="${escapeHtml(t('inv.aumentar', { nombre }))}">+</button>
            </div>
            <button class="btn-quitar-seleccion" type="button" data-accion="quitar" data-carta-id="${carta.id}"
                    aria-label="${escapeHtml(t('inv.quitarSeleccion', { nombre }))}">✕</button>
        </li>`;
    }).join('');

    document.getElementById('btn-confirmar-anadir').textContent =
        t('inv.anadirSeleccion', { n: seleccion.size });
}

// Una petición por carta, en serie: el endpoint añade de una en una y así
// cada carta valida su propio tope. Las que fallan se quedan seleccionadas
// para poder corregirlas y reintentar; las que entran salen de la selección.
async function confirmarAnadir() {
    if (!seleccion.size || anadiendo) return;
    anadiendo = true;
    const boton = document.getElementById('btn-confirmar-anadir');
    boton.disabled = true;

    let anadidas = 0;
    const fallos = [];
    for (const [id, { carta, cantidad }] of seleccion) {
        try {
            // La carta ya existe en el catálogo del backend: basta con su ID
            const res = await apiFetch(`/inventario`, {
                method: 'POST',
                body: JSON.stringify({ carta_id: id, cantidad })
            });
            const datos = await parsearRespuesta(res);
            if (!res.ok) throw new Error(datos.error || manejarErrorHTTP(res.status));
            seleccion.delete(id);
            anadidas++;
        } catch (e) {
            fallos.push(`${carta.nombre || t('inv.cartaNum', { id: String(id) })}: ${e.message}`);
        }
    }

    anadiendo = false;
    boton.disabled = false;
    if (anadidas) cargarInventario();

    if (!fallos.length) {
        if (!document.getElementById('modal-overlay').hidden) cerrarModal();
        mostrarAlerta(t('inv.anadidas', { n: anadidas }), 'exito');
        return;
    }
    renderizarModal(resultadosModal);
    renderizarSeleccion();
    mostrarAlerta(t('inv.errorAnadir', { n: anadidas, detalle: fallos.join(' · ') }), 'error');
}

function abrirModal() {
    const overlay = document.getElementById('modal-overlay');
    overlay.hidden = false;
    renderizarSeleccion();
    // Accesibilidad: foco al modal, retención de Tab y cierre con Escape
    abrirModalAccesible(overlay, cerrarModal);
}

function cerrarModal() {
    document.getElementById('modal-overlay').hidden = true;
    document.getElementById('modal-buscar').value = '';
    seleccion.clear();
    cargarCatalogoModal();
    // Accesibilidad: devuelve el foco al botón que abrió el modal
    cerrarModalAccesible();
}

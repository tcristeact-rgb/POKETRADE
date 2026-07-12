// marketplace.js — Listado público de tradeos activos

import { API_URL, estaLogueado, obtenerUsuario, headersAuth, parsearRespuesta } from './auth.js';
import { formatearFecha, formatearPrecio, escapeHtml, dorsoCarta, abrirModalAccesible, cerrarModalAccesible } from './utils.js';

let todosLosTradeos   = [];
let inventarioUsuario = [];
let tradeoEnCurso     = null;

document.addEventListener('DOMContentLoaded', () => {
    mostrarCtaPublicar();

    // Filtros y reintento
    document.getElementById('buscar-carta')?.addEventListener('input', filtrar);
    document.getElementById('filtro-tipo')?.addEventListener('change', filtrar);
    document.getElementById('btn-reintentar-mkt')?.addEventListener('click', cargarTradeos);

    // Delegación de eventos para las tarjetas de tradeo
    document.getElementById('grid-tradeos')?.addEventListener('click', (e) => {
        const el = e.target.closest('[data-accion]');
        if (!el) return;
        if (el.dataset.accion === 'aceptar') abrirModalAceptar(Number(el.dataset.tradeoId));
        else if (el.dataset.accion === 'limpiar') limpiarFiltros();
    });

    // Imágenes muertas dentro del grid (error no burbujea: captura):
    // la protagonista se convierte en placeholder, las minis se ocultan
    document.getElementById('grid-tradeos')?.addEventListener('error', (e) => {
        const img = e.target;
        if (!(img instanceof HTMLImageElement) || img.dataset.rota) return;
        // El dorso propio es un SVG local que no falla: evita el bucle
        if (img.classList.contains('carta-dorso')) return;
        img.dataset.rota = '1';
        if (img.classList.contains('tradeo-protagonista')) {
            img.outerHTML = dorsoCarta('tradeo-protagonista');
        } else if (img.classList.contains('busca-mini')) {
            img.outerHTML = dorsoCarta('busca-mini');
        }
    }, true);

    // Modal de aceptar tradeo
    document.getElementById('btn-cerrar-aceptar')?.addEventListener('click', cerrarModal);
    document.getElementById('btn-cancelar-aceptar')?.addEventListener('click', cerrarModal);
    document.getElementById('btn-confirmar')?.addEventListener('click', confirmarAceptar);

    cargarTradeos();
});

// ─── CTA publicar ──────────────────────────────────────

function mostrarCtaPublicar() {
    if (estaLogueado()) {
        document.getElementById('cta-publicar').hidden = false;
    }
}

// ─── Carga de tradeos ──────────────────────────────────

async function cargarTradeos() {
    ocultarError();
    try {
        const res = await fetch(`${API_URL}/tradeos`);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        todosLosTradeos = await res.json();
        filtrar();
    } catch (e) {
        mostrarError('No se pudieron cargar los tradeos. ¿Está el servidor activo?');
    }
}

// ─── Filtrado ──────────────────────────────────────────

function filtrar() {
    const texto = document.getElementById('buscar-carta').value.toLowerCase().trim();
    const tipo  = document.getElementById('filtro-tipo').value;

    const filtrados = todosLosTradeos.filter(t => {
        const cartas        = [...(t.cartas_ofrece || []), ...(t.cartas_busca || [])];
        const coincideTexto = !texto || cartas.some(c => c.nombre.toLowerCase().includes(texto));
        const coincideTipo  = !tipo  || cartas.some(c => c.tipo === tipo);
        return coincideTexto && coincideTipo;
    });

    renderizarTradeos(filtrados);
}

// ─── Renderizado ───────────────────────────────────────

function renderizarTradeos(tradeos) {
    const grid     = document.getElementById('grid-tradeos');
    const contador = document.getElementById('contador');

    contador.textContent = tradeos.length === 1
        ? '1 tradeo activo'
        : `${tradeos.length} tradeos activos`;

    if (!tradeos.length) {
        grid.innerHTML = `
            <div class="vacio-msg">
                <p>No hay tradeos que coincidan con tu búsqueda.</p>
                <button class="btn-secundario" type="button" data-accion="limpiar">Limpiar filtros</button>
            </div>`;
        return;
    }

    grid.innerHTML = tradeos.map(t => tarjetaTradeo(t)).join('');
}

function tarjetaTradeo(t) {
    const fecha         = formatearFecha(t.created_at);
    const inicial       = (t.usuario?.nombre?.[0] || '?').toUpperCase();
    const nombreUser    = t.usuario ? `${t.usuario.nombre} ${t.usuario.apellido}` : 'Usuario';
    const usuarioActual = obtenerUsuario();
    const esPropioTradeo = usuarioActual && t.usuario?.id === usuarioActual.id;

    const descripcion = t.descripcion
        ? `<p class="tradeo-descripcion">"${escapeHtml(t.descripcion)}"</p>` : '';

    let botonAccion;
    if (!estaLogueado()) {
        botonAccion = `<a href="login.html" class="btn-contactar">Inicia sesión para aceptar</a>`;
    } else if (esPropioTradeo) {
        botonAccion = `<button class="btn-contactar btn-propio" type="button" disabled>Tu propio tradeo</button>`;
    } else {
        botonAccion = `<button class="btn-contactar" type="button" data-accion="aceptar" data-tradeo-id="${t.id}">Aceptar tradeo</button>`;
    }

    return `
    <div class="tradeo-card" id="tradeo-card-${t.id}">
        <div class="tradeo-card-header">
            <div class="usuario-info">
                <div class="usuario-avatar" aria-hidden="true">${escapeHtml(inicial)}</div>
                <span class="usuario-nombre">${escapeHtml(nombreUser)}</span>
            </div>
            <span class="tradeo-fecha">${fecha}</span>
        </div>
        <div class="tradeo-card-body">
            <div class="tradeo-ofrece">
                <span class="intercambio-label label-ofrece">Ofrece</span>
                ${bloqueOfrece(t.cartas_ofrece)}
            </div>
            <div class="tradeo-busca">
                <span class="intercambio-label label-busca">Busca ⇄</span>
                ${bloqueBusca(t.cartas_busca)}
            </div>
            ${descripcion}
        </div>
        <div class="tradeo-card-footer">
            ${botonAccion}
        </div>
    </div>`;
}

// Lado "ofrece": la primera carta es la protagonista (ilustración
// vertical grande + nombre); el resto se resume en "+N más" y el
// valor suma los precios Cardmarket conocidos del lado
function bloqueOfrece(cartas = []) {
    if (!cartas.length) {
        return '<span class="miniaturas-vacio">—</span>';
    }

    const [principal, ...resto] = cartas;
    const imagen = principal.imagen_low || principal.imagen_url;

    const conPrecio = cartas.filter(c => c.precio_cardmarket != null);
    const valor = conPrecio.length
        ? formatearPrecio(conPrecio.reduce((suma, c) => suma + Number(c.precio_cardmarket), 0))
        : null;

    return `
        <div class="ofrece-principal">
            ${imagen
                ? `<img class="tradeo-protagonista" src="${escapeHtml(imagen)}"
                       alt="${escapeHtml(principal.nombre)}" loading="lazy" />`
                : dorsoCarta('tradeo-protagonista')}
            <div class="ofrece-detalle">
                <span class="ofrece-nombre">${escapeHtml(principal.nombre)}</span>
                ${resto.length
                    ? `<span class="ofrece-extra">+${resto.length} ${resto.length === 1 ? 'carta más' : 'cartas más'}</span>`
                    : ''}
                ${valor ? `<span class="ofrece-valor">~ ${valor}</span>` : ''}
            </div>
        </div>`;
}

// Lado "busca": fila de miniaturas verticales (máx. 4) con el nombre
// completo en title; el resto queda en un chip "+N"
function bloqueBusca(cartas = []) {
    if (!cartas.length) {
        return '<span class="miniaturas-vacio">—</span>';
    }

    const visibles = cartas.slice(0, 4);
    const ocultas  = cartas.length - visibles.length;

    const minis = visibles.map(c => {
        const imagen = c.imagen_low || c.imagen_url;
        const nombre = escapeHtml(c.nombre);
        return imagen
            ? `<img class="busca-mini" src="${escapeHtml(imagen)}" alt="${nombre}" title="${nombre}" loading="lazy" />`
            : dorsoCarta('busca-mini');
    }).join('');

    return `
        <div class="busca-minis">
            ${minis}
            ${ocultas > 0 ? `<span class="busca-mas" title="${escapeHtml(
                cartas.slice(4).map(c => c.nombre).join(', '))}">+${ocultas}</span>` : ''}
        </div>`;
}

// ─── Modal: aceptar tradeo ─────────────────────────────

async function abrirModalAceptar(tradeoId) {
    tradeoEnCurso = todosLosTradeos.find(t => t.id === tradeoId);
    if (!tradeoEnCurso) return;

    document.getElementById('modal-aceptar').hidden = false;
    document.getElementById('modal-cuerpo').innerHTML =
        `<p class="modal-cargando">Comprobando tu inventario...</p>`;
    document.getElementById('btn-confirmar').disabled = true;
    document.getElementById('modal-estado').textContent = '';

    // Accesibilidad: foco al modal, retención de Tab y cierre con Escape
    abrirModalAccesible(document.getElementById('modal-aceptar'), cerrarModal);

    try {
        const res = await fetch(`${API_URL}/inventario`, { headers: headersAuth() });
        if (!res.ok) throw new Error();
        inventarioUsuario = await res.json();
    } catch (_) {
        inventarioUsuario = [];
    }

    renderizarModalAceptar(tradeoEnCurso);
}

function renderizarModalAceptar(tradeo) {
    const nombreUser = tradeo.usuario
        ? `${tradeo.usuario.nombre} ${tradeo.usuario.apellido}` : 'Usuario';

    document.getElementById('modal-titulo').textContent = `Aceptar tradeo de ${nombreUser}`;

    const cartasNecesarias = (tradeo.cartas_busca || []).map(carta => {
        const itemInventario = inventarioUsuario.find(i => i.carta_id === carta.id || i.carta?.id === carta.id);
        return { carta, itemInventario, tienes: !!itemInventario };
    });

    const cartasRecibidas  = tradeo.cartas_ofrece || [];
    const todasDisponibles = cartasNecesarias.every(c => c.tienes);
    const faltantes        = cartasNecesarias.filter(c => !c.tienes);

    const filaRecibiras = cartasRecibidas.map(c => `
        <div class="modal-carta-fila">
            ${c.imagen_url ? `<img src="${escapeHtml(c.imagen_url)}" alt="${escapeHtml(c.nombre)}" />` : '<div class="modal-carta-placeholder" aria-hidden="true">?</div>'}
            <div class="modal-carta-info">
                <strong>${escapeHtml(c.nombre)}</strong>
                <span>${escapeHtml(c.tipo || '')} ${c.rareza ? '· ' + escapeHtml(c.rareza) : ''}</span>
            </div>
            <span class="modal-check exito">+ Recibirás</span>
        </div>`).join('');

    const filasDaras = cartasNecesarias.map(({ carta, tienes }) => `
        <div class="modal-carta-fila">
            ${carta.imagen_url ? `<img src="${escapeHtml(carta.imagen_url)}" alt="${escapeHtml(carta.nombre)}" />` : '<div class="modal-carta-placeholder" aria-hidden="true">?</div>'}
            <div class="modal-carta-info">
                <strong>${escapeHtml(carta.nombre)}</strong>
                <span>${escapeHtml(carta.tipo || '')} ${carta.rareza ? '· ' + escapeHtml(carta.rareza) : ''}</span>
            </div>
            <span class="modal-check ${tienes ? 'exito' : 'error'}">${tienes ? '✓ Tienes' : '✗ No tienes'}</span>
        </div>`).join('');

    document.getElementById('modal-cuerpo').innerHTML = `
        <div class="modal-seccion">
            <h4 class="modal-seccion-titulo recibiras">Recibirás</h4>
            ${filaRecibiras || '<p class="modal-vacio">Sin cartas</p>'}
        </div>
        <div class="modal-seccion">
            <h4 class="modal-seccion-titulo daras">Darás a cambio</h4>
            ${filasDaras || '<p class="modal-vacio">Sin cartas requeridas</p>'}
        </div>`;

    const estadoEl     = document.getElementById('modal-estado');
    const btnConfirmar = document.getElementById('btn-confirmar');

    if (todasDisponibles) {
        estadoEl.textContent = 'Tienes todas las cartas necesarias para aceptar este tradeo.';
        estadoEl.className = 'modal-estado exito';
        btnConfirmar.disabled = false;
    } else {
        const nombres = faltantes.map(c => c.carta.nombre).join(', ');
        estadoEl.textContent = `Te faltan: ${nombres}`;
        estadoEl.className = 'modal-estado error';
        btnConfirmar.disabled = true;
    }
}

async function confirmarAceptar() {
    if (!tradeoEnCurso) return;

    const btnConfirmar = document.getElementById('btn-confirmar');
    btnConfirmar.disabled = true;
    btnConfirmar.textContent = 'Procesando...';

    try {
        const res = await fetch(`${API_URL}/tradeos/${tradeoEnCurso.id}/aceptar`, {
            method: 'POST',
            headers: headersAuth()
        });
        const datos = await parsearRespuesta(res);
        if (!res.ok) throw new Error(datos.error || `Error ${res.status}`);

        cerrarModal();
        mostrarToastExito(tradeoEnCurso);
        marcarTradeoAceptado(tradeoEnCurso.id);

    } catch (e) {
        const estadoEl = document.getElementById('modal-estado');
        estadoEl.textContent = `Error durante el intercambio: ${e.message}`;
        estadoEl.className = 'modal-estado error';
        btnConfirmar.disabled = false;
        btnConfirmar.textContent = 'Confirmar intercambio';
    }
}

function cerrarModal() {
    document.getElementById('modal-aceptar').hidden = true;
    tradeoEnCurso = null;
    inventarioUsuario = [];
    // Accesibilidad: devuelve el foco al botón que abrió el modal
    cerrarModalAccesible();
}

function marcarTradeoAceptado(tradeoId) {
    const card = document.getElementById(`tradeo-card-${tradeoId}`);
    if (!card) return;

    card.classList.add('tradeo-card-saliendo');

    setTimeout(() => {
        card.remove();
        todosLosTradeos = todosLosTradeos.filter(t => t.id !== tradeoId);

        const grid     = document.getElementById('grid-tradeos');
        const activos  = grid.querySelectorAll('.tradeo-card').length;
        const contador = document.getElementById('contador');
        contador.textContent = activos === 1 ? '1 tradeo activo' : `${activos} tradeos activos`;

        if (activos === 0) {
            grid.innerHTML = `
                <div class="vacio-msg">
                    <p>No hay tradeos que coincidan con tu búsqueda.</p>
                    <button class="btn-secundario" type="button" data-accion="limpiar">Limpiar filtros</button>
                </div>`;
        }
    }, 400);
}

function mostrarToastExito(tradeo) {
    const nombres = (tradeo.cartas_ofrece || []).map(c => c.nombre).join(', ');
    const toast = document.getElementById('toast-mkt');
    toast.textContent = `¡Intercambio completado! Has recibido: ${nombres}`;
    toast.hidden = false;
    setTimeout(() => { toast.hidden = true; }, 4500);
}

// ─── Utilidades ────────────────────────────────────────

function limpiarFiltros() {
    document.getElementById('buscar-carta').value = '';
    document.getElementById('filtro-tipo').value  = '';
    filtrar();
}

function mostrarError(msg) {
    const box = document.getElementById('error-box');
    document.getElementById('error-msg').textContent = msg;
    box.hidden = false;
    document.getElementById('grid-tradeos').innerHTML = '';
    document.getElementById('contador').textContent = '';
}

function ocultarError() {
    document.getElementById('error-box').hidden = true;
}

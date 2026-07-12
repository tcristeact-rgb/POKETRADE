// marketplace.js — Listado público de tradeos activos

import { API_URL, estaLogueado, obtenerUsuario, headersAuth, irALogin, accionPendiente, parsearRespuesta } from './auth.js';
import { formatearFecha, formatearPrecio, escapeHtml, dorsoCarta, abrirModalAccesible, cerrarModalAccesible } from './utils.js';
import { abrirLightbox, cerrarLightbox } from './lightbox.js';

let todosLosTradeos   = [];
let inventarioUsuario = [];
let tradeoEnCurso     = null;
let tradeoDetalle     = null;   // Tradeo que muestra el modal de detalle
let urlEmpujada       = false;  // ¿El modal abierto metió una entrada en el historial?

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
        else if (el.dataset.accion === 'detalle') abrirModalDetalle(Number(el.dataset.tradeoId));
        else if (el.dataset.accion === 'limpiar') limpiarFiltros();
        else if (el.dataset.accion === 'login') {
            irALogin('aceptar', { tipo: 'aceptar', tradeoId: Number(el.dataset.tradeoId) });
        }
    });

    montarModalDetalle();

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
    // Esqueletos también al reintentar tras un error, no solo en la
    // primera carga (los del HTML ya se habrán reemplazado)
    document.getElementById('grid-tradeos').innerHTML = Array(8).fill(
        '<div class="skeleton-card" aria-hidden="true">' +
        '<div class="skeleton-block skeleton-block-header"></div>' +
        '<div class="skeleton-card-cuerpo">' +
        '<div class="skeleton-block skeleton-block-grande"></div>' +
        '<div class="skeleton-block skeleton-block-linea"></div>' +
        '</div>' +
        '<div class="skeleton-block skeleton-block-footer"></div>' +
        '</div>').join('');
    try {
        const res = await fetch(`${API_URL}/tradeos`);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        todosLosTradeos = await res.json();
        filtrar();
        retomarAccionPendiente();
        abrirDesdeUrl();
    } catch (e) {
        mostrarError('No se pudieron cargar los tradeos. ¿Está el servidor activo?');
    }
}

// Enlace compartido o recarga con ?tradeo=X. La acción pendiente del login
// tiene prioridad: a quien viene de autenticarse le prometimos devolverlo a
// su punto de decisión, y ese es el modal de aceptar, no el de detalle.
async function abrirDesdeUrl() {
    if (!document.getElementById('modal-aceptar')?.hidden) return;

    const id = Number(new URLSearchParams(window.location.search).get('tradeo'));
    if (!id) return;

    const activo = todosLosTradeos.find(t => t.id === id);
    if (activo) {
        mostrarModalDetalle(activo, { empujarUrl: false });
        return;
    }

    // No está entre los activos: el enlace puede apuntar a un tradeo ya
    // cerrado. El endpoint de detalle sí lo devuelve, y el modal lo enseña
    // en su estado real, sin CTA. Si tampoco existe, se limpia el parámetro.
    try {
        const res = await fetch(`${API_URL}/tradeos/${id}`);
        if (!res.ok) throw new Error();
        mostrarModalDetalle(await res.json(), { empujarUrl: false });
    } catch (_) {
        const url = new URL(window.location.href);
        url.searchParams.delete('tradeo');
        history.replaceState(null, '', url);
    }
}

// El usuario pulsó "aceptar" sin sesión, pasó por el login y ha vuelto:
// le reabrimos el modal del tradeo que quería. Abrirlo no ejecuta nada,
// solo lo devuelve al punto de decisión donde lo dejó.
function retomarAccionPendiente() {
    const accion = accionPendiente();
    if (accion?.tipo !== 'aceptar' || !estaLogueado()) return;

    const tradeo = todosLosTradeos.find(t => t.id === accion.tradeoId);
    const usuario = obtenerUsuario();
    // Ya no existe, o resulta ser suyo: mejor no abrir nada
    if (!tradeo || tradeo.usuario?.id === usuario?.id) return;

    abrirModalAceptar(accion.tradeoId);
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
        // Al login recordando este tradeo: al volver se reabre su modal
        botonAccion = `<button class="btn-contactar" type="button" data-accion="login" data-tradeo-id="${t.id}">Inicia sesión para aceptar</button>`;
    } else if (esPropioTradeo) {
        botonAccion = `<button class="btn-contactar btn-propio" type="button" disabled>Tu propio tradeo</button>`;
    } else {
        botonAccion = `<button class="btn-contactar" type="button" data-accion="aceptar" data-tradeo-id="${t.id}">Aceptar tradeo</button>`;
    }

    // Cabecera + cuerpo son UN botón que abre el detalle; el de aceptar
    // queda fuera. Así no se anidan elementos interactivos (un botón dentro
    // de otro es HTML inválido y un lío para los lectores de pantalla): la
    // tarjeta es un solo tab-stop y el CTA otro, sin ARIA de mentira.
    return `
    <article class="tradeo-card" id="tradeo-card-${t.id}">
        <button class="tradeo-card-abrir" type="button" data-accion="detalle" data-tradeo-id="${t.id}"
                aria-label="Ver detalle del tradeo de ${escapeHtml(nombreUser)}">
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
        </button>
        <div class="tradeo-card-footer">
            ${botonAccion}
        </div>
    </article>`;
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

// ─── Modal: detalle del tradeo ─────────────────────────
// Superficie pública e informativa. No pide nada al backend: /api/tradeos
// ya trajo el tradeo entero (autor, estado, fecha y las dos listas de
// cartas con precio), así que se pinta al instante. Aceptar sigue siendo
// un paso aparte —mueve cartas de verdad y no se deshace—, así que el CTA
// entrega el control al modal de aceptar en vez de ejecutar nada aquí.

function montarModalDetalle() {
    const modal = document.getElementById('modal-detalle');
    if (!modal) return;

    document.getElementById('btn-cerrar-detalle')?.addEventListener('click', cerrarModalDetalle);

    modal.addEventListener('click', (e) => {
        // Click en el fondo oscurecido (no en la caja) → cerrar
        if (e.target === modal) { cerrarModalDetalle(); return; }

        const zoom = e.target.closest('[data-zoom]');
        if (zoom) { abrirZoom(Number(zoom.dataset.zoom)); return; }

        const el = e.target.closest('[data-accion]');
        if (!el) return;

        if (el.dataset.accion === 'cerrar-detalle') {
            cerrarModalDetalle();
        } else if (el.dataset.accion === 'login') {
            irALogin('aceptar', { tipo: 'aceptar', tradeoId: Number(el.dataset.tradeoId) });
        } else if (el.dataset.accion === 'aceptar-desde-detalle') {
            // Detalle y aceptar nunca se apilan: el primero cede el paso
            const id = Number(el.dataset.tradeoId);
            cerrarModalDetalle();
            abrirModalAceptar(id);
        }
    });

    // Ilustraciones muertas dentro del modal → dorso propio (error no burbujea)
    modal.addEventListener('error', (e) => {
        const img = e.target;
        if (!(img instanceof HTMLImageElement) || img.dataset.rota) return;
        if (img.classList.contains('carta-dorso')) return;
        img.dataset.rota = '1';
        if (img.closest('.detalle-carta')) img.outerHTML = dorsoCarta();
    }, true);

    // Atrás del navegador: cierra el modal (y adelante lo reabre)
    window.addEventListener('popstate', sincronizarConUrl);
}

function abrirModalDetalle(tradeoId, opciones = {}) {
    const tradeo = todosLosTradeos.find(t => t.id === tradeoId);
    if (tradeo) mostrarModalDetalle(tradeo, opciones);
}

function mostrarModalDetalle(tradeo, { empujarUrl = true } = {}) {
    tradeoDetalle = tradeo;
    renderizarModalDetalle(tradeo);

    const modal = document.getElementById('modal-detalle');
    modal.hidden = false;
    abrirModalAccesible(modal, cerrarModalDetalle);

    // El modal abierto vive en la URL: se puede compartir y recargar, y
    // Atrás lo cierra (que es lo que espera cualquiera en el móvil)
    if (empujarUrl) {
        const url = new URL(window.location.href);
        url.searchParams.set('tradeo', tradeo.id);
        history.pushState({ tradeo: tradeo.id }, '', url);
        urlEmpujada = true;
    }
}

function cerrarModalDetalle() {
    const modal = document.getElementById('modal-detalle');
    if (!modal || modal.hidden) return;

    // Si había una carta ampliada encima, no dejarla flotando sobre la nada
    cerrarLightbox();

    modal.hidden = true;
    tradeoDetalle = null;
    cerrarModalAccesible();

    if (urlEmpujada) {
        // Deshace la entrada que metimos: cerrar con la ✕ y con Atrás
        // dejan el historial igual, sin acumular basura
        urlEmpujada = false;
        history.back();
    } else if (new URLSearchParams(window.location.search).has('tradeo')) {
        // Se abrió desde un enlace compartido, sin entrada propia: basta limpiar
        const url = new URL(window.location.href);
        url.searchParams.delete('tradeo');
        history.replaceState(null, '', url);
    }
}

// Mantiene el modal y la URL de acuerdo cuando el usuario usa atrás/adelante
function sincronizarConUrl() {
    const modal = document.getElementById('modal-detalle');
    if (!modal) return;

    const id       = Number(new URLSearchParams(window.location.search).get('tradeo'));
    const abierto  = !modal.hidden;

    if (id && !abierto) {
        abrirModalDetalle(id, { empujarUrl: false });
    } else if (!id && abierto) {
        // El historial ya está donde debe: cerrar sin volver a tocarlo
        urlEmpujada = false;
        cerrarModalDetalle();
    }
}

function renderizarModalDetalle(t) {
    const nombreUser = t.usuario ? `${t.usuario.nombre} ${t.usuario.apellido}` : 'Usuario';
    const inicial    = (t.usuario?.nombre?.[0] || '?').toUpperCase();

    document.getElementById('detalle-avatar').textContent  = inicial;
    document.getElementById('detalle-titulo').textContent   = `Tradeo de ${nombreUser}`;
    document.getElementById('detalle-meta').innerHTML =
        `<span class="detalle-estado estado-${escapeHtml(t.estado)}">${escapeHtml(t.estado)}</span>` +
        `<span class="detalle-fecha">Publicado el ${formatearFecha(t.created_at)}</span>`;

    const ofrece = t.cartas_ofrece || [];
    const busca  = t.cartas_busca  || [];

    document.getElementById('detalle-cuerpo').innerHTML = `
        ${t.descripcion ? `<p class="detalle-descripcion">“${escapeHtml(t.descripcion)}”</p>` : ''}
        <div class="detalle-intercambio">
            ${ladoDetalle('ofrece', 'Ofrece', ofrece, 0)}
            <div class="detalle-cambio" aria-hidden="true">⇄</div>
            ${ladoDetalle('busca', 'Busca a cambio', busca, ofrece.length)}
        </div>`;

    document.getElementById('detalle-footer').innerHTML = pieModalDetalle(t);
}

// Un lado del intercambio. `offset` desplaza el índice de cada carta dentro
// de la lista combinada (ofrece + busca) que recibe el lightbox, para poder
// recorrer el tradeo entero con las flechas sin salir del zoom.
function ladoDetalle(clase, etiqueta, cartas, offset) {
    const contenido = cartas.length
        ? cartas.map((c, i) => cartaDetalle(c, offset + i)).join('')
        : '<p class="modal-vacio">Sin cartas</p>';

    return `
        <section class="detalle-lado detalle-lado-${clase}">
            <h4 class="intercambio-label label-${clase}">
                ${etiqueta} <span class="detalle-cuenta">${cartas.length}</span>
            </h4>
            <div class="detalle-cartas">${contenido}</div>
            ${valorLado(cartas)}
        </section>`;
}

function cartaDetalle(c, indiceGlobal) {
    const imagen = c.imagen_low || c.imagen_url;
    const nombre = escapeHtml(c.nombre);
    const precio = formatearPrecio(c.precio_cardmarket);
    const meta   = [c.rareza, c.set_expansion].filter(Boolean).map(escapeHtml).join(' · ');

    return `
        <button class="detalle-carta" type="button" data-zoom="${indiceGlobal}"
                aria-label="Ampliar la ilustración de ${nombre}">
            ${imagen
                ? `<img src="${escapeHtml(imagen)}" alt="${nombre}" loading="lazy" />`
                : dorsoCarta()}
            <span class="detalle-carta-nombre">${nombre}</span>
            ${meta ? `<span class="detalle-carta-meta">${meta}</span>` : ''}
            <span class="detalle-carta-precio">${precio || '—'}</span>
        </button>`;
}

// Valor de referencia del lado. Los precios de Cardmarket son dispersos:
// se suma lo que hay y se dice cuántas cartas no cuentan, en vez de fingir
// un total exacto que no lo es.
function valorLado(cartas) {
    const conPrecio = cartas.filter(c => c.precio_cardmarket != null);
    if (!conPrecio.length) {
        return cartas.length
            ? '<p class="detalle-valor detalle-valor-vacio">Sin precio de referencia</p>'
            : '';
    }

    const total     = formatearPrecio(conPrecio.reduce((s, c) => s + Number(c.precio_cardmarket), 0));
    const sinPrecio = cartas.length - conPrecio.length;

    return `<p class="detalle-valor">~ ${total}` +
        (sinPrecio ? ` <span class="detalle-valor-nota">· ${sinPrecio} sin precio</span>` : '') +
        `</p>`;
}

function pieModalDetalle(t) {
    const cerrar = '<button class="btn-cancelar-modal" type="button" data-accion="cerrar-detalle">Cerrar</button>';

    // Enlace compartido de un tradeo ya cerrado o cancelado
    if (t.estado !== 'activo') {
        return `<p class="detalle-no-disponible">Este tradeo ya no está disponible.</p>${cerrar}`;
    }

    if (!estaLogueado()) {
        return cerrar +
            `<button class="btn-confirmar-modal" type="button" data-accion="login" data-tradeo-id="${t.id}">Inicia sesión para aceptar</button>`;
    }

    const usuarioActual = obtenerUsuario();
    if (usuarioActual && t.usuario?.id === usuarioActual.id) {
        return cerrar + '<button class="btn-confirmar-modal" type="button" disabled>Tu propio tradeo</button>';
    }

    return cerrar +
        `<button class="btn-confirmar-modal" type="button" data-accion="aceptar-desde-detalle" data-tradeo-id="${t.id}">Aceptar tradeo</button>`;
}

// Zoom desde el modal: el lightbox recibe TODAS las cartas del tradeo, no
// solo la pulsada. Valorar un tradeo es comparar unas cartas con otras, y
// así se recorren todas con las flechas sin cerrar nada.
function abrirZoom(indice) {
    if (!tradeoDetalle) return;

    const todas = [...(tradeoDetalle.cartas_ofrece || []), ...(tradeoDetalle.cartas_busca || [])];
    if (!todas[indice]) return;

    abrirLightbox(todas, indice, { enlaceFicha: true });
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

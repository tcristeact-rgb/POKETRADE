// detalle-carta.js — Detalle de una carta del catálogo (módulo ES6)
// Recibe el ID interno de la carta por parámetro ?id= en la URL y pide
// los datos a nuestra API (que incluye anterior_id/siguiente_id para
// navegar por el catálogo sin asumir IDs consecutivos).

import { API_URL, estaLogueado, headersAuth, irALogin, manejarErrorHTTP, parsearRespuesta } from './auth.js';
import { t } from './i18n.js';
import { alCargarDOM, escapeHtml, mostrarAlerta, formatearPrecio, dorsoCarta } from './utils.js';
import { abrirLightbox } from './lightbox.js';

let cartaActual = null;

alCargarDOM(() => {
    const id = new URLSearchParams(window.location.search).get('id');

    if (!id) {
        mostrarError(t('carta.noEspecificada'));
        return;
    }

    cargarDetalle(id);
});

// Si el usuario usa atrás/adelante del navegador entre cartas, recargamos
// el detalle correspondiente al ?id= de la URL restaurada.
window.addEventListener('popstate', () => {
    const id = new URLSearchParams(window.location.search).get('id');
    if (id) cargarDetalle(id);
});

async function cargarDetalle(id) {
    // Skeleton mientras carga
    document.getElementById('contenido-detalle').innerHTML =
        '<div class="skeleton-detalle"></div>';

    try {
        const res = await fetch(`${API_URL}/cartas/${id}`);

        if (res.status === 404) { mostrarError(t('carta.noExiste')); return; }
        if (!res.ok) throw new Error(t('comun.errorApi'));

        cartaActual = await res.json();
        renderizarDetalle(cartaActual);

    } catch (e) {
        mostrarError(t('carta.errorCargar', { mensaje: e.message }));
    }
}

// Fila de atributo del panel de detalle; si la carta no tiene ese dato
// (ilustrador, precio, hp...) no se pinta nada
function filaAtributo(label, valorHTML) {
    if (!valorHTML) return '';
    return `
        <div class="atributo-fila">
            <span class="atributo-label">${label}</span>
            ${valorHTML}
        </div>`;
}

function renderizarDetalle(carta) {
    document.title = t('meta.tituloPagina', { titulo: carta.nombre });

    // Breadcrumb: Inicio › Catálogo › {Set} › {Carta}. El set enlaza a
    // su vista dentro del catálogo unificado (?set=...)
    const miga = document.getElementById('breadcrumb');
    if (miga) {
        miga.innerHTML =
            `<a href="../index.html">${escapeHtml(t('comun.inicio'))}</a> › ` +
            `<a href="catalogo.html">${escapeHtml(t('catalogo.titulo'))}</a> › ` +
            (carta.set_id && carta.set_expansion
                ? `<a href="catalogo.html?set=${encodeURIComponent(carta.set_id)}">${escapeHtml(carta.set_expansion)}</a> › `
                : '') +
            `<span id="breadcrumb-nombre">${escapeHtml(carta.nombre)}</span>`;
    }

    const nombreSeguro = escapeHtml(carta.nombre);

    // Sin sesión, el botón lleva al login recordando esta carta: al
    // volver, el usuario aterriza aquí mismo con el botón ya activo
    const botonInventario = estaLogueado()
        ? `<button class="btn-inventario" id="btn-add-inventario" type="button">${escapeHtml(t('carta.anadirInventario'))}</button>`
        : `<button class="btn-primario" id="btn-login-inventario" type="button">${escapeHtml(t('carta.iniciaSesionAnadir'))}</button>`;

    // En el detalle usamos la ilustración en alta calidad (high.webp);
    // imagen_url queda como respaldo para filas antiguas
    const imagen = carta.imagen_high || carta.imagen_url;

    // Precio medio de Cardmarket en EUR — puede no existir
    const precio = formatearPrecio(carta.precio_cardmarket);

    // Set + número de coleccionista dentro del set (ej: "151 · Nº 006")
    const setHTML = carta.set_expansion
        ? `<span>${escapeHtml(carta.set_expansion)}${carta.numero ? ` · ${escapeHtml(t('carta.numero', { numero: carta.numero }))}` : ''}</span>`
        : '';

    document.getElementById('contenido-detalle').innerHTML = `
        <div class="detalle-nav">
        <button class="detalle-flecha" id="detalle-flecha-prev" type="button" aria-label="${escapeHtml(t('carta.verAnterior'))}" ${carta.anterior_id ? '' : 'disabled'}>❮</button>
        <div class="detalle-card">
            <div class="detalle-imagen">
                ${imagen
                    ? `<button class="detalle-imagen-zoom" id="btn-zoom-carta" type="button"
                          aria-label="${escapeHtml(t('carta.ampliar', { nombre: carta.nombre }))}">
                          <img src="${escapeHtml(imagen)}" alt="${nombreSeguro}" />
                       </button>`
                    : dorsoCarta()}
            </div>
            <div class="detalle-info">
                <h1>${nombreSeguro}</h1>
                <div class="atributos">
                    ${filaAtributo(t('carta.tipo'),   carta.tipo   ? `<span class="badge-tipo">${escapeHtml(carta.tipo)}</span>`     : '')}
                    ${filaAtributo(t('carta.rareza'), carta.rareza ? `<span class="badge-rareza">${escapeHtml(carta.rareza)}</span>` : '')}
                    ${filaAtributo(t('carta.set'),    setHTML)}
                    ${filaAtributo(t('carta.ps'),     carta.hp ? `<span>${escapeHtml(t('carta.psValor', { n: carta.hp }))}</span>` : '')}
                    ${filaAtributo(t('carta.ilustracion'), carta.ilustrador ? `<span>${escapeHtml(carta.ilustrador)}</span>` : '')}
                    ${filaAtributo(t('carta.precioMedio'), precio
                        ? `<span class="precio-cardmarket">${precio}</span> <span class="precio-fuente">${escapeHtml(t('carta.fuentePrecio'))}</span>`
                        : '')}
                </div>
                ${carta.descripcion ? `<p class="descripcion-carta">${escapeHtml(carta.descripcion)}</p>` : ''}
                <div class="acciones-carta">
                    ${botonInventario}
                </div>
            </div>
        </div>
        <button class="detalle-flecha" id="detalle-flecha-next" type="button" aria-label="${escapeHtml(t('carta.verSiguiente'))}" ${carta.siguiente_id ? '' : 'disabled'}>❯</button>
        </div>
    `;

    // Enlazar el botón de inventario
    document.getElementById('btn-add-inventario')
        ?.addEventListener('click', () => anadirAInventario(carta));

    // Sin sesión: al login y de vuelta. No se añade la carta sola al
    // volver — hacerlo en silencio sería una sorpresa desagradable; el
    // botón queda a un click.
    document.getElementById('btn-login-inventario')
        ?.addEventListener('click', () => irALogin('anadir'));

    // Zoom de la ilustración: botón accesible también con teclado
    document.getElementById('btn-zoom-carta')
        ?.addEventListener('click', () => abrirLightbox([carta]));

    // URL de imagen muerta → dorso propio, nunca imagen rota
    document.querySelector('.detalle-imagen img')?.addEventListener('error', () => {
        const contenedor = document.querySelector('.detalle-imagen');
        if (contenedor) contenedor.innerHTML = dorsoCarta();
    }, { once: true });

    // Flechas laterales: navegan a la carta anterior / siguiente del catálogo
    document.getElementById('detalle-flecha-prev')
        ?.addEventListener('click', () => irACarta(carta.anterior_id));
    document.getElementById('detalle-flecha-next')
        ?.addEventListener('click', () => irACarta(carta.siguiente_id));
}

// Navega a otra carta sin recargar toda la página
function irACarta(id) {
    if (!id) return;
    history.pushState(null, '', `?id=${id}`);
    window.scrollTo({ top: 0, behavior: 'smooth' });
    cargarDetalle(id);
}

// La carta ya existe en el catálogo del backend: basta con enviar su ID
async function anadirAInventario(carta) {
    const btn = document.getElementById('btn-add-inventario');
    btn.disabled = true;
    btn.textContent = t('carta.anadiendo');

    try {
        const res = await fetch(`${API_URL}/inventario`, {
            method: 'POST',
            headers: headersAuth(),
            body: JSON.stringify({ carta_id: carta.id, cantidad: 1 })
        });
        const datos = await parsearRespuesta(res);
        if (!res.ok) throw new Error(datos.error || manejarErrorHTTP(res.status));
        mostrarAlerta(t('carta.anadida'), 'exito');
        btn.textContent = t('carta.enInventario');
    } catch (e) {
        mostrarAlerta(t('comun.error', { mensaje: e.message }), 'error');
        btn.disabled = false;
        btn.textContent = t('carta.anadirInventario');
    }
}

function mostrarError(msg) {
    document.getElementById('contenido-detalle').innerHTML =
        `<p class="error-texto detalle-error">${msg}</p>`;
}

// detalle-carta.js — Detalle de una carta del catálogo (módulo ES6)
// Recibe el ID interno de la carta por parámetro ?id= en la URL y pide
// los datos a nuestra API (que incluye anterior_id/siguiente_id para
// navegar por el catálogo sin asumir IDs consecutivos).

import { API_URL, estaLogueado, headersAuth, manejarErrorHTTP, parsearRespuesta } from './auth.js';
import { escapeHtml, mostrarAlerta, formatearPrecio } from './utils.js';
import { abrirLightbox } from './lightbox.js';

let cartaActual = null;

document.addEventListener('DOMContentLoaded', () => {
    const id = new URLSearchParams(window.location.search).get('id');

    if (!id) {
        mostrarError('No se especificó ninguna carta.');
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

        if (res.status === 404) { mostrarError('Esta carta no existe.'); return; }
        if (!res.ok) throw new Error('Error al conectar con la API');

        cartaActual = await res.json();
        renderizarDetalle(cartaActual);

    } catch (e) {
        mostrarError(`Error al cargar la carta: ${e.message}`);
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
    document.title = `${carta.nombre} - PokeTrade`;
    document.getElementById('breadcrumb-nombre').textContent = carta.nombre;

    const nombreSeguro = escapeHtml(carta.nombre);

    // Botón de inventario solo si el usuario está logueado
    const botonInventario = estaLogueado()
        ? `<button class="btn-inventario" id="btn-add-inventario" type="button">+ Añadir a mi inventario</button>`
        : `<a href="login.html" class="btn-primario">Inicia sesión para añadir</a>`;

    // En el detalle usamos la ilustración en alta calidad (high.webp);
    // imagen_url queda como respaldo para filas antiguas
    const imagen = carta.imagen_high || carta.imagen_url;

    // Precio medio de Cardmarket en EUR — puede no existir
    const precio = formatearPrecio(carta.precio_cardmarket);

    // Set + número de coleccionista dentro del set (ej: "151 · Nº 006")
    const setHTML = carta.set_expansion
        ? `<span>${escapeHtml(carta.set_expansion)}${carta.numero ? ` · Nº ${escapeHtml(carta.numero)}` : ''}</span>`
        : '';

    document.getElementById('contenido-detalle').innerHTML = `
        <div class="detalle-nav">
        <button class="detalle-flecha" id="detalle-flecha-prev" type="button" aria-label="Ver carta anterior" ${carta.anterior_id ? '' : 'disabled'}>❮</button>
        <div class="detalle-card">
            <div class="detalle-imagen">
                ${imagen
                    ? `<button class="detalle-imagen-zoom" id="btn-zoom-carta" type="button"
                          aria-label="Ampliar ilustración de ${nombreSeguro}">
                          <img src="${escapeHtml(imagen)}" alt="${nombreSeguro}" />
                       </button>`
                    : `<div class="sin-imagen-grande" aria-hidden="true">?</div>`}
            </div>
            <div class="detalle-info">
                <h1>${nombreSeguro}</h1>
                <div class="atributos">
                    ${filaAtributo('Tipo',        carta.tipo   ? `<span class="badge-tipo">${escapeHtml(carta.tipo)}</span>`       : '')}
                    ${filaAtributo('Rareza',      carta.rareza ? `<span class="badge-rareza">${escapeHtml(carta.rareza)}</span>`   : '')}
                    ${filaAtributo('Set',         setHTML)}
                    ${filaAtributo('PS',          carta.hp ? `<span>${carta.hp} PS</span>` : '')}
                    ${filaAtributo('Ilustración', carta.ilustrador ? `<span>${escapeHtml(carta.ilustrador)}</span>` : '')}
                    ${filaAtributo('Precio medio', precio
                        ? `<span class="precio-cardmarket">${precio}</span> <span class="precio-fuente">(Cardmarket)</span>`
                        : '')}
                </div>
                ${carta.descripcion ? `<p class="descripcion-carta">${escapeHtml(carta.descripcion)}</p>` : ''}
                <div class="acciones-carta">
                    ${botonInventario}
                </div>
            </div>
        </div>
        <button class="detalle-flecha" id="detalle-flecha-next" type="button" aria-label="Ver carta siguiente" ${carta.siguiente_id ? '' : 'disabled'}>❯</button>
        </div>
    `;

    // Enlazar el botón de inventario
    document.getElementById('btn-add-inventario')
        ?.addEventListener('click', () => anadirAInventario(carta));

    // Zoom de la ilustración: botón accesible también con teclado
    document.getElementById('btn-zoom-carta')
        ?.addEventListener('click', () => abrirLightbox([carta]));

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
    btn.textContent = 'Añadiendo...';

    try {
        const res = await fetch(`${API_URL}/inventario`, {
            method: 'POST',
            headers: headersAuth(),
            body: JSON.stringify({ carta_id: carta.id, cantidad: 1 })
        });
        const datos = await parsearRespuesta(res);
        if (!res.ok) throw new Error(datos.error || manejarErrorHTTP(res.status));
        mostrarAlerta('Carta añadida al inventario.', 'exito');
        btn.textContent = '✓ En inventario';
    } catch (e) {
        mostrarAlerta(`Error: ${e.message}`, 'error');
        btn.disabled = false;
        btn.textContent = '+ Añadir a mi inventario';
    }
}

function mostrarError(msg) {
    document.getElementById('contenido-detalle').innerHTML =
        `<p class="error-texto detalle-error">${msg}</p>`;
}

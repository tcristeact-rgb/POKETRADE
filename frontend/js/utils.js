// utils.js – Funciones compartidas entre todas las páginas
// Módulo ES6: importa paginaUrl de auth.js y exporta utilidades.

import { API_URL, paginaUrl } from './auth.js';

// ─── Placeholders propios de PokeTrade para assets ausentes ──
// Para los logos/ilustraciones que TCGdex no tiene no existe fuente
// legítima alternativa, así que se cubren con marca propia (SVG/CSS,
// cero terceros). Alimentan tanto el dato ausente (imagen null) como
// la red de seguridad de imágenes rotas (onerror).

// Dorso de carta genérico (diseño propio, NO el dorso oficial). Se
// emite como <img> en el hueco de la ilustración: hereda el tamaño
// del selector de cada contexto (grid 155px, detalle 210px, mini
// 52px...). extraClase sirve para contextos con clase propia
// (marketplace: tradeo-protagonista / busca-mini).
export const URL_DORSO = paginaUrl('img/carta-dorso.svg');

export function dorsoCarta(extraClase = '') {
    return `<img class="carta-dorso ${extraClase}" src="${URL_DORSO}" alt="" aria-hidden="true" />`;
}

// Placeholder tipográfico para serie/set sin logo: el nombre en la
// tipografía display sobre carbón con acento ámbar (wordmark). Un
// solo componente, sin archivo por set; el nombre se auto-ajusta con
// container queries. Si el set tiene símbolo real se incrusta como
// pequeña marca (asset legítimo aprovechado).
export function placeholderLogo(nombre, simbolo = null) {
    const seguro = escapeHtml(nombre || 'Sin nombre');
    return `<div class="ph-logo" role="img" aria-label="${seguro}">` +
        (simbolo ? `<img class="ph-logo-simbolo" src="${escapeHtml(simbolo)}" alt="" loading="lazy" />` : '') +
        `<span class="ph-logo-nombre">${seguro}</span>` +
        `<span class="ph-logo-barra" aria-hidden="true"></span>` +
        `</div>`;
}

// Retrasa la ejecución de fn hasta que pasen ms sin nuevas llamadas.
export function debounce(fn, ms) {
    let temporizador;
    return (...args) => {
        clearTimeout(temporizador);
        temporizador = setTimeout(() => fn(...args), ms);
    };
}

// Búsqueda de cartas para los selectores (modal de inventario y
// publicar tradeo). Con el catálogo completo por expansiones ya no se
// puede descargar todo al navegador: se pide al backend la primera
// página de resultados del filtro por nombre, que basta para elegir.
export async function buscarCartasCatalogo(texto = '', limite = 60) {
    const params = new URLSearchParams({ por_pagina: limite });
    if (texto) params.set('nombre', texto);

    const res = await fetch(`${API_URL}/cartas?${params}`);
    if (!res.ok) throw new Error('Error al conectar con la API');
    const datos = await res.json();

    return datos.data.map(c => ({
        id:         c.id,
        nombre:     c.nombre,
        numero:     c.numero,
        imagen_url: c.imagen_low || c.imagen_url,
    }));
}

export function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

export function formatearFecha(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' });
}

// Formatea el precio medio de Cardmarket en EUR (es-ES).
// Devuelve null si la carta no tiene precio publicado, para que el
// llamador decida no pintar nada (manejo con gracia de la ausencia).
export function formatearPrecio(precio) {
    const valor = Number(precio);
    if (precio === null || precio === undefined || Number.isNaN(valor)) return null;
    return new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(valor);
}

export function mostrarAlerta(msg, tipo, elementoId = 'alerta') {
    const el = document.getElementById(elementoId);
    if (!el) return;
    el.className = `alerta ${tipo}`;
    el.textContent = msg;
    // Cancelamos el temporizador anterior para que no borre este mensaje
    clearTimeout(el._alertaTimeout);
    el._alertaTimeout = setTimeout(() => { el.className = 'alerta'; el.textContent = ''; }, 4000);
}

export function tarjetaCarta(carta) {
    const nombre = carta.nombre || 'Sin nombre';
    // ID interno o, si la carta aún no está en BD (resultados de la
    // búsqueda global), el de TCGdex: el detalle acepta ambos y crea
    // la fila bajo demanda
    const id     = carta.id ?? carta.tcgdex_id ?? 0;
    const url    = paginaUrl(`pages/detalle-carta.html?id=${encodeURIComponent(id)}`);
    const nombreSeguro = escapeHtml(nombre);
    // En grids usamos la versión ligera de la ilustración (low.webp);
    // imagen_url queda como respaldo para filas antiguas sin variante
    const imagen  = carta.imagen_low || carta.imagen_url;
    const imgHTML = imagen
        ? `<img src="${escapeHtml(imagen)}" alt="${nombreSeguro}" loading="lazy" />`
        : dorsoCarta();
    const precio = formatearPrecio(carta.precio_cardmarket);
    return `
        <a class="carta-card" href="${url}" aria-label="Ver detalle de ${nombreSeguro}">
            ${imgHTML}
            <div class="carta-info">
                <h3>${nombreSeguro}</h3>
                ${carta.tipo          ? `<span class="carta-tipo">${escapeHtml(carta.tipo)}</span>`         : ''}
                ${carta.rareza        ? `<span class="carta-rareza">${escapeHtml(carta.rareza)}</span>`     : ''}
                ${carta.set_expansion ? `<span class="carta-set">${escapeHtml(carta.set_expansion)}</span>` : ''}
                ${precio              ? `<span class="carta-precio">${precio}</span>`                       : ''}
            </div>
        </a>`;
}

// ─── Red de seguridad para imágenes rotas ──
// Si una URL de asset (TCGdex u otra) devuelve error, la imagen se
// sustituye por el placeholder correspondiente en vez de dejar el
// icono de imagen rota del navegador. Listener delegado en fase de
// captura, porque los eventos error de <img> no burbujean.
export function activarPlaceholderImagenes(idContenedor) {
    const contenedor = document.getElementById(idContenedor);

    contenedor?.addEventListener('error', (e) => {
        const img = e.target;
        if (!(img instanceof HTMLImageElement) || img.dataset.rota) return;
        img.dataset.rota = '1';

        // Símbolo del wordmark tipográfico que muere: solo se oculta
        // (el nombre ya identifica la serie/set), sin degradar más
        if (img.classList.contains('ph-logo-simbolo')) {
            img.style.display = 'none';
            return;
        }

        const logoGrid = img.closest('.set-logo');
        if (logoGrid) {
            // Logo de la baldosa de serie/set → wordmark propio con el
            // nombre (guardado en data-nombre para este momento)
            logoGrid.outerHTML = placeholderLogo(img.dataset.nombre);
            return;
        }

        // Logo de la cabecera de un set: el <h1> contiguo ya muestra el
        // nombre, así que el bloque de logo se retira sin duplicarlo
        const logoCabecera = img.closest('.set-cabecera-logo');
        if (logoCabecera) {
            logoCabecera.remove();
            return;
        }

        // Ilustración de carta (grid o miniatura) → dorso propio
        if (img.matches('.carta-card > img') || img.matches('.miniatura img')) {
            img.outerHTML = dorsoCarta();
            return;
        }

        // Caso genérico: mejor hueco que imagen rota
        img.style.visibility = 'hidden';
    }, true);
}

// ─── Accesibilidad: pila de ventanas modales ──
// Es una PILA, no un singleton: hay capas legítimas (el lightbox se abre
// ENCIMA del modal de detalle de un tradeo para ver una carta en grande).
// Con una sola variable de módulo, abrir el segundo nivel sobrescribía la
// referencia del listener del primero sin quitarlo del document: los dos
// seguían vivos y Escape ejecutaba los dos cerrarFn, cerrando la pila
// entera de golpe; además el foco solo volvía al último nivel cerrado.
//
// Aquí cada nivel guarda su propio listener y el elemento que tenía el
// foco al abrirse, y la trampa de teclado SOLO actúa si es la cima. Así
// Escape y Tab afectan únicamente a la ventana de arriba, y al cerrar, el
// foco se devuelve nivel a nivel (lightbox → carta del modal → tarjeta).
const pilaModales = [];

export function abrirModalAccesible(modal, cerrarFn) {
    if (!modal) return;

    const enfocables = () => Array.from(modal.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]), ' +
        'select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    )).filter(el => el.getClientRects().length > 0);

    const nivel = { focoPrevio: document.activeElement, trampa: null };

    nivel.trampa = function (e) {
        // Los niveles de debajo siguen escuchando, pero se inhiben: solo
        // manda la cima. Sin esto, Escape cerraría también lo que hay bajo.
        if (pilaModales[pilaModales.length - 1] !== nivel) return;

        if (e.key === 'Escape') { e.preventDefault(); cerrarFn(); return; }
        if (e.key !== 'Tab') return;
        const f = enfocables();
        if (!f.length) return;
        const primero = f[0];
        const ultimo  = f[f.length - 1];
        if (e.shiftKey && document.activeElement === primero) {
            e.preventDefault(); ultimo.focus();
        } else if (!e.shiftKey && document.activeElement === ultimo) {
            e.preventDefault(); primero.focus();
        }
    };

    pilaModales.push(nivel);
    document.addEventListener('keydown', nivel.trampa);

    const lista = enfocables();
    if (lista.length) lista[0].focus();

    sincronizarScrollFondo();
}

// Cierra el nivel superior — que es siempre el que el llamador acaba de
// cerrar, porque solo se puede interactuar con la cima.
export function cerrarModalAccesible() {
    const nivel = pilaModales.pop();
    if (!nivel) return;

    document.removeEventListener('keydown', nivel.trampa);

    if (nivel.focoPrevio && typeof nivel.focoPrevio.focus === 'function') {
        nivel.focoPrevio.focus();
    }

    sincronizarScrollFondo();
}

// El fondo no debe scrollarse mientras quede alguna ventana abierta. Lo
// gobierna la pila (y no cada modal por su cuenta) para que cerrar el
// lightbox sobre un modal no desbloquee el scroll con el modal aún abierto.
function sincronizarScrollFondo() {
    document.body.classList.toggle('modal-abierto', pilaModales.length > 0);
}

// Miniaturas de cartas para tarjetas de trade
export function miniaturas(cartas) {
    if (!cartas?.length) return '<span class="miniaturas-vacio">—</span>';
    return cartas.map(c => {
        const imagen = c.imagen_low || c.imagen_url;
        return `
        <div class="miniatura">
            ${imagen
                ? `<img src="${escapeHtml(imagen)}" alt="${escapeHtml(c.nombre)}" loading="lazy" />`
                : dorsoCarta()}
            <span>${escapeHtml(c.nombre)}</span>
        </div>`;
    }).join('');
}


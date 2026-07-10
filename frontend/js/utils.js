// utils.js – Funciones compartidas entre todas las páginas
// Módulo ES6: importa paginaUrl de auth.js y exporta utilidades.

import { API_URL, paginaUrl } from './auth.js';

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
    const id     = carta.id || 0;
    const url    = paginaUrl(`pages/detalle-carta.html?id=${id}`);
    const nombreSeguro = escapeHtml(nombre);
    // En grids usamos la versión ligera de la ilustración (low.webp);
    // imagen_url queda como respaldo para filas antiguas sin variante
    const imagen  = carta.imagen_low || carta.imagen_url;
    const imgHTML = imagen
        ? `<img src="${escapeHtml(imagen)}" alt="${nombreSeguro}" loading="lazy" />`
        : `<div class="carta-sin-imagen" aria-hidden="true"><img class="icono" src="${paginaUrl('img/icons/carta.svg')}" alt="" /></div>`;
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

// ─── Accesibilidad: gestión de foco en ventanas modales ──
let _focoPrevioModal = null;
let _trampaFocoModal = null;

export function abrirModalAccesible(modal, cerrarFn) {
    if (!modal) return;
    _focoPrevioModal = document.activeElement;

    const enfocables = () => Array.from(modal.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]), ' +
        'select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    )).filter(el => el.getClientRects().length > 0);

    const lista = enfocables();
    if (lista.length) lista[0].focus();

    _trampaFocoModal = function (e) {
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
    document.addEventListener('keydown', _trampaFocoModal);
}

export function cerrarModalAccesible() {
    if (_trampaFocoModal) {
        document.removeEventListener('keydown', _trampaFocoModal);
        _trampaFocoModal = null;
    }
    if (_focoPrevioModal && typeof _focoPrevioModal.focus === 'function') {
        _focoPrevioModal.focus();
    }
    _focoPrevioModal = null;
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
                : `<div class="miniatura-sin-img" aria-hidden="true">?</div>`}
            <span>${escapeHtml(c.nombre)}</span>
        </div>`;
    }).join('');
}


// utils.js – Funciones compartidas entre todas las páginas
// Módulo ES6: importa paginaUrl de auth.js y exporta utilidades.

import { paginaUrl } from './auth.js';

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

// ─── Funciones para la PokeAPI ────────────────────────────

// Convierte un objeto de la PokeAPI al formato que usa tarjetaCarta()
export function pokemonACarta(p) {
    return {
        id:            p.id,
        nombre:        capitalizarNombre(p.name),
        tipo:          p.types?.[0]?.type?.name
                           ? traducirTipo(p.types[0].type.name) : null,
        rareza:        calcularRareza(p.base_experience),
        imagen_url:    p.sprites?.other?.['official-artwork']?.front_default
                           || p.sprites?.front_default
                           || null,
        set_expansion: `Gen ${calcularGeneracion(p.id)}`,
        // Datos extra para la página de detalle
        stats:         p.stats,
        altura:        p.height,
        peso:          p.weight,
        tipos:         p.types,         
        habilidades:   p.abilities,
    };
}

// Capitaliza el nombre del Pokémon 
export function capitalizarNombre(nombre) {
    return nombre.charAt(0).toUpperCase() + nombre.slice(1);
}

// Traduce los tipos de inglés a español
export function traducirTipo(tipo) {
    const tipos = {
        fire: 'Fuego', water: 'Agua', grass: 'Planta',
        electric: 'Eléctrico', psychic: 'Psíquico', ice: 'Hielo',
        dragon: 'Dragón', dark: 'Siniestro', fairy: 'Hada',
        normal: 'Normal', fighting: 'Lucha', flying: 'Volador',
        poison: 'Veneno', ground: 'Tierra', rock: 'Roca',
        bug: 'Bicho', ghost: 'Fantasma', steel: 'Acero',
    };
    return tipos[tipo] || tipo;
}

// Calcula la rareza según la experiencia base del Pokémon
export function calcularRareza(exp) {
    if (!exp)       return 'Común';
    if (exp >= 250) return 'Ultra Rara';
    if (exp >= 150) return 'Rara Holo';
    if (exp >= 100) return 'Rara';
    if (exp >= 50)  return 'Poco común';
    return 'Común';
}

// Calcula la generación según el ID del Pokémon
export function calcularGeneracion(id) {
    if (id <= 151)  return 'I';
    if (id <= 251)  return 'II';
    if (id <= 386)  return 'III';
    if (id <= 493)  return 'IV';
    if (id <= 649)  return 'V';
    if (id <= 721)  return 'VI';
    if (id <= 809)  return 'VII';
    if (id <= 905)  return 'VIII';
    return 'IX';
}

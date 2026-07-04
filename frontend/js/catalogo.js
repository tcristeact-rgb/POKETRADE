// catalogo.js — Catálogo de cartas TCG servido por nuestra API (módulo ES6)
// El filtrado y la paginación ocurren en el backend (GET /api/cartas);
// aquí solo pedimos la página que toca y pintamos los resultados.

import { API_URL } from './auth.js';
import { tarjetaCarta } from './utils.js';

const CARTAS_POR_PAGINA = 20;
let paginaActual = 1;
let totalCartas  = 0;
let totalPaginas = 1;

// Retrasa la ejecución de fn hasta que pasen ms sin nuevas llamadas.
function debounce(fn, ms) {
    let temporizador;
    return (...args) => {
        clearTimeout(temporizador);
        temporizador = setTimeout(() => fn(...args), ms);
    };
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('filtro-nombre')?.addEventListener('input', debounce(filtrar, 300));
    document.getElementById('filtro-tipo')?.addEventListener('change', filtrar);
    document.getElementById('filtro-rareza')?.addEventListener('change', filtrar);

    // Paginación: listeners delegados sobre el contenedor
    const paginacionEl = document.getElementById('paginacion');
    paginacionEl?.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-pagina]');
        if (btn && !btn.disabled) { irAPagina(Number(btn.dataset.pagina)); return; }
        if (e.target.closest('[data-accion="ir"]')) saltarAPaginaEscrita();
    });

    paginacionEl?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.id === 'input-ir-pagina') {
            e.preventDefault();
            saltarAPaginaEscrita();
        }
    });

    iniciarCatalogo();
});

async function iniciarCatalogo() {
    // Los <select> de tipo y rareza se rellenan con los valores reales
    // que hay en la BD (no hace falta esperar a que termine)
    cargarFiltros();

    const params = new URLSearchParams(window.location.search);

    // Búsqueda llegada desde el buscador del header (?q=)
    const q = params.get('q');
    if (q) {
        const inputNombre = document.getElementById('filtro-nombre');
        if (inputNombre) inputNombre.value = q;
    }

    // Página inicial: si la URL trae ?page=N volvemos a esa página
    const paginaInicial = Math.max(1, parseInt(params.get('page'), 10) || 1);

    try {
        await cargarPagina(paginaInicial);
    } catch (e) {
        document.getElementById('grid-cartas').innerHTML =
            `<p class="grid-mensaje error-texto">Error al cargar las cartas: ${e.message}</p>`;
    }
}

// Rellena los <select> con los valores distintos presentes en la BD.
// Si falla no pasa nada: los filtros se quedan solo con "Todos".
async function cargarFiltros() {
    try {
        const res = await fetch(`${API_URL}/cartas/filtros`);
        if (!res.ok) return;
        const filtros = await res.json();
        rellenarSelect('filtro-tipo',   filtros.tipos);
        rellenarSelect('filtro-rareza', filtros.rarezas);
    } catch (_) { /* los filtros son secundarios: no rompemos el catálogo */ }
}

// Conserva la primera opción ("Todos los ...") y añade el resto
function rellenarSelect(id, valores) {
    const select = document.getElementById(id);
    if (!select || !Array.isArray(valores)) return;
    select.length = 1;
    valores.forEach(v => select.add(new Option(v, v)));
}

// Valores actuales de los filtros de la barra superior
function filtrosActuales() {
    return {
        nombre: document.getElementById('filtro-nombre')?.value.trim() || '',
        tipo:   document.getElementById('filtro-tipo')?.value || '',
        rareza: document.getElementById('filtro-rareza')?.value || '',
    };
}

async function cargarPagina(pagina) {
    const grid = document.getElementById('grid-cartas');
    grid.innerHTML = Array(8).fill('<div class="carta-card skeleton" aria-hidden="true"></div>').join('');

    const { nombre, tipo, rareza } = filtrosActuales();
    const params = new URLSearchParams({ page: pagina, por_pagina: CARTAS_POR_PAGINA });
    if (nombre) params.set('nombre', nombre);
    if (tipo)   params.set('tipo', tipo);
    if (rareza) params.set('rareza', rareza);

    const res = await fetch(`${API_URL}/cartas?${params}`);
    if (!res.ok) throw new Error('Error al conectar con la API');
    const datos = await res.json();

    // Respuesta del paginador de Laravel: data, total, current_page,
    // last_page, from, to...
    totalCartas  = datos.total;
    totalPaginas = Math.max(1, datos.last_page);
    paginaActual = datos.current_page;

    actualizarUrlPagina(paginaActual);
    mostrarCartas(datos.data);
    actualizarPaginacion(totalCartas === 0);
    actualizarInfo(datos.from ?? 0, datos.to ?? 0, totalCartas);

    // Scroll suave al inicio del catálogo
    document.querySelector('.catalogo-contenedor')?.scrollIntoView({ behavior: 'smooth' });
}

function mostrarCartas(cartas) {
    const grid = document.getElementById('grid-cartas');

    if (!cartas.length) {
        grid.innerHTML = '<p class="grid-mensaje">No se encontraron cartas.</p>';
        return;
    }

    grid.innerHTML = cartas.map(c => tarjetaCarta(c)).join('');
}

// Devuelve el HTML de un botón de paginación accesible
function botonPagina(pagina, etiqueta, { activa = false, deshabilitado = false } = {}) {
    const aria = activa
        ? ` aria-label="Página ${pagina}, página actual" aria-current="page"`
        : ` aria-label="Ir a página ${pagina}"`;
    return `<button class="btn-pagina${activa ? ' activa' : ''}" type="button"` +
           ` data-pagina="${pagina}"${aria}${deshabilitado ? ' disabled' : ''}>${etiqueta}</button>`;
}

// Actualiza la barra de paginación
function actualizarPaginacion(ocultar = false) {
    const contenedor = document.getElementById('paginacion');
    if (!contenedor) return;

    if (ocultar || totalPaginas <= 1) { contenedor.innerHTML = ''; return; }

    let inicio = Math.max(1, paginaActual - 2);
    let fin    = Math.min(totalPaginas, inicio + 4);
    if (fin - inicio < 4) inicio = Math.max(1, fin - 4);

    let html = '';

    html += `<button class="btn-pagina" type="button" data-pagina="${paginaActual - 1}" aria-label="Página anterior" ${paginaActual === 1 ? 'disabled' : ''}>← Ant</button>`;

    if (inicio > 1) {
        html += botonPagina(1, '1');
        if (inicio > 2) html += `<span class="paginacion-puntos" aria-hidden="true">…</span>`;
    }

    for (let i = inicio; i <= fin; i++) {
        html += botonPagina(i, String(i), { activa: i === paginaActual });
    }

    if (fin < totalPaginas) {
        if (fin < totalPaginas - 1) html += `<span class="paginacion-puntos" aria-hidden="true">…</span>`;
        html += botonPagina(totalPaginas, String(totalPaginas));
    }

    html += `<button class="btn-pagina" type="button" data-pagina="${paginaActual + 1}" aria-label="Página siguiente" ${paginaActual === totalPaginas ? 'disabled' : ''}>Sig →</button>`;

    // Salto directo: campo para escribir el número de página al que ir
    html += `<span class="paginacion-ir">` +
            `<label for="input-ir-pagina">Ir a página</label>` +
            `<input type="number" id="input-ir-pagina" class="input-ir-pagina"` +
            ` min="1" max="${totalPaginas}" inputmode="numeric" placeholder="${paginaActual}" />` +
            `<button class="btn-pagina" type="button" data-accion="ir"` +
            ` aria-label="Ir a la página escrita">Ir</button>` +
            `</span>`;

    contenedor.innerHTML = html;
}

// Actualiza el texto de información de paginación
function actualizarInfo(desde, hasta, total) {
    const info = document.getElementById('paginacion-info');
    if (info) info.textContent = `Mostrando ${desde}–${hasta} de ${total.toLocaleString('es-ES')} cartas`;
}

// Navega a una página específica
async function irAPagina(pagina) {
    if (pagina < 1 || pagina > totalPaginas) return;

    try {
        await cargarPagina(pagina);
    } catch (e) {
        document.getElementById('grid-cartas').innerHTML =
            `<p class="grid-mensaje error-texto">Error al cargar las cartas: ${e.message}</p>`;
    }
}

// Refleja la página actual en la URL (?page=N) sin añadir entradas al
// historial, para que el botón "atrás" del navegador devuelva el catálogo
// a la misma página en la que estaba el usuario antes de abrir una carta.
function actualizarUrlPagina(pagina) {
    const url = new URL(window.location.href);
    if (pagina > 1) {
        url.searchParams.set('page', pagina);
    } else {
        url.searchParams.delete('page');
    }
    history.replaceState(null, '', url);
}

// Salta a la página escrita en el campo numérico, acotándola al rango válido.
function saltarAPaginaEscrita() {
    const input = document.getElementById('input-ir-pagina');
    if (!input) return;
    let pagina = parseInt(input.value, 10);
    if (!pagina) return;
    pagina = Math.min(Math.max(1, pagina), totalPaginas);
    irAPagina(pagina);
}

// Los filtros cambiaron: pedimos la página 1 con los filtros nuevos
// (el backend aplica nombre/tipo/rareza sobre todo el catálogo)
function filtrar() {
    irAPagina(1);
}

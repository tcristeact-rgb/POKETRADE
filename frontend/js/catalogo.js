// catalogo.js — Catálogo de cartas TCG servido por nuestra API (módulo ES6)
// El filtrado y la paginación ocurren en el backend (GET /api/cartas);
// aquí solo pedimos la página que toca y pintamos los resultados.
// La barra de paginación es el componente compartido de paginacion.js.

import { API_URL } from './auth.js';
import { tarjetaCarta } from './utils.js';
import { crearPaginacion } from './paginacion.js';
import { activarLightboxEnGrid } from './lightbox.js';

const CARTAS_POR_PAGINA = 20;
let cartasActuales = [];

// Zoom: click en la ilustración de una tarjeta → lightbox con las
// cartas de la página actual (flechas ←/→ para moverse entre ellas)
activarLightboxEnGrid('grid-cartas', () => cartasActuales);

const paginacion = crearPaginacion({
    contenedorId: 'paginacion',
    infoId:       'paginacion-info',
    alCambiar:    irAPagina,
});

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
    actualizarUrlPagina(datos.current_page);
    mostrarCartas(datos.data);
    paginacion.actualizar(datos);

    // Scroll suave al inicio del catálogo
    document.querySelector('.catalogo-contenedor')?.scrollIntoView({ behavior: 'smooth' });
}

function mostrarCartas(cartas) {
    const grid = document.getElementById('grid-cartas');
    cartasActuales = cartas;

    if (!cartas.length) {
        grid.innerHTML = '<p class="grid-mensaje">No se encontraron cartas.</p>';
        return;
    }

    grid.innerHTML = cartas.map(c => tarjetaCarta(c)).join('');
}

// Navega a una página específica
async function irAPagina(pagina) {
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

// Los filtros cambiaron: pedimos la página 1 con los filtros nuevos
// (el backend aplica nombre/tipo/rareza sobre todo el catálogo)
function filtrar() {
    irAPagina(1);
}

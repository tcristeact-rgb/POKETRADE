// catalogo.js — Catálogo completo con paginación, filtros y buscador
// Módulo ES6. Carga los Pokémon de la PokeAPI de 20 en 20.

import { tarjetaCarta, pokemonACarta } from './utils.js';

const CARTAS_POR_PAGINA = 20;       // Pokémon por página
let paginaActual        = 1;        // Página actual
let totalPokemon        = 0;        // Total de Pokémon en la PokeAPI
let cartasFiltradas     = [];       // Resultado de los filtros activos
let todasLasCartas      = [];       // Cache de todas las cartas ya cargadas
let cargandoTodo        = false;    // Carga de fondo en curso
let cargaCompletada     = false;    // Toda la PokeAPI ya está en cache
let conteoFiltradoPrevio = -1;      // Nº de resultados del último refresco

// Retrasa la ejecución de fn hasta que pasen ms sin nuevas llamadas.
function debounce(fn, ms) {
    let temporizador;
    return (...args) => {
        clearTimeout(temporizador);
        temporizador = setTimeout(() => fn(...args), ms);
    };
}

document.addEventListener('DOMContentLoaded', () => {
    // Filtros: se enlazan con addEventListener (sin onclick en línea)
    document.getElementById('filtro-nombre')?.addEventListener('input', debounce(filtrar, 300));
    document.getElementById('filtro-tipo')?.addEventListener('change', filtrar);
    document.getElementById('filtro-rareza')?.addEventListener('change', filtrar);

    // Paginación: un único listener delegado sobre el contenedor
    document.getElementById('paginacion')?.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-pagina]');
        if (btn && !btn.disabled) irAPagina(Number(btn.dataset.pagina));
    });

    iniciarCatalogo();
});

async function iniciarCatalogo() {
    const grid = document.getElementById('grid-cartas');
    grid.innerHTML = Array(8).fill('<div class="carta-card skeleton" aria-hidden="true"></div>').join('');

    try {
        // Pedimos el total de Pokémon disponibles en la PokeAPI
        const resTotal = await fetch('https://pokeapi.co/api/v2/pokemon?limit=1');
        if (!resTotal.ok) throw new Error('Error al conectar con la PokeAPI');
        const datosTotales = await resTotal.json();

        // Solo usamos los Pokémon con ID hasta 1010 (excluye formas especiales)
        totalPokemon = Math.min(datosTotales.count, 1010);

        // Cargamos la primera página
        await cargarPagina(1);

        // Búsqueda desde el header (?q=): filtramos de inmediato con lo que
        // ya hay en cache y dejamos que la carga de fondo complete resultados.
        const q = new URLSearchParams(window.location.search).get('q');
        if (q) {
            const inputNombre = document.getElementById('filtro-nombre');
            if (inputNombre) {
                inputNombre.value = q;
                cargarTodoEnSegundoPlano();   // arranca la carga (no bloquea)
                filtrar();                    // filtra ya, sin esperar
            }
        } else {
            // Sin búsqueda cargamos el resto en segundo plano
            cargarTodoEnSegundoPlano();
        }

    } catch (e) {
        grid.innerHTML = `<p class="grid-mensaje error-texto">Error al cargar las cartas: ${e.message}</p>`;
    }
}

// Carga una página específica de Pokémon
async function cargarPagina(pagina) {
    const grid = document.getElementById('grid-cartas');
    grid.innerHTML = Array(8).fill('<div class="carta-card skeleton" aria-hidden="true"></div>').join('');

    const offset   = (pagina - 1) * CARTAS_POR_PAGINA;
    const res      = await fetch(`https://pokeapi.co/api/v2/pokemon?limit=${CARTAS_POR_PAGINA}&offset=${offset}`);
    if (!res.ok) throw new Error('Error al conectar con la PokeAPI');
    const datos    = await res.json();

    // Cargamos los datos completos de cada Pokémon en paralelo
    const promesas = datos.results.map(p => fetch(p.url).then(r => r.json()));
    const pokemons = await Promise.all(promesas);
    const cartas   = pokemons.map(p => pokemonACarta(p));

    // Guardamos en cache las que no estaban aún
    cartas.forEach(c => {
        if (!todasLasCartas.find(x => x.id === c.id)) {
            todasLasCartas.push(c);
        }
    });

    paginaActual = pagina;
    mostrarCartas(cartas);
    actualizarPaginacion();
    actualizarInfo(offset + 1, Math.min(offset + CARTAS_POR_PAGINA, totalPokemon), totalPokemon);

    // Scroll suave al inicio del catálogo
    document.querySelector('.catalogo-contenedor')?.scrollIntoView({ behavior: 'smooth' });
}

// Carga todos los Pokémon en segundo plano sin bloquear la UI
async function cargarTodoEnSegundoPlano() {
    if (cargandoTodo || cargaCompletada) return;
    cargandoTodo = true;

    const totalPaginas = Math.ceil(totalPokemon / CARTAS_POR_PAGINA);

    for (let p = 1; p <= totalPaginas; p++) {
        const offset     = (p - 1) * CARTAS_POR_PAGINA;
        const yaEnCache  = todasLasCartas.some(c => c.id === offset + 1);
        if (yaEnCache) continue;

        try {
            const res = await fetch(`https://pokeapi.co/api/v2/pokemon?limit=${CARTAS_POR_PAGINA}&offset=${offset}`);
            if (!res.ok) continue;
            const datos    = await res.json();
            const promesas = datos.results.map(p => fetch(p.url).then(r => r.json()));
            const pokemons = await Promise.all(promesas);
            pokemons.forEach(poke => {
                if (!todasLasCartas.find(x => x.id === poke.id)) {
                    todasLasCartas.push(pokemonACarta(poke));
                }
            });
            // Si hay una búsqueda activa, refrescamos los resultados
            // conforme van llegando más cartas (sin esperar a terminar).
            refrescarBusquedaSiActiva();
        } catch (_) {}

        // Pequeña pausa para no saturar la API
        await new Promise(r => setTimeout(r, 100));
    }

    cargandoTodo         = false;
    cargaCompletada      = true;
    conteoFiltradoPrevio = -1;        // fuerza el render del estado final
    refrescarBusquedaSiActiva();
}

function mostrarCartas(cartas) {
    const grid = document.getElementById('grid-cartas');

    if (!cartas.length) {
        grid.innerHTML = '<p class="grid-mensaje">No se encontraron cartas.</p>';
        actualizarPaginacion(true);
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

    if (ocultar) { contenedor.innerHTML = ''; return; }

    const totalPaginas = Math.ceil(totalPokemon / CARTAS_POR_PAGINA);

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

    contenedor.innerHTML = html;
}

// Actualiza el texto de información de paginación
function actualizarInfo(desde, hasta, total) {
    const info = document.getElementById('paginacion-info');
    if (info) info.textContent = `Mostrando ${desde}–${hasta} de ${total.toLocaleString('es-ES')} Pokémon`;
}

// Navega a una página específica
async function irAPagina(pagina) {
    const totalPaginas = Math.ceil(totalPokemon / CARTAS_POR_PAGINA);
    if (pagina < 1 || pagina > totalPaginas) return;

    const nombre = document.getElementById('filtro-nombre')?.value.toLowerCase() || '';
    const tipo   = document.getElementById('filtro-tipo')?.value || '';
    const rareza = document.getElementById('filtro-rareza')?.value || '';

    if (nombre || tipo || rareza) {
        paginaActual = pagina;
        mostrarPaginaFiltrada();
        return;
    }

    await cargarPagina(pagina);
}

// ¿Hay algún filtro activo ahora mismo?
function hayFiltrosActivos() {
    const nombre = document.getElementById('filtro-nombre')?.value.trim() || '';
    const tipo   = document.getElementById('filtro-tipo')?.value || '';
    const rareza = document.getElementById('filtro-rareza')?.value || '';
    return Boolean(nombre || tipo || rareza);
}

// Recalcula cartasFiltradas a partir del cache disponible ahora mismo
function calcularFiltradas() {
    const nombre = document.getElementById('filtro-nombre')?.value.toLowerCase().trim() || '';
    const tipo   = document.getElementById('filtro-tipo')?.value || '';
    const rareza = document.getElementById('filtro-rareza')?.value || '';

    cartasFiltradas = todasLasCartas.filter(c => {
        const coincideNombre = c.nombre.toLowerCase().includes(nombre);
        const coincideTipo   = !tipo   || c.tipo   === tipo;
        const coincideRareza = !rareza || c.rareza === rareza;
        return coincideNombre && coincideTipo && coincideRareza;
    });
}

// Aplica los filtros desde cero (vuelve a la página 1 de resultados)
function filtrar() {
    if (!hayFiltrosActivos()) {
        // Sin filtros volvemos a la paginación normal
        paginaActual = 1;
        cargarPagina(1);
        return;
    }
    calcularFiltradas();
    conteoFiltradoPrevio = cartasFiltradas.length;
    paginaActual = 1;
    mostrarPaginaFiltrada();
}

// Refresca una búsqueda activa sin perder la página del usuario.
// Lo invoca la carga de fondo conforme entran más cartas al cache.
function refrescarBusquedaSiActiva() {
    if (!hayFiltrosActivos()) return;
    calcularFiltradas();
    if (cartasFiltradas.length === conteoFiltradoPrevio) return;  // sin cambios
    conteoFiltradoPrevio = cartasFiltradas.length;
    const totPags = Math.max(1, Math.ceil(cartasFiltradas.length / CARTAS_POR_PAGINA));
    paginaActual  = Math.min(paginaActual, totPags);
    mostrarPaginaFiltrada();
}

// Muestra la página actual de los resultados filtrados
function mostrarPaginaFiltrada() {
    const inicio  = (paginaActual - 1) * CARTAS_POR_PAGINA;
    const fin     = inicio + CARTAS_POR_PAGINA;
    const pagina  = cartasFiltradas.slice(inicio, fin);
    const total   = cartasFiltradas.length;
    const totPags = Math.ceil(total / CARTAS_POR_PAGINA);

    // Sin resultados todavía pero la carga de fondo sigue: estado "buscando"
    if (!total && cargandoTodo) {
        document.getElementById('grid-cartas').innerHTML =
            '<p class="grid-mensaje">Buscando en el catálogo completo…</p>';
        const cont = document.getElementById('paginacion');
        if (cont) cont.innerHTML = '';
        const info = document.getElementById('paginacion-info');
        if (info) info.textContent = '';
        return;
    }

    mostrarCartas(pagina);

    const contenedor = document.getElementById('paginacion');
    if (contenedor) {
        if (totPags <= 1) {
            contenedor.innerHTML = '';
        } else {
            let html = '';
            html += `<button class="btn-pagina" type="button" data-pagina="${paginaActual - 1}" aria-label="Página anterior" ${paginaActual === 1 ? 'disabled' : ''}>← Ant</button>`;
            for (let i = 1; i <= Math.min(totPags, 10); i++) {
                html += botonPagina(i, String(i), { activa: i === paginaActual });
            }
            html += `<button class="btn-pagina" type="button" data-pagina="${paginaActual + 1}" aria-label="Página siguiente" ${paginaActual === totPags ? 'disabled' : ''}>Sig →</button>`;
            contenedor.innerHTML = html;
        }
    }

    actualizarInfo(inicio + 1, Math.min(fin, total), total);

    // Aviso de que la búsqueda todavía sigue completándose en segundo plano
    if (cargandoTodo) {
        const info = document.getElementById('paginacion-info');
        if (info) info.textContent += ' · buscando más…';
    }
}

// catalogo.js — Catálogo completo con paginación, filtros y buscador
// Carga todos los Pokémon de la PokeAPI con paginación de 20 en 20

const CARTAS_POR_PAGINA = 20;       // Pokémon por página
let paginaActual        = 1;        // Página actual
let totalPokemon        = 0;        // Total de Pokémon en la PokeAPI
let cartasFiltradas     = [];       // Resultado de los filtros activos
let todasLasCartas      = [];       // Cache de todas las cartas ya cargadas
let cargandoTodo        = false;    // Indica si está cargando en segundo plano

document.addEventListener('DOMContentLoaded', () => {
    // Comprobamos si hay búsqueda desde el buscador del header (?q=)
    const q = new URLSearchParams(window.location.search).get('q');
    if (q) {
        const inputNombre = document.getElementById('filtro-nombre');
        if (inputNombre) inputNombre.value = q;
    }

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
        await cargarPagina(paginaActual);

        // Si hay búsqueda activa la aplicamos
        const q = new URLSearchParams(window.location.search).get('q');
        if (q) filtrar();

        // Cargamos el resto en segundo plano para que los filtros funcionen con todo
        cargarTodoEnSegundoPlano();

    } catch (e) {
        grid.innerHTML = `<p class="error-texto" style="grid-column:1/-1;padding:2rem;text-align:center;">Error al cargar las cartas: ${e.message}</p>`;
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

// Carga todos los Pokémon en segundo plano para que los filtros funcionen con todo
async function cargarTodoEnSegundoPlano() {
    if (cargandoTodo) return;
    cargandoTodo = true;

    const totalPaginas = Math.ceil(totalPokemon / CARTAS_POR_PAGINA);

    for (let p = 1; p <= totalPaginas; p++) {
        // Saltamos las que ya están en cache
        const offset = (p - 1) * CARTAS_POR_PAGINA;
        const yaEnCache = todasLasCartas.some(c => c.id === offset + 1);
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
        } catch (_) {}

        // Pequeña pausa para no saturar la API
        await new Promise(r => setTimeout(r, 100));
    }
}

function mostrarCartas(cartas) {
    const grid = document.getElementById('grid-cartas');

    if (!cartas.length) {
        grid.innerHTML = '<p style="grid-column:1/-1;text-align:center;color:#888;padding:2rem;">No se encontraron cartas.</p>';
        actualizarPaginacion(true);
        return;
    }

    grid.innerHTML = cartas.map(c => tarjetaCarta(c)).join('');
}

// Actualiza la barra de paginación
function actualizarPaginacion(ocultar = false) {
    const contenedor = document.getElementById('paginacion');
    if (!contenedor) return;

    if (ocultar) { contenedor.innerHTML = ''; return; }

    const totalPaginas = Math.ceil(totalPokemon / CARTAS_POR_PAGINA);

    // Calculamos el rango de páginas a mostrar (máximo 5 botones)
    let inicio = Math.max(1, paginaActual - 2);
    let fin    = Math.min(totalPaginas, inicio + 4);
    if (fin - inicio < 4) inicio = Math.max(1, fin - 4);

    let html = '';

    // Botón anterior
    html += `<button class="btn-pagina" onclick="irAPagina(${paginaActual - 1})" ${paginaActual === 1 ? 'disabled' : ''}>← Ant</button>`;

    // Primera página si no está en el rango
    if (inicio > 1) {
        html += `<button class="btn-pagina" onclick="irAPagina(1)">1</button>`;
        if (inicio > 2) html += `<span style="color:var(--muted);padding:0 0.25rem;">…</span>`;
    }

    // Páginas del rango
    for (let i = inicio; i <= fin; i++) {
        html += `<button class="btn-pagina ${i === paginaActual ? 'activa' : ''}" onclick="irAPagina(${i})">${i}</button>`;
    }

    // Última página si no está en el rango
    if (fin < totalPaginas) {
        if (fin < totalPaginas - 1) html += `<span style="color:var(--muted);padding:0 0.25rem;">…</span>`;
        html += `<button class="btn-pagina" onclick="irAPagina(${totalPaginas})">${totalPaginas}</button>`;
    }

    // Botón siguiente
    html += `<button class="btn-pagina" onclick="irAPagina(${paginaActual + 1})" ${paginaActual === totalPaginas ? 'disabled' : ''}>Sig →</button>`;

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

    // Si hay filtros activos paginamos sobre los resultados filtrados
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

// Filtra las cartas del cache
function filtrar() {
    const nombre = document.getElementById('filtro-nombre')?.value.toLowerCase() || '';
    const tipo   = document.getElementById('filtro-tipo')?.value || '';
    const rareza = document.getElementById('filtro-rareza')?.value || '';

    if (!nombre && !tipo && !rareza) {
        // Sin filtros volvemos a la paginación normal
        paginaActual = 1;
        cargarPagina(1);
        return;
    }

    // Filtramos sobre el cache
    cartasFiltradas = todasLasCartas.filter(c => {
        const coincideNombre = c.nombre.toLowerCase().includes(nombre);
        const coincideTipo   = !tipo   || c.tipo   === tipo;
        const coincideRareza = !rareza || c.rareza === rareza;
        return coincideNombre && coincideTipo && coincideRareza;
    });

    paginaActual = 1;
    mostrarPaginaFiltrada();
}

// Muestra la página actual de los resultados filtrados
function mostrarPaginaFiltrada() {
    const inicio  = (paginaActual - 1) * CARTAS_POR_PAGINA;
    const fin     = inicio + CARTAS_POR_PAGINA;
    const pagina  = cartasFiltradas.slice(inicio, fin);
    const total   = cartasFiltradas.length;
    const totPags = Math.ceil(total / CARTAS_POR_PAGINA);

    mostrarCartas(pagina);

    // Paginación para los filtrados
    const contenedor = document.getElementById('paginacion');
    if (contenedor) {
        if (totPags <= 1) { contenedor.innerHTML = ''; }
        else {
            let html = '';
            html += `<button class="btn-pagina" onclick="irAPagina(${paginaActual - 1})" ${paginaActual === 1 ? 'disabled' : ''}>← Ant</button>`;
            for (let i = 1; i <= Math.min(totPags, 10); i++) {
                html += `<button class="btn-pagina ${i === paginaActual ? 'activa' : ''}" onclick="irAPagina(${i})">${i}</button>`;
            }
            html += `<button class="btn-pagina" onclick="irAPagina(${paginaActual + 1})" ${paginaActual === totPags ? 'disabled' : ''}>Sig →</button>`;
            contenedor.innerHTML = html;
        }
    }

    actualizarInfo(inicio + 1, Math.min(fin, total), total);
}
// catalogo.js — Catálogo completo usando la PokeAPI (151 Pokémon Gen 1)
// Mantiene compatibilidad con el buscador del header (?q=)

const CARTAS_POR_PAGINA = 20;
let todasLasCartas = []; // Cache de todas las cartas cargadas

document.addEventListener('DOMContentLoaded', cargarCartas);

async function cargarCartas() {
    const grid = document.getElementById('grid-cartas');
    grid.innerHTML = Array(8).fill('<div class="carta-card skeleton" aria-hidden="true"></div>').join('');

    try {
        // Pedimos los primeros 151 Pokémon a la PokeAPI (Gen 1 completa)
        const res = await fetch(`https://pokeapi.co/api/v2/pokemon?limit=151&offset=0`);
        if (!res.ok) throw new Error('Error al conectar con la PokeAPI');
        const datos = await res.json();

        // Cargamos los primeros 20 inmediatamente para mostrar algo rápido
        const primeros = datos.results.slice(0, CARTAS_POR_PAGINA);
        const promesas = primeros.map(p => fetch(p.url).then(r => r.json()));
        const pokemons = await Promise.all(promesas);

        // Guardamos en cache y mostramos
        todasLasCartas = pokemons.map(p => pokemonACarta(p));

        // Comprobamos si hay búsqueda desde el header (?q=)
        const q = new URLSearchParams(window.location.search).get('q');
        if (q) {
            const inputNombre = document.getElementById('filtro-nombre');
            if (inputNombre) inputNombre.value = q;
            filtrar();
        } else {
            mostrarCartas(todasLasCartas);
        }

        // Cargamos el resto en segundo plano para que los filtros funcionen con todo
        cargarRestoEnSegundoPlano(datos.results.slice(CARTAS_POR_PAGINA));

    } catch (e) {
        grid.innerHTML = `<p class="error-texto" style="grid-column:1/-1;padding:2rem;text-align:center;">Error al cargar las cartas: ${e.message}</p>`;
    }
}

// Carga el resto de Pokémon en segundo plano sin bloquear la UI
async function cargarRestoEnSegundoPlano(lista) {
    // Los cargamos en grupos de 20 para no saturar la API
    for (let i = 0; i < lista.length; i += 20) {
        const grupo    = lista.slice(i, i + 20);
        const promesas = grupo.map(p => fetch(p.url).then(r => r.json()));
        const pokemons = await Promise.all(promesas);
        todasLasCartas = [...todasLasCartas, ...pokemons.map(p => pokemonACarta(p))];
    }
}

function mostrarCartas(cartas) {
    const grid = document.getElementById('grid-cartas');

    if (!cartas.length) {
        grid.innerHTML = '<p style="grid-column:1/-1;text-align:center;color:#888;padding:2rem;">No se encontraron cartas.</p>';
        return;
    }

    grid.innerHTML = cartas.map(c => tarjetaCarta(c)).join('');
}

// Filtra las cartas del cache sin hacer nuevas peticiones a la API
function filtrar() {
    const nombre = document.getElementById('filtro-nombre')?.value.toLowerCase() || '';
    const tipo   = document.getElementById('filtro-tipo')?.value || '';
    const rareza = document.getElementById('filtro-rareza')?.value || '';

    const filtradas = todasLasCartas.filter(c => {
        const coincideNombre = c.nombre.toLowerCase().includes(nombre);
        const coincideTipo   = !tipo   || c.tipo   === tipo;
        const coincideRareza = !rareza || c.rareza === rareza;
        return coincideNombre && coincideTipo && coincideRareza;
    });

    mostrarCartas(filtradas);
}
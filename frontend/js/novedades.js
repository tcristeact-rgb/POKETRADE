// novedades.js — Pokémon más recientes de la PokeAPI

import { tarjetaCarta, pokemonACarta } from './utils.js';

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('btn-reintentar-novedades')
        ?.addEventListener('click', cargarNovedades);
    cargarNovedades();
});

async function cargarNovedades() {
    const grid     = document.getElementById('grid-novedades');
    const errorBox = document.getElementById('error-novedades');
    const errorMsg = document.getElementById('error-novedades-msg');

    grid.innerHTML = Array(8).fill('<div class="carta-card skeleton" aria-hidden="true"></div>').join('');
    errorBox.hidden = true;

    try {
        // Pedimos el total para saber el ID más alto
        const resTotal = await fetch('https://pokeapi.co/api/v2/pokemon?limit=1');
        if (!resTotal.ok) throw new Error('Error al conectar con la PokeAPI');
        const datosTotales = await resTotal.json();
        const total = Math.min(datosTotales.count, 1010);

        // Cogemos los últimos 60 para tener margen de filtrar los sin imagen
        const offset = Math.max(0, total - 60);
        const res = await fetch(`https://pokeapi.co/api/v2/pokemon?limit=60&offset=${offset}`);
        if (!res.ok) throw new Error('Error al conectar con la PokeAPI');
        const datos = await res.json();

        // Invertimos para mostrar del más nuevo al más antiguo
        const listaInvertida = [...datos.results].reverse();

        // Cargamos datos completos en paralelo
        const promesas = listaInvertida.map(p => fetch(p.url).then(r => r.json()));
        const pokemons = await Promise.all(promesas);

        // Filtramos solo los que tienen artwork oficial
        const conImagen = pokemons
            .filter(p => p.sprites?.other?.['official-artwork']?.front_default)
            .slice(0, 20);

        if (!conImagen.length) {
            grid.innerHTML = '<p class="grid-mensaje">No hay novedades disponibles.</p>';
            return;
        }

        grid.innerHTML = conImagen.map(p => tarjetaCarta(pokemonACarta(p))).join('');

    } catch (error) {
        grid.innerHTML = '';
        errorBox.hidden = false;
        errorMsg.textContent = 'No se pudieron cargar las novedades. Inténtalo más tarde.';
    }
}

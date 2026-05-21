// mas-vendido.js — Ranking de cartas más demandadas en tradeos (módulo ES6)
// Combina datos de nuestra API (tradeos) con imágenes de la PokeAPI.

import { API_URL, paginaUrl } from './auth.js';
import { pokemonACarta, escapeHtml } from './utils.js';

document.addEventListener('DOMContentLoaded', cargarMasVendido);

async function cargarMasVendido() {
    const grid     = document.getElementById('grid-mas-vendido');
    const errorBox = document.getElementById('error-box');
    const errorMsg = document.getElementById('error-msg');

    // Mostramos skeletons mientras carga
    grid.innerHTML = Array(8).fill('<div class="carta-card skeleton" aria-hidden="true"></div>').join('');
    errorBox.hidden = true;

    try {
        // Pedimos los tradeos activos a nuestra API
        const res = await fetch(`${API_URL}/tradeos`);
        if (!res.ok) throw new Error(`HTTP_${res.status}`);
        const tradeos = await res.json();

        // Contamos cuántas veces aparece cada carta en los tradeos
        const conteo = {};
        for (const tradeo of tradeos) {
            for (const carta of [...(tradeo.cartas_ofrece || []), ...(tradeo.cartas_busca || [])]) {
                if (!conteo[carta.id]) {
                    conteo[carta.id] = { carta, veces: 0 };
                }
                conteo[carta.id].veces++;
            }
        }

        // Ordenamos de más a menos demandada
        const ranking = Object.values(conteo).sort((a, b) => b.veces - a.veces);

        if (!ranking.length) {
            // Si no hay tradeos, mostramos los 8 Pokémon más populares de la PokeAPI
            await cargarPopularesPokeAPI(grid);
            return;
        }

        // Para las cartas del ranking intentamos mejorar la imagen con la PokeAPI
        const cartasConImagen = await Promise.all(
            ranking.map(async (entry) => {
                if (entry.carta.imagen_url) return entry;
                try {
                    const numero = Number(entry.carta.numero) || entry.carta.id;
                    const res2 = await fetch(`https://pokeapi.co/api/v2/pokemon/${numero}`);
                    if (res2.ok) {
                        const poke = await res2.json();
                        entry.carta.imagen_url = poke.sprites?.other?.['official-artwork']?.front_default || null;
                    }
                } catch (_) {}
                return entry;
            })
        );

        grid.innerHTML = cartasConImagen.map((entry, i) => tarjetaRanking(entry, i + 1)).join('');

    } catch (e) {
        // Si falla nuestra API mostramos populares de la PokeAPI igualmente
        try {
            await cargarPopularesPokeAPI(grid);
        } catch (_) {
            grid.innerHTML = '';
            errorBox.hidden = false;
            errorMsg.textContent = 'Sin conexión. ¿Está activo el backend?';
        }
    }
}

// Fallback: muestra los Pokémon más icónicos cuando no hay tradeos
async function cargarPopularesPokeAPI(grid) {
    const populares = [6, 25, 150, 9, 3, 94, 149, 131];
    const promesas  = populares.map(id =>
        fetch(`https://pokeapi.co/api/v2/pokemon/${id}`).then(r => r.json())
    );
    const pokemons = await Promise.all(promesas);

    grid.innerHTML = pokemons.map((p, i) => {
        const carta = pokemonACarta(p);
        return tarjetaRanking({ carta, veces: null }, i + 1);
    }).join('');
}

// Tarjeta de carta con posición en el ranking. Toda la tarjeta es un
// enlace <a>: accesible con teclado de forma nativa.
function tarjetaRanking({ carta, veces }, posicion) {
    const medallas = { 1: 'oro', 2: 'plata', 3: 'bronce' };
    const medalla = medallas[posicion]
        ? `<img class="icono" src="${paginaUrl('img/icons/medalla-' + medallas[posicion] + '.svg')}" alt="" />`
        : `#${posicion}`;
    // Las cartas del backend llevan numero (nº de Pokédex); su id es el de la
    // BD y no coincide con el de la PokeAPI, que es lo que espera el detalle.
    const idDetalle = carta.numero ? Number(carta.numero) : carta.id;
    const url       = paginaUrl(`pages/detalle-carta.html?id=${idDetalle}`);
    const nombre    = escapeHtml(carta.nombre);
    const imgHTML = carta.imagen_url
        ? `<img src="${escapeHtml(carta.imagen_url)}" alt="${nombre}" loading="lazy" />`
        : `<div class="carta-sin-imagen" aria-hidden="true"><img class="icono" src="${paginaUrl('img/icons/carta.svg')}" alt="" /></div>`;
    const contadorHTML = veces !== null
        ? `<span class="mv-contador">${veces} ${veces === 1 ? 'tradeo' : 'tradeos'}</span>`
        : `<span class="mv-contador"><img class="icono" src="${paginaUrl('img/icons/estrella.svg')}" alt="" /> Popular</span>`;

    return `
    <a class="carta-card mv-card" href="${url}" aria-label="Ver detalle de ${nombre}">
        <div class="mv-posicion" aria-hidden="true">${medalla}</div>
        ${imgHTML}
        <div class="carta-info">
            <h3>${nombre}</h3>
            ${carta.tipo   ? `<span class="carta-tipo">${escapeHtml(carta.tipo)}</span>`   : ''}
            ${carta.rareza ? `<span class="carta-rareza">${escapeHtml(carta.rareza)}</span>` : ''}
            ${contadorHTML}
        </div>
    </a>`;
}

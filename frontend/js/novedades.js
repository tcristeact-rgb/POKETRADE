// novedades.js — Cartas más recientes del catálogo (módulo ES6)

import { API_URL } from './auth.js';
import { tarjetaCarta } from './utils.js';

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
        // Últimas cartas añadidas al catálogo, desde nuestra API
        const res = await fetch(`${API_URL}/cartas?orden=recientes&por_pagina=20`);
        if (!res.ok) throw new Error('Error al conectar con la API');
        const datos = await res.json();

        if (!datos.data.length) {
            grid.innerHTML = '<p class="grid-mensaje">No hay novedades disponibles.</p>';
            return;
        }

        grid.innerHTML = datos.data.map(c => tarjetaCarta(c)).join('');

    } catch (error) {
        grid.innerHTML = '';
        errorBox.hidden = false;
        errorMsg.textContent = 'No se pudieron cargar las novedades. Inténtalo más tarde.';
    }
}

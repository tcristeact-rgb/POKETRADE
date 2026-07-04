// inicio.js — Lógica de la página principal (módulo ES6)

import { API_URL, estaLogueado } from './auth.js';
import { tarjetaCarta } from './utils.js';

document.addEventListener('DOMContentLoaded', () => {
    const btnTradeo = document.querySelector('#btn-hero-tradeo');
    if (btnTradeo && !estaLogueado()) {
        btnTradeo.href = 'pages/login.html';
    }

    const ctaSeccion = document.getElementById('cta-seccion');
    if (ctaSeccion && estaLogueado()) {
        ctaSeccion.hidden = true;
    }

    cargarNovedades();
    cargarEstadisticas();

    const btnReintentar = document.getElementById('btn-reintentar-novedades');
    if (btnReintentar) btnReintentar.addEventListener('click', cargarNovedades);
});

// ─── Novedades: las 8 cartas más recientes del catálogo ────────────

async function cargarNovedades() {
    const grid     = document.getElementById('grid-novedades');
    const errorBox = document.getElementById('error-novedades');
    const errorMsg = document.getElementById('error-novedades-msg');

    if (!grid) return;

    grid.innerHTML = Array(4).fill('<div class="carta-card skeleton" aria-hidden="true"></div>').join('');
    errorBox.hidden = true;

    try {
        // Últimas cartas añadidas al catálogo, desde nuestra API
        const res = await fetch(`${API_URL}/cartas?orden=recientes&por_pagina=8`);
        if (!res.ok) throw new Error('Error al conectar con la API');
        const datos = await res.json();

        if (!datos.data.length) {
            grid.innerHTML = '<p class="grid-mensaje">Aún no hay cartas en el catálogo.</p>';
            return;
        }

        grid.innerHTML = datos.data.map(c => tarjetaCarta(c)).join('');

    } catch (error) {
        grid.innerHTML = '';
        errorBox.hidden = false;
        errorMsg.textContent = 'No se pudieron cargar las novedades. Inténtalo más tarde.';
    }
}

// ─── Estadísticas del hero ────────────────────────────

async function cargarEstadisticas() {
    try {
        // El total de cartas sale del paginador de nuestra API
        // (pedimos 1 sola carta: el campo total trae el recuento)
        const [resCartas, resTradeos] = await Promise.all([
            fetch(`${API_URL}/cartas?por_pagina=1`),
            fetch(`${API_URL}/tradeos`)
        ]);

        const datosCartas = resCartas.ok ? await resCartas.json() : null;
        const tradeos     = resTradeos.ok ? await resTradeos.json() : [];

        const totalCartas  = datosCartas?.total ?? 0;
        const totalTradeos = Array.isArray(tradeos) ? tradeos.length : 0;

        const statCartas  = document.querySelector('#stat-cartas  .stat-numero');
        const statTradeos = document.querySelector('#stat-tradeos .stat-numero');

        if (statCartas)  statCartas.textContent  = totalCartas.toLocaleString('es-ES');
        if (statTradeos) statTradeos.textContent = totalTradeos.toLocaleString('es-ES');

    } catch (_) {
        // Las estadísticas son decorativas
    } finally {
        document.querySelectorAll('.stat-item').forEach(el => el.classList.remove('skeleton'));
    }
}

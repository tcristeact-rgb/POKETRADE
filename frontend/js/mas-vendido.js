// mas-vendido.js — Ranking de cartas más demandadas en tradeos

import { API_URL, paginaUrl } from './auth.js';
import { t } from './i18n.js';
import { alCargarDOM, escapeHtml, dorsoCarta } from './utils.js';

alCargarDOM(() => {
    cargarMasVendido();
    document.getElementById('btn-reintentar-mv')?.addEventListener('click', cargarMasVendido);
});

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
            // Si no hay tradeos, mostramos las cartas más valiosas del catálogo
            await cargarDestacadas(grid);
            return;
        }

        // Las cartas del ranking vienen de nuestra API con su ilustración
        // TCGdex ya incluida (imagen_low/imagen_high)
        grid.innerHTML = ranking.map((entry, i) => tarjetaRanking(entry, i + 1)).join('');

    } catch (e) {
        // Si falla la carga de tradeos probamos con las destacadas
        try {
            await cargarDestacadas(grid);
        } catch (_) {
            grid.innerHTML = '';
            errorBox.hidden = false;
            errorMsg.textContent = t('mv.sinConexion');
        }
    }
}

// Fallback: muestra las 8 cartas con mayor precio de Cardmarket
// cuando todavía no hay tradeos publicados
async function cargarDestacadas(grid) {
    const res = await fetch(`${API_URL}/cartas?orden=precio&por_pagina=8`);
    if (!res.ok) throw new Error(t('comun.errorApi'));
    const datos = await res.json();

    grid.innerHTML = datos.data.map((carta, i) =>
        tarjetaRanking({ carta, veces: null }, i + 1)
    ).join('');
}

// Tarjeta de carta con posición en el ranking. Toda la tarjeta es un
// enlace <a>: accesible con teclado de forma nativa.
function tarjetaRanking({ carta, veces }, posicion) {
    const medallas = { 1: 'oro', 2: 'plata', 3: 'bronce' };
    const medalla = medallas[posicion]
        ? `<img class="icono" src="${paginaUrl('img/icons/medalla-' + medallas[posicion] + '.svg')}" alt="" />`
        : `#${posicion}`;
    const url    = paginaUrl(`pages/detalle-carta.html?id=${carta.id}`);
    const nombre = escapeHtml(carta.nombre);
    const imagen = carta.imagen_low || carta.imagen_url;
    const imgHTML = imagen
        ? `<img src="${escapeHtml(imagen)}" alt="${nombre}" loading="lazy" />`
        : dorsoCarta();
    const contadorHTML = veces !== null
        ? `<span class="mv-contador">${escapeHtml(t('mv.nTradeos', { n: veces }))}</span>`
        : `<span class="mv-contador"><img class="icono" src="${paginaUrl('img/icons/estrella.svg')}" alt="" /> ${escapeHtml(t('mv.popular'))}</span>`;

    return `
    <a class="carta-card mv-card" href="${url}" aria-label="${escapeHtml(t('comun.verDetalleDe', { nombre: carta.nombre }))}">
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

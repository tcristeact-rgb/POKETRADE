document.addEventListener('DOMContentLoaded', cargarMasVendido);

async function cargarMasVendido() {
    const grid     = document.getElementById('grid-mas-vendido');
    const errorBox = document.getElementById('error-box');
    const errorMsg = document.getElementById('error-msg');

    grid.innerHTML = Array(8).fill('<div class="carta-card skeleton" aria-hidden="true"></div>').join('');
    errorBox.hidden = true;

    try {
        const res = await fetch(`${API_URL}/tradeos`);
        if (!res.ok) throw new Error(`HTTP_${res.status}`);
        const tradeos = await res.json();

        const conteo = {};
        for (const tradeo of tradeos) {
            for (const carta of [...(tradeo.cartas_ofrece || []), ...(tradeo.cartas_busca || [])]) {
                if (!conteo[carta.id]) {
                    conteo[carta.id] = { carta, veces: 0 };
                }
                conteo[carta.id].veces++;
            }
        }

        const ranking = Object.values(conteo).sort((a, b) => b.veces - a.veces);

        if (!ranking.length) {
            grid.innerHTML = '<p class="vacio-msg" style="grid-column:1/-1;text-align:center;">No hay tradeos activos aún.</p>';
            return;
        }

        grid.innerHTML = ranking.map((entry, i) => tarjetaRanking(entry, i + 1)).join('');

    } catch (e) {
        grid.innerHTML = '';
        errorBox.hidden = false;
        errorMsg.textContent = e.message.includes('HTTP_')
            ? 'No se pudo conectar con el servidor.'
            : 'Sin conexión. ¿Está activo el backend?';
    }
}

function tarjetaRanking({ carta, veces }, posicion) {
    const medalla = posicion === 1 ? '🥇' : posicion === 2 ? '🥈' : posicion === 3 ? '🥉' : `#${posicion}`;
    const url     = paginaUrl(`pages/detalle-carta.html?id=${carta.id}`);
    const imgHTML = carta.imagen_url
        ? `<img src="${carta.imagen_url}" alt="${escapeHtml(carta.nombre)}" loading="lazy" />`
        : `<div class="carta-sin-imagen">🃏</div>`;

    return `
    <article class="carta-card mv-card" onclick="window.location.href='${url}'" style="cursor:pointer;" tabindex="0" role="button" aria-label="${escapeHtml(carta.nombre)}, ${veces} tradeos">
        <div class="mv-posicion">${medalla}</div>
        ${imgHTML}
        <div class="carta-info">
            <h3>${escapeHtml(carta.nombre)}</h3>
            ${carta.tipo   ? `<span class="carta-tipo">${escapeHtml(carta.tipo)}</span>`   : ''}
            ${carta.rareza ? `<span class="carta-rareza">${escapeHtml(carta.rareza)}</span>` : ''}
            <span class="mv-contador">${veces} ${veces === 1 ? 'tradeo' : 'tradeos'}</span>
        </div>
    </article>`;
}

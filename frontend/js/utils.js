// utils.js – Funciones compartidas entre todas las páginas
// Cargado después de auth.js (depende de paginaUrl)

function escapeHtml(str) {
    if (!str) return '';
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function formatearFecha(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' });
}

// Uso: mostrarAlerta(msg, tipo)              → muestra en #alerta
//      mostrarAlerta(msg, tipo, elementoId)  → muestra en #elementoId
function mostrarAlerta(msg, tipo, elementoId = 'alerta') {
    const el = document.getElementById(elementoId);
    if (!el) return;
    el.className = `alerta ${tipo}`;
    el.textContent = msg;
    setTimeout(() => { el.className = 'alerta'; el.textContent = ''; }, 4000);
}

// Tarjeta de carta compartida por catálogo, novedades, inicio y más vendidas
function tarjetaCarta(carta) {
    const nombre = carta.nombre || 'Sin nombre';
    const id     = carta.id || 0;
    const url    = paginaUrl(`pages/detalle-carta.html?id=${id}`);
    const imgHTML = carta.imagen_url
        ? `<img src="${carta.imagen_url}" alt="${escapeHtml(nombre)}" loading="lazy" />`
        : `<div class="carta-sin-imagen">🃏</div>`;
    return `
        <article class="carta-card" onclick="window.location.href='${url}'" style="cursor:pointer;">
            ${imgHTML}
            <div class="carta-info">
                <h3>${escapeHtml(nombre)}</h3>
                ${carta.tipo          ? `<span class="carta-tipo">${escapeHtml(carta.tipo)}</span>`         : ''}
                ${carta.rareza        ? `<span class="carta-rareza">${escapeHtml(carta.rareza)}</span>`     : ''}
                ${carta.set_expansion ? `<span class="carta-set">${escapeHtml(carta.set_expansion)}</span>` : ''}
                <button class="btn-ver-detalle" onclick="event.stopPropagation();window.location.href='${url}'">+ Info</button>
            </div>
        </article>`;
}

// Miniaturas de cartas para tarjetas de tradeo (marketplace y mis tradeos)
function miniaturas(cartas) {
    if (!cartas?.length) return '<span style="color:#aaa;font-size:0.8rem;">—</span>';
    return cartas.map(c => `
        <div class="miniatura">
            ${c.imagen_url
                ? `<img src="${c.imagen_url}" alt="${escapeHtml(c.nombre)}" loading="lazy" />`
                : `<div class="miniatura-sin-img">?</div>`}
            <span>${escapeHtml(c.nombre)}</span>
        </div>`
    ).join('');
}

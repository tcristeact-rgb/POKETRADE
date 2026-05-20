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
    const nombreSeguro = escapeHtml(nombre);
    const imgHTML = carta.imagen_url
        ? `<img src="${carta.imagen_url}" alt="${nombreSeguro}" loading="lazy" />`
        : `<div class="carta-sin-imagen" aria-hidden="true">🃏</div>`;
    return `
        <article class="carta-card" onclick="window.location.href='${url}'" style="cursor:pointer;">
            ${imgHTML}
            <div class="carta-info">
                <h3><a href="${url}">${nombreSeguro}</a></h3>
                ${carta.tipo          ? `<span class="carta-tipo">${escapeHtml(carta.tipo)}</span>`         : ''}
                ${carta.rareza        ? `<span class="carta-rareza">${escapeHtml(carta.rareza)}</span>`     : ''}
                ${carta.set_expansion ? `<span class="carta-set">${escapeHtml(carta.set_expansion)}</span>` : ''}
                <button class="btn-ver-detalle" onclick="event.stopPropagation();window.location.href='${url}'" aria-label="Ver detalle de ${nombreSeguro}">+ Info</button>
            </div>
        </article>`;
}

// ─── Accesibilidad: gestión de foco en ventanas modales ──
// Mueve el foco al modal, lo retiene dentro (Tab cíclico),
// cierra con Escape y devuelve el foco al elemento disparador.
// (WCAG 2.1.2 Sin trampas de teclado, 2.4.3 Orden de foco)
let _focoPrevioModal = null;
let _trampaFocoModal = null;

function abrirModalAccesible(modal, cerrarFn) {
    if (!modal) return;
    _focoPrevioModal = document.activeElement;

    const enfocables = () => Array.from(modal.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]), ' +
        'select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    )).filter(el => el.getClientRects().length > 0);

    const lista = enfocables();
    if (lista.length) lista[0].focus();

    _trampaFocoModal = function (e) {
        if (e.key === 'Escape') { e.preventDefault(); cerrarFn(); return; }
        if (e.key !== 'Tab') return;
        const f = enfocables();
        if (!f.length) return;
        const primero = f[0];
        const ultimo  = f[f.length - 1];
        if (e.shiftKey && document.activeElement === primero) {
            e.preventDefault(); ultimo.focus();
        } else if (!e.shiftKey && document.activeElement === ultimo) {
            e.preventDefault(); primero.focus();
        }
    };
    document.addEventListener('keydown', _trampaFocoModal);
}

function cerrarModalAccesible() {
    if (_trampaFocoModal) {
        document.removeEventListener('keydown', _trampaFocoModal);
        _trampaFocoModal = null;
    }
    if (_focoPrevioModal && typeof _focoPrevioModal.focus === 'function') {
        _focoPrevioModal.focus();
    }
    _focoPrevioModal = null;
}

// Miniaturas de cartas para tarjetas de tradeo (marketplace y mis tradeos)
function miniaturas(cartas) {
    if (!cartas?.length) return '<span style="color:#aaa;font-size:0.8rem;">—</span>';
    return cartas.map(c => `
        <div class="miniatura">
            ${c.imagen_url
                ? `<img src="${c.imagen_url}" alt="${escapeHtml(c.nombre)}" loading="lazy" />`
                : `<div class="miniatura-sin-img" aria-hidden="true">?</div>`}
            <span>${escapeHtml(c.nombre)}</span>
        </div>`
    ).join('');
}

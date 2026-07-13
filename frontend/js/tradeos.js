// tradeos.js — Gestión de los tradeos del usuario

import { apiFetch, protegerRuta, manejarErrorHTTP, parsearRespuesta } from './auth.js';
import { t } from './i18n.js';
import { alCargarDOM, formatearFecha, miniaturas, mostrarAlerta, escapeHtml } from './utils.js';

protegerRuta('tradeos');

let todosMisTradeos = [];

alCargarDOM(() => {
    cargarMisTradeos();

    // Filtros de estado
    document.querySelector('.filtro-estado')?.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-estado]');
        if (btn) filtrarPorEstado(btn.dataset.estado, btn);
    });

    // Acciones sobre cada tradeo
    document.getElementById('lista-tradeos')?.addEventListener('click', (e) => {
        const el = e.target.closest('[data-accion]');
        if (!el) return;
        const id = Number(el.dataset.tradeoId);
        if (el.dataset.accion === 'estado') cambiarEstado(id, el.dataset.estado);
        else if (el.dataset.accion === 'eliminar') eliminarTradeo(id);
    });
});

async function cargarMisTradeos() {
    const lista = document.getElementById('lista-tradeos');
    lista.innerHTML = Array(3)
        .fill('<div class="mistradeo-card skeleton" aria-hidden="true"></div>').join('');
    try {
        const res = await apiFetch(`/mis-tradeos`);
        if (!res.ok) throw new Error(manejarErrorHTTP(res.status));
        todosMisTradeos = await res.json();

        // Mantener el filtro activo tras recargar
        const activo = document.querySelector('.btn-filtro.activo')?.dataset.estado || 'todos';
        const filtrados = activo === 'todos'
            ? todosMisTradeos
            : todosMisTradeos.filter(tr => tr.estado === activo);
        renderizarTradeos(filtrados);
    } catch (e) {
        lista.innerHTML = `<p class="error-texto">${escapeHtml(t('tradeos.errorCargar', { mensaje: e.message }))}</p>`;
    }
}

function renderizarTradeos(tradeos) {
    const lista = document.getElementById('lista-tradeos');

    if (!tradeos.length) {
        lista.innerHTML = `
            <div class="vacio-msg">
                <p>${escapeHtml(t('tradeos.sinCategoria'))}</p>
                <a href="publicar-tradeo.html" class="btn-primario">${escapeHtml(t('tradeos.publicarPrimero'))}</a>
            </div>`;
        return;
    }

    lista.innerHTML = tradeos.map(tradeo => {
        const fecha = formatearFecha(tradeo.created_at);
        const badge = badgeEstado(tradeo.estado);

        const ofreceMinis = miniaturas(tradeo.cartas_ofrece);
        const buscaMinis  = miniaturas(tradeo.cartas_busca);

        const botonesAccion = tradeo.estado === 'activo' ? `
            <button class="btn-accion btn-cerrar" type="button" data-accion="estado" data-tradeo-id="${tradeo.id}" data-estado="cerrado">${escapeHtml(t('tradeos.marcarCerrado'))}</button>
            <button class="btn-accion btn-cancelar" type="button" data-accion="estado" data-tradeo-id="${tradeo.id}" data-estado="cancelado">${escapeHtml(t('comun.cancelar'))}</button>
            <button class="btn-accion btn-eliminar-tradeo" type="button" data-accion="eliminar" data-tradeo-id="${tradeo.id}">${escapeHtml(t('comun.eliminar'))}</button>
        ` : `
            <button class="btn-accion btn-eliminar-tradeo" type="button" data-accion="eliminar" data-tradeo-id="${tradeo.id}">${escapeHtml(t('comun.eliminar'))}</button>
        `;

        return `
        <div class="mistradeo-card" id="tradeo-${tradeo.id}">
            <div class="mistradeo-card-header">
                <span class="mistradeo-fecha">${fecha}</span>
                ${badge}
            </div>
            <div class="tradeo-cartas">
                <div class="tradeo-grupo">
                    <h4>${escapeHtml(t('tradeos.ofrezco'))}</h4>
                    <div class="cartas-miniaturas">${ofreceMinis}</div>
                </div>
                <div class="tradeo-grupo">
                    <h4>${escapeHtml(t('tradeos.busco'))}</h4>
                    <div class="cartas-miniaturas">${buscaMinis}</div>
                </div>
            </div>
            ${tradeo.descripcion ? `<p class="mistradeo-descripcion">"${escapeHtml(tradeo.descripcion)}"</p>` : ''}
            <div class="tradeo-acciones">${botonesAccion}</div>
        </div>`;
    }).join('');
}

function badgeEstado(estado) {
    const clases = { activo: 'badge-activo', cerrado: 'badge-cerrado', cancelado: 'badge-cancelado' };
    const etiqueta = clases[estado] ? t(`estado.${estado}`) : estado;
    return `<span class="badge-estado ${clases[estado] || ''}">${escapeHtml(etiqueta)}</span>`;
}

function filtrarPorEstado(estado, boton) {
    document.querySelectorAll('.btn-filtro').forEach(b => b.classList.remove('activo'));
    boton.classList.add('activo');

    const filtrados = estado === 'todos'
        ? todosMisTradeos
        : todosMisTradeos.filter(tr => tr.estado === estado);

    renderizarTradeos(filtrados);
}

async function cambiarEstado(id, nuevoEstado) {
    // Cada confirmación es una frase entera en el diccionario. Antes se
    // montaba insertando el verbo suelto ("cerrar"/"cancelar") en una
    // plantilla: eso solo funciona en español, y ni siquiera siempre.
    const CONFIRMACIONES = {
        cerrado:   'tradeos.confirmarCerrar',
        cancelado: 'tradeos.confirmarCancelar',
    };
    const clave = CONFIRMACIONES[nuevoEstado];
    if (!clave || !confirm(t(clave))) return;

    try {
        const res = await apiFetch(`/tradeos/${id}`, {
            method: 'PUT',
            body: JSON.stringify({ estado: nuevoEstado })
        });
        const datos = await parsearRespuesta(res);
        if (!res.ok) throw new Error(datos.error || manejarErrorHTTP(res.status));
        mostrarAlerta(t('tradeos.estadoActualizado'), 'exito');
        cargarMisTradeos();
    } catch (e) {
        mostrarAlerta(t('comun.error', { mensaje: e.message }), 'error');
    }
}

async function eliminarTradeo(id) {
    if (!confirm(t('tradeos.confirmarEliminar'))) return;

    try {
        const res = await apiFetch(`/tradeos/${id}`, {
            method: 'DELETE',
        });
        if (!res.ok) throw new Error(manejarErrorHTTP(res.status));
        mostrarAlerta(t('tradeos.eliminado'), 'exito');
        cargarMisTradeos();
    } catch (e) {
        mostrarAlerta(t('comun.error', { mensaje: e.message }), 'error');
    }
}

// inventario.js — Gestión del inventario del usuario (módulo ES6)

import { API_URL, headersAuth, protegerRuta, manejarErrorHTTP, parsearRespuesta } from './auth.js';
import { escapeHtml, mostrarAlerta, abrirModalAccesible, cerrarModalAccesible,
         capitalizarNombre, pokemonACarta } from './utils.js';

protegerRuta();

const MAX_MODAL_VISIBLE = 60;   // Pokémon mostrados a la vez en el modal

let todasLasCartasCatalogo = [];   // catálogo completo de la PokeAPI (datos ligeros)
let cartaSeleccionadaId    = null;
let cantidadSeleccionada   = 1;

document.addEventListener('DOMContentLoaded', () => {
    cargarInventario();
    cargarCatalogoPModal();

    // Botones estáticos (sin manejadores en línea)
    document.getElementById('btn-abrir-modal')?.addEventListener('click', abrirModal);
    document.getElementById('btn-cerrar-modal-inv')?.addEventListener('click', cerrarModal);
    document.getElementById('modal-buscar')?.addEventListener('input', filtrarModal);
    document.getElementById('btn-cantidad-menos')?.addEventListener('click', () => cambiarCantidad(-1));
    document.getElementById('btn-cantidad-mas')?.addEventListener('click', () => cambiarCantidad(1));
    document.getElementById('btn-confirmar-anadir')?.addEventListener('click', confirmarAnadir);

    // Cerrar el modal al hacer clic fuera de la caja
    document.getElementById('modal-overlay')?.addEventListener('click', (e) => {
        if (e.target === e.currentTarget) cerrarModal();
    });

    // Delegación: acciones sobre las cartas del inventario
    document.getElementById('grid-inventario')?.addEventListener('click', (e) => {
        const el = e.target.closest('[data-accion]');
        if (!el) return;
        if (el.dataset.accion === 'eliminar') eliminarItem(Number(el.dataset.itemId));
        else if (el.dataset.accion === 'abrir-modal') abrirModal();
    });

    // Delegación: selección de carta en el modal (ratón y teclado)
    const lista = document.getElementById('lista-cartas-modal');
    lista?.addEventListener('click', (e) => {
        const card = e.target.closest('[data-carta-id]');
        if (card) seleccionarCarta(Number(card.dataset.cartaId), card);
    });
    lista?.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        const card = e.target.closest('[data-carta-id]');
        if (card) { e.preventDefault(); seleccionarCarta(Number(card.dataset.cartaId), card); }
    });
});

// ─── Inventario ───────────────────────────────────────

async function cargarInventario() {
    const grid = document.getElementById('grid-inventario');
    try {
        const res = await fetch(`${API_URL}/inventario`, { headers: headersAuth() });
        if (!res.ok) throw new Error(manejarErrorHTTP(res.status));
        const items = await res.json();
        renderizarInventario(items);
    } catch (e) {
        grid.innerHTML = `<p class="error-texto">Error al cargar el inventario: ${e.message}</p>`;
    }
}

function renderizarInventario(items) {
    const grid = document.getElementById('grid-inventario');

    if (!items.length) {
        grid.innerHTML = `
            <div class="vacio-msg">
                <p>Tu inventario está vacío.</p>
                <button class="btn-primario" type="button" data-accion="abrir-modal">Añadir primera carta</button>
            </div>`;
        return;
    }

    grid.innerHTML = items.map(item => {
        const nombre = item.carta?.nombre || 'Carta';
        return `
        <div class="carta-inventario">
            <span class="badge-cantidad">${item.cantidad}</span>
            ${item.carta?.imagen_url
                ? `<img src="${escapeHtml(item.carta.imagen_url)}" alt="${escapeHtml(nombre)}" />`
                : `<div class="carta-sin-imagen" aria-hidden="true">?</div>`}
            <h3>${escapeHtml(nombre)}</h3>
            <span class="carta-tipo">${escapeHtml(item.carta?.tipo || '—')}</span>
            <span class="carta-rareza">${escapeHtml(item.carta?.rareza || '')}</span>
            <button class="btn-eliminar" type="button" data-accion="eliminar" data-item-id="${item.id}"
                    aria-label="Eliminar ${escapeHtml(nombre)} del inventario">Eliminar</button>
        </div>`;
    }).join('');
}

async function eliminarItem(id) {
    if (!confirm('¿Eliminar esta carta del inventario?')) return;
    try {
        const res = await fetch(`${API_URL}/inventario/${id}`, {
            method: 'DELETE',
            headers: headersAuth()
        });
        if (!res.ok) throw new Error(manejarErrorHTTP(res.status));
        mostrarAlerta('Carta eliminada del inventario.', 'exito');
        cargarInventario();
    } catch (e) {
        mostrarAlerta(`Error: ${e.message}`, 'error');
    }
}

// ─── Modal: catálogo ──────────────────────────────────

async function cargarCatalogoPModal() {
    try {
        // El catálogo son los Pokémon de la PokeAPI: una sola petición ligera
        // (nombre y URL). La imagen se deriva del ID, sin pedir cada detalle.
        const res = await fetch('https://pokeapi.co/api/v2/pokemon?limit=10000');
        if (!res.ok) throw new Error('Error al conectar con la PokeAPI');
        const datos = await res.json();

        todasLasCartasCatalogo = datos.results
            .map(p => {
                const id = Number(p.url.split('/').filter(Boolean).pop());
                return {
                    id,
                    nombre:     capitalizarNombre(p.name),
                    imagen_url: `https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/${id}.png`,
                };
            })
            .filter(c => c.id <= 1010);   // excluye formas especiales

        renderizarModal(todasLasCartasCatalogo.slice(0, MAX_MODAL_VISIBLE));
    } catch (e) {
        document.getElementById('lista-cartas-modal').innerHTML =
            `<p class="error-texto">No se pudo cargar el catálogo: ${e.message}</p>`;
    }
}

function renderizarModal(cartas) {
    const lista = document.getElementById('lista-cartas-modal');

    if (!cartas.length) {
        lista.innerHTML = '<p>No se encontraron cartas.</p>';
        return;
    }

    lista.innerHTML = cartas.map(carta => `
        <div class="carta-seleccionable" role="button" tabindex="0"
             data-carta-id="${carta.id}"
             aria-label="Seleccionar ${escapeHtml(carta.nombre)}">
            ${carta.imagen_url
                ? `<img src="${escapeHtml(carta.imagen_url)}" alt="${escapeHtml(carta.nombre)}" />`
                : `<div class="placeholder-img" aria-hidden="true">?</div>`}
            <p>${escapeHtml(carta.nombre)}</p>
        </div>
    `).join('');
}

function filtrarModal() {
    const texto  = document.getElementById('modal-buscar').value.toLowerCase().trim();
    const fuente = texto
        ? todasLasCartasCatalogo.filter(c => c.nombre.toLowerCase().includes(texto))
        : todasLasCartasCatalogo;
    // Con >1000 Pokémon limitamos los visibles; el buscador acota el resto.
    renderizarModal(fuente.slice(0, MAX_MODAL_VISIBLE));
}

function seleccionarCarta(id, el) {
    const carta = todasLasCartasCatalogo.find(c => c.id === id);
    cartaSeleccionadaId  = id;
    cantidadSeleccionada = 1;
    document.getElementById('nombre-seleccionada').textContent = carta?.nombre || `Carta #${id}`;
    document.getElementById('cantidad-valor').textContent = 1;
    document.getElementById('seleccion-panel').hidden = false;

    document.querySelectorAll('.carta-seleccionable').forEach(c => c.classList.remove('seleccionada'));
    if (el) el.classList.add('seleccionada');
}

function cambiarCantidad(delta) {
    cantidadSeleccionada = Math.max(1, Math.min(99, cantidadSeleccionada + delta));
    document.getElementById('cantidad-valor').textContent = cantidadSeleccionada;
}

async function confirmarAnadir() {
    if (!cartaSeleccionadaId) return;
    try {
        // Pedimos los datos completos del Pokémon a la PokeAPI. El backend
        // crea la carta si aún no existe en el catálogo (firstOrCreate por numero).
        const resPoke = await fetch(`https://pokeapi.co/api/v2/pokemon/${cartaSeleccionadaId}`);
        if (!resPoke.ok) throw new Error('No se pudieron cargar los datos de la carta.');
        const carta = pokemonACarta(await resPoke.json());

        const res = await fetch(`${API_URL}/inventario`, {
            method: 'POST',
            headers: headersAuth(),
            body: JSON.stringify({
                carta: {
                    nombre:        carta.nombre,
                    numero:        String(carta.id).padStart(3, '0'),
                    tipo:          carta.tipo,
                    rareza:        carta.rareza,
                    set_expansion: carta.set_expansion,
                    imagen_url:    carta.imagen_url,
                },
                cantidad: cantidadSeleccionada
            })
        });
        const datos = await parsearRespuesta(res);
        if (!res.ok) throw new Error(datos.error || manejarErrorHTTP(res.status));
        cerrarModal();
        mostrarAlerta('Carta añadida al inventario.', 'exito');
        cargarInventario();
    } catch (e) {
        mostrarAlerta(`Error: ${e.message}`, 'error');
    }
}

function abrirModal() {
    const overlay = document.getElementById('modal-overlay');
    overlay.hidden = false;
    document.getElementById('seleccion-panel').hidden = true;
    cartaSeleccionadaId = null;
    // Accesibilidad: foco al modal, retención de Tab y cierre con Escape
    abrirModalAccesible(overlay, cerrarModal);
}

function cerrarModal() {
    document.getElementById('modal-overlay').hidden = true;
    document.getElementById('modal-buscar').value = '';
    renderizarModal(todasLasCartasCatalogo.slice(0, MAX_MODAL_VISIBLE));
    // Accesibilidad: devuelve el foco al botón que abrió el modal
    cerrarModalAccesible();
}

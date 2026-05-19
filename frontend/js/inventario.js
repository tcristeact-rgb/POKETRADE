protegerRuta();

let todasLasCartasCatalogo = [];
let cartaSeleccionadaId = null;
let cantidadSeleccionada = 1;

document.addEventListener('DOMContentLoaded', () => {
    cargarInventario();
    cargarCatalogoPModal();
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
            <div class="vacio-msg" style="grid-column:1/-1">
                <p>Tu inventario está vacío.</p>
                <button class="btn-primario" onclick="abrirModal()">Añadir primera carta</button>
            </div>`;
        return;
    }

    grid.innerHTML = items.map(item => `
        <div class="carta-inventario">
            <span class="badge-cantidad">${item.cantidad}</span>
            ${item.carta?.imagen_url
                ? `<img src="${item.carta.imagen_url}" alt="${item.carta.nombre}" />`
                : `<div class="carta-sin-imagen">?</div>`}
            <h3>${item.carta?.nombre || 'Carta'}</h3>
            <span class="carta-tipo">${item.carta?.tipo || '—'}</span>
            <span class="carta-rareza">${item.carta?.rareza || ''}</span>
            <button class="btn-eliminar" onclick="eliminarItem(${item.id})">Eliminar</button>
        </div>
    `).join('');
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
        const res = await fetch(`${API_URL}/cartas`);
        const cartas = await res.json();
        todasLasCartasCatalogo = cartas;
        renderizarModal(cartas);
    } catch (_) {
        document.getElementById('lista-cartas-modal').innerHTML =
            '<p class="error-texto">No se pudo cargar el catálogo.</p>';
    }
}

function renderizarModal(cartas) {
    const lista = document.getElementById('lista-cartas-modal');

    if (!cartas.length) {
        lista.innerHTML = '<p>No hay cartas disponibles.</p>';
        return;
    }

    lista.innerHTML = cartas.map(carta => `
        <div class="carta-seleccionable" style="padding:0.75rem;" onclick="seleccionarCarta(${carta.id}, '${carta.nombre.replace(/'/g, "\\'")}')">
            ${carta.imagen_url
                ? `<img src="${carta.imagen_url}" alt="${carta.nombre}" style="width:80px;height:80px;object-fit:contain;" />`
                : `<div style="width:80px;height:80px;background:#f0f0f0;border-radius:6px;margin:0 auto;display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#ccc;">?</div>`}
            <p>${carta.nombre}</p>
        </div>
    `).join('');
}

function filtrarModal() {
    const texto = document.getElementById('modal-buscar').value.toLowerCase();
    const filtradas = todasLasCartasCatalogo.filter(c => c.nombre.toLowerCase().includes(texto));
    renderizarModal(filtradas);
}

function seleccionarCarta(id, nombre) {
    cartaSeleccionadaId = id;
    cantidadSeleccionada = 1;
    document.getElementById('nombre-seleccionada').textContent = nombre;
    document.getElementById('cantidad-valor').textContent = 1;
    document.getElementById('seleccion-panel').style.display = 'block';

    document.querySelectorAll('.carta-seleccionable').forEach(el => el.classList.remove('seleccionada'));
    event.currentTarget.classList.add('seleccionada');
}

function cambiarCantidad(delta) {
    cantidadSeleccionada = Math.max(1, Math.min(99, cantidadSeleccionada + delta));
    document.getElementById('cantidad-valor').textContent = cantidadSeleccionada;
}

async function confirmarAnadir() {
    if (!cartaSeleccionadaId) return;
    try {
        const res = await fetch(`${API_URL}/inventario`, {
            method: 'POST',
            headers: headersAuth(),
            body: JSON.stringify({ carta_id: cartaSeleccionadaId, cantidad: cantidadSeleccionada })
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
    document.getElementById('modal-overlay').classList.add('activo');
    document.getElementById('seleccion-panel').style.display = 'none';
    cartaSeleccionadaId = null;
}

function cerrarModal() {
    document.getElementById('modal-overlay').classList.remove('activo');
    document.getElementById('modal-buscar').value = '';
    renderizarModal(todasLasCartasCatalogo);
}

document.getElementById('modal-overlay').addEventListener('click', e => {
    if (e.target === e.currentTarget) cerrarModal();
});

protegerRuta();

let inventario = [];
let catalogoCartas = [];
let idsOfrece = new Set();
let idsBusca  = new Set();

document.addEventListener('DOMContentLoaded', () => {
    cargarInventarioOfrece();
    cargarCatalogoBusca();
});

// ─── Cargar datos ─────────────────────────────────────

async function cargarInventarioOfrece() {
    const grid = document.getElementById('grid-ofrece');
    try {
        const res = await fetch(`${API_URL}/inventario`, { headers: headersAuth() });
        if (!res.ok) throw new Error(manejarErrorHTTP(res.status));
        inventario = await res.json();
        renderizarOfrece(inventario);
    } catch (e) {
        grid.innerHTML = `<p class="vacio-seccion error-texto">Error al cargar inventario: ${e.message}</p>`;
    }
}

async function cargarCatalogoBusca() {
    const grid = document.getElementById('grid-busca');
    try {
        const res = await fetch(`${API_URL}/cartas`);
        if (!res.ok) throw new Error(manejarErrorHTTP(res.status));
        catalogoCartas = await res.json();
        renderizarBusca(catalogoCartas);
    } catch (e) {
        grid.innerHTML = `<p class="vacio-seccion error-texto">Error al cargar catálogo: ${e.message}</p>`;
    }
}

// ─── Renderizado ──────────────────────────────────────

function renderizarOfrece(items) {
    const grid = document.getElementById('grid-ofrece');

    if (!items.length) {
        grid.innerHTML = `<p class="vacio-seccion">Tu inventario está vacío. <a href="inventario.html">Añade cartas primero</a>.</p>`;
        return;
    }

    grid.innerHTML = items.map(item => {
        const carta = item.carta;
        return `
        <div class="carta-seleccionable ${idsOfrece.has(carta.id) ? 'seleccionada' : ''}"
             style="padding:0.6rem;"
             onclick="toggleOfrece(${carta.id}, '${carta.nombre.replace(/'/g, "\\'")}', this)">
            ${carta.imagen_url
                ? `<img src="${carta.imagen_url}" alt="${carta.nombre}" style="width:70px;height:70px;object-fit:contain;" />`
                : `<div style="width:70px;height:70px;background:#f0f0f0;border-radius:6px;margin:0 auto;display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#ccc;">?</div>`}
            <p>${carta.nombre}</p>
            <small>x${item.cantidad}</small>
        </div>`;
    }).join('');
}

function renderizarBusca(cartas) {
    const grid = document.getElementById('grid-busca');

    if (!cartas.length) {
        grid.innerHTML = `<p class="vacio-seccion">No hay cartas disponibles.</p>`;
        return;
    }

    grid.innerHTML = cartas.map(carta => `
        <div class="carta-seleccionable ${idsBusca.has(carta.id) ? 'seleccionada' : ''}"
             style="padding:0.6rem;"
             onclick="toggleBusca(${carta.id}, '${carta.nombre.replace(/'/g, "\\'")}', this)">
            ${carta.imagen_url
                ? `<img src="${carta.imagen_url}" alt="${carta.nombre}" style="width:70px;height:70px;object-fit:contain;" />`
                : `<div style="width:70px;height:70px;background:#f0f0f0;border-radius:6px;margin:0 auto;display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#ccc;">?</div>`}
            <p>${carta.nombre}</p>
            <small>${carta.rareza || ''}</small>
        </div>
    `).join('');
}

// ─── Selección ────────────────────────────────────────

function toggleOfrece(id, nombre, el) {
    if (idsOfrece.has(id)) {
        idsOfrece.delete(id);
        el.classList.remove('seleccionada');
    } else {
        idsOfrece.add(id);
        el.classList.add('seleccionada');
    }
    actualizarPreview('preview-ofrece', idsOfrece, catalogoCartas.concat(inventario.map(i => i.carta)));
}

function toggleBusca(id, nombre, el) {
    if (idsBusca.has(id)) {
        idsBusca.delete(id);
        el.classList.remove('seleccionada');
    } else {
        idsBusca.add(id);
        el.classList.add('seleccionada');
    }
    actualizarPreview('preview-busca', idsBusca, catalogoCartas);
}

function actualizarPreview(contenedorId, ids, fuente) {
    const preview = document.getElementById(contenedorId);
    if (!ids.size) {
        preview.innerHTML = '<span style="color:#aaa; font-size:0.85rem;">Ninguna seleccionada</span>';
        return;
    }
    preview.innerHTML = [...ids].map(id => {
        const carta = fuente.find(c => c.id === id);
        const nombre = carta?.nombre || `#${id}`;
        return `<span class="chip-carta">${nombre}<button onclick="quitarSeleccion(${id}, '${contenedorId}')">×</button></span>`;
    }).join('');
}

function quitarSeleccion(id, contenedorId) {
    if (contenedorId === 'preview-ofrece') {
        idsOfrece.delete(id);
        actualizarPreview('preview-ofrece', idsOfrece, catalogoCartas.concat(inventario.map(i => i.carta)));
        renderizarOfrece(inventario);
    } else {
        idsBusca.delete(id);
        actualizarPreview('preview-busca', idsBusca, catalogoCartas);
        renderizarBusca(filtrarBuscaActual());
    }
}

function filtrarBuscaActual() {
    const texto = document.getElementById('buscar-busca').value.toLowerCase();
    return catalogoCartas.filter(c => c.nombre.toLowerCase().includes(texto));
}

function filtrarBusca() {
    renderizarBusca(filtrarBuscaActual());
}

function actualizarContador() {
    const val = document.getElementById('descripcion').value.length;
    document.getElementById('contador-chars').textContent = val;
}

// ─── Publicar ─────────────────────────────────────────

async function publicarTradeo() {
    if (!idsOfrece.size) {
        mostrarAlerta('Selecciona al menos una carta que ofrecer.', 'error');
        return;
    }
    if (!idsBusca.size) {
        mostrarAlerta('Selecciona al menos una carta que buscar.', 'error');
        return;
    }

    const payload = {
        cartas_ofrece: [...idsOfrece],
        cartas_busca:  [...idsBusca],
        descripcion:   document.getElementById('descripcion').value.trim() || null,
    };

    try {
        const res = await fetch(`${API_URL}/tradeos`, {
            method: 'POST',
            headers: headersAuth(),
            body: JSON.stringify(payload)
        });
        const datos = await parsearRespuesta(res);
        if (!res.ok) throw new Error(datos.error || manejarErrorHTTP(res.status));

        // Quitar del inventario las cartas que el creador ofrece
        const itemsAEliminar = inventario.filter(item =>
            idsOfrece.has(item.carta?.id ?? item.carta_id)
        );
        for (const item of itemsAEliminar) {
            await fetch(`${API_URL}/inventario/${item.id}`, {
                method: 'DELETE',
                headers: headersAuth()
            });
        }

        mostrarAlerta('¡Tradeo publicado correctamente!', 'exito');
        setTimeout(() => { window.location.href = 'tradeos.html'; }, 1500);
    } catch (e) {
        mostrarAlerta(`Error al publicar: ${e.message}`, 'error');
    }
}

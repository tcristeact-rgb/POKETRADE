document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    const id = params.get('id');

    if (!id) {
        mostrarError('No se especificó ninguna carta.');
        return;
    }

    cargarDetalle(id);
});

async function cargarDetalle(id) {
    try {
        const res = await fetch(`${API_URL}/cartas/${id}`);
        if (res.status === 404) { mostrarError('Esta carta no existe.'); return; }
        if (!res.ok) throw new Error(manejarErrorHTTP(res.status));
        const carta = await res.json();
        renderizarDetalle(carta);
    } catch (e) {
        mostrarError(`Error al cargar la carta: ${e.message}`);
    }
}

function renderizarDetalle(carta) {
    document.title = `${carta.nombre} - PokeTrade`;
    document.getElementById('breadcrumb-nombre').textContent = carta.nombre;

    const botonInventario = estaLogueado()
        ? `<button class="btn-inventario" onclick="anadirAInventario(${carta.id})">+ Añadir a mi inventario</button>`
        : `<a href="login.html" class="btn-primario">Inicia sesión para añadir</a>`;

    document.getElementById('contenido-detalle').innerHTML = `
        <div class="detalle-card">
            <div class="detalle-imagen">
                ${carta.imagen_url
                    ? `<img src="${carta.imagen_url}" alt="${carta.nombre}" />`
                    : `<div class="sin-imagen-grande">?</div>`}
            </div>
            <div class="detalle-info">
                <h1>${carta.nombre}</h1>
                <div class="atributos">
                    ${carta.tipo ? `
                    <div class="atributo-fila">
                        <span class="atributo-label">Tipo</span>
                        <span class="badge-tipo">${carta.tipo}</span>
                    </div>` : ''}
                    ${carta.rareza ? `
                    <div class="atributo-fila">
                        <span class="atributo-label">Rareza</span>
                        <span class="badge-rareza">${carta.rareza}</span>
                    </div>` : ''}
                    ${carta.set_expansion ? `
                    <div class="atributo-fila">
                        <span class="atributo-label">Expansión</span>
                        <span>${carta.set_expansion}</span>
                    </div>` : ''}
                    ${carta.numero ? `
                    <div class="atributo-fila">
                        <span class="atributo-label">Número</span>
                        <span>#${carta.numero}</span>
                    </div>` : ''}
                </div>
                ${carta.descripcion ? `<p class="descripcion-carta">${carta.descripcion}</p>` : ''}
                <div class="acciones-carta">
                    ${botonInventario}
                    <a href="catalogo.html" class="btn-secundario" style="padding:0.65rem 1.25rem;">← Catálogo</a>
                </div>
            </div>
        </div>
    `;
}

async function anadirAInventario(cartaId) {
    const btn = document.querySelector('.btn-inventario');
    btn.disabled = true;
    btn.textContent = 'Añadiendo...';

    try {
        const res = await fetch(`${API_URL}/inventario`, {
            method: 'POST',
            headers: headersAuth(),
            body: JSON.stringify({ carta_id: cartaId, cantidad: 1 })
        });
        const datos = await parsearRespuesta(res);
        if (!res.ok) throw new Error(datos.error || manejarErrorHTTP(res.status));
        mostrarAlerta('Carta añadida al inventario.', 'exito');
        btn.textContent = '✓ En inventario';
    } catch (e) {
        mostrarAlerta(`Error: ${e.message}`, 'error');
        btn.disabled = false;
        btn.textContent = '+ Añadir a mi inventario';
    }
}

function mostrarError(msg) {
    document.getElementById('contenido-detalle').innerHTML =
        `<p class="error-texto" style="text-align:center;padding:3rem;">${msg}</p>`;
}

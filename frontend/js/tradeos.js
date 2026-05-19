protegerRuta();

let todosMisTradeos = [];
let filtroActual = 'todos';

document.addEventListener('DOMContentLoaded', cargarMisTradeos);

async function cargarMisTradeos() {
    const lista = document.getElementById('lista-tradeos');
    lista.innerHTML = '<p>Cargando tradeos...</p>';
    try {
        const res = await fetch(`${API_URL}/mis-tradeos`, { headers: headersAuth() });
        if (!res.ok) throw new Error(manejarErrorHTTP(res.status));
        const datos = await res.json();
        todosMisTradeos = datos;
        renderizarTradeos(datos);
    } catch (e) {
        lista.innerHTML = `<p class="error-texto">Error al cargar tus tradeos: ${e.message}</p>`;
    }
}

function renderizarTradeos(tradeos) {
    const lista = document.getElementById('lista-tradeos');

    if (!tradeos.length) {
        lista.innerHTML = `
            <div class="vacio-msg">
                <p>No tienes tradeos en esta categoría.</p>
                <a href="publicar-tradeo.html" class="btn-primario">Publicar primer tradeo</a>
            </div>`;
        return;
    }

    lista.innerHTML = tradeos.map(t => {
        const fecha = formatearFecha(t.created_at);
        const badge = badgeEstado(t.estado);

        const ofreceMinis = miniaturas(t.cartas_ofrece);
        const buscaMinis  = miniaturas(t.cartas_busca);

        const botonesAccion = t.estado === 'activo' ? `
            <button class="btn-accion btn-cerrar"   onclick="cambiarEstado(${t.id}, 'cerrado')">Marcar cerrado</button>
            <button class="btn-accion btn-cancelar" onclick="cambiarEstado(${t.id}, 'cancelado')">Cancelar</button>
            <button class="btn-accion btn-eliminar-tradeo" onclick="eliminarTradeo(${t.id})">Eliminar</button>
        ` : `
            <button class="btn-accion btn-eliminar-tradeo" onclick="eliminarTradeo(${t.id})">Eliminar</button>
        `;

        return `
        <div class="tradeo-card" id="tradeo-${t.id}">
            <div class="tradeo-card-header">
                <span class="tradeo-fecha">${fecha}</span>
                ${badge}
            </div>
            <div class="tradeo-cartas">
                <div class="tradeo-grupo">
                    <h4>Ofrezco</h4>
                    <div class="cartas-miniaturas">${ofreceMinis}</div>
                </div>
                <div class="tradeo-grupo">
                    <h4>Busco</h4>
                    <div class="cartas-miniaturas">${buscaMinis}</div>
                </div>
            </div>
            ${t.descripcion ? `<p class="tradeo-descripcion">"${t.descripcion}"</p>` : ''}
            <div class="tradeo-acciones">${botonesAccion}</div>
        </div>`;
    }).join('');
}

function badgeEstado(estado) {
    const clases   = { activo: 'badge-activo', cerrado: 'badge-cerrado', cancelado: 'badge-cancelado' };
    const etiquetas = { activo: 'Activo', cerrado: 'Cerrado', cancelado: 'Cancelado' };
    return `<span class="badge-estado ${clases[estado] || ''}">${etiquetas[estado] || estado}</span>`;
}

function filtrarPorEstado(estado, boton) {
    filtroActual = estado;
    document.querySelectorAll('.btn-filtro').forEach(b => b.classList.remove('activo'));
    boton.classList.add('activo');

    const filtrados = estado === 'todos'
        ? todosMisTradeos
        : todosMisTradeos.filter(t => t.estado === estado);

    renderizarTradeos(filtrados);
}

async function cambiarEstado(id, nuevoEstado) {
    const labels = { cerrado: 'cerrar', cancelado: 'cancelar' };
    if (!confirm(`¿Quieres ${labels[nuevoEstado]} este tradeo?`)) return;

    try {
        const res = await fetch(`${API_URL}/tradeos/${id}`, {
            method: 'PUT',
            headers: headersAuth(),
            body: JSON.stringify({ estado: nuevoEstado })
        });
        const datos = await parsearRespuesta(res);
        if (!res.ok) throw new Error(datos.error || manejarErrorHTTP(res.status));
        mostrarAlerta('Estado actualizado.', 'exito');
        cargarMisTradeos();
    } catch (e) {
        mostrarAlerta(`Error: ${e.message}`, 'error');
    }
}

async function eliminarTradeo(id) {
    if (!confirm('¿Eliminar este tradeo permanentemente?')) return;

    try {
        const res = await fetch(`${API_URL}/tradeos/${id}`, {
            method: 'DELETE',
            headers: headersAuth()
        });
        if (!res.ok) throw new Error(manejarErrorHTTP(res.status));
        mostrarAlerta('Tradeo eliminado.', 'exito');
        cargarMisTradeos();
    } catch (e) {
        mostrarAlerta(`Error: ${e.message}`, 'error');
    }
}

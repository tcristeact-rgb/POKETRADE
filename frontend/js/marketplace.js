let todosLosTradeos  = [];
let inventarioUsuario = [];
let tradeoEnCurso     = null;

document.addEventListener('DOMContentLoaded', () => {
    mostrarCtaPublicar();
    cargarTradeos();
});

// ─── CTA publicar ──────────────────────────────────────

function mostrarCtaPublicar() {
    if (estaLogueado()) {
        document.getElementById('cta-publicar').hidden = false;
    }
}

// ─── Carga de tradeos ──────────────────────────────────

async function cargarTradeos() {
    ocultarError();
    try {
        const res = await fetch(`${API_URL}/tradeos`);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        todosLosTradeos = await res.json();
        filtrar();
    } catch (e) {
        mostrarError('No se pudieron cargar los tradeos. ¿Está el servidor activo?');
    }
}

// ─── Filtrado ──────────────────────────────────────────

function filtrar() {
    const texto = document.getElementById('buscar-carta').value.toLowerCase().trim();
    const tipo  = document.getElementById('filtro-tipo').value;

    const filtrados = todosLosTradeos.filter(t => {
        const todasLasCartas = [...(t.cartas_ofrece || []), ...(t.cartas_busca || [])];
        const coincideTexto  = !texto || todasLasCartas.some(c => c.nombre.toLowerCase().includes(texto));
        const coincideTipo   = !tipo  || todasLasCartas.some(c => c.tipo === tipo);
        return coincideTexto && coincideTipo;
    });

    renderizarTradeos(filtrados);
}

// ─── Renderizado ───────────────────────────────────────

function renderizarTradeos(tradeos) {
    const grid     = document.getElementById('grid-tradeos');
    const contador = document.getElementById('contador');

    contador.textContent = tradeos.length === 1
        ? '1 tradeo activo'
        : `${tradeos.length} tradeos activos`;

    if (!tradeos.length) {
        grid.innerHTML = `
            <div class="vacio-msg">
                <p>No hay tradeos que coincidan con tu búsqueda.</p>
                <button class="btn-secundario" onclick="limpiarFiltros()">Limpiar filtros</button>
            </div>`;
        return;
    }

    grid.innerHTML = tradeos.map(t => tarjetaTradeo(t)).join('');
}

function tarjetaTradeo(t) {
    const fecha       = formatearFecha(t.created_at);
    const inicial     = (t.usuario?.nombre?.[0] || '?').toUpperCase();
    const nombreUser  = t.usuario ? `${t.usuario.nombre} ${t.usuario.apellido}` : 'Usuario';
    const usuarioActual = obtenerUsuario();
    const esPropioTradeo = usuarioActual && t.usuario?.id === usuarioActual.id;

    const miniaturasOfrece = miniaturas(t.cartas_ofrece);
    const miniaturasBusca  = miniaturas(t.cartas_busca);

    const descripcion = t.descripcion
        ? `<p class="tradeo-descripcion">"${escapeHtml(t.descripcion)}"</p>` : '';

    let botonAccion;
    if (!estaLogueado()) {
        botonAccion = `<a href="login.html" class="btn-contactar">Inicia sesión para aceptar</a>`;
    } else if (esPropioTradeo) {
        botonAccion = `<button class="btn-contactar btn-propio" disabled>Tu propio tradeo</button>`;
    } else {
        botonAccion = `<button class="btn-contactar" onclick="abrirModalAceptar(${t.id})">Aceptar tradeo</button>`;
    }

    return `
    <div class="tradeo-card" id="tradeo-card-${t.id}">
        <div class="tradeo-card-header">
            <div class="usuario-info">
                <div class="usuario-avatar">${inicial}</div>
                <span class="usuario-nombre">${escapeHtml(nombreUser)}</span>
            </div>
            <span class="tradeo-fecha">${fecha}</span>
        </div>
        <div class="tradeo-card-body">
            <div class="intercambio">
                <div class="intercambio-lado">
                    <div class="intercambio-label label-ofrece">Ofrece</div>
                    <div class="cartas-miniaturas">${miniaturasOfrece}</div>
                </div>
                <div class="flecha-intercambio">⇄</div>
                <div class="intercambio-lado">
                    <div class="intercambio-label label-busca">Busca</div>
                    <div class="cartas-miniaturas">${miniaturasBusca}</div>
                </div>
            </div>
            ${descripcion}
        </div>
        <div class="tradeo-card-footer">
            ${botonAccion}
        </div>
    </div>`;
}

// ─── Modal: aceptar tradeo ─────────────────────────────

async function abrirModalAceptar(tradeoId) {
    tradeoEnCurso = todosLosTradeos.find(t => t.id === tradeoId);
    if (!tradeoEnCurso) return;

    document.getElementById('modal-aceptar').hidden = false;
    document.getElementById('modal-cuerpo').innerHTML = `
        <p style="text-align:center;color:#888;padding:2rem;">Comprobando tu inventario...</p>`;
    document.getElementById('btn-confirmar').disabled = true;
    document.getElementById('modal-estado').textContent = '';

    try {
        const res = await fetch(`${API_URL}/inventario`, { headers: headersAuth() });
        if (!res.ok) throw new Error();
        inventarioUsuario = await res.json();
    } catch (_) {
        inventarioUsuario = [];
    }

    renderizarModalAceptar(tradeoEnCurso);
}

function renderizarModalAceptar(tradeo) {
    const nombreUser = tradeo.usuario
        ? `${tradeo.usuario.nombre} ${tradeo.usuario.apellido}` : 'Usuario';

    document.getElementById('modal-titulo').textContent = `Aceptar tradeo de ${nombreUser}`;

    const cartasNecesarias = (tradeo.cartas_busca || []).map(carta => {
        const itemInventario = inventarioUsuario.find(i => i.carta_id === carta.id || i.carta?.id === carta.id);
        return { carta, itemInventario, tienes: !!itemInventario };
    });

    const cartasRecibidas = tradeo.cartas_ofrece || [];
    const todasDisponibles = cartasNecesarias.every(c => c.tienes);
    const faltantes = cartasNecesarias.filter(c => !c.tienes);

    const filaRecibiras = cartasRecibidas.map(c => `
        <div class="modal-carta-fila">
            ${c.imagen_url ? `<img src="${c.imagen_url}" alt="${escapeHtml(c.nombre)}" />` : '<div class="modal-carta-placeholder">?</div>'}
            <div class="modal-carta-info">
                <strong>${escapeHtml(c.nombre)}</strong>
                <span>${escapeHtml(c.tipo || '')} ${c.rareza ? '· ' + escapeHtml(c.rareza) : ''}</span>
            </div>
            <span class="modal-check exito">+ Recibirás</span>
        </div>`).join('');

    const filasDaras = cartasNecesarias.map(({ carta, tienes }) => `
        <div class="modal-carta-fila">
            ${carta.imagen_url ? `<img src="${carta.imagen_url}" alt="${escapeHtml(carta.nombre)}" />` : '<div class="modal-carta-placeholder">?</div>'}
            <div class="modal-carta-info">
                <strong>${escapeHtml(carta.nombre)}</strong>
                <span>${escapeHtml(carta.tipo || '')} ${carta.rareza ? '· ' + escapeHtml(carta.rareza) : ''}</span>
            </div>
            <span class="modal-check ${tienes ? 'exito' : 'error'}">${tienes ? '✓ Tienes' : '✗ No tienes'}</span>
        </div>`).join('');

    document.getElementById('modal-cuerpo').innerHTML = `
        <div class="modal-seccion">
            <h4 class="modal-seccion-titulo recibiras">Recibirás</h4>
            ${filaRecibiras || '<p class="modal-vacio">Sin cartas</p>'}
        </div>
        <div class="modal-seccion">
            <h4 class="modal-seccion-titulo daras">Darás a cambio</h4>
            ${filasDaras || '<p class="modal-vacio">Sin cartas requeridas</p>'}
        </div>`;

    const estadoEl    = document.getElementById('modal-estado');
    const btnConfirmar = document.getElementById('btn-confirmar');

    if (todasDisponibles) {
        estadoEl.textContent = 'Tienes todas las cartas necesarias para aceptar este tradeo.';
        estadoEl.className = 'modal-estado exito';
        btnConfirmar.disabled = false;
    } else {
        const nombres = faltantes.map(c => c.carta.nombre).join(', ');
        estadoEl.textContent = `Te faltan: ${nombres}`;
        estadoEl.className = 'modal-estado error';
        btnConfirmar.disabled = true;
    }
}

async function confirmarAceptar() {
    if (!tradeoEnCurso) return;

    const btnConfirmar = document.getElementById('btn-confirmar');
    btnConfirmar.disabled = true;
    btnConfirmar.textContent = 'Procesando...';

    try {
        const res = await fetch(`${API_URL}/tradeos/${tradeoEnCurso.id}/aceptar`, {
            method: 'POST',
            headers: headersAuth()
        });
        const datos = await parsearRespuesta(res);
        if (!res.ok) throw new Error(datos.error || `Error ${res.status}`);

        cerrarModal();
        mostrarToastExito(tradeoEnCurso);
        marcarTradeoAceptado(tradeoEnCurso.id);

    } catch (e) {
        const estadoEl = document.getElementById('modal-estado');
        estadoEl.textContent = `Error durante el intercambio: ${e.message}`;
        estadoEl.className = 'modal-estado error';
        btnConfirmar.disabled = false;
        btnConfirmar.textContent = 'Confirmar intercambio';
    }
}

function cerrarModal() {
    document.getElementById('modal-aceptar').hidden = true;
    tradeoEnCurso = null;
    inventarioUsuario = [];
}

function marcarTradeoAceptado(tradeoId) {
    const card = document.getElementById(`tradeo-card-${tradeoId}`);
    if (!card) return;

    card.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
    card.style.opacity    = '0';
    card.style.transform  = 'scale(0.95)';

    setTimeout(() => {
        card.remove();
        todosLosTradeos = todosLosTradeos.filter(t => t.id !== tradeoId);

        const grid    = document.getElementById('grid-tradeos');
        const activos = grid.querySelectorAll('.tradeo-card').length;
        const contador = document.getElementById('contador');
        contador.textContent = activos === 1 ? '1 tradeo activo' : `${activos} tradeos activos`;

        if (activos === 0) {
            grid.innerHTML = `
                <div class="vacio-msg">
                    <p>No hay tradeos que coincidan con tu búsqueda.</p>
                    <button class="btn-secundario" onclick="limpiarFiltros()">Limpiar filtros</button>
                </div>`;
        }
    }, 400);
}

function mostrarToastExito(tradeo) {
    const nombres = (tradeo.cartas_ofrece || []).map(c => c.nombre).join(', ');
    const toast = document.getElementById('toast-mkt');
    toast.textContent = `¡Intercambio completado! Has recibido: ${nombres}`;
    toast.hidden = false;
    setTimeout(() => { toast.hidden = true; }, 4500);
}

// ─── Utilidades ────────────────────────────────────────

function limpiarFiltros() {
    document.getElementById('buscar-carta').value = '';
    document.getElementById('filtro-tipo').value  = '';
    filtrar();
}

function mostrarError(msg) {
    const box = document.getElementById('error-box');
    document.getElementById('error-msg').textContent = msg;
    box.hidden = false;
    document.getElementById('grid-tradeos').innerHTML = '';
    document.getElementById('contador').textContent = '';
}

function ocultarError() {
    document.getElementById('error-box').hidden = true;
}

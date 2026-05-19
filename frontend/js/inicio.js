document.addEventListener('DOMContentLoaded', () => {
    const btnTradeo = document.querySelector('#btn-hero-tradeo');
    if (btnTradeo && !estaLogueado()) {
        btnTradeo.href = 'pages/login.html';
    }

    cargarNovedades();
    cargarEstadisticas();

    const btnReintentar = document.getElementById('btn-reintentar-novedades');
    if (btnReintentar) btnReintentar.addEventListener('click', cargarNovedades);
});

// ─── Novedades ────────────────────────────────────────

async function cargarNovedades() {
    const grid     = document.getElementById('grid-novedades');
    const errorBox = document.getElementById('error-novedades');
    const errorMsg = document.getElementById('error-novedades-msg');

    if (!grid) return;

    grid.innerHTML = Array(4).fill('<div class="carta-card skeleton" aria-hidden="true"></div>').join('');
    errorBox.hidden = true;

    try {
        const res = await fetch(`${API_URL}/cartas`);
        if (!res.ok) throw new Error(`HTTP_${res.status}`);

        const datos  = await res.json();
        const cartas = Array.isArray(datos) ? datos.slice(0, 8) : (datos.data ?? []).slice(0, 8);

        if (!cartas.length) {
            grid.innerHTML = '<p class="sin-resultados">No hay novedades disponibles aún.</p>';
            return;
        }

        grid.innerHTML = cartas.map(c => tarjetaCarta(c)).join('');

    } catch (error) {
        grid.innerHTML = '';
        errorBox.hidden = false;

        if (error.message.includes('HTTP_404')) {
            errorMsg.textContent = 'El catálogo no está disponible en este momento.';
        } else if (error.message.includes('HTTP_5')) {
            errorMsg.textContent = 'Error en el servidor. Inténtalo más tarde.';
        } else {
            errorMsg.textContent = 'Sin conexión con el servidor. ¿Está activo el backend?';
        }
    }
}

// ─── Estadísticas del hero ────────────────────────────

async function cargarEstadisticas() {
    try {
        const [resCartas, resTradeos] = await Promise.all([
            fetch(`${API_URL}/cartas`),
            fetch(`${API_URL}/tradeos`)
        ]);

        const cartas  = resCartas.ok  ? await resCartas.json()  : [];
        const tradeos = resTradeos.ok ? await resTradeos.json() : [];

        const totalCartas  = Array.isArray(cartas)  ? cartas.length  : (cartas.data?.length  ?? 0);
        const totalTradeos = Array.isArray(tradeos) ? tradeos.length : (tradeos.data?.length ?? 0);

        const statCartas  = document.querySelector('#stat-cartas  .stat-numero');
        const statTradeos = document.querySelector('#stat-tradeos .stat-numero');

        if (statCartas)  statCartas.textContent  = totalCartas.toLocaleString('es-ES');
        if (statTradeos) statTradeos.textContent = totalTradeos.toLocaleString('es-ES');

    } catch (_) {
        // Estadísticas decorativas; si fallan no bloquean la página
    } finally {
        document.querySelectorAll('.stat-item').forEach(el => el.classList.remove('skeleton'));
    }
}

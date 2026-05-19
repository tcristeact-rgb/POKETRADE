document.addEventListener('DOMContentLoaded', cargarNovedades);

async function cargarNovedades() {
    const grid     = document.getElementById('grid-novedades');
    const errorBox = document.getElementById('error-novedades');
    const errorMsg = document.getElementById('error-novedades-msg');

    grid.innerHTML = Array(8).fill('<div class="carta-card skeleton" aria-hidden="true"></div>').join('');
    errorBox.hidden = true;

    try {
        const res = await fetch(`${API_URL}/cartas`);
        if (!res.ok) throw new Error(`HTTP_${res.status}`);

        const datos  = await res.json();
        const cartas = Array.isArray(datos) ? datos : (datos.data ?? []);

        if (!cartas.length) {
            grid.innerHTML = '<p style="grid-column:1/-1;text-align:center;color:#888;">No hay cartas disponibles.</p>';
            return;
        }

        grid.innerHTML = cartas.map(c => tarjetaCarta(c)).join('');

    } catch (error) {
        grid.innerHTML = '';
        errorBox.hidden = false;

        if (error.message.includes('HTTP_5')) {
            errorMsg.textContent = 'Error en el servidor. Inténtalo más tarde.';
        } else if (error.message.includes('HTTP_')) {
            errorMsg.textContent = 'No se pudo cargar el catálogo.';
        } else {
            errorMsg.textContent = 'Sin conexión con el servidor. ¿Está activo el backend?';
        }
    }
}

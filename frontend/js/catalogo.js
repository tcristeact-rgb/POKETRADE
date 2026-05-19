let todasLasCartas = [];

document.addEventListener('DOMContentLoaded', cargarCartas);

async function cargarCartas() {
    const grid = document.getElementById('grid-cartas');
    grid.innerHTML = Array(8).fill('<div class="carta-card skeleton" aria-hidden="true"></div>').join('');

    try {
        const res = await fetch(`${API_URL}/cartas`);
        if (!res.ok) throw new Error(manejarErrorHTTP(res.status));
        todasLasCartas = await res.json();
        mostrarCartas(todasLasCartas);
    } catch (e) {
        grid.innerHTML = `<p class="error-texto" style="grid-column:1/-1;padding:2rem;text-align:center;">Error al cargar las cartas: ${e.message}</p>`;
    }
}

function mostrarCartas(cartas) {
    const grid = document.getElementById('grid-cartas');

    if (!cartas.length) {
        grid.innerHTML = '<p style="grid-column:1/-1;text-align:center;color:#888;padding:2rem;">No se encontraron cartas.</p>';
        return;
    }

    grid.innerHTML = cartas.map(c => tarjetaCarta(c)).join('');
}

function filtrar() {
    const nombre = document.getElementById('filtro-nombre').value.toLowerCase();
    const tipo   = document.getElementById('filtro-tipo').value;
    const rareza = document.getElementById('filtro-rareza').value;

    const filtradas = todasLasCartas.filter(c => {
        const coincideNombre = c.nombre.toLowerCase().includes(nombre);
        const coincideTipo   = !tipo   || c.tipo   === tipo;
        const coincideRareza = !rareza || c.rareza === rareza;
        return coincideNombre && coincideTipo && coincideRareza;
    });

    mostrarCartas(filtradas);
}

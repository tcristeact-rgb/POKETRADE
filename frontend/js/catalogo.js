let todasLasCartas = [];

// Cargar cartas al iniciar
document.addEventListener('DOMContentLoaded', () => {
    cargarCartas();
});

async function cargarCartas() {
    try {
        const respuesta = await fetch(`${API_URL}/cartas`, {
            headers: headersAuth()
        });
        const cartas = await respuesta.json();
        todasLasCartas = cartas;
        mostrarCartas(cartas);
    } catch (error) {
            document.getElementById('grid-cartas').innerHTML =
                '<p class="error-texto">Error al cargar las cartas</p>';
    }
}

function mostrarCartas(cartas) {
    const grid = document.getElementById('grid-cartas');

    if (cartas.length === 0) {
        grid.innerHTML = '<p>No se encontraron cartas</p>';
        return;
    }

    grid.innerHTML = cartas.map(carta => `
        <div class="carta-card" onclick="verDetalle(${carta.id})">
            ${carta.imagen_url 
                ? `<img src="${carta.imagen_url}" alt="${carta.nombre}" />`
                : `<div class="carta-sin-imagen">?</div>`
            }
            <div class="carta-info">
                <h3>${carta.nombre}</h3>
                <span class="carta-tipo">${carta.tipo || 'Sin tipo'}</span>
                <span class="carta-rareza">${carta.rareza || 'Sin rareza'}</span>
                <p class="carta-set">${carta.set_expansion || ''}</p>
            </div>
            <button class="btn-ver-detalle">Ver detalle</button>
        </div>
    `).join('');
}

function filtrar() {
    const nombre = document.getElementById('filtro-nombre').value.toLowerCase();
    const tipo = document.getElementById('filtro-tipo').value;
    const rareza = document.getElementById('filtro-rareza').value;

    const filtradas = todasLasCartas.filter(carta => {
        const coincideNombre = carta.nombre.toLowerCase().includes(nombre);
        const coincideTipo = tipo === '' || carta.tipo === tipo;
        const coincideRareza = rareza === '' || carta.rareza === rareza;
        return coincideNombre && coincideTipo && coincideRareza;
    });

    mostrarCartas(filtradas);
}

function verDetalle(id) {
    window.location.href = `detalle-carta.html?id=${id}`;
}
// detalle-carta.js — Carga el detalle de una carta desde la PokeAPI
// Recibe el ID del Pokémon por parámetro ?id= en la URL

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
        // Pedimos los datos del Pokémon directamente a la PokeAPI
        const res = await fetch(`https://pokeapi.co/api/v2/pokemon/${id}`);
        if (res.status === 404) { mostrarError('Esta carta no existe.'); return; }
        if (!res.ok) throw new Error('Error al conectar con la PokeAPI');

        const poke = await res.json();

        // También pedimos la especie para obtener la descripción en español
        const resEspecie = await fetch(poke.species.url);
        const especie    = resEspecie.ok ? await resEspecie.json() : null;

        // Buscamos la descripción en español
        const descripcion = especie?.flavor_text_entries
            ?.find(e => e.language.name === 'es')
            ?.flavor_text
            ?.replace(/\f/g, ' ') || null;

        // Convertimos al formato interno y añadimos la descripción
        const carta = { ...pokemonACarta(poke), descripcion };

        renderizarDetalle(carta);

    } catch (e) {
        mostrarError(`Error al cargar la carta: ${e.message}`);
    }
}

function renderizarDetalle(carta) {
    document.title = `${carta.nombre} - PokeTrade`;
    document.getElementById('breadcrumb-nombre').textContent = carta.nombre;

    // Botón de inventario solo si el usuario está logueado
    const botonInventario = estaLogueado()
        ? `<button class="btn-inventario" onclick="anadirAInventario(${carta.id})">+ Añadir a mi inventario</button>`
        : `<a href="login.html" class="btn-primario">Inicia sesión para añadir</a>`;

    // Generamos las barras de stats del Pokémon
    const statsHTML = carta.stats ? `
        <div class="stats-seccion">
            <h2 class="stats-titulo">Estadísticas base</h2>
            <div class="stats-lista">
                ${carta.stats.map(s => {
                    const nombreStat = traducirStat(s.stat.name);
                    const valor      = s.base_stat;
                    const porcentaje = Math.min(100, Math.round((valor / 255) * 100));
                    return `
                    <div class="stat-fila">
                        <span class="stat-nombre">${nombreStat}</span>
                        <span class="stat-valor">${valor}</span>
                        <div class="stat-barra-fondo">
                            <div class="stat-barra" style="width:${porcentaje}%;background:${colorStat(valor)}"></div>
                        </div>
                    </div>`;
                }).join('')}
            </div>
        </div>` : '';

    // Todos los tipos del Pokémon (algunos tienen 2)
    const tiposHTML = carta.tipos
        ? carta.tipos.map(t => `<span class="badge-tipo">${traducirTipo(t.type.name)}</span>`).join(' ')
        : carta.tipo ? `<span class="badge-tipo">${carta.tipo}</span>` : '';

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
                    ${tiposHTML ? `
                    <div class="atributo-fila">
                        <span class="atributo-label">Tipo</span>
                        <div style="display:flex;gap:0.4rem;">${tiposHTML}</div>
                    </div>` : ''}
                    ${carta.rareza ? `
                    <div class="atributo-fila">
                        <span class="atributo-label">Rareza</span>
                        <span class="badge-rareza">${carta.rareza}</span>
                    </div>` : ''}
                    ${carta.set_expansion ? `
                    <div class="atributo-fila">
                        <span class="atributo-label">Generación</span>
                        <span>${carta.set_expansion}</span>
                    </div>` : ''}
                    ${carta.altura ? `
                    <div class="atributo-fila">
                        <span class="atributo-label">Altura</span>
                        <span>${(carta.altura / 10).toFixed(1)} m</span>
                    </div>` : ''}
                    ${carta.peso ? `
                    <div class="atributo-fila">
                        <span class="atributo-label">Peso</span>
                        <span>${(carta.peso / 10).toFixed(1)} kg</span>
                    </div>` : ''}
                </div>
                ${carta.descripcion ? `<p class="descripcion-carta">${carta.descripcion}</p>` : ''}
                <div class="acciones-carta">
                    ${botonInventario}
                    <a href="catalogo.html" class="btn-secundario" style="padding:0.65rem 1.25rem;">← Catálogo</a>
                </div>
            </div>
        </div>
        ${statsHTML}
    `;
}

// Traduce los nombres de las stats de inglés a español
function traducirStat(stat) {
    const stats = {
        'hp':              'PS',
        'attack':          'Ataque',
        'defense':         'Defensa',
        'special-attack':  'At. Especial',
        'special-defense': 'Def. Especial',
        'speed':           'Velocidad',
    };
    return stats[stat] || stat;
}

// Color de la barra según el valor de la stat
function colorStat(valor) {
    if (valor >= 150) return '#22c55e'; // Verde brillante
    if (valor >= 100) return '#84cc16'; // Verde
    if (valor >= 70)  return '#eab308'; // Amarillo
    if (valor >= 50)  return '#f97316'; // Naranja
    return '#ef4444';                   // Rojo
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
// detalle-carta.js — Detalle de una carta desde la PokeAPI (módulo ES6)
// Recibe el ID del Pokémon por parámetro ?id= en la URL.

import { API_URL, estaLogueado, headersAuth, manejarErrorHTTP, parsearRespuesta } from './auth.js';
import { pokemonACarta, traducirTipo, escapeHtml, mostrarAlerta } from './utils.js';

let cartaActual = null;

document.addEventListener('DOMContentLoaded', () => {
    const id = new URLSearchParams(window.location.search).get('id');

    if (!id) {
        mostrarError('No se especificó ninguna carta.');
        return;
    }

    cargarDetalle(id);
});

async function cargarDetalle(id) {
    try {
        const res = await fetch(`https://pokeapi.co/api/v2/pokemon/${id}`);

        if (res.status === 404) { mostrarError('Esta carta no existe.'); return; }
        if (!res.ok) throw new Error('Error al conectar con la PokeAPI');

        const poke = await res.json();

        // También pedimos la especie para obtener la descripción en español
        const resEspecie = await fetch(poke.species.url);
        const especie    = resEspecie.ok ? await resEspecie.json() : null;

        const descripcion = especie?.flavor_text_entries
            ?.find(e => e.language.name === 'es')
            ?.flavor_text
            ?.replace(/\f/g, ' ') || null;

        cartaActual = { ...pokemonACarta(poke), descripcion };
        renderizarDetalle(cartaActual);

    } catch (e) {
        mostrarError(`Error al cargar la carta: ${e.message}`);
    }
}

function renderizarDetalle(carta) {
    document.title = `${carta.nombre} - PokeTrade`;
    document.getElementById('breadcrumb-nombre').textContent = carta.nombre;

    const nombreSeguro = escapeHtml(carta.nombre);

    // Botón de inventario solo si el usuario está logueado
    const botonInventario = estaLogueado()
        ? `<button class="btn-inventario" id="btn-add-inventario" type="button">+ Añadir a mi inventario</button>`
        : `<a href="login.html" class="btn-primario">Inicia sesión para añadir</a>`;

    // Barras de stats del Pokémon
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
                            <div class="stat-barra stat-barra-${claseStat(valor)}" style="width:${porcentaje}%"></div>
                        </div>
                    </div>`;
                }).join('')}
            </div>
        </div>` : '';

    // Todos los tipos del Pokémon (algunos tienen 2)
    const tiposHTML = carta.tipos
        ? carta.tipos.map(t => `<span class="badge-tipo">${escapeHtml(traducirTipo(t.type.name))}</span>`).join(' ')
        : carta.tipo ? `<span class="badge-tipo">${escapeHtml(carta.tipo)}</span>` : '';

    document.getElementById('contenido-detalle').innerHTML = `
        <div class="detalle-card">
            <div class="detalle-imagen">
                ${carta.imagen_url
                    ? `<img src="${escapeHtml(carta.imagen_url)}" alt="${nombreSeguro}" />`
                    : `<div class="sin-imagen-grande" aria-hidden="true">?</div>`}
            </div>
            <div class="detalle-info">
                <h1>${nombreSeguro}</h1>
                <div class="atributos">
                    ${tiposHTML ? `
                    <div class="atributo-fila">
                        <span class="atributo-label">Tipo</span>
                        <div class="detalle-tipos">${tiposHTML}</div>
                    </div>` : ''}
                    ${carta.rareza ? `
                    <div class="atributo-fila">
                        <span class="atributo-label">Rareza</span>
                        <span class="badge-rareza">${escapeHtml(carta.rareza)}</span>
                    </div>` : ''}
                    ${carta.set_expansion ? `
                    <div class="atributo-fila">
                        <span class="atributo-label">Generación</span>
                        <span>${escapeHtml(carta.set_expansion)}</span>
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
                ${carta.descripcion ? `<p class="descripcion-carta">${escapeHtml(carta.descripcion)}</p>` : ''}
                <div class="acciones-carta">
                    ${botonInventario}
                    <a href="catalogo.html" class="btn-secundario">← Catálogo</a>
                </div>
            </div>
        </div>
        ${statsHTML}
    `;

    // Enlazar el botón de inventario (sin onclick en línea)
    document.getElementById('btn-add-inventario')
        ?.addEventListener('click', () => anadirAInventario(carta));
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

// Clase CSS de la barra según el valor de la stat
function claseStat(valor) {
    if (valor >= 150) return 'muy-alta';
    if (valor >= 100) return 'alta';
    if (valor >= 70)  return 'media';
    if (valor >= 50)  return 'baja';
    return 'muy-baja';
}

// El detalle se nutre de la PokeAPI, cuyos IDs NO coinciden con los de la
// tabla cartas del backend. Por eso enviamos los datos completos del Pokémon:
// el backend lo busca por numero (nº de Pokédex) y, si no está en el
// catálogo, lo crea en el momento. Así funciona para cualquier Pokémon.
async function anadirAInventario(carta) {
    const btn = document.getElementById('btn-add-inventario');
    btn.disabled = true;
    btn.textContent = 'Añadiendo...';

    try {
        const res = await fetch(`${API_URL}/inventario`, {
            method: 'POST',
            headers: headersAuth(),
            body: JSON.stringify({
                carta: {
                    nombre:        carta.nombre,
                    numero:        String(carta.id).padStart(3, '0'),
                    tipo:          carta.tipo,
                    rareza:        carta.rareza,
                    set_expansion: carta.set_expansion,
                    imagen_url:    carta.imagen_url,
                    descripcion:   carta.descripcion,
                },
                cantidad: 1
            })
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
        `<p class="error-texto detalle-error">${msg}</p>`;
}

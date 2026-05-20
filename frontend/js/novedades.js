// novedades.js — Carga las últimas cartas desde la PokeAPI
// Muestra los últimos 20 Pokémon añadidos (los de ID más alto = más recientes)

const TOTAL_NOVEDADES = 20;    // Cuántas cartas mostrar en novedades
const OFFSET_NOVEDADES = 880;  // Empezamos desde el Pokémon 880 (los más recientes)

document.addEventListener('DOMContentLoaded', cargarNovedades);

async function cargarNovedades() {
    const grid     = document.getElementById('grid-novedades');
    const errorBox = document.getElementById('error-novedades');
    const errorMsg = document.getElementById('error-novedades-msg');

    // Mostramos skeletons mientras carga
    grid.innerHTML = Array(8).fill('<div class="carta-card skeleton" aria-hidden="true"></div>').join('');
    errorBox.hidden = true;

    try {
        // Pedimos la lista de Pokémon recientes a la PokeAPI
        const res = await fetch(`https://pokeapi.co/api/v2/pokemon?limit=${TOTAL_NOVEDADES}&offset=${OFFSET_NOVEDADES}`);
        if (!res.ok) throw new Error('Error al conectar con la PokeAPI');
        const datos = await res.json();

        // Para cada Pokémon de la lista pedimos sus datos completos en paralelo
        const promesas = datos.results.map(p => fetch(p.url).then(r => r.json()));
        const pokemons = await Promise.all(promesas);

        // Convertimos los datos de la PokeAPI al formato de tarjetaCarta()
        const cartas = pokemons.map(p => pokemonACarta(p));

        grid.innerHTML = cartas.map(c => tarjetaCarta(c)).join('');

    } catch (error) {
        grid.innerHTML = '';
        errorBox.hidden = false;
        errorMsg.textContent = 'No se pudieron cargar las novedades. Inténtalo más tarde.';
    }
}

// Convierte un objeto de la PokeAPI al formato que usa tarjetaCarta()
function pokemonACarta(p) {
    return {
        id:          p.id,
        nombre:      capitalizarNombre(p.name),
        tipo:        p.types[0]?.type?.name
                        ? traducirTipo(p.types[0].type.name) : null,
        rareza:      calcularRareza(p.base_experience),
        imagen_url:  p.sprites?.other?.['official-artwork']?.front_default
                        || p.sprites?.front_default
                        || null,
        set_expansion: `Gen ${calcularGeneracion(p.id)}`,
        // Guardamos los stats para la página de detalle
        stats:       p.stats,
        altura:      p.height,
        peso:        p.weight,
    };
}

// Capitaliza el nombre del Pokémon (ej: "charizard" → "Charizard")
function capitalizarNombre(nombre) {
    return nombre.charAt(0).toUpperCase() + nombre.slice(1);
}

// Traduce los tipos de inglés a español
function traducirTipo(tipo) {
    const tipos = {
        fire: 'Fuego', water: 'Agua', grass: 'Planta',
        electric: 'Eléctrico', psychic: 'Psíquico', ice: 'Hielo',
        dragon: 'Dragón', dark: 'Siniestro', fairy: 'Hada',
        normal: 'Normal', fighting: 'Lucha', flying: 'Volador',
        poison: 'Veneno', ground: 'Tierra', rock: 'Roca',
        bug: 'Bicho', ghost: 'Fantasma', steel: 'Acero',
    };
    return tipos[tipo] || tipo;
}

// Calcula la rareza según la experiencia base del Pokémon
function calcularRareza(exp) {
    if (!exp) return 'Común';
    if (exp >= 250) return 'Ultra Rara';
    if (exp >= 150) return 'Rara Holo';
    if (exp >= 100) return 'Rara';
    if (exp >= 50)  return 'Poco común';
    return 'Común';
}

// Calcula la generación según el ID del Pokémon
function calcularGeneracion(id) {
    if (id <= 151)  return 'I';
    if (id <= 251)  return 'II';
    if (id <= 386)  return 'III';
    if (id <= 493)  return 'IV';
    if (id <= 649)  return 'V';
    if (id <= 721)  return 'VI';
    if (id <= 809)  return 'VII';
    if (id <= 905)  return 'VIII';
    return 'IX';
}
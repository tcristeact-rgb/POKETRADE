// inicio.js — Lógica de la página principal

document.addEventListener('DOMContentLoaded', () => {
    const btnTradeo = document.querySelector('#btn-hero-tradeo');
    if (btnTradeo && !estaLogueado()) {
        btnTradeo.href = 'pages/login.html';
    }

    const ctaSeccion = document.getElementById('cta-seccion');
    if (ctaSeccion && estaLogueado()) {
        ctaSeccion.hidden = true;
    }

    cargarNovedades();
    cargarEstadisticas();

    const btnReintentar = document.getElementById('btn-reintentar-novedades');
    if (btnReintentar) btnReintentar.addEventListener('click', cargarNovedades);
});

// ─── Novedades: los 8 Pokémon más recientes con imagen oficial ────────────

async function cargarNovedades() {
    const grid     = document.getElementById('grid-novedades');
    const errorBox = document.getElementById('error-novedades');
    const errorMsg = document.getElementById('error-novedades-msg');

    if (!grid) return;

    grid.innerHTML = Array(4).fill('<div class="carta-card skeleton" aria-hidden="true"></div>').join('');
    errorBox.hidden = true;

    try {
        // Pedimos el total para calcular el offset desde el final
        const resTotal = await fetch('https://pokeapi.co/api/v2/pokemon?limit=1');
        if (!resTotal.ok) throw new Error('Error al conectar con la PokeAPI');
        const datosTotales = await resTotal.json();
        const total = datosTotales.count;

        // Cogemos los últimos 40 para tener margen de filtrar los sin imagen
        const offset = Math.max(0, total - 40);
        const res = await fetch(`https://pokeapi.co/api/v2/pokemon?limit=40&offset=${offset}`);
        if (!res.ok) throw new Error('Error al conectar con la PokeAPI');
        const datos = await res.json();

        // Invertimos para mostrar del más nuevo al más antiguo
        const listaInvertida = [...datos.results].reverse();

        // Cargamos datos completos en paralelo
        const promesas = listaInvertida.map(p => fetch(p.url).then(r => r.json()));
        const pokemons = await Promise.all(promesas);

        // Filtramos los que tienen artwork oficial y cogemos los primeros 8
        const conImagen = pokemons
            .filter(p => p.sprites?.other?.['official-artwork']?.front_default)
            .slice(0, 8);

        grid.innerHTML = conImagen.map(p => tarjetaCarta(pokemonACarta(p))).join('');

    } catch (error) {
        grid.innerHTML = '';
        errorBox.hidden = false;
        errorMsg.textContent = 'No se pudieron cargar las novedades. Inténtalo más tarde.';
    }
}

// ─── Estadísticas del hero ────────────────────────────

async function cargarEstadisticas() {
    try {
        const [resPoke, resTradeos] = await Promise.all([
            fetch('https://pokeapi.co/api/v2/pokemon?limit=1'),
            fetch(`${API_URL}/tradeos`)
        ]);

        const datosPoke = resPoke.ok ? await resPoke.json() : null;
        const tradeos   = resTradeos.ok ? await resTradeos.json() : [];

        const totalCartas  = datosPoke?.count ?? 0;
        const totalTradeos = Array.isArray(tradeos) ? tradeos.length : 0;

        const statCartas  = document.querySelector('#stat-cartas  .stat-numero');
        const statTradeos = document.querySelector('#stat-tradeos .stat-numero');

        if (statCartas)  statCartas.textContent  = totalCartas.toLocaleString('es-ES');
        if (statTradeos) statTradeos.textContent = totalTradeos.toLocaleString('es-ES');

    } catch (_) {
        // Las estadísticas son decorativas
    } finally {
        document.querySelectorAll('.stat-item').forEach(el => el.classList.remove('skeleton'));
    }
}
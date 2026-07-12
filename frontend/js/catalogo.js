// catalogo.js — Catálogo unificado del TCG (módulo ES6)
// Una sola página con cuatro vistas, decididas por la URL:
//   catalogo.html                → grid de series (más recientes primero)
//   catalogo.html?serie=sv       → grid de sets de esa serie
//   catalogo.html?set=sv03.5     → cartas del set, paginadas
//   filtros activos SIN ?set=    → búsqueda global contra TCGdex
//
// La barra de filtros (q, tipo, rareza) es persistente: su estado vive
// en la URL (filtros-catalogo.js) y todos los enlaces internos la
// propagan, así el filtro sobrevive al navegar entre expansiones.
// Dentro de un set filtra sus cartas (GET /api/sets/{id}/cartas);
// fuera, busca en TODO el catálogo (GET /api/cartas/buscar). La tabla
// local completa (GET /api/cartas) ya no se usa aquí.

import { API_URL } from './auth.js';
import { t } from './i18n.js';
import { alCargarDOM, activarPlaceholderImagenes, escapeHtml, tarjetaCarta, placeholderLogo } from './utils.js';
import { crearPaginacion } from './paginacion.js';
import { activarLightboxEnGrid } from './lightbox.js';
import { iniciarFiltros } from './filtros-catalogo.js';

const CARTAS_POR_PAGINA = 20;
const MAX_BUSQUEDA_GLOBAL = 60;

let filtros;             // instancia de filtros-catalogo.js
let cartasActuales = []; // cartas visibles (para el lightbox)

// Zoom: click en la ilustración de una tarjeta → lightbox con las
// cartas visibles (flechas ←/→ para moverse entre ellas)
activarLightboxEnGrid('grid-catalogo', () => cartasActuales);

// URLs de imagen muertas → placeholder, nunca imagen rota
activarPlaceholderImagenes('grid-catalogo');
activarPlaceholderImagenes('cabecera-catalogo');

const paginacion = crearPaginacion({
    contenedorId: 'paginacion',
    infoId:       'paginacion-info',
    alCambiar:    (pagina) => renderizar(pagina),
});

alCargarDOM(() => {
    filtros = iniciarFiltros({ alCambiar: () => renderizar(1) });

    const paginaInicial = Math.max(1, parseInt(param('page'), 10) || 1);
    renderizar(paginaInicial);
});

// ── Enrutado de vistas ─────────────────────────────

function param(nombre) {
    return new URLSearchParams(window.location.search).get(nombre) || '';
}

function renderizar(pagina = 1) {
    const setId = param('set');

    if (setId) {
        vistaSet(setId, pagina);
    } else if (filtros.hayFiltros()) {
        vistaBusquedaGlobal();
    } else if (param('serie')) {
        vistaSets(param('serie'));
    } else {
        vistaSeries();
    }
}

// URL interna del catálogo con los filtros activos propagados,
// para que el estado sobreviva al navegar entre series y sets
function urlCatalogo(paramsBase) {
    const destino = new URLSearchParams(paramsBase);
    filtros.aplicarSobre(destino);
    return `catalogo.html?${destino}`;
}

// ── Vista 1: todas las series ──────────────────────

async function vistaSeries() {
    cabecera(t('catalogo.titulo'), t('catalogo.subtituloSeries'));
    breadcrumb([[t('comun.inicio'), '../index.html'], t('catalogo.titulo')]);
    modoFiltro(null);
    sinPaginacion();
    esqueletos('set-card');

    const grid = document.getElementById('grid-catalogo');
    grid.className = 'grid-sets';

    try {
        const res = await fetch(`${API_URL}/series`);
        if (!res.ok) throw new Error(t('comun.errorApi'));
        const series = await res.json();

        if (!series.length) {
            grid.innerHTML = `<p class="grid-mensaje">${escapeHtml(t('catalogo.sinSeries'))}</p>`;
            return;
        }

        grid.innerHTML = series.map(s => tarjetaSerie(s)).join('');
    } catch (e) {
        mostrarError(t('catalogo.errorCatalogo', { mensaje: e.message }));
    }
}

function tarjetaSerie(serie) {
    const nombre = escapeHtml(serie.nombre);
    const anio   = serie.fecha_ultimo_set ? serie.fecha_ultimo_set.slice(0, 4) : null;

    // Serie de un solo set (el backend manda su id en set_unico): el
    // enlace salta directo a las cartas. Un grid con una sola tarjeta
    // sería un click muerto. Los filtros activos viajan igualmente.
    const destino = serie.set_unico
        ? urlCatalogo({ set: serie.set_unico })
        : urlCatalogo({ serie: serie.tcgdex_id });

    const etiqueta = serie.set_unico
        ? t('catalogo.verCartasDe', { nombre: serie.nombre })
        : t('catalogo.verSetsDe',  { nombre: serie.nombre });

    // Una serie de un solo set muestra el año a secas; las de varios,
    // el año del último ("hasta 2024")
    const unSoloSet = serie.sets_count === 1;
    const meta = [
        t('catalogo.nSets', { n: serie.sets_count }),
        anio ? (unSoloSet ? anio : t('catalogo.hasta', { anio })) : null,
    ].filter(Boolean).join(' · ');

    return `
        <a class="set-card" href="${destino}" aria-label="${escapeHtml(etiqueta)}">
            ${logoHTML(serie.logo, null, serie.nombre)}
            <div class="set-info">
                <h3>${nombre}</h3>
                <p class="set-meta">${escapeHtml(meta)}</p>
            </div>
        </a>`;
}

// ── Vista 2: sets de una serie ─────────────────────

async function vistaSets(serieId) {
    modoFiltro(null);
    sinPaginacion();
    esqueletoCabecera();
    esqueletos('set-card');

    const grid = document.getElementById('grid-catalogo');
    grid.className = 'grid-sets';

    try {
        const res = await fetch(`${API_URL}/series/${encodeURIComponent(serieId)}`);
        if (res.status === 404) { mostrarError(t('catalogo.serieNoExiste')); return; }
        if (!res.ok) throw new Error(t('comun.errorApi'));
        const serie = await res.json();

        // Serie de un solo set: no hay nada que elegir. Sustituimos la
        // URL por la del set (sin ensuciar el historial, y conservando
        // los filtros) y pintamos sus cartas. Esta es la red de
        // seguridad para marcadores y enlaces antiguos a ?serie=; el
        // camino normal ya enlaza directo desde el grid de series.
        if (serie.sets.length === 1) {
            const setId = serie.sets[0].tcgdex_id;
            const url = new URL(window.location.href);
            url.searchParams.delete('serie');
            url.searchParams.set('set', setId);
            history.replaceState(null, '', url);
            vistaSet(setId, 1);
            return;
        }

        document.title = t('meta.tituloPagina', { titulo: serie.nombre });
        cabecera(serie.nombre, t('catalogo.subtituloSets', { n: serie.sets.length }));
        breadcrumb([[t('comun.inicio'), '../index.html'], [t('catalogo.titulo'), urlCatalogo({})], serie.nombre]);

        if (!serie.sets.length) {
            grid.innerHTML = `<p class="grid-mensaje">${escapeHtml(t('catalogo.sinSets'))}</p>`;
            return;
        }

        grid.innerHTML = serie.sets.map(s => tarjetaSet(s)).join('');
    } catch (e) {
        mostrarError(t('catalogo.errorSerie', { mensaje: e.message }));
    }
}

function tarjetaSet(set) {
    const nombre = escapeHtml(set.nombre);
    const anio   = set.fecha_lanzamiento ? set.fecha_lanzamiento.slice(0, 4) : null;
    const meta   = [anio, t('catalogo.nCartas', { n: set.numero_cartas })].filter(Boolean).join(' · ');

    return `
        <a class="set-card" href="${urlCatalogo({ set: set.tcgdex_id })}"
           aria-label="${escapeHtml(t('catalogo.verCartasSet', { nombre: set.nombre }))}">
            ${logoHTML(set.logo, set.simbolo, set.nombre)}
            <div class="set-info">
                <h3>${nombre}</h3>
                <p class="set-meta">${escapeHtml(meta)}</p>
            </div>
        </a>`;
}

// ── Vista 3: cartas de un set (paginadas, filtrables) ──

async function vistaSet(setId, pagina) {
    esqueletos('carta-card', CARTAS_POR_PAGINA);
    programarAvisoLento();

    const grid = document.getElementById('grid-catalogo');
    grid.className = 'grid-cartas';

    // La cabecera del set solo hace falta la primera vez; al paginar o
    // filtrar ya está pintada y basta con refrescar el indicador de modo
    const cabeceraEl = document.getElementById('cabecera-catalogo');
    if (cabeceraEl.dataset.set !== setId) {
        esqueletoCabecera();
        cargarCabeceraSet(setId);
    } else {
        modoFiltro(filtros.hayFiltros()
            ? t('catalogo.filtrandoEn', { set: cabeceraEl.dataset.nombre })
            : null);
    }

    try {
        const { q, tipo, rareza } = filtros.actuales();
        const params = new URLSearchParams({ page: pagina, por_pagina: CARTAS_POR_PAGINA });
        if (q)      params.set('nombre', q);
        if (tipo)   params.set('tipo', tipo);
        if (rareza) params.set('rareza', rareza);

        const res = await fetch(`${API_URL}/sets/${encodeURIComponent(setId)}/cartas?${params}`);
        cancelarAvisoLento();

        if (res.status === 404) { mostrarError(t('catalogo.setNoExiste')); return; }
        if (res.status === 503) {
            const datos = await res.json().catch(() => ({}));
            mostrarErrorConReintento(datos.error || t('catalogo.tcgdexCaido'), pagina);
            return;
        }
        if (!res.ok) throw new Error(t('comun.errorApi'));
        const datos = await res.json();

        actualizarUrlPagina(datos.current_page);
        mostrarCartas(datos.data, t('catalogo.sinCoincidenciasSet'));
        paginacion.actualizar(datos);

        if (pagina > 1) {
            document.querySelector('.catalogo-contenedor')?.scrollIntoView({ behavior: 'smooth' });
        }
    } catch (e) {
        cancelarAvisoLento();
        mostrarErrorConReintento(t('catalogo.errorCartas', { mensaje: e.message }), pagina);
    }
}

// Cabecera y breadcrumb del set (GET /api/sets/{id}, en paralelo con
// las cartas: sale del índice y no depende del cacheo)
async function cargarCabeceraSet(setId) {
    try {
        const res = await fetch(`${API_URL}/sets/${encodeURIComponent(setId)}`);
        if (!res.ok) return;
        const set = await res.json();

        document.title = t('meta.tituloPagina', { titulo: set.nombre });

        const anio = set.fecha_lanzamiento ? set.fecha_lanzamiento.slice(0, 4) : null;
        cabecera(
            set.nombre,
            [anio, t('catalogo.nCartas', { n: set.numero_cartas })].filter(Boolean).join(' · '),
            set.logo,
        );

        const cabeceraEl = document.getElementById('cabecera-catalogo');
        cabeceraEl.dataset.set    = setId;
        cabeceraEl.dataset.nombre = set.nombre;
        modoFiltro(filtros.hayFiltros() ? t('catalogo.filtrandoEn', { set: set.nombre }) : null);

        // Breadcrumb: en una serie de varios sets el nivel de serie es
        // un enlace normal. Si la serie tiene un único set, ese enlace
        // volvería a este mismo sitio, así que se degrada a texto plano
        // (mantiene el contexto sin click muerto) y se omite del todo
        // cuando serie y set se llaman igual, para no repetir el nombre.
        const miga = [[t('comun.inicio'), '../index.html'], [t('catalogo.titulo'), urlCatalogo({})]];
        if (set.serie) {
            const serieDeUnSet = set.serie.sets_count === 1;
            if (!serieDeUnSet) {
                miga.push([set.serie.nombre, urlCatalogo({ serie: set.serie.tcgdex_id })]);
            } else if (set.serie.nombre !== set.nombre) {
                miga.push(set.serie.nombre);
            }
        }
        miga.push(set.nombre);
        breadcrumb(miga);
    } catch (_) { /* la cabecera es secundaria: el grid sigue funcionando */ }
}

// ── Vista 4: búsqueda global en todo el catálogo ───

async function vistaBusquedaGlobal() {
    cabecera(t('catalogo.titulo'), t('catalogo.subtituloBusqueda'));
    breadcrumb([
        [t('comun.inicio'), '../index.html'],
        [t('catalogo.titulo'), urlCatalogo({})],
        t('catalogo.busqueda'),
    ]);
    modoFiltro(t('catalogo.buscando'));
    sinPaginacion();
    esqueletos('carta-card', CARTAS_POR_PAGINA);

    const grid = document.getElementById('grid-catalogo');
    grid.className = 'grid-cartas';

    try {
        const { q, tipo, rareza } = filtros.actuales();
        const params = new URLSearchParams();
        if (q)      params.set('q', q);
        if (tipo)   params.set('tipo', tipo);
        if (rareza) params.set('rareza', rareza);

        const res = await fetch(`${API_URL}/cartas/buscar?${params}`);

        if (res.status === 503) {
            const datos = await res.json().catch(() => ({}));
            mostrarErrorConReintento(datos.error || t('catalogo.tcgdexCaido'));
            return;
        }
        if (!res.ok) throw new Error(t('comun.errorApi'));
        const datos = await res.json();

        mostrarCartas(datos.data, t('catalogo.sinCoincidencias'));

        const info = document.getElementById('paginacion-info');
        if (info) {
            info.textContent = datos.total === MAX_BUSQUEDA_GLOBAL
                ? t('catalogo.primerosResultados', { n: datos.total })
                : t('catalogo.resultados', { n: datos.total });
        }
    } catch (e) {
        mostrarErrorConReintento(t('catalogo.errorBusqueda', { mensaje: e.message }));
    }
}

// ── Piezas compartidas ─────────────────────────────

function mostrarCartas(cartas, mensajeVacio) {
    const grid = document.getElementById('grid-catalogo');
    cartasActuales = cartas;

    if (!cartas.length) {
        grid.innerHTML =
            `<div class="grid-mensaje">` +
            `<p>${escapeHtml(mensajeVacio)}</p>` +
            `<button class="btn-primario btn-limpiar-vacio" type="button">${escapeHtml(t('catalogo.limpiarFiltros'))}</button>` +
            `</div>`;
        grid.querySelector('.btn-limpiar-vacio')?.addEventListener('click', () => filtros.limpiar());
        return;
    }

    grid.innerHTML = cartas.map(c => tarjetaCarta(c)).join('');
}

// Logo con degradación elegante: logo real → wordmark propio con el
// nombre (y el símbolo real incrustado si existe). El data-nombre deja
// el nombre a mano para la red de seguridad si el logo real muere.
function logoHTML(logo, simbolo, nombre) {
    if (logo) {
        return `<div class="set-logo"><img src="${escapeHtml(logo)}" alt=""` +
               ` data-nombre="${escapeHtml(nombre)}" loading="lazy" /></div>`;
    }
    return `<div class="set-logo">${placeholderLogo(nombre, simbolo)}</div>`;
}

function cabecera(titulo, subtitulo, logo = null) {
    const contenedor = document.getElementById('cabecera-catalogo');
    contenedor.innerHTML = `
        ${logo ? `<div class="set-cabecera-logo"><img src="${escapeHtml(logo)}" alt="" data-nombre="${escapeHtml(titulo)}" /></div>` : ''}
        <div>
            <h1 id="titulo-catalogo">${escapeHtml(titulo)}</h1>
            ${subtitulo ? `<p class="set-meta" id="subtitulo-catalogo">${escapeHtml(subtitulo)}</p>` : ''}
        </div>`;
}

// Pinta la miga de pan: cada elemento es "texto" (actual) o [texto, href]
function breadcrumb(elementos) {
    document.getElementById('breadcrumb').innerHTML = elementos
        .map(el => Array.isArray(el)
            ? `<a href="${escapeHtml(el[1])}">${escapeHtml(el[0])}</a>`
            : `<span>${escapeHtml(el)}</span>`)
        .join(' › ');
}

// Indicador del alcance de los filtros. Fuera de un set se pasa solo
// el texto; dentro, cargarCabeceraSet lo refresca con el nombre real
function modoFiltro(texto) {
    const el = document.getElementById('modo-filtro');
    if (!el) return;
    el.hidden = !texto;
    el.textContent = texto || '';
}

function sinPaginacion() {
    paginacion.actualizar({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 });
    const info = document.getElementById('paginacion-info');
    if (info) info.textContent = '';
}

// Los esqueletos deben ser TANTOS como el contenido que va a llegar:
// pintar 8 y recibir 20 cartas hace crecer la página de golpe (salto
// de layout). Por eso el grid de cartas pide CARTAS_POR_PAGINA.
function esqueletos(clase, cantidad = 12) {
    document.getElementById('grid-catalogo').innerHTML =
        Array(cantidad).fill(`<div class="${clase} skeleton" aria-hidden="true"></div>`).join('');
}

// La cabecera del set también tarda (es otra petición): sin esqueleto,
// el título aparece de golpe y empuja el grid hacia abajo
function esqueletoCabecera() {
    document.getElementById('cabecera-catalogo').innerHTML =
        '<div class="skeleton skeleton-cabecera-set" aria-hidden="true"></div>';
}

// Aviso de espera larga. La primera visita a un set no cacheado tiene
// que ir a TCGdex y guardar sus cartas (cache-aside), así que puede
// tardar segundos: en vez de dejar los esqueletos mudos, se explica.
let temporizadorAviso = null;

function programarAvisoLento() {
    cancelarAvisoLento();
    temporizadorAviso = setTimeout(() => {
        const aviso = document.getElementById('aviso-carga');
        if (aviso) aviso.hidden = false;
    }, 2500);
}

function cancelarAvisoLento() {
    clearTimeout(temporizadorAviso);
    temporizadorAviso = null;
    const aviso = document.getElementById('aviso-carga');
    if (aviso) aviso.hidden = true;
}

// Refleja la página actual en la URL (?page=N) sin ensuciar el
// historial, para que atrás/recarga devuelvan a la misma página
function actualizarUrlPagina(pagina) {
    const url = new URL(window.location.href);
    if (pagina > 1) {
        url.searchParams.set('page', pagina);
    } else {
        url.searchParams.delete('page');
    }
    history.replaceState(null, '', url);
}

function mostrarError(msg) {
    sinPaginacion();
    document.getElementById('grid-catalogo').innerHTML =
        `<p class="grid-mensaje error-texto">${escapeHtml(msg)}</p>`;
}

// Error recuperable (TCGdex caído o red): mensaje + botón de reintento
function mostrarErrorConReintento(msg, pagina = 1) {
    const grid = document.getElementById('grid-catalogo');
    grid.innerHTML =
        `<div class="grid-mensaje">` +
        `<p class="error-texto">${escapeHtml(msg)}</p>` +
        `<button class="btn-primario btn-reintentar" type="button">${escapeHtml(t('comun.reintentar'))}</button>` +
        `</div>`;
    grid.querySelector('.btn-reintentar')?.addEventListener('click', () => renderizar(pagina));
}

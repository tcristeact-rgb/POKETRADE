// inicio.js — Lógica de la página principal (módulo ES6)

import { API_URL, estaLogueado } from './auth.js';
import { t } from './i18n.js';
import { alCargarDOM, tarjetaCarta, escapeHtml, formatearPrecio } from './utils.js';

alCargarDOM(() => {
    const ctaSeccion = document.getElementById('cta-seccion');
    if (ctaSeccion && estaLogueado()) {
        ctaSeccion.hidden = true;
    }

    cargarNovedades();
    cargarDestacadas();

    const btnReintentar = document.getElementById('btn-reintentar-novedades');
    if (btnReintentar) btnReintentar.addEventListener('click', cargarNovedades);
});

// ─── Novedades: las 8 cartas más recientes del catálogo ────────────

async function cargarNovedades() {
    const grid     = document.getElementById('grid-novedades');
    const errorBox = document.getElementById('error-novedades');
    const errorMsg = document.getElementById('error-novedades-msg');

    if (!grid) return;

    grid.innerHTML = Array(4).fill('<div class="carta-card skeleton" aria-hidden="true"></div>').join('');
    errorBox.hidden = true;

    try {
        // Últimas cartas añadidas al catálogo, desde nuestra API
        const res = await fetch(`${API_URL}/cartas?orden=recientes&por_pagina=8`);
        if (!res.ok) throw new Error(t('comun.errorApi'));
        const datos = await res.json();

        if (!datos.data.length) {
            grid.innerHTML = `<p class="grid-mensaje">${escapeHtml(t('home.sinCartas'))}</p>`;
            return;
        }

        grid.innerHTML = datos.data.map(c => tarjetaCarta(c)).join('');

    } catch (error) {
        grid.innerHTML = '';
        errorBox.hidden = false;
        errorMsg.textContent = t('home.errorNovedadesTarde');
    }
}

// ─── Escaparate del hero: carta destacada protagonista ─────────────
// Una carta en grande rotando entre las 4 más caras de las novedades
// (GET /cartas/destacadas). Flechas + swipe + puntos + autoplay
// (pausado con puntero/foco encima, desactivado con
// prefers-reduced-motion). La carta y el bloque nombre/precio del
// hero enlazan a su detalle.

let destacadas   = [];
let indiceActivo = 0;
let autoplay     = null;
let huboSwipe    = false;
const REDUCIR_MOVIMIENTO = window.matchMedia('(prefers-reduced-motion: reduce)');
const coloresGlow = new Map();   // id de carta → rgba del glow ya calculado

async function cargarDestacadas() {
    const escaparate = document.getElementById('hero-escaparate');
    if (!escaparate) return;

    try {
        const res = await fetch(`${API_URL}/cartas/destacadas`);
        if (!res.ok) throw new Error();
        const { data } = await res.json();
        destacadas = (data || []).filter(c => c.id && (c.imagen_high || c.imagen_low));
        if (!destacadas.length) throw new Error();
    } catch (_) {
        // Sin datos o API caída: el hero se queda solo con el texto,
        // centrado como antes (sin hueco roto)
        escaparate.hidden = true;
        document.querySelector('.hero-showcase')?.classList.add('hero-sin-escaparate');
        return;
    }

    // Precargar las ilustraciones grandes: sin flash blanco al rotar
    destacadas.forEach(c => { new Image().src = c.imagen_high || c.imagen_low; });

    montarControles();
    mostrarCarta(0);
    iniciarAutoplay();
}

// Pinta la carta `nueva` (con slide+fade si hay dirección de
// navegación). .carrusel-carta es el ÚNICO dueño del transform: la
// futura animación de "sobre abriéndose" sustituirá estas clases sin
// tocar la estructura.
function mostrarCarta(nueva, direccion = 0) {
    indiceActivo = (nueva + destacadas.length) % destacadas.length;
    const carta = destacadas[indiceActivo];
    const cont  = document.getElementById('carrusel-carta');

    const pintar = () => {
        const img = new Image();
        img.className = 'carrusel-img';
        img.alt = t('home.altCarta', { nombre: carta.nombre });
        img.src = carta.imagen_high || carta.imagen_low;
        // Imagen muerta: probar la versión ligera; si tampoco, fuera
        // de la rotación (nunca un marco roto en el hero)
        img.addEventListener('error', () => {
            if (carta.imagen_low && img.src !== carta.imagen_low) {
                img.src = carta.imagen_low;
            } else {
                descartarCarta(carta);
            }
        });
        cont.replaceChildren(img);

        // Entra desde el lado contrario al que salió
        if (direccion) {
            cont.classList.add(direccion > 0 ? 'entra-der' : 'entra-izq');
            requestAnimationFrame(() => requestAnimationFrame(() =>
                cont.classList.remove('entra-der', 'entra-izq')));
        }

        aplicarGlow(carta);
        actualizarInfo(carta);
        actualizarPuntos();
    };

    if (direccion && !REDUCIR_MOVIMIENTO.matches) {
        cont.classList.add(direccion > 0 ? 'sale-izq' : 'sale-der');
        setTimeout(() => { cont.classList.remove('sale-izq', 'sale-der'); pintar(); }, 200);
    } else {
        pintar();
    }
}

function actualizarInfo(carta) {
    const enlace = `pages/detalle-carta.html?id=${carta.id}`;

    const info = document.getElementById('hero-carta-info');
    info.hidden = false;
    info.href = enlace;
    document.getElementById('hero-carta-nombre').textContent = carta.nombre;
    const precio = formatearPrecio(carta.precio_cardmarket);
    document.getElementById('hero-carta-meta').textContent =
        [carta.set_expansion, precio].filter(Boolean).join(' · ');

    const escena = document.getElementById('carrusel-escena');
    escena.href = enlace;
    escena.setAttribute('aria-label', t('comun.verDetalleDe', { nombre: carta.nombre }));
}

function actualizarPuntos() {
    document.querySelectorAll('.carrusel-punto').forEach((punto, i) => {
        punto.setAttribute('aria-current', i === indiceActivo ? 'true' : 'false');
    });
}

// Si la única imagen de una carta está muerta se retira de la
// rotación; sin cartas restantes el escaparate desaparece entero
function descartarCarta(carta) {
    destacadas = destacadas.filter(c => c.id !== carta.id);
    if (!destacadas.length) {
        pausarAutoplay();
        document.getElementById('hero-escaparate').hidden = true;
        document.getElementById('hero-carta-info').hidden = true;
        document.querySelector('.hero-showcase')?.classList.add('hero-sin-escaparate');
        return;
    }
    montarPuntos();
    mostrarCarta(indiceActivo % destacadas.length);
}

// ── Controles: flechas, puntos, swipe y pausas del autoplay ──

function montarControles() {
    const prev = document.getElementById('carrusel-prev');
    const next = document.getElementById('carrusel-next');

    if (destacadas.length > 1) {
        prev.hidden = false;
        next.hidden = false;
        document.getElementById('carrusel-puntos').hidden = false;
    }
    prev.addEventListener('click', () => navegar(-1));
    next.addEventListener('click', () => navegar(1));
    montarPuntos();

    // Swipe táctil sobre la carta (umbral 40px). El click posterior a
    // un swipe se anula para no navegar al detalle sin querer.
    const escena = document.getElementById('carrusel-escena');
    let toqueX = null;
    escena.addEventListener('touchstart', (e) => {
        toqueX = e.touches[0].clientX;
        pausarAutoplay();
    }, { passive: true });
    escena.addEventListener('touchend', (e) => {
        if (toqueX === null) return;
        const dx = e.changedTouches[0].clientX - toqueX;
        toqueX = null;
        if (Math.abs(dx) > 40) {
            huboSwipe = true;
            navegar(dx < 0 ? 1 : -1);
        }
        reanudarAutoplay();
    }, { passive: true });
    escena.addEventListener('click', (e) => {
        if (huboSwipe) { e.preventDefault(); huboSwipe = false; }
    });

    // Autoplay en pausa mientras el puntero o el foco estén encima
    for (const zona of [document.getElementById('hero-escaparate'),
                        document.getElementById('hero-carta-info')]) {
        zona.addEventListener('mouseenter', pausarAutoplay);
        zona.addEventListener('mouseleave', reanudarAutoplay);
        zona.addEventListener('focusin',  pausarAutoplay);
        zona.addEventListener('focusout', reanudarAutoplay);
    }
    document.addEventListener('visibilitychange', () =>
        document.hidden ? pausarAutoplay() : reanudarAutoplay());
}

function montarPuntos() {
    const puntos = document.getElementById('carrusel-puntos');
    puntos.innerHTML = destacadas.map((c, i) =>
        `<button type="button" class="carrusel-punto"
                 aria-label="${escapeHtml(t('home.verCartaN', { n: String(i + 1), nombre: c.nombre }))}"></button>`).join('');
    puntos.querySelectorAll('button').forEach((boton, i) =>
        boton.addEventListener('click', () => {
            navegar(Math.sign(i - indiceActivo) || 1, i);
        }));
}

// Navegación manual: mueve la carta y, si el autoplay estaba en
// marcha, reinicia su cuenta para no rotar justo después del click
function navegar(direccion, destino = null) {
    mostrarCarta(destino ?? indiceActivo + direccion, direccion);
    if (autoplay) { pausarAutoplay(); iniciarAutoplay(); }
}

// ── Autoplay ──

function iniciarAutoplay() {
    if (autoplay || REDUCIR_MOVIMIENTO.matches || destacadas.length < 2) return;
    autoplay = setInterval(() => mostrarCarta(indiceActivo + 1, 1), 6500);
}
function pausarAutoplay() {
    clearInterval(autoplay);
    autoplay = null;
}
function reanudarAutoplay() {
    iniciarAutoplay();
}

// ── Glow por color dominante ──
// El CDN de TCGdex sirve CORS (*): un canvas de 4×4 promedia la
// ilustración ligera y el color resultante (con la saturación
// estirada: la media de una carta entera sale grisácea) tiñe el halo
// tras la carta. Si algo falla se queda el ámbar por defecto del CSS.
function aplicarGlow(carta) {
    const escena = document.getElementById('carrusel-escena');
    const pintar = (color) => escena.style.setProperty('--glow-color', color);

    if (coloresGlow.has(carta.id)) {
        pintar(coloresGlow.get(carta.id));
        return;
    }

    pintar('');   // ámbar por defecto mientras se calcula
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.addEventListener('load', () => {
        try {
            const lienzo = document.createElement('canvas');
            lienzo.width = lienzo.height = 4;
            const ctx = lienzo.getContext('2d', { willReadFrequently: true });
            // Solo el 50% central: la ilustración, sin el marco
            // blanco/amarillo de la carta que lava el color
            ctx.drawImage(img,
                img.naturalWidth * 0.25, img.naturalHeight * 0.25,
                img.naturalWidth * 0.5,  img.naturalHeight * 0.5,
                0, 0, 4, 4);
            const px = ctx.getImageData(0, 0, 4, 4).data;

            let r = 0, g = 0, b = 0;
            for (let i = 0; i < px.length; i += 4) {
                r += px[i]; g += px[i + 1]; b += px[i + 2];
            }
            const n = px.length / 4;
            [r, g, b] = [r / n, g / n, b / n];

            const media = (r + g + b) / 3;
            const saturar = (c) =>
                Math.max(0, Math.min(255, Math.round(media + (c - media) * 2.2)));
            const color = `rgba(${saturar(r)}, ${saturar(g)}, ${saturar(b)}, 0.55)`;

            coloresGlow.set(carta.id, color);
            // Solo pintar si esta carta sigue siendo la activa
            if (destacadas[indiceActivo]?.id === carta.id) pintar(color);
        } catch (_) { /* canvas tainted u otro fallo: ámbar fijo */ }
    });
    // La versión ligera puede estar muerta con la grande viva (pasa
    // en sets antiguos): si falla, reintentar con la otra variante
    img.addEventListener('error', () => {
        if (carta.imagen_high && img.src !== carta.imagen_high) {
            img.src = carta.imagen_high;
        }
    });
    img.src = carta.imagen_low || carta.imagen_high;
}

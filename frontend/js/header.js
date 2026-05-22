// ===================================================
// header.js — Inyecta el header y el footer unificados
// de PokeTrade. Módulo ES6.
// La página debe incluir:
//   <div id="app-header"></div>  → donde va el header
//   <div id="app-footer"></div>  → donde va el footer
// ===================================================

import { paginaUrl, estaLogueado, obtenerUsuario, cerrarSesion, renderizarMenu } from './auth.js';

// Rutas calculadas una sola vez (válidas desde la raíz y desde pages/)
const rIndex       = paginaUrl('index.html');
const rNovedades   = paginaUrl('pages/novedades.html');
const rMasVendido  = paginaUrl('pages/mas-vendido.html');
const rCatalogo    = paginaUrl('pages/catalogo.html');
const rMarketplace = paginaUrl('pages/marketplace.html');
const rLogin       = paginaUrl('pages/login.html');
const rRegistro    = paginaUrl('pages/registro.html');
const rInventario  = paginaUrl('pages/inventario.html');
const rTradeos     = paginaUrl('pages/tradeos.html');
const rPerfil      = paginaUrl('pages/perfil.html');
const rPublicar    = paginaUrl('pages/publicar-tradeo.html');
const rAvisoLegal  = paginaUrl('pages/aviso-legal.html');
const rPrivacidad  = paginaUrl('pages/privacidad.html');
const rContacto    = paginaUrl('pages/contacto.html');

// Detectar la página activa para aria-current
const paginaActual = window.location.pathname.split('/').pop() || 'index.html';
function activa(href) {
  return paginaActual === href.split('/').pop() ? ' aria-current="page"' : '';
}

inyectarHeader();
inyectarFooter();

// ───────────────────────────────────────────────────
// HEADER
// ───────────────────────────────────────────────────
function inyectarHeader() {
  const mount = document.getElementById('app-header');
  if (!mount) return;

  mount.outerHTML =
    '<a href="#contenido-principal" class="skip-link">Saltar al contenido principal</a>' +
    '<header>' +
    '<nav aria-label="Navegación principal">' +
    '<a href="' + rIndex + '" class="logo" aria-label="PokeTrade – Inicio">' +
    '<img src="' + paginaUrl('img/favicon.svg') + '" alt="" class="logo-icon" aria-hidden="true" /> PokeTrade</a>' +
    '<ul class="nav-links">' +
    '<li><a href="' + rNovedades + '"' + activa(rNovedades) + '>Novedades</a></li>' +
    '<li><a href="' + rMasVendido + '"' + activa(rMasVendido) + '>Más Vendido</a></li>' +
    '<li><a href="' + rCatalogo + '"' + activa(rCatalogo) + '>Catálogo</a></li>' +
    '<li><a href="' + rMarketplace + '"' + activa(rMarketplace) + '>Marketplace</a></li>' +
    '</ul>' +
    '<div class="buscador-contenedor" role="search">' +
    '<input type="search" id="buscador" placeholder="Buscar carta..." autocomplete="off" aria-label="Buscar carta" />' +
    '<button id="btn-buscar" aria-label="Buscar"><img class="icono" src="' + paginaUrl('img/icons/buscar.svg') + '" alt="" /></button>' +
    '</div>' +
    '<div id="menu-usuario"></div>' +
    '<button id="btn-tema" class="btn-tema" type="button" aria-label="Activar modo oscuro"></button>' +
    '<button id="btn-hamburguesa" class="btn-hamburguesa"' +
    ' aria-label="Abrir menú de navegación" aria-expanded="false" aria-controls="nav-drawer">' +
    '<span></span><span></span><span></span>' +
    '</button>' +
    '</nav>' +
    '</header>' +
    '<div id="drawer-backdrop" class="drawer-backdrop" aria-hidden="true"></div>' +
    '<aside id="nav-drawer" class="nav-drawer" aria-hidden="true" aria-label="Menú de navegación" role="dialog" aria-modal="true">' +
    '<div class="drawer-cabecera">' +
    '<a href="' + rIndex + '" class="logo" aria-label="PokeTrade – Inicio">' +
    '<img src="' + paginaUrl('img/favicon.svg') + '" alt="" class="logo-icon" aria-hidden="true" /> PokeTrade</a>' +
    '<button id="btn-drawer-cerrar" class="drawer-btn-cerrar" aria-label="Cerrar menú">✕</button>' +
    '</div>' +
    '<div class="buscador-contenedor drawer-buscador" role="search">' +
    '<input type="search" id="buscador-drawer" placeholder="Buscar carta..." autocomplete="off" aria-label="Buscar carta" />' +
    '<button id="btn-buscar-drawer" aria-label="Buscar"><img class="icono" src="' + paginaUrl('img/icons/buscar.svg') + '" alt="" /></button>' +
    '</div>' +
    '<nav class="drawer-nav" aria-label="Secciones">' +
    '<ul>' +
    '<li><a href="' + rNovedades + '">Novedades</a></li>' +
    '<li><a href="' + rMasVendido + '">Más Vendido</a></li>' +
    '<li><a href="' + rCatalogo + '">Catálogo</a></li>' +
    '<li><a href="' + rMarketplace + '">Marketplace</a></li>' +
    '</ul>' +
    '</nav>' +
    '<hr class="drawer-hr" />' +
    '<div id="drawer-auth" class="drawer-auth"></div>' +
    '</aside>';

  // El enlace de salto necesita un destino enfocable (WCAG 2.4.1)
  const main = document.querySelector('main');
  if (main) {
    if (!main.id) main.id = 'contenido-principal';
    main.setAttribute('tabindex', '-1');
  }

  // Poblar el menú-usuario del header
  renderizarMenu();

  // Poblar la sección de auth dentro del drawer
  const drawerAuth = document.getElementById('drawer-auth');
  if (drawerAuth) {
    if (estaLogueado()) {
      const u = obtenerUsuario();
      drawerAuth.innerHTML =
        '<div class="drawer-usuario"><img class="icono" src="' + paginaUrl('img/icons/usuario.svg') + '" alt="" /> <span class="drawer-usuario-nombre"></span></div>' +
        '<a href="' + rInventario + '" class="drawer-enlace"><img class="icono" src="' + paginaUrl('img/icons/inventario.svg') + '" alt="" /> Inventario</a>' +
        '<a href="' + rTradeos + '" class="drawer-enlace"><img class="icono" src="' + paginaUrl('img/icons/tradeos.svg') + '" alt="" /> Mis Tradeos</a>' +
        '<a href="' + rPerfil + '" class="drawer-enlace"><img class="icono" src="' + paginaUrl('img/icons/perfil.svg') + '" alt="" /> Perfil</a>' +
        '<button type="button" class="drawer-btn-logout"><img class="icono" src="' + paginaUrl('img/icons/logout.svg') + '" alt="" /> Cerrar sesión</button>';
      // textContent evita inyección de HTML con el nombre del usuario
      drawerAuth.querySelector('.drawer-usuario-nombre').textContent =
        (u && u.nombre) ? u.nombre : 'Usuario';
      drawerAuth.querySelector('.drawer-btn-logout').addEventListener('click', cerrarSesion);
    } else {
      drawerAuth.innerHTML =
        '<a href="' + rLogin + '" class="drawer-enlace">Iniciar sesión</a>' +
        '<a href="' + rRegistro + '" class="btn-primario drawer-btn-registro">Registrarse</a>';
    }
  }

  configurarDrawer();
  configurarBuscadores();
  configurarTema();
}

// ── Lógica del drawer (apertura / cierre) ──────────
function configurarDrawer() {
  const hamburguesa = document.getElementById('btn-hamburguesa');
  const backdrop    = document.getElementById('drawer-backdrop');
  const drawer      = document.getElementById('nav-drawer');
  const btnCerrar   = document.getElementById('btn-drawer-cerrar');

  function abrirDrawer() {
    drawer.classList.add('abierto');
    backdrop.classList.add('visible');
    document.body.classList.add('nav-drawer-abierto');
    hamburguesa.setAttribute('aria-expanded', 'true');
    drawer.setAttribute('aria-hidden', 'false');
    btnCerrar.focus();
  }
  function cerrarDrawer() {
    drawer.classList.remove('abierto');
    backdrop.classList.remove('visible');
    document.body.classList.remove('nav-drawer-abierto');
    hamburguesa.setAttribute('aria-expanded', 'false');
    drawer.setAttribute('aria-hidden', 'true');
    hamburguesa.focus();
  }

  hamburguesa.addEventListener('click', abrirDrawer);
  btnCerrar.addEventListener('click', cerrarDrawer);
  backdrop.addEventListener('click', cerrarDrawer);

  // Cierre con Escape y retención del foco dentro del drawer (WCAG 2.1.2 / 2.4.3)
  document.addEventListener('keydown', (e) => {
    if (!drawer.classList.contains('abierto')) return;
    if (e.key === 'Escape') { cerrarDrawer(); return; }
    if (e.key !== 'Tab') return;
    const f = Array.from(
      drawer.querySelectorAll('a[href], button:not([disabled]), input:not([disabled])')
    ).filter(el => el.getClientRects().length > 0);
    if (!f.length) return;
    const primero = f[0];
    const ultimo  = f[f.length - 1];
    if (e.shiftKey && document.activeElement === primero) {
      e.preventDefault(); ultimo.focus();
    } else if (!e.shiftKey && document.activeElement === ultimo) {
      e.preventDefault(); primero.focus();
    }
  });
  drawer.querySelectorAll('a[href]').forEach((a) => {
    a.addEventListener('click', cerrarDrawer);
  });
}

// ── Buscadores (header + drawer) ───────────────────
function configurarBuscadores() {
  function lanzarBusqueda(termino) {
    const q = encodeURIComponent((termino || '').trim());
    if (!q) return;
    window.location.href = paginaUrl('pages/catalogo.html') + '?q=' + q;
  }
  const bHdr = document.getElementById('buscador');
  const btnH = document.getElementById('btn-buscar');
  const bDrw = document.getElementById('buscador-drawer');
  const btnD = document.getElementById('btn-buscar-drawer');
  if (bHdr && btnH) {
    bHdr.addEventListener('keydown', (e) => { if (e.key === 'Enter') lanzarBusqueda(bHdr.value); });
    btnH.addEventListener('click', () => lanzarBusqueda(bHdr.value));
  }
  if (bDrw && btnD) {
    bDrw.addEventListener('keydown', (e) => { if (e.key === 'Enter') lanzarBusqueda(bDrw.value); });
    btnD.addEventListener('click', () => lanzarBusqueda(bDrw.value));
  }
}

// ── Conmutador de modo claro / oscuro ──────────────
// El tema se guarda en localStorage. Un script inline en el <head>
// de cada página lo aplica antes del primer pintado (sin parpadeo);
// aquí solo sincronizamos el botón y gestionamos el clic.
function configurarTema() {
  const btn = document.getElementById('btn-tema');
  if (!btn) return;

  const ICONO_LUNA = '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8Z"/></svg>';
  const ICONO_SOL  = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4.2"/><path d="M12 2.5v2.4M12 19.1v2.4M4.6 4.6l1.7 1.7M17.7 17.7l1.7 1.7M2.5 12h2.4M19.1 12h2.4M4.6 19.4l1.7-1.7M17.7 6.3l1.7-1.7"/></svg>';

  function aplicar(tema) {
    const oscuro = tema === 'oscuro';
    document.documentElement.setAttribute('data-tema', oscuro ? 'oscuro' : 'claro');
    btn.innerHTML = oscuro ? ICONO_SOL : ICONO_LUNA;
    btn.setAttribute('aria-label', oscuro ? 'Activar modo claro' : 'Activar modo oscuro');
    btn.setAttribute('aria-pressed', oscuro ? 'true' : 'false');
  }

  let guardado = null;
  try { guardado = localStorage.getItem('tema'); } catch (_) {}
  aplicar(guardado === 'oscuro' ? 'oscuro' : 'claro');

  btn.addEventListener('click', () => {
    const oscuro = document.documentElement.getAttribute('data-tema') === 'oscuro';
    const nuevo  = oscuro ? 'claro' : 'oscuro';
    try { localStorage.setItem('tema', nuevo); } catch (_) {}
    aplicar(nuevo);
  });
}

// ───────────────────────────────────────────────────
// FOOTER (mismo footer completo en todas las páginas)
// ───────────────────────────────────────────────────
function inyectarFooter() {
  const mount = document.getElementById('app-footer');
  if (!mount) return;

  mount.outerHTML =
    '<footer>' +
    '<div class="footer-grid">' +
    '<div class="footer-col">' +
    '<h3><img src="' + paginaUrl('img/favicon.svg') + '" alt="" class="logo-icon" aria-hidden="true" /> PokeTrade</h3>' +
    '<p>Plataforma de intercambio de cartas Pokémon para coleccionistas de toda España.</p>' +
    '<p class="footer-sede">IES El Lago · Madrid</p>' +
    '</div>' +
    '<div class="footer-col">' +
    '<h3>Navegación</h3>' +
    '<ul>' +
    '<li><a href="' + rNovedades + '">Novedades</a></li>' +
    '<li><a href="' + rCatalogo + '">Catálogo</a></li>' +
    '<li><a href="' + rMasVendido + '">Más Vendido</a></li>' +
    '<li><a href="' + rPublicar + '">Publicar Tradeo</a></li>' +
    '</ul>' +
    '</div>' +
    '<div class="footer-col">' +
    '<h3>Legal</h3>' +
    '<ul>' +
    '<li><a href="' + rAvisoLegal + '">Aviso Legal</a></li>' +
    '<li><a href="' + rPrivacidad + '">Privacidad</a></li>' +
    '<li><a href="' + rContacto + '">Soporte</a></li>' +
    '</ul>' +
    '</div>' +
    '<div class="footer-col">' +
    '<h3>Soporte</h3>' +
    '<p>poketrade@iesellago.es</p>' +
    '<p class="footer-autores">Daniel Leal &amp; Teo Cristea</p>' +
    '</div>' +
    '</div>' +
    '<div class="footer-bottom">' +
    '<p>&copy; 2026 PokeTrade – Daniel Leal &amp; Teo Cristea. Todos los derechos reservados.</p>' +
    '</div>' +
    '</footer>';
}

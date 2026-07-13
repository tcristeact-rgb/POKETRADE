// ===================================================
// header.js — Inyecta el header y el footer unificados
// de PokeTrade.
// =================================================== 

import { paginaUrl, estaLogueado, obtenerUsuario, cerrarSesion, renderizarMenu } from './auth.js';
import { t, idioma, IDIOMAS, cambiarIdioma, aplicarTraducciones } from './i18n.js';
import { sellarCabecera } from './seo.js';

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

// header.js se carga en TODAS las páginas, así que es el punto natural
// para traducir el HTML estático (los data-i18n del markup). Va antes de
// inyectar nada: el header y el footer ya se construyen traducidos.
aplicarTraducciones(document);

// Y por lo mismo, el punto natural para firmar la cabecera con el canonical y
// los hreflang de esta página (ver seo.js)
sellarCabecera();

inyectarHeader();
inyectarFooter();

// ───────────────────────────────────────────────────
// HEADER
// ───────────────────────────────────────────────────
function inyectarHeader() {
  const mount = document.getElementById('app-header');
  if (!mount) return;

  mount.outerHTML =
    '<a href="#contenido-principal" class="skip-link">' + t('header.saltar') + '</a>' +
    '<header>' +
    '<nav aria-label="' + t('header.navPrincipal') + '">' +
    '<a href="' + rIndex + '" class="logo" aria-label="' + t('header.logoInicio') + '">' +
    '<img src="' + paginaUrl('img/favicon.svg') + '" alt="" class="logo-icon" aria-hidden="true" /> PokeTrade</a>' +
    '<ul class="nav-links">' +
    '<li><a href="' + rNovedades + '"' + activa(rNovedades) + '>' + t('nav.novedades') + '</a></li>' +
    '<li><a href="' + rMasVendido + '"' + activa(rMasVendido) + '>' + t('nav.masVendido') + '</a></li>' +
    '<li><a href="' + rCatalogo + '"' + activa(rCatalogo) + '>' + t('nav.catalogo') + '</a></li>' +
    '<li><a href="' + rMarketplace + '"' + activa(rMarketplace) + '>' + t('nav.marketplace') + '</a></li>' +
    '</ul>' +
    '<div class="buscador-contenedor" role="search">' +
    '<input type="search" id="buscador" placeholder="' + t('header.buscarPlaceholder') + '" autocomplete="off" aria-label="' + t('header.buscarCarta') + '" />' +
    '<button id="btn-buscar" aria-label="' + t('header.buscar') + '"><img class="icono" src="' + paginaUrl('img/icons/buscar.svg') + '" alt="" /></button>' +
    '</div>' +
    '<div id="menu-usuario"></div>' +
    selectorIdiomaHTML() +
    '<button id="btn-tema" class="btn-tema" type="button" aria-label="' + t('header.modoOscuro') + '"></button>' +
    '<button id="btn-hamburguesa" class="btn-hamburguesa"' +
    ' aria-label="' + t('header.abrirMenu') + '" aria-expanded="false" aria-controls="nav-drawer">' +
    '<span></span><span></span><span></span>' +
    '</button>' +
    '</nav>' +
    '</header>' +
    '<div id="drawer-backdrop" class="drawer-backdrop" aria-hidden="true"></div>' +
    '<aside id="nav-drawer" class="nav-drawer" aria-hidden="true" aria-label="' + t('header.menuNav') + '" role="dialog" aria-modal="true">' +
    '<div class="drawer-cabecera">' +
    '<a href="' + rIndex + '" class="logo" aria-label="' + t('header.logoInicio') + '">' +
    '<img src="' + paginaUrl('img/favicon.svg') + '" alt="" class="logo-icon" aria-hidden="true" /> PokeTrade</a>' +
    '<button id="btn-drawer-cerrar" class="drawer-btn-cerrar" aria-label="' + t('header.cerrarMenu') + '">✕</button>' +
    '</div>' +
    '<div class="buscador-contenedor drawer-buscador" role="search">' +
    '<input type="search" id="buscador-drawer" placeholder="' + t('header.buscarPlaceholder') + '" autocomplete="off" aria-label="' + t('header.buscarCarta') + '" />' +
    '<button id="btn-buscar-drawer" aria-label="' + t('header.buscar') + '"><img class="icono" src="' + paginaUrl('img/icons/buscar.svg') + '" alt="" /></button>' +
    '</div>' +
    '<nav class="drawer-nav" aria-label="' + t('header.secciones') + '">' +
    '<ul>' +
    '<li><a href="' + rNovedades + '">' + t('nav.novedades') + '</a></li>' +
    '<li><a href="' + rMasVendido + '">' + t('nav.masVendido') + '</a></li>' +
    '<li><a href="' + rCatalogo + '">' + t('nav.catalogo') + '</a></li>' +
    '<li><a href="' + rMarketplace + '">' + t('nav.marketplace') + '</a></li>' +
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
        '<a href="' + rInventario + '" class="drawer-enlace"><img class="icono" src="' + paginaUrl('img/icons/inventario.svg') + '" alt="" /> ' + t('menu.inventario') + '</a>' +
        '<a href="' + rTradeos + '" class="drawer-enlace"><img class="icono" src="' + paginaUrl('img/icons/tradeos.svg') + '" alt="" /> ' + t('menu.misTradeos') + '</a>' +
        '<a href="' + rPerfil + '" class="drawer-enlace"><img class="icono" src="' + paginaUrl('img/icons/perfil.svg') + '" alt="" /> ' + t('menu.perfil') + '</a>' +
        '<button type="button" class="drawer-btn-logout"><img class="icono" src="' + paginaUrl('img/icons/logout.svg') + '" alt="" /> ' + t('menu.cerrarSesion') + '</button>';

      drawerAuth.querySelector('.drawer-usuario-nombre').textContent =
        (u && u.nombre) ? u.nombre : t('comun.usuario');
      drawerAuth.querySelector('.drawer-btn-logout').addEventListener('click', cerrarSesion);
    } else {
      drawerAuth.innerHTML =
        '<a href="' + rLogin + '" class="drawer-enlace">' + t('menu.iniciarSesion') + '</a>' +
        '<a href="' + rRegistro + '" class="btn-primario drawer-btn-registro">' + t('menu.registrarse') + '</a>';
    }
  }

  configurarDrawer();
  configurarBuscadores();
  configurarTema();
  configurarIdioma();
  configurarScrollCristal();
}

// ── Selector de idioma ─────────────────────────────
// Un <select> nativo: se recorre con teclado, lo anuncian los lectores de
// pantalla y en móvil abre el selector del sistema, todo sin una línea de JS.
// Las opciones salen del registro de i18n.js, así que un idioma nuevo aparece
// aquí sin tocar el header.
//
// Pero un <select> se dimensiona por su opción MÁS LARGA, y "ES · Español" son
// 125 px: era el control más ancho del header —más que el botón de registro— y
// a 1200 px empujaba la barra fuera de la pantalla. Así que se separa lo que se
// VE de lo que FUNCIONA: la píldora enseña el código y ya, y el <select> real va
// encima, transparente, quedándose con toda la interacción. Los nombres
// completos siguen en el desplegable, que es donde se elige.
function selectorIdiomaHTML() {
  const opciones = Object.entries(IDIOMAS)
    .map(([codigo, nombre]) =>
      '<option value="' + codigo + '"' + (codigo === idioma ? ' selected' : '') + '>' + nombre + '</option>')
    .join('');

  return '<div class="selector-idioma">' +
         '<span class="selector-idioma-codigo" aria-hidden="true">' + idioma.toUpperCase() + '</span>' +
         '<select id="selector-idioma" aria-label="' + t('header.idioma') + '">' + opciones + '</select>' +
         '</div>';
}

function configurarIdioma() {
  document.getElementById('selector-idioma')
    ?.addEventListener('change', (e) => cambiarIdioma(e.target.value));
}

// ── Cristal al hacer scroll ────────────────────────
// La píldora hunde su tinte y endurece la sombra al despegarse del
// top (.scrolled solo cambia color y sombra: cero saltos de layout).
function configurarScrollCristal() {
  const header = document.querySelector('header');
  if (!header) return;
  let pendiente = false;
  function actualizar() {
    pendiente = false;
    header.classList.toggle('scrolled', window.scrollY > 8);
  }
  window.addEventListener('scroll', () => {
    if (!pendiente) { pendiente = true; requestAnimationFrame(actualizar); }
  }, { passive: true });
  actualizar();
}

// ── Lógica del drawer ──────────
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

  // Cierre con Escape y retención del foco dentro del drawer
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

// ── Buscadores ───────────────────
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
function configurarTema() {
  const btn = document.getElementById('btn-tema');
  if (!btn) return;

  const ICONO_LUNA = '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8Z"/></svg>';
  const ICONO_SOL  = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4.2"/><path d="M12 2.5v2.4M12 19.1v2.4M4.6 4.6l1.7 1.7M17.7 17.7l1.7 1.7M2.5 12h2.4M19.1 12h2.4M4.6 19.4l1.7-1.7M17.7 6.3l1.7-1.7"/></svg>';

  function aplicar(tema) {
    const oscuro = tema === 'oscuro';
    document.documentElement.setAttribute('data-tema', oscuro ? 'oscuro' : 'claro');
    btn.innerHTML = oscuro ? ICONO_SOL : ICONO_LUNA;
    btn.setAttribute('aria-label', t(oscuro ? 'header.modoClaro' : 'header.modoOscuro'));
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
// FOOTER
// ───────────────────────────────────────────────────
function inyectarFooter() {
  const mount = document.getElementById('app-footer');
  if (!mount) return;

  mount.outerHTML =
    '<footer>' +
    '<div class="footer-grid">' +
    '<div class="footer-col">' +
    '<h3><img src="' + paginaUrl('img/favicon.svg') + '" alt="" class="logo-icon" aria-hidden="true" /> PokeTrade</h3>' +
    '<p>' + t('footer.descripcion') + '</p>' +
    '<p class="footer-sede">' + t('footer.sede') + '</p>' +
    '</div>' +
    '<div class="footer-col">' +
    '<h3>' + t('footer.navegacion') + '</h3>' +
    '<ul>' +
    '<li><a href="' + rNovedades + '">' + t('nav.novedades') + '</a></li>' +
    '<li><a href="' + rCatalogo + '">' + t('nav.catalogo') + '</a></li>' +
    '<li><a href="' + rMasVendido + '">' + t('nav.masVendido') + '</a></li>' +
    '<li><a href="' + rPublicar + '">' + t('footer.publicarTradeo') + '</a></li>' +
    '</ul>' +
    '</div>' +
    '<div class="footer-col">' +
    '<h3>' + t('footer.legal') + '</h3>' +
    '<ul>' +
    '<li><a href="' + rAvisoLegal + '">' + t('footer.avisoLegal') + '</a></li>' +
    '<li><a href="' + rPrivacidad + '">' + t('footer.privacidad') + '</a></li>' +
    '<li><a href="' + rContacto + '">' + t('footer.soporte') + '</a></li>' +
    '</ul>' +
    '</div>' +
    '<div class="footer-col">' +
    '<h3>' + t('footer.soporte') + '</h3>' +
    '<p>poketrade@iesellago.es</p>' +
    '<p class="footer-autores">Daniel Leal &amp; Teo Cristea</p>' +
    '</div>' +
    '</div>' +
    '<div class="footer-bottom">' +
    '<p>' + t('footer.copyright') + '</p>' +
    '</div>' +
    '</footer>';
}

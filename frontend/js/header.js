// ===================================================
// header.js — Inyecta el header unificado de PokeTrade
// Se carga DESPUES de auth.js en todas las páginas.
// La página debe incluir <div id="app-header"></div>
// como placeholder donde se inyectará el header.
// ===================================================

(function () {
  var mount = document.getElementById('app-header');
  if (!mount) return;

  // Helper local en caso de que auth.js no esté cargado todavía
  function ruta(r) {
    if (typeof paginaUrl === 'function') return paginaUrl(r);
    var enPagesDir = window.location.pathname.indexOf('/pages/') !== -1;
    if (enPagesDir) return r.indexOf('pages/') === 0 ? r.replace('pages/', '') : '../' + r;
    return r;
  }

  var rIndex = ruta('index.html');
  var rNovedades = ruta('pages/novedades.html');
  var rMasVendido = ruta('pages/mas-vendido.html');
  var rCatalogo = ruta('pages/catalogo.html');
  var rMarketplace = ruta('pages/marketplace.html');
  var rLogin = ruta('pages/login.html');
  var rRegistro = ruta('pages/registro.html');
  var rInventario = ruta('pages/inventario.html');
  var rTradeos = ruta('pages/tradeos.html');
  var rPerfil = ruta('pages/perfil.html');

  // Detectar la página activa para aria-current
  var paginaActual = window.location.pathname.split('/').pop() || 'index.html';
  function activa(href) {
    return paginaActual === href.split('/').pop() ? ' aria-current="page"' : '';
  }

  mount.outerHTML =
    '<a href="#contenido-principal" class="skip-link">Saltar al contenido principal</a>' +
    '<header>' +
    '<nav aria-label="Navegación principal">' +
    '<a href="' + rIndex + '" class="logo" aria-label="PokeTrade – Inicio">' +
    '<span aria-hidden="true">🃏</span> PokeTrade</a>' +
    '<ul class="nav-links">' +
    '<li><a href="' + rNovedades + '"' + activa(rNovedades) + '>Novedades</a></li>' +
    '<li><a href="' + rMasVendido + '"' + activa(rMasVendido) + '>Más Vendido</a></li>' +
    '<li><a href="' + rCatalogo + '"' + activa(rCatalogo) + '>Catálogo</a></li>' +
    '<li><a href="' + rMarketplace + '"' + activa(rMarketplace) + '>Marketplace</a></li>' +
    '</ul>' +
    '<div class="buscador-contenedor" role="search">' +
    '<input type="search" id="buscador" placeholder="Buscar carta..." autocomplete="off" aria-label="Buscar carta" />' +
    '<button id="btn-buscar" aria-label="Buscar">🔍</button>' +
    '</div>' +
    '<div id="menu-usuario"></div>' +
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
    '<span aria-hidden="true">🃏</span> PokeTrade</a>' +
    '<button id="btn-drawer-cerrar" class="drawer-btn-cerrar" aria-label="Cerrar menú">✕</button>' +
    '</div>' +
    '<div class="buscador-contenedor drawer-buscador" role="search">' +
    '<input type="search" id="buscador-drawer" placeholder="Buscar carta..." autocomplete="off" aria-label="Buscar carta" />' +
    '<button id="btn-buscar-drawer" aria-label="Buscar">🔍</button>' +
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
  var main = document.querySelector('main');
  if (main) {
    if (!main.id) main.id = 'contenido-principal';
    main.setAttribute('tabindex', '-1');
  }

  // Poblar el menú-usuario del header (auth.js lo expone)
  if (typeof renderizarMenu === 'function') renderizarMenu();

  // Poblar la sección de auth dentro del drawer
  var drawerAuth = document.getElementById('drawer-auth');
  if (drawerAuth) {
    if (typeof estaLogueado === 'function' && estaLogueado()) {
      var u = typeof obtenerUsuario === 'function' ? obtenerUsuario() : null;
      drawerAuth.innerHTML =
        '<div class="drawer-usuario">👤 ' + (u && u.nombre ? u.nombre : 'Usuario') + '</div>' +
        '<a href="' + rInventario + '" class="drawer-enlace">📦 Inventario</a>' +
        '<a href="' + rTradeos + '" class="drawer-enlace">🔄 Mis Tradeos</a>' +
        '<a href="' + rPerfil + '" class="drawer-enlace">⚙️ Perfil</a>' +
        '<button class="drawer-btn-logout" onclick="cerrarSesion()">🚪 Cerrar sesión</button>';
    } else {
      drawerAuth.innerHTML =
        '<a href="' + rLogin + '" class="drawer-enlace">Iniciar sesión</a>' +
        '<a href="' + rRegistro + '" class="btn-primario drawer-btn-registro">Registrarse</a>';
    }
  }

  // ── Lógica del drawer (apertura / cierre) ──────────
  var hamburguesa = document.getElementById('btn-hamburguesa');
  var backdrop = document.getElementById('drawer-backdrop');
  var drawer = document.getElementById('nav-drawer');
  var btnCerrar = document.getElementById('btn-drawer-cerrar');

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
  document.addEventListener('keydown', function (e) {
    if (!drawer.classList.contains('abierto')) return;
    if (e.key === 'Escape') { cerrarDrawer(); return; }
    if (e.key !== 'Tab') return;
    var f = Array.prototype.filter.call(
      drawer.querySelectorAll('a[href], button:not([disabled]), input:not([disabled])'),
      function (el) { return el.getClientRects().length > 0; }
    );
    if (!f.length) return;
    var primero = f[0], ultimo = f[f.length - 1];
    if (e.shiftKey && document.activeElement === primero) {
      e.preventDefault(); ultimo.focus();
    } else if (!e.shiftKey && document.activeElement === ultimo) {
      e.preventDefault(); primero.focus();
    }
  });
  drawer.querySelectorAll('a[href]').forEach(function (a) {
    a.addEventListener('click', cerrarDrawer);
  });

  // ── Buscadores (header + drawer) ───────────────────
  function lanzarBusqueda(termino) {
    var q = encodeURIComponent((termino || '').trim());
    if (!q) return;
    // Calculamos la ruta al catálogo desde cualquier página
    var enPages = window.location.pathname.includes('/pages/');
    var urlCatalogo = enPages ? 'catalogo.html' : 'pages/catalogo.html';
    window.location.href = urlCatalogo + '?q=' + q;
  }
  var bHdr = document.getElementById('buscador');
  var btnH = document.getElementById('btn-buscar');
  var bDrw = document.getElementById('buscador-drawer');
  var btnD = document.getElementById('btn-buscar-drawer');
  if (bHdr && btnH) {
    bHdr.addEventListener('keydown', function (e) { if (e.key === 'Enter') lanzarBusqueda(bHdr.value); });
    btnH.addEventListener('click', function () { lanzarBusqueda(bHdr.value); });
  }
  if (bDrw && btnD) {
    bDrw.addEventListener('keydown', function (e) { if (e.key === 'Enter') lanzarBusqueda(bDrw.value); });
    btnD.addEventListener('click', function () { lanzarBusqueda(bDrw.value); });
  }
}());

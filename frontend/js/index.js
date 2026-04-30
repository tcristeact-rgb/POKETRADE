/* ===================================================
   index.js  –  Lógica AJAX de la página de inicio
   Tarea 04 – Desarrollo del Entorno Cliente (Consumidor)

   Flujo AJAX principal documentado:
     1. DOMContentLoaded → comprobar token JWT en localStorage
     2. actualizarMenuUsuario() → modifica el DOM según autenticación
     3. cargarNovedades():
          Petición AJAX (fetch) → Recibir JSON → Actualizar HTML (DOM)
     4. cargarEstadisticas():
          Petición AJAX (fetch) → Recibir JSON → Actualizar stats del hero
     5. Gestión de errores:
          - Error de red (sin conexión / CORS / servidor caído)
          - Códigos HTTP: 401, 403, 404, 422, 500, 503
   =================================================== */

'use strict';

const API_BASE = 'http://localhost:8000/api';

/* ─────────────────────────────────────────────────
   UTILIDADES JWT – localStorage
   Coherencia con el backend: token Bearer en cada petición
───────────────────────────────────────────────── */

/**
 * Devuelve el token JWT guardado en localStorage, o null si no existe.
 * @returns {string|null}
 */
function getToken() {
  return localStorage.getItem('poketrade_token');
}

/**
 * Guarda el token JWT y los datos del usuario en localStorage.
 * @param {string} token   - JWT recibido del backend
 * @param {object} usuario - Objeto usuario { id, nombre, email, ... }
 */
function guardarSesion(token, usuario) {
  localStorage.setItem('poketrade_token', token);
  localStorage.setItem('poketrade_usuario', JSON.stringify(usuario));
}

/**
 * Elimina la sesión del almacenamiento local (logout local).
 */
function eliminarSesion() {
  localStorage.removeItem('poketrade_token');
  localStorage.removeItem('poketrade_usuario');
}

/**
 * Devuelve los datos del usuario guardados en localStorage, o null.
 * @returns {object|null}
 */
function getUsuario() {
  const data = localStorage.getItem('poketrade_usuario');
  try {
    return data ? JSON.parse(data) : null;
  } catch {
    // JSON malformado → limpiar y tratar como no autenticado
    eliminarSesion();
    return null;
  }
}

/* ─────────────────────────────────────────────────
   HELPER FETCH – PETICIÓN AJAX CON MANEJO DE ERRORES HTTP
   ─────────────────────────────────────────────────
   Flujo AJAX documentado (Tarea 04):
     1. Construir cabeceras (con token JWT si existe)
     2. Lanzar fetch()  ← Petición AJAX
     3. Comprobar respuesta.ok → gestionar código HTTP
     4. Parsear JSON     ← Recibir JSON
     5. Devolver datos al llamador → el llamador actualiza el DOM
───────────────────────────────────────────────── */

/**
 * Realiza una petición AJAX añadiendo el token JWT si existe.
 * Centraliza la gestión de errores de red y códigos HTTP.
 *
 * @param {string} endpoint  - Ruta relativa a API_BASE (ej: '/cartas')
 * @param {object} opciones  - Opciones fetch adicionales (method, body…)
 * @returns {Promise<object>} - JSON de respuesta parseado
 * @throws {Error}           - Con prefijo ERROR_RED o HTTP_<código>
 */
async function apiFetch(endpoint, opciones = {}) {
  const token = getToken();

  // Cabeceras: Content-Type + Accept + Authorization Bearer (si hay token)
  const headers = {
    'Content-Type': 'application/json',
    'Accept':       'application/json',
    ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
    ...(opciones.headers || {}),
  };

  let respuesta;

  try {
    // ── PASO 1: Petición AJAX ──────────────────────────────────────────
    respuesta = await fetch(`${API_BASE}${endpoint}`, {
      ...opciones,
      headers,
    });
  } catch (errorRed) {
    // Error de red: sin conexión, servidor caído, problema de CORS…
    throw new Error('ERROR_RED: No se puede conectar con el servidor.');
  }

  // ── PASO 2: Gestión de códigos de estado HTTP ──────────────────────
  if (!respuesta.ok) {
    let mensajeError;

    switch (respuesta.status) {
      case 400:
        mensajeError = 'Solicitud incorrecta. Revisa los datos enviados.';
        break;
      case 401:
        // Token caducado o inválido → limpiar sesión local
        mensajeError = 'No autenticado. Por favor, inicia sesión.';
        eliminarSesion();
        actualizarMenuUsuario();
        break;
      case 403:
        mensajeError = 'No tienes permisos para realizar esta acción.';
        break;
      case 404:
        mensajeError = 'El recurso solicitado no existe.';
        break;
      case 422:
        mensajeError = 'Datos no válidos. Revisa el formulario.';
        break;
      case 500:
        mensajeError = 'Error interno del servidor. Inténtalo más tarde.';
        break;
      case 503:
        mensajeError = 'Servicio no disponible. Inténtalo más tarde.';
        break;
      default:
        mensajeError = `Error inesperado (código ${respuesta.status}).`;
    }

    throw new Error(`HTTP_${respuesta.status}: ${mensajeError}`);
  }

  // ── PASO 3: Parsear JSON de respuesta ─────────────────────────────
  try {
    return await respuesta.json();
  } catch {
    throw new Error('ERROR_JSON: La respuesta del servidor no es JSON válido.');
  }
}

/* ─────────────────────────────────────────────────
   MENÚ DE USUARIO
   Actualiza el DOM según el estado de autenticación (token JWT)
   Prototipo p.2: dropdown con Inventario, Mis Tradeos, Amigos, Perfil, Logout
───────────────────────────────────────────────── */

function actualizarMenuUsuario() {
  const usuario    = getUsuario();
  const menuNoAuth = document.getElementById('menu-no-auth');
  const menuAuth   = document.getElementById('menu-auth');
  const nombreSpan = document.getElementById('nombre-usuario');
  const ctaSeccion = document.getElementById('cta-seccion');
  const btnTradeo  = document.getElementById('btn-hero-tradeo');

  const autenticado = usuario !== null && getToken() !== null;

  if (autenticado) {
    // ── Usuario autenticado ──────────────────────────────────────────
    menuNoAuth.hidden = true;
    menuAuth.hidden   = false;

    // Mostrar nombre o email del usuario en el botón del dropdown
    nombreSpan.textContent = usuario.nombre || usuario.email || 'Usuario';

    // Ocultar sección CTA (ya está registrado, no necesita el banner)
    if (ctaSeccion) ctaSeccion.hidden = true;

    // Botón hero "Publicar tradeo" → página de publicación
    if (btnTradeo) {
      btnTradeo.href  = 'pages/publicar-tradeo.html';
      btnTradeo.title = 'Publicar un nuevo tradeo';
    }
  } else {
    // ── Usuario no autenticado ────────────────────────────────────────
    menuNoAuth.hidden = false;
    menuAuth.hidden   = true;

    // "Publicar tradeo" → redirigir a login si no hay sesión
    if (btnTradeo) {
      btnTradeo.href  = 'pages/login.html';
      btnTradeo.title = 'Inicia sesión para publicar un tradeo';
    }
  }
}

/* ─────────────────────────────────────────────────
   DROPDOWN DE USUARIO
   Toggle abierto/cerrado; se cierra al hacer clic fuera
───────────────────────────────────────────────── */

function initDropdown() {
  const dropdown    = document.querySelector('.dropdown');
  const btnDropdown = document.getElementById('btn-usuario');
  if (!dropdown || !btnDropdown) return;

  // Alternar estado abierto al pulsar el botón
  btnDropdown.addEventListener('click', (e) => {
    e.stopPropagation();
    const abierto = dropdown.classList.toggle('abierto');
    btnDropdown.setAttribute('aria-expanded', abierto ? 'true' : 'false');
  });

  // Cerrar al hacer clic fuera del dropdown
  document.addEventListener('click', () => {
    dropdown.classList.remove('abierto');
    btnDropdown.setAttribute('aria-expanded', 'false');
  });

  // Cerrar con tecla Escape
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      dropdown.classList.remove('abierto');
      btnDropdown.setAttribute('aria-expanded', 'false');
      btnDropdown.focus();
    }
  });
}

/* ─────────────────────────────────────────────────
   CERRAR SESIÓN
   1. Notifica al backend para invalidar el token (POST /auth/logout)
   2. Limpia localStorage independientemente del resultado
   3. Recarga la página
───────────────────────────────────────────────── */

async function cerrarSesion() {
  const token = getToken();

  if (token) {
    try {
      // Petición AJAX al backend para invalidar el token JWT en servidor
      await apiFetch('/auth/logout', { method: 'POST' });
    } catch (_) {
      // Si falla (red, 401…), igualmente limpiamos la sesión local
    }
  }

  eliminarSesion();
  mostrarToast('Sesión cerrada correctamente.', 'ok');

  // Pequeña pausa antes de recargar para que el usuario vea el toast
  setTimeout(() => window.location.reload(), 900);
}

/* ─────────────────────────────────────────────────
   CARGAR NOVEDADES DESDE LA API
   ─────────────────────────────────────────────────
   Flujo AJAX documentado (Tarea 04):
     Petición AJAX → Recibir JSON → Actualizar HTML (DOM)

   Endpoint: GET /api/cartas?limit=8&orden=recientes
   Respuesta esperada: { data: [ {id, nombre, tipo, rareza, set_nombre, imagen_url}, … ] }
   Error de red   → mostrar mensaje "Sin conexión con el servidor"
   Error HTTP 404 → mostrar "Catálogo no disponible"
   Error HTTP 500 → mostrar "Error en el servidor"
───────────────────────────────────────────────── */

async function cargarNovedades() {
  const grid     = document.getElementById('grid-novedades');
  const errorBox = document.getElementById('error-novedades');
  const errorMsg = document.getElementById('error-novedades-msg');

  // Mostrar skeletons de carga mientras esperamos la respuesta
  grid.innerHTML = `
    <div class="carta-card skeleton" aria-hidden="true"></div>
    <div class="carta-card skeleton" aria-hidden="true"></div>
    <div class="carta-card skeleton" aria-hidden="true"></div>
    <div class="carta-card skeleton" aria-hidden="true"></div>
  `;
  grid.setAttribute('aria-busy', 'true');
  errorBox.hidden = true;

  try {
    // ── PASO 1: Petición AJAX ──────────────────────────────────────────
    const datos = await apiFetch('/cartas?limit=8&orden=recientes');

    // ── PASO 2: Procesar JSON recibido ─────────────────────────────────
    // Compatibilidad: la API puede devolver { data: [...] } (paginación Laravel)
    // o directamente un array de cartas
    const cartas = Array.isArray(datos) ? datos : (datos.data ?? []);

    if (cartas.length === 0) {
      grid.innerHTML = '<p class="sin-resultados">No hay novedades disponibles aún.</p>';
      grid.setAttribute('aria-busy', 'false');
      return;
    }

    // ── PASO 3: Actualizar DOM con los datos JSON recibidos ────────────
    grid.innerHTML = cartas.map(carta => crearTarjetaCarta(carta)).join('');
    grid.setAttribute('aria-busy', 'false');

  } catch (error) {
    // ── Gestión de errores: red y códigos HTTP ─────────────────────────
    grid.innerHTML = '';
    grid.setAttribute('aria-busy', 'false');
    errorBox.hidden = false;

    if (error.message.startsWith('ERROR_RED')) {
      errorMsg.textContent = 'Sin conexión con el servidor. ¿Está activo el backend?';
    } else if (error.message.includes('HTTP_404')) {
      errorMsg.textContent = 'El catálogo de cartas no está disponible en este momento.';
    } else if (error.message.includes('HTTP_500') || error.message.includes('HTTP_503')) {
      errorMsg.textContent = 'Error en el servidor. Por favor, inténtalo más tarde.';
    } else if (error.message.includes('HTTP_401')) {
      errorMsg.textContent = 'Sesión expirada. Por favor, inicia sesión de nuevo.';
    } else if (error.message.startsWith('ERROR_JSON')) {
      errorMsg.textContent = 'El servidor devolvió una respuesta inesperada.';
    } else {
      // Eliminar el prefijo técnico antes de mostrarlo al usuario
      errorMsg.textContent = error.message.replace(/^(HTTP_\d+|ERROR_\w+): /, '');
    }

    console.error('[PokeTrade] Error cargando novedades:', error);
  }
}

/**
 * Genera el HTML de una tarjeta de carta a partir del objeto JSON recibido.
 * Prototipo p.3/p.4: imagen + nombre + tipo + rareza + set + botón "+Info"
 *
 * @param {object} carta - { id, nombre, tipo, rareza, set_nombre, imagen_url }
 * @returns {string} HTML de la tarjeta
 */
function crearTarjetaCarta(carta) {
  // Sanitizar contenido para evitar XSS
  const nombre   = escaparHTML(carta.nombre   || 'Sin nombre');
  const tipo     = escaparHTML(carta.tipo     || '');
  const rareza   = escaparHTML(carta.rareza   || '');
  const setNombre= escaparHTML(carta.set_nombre || '');
  const imgUrl   = carta.imagen_url ? escaparAtributo(carta.imagen_url) : '';
  const id       = parseInt(carta.id, 10) || 0;

  const imagenHTML = imgUrl
    ? `<img src="${imgUrl}" alt="Carta ${nombre}" width="120" height="160" loading="lazy" />`
    : `<div class="carta-sin-imagen" aria-hidden="true">🃏</div>`;

  return `
    <article class="carta-card">
      ${imagenHTML}
      <div class="carta-info">
        <h3 title="${nombre}">${nombre}</h3>
        ${tipo     ? `<span class="carta-tipo">${tipo}</span>`          : ''}
        ${rareza   ? `<span class="carta-rareza">${rareza}</span>`       : ''}
        ${setNombre? `<span class="carta-set">${setNombre}</span>`       : ''}
        <button
          class="btn-ver-detalle"
          onclick="window.location.href='pages/detalle-carta.html?id=${id}'"
          aria-label="Ver más información sobre ${nombre}"
        >
          + Info
        </button>
      </div>
    </article>
  `;
}

/* ─────────────────────────────────────────────────
   CARGAR ESTADÍSTICAS DEL HERO
   ─────────────────────────────────────────────────
   Flujo AJAX: GET /api/stats → JSON → actualizar stats en el DOM
   Endpoint:  { total_cartas: number, total_tradeos: number }
   Si falla → quitar skeleton y dejar "—" (decorativo, no bloquea UX)
───────────────────────────────────────────────── */

async function cargarEstadisticas() {
  try {
    // ── Petición AJAX ─────────────────────────────────────────────────
    const stats = await apiFetch('/stats');

    // ── Actualizar DOM con el JSON recibido ───────────────────────────
    const statCartas  = document.querySelector('#stat-cartas  .stat-numero');
    const statTradeos = document.querySelector('#stat-tradeos .stat-numero');

    if (statCartas)  statCartas.textContent  = formatearNumero(stats.total_cartas)  ?? '—';
    if (statTradeos) statTradeos.textContent = formatearNumero(stats.total_tradeos) ?? '—';

  } catch (error) {
    // Las estadísticas son decorativas; si fallan, no bloqueamos la página
    console.warn('[PokeTrade] No se pudieron cargar las estadísticas:', error.message);
  } finally {
    // Siempre quitar el efecto skeleton, haya datos o no
    document.querySelectorAll('.stat-item').forEach(el => el.classList.remove('skeleton'));
  }
}

/* ─────────────────────────────────────────────────
   BUSCADOR
   Prototipo p.2: busca carta o trader → redirige a catálogo con ?q=
───────────────────────────────────────────────── */

function initBuscador() {
  const input    = document.getElementById('buscador');
  const btnBuscar= document.getElementById('btn-buscar');
  if (!input || !btnBuscar) return;

  const ejecutarBusqueda = () => {
    const termino = input.value.trim();
    if (termino.length < 2) {
      input.focus();
      return;
    }
    window.location.href = `pages/catalogo.html?q=${encodeURIComponent(termino)}`;
  };

  btnBuscar.addEventListener('click', ejecutarBusqueda);
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') ejecutarBusqueda();
  });
}

/* ─────────────────────────────────────────────────
   TOAST DE NOTIFICACIONES
   Tipos: 'ok' (verde), 'error' (rojo), 'info' (azul)
───────────────────────────────────────────────── */

function mostrarToast(mensaje, tipo = 'info') {
  const toast = document.getElementById('toast');
  if (!toast) return;

  // Limpiar clases anteriores
  toast.className = 'toast';
  toast.textContent = mensaje;
  toast.hidden = false;

  // Pequeño delay para que la transición CSS funcione correctamente
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      toast.classList.add('visible', `toast-${tipo}`);
    });
  });

  setTimeout(() => {
    toast.classList.remove('visible');
    setTimeout(() => { toast.hidden = true; }, 320);
  }, 3200);
}

/* ─────────────────────────────────────────────────
   UTILIDADES
───────────────────────────────────────────────── */

/**
 * Escapa caracteres HTML para prevenir XSS en innerHTML.
 * @param {string} str
 * @returns {string}
 */
function escaparHTML(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

/**
 * Escapa caracteres especiales para uso en atributos HTML.
 * @param {string} str
 * @returns {string}
 */
function escaparAtributo(str) {
  return str.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

/**
 * Formatea un número con separador de miles (ej: 1234 → "1.234").
 * Devuelve null si el valor no es numérico.
 * @param {*} valor
 * @returns {string|null}
 */
function formatearNumero(valor) {
  const n = parseInt(valor, 10);
  if (isNaN(n)) return null;
  return n.toLocaleString('es-ES');
}

/* ─────────────────────────────────────────────────
   INICIALIZACIÓN
   Se ejecuta cuando el DOM está completamente cargado
───────────────────────────────────────────────── */

document.addEventListener('DOMContentLoaded', () => {

  // 1. Actualizar menú de navegación según estado de autenticación (JWT)
  actualizarMenuUsuario();

  // 2. Inicializar componentes de interfaz de usuario
  initDropdown();
  initBuscador();

  // 3. Evento del botón de cerrar sesión
  const btnLogout = document.getElementById('btn-logout');
  if (btnLogout) btnLogout.addEventListener('click', cerrarSesion);

  // 4. Cargar datos desde la API mediante peticiones AJAX
  //    Flujo: Petición fetch() → Recibir JSON → Actualizar DOM
  cargarNovedades();
  cargarEstadisticas();

  // 5. Botón "Reintentar" en caso de error de red o HTTP
  const btnReintentar = document.getElementById('btn-reintentar-novedades');
  if (btnReintentar) btnReintentar.addEventListener('click', cargarNovedades);
});

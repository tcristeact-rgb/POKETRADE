// ===================================================
// auth.js – Gestión de sesión y autenticación JWT
// Módulo ES6: expone sus funciones mediante `export`.
// La URL de la API se importa desde config.js.
// ===================================================

import { API_URL } from './config.js';

// Se reexporta para que las páginas puedan importarla
// directamente desde este módulo junto al resto de utilidades.
export { API_URL };

// Calcula la ruta relativa correcta según si estamos en pages/ o en la raíz.
// Así las rutas funcionan tanto desde la raíz del proyecto
// como desde la carpeta pages/.
export function paginaUrl(ruta) {
  // ruta es relativa a la raíz de frontend/, ej: 'pages/login.html' o 'index.html'
  const enPagesDir = window.location.pathname.includes('/pages/');
  if (enPagesDir) {
    return ruta.startsWith('pages/') ? ruta.replace('pages/', '') : '../' + ruta;
  }
  return ruta;
}

// ─────────────────────────────────────────────────
// GESTIÓN DEL TOKEN JWT
// ─────────────────────────────────────────────────

export function obtenerToken() {
  return localStorage.getItem('token');
}

export function obtenerUsuario() {
  const datos = localStorage.getItem('usuario');
  return datos ? JSON.parse(datos) : null;
}

export function estaLogueado() {
  return !!obtenerToken();
}

export function guardarSesion(token, usuario) {
  localStorage.setItem('token', token);
  localStorage.setItem('usuario', JSON.stringify(usuario));
}

export function eliminarSesion() {
  localStorage.removeItem('token');
  localStorage.removeItem('usuario');
}

// Devuelve los headers con JWT para rutas protegidas
// Uso: fetch(url, { headers: headersAuth() })
export function headersAuth() {
  return {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'Authorization': `Bearer ${obtenerToken()}`
  };
}

// ─────────────────────────────────────────────────
// PROTECCIÓN DE RUTAS
// Llamar al inicio de cada página que requiera
// sesión activa (inventario, tradeos, perfil…)
// Si no hay token redirige al login inmediatamente
// ─────────────────────────────────────────────────

export function protegerRuta() {
  if (!estaLogueado()) {
    // Guardar la página actual para redirigir después del login
    const paginaActual = window.location.pathname;
    sessionStorage.setItem('redirigir_tras_login', paginaActual);
    window.location.href = paginaUrl('pages/login.html');
  }
}

// ─────────────────────────────────────────────────
// GESTIÓN DE ERRORES HTTP CENTRALIZADA
// Recibe el status HTTP y un elementoId opcional
// para mostrar el mensaje directamente en el DOM
// ─────────────────────────────────────────────────

export function manejarErrorHTTP(status, elementoId = null) {
  let mensaje;

  switch (status) {
    case 401:
      mensaje = 'Sesión expirada. Por favor, inicia sesión de nuevo.';
      eliminarSesion();
      // Redirigir al login si estamos en una página protegida
      if (!window.location.pathname.includes('login')) {
        setTimeout(() => { window.location.href = paginaUrl('pages/login.html'); }, 1500);
      }
      break;
    case 403:
      mensaje = 'No tienes permisos para realizar esta acción.';
      break;
    case 404:
      mensaje = 'El recurso solicitado no existe.';
      break;
    case 422:
      mensaje = 'Datos no válidos. Revisa el formulario.';
      break;
    case 500:
    case 503:
      mensaje = 'Error interno del servidor. Inténtalo más tarde.';
      break;
    default:
      mensaje = `Error inesperado (código ${status}).`;
  }

  // Actualizar el DOM si se proporciona un elemento destino
  if (elementoId) {
    const el = document.getElementById(elementoId);
    if (el) el.textContent = mensaje;
  }

  return mensaje;
}

// ─────────────────────────────────────────────────
// HELPER: parsear respuesta de forma segura
// Evita que respuesta.json() falle si el servidor
// devuelve HTML en lugar de JSON (ej: error 500)
// ─────────────────────────────────────────────────

export async function parsearRespuesta(respuesta) {
  const contentType = respuesta.headers.get('Content-Type') || '';
  if (contentType.includes('application/json')) {
    return await respuesta.json();
  }
  // El servidor devuelve HTML u otro formato — devolvemos objeto vacío
  return {};
}

// ─────────────────────────────────────────────────
// LOGIN
// Petición AJAX → recibe token JWT → guarda sesión
// ─────────────────────────────────────────────────

export async function login(email, password) {
  let respuesta;

  // Capturar error de red por separado (servidor caído, sin conexión)
  try {
    respuesta = await fetch(`${API_URL}/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password })
    });
  } catch (_) {
    throw new Error('Sin conexión con el servidor. ¿Está activo el backend?');
  }

  // Parsear JSON de forma segura antes de comprobar el status
  const datos = await parsearRespuesta(respuesta);

  if (!respuesta.ok) {
    throw new Error(datos.error || manejarErrorHTTP(respuesta.status));
  }

  // Guardar token y datos del usuario en localStorage
  guardarSesion(datos.token, datos.usuario);

  // Redirigir a la página que intentaba visitar antes del login;
  // si no hay ninguna guardada, volvemos al inicio.
  const redirigir = sessionStorage.getItem('redirigir_tras_login');
  if (redirigir) {
    sessionStorage.removeItem('redirigir_tras_login');
    window.location.href = redirigir;
  } else {
    window.location.href = paginaUrl('index.html');
  }

  return datos;
}

// ─────────────────────────────────────────────────
// REGISTRO
// ─────────────────────────────────────────────────

export async function registro(campos) {
  // campos: { nombre, apellido, email, password, fecha_nacimiento, nacionalidad }
  let respuesta;

  // Capturar error de red por separado
  try {
    respuesta = await fetch(`${API_URL}/auth/registro`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(campos)
    });
  } catch (_) {
    throw new Error('Sin conexión con el servidor. ¿Está activo el backend?');
  }

  const datos = await parsearRespuesta(respuesta);

  if (!respuesta.ok) {
    throw new Error(datos.error || manejarErrorHTTP(respuesta.status));
  }

  return datos;
}

// ─────────────────────────────────────────────────
// LOGOUT
// Notifica al backend e invalida el token local
// ─────────────────────────────────────────────────

export async function cerrarSesion() {
  const token = obtenerToken();

  if (token) {
    try {
      await fetch(`${API_URL}/auth/logout`, {
        method: 'POST',
        headers: headersAuth()
      });
    } catch (_) {
      // Si falla el servidor igualmente limpiamos local
    }
  }

  eliminarSesion();
  window.location.href = paginaUrl('index.html');
}

// ─────────────────────────────────────────────────
// RENDERIZAR MENÚ DE USUARIO EN EL HEADER
// La llama header.js tras inyectar el header.
// ─────────────────────────────────────────────────

// El listener para cerrar el dropdown al hacer clic fuera se
// registra una sola vez aunque renderizarMenu() se ejecute
// varias veces (evita acumular listeners en `document`).
let _listenerDropdownRegistrado = false;

function cerrarDropdownFuera(e) {
  const dropdown = document.querySelector('#menu-usuario .dropdown');
  if (dropdown && !dropdown.contains(e.target)) {
    dropdown.classList.remove('abierto');
  }
}

export function renderizarMenu() {
  const menu = document.getElementById('menu-usuario');
  if (!menu) return;

  if (estaLogueado()) {
    const usuario = obtenerUsuario();
    menu.innerHTML = `
      <div class="dropdown">
        <button class="btn-dropdown" type="button" aria-haspopup="true" aria-expanded="false">
          <img class="icono" src="${paginaUrl('img/icons/usuario.svg')}" alt="" /> <span class="btn-dropdown-nombre"></span> ▾
        </button>
        <ul class="dropdown-menu">
          <li><a href="${paginaUrl('pages/inventario.html')}"><img class="icono" src="${paginaUrl('img/icons/inventario.svg')}" alt="" /> Inventario</a></li>
          <li><a href="${paginaUrl('pages/tradeos.html')}"><img class="icono" src="${paginaUrl('img/icons/tradeos.svg')}" alt="" /> Mis Tradeos</a></li>
          <li><a href="${paginaUrl('pages/perfil.html')}"><img class="icono" src="${paginaUrl('img/icons/perfil.svg')}" alt="" /> Perfil</a></li>
          <li><hr/></li>
          <li><button type="button" class="btn-logout"><img class="icono" src="${paginaUrl('img/icons/logout.svg')}" alt="" /> Cerrar sesión</button></li>
        </ul>
      </div>
    `;

    // El nombre se asigna con textContent para evitar inyección de HTML (XSS)
    menu.querySelector('.btn-dropdown-nombre').textContent = usuario?.nombre || 'Usuario';

    const dropdown = menu.querySelector('.dropdown');
    const btnDropdown = menu.querySelector('.btn-dropdown');
    btnDropdown.addEventListener('click', (e) => {
      e.stopPropagation();
      const abierto = dropdown.classList.toggle('abierto');
      btnDropdown.setAttribute('aria-expanded', abierto ? 'true' : 'false');
    });

    menu.querySelector('.btn-logout').addEventListener('click', cerrarSesion);

    if (!_listenerDropdownRegistrado) {
      document.addEventListener('click', cerrarDropdownFuera);
      _listenerDropdownRegistrado = true;
    }

  } else {
    menu.innerHTML = `
      <a href="${paginaUrl('pages/login.html')}">Iniciar sesión</a>
      <a href="${paginaUrl('pages/registro.html')}" class="btn-primario btn-nav">Registrarse</a>
    `;
  }
}

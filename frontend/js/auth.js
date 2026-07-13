// ===================================================
// auth.js – Gestión de sesión y autenticación JWT
// La URL de la API se importa desde config.js.
// ===================================================

import { API_URL } from './config.js';
import { t, idioma } from './i18n.js';

export { API_URL };

// Calcula la ruta relativa correcta según si estamos en pages/ o en la raíz.
export function paginaUrl(ruta) {
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

// ─────────────────────────────────────────────────
// PETICIONES A LA API
// ─────────────────────────────────────────────────

// Único punto por el que salen TODAS las peticiones a la API.
//
// Existe para que el idioma activo viaje siempre en Accept-Language sin que
// haya que acordarse en cada llamada: antes había 32 fetch() sueltos, y el
// día que alguien añada el 33 la cabecera se le habría olvidado. De paso, la
// ruta se escribe relativa ('/cartas') en vez de repetir la plantilla
// `${API_URL}/...` en cada sitio.
//
// El token se añade cuando lo hay: las rutas públicas lo ignoran, así que no
// hace falta distinguir entre peticiones autenticadas y anónimas.
//
// Devuelve la Response tal cual, sin tocarla: quien llama sigue mirando .ok,
// .status y .json() exactamente igual que antes.
export function apiFetch(ruta, opciones = {}) {
  const cabeceras = {
    'Accept': 'application/json',
    'Accept-Language': idioma,
    ...(opciones.body ? { 'Content-Type': 'application/json' } : {}),
    ...opciones.headers,
  };

  const token = obtenerToken();
  if (token) cabeceras['Authorization'] = `Bearer ${token}`;

  return fetch(`${API_URL}${ruta}`, { ...opciones, headers: cabeceras });
}

// ─────────────────────────────────────────────────
// PROTECCIÓN DE RUTAS
// ─────────────────────────────────────────────────

// ─────────────────────────────────────────────────
// VOLVER AL ORIGEN TRAS EL LOGIN
// El destino viaja en sessionStorage y NO en un ?volver= de la URL:
// un parámetro es controlable por cualquiera que difunda un enlace
// (…/login.html?volver=https://sitio-malo) y se convierte en un
// open-redirect para phishing. sessionStorage solo lo escribe nuestro
// código, es del mismo origen y muere al cerrar la pestaña. Aun así se
// valida al leer: solo rutas internas.
// ─────────────────────────────────────────────────

const CLAVE_VOLVER = 'volver_tras_login';
const CLAVE_MOTIVO = 'motivo_login';
const CLAVE_ACCION = 'accion_tras_login';

// Solo rutas internas: fuera absolutas (http://…) y protocol-relative
// (//otro-sitio), que el navegador trataría como externas
function esRutaInterna(valor) {
  return typeof valor === 'string'
      && valor.startsWith('/')
      && !valor.startsWith('//');
}

// Lleva al login recordando de dónde venimos, por qué, y opcionalmente
// la acción que el usuario intentaba hacer (para retomarla al volver).
export function irALogin(motivo = '', accion = null) {
  const origen = window.location.pathname + window.location.search + window.location.hash;

  try {
    sessionStorage.setItem(CLAVE_VOLVER, origen);
    if (motivo) sessionStorage.setItem(CLAVE_MOTIVO, motivo);
    else sessionStorage.removeItem(CLAVE_MOTIVO);
    if (accion) sessionStorage.setItem(CLAVE_ACCION, JSON.stringify(accion));
    else sessionStorage.removeItem(CLAVE_ACCION);
  } catch (_) { /* sin sessionStorage se pierde el retorno, no el login */ }

  window.location.href = paginaUrl('pages/login.html');
}

// Motivo por el que se pidió la sesión (lo muestra la página de login)
export function motivoLogin() {
  try { return sessionStorage.getItem(CLAVE_MOTIVO) || ''; } catch (_) { return ''; }
}

// Acción pendiente que dejó a medias el usuario. Se consume al leerla:
// solo debe retomarse una vez.
export function accionPendiente() {
  try {
    const dato = sessionStorage.getItem(CLAVE_ACCION);
    if (!dato) return null;
    sessionStorage.removeItem(CLAVE_ACCION);
    return JSON.parse(dato);
  } catch (_) {
    return null;
  }
}

export function protegerRuta(motivo = '') {
  if (!estaLogueado()) {
    irALogin(motivo);
  }
}

// ─────────────────────────────────────────────────
// GESTIÓN DE ERRORES HTTP CENTRALIZADA
// ─────────────────────────────────────────────────

// Evita que varias respuestas 401 simultáneas
let _redireccion401EnCurso = false;

export function manejarErrorHTTP(status, elementoId = null) {
  let mensaje;

  switch (status) {
    case 401:
      mensaje = t('error.401');
      // Solo la primera respuesta 401 cierra sesión y redirige
      if (!_redireccion401EnCurso) {
        _redireccion401EnCurso = true;
        eliminarSesion();
        // Redirigir al login si estamos en una página protegida, y
        // recordar dónde estaba para devolverlo tras reautenticarse
        if (!window.location.pathname.includes('login')) {
          setTimeout(() => irALogin('expirada'), 1500);
        }
      }
      break;
    case 403:
      mensaje = t('error.403');
      break;
    case 404:
      mensaje = t('error.404');
      break;
    case 422:
      mensaje = t('error.422');
      break;
    case 500:
    case 503:
      mensaje = t('error.500');
      break;
    default:
      mensaje = t('error.inesperado', { status: String(status) });
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
// devuelve HTML en lugar de JSON
// ─────────────────────────────────────────────────

export async function parsearRespuesta(respuesta) {
  const contentType = respuesta.headers.get('Content-Type') || '';
  if (contentType.includes('application/json')) {
    return await respuesta.json();
  }
  return {};
}

// ─────────────────────────────────────────────────
// LOGIN
// Petición AJAX → recibe token JWT → guarda sesión
// ─────────────────────────────────────────────────

export async function login(email, password) {
  let respuesta;

  try {
    respuesta = await apiFetch('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password })
    });
  } catch (_) {
    throw new Error(t('error.sinConexion'));
  }

  // Parsear JSON de forma segura antes de comprobar el status
  const datos = await parsearRespuesta(respuesta);

  if (!respuesta.ok) {
    throw new Error(datos.error || manejarErrorHTTP(respuesta.status));
  }

  // Guardar token y datos del usuario en localStorage
  guardarSesion(datos.token, datos.usuario);

  // Volvemos exactamente a donde estaba el usuario (con su query: el
  // set que miraba, sus filtros...). La acción pendiente NO se borra
  // aquí: la consume la página de destino al retomarla.
  let destino = null;
  try {
    destino = sessionStorage.getItem(CLAVE_VOLVER);
    sessionStorage.removeItem(CLAVE_VOLVER);
    sessionStorage.removeItem(CLAVE_MOTIVO);
  } catch (_) { /* ignorado: se cae al destino por defecto */ }

  window.location.href = esRutaInterna(destino) ? destino : paginaUrl('index.html');

  return datos;
}

// ─────────────────────────────────────────────────
// REGISTRO
// ─────────────────────────────────────────────────

export async function registro(campos) {
  // campos: { nombre, apellido, email, password, fecha_nacimiento, nacionalidad }
  let respuesta;

  try {
    respuesta = await apiFetch('/auth/registro', {
      method: 'POST',
      body: JSON.stringify(campos)
    });
  } catch (_) {
    throw new Error(t('error.sinConexion'));
  }

  const datos = await parsearRespuesta(respuesta);

  if (!respuesta.ok) {
    throw new Error(datos.error || manejarErrorHTTP(respuesta.status));
  }

  return datos;
}

// ─────────────────────────────────────────────────
// LOGOUT
// ─────────────────────────────────────────────────

export async function cerrarSesion() {
  const token = obtenerToken();

  if (token) {
    try {
      await apiFetch('/auth/logout', { method: 'POST' });
    } catch (_) {
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
          <li><a href="${paginaUrl('pages/inventario.html')}"><img class="icono" src="${paginaUrl('img/icons/inventario.svg')}" alt="" /> ${t('menu.inventario')}</a></li>
          <li><a href="${paginaUrl('pages/tradeos.html')}"><img class="icono" src="${paginaUrl('img/icons/tradeos.svg')}" alt="" /> ${t('menu.misTradeos')}</a></li>
          <li><a href="${paginaUrl('pages/perfil.html')}"><img class="icono" src="${paginaUrl('img/icons/perfil.svg')}" alt="" /> ${t('menu.perfil')}</a></li>
          <li><hr/></li>
          <li><button type="button" class="btn-logout"><img class="icono" src="${paginaUrl('img/icons/logout.svg')}" alt="" /> ${t('menu.cerrarSesion')}</button></li>
        </ul>
      </div>
    `;

    // El nombre se asigna con textContent para evitar inyección de HTML (XSS)
    menu.querySelector('.btn-dropdown-nombre').textContent = usuario?.nombre || t('comun.usuario');

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
      <a href="${paginaUrl('pages/login.html')}">${t('menu.iniciarSesion')}</a>
      <a href="${paginaUrl('pages/registro.html')}" class="btn-primario btn-nav">${t('menu.registrarse')}</a>
    `;
  }
}

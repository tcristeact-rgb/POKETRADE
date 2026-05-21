// ===================================================
// auth.js – Gestión de sesión y autenticación JWT
// Se carga en TODAS las páginas antes que cualquier
// otro script. Expone API_URL como variable global.
// ===================================================

const API_URL = 'http://localhost:8000/api';

// Calcula la ruta relativa correcta según si estamos en pages/ o en la raíz.
// Así las rutas funcionan tanto con Live Server desde la raíz del proyecto
// como desde la carpeta frontend/.
function paginaUrl(ruta) {
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

function obtenerToken() {
  return localStorage.getItem('token');
}

function obtenerUsuario() {
  const datos = localStorage.getItem('usuario');
  return datos ? JSON.parse(datos) : null;
}

function estaLogueado() {
  return !!obtenerToken();
}

function guardarSesion(token, usuario) {
  localStorage.setItem('token', token);
  localStorage.setItem('usuario', JSON.stringify(usuario));
}

function eliminarSesion() {
  localStorage.removeItem('token');
  localStorage.removeItem('usuario');
}

// Devuelve los headers con JWT para rutas protegidas
// Uso: fetch(url, { headers: headersAuth() })
function headersAuth() {
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

function protegerRuta() {
  if (!estaLogueado()) {
    // Guardar la pagina actual para redirigir despues del login
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

function manejarErrorHTTP(status, elementoId = null) {
  let mensaje;

  switch (status) {
    case 401:
      mensaje = 'Sesion expirada. Por favor, inicia sesion de nuevo.';
      eliminarSesion();
      // Redirigir al login si estamos en una pagina protegida
      if (!window.location.pathname.includes('login')) {
        setTimeout(() => { window.location.href = paginaUrl('pages/login.html'); }, 1500);
      }
      break;
    case 403:
      mensaje = 'No tienes permisos para realizar esta accion.';
      break;
    case 404:
      mensaje = 'El recurso solicitado no existe.';
      break;
    case 422:
      mensaje = 'Datos no validos. Revisa el formulario.';
      break;
    case 500:
    case 503:
      mensaje = 'Error interno del servidor. Inténtalo mas tarde.';
      break;
    default:
      mensaje = `Error inesperado (codigo ${status}).`;
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

async function parsearRespuesta(respuesta) {
  const contentType = respuesta.headers.get('Content-Type') || '';
  if (contentType.includes('application/json')) {
    return await respuesta.json();
  }
  // El servidor devuelve HTML u otro formato — devolvemos objeto vacio
  return {};
}

// ─────────────────────────────────────────────────
// LOGIN
// Petición AJAX → recibe token JWT → guarda sesión
// ─────────────────────────────────────────────────

async function login(email, password) {
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

  // Redirigir a la página que intentaba visitar antes del login (si existe)
  const redirigir = sessionStorage.getItem('redirigir_tras_login');
  if (redirigir) {
    sessionStorage.removeItem('redirigir_tras_login');
    window.location.href = redirigir;
  }

  return datos;
}

// ─────────────────────────────────────────────────
// REGISTRO
// ─────────────────────────────────────────────────

async function registro(campos) {
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

async function cerrarSesion() {
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
// Se ejecuta automáticamente al cargar cada página
// ─────────────────────────────────────────────────

function renderizarMenu() {
  const menu = document.getElementById('menu-usuario');
  if (!menu) return;

  if (estaLogueado()) {
    const usuario = obtenerUsuario();
    menu.innerHTML = `
      <div class="dropdown">
        <button class="btn-dropdown" onclick="this.parentElement.classList.toggle('abierto')">
          👤 ${usuario?.nombre || 'Usuario'} ▾
        </button>
        <ul class="dropdown-menu">
          <li><a href="${paginaUrl('pages/inventario.html')}">📦 Inventario</a></li>
          <li><a href="${paginaUrl('pages/tradeos.html')}">🔄 Mis Tradeos</a></li>
          <li><a href="${paginaUrl('pages/perfil.html')}">⚙️ Perfil</a></li>
          <li><hr/></li>
          <li><button onclick="cerrarSesion()">🚪 Cerrar sesión</button></li>
        </ul>
      </div>
    `;

    // Cierra el dropdown al hacer clic fuera
    document.addEventListener('click', function cerrarFuera(e) {
      const dropdown = menu.querySelector('.dropdown');
      if (dropdown && !dropdown.contains(e.target)) {
        dropdown.classList.remove('abierto');
      }
    });

  } else {
    menu.innerHTML = `
      <a href="${paginaUrl('pages/login.html')}">Iniciar sesión</a>
      <a href="${paginaUrl('pages/registro.html')}" class="btn-primario btn-nav">Registrarse</a>
    `;
  }
}

// Ejecutar al cargar cualquier página
renderizarMenu();
// ===================================================
// login.js — Inicio de sesión con envío por formulario
// (Enter nativo), bloqueo del botón para evitar envíos
// duplicados y aviso de registro correcto.
// ===================================================

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('form-login');
  if (form) form.addEventListener('submit', iniciarSesion);

  // Aviso de éxito si el usuario llega tras registrarse
  if (new URLSearchParams(window.location.search).get('registro') === 'ok') {
    const aviso = document.getElementById('aviso-registro');
    if (aviso) aviso.hidden = false;
  }
});

async function iniciarSesion(e) {
  if (e) e.preventDefault();

  const email = document.getElementById('email').value.trim();
  const password = document.getElementById('password').value.trim();
  const errorMensaje = document.getElementById('error-mensaje');
  errorMensaje.textContent = '';

  // Validación mínima con foco en el primer campo vacío
  if (!email || !password) {
    errorMensaje.textContent = 'Introduce tu correo y tu contraseña.';
    document.getElementById(!email ? 'email' : 'password').focus();
    return;
  }

  // Bloqueo del botón: evita envíos duplicados por doble clic
  const boton = document.querySelector('#form-login button[type="submit"]');
  const textoOriginal = boton.textContent;
  boton.disabled = true;
  boton.textContent = 'Entrando…';

  try {
    const respuesta = await fetch(`${API_URL}/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password })
    });

    const datos = await respuesta.json().catch(() => ({}));

    if (!respuesta.ok) {
      errorMensaje.textContent = datos.error || 'Correo o contraseña incorrectos.';
      boton.disabled = false;
      boton.textContent = textoOriginal;
      return;
    }

    guardarSesion(datos.token, datos.usuario);
    window.location.href = '../index.html';
  } catch (error) {
    errorMensaje.textContent = 'Error de conexión con el servidor. Inténtalo de nuevo en unos instantes.';
    boton.disabled = false;
    boton.textContent = textoOriginal;
  }
}

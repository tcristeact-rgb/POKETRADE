// ===================================================
// login.js — Inicio de sesión 
// ===================================================

import { login, motivoLogin } from './auth.js';

// Por qué se le ha pedido la sesión al usuario. El aviso lo da el
// propio login (no una página previa que sería un callejón), y tras
// autenticarse vuelve solo a donde estaba.
const MOTIVOS = {
  aceptar:    'Inicia sesión para aceptar este tradeo.',
  anadir:     'Inicia sesión para añadir la carta a tu inventario.',
  publicar:   'Inicia sesión para publicar un tradeo.',
  inventario: 'Inicia sesión para ver tu inventario.',
  tradeos:    'Inicia sesión para ver tus tradeos.',
  perfil:     'Inicia sesión para ver tu perfil.',
  expirada:   'Tu sesión ha caducado. Vuelve a iniciar sesión para continuar.',
};

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('form-login');
  if (form) form.addEventListener('submit', iniciarSesion);

  // Aviso de éxito si el usuario llega tras registrarse
  if (new URLSearchParams(window.location.search).get('registro') === 'ok') {
    const aviso = document.getElementById('aviso-registro');
    if (aviso) aviso.hidden = false;
  }

  const texto = MOTIVOS[motivoLogin()];
  if (texto) {
    const aviso = document.getElementById('aviso-motivo');
    if (aviso) {
      aviso.textContent = texto;
      aviso.hidden = false;
    }
  }
});

async function iniciarSesion(e) {
  if (e) e.preventDefault();

  const email = document.getElementById('email').value.trim();
  const password = document.getElementById('password').value;
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
    // login() guarda la sesión y redirige automáticamente
    await login(email, password);
  } catch (error) {
    errorMensaje.textContent = error.message || 'Correo o contraseña incorrectos.';
    boton.disabled = false;
    boton.textContent = textoOriginal;
  }
}

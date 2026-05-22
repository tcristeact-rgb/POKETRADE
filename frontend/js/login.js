// ===================================================
// login.js — Inicio de sesión 
// ===================================================

import { login } from './auth.js';

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

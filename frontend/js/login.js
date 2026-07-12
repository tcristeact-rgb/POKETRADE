// ===================================================
// login.js — Inicio de sesión 
// ===================================================

import { login, motivoLogin } from './auth.js';
import { t } from './i18n.js';
import { alCargarDOM } from './utils.js';

// Por qué se le ha pedido la sesión al usuario. El aviso lo da el
// propio login (no una página previa que sería un callejón), y tras
// autenticarse vuelve solo a donde estaba. Los motivos son claves del
// diccionario ('motivo.aceptar'...), no textos.
const MOTIVOS = ['aceptar', 'anadir', 'publicar', 'inventario', 'tradeos', 'perfil', 'expirada'];

alCargarDOM(() => {
  const form = document.getElementById('form-login');
  if (form) form.addEventListener('submit', iniciarSesion);

  // Aviso de éxito si el usuario llega tras registrarse
  if (new URLSearchParams(window.location.search).get('registro') === 'ok') {
    const aviso = document.getElementById('aviso-registro');
    if (aviso) aviso.hidden = false;
  }

  const motivo = motivoLogin();
  if (MOTIVOS.includes(motivo)) {
    const aviso = document.getElementById('aviso-motivo');
    if (aviso) {
      aviso.textContent = t(`motivo.${motivo}`);
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
    errorMensaje.textContent = t('auth.introduceCredenciales');
    document.getElementById(!email ? 'email' : 'password').focus();
    return;
  }

  // Bloqueo del botón: evita envíos duplicados por doble clic
  const boton = document.querySelector('#form-login button[type="submit"]');
  const textoOriginal = boton.textContent;
  boton.disabled = true;
  boton.textContent = t('auth.entrando');

  try {
    // login() guarda la sesión y redirige automáticamente
    await login(email, password);
  } catch (error) {
    errorMensaje.textContent = error.message || t('auth.credencialesIncorrectas');
    boton.disabled = false;
    boton.textContent = textoOriginal;
  }
}

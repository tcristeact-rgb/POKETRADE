// verificar.js — Verificación del correo con el código de 6 dígitos
//
// Llega aquí quien acaba de registrarse (registro.js) o quien intentó iniciar
// sesión con una cuenta sin verificar (auth.js, con ?motivo=login). El email
// viene en la URL; el código lo teclea el usuario. Al verificar, al login.

import { verificarCorreo, reenviarCodigo, manejarErrorHTTP } from './auth.js';
import { t } from './i18n.js';
import { alCargarDOM } from './utils.js';

const ESPERA_REENVIO_S = 60;
let cuentaAtras = null;

alCargarDOM(() => {
  const params = new URLSearchParams(window.location.search);
  const email  = document.getElementById('email');

  if (params.get('email')) email.value = params.get('email');
  if (params.get('motivo') === 'login') {
    document.getElementById('aviso-motivo').hidden = false;
  }

  // El foco al código si el email ya viene puesto
  (email.value ? document.getElementById('codigo') : email).focus();

  document.getElementById('form-verificar').addEventListener('submit', verificar);
  document.getElementById('btn-reenviar').addEventListener('click', reenviar);
});

async function verificar(e) {
  e.preventDefault();
  const email  = document.getElementById('email').value.trim();
  const codigo = document.getElementById('codigo').value.trim();
  const error  = document.getElementById('error-mensaje');
  error.textContent = '';

  if (!/^[0-9]{6}$/.test(codigo)) {
    error.textContent = t('verificar.codigoInvalido');
    return;
  }

  try {
    await verificarCorreo(email, codigo);
    window.location.href = 'login.html?verificado=ok';
  } catch (err) {
    error.textContent = err.message;
  }
}

async function reenviar(e) {
  e.preventDefault();
  const email  = document.getElementById('email').value.trim();
  const error  = document.getElementById('error-mensaje');
  const aviso  = document.getElementById('aviso-reenvio');
  error.textContent = '';

  if (!email) {
    error.textContent = t('verificar.emailNecesario');
    return;
  }

  try {
    const datos = await reenviarCodigo(email);
    aviso.textContent = datos.mensaje || t('verificar.reenviado');
    aviso.hidden = false;
    esperarAntesDeReenviar();
  } catch (err) {
    error.textContent = err.message;
  }
}

// El enlace de reenvío se retira durante un minuto: la API limita los
// reenvíos por email, y el correo tarda unos segundos en llegar de todos modos
function esperarAntesDeReenviar() {
  const boton  = document.getElementById('btn-reenviar');
  const espera = document.getElementById('reenvio-espera');
  let restante = ESPERA_REENVIO_S;

  boton.hidden  = true;
  espera.hidden = false;
  espera.textContent = t('verificar.reenviarEn', { s: String(restante) });

  clearInterval(cuentaAtras);
  cuentaAtras = setInterval(() => {
    restante -= 1;
    if (restante <= 0) {
      clearInterval(cuentaAtras);
      espera.hidden = true;
      boton.hidden  = false;
      return;
    }
    espera.textContent = t('verificar.reenviarEn', { s: String(restante) });
  }, 1000);
}

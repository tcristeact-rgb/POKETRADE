// contacto.js — Formulario de soporte (módulo ES6)
// Al enviar abre el cliente de correo del usuario con los datos
// rellenos. Como alternativa muestra el email directo para copiar.

import { t } from './i18n.js';
import { alCargarDOM } from './utils.js';

alCargarDOM(() => {
  document.getElementById('form-soporte')?.addEventListener('submit', enviarFormulario);
});

function enviarFormulario(e) {
  e.preventDefault();

  const nombre  = document.getElementById('nombre').value.trim();
  const email   = document.getElementById('email').value.trim();
  const asunto  = document.getElementById('asunto').value;
  const mensaje = document.getElementById('mensaje').value.trim();
  const alerta  = document.getElementById('alerta');

  // Validación básica de campos obligatorios
  if (!nombre || !email || !asunto || !mensaje) {
    alerta.className = 'alerta error';
    alerta.textContent = t('sop.rellenaCampos');
    return;
  }

  // Validación básica de formato de email
  if (!email.includes('@') || !email.includes('.')) {
    alerta.className = 'alerta error';
    alerta.textContent = t('sop.correoInvalido');
    return;
  }

  // El correo se redacta en el idioma que el usuario está usando
  const CLAVES_ASUNTO = {
    error:      'sop.asuntoError',
    sugerencia: 'sop.asuntoSugerencia',
    cuenta:     'sop.asuntoCuenta',
    otro:       'sop.asuntoOtro',
  };
  const asuntoTexto = CLAVES_ASUNTO[asunto] ? t(CLAVES_ASUNTO[asunto]) : asunto;

  const cuerpo = t('sop.cuerpoCorreo', { nombre, email, mensaje });

  const mailtoUrl = `mailto:poketrade@iesellago.es`
    + `?subject=${encodeURIComponent(`[PokeTrade] ${asuntoTexto}`)}`
    + `&body=${encodeURIComponent(cuerpo)}`;

  window.location.href = mailtoUrl;

  alerta.className = 'alerta exito';
  alerta.textContent = t('sop.abriendoCliente');
  document.getElementById('form-soporte').reset();

  setTimeout(() => { alerta.className = 'alerta'; alerta.textContent = ''; }, 6000);
}

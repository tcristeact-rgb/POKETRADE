// contacto.js — Formulario de soporte (módulo ES6)
// Al enviar abre el cliente de correo del usuario con los datos
// rellenos. Como alternativa muestra el email directo para copiar.

document.addEventListener('DOMContentLoaded', () => {
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
    alerta.textContent = 'Por favor, rellena todos los campos.';
    return;
  }

  // Validación básica de formato de email
  if (!email.includes('@') || !email.includes('.')) {
    alerta.className = 'alerta error';
    alerta.textContent = 'Por favor, introduce un correo electrónico válido.';
    return;
  }

  // Construimos el asunto y cuerpo del correo
  const asuntoTexto = {
    error: 'Reporte de error',
    sugerencia: 'Sugerencia de mejora',
    cuenta: 'Problema con mi cuenta',
    otro: 'Consulta general',
  }[asunto] || asunto;

  const cuerpo = `Nombre: ${nombre}\nEmail: ${email}\n\n${mensaje}`;

  const mailtoUrl = `mailto:poketrade@iesellago.es`
    + `?subject=${encodeURIComponent(`[PokeTrade] ${asuntoTexto}`)}`
    + `&body=${encodeURIComponent(cuerpo)}`;

  window.location.href = mailtoUrl;

  alerta.className = 'alerta exito';
  alerta.textContent = '¡Abriendo tu cliente de correo! Si no se abre automáticamente, escríbenos a poketrade@iesellago.es';
  document.getElementById('form-soporte').reset();

  setTimeout(() => { alerta.className = 'alerta'; alerta.textContent = ''; }, 6000);
}

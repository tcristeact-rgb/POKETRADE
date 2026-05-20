// ===================================================
// registro.js — Alta de usuario con validación
// preventiva: feedback por campo, ayuda contextual
// y bloqueo del botón para evitar envíos duplicados.
// ===================================================

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('form-registro');
  if (form) form.addEventListener('submit', registrarse);

  // Validación en tiempo real: al abandonar cada campo
  ['nombre', 'apellido', 'email', 'password', 'confirmar'].forEach((id) => {
    const campo = document.getElementById(id);
    if (campo) campo.addEventListener('blur', () => validarCampo(id));
  });

  // La confirmación se revalida mientras se escribe
  const confirmar = document.getElementById('confirmar');
  if (confirmar) confirmar.addEventListener('input', () => validarCampo('confirmar'));
});

// Pinta el estado de un campo (válido / inválido) y su mensaje
function marcarCampo(id, mensaje) {
  const campo = document.getElementById(id);
  const error = document.getElementById('error-' + id);
  const vacio = !campo || campo.value.trim() === '';
  if (campo) {
    campo.classList.toggle('invalido', !!mensaje);
    campo.classList.toggle('valido', !mensaje && !vacio);
    campo.setAttribute('aria-invalid', mensaje ? 'true' : 'false');
  }
  if (error) error.textContent = mensaje || '';
  return !mensaje;
}

// Valida un campo concreto; devuelve true si es correcto
function validarCampo(id) {
  const valor = (document.getElementById(id)?.value || '').trim();
  switch (id) {
    case 'nombre':
    case 'apellido':
      return marcarCampo(id, valor.length < 2 ? 'Introduce al menos 2 caracteres.' : '');
    case 'email':
      return marcarCampo(
        id,
        !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(valor)
          ? 'Introduce un correo válido (ejemplo: nombre@correo.com).'
          : ''
      );
    case 'password':
      return marcarCampo(id, valor.length < 6 ? 'La contraseña debe tener al menos 6 caracteres.' : '');
    case 'confirmar': {
      const pass = (document.getElementById('password')?.value || '').trim();
      return marcarCampo(id, valor !== pass ? 'Las contraseñas no coinciden.' : '');
    }
    default:
      return true;
  }
}

async function registrarse(e) {
  if (e) e.preventDefault();

  const errorMensaje = document.getElementById('error-mensaje');
  errorMensaje.textContent = '';

  // Validación completa antes de enviar
  const campos = ['nombre', 'apellido', 'email', 'password', 'confirmar'];
  let primerError = null;
  campos.forEach((id) => {
    if (!validarCampo(id) && !primerError) primerError = id;
  });

  if (primerError) {
    errorMensaje.textContent = 'Revisa los campos marcados en rojo antes de continuar.';
    document.getElementById(primerError).focus();
    return;
  }

  const datos = {
    nombre: document.getElementById('nombre').value.trim(),
    apellido: document.getElementById('apellido').value.trim(),
    email: document.getElementById('email').value.trim(),
    password: document.getElementById('password').value.trim(),
    fecha_nacimiento: document.getElementById('fecha_nacimiento').value,
    nacionalidad: document.getElementById('nacionalidad').value.trim()
  };

  // Bloqueo del botón: evita envíos duplicados por doble clic
  const boton = document.querySelector('#form-registro button[type="submit"]');
  const textoOriginal = boton.textContent;
  boton.disabled = true;
  boton.textContent = 'Creando cuenta…';

  try {
    const respuesta = await fetch(`${API_URL}/auth/registro`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(datos)
    });

    const resp = await respuesta.json().catch(() => ({}));

    if (!respuesta.ok) {
      errorMensaje.textContent = resp.error || 'No se pudo completar el registro. Revisa tus datos.';
      boton.disabled = false;
      boton.textContent = textoOriginal;
      return;
    }

    // Éxito: avisamos en el login mediante el parámetro ?registro=ok
    window.location.href = 'login.html?registro=ok';
  } catch (error) {
    errorMensaje.textContent = 'Error de conexión con el servidor. Inténtalo de nuevo en unos instantes.';
    boton.disabled = false;
    boton.textContent = textoOriginal;
  }
}

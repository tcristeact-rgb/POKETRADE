// perfil.js — Edición de perfil y cambio de contraseña

import { apiFetch, protegerRuta, obtenerUsuario, manejarErrorHTTP, parsearRespuesta, renderizarMenu, paginaUrl, cambiarPassword as cambiarPasswordApi } from './auth.js';
import { t } from './i18n.js';
import { alCargarDOM, mostrarAlerta } from './utils.js';

protegerRuta('perfil');

alCargarDOM(() => {
    cargarPerfil();

    document.getElementById('avatar_url')?.addEventListener('input', previsualizarAvatar);
    document.getElementById('btn-guardar-perfil')?.addEventListener('click', guardarPerfil);
    document.getElementById('btn-cambiar-password')?.addEventListener('click', cambiarPassword);
});

async function cargarPerfil() {
    try {
        const res = await apiFetch(`/usuario/perfil`);
        if (!res.ok) throw new Error(manejarErrorHTTP(res.status));
        const datos = await res.json();
        rellenarFormulario(datos);
    } catch (e) {
        mostrarAlerta(t('perfil.errorCargar', { mensaje: e.message }), 'error', 'alerta-perfil');
    }
}

function rellenarFormulario(u) {
    document.getElementById('nombre').value           = u.nombre || '';
    document.getElementById('apellido').value         = u.apellido || '';
    document.getElementById('email').value            = u.email || '';
    document.getElementById('nacionalidad').value     = u.nacionalidad || '';
    document.getElementById('fecha_nacimiento').value = u.fecha_nacimiento || '';
    document.getElementById('avatar_url').value       = u.avatar_url || '';
    actualizarAvatar(u.avatar_url);
}

function previsualizarAvatar() {
    actualizarAvatar(document.getElementById('avatar_url').value.trim());
}

function actualizarAvatar(url) {
    const contenedor = document.getElementById('avatar-container');
    contenedor.innerHTML = '';

    if (url) {
        const img = document.createElement('img');
        img.src = url;
        img.alt = t('perfil.avatarAlt');
        img.className = 'avatar-preview';
        img.addEventListener('error', () => {
            contenedor.innerHTML = '<div class="avatar-placeholder" aria-hidden="true"><img class="icono" src="' + paginaUrl('img/icons/usuario.svg') + '" alt="" /></div>';
        });
        contenedor.appendChild(img);
    } else {
        contenedor.innerHTML = '<div class="avatar-placeholder" aria-hidden="true"><img class="icono" src="' + paginaUrl('img/icons/usuario.svg') + '" alt="" /></div>';
    }
}

async function guardarPerfil() {
    const campos = {
        nombre:           document.getElementById('nombre').value.trim(),
        apellido:         document.getElementById('apellido').value.trim(),
        nacionalidad:     document.getElementById('nacionalidad').value.trim(),
        fecha_nacimiento: document.getElementById('fecha_nacimiento').value || null,
        avatar_url:       document.getElementById('avatar_url').value.trim() || null,
    };

    if (!campos.nombre || !campos.apellido) {
        mostrarAlerta(t('perfil.nombreApellidoObligatorios'), 'error', 'alerta-perfil');
        return;
    }

    try {
        const res = await apiFetch(`/usuario/perfil`, {
            method: 'PUT',
            body: JSON.stringify(campos)
        });
        const datos = await parsearRespuesta(res);
        if (!res.ok) throw new Error(datos.error || manejarErrorHTTP(res.status));

        const usuario = obtenerUsuario();
        if (usuario) {
            usuario.nombre = datos.usuario?.nombre || campos.nombre;
            localStorage.setItem('usuario', JSON.stringify(usuario));
            renderizarMenu();
        }

        mostrarAlerta(t('perfil.actualizado'), 'exito', 'alerta-perfil');
    } catch (e) {
        mostrarAlerta(t('comun.error', { mensaje: e.message }), 'error', 'alerta-perfil');
    }
}

async function cambiarPassword() {
    const actual    = document.getElementById('password_actual').value;
    const nueva     = document.getElementById('password_nueva').value;
    const confirmar = document.getElementById('password_confirmar').value;

    if (!actual || !nueva || !confirmar) {
        mostrarAlerta(t('perfil.rellenaPassword'), 'error', 'alerta-password');
        return;
    }
    if (nueva.length < 8) {
        mostrarAlerta(t('perfil.passwordMin8'), 'error', 'alerta-password');
        return;
    }
    if (nueva !== confirmar) {
        mostrarAlerta(t('auth.noCoinciden'), 'error', 'alerta-password');
        return;
    }

    try {
        // auth.js guarda el token nuevo que devuelve el backend: el antiguo ya no vale
        await cambiarPasswordApi(actual, nueva);

        document.getElementById('password_actual').value    = '';
        document.getElementById('password_nueva').value     = '';
        document.getElementById('password_confirmar').value = '';
        mostrarAlerta(t('perfil.passwordCambiada'), 'exito', 'alerta-password');
    } catch (e) {
        mostrarAlerta(t('comun.error', { mensaje: e.message }), 'error', 'alerta-password');
    }
}

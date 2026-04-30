async function registrarse() {
    const nombre = document.getElementById('nombre').value.trim();
    const apellido = document.getElementById('apellido').value.trim();
    const email = document.getElementById('email').value.trim();
    const fecha_nacimiento = document.getElementById('fecha_nacimiento').value;
    const nacionalidad = document.getElementById('nacionalidad').value.trim();
    const password = document.getElementById('password').value.trim();
    const confirmar = document.getElementById('confirmar').value.trim();
    const errorMensaje = document.getElementById('error-mensaje');

    // Validaciones
    if (!nombre || !apellido || !email || !password || !confirmar) {
        errorMensaje.textContent = 'Por favor rellena todos los campos obligatorios';
        return;
    }

    if (password !== confirmar) {
        errorMensaje.textContent = 'Las contraseñas no coinciden';
        return;
    }

    if (password.length < 6) {
        errorMensaje.textContent = 'La contraseña debe tener al menos 6 caracteres';
        return;
    }

    try {
        const respuesta = await fetch(`${API_URL}/auth/registro`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                nombre, apellido, email,
                password, fecha_nacimiento, nacionalidad
            })
        });

        const datos = await respuesta.json();

        if (!respuesta.ok) {
            errorMensaje.textContent = datos.error || 'Error al registrarse';
            return;
        }

        // Redirigir al login
        window.location.href = 'login.html';

    } catch (error) {
        errorMensaje.textContent = 'Error al conectar con el servidor';
    }
}
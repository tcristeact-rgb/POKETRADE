async function iniciarSesion() {
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value.trim();
    const errorMensaje = document.getElementById('error-mensaje');

    if (!email || !password) {
        errorMensaje.textContent = 'Por favor rellena todos los campos';
        return;
    }

    try {
        const respuesta = await fetch(`${API_URL}/auth/login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });

        const datos = await respuesta.json();

        if (!respuesta.ok) {
            errorMensaje.textContent = datos.error || 'Error al iniciar sesión';
            return;
        }

        // Guardar sesion
        guardarSesion(datos.token, datos.usuario);
        window.location.href = '../index.html';

    } catch (error) {
        errorMensaje.textContent = 'Error al conectar con el servidor';
    }
}

// Permitir enviar con Enter
document.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') iniciarSesion();
});
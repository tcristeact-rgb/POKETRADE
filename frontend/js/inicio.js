// Lógica específica de la página de inicio

// Si el usuario ya está logueado, cambiar el botón de publicar tradeo
document.addEventListener('DOMContentLoaded', () => {
    const btnTradeo = document.querySelector('.btn-secundario');
    if (btnTradeo && !estaLogueado()) {
        btnTradeo.href = 'pages/login.html';
    }
});
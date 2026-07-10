// paginacion.js — Paginación numerada reutilizable (módulo ES6)
// Extraída del catálogo para compartirla con la vista de cartas de un
// set. Trabaja con la forma del paginador de Laravel (current_page,
// last_page, total, from, to) y delega la carga de cada página en el
// callback alCambiar(pagina) que le pasa la página que la usa.

export function crearPaginacion({ contenedorId, infoId, alCambiar, sustantivo = 'cartas' }) {
    const contenedor = document.getElementById(contenedorId);
    const info       = infoId ? document.getElementById(infoId) : null;

    let paginaActual = 1;
    let totalPaginas = 1;

    // Listeners delegados sobre el contenedor (los botones se re-crean
    // en cada render, el contenedor no)
    contenedor?.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-pagina]');
        if (btn && !btn.disabled) { irA(Number(btn.dataset.pagina)); return; }
        if (e.target.closest('[data-accion="ir"]')) saltarAPaginaEscrita();
    });

    contenedor?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.id === 'input-ir-pagina') {
            e.preventDefault();
            saltarAPaginaEscrita();
        }
    });

    // Navega a una página válida delegando la carga en el llamador
    function irA(pagina) {
        if (pagina < 1 || pagina > totalPaginas) return;
        alCambiar(pagina);
    }

    // Salta a la página escrita en el campo numérico, acotada al rango
    function saltarAPaginaEscrita() {
        const input = document.getElementById('input-ir-pagina');
        if (!input) return;
        let pagina = parseInt(input.value, 10);
        if (!pagina) return;
        pagina = Math.min(Math.max(1, pagina), totalPaginas);
        irA(pagina);
    }

    // Devuelve el HTML de un botón de paginación accesible
    function botonPagina(pagina, etiqueta, { activa = false, deshabilitado = false } = {}) {
        const aria = activa
            ? ` aria-label="Página ${pagina}, página actual" aria-current="page"`
            : ` aria-label="Ir a página ${pagina}"`;
        return `<button class="btn-pagina${activa ? ' activa' : ''}" type="button"` +
               ` data-pagina="${pagina}"${aria}${deshabilitado ? ' disabled' : ''}>${etiqueta}</button>`;
    }

    // Pinta la barra completa: anterior, ventana de 5 páginas con
    // puntos suspensivos, siguiente y salto directo
    function render(ocultar) {
        if (!contenedor) return;

        if (ocultar || totalPaginas <= 1) { contenedor.innerHTML = ''; return; }

        let inicio = Math.max(1, paginaActual - 2);
        let fin    = Math.min(totalPaginas, inicio + 4);
        if (fin - inicio < 4) inicio = Math.max(1, fin - 4);

        let html = '';

        html += `<button class="btn-pagina" type="button" data-pagina="${paginaActual - 1}" aria-label="Página anterior" ${paginaActual === 1 ? 'disabled' : ''}>← Ant</button>`;

        if (inicio > 1) {
            html += botonPagina(1, '1');
            if (inicio > 2) html += `<span class="paginacion-puntos" aria-hidden="true">…</span>`;
        }

        for (let i = inicio; i <= fin; i++) {
            html += botonPagina(i, String(i), { activa: i === paginaActual });
        }

        if (fin < totalPaginas) {
            if (fin < totalPaginas - 1) html += `<span class="paginacion-puntos" aria-hidden="true">…</span>`;
            html += botonPagina(totalPaginas, String(totalPaginas));
        }

        html += `<button class="btn-pagina" type="button" data-pagina="${paginaActual + 1}" aria-label="Página siguiente" ${paginaActual === totalPaginas ? 'disabled' : ''}>Sig →</button>`;

        // Salto directo: campo para escribir el número de página al que ir
        html += `<span class="paginacion-ir">` +
                `<label for="input-ir-pagina">Ir a página</label>` +
                `<input type="number" id="input-ir-pagina" class="input-ir-pagina"` +
                ` min="1" max="${totalPaginas}" inputmode="numeric" placeholder="${paginaActual}" />` +
                `<button class="btn-pagina" type="button" data-accion="ir"` +
                ` aria-label="Ir a la página escrita">Ir</button>` +
                `</span>`;

        contenedor.innerHTML = html;
    }

    // Sincroniza el componente con la respuesta del paginador de Laravel
    // y actualiza el texto "Mostrando X–Y de Z ..."
    function actualizar(datos) {
        paginaActual = datos.current_page;
        totalPaginas = Math.max(1, datos.last_page);

        render(datos.total === 0);

        if (info) {
            info.textContent =
                `Mostrando ${datos.from ?? 0}–${datos.to ?? 0} de ${datos.total.toLocaleString('es-ES')} ${sustantivo}`;
        }
    }

    return {
        actualizar,
        irA,
        get paginaActual() { return paginaActual; },
        get totalPaginas() { return totalPaginas; },
    };
}

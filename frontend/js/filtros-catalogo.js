// filtros-catalogo.js — Estado de los filtros del catálogo (módulo ES6)
// La URL es la única fuente de verdad: q, tipo y rareza viven como
// query params (compartibles y sobreviven a recargas), y este módulo
// mantiene sincronizados la barra de filtros y la URL en ambos
// sentidos. Sin framework: un objeto con la API mínima que necesita
// catalogo.js.
//
// Los desplegables se pueblan con los tipos y rarezas oficiales del
// TCG (GET /api/cartas/filtros, que los proxea desde TCGdex).

import { API_URL } from './auth.js';
import { debounce } from './utils.js';

// Debounce del input de nombre: la búsqueda global golpea a TCGdex,
// no queremos una petición por tecla
const DEBOUNCE_MS = 400;

export function iniciarFiltros({ alCambiar }) {
    const inputNombre  = document.getElementById('filtro-nombre');
    const selectTipo   = document.getElementById('filtro-tipo');
    const selectRareza = document.getElementById('filtro-rareza');
    const btnLimpiar   = document.getElementById('btn-limpiar-filtros');

    // ── URL → controles (estado inicial) ──────────────
    const params = new URLSearchParams(window.location.search);
    if (inputNombre) inputNombre.value = params.get('q') || '';

    // Los <select> se rellenan en cuanto llegan los valores; el valor
    // de la URL se aplica después de añadir las opciones (antes no
    // existiría la <option> y el navegador lo descartaría)
    cargarOpciones().then(() => {
        if (selectTipo)   selectTipo.value   = params.get('tipo')   || '';
        if (selectRareza) selectRareza.value = params.get('rareza') || '';
        actualizarBotonLimpiar();
    });

    actualizarBotonLimpiar();

    // ── Controles → URL + aviso al catálogo ───────────
    inputNombre?.addEventListener('input', debounce(aplicar, DEBOUNCE_MS));
    selectTipo?.addEventListener('change', aplicar);
    selectRareza?.addEventListener('change', aplicar);
    btnLimpiar?.addEventListener('click', limpiar);

    // El estado SIEMPRE se lee de la URL, no de los controles: en la
    // carga inicial los <select> aún no tienen opciones (llegan por
    // fetch) y leerlos daría un estado vacío aunque la URL traiga
    // ?tipo=Fuego. Los controles solo editan la URL (ver aplicar).
    function actuales() {
        const params = new URLSearchParams(window.location.search);
        return {
            q:      (params.get('q') || '').trim(),
            tipo:   params.get('tipo') || '',
            rareza: params.get('rareza') || '',
        };
    }

    // Valores escritos en los controles (pueden ir por delante de la
    // URL justo antes de aplicar)
    function lecturaControles() {
        return {
            q:      inputNombre?.value.trim() || '',
            tipo:   selectTipo?.value || '',
            rareza: selectRareza?.value || '',
        };
    }

    // Hay filtros "efectivos": tipo o rareza elegidos, o un texto de
    // al menos 2 caracteres (el mínimo que acepta la búsqueda global;
    // con 1 carácter no se dispara nada)
    function hayFiltros() {
        const { q, tipo, rareza } = actuales();
        return q.length >= 2 || tipo !== '' || rareza !== '';
    }

    // Vuelca los filtros activos sobre un URLSearchParams (para
    // propagarlos en los enlaces de serie/set)
    function aplicarSobre(destino) {
        return volcar(actuales(), destino);
    }

    function volcar({ q, tipo, rareza }, destino) {
        ['q', 'tipo', 'rareza'].forEach(p => destino.delete(p));
        if (q)      destino.set('q', q);
        if (tipo)   destino.set('tipo', tipo);
        if (rareza) destino.set('rareza', rareza);
        return destino;
    }

    // Los controles cambiaron: URL al día (sin ensuciar el historial),
    // página de resultados a la 1 y re-render de la vista actual
    function aplicar() {
        const url = new URL(window.location.href);
        volcar(lecturaControles(), url.searchParams);
        url.searchParams.delete('page');
        history.replaceState(null, '', url);

        actualizarBotonLimpiar();
        alCambiar();
    }

    function limpiar() {
        if (inputNombre)  inputNombre.value  = '';
        if (selectTipo)   selectTipo.value   = '';
        if (selectRareza) selectRareza.value = '';
        aplicar();
    }

    // El botón "Limpiar filtros" solo se muestra cuando hay algo puesto
    // (aunque sea 1 carácter todavía no efectivo)
    function actualizarBotonLimpiar() {
        const { q, tipo, rareza } = lecturaControles();
        if (btnLimpiar) btnLimpiar.hidden = q === '' && tipo === '' && rareza === '';
    }

    // Rellena los <select> con los tipos y rarezas del TCG. Si falla no
    // pasa nada: quedan solo con "Todos" y el filtro de nombre funciona
    async function cargarOpciones() {
        try {
            const res = await fetch(`${API_URL}/cartas/filtros`);
            if (!res.ok) return;
            const filtros = await res.json();
            rellenarSelect(selectTipo,   filtros.tipos);
            rellenarSelect(selectRareza, filtros.rarezas);
        } catch (_) { /* los desplegables son secundarios */ }
    }

    function rellenarSelect(select, valores) {
        if (!select || !Array.isArray(valores)) return;
        select.length = 1; // conserva la primera opción ("Todos los ...")
        valores.forEach(v => select.add(new Option(v, v)));
    }

    return { actuales, hayFiltros, aplicarSobre, limpiar };
}

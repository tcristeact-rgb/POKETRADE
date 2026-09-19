// ===================================================
// autocompletado.js – Sugerencias de nombres de carta bajo un buscador
//
// Un solo módulo para los tres buscadores (header, drawer móvil, filtro
// del catálogo). Las sugerencias salen de un índice de nombres únicos
// (GET /api/cartas/nombres, ~25 KB gzip) que se baja UNA vez, al primer
// foco de cualquier buscador de la página, y se filtra en memoria: hay
// sugerencias desde la primera letra y ninguna petición por tecla.
//
// Es un combobox del patrón WAI-ARIA: el input lleva role="combobox" y
// aria-activedescendant apunta a la opción resaltada; la lista es un
// listbox con options. ↓/↑ recorren, Enter elige la resaltada, Escape
// cierra, clic fuera cierra.
// ===================================================

import { apiFetch } from './auth.js';
import { t } from './i18n.js';
import { escapeHtml } from './utils.js';

const MAX_SUGERENCIAS = 8;

// El índice se comparte entre todos los buscadores de la página: una
// promesa, una descarga. Si la API no contesta, se queda en null y el
// buscador sigue funcionando como siempre (sin lista); el siguiente foco
// vuelve a intentarlo.
let indice = null;        // [{ nombre, clave }] — clave: normalizada para comparar
let cargando = null;

async function cargarIndice() {
  if (indice) return indice;
  if (cargando) return cargando;

  cargando = (async () => {
    try {
      const res = await apiFetch('/cartas/nombres');
      if (!res.ok) return null;
      const nombres = await res.json();
      indice = nombres.map((nombre) => ({ nombre, clave: normalizar(nombre) }));
      return indice;
    } catch (_) {
      return null;
    } finally {
      cargando = null;
    }
  })();

  return cargando;
}

// Sin tildes ni mayúsculas: "charizard" encuentra "Charizard ex" y "pikachu"
// encuentra "Pikachu" aunque el usuario escriba "PÍKACHU"
function normalizar(texto) {
  return texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
}

// Relevancia: empieza por el término > alguna palabra empieza por él > lo
// contiene. Dentro de cada grupo se conserva el orden alfabético del índice.
export function sugerir(lista, termino, max = MAX_SUGERENCIAS) {
  const q = normalizar(termino);
  if (!q) return [];

  const prefijo = [], palabra = [], contiene = [];
  for (const { nombre, clave } of lista) {
    if (clave.startsWith(q))            prefijo.push(nombre);
    else if (clave.includes(' ' + q))   palabra.push(nombre);
    else if (clave.includes(q))         contiene.push(nombre);
    if (prefijo.length >= max) break;   // los mejores ya están completos
  }

  return [...prefijo, ...palabra, ...contiene].slice(0, max);
}

let contador = 0;

// input: el <input> de búsqueda. alElegir(nombre): qué hacer al seleccionar
// una sugerencia (el input ya tiene ese nombre como valor cuando se llama).
export function montarAutocompletado(input, { alElegir }) {
  if (!input || input.dataset.autocompletado) return;
  input.dataset.autocompletado = '1';

  const idLista = `autocompletado-${++contador}`;
  const lista = document.createElement('ul');
  lista.id = idLista;
  lista.className = 'autocompletado-lista';
  lista.setAttribute('role', 'listbox');
  lista.setAttribute('aria-label', t('busqueda.sugerenciasAria'));
  lista.hidden = true;
  input.insertAdjacentElement('afterend', lista);

  input.setAttribute('role', 'combobox');
  input.setAttribute('aria-autocomplete', 'list');
  input.setAttribute('aria-expanded', 'false');
  input.setAttribute('aria-controls', idLista);
  input.setAttribute('autocomplete', 'off');

  let opciones = [];
  let activa = -1;

  // Se monta al primer foco, así que el índice se pide ya; si el usuario ya
  // había escrito algo mientras llegaba, se sugiere en cuanto está
  cargarIndice().then(() => {
    if (indice && document.activeElement === input && input.value) abrir();
  });

  function cerrar() {
    lista.hidden = true;
    lista.innerHTML = '';
    opciones = [];
    activa = -1;
    input.setAttribute('aria-expanded', 'false');
    input.removeAttribute('aria-activedescendant');
  }

  function resaltar(i) {
    activa = i;
    lista.querySelectorAll('[role="option"]').forEach((li, n) => {
      const es = n === i;
      li.setAttribute('aria-selected', es ? 'true' : 'false');
      if (es) li.scrollIntoView({ block: 'nearest' });
    });
    if (i >= 0) input.setAttribute('aria-activedescendant', `${idLista}-${i}`);
    else input.removeAttribute('aria-activedescendant');
  }

  function elegir(i) {
    const nombre = opciones[i];
    if (nombre === undefined) return;
    input.value = nombre;
    cerrar();
    alElegir(nombre);
  }

  function abrir() {
    opciones = indice ? sugerir(indice, input.value) : [];
    if (!opciones.length) { cerrar(); return; }

    // La lista se alinea con el input aunque el contenedor sea más ancho
    // (la barra de filtros del catálogo)
    lista.style.left = `${input.offsetLeft}px`;
    lista.style.width = `${input.offsetWidth}px`;

    lista.innerHTML = opciones.map((nombre, i) =>
      `<li id="${idLista}-${i}" role="option" aria-selected="false" class="autocompletado-opcion">${escapeHtml(nombre)}</li>`
    ).join('');
    lista.hidden = false;
    input.setAttribute('aria-expanded', 'true');
    resaltar(-1);
  }

  input.addEventListener('input', () => {
    if (indice) abrir(); else cargarIndice();
  });

  input.addEventListener('keydown', (e) => {
    if (lista.hidden) return;

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      resaltar((activa + 1) % opciones.length);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      resaltar((activa - 1 + opciones.length) % opciones.length);
    } else if (e.key === 'Enter' && activa >= 0) {
      // Elegimos nosotros: que el Enter del buscador no navegue además con
      // el texto a medias
      e.preventDefault();
      e.stopImmediatePropagation();
      elegir(activa);
    } else if (e.key === 'Escape') {
      e.preventDefault();
      cerrar();
    }
  });

  // mousedown y no click: el click llegaría después del blur del input
  lista.addEventListener('mousedown', (e) => {
    const li = e.target.closest('[role="option"]');
    if (!li) return;
    e.preventDefault();
    elegir([...lista.children].indexOf(li));
  });

  document.addEventListener('click', (e) => {
    if (!lista.hidden && e.target !== input && !lista.contains(e.target)) cerrar();
  });
}

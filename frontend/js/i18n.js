// ===================================================
// i18n.js — Núcleo de internacionalización (módulo ES6, sin librerías)
//
// Solo se descarga el diccionario del idioma ACTIVO, con un import()
// dinámico y un await de nivel superior: cualquier módulo que importe
// este fichero se resuelve ya con las traducciones cargadas, así que
// t() nunca devuelve una clave a medio resolver.
//
// Añadir un idioma = crear js/i18n/{codigo}.js y sumar su código a
// IDIOMAS. Todo lo demás (locale BCP-47, nombre para el selector,
// reglas de plural) sale del propio diccionario o de Intl.
// ===================================================

// Registro de idiomas disponibles: código → nombre en su propio idioma
// (es lo que verá cada usuario en el selector, se entienda o no el resto
// de la interfaz). El primero es el de respaldo y el que está escrito
// literalmente en el HTML de las páginas.
export const IDIOMAS = {
    es: 'Español',
    en: 'English',
};

const CODIGOS     = Object.keys(IDIOMAS);
const POR_DEFECTO = CODIGOS[0];
const CLAVE       = 'idioma';

// ── Idioma activo ──────────────────────────────────
// Preferencia guardada → idioma del navegador → respaldo.
function detectar() {
    let guardado = null;
    try { guardado = localStorage.getItem(CLAVE); } catch (_) { /* sin localStorage: al respaldo */ }
    if (CODIGOS.includes(guardado)) return guardado;

    const navegador = (navigator.language || '').slice(0, 2).toLowerCase();
    return CODIGOS.includes(navegador) ? navegador : POR_DEFECTO;
}

export const idioma = detectar();

// El await de nivel superior es lo que garantiza que nadie llame a t()
// antes de tiempo: importar i18n.js ya implica esperar al diccionario.
const dicc = (await import(`./i18n/${idioma}.js`)).default;

// El locale BCP-47 (es-ES, en-GB…) lo declara el propio diccionario:
// es conocimiento del idioma, no del núcleo.
export const locale = dicc._meta.locale;

// Las reglas de plural las pone Intl, no nosotros. Importa: el español
// y el inglés tienen dos categorías (one/other), pero el rumano tiene
// tres (one, few para 2–19, other) y el árabe seis. Un `n === 1 ? a : b`
// escrito a mano funcionaría hoy y se rompería en silencio al añadir el
// tercer idioma.
const plurales = new Intl.PluralRules(locale);

// ── Traducción ─────────────────────────────────────

// t('catalogo.resultados', { n: 25 })
//
// El valor de una clave es un string, o un objeto con una forma por
// categoría de plural ({ one, other, ... }) que se elige con params.n.
//
// Los {marcadores} se sustituyen por params. Los números se formatean
// según el locale (2265 → "2.265" en es, "2,265" en en); si no quieres
// ese agrupamiento (un número de página, por ejemplo), pasa un string.
export function t(clave, params = {}) {
    let valor = dicc[clave];

    // Clave ausente: se devuelve marcada en vez de caer al idioma de
    // respaldo. Un texto sin traducir tiene que cantar, no esconderse.
    if (valor === undefined) return `⟦${clave}⟧`;

    if (typeof valor === 'object') {
        const categoria = plurales.select(Number(params.n) || 0);
        valor = valor[categoria] ?? valor.other;
    }

    return interpolar(valor, params);
}

function interpolar(texto, params) {
    return texto.replace(/\{(\w+)\}/g, (coincidencia, clave) => {
        const valor = params[clave];
        if (valor === undefined) return coincidencia;
        return typeof valor === 'number' ? numero(valor) : valor;
    });
}

// ── Formatos por locale ────────────────────────────

export function numero(valor) {
    return new Intl.NumberFormat(locale).format(valor);
}

// El precio se sigue expresando en EUR en todos los idiomas (la
// plataforma es española y Cardmarket publica en euros): lo que cambia
// es el formato — 12,50 € en es, €12.50 en en.
export function precio(valor) {
    const n = Number(valor);
    if (valor === null || valor === undefined || Number.isNaN(n)) return null;
    return new Intl.NumberFormat(locale, { style: 'currency', currency: 'EUR' }).format(n);
}

export function fecha(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString(locale, {
        day: '2-digit', month: 'short', year: 'numeric',
    });
}

// ── Aplicación al HTML estático ────────────────────

// Recorre `raiz` traduciendo lo marcado en el markup:
//   data-i18n="clave"                         → textContent
//   data-i18n-attr="aria-label:clave;alt:otra" → atributos
//   data-i18n-html="clave"                    → innerHTML
//
// El caso normal es data-i18n y usa textContent, así que un diccionario
// nunca puede inyectar markup por accidente. data-i18n-html queda para
// la prosa que lleva un enlace o un <code> dentro y no se puede partir
// en trozos sin romper el orden de las palabras en otro idioma.
//
// Recibe una raíz (y no siempre `document`) porque el header, el footer
// y los grids se construyen desde JS: hay que poder traducir también el
// DOM que aparece después.
export function aplicarTraducciones(raiz = document) {
    raiz.querySelectorAll('[data-i18n]').forEach((el) => {
        el.textContent = t(el.dataset.i18n);
    });

    raiz.querySelectorAll('[data-i18n-html]').forEach((el) => {
        el.innerHTML = t(el.dataset.i18nHtml);
    });

    raiz.querySelectorAll('[data-i18n-attr]').forEach((el) => {
        el.dataset.i18nAttr.split(';').forEach((par) => {
            const [atributo, clave] = par.split(':').map((s) => s.trim());
            if (atributo && clave) el.setAttribute(atributo, t(clave));
        });
    });

    // El HTML viene escrito en el idioma por defecto, así que a quien
    // navega en otro se le oculta el cuerpo hasta este momento (ver el
    // script de arranque en el <head>): mejor un instante en blanco que
    // ver el texto en español y que cambie de golpe.
    document.documentElement.classList.remove('i18n-pendiente');
    document.documentElement.lang = idioma;
}

// ── Cambio de idioma ───────────────────────────────

// Recarga la página: el DOM lo pintan a medias el HTML estático y varios
// módulos JS con su propio estado (grids, modales, cabeceras de set...).
// Recargar es la forma barata de garantizar que no queda ni un rincón en
// el idioma anterior, y conserva la URL con sus filtros.
export function cambiarIdioma(nuevo) {
    if (!CODIGOS.includes(nuevo) || nuevo === idioma) return;
    try { localStorage.setItem(CLAVE, nuevo); } catch (_) { /* sin persistencia, pero cambia */ }
    window.location.reload();
}

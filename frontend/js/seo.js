// ===================================================
// seo.js — Canonical y hreflang, calculados en el cliente
//
// El MISMO fichero HTML se sirve en dos URLs (/pages/catalogo.html y
// /en/pages/catalogo.html, vía rewrite de Vercel). Eso obliga a calcular estas
// etiquetas aquí y no a escribirlas en el HTML: un canonical estático apuntaría
// las DOS versiones a la española, y Google borraría la inglesa del índice —
// exactamente lo contrario de lo que se busca al hacer i18n en un portfolio.
//
// Este es el segundo cinturón, no el único. El hreflang va también en el
// sitemap.xml, que es estático y no depende de que se ejecute JavaScript.
// Google acepta los dos canales; el sitemap es el que no puede fallar.
// ===================================================

import { IDIOMAS, idioma, urlEnIdioma } from './i18n.js';

// Las páginas marcadas como noindex (404, y los antiguos expansiones/set, que
// solo redirigen) no entran a competir por posicionarse: ni canonical ni
// alternates, que solo servirían para confundir al rastreador.
export function sellarCabecera() {
    if (document.querySelector('meta[name="robots"][content*="noindex"]')) return;

    // Canonical apuntándose a sí misma, CON sus parámetros: ?set=sv03.5 no es la
    // misma página que el índice del catálogo, y merece indexarse aparte.
    enlace('canonical', canonica(idioma));

    // hreflang: "esta página existe en estos idiomas, y aquí está cada una".
    // Cada versión se declara a sí misma además de a las otras — si el grupo no
    // es recíproco, Google lo ignora entero.
    for (const codigo of Object.keys(IDIOMAS)) {
        enlace('alternate', canonica(codigo), codigo);
    }

    // A dónde mandar a quien no habla ninguno de los dos
    enlace('alternate', canonica(Object.keys(IDIOMAS)[0]), 'x-default');
}

// La URL con la que esta página quiere ser indexada en un idioma.
//
// El origen sale de la propia página y no de una constante: así es correcta en
// local, en las previews de Vercel y en producción, sin tocar nada. Y se le
// quita el "index.html" del final, porque la home responde en dos URLs (/ y
// /index.html) y el canonical tiene que quedarse con una sola: si no, Google
// las ve como contenido duplicado y elige él.
function canonica(codigo) {
    const ruta = urlEnIdioma(codigo).replace(/\/index\.html(?=$|\?)/, '/');

    return window.location.origin + ruta;
}

function enlace(rel, href, hreflang = null) {
    const el = document.createElement('link');
    el.rel  = rel;
    el.href = href;
    if (hreflang) el.hreflang = hreflang;
    document.head.appendChild(el);
}

// Genera frontend/sitemap.xml. No es un paso de build: la web sigue sin
// compilarse. Es una herramienta que se ejecuta a mano cuando cambia la lista
// de páginas o la de idiomas:
//
//     node tools/generar-sitemap.mjs
//
// A mano no compensa: con el hreflang recíproco cada URL tiene que declarar
// TODAS las versiones, incluida ella misma (si el grupo no es recíproco, Google
// lo ignora entero), así que N páginas × M idiomas dan N×M bloques con M+1
// enlaces cada uno. Añadir un idioma aquí es una línea; a mano serían diez
// bloques nuevos y veinte enlaces repartidos por el fichero.
import { writeFileSync } from 'node:fs';

const SITIO = 'https://poketrade-beryl.vercel.app';

// Código de idioma → prefijo de URL. El de por defecto NO lleva prefijo: así
// las URLs que ya existían siguen valiendo. Debe coincidir con IDIOMAS en
// frontend/js/i18n.js y con los rewrites de frontend/vercel.json.
const IDIOMAS = {
    es: '',
    en: '/en',
};

const POR_DEFECTO = Object.keys(IDIOMAS)[0];

// Solo las páginas indexables: fuera las que llevan noindex (404, y los
// antiguos expansiones/set, que ya solo redirigen).
//
// La home va como "/" y no como "/index.html": responde en las dos URLs, y hay
// que anunciar una sola o Google las cuenta como contenido duplicado. Es la
// misma que declara el canonical (ver seo.js).
const PAGINAS = [
    '/',
    '/pages/novedades.html',
    '/pages/mas-vendido.html',
    '/pages/catalogo.html',
    '/pages/marketplace.html',
    '/pages/login.html',
    '/pages/registro.html',
    '/pages/aviso-legal.html',
    '/pages/privacidad.html',
    '/pages/contacto.html',
];

const alternativas = (pagina) => [
    ...Object.entries(IDIOMAS).map(([codigo, prefijo]) =>
        `    <xhtml:link rel="alternate" hreflang="${codigo}" href="${SITIO}${prefijo}${pagina}"/>`),
    // A dónde mandar a quien no habla ninguno
    `    <xhtml:link rel="alternate" hreflang="x-default" href="${SITIO}${IDIOMAS[POR_DEFECTO]}${pagina}"/>`,
].join('\n');

const urls = PAGINAS.flatMap((pagina) =>
    Object.values(IDIOMAS).map((prefijo) =>
        `  <url>\n    <loc>${SITIO}${prefijo}${pagina}</loc>\n${alternativas(pagina)}\n  </url>`));

writeFileSync(new URL('../frontend/sitemap.xml', import.meta.url),
`<?xml version="1.0" encoding="UTF-8"?>
<!-- GENERADO por tools/generar-sitemap.mjs — no editar a mano.

     Las dos versiones de cada página, cada una declarando a las demás con
     hreflang. Google lee el hreflang del sitemap sin ejecutar JavaScript, así
     que este es el canal fiable; las etiquetas <link> que inyecta seo.js en la
     cabecera son el refuerzo, no el sustituto. -->
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml">
${urls.join('\n')}
</urlset>
`);

console.log(`frontend/sitemap.xml: ${urls.length} URLs (${PAGINAS.length} páginas × ${Object.keys(IDIOMAS).length} idiomas)`);

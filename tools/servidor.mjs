// Servidor estático para desarrollar el frontend:
//
//     node tools/servidor.mjs      →  http://localhost:5500
//
// Hace lo mismo que Vercel con vercel.json, que es lo que un estático a secas no
// hace y por eso hay que tenerlo aquí:
//
//   - rewrite  /en/(.*)  →  /(.*)     el MISMO fichero servido en dos URLs
//   - header   Content-Language: en   en todo lo que cuelga de /en/
//
// Sin el rewrite, /en/pages/catalogo.html es un 404: la versión inglesa vive en
// esa URL pero el fichero está en pages/catalogo.html. No hay una segunda copia
// que servir, y no la va a haber — ese es justamente el diseño (ver seo.js).
//
// Tampoco hace clean-URLs, a propósito: el 301 de `npx serve` se lleva por
// delante la query, y ?set=sv03.5 llegaba a la app vacío.
//
// Sin dependencias: solo Node.
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const RAIZ   = fileURLToPath(new URL('../frontend', import.meta.url));
const PUERTO = 5500;   // el mismo que Live Server: la API ya lo tiene en su lista de CORS

// Los prefijos de idioma que se reescriben. Lista explícita, no "dos letras
// cualesquiera": /js/ también son dos letras, y con la versión genérica este
// servidor servía /js/header.js como si fuera la home en un idioma llamado "js".
// Debe coincidir con los rewrites de frontend/vercel.json.
const PREFIJOS = ['en'];

const TIPOS = {
    '.html': 'text/html; charset=utf-8',
    '.js':   'text/javascript; charset=utf-8',
    '.css':  'text/css; charset=utf-8',
    '.svg':  'image/svg+xml',
    '.png':  'image/png',
    '.webp': 'image/webp',
    '.ico':  'image/x-icon',
    '.json': 'application/json',
    '.xml':  'application/xml',
    '.txt':  'text/plain; charset=utf-8',
};

http.createServer((req, res) => {
    let ruta = decodeURIComponent(req.url.split('?')[0]);

    // El rewrite
    const prefijo = PREFIJOS.find((p) => ruta === `/${p}` || ruta.startsWith(`/${p}/`));
    if (prefijo) ruta = ruta.slice(prefijo.length + 1) || '/';

    if (ruta.endsWith('/')) ruta += 'index.html';

    const archivo = path.join(RAIZ, ruta);

    // Que nadie se salga de frontend/ con un ../
    if (!archivo.startsWith(RAIZ) || !fs.existsSync(archivo) || fs.statSync(archivo).isDirectory()) {
        res.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' });
        res.end(`404 · no existe ${ruta}`);
        return;
    }

    const cabeceras = { 'Content-Type': TIPOS[path.extname(archivo)] ?? 'application/octet-stream' };
    if (prefijo) cabeceras['Content-Language'] = prefijo;

    res.writeHead(200, cabeceras);
    fs.createReadStream(archivo).pipe(res);
}).listen(PUERTO, () => {
    console.log(`PokeTrade en http://localhost:${PUERTO}`);
    console.log(`         y en http://localhost:${PUERTO}/en/   (la misma web, en inglés)`);
});

// ===================================================
// espera.js — Cuando el servidor está dormido, decirlo
//
// La API vive en el plan gratuito de Render, que apaga el servicio tras 15
// minutos sin tráfico. La primera petición después de eso tiene que esperar a que
// el contenedor entero se levante: puede irse a un minuto. No es un fallo, y no
// hay parche de código que lo evite — es lo que se compra con un plan gratuito.
//
// Lo que sí es un fallo es dejar al visitante mirando un esqueleto mudo durante
// ese minuto, sin saber si la web está rota. Así que a los 3 segundos se le
// explica lo que pasa, con un reloj para que vea que aquello avanza.
//
// Esto envuelve a apiFetch, que es el único sitio por el que salen las peticiones
// (ver auth.js): cubre la aplicación entera sin que ninguna vista se entere.
// ===================================================

import { t } from './i18n.js';

// A partir de aquí, una petición normal ya debería haber vuelto: si no ha
// vuelto, es que el servidor está despertando
const UMBRAL_AVISO = 3000;

// Un arranque en frío de Render se va a ~60 s. 90 da margen sin dejar a nadie
// colgado para siempre. (Sin esto, el navegador espera sus propios ~300 s.)
const TIMEOUT = 90000;

const PAUSA_REINTENTO = 2000;

// Códigos que devuelve el proxy de Render mientras el contenedor todavía no
// escucha en el puerto. No son errores de la aplicación: son "aún no está"
const PUERTA_CERRADA = [502, 503, 504];

let enVuelo      = 0;
let inicio       = 0;
let temporizador = null;
let reloj        = null;
let aviso        = null;

// Envuelve una petición con aviso, timeout y —si es segura de repetir— un
// reintento. `hacerPeticion(signal)` tiene que devolver la promesa de un fetch.
export async function conEspera(hacerPeticion, { reintentable }) {
    empezar();

    try {
        return await intentar(hacerPeticion, reintentable);
    } finally {
        terminar();
    }
}

async function intentar(hacerPeticion, reintentable) {
    let respuesta;

    try {
        respuesta = await conTimeout(hacerPeticion);
    } catch (error) {
        // Ni siquiera hubo respuesta. Si la petición es segura de repetir, se
        // repite una vez: mientras Render levanta el contenedor, la conexión se
        // cae en seco y el segundo intento suele pillarlo ya en pie.
        //
        // Un timeout nuestro NO se reintenta: si en 90 s no ha contestado, darle
        // otros 90 es hacer esperar al visitante tres minutos para nada.
        if (!reintentable || error.name === 'AbortError') throw error;

        await pausa(PAUSA_REINTENTO);
        return conTimeout(hacerPeticion);
    }

    if (reintentable && PUERTA_CERRADA.includes(respuesta.status)) {
        await pausa(PAUSA_REINTENTO);
        return conTimeout(hacerPeticion);
    }

    return respuesta;
}

function conTimeout(hacerPeticion) {
    const abortador = new AbortController();
    const corte     = setTimeout(() => abortador.abort(), TIMEOUT);

    return hacerPeticion(abortador.signal).finally(() => clearTimeout(corte));
}

const pausa = (ms) => new Promise((listo) => setTimeout(listo, ms));

// ── El aviso ───────────────────────────────────────
// El contador es global: si la home dispara dos peticiones a la vez, el aviso
// aparece una sola vez y se va cuando han vuelto las dos.

function empezar() {
    if (enVuelo === 0) {
        inicio       = Date.now();
        temporizador = setTimeout(mostrar, UMBRAL_AVISO);
    }

    enVuelo++;
}

function terminar() {
    enVuelo--;

    if (enVuelo > 0) return;

    clearTimeout(temporizador);
    clearInterval(reloj);

    if (aviso) aviso.hidden = true;
    document.documentElement.classList.remove('servidor-despertando');
}

function mostrar() {
    if (!aviso) aviso = construir();

    aviso.hidden = false;

    // La clase la mira el CSS para callar los avisos de carga de cada página
    // (el del catálogo, por ejemplo): dos mensajes de espera a la vez no
    // informan el doble, confunden el doble.
    document.documentElement.classList.add('servidor-despertando');

    marcarReloj();
    reloj = setInterval(marcarReloj, 1000);
}

// Se construye con nodos y textContent, no con innerHTML: por seguridad, y
// porque escapeHtml vive en utils.js, que importa auth.js, que importa esto —
// tirar de él aquí montaría un ciclo de imports.
function construir() {
    const caja = document.createElement('div');
    caja.className = 'aviso-despertar';
    caja.setAttribute('role', 'status');
    caja.setAttribute('aria-live', 'polite');
    caja.hidden = true;

    const rueda = document.createElement('span');
    rueda.className = 'aviso-despertar-rueda';
    rueda.setAttribute('aria-hidden', 'true');

    const texto = document.createElement('div');
    texto.className = 'aviso-despertar-texto';

    const titulo = document.createElement('strong');
    titulo.textContent = t('espera.titulo');

    const explicacion = document.createElement('p');
    explicacion.textContent = t('espera.texto');

    texto.append(titulo, explicacion);

    const segundos = document.createElement('span');
    segundos.className = 'aviso-despertar-reloj';

    caja.append(rueda, texto, segundos);
    document.body.appendChild(caja);

    return caja;
}

// El reloj no es decoración: sin él, un spinner girando es indistinguible de una
// web colgada. Con él se ve que el tiempo corre y cuánto lleva.
function marcarReloj() {
    const s = Math.round((Date.now() - inicio) / 1000);

    aviso.querySelector('.aviso-despertar-reloj').textContent = t('espera.segundos', { n: s });
}

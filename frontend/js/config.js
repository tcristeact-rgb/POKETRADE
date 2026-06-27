// ===================================================
// config.js — Configuración global de la aplicación
// Centraliza la URL base de la API.
//
// La URL se resuelve en este orden:
//   1. window.POKETRADE_API_URL → si está definida, tiene prioridad
//      (override manual para apuntar a otro backend sin tocar código).
//   2. Según el dominio:
//        - localhost / 127.0.0.1 → backend local (desarrollo).
//        - cualquier otro dominio (Vercel) → backend en Render (producción).
// ===================================================

// En local servimos el frontend con Live Server o `npx serve`, que usan
// localhost o 127.0.0.1; cualquier otro host se considera producción.
const esLocal = ['localhost', '127.0.0.1'].includes(location.hostname);

export const API_URL =
  (typeof window !== 'undefined' && window.POKETRADE_API_URL) ||
  (esLocal
    ? 'http://localhost:8000/api'
    : 'https://poketrade-api-3nwm.onrender.com/api');

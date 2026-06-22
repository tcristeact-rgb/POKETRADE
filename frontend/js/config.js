// ===================================================
// config.js — Configuración global de la aplicación
// Centraliza la URL base de la API.
//
// La URL se resuelve en este orden:
//   1. window.POKETRADE_API_URL → si está definida, tiene prioridad.
//   2. Fallback a localhost      → para desarrollo local.
//
// En PRODUCCIÓN (frontend en Vercel) NO hace falta tocar este archivo:
// basta con definir window.POKETRADE_API_URL apuntando al backend de Render
// ANTES de cargar los módulos JS. Por ejemplo, en el <head> del HTML, antes
// del <script type="module">:
//
//   <script>window.POKETRADE_API_URL = 'https://poketrade-api.onrender.com/api';</script>
// ===================================================

export const API_URL =
  (typeof window !== 'undefined' && window.POKETRADE_API_URL) ||
  'http://localhost:8000/api';

// ===================================================
// config.js — Configuración global de la aplicación
// Centraliza la URL base de la API
// ===================================================

export const API_URL =
  (typeof window !== 'undefined' && window.POKETRADE_API_URL) ||
  'http://localhost:8000/api';

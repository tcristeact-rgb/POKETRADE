// ===================================================
// config.js — Configuración global de la aplicación
// Centraliza la URL base de la API para no tenerla
// repartida ("hardcodeada") por el resto de módulos.
//
// Para apuntar a otro servidor (producción, despliegue
// en otra máquina…), define window.POKETRADE_API_URL
// en el HTML antes de cargar los módulos, por ejemplo:
//   <script>window.POKETRADE_API_URL = 'https://mi-api.com/api';</script>
// ===================================================

export const API_URL =
  (typeof window !== 'undefined' && window.POKETRADE_API_URL) ||
  'http://localhost:8000/api';

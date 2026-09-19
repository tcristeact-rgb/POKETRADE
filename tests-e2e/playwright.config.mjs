// Suite mínima de extremo a extremo: los caminos críticos de PokeTrade contra
// la API (php artisan serve) y el frontend (node tools/servidor.mjs) locales.
//
// Playwright arranca los dos servidores él mismo (webServer) y espera a que
// respondan antes del primer test; si ya están levantados, los reutiliza.
// Un solo worker: los tests comparten la BD de desarrollo y la caché del hero.
import { defineConfig, devices } from '@playwright/test';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const raiz = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

export default defineConfig({
  testDir: './tests',
  globalSetup: './global-setup.mjs',
  workers: 1,
  fullyParallel: false,
  retries: 0,
  // La primera visita a un set no cacheado y el hero pueden salir a TCGdex.
  timeout: 60_000,
  expect: { timeout: 15_000 },
  reporter: [['list']],
  use: {
    baseURL: 'http://localhost:5500',
    locale: 'es-ES',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
  webServer: [
    {
      command: 'php artisan serve --host 127.0.0.1 --port 8000',
      cwd: path.join(raiz, 'api'),
      url: 'http://127.0.0.1:8000/api/health',
      reuseExistingServer: true,
      timeout: 30_000,
    },
    {
      command: 'node tools/servidor.mjs',
      cwd: raiz,
      url: 'http://localhost:5500/',
      reuseExistingServer: true,
      timeout: 15_000,
    },
  ],
});

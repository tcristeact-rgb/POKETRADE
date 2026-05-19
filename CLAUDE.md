# CLAUDE.md – Reglas del proyecto POKETRADE

## RESTRICCIÓN ABSOLUTA: Backend intocable

**Nunca modificar, editar, crear ni eliminar ningún archivo dentro de la carpeta `api/`.**

Esto incluye sin excepción:

- `api/app/` — Modelos, Controladores, Middleware, Providers
- `api/routes/` — Rutas de la API
- `api/database/` — Migraciones, Seeders, Factories
- `api/config/` — Configuración de Laravel
- `api/resources/` — Vistas y assets del backend
- `api/tests/` — Tests del backend
- `api/composer.json`, `api/package.json` — Dependencias
- Cualquier otro archivo dentro de `api/`

Todo el trabajo se realiza exclusivamente en la carpeta `frontend/`.

## Stack del proyecto

- **Backend:** Laravel 12 + JWT Auth (solo lectura, no tocar)
- **Frontend:** Vanilla JS + HTML5 + CSS3 (aquí se trabaja)
- **API base:** `http://localhost:8000/api`

## Endpoints disponibles (referencia para el frontend)

### Públicos
- `GET /api/cartas` — Listado de cartas (filtros: nombre, tipo, rareza, set_expansion)
- `GET /api/cartas/{id}` — Detalle de carta
- `GET /api/tradeos` — Tradeos activos
- `GET /api/tradeos/{id}` — Detalle de tradeo
- `POST /api/auth/registro` — Registro de usuario
- `POST /api/auth/login` — Login (devuelve JWT)

### Protegidos (requieren JWT en header `Authorization: Bearer <token>`)
- `POST /api/auth/logout`
- `GET /api/usuario/perfil`
- `PUT /api/usuario/perfil`
- `PUT /api/usuario/password`
- `GET /api/inventario`
- `POST /api/inventario`
- `DELETE /api/inventario/{id}`
- `POST /api/tradeos`
- `PUT /api/tradeos/{id}`
- `DELETE /api/tradeos/{id}`
- `GET /api/mis-tradeos`

### Admin (requieren JWT + rol admin)
- `POST /api/cartas`
- `PUT /api/cartas/{id}`
- `DELETE /api/cartas/{id}`
- `GET /api/admin/usuarios`
- `DELETE /api/admin/usuarios/{id}`

# TFG - DAW2 Desarrollo de Aplicaciones Web
# Contexto: Proyecto Final de Módulo DWEC (Desarrollo Web en Entorno Cliente)

## 👤 CONTEXTO DEL USUARIO
- Estudiante FP DAW 2º curso, Madrid
- Stack: JavaScript/TypeScript, HTML5, CSS3, Java (backend), SQL/Supabase
- Exámenes: May 2026 (Usabilidad, Accesibilidad, Consumer + programación)
- Requisitos académicos: WCAG 2.1 AA, heurísticas de Nielsen, Lighthouse ≥90

## 🎯 CRITERIOS DE EVALUACIÓN DWEC (BOE-A-2023-13221)
| Código | Criterio | Aplicación en TFG |
|--------|----------|-------------------|
| 2.a | Estructura semántica HTML5 | Usar `<header>`, `<nav>`, `<main>`, `<article>`, `<footer>` [web:23] |
| 2.b | Validación W3C | Código sin errores en validator.w3.org [web:23] |
| 2.c | Accesibilidad WCAG 2.1 AA | Contrast ratio ≥4.5:1, ARIA labels, navegación teclado [web:34] |
| 2.d | Responsive Design | Mobile-first, breakpoints 320/768/1024/1440px [web:23] |
| 2.e | Rendimiento Lighthouse | Performance ≥90, Accessibility ≥90, Best Practices ≥85 [web:23] |
| 3.a | DOM manipulation | Vanilla JS o React sin jQuery [web:23] |
| 3.b | Eventos asíncronos | Fetch API, async/await, Promise handling [web:23] |
| 4.a | Frameworks modernos | React/Vue/Angular (justificar elección) [web:26] |
| 4.b | Modularización | ES6 modules, import/export, code splitting [web:23] |
| 5.a | APIs REST | Consumir APIs externas (JSON placeholder, OpenAPI) [web:26] |
| 5.b | Web APIs nativas | LocalStorage, Service Workers, Geolocation [web:23] |

## 🛠️ STACK TECNOLÓGICO OBLIGATORIO
```yaml
frontend:
  - JavaScript ES11+ (TypeScript estricto donde aplique)
  - HTML5 semántico (validación W3C mandatory)
  - CSS3 (Custom Properties, Flexbox, Grid)
  - Framework: React 18+ o Vanilla JS (justificar en memoria)
  - Build tool: Vite o Webpack

backend:
  - Java 17+ (inheritance, polymorphismo, collections framework)
  - Spring Boot (opcional, justificar)
  - APIs REST con OpenAPI specification

database:
  - PostgreSQL/Supabase
  - SQL normalizado (1NF, 2NF, 3NF)
  - Índices optimizados para queries frecuentes

accessibility:
  - WCAG 2.1 AA (no AAA, es demasiado restrictivo)
  - Lighthouse accessibility score ≥90
  - WAVE validation sin errores blocking
  - Screen reader testing (NVDA o VoiceOver)

usability:
  - Heurísticas de Nielsen (10 heurísticas)
  - Test con 5 usuarios mínimo
  - Time-on-task <30s para tareas críticas
```

## 📐 ESTÁNDARES DE CÓDIGO

### JavaScript/TypeScript
```markdown
DO:
- Usar TypeScript con `strict: true` en tsconfig.json
- Nombrar variables: `camelCase` para vars, `PascalCase` para clases
- JSDoc para todas las funciones públicas
- ES6 modules: `import/export` (no CommonJS)
- Async/await en lugar de `.then()`
- Error handling con `try/catch` en todas las peticiones

DO NOT:
- ❌ No usar `var` (solo `let`/`const`)
- ❌ No usar `any` en TypeScript (especificar tipos)
- ❌ No usar callbacks anidados (max 3 niveles)
- ❌ No bloquear el main thread (offload a Web Workers)
```

### HTML5 Accesibilidad
```markdown
DO:
- `<main>` solo UNA vez por página
- `<nav>` con `aria-label=" principales"`
- Imágenes con `alt` descriptivo (no "imagen1.jpg")
- Contraste de color ≥4.5:1 para texto normal, ≥3:1 para grande
- Focus visible en todos los elementos interactivos
- Landmarks: `<header>`, `<nav>`, `<main>`, `<aside>`, `<footer>`

DO NOT:
- ❌ No usar `<div>` para botones (usar `<button>`)
- ❌ No usar `role="presentation"` en elementos semánticos
- ❌ No ocultar elementos con `display:none` si son necesarios para screen readers
- ❌ No usar colores solo para transmitir información (ej: "campos rojos son obligatorios")
```

### Java (Backend)
```markdown
DO:
- Patrón Strategy o Factory para lógica compleja
- Inyección de dependencias (no `new` en constructors)
- JUnit 5 para tests (≥80% coverage)
- Streams API para colecciones
- Optional para valores nullable

DO NOT:
- ❌ No usar `extends` innecesario (preferir composición)
- ❌ No lanzar `Exception` genérica (crear custom exceptions)
- ❌ No usar `null` (usar `Optional.empty()`)
```

## 🔧 COMANDOS DEL PROYECTO
```bash
# Desarrollo
npm run dev              # Vite dev server (localhost:3000)
npm run type-check       # TypeScript validation
npm run lint             # ESLint + Prettier

# Testing
npm test                 # Jest unit tests
npm run test:e2e         # Playwright e2e
npm run test:a11y        # pa11y accessibility tests

# Build & Validación
npm run build            # Production build
npm run preview          # Preview production build
npx lighthouse http://localhost:3000 --output=html --output-path=./reports/lighthouse.html
npx wave-cli http://localhost:3000 --output=./reports/wave.json
```

Realiza todos los cambios considerando una estructura mobile first

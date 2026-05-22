# PokeTrade

Plataforma web de intercambio de cartas Pokémon entre coleccionistas.
Proyecto de Fin de Grado (TFG) del ciclo **DAW** — IES El Lago, Madrid.

Autores: **Daniel Leal** y **Teo Cristea**.

> La **memoria completa** del proyecto se encuentra en el PDF entregado en la
> tarea. Este README resume la información técnica necesaria para ejecutar y
> entender el repositorio.

---

## Descripción

PokeTrade permite a los usuarios registrar su colección de cartas, publicar
"tradeos" (ofertas de intercambio) y aceptar los de otros coleccionistas.
El catálogo de cartas se nutre de la [PokeAPI](https://pokeapi.co/) y los
datos de usuarios, inventario y tradeos se gestionan con una API REST propia.

---

## Stack tecnológico

| Capa        | Tecnología                                              |
|-------------|---------------------------------------------------------|
| Frontend    | JavaScript ES6 (módulos `import`/`export`), HTML5, CSS3 |
| Backend     | Laravel 12 + autenticación JWT (carpeta `api/`)         |
| API externa | PokeAPI v2                                              |
| Tests       | Jest (pruebas unitarias del frontend)                   |

El frontend es **vanilla JS**: sin frameworks, organizado en módulos ES6.

---

## Estructura del proyecto

```
POKETRADE/
├── api/                  Backend Laravel
├── frontend/
│   ├── index.html        Página de inicio
│   ├── 404.html          Página de error 404
│   ├── favicon.svg
│   ├── css/
│   │   └── estilos.css   Hoja de estilos única (todo el CSS centralizado)
│   ├── js/
│   │   ├── config.js     Configuración (URL base de la API)
│   │   ├── auth.js       Sesión y autenticación JWT
│   │   ├── header.js     Inyecta la cabecera y el pie comunes
│   │   ├── utils.js      Utilidades compartidas
│   │   └── *.js          Un módulo por página
│   ├── pages/            Resto de páginas HTML
│   ├── tests/            Pruebas unitarias (Jest)
│   └── package.json
└── README.md
```

---

## Puesta en marcha

### 1. Backend (API)

Requiere PHP 8.2+, Composer y una base de datos.

```bash
cd api
composer install
cp .env.example .env        # configurar la base de datos
php artisan key:generate
php artisan jwt:secret
php artisan migrate --seed
php artisan serve            # queda sirviendo en http://localhost:8000
```

### 2. Frontend

El frontend usa **módulos ES6**, por lo que **debe servirse mediante HTTP**
(no abriendo los `.html` con doble clic como `file://`, que el navegador
bloquea por seguridad). Cualquier servidor estático sirve:

- Extensión **Live Server** de VS Code (clic derecho sobre `index.html` →
  *Open with Live Server*).
- O por línea de comandos: `npx serve frontend`

La URL de la API se define en [`frontend/js/config.js`](frontend/js/config.js).
Por defecto apunta a `http://localhost:8000/api`. Para usar otro servidor,
define `window.POKETRADE_API_URL` antes de cargar los scripts.

---

## Pruebas

```bash
cd frontend
npm install      # instala Jest (solo la primera vez)
npm test         # ejecuta las pruebas unitarias
```

Las pruebas cubren las funciones puras de [`frontend/js/utils.js`](frontend/js/utils.js)
(escape de HTML, cálculo de rareza y generación, traducción de tipos…).

---

## Endpoints de la API

### Públicos
- `GET  /api/cartas` · `GET /api/cartas/{id}`
- `GET  /api/tradeos` · `GET /api/tradeos/{id}`
- `POST /api/auth/registro` · `POST /api/auth/login`

### Protegidos (cabecera `Authorization: Bearer <token>`)
- `POST /api/auth/logout`
- `GET/PUT /api/usuario/perfil` · `PUT /api/usuario/password`
- `GET/POST /api/inventario` · `DELETE /api/inventario/{id}`
- `POST/PUT/DELETE /api/tradeos` · `GET /api/mis-tradeos`

---

## Accesibilidad

El frontend sigue las pautas **WCAG 2.1 AA**: HTML5 semántico, enlace para
saltar al contenido, navegación por teclado, gestión del foco en ventanas
modales, atributos ARIA y contraste de color suficiente.

---

## Aviso legal

Pokémon y sus marcas son propiedad de Nintendo, Game Freak y The Pokémon
Company. Este proyecto es **académico y sin ánimo de lucro**.

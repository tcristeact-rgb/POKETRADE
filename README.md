# PokeTrade

> A full-stack Pokémon card trading platform — browse a catalog of 1,000+ cards, build your collection, and trade with other collectors.

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-Supabase-4169E1?logo=postgresql&logoColor=white)

**Live demo: [poketrade-beryl.vercel.app](https://poketrade-beryl.vercel.app)**
> The backend runs on a free tier and sleeps after inactivity — the first request may take ~30–50s to wake up.

![PokeTrade home](docs/screenshots/home.png)

---

## About

PokeTrade is a full-stack web application where collectors register their Pokémon TCG cards, publish trade offers, and accept offers from other users. It combines a custom REST API (Laravel 12, JWT auth) with an external public API (TCGdex v2) that feeds the card catalog with real trading cards: official illustrations, sets, rarities, illustrators and Cardmarket prices.

The frontend and backend are fully decoupled: a vanilla JavaScript client (no framework, no build step) talks to the API exclusively over HTTP/JSON.

This project was developed as the final project (Trabajo de Fin de Grado) for the Higher Technical Degree in Web Application Development (DAW).

## Features

- JWT authentication — register, login, logout, protected routes
- User profile — view/edit profile, change password
- Card catalog — server-side paginated listing and detail view of real TCG cards (official illustration, set and collector number, illustrator, HP, Cardmarket average price in EUR), with filters by name, type and rarity (case-insensitive search) built from the actual database values
- Personal inventory — add cards from the catalog, remove them, manage quantities
- Trades (the core feature) — publish a trade (offered cards are withdrawn from inventory inside a DB transaction), browse a public marketplace, and accept trades with an atomic card swap between both users' inventories
- Admin role — card CRUD and user management behind role middleware
- Accessibility — WCAG 2.1 AA: focus trap in modals, ARIA attributes, keyboard navigation, skip-to-content link

## Screenshots

| Catalog | Marketplace | Card detail |
|---|---|---|
| ![Catalog](docs/screenshots/catalog.png) | ![Marketplace](docs/screenshots/marketplace.png) | ![Card detail](docs/screenshots/card-detail.png) |

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 12 (PHP 8.2) |
| Auth | JWT (tymon/jwt-auth) |
| Frontend | Vanilla JavaScript (ES6 modules), HTML5, CSS3 — no framework, no build |
| External API | TCGdex v2 (card data, seeded into the DB) |
| Database | SQLite (local) · PostgreSQL / Supabase (production) |
| Testing | PHPUnit (in-memory SQLite) |
| Deployment | Render (backend) · Vercel (frontend) · Supabase (database) |

## Architecture & Deployment

Decoupled client–server architecture. The frontend and backend are deployed independently and communicate over a REST API:

```
Browser (Vercel)  --HTTP/JSON-->  Laravel REST API (Render)  -->  PostgreSQL (Supabase)
                                            |
                                            +-->  TCGdex API v2 (seeder & sync only, cached)
```

The card catalog is seeded into the database from TCGdex (curated sets "151" and "Cenit Supremo", ~437 cards) instead of being fetched live: the browser never waits on the external API, and TCGdex receives a minimum of requests (responses are also cached server-side for 24 h). A `cartas:sincronizar-tcgdex` artisan command refreshes prices and data on demand.

## Technical Highlights

- Atomic trades with race-condition protection — accepting a trade runs inside a database transaction and uses `lockForUpdate()` to prevent two users from accepting the same trade simultaneously (double-spend).
- N+1 prevention — eager loading of relationships on listing endpoints.
- XSS-safe rendering — systematic HTML escaping and `textContent` for all user-provided data on the client.
- Cross-database compatibility — driver-aware query operators so the same code runs on SQLite (local) and PostgreSQL (production).
- Accessibility-first — keyboard-navigable modals with focus management, not just visual styling.

## Local Setup

Backend
```bash
cd api
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
php artisan migrate --seed
php artisan serve            # http://localhost:8000
```

Frontend (must be served over HTTP, not `file://`)
```bash
npx serve frontend          # or use VS Code Live Server
```

Running `php artisan migrate --seed` populates your local database with the real TCG card catalog (fetched once from TCGdex) plus sample users, inventories and trades so you can explore the app right away during development. In production only the card catalog is seeded (`php artisan db:seed --class=CartasSeeder --force`); no demo users are created, so on the live demo you can simply sign up to try it.

## Testing

```bash
cd api
php artisan test            # 17 tests, in-memory SQLite
```

## License

[MIT](LICENSE)

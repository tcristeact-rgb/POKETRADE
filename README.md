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

PokeTrade is a full-stack web application where collectors register their Pokémon cards, publish trade offers, and accept offers from other users. It combines a custom REST API (Laravel 12, JWT auth) with an external public API (PokeAPI v2) that feeds the card catalog with real data and artwork.

The frontend and backend are fully decoupled: a vanilla JavaScript client (no framework, no build step) talks to the API exclusively over HTTP/JSON.

This project was developed as the final project (Trabajo de Fin de Grado) for the Higher Technical Degree in Web Application Development (DAW).

## Features

- JWT authentication — register, login, logout, protected routes
- User profile — view/edit profile, change password
- Card catalog — paginated listing and detail view fed by PokeAPI, with filters by name, type and rarity (case-insensitive search)
- Personal inventory — add cards (resolved on-demand from PokeAPI), remove them, manage quantities
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
| External API | PokeAPI v2 |
| Database | SQLite (local) · PostgreSQL / Supabase (production) |
| Testing | PHPUnit (in-memory SQLite) |
| Deployment | Render (backend) · Vercel (frontend) · Supabase (database) |

## Architecture & Deployment

Decoupled client–server architecture. The frontend and backend are deployed independently and communicate over a REST API:

```
Browser (Vercel)  --HTTP/JSON-->  Laravel REST API (Render)  -->  PostgreSQL (Supabase)
                                            |
                                            +-->  PokeAPI v2 (external)
```

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

Demo accounts (seeded locally only — production starts with an empty database):

| Role | Email | Password |
|---|---|---|
| Admin | admin@poketrade.es | admin123 |
| User | teo@poketrade.es | 123456 |

## Testing

```bash
cd api
php artisan test            # 17 tests, in-memory SQLite
```

## License

[MIT](LICENSE)

# PokeTrade

> A full-stack Pokémon card trading platform — browse the complete TCG catalog by expansion set, build your collection, and trade with other collectors.

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
- Full TCG catalog by expansion — browse every series and set ever published (~21 series, ~214 sets) with breadcrumb navigation: series → sets (logo, year, card count) → paginated card grid. Card data is cached on demand per set, so any card of any set can be collected and traded
- Illustration zoom — clicking any card illustration opens an accessible lightbox with the high-quality image (low-res placeholder while it loads, arrow-key navigation between cards, focus trap, Escape/outside-click close)
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
                                            +-->  TCGdex API v2 (sync command & cache-aside, cached 24 h)
```

### Card catalog: lightweight index + cache-aside

The full TCG catalog is ~130k cards, so it is **not** stored upfront. Instead:

1. **Series/sets index** — `php artisan tcgdex:sync-sets` seeds a lightweight index (~21 series, ~214 sets: name, logo, release date, card count). It is idempotent and walks **every** TCGdex catalog: each pass writes its own name column (`nombre_es`, `nombre_en`) and adds whatever that catalog has and the previous one lacked (some old sets were never translated). Run it once after deploying and re-run whenever new sets are released.
2. **Cards on demand, one language at a time (cache-aside)** — the first time anyone opens a set **in a given language**, `GET /api/sets/{id}/cartas` fetches that catalog from TCGdex (a single request), persists it inside a DB transaction and records the language in `idiomas_sincronizados`; every later visit in that language is served straight from the database. A set nobody has opened in English never spends a request getting translated. If TCGdex is down, the endpoint returns a clear 503 and the set is never left half-cached.
3. **Lazy detail hydration, also per language** — cards enter the DB with just name, number and image; the first time a card's detail page is opened in a language, `GET /api/cartas/{id}` completes rarity, type, price and description from that catalog and persists them.

What gets stored per language is only what actually differs: `nombre`, `descripcion` and the illustration (the TCGdex asset carries the language in its path, because the artwork contains the card's printed text). Types and rarities are a **closed set**, so they are stored as a canonical key and translated from a dictionary (`lang/{es,en}/tcg.php`) — zero DB growth. Everything else (price, HP, illustrator, card number) is language-neutral.

TCGdex's answers are distinguished three ways, which is what makes the per-language cache work: the data, `404` (*this catalog does not have it, and never will* — cached, so we stop asking for the Spanish version of Base Set) and *no answer at all* (a 5xx or a timeout — not cached, so it is retried).

The browser never talks to TCGdex directly, and all external responses are cached server-side for 24 h. `cartas:sincronizar-tcgdex [--idioma=en]` still refreshes prices/data of already-stored cards on demand.

**Excluded catalogs** — `config/tcgdex.php` holds a `series_excluidas` list (currently `tcgp` Pokémon Pocket, `mc` McDonald's, `tk` Trainer Kits: non-physical or asset-less catalogs). Adding a serie id there requires no code changes: the sync skips it, the global search filters its cards out, and `php artisan tcgdex:purgar-excluidos` (dry-run by default, `--force` to apply) removes anything already imported, including inventories and trades that reference those cards.

### Catalog & expansion endpoints

| Method & path | Description |
|---|---|
| `GET /api/series` | All series, newest first, with set counts |
| `GET /api/series/{id}` | One serie with its sets (accepts TCGdex or internal ID) |
| `GET /api/sets` | All sets, `?serie=X` to filter |
| `GET /api/sets/{id}` | Set header info (logo, release date, card count) |
| `GET /api/sets/{id}/cartas` | Paginated cards of a set — triggers the on-demand caching |
| `GET /api/cartas/{id}` | Card detail — triggers lazy hydration from TCGdex |

## Technical Highlights

- Bilingual (Spanish/English), no i18n library — the UI runs on a hand-rolled dictionary with `Intl.PluralRules` and `Intl.NumberFormat` (so `12,50 €` and `€12.50` are the same number, and a third language's plural rules will not silently break); the API negotiates `Accept-Language` and answers with `Content-Language` + `Vary`; and the card data lives in per-language columns filled lazily. Adding a language means editing one dictionary.
- Atomic trades with race-condition protection — accepting a trade runs inside a database transaction and uses `lockForUpdate()` to prevent two users from accepting the same trade simultaneously (double-spend).
- N+1 prevention — eager loading of relationships on listing endpoints (a card always travels with its set, which is where its translated expansion name comes from).
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
php artisan tcgdex:sync-sets # series/sets index for the expansions browser (~3 min the first time)
php artisan serve            # http://localhost:8000
```

Frontend (must be served over HTTP, not `file://`)
```bash
npx serve frontend          # or use VS Code Live Server
```

Running `php artisan migrate --seed` populates your local database with a starter card catalog (two curated sets fetched once from TCGdex) plus sample users, inventories and trades so you can explore the app right away during development. `php artisan tcgdex:sync-sets` adds the series/sets index so the expansions browser works; the cards of any other set are cached automatically the first time you open it. In production only the catalog is set up (`php artisan db:seed --class=CartasSeeder --force` plus `php artisan tcgdex:sync-sets`, which needs no `--force` because it has no environment guard); no demo users are created, so on the live demo you can simply sign up to try it.

After a deploy that changes the schema, run `php artisan cache:clear`: the `cache` table survives deploys, and anything cached from the old schema would be served for up to an hour.

## Testing

```bash
cd api
php artisan test            # 90 tests, in-memory SQLite (TCGdex mocked with Http::fake)
```

## License

[MIT](LICENSE)

# FeedYou

AI-powered restaurant POS for Malaysian F&B, built for the SUC × FeedYou hackathon.

- **POS counter** (`/pos`): tap items, or type or speak an order: *"2 kopi o kurang manis, satu nasi lemak tambah telur"*
- **QR table ordering** (`/t/{token}`): customers order from their phone and track the order live
- **Kitchen display** (`/kitchen`): tickets grouped by status, late orders highlighted, sound on new orders
- **Owner dashboard** (`/admin`): sales, best sellers, AI insights, sold-out toggles, printable table QR codes
- **1-click setup** (`/setup`): restaurant name, SST and service charge, Gemini key (encrypted), staff PIN

## Quick start

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed
php artisan serve
```

Open http://localhost:8000. The AI ordering works without an API key (offline rule-based parser). Add a Gemini key in `/setup` to use the LLM.

Requires PHP 8.4+ (Laravel 13). Or run `docker compose up --build`.

## Tests

```bash
php artisan test
```

## Architecture

See [ARCHITECTURE.md](ARCHITECTURE.md) for layers, data model, order state machine, AI design, API reference and roadmap.

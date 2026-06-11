# Inordio

> *Keep your business in order* — multi-tenant field service management for trades businesses.

Inordio is a SaaS platform for MSPs, plumbing, HVAC, electrical, and other field service companies. Its core differentiator is **real inventory**: actual quantities tracked per location (warehouse + trucks), serialized assets with nested assemblies, QR pick lists, and a quote → job → invoice chain that always knows what was used.

**Read [PROJECT-BRIEF.md](PROJECT-BRIEF.md) first** — it is the source of truth for product scope and architecture decisions.

## Stack

Laravel 12 · Livewire 3 (Breeze) · MySQL 8 · stancl/tenancy · Tailwind CSS

## Local development

Requires PHP 8.4+, Composer, Node 20+, MySQL 8 (Windows: [Laravel Herd](https://herd.laravel.com) provides PHP/Composer).

```bash
composer install
npm install && npm run build
cp .env.example .env        # set DB_DATABASE=inordio, DB_USERNAME/PASSWORD
php artisan key:generate
php artisan migrate
php artisan serve
```

Run tests:

```bash
php artisan test
```

## Repository notes

- The original Next.js scaffold (Dec 2025) lives on the `archive/nextjs-scaffold-2025` branch; what was carried forward is documented in [docs/LEGACY-SCAFFOLD-NOTES.md](docs/LEGACY-SCAFFOLD-NOTES.md).
- Every tenant-scoped table must carry `tenant_id`; tenant isolation is tested with every feature. See the brief, §3.

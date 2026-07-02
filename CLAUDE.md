# Inordio — Claude Code context

**Resuming the build?** Start with **`docs/FOR-CLAUDE-CODE.md`** (how we work + gotchas), then **`docs/PROGRESS.md`** (everything built — well past the MVP spine now; 295 tests green), **`docs/ROADMAP-TO-PRODUCTION.md`** (what's left), **`docs/RESUME-FROM-HOME.md`** (run it locally), and **PROJECT-BRIEF.md** (scope/architecture source of truth). Key rules:

- Stack is Laravel 12 + Livewire 3 + MySQL 8 + stancl/tenancy. **Decided — do not relitigate.** No forks of third-party apps; everything is first-party.
- Every tenant-scoped table needs `tenant_id`. **Write tenant-isolation tests with every feature** — data leakage between tenants is the worst possible bug in this product.
- Mobile-first UI for anything technicians touch (PWA, phones in the field).
- Canadian taxes are data, not constants (brief §7).
- AI features go through a single `AiGateway` service class pointed at TTG's local gateway — no AI provider SDKs in app code (brief §8).
- Audit before assuming: check existing migrations/configs before generating new code.
- User roles: Owner / Admin / Office / Technician / Viewer (five roles — see docs/LEGACY-SCAFFOLD-NOTES.md). Permission gates live in `app/Providers/AppServiceProvider.php`.

## Conventions established during the build (match these)

- Models: `#[Fillable([...])]` attribute + `BelongsToTenant` + `casts()`. Lifecycle `*_at` columns are NOT fillable — set them directly in transition methods.
- Migrations: copy the `tenant_id` block from `2026_06_11_000001_*`; use string columns + enum casts, never DB `enum()` (SQLite tests).
- Volt full-page components under `resources/views/livewire/<area>/`, routed with `Volt::route`. Detail route param must NOT match the model property name (use `{itemId}`, not `{item}`) or Livewire route-model-binds it and skips the tenant scope. Resolve records in `mount()` via `findOrFail`.
- Auto-number via a `booted()` `created` hook + `saveQuietly()`.
- The domain Job model uses table **`service_jobs`** (`jobs` is reserved by the queue).
- Tax rates live ONLY in `config/taxes.php`; invoices snapshot tax at issue time.

## Testing / running

- Tests run on in-memory SQLite (`phpunit.xml`) — no MySQL needed. Every feature gets a tenant-isolation test.
- The AI agent sandbox can't run PHP or write to `.git`; the user runs `run-inventory-tests.ps1` (full suite → `test-results.txt`, UTF-16) and commits on the Windows host.

## Commands

```bash
php artisan test          # PHPUnit suite
npm run build             # Vite production build
npm run dev               # Vite dev server
php artisan migrate       # uses MySQL database `inordio` locally
```

The old Next.js scaffold is on branch `archive/nextjs-scaffold-2025` — reference only, never merge.

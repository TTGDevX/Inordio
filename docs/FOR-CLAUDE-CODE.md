# For Claude Code — start here

You're resuming an in-progress Laravel app (**Inordio** — a multi-tenant field-service SaaS for trades). Read this, then the docs below, before writing code.

## Read order
1. **This file** — how we work + hard-won gotchas.
2. **`CLAUDE.md`** — stack, conventions, rules (do not relitigate the stack).
3. **`docs/PROGRESS.md`** — everything that's built, in detail.
4. **`docs/ROADMAP-TO-PRODUCTION.md`** — what's left and what each item needs.
5. **`docs/RESUME-FROM-HOME.md`** — how to run it locally on a fresh machine.
6. **`PROJECT-BRIEF.md`** — scope/architecture source of truth.

## Current state (July 2026)
- **295 tests green.** The full spine (multi-tenant → inventory → customers → quotes → jobs → invoices → payments) plus a large amount of breadth is built: serialized assets, movement log, QR labels, short-pick/back-order, checklists, deposits/progress billing, service agreements, per-tenant SMTP, editable email templates, JSON API (read + writes), ops board, labour tracking, GST/HST report, receipts, sign-off, PDF, notifications, global search, Help page.
- Feature work is **past MVP**; what remains needs the owner (accounts/decisions) or infra (production hardening). See the roadmap.

## How we work (important)
- **You edit files; you do NOT run PHP, migrations, git, or the test suite.** The sandbox can't. After a change, the **human runs `run-inventory-tests.ps1`** on the Windows host (full `php artisan test` → `test-results.txt`, UTF-16) and **commits/pushes** themselves.
- So: make a coherent slice, then hand back the exact commands to run + commit. Tell them when a **migration** is needed (say "↳ run `php artisan migrate` first").
- **Every feature ships with tests**, always including a **tenant-isolation test** (cross-tenant data leakage is the worst possible bug here).
- Keep slices small and green; the human is often rate-limited, so don't waste round-trips.

## Conventions (also in CLAUDE.md)
- Models: `#[Fillable([...])]` + `BelongsToTenant` + `casts()`. Lifecycle `*_at` columns are NOT fillable — set them directly in transition methods.
- Volt full-page components under `resources/views/livewire/<area>/`; route param must NOT match the component's model property (use `{itemId}`, not `{item}`) or Livewire route-model-binds and skips the tenant scope. Resolve in `mount()` via `findOrFail`.
- Migrations: copy the `tenant_id` block from an existing one; string columns + enum casts, never DB `enum()` (SQLite tests).
- Tax rates live ONLY in `config/taxes.php`; invoices snapshot tax at issue time. Money math goes through `App\Support\Money` (integer cents).
- Gates in `app/Providers/AppServiceProvider.php`; enforce server-side with `abort_unless(Gate::allows(...), 403)` AND hide UI with `@can`.

## Gotchas learned the hard way (don't repeat these)
- **Blade nested braces:** never write a literal `{{ ... }}` inside a Blade echo (e.g. `{{ '{{ token }}' }}`) — the inner `}}` closes the echo and the view won't compile. Build the literal string in PHP (`@php($x = '{{ token }}')`) and echo `{{ $x }}`, or use `@{{ }}`.
- **Dates in queries:** use `whereDate('col', '>=', $from)` / `<=` for date-range filters, not `whereBetween` (a stored time component breaks the upper bound).
- **Enums in `whereIn`:** pass backing values (`Status::A->value`), not enum instances.
- **DomPDF memory:** rendering PDFs is memory-heavy; `phpunit.xml` sets `memory_limit=512M`. On a real send hitting 128M, bump PHP `memory_limit` to 256M. DomPDF is guarded by `class_exists` in the mailables.
- **Signature/canvas:** wrap client-drawn canvases in `wire:ignore` so Livewire doesn't morph them.
- **Never bulk find-replace Blade files** (it corrupted layouts once). Make targeted edits.
- After pulling, if a page errors on a missing column, **run `php artisan migrate`** — there are many migrations.

## Commands (human runs these)
```powershell
powershell -ExecutionPolicy Bypass -File .\run-inventory-tests.ps1   # full test suite
php artisan migrate      # when a slice adds a migration (local MySQL `inordio`)
npm run build            # Vite
```

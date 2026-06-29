# Inordio — Build Progress & Resume Guide

> **Purpose:** This is the single source of truth for *where the build is* and *how to pick it back up cold*. Read this together with `PROJECT-BRIEF.md` (scope/architecture) and `CLAUDE.md` (rules). Last updated June 2026.

---

## 1. Status at a glance

The full MVP spine from the brief is built and tested: **multi-tenant → inventory → customers → quotes → jobs → invoices → payments**, with role-based access control and a Canadian tax engine.

| Phase | Scope | State |
|-------|-------|-------|
| 0 | Tenancy, 5 roles, auth | ✅ committed |
| 1 | Inventory core + mobile UI + stock movements + RBAC | ✅ committed |
| 2 | Customers (province + tax fields) | ✅ committed |
| 3 | Quotes + approvals | ✅ committed |
| 4 | Jobs + scheduling + quote→job | ✅ committed |
| 5 | Invoicing + payments + tax engine | ⏳ built; verify + commit (see §6) |

Test suite: ~116 tests, all green at last run (Phase 5 pending its run). Tests use **in-memory SQLite**, so they need no MySQL.

---

## 2. What exists, by domain

All models use the `BelongsToTenant` trait (stancl/tenancy) and carry a `tenant_id`. All list/detail/form screens are Livewire **Volt** full-page components under `resources/views/livewire/<area>/`.

### Tenancy & roles (Phase 0)
- `app/Enums/UserRole.php` — Owner(5) > Admin(4) > Office(3) > Technician(2) > Viewer(1), with `rank()` / `isAtLeast()`.
- `app/Http/Middleware/IdentifyTenant.php` — resolves tenant from `TENANT_ID` env pin → authenticated user → (subdomain later). Appended to the `web` group in `bootstrap/app.php`.
- Tests: `tests/Feature/Tenancy/`.

### Inventory (Phase 1)
- Models: `Category`, `Supplier`, `Location` (warehouse/truck/jobsite via `LocationType`), `InventoryItem` (dual SKU, cost/price, `is_serialized`), `StockLevel` (qty per location, reorder point), `StockMovement` (immutable ledger: receipt/transfer/usage/adjustment), `SerializedAsset` (self-referencing parent for arbitrary nesting — the "LEGO model"), `AssetEvent`.
- Service: `app/Services/StockManager.php` — the **only** way to change stock. `receive / transfer / consume / adjust`, each transactional, recording a movement + adjusting levels, throwing `InsufficientStockException` rather than going negative.
- UI: `inventory/index`, `inventory/show` (with stock-move actions), `inventory/form`; `locations/index` (inline create/edit).

### Customers (Phase 2)
- `Customer` + `app/Enums/Province.php` (13 provinces/territories). Carries address, `province`, `tax_exempt`, `tax_number`. UI: `customers/index|show|form`.

### Quotes (Phase 3)
- `Quote` (auto-numbered `Q-00001`, `subtotal()` pre-tax, status flow), `QuoteLineItem` (snapshots description+price), `app/Enums/QuoteStatus.php` (draft/sent/approved/declined).
- UI: `quotes/index`, `quotes/form` (line-item builder, live subtotal), `quotes/show` (send/approve/decline + **Convert to job**).

### Jobs (Phase 4)
- `Job` (**table `service_jobs`** — see §5 gotchas; auto-numbered `J-00001`; `fromQuote()` copies lines; status flow), `JobLineItem`, `app/Enums/JobStatus.php` (scheduled/in_progress/done/cancelled).
- UI: `jobs/index`, `jobs/form` (customer, title, technician, schedule, lines), `jobs/show` (start/complete/cancel + assign tech + **Create invoice**).

### Invoicing + payments + tax (Phase 5)
- `app/Services/TaxCalculator.php` + `config/taxes.php` — **rates as data** (see §7). Non-compounded, honors tax-exempt.
- `Invoice` (auto-numbered `INV-00001`, **tax snapshotted at issue time**: `province`, `tax_breakdown` JSON, `tax_total` frozen on the row; `total()/amountPaid()/balance()`; `fromJob()`), `InvoiceLineItem`, `Payment` (`recordPayment()` auto-flips status to Paid when balance clears). Enums: `InvoiceStatus`, `PaymentMethod` (cash/cheque/e-transfer/card/other).
- UI: `invoices/index`, `invoices/show` (tax breakdown, payments list, record-payment form, mark-sent, void).

---

## 3. Roles & permissions (Gates)

Defined in `app/Providers/AppServiceProvider.php` via `Gate::define`, keyed to the `UserRole` hierarchy:

| Ability | Min role | Used for |
|---------|----------|----------|
| `move-stock` | Technician | receive/transfer/consume on item page |
| `manage-inventory` | Office | item create/edit |
| `manage-locations` | Office | location create/edit |
| `manage-customers` | Office | customer create/edit |
| `manage-quotes` | Office | quote create/edit/send/approve/decline, convert-to-job is `manage-jobs` |
| `manage-jobs` | Office | job create/edit/convert/assign/cancel |
| `work-jobs` | Technician | job start/complete (techs in the field) |
| `manage-invoices` | Office | create from job, mark-sent, void |
| `record-payments` | Office | record a payment |

Enforced **server-side** (`abort_unless(Gate::allows(...), 403)` in component mount/actions) AND reflected in the UI with `@can`. Viewers are read-only everywhere.

---

## 4. Conventions (follow these for new work)

- **Stack is fixed** (brief §2): Laravel + Livewire 3 (Volt) + MySQL + stancl/tenancy + Tailwind. Installed framework is 13.x; APIs used are stable across 11–13.
- **Models:** `#[Fillable([...])]` attribute (matches `User`), `BelongsToTenant`, `protected function casts()`. `*_at` lifecycle timestamps are **not** in `Fillable` — set them by direct assignment in transition methods (see §5).
- **Migrations:** every tenant table starts with the `tenant_id` block (string 36, nullable, indexed, FK cascade) copied from `2026_06_11_000001_*`. Use **string columns + enum casts**, never DB `enum()` (SQLite-test compatibility).
- **Auto-numbering:** `booted()` `created` hook → `forceFill([...])->saveQuietly()`.
- **Volt routes:** `Volt::route('thing/{thingId}', 'thing.show')`. **Param must NOT share the model's property name** (use `{itemId}`, not `{item}`) — otherwise Livewire route-model-binds it and bypasses the tenant scope. Register static segments (`thing/create`) **before** the `{thingId}` wildcard.
- **Tenant-safe detail pages:** resolve the record in `mount()` with `Model::findOrFail($id)` (runs under tenancy), not implicit route-model binding.
- **Line-item builder pattern** (quotes/jobs forms): `public array $lines`, `addLine/removeLine`, an `updated()` hook that prefills description+price when a catalogue item is picked, and **normalize `''` → `null`** for `inventory_item_id` before validating (empty `<select>` value fails the `integer` rule otherwise).
- **Layout:** authed pages use `#[Layout('layouts.app')]`.

---

## 5. Gotchas already hit (don't relearn these the hard way)

1. **Route param/property name collision** → Livewire treats `{item}` as a route-model binding and skips the tenant scope, 404ing everything. Fix: distinct param names (`{itemId}`) + resolve in `mount()`. (Caught by a 404 test.)
2. **Mass-assignment silently drops non-fillable fields.** `update(['sent_at' => now()])` did nothing because `sent_at` isn't fillable — status changed but the timestamp didn't. Fix: set `*_at` columns by direct property assignment. (Caught by a status test.)
3. **`jobs` is reserved** by Laravel's queue. The domain Job model maps to **`service_jobs`** (`protected $table = 'service_jobs'`). FKs reference `service_jobs`.
4. **Volt files are global-namespace:** `use InvalidArgumentException;` (a global class) throws "use statement … has no effect". Reference global classes with a leading backslash (`\InvalidArgumentException`) and no `use`.
5. **Dev environment:** the AI agent's sandbox can't run PHP and **can't write to `.git`**. Tests are run on the Windows host via `run-inventory-tests.ps1`; commits are done by the user. If git shows mass "deleted/untracked" weirdness, run `git reset` (non-destructive) — it was a confused index, files are safe. Clear a stale lock with `Remove-Item .git\index.lock -Force`.
6. **`.ps1` scripts must be ASCII** — an em-dash in the helper script broke Windows PowerShell parsing.
7. **`test-results.txt` is UTF-16**, so plain `grep` mangles it; decode before reading.

---

## 6. How to run

Requirements: PHP 8.4+, Composer, Node 20+, MySQL 8 for real use (tests use SQLite). See `README.md`.

```bash
composer install
npm install && npm run build
php artisan migrate          # real dev DB (MySQL `inordio`)
php artisan serve            # click through the app
php artisan test             # full suite (in-memory SQLite)
```

Windows helper that runs the whole suite and logs to `test-results.txt`:

```powershell
powershell -ExecutionPolicy Bypass -File .\run-inventory-tests.ps1
```

`run-inventory-tests.ps1` and `test-results.txt` are git-ignored (local helpers).

---

## 7. Canadian tax rates (verify before each tax change)

`config/taxes.php` is the **only** place rates live. Verified June 2026; non-compounded (each component applied to the pre-tax subtotal, per current CRA rules).

| Province | Components | Notes |
|----------|-----------|-------|
| AB, NT, NU, YT | GST 5% | |
| ON | HST 13% | |
| **NS** | **HST 14%** | **dropped from 15% on 2025-04-01** |
| NB, NL, PE | HST 15% | |
| BC, MB | GST 5% + PST 7% | |
| SK | GST 5% + PST 6% | |
| QC | GST 5% + QST 9.975% | |

Sources: Retail Council of Canada (NS→14%), canada.ca GST/HST rate page. **Re-verify on any rate-change news; edit only `config/taxes.php`.** Existing invoices are unaffected because tax is snapshotted onto each invoice at issue time.

---

## 8. Roadmap — what to do next (priority order)

> ✅ **Pick-list flow is now BUILT** (Phase "1.5", June 2026): `PickList` + `PickListItem` models, `PickList::generateFrom(Job)` (catalogue lines only), a pick-list page where each line is picked from a chosen source location to the destination truck via `StockManager::transfer` (checks off, auto-completes), and **job completion consumes the picked quantities off the truck** via `StockManager::consume` (closing the loop so stock reflects what was actually used). Files: `app/Models/PickList*.php`, `app/Enums/PickListStatus.php`, `resources/views/livewire/picklists/show.blade.php`, generate/consume wired into `jobs/show`. Tests in `tests/Feature/PickLists/`. **Still open:** real **camera QR/barcode scanning** as the pick input (currently a source-location select + Pick button — same domain action, just needs the scanner UI layer; items already carry `barcode`, and a scan can match `InventoryItem::where('barcode', …)`).

1. **Camera QR/barcode scanning UI** — layer a phone-camera scanner onto the pick-list page (and receiving). Pure front-end on top of the working domain.
2. **Reports / dashboard:** open quotes, scheduled jobs this week, unpaid invoices (aging), low-stock alerts (`StockLevel::isLow()` already exists). All data is present.
4. **Phase 6 — Expenses + receipt OCR.** Blocked on an open question: AI gateway endpoint/protocol/auth (brief §8/§9). Route all AI through one `AiGateway` service class — no provider SDKs in app code.
5. **RBAC refinements:** scope `work-jobs` to the *assigned* technician (currently any tech in the tenant); add delete/deactivate flows.
6. **Polish:** standalone invoice builder (invoices currently come only from jobs), movement-history UI on items, edit-after-send rules.
7. **Pre-external-beta (brief §3):** wildcard TLS + subdomain tenant identification, MySQL backup strategy, package as a Pterodactyl egg.

### Still-open questions from the brief (need Scott's input)
- AI gateway details (endpoint, protocol, models, auth).
- OCR provider for receipts (gateway vs Google Vision vs Textract).
- Offline-sync strategy for the mobile PWA.

---

## 9. Testing approach (so new tests match)

- In-memory SQLite (`phpunit.xml`), `RefreshDatabase`.
- Pattern: create `Tenant`s, `tenancy()->initialize($t)` to create scoped data, `tenancy()->end()` before HTTP requests so the middleware re-resolves from the acting user. An `asTenant($t, fn)` helper appears in several tests.
- **Component logic:** `Livewire\Volt\Volt::test('area.component', [...])->set(...)->call(...)->assertHasNoErrors()`.
- **Authorization & isolation:** prefer HTTP (`$this->actingAs($user)->get(route(...))->assertForbidden()/assertNotFound()`) plus the `Gate` matrix directly. Note: Livewire's test helper has **no** `assertForbidden`/`assertStatus`, so don't assert aborts on Volt *actions* — test the gate + page guard + UI show/hide instead.
- Every feature includes a **tenant-isolation** test (data leakage is the worst bug — brief §3).

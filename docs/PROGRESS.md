# Inordio — Build Progress & Resume Guide

> **Purpose:** This is the single source of truth for *where the build is* and *how to pick it back up cold*. Read this together with `PROJECT-BRIEF.md` (scope/architecture) and `CLAUDE.md` (rules). Last updated **June 30, 2026 (month-end checkpoint)**.

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
| 5 | Invoicing + payments + tax engine | ✅ committed |

Test suite: **252 tests green** (679 assertions) at last run. Tests use **in-memory SQLite**, so they need no MySQL.

### Month-end checkpoint — June 30, 2026

The MVP spine (Phases 0–5) shipped, and a wave of post-MVP slices landed on top of it. Everything below is built, tested, and pushed to `main`:

- **Foundations for real money & trust:** money-as-integer-cents (`Money` helper), rounding-safe tax math, weighted-average costing (`StockManager` + `average_cost`), audit log (`Auditable` trait) **and** an Admin-only audit viewer.
- **Getting documents out:** branded print/PDF quotes & invoices, company profile + logo, email sending (quote/invoice mailables) + scheduled overdue reminders, **customer statements**, **custom per-tenant document numbering** (editable prefix + counter).
- **Inventory differentiator completed:** supplier offerings + preferred cost, CSV exports, **stock-movement log** (global + per-item), **item photos**, **low-stock reorder view** (grouped by preferred supplier), and the **serialized-asset "LEGO" UI** (nested tree, assemble/disassemble/move/retire with event history).
- **Field & ops:** job photos, job notes thread, **jobs schedule/dispatch board** (by date + technician filter), archive/restore for customers & items, purchase orders + receiving, ops dashboard now linking into all the above.

Brief scorecard rows flipped to ✅ since the original review: **Equipment tracking (serialized/nested)** and **Jobs + Scheduling**. See `REVIEW-AND-GAPS.md`.

### July 1, 2026 update — more gaps closed

Since the month-end checkpoint: **QR label generation** (items/assets/locations, scanner-ready tokens), **short-pick / back-order** on pick lists, **checklists / inspections** (templates → per-job fillable), **deposits / progress billing** (staged invoices), and **service agreements / recurring** (cadence-driven job generation). Then a **platform** wave: **per-tenant SMTP** email settings, **editable email templates** (safe `{{ token }}` substitution), and a **first-party developer JSON API** (`/api/v1`, hashed bearer tokens, tenant-scoped). Test suite 203 → 252. Brief rows now also ✅: **Checklists / Inspections**, **Service Agreements**; inventory-spec gaps closed: QR labels, movement history, item photos, serialized-asset UI, back-order/short-pick. Invoice-Ninja-style gaps closed: per-tenant SMTP, editable email templates.

**Still open (next priorities):** camera barcode/QR scanning (needs a screenshot loop to tune UX — labels/tokens already exist to scan); online payments + client portal (needs gateway accounts); expense/receipt OCR (needs AI-gateway details); recurring *invoices* specifically; API writes/webhooks; user-editable **print/PDF** doc templates (email templates done); Sage + Discord integrations; production hardening (subdomains/TLS/backups).

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

### Field documentation (post-MVP slices)
- **Customer statements**: `print/statement.blade.php` + `customers/{customerId}/statement` route (gated `manage-customers`), branded letterhead, table of invoices with balance owing. "Statement" link on the customer page.
- **Job photos**: `job_photos` table + `JobPhoto` model (`BelongsToTenant`, `url()`), `Job::photos()`. `jobs/show` uses `WithFileUploads` — techs (`work-jobs`) attach a captured/uploaded photo with optional caption (stored at `job-photos/{tenant}` on the public disk); office (`manage-jobs`) can remove. Mobile-friendly `capture="environment"` file input. Needs `php artisan storage:link` once (already done for the logo).
- **Audit viewer**: `audit/index` Volt page (route `audit`, gated `view-audit` = Admin+), reads the existing `audit_logs` (written by the `Auditable` trait). Paginated 50/page, `#[Url]` filters by record type + action, badge per action, compact changed-fields summary. Linked from the account dropdown ("Audit trail"). No schema change.
- **Customer archive/restore**: uses the existing `customers.is_active` flag. `customers/show` has an Archive/Restore action (`toggleArchive`, `manage-customers`) + archived banner; `customers/index` filters to active by default with a `#[Url] archived` "Show archived" toggle. Nothing is deleted. No schema change.
- **Inventory item archive/restore**: same pattern. Added `inventory_items.is_active` (migration `2026_06_16_000020`, default true; model fillable+cast; factory). `inventory/show` Archive/Restore (`toggleArchive`, `manage-inventory`) + banner; `inventory/index` "Show archived" toggle. Archived items are also **excluded from the quote and job item pickers** (`InventoryItem::where('is_active', true)` in both forms) while existing line references via `find()` still resolve. Suppliers have no standalone UI, so they're not archivable yet.
- **GST/HST collected report**: a date-filtered "Tax collected" section on `reports/index` (gated `view-reports`). Sums tax on **issued** invoices (Sent/Paid, excludes Draft/Void) in the `#[Url] from`/`to` range, grouped by tax component (parsed from each invoice's frozen `tax_breakdown` label, e.g. "HST (13%)" → "HST"), plus taxable sales and total. Built entirely from the tax snapshots already on invoices — for filing. No migration.
- **Labour / time tracking**: techs log billable hours on a job (`job_time_entries`, migration `2026_06_16_000029`; `JobTimeEntry` with `amount()` = hours×rate). `Job::timeEntries()`/`labourTotal()`/`loggedHours()`. "Labour & time" panel on `jobs/show` — techs (`work-jobs`) log hours + rate + note (rate prefilled from new `company_settings.default_labour_rate`, editable in Company Settings); office (`manage-jobs`) removes. **`Invoice::fromJob` now appends a labour line per time entry** (only when hours are logged) so labour is billed — `subtotal()`/`margin()`/deposit logic untouched, so the money-path tests are unaffected when no labour exists.
- **Ops board (kiosk / wall screen)** — `board` Volt page (route `/board`, auth-only) that **auto-refreshes** (`wire:poll.30s`): "Active jobs" (Scheduled/InProgress, big readable rows) + "Picking queue" (open pick lists with lines-remaining and destination). Designed for a shop TV pointed at the address on a logged-in kiosk browser. Linked from the dashboard header. No migration. *v2 idea: a tokenless display key in the URL so an unattended screen needs no login (see ROADMAP-TO-PRODUCTION.md).*
- **Developer JSON API** (first-party, no Sanctum): read-only `/api/v1` (registered via `bootstrap/app.php` `withRouting(api:)`, `api` RateLimiter defined in `AppServiceProvider`). Auth is a hashed bearer token — `api_tokens` table (migration `2026_06_16_000028`) storing only the **SHA-256 hash**; `ApiToken::issue()` returns the one-time plaintext (`ttg_…`). `AuthenticateApiToken` middleware looks the token up **tenant-agnostically** (raw query, since no tenant is initialized yet), then `tenancy()->initialize()`s the token's tenant and `auth()->setUser()`s the owner — so the normal `BelongsToTenant` scope isolates everything. Endpoints: `me`, `customers`(+show), `invoices`(+show, with totals/balance), `jobs`, `inventory` — all shaped JSON, never raw model dumps. Token management UI at `settings/api-tokens` (Admin+, plaintext shown once, revoke), linked from the account dropdown. *Read-only for v1; writes/webhooks can layer on.*
- **Editable email templates**: per-tenant, per-type email templates (`document_templates`, migration `2026_06_16_000027`; types `invoice_email`, `quote_email`). `DocumentTemplate` provides `defaults()`, `resolve()` (saved-or-default), `tokens()` (editor legend), and **`render()` — a safe whitelisted `{{ token }}` substitution (NEVER Blade/PHP eval, so no code-execution surface)**. Users edit the **subject + message wording** at `settings/templates` (gated `manage-settings`, linked from the account dropdown); the branded HTML wrapper (logo, totals table, footer) stays in Blade. `InvoiceMail`/`QuoteMail` build a variable map and render subject + body from the template (reminder keeps its fixed subject). Tokens e.g. `{{ customer_name }}`, `{{ invoice_number }}`, `{{ invoice_balance }}`, `{{ invoice_due_date }}`, `{{ company_name }}`.
- **Per-tenant outgoing email (SMTP)**: each tenant can configure its own mail server in **Company Settings → Outgoing email** (host/port/encryption/username/password/from). New columns on `company_settings` (migration `2026_06_16_000026`); `mail_password` uses the **`encrypted` cast** (never plaintext at rest) and the form keeps a blank password = "unchanged". `App\Services\TenantMailer::resolve()` registers a runtime `tenant` SMTP mailer from those creds (or falls back to the app default) and returns the from-address/name; invoice + quote sending and the reminders command all route through it. Mailables (`InvoiceMail`/`QuoteMail`/new `TestEmail`) stamp the tenant from-address in the envelope. "Send test email" action verifies config. Like Invoice Ninja's per-company SMTP.
- **Service agreements / recurring** (brief feature): contract maintenance that spawns scheduled jobs on a cadence. Tables `service_agreements`, `service_agreement_items`, + `service_agreement_id` on `service_jobs` (migration `2026_06_16_000025`). `Cadence` enum (monthly/quarterly/semiannual/annual) with `advance(Carbon)` (no month-overflow). `ServiceAgreement::generateDueJob()` creates a scheduled Job, copies the item template onto its lines, advances `next_run_at`, stamps `last_run_at`; `isDue()`. Console command `agreements:run` (scheduled `dailyAt('06:00')` in `routes/console.php`) iterates tenants and generates due active agreements. UI at `agreements` (index with pause/resume, **Generate now**, delete; create/edit form with customer/title/cadence/next-visit/line-items), gated `manage-jobs`, linked from the jobs index header. Generated jobs flow through the normal job → pick → invoice pipeline.
- **Deposits / progress billing**: a job can now be billed in stages instead of one final invoice. `Job::invoices()` (hasMany) + `amountInvoiced()`/`amountRemaining()` (pre-tax, excludes void). `Invoice::forJobAmount($job, $amount, $label)` raises a partial invoice as a single custom line with the **same tax-snapshot** as `fromJob()` (no change to `fromJob`). `jobs/show` invoicing panel shows billed/remaining, lists all invoices, and offers "Create full invoice" (only before any billing), "Bill amount" (deposit/progress, capped at the remaining balance), and "Bill remaining" (final). `createInvoice` is now guarded to no-op once any invoice exists (keeps the idempotency test green). No migration — just multiple invoice rows per job.
- **Checklists / inspections** (brief CORE feature): reusable templates → snapshotted onto jobs. Tables `checklist_templates`, `checklist_template_items`, `job_checklists`, `job_checklist_items` (migration `2026_06_16_000024`). Enum `ChecklistItemStatus` (pending/pass/fail/na). Models: `ChecklistTemplate`/`ChecklistTemplateItem`, `JobChecklist` (`fromTemplate()` snapshots items, `answeredCount()`/`isComplete()`/`hasFailures()`), `JobChecklistItem` (`mark()`); `Job::checklists()`. Template manager at `checklists` (index/create/`{id}/edit`, gated `manage-jobs`, dynamic item rows) linked from the jobs index header. On `jobs/show`: attach a template (copies items so later template edits don't mutate a filled checklist), then techs (`work-jobs`) mark each item pass/fail/N-A with an optional note; office (`manage-jobs`) removes a checklist. Progress + failure badges.
- **Short-pick / back-order**: pick-list lines can now be picked short. `pick_list_items` gained `picked_quantity` + `short_quantity` (migration `2026_06_16_000023`). The pick form takes a **quantity** (blank = full need); picking less transfers only that and records the shortfall as back-order. A **"none available"** action (`markShort`) resolves a line with the whole qty back-ordered and no stock movement. `PickListItem::markPicked($from, $qty)`/`markShort()`/`isShort()`; `PickList::backorderItems()`/`hasBackorders()` (amber banner on the pick page). Job completion now consumes `picked_quantity` (not the full need) so truck stock stays correct after a short pick. *Follow-up: feed back-orders into the reorder view.*
- **QR label generation**: printable QR label sheets (`print/labels.blade.php`, extends `layouts.print`) rendered **client-side** via cdnjs `qrcodejs` (no composer/PHP dependency). Routes (all gated `manage-inventory`, tenant-safe): `inventory/labels` (all active items), `inventory/{itemId}/label`, `assets/{assetId}/label`, `locations/labels` (bins/trucks/warehouses). Encodes scanner-ready tokens — `INV:{internal_sku}`, `AST:{serial}`, `LOC:{id}` — which the future camera-scan feature will parse. Buttons: "Print labels" on inventory + locations indexes, "Label" on item + asset show pages. *This is the label half of brief §5; scan-to-pick/receive is the remaining half.*
- **Reorder / low-stock view**: `inventory/reorder` Volt page (route before the `{itemId}` wildcard, gated `manage-inventory`) — every stock level at/below its `min_quantity`, grouped by the item's **preferred supplier** (new `InventoryItem::preferredOffering()`/`preferredSupplierName()`), showing on-hand/min/short-by, with a "New purchase order" button. Linked from the inventory index header ("Reorder"). Reuses the dashboard's low-stock query logic. No schema change.
- **Jobs schedule / dispatch board**: `jobs/schedule` Volt page (route `jobs/schedule`, registered before the `{jobId}` wildcard) — active jobs (Scheduled/InProgress) grouped by scheduled date with Today/Overdue highlighting, a "Needs scheduling" section for jobs with no date, and a technician filter (`#[Url] tech` = all / unassigned / user id). View-open (auth only, like the jobs list). Linked from the jobs index header ("Schedule"). No schema change.
- **Job notes thread**: `job_notes` table + `JobNote` model + `Job::noteThread()` (named to avoid the existing `notes` string column). A "Notes & updates" panel on `jobs/show` — techs/office (`work-jobs`) post timestamped updates; office (`manage-jobs`) can remove. Newest-first, author + timestamp. Migration `2026_06_16_000022`.
- **Serialized-asset UI (the "LEGO" nested-equipment model)**: the data layer (`serialized_assets` self-referencing tree + `asset_events` history) now has full screens. `SerializedAsset` gained behavior — `recordEvent()`, `attachTo()` (assemble → nests, inherits location, status Deployed, logs Assembled + cycle guard via `containsInSubtree()`), `detach()` (disassemble → floats up keeping the inherited location as a home, status InStock, logs Disassembled), `moveTo()` (relocates the **root**, whole tree follows, logs Moved), `retire()`. `AssetStatus::badgeClasses()`; `AssetEvent` got `parentAsset()`/`location()`/`user()`. Pages: `assets/index` (top-level units only, search + status filter), `assets/form` (register/edit; tenant-scoped unique serial; records Created), `assets/show` (recursive composition tree via `partials/asset-node`, effective location, assemble/detach/move/retire actions, event timeline). Gates: register/edit/retire = `manage-inventory`; assemble/detach/move = `move-stock` (field techs). Routes `assets`/`create`/`{assetId}/edit`/`{assetId}` (param `{assetId}`, resolved in mount). "Assets" nav link. No schema change (schema pre-existed). *Still open: camera scan-to-explore as an input layer.*
- **Stock-movement log (surfaced ledger)**: the `stock_movements` ledger now has UI. Added `StockMovement::job()`/`supplier()` relations + `StockMovementType::badgeClasses()`. `movements/index` Volt page (route `movements`, gated `manage-inventory`) — paginated 50/page, `#[Url]` filters by type + item, columns When/Item/Type/Qty/From→To/By/Reference (job # or supplier or note). Linked from the inventory index header ("Movement log"). Each item page also shows a "Recent movements" panel (latest 15) with a "View full log" deep-link pre-filtered to that item. No schema change.
- **Item photos**: completes the existing `inventory_items.photo_path` column with an upload UI on `inventory/show` (`savePhoto`/`removePhoto`, `manage-inventory`, `WithFileUploads`, stored at `item-photos/{tenant}` on the public disk, old file cleaned up on replace/remove). Shown on the item page and as a 36px thumbnail on the inventory index table. Mobile `capture="environment"`. No schema change.
- **Custom document numbering**: invoices and quotes now use a **per-tenant counter** instead of the global row id (migration `2026_06_16_000021` adds `invoice_prefix`/`invoice_next_number`/`quote_prefix`/`quote_next_number` to `company_settings`, defaults `INV-`/`Q-` from 1). `CompanySetting::allocateNumber('invoice'|'quote')` formats `prefix + zero-padded(next)` and increments the counter; the `Invoice`/`Quote` `created` hooks call it (still guarded by `if (! $number)`). Editable prefix + next-number in **settings/company** ("Document numbering" section). Bonus: numbers are now gap-free *per tenant* (the old id scheme leaked/skipped across tenants). Jobs/POs still use the id scheme. Two existing management tests were relaxed to assert the `/^INV-\d{5}$/` and `/^Q-\d{5}$/` shape.

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
| `view-audit` | Admin | read the audit trail |

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

> ✅ **Printable documents BUILT** (June 2026): branded, print-to-PDF views for invoices and quotes via the browser's print dialog (no PDF dependency). Routes `invoices/{id}/print` + `quotes/{id}/print` → `resources/views/print/*` on `layouts/print.blade.php`; "Print / PDF" links on the invoice/quote detail pages. Tenant-safe (resolved under tenancy), tests in `tests/Feature/Print/`. A server-side PDF lib (DomPDF) or emailing the document are possible later upgrades.

> ✅ **Dark theme BUILT** (June 2026): a single isolated CSS layer in `resources/css/app.css` (overrides under `.dark`), toggled by a nav button + no-flash script in the layouts. NOT Tailwind `dark:` variants (those mis-compiled here due to a v3/v4 mismatch). Lesson: do theming on a branch, verify by screenshot, never bulk find-replace blade files.

### Company profile, branding & document templates (requested June 2026)

These three are tightly related and should be done together, in this order:

1. **Company/tenant profile + settings.** A settings screen where each tenant configures its own business identity: legal name, address, phone, email, **GST/HST registration number** (required on Canadian tax invoices), default payment terms, and an invoice footer/terms blurb. Store on the tenant (the `data` column already exists) or a dedicated `company_settings` table keyed by `tenant_id`. This is the foundation the print documents read from (today they only show `tenant('name')`).
2. **Logo upload.** Per-tenant logo stored via Laravel filesystem (local now, S3-compatible later per brief §2). Show it on the print/PDF header and the nav. Watch tenant isolation on the storage path (prefix by `tenant_id`).
3. **PDF/document templating.** Settings-driven branding for the print views: logo, an accent colour, the footer/terms text, and toggles for which fields show. A full drag-and-drop template editor is out of scope; a clean "branding pulled from company settings" pass gets 90% of the value. (If a true server-side PDF is wanted, add DomPDF here.)

### Benchmark: gaps vs Invoice Ninja (verify with a fresh search next session)

Invoice Ninja was deliberately *not* forked (brief §3) — it's a yardstick for invoicing completeness. Things it has that Inordio doesn't yet, ranked by relevance to a Canadian field-service shop:

- **Online payment collection** — pay an invoice by card/e-transfer via a link (Stripe/Square/Rotessa/Interac, brief §7). We only record payments manually. *High value.*
- **Client portal** — customers log in to view/approve quotes and pay invoices. (Roadmap item below.) *High.*
- **Email delivery + automated reminders** — send the invoice and nudge overdue ones automatically. *High.*
- **Recurring invoices / subscriptions** — ties into the brief's **Service Agreements**. *Medium-high.*
- **Credit notes & refunds** — we track partial payments via balance but have no credit/refund concept. *Medium.*
- **Customer statements** — an account summary across invoices/payments. *Medium.*
- **Configurable invoice numbering** — prefixes/sequences per tenant (we use a global `INV-#####`). *Low-medium.*
- **Attachments on invoices**, multi-currency, multi-language. *Low for now (single-currency CAD, English).*

We already match or exceed IN on: true inventory + stock movements, the pick-list/truck flow, jobs/scheduling, role-based access, and Canadian provincial tax snapshotting — none of which IN does for trades.

### Remaining roadmap (build-ready)

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

# Resume from home — Inordio

Quick handoff so you (or Claude) can pick this up on another machine. **Repo:** `TTGDevX/Inordio`, branch `main`. **Last state:** 295 tests green.

> Read order for a cold start: this file → `docs/PROGRESS.md` (what's built) → `docs/ROADMAP-TO-PRODUCTION.md` (what's left) → `PROJECT-BRIEF.md` (scope) → `CLAUDE.md` (rules).

---

## 1. Get it running on a fresh machine

```powershell
git clone https://github.com/TTGDevX/Inordio.git
cd Inordio

composer install          # pulls DomPDF etc. (already in composer.json)
npm install
npm run build             # or: npm run dev  (for hot reload)

copy .env.example .env    # if you don't already have a .env
php artisan key:generate
```

**Database (local dev uses MySQL `inordio`; tests use in-memory SQLite automatically):**
- Create a MySQL database named `inordio`, set `DB_*` in `.env`, then:
```powershell
php artisan migrate
```
- (If you'd rather not run MySQL locally, set `DB_CONNECTION=sqlite` and `DB_DATABASE` to a file path, then `php artisan migrate`.)

**Create a company + login user** (once):
```powershell
php artisan tinker
```
```php
$t = \App\Models\Tenant::create(['name' => 'TTG']);
\App\Models\User::factory()->create(['tenant_id' => $t->id, 'role' => 'owner', 'name' => 'Scott', 'email' => 'scott@ttg.test']);
```
Then `php artisan serve` → open **http://localhost:8000** and log in as **scott@ttg.test / password**.

---

## 2. Running the tests

The test suite runs on in-memory SQLite (no MySQL needed):
```powershell
powershell -ExecutionPolicy Bypass -File .\run-inventory-tests.ps1
# full output -> test-results.txt
```
Every feature has tests; keep the suite green before committing.

---

## 3. Gotchas / notes

- **PDF sending** uses DomPDF (installed). If a real email send hits a memory error, bump PHP `memory_limit` to `256M`. Test `memory_limit` is already set to 512M in `phpunit.xml`.
- **Emails** default to the `log` mailer locally (check `storage/logs`), or configure per-tenant SMTP in Company settings.
- **Migrations:** if a page errors about a missing column after pulling, run `php artisan migrate` (there have been many).
- **Don't bulk find-replace Blade files**, and remember the nested `{{ ... }}` gotcha (build literal-brace strings in PHP, not in the Blade source).

---

## 4. What to do next (from the roadmap)

The build-ready feature work is essentially done. Remaining items all need Scott or infrastructure:
- **Camera barcode/QR scanning** — do this with Claude in a screenshot loop (labels/tokens already exist to scan).
- **Getting paid (Canada-first):** Interac e-Transfer auto-deposits to the bank, so the priority is recording/reconciling deposits, not a card gateway. EFT/Rotessa for recurring; card pay-by-link only if wanted.
- **Accounts/decisions:** expense OCR (AI gateway), Sage, Discord, recurring invoices.
- **Production hardening (the real gate to launch):** deployment, TLS + per-tenant subdomains, backups, error monitoring, security pass, prod queue/scheduler, S3-style uploads.

## 5. Test these 3 by hand ASAP

1. **Tenant isolation** — two companies, confirm neither can see the other's data (customers/jobs/invoices/inventory/API).
2. **Money path** — quote → job → pick → deposit + final invoice → record an Interac e-Transfer payment → email receipt; verify provincial tax, balance math, and the GST/HST report.
3. **Real email + PDF** — set SMTP, send a test, then email an invoice/quote to yourself and confirm the PDF attaches and opens.

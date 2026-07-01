# Inordio — Roadmap to Production

> Living backlog of everything still open, and an honest read on how close we are to a real launch. Companion to `PROGRESS.md` (what's built) and `REVIEW-AND-GAPS.md` (brief scorecard). Last updated **July 1, 2026** — 252 tests green.

## Where we are

The full field-service spine (multi-tenant → inventory → customers → quotes → jobs → invoices → payments) plus a large amount of breadth and polish is built and tested: serialized-asset "LEGO" UI, stock-movement log, item photos, reorder, QR labels, short-pick/back-order, checklists/inspections, deposits/progress billing, service agreements, per-tenant SMTP, editable email templates, and a first-party JSON API. Feature-wise this is **past MVP** and into "competitive product" territory.

What remains is (A) a handful of build-ready features, (B) things that need an account or credential, (C) product decisions, and (D) production hardening. Details below.

---

## A. Build-ready features (no external dependencies — we can just build these)

1. **Ops display boards (kiosk/wall screen)** — auto-refreshing read-only screens a shop TV points at: today's jobs + open pick lists. **✅ v1 built (logged-in kiosk, `/board`, auto-refresh).** *v2 remaining = tokenless display key in the URL so an unattended screen needs no login.*
2. **Camera barcode/QR scanning** — scan-to-pick / scan-to-receive; labels + tokens already exist to scan. *Needs a short screenshot loop to tune the camera UX.*
3. **Labour / time tracking on jobs** — techs log hours; hours become invoice lines. **✅ built** (per-entry hours×rate on the job, flows onto the invoice; default rate in Company Settings).
4. **GST/HST collected report** — tax-period summary of tax collected for filing. **✅ built** (date-filtered "Tax collected" section on Reports, grouped by component from invoice snapshots).
5. **Payment receipt emails** — email a receipt when a payment is recorded. **✅ built** (opt-in checkbox on the payment form; editable `payment_receipt` template).
6. **Customer sign-off / signature capture** — customer signs on job completion; attaches to the job. **✅ built** (on-screen signature canvas → stored image + signer name + time on the job).
7. **Server-side PDF (DomPDF)** — attach real PDF invoices/quotes to emails. **✅ built** (guarded by `class_exists`; run `composer require barryvdh/laravel-dompdf` on the host to activate the attachment).
8. **Back-orders feed the reorder view** — short-picked shortfalls surface as suggested reorders. **✅ built** ("Back-ordered from picks" section on Reorder, open jobs only).
9. **API writes + webhooks** — **🟡 writes built** (POST customers/jobs, PATCH customers, gate-enforced). Outbound **webhooks** remain.
10. **User-editable print/PDF templates** — email templates are editable; document templates are still fixed Blade.
11. **In-app / email notifications** — "job assigned to me", "invoice overdue", "stock low". **🟡 started** — in-app database notifications built with a "job assigned" type + Alerts inbox/badge; more types (overdue, low stock) and email channel can layer on.
12. **Global search** — one box to jump to any customer / job / invoice / item. **✅ built** (`/search`, live, tenant-scoped).

## B. Needs an account or credentials from Scott

13. **Online payments + pay-by-link** — Stripe / Square / Interac / Rotessa. Needs gateway account(s).
14. **Client portal** — customers log in to view/approve quotes and pay invoices (depends on #13).
15. **Expense / receipt OCR** — needs the TTG local AI-gateway endpoint/auth details (brief §8).
16. **Sage integration** — accounting export/sync (brief phase 8).
17. **Discord integration** — notifications (brief phase 7).

## C. Product decisions

18. **Recurring invoices** (distinct from recurring *jobs*, which service agreements already do).
19. **Estimate revisions / quote versioning** — track v1/v2 of a quote.
20. **Per-item vs per-location stock minimums** — confirm which the reorder logic should use (currently per-location).

## D. Production hardening (before real external customers)

21. **Deployment** — the production "egg"/container, environment config, zero-downtime deploys.
22. **Subdomain tenancy + TLS** — per-tenant subdomains and certificates (brief §3).
23. **Backups + restore drill** — automated DB backups and a tested restore.
24. **Error monitoring + logging** — Sentry/Flare-style capture, uptime checks.
25. **Security pass** — auth rate-limiting, optional 2FA, dependency audit, secrets handling, file-upload scanning.
26. **Object storage** — move uploads (logos/photos) to S3-compatible storage (brief §2).
27. **Queue + scheduler in prod** — run `schedule:work` / a worker for reminders, agreements, emails.

---

## Suggested build order (build-ready first, since the rest is gated)

Boards → labour/time tracking → GST/HST report → payment receipts → signature capture → server-side PDF → back-orders→reorder → notifications → global search → API writes. Then the gated items as accounts/decisions land, then the hardening block before any external launch.

---

## Pre-production readiness — honest assessment

**Feature completeness: ready for a closed beta now.** With one friendly first customer doing **manual** payment entry, the product already does real work end-to-end: quote → job → schedule → pick → complete (with checklists/photos) → invoice (incl. deposits) → record payment → email/statement, plus recurring agreements and an API. That's genuinely more than many shipping competitors.

**The gap to a real pre-production launch is mostly operational, not features:**

- **Must-have before any external user:** the hardening block (D) — deployment, TLS/subdomains, backups, error monitoring, a security pass, prod queue/scheduler, object storage. This is the real remaining work and it's largely infra, not app code.
- **Strongly-wanted for a paid launch:** online payments (#13) so customers self-pay, and server-side PDF (#7) so emailed invoices carry the document.
- **Nice-to-have, not blocking:** everything else in A/B/C.

**Rough framing:** the *application* is ~85–90% of the way to a beta-ready product; the *launch* (a production environment you'd trust a paying customer on) is gated mainly by the hardening block, which we haven't started because it needs infra decisions and, in places, accounts. Net: we can keep closing build-ready features to 100% on the app side now, and the path to production is then a focused hardening sprint plus the couple of paid integrations.

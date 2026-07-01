# Inordio — Whole-Project Review vs. Original Brief (June 2026)

A step-back comparison of what's built against **PROJECT-BRIEF.md**, plus practicalities to borrow from Invoice Ninja and things not in the brief that I think we should add. Written as a planning input — no code changes here.

---

## 1. The core promise — are we delivering it?

Brief §1: a field-service platform whose differentiator is **real inventory** (actual quantities per location: warehouse + trucks), built for trades, Canadian-first, without the $500/mo price tag.

**Verdict: the heart is built and solid.** True multi-location inventory, a stock-movement ledger, the warehouse→truck→job pick/consume flow, Canadian provincial tax snapshotting, and the full quote→job→invoice→payment spine all work and are tested (126 tests). That's the differentiated core competitors (Jobber/Housecall/Invoice Ninja) don't do for trades. Good place to be.

The gaps are mostly **breadth** (features around the core) and **polish/practicality** (delivering documents, getting paid, looking professional), not the core itself.

---

## 2. Feature-map scorecard (brief §4 CORE)

| Feature | Status | Notes |
|---|---|---|
| Multi-tenant + Auth | ✅ | |
| Inventory + Locations | ✅ | |
| Truck Stock + Transfers | ✅ | via StockManager + pick list |
| **Purchase Orders** | ❌ | receiving is manual (`StockManager::receive`); no PO/receipt entity. Legacy map had Supplier→PO→POLineItem/POReceipt. |
| Customers | ✅ | |
| Quotes + Approvals | ✅ | |
| **Service Agreements** | ✅ | built June 2026 — recurring maintenance spawns scheduled jobs on a cadence (`agreements:run`), copies a line template, advances the schedule. Recurring *invoices* specifically could layer on later. |
| Jobs + Scheduling | ✅ | jobs + a **dispatch board** (jobs by date, Today/Overdue, needs-scheduling, technician filter) built June 2026. A drag-to-reschedule calendar could come later. |
| **Equipment Tracking (serialized/nested)** | ✅ | **UI built (June 2026):** assets index/register/tree-show, assemble/disassemble/move/retire with event logging + location inheritance. Only camera scan-to-explore remains. |
| **Checklists / Inspections** | ✅ | built June 2026 — reusable templates snapshotted onto jobs; techs mark pass/fail/N-A + notes; progress & failure tracking. |
| Invoicing + Payments | 🟡 | full invoicing + **manual** payments; no online collection. |
| **Expense / Receipt Scanning** | ❌ | Phase 6; needs the AI gateway. |
| **Sage Integration** | ❌ | Phase 8. |
| User Roles + Permissions | ✅ | RBAC across all modules. |
| Reports + Dashboards | 🟡 | ops dashboard done; **no reports** (AR aging, stock valuation, etc.). |
| **Discord Integration** | ❌ | Phase 7. |

Added beyond the brief this build: ops dashboard, **printable PDF quotes/invoices**, dark theme.

---

## 3. Inventory-spec gaps (brief §5 — the differentiator's fine print)

The engine is built, but several spec'd pieces are missing and they're what make the differentiator *feel* complete:

- **QR label generation** ✅ (June 2026) — printable QR label sheets for items, bins/trucks, and serialized assets; encodes `INV:`/`AST:`/`LOC:` tokens for the scanner. (Pick-list label sheets could be added later.)
- **Camera barcode/QR scanning** ❌ — scan-to-pick, scan-to-receive, auto-deduct. Not built (top of build-ready roadmap; labels now exist to scan).
- **Serialized-asset UI** ✅ (June 2026) — tree view, assemble/disassemble/move/retire with event history. Scan-to-explore pending the scanner.
- **Item photos** ✅ (June 2026) — upload/replace/remove on the item page + thumbnails on the list.
- **Back-order tracking / short-pick flagging** ✅ (June 2026) — pick a line short (quantity picked vs needed) or mark it "none available"; the shortfall is recorded as back-order and completion consumes only what was picked.
- **Movement history UI** ✅ (June 2026) — global movement log + per-item recent-movements panel.
- **Low-stock alerts** 🟡 — dashboard shows low-stock count/list; no real notifications, and minimums are per-location (brief said per-item — confirm which you want).

---

## 4. Practicalities to borrow from Invoice Ninja (your ask)

You specifically want IN-style templating of the documents and emails. Concretely:

- **Email delivery** ✅ — quotes/invoices/reminders send via Mailables; **per-tenant SMTP** is configurable in Company Settings (June 2026). *Templating* of the subject/body is still fixed in Blade — user-editable templates are the remaining piece (in progress).
- **Automated reminders** ✅ — `invoices:send-reminders` scheduled daily; reminder body is the invoice email (reminder variant).
- **Document/PDF templates** 🟡 — branding (logo, colours, footer/terms) IS pulled from company settings; a user-editable template editor is the open part.
- **Recurring invoices / auto-billing** 🟡 — the brief's **Service Agreements are built** (recurring *jobs*); recurring *invoices* specifically could layer on.
- **Client portal + pay-by-link** ❌ — customers view/approve/pay online.
- **Customer statements, credit notes, configurable numbering** ❌ — standard IN invoicing niceties.

**Suggested sequence for the IN-style block:** company profile + logo → branded PDF templates → email sending (with templates) for quotes/invoices → automated overdue reminders → online payment links → client portal. Each builds on the last.

---

## 5. Things NOT in the brief that I think we should add

1. **Audit log** (high) — who changed/voided what, when (esp. invoices, payments, stock). The legacy notes had `AuditLog`; for a multi-tenant financial system it's important for trust and disputes. Low effort with a model observer.
2. **Money as integer cents** (high, do before real $ flows) — totals are stored as decimals and computed with floats; we already saw float rounding in the QST test. Standard practice is integer cents (or a money cast) to avoid penny drift on invoices. Worth fixing while the data is still small.
3. **Soft deletes / no hard-delete of financial records** (medium) — invoices/payments/jobs should archive, not vanish. We have `void` for invoices; extend the pattern and add soft deletes.
4. **Job photos & notes** (medium) — legacy map had `JobPhoto`/`JobNote`; techs documenting work with photos is a real trades need and a nice differentiator vs. plain invoicing tools.
5. **Deposits / progress billing** ✅ (June 2026) — jobs can be billed in stages (deposit / progress / final) via `Invoice::forJobAmount`, capped at the uninvoiced balance, each a real tax-snapshotted invoice.
6. **In-app + email notifications** (medium) — low stock, overdue invoice, job assigned-to-me.
7. **Configurable per-tenant invoice/quote numbering** (low-med) — prefixes/sequences instead of a global `INV-#####`.
8. **CSV export** (low-med) — accountant/Sage handoff before the full Sage integration lands.

---

## 6. Recommended order when work resumes

1. **Company profile + logo + branded PDF templates** (build-ready, unblocks looking professional).
2. **Email sending + templates** for quotes/invoices (the real "give out info" completion). *Needs SMTP.*
3. **Money-as-cents refactor + audit log** (do before pushing real invoicing volume — cheaper now than later).
4. **Camera scanning + QR labels + serialized-asset UI** (completes the inventory differentiator).
5. **Online payments → automated reminders → client portal** (the IN-style getting-paid loop). *Needs gateway accounts.*
6. Then the remaining brief phases: expenses/OCR, Discord, Sage, reports; and production hardening (TLS/subdomains/backups/egg) before any external customer.

**Decisions needed from Scott:** AI gateway details (endpoint/protocol/auth), payment gateway + SMTP accounts, per-item vs per-location stock minimums, and whether to do the money-as-cents refactor (recommended yes).

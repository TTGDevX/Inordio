# Inordio — Project Brief & Development Context

> **Purpose:** Hand this file to Claude Code (drop it in the repo root or reference it at session start) so it has full project context. Treat this as the source of truth for product scope and architectural decisions.

**Product Name:** Inordio — *"Keep your business in order"* (from Latin "in ordo")
**Organization:** TTGDevX
**Repository:** https://github.com/TTGDevX/Inordio
**Owner / Product:** Scott Thompson (Thompson Technology Group, London, Ontario)
**Last updated:** June 2026

---

## 1. What Inordio Is

Inordio is a **multi-tenant field service management SaaS platform** for trades businesses: MSPs, plumbing, HVAC, electrical, and similar field service companies.

**The problem it solves:** Most field service software (Jobber, Housecall Pro, etc.) treats inventory as a price list. They don't track *actual quantities* — like knowing you have 5 copper fittings in the warehouse and 3 in Mike's truck. Businesses either run spreadsheets on the side or fly blind. Nothing on the market does real inventory properly without costing $500+/month.

**First customer:** Thompson Technology Group (TTG), Scott's own MSP. TTG dogfoods Inordio internally before it's sold externally. A plumbing company is lined up as the first external beta customer.

**Target market:**
- MSPs (like TTG)
- Plumbing companies
- HVAC contractors
- Electrical contractors
- Other field service businesses

**Key differentiators:**
- True inventory with multi-location tracking (warehouse + trucks)
- Canadian-first: provincial tax handling, Canadian payment stack, Sage integration
- Built for trades from the ground up, not retrofitted
- Discord bot as an operations command center

---

## 2. Tech Stack (DECIDED — do not relitigate)

> History note: the project was originally scaffolded as Next.js/TypeScript/tRPC/Prisma/PostgreSQL. It was **deliberately rebuilt around Laravel** after consultation with the lead dev. Laravel is the stack. Don't suggest switching back.

| Layer | Technology | Rationale |
|-------|------------|-----------|
| Backend | Laravel 12 | Batteries included, fast development (brief originally said 11; scaffolded on current major June 2026) |
| Frontend | Livewire 3 + Blade | Real-time UI without JS framework complexity |
| Database | MySQL 8 | Well-known, great Laravel support |
| Multi-tenancy | stancl/tenancy | Automatic tenant isolation |
| Auth | Laravel Breeze | Built-in, fast setup |
| CSS | Tailwind CSS | Ships with Laravel |
| UI components | Flux UI or Blade UI Kit | Pre-built Livewire components |
| PDF generation | DomPDF or Snappy | Quotes, invoices, reports |
| Queues | Laravel Queues (database or Redis) | Emails, PDFs, notifications |
| File storage | Laravel Storage (local → S3-compatible later) | Receipts, photos, documents |
| API | Laravel API Resources | For Discord bot + future mobile |

**Quick start:**
```bash
laravel new inordio
cd inordio
composer require laravel/breeze livewire/livewire stancl/tenancy
php artisan breeze:install livewire
php artisan tenancy:install
npm install && npm run build
php artisan migrate
php artisan serve
```

---

## 3. Architecture Decisions

### Multi-tenancy
**Single database, tenant_id column isolation** via stancl/tenancy.

```
┌─────────────────────────────────────────────────────┐
│                      MySQL                          │
│  Every table has tenant_id column                   │
│  ┌────────────┐ ┌──────────────┐ ┌─────────────┐    │
│  │ TTG MSP    │ │ ABC Plumbing │ │ XYZ HVAC    │    │
│  │ tenant_id=1│ │ tenant_id=2  │ │ tenant_id=3 │    │
│  └────────────┘ └──────────────┘ └─────────────┘    │
│  Middleware auto-filters all queries by tenant      │
└─────────────────────────────────────────────────────┘
```

Rules:
- **Every model needs tenant_id** — use a trait or base model
- **Test tenant isolation early and often** — data leakage between tenants is the worst possible bug in this product
- Tenant identification: **resolved from the authenticated user for now** (app is served by IP during alpha — no subdomains available). One login page; each user belongs to a tenant; all requests scoped to that tenant after auth. Design so subdomain identification (ttg.inordio.ca) can be enabled later without rework once domains/TLS exist.

### Encryption
**Server-side encryption** for sensitive fields using Laravel's built-in encrypted casts:

```php
protected $casts = [
    'sensitive_field' => 'encrypted',
];
```

The original zero-knowledge/E2EE architecture was **descoped** for MVP. Trades businesses need "don't get hacked," not zero-knowledge. Client-side encryption can be added later if customers demand it.

### One integrated application — no forks
Everything is built **natively in Inordio as a single codebase**. Do not fork or embed third-party apps for core features — e.g. **Invoice Ninja was considered for invoicing and explicitly rejected**. Invoicing, quoting, inventory, etc. are all first-party Inordio modules sharing one data model and one tenancy layer. External services are fine as *integrations* (Stripe, Sage, the AI gateway), but never as forked codebases.

### Platform
Web app, **mobile-first UI** (techs use phones in the field, sometimes in basements with no signal). PWA approach — installable, no app stores, instant updates. Offline support is an open question (see §9).

### Deployment (alpha/beta)
Runs on TTG's Proxmox infrastructure under **Pterodactyl**, which TTG already uses as a general-purpose container orchestrator (the panel itself is Laravel — same stack). Plan:

1. **Now:** app served **by IP** — no domain, no subdomains, no TLS termination concerns during early alpha
2. **Once stable:** package Inordio as a **Pterodactyl egg** — app container (PHP/nginx) + queue worker + `schedule:work` as persistent processes; MySQL outside the egg
3. **Before external beta:** front with a reverse proxy + wildcard TLS (`*.inordio.ca`), enable subdomain tenant identification, settle backup strategy (MySQL dumps + storage)

Zero-downtime deploys are explicitly not a goal until external customers exist.

---

## 4. Feature Map

```
CORE (MVP)                          ENHANCED (Post-MVP)
─────────────────────────          ─────────────────────────
✓ Multi-tenant + Auth              • Customer Portal
✓ Inventory + Locations            • Online Booking
✓ Truck Stock + Transfers          • Route Optimization
✓ Purchase Orders                  • GPS Live Tracking
✓ Customers                        • SMS Notifications (Twilio)
✓ Quotes + Approvals               • Flat Rate Pricing Book
✓ Service Agreements               • Franchise Multi-Branch
✓ Jobs + Scheduling                • Subcontractor Management
✓ Equipment Tracking               • Financing Integration
✓ Checklists / Inspections         • Permit Tracking
✓ Invoicing + Payments             • Commission Tracking
✓ Expense / Receipt Scanning       • Call Logging
✓ Sage Integration                 • Zapier Integration
✓ User Roles + Permissions         • QuickBooks Integration
✓ Reports + Dashboards             • White-label Option
✓ Discord Integration              • Native Mobile App (if needed)
```

---

## 5. Inventory Spec (the differentiator — build this right)

The core flow everything hangs off:

```
Inventory in warehouse
       ↓
Quote job (pulls from inventory)
       ↓
Job created → pick list generated (QR-coded)
       ↓
Tech scans pick list, picks items (warehouse → truck transfer)
       ↓
Do job (deduct from truck)
       ↓
Invoice (knows exactly what was used)
```

If inventory isn't solid, everything downstream is garbage.

### Inventory Items
- Name, description
- **Dual SKU system** (explicit Scott requirement):
  - **Internal SKU** — company's own product code (e.g. `TTG-CAT6-BLU-1000`)
  - **Vendor SKU** — the supplier's product code
- Barcode
- **Cost** (what you pay) vs **Price** (what you charge) — both required
- Category (e.g. "Networking", "Cabling", "Parts")
- Unit of measure
- Item photos
- Vendor/supplier info

### Multi-Location Stock
- Location types: Warehouse, Truck A, Truck B, Job Sites
- Stock quantity tracked **per location**
- Stock transfers between locations (Warehouse → Truck → Job)
- Each truck is a "mini-warehouse"; techs see their own truck stock
- Real-time sync

### Serialized Assets & Nested Assemblies — "the LEGO model"
This is the full spec behind **Equipment Tracking** in the feature map. Inventory has **two modes** side by side:

1. **Quantity stock** (fungible) — 5 copper fittings, 300 ft of CAT6. Counts per location, no individual identity. Everything above describes this mode.
2. **Serialized assets** — individual units with a serial number, identity, history, and **nesting**.

The LEGO principle: pieces combine into sets, sets go into boxes — every level has its own serial, and scanning any level reveals what's inside. Concrete example:

```
Job Site: ABC Corp
└── Rack R-001 (serial: RK-2231)
    ├── Server SRV-014 (serial: SV-8812)
    │   ├── Hard Drive (serial: WD-44521)
    │   └── Hard Drive (serial: WD-44522)
    └── Switch (serial: UB-71h22)
```

Rules:
- Every serialized asset has an optional **parent asset** (self-referencing relationship). **Arbitrary nesting depth** — do not hardcode levels like piece/set/box.
- **Location is inherited from the topmost parent.** Move the rack to a job site and everything inside it is automatically at that site.
- **Assembly and disassembly are recorded events.** Pulling a drive from quantity stock and installing it into a server creates history; a warranty swap later shows "WD-44521 was in SV-8812 from March–June, replaced."
- Scan any serial → walk **down** (what's inside?) or **up** (what is this part of, and which customer has it?).
- Payoffs: instant warranty claims, recall lookups, theft/shrinkage tracing, invoices that enumerate exactly what was delivered.
- Side benefit: nested assemblies are a lightweight bill-of-materials — groundwork for future manufacturing-adjacent verticals without building an MES.
- **Schema lands in Phase 1** alongside items/stock (retrofitting the self-reference later is painful). Tree-view/scan-to-explore UI can come later.

### Barcode Scanning
- Camera-based (web camera + mobile phone camera)
- Scan when picking items for jobs and when receiving inventory
- Auto-deduct on scan
- Inordio **generates QR labels** for items, shelf/bin locations, trucks, serialized assets, and pick lists

### Pick Lists & QR Workflow
The flow connecting sales to the warehouse floor:

1. Quote is approved → **job** is created → job generates a **pick list** from the quoted line items
2. The pick list carries a **QR code** (printed or on a dispatch screen) encoding its ID
3. Tech scans the pick list QR with their phone → the live pick list opens on screen
4. Tech scans each item's barcode as they pull it → item **checks off** and the scan records a stock movement (warehouse → truck); the pick is effectively a guided transfer
5. **Serialized assemblies integrate**: scanning an assembly's serial checks off all nested components in one scan
6. Short picks (item unavailable) flag the line and feed **back-order tracking**
7. Pick list shows live progress — dispatcher can see picking status per job

### Alerts & History
- Low stock alerts with per-item minimum quantities
- Back-order tracking
- Full inventory movement history (transfers, usage, adjustments)

---

## 6. Development Phases & Build Order

Build order rationale: invoicing needs customers, line items, parts, labor, and tax — so it comes last in the core chain. Inventory comes early because it's the differentiator and quotes depend on it.

| Phase | Scope | Notes |
|-------|-------|-------|
| **0** | Foundation: Laravel install, Git, MySQL, stancl/tenancy, Breeze auth, user roles (Owner/Admin/Tech), tenant settings | ~Weeks 1–2 |
| **1** | Inventory core: locations (warehouse/trucks), items (dual SKU, cost/price), stock levels per location, transfers | ~Weeks 3–4. **First usable milestone for TTG** |
| **2** | Customers | Needed before quoting |
| **3** | Quotes + approvals | Pulls from inventory |
| **4** | Jobs + scheduling/dispatch | Quote → Job conversion |
| **5** | Invoicing + payments | Job → Invoice conversion; Canadian taxes |
| **6** | Expenses + receipt scanning (OCR) | |
| **7** | Discord bot | Can be built in parallel — relatively independent |
| **8** | Sage integration, reports, dashboards, polish | |

**Timeline estimate:** ~30 weeks to full production SaaS on the Laravel stack. MVP for internal TTG use lands much earlier (Phases 0–1 are usable on their own for warehouse/truck tracking).

### Rollout Plan
| Stage | Tenant | When | Purpose |
|-------|--------|------|---------|
| Alpha | TTG (Scott's MSP) | Phases 0–4 | Dogfooding, find bugs, refine UX |
| Beta | Plumbing company | After Phase 5 | First external customer, validate for trades |
| Launch | Public SaaS | After Phase 8 | Open signups |

---

## 7. Canadian Tax Reference

Tax calculation is based on **customer location** (province):

| Province | Tax | Rate | Lines |
|----------|-----|------|-------|
| Ontario | HST | 13% | 1 |
| Alberta | GST | 5% | 1 |
| BC | GST + PST | 5% + 7% | 2 |
| Saskatchewan | GST + PST | 5% + 6% | 2 |
| Manitoba | GST + PST | 5% + 7% | 2 |
| Quebec | GST + QST | 5% + 9.975% | 2 |
| New Brunswick | HST | 15% | 1 |
| Nova Scotia | HST | 15% | 1 |
| PEI | HST | 15% | 1 |
| Newfoundland | HST | 15% | 1 |
| Yukon / NWT / Nunavut | GST | 5% | 1 |

> Verify current rates before shipping the tax calculator — provincial rates change (e.g. Nova Scotia announced HST changes). Build rates as data, not hardcoded constants.

**Payments (Canadian-first):** Stripe, Square, Rotessa, PayPal. Interac/e-Transfer support is a selling point.

---

## 8. AI Integration

AI features are routed through **TTG's local AI gateway API** (self-hosted on TTG infrastructure) rather than calling cloud AI providers directly. This keeps customer data on-prem and gives one place to manage models, keys, and costs.

Architecture rule: Inordio talks to a single internal `AiGateway` service class — no AI provider SDKs scattered through the codebase. Swapping models/backends happens at the gateway, not in app code.

**Planned AI features (by phase they attach to):**
| Feature | Phase | Notes |
|---------|-------|-------|
| Receipt/expense extraction | 6 | Vision model via gateway extracts vendor, totals, tax lines; likely replaces the Google Vision vs Textract decision |
| Discord bot natural language ops | 7 | "How many CAT6 boxes on Mike's truck?", "Draft a quote for ABC Plumbing" |
| Quote/job drafting assistance | 3–4 | Draft descriptions, suggest line items from similar past jobs |
| Inventory insights | Post-MVP | Reorder suggestions from usage history, seasonal patterns |

**To confirm with Scott:** gateway endpoint/protocol (OpenAI-compatible?), available models (vision-capable needed for receipts), auth scheme, and per-tenant data handling rules.

---

## 9. Open Questions (resolve with Scott before building the affected pieces)

- [ ] Offline sync strategy for the mobile PWA (techs in no-signal basements)
- [ ] OCR provider for receipt scanning: local AI gateway (preferred, see §8) vs Google Vision vs AWS Textract
- [ ] AI gateway details: endpoint, protocol, models, auth (see §8)
- [x] Hosting: **decided** — Pterodactyl on Scott's Proxmox infra; IP access now, packaged as a Pterodactyl egg once stable (see §3 Deployment)
- [x] Tenant identification: **decided** — resolved from authenticated user during alpha (IP access, no subdomains); subdomain identification enabled later (see §3)
- [ ] Current actual state of the repo — it was scaffolded in Dec 2025; **audit what exists before writing new code**

---

## 10. Related Project: MOX

MOX is a separate internal TTG inventory tool (built by "Script," a developer friend). Inordio's inventory spec is partly informed by gaps found in MOX. Feature parity targets identified from that comparison: item photos, categories, vendor/supplier tracking, cost vs price split, barcode scanning, stock transfers, low stock alerts, inventory history, dual SKU. Inordio should cover all of these natively.

---

## 11. Working Conventions (how Scott likes to work)

- **Audit before assuming.** Check the actual repo state, existing migrations, and configs before generating new code.
- **Do it correctly over quickly.** No shortcuts that create rework.
- **Concise, copy-paste-ready commands** when giving Scott anything to run manually.
- **Mobile-first UI** for anything techs touch.
- **Test tenant isolation** with every feature — write tests proving tenant A can't see tenant B's data.
- Start simple: get Phases 0–1 rock solid before stacking features.

---

*Built with ☕ in London, Ontario, Canada.*

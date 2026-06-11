# Salvage Notes from the Next.js Scaffold (Dec 2025)

> The original Next.js/Prisma scaffold lives on the `archive/nextjs-scaffold-2025` branch.
> This file captures what's worth carrying into the Laravel build. Destination: `docs/` in the new repo.

## Carry forward

### 1. Five-role user model (better than the brief's three)
The old Prisma schema defined: `OWNER / ADMIN / OFFICE / TECHNICIAN / VIEWER`.
The brief only lists Owner/Admin/Tech. Use the five-role version — real shops have
office staff who book jobs but shouldn't manage settings, and accountants/partners
who need read-only access.

### 2. Entity map (from old docs/DATABASE.md)
Five layers, translates directly to Laravel migrations:

- **Tenant layer:** Tenant → User (+ Certification, TimeEntry, PTORequest), Location, Customer → Equipment, Settings
- **Inventory layer:** Supplier → PurchaseOrder → POLineItem/POReceipt; InventoryItem → StockLevel (per location) → StockTransfer; Category; ~~Kit → KitComponent~~ → superseded by the serialized-asset nesting model (brief §5)
- **Sales layer:** Customer → Quote → QuoteLineItem → Job; ServiceAgreement → AgreementVisit; Invoice → Payment
- **Field layer:** Job → JobPart, JobLabor, JobPhoto, JobNote, JobChecklist → ChecklistResponse, Equipment
- **Expense layer:** Expense → Receipt (OCR data)

Note: TimeEntry, PTORequest, Certification weren't in the brief's feature map —
light HR features the old scaffold envisioned. Park as post-MVP candidates.

### 3. Tenant/domain details from old schema
- Tenant: `slug` (subdomain), `status` (active/suspended/cancelled), `plan` (trial/solo/team/pro/enterprise), `trialEndsAt`, Stripe `subscriptionId`
- User: per-tenant unique email (`tenantId + email`), invite flow (`PENDING` status), MFA fields
- Location: type enum (warehouse/truck/jobsite), truck → assigned user (one tech per truck)
- AuditLog: action/entity/entityId + ip/userAgent, indexed by (tenant, createdAt) and (tenant, entity)

### 4. GitHub repo hygiene
Issue templates (bug/feature), PR template, CI workflow shape — reuse with
Laravel commands (composer, artisan test, pint) swapped in.

## Deliberately NOT carried forward
- Zero-knowledge / client-side E2EE (descoped — Laravel encrypted casts instead)
- PostgreSQL + RLS (now MySQL + stancl/tenancy global scopes)
- tRPC / Prisma / Better-Auth / pnpm monorepo (now Laravel monolith + Breeze)
- `encryptedX` column naming convention (tied to the E2EE design)

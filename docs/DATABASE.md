# Inordio Database Schema

## Overview

Inordio uses PostgreSQL with Row-Level Security (RLS) for multi-tenant data isolation. All business data is encrypted client-side before storage.

## Schema Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                         TENANT LAYER                                │
├─────────────────────────────────────────────────────────────────────┤
│  Tenant ─────┬───── User ──────── Certification                     │
│              │        └───────── TimeEntry                          │
│              │        └───────── PTORequest                         │
│              ├───── Location (warehouse, truck)                     │
│              ├───── Customer ──── Equipment                         │
│              └───── Settings                                        │
└─────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│                       INVENTORY LAYER                               │
├─────────────────────────────────────────────────────────────────────┤
│  Supplier ─────────── PurchaseOrder ──── POLineItem                 │
│                            └──────────── POReceipt                  │
│                                                                     │
│  InventoryItem ────── StockLevel (per location)                     │
│       │                    └─── StockTransfer                       │
│       │                                                             │
│  Category ────────── InventoryItem                                  │
│  Kit ────────────── KitComponent                                    │
└─────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│                         SALES LAYER                                 │
├─────────────────────────────────────────────────────────────────────┤
│  Customer ────── Quote ────── QuoteLineItem                         │
│      │              └───────── Job (converted)                      │
│      ├───── ServiceAgreement ──── AgreementVisit                    │
│      └────────────────── Invoice ──── Payment                       │
└─────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│                        FIELD LAYER                                  │
├─────────────────────────────────────────────────────────────────────┤
│  Job ─────┬───── JobPart                                            │
│           ├───── JobLabor                                           │
│           ├───── JobPhoto                                           │
│           ├───── JobNote                                            │
│           ├───── JobChecklist ──── ChecklistResponse                │
│           └───── Equipment (installed/serviced)                     │
└─────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│                       EXPENSE LAYER                                 │
├─────────────────────────────────────────────────────────────────────┤
│  Expense ──── Receipt (OCR data)                                    │
└─────────────────────────────────────────────────────────────────────┘
```

## Row-Level Security Setup

After running Prisma migrations, execute this SQL to enable RLS:

```sql
-- ===========================================
-- ENABLE ROW LEVEL SECURITY
-- ===========================================

-- Tenant table (special handling - no RLS needed as tenants access own record)
-- Users table
ALTER TABLE "User" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "User" FORCE ROW LEVEL SECURITY;

-- Create policies
CREATE POLICY user_tenant_isolation ON "User"
  FOR ALL
  USING (tenant_id = current_setting('app.current_tenant_id', true)::uuid)
  WITH CHECK (tenant_id = current_setting('app.current_tenant_id', true)::uuid);

-- Location table
ALTER TABLE "Location" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "Location" FORCE ROW LEVEL SECURITY;

CREATE POLICY location_tenant_isolation ON "Location"
  FOR ALL
  USING (tenant_id = current_setting('app.current_tenant_id', true)::uuid)
  WITH CHECK (tenant_id = current_setting('app.current_tenant_id', true)::uuid);

-- Audit Log
ALTER TABLE "AuditLog" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "AuditLog" FORCE ROW LEVEL SECURITY;

CREATE POLICY audit_tenant_isolation ON "AuditLog"
  FOR ALL
  USING (tenant_id = current_setting('app.current_tenant_id', true)::uuid)
  WITH CHECK (tenant_id = current_setting('app.current_tenant_id', true)::uuid);

-- Repeat for all tenant-scoped tables...
```

## Setting Tenant Context

Before executing queries, set the tenant context:

```typescript
// In your API middleware
await prisma.$executeRawUnsafe(
  `SET app.current_tenant_id = '${tenantId}'`
);

// Execute your queries...

// Reset after (optional, connection pooling handles this)
await prisma.$executeRawUnsafe(`RESET app.current_tenant_id`);
```

## Encrypted Fields Convention

All encrypted fields follow this naming pattern:
- `encryptedName` - Encrypted company/person name
- `encryptedAddress` - Encrypted address
- `encryptedPhone` - Encrypted phone number
- `encryptedSettings` - Encrypted JSON blob

Encrypted values are stored as strings in this format:
```
{version}.{iv_base64}.{ciphertext_base64}
```

Example:
```
1.dGVzdGl2MTIzNDU2.YWJjZGVmZ2hpamtsbW5vcHFyc3R1dnd4eXo=
```

## Indexes

Key indexes for performance:

```sql
-- Tenant isolation (on every table)
CREATE INDEX idx_user_tenant ON "User" (tenant_id);
CREATE INDEX idx_location_tenant ON "Location" (tenant_id);

-- Common queries
CREATE INDEX idx_user_email ON "User" (tenant_id, email);
CREATE INDEX idx_location_type ON "Location" (tenant_id, type);
CREATE INDEX idx_location_active ON "Location" (tenant_id, is_active);

-- Audit queries
CREATE INDEX idx_audit_tenant_date ON "AuditLog" (tenant_id, created_at);
CREATE INDEX idx_audit_entity ON "AuditLog" (tenant_id, entity);
```

## Migrations

```bash
# Create a new migration
pnpm db:migrate -- --name add_customers

# Apply migrations (development)
pnpm db:migrate

# Apply migrations (production)
pnpm db:migrate:deploy

# Push schema without migration (development only)
pnpm db:push

# Open Prisma Studio
pnpm db:studio
```

## Backup & Recovery

Since data is encrypted client-side, database backups contain only encrypted data. This means:

1. **Backups are safe** - Even if compromised, data is encrypted
2. **Recovery requires keys** - Users must have their encryption keys to access restored data
3. **Key management is critical** - Lost keys = lost data

Recommended backup strategy:
- Daily automated backups
- Point-in-time recovery enabled
- Test restores monthly

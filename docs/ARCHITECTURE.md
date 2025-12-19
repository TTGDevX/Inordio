# Inordio Architecture

## Overview

Inordio is a multi-tenant field service management SaaS platform built with a modern TypeScript stack.

## High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                           CLIENTS                                   │
├─────────────────────────────────────────────────────────────────────┤
│  Web App (Next.js)  │  Mobile PWA  │  Discord Bot  │  API Clients  │
└──────────┬──────────┴──────┬───────┴───────┬───────┴───────┬────────┘
           │                 │               │               │
           └─────────────────┴───────────────┴───────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                         API LAYER                                   │
├─────────────────────────────────────────────────────────────────────┤
│  Next.js API Routes  │  tRPC  │  Webhooks  │  Discord Gateway       │
└──────────────────────┴────────┴────────────┴────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                       BUSINESS LOGIC                                │
├─────────────────────────────────────────────────────────────────────┤
│  Tenant Context  │  Services  │  Validation  │  Authorization       │
└──────────────────┴────────────┴──────────────┴──────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                         DATA LAYER                                  │
├─────────────────────────────────────────────────────────────────────┤
│  Prisma ORM  │  PostgreSQL + RLS  │  Redis  │  S3 Storage           │
└──────────────┴────────────────────┴─────────┴───────────────────────┘
```

## Multi-Tenancy

### Strategy: Shared Database with Row-Level Security

All tenants share the same database, with data isolation enforced at the database level using PostgreSQL's Row-Level Security (RLS).

```sql
-- Every table has a tenant_id column
-- RLS policies ensure queries only return rows for the current tenant

ALTER TABLE "User" ENABLE ROW LEVEL SECURITY;

CREATE POLICY tenant_isolation ON "User"
  USING (tenant_id = current_setting('app.current_tenant_id')::uuid);
```

### Tenant Context Flow

```
1. Request arrives with tenant identifier (subdomain or header)
2. Middleware resolves tenant and validates subscription
3. Database session is configured with tenant context
4. All queries automatically filtered by RLS
5. Response returned
```

## End-to-End Encryption

### Zero-Knowledge Architecture

The server never has access to unencrypted business data. All sensitive data is encrypted client-side before transmission.

```
┌─────────────────────────────────────────────────────────────────────┐
│                         CLIENT                                       │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │ 1. User enters password                                        │  │
│  │ 2. Password → PBKDF2 → Password-Derived Key                    │  │
│  │ 3. PDK unwraps stored Encryption Key                           │  │
│  │ 4. Encryption Key used for all data operations                 │  │
│  └───────────────────────────────────────────────────────────────┘  │
│                              │                                       │
│                              ▼                                       │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │ encrypt(data, key) → ciphertext                               │  │
│  │ Only ciphertext leaves the browser                            │  │
│  └───────────────────────────────────────────────────────────────┘  │
└──────────────────────────────┬──────────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────────┐
│                         SERVER                                       │
│  - Stores only encrypted blobs                                       │
│  - Cannot decrypt (no keys)                                          │
│  - Metadata (IDs, timestamps) unencrypted for queries               │
└─────────────────────────────────────────────────────────────────────┘
```

### What's Encrypted vs Unencrypted

| Encrypted | Unencrypted |
|-----------|-------------|
| Customer names, addresses | Record IDs (UUIDs) |
| Financial data (costs, prices) | Timestamps |
| Job notes, descriptions | Status enums |
| Employee details | Foreign keys |
| Documents, photos | Tenant ID |

## Package Structure

```
inordio/
├── apps/
│   ├── web/              # Next.js application
│   │   ├── src/
│   │   │   ├── app/      # App router pages
│   │   │   ├── components/
│   │   │   ├── lib/      # Utilities
│   │   │   └── server/   # tRPC routers
│   │   └── public/
│   └── discord-bot/      # Discord.js bot
│
├── packages/
│   ├── database/         # Prisma schema & client
│   ├── core/             # Shared business logic
│   ├── encryption/       # E2EE utilities
│   └── ui/               # Shared UI components
│
└── infrastructure/
    └── docker/           # Local dev environment
```

## Technology Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Framework | Next.js 14 | Full-stack, SSR, great DX |
| API | tRPC | End-to-end type safety |
| Database | PostgreSQL | RLS, reliability, features |
| ORM | Prisma | Type-safe, migrations, studio |
| UI | Shadcn/UI | Customizable, accessible |
| Styling | Tailwind CSS | Utility-first, fast |
| Auth | Better-Auth | Modern, flexible |
| Monorepo | pnpm + Turbo | Fast, efficient |

## Deployment

### Development
- Docker Compose for PostgreSQL, Redis, MinIO
- Hot reload with Turbo

### Production (Future)
- Options: Proxmox VMs, DigitalOcean, AWS, Vercel
- PostgreSQL: Managed service recommended
- Files: S3-compatible storage
- Redis: Managed service recommended

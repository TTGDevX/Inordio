# Inordio Development Context

> **Purpose:** Share this file with Claude at the start of each development session to provide context.

## Project Overview

**Inordio** (from Latin "in ordo" - in order) is a field service management SaaS platform for trades businesses (MSP, plumbing, HVAC, electrical).

**Key Features:**
- Multi-tenant with end-to-end encryption
- Inventory management with truck stock tracking
- Quotes, jobs, invoicing
- Canadian payment processing (Stripe, Square, Rotessa, PayPal)
- Sage accounting integration
- Expense/receipt scanning with OCR
- Discord bot integration
- Service agreements (recurring contracts)
- Equipment/asset tracking
- Scheduling/dispatch board

**Tech Stack:**
- Frontend: Next.js 14, React, Tailwind CSS, Shadcn/UI
- Backend: Next.js API Routes, tRPC
- Database: PostgreSQL with Row-Level Security
- ORM: Prisma
- Auth: Better-Auth
- Monorepo: pnpm + Turborepo

---

## Current Phase

**Phase:** 0 - Foundation
**Sprint:** 1 - Project Setup

---

## Last Session

- **Date:** [UPDATE THIS]
- **Completed:** Initial repo scaffold
- **In Progress:** [UPDATE THIS]
- **Blocked:** None

---

## Active Branch

`main` (or `feature/xxx`)

---

## Key Files Changed Recently

- [UPDATE THIS]

---

## Key Decisions Made

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Monorepo tool | pnpm + Turborepo | Fast, good caching, industry standard |
| Database | PostgreSQL + RLS | Multi-tenant isolation, battle-tested |
| API layer | tRPC | End-to-end type safety |
| UI components | Shadcn/UI | Customizable, accessible, modern |
| Multi-tenancy | Shared DB + RLS | Cost efficient, easier maintenance |
| Encryption | Client-side E2EE | Zero-knowledge architecture |

---

## Open Questions

- [ ] Offline sync strategy for mobile PWA?
- [ ] Which OCR provider (Google Vision vs AWS Textract)?
- [ ] Hosting: Proxmox initially or cloud-native from start?

---

## Repository Structure

```
inordio/
├── apps/
│   ├── web/              # Next.js main application
│   └── discord-bot/      # Discord bot
├── packages/
│   ├── database/         # Prisma schema, migrations
│   ├── core/             # Shared business logic
│   ├── encryption/       # E2EE utilities
│   └── ui/               # Shared UI components
├── infrastructure/
│   └── docker/           # Docker configs
├── docs/                 # Documentation
└── scripts/              # Utility scripts
```

---

## Useful Commands

```bash
# Development
pnpm dev                  # Start all apps in dev mode
pnpm build                # Build all apps
pnpm lint                 # Lint all packages
pnpm typecheck            # TypeScript check

# Database
pnpm db:generate          # Generate Prisma client
pnpm db:migrate           # Run migrations (dev)
pnpm db:push              # Push schema (no migration)
pnpm db:studio            # Open Prisma Studio
pnpm db:seed              # Seed dev data

# Testing
pnpm test                 # Run all tests
pnpm test:watch           # Watch mode
```

---

## Links

- **Repo:** https://github.com/TTGDevX/Inordio
- **Project Board:** https://github.com/orgs/TTGDevX/projects/1
- **Staging:** [TBD]
- **Production:** [TBD]

---

## Notes for Claude

1. Always check this CONTEXT.md first
2. Run `pnpm typecheck` before suggesting commits
3. Use existing patterns from codebase
4. All business data must support encryption
5. Every database table needs `tenantId` for RLS
6. Canadian tax rules apply (HST/GST/PST)
7. Keep mobile/PWA in mind for field tech UX

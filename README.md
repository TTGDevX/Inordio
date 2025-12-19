# Inordio

> **Keep your business in order.**

Inordio is a field service management platform built for trades businesses - MSPs, plumbers, HVAC technicians, electricians, and more. Real inventory tracking, truck stock management, job scheduling, invoicing, and Canadian payment processing - all in one place.

## ✨ Features

### Core
- 📦 **Real Inventory** - Not just a price list. Track actual quantities across warehouses and trucks.
- 🚚 **Truck Stock** - Know what's in every vehicle. Transfer parts between locations.
- 📝 **Quotes & Jobs** - From quote approval to job completion to invoice.
- 💰 **Canadian Payments** - Stripe, Square, Interac, e-Transfer, PAD (Rotessa), PayPal.
- 🔐 **End-to-End Encryption** - Your customers' data is encrypted. Even we can't see it.

### Field Operations
- 📅 **Scheduling Board** - Drag-and-drop dispatch. See your whole team at a glance.
- 🔧 **Equipment Tracking** - Track what you've installed at each customer. Warranty alerts.
- ✅ **Checklists** - Inspection forms, job completion checklists, compliance documentation.
- 🧾 **Expense Tracking** - Snap a receipt, OCR extracts the data, submit for approval.
- 📱 **Mobile Ready** - PWA that works offline for techs in basements.

### Business
- 🔄 **Service Agreements** - Recurring maintenance contracts with auto-generated jobs.
- 📊 **Reports & Analytics** - Revenue, tech performance, inventory turnover.
- 🤖 **Discord Integration** - Command center for your operations.
- 📚 **Sage Integration** - Sync invoices, payments, and expenses.

## 🚀 Getting Started

### Prerequisites

- Node.js 20+
- pnpm 8+
- PostgreSQL 16+
- Docker (optional, for local development)

### Installation

```bash
# Clone the repository
git clone https://github.com/TTGDevX/Inordio.git
cd Inordio

# Install dependencies
pnpm install

# Copy environment file
cp .env.example .env

# Start PostgreSQL (if using Docker)
docker compose up -d postgres

# Run database migrations
pnpm db:migrate

# Seed development data (optional)
pnpm db:seed

# Start development server
pnpm dev
```

Visit `http://localhost:3000` to see the app.

## 📁 Project Structure

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

## 🛠️ Tech Stack

| Layer | Technology |
|-------|------------|
| Frontend | Next.js 14, React, Tailwind CSS, Shadcn/UI |
| Backend | Next.js API Routes, tRPC |
| Database | PostgreSQL with Row-Level Security |
| ORM | Prisma |
| Auth | Better-Auth |
| Payments | Stripe, Square, Rotessa |
| Monorepo | pnpm + Turborepo |

## 📖 Documentation

- [Architecture](./docs/ARCHITECTURE.md)
- [Database Schema](./docs/DATABASE.md)
- [API Reference](./docs/API.md)
- [Encryption](./docs/ENCRYPTION.md)
- [Deployment](./docs/DEPLOYMENT.md)

## 🧪 Development

```bash
# Run all tests
pnpm test

# Type checking
pnpm typecheck

# Linting
pnpm lint

# Format code
pnpm format

# Database studio
pnpm db:studio
```

## 📄 License

Proprietary - All rights reserved by TTG Development.

---

Built with ☕ in London, Ontario, Canada.

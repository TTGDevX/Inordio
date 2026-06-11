# Inordio — Claude Code context

Read **PROJECT-BRIEF.md** (repo root) before doing anything — it is the source of truth for scope, stack, and architecture decisions. Key rules from it:

- Stack is Laravel 12 + Livewire 3 + MySQL 8 + stancl/tenancy. **Decided — do not relitigate.** No forks of third-party apps; everything is first-party.
- Every tenant-scoped table needs `tenant_id`. **Write tenant-isolation tests with every feature** — data leakage between tenants is the worst possible bug in this product.
- Mobile-first UI for anything technicians touch (PWA, phones in the field).
- Canadian taxes are data, not constants (brief §7).
- AI features go through a single `AiGateway` service class pointed at TTG's local gateway — no AI provider SDKs in app code (brief §8).
- Audit before assuming: check existing migrations/configs before generating new code.
- User roles: Owner / Admin / Office / Technician / Viewer (five roles — see docs/LEGACY-SCAFFOLD-NOTES.md).

## Commands

```bash
php artisan test          # PHPUnit suite
npm run build             # Vite production build
npm run dev               # Vite dev server
php artisan migrate       # uses MySQL database `inordio` locally
```

The old Next.js scaffold is on branch `archive/nextjs-scaffold-2025` — reference only, never merge.

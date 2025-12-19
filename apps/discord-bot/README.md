# Discord Bot

Inordio Discord bot for field operations command center.

## Features

- Notifications (jobs, payments, alerts)
- Commands (!inventory, !job, !schedule, etc.)
- Interactive approvals
- Daily summaries

## Setup

```bash
cd apps/discord-bot
pnpm install
```

## Environment Variables

```
DISCORD_BOT_TOKEN=your-bot-token
DISCORD_CLIENT_ID=your-client-id
INORDIO_API_URL=http://localhost:3000
```

## Development

```bash
pnpm dev
```

## Commands

See docs/DISCORD.md for full command reference.

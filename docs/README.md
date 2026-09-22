# InvoFlow — Agent Docs

This folder is written **for AI coding agents** (Claude, Antigravity, Cursor, Copilot, whatever
comes next) working on this repo — not for end users. Its job is to let any agent get fully
oriented in a couple of file reads instead of re-deriving the whole codebase (or re-asking the
human) every session.

**If you are an agent starting work here: read this file, then whichever numbered file matches
your task, before exploring the codebase from scratch.** Treat these files as the current source
of truth for things that are *not* obvious from the code itself (business rules, "why", known
traps, deployment reality). If you learn something new and non-obvious while working, **update
the relevant file** — that's the whole point of this folder existing.

## What this project is

**InvoFlow** — a manufacturing inventory + costing ERP for an agro-chemical manufacturer, built
in Laravel. One codebase serves two very different UIs (desktop back-office, mobile PWA for
field/factory use) and syncs data both ways with an on-premise Busy ERP (via MS SQL Server) and
a separate ERP master-data HTTP API. See [01-overview.md](01-overview.md) for the full picture.

## Map of this folder

| File | Read this when you need to... |
|---|---|
| [01-overview.md](01-overview.md) | Get oriented for the first time: stack, environments, domains, terminology |
| [02-architecture.md](02-architecture.md) | Find where a feature lives: routes, controllers, middleware, scheduler |
| [03-database.md](03-database.md) | Understand tables, models, relationships |
| [04-costing-module.md](04-costing-module.md) | Touch anything under Costing/BOM (formulas, permissions, schema) |
| [05-sales-sync-bridge.md](05-sales-sync-bridge.md) | Touch anything related to MSSQL sync, the local bridge agents, or "why is agent/sales data missing/wrong" |
| [06-permissions.md](06-permissions.md) | Add a new page/feature and need to gate it correctly |
| [07-deployment.md](07-deployment.md) | Ship a change, or figure out what's actually live vs. local-only |
| [08-known-issues.md](08-known-issues.md) | Avoid re-discovering a trap someone already hit |

## Related files already in the repo root (one level up)

These pre-date this `docs/` folder and are still authoritative for their topics — this folder
indexes and complements them, it does not replace them:

- `../PROJECT_MEMORY.md` — Costing/BOM formulas, schema, and UI rules in detail.
- `../CHANGELOG.md` — Dated feature history.
- `../TASKS.md` — Completed work checklist + what's still outstanding.

## Ground rules for agents editing this repo

1. **No git remote is configured.** Deployment to the live site is manual/file-based, not
   `git push`. See [07-deployment.md](07-deployment.md) before assuming a commit ships anything.
2. **Two live databases exist**: this machine's local XAMPP MySQL (`inventory_laravel_db`) and
   the production Hostinger MySQL behind `https://invoflow.gloswebdev.in`. They are kept in sync
   by the local Python bridge agents, not automatically identical. Don't assume a query against
   one reflects the other.
3. **Two unrelated things are both called "the bridge"** — see
   [05-sales-sync-bridge.md](05-sales-sync-bridge.md) before touching sync/bridge code, this has
   already caused real confusion once.
4. Prefer extending the shared logic (`ReportController::salesDrilldown`, `getPartyMasterMap()`,
   etc.) over duplicating it into `MobileController` — the desktop/mobile split already causes a
   lot of near-duplicate code; don't add more without a reason.

*Last written: 2026-09-15, alongside the sales-drilldown "No Agent" data-sync incident documented
in [05-sales-sync-bridge.md](05-sales-sync-bridge.md).*

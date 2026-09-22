# 07 — Deployment

## There is no git remote

`git remote -v` is empty for this repo. **Deployment is not `git push`-based.** Do not assume a
local commit (or even a local *uncommitted* change) is or isn't live on production — you cannot
tell from git state alone. See the caveat at the bottom of this file.

## The actual deploy path

Full instructions: [`../shared_hosting_deploy.md`](../shared_hosting_deploy.md) (Hinglish,
written for the project owner). Summary:

1. On this local machine: `composer install --optimize-autoloader --no-dev`, `npm run build`,
   `php artisan config:clear && route:clear && view:clear`.
2. Zip the app folder (`app/`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/`,
   `routes/`, `storage/`, `vendor/`, `artisan`, `composer.json`, `.htaccess`) — `public/` is
   special-cased on shared hosting (its contents become `public_html`, not a subfolder).
3. Upload. Two ways this actually happens in practice:
   - Manual FTP / hPanel File Manager upload of the zip, extracted server-side.
   - **The in-app updater**: `System → Update` in the admin UI
     (`SystemController::applyUpdate()`, route `system.update.apply`) accepts a `.zip` upload
     (up to 300MB) and extracts it over the live deployment itself, server-side. This is
     admin-only (`adminOnly()` gate) and is the more likely path for a routine update once the
     app itself is already live, since it needs no separate FTP client.
4. Production `.env` (DB credentials, API keys, tokens) is **not** part of what you build/zip
   locally — it lives only on the server and is not tracked in this repo.

## Cron

Hostinger hPanel cron hits `GET /cron/database-backup?token=...` (no Laravel `auth` middleware —
token-only, see [08-known-issues.md](08-known-issues.md)) since shared hosting typically can't run
a persistent Laravel scheduler process; this is a workaround for `schedule:run` not being
continuously invokable there.

## Database Migrations on Shared Hosting (Hostinger)

Because Hostinger shared hosting does not run `php artisan migrate` automatically on FTP/Zip upload,
any schema changes introduced in Laravel migration files must be executed manually via phpMyAdmin
on the production database (`u293228258_invoflowsagar`).

### Required SQL for Unified BOM & ERP Push Updates (2026-09-20)
If deploying changes related to `ProductionController` or `BomResolverService`, execute the following:

```sql
-- 1. Add ERP push tracking columns to productions table
ALTER TABLE `productions` 
ADD COLUMN `erp_push_status` VARCHAR(255) NOT NULL DEFAULT 'pending' AFTER `user_id`,
ADD COLUMN `erp_issue_response` TEXT NULL AFTER `erp_push_status`,
ADD COLUMN `erp_receipt_response` TEXT NULL AFTER `erp_issue_response`;

-- 2. Insert ERP push configuration settings
INSERT INTO `app_settings` (`key`, `value`, `label`, `group`, `created_at`, `updated_at`) VALUES
('erp_push_enabled', '1', 'Enable ERP Stock Push', 'erp_push', NOW(), NOW()),
('erp_push_base_url', 'http://logic.gloswebdev.in', 'ERP Push Base URL', 'erp_push', NOW(), NOW()),
('erp_push_username', 'SALapi', 'ERP Push Username (Basic Auth)', 'erp_push', NOW(), NOW()),
('erp_push_password', 'SAL@api@123', 'ERP Push Password (Basic Auth)', 'erp_push', NOW(), NOW()),
('erp_receipt_doc_prefix', 'REC', 'Receipt Doc Prefix', 'erp_push', NOW(), NOW()),
('erp_receipt_godown_name', 'MAIN', 'Receipt Godown Name', 'erp_push', NOW(), NOW()),
('erp_receipt_received_from', '', 'Receipt ReceivedFrom', 'erp_push', NOW(), NOW()),
('erp_receipt_issue_to', '', 'Receipt IssueTo', 'erp_push', NOW(), NOW()),
('erp_issue_doc_prefix', 'IS', 'Issue Doc Prefix', 'erp_push', NOW(), NOW()),
('erp_issue_godown_name', '', 'Issue Godown Name', 'erp_push', NOW(), NOW()),
('erp_issue_issue_to', 'DAMAGE', 'Issue IssueTo (mandatory)', 'erp_push', NOW(), NOW());

-- 3. Align 100% SOLUBLE IN WATER product type
UPDATE products 
SET product_type_id = 9 
WHERE category = '100% SOLUBLE IN WATER' OR group_id = 7;
```

### Required SQL for Stock Adjustment Revamp & Branch Lock (2026-09-22)
If deploying the revamped Stock Adjustment module, execute the following SQL on the live database (`u293228258_invoflowsagar`):

```sql
ALTER TABLE `adjustments`
  ADD COLUMN `user_id` BIGINT UNSIGNED NULL AFTER `product_id`,
  ADD COLUMN `branch_code` VARCHAR(50) NULL AFTER `user_id`,
  ADD COLUMN `branch_name` VARCHAR(100) NULL AFTER `branch_code`,
  ADD COLUMN `erp_push_status` VARCHAR(30) NOT NULL DEFAULT 'skipped' AFTER `reason`,
  ADD COLUMN `erp_doc_no` VARCHAR(100) NULL AFTER `erp_push_status`,
  ADD COLUMN `erp_response` TEXT NULL AFTER `erp_doc_no`,
  ADD CONSTRAINT `adjustments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
```

## ⚠️ State as of 2026-09-15 — verify before assuming either direction

A `git status` at this date showed **~30 files locally modified/deleted but never committed**,
including very large diffs against the last commit (`3f5de920`) in files central to this session's
work: `ReportController.php` (+1356/-lines, essentially the whole file), `sales_report.blade.php`
(+978 lines), `MssqlSyncController.php` (+518 lines), `BridgeApiController.php`,
`query_executor.blade.php`, `routes/web.php`, plus `local_bridge/invoflow_bridge.py` and
`bridge_config.json`. Additionally, `deploy.sh`, `README.md`, and `HOSTINGER_DEPLOY_GUIDE.md` show
as **deleted** in the working tree relative to the last commit.

**This was not caused by any AI-agent session** — no `Edit`/`Write` tool call touched any of these
files in the session where this was discovered; it predates that session's involvement entirely.

This is unresolved as of this writing: it is not established here whether this large local diff
represents work **already uploaded to production** (and just never `git commit`-ed locally, which
would be consistent with production behavior observed during this session matching this local
code closely) or **work still pending upload**. **Do not assume either.** Before deploying
anything, or before treating production's behavior as reflecting a different (older/committed)
version of this code:

1. Ask the project owner directly whether these files were already pushed live.
2. If unclear, compare specific behavior (an error message string, a route, a UI label unique to
   the new code) against the live site to infer which version is actually deployed.
3. Once resolved, commit the local state (`git add -A && git commit`) so this ambiguity can't
   recur — the missing `deploy.sh`/`HOSTINGER_DEPLOY_GUIDE.md` in particular should be restored
   or deliberately re-deleted-and-committed, not left as an untracked deletion.

If you're an agent reading this and the ambiguity above has since been resolved, please replace
this section with what was actually true, rather than leaving a stale warning in place.

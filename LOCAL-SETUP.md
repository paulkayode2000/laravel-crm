# Krayin CRM - Local Isolated Setup

Everything for this instance lives under `C:\Users\kolugbenga\laravel-crm-local`.
No machine-wide PHP, MySQL, Composer or service configuration was modified.

| Item | Value |
| --- | --- |
| App URL | http://127.0.0.1:8000 |
| Admin URL | http://127.0.0.1:8000/admin/login |
| Repository | https://github.com/paulkayode2000/crm (`2.2`, `main`; private) |
| Fork | https://github.com/paulkayode2000/laravel-crm (`origin`, branch `2.2`) |
| Upstream | https://github.com/krayin/laravel-crm (`upstream`) |

## Credentials

Application admin (created locally):

```
Email:    admin@krayin.local
Password: KrayinAdmin3307x2026
Name:     Demo Administrator
Role:     Administrator (role_id 1)
```

Project-owned database (isolated MySQL 8.4 instance, **port 3307**):

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=laravel_crm
DB_USERNAME=laravel_crm
DB_PASSWORD=KrayinLocal3307x2026
```

The instance `root` account is passwordless but bound to `127.0.0.1` only
(`--initialize-insecure`), so it is reachable only from this machine.
The pre-existing machine-wide MySQL84 service on port 3306 is untouched.

## Demo roles, accounts and data

`config/demo.php` is the single source of truth: the admin login page renders a
one-click button per account, and `LocalDemoUsersSeeder` creates exactly those
roles and users. Everything below is local-environment only.

| Role (permission type) | E-mail | Password | What it demonstrates |
| --- | --- | --- | --- |
| Administrator (`all`) | admin@krayin.local | KrayinAdmin3307x2026 | Full access: settings, users, roles, warehouses, configuration |
| Sales Manager (`custom`, full CRUD) | manager@krayin.local | KrayinDemo3307x2026 | Every record; create/edit/delete leads, quotes, contacts, products |
| Sales Agent (`custom`, no delete) | agent@krayin.local | KrayinDemo3307x2026 | Only own records (`view_permission=individual`); create/edit, no delete |
| Support Agent (`custom`, read-only) | support@krayin.local | KrayinDemo3307x2026 | Only own records; view and print only, no create/edit/delete |

Re-seed the demo dataset (idempotent - matched on natural keys, safe to re-run):

```powershell
php artisan db:seed --class=Database\Seeders\LocalDemoDataSeeder
```

That command runs `LocalDemoUsersSeeder` first and then seeds:

* **24 leads** - six per owner, one in each pipeline stage (new, follow-up,
  prospect, negotiation, won, lost) with values, sources, types, tags, expected
  close dates and closed dates inside the dashboard's one-month window.
* **8 quotes** with line items, linked to a contact person and to a lead (so the
  quote PDF/print works).
* **10 people and 8 organizations** with addresses, e-mails and phone numbers.
* **10 products** with SKUs, prices and tags, plus **40 inventory rows** across
  **2 warehouses** with **4 locations** (stock shows in Products and Warehouses).
* **20 lead-product rows** which drive the Top Selling Products dashboard widget.
* **12 activities** (calls, meetings, lunches - some done, some upcoming) with
  participants, attached to leads and people.
* **6 tags, 3 user groups and 2 marketing events.**

Notes:

* The sign-in panel posts to the normal `admin.session.store` route, so it uses
  the same authentication, rate limiting and permission checks as a manual login.
* Toggle it with `DEMO_LOGIN_ENABLED=false` in `.env` (then `php artisan
  config:clear`); it defaults to on only when `APP_ENV=local`.
* Passwords can be overridden with `DEMO_ADMIN_PASSWORD` / `DEMO_USERS_PASSWORD`.
* **Mail** is not seeded: Krayin syncs e-mails from a real mailbox, so the Mail
  module stays empty until IMAP/SMTP is configured under Settings > Configuration.

## Third-party calls

This checkout is hardened so that loading a page never leaves the machine. Every
asset that used to come from a CDN or from Google Fonts is served from
`public/vendored/`, and both installer call-outs are off. Details and hashes are
in `public/vendored/README.md`.

| What | Before | Now |
| --- | --- | --- |
| Inter webfont | `fonts.googleapis.com` + `fonts.gstatic.com` | `public/vendored/fonts/` (latin + latin-ext `woff2`) |
| Poppins, DM Serif Display | `fonts.googleapis.com` on login, installer and web form pages | removed - no stylesheet ever applied either family |
| TinyMCE 6.6.2 (quick create, e-mail editor) | `cdnjs.cloudflare.com` | `public/vendored/tinymce/` |
| CodeMirror 5.30 (webhook payload editor) | `cdnjs.cloudflare.com` | `public/vendored/codemirror/` |
| `chartjs-chart-funnel` 4.2.1 (dashboard funnel) | `cdn.jsdelivr.net` | `public/vendored/chartjs-chart-funnel/` |
| flatpickr dark theme | `npmcdn.com`, only when dark mode is on | `public/vendored/flatpickr/themes/` |
| Install telemetry | `POST updates.krayincrm.com/api/updates` on `krayin.installed` | skipped, see below |
| Installer logo route | `GET updates.krayincrm.com/krayin.png` via `/cache/{filename}` | bundled `logo.svg`; the admin layout no longer preloads that route |

### Telemetry switch

`config/telemetry.php` gates the only server-side outbound requests:

```php
'enabled' => env('KRAYIN_TELEMETRY_ENABLED', false),
```

It defaults to **off**, so the `krayin.installed` listener returns immediately and
`/cache/{filename}` serves the logo that ships with the admin package. Setting
`KRAYIN_TELEMETRY_ENABLED=true` in `.env` restores the upstream behaviour (the
POST contains the app URL, the first administrator's name and e-mail and the
default country). The listener stays registered in the service provider either
way, so the switch needs no code change.

Because the flag is data driven, a fresh clone does not need a pre-created
`storage/installed` marker to stay silent: the first request after installation
may still fire `krayin.installed`, but the listener does nothing.

### Integrations that stay dormant

Nothing else is configured to phone home, and the audit that established this is
reproducible: mail uses the `mailhog:1025` placeholder, `BROADCAST_DRIVER=log`
(so no `Echo`/Pusher client is even rendered), `QUEUE_CONNECTION=sync`, the
webhook table starts empty, Google Contacts has no client ID and the AI service
has no OpenRouter key.

### The compiled bundles

The flatpickr theme URL lives inside the Vite output as well as in the source, so
`public/admin/build/assets/app-3HMmX12V.js` and
`public/webform/build/assets/app-Di8tgz7D.js` are patched in place (one string,
`https://npmcdn.com/flatpickr/dist/themes/` -> `/vendored/flatpickr/themes/`).
`npm run build` in `packages/Webkul/Admin` or `packages/Webkul/WebForm` regenerates
them from the already-patched source, so a future rebuild keeps the local path.

## What is isolated

| Concern | Location |
| --- | --- |
| PHP config | `.\php.ini` via `PHPRC` (intl enabled, 512M memory, 20M uploads) |
| Composer binary | `.\composer.phar` |
| Composer home/cache | `.\.tools\composer` |
| MySQL data directory | `.\.tools\mysql\data` |
| MySQL config | `.\.tools\mysql\my.ini` |
| MySQL database/user bootstrap | `.\.tools\mysql\init-db.sql` |
| Logs | `.\.tools\logs` |
| Start/stop scripts (committed) | `.\start-local.ps1`, `.\stop-local.ps1` |

`.tools/`, `composer.phar`, `php.ini` and `storage/installed` are listed in
`.git/info/exclude`: they are machine-local binaries/state and stay out of the
repository, so `git status` stays clean without touching `.gitignore`.
Everything needed to boot the app (`start-local.ps1`, `stop-local.ps1`,
`config/demo.php`, the demo seeders, this document) is committed.

## Start / stop

```powershell
# MySQL (3307) + PHP dev server (8000) in one step
powershell -ExecutionPolicy Bypass -File .\start-local.ps1

# Stop both again
powershell -ExecutionPolicy Bypass -File .\stop-local.ps1
```

`start-local.ps1` is repository-owned and self-describing (`Get-Help
.\start-local.ps1 -Detailed`). It:

1. points `PHPRC` at the project `php.ini` (enables `intl`, 512M memory, 20M
   uploads) so no machine-wide PHP config is used;
2. starts the local MySQL instance from `.tools\mysql\my.ini` when that file and
   a `mysqld.exe` exist, otherwise it assumes the database in `.env` is already
   reachable;
3. runs `php artisan serve --host=127.0.0.1 --port=8000`.

Useful switches: `-Port 8080`, `-MysqlPort 3308`, `-SkipDatabase`,
`-MysqlBin 'C:\path\to\mysql\bin'`, `-MysqlConfig 'C:\path\to\my.ini'`.

The MySQL instance runs as a normal user process (no Windows service, no admin
rights), so start it with the script after a reboot.

## Fresh clone quickstart

The repository contains the application, the compiled admin assets and the demo
seeders; only `.env`, `vendor/` and the local MySQL data are generated.

```powershell
git clone https://github.com/paulkayode2000/crm.git
cd crm

Copy-Item .env.example .env
# set DB_HOST / DB_PORT / DB_DATABASE / DB_USERNAME / DB_PASSWORD for your MySQL

php composer.phar install          # or: composer install
php artisan key:generate
php artisan migrate --seed         # Krayin base data
php artisan db:seed --class=Database\Seeders\LocalDemoDataSeeder

powershell -ExecutionPolicy Bypass -File .\start-local.ps1
```

Then open http://127.0.0.1:8000/admin/login and use one of the demo buttons.

## How it was installed

```powershell
# same non-interactive path the project's CI uses (.github/workflows/ci.yml)
php artisan krayin-crm:install --skip-env-check --skip-admin-creation
```

That generated the key, ran `migrate:fresh` (97 migrations / 61 tables), seeded
Krayin base data (255 countries, 42 attributes, roles, email templates,
workflows), published configs, linked `public/storage` and cleared caches.
The `--skip-admin-creation` flag was used because the admin prompt is
interactive; the administrator row (user id 1) was then updated directly and
the installation was marked complete (`storage/installed` +
`core_config.installation.completed = 1`).

## Notes / troubleshooting

* **Composer autoload stall.** On this machine the optimized classmap scan
  (`composer install` -> "Generating optimized autoload files") stalls. The
  autoloader was generated with
  `php composer.phar dump-autoload --no-interaction --no-scripts`
  (non-optimized, dev-friendly). If you re-run `composer install` and it hangs
  at that step, interrupt it and run the command above again.
* **Assets.** Admin, installer and web-form bundles are committed in the repo
  (`public/admin/build`, `public/installer/build`, `public/webform/build`), so
  no `npm run build` is required to run the app. Admin frontend dev
  dependencies are installed at `packages/Webkul/Admin/node_modules`
  (`npm ci` already run) for `npm run dev`.
* **Installer.** `/installer` is not needed; installation is already complete
  and guarded by `storage/installed` and the `core_config` flag.
* **Mail.** `.env` keeps the upstream `mailhog` defaults; the app runs
  synchronously (`QUEUE_CONNECTION=sync`), so nothing tries to deliver mail
  during normal use.

# Krayin CRM - Local Isolated Setup

Everything for this instance lives under `C:\Users\kolugbenga\laravel-crm-local`.
No machine-wide PHP, MySQL, Composer or service configuration was modified.

| Item | Value |
| --- | --- |
| App URL | http://127.0.0.1:8000 |
| Admin URL | http://127.0.0.1:8000/admin/login |
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

## What is isolated

| Concern | Location |
| --- | --- |
| PHP config | `.\php.ini` via `PHPRC` (intl enabled, 512M memory, 20M uploads) |
| Composer binary | `.\composer.phar` |
| Composer home/cache | `.\.tools\composer` |
| MySQL data directory | `.\.tools\mysql\data` |
| MySQL config | `.\.tools\mysql\my.ini` |
| Logs | `.\.tools\logs` |
| Scripts | `.\.tools\scripts` |

`.tools/`, `composer.phar`, `php.ini`, `storage/installed` are listed in
`.git/info/exclude`, so `git status` stays clean without touching `.gitignore`.

## Start / stop

```powershell
# Start MySQL (3307) + PHP dev server (8000) together
powershell -ExecutionPolicy Bypass -File .\start-local.ps1

# Individual pieces
powershell -ExecutionPolicy Bypass -File .\.tools\scripts\start-mysql.ps1
powershell -ExecutionPolicy Bypass -File .\.tools\scripts\stop-mysql.ps1
```

`start-local.ps1` sets `PHPRC` to the project `php.ini` and runs
`php artisan serve --host=127.0.0.1 --port=8000`. Stop the web server with
`Ctrl+C`; stop the database with `stop-mysql.ps1`.

The MySQL instance runs as a normal user process (no Windows service, no admin
rights), so start it with the script after a reboot.

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

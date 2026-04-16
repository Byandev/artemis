# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Stack

Laravel 12 (PHP 8.2+) + Inertia.js 2 + React 19 + TypeScript + Tailwind v4. Pest 4 for tests, Pint for PHP formatting, ESLint + Prettier for frontend. Queues via Horizon, debugging via Telescope, auth via Fortify, HMR/bundling via Vite with the Wayfinder plugin generating typed route helpers into `resources/js/wayfinder/` and `resources/js/routes/` / `resources/js/actions/`. Local dev typically runs under Laravel Herd (MySQL at 127.0.0.1); a Sail `compose.yaml` also exists for docker-based dev.

## Commands

Dev (runs `artisan serve` + queue listener + `pail` logs + Vite concurrently):

```
composer dev          # standard
composer dev:ssr      # with Inertia SSR
```

Tests (Pest):

```
composer test                              # full suite (clears config first)
./vendor/bin/pest tests/Feature/Foo.php    # single file
./vendor/bin/pest --filter="test name"     # single test by name
./vendor/bin/pest --group=slow             # by group
```

Lint / format / typecheck:

```
vendor/bin/pint        # PHP formatting (required by CI)
npm run lint           # eslint --fix
npm run format         # prettier --write resources/
npm run format:check
npm run types          # tsc --noEmit
```

Build:

```
npm run build          # vite build
npm run build:ssr      # client + ssr bundle
```

Common artisan:

```
php artisan migrate
php artisan module:list                       # nwidart/laravel-modules
php artisan module:make-migration Foo Pancake
php artisan horizon                           # queue worker dashboard
php artisan telescope                         # debug UI at /telescope
php artisan schedule:work                     # run scheduler locally
```

CI (`.github/workflows/`) runs Pint, `npm run format`, `npm run lint`, and `./vendor/bin/pest` on push/PR to `develop` and `main`. PHP 8.4 in CI; runtime requires ^8.2.

## Architecture

### Multi-tenant workspace model

Every meaningful domain record is scoped to a `Workspace`. Users belong to workspaces via `WorkspaceUser` (pivot) and have roles via Spatie-style `Role` / `Team` / `CustomerServiceRepresentative` tables. The active workspace is URL-driven — authenticated routes live under `workspaces/{workspace:slug}/...` in `routes/workspaces.php`, and `HandleInertiaRequests` shares `currentWorkspace` from the route parameter plus the user's first 3 workspaces to every Inertia page. `session('current_workspace_id')` is only used by the `/dashboard` redirect in `routes/web.php` to pick which workspace to land on after login. `CheckWorkspace` middleware (`workspace` alias) guards membership.

### Three ways in

1. **Inertia (web)** — `routes/web.php` + `routes/workspaces.php` + `routes/settings.php` + `routes/auth.php` + `routes/browser-api.php`. Controllers under `app/Http/Controllers/Workspaces/**` return `Inertia::render(...)`. React pages resolve from `resources/js/pages/**/*.tsx` (see `resources/js/app.tsx`). Layouts are in `resources/js/layouts/` (`app-sidebar-layout`, `auth-layout`, etc.). `@/*` → `resources/js/*` TS path alias.
2. **Public API (machine)** — `routes/api.php` exposes `/api/v1/public/*` guarded by the `api.key` middleware (`AuthenticateApiKey`). Clients send `Authorization: Bearer <raw>` or `X-API-Key: <raw>`; `WorkspaceApiKey::findByRawKey` looks up the key, stamps `last_used_at`, and attaches `workspace` + `api_key` to the request attributes. Controllers live under `app/Http/Controllers/PublicApi/`.
3. **Browser API / "ask" endpoints** — `routes/browser-api.php` and ad-hoc endpoints under `/workspaces/{workspace}/ask` (see `AskDataController`) for in-page XHR from the React app.

### nwidart/laravel-modules layout

Feature areas live in `Modules/{AdsManager,Botcake,Pancake,Inventory}/` — each module is a self-contained Laravel mini-app with its own `app/` (Http, Models, Providers), `routes/`, `config/`, `database/migrations/`, `resources/`, `tests/`, and `vite.config.js`. The root `composer.json` uses the `wikimedia/composer-merge-plugin` to merge `Modules/*/composer.json` so module dependencies are installed at the root. Module status is toggled in `modules_statuses.json`. Autoload namespace is `Modules\{Name}\...` → `Modules/{Name}/app/`. When adding migrations/controllers/models that belong to a feature area already owned by a module (e.g. inventory, ads, botcake, pancake), put them inside the module rather than under `app/`.

Each module has its own Vite build (`npm`-wise via `vite.config.js` inside the module) that outputs to `public/build-{modulename}/`, separate from the root Vite build (`public/build/`). Most current frontend work still lives at the root `resources/js/`; module-specific JS builds are optional.

### Queries, metrics, jobs, scheduling

- Heavy analytics queries (RTS "group by X" dashboards) are extracted into `app/Queries/Rts*Query.php` classes, extending `RtsBaseQuery`. Controllers under `app/Http/Controllers/Workspaces/RTS/` dispatch to these. Don't inline complex aggregations in controllers — add/extend a query class.
- Cross-workspace metric aggregations live in `app/Metrics/{Orders,ParcelJourney}/` and `app/Support/WorkspaceMetrics.php`.
- `app/Jobs/` holds queued work (ad-record fetches, parcel update notifications, video creative generation). `composer dev` runs `queue:listen` so jobs execute locally without Horizon.
- Scheduled commands are wired in `routes/console.php` (not via Kernel). Notable: `trigger-fetch-page-orders` every 30 min, `sync:csr-daily-records` daily 03:00, `trigger-fetch-csr-erp-dail-records` twice daily. Commands live in `app/Console/Commands/`.

### Frontend conventions

- UI primitives in `resources/js/components/ui/` (Radix + `class-variance-authority` + `tailwind-merge`, shadcn-style — see `components.json`).
- Feature components grouped by domain: `components/{ai,charts,filters,inventory,metrics,pages,products,rts,roles,teams,workspace,workspaces}/`.
- State: Zustand stores in `resources/js/stores/`, theme in `hooks/use-appearance.ts` (initialised in `app.tsx`).
- Wayfinder generates typed route/action helpers — import from `@/routes/...` and `@/actions/...` rather than hand-writing URLs. Regenerated by Vite during `npm run dev`/`build`; if routes get out of sync, run `php artisan wayfinder:generate --with-form`.
- Public-facing pages (no auth) live under `resources/js/pages/workspaces/public/` and are reached via `/public/workspaces/...` routes declared at the top of `routes/workspaces.php` (outside the `auth` middleware group).

### Integrations

- **Facebook / Ads Manager** — OAuth callback at `/auth/facebook/callback` (`Integrations/FacebookController`). Ads data syncing jobs in `app/Jobs/AdsManager/` and `app/Jobs/FetchAd*.php`. Optimization rules live in `OptimizationRule` + `OptimizationRuleCondition` models with an `OptimizationRuleController` under `Workspaces/AdsManager/`.
- **Pancake (orders/shops/pages)** — a third-party Vietnam e-commerce platform. `Page` has a `pancake_token`; sync commands (`trigger-fetch-page-orders`, `trigger-fetch-shops-*`) pull orders/customers/users into local tables. `Modules/Pancake/` owns the module-level code.
- **Botcake** — messaging automation; `app/Services/Botcake.php` + `Modules/Botcake/` + controllers under `Workspaces/Botcake/`.
- **Media** — `spatie/laravel-medialibrary` with S3 disk support (`league/flysystem-aws-s3-v3`). `VideoCreative` uses queued jobs (`GenerateVideoCreative`, `CheckVideoCreativeStatus`).
- **RTS (parcel journey / delivery)** — notification templates + logs (`ParcelJourney*` models); monthly log rollup via `save-parcel-journey-notification-log`.

### Auth

Laravel Fortify is the auth backbone (registration, login, 2FA — see `two-factor-setup-modal.tsx`). `App\Providers\FortifyServiceProvider` configures views and rate limits. After login users hit `/dashboard`, which redirects to their current/owned/first workspace or `/workspaces/setup`.

## Conventions worth knowing

- **Route model binding uses `{workspace:slug}` in most places**, but a handful of routes still bind by ID (`{workspace}`) — check the specific route before assuming. Mixing the two in one controller action will surprise you.
- **Public vs protected API**: `/api/v1/public/*` is API-key-authed (not OAuth, not session). Don't add user-session-dependent logic there. Add new machine endpoints under `PublicApi\` controllers and the same `api.key` middleware.
- **Don't inline RTS aggregations** — extend `RtsBaseQuery` or add a sibling under `app/Queries/`.
- **Module migrations** go inside `Modules/{Name}/database/migrations/`, not the root `database/migrations/`. Run them with the standard `php artisan migrate` — modules are auto-discovered.
- **`ecomm_control_hub` / `laravel` binary files in the repo root** are leftover artefacts (large binary blobs), not executables to run. Ignore them.
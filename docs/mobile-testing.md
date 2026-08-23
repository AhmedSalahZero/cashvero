# Mobile Viewport Testing

CashVero is desktop-first. This suite audits every catalogued Inertia page at a **375×812** mobile viewport and catches horizontal overflow / unusable main content.

## Prerequisites

1. App running (`php artisan serve` or your usual stack) at `APP_URL` (default `http://127.0.0.1:8000`).
2. Built frontend assets (`npm run build` or `npm run dev`).
3. MySQL reachable with seed data (same DB as the app).
4. Playwright installed:

```bash
npm install
npx playwright install chromium
```

5. Credentials for a user that can open company pages (usually a super-admin):

```bash
export E2E_EMAIL='admin@example.com'
export E2E_PASSWORD='your-password'
export APP_URL='http://127.0.0.1:8000'   # optional
```

## Commands

| Command | What it does |
|---------|----------------|
| `npm run test:mobile:catalog` | Regenerates `tests/e2e/mobile/catalog.json` from SidebarMenu + seed IDs |
| `npm run test:mobile` | Catalog + Playwright audit (needs `@playwright/test`) |
| `npm run test:mobile:chrome` | Catalog + system Chrome CDP audit (no npm Playwright required) |
| `npm run test:mobile:report` | Opens the Playwright HTML report |

Prefer `npm run test:mobile:chrome` when the Playwright package cannot be installed (offline/proxy issues). It uses the machine's `google-chrome` and a zero-dependency Python CDP client at `tests/e2e/mobile/chrome_audit.py`. If MySQL is down, the catalog command falls back to `catalog.static.json`.

After a run, also open:

- `tests/e2e/mobile/summary.html` — pass/fail by page
- `tests/e2e/mobile/summary.json` — machine-readable issues grouped by type
- `tests/e2e/mobile/artifacts/*.png` — full-page screenshots for failures

## What is checked

For each URL in the catalog:

1. **HTTP status** — 404/optional pages are skipped; 5xx fails
2. **Horizontal overflow** — `documentElement.scrollWidth <= innerWidth`
3. **Main content width** — `<main>` should be ≥ ~280px (catches the old always-on sidebar eating the phone)
4. **Touch targets** — buttons under 40×32 are reported as warnings only

## Adding pages

Edit [`tests/e2e/mobile/page-catalog.php`](../tests/e2e/mobile/page-catalog.php):

- Sidebar Inertia links are picked up automatically from `App\Support\SidebarMenu`
- Add extra route names to `$extraSidebarRoutes` or `$facilityRoutes`
- Mark hard-to-seed pages with `'optional' => true` so missing data skips instead of fails

Then re-run `npm run test:mobile:catalog`.

## Layout fixes this suite guards

- Off-canvas mobile sidebar in `resources/js/Layouts/AppLayout.vue` (below `lg`)
- Global table scroll / page padding in `resources/css/app.css`
- Dashboard tab strip horizontal scroll via `.cvr-dash-tabs`

## CI (optional)

```yaml
- run: npm ci
- run: npx playwright install --with-deps chromium
- run: php tests/e2e/mobile/page-catalog.php
- run: npm run test:mobile
  env:
    APP_URL: ${{ env.APP_URL }}
    E2E_EMAIL: ${{ secrets.E2E_EMAIL }}
    E2E_PASSWORD: ${{ secrets.E2E_PASSWORD }}
```

Prefer running against a seeded staging DB; empty schemas will skip most optional pages.

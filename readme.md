[![CI](https://github.com/Neuron-PHP/labor-board/actions/workflows/ci.yml/badge.svg)](https://github.com/Neuron-PHP/labor-board/actions)
# Neuron-PHP Labor Board

A job-queue dashboard for Neuron MVC and CMS applications. Install it, and
**System → Queue** appears in the CMS admin (or `/admin/queue` in a plain MVC
app): pending, delayed, reserved and failed jobs, retry, delete, and
**Dispatch now** for scheduled entries.

Nothing in the UI runs a job inside the web request. Actions queue work for
the worker, the same way `jobs:retry` and `jobs:list` do.

## Install

```bash
composer require neuron-php/labor-board
```

The package declares `extra.neuron.mvc-provider`. The MVC application
discovers it at boot: routes, views, and a `system` navigation item named
**Queue**. No site wiring.

Requires `neuron-php/mvc` ≥ 0.9.58 and `neuron-php/jobs` ≥ 0.2.24.

## What you get

| URL | What it does |
|-----|----------------|
| `GET /admin/queue` | Overview: per-queue counts, failed total, next scheduled runs |
| `POST /admin/queue/schedule/:name/dispatch` | Queue a scheduled entry now (`csrf`) |
| `GET /admin/queue/q/:queue` | Pending / delayed / reserved tabs |
| `POST /admin/queue/q/:queue/:id/release` | Make a delayed job available now |
| `DELETE /admin/queue/q/:queue/:id` | Remove a pending or delayed job |
| `DELETE /admin/queue/q/:queue` | Clear the queue |
| `GET /admin/queue/failed` | Failed jobs, retry / forget |
| `GET /admin/queue/api/stats` | JSON for the overview auto-refresh |

The CMS admin layout is used when `layouts/admin.php` exists. Otherwise the
package ships a Bootstrap 5 fallback (`layouts/labor-board.php`).

## Authentication

Every page runs the `labor-board.auth` filter:

1. It delegates to the host filter named by `labor_board.auth_filter`
   (default `admin`). The CMS registers `admin` in `Neuron\Cms\boot()`.
2. If that filter is not registered, the request is **403**. Installing the
   package never opens an unauthenticated admin surface.
3. After the inner filter, `labor_board.role` (default `admin`) must match
   `AUTH_USER_ROLE`. Editors can reach the rest of `/admin` but not the
   queue dashboard unless you widen this.

```yaml
labor_board:
  auth_filter: admin   # null / none = no inner filter (logged at boot)
  role: admin          # null / none = any authenticated role
```

An MVC-only app registers its own filter as `admin` (or sets `auth_filter`
to whatever name it uses).

## Configuration

Every key is optional. Copy `resources/config/labor-board.yaml.example`
into `neuron.yaml`.

```yaml
labor_board:
  enabled: true
  auth_filter: admin
  role: admin
  layout: auto          # auto | admin | labor-board
  page_size: 50
  refresh_seconds: 10   # 0 disables auto-refresh
  # stale_after: 90     # defaults to queue.retry_after
  warn_pending: 100
  alert_pending: 1000
  warn_oldest_age: 300
```

`labor_board.enabled: false` removes the routes, views and navigation.
`components.disabled: [Neuron\LaborBoard\Provider]` does the same from
outside the package.

## Drivers

| Driver | Overview | Job list | Failed | Dispatch now |
|--------|----------|----------|--------|--------------|
| database, file, array, redis | counts | yes | yes | yes |
| counts-only (no `IQueueInspector`) | counts | panel | in-memory page | yes |
| sync | explainer | redirect home | redirect home | n/a |

## Tests

```bash
./vendor/bin/phpunit tests
```

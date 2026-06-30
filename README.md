# Red Flag Bingo

> The dating red flags you saw coming, now a competitive sport.

**Live demo → [redflagbingo.fun](https://redflagbingo.fun)**

![CI](https://github.com/Labault/red-flag-bingo/actions/workflows/ci.yml/badge.svg)
![PHPStan](https://img.shields.io/badge/PHPStan-level%209-2a2a2a)
![Symfony](https://img.shields.io/badge/Symfony-7.4-000000)

Red Flag Bingo is a real-time, multiplayer bingo game built on the clichés of
dating apps. Everyone shares a board, ticks off the red flags as they show up,
and every move lands on every screen at once. No refresh, no polling, no "hit F5
to check if you won".

The concept is the joke. The plumbing is the point. Real-time sync on a PHP stack
usually means bolting a Node service onto the side and hoping the two stay in
touch. Here it's a single process: **Mercure running inside FrankenPHP**, serving
the app and the live stream together. Most of this README is about how that holds
together.

---

## Stack

| Layer     | Tech                                                  |
| --------- | ----------------------------------------------------- |
| Framework | Symfony 7.4, PHP 8.3                                   |
| Server    | FrankenPHP (Caddy-based, worker mode)                 |
| Real-time | Mercure (SSE), embedded in FrankenPHP                 |
| Front     | Twig, Stimulus/Turbo, Tailwind v4 (AssetMapper)       |
| Database  | PostgreSQL 16, Doctrine ORM                           |
| Async     | Symfony Messenger, Scheduler (archived-flag purge)    |
| Quality   | PHPUnit, PHPStan level 9, GitHub Actions              |
| Infra     | Docker Compose, shared global Caddy reverse proxy     |

---

## Why it's built this way

### Real-time without a second server

Multiplayer state is pushed over **Mercure** (Server-Sent Events). When a player
acts on a board, `CardController` publishes a Mercure `Update`; every browser
subscribed to that board's topic gets it and updates in place, driven by Turbo on
the front. No hand-written WebSocket glue.

The interesting bit is *where* Mercure runs. Instead of a standalone hub sitting
next to PHP, the hub is **embedded in FrankenPHP** (`order mercure after encode`
in the Caddyfile). One process serves HTTP and the SSE stream on a single internal
port: fewer moving parts, one less container, no cross-service auth dance.

### FrankenPHP in worker mode

The app runs FrankenPHP in **worker mode**: the Symfony kernel boots once and
stays in memory across requests, instead of being rebuilt on every hit like
classic PHP-FPM. Lower latency, less overhead, which is exactly what you want from
an app holding live connections open.

### TLS handled upstream, the app stays plain HTTP

In production, Red Flag Bingo sits **behind a shared global Caddy** that
multiplexes ports 80/443 across several apps on the VPS. By design:

- FrankenPHP listens **HTTP-only on port 80 of the internal Docker network**. It
  doesn't terminate TLS, doesn't ask Let's Encrypt for a certificate, and
  **exposes no host port**. The global Caddy reaches it over a shared network.
- Security headers (`Strict-Transport-Security`, `X-Content-Type-Options`,
  `X-Frame-Options`, `Referrer-Policy`) are set by the **global Caddy**, not by
  FrankenPHP, so nothing gets sent twice.

### Network isolation

The `app` service lives on two Docker networks:

- `web` (external, shared with the global Caddy): how the proxy reaches the app.
- `internal` (project-private): how `app` talks to `database` and `scheduler`.

`database` and `scheduler` sit **only** on `internal`, invisible to the proxy and
to the outside world. The `scheduler` reuses the exact image built for `app` (no
rebuild) and runs the Symfony Scheduler, e.g. purging archived red flags through a
Messenger handler.

---

## Quality

Small project, wired like a real one:

- **PHPStan level 9, no baseline.** The Doctrine layer is fully typed (entities,
  query results), so the analyzer actually earns its keep instead of waving things
  through.
- **PHPUnit** on the logic that would actually hurt if it broke: bingo win
  detection across rows, columns, both diagonals, and overlapping lines. No tests
  on getters.
- **GitHub Actions** runs PHPStan and the test suite on every push and pull
  request. Green or it doesn't ship.

```bash
composer test       # PHPUnit
composer phpstan    # static analysis, level 9
```

---

## Project structure

```
src/
  Controller/        public board + admin (dashboard, imports, theming)
    CardController   publishes Mercure updates on player actions
  Entity/            Theme, RedFlag, BingoCard
  Service/           CardGenerator, BingoChecker, ArchiveService, stats, import/export
  Security/          admin authenticator (no User entity, bcrypt + rate limiter)
  Message*/          async purge of archived red flags
  Schedule.php       Symfony Scheduler definition
config/themes/       bundled bingo themes as YAML (dating app, family dinner, the bar…)
frankenphp/          Caddyfile (dev) + Caddyfile.prod (Mercure + plain HTTP)
```

---

## Getting started (local)

Requirements: Docker + Docker Compose v2.

```bash
git clone git@github.com:Labault/red-flag-bingo.git
cd red-flag-bingo
docker compose up -d --build
docker compose exec app bin/console doctrine:migrations:migrate

# Themes live in config/themes/ as YAML. Import the ones you want:
docker compose exec app bin/console app:theme:import config/themes/date.yaml
```

The app is served by FrankenPHP; open the local URL printed by Caddy. Tailwind is
built through AssetMapper, so there's no separate Node build step.

Admin access uses a bcrypt-hashed password (no `User` entity, in-memory
authenticator, rate-limited login):

```bash
docker compose exec app bin/console app:admin:hash-password
# put the resulting hash in ADMIN_PASSWORD_HASH
```

---

## Production deployment

Production runs behind a **shared global Caddy** that terminates TLS for several
apps, so the project's own stack speaks plain HTTP internally.

**Prerequisite: the shared Docker network must exist before starting the stack.**

```bash
docker network create web
```

The global Caddy attaches to that same `web` network, with a `reverse_proxy` to
`rfb_app:80` (the container name on the network).

**Deploy:**

```bash
cp .env.prod.local.dist .env.prod.local   # fill in real secrets (never committed)
docker compose -f compose.prod.yaml up -d --build
docker compose -f compose.prod.yaml exec app bin/console doctrine:migrations:migrate
```

Secrets (`APP_SECRET`, `MERCURE_JWT_SECRET`, `POSTGRES_PASSWORD`,
`ADMIN_PASSWORD_HASH`) live in `.env.prod.local` on the server, generated with
`openssl rand`, never in the repository.

> Note: `LETSENCRYPT_EMAIL` is still in `.env.prod.local.dist` for backward
> compatibility but isn't consumed anymore. Certificate management is the global
> Caddy's job.

---

## License

All rights reserved.

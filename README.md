# Red Flag Bingo

> Spot the dating red flags together — live.

**Live demo → [redflagbingo.fun](https://redflagbingo.fun)**

Red Flag Bingo is a real-time, collaborative bingo game built around the codes
of dating apps. Players share a bingo board and tick off the red flags as they
appear — and everyone sees every move instantly, on every screen. No refresh,
no polling.

The fun is in the concept; the interesting part is the plumbing. Real-time sync
on a PHP stack usually means bolting on a separate service. Here it's done with
**Mercure running inside FrankenPHP** — a single process serving both the app
and the live updates. This README is mostly about that.

---

## Stack

| Layer      | Tech                                                       |
| ---------- | ---------------------------------------------------------- |
| Framework  | Symfony 7.4 · PHP 8.3                                       |
| Server     | FrankenPHP (Caddy-based, worker mode)                      |
| Real-time  | Mercure (SSE), integrated into FrankenPHP                  |
| Front      | Twig · Stimulus/Turbo · Tailwind v4 (AssetMapper)          |
| Database   | PostgreSQL 16 · Doctrine ORM                               |
| Async      | Symfony Messenger · Scheduler (archived-flag purge)        |
| Infra      | Docker Compose · external global Caddy reverse proxy       |

---

## Design decisions

### 1. Real-time without a separate hub

Multiplayer state is pushed over **Mercure** (Server-Sent Events). When a player
acts on a board, `CardController` publishes a Mercure `Update`; every connected
browser subscribed to that board's topic receives it and updates in place —
driven by Turbo on the front, so there's no hand-written WebSocket glue.

The notable part is *where* Mercure runs. Instead of deploying a standalone
Mercure hub alongside PHP, the hub is **embedded in FrankenPHP** (`order mercure
after encode` in the Caddyfile). One process serves HTTP *and* the SSE stream,
on a single internal port — fewer moving parts, no extra container, no
cross-service auth dance.

### 2. FrankenPHP in worker mode

The app runs on FrankenPHP with its **worker mode**: the Symfony kernel is
booted once and kept in memory across requests, instead of being rebuilt on
every hit like classic PHP-FPM. Lower latency, less overhead — which matters for
an app that's holding live connections open.

### 3. TLS terminated upstream, app stays plain HTTP

In production, Red Flag Bingo sits **behind a shared global Caddy reverse proxy**
that multiplexes ports 80/443 across several apps on the VPS. Consequences, by
design:

- FrankenPHP listens **HTTP-only on port 80 of the internal Docker network**. It
  doesn't terminate TLS, doesn't request a Let's Encrypt certificate, and
  **exposes no port on the host** — the global Caddy reaches it over a shared
  Docker network.
- Security headers (`Strict-Transport-Security`, `X-Content-Type-Options`,
  `X-Frame-Options`, `Referrer-Policy`…) are added by the **global Caddy**, not
  by FrankenPHP, to avoid duplicates.

### 4. Network isolation

The `app` service is attached to two Docker networks:

- `web` (external, shared with the global Caddy) — how the proxy reaches the app.
- `internal` (project-private) — how `app` talks to `database` and `scheduler`.

`database` and `scheduler` live **only** on `internal`, so they're invisible to
the global proxy and to the outside world. The `scheduler` reuses the exact
image built for `app` (no rebuild) and runs the Symfony Scheduler — e.g. purging
archived red flags via a Messenger handler.

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
config/themes/       declarative bingo themes (wedding, family dinner, dating…)
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
docker compose exec app bin/console app:theme:import   # load bundled themes
```

The app is served by FrankenPHP; open the local URL printed by Caddy. Tailwind
is built through AssetMapper, so there's no separate Node build step.

Admin access uses a bcrypt-hashed password (no `User` entity, in-memory
authenticator, rate-limited login):

```bash
docker compose exec app bin/console app:admin:hash-password
# put the resulting hash in ADMIN_PASSWORD_HASH
```

---

## Production deployment

Production runs behind a **shared global Caddy** that terminates TLS for several
apps. The project's own stack therefore speaks plain HTTP internally.

**Prerequisite — the shared Docker network must exist before starting the stack:**

```bash
docker network create web
```

The global Caddy must be attached to that same `web` network, with a
`reverse_proxy` to `rfb_app:80` (the container name on the Docker network).

**Deploy:**

```bash
cp .env.prod.local.dist .env.prod.local   # fill in real secrets (never committed)
docker compose -f compose.prod.yaml up -d --build
docker compose -f compose.prod.yaml exec app bin/console doctrine:migrations:migrate
```

Secrets (`APP_SECRET`, `MERCURE_JWT_SECRET`, `POSTGRES_PASSWORD`,
`ADMIN_PASSWORD_HASH`) live in `.env.prod.local` on the server, generated with
`openssl rand` — never in the repository.

> Note: `LETSENCRYPT_EMAIL` is kept in `.env.prod.local.dist` for backward
> compatibility but is no longer consumed — certificate management is delegated
> to the global Caddy.

---

## License

MIT — see [LICENSE](LICENSE).

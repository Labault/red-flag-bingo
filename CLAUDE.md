# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Stack

- **Symfony 7.4** on PHP 8.4+, served by **FrankenPHP** (worker mode, hot-reload enabled in dev via `FRANKENPHP_HOT_RELOAD`).
- **Doctrine ORM 3** + **doctrine-migrations-bundle**, against **PostgreSQL 16**. Mappings are attribute-based, scanned from `src/Entity` (configured in [config/packages/doctrine.yaml](config/packages/doctrine.yaml)).
- **Hotwire** front-end: Symfony **AssetMapper** + **importmap** (no Webpack/Encore — JS deps live in [importmap.php](importmap.php)), **Stimulus**, **Turbo**, plus **Tailwind v4** via `symfonycasts/tailwind-bundle` (no `node_modules` for Tailwind — the bundle downloads a standalone binary, version pinned in [config/packages/symfonycasts_tailwind.yaml](config/packages/symfonycasts_tailwind.yaml)). **Chart.js 4** is pulled via importmap for the admin dashboard.
- **Mercure** (`symfony/mercure-bundle` + standalone `dunglas/mercure` hub container) is wired up for real-time card updates: cell toggles, bingo banner, viewer counter — published as Turbo Streams on the topic `https://rfb.app/cards/{slug}`.
- **Messenger** transport is `doctrine://default` (no Redis/AMQP). The **Scheduler** ([src/Schedule.php](src/Schedule.php)) dispatches cron-style messages via the `scheduler_default` transport — consumed by a dedicated `scheduler` service in compose.
- **Mailer** uses Mailpit in dev (added by the override compose file).
- **Security**: bcrypt-hashed admin password stored in `.env.local` (`ADMIN_PASSWORD_HASH`), checked by [src/Security/AdminAuthenticator.php](src/Security/AdminAuthenticator.php) against an in-memory `admin` user. Rate-limited (5 / 15 min, sliding window) via [config/packages/rate_limiter.yaml](config/packages/rate_limiter.yaml).

## Running

The project is meant to run via Docker Compose ([compose.yaml](compose.yaml)):

```bash
docker compose up -d           # FrankenPHP app on :8000, Postgres on :5433, Mercure hub, scheduler worker
docker compose exec app bash   # shell into rfb_app
```

Containers:
- **`rfb_app`** — FrankenPHP serving the Symfony app on `:8000` (mapped from `:80` inside).
- **`rfb_db`** — Postgres 16, exposed on host `:5433` (in-container `:5432`). Credentials `rfb/rfb/rfb`.
- **`scheduler`** — same image as `app`, runs `messenger:consume scheduler_default` with `--time-limit=3600 --memory-limit=128M` (anti-leak restart).
- **`mercure`** — `dunglas/mercure` hub for SSE.

Inside the app container, `WORKDIR` is `/app` and `DATABASE_URL` points at the `database` service. The `.env` defaults are for running outside Docker and will not match the compose setup — use the compose-provided `DATABASE_URL` when running in-container. The compose override file ([compose.override.yaml](compose.override.yaml)) is auto-loaded by `docker compose` and adds Mailpit + extra port mappings.

## Common commands

All `bin/console` and `bin/phpunit` invocations assume you're either in the container or have a local PHP 8.4+ toolchain.

```bash
# Symfony CLI
php bin/console                              # list commands
php bin/console debug:router                 # routes
php bin/console cache:clear

# Doctrine
php bin/console doctrine:migrations:diff     # generate migration from entity diff
php bin/console doctrine:migrations:migrate  # apply migrations
php bin/console make:entity                  # scaffolding (maker-bundle, dev only)

# Tailwind (standalone binary, no npm)
php bin/console tailwind:build --watch       # dev: watch & rebuild app.css
php bin/console tailwind:build --minify      # prod build

# AssetMapper / importmap
php bin/console importmap:require <pkg>      # add a JS dep (edits importmap.php)
php bin/console asset-map:compile            # prod build of asset map

# App-specific commands
php bin/console app:admin:hash-password      # generate bcrypt hash for ADMIN_PASSWORD_HASH
php bin/console app:theme:import path/to.yml [--dry-run]  # import a theme + red flags from YAML
php bin/console app:purge-archived-red-flags [--days=90]  # CLI counterpart of the cron purge

# Messenger / Scheduler (when not running the dedicated container)
php bin/console messenger:consume scheduler_default -vv

# Tests
php bin/phpunit                              # full suite
php bin/phpunit tests/Path/To/SomeTest.php   # single file
php bin/phpunit --filter testMethodName      # single test
```

PHPUnit ([phpunit.dist.xml](phpunit.dist.xml)) is configured with `failOnDeprecation`, `failOnNotice`, `failOnWarning` all on, and `restrictWarnings`/`restrictNotices` in the source filter — **any deprecation triggered through `src/` will fail the suite**, so be deliberate about touching deprecated APIs. The test env uses a `_test`-suffixed database (`dbname_suffix`) and ParaTest-compatible per-token suffixing. Currently the suite is empty (only `tests/bootstrap.php` exists).

## Project layout

- **[src/Entity/](src/Entity/)** — three entities:
  - `Theme` — name, unique `slug`, `emoji`, `OneToMany` to `RedFlag` (orphan-remove).
  - `RedFlag` — `text` (TEXT), `rarity` (`Rarity` enum), `ManyToOne` Theme (`onDelete: CASCADE`), nullable `archivedAt` (soft-delete). Indexed on `archived_at`.
  - `BingoCard` — short unique `slug` (7 chars, no ambiguous letters), `ManyToOne` Theme, JSON `cells` (25 red-flag IDs in grid order), JSON `markedCells` (positions), `createdAt` (auto-set via `#[ORM\PrePersist]`), nullable `bingoReachedAt`. Indexed on `bingo_reached_at` and `created_at`.
- **[src/Enum/Rarity.php](src/Enum/Rarity.php)** — `Common` / `Rare` / `Legendary` (string-backed), with `label()` and `emoji()` helpers.
- **[src/Repository/](src/Repository/)** — custom queries; notably `RedFlagRepository::findByIdsIncludingArchived` / `findIncludingArchived` / `findArchivedOlderThan` / `findAllByThemeIncludingArchived` all temporarily disable the `archived_red_flag` Doctrine filter inside a `try/finally`. `BingoCardRepository` aggregates stats (per-day, per-theme, top-winning red flags) for the admin dashboard.
- **[src/Doctrine/Filter/ArchivedRedFlagFilter.php](src/Doctrine/Filter/ArchivedRedFlagFilter.php)** — Doctrine SQL filter, **enabled by default**, that adds `archived_at IS NULL` on every `RedFlag` query. Disable temporarily via `$em->getFilters()->disable('archived_red_flag')` when you legitimately need archived rows (card display, import dedup, purge).
- **[src/Controller/](src/Controller/)**:
  - `HomeController` — `/` lands on the "date" theme card-creation page.
  - `CardController` — `/card/new/{themeSlug}` creates a card, `/card/{slug}` shows it, `/card/{slug}/toggle/{position}` toggles a cell (publishes Turbo Streams via Mercure for cell + bingo banner), `/card/{slug}/heartbeat` keeps a viewer count in cache and broadcasts updates.
  - `Controller/Admin/*` — `DashboardController` (stats), `RedFlagAdminController` (CRUD + archive/restore), `ThemeAdminController`, `ImportController` (upload + preview YAML), `SecurityController` (login/logout).
- **[src/Service/](src/Service/)**:
  - `CardGenerator` — picks 15 commons + 7 rares + 3 legendaries (constants `CELLS_PER_CARD = 25`, `DISTRIBUTION`), shuffles, generates a unique 7-char slug.
  - `BingoChecker` — pure logic over a 5×5 grid (12 winning lines: rows, cols, diagonals).
  - `ArchiveService` — soft-archive / restore / purge red flags. Logs every action.
  - `Stats/StatsService` — admin-dashboard aggregations, PSR-6 cached for 5 min under prefix `admin_stats_`. Call `invalidateCache()` after bulk imports/deletes.
  - `Import/ThemeImporter` (+ `ImportReport`) — YAML import with dry-run, per-rarity playability check, dedup against archived rows.
  - `Export/ThemeExporter` — symmetric YAML export.
- **[src/Security/AdminAuthenticator.php](src/Security/AdminAuthenticator.php)** — custom authenticator (no User entity), bcrypt hash from env, rate limiter `admin_login` (factory aliased via [config/services.yaml](config/services.yaml)), CSRF token `admin_login`, redirects to `app_admin_dashboard` on success.
- **[src/Schedule.php](src/Schedule.php)** — `#[AsSchedule]` provider, stateful (replays missed runs once), runs `PurgeArchivedRedFlagsMessage(90)` daily at 03:00 UTC. Handler in [src/MessageHandler/](src/MessageHandler/) calls `ArchiveService::purgeOlderThan`.
- **[src/Command/](src/Command/)** — `app:theme:import`, `app:admin:hash-password`, `app:purge-archived-red-flags`, `app:backfill-bingo-reached-at` (one-shot data backfill).
- **[src/Form/Admin/](src/Form/Admin/)** + **[src/Dto/Import/](src/Dto/Import/)** — admin CRUD forms and YAML import DTOs (validated by `symfony/validator`).
- **[migrations/](migrations/)** — three migrations: initial schema, archived_at on RedFlag, bingoReachedAt on BingoCard.
- **[templates/](templates/)** — Twig: `base.html.twig` wires the importmap + Tailwind-built `styles/app.css` + FrankenPHP hot-reload script. `card/` partials are designed to be Turbo-Stream-replaceable (`_cell.html.twig`, `_bingo_banner.html.twig`, `_viewer_count.html.twig`). `admin/` has its own `_layout.html.twig`.
- **[assets/](assets/)** — entrypoint `app.js`, Stimulus controllers (auto-registered via `controllers.json`): `chart`, `confirm_delete`, `csrf_protection`, `dropzone`, `heartbeat`, `search_list`, plus the default `hello`. Tailwind source at `assets/styles/app.css`.
- **[config/services.yaml](config/services.yaml)** — only two explicit service overrides: `AdminAuthenticator` (injects `ADMIN_PASSWORD_HASH` env + `@limiter.admin_login`) and `ImportController` (injects `%app.imports_directory%` = `var/imports`).

## Things worth knowing before changing code

- **The `archived_red_flag` Doctrine filter is enabled globally.** Any new query that needs to see archived red flags (admin listings, card display of legacy cards, imports, exports, purge) must wrap its query with `disable('archived_red_flag')` in a `try/finally`. Existing repository helpers already do this — prefer reusing them.
- **Cards keep references to archived red flags.** Never hard-delete a `RedFlag` that may still be referenced by a `BingoCard.cells`; archive (set `archivedAt`) instead. Hard-deletion happens only via the daily cron purge for rows archived >90 days.
- **Real-time updates flow through Mercure.** Any controller mutating a `BingoCard` should publish on `https://rfb.app/cards/{slug}` to keep multi-viewer sessions consistent. Heartbeats use a PSR-6 cache key `viewers_{slug}` with a 15s freshness window.
- **`bingoReachedAt` is set on the *first* winning toggle**, and cleared if all winning lines are uncovered again — `CardController::toggle` is the source of truth. Stats queries (`countWithBingo`) rely on this field, not on recomputing from `markedCells`.
- **Password hashing**: bcrypt cost 12 in dev/prod, downgraded to `auto` cost 4 under `when@test` to keep tests fast. `ADMIN_PASSWORD_HASH` lives in `.env.local`; an empty value triggers a clear error in the authenticator.
- **Postgres identity strategy is forced to `IDENTITY`** in the Doctrine config (not `SEQUENCE`), so `#[GeneratedValue]` produces `BIGINT GENERATED BY DEFAULT AS IDENTITY` columns.
- **`auto_generate_proxy_classes` is on in dev**, off in prod — entity changes don't require a manual proxy regen locally.
- **Stats cache**: `StatsService` memoizes everything for 5 min. After admin imports/CRUD that affect counts, call `StatsService::invalidateCache()` if the change must be visible immediately on the dashboard.
- **Compose override file** ([compose.override.yaml](compose.override.yaml)) is auto-loaded and adds Mailpit + extra port mappings — host ports for Mailpit/Postgres may differ from the defaults if you don't override.

## Code Style

### Yoda conditions
Pour toute comparaison d’égalité ou d’identité avec un littéral, `null` ou une constante, place l’opérande non-variable à gauche. Ne s’applique qu’à `==`, `===`, `!=`, `!==` (pas à `<`, `>`, `<=`, `>=`, ni aux comparaisons entre deux variables/expressions).

```php
// ✅ Bon
if (null === $user) { ... }
if ('' !== $search) { ... }
if (0 === $count) { ... }
if (UserType::PATIENT === $this->userType) { ... }

// ❌ Mauvais
if ($user === null) { ... }
if ($search !== '') { ... }
```

### Alignement vertical des `=>` et `=`
Dans tout bloc d’éléments consécutifs (tableau associatif multi-lignes, suite d’assignations de variables, branches de `match`, valeurs d’`enum`), aligne verticalement les `=>` ou `=` sur la clé/variable la plus longue du bloc.

```php
// ✅ Bon — tableau
return $this->render('professional/patients.html.twig', [
    'links'       => $links,
    'totalCount'  => $totalCount,
    'currentPage' => $page,
    'totalPages'  => $totalPages,
    'search'      => $search ?? '',
]);

// ✅ Bon — assignations consécutives
$page   = max(1, $request->query->getInt('page', 1));
$search = trim((string) $request->query->get('q', ''));

// ✅ Bon — match / enum
return match ($this) {
    self::PATIENT      => 'user_type.patient',
    self::PROFESSIONAL => 'user_type.professional',
};
```

Une ligne vide ou un commentaire rompt le bloc : recalcule l’alignement de chaque sous-bloc indépendamment. Les tableaux inline (sur une seule ligne) ne sont pas concernés.

### Early returns
Privilégie les *early returns* dès que possible : sors de la fonction (par `return`, `throw`, `continue`, `break`) au plus tôt pour traiter les cas d’erreur, garde, ou sortie anticipée. Évite les `else` après un `return`/`throw` et limite l’imbrication des `if`.

```php
// ✅ Bon — early returns, pas de else superflu
public function update(MoodEntry $entry, ?User $user): Response
{
    if (null === $user) {
        throw $this->createAccessDeniedException();
    }

    if ($entry->getUser() !== $user) {
        throw $this->createNotFoundException();
    }

    return $this->render('mood/edit.html.twig', ['entry' => $entry]);
}

// ❌ Mauvais — imbrication inutile, else après return
public function update(MoodEntry $entry, ?User $user): Response
{
    if (null !== $user) {
        if ($entry->getUser() === $user) {
            return $this->render('mood/edit.html.twig', ['entry' => $entry]);
        } else {
            throw $this->createNotFoundException();
        }
    } else {
        throw $this->createAccessDeniedException();
    }
}
```

### Variables inutilisées
Ne laisse pas de variables locales assignées puis jamais lues. Si une valeur n’est utilisée que pour un effet de bord, appelle directement l’expression sans la stocker. Attention aux faux positifs : variables interpolées dans une chaîne (`"$x"`, heredoc), passées par référence (`&$item`), capturées par `use ($x)` dans une closure, ou présentes dans `compact()`/`extract()` — elles restent utilisées. En cas de doute, conserve la variable plutôt que de risquer une régression. Ne touche jamais aux paramètres de fonction (signature publique).

### Tri alphabétique
Trie par ordre alphabétique les éléments d’un même groupe dès que c’est possible sans casser le code : constantes de classe, propriétés, méthodes, branches de `match`, cases d’`enum`, clés de tableau associatif, imports `use`.

Exceptions :
- `__construct` reste toujours en premier dans une classe.
- Dans une entité Doctrine, l’identifiant (`$id`) reste en premier ; les autres propriétés sont triées.
- Si l’ordre est sémantique (étapes successives, ordre d’affichage explicite, dépendance d’une étape sur la précédente), conserve l’ordre original.
- Les paramètres d’une fonction restent dans leur ordre d’origine (signature publique).
- Les statements à l’intérieur d’une méthode ne sont jamais réordonnés (ils ont une dépendance temporelle).

```php
// ✅ Bon — constantes triées
private const CACHE_PREFIX = 'admin_stats_';
private const CACHE_TTL    = 300;

// ✅ Bon — méthodes triées (constructor first)
class FooService
{
    public function __construct(...) {}
    public function applyChanges(): void { ... }
    public function findById(int $id): ?Foo { ... }
    public function purgeAll(): int { ... }
}

// ✅ Bon — clés alphabétiques (l’ordre d’itération n’a pas d’impact sur la sortie)
$config = [
    'cache'    => true,
    'database' => $db,
    'logger'   => $logger,
];

// ✅ Bon — branches match alphabétiques
return match ($status) {
    'active'   => 'green',
    'archived' => 'gray',
    'pending'  => 'yellow',
};

// ❌ À NE PAS faire — un tableau positionnel (l’index 0 est utilisé comme premier label)
$labels = ['Janvier', 'Février', 'Mars']; // l’ordre est sémantique → ne pas trier
```

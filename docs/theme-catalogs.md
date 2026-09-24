# Theme catalogs

The seven YAML files in `config/themes/` hold the bundled French red-flag texts.
Generated boards live separately in PostgreSQL: each board stores 25 red-flag IDs
and the positions players have marked.

## Editorial contract

Each theme contains 500 entries: 300 `common`, 140 `rare`, 60 `legendary`.
This follows the board generator’s 15/7/3 draw ratio and gives each rarity pool
the same sampling rate. Rarity describes how unusual the scene is, not how cruel
the joke can get.

Keep an entry when a player can recognize the scene and tick it without guessing
someone’s motives. Favor a specific contradiction, intrusion, awkward exchange
or familiar annoyance. A harmless preference alone does not make a red flag.

- Write one self-contained situation per entry, in natural French.
- Keep text short enough for a grid cell, preferably below 100 characters.
  The editorial ceiling is 120; the importer’s hard limit is 200.
- A legendary entry should be memorable and plausible, not an invented disaster.
- Remove semantic duplicates, including variants that only swap a drink, object,
  number or speaker. Different wording does not make a different joke.
- Target behavior, not bodies, identities or medical situations.
- Use typographic apostrophes and French quotation marks; avoid em dashes.
- Write new situations individually. Do not fill a quota with sentence templates.

Theme boundaries matter: dating apps cover profiles, messages and arrangements
before meeting; Date covers the actual encounter. Wedding entries cover the
wedding day rather than months of planning. Family entries happen around the
meal, bar entries during the outing, meeting entries during the session, and
flatshare entries in shared daily life.

Keep theme slugs stable when editing. Section comments organize the editorial
review; they are not imported and do not change the random draw.

## September 2026 review

The review covered all 3,648 source entries, retaining strong situations,
rewriting their wording where needed and replacing weak or repeated ideas.
The revised catalog contains 3,500 entries.

| Theme | Before | After | Main editorial change |
| --- | ---: | ---: | --- |
| Dating apps | 557 | 500 | Profiles and pre-meeting exchanges; remove date scenes and repeated demands. |
| At the bar | 535 | 500 | Orders, rounds, space and social friction; remove harmless preferences and invented disasters. |
| Date | 450 | 500 | Observable behavior during the encounter; replace vague judgments with concrete scenes. |
| Flatshare | 520 | 500 | Shared spaces, money and boundaries; remove ordinary habits framed as faults. |
| Wedding | 500 | 500 | Focus on the wedding day, not planning or the honeymoon. |
| Family meal | 566 | 500 | Table dynamics, intrusions and familiar contradictions; remove medical and violent punchlines. |
| Work meeting | 520 | 500 | Make jargon part of a situation; remove isolated buzzwords and repeated scheduling complaints. |

These are catalog counts, not the number of rows in an existing database or the
number of generated boards. Reworded entries are not automatically matched to
their old database rows by the importer.

## Importing a revision

`app:theme:import` is additive. It creates missing texts and skips existing texts
case-insensitively, including archived rows. It does not archive removed texts,
update existing rarity values, restore archived texts or replace the whole pool.
Even punctuation changes can therefore create a new row.

Editing these files changes the bundled catalogs only. An existing database will
not become the revised 500-entry pool simply by importing them again. Replacing
an existing pool requires a separately reviewed synchronization that archives
removed entries and preserves IDs referenced by existing boards. Do not delete
a theme as a shortcut: its foreign keys cascade to red flags and boards.

Before a new import, check YAML syntax, DTO validation, normalized duplicates,
lengths and counts by rarity. Then use the existing dry-run against the intended
database:

```bash
docker compose exec app bin/console app:theme:import config/themes/date.yaml --dry-run
```

The dry-run reports additions and playability, not a replacement diff. The
editorial review of plausibility and repeated ideas still needs a human pass.

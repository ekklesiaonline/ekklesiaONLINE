# Liturgical Day Canonical Alias Strategy (Optional)

Date: 2026-04-03
Status: Optional enhancement (no runtime behavior change until enabled).

## 1) Purpose

Provide a controlled alias layer for canonical liturgical day names so legacy or variant canonical strings can resolve to stable translation keys without changing stored data.

## 2) Why This Is Optional

- Current implementation already localizes canonical service type, season, and color values.
- Many liturgical day names are church-authored or diocesan-managed text.
- Auto-translating arbitrary day names risks incorrect theology/liturgical meaning.

## 3) Suggested Alias Model

Introduce an alias dictionary for known canonical day variants:

- Alias source value (stored/canonical text)
- Canonical translation key (stable internal key)
- Locale rendering via language files

Example mapping intent:

- "Palm Sunday" -> key: liturgicalDay.palmSunday
- "Sunday of the Passion" -> key: liturgicalDay.palmSunday
- "Maundy Thursday" -> key: liturgicalDay.maundyThursday
- "Holy Thursday" -> key: liturgicalDay.maundyThursday

## 4) Resolution Rules

1. Normalize candidate text (trim, collapse spaces, lowercase for lookup only).
2. If alias exists, render through mapped language key.
3. If alias not found, render original text unchanged.
4. Never mutate canonical database value during display.

## 5) Guardrails

- Alias entries should be reviewed by diocesan liturgy owners.
- Keep alias list intentionally small and explicit.
- Do not infer translations from free-text notes/titles.
- Log unresolved high-frequency variants before adding aliases.

## 6) Rollout Plan

1. Add a minimal alias dictionary with top recurring variants only.
2. Add language keys in en/es/fr for mapped canonical day keys.
3. Wire helper-level optional alias resolver before final display.
4. Validate via locale matrix on service and worship-planning pages.
5. Expand aliases incrementally from observed production variants.

## 7) Validation Targets

- Worship/Views/Services/view.php
- Worship/Views/Services/index.php
- Worship/Views/WorshipPlanning/index.php
- Worship/Views/WorshipPlanning/service.php
- Worship/Views/WorshipPlanning/print.php

Expected behavior:

- Mapped canonical variants localize consistently in en/es/fr.
- Unmapped or custom text remains unchanged.
- No data migration required.

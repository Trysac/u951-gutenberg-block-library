# Project record

## Objective

Accessible server-rendered Gutenberg blocks with a WordPress block-grammar parser and serializer, attribute validation and markup that passes axe-core with zero violations.

## Scope

- In scope: The public API documented in README.md, block parsing/serialization, attribute validation, accessible HTML rendering, and CLI support.

## Out of Scope

- Editor JavaScript.
- Styles build tooling.
- Block patterns.
- i18n functions.
- WordPress hooks integration.

## Effort metrics

| Metric | Value |
|---|---|
| Tokens (input / output) | 2258302 / 35130 |
| Hours (human) | pending measurement |
| Cost (USD) | 0.8959 |

## Factory run u951 (measured from API usage)

| Date (UTC) | Phase | Model | Batch | Input | Output | Cache write | Cache read | Cost (USD) |
|---|---|---|---|---|---|---|---|---|
| 2026-11-17 | generation | google/gemini-4-argon | yes | 6297 | 22804 | 0 | 0 | 0.4813 |
| 2026-11-17 | fix-1 | google/gemini-3.8-flash | no | 95320 | 6985 | 0 | 879606 | 0.1637 |
| 2026-11-17 | fix-2 | google/gemini-3.8-flash | no | 73376 | 2579 | 0 | 514682 | 0.1033 |
| 2026-11-17 | audit | google/gemini-3.8-flash | no | 127464 | 851 | 0 | 539466 | 0.1393 |
| 2026-11-17 | docs | google/gemini-3.1-flash-lite | no | 22091 | 1911 | 0 | 0 | 0.0084 |

Total: $0.8959. Prices: vertex line, as measured by its adapter. Providers serve the current version of each model id, so the date tells which version answered; versions, launch stages and price sources per line are in the factory's model register (docs/MODELS.md).

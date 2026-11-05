# Tuning the search

Everything that decides what the search understands, finds and offers, and how to check a change. Most of it is
constants; change one, run the tests, release.

## The loop

1. **Collect real searches** (see `docs/demand.md` for the file and where each section comes from), and the
   searches that found nothing: WooCommerce → Status → Logs, source `oobj-search` ("No products for: …").
2. **See what isn't understood**: `php tools/unread.php queries.txt [limit]` lists what's left over as plain words,
   most searched first ("vertical helix" → "vertical"). Some are meant to stay words (labret, spike: no category or
   fact for them); the rest are what to teach next.
3. **Teach it** (below), adding each real query to `tests/query/fixtures/real-queries.php` with how it should read.
4. **Check**: `composer test:query` (seconds, no WordPress), `composer test`, and on the local site
   `wp oobj-search explain "<query>"`, `wp oobj-search coverage`, `wp oobj-search examples`.
5. **Rebuild demand** if new words were taught (`docs/demand.md`), then release.

## What words mean: `inc/query/class-oobj-search-vocabulary.php`

- Phrases for each facet value (categories, materials, fittings, colours, themes), gauges (`GAUGES`: gauge → mm),
  price words (`PRICE_LOW_PHRASES`, `PRICE_HIGH_PHRASES`), `STOP_WORDS` (dropped once the rest is understood).
- `SEASONS`: dates, what's favoured, and the season's example.
- `DEMAND`: how much each value is searched for (`docs/demand.md`).

A change to the vocabulary or to how facts are worked out changes `OOBJ_SEARCH_Facts::version()`, so every product's
facts are worked out again in the background (Action Scheduler) after the release.

## What products are: `inc/query/class-oobj-search-product-facts.php`

Facts come from a product's name, tags, attributes, labelled description lines and categories, precision first. To
check a change across the whole shop: `wp eval-file tools/export-products.php > products.json` (every published
product, in the shape it reads), then compare facts before and after. `tests/query/fixtures/products.json` is a
sample of 272 with expected facts (`ProductFactsFixtureTest`): add a product there when a fix is about it.
`wp oobj-search coverage` shows how many products have each kind of fact.

## What's found: `inc/class-oobj-search-search.php`

- `RELAX_ORDER`: when nothing matches, the order parts are let go in (words first, category last).

## What's suggested: `inc/class-oobj-search-suggestions.php`

| Constant | Now | What it does |
|---|---|---|
| `MAX` | 5 | Suggestions at most (one of each kind). |
| `MIN_RESULTS` | 6 | Fewer results: no suggestions and no "Search by". |
| `MIN_FOUND` | 3 | A suggestion or "Search by" link must find at least this many. |
| `IDEAL_SHARE` | 0.3 | The share of the results a suggestion fits best at keeping. |
| `MAX_LIFT` | 4 | Cap on how much more common among the results than in the shop counts. |
| `SEASON_BOOST` | 3 | In-season values' score multiplier. |
| `PRICES` | 5, 8, 10, 15, 20 | Price limits offered. |
| `PRICE_WEIGHT`, `SORT_WEIGHT` | 2.5, 0.6 | How price limits and "cheapest" score against facts. |
| `NOUNS` | `fitting:clicker` | Values that go after the search ("septum clicker"), not before. |

## The search panel: `inc/class-oobj-search-examples.php`

- `EXAMPLES`: the example searches. Between them they must use everything the search understands
  (`ExamplesTest`); `wp oobj-search examples` shows what each finds.
- `GROUPS`: the "Search by" groups and their order; `MIN_PRODUCTS` (10) a value needs to get a button;
  `PER_GROUP` (10) buttons at most; `PRICES`: the panel's price buttons.

How many examples show (3) and where the panel sits are the `oob` theme's (`assets/js/search-panel.js`).

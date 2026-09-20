# What breaks these tests

A green test proves nothing until you know what turns it red. Each mutation
below was applied to a **committed** tree, run, and reverted. The results are
what was measured on 2026-09-03, not what was predicted.

Baseline: `OK (4 tests, 7 assertions)`.

| Mutation | Edit | Measured |
|---|---|---|
| M0 | `register.php` — remove the `add_action( 'plugins_loaded', 'mhmuicore_boot', 0 )` call | **3 failures**: priority, arbitration, shipped-bootstrap |
| M1 | `register.php` — priority `0` → `20` | **1 failure**: priority |
| M2 | `register.php` — `version_compare( ..., '>' )` → `'<'` | **2 failures**: arbitration, shipped-bootstrap |
| M3 | `tests/bootstrap-wp.php` — neutralise the `tests_add_filter` registration | **3 failures**: priority, arbitration, shipped-bootstrap |
| M4 | `tests/bootstrap-wp.php` — hoist `require register.php` out of the closure to the top level | **exit code 1**, no tests run |

## Why M0 exists

`has_action()` returns an int priority when the callback is registered and
`false` when it is not, and priority zero is falsy. Written as
`assertEquals( 0, has_action( ... ) )` the priority test passes against a
*missing* registration — measured directly with this package's own PHPUnit:
`assertEquals( 0, false )` passes, `assertSame( 0, false )` fails.

M1 does not cover that hole. Changing the priority turns the test red under
either assertion, so a mutation set with M1 alone would report a healthy gate
while the assertion that matters was inert. M0 is what pins `assertSame`.

## Why M4 exists

`register.php` opens with `if ( ! defined( 'ABSPATH' ) ) { exit; }`, and a bare
`exit` is status **0**. Requiring it from this bootstrap's top level — an edit
that reads as a tidy-up — ends the PHPUnit process inside the bootstrap: no
banner, no `No tests executed!`, exit 0, green CI step. `failOnEmptyTestSuite`
does not catch it, because the process never reaches the point of having a
suite.

The shutdown guard at the top of `tests/bootstrap-wp.php` converts that silence
into exit 1. M4 is the mutation that proves the guard still works.

Two other ways to get the order wrong already failed loudly and need no guard:
`functions.php` after `tests_add_filter` is an undefined-function fatal, and
`includes/bootstrap.php` before `tests_add_filter` leaves the callback
registered on a hook WordPress has already fired, which turns the tests red.

## Vacuous-green declaration

`test_wordpress_dispatched_plugins_loaded_exactly_once` stayed green under M0
through M3 and would stay green with none of this package's code loaded. That is
deliberate and it is not a claim about the loader: an empty registry and a hook
that never fired produce the same absence, and this assertion is what separates
them. Without it the arbitration test could be satisfied for a reason that has
nothing to do with the code under test.

Of the other three tests, **no mutation leaves all of them green** — each goes
red under at least two of M0–M3, and none is green under M0 or M3.

## Running them again

```bash
docker compose -f docker/test/docker-compose.yml run --rm php composer test:wp
```

Mutate, run, then `git checkout -- <file>`.

🔴 **Only on a committed tree.** During the round that produced this file a
mutation was applied to a tree with uncommitted work, and the revert threw that
work away — the rule is here because it was broken once.

## 2026-09-20 — icon vocabulary (Task 3: three renderers + gate 6 dictionary + K5)

Three mutations, applied one at a time to commit `8216f54` (task 3's own commit,
which is already `git archive HEAD`-clean), run, and reverted. Unlike M0-M4
above, these three do not all measure the same suite — two of them (M5, M6)
are caught by the JS gate 6 file and the PHP snapshot check, not by
`tests/Integration/`; M7 is the only one caught inside this directory. They are
recorded here anyway, at the same home as M0-M4, per the round's decision.

Baselines (all green before any mutation):
- `npx jest tests/Gate/kit-parity.test.js` → 7 tests green (includes the new
  `gate 6 -- the icon vocabulary is one table with two copies` describe block).
- `composer check:kit-parity` → `kit-parity: 2 PHP renderer(s), snapshot in sync`.
- `docker compose -f docker/test/docker-compose.yml run --rm php bash -c "composer test:wp"`
  → `OK (17 tests, 90 assertions)`, STDERR prints
  `IconVocabularyTest: WordPress 7.1, 350 dashicons`.

| Mutation | Edit | Measured |
|---|---|---|
| M5 | `src/Kit/Icons.php` — `SEED`'in `'revenue' => 'money-alt'` satırı → `'revenue' => 'money'` | **1 failure**, isolated: `npx jest tests/Gate/kit-parity.test.js -t "every seed concept"` red (`SEED` ≠ `PHP_SEED`, diff shows `"revenue": "money"` vs `"money-alt"`); the other six tests in that file (EMPTY-SET guards, fixture snapshot, branch coverage, DIRECTIONS pin) stay green |
| M6 | M5 kept, **plus** the same edit mirrored in `src-react/icons.js` (`revenue: 'money-alt'` → `revenue: 'money'`) — the two twins agree with each other again | The dictionary test itself goes **green** (`SEED` now equals `PHP_SEED` again, both say `money`) — a mutation that stays vacuously invisible to gate 6's own table comparison. But `composer check:kit-parity` → **red**: `kit-parity: src-react/kit-classes.json is stale -- run composer dump:kit-classes` (the committed snapshot still has `money-alt`). And `npx jest tests/Gate/kit-parity.test.js -t "every fixture"` → **red**: fixture 21 (`Concept`) renders `dashicons-money` against a committed snapshot of `dashicons-money-alt`. **Meaning:** breaking both twins identically hides the change from the table-vs-table test, but the committed snapshot and the JSX-vs-snapshot fixture test are a second, independent measurement of the same fact — the two protections do not mask each other |
| M7 | `src/Kit/Icons.php` — `SEED`'in `'place' => 'location-alt'` anahtarı → `'location' => 'location-alt'` (K5 ihlali: anahtar artık gerçek bir Dashicon adı) | Docker `composer test:wp` → **1 failure**: `IconVocabularyTest::test_no_concept_KEY_is_a_real_dashicon` — *"concept 'location' is ALSO a dashicon name: a consumer writing it as a raw suffix would silently get a different icon"*. The other 16 tests (including `test_every_concept_VALUE_is_a_real_dashicon` and the positive control) stay green; `WordPress 7.1, 350 dashicons` still printed to STDERR |

Reverted with `git checkout -- src/Kit/Icons.php src-react/icons.js` after each
step; `git diff src/Kit/Icons.php src-react/icons.js` empty afterward.

### Running M5-M7 again

```bash
npx jest tests/Gate/kit-parity.test.js   # M5, M6 (dictionary + fixture halves)
composer check:kit-parity                # M6 (snapshot half)
docker compose -f docker/test/docker-compose.yml run --rm php bash -c "composer test:wp"   # M7
```

Mutate `src/Kit/Icons.php` (and, for M6, `src-react/icons.js`), run, then
`git checkout -- <file>` — same committed-tree rule as above.

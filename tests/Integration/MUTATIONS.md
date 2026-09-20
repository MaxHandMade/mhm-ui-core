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

## 2026-09-20 — icon-concept convergence gate (Task 4)

Three mutations, applied one at a time to commit `897907a` (task 4's own commit,
already `git archive HEAD`-clean), run via `composer check:icon-concepts`, and
reverted with `git checkout -- tests/Fixtures/icon-concepts/raw-suffix.php`.
Unlike M0-M7, these measure `bin/check-icon-concepts.php`'s exit code directly
(`echo "exit=$?"`, never through `| tail`) against the **anchored fixture
tree** (`tests/Fixtures/icon-concepts/`), not the package's own `src/`/
`src-react/` — measured 2026-09-20: not one anchored icon literal exists there,
so a gate that scanned it could never go red. This is the v1 defect the gate
was rewritten to close (see `IconConceptScanner`'s class docblock).

Baseline (before any mutation): `composer check:icon-concepts` → STDOUT ends
`icon-concepts: 5 file(s), 2 concept call site(s), 2 raw, 1 unknown`, **exit=0**
(`--expect-raw=2` holds).

| Mutation | Edit | Measured |
|---|---|---|
| M8 | `tests/Fixtures/icon-concepts/raw-suffix.php` — `'icon' => 'money-alt'` → `'icon' => 'revenue'` (the violation itself is removed) | **exit=2**. STDOUT: `icon-concepts: 5 file(s), 3 concept call site(s), 1 raw, 1 unknown`; STDERR: `EXPECT-RAW: wanted 2, measured 1` |
| M9 | M8 reverted first, then `mhmuicore_stat_card_html` → `some_other_helper` in the same file (the anchor is removed, so the file is no longer a kit call site) | **exit=2**. STDOUT: `icon-concepts: 4 file(s), 2 concept call site(s), 1 raw, 1 unknown` (files 5→4); STDERR: `EXPECT-RAW: wanted 2, measured 1` |
| — | `git checkout -- tests/Fixtures/icon-concepts/raw-suffix.php` (return to baseline) | **exit=0**, STDOUT identical to the baseline line above; `git diff` empty |

**Meaning:** both mutations independently turn the gate red, and for different
reasons — M8 by making the vocabulary violation disappear (raw count drops),
M9 by making the *file itself* disappear from the scan (file count drops).
Neither is caught by the other's mechanism: a gate that only compared file
counts would miss M8, and one that only compared raw counts would miss a
whole class of "the anchor silently stopped matching" failures, which is
exactly what M9 stands in for. `--expect-raw=2` is what turns a merely-passing
run into one that failed loudly the moment either invariant broke.

### Running M8-M9 again

```bash
cd C:/projects/mhm-ui-core
composer check:icon-concepts ; echo "exit=$?"            # exit=0 (baseline)
sed -i "s/'icon' => 'money-alt'/'icon' => 'revenue'/" tests/Fixtures/icon-concepts/raw-suffix.php
composer check:icon-concepts ; echo "exit=$?"            # exit=2 (M8)
git checkout tests/Fixtures/icon-concepts/raw-suffix.php
sed -i "s/mhmuicore_stat_card_html/some_other_helper/" tests/Fixtures/icon-concepts/raw-suffix.php
composer check:icon-concepts ; echo "exit=$?"            # exit=2 (M9)
git checkout tests/Fixtures/icon-concepts/raw-suffix.php
composer check:icon-concepts ; echo "exit=$?"            # exit=0 (back to baseline)
```

Mutate only on a committed tree, then `git checkout -- <file>` — same rule as
M0-M7 above.

## 2026-09-20 — icon-concept gate, fix round 1 (Task 4): the untested exit-1 path

Fix round 1 added `tests/Kit/IconConceptScannerCliTest.php`, which runs
`bin/check-icon-concepts.php` as a real process (`exec()`), with **no**
`--expect-raw` flag — the flag `composer.json`'s `check:icon-concepts` script
always passes, and therefore the only invocation any CI step or prior test
ever exercised. Before this file existed, the CLI's actual consumer-facing
line — `exit( array() === $result['raw'] ? 0 : 1 )` — had never been run with
`$expect_raw === null`.

Applied to commit `feefc56` (this fix round's own commit, `git archive
HEAD`-clean), run, and reverted.

Baseline: `./vendor/bin/phpunit -c phpunit.xml --filter IconConceptScannerCliTest`
→ `OK (2 tests, 3 assertions)`.

| Mutation | Edit | Measured |
|---|---|---|
| M10 | `bin/check-icon-concepts.php` — `exit( array() === $result['raw'] ? 0 : 1 )` → `? 1 : 0` (the exit-code ternary inverted) | **2 failures**, both in `IconConceptScannerCliTest`: `test_a_raw_suffix_exits_1_with_no_expect_raw_flag` (`Failed asserting that 0 is identical to 1`) and `test_a_clean_input_exits_0_with_no_expect_raw_flag` (`Failed asserting that 1 is identical to 0`) — every other test in the suite (373 total, including `IconConceptScannerTest`'s 6 and every `--expect-raw`-based measurement) stayed green, because `--expect-raw` never reaches this ternary |

Reverted with `git checkout -- bin/check-icon-concepts.php`; re-ran
`IconConceptScannerCliTest` → `OK (2 tests, 3 assertions)` again; `git diff
bin/check-icon-concepts.php` empty.

**Meaning:** M10 is the mutation none of M8/M9/`--expect-raw` could ever
catch, by construction — they all run through the `--expect-raw` branch,
which returns before reaching the plain `? 0 : 1` line. Only a test that
invokes the CLI without that flag exercises the line a real consumer's CI
actually depends on.

### Running M10 again

```bash
cd C:/projects/mhm-ui-core
./vendor/bin/phpunit -c phpunit.xml --filter IconConceptScannerCliTest   # OK (2 tests, 3 assertions)
sed -i "s/? 0 : 1 );/? 1 : 0 );/" bin/check-icon-concepts.php
./vendor/bin/phpunit -c phpunit.xml --filter IconConceptScannerCliTest   # 2 failures
git checkout bin/check-icon-concepts.php
./vendor/bin/phpunit -c phpunit.xml --filter IconConceptScannerCliTest   # OK (2 tests, 3 assertions)
```

Mutate only on a committed tree, then `git checkout -- <file>` — same rule as
M0-M9 above.

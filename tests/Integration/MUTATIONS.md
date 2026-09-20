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

## 2026-09-20 — vertical rhythm ownership moves to the page shell (Task 5)

Three mutations, applied one at a time to commit `9761d56` (this task's own
commit, `git archive HEAD`-clean), run, and reverted. All three are caught by
the new `tests/Gate/layout.test.js` describe block `vertical rhythm is owned
by the page shell (0.14.0)`.

Baseline (green before any mutation): `npx jest tests/Gate/layout.test.js` →
19 tests green (15 pre-existing + 4 new).

Before the CSS was written, the rule was measured in the browser on both
surfaces (admin `wp-admin`, logged in; front, logged out) with a probe mu-plugin
at `C:/projects/rentiva-dev/mu-plugins/ui-core-rhythm-probe/` (Chrome
`HeadlessChrome/153.0.0.0`, WordPress 7.1.1, puppeteer-core, disk-edited CSS +
hard reload, no CSSOM): `.mhmui-stats-grid`/`.mhmui-widget` went from
16px/12px (admin) and 0px/0px (front) to **12px/12px on both surfaces**, while
`h1`/`.notice`/`p` stayed at their baseline (0px/5px/13px admin; 0px/0px/0px
front) and neither page grew a horizontal scrollbar. Full numbers are the ones
just given above; no report file backs them in this repository --
`.superpowers/` is git-ignored, so a path under it (as an earlier draft of this
line pointed to) does not exist in any clone. This paragraph is the durable
record.

| Mutation | Edit | Measured |
|---|---|---|
| M11 | `assets/react/admin.css` — `.mhmui-stats-grid` regains `margin-top: 16px;` | **1 failure**: `the stats grid no longer carries its own outer margin` (`ruleBody` for `.mhmui-stats-grid` matches `/margin(-top\|-block-start)?\s*:/`) — the other 3 rhythm tests and all 15 pre-existing tests stay green |
| M12 | `assets/react/admin.css` — the rhythm selector `.mhmui-admin-page > * + :is( .mhmui-stats-grid, .mhmui-widget, .mhmui-pagination, .mhmui-notice )` → `.mhmui-admin-page > * + *` (bare sibling combinator, no `:is()` guard) | **2 failures**: `the rhythm never targets core-owned elements` (the file now matches the banned `mhmui-(admin\|front)-page > * + *` shape) **and** `both shells space their kit-member children with --mhmui-space-3` (the exact `:is(...)`-qualified selector `ruleBody` looks for no longer exists in admin.css) |
| M13 | `assets/react/admin.css` — the whole rhythm rule block deleted | **1 failure**: `both shells space their kit-member children with --mhmui-space-3` (admin.css half: `ruleBody` returns `null` for the shell+`:is(...)` selector) — the front.css half of the same test stays green, confirming the check inspects each file independently |

Reverted with targeted `Edit` calls restoring the exact committed text after
M11 and M12, and `git checkout -- assets/react/admin.css` after M13; `git diff
assets/react/admin.css assets/react/front.css` empty afterward; full suite
(`npm test`) green again (185/185).

### Running M11-M13 again

```bash
cd C:/projects/mhm-ui-core
npx jest tests/Gate/layout.test.js -t "vertical rhythm"   # baseline: 4/4 green

# M11 -- put the grid's own margin back
sed -i "s/\tgap: var( --mhmui-gap );\n/&\tmargin-top: 16px;\n/" assets/react/admin.css   # or edit by hand
npx jest tests/Gate/layout.test.js -t "vertical rhythm"   # 1 failure
git checkout -- assets/react/admin.css

# M12 -- widen the rhythm selector to a bare sibling combinator
# (edit admin.css: replace `> * + :is( .mhmui-stats-grid, .mhmui-widget, .mhmui-pagination, .mhmui-notice )` with `> * + *`)
npx jest tests/Gate/layout.test.js -t "vertical rhythm"   # 2 failures
git checkout -- assets/react/admin.css

# M13 -- delete the rhythm rule entirely
# (edit admin.css: remove the `.mhmui-admin-page > * + :is(...)` block)
npx jest tests/Gate/layout.test.js -t "vertical rhythm"   # 1 failure
git checkout -- assets/react/admin.css
```

Mutate only on a committed tree, then `git checkout -- <file>` — same rule as
M0-M10 above.

## 2026-09-20 — vertical rhythm, fix round 1 (Task 5): a comment-blind gate + the untested front.css leg

Two findings from independent review of the Task 5 commit (`9761d56`), fixed together on top of it.

### Finding 1 — the gate scanned raw CSS text, comments included

`the rhythm never targets core-owned elements` matched its banned-shape regex against the RAW file
string, never stripped of comments -- unlike every other check in this file (`ruleBody()`'s own head,
and `classUniverse()` in `tests/Gate/kit-parity.test.js`, both strip `/* ... */` before matching). The
original Task 5 commit's docblock explained the selector choice with prose that happened to spell out
the banned shape literally (`` `.mhmui-admin-page > * + *` ``) as a counter-example -- the gate read its
own explanatory comment as a violation. The wrong fix (what the first pass did, and review rejected) is
rewording the comment to dodge the regex; the right fix is narrowing the gate to code, matching this
file's own established pattern: `const code = css.replace( /\/\*[\s\S]*?\*\//g, '' );` before the
`toMatch`. Applied; the docblock's literal counter-example text was restored verbatim (the reword from
the first pass reverted) as the POSITIVE control.

**Positive control** (comment restored to contain the literal banned shape, admin.css and front.css
both): `npx jest tests/Gate/layout.test.js -t "vertical rhythm"` → **4/4 green**, including `the rhythm
never targets core-owned elements` -- confirms the narrowed gate no longer treats its own documentation
as a violation.

**Blindness check** (M12 re-run against the fixed gate, LIVE CODE this time, not a comment): `admin.css`
`.mhmui-admin-page > * + :is( .mhmui-stats-grid, .mhmui-widget, .mhmui-pagination, .mhmui-notice )` →
`.mhmui-admin-page > * + *` (the same edit as M12): **2 failures** -- `the rhythm never targets
core-owned elements` (the intended catch, still fires after comment-stripping) and `both shells space
their kit-member children with --mhmui-space-3` (the exact `:is(...)`-selector no longer exists).
Reverted (`Edit` restoring the exact committed rule text); `git diff` on the rule itself empty afterward.
Confirms stripping comments did not blind the gate to the real defect it exists to catch.

### Finding 2 — the docblock overclaimed: only one of four targets actually lost its own margin

`.mhmui-stats-grid`'s own `margin-top: 16px` was removed, but `.mhmui-widget`
(`margin-top: var(--mhmui-gap)`), `.mhmui-pagination` (`margin-top: 12px`) and `.mhmui-notice`
(`margin: 12px 0`) all still carry their own margin declarations in `admin.css` -- measured by reading
the file, not assumed. The shell rule (0,2,0) always outranks these (0,1,0) declarations inside the
shell regardless of source order, but the override is invisible today because `--mhmui-gap`, the two
literals, and `--mhmui-space-3` all resolve to the same 12px. **No code changed** -- those own-margins
are the only spacing source for a shell-less surface (the existing "known limit" paragraph), so removing
them would be an unmeasured regression. Both docblocks (`admin.css`, `front.css`) rewritten to state the
measured fact: admin.css's three other targets keep their own margins and the shell overrides them only
inside the shell (today invisibly, since the values match); front.css defines none of the three targets
at all, so there is no overlap there -- the shell rule is their sole spacing source on the front end.

### Finding 3 (M14) — front.css's half of the rhythm rule had never been mutated

M11-M13 (Task 5's own commit) only touched `admin.css`. The `for` loop in `both shells space their
kit-member children with --mhmui-space-3` covers `front.css` by construction, but that coverage had
only been read, not measured.

**M14** — `front.css`: the whole `.mhmui-front-page > * + :is( .mhmui-stats-grid, .mhmui-widget,
.mhmui-pagination, .mhmui-notice )` rule block deleted:
```
npx jest tests/Gate/layout.test.js -t "vertical rhythm"
```
→ **1 failure**: `both shells space their kit-member children with --mhmui-space-3` (front.css half:
`ruleBody` returns `null` for `.mhmui-front-page` + the rhythm selector) -- the admin.css half of the
same test, and all three other rhythm tests, stayed green. Reverted by re-inserting the exact committed
rule text (not `git checkout`, since the docblock fix above was uncommitted at the same time and had to
be kept); `git diff` on the rule text itself empty afterward (only the docblock prose differs from
`9761d56`, as intended).

### Re-running (fix round 1)

```bash
cd C:/projects/mhm-ui-core
npx jest tests/Gate/layout.test.js -t "vertical rhythm"   # baseline: 4/4 green

# Finding 1 blindness check -- mutate LIVE CODE, not a comment
# (edit admin.css: replace `> * + :is( .mhmui-stats-grid, .mhmui-widget, .mhmui-pagination, .mhmui-notice )` with `> * + *`)
npx jest tests/Gate/layout.test.js -t "vertical rhythm"   # 2 failures
# revert by restoring the exact selector text

# M14 -- delete the front.css leg of the rhythm rule
# (edit front.css: remove the `.mhmui-front-page > * + :is(...)` block)
npx jest tests/Gate/layout.test.js -t "vertical rhythm"   # 1 failure
# revert by restoring the exact rule block
```

Mutate only on a committed tree (or, when other uncommitted-but-intentional edits are present in the
same file, restore by re-inserting the exact prior text rather than `git checkout`, and confirm with
`git diff` that only the intended lines differ from the last commit afterward) -- same rule as M0-M13
above.

## 2026-09-20 — vertical rhythm, fix round 2 (final fix wave): the gate name outran what it measured (Finding 7)

Independent review of the round-1 gate found it named itself `the rhythm never targets core-owned
elements` but only ever banned the one literal shape `> * + *`: `.mhmui-admin-page > * + p` (a raw tag)
or `.mhmui-admin-page > *:not(h1) + *` (a negated wildcard) still targeted a core-owned element while
staying green. Fix: narrow the name, widen the ban -- `rhythmTargetsAreWrapped()` in
`tests/Gate/layout.test.js` now requires that whatever the LAST combinator in a shell-prefixed selector
(`mhmui-(admin|front)-page`) introduces is a `:is(...)` group, matching the shape the shipped rule
already uses. Comments are stripped first, same as every other check in this file, so the gate cannot
mistake a docblock describing the ban for a violation of it.

**M15**, three mutations, applied one at a time to `assets/react/admin.css` on a tree with other
uncommitted-but-intentional edits already present in the same file (this fix wave's own Finding-3 path
correction), reverted by re-inserting the exact prior selector text (not `git checkout`, for the same
reason as M14) and confirmed with `git diff` that only the intended lines differ from the last commit
afterward:

| Mutation | Edit | Measured |
|---|---|---|
| M15a | `.mhmui-admin-page > * + :is( .mhmui-stats-grid, .mhmui-widget, .mhmui-pagination, .mhmui-notice )` → `.mhmui-admin-page > * + p` (the finding's own named bypass: an unwrapped tag) | **2 failures**: `the rhythm never targets core-owned elements` and `both shells space their kit-member children with --mhmui-space-3` (the exact `:is(...)`-selector `ruleBody` looks for no longer exists) |
| M15b | same rule → `.mhmui-admin-page > * + *` (the OLD literal ban's own shape, still unwrapped) | same **2 failures** as M15a |
| M15c (baseline) | rule restored to the shipped `+ :is( .mhmui-stats-grid, .mhmui-widget, .mhmui-pagination, .mhmui-notice )` | **0 failures** -- full `tests/Gate/layout.test.js` green, 19/19 |

```bash
cd C:/projects/mhm-ui-core
npx jest tests/Gate/layout.test.js            # baseline: 19/19 green

# M15a -- edit admin.css: `> * + :is( .mhmui-stats-grid, .mhmui-widget, .mhmui-pagination, .mhmui-notice )` -> `> * + p`
npx jest tests/Gate/layout.test.js -t "vertical rhythm"   # 2 failures
# revert by restoring the exact selector text

# M15b -- edit admin.css: same rule -> `> * + *`
npx jest tests/Gate/layout.test.js -t "vertical rhythm"   # 2 failures
# revert by restoring the exact selector text

npx jest tests/Gate/layout.test.js            # M15c: back to 19/19 green
git diff assets/react/admin.css assets/react/front.css   # only the intended doc fixes remain
```

Three inline unit tests pin the same three shapes directly against `rhythmTargetsAreWrapped()` without
touching the shipped CSS (`M15 mutation: `> * + p` …`, `` M15 mutation: `> * + *` … ``, `` M15 baseline:
… ``), so a future change to this function is caught even before anyone thinks to mutate the real
stylesheet again.

## 2026-09-20 — icon-concept gate, fix round 3 (Task 4): js_icons() stops reading comments

Codex PR #32 measured a real false positive: `js_icons()`'s bare per-line regex read a `//`-commented
example (`// icon: 'money-alt'`) as a live call site. The fix added `strip_js_comments()`, a small
character state machine run before the regex — it tracks single/double/backtick strings (so a `//`
inside a URL literal is not mistaken for a comment start) and blanks comment text with spaces rather
than deleting it (so every newline survives and reported line numbers stay correct). `php_icons()` was
never affected — `token_get_all()` already gives a comment its own `T_COMMENT` token — so both mutations
below are JS-only, applied one at a time to commit `e98a58a` (this fix round's own commit, `git archive
HEAD`-clean), run via `./vendor/bin/phpunit -c phpunit.xml --filter IconConceptScannerTest`, and reverted
with `git checkout -- src/Kit/IconConceptScanner.php`.

Baseline: `OK (9 tests, 35 assertions)`. Fixture-tree regression fence: `kit-caller.jsx` carries both a
`//` and a `/* */` decoy next to its real `money-alt` call site — with the fix in place,
`composer check:icon-concepts` still measures `5 file(s), 2 concept call site(s), 2 raw, 1 unknown`
(`--expect-raw=2` unchanged); the table below is what breaks if the fix is removed.

| Mutation | Edit | Measured |
|---|---|---|
| M16a | `js_icons()` — `explode( "\n", $this->strip_js_comments( $source ) )` → `explode( "\n", $source )` (comment-stripping call removed entirely) | **4 failures**: `test_a_raw_suffix_with_a_concept_is_reported_in_both_languages` (`raw` count 2→4 — both decoys in `kit-caller.jsx` are now read as raw hits), `test_a_data_attribute_is_not_mistaken_for_the_icon_prop` (same count assertion), `test_a_commented_icon_example_is_never_reported` (the isolated `//`+`/* */` fixture now reports 2 raw instead of 0), `test_a_real_call_site_survives_next_to_commented_decoys` (3 `money-alt` hits in `kit-caller.jsx` instead of 1) — the other 5 tests (including the URL test) stayed green |
| M16b | `strip_js_comments()` — the `if ( ( "'" === $char) \|\| ('"' === $char) \|\| ('`' === $char) ) { $state = 'string'; $quote = $char; }` block removed (string-awareness disabled, comments still stripped) | **1 failure, isolated**: `test_a_double_slash_inside_a_string_is_not_mistaken_for_a_comment` (`Failed asserting that 0 is identical to 1`) — the `//` inside `'https://example.com/icons'` is now read as a real comment start and blanks the rest of the line, including the real `mhmuicore_stat_card_html( { icon: 'revenue' } )` call sharing that line; the other 8 tests, including the two comment-decoy tests, stayed green because M16b does not touch comment-detection itself, only string-boundary tracking |

**Meaning:** M16a and M16b are caught by disjoint test sets — M16a breaks every test that depends on
comments actually being stripped, M16b breaks only the one test that depends on strings being respected
*while* stripping. Neither mutation is covered by the other's failure set, so both halves of the fix are
independently pinned.

**A fixture design note, measured while writing M16b's test:** the URL and the real call site were first
written on two separate lines. M16b did not turn that version red — `line_comment` already resets at
every `"\n"` regardless of string-awareness, so an unterminated `//` inside a URL only ever corrupts the
*rest of that same line*, never a later one. The test only exercises what it claims once the URL and the
real call share one line (see the `c2bdd6d` follow-up commit) — a reminder that a mutation test proves
nothing about a fixture it was never run against.

### Running M16 again

```bash
cd C:/projects/mhm-ui-core
./vendor/bin/phpunit -c phpunit.xml --filter IconConceptScannerTest   # baseline: OK (9 tests, 35 assertions)

# M16a
git diff --stat src/Kit/IconConceptScanner.php   # confirm clean before mutating
sed -n '399p' src/Kit/IconConceptScanner.php     # explode( "\n", $this->strip_js_comments( $source ) )
# edit that line by hand to: explode( "\n", $source )
./vendor/bin/phpunit -c phpunit.xml --filter IconConceptScannerTest   # 4 failures
git checkout -- src/Kit/IconConceptScanner.php

# M16b -- remove the `if ( ( "'" === $char ) || ... ) { $state = 'string'; $quote = $char; }` block
# from strip_js_comments()'s 'normal' branch by hand
./vendor/bin/phpunit -c phpunit.xml --filter IconConceptScannerTest   # 1 failure
git checkout -- src/Kit/IconConceptScanner.php

./vendor/bin/phpunit -c phpunit.xml --filter IconConceptScannerTest   # back to OK (9 tests, 35 assertions)
```

Mutate only on a committed tree, then `git checkout -- <file>` — same rule as M0-M10 above. (`sed -i`
could not express M16a/M16b safely as one-liners against this multi-line method, so both were applied by
hand with the Edit tool and reverted with `git checkout`, per the house rule that the measurement matters,
not the tool.)

## 2026-09-20 — icon-concept gate, fix round 4 (Task 4): a regex literal stops swallowing files

Fix round 3's `strip_js_comments()` had no notion of a JS regex literal. Measured on the fix-round-3
tree (commit `7ca0730`), against a single anchored `.jsx` file holding
`const trim = ( s ) => s.replace( /\/*abc/, '' );` and two real raw suffixes:

```
php bin/check-icon-concepts.php <that file>
EMPTY-SET: 1 anchored file(s) but not one readable icon value -- green here proves nothing
exit=2
```

and, with one additional clean file that does measure something — the shape a real consumer tree has:

```
php bin/check-icon-concepts.php <that file> <a clean file>
icon-concepts: 2 file(s), 1 concept call site(s), 0 raw, 0 unknown
exit=0
```

Two real violations gone, gate green, not one warning: `\/*` was read as `/*`, the block comment never
closed, and the rest of the file was blanked. `EMPTY-SET` only caught the first run because nothing else
measured anything. A second shape, `const re = /https:\/\//; const c = { icon: 'money-alt' };`, lost the
rest of its line the same way.

Commit `e6d8b6f` fixes both by honouring a backslash escape in CODE state, and — because that is a patch
on two measured symptoms, not a JS parser — makes an unreadable file loud instead of silent: reaching EOF
inside a block comment returns `null`, `scan()` files it under a new `failed` bucket, and the CLI prints
`MEASURE-FAILED: <file> -- ...` and exits 2 **before** `--expect-raw` can answer.

**Baseline (commit `e6d8b6f`):** `./vendor/bin/phpunit -c phpunit.xml --filter IconConceptScanner` →
`OK (16 tests, 63 assertions)`. `composer check:icon-concepts` → `exit=0`,
`icon-concepts: 5 file(s), 2 concept call site(s), 2 raw, 1 unknown` (the fixture tree's totals and
`--expect-raw=2` are unchanged by this round — the four new cases use throwaway files the tests write
and `unlink`, so the committed tree never grows a pathological fixture).

| # | Mutation | Measured result |
| --- | --- | --- |
| M17a | `strip_js_comments()` — the `if ( ( '\' === $char ) && ( '' !== $next ) ) { $out .= $char . $next; ++$i; continue; }` block removed from the `'normal'` branch (escape-tracking gone, everything else intact) | **3 failures**: `test_an_escaped_slash_star_in_a_regex_does_not_swallow_the_file`, `test_an_escaped_slash_pair_in_a_regex_does_not_hide_the_rest_of_its_line`, `test_POSITIVE_CONTROL_comments_stay_unscanned_while_real_call_sites_report` — the other 13 stayed green (`Tests: 16, Assertions: 51, Failures: 3`) |
| M17b | `strip_js_comments()` — `return ( 'block_comment' === $state ) ? null : $out;` → `return $out;` (the unterminated-block guard gone, escape-tracking intact) | **2 failures**: `IconConceptScannerTest::test_an_unterminated_block_comment_fails_loudly_instead_of_reporting_clean` and `IconConceptScannerCliTest::test_an_unmeasured_file_exits_2_with_MEASURE_FAILED` — the other 14 stayed green (`Tests: 16, Assertions: 57, Failures: 2`) |

**Meaning:** the two failure sets are disjoint, so neither half of the fix rides on the other's tests.
M17a pins the part that closes the two shapes that were actually measured; M17b pins the part that keeps
the *next* unparsed shape from being silent. Note what M17b's failures do **not** include: with the guard
removed, the gate on an unterminated-block file reports `1 raw` and exits 1 — red, but for the wrong
reason and from a file it only half read. Its CLI test deliberately passes `--expect-raw=0`, the count an
unread file produces, so the mutation is caught as a *silent pass* (exit 0) rather than as an accidental
red.

### Running M17 again

```bash
cd C:/projects/mhm-ui-core
./vendor/bin/phpunit -c phpunit.xml --filter IconConceptScanner   # baseline: OK (16 tests, 63 assertions)

# M17a -- delete the 8-line escape block from strip_js_comments()'s 'normal' branch
grep -n "An escape in CODE state" src/Kit/IconConceptScanner.php   # comment sits inside the block
# remove the enclosing `if ( ( '\' === $char ) && ( '' !== $next ) ) { ... }` by hand
./vendor/bin/phpunit -c phpunit.xml --filter IconConceptScanner   # 3 failures
git checkout -- src/Kit/IconConceptScanner.php

# M17b
sed -i "s|return ( 'block_comment' === \$state ) ? null : \$out;|return \$out;|" src/Kit/IconConceptScanner.php
./vendor/bin/phpunit -c phpunit.xml --filter IconConceptScanner   # 2 failures
git checkout -- src/Kit/IconConceptScanner.php

./vendor/bin/phpunit -c phpunit.xml --filter IconConceptScanner   # back to OK (16 tests, 63 assertions)
```

Mutate only on a committed tree, then `git checkout -- <file>` — same rule as M0-M16 above. M17b is a
safe one-line `sed -i`; M17a is a multi-line block and was removed by hand (`sed -i '362,369d'` against
commit `e6d8b6f`'s exact line numbers), then reverted with `git checkout`.

## 2026-09-20 — icon-concept gate, fix round 5 (Task 4): a comment only starts where a comment is written

Fix round 4 closed the two ESCAPED regex shapes and reported, honestly, the one it could not close.
Measured on commit `dc5262a`, against a single anchored `.jsx` file:

```
import { StatCard } from 'ui-core/src-react/components/Stat';
const re = /[/*]/;
export const A = () => <StatCard icon={ 'money-alt' } />;
/* an ordinary, properly closed comment */
export const B = () => <StatCard icon={ 'revenue' } />;
```

```
php bin/check-icon-concepts.php <that file>
icon-concepts: 1 file(s), 1 concept call site(s), 0 raw, 0 unknown
exit=0
```

`/[/*]/` carries no escape for fix round 4's escape rule to catch, so its `/*` opened a block comment —
and, worse than the escaped shapes, the ordinary well-formed `/* */` on the next line CLOSED it again, so
the unclosed-block guard never fired either. A real `money-alt` violation, swallowed in silence between
two innocent lines, exit 0.

Commit `0d57c69` narrows WHERE a comment may start instead of writing a JS lexer. `opens_a_comment()`:
a `/` opens a comment only at the start of a line, after whitespace, or directly after `;`, `{` or `}`.
A `/` glued to `[`, `(`, `=`, `,`, a letter or a digit is read as code. Same file, after:

```
RAW      <that file>:3  'money-alt' -- write 'revenue'
icon-concepts: 1 file(s), 1 concept call site(s), 1 raw, 0 unknown
exit=1
```

**Baseline (commit `0d57c69`):** `./vendor/bin/phpunit -c phpunit.xml --filter IconConceptScanner` →
`OK (19 tests, 74 assertions)`. `composer check:icon-concepts` → `exit=0`,
`icon-concepts: 5 file(s), 2 concept call site(s), 2 raw, 1 unknown` (unchanged; the three new cases use
throwaway files the tests write and `unlink`, as in M17).

| # | Mutation | Measured result |
| --- | --- | --- |
| M18 | `strip_js_comments()` — `&& $this->opens_a_comment( $source, $i )` dropped from the comment-start condition, so a `/` opens a comment anywhere again (escape rule and unclosed-block guard both intact) | **2 failures**: `test_an_unescaped_slash_star_in_a_regex_does_not_swallow_what_follows` (the `money-alt` between the regex and the real comment disappears again — `raw` 1→0) and `test_an_unrecognised_comment_position_is_SCANNED_not_swallowed` (the glued `f(/* ... */1)` form is stripped again instead of scanned) — the other 17 stayed green (`Tests: 19, Assertions: 70, Failures: 2`) |

**Meaning, and what M18 deliberately does NOT break:** the positive control
`test_POSITIVE_CONTROL_a_comment_in_every_position_it_is_written_stays_unscanned` stays **green** under
M18, and that is correct rather than a gap — removing the narrowing only *widens* what counts as a
comment, so every decoy in an allowed position is still stripped. That test is not there to catch M18; it
is there to catch the opposite mistake, a narrowing written too tightly, which would lose fix round 3's
win. The two mutations that would turn it red are M16a (stripping removed entirely) and any future
over-tightening of `opens_a_comment()`.

Note also which direction M18's first failure runs: without the narrowing the gate is **green on a file
with a real violation in it**. That is the silent false negative this round exists to remove, and it is
why the fix errs the other way — an unrecognised shape is scanned, so the worst case is noise. The second
failure pins the price of that choice as a measurement rather than a surprise.

### Running M18 again

```bash
cd C:/projects/mhm-ui-core
./vendor/bin/phpunit -c phpunit.xml --filter IconConceptScanner   # baseline: OK (19 tests, 74 assertions)

sed -i 's| \&\& \$this->opens_a_comment( \$source, \$i ) ) {| ) {|' src/Kit/IconConceptScanner.php
./vendor/bin/phpunit -c phpunit.xml --filter IconConceptScanner   # 2 failures
git checkout -- src/Kit/IconConceptScanner.php

./vendor/bin/phpunit -c phpunit.xml --filter IconConceptScanner   # back to OK (19 tests, 74 assertions)
```

Mutate only on a committed tree, then `git checkout -- <file>` — same rule as M0-M17 above.

# What breaks these tests

A green test proves nothing until you know what turns it red. Each mutation
below was applied to a committed tree, run, and reverted. The results are what
was measured on 2026-09-03, not what was predicted.

| Mutation | Edit | Result |
|---|---|---|
| M0 | `register.php` — remove the `add_action( 'plugins_loaded', 'mhmuicore_boot', 0 )` call | **2 failures**: the priority test and the arbitration test |
| M1 | `register.php` — priority `0` → `20` | **1 failure**: the priority test |
| M2 | `register.php` — `version_compare( ..., '>' )` → `'<'` | **1 failure**: the arbitration test |
| M3 | `tests/bootstrap-wp.php` — neutralise the `tests_add_filter` registration | **2 failures**: the priority test and the arbitration test |

## Why M0 exists

`has_action()` returns an int priority when the callback is registered and
`false` when it is not, and priority zero is falsy. Written as
`assertEquals( 0, has_action( ... ) )` the priority test passes against a
missing registration — measured directly with this package's own PHPUnit:
`assertEquals( 0, false )` passes, `assertSame( 0, false )` fails.

M1 does not cover that hole. Changing the priority to 20 turns the test red
whichever assertion is used, so a suite with M1 alone would report a healthy
gate while the assertion that matters was inert. M0 is the mutation that pins
`assertSame`.

## M0 was stronger than expected

The plan predicted M0 would fail the priority test only. It fails the
arbitration test too, and the reason is worth keeping: with no registration
there is no boot, so no fixture bootstrap runs and the arbitration assertion
sees an empty registry. The two failures have one cause.

## Vacuous-green declaration

`test_wordpress_dispatched_plugins_loaded_exactly_once` stayed green under all
four mutations. It would stay green with none of this package's code loaded.
That is deliberate and it is not a claim about the loader: an empty registry
and a hook that never fired produce the same empty global, and this assertion
is what separates them. Without it the arbitration test could be satisfied for
a reason that has nothing to do with the code under test.

The other two tests are green under no mutation.

## Running them again

```bash
docker compose -f docker/test/docker-compose.yml run --rm php composer test:wp
```

Mutate, run, then `git checkout -- <file>`. Mutations are only meaningful on a
committed tree: a dirty tree cannot tell you which edit produced the red.

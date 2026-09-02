<?php
/**
 * Losing fixture: the lower of the two registered versions.
 *
 * It records that it ran and asserts nothing. The assertion lives in the test,
 * because what is under test is which of these files WordPress caused to load.
 *
 * @package MHMUiCore
 */

declare(strict_types=1);

$GLOBALS['mhmuicore_test_booted'][] = '0.0.1';

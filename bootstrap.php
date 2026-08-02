<?php
/**
 * Bootstrap for the winning copy of ui-core.
 *
 * Loaded exactly once, by mhmuicore_boot(), from the highest registered version.
 *
 * @package MHMUiCore
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'MHMUICORE_VERSION' ) ) {
	return;
}

define( 'MHMUICORE_VERSION', '0.2.0' );
define( 'MHMUICORE_DIR', __DIR__ );

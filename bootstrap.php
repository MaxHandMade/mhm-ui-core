<?php
/**
 * Bootstrap for the winning copy of ui-core.
 *
 * Loaded exactly once, by mhm_ui_core_boot(), from the highest registered version.
 *
 * @package MHM\UiCore
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'MHM_UI_CORE_VERSION' ) ) {
	return;
}

define( 'MHM_UI_CORE_VERSION', '0.1.0' );
define( 'MHM_UI_CORE_DIR', __DIR__ );

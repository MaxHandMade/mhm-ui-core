<?php
/**
 * The pilot's PRO ADD-ON.
 *
 * Fills the slot the core declared and grants a capability. It also carries,
 * on purpose, a licence check that talks to a server: exactly what the free
 * core must NOT contain. PilotSeamTest runs PurityScanner over both trees and
 * expects this one to trip -- the negative control that proves the gate can
 * see.
 *
 * @package MHMUiCore
 */

declare(strict_types=1);

$GLOBALS['pilot_seam']->fill(
	'hero_after',
	static function ( string $html, array $settings ): string {
		return $html . '<div class="pilot-pro-upsell" data-layout="' . esc_attr( (string) $settings['layout'] ) . '">More from Pro</div>';
	}
);

$GLOBALS['pilot_caps']->grant( 'pro_badge' );

/**
 * A licence check. Never called by the tests; it exists to be FOUND.
 *
 * @param string $key Licence key.
 * @return bool
 */
function pilot_pro_activate_license( string $key ): bool {
	$response = wp_remote_get( 'https://licenses.example.test/?key=' . rawurlencode( $key ) );
	return ! is_wp_error( $response );
}

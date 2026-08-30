<?php
/**
 * The canonical error-code suffix inventory.
 *
 * @package MHMUiCore\Layout
 */

declare(strict_types=1);

namespace MHMUiCore\Layout;

/**
 * The canonical suffix inventory.
 *
 * Before the move all eleven codes were decorative: the consumer converted every
 * WP_Error into an Exception carrying only the message, so the code never
 * reached a caller. Making the code the only currency means it must be pinned --
 * a port that renames or drops four of them would otherwise pass every gate.
 *
 * Seven are raised by BlueprintValidator; the other four are raised by
 * CompositionBuilder. This inventory shipped complete before CompositionBuilder
 * existed, because a suffix list that grows piecemeal is not a canonical one --
 * see G1 (bin/check-error-prefix.php) for how coverage is asserted rather than
 * assumed.
 */
final class ErrorCodes {

	public const UNSUPPORTED_VERSION = 'unsupported_version';
	public const INVALID_BLUEPRINT   = 'invalid_blueprint';
	public const INVALID_COMPONENTS  = 'invalid_components';
	public const INVALID_PAGE        = 'invalid_page';
	public const INVALID_INSTANCE    = 'invalid_instance';
	public const NO_PAGES            = 'no_pages';
	public const UNKNOWN_COMPONENT   = 'unknown_component';
	public const MISSING_ADAPTER     = 'missing_adapter';
	public const FORBIDDEN_PATTERN   = 'forbidden_pattern';
	public const TAILWIND_LEAKAGE    = 'tailwind_leakage';
	public const UTILITY_LEAKAGE     = 'utility_leakage';

	/**
	 * Every canonical suffix, in one place.
	 *
	 * @var list<string>
	 */
	public const ALL = array(
		self::UNSUPPORTED_VERSION,
		self::INVALID_BLUEPRINT,
		self::INVALID_COMPONENTS,
		self::INVALID_PAGE,
		self::INVALID_INSTANCE,
		self::NO_PAGES,
		self::UNKNOWN_COMPONENT,
		self::MISSING_ADAPTER,
		self::FORBIDDEN_PATTERN,
		self::TAILWIND_LEAKAGE,
		self::UTILITY_LEAKAGE,
	);
}

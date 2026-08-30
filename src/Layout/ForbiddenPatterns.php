<?php
/**
 * House rules, not consumer configuration.
 *
 * @package MHMUiCore\Layout
 */

declare(strict_types=1);

namespace MHMUiCore\Layout;

/**
 * House rules, not consumer configuration.
 *
 * A second plugin's answer for these would be identical, so injecting them
 * would make every consumer re-declare the same list. What IS consumer-specific
 * is the class prefix that may legitimately precede a utility-shaped fragment --
 * that comes from the contract (see CompositionBuilder::scan_for_prohibited_patterns()).
 */
final class ForbiddenPatterns {

	/**
	 * Framework leakage, matched case-insensitively.
	 *
	 * "tailwind" already matches every string that contains a Tailwind CDN URL
	 * (stripos() is a substring match). A separate "cdn.tailwindcss.com" entry
	 * added nothing to the ban and cost the consuming plugin its only Plugin
	 * Check ERROR: the scanner reads a literal CDN host in source as offloaded
	 * content and cannot tell a blocklist from a script tag. The behaviour is
	 * pinned by GovernanceGateTest's CDN cases, not by this list -- do not
	 * reintroduce the literal host.
	 *
	 * @var list<string>
	 */
	public const FRAMEWORK = array( 'tw-', 'tailwind', 'antialiased', 'flex-1' );

	/** Utility-shaped class fragments. @var list<string> */
	public const UTILITY_FRAGMENTS = array( 'bg-', 'p-', 'm-', 'flex-', 'grid-', 'w-' );
}

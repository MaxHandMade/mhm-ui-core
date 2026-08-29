<?php

declare(strict_types=1);

namespace MHMUiCore\Tests\Fixtures;

use MHMUiCore\Layout\LayoutComponentAdapter;

/**
 * The package's tests must NOT use a consumer's adapters: testing Rentiva's
 * assumptions against Rentiva is what Phase 2 exists to stop.
 */
final class FixtureAdapter implements LayoutComponentAdapter {

	/** @var string */
	private $markup;

	public function __construct( string $markup = '<p>fixture</p>' ) {
		$this->markup = $markup;
	}

	public function render( array $attributes, string $instance_id ): string {
		unset( $attributes );

		return str_replace( '{id}', $instance_id, $this->markup );
	}
}

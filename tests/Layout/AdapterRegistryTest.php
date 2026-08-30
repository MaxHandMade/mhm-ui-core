<?php

declare(strict_types=1);

namespace MHMUiCore\Tests\Layout;

use MHMUiCore\Layout\AdapterRegistry;
use MHMUiCore\Layout\LayoutContract;
use MHMUiCore\Tests\Fixtures\FixtureAdapter;
use PHPUnit\Framework\TestCase;

/**
 * The old registry was static, so its table survived between tests and a
 * mutation once stayed alive because of it. Instance state cannot leak.
 */
final class AdapterRegistryTest extends TestCase {

	private function contract( array $adapters ): LayoutContract {
		return new LayoutContract(
			array(
				'error_prefix'  => 'zzz',
				'markup_prefix' => 'fixture',
				'adapters'      => $adapters,
			)
		);
	}

	public function test_resolves_a_registered_type(): void {
		$registry = new AdapterRegistry( $this->contract( array( 'hero' => new FixtureAdapter() ) ) );

		$this->assertTrue( $registry->has( 'hero' ) );
		$this->assertInstanceOf( FixtureAdapter::class, $registry->get_adapter( 'hero' ) );
	}

	public function test_two_registries_do_not_share_state(): void {
		$a = new AdapterRegistry( $this->contract( array( 'hero' => new FixtureAdapter() ) ) );
		$b = new AdapterRegistry( $this->contract( array( 'other' => new FixtureAdapter() ) ) );

		$this->assertFalse( $a->has( 'other' ) );
		$this->assertFalse( $b->has( 'hero' ) );
	}
}

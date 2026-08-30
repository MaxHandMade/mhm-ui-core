<?php

declare(strict_types=1);

namespace MHMUiCore\Tests\Layout;

use InvalidArgumentException;
use MHMUiCore\Layout\LayoutContract;
use MHMUiCore\Tests\Fixtures\FixtureAdapter;
use PHPUnit\Framework\TestCase;

final class LayoutContractTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		require_once __DIR__ . '/../Fixtures/wp-function-stubs.php';
	}

	/** @return array<string,mixed> */
	private function valid_config(): array {
		return array(
			'error_prefix'  => 'zzz',
			'markup_prefix' => 'fixture',
			'adapters'      => array( 'hero' => new FixtureAdapter() ),
		);
	}

	public function test_error_code_prefixes_the_suffix(): void {
		$contract = new LayoutContract( $this->valid_config() );

		$this->assertSame( 'zzz_unknown_component', $contract->error_code( 'unknown_component' ) );
	}

	public function test_adapter_lookup_returns_null_for_unregistered_type(): void {
		$contract = new LayoutContract( $this->valid_config() );

		$this->assertInstanceOf( FixtureAdapter::class, $contract->adapter( 'hero' ) );
		$this->assertNull( $contract->adapter( 'nope' ) );
	}

	/** @dataProvider broken_configs */
	public function test_a_broken_contract_is_a_programmer_error( array $config, string $needle ): void {
		// A malformed contract is NOT a domain error: it can never be recovered
		// from at runtime, so it throws rather than returning WP_Error.
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/' . preg_quote( $needle, '/' ) . '/' );

		new LayoutContract( $config );
	}

	/** @return array<string,array{0:array<string,mixed>,1:string}> */
	public function broken_configs(): array {
		$adapters = array( 'hero' => new FixtureAdapter() );

		return array(
			'missing error_prefix'  => array( array( 'markup_prefix' => 'fixture', 'adapters' => $adapters ), 'error_prefix' ),
			'empty error_prefix'    => array( array( 'error_prefix' => '', 'markup_prefix' => 'fixture', 'adapters' => $adapters ), 'error_prefix' ),
			'uppercase prefix'      => array( array( 'error_prefix' => 'Zzz', 'markup_prefix' => 'fixture', 'adapters' => $adapters ), 'error_prefix' ),
			'missing markup_prefix' => array( array( 'error_prefix' => 'zzz', 'adapters' => $adapters ), 'markup_prefix' ),
			'no adapters'           => array( array( 'error_prefix' => 'zzz', 'markup_prefix' => 'fixture', 'adapters' => array() ), 'adapters' ),
			'wrong adapter type'    => array( array( 'error_prefix' => 'zzz', 'markup_prefix' => 'fixture', 'adapters' => array( 'hero' => 'nope' ) ), 'LayoutComponentAdapter' ),
			'markup_prefix collides with a utility fragment' => array(
				array( 'error_prefix' => 'zzz', 'markup_prefix' => 'bg', 'adapters' => $adapters ),
				'markup_prefix',
			),
		);
	}
}

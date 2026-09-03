<?php
declare( strict_types = 1 );

namespace MHMUiCore\Tests\Component;

use InvalidArgumentException;
use MHMUiCore\Component\ComponentContract;
use PHPUnit\Framework\TestCase;

final class ComponentContractTest extends TestCase {

	public static function setUpBeforeClass(): void {
		require_once __DIR__ . '/../Fixtures/wp-function-stubs.php';
	}

	private function hero(): ComponentContract {
		return new ComponentContract( require __DIR__ . '/../Fixtures/hero-contract.php' );
	}

	public function test_defaults_come_from_the_declaration(): void {
		self::assertSame(
			array(
				'title'      => '',
				'showButton' => true,
				'columns'    => 3,
				'layout'     => 'grid',
			),
			$this->hero()->defaults()
		);
	}

	public function test_sanitize_is_an_allowlist_that_types_every_value(): void {
		$out = $this->hero()->sanitize(
			array(
				'title'      => '<b>Hi</b> there',
				'showButton' => '0',
				'columns'    => '4',
				'layout'     => 'slider',
				'evil'       => 'dropped',
			)
		);

		self::assertSame(
			array(
				'title'      => 'Hi there',
				'showButton' => false,
				'columns'    => 4,
				'layout'     => 'slider',
			),
			$out
		);
		self::assertArrayNotHasKey( 'evil', $out );
	}

	/** @dataProvider boolean_spellings */
	public function test_every_surface_spelling_of_a_boolean_is_understood( $raw, bool $expected ): void {
		self::assertSame( $expected, $this->hero()->sanitize( array( 'showButton' => $raw ) )['showButton'] );
	}

	public static function boolean_spellings(): array {
		return array(
			'shortcode 1'   => array( '1', true ),
			'shortcode 0'   => array( '0', false ),
			'block true'    => array( true, true ),
			'block false'   => array( false, false ),
			'elementor yes' => array( 'yes', true ),
			'elementor no'  => array( 'no', false ),
			'elementor ""'  => array( '', false ),
			'word true'     => array( 'TRUE', true ),
		);
	}

	public function test_an_enum_value_outside_the_list_falls_back_to_the_default(): void {
		self::assertSame( 'grid', $this->hero()->sanitize( array( 'layout' => 'carousel' ) )['layout'] );
	}

	public function test_kebab_and_slug(): void {
		$c = new ComponentContract( array( 'slug' => 'featured_vehicles', 'title' => 'X' ) );
		self::assertSame( 'featured_vehicles', $c->slug() );
		self::assertSame( 'featured-vehicles', $c->kebab() );
	}

	public function test_a_bad_slug_throws(): void {
		$this->expectException( InvalidArgumentException::class );
		new ComponentContract( array( 'slug' => 'Bad-Slug', 'title' => 'X' ) );
	}

	public function test_an_unknown_setting_type_throws(): void {
		$this->expectException( InvalidArgumentException::class );
		new ComponentContract( array( 'slug' => 'x', 'title' => 'X', 'settings' => array( 'a' => array( 'type' => 'float' ) ) ) );
	}

	public function test_an_enum_default_outside_its_options_throws(): void {
		$this->expectException( InvalidArgumentException::class );
		new ComponentContract(
			array(
				'slug'     => 'x',
				'title'    => 'X',
				'settings' => array( 'a' => array( 'type' => 'enum', 'enum' => array( 'p', 'q' ), 'default' => 'z' ) ),
			)
		);
	}

	public function test_an_enum_without_options_throws(): void {
		$this->expectException( InvalidArgumentException::class );
		new ComponentContract( array( 'slug' => 'x', 'title' => 'X', 'settings' => array( 'a' => array( 'type' => 'enum' ) ) ) );
	}
}

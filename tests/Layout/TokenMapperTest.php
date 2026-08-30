<?php

declare(strict_types=1);

namespace MHMUiCore\Tests\Layout;

use MHMUiCore\Layout\TokenMapper;
use PHPUnit\Framework\TestCase;

final class TokenMapperTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		require_once __DIR__ . '/../Fixtures/wp-function-stubs.php';
	}

	public function test_targets_live_in_the_package_namespace(): void {
		// bin/check-php-namespace.php rejects any "--" literal that is not
		// "--mhmui-". The old "--mhm-bp-*" targets would turn the package's own
		// gate red the moment this class landed here.
		foreach ( TokenMapper::TOKEN_MAPPING as $target ) {
			$this->assertStringStartsWith( '--mhmui-bp-', $target );
		}
	}

	public function test_nine_mappings_survive_the_port(): void {
		$this->assertCount( 9, TokenMapper::TOKEN_MAPPING );
	}

	public function test_maps_a_known_token_to_an_inline_declaration(): void {
		// The real code builds each declaration with
		// sprintf( '%s: %s;', $target_var, $sanitized_value ) -- a space after
		// the colon, not "--mhmui-bp-primary:#ff0000;".
		$mapper = new TokenMapper();

		$this->assertSame(
			'--mhmui-bp-primary: #ff0000;',
			$mapper->map_to_style_string( array( 'colors' => array( 'primary' => '#ff0000' ) ) )
		);
	}

	public function test_source_keys_are_dot_notated_against_nested_arrays(): void {
		// Measured on the pre-move code: source keys resolve via a private
		// resolve_token_value() that walks nested arrays by dot-notated path
		// ("colors.primary" -> $tokens['colors']['primary']). Source keys are
		// consumer-facing (stored manifests are keyed on them) and must not
		// change -- only the CSS variable targets are renamed.
		$mapper = new TokenMapper();

		$style = $mapper->map_to_style_string(
			array(
				'colors'  => array(
					'primary'    => '#111111',
					'secondary'  => '#222222',
					'text'       => '#333333',
					'background' => '#444444',
					'surface'    => '#555555',
					'accent'     => '#666666',
				),
				'spacing' => array( 'unit' => '8px' ),
				'radius'  => array( 'main' => '4px' ),
				'fonts'   => array( 'body' => 'Inter, sans-serif' ),
			)
		);

		$this->assertSame(
			'--mhmui-bp-primary: #111111; --mhmui-bp-secondary: #222222; --mhmui-bp-text-primary: #333333;'
			. ' --mhmui-bp-bg-main: #444444; --mhmui-bp-bg-soft: #555555; --mhmui-bp-accent: #666666;'
			. ' --mhmui-bp-spacing-base: 8px; --mhmui-bp-border-radius: 4px; --mhmui-bp-font-family: Inter, sans-serif;',
			$style
		);
	}

	public function test_an_absent_token_is_silently_skipped(): void {
		$mapper = new TokenMapper();

		$this->assertSame(
			'',
			$mapper->map_to_style_string( array() )
		);
	}

	/** @dataProvider injection_vectors */
	public function test_a_value_that_smuggles_a_second_declaration_is_dropped( string $value ): void {
		// Measured on the pre-move code: "^rgba?\(.*\)$" accepted
		// "rgba(0); background:url(...)" and esc_attr does not escape ";", "(" or
		// ")". The declaration reached the browser intact.
		$mapper = new TokenMapper();

		$this->assertSame( '', $mapper->map_to_style_string( array( 'colors' => array( 'primary' => $value ) ) ) );
	}

	/** @return array<string,array{0:string}> */
	public function injection_vectors(): array {
		return array(
			'second declaration' => array( 'rgba(0); background:url(//evil.example/x.png)' ),
			'url function'       => array( 'url(//evil.example/x.png)' ),
			'legacy expression'  => array( 'expression(alert(1))' ),
			'brace injection'    => array( '0} .a{color:red' ),
			'import injection'   => array( '0;@import url(//evil.example/x.css)' ),
			'whitespace before url paren' => array( 'url  (//evil.example/x.png)' ),
			// 'import injection' above trips the ";" alternative first, so the
			// "@import" branch of the pattern is never exercised in isolation.
			// This row has no ";", "{", "}" or "url(" at all -- only "@import"
			// itself can reject it.
			'@import isolated'   => array( '@import "evil.css"' ),
			// The pattern's "/i" flag is otherwise untested: nothing here proves
			// the url( check is case-insensitive.
			'uppercase url'      => array( 'URL(//evil.example/x.png)' ),
		);
	}

	public function test_legitimate_colour_functions_still_pass(): void {
		$mapper = new TokenMapper();

		$this->assertSame(
			'--mhmui-bp-primary: rgba(12, 34, 56, 0.5);',
			$mapper->map_to_style_string( array( 'colors' => array( 'primary' => 'rgba(12, 34, 56, 0.5)' ) ) )
		);
	}

	public function test_legitimate_hsl_function_still_passes(): void {
		$mapper = new TokenMapper();

		$this->assertSame(
			'--mhmui-bp-primary: hsl(210, 50%, 40%);',
			$mapper->map_to_style_string( array( 'colors' => array( 'primary' => 'hsl(210, 50%, 40%)' ) ) )
		);
	}

	public function test_tailwind_leakage_is_still_rejected(): void {
		$mapper = new TokenMapper();

		$this->assertSame(
			'',
			$mapper->map_to_style_string( array( 'colors' => array( 'primary' => 'tw-bg-red-500' ) ) )
		);
	}
}

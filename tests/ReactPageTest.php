<?php
/**
 * Locks mhmuicore_enqueue_react_page(), the shared React admin page loader.
 *
 * WHY THIS FUNCTION EXISTS HERE AT ALL
 *
 * The loader used to live in one plugin (mhm-rentiva's AssetManager) while a
 * second plugin called it across the plugin boundary for five of its own admin
 * screens -- passing that plugin's own path, URL and text domain as arguments,
 * which is the shape of shared infrastructure wearing a product's namespace.
 * Deactivating the first plugin took the second plugin's React screens with it.
 *
 * @package MHMUiCore
 */

declare(strict_types=1);

namespace MHMUiCore\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing Procedural bootstrap function; covered behaviourally.
 */
final class ReactPageTest extends TestCase {

	/**
	 * Handle prefix used throughout, mirroring a real consumer's spelling.
	 */
	private const PREFIX = 'mhm-rentiva-react-';

	/**
	 * Boot the package's bootstrap.php once for the whole class.
	 */
	public static function setUpBeforeClass(): void {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', sys_get_temp_dir() . '/' );
		}
		require_once __DIR__ . '/Fixtures/wp-function-stubs.php';
		require_once __DIR__ . '/../bootstrap.php';
	}

	/**
	 * Reset the recorded calls AND the once-per-request nonce guard.
	 *
	 * The guard is a global rather than a function static precisely so it can be
	 * reset here. A function static would make "added once" untestable: the
	 * first test to run would consume it and every later test would agree for
	 * the wrong reason.
	 */
	protected function setUp(): void {
		$GLOBALS['mhmuicore_test_wp_calls']      = array();
		$GLOBALS['mhmuicore_react_nonce_added'] = false;
	}

	/**
	 * Minimum viable argument set, pointing at the committed fixture plugin.
	 *
	 * @param array<string, mixed> $overrides Keys to replace or add.
	 * @return array<string, mixed>
	 */
	private function args( array $overrides = array() ): array {
		return array_merge(
			array(
				'page'          => 'dashboard',
				'base_dir'      => __DIR__ . '/Fixtures/plugin/',
				'base_url'      => 'https://example.test/wp-content/plugins/demo/',
				'handle_prefix' => self::PREFIX,
				'version'       => '9.9.9',
				'text_domain'   => 'demo-domain',
			),
			$overrides
		);
	}

	/**
	 * 🔴 THE LOCATION INVARIANT — and it is an INVENTORY lock, not a name list.
	 *
	 * bootstrap.php is selected by "highest version wins"; register.php is
	 * guarded by function_exists(), so the FIRST plugin to load wins there. A
	 * loader declared in register.php would therefore come from whichever copy
	 * happened to load first -- possibly an older one whose enqueue contract no
	 * longer matches the bundles being enqueued.
	 *
	 * Asserting only "the new function is in bootstrap.php" would go stale the
	 * moment someone adds a seventh global. So this derives the full set of
	 * functions bootstrap.php declares and asserts that register.php declares
	 * none of them -- a future addition is covered without anyone remembering
	 * to extend a provider.
	 */
	public function test_bootstrap_declarations_never_appear_in_register(): void {
		$bootstrap = self::declared_functions( __DIR__ . '/../bootstrap.php' );
		$register  = self::declared_functions( __DIR__ . '/../register.php' );

		$this->assertContains(
			'mhmuicore_enqueue_react_page',
			$bootstrap,
			'The React page loader must be declared in bootstrap.php (highest version wins).'
		);

		$this->assertSame(
			array(),
			array_values( array_intersect( $bootstrap, $register ) ),
			'No global declared in bootstrap.php may also be declared in register.php: '
			. 'register.php is first-loader-wins, so the older copy could supply it.'
		);
	}

	/**
	 * Names of functions really declared in $file.
	 *
	 * Tokenised rather than grepped so that a name inside a docblock, a comment
	 * or a string literal does not count as a declaration.
	 *
	 * @param string $file Absolute path to a PHP file.
	 * @return string[]
	 */
	private static function declared_functions( string $file ): array {
		$tokens = token_get_all( (string) file_get_contents( $file ) );
		$count  = count( $tokens );
		$names  = array();

		for ( $index = 0; $index < $count; $index++ ) {
			if ( ! is_array( $tokens[ $index ] ) || T_FUNCTION !== $tokens[ $index ][0] ) {
				continue;
			}
			$next = $index + 1;
			while ( $next < $count && is_array( $tokens[ $next ] ) && T_WHITESPACE === $tokens[ $next ][0] ) {
				$next++;
			}
			if ( $next < $count && is_array( $tokens[ $next ] ) && T_STRING === $tokens[ $next ][0] ) {
				$names[] = $tokens[ $next ][1];
			}
		}

		return $names;
	}

	/**
	 * The component stylesheet is not optional: WordPress ships its React
	 * components unstyled, so a page that skips this renders bare.
	 */
	public function test_component_stylesheet_is_enqueued(): void {
		mhmuicore_enqueue_react_page( $this->args() );

		$styles = mhmuicore_test_calls( 'wp_enqueue_style' );
		$this->assertCount( 1, $styles );
		$this->assertSame( 'wp-components', $styles[0]['handle'] );
	}

	/**
	 * Dependencies and version come from the generated manifest, not from the
	 * caller: @wordpress/scripts computes both, and hand-maintained copies rot.
	 */
	public function test_dependencies_and_version_come_from_the_generated_manifest(): void {
		mhmuicore_enqueue_react_page( $this->args() );

		$scripts = mhmuicore_test_calls( 'wp_enqueue_script' );
		$this->assertCount( 1, $scripts );
		$this->assertSame(
			array( 'wp-element', 'wp-api-fetch', 'wp-i18n' ),
			$scripts[0]['deps']
		);
		$this->assertSame( 'a1b2c3d4e5f6', $scripts[0]['ver'] );
		$this->assertNotSame( '9.9.9', $scripts[0]['ver'], 'The caller version must not win over the manifest.' );
	}

	/**
	 * Extra dependencies are appended AFTER the generated ones, never in front:
	 * the generated list is what the bundle actually imports.
	 */
	public function test_extra_dependencies_are_appended_after_the_generated_ones(): void {
		mhmuicore_enqueue_react_page( $this->args( array( 'deps' => array( 'chart-js' ) ) ) );

		$scripts = mhmuicore_test_calls( 'wp_enqueue_script' );
		$this->assertSame(
			array( 'wp-element', 'wp-api-fetch', 'wp-i18n', 'chart-js' ),
			$scripts[0]['deps']
		);
	}

	/**
	 * A missing manifest must not enqueue a versionless script: without a
	 * version WordPress appends its own core version and the browser serves a
	 * stale bundle after every deploy.
	 */
	public function test_missing_manifest_falls_back_to_the_supplied_version(): void {
		mhmuicore_enqueue_react_page( $this->args( array( 'page' => 'no-such-page' ) ) );

		$scripts = mhmuicore_test_calls( 'wp_enqueue_script' );
		$this->assertCount( 1, $scripts );
		$this->assertSame( array(), $scripts[0]['deps'] );
		$this->assertSame( '9.9.9', $scripts[0]['ver'] );
	}

	/**
	 * Handle and URL are built from the caller's own prefix and base URL — the
	 * whole point of the move is that no consumer's identity is baked in here.
	 */
	public function test_handle_and_url_are_built_from_the_callers_own_identity(): void {
		mhmuicore_enqueue_react_page( $this->args() );

		$scripts = mhmuicore_test_calls( 'wp_enqueue_script' );
		$this->assertSame( 'mhm-rentiva-react-dashboard', $scripts[0]['handle'] );
		$this->assertSame(
			'https://example.test/wp-content/plugins/demo/build/admin/dashboard.js',
			$scripts[0]['src']
		);
		$this->assertTrue( $scripts[0]['args'], 'The bundle must load in the footer.' );
	}

	/**
	 * Translations are looked up under the CALLER's domain and directory.
	 *
	 * This pairing is the defect that forced the text domain to become a
	 * parameter in the first place: a hardcoded domain made the second plugin's
	 * bundles ask WordPress for the first plugin's JSON inside the second
	 * plugin's languages/ directory — a lookup that can never succeed.
	 */
	public function test_translations_use_the_callers_domain_and_directory(): void {
		mhmuicore_enqueue_react_page( $this->args() );

		$calls = mhmuicore_test_calls( 'wp_set_script_translations' );
		$this->assertCount( 1, $calls );
		$this->assertSame( 'mhm-rentiva-react-dashboard', $calls[0]['handle'] );
		$this->assertSame( 'demo-domain', $calls[0]['domain'] );
		$this->assertSame( __DIR__ . '/Fixtures/plugin/languages/', $calls[0]['path'] );
	}

	/**
	 * A caller may hold its catalogues elsewhere.
	 */
	public function test_languages_directory_can_be_overridden(): void {
		mhmuicore_enqueue_react_page( $this->args( array( 'languages_dir' => '/custom/i18n/' ) ) );

		$calls = mhmuicore_test_calls( 'wp_set_script_translations' );
		$this->assertSame( '/custom/i18n/', $calls[0]['path'] );
	}

	/**
	 * The REST nonce middleware is installed once per request, however many
	 * pages enqueue. Installing it twice stacks two middlewares on api-fetch.
	 */
	public function test_nonce_middleware_is_installed_once_per_request(): void {
		mhmuicore_enqueue_react_page( $this->args() );
		mhmuicore_enqueue_react_page( $this->args( array( 'page' => 'no-such-page' ) ) );

		$inline = mhmuicore_test_calls( 'wp_add_inline_script' );
		$this->assertCount( 1, $inline, 'Two pages must still yield exactly one nonce middleware.' );
		$this->assertSame( 'wp-api-fetch', $inline[0]['handle'] );
		$this->assertSame( 'after', $inline[0]['position'] );

		// The esc_js stub marks its input, so this also proves the nonce was
		// routed through escaping rather than interpolated raw.
		$this->assertStringContainsString( 'esc_js(nonce-for-wp_rest)', $inline[0]['data'] );
		$this->assertStringContainsString( 'createNonceMiddleware', $inline[0]['data'] );

		// Both pages must still have been enqueued — the guard is on the nonce,
		// not on the function.
		$this->assertCount( 2, mhmuicore_test_calls( 'wp_enqueue_script' ) );
	}

	/**
	 * Every required key is required. A caller that forgets one is a
	 * programming error in our own plugins, and it must surface at the call
	 * site rather than enqueue a script from a half-built URL.
	 *
	 * @dataProvider required_key_provider
	 *
	 * @param string $key Key to remove.
	 */
	public function test_missing_required_key_is_rejected( string $key ): void {
		$args = $this->args();
		unset( $args[ $key ] );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( $key );

		mhmuicore_enqueue_react_page( $args );
	}

	/**
	 * An empty string is the shape an undefined plugin constant collapses to,
	 * so it must be rejected exactly like a missing key.
	 *
	 * @dataProvider required_key_provider
	 *
	 * @param string $key Key to blank out.
	 */
	public function test_empty_required_key_is_rejected( string $key ): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( $key );

		mhmuicore_enqueue_react_page( $this->args( array( $key => '' ) ) );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function required_key_provider(): array {
		return array(
			'page'          => array( 'page' ),
			'base_dir'      => array( 'base_dir' ),
			'base_url'      => array( 'base_url' ),
			'handle_prefix' => array( 'handle_prefix' ),
			'version'       => array( 'version' ),
			'text_domain'   => array( 'text_domain' ),
		);
	}

	/**
	 * The rejection message goes through escaping.
	 *
	 * An uncaught exception's message is printed, so WordPress counts a throw as
	 * an output site. This package ships inside consuming plugins and their gates
	 * lint vendor/ with wider rulesets than this package once ran: the v0.4.0
	 * loader passed here and turned a consumer's security gate red. The stub
	 * marks its input, so this fails if the esc_html() call is ever dropped --
	 * asserting the message text alone could not tell the difference.
	 */
	public function test_rejection_message_is_escaped(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'esc_html(' );

		mhmuicore_enqueue_react_page( $this->args( array( 'page' => '' ) ) );
	}

	/**
	 * A rejected call must not have enqueued anything on its way out.
	 */
	public function test_rejected_call_enqueues_nothing(): void {
		try {
			mhmuicore_enqueue_react_page( $this->args( array( 'base_url' => '' ) ) );
			$this->fail( 'Expected InvalidArgumentException.' );
		} catch ( InvalidArgumentException $e ) {
			unset( $e );
		}

		$this->assertSame( array(), mhmuicore_test_calls( 'wp_enqueue_script' ) );
		$this->assertSame( array(), mhmuicore_test_calls( 'wp_enqueue_style' ) );
		$this->assertSame( array(), mhmuicore_test_calls( 'wp_add_inline_script' ) );
	}
}

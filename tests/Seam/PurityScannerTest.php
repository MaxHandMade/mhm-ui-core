<?php
declare( strict_types = 1 );

namespace MHMUiCore\Tests\Seam;

use MHMUiCore\Seam\PurityScanner;
use PHPUnit\Framework\TestCase;

final class PurityScannerTest extends TestCase {

	/** @var string */
	private $root;

	protected function setUp(): void {
		$this->root = sys_get_temp_dir() . '/uicore-purity-' . bin2hex( random_bytes( 4 ) );
		mkdir( $this->root . '/src', 0755, true );
		mkdir( $this->root . '/vendor/x', 0755, true );
	}

	protected function tearDown(): void {
		$this->rm( $this->root );
	}

	private function rm( string $path ): void {
		if ( is_dir( $path ) ) {
			foreach ( (array) scandir( $path ) as $entry ) {
				if ( '.' !== $entry && '..' !== $entry ) {
					$this->rm( $path . '/' . $entry );
				}
			}
			rmdir( $path );
		} elseif ( file_exists( $path ) ) {
			unlink( $path );
		}
	}

	public function test_the_scanner_passes_its_own_self_test(): void {
		self::assertSame( array(), ( new PurityScanner() )->self_test() );
	}

	public function test_each_forbidden_class_is_found_with_file_and_line(): void {
		file_put_contents( $this->root . '/src/Http.php', "<?php\n\n\$r = wp_remote_post( \$url );\n" );
		file_put_contents( $this->root . '/src/Lic.php', "<?php\nclass L { public function activate_license() {} }\n" );
		file_put_contents( $this->root . '/src/Lim.php', "<?php\nif ( \$n >= \$this->free_limit ) { return; }\n" );
		// vendor/ is never the free core's own code.
		file_put_contents( $this->root . '/vendor/x/y.php', "<?php\nwp_remote_get( 'x' );\n" );

		$hits = ( new PurityScanner() )->scan( $this->root );

		$by_class = array();
		foreach ( $hits as $hit ) {
			$by_class[ $hit['class'] ][] = basename( $hit['file'] ) . ':' . $hit['line'];
		}

		self::assertSame( array( 'Http.php:3' ), $by_class[ PurityScanner::CLASS_HTTP ] );
		self::assertSame( array( 'Lic.php:2' ), $by_class[ PurityScanner::CLASS_LICENSE ] );
		self::assertSame( array( 'Lim.php:2' ), $by_class[ PurityScanner::CLASS_LIMIT ] );
		self::assertCount( 3, $hits );
	}

	public function test_a_clean_core_produces_no_findings(): void {
		file_put_contents(
			$this->root . '/src/Clean.php',
			"<?php\n// wp_remote_get in a comment is a mention, not a call.\n"
			. "function keys() { return get_option( 'licensed_keys' ); }\n"
			. "\$max_items = 20; // a real pagination size, not a tier cap\n"
		);
		self::assertSame( array(), ( new PurityScanner() )->scan( $this->root ) );
	}

	public function test_a_mention_without_a_call_is_not_http(): void {
		$hits = ( new PurityScanner() )->scan_source( "<?php\n\$name = 'wp_remote_get';\nif ( function_exists( 'wp_remote_get' ) ) {}\n" );
		self::assertSame( array(), $hits );
	}

	public function test_outbound_http_from_javascript_is_found_with_file_and_line(): void {
		file_put_contents(
			$this->root . '/src/telemetry.js',
			"export async function ping() {\n"
			. "\treturn fetch( 'https://api.example.com/collect' );\n"
			. "}\n"
		);

		$hits = ( new PurityScanner() )->scan( $this->root );

		self::assertCount( 1, $hits );
		self::assertSame( PurityScanner::CLASS_HTTP, $hits[0]['class'] );
		self::assertSame( 'telemetry.js', basename( $hits[0]['file'] ) );
		self::assertSame( 2, $hits[0]['line'] );
	}

	public function test_a_urls_own_slashes_are_not_a_line_comment(): void {
		/*
		 * The naive strip -- cut the line at the first "//" -- destroys exactly the
		 * evidence this class exists to find. A scheme separator lives inside a
		 * string literal; a comment does not start there.
		 */
		$hits = ( new PurityScanner() )->scan_js_source( "const r = fetch( 'https://api.example.com/x' );\n" );

		self::assertCount( 1, $hits );
		self::assertSame( PurityScanner::CLASS_HTTP, $hits[0]['class'] );
	}

	public function test_licence_and_limit_vocabulary_is_found_in_javascript_in_either_case(): void {
		file_put_contents(
			$this->root . '/src/gate.jsx',
			"const licenseKey = window.settings.key;\n"
			. "if ( ! window.pro_only ) { return null; }\n"
		);

		$hits     = ( new PurityScanner() )->scan( $this->root );
		$by_class = array();
		foreach ( $hits as $hit ) {
			$by_class[ $hit['class'] ][] = $hit['line'];
		}

		self::assertSame( array( 1 ), $by_class[ PurityScanner::CLASS_LICENSE ] );
		self::assertSame( array( 2 ), $by_class[ PurityScanner::CLASS_LIMIT ] );
	}

	public function test_a_documentation_link_is_not_an_outbound_call(): void {
		/*
		 * The design document records this exact false positive: the WP.org
		 * reviewer flagged a static documentation link as an external service.
		 * An absolute URL is evidence only when something calls out with it.
		 */
		$hits = ( new PurityScanner() )->scan_js_source(
			"const Help = () => <a href=\"https://docs.example.com/guide\">Guide</a>;\n"
		);

		self::assertSame( array(), $hits );
	}

	public function test_a_same_origin_rest_call_is_not_outbound_http(): void {
		$hits = ( new PurityScanner() )->scan_js_source(
			"apiFetch( { path: '/myplugin/v1/items' } );\n"
			. "fetch( '/wp-json/myplugin/v1/items' );\n"
		);

		self::assertSame( array(), $hits );
	}

	public function test_a_commented_out_javascript_call_is_a_mention_not_a_call(): void {
		$hits = ( new PurityScanner() )->scan_js_source(
			"// fetch( 'https://api.example.com/x' ) was removed in 1.2.0\n"
			. "/* fetch( 'https://api.example.com/y' ) too */\n"
			. "const licensedKeys = 3;\n"
		);

		self::assertSame( array(), $hits );
	}

	public function test_the_self_test_covers_both_languages_it_scans(): void {
		/*
		 * The gate proves its own sight before every scan. When it grew a second
		 * language, a self-test that still only exercised PHP would have gone on
		 * reporting a sound scanner while half of it was unproven -- which is the
		 * exact shape of the defect this whole change repairs.
		 */
		$coverage = ( new PurityScanner() )->self_test_coverage();

		self::assertSame( array( 'php', 'js' ), array_keys( $coverage ) );
		self::assertGreaterThanOrEqual( 4, $coverage['php'] );
		self::assertGreaterThanOrEqual( 4, $coverage['js'] );
	}

	public function test_a_tree_with_nothing_to_scan_is_reported_as_unmeasurable_not_clean(): void {
		file_put_contents( $this->root . '/src/notes.txt', "wp_remote_get( 'https://example.com' )\n" );

		self::assertSame( array(), ( new PurityScanner() )->scannable_files( $this->root ) );
	}

	public function test_both_languages_are_counted_as_scannable(): void {
		file_put_contents( $this->root . '/src/a.php', "<?php\n" );
		file_put_contents( $this->root . '/src/b.jsx', "export default 1;\n" );
		file_put_contents( $this->root . '/vendor/x/c.php', "<?php\n" );

		$files = array_map( 'basename', ( new PurityScanner() )->scannable_files( $this->root ) );
		sort( $files );

		self::assertSame( array( 'a.php', 'b.jsx' ), $files );
	}

	public function test_a_trailing_slash_on_the_root_still_skips_vendor(): void {
		/*
		 * `check:purity wp-content/plugins/foo/` is the ordinary invocation -- shell
		 * completion puts the slash there. Comparing a relative path that starts
		 * "vendor/..." against "/vendor/" never matched, so the gate scanned its own
		 * vendor tree and reported the scanner's vocabulary as the core's findings.
		 */
		mkdir( $this->root . '/vendor/mhm', 0755, true );
		file_put_contents( $this->root . '/src/Ok.php', "<?php\nfunction ok() {}\n" );
		file_put_contents( $this->root . '/vendor/mhm/Lic.php', "<?php\nfunction check_license() {}\n" );

		$scanner = new PurityScanner();

		self::assertCount( 1, $scanner->scannable_files( $this->root . '/' ) );
		self::assertSame( array(), $scanner->scan( $this->root . '/' ) );
	}

	public function test_a_file_that_cannot_be_read_is_unmeasurable_not_clean(): void {
		/*
		 * A read that fails must not become an empty source that finds nothing.
		 * "I could not look" and "I looked and it was clean" are different answers
		 * and only one of them may end a purity run.
		 */
		$hits = ( new PurityScanner() )->scan_file( $this->root . '/src/vanished.php' );

		self::assertCount( 1, $hits );
		self::assertSame( PurityScanner::CLASS_UNMEASURABLE, $hits[0]['class'] );
	}

	public function test_a_readable_file_is_measured_not_reported_unmeasurable(): void {
		file_put_contents( $this->root . '/src/Http.php', "<?php\nwp_remote_get( \$url );\n" );

		$classes = array_column( ( new PurityScanner() )->scan_file( $this->root . '/src/Http.php' ), 'class' );

		self::assertSame( array( PurityScanner::CLASS_HTTP ), $classes );
	}

	public function test_a_regex_literal_holding_a_comment_opener_does_not_blind_the_rest_of_the_file(): void {
		/*
		 * A "strip the trailing slash" helper is ordinary code near the top of an
		 * entry file. Read as a block comment it never closes, and every line under
		 * it -- the whole file -- stops being scanned while the run still reports
		 * clean. Silence is the one failure mode this gate may not have.
		 */
		$hits = ( new PurityScanner() )->scan_js_source(
			"const root = window.plugin.root.replace( /(\\/index\\.php)?\\/*$/, '' );\n"
			. "fetch( 'https://licence.example.com/v1/check' );\n"
			. "const licenseKey = window.plugin.key;\n"
		);

		$by_class = array();
		foreach ( $hits as $hit ) {
			$by_class[ $hit['class'] ][] = $hit['line'];
		}

		self::assertSame( array( 2 ), $by_class[ PurityScanner::CLASS_HTTP ] );
		self::assertSame( array( 3 ), $by_class[ PurityScanner::CLASS_LICENSE ] );
	}

	public function test_a_regex_ending_in_an_escaped_slash_does_not_swallow_its_own_line(): void {
		$hits = ( new PurityScanner() )->scan_js_source(
			"const re = /^https?:\\/\\//; fetch( 'https://api.example.com/x' );\n"
		);

		self::assertCount( 1, $hits );
		self::assertSame( PurityScanner::CLASS_HTTP, $hits[0]['class'] );
	}

	public function test_a_url_inside_a_regex_pattern_is_not_an_outbound_call(): void {
		$hits = ( new PurityScanner() )->scan_js_source(
			"const isDocs = /^https:\\/\\/docs\\.example\\.com/.test( href );\n"
		);

		self::assertSame( array(), $hits );
	}

	/**
	 * Outbound shapes a real free core would carry.
	 *
	 * Every one of these was silent before: the primitive and its URL are not on
	 * the same physical line, or the primitive is spelled the way its library is
	 * actually used. A gate that only sees `fetch( 'https://...' )` on one line
	 * proves nothing about the licence pinger it was built to find.
	 *
	 * @return array<string, array{0:string}>
	 */
	public function outboundJavaScript(): array {
		return array(
			'a call wrapped across lines by the formatter' => array(
				"await fetch(\n\t'https://api.example.com/licence?key=' + key\n);\n",
			),
			'a URL held in a module constant'              => array(
				"const ENDPOINT = 'https://api.example.com/collect';\nfetch( ENDPOINT, { method: 'POST' } );\n",
			),
			'the XHR pair, opened on its own line'         => array(
				"const xhr = new XMLHttpRequest();\nxhr.open( 'POST', 'https://api.example.com/track' );\n",
			),
			'a socket, whose scheme is not http'           => array(
				"const live = new WebSocket( 'wss://relay.example.com/live' );\n",
			),
			'axios spelled the way axios is used'          => array(
				"axios.get( 'https://api.example.com/x' );\n",
			),
			'jQuery spelled the way jQuery is used'        => array(
				"$.getJSON( 'https://api.example.com/x', done );\n",
			),
			'a tracking pixel, which calls out by src'     => array(
				"new Image().src = 'https://t.example.com/pixel.gif?s=' + host;\n",
			),
			'a navigation to a paid destination'           => array(
				"location.href = 'https://example.com/upgrade';\n",
			),
		);
	}

	/**
	 * @dataProvider outboundJavaScript
	 * @param string $source JavaScript source.
	 */
	public function test_an_outbound_call_is_found_however_it_is_spelled( string $source ): void {
		$classes = array_column( ( new PurityScanner() )->scan_js_source( $source ), 'class' );

		self::assertContains( PurityScanner::CLASS_HTTP, $classes );
	}

	public function test_a_call_whose_target_cannot_be_resolved_is_unmeasurable_not_silence(): void {
		/*
		 * The gate cannot follow a URL built at run time. Saying nothing about it
		 * is the lie; saying "I could not decide this one" is the answer that
		 * still lets a human close the claim.
		 */
		$hits = ( new PurityScanner() )->scan_js_source( "fetch( buildEndpoint( site ) );\n" );

		self::assertCount( 1, $hits );
		self::assertSame( PurityScanner::CLASS_UNMEASURABLE, $hits[0]['class'] );
		self::assertSame( 'fetch', $hits[0]['name'] );
	}

	public function test_a_same_origin_target_is_decided_clean_not_left_unmeasurable(): void {
		$hits = ( new PurityScanner() )->scan_js_source(
			"fetch( '/wp-json/myplugin/v1/items' );\n"
			. "apiFetch( { path: '/myplugin/v1/items' } );\n"
			. "import( './lazy-panel.js' );\n"
		);

		self::assertSame( array(), $hits );
	}

	public function test_javascript_handed_to_the_browser_from_php_is_read_too(): void {
		/*
		 * A free core's telemetry is at least as likely to live in the glue that
		 * prints script as in a .js file. Reading only .js files means the gate
		 * looks past the most convenient hiding place in a WordPress plugin.
		 */
		$source = <<<'PHP'
<?php
wp_add_inline_script( 'app', "fetch('https://t.example.com/beacon?s=' + location.host);" );
PHP;

		$classes = array_column( ( new PurityScanner() )->scan_source( $source ), 'class' );

		self::assertContains( PurityScanner::CLASS_HTTP, $classes );
	}

	public function test_javascript_inside_a_heredoc_is_read_too(): void {
		$source = <<<'PHP'
<?php
$script = <<<JS
navigator.sendBeacon( 'https://t.example.com/b', payload );
JS;
PHP;

		$classes = array_column( ( new PurityScanner() )->scan_source( $source ), 'class' );

		self::assertContains( PurityScanner::CLASS_HTTP, $classes );
	}

	public function test_javascript_printed_as_markup_outside_php_tags_is_read_too(): void {
		$source = <<<'PHP'
<?php $x = 1; ?>
<script>
	new Image().src = 'https://t.example.com/pixel.gif';
</script>
PHP;

		$classes = array_column( ( new PurityScanner() )->scan_source( $source ), 'class' );

		self::assertContains( PurityScanner::CLASS_HTTP, $classes );
	}

	public function test_a_php_string_holding_a_url_nobody_calls_is_still_clean(): void {
		$source = <<<'PHP'
<?php
$docs = 'https://docs.example.com/guide';
printf( '<a href="%s">docs</a>', esc_url( $docs ) );
PHP;

		self::assertSame( array(), ( new PurityScanner() )->scan_source( $source ) );
	}

	public function test_a_minified_bundle_is_reported_unmeasurable_not_scanned(): void {
		/*
		 * On one 900 KB line every call and every URL in the file share a window,
		 * so an unrelated `fetch(` and an unrelated "http://www.w3.org/2000/svg"
		 * become an outbound call. An audit found exactly that in two real
		 * bundles. Mangled identifiers make the vocabulary half worthless at the
		 * same time. The honest answer is that a generated bundle is not readable
		 * evidence -- run the gate on the sources it was built from.
		 */
		$bundle = 'function fetch(a,b){return a[b]}' . str_repeat( 'var q=1;', 400 )
			. 'var ns="http://www.w3.org/2000/svg";';
		file_put_contents( $this->root . '/src/index.min.js', $bundle . "\n" );

		$hits = ( new PurityScanner() )->scan( $this->root );

		self::assertCount( 1, $hits );
		self::assertSame( PurityScanner::CLASS_UNMEASURABLE, $hits[0]['class'] );
		self::assertSame( 'minified', $hits[0]['name'] );
	}

	public function test_an_ordinary_source_line_is_not_mistaken_for_a_bundle(): void {
		file_put_contents(
			$this->root . '/src/ok.js',
			"export const label = 'a long but perfectly ordinary line of source code';\n"
		);

		self::assertSame( array(), ( new PurityScanner() )->scan( $this->root ) );
	}

	public function test_a_php_string_that_merely_names_a_javascript_call_is_not_one(): void {
		/*
		 * Inside PHP the gate cannot tell script from prose: an error message, a
		 * label, a pattern in a table all look the same as source. Found by running
		 * the new gate over this package, where the shape table's own label
		 * "import()" was reported as an undecided call. So embedded JavaScript is
		 * reported on evidence only -- a network shape AND an absolute URL -- and
		 * never on the absence of one.
		 */
		$source = <<<'PHP'
<?php
$shapes = array( 'import()', 'fetch', 'window.open' );
$message = 'Call import( path ) instead of fetch( url ) here.';
PHP;

		self::assertSame( array(), ( new PurityScanner() )->scan_source( $source ) );
	}

	public function test_a_wrapper_does_not_lend_its_strings_to_the_calls_inside_it(): void {
		/*
		 * Almost all WordPress JavaScript lives inside a wrapper: an IIFE, a
		 * jQuery ready callback, a registerBlockType object. An audit ran this gate
		 * over a real Lite core and found the SAME `$.ajax({url: ajax_url})` call
		 * reported undecided in thirty files and clean in six -- decided by an
		 * unrelated "/" inside the enclosing callback. A window that wide makes the
		 * answer random, which is worse than either answer.
		 *
		 * The window is the call's own argument list. Nothing else.
		 */
		$hits = ( new PurityScanner() )->scan_js_source(
			"( function ( $ ) {\n"
			. "\t$( '#x' ).html( '<input type=\"number\" />' );\n"
			. "\t$.ajax( { url: vars.endpoint, type: 'POST' } );\n"
			. "} )( jQuery );\n"
		);

		self::assertCount( 1, $hits );
		self::assertSame( PurityScanner::CLASS_UNMEASURABLE, $hits[0]['class'] );
		self::assertSame( 3, $hits[0]['line'], 'the finding belongs to the call, not to the wrapper' );
	}

	public function test_a_wrappers_absolute_url_does_not_become_the_calls_own(): void {
		/*
		 * The same defect in the other direction: an inline SVG namespace or a
		 * documentation link in the enclosing block turned a same-origin REST call
		 * into an outbound one -- the WP.org reviewer's own false positive, which
		 * this gate's negative fixture claims to have learned from.
		 */
		$hits = ( new PurityScanner() )->scan_js_source(
			"jQuery( function ( $ ) {\n"
			. "\tconst icon = '<svg xmlns=\"http://www.w3.org/2000/svg\"></svg>';\n"
			. "\tfetch( '/wp-json/myplugin/v1/items' );\n"
			. "} );\n"
		);

		self::assertSame( array(), $hits );
	}

	public function test_an_option_object_is_inside_the_calls_own_window(): void {
		$hits = ( new PurityScanner() )->scan_js_source(
			"$.ajax( {\n\turl: 'https://api.example.com/collect',\n\ttype: 'POST'\n} );\n"
		);

		self::assertCount( 1, $hits );
		self::assertSame( PurityScanner::CLASS_HTTP, $hits[0]['class'] );
	}

	public function test_a_fully_qualified_php_call_is_still_the_call(): void {
		/*
		 * Namespaced plugin code writes `\wp_remote_get(`, which PHP 8 hands over
		 * as T_NAME_FULLY_QUALIFIED, not T_STRING. That is not "a variable function
		 * name" -- it is the ordinary spelling, and it was invisible.
		 */
		$source = '<?php
namespace X;
' . '$r = ' . chr( 92 ) . "wp_remote_get( 'https://example.com' );
";

		$classes = array_column( ( new PurityScanner() )->scan_source( $source ), 'class' );

		self::assertContains( PurityScanner::CLASS_HTTP, $classes );
	}

	public function test_php_vocabulary_is_read_in_either_case_like_javascript(): void {
		$source = '<?php
' . 'class L { public function activateLicense( $licenseKey ) { return $this->isLicensed(); } }' . '
';

		$classes = array_column( ( new PurityScanner() )->scan_source( $source ), 'class' );

		self::assertContains( PurityScanner::CLASS_LICENSE, $classes );
	}

	public function test_a_name_bound_twice_is_not_resolved_at_all(): void {
		/*
		 * The binding map is file-wide and scope-blind. Resolving a name that two
		 * functions bind differently attributes one function's URL to the other's
		 * call -- a finding on innocent code, or a miss on guilty code, depending
		 * only on which binding was written last.
		 */
		$hits = ( new PurityScanner() )->scan_js_source(
			"function load() { const url = '/wp-json/myplugin/v1/items'; return fetch( url ); }\n"
			. "function help() { const url = 'https://docs.example.com/guide'; return url; }\n"
		);

		$classes = array_column( $hits, 'class' );
		self::assertNotContains( PurityScanner::CLASS_HTTP, $classes );
	}

	public function test_a_global_wordpress_itself_defines_is_read_as_this_site(): void {
		/*
		 * `ajaxurl` is admin-ajax.php on the current host by definition, and
		 * wpApiSettings.root is what wp_localize_script fills with rest_url(). An
		 * audit ran this gate over two real plugins and got 34 undecided calls,
		 * almost all of them these two idioms. Burying the genuinely undecided
		 * calls under the ordinary ones is how a gate stops being read.
		 */
		$hits = ( new PurityScanner() )->scan_js_source(
			"$.post( ajaxurl, data );\nfetch( wpApiSettings.root + 'wp/v2/posts' );\n"
		);

		self::assertSame( array(), $hits );
	}

	/**
	 * Ways a page loads something from elsewhere without calling `fetch`.
	 *
	 * @return array<string, array{0:string}>
	 */
	public function outboundSinks(): array {
		return array(
			'a tracking pixel written as markup'     => array(
				"const pixel = '<img src=\"https://t.example.com/pixel.gif\" alt=\"\" />';\n",
			),
			'the same pixel set through the DOM api' => array(
				"img.setAttribute( 'src', 'https://t.example.com/pixel.gif' );\n",
			),
			'an XHR whose verb is a variable'        => array(
				"xhr.open( method, 'https://api.example.com/track' );\n",
			),
			'a checkout opened in a new window'      => array(
				"window.open( 'https://example.com/checkout' );\n",
			),
			'a server-sent event stream'             => array(
				"const s = new EventSource( 'https://stream.example.com/live' );\n",
			),
			'a worker script pulled from a CDN'      => array(
				"importScripts( 'https://cdn.example.com/worker.js' );\n",
			),
			'a module imported from a CDN'           => array(
				"await import( 'https://cdn.example.com/pro.js' );\n",
			),
		);
	}

	/**
	 * @dataProvider outboundSinks
	 * @param string $source JavaScript source.
	 */
	public function test_a_resource_pulled_from_elsewhere_is_an_outbound_call( string $source ): void {
		$classes = array_column( ( new PurityScanner() )->scan_js_source( $source ), 'class' );

		self::assertContains( PurityScanner::CLASS_HTTP, $classes );
	}

	public function test_a_local_image_assignment_is_not_worth_a_word(): void {
		/*
		 * `img.src = imageUrl` is ordinary code. Reporting it undecided on every
		 * gallery in every plugin is how a gate stops being read, so a sink speaks
		 * on evidence and stays quiet otherwise.
		 */
		$hits = ( new PurityScanner() )->scan_js_source(
			"img.src = imageUrl;\nform.action = adminUrl;\nlocation.replace( returnTo );\n"
		);

		self::assertSame( array(), $hits );
	}

	public function test_typescript_sources_are_read_like_javascript(): void {
		file_put_contents( $this->root . '/src/telemetry.ts', "fetch( 'https://api.example.com/x' );\n" );

		$classes = array_column( ( new PurityScanner() )->scan( $this->root ), 'class' );

		self::assertSame( array( PurityScanner::CLASS_HTTP ), $classes );
	}

	public function test_dependencies_are_not_the_free_cores_own_code(): void {
		mkdir( $this->root . '/node_modules/pkg', 0755, true );
		file_put_contents( $this->root . '/node_modules/pkg/i.js', "fetch( 'https://api.example.com/x' );\n" );

		self::assertSame( array(), ( new PurityScanner() )->scan( $this->root ) );
	}

	public function test_an_embedded_script_reports_the_line_it_is_actually_on(): void {
		/*
		 * A finding whose line points at the top of the file sends the reader
		 * hunting. The offset inside the PHP token is part of the answer.
		 */
		$source = "<?php\n" . '$a = 1;' . "\n" . '$b = 2;' . "\n?>\n<script>\nfetch( \'https://t.example.com/b\' );\n</script>\n";

		$hits = ( new PurityScanner() )->scan_source( $source );

		self::assertCount( 1, $hits );
		self::assertSame( 6, $hits[0]['line'] );
	}

	public function test_a_regex_after_return_does_not_blind_what_follows(): void {
		$hits = ( new PurityScanner() )->scan_js_source(
			"function strip( u ) { return /\\/*$/.test( u ); }\n"
			. "fetch( 'https://licence.example.com/check' );\n"
		);

		$classes = array_column( $hits, 'class' );
		self::assertContains( PurityScanner::CLASS_HTTP, $classes );
	}

	public function test_a_long_hand_written_line_is_still_source(): void {
		/*
		 * A block icon's `<path d="...">` and a base64 data URI are ordinary long
		 * lines. Calling them generated means the file is never read at all.
		 */
		$path = str_repeat( 'M0 0L10 10 ', 100 );
		file_put_contents( $this->root . '/src/icon.jsx', "export const icon = '" . $path . "';\n" );

		self::assertSame( array(), ( new PurityScanner() )->scan( $this->root ) );
	}

	public function test_only_the_target_argument_decides_a_call_not_its_options(): void {
		/*
		 * The window narrowed from the enclosing block to the argument list, and
		 * the defect moved with it: a "/checkout" in an option object made an
		 * unresolved target clean, and a documentation URL in a payload made a
		 * same-origin call outbound. The target is the first argument -- or, when
		 * that is an option object, its url/path property. Nothing else.
		 */
		$hits = ( new PurityScanner() )->scan_js_source(
			"$.ajax( { url: settings.endpoint, type: 'POST', data: { redirect_to: '/checkout' } } );\n"
		);

		self::assertCount( 1, $hits );
		self::assertSame( PurityScanner::CLASS_UNMEASURABLE, $hits[0]['class'] );
	}

	public function test_a_payload_url_is_not_the_calls_own_target(): void {
		$hits = ( new PurityScanner() )->scan_js_source(
			"$.post( ajaxurl, { action: 'dismiss', docs: 'https://docs.example.com/notice' } );\n"
			. "fetch( '/wp-json/x/v1/share', { body: JSON.stringify( { url: 'https://twitter.com/intent/tweet' } ) } );\n"
		);

		self::assertSame( array(), $hits );
	}

	public function test_a_plugins_own_localize_key_is_not_a_wordpress_global(): void {
		/*
		 * `ajax_url`, `rest_url`, `restUrl`, `admin_url` are names a plugin chooses
		 * for its own wp_localize_script payload -- this package's own consumer
		 * defines all four. Reading them as "WordPress fills this locally" let an
		 * audit point one at https://telemetry.vendor.example and still get a clean
		 * run. Only the names WordPress itself defines are site globals.
		 */
		$hits = ( new PurityScanner() )->scan_js_source(
			"$.post( mpTracker.ajax_url, data );\nfetch( cfg.restUrl );\n"
		);

		self::assertCount( 2, $hits );
		self::assertSame( PurityScanner::CLASS_UNMEASURABLE, $hits[0]['class'] );
		self::assertSame( PurityScanner::CLASS_UNMEASURABLE, $hits[1]['class'] );
	}

	public function test_php_method_names_are_read_in_either_case(): void {
		/*
		 * The identifier was lowercased before the camel boundary was looked for,
		 * so `activateLicense()` -- a PSR-styled licence client's whole surface --
		 * was invisible while the README claimed both cases.
		 */
		$source = "<?php\n" . 'class L { public function activateLicense() {} public function isProActive() { return true; } }' . "\n";

		$classes = array_column( ( new PurityScanner() )->scan_source( $source ), 'class' );

		self::assertContains( PurityScanner::CLASS_LICENSE, $classes );
		self::assertContains( PurityScanner::CLASS_LIMIT, $classes );
	}

	public function test_every_named_wp_http_function_is_actually_listed(): void {
		$source = "<?php\n" . '$r = wp_safe_remote_head( ' . "'https://x.example' );" . "\n";

		$classes = array_column( ( new PurityScanner() )->scan_source( $source ), 'class' );

		self::assertContains( PurityScanner::CLASS_HTTP, $classes );
	}

	public function test_vocabulary_inside_an_interpolated_string_is_still_vocabulary(): void {
		$source = "<?php\n" . '$url = "edd_action=activate_license&license={$key}";' . "\n";

		$classes = array_column( ( new PurityScanner() )->scan_source( $source ), 'class' );

		self::assertContains( PurityScanner::CLASS_LICENSE, $classes );
	}

	public function test_the_plainest_upsell_redirect_is_an_outbound_call(): void {
		$hits = ( new PurityScanner() )->scan_js_source(
			"window.location = 'https://example.com/upgrade';\n"
		);

		self::assertCount( 1, $hits );
		self::assertSame( PurityScanner::CLASS_HTTP, $hits[0]['class'] );
	}

	public function test_a_wrapped_assignment_is_still_the_same_assignment(): void {
		/*
		 * Prettier wraps at 80 columns, so the URL lands on the next line. Stopping
		 * the window at the newline meant the commonest formatting hid the sink.
		 */
		$hits = ( new PurityScanner() )->scan_js_source(
			"img.src =\n\t'https://t.example.com/pixel.gif?site=' + host;\n"
		);

		self::assertCount( 1, $hits );
		self::assertSame( PurityScanner::CLASS_HTTP, $hits[0]['class'] );
	}

	public function test_one_call_is_one_finding(): void {
		$hits = ( new PurityScanner() )->scan_js_source( "window.open( 'https://example.com/checkout' );\n" );

		self::assertCount( 1, $hits );
	}

	public function test_a_method_named_fetch_is_a_definition_not_a_call(): void {
		$hits = ( new PurityScanner() )->scan_js_source(
			"class Store {\n\tfetch( id ) {\n\t\treturn this.items[ id ];\n\t}\n}\n"
		);

		self::assertSame( array(), $hits );
	}

	public function test_setattribute_speaks_for_resources_not_for_links(): void {
		$hits = ( new PurityScanner() )->scan_js_source(
			"link.setAttribute( 'href', 'https://docs.example.com/guide' );\n"
			. "el.setAttribute( 'data-docs', 'https://docs.example.com/guide' );\n"
		);

		self::assertSame( array(), $hits );
	}

	/**
	 * PHP calls that reach outside only when an absolute URL is handed to them.
	 *
	 * @return array<string, array{0:string}>
	 */
	public function phpCallsCarryingAUrl(): array {
		return array(
			'a script enqueued from a CDN'   => array( "wp_enqueue_script( 'x', 'https://cdn.example.com/x.js' );" ),
			'a font enqueued from a CDN'     => array( "wp_enqueue_style( 'f', 'https://fonts.googleapis.com/css2' );" ),
			'a script registered from a CDN' => array( "wp_register_script( 'x', 'https://cdn.example.com/x.js' );" ),
			'a file read over http'          => array( "$body = file_get_contents( 'https://api.example.com/x' );" ),
			'a download helper'              => array( "$tmp = download_url( 'https://api.example.com/x.zip' );" ),
			'headers fetched over http'      => array( "$h = get_headers( 'https://api.example.com/x' );" ),
		);
	}

	/**
	 * @dataProvider phpCallsCarryingAUrl
	 * @param string $statement One PHP statement.
	 */
	public function test_a_php_call_handed_an_absolute_url_reaches_outside( string $statement ): void {
		$classes = array_column( ( new PurityScanner() )->scan_source( "<?php\n" . $statement . "\n" ), 'class' );

		self::assertContains( PurityScanner::CLASS_HTTP, $classes );
	}

	public function test_the_same_php_calls_are_silent_on_local_paths(): void {
		/*
		 * `file_get_contents( __DIR__ . '/x.json' )` and a plugin's own asset URL are
		 * what these functions are for. They speak only on the evidence of an
		 * absolute URL, never on its absence -- otherwise every enqueue in every
		 * plugin becomes a finding and the gate stops being read.
		 */
		$source = "<?php\n"
			. "wp_enqueue_script( 'x', plugins_url( 'assets/x.js', __FILE__ ), array(), '1.0', true );\n"
			. '$json = file_get_contents( __DIR__ . ' . chr( 39 ) . '/data.json' . chr( 39 ) . ' );' . "\n";

		self::assertSame( array(), ( new PurityScanner() )->scan_source( $source ) );
	}

	public function test_a_missing_directory_yields_no_findings_not_a_crash(): void {
		self::assertSame( array(), ( new PurityScanner() )->scan( $this->root . '/does-not-exist' ) );
	}
}

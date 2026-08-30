<?php

declare(strict_types=1);

namespace MHMUiCore\Tests\Gate;

use PHPUnit\Framework\TestCase;

/**
 * G2 -- the gate that keeps the package speechless.
 *
 * The package owns no consumer slug, so it cannot name a text domain that a
 * .pot would ever collect. A string added under src/Layout would not be
 * "untranslated" -- it would be invisible to every extractor and ship as raw
 * English forever, in every consumer, with nothing anywhere reporting it.
 *
 * One case per gettext variant, because a scanner that knows only __() waves
 * through esc_html_e() and _nx() -- exactly the blind spot this gate exists
 * not to have.
 */
final class NoI18nGateTest extends TestCase {

	private const GATE = __DIR__ . '/../../bin/check-no-i18n.php';

	/** @var list<string> */
	private array $temp_dirs = array();

	protected function tearDown(): void {
		foreach ( $this->temp_dirs as $dir ) {
			$this->remove_recursive( $dir );
		}
		$this->temp_dirs = array();

		parent::tearDown();
	}

	/** @dataProvider gettext_variants */
	public function test_each_gettext_variant_is_caught( string $snippet ): void {
		$result = $this->run_gate_against( "<?php\nnamespace MHMUiCore\\Layout;\nclass X { public function f() { " . $snippet . " } }\n" );

		$this->assertSame( 1, $result['exit'], 'Gate accepted: ' . $snippet . "\nOutput:\n" . $result['out'] );
	}

	/**
	 * Every name bin/check-no-i18n.php bans, one call form each. This list must
	 * stay identical to the gate's own $banned array -- that duplication is the
	 * point: the test enumerates the promise independently of the
	 * implementation, so a name quietly dropped from the gate's array shows up
	 * here as a variant nothing exercises, not as a silently narrower gate.
	 *
	 * @return array<string,array{0:string}>
	 */
	public function gettext_variants(): array {
		return array(
			'__'                             => array( 'return __( "x", "d" );' ),
			'_e'                             => array( '_e( "x", "d" );' ),
			'_x'                             => array( 'return _x( "x", "ctx", "d" );' ),
			'_n'                             => array( 'return _n( "a", "b", 2, "d" );' ),
			'_ex'                            => array( '_ex( "x", "ctx", "d" );' ),
			'_nx'                            => array( 'return _nx( "a", "b", 2, "ctx", "d" );' ),
			'_n_noop'                        => array( 'return _n_noop( "a", "b", "d" );' ),
			'_nx_noop'                       => array( 'return _nx_noop( "a", "b", "ctx", "d" );' ),
			'esc_html__'                     => array( 'return esc_html__( "x", "d" );' ),
			'esc_html_e'                     => array( 'esc_html_e( "x", "d" );' ),
			'esc_html_x'                     => array( 'return esc_html_x( "x", "ctx", "d" );' ),
			'esc_attr__'                     => array( 'return esc_attr__( "x", "d" );' ),
			'esc_attr_e'                     => array( 'esc_attr_e( "x", "d" );' ),
			'esc_attr_x'                     => array( 'return esc_attr_x( "x", "ctx", "d" );' ),
			'translate'                      => array( 'return translate( "x", "d" );' ),
			'translate_with_gettext_context' => array( 'return translate_with_gettext_context( "x", "ctx", "d" );' ),
			// Not a banned name of its own -- this proves the discriminator survives
			// a root-prefixed call form. token_get_all() tokenizes "\__" as a single
			// T_NAME_FULLY_QUALIFIED token, not as T_STRING "__"; a scanner that only
			// matches T_STRING would let this exact form through.
			'root-prefixed \\__'             => array( 'return \\__( "x", "d" );' ),
		);
	}

	public function test_clean_code_passes(): void {
		$result = $this->run_gate_against( "<?php\nnamespace MHMUiCore\\Layout;\nclass X { public function f() { return 'plain'; } }\n" );

		$this->assertSame( 0, $result['exit'], $result['out'] );
		$this->assertStringContainsString( 'SUMMARY: 0', $result['out'] );
	}

	public function test_a_comment_mentioning_gettext_is_not_a_call(): void {
		// token_get_all() must be the discriminator here, not a substring search:
		// this file never calls __(), it only spells the name inside a comment.
		$php  = "<?php\n";
		$php .= "namespace MHMUiCore\\Layout;\n";
		$php .= "// we deliberately never call __() here, we just talk about it\n";
		$php .= "/* esc_html_e() is also just mentioned, never called */\n";
		$php .= "class X {\n";
		$php .= "\tpublic function f(): string {\n";
		$php .= "\t\treturn 'no gettext functions are invoked in this file';\n";
		$php .= "\t}\n";
		$php .= "}\n";

		$result = $this->run_gate_against( $php );

		$this->assertSame( 0, $result['exit'], $result['out'] );
		$this->assertStringContainsString( 'SUMMARY: 0', $result['out'] );
	}

	public function test_missing_target_directory_exits_two_without_claiming_zero(): void {
		$root = sys_get_temp_dir() . '/mhmui-gate-empty-' . uniqid( '', true );
		mkdir( $root, 0777, true );
		$this->temp_dirs[] = $root;

		$out  = array();
		$exit = 0;
		exec( 'php ' . escapeshellarg( self::GATE ) . ' ' . escapeshellarg( $root ) . ' 2>&1', $out, $exit );
		$joined = implode( "\n", $out );

		$this->assertSame( 2, $exit, $joined );
		// "I could not measure" must never read like "I measured zero".
		$this->assertStringNotContainsString( 'SUMMARY: 0', $joined );
	}

	/** @return array{exit:int,out:string} */
	private function run_gate_against( string $php ): array {
		$dir = sys_get_temp_dir() . '/mhmui-gate-' . uniqid( '', true ) . '/src/Layout';
		mkdir( $dir, 0777, true );
		file_put_contents( $dir . '/Sample.php', $php );

		$root                = dirname( $dir, 2 );
		$this->temp_dirs[]   = $root;

		$out  = array();
		$exit = 0;
		exec( 'php ' . escapeshellarg( self::GATE ) . ' ' . escapeshellarg( $root ) . ' 2>&1', $out, $exit );

		return array(
			'exit' => $exit,
			'out'  => implode( "\n", $out ),
		);
	}

	private function remove_recursive( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $items as $item ) {
			if ( $item->isDir() ) {
				rmdir( $item->getPathname() );
			} else {
				unlink( $item->getPathname() );
			}
		}

		rmdir( $dir );
	}
}

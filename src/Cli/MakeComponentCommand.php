<?php
/**
 * `wp mhm-ui make:component`.
 *
 * @package MHMUiCore\Cli
 */

declare(strict_types=1);

namespace MHMUiCore\Cli;

use MHMUiCore\Component\ComponentContract;
use MHMUiCore\Component\ComponentFactory;
use MHMUiCore\Component\ComponentScaffolder;
use Throwable;
use WP_CLI;

/**
 * Scaffold a component from the command line.
 *
 * ## OPTIONS
 *
 * <slug>
 * : Component slug, ^[a-z][a-z0-9_]*$.
 *
 * --prefix=<prefix>
 * : Product prefix for shortcode and Elementor names.
 *
 * --block-namespace=<ns>
 * : Block namespace, e.g. mhm-rentiva.
 *
 * --text-domain=<domain>
 * : Product text domain.
 *
 * --php-namespace=<ns>
 * : Namespace for the generated renderer class.
 *
 * [--title=<title>]
 * : Human title. Defaults to the slug, title-cased.
 *
 * [--dir=<dir>]
 * : Product root to write into. Defaults to the current directory.
 *
 * [--dry-run]
 * : Print the files instead of writing them.
 */
final class MakeComponentCommand {

	/**
	 * Run.
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Flags.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$slug = $args[0] ?? '';

		try {
			$factory = new ComponentFactory(
				array(
					'prefix'          => (string) ( $assoc_args['prefix'] ?? '' ),
					'block_namespace' => (string) ( $assoc_args['block-namespace'] ?? '' ),
					'text_domain'     => (string) ( $assoc_args['text-domain'] ?? '' ),
				)
			);

			$contract = new ComponentContract(
				array(
					'slug'  => $slug,
					'title' => (string) ( $assoc_args['title'] ?? ucwords( str_replace( '_', ' ', $slug ) ) ),
				)
			);

			$scaffolder = new ComponentScaffolder( $factory, (string) ( $assoc_args['php-namespace'] ?? '' ) );

			if ( isset( $assoc_args['dry-run'] ) ) {
				foreach ( $scaffolder->files( $contract ) as $path => $contents ) {
					WP_CLI::log( '--- ' . $path );
					WP_CLI::log( $contents );
				}
				return;
			}

			$dir = (string) ( $assoc_args['dir'] ?? getcwd() );
			foreach ( $scaffolder->write( $contract, $dir ) as $path ) {
				WP_CLI::log( 'wrote ' . $path );
			}
			WP_CLI::success( 'Component "' . $slug . '" scaffolded. Write the renderer; everything else is derived.' );
		} catch ( Throwable $e ) {
			WP_CLI::error( $e->getMessage() );
		}
	}
}

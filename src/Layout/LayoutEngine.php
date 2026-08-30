<?php
/**
 * Layout Engine
 *
 * @package MHMUiCore\Layout
 */

declare(strict_types=1);

namespace MHMUiCore\Layout;

use WP_Error;

/**
 * The four surfaces the consumer's WP-CLI command needs -- and nothing that
 * touches the database.
 *
 * Persistence (atomic import, audit log, history, rollback) stays in the
 * consumer: this package has no WordPress test harness, so a moved
 * wp_insert_post() could only ever be tested against a mock of itself. The
 * four methods below map one-to-one onto the consumer's four WP-CLI
 * subcommands -- import, rollback, history, diff -- and nothing else is
 * added on a guess about a caller that does not exist.
 */
final class LayoutEngine {

	/**
	 * Validates manifest data against structural requirements and governance rules.
	 *
	 * @var BlueprintValidator
	 */
	private $validator;

	/**
	 * Assembles a page's composition into rendered markup.
	 *
	 * @var CompositionBuilder
	 */
	private $builder;

	/**
	 * Constructor.
	 *
	 * @param LayoutContract $contract The consumer identity used to build the
	 *                                 validator and builder it delegates to.
	 */
	public function __construct( LayoutContract $contract ) {
		$this->validator = new BlueprintValidator( $contract );
		$this->builder   = new CompositionBuilder( $contract );
	}

	/**
	 * Validates raw manifest data.
	 *
	 * @param array<string,mixed> $manifest Manifest data.
	 * @return true|WP_Error
	 */
	public function validate( array $manifest ) {
		return $this->validator->validate( $manifest );
	}

	/**
	 * Builds the final post content markup from blueprint composition.
	 *
	 * @param array<string,mixed> $manifest Full blueprint manifest.
	 * @param array<string,mixed> $page     Specific page entry from manifest.
	 * @return string|WP_Error Rendered markup.
	 */
	public function build( array $manifest, array $page ) {
		return $this->builder->build( $manifest, $page );
	}

	/**
	 * Normalizes a validated manifest into deterministic structure.
	 *
	 * @param array<int|string,mixed> $manifest Validated manifest.
	 * @return array<int|string,mixed>
	 */
	public function normalize( array $manifest ): array {
		return Normalization::normalize( $manifest );
	}

	/**
	 * Computes a deterministic diff between two manifests.
	 *
	 * @param array<int|string,mixed> $current  Current manifest map.
	 * @param array<int|string,mixed> $previous Previous manifest map.
	 * @return array<string,mixed> Diff result summary.
	 */
	public function diff( array $current, array $previous ): array {
		return DiffService::diff( $current, $previous );
	}
}

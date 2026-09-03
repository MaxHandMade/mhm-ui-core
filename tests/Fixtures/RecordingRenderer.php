<?php
declare( strict_types = 1 );

namespace MHMUiCore\Tests\Fixtures;

use MHMUiCore\Component\ComponentRenderer;

/**
 * A renderer that remembers what it was asked to render.
 *
 * The surfaces are the subject under test; the renderer only has to make what
 * reached it observable. It returns a deterministic string carrying the surface
 * and the typed settings so a single assertion can pin both.
 */
final class RecordingRenderer implements ComponentRenderer {

	/** @var list<array{settings: array<string, mixed>, context: array<string, mixed>}> */
	public $calls = array();

	public function render( array $settings, array $context ): string {
		$this->calls[] = array(
			'settings' => $settings,
			'context'  => $context,
		);

		return '<div data-surface="' . $context['surface'] . '" data-id="' . $context['instance_id'] . '">'
			. json_encode( $settings )
			. '</div>';
	}

	/** @return array<string, mixed> */
	public function last_settings(): array {
		return $this->calls[ count( $this->calls ) - 1 ]['settings'];
	}

	/** @return array<string, mixed> */
	public function last_context(): array {
		return $this->calls[ count( $this->calls ) - 1 ]['context'];
	}
}

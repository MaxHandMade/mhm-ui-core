<?php
/**
 * The declarative component contract: Sabit / Veri / Ayar.
 *
 * @package MHMUiCore\Component
 */

declare(strict_types=1);

namespace MHMUiCore\Component;

use InvalidArgumentException;

/**
 * One component, declared once.
 *
 * The design document calls this "the component's only real design decision":
 * every part of a design is either FIXED (stays in the template), DATA (the
 * renderer's query) or a SETTING (a user choice). Only the settings need
 * declaring -- fixed parts live in the renderer's template, and data keys are
 * named here so the three surfaces can promise what the renderer provides.
 *
 * From this one object the package DERIVES the shortcode attribute allowlist,
 * the block.json attributes, the Elementor controls and the Layout adapter. The
 * product writes the renderer and nothing else.
 *
 * Labels are plain strings on purpose: this package has no text domain, so the
 * product passes labels it has already translated. A gettext call here would be
 * invisible to every extractor (see bin/check-no-i18n.php).
 */
final class ComponentContract {

	public const TYPE_STRING  = 'string';
	public const TYPE_BOOLEAN = 'boolean';
	public const TYPE_INTEGER = 'integer';
	public const TYPE_ENUM    = 'enum';

	private const TYPES = array( self::TYPE_STRING, self::TYPE_BOOLEAN, self::TYPE_INTEGER, self::TYPE_ENUM );

	private const SLUG_PATTERN = '/^[a-z][a-z0-9_]*$/';
	private const KEY_PATTERN  = '/^[a-zA-Z][a-zA-Z0-9_]*$/';

	/**
	 * Machine name, e.g. "featured_vehicles".
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * Human title, already translated by the product.
	 *
	 * @var string
	 */
	private $title;

	/**
	 * Normalised setting declarations, keyed by name.
	 *
	 * @var array<string, array{type:string, default:mixed, label:string, enum:list<string>}>
	 */
	private $settings = array();

	/**
	 * Keys the renderer promises to compute from the database.
	 *
	 * @var list<string>
	 */
	private $data_keys = array();

	/**
	 * Block-only presentation hints (category, icon, supports).
	 *
	 * @var array<string, mixed>
	 */
	private $block = array();

	/**
	 * Build a contract from a declaration array.
	 *
	 * Keys: 'slug' (required, ^[a-z][a-z0-9_]*$) · 'title' (required, translated) ·
	 * 'settings' (name => {type, default, label, enum}) · 'data' (keys the renderer
	 * provides) · 'block' ({category, icon, description, supports, editorStyle, style}).
	 *
	 * @param array<string, mixed> $config Declaration; keys described above.
	 *
	 * @throws InvalidArgumentException When the declaration is malformed. Loud on
	 *                                  purpose: a contract is read once at boot
	 *                                  and a silent default here would ship a
	 *                                  surface with the wrong shape.
	 */
	public function __construct( array $config ) {
		$slug = $config['slug'] ?? null;
		if ( ! is_string( $slug ) || 1 !== preg_match( self::SLUG_PATTERN, $slug ) ) {
			throw new InvalidArgumentException( 'ComponentContract: "slug" must match ^[a-z][a-z0-9_]*$.' );
		}
		$this->slug = $slug;

		$title = $config['title'] ?? null;
		if ( ! is_string( $title ) || '' === trim( $title ) ) {
			throw new InvalidArgumentException( 'ComponentContract: "title" must be a non-empty string.' );
		}
		$this->title = $title;

		$settings = $config['settings'] ?? array();
		if ( ! is_array( $settings ) ) {
			throw new InvalidArgumentException( 'ComponentContract: "settings" must be an array.' );
		}
		foreach ( $settings as $name => $declaration ) {
			$this->settings[ (string) $name ] = $this->normalise_setting( (string) $name, $declaration );
		}

		$data = $config['data'] ?? array();
		if ( ! is_array( $data ) ) {
			throw new InvalidArgumentException( 'ComponentContract: "data" must be a list of keys.' );
		}
		foreach ( $data as $key ) {
			if ( ! is_string( $key ) || 1 !== preg_match( self::KEY_PATTERN, $key ) ) {
				throw new InvalidArgumentException( 'ComponentContract: every "data" key must be an identifier.' );
			}
			$this->data_keys[] = $key;
		}

		$block = $config['block'] ?? array();
		if ( ! is_array( $block ) ) {
			throw new InvalidArgumentException( 'ComponentContract: "block" must be an array.' );
		}
		$this->block = $block;
	}

	/**
	 * Validate one setting declaration and fill its defaults.
	 *
	 * @param string $name        Setting name.
	 * @param mixed  $declaration Raw declaration.
	 * @return array{type:string, default:mixed, label:string, enum:list<string>}
	 *
	 * @throws InvalidArgumentException When the type is unknown, the enum is
	 *                                  empty, or the default is outside the enum.
	 */
	private function normalise_setting( string $name, $declaration ): array {
		if ( 1 !== preg_match( self::KEY_PATTERN, $name ) ) {
			throw new InvalidArgumentException(
				esc_html( sprintf( 'ComponentContract: setting name "%s" must be an identifier.', $name ) )
			);
		}
		if ( ! is_array( $declaration ) ) {
			throw new InvalidArgumentException(
				esc_html( sprintf( 'ComponentContract: setting "%s" must be an array.', $name ) )
			);
		}

		$type = $declaration['type'] ?? self::TYPE_STRING;
		if ( ! is_string( $type ) || ! in_array( $type, self::TYPES, true ) ) {
			throw new InvalidArgumentException(
				esc_html( sprintf( 'ComponentContract: setting "%s" has unknown type.', $name ) )
			);
		}

		$enum = array();
		if ( self::TYPE_ENUM === $type ) {
			$raw_enum = $declaration['enum'] ?? array();
			if ( ! is_array( $raw_enum ) || array() === $raw_enum ) {
				throw new InvalidArgumentException(
					esc_html( sprintf( 'ComponentContract: enum setting "%s" needs a non-empty "enum" list.', $name ) )
				);
			}
			foreach ( $raw_enum as $option ) {
				$enum[] = (string) $option;
			}
		}

		$default = $declaration['default'] ?? $this->type_default( $type, $enum );
		if ( self::TYPE_ENUM === $type && ! in_array( (string) $default, $enum, true ) ) {
			throw new InvalidArgumentException(
				esc_html( sprintf( 'ComponentContract: default of enum setting "%s" is not one of its options.', $name ) )
			);
		}

		$label = $declaration['label'] ?? $name;

		return array(
			'type'    => $type,
			'default' => $this->coerce( $type, $default, $enum, $default ),
			'label'   => (string) $label,
			'enum'    => $enum,
		);
	}

	/**
	 * The default a type falls back to when the declaration names none.
	 *
	 * @param string             $type    Setting type.
	 * @param array<int, string> $options Enum options, for TYPE_ENUM.
	 * @return mixed
	 */
	private function type_default( string $type, array $options ) {
		switch ( $type ) {
			case self::TYPE_BOOLEAN:
				return false;
			case self::TYPE_INTEGER:
				return 0;
			case self::TYPE_ENUM:
				return $options[0];
			default:
				return '';
		}
	}

	/**
	 * Machine name.
	 *
	 * @return string
	 */
	public function slug(): string {
		return $this->slug;
	}

	/**
	 * Machine name with hyphens, for block names and file names.
	 *
	 * @return string
	 */
	public function kebab(): string {
		return str_replace( '_', '-', $this->slug );
	}

	/**
	 * Translated title.
	 *
	 * @return string
	 */
	public function title(): string {
		return $this->title;
	}

	/**
	 * Setting declarations.
	 *
	 * @return array<string, array{type:string, default:mixed, label:string, enum:list<string>}>
	 */
	public function settings(): array {
		return $this->settings;
	}

	/**
	 * Setting defaults, keyed by name -- the shortcode_atts() baseline.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		$out = array();
		foreach ( $this->settings as $name => $setting ) {
			$out[ $name ] = $setting['default'];
		}
		return $out;
	}

	/**
	 * Keys the renderer provides from the database.
	 *
	 * @return list<string>
	 */
	public function data_keys(): array {
		return $this->data_keys;
	}

	/**
	 * Block presentation hints as declared.
	 *
	 * @return array<string, mixed>
	 */
	public function block_hints(): array {
		return $this->block;
	}

	/**
	 * Coerce raw surface input into the declared types.
	 *
	 * This is the allowlist: a key that is not a declared setting is dropped, a
	 * value outside an enum falls back to the default, a boolean accepts every
	 * spelling the three surfaces produce ('1'/'0' from shortcodes, true/false
	 * from blocks, 'yes'/'no' from Elementor switchers). Every surface runs its
	 * input through here, so the renderer sees one shape regardless of origin.
	 *
	 * @param array<string, mixed> $raw Attributes as the surface received them.
	 * @return array<string, mixed> Declared settings only, typed.
	 */
	public function sanitize( array $raw ): array {
		$out = array();
		foreach ( $this->settings as $name => $setting ) {
			$value        = array_key_exists( $name, $raw ) ? $raw[ $name ] : $setting['default'];
			$out[ $name ] = $this->coerce( $setting['type'], $value, $setting['enum'], $setting['default'] );
		}
		return $out;
	}

	/**
	 * Coerce one value to one type.
	 *
	 * @param string             $type     Setting type.
	 * @param mixed              $value    Raw value.
	 * @param array<int, string> $options  Enum options.
	 * @param mixed              $fallback Fallback for out-of-range enums.
	 * @return mixed
	 */
	private function coerce( string $type, $value, array $options, $fallback ) {
		switch ( $type ) {
			case self::TYPE_BOOLEAN:
				if ( is_bool( $value ) ) {
					return $value;
				}
				$text = strtolower( trim( (string) $value ) );
				return in_array( $text, array( '1', 'true', 'yes', 'on' ), true );

			case self::TYPE_INTEGER:
				return (int) $value;

			case self::TYPE_ENUM:
				$text = (string) $value;
				return in_array( $text, $options, true ) ? $text : (string) $fallback;

			default:
				return $this->clean_text( (string) $value );
		}
	}

	/**
	 * Strip markup from a free-text setting.
	 *
	 * Uses sanitize_text_field() when WordPress is loaded; the package's unit
	 * suite runs without it, and a tag stripper is the honest equivalent there.
	 *
	 * @param string $value Raw text.
	 * @return string
	 */
	private function clean_text( string $value ): string {
		if ( function_exists( 'sanitize_text_field' ) ) {
			return sanitize_text_field( $value );
		}
		return trim( (string) preg_replace( '/<[^>]*>/', '', $value ) );
	}
}

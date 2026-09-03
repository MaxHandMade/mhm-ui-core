<?php
/**
 * Rentiva's featured-vehicles block, written back as a contract.
 *
 * Phase 4's acceptance test from the design document: "an existing Rentiva
 * component is turned back into a contract and regenerated from the scaffold;
 * if the result does not match the existing code, the abstraction is wrong."
 * featured-vehicles.block.json next to this file is a verbatim copy of the
 * product's shipped block.json; the test regenerates it from THIS declaration.
 *
 * The supports block is passed through as data: it is the product's choice of
 * editor features, not something the package decides, so the contract carries
 * it verbatim under 'block'.
 */

declare( strict_types = 1 );

$bool = static fn( bool $default ): array => array(
	'type'    => 'boolean',
	'default' => $default,
);
$text = static fn( string $default ): array => array(
	'type'    => 'string',
	'default' => $default,
);

return array(
	'slug'     => 'featured_vehicles',
	'title'    => 'MHM Featured Vehicles',
	'settings' => array(
		'layout'             => $text( 'slider' ),
		'showPrice'          => $bool( true ),
		'showRating'         => $bool( true ),
		'showCategory'       => $bool( true ),
		'showBookButton'     => $bool( true ),
		'showFeatures'       => $bool( true ),
		'showBrand'          => $bool( false ),
		'showAvailability'   => $bool( false ),
		'showCompareButton'  => $bool( true ),
		'showBadges'         => $bool( true ),
		'showFavoriteButton' => $bool( true ),
		'filterCategories'   => $text( '' ),
		'sortBy'             => array(
			'type'    => 'enum',
			'default' => 'date',
			'enum'    => array( 'date', 'title', 'price', 'popularity', 'newest', 'rating' ),
		),
		'sortOrder'          => array(
			'type'    => 'enum',
			'default' => 'desc',
			'enum'    => array( 'asc', 'desc' ),
		),
		'limit'              => $text( '6' ),
		'columns'            => $text( '3' ),
		'title'              => $text( '' ),
		'ids'                => $text( '' ),
		'category'           => $text( '' ),
		'autoplay'           => $bool( true ),
		'interval'           => $text( '5000' ),
		'showBookingButton'  => $bool( true ),
		'maxFeatures'        => $text( '5' ),
		'imageSize'          => $text( 'large' ),
		'priceFormat'        => $text( 'daily' ),
		'viewAllUrl'         => $text( '' ),
		'viewAllText'        => $text( '' ),
		'className'          => $text( '' ),
	),
	'data'     => array( 'vehicles' ),
	'block'    => array(
		'category'    => 'widgets',
		'icon'        => 'star-filled',
		'description' => 'Display featured vehicles as a carousel.',
		'supports'    => array(
			'html'            => false,
			'anchor'          => true,
			'className'       => true,
			'customClassName' => true,
			'align'           => array( 'wide', 'full', 'center' ),
			'color'           => array(
				'text'       => true,
				'background' => true,
				'link'       => true,
				'gradients'  => true,
			),
			'spacing'         => array(
				'margin'   => true,
				'padding'  => true,
				'blockGap' => true,
			),
			'border'          => array(
				'color'  => true,
				'radius' => true,
				'style'  => true,
				'width'  => true,
			),
			'typography'      => array(
				'fontSize'                 => true,
				'lineHeight'               => true,
				'__experimentalFontFamily' => true,
				'__experimentalFontWeight' => true,
			),
		),
		'editorStyle' => array( 'mhm-rentiva-core-variables' ),
		'style'       => array( 'mhm-rentiva-featured-vehicles' ),
	),
);

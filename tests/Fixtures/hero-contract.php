<?php
/**
 * The contract every surface test shares: one of each setting type.
 */

declare( strict_types = 1 );

return array(
	'slug'     => 'hero',
	'title'    => 'Hero',
	'settings' => array(
		'title'      => array(
			'type'    => 'string',
			'default' => '',
			'label'   => 'Title',
		),
		'showButton' => array(
			'type'    => 'boolean',
			'default' => true,
			'label'   => 'Show button',
		),
		'columns'    => array(
			'type'    => 'integer',
			'default' => 3,
			'label'   => 'Columns',
		),
		'layout'     => array(
			'type'    => 'enum',
			'default' => 'grid',
			'enum'    => array( 'grid', 'slider' ),
			'label'   => 'Layout',
		),
	),
	'data'     => array( 'items' ),
	'block'    => array(
		'category'    => 'widgets',
		'icon'        => 'star-filled',
		'description' => 'A hero.',
	),
);

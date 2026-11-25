<?php defined( 'ABSPATH' ) || exit;
// This file is generated. Do not modify it manually.
return array(
	'table-of-contents' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'core/table-of-contents',
		'title' => 'Table of Contents',
		'category' => 'design',
		'description' => 'Summarize your post with a list of headings. Add HTML anchors to Heading blocks to link them here.',
		'keywords' => array(
			'document outline',
			'summary'
		),
		'textdomain' => 'ship-fixes',
		'usesContext' => array(
			'postId',
			'postType'
		),
		'attributes' => array(
			'onlyIncludeCurrentPage' => array(
				'type' => 'boolean',
				'default' => false
			),
			'maxLevel' => array(
				'type' => 'number'
			),
			'ordered' => array(
				'type' => 'boolean',
				'default' => true
			)
		),
		'supports' => array(
			'anchor' => true,
			'ariaLabel' => true,
			'html' => false,
			'background' => array(
				'backgroundImage' => true,
				'backgroundSize' => true,
				'gradient' => true
			),
			'color' => array(
				'text' => true,
				'background' => true,
				'gradients' => true,
				'link' => true
			),
			'shadow' => true,
			'spacing' => array(
				'margin' => true,
				'padding' => true
			),
			'typography' => array(
				'fontSize' => true,
				'lineHeight' => true,
				'textAlign' => true,
				'__experimentalFontFamily' => true,
				'__experimentalFontWeight' => true,
				'__experimentalFontStyle' => true,
				'__experimentalTextTransform' => true,
				'__experimentalTextDecoration' => true,
				'__experimentalLetterSpacing' => true,
				'__experimentalDefaultControls' => array(
					'fontSize' => true
				)
			),
			'interactivity' => array(
				'clientNavigation' => true
			),
			'__experimentalBorder' => array(
				'radius' => true,
				'color' => true,
				'width' => true,
				'style' => true,
				'__experimentalDefaultControls' => array(
					'radius' => true,
					'color' => true,
					'width' => true,
					'style' => true
				)
			)
		),
		'style' => 'file:./style-index.css',
		'editorScript' => 'file:./index.js'
	)
);

<?php
/**
 * The fixes: small, separate things this plugin does, each switched on or off in the settings (Settings). A fix is one
 * class in includes/ with a static init() that adds its hooks; it is only called where the fix is on.
 *
 * To add a fix: its class, an entry in FIXES (ID in snake_case, class, default) and its texts in texts().
 *
 * @package Ship\Fixes
 */

declare( strict_types=1 );

namespace Ship\Fixes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The list of fixes.
 */
final class Fixes {

	/**
	 * Every fix: ID => class and whether it is on until someone decides otherwise.
	 *
	 * @var array<string, array{class: class-string, default: bool}>
	 */
	public const FIXES = array(
		'table_of_contents' => array(
			'class'   => Table_Of_Contents::class,
			'default' => true,
		),
		'youtube_no_cookie' => array(
			'class'   => YouTube_No_Cookie::class,
			'default' => true,
		),
	);

	/**
	 * Starts the fixes that are on for the current site (on plugins_loaded).
	 *
	 * @return void
	 */
	public static function init(): void {
		foreach ( Settings::get() as $id => $on ) {
			if ( $on ) {
				call_user_func( array( self::FIXES[ $id ]['class'], 'init' ) );
			}
		}
	}

	/**
	 * Label and description of every fix, for the settings screens (translated: not before init).
	 *
	 * @return array<int, array{id: string, label: string, description: string}>
	 */
	public static function texts(): array {
		return array(
			array(
				'id'          => 'table_of_contents',
				'label'       => __( 'Table of Contents block', 'ship-fixes' ),
				'description' => __( 'The Table of Contents block of the Gutenberg plugin, for sites without that plugin. It steps aside when the Gutenberg plugin or WordPress itself provides the block.', 'ship-fixes' ),
			),
			array(
				'id'          => 'youtube_no_cookie',
				'label'       => __( 'YouTube privacy-enhanced mode', 'ship-fixes' ),
				'description' => __( "YouTube videos in Embed blocks play from youtube-nocookie.com (YouTube's privacy-enhanced mode), whatever the link: youtube.com, youtu.be, Shorts, live or youtube-nocookie.com. The video's title and start time are kept.", 'ship-fixes' ),
			),
		);
	}
}

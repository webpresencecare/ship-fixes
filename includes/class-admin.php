<?php
/**
 * The settings screens (React, DataForm), reading and saving through the plugin's REST routes (Rest):
 *
 * - each site's Settings, Ship Fixes, wherever the plugin is active and the site may change its settings;
 * - Network Admin, Settings, Ship Fixes, when the plugin is activated for the whole network.
 *
 * @package Ship\Fixes
 */

declare( strict_types=1 );

namespace Ship\Fixes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Menu entries, pages and scripts.
 */
final class Admin {

	/** Page slug (the same on both screens). */
	public const PAGE = 'ship-fixes';

	/**
	 * Hook suffixes of the pages added, and their scope (site or network).
	 *
	 * @var array<string, string>
	 */
	private static array $hooks = array();

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'network_admin_menu', array( __CLASS__, 'network_menu' ) );
		add_action( 'admin_menu', array( __CLASS__, 'site_menu' ) );
		add_filter( 'network_admin_plugin_action_links_' . plugin_basename( SHIP_FIXES_FILE ), array( __CLASS__, 'network_action_links' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SHIP_FIXES_FILE ), array( __CLASS__, 'site_action_links' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * A settings screen's address.
	 *
	 * @param string $scope site or network.
	 * @return string
	 */
	public static function url( string $scope = 'site' ): string {
		return 'network' === $scope
			? network_admin_url( 'settings.php?page=' . self::PAGE )
			: admin_url( 'options-general.php?page=' . self::PAGE );
	}

	/**
	 * Network Admin, Settings, when the plugin runs network-wide.
	 *
	 * @return void
	 */
	public static function network_menu(): void {
		if ( Settings::network_wide() ) {
			self::add( 'settings.php', 'manage_network_options', 'network' );
		}
	}

	/**
	 * The site's Settings, when the site may change its settings.
	 *
	 * @return void
	 */
	public static function site_menu(): void {
		if ( Settings::site_may_change() ) {
			self::add( 'options-general.php', 'manage_options', 'site' );
		}
	}

	/**
	 * Adds one page.
	 *
	 * @param string $parent_menu Parent menu.
	 * @param string $capability  Capability.
	 * @param string $scope       site or network.
	 * @return void
	 */
	private static function add( string $parent_menu, string $capability, string $scope ): void {
		// The plugin's name is not translated.
		$hook = add_submenu_page( $parent_menu, 'Ship Fixes', 'Ship Fixes', $capability, self::PAGE, array( __CLASS__, 'render' ) );
		if ( is_string( $hook ) ) {
			self::$hooks[ $hook ] = $scope;
		}
	}

	/**
	 * A Settings link on the network's Plugins screen.
	 *
	 * @param array<int|string, string> $links Links.
	 * @return array<int|string, string>
	 */
	public static function network_action_links( $links ): array {
		$links = (array) $links;
		if ( Settings::network_wide() && current_user_can( 'manage_network_options' ) ) {
			array_unshift( $links, self::link( 'network' ) );
		}
		return $links;
	}

	/**
	 * A Settings link on a site's Plugins screen.
	 *
	 * @param array<int|string, string> $links Links.
	 * @return array<int|string, string>
	 */
	public static function site_action_links( $links ): array {
		$links = (array) $links;
		if ( Settings::site_may_change() && current_user_can( 'manage_options' ) ) {
			array_unshift( $links, self::link( 'site' ) );
		}
		return $links;
	}

	/**
	 * A Settings link.
	 *
	 * @param string $scope site or network.
	 * @return string
	 */
	private static function link( string $scope ): string {
		return sprintf( '<a href="%1$s">%2$s</a>', esc_url( self::url( $scope ) ), esc_html__( 'Settings', 'ship-fixes' ) );
	}

	/**
	 * The page: a heading and the root the React screen fills.
	 *
	 * @return void
	 */
	public static function render(): void {
		echo '<div class="wrap"><h1>' . esc_html( get_admin_page_title() ) . '</h1>';
		echo '<div id="ship-fixes-settings"><noscript><p>' . esc_html__( 'This screen needs JavaScript.', 'ship-fixes' ) . '</p></noscript></div></div>';
	}

	/**
	 * Loads the screen's script and styles on its pages only.
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public static function enqueue( $hook_suffix ): void {
		$scope = self::$hooks[ (string) $hook_suffix ] ?? '';
		if ( '' === $scope ) {
			return;
		}
		$asset = self::asset( 'settings' );
		wp_enqueue_script( 'ship-fixes-settings', plugins_url( 'build/admin/settings.js', SHIP_FIXES_FILE ), $asset['dependencies'], $asset['version'], array( 'in_footer' => true ) );
		wp_set_script_translations( 'ship-fixes-settings', 'ship-fixes', SHIP_FIXES_DIR . '/languages' );
		wp_add_inline_script(
			'ship-fixes-settings',
			'window.shipFixesSettings = ' . wp_json_encode(
				array(
					'scope'       => $scope,
					'networkWide' => Settings::network_wide(),
					'fixes'       => Fixes::texts(),
				)
			) . ';',
			'before'
		);
		if ( is_readable( SHIP_FIXES_DIR . '/build/admin/settings.css' ) ) {
			wp_enqueue_style( 'ship-fixes-settings', plugins_url( 'build/admin/settings.css', SHIP_FIXES_FILE ), array( 'wp-components' ), $asset['version'] );
		}
	}

	/**
	 * Dependencies and version of a built admin script (build/admin/<name>.asset.php).
	 *
	 * @param string $name Entry name.
	 * @return array{dependencies: array<int, non-falsy-string>, version: string}
	 */
	public static function asset( string $name ): array {
		$file  = SHIP_FIXES_DIR . '/build/admin/' . $name . '.asset.php';
		$asset = is_readable( $file ) ? require $file : array();
		return array(
			'dependencies' => is_array( $asset ) && isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ? array_values( array_filter( array_map( 'strval', $asset['dependencies'] ) ) ) : array(),
			'version'      => is_array( $asset ) && isset( $asset['version'] ) ? (string) $asset['version'] : SHIP_FIXES_VERSION,
		);
	}
}

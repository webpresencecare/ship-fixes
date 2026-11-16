<?php
/**
 * REST routes for the settings screens:
 *
 * - `ship-fixes/v1/settings` (GET, POST): which fixes are on for the current site, for its administrators
 *   (`manage_options`), and only where the site may change them (Settings::site_may_change()).
 * - `ship-fixes/v1/network-settings` (GET, POST): the network's settings, for network administrators
 *   (`manage_network_options`), only when the plugin runs network-wide.
 *
 * Input is checked by each route's schema and again by Settings.
 *
 * @package Ship\Fixes
 */

declare( strict_types=1 );

namespace Ship\Fixes;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The plugin's REST routes.
 */
final class Rest {

	/** Namespace. */
	public const NAMESPACE = 'ship-fixes/v1';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Registers the routes.
	 *
	 * @return void
	 */
	public static function routes(): void {
		$flag  = array( 'type' => 'boolean' );
		$fixes = array_fill_keys( array_keys( Fixes::FIXES ), $flag );
		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_site' ),
					'permission_callback' => array( __CLASS__, 'can_manage_site' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'save_site' ),
					'permission_callback' => array( __CLASS__, 'can_manage_site' ),
					'args'                => $fixes,
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/network-settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_network' ),
					'permission_callback' => array( __CLASS__, 'can_manage_network' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'save_network' ),
					'permission_callback' => array( __CLASS__, 'can_manage_network' ),
					'args'                => $fixes + array( 'site_overrides' => $flag ),
				),
			)
		);
	}

	/**
	 * Permission callback of the site route.
	 *
	 * @return bool|WP_Error
	 */
	public static function can_manage_site() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		if ( ! Settings::site_may_change() ) {
			return new WP_Error( 'ship_fixes_network_decides', __( 'The network’s settings apply to this site.', 'ship-fixes' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Permission callback of the network route.
	 *
	 * @return bool|WP_Error
	 */
	public static function can_manage_network() {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return false;
		}
		if ( ! Settings::network_wide() ) {
			return new WP_Error( 'ship_fixes_not_network_wide', __( 'The plugin is not active for the whole network.', 'ship-fixes' ), array( 'status' => 404 ) );
		}
		return true;
	}

	/**
	 * GET: the fixes in force on the site.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_site(): WP_REST_Response {
		return new WP_REST_Response( Settings::get() );
	}

	/**
	 * POST: saves the site's settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function save_site( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( Settings::update_site( self::changes( $request, array_keys( Fixes::FIXES ) ) ) );
	}

	/**
	 * GET: the network's settings.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_network(): WP_REST_Response {
		return new WP_REST_Response( Settings::network_values() );
	}

	/**
	 * POST: saves the network's settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function save_network( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( Settings::update_network( self::changes( $request, array( ...array_keys( Fixes::FIXES ), 'site_overrides' ) ) ) );
	}

	/**
	 * The given values among the known keys.
	 *
	 * @param WP_REST_Request    $request Request.
	 * @param array<int, string> $keys    Known keys.
	 * @return array<string, mixed>
	 */
	private static function changes( WP_REST_Request $request, array $keys ): array {
		$changes = array();
		foreach ( $keys as $key ) {
			if ( $request->has_param( $key ) ) {
				$changes[ $key ] = $request->get_param( $key );
			}
		}
		return $changes;
	}
}

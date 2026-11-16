<?php
/**
 * Settings: which fixes are on (Fixes::FIXES), for every way the plugin can run:
 *
 * - On a single site, or activated on some sites of a network: each site decides (its Settings menu), kept in the site
 *   option OPTION. A fix nobody decided on has its default.
 * - Activated for the whole network: the network decides (Network Admin, Settings), kept in the network option
 *   NETWORK_OPTION, and says whether site administrators may decide for their own site. Where they may, what a site
 *   saved wins over the network's choice.
 *
 * @package Ship\Fixes
 */

declare( strict_types=1 );

namespace Ship\Fixes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads, checks and saves the settings.
 */
final class Settings {

	/** Each site's option: fix ID => bool, only the fixes the site decided on. */
	public const OPTION = 'ship_fixes_settings';

	/** The network's option (network-wide only): fix ID => bool, and `site_overrides`. */
	public const NETWORK_OPTION = 'ship_fixes_network';

	/**
	 * Whether the network decides: the plugin is activated for the whole network.
	 *
	 * @return bool
	 */
	public static function network_wide(): bool {
		if ( ! is_multisite() ) {
			return false;
		}
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active_for_network( plugin_basename( SHIP_FIXES_FILE ) );
	}

	/**
	 * The network's settings: every fix, and whether site administrators may decide for their site.
	 *
	 * @return array<string, bool>
	 */
	public static function network_values(): array {
		$stored = get_site_option( self::NETWORK_OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$values = array();
		foreach ( Fixes::FIXES as $id => $fix ) {
			$values[ $id ] = self::flag( $stored, $id, $fix['default'] );
		}
		$values['site_overrides'] = self::flag( $stored, 'site_overrides', true );
		return $values;
	}

	/**
	 * Whether the current site's administrators may change settings on their own site.
	 *
	 * @return bool
	 */
	public static function site_may_change(): bool {
		return ! self::network_wide() || self::network_values()['site_overrides'];
	}

	/**
	 * The fixes in force on the current site: fix ID => on or off.
	 *
	 * @return array<string, bool>
	 */
	public static function get(): array {
		$network = self::network_wide() ? self::network_values() : null;
		$site    = null === $network || $network['site_overrides'] ? self::site_stored() : array();
		$values  = array();
		foreach ( Fixes::FIXES as $id => $fix ) {
			$values[ $id ] = self::flag( $site, $id, null === $network ? $fix['default'] : $network[ $id ] );
		}
		return $values;
	}

	/**
	 * Saves the network's settings (unknown keys are ignored).
	 *
	 * @param array<string, mixed> $changes New values.
	 * @return array<string, bool> The saved values.
	 */
	public static function update_network( array $changes ): array {
		$values = self::network_values();
		foreach ( $values as $key => $value ) {
			$values[ $key ] = self::flag( $changes, $key, $value );
		}
		update_site_option( self::NETWORK_OPTION, $values );
		return $values;
	}

	/**
	 * Saves the current site's settings (unknown keys are ignored).
	 *
	 * @param array<string, mixed> $changes New values.
	 * @return array<string, bool> The fixes in force on the site.
	 */
	public static function update_site( array $changes ): array {
		$values = array();
		$stored = self::site_stored();
		foreach ( array_keys( Fixes::FIXES ) as $id ) {
			if ( array_key_exists( $id, $changes ) || array_key_exists( $id, $stored ) ) {
				$values[ $id ] = self::flag( $changes, $id, self::flag( $stored, $id, false ) );
			}
		}
		// Read on every request, so loaded with the site's other options.
		update_option( self::OPTION, $values, true );
		return self::get();
	}

	/**
	 * What the current site saved.
	 *
	 * @return array<mixed>
	 */
	private static function site_stored(): array {
		$stored = get_option( self::OPTION, array() );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * A yes or no value from an array.
	 *
	 * @param array<mixed> $values   Values.
	 * @param string       $key      Key.
	 * @param bool         $fallback Value when the key is missing.
	 * @return bool
	 */
	private static function flag( array $values, string $key, bool $fallback ): bool {
		return array_key_exists( $key, $values ) ? (bool) filter_var( $values[ $key ], FILTER_VALIDATE_BOOLEAN ) : $fallback;
	}
}

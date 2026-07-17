<?php
/**
 * Helper functions to perform various plugins and addons related actions.
 *
 * @since 1.8.2.2
 */

use WPForms\Requirements\Requirements;

/**
 * Check if addon met requirements.
 *
 * @since 1.8.2.2
 *
 * @param array $requirements Addon requirements.
 *
 * @return bool
 */
function wpforms_requirements( array $requirements ): bool {

	return Requirements::get_instance()->validate( $requirements );
}

/**
 * Determine if an addon is active and passed all requirements.
 *
 * @since 1.9.2
 *
 * @param string $addon_slug Addon slug without `wpforms-` prefix.
 *
 * @return bool
 */
function wpforms_is_addon_initialized( string $addon_slug ): bool {

	$basename = sprintf( 'wpforms-%1$s/wpforms-%1$s.php', $addon_slug );

	if ( is_multisite() ) {
		$active_plugins = (array) get_option( 'active_plugins', [] );

		if ( in_array( $basename, $active_plugins, true ) ) {
			return true;
		}
	}

	return Requirements::get_instance()->is_validated( $basename );
}

/**
 * Install (and optionally activate or upgrade) a WPForms addon or a WordPress.org plugin.
 *
 * The plugin main file is the only identifier, and addons are plugins under the hood. The
 * `WPForms\Helpers\Plugin` service detects addons by their `wpforms-{slug}` directory,
 * installs them from the license API, and refuses any whose tier is above the site's
 * license; every other plugin comes from WordPress.org. A plugin already present on disk
 * is upgraded (when `$upgrade`) and activated without being re-downloaded. By default the
 * plugin is installed, upgraded to the latest release when already present, AND activated.
 *
 * @since 2.0.0
 *
 * @param string $plugin_file Plugin main file relative to `WP_PLUGIN_DIR`: a WordPress.org
 *                            plugin (e.g. `contact-form-7/wp-contact-form-7.php`) or a
 *                            WPForms addon (e.g. `wpforms-stripe/wpforms-stripe.php`).
 * @param bool   $activate    Whether to activate the plugin after install. Default true.
 * @param bool   $upgrade     Whether to upgrade an already-installed plugin to the latest release. Default true.
 *
 * @return array|WP_Error {
 *     Result array when the plugin is installed or already present, `WP_Error` when the
 *     plugin service is unavailable, the addon's license level is insufficient, or a
 *     genuine install fails.
 *
 *     @type string $plugin       Plugin main file.
 *     @type bool   $is_installed Whether the plugin is present on disk.
 *     @type bool   $is_active    Whether the plugin is now active.
 * }
 */
function wpforms_install_plugin( string $plugin_file, bool $activate = true, bool $upgrade = true ) {

	$plugin_obj = wpforms()->obj( 'plugin' );

	if ( $plugin_obj === null ) {
		return new WP_Error(
			'wpforms_install_plugin_unavailable',
			esc_html__( 'Could not install the plugin. Please download and install it manually.', 'wpforms-lite' )
		);
	}

	$installed      = $plugin_obj->install( $plugin_file );
	$already_exists = is_wp_error( $installed ) && $installed->get_error_code() === 'wpforms_install_plugin_exists';

	if ( $upgrade && $already_exists ) {
		$installed = $plugin_obj->upgrade( $plugin_file );
	}

	if ( ! $already_exists && is_wp_error( $installed ) ) {
		return $installed;
	}

	$response = [
		'plugin'       => $plugin_file,
		'is_installed' => true,
		'is_active'    => false,
	];

	if ( ! $activate ) {
		return $response;
	}

	$activated = $plugin_obj->activate( $plugin_file );

	if ( is_wp_error( $activated ) ) {
		return $activated;
	}

	$response['is_active'] = true;

	return $response;
}

/**
 * Check addon requirements and activate addon or plugin.
 *
 * @since 1.8.4
 * @since 1.9.2 Keep addons active even if they don't meet requirements.
 *
 * @param string $plugin Path to the plugin file relative to the plugins' directory.
 *
 * @return null|WP_Error Null on success, WP_Error on invalid file.
 */
function wpforms_activate_plugin( string $plugin ) {

	$activate = activate_plugin( $plugin );

	if ( is_wp_error( $activate ) ) {
		return $activate;
	}

	$requirements = Requirements::get_instance();

	if ( $requirements->is_validated( $plugin ) ) {
		return null;
	}

	return new WP_Error( 'wpforms_addon_incompatible', $requirements->get_notice( $plugin ) );
}

/**
 * Compares two "PHP-standardized" version number strings.
 *
 * Removes any "-RCn", "-beta" from version numbers first.
 *
 * @since 1.9.4
 *
 * @param string $version1 Version number.
 * @param string $version2 Version number.
 * @param string $operator Comparison operator.
 *
 * @return bool
 */
function wpforms_version_compare( $version1, $version2, $operator ): bool {

	// If the version is not a string, return false.
	if ( ! is_string( $version1 ) || ! is_string( $version2 ) ) {
		return false;
	}

	// Strip dash and anything after it.
	$clean_version_number = function ( $version ) {
		return preg_replace( '/-.+/', '', $version );
	};

	return version_compare(
		$clean_version_number( $version1 ),
		$clean_version_number( $version2 ),
		$operator
	);
}

<?php

namespace WPForms\Helpers;

use Automatic_Upgrader_Skin;
use WP_Error;

/**
 * Source of truth for installing, activating, and upgrading plugins and WPForms addons.
 *
 * A plugin is identified by its main file relative to `WP_PLUGIN_DIR`
 * (e.g. `contact-form-7/wp-contact-form-7.php`). WPForms addons are plugins too: they
 * live in a `wpforms-{slug}` directory and are detected by that prefix, so their package
 * comes from the license API (gated by the site's license level) while every other plugin
 * downloads from WordPress.org. "Already installed" is a check for the file on disk; a
 * plugin that is already present is never re-downloaded, only activated or upgraded.
 *
 * @since 2.0.0
 */
class Plugin {

	/**
	 * Cache of plugin header data, keyed by plugin basename.
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	private static $plugin_data_cache = [];

	/**
	 * Install a plugin or WPForms addon by its main file, downloading it when missing.
	 *
	 * Runs the WP installer through `PluginSilentUpgrader` with a neutral upgrader skin so
	 * the call works in AJAX, REST, CLI, and plain PHP contexts alike. Addons are detected
	 * by the `wpforms-` directory prefix and resolved against the license API: one whose
	 * tier is above the site's license is refused. A plugin already on disk is reported as
	 * a `wpforms_install_plugin_exists` error (with the plugin file as error data) instead
	 * of being re-downloaded, so the caller can decide whether to activate or upgrade it.
	 *
	 * @since 2.0.0
	 *
	 * @param string $plugin_file Plugin main file relative to `WP_PLUGIN_DIR`: a WordPress.org
	 *                            plugin (e.g. `contact-form-7/wp-contact-form-7.php`) or a
	 *                            WPForms addon (e.g. `wpforms-stripe/wpforms-stripe.php`).
	 *
	 * @return array|WP_Error {
	 *     Install result on success, `WP_Error` on failure or when already present
	 *     (error code `wpforms_install_plugin_exists`).
	 *
	 *     @type string $plugin Plugin main file.
	 * }
	 */
	public function install( string $plugin_file ) { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks, Generic.Metrics.CyclomaticComplexity.TooHigh

		$generic_error = esc_html__( 'Could not install the plugin. Please download and install it manually.', 'wpforms-lite' );

		if ( $plugin_file === '' ) {
			return new WP_Error( 'wpforms_install_plugin_empty', $generic_error );
		}

		// Already on disk: report it so the caller can activate or upgrade it instead of re-downloading.
		if ( $this->is_installed( $plugin_file ) ) {
			return new WP_Error(
				'wpforms_install_plugin_exists',
				esc_html__( 'The plugin is already installed.', 'wpforms-lite' ),
				$plugin_file
			);
		}

		$is_addon = $this->is_addon( $plugin_file );

		if ( ! wpforms_can_install( $is_addon ? 'addon' : 'plugin' ) ) {
			return new WP_Error( 'wpforms_install_plugin_forbidden', $generic_error );
		}

		// Addons are gated by the site's license level.
		if ( $is_addon && ! $this->has_addon_access( $plugin_file ) ) {
			return new WP_Error(
				'wpforms_install_plugin_access',
				esc_html__( 'Your license level does not include this addon. Please upgrade your plan to install it.', 'wpforms-lite' )
			);
		}

		$download_url = $this->get_download_url( $plugin_file );

		if ( $download_url === '' ) {
			return new WP_Error( 'wpforms_install_plugin_url', $generic_error );
		}

		$installed = $this->install_from_url( $plugin_file, $download_url );

		if ( is_wp_error( $installed ) ) {
			return $installed;
		}

		return [ 'plugin' => $plugin_file ];
	}

	/**
	 * Install a plugin from an explicit download URL.
	 *
	 * For callers that already hold a resolved package URL (e.g. the Setup Wizard's
	 * Lite-to-Pro upgrade, where the license API returns the URL). The catalog/license
	 * checks `install()` runs are intentionally skipped: the caller-supplied URL
	 * already encodes the access decision. The install-capability gate still applies,
	 * so a `public` entry point can never install without the right permission.
	 *
	 * @since 2.0.0
	 *
	 * @param string $plugin_file  Plugin main file relative to `WP_PLUGIN_DIR`.
	 * @param string $download_url Resolved package URL.
	 *
	 * @return true|WP_Error
	 */
	public function install_from_url( string $plugin_file, string $download_url ) {

		$generic_error = esc_html__( 'Could not install the plugin. Please download and install it manually.', 'wpforms-lite' );
		$is_addon      = $this->is_addon( $plugin_file );

		if ( ! wpforms_can_install( $is_addon ? 'addon' : 'plugin' ) ) {
			return new WP_Error( 'wpforms_install_plugin_forbidden', $generic_error );
		}

		if ( ! $this->prepare_filesystem() ) {
			return new WP_Error( 'wpforms_install_plugin_filesystem', $generic_error );
		}

		$installer = new PluginSilentUpgrader( new Automatic_Upgrader_Skin() );

		if ( ! method_exists( $installer, 'install' ) ) {
			return new WP_Error( 'wpforms_install_plugin_installer', $generic_error );
		}

		$installed = $installer->install( $download_url );

		if ( is_wp_error( $installed ) ) {
			return $installed;
		}

		wp_cache_flush();

		if ( ! $this->is_installed( $plugin_file ) ) {
			return new WP_Error( 'wpforms_install_plugin_failed', $generic_error );
		}

		return true;
	}

	/**
	 * Activate an installed plugin by its main file.
	 *
	 * @since 2.0.0
	 *
	 * @param string $plugin_file Plugin main file (e.g. `wp-mail-smtp/wp_mail_smtp.php`).
	 *
	 * @return true|WP_Error True on success, `WP_Error` on failure.
	 */
	public function activate( string $plugin_file ) {

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return new WP_Error(
				'wpforms_activate_plugin_forbidden',
				esc_html__( 'Plugin activation is disabled for you on this site.', 'wpforms-lite' )
			);
		}

		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$activated = activate_plugin( $plugin_file );

		if ( is_wp_error( $activated ) ) {
			return $activated;
		}

		/** This action is documented in includes/admin/ajax-actions.php. */
		do_action( 'wpforms_plugin_activated', $plugin_file ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		return true;
	}

	/**
	 * Upgrade an installed plugin or WPForms addon to its latest release.
	 *
	 * Overwrites the plugin on disk with the latest package by clearing the destination
	 * first, but only when a genuinely newer version is available: when the installed build
	 * is the same as or newer than the available one (e.g. a patched or beta build), the
	 * call is a no-op that returns success without downgrading. Addons are detected by the
	 * `wpforms-` directory prefix and pull the latest license build from the license API
	 * (gated by the site's license level); every other plugin pulls the latest
	 * WordPress.org build.
	 *
	 * @since 2.0.0
	 *
	 * @param string $plugin_file Plugin main file relative to `WP_PLUGIN_DIR`: a WordPress.org
	 *                            plugin (e.g. `contact-form-7/wp-contact-form-7.php`) or a
	 *                            WPForms addon (e.g. `wpforms-stripe/wpforms-stripe.php`).
	 *
	 * @return array|WP_Error {
	 *     Upgrade result on success, `WP_Error` on failure.
	 *
	 *     @type string $plugin Plugin main file.
	 * }
	 */
	public function upgrade( string $plugin_file ) { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		$generic_error = esc_html__( 'Could not upgrade the plugin. Please update it manually.', 'wpforms-lite' );

		if ( $plugin_file === '' || ! $this->is_installed( $plugin_file ) ) {
			return new WP_Error( 'wpforms_upgrade_plugin_missing', $generic_error );
		}

		if ( ! current_user_can( 'update_plugins' ) || ! wp_is_file_mod_allowed( 'wpforms_can_install' ) ) {
			return new WP_Error( 'wpforms_upgrade_plugin_forbidden', $generic_error );
		}

		// Addons are gated by the site's license level.
		if ( $this->is_addon( $plugin_file ) && ! $this->has_addon_access( $plugin_file ) ) {
			return new WP_Error(
				'wpforms_upgrade_plugin_access',
				esc_html__( 'Your license level does not include this addon. Please upgrade your plan to update it.', 'wpforms-lite' )
			);
		}

		// Never downgrade: skip the overwrite unless a genuinely newer version is available.
		if ( ! $this->has_available_update( $plugin_file ) ) {
			return [ 'plugin' => $plugin_file ];
		}

		$download_url = $this->get_download_url( $plugin_file );

		if ( $download_url === '' ) {
			return new WP_Error( 'wpforms_upgrade_plugin_url', $generic_error );
		}

		if ( ! $this->prepare_filesystem() ) {
			return new WP_Error( 'wpforms_upgrade_plugin_filesystem', $generic_error );
		}

		$upgrader = new PluginSilentUpgrader( new Automatic_Upgrader_Skin() );

		if ( ! method_exists( $upgrader, 'run' ) ) {
			return new WP_Error( 'wpforms_upgrade_plugin_upgrader', $generic_error );
		}

		$result = $upgrader->run(
			[
				'package'                     => $download_url,
				'destination'                 => WP_PLUGIN_DIR,
				'clear_destination'           => true,
				'abort_if_destination_exists' => false,
				'clear_working'               => true,
				'hook_extra'                  => [
					'type'   => 'plugin',
					'action' => 'update',
					'plugin' => $plugin_file,
				],
			]
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( ! $result ) {
			return new WP_Error( 'wpforms_upgrade_plugin_failed', $generic_error );
		}

		wp_cache_flush();

		return [ 'plugin' => $plugin_file ];
	}

	/**
	 * Whether a plugin basename belongs to a genuine WPForms addon.
	 *
	 * Stricter than {@see self::is_addon()}: on top of the `wpforms-` directory
	 * prefix it excludes the core plugin and reads the installed plugin header to
	 * confirm the `WPForms` author, ruling out third-party forks that reuse the
	 * prefix. It therefore needs the plugin present on disk, so it detects
	 * already-installed addons — use `is_addon()` to route a not-yet-installed
	 * download. Shared so the addon-detection logic lives in one place across both
	 * editions instead of being duplicated per consumer.
	 *
	 * @since 2.0.0
	 *
	 * @param string $plugin Plugin basename (e.g. `wpforms-stripe/wpforms-stripe.php`).
	 *
	 * @return bool
	 */
	public static function is_wpforms_addon( string $plugin ): bool {

		// The core plugin (Lite or Pro) is never an addon.
		if ( $plugin === 'wpforms/wpforms.php' || strpos( $plugin, 'wpforms-' ) !== 0 ) {
			return false;
		}

		// Forks reuse the `wpforms-` prefix, so confirm the author from the header.
		$author = strtolower( (string) ( self::get_plugin_data( $plugin )['AuthorName'] ?? '' ) );

		return $author === 'wpforms';
	}

	/**
	 * Read and cache a plugin's header data by basename.
	 *
	 * @since 2.0.0
	 *
	 * @param string $plugin Plugin basename (e.g. `wpforms-stripe/wpforms-stripe.php`).
	 *
	 * @return array Plugin header data, or an empty array when the file is absent.
	 */
	public static function get_plugin_data( string $plugin ): array {

		if ( isset( self::$plugin_data_cache[ $plugin ] ) ) {
			return self::$plugin_data_cache[ $plugin ];
		}

		$plugin_file = trailingslashit( WP_PLUGIN_DIR ) . $plugin;

		if ( ! file_exists( $plugin_file ) ) {
			self::$plugin_data_cache[ $plugin ] = [];

			return [];
		}

		self::ensure_plugin_functions();

		self::$plugin_data_cache[ $plugin ] = get_plugin_data( $plugin_file, false, false );

		return self::$plugin_data_cache[ $plugin ];
	}

	/**
	 * Ensure the WordPress plugin admin functions are loaded.
	 *
	 * Available in admin requests, but not in REST/cron contexts where the wizard
	 * and update routines also run.
	 *
	 * @since 2.0.0
	 */
	public static function ensure_plugin_functions(): void {

		if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}

	/**
	 * Prepare the WordPress filesystem and upgrader prerequisites.
	 *
	 * Loads the upgrader classes, resolves filesystem credentials without emitting
	 * the credentials form, and prevents WordPress from fetching translations during
	 * the run (which would break JS output).
	 *
	 * @since 2.0.0
	 *
	 * @return bool Whether the filesystem is ready for an install or upgrade.
	 */
	private function prepare_filesystem(): bool { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// The core upgrader calls get_plugin_data() and wp_clean_plugins_cache() mid-run,
		// and neither is loaded on REST/cron requests.
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! class_exists( 'Plugin_Upgrader', false ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		// Suppress any output the credentials form might emit when run outside an admin screen.
		ob_start();
		$credentials = request_filesystem_credentials( '', '', false, false );

		ob_end_clean();

		if ( $credentials === false || ! WP_Filesystem( $credentials ) ) {
			return false;
		}

		// Do not allow WordPress to search/download translations, as this will break JS output.
		remove_action( 'upgrader_process_complete', [ 'Language_Pack_Upgrader', 'async_upgrade' ], 20 );

		return true;
	}

	/**
	 * Whether a plugin main file belongs to a WPForms addon.
	 *
	 * Addons live in a `wpforms-{slug}` directory, so the directory prefix distinguishes
	 * them from WordPress.org plugins.
	 *
	 * @since 2.0.0
	 *
	 * @param string $plugin_file Plugin main file relative to `WP_PLUGIN_DIR`.
	 *
	 * @return bool
	 */
	private function is_addon( string $plugin_file ): bool {

		return strpos( dirname( $plugin_file ), 'wpforms-' ) === 0;
	}

	/**
	 * Whether the site's license level grants access to an addon.
	 *
	 * Reads the `plugin_allow` flag the Addons handler computes for the addon (its
	 * `has_access()` result, which maps `agency`/`ultimate` to `elite`). Returns false on
	 * Lite and whenever the handler is unavailable, so addons fail closed.
	 *
	 * @since 2.0.0
	 *
	 * @param string $plugin_file Addon main file relative to `WP_PLUGIN_DIR`.
	 *
	 * @return bool
	 */
	private function has_addon_access( string $plugin_file ): bool {

		$addons = wpforms()->obj( 'addons' );

		if ( ! $addons ) {
			return false;
		}

		$addon = (array) $addons->get_addon( dirname( $plugin_file ) );

		return ! empty( $addon['plugin_allow'] );
	}

	/**
	 * Whether a genuinely newer version of the plugin is available to install.
	 *
	 * Both WordPress core and the WPForms addon updater list a plugin in the
	 * `update_plugins` transient's `response` only when the available version is newer than
	 * the installed one, so a higher (e.g. patched or beta) build is absent from it. The
	 * available version is re-compared against the build on disk as well, so a stale
	 * transient entry can never trigger a downgrade. When the transient has never been
	 * populated (a fresh site, or a REST/cron context) the update list is refreshed once,
	 * so a genuine update is not mistaken for "already current".
	 *
	 * @since 2.0.0
	 *
	 * @param string $plugin_file Plugin main file relative to `WP_PLUGIN_DIR`.
	 *
	 * @return bool
	 */
	private function has_available_update( string $plugin_file ): bool {

		$updates = get_site_transient( 'update_plugins' );

		// An absent cache means "never checked", not "nothing to update": populate it once.
		// A populated cache that simply omits the plugin legitimately means it is current.
		if ( ! is_object( $updates ) || ! isset( $updates->last_checked ) ) {
			wp_update_plugins();

			$updates = get_site_transient( 'update_plugins' );
		}

		if ( ! is_object( $updates ) || ! isset( $updates->response[ $plugin_file ]->new_version ) ) {
			return false;
		}

		$installed = $this->get_installed_version( $plugin_file );

		return $installed === '' || wpforms_version_compare( (string) $updates->response[ $plugin_file ]->new_version, $installed, '>' );
	}

	/**
	 * Read the installed version of a plugin from its header on disk.
	 *
	 * @since 2.0.0
	 *
	 * @param string $plugin_file Plugin main file relative to `WP_PLUGIN_DIR`.
	 *
	 * @return string Version string, or an empty string when it cannot be determined.
	 */
	private function get_installed_version( string $plugin_file ): string {

		self::ensure_plugin_functions();

		return get_plugins()[ $plugin_file ]['Version'] ?? '';
	}

	/**
	 * Whether a plugin's main file is already present under `WP_PLUGIN_DIR`.
	 *
	 * @since 2.0.0
	 *
	 * @param string $plugin_file Plugin main file relative to `WP_PLUGIN_DIR`.
	 *
	 * @return bool
	 */
	private function is_installed( string $plugin_file ): bool {

		return file_exists( trailingslashit( WP_PLUGIN_DIR ) . $plugin_file );
	}

	/**
	 * Build the download URL for a plugin main file.
	 *
	 * For a WPForms addon the license-keyed package URL comes from the Addons handler
	 * (empty on Lite or for an unknown/inaccessible addon). For every other plugin the
	 * directory is the WordPress.org slug, so `contact-form-7/wp-contact-form-7.php`
	 * resolves to `https://downloads.wordpress.org/plugin/contact-form-7.zip`.
	 *
	 * @since 2.0.0
	 *
	 * @param string $plugin_file Plugin main file relative to `WP_PLUGIN_DIR`.
	 *
	 * @return string
	 */
	private function get_download_url( string $plugin_file ): string {

		$slug = dirname( $plugin_file );

		if ( ! $this->is_addon( $plugin_file ) ) {
			return sprintf( 'https://downloads.wordpress.org/plugin/%s.zip', $slug );
		}

		$addons = wpforms()->obj( 'addons' );

		return $addons && method_exists( $addons, 'get_url' ) ? (string) $addons->get_url( $slug ) : '';
	}
}

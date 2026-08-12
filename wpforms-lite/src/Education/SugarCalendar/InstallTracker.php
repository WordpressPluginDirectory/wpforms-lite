<?php

namespace WPForms\Education\SugarCalendar;

use WP_Upgrader;

/**
 * Sugar Calendar install attribution and activation-redirect suppression.
 *
 * Both options are consumed by the Sugar Calendar plugin itself (see its
 * Admin\Area::OPTION_REDIRECT and source tracking): the redirect flag must
 * exist BEFORE activate_plugin() runs inside the wpforms_install_addon AJAX
 * request, and the source option is written on the wpforms_plugin_activated
 * hook fired after activation (includes/admin/ajax-actions.php).
 *
 * @since 2.0.0.3
 */
class InstallTracker {

	/**
	 * Sugar Calendar option key — install source.
	 *
	 * @since 2.0.0.3
	 */
	const SOURCE_OPTION = 'sugar_calendar_source';

	/**
	 * Sugar Calendar option key — suppress the post-activation setup wizard redirect.
	 *
	 * @since 2.0.0.3
	 */
	const PREVENT_REDIRECT_OPTION = 'sugar_calendar_prevent_redirect';

	/**
	 * Own option key — marks the redirect flag as written by us.
	 *
	 * @since 2.0.0.3
	 */
	const OWN_REDIRECT_OPTION = 'wpforms_sugar_calendar_prevent_redirect';

	/**
	 * Whether the current request is a Sugar Calendar install started from WPForms.
	 *
	 * @since 2.0.0.3
	 *
	 * @var bool
	 */
	private $is_own_install = false;

	/**
	 * Whether this request created the redirect flag.
	 *
	 * @since 2.0.0.3
	 *
	 * @var bool
	 */
	private $is_redirect_prevented = false;

	/**
	 * Whether Sugar Calendar was activated in this request.
	 *
	 * @since 2.0.0.3
	 *
	 * @var bool
	 */
	private $is_activated = false;

	/**
	 * Init.
	 *
	 * @since 2.0.0.3
	 */
	public function init() {

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0.0.3
	 */
	private function hooks() {

		// Priority 5: run before the wpforms_install_addon() handler (default 10) in the same AJAX request.
		add_action( 'wp_ajax_wpforms_install_addon', [ $this, 'maybe_watch_install' ], 5 );
		add_action( 'upgrader_process_complete', [ $this, 'maybe_prevent_redirect' ], 10, 2 );
		add_action( 'wpforms_plugin_activated', [ $this, 'maybe_record_source' ] );
		add_action( 'shutdown', [ $this, 'maybe_revert_prevent_redirect' ] );
		add_action( 'deleted_plugin', [ $this, 'maybe_clean_prevent_redirect' ], 10, 2 );
	}

	/**
	 * Mark the current AJAX request as our own Sugar Calendar install.
	 *
	 * Only arms the redirect suppression: the option itself is written once the files
	 * are in place, so a failed download or unzip does not leave the flag behind and
	 * silently break the wizard of a later manual install.
	 *
	 * @since 2.0.0.3
	 */
	public function maybe_watch_install() {

		if ( ! check_ajax_referer( 'wpforms-admin', 'nonce', false ) || ! wpforms_can_install( 'plugin' ) ) {
			return;
		}

		$plugin_url = isset( $_POST['plugin'] ) ? esc_url_raw( wp_unslash( $_POST['plugin'] ) ) : '';

		$this->is_own_install = $plugin_url === Helper::INSTALL_ZIP_URL;
	}

	/**
	 * Suppress the Sugar Calendar setup wizard redirect once the plugin files are installed.
	 *
	 * Runs inside PluginSilentUpgrader::run(), after the package is installed and before
	 * the AJAX handler activates the plugin. Limited to our own request, so a manual
	 * install from the Plugins page keeps its welcome screen.
	 *
	 * @since 2.0.0.3
	 *
	 * @param WP_Upgrader $upgrader   Upgrader instance.
	 * @param array       $hook_extra Extra arguments passed to hooked filters.
	 */
	public function maybe_prevent_redirect( $upgrader, $hook_extra ) { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks -- the landing page default has to be removed right before the write.

		if ( ! $this->is_own_install ) {
			return;
		}

		if ( ( $hook_extra['type'] ?? '' ) !== 'plugin' || ( $hook_extra['action'] ?? '' ) !== 'install' ) {
			return;
		}

		// The hook also fires when the package failed to install.
		$destination = is_wp_error( $upgrader->result ?? null ) ? '' : ( $upgrader->result['destination_name'] ?? '' );

		if ( $destination !== Helper::SLUG ) {
			return;
		}

		// The Sugar Calendar landing page substitutes a truthy default for this option during AJAX
		// (\WPForms\Admin\Pages\SugarCalendar::hooks()), which would make this write a silent no-op.
		remove_filter( 'default_option_' . self::PREVENT_REDIRECT_OPTION, '__return_true' );

		// The return value also tells us the flag is ours, so a value stored by somebody
		// else is never removed on shutdown.
		$this->is_redirect_prevented = update_option( self::PREVENT_REDIRECT_OPTION, true );

		if ( $this->is_redirect_prevented ) {
			// Ownership marker, never autoloaded: it is read only when a plugin is deleted.
			update_option( self::OWN_REDIRECT_OPTION, true, false );
		}
	}

	/**
	 * Record WPForms as the install source when Sugar Calendar Lite is activated.
	 *
	 * @since 2.0.0.3
	 *
	 * @param string $plugin_basename Path to the activated plugin file relative to the plugins directory.
	 */
	public function maybe_record_source( $plugin_basename ) {

		if ( ! is_string( $plugin_basename ) || strpos( $plugin_basename, Helper::SLUG . '/' ) !== 0 ) {
			return;
		}

		$this->is_activated = true;

		update_option( self::SOURCE_OPTION, 'wpforms' );
	}

	/**
	 * Remove the redirect flag when our install never reached activation.
	 *
	 * The flag is written before activate_plugin() because Sugar Calendar reads it while
	 * activating, but the request can still end without activation: a WP_Error from
	 * activate_plugin(), or a user who may install plugins but not activate them. Leaving
	 * the flag behind would silently skip the wizard of a later manual activation.
	 *
	 * @since 2.0.0.3
	 */
	public function maybe_revert_prevent_redirect(): void {

		if ( ! $this->is_redirect_prevented || $this->is_activated ) {
			return;
		}

		delete_option( self::PREVENT_REDIRECT_OPTION );
		delete_option( self::OWN_REDIRECT_OPTION );
	}

	/**
	 * Drop our redirect flag when Sugar Calendar is deleted.
	 *
	 * Sugar Calendar has no uninstall routine and treats the option as a permanent opt-out,
	 * so the flag we wrote for one install would keep suppressing the setup wizard of any
	 * later manual install. A flag stored by somebody else carries no ownership marker and stays.
	 *
	 * @since 2.0.0.3
	 *
	 * @param string $plugin_file Path to the deleted plugin file relative to the plugins directory.
	 * @param bool   $deleted     Whether the deletion succeeded.
	 */
	public function maybe_clean_prevent_redirect( $plugin_file, $deleted = true ): void {

		$folder = strtok( (string) $plugin_file, '/' );

		if ( ! $deleted || ( $folder !== Helper::SLUG && $folder !== Helper::PRO_SLUG ) ) {
			return;
		}

		if ( ! get_option( self::OWN_REDIRECT_OPTION ) ) {
			return;
		}

		// The hook fires before delete_plugins() clears the plugins cache, and it fires once per
		// plugin, so both the cache and the resolved basename are refreshed before looking for a
		// remaining copy: Lite can sit next to Pro, which still needs the flag.
		wp_clean_plugins_cache( false );

		if ( Helper::is_installed( true ) ) {
			return;
		}

		delete_option( self::PREVENT_REDIRECT_OPTION );
		delete_option( self::OWN_REDIRECT_OPTION );
	}
}

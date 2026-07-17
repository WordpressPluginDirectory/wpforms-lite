<?php

namespace WPForms\Lite\SetupWizard;

use WP_Error;
use WPForms\SetupWizard\RestApi as BaseRestApi;

/**
 * Setup Wizard REST API (Lite).
 *
 * Completes license activation by upgrading the site from Lite to Pro: it
 * resolves the license-keyed Pro package, downloads it, and swaps the active
 * plugin. Mirrors the proven install flow in
 * {@see \WPForms\Lite\Admin\Connect::process()}.
 *
 * @since 2.0.0
 */
class RestApi extends BaseRestApi {

	/**
	 * WPForms Pro plugin basename: the swap target.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private const PRO_PLUGIN = 'wpforms/wpforms.php';

	/**
	 * Validate a key against the license API, then upgrade Lite to Pro.
	 *
	 * The validated key is persisted before the install runs, so a valid key is
	 * never lost if the Pro install fails.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key License key.
	 *
	 * @return array|WP_Error
	 */
	protected function activate_license( string $key ) {

		if ( ! wpforms_can_install( 'plugin' ) ) {
			return new WP_Error(
				'wpforms_setup_wizard_license_forbidden',
				esc_html__( 'You are not allowed to install plugins on this site.', 'wpforms-lite' ),
				[ 'status' => 403 ]
			);
		}

		$response = parent::activate_license( $key );

		if ( is_wp_error( $response ) || empty( $response['success'] ) ) {
			return $response;
		}

		// Handoff the Pro bootstrap consumption to activate the license after the swap.
		update_option( 'wpforms_connect', $key );

		$package_url = $this->get_pro_package_url( $key );

		if ( $package_url === '' ) {
			return new WP_Error(
				'wpforms_setup_wizard_license_package',
				esc_html__( 'Could not retrieve the WPForms Pro download. Please try again later.', 'wpforms-lite' ),
				[ 'status' => 502 ]
			);
		}

		$installed = $this->install_pro( $package_url );

		if ( is_wp_error( $installed ) ) {
			return $installed;
		}

		$response['message'] = esc_html__( 'WPForms Pro installed and activated.', 'wpforms-lite' );

		return $response;
	}

	/**
	 * Resolve the license-keyed WPForms Pro download URL.
	 *
	 * Tries the current `get-plugin-updates` action (slug-keyed `package`),
	 * falling back to the legacy `get-plugin-info` action (`download_link`).
	 *
	 * @since 2.0.0
	 *
	 * @param string $key License key.
	 *
	 * @return string Download URL, or an empty string when unavailable.
	 */
	private function get_pro_package_url( string $key ): string {

		$updates = $this->license_api_request( 'get-plugin-updates', $key, [ 'tgm-updater-plugins' => 'wpforms' ] );
		$package = $this->extract_package_url( $updates );

		if ( $package !== '' ) {
			return $package;
		}

		$info = $this->license_api_request( 'get-plugin-info', $key, [ 'tgm-updater-plugin' => 'wpforms' ] );

		if ( $info !== null && ! empty( $info['download_link'] ) ) {
			return (string) $info['download_link'];
		}

		return '';
	}

	/**
	 * Pull a `package` URL out of a plugin-updates response.
	 *
	 * Handles both the flat shape (`{ package: … }`) and the slug-keyed shape
	 * (`{ "wpforms-pro": { package: … } }`).
	 *
	 * @since 2.0.0
	 *
	 * @param array|null $updates Decoded plugin-updates response.
	 *
	 * @return string Package URL, or an empty string when absent.
	 */
	private function extract_package_url( ?array $updates ): string {

		if ( $updates === null ) {
			return '';
		}

		if ( ! empty( $updates['package'] ) ) {
			return (string) $updates['package'];
		}

		foreach ( [ 'wpforms-pro', 'wpforms' ] as $slug ) {
			if ( is_array( $updates[ $slug ] ?? null ) && ! empty( $updates[ $slug ]['package'] ) ) {
				return (string) $updates[ $slug ]['package'];
			}
		}

		return '';
	}

	/**
	 * Install WPForms Pro and swap it in for Lite.
	 *
	 * Filesystem prep and the silent install itself are delegated to the shared
	 * `Helpers\Plugin` service so both call sites stay in lockstep; this method
	 * only owns the Pro-specific error message and the Lite-to-Pro swap.
	 *
	 * @since 2.0.0
	 *
	 * @param string $package_url WPForms Pro download URL.
	 *
	 * @return true|WP_Error
	 */
	private function install_pro( string $package_url ) {

		$generic_error = esc_html__( 'There was an error while installing WPForms Pro. Please download it from wpforms.com and install it manually.', 'wpforms-lite' );

		// Download and install only when Pro is not already on disk.
		if ( ! $this->is_pro_installed() ) {
			$plugin = wpforms()->obj( 'plugin' );

			if ( $plugin === null ) {
				return new WP_Error( 'wpforms_setup_wizard_license_installer', $generic_error, [ 'status' => 500 ] );
			}

			$installed = $plugin->install_from_url( self::PRO_PLUGIN, $package_url );

			if ( is_wp_error( $installed ) ) {
				return new WP_Error( 'wpforms_setup_wizard_license_install_failed', $generic_error, [ 'status' => 500 ] );
			}
		}

		return $this->swap_to_pro( $generic_error );
	}

	/**
	 * Swap the active plugin from Lite to Pro.
	 *
	 * Deactivates Lite (option-only; Lite's loaded code serves the rest of this
	 * request) and activates Pro silently. Silent activation is mandatory: WP's
	 * `plugin_sandbox_scrape()` includes the Pro main file, and the
	 * `function_exists( 'wpforms' )` guard in `wpforms.php` makes it bail without
	 * redeclaring. Pro bootstraps on the next request.
	 *
	 * @since 2.0.0
	 *
	 * @param string $generic_error Fallback user-facing error message.
	 *
	 * @return true|WP_Error
	 */
	private function swap_to_pro( string $generic_error ) {

		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$lite_plugin = plugin_basename( WPFORMS_PLUGIN_FILE );

		deactivate_plugins( $lite_plugin );

		/** This action is documented in wpforms.php. */
		do_action( 'wpforms_plugin_deactivated', $lite_plugin ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName, WPForms.Comments.PHPDocHooks.RequiredHookDocumentation

		$activated = activate_plugin( self::PRO_PLUGIN, '', false, true );

		if ( is_wp_error( $activated ) ) {
			// Roll back so the site is never left with neither version active.
			activate_plugin( $lite_plugin, '', false, true );

			return new WP_Error( 'wpforms_setup_wizard_license_activation_failed', $generic_error, [ 'status' => 500 ] );
		}

		// Trigger the Pro installation routine (and license handoff) on next load.
		add_option( 'wpforms_install', 1 );

		return true;
	}

	/**
	 * Whether WPForms Pro is already present under the plugins directory.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	private function is_pro_installed(): bool {

		return file_exists( trailingslashit( WP_PLUGIN_DIR ) . self::PRO_PLUGIN );
	}
}

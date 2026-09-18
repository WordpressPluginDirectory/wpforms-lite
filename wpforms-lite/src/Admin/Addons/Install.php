<?php

namespace WPForms\Admin\Addons;

use WPForms\SetupWizard\Service\PluginInstaller;

/**
 * Shared addon/plugin install AJAX endpoint.
 *
 * Installs a WPForms addon or a recommended WordPress.org plugin in place, reusing the
 * Setup Wizard's install gateway. Addon license access is enforced per plugin by that
 * gateway, so the endpoint gates only on the install capability. Used by the Setup
 * Checklist page and the Dashboard FeaturesAddons widget.
 *
 * @since 2.0.2
 */
class Install {

	/**
	 * Install AJAX action (also used as its nonce action).
	 *
	 * @since 2.0.2
	 *
	 * @var string
	 */
	public const ACTION = 'wpforms_addons_install';

	/**
	 * Register hooks.
	 *
	 * @since 2.0.2
	 */
	public function hooks(): void {

		add_action( 'wp_ajax_' . self::ACTION, [ $this, 'install' ] );
	}

	/**
	 * Install one or more plugins in place and return the outcome.
	 *
	 * @since 2.0.2
	 */
	public function install(): void {

		check_ajax_referer( self::ACTION, 'nonce' );

		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_send_json_error(
				[ 'message' => esc_html__( 'You do not have permission to install plugins.', 'wpforms-lite' ) ],
				403
			);
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized on the next line.
		$plugins = isset( $_POST['plugin'] ) ? (array) wp_unslash( $_POST['plugin'] ) : [];
		$plugins = array_values( array_filter( array_map( 'wpforms_sanitize_key', $plugins ) ) );

		if ( $plugins === [] ) {
			wp_send_json_error( [ 'message' => esc_html__( 'No plugin was specified.', 'wpforms-lite' ) ] );
		}

		$result = ( new PluginInstaller() )->install( $plugins );
		$failed = array_diff( $plugins, $result['installed'] );

		if ( $failed !== [] ) {
			$first   = (string) reset( $failed );
			$message = $result['failed'][ $first ] ?? __( 'The plugin could not be installed.', 'wpforms-lite' );

			wp_send_json_error( [ 'message' => esc_html( $message ) ], 500 );
		}

		wp_send_json_success();
	}

	/**
	 * Whether the current user can drive the in-place install CTA for the given action.
	 *
	 * Client-side counterpart to this endpoint's own capability check, so a CTA the endpoint
	 * would reject is never rendered as if it worked. Activation is gated on the install
	 * capability too: install() requires it regardless of the requested action, and activating
	 * through the endpoint may also upgrade the plugin. Any other state — notably the terminal
	 * 'active' one — never reaches the endpoint and needs no capability.
	 *
	 * @since 2.0.2
	 *
	 * @param string $action CTA action: 'install-plugin', 'activate-plugin', or 'active'.
	 * @param string $type   Either 'plugin' for a wordpress.org plugin or 'addon' for a WPForms
	 *                       addon; only 'addon' additionally requires a valid license.
	 *
	 * @return bool
	 */
	public static function can_install_and_activate( string $action, string $type ): bool {

		if ( $action === 'install-plugin' ) {
			return wpforms_can_install( $type );
		}

		if ( $action === 'activate-plugin' ) {
			return wpforms_can_activate( $type ) && wpforms_can_install( $type );
		}

		return true;
	}

	/**
	 * Resolve the out-of-band destination for a plugin that cannot be installed in place:
	 * the Plugins screen when the plugin is already on disk and the user may activate it,
	 * otherwise its WordPress.org page.
	 *
	 * Canonical for every fallback CTA — the decision and the wordpress.org URL live here
	 * so consumers only map the result onto their own link shape.
	 *
	 * @since 2.0.2
	 *
	 * @param string $file         Plugin main-file path (folder/file.php).
	 * @param bool   $is_installed Whether the plugin is already on disk.
	 *
	 * @return array {
	 *     Fallback destination.
	 *
	 *     @type string $url         Destination URL.
	 *     @type bool   $is_activate Whether the destination is the activate route (Plugins screen).
	 * }
	 */
	public static function get_fallback_destination( string $file, bool $is_installed ): array {

		if ( $is_installed && current_user_can( 'activate_plugins' ) ) {
			return [
				'url'         => admin_url( 'plugins.php' ),
				'is_activate' => true,
			];
		}

		return [
			'url'         => 'https://wordpress.org/plugins/' . dirname( $file ) . '/',
			'is_activate' => false,
		];
	}
}

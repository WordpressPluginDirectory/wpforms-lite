<?php

namespace WPForms\SetupChecklist;

use WPForms\SetupWizard\Service\PluginInstaller;

/**
 * Setup Checklist AJAX endpoints.
 *
 * Handles the actions the checklist page fires from the browser. The checklist
 * is never dismissed automatically — even once every step is complete — so the
 * user can keep exploring the addons, integrations, and recommended-plugins
 * grids; the footer "Complete Setup Checklist" link is the only way to dismiss
 * it entirely, and it routes here.
 *
 * @since 2.0.0
 */
class Ajax {

	/**
	 * Dismiss AJAX action and its nonce action.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public const DISMISS_ACTION = 'wpforms_setup_checklist_dismiss';

	/**
	 * Install-plugin AJAX action (also used as its nonce action).
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public const INSTALL_PLUGIN_ACTION = 'wpforms_setup_checklist_install_plugin';

	/**
	 * Checklist model (source of the progress percentage captured at dismissal).
	 *
	 * @since 2.0.0
	 *
	 * @var Checklist
	 */
	private $checklist;

	/**
	 * Per-site state store.
	 *
	 * @since 2.0.0
	 *
	 * @var State
	 */
	private $state;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param Checklist $checklist Checklist model.
	 * @param State     $state     Per-site state store.
	 */
	public function __construct( Checklist $checklist, State $state ) {

		$this->checklist = $checklist;
		$this->state     = $state;
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 */
	public function hooks(): void {

		add_action( 'wp_ajax_' . self::DISMISS_ACTION, [ $this, 'dismiss' ] );
		add_action( 'wp_ajax_' . self::INSTALL_PLUGIN_ACTION, [ $this, 'install_plugin' ] );
	}

	/**
	 * Dismiss the checklist for this site and return where to redirect next.
	 *
	 * @since 2.0.0
	 */
	public function dismiss(): void {

		check_ajax_referer( self::DISMISS_ACTION, 'nonce' );

		if ( ! current_user_can( wpforms_get_capability_manage_options() ) ) {
			wp_send_json_error(
				[ 'message' => esc_html__( 'You do not have permission to dismiss the checklist.', 'wpforms-lite' ) ],
				403
			);
		}

		$progress = $this->checklist->get_progress()['percent'];

		$this->state->dismiss( $progress );

		wp_send_json_success(
			[
				'redirect_url' => add_query_arg( 'page', 'wpforms-overview', admin_url( 'admin.php' ) ),
			]
		);
	}

	/**
	 * Install a plugin ( a WPForms addon or a recommended WordPress.org plugin ) in place,
	 * reusing the Setup Wizard's install gateway. Addon license access is enforced per plugin
	 * by that gateway, so the endpoint gates only on the install capability.
	 *
	 * @since 2.0.0
	 */
	public function install_plugin(): void {

		check_ajax_referer( self::INSTALL_PLUGIN_ACTION, 'nonce' );

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
}

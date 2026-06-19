<?php

namespace WPForms\Integrations\AiMcp;

use WPForms\Integrations\IntegrationInterface;

/**
 * AI MCP integration. Owns the Tools-tab assets and the write-gate AJAX handler.
 *
 * @since 1.10.2
 */
class AiMcp implements IntegrationInterface {

	/**
	 * Key inside the shared wpforms_settings option array that stores the write-gate toggle.
	 *
	 * @since 1.10.2
	 */
	const SETTING_KEY = 'ai-mcp-write-enabled';

	/**
	 * WPVibe plugin basename on wp.org.
	 *
	 * @since 1.10.2
	 */
	const WPVIBE_BASENAME = 'vibe-ai/vibe-ai.php';

	/**
	 * WPVibe latest-stable download URL on wp.org.
	 *
	 * @since 1.10.2
	 */
	const WPVIBE_DOWNLOAD_URL = 'https://downloads.wordpress.org/plugin/vibe-ai.latest-stable.zip';

	/**
	 * Top-level WPVibe admin page slug.
	 *
	 * @since 1.10.2
	 */
	const WPVIBE_PAGE_SLUG = 'vibe-ai';

	/**
	 * Screen ID of the top-level WPVibe admin page (`add_menu_page` slug `vibe-ai`).
	 *
	 * @since 1.10.2
	 */
	const WPVIBE_PAGE_SCREEN_ID = 'toplevel_page_vibe-ai';

	/**
	 * User meta key flagging that the current user has visited the WPVibe admin page.
	 *
	 * Drives the "Set Up WPVibe" → "Go To WPVibe" CTA copy swap on the AI MCP page.
	 *
	 * @since 1.10.2
	 */
	const USER_META_VISITED_WPVIBE = 'wpforms_ai_mcp_visited_wpvibe';

	/**
	 * Option key — install source recorded when WPVibe is activated via WPForms.
	 *
	 * Picked up by WPForms\Integrations\UsageTracking\UsageTracking and forwarded
	 * to AM analytics as `wpforms_wpvibe_date`. Mirrors the convention used by
	 * other partner plugins (see \WPForms\Education\ActiveLayer\InstallTracker).
	 *
	 * @since 1.10.2
	 */
	const WPVIBE_SOURCE_OPTION = 'wpvibe_source';

	/**
	 * Option key — unix timestamp of the first activation through WPForms.
	 *
	 * @since 1.10.2
	 */
	const WPVIBE_DATE_OPTION = 'wpvibe_date';

	/**
	 * Allow load when in admin or doing AJAX (the AJAX handler must register even off-tab),
	 * and only where the Abilities API exists (WP 6.9+) since the tab has nothing to gate without it.
	 *
	 * @since 1.10.2
	 *
	 * @return bool
	 */
	public function allow_load(): bool {

		return ( is_admin() || wp_doing_ajax() ) && function_exists( 'wp_register_ability' );
	}

	/**
	 * Load the integration — register hooks.
	 *
	 * @since 1.10.2
	 */
	public function load() {

		$this->hooks();
	}

	/**
	 * Register hooks. AJAX handler always; asset enqueuing only on the AI MCP tab.
	 *
	 * @since 1.10.2
	 */
	private function hooks() {

		add_action( 'wp_ajax_wpforms_ai_mcp_toggle_write', [ $this, 'ajax_toggle_write' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ], 20 );
		add_action( 'current_screen', [ $this, 'maybe_mark_wpvibe_visited' ] );
		add_action( 'wpforms_plugin_activated', [ $this, 'maybe_record_wpvibe_source' ] );
	}

	/**
	 * Record the install source when WPVibe is activated through the WPForms flow.
	 *
	 * Hooked on `wpforms_plugin_activated` rather than core `activated_plugin`, so
	 * the source is only attributed when activation actually went through our AJAX
	 * installer — independent activations from the Plugins page or WP-CLI are not
	 * counted as "from WPForms". Idempotent: once a source is recorded, later
	 * re-activations through WPForms do NOT overwrite it.
	 *
	 * Mirrors the canonical pattern in \WPForms\Education\ActiveLayer\InstallTracker.
	 *
	 * @since 1.10.2
	 *
	 * @param string $plugin_basename Basename of the plugin being activated.
	 */
	public function maybe_record_wpvibe_source( $plugin_basename ) {

		if ( $plugin_basename !== self::WPVIBE_BASENAME ) {
			return;
		}

		if ( get_option( self::WPVIBE_SOURCE_OPTION ) ) {
			return;
		}

		$source = wpforms()->is_pro() ? 'WPForms' : 'WPForms Lite';

		update_option( self::WPVIBE_SOURCE_OPTION, $source, false );
		update_option( self::WPVIBE_DATE_OPTION, time(), false );
	}

	/**
	 * Flag that the current user has visited the WPVibe admin page.
	 *
	 * Hooked on `current_screen` so any path that lands on the WPVibe page —
	 * sidebar click, our CTA, a direct URL — counts as a visit. Used to swap
	 * the active-state CTA copy from "Set Up WPVibe" to "Go To WPVibe".
	 *
	 * @since 1.10.2
	 *
	 * @param WP_Screen $screen Current screen.
	 */
	public function maybe_mark_wpvibe_visited( $screen ) {

		if ( ! is_object( $screen ) || $screen->id !== self::WPVIBE_PAGE_SCREEN_ID ) {
			return;
		}

		$user_id = get_current_user_id();

		if ( ! $user_id || get_user_meta( $user_id, self::USER_META_VISITED_WPVIBE, true ) ) {
			return;
		}

		update_user_meta( $user_id, self::USER_META_VISITED_WPVIBE, '1' );
	}

	/**
	 * Detect whether the current request is rendering the AI MCP tab.
	 *
	 * @since 1.10.2
	 *
	 * @return bool
	 */
	private function is_ai_mcp_tab(): bool {

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
		$view = isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return $page === 'wpforms-tools' && $view === 'ai-mcp';
	}

	/**
	 * Enqueue the page JS and localize handler config. Only fires on the AI MCP tab.
	 *
	 * @since 1.10.2
	 */
	public function enqueue_scripts() {

		if ( ! $this->is_ai_mcp_tab() ) {
			return;
		}

		$min = wpforms_get_min_suffix();

		wp_enqueue_script(
			'wpforms-ai-mcp',
			WPFORMS_PLUGIN_URL . "assets/js/admin/tools/ai-mcp{$min}.js",
			[ 'jquery', 'wpforms-admin-utils' ],
			WPFORMS_VERSION,
			true
		);

		wp_localize_script(
			'wpforms-ai-mcp',
			'wpformsAiMcpVars',
			[
				'toggleNonce' => wp_create_nonce( 'wpforms_ai_mcp_toggle' ),
				'i18n'        => [
					'saved'        => __( 'Saved', 'wpforms-lite' ),
					'genericError' => __( 'Something went wrong. Please try again.', 'wpforms-lite' ),
				],
			]
		);
	}

	/**
	 * AJAX handler for flipping the write-access toggle.
	 *
	 * Validates nonce + admin cap, merges the new value into the shared
	 * wpforms_settings option (under SETTING_KEY), and returns the new state.
	 *
	 * Stores '1' for ON and '' (empty) for OFF — matching how other booleans
	 * inside wpforms_settings (logs-enable, lite-connect-enabled) are represented.
	 *
	 * @since 1.10.2
	 */
	public function ajax_toggle_write() {

		check_ajax_referer( 'wpforms_ai_mcp_toggle', 'nonce' );

		if ( ! wpforms_current_user_can() ) {
			wp_send_json_error(
				[ 'message' => esc_html__( 'You do not have permission to change this setting.', 'wpforms-lite' ) ],
				403
			);

			return;
		}

		$raw     = isset( $_POST['enabled'] ) ? sanitize_text_field( wp_unslash( $_POST['enabled'] ) ) : '';
		$enabled = rest_sanitize_boolean( $raw );

		$settings                      = (array) get_option( 'wpforms_settings', [] );
		$settings[ self::SETTING_KEY ] = $enabled ? '1' : '';

		// Persist through the canonical helper so the wpforms_update_settings filter and
		// wpforms_settings_updated action run, keeping us in line with every other settings write.
		wpforms_update_settings( $settings );

		wp_send_json_success( [ 'enabled' => $enabled ] );
	}
}

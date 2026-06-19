<?php

namespace WPForms\Admin\Tools\Views;

use WPForms\Integrations\AiMcp\AiMcp as AiMcpIntegration;

/**
 * AI MCP Tools tab — promotes WPVibe MCP bridge and gates write access for Abilities API.
 *
 * @since 1.10.2
 */
class AiMcp extends View {

	/**
	 * View slug.
	 *
	 * @since 1.10.2
	 *
	 * @var string
	 */
	protected $slug = 'ai-mcp';

	/**
	 * Init the view. No-op because asset enqueuing and AJAX registration live
	 * on the Integration class so they run independently of which tab is open.
	 *
	 * @since 1.10.2
	 */
	public function init(): void {

		// Hooks live on the AiMcp Integration class so the AJAX handler can register regardless of which Tools tab is open.
	}

	/**
	 * Tab label used in the Tools navigation.
	 *
	 * @since 1.10.2
	 *
	 * @return string
	 */
	public function get_label(): string {

		return __( 'AI MCP', 'wpforms-lite' );
	}

	/**
	 * Gate the tab on admin-only capability. Same bar as Logs and CodeSnippets.
	 *
	 * @since 1.10.2
	 *
	 * @return bool
	 */
	public function check_capability(): bool {

		/**
		 * Filters whether the current user may view the AI MCP tab.
		 *
		 * @since 1.10.2
		 *
		 * @param bool $can Defaults to manage_options plus the Abilities API (WP 6.9+) being present.
		 *                  Without the Abilities API no WPForms ability registers, so the tab and its
		 *                  write toggle would have nothing to act on.
		 */
		return (bool) apply_filters(
			'wpforms_admin_tools_views_ai_mcp_check_capability',
			wpforms_current_user_can() && function_exists( 'wp_register_ability' )
		);
	}

	/**
	 * Resolve WPVibe state + the current toggle value and render the template.
	 *
	 * @since 1.10.2
	 */
	public function display(): void {

		$user_id = get_current_user_id();

		echo wpforms_render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'admin/tools/ai-mcp',
			[
				'state'               => $this->resolve_wpvibe_state(),
				'is_write_enabled'    => (bool) wpforms_setting( AiMcpIntegration::SETTING_KEY, false ),
				'is_pro'              => wpforms()->is_pro(),
				'wpvibe_download_url' => AiMcpIntegration::WPVIBE_DOWNLOAD_URL,
				'wpvibe_basename'     => AiMcpIntegration::WPVIBE_BASENAME,
				'wpvibe_setup_url'    => admin_url( 'admin.php?page=' . AiMcpIntegration::WPVIBE_PAGE_SLUG ),
				'has_visited_wpvibe'  => $user_id && (bool) get_user_meta( $user_id, AiMcpIntegration::USER_META_VISITED_WPVIBE, true ),
				'docs_url'            => 'https://wpforms.com/docs/using-wpforms-with-ai-assistants/',
			],
			true
		);
	}

	/**
	 * Detect whether WPVibe is not installed, installed but inactive, or active.
	 *
	 * @since 1.10.2
	 *
	 * @return string One of 'not_installed', 'installed_inactive', 'active'.
	 */
	private function resolve_wpvibe_state(): string {

		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$basename = AiMcpIntegration::WPVIBE_BASENAME;
		$plugins  = get_plugins();

		if ( ! array_key_exists( $basename, $plugins ) ) {
			return 'not_installed';
		}

		if ( ! is_plugin_active( $basename ) ) {
			return 'installed_inactive';
		}

		return 'active';
	}
}

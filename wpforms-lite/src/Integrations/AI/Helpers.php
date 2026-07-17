<?php

namespace WPForms\Integrations\AI;

/**
 * AI features related helper methods.
 *
 * @since 1.9.1
 */
class Helpers {

	/**
	 * Key for a state whether integration is disabled on the Settings > Misc admin page.
	 *
	 * @since 1.9.1
	 */
	public const DISABLE_KEY = 'ai-feature-disabled';

	/**
	 * Key for a state whether integration is used (or has been used).
	 * There is no UI/UX for it, and it's used for internal purposes.
	 *
	 * @since 1.9.1
	 */
	private const USE_KEY = 'ai-feature-used';

	/**
	 * Determine whether integration is disabled.
	 *
	 * @since 1.9.1
	 *
	 * @return bool
	 */
	public static function is_disabled(): bool {

		return self::is_disabled_by_rule() || wpforms_setting( self::DISABLE_KEY );
	}

	/**
	 * Determine whether integration is used.
	 *
	 * @since 1.9.1
	 *
	 * @return bool
	 */
	public static function is_used(): bool {

		return (bool) wpforms_setting( self::USE_KEY );
	}

	/**
	 * Mark integration as used.
	 *
	 * @since 1.9.1
	 */
	public static function set_ai_used(): void {

		if ( self::is_used() ) {
			return;
		}

		$settings = (array) get_option( 'wpforms_settings', [] );

		$settings[ self::USE_KEY ] = true;

		update_option( 'wpforms_settings', $settings );
	}

	/**
	 * Determine whether integration is disabled through constant or filter.
	 *
	 * @since 1.9.1
	 *
	 * @return bool
	 * @noinspection PhpUndefinedConstantInspection
	 */
	public static function is_disabled_by_rule(): bool {

		$is_disabled = defined( 'WPFORMS_DISABLE_AI_FEATURES' ) && WPFORMS_DISABLE_AI_FEATURES;

		/**
		 * Allow modifying whether AI integration is disabled in WPForms.
		 *
		 * @since 1.9.1
		 *
		 * @param bool $is_disabled True if AI integration is disabled. Default is false.
		 */
		return (bool) apply_filters( 'wpforms_disable_ai_features', $is_disabled ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Log an error record.
	 *
	 * @since 1.9.1
	 *
	 * @param string $message  Error message.
	 * @param string $endpoint Endpoint.
	 * @param array  $args     Arguments.
	 */
	public static function log_error( string $message, string $endpoint, array $args ) {

		wpforms_log(
			'AI Integration Error',
			[
				'error'    => $message,
				'endpoint' => $endpoint,
				'args'     => $args,
			],
			[
				'type' => [ 'ai', 'error' ],
			]
		);
	}

	/**
	 * Get the shared chat-element localize strings.
	 *
	 * Common payload consumed by every AI chat surface (form-editor and admin-area
	 * surfaces both render the same custom element). Each caller supplies the surface-
	 * specific `nonce` action and an optional `actions` map; everything else (button
	 * labels, dialog copy, error/reason strings, docking labels) is shared.
	 *
	 * Mode-specific strings (e.g. the `admin` or `form-editor` mode block) are
	 * appended by the surface via its own filter — this method only returns the
	 * shared base.
	 *
	 * @since 2.0.0
	 *
	 * @param string $nonce_action Nonce action passed to `wp_create_nonce`.
	 * @param array  $actions      Additional AJAX-action map exposed to api.js.
	 *
	 * @return array
	 */
	public static function get_chat_element_strings( string $nonce_action, array $actions = [] ): array {

		return [
			'ajaxurl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( $nonce_action ),
			'min'       => wpforms_get_min_suffix(),
			'like'      => esc_html__( 'Great response!', 'wpforms-lite' ),
			'dislike'   => esc_html__( 'Bad response', 'wpforms-lite' ),
			'refresh'   => esc_html__( 'Clear chat history', 'wpforms-lite' ),
			'retry'     => esc_html__( 'Retry', 'wpforms-lite' ),
			'btnYes'    => esc_html__( 'Yes, Continue', 'wpforms-lite' ),
			'btnCancel' => esc_html__( 'Cancel', 'wpforms-lite' ),
			'confirm'   => [
				'refreshTitle'   => esc_html__( 'Clear Chat History', 'wpforms-lite' ),
				'refreshMessage' => esc_html__( 'Are you sure you want to clear the AI chat history and start over?', 'wpforms-lite' ),
			],
			'errors'    => [
				'default'    => esc_html__( 'An error occurred.', 'wpforms-lite' ),
				'network'    => esc_html__( 'There appears to be a network error.', 'wpforms-lite' ),
				'empty'      => esc_html__( 'I\'m not sure what to do with that.', 'wpforms-lite' ),
				'rate_limit' => esc_html__( 'You\'ve hit your daily AI request limit.', 'wpforms-lite' ),
			],
			'warnings'  => [
				'prohibited_code' => esc_html__( 'Prohibited code has been removed.', 'wpforms-lite' ),
			],
			'reasons'   => [
				'default'         => esc_html__( 'Please try again.', 'wpforms-lite' ),
				'empty'           => esc_html__( 'Please try a different prompt. You might need to be more descriptive.', 'wpforms-lite' ),
				'prohibited_code' => esc_html__( 'Only basic styling tags are permitted. All other code deemed unsafe has been removed.', 'wpforms-lite' ),
				'rate_limit'      => sprintf(
					wp_kses( /* translators: %s - WPForms contact support link. */
						__( 'You can make up to 50 AI requests per day. If you believe this is an error, <a href="%s" target="_blank" rel="noopener noreferrer">please contact WPForms support</a>.', 'wpforms-lite' ),
						[
							'a' => [
								'href'   => [],
								'target' => [],
								'rel'    => [],
							],
						]
					),
					wpforms_utm_link( 'https://wpforms.com/account/support/', 'AI Feature' )
				),
			],
			'actions'   => $actions,
			'pinChat'   => is_rtl() ? esc_html__( 'Dock to the Left', 'wpforms-lite' ) : esc_html__( 'Dock to the Right', 'wpforms-lite' ),
			'unpinChat' => esc_html__( 'Open in Popup', 'wpforms-lite' ),
			'close'     => esc_html__( 'Close', 'wpforms-lite' ),
		];
	}

	/**
	 * Get the license type.
	 *
	 * @since 1.9.4
	 *
	 * @return string
	 */
	public static function get_license_type(): string {

		$license = (array) get_option( 'wpforms_license', [] );

		return $license['type'] ?? '';
	}

	/**
	 * Determine whether a license key is active.
	 *
	 * @since 1.9.4
	 *
	 * @return bool
	 */
	public static function is_license_active(): bool {

		return wpforms_is_license_valid();
	}
}

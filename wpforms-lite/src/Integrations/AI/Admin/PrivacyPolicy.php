<?php

namespace WPForms\Integrations\AI\Admin;

/**
 * Suggested privacy policy text for the AI features.
 *
 * WordPress shows the text registered here on the Privacy Policy Guide screen
 * (Settings > Privacy > Policy Guide), where site owners can copy it into their own policy.
 *
 * Further AI-adjacent disclosures belong in `get_content()`, never in a second
 * `wp_add_privacy_policy_content()` call: `WP_Privacy_Policy_Content::add()` appends
 * unconditionally and dedupes only on an exact plugin name and policy text match, so another
 * `add( 'WPForms', … )` would render two identically titled accordions.
 *
 * @since 2.0.2
 */
class PrivacyPolicy {

	/**
	 * Number of days within which identifiers are removed from stored prompts and AI responses.
	 *
	 * Mirrors REDACTION_DELAY_DAYS of the WPForms AI service, and must be changed together with
	 * the published privacy policy.
	 *
	 * @since 2.0.2
	 */
	public const REDACTION_DELAY_DAYS = 2;

	/**
	 * Number of days after which a stored prompt and AI response no longer carry the domain used for the request.
	 *
	 * Mirrors RETENTION_DAYS of the WPForms AI service (the approved 180-day window), and must be
	 * changed together with the published privacy policy.
	 *
	 * @since 2.0.2
	 */
	public const RETENTION_DAYS = 180;

	/**
	 * Initialize.
	 *
	 * @since 2.0.2
	 */
	public function init() {

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0.2
	 */
	private function hooks() {

		add_action( 'admin_init', [ $this, 'register' ] );
	}

	/**
	 * Register the suggested privacy policy text with WordPress.
	 *
	 * Must run on `admin_init`, otherwise `wp_add_privacy_policy_content()` raises
	 * `_doing_it_wrong`. Registration cannot be narrowed to the Policy Guide screen either:
	 * core compares the registered text against the published policy in
	 * `WP_Privacy_Policy_Content::text_change_check()`, hooked on `admin_init` at priority 100,
	 * so the content is built on every admin and admin-ajax request.
	 *
	 * @since 2.0.2
	 */
	public function register() {

		wp_add_privacy_policy_content( 'WPForms', $this->get_content() );
	}

	/**
	 * Get the suggested privacy policy content.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	private function get_content(): string {

		$paragraphs = [
			esc_html__( 'This website uses AI features provided by WPForms. When a site administrator uses one of them (AI Forms, AI Choices, AI Form Editor or AI Chat), the text of their prompt and the configuration of the form being edited, which can include notification recipient email addresses, are sent to the WPForms AI service at wpformsapi.com, operated by Awesome Motive, Inc., and processed by OpenAI. Form entries submitted by visitors are never transmitted.', 'wpforms-lite' ),
			sprintf(
				/* translators: %1$d - number of days within which identifiers are removed from stored prompts and AI responses, %2$d - number of days after which a stored record no longer carries the domain used for the request. */
				esc_html__( 'Prompts and AI responses are stored on infrastructure hosted by Cloudflare and PlanetScale, and error diagnostics are sent to Sentry. All processing takes place in the United States. Within %1$d days of a request, email addresses, phone numbers, web addresses, IP addresses and payment or bank account numbers are removed from the stored prompt and response, and after %2$d days the stored record no longer carries the domain used for the request.', 'wpforms-lite' ),
				self::REDACTION_DELAY_DAYS,
				self::RETENTION_DAYS
			),
		];

		// Lite authenticates the AI service through Lite Connect; Pro uses the license key.
		if ( ! wpforms()->is_pro() ) {
			$paragraphs[] = esc_html__( 'The AI service is authenticated through WPForms Lite Connect at wpformsliteconnect.com.', 'wpforms-lite' );
		}

		$paragraphs[] = esc_html__( 'Site administrators can turn off all AI features under WPForms → Settings → Misc.', 'wpforms-lite' );

		// WordPress shows the tutorial paragraph on the guide screen only and strips it from
		// the copied policy, so it is not part of the wpautop()'d body.
		$tutorial = '<p class="privacy-policy-tutorial">' .
			esc_html__( 'This sample text covers the WPForms AI features. Include it only if the administrators of this site use them, and adjust it to match how your site is run.', 'wpforms-lite' ) .
			'</p>';

		return wp_kses_post( $tutorial . wpautop( implode( "\n\n", $paragraphs ), false ) );
	}
}

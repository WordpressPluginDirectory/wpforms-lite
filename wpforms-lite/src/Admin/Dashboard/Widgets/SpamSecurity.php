<?php

namespace WPForms\Admin\Dashboard\Widgets;

use WPForms\Admin\Dashboard\AccessContext;
use WPForms\Admin\Dashboard\WidgetState;
use WPForms\Education\ActiveLayer\Helper as ActiveLayerHelper;
use WPForms\Education\WPConsent\Helper as WPConsentHelper;

/**
 * "Spam & Security Checkup" sidebar widget.
 *
 * A configuration checklist with two groups — Anti-Spam and Security & Privacy — each
 * satisfied by either of two alternatives, followed by documentation links. Every row
 * reflects live configuration state; nothing here reads form or entry data.
 *
 * Identical on every tier; no gear menu; not dismissible; no JS.
 *
 * @since 2.0.2
 */
class SpamSecurity extends AbstractWidget {

	/**
	 * Column placement.
	 *
	 * @since 2.0.2
	 */
	public const COLUMN = 'sidebar';

	/**
	 * Default sort position within the sidebar (between Growth Tools and Getting Started).
	 *
	 * @since 2.0.2
	 */
	public const ORDER = 40;

	/**
	 * UTM medium shared by every documentation link in the widget.
	 *
	 * @since 2.0.2
	 */
	private const UTM_MEDIUM = 'Dashboard - Spam and Security';

	/**
	 * The spam-prevention guide. Two of the four doc links are anchors into its sections.
	 *
	 * @since 2.0.2
	 */
	private const SPAM_GUIDE_URL = 'https://wpforms.com/docs/how-to-prevent-spam-in-wpforms/';

	/**
	 * WPConsent's top-level admin page, where an onboarded user should land.
	 *
	 * `Education\WPConsent\Helper` exposes only the onboarding URL, and that helper is shared
	 * with the settings callout and the builder GDPR notice, so the slug is resolved here
	 * rather than by widening a shipped API.
	 *
	 * @since 2.0.2
	 */
	private const WPCONSENT_DASHBOARD_PAGE = 'wpconsent';

	/**
	 * Get the widget identifier.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	public function get_id(): string {

		return 'spam-security';
	}

	/**
	 * Get the widget title.
	 *
	 * @since 2.0.2
	 *
	 * @return string
	 */
	public function get_title(): string {

		return esc_html__( 'Spam & Security Checkup', 'wpforms-lite' );
	}

	/**
	 * Get the widget state — always visible, no variant, no tier variation.
	 *
	 * @since 2.0.2
	 *
	 * @param AccessContext $access Access context.
	 *
	 * @return WidgetState
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function get_state( AccessContext $access ): WidgetState { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Contract signature; the widget is tier-agnostic and always visible.

		return new WidgetState( true );
	}

	/**
	 * Render the widget body.
	 *
	 * @since 2.0.2
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Aggregated data.
	 * @param AccessContext $access  Access context.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	protected function render_body( string $variant, array $data, AccessContext $access ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Contract signature; every row reads live configuration, not aggregated data.

		return (string) wpforms_render(
			'admin/dashboard/sidebar/spam-security',
			[
				'groups'     => $this->get_groups(),
				'docs_title' => __( 'Additional Information', 'wpforms-lite' ),
				'doc_links'  => $this->get_doc_links(),
			],
			true
		);
	}

	/**
	 * Get the two checklist groups.
	 *
	 * @since 2.0.2
	 *
	 * @return array Groups: each item has `title` and `rows`.
	 */
	private function get_groups(): array {

		return [
			[
				'title' => __( 'Anti-Spam', 'wpforms-lite' ),
				'rows'  => $this->get_anti_spam_rows(),
			],
			[
				'title' => __( 'Security & Privacy', 'wpforms-lite' ),
				'rows'  => $this->get_privacy_rows(),
			],
		];
	}

	/**
	 * Get the Anti-Spam group rows.
	 *
	 * Either alternative satisfies the group: the satisfied row gets a check and keeps its link,
	 * while the other is struck through as no longer needed and carries no link at all.
	 *
	 * @since 2.0.2
	 *
	 * @return array
	 */
	private function get_anti_spam_rows(): array {

		$active_layer_met = $this->is_active_layer_met();
		$captcha_met      = $this->is_captcha_met();

		// A superseded row carries no link: offering to install something the user has been told
		// they no longer need would contradict the strike.
		$active_layer_superseded = ! $active_layer_met && $captcha_met;
		$captcha_superseded      = ! $captcha_met && $active_layer_met;

		return [
			array_merge(
				[
					'name'          => 'ActiveLayer',
					/* translators: %1$s - the linked "ActiveLayer" plugin name; %2$s - the italic "or…" continuation. */
					'label_format'  => __( '%1$s installed and configured, %2$s', 'wpforms-lite' ),
					'or_text'       => __( 'or…', 'wpforms-lite' ),
					'is_met'        => $active_layer_met,
					'is_superseded' => $active_layer_superseded,
					'sibling_name'  => __( 'a CAPTCHA service', 'wpforms-lite' ),
				],
				$active_layer_superseded ? $this->get_empty_link() : $this->get_active_layer_link()
			),
			array_merge(
				[
					'name'          => __( 'Captcha', 'wpforms-lite' ),
					/* translators: %1$s - the linked "Captcha" label. */
					'label_format'  => __( '%1$s service enabled', 'wpforms-lite' ),
					'or_text'       => '',
					'is_met'        => $captcha_met,
					'is_superseded' => $captcha_superseded,
					'sibling_name'  => 'ActiveLayer',
				],
				$captcha_superseded ? $this->get_empty_link() : $this->get_settings_link( 'captcha' )
			),
		];
	}

	/**
	 * Get the Security & Privacy group rows.
	 *
	 * @since 2.0.2
	 *
	 * @return array
	 */
	private function get_privacy_rows(): array {

		$wpconsent_met = $this->is_wpconsent_met();
		$gdpr_met      = $this->is_gdpr_met();

		$wpconsent_superseded = ! $wpconsent_met && $gdpr_met;
		$gdpr_superseded      = ! $gdpr_met && $wpconsent_met;

		return [
			array_merge(
				[
					'name'          => 'WPConsent',
					/* translators: %1$s - the linked "WPConsent" plugin name; %2$s - the italic "or…" continuation. */
					'label_format'  => __( '%1$s installed and configured, %2$s', 'wpforms-lite' ),
					'or_text'       => __( 'or…', 'wpforms-lite' ),
					'is_met'        => $wpconsent_met,
					'is_superseded' => $wpconsent_superseded,
					'sibling_name'  => __( 'GDPR Enhancements', 'wpforms-lite' ),
				],
				$wpconsent_superseded ? $this->get_empty_link() : $this->get_wpconsent_link( $wpconsent_met )
			),
			array_merge(
				[
					'name'          => __( 'GDPR Enhancements', 'wpforms-lite' ),
					/* translators: %1$s - the linked "GDPR Enhancements" setting name. */
					'label_format'  => __( '%1$s enabled', 'wpforms-lite' ),
					'or_text'       => '',
					'is_met'        => $gdpr_met,
					'is_superseded' => $gdpr_superseded,
					'sibling_name'  => 'WPConsent',
				],
				$gdpr_superseded ? $this->get_empty_link() : $this->get_settings_link( 'general', 'gdpr' )
			),
		];
	}

	/**
	 * Whether ActiveLayer satisfies its row: active and holding an API key.
	 *
	 * Same definition as `SetupChecklist\CompletionDetector::is_spam_protection_enabled()` —
	 * an activated plugin without a key protects nothing. Not shared with the Spam Entries
	 * card: that one gates on spam-entry logging alone, which ActiveLayer does not provide.
	 *
	 * @since 2.0.2
	 *
	 * @return bool
	 */
	private function is_active_layer_met(): bool {

		return ActiveLayerHelper::is_set_up();
	}

	/**
	 * Whether a CAPTCHA service satisfies its row: a provider chosen and both keys present.
	 *
	 * Same definition as `SetupChecklist\CompletionDetector::is_spam_protection_enabled()`.
	 * The provider test must come first — `wpforms_get_captcha_settings()` returns only the
	 * `provider` key when no valid provider is selected.
	 *
	 * @since 2.0.2
	 *
	 * @return bool
	 */
	private function is_captcha_met(): bool {

		$captcha = wpforms_get_captcha_settings();

		return $captcha['provider'] !== 'none'
			&& ! empty( $captcha['site_key'] )
			&& ! empty( $captcha['secret_key'] );
	}

	/**
	 * Whether WPConsent satisfies its row: active and its onboarding finished.
	 *
	 * Same onboarding condition as
	 * `SetupChecklist\CompletionDetector::is_privacy_compliance_configured()`, with broader
	 * activation detection covering both Lite and Premium.
	 *
	 * @since 2.0.2
	 *
	 * @return bool
	 */
	private function is_wpconsent_met(): bool {

		if ( ! WPConsentHelper::is_activated() ) {
			return false;
		}

		return function_exists( 'wpconsent' )
			&& (bool) wpconsent()->settings->get_option( 'onboarding_completed' );
	}

	/**
	 * Whether GDPR Enhancements is enabled in Settings → General.
	 *
	 * @since 2.0.2
	 *
	 * @return bool
	 */
	private function is_gdpr_met(): bool {

		return (bool) wpforms_setting( 'gdpr' );
	}

	/**
	 * Resolve the ActiveLayer row link.
	 *
	 * The row's own name is the only link — per the design there is no separate CTA. Install
	 * and activate are education promos and obey the education kill switch; once the plugin
	 * is active the link points at its dashboard and is not a promo.
	 *
	 * @since 2.0.2
	 *
	 * @return array
	 */
	private function get_active_layer_link(): array {

		$modal  = ActiveLayerHelper::get_modal_data();
		$action = $modal['attrs']['data-action'] ?? '';

		// Install/activate are promos: with education off the name renders as plain text.
		if ( $action !== '' && ! $this->is_education_available() ) {
			return $this->get_empty_link();
		}

		return [
			'link'       => $modal['link'],
			'link_class' => $modal['class'],
			'link_attrs' => $modal['attrs'],
		];
	}

	/**
	 * Resolve the WPConsent row link.
	 *
	 * `Education\WPConsent\Helper::get_install_button_data()` returns only the action and the
	 * `data-*` payload, so the link and modal class are assembled here. It also omits
	 * `data-name`, which `education/core.js` interpolates into the install prompt — without it
	 * the modal reads "the undefined addon", so the name is supplied here too. A matching
	 * `get_modal_data()` on that helper would remove this assembly, but it is shared with the
	 * settings callout and the builder GDPR notice, so widening it is out of scope here.
	 *
	 * @since 2.0.2
	 *
	 * @param bool $is_met Whether WPConsent already satisfies its row, as resolved by the caller.
	 *
	 * @return array
	 */
	private function get_wpconsent_link( bool $is_met ): array {

		if ( WPConsentHelper::is_activated() ) {
			// Onboarded: land on the plugin's dashboard, mirroring ActiveLayer's met row.
			// Onboarding unfinished: resume the wizard, which is what makes the row unmet.
			$link = $is_met
				? admin_url( 'admin.php?page=' . self::WPCONSENT_DASHBOARD_PAGE )
				: WPConsentHelper::get_setup_url();

			return [
				'link'       => $link,
				'link_class' => '',
				'link_attrs' => [],
			];
		}

		if ( ! $this->is_education_available() ) {
			return $this->get_empty_link();
		}

		$button = WPConsentHelper::get_install_button_data();

		return [
			'link'       => '#',
			'link_class' => 'education-modal',
			'link_attrs' => array_merge( [ 'data-name' => 'WPConsent plugin' ], $button['attrs'] ),
		];
	}

	/**
	 * Build a link pointing at a WPForms settings view, optionally anchored to one row.
	 *
	 * Settings rows are wrapped by `wpforms_settings_output_field()` with an
	 * `id="wpforms-setting-row-{id}"`, so a row id makes a valid fragment. The CAPTCHA view
	 * needs none — the whole view is the CAPTCHA configuration.
	 *
	 * @since 2.0.2
	 *
	 * @param string $view    Settings view slug.
	 * @param string $setting Optional setting id to anchor to.
	 *
	 * @return array
	 */
	private function get_settings_link( string $view, string $setting = '' ): array {

		$link = admin_url( 'admin.php?page=wpforms-settings&view=' . $view );

		if ( $setting !== '' ) {
			$link .= '#wpforms-setting-row-' . $setting;
		}

		return [
			'link'       => $link,
			'link_class' => '',
			'link_attrs' => [],
		];
	}

	/**
	 * A row with no reachable destination — the name renders as plain text.
	 *
	 * @since 2.0.2
	 *
	 * @return array
	 */
	private function get_empty_link(): array {

		return [
			'link'       => '',
			'link_class' => '',
			'link_attrs' => [],
		];
	}

	/**
	 * Get the Additional Information documentation links.
	 *
	 * The first two titles are section headings of the spam-prevention guide, so they link to
	 * anchors; the other two have dedicated articles. The two anchor ids are shorter than
	 * their heading text and were read off the live article — do not derive them from labels.
	 *
	 * @since 2.0.2
	 *
	 * @return array Links: each item has `label` and a UTM-wrapped `url`.
	 */
	private function get_doc_links(): array {

		$links = [
			[
				'label' => __( 'Accessing Spam Protection and Security Settings', 'wpforms-lite' ),
				'url'   => wpforms_utm_link( self::SPAM_GUIDE_URL . '#spam-protection-and-security-settings', self::UTM_MEDIUM, 'Accessing Spam Protection and Security Settings' ),
			],
			[
				'label' => __( 'Enabling Minimum Time to Submit', 'wpforms-lite' ),
				'url'   => wpforms_utm_link( self::SPAM_GUIDE_URL . '#minimum-time', self::UTM_MEDIUM, 'Enabling Minimum Time to Submit' ),
			],
			[
				'label' => __( 'Adding Spam Filters', 'wpforms-lite' ),
				'url'   => wpforms_utm_link( 'https://wpforms.com/docs/adding-spam-filters/', self::UTM_MEDIUM, 'Adding Spam Filters' ),
			],
			[
				'label' => __( 'Creating an Allowlist or Denylist', 'wpforms-lite' ),
				'url'   => wpforms_utm_link( 'https://wpforms.com/docs/how-to-create-an-allowlist-denylist-for-email-addresses-in-wpforms/', self::UTM_MEDIUM, 'Creating an Allowlist or Denylist' ),
			],
		];

		/**
		 * Filter the Spam & Security Checkup documentation links.
		 *
		 * @since 2.0.2
		 *
		 * @param array $links Documentation links: each item has `label` and `url`.
		 */
		return (array) apply_filters( 'wpforms_admin_dashboard_widgets_spam_security_get_doc_links', $links );
	}

	/**
	 * Whether education prompts are available.
	 *
	 * Mirrors `Dashboard\StatCards`: the class is unregistered when the
	 * `wpforms_admin_education` kill switch is off, so install promos must not render.
	 *
	 * @since 2.0.2
	 *
	 * @return bool
	 */
	private function is_education_available(): bool {

		return (bool) wpforms()->obj( 'education_feature_tooltip' );
	}
}

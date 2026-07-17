<?php

namespace WPForms\Admin\Settings\Gdpr;

use WPForms\Education\WPConsent\Helper as WPConsent;

/**
 * "Privacy Compliance" WPConsent cross-promo row in Settings → General GDPR.
 *
 * @since 2.0.0
 */
class WPConsentCallout {

	/**
	 * Init.
	 *
	 * @since 2.0.0
	 */
	public function init() {

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 */
	private function hooks() {

		// Priority 15 — runs after Pro's priority-5 GDPR sub-settings insert.
		add_filter( 'wpforms_settings_defaults', [ $this, 'add_setting' ], 15 );
		add_action( 'wpforms_settings_enqueue', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Insert the Privacy Compliance row after the last GDPR setting.
	 *
	 * @since 2.0.0
	 *
	 * @param array $settings Registered settings.
	 *
	 * @return array
	 */
	public function add_setting( $settings ): array {

		// Guard against a third-party filter passing a non-array; the `: array` return type would otherwise fatal.
		$settings = (array) $settings;

		if ( empty( $settings['general'] ) || ! wpforms_can_install( 'plugin' ) ) {
			return $settings;
		}

		// Anchor after gdpr-disable-details (Pro) when present, else after gdpr.
		$anchor = array_key_exists( 'gdpr-disable-details', $settings['general'] ) ? 'gdpr-disable-details' : 'gdpr';

		$settings['general'] = wpforms_array_insert(
			$settings['general'],
			[
				'gdpr-privacy-compliance' => [
					'id'        => 'gdpr-privacy-compliance',
					'name'      => esc_html__( 'Privacy Compliance', 'wpforms-lite' ),
					'type'      => 'content',
					'content'   => $this->get_button_html() . $this->get_description(),
					'is_hidden' => ! wpforms_setting( 'gdpr' ),
				],
			],
			$anchor
		);

		return $settings;
	}

	/**
	 * Description paragraph shown below the button.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	private function get_description(): string {

		return '<p class="wpforms-setting-desc">' . sprintf(
			wp_kses(
				/* translators: %s - WPConsent learn-more URL. */
				__( 'Improve GDPR, CCPA, and privacy compliance with cookie banner and consent records. <a href="%s" target="_blank" rel="noopener noreferrer" class="wpforms-learn-more">Learn More</a>', 'wpforms-lite' ),
				[
					'a' => [
						'href'   => [],
						'target' => [],
						'rel'    => [],
						'class'  => [],
					],
                ]
			),
			esc_url( wpforms_utm_link( 'https://wpconsent.com/', 'settings-gdpr', 'WPConsent Privacy Compliance' ) )
		) . '</p>';
	}

	/**
	 * Button markup: "Set Up" link when WPConsent is active, else blue install button.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	private function get_button_html(): string {

		if ( WPConsent::is_activated() ) {
			return sprintf(
				'<a href="%s" class="wpforms-btn wpforms-btn-md wpforms-btn-light-grey">%s</a>',
				esc_url( WPConsent::get_setup_url() ),
				esc_html__( 'Set Up WPConsent', 'wpforms-lite' )
			);
		}

		$button_data = WPConsent::get_install_button_data();
		$attrs       = '';

		foreach ( $button_data['attrs'] as $attr_name => $attr_value ) {
			$attrs .= sprintf( ' %s="%s"', esc_attr( $attr_name ), esc_attr( $attr_value ) );
		}

		// Pre-built attribute string is already escaped via esc_attr above.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return sprintf(
			'<button type="button" class="wpforms-btn wpforms-btn-md wpforms-btn-blue wpforms-wpconsent-install" data-setup-url="%s" data-setup-text="%s"%s>%s</button>',
			esc_url( WPConsent::get_setup_url() ),
			esc_attr( __( 'Set Up WPConsent', 'wpforms-lite' ) ),
			$attrs,
			esc_html__( 'Install WPConsent', 'wpforms-lite' )
		);
	}

	/**
	 * Enqueue the inline install handler script on the settings page.
	 *
	 * @since 2.0.0
	 */
	public function enqueue_assets() {

		if ( WPConsent::is_activated() || ! wpforms_can_install( 'plugin' ) ) {
			return;
		}

		$min = wpforms_get_min_suffix();

		wp_enqueue_script(
			'wpforms-wpconsent-install',
			WPFORMS_PLUGIN_URL . "assets/js/admin/education/wpconsent-install{$min}.js",
			[ 'jquery' ],
			WPFORMS_VERSION,
			true
		);

		wp_localize_script(
			'wpforms-wpconsent-install',
			'wpforms_wpconsent_install',
			[ 'ajax_url' => admin_url( 'admin-ajax.php' ) ]
		);
	}
}

<?php

namespace WPForms\Admin\Education\Builder;

use WPForms\Education\WPConsent\Helper as WPConsent;

/**
 * WPConsent cross-promo notice inside the GDPR Agreement field options.
 *
 * @since 2.0.0
 */
class Gdpr {

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

		add_action( 'wpforms_field_options_bottom_basic-options', [ $this, 'maybe_render_notice' ], 10, 1 );
		add_action( 'wpforms_builder_enqueues', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Whether the WPConsent notice should render.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	private function should_render(): bool {

		return ! WPConsent::is_activated() && wpforms_can_install( 'plugin' );
	}

	/**
	 * Render the "Set Up Privacy Compliance" notice below Description for the
	 * GDPR Agreement field only.
	 *
	 * @since 2.0.0
	 *
	 * @param array $field Field data.
	 */
	public function maybe_render_notice( $field ) {

		if ( ! isset( $field['type'] ) || $field['type'] !== 'gdpr-checkbox' || ! $this->should_render() ) {
			return;
		}

		$data       = WPConsent::get_install_button_data();
		$attrs_html = '';

		foreach ( $data['attrs'] as $key => $value ) {
			$attrs_html .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( $value ) );
		}

		printf(
			'<div class="wpforms-alert wpforms-alert-info wpforms-gdpr-wpconsent-notice">
				<h4 class="wpforms-alert-heading">%1$s</h4>
				<p>%2$s</p>
				<button type="button" class="wpforms-btn wpforms-btn-sm wpforms-btn-blue wpforms-wpconsent-install" data-setup-url="%3$s" data-setup-text="%4$s"%5$s>%6$s</button>
			</div>',
			esc_html__( 'Set Up Privacy Compliance', 'wpforms-lite' ),
			esc_html__( 'Improve GDPR, CCPA, and privacy compliance with cookie banner and consent records.', 'wpforms-lite' ),
			esc_url( WPConsent::get_setup_url() ),
			esc_attr__( 'Set Up WPConsent', 'wpforms-lite' ),
			$attrs_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each attr key/value escaped above.
			esc_html__( 'Install WPConsent', 'wpforms-lite' )
		);
	}

	/**
	 * Enqueue the inline install handler in the builder.
	 *
	 * @since 2.0.0
	 */
	public function enqueue_assets() {

		if ( ! $this->should_render() ) {
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

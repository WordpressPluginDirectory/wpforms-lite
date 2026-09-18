<?php

namespace WPForms\Admin\Education\Builder;

use WPForms_Builder_Panel_Settings;
use WPForms\Admin\Builder\Settings\QrCode as QrCodeSettings;
use WPForms\Admin\Education\EducationInterface;

/**
 * QR Code Logo Education feature for every plan below Pro.
 *
 * @since 2.0.1
 */
class QrCode implements EducationInterface {

	/**
	 * Indicate if the current Education feature is allowed to load.
	 *
	 * @since 2.0.1
	 *
	 * @return bool
	 */
	public function allow_load() {

		if ( QrCodeSettings::is_logo_allowed() ) {
			return false;
		}

		return wpforms_is_admin_page( 'builder' ) || wp_doing_ajax();
	}

	/**
	 * Init.
	 *
	 * @since 2.0.1
	 */
	public function init() {

		if ( ! $this->allow_load() ) {
			return;
		}

		$this->hooks();
	}

	/**
	 * Load hooks.
	 *
	 * @since 2.0.1
	 */
	private function hooks() {

		add_action( 'wpforms_admin_builder_settings_qr_code_logo', [ $this, 'logo_settings' ] );
		add_action( 'wp_ajax_wpforms_qr_code_logo_upsell', [ $this, 'ajax_upsell_event' ] );
	}

	/**
	 * Bump a Pro logo upsell funnel counter.
	 *
	 * @since 2.0.1
	 */
	public function ajax_upsell_event() {

		check_ajax_referer( 'wpforms-builder', 'nonce' );

		if ( ! wpforms_current_user_can( 'edit_forms' ) ) {
			wp_send_json_error();
		}

		$event = sanitize_key( wp_unslash( $_POST['event'] ?? '' ) );

		if ( ! in_array( $event, QrCodeSettings::LOGO_UPSELL_EVENTS, true ) ) {
			wp_send_json_error();
		}

		$events           = QrCodeSettings::get_logo_upsell_events();
		$events[ $event ] = (int) ( $events[ $event ] ?? 0 ) + 1;

		update_option( QrCodeSettings::LOGO_UPSELL_EVENTS_OPTION, $events, false );

		wp_send_json_success();
	}

	/**
	 * Render the locked QR Code Logo control: below Pro the logo is fixed to WPForms.
	 *
	 * @since 2.0.1
	 *
	 * @param WPForms_Builder_Panel_Settings $settings Builder panel settings.
	 */
	public function logo_settings( $settings ) {

		wpforms_panel_field(
			'select',
			'settings',
			'qr_code_logo',
			$settings->form_data,
			esc_html__( 'Logo', 'wpforms-lite' ),
			[
				'class'       => 'wpforms-panel-field-qr-code-logo',
				'input_class' => 'education-modal',
				'options'     => [
					'wpforms' => esc_html__( 'WPForms', 'wpforms-lite' ),
				],
				'value'       => 'wpforms',
				'pro_badge'   => true,
				'data'        => [
					'action'      => 'upgrade',
					'name'        => esc_html__( 'QR Code Logo', 'wpforms-lite' ),
					'utm-content' => 'QR Code Logo',
					'licence'     => 'pro',
				],
				'after'       => '<p class="note">' . esc_html__( 'Choose the image shown in the middle.', 'wpforms-lite' ) . '</p>',
			]
		);
	}
}

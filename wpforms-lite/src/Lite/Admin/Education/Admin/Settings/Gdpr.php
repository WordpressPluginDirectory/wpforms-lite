<?php

namespace WPForms\Lite\Admin\Education\Admin\Settings;

use WPForms\Admin\Education\Helpers as EducationHelpers;

/**
 * GDPR education feature for the Lite plugin.
 *
 * Injects Pro-only GDPR sub-settings into the General settings view as
 * grayed-out toggles with a Pro badge. Clicking the row opens the standard
 * education upgrade modal.
 *
 * @since 1.10.1
 */
class Gdpr {

	/**
	 * Initialize the class.
	 *
	 * @since 1.10.1
	 */
	public function init() {

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.10.1
	 */
	private function hooks() {

		add_filter( 'wpforms_settings_defaults', [ $this, 'add_sub_settings' ] );
	}

	/**
	 * Adds Pro-only GDPR sub-settings after the GDPR toggle in the General view.
	 *
	 * @since 1.10.1
	 *
	 * @param array $defaults Registered settings defaults.
	 *
	 * @return array
	 */
	public function add_sub_settings( $defaults ): array {

		$defaults = (array) $defaults;

		if ( empty( $defaults['general']['gdpr'] ) ) {
			return $defaults;
		}

		$defaults['general'] = wpforms_array_insert(
			$defaults['general'],
			$this->get_sub_settings(),
			'gdpr'
		);

		return $defaults;
	}

	/**
	 * Build the Pro-only GDPR sub-setting stubs.
	 *
	 * @since 1.10.1
	 *
	 * @return array
	 */
	private function get_sub_settings(): array {

		$badge    = EducationHelpers::get_badge( 'Pro' );
		$row_args = [ 'action' => 'upgrade' ];

		return [
			'gdpr-disable-uuid'    => [
				'id'              => 'gdpr-disable-uuid',
				'name'            => esc_html__( 'Disable User Cookies', 'wpforms-lite' ),
				'desc'            => esc_html__( 'Disable user tracking cookies. This will disable the Related Entries feature and the Form Abandonment addon.', 'wpforms-lite' ),
				'type'            => 'toggle',
				'status'          => false,
				'disabled'        => true,
				'class'           => [ 'education-modal' ],
				'education_badge' => $badge,
				'data_attributes' => array_merge( [ 'name' => esc_html__( 'Disable User Cookies', 'wpforms-lite' ) ], $row_args ),
			],
			'gdpr-disable-details' => [
				'id'              => 'gdpr-disable-details',
				'name'            => esc_html__( 'Disable User Details', 'wpforms-lite' ),
				'desc'            => esc_html__( 'Disable storage IP addresses and User Agent on all forms. If unchecked, then this can be managed on a form-by-form basis inside the form builder under Settings → General', 'wpforms-lite' ),
				'type'            => 'toggle',
				'status'          => false,
				'disabled'        => true,
				'class'           => [ 'education-modal' ],
				'education_badge' => $badge,
				'data_attributes' => array_merge( [ 'name' => esc_html__( 'Disable User Details', 'wpforms-lite' ) ], $row_args ),
			],
		];
	}
}

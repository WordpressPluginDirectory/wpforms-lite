<?php

namespace WPForms\Integrations\PayPalCommerce\Admin\Builder;

use WPForms\Integrations\PayPalCommerce\Helpers;

/**
 * Script enqueues for the PayPalCommerce Builder settings panel.
 *
 * @since 1.10.0
 */
class Enqueues {

	/**
	 * Initialize.
	 *
	 * @since 1.10.0
	 */
	public function init(): void {

		$this->hooks();
	}

	/**
	 * Builder hooks.
	 *
	 * @since 1.10.0
	 */
	private function hooks(): void {

		add_action( 'wpforms_builder_enqueues', [ $this, 'enqueues' ] );
		add_filter( 'wpforms_builder_strings',  [ $this, 'javascript_strings' ] );
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 1.10.0
	 */
	public function enqueues(): void {

		$min = wpforms_get_min_suffix();

		wp_enqueue_script(
			'wpforms-builder-paypal-commerce',
			WPFORMS_PLUGIN_URL . "assets/js/integrations/paypal-commerce/builder-paypal-commerce{$min}.js",
			[ 'wpforms-builder' ],
			WPFORMS_VERSION,
			true
		);

		wp_enqueue_style(
			'wpforms-builder-paypal-commerce',
			WPFORMS_PLUGIN_URL . "assets/css/integrations/paypal-commerce/builder-paypal-commerce{$min}.css",
			[],
			WPFORMS_VERSION
		);
	}

	/**
	 * Add localized strings.
	 *
	 * @since 1.10.0
	 *
	 * @param array $strings Form builder JS strings.
	 *
	 * @return array
	 *
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function javascript_strings( $strings ): array {

		$strings = (array) $strings;

		$strings['paypal_commerce_connection_required'] = '<p>' . esc_html__( 'PayPal account connection is required when using the PayPal field. Connect your PayPal account to start accepting payments.', 'wpforms-lite' ) . '</p>';

		$strings['paypal_commerce_connect_button'] = esc_html__( 'Connect With PayPal', 'wpforms-lite' );

		$strings['paypal_commerce_payments_enabled_required'] = '<p>' . esc_html__( 'PayPal Payments must be enabled when using the PayPal Commerce field. Enable PayPal Payments in this form to start accepting payments.', 'wpforms-lite' ) . '</p>';

		$strings['paypal_commerce_enable_payments_button'] = esc_html__( 'Enable PayPal Payments', 'wpforms-lite' );

		$strings['paypal_commerce_ajax_required'] = wp_kses(
			__( '<p>AJAX form submissions are required when using the PayPal Commerce field.</p><p>To proceed, please go to <strong>Settings » General</strong> and check <strong>Enable AJAX form submission</strong>.</p>', 'wpforms-lite' ),
			[
				'p'      => [],
				'strong' => [],
			]
		);

		$strings['paypal_commerce_plan_name_disabled']       = esc_html__( 'The plan name can’t be changed once you save it. Please create a new plan.', 'wpforms-lite' );
		$strings['paypal_commerce_product_type_disabled']    = esc_html__( 'The product type can’t be changed once you save it. Please create a new plan.', 'wpforms-lite' );
		$strings['paypal_commerce_recurring_times_disabled'] = esc_html__( 'The recurring plan can’t be changed once you save it. Please create a new plan.', 'wpforms-lite' );
		$strings['paypal_commerce_fastlane_cc_warning']      = esc_html__( 'Credit Card and Fastlane cannot be enabled at the same time.', 'wpforms-lite' );
		$strings['paypal_commerce_is_pro']                   = Helpers::is_pro();

		return $strings;
	}
}

<?php

namespace WPForms\Integrations\PayPalCommerce;

/**
 * Form Analytics integration for the PayPal Commerce field.
 *
 * Registers the field as a composite analytics field so engagement is tracked
 * per payment method — the method selector ( `payment_method` ) plus one
 * subfield for each enabled method ( PayPal Checkout, Credit Card, Fastlane ) —
 * instead of a single aggregate row. The subfield keys mirror the frontend
 * tracker ( the method selector option values ).
 *
 * @since 2.0.0
 */
class Analytics {

	/**
	 * Field type slug.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	const FIELD_TYPE = 'paypal-commerce';

	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 */
	public function hooks(): void {

		add_filter( 'wpforms_frontend_analytics_composite_field_types', [ $this, 'register_composite_field_type' ] );
		add_filter( 'wpforms_pro_analytics_subfield_labels_resolve', [ $this, 'resolve_subfield_labels' ], 10, 2 );
		add_filter( 'wpforms_pro_analytics_subfield_labels_field_label', [ $this, 'filter_field_label' ], 10, 2 );
	}

	/**
	 * Use the field-type name as the analytics field-level label.
	 *
	 * The form label of a PayPal Commerce field is often the method-selector name
	 * ( e.g. "Payment Method" ), which reads as a redundant prefix in the subfield
	 * breakdown. Showing "PayPal Commerce" instead makes the rows self-describing
	 * ( "PayPal Commerce: Credit Card" rather than "Payment Method: Credit Card" ).
	 *
	 * @since 2.0.0
	 *
	 * @param string|mixed $label Field label.
	 * @param array        $field Field definition from form_data.
	 *
	 * @return string
	 *
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function filter_field_label( $label, $field ): string {

		$field = (array) $field;

		if ( ( $field['type'] ?? '' ) !== self::FIELD_TYPE ) {
			return (string) $label;
		}

		return __( 'PayPal Commerce', 'wpforms-lite' );
	}

	/**
	 * Register PayPal Commerce as a composite field type for per-subfield tracking.
	 *
	 * @since 2.0.0
	 *
	 * @param array|mixed $types Composite field type slugs.
	 *
	 * @return array
	 *
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function register_composite_field_type( $types ): array {

		$types = (array) $types;

		$types[] = self::FIELD_TYPE;

		return $types;
	}

	/**
	 * Map PayPal Commerce subfield keys to human-readable labels.
	 *
	 * Keys mirror the frontend tracker: `payment_method` for the method selector
	 * ( present only when two or more methods are enabled ), one key per enabled
	 * method ( matching the selector option values ), and `cardname` for the
	 * cardholder-name input when it is rendered.
	 *
	 * @since 2.0.0
	 *
	 * @param array|mixed $labels Map of subfield_key => label.
	 * @param array       $field  Field definition from form_data.
	 *
	 * @return array
	 *
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function resolve_subfield_labels( $labels, $field ): array {

		$labels = (array) $labels;
		$field  = (array) $field;

		if ( ( $field['type'] ?? '' ) !== self::FIELD_TYPE ) {
			return $labels;
		}

		$methods = $this->get_enabled_method_labels( $field );

		// The method selector renders only when two or more methods are available.
		if ( count( $methods ) >= 2 ) {
			$labels['payment_method'] = __( 'Payment Method', 'wpforms-lite' );
		}

		$labels += $methods;

		// The cardholder-name input ( wpforms[fields][{id}][cardname] ) is the only
		// per-input row in the card section — the number / expiry / CVV hosted fields
		// are cross-origin iframes folded into the credit_card method row. It renders
		// only when both the Credit Card method and the Card Holder Name toggle are on
		// ( see Fields\PayPalCommerce::field_properties() ), so gate the label the same
		// way to avoid seeding a phantom row when the input is absent.
		if ( isset( $field['credit_card'], $field['card_holder_enable'] ) ) {
			$labels['cardname'] = __( 'Card Holder Name', 'wpforms-lite' );
		}

		return $labels;
	}

	/**
	 * Build the enabled-method label map for a PayPal Commerce field.
	 *
	 * Labels are intentionally left unescaped — each consumer escapes at its own
	 * output boundary ( consistent with the core `SubfieldLabels` resolvers ).
	 *
	 * @since 2.0.0
	 *
	 * @param array $field Field definition from form_data.
	 *
	 * @return array
	 */
	private function get_enabled_method_labels( array $field ): array {

		$labels = [];

		if ( ! empty( $field['paypal_checkout'] ) ) {
			$labels['checkout'] = __( 'PayPal Checkout', 'wpforms-lite' );
		}

		if ( ! empty( $field['credit_card'] ) ) {
			$labels['credit_card'] = __( 'Credit Card', 'wpforms-lite' );
		}

		if ( ! empty( $field['fastlane'] ) ) {
			$labels['fastlane'] = __( 'Fastlane', 'wpforms-lite' );
		}

		return $labels;
	}
}

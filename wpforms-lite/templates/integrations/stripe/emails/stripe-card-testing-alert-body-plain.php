<?php
/**
 * Stripe card testing alert email body template (plain text).
 *
 * This template can be overridden by copying it to yourtheme/wpforms/emails/stripe-card-testing-alert-body-plain.php.
 *
 * @since 2.0.0
 *
 * @var string $threshold_formatted Formatted amount threshold (e.g. "$3.00").
 * @var string $duration_formatted  Human-readable block duration (e.g. "5 mins", "1 hour").
 * @var string $forms_url           URL to the WPForms forms overview.
 * @var array  $affected_forms      Forms targeted by the surge, each [ 'title' => string, 'url' => string ].
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo esc_html__( 'Possible Card Testing Detected by WPForms', 'wpforms-lite' ) . "\n\n";

echo esc_html__( 'WPForms has detected an unusual number of low-amount Stripe payment attempts on your site within a short period of time. This pattern is often associated with card testing — automated probing of stolen card numbers using low-value charges to avoid detection.', 'wpforms-lite' ) . "\n\n";

printf(
	/* translators: %1$s - formatted threshold amount (e.g. $3.00), %2$s - human-readable block duration (e.g. "5 mins", "1 hour"). */
	esc_html__( 'As a precaution, WPForms has temporarily blocked all Stripe payments with an amount at or below %1$s for the next %2$s. Payments above this threshold continue to process normally. The block will lift automatically.', 'wpforms-lite' ),
	esc_html( $threshold_formatted ),
	esc_html( $duration_formatted )
);
echo "\n\n";

echo esc_html__( 'Recommended Action: Enable the "Minimum Price" option on any Payment Single Item fields used in your forms to make low-amount card testing unprofitable for attackers.', 'wpforms-lite' ) . "\n\n";

if ( ! empty( $affected_forms ) ) {
	echo esc_html__( 'Affected forms:', 'wpforms-lite' ) . "\n";

	foreach ( $affected_forms as $affected_form ) {
		echo '- ' . esc_html( $affected_form['title'] ) . ': ' . esc_url( $affected_form['url'] ) . "\n";
	}

	echo "\n";
}

echo esc_html__( 'Review your forms:', 'wpforms-lite' ) . ' ' . esc_url( $forms_url ) . "\n";

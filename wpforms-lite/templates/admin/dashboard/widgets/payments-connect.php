<?php
/**
 * Dashboard "Payments" widget — connect band (no gateway configured).
 *
 * @since 2.0.2
 *
 * @var string $connect_url       URL for the "Connect with Stripe" button.
 * @var string $settings_url      URL for the "Payment Settings" footer link.
 * @var bool   $show_mercado_pago Whether the footer mentions Mercado Pago (site currency is one it can process).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$images_url = WPFORMS_PLUGIN_URL . 'assets/images/empty-states/payments/';

// Brand marks precede PayPal and Square in the footer disclaimer; the names are
// brands, not translatable copy.
$paypal = sprintf(
	'<img src="%s" alt="" width="16" height="16"><span class="wpforms-dashboard-payments-connect-brand">PayPal</span>',
	esc_url( $images_url . 'paypal-brand-icon.png' )
);

$square = sprintf(
	'<img src="%s" alt="" width="16" height="16"><span class="wpforms-dashboard-payments-connect-brand">Square</span>',
	esc_url( $images_url . 'square-brand-icon.svg' )
);

// Authorize.Net and Mercado Pago have no 16px brand mark, but their names read in
// the same color as the branded ones.
$authorize = '<span class="wpforms-dashboard-payments-connect-brand">Authorize.Net</span>';

$mercado_pago = '<span class="wpforms-dashboard-payments-connect-brand">Mercado Pago</span>';

$settings_link = sprintf(
	'<a href="%s">%s</a>',
	esc_url( $settings_url ),
	esc_html__( 'Payment Settings', 'wpforms-lite' )
);
?>
<div class="wpforms-dashboard-payments-connect">
	<div class="wpforms-dashboard-payments-connect-hero">
		<div class="wpforms-dashboard-payments-connect-headline">
			<p class="wpforms-dashboard-payments-connect-lead">
				<img
					src="<?php echo esc_url( $images_url . 'stripe-brand-icon.svg' ); ?>"
					alt="<?php esc_attr_e( 'Stripe', 'wpforms-lite' ); ?>"
					class="wpforms-dashboard-payments-connect-logo"
				>
				<span class="wpforms-dashboard-payments-connect-sep" aria-hidden="true">&mdash;</span>
				<span class="wpforms-dashboard-payments-connect-title">
					<?php esc_html_e( 'Securely Accept Credit Card Payments', 'wpforms-lite' ); ?>
				</span>
			</p>

			<p class="wpforms-dashboard-payments-connect-description">
				<?php esc_html_e( 'Accept credit card payments, Apple Pay, Google Pay, ACH, and more with WPForms Stripe integration.', 'wpforms-lite' ); ?>
			</p>
		</div>

		<a href="<?php echo esc_url( $connect_url ); ?>" class="wpforms-btn wpforms-btn-sm wpforms-dashboard-payments-connect-cta">
			<img
				src="<?php echo esc_url( $images_url . 'stripe-connect-icon.svg' ); ?>"
				alt=""
				class="wpforms-dashboard-payments-connect-cta-icon"
			>
			<span class="wpforms-dashboard-payments-connect-cta-divider" aria-hidden="true"></span>
			<span class="wpforms-dashboard-payments-connect-cta-label">
				<?php esc_html_e( 'Connect with Stripe', 'wpforms-lite' ); ?>
			</span>
		</a>
	</div>

	<p class="wpforms-dashboard-payments-connect-footer">
		<i class="fa fa-info-circle wpforms-dashboard-payments-connect-footer-icon" aria-hidden="true"></i>
		<span class="wpforms-dashboard-payments-connect-footer-text">
			<?php
			$allowed_tags = [
				'a'    => [
					'href' => [],
				],
				'img'  => [
					'src'    => [],
					'alt'    => [],
					'width'  => [],
					'height' => [],
				],
				'span' => [
					'class' => [],
				],
			];

			if ( $show_mercado_pago ) {
				echo wp_kses( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized by wp_kses(); parts escaped above.
					sprintf(
						/* translators: 1: PayPal brand (icon + name), 2: Square brand (icon + name), 3: Authorize.Net brand name, 4: Mercado Pago brand name, 5: Payment Settings page link. */
						__( 'WPForms also supports %1$s, %2$s, %3$s, and %4$s. Go to %5$s to configure.', 'wpforms-lite' ),
						$paypal,
						$square,
						$authorize,
						$mercado_pago,
						$settings_link
					),
					$allowed_tags
				);
			} else {
				echo wp_kses( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized by wp_kses(); parts escaped above.
					sprintf(
						/* translators: 1: PayPal brand (icon + name), 2: Square brand (icon + name), 3: Authorize.Net brand name, 4: Payment Settings page link. */
						__( 'WPForms also supports %1$s, %2$s, and %3$s. Go to %4$s to configure.', 'wpforms-lite' ),
						$paypal,
						$square,
						$authorize,
						$settings_link
					),
					$allowed_tags
				);
			}
			?>
		</span>
	</p>
</div>

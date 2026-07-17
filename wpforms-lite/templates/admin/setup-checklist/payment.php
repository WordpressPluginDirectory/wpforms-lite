<?php
/**
 * Setup Checklist payment gateway item — the Stripe hero block.
 *
 * @since 2.0.0
 *
 * @var array  $gateway             Stripe gateway tile data.
 * @var bool   $is_stripe_connected Whether Stripe is already configured.
 * @var bool   $is_pro_plus         Whether the license is Pro or Elite.
 * @var string $settings_url        Payments settings page URL.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="wpforms-setup-checklist-payment">
	<div class="wpforms-setup-checklist-payment__stripe">
		<div class="wpforms-setup-checklist-payment__info">
			<img class="wpforms-setup-checklist-payment__icon" src="<?php echo esc_url( $gateway['icon'] ); ?>" alt="<?php echo esc_attr( $gateway['icon_alt'] ); ?>" width="100" height="100">
			<div class="wpforms-setup-checklist-payment__text">
				<h3 class="wpforms-setup-checklist-payment__heading">
					<img class="wpforms-setup-checklist-payment__brand" src="<?php echo esc_url( $gateway['brand'] ); ?>" alt="<?php echo esc_attr( $gateway['name'] ); ?>" width="97" height="40">
					<span class="wpforms-setup-checklist-payment__dash" aria-hidden="true">&mdash;</span>
					<span class="wpforms-setup-checklist-payment__tagline"><?php echo esc_html( $gateway['tagline'] ); ?></span>
				</h3>
				<p class="wpforms-setup-checklist-payment__desc"><?php echo esc_html( $gateway['description'] ); ?></p>
				<?php if ( $is_pro_plus ) : ?>
					<p class="wpforms-setup-checklist-payment__fees">
						<?php
						printf(
							wp_kses(
								/* translators: %1$s - Coupons, %2$s - Calculations (each the emphasised addon name). */
								__( 'Works seamlessly with the %1$s and %2$s addons.', 'wpforms-lite' ),
								[ 'strong' => [] ]
							),
							'<strong>Coupons</strong>',
							'<strong>Calculations</strong>'
						);
						?>
					</p>
				<?php else : ?>
					<p class="wpforms-setup-checklist-payment__fees">
						<?php esc_html_e( 'Note: 3% platform fees apply that can be removed by', 'wpforms-lite' ); ?>
						<a class="wpforms-setup-checklist-payment__fees-link wpforms-upgrade-modal" href="<?php echo esc_url( wpforms_admin_upgrade_link( 'Setup Checklist', 'Payment Gateway Upgrade' ) ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Upgrading to Pro', 'wpforms-lite' ); ?>
						</a>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php if ( ! $is_stripe_connected ) : ?>
			<a class="wpforms-btn wpforms-btn-sm wpforms-setup-checklist-payment__btn" href="<?php echo esc_url( $gateway['url'] ); ?>">
				<img class="wpforms-setup-checklist-payment__btn-icon" src="<?php echo esc_url( $gateway['btn_icon'] ); ?>" alt="" width="12" height="16">
				<span class="wpforms-setup-checklist-payment__btn-divider" aria-hidden="true"></span>
				<span class="wpforms-setup-checklist-payment__btn-text"><?php echo esc_html( $gateway['btn_text'] ); ?></span>
				<svg class="wpforms-setup-checklist-payment__btn-spinner" width="16" height="16" viewBox="0 0 80 80" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path fill="currentColor" opacity=".25" d="M40 0C17.9 0 0 17.9 0 40s17.9 40 40 40 40-17.9 40-40S62.1 0 40 0zm0 72C22.3 72 8 57.7 8 40S22.3 8 40 8s32 14.3 32 32-14.3 32-32 32z"/><path fill="currentColor" d="M75.8 47.4h-.4c-2.2-.2-3.8-2.2-3.6-4.4.1-1 .1-2 .1-3C72 22.4 57.6 8 40 8c-2.2 0-4-1.8-4-4s1.8-4 4-4c22.1 0 40 17.9 40 40 0 1.3-.1 2.5-.2 3.8-.2 2.1-1.9 3.6-4 3.6z"/></svg>
			</a>
		<?php endif; ?>
	</div>
	<p class="wpforms-setup-checklist-payment__other">
		<i class="wpforms-setup-checklist-payment__other-icon fa-solid fa-circle-info" aria-hidden="true"></i>
		<span>
		<?php
		$paypal_icon = sprintf(
			'<img class="wpforms-setup-checklist-payment__gateway-icon" src="%s" alt="" width="13" height="16">',
			esc_url( WPFORMS_PLUGIN_URL . 'assets/images/setup-checklist/paypal-icon.png' )
		);

		$square_icon = sprintf(
			'<img class="wpforms-setup-checklist-payment__gateway-icon" src="%s" alt="" width="16" height="16">',
			esc_url( WPFORMS_PLUGIN_URL . 'assets/images/setup-checklist/square-icon.png' )
		);

		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $settings_url ),
			esc_html__( 'Payment Settings', 'wpforms-lite' )
		);

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		printf(
			wp_kses(
				/* translators: %1$s - PayPal icon, %2$s - Square icon, %3$s - Payment Settings link. */
				__( 'WPForms also supports other payment gateways, including %1$s PayPal, %2$s Square, and Authorize.net which can be configured from %3$s.', 'wpforms-lite' ),
				[
					'img' => [
						'class'  => [],
						'src'    => [],
						'alt'    => [],
						'width'  => [],
						'height' => [],
					],
					'a'   => [
						'href' => [],
					],
				]
			),
			$paypal_icon,
			$square_icon,
			$settings_link
		);
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		</span>
	</p>
</div>

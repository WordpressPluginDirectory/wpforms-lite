<?php
/**
 * Stripe card testing alert email body template.
 *
 * This template can be overridden by copying it to yourtheme/wpforms/emails/stripe-card-testing-alert-body.php.
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

// Text/HTML alignment for the notification copy (RTL-aware).
$text_align = is_rtl() ? 'right' : 'left';

?>

<table class="summary-container" border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
	<tbody>
		<tr>
			<td class="summary-content" bgcolor="#ffffff">
				<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
					<tbody>
						<tr>
							<td><!-- Deliberately empty to support consistent sizing and layout across multiple email clients. --></td>
							<td class="summary-content-inner" align="<?php echo esc_attr( $text_align ); ?>" valign="top" width="600">
								<div class="summary-header" width="100%">
									<h6 class="greeting"><?php esc_html_e( 'Possible card testing detected', 'wpforms-lite' ); ?></h6>
									<p style="text-align: <?php echo esc_attr( $text_align ); ?>;">
										<?php esc_html_e( 'WPForms has detected an unusual number of low-amount Stripe payment attempts on your site within a short period of time. This pattern is often associated with card testing — automated probing of stolen card numbers using low-value charges to avoid detection.', 'wpforms-lite' ); ?>
									</p>
									<p style="text-align: <?php echo esc_attr( $text_align ); ?>;">
										<?php
										printf(
											/* translators: %1$s - formatted threshold amount (e.g. $3.00), %2$s - human-readable block duration (e.g. "5 mins", "1 hour"). */
											esc_html__( 'As a precaution, WPForms has temporarily blocked all Stripe payments with an amount at or below %1$s for the next %2$s. Payments above this threshold continue to process normally. The block will lift automatically.', 'wpforms-lite' ),
											'<strong>' . esc_html( $threshold_formatted ) . '</strong>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
											'<strong>' . esc_html( $duration_formatted ) . '</strong>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										);
										?>
									</p>
									<p style="text-align: <?php echo esc_attr( $text_align ); ?>;">
										<strong><?php esc_html_e( 'Recommended action:', 'wpforms-lite' ); ?></strong>
										<?php esc_html_e( 'Enable the "Minimum Price" option on any Payment Single Item fields used in your forms to make low-amount card testing unprofitable for attackers.', 'wpforms-lite' ); ?>
									</p>
								</div>

								<div class="email-summaries-wrapper" width="100%">
									<?php if ( ! empty( $affected_forms ) ) : ?>
										<table class="email-summaries" border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
											<tbody>
												<tr>
													<td align="<?php echo esc_attr( $text_align ); ?>" valign="top" style="background-color: #f8f8f8; border: 1px solid #dddddd; font-size: 16px; font-weight: bold; line-height: 16px; padding: 15px 20px;"><?php esc_html_e( 'Affected forms', 'wpforms-lite' ); ?></td>
												</tr>
												<?php foreach ( $affected_forms as $affected_form ) : ?>
													<tr>
														<td class="form-name" align="<?php echo esc_attr( $text_align ); ?>" valign="middle">
															<a href="<?php echo esc_url( $affected_form['url'] ); ?>" rel="noopener noreferrer" target="_blank" style="color: #e27730; text-decoration: none;"><?php echo esc_html( $affected_form['title'] ); ?></a>
														</td>
													</tr>
												<?php endforeach; ?>
											</tbody>
										</table>
										<div style="line-height: 20px; font-size: 20px;">&nbsp;</div>
									<?php endif; ?>
									<table class="button-wrapper" border="0" cellpadding="0" cellspacing="24" role="presentation" align="center">
										<tr>
											<td class="button button-orange" align="center" border="1" valign="middle">
												<a href="<?php echo esc_url( $forms_url ); ?>" class="button-link" rel="noopener noreferrer" target="_blank" bgcolor="#e27730">
													<?php esc_html_e( 'Review your forms', 'wpforms-lite' ); ?>
												</a>
											</td>
										</tr>
									</table>
								</div>
							</td>
							<td><!-- Deliberately empty to support consistent sizing and layout across multiple email clients. --></td>
						</tr>
					</tbody>
				</table>
			</td>
		</tr>
	</tbody>
</table>

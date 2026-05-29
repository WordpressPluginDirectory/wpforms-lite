<?php
/**
 * Email Summary body template.
 *
 * This template can be overridden by copying it to yourtheme/wpforms/emails/summary-body.php.
 *
 * @since 1.5.4
 * @since 1.8.8 Added `$overview`, `$has_trends`, `$notification_block`, and `$icons` parameters.
 *
 * @var array $overview           Form entries overview data.
 * @var array $entries            Form entries data to loop through.
 * @var bool  $has_trends         Whether trends data is available.
 * @var array $notification_block Notification block shown before the Info block.
 * @var array $info_block         Info block shown at the end of the email.
 * @var array $icons              Icons used for the design purposes.
 */

use WPForms\Integrations\LiteConnect\LiteConnect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$utm_medium = 'Weekly Summary Email';

?>

<table class="summary-container" border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
	<tbody>
		<tr>
			<td class="summary-content" bgcolor="#ffffff">
				<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
					<tbody>
						<tr>
							<td><!-- Deliberately empty to support consistent sizing and layout across multiple email clients. --></td>
							<td class="summary-content-inner" align="center" valign="top" width="600">
								<div class="summary-header" width="100%">
									<h2>
										<?php esc_html_e( 'Your Weekly WPForms Summary', 'wpforms-lite' ); ?>
									</h2>
									<p>
										<?php echo wp_kses_post( __( 'Here’s how your forms performed this past week.<br>Below is a breakdown of submissions by form.', 'wpforms-lite' ) ); ?>
									</p>
								</div>
								<div class="email-summaries-overview-wrapper" width="100%">
									<?php if ( isset( $overview['total'] ) ) : ?>
										<table class="email-summaries-overview" border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" bgcolor="#f8f8f8">
											<tbody>
											<tr>
												<td class="overview-icon" valign="top">
													<img src="<?php echo esc_url( $icons['overview'] ); ?>" width="52" height="52" alt="<?php esc_attr_e( 'Overview', 'wpforms-lite' ); ?>" />
												</td>
												<td class="overview-stats" valign="top">
													<h5>
														<?php
														printf(
														/* translators: %1$d - number of entries. */
															esc_html__( '%1$d Total', 'wpforms-lite' ),
															absint( $overview['total'] )
														);
														?>
													</h5>
													<p>
														<?php echo wp_kses( _n( 'Entry This Week', 'Entries This Week', absint( $overview['total'] ), 'wpforms-lite' ), [] ); ?>
													</p>
												</td>
												<?php if ( isset( $overview['trends'] ) ) : ?>
													<td class="summary-trend">
														<table class="trend-<?php echo esc_attr( (int) $overview['trends'] < 0 ? 'downward' : 'upward' ); ?>" border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
															<tr valign="middle">
																<td valign="middle">
																	<img src="<?php echo esc_url( $icons[ (int) $overview['trends'] < 0 ? 'downward' : 'upward' ] ); ?>" width="14" height="18" alt="<?php echo esc_attr( (int) $overview['trends'] < 0 ? 'downward' : 'upward' ); ?>" />
																</td>
																<td dir="ltr" valign="middle">
																	<?php echo esc_html( $overview['trends'] ); ?>
																</td>
															</tr>
														</table>
													</td>
												<?php endif; ?>
											</tr>
											</tbody>
										</table>
									<?php endif; ?>
								</div>
								<div class="email-summaries-wrapper" width="100%">
									<table class="email-summaries" border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
										<thead>
										<tr>
											<th align="<?php echo is_rtl() ? 'right' : 'left'; ?>" valign="top"><?php esc_html_e( 'Form', 'wpforms-lite' ); ?></th>
											<th class="entries-column" align="<?php echo is_rtl() ? 'right' : 'left'; ?>" valign="top" colspan="2"><?php esc_html_e( 'Entries', 'wpforms-lite' ); ?></th>
										</tr>
										</thead>
										<tbody>
										<?php foreach ( $entries as $row ) : ?>
											<tr id="form-<?php echo esc_attr( $row['form_id'] ); ?>">
												<td class="form-name" valign="middle"><?php echo isset( $row['title'] ) ? esc_html( $row['title'] ) : ''; ?></td>
												<td class="entry-count" align="center" valign="middle">
													<?php if ( empty( $row['edit_url'] ) ) : ?>
														<span>
																<?php echo isset( $row['count'] ) ? absint( $row['count'] ) : ''; ?>
															</span>
													<?php else : ?>
														<a href="<?php echo esc_url( $row['edit_url'] ); ?>">
															<?php echo isset( $row['count'] ) ? absint( $row['count'] ) : ''; ?>
														</a>
													<?php endif; ?>
												</td>
												<?php if ( $has_trends ) : ?>
													<td class="summary-trend" align="center" valign="middle">
														<?php if ( isset( $row['trends'] ) ) : ?>
															<table class="trend-<?php echo esc_attr( (int) $row['trends'] < 0 ? 'downward' : 'upward' ); ?>" border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
																<tr valign="middle">
																	<td valign="middle">
																		<img src="<?php echo esc_url( $icons[ (int) $row['trends'] < 0 ? 'downward' : 'upward' ] ); ?>" width="10" height="13" alt="<?php echo esc_attr( (int) $row['trends'] < 0 ? 'downward' : 'upward' ); ?>" />
																	</td>
																	<td dir="ltr" valign="middle">
																		<?php echo esc_html( $row['trends'] ); ?>
																	</td>
																</tr>
															</table>
														<?php else : ?>
															&mdash;
														<?php endif; ?>
													</td>
												<?php endif; ?>
											</tr>
										<?php endforeach; ?>

										<?php if ( empty( $entries ) ) : ?>
											<tr>
												<td colspan="3">
													<?php esc_html_e( 'It appears you do not have any form entries yet.', 'wpforms-lite' ); ?>
												</td>
											</tr>
										<?php endif; ?>
										</tbody>
									</table>
								</div>
								<?php if ( ! wpforms()->is_pro() ) : ?>
									<div class="summary-lite-upgrade-wrapper">
										<table class="summary-lite-upgrade" border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
											<tbody>
												<tr>
													<td class="summary-lite-upgrade-card">
														<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
															<tbody>
																<?php if ( LiteConnect::is_enabled() ) : ?>
																	<tr>
																		<td align="center" valign="top" style="padding: 25px 30px 0 30px;">
																			<h4>
																				<?php esc_html_e( 'Your entries are being backed up, but only for one year.', 'wpforms-lite' ); ?>
																			</h4>
																			<p>
																				<?php esc_html_e( 'Lite Connect is saving your entries securely in the cloud. Upgrade to Pro to keep them permanently and manage all your entries right inside WordPress.', 'wpforms-lite' ); ?>
																			</p>
																		</td>
																	</tr>
																	<tr>
																		<td align="center" valign="middle" style="padding: 16px 30px 30px 30px;">
																			<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse: separate; margin: 0 auto;">
																				<tr>
																					<td class="button button-blue" align="center" border="1" valign="middle" style="background:#056aab; border:1px solid #056aab;">
																						<a href="<?php echo esc_url( wpforms_utm_link( 'https://wpforms.com/pricing-lite/', $utm_medium, 'Upgrade - With LC' ) ); ?>" class="button-link" rel="noopener noreferrer" target="_blank" bgcolor="#056aab">
																							<?php esc_html_e( 'Upgrade to Pro', 'wpforms-lite' ); ?>
																						</a>
																					</td>
																				</tr>
																			</table>
																		</td>
																	</tr>
																<?php else : ?>
																	<tr>
																		<td align="center" valign="top" style="padding: 25px 30px 0 30px;">
																			<h4>
																				<?php esc_html_e( 'Your entries are not being backed up.', 'wpforms-lite' ); ?>
																			</h4>
																			<p>
																				<?php
																				printf(
																					wp_kses(
																						/* translators: %1$s - WPForms.com Pricing page URL. */
																						__( 'Enable Lite Connect today to start storing entries. When you’re ready to manage your entries inside WordPress, just <a href="%1$s" target="_blank" rel="noopener noreferrer">Upgrade to Pro</a> and we’ll import them in seconds!', 'wpforms-lite' ),
																						[
																							'a' => [
																								'href'   => [],
																								'rel'    => [],
																								'target' => [],
																							],
																						]
																					),
																					esc_url( wpforms_utm_link( 'https://wpforms.com/pricing-lite/', $utm_medium, 'Upgrade - Without LC' ) )
																				);
																				?>
																			</p>
																		</td>
																	</tr>
																	<tr>
																		<td align="center" valign="middle" style="padding: 16px 30px 30px 30px;">
																			<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse: separate; margin: 0 auto;">
																				<tr>
																					<td class="button button-blue" align="center" border="1" valign="middle" style="background:#056aab; border:1px solid #056aab;">
																						<a href="<?php echo esc_url( wpforms_utm_link( 'https://wpforms.com/docs/how-to-use-lite-connect-for-wpforms/', $utm_medium, 'Documentation#backup-with-lite-connect' ) ); ?>" class="button-link" rel="noopener noreferrer" target="_blank" bgcolor="#056aab">
																							<?php esc_html_e( 'Enable Lite Connect', 'wpforms-lite' ); ?>
																						</a>
																					</td>
																				</tr>
																			</table>
																		</td>
																	</tr>
																<?php endif; ?>
															</tbody>
														</table>
													</td>
												</tr>
											</tbody>
										</table>
									</div>
								<?php endif; ?>
							</td>
							<td><!-- Deliberately empty to support consistent sizing and layout across multiple email clients. --></td>
						</tr>
					</tbody>
				</table>
			</td>
		</tr>
		<?php if ( ! empty( $notification_block ) ) : ?>
			<tr class="summary-notice" align="center">
				<td class="summary-notification-block" bgcolor="#edf3f7">
					<table class="summary-notification-table summary-notification-table" border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
						<tbody>
						<?php if ( ! empty( $icons['notification_block'] ) ) : ?>
							<tr>
								<td class="summary-notice-icon" align="center" valign="middle">
									<img src="<?php echo esc_url( $icons['notification_block'] ); ?>" width="52" height="52" alt="<?php esc_attr_e( 'Notification', 'wpforms-lite' ); ?>" />
								</td>
							</tr>
						<?php endif; ?>
						<?php if ( ! empty( $notification_block['title'] ) || ! empty( $notification_block['content'] ) ) : ?>
							<tr>
								<td class="summary-notice-content" align="center" valign="middle">
									<?php if ( ! empty( $notification_block['title'] ) ) : ?>
										<h4><?php echo esc_html( $notification_block['title'] ); ?></h4>
									<?php endif; ?>
									<?php if ( ! empty( $notification_block['content'] ) ) : ?>
										<p><?php echo wp_kses_post( $notification_block['content'] ); ?></p>
									<?php endif; ?>
								</td>
							</tr>
						<?php endif; ?>

						<?php if ( ! empty( $notification_block['btns'] ) ) : ?>
							<tr>
								<td class="button-container" align="center" valign="middle">
									<table class="button-wrapper" cellspacing="24">
										<tr>
											<?php if ( ! empty( $notification_block['btns']['main']['url'] ) && ! empty( $notification_block['btns']['main']['text'] ) ) : ?>
												<td class="button button-blue" align="center" border="1" valign="middle">
													<a href="<?php echo esc_url( $notification_block['btns']['main']['url'] ); ?>" class="button-link" rel="noopener noreferrer" target="_blank" bgcolor="#036aab">
														<?php echo esc_html( $notification_block['btns']['main']['text'] ); ?>
													</a>
												</td>
											<?php endif; ?>
											<?php if ( ! empty( $notification_block['btns']['alt']['url'] ) && ! empty( $notification_block['btns']['alt']['text'] ) ) : ?>
												<td class="button button-blue-outline" align="center" border="1" valign="middle">
													<a href="<?php echo esc_url( $notification_block['btns']['alt']['url'] ); ?>" class="button-link" rel="noopener noreferrer" target="_blank" bgcolor="#edf3f7">
														<?php echo esc_html( $notification_block['btns']['alt']['text'] ); ?>
													</a>
												</td>
											<?php endif; ?>
										</tr>
									</table>
								</td>
							</tr>
						<?php endif; ?>
						</tbody>
					</table>
				</td>
			</tr>
		<?php endif; ?>
		<?php if ( ! empty( $info_block ) ) : ?>
			<?php if ( ! empty( $notification_block ) ) : ?>
				<tr><td class="summary-notice-divider" height="1">&nbsp;</td></tr>
			<?php endif; ?>
			<tr class="summary-notice" align="center">
				<td class="summary-info-block" bgcolor="#f7f0ed">
					<table class="summary-info-table summary-notice-table" border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
						<tbody>
							<?php if ( ! empty( $icons['info_block'] ) ) : ?>
								<tr>
									<td class="summary-notice-icon" align="center" valign="middle">
										<img src="<?php echo esc_url( $icons['info_block'] ); ?>" width="52" height="52" alt="<?php esc_attr_e( 'Info', 'wpforms-lite' ); ?>" />
									</td>
								</tr>
							<?php endif; ?>
							<?php if ( ! empty( $info_block['title'] ) || ! empty( $info_block['content'] ) ) : ?>
								<tr>
									<td class="summary-notice-content" align="center" valign="middle">
										<?php if ( ! empty( $info_block['title'] ) ) : ?>
											<h4><?php echo esc_html( $info_block['title'] ); ?></h4>
										<?php endif; ?>
										<?php if ( ! empty( $info_block['content'] ) ) : ?>
											<p><?php echo wp_kses_post( $info_block['content'] ); ?></p>
										<?php endif; ?>
									</td>
								</tr>
							<?php endif; ?>

							<?php if ( ! empty( $info_block['url'] ) && ! empty( $info_block['button'] ) ) : ?>
								<tr>
									<td class="button-container" align="center" valign="middle">
										<table class="button-wrapper" cellspacing="24">
											<tr>
												<td class="button button-orange" align="center" border="1" valign="middle">
													<a href="<?php echo esc_url( $info_block['url'] ); ?>" class="button-link" rel="noopener noreferrer" target="_blank" bgcolor="#e27730">
														<?php echo esc_html( $info_block['button'] ); ?>
													</a>
												</td>
											</tr>
										</table>
									</td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>
				</td>
			</tr>
		<?php endif; ?>
	</tbody>
</table>

<?php
/**
 * Dashboard "WP Mail SMTP by WPForms" promo widget.
 *
 * Rendered as a single flat card: logo + title/badge + social proof on the top
 * row, body copy, a state-dependent CTA, and a dismiss button. The install CTA
 * and dismiss reuse Education Core's already-loaded handlers — no widget JS.
 *
 * @since 2.0.2
 *
 * @var string $variant         Widget variant: 'install' or 'setup'.
 * @var string $logo_url        WP Mail SMTP icon URL (1x).
 * @var string $install_url     WP Mail SMTP download URL (install CTA).
 * @var string $install_nonce   Nonce for the wpforms_install_addon AJAX action ('wpforms-admin').
 * @var string $redirect_url    Post-install redirect target (Setup Wizard).
 * @var string $setup_url       Setup Wizard URL (setup CTA).
 * @var string $dismiss_section Education dismiss section key (without the 'edu-' prefix).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wpforms-dashboard-wp-mail-smtp-top">
	<img
		class="wpforms-dashboard-wp-mail-smtp-logo"
		src="<?php echo esc_url( $logo_url ); ?>"
		alt="<?php esc_attr_e( 'WP Mail SMTP', 'wpforms-lite' ); ?>"
	/>

	<div class="wpforms-dashboard-wp-mail-smtp-text">
		<div class="wpforms-dashboard-wp-mail-smtp-titlerow">
			<span class="wpforms-dashboard-wp-mail-smtp-title"><?php esc_html_e( 'WP Mail SMTP by WPForms', 'wpforms-lite' ); ?></span>
			<span class="wpforms-dashboard-wp-mail-smtp-badge"><?php esc_html_e( 'Free', 'wpforms-lite' ); ?></span>
		</div>
		<p class="wpforms-dashboard-wp-mail-smtp-proof"><?php esc_html_e( '4,000,000+ Active Installs · ★ 4.8', 'wpforms-lite' ); ?></p>
	</div>
</div>

<p class="wpforms-dashboard-wp-mail-smtp-copy"><?php esc_html_e( 'Our free plugin makes sure your emails actually get delivered to your inbox.', 'wpforms-lite' ); ?></p>

<?php if ( $variant === 'setup' ) : ?>
	<a href="<?php echo esc_url( $setup_url ); ?>" class="wpforms-btn wpforms-btn-md wpforms-btn-blue wpforms-dashboard-wp-mail-smtp-cta">
		<?php esc_html_e( 'Set Up WP Mail SMTP', 'wpforms-lite' ); ?>
	</a>
<?php else : ?>
	<button
		type="button"
		class="wpforms-btn wpforms-btn-md wpforms-btn-blue wpforms-dashboard-wp-mail-smtp-cta education-modal"
		data-action="install"
		data-type="plugin"
		data-name="WP Mail SMTP"
		data-url="<?php echo esc_url( $install_url ); ?>"
		data-nonce="<?php echo esc_attr( $install_nonce ); ?>"
		data-redirect-url="<?php echo esc_url( $redirect_url ); ?>"
	>
		<?php esc_html_e( 'Install WP Mail SMTP', 'wpforms-lite' ); ?>
	</button>
<?php endif; ?>

<button
	type="button"
	class="wpforms-dismiss-button wpforms-dashboard-wp-mail-smtp-dismiss"
	data-section="<?php echo esc_attr( $dismiss_section ); ?>"
	title="<?php esc_attr_e( 'Dismiss', 'wpforms-lite' ); ?>"
>
	<i class="fa fa-times" aria-hidden="true"></i>
	<span class="screen-reader-text"><?php esc_html_e( 'Dismiss', 'wpforms-lite' ); ?></span>
</button>

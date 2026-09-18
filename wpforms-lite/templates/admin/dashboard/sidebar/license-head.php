<?php
/**
 * Dashboard "License / Update" widget — head (title row), Lite.
 *
 * The edition + version row with the pending-update CTA, plus the
 * "Upgrade to Pro" subtitle. The Pro edition ships `license-head-pro` with the
 * license problem states.
 *
 * @since 2.0.2
 *
 * @var string $edition_label    e.g. "WPForms Lite".
 * @var string $version          Plugin version.
 * @var bool   $update_available Whether a plugin update is pending.
 * @var string $upgrade_url      Upgrade-to-Pro URL.
 * @var string $plugins_url      WP Plugins page URL (Update Now).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wpforms-dashboard-license-titlerow">
	<span class="wpforms-dashboard-license-title">
		<?php echo esc_html( $edition_label ); ?>
		<span class="wpforms-dashboard-license-version"><?php echo esc_html( $version ); ?></span>
	</span>

	<?php if ( $update_available ) : ?>
		<a href="<?php echo esc_url( $plugins_url ); ?>" class="wpforms-btn wpforms-btn-sm wpforms-btn-blue">
			<?php esc_html_e( 'Update Now', 'wpforms-lite' ); ?>
		</a>
	<?php endif; ?>
</div>

<p class="wpforms-dashboard-license-upgrade-wrap">
	<a href="<?php echo esc_url( $upgrade_url ); ?>" class="wpforms-dashboard-arrow-link wpforms-dashboard-license-upgrade" target="_blank" rel="noopener noreferrer">
		<span class="wpforms-dashboard-arrow-link-text"><?php esc_html_e( 'Upgrade to Pro', 'wpforms-lite' ); ?></span>
		<span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
	</a>
</p>

<?php
/**
 * Dashboard Entries widget — Form Abandonment notice.
 *
 * A dismissible inset band below the table promoting the Form Abandonment addon.
 * The CTA is the shared install-link partial: an in-place Install/Activate on
 * Pro/Elite (entitled), or an Upgrade to Pro link on Lite/Basic/Plus.
 *
 * @since 2.0.2
 *
 * @var string $title Tier-dependent notice title.
 * @var array  $link  Shared install-link payload (text, url, action, plugin, external).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$promo_body = sprintf(
	/* translators: %s - Form Abandonment addon name. */
	esc_html__( 'Save partial-form submissions with our %s addon.', 'wpforms-lite' ),
	'<strong class="wpforms-dashboard-widget-entries-promo-brand">Form Abandonment</strong>'
);
?>
<div class="wpforms-dashboard-widget-entries-promo wpforms-dismiss-container">
	<span class="wpforms-dashboard-widget-entries-promo-icon">
		<img src="<?php echo esc_url( WPFORMS_PLUGIN_URL . 'assets/images/addon-icon-form-abandonment.png' ); ?>" alt="">
	</span>

	<div class="wpforms-dashboard-widget-entries-promo-text">
		<p class="wpforms-dashboard-widget-entries-promo-title"><?php echo esc_html( $title ); ?></p>
		<p class="wpforms-dashboard-widget-entries-promo-body">
			<?php echo $promo_body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Copy escaped above; brand markup is a static literal. ?>
			<?php
			// The addon-tiles.js module binds the in-place install CTA via the shared `wpforms-addon-tile__link` class.
			$cta = wpforms_render(
				'admin/addons/install-link',
				[
					'base_class' => 'wpforms-addon-tile__link wpforms-dashboard-widget-entries-promo-link',
					'link'       => $link,
				],
				true
			);

			echo $cta; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered template output, escaped within the partial.
			?>
		</p>
	</div>

	<button type="button" class="wpforms-dismiss-button wpforms-dashboard-widget-entries-promo-dismiss"
		data-section="dashboard-abandonment-promo" aria-label="<?php esc_attr_e( 'Dismiss', 'wpforms-lite' ); ?>">
		<i class="fa fa-times" aria-hidden="true"></i>
	</button>
</div>

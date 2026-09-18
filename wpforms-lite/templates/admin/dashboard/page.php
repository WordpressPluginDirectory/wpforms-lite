<?php
/**
 * Dashboard page shell template.
 *
 * @since 2.0.2
 *
 * @var string $datepicker        Date-range datepicker rendered HTML. Empty on Lite.
 * @var string $datepicker_notice Date-range readiness notice HTML. Empty on Lite and once the backfill completes.
 * @var string $stat_cards        Stat cards rendered HTML.
 * @var string $stat_cards_class  Count-driven row modifier class.
 * @var string $main_widgets      Main column widgets rendered HTML.
 * @var string $sidebar_widgets   Sidebar widgets rendered HTML.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="wpforms-dashboard" class="wrap wpforms-admin-wrap wpforms-dashboard-page">
	<h1 class="page-title">
		<?php esc_html_e( 'Dashboard', 'wpforms-lite' ); ?>

		<div class="wpforms-dashboard-title-actions">
			<?php echo $datepicker_notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo $datepicker; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	</h1>

	<div class="wpforms-admin-content">
		<div id="wpforms-dashboard-attention"></div>

		<div id="wpforms-dashboard-stat-cards" class="wpforms-dashboard-stat-cards <?php echo esc_attr( $stat_cards_class ); ?>">
			<?php echo $stat_cards; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>

		<div class="wpforms-dashboard-columns">
			<div id="wpforms-dashboard-column-main" class="wpforms-dashboard-column-main">
				<?php echo $main_widgets; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<div id="wpforms-dashboard-column-sidebar" class="wpforms-dashboard-column-sidebar">
				<?php echo $sidebar_widgets; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
	</div>
</div>
